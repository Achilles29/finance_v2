<?php
declare(strict_types=1);
// Real application writers with a :memory: SQLite adapter. Never reads application DB config.
// FOR UPDATE is translated for functional tests only; this does NOT validate MariaDB locking/DDL.
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
$root=dirname(__DIR__,2);
define('BASEPATH',$root.'/system/');define('APPPATH',$root.'/application/');define('ENVIRONMENT','testing');
date_default_timezone_set('Asia/Jakarta');
function log_message($level,$message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION,$version,'>='); }
function show_error($message='',$status=500): void { throw new RuntimeException((string)$message); }
function site_url($path=''): string { return '/'.$path; }
$context=new stdClass();function &get_instance() { return $GLOBALS['context']; }
require BASEPATH.'core/Model.php';require BASEPATH.'database/DB.php';
$params=['dbdriver'=>'sqlite3','database'=>':memory:','db_debug'=>false,'save_queries'=>true];
$unused=DB($params,true);
class FinanceAllocationMemoryDB extends CI_DB_sqlite3_driver
{
    public $failAudit=false;
    public $hideAllocationSchema=false;
    public $hideTransferSchema=false;
    public function field_exists($field_name,$table_name)
    {
        if($this->hideTransferSchema && $table_name==='fin_revenue_reconciliation_line' && in_array($field_name,['counter_account_id','counter_payment_method_id','counter_mutation_id'],true))return false;
        return parent::field_exists($field_name,$table_name);
    }
    public function table_exists($table_name)
    {
        if($this->hideAllocationSchema && in_array($table_name,['fin_receipt_distribution','fin_receipt_allocation','fin_plan_allocation','fin_bank_statement_row'],true))return false;
        return parent::table_exists($table_name);
    }
    public function reset_fixture_request(): void { $this->_trans_status=true; }
    public function query($sql,$binds=false,$return_object=null)
    {
        if ($this->failAudit && preg_match('/^INSERT INTO ["`]?aud_transaction_log/i',$sql)) { $this->_trans_status=false;return false; }
        $sql=preg_replace('/\s+FOR UPDATE\b/i','',$sql);
        $sql=str_replace('DATE_ADD(?, INTERVAL 1 DAY)',"datetime(?, '+1 day')",$sql);
        return parent::query($sql,$binds,$return_object);
    }
}
$db=new FinanceAllocationMemoryDB($params);$db->initialize();$context->db=$db;
$db->conn_id->createFunction('IF',static fn($c,$a,$b)=>$c?$a:$b,3);
$db->conn_id->createFunction('FIELD',static function($needle,...$values){$index=array_search($needle,$values,true);return $index===false?0:$index+1;},-1);
$checks=0;$check=static function(bool $ok,string $label)use(&$checks):void { if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL; };
$put=static function(string $table,array $data)use($db):int { if(!$db->insert($table,$data))throw new RuntimeException('fixture '.$table.' '.json_encode($db->error()));return (int)$db->insert_id(); };
$create=static function(string $sql)use($db):void {
    if(!preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?\s*\((.*?)\) ENGINE/s',$sql,$m))throw new RuntimeException('Fixture DDL not found.');
    $body=preg_replace('/\benum\([^)]*\)/i','TEXT',$m[2]);
    $parts=preg_split('/,(?![^()]*\))/',$body);$columns=[];
    foreach($parts as $part){
        $part=trim($part);if(preg_match('/^(?:KEY|CONSTRAINT|FULLTEXT)\b/i',$part))continue;
        $part=preg_replace('/\bUNIQUE KEY\s+`?\w+`?\s*/i','UNIQUE ',$part);
        $part=preg_replace('/\b(?:bigint|int|tinyint|smallint)(?:\(\d+\))?(?:\s+unsigned)?/i','INTEGER',$part);
        $part=preg_replace('/\b(?:AUTO_INCREMENT|USING BTREE)\b/i','',$part);
        $part=preg_replace('/\s+ON UPDATE current_timestamp\(\)/i','',$part);
        $part=str_ireplace('current_timestamp()','CURRENT_TIMESTAMP',$part);
        $part=preg_replace('/\s+(?:COLLATE|CHARACTER SET)\s+\w+/i','',$part);
        $part=preg_replace('/\s+COMMENT\s+\x27[^\x27]*\x27/i','',$part);
        $columns[]=$part;
    }
    if(!$db->query('CREATE TABLE '.$m[1].' ('.implode(',',$columns).')'))throw new RuntimeException('Fixture DDL '.$m[1].' '.json_encode($db->error()));
};
$baseline=file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql');
foreach(['fin_company_account','auth_user','fin_account_mutation_log','fin_payable','fin_receivable','fin_period_close','aud_transaction_log','fin_cash_reconciliation','fin_cash_reconciliation_line','fin_revenue_reconciliation','fin_revenue_reconciliation_line','fin_revenue_reconciliation_method','pos_payment_method','pos_payment','pos_payment_line','pos_refund','pay_payroll_period','pay_payroll_result'] as $table){
    preg_match('/CREATE TABLE `'.$table.'`.*?;\n/s',$baseline,$m);$create($m[0]);
}
foreach(['2026-09-14a_finance_control_workspace.sql','2026-09-14b_finance_control_operations.sql','2026-09-14c_finance_allocation_bank_review.sql'] as $file){
    preg_match_all('/CREATE TABLE IF NOT EXISTS .*?;\n/s',file_get_contents($root.'/sql/'.$file),$matches);
    foreach($matches[0] as $sql)$create($sql);
}
foreach(['fin_account_mutation_log'=>['report_category TEXT','settlement_control_id INTEGER','settlement_charge_id INTEGER','client_request_key TEXT'],
    'fin_revenue_reconciliation_line'=>['report_category TEXT','settlement_control_id INTEGER','settlement_charge_id INTEGER','counter_payment_method_id INTEGER','counter_account_id INTEGER','counter_mutation_id INTEGER'],
    'fin_cash_reconciliation_line'=>['report_category TEXT','settlement_control_id INTEGER','settlement_charge_id INTEGER'],
    'fin_settlement_control'=>['receipt_mode INTEGER DEFAULT 0','receipt_opening_amount NUMERIC']] as $table=>$fields) {
    foreach($fields as $field)if(!$db->field_exists(explode(' ',$field)[0],$table))$db->query('ALTER TABLE '.$table.' ADD COLUMN '.$field);
}
$db->data_cache=[];
require APPPATH.'models/Finance_control_operation_model.php';require APPPATH.'models/Finance_revenue_reconciliation_model.php';require APPPATH.'models/Finance_cash_reconciliation_model.php';
$op=new Finance_control_operation_model();$revenue=new Finance_revenue_reconciliation_model();$cash=new Finance_cash_reconciliation_model();
$ok=static function(array $r,string $label)use($check):array{$check(!empty($r['ok']),$label.' '.($r['message']??''));return $r;};
$deny=static function(array $r,string $label)use($check):void{$check(empty($r['ok']),$label.' '.($r['message']??''));};
$throws=static function(callable $fn,string $label)use($check):void{try{$fn();$bad=false;}catch(Throwable $e){$bad=true;}$check($bad,$label);};
$today=date('Y-m-d');$key=static fn()=>bin2hex(random_bytes(16));
$put('fin_control_policy',['id'=>1]);
foreach([1,2,3] as $id)$put('fin_company_account',['id'=>$id,'account_code'=>'TEST-'.$id,'account_name'=>'Synthetic '.$id,'current_balance'=>1000,'opening_balance'=>1000]);
foreach([1,2,3] as $id)$put('pos_payment_method',['id'=>$id,'method_code'=>'TEST-'.$id,'method_name'=>'Synthetic '.$id,'method_type'=>'BANK','company_account_id'=>$id===2?1:$id]);
$caseSeed=['revenue_date'=>$today,'account_id'=>1,'due_date'=>$today,'expected_amount'=>1000,'source_fingerprint'=>str_repeat('f',64)];
$case1=$put('fin_settlement_control',$caseSeed+['payment_method_id'=>1,'provider_reference'=>'A','received_amount'=>50]);
$case2=$put('fin_settlement_control',$caseSeed+['payment_method_id'=>2,'provider_reference'=>'B','received_amount'=>0]);
$case3=$put('fin_settlement_control',array_replace($caseSeed,['account_id'=>3])+['payment_method_id'=>3,'provider_reference'=>'C']);
$balance=static fn()=>(float)$db->query('SELECT SUM(current_balance) n FROM fin_company_account')->row('n');
$case=static fn($id)=>$db->get_where('fin_settlement_control',['id'=>$id])->row_array();
$receipt=$ok($op->save_receipt(['settlement_id'=>$case1,'revision'=>1,'request_key'=>$key(),'received_date'=>$today,'reference_no'=>'R-1','amount'=>'100.25','notes'=>'synthetic receipt'],1),'save receipt')['id'];
$check((float)$case($case1)['received_amount']===150.25,'legacy opening preserved before distribution');
$payload=['receipt_id'=>$receipt,'revision'=>0,'request_key'=>$key(),'notes'=>'split verified transfer','allocations'=>json_encode([['settlement_id'=>$case1,'amount'=>'40.10'],['settlement_id'=>$case2,'amount'=>'50.15']])];
$ok($op->distribute_receipt($payload,1),'split transfer across two settlements');
$check((float)$case($case1)['received_amount']===90.10 && (float)$case($case2)['received_amount']===50.15 && $balance()===3000.,'split exact cents, preserves opening, never posts bank');
$ok($op->distribute_receipt($payload,1),'distribution request replay');
$deny($op->distribute_receipt(array_replace($payload,['notes'=>'changed request']),1),'same key different contents');
$deny($op->distribute_receipt(array_replace($payload,['request_key'=>$key()]),1),'stale distribution revision');
$bad=array_replace($payload,['revision'=>1,'request_key'=>$key(),'allocations'=>json_encode([['settlement_id'=>$case2,'amount'=>'100.26']])]);
$deny($op->distribute_receipt($bad,1),'overallocated transfer');
$deny($op->distribute_receipt(array_replace($bad,['allocations'=>json_encode([['settlement_id'=>$case3,'amount'=>'10']])]),1),'different account allocation');
$deny($op->distribute_receipt(array_replace($bad,['allocations'=>json_encode([['settlement_id'=>$case1,'amount'=>'10'],['settlement_id'=>$case1,'amount'=>'10']])]),1),'duplicate settlement');
$deny($op->void_receipt(['id'=>$receipt,'revision'=>$case($case1)['revision'],'reason'=>'wrong transfer'],1),'distributed VOID requires reviewed distribution revision');
$ok($op->void_receipt(['id'=>$receipt,'revision'=>$case($case1)['revision'],'distribution_revision'=>1,'reason'=>'wrong transfer'],1),'VOID entire allocated receipt');
$check((float)$case($case1)['received_amount']===50. && (float)$case($case2)['received_amount']===0. && $balance()===3000.,'VOID affects both settlements and no bank');
$deny($op->distribute_receipt(array_replace($payload,['revision'=>1,'request_key'=>$key()]),1),'cannot reallocate void receipt');
$receipt2=$ok($op->save_receipt(['settlement_id'=>$case1,'revision'=>$case($case1)['revision'],'request_key'=>$key(),'received_date'=>$today,'reference_no'=>'R-2','amount'=>'20','notes'=>'second transfer'],1),'second transfer')['id'];
$db->failAudit=true;
$deny($op->distribute_receipt(['receipt_id'=>$receipt2,'revision'=>0,'request_key'=>$key(),'notes'=>'rollback','allocations'=>json_encode([['settlement_id'=>$case2,'amount'=>'20']])],1),'audit failure rejects distribution');
$db->failAudit=false;$db->reset_fixture_request();
$check((float)$case($case1)['received_amount']===70. && (float)$case($case2)['received_amount']===0.,'audit failure rolls back both totals');
$m=$put('fin_account_mutation_log',['mutation_no'=>'MANUAL-1','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'OUT','amount'=>100,'ref_module'=>'FINANCE']);
$planSeed=['direction'=>'OUT','amount'=>100,'due_date'=>$today,'notes'=>'synthetic plan'];
$plan1=$put('fin_cash_plan',$planSeed+['title'=>'Plan A','request_key'=>$key()]);$plan2=$put('fin_cash_plan',$planSeed+['title'=>'Plan B','request_key'=>$key()]);
$p=['plan_id'=>$plan1,'mutation_id'=>$m,'revision'=>1,'request_key'=>$key(),'amount'=>'60','notes'=>'first portion'];
$id1=$ok($op->allocate_plan($p,1),'partial plan one')['id'];$ok($op->allocate_plan($p,1),'partial plan replay');
$deny($op->allocate_plan(array_replace($p,['amount'=>'61']),1),'plan replay amount change');
$id2=$ok($op->allocate_plan(array_replace($p,['plan_id'=>$plan2,'request_key'=>$key(),'amount'=>'40']),1),'remaining portion to second plan')['id'];
$check(Finance_allocation_policy::used_plan_cents($db,$m)===10000 && $balance()===3000.,'plan allocations capped at actual, no cash posting');
$forecast=$op->forecast(7);$plans=array_column($forecast['plans'],null,'id');
$check($plans[$plan1]['actual']===60. && $plans[$plan1]['remaining']===40. && $plans[$plan2]['actual']===40. && $plans[$plan2]['remaining']===60.,'actual forecast uses partial amounts for each plan');
$deny($op->allocate_plan(array_replace($p,['revision'=>2,'request_key'=>$key(),'amount'=>'0.01']),1),'plan over-allocation');
$deny($op->link_plan(['plan_id'=>$plan1,'mutation_id'=>$m,'revision'=>2,'notes'=>'whole'],1),'legacy whole link cannot bypass allocated cap');
$ok($op->unlink_plan_allocation(['id'=>$id1,'revision'=>2,'reason'=>'wrong plan'],1),'unlink partial');
$check(Finance_allocation_policy::used_plan_cents($db,$m)===4000,'unlink releases only its portion');
$pos=$put('fin_account_mutation_log',['mutation_no'=>'POS-TEST','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'OUT','amount'=>100,'ref_module'=>'POS']);
$deny($op->allocate_plan(array_replace($p,['mutation_id'=>$pos,'request_key'=>$key(),'revision'=>3]),1),'POS not manual plan actual');
$put('fin_account_mutation_log',['mutation_no'=>'REVERSE-1','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'IN','amount'=>100,'ref_module'=>'FINANCE','reversal_of_mutation_id'=>$m]);
$deny($op->allocate_plan(array_replace($p,['request_key'=>$key(),'revision'=>3]),1),'void mutation cannot allocate');
$effective=$db->query('SELECT COUNT(*) n FROM fin_plan_allocation a JOIN fin_account_mutation_log m ON m.id=a.mutation_id WHERE a.active_key IS NOT NULL AND '.Finance_settlement_control::effective())->row('n');
$check((int)$effective===0,'void original excludes partials from forecast effective predicate');
$plans=array_column($op->forecast(7)['plans'],null,'id');
$check($plans[$plan2]['actual']===0. && $plans[$plan2]['remaining']===100.,'actual forecast restores plan after VOID');
$csv="Date;Reference;In;Out\n".$today.";BANK-A;100,25;\n".$today.";BANK-B;;10,00\n";
$import=['account_id'=>1,'csv'=>$csv,'delimiter'=>';','date_format'=>'Y-m-d','number_format'=>'id','column_date'=>0,'column_reference'=>1,'column_in'=>2,'column_out'=>3];
$preview=$ok($op->import_statement($import,1),'CSV preview');$check(count($preview['rows'])===2 && $db->count_all('fin_bank_statement_row')===0,'preview never persists rows');
$ok($op->import_statement($import+['confirm_hash'=>$preview['preview_hash']],1),'confirm CSV import');
$ok($op->import_statement($import+['confirm_hash'=>$preview['preview_hash']],1),'repeat CSV import');
$check($db->count_all('fin_bank_statement_row')===2 && $balance()===3000.,'CSV replay dedup and no cash');
$deny($op->import_statement($import+['confirm_hash'=>str_repeat('0',64)],1),'changed preview hash');
$deny($op->import_statement(array_replace($import,['account_id'=>2,'confirm_hash'=>$preview['preview_hash']]),1),'preview bound to account');
$deny($op->import_statement(array_replace($import,['column_in'=>3,'column_out'=>2,'confirm_hash'=>$preview['preview_hash']]),1),'preview bound to column mapping');
$throws(fn()=>Finance_bank_csv::parse(str_replace('100,25;','100,25;1,00',$csv),$import),'both in/out CSV rejected');
$throws(fn()=>Finance_bank_csv::parse($csv,array_replace($import,['column_out'=>2])),'duplicate mapped column rejected');
$throws(fn()=>Finance_bank_csv::parse(str_repeat('x',1048577),$import),'oversize CSV rejected');
$throws(fn()=>Finance_bank_csv::parse("\xff",$import),'invalid UTF8 rejected');
$throws(fn()=>Finance_bank_csv::parse(str_replace('100,25','1e5',$csv),$import),'exponent not accepted as money');
$bankMutation=$put('fin_account_mutation_log',['mutation_no'=>'BANK-MATCH','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'IN','amount'=>'100.25','ref_module'=>'POS']);
$bankRow=$db->get_where('fin_bank_statement_row',['reference_no'=>'BANK-A'])->row_array();
$ok($op->match_statement(['id'=>$bankRow['id'],'mutation_id'=>$bankMutation,'revision'=>0,'reason'=>'verified reference'],1),'confirm exact bank match');
$db->where('id',$bankMutation)->update('fin_account_mutation_log',['amount'=>'100.26']);
$changed=array_column($op->bank_rows(['account_id'=>1])['rows'],null,'id');
$check(!$changed[$bankRow['id']]['effective'],'edited mutation amount invalidates bank match');
$db->where('id',$bankMutation)->update('fin_account_mutation_log',['amount'=>'100.25']);
$deny($op->match_statement(['id'=>$bankRow['id'],'mutation_id'=>$bankMutation,'revision'=>0,'reason'=>'stale'],1),'stale bank confirmation');
$bankOther=$db->get_where('fin_bank_statement_row',['reference_no'=>'BANK-B'])->row_array();
$deny($op->match_statement(['id'=>$bankOther['id'],'mutation_id'=>$bankMutation,'revision'=>0,'reason'=>'wrong amount'],1),'wrong direction amount bank match');
$put('fin_account_mutation_log',['mutation_no'=>'BANK-VOID','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'OUT','amount'=>'100.25','ref_module'=>'POS','reversal_of_mutation_id'=>$bankMutation]);
$rows=$op->bank_rows(['account_id'=>1]);$matched=array_values(array_filter($rows['rows'],fn($r)=>(int)$r['id']===(int)$bankRow['id']))[0];
$check(!$matched['effective'],'bank match visibly stale after original VOID');
$ok($op->match_statement(['id'=>$bankRow['id'],'mutation_id'=>0,'revision'=>1,'reason'=>'release void link'],1),'unlink bank evidence');
foreach(['-1','0','1.234','1e3','NaN','1000000000000'] as $value)$throws(fn()=>Finance_allocation_policy::cents($value),'strict cents '.$value);
$check(Finance_allocation_policy::cents('999999999999.99')===99999999999999,'maximum exact cents');

// Daily revenue transfer: optional method attribution, never requires/closes a counter line.
foreach([4,5] as $id) {
    $put('pos_payment_method',['id'=>$id,'method_code'=>'RECON-'.$id,'method_name'=>'Reconciliation '.$id,'method_type'=>'BANK','company_account_id'=>$id===4?1:2]);
    $put('pos_payment',['id'=>$id,'payment_no'=>'P-'.$id,'order_id'=>$id,'payment_type'=>'FINAL','payment_status'=>'PAID','paid_at'=>$today.' 10:00:00']);
    $put('pos_payment_line',['payment_id'=>$id,'line_no'=>1,'payment_method_id'=>$id,'amount'=>100,'status'=>'PAID']);
}
$header=$put('fin_revenue_reconciliation',['reconciliation_no'=>'REV-1','reconciliation_date'=>$today,'revenue_date'=>$today,'round_no'=>1]);
$seed=['reconciliation_id'=>$header,'expected_amount'=>100,'status'=>'OPEN'];
$left=$put('fin_revenue_reconciliation_line',$seed+['payment_method_id'=>4,'account_id'=>1,'actual_amount'=>80,'difference_amount'=>-20,'resolution_type'=>'TRANSFER','counter_account_id'=>2,'counter_payment_method_id'=>5,'resolution_note'=>'Incorrect payment method']);
$right=$put('fin_revenue_reconciliation_line',$seed+['payment_method_id'=>5,'account_id'=>2,'actual_amount'=>120,'difference_amount'=>20,'resolution_type'=>'NONE']);
$before=$balance();$ok($revenue->post_line($left,1),'revenue transfer with optional method attribution');
$check($balance()===$before && (float)$db->get_where('fin_company_account',['id'=>1])->row('current_balance')===980.,'two-account transfer zero sum');
$l=$db->get_where('fin_revenue_reconciliation_line',['id'=>$left])->row_array();$r=$db->get_where('fin_revenue_reconciliation_line',['id'=>$right])->row_array();
$check($l['status']==='POSTED' && $r['status']==='OPEN' && empty($r['mutation_id']) && (int)$l['counter_mutation_id']>0,'only primary line posted; counter line untouched');
$deny($revenue->post_line($left,1),'primary transfer cannot post twice');
$deny($revenue->post_line($right,1),'counter NONE cannot post; attribution already counted');
$map=(new ReflectionClass($revenue))->getMethod('posted_adjustment_map');$map->setAccessible(true);$adjusted=$map->invoke($revenue,$today);
$check((float)$adjusted[4]===-20. && (float)$adjusted[5]===20.,'both methods adjustments included exactly once');
$dashboard=$revenue->dashboard($today,$today,$header);$byMethod=array_column($dashboard['rows'],null,'payment_method_id');
$check($byMethod[4]['difference_amount']===0. && $byMethod[5]['difference_amount']===0. && $byMethod[4]['posted_difference_amount']===-20.,'posted revenue displays zero remaining, preserves historical difference separately');
$check($db->where('ref_module','FINANCE_TRANSFER')->where('report_category IS NOT NULL',null,false)->count_all_results('fin_account_mutation_log')===0,'transfer not categorized as income or expense');
$header2=$put('fin_revenue_reconciliation',['reconciliation_no'=>'REV-2','reconciliation_date'=>$today,'revenue_date'=>$today,'round_no'=>2]);
$left2=$put('fin_revenue_reconciliation_line',array_replace($seed,['reconciliation_id'=>$header2])+['payment_method_id'=>4,'account_id'=>1,'actual_amount'=>70,'difference_amount'=>-10,'resolution_type'=>'TRANSFER','counter_account_id'=>2,'counter_payment_method_id'=>5,'resolution_note'=>'Correction two']);
$right2=$put('fin_revenue_reconciliation_line',array_replace($seed,['reconciliation_id'=>$header2])+['payment_method_id'=>5,'account_id'=>2,'actual_amount'=>131,'difference_amount'=>11,'resolution_type'=>'NONE']);
$count=$db->count_all('fin_account_mutation_log');
$db->where('id',$left2)->update('fin_revenue_reconciliation_line',['difference_amount'=>-11]);
$deny($revenue->post_line($left2,1),'stale saved transfer difference rejected');
$check($db->count_all('fin_account_mutation_log')===$count,'stale transfer no cash posting');
$db->where('id',$left2)->update('fin_revenue_reconciliation_line',['difference_amount'=>-10]);
$db->failAudit=true;$deny($revenue->post_line($left2,1),'transfer audit failure despite unequal counter line');$db->failAudit=false;$db->reset_fixture_request();
$check($db->count_all('fin_account_mutation_log')===$count && $balance()===$before,'transfer audit rollback both sides');
$db->where('id',1)->update('fin_company_account',['current_balance'=>5]);
$deny($revenue->post_line($left2,1),'insufficient source transfer');
$check($db->count_all('fin_account_mutation_log')===$count,'insufficient transfer no unilateral entry');
$db->where('id',1)->update('fin_company_account',['current_balance'=>980]);
$period=$put('fin_period_close',['period_code'=>'TEST-CLOSE','period_year'=>(int)date('Y'),'period_month'=>(int)date('n'),'period_start'=>$today,'period_end'=>$today,'status'=>'CLOSED']);
$deny($revenue->post_line($left2,1),'closed period transfer');
$deny($op->allocate_plan(array_replace($p,['request_key'=>$key(),'revision'=>3]),1),'closed period metadata write');
$db->where('id',$period)->update('fin_period_close',['status'=>'OPEN']);
// Cash stale balance regression: no posting of a different amount than reviewed.
$ch=$put('fin_cash_reconciliation',['reconciliation_no'=>'CASH-1','reconciliation_date'=>$today,'round_no'=>1]);
$cl=$put('fin_cash_reconciliation_line',['reconciliation_id'=>$ch,'account_id'=>1,'system_balance'=>1000,'actual_balance'=>950,'difference_amount'=>-50,'resolution_type'=>'OUT','report_category'=>'CASH_SHORTAGE','status'=>'OPEN']);
$count=$db->count_all('fin_account_mutation_log');
$denied=$cash->post_line($cl,1);$deny($denied,'stale cash snapshot');
$check(str_contains($denied['message'],'Saldo berubah') && $db->count_all('fin_account_mutation_log')===$count,'stale cash rejection creates no mutation');
// Fresh IN/OUT/TRANSFER remain available in cash reconciliation, no historical data involved.
$db->where('id',$cl)->update('fin_cash_reconciliation_line',['system_balance'=>980,'actual_balance'=>950,'difference_amount'=>-30]);
$ok($cash->post_line($cl,1),'cash OUT current snapshot');
$ch2=$put('fin_cash_reconciliation',['reconciliation_no'=>'CASH-2','reconciliation_date'=>$today,'round_no'=>2]);
$cl2=$put('fin_cash_reconciliation_line',['reconciliation_id'=>$ch2,'account_id'=>1,'system_balance'=>950,'actual_balance'=>960,'difference_amount'=>10,'resolution_type'=>'IN','report_category'=>'CASH_SURPLUS','status'=>'OPEN']);
$ok($cash->post_line($cl2,1),'cash IN current snapshot');
$ch3=$put('fin_cash_reconciliation',['reconciliation_no'=>'CASH-3','reconciliation_date'=>$today,'round_no'=>3]);
$cl3=$put('fin_cash_reconciliation_line',['reconciliation_id'=>$ch3,'account_id'=>1,'system_balance'=>960,'actual_balance'=>950,'difference_amount'=>-10,'resolution_type'=>'TRANSFER','counter_account_id'=>2,'status'=>'OPEN']);
$cashBefore=$balance();$ok($cash->post_line($cl3,1),'cash TRANSFER current snapshot');
$check($balance()===$cashBefore,'cash TRANSFER zero sum');
$ch4=$put('fin_cash_reconciliation',['reconciliation_no'=>'CASH-4','reconciliation_date'=>$today,'round_no'=>4]);
$cl4=$put('fin_cash_reconciliation_line',['reconciliation_id'=>$ch4,'account_id'=>1,'system_balance'=>950,'actual_balance'=>940,'difference_amount'=>-10,'resolution_type'=>'TRANSFER','counter_account_id'=>3,'status'=>'OPEN']);
$db->where('id',3)->update('fin_company_account',['currency_code'=>'USD']);
$deny($cash->post_line($cl4,1),'cash transfer rejects mixed currency without exchange rate');
$db->where('id',3)->update('fin_company_account',['currency_code'=>'IDR']);
$header3=$put('fin_revenue_reconciliation',['reconciliation_no'=>'REV-3','reconciliation_date'=>$today,'revenue_date'=>$today,'round_no'=>3]);
$revenueOut=$put('fin_revenue_reconciliation_line',array_replace($seed,['reconciliation_id'=>$header3])+['payment_method_id'=>4,'account_id'=>1,'actual_amount'=>75,'difference_amount'=>-5,'resolution_type'=>'OUT','report_category'=>'CASH_SHORTAGE','resolution_note'=>'Verified shortfall']);
$revenueIn=$put('fin_revenue_reconciliation_line',array_replace($seed,['reconciliation_id'=>$header3])+['payment_method_id'=>5,'account_id'=>2,'actual_amount'=>125,'difference_amount'=>5,'resolution_type'=>'IN','report_category'=>'CASH_SURPLUS','resolution_note'=>'Verified surplus']);
$ok($revenue->post_line($revenueOut,1),'revenue OUT remains functional');
$ok($revenue->post_line($revenueIn,1),'revenue IN remains functional');
$methods=array_column($revenue->dashboard($today,$today,$header3)['rows'],null,'payment_method_id');
$check($methods[4]['difference_amount']===0. && $methods[5]['difference_amount']===0.,'revenue IN and OUT both show resolved remaining');
$deny($revenue->post_line($revenueOut,1),'revenue adjustment cannot post twice');
// User contract: daily reconciliation uses the SAME category list as cash/manual.
// Standalone transfer can use an account with no POS method and no second recon line.
$standaloneAccount=$put('fin_company_account',['account_code'=>'NO-POS-METHOD','account_name'=>'Reserve account','current_balance'=>200,'opening_balance'=>200]);
$round=$ok($revenue->create_round(['reconciliation_date'=>$today,'revenue_date'=>$today],1),'new daily round')['header'];
$base=['reconciliation_id'=>$round['id'],'reconciliation_date'=>$today,'revenue_date'=>$today,'payment_method_id'=>4,'account_id'=>1,'actual_amount'=>'70','resolution_type'=>'TRANSFER','counter_account_id'=>$standaloneAccount,'resolution_note'=>'Move verified daily difference'];
$deny($revenue->save_line(array_replace($base,['counter_account_id'=>1]),1),'save transfer same account');
$deny($revenue->save_line(array_replace($base,['counter_account_id'=>99999]),1),'save transfer nonexistent account');
$deny($revenue->save_line(array_replace($base,['counter_payment_method_id'=>5]),1),'optional counter method wrong mapping');
$db->where('id',$standaloneAccount)->update('fin_company_account',['is_active'=>0]);
$deny($revenue->save_line($base,1),'save transfer inactive account');
$db->where('id',$standaloneAccount)->update('fin_company_account',['is_active'=>1,'currency_code'=>'USD']);
$deny($revenue->save_line($base,1),'save transfer mixed currency');
$db->where('id',$standaloneAccount)->update('fin_company_account',['currency_code'=>'IDR']);
$saved=$ok($revenue->save_line($base+['report_category'=>'CASH_SHORTAGE','settlement_control_id'=>$case1,'settlement_charge_id'=>999],1),'save standalone transfer without counter method');
$line=$db->get_where('fin_revenue_reconciliation_line',['id'=>$saved['line_id']])->row_array();
$check(empty($line['counter_payment_method_id']) && empty($line['report_category']) && empty($line['settlement_control_id']) && empty($line['settlement_charge_id']),'transfer strips category and unrelated settlement references');
$sum=$balance();$oldBank=(float)$db->get_where('fin_company_account',['id'=>$standaloneAccount])->row('current_balance');
$ok($revenue->post_line($saved['line_id'],1),'post standalone OUT transfer with no second method or line');
$check($balance()===$sum && (float)$db->get_where('fin_company_account',['id'=>$standaloneAccount])->row('current_balance')===$oldBank+5.,'standalone deficit transfers primary OUT counter IN');
$roundIn=$ok($revenue->create_round(['reconciliation_date'=>$today,'revenue_date'=>$today],1),'round for surplus transfer')['header'];
$savedIn=$ok($revenue->save_line(array_replace($base,['reconciliation_id'=>$roundIn['id'],'actual_amount'=>'72']),1),'save standalone surplus transfer');
$db->where('id',$standaloneAccount)->update('fin_company_account',['is_active'=>0]);
$deny($revenue->post_line($savedIn['line_id'],1),'post rechecks inactive counter account');
$db->where('id',$standaloneAccount)->update('fin_company_account',['is_active'=>1,'currency_code'=>'USD']);
$deny($revenue->post_line($savedIn['line_id'],1),'post rechecks mixed currency');
$db->where('id',$standaloneAccount)->update('fin_company_account',['currency_code'=>'IDR']);
$ok($revenue->post_line($savedIn['line_id'],1),'post standalone IN transfer');
$check($balance()===$sum && (float)$db->get_where('fin_company_account',['id'=>$standaloneAccount])->row('current_balance')===$oldBank+3.,'standalone surplus transfers counter OUT primary IN');
$deny($revenue->save_line(array_replace($base,['reconciliation_id'=>$roundIn['id'],'actual_amount'=>'74']),1),'saved posted transfer cannot be overwritten');
$roundNone=$ok($revenue->create_round(['reconciliation_date'=>$today,'revenue_date'=>$today],1),'round for leave open')['header'];
$none=$ok($revenue->save_line(array_replace($base,['reconciliation_id'=>$roundNone['id'],'resolution_type'=>'NONE','actual_amount'=>'65','account_id'=>0]),1),'leave daily difference open without selected account');
$noneRow=$db->get_where('fin_revenue_reconciliation_line',['id'=>$none['line_id']])->row_array();
$check($noneRow['status']==='OPEN' && $noneRow['account_id']===null && empty($noneRow['counter_account_id']) && $balance()===$sum,'NONE stores reviewed difference only, no cash changes');
$deny($revenue->post_line($none['line_id'],1),'NONE cannot post a cash mutation');

$negativeRound=$ok($revenue->create_round(['reconciliation_date'=>$today,'revenue_date'=>$today],1),'round for negative destination balance')['header'];
$negative=$ok($revenue->save_line(array_replace($base,['reconciliation_id'=>$negativeRound['id'],'actual_amount'=>'71']),1),'save transfer to account with legacy negative balance');
$db->where('id',$standaloneAccount)->update('fin_company_account',['current_balance'=>-10]);
$negativeSum=$balance();
$ok($revenue->post_line($negative['line_id'],1),'incoming transfer reduces negative destination by exact amount');
$check($balance()===$negativeSum && (float)$db->get_where('fin_company_account',['id'=>$standaloneAccount])->row('current_balance')===-9.,'negative destination never clamped to create phantom cash');

// Ordinary IN/OUT must not be blocked by an unrelated/partial settlement case.
$mid=20;
foreach(Finance_mutation_policy::categories() as $category=>$option) {
    foreach($option['direction']==='BOTH'?['IN','OUT']:[$option['direction']] as $direction) {
        $mid++;
        $put('pos_payment_method',['id'=>$mid,'method_code'=>'CATEGORY-'.$mid,'method_name'=>'Category '.$mid,'method_type'=>'BANK','company_account_id'=>1]);
        $put('pos_payment',['id'=>$mid,'payment_no'=>'CATEGORY-'.$mid,'order_id'=>$mid,'payment_type'=>'FINAL','payment_status'=>'PAID','paid_at'=>$today.' 10:00:00']);
        $put('pos_payment_line',['payment_id'=>$mid,'line_no'=>1,'payment_method_id'=>$mid,'amount'=>100,'status'=>'PAID']);
        $trace=Finance_settlement_control::trace($db,$today,$mid);
        $control=$put('fin_settlement_control',['payment_method_id'=>$mid,'revenue_date'=>$today,'account_id'=>1,'due_date'=>$today,'expected_amount'=>100,'received_amount'=>30,'provider_reference'=>'DAILY-'.$mid,'source_fingerprint'=>$trace['source_fingerprint'],'settlement_complete'=>0]);
        $form=['reconciliation_id'=>$roundNone['id'],'reconciliation_date'=>$today,'revenue_date'=>$today,'payment_method_id'=>$mid,'account_id'=>1,'actual_amount'=>$direction==='IN'?'101':'99','resolution_type'=>$direction,'report_category'=>$category,'resolution_note'=>'Verified daily check'];
        $savedCategory=$ok($revenue->save_line($form,1),'save daily '.$category.' '.$direction);
        $beforeCategory=$balance();
        if(in_array($category,['PROMO_EXPENSE','PLATFORM_FEE'],true)){
            $deny($revenue->post_line($savedCategory['line_id'],1),'fee requires reference exactly as manual/cash '.$category);
            $check($balance()===$beforeCategory,'missing fee evidence cannot change cash');
            // Full evidence is explicitly optional for ordinary categories, mandatory for fees.
            $charge=$put('fin_settlement_charge',['settlement_id'=>$control,'document_no'=>'DAILY-'.$mid,'line_reference'=>'1','identity_hash'=>hash('sha256','DAILY-'.$mid),'category'=>$category,'direction'=>'OUT','charge_date'=>$today,'account_id'=>1,'amount'=>1,'notes'=>'verified fee','request_key'=>$key()]);
            $savedCategory=$ok($revenue->save_line($form+['settlement_control_id'=>$control,'settlement_charge_id'=>$charge],1),'save explicit fee reference '.$category);
            $deny($revenue->post_line($savedCategory['line_id'],1),'partial settlement cannot be treated as fee '.$category);
            $db->where('id',$control)->update('fin_settlement_control',['settlement_complete'=>1]);
        }
        $ok($revenue->post_line($savedCategory['line_id'],1),'post daily '.$category.' '.$direction);
        $postedRow=$db->get_where('fin_revenue_reconciliation_line',['id'=>$savedCategory['line_id']])->row_array();
        $mutation=$db->get_where('fin_account_mutation_log',['id'=>$postedRow['mutation_id']])->row_array();
        $check($mutation['report_category']===$category && $mutation['mutation_type']===$direction && (float)$mutation['amount']===1. && $balance()===$beforeCategory+($direction==='IN'?1.:-1.),'exact amount and category preserved '.$category.' '.$direction);
    }
}

// Legacy two-line transfer attribution must not be counted twice.
$legacyDay=date('Y-m-d',strtotime($today.' -1 day'));
$legacyHeader=$put('fin_revenue_reconciliation',['reconciliation_no'=>'LEGACY-PAIR','reconciliation_date'=>$today,'revenue_date'=>$legacyDay,'round_no'=>1]);
$legacyA=$put('fin_revenue_reconciliation_line',['reconciliation_id'=>$legacyHeader,'payment_method_id'=>4,'account_id'=>1,'status'=>'POSTED','resolution_type'=>'TRANSFER','counter_payment_method_id'=>5,'counter_account_id'=>2]);
$legacyB=$put('fin_revenue_reconciliation_line',['reconciliation_id'=>$legacyHeader,'payment_method_id'=>5,'account_id'=>2,'status'=>'POSTED','resolution_type'=>'TRANSFER','counter_payment_method_id'=>4,'counter_account_id'=>1]);
$legacyOut=$put('fin_account_mutation_log',['mutation_no'=>'LEGACY-OUT','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'OUT','amount'=>10,'ref_module'=>'FINANCE_TRANSFER','ref_table'=>'fin_revenue_reconciliation_line','ref_id'=>$legacyA]);
$legacyIn=$put('fin_account_mutation_log',['mutation_no'=>'LEGACY-IN','mutation_date'=>$today,'account_id'=>2,'mutation_type'=>'IN','amount'=>10,'ref_module'=>'FINANCE_TRANSFER','ref_table'=>'fin_revenue_reconciliation_line','ref_id'=>$legacyB]);
$db->where('id',$legacyA)->update('fin_revenue_reconciliation_line',['mutation_id'=>$legacyOut,'counter_mutation_id'=>$legacyIn]);
$db->where('id',$legacyB)->update('fin_revenue_reconciliation_line',['mutation_id'=>$legacyIn,'counter_mutation_id'=>$legacyOut]);
$legacyMap=$map->invoke($revenue,$legacyDay);
$check((float)$legacyMap[4]===-10. && (float)$legacyMap[5]===10.,'legacy two-line transfer counted only once per method');
$db->hideTransferSchema=true;
$deny($revenue->save_line($base,1),'missing transfer schema rejects writer safely');
$check(!$revenue->dashboard($today,$today)['transfer_ready'],'missing transfer schema keeps daily reader available');
$db->hideTransferSchema=false;
// Real PHP views, including both migration-off and migration-on variants; no HTTP/server access.
function html_escape($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function base_url($path=''): string { return '/'.$path; }
$renderer=new class {
    public $load;
    public $security;
    public function view($name,$data=[]): void {
        if($name==='finance/_tabs')return;
        extract($data); require APPPATH.'views/'.$name.'.php';
    }
};$renderer->load=$renderer;
$renderer->security=new class { public function get_csrf_token_name(){return 'fixture_csrf';} public function get_csrf_hash(){return str_repeat('c',64);} };
$uiBase=['page_title'=>'Synthetic','csrf'=>str_repeat('a',64),'can_approve'=>false,'can_settings'=>false,'can_void_modules'=>[],
    'error'=>'','date'=>$today,'method_id'=>1,'month'=>date('Y-m'),'days'=>7,'methods'=>$op->methods()];
$uiDir=null;
if(in_array('--export-ui',$argv,true)) {
    $uiDir=trim((string)shell_exec('mktemp -d /tmp/finance-allocation-ui-XXXXXX'));
    if(!preg_match('~\A/tmp/finance-allocation-ui-[a-zA-Z0-9]+\z~D',$uiDir)||!is_dir($uiDir))throw new RuntimeException('Fixture output unavailable.');
}
foreach(['settlement','cash-plan','bank-review'] as $tab) foreach([true,false] as $canEdit) {
    $result=$tab==='settlement'?$op->settlement($today,1):($tab==='cash-plan'?$op->forecast(7):$op->bank_rows(['account_id'=>1]));
    $data=$uiBase+['tab'=>$tab,'can_edit'=>$canEdit,'operations_ready'=>true,'allocation_ready'=>true,'result'=>$result,'operation_details'=>$op->details($case1),'accounts'=>$db->get('fin_company_account')->result_array()];
    ob_start();$renderer->view('finance/control',$data);$html=ob_get_clean();
    $check(!preg_match('/Warning:|Fatal error:|Parse error:/',$html),'PHP render '.$tab.' '.($canEdit?'edit':'readonly'));
    if(!$canEdit)$check(!preg_match('/data-save=|class="fc-allocation-form"|id="fc-bank-import"/',$html),'read-only hides all new writers '.$tab);
    if($tab==='bank-review')$check(str_contains($html,'Cocokkan bank'),'bank review discoverable as tab');
    if($uiDir)file_put_contents($uiDir.'/'.$tab.($canEdit?'':'-readonly').'.html',$html);
}
foreach([true,false] as $ready) {
    $data=$uiBase+['tab'=>'cash-plan','can_edit'=>true,'operations_ready'=>$ready,'allocation_ready'=>false,'result'=>$op->forecast(7)];
    ob_start();$renderer->view('finance/control',$data);$html=ob_get_clean();
    $check(!str_contains($html,'plan-allocate')&&!str_contains($html,'fc-bank-import'),'migration-off keeps legacy forms');
}
foreach([true,false] as $canEdit) {
    $data=['dashboard'=>$revenue->dashboard($today,$today),'can_reconcile_edit'=>$canEdit,'report_categories'=>Finance_mutation_policy::categories(),
        'save_url'=>'/fixture/save','post_url'=>'/fixture/post','round_create_url'=>'/fixture/round','reconciliation_csrf'=>str_repeat('b',64),'settlement_options'=>[]];
    ob_start();$renderer->view('finance/revenue_reconciliation',$data);$html=ob_get_clean();
    $check(str_contains($html,'Transfer antar rekening'),'revenue transfer option renders');
    if($uiDir)file_put_contents($uiDir.'/revenue'.($canEdit?'':'-readonly').'.html',$html);
    $data['dashboard']=$cash->dashboard($today);
    ob_start();$renderer->view('finance/cash_reconciliation',$data);$html=ob_get_clean();
    $check(!preg_match('/Warning:|Fatal error:/',$html),'cash reconciliation PHP render '.($canEdit?'edit':'readonly'));
    if($uiDir)file_put_contents($uiDir.'/cash'.($canEdit?'':'-readonly').'.html',$html);
}
$db->hideAllocationSchema=true;
$deny($op->allocate_plan($p,1),'missing schema rejects partial writer safely');
$deny($op->distribute_receipt($payload,1),'missing schema rejects receipt allocation safely');
$deny($op->import_statement($import,1),'missing schema rejects CSV import safely');
$profile=json_decode(file_get_contents($root.'/tools/release/customer_clean_profile.json'),true);
foreach(['Finance_allocation_policy','Finance_allocation_operations','Finance_bank_csv','Finance_bank_operations','Finance_revenue_transfer'] as $name)$check(in_array('application/libraries/'.$name.'.php',$profile['code_files'],true),'release includes required library '.$name);
$db->hideAllocationSchema=false;
require __DIR__.'/finance_reconciliation_reporting_cases.php';
finance_reconciliation_reporting_cases($db,$revenue,$cash,$op,$check,$put,$create,$today);
if($uiDir)echo 'UI_FIXTURE='.$uiDir.PHP_EOL;
echo 'Finance allocation/bank: '.$checks.' PASS (:memory: SQLite only). MariaDB migration/concurrency and real UI UAT not run.'.PHP_EOL;
