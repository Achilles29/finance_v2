<?php
declare(strict_types=1);
/** Companion entry point, not an installation/update command. Never run as web/root. */
require_once __DIR__.'/UpdatePreflight.php';
require_once __DIR__.'/UpdateJournal.php';
if(PHP_SAPI!=='cli')exit(1);
try{
    $args=[];
    foreach(array_slice($argv,1)as$arg){
        if(preg_match('/\A--(root|candidate|authorization)=(.+)\z/D',$arg,$m)!==1||isset($args[$m[1]]))throw new RuntimeException('UPDATE_ARGUMENT_INVALID');
        $args[$m[1]]=$m[2];
    }
    if(count($args)!==3)throw new RuntimeException('UPDATE_ARGUMENT_INVALID');
    $root=$args['root'];PortableStore::writer($root);
    // Issued credential must be private inside this application, never an arbitrary network/web input.
    if(!str_starts_with($args['authorization'],$root.'/private/'))throw new RuntimeException('UPDATE_AUTHORIZATION_PATH_INVALID');
    $authorization=CustomerPlatform::document($root,$args['authorization'],32768);
    // Always recheck package integrity and current license BEFORE returning a cached historical report.
    $result=UpdatePreflight::inspect($root,$args['candidate'],$authorization);
    $current=PortablePackage::verify($root);$next=PortablePackage::verify($args['candidate']);
    $installed=Control_license_cache::customer_context($root,$root.'/storage/customer-installation.json');
    $journal=new UpdateJournal(new PortableStore($root,'private'));
    $report=$journal->run($authorization,$current['release_trust'],$current,$next,$installed['identity'],static fn():array=>$result);
    echo json_encode($report,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
    $code=preg_match('/\A[A-Z][A-Z_]{3,100}\z/D',$e->getMessage())?$e->getMessage():'UPDATE_PREFLIGHT_FAILED';
    fwrite(STDERR,json_encode(['status'=>'BLOCKED','code'=>$code,'apply_supported'=>false])."\n");exit(1);
}
