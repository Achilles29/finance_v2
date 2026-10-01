<?php
declare(strict_types=1);
// Explicit isolated test only. Never reads operational Finance tables or imports customer transactions.
require dirname(__DIR__).'/install/PrivateDeployment.php';
umask(0077);
$s=$argv[1]??'';$manifest=$argv[2]??'';$old=$argv[3]??null;
if(PHP_SAPI!=='cli'||posix_geteuid()!==0||preg_match('~\A/var/lib/finance-web-[0-9]{8}[.][A-Za-z0-9]{6}\z~D',$s)!==1||realpath($s)!==$s||is_file($s.'/private/config.json'))throw new RuntimeException('FRESH_DISPOSABLE_DIRECTORY_REQUIRED');
$account=posix_getpwnam('finance_c3_trial');if(!$account||$account['uid']===0)throw new RuntimeException('TEST_ACCOUNT_REQUIRED');
if(!isset($argv[4]))throw new RuntimeException('EXPLICIT_FIXTURE_LICENSE_PUBLIC_DIRECTORY_REQUIRED');
$licenseFiles=new LicenseAgentFiles($argv[4],dirname(__DIR__,2),(int)$account['gid'],false);
$licenseIdentity=$licenseFiles->read('identity.json',0640);
$licenseFiles->read('trust.json',0640);$licenseFiles->read('runtime.json',0640);
if(!is_string($licenseIdentity['instance_id']??null)||$licenseIdentity['instance_id']==='')throw new RuntimeException('FIXTURE_LICENSE_IDENTITY_REQUIRED');
chgrp($s,$account['gid']);chmod($s,0750);mkdir($s.'/private',0700);mkdir($s.'/fixture',0700);
$name='c3_finance_test_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(24));
$write=static function(string $p,string $bytes,int $mode=0600):void{if(file_exists($p)||is_link($p))throw new RuntimeException('FIXTURE_OUTPUT_EXISTS');$h=fopen($p,'xb');if(!$h||fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('FIXTURE_WRITE_FAILED');fclose($h);chmod($p,$mode);};
$panel=new PDO('sqlite:file:/www/server/panel/data/default.db?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$adminPassword=$panel->query('SELECT mysql_root FROM config LIMIT 1')->fetchColumn();unset($panel);
$admin=new PDO('mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4','root',$adminPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($adminPassword);
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$admin->exec('CREATE USER '.$admin->quote($name)."@'localhost' IDENTIFIED BY ".$admin->quote($password));$admin->exec('GRANT ALL ON `'.$name.'`.* TO '.$admin->quote($name)."@'localhost'");unset($admin);
$write($s.'/fixture/disposable-client.cnf',"[client]\nprotocol=socket\nsocket=/tmp/mysql.sock\nuser=".$name."\npassword=".$password."\n");
$write($s.'/fixture/disposable-database.name',$name."\n");
$settings=['FINANCE_DB_HOST'=>'localhost','FINANCE_DB_USER'=>$name,'FINANCE_DB_PASSWORD'=>$password,'FINANCE_DB_NAME'=>$name,'FINANCE_BASE_URL'=>'https://127.0.0.1:18443/','FINANCE_ENCRYPTION_KEY'=>bin2hex(random_bytes(32)),'FINANCE_SESSION_COOKIE'=>'finance_c3_instance_session'];unset($password);
$c=['private_dir'=>$s.'/private','runtime_dir'=>$s,'release_root'=>$s.'/app','signed_manifest'=>$manifest,'trust_file'=>'/var/lib/namua-control/release-signing/trusted/NAMUA_FINANCE.json','deployment_file'=>$s.'/deployment.json','defaults_extra_file'=>$s.'/fixture/disposable-client.cnf','database_name_file'=>$s.'/fixture/disposable-database.name','owner_file'=>$s.'/fixture/disposable-owner.json','php_fpm'=>'/www/server/php/81/sbin/php-fpm','nginx'=>'/www/server/nginx/sbin/nginx','user'=>'finance_c3_trial','group'=>'finance_c3_trial','port'=>18443,'tls_certificate'=>$s.'/tls.crt','tls_key'=>$s.'/tls.key','health_ca'=>$s.'/tls.crt','mime_types'=>'/www/server/nginx/conf/mime.types','lua_root'=>'/www/server/nginx/lib/lua','composer'=>'/usr/bin/composer','mode'=>'clean_install'];
$c['license_public_dir']=$argv[4];$c['instance_id']=$licenseIdentity['instance_id'];
if($old!==null){
    if(preg_match('~\A/var/lib/finance-web-[0-9]{8}[.][A-Za-z0-9]{6}\z~D',$old)!==1||realpath($old)!==$old)throw new RuntimeException('DISPOSABLE_SOURCE_REQUIRED');
    $previous=PrivateDeployment::read($old.'/private/config.json');$descriptor=PrivateDeployment::read($old.'/private/instance.json');
    $oldName=trim((string)file_get_contents($previous['database_name_file']));
    if(($descriptor['status']??'')!=='STOPPED'||preg_match('/\Ac3_finance_test_[a-f0-9]{12}\z/D',$oldName)!==1)throw new RuntimeException('QUIESCED_DISPOSABLE_SOURCE_REQUIRED');
    $backup=$s.'/private/before-upgrade.sql';
    // Restricted source account; --result-file avoids shell interpolation and stdout data exposure.
    PrivateDeployment::run(['/usr/bin/mysqldump','--defaults-extra-file='.$previous['defaults_extra_file'],'--single-transaction','--hex-blob','--skip-add-drop-table','--result-file='.$backup,$oldName],$s.'/private/backup.log',120);
    chmod($backup,0600);$c['backup_file']=$backup;$c['backup_sha256']=hash_file('sha256',$backup);$c['from_schema']=PrivateDeployment::read($old.'/private/result.json')['to_schema'];
    $p=proc_open(['/usr/bin/mysql','--defaults-extra-file='.$c['defaults_extra_file'],'--database='.$name],[0=>['file',$backup,'r'],1=>['file',$s.'/private/restore.log','a'],2=>['file',$s.'/private/restore.log','a']],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('FIXTURE_RESTORE_FAILED');
    $oldSettings=json_decode((string)file_get_contents($old.'/deployment.json'),true,32,JSON_THROW_ON_ERROR);$settings['FINANCE_ENCRYPTION_KEY']=$oldSettings['FINANCE_ENCRYPTION_KEY'];unset($oldSettings);
    $c['mode']='upgrade';$c['previous_config_file']=$old.'/private/config.json';copy($previous['owner_file'],$c['owner_file']);chmod($c['owner_file'],0600);
}else{
    $ownerName='trial.'.bin2hex(random_bytes(4));$ownerPassword='Aa1!'.bin2hex(random_bytes(20));
    $write($c['owner_file'],json_encode(['username'=>$ownerName,'email'=>$ownerName.'@example.invalid','password'=>$ownerPassword]));unset($ownerPassword);
}
foreach(['SESSION'=>'sessions','LOG'=>'logs','CACHE'=>'cache']as$key=>$dir)$settings['FINANCE_'.$key.'_PATH']=$s.'/'.$dir;
$write($s.'/deployment.json',json_encode($settings,JSON_THROW_ON_ERROR),0640);chgrp($s.'/deployment.json',$account['gid']);unset($settings);
PrivateDeployment::run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','3','-subj','/CN=Finance Instance Trial','-addext','subjectAltName=IP:127.0.0.1','-keyout',$s.'/tls.key','-out',$s.'/tls.crt'],$s.'/private/openssl.log');chmod($s.'/tls.key',0600);chmod($s.'/tls.crt',0644);
PrivateDeployment::write($s.'/private/config.json',$c);
echo json_encode(['status'=>'PREPARED','mode'=>$c['mode'],'database'=>$name,'config_file'=>$s.'/private/config.json','public_listener'=>false,'operational_data_read'=>false])."\n";
