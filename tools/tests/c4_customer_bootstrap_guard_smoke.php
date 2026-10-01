<?php
declare(strict_types=1);

// Concrete front-controller acceptance uses only a disposable copied application tree.
if (($argv[1] ?? '') === '--front-controller-child') {
    putenv('CI_ENV=testing');
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/pos';
    require $argv[2].'/index.php';
    throw new RuntimeException('CUSTOMER_BOOTSTRAP_DID_NOT_STOP');
}
require_once dirname(__DIR__,2).'/application/libraries/Control_license_cache.php';
require_once dirname(__DIR__).'/install/LinuxWebProfile.php';
if (PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||posix_geteuid()!==0)throw new RuntimeException('LINUX_ROOT_FIXTURE_REQUIRED');
$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{
    if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label."\n";
};
$source=dirname(__DIR__,2);$base='/var/lib/finance-customer-guard-test-'.bin2hex(random_bytes(8));
$root=$base.'/app';$public=$base.'/public';$contextFile=$base.'/customer-installation.json';
$gid=(int)posix_getgrnam('www')['gid'];$mask=umask(0027);
mkdir($base,0750);chgrp($base,$gid);mkdir($root,0755);mkdir($public,0750);chgrp($public,$gid);
$write=static function(string $path,string $bytes,int $mode=0644)use($gid):void{
    if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true);
    if(is_link($path))throw new RuntimeException('FIXTURE_LINK_REJECTED');
    file_put_contents($path,$bytes);chmod($path,$mode);chgrp($path,$gid);clearstatcache();
};
$remove=static function(string $path)use(&$remove,$base):void{
    if(!str_starts_with($path.'/',$base.'/'))throw new RuntimeException('FIXTURE_CLEANUP_BOUNDARY');
    if(is_link($path)||is_file($path)){unlink($path);return;}
    foreach(scandir($path)?:[]as$name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);
    rmdir($path);
};
$kp=sodium_crypto_sign_keypair();$sk=sodium_crypto_sign_secretkey($kp);$pk=sodium_crypto_sign_publickey($kp);
$now=1800000000;$identity=['instance_id'=>'fixture-instance','installation_id'=>'00000000-0000-4000-8000-000000000001','instance_public_key_sha256'=>hash('sha256','fixture-instance-key')];
$machine=Control_license_cache::customer_machine_fingerprint();
$trust=['schema'=>1,'purpose'=>'NAMUA_LICENSE_SIGNING','algorithm'=>'Ed25519','product_code'=>'NAMUA_FINANCE',
    'key_id'=>'fixture-key','status'=>'ACTIVE','public_key_base64'=>base64_encode($pk),'public_key_sha256'=>hash('sha256',$pk)];
