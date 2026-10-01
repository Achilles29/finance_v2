<?php
declare(strict_types=1);
/** Real disposable DB + HTTPS nginx/FPM. Control responses are SYNTHETIC, not publish/activation evidence. */
if(PHP_SAPI!=='cli'||PHP_OS_FAMILY!=='Linux'||posix_geteuid()!==0||!in_array('--disposable',$argv,true))throw new RuntimeException('DISPOSABLE_LINUX_ROOT_HARNESS_REQUIRED');
$source=dirname(__DIR__,2);require '/www/wwwroot/control/application/libraries/Finance_setup_packet.php';
require $source.'/tools/release/ControlReleaseBridge.php';
require $source.'/tools/install/PrivateDeployment.php';
$base='/var/lib/finance-guided-'.bin2hex(random_bytes(8));$root=$base.'/finance';$processes=[];$checks=0;
$owner=posix_getpwnam('namua-build')['uid'];$web=posix_getpwnam('www')['uid'];$group=posix_getgrnam('namua-build')['gid'];
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name."\n";$checks++;};
$write=static function(string $path,string $data,int $mode=0640)use($owner,$group):void{if(!is_dir(dirname($path)))mkdir(dirname($path),0750,true);file_put_contents($path,$data);chown($path,$owner);chgrp($path,$group);chmod($path,$mode);clearstatcache();};
$json=static function(string $path,array $v,int $mode=0600)use($write):void{$write($path,json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$mode);};
$run=static function(array $command):array{return financeArtifactSignatureRun($command);};
$http=static function(string $path,?array $body=null)use(&$port,$base):array{$c=curl_init('https://127.0.0.1:'.$port.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_TIMEOUT=>20]);if($body!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);$b=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);$mime=curl_getinfo($c,CURLINFO_CONTENT_TYPE);curl_close($c);return ['mime'=>$mime,'status'=>$status,'body'=>$b,'json'=>is_string($b)?json_decode($b,true):null];};
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
    $inspection=ControlReleaseBridge::inspect($tar);$check(($inspection['distribution_profile_version']??null)===9,'independent TAR validator accepts mapped durable profile v9');
    // All evidence stays inside one parent folder; trust is a generated test-only issuer.
    $kp=sodium_crypto_sign_keypair();$sk=sodium_crypto_sign_secretkey($kp);$pk=sodium_crypto_sign_publickey($kp);
    $trust=['schema'=>1,'product_code'=>'NAMUA_FINANCE','algorithm'=>'Ed25519','status'=>'ACTIVE','key_id'=>'00000000-0000-4000-8000-000000000011','public_key_base64'=>base64_encode($pk),'public_key_sha256'=>hash('sha256',$pk)];
    $wire=['schema'=>1,'context'=>'NAMUA_RELEASE_MANIFEST_V1','product_code'=>'NAMUA_FINANCE','release_public_id'=>'00000000-0000-4000-8000-000000000012',
        'version'=>'0.1.0-alpha.21','source_commit'=>str_repeat('a',40),'filename'=>'package.tar','media_type'=>'application/x-tar','size_bytes'=>filesize($tar),'sha256'=>hash_file('sha256',$tar),
        'source_manifest_sha256'=>hash_file('sha256',$root.'/app-manifest.json'),'customer_runtime_guard'=>'FINANCE_CUSTOMER_SERVER_V1','distribution_profile'=>'CUSTOMER_CLEAN','distribution_profile_version'=>9,
        'customer_content_audit'=>['status'=>'PASS','profile_sha256'=>$profile->digest(),'artifact_sha256'=>hash_file('sha256',$tar),'source_manifest_sha256'=>hash_file('sha256',$root.'/RELEASE-MANIFEST.json')],
        'packaging'=>['profile_code'=>'CUSTOMER_CLEAN','rules_sha256'=>$profile->digest(),'audience'=>'CUSTOMER','sample_data'=>'NONE'],'contains_customer_data'=>false,'contains_secrets'=>false,'verification'=>[]];
    foreach(ControlReleaseBridge::BUILD_GATES as $g)$wire['verification'][$g]=['status'=>'PASS','evidence_sha256'=>hash('sha256','FIXTURE_NOT_RELEASE_'.$g)];
    $raw=json_encode($wire,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $dir=$root.'/private/delivery';$write($dir.'/release.json',$raw,0600);$write($dir.'/package.tar',file_get_contents($tar),0600);
    $json($dir.'/release-trust.json',$trust);$json($dir.'/release.sig.json',ControlReleaseBridge::sign($raw,'package.release.json',$trust+['secret_key_base64'=>base64_encode($sk)]));
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$controlPort=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $secret=bin2hex(random_bytes(32));$monitor=bin2hex(random_bytes(32));
    $credential=['instance_id'=>'portable-fixture','environment'=>'STAGING','control_origin'=>'https://127.0.0.1:'.$controlPort,'activation_code'=>'nla_'.bin2hex(random_bytes(20)),
        'license_trust'=>$trust+['purpose'=>'NAMUA_LICENSE_SIGNING'],'monitoring'=>['key_id'=>'00000000-0000-4000-8000-000000000013','secret'=>$monitor]];
    $json($dir.'/credentials.json',$credential);
    $permit=['purpose'=>'NAMUA_FINANCE_SETUP_V1','product_code'=>'NAMUA_FINANCE','permit_id'=>'00000000-0000-4000-8000-000000000014','instance_id'=>'portable-fixture',
        'deployment_id'=>'00000000-0000-4000-8000-000000000015','plan_sha256'=>hash('sha256','fixture-plan'),'release_public_id'=>$wire['release_public_id'],'source_commit'=>$wire['source_commit'],
        'release_manifest_sha256'=>hash('sha256',$raw),'artifact_sha256'=>$wire['sha256'],'profile_sha256'=>$profile->digest(),'profile_version'=>9,'environment'=>'STAGING',
        'credentials_sha256'=>hash_file('sha256',$dir.'/credentials.json'),'setup_secret_sha256'=>hash('sha256',$secret),'issued_at'=>time()-86400*45,'expires_at'=>null,'permission_policy'=>'UNTIL_USED_OR_REVOKED'];
    $signPermit=static function(array $value)use($sk,$trust,$dir,$json):void{$raw=json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$json($dir.'/permit.json',['key_id'=>$trust['key_id'],'payload_base64'=>base64_encode($raw),'signature_base64'=>base64_encode(sodium_crypto_sign_detached("NAMUA_FINANCE_SETUP_V1\n".hash('sha256',$raw),$sk))]);};$signPermit($permit);
    $fixture=['root'=>$root,'issuer'=>base64_encode($sk),'trust'=>$trust,'issued'=>time()-5,'monitoring_secret'=>$monitor];mkdir($base.'/mock',0700);chown($base.'/mock',$owner);chgrp($base.'/mock',$group);$json($base.'/mock/fixture.json',$fixture);

    // Exercise Control's actual pure ZIP bundler, without writing Control source/database.
    $inventory=$entries;$inventory[]=['path'=>'RELEASE-MANIFEST.json','size'=>filesize($root.'/RELEASE-MANIFEST.json'),'sha256'=>hash_file('sha256',$root.'/RELEASE-MANIFEST.json')];
    $delivery=[];foreach(['package.tar','release.json','release.sig.json','release-trust.json','permit.json','credentials.json']as$n)$delivery[$n]=$dir.'/'.$n;
    $zipFile=$base.'/customer.zip';$bundle=Finance_setup_packet::bundle($zipFile,$tar,$inventory,$delivery,['KODE-SETUP.txt'=>$secret,"MULAI-DI-SINI.html"=>'<p>Ikuti docs/customer_single_folder.md</p>']);
    $check($bundle['files']===count($inventory)+8,'actual Control bundle produces complete one-folder ZIP');
    rename($root,$base.'/build-source');mkdir($root,0750);chown($root,$owner);chgrp($root,$group);chown($zipFile,$owner);chmod($zipFile,0600);

    mkdir($base.'/extracted',0750);chown($base.'/extracted',$owner);chgrp($base.'/extracted',$group);
    $r=$run(['/usr/sbin/runuser','-u','namua-build','--','unzip','-q',$zipFile,'-d',$base.'/extracted']);$check($r['code']===0,'customer ZIP really extracted');
    rmdir($root);rename($base.'/extracted/finance',$root);chmod($root,0750);$dir=$root.'/private/delivery';
    // Root-owned isolated spool: never touches host crontabs or adds users.
    mkdir($base.'/cron',0700);
    $processes[]=proc_open(['/usr/bin/busybox','crond','-f','-l','5','-c',$base.'/cron'],[0=>['file','/dev/null','r'],1=>['file',$base.'/cron.log','a'],2=>['file',$base.'/cron.log','a']],$pipes);
    if(!in_array('--render-only',$argv,true)) {
    // Isolated daemon with private admin socket and ephemeral loopback TCP for actual UI fields.
    $mysql='/www/server/mysql';$dbdir=$base.'/db';mkdir($dbdir,0750);chgrp($dbdir,$group);
    PrivateDeployment::run([$mysql.'/scripts/mariadb-install-db','--no-defaults','--basedir='.$mysql,'--datadir='.$dbdir.'/data','--auth-root-authentication-method=normal','--skip-test-db'],$base.'/mysql-init.log',180);
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$dbPort=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $socket=$dbdir.'/mysql.sock';$processes[]=proc_open([$mysql.'/bin/mariadbd','--no-defaults','--user=root','--basedir='.$mysql,'--datadir='.$dbdir.'/data','--socket='.$socket,'--pid-file='.$dbdir.'/pid','--log-error='.$dbdir.'/error.log','--bind-address=127.0.0.1','--port='.$dbPort,'--skip-log-bin','--innodb-buffer-pool-size=64M'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
    $pdo=null;for($n=0;$n<150;$n++){try{$pdo=new PDO('mysql:unix_socket='.$socket.';dbname=mysql','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);break;}catch(Throwable $e){usleep(100000);}}
    $check($pdo instanceof PDO,'new disposable MariaDB socket online; live database not accessed');
    $dbPassword=bin2hex(random_bytes(24));$pdo->exec('CREATE DATABASE portable_customer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("CREATE USER 'portable_user'@'localhost' IDENTIFIED BY ".$pdo->quote($dbPassword));$pdo->exec("GRANT ALL ON portable_customer.* TO 'portable_user'@'localhost'");
    $pdo->exec("GRANT ALL ON does_not_exist.* TO 'portable_user'@'localhost'");
    $fixture['database']=['host'=>'127.0.0.1','port'=>$dbPort,'socket'=>'','name'=>'portable_customer','user'=>'portable_user','password'=>$dbPassword];
    $ownerUsername='owner_'.bin2hex(random_bytes(6));
    $fixture['owner']=['username'=>$ownerUsername,'email'=>'owner@example.invalid','password'=>bin2hex(random_bytes(14)).'Z9!'];$fixture['release_hash']=hash('sha256',$raw);$json($base.'/mock/fixture.json',$fixture);
    }
    // HTTPS + actual FPM worker www, no FINANCE_* environment or CI_ENV override.
    $listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);$port=(int)substr(strrchr(stream_socket_get_name($listener,false),':'),1);fclose($listener);
    $run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',$base.'/tls.key','-out',$base.'/tls.crt','-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1']);
    $write($root.'/private/ca.pem',file_get_contents($base.'/tls.crt'),0600);
    $fpm="[global]\npid=$base/fpm.pid\nerror_log=$base/fpm.log\ndaemonize=no\n[finance]\nuser=www\ngroup=namua-build\nlisten=$base/fpm.sock\nlisten.owner=www\nlisten.group=namua-build\nlisten.mode=0660\npm=static\npm.max_children=2\nclear_env=yes\ncatch_workers_output=yes\nchdir=$root\nphp_admin_flag[display_errors]=off\nphp_admin_flag[log_errors]=on\nphp_admin_value[error_log]=$root/storage/logs/php.log\n";
    $fpm.="\n[synthetic-control]\nuser=namua-build\ngroup=namua-build\nlisten=$base/control.sock\nlisten.owner=www\nlisten.group=namua-build\nlisten.mode=0660\npm=static\npm.max_children=2\nclear_env=yes\n";
    $write($base.'/fpm.conf',$fpm);$processes[]=proc_open(['/www/server/php/81/sbin/php-fpm','-F','-y',$base.'/fpm.conf'],[0=>['file','/dev/null','r'],1=>['file',$base.'/fpm-out.log','a'],2=>['file',$base.'/fpm-out.log','a']],$pipes);
    $template=file_get_contents($source.'/tools/install/portable/nginx.conf.example');$template=str_replace(['/absolute/path/finance','unix:/run/php/finance-web.sock;'],[$root,'unix:'.$base.'/fpm.sock;'],$template);
    $template=str_replace('include fastcgi_params;',"include /www/server/nginx/conf/fastcgi_params;\n fastcgi_param HTTPS on;",$template);
    $nginx="user www namua-build;\nworker_processes 1;\npid $base/nginx.pid;\nerror_log $base/nginx.log;\ndaemon off;\nevents {worker_connections 64;}\nhttp {lua_package_path \"/www/server/nginx/lib/lua/?.lua;;\";include /www/server/nginx/conf/mime.types;access_log off;client_body_temp_path $base/body;fastcgi_temp_path $base/fastcgi;server {listen 127.0.0.1:$port ssl;server_name 127.0.0.1;ssl_certificate $base/tls.crt;ssl_certificate_key $base/tls.key;\n$template\n}}\n";
    $extra="server {listen 127.0.0.1:$controlPort ssl;server_name 127.0.0.1;ssl_certificate $base/tls.crt;ssl_certificate_key $base/tls.key;location / {include /www/server/nginx/conf/fastcgi_params;fastcgi_param SCRIPT_FILENAME $source/tools/tests/customer_guided_control_fixture.php;fastcgi_param FINANCE_TEST_FIXTURE $base/mock/fixture.json;fastcgi_pass unix:$base/control.sock;}}";
    $nginx=substr(rtrim($nginx),0,-1).$extra."}\n";
    $write($base.'/nginx.conf',$nginx);$processes[]=proc_open(['/www/server/nginx/sbin/nginx','-p',$base,'-c',$base.'/nginx.conf'],[0=>['file','/dev/null','r'],1=>['file',$base.'/nginx-out.log','a'],2=>['file',$base.'/nginx-out.log','a']],$pipes);
    for($n=0;$n<100;$n++){usleep(100000);$r=$http('/setup');if($r['status']===200)break;}
    $check($r['status']===200&&str_contains($r['body'],'Pasang dan aktifkan')&&str_starts_with($r['mime'],'text/html'),'real HTTPS Indonesian setup served as HTML by non-root web account: '.$r['mime']);
    $fixture['base_url']='https://127.0.0.1:'.$port.'/';$json($base.'/mock/fixture.json',$fixture);
    require $source.'/tools/install/portable/LinuxPreparation.php';
    $r=LinuxPreparation::command(['node',$source.'/tools/tests/customer_guided_browser.cjs',$base.'/mock/fixture.json','--page-only'],'',90);
    $check($r['code']===0,'Chrome renders actual setup before preparation '.($r['code']===0?'OK':$r['err']));
    if(in_array('--render-only',$argv,true)){echo "RENDER_ONLY_PASS_NOT_INSTALL_ACCEPTANCE\n";return;}
    $prepArgs=['sh',$root.'/tools/install/portable/prepare.sh','--web-user=www','--web-group=namua-build','--installer-user=namua-build','--url=https://127.0.0.1:'.$port.'/','--cron-binary=/usr/bin/busybox','--cron-spool='.$base.'/cron'];
    $r=LinuxPreparation::command($prepArgs,"BATAL\n");$check($r['code']!==0&&!is_file($root.'/private/agent/agent.json')&&!is_file($base.'/cron/namua-build'),'declining confirmation makes no identity or cron changes');
    $expired=$permit;$expired['issued_at']=time()-100;$expired['expires_at']=time()-1;$signPermit($expired);
    $r=LinuxPreparation::command($prepArgs,"SIAP\n");$check($r['code']!==0&&str_contains($r['err'],'SETUP_PERMISSION_BINDING_INVALID')&&!is_file($root.'/private/agent/agent.json'),'v8 permit with an injected expiry is rejected before preparation');$signPermit($permit);
    $wrong=$permit;$wrong['instance_id']='wrong-instance';$signPermit($wrong);
    $r=LinuxPreparation::command($prepArgs,"SIAP\n");$check($r['code']!==0&&str_contains($r['err'],'DELIVERY_CREDENTIAL_BINDING_INVALID'),'credential/permit binding mismatch rejected before preparation');$signPermit($permit);
    $savedCore=file_get_contents($root.'/application/config/database.php');$write($root.'/application/config/database.php',$savedCore."\n// test tamper\n");
    $r=LinuxPreparation::command($prepArgs,"SIAP\n");$check($r['code']!==0&&str_contains($r['err'],'PACKAGE_CORE_MODIFIED'),'changed package rejected before privileged mutations');$write($root.'/application/config/database.php',$savedCore);
    chmod($base,0770);
    $r=LinuxPreparation::command($prepArgs,"SIAP\n");$check($r['code']!==0&&str_contains($r['err'],'INSTALL_PARENT_UNSAFE'),'unsafe parent permissions rejected without broad chmod');chmod($base,0750);
    $r=LinuxPreparation::command($prepArgs,"SIAP\n",120);$check($r['code']===0,'ONE command verifies ZIP, prepares private state and proves real cron tick: '.($r['code']===0?'OK':$r['err']));
    $identityHash=hash_file('sha256',$root.'/private/agent/agent.json');$cronFirst=file_get_contents($base.'/cron/namua-build');
    $r=LinuxPreparation::command($prepArgs,"SIAP\n",120);$check($r['code']===0&&hash_file('sha256',$root.'/private/agent/agent.json')===$identityHash&&file_get_contents($base.'/cron/namua-build')===$cronFirst,'second one-command run preserves identity and exact three cron entries');
    $check(substr_count($cronFirst,'* * * * *')===3&&!is_file($root.'/private/database.json'),'only installer/license/heartbeat jobs, no SQL during preparation');
    $check($http('/pos')['status']===423,'extract/import without activation cannot open business route');
    foreach(['/private/agent/agent.json','/config/customer.json','/storage/setup/browser.json','/installer/layout.json','/%2e%2e/private/delivery/credentials.json','/tools/install/portable/finance_setup.php']as$path){$r=$http($path);$check(in_array($r['status'],[400,403,404],true),'HTTP protected '.$path);}
    $r=$http('/setup',['action'=>'status','secret'=>str_repeat('x',64)]);$check($r['status']===400&&$r['json']['code']==='SETUP_REQUEST_UNAUTHORIZED','uninvited browser cannot read installation progress');

    $probeId=bin2hex(random_bytes(16));$installId=bin2hex(random_bytes(16));
    $config=['database'=>$fixture['database'],'base_url'=>'https://127.0.0.1:'.$port.'/'];
    $await=static function(string $id,int $timeout=100)use($http,$secret):array{
        $start=time();do{$r=$http('/setup',['action'=>'status','secret'=>$secret,'id'=>$id]);if(isset($r['json']['command']['ok']))return $r['json'];usleep(500000);}while(time()-$start<$timeout);
        throw new RuntimeException('SCHEDULER_COMMAND_TIMEOUT');
    };
    // Queue negative probes together; scheduler consumes bounded batches without SQL.
    $ids=[];foreach(['wrong_password','missing_database','incomplete']as$case){
        $c=$config;if($case==='wrong_password')$c['database']['password']='wrong-fixture-password';
        elseif($case==='missing_database')$c['database']['name']='does_not_exist';else unset($c['database']['host']);
        $id=bin2hex(random_bytes(16));$ids[$case]=$id;
        $r=$http('/setup',['action'=>'probe','id'=>$id,'secret'=>$secret,'config'=>$c]);$check($r['status']===200,'probe queued: '.$case);
    }
    foreach($ids as$case=>$id){$s=$await($id);$expected=['wrong_password'=>'DATABASE_CREDENTIAL_REJECTED','missing_database'=>'DATABASE_NOT_FOUND','incomplete'=>'CUSTOMER_CONFIG_INCOMPLETE'][$case];$check($s['command']['code']===$expected,'clear UI probe failure: '.$case.' '.($s['command']['code']??''));}
    $pdo->exec('CREATE TABLE portable_customer.preexisting (id INT)');
    $id=bin2hex(random_bytes(16));$http('/setup',['action'=>'probe','id'=>$id,'secret'=>$secret,'config'=>$config]);$s=$await($id);
    $check($s['command']['code']==='DATABASE_NOT_EMPTY','UI refuses nonempty database');
    $check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema="portable_customer"')->fetchColumn()===1,'no existing table deleted by installer');
    $pdo->exec('DROP TABLE portable_customer.preexisting'); // Harness-created table in isolated daemon only.
    $fixture['base_url']=$config['base_url'];$json($base.'/mock/fixture.json',$fixture);
    $r=LinuxPreparation::command(['node',$source.'/tools/tests/customer_guided_browser.cjs',$base.'/mock/fixture.json'],'',240);
    $browser=json_decode(trim($r['out']),true);
    $check($r['code']===0&&($browser['status']??'')==='PASS','real Chrome wizard: code, DB probe, passwords, mobile layout, review and install '.($r['code']===0?'OK':$r['err']));
    $probeId=$browser['probe_id'];$installId=$browser['install_id'];
    $input=['action'=>'install','id'=>$installId,'probe_id'=>$probeId,'confirmed'=>true,'secret'=>$secret,'config'=>$config,'owner'=>$fixture['owner']];
    $r=$http('/setup',$input);$check($r['status']===200,'UI install accepted');
    $sealed=file_get_contents($root.'/storage/inbox/command-'.$installId.'.json');
    $check(!str_contains($sealed,$dbPassword)&&!str_contains($sealed,$fixture['owner']['password']),'queue contains only encrypted DB/admin credentials');
    $http('/setup',$input);$check(count(glob($root.'/storage/inbox/command-*.json'))===1,'repeat identical HTTP submission cannot duplicate job');
    $s=$await($installId,300);
    if(($s['command']['code']??'')!=='CONTROL_ACK_REQUIRED')echo 'DIAGNOSTIC '.json_encode($s)."\n";
    $check(($s['command']['code']??'')==='CONTROL_ACK_REQUIRED','synthetic Control loses receipt acknowledgement after real DB install');
    $journalHash=hash_file('sha256',$root.'/private/database.json');
    $start=time();do{$r=$http('/setup',['action'=>'status','secret'=>$secret,'id'=>$installId]);if(!empty($r['json']['closed']))break;usleep(500000);}while(time()-$start<100);
    $check(!empty($r['json']['closed']),'real scheduler resumes SAME attempt after lost acknowledgement');
    $check(hash_file('sha256',$root.'/private/database.json')===$journalHash,'resume does not replay completed SQL');
    $control=json_decode(file_get_contents($base.'/mock/control-state.json'),true);
    $check($control['requests']===1&&$control['receipts']===2,'one activation only and same receipt re-acknowledged');
    $check((int)$pdo->query('SELECT COUNT(*) FROM portable_customer.sys_schema_migration')->fetchColumn()===20,'only baseline and registered 20 clean-install migrations');
    $check($http('/login')['status']===200,'real application login via same local customer.json without FPM env');
    $login=curl_init('https://127.0.0.1:'.$port.'/auth/do_login');$location='';
    curl_setopt_array($login,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CAINFO=>$base.'/tls.crt',CURLOPT_TIMEOUT=>20,CURLOPT_POST=>true,
        CURLOPT_COOKIEJAR=>$base.'/login-cookies',CURLOPT_POSTFIELDS=>http_build_query(['identifier'=>$fixture['owner']['username'],'password'=>$fixture['owner']['password']]),
        CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$location):int{if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);}]);
    curl_exec($login);$loginStatus=(int)curl_getinfo($login,CURLINFO_RESPONSE_CODE);curl_close($login);
    $check(in_array($loginStatus,[302,303],true)&&$location!==''&&!str_ends_with(rtrim($location,'/'),'/login'),'first administrator really authenticates through application login');
    $r=$http('/setup',$input);$check($r['status']===400&&$r['json']['code']==='SETUP_ALREADY_COMPLETE','successful setup locked against reuse');

    $start=time();do{$control=json_decode(file_get_contents($base.'/mock/control-state.json'),true);if($control['heartbeats']>0&&$control['polls']>=3)break;usleep(500000);}while(time()-$start<100);
    $check($control['heartbeats']>0&&$control['polls']>=3,'separate scheduled license polling and signed heartbeat really reach HTTPS synthetic server');
    $agentFile=$root.'/private/agent/agent.json';
    $r=$run(['/usr/sbin/runuser','-u','www','-g','namua-build','--',PHP_BINARY,'-r','exit(is_readable($argv[1])?1:0);',$agentFile]);
    $check($r['code']===0,'web account cannot read agent credential');
    $r=$run(['/usr/sbin/runuser','-u','www','-g','namua-build','--',PHP_BINARY,'-r','exit(is_writable($argv[1])?1:0);',$root.'/storage/license/runtime.json']);
    $check($r['code']===0,'web account cannot modify license cache');
    $core=file_get_contents($root.'/application/config/database.php');$write($root.'/application/config/database.php',$core."\n// changed\n");
    $check($http('/pos')['status']===423,'modified signed core blocked');$write($root.'/application/config/database.php',$core);
    echo json_encode(['status'=>'PASS','checks'=>$checks,'zip_builder'=>'ACTUAL_CONTROL_CLASS_READ_ONLY','scheduler'=>'REAL_BUSYBOX_CROND_ISOLATED','control'=>'SYNTHETIC_HTTPS_NOT_LIVE','windows'=>'NOT_RUN','database_disposable'=>true,'published'=>false])."\n";
}finally{
    foreach(array_reverse($processes)as$proc)if(is_resource($proc)){proc_terminate($proc,15);proc_close($proc);}
    $remove($base);
}
