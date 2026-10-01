<?php
declare(strict_types=1);
/** Real disposable DB + HTTPS nginx/FPM. Control responses are SYNTHETIC, not publish/activation evidence. */
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||posix_geteuid()!==0||!in_array('--disposable',$argv,true))throw new RuntimeException('DISPOSABLE_LINUX_ROOT_HARNESS_REQUIRED');
$source=dirname(__DIR__,2);require $source.'/tools/release/ControlReleaseBridge.php';
require $source.'/tools/install/PrivateDeployment.php';
$base='/var/lib/finance-portable-'.bin2hex(random_bytes(8));$root=$base.'/finance';$processes=[];$checks=0;
$owner=posix_getpwnam('namua-build')['uid'];$web=posix_getpwnam('www')['uid'];$group=posix_getgrnam('www')['gid'];
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name."\n";$checks++;};
$write=static function(string $path,string $data,int $mode=0640)use($owner,$group):void{if(!is_dir(dirname($path)))mkdir(dirname($path),0750,true);file_put_contents($path,$data);chown($path,$owner);chgrp($path,$group);chmod($path,$mode);clearstatcache();};
$json=static function(string $path,array $v,int $mode=0600)use($write):void{$write($path,json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$mode);};
$run=static function(array $command):array{return financeArtifactSignatureRun($command);};
$worker=static function(string $mode)use($base,$source):array{
    $log=$base.'/worker-'.bin2hex(random_bytes(5)).'.log';
    try{PrivateDeployment::run(['/usr/sbin/runuser','-u','namua-build','-g','www','--',PHP_BINARY,$source.'/tools/tests/customer_portable_worker_fixture.php',$base.'/fixture.json',$mode],$log,240);}catch(Throwable $e){}
    $output=file_get_contents($log);$v=json_decode($output,true);if(!is_array($v))throw new RuntimeException('WORKER_OUTPUT_INVALID '.substr($output,0,800));return $v;
};
$http=static function(string $path,?array $body=null)use(&$port,$base):array{$c=curl_init('https://127.0.0.1:'.$port.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_TIMEOUT=>20]);if($body!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);$b=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return ['status'=>$status,'body'=>$b,'json'=>is_string($b)?json_decode($b,true):null];};
$remove=static function(string $path)use(&$remove,$base):void{if(!str_starts_with($path.'/',$base.'/'))throw new RuntimeException('CLEANUP_SCOPE');if(is_file($path)||is_link($path)){unlink($path);return;}foreach(scandir($path)?:[]as$n)if($n!=='.'&&$n!=='..')$remove($path.'/'.$n);rmdir($path);};
mkdir($base,0750);chgrp($base,$group);mkdir($root,0750);
try {
    $profile=CustomerReleaseProfile::fromRoot($source);$p=json_decode(file_get_contents($source.'/'.CustomerReleaseProfile::PATH),true);
    $paths=array_unique(array_merge($p['files'],$p['code_files'],array_keys($p['static_sha256']),array_keys($p['sql_sha256'])));$entries=[];
    foreach($paths as $path){$data=file_get_contents($source.'/'.$path);$target=CustomerLayout::target($path);$write($root.'/'.$target,$data,0644);$entries[]=['path'=>$target,'sha256'=>hash('sha256',$data),'size'=>strlen($data),'mode'=>'0644'];}
    $profile->audit($entries);usort($entries,fn($a,$b)=>strcmp($a['path'],$b['path']));
    $json($root.'/RELEASE-MANIFEST.json',['schema'=>'finance.release-artifact-manifest','schema_version'=>1,'source_epoch'=>1700000000,'files'=>$entries],0644);
    $list=$base.'/tar.list';$write($list,implode("\n",array_merge(array_column($entries,'path'),['RELEASE-MANIFEST.json'])));
    $tar=$base.'/package.tar';$r=$run(['/usr/bin/tar','--create','--format=gnu','--owner=0','--group=0','--numeric-owner','--no-recursion','-C',$root,'-T',$list,'-f',$tar]);
    $check($r['code']===0,'disposable mapped TAR built without moving development files');
    $inspection=ControlReleaseBridge::inspect($tar);$check(($inspection['distribution_profile_version']??null)===11,'independent TAR validator accepts mapped update profile v11');
    $r=$run(['sh',$source.'/tools/install/portable/prepare-linux.sh',$root,'namua-build','www','www']);$check($r['code']===0,'one-time provisioning with distinct non-root worker/web accounts');
    // All evidence stays inside one parent folder; trust is a generated test-only issuer.
    $kp=sodium_crypto_sign_keypair();$sk=sodium_crypto_sign_secretkey($kp);$pk=sodium_crypto_sign_publickey($kp);
    $trust=['schema'=>1,'product_code'=>'NAMUA_FINANCE','algorithm'=>'Ed25519','status'=>'ACTIVE','key_id'=>'00000000-0000-4000-8000-000000000011','public_key_base64'=>base64_encode($pk),'public_key_sha256'=>hash('sha256',$pk)];
    $wire=['schema'=>1,'context'=>'NAMUA_RELEASE_MANIFEST_V1','product_code'=>'NAMUA_FINANCE','release_public_id'=>'00000000-0000-4000-8000-000000000012',
        'version'=>'0.1.0-alpha.23','source_commit'=>str_repeat('a',40),'filename'=>'package.tar','media_type'=>'application/x-tar','size_bytes'=>filesize($tar),'sha256'=>hash_file('sha256',$tar),
        'source_manifest_sha256'=>hash_file('sha256',$root.'/app-manifest.json'),'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1','distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>11,
        'application_update_contract'=>'FINANCE_APPLICATION_UPDATE_V1',
        'customer_content_audit'=>['status'=>'PASS','profile_sha256'=>$profile->digest(),'artifact_sha256'=>hash_file('sha256',$tar),'source_manifest_sha256'=>hash_file('sha256',$root.'/RELEASE-MANIFEST.json')],
        'packaging'=>['profile_code'=>'CUSTOMER_CLEAN','rules_sha256'=>$profile->digest(),'audience'=>'CUSTOMER','sample_data'=>'NONE'],'contains_customer_data'=>false,'contains_secrets'=>false,'verification'=>[]];
    foreach(ControlReleaseBridge::BUILD_GATES as $g)$wire['verification'][$g]=['status'=>'PASS','evidence_sha256'=>hash('sha256','FIXTURE_NOT_RELEASE_'.$g)];
    $raw=json_encode($wire,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $dir=$root.'/private/delivery';$write($dir.'/release.json',$raw,0600);$write($dir.'/package.tar',file_get_contents($tar),0600);
    $json($dir.'/release-trust.json',$trust);$json($dir.'/release.sig.json',ControlReleaseBridge::sign($raw,'package.release.json',$trust+['secret_key_base64'=>base64_encode($sk)]));
    $secret=bin2hex(random_bytes(32));$monitor=bin2hex(random_bytes(32));
    $credential=['instance_id'=>'portable-fixture','environment'=>'STAGING','control_origin'=>'https://control.example.invalid','activation_code'=>'nla_'.bin2hex(random_bytes(20)),
        'license_trust'=>$trust+['purpose'=>'NAMUA_LICENSE_SIGNING'],'monitoring'=>['key_id'=>'00000000-0000-4000-8000-000000000013','secret'=>$monitor]];
    $json($dir.'/credentials.json',$credential);
    $permit=['purpose'=>'NAMUA_FINANCE_SETUP_V1','product_code'=>'NAMUA_FINANCE','permit_id'=>'00000000-0000-4000-8000-000000000014','instance_id'=>'portable-fixture',
        'deployment_id'=>'00000000-0000-4000-8000-000000000015','plan_sha256'=>hash('sha256','fixture-plan'),'release_public_id'=>$wire['release_public_id'],'source_commit'=>$wire['source_commit'],
        'release_manifest_sha256'=>hash('sha256',$raw),'artifact_sha256'=>$wire['sha256'],'profile_sha256'=>$profile->digest(),'profile_version'=>11,'environment'=>'STAGING',
        'credentials_sha256'=>hash_file('sha256',$dir.'/credentials.json'),'setup_secret_sha256'=>hash('sha256',$secret),'issued_at'=>time()-86400*45,'expires_at'=>null,'permission_policy'=>'UNTIL_USED_OR_REVOKED'];
    $signPermit=static function(array $value)use($sk,$trust,$dir,$json):void{$raw=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$json($dir.'/permit.json',['key_id'=>$trust['key_id'],'payload_base64'=>base64_encode($raw),'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_FINANCE_SETUP_V1\n".hash('sha256',$raw),$sk))]);};$signPermit($permit);
    $fixture=['root'=>$root,'issuer'=>base64_encode($sk),'trust'=>$trust,'issued'=>time()-5,'monitoring_secret'=>$monitor];$json($base.'/fixture.json',$fixture);
    $r=$worker('prepare');$check($r['ok'],'non-root companion verifies signature/permit/profile and creates unique agent '.json_encode($r));
    $agentFile=$root.'/private/agent/agent.json';$identityHash=hash_file('sha256',$agentFile);
    $r=$worker('prepare');$check($r['ok']&&hash_file('sha256',$agentFile)===$identityHash,'repeated preparation preserves installation identity');
    $expired=$permit;$expired['expires_at']=time()-1;$expired['issued_at']=time()-100;$signPermit($expired);
    $r=$worker('prepare');$check(!$r['ok']&&$r['code']==='SETUP_PERMISSION_BINDING_INVALID','v8 permit with an injected expiry is rejected before SQL');
    $permit['permit_id']='00000000-0000-4000-8000-000000000016';$signPermit($permit);$r=$worker('prepare');
    $check($r['ok']&&hash_file('sha256',$agentFile)===$identityHash,'replacement permission preserves existing agent identity and evidence');
    foreach(['artifact_sha256','release_manifest_sha256','profile_sha256','source_commit','release_public_id','profile_version'] as $field) {
        $bad=$permit;$bad[$field]=$field==='profile_version'?5:($field==='source_commit'?str_repeat('b',40):str_repeat('b',64));$signPermit($bad);
        $r=$worker('permit');$check(!$r['ok'],'signed but mismatched setup permission rejected: '.$field);
    }
    foreach(['instance_id','plan_sha256','deployment_id'] as $field) {
        $bad=$permit;$bad['permit_id']='00000000-0000-4000-8000-000000000020';$bad[$field]=$field==='instance_id'?'different-instance':($field==='plan_sha256'?str_repeat('c',64):'00000000-0000-4000-8000-000000000021');$signPermit($bad);
        $r=$worker('prepare');$check(!$r['ok']&&in_array($r['code'],['REPLACEMENT_PERMISSION_BINDING_INVALID','DELIVERY_CREDENTIAL_BINDING_INVALID'],true),'replacement keeps original binding: '.$field);
    }
    $signPermit($permit);$saved=file_get_contents($dir.'/permit.json');$bad=json_decode($saved,true);$bad['signature_base64']=base64_encode(random_bytes(64));$json($dir.'/permit.json',$bad);
    $check(!$worker('permit')['ok'],'forged permission signature rejected');$write($dir.'/permit.json',$saved,0600);
    // Isolated DB only: new datadir + Unix socket; no live TCP/database connection.
    $mysql='/www/server/mysql';$dbdir=$base.'/db';mkdir($dbdir,0750);chgrp($dbdir,$group);
    PrivateDeployment::run([$mysql.'/scripts/mariadb-install-db','--no-defaults','--basedir='.$mysql,'--datadir='.$dbdir.'/data','--auth-root-authentication-method=normal','--skip-test-db'],$base.'/mysql-init.log',180);
    $socket=$dbdir.'/mysql.sock';$processes[]=proc_open([$mysql.'/bin/mariadbd','--no-defaults','--user=root','--basedir='.$mysql,'--datadir='.$dbdir.'/data','--socket='.$socket,'--pid-file='.$dbdir.'/pid','--log-error='.$dbdir.'/error.log','--skip-networking','--skip-log-bin','--innodb-buffer-pool-size=64M'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    $pdo=null;for($n=0;$n<150;$n++){try{$pdo=new PDO('mysql:unix_socket='.$socket.';dbname=mysql','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);break;}catch(Throwable $e){usleep(100000);}}
    $check($pdo instanceof PDO,'new disposable MariaDB socket online; live database not accessed');
    $dbPassword=bin2hex(random_bytes(24));$pdo->exec('CREATE DATABASE portable_customer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("CREATE USER 'portable_user'@'localhost' IDENTIFIED BY ".$pdo->quote($dbPassword));$pdo->exec("GRANT ALL ON portable_customer.* TO 'portable_user'@'localhost'");
    $fixture['database']=['host'=>'localhost','port'=>3306,'socket'=>$socket,'name'=>'portable_customer','user'=>'portable_user','password'=>$dbPassword];
    $ownerUsername='owner_'.bin2hex(random_bytes(6));
    $fixture['owner']=['username'=>$ownerUsername,'email'=>'owner@example.invalid','password'=>bin2hex(random_bytes(14)).'Z9!'];$fixture['release_hash']=hash('sha256',$raw);$json($base.'/fixture.json',$fixture);
    $badFixture=$fixture;$badFixture['database']['password']=bin2hex(random_bytes(20));$json($base.'/fixture.json',$badFixture);
    $r=$worker('db');$check(!$r['ok']&&$r['code']==='DATABASE_CREDENTIAL_REJECTED'&&!is_file($root.'/private/database.json'),'wrong database password rejected without SQL journal');$json($base.'/fixture.json',$fixture);
    $pdo->exec('CREATE TABLE portable_customer.preexisting (id INT)');$r=$worker('db');
    $check(!$r['ok']&&$r['code']==='DATABASE_NOT_EMPTY','nonempty database rejected without deleting table');
    $check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="portable_customer"')->fetchColumn()===1,'preexisting disposable table retained');
    $pdo->exec('DROP TABLE portable_customer.preexisting'); // Only this fixture-created table in this new isolated daemon.
    // HTTPS + actual FPM worker www, no FINANCE_* environment or CI_ENV override.
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',$base.'/tls.key','-out',$base.'/tls.crt','-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1']);
    $write($root.'/private/ca.pem',file_get_contents($base.'/tls.crt'),0600);
    $fpm="[global]\npid=$base/fpm.pid\nerror_log=$base/fpm.log\ndaemonize=no\n[finance]\nuser=www\ngroup=www\nlisten=$base/fpm.sock\nlisten.owner=www\nlisten.group=www\nlisten.mode=0600\npm=static\npm.max_children=2\nclear_env=yes\ncatch_workers_output=yes\nchdir=$root\nphp_admin_flag[display_errors]=off\nphp_admin_flag[log_errors]=on\nphp_admin_value[error_log]=$root/storage/logs/php.log\n";
    $write($base.'/fpm.conf',$fpm);$processes[]=proc_open(['/www/server/php/81/sbin/php-fpm','-F','-y',$base.'/fpm.conf'],[0=>['file','/dev/null','r'],1=>['file',$base.'/fpm-out.log','a'],2=>['file',$base.'/fpm-out.log','a']],$pipes);
    $template=file_get_contents($source.'/tools/install/portable/nginx.conf.example');$template=str_replace(['/absolute/path/finance','unix:/run/php/finance-web.sock;'],[$root,'unix:'.$base.'/fpm.sock;'],$template);
    $template=str_replace('include fastcgi_params;',"include /www/server/nginx/conf/fastcgi_params;\n fastcgi_param HTTPS on;",$template);
    $nginx="user www www;\nworker_processes 1;\npid $base/nginx.pid;\nerror_log $base/nginx.log;\ndaemon off;\nevents {worker_connections 64;}\nhttp {lua_package_path \"/www/server/nginx/lib/lua/?.lua;;\";include /www/server/nginx/conf/mime.types;access_log off;client_body_temp_path $base/body;fastcgi_temp_path $base/fastcgi;server {listen 127.0.0.1:$port ssl;server_name 127.0.0.1;ssl_certificate $base/tls.crt;ssl_certificate_key $base/tls.key;\n$template\n}}\n";
    $write($base.'/nginx.conf',$nginx);$processes[]=proc_open(['/www/server/nginx/sbin/nginx','-p',$base,'-c',$base.'/nginx.conf'],[0=>['file','/dev/null','r'],1=>['file',$base.'/nginx-out.log','a'],2=>['file',$base.'/nginx-out.log','a']],$pipes);
    for($n=0;$n<100;$n++){usleep(100000);$r=$http('/setup');if($r['status']===200)break;}
    $check($r['status']===200&&str_contains($r['body'],'Pasang dan aktifkan'),'real HTTPS Indonesian setup served as non-root web account');
    $check($http('/pos')['status']===423,'extract/import without activation cannot open business route');
    foreach(['/private/agent/agent.json','/config/customer.json','/storage/setup/browser.json','/installer/layout.json','/%2e%2e/private/delivery/credentials.json','/tools/install/portable/finance_setup.php']as$path){$r=$http($path);$check(in_array($r['status'],[400,403,404],true),'HTTP protected '.$path);}
    $r=$http('/setup',['action'=>'status','secret'=>str_repeat('x',64)]);$check($r['status']===400&&$r['json']['code']==='SETUP_REQUEST_UNAUTHORIZED','uninvited browser cannot read installation progress');
    $worker('tick');
    $probeId=bin2hex(random_bytes(16));$installId=bin2hex(random_bytes(16));
    $input=['action'=>'install','id'=>$installId,'probe_id'=>$probeId,'confirmed'=>true,'secret'=>$secret,'config'=>['database'=>$fixture['database'],'base_url'=>'https://127.0.0.1:'.$port.'/'],'owner'=>$fixture['owner']];
    $r=$http('/setup',['action'=>'probe','id'=>$probeId,'secret'=>$secret,'config'=>$input['config']]);$check($r['status']===200,'UI probe queued');
    $worker('tick');$r=$http('/setup',['action'=>'status','secret'=>$secret,'id'=>$probeId]);$check($r['json']['command']['ok']===true,'database probe through web and companion succeeds');
    $r=$http('/setup',$input);$check($r['status']===200&&$r['json']['phase']==='QUEUED','authorized browser seals database/admin credentials into bounded inbox');
    $sealed=file_get_contents($root.'/storage/inbox/command-'.$installId.'.json');$check(!str_contains($sealed,$dbPassword)&&!str_contains($sealed,$fixture['owner']['password']),'web queue contains no plaintext passwords');
    $r=$http('/setup',$input);$check($r['status']===200&&count(glob($root.'/storage/inbox/command-*.json'))===1,'duplicate setup submission is idempotent');
    $denied=$fixture;$denied['quota_denied']=true;$json($base.'/fixture.json',$denied);
    $worker('tick');$r=$http('/setup',['action'=>'status','secret'=>$secret,'id'=>$installId]);$check($r['json']['command']['code']==='INSTANCE_LIMIT_EXCEEDED','Control quota rejection blocks installer without granting activation');
    $check(!is_file($root.'/private/database.json')&&(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="portable_customer"')->fetchColumn()===0,'quota rejection leaves new database empty, no SQL started');
    $identityBefore=json_decode(file_get_contents($agentFile),true)['identity'];
    $credential['activation_code']='nla_'.bin2hex(random_bytes(20));$json($dir.'/credentials.json',$credential);
    $permit['permit_id']='00000000-0000-4000-8000-000000000022';$permit['credentials_sha256']=hash_file('sha256',$dir.'/credentials.json');$signPermit($permit);
    $json($base.'/fixture.json',$fixture);$r=$worker('prepare');
    $check($r['ok']&&json_decode(file_get_contents($agentFile),true)['identity']===$identityBefore,'new Control permission after quota review keeps installation identity and submission');
    $r=$worker('run');
    if(!$r['ok']){
        $probe=$http('/login');echo 'FIXTURE login status='.$probe['status'].' response='.substr(strip_tags((string)$probe['body']),0,700)."\n";echo 'FIXTURE guard='.json_encode($worker('guard'))."\n";
        $log=is_file($root.'/storage/logs/php.log')?file_get_contents($root.'/storage/logs/php.log'):'';
        $log=str_replace([$dbPassword,$fixture['owner']['password'],$secret,$monitor,base64_encode($sk)],'[redacted]',$log);
        echo 'FIXTURE PHP diagnostic '.substr($log,-2500)."\n";
    }
    $check($r['ok']&&$r['result']['phase']==='COMPLETE','real baseline/migrations/owner + signed synthetic activation + HTTPS login + receipt complete '.json_encode($r));
    $check((int)$pdo->query('SELECT COUNT(*) FROM portable_customer.sys_schema_migration')->fetchColumn()===26,'only 26 registered clean_install migrations applied');
    $check($http('/login')['status']===200,'application web bootstrap reads same local config without custom FPM environment');
    $check($worker('config')['ok'],'CLI reads same customer config');
    $login=curl_init('https://127.0.0.1:'.$port.'/auth/do_login');$location='';
    curl_setopt_array($login,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_TIMEOUT=>20,CURLOPT_POST=>true,
        CURLOPT_COOKIEJAR=>$base.'/login-cookies',CURLOPT_POSTFIELDS=>http_build_query(['identifier'=>$fixture['owner']['username'],'password'=>$fixture['owner']['password']]),
        CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$location):int{if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);}]);
    curl_exec($login);$loginStatus=(int)curl_getinfo($login,CURLINFO_RESPONSE_CODE);curl_setopt($login,CURLOPT_COOKIELIST,'FLUSH');curl_close($login);unset($login);
    $check(in_array($loginStatus,[302,303],true)&&$location!==''&&!str_ends_with(rtrim($location,'/'),'/login'),'first administrator really authenticates through application login');
    $r=$http('/setup',$input);$check($r['status']===400&&$r['json']['code']==='SETUP_ALREADY_COMPLETE','successful setup locked against reuse');
    $before=hash_file('sha256',$root.'/private/database.json');$check($worker('run')['ok']&&hash_file('sha256',$root.'/private/database.json')===$before,'repeat scheduled run does not replay SQL');
    $r=$worker('sync');$check($r['ok']&&$r['result']['heartbeat']==='ACKNOWLEDGED','separate signed license sync and HMAC heartbeat with URL-derived hostname');
    if(in_array('--feature-boundary',$argv,true)) require __DIR__.'/feature_boundary_http_cases.php';
    if(in_array('--update',$argv,true)) require __DIR__.'/customer_update_integration_cases.php';
    $config=file_get_contents($root.'/config/customer.json');$write($root.'/config/customer.json','{broken',0640);$check($http('/login')['status']===503,'broken local JSON blocks runtime without fallback');$write($root.'/config/customer.json',$config,0640);
    chmod($root.'/config/customer.json',0666);$check($http('/login')['status']===503,'unsafe local permissions block runtime');chmod($root.'/config/customer.json',0640);
    rename($root.'/storage/customer-installation.json',$base.'/context.saved');$check($http('/pos')['status']===423,'missing context cannot become legacy');rename($base.'/context.saved',$root.'/storage/customer-installation.json');
    $cache=file_get_contents($root.'/storage/license/runtime.json');$v=json_decode($cache,true);$v['envelope']['signature_base64']=base64_encode(random_bytes(64));$json($root.'/storage/license/runtime.json',$v,0640);$check($http('/pos')['status']===423,'invalid license signature blocks business route');$write($root.'/storage/license/runtime.json',$cache,0640);
    $core=file_get_contents($root.'/application/config/database.php');$write($root.'/application/config/database.php',$core."\n",0644);$check($http('/pos')['status']===423,'core change rejected even with valid activated cache');$write($root.'/application/config/database.php',$core,0644);
    $check($worker('verify')['ok'],'full immutable inventory still valid after runtime installation');
    $context=file_get_contents($root.'/storage/customer-installation.json');$bad=json_decode($context,true);$bad['machine_fingerprint_sha256']=str_repeat('b',64);$json($root.'/storage/customer-installation.json',$bad,0640);
    $check($http('/pos')['status']===423,'copied installation with wrong host fingerprint cannot open business route');$write($root.'/storage/customer-installation.json',$context,0640);
    rename($root.'/config/customer.json',$base.'/config.saved');symlink($base.'/config.saved',$root.'/config/customer.json');
    $check($http('/login')['status']===503,'customer config symlink rejected in actual web');unlink($root.'/config/customer.json');rename($base.'/config.saved',$root.'/config/customer.json');
    $webRead=$run(['/usr/sbin/runuser','-u','www','--',PHP_BINARY,'-r','exit(is_readable($argv[1])?1:0);',$agentFile]);$check($webRead['code']===0,'web account cannot read private agent key');
    $webWrite=$run(['/usr/sbin/runuser','-u','www','--',PHP_BINARY,'-r','exit(is_writable($argv[1])?1:0);',$root.'/storage/license/runtime.json']);$check($webWrite['code']===0,'web account cannot replace signed license cache');
    // Real process exit at a persisted SQL boundary, in a SECOND newly created disposable DB.
    $pdo->exec('CREATE DATABASE portable_resume CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$pdo->exec("GRANT ALL ON portable_resume.* TO 'portable_user'@'localhost'");
    rename($root.'/private/database.json',$base.'/original-journal.json');
    $resume=$fixture;$resume['database']['name']='portable_resume';$json($base.'/fixture.json',$resume);
    $r=$worker('interrupt');$check($r['ok']&&$r['result']==='PROCESS_EXIT_AT_DURABLE_CHECKPOINT','installer process really exits after durable baseline checkpoint');
    $state=json_decode(file_get_contents($root.'/private/database.json'),true);$check($state['phase']==='READY'&&count($state['completed'])===1,'interruption retains completed baseline evidence');
    $r=$worker('db');$check($r['ok']&&$r['result']['status']==='ok','new process resumes registered migrations and first owner without baseline replay');
    $ledger=$pdo->query('SELECT migration_id,checksum_sha256,applied_at FROM portable_resume.sys_schema_migration ORDER BY migration_id')->fetchAll(PDO::FETCH_ASSOC);
    $state=json_decode(file_get_contents($root.'/private/database.json'),true);$state['phase']='RUNNING';$json($root.'/private/database.json',$state);$journalHash=hash_file('sha256',$root.'/private/database.json');
    $r=$worker('db');$check(!$r['ok']&&$r['code']==='DATABASE_PARTIAL_REVIEW_REQUIRED','uncertain in-flight DDL state stops for review instead of replay');
    $check(hash_file('sha256',$root.'/private/database.json')===$journalHash&&$ledger===$pdo->query('SELECT migration_id,checksum_sha256,applied_at FROM portable_resume.sys_schema_migration ORDER BY migration_id')->fetchAll(PDO::FETCH_ASSOC),'rejected retry leaves journal and migration ledger unchanged');
    rename($root.'/private/database.json',$base.'/resume-journal.json');rename($base.'/original-journal.json',$root.'/private/database.json');$json($base.'/fixture.json',$fixture);
    echo json_encode(['status'=>'PASS','checks'=>$checks,'database_disposable'=>true,'php_fpm_custom_environment'=>false,'windows'=>'NOT_RUN','control'=>'SYNTHETIC_NOT_LIVE','published'=>false])."\n";
}finally{
    foreach(array_reverse($processes)as$proc)if(is_resource($proc)){proc_terminate($proc,15);proc_close($proc);}
    // Retain only failure diagnostics on request? No: no secrets/dumps left behind by this fixture.
    $remove($base);
}
