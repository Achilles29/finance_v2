<?php

declare(strict_types=1);

final class A510BundleFailure extends RuntimeException
{
    public string $failureCode;
    public function __construct(string $code, string $message){parent::__construct($message);$this->failureCode=$code;}
}

function a510_fail(string $code,string $message):void{throw new A510BundleFailure($code,$message);}
function a510_emit(array $value):void{echo json_encode($value,JSON_UNESCAPED_SLASHES).PHP_EOL;}
function a510_safe_artifact(string $name):bool{return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,126}\.sql\.gz$/D',$name)===1&&strpos($name,'..')===false;}
function a510_safe_bundle_name(string $name):bool{return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,126}$/D',$name)===1&&strpos($name,'..')===false;}
function a510_digest($value,string $field):?string{if($value===null||$value==='null')return null;if(!is_string($value)||preg_match('/^[0-9a-f]{64}$/D',$value)!==1)a510_fail('invalid_digest',$field.' must be lowercase sha256 or null.');return$value;}
function a510_catalog_path():string{return __DIR__.'/migration_catalog.json';}
function a510_canonical_json(array $value):string{$json=json_encode($value,JSON_UNESCAPED_SLASHES);if(!is_string($json))a510_fail('manifest_encode_failed','Manifest could not be encoded.');return$json.PHP_EOL;}

function a510_bundle_directory(string $path):string
{
    if($path===''||$path[0]!==DIRECTORY_SEPARATOR||is_link($path)||!is_dir($path))a510_fail('unsafe_bundle','Bundle directory must be an absolute regular directory.');
    $real=realpath($path);if($real===false||$real!==rtrim($path,DIRECTORY_SEPARATOR))a510_fail('unsafe_bundle','Bundle directory is not canonical.');return$real;
}

function a510_manifest(array $input):array
{
    foreach(['bundle_dir','artifact','source_epoch','schema_digest','ledger_digest']as$key)if(!array_key_exists($key,$input))a510_fail('manifest_input','Manifest input is incomplete.');
    $dir=a510_bundle_directory((string)$input['bundle_dir']);$artifact=(string)$input['artifact'];
    if(!a510_safe_artifact($artifact))a510_fail('unsafe_artifact','Artifact name is unsafe.');
    $path=$dir.DIRECTORY_SEPARATOR.$artifact;
    if(is_link($path)||!is_file($path)||realpath($path)!==$path)a510_fail('artifact_missing','Archive must be a canonical regular non-symlink file.');
    $epoch=(string)$input['source_epoch'];if(preg_match('/^(0|[1-9][0-9]{0,9})$/D',$epoch)!==1||(int)$epoch>2147483647)a510_fail('invalid_source_epoch','Source epoch is invalid.');
    $catalog=a510_catalog_path();if(!is_file($catalog)||is_link($catalog))a510_fail('catalog_missing','Migration catalog is unavailable.');
    $size=filesize($path);$archiveHash=hash_file('sha256',$path);$catalogHash=hash_file('sha256',$catalog);
    if(!is_int($size)||!is_string($archiveHash)||!is_string($catalogHash))a510_fail('artifact_read_failed','Artifact metadata is unavailable.');
    return[
        'format'=>'finance-backup-bundle','version'=>1,
        'artifact'=>['path'=>$artifact,'sha256'=>$archiveHash,'size_bytes'=>$size],
        'source'=>['epoch'=>(int)$epoch,'created_utc'=>gmdate('Y-m-d\TH:i:s\Z',(int)$epoch)],
        'migration_catalog_sha256'=>$catalogHash,
        'schema_digest'=>a510_digest($input['schema_digest'],'schema_digest'),
        'ledger_digest'=>a510_digest($input['ledger_digest'],'ledger_digest'),
    ];
}

function a510_source_archive(string $path):array
{
    if($path===''||$path[0]!==DIRECTORY_SEPARATOR||is_link($path)||!is_file($path)||realpath($path)!==$path||!a510_safe_artifact(basename($path)))a510_fail('unsafe_source_archive','Source archive must be an absolute canonical regular non-symlink .sql.gz file.');
    $stat=lstat($path);if(!is_array($stat))a510_fail('source_archive_unavailable','Source archive metadata is unavailable.');return[$path,$stat];
}

function a510_output_bundle(string $path):array
{
    if($path===''||$path[0]!==DIRECTORY_SEPARATOR||substr($path,-1)===DIRECTORY_SEPARATOR||!a510_safe_bundle_name(basename($path)))a510_fail('unsafe_output_bundle','Output bundle path is unsafe.');
    $parent=dirname($path);if(is_link($parent)||!is_dir($parent)||realpath($parent)!==$parent||$path!==$parent.DIRECTORY_SEPARATOR.basename($path))a510_fail('unsafe_output_bundle','Output parent must be canonical.');
    if(!function_exists('posix_geteuid'))a510_fail('owner_check_unavailable','Effective owner check is unavailable.');$stat=lstat($parent);if(!is_array($stat)||($stat['uid']??-1)!==posix_geteuid()||(($stat['mode']??0)&0022)!==0)a510_fail('unsafe_output_parent','Output parent must be owned by the effective user and not group/world writable.');return[$parent,$path];
}

