<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('FAP_LIBRARY_ONLY',true);
require dirname(__DIR__).'/db/finance_allocation_postcheck.php';
$root=dirname(__DIR__,2);$hashes=fap_source_hashes($root);$rules=fap_checks($hashes);$sql=fap_sql($rules,$hashes);
$n=0;
$check=static function(bool $ok,string $label)use(&$n):void {if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo 'PASS '.$label.PHP_EOL;};
$throws=static function(callable $fn,string $label)use($check):void {try{$fn();$bad=false;}catch(Throwable $e){$bad=true;}$check($bad,$label);};
$check(count($rules)>80,'complete postcheck contract, not merely table existence');
foreach(explode(';',trim($sql)) as $statement)if(trim($statement)!==''){
    $check(str_starts_with(trim($statement),'SELECT '),'generated statement is SELECT only');
    $withoutStrings=preg_replace("/'(?:''|[^'])*'/",'',$statement);
    $check(!preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE|CALL|PREPARE|EXECUTE|SET|INTO|LOAD_FILE|GET_LOCK|SLEEP|BENCHMARK)\b/i',$withoutStrings)
        && !preg_match('/\bREPLACE\b(?!\s*\()/i',$withoutStrings),'no write/control statements or side-effect functions');
}
$reads=[];preg_match_all('/\bFROM\s+([a-zA-Z_\.]+)/',$sql,$m);$reads=array_unique($m[1]);sort($reads);
$check($reads===['information_schema.COLUMNS','information_schema.STATISTICS','information_schema.TABLES','sys_schema_migration'],'reads only schema metadata and migration ledger');
$catalog=json_decode(file_get_contents($root.'/tools/db/migration_catalog.json'),true);
foreach($catalog['migrations'] as $migration)if(isset($hashes[basename($migration['path'])])){
    $check(str_contains($rules['ledger.'.basename($migration['path'])]['expression'],fap_literal($migration['id'])),'uses actual canonical migration ID '.$migration['id']);
}

