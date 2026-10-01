<?php
declare(strict_types=1);
require_once __DIR__.'/PrivateDeployment.php';
require_once dirname(__DIR__).'/licensing/ControlLicenseProtocol.php';
require_once dirname(__DIR__).'/release/ControlReleaseBridge.php';

/** Claim/download/receipt client. Does not execute downloaded code or automatically publish a release. */
final class ControlDelivery
{
    private string $dir;
    private array $config;
    private $transport;
    public function __construct(string $directory,array $config,?callable $transport=null)
    {
        PrivateDeployment::directory($directory);$this->dir=$directory;$this->config=$config;$this->transport=$transport;
        ControlLicenseProtocol::origin($config['control_origin']??'');
        if(preg_match('/\Andi_[A-Za-z0-9_-]{48}\z/D',$config['install_token']??'')!==1
            ||preg_match('/\A[a-z0-9][a-z0-9_-]{2,79}\z/D',$config['instance_id']??'')!==1)throw new RuntimeException('DELIVERY_CONFIG_INVALID');
        if(isset($config['ca_file']))LicenseAgentFiles::securePath($config['ca_file'],dirname(__DIR__,2));
    }
    public static function validatePlan(array $p,array $expected): array
    {
        if(($p['status']??'')!=='accepted'||($p['product_code']??'')!=='NAMUA_FINANCE'||($p['instance_id']??'')!==$expected['instance_id']
            ||!in_array($p['action']??'',['DEPLOY','ROLLBACK'],true)
            ||!in_array($p['environment']??'',['DEMO','STAGING','PRODUCTION'],true)
            ||preg_match('/\A[a-f0-9-]{36}\z/D',$p['deployment_id']??'')!==1||preg_match('/\A[a-f0-9]{64}\z/D',$p['plan_sha256']??'')!==1
            ||!is_array($p['artifacts']??null)||count($p['artifacts'])!==3)throw new RuntimeException('PLAN_BINDING_INVALID');
        foreach(['deployment_id','plan_sha256'] as $key)if(isset($expected[$key])&&$p[$key]!==$expected[$key])throw new RuntimeException('PLAN_BINDING_INVALID');
        foreach(['manifest_sha256','artifact_sha256'] as $key)if(preg_match('/\A[a-f0-9]{64}\z/D',$p['release'][$key]??'')!==1)throw new RuntimeException('PLAN_RELEASE_INVALID');
        if(preg_match('/\A\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?\z/D',$p['release']['version']??'')!==1)throw new RuntimeException('PLAN_RELEASE_INVALID');
        if (array_key_exists('distribution_profile', $p['release'])) {
            if (($p['release']['distribution_profile'] ?? '') !== CustomerReleaseProfile::ID
                || !in_array($p['release']['distribution_profile_version'] ?? null, [1,2,3,4,5,6,7,8,9,10,11], true)
                || ($p['release']['seed_profile'] ?? '') !== 'REFERENCE_ONLY'
                || preg_match('/\A[a-f0-9]{64}\z/D', $p['release']['customer_content_profile_sha256'] ?? '') !== 1) throw new RuntimeException('PLAN_CUSTOMER_PROFILE_INVALID');
        }
        $byType=[];
        foreach($p['artifacts'] as $a){
            if(!is_array($a)||!in_array($a['type']??'',['APPLICATION_PACKAGE','MANIFEST','OTHER'],true)||isset($byType[$a['type']])
                ||preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{1,180}\z/D',$a['filename']??'')!==1||strpos($a['filename'],'..')!==false
                ||preg_match('/\A[a-f0-9]{64}\z/D',$a['sha256']??'')!==1||!is_int($a['size_bytes']??null)||$a['size_bytes']<1
                ||$a['size_bytes']>($a['type']==='APPLICATION_PACKAGE'?ControlReleaseBridge::MAX_BYTES:1048576))throw new RuntimeException('PLAN_ARTIFACT_INVALID');
            $byType[$a['type']]=$a;
        }
        $stem=substr($byType['APPLICATION_PACKAGE']['filename'],0,-4);
        if(substr($byType['APPLICATION_PACKAGE']['filename'],-4)!=='.tar'||$byType['MANIFEST']['filename']!==$stem.'.release.json'||$byType['OTHER']['filename']!==$stem.'.release.sig.json'
            ||$byType['APPLICATION_PACKAGE']['sha256']!==$p['release']['artifact_sha256']||$byType['MANIFEST']['sha256']!==$p['release']['manifest_sha256'])throw new RuntimeException('PLAN_ARTIFACT_BINDING');
        return $byType;
    }
    // Registry domain is metadata, never a local configuration or credential binding.
    private function binding(): string
    {
        $config=$this->config;unset($config['primary_domain']);ksort($config);
        return hash('sha256',json_encode($config,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    private function readState(): array
    {
        $state=PrivateDeployment::read($this->dir.'/delivery.json');
        $legacy=($state['schema']??null)===1;
        $binding=$legacy?hash('sha256',json_encode($this->config,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)):$this->binding();
        if(!in_array($state['schema']??null,[1,2],true)||($state['binding']??'')!==$binding)throw new RuntimeException('DELIVERY_STATE_MISMATCH');
        if($legacy){
            $backup=$this->dir.'/delivery-v1.json';
            if(file_exists($backup)||is_link($backup)){if(PrivateDeployment::read($backup)!==$state)throw new RuntimeException('LEGACY_STATE_ARCHIVE_CONFLICT');}
            else PrivateDeployment::write($backup,$state);
            $state['legacy_binding']=$state['binding'];$state['schema']=2;$state['binding']=$this->binding();
            PrivateDeployment::write($this->dir.'/delivery.json',$state);
        }
        return $state;
    }
    public static function recoveryMessage(string $code): string
    {
        if($code==='DELIVERY_ALREADY_VERIFIED')return 'Paket sudah diverifikasi. Gunakan artefak dan journal yang ada; tidak perlu token unduh baru. Periksa instance.json serta health/riwayat migrasi sebelum melanjutkan instalasi, jangan ulang SQL atau reset state.';
        if(in_array($code,['DELIVERY_CONFIG_INVALID','INSTALL_TOKEN_REJECTED','CLAIM_REQUIRES_REVIEW','CLAIM_RESPONSE_LOST_REQUIRES_CONTROL_REVIEW','DELIVERY_STATE_MISMATCH','REPLACEMENT_TOKEN_REQUIRED'],true))
            return 'Token instalasi ditolak/tidak dapat dipastikan: bisa tidak valid, kedaluwarsa, dicabut, atau sudah dipakai. Ini bukan bukti hak instalasi berakhir. Simpan job, delivery.json dan file partial. Minta operator Control memeriksa deployment dan menerbitkan credential pengganti; gunakan mode replace dengan job lama dan job baru pada state_dir baru. Jangan hapus journal atau mengulang SQL. Jika instance.json berstatus INSTALLING/terpasang atau database sudah berisi, periksa migrasi dan health lebih dahulu.';
        return 'Simpan journal dan file partial untuk pemeriksaan Control. Jangan paksa token lama, reset identitas/instance, atau ulangi SQL yang mungkin sudah berhasil. Periksa instance.json, riwayat migrasi dan health sebelum melanjutkan instalasi.';
    }
    /** Prepare a separate download attempt only. Never stage, install, reset, or mutate a database. */
    public function replacement(string $directory,array $config): array
    {
        $next=new self($directory,$config);
        if($directory===$this->dir)throw new RuntimeException('REPLACEMENT_DIRECTORY_REQUIRED');
        $oldLock=PrivateDeployment::lock($this->dir);$newLock=null;
        try{
            $newLock=PrivateDeployment::lock($directory);$old=$this->readState();
            if(file_exists($directory.'/delivery.json')||is_link($directory.'/delivery.json'))throw new RuntimeException('REPLACEMENT_STATE_EXISTS');
            // A new attempt must not inherit a receipt, archive, partial download or instance journal.
            foreach(scandir($directory)?:[] as $entry)if(!in_array($entry,['.','..','operation.lock','job.json'],true))throw new RuntimeException('REPLACEMENT_DIRECTORY_NOT_FRESH');
            $before=$this->config;$after=$config;unset($before['install_token'],$before['primary_domain'],$after['install_token'],$after['primary_domain']);
            ksort($before);ksort($after);
            if($before!==$after)throw new RuntimeException('REPLACEMENT_BINDING_MISMATCH');
            $previousCredentials=array_merge($old['previous_credentials']??[],[hash('sha256',$this->config['install_token'])]);
            if(in_array(hash('sha256',$config['install_token']),$previousCredentials,true))throw new RuntimeException('REPLACEMENT_TOKEN_REQUIRED');
            if(count($previousCredentials)>100)throw new RuntimeException('REPLACEMENT_HISTORY_REQUIRES_REVIEW');
            if(($old['phase']??'')==='VERIFIED'||is_file($this->dir.'/receipt.json'))throw new RuntimeException('DELIVERY_ALREADY_VERIFIED');
            $state=['schema'=>2,'binding'=>$next->binding(),'phase'=>'NEW','downloads'=>[],'previous_credentials'=>$previousCredentials,
                'previous_attempt'=>['state_dir'=>$this->dir,'journal_sha256'=>hash_file('sha256',$this->dir.'/delivery.json'),'phase'=>$old['phase'],'at'=>gmdate(DATE_ATOM)]];
            if(isset($old['plan']))$state['previous_plan']=$old['plan'];
            elseif(isset($old['previous_plan']))$state['previous_plan']=$old['previous_plan'];
            PrivateDeployment::write($directory.'/delivery.json',$state);
            return ['status'=>'REPLACEMENT_PREPARED','installed'=>false,'database_changed'=>false,'previous_evidence_preserved'=>true];
        }finally{if($newLock){flock($newLock,LOCK_UN);fclose($newLock);}flock($oldLock,LOCK_UN);fclose($oldLock);}
    }
    private static function validateReplacementPlan(array $plan,array $previous): void
    {
        // Domain is deliberately absent; every security/release field must remain identical.
        foreach(['instance_id','deployment_id','plan_sha256','product_code','action','environment','release'] as $key)
            if(($plan[$key]??null)!==($previous[$key]??null))throw new RuntimeException('REPLACEMENT_PLAN_MISMATCH');
        if(self::validatePlan($plan,$previous)!==self::validatePlan($previous,$previous))throw new RuntimeException('REPLACEMENT_PLAN_MISMATCH');
    }
    private function http(string $path,array $payload,array $headers=[],?array $artifact=null): array
    {
        if(!in_array($path,['/api/v1/install-plans/claim','/api/v1/install-plans/artifact','/api/v1/deployment-receipts'],true))throw new RuntimeException('DELIVERY_PATH_INVALID');
        if($this->transport!==null)return ($this->transport)($path,$payload,$headers,$artifact);
        $body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$response='';$written=0;$h=null;
        $partial=$artifact?$this->dir.'/'.$artifact['filename'].'.partial':null;
        if($partial!==null){if(file_exists($partial)||is_link($partial))throw new RuntimeException('PARTIAL_DOWNLOAD_REQUIRES_REVIEW');$h=fopen($partial,'xb');if(!$h)throw new RuntimeException('DOWNLOAD_FILE_FAILED');chmod($partial,0600);}
        $c=curl_init($this->config['control_origin'].$path);
        curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$artifact?600:20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
            CURLOPT_WRITEFUNCTION=>static function($c,string $chunk)use(&$response,&$written,$h,$artifact):int{
                if($artifact&&(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE)!==200){if(strlen($response)+strlen($chunk)>1048576)return 0;$response.=$chunk;return strlen($chunk);}
                $n=strlen($chunk);if($written+$n>($artifact?$artifact['size_bytes']:1048576))return 0;
                if($h){if(fwrite($h,$chunk)!==$n)return 0;}else{$response.=$chunk;}$written+=$n;return $n;
            }]);
        if(isset($this->config['ca_file']))curl_setopt($c,CURLOPT_CAINFO,$this->config['ca_file']);
        $ok=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
        if($h){$flushed=fflush($h)&&fsync($h);fclose($h);if(!$flushed)throw new RuntimeException('DOWNLOAD_FLUSH_FAILED');}
        if($ok===false)throw new RuntimeException('DELIVERY_NETWORK_UNCERTAIN');
        if($status>=300&&$status<400)throw new RuntimeException('DELIVERY_REDIRECT_REJECTED');
        if($artifact){
            if($status===401&&in_array(json_decode($response,true)['code']??'',['install_token_invalid','artifact_token_invalid'],true))throw new RuntimeException('INSTALL_TOKEN_REJECTED');
            if($status!==200||$written!==$artifact['size_bytes']||hash_file('sha256',$partial)!==$artifact['sha256'])throw new RuntimeException('DOWNLOAD_INTEGRITY_FAILED');
            if(!rename($partial,$this->dir.'/'.$artifact['filename']))throw new RuntimeException('DOWNLOAD_PUBLISH_FAILED');
            return ['status'=>'DOWNLOADED'];
        }
        $json=json_decode($response,true,64,JSON_THROW_ON_ERROR);if(!is_array($json))throw new RuntimeException('DELIVERY_RESPONSE_INVALID');
        return ['http'=>$status,'json'=>$json];
    }
    public function fetch(string $trustFile): array
    {
        $lock=PrivateDeployment::lock($this->dir);$file=$this->dir.'/delivery.json';
        try{
            $state=is_file($file)?$this->readState():['schema'=>2,'binding'=>$this->binding(),'phase'=>'NEW','downloads'=>[]];
            if($state['phase']==='NEW'){
                $state['phase']='CLAIM_UNCERTAIN';PrivateDeployment::write($file,$state);
                $r=$this->http('/api/v1/install-plans/claim',['install_token'=>$this->config['install_token']]);
                if($r['http']!==200){
                    $rejected=$r['http']===401&&($r['json']['code']??'')==='install_token_invalid';
                    $state['phase']=$rejected?'TOKEN_REJECTED':'CLAIM_UNCERTAIN';
                    $state['failure']=['http'=>$r['http'],'code'=>$rejected?'INSTALL_TOKEN_REJECTED':'CLAIM_REQUIRES_REVIEW','at'=>gmdate(DATE_ATOM)];
                    PrivateDeployment::write($file,$state);throw new RuntimeException($state['failure']['code']);
                }
                // Preserve the authenticated response even if binding validation fails.
                $state['claim_response']=$r['json'];PrivateDeployment::write($file,$state);
                self::validatePlan($r['json'],$this->config);
                if(isset($state['previous_plan']))self::validateReplacementPlan($r['json'],$state['previous_plan']);
                $state['plan']=$r['json'];$state['phase']='CLAIMED';PrivateDeployment::write($file,$state);
            }
            if($state['phase']==='TOKEN_REJECTED'||($state['failure']['code']??'')==='INSTALL_TOKEN_REJECTED')throw new RuntimeException('INSTALL_TOKEN_REJECTED');
            if(!isset($state['plan']))throw new RuntimeException('CLAIM_RESPONSE_LOST_REQUIRES_CONTROL_REVIEW');
            $artifacts=self::validatePlan($state['plan'],$this->config);
            foreach(['MANIFEST','OTHER','APPLICATION_PACKAGE'] as $type){$a=$artifacts[$type];$path=$this->dir.'/'.$a['filename'];
                if(is_file($path)&&!is_link($path)&&filesize($path)===$a['size_bytes']&&hash_file('sha256',$path)===$a['sha256']){$state['downloads'][$type]='COMPLETE';continue;}
                if(file_exists($path)||is_link($path)||isset($state['downloads'][$type]))throw new RuntimeException('DOWNLOAD_UNCERTAIN_REQUIRES_CONTROL_REVIEW');
                $state['downloads'][$type]='REQUEST_UNCERTAIN';PrivateDeployment::write($file,$state);
                try{$this->http('/api/v1/install-plans/artifact',['install_token'=>$this->config['install_token'],'artifact_type'=>$type],[],$a);}
                catch(RuntimeException $error){
                    $state['failure']=['code'=>$error->getMessage()==='INSTALL_TOKEN_REJECTED'?'INSTALL_TOKEN_REJECTED':'DOWNLOAD_REQUIRES_REVIEW','artifact_type'=>$type,'at'=>gmdate(DATE_ATOM)];
                    PrivateDeployment::write($file,$state);throw $error;
                }
                $state['downloads'][$type]='COMPLETE';PrivateDeployment::write($file,$state);
            }
            $manifest=$this->dir.'/'.$artifacts['MANIFEST']['filename'];$bytes=(string)file_get_contents($manifest);
            $verified=ControlReleaseBridge::verify($bytes,basename($manifest),ControlReleaseBridge::json((string)file_get_contents($this->dir.'/'.$artifacts['OTHER']['filename'])),ControlReleaseBridge::loadKey($trustFile),$this->dir.'/'.$artifacts['APPLICATION_PACKAGE']['filename']);
            if($verified['version']!==$state['plan']['release']['version'])throw new RuntimeException('DELIVERED_VERSION_MISMATCH');
            self::validateCustomerBinding($state['plan'], $verified);
            $state['phase']='VERIFIED';$state['verification']=$verified;PrivateDeployment::write($file,$state);
            return ['status'=>'VERIFIED','version'=>$verified['version'],'signed_manifest'=>$manifest,'deployment_id'=>$state['plan']['deployment_id'],'installed'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function validateCustomerBinding(array $plan, array $verified): void
    {
        $release = $plan['release'] ?? [];
        if (!empty($verified['customer_clean_eligible'])) {
            foreach (['distribution_profile', 'distribution_profile_version', 'seed_profile'] as $key) {
                if (($release[$key] ?? null) !== ($verified[$key] ?? null)) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
            }
            if (($release['customer_content_profile_sha256'] ?? '') !== ($verified['customer_content_audit']['profile_sha256'] ?? null)) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
        } elseif (isset($release['distribution_profile'])) throw new RuntimeException('DELIVERED_CUSTOMER_PROFILE_MISMATCH');
    }

    public function receipt(array $identity,array $result): array
    {
        $lock=PrivateDeployment::lock($this->dir);
        try{
            $state=$this->readState();$p=$state['plan']??[];
            self::validatePlan($p,$this->config);
            if(($state['phase']??'')!=='VERIFIED'||($identity['instance_id']??'')!==($p['instance_id']??null)||($identity['environment']??'')!==($p['environment']??null)
                ||preg_match('/\A[a-f0-9-]{36}\z/D',$identity['key_id']??'')!==1||!is_string($identity['secret']??null)||strlen($identity['secret'])<32
                ||($result['status']??'')!=='PASS'||($result['web_verified']??false)!==true||($result['version']??'')!==$p['release']['version']
                ||($result['artifact_sha256']??'')!==$p['release']['artifact_sha256']||($result['health']['status']??'')!=='ok')throw new RuntimeException('RECEIPT_EVIDENCE_REQUIRED');
            $path=$this->dir.'/receipt.json';$from=$result['from_schema']??'';$to=$result['to_schema']??'';
            if(preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D',$from)!==1||preg_match('/\A[A-Za-z0-9._-]{1,80}\z/D',$to)!==1)throw new RuntimeException('RECEIPT_SCHEMA_REQUIRED');
            if(is_file($path))$r=PrivateDeployment::read($path);else{
                $r=['receipt_id'=>PrivateDeployment::uuid(),'instance_id'=>$identity['instance_id'],'occurred_at'=>gmdate(DATE_ATOM),
                    'receipt_type'=>$p['action']==='ROLLBACK'?'RELEASE_ROLLBACK':'RELEASE_ACTIVATION','status'=>'SUCCEEDED','environment'=>$p['environment'],
                    'app_version'=>$p['release']['version'],'from_schema'=>$from,'to_schema'=>$to,'migration_versions'=>$result['migration_versions']??[],
                    'release_manifest_sha256'=>$p['release']['manifest_sha256'],'artifact_sha256'=>$p['release']['artifact_sha256'],'backup_sha256'=>$result['backup_sha256']??null];
                PrivateDeployment::write($path,$r);
            }
            $timestamp=gmdate(DATE_ATOM);$nonce=bin2hex(random_bytes(24));$idempotency='finance-receipt-'.$r['receipt_id'];
            $canonical=implode("\n",['POST','/api/v1/deployment-receipts',$identity['instance_id'],$identity['key_id'],$timestamp,$nonce,$idempotency,hash('sha256',json_encode($r,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))]);
            $answer=$this->http('/api/v1/deployment-receipts',$r,['X-Namua-Instance-ID: '.$identity['instance_id'],'X-Namua-Key-ID: '.$identity['key_id'],
                'X-Namua-Timestamp: '.$timestamp,'X-Namua-Nonce: '.$nonce,'Idempotency-Key: '.$idempotency,'X-Namua-Signature: '.hash_hmac('sha256',$canonical,$identity['secret'])]);
            if(!in_array($answer['http'],[200,202],true)||($answer['json']['status']??'')!=='accepted')throw new RuntimeException('RECEIPT_NOT_ACCEPTED');
            PrivateDeployment::write($this->dir.'/receipt-ack.json',$answer['json']);return ['status'=>'ACKNOWLEDGED','duplicate'=>$answer['json']['duplicate']??false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
