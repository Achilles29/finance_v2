<?php

declare(strict_types=1);

if(!defined('A510_BACKUP_MANIFEST_LIBRARY_ONLY'))define('A510_BACKUP_MANIFEST_LIBRARY_ONLY',true);
require_once __DIR__.'/backup_bundle_manifest.php';

const A510_MANIFEST_NAME='backup_bundle_manifest.json';

function a510_timeout_seconds():int{$raw=getenv('A5_BACKUP_PREFLIGHT_TIMEOUT_SECONDS');if($raw===false||$raw==='')return 30;if(preg_match('/^(?:[1-9]|[12][0-9]|30)$/D',$raw)!==1)a510_fail('invalid_timeout','Preflight timeout is invalid.');return(int)$raw;}
function a510_test_set_gzip_binary(?string $path):void{if(!defined('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY')||A510_RESTORE_PREFLIGHT_LIBRARY_ONLY!==true)a510_fail('gzip_untrusted','Test gzip hook is unavailable.');if($path!==null&&($path===''||$path[0]!==DIRECTORY_SEPARATOR||is_link($path)||!is_file($path)||!is_executable($path)||realpath($path)!==$path))a510_fail('gzip_untrusted','Test gzip binary is unsafe.');$GLOBALS['a510_test_gzip_binary']=$path;}
function a510_find_gzip():string
{
    if(array_key_exists('a510_test_gzip_binary',$GLOBALS)&&is_string($GLOBALS['a510_test_gzip_binary']))return$GLOBALS['a510_test_gzip_binary'];
    $path='/usr/bin/gzip';$stat=@lstat($path);if(!is_array($stat)||is_link($path)||!is_file($path)||!is_executable($path)||realpath($path)!==$path||($stat['uid']??-1)!==0||(($stat['mode']??0)&0022)!==0)a510_fail('gzip_untrusted','Host-approved gzip verifier is unavailable or unsafe.');return$path;
}
function a510_test_set_sync_binary(?string $path):void{if(!defined('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY')||A510_RESTORE_PREFLIGHT_LIBRARY_ONLY!==true)a510_fail('sync_untrusted','Test sync hook is unavailable.');if($path!==null&&($path===''||$path[0]!==DIRECTORY_SEPARATOR||is_link($path)||!is_file($path)||!is_executable($path)||realpath($path)!==$path))a510_fail('sync_untrusted','Test sync binary is unsafe.');$GLOBALS['a510_test_sync_binary']=$path;}
function a510_find_sync():string{if(array_key_exists('a510_test_sync_binary',$GLOBALS)&&is_string($GLOBALS['a510_test_sync_binary']))return$GLOBALS['a510_test_sync_binary'];$path='/usr/bin/sync';$stat=@lstat($path);if(!is_array($stat)||is_link($path)||!is_file($path)||!is_executable($path)||realpath($path)!==$path||($stat['uid']??-1)!==0||(($stat['mode']??0)&0022)!==0)a510_fail('sync_untrusted','Host-approved filesystem sync tool is unavailable or unsafe.');return$path;}
function a510_sync_published_path(string $path):void
{
    $process=proc_open([a510_find_sync(),'-f','--',$path],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,[]);if(!is_resource($process))a510_fail('publish_sync_failed','Filesystem durability sync could not start.');stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$deadline=microtime(true)+a510_timeout_seconds();$exit=null;
    while(true){foreach([1,2]as$i)while(is_string($chunk=fread($pipes[$i],8192))&&$chunk!==''){}$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)>=$deadline){fclose($pipes[1]);fclose($pipes[2]);proc_terminate($process,15);usleep(100000);$status=proc_get_status($process);if($status['running'])proc_terminate($process,9);proc_close($process);a510_fail('publish_sync_timeout','Filesystem durability sync timed out.');}usleep(10000);}
    foreach([1,2]as$i){while(is_string($chunk=fread($pipes[$i],8192))&&$chunk!==''){}fclose($pipes[$i]);}$closed=proc_close($process);$code=$closed>=0?$closed:$exit;if($code!==0)a510_fail('publish_sync_failed','Filesystem durability sync failed.');
}

function a510_gzip_test(string $archive):void
{
    $process=proc_open([a510_find_gzip(),'-t','--',$archive],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,[]);
    if(!is_resource($process))a510_fail('gzip_unavailable','Gzip verifier could not start.');
    stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$deadline=microtime(true)+a510_timeout_seconds();$timedOut=false;
    $observedExit=null;while(true){foreach([1,2]as$i)while(is_string($chunk=fread($pipes[$i],8192))&&$chunk!==''){}$status=proc_get_status($process);if(!$status['running']){$observedExit=is_int($status['exitcode'])?$status['exitcode']:null;break;}if(microtime(true)>=$deadline){$timedOut=true;fclose($pipes[1]);fclose($pipes[2]);proc_terminate($process,15);usleep(100000);$status=proc_get_status($process);if($status['running'])proc_terminate($process,9);proc_close($process);break;}usleep(10000);}
    if($timedOut)a510_fail('gzip_timeout','Gzip integrity verification timed out.');
    foreach([1,2]as$i){while(is_string($chunk=fread($pipes[$i],8192))&&$chunk!==''){}fclose($pipes[$i]);}$closed=proc_close($process);$code=$closed>=0?$closed:$observedExit;if($code!==0)a510_fail('gzip_invalid','Archive gzip integrity check failed.');
}

