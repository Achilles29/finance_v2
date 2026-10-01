<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
define('BASEPATH',$root.'/system/'); define('APPPATH',$root.'/application/');
function log_message($level,$message): void {}
class MY_Controller
{
    public $input, $output, $load, $db, $Procurement_report_model, $Monthly_finance_model, $Finance_inventory_analysis_model;
    public array $grants=[], $rendered=[];
    public ?int $scope=null;
    protected function require_permission($page,$action) { if(!$this->can($page,$action)) throw new RuntimeException('DENIED '.$action); }
    protected function can($page,$action) { return in_array($page.':'.$action,$this->grants,true); }
    protected function active_division_id() { return $this->scope; }
    protected function render($view,$data) { $this->rendered=[$view,$data]; }
}
require APPPATH.'controllers/Procurement_reports.php'; require APPPATH.'controllers/Financial_analysis.php';
$checks=0;
$check=static function($ok,$label)use(&$checks){ if(!$ok) throw new RuntimeException($label); $checks++; };
ob_start();
Report_workspace::export('fixture',['amount'=>'Jumlah','document'=>'Dokumen'],[['amount'=>'-12.34','document'=>"\t=unsafe"]]);
$numericCsv=ob_get_clean();
$check(str_contains($numericCsv,'-12,34') && str_contains($numericCsv,"'\t=unsafe"),'Indonesian numeric cells preserved; text formulas neutralized');
$build=static function($class,array $query,array $grants) {
    $c=new $class(); $c->grants=$grants;
    $c->input=new class($query) { private $q; public function __construct($q){$this->q=$q;} public function get($key=null,$clean=true){return $key===null?$this->q:($this->q[$key]??null);} };
    $c->output=new class { public $status=200; public function set_header($h){} public function set_status_header($s){$this->status=$s;} };
    $c->db=new class { public $queries=[]; public function query($sql){$this->queries[]=$sql;return true;} };
    $c->load=new class { public $loaded=[]; public function model($name){$this->loaded[]=$name;} };
    $c->Procurement_report_model=new class { public $filters=[]; public function scopedDivision($id){if($id!==20)throw new RuntimeException('scope');return 2;} public function divisions(){return [['id'=>2],['id'=>3]];} public function report($kind,$filters){$this->filters=$filters;return ['rows'=>[['event_date'=>'2026-09-01','document_no'=>'=unsafe']]];} };
    $c->Monthly_finance_model=new class { public function monthOptions($f){return Report_workspace::monthOptions($f);} public function report($f){return ['rows'=>[['mutation_no'=>'=unsafe']],
        'months'=>['2026-08','2026-09'],'monthly'=>[['label'=>'2026-08','omzet'=>1000],['label'=>'2026-09','omzet'=>-1234]],
        'flow_options'=>['business'=>'Pengeluaran usaha'],'matrix'=>[['label'=>'=unsafe','flow'=>'business','direction'=>'OUT','values'=>['2026-08'=>10000,'2026-09'=>5000],'total'=>15000]],
        'sales'=>['available'=>$f['account_id']===0,'reason'=>'Filter kas aktif','rows'=>[['document_no'=>'=unsafe','billing'=>'0.00','tax'=>'0.00','refund'=>'12.34','value'=>'-12.34']]]];} };
    $c->Finance_inventory_analysis_model=new class { public function report($f){return ['revenue_available'=>true,'groups'=>[['month'=>'2026-09','division_name'=>'=unsafe','location'=>'REGULAR',
        'revenue'=>10000,'purchase'=>2000,'sr'=>3000,'used'=>4000,'transferred'=>0,'supply_ratio'=>50,'usage_ratio'=>40,'material'=>null]],
        'losses'=>[['movement_no'=>'=unsafe','qty'=>'-1.25','value'=>'-12.34']]];} };
    return $c;
};
foreach([['Procurement_reports','sr','procurement.sr_report.index'],['Procurement_reports','materials','procurement.material_report.index'],['Financial_analysis','index','finance.monthly_analysis.index']] as [$class,$method,$page]) {
    foreach([[],['export'=>'csv']] as $query) {
        $c=$build($class,$query,[]);
        try{$c->$method();$check(false,'anonymous role accepted');}catch(RuntimeException $e){$check($e->getMessage()==='DENIED view','view denied before query');}
        $check(!$c->db->queries && !$c->load->loaded,'unauthorized cannot reach DB');
    }
    $c=$build($class,['export'=>'csv'],[$page.':view']);
    try{$c->$method();$check(false,'export accepted');}catch(RuntimeException $e){$check($e->getMessage()==='DENIED export','independent export denial');}
    $check(!$c->db->queries,'export denial precedes DB');
    $c=$build($class,['month'=>'2026-09'],[$page.':view']); $c->$method();
    $check(count($c->rendered)===2 && $c->db->queries===['START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY','ROLLBACK'],'HTML read-only transaction ends');
    $c=$build($class,['month'=>'2026-99'],[$page.':view']); $c->$method();
    $check($c->output->status===422 && $c->rendered[1]['error']!=='','invalid month handled without SQL data read');
    $c=$build($class,['month'=>'2026-09','export'=>'csv'],[$page.':view',$page.':export']);
    ob_start(); $c->$method(); $csv=ob_get_clean();
    $check(str_starts_with($csv,"\xEF\xBB\xBF") && str_contains($csv,"'=unsafe"),'CSV is UTF-8 and formula-safe');
    $check(end($c->db->queries)==='ROLLBACK' && !$c->rendered,'CSV rolls back and does not append HTML');
    if($class==='Procurement_reports') {
        $c=$build($class,['month'=>'2026-09','division_id'=>3],[$page.':view']);$c->scope=20;$c->$method();
        $check($c->Procurement_report_model->filters['division_id']===2 && count($c->rendered[1]['divisions'])===1,'forged division overridden by server scope');
    }
}
$page='finance.monthly_analysis.index';
$c=$build('Financial_analysis',['export'=>'matrix'],[$page.':view']);
try{$c->index();$check(false,'matrix export accepted');}catch(RuntimeException $e){$check($e->getMessage()==='DENIED export','matrix requires independent export capability');}
$check(!$c->db->queries,'matrix export denial before query');
foreach([['export'=>'matrix'],['export'=>'csv','view'=>'sales']] as $query){
    $c=$build('Financial_analysis',$query+['month'=>'2026-09','month_from'=>'2026-08','month_to'=>'2026-09'],[$page.':view',$page.':export']);
    ob_start();$c->index();$csv=ob_get_clean();
    $check(str_contains($csv,"'=unsafe") && str_contains($csv,'-12,34'),'matrix/sales CSV numeric negatives and escaped source text');
    $check(end($c->db->queries)==='ROLLBACK' && !$c->rendered,'matrix/sales export never appends HTML');
    if($query['export']==='matrix'){$check(str_contains($csv,'2026-08;2026-09') && str_contains($csv,'Omzet POS neto'),'matrix CSV columns follow selected range with distinct revenue row');}
}
$c=$build('Financial_analysis',['export'=>'csv','view'=>'sales','account_id'=>1],[$page.':view',$page.':export']);
ob_start();$c->index();$csv=ob_get_clean();
$check($csv==='' && $c->output->status===422,'cannot export unavailable revenue as zero');
foreach([['month_from'=>'2026-10','month_to'=>'2026-09'],['month_from'=>'2025-01','month_to'=>'2026-09']] as $query){
    $c=$build('Financial_analysis',$query,[$page.':view']);$c->index();
    $check($c->output->status===422 && $c->db->queries===['ROLLBACK'],'invalid ranges rejected before snapshot');
}
foreach(['efficiency','waste'] as $view){
    $c=$build('Financial_analysis',['view'=>$view,'export'=>'csv','month'=>'2026-09'],[$page.':view']);
    try{$c->index();$check(false,'inventory export accepted');}catch(RuntimeException $e){$check($e->getMessage()==='DENIED export','inventory report respects finance export permission');}
    $c=$build('Financial_analysis',['view'=>$view,'export'=>'csv','month'=>'2026-09'],[$page.':view',$page.':export']);
    ob_start();$c->index();$csv=ob_get_clean();
    $check(str_contains($csv,"'=unsafe") && !$c->rendered && end($c->db->queries)==='ROLLBACK','inventory export formula safe and read only');
}
require APPPATH.'libraries/Feature_policy.php';
$manifest=json_decode(file_get_contents($root.'/app-manifest.json'),true);
$policy=require APPPATH.'config/feature_access.php';
foreach(['STARTER_POS','ENTERPRISE'] as $edition) {
    $entitlements=[];
    foreach($manifest['editions'] as $item) if($item['code']===$edition) foreach($item['features'] as $code=>$value) $entitlements[$code]=$value==='1';
    $guard=new Feature_policy($manifest,$policy,['verified'=>true,'status'=>'ACTIVE','payload'=>['edition'=>$edition,'entitlements'=>$entitlements]],true);
    foreach([['procurement_reports','sr'],['procurement_reports','materials'],['financial_analysis','index']] as [$class,$method]) {
        $decision=$guard->route($class,$method);
        $check($decision['allowed']===($edition==='ENTERPRISE') && $decision['page'],'licensed report route '.$class.'/'.$method);
    }
}
echo 'PASS '.$checks.' controller/permission/feature checks; no database accessed.'.PHP_EOL;
