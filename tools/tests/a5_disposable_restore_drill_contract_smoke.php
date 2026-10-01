<?php

declare(strict_types=1);

define('A511_DISPOSABLE_RESTORE_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/disposable_restore_drill.php';

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) echo 'PASS: ' . $message . PHP_EOL;
    else { $failures[] = $message; fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); }
};
function a511t_remove(string $root, string $path): void { if(strpos($path,$root)!==0)throw new RuntimeException('cleanup');if(is_link($path)||is_file($path)){@unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path)?:[]as$e)if($e!=='.'&&$e!=='..')a511t_remove($root,$path.'/'.$e);@rmdir($path); }
function a511t_failure(callable $call): string { try{$call();}catch(A511Failure $e){return$e->failureCode;}return''; }
function a511t_archive(string $path,string $sql):void{file_put_contents($path,gzencode($sql,6));chmod($path,0600);}
function a511t_process(array $command,string $cwd):array{$p=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$x,$cwd,['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta']);$o=stream_get_contents($x[1]);$e=stream_get_contents($x[2]);fclose($x[1]);fclose($x[2]);return['exit'=>proc_close($p),'out'=>(string)$o,'err'=>(string)$e];}

$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/a511-contract-'.bin2hex(random_bytes(5));mkdir($tmp,0700);register_shutdown_function(static fn()=>is_dir($tmp)?a511t_remove($tmp,$tmp):null);
$run='0123456789abcdef';$target=a511_target_name($run);
$check($target==='a511_restore_0123456789abcdef','target name is generated only from 16 hex');
$check(a511t_failure(static fn()=>a511_target_name('../unsafe'))==='invalid_run_id','invalid run id is rejected');
$check(a511t_failure(static fn()=>a511_assert_target_name('finance'))==='unsafe_target','source-like target is rejected');
$check(a511_account($target,'localhost')==="'a511_restore_0123456789abcdef'@'localhost'",'account identity is exact');
$check(a511t_failure(static fn()=>a511_account($target,'%'))==='unsafe_account_host','wildcard account host is rejected');

$option=$tmp.'/admin.cnf';file_put_contents($option,"[client]\nhost=\"127.0.0.1\"\nport=3306\nuser=ignored\npassword=ignored\ndatabase=ignored\n");chmod($option,0600);$endpoint=a511_option_endpoint($option);
$check(($endpoint['host']??'')==='127.0.0.1'&&($endpoint['port']??'')==='3306','only endpoint metadata is parsed');
chmod($option,0640);$check(a511t_failure(static fn()=>a511_private_file($option,$root))==='option_file_permissions','non-private option file is rejected');chmod($option,0600);
$check(a511_private_file($option,$root)===$option,'private external option file is accepted');
$included=$tmp.'/included.cnf';file_put_contents($included,"[client]\n!include /tmp/x.cnf\n");chmod($included,0600);$check(a511t_failure(static fn()=>a511_option_endpoint($included))==='option_file_parse','option include escape is rejected');
$remote=$tmp.'/remote.cnf';file_put_contents($remote,"[client]\nhost=db.example.invalid\n");chmod($remote,0600);$check(a511t_failure(static fn()=>a511_option_endpoint($remote))==='remote_endpoint','remote database endpoint is rejected');
$targetOption=a511_write_target_option($tmp,$run,$endpoint,$target,str_repeat('a',48),$target);$body=file_get_contents($targetOption);$targetDatabaseNameFile=a511_write_target_database_name($tmp,$run,$target);$databaseNameBody=file_get_contents($targetDatabaseNameFile);
$check((fileperms($targetOption)&0777)===0600&&strpos((string)$body,'database="'.$target.'"')!==false,'target option is private and database-bound');
$check($targetDatabaseNameFile!==$targetOption&&(fileperms($targetDatabaseNameFile)&0777)===0600&&$databaseNameBody===$target."\n",'separate database-name file is private and contains the exact target plus one LF');
$adminClientArgv=a511_direct_client_argv($targetOption);$targetClientArgv=a511_direct_client_argv($targetOption,$target);$restoreClientArgv=a511_restore_client_argv($targetOption,$target);
$check(array_slice($adminClientArgv,-1)===['--skip-column-names']&&array_slice($targetClientArgv,-2)===['--skip-column-names',$target],'admin direct client remains unbound while target direct client appends the validated target final');
$check(array_slice($restoreClientArgv,-2)===['--batch',$target],'restore client appends the validated target as its final argv argument');
$check(a511_remove_target_temporary_file($targetDatabaseNameFile,$tmp,$run)&&a511_remove_target_temporary_file($targetOption,$tmp,$run)&&!file_exists($targetDatabaseNameFile)&&!file_exists($targetOption),'temporary target option and database-name files leave no residue');

