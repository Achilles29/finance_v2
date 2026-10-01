<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/application/libraries/DeploymentConfig.php';
require_once dirname(__DIR__).'/install/CustomerDatabase.php';
if (PHP_SAPI!=='cli'||posix_geteuid()!==0)throw new RuntimeException('ROOT_DISPOSABLE_FIXTURE_REQUIRED');
$base='/var/lib/finance-local-config-test-'.bin2hex(random_bytes(6));
$root=$base.'/app';$gid=(int)posix_getgrnam('www')['gid'];$checks=0;
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$name);$checks++;echo 'PASS '.$name."\n";};
$reject=static function(callable $call,string $name)use($check):void{try{$call();}catch(Throwable $e){$check(true,$name);return;}$check(false,$name);};
$remove=static function(string $p)use(&$remove,$base):void{
    if(!str_starts_with($p.'/',$base.'/'))throw new RuntimeException('CLEANUP_BOUNDARY');
    if(is_link($p)||is_file($p)){unlink($p);return;}
    foreach(scandir($p)?:[]as$n)if($n!=='.'&&$n!=='..')$remove($p.'/'.$n);rmdir($p);
};
$mask=umask(0022);mkdir($base,0750);chgrp($base,$gid);mkdir($root,0755);mkdir($root.'/config',0755);
$config=['schema'=>1,'database'=>['host'=>'127.0.0.1','port'=>3307,'socket'=>'','name'=>'test_customer',
    'user'=>'user_'.bin2hex(random_bytes(4)),'password'=>bin2hex(random_bytes(16)).'"\\#; $'],
    'base_url'=>'https://customer.example/','encryption_key'=>bin2hex(random_bytes(32)),
    'runtime'=>['directory'=>'../state','session_cookie'=>'test_customer']];
