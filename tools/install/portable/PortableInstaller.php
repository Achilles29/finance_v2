<?php
declare(strict_types=1);
require_once __DIR__.'/PortablePackage.php';
require_once __DIR__.'/PortableDatabase.php';
require_once __DIR__.'/PortableControl.php';
require_once dirname(__DIR__,2).'/licensing/FinanceLicenseAgent.php';
require_once dirname(__DIR__,3).'/application/libraries/DeploymentConfig.php';

final class PortableInstaller
{
    private PortableStore $private;
    private PortableStore $status;
    public function __construct(private string $root,private $transport=null)
    {
        PortableStore::writer($root);
        $this->private=new PortableStore($root,'private');$this->status=new PortableStore($root,'storage/setup');
    }
    public static function requirements(): array
    {
        $checks=['php_8_1'=>PHP_VERSION_ID>=80100&&PHP_VERSION_ID<80200,'platform'=>in_array(PHP_OS_FAMILY,['Linux','Windows'],true),'64_bit'=>PHP_INT_SIZE===8];
        foreach(['pdo_mysql','mysqli','sodium','curl','mbstring','json','openssl','zip','xml','session'] as $ext)$checks[$ext]=extension_loaded($ext);
        foreach($checks as $pass)if(!$pass)throw new RuntimeException('SERVER_REQUIREMENTS_MISSING');
        // Existing signed runtime contract makes fileinfo a WhatsApp MIME feature dependency, not installer-wide.
        return $checks+['optional_fileinfo'=>extension_loaded('fileinfo')];
    }
    private function credentials(array $p): array
    {
        $file=$this->root.'/private/delivery/credentials.json';
        if(!hash_equals($p['credentials_sha256']??'',hash_file('sha256',$file)))throw new RuntimeException('DELIVERY_CREDENTIAL_BINDING_INVALID');
        $v=CustomerPlatform::document($this->root,$file);
        if(($v['instance_id']??'')!==$p['instance_id'] || ($v['environment']??'')!==$p['environment'])throw new RuntimeException('DELIVERY_CREDENTIAL_BINDING_INVALID');
        ControlLicenseProtocol::origin($v['control_origin']??'');return $v;
    }
    private function agent(): FinanceLicenseAgent
    {
        return new FinanceLicenseAgent(new PortableStore($this->root,'private/agent'),new PortableStore($this->root,'storage/license'),
            CustomerPlatform::fingerprint(),[$this->control(),'post']);
    }
    private function control(): PortableControl
    {
        $c=$this->private->read('delivery-state.json');
        return new PortableControl($this->root,$c['credential']['control_origin'],$this->transport);
    }
    private function update(string $phase,int $percent,string $code=''): array
    {
        $s=['phase'=>$phase,'percent'=>$percent,'code'=>$code,'updated_at'=>gmdate(DATE_ATOM),'database_started'=>$this->private->exists('database.json')];
        $this->status->write('status.json',$s,0640);return $s;
    }
    /** Admin runs once as dedicated unprivileged installer; renewal preserves all evidence and DB journal. */
    public function prepare(): array
    {
        self::requirements();$lock=$this->private->lock();
        try {
            if($this->private->exists('complete.json'))return ['phase'=>'COMPLETE','percent'=>100];
            $context=PortablePackage::verify($this->root);$permit=PortablePackage::permit($this->root,$context);$credential=$this->credentials($permit);
            if($context['distribution_profile_version']>=11&&!is_file($this->root.'/storage/setup/application-update.lock')){
                // Never replace a live lock inode. Future requests share this stable file.
                $gate=$this->status->lock('application-update.lock');
                chmod($this->root.'/storage/setup/application-update.lock',0640);
                chgrp($this->root.'/storage/setup/application-update.lock',filegroup($this->root));flock($gate,LOCK_UN);fclose($gate);
            }
            if($this->private->exists('delivery-state.json')) {
                $old=$this->private->read('delivery-state.json');
                foreach(['instance_id','deployment_id','plan_sha256','release_public_id','source_commit','artifact_sha256','release_manifest_sha256','profile_sha256','profile_version','environment'] as $key)
                    if(($old['permit'][$key]??null)!==($permit[$key]??null))throw new RuntimeException('REPLACEMENT_PERMISSION_BINDING_INVALID');
                if($old['permit']['permit_id']===$permit['permit_id'] && $old['permit']!==$permit)throw new RuntimeException('REPLACEMENT_PERMISSION_BINDING_INVALID');
                if($old['permit']['permit_id']!==$permit['permit_id'])$this->private->write('attempt-'.bin2hex(random_bytes(12)).'.json',$old);
            } else {
                if(!$this->private->exists('setup-key.json')){
                    $box=sodium_crypto_box_keypair();$this->private->write('setup-key.json',['key'=>base64_encode($box)]);sodium_memzero($box);
                }
            }
            $this->private->write('delivery-state.json',['context'=>$context,'permit'=>$permit,'credential'=>$credential]);
            $agentStore=new PortableStore($this->root,'private/agent');
            if(!$agentStore->exists('agent.json'))$this->agent()->initialize($permit['instance_id'],$credential['control_origin'],$credential['license_trust']);
            else foreach(['identity.json','trust.json','runtime.json']as$name)
                if(!(new PortableStore($this->root,'storage/license'))->exists($name))throw new RuntimeException('INITIALIZATION_REVIEW_REQUIRED');
            $key=base64_decode($this->private->read('setup-key.json')['key'],true);
            $this->status->write('browser.json',['contract'=>'FINANCE_SETUP_BROWSER_V1','permit_id'=>$permit['permit_id'],
                'secret_sha256'=>$permit['setup_secret_sha256'],'expires_at'=>$permit['expires_at'],
                'permission_policy'=>$permit['permission_policy']??'FIXED_EXPIRY','profile_version'=>$permit['profile_version'],
                'public_key'=>base64_encode(sodium_crypto_box_publickey($key))],0640);
            sodium_memzero($key);
            if($this->status->exists('status.json'))return $this->status->read('status.json',0640);
            return $this->update('READY',10);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function job(array $permit): array
    {
        if($this->private->exists('job.json'))return $this->private->read('job.json');
        $file=$this->root.'/storage/inbox/request.json';
        if(!is_file($file)||is_link($file)||filesize($file)>65536||str_replace('\\','/',(string)realpath($file))!==$file)throw new RuntimeException('WAITING_FOR_CUSTOMER');
        $packet=json_decode(file_get_contents($file),true,8,JSON_THROW_ON_ERROR);
        $key=base64_decode($this->private->read('setup-key.json')['key'],true);
        $cipher=base64_decode($packet['sealed']??'',true);
        $raw=is_string($cipher)?sodium_crypto_box_seal_open($cipher,$key):false;sodium_memzero($key);
        if(!is_string($raw))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $job=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(($job['permit_id']??'')!==$permit['permit_id']||!is_string($job['secret']??null)||!hash_equals($permit['setup_secret_sha256'],hash('sha256',$job['secret'])))throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        PortableDatabase::owner($job['owner']??[]);
        unset($job['secret']);$this->private->write('job.json',$job);return $job;
    }
    public function run(): array
    {
        $lock=$this->private->lock();
        try {
            if($this->private->exists('complete.json'))return $this->update('COMPLETE',100);
            self::requirements();
            $delivery=$this->private->read('delivery-state.json');$context=PortablePackage::verify($this->root);
            // Expiry is an authorization check before starting a new attempt, not a purchase/install deadline.
            $permit=PortablePackage::permit($this->root,$context,!$this->private->exists('job.json'));
            if($permit!==$delivery['permit'])throw new RuntimeException('DELIVERY_PREPARE_REQUIRED');
            $job=$this->job($permit);$credential=$delivery['credential'];
            if(!$this->private->exists('configured.json')) {
                $config=self::configuration($job['config']??[]);
                DeploymentConfig::previewCustomer($this->root,$config);
                PortableDatabase::version(PortableDatabase::connect($config['database']));
                PortableStore::writeFile($this->root,$this->root.'/config/customer.json',$config,0640);
                $snapshot=DeploymentConfig::forRoot($this->root)->values();
                $connection=PortableDatabase::connect($config['database']);
                if(!$this->private->exists('database.json'))PortableDatabase::emptyDatabase($connection);
                unset($connection);
                $this->private->write('configured.json',['sha256'=>hash_file('sha256',$this->root.'/config/customer.json')]);
            }
            $snapshot=DeploymentConfig::forRoot($this->root)->values();
            $config=CustomerPlatform::document($this->root,$this->root.'/config/customer.json');
            if(!hash_equals($this->private->read('configured.json')['sha256'],hash_file('sha256',$this->root.'/config/customer.json')))throw new RuntimeException('CONFIG_CHANGED_DURING_INSTALL');
            $this->update('ACTIVATING',25);
            $agent=$this->agent();$agentState=(new PortableStore($this->root,'private/agent'))->read('agent.json');
            if(in_array($agentState['activation_state'],['NOT_REQUESTED','INPUT_REJECTED','DENIED'],true))$agent->activate($credential['activation_code']);
            elseif($agentState['activation_state']==='REQUEST_UNCERTAIN')$agent->recover($credential['activation_code']);
            $v=$agent->poll();
            if(empty($v['verified'])||!in_array($v['status'],['ACTIVE','GRACE'],true))return $this->update('WAITING_ACTIVATION',30,$v['code']);
            $pub=new PortableStore($this->root,'storage/license');$context['identity']=$pub->read('identity.json',0640);
            $bound=Control_license_verifier::verify($pub->read('runtime.json',0640)['envelope'],$pub->read('trust.json',0640),
                $context['identity']+['machine_fingerprint_sha256'=>CustomerPlatform::fingerprint()]);
            if(empty($bound['verified'])||!in_array($bound['status'],['ACTIVE','GRACE'],true))throw new RuntimeException('LICENSE_MACHINE_BINDING_REQUIRED');
            $this->update('DATABASE',40);
            $health=PortableDatabase::install($this->root,$config['database'],$job['owner'],$context['release_manifest_sha256'],
                fn(int $n,int $total)=>$this->update('DATABASE',40+(int)(40*$n/$total)));
            PortableStore::writeFile($this->root,$this->root.'/storage/customer-installation.json',$context,0640);
            $this->update('WEB_CHECK',85);$this->control()->web($snapshot['FINANCE_BASE_URL']);
            $this->update('REPORTING',92);$this->receipt($context,$permit,$credential,$health);
            $this->private->write('complete.json',['completed_at'=>gmdate(DATE_ATOM),'release_manifest_sha256'=>$context['release_manifest_sha256'],'health'=>$health]);
            $this->status->write('closed.json',['closed'=>true],0640);
            return $this->update('COMPLETE',100);
        } catch(Throwable $e) {
            $code=preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'INSTALL_STEP_FAILED';
            $progress=$this->status->exists('status.json')?(int)($this->status->read('status.json')['percent']??10):10;
            $this->update($code==='WAITING_FOR_CUSTOMER'?'READY':'ATTENTION',$progress,$code);
            throw new RuntimeException($code);
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }
    private function receipt(array $c,array $p,array $credential,array $health): void
    {
        if($this->private->exists('receipt-ack.json'))return;
        $app=json_decode(file_get_contents($this->root.'/app-manifest.json'),true);
        if($this->private->exists('receipt.json'))$r=$this->private->read('receipt.json');else {
            $r=['receipt_id'=>self::uuid(),'instance_id'=>$p['instance_id'],'occurred_at'=>gmdate(DATE_ATOM),
                'receipt_type'=>'RELEASE_ACTIVATION','status'=>'SUCCEEDED','environment'=>$p['environment'],'app_version'=>$c['version'],
                'from_schema'=>$app['baseline_schema_version'],'to_schema'=>$app['schema_version'],'migration_versions'=>$health['migrations'],
                'release_manifest_sha256'=>$c['release_manifest_sha256'],'artifact_sha256'=>$c['artifact_sha256'],'backup_sha256'=>null];
            $this->private->write('receipt.json',$r);
        }
        $ack=$this->control()->send('/api/v1/deployment-receipts',$r,$credential['monitoring'],'finance-receipt-'.$r['receipt_id']);
        $this->private->write('receipt-ack.json',$ack);
    }
    /** Separate scheduled command. A heartbeat is never a license grant. */
    public function sync(): array
    {
        $v=$this->licenseSync();$this->heartbeat();return ['license'=>$v,'heartbeat'=>'ACKNOWLEDGED'];
    }
    public function licenseSync(): array { return $this->agent()->poll(); }
    public function heartbeat(): array
    {
        if(!$this->private->exists('complete.json'))throw new RuntimeException('INSTALLATION_NOT_COMPLETE');
        $delivery=$this->private->read('delivery-state.json');$c=$delivery['context'];$p=$delivery['permit'];
        $settings=DeploymentConfig::forRoot($this->root)->values();$dbOk=false;$webOk=false;
        try{$config=CustomerPlatform::document($this->root,$this->root.'/config/customer.json');PortableDatabase::connect($config['database'])->query('SELECT 1');$dbOk=true;}catch(Throwable $e){}
        try{$this->control()->web($settings['FINANCE_BASE_URL']);$webOk=true;}catch(Throwable $e){}
        $host=strtolower(trim((string)parse_url($settings['FINANCE_BASE_URL'],PHP_URL_HOST),'[]'));
        if(strlen($host)>190)throw new RuntimeException('RUNTIME_DOMAIN_INVALID');
        $app=json_decode(file_get_contents($this->root.'/app-manifest.json'),true);
        $payload=['instance_id'=>$p['instance_id'],'sent_at'=>gmdate(DATE_ATOM),'app_version'=>$c['version'],'schema_version'=>$app['schema_version'],
            'environment'=>$p['environment'],'health'=>$dbOk&&$webOk?'OK':'DEGRADED',
            'components'=>['database'=>$dbOk?'OK':'DEGRADED','http'=>$webOk?'OK':'DEGRADED','schema_migration'=>'UNKNOWN','runtime_queue'=>'UNKNOWN','backup'=>'UNKNOWN'],
            'metrics'=>['queue_pending'=>0,'queue_failed'=>0],'runtime'=>['primary_domain'=>$host]];
        $ack=$this->control()->send('/api/v1/heartbeats',$payload,$delivery['credential']['monitoring'],'hb-'.bin2hex(random_bytes(16)));
        $this->private->write('heartbeat.json',['sent_at'=>gmdate(DATE_ATOM),'status'=>'ACKNOWLEDGED']);
        return ['heartbeat'=>'ACKNOWLEDGED'];
    }

    public static function configuration(array $input): array
    {
        if(array_diff(array_keys($input),['database','base_url'])||count($input)!==2)throw new RuntimeException('CUSTOMER_CONFIG_INCOMPLETE');
        return $input+['schema'=>1,'encryption_key'=>bin2hex(random_bytes(32)),'runtime'=>['directory'=>'storage','session_cookie'=>'finance_session']];
    }

    /** Only the non-root companion calls this after authenticating a sealed UI request and DB probe. */
    public function submit(array $job,bool $replace=false): void
    {
        PortableDatabase::owner($job['owner']??[]);
        DeploymentConfig::previewCustomer($this->root,self::configuration($job['config']??[]));
        if($this->private->exists('job.json')) {
            $old=$this->private->read('job.json');
            if(($old['config']??null)===($job['config']??null)&&($old['owner']??null)===($job['owner']??null))return;
            if(!$replace)throw new RuntimeException('SETUP_REQUEST_EXISTS');
            $this->retryInput();
        }
        $lock=$this->private->lock();
        try {
            if($this->private->exists('database.json')||$this->private->exists('complete.json'))throw new RuntimeException('DATABASE_REVIEW_REQUIRED');
            $this->private->write('job.json',['config'=>$job['config'],'owner'=>$job['owner']]);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    /** Explicit admin recovery BEFORE any SQL; preserve submissions, do not rotate installation keys. */
    public function retryInput(): array
    {
        $lock=$this->private->lock();
        try {
            if($this->private->exists('database.json')||$this->private->exists('complete.json'))throw new RuntimeException('DATABASE_REVIEW_REQUIRED');
            $tag=bin2hex(random_bytes(8));
            foreach(['job.json','configured.json']as$name)if($this->private->exists($name)) {
                if(!rename($this->root.'/private/'.$name,$this->root.'/private/previous-'.$tag.'-'.$name))throw new RuntimeException('RECOVERY_ARCHIVE_FAILED');
            }
            $queue=$this->root.'/storage/inbox/request.json';
            if(is_link($queue))throw new RuntimeException('INBOX_UNSAFE');
            if(is_file($queue) && !rename($queue,$this->root.'/private/previous-'.$tag.'-request.json'))throw new RuntimeException('RECOVERY_ARCHIVE_FAILED');
            return $this->update('READY',10);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function uuid(): string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
    }
}
