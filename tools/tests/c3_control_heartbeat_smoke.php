<?php
declare(strict_types=1);
define('FINANCE_HEARTBEAT_LIBRARY_ONLY',true);
require dirname(__DIR__,2).'/scripts/control_center_heartbeat.php';
$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);++$checks;echo 'PASS '.$label."\n";};
$reject=static function(callable $call,string $label)use($check):void{try{$call();}catch(Throwable $e){$check(true,$label);return;}$check(false,$label);};
$old=['public_url'=>'https://local.example.invalid/finance/'];
$check(heartbeat_runtime($old,null)===null,'legacy sender omits runtime and preserves monitoring metadata');
$config=$old+['runtime_fields'=>['primary_domain','region'],'region'=>'Jakarta'];
$_SERVER['HTTP_HOST']='spoofed.example.invalid';
$check(heartbeat_runtime($config,null)===['primary_domain'=>'local.example.invalid','region'=>'Jakarta'],'trusted private URL fallback never uses HTTP_HOST');
$check(heartbeat_runtime($config,'https://Customer.Example.invalid:8443/finance/')===['primary_domain'=>'customer.example.invalid','region'=>'Jakarta'],'configured base URL wins; strip protocol port and path');
$check(heartbeat_runtime($config,'https://changed.example.invalid')['primary_domain']==='changed.example.invalid','customer URL may change without identity change');
$check(heartbeat_runtime($config,'')['primary_domain']==='','explicit unavailable base URL emits empty metadata');
$check(heartbeat_runtime(['runtime_fields'=>['region'],'region'=>''],null)===['region'=>''],'empty region clears it while omitted domain stays absent');
$check(heartbeat_runtime(['runtime_fields'=>[]],null)===[],'empty runtime selection does not clear individual fields');
foreach(['http://plain.example.invalid','https://user:pass@example.invalid','https://example.invalid/?token=synthetic','https://example.invalid/#fragment',"https://example.invalid/\n",'https://999.999.999.999'] as $bad)$reject(fn()=>heartbeat_runtime($config,$bad),'unsafe URL cannot become runtime metadata');
$host=str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',62);
$check(strlen(heartbeat_runtime($config,'https://'.$host)['primary_domain'])===190,'domain exactly 190 bytes accepted');
$reject(fn()=>heartbeat_runtime($config,'https://'.$host.'c'),'domain over 190 bytes rejected');
$check(heartbeat_runtime($config,'https://[2001:db8::1]:8443/path')['primary_domain']==='2001:db8::1','IPv6 literal carries no brackets or port');
$check(heartbeat_runtime(['runtime_fields'=>['region'],'region'=>str_repeat('界',80)],null)['region']===str_repeat('界',80),'region limit counts Unicode characters, not bytes');
foreach([str_repeat('界',81),"bad\nregion","bad\0region","bad\x7fregion","bad\u{0085}region","\xff",42] as $bad)$reject(fn()=>heartbeat_runtime(['runtime_fields'=>['region'],'region'=>$bad],null),'invalid region or controls rejected');
foreach([['secret'],['primary_domain','secret'],['primary_domain','primary_domain'],[[]],'primary_domain'] as $fields)$reject(fn()=>heartbeat_runtime(['runtime_fields'=>$fields],null),'runtime allowlist rejects unknown/invalid selection');
$payload=['instance_id'=>'fixture-instance','sent_at'=>gmdate(DATE_ATOM),'app_version'=>'0.1.0-test','schema_version'=>'fixture','environment'=>'DEMO','health'=>'OK','components'=>['database'=>'OK'],'metrics'=>[]];
// Optional read-only interoperability with actual Control validator. No bootstrap, DB or network.
foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--control-root='))throw new RuntimeException('USAGE');
    $control=substr($arg,15);define('BASEPATH','fixture');define('APPPATH',$control.'/application/');class CI_Controller {}
    require $control.'/application/libraries/Instance_registration.php';require $control.'/application/controllers/Api_heartbeats.php';
    $reflection=new ReflectionClass(Api_heartbeats::class);$controller=$reflection->newInstanceWithoutConstructor();
    $method=$reflection->getMethod('validate_payload');$method->setAccessible(true);
    $check($method->invoke($controller,$payload)===null,'actual Control accepts old heartbeat without runtime');
    foreach([heartbeat_runtime($config,null),['primary_domain'=>'','region'=>''],['region'=>'Jakarta'],[]] as $metadata)$check($method->invoke($controller,$payload+['runtime'=>$metadata])===null,'actual Control accepts new optional metadata');
    $check($method->invoke($controller,$payload+['runtime'=>['secret'=>'synthetic']])==='runtime_metadata_invalid','actual Control rejects extra metadata field');
}
$withRuntime=$payload+['runtime'=>heartbeat_runtime($config,null)];
$body=json_encode($withRuntime,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$changed=$withRuntime;$changed['runtime']['primary_domain']='other.example.invalid';
$check(hash('sha256',$body)!==hash('sha256',json_encode($changed,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'runtime changes are covered by raw body digest in HMAC');
$source=file_get_contents(dirname(__DIR__,2).'/scripts/control_center_heartbeat.php');
$check(strpos($source,"\$payload['runtime']=")<strpos($source,"\$body = json_encode(\$payload"),'metadata added before signed body serialization');
echo 'All '.$checks.' heartbeat metadata checks passed.'."\n";
