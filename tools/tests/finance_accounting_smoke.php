<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
$root=dirname(__DIR__,2);
define('BASEPATH',$root.'/system/');define('APPPATH',$root.'/application/');define('ENVIRONMENT','testing');
date_default_timezone_set('Asia/Jakarta');
function log_message($level,$message): void {}
function is_php($version): bool {return version_compare(PHP_VERSION,$version,'>=');}
function show_error($message='',$status=500): void {throw new RuntimeException((string)$message);}
function site_url($path=''): string {return '/'.$path;}
function base_url($path=''): string {return '/'.$path;}
$context=new stdClass();function &get_instance(){return $GLOBALS['context'];}
require BASEPATH.'core/Model.php';require BASEPATH.'database/DB.php';
$params=['dbdriver'=>'sqlite3','database'=>':memory:','db_debug'=>false,'save_queries'=>true];DB($params,true);
class AccountingMemoryDB extends CI_DB_sqlite3_driver
{
    public $failAudit=false;
    public function query($sql,$binds=false,$return_object=null)
    {
        if($this->failAudit&&preg_match('/^INSERT INTO ["`]?aud_transaction_log/i',$sql)){$this->_trans_status=false;return false;}
        return parent::query(preg_replace('/\s+FOR UPDATE\b/i','',$sql),$binds,$return_object);
    }
    public function reset_request(): void {$this->_trans_status=true;}
}
$db=new AccountingMemoryDB($params);$db->initialize();$context->db=$db;
$checks=0;$check=static function($ok,$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;};
$sqlRun=static function($sql)use($db):void{if(!$db->query($sql))throw new RuntimeException('Fixture query '.json_encode($db->error()));};
$create=static function(string $sql)use($sqlRun):void{
    if(!preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?\s*\((.*?)\) ENGINE/s',$sql,$m))throw new RuntimeException('Fixture DDL missing.');
    $body=preg_replace('/\benum\([^)]*\)/i','TEXT',$m[2]);$parts=preg_split('/,(?![^()]*\))/',$body);$columns=[];
    foreach($parts as $part){$part=trim($part);if(preg_match('/^(KEY|CONSTRAINT|FULLTEXT)\b/i',$part))continue;
        $part=preg_replace('/\bUNIQUE KEY\s+`?\w+`?\s*/i','UNIQUE ',$part);
        $part=preg_replace('/\b(bigint|int|tinyint|smallint)(\(\d+\))?(\s+unsigned)?/i','INTEGER',$part);
        $part=preg_replace('/\b(AUTO_INCREMENT|USING BTREE)\b/i','',$part);
        $part=preg_replace('/\s+ON UPDATE current_timestamp\(\)/i','',$part);$part=str_ireplace('current_timestamp()','CURRENT_TIMESTAMP',$part);
        $part=preg_replace('/\s+(COLLATE|CHARACTER SET)\s+\w+/i','',$part);
        $part=preg_replace('/\s+COMMENT\s+\x27[^\x27]*\x27/i','',$part);$columns[]=$part;
    }$sqlRun('CREATE TABLE '.$m[1].' ('.implode(',',$columns).')');
};
$baseline=file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql');
foreach(['fin_company_account','fin_account_mutation_log','fin_period_close','aud_transaction_log'] as $table){preg_match('/CREATE TABLE `'.$table.'`.*?;\n/s',$baseline,$m);$create($m[0]);}
$sqlRun('ALTER TABLE fin_account_mutation_log ADD COLUMN report_category TEXT');
$migration=file_get_contents($root.'/sql/2026-09-15a_finance_general_ledger.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS .*?;\n/s',$migration,$matches);foreach($matches[0] as $ddl)$create($ddl);
$sqlRun('INSERT INTO fin_gl_guard (id) VALUES (1)');
preg_match('/INSERT IGNORE INTO fin_gl_account.*?;/s',$migration,$seed);$sqlRun(str_replace('INSERT IGNORE','INSERT OR IGNORE',$seed[0]));
$db->data_cache=[];
require APPPATH.'models/Finance_accounting_model.php';$model=new Finance_accounting_model();
$put=static function($table,$row)use($db):int{if(!$db->insert($table,$row))throw new RuntimeException('Fixture insert '.json_encode($db->error()));return (int)$db->insert_id();};
$key=static fn()=>bin2hex(random_bytes(16));
$line=static fn($code,$d='0',$c='0')=>['account_code'=>$code,'debit'=>(string)$d,'credit'=>(string)$c];
$good=static function($p,$label)use($model,$check):array{$r=$model->post($p,1,'127.0.0.1');$check(!empty($r['ok']),$label.' '.($r['message']??''));return $r;};
$bad=static function($p,$reason)use($model,$check,$db):void{$before=$db->count_all('fin_gl_journal');$r=$model->post($p,1);$check(empty($r['ok'])&&str_contains($r['message'],$reason),'reject '.$reason);$check($before===$db->count_all('fin_gl_journal'),'rejection writes no journal');$db->reset_request();};
$throws=static function($fn,$label)use($check):void{try{$fn();$thrown=false;}catch(Throwable $e){$thrown=true;}$check($thrown,$label);};
$new=static fn($kind,$date,$lines)=>['kind'=>$kind,'date'=>$date,'reference'=>'Synthetic proof','memo'=>'Verified synthetic document','request_key'=>bin2hex(random_bytes(16)),'lines'=>$lines];
foreach([[1,'CASH','IDR',1000,1],[2,'BANK','IDR',500,0],[3,'BANK','USD',99,1]] as [$id,$type,$currency,$amount,$active])$put('fin_company_account',['id'=>$id,'account_code'=>'TEST-'.$id,'account_name'=>'Synthetic <img src=x> '.$id,'account_type'=>$type,'currency_code'=>$currency,'opening_balance'=>$amount,'current_balance'=>$amount,'is_active'=>$active]);
$check($model->ready(),'journal schema ready in isolated memory');
$check(count($model->accounts())===24,'reference COA imported from actual SQL');
$check(Finance_journal_policy::cents('0.10')+Finance_journal_policy::cents('0.20')===30,'exact integer cents');
foreach(['1e3','1,000.00','1.001',[],null,'NaN'] as $value)$throws(static fn()=>Finance_journal_policy::cents($value),'invalid amount rejected');
$throws(static fn()=>Finance_journal_policy::date('2026-02-30'),'invalid date rejected');
$bad($new('ADJUSTMENT','2026-07-01',[$line('1300',10),$line('3100',0,10)]),'Siapkan saldo awal');
$preview=$model->preview('OPENING',0,'2026-06-30');
$opening=$new('OPENING','2026-06-30',[$line('1300',200),$line('3100',0,1700)])+['source_hash'=>$preview['source_hash']];
$bad(array_replace($opening,['source_hash'=>str_repeat('0',64)]),'Saldo awal kas berubah');
$first=$good($opening,'opening cash plus inventory/equity balanced');
$check($good($opening,'request retry')['id']===$first['id'],'retry returns same journal');
$bad(array_replace($opening,['memo'=>'Changed']),'isi berbeda');
$bad(array_replace($opening,['request_key'=>$key()]),'Saldo awal sudah ada');
$bad($new('ADJUSTMENT','2026-06-30',[$line('1300',10),$line('3100',0,10)]),'setelah tanggal saldo awal');
$bad($new('ADJUSTMENT','2026-07-01',[$line('5200',10),$line('1300',0,9)]),'belum seimbang');
$bad($new('ADJUSTMENT','2026-07-01',[$line('5200',10,10),$line('1300',0,10)]),'debit ATAU kredit');
$bad($new('ADJUSTMENT','2026-07-01',[$line('XXXX',10),$line('1300',0,10)]),'akun akuntansi');
$bad($new('ADJUSTMENT','2026-07-01',[['account_code'=>'1100','company_account_id'=>1,'debit'=>'10','credit'=>'0'],$line('3100',0,10)]),'Baris manual');
$bad($new('ADJUSTMENT','2099-07-01',[$line('1300',10),$line('3100',0,10)]),'masa depan');
$nextSource=1;
$source=static function($type,$amount,$module,$category=null,$date='2026-07-10',$account=1,$reversal=null)use($put,$db,&$nextSource):int{
    $n=$nextSource++;$before=(float)$db->get_where('fin_company_account',['id'=>$account])->row('current_balance');$after=$before+($type==='IN'?1:-1)*(float)$amount;
    $id=$put('fin_account_mutation_log',['mutation_no'=>'TEST-M-'.$n,'mutation_date'=>$date,'account_id'=>$account,'mutation_type'=>$type,'amount'=>$amount,'balance_before'=>$before,'balance_after'=>$after,'ref_module'=>$module,'ref_table'=>'synthetic_source','ref_id'=>$n,'report_category'=>$category,'reversal_of_mutation_id'=>$reversal]);
    $db->where('id',$account)->update('fin_company_account',['current_balance'=>$after]);return $id;
};
$mutationForm=static function($id,$lines,$flow='OPERATING')use($model,$new):array{
    $v=$model->preview('MUTATION',$id,'2026-07-01');return $new('MUTATION',$v['date'],$lines)+['source_id'=>$id,'source_hash'=>$v['source_hash'],'cashflow_class'=>$flow];
};
$sale=$source('IN',110,'POS');$purchase=$source('OUT',50,'PURCHASE');$fee=$source('OUT',10,'REVENUE_RECON','PLATFORM_FEE');
$capital=$source('IN',30,'FINANCE','OWNER_CAPITAL');$drawing=$source('OUT',5,'FINANCE','OWNER_DRAWING');
$loan=$source('IN',40,'FINANCE_PAYABLE');$deposit=$source('IN',20,'POS');$collection=$source('IN',15,'FINANCE_RECEIVABLE');
$salary=$source('OUT',12,'PAYROLL');$surplus=$source('IN',4,'FINANCE_RECON','CASH_SURPLUS');$unknown=$source('OUT',3,'LEGACY_UNKNOWN');
$out=$source('OUT',25,'FINANCE_TRANSFER');$in=$source('IN',25,'FINANCE_TRANSFER',null,'2026-07-10',2);
$usd=$source('IN',2,'FINANCE',null,'2026-07-10',3);
$cash=$model->cash_report('2026-07');
$check(count($cash['rows'])===2 && $cash['mismatch']===0,'inactive IDR included, foreign currency excluded, cash history matches');
$check($cash['total']['closing']===$cash['total']['opening']+$cash['total']['inflow']-$cash['total']['outflow'],'actual cash equation includes every raw source');
$check($cash['groups'][0]['flow_class']==='UNCLASSIFIED'&&array_sum(array_column($cash['groups'],'inflow'))===$cash['total']['inflow'],'unclassified cash never omitted');
$check($model->queue('2026-07')['count']===13,'all IDR source types appear in journal queue');
$throws(static fn()=>$model->preview('MUTATION',$usd,'2026-07-01'),'foreign currency journal rejected');
$check($model->preview('MUTATION',$sale,'2026-07-01')['suggestion']===null,'POS not blindly recognized as revenue');
$check($model->preview('MUTATION',$purchase,'2026-07-01')['suggestion']===null,'purchase not blindly recognized as expense');
$check($model->preview('MUTATION',$fee,'2026-07-01')['suggestion']==='5400','fee suggestion explicit');
$bad(array_replace($mutationForm($sale,[$line('4100',0,110)]),['source_hash'=>str_repeat('0',64)]),'Mutasi berubah');
$bad($mutationForm($sale,[$line('4100',0,110)],'TRANSFER'),'Kelompok transfer');
$beforeBalances=$db->get('fin_company_account')->result_array();$beforeMutations=$db->get('fin_account_mutation_log')->result_array();
foreach([
 [$sale,[$line('4100',0,100),$line('2300',0,10)],'OPERATING'],[$purchase,[$line('1300',50)],'OPERATING'],
 [$fee,[$line('5400',10)],'OPERATING'],[$capital,[$line('3100',0,30)],'FINANCING'],[$drawing,[$line('3300',5)],'FINANCING'],
 [$loan,[$line('2500',0,40)],'FINANCING'],[$deposit,[$line('2200',0,20)],'OPERATING'],[$collection,[$line('1200',0,15)],'OPERATING'],
 [$salary,[$line('2400',12)],'OPERATING'],[$surplus,[$line('4200',0,4)],'OPERATING'],[$unknown,[$line('5200',3)],'OPERATING'],
 [$out,[],'TRANSFER'],[$in,[],'TRANSFER'],
] as [$id,$lines,$flow])$good($mutationForm($id,$lines,$flow),'source journal '.$id);
$check($beforeBalances===$db->get('fin_company_account')->result_array() && $beforeMutations===$db->get('fin_account_mutation_log')->result_array(),'posting all journals never writes business cash or source data');
$bad($mutationForm($sale,[$line('4100',0,110)]),'sudah dijurnal');
$check($model->queue('2026-07')['count']===0,'source coverage complete');
$good($new('ADJUSTMENT','2026-07-11',[$line('1200',15),$line('4100',0,15)]),'credit sale recognition');
$good($new('ADJUSTMENT','2026-07-11',[$line('5500',12),$line('2400',0,12)]),'salary accrued without second cash posting');
$good($new('ADJUSTMENT','2026-07-11',[$line('5100',35),$line('1300',0,35)]),'HPP distinct from cash purchase');
$depreciation=$good($new('ADJUSTMENT','2026-07-11',[$line('5700',2),$line('1490',0,2)]),'depreciation recognized noncash');
$report=$model->statements('2026-07');$check($report['sum']['profit']===5700,'P&L uses recognition, not all cash inflow/outflow');
$check($report['sum']['trial_difference']===0&&$report['sum']['balance_difference']===0,'trial balance and accounting equation balanced');
$check($report['sum']['total_equity']===178200,'equity movement reconciles capital drawings prior earnings profit');
$check(!$report['cashDifference']&&$report['pending']===0&&$report['stale']===0,'GL cash equals raw cash and all sources covered');
$check(array_column($report['rows'],null,'code')['1190']['closing']===0,'internal transfer clears without profit impact');
$check($model->ledger('2026-07','1100')['count']===13,'cash general ledger lists all cash journal lines');
$check($model->journal_list('2026-07',1,$depreciation['id'])['detail']['id']==$depreciation['id'],'journal detail available');
$july=$model->cash_report('2026-07');
$reversal=$source('IN',10,'FINANCE',null,'2026-08-01',1,$fee);
$good($mutationForm($reversal,[$line('4100',0,10)]),'cash reversal uses original accounts, ignores conflicting suggestion');
$check($model->cash_report('2026-07')['total']===$july['total'],'August VOID does not erase July actual cash');
$check($model->cash_report('2026-08')['total']['inflow']===1000,'August reversal included on actual date');
$check($model->statements('2026-07')['sum']['profit']===5700,'August journal does not rewrite July accrual report');
$check($model->statements('2026-08')['sum']['profit']===1000,'fee reversal reduces expense, not fake sales');
$rev=$new('REVERSAL','2026-08-02',[])+['journal_id'=>$depreciation['id']];$good($rev,'reverse noncash journal');
$bad(array_replace($rev,['request_key'=>$key()]),'sudah dibalik');
$bad($new('REVERSAL','2026-08-02',[])+['journal_id'=>$first['id']],'hanya untuk penyesuaian');
$check($model->statements('2026-08')['sum']['profit']===1200,'noncash reversal reflected in proper period');
$auditForm=$new('ADJUSTMENT','2026-08-03',[$line('5200',1),$line('2100',0,1)]);
$db->failAudit=true;$bad($auditForm,'Jurnal gagal');$db->failAudit=false;$db->reset_request();
$good($auditForm,'retry after audit rollback');
$sqlRun("INSERT INTO fin_period_close (period_code,period_year,period_month,period_start,period_end,status) VALUES ('TEST-CLOSE',2026,8,'2026-08-01','2026-08-31','CLOSED')");
$bad($new('ADJUSTMENT','2026-08-04',[$line('5200',1),$line('2100',0,1)]),'Periode jurnal');
$db->where('id',$sale)->update('fin_account_mutation_log',['report_category'=>'CHANGED']);
$check($model->statements('2026-07')['stale']===1,'source change surfaced rather than rewriting journal');
$db->where('id',$sale)->update('fin_account_mutation_log',['report_category'=>null]);
$late=$source('OUT',1,'UNKNOWN_LATE',null,'2026-07-31');
$report=$model->statements('2026-08');$check($report['pending']===1&&count($report['cashDifference'])===1,'earlier unjournaled cash visible in later month completeness');
$db->where('id',1)->update('fin_company_account',['current_balance'=>999]);
$check($model->cash_report('2026-07')['mismatch']===1,'live balance drift independently detected');
$check(!preg_match('/(?:UPDATE|DELETE FROM)\s+(?:fin_company_account|fin_account_mutation_log)\b/i',file_get_contents(APPPATH.'models/Finance_accounting_model.php')),'production model contains no source cash writes');
$check(str_contains(file_get_contents(APPPATH.'controllers/Finance_accounting.php'),"require_permission(self::PAGE,'create')"),'posting requires journal permission');
$check(str_contains($migration,"'finance.accounting.index'")&&str_contains($migration,"'grp.finance'"),'sidebar and RBAC prepared in migration');
$check(!preg_match('/\b(DROP|TRUNCATE|UPDATE|DELETE)\s+(TABLE|FROM|fin_)\b/i',$migration),'migration additive metadata only');
$check(count($model->preview('MUTATION',$reversal,'2026-08-01')['fixed'])===2&&$model->preview('MUTATION',$reversal,'2026-08-01')['auto_counter'],'reversal preview shows server-derived counter line');
$check($model->preview('MUTATION',$out,'2026-07-10')['auto_counter'],'transfer preview no arbitrary counter required');

require __DIR__.'/finance_accounting_setup_cases.php';

// Exercise actual controller guards without booting the app/config or opening sockets.
class MY_Controller
{
    public $load,$input,$session,$output,$Finance_accounting_model,$rendered;
    protected $current_user=['id'=>1];
    public $allowed=true,$permissions=[];
    public function __construct(){}
    protected function can($page,$action='view'){return $this->allowed&&($this->permissions[$page.'.'.$action]??true);}
    protected function require_permission($page,$action='view'){if(!$this->can($page,$action))throw new RuntimeException('Permission denied');}
    protected function render($view,$data){$this->rendered=$data;}
}
require APPPATH.'controllers/Finance_accounting.php';
$rc=new ReflectionClass('Finance_accounting');$controller=$rc->newInstanceWithoutConstructor();
$controller->input=new class{
    public $http='POST',$header,$raw_input_stream='{}';
    public function method($upper=true){return $this->http;}
    public function get_request_header($key,$x=false){return $this->header;}
    public function ip_address(){return '127.0.0.1';}
};
$controller->session=new class{public $token;public function userdata($key){return $this->token;}};
$controller->output=new class{
    public $code,$body;
    public function set_status_header($v){$this->code=$v;return $this;}
    public function set_header($v){return $this;}
    public function set_content_type($v){return $this;}
    public function set_output($v){$this->body=$v;return $this;}
};
$controller->Finance_accounting_model=new class{public $calls=0,$setupCalls=0;public function post($p,$actor,$ip){$this->calls++;return ['ok'=>true,'id'=>1];}public function save_setup($kind,$p,$actor,$ip){$this->setupCalls++;return ['ok'=>true];}};
$controller->session->token=str_repeat('a',64);$controller->input->header=str_repeat('a',64);
$controller->allowed=false;$throws(static fn()=>$controller->post(),'controller RBAC denies unauthorized writer');$controller->allowed=true;
$controller->input->http='GET';$controller->post();$check($controller->output->code===403,'GET cannot post journal');
$controller->input->http='POST';$controller->input->header='bad';$controller->post();$check($controller->output->code===403,'mismatched CSRF rejected');
$controller->input->header=str_repeat('a',64);$controller->input->raw_input_stream=str_repeat('x',65537);$controller->post();$check($controller->output->code===400,'oversized input rejected');
$controller->input->raw_input_stream='no JSON';$controller->post();$check($controller->output->code===400,'malformed JSON rejected');
$check($controller->Finance_accounting_model->calls===0,'denied HTTP requests never invoke writer');
$controller->input->raw_input_stream='{}';$controller->post();$check($controller->output->code===200&&$controller->Finance_accounting_model->calls===1,'valid session routes through guarded writer');
$controller->permissions['finance.accounting.settings.edit']=false;
$throws(static fn()=>$controller->save_setup('account'),'journal poster cannot change configuration without separate permission');
$controller->permissions['finance.accounting.settings.edit']=true;$controller->permissions['finance.accounting.index.view']=false;
$throws(static fn()=>$controller->save_setup('account'),'configuration writer requires accounting view access');
$controller->permissions['finance.accounting.index.view']=true;
$controller->input->http='GET';$controller->save_setup('account');$check($controller->output->code===403,'GET cannot save configuration');
$controller->input->http='POST';$controller->input->header='bad';$controller->save_setup('account');$check($controller->output->code===403,'setup rejects missing/mismatched CSRF');
$controller->input->header=str_repeat('a',64);$controller->input->raw_input_stream=str_repeat('x',16385);$controller->save_setup('account');$check($controller->output->code===400,'setup limits payload');
$controller->input->raw_input_stream='bad';$controller->save_setup('account');$check($controller->output->code===400,'setup rejects invalid JSON');
$controller->input->raw_input_stream='{}';$controller->save_setup('invalid');$check($controller->output->code===400,'setup rejects unrecognized action');
$check($controller->Finance_accounting_model->setupCalls===0,'setup denied requests never reach model');
$controller->save_setup('mapping');$check($controller->output->code===200&&$controller->Finance_accounting_model->setupCalls===1,'authorized setup uses protected writer');

$renderer=new class{
    public $load;
    public function __construct(){$this->load=new class{public function view($name,$data){if(in_array($name,['finance/accounting_settings','finance/accounting_guide'],true)){extract($data,EXTR_SKIP);include APPPATH.'views/'.$name.'.php';}}};}
    public function render($data){extract($data,EXTR_SKIP);ob_start();include APPPATH.'views/finance/accounting.php';return ob_get_clean();}
};
$ui=[];
set_error_handler(static function($severity,$message,$file,$line){throw new RuntimeException("Render error: $message ($line)");});
try {
    foreach([true,false] as $canPost)foreach(['cash','queue','journals','ledger','trial','profit','balance','equity','guide','settings','settings-missing','entry','entry-assistant','entry-transfer','entry-reversal','entry-opening'] as $variant){
        if(str_starts_with($variant,'entry')&&!$canPost)continue;
        $tab=str_starts_with($variant,'entry')?'entry':(str_starts_with($variant,'settings')?'settings':$variant);
        $data=['tab'=>$tab,'month'=>'2026-07','currency'=>'IDR','csrf'=>str_repeat('a',64),'ready'=>true,'can_post'=>$canPost,'error'=>'',
            'accounts'=>$model->accounts(),'opening'=>$model->opening(),'currencies'=>['IDR','USD'],'result'=>[], 'can_view_setup'=>true,'can_setup'=>$canPost];
        if($tab==='cash')$data['result']=$model->cash_report('2026-07');
        elseif($tab==='queue')$data['result']=$model->queue('2026-07');
        elseif($tab==='journals')$data['result']=$model->journal_list('2026-07',1,$depreciation['id']);
        elseif($tab==='ledger')$data['result']=$model->ledger('2026-07','1100');
        elseif($tab==='entry')$data['result']=match($variant){'entry-assistant'=>$model->preview('MUTATION',$expenseSource,'2026-07-10'),'entry-transfer'=>$model->preview('MUTATION',$out,'2026-07-10'),'entry-reversal'=>$model->preview('MUTATION',$reversal,'2026-08-01'),'entry-opening'=>$model->preview('OPENING',0,'2026-06-30'),default=>$model->preview('ADJUSTMENT',0,'2026-07-12')};
        elseif($tab==='settings')$data['result']=$variant==='settings-missing'?$setupMissingData:$model->setup_data();
        elseif($tab!=='guide')$data['result']=$model->statements('2026-07');
        $html=$renderer->render($data);$ui[$variant.($canPost?'':'-readonly')]=$html;
        $check(str_contains($html,'gl-workspace'),'render '.$variant.($canPost?'':' read-only'));
        $check(!str_contains($html,'<img src=x>'),'stored account names escaped '.$variant);
        if(!$canPost)$check(!str_contains($html,'id="gl-entry"')&&!str_contains($html,'id="gl-reverse"'),'read-only has no writer forms '.$variant);
        if($tab==='settings')$check((bool)preg_match('/<button[^>]*>Simpan pemetaan<\/button>/',$html)===($canPost&&$variant==='settings'),'settings writes require permission and migration '.$variant);
        if($tab==='guide')$check(str_contains($html,'Kamus singkat')&&str_contains($html,'Rp100.000')&&str_contains($html,'Admin server'),'real beginner guide contains roles glossary and worked examples');
        if($variant==='entry-assistant')$check(str_contains($html,'id="gl-assistant-confirm"')&&str_contains($html,'data-hash='),'real assistant options require confirmation and mapping fingerprint');
    }
    $data['tab']='trial';$data['error']='SQL 2026-09-15a belum aktif';$data['result']=[];
    $html=$renderer->render($data);$check(str_contains($html,'SQL 2026-09-15a belum aktif'),'schema missing is understandable, no PHP error');
    $check(str_contains($html,'month=2026-07'),'view navigation preserves controller-selected month');
    unset($data['month']);
    $fallbackHtml=$renderer->render($data);
    $check(str_contains($fallbackHtml,'month='.date('Y-m')),'view has explicit current-month fallback without undefined variable warning');
} finally {restore_error_handler();}
$db->trans_begin();
// Empty-install branch on the same disposable :memory: fixture only; rollback restores it.
if($db->database!==':memory:')throw new RuntimeException('Memory fixture required.');
foreach(['fin_gl_line','fin_gl_journal','fin_account_mutation_log','fin_period_close'] as $table)$sqlRun('DELETE FROM '.$table);
$sqlRun('UPDATE fin_company_account SET opening_balance=0,current_balance=0');
$zero=$model->preview('OPENING',0,'2026-06-30');
$good($new('OPENING','2026-06-30',[])+['source_hash'=>$zero['source_hash']],'zero opening supported for clean business');
$check($model->journal_list('2026-06')['count']===1&&count($model->journal_list('2026-06')['rows'])===1,'zero opening remains in immutable journal history');
$check($model->statements('2026-07')['sum']['balance_difference']===0,'zero opening statements balanced');
$db->trans_rollback();$db->reset_request();
echo 'Finance accounting: '.$checks.' PASS (SQLite :memory: only; no live DB, DDL/locking/UAT not validated).'.PHP_EOL;
if(in_array('--render-json',$argv,true))echo 'GL_UI_JSON='.json_encode($ui,JSON_THROW_ON_ERROR).PHP_EOL;
