<?php

declare(strict_types=1);

$checks=0; $failures=[];
$check=static function(bool $ok,string $message)use(&$checks,&$failures):void{$checks++;if(!$ok){$failures[]=$message;fwrite(STDERR,'FAIL: '.$message.PHP_EOL);return;}echo 'PASS: '.$message.PHP_EOL;};
function a55t_rm(string $root,string $path):void{if(strpos($path,$root)!==0)throw new RuntimeException('unsafe cleanup');if(is_link($path)||is_file($path)){@unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path)?:[]as$entry)if($entry!=='.'&&$entry!=='..')a55t_rm($root,$path.'/'.$entry);@rmdir($path);}
function a55t_run(array $command,string $cwd,array $env):array{$p=proc_open($command,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd,$env);if(!is_resource($p))return['code'=>255,'out'=>'','err'=>''];$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return['code'=>proc_close($p),'out'=>(string)$out,'err'=>(string)$err];}
function a55t_code(array $result):string{$json=json_decode(trim($result['err']),true);return is_array($json)?(string)($json['code']??''):'';}

$root=dirname(__DIR__,2);$probe=$root.'/tools/db/schema_fingerprint_probe.php';
define('A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY',true);require $probe;
$manifest=json_decode((string)file_get_contents($root.'/tools/db/legacy_schema_fingerprints.json'),true);
$inventory=json_decode((string)file_get_contents($root.'/tools/db/legacy_sql_inventory.json'),true);
$tmp=sys_get_temp_dir().'/finance-a55-'.bin2hex(random_bytes(6));mkdir($tmp.'/bin',0700,true);
register_shutdown_function(static function()use($tmp):void{if(is_dir($tmp))a55t_rm($tmp,$tmp);});
$option=$tmp.'/client.cnf';file_put_contents($option,"[client]\nuser=fixture\n");chmod($option,0600);$databaseName='a55_contract_db';$databaseNameFile=$tmp.'/database-name';file_put_contents($databaseNameFile,$databaseName."\n");chmod($databaseNameFile,0600);
$capture=$tmp.'/capture.sql';$pidFile=$tmp.'/pid';$argvFile=$tmp.'/argv.json';
$fake=<<<'PHP'
#!/usr/bin/php
<?php
$mode=getenv('A55_MODE')?:'eligible';$capture=getenv('A55_CAPTURE');$pid=getenv('A55_PID');$expectedDatabase=getenv('A55_EXPECTED_DATABASE');file_put_contents(getenv('A55_ARGV'),json_encode($argv,JSON_UNESCAPED_SLASHES));if(!is_string($expectedDatabase)||end($argv)!==$expectedDatabase){fwrite(STDERR,"RAW SECRET selected database missing\n");exit(12);}file_put_contents($pid,(string)getmypid());$ordinal=0;
while(($line=fgets(STDIN))!==false){file_put_contents($capture,$line,FILE_APPEND);$ordinal++;if($mode==='timeout'){if(function_exists('pcntl_async_signals')){pcntl_async_signals(true);pcntl_signal(SIGTERM,SIG_IGN);}while(true)usleep(100000);}if($mode==='malformed'){fwrite(STDOUT,"RAW SECRET malformed\n");fflush(STDOUT);exit(8);}if($mode==='client_failure'){fwrite(STDERR,"RAW SECRET /secure/client.cnf\n");exit(9);}$fail=['missing_column'=>1,'wrong_column'=>1,'missing_index'=>2,'missing_fk'=>3,'wrong_default'=>4,'missing_semantic_seed'=>7,'invalid_global_target'=>8,'active_code_collision'=>9][$mode]??0;fwrite(STDOUT,"__A55_CHECK__\t".(($ordinal===$fail)?'0':'1')."\n");fflush(STDOUT);}
PHP;
file_put_contents($tmp.'/bin/mariadb',$fake);chmod($tmp.'/bin/mariadb',0700);
$env=['PATH'=>$tmp.'/bin','A55_CAPTURE'=>$capture,'A55_PID'=>$pidFile,'A55_ARGV'=>$argvFile,'A55_EXPECTED_DATABASE'=>$databaseName];
$validate=a55t_run([PHP_BINARY,$probe,'validate'],$root,$env);$validateJson=json_decode(trim($validate['out']),true);
$check($validate['code']===0&&($validateJson['records']??0)===7&&($validateJson['candidates']??0)===4&&($validateJson['historical_not_eligible']??0)===3&&!file_exists($argvFile),'validate fixes scope at seven records, four candidates, and three historical exclusions without starting a DB client');
$check(hash_file('sha256',$root.'/tools/db/legacy_sql_inventory.json')===($manifest['inventory_sha256']??'')&&array_column($manifest['records'],'path')===array_column($inventory['records'],'path'),'fingerprints bind exactly to inventory hash and record order');
$historical=array_values(array_filter($manifest['records'],static function(array $r):bool{return $r['disposition']==='historical-unverified';}));
$check(count($historical)===3&&count(array_filter($historical,static function(array $r):bool{return $r['eligibility']==='not_eligible'&&$r['checks']===[];}))===3,'historical records are hard not eligible with no executable checks');
$unsafeSelects=['SELECT 1; SELECT 2',"SELECT 1 INTO OUTFILE '/tmp/a55'","SELECT 1 INTO DUMPFILE '/tmp/a55'",'SELECT * FROM sys_page FOR UPDATE','SELECT * FROM sys_page FOR SHARE','SELECT * FROM sys_page LOCK IN SHARE MODE','SELECT @probe := 1','SELECT 1 INTO @probe','SELECT @probe = 1',"SELECT GET_LOCK('a55',1)","SELECT RELEASE_LOCK('a55')",'SELECT RELEASE_ALL_LOCKS()',"SELECT IS_FREE_LOCK('a55')",'SELECT SLEEP(1)','SELECT BENCHMARK(10,SHA1(1))',"SELECT LOAD_FILE('/tmp/a55')"];
foreach($unsafeSelects as$unsafeSelect)$check(!a55_safe_select($unsafeSelect),'safe-select rejects side-effect or denial-of-service fixture');
$check(a55_safe_select("SELECT REPLACE('a','a','b')")&&a55_safe_select('SELECT COLUMN_NAME FROM information_schema.COLUMNS'),'safe-select retains ordinary read-only metadata expressions');
$pageAliasRecord=array_values(array_filter($manifest['records'],static function(array $r):bool{return $r['path']==='sql/2026-09-04b_a3_page_alias_registry.sql';}))[0]??[];
$pageAliasChecks=array_column($pageAliasRecord['checks']??[],'id');
$check(in_array('page_alias_semantic_seed',$pageAliasChecks,true)&&in_array('page_alias_global_target_integrity',$pageAliasChecks,true)&&in_array('page_alias_global_code_collision',$pageAliasChecks,true),'page-alias fingerprint includes mappings and both global integrity checks');

