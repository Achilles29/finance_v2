<?php
declare(strict_types=1);

/** Opt-in, root-owned isolated processes, ephemeral signing key, NEVER a release/publish gate attestation. */
require_once dirname(__DIR__).'/install/FinanceInstance.php';
if (PHP_SAPI!=='cli'||posix_geteuid()!==0||!in_array('--disposable',$argv,true))throw new RuntimeException('EXPLICIT_DISPOSABLE_TEST_REQUIRED');
$source=dirname(__DIR__,2);$base='/var/lib/finance-local-e2e-'.bin2hex(random_bytes(6));
$root=$base.'/app';$gid=(int)posix_getgrnam('www')['gid'];$checks=0;$dbProcess=null;$instance=null;$started=false;
$mask=umask(0022);mkdir($base,0750);chgrp($base,$gid);
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$name);$checks++;echo 'PASS '.$name."\n";};
$reject=static function(callable $call,string $name)use($check):void{try{$call();}catch(Throwable $e){$check(true,$name);return;}$check(false,$name);};
$write=static function(string $p,string $s,int $mode=0644)use($gid):void{
    if(!is_dir(dirname($p)))mkdir(dirname($p),0755,true);
    if(is_link($p))throw new RuntimeException('FIXTURE_LINK_REJECTED');
    file_put_contents($p,$s);chmod($p,$mode);chgrp($p,$gid);clearstatcache();
};
$json=static function(string $p,array $v,int $mode=0600)use($write):void{$write($p,json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$mode);};
$run=static function(array $cmd,string $label)use($base):string{
    if ($label==='MYSQL_INIT') {
        // Datadir initialization can exceed the archive helper's fixed 30s under parallel I/O.
        PrivateDeployment::run($cmd,$base.'/database-init.log',180);
        return '';
    }
    $r=financeArtifactSignatureRun($cmd);
    if($r['code']!==0||$r['overflow'])throw new RuntimeException('FIXTURE_COMMAND_FAILED_'.$label);
    return $r['stdout'];
};
$remove=static function(string $p)use(&$remove,$base):void{
    if(!str_starts_with($p.'/',$base.'/'))throw new RuntimeException('CLEANUP_BOUNDARY');
    if(is_link($p)||is_file($p)){unlink($p);return;}
    foreach(scandir($p)?:[]as$n)if($n!=='.'&&$n!=='..')$remove($p.'/'.$n);rmdir($p);
};
try {
    // Build a disposable contract fixture. Its stub gate evidence is NOT release evidence.
    $snapshot=$base.'/source';mkdir($snapshot,0755);
    $profile=CustomerReleaseProfile::fromRoot($source);
    $raw=json_decode(file_get_contents($source.'/'.CustomerReleaseProfile::PATH),true);
    $paths=array_unique(array_merge($raw['files'],$raw['code_files'],array_keys($raw['static_sha256']),array_keys($raw['sql_sha256'])));
    foreach($paths as$p)$write($snapshot.'/'.$p,file_get_contents($source.'/'.$p),(fileperms($source.'/'.$p)&0111)?0755:0644);
    foreach(['a4_release_preflight_smoke.php','a4_static_analysis_smoke.php','a4_dependency_vulnerability_smoke.php']as$gate){
        $write($snapshot.'/tools/tests/'.$gate,"<?php echo 'CONTRACT_FIXTURE_NOT_RELEASE';\n");
    }
    $run(['git','init','-q',$snapshot],'GIT_INIT');$run(['git','-C',$snapshot,'add','-f','.'],'GIT_ADD');
    $run(['git','-C',$snapshot,'-c','user.name=Disposable fixture','-c','user.email=fixture@example.invalid',
        '-c','commit.gpgsign=false','-c','core.hooksPath=/dev/null','commit','-qm','isolated installer fixture, not production cutoff'],'GIT_COMMIT');
    $artifact=$base.'/customer-fixture.tar';
    $run([PHP_BINARY,$source.'/tools/release/build_release_artifact.php','--root='.$snapshot,'--output='.$artifact,'--source-epoch=1700000000'],'ARTIFACT');
    $m=ControlReleaseBridge::describe($snapshot,$artifact);
    $kp=sodium_crypto_sign_keypair();$sk=sodium_crypto_sign_secretkey($kp);$pk=sodium_crypto_sign_publickey($kp);
    $key=['schema'=>1,'product_code'=>'NAMUA_FINANCE','algorithm'=>'Ed25519','key_id'=>'00000000-0000-4000-8000-000000000009',
        'public_key_base64'=>base64_encode($pk),'public_key_sha256'=>hash('sha256',$pk),'secret_key_base64'=>base64_encode($sk)];
    $trust=$key;unset($trust['secret_key_base64']);$trust['status']='ACTIVE';
    $wire=['schema'=>1,'context'=>ControlReleaseBridge::CONTEXT,'product_code'=>'NAMUA_FINANCE',
        'release_public_id'=>'00000000-0000-4000-8000-000000000002','version'=>$m['version'],'channel'=>'ALPHA',
        'source_commit'=>$m['source_commit'],'source_manifest_sha256'=>hash_file('sha256',$snapshot.'/app-manifest.json'),
        'build_request_sha256'=>hash('sha256','FIXTURE'),'build_report_sha256'=>hash('sha256','FIXTURE_REPORT'),
        'filename'=>basename($artifact),'media_type'=>'application/x-tar','size_bytes'=>filesize($artifact),'sha256'=>hash_file('sha256',$artifact),
        'contains_customer_data'=>false,'contains_secrets'=>false,'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1',
        'distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>5,'customer_content_audit'=>$m['customer_content_audit'],
        'packaging'=>['profile_code'=>'CUSTOMER_CLEAN','rules_sha256'=>$profile->digest(),'audience'=>'CUSTOMER','sample_data'=>'NONE'],'verification'=>[]];
    foreach(ControlReleaseBridge::BUILD_GATES as$g)$wire['verification'][$g]=['status'=>'PASS','evidence_sha256'=>hash('sha256','FIXTURE_ONLY:'.$g)];
    $bytes=json_encode($wire,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$manifest=$base.'/customer-fixture.release.json';
    $write($manifest,$bytes,0600);$json($base.'/customer-fixture.release.sig.json',ControlReleaseBridge::sign($bytes,basename($manifest),$key));
    $json($base.'/release-trust.json',$trust);unset($key);
    $check(!in_array('config/customer.json',$paths,true)&&is_file($snapshot.'/config/customer.example.json'),'artifact includes template but no local secrets');

    // Initialize a NEW private MariaDB datadir/socket, never /tmp/mysql.sock or any operational service.
    $mysql='/www/server/mysql';$dbdir=$base.'/mysql';mkdir($dbdir,0750);chgrp($dbdir,$gid);
    $run([$mysql.'/scripts/mariadb-install-db','--no-defaults','--basedir='.$mysql,'--datadir='.$dbdir.'/data',
        '--auth-root-authentication-method=normal','--skip-test-db'],'MYSQL_INIT');
    $socket=$dbdir.'/db.sock';
    $dbProcess=proc_open([$mysql.'/bin/mariadbd','--no-defaults','--user=root','--basedir='.$mysql,'--datadir='.$dbdir.'/data',
        '--socket='.$socket,'--pid-file='.$dbdir.'/db.pid','--log-error='.$dbdir.'/db.log','--skip-networking','--skip-log-bin',
        '--skip-name-resolve','--innodb-buffer-pool-size=64M','--character-set-server=utf8mb4','--collation-server=utf8mb4_unicode_ci'],
        [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,$base);
    if(!is_resource($dbProcess))throw new RuntimeException('DISPOSABLE_DB_START_FAILED');
    $admin=$dbdir.'/admin.cnf';$write($admin,"[client]\nprotocol=socket\nsocket=$socket\nuser=root\n",0600);
    for($i=0;$i<100;$i++){
        $r=financeArtifactSignatureRun([$mysql.'/bin/mariadb','--defaults-file='.$admin,'--connect-timeout=1','--execute=SELECT 1']);
        if($r['code']===0)break;if(!proc_get_status($dbProcess)['running'])throw new RuntimeException('DISPOSABLE_DB_DIED');usleep(100000);
    }
    $check($r['code']===0,'isolated MariaDB ready with networking disabled');
    $pdo=new PDO('mysql:unix_socket='.$socket.';dbname=mysql','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $dbUser='u_'.bin2hex(random_bytes(6));$dbPass=bin2hex(random_bytes(20)).'"\\#;$';
    $pdo->exec('CREATE DATABASE finance_customer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('CREATE DATABASE finance_partial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('CREATE USER '.$pdo->quote($dbUser)."@'localhost' IDENTIFIED BY ".$pdo->quote($dbPass));
    $pdo->exec('GRANT ALL ON finance_customer.* TO '.$pdo->quote($dbUser)."@'localhost'");
    $pdo->exec('GRANT SELECT,CREATE,ALTER,DROP,INDEX ON finance_partial.* TO '.$pdo->quote($dbUser)."@'localhost'");
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',$base.'/tls.key','-out',$base.'/tls.crt',
        '-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1'],'TLS');
    chmod($base.'/tls.key',0600);
    foreach(['private'=>0700,'runtime'=>0750,'public'=>0750]as$dir=>$mode){mkdir($base.'/'.$dir,$mode);chgrp($base.'/'.$dir,$gid);chmod($base.'/'.$dir,$mode);}
    $identity=['instance_id'=>'fixture-instance','installation_id'=>'00000000-0000-4000-8000-000000000001','instance_public_key_sha256'=>hash('sha256','fixture-agent')];
    $licenseTrust=$trust+['purpose'=>'NAMUA_LICENSE_SIGNING'];
    $json($base.'/public/identity.json',$identity,0640);$json($base.'/public/trust.json',$licenseTrust,0640);
    $json($base.'/public/runtime.json',Control_license_cache::initial($identity),0640);
    $ownerPassword=bin2hex(random_bytes(16)).'A1!';
    $ownerUsername='owner_'.bin2hex(random_bytes(6));
    $owner=['username'=>$ownerUsername,'email'=>'owner@example.invalid','password'=>$ownerPassword];$json($base.'/owner.json',$owner);
    $job=['configuration_source'=>'customer_local','private_dir'=>$base.'/private','runtime_dir'=>$base.'/runtime',
        'release_root'=>$root,'signed_manifest'=>$manifest,'trust_file'=>$base.'/release-trust.json','owner_file'=>$base.'/owner.json',
        'php_fpm'=>'/www/server/php/81/sbin/php-fpm','nginx'=>'/www/server/nginx/sbin/nginx','user'=>'www','group'=>'www','port'=>$port,
        'tls_certificate'=>$base.'/tls.crt','tls_key'=>$base.'/tls.key','mime_types'=>'/www/server/nginx/conf/mime.types',
        'lua_root'=>'/www/server/nginx/lib/lua','composer'=>'/usr/bin/composer','mode'=>'clean_install',
        'license_public_dir'=>$base.'/public','instance_id'=>$identity['instance_id'],'health_ca'=>$base.'/tls.crt'];
    $instance=new FinanceInstance($job);$stage=$instance->stage();$check($stage['status']==='STAGED','real installer verifies signed fixture and stages source');
    $config=['schema'=>1,'database'=>['host'=>'localhost','port'=>3306,'socket'=>$socket,'name'=>'finance_customer','user'=>$dbUser,'password'=>$dbPass],
        'base_url'=>'https://127.0.0.1:'.$port.'/','encryption_key'=>bin2hex(random_bytes(32)),
        'runtime'=>['directory'=>'../runtime','session_cookie'=>'finance_fixture_session']];
    $save=static function(array $c)use($json,$root):void{$json($root.'/config/customer.json',$c,0640);};$save($config);
    $cli=$run([PHP_BINARY,$root.'/tools/install/customer_config.php','check'],'CLI_CONFIG');
    $check(json_decode($cli,true)['status']==='PASS'&&strpos($cli,$dbPass)===false,'actual extracted CLI reads local config without environment and hides password');
    $options=['release-root'=>$root,'signed-manifest'=>$manifest,'trust-file'=>$base.'/release-trust.json','owner-file'=>$base.'/owner.json'];
    $bad=$config;$bad['database']['password']=bin2hex(random_bytes(20));$save($bad);
    $reject(fn()=>c3InstallDatabase($options),'wrong password cannot run baseline');
    $check(!is_file($base.'/runtime/.installer/database-attempt.json'),'failed authentication does not claim SQL started');$save($config);
    $pdo->exec('CREATE TABLE finance_customer.preexisting (id INT)');
    $reject(fn()=>c3InstallDatabase($options),'nonempty database rejected');
    $check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="finance_customer"')->fetchColumn()===1,'nonempty database not wiped');
    $pdo->exec('DROP TABLE finance_customer.preexisting'); // This fixture-created table only, in the new private daemon.
    $write($root.'/config/extra.json','{}');$reject(fn()=>c3InstallDatabase($options),'no broad config directory integrity exception');unlink($root.'/config/extra.json');
    $original=file_get_contents($root.'/application/config/database.php');$write($root.'/application/config/database.php',$original."\n");
    $reject(fn()=>c3InstallDatabase($options),'immutable database.php tampering rejected before SQL');$write($root.'/application/config/database.php',$original);
    $partial=$config;$partial['database']['name']='finance_partial';$partial['runtime']['directory']='../partial-runtime';$save($partial);
    $reject(fn()=>c3InstallDatabase($options),'simulated insufficient DML privilege leaves partial install');
    $journal=$base.'/partial-runtime/.installer/database-attempt.json';$before=hash_file('sha256',$journal);
    $check(json_decode(file_get_contents($journal),true)['status']==='STARTED','uncertain DDL attempt journal retained');
    $tables=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="finance_partial"')->fetchColumn();
    $check($tables>0,'partial DDL actually created tables in disposable DB');
    $reject(fn()=>c3InstallDatabase($options),'partial attempt never automatically replays SQL');
    $check(hash_file('sha256',$journal)===$before&&$tables===(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="finance_partial"')->fetchColumn(),'retry preserves journal and partial tables');
    $save($config);$install=$instance->install();
    $check($install['status']==='PREPARED','full local installer prepares baseline, migrations, owner, runtime and signed context');
    $attempt=json_decode(file_get_contents($base.'/runtime/.installer/database-attempt.json'),true);
    $check($attempt['status']==='COMPLETE','successful DB install recorded COMPLETE');
    $check(strpos(file_get_contents($base.'/runtime/php-fpm.conf'),'env[')===false,'generated PHP-FPM requires NO custom environment variables');
    $check(glob($base.'/runtime/.installer/connection-*')===[],'temporary database credentials removed after full install');
    $reject(fn()=>$instance->install(),'second full install blocked by recorded state');
    $started=true;$instance->start();
    $cookie=$base.'/cookies.txt';
    $http=static function(string $path,?array $post=null)use($port,$base,$cookie):array{
        $c=curl_init('https://127.0.0.1:'.$port.$path);
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_TIMEOUT=>20,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
        if($post!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$post);
        $raw=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$size=curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
        if(!is_string($raw))throw new RuntimeException('FIXTURE_HTTPS_FAILED');
        return ['code'=>$code,'headers'=>substr($raw,0,$size),'body'=>substr($raw,$size)];
    };
    foreach(['/config','/config/','/config/customer.json','/config/customer.example.json','/config/.htaccess','/config/../config/customer.json',
        '/config/customer.json/extra','/co%6efig/customer.json','/config%2Fcustomer.json','/config/customer.json?download=1']as$p){
        $r=$http($p);$check($r['code']===404&&strpos($r['body'],$dbPass)===false,'nginx blocks configuration HTTP access '.$p);
    }
    $check($http('/pos')['code']===423,'unactivated/denied lease locks business without env selectors');
    $login=$http('/login');$check($login['code']===200&&strpos($login['body'],'name="identifier"')!==false,'real login renders via local DB, nginx and FPM');
    $now=time();$payload=$identity+['schema'=>1,'product'=>'NAMUA_FINANCE','key_id'=>$trust['key_id'],'license_id'=>'fixture-license',
        'edition'=>'STARTER_POS','metric'=>'SERVER_INSTANCE','rights_model'=>'PERPETUAL','entitlements'=>['POS_CORE'=>true],
        'machine_fingerprint_sha256'=>Control_license_cache::customer_machine_fingerprint(),'issued_at'=>gmdate(DATE_ATOM,$now-1),
        'expires_at'=>gmdate(DATE_ATOM,$now+3600),'grace_until'=>gmdate(DATE_ATOM,$now+7200)];
    $raw=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $envelope=['schema'=>1,'algorithm'=>'Ed25519','key_id'=>$trust['key_id'],'payload_base64'=>base64_encode($raw),
        'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_LICENSE_V1\n".hash('sha256',$raw),$sk))];
    $cache=Control_license_cache::transition(Control_license_cache::initial($identity),200,
        ['license'=>$envelope,'token_sha256'=>hash('sha256',json_encode($envelope,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))],$licenseTrust,$identity,$now);
    $json($base.'/public/runtime.json',$cache,0640);
    $auth=$http('/auth/do_login',['identifier'=>$owner['username'],'password'=>$owner['password']]);
    $check(in_array($auth['code'],[302,303],true)&&stripos($auth['headers'],'Location: '.$config['base_url'].'login')===false,'actual first owner login succeeds with disposable signed lease');
    $health=$instance->health();$check($health['status']==='PASS'&&$health['web_verified'],'full licensed HTTPS and database health PASS');
    $changed=$config;$changed['base_url']='https://changed.example/';$save($changed);
    $v=Control_license_cache::customer_verification($root,$base.'/runtime/customer-installation.json');
    $check($v['status']==='ACTIVE','customer URL is mutable metadata, not a domain license lock');$save($config);
    $ctx=$base.'/runtime/customer-installation.json';rename($ctx,$ctx.'.saved');
    $check($http('/pos')['code']===423,'removing installation context cannot bypass license');rename($ctx.'.saved',$ctx);
    rename($root.'/config/customer.json',$base.'/config.saved');
    $check($http('/pos')['code']===503,'deleting local config does not connect to master or skip guard');rename($base.'/config.saved',$root.'/config/customer.json');
    $write($root.'/config/customer.json','{broken',0640);$check($http('/login')['code']===503,'malformed JSON fails safely in actual web bootstrap');$save($config);
    chmod($root.'/config/customer.json',0666);clearstatcache();$check($http('/login')['code']===503,'unsafe permission fails in actual web bootstrap');$save($config);
    $json($base.'/copy.json',$config,0640);unlink($root.'/config/customer.json');symlink($base.'/copy.json',$root.'/config/customer.json');
    $check($http('/login')['code']===503,'symlink fails in actual web bootstrap');unlink($root.'/config/customer.json');$save($config);
    $original=file_get_contents($root.'/application/config/database.php');$write($root.'/application/config/database.php',$original."\n");
    $check($http('/pos')['code']===423,'core database mapping tampering cannot bypass runtime integrity');$write($root.'/application/config/database.php',$original);
    echo json_encode(['status'=>'PASS','checks'=>$checks,'kind'=>'DISPOSABLE_INSTALLER_WEB_ACCEPTANCE_NOT_RELEASE',
        'version'=>$m['version'],'profile_version'=>5,'health'=>$health['health'],'production_database_accessed'=>false,
        'custom_fpm_environment'=>false,'published'=>false],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} finally {
    if($started&&$instance!==null){try{$instance->stop();}catch(Throwable $e){echo "CLEANUP web stop requires inspection: ".get_class($e)."\n";}}
    if(is_resource($dbProcess)){proc_terminate($dbProcess,15);proc_close($dbProcess);}
    if(isset($sk))sodium_memzero($sk);if(isset($kp))sodium_memzero($kp);
    // Only freshly created disposable artifacts/data, never Finance runtime or master data.
    $remove($base);umask($mask);
}
