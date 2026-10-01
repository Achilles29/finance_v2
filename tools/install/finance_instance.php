<?php
declare(strict_types=1);
require __DIR__.'/FinanceInstance.php';
require_once dirname(__DIR__,2).'/application/libraries/Control_license_verifier.php';
umask(0077);
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['stage','install','start','stop','health'],true)||count($argv)!==3||strpos($argv[2],'--config=')!==0)throw new RuntimeException('USAGE_INSTANCE_WITH_PRIVATE_CONFIG');
    $instance=new FinanceInstance(PrivateDeployment::read(substr($argv[2],9)));
    echo json_encode($instance->$mode(),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){$code=$e instanceof RuntimeException&&preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'INSTANCE_ACTION_FAILED';fwrite(STDERR,json_encode(['status'=>'ACTION_REQUIRED','code'=>$code,'recovery'=>'Keep release/database/runtime. Inspect private logs; no automatic destructive rollback.'])."\n");exit(1);}