function a510_same_file_stat(array $a,array $b):bool{foreach(['dev','ino','mode','size','mtime','ctime']as$key)if(($a[$key]??null)!==($b[$key]??null))return false;return true;}
function a510_exact_mode(string $path,int $mode):bool{clearstatcache(true,$path);$actual=@fileperms($path);return is_int($actual)&&($actual&0777)===$mode;}
function a510_cleanup_stage(string $stage):bool{if(!file_exists($stage)&&!is_link($stage))return true;if(!is_dir($stage)||is_link($stage))return false;$entries=scandir($stage);if(!is_array($entries))return false;foreach($entries as$entry)if($entry!=='.'&&$entry!=='..'){$path=$stage.DIRECTORY_SEPARATOR.$entry;if((is_file($path)||is_link($path))&&!@unlink($path))return false;}$removed=@rmdir($stage);return$removed&&($GLOBALS['a510_test_build_failure']??null)!=='cleanup_observe_failure';}
function a510_test_set_build_failure(?string $point):void{if(!defined('A510_BACKUP_MANIFEST_LIBRARY_ONLY')||A510_BACKUP_MANIFEST_LIBRARY_ONLY!==true)a510_fail('test_hook_unavailable','Build test hook is unavailable.');if($point!==null&&!in_array($point,['stage_mode','cleanup_observe_failure'],true))a510_fail('test_hook_invalid','Build test hook is invalid.');$GLOBALS['a510_test_build_failure']=$point;}
function a510_parent_lock(string $parent)
{
    $path=$parent.DIRECTORY_SEPARATOR.'.finance-backup-bundle.lock';clearstatcache(true,$path);$existing=@lstat($path);if(is_array($existing)&&(is_link($path)||!is_file($path)||($existing['uid']??-1)!==posix_geteuid()||(($existing['mode']??0)&0077)!==0))a510_fail('unsafe_parent_lock','Parent build lock is unsafe.');
    $handle=@fopen($path,'c+b');if(!is_resource($handle))a510_fail('parent_lock_failed','Parent build lock could not be opened.');if(!chmod($path,0600)||!a510_exact_mode($path,0600)){fclose($handle);a510_fail('parent_lock_failed','Parent build lock mode could not be secured.');}if(!flock($handle,LOCK_EX)){fclose($handle);a510_fail('parent_lock_failed','Parent build lock could not be acquired.');}return$handle;
}

