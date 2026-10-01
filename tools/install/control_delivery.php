<?php
declare(strict_types=1);
require __DIR__.'/ControlDelivery.php';
umask(0077);
try{
    $mode=$argv[1]??'';$o=[];foreach(array_slice($argv,2) as $a){if(preg_match('/\A--(job-file|previous-job-file|trust-file|identity-file|result-file)=(.+)\z/D',$a,$m)!==1||isset($o[$m[1]]))throw new RuntimeException('USAGE');$o[$m[1]]=$m[2];}
    $expected=$mode==='fetch'?['job-file','trust-file']:($mode==='receipt'?['job-file','identity-file','result-file']:($mode==='replace'?['job-file','previous-job-file']:[]));
    if(!$expected||array_diff($expected,array_keys($o))||array_diff(array_keys($o),$expected))throw new RuntimeException('USAGE_FETCH_RECEIPT_OR_REPLACE_WITH_PRIVATE_FILES');
    $job=PrivateDeployment::read($o['job-file']);$dir=$job['state_dir']??'';unset($job['state_dir']);$client=new ControlDelivery($dir,$job);
    if($mode==='replace'){
        $previous=PrivateDeployment::read($o['previous-job-file']);$previousDir=$previous['state_dir']??'';unset($previous['state_dir']);
        $r=(new ControlDelivery($previousDir,$previous))->replacement($dir,$job);
    }else{$r=$mode==='fetch'?$client->fetch($o['trust-file']):$client->receipt(PrivateDeployment::read($o['identity-file']),PrivateDeployment::read($o['result-file']));}
    echo json_encode($r,JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){$code=$e instanceof RuntimeException&&preg_match('/\A[A-Z_]+\z/D',$e->getMessage())?$e->getMessage():'DELIVERY_FAILED';fwrite(STDERR,json_encode(['status'=>'ACTION_REQUIRED','code'=>$code,'recovery'=>ControlDelivery::recoveryMessage($code)],JSON_UNESCAPED_SLASHES)."\n");exit(1);}