$args=['probe','--defaults-extra-file='.$option,'--database-name-file='.$databaseNameFile];
@unlink($capture);$eligible=a55t_run([PHP_BINARY,$probe,...$args],$root,$env+['A55_MODE'=>'eligible']);$eligibleJson=json_decode(trim($eligible['out']),true);$sent=(string)@file_get_contents($capture);
$check($eligible['code']===0&&($eligibleJson['candidate_eligible']??0)===4&&($eligibleJson['overall_eligibility']??'')==='candidate_evidence_complete_historical_excluded','exact fake metadata produces eligible evidence for four candidates only');
$clientArgv=json_decode((string)@file_get_contents($argvFile),true);$check(is_array($clientArgv)&&array_slice($clientArgv,-2)===['--unbuffered',$databaseName],'fingerprint client selects the database as the final argv argument');
$check(substr_count($sent,"SELECT ")>=7&&preg_match('/(^|\n)\s*(SET|INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|LOCK|START|COMMIT)\b/i',$sent)!==1,'probe sends only read-only SELECT fingerprint queries');
$sentQueries=array_values(array_filter(preg_split('/\R/',$sent)?:[],static function(string $line):bool{return trim($line)!=='';}));
$check(count($sentQueries)===9&&count(array_filter($sentQueries,static function(string $line):bool{return preg_match('/;\s*$/',$line)===1&&substr_count($line,';')===1;}))===9,'probe terminates every validated SELECT with exactly one semicolon');
$check(count(array_filter($eligibleJson['records']??[],static function(array $r):bool{return $r['disposition']==='historical-unverified'&&$r['eligibility']==='not_eligible';}))===3,'historical results remain not eligible even when all queried evidence passes');