// Evaluate the generated SELECTs against synthetic information_schema data in memory.
// BINARY casts are translated to SQLite's case-sensitive string comparison; this
// validates conditions/reporting, not MariaDB syntax, privileges, locks or deployment.
$db=new SQLite3(':memory:');$db->exec("ATTACH DATABASE ':memory:' AS information_schema");
$db->createFunction('DATABASE',static fn()=>'db_finance',0);
$db->exec('CREATE TABLE information_schema.TABLES(TABLE_SCHEMA TEXT,TABLE_NAME TEXT,TABLE_TYPE TEXT,ENGINE TEXT,TABLE_COLLATION TEXT)');
$db->exec('CREATE TABLE information_schema.COLUMNS(TABLE_SCHEMA TEXT,TABLE_NAME TEXT,COLUMN_NAME TEXT,DATA_TYPE TEXT,COLUMN_TYPE TEXT,CHARACTER_MAXIMUM_LENGTH INT,CHARACTER_SET_NAME TEXT,NUMERIC_PRECISION INT,NUMERIC_SCALE INT,DATETIME_PRECISION INT,IS_NULLABLE TEXT,COLUMN_DEFAULT TEXT,EXTRA TEXT)');
$db->exec('CREATE TABLE information_schema.STATISTICS(TABLE_SCHEMA TEXT,TABLE_NAME TEXT,INDEX_NAME TEXT,SEQ_IN_INDEX INT,COLUMN_NAME TEXT,SUB_PART INT,NON_UNIQUE INT,INDEX_TYPE TEXT)');
$db->exec('CREATE TABLE sys_schema_migration(migration_id TEXT,filename TEXT,checksum_sha256 TEXT)');
$insert=static function(string $table,array $row)use($db):void {
    $statement=$db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
    foreach(array_values($row) as $i=>$value)$statement->bindValue($i+1,$value,$value===null?SQLITE3_NULL:(is_int($value)?SQLITE3_INTEGER:SQLITE3_TEXT));
    if(!$statement->execute())throw new RuntimeException('Metadata fixture failed.');
};
foreach($rules as $id=>$rule)if(str_starts_with($id,'table.'))$insert('information_schema.TABLES',['TABLE_SCHEMA'=>'db_finance','TABLE_NAME'=>substr($id,6),'TABLE_TYPE'=>'BASE TABLE','ENGINE'=>'InnoDB','TABLE_COLLATION'=>'utf8mb4_unicode_ci']);
$insert('information_schema.TABLES',['TABLE_SCHEMA'=>'db_finance','TABLE_NAME'=>'sys_schema_migration','TABLE_TYPE'=>'BASE TABLE']);
foreach(fap_columns() as $table=>$columns)foreach($columns as $column=>$spec){
    $nullable=str_starts_with($spec,'?');$auto=str_contains($spec,' AUTO');
    [$type,$default]=array_pad(explode('=',str_replace(' AUTO','',ltrim($spec,'?')),2),2,null);
    preg_match('/^([a-z]+)(?:\((\d+)(?:,(\d+))?\))?/',$type,$m);
    $data=['TABLE_SCHEMA'=>'db_finance','TABLE_NAME'=>$table,'COLUMN_NAME'=>$column,'DATA_TYPE'=>$m[1],'COLUMN_TYPE'=>$type,'IS_NULLABLE'=>$nullable?'YES':'NO','EXTRA'=>$auto?'auto_increment':'','COLUMN_DEFAULT'=>$default==='CURRENT_TIMESTAMP'?'current_timestamp()':($default===null?null:"'".$default."'")];
    if(in_array($m[1],['char','varchar'],true))$data+=['CHARACTER_MAXIMUM_LENGTH'=>(int)$m[2],'CHARACTER_SET_NAME'=>'utf8mb4'];
    if($m[1]==='decimal')$data+=['NUMERIC_PRECISION'=>(int)$m[2],'NUMERIC_SCALE'=>(int)$m[3]];
    if($m[1]==='datetime')$data+=['DATETIME_PRECISION'=>0];
    $insert('information_schema.COLUMNS',$data);
}
foreach(fap_indexes() as [$table,$index,$unique,$columns])foreach($columns as $i=>$column)$insert('information_schema.STATISTICS',[
    'TABLE_SCHEMA'=>'db_finance','TABLE_NAME'=>$table,'INDEX_NAME'=>$index,'SEQ_IN_INDEX'=>$i+1,'COLUMN_NAME'=>$column,'SUB_PART'=>null,'NON_UNIQUE'=>$unique?0:1,'INDEX_TYPE'=>'BTREE',
]);
foreach($hashes as $file=>$hash)$insert('sys_schema_migration',['migration_id'=>str_replace('_','-',substr($file,0,-4)),'filename'=>'sql/'.$file,'checksum_sha256'=>$hash]);
$evaluate=static function()use($db,$sql):string {
    $output='';
    foreach(explode(';',trim($sql)) as $query)if(trim($query)!==''){
        $query=preg_replace('/\bBINARY\s+/','',$query);$result=$db->query($query);
        if(!$result)throw new RuntimeException('Fixture SELECT failed.');
        while($row=$result->fetchArray(SQLITE3_NUM))$output.=implode("\t",$row)."\n";
    }
    return $output;
};
$good=$evaluate();$report=fap_report($good,$rules,$hashes);
$check($report['exit_code']===0 && str_contains($report['text'],'HASIL: SKEMA_LEDGER_LULUS'),'complete schema and matching ledger pass');
$check(str_contains($report['text'],'BELUM diverifikasi'),'passing checker never claims UAT or customer package ready');
$negative=static function(string $update,string $id,string $status,string $label)use($db,$evaluate,$rules,$hashes,$check):void {
    $db->exec('BEGIN');$db->exec($update);$raw=$evaluate();$report=fap_report($raw,$rules,$hashes);
    $check(str_contains($raw,$id."\t".$status."\t") && $report['exit_code']!==0,$label);$db->exec('ROLLBACK');
};
$negative("DELETE FROM information_schema.TABLES WHERE TABLE_NAME='fin_plan_allocation'",'table.fin_plan_allocation','FAIL','missing table fails');
$negative("UPDATE information_schema.TABLES SET ENGINE='MyISAM' WHERE TABLE_NAME='fin_receipt_allocation'",'table.fin_receipt_allocation','FAIL','nontransactional engine fails');
$negative("UPDATE information_schema.TABLES SET TABLE_TYPE='VIEW' WHERE TABLE_NAME='fin_receipt_distribution'",'table.fin_receipt_distribution','FAIL','view cannot impersonate table');
$negative("DELETE FROM information_schema.COLUMNS WHERE COLUMN_NAME='counter_account_id'",'column.fin_revenue_reconciliation_line.counter_account_id','FAIL','missing counter column fails');
$negative("UPDATE information_schema.COLUMNS SET COLUMN_TYPE='bigint' WHERE COLUMN_NAME='counter_account_id'",'column.fin_revenue_reconciliation_line.counter_account_id','FAIL','signed counter id fails');
$negative("UPDATE information_schema.COLUMNS SET COLUMN_TYPE=\"enum('NONE','IN','OUT')\" WHERE COLUMN_NAME='resolution_type'",'column.fin_revenue_reconciliation_line.resolution_type','FAIL','enum without TRANSFER fails');
$negative("UPDATE information_schema.COLUMNS SET IS_NULLABLE='NO' WHERE COLUMN_NAME='active_key'",'column.fin_plan_allocation.active_key','FAIL','nullable active key is essential for history');
$negative("UPDATE information_schema.COLUMNS SET NUMERIC_SCALE=0 WHERE COLUMN_NAME='amount'",'column.fin_plan_allocation.amount','FAIL','loss of decimal cents fails');
$negative("UPDATE information_schema.COLUMNS SET COLUMN_DEFAULT='1' WHERE COLUMN_NAME='revision'",'column.fin_bank_statement_row.revision','FAIL','incorrect revision default fails');
$negative("UPDATE information_schema.COLUMNS SET EXTRA='on update current_timestamp()' WHERE COLUMN_NAME='created_at'",'column.fin_bank_statement_row.created_at','FAIL','automatic timestamp rewrite fails');
$negative("UPDATE information_schema.COLUMNS SET EXTRA='' WHERE COLUMN_NAME='id'",'column.fin_bank_statement_row.id','FAIL','missing auto increment fails');
$negative("UPDATE information_schema.STATISTICS SET NON_UNIQUE=1 WHERE INDEX_NAME='uq_fin_bank_match'",'index.fin_bank_statement_row.uq_fin_bank_match','FAIL','nonunique bank-match index fails');
$negative("UPDATE information_schema.STATISTICS SET SEQ_IN_INDEX=3-SEQ_IN_INDEX WHERE INDEX_NAME='uq_fin_bank_row'",'index.fin_bank_statement_row.uq_fin_bank_row','FAIL','wrong composite index order fails');
$negative("UPDATE information_schema.STATISTICS SET SUB_PART=8 WHERE INDEX_NAME='uq_fin_bank_row' AND COLUMN_NAME='row_hash'",'index.fin_bank_statement_row.uq_fin_bank_row','FAIL','prefix hash index fails');
$negative("DELETE FROM information_schema.STATISTICS WHERE INDEX_NAME='ix_fin_bank_date'",'index.fin_bank_statement_row.ix_fin_bank_date','FAIL','missing date index fails');
$negative("DELETE FROM sys_schema_migration WHERE filename='sql/2026-09-14c_finance_allocation_bank_review.sql'",'ledger.2026-09-14c_finance_allocation_bank_review.sql','WARN','manual apply without ledger is pending, not schema failure');
$db->exec('BEGIN');$db->exec("DELETE FROM sys_schema_migration WHERE filename='sql/2026-09-14c_finance_allocation_bank_review.sql'");
$manual=fap_report($evaluate(),$rules,$hashes);
$check($manual['exit_code']===2 && str_contains($manual['text'],'SKEMA_LULUS_LEDGER_PENDING') && str_contains($manual['text'],'jangan otomatis mengulang migrasi'),'manual application is distinguished from failed schema');$db->exec('ROLLBACK');
$negative("UPDATE sys_schema_migration SET checksum_sha256='wrong' WHERE filename='sql/2026-09-14a_finance_control_workspace.sql'",'ledger.2026-09-14a_finance_control_workspace.sql','FAIL','ledger checksum drift fails');
$negative("INSERT INTO sys_schema_migration SELECT 'unexpected-id',filename,checksum_sha256 FROM sys_schema_migration WHERE filename='sql/2026-09-14c_finance_allocation_bank_review.sql'",'ledger.2026-09-14c_finance_allocation_bank_review.sql','FAIL','duplicate or conflicting ledger fails');
$db->createFunction('DATABASE',static fn()=>'wrong_database',0);$wrong=fap_report($evaluate(),$rules,$hashes);
$check($wrong['exit_code']===1 && str_contains($wrong['text'],'Database tujuan db_finance'),'wrong selected database rejected');
$db->createFunction('DATABASE',static fn()=>'db_finance',0);
$throws(fn()=>fap_report('',$rules,$hashes),'empty client output is not success');
$throws(fn()=>fap_report(preg_replace('/^END.*\n/m','',$good),$rules,$hashes),'client error before END is not success');
$throws(fn()=>fap_report(preg_replace('/^column\.fin_plan_allocation\.amount.*\n/m','',$good),$rules,$hashes),'missing required check is not success');
$throws(fn()=>fap_report($good.explode("\n",$good)[1]."\n",$rules,$hashes),'duplicate or appended output rejected');
$throws(fn()=>fap_report(str_replace('PASS','WARN',$good),$rules,$hashes),'schema WARN cannot bypass failure');
$throws(fn()=>fap_report(str_replace(fap_fingerprint($rules,$hashes),str_repeat('0',64),$good),$rules,$hashes),'changed sources or output fingerprint rejected');
$throws(fn()=>fap_report(str_repeat('x',131073),$rules,$hashes),'oversized client output rejected');
$throws(fn()=>fap_report("sensitive-untrusted-input\n",$rules,$hashes),'arbitrary input cannot be echoed as result');
$check(fap_report(str_replace("\n","\r\n",$good),$rules,$hashes)['exit_code']===0,'CRLF client output supported');

