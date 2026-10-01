<?php
declare(strict_types=1);
// Explicit test setup: new database + source-only Control copy, never an operational Control test.
require dirname(__DIR__).'/install/LinuxWebProfile.php';
umask(0077);
$s=$argv[1]??''; $control=$argv[2]??'';
if (PHP_SAPI!=='cli' || posix_geteuid()!==0 || preg_match('~\A/var/lib/finance-control-[0-9]{8}[.][A-Za-z0-9]{6}\z~D',$s)!==1
    || realpath($s)!==$s || realpath($control)!==$control || !is_file($control.'/application/controllers/Api_license_activations.php')
    || is_file($s.'/fixture.json')) throw new RuntimeException('FRESH_EXPLICIT_FIXTURE_REQUIRED');
$account=posix_getpwnam('finance_c3_trial'); if (!$account || $account['uid']===0) throw new RuntimeException('ISOLATED_ACCOUNT_REQUIRED');
$uid=$account['uid']; $gid=$account['gid']; chmod($s,0750); chgrp($s,$gid);
$write=static function(string $path,string $text,int $mode=0600,?int $group=null): void {
    if (file_exists($path)||is_link($path)) throw new RuntimeException('FIXTURE_OUTPUT_EXISTS');
    $h=fopen($path,'xb'); if (!$h || fwrite($h,$text)!==strlen($text)) throw new RuntimeException('FIXTURE_WRITE_FAILED');
    fflush($h); fclose($h); chmod($path,$mode); if ($group!==null) chgrp($path,$group);
};
$copyTree=static function(string $from,string $to) use (&$copyTree): void {
    if (is_link($from)) throw new RuntimeException('SOURCE_LINK_REJECTED');
    mkdir($to,0755); chmod($to,0755);
    foreach (scandir($from)?:[] as $name) {
        if ($name==='.'||$name==='..'||$name[0]==='.'||in_array($name,['cache','logs'],true)) continue;
        $src=$from.'/'.$name; $dst=$to.'/'.$name;
        if (is_link($src)) throw new RuntimeException('SOURCE_LINK_REJECTED');
        if (is_dir($src)) $copyTree($src,$dst);
        elseif (preg_match('/\.(php|html|json)$/D',$name)) { copy($src,$dst); chmod($dst,0644); }
    }
};
mkdir($s.'/app',0755); chmod($s.'/app',0755);
foreach (['application','system'] as $dir) $copyTree($control.'/'.$dir,$s.'/app/'.$dir);
copy($control.'/index.php',$s.'/app/index.php'); chmod($s.'/app/index.php',0644);
foreach (['logs','cache','sessions','tmp'] as $dir) { mkdir($s.'/'.$dir,0700); chown($s.'/'.$dir,$uid); chgrp($s.'/'.$dir,$gid); }
mkdir($s.'/keys',0700);
$panel=new PDO('sqlite:file:/www/server/panel/data/default.db?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$password=$panel->query('SELECT mysql_root FROM config LIMIT 1')->fetchColumn(); unset($panel);
$admin=new PDO('mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4','root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); unset($password);
$name='finance_ctl_test_'.bin2hex(random_bytes(6)); $password=bin2hex(random_bytes(24));
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$admin->exec('CREATE USER '.$admin->quote($name)."@'localhost' IDENTIFIED BY ".$admin->quote($password));
$admin->exec('GRANT ALL ON `'.$name.'`.* TO '.$admin->quote($name)."@'localhost'"); unset($admin);
$fixture=['database'=>$name,'user'=>$name,'password'=>$password,'host'=>'127.0.0.1','key_root'=>$s.'/keys'];
$write($s.'/issuer.json',json_encode($fixture,JSON_THROW_ON_ERROR));
$db=new PDO('mysql:host=127.0.0.1;dbname='.$name.';charset=utf8mb4',$name,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$migrations=glob($control.'/database/migrations/*.sql')?:[]; sort($migrations,SORT_STRING);
foreach ($migrations as $file) foreach (preg_split('/;[\t ]*(?:\r?\n|$)/',(string)file_get_contents($file))?:[] as $sql) if (trim($sql)!=='') $db->exec($sql);
$uuid=static function(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); };
$applicationKey=bin2hex(random_bytes(32));
$fixture['application_key']=$applicationKey; $fixture['base_url']='https://127.0.0.1:18444/';
$fixture['owner_password']=bin2hex(random_bytes(16));
$stmt=$db->prepare("INSERT INTO users (public_id,username,email,full_name,password_hash,is_active,must_change_password) VALUES (?,?,?,?,?,1,0)");
foreach (['owner','reviewer'] as $who) {
    $stmt->execute([$uuid(),'practice.'.$who,'practice.'.$who.'@example.invalid','Practice '.$who,password_hash($fixture['owner_password'],PASSWORD_DEFAULT)]);
    $fixture[$who.'_id']=(int)$db->lastInsertId();
    $role=$who==='owner'?'OWNER':'RELEASE_MANAGER';
    $db->exec('INSERT INTO user_roles (user_id,role_id) SELECT '.$fixture[$who.'_id']." ,id FROM roles WHERE code=".$db->quote($role));
}
$db->exec("INSERT INTO products (code,name,description,catalog_source,is_active) VALUES ('NAMUA_FINANCE','Finance Practice','Isolated Finance contract fixture','MANUAL',1)"); $fixture['product_id']=(int)$db->lastInsertId();
$db->exec("INSERT INTO editions (product_id,code,name,sort_order,is_active) VALUES (".$fixture['product_id'].",'STARTER_POS','Starter POS Practice',1,1)"); $fixture['edition_id']=(int)$db->lastInsertId();
$db->exec("INSERT INTO product_license_policies (product_id,lease_days,grace_days,license_metric,guard_required,agent_min_version,supported_platforms_json,rights_model,default_maintenance_days) VALUES (".$fixture['product_id'].",30,30,'SERVER_INSTANCE',1,'0.1.0','[\"linux-amd64\"]','PERPETUAL',365)");
$q=$db->prepare("INSERT INTO features (product_id,code,name,value_type,default_value,is_active) VALUES (?,? ,?,'BOOLEAN','0',1)");
foreach (['POS_CORE'=>true,'PAYROLL'=>false] as $code=>$value) { $q->execute([$fixture['product_id'],$code,$code]);$id=(int)$db->lastInsertId();$db->exec('INSERT INTO edition_features (edition_id,feature_id,configured_value) VALUES ('.$fixture['edition_id'].','.$id.','.$db->quote($value?'1':'0').')'); }
$q=$db->prepare("INSERT INTO customers (public_id,code,legal_name,display_name,status,created_by,updated_by) VALUES (?,'PRACTICE-FINANCE','Practice Only','Practice Only','ACTIVE',?,?)");
$q->execute([$uuid(),$fixture['owner_id'],$fixture['owner_id']]);$fixture['customer_id']=(int)$db->lastInsertId();
$q=$db->prepare("INSERT INTO instances (instance_id,customer_id,product_id,edition_id,environment,primary_domain,timezone,status,lifecycle_status,created_by,updated_by) VALUES (?,?,?,?,'DEMO',?,'Asia/Jakarta','ONBOARDING','ONBOARDING',?,?)");
foreach (['primary','recovery'] as $type) { $instance='practice-'.$type.'-'.substr($name,-12);$q->execute([$instance,$fixture['customer_id'],$fixture['product_id'],$fixture['edition_id'],$instance.'.example.invalid',$fixture['owner_id'],$fixture['owner_id']]); $fixture[$type]=['instance_id'=>$instance,'record_id'=>(int)$db->lastInsertId()]; }
$q=$db->prepare("INSERT INTO subscriptions (public_id,subscription_code,customer_id,product_id,edition_id,status,max_instances,user_limit,starts_at,rights_model,perpetual_granted_at,maintenance_ends_at,created_by,updated_by) VALUES (?,'PRACTICE-SUB',?,?,?,'ACTIVE',2,NULL,NOW(),'PERPETUAL',NOW(),DATE_SUB(NOW(),INTERVAL 1 DAY),?,?)");
$q->execute([$uuid(),$fixture['customer_id'],$fixture['product_id'],$fixture['edition_id'],$fixture['owner_id'],$fixture['owner_id']]);$fixture['subscription_id']=(int)$db->lastInsertId();
$q=$db->prepare('INSERT INTO license_activation_codes (public_id,subscription_id,instance_id,code_hash,expires_at,created_by) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR),?)');
foreach (['primary','recovery'] as $type) { $code='nla_'.rtrim(strtr(base64_encode(random_bytes(30)),'+/','-_'),'=');$q->execute([$uuid(),$fixture['subscription_id'],$fixture[$type]['record_id'],hash('sha256',$code),$fixture['owner_id']]);$fixture[$type]['activation_code']=$code; }
$pair=sodium_crypto_sign_keypair();$public=sodium_crypto_sign_publickey($pair);
$trust=['schema'=>1,'purpose'=>'NAMUA_LICENSE_SIGNING','product_code'=>'NAMUA_FINANCE','key_id'=>$uuid(),'algorithm'=>'Ed25519','status'=>'ACTIVE','public_key_base64'=>base64_encode($public),'public_key_sha256'=>hash('sha256',$public)];
$write($s.'/keys/NAMUA_FINANCE.json',json_encode($trust+['secret_key_base64'=>base64_encode(sodium_crypto_sign_secretkey($pair))]));sodium_memzero($pair);
$write($s.'/trust.json',json_encode($trust));
$write($s.'/fixture.json',json_encode($fixture,JSON_THROW_ON_ERROR));
$runtime=['database'=>$name,'user'=>$name,'password'=>$password,'application_key'=>$applicationKey,'base_url'=>$fixture['base_url']];
$write($s.'/runtime.json',json_encode($runtime,JSON_THROW_ON_ERROR),0640,$gid); unset($password,$fixture,$runtime);
$config="<?php\ndefined('BASEPATH') OR exit;\n\$active_group='default'; \$query_builder=true;\n\$f=json_decode(file_get_contents(".var_export($s.'/runtime.json',true)."),true);\nif (!preg_match('/\\Afinance_ctl_test_[a-f0-9]{12}\\z/D',\$f['database']) || \$f['user']!==\$f['database']) exit;\n";
$write($s.'/app/application/config/production/database.php',$config."\$db['default']=array('dsn'=>'','hostname'=>'127.0.0.1','username'=>\$f['user'],'password'=>\$f['password'],'database'=>\$f['database'],'dbdriver'=>'mysqli','dbprefix'=>'','pconnect'=>false,'db_debug'=>false,'cache_on'=>false,'cachedir'=>'','char_set'=>'utf8mb4','dbcollat'=>'utf8mb4_unicode_ci','swap_pre'=>'','encrypt'=>false,'compress'=>false,'stricton'=>true,'failover'=>array(),'save_queries'=>false);\n",0644);
// Generated isolated config overrides only runtime paths/secrets, not business/auth code.
$extra="\n\$f=json_decode(file_get_contents(".var_export($s.'/runtime.json',true)."),true);\n\$config['base_url']=\$f['base_url'];\n\$config['encryption_key']=\$f['application_key'];\n\$config['sess_save_path']=".var_export($s.'/sessions',true).";\n\$config['log_path']=".var_export($s.'/logs/',true).";\n";
file_put_contents($s.'/app/application/config/production/config.php',$extra,FILE_APPEND);
$write($s.'/unused-deployment.json','{}',0640,$gid);
// Test certificate only; verify IP SAN with curl, never disable TLS verification.
$p=proc_open(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','2','-subj','/CN=Finance Practice','-addext','subjectAltName=IP:127.0.0.1','-keyout',$s.'/tls.key','-out',$s.'/tls.crt'],[0=>['file','/dev/null','r'],1=>['file',$s.'/openssl.log','a'],2=>['file',$s.'/openssl.log','a']],$pipes);
if (!is_resource($p)||proc_close($p)!==0) throw new RuntimeException('TLS_FIXTURE_FAILED'); chmod($s.'/tls.key',0600);chmod($s.'/tls.crt',0644);
$profiles=LinuxWebProfile::render(['app_root'=>$s.'/app','state_root'=>$s,'deployment_file'=>$s.'/unused-deployment.json','tls_certificate'=>$s.'/tls.crt','tls_key'=>$s.'/tls.key','mime_types'=>'/www/server/nginx/conf/mime.types','user'=>'finance_c3_trial','group'=>'finance_c3_trial','port'=>18444,'lua_root'=>'/www/server/nginx/lib/lua']);
foreach($profiles as $file=>$bytes) $write($s.'/'.$file,$bytes);
echo json_encode(['status'=>'PREPARED','scope'=>'isolated Control source/database fixture','database'=>$name,'migrations'=>count($migrations),'url'=>'https://127.0.0.1:18444/','operational_control_changed'=>false])."\n";
