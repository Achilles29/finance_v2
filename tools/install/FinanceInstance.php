<?php
declare(strict_types=1);
require_once __DIR__.'/PrivateDeployment.php';
require_once __DIR__.'/LinuxWebProfile.php';
require_once dirname(__DIR__,2).'/application/libraries/Control_license_verifier.php';
require_once dirname(__DIR__,2).'/application/libraries/Control_license_cache.php';
require_once dirname(__DIR__,2).'/application/libraries/DeploymentConfig.php';
if(!defined('C3_CLEAN_INSTALL_LIBRARY_ONLY'))define('C3_CLEAN_INSTALL_LIBRARY_ONLY',true);
require_once __DIR__.'/clean_install_database.php';

/** Verified code and isolated services. No deletion of releases, uploads or databases. */
final class FinanceInstance
{
    private array $c;
    private ?array $localConnection = null;
    private ?array $localSettings = null;
    public function __construct(array $c)
    {
        $required=['private_dir','runtime_dir','release_root','signed_manifest','trust_file','php_fpm','nginx','user','group','port','tls_certificate','tls_key','mime_types','mode'];
        if (($c['configuration_source']??'')!=='customer_local') $required=array_merge($required,['deployment_file','defaults_extra_file','database_name_file']);
        else foreach(['deployment_file','defaults_extra_file','database_name_file'] as $key) if(isset($c[$key]))throw new RuntimeException('CUSTOMER_CONFIG_DUPLICATE_DATABASE_INPUT');
        foreach($required as $key)if(!array_key_exists($key,$c))throw new RuntimeException('INSTANCE_CONFIG_INCOMPLETE');
        PrivateDeployment::directory($c['private_dir']);
        LicenseAgentFiles::securePath($c['runtime_dir'],dirname(__DIR__,2),true);
        if (!in_array($c['mode'],['clean_install','upgrade'],true)||realpath(dirname($c['release_root']))!==dirname($c['release_root'])
            ||preg_match('~\A/[A-Za-z0-9_./-]+\z~D',$c['release_root'])!==1||strpos($c['release_root'],'..')!==false
            ||$c['release_root']===$c['runtime_dir']||str_starts_with($c['runtime_dir'].'/',$c['release_root'].'/'))throw new RuntimeException('INSTANCE_BOUNDARY_INVALID');
        LicenseAgentFiles::securePath(dirname($c['release_root']),dirname(__DIR__,2),true);
        foreach(['php_fpm','nginx']as$key)if(realpath($c[$key])!==$c[$key]||!is_executable($c[$key])||fileowner($c[$key])!==0||(fileperms($c[$key])&0022)!==0)throw new RuntimeException('INSTANCE_BINARY_UNSAFE');
        $account=posix_getpwnam($c['user']);$group=posix_getgrnam($c['group']);
        if(!$account||!$group||$account['uid']===0||$group['gid']===0)throw new RuntimeException('INSTANCE_ACCOUNT_INVALID');
        $this->c=$c+['uid'=>$account['uid'],'gid'=>$group['gid']];
    }
    private function descriptor(): string{return $this->c['private_dir'].'/instance.json';}
    private function manifest(): array
    {
        $path=$this->c['signed_manifest'];financeArtifactSignatureRegularFile($path,'MANIFEST_UNSAFE');
        $bytes=(string)file_get_contents($path);$m=ControlReleaseBridge::json($bytes);
        if(substr($path,-13)!=='.release.json')throw new RuntimeException('MANIFEST_INVALID');
        $name=ControlReleaseBridge::artifactName($m);
        $verified=ControlReleaseBridge::verify($bytes,basename($path),ControlReleaseBridge::json((string)file_get_contents(substr($path,0,-13).'.release.sig.json')),
            ControlReleaseBridge::loadKey($this->c['trust_file']),dirname($path).'/'.$name);return $verified['install_manifest'];
    }
    public function stage(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            if(file_exists($this->descriptor())||is_link($this->descriptor()))throw new RuntimeException('INSTANCE_STATE_EXISTS');
            $m=$this->manifest();$app=$this->c['release_root'];
            if(file_exists($app)||is_link($app))throw new RuntimeException('RELEASE_TARGET_EXISTS');
            mkdir($app,0755);chmod($app,0755);$mask=umask(0022);
            try{PrivateDeployment::run([financeArtifactSignatureTarBinary(),'--extract','--file='.dirname($this->c['signed_manifest']).'/'.$m['artifact'],'--directory='.$app,'--no-same-owner','--no-same-permissions'],$this->c['private_dir'].'/extract.log',120);}finally{umask($mask);}
            $release=a513_validate_release($app,$app.'/RELEASE-MANIFEST.json');
            if($release['manifest_sha256']!==$m['source_manifest_sha256'])throw new RuntimeException('EXTRACTED_SOURCE_MISMATCH');
            $r=['status'=>'STAGED','version'=>$m['version'],'artifact_sha256'=>$m['sha256'],'source_commit'=>$m['source_commit'],'source_files'=>count($release['manifest']['files']),'database_changed'=>false];
            PrivateDeployment::write($this->descriptor(),$r+['config_sha256'=>hash('sha256',json_encode($this->c))]);return $r;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function assertDescriptor(): array
    {
        $d=PrivateDeployment::read($this->descriptor());
        if(($d['config_sha256']??'')!==hash('sha256',json_encode($this->c)))throw new RuntimeException('INSTANCE_CONFIGURATION_CHANGED');return $d;
    }
    /** Publish only metadata authenticated by manifest(), never activation codes or private keys. */
    private function customerContext(array $m): string
    {
        $c=$this->c;
        if (($m['customer_runtime_guard']??'')!=='FINANCE_CUSTOMER_SERVER_V1') throw new RuntimeException('CUSTOMER_RUNTIME_GUARD_REQUIRED');
        if (!is_string($c['license_public_dir']??null) || !is_string($c['instance_id']??null)
            || $c['instance_id']==='') throw new RuntimeException('CUSTOMER_LICENSE_CONFIGURATION_REQUIRED');
        $public=new LicenseAgentFiles($c['license_public_dir'],$c['release_root'],$c['gid'],false);
        $identity=$public->read('identity.json',0640);
        $public->read('trust.json',0640);$public->read('runtime.json',0640);
        if (count($identity)!==3 || ($identity['instance_id']??null)!==$c['instance_id']) throw new RuntimeException('CUSTOMER_IDENTITY_MISMATCH');
        $release=a513_validate_release($c['release_root'],$c['release_root'].'/RELEASE-MANIFEST.json');
        $inventory=[];foreach($release['manifest']['files']as$entry)$inventory[$entry['path']]=$entry['sha256'];
        $core=[];foreach(Control_license_cache::customer_core_files($m['distribution_profile_version'])as$relative){
            if(!is_string($inventory[$relative]??null))throw new RuntimeException('CUSTOMER_CORE_MISSING');
            $core[$relative]=$inventory[$relative];
        }
        $bytes=(string)file_get_contents($c['signed_manifest']);
        $trust=ControlReleaseBridge::loadKey($c['trust_file']);$publicTrust=[];
        foreach(['schema','product_code','algorithm','key_id','status','public_key_base64','public_key_sha256']as$key)$publicTrust[$key]=$trust[$key]??null;
        $context=['schema'=>1,'purpose'=>'FINANCE_CUSTOMER_INSTALLATION','product_code'=>'NAMUA_FINANCE',
            'release_root'=>$c['release_root'],'release_public_id'=>$m['release_public_id']??'',
            'version'=>$m['version'],'source_commit'=>$m['source_commit'],
            'artifact_sha256'=>$m['sha256'],'release_manifest_sha256'=>hash('sha256',$bytes),
            'app_manifest_sha256'=>$m['control_app_manifest_sha256']??'',
            'source_manifest_sha256'=>$m['source_manifest_sha256'],
            'distribution_profile'=>$m['distribution_profile']??'',
            'distribution_profile_version'=>$m['distribution_profile_version']??null,
            'profile_sha256'=>$m['customer_content_audit']['profile_sha256']??'',
            'identity'=>$identity,'machine_fingerprint_sha256'=>Control_license_cache::customer_machine_fingerprint(),
            'customer_runtime_guard'=>$m['customer_runtime_guard'],
            'core_sha256'=>$core,'release_manifest_base64'=>base64_encode($bytes),
            'release_signature'=>ControlReleaseBridge::json((string)file_get_contents(substr($c['signed_manifest'],0,-13).'.release.sig.json')),
            'release_trust'=>$publicTrust,
            'license_trust_file'=>$c['license_public_dir'].'/trust.json',
            'license_identity_file'=>$c['license_public_dir'].'/identity.json',
            'license_cache_file'=>$c['license_public_dir'].'/runtime.json'];
        $file=$c['runtime_dir'].'/customer-installation.json';
        if(file_exists($file)||is_link($file))throw new RuntimeException('CUSTOMER_CONTEXT_EXISTS');
        PrivateDeployment::write($file,$context);
        if(!chgrp($file,$c['gid'])||!chmod($file,0640))throw new RuntimeException('CUSTOMER_CONTEXT_WRITE_FAILED');
        Control_license_cache::customer_context($c['release_root'],$file);
        return $file;
    }
    public function install(): array
    {
        if (($this->c['configuration_source']??'')==='customer_local' && $this->localConnection===null) {
            return $this->withLocalConnection(fn():array=>$this->install());
        }
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            $d=$this->assertDescriptor();if($d['status']!=='STAGED')throw new RuntimeException('INSTALL_ALREADY_ATTEMPTED');
            $c=$this->connectionConfig();$m=$this->manifest();$dbName=a5_read_database_name(dirname(__DIR__,2),$c['database_name_file']);
            $option=a5_assert_apply_security(dirname(__DIR__,2),$c['defaults_extra_file']);
            $settings=$this->localSettings??Control_license_verifier::deployment_document($c['deployment_file'], $c['release_root']);
            if ($this->localSettings!==null) {
                CustomerDatabase::assertBinding($settings,$option,$dbName);
                if (($m['distribution_profile_version']??0)<5)throw new RuntimeException('CUSTOMER_LOCAL_PROFILE_REQUIRED');
            } elseif (CustomerLocalConfig::present($c['release_root'])) throw new RuntimeException('CUSTOMER_CONFIG_DUPLICATE_DATABASE_INPUT');
            $baseUrl=DeploymentConfig::fromSnapshot($settings)->canonicalBaseUrl('',true);
            $url=parse_url($baseUrl);
            if (!is_array($url)||($url['path']??'/')!=='/'||!is_string($url['host']??null)
                ||preg_match('/\A[A-Za-z0-9.-]+\z/D',$url['host'])!==1)throw new RuntimeException('CUSTOMER_WEB_URL_UNSUPPORTED');
            if ($this->localSettings===null) {
            $login=parse_ini_file($option,true,INI_SCANNER_RAW)['client']??[];
            if(($settings['FINANCE_DB_NAME']??'')!==$dbName||($settings['FINANCE_DB_USER']??'')!==($login['user']??null)
                ||!is_string($settings['FINANCE_DB_PASSWORD']??null)||!hash_equals((string)($login['password']??''),$settings['FINANCE_DB_PASSWORD'])
                ||strlen($settings['FINANCE_ENCRYPTION_KEY']??'')<32)throw new RuntimeException('WEB_DATABASE_BINDING_MISMATCH');
            $expectedHost=($login['protocol']??'')==='socket'?'localhost':($login['host']??'localhost');
            if(($settings['FINANCE_DB_HOST']??'')!==$expectedHost)throw new RuntimeException('WEB_DATABASE_ENDPOINT_MISMATCH');
            }
            // Validate all local inputs before the first SQL statement that changes schema.
            if (!isset($c['composer']) || realpath($c['composer'])!==$c['composer'] || !is_file($c['composer']) || fileowner($c['composer'])!==0 || (fileperms($c['composer'])&0022)!==0) throw new RuntimeException('COMPOSER_BINARY_REQUIRED');
            if ($c['mode']==='clean_install') {
                if (!isset($c['owner_file'])) throw new RuntimeException('FIRST_OWNER_FILE_REQUIRED');
                a512_owner_file(dirname(__DIR__,2),$c['owner_file']);
            } elseif (!isset($c['backup_file'],$c['backup_sha256']) || !is_file($c['backup_file']) || is_link($c['backup_file']) || !hash_equals(hash_file('sha256',$c['backup_file']),$c['backup_sha256'])) {
                throw new RuntimeException('VERIFIED_UPGRADE_BACKUP_REQUIRED');
            }
            $previous=null;
            if ($c['mode']==='upgrade') {
                if (!isset($c['previous_config_file'])) throw new RuntimeException('PREVIOUS_INSTANCE_REQUIRED');
                $previous=PrivateDeployment::read($c['previous_config_file']);
                $before=PrivateDeployment::read($previous['private_dir'].'/instance.json');
                $oldSettings=($previous['configuration_source']??'')==='customer_local'
                    ?DeploymentConfig::forRoot($previous['release_root'])->values()
                    :Control_license_verifier::deployment_document($previous['deployment_file'],$previous['release_root']);
                if (($before['status']??'')!=='STOPPED' || ($oldSettings['FINANCE_DB_NAME']??'')===$dbName
                    || ($oldSettings['FINANCE_ENCRYPTION_KEY']??'')!==$settings['FINANCE_ENCRYPTION_KEY']
                    || ($oldSettings['FINANCE_BASE_URL']??'')!==($settings['FINANCE_BASE_URL']??null)) throw new RuntimeException('QUIESCED_DATABASE_COPY_REQUIRED');
            }
            if (filegroup($c['runtime_dir'])!==$c['gid'] || (fileperms($c['runtime_dir'])&0050)!==0050) throw new RuntimeException('RUNTIME_SERVICE_GROUP_REQUIRED');
            foreach (['sessions','logs','cache','tmp'] as $dir) if (file_exists($c['runtime_dir'].'/'.$dir)||is_link($c['runtime_dir'].'/'.$dir)) throw new RuntimeException('FRESH_RUNTIME_DIRECTORY_REQUIRED');
            foreach (['SESSION'=>'sessions','LOG'=>'logs','CACHE'=>'cache'] as $key=>$dir) if (($settings['FINANCE_'.$key.'_PATH']??'')!==$c['runtime_dir'].'/'.$dir) throw new RuntimeException('RUNTIME_DIRECTORY_BINDING_MISMATCH');
            // Fail a wrong password/nonempty DB before producing context or marking SQL attempted.
            if ($c['mode']==='clean_install') {
                $probe=a5_client_open(a5_find_client(),$option,$dbName,microtime(true)+15);
                try {
                    a5_client_send($probe,"SELECT CONCAT('__C3_EMPTY__\\t',COUNT(*)) FROM information_schema.tables WHERE table_schema=DATABASE();");
                    c3InstallEmptyDatabase(a5_client_marker($probe,'__C3_EMPTY__'));
                } finally { a5_client_close($probe,true); }
            }
            $profile=['app_root'=>$c['release_root'],'state_root'=>$c['runtime_dir'],'deployment_file'=>$c['deployment_file']??'',
                'configuration_source'=>$c['configuration_source']??'legacy','tls_certificate'=>$c['tls_certificate'],'tls_key'=>$c['tls_key'],'mime_types'=>$c['mime_types'],'user'=>$c['user'],'group'=>$c['group'],'port'=>$c['port'],'daemonize'=>true];
            foreach(['lua_root','license_public_dir']as$key)if(isset($c[$key]))$profile[$key]=$c[$key];
            $profile['customer_installation_file']=$this->customerContext($m);
            $configs=LinuxWebProfile::render($profile);
            foreach (array_keys($configs) as $name) if (file_exists($c['runtime_dir'].'/'.$name)||is_link($c['runtime_dir'].'/'.$name)) throw new RuntimeException('SERVICE_CONFIG_EXISTS');
            $d['status']='INSTALLING';PrivateDeployment::write($this->descriptor(),$d);
            if($c['mode']==='clean_install'){
                if(!isset($c['owner_file']))throw new RuntimeException('FIRST_OWNER_FILE_REQUIRED');
                $options=['release-root'=>$c['release_root'],'signed-manifest'=>$c['signed_manifest'],'trust-file'=>$c['trust_file'],'owner-file'=>$c['owner_file']];
                if ($this->localSettings===null) $options+=['defaults-extra-file'=>$option,'database-name-file'=>$c['database_name_file']];
                $install=c3InstallDatabase($options);
                $health=$install['health'];
            }else{
                $release=a513_validate_release($c['release_root'],$c['release_root'].'/RELEASE-MANIFEST.json');
                $client=a5_client_open(a5_find_client(),$option,$dbName,microtime(true)+30);
                try{a5_client_send($client,"SELECT CONCAT('__C3_VERSION__\\t',VERSION());");c3InstallDatabaseVersion(a5_client_marker($client,'__C3_VERSION__'),$m['runtime']['database']);}finally{a5_client_close($client,true);}
                a5_apply($release['catalog'],$release['root'],'upgrade',$option,$dbName);
                $health=a513_check_database($release,'upgrade',$option,$dbName);
            }
            foreach(['sessions','logs','cache','tmp']as$dir){$p=$c['runtime_dir'].'/'.$dir;if(file_exists($p)||is_link($p))throw new RuntimeException('FRESH_RUNTIME_DIRECTORY_REQUIRED');mkdir($p,0700);chown($p,$c['uid']);chgrp($p,$c['gid']);}
            foreach(['SESSION'=>'sessions','LOG'=>'logs','CACHE'=>'cache']as$key=>$dir)if(($settings['FINANCE_'.$key.'_PATH']??'')!==$c['runtime_dir'].'/'.$dir)throw new RuntimeException('RUNTIME_DIRECTORY_BINDING_MISMATCH');
            require_once dirname(__DIR__,2).'/application/libraries/Upload_storage_policy.php';
            foreach(array_keys(Upload_storage_policy::DIRECTORIES)as$relative){$p=$c['release_root'];foreach(explode('/',$relative)as$part){$p.='/'.$part;if(is_link($p))throw new RuntimeException('UPLOAD_LINK_REJECTED');if(!is_dir($p)){mkdir($p,0755);chmod($p,0755);}}chown($p,$c['uid']);chgrp($p,$c['gid']);chmod($p,0770);}
            $uploads=[];
            if ($previous!==null) foreach(array_keys(Upload_storage_policy::DIRECTORIES) as $relative) {
                $from=$previous['release_root'].'/'.$relative;$to=$c['release_root'].'/'.$relative;
                if (!is_dir($from)||is_link($from)) throw new RuntimeException('PREVIOUS_UPLOAD_UNSAFE');
                $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach($walk as $entry){
                    if($entry->isLink())throw new RuntimeException('UPLOAD_LINK_REJECTED');
                    $rel=substr($entry->getPathname(),strlen($from)+1);$dst=$to.'/'.$rel;
                    if($entry->isDir()){if(!is_dir($dst))mkdir($dst,0770);chown($dst,$c['uid']);chgrp($dst,$c['gid']);chmod($dst,0770);continue;}
                    if(!$entry->isFile())throw new RuntimeException('UPLOAD_NOT_REGULAR');
                    $hash=hash_file('sha256',$entry->getPathname());
                    if(file_exists($dst)){if(is_link($dst)||!is_file($dst)||!hash_equals($hash,hash_file('sha256',$dst)))throw new RuntimeException('UPLOAD_COLLIDES_WITH_RELEASE');}
                    else{if(!copy($entry->getPathname(),$dst)||!hash_equals($hash,hash_file('sha256',$dst)))throw new RuntimeException('UPLOAD_COPY_FAILED');chown($dst,$c['uid']);chgrp($dst,$c['gid']);chmod($dst,0660);}
                    $uploads[$relative.'/'.$rel]=$hash;
                }
            }
            PrivateDeployment::write($c['private_dir'].'/preserved-uploads.json',$uploads);
            // Current package has no production Composer dependencies. Generate its autoloader explicitly.
            if(!isset($c['composer'])||!is_file($c['composer'])||fileowner($c['composer'])!==0||(fileperms($c['composer'])&0022)!==0)throw new RuntimeException('COMPOSER_BINARY_REQUIRED');
            PrivateDeployment::run([PHP_BINARY,$c['composer'],'install','--working-dir='.$c['release_root'],'--no-dev','--no-interaction','--no-progress','--no-plugins'],$c['private_dir'].'/composer.log',180);
            foreach($configs as$name=>$bytes){$path=$c['runtime_dir'].'/'.$name;$h=fopen($path,'xb');if(!$h||fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('SERVICE_CONFIG_WRITE');fclose($h);chmod($path,0600);}
            $d['status']='PREPARED';$d['health']=$health;PrivateDeployment::write($this->descriptor(),$d);
            return ['status'=>'PREPARED','version'=>$m['version'],'health'=>$health,'web_started'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function ownPid(string $kind): ?int
    {
        $file=$this->c['runtime_dir'].'/'.$kind.'.pid';if(!is_file($file))return null;
        if(is_link($file)||fileowner($file)!==0)throw new RuntimeException('SERVICE_PID_UNSAFE');$raw=trim((string)file_get_contents($file));
        if(preg_match('/\A[1-9][0-9]{0,8}\z/D',$raw)!==1)throw new RuntimeException('SERVICE_PID_INVALID');$pid=(int)$raw;
        if(!is_dir('/proc/'.$pid))return null;
        $args=(string)file_get_contents('/proc/'.$pid.'/cmdline');$config=$this->c['runtime_dir'].'/'.($kind==='fpm'?'php-fpm.conf':'nginx.conf');
        if(fileowner('/proc/'.$pid)!==0||strpos($args,$config)===false)throw new RuntimeException('SERVICE_PID_NOT_OWNED');return $pid;
    }
    public function start(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{
            $d=$this->assertDescriptor();if(!in_array($d['status'],['PREPARED','STOPPED','RUNNING'],true))throw new RuntimeException('INSTANCE_NOT_PREPARED');
            $c=$this->c;$s=$c['runtime_dir'];$log=$c['private_dir'].'/service.log';
            if($this->ownPid('nginx')!==null||$this->ownPid('fpm')!==null)throw new RuntimeException('SERVICE_ALREADY_RUNNING');
            PrivateDeployment::run([$c['php_fpm'],'-t','-y',$s.'/php-fpm.conf'],$log);
            PrivateDeployment::run([$c['nginx'],'-t','-p',$s.'/','-c',$s.'/nginx.conf'],$log);
            PrivateDeployment::run([$c['php_fpm'],'-D','-y',$s.'/php-fpm.conf'],$log);
            try{PrivateDeployment::run([$c['nginx'],'-p',$s.'/','-c',$s.'/nginx.conf'],$log);}catch(Throwable $e){$pid=$this->ownPid('fpm');if($pid!==null)posix_kill($pid,SIGQUIT);throw $e;}
            $d['status']='RUNNING';PrivateDeployment::write($this->descriptor(),$d);return ['status'=>'RUNNING','public_listener'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function stop(): array
    {
        $lock=PrivateDeployment::lock($this->c['private_dir']);
        try{$d=$this->assertDescriptor();foreach(['nginx','fpm']as$kind){$pid=$this->ownPid($kind);if($pid===null)continue;posix_kill($pid,SIGQUIT);$until=microtime(true)+10;while(is_dir('/proc/'.$pid)&&microtime(true)<$until){clearstatcache();usleep(50000);}if(is_dir('/proc/'.$pid))throw new RuntimeException('SERVICE_STOP_TIMEOUT');}$d['status']='STOPPED';PrivateDeployment::write($this->descriptor(),$d);return ['status'=>'STOPPED','files_deleted'=>false];}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function health(): array
    {
        if (($this->c['configuration_source']??'')==='customer_local' && $this->localConnection===null) {
            return $this->withLocalConnection(fn():array=>$this->health());
        }
        $this->assertDescriptor();$c=$this->connectionConfig();$m=$this->manifest();$release=a513_validate_release($c['release_root'],$c['release_root'].'/RELEASE-MANIFEST.json');
        $license=Control_license_cache::customer_verification($c['release_root'],$c['runtime_dir'].'/customer-installation.json');
        if(empty($license['verified'])||!in_array($license['status']??'', ['ACTIVE','GRACE'], true))throw new RuntimeException('CUSTOMER_LICENSE_NOT_READY');
        $dbName=a5_read_database_name(dirname(__DIR__,2),$c['database_name_file']);$health=a513_check_database($release,$c['mode'],$c['defaults_extra_file'],$dbName);
        $settings=$this->localSettings??Control_license_verifier::deployment_document($c['deployment_file'],$c['release_root']);$host=parse_url($settings['FINANCE_BASE_URL']??'',PHP_URL_HOST);
        if(!is_string($host)||preg_match('/\A[A-Za-z0-9.-]+\z/D',$host)!==1)throw new RuntimeException('HEALTH_HOST_INVALID');
        $h=curl_init('https://'.$host.':'.$c['port'].'/login');curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$host.':'.$c['port'].':127.0.0.1']]);
        if(isset($c['health_ca']))curl_setopt($h,CURLOPT_CAINFO,$c['health_ca']);$body=curl_exec($h);$code=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
        if($code!==200||!is_string($body)||strpos($body,'name="identifier"')===false)throw new RuntimeException('WEB_HEALTH_FAILED');
        $r=['status'=>'PASS','web_verified'=>true,'health'=>$health,'version'=>$m['version'],'artifact_sha256'=>$m['sha256'],
            'license_status'=>$license['status'],'machine_binding_verified'=>true,
            'from_schema'=>$c['from_schema']??$m['baseline_schema_version'],'to_schema'=>$m['schema_version'],
            'migration_versions'=>array_column(a5_plan($release['catalog'],$c['mode']),'id'),'backup_sha256'=>$c['backup_sha256']??null,'at'=>date(DATE_ATOM)];
        PrivateDeployment::write($c['private_dir'].'/result.json',$r);return $r;
    }

    private function withLocalConnection(callable $action): array
    {
        return CustomerDatabase::withConnection($this->c['release_root'],function(array $connection,array $settings)use($action):array {
            if (CustomerLocalConfig::runtime($settings)!==$this->c['runtime_dir']) throw new RuntimeException('RUNTIME_DIRECTORY_BINDING_MISMATCH');
            $this->localConnection=$connection;$this->localSettings=$settings;
            try { return $action(); } finally { $this->localConnection=null;$this->localSettings=null; }
        });
    }

    private function connectionConfig(): array
    {
        $c=$this->c;
        if ($this->localConnection!==null) {
            $c['defaults_extra_file']=$this->localConnection['defaults-extra-file'];
            $c['database_name_file']=$this->localConnection['database-name-file'];
        }
        return $c;
    }
}