foreach(['missing_column','wrong_column','missing_index','missing_fk','wrong_default','missing_semantic_seed','invalid_global_target','active_code_collision']as$mode){@unlink($capture);$result=a55t_run([PHP_BINARY,$probe,...$args],$root,$env+['A55_MODE'=>$mode]);$json=json_decode(trim($result['out']),true);$check($result['code']===0&&($json['overall_eligibility']??'')==='not_eligible'&&($json['candidate_eligible']??4)<4,$mode.' produces deterministic not-eligible evidence');}
foreach(['malformed'=>'malformed_output','client_failure'=>'client_failure']as$mode=>$code){@unlink($capture);$result=a55t_run([PHP_BINARY,$probe,...$args],$root,$env+['A55_MODE'=>$mode]);$check($result['code']!==0&&a55t_code($result)===$code&&strpos($result['err'],'RAW SECRET')===false&&strpos($result['err'],$option)===false,$mode.' fails closed with redacted diagnostics');}
@unlink($capture);@unlink($pidFile);$timeout=a55t_run([PHP_BINARY,$probe,...$args],$root,$env+['A55_MODE'=>'timeout','A5_MIGRATION_TIMEOUT_SECONDS'=>'1']);$pid=(int)@file_get_contents($pidFile);$alive=$pid>0&&function_exists('posix_kill')?@posix_kill($pid,0):false;
$check($timeout['code']!==0&&a55t_code($timeout)==='client_timeout'&&!$alive,'nonresponsive fingerprint client is terminated at the bounded deadline');
$credential=a55t_run([PHP_BINARY,'-d','variables_order=GPCS',$probe,...$args],$root,$env+['DB_PASSWORD'=>'SECRET']);$check($credential['code']!==0&&a55t_code($credential)==='credential_env'&&strpos($credential['err'],'SECRET')===false,'credential environment is rejected independently of variables_order');
$missingDatabase=a55t_run([PHP_BINARY,$probe,'probe','--defaults-extra-file='.$option],$root,$env);$check($missingDatabase['code']!==0&&a55t_code($missingDatabase)==='usage'&&strpos($missingDatabase['err'],$option)===false,'missing database-name file argument fails before client start without path disclosure');
$extra=a55t_run([PHP_BINARY,$probe,...$args,'--unexpected'],$root,$env);$check($extra['code']!==0&&a55t_code($extra)==='usage','extra fingerprint probe argument fails closed');
$cli=a55t_run([PHP_BINARY,$probe,...$args,'--password=SECRET'],$root,$env);$check($cli['code']!==0&&a55t_code($cli)==='credential_cli'&&strpos($cli['err'],'SECRET')===false,'credential CLI argument is rejected without disclosure');
$plan=a55t_run([PHP_BINARY,$root.'/tools/db/migration_runner.php','plan','--policy=upgrade'],$root,['PATH'=>'/usr/bin:/bin']);$planJson=json_decode(trim($plan['out']),true);$check($plan['code']===0&&array_column($planJson['migrations']??[],'id')===['2026-09-04c-a5-schema-migration-registry-foundation','2026-09-05e-whatsapp-safe-reference-seed','2026-09-05a-telegram-bot-foundation','2026-09-05b-telegram-setup-guide','2026-09-05c-telegram-safe-activation-default','2026-09-06a-component-formula-version-history','2026-09-06b-component-formula-restore-action','2026-09-06c-pos-mobile-reversal-step-up','2026-09-06d-pos-mobile-reprint-step-up','2026-09-06e-activity-audit-foundation','2026-09-06f-pos-mobile-cashier-close-step-up','2026-09-06g-pos-mobile-reservation-refund-step-up','2026-09-06h-roastery-label-template-studio','2026-09-06i-a3-sidebar-task-oriented-layout','2026-09-07a-c2-c4-business-profile-license-runtime-foundation', '2026-09-12a-roast-connect-catalog', '2026-09-13a-finance-mutation-reporting-category', '2026-09-14a-finance-control-workspace', '2026-09-14b-finance-control-operations','2026-09-14c-finance-allocation-bank-review','2026-09-15a-finance-general-ledger','2026-09-15b-finance-journal-assistant','2026-09-15c-application-user-guide','2026-09-16a-procurement-stock-review','2026-09-20a-pos-stock-commit-not-required','2026-09-23a-module-notifications'],'catalog plan contains all twenty-six managed upgrade migrations in dependency order');
a55t_rm($tmp,$tmp);$check(!file_exists($tmp),'fingerprint fixtures are cleaned');
if($failures!==[]){fwrite(STDERR,count($failures).' A5.5 fingerprint check(s) failed.'.PHP_EOL);exit(1);}echo 'All '.$checks.' A5.5 schema fingerprint checks passed.'.PHP_EOL;