function a510_atomic_build(array $input):array
{
    foreach(['source_archive','output_bundle','source_epoch','schema_digest','ledger_digest']as$key)if(!array_key_exists($key,$input))a510_fail('build_input','Bundle build input is incomplete.');
    if(!function_exists('fsync'))a510_fail('fsync_unavailable','Required filesystem sync primitive is unavailable.');
    [$source,$pathStat]=a510_source_archive((string)$input['source_archive']);[$parent,$final]=a510_output_bundle((string)$input['output_bundle']);$artifact=basename($source);$lock=a510_parent_lock($parent);
    $stage=$parent.DIRECTORY_SEPARATOR.'.'.basename($final).'.stage-'.bin2hex(random_bytes(8));$cleanupTarget=$stage;$primaryFailure=null;
    clearstatcache(true,$final);if(@lstat($final)!==false){flock($lock,LOCK_UN);fclose($lock);a510_fail('output_exists','Output bundle already exists.');}
    if(!mkdir($stage,0700)){flock($lock,LOCK_UN);fclose($lock);a510_fail('stage_create_failed','Private bundle stage could not be created.');}
    try{
        if(($GLOBALS['a510_test_build_failure']??null)==='stage_mode'||!chmod($stage,0700)||!a510_exact_mode($stage,0700))a510_fail('stage_mode_failed','Private bundle stage mode could not be secured.');
        $in=@fopen($source,'rb');$out=@fopen($stage.DIRECTORY_SEPARATOR.$artifact,'xb');if(!is_resource($in)||!is_resource($out))a510_fail('archive_copy_failed','Archive copy could not start.');
        $handleBefore=fstat($in);if(!is_array($handleBefore)||!a510_same_file_stat($pathStat,$handleBefore)){fclose($in);fclose($out);a510_fail('source_archive_changed','Source archive identity changed.');}
        $hash=hash_init('sha256');$bytes=0;while(!feof($in)){$chunk=fread($in,1048576);if(!is_string($chunk))a510_fail('archive_copy_failed','Archive read failed.');if($chunk==='')continue;hash_update($hash,$chunk);$length=strlen($chunk);$written=0;while($written<$length){$n=fwrite($out,substr($chunk,$written));if(!is_int($n)||$n<1)a510_fail('archive_copy_failed','Archive write failed.');$written+=$n;}$bytes+=$length;}
        if(!fflush($out)||!fsync($out))a510_fail('archive_sync_failed','Staged archive could not be synchronized.');$handleAfter=fstat($in);fclose($in);fclose($out);$stagedArchive=$stage.DIRECTORY_SEPARATOR.$artifact;if(!chmod($stagedArchive,0600)||!a510_exact_mode($stagedArchive,0600))a510_fail('archive_mode_failed','Staged archive mode could not be secured.');clearstatcache(true,$source);$pathAfter=lstat($source);
        if(!is_array($handleAfter)||!is_array($pathAfter)||!a510_same_file_stat($handleBefore,$handleAfter)||!a510_same_file_stat($pathStat,$pathAfter)||$bytes!==($handleBefore['size']??-1))a510_fail('source_archive_changed','Source archive changed during copy.');
        $copiedHash=hash_final($hash);$stageHash=hash_file('sha256',$stage.DIRECTORY_SEPARATOR.$artifact);if(!is_string($stageHash)||!hash_equals($copiedHash,$stageHash)||filesize($stage.DIRECTORY_SEPARATOR.$artifact)!==$bytes)a510_fail('archive_copy_failed','Staged archive integrity check failed.');
        $manifest=a510_manifest(['bundle_dir'=>$stage,'artifact'=>$artifact,'source_epoch'=>$input['source_epoch'],'schema_digest'=>$input['schema_digest'],'ledger_digest'=>$input['ledger_digest']]);$manifestPath=$stage.DIRECTORY_SEPARATOR.'backup_bundle_manifest.json';
        $manifestHandle=@fopen($manifestPath,'xb');if(!is_resource($manifestHandle))a510_fail('manifest_write_failed','Canonical manifest could not be created.');$json=a510_canonical_json($manifest);$offset=0;while($offset<strlen($json)){$n=fwrite($manifestHandle,substr($json,$offset));if(!is_int($n)||$n<1)a510_fail('manifest_write_failed','Canonical manifest write was incomplete.');$offset+=$n;}if(!fflush($manifestHandle)||!fsync($manifestHandle))a510_fail('manifest_sync_failed','Canonical manifest could not be synchronized.');fclose($manifestHandle);if(!chmod($manifestPath,0600)||!a510_exact_mode($manifestPath,0600))a510_fail('manifest_mode_failed','Canonical manifest mode could not be secured.');
        if(!defined('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY'))define('A510_RESTORE_PREFLIGHT_LIBRARY_ONLY',true);require_once __DIR__.'/restore_preflight.php';a510_load_bundle($stage);
        clearstatcache(true,$final);if(@lstat($final)!==false)a510_fail('output_exists','Output bundle already exists.');if(!rename($stage,$final))a510_fail('publish_failed','Bundle could not be atomically published.');$cleanupTarget=$final;a510_sync_published_path($final);a510_sync_published_path($parent);$cleanupTarget='';
        return['status'=>'ok','mode'=>'build','bundle'=>basename($final),'artifact'=>$artifact,'manifest'=>'backup_bundle_manifest.json','format'=>'finance-backup-bundle','version'=>1,'durability'=>'fsync_and_syncfs_completed'];
    }catch(A510BundleFailure $error){$primaryFailure=$error;throw$error;}finally{$cleanupOk=$cleanupTarget===''||a510_cleanup_stage($cleanupTarget);$unlockOk=flock($lock,LOCK_UN);$closeOk=fclose($lock);if(!$cleanupOk||!$unlockOk||!$closeOk){$suffix=' Cleanup outcome:'.(!$cleanupOk?' target_failed':' target_ok').((!$unlockOk||!$closeOk)?',lock_failed.':',lock_ok.');if($primaryFailure instanceof A510BundleFailure)throw new A510BundleFailure($primaryFailure->failureCode,$primaryFailure->getMessage().$suffix);a510_fail(!$cleanupOk?'stage_cleanup_failed':'parent_lock_release_failed','Bundle cleanup failed.'.$suffix);}}
}

function a510_parse_options(array $args,int $start,array $allowed):array
{
    $out=[];for($i=$start,$n=count($args);$i<$n;$i++){$arg=$args[$i];if(!is_string($arg)||preg_match('/^--([a-z0-9-]+)=(.*)$/D',$arg,$m)!==1)a510_fail('usage','Invalid option syntax.');$key=str_replace('-','_',$m[1]);if(!in_array($key,$allowed,true)||array_key_exists($key,$out))a510_fail('usage','Unknown or duplicate option.');$out[$key]=$m[2];}return$out;
}

if(defined('A510_BACKUP_MANIFEST_LIBRARY_ONLY')&&A510_BACKUP_MANIFEST_LIBRARY_ONLY)return;

try{
    $args=$argv??[];foreach($args as$arg)if(is_string($arg)&&preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i',$arg))a510_fail('credential_cli','Credential arguments are forbidden.');
    if(($args[1]??'')!=='build')a510_fail('usage','Use build with one source archive and one new output bundle.');
    $options=a510_parse_options($args,2,['source_archive','output_bundle','source_epoch','schema_digest','ledger_digest']);
    foreach(['source_archive','output_bundle','source_epoch']as$key)if(!isset($options[$key]))a510_fail('usage','Required build option is missing.');
    $options+=['schema_digest'=>null,'ledger_digest'=>null];a510_emit(a510_atomic_build($options));
}catch(A510BundleFailure$error){fwrite(STDERR,json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()],JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
