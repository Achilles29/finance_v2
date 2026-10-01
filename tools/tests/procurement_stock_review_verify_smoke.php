<?php
declare(strict_types=1);
// Actual verification/normalization/identity code, SQLite transaction fixture.
// External PO creation is a failure-injectable boundary; no application bootstrap/live DB.
$root=dirname(__DIR__,2);define('BASEPATH',$root.'/system/');
set_error_handler(static function($n,$m,$f,$l):void{throw new ErrorException($m,0,$n,$f,$l);});
class CI_Model {public $db,$load,$session,$Purchase_model,$itemidentityresolver;}
function &get_instance(){return $GLOBALS['reviewModel'];}
require $root.'/application/libraries/ItemIdentityResolver.php';
require $root.'/application/models/Procurement_model.php';
class ReviewVerifyResult {
    public function __construct(private array $rows){}
    public function row_array():array{return $this->rows[0]??[];}
    public function result_array():array{return $this->rows;}
}
class ReviewVerifyDb {
    public PDO $pdo;public string $database='fixture';public int $begins=0;public $onBegin=null;
    public bool $failBegin=false,$failCommit=false,$failEvidence=false;private string $table='', $select='*';private array $where=[];
    public function __construct(){
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('FIELD',static fn($value,...$choices)=>array_search($value,$choices,true)+1,-1);
    }
    public function query($sql,$params=[]){
        $q=$this->pdo->prepare(str_replace(' FOR UPDATE','',$sql));$q->execute($params);
        return new ReviewVerifyResult($q->fetchAll(PDO::FETCH_ASSOC));
    }
    public function table_exists($table):bool{return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array();}
    public function field_exists($field,$table):bool{return in_array($field,array_column($this->query('PRAGMA table_info('.$table.')')->result_array(),'name'),true);}
    public function from($table){$this->table=$table;return $this;}
    public function select($select,$escape=true){$this->select=$select;return $this;}
    public function where($key,$value){$this->where[$key]=$value;return $this;}
    public function limit($limit){return $this;}
    private function reset():void{$this->table='';$this->select='*';$this->where=[];}
    private function condition():string{return $this->where?' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.'=?',array_keys($this->where))):'';}
    public function get(){
        if($this->table==='information_schema.COLUMNS'){$this->reset();return new ReviewVerifyResult([['IS_NULLABLE'=>'YES']]);}
        $q=$this->query('SELECT '.$this->select.' FROM '.$this->table.$this->condition(),array_values($this->where));$this->reset();return $q;
    }
    public function delete($table){$this->query('DELETE FROM '.$table.$this->condition(),array_values($this->where));$this->reset();return true;}
    public function update($table,$values){
        $this->query('UPDATE '.$table.' SET '.implode(',',array_map(static fn($k)=>$k.'=?',array_keys($values))).$this->condition(),array_merge(array_values($values),array_values($this->where)));$this->reset();return true;
    }
    public function insert($table,$values){
        if($this->failEvidence && $table==='pur_division_stock_review')return false;
        $this->query('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));return true;
    }
    public function escape($v):string{return $this->pdo->quote($v);}
    public function trans_begin():bool{$this->begins++;if($this->failBegin)return false;$this->pdo->beginTransaction();if($this->onBegin)($this->onBegin)($this);return true;}
    public function trans_rollback():bool{if($this->pdo->inTransaction())$this->pdo->rollBack();return true;}
    public function trans_commit():bool{return !$this->failCommit && $this->pdo->commit();}
    public function trans_status():bool{return true;}
}
class ReviewVerifySession {
    private array $data=[];
    public function userdata($key){return $this->data[$key]??null;}
    public function set_userdata($key,$value):void{$this->data[$key]=$value;}
}
class ReviewVerifyLoader {
    public function __construct(private $model){}
    public function database():void{}
    public function library($name):void{$this->model->itemidentityresolver=new ItemIdentityResolver();}
    public function model($name):void{}
}
class ReviewVerifyPurchase {
    public bool $fail=false;public int $calls=0;
    public function __construct(private $db){}
    public function store_order_with_lines($header,$lines,$user,$ip):array{
        $id=91+$this->calls++;$this->db->insert('fixture_po',['id'=>$id,'vendor_id'=>$header['vendor_id']]);
        return $this->fail?['ok'=>false,'message'=>'synthetic failure']:['ok'=>true,'data'=>['purchase_order_id'=>$id,'po_no'=>'PO-FIXTURE']];
    }
}
function fixture():array{
    $db=new ReviewVerifyDb();$db->pdo->exec("CREATE TABLE pur_division_request(id INTEGER PRIMARY KEY,request_no TEXT,request_date TEXT,needed_date TEXT,division_id INTEGER,destination_type TEXT,status TEXT,updated_at TEXT,notes TEXT);
        INSERT INTO pur_division_request VALUES(12,'REQ-FIXTURE','2026-09-16',NULL,1,'BAR','SUBMITTED','2026-09-16 09:00:00',NULL);
        CREATE TABLE pur_division_request_link(id INTEGER PRIMARY KEY,request_id INTEGER,doc_type TEXT,doc_id INTEGER,notes TEXT,created_at TEXT);
        CREATE TABLE pur_store_request(id INTEGER);CREATE TABLE pur_store_request_line(id INTEGER);
        CREATE TABLE mst_operational_division(id INTEGER,code TEXT,name TEXT);INSERT INTO mst_operational_division VALUES(1,'BAR','BAR');
        CREATE TABLE mst_purchase_type(id INTEGER,type_code TEXT,is_active INTEGER);INSERT INTO mst_purchase_type VALUES(1,'INV_STOK',1);
        CREATE TABLE mst_uom(id INTEGER,code TEXT);INSERT INTO mst_uom VALUES(1,'GR');
        CREATE TABLE auth_user(id INTEGER,username TEXT);INSERT INTO auth_user VALUES(7,'Verifier');
        CREATE TABLE fixture_po(id INTEGER PRIMARY KEY,vendor_id INTEGER);
        CREATE TABLE pur_division_stock_review(id INTEGER PRIMARY KEY,request_id INTEGER UNIQUE,reviewed_by INTEGER,reviewed_at TEXT,source_ip TEXT,confirmed_with TEXT,reason TEXT,snapshot_hash TEXT,snapshot_json TEXT);");
    $columns=['request_id','line_no','line_kind','item_id','material_id','profile_key','profile_name','profile_brand','profile_description','profile_expired_date','buy_uom_id','content_uom_id','profile_content_per_buy','profile_buy_uom_code','profile_content_uom_code','qty_buy_requested','qty_content_requested','qty_content_available_snapshot','routed_to','qty_content_to_sr','qty_content_to_po','notes','created_at','usage_purpose','request_uom_mode','vendor_id','estimated_unit_price'];
    $db->pdo->exec('CREATE TABLE pur_division_request_line(id INTEGER PRIMARY KEY,'.implode(',',array_map(static fn($c)=>$c.' TEXT',$columns)).')');
    $db->pdo->exec("INSERT INTO pur_division_request_line(request_id,profile_name) VALUES(12,'original line')");
    $model=new Procurement_model();$GLOBALS['reviewModel']=$model;$model->db=$db;$model->load=new ReviewVerifyLoader($model);
    $model->session=new ReviewVerifySession();$model->Purchase_model=new ReviewVerifyPurchase($db);
    $header=$db->query('SELECT * FROM pur_division_request')->row_array();
    $lines=[['source_type'=>'MANUAL','usage_purpose'=>'BAHAN_BAKU','profile_name'=>'Material baru fixture','profile_key'=>'manual-fixture',
        'buy_uom_id'=>1,'content_uom_id'=>1,'profile_content_per_buy'=>1,'request_uom_mode'=>'CONTENT','qty_content_requested'=>500,'vendor_id'=>2]];
    $preview=$model->preview_division_stock(12,$header,$lines,7);
    if(empty($preview['ok']))throw new RuntimeException(json_encode($preview));
    $header['stock_review']=['token'=>$preview['data']['token'],'confirmed'=>true,'confirmed_with'=>'Kepala BAR','reason'=>'Stok fisik dikonfirmasi untuk event besok'];
    return [$model,$db,$header,$lines];
}
$checks=0;$check=static function($ok,$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;};
$count=static fn($db,$table)=>(int)$db->query('SELECT COUNT(*) AS n FROM '.$table)->row_array()['n'];
foreach(['success','no-token','no-reason','changed-line','concurrent-status','concurrent-revision','po-failed','evidence-failed','begin-failed','commit-failed','missing-schema'] as $case){
    [$m,$db,$header,$lines]=fixture();
    if($case==='no-token')unset($header['stock_review']['token']);
    if($case==='no-reason')$header['stock_review']['reason']='';
    if($case==='changed-line')$lines[0]['qty_content_requested']=600;
    if($case==='concurrent-status')$db->onBegin=static fn($d)=>$d->pdo->exec("UPDATE pur_division_request SET status='VERIFIED'");
    if($case==='concurrent-revision')$db->onBegin=static fn($d)=>$d->pdo->exec("UPDATE pur_division_request SET updated_at='2026-09-16 10:00:00'");
    if($case==='po-failed')$m->Purchase_model->fail=true;
    if($case==='evidence-failed')$db->failEvidence=true;
    if($case==='begin-failed')$db->failBegin=true;
    if($case==='commit-failed')$db->failCommit=true;
    if($case==='missing-schema')$db->pdo->exec('DROP TABLE pur_division_stock_review');
    $result=$m->verify_division_request(12,$header,$lines,7,'127.0.0.1');$success=$case==='success';
    $check($result['ok']===$success,$case.' result');
    $check($count($db,'fixture_po')===($success?1:0),$case.' PO boundary atomic');
    $check($count($db,'pur_division_request_link')===($success?1:0),$case.' link atomic');
    if($case!=='missing-schema')$check($count($db,'pur_division_stock_review')===($success?1:0),$case.' evidence atomic');
    $check($db->query('SELECT status FROM pur_division_request')->row_array()['status']===($success?'VERIFIED':'SUBMITTED'),$case.' status atomic');
    $check(!$db->pdo->inTransaction(),$case.' no open transaction');
    if($success){
        $history=$m->stock_review_history('PO',91);$check(count($history)===1 && $history[0]['confirmed_with']==='Kepala BAR','linked PO carries actual approval');
        $check(count($m->stock_review_history('SR',91))===0,'PO ID must not match SR same numeric ID');
        $check(!$m->verify_division_request(12,$header,$lines,7)['ok'] && $count($db,'pur_division_stock_review')===1,'repeat verification rejected');
    }else{
        $check($db->query('SELECT profile_name FROM pur_division_request_line')->row_array()['profile_name']==='original line',$case.' prior lines preserved');
    }
}
[$m,$db,$header,$lines]=fixture();$lines[0]['usage_purpose']='OPERASIONAL';unset($header['stock_review']);$db->pdo->exec('DROP TABLE pur_division_stock_review');
$check($m->verify_division_request(12,$header,$lines,7)['ok'],'operational-only manual request still verifies without new schema');

// Exercise actual controller POST/CSRF/scope/payload gates without constructing CI.
class MY_Controller {
    public $db,$input,$output,$session,$Procurement_model,$Purchase_model,$load;
    public bool $allowed=false;
    public array $current_user=['id'=>7,'employee_id'=>3,'role_code'=>'BARISTA'];
    public function can($page,$action):bool{return $this->allowed;}
}
require $root.'/application/controllers/Procurement.php';
class ReviewHttpInput {
    public string $verb='POST',$raw_input_stream='',$csrf='';public $formToken='';
    public function method($uppercase=false){return $uppercase?$this->verb:strtolower($this->verb);}
    public function get_request_header($name,$filter){return $this->csrf;}
    public function post($name,$filter){return $this->formToken;}
}
class ReviewHttpOutput {
    public int $status=200;public string $body='';public array $headers=[];
    public function set_header($header){$this->headers[]=$header;return $this;}
    public function set_status_header($status){$this->status=$status;return $this;}
    public function set_content_type($type){return $this;}
    public function set_output($body){$this->body=$body;return $this;}
}
class ReviewHttpDb {
    public bool $db_debug=true;public ?array $request=['division_id'=>1,'updated_at'=>'server revision'];
    public function __call($name,$args){if($name==='get')return new ReviewVerifyResult([['division_code'=>'BAR']]);return $this;}
    public function query($sql,$params){return new ReviewVerifyResult($this->request?[$this->request]:[]);}
}
class ReviewHttpModels {
    public int $previews=0;public bool $fail=false;public array $received=[];
    public function list_active_operational_divisions(){return [['id'=>1,'code'=>'BAR'],['id'=>2,'code'=>'KITCHEN']];}
    public function build_destination_guard_map($options){return [1=>['BAR','BAR_EVENT'],2=>['KITCHEN']];}
    public function preview_division_stock(...$args){$this->previews++;$this->received=$args;if($this->fail)throw new RuntimeException('sensitive fixture details');return ['ok'=>true,'data'=>[]];}
    public function preview_manual_stock(...$args){$this->previews++;if($this->fail)throw new RuntimeException('sensitive fixture details');return ['rows'=>[]];}
}
$http=(new ReflectionClass(Procurement::class))->newInstanceWithoutConstructor();
$http->db=new ReviewHttpDb();$http->input=new ReviewHttpInput();$http->session=new ReviewVerifySession();
$http->Procurement_model=$http->Purchase_model=new ReviewHttpModels();$csrf=str_repeat('a',64);
$http->session->set_userdata('procurement_mutation_csrf',$csrf);
$payload=['request_id'=>12,'header'=>['division_id'=>1,'destination_type'=>'BAR'],'lines'=>[['qty_content_requested'=>1]]];
foreach(['success','get','csrf','foreign-request','missing-request','foreign-new','wrong-location','nested-line','huge','exception'] as $case){
    $http->output=new ReviewHttpOutput();$http->input->verb='POST';$http->input->csrf=$csrf;$data=$payload;
    $http->db->request=['division_id'=>1,'updated_at'=>'server revision'];$http->Procurement_model->fail=false;
    if($case==='get')$http->input->verb='GET';
    if($case==='csrf')$http->input->csrf='wrong';
    if($case==='foreign-request')$http->db->request['division_id']=2; // fake in-scope form ID must not override real owner
    if($case==='missing-request')$http->db->request=null;
    if($case==='foreign-new'){$data['request_id']=0;$data['header']['division_id']=2;}
    if($case==='wrong-location')$data['header']['destination_type']='KITCHEN';
    if($case==='nested-line')$data['lines'][0]['qty_content_requested']=[];
    if($case==='huge')$data['lines']=array_fill(0,101,[]);
    if($case==='exception')$http->Procurement_model->fail=true;
    $before=$http->Procurement_model->previews;$http->input->raw_input_stream=json_encode($data);$http->division_stock_preview();
    $expected=['success'=>200,'get'=>405,'csrf'=>403,'foreign-request'=>403,'missing-request'=>404,'foreign-new'=>403,'wrong-location'=>422,'nested-line'=>400,'huge'=>400,'exception'=>503][$case];
    $check($http->output->status===$expected,'HTTP '.$case.' status');
    $check($http->Procurement_model->previews===$before+(in_array($case,['success','exception'],true)?1:0),'HTTP '.$case.' rejected before stock read');
    $check($http->db->db_debug && !str_contains($http->output->body,'sensitive fixture'),'HTTP '.$case.' no debug leak/restore');
    if($case==='success')$check($http->Procurement_model->received[1]['updated_at']==='server revision' && $http->Procurement_model->received[3]===7,'HTTP binds persisted revision and current actor');
}
$formCsrf=new ReflectionMethod(Procurement::class,'require_division_verification_csrf');$formCsrf->setAccessible(true);
foreach([$csrf,'',[],str_repeat('b',64)] as $provided){
    $http->input->formToken=$provided;$http->input->verb='POST';
    $check($formCsrf->invoke($http)===($provided===$csrf),'form CSRF rejects missing/array/wrong token');
}
[$partialModel,$partialDb,$partialHeader,$partialLines]=fixture();
$partialLines[0]['qty_content_requested']=0;
$partial=$partialModel->preview_division_stock(12,$partialHeader,$partialLines,7);
$check(!empty($partial['ok']) && !empty($partial['data']['input_incomplete']) && $partial['data']['token']==='', 'incomplete line can read stock but cannot receive verification token');
require $root.'/application/controllers/Purchase.php';
foreach ([Procurement::class=>'store_request_stock_preview',Purchase::class=>'order_stock_preview'] as $class=>$method) {
    foreach (['success','get','csrf','forbidden','json','exception'] as $case) {
        $controller=(new ReflectionClass($class))->newInstanceWithoutConstructor();
        $controller->input=new ReviewHttpInput();$controller->output=new ReviewHttpOutput();$controller->session=new ReviewVerifySession();
        $controller->Procurement_model=new ReviewHttpModels();$controller->load=new class { function model($name) {} };
        $controller->session->set_userdata($class===Purchase::class?'purchase_mutation_csrf':'procurement_mutation_csrf',$csrf);
        $controller->allowed=$case!=='forbidden';$controller->input->verb=$case==='get'?'GET':'POST';$controller->input->csrf=$case==='csrf'?'bad':$csrf;
        $controller->input->raw_input_stream=$case==='json'?'broken':json_encode($payload);$controller->Procurement_model->fail=$case==='exception';
        $controller->$method();$expected=['success'=>200,'get'=>405,'csrf'=>403,'forbidden'=>403,'json'=>422,'exception'=>503][$case];
        $check($controller->output->status===$expected,$class.' manual stock '.$case.' HTTP status');
        $check($controller->Procurement_model->previews===(in_array($case,['success','exception'],true)?1:0),$class.' manual stock guards before read');
        $check(!str_contains($controller->output->body,'sensitive fixture'),$class.' manual stock no debug leakage');
    }
}
// Independent decisions must preserve sibling lines, links and stock evidence.
function lineFixture():array {
    [$m,$db,$header,$lines]=fixture();
    $db->pdo->exec("ALTER TABLE pur_division_request_line ADD COLUMN review_status TEXT DEFAULT 'PENDING';
        ALTER TABLE pur_division_request_line ADD COLUMN reviewed_by INTEGER;
        ALTER TABLE pur_division_request_line ADD COLUMN reviewed_at TEXT;
        ALTER TABLE pur_division_request_line ADD COLUMN review_notes TEXT;
        ALTER TABLE pur_division_request_link ADD COLUMN request_line_id INTEGER;
        DROP TABLE pur_division_stock_review;
        CREATE TABLE pur_division_stock_review(id INTEGER PRIMARY KEY,request_id INTEGER,request_line_id INTEGER DEFAULT 0,reviewed_by INTEGER,reviewed_at TEXT,source_ip TEXT,confirmed_with TEXT,reason TEXT,snapshot_hash TEXT,snapshot_json TEXT,UNIQUE(request_id,request_line_id));
        INSERT INTO pur_division_request_line(id,request_id,line_no,profile_name) VALUES(2,12,2,'second line');
        INSERT INTO pur_division_request_line(id,request_id,line_no,profile_name) VALUES(3,99,1,'foreign line');");
    return [$m,$db,$header,$lines];
}
function lineConfirmation($m,$db,$lines):array {
    $header=$db->query('SELECT * FROM pur_division_request WHERE id=12')->row_array();
    $preview=$m->preview_division_stock(12,$header,$lines,7);
    return ['token'=>$preview['data']['token'],'confirmed'=>true,'confirmed_with'=>'Kepala BAR','reason'=>'Kebutuhan telah dikonfirmasi untuk event'];
}
foreach (['success','wrong-parent','repeat','po-failed','reject','reopen','all-rejected','stale-status','two-verified'] as $case) {
    [$m,$db,$header,$lines]=lineFixture();
    $payload=['line'=>$lines[0],'stock_review'=>lineConfirmation($m,$db,$lines)];
    if ($case==='po-failed') $m->Purchase_model->fail=true;
    if ($case==='stale-status') $db->onBegin=static fn($d)=>$d->pdo->exec("UPDATE pur_division_request_line SET review_status='REJECTED' WHERE id=1");
    $r=$m->decide_division_request_line(12,$case==='wrong-parent'?3:1,'VERIFY',$payload,7);
    $success=!in_array($case,['wrong-parent','po-failed','stale-status'],true);
    $check($r['ok']===$success,'line '.$case.' result');
    $check($count($db,'fixture_po')===($success?1:0),'line '.$case.' atomic documents');
    $check($count($db,'pur_division_request_line')===3,'line '.$case.' preserves all IDs');
    $check($db->query('SELECT review_status FROM pur_division_request_line WHERE id=2')->row_array()['review_status']==='PENDING','line '.$case.' leaves sibling pending');
    $check($db->query('SELECT status FROM pur_division_request')->row_array()['status']==='SUBMITTED','line '.$case.' parent remains open');
    $check(!$db->pdo->inTransaction(),'line '.$case.' transaction closed');
    if ($case==='repeat') {
        $payload['stock_review']=lineConfirmation($m,$db,$lines);
        $check(!$m->decide_division_request_line(12,1,'VERIFY',$payload,7)['ok'] && $count($db,'fixture_po')===1,'repeat does not duplicate PO');
    }
    if (in_array($case,['reject','reopen'],true)) {
        $check($m->decide_division_request_line(12,2,'REJECT',['reason'=>'Barang tidak diperlukan'],7)['ok'],'reject only pending sibling');
        $check($db->query('SELECT status FROM pur_division_request')->row_array()['status']==='VERIFIED','mixed completed decisions finalize parent');
        $check($count($db,'fixture_po')===1 && $count($db,'pur_division_request_link')===1,'reject preserves verified document');
        $check(!$m->decide_division_request_line(12,1,'REJECT',['reason'=>'No'],7)['ok'],'cannot reject verified line');
        if ($case==='reopen') {
            $check($m->decide_division_request_line(12,2,'REOPEN',['reason'=>'Kebutuhan tambahan dikonfirmasi'],7)['ok'],'reopen rejected line');
            $check($db->query('SELECT status FROM pur_division_request')->row_array()['status']==='SUBMITTED','reopen parent without touching prior docs');
        }
    }
    if ($case==='two-verified') {
        $payload['stock_review']=lineConfirmation($m,$db,$lines);
        $check($m->decide_division_request_line(12,2,'VERIFY',$payload,7)['ok'],'second line verifies independently');
        $check($count($db,'pur_division_stock_review')===2 && $count($db,'fixture_po')===2,'separate evidence and documents');
        $check(count($m->stock_review_history('PO',91))===1 && count($m->stock_review_history('PO',92))===1,'each PO gets only its own review');
        $check($db->query('SELECT status FROM pur_division_request')->row_array()['status']==='VERIFIED','all verified parent');
    }
}
[$m,$db,$header,$lines]=lineFixture();
$check($m->decide_division_request_line(12,1,'REJECT',['reason'=>'Tidak dibutuhkan'],7)['ok'],'reject first line');
$check($m->decide_division_request_line(12,2,'REJECT',['reason'=>'Tidak dibutuhkan'],7)['ok'],'reject second line');
$check($db->query('SELECT status FROM pur_division_request')->row_array()['status']==='REJECTED' && $count($db,'fixture_po')===0,'all rejected creates no documents');
echo "Procurement stock review verification: {$checks} PASS (SQLite; PO boundary stub, no live DB or MariaDB lock simulation).\n";
