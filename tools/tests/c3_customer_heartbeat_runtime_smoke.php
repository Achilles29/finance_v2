<?php
declare(strict_types=1);
define('FINANCE_HEARTBEAT_LIBRARY_ONLY',true);
require dirname(__DIR__,2).'/scripts/control_center_heartbeat.php';
$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);++$checks;echo 'PASS '.$label."\n";};
$reject=static function(callable $call,string $code,string $label)use($check):void{
    try{$call();}catch(RuntimeException $error){$check($error->getMessage()===$code,$label);return;}
    $check(false,$label);
};
$fixtureUser='fixture_'.bin2hex(random_bytes(8));
$fixturePassword=bin2hex(random_bytes(32));
$complete=[DeploymentConfig::DB_HOST=>'localhost',DeploymentConfig::DB_NAME=>'customer_fixture',
    DeploymentConfig::DB_USER=>$fixtureUser,DeploymentConfig::DB_PASSWORD=>$fixturePassword];
$expected=['hostname'=>'localhost','database'=>'customer_fixture','username'=>$fixtureUser,'password'=>$fixturePassword];
$check(heartbeat_deployment_database(DeploymentConfig::fromSnapshot($complete))===$expected,'customer DB maps only its deployment contract without connecting');
$check(!heartbeat_explicit_database([]),'legacy absent selectors retain the legacy branch');
$check(!heartbeat_explicit_database(['unrelated'=>'value']),'unrelated environment never opts a master into customer DB selection');
foreach(['FINANCE_DEPLOYMENT_FILE','FINANCE_CUSTOMER_INSTALLATION_FILE','FINANCE_HEARTBEAT_CONFIG_FILE',DeploymentConfig::DB_HOST,DeploymentConfig::DB_NAME,DeploymentConfig::DB_USER,DeploymentConfig::DB_PASSWORD] as $name){
    $check(heartbeat_explicit_database([$name=>'']),'even blank explicit deployment selectors prohibit master fallback');
    $reject(fn()=>heartbeat_database_config(DeploymentConfig::fromSnapshot([]),[$name=>'']),
        'HEARTBEAT_DATABASE_CONFIG_INCOMPLETE','incomplete explicit deployment fails before any legacy DB read');
}
$check(heartbeat_database_config(DeploymentConfig::fromSnapshot($complete),['FINANCE_DEPLOYMENT_FILE'=>'/private/customer/deployment.json'])===$expected,'complete explicit installation cannot read a shared master PHP configuration');
$reject(fn()=>heartbeat_database_config(DeploymentConfig::fromSnapshot([]),['FINANCE_HEARTBEAT_CONFIG_FILE'=>'/private/customer/heartbeat.json']),
    'HEARTBEAT_DATABASE_CONFIG_INCOMPLETE','config override alone cannot read master DB for a different customer heartbeat');
$check(heartbeat_database_config(DeploymentConfig::fromSnapshot($complete),['FINANCE_HEARTBEAT_CONFIG_FILE'=>'/private/customer/heartbeat.json'])===$expected,
    'config override uses only explicitly complete customer DB fields');
