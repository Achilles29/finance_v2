<?php
declare(strict_types=1);
require_once __DIR__.'/PortableInstaller.php';

/** The scheduler runs this class as the installer account, NEVER as root or the web account. */
final class SetupService
{
    private PortableStore $private;
    private PortableStore $public;
    public function __construct(private string $root,private $transport=null)
    {
        PortableStore::writer($root);
        $this->private=new PortableStore($root,'private');$this->public=new PortableStore($root,'storage/setup');
    }
    public static function id(string $id): string
    {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))throw new RuntimeException('SETUP_REQUEST_INVALID');return $id;
    }
    public static function code(Throwable $e): string
    {
        return preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'INSTALL_STEP_FAILED';
    }
    private static function digest(array $v): string
    {
        $normalize=static function(array $a)use(&$normalize):array{ksort($a);foreach($a as &$x)if(is_array($x))$x=$normalize($x);return $a;};
        return hash('sha256',json_encode($normalize($v),JSON_THROW_ON_ERROR));
    }
    public function tick(): array
    {
        $lock=$this->private->lock('worker.lock');
        $this->public->write('worker.json',['at'=>time(),'pid'=>getmypid(),'state'=>'RUNNING'],0640);
        $failure='';
        try {
            if($this->private->exists('complete.json')){
                require_once dirname(__DIR__,2).'/update/UpdateService.php';
                return (new UpdateService($this->root))->tick();
            }
            PortablePackage::verify($this->root);
            $installer=new PortableInstaller($this->root,$this->transport);
            $count=0;
            foreach(glob($this->root.'/storage/inbox/command-*.json')?:[]as$file) {
                if(++$count>4)break;
                $id=substr(basename($file),8,-5);
                if(!preg_match('/\A[a-f0-9]{32}\z/D',$id))continue;
                $record='result-'.$id.'.json';
                if($this->public->exists($record)){ $this->archive($file,$id);continue; }
                $kind='unknown';
                try {
                    $c=$this->command($file);if(($c['id']??'')!==$id)throw new RuntimeException('SETUP_REQUEST_INVALID');$kind=$c['kind']??'';
                    if($kind==='probe') {
                        $config=PortableInstaller::configuration($c['config']??[]);
                        DeploymentConfig::previewCustomer($this->root,$config);
                        $result=PortableDatabase::probe($config['database']);
                        $this->private->write('probe-'.$id.'.json',['at'=>time(),'digest'=>self::digest($c['config']),'permit_id'=>$c['permit_id']]);
                        $state=['phase'=>'PROBE_OK','result'=>$result];
                    }elseif($kind==='install') {
                        $probe=$this->private->read('probe-'.self::id($c['probe_id']??'').'.json');
                        if(($probe['at']??0)<time()-900||($probe['permit_id']??'')!==$c['permit_id']||!hash_equals($probe['digest']??'',self::digest($c['config']??[])))throw new RuntimeException('DATABASE_PROBE_REQUIRED');
                        if(($c['confirmed']??false)!==true)throw new RuntimeException('SETUP_CONFIRM_REQUIRED');
                        // Check empty DB once more inside install under its own DB lock before any SQL.
                        $installer->submit($c,($c['replace_input']??false)===true);
                        $state=$installer->run();
                    }else throw new RuntimeException('SETUP_REQUEST_INVALID');
                    $this->public->write($record,['id'=>$id,'kind'=>$kind,'ok'=>true,'at'=>time()]+$state,0640);
                }catch(Throwable $e) {
                    $this->public->write($record,['id'=>$id,'kind'=>$kind,'ok'=>false,'at'=>time(),'code'=>self::code($e)],0640);
                }
                $this->archive($file,$id);
            }
            if($count===0 && $this->private->exists('job.json')&&!$this->private->exists('complete.json')) {
                // Continues the SAME persisted attempt; installer owns all activation/DDL replay decisions.
                try{$installer->run();}catch(Throwable $e){return ['phase'=>'ATTENTION','code'=>self::code($e)];}
            }
            return ['phase'=>'LISTENING'];
        }catch(Throwable $e){$failure=self::code($e);throw $e;
        }finally{
            $this->public->write('worker.json',['at'=>time(),'pid'=>getmypid(),'state'=>$failure===''?'LISTENING':'ATTENTION','code'=>$failure],0640);
            flock($lock,LOCK_UN);fclose($lock);
        }
    }
    private function command(string $file): array
    {
        if(is_link($file)||realpath($file)!==$file||!is_file($file)||filesize($file)>65536||(stat($file)['nlink']??0)!==1)throw new RuntimeException('INBOX_UNSAFE');
        $envelope=json_decode(file_get_contents($file),true,8,JSON_THROW_ON_ERROR);
        $key=base64_decode($this->private->read('setup-key.json')['key'],true);
        $bytes=base64_decode($envelope['sealed']??'',true);
        $raw=is_string($bytes)?sodium_crypto_box_seal_open($bytes,$key):false;sodium_memzero($key);
        if(!is_string($raw))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $command=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        $delivery=$this->private->read('delivery-state.json');
        $permit=PortablePackage::permit($this->root,$delivery['context']);
        if($permit!==$delivery['permit'])throw new RuntimeException('DELIVERY_PREPARE_REQUIRED');
        if(($command['permit_id']??'')!==$permit['permit_id']||!is_string($command['secret']??null)||!hash_equals($permit['setup_secret_sha256'],hash('sha256',$command['secret'])))throw new RuntimeException('SETUP_REQUEST_UNAUTHORIZED');
        unset($command['secret']);return $command;
    }
    private function archive(string $file,string $id): void
    {
        if(is_link($file)||!is_file($file)||filesize($file)>65536||(stat($file)['nlink']??0)!==1)throw new RuntimeException('INBOX_UNSAFE');
        // Preserve sealed evidence, not plaintext in a web-readable log.
        // The web account owns the inbox inode. Copy atomically as the worker instead of
        // renaming that inode and attempting an unauthorized chmod/chown.
        $name='command-'.self::id($id).'.json';$packet=['sealed_evidence_base64'=>base64_encode(file_get_contents($file))];
        if($this->private->exists($name)){
            if($this->private->read($name)!==$packet)throw new RuntimeException('COMMAND_HISTORY_CONFLICT');
        }else $this->private->write($name,$packet);
        if(!unlink($file))throw new RuntimeException('COMMAND_ARCHIVE_FAILED');
    }
    public function scheduled(string $kind): array
    {
        if(!in_array($kind,['license-sync','heartbeat'],true))throw new RuntimeException('SETUP_REQUEST_INVALID');
        $lock=$this->private->lock($kind.'.lock');
        try {
            $this->public->write($kind.'.json',['at'=>time(),'status'=>'RUNNING'],0640);
            if(!$this->private->exists('complete.json'))$result=['status'=>'WAITING_INSTALLATION'];
            else {
                require_once dirname(__DIR__,3).'/application/libraries/Customer_update_guard.php';
                Customer_update_guard::enter($this->root);
                $last=$kind.'-attempt.json';
                if($this->private->exists($last)&&($this->private->read($last)['at']??0)>time()-300){
                    $this->public->write($kind.'.json',['at'=>time(),'status'=>'WAIT_NEXT_INTERVAL'],0640);
                    return ['status'=>'WAIT_NEXT_INTERVAL'];
                }
                $this->private->write($last,['at'=>time()]);
                $i=new PortableInstaller($this->root,$this->transport);
                $result=$kind==='license-sync'?$i->licenseSync():$i->heartbeat();
            }
            $this->public->write($kind.'.json',['at'=>time(),'status'=>'CHECKED'],0640);
            return $result;
        }catch(Throwable $e){$this->public->write($kind.'.json',['at'=>time(),'status'=>'ATTENTION','code'=>self::code($e)],0640);throw $e;}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
