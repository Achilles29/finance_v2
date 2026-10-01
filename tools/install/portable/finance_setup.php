<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/SetupService.php';
try {
    $root=str_replace('\\','/',dirname(__DIR__,3));
    $installer=new PortableInstaller($root);
    $action=$argv[1]??'';
    if(!in_array($action,['check','prepare','run','sync','retry-input','tick','license-sync','heartbeat'],true)||count($argv)!==2)throw new RuntimeException('USE_CHECK_PREPARE_RUN_SYNC_OR_RETRY_INPUT');
    if($action==='retry-input')$action='retryInput';
    if($action==='tick')$result=(new SetupService($root))->tick();
    elseif(in_array($action,['license-sync','heartbeat'],true))$result=(new SetupService($root))->scheduled($action);
    else $result=$action==='check'?PortableInstaller::requirements():$installer->$action();
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){$code=preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'SETUP_FAILED';fwrite(STDERR,json_encode(['status'=>'BLOCKED','code'=>$code])."\n");exit(1);}