// All new table columns in the actual migration must be represented by the checker.
$source=file_get_contents($root.'/sql/2026-09-14c_finance_allocation_bank_review.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\) ENGINE=/s',$source,$blocks,PREG_SET_ORDER);
foreach($blocks as $block){
    $columns=[];
    foreach(preg_split('/,(?![^()]*\))/',$block[2]) as $part){
        if(preg_match('/^\s*(\w+)\s+(?:BIGINT|INT|CHAR|VARCHAR|DECIMAL|DATETIME|DATE)\b/i',$part,$m))$columns[]=$m[1];
    }
    $check($columns===array_keys(fap_columns()[$block[1]]),'every migrated column checked '.$block[1]);
}
$check(!preg_match('/mysqli|PDO|application\/config|proc_open|shell_exec|exec\s*\(/',file_get_contents(dirname(__DIR__).'/db/finance_allocation_postcheck.php')),'checker never connects, executes clients or loads credentials');
$cli=static function(string $mode,string $stdin='')use($root):array {
    $p=proc_open([PHP_BINARY,$root.'/tools/db/finance_allocation_postcheck.php',$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Fixture CLI unavailable.');
    if($stdin!=='')fwrite($pipes[0],$stdin);fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    return ['exit'=>proc_close($p),'out'=>$out,'err'=>$err];
};
$r=$cli('--sql');$check($r['exit']===0 && $r['out']===$sql && $r['err']==='','CLI emits exact SELECT program');
$r=$cli('--report',$good);$check($r['exit']===0 && str_contains($r['out'],'SKEMA_LEDGER_LULUS'),'CLI reports complete synthetic stream');
$db->exec('BEGIN');$db->exec("DELETE FROM sys_schema_migration WHERE filename='sql/2026-09-14c_finance_allocation_bank_review.sql'");
$r=$cli('--report',$evaluate());$check($r['exit']===2 && str_contains($r['out'],'SKEMA_LULUS_LEDGER_PENDING'),'CLI pending ledger is exit 2, not automatic apply');$db->exec('ROLLBACK');
$r=$cli('--report');$check($r['exit']===1 && $r['out']==='','failed upstream client cannot produce a passing report');
$r=$cli('--report',"synthetic-private-input\n");$check($r['exit']===1 && !str_contains($r['out'].$r['err'],'synthetic-private-input'),'CLI errors do not reflect input');
$r=$cli('--password=synthetic-private-argument');$check($r['exit']===1 && !str_contains($r['out'].$r['err'],'synthetic-private-argument'),'CLI rejects credential arguments without echo');
echo 'Finance allocation postcheck: '.$n.' PASS; '.count($rules).' emitted checks. Synthetic metadata only; live MariaDB not tested.'.PHP_EOL;
