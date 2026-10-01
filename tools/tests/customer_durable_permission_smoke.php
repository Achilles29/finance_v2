<?php
declare(strict_types=1);
require dirname(__DIR__).'/install/portable/PortablePackage.php';
require dirname(__DIR__).'/install/portable/SetupUi.php';
$n=0;$check=static function(bool $ok,string $label)use(&$n){if(!$ok)throw new RuntimeException($label);$n++;};
$reject=static function(array $p,int $v,string $code)use($check){try{PortablePackage::assertPermissionWindow($p,$v,true,1800000000);$check(false,'Expected rejection');}catch(RuntimeException$e){$check($e->getMessage()===$code,'Exact failure '.$code);}};
$p=['issued_at'=>1700000000,'expires_at'=>NULL,'permission_policy'=>'UNTIL_USED_OR_REVOKED'];
foreach([8,9,10,11]as$version)foreach([1800000000,1900000000,2000000000]as$at){PortablePackage::assertPermissionWindow($p,$version,true,$at);$check(true,'Signed durable window has no installation deadline');$check(!SetupUi::permissionExpired($p+['profile_version'=>$version],$at),'UI agrees after years of waiting');}
foreach(['expires_at'=>1800000100,'permission_policy'=>'FIXED_EXPIRY','issued_at'=>1800001000]as$k=>$v){$bad=$p;$bad[$k]=$v;$reject($bad,8,'SETUP_PERMISSION_BINDING_INVALID');}
$bad=$p;unset($bad['expires_at']);$reject($bad,8,'SETUP_PERMISSION_BINDING_INVALID');
$bad=$p;unset($bad['permission_policy']);$reject($bad,8,'SETUP_PERMISSION_BINDING_INVALID');
foreach([6,7,12]as$v)$reject($p,$v,'SETUP_PERMISSION_BINDING_INVALID');
$old=['issued_at'=>1700000000,'expires_at'=>1700003600];$reject($old,7,'SETUP_PERMISSION_EXPIRED');
PortablePackage::assertPermissionWindow($old,7,false,1800000000);$check(true,'Historical evidence can still be read without granting new setup permission');
foreach([[],['expires_at'=>NULL],$p,$p+['profile_version'=>7],['permission_policy'=>'UNKNOWN','expires_at'=>NULL]]as$b)$check(SetupUi::permissionExpired($b,1800000000),'Malformed web state never unlocks setup');
echo "Durable customer permission: $n checks PASS; no DB or Control contact.\n";