function a510_exact_keys(array $value,array $keys):bool{return array_keys($value)===$keys;}
function a510_load_bundle(string $directory):array
{
    $dir=a510_bundle_directory($directory);$manifestPath=$dir.DIRECTORY_SEPARATOR.A510_MANIFEST_NAME;
    if(is_link($manifestPath)||!is_file($manifestPath)||realpath($manifestPath)!==$manifestPath)a510_fail('partial_bundle','Manifest is missing or unsafe.');
    $raw=file_get_contents($manifestPath,false,null,0,65537);if(!is_string($raw)||strlen($raw)>65536)a510_fail('manifest_malformed','Manifest is unreadable or oversized.');
    try{$manifest=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){a510_fail('manifest_malformed','Manifest JSON is malformed.');}
    if(!is_array($manifest)||!a510_exact_keys($manifest,['format','version','artifact','source','migration_catalog_sha256','schema_digest','ledger_digest'])||$manifest['format']!=='finance-backup-bundle'||$manifest['version']!==1||!is_array($manifest['artifact'])||!a510_exact_keys($manifest['artifact'],['path','sha256','size_bytes'])||!is_array($manifest['source'])||!a510_exact_keys($manifest['source'],['epoch','created_utc']))a510_fail('manifest_malformed','Manifest schema is invalid.');
    $artifact=$manifest['artifact']['path'];if(!is_string($artifact)||!a510_safe_artifact($artifact))a510_fail('unsafe_artifact','Artifact name is unsafe.');
    if(!is_string($manifest['artifact']['sha256'])||preg_match('/^[0-9a-f]{64}$/D',$manifest['artifact']['sha256'])!==1||!is_int($manifest['artifact']['size_bytes'])||$manifest['artifact']['size_bytes']<0||!is_int($manifest['source']['epoch'])||$manifest['source']['epoch']<0||$manifest['source']['epoch']>2147483647||$manifest['source']['created_utc']!==gmdate('Y-m-d\TH:i:s\Z',$manifest['source']['epoch'])||!is_string($manifest['migration_catalog_sha256'])||preg_match('/^[0-9a-f]{64}$/D',$manifest['migration_catalog_sha256'])!==1)a510_fail('manifest_malformed','Manifest values are invalid.');
    a510_digest($manifest['schema_digest'],'schema_digest');a510_digest($manifest['ledger_digest'],'ledger_digest');
    $canonical=json_encode($manifest,JSON_UNESCAPED_SLASHES).PHP_EOL;if($raw!==$canonical)a510_fail('manifest_malformed','Manifest is not canonical.');
    $entries=array_values(array_diff(scandir($dir)?:[],['.','..']));sort($entries,SORT_STRING);$expected=[A510_MANIFEST_NAME,$artifact];sort($expected,SORT_STRING);if($entries!==$expected)a510_fail('unexpected_artifact','Bundle contains missing or unexpected artifacts.');
    $archive=$dir.DIRECTORY_SEPARATOR.$artifact;if(is_link($archive)||!is_file($archive)||realpath($archive)!==$archive)a510_fail('partial_bundle','Archive is missing or unsafe.');
    $size=filesize($archive);$hash=hash_file('sha256',$archive);if($size!==$manifest['artifact']['size_bytes']||!is_string($hash)||!hash_equals($manifest['artifact']['sha256'],$hash))a510_fail('artifact_mismatch','Archive size or checksum does not match.');
    $catalogHash=hash_file('sha256',a510_catalog_path());if(!is_string($catalogHash)||!hash_equals($manifest['migration_catalog_sha256'],$catalogHash))a510_fail('catalog_mismatch','Migration catalog checksum does not match.');
    a510_gzip_test($archive);return$manifest;
}

function a510_preflight_output(string $mode,array $manifest):array
{
    return['status'=>'ok','mode'=>$mode,'format'=>$manifest['format'],'version'=>$manifest['version'],'artifact'=>$manifest['artifact']['path'],'source_epoch'=>$manifest['source']['epoch'],'created_utc'=>$manifest['source']['created_utc'],'plan'=>['verify_manifest','verify_archive_size_sha256','verify_migration_catalog_sha256','verify_gzip_integrity','reverify_bundle_immediately_before_consume','restore_external_review_required'],'database_action'=>'none'];
}

if(defined('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY')&&A510_RESTORE_PREFLIGHT_LIBRARY_ONLY)return;

try{
    $args=$argv??[];foreach($args as$arg)if(is_string($arg)&&preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i',$arg))a510_fail('credential_cli','Credential arguments are forbidden.');
    $mode=$args[1]??'';if(!in_array($mode,['verify','plan'],true)||count($args)!==3||strpos($args[2],'--bundle-dir=')!==0)a510_fail('source_target_ambiguity','Use verify or plan with exactly one bundle directory.');
    a510_emit(a510_preflight_output($mode,a510_load_bundle(substr($args[2],13))));
}catch(A510BundleFailure$error){fwrite(STDERR,json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