$makeCache=static function(array $extra=[])use($identity,$now,$machine,$sk,$trust):array{
    $p=array_replace($identity+['schema'=>1,'product'=>'NAMUA_FINANCE','key_id'=>$trust['key_id'],'license_id'=>'fixture-license',
        'edition'=>'STARTER_POS','metric'=>'SERVER_INSTANCE','rights_model'=>'PERPETUAL','entitlements'=>['POS_CORE'=>true],
        'machine_fingerprint_sha256'=>$machine,'issued_at'=>gmdate(DATE_ATOM,$now-30),
        'expires_at'=>gmdate(DATE_ATOM,$now+3600),'grace_until'=>gmdate(DATE_ATOM,$now+7200)],$extra);
    if(($p['machine_fingerprint_sha256']??null)===null)unset($p['machine_fingerprint_sha256']);
    $raw=json_encode($p,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $e=['schema'=>1,'algorithm'=>'Ed25519','key_id'=>$trust['key_id'],'payload_base64'=>base64_encode($raw),
        'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n".hash('sha256',$raw),$sk))];
    return Control_license_cache::transition(Control_license_cache::initial($identity),200,
        ['license'=>$e,'token_sha256'=>hash('sha256',json_encode($e,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))],$trust,$identity,$now);
};
try{
    $core=[];$entries=[];
    foreach(Control_license_cache::customer_core_files()as$relative){
        $bytes=(string)file_get_contents($source.'/'.$relative);
        // Preserve this suite's historical v5/root layout; v6 is covered by portable acceptance.
        if($relative==='tools/release/customer_clean_profile.json')$bytes=json_encode(['profile'=>'CUSTOMER_CLEAN','profile_version'=>5]);
        $write($root.'/'.$relative,$bytes);
        $core[$relative]=hash('sha256',$bytes);$entries[]=['path'=>$relative,'sha256'=>$core[$relative],'size'=>strlen($bytes)];
    }
    $write($root.'/application/libraries/CustomerPlatform.php',file_get_contents($source.'/application/libraries/CustomerPlatform.php'));
    mkdir($root.'/application/views',0755,true);
    $appBytes=(string)file_get_contents($source.'/app-manifest.json');$app=json_decode($appBytes,true,32,JSON_THROW_ON_ERROR);
    $write($root.'/app-manifest.json',$appBytes);$write($root.'/RELEASE-MANIFEST.json',json_encode(['files'=>$entries],JSON_THROW_ON_ERROR));
    $write($public.'/trust.json',json_encode($trust),0640);$write($public.'/identity.json',json_encode($identity),0640);
    $cache=$makeCache();$saveCache=static function(array $c)use($write,$public):void{$write($public.'/runtime.json',json_encode($c,JSON_THROW_ON_ERROR),0640);};$saveCache($cache);
    $c=['schema'=>1,'purpose'=>'FINANCE_CUSTOMER_INSTALLATION','product_code'=>'NAMUA_FINANCE','release_root'=>$root,
        'release_public_id'=>'00000000-0000-4000-8000-000000000002','version'=>$app['version'],'source_commit'=>str_repeat('a',40),
        'artifact_sha256'=>hash('sha256','fixture-tar'),'app_manifest_sha256'=>hash('sha256',$appBytes),
        'source_manifest_sha256'=>hash_file('sha256',$root.'/RELEASE-MANIFEST.json'),
        'distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>5,
        'profile_sha256'=>hash_file('sha256',$root.'/tools/release/customer_clean_profile.json'),
        'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1',
        'identity'=>$identity,'machine_fingerprint_sha256'=>$machine,'core_sha256'=>$core,
        'license_trust_file'=>$public.'/trust.json','license_identity_file'=>$public.'/identity.json','license_cache_file'=>$public.'/runtime.json'];
    $wire=['schema'=>1,'context'=>'NAMUA_RELEASE_MANIFEST_V1','product_code'=>'NAMUA_FINANCE','release_public_id'=>$c['release_public_id'],
        'version'=>$c['version'],'source_commit'=>$c['source_commit'],'source_manifest_sha256'=>$c['app_manifest_sha256'],
        'filename'=>'finance-fixture.tar','sha256'=>$c['artifact_sha256'],'contains_customer_data'=>false,'contains_secrets'=>false,
        'distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>5,
        'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1',
        'customer_content_audit'=>['status'=>'PASS','profile_sha256'=>$c['profile_sha256'],'artifact_sha256'=>$c['artifact_sha256'],'source_manifest_sha256'=>$c['source_manifest_sha256']],
        'packaging'=>['profile_code'=>'CUSTOMER_CLEAN','rules_sha256'=>$c['profile_sha256'],'audience'=>'CUSTOMER','sample_data'=>'NONE']];
    $wireBytes=json_encode($wire,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$digest=hash('sha256',$wireBytes);
    $c['release_manifest_sha256']=$digest;$c['release_manifest_base64']=base64_encode($wireBytes);
    $c['release_trust']=$trust;
    $c['release_signature']=['schema'=>1,'product_code'=>'NAMUA_FINANCE','algorithm'=>'Ed25519','context'=>'NAMUA_RELEASE_MANIFEST_V1',
        'signed_file'=>'finance-fixture.release.json','key_id'=>$trust['key_id'],'manifest_sha256'=>$digest,'public_key_sha256'=>hash('sha256',$pk),
        'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_RELEASE_MANIFEST_V1\n".$digest,$sk))];
    $saveContext=static function(array $value)use($write,$contextFile):void{$write($contextFile,json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),0640);};$saveContext($c);
    $env=['FINANCE_CUSTOMER_INSTALLATION_FILE'=>$contextFile,'FINANCE_LICENSE_TRUST_FILE'=>$public.'/trust.json',
        'FINANCE_LICENSE_IDENTITY_FILE'=>$public.'/identity.json','FINANCE_LICENSE_CACHE_FILE'=>$public.'/runtime.json'];
    $server=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/pos'];
    $decision=static fn(array $e,array $s,int $at):array=>Control_license_cache::customer_guard($root,$e,$s,$at);
    $check(Control_license_cache::customer_context($root,$contextFile)['version']===$app['version'],'verified context works without Git checkout');
    define('FINANCE_HEARTBEAT_LIBRARY_ONLY',true);require_once $source.'/scripts/control_center_heartbeat.php';
    $check(heartbeat_git_version($root)==='unversioned','customer package has no Git metadata');
    $check(heartbeat_release_version($root,Control_license_cache::customer_context($root,$contextFile))===$app['version'],'real heartbeat sender obtains exact version from verified customer context');
    $check($decision($env,$server,$now)['allowed'],'Control-signed active host lease unlocks business');
    $check($decision($env,$server,$now+3601)['allowed'],'signed offline grace unlocks business');
    $check(!$decision($env,$server,$now+7201)['allowed'],'lease beyond signed grace locks business');
    $check(!$decision($env,$server,$now-301)['allowed'],'clock rollback locks business');
    $check($decision([], $server,$now)['allowed']&&!$decision([], $server,$now)['managed'],'master without managed flags is unchanged');
    $missing=$env;unset($missing['FINANCE_CUSTOMER_INSTALLATION_FILE']);
    $check($decision($missing,$server,$now)['code']==='CUSTOMER_CONTEXT_REQUIRED','removing only context flag cannot bypass managed guard');
    $badEnv=$env;$badEnv['FINANCE_LICENSE_CACHE_FILE']=$base.'/other.json';
    $check(!$decision($badEnv,$server,$now)['allowed'],'environment cannot substitute another license cache');
    foreach(['machine_fingerprint_sha256'=>hash('sha256','other-host'),'missing_machine'=>null]as$key=>$value){
        $saveCache($makeCache(['machine_fingerprint_sha256'=>$value]));
        $check($decision($env,$server,$now)['code']==='MACHINE_BINDING_MISMATCH','new customer requires signed machine binding: '.$key);
    }
    $saveCache($cache);
    $revoked=$cache;$revoked['revoked_at']=$now;$revoked['connection']='REVOKED';$saveCache($revoked);
    $check(!$decision($env,$server,$now)['allowed'],'revocation cannot be bypassed with retained active envelope');
    $saveCache(Control_license_cache::initial($identity));
    $check(!$decision($env,$server,$now)['allowed'],'unactivated or quota-denied installation is locked');
    $check(Control_license_cache::customer_context($root,$contextFile)['identity']===$identity,'heartbeat context remains readable while unactivated');
    $check(heartbeat_release_version($root,Control_license_cache::customer_context($root,$contextFile))===$app['version'],'unactivated customer still reports bound release version for monitoring');
    foreach([['GET','/login'],['POST','/auth/do_login'],['GET','/system/license'],['POST','/logout']]as[$method,$uri]){
        $d=$decision($env,['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$uri],$now);
        $check($d['allowed']&&!empty($d['recovery_only']),'exact license recovery endpoint permitted: '.$method.' '.$uri);
    }
    foreach(['/login/../pos','/%6cogin','/login%2f..%2fpos','/login/','/Login','/index.php/login','/login?c=pos','/login?m=sale','/login?d=admin','/system/license/delete','/pos','/api/export']as$uri){
        $check(!$decision($env,['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$uri],$now)['allowed'],'no recovery path/query bypass: '.$uri);
    }
    $check(!$decision($env,['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/login'],$now)['allowed'],'recovery allowlist is HTTP-method scoped');
    $pipes=[];$childEnv=['PATH'=>'/usr/bin:/bin','CI_ENV'=>'testing','FINANCE_CUSTOMER_INSTALLATION_FILE'=>$contextFile];
    $childEnv+=['FINANCE_DB_HOST'=>'127.0.0.1','FINANCE_DB_NAME'=>'fixture_db',
        'FINANCE_DB_USER'=>bin2hex(random_bytes(8)),'FINANCE_DB_PASSWORD'=>bin2hex(random_bytes(16)),
        'FINANCE_ENCRYPTION_KEY'=>bin2hex(random_bytes(32))];
    $p=proc_open([PHP_BINARY,__FILE__,'--front-controller-child',$root],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$childEnv);
    if(!is_resource($p))throw new RuntimeException('FIXTURE_PROCESS_FAILED');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
    if($exit!==1||strpos($out,'Instalasi belum berlisensi aktif')===false||$err!=='')throw new RuntimeException('FRONT_CONTROLLER_FIXTURE_DIAGNOSTIC '.json_encode(['exit'=>$exit,'out'=>$out,'err'=>$err]));
    $check(true,'real front controller locks business before CI or database bootstrap');
    $saveCache($cache);
    foreach(['release_root'=>$source,'release_public_id'=>'00000000-0000-4000-8000-000000000003',
        'version'=>'9.9.9','source_commit'=>str_repeat('b',40),'artifact_sha256'=>hash('sha256','other-tar'),
        'distribution_profile_version'=>3,'profile_sha256'=>hash('sha256','other-profile'),
        'customer_runtime_guard'=>'UNKNOWN_GUARD',
        'source_manifest_sha256'=>hash('sha256','other-inner'),'app_manifest_sha256'=>hash('sha256','other-app'),
        'machine_fingerprint_sha256'=>hash('sha256','other-machine')]as$key=>$value){
        $altered=$c;$altered[$key]=$value;$saveContext($altered);
        $check(!$decision($env,$server,$now)['allowed'],'changed context binding is rejected: '.$key);
    }
    $saveContext($c);$altered=$c;$altered['release_signature']['signature_base64']=base64_encode(random_bytes(64));$saveContext($altered);
    $check($decision($env,$server,$now)['code']==='CUSTOMER_RELEASE_PROOF_INVALID','release checksum cannot replace Ed25519 signature');$saveContext($c);
    foreach(['app-manifest.json','RELEASE-MANIFEST.json','index.php','application/libraries/Control_license_verifier.php']as$relative){
        $original=(string)file_get_contents($root.'/'.$relative);$write($root.'/'.$relative,$original."\n ");
        $check(!$decision($env,$server,$now)['allowed'],'changed immutable customer file locks business: '.$relative);
        $write($root.'/'.$relative,$original);
    }
    $identityOther=$identity;$identityOther['instance_id']='other-instance';$write($public.'/identity.json',json_encode($identityOther),0640);
    $check($decision($env,$server,$now)['code']==='CUSTOMER_IDENTITY_MISMATCH','license identity cannot be swapped');$write($public.'/identity.json',json_encode($identity),0640);
    $drift=$cache;$drift['issued_at']--;$saveCache($drift);
    $check($decision($env,$server,$now)['code']==='CACHE_WATERMARK_MISMATCH','signed cache watermark enforced');$saveCache($cache);
    chmod($contextFile,0660);clearstatcache();$check(!$decision($env,$server,$now)['allowed'],'web-writable installation context rejected');chmod($contextFile,0640);clearstatcache();
    $otherFile=$base.'/context-copy.json';copy($contextFile,$otherFile);chmod($otherFile,0640);unlink($contextFile);symlink($otherFile,$contextFile);
    $check(!$decision($env,$server,$now)['allowed'],'symlink context rejected');unlink($contextFile);$saveContext($c);
    $profile=['app_root'=>$root,'state_root'=>$base,'deployment_file'=>$contextFile,'tls_certificate'=>$contextFile,'tls_key'=>$contextFile,
        'mime_types'=>$contextFile,'user'=>'finance_trial','group'=>'finance_trial','port'=>18443,'license_public_dir'=>$public,'customer_installation_file'=>$contextFile];
    $rendered=LinuxWebProfile::render($profile);
    $check(strpos($rendered['php-fpm.conf'],'env[FINANCE_CUSTOMER_INSTALLATION_FILE] = '.$contextFile)!==false,'new managed FPM always receives central customer guard context');
    $check(strpos($rendered['php-fpm.conf'],'env[FINANCE_LICENSE_CACHE_FILE] = '.$public.'/runtime.json')!==false,'managed FPM binds exact cache outside source');
    $check(Control_license_cache::customer_verification($root,$contextFile,$now)['status']==='ACTIVE','strict health verification succeeds only with usable host lease');
    define('BASEPATH',$root.'/system/');define('FCPATH',$root.'/');
    class CI_Model { public $load;public $db; }
    require_once $source.'/application/models/License_runtime_model.php';
    $db=new class { public int $calls=0;public function table_exists(string $table):bool{$this->calls++;throw new RuntimeException('FIXTURE_SQL_ACCESS_FORBIDDEN');} };
    $model=static function()use($db):License_runtime_model{
        $m=new License_runtime_model();$m->db=$db;$m->load=new class { public function library(string $name):void{} };return $m;
    };
    $oldEnv=getenv('FINANCE_CUSTOMER_INSTALLATION_FILE');putenv('FINANCE_CUSTOMER_INSTALLATION_FILE='.$contextFile);
    try{
        $actual=time();$modelCache=$makeCache(['issued_at'=>gmdate(DATE_ATOM,$actual-30),'expires_at'=>gmdate(DATE_ATOM,$actual+3600),'grace_until'=>gmdate(DATE_ATOM,$actual+7200)]);
        $modelCache['last_seen_at']=$actual;$modelCache['synced_at']=$actual;$saveCache($modelCache);
        $m=$model();$check($m->verification()['status']==='ACTIVE'&&$db->calls===0,'actual license status model uses host-bound context even without cache env selector');
        $modelCache['revoked_at']=$actual;$saveCache($modelCache);$m=$model();
        $check($m->verification()['status']==='REVOKED'&&$m->current_license()===[]&&$db->calls===0,'actual customer status cannot advertise revoked cached envelope as active');
        $bad=$c;$bad['release_root']=$source;$saveContext($bad);$m=$model();
        $check(!$m->verification()['verified']&&$db->calls===0,'invalid customer context never falls back to local SQL license authority');$saveContext($c);
    }finally{putenv($oldEnv===false?'FINANCE_CUSTOMER_INSTALLATION_FILE':'FINANCE_CUSTOMER_INSTALLATION_FILE='.$oldEnv);}
    echo 'All '.$checks." customer bootstrap guard checks passed; no database or network access.\n";
}finally{
    sodium_memzero($sk);sodium_memzero($kp);$remove($base);umask($mask);
}
