<?php
declare(strict_types=1);
require_once __DIR__.'/UpdateTransport.php';
require_once __DIR__.'/UpdateAuthorization.php';
require_once __DIR__.'/UpdateDatabase.php';
require_once dirname(__DIR__).'/install/portable/PortablePackage.php';
require_once dirname(__DIR__,2).'/application/libraries/DeploymentConfig.php';

/** Installer-account worker. Download is automatic; maintenance/apply requires the customer's confirmation. */
final class UpdateService
{
    private PortableStore $private;
    private PortableStore $public;
    private UpdateTransport $control;
    public function __construct(private string $root,private $transport=null)
    {
        PortableStore::writer($root);$this->private=new PortableStore($root,'private');$this->public=new PortableStore($root,'storage/setup');
        $this->control=new UpdateTransport($root,$transport);
    }
    private function status(array $s): array
    {
        $this->public->write('update-status.json',['at'=>time()]+$s,0640);return $s;
    }
    public static function code(Throwable $e): string
    {
        return preg_match('/\A[A-Z][A-Z_]{3,100}\z/D',$e->getMessage())?$e->getMessage():'UPDATE_REVIEW_REQUIRED';
    }
    public static function plan(array $authorization): array
    {
        unset($authorization['issued_at'],$authorization['expires_at']);return $authorization;
    }
    private function receipt(array &$job,string $phase,string $code=''): void
    {
        $r=['phase'=>$phase,'plan_sha256'=>$job['plan_sha256'],'from_manifest_sha256'=>$job['plan']['from']['release_manifest_sha256'],
            'to_manifest_sha256'=>$job['plan']['to']['release_manifest_sha256'],'backup_sha256'=>$job['backup']['sha256']??null,
            'ledger_sha256'=>$job['ledger_sha256']??null,'code'=>$code];
        $job['pending_receipt']=$r;$this->private->write('update-job.json',$job);
        $result=$this->control->request(['action'=>'receipt','plan_id'=>$job['plan']['authorization_id'],'receipt'=>$r]);
        if(($result['status']??'')!=='accepted')throw new RuntimeException('UPDATE_CONTROL_ACK_REQUIRED');
        unset($job['pending_receipt']);$this->private->write('update-job.json',$job);
    }
    private function permission(array $offer,array $current,array $next,array $identity): array
    {
        $p=UpdateAuthorization::verifyExecution($offer['authorization'],$current['release_trust'],$current,$next,$identity,time());
        $plan=self::plan($p);
        if(UpdateAuthorization::digest($plan)!==($offer['plan_sha256']??'')||UpdateAuthorization::digest($plan)!==UpdateAuthorization::digest($offer['plan']??[]))throw new RuntimeException('UPDATE_PLAN_CHANGED');
        return $plan;
    }
    private function prepare(array $offer,array $current,array $identity): array
    {
        $raw=base64_decode($offer['manifest_base64']??'',true);
        if(!is_string($raw)||strlen($raw)>100000)throw new RuntimeException('UPDATE_MANIFEST_INVALID');
        $m=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(($m['application_update_contract']??'')!=='FINANCE_APPLICATION_UPDATE_V1')throw new RuntimeException('UPDATE_TARGET_AGENT_UNSUPPORTED');
        $target=['product_code'=>'NAMUA_FINANCE','release_root'=>$this->root,'release_public_id'=>$m['release_public_id']??'','version'=>$m['version']??'','source_commit'=>$m['source_commit']??'',
            'artifact_sha256'=>$m['sha256']??'','release_manifest_sha256'=>hash('sha256',$raw),'app_manifest_sha256'=>$m['source_manifest_sha256']??'',
            'source_manifest_sha256'=>$m['customer_content_audit']['source_manifest_sha256']??'','profile_sha256'=>$m['customer_content_audit']['profile_sha256']??'',
            'distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>$m['distribution_profile_version']??null,
            'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1','release_manifest_base64'=>$offer['manifest_base64'],
            'release_signature'=>$offer['signature'],'release_trust'=>$current['release_trust']];
        Control_license_cache::customer_release_proof($target);
        $plan=$this->permission($offer,$current,$target,$identity);
        $dir=$this->root.'/private/update-'.$plan['authorization_id'];UpdateFiles::directory($this->root,$dir);
        $job=['plan'=>$plan,'plan_sha256'=>$offer['plan_sha256'],'phase'=>'DOWNLOADING','directory'=>$dir,'authorization'=>$offer['authorization'],
            'from_context'=>$current,'identity'=>$identity];$this->private->write('update-job.json',$job);$this->receipt($job,'DOWNLOADING');
        $archive=$dir.'/package.tar';
        if(!is_file($archive))$this->control->request(['action'=>'artifact','plan_id'=>$plan['authorization_id']],$archive);
        if(!hash_equals($plan['to']['artifact_sha256'],hash_file('sha256',$archive)))throw new RuntimeException('UPDATE_ARCHIVE_INVALID');
        $candidate=$dir.'/candidate';
        if(!is_dir($candidate))UpdateFiles::extract($this->root,$archive,$candidate,$plan['to']['artifact_sha256']);
        UpdateFiles::directory($this->root,$candidate.'/private/delivery');
        foreach(['release.json'=>$raw,'release.sig.json'=>json_encode($offer['signature'],JSON_THROW_ON_ERROR),
            'release-trust.json'=>json_encode($current['release_trust'],JSON_THROW_ON_ERROR)]as$name=>$bytes)UpdateFiles::write($this->root,$candidate.'/private/delivery/'.$name,$bytes);
        $package=$candidate.'/private/delivery/package.tar';
        if(!is_file($package))UpdateFiles::write($this->root,$package,file_get_contents($archive));
        $next=PortablePackage::verify($candidate);$this->permission($offer,$current,$next,$identity);
        $job['candidate']=$candidate;$job['target_context']=$next;$job['phase']='READY';$this->private->write('update-job.json',$job);$this->receipt($job,'READY');
        return $job;
    }
    public function tick(): array
    {
        $lock=$this->private->lock('update-worker.lock');$job=null;
        try{
            if(!$this->private->exists('complete.json'))return $this->status(['phase'=>'WAITING_INSTALLATION']);
            $job=$this->private->exists('update-job.json')?$this->private->read('update-job.json'):null;
            if($job&&in_array($job['phase'],['MAINTENANCE','BACKUP','MIGRATING','SWITCHING','VERIFYING','ATTENTION'],true))return $this->status(['phase'=>'ATTENTION','code'=>'UPDATE_INTERRUPTED_REVIEW_REQUIRED','to_version'=>$job['plan']['to']['version']]);
            if($job&&$job['phase']==='COMPLETE'){
                if(isset($job['pending_receipt']))$this->receipt($job,'SUCCEEDED');
                $marker=$this->root.'/storage/setup/update-maintenance.json';
                if(is_file($marker)){
                    $m=$this->public->read('update-maintenance.json');
                    if(($m['plan_sha256']??'')!==$job['plan_sha256'])throw new RuntimeException('UPDATE_MAINTENANCE_BINDING_INVALID');
                    if(!unlink($marker))throw new RuntimeException('UPDATE_MAINTENANCE_RELEASE_FAILED');
                }
                $this->private->write('update-completed-'.$job['plan']['authorization_id'].'.json',$job);
                // Completion remains durable; next offer replaces the current pointer, not its evidence.
            }
            $offer=$this->control->request(['action'=>'poll']);
            if(($offer['status']??'')==='NO_UPDATE')return $this->status(['phase'=>$job&&$job['phase']==='COMPLETE'?'COMPLETE':'NO_UPDATE','version'=>$job['plan']['to']['version']??'']);
            if(($offer['status']??'')!=='AVAILABLE')return $this->status(['phase'=>'PREPARING']);
            $current=PortablePackage::verify($this->root);$installed=Control_license_cache::customer_context($this->root,$this->root.'/storage/customer-installation.json');
            $decision=Control_license_cache::customer_verification($this->root,$this->root.'/storage/customer-installation.json');
            if(!($decision['verified']??false)||!in_array($decision['status']??'',['ACTIVE','GRACE'],true))throw new RuntimeException('UPDATE_LICENSE_RECOVERY_REQUIRED');
            if(!$job||$job['plan_sha256']!==$offer['plan_sha256']||$job['phase']!=='READY')$job=$this->prepare($offer,$current,$installed['identity']);
            $next=PortablePackage::verify($job['candidate']);$this->permission($offer,$current,$next,$installed['identity']);
            $job['authorization']=$offer['authorization'];$this->private->write('update-job.json',$job);
            $confirmation=$this->root.'/storage/inbox/update-'.$job['plan_sha256'].'.json';
            if(!is_file($confirmation))return $this->status(['phase'=>'READY','plan_sha256'=>$job['plan_sha256'],'from_version'=>$current['version'],'to_version'=>$next['version']]);
            if(is_link($confirmation)||(stat($confirmation)['nlink']??0)!==1||filesize($confirmation)>4096)throw new RuntimeException('UPDATE_CONFIRMATION_INVALID');
            $c=json_decode(file_get_contents($confirmation),true,8,JSON_THROW_ON_ERROR);
            if(array_keys($c)!==['plan_sha256','confirmed']||$c['plan_sha256']!==$job['plan_sha256']||$c['confirmed']!==true)throw new RuntimeException('UPDATE_CONFIRMATION_INVALID');
            return $this->apply($job);
        }catch(Throwable $e){return $this->status(['phase'=>'ATTENTION','code'=>self::code($e),'to_version'=>$job['plan']['to']['version']??'']);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    /** Requires runtime request locking; the legacy alpha.20 launcher cannot use this path yet. */
    private function apply(array $job): array
    {
        if(PHP_OS_FAMILY!=='Linux')throw new RuntimeException('UPDATE_PLATFORM_ACCEPTANCE_REQUIRED');
        $current=PortablePackage::verify($this->root);
        if($current['distribution_profile_version']<11)throw new RuntimeException('UPDATE_LEGACY_PREPARATION_REQUIRED');
        $gate=$this->root.'/storage/setup/application-update.lock';CustomerPlatform::path($this->root,$gate);
        $h=fopen($gate,'rb');if(!$h||!flock($h,LOCK_EX|LOCK_NB)){if($h)fclose($h);throw new RuntimeException('UPDATE_REQUESTS_STILL_RUNNING');}
        $pdo=null;$dbLock=false;
        try{
            $candidate=$job['candidate'];$next=PortablePackage::verify($candidate);
            UpdateAuthorization::verifyExecution($job['authorization'],$current['release_trust'],$current,$next,$job['identity'],time());
            $config=CustomerLocalConfig::read($this->root); // Read existing customer config; never replace it.
            $pdo=PortableDatabase::connect(['host'=>$config['FINANCE_DB_HOST'],'port'=>(int)$config['FINANCE_DB_PORT'],
                'socket'=>$config['FINANCE_DB_SOCKET'],'name'=>$config['FINANCE_DB_NAME'],'user'=>$config['FINANCE_DB_USER'],'password'=>$config['FINANCE_DB_PASSWORD']]);
            $q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute(['finance_application_update']);if((int)$q->fetchColumn()!==1)throw new RuntimeException('UPDATE_DATABASE_BUSY');$dbLock=true;
            $pending=UpdateDatabase::plan($pdo,$candidate);
            $job['phase']='MAINTENANCE';$this->private->write('update-job.json',$job);$this->public->write('update-maintenance.json',['plan_sha256'=>$job['plan_sha256'],'since'=>time()],0640);$this->receipt($job,'MAINTENANCE');
            $this->status(['phase'=>'BACKUP','to_version'=>$next['version']]);$job['phase']='BACKUP';$this->private->write('update-job.json',$job);
            $job['backup']=UpdateDatabase::backup($pdo,$job['directory'].'/database-backup.ndjson');$this->private->write('update-job.json',$job);$this->receipt($job,'BACKUP');
            $job['phase']='MIGRATING';$this->private->write('update-job.json',$job);$this->receipt($job,'MIGRATING');$this->status(['phase'=>'MIGRATING','to_version'=>$next['version']]);
            $job['ledger_sha256']=UpdateDatabase::apply($pdo,$candidate,$pending,function($id,$step)use(&$job){$job['migration_checkpoint']=[$id,$step];$this->private->write('update-job.json',$job);});
            $job['phase']='SWITCHING';$this->private->write('update-job.json',$job);$this->receipt($job,'SWITCHING');$this->status(['phase'=>'SWITCHING','to_version'=>$next['version']]);
            $old=UpdateFiles::inventory($this->root);$new=UpdateFiles::inventory($candidate);
            UpdateFiles::switchCode($this->root,$candidate,$job['directory'].'/previous-code',$old,$new,function($path,$step)use(&$job){$job['file_checkpoint']=[$path,$step];$this->private->write('update-job.json',$job);});
            foreach(['release.json','release.sig.json','package.tar']as$name){
                UpdateFiles::write($this->root,$job['directory'].'/previous-'.$name,file_get_contents($this->root.'/private/delivery/'.$name));
                UpdateFiles::write($this->root,$this->root.'/private/delivery/'.$name,file_get_contents($candidate.'/private/delivery/'.$name));
            }
            $next=PortablePackage::verify($this->root);$next['identity']=$job['identity'];
            PortableStore::writeFile($this->root,$this->root.'/storage/customer-installation.json',$next,0640);
            $delivery=$this->private->read('delivery-state.json');$job['old_delivery_context']=$delivery['context'];$delivery['context']=$next;$this->private->write('delivery-state.json',$delivery);
            $job['phase']='VERIFYING';$this->private->write('update-job.json',$job);$this->receipt($job,'VERIFYING');
            $verified=Control_license_cache::customer_verification($this->root,$this->root.'/storage/customer-installation.json');
            if(!($verified['verified']??false)||!in_array($verified['status']??'',['ACTIVE','GRACE'],true))throw new RuntimeException('UPDATE_LICENSE_POSTCHECK_FAILED');
            if(UpdateDatabase::plan($pdo,$this->root)!==[])throw new RuntimeException('UPDATE_DATABASE_POSTCHECK_FAILED');
            $complete=$this->private->read('complete.json');$complete['release_manifest_sha256']=$next['release_manifest_sha256'];$complete['updated_at']=gmdate(DATE_ATOM);$this->private->write('complete.json',$complete);
            $job['phase']='COMPLETE';$this->private->write('update-job.json',$job);
            // The database and files are consistent. A lost Control acknowledgement retries the SAME receipt.
            $this->receipt($job,'SUCCEEDED');
            if(!unlink($this->root.'/storage/setup/update-maintenance.json'))throw new RuntimeException('UPDATE_MAINTENANCE_RELEASE_FAILED');
            return $this->status(['phase'=>'COMPLETE','version'=>$next['version']]);
        }finally{if($dbLock){$pdo->query("SELECT RELEASE_LOCK('finance_application_update')");}flock($h,LOCK_UN);fclose($h);}
    }
}
