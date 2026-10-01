<?php
declare(strict_types=1);
require dirname(__DIR__).'/install/ControlDelivery.php';
$checks=0;$check=static function(bool$ok,string$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);++$checks;echo 'PASS '.$label."\n";};
$expected=['instance_id'=>'practice-example','primary_domain'=>'practice.example.invalid'];
$p=['status'=>'accepted','product_code'=>'NAMUA_FINANCE','instance_id'=>$expected['instance_id'],'primary_domain'=>$expected['primary_domain'],'action'=>'DEPLOY','environment'=>'DEMO','deployment_id'=>'00000000-0000-4000-8000-000000000001','plan_sha256'=>str_repeat('a',64),'release'=>['manifest_sha256'=>str_repeat('b',64),'artifact_sha256'=>str_repeat('c',64),'version'=>'0.1.0-alpha.10'],'artifacts'=>[
 ['type'=>'APPLICATION_PACKAGE','filename'=>'finance-0.1.0-alpha.10.tar','sha256'=>str_repeat('c',64),'size_bytes'=>597381120],
 ['type'=>'MANIFEST','filename'=>'finance-0.1.0-alpha.10.release.json','sha256'=>str_repeat('b',64),'size_bytes'=>1200],
 ['type'=>'OTHER','filename'=>'finance-0.1.0-alpha.10.release.sig.json','sha256'=>str_repeat('d',64),'size_bytes'=>512]]];
$check(count(ControlDelivery::validatePlan($p,$expected))===3,'valid Finance plan accepts large streaming artifact and two sidecars');
foreach([null,'','different.example.invalid','changed.example.invalid'] as $domain){$v=$p;$v['primary_domain']=$domain;$check(count(ControlDelivery::validatePlan($v,$expected))===3,'registry domain is optional metadata, not a binding');}
$v=$p;unset($v['primary_domain']);$check(count(ControlDelivery::validatePlan($v,['instance_id'=>$expected['instance_id']]))===3,'missing registry and local metadata accepted');
$mutations=[static function(&$v){$v['instance_id']='wrong-instance';},static function(&$v){$v['plan_sha256']='invalid';},static function(&$v){$v['deployment_id']='invalid';},static function(&$v){$v['product_code']='OTHER';},static function(&$v){$v['action']='DELETE';},static function(&$v){$v['environment']='UNKNOWN';},static function(&$v){$v['artifacts'][0]['size_bytes']=ControlReleaseBridge::MAX_BYTES+1;},static function(&$v){$v['artifacts'][1]['size_bytes']=1048577;},static function(&$v){$v['artifacts'][0]['filename']='../archive.tar';},static function(&$v){$v['artifacts'][1]['filename']='other.release.json';},static function(&$v){$v['artifacts'][2]['type']='MANIFEST';},static function(&$v){$v['artifacts'][0]['sha256']=str_repeat('d',64);},static function(&$v){$v['artifacts'][2]['size_bytes']='512';}];
foreach($mutations as$mutate){$bad=$p;$mutate($bad);$denied=false;try{ControlDelivery::validatePlan($bad,$expected);}catch(RuntimeException$e){$denied=true;}$check($denied,'invalid plan binding or unsafe artifact rejected');}
foreach(['plan_sha256'=>str_repeat('f',64),'deployment_id'=>'00000000-0000-4000-8000-000000000002'] as $key=>$value){
    $denied=false;try{ControlDelivery::validatePlan($p,$expected+[$key=>$value]);}catch(RuntimeException $e){$denied=$e->getMessage()==='PLAN_BINDING_INVALID';}
    $check($denied,'explicit expected '.$key.' must match, even with a valid format');
}
$root=dirname(__DIR__,2);$delivery=(string)file_get_contents($root.'/tools/install/ControlDelivery.php');$instance=(string)file_get_contents($root.'/tools/install/FinanceInstance.php');
$check(strpos($delivery,"['web_verified']??false)!==true")!==false,'receipt explicitly requires web acceptance');
$check(strpos($delivery,'CURLOPT_FOLLOWLOCATION=>false')!==false&&strpos($delivery,'CURLOPT_SSL_VERIFYPEER=>true')!==false,'delivery never disables TLS or follows redirects');
$check(strpos($delivery,"'CLAIM_UNCERTAIN'")!==false&&strpos($delivery,"'REQUEST_UNCERTAIN'")!==false,'ambiguous one-use requests persist before transport');
$check(strpos($instance,'VERIFIED_UPGRADE_BACKUP_REQUIRED')<strpos($instance,"\$d['status']='INSTALLING'"),'upgrade validates backup before schema changes');
$check(strpos($instance,'SERVICE_PID_NOT_OWNED')!==false&&strpos($instance,'posix_kill($pid,SIGQUIT)')!==false,'service control is limited to its recorded configuration');
$check(strpos($instance,'DROP DATABASE')===false&&strpos($instance,'rm -rf')===false,'instance manager does not delete databases or release directories');
echo 'All '.$checks.' installer/delivery contract checks passed.'."\n";