foreach(array_keys($complete) as $name){
    foreach([null,[],42,'','   ',"bad\0value"] as $bad){
        $values=$complete;$values[$name]=$bad;
        $reject(fn()=>heartbeat_deployment_database(DeploymentConfig::fromSnapshot($values)),
            'HEARTBEAT_DATABASE_CONFIG_INCOMPLETE','wrong-type empty or NUL customer DB field fails closed');
    }
}
foreach(['localhost;dbname=master','https://db.example.invalid','host name',str_repeat('a',256),'../master'] as $bad){
    $values=$complete;$values[DeploymentConfig::DB_HOST]=$bad;
    $reject(fn()=>heartbeat_deployment_database(DeploymentConfig::fromSnapshot($values)),
        'HEARTBEAT_DATABASE_ENDPOINT_INVALID','invalid customer DB endpoint cannot redirect a PDO DSN');
}
foreach(['database-name','db;host=other',str_repeat('a',65),'../master'] as $bad){
    $values=$complete;$values[DeploymentConfig::DB_NAME]=$bad;
    $reject(fn()=>heartbeat_deployment_database(DeploymentConfig::fromSnapshot($values)),
        'HEARTBEAT_DATABASE_ENDPOINT_INVALID','DB identifier must match clean install database.name contract');
}
$values=$complete;$values[DeploymentConfig::DB_HOST]='2001:db8::1';
$check(heartbeat_deployment_database(DeploymentConfig::fromSnapshot($values))['hostname']==='2001:db8::1','trusted IPv6 DB host remains a host rather than an injected DSN');
$check(heartbeat_config_path([])===HEARTBEAT_CONFIG,'legacy heartbeat configuration path unchanged');
$check(heartbeat_config_path(['FINANCE_HEARTBEAT_CONFIG_FILE'=>'/var/lib/customer/heartbeat.json'])==='/var/lib/customer/heartbeat.json','per-install private heartbeat file selected explicitly');
foreach(['',[],null,'relative.json',"/var/lib/bad\0path"] as $bad)
    $reject(fn()=>heartbeat_config_path(['FINANCE_HEARTBEAT_CONFIG_FILE'=>$bad]),'HEARTBEAT_CONFIG_PATH_INVALID','unsafe explicit heartbeat path never falls back to the master');
$root=realpath(dirname(__DIR__,2));
$check(heartbeat_release_version($root,null)===heartbeat_git_version($root),'legacy version behavior retained without customer context');
$context=['purpose'=>'FINANCE_CUSTOMER_INSTALLATION','product_code'=>'NAMUA_FINANCE','release_root'=>realpath(__DIR__),'version'=>'0.1.0-alpha.15'];
$check(heartbeat_git_version(realpath(__DIR__))==='unversioned','customer-like fixture root has no Git checkout');
$check(heartbeat_release_version(realpath(__DIR__),$context)==='0.1.0-alpha.15','verified customer context reports package SemVer without Git');
foreach(['purpose'=>'OTHER','product_code'=>'OTHER','release_root'=>$root,'version'=>'unversioned'] as $name=>$bad){
    $changed=$context;$changed[$name]=$bad;
    $reject(fn()=>heartbeat_release_version(realpath(__DIR__),$changed),'HEARTBEAT_RELEASE_VERSION_INVALID','context for another root product or nonrelease version cannot report customer identity');
}
$source=file_get_contents(dirname(__DIR__,2).'/scripts/control_center_heartbeat.php');
$check(strpos($source,'Control_license_cache::customer_context(FINANCE_ROOT,$contextFile)')!==false,'sender consumes actual cryptographically-bound customer context loader');
$check(strpos($source,"\$customerContext['identity']['instance_id']")!==false,'customer HMAC config must match context instance, not another master installation');
$check(strpos($source,"'app_version' => \$appVersion")!==false,'signed payload uses verified customer version rather than a nonexistent Git directory');
$check(strpos($source,"\$payload['runtime']=")<strpos($source,'$body = json_encode($payload'),'optional monitoring runtime remains covered by HMAC');
$reflection=new ReflectionFunction('heartbeat_database_config');
$lines=file($reflection->getFileName());$body=implode('',array_slice($lines,$reflection->getStartLine()-1,$reflection->getEndLine()-$reflection->getStartLine()+1));
$check(strpos($body,'return heartbeat_deployment_database(')<strpos($body,'include PRIVATE_DATABASE_CONFIG'),'explicit customer return precedes the unreachable legacy master include');
$child='define("FINANCE_HEARTBEAT_LIBRARY_ONLY",true);require '.var_export(dirname(__DIR__,2).'/scripts/control_center_heartbeat.php',true).';'
    .'$e=heartbeat_deployment_environment();if(($e["FINANCE_HEARTBEAT_CONFIG_FILE"]??null)!=="/private/customer/heartbeat.json")exit(2);'
    .'try{heartbeat_database_config(DeploymentConfig::fromSnapshot([]));exit(3);}catch(RuntimeException $x){echo $x->getMessage();}';
$pipes=[];$process=proc_open([PHP_BINARY,'-r',$child],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,
    ['PATH'=>'/usr/bin:/bin','FINANCE_HEARTBEAT_CONFIG_FILE'=>'/private/customer/heartbeat.json']);