$save=static function($value)use($root,$gid):void{
    file_put_contents($root.'/config/customer.json',is_array($value)?json_encode($value,JSON_THROW_ON_ERROR):$value);
    chmod($root.'/config/customer.json',0640);chgrp($root.'/config/customer.json',$gid);clearstatcache();
};
try {
    $save($config);$c=DeploymentConfig::forRoot($root);$s=$c->values();
    $before=hash_file('sha256',$root.'/config/customer.json');
    $check(DeploymentConfig::previewCustomer($root,$config)->values()===$s&&hash_file('sha256',$root.'/config/customer.json')===$before,'UI preview uses same complete config resolver without writing files');
    $check($c->isLocal()&&$c->hasExplicitDatabase(),'complete local source is explicit and no legacy DB fallback');
    $check($s['FINANCE_DB_PORT']==='3307'&&$s['FINANCE_DB_PASSWORD']===$config['database']['password'],'port and password preserved exactly');
    $check($s['FINANCE_SESSION_PATH']===$base.'/state/sessions','runtime is installation-relative, not process cwd');
    $check($c->customerEnvironment($root,[])['FINANCE_CUSTOMER_INSTALLATION_FILE']===$base.'/state/customer-installation.json','license context automatic outside source');
    $reject(fn()=>$c->customerEnvironment($root,['FINANCE_CUSTOMER_INSTALLATION_FILE'=>$base.'/other.json']),'cannot substitute another context with environment');
    $check(CustomerLocalConfig::merge($s,$s)===$s,'identical legacy settings accepted');
    foreach(['FINANCE_DB_HOST','FINANCE_DB_PORT','FINANCE_DB_NAME','FINANCE_DB_USER','FINANCE_DB_PASSWORD','FINANCE_BASE_URL','FINANCE_ENCRYPTION_KEY']as$k){
        $reject(fn()=>CustomerLocalConfig::merge($s,[$k=>'different'],$s),'conflicting external setting cannot hide behind env override: '.$k);
    }
    putenv('FINANCE_DB_NAME=wrong_database');
    try{$reject(fn()=>DeploymentConfig::forRoot($root),'real environment conflict fails closed');$reject(fn()=>DeploymentConfig::previewCustomer($root,$config),'UI preview also rejects conflicting environment');}finally{putenv('FINANCE_DB_NAME');}
    $external=$base.'/external.json';file_put_contents($external,json_encode(['FINANCE_DB_NAME'=>'wrong_database']));chmod($external,0600);
    putenv('FINANCE_DEPLOYMENT_FILE='.$external);putenv('FINANCE_DB_NAME='.$s['FINANCE_DB_NAME']);
    try{$reject(fn()=>DeploymentConfig::forRoot($root),'real external file masked by matching environment still rejected');}
    finally{putenv('FINANCE_DEPLOYMENT_FILE');putenv('FINANCE_DB_NAME');}
    putenv('FINANCE_DEPLOYMENT_FILE='.$external);putenv('FINANCE_DB_NAME=legacy_environment');
    try {
        // Explicit empty fixture root: the working checkout may itself have private local settings.
        $check(DeploymentConfig::snapshotEnvironment(null, $base.'/no-local')['FINANCE_DB_NAME']==='legacy_environment','legacy environment still overrides external file without local configuration');
        putenv('FINANCE_DB_NAME');
        $check(DeploymentConfig::snapshotEnvironment(null, $base.'/no-local')['FINANCE_DB_NAME']==='wrong_database','legacy external-only configuration still loads without local configuration');
    } finally {putenv('FINANCE_DEPLOYMENT_FILE');putenv('FINANCE_DB_NAME');}
    foreach(['database','base_url','encryption_key','runtime']as$k){$bad=$config;unset($bad[$k]);$save($bad);$reject(fn()=>DeploymentConfig::forRoot($root),'missing '.$k.' rejected');}
    foreach(['host','name','user','password']as$k){$bad=$config;$bad['database'][$k]='';$save($bad);$reject(fn()=>DeploymentConfig::forRoot($root),'blank database '.$k.' rejected');}
    foreach(['{broken','[]','null','{"schema":1,"database":[]}']as$bytes){$save($bytes);$reject(fn()=>DeploymentConfig::forRoot($root),'invalid JSON/schema rejected');}
    foreach(['http://example.com/','https://name:password@example.com/','https://example.com/subpath/','https://example.com/?x=y']as$url){
        $bad=$config;$bad['base_url']=$url;$save($bad);$reject(fn()=>DeploymentConfig::forRoot($root),'unsafe or unsupported URL rejected');
    }
    foreach(['../../outside',$root.'/runtime','/tmp/../root','/','/var','/var/lib','/tmp/danger']as$path){
        $bad=$config;$bad['runtime']['directory']=$path;$save($bad);$reject(fn()=>DeploymentConfig::forRoot($root),'unsafe runtime rejected');
    }
    $bad=$config;$bad['license_private_key']='not-allowed';$save($bad);$reject(fn()=>DeploymentConfig::forRoot($root),'license material cannot be injected into local schema');
    $save($config);
    foreach([0644,0660,0666,0777]as$mode){chmod($root.'/config/customer.json',$mode);clearstatcache();$reject(fn()=>DeploymentConfig::forRoot($root),'unsafe config mode rejected '.decoct($mode));}
    $save($config);chmod($root.'/config',0775);clearstatcache();$reject(fn()=>DeploymentConfig::forRoot($root),'writable config parent rejected');chmod($root.'/config',0755);clearstatcache();
    rename($root.'/config/customer.json',$base.'/saved.json');symlink($base.'/saved.json',$root.'/config/customer.json');
    $reject(fn()=>DeploymentConfig::forRoot($root),'symlink local file rejected');unlink($root.'/config/customer.json');rename($base.'/saved.json',$root.'/config/customer.json');
    rename($root.'/config',$base.'/saved-config');symlink($base.'/saved-config',$root.'/config');
    $reject(fn()=>DeploymentConfig::forRoot($root),'symlink config directory rejected');unlink($root.'/config');rename($base.'/saved-config',$root.'/config');
    clearstatcache();
    CustomerDatabase::withConnection($root,function(array $files,array $settings,string $private)use($check,$root):void{
        $check(CustomerDatabase::ownsOption($files['defaults-extra-file']),'local connection scope opts out of global/home MySQL defaults');
        CustomerDatabase::assertBinding($settings,$files['defaults-extra-file'],trim(file_get_contents($files['database-name-file'])));
        $check((fileperms($files['defaults-extra-file'])&0777)===0600,'derived client credentials root-private');
        $check(!str_starts_with($files['defaults-extra-file'],$root.'/'),'temporary credentials are outside source');
    });
    $check(glob($base.'/state/.installer/connection-*')===[],'temporary credential files removed after callback');
    $check(!CustomerDatabase::ownsOption('/private/legacy.cnf'),'legacy MySQL option behavior is not changed');
    $reject(fn()=>CustomerDatabase::withConnection($root,static function(){throw new RuntimeException('SIMULATED_FAILURE');}),'simulated failure returns error');
    $check(glob($base.'/state/.installer/connection-*')===[],'temporary credentials also removed after failure');
    $check(!isset($s['FINANCE_LICENSE_PRIVATE_KEY']),'no license private key copied');
    file_put_contents($root.'/RELEASE-MANIFEST.json','{}');
    $save(['schema'=>1,'scope'=>'database_only','database'=>$config['database']]);
    $reject(fn()=>CustomerLocalConfig::read($root),'signed/package layout refuses source-server database-only scope even with safe permissions');
    unlink($root.'/config/customer.json');
    $reject(fn()=>DeploymentConfig::fromSnapshot([])->customerEnvironment($root,[]),'removing local config/context cannot turn package into LEGACY');
    $check(DeploymentConfig::fromSnapshot([])->customerEnvironment($base,[])===[],'legacy source without package markers unchanged');
    echo "All $checks customer local configuration checks passed; no network/database access.\n";
}finally{$remove($base);umask($mask);}