$safe=$tmp.'/safe.sql.gz';a511t_archive($safe,"-- fixture\nCREATE TABLE `demo` (`id` int);\nLOCK TABLES `demo` WRITE;\nINSERT INTO `demo` VALUES (1),(2);\nUNLOCK TABLES;\n");a511_scan_archive($safe,10);$check(true,'normal local dump passes scan');
$unsafe=['use'=>"USE `finance`;\n",'database'=>"CREATE DATABASE `finance`;\n",'grant'=>"GRANT ALL ON *.* TO 'x'@'%';\n",'user'=>"CREATE USER 'x'@'%';\n",'definer'=>"CREATE DEFINER='x'@'%' VIEW `v` AS SELECT 1;\n",'qualified'=>"CREATE TABLE `finance`.`x` (`id` int);\n",'global'=>"SET GLOBAL sql_mode='';\n",'file'=>"SELECT 1 INTO OUTFILE '/tmp/x';\n",'chained'=>"SET NAMES utf8mb4; USE `finance`;\n",'source'=>"source /etc/passwd\n",'shell'=>"\\! id\n",'delimiter_drop'=>"DELIMITER //\nDROP DATABASE `finance`//\n",'system_word'=>"system id\n",'connect_word'=>"connect finance localhost\n",'multiline_database'=>"DROP\nDATABASE `finance`;\n",'multiline_load'=>"LOAD\nDATA LOCAL INFILE '/tmp/x' INTO TABLE `demo`;\n"];
foreach($unsafe as$label=>$sql){$p=$tmp.'/'.$label.'.sql.gz';a511t_archive($p,$sql);$check(a511t_failure(static fn()=>a511_scan_archive($p,10))==='dump_escape',$label.' escape is rejected');}
$data=$tmp.'/data.sql.gz';a511t_archive($data,"INSERT INTO `demo` VALUES ('USE finance'),('DEFINER=x'),('RESET MASTER');\n");a511_scan_archive($data,10);$check(true,'keywords inside INSERT data do not false-positive');

$evidence=['format'=>'finance-a5-disposable-restore','version'=>1,'run_id'=>$run,'status'=>'error','failure_code'=>'fixture','phases'=>['cleanup'=>true]];$ev=a511_write_evidence($tmp,$run,$evidence);$evBody=file_get_contents($ev);
$check((fileperms($ev)&0777)===0600&&strpos((string)$evBody,'password')===false,'evidence is private and redacted');
$cli=a511t_process([PHP_BINARY,$root.'/tools/db/disposable_restore_drill.php','run','--database=finance'],$root);$decoded=json_decode(trim($cli['err']),true);
$check($cli['exit']!==0&&($decoded['code']??'')==='credential_cli'&&strpos($cli['err'],'finance')===false,'target and credential CLI values are rejected without echo');
$source=file_get_contents($root.'/tools/db/disposable_restore_drill.php');
$check(strpos((string)$source,"'/usr/bin/mariadb'")!==false&&strpos((string)$source,"'/usr/bin/gzip'")!==false,'trusted binaries use fixed paths');
$check(substr_count((string)$source,"'--sandbox'")>=2&&substr_count((string)$source,"'--local-infile=0'")>=2,'all MariaDB client paths disable metacommands and local infile');
$check(substr_count((string)$source,'a510_load_bundle($bundleDir)')>=2,'bundle is verified initially and immediately before restore');
$check(strpos((string)$source,'migration_runner.php')!==false&&strpos((string)$source,"'--policy=upgrade'")!==false,'official upgrade migration runner is used');
$check(strpos((string)$source,'schema_fingerprint_probe.php')!==false&&strpos((string)$source,'candidate_eligible')!==false,'fingerprint 4/4 is required');
$check(substr_count((string)$source,"'--database-name-file='.")===3&&strpos((string)$source,"['applied'] ?? null) !== \$managedMigrationCount")!==false&&strpos((string)$source,"['skipped'] ?? null) !== \$managedMigrationCount")!==false,'both migration runs and fingerprint use the separate database-name file with catalog-derived apply/replay counts');
$check(strpos((string)$source,'LOWER(HEX(migration_id))')!==false&&strpos((string)$source,"'2026-09-06h-roastery-label-template-studio'")!==false&&strpos((string)$source,"bin2hex(\$managedMigration['id']) . \"\\t\" . \$managedMigration['sha256']")!==false&&strpos((string)$source,'$actualRegistryRows !== $expectedRegistryRows')!==false,'registry verification binds every ordered upgrade ID to exact catalog checksums');
$check(strpos((string)$source,'$registryRows = $managedMigrationCount;')!==false&&strpos((string)$source,"'state'=>\$registryRows === \$managedMigrationCount ? 'COMPATIBLE_V1' : 'not_verified'")!==false&&strpos((string)$source,"'bootstrap_rows'=>\$registryRows")!==false,'registry evidence reports the catalog-derived verified upgrade row count');
$check(strpos((string)$source,'a511_restore_archive($archive, $targetOption, $target, $timeout)')!==false&&substr_count((string)$source,'a511_client($targetOption,')===2&&substr_count((string)$source,'$timeout, $target);')>=2,'restore and both direct target verification calls bind the exact generated target');
$check(strpos((string)$source,'DROP DATABASE IF EXISTS')!==false&&strpos((string)$source,'a511_assert_target_name($target)')!==false,'cleanup has exact target guard');
$check(strpos((string)$source,"PRIVILEGE_TYPE<>'USAGE'")!==false&&strpos((string)$source,'IS_GRANTABLE')!==false,'global and grant-option privileges are checked');
$check(strpos((string)$source,'a511_remove_target_temporary_file($targetDatabaseNameFile, $evidenceDir, $runId)')!==false&&strpos((string)$source,'a511_remove_target_temporary_file($targetOption, $evidenceDir, $runId)')!==false,'temporary credential and database-name cleanup is explicit and path-bounded');
$check(strpos((string)$source,'with redacted output')!==false&&strpos((string)$source,'stream_get_contents($dbPipes[2])')!==false,'client diagnostics are consumed but redacted');
$check(substr_count((string)$source,'proc_close(')>=4&&strpos((string)$source,'if ($caught instanceof Throwable) throw $caught;')!==false,'failed pipelines are reaped before cleanup continues');
a511t_remove($tmp,$tmp);$check(!file_exists($tmp),'fixtures are cleaned');
if($failures){fwrite(STDERR,count($failures).' A5.11 contract failure(s).'.PHP_EOL);exit(1);}echo'All '.$checks.' A5.11 disposable restore checks passed.'.PHP_EOL;