if(!is_resource($process))throw new RuntimeException('FIXTURE_PROCESS_FAILED');
$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
$check($exit===0&&$out==='HEARTBEAT_DATABASE_CONFIG_INCOMPLETE'&&$error==='',
    'actual environment capture and production DB helper reject override-only customer before legacy include');

// Bounded disposable filesystem only, never PDO, HTTP or application/database bootstrap.
if(PHP_OS_FAMILY==='Linux'&&function_exists('posix_geteuid')&&posix_geteuid()===0){
    $base='/var/lib/finance-heartbeat-contract-test-'.bin2hex(random_bytes(8));
    if(!mkdir($base,0700))throw new RuntimeException('FIXTURE_DIRECTORY_FAILED');
    $config=['endpoint'=>'https://control.example.invalid/api/v1/heartbeats','public_url'=>'https://customer.example.invalid/',
        'instance_id'=>'customer-fixture','key_id'=>'00000000-0000-4000-8000-000000000001','secret'=>str_repeat('fixture-',6),'environment'=>'PRODUCTION'];
    $write=static function(string $file,array $value,int $mode=0600):void{
        $h=fopen($file,'xb');if(!$h)throw new RuntimeException('FIXTURE_FILE_EXISTS');
        try{if(fwrite($h,json_encode($value,JSON_THROW_ON_ERROR))===false||!chmod($file,$mode))throw new RuntimeException('FIXTURE_WRITE_FAILED');}finally{fclose($h);}
    };
    $probe=static function(string $file):array{
        $script='define("FINANCE_HEARTBEAT_LIBRARY_ONLY",true);require '.var_export(dirname(__DIR__,2).'/scripts/control_center_heartbeat.php',true).';heartbeat_private_json($argv[1]);echo "CONFIG_VALIDATED";';
        $p=proc_open([PHP_BINARY,'-r',$script,$file],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))throw new RuntimeException('FIXTURE_PROCESS_FAILED');
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        return ['exit'=>proc_close($p),'out'=>$stdout,'error'=>$stderr];
    };
    try{
        $safe=$base.'/safe.json';$write($safe,$config);
        $r=$probe($safe);$check($r['exit']===0&&$r['out']==='CONFIG_VALIDATED','actual private config validator accepts root-owned per-install file');
        foreach([0644,0660,0666] as $mode){
            chmod($safe,$mode);$r=$probe($safe);
            $check($r['exit']!==0&&strpos($r['error'],'config_permissions')!==false,'world-readable or group/world-writable credential config rejected');
            $check(strpos($r['out'].$r['error'],$config['secret'])===false,'permission error never reveals credential bytes');
        }
        chmod($safe,0600);symlink($safe,$base.'/link.json');
        $r=$probe($base.'/link.json');$check($r['exit']!==0,'symlink credential file rejected');
        mkdir($base.'/unsafe',0700);$write($base.'/unsafe/config.json',$config);chmod($base.'/unsafe',0777);
        $r=$probe($base.'/unsafe/config.json');$check($r['exit']!==0,'writable private parent directory rejected');
        chmod($base.'/unsafe',0700);
        $changed=$config;$changed['public_url']='https://user:fixture@example.invalid/';$write($base.'/bad-url.json',$changed);
        $r=$probe($base.'/bad-url.json');$check($r['exit']!==0&&strpos($r['error'],'config_invalid')!==false,'customer health URL with embedded credentials rejected');
        $changed=$config;unset($changed['secret']);$write($base.'/missing.json',$changed);
        $r=$probe($base.'/missing.json');$check($r['exit']!==0&&strpos($r['error'],'config_invalid')!==false,'incomplete per-install HMAC config rejected without selecting master');
    }finally{
        // Only this test's exact fresh known files; no glob, recursive deletion, user data or DB cleanup.
        foreach(['safe.json','link.json','bad-url.json','missing.json','unsafe/config.json'] as $name)if(is_file($base.'/'.$name)||is_link($base.'/'.$name))unlink($base.'/'.$name);
        if(is_dir($base.'/unsafe'))rmdir($base.'/unsafe');rmdir($base);
    }
}
echo 'All '.$checks.' customer heartbeat runtime checks passed.'."\n";
