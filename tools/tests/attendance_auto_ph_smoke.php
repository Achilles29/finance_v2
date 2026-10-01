<?php
declare(strict_types=1);
// Real attendance/portal models, isolated SQLite only. No app bootstrap/config/socket.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$root=dirname(__DIR__,2);
define('BASEPATH',$root.'/system/');define('APPPATH',$root.'/application/');define('ENVIRONMENT','testing');
date_default_timezone_set('Asia/Jakarta');
function log_message($level,$message): void {}
function is_php($version): bool {return version_compare(PHP_VERSION,$version,'>=');}
function show_error($message='',$status=500): void {throw new RuntimeException((string)$message);}
function html_escape($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function site_url($path=''): string {return '/'.$path;}
$context=new stdClass();function &get_instance(){return $GLOBALS['context'];}
require BASEPATH.'core/Model.php';require BASEPATH.'database/DB.php';
$params=['dbdriver'=>'sqlite3','database'=>':memory:','db_debug'=>false,'save_queries'=>true];DB($params,true);
class PhMemoryDB extends CI_DB_sqlite3_driver
{
    public $failPattern='',$failCommit=false;
    public function query($sql,$binds=false,$return_object=null)
    {
        if($this->failPattern!==''&&preg_match($this->failPattern,$sql)){$this->_trans_status=false;return false;}
        $sql=preg_replace('/\bINSERT IGNORE\b/i','INSERT OR IGNORE',$sql);
        $sql=preg_replace('/\s+FOR UPDATE\b/i','',$sql);
        return parent::query($sql,$binds,$return_object);
    }
    public function trans_commit(){return $this->failCommit?false:parent::trans_commit();}
    public function reset_request(): void {$this->_trans_status=true;$this->failPattern='';$this->failCommit=false;}
}
$db=new PhMemoryDB($params);$db->initialize();$context->db=$db;
$checks=0;$check=static function($ok,$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;};
$run=static function($sql)use($db):void{if(!$db->query($sql))throw new RuntimeException('Fixture query '.json_encode($db->error()));};
$baseline=file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql');
$tables=['att_daily','att_shift','att_shift_schedule','att_employee_ph_ledger','att_ph_eligibility','att_holiday_calendar','att_attendance_policy','att_presence'];
foreach($tables as $table){
    if(!preg_match('/CREATE TABLE `'.$table.'`\s*\((.*?)\) ENGINE/s',$baseline,$m))throw new RuntimeException('Missing fixture '.$table);
    $body=preg_replace('/\benum\([^)]*\)/i','TEXT',$m[1]);$columns=[];
    foreach(preg_split('/,(?![^()]*\))/',$body) as $part){
        $part=trim($part);if(preg_match('/^(KEY|CONSTRAINT|FULLTEXT)\b/i',$part))continue;
        $part=preg_replace('/\bUNIQUE KEY\s+`?\w+`?\s*/i','UNIQUE ',$part);
        $part=preg_replace('/\b(bigint|int|tinyint|smallint)(\(\d+\))?(\s+unsigned)?/i','INTEGER',$part);
        $part=preg_replace('/\b(AUTO_INCREMENT|USING BTREE)\b/i','',$part);
        $part=preg_replace('/\s+ON UPDATE current_timestamp\(\)/i','',$part);$part=str_ireplace('current_timestamp()','CURRENT_TIMESTAMP',$part);
        $part=preg_replace('/\s+(COLLATE|CHARACTER SET)\s+\w+/i','',$part);
        $part=preg_replace('/\s+COMMENT\s+\x27[^\x27]*\x27/i','',$part);$columns[]=$part;
    }$run('CREATE TABLE '.$table.' ('.implode(',',$columns).')');
}
require APPPATH.'models/Attendance_model.php';require APPPATH.'models/My_portal_model.php';
$context->Compensation_model=new class{
    public $active=true;
    public function resolve_for_employee($employee,$date): array {return ['source'=>$this->active?'CONTRACT':'LEGACY','contract_id'=>$this->active?1:0,'snapshot_id'=>1,'basic_salary'=>2600000,'position_allowance'=>260000,'objective_allowance'=>260000,'meal_rate'=>10000,'overtime_rate'=>0];}
    public function resolve_finalized_contract_for_employee($employee,$date): array {return $this->resolve_for_employee($employee,$date);}
    public function build_att_daily_provenance($comp): array {return ['compensation_source'=>$comp['source'],'compensation_contract_id'=>$comp['contract_id'],'compensation_snapshot_id'=>$comp['snapshot_id']];}
};
$context->load=new class {
    public function model($name): void {
        $ci=get_instance();if(isset($ci->$name))return;
        if($name==='Attendance_model'){$ci->$name=new Attendance_model();return;}
        throw new RuntimeException('Unexpected model '.$name);
    }
};
$portal=new My_portal_model();$loader=$context->load;
$check(isset($context->load)&&!method_exists($context,'load'),'reproduce CI shape: loader property exists, old method_exists check wrongly fails');
$check(str_contains(file_get_contents(BASEPATH.'core/Controller.php'),'public $load;'),'real CI controller declares loader property');
$put=static function($table,$row)use($db):int{if(!$db->insert($table,$row))throw new RuntimeException('Fixture insert '.$table.' '.json_encode($db->error()));return (int)$db->insert_id();};
$today=date('Y-m-d');$yesterday=date('Y-m-d',strtotime('-1 day'));$before=date('Y-m-d',strtotime('-10 days'));$tomorrow=date('Y-m-d',strtotime('+1 day'));
foreach([[1,'PH'],[2,'PHB'],[3,'REGULAR'],[4,'OFF']] as [$id,$code])$put('att_shift',['id'=>$id,'shift_code'=>$code,'shift_name'=>$code,'start_time'=>'09:00:00','end_time'=>'17:00:00','is_active'=>1]);
$policy=['ph_attendance_mode'=>'AUTO_PRESENT','default_work_days_per_month'=>26,'ph_gets_meal_allowance'=>1,'meal_calc_mode'=>'MONTHLY','enforce_geofence'=>1];
$employeeSeq=0;
$employee=static function($qty=1,$shift=1,$expiry=null,$eligible=1,$effective=null,$grantDate=null)use(&$employeeSeq,$put,$today,$before):int{
    $id=++$employeeSeq;
    if($shift)$put('att_shift_schedule',['employee_id'=>$id,'schedule_date'=>$today,'shift_id'=>$shift]);
    $put('att_ph_eligibility',['employee_id'=>$id,'is_eligible'=>$eligible,'effective_date'=>$effective??$before]);
    if($qty>0)$put('att_employee_ph_ledger',['employee_id'=>$id,'tx_date'=>$grantDate??$before,'tx_type'=>'GRANT','qty_days'=>$qty,'expired_at'=>$expiry,'ref_table'=>'synthetic_grant','ref_id'=>$id,'entry_mode'=>'MANUAL']);
    return $id;
};
$snapshot=static function()use($db,$tables):array{$data=[];foreach($tables as $table)$data[$table]=$db->get($table)->result_array();return $data;};
$deny=static function($id,$reason)use($portal,$today,$policy,$check,$db,$snapshot):array{
    $before=$snapshot();$r=$portal->ensure_auto_ph_presence($id,$today,$policy);
    $check(empty($r['ok'])&&str_contains($r['message'],$reason),'reject '.$reason);
    $check($snapshot()===$before,'rejected auto PH leaves daily/ledger unchanged');$db->reset_request();return $r;
};
$id=$employee();
$context->load=null;$deny($id,'Layanan validasi PH');$context->load=$loader;
$context->Attendance_model=new stdClass();$deny($id,'validasi saldo PH');unset($context->Attendance_model);
$first=$portal->ensure_auto_ph_presence($id,$today,$policy);
$check($first['ok']&&$first['created']&&$first['recorded'],'opening page creates paid PH with normal CI loader, no GPS/location/event');
$daily=$db->get_where('att_daily',['employee_id'=>$id])->row_array();
$check($daily['attendance_status']==='HOLIDAY'&&$daily['source_type']==='AUTO','PH recorded as paid holiday, not physical work or new grant');
$check((float)$daily['daily_salary_amount']===130000.0&&(float)$daily['meal_amount']===10000.0,'PH retains existing salary/meal policy and contract snapshot');
$check((int)$daily['compensation_contract_id']===1&&(int)$daily['late_minutes']===0&&(int)$daily['overtime_minutes']===0,'PH snapshots contract without late/overtime');
$check($db->where('employee_id',$id)->where('tx_type','USE')->count_all_results('att_employee_ph_ledger')===1,'one USE linked to daily');
$check($db->count_all('att_presence')===0,'automatic PH does not fabricate device/GPS presence events');
$same=$snapshot();$repeat=$portal->ensure_auto_ph_presence($id,$today,$policy);
$check($repeat['ok']&&!$repeat['created']&&$repeat['recorded']&&$snapshot()===$same,'reopening page does not debit another PH or rewrite salary');
$check(str_contains($repeat['message'],'tidak memakai jatah tambahan'),'repeat explains already recorded state');
$phb=$employee(1,2);$r=$portal->ensure_auto_ph_presence($phb,$today,array_replace($policy,['meal_calc_mode'=>'CUSTOM']));
$check($r['ok']&&$r['recorded'],'PHB follows same automatic path');
$phbRow=$db->get_where('att_daily',['employee_id'=>$phb])->row_array();
$check((float)$phbRow['daily_salary_amount']===120000.0&&(float)$phbRow['meal_amount']===10000.0,'CUSTOM meal is recorded separately, not paid twice through salary');
$expiryToday=$employee(1,1,$today);$r=$portal->ensure_auto_ph_presence($expiryToday,$today,$policy);
$check($r['ok'],'PH remains usable through its expiry date inclusive');
$deny($employee(0),'saldo tidak cukup');$deny($employee(0.5),'saldo tidak cukup');
$deny($employee(1,1,$yesterday),'saldo tidak cukup');
$deny($employee(1,1,null,0),'hak PH aktif');$deny($employee(1,1,null,1,$tomorrow),'hak PH aktif');
$deny($employee(1,1,null,1,null,$tomorrow),'saldo tidak cukup');
$used=$employee();$put('att_employee_ph_ledger',['employee_id'=>$used,'tx_date'=>$yesterday,'tx_type'=>'USE','qty_days'=>1,'ref_table'=>'synthetic_previous_use','ref_id'=>$used]);$deny($used,'saldo tidak cukup');
$noContract=$employee();$context->Compensation_model->active=false;$deny($noContract,'kontrak ACTIVE');$context->Compensation_model->active=true;
foreach([0,3,4] as $shift){$other=$employee(1,$shift);$same=$snapshot();$r=$portal->ensure_auto_ph_presence($other,$today,$policy);$check($r['ok']&&!$r['created']&&!$r['recorded']&&$same===$snapshot(),'no automatic PH for missing/regular/OFF schedule '.$shift);}
$other=$employee();$same=$snapshot();$r=$portal->ensure_auto_ph_presence($other,$today,['ph_attendance_mode'=>'MANUAL']);$check($r['ok']&&!$r['created']&&$same===$snapshot(),'model respects explicit non-automatic mode');
$existing=$employee();$put('att_daily',['employee_id'=>$existing,'attendance_date'=>$today,'shift_id'=>3,'attendance_status'=>'SICK']);
$same=$snapshot();$conflict=$portal->ensure_auto_ph_presence($existing,$today,$policy);
$check(!$conflict['recorded']&&$same===$snapshot(),'existing non-PH record preserved and never falsely shown as PH success');
foreach(['/^INSERT INTO ["`]?att_daily/i','/^UPDATE ["`]?att_daily/i','/^INSERT IGNORE INTO att_employee_ph_ledger/i'] as $pattern){
    $failed=$employee();$db->failPattern=$pattern;$deny($failed,'Gagal');
}
$failed=$employee();$db->failCommit=true;$deny($failed,'Gagal menyimpan');
$retry=$portal->ensure_auto_ph_presence($failed,$today,$policy);$check($retry['ok']&&$retry['created'],'retry after rollback safely creates one PH');

// The same broken load check also skipped grants after ordinary holiday work.
// Invoke the real recompute path with synthetic device events and compensation adapter.
$regular=$employee(0,3);
$put('att_holiday_calendar',['holiday_date'=>$today,'holiday_name'=>'Synthetic national holiday','holiday_type'=>'NATIONAL','is_active'=>1]);
foreach(['CHECKIN'=>'09:00:00','CHECKOUT'=>'17:00:00'] as $event=>$clock)$put('att_presence',['employee_id'=>$regular,'shift_id'=>3,'attendance_date'=>$today,'attendance_time'=>$clock,'attendance_at'=>$today.' '.$clock,'event_type'=>$event,'source_type'=>'DEVICE']);
$recompute=new ReflectionMethod(My_portal_model::class,'recompute_daily');$recompute->setAccessible(true);
$recalc=$recompute->invoke($portal,$regular,$today,$portal->get_schedule_with_shift($regular,$today),$policy);
$check($recalc['ok'],'real regular attendance recompute still succeeds');
$check($db->where('employee_id',$regular)->where('tx_type','GRANT')->count_all_results('att_employee_ph_ledger')===1,'regular national-holiday attendance now reaches PH grant sync');
$same=$snapshot();$recompute->invoke($portal,$regular,$today,$portal->get_schedule_with_shift($regular,$today),$policy);
$check($db->where('employee_id',$regular)->where('tx_type','GRANT')->count_all_results('att_employee_ph_ledger')===1,'regular attendance recompute does not duplicate PH grant');

$render=static function(array $data):string{extract($data,EXTR_SKIP);ob_start();include APPPATH.'views/my/attendance.php';return ob_get_clean();};
$base=['employee'=>['id'=>$id,'employee_name'=>'Synthetic <img src=x>','employee_code'=>'TEST'],'today'=>$today,'today_schedule'=>$portal->get_schedule_with_shift($id,$today),'ph_auto_result'=>$first,'policy'=>$policy,'rows'=>[], 'location_options'=>[['value'=>1,'label'=>'Synthetic location']]];
set_error_handler(static function($severity,$message){throw new RuntimeException('PHP render: '.$message);});
try{
    $html=$render($base);$check(str_contains($html,'Presensi PH sudah tercatat')&&!str_contains($html,'<form class="my-attendance-check-form"'),'PH success UI does not require check buttons');
    $check(!str_contains($html,'id="gps-status"')&&!str_contains($html,'<img src=x>'),'PH UI omits GPS and escapes employee text');
    $check(str_contains($html,"if (!document.querySelector('.my-attendance-check-form')) return;"),'no device geolocation request for auto PH');
    $error=$render(array_replace($base,['ph_auto_result'=>['ok'=>false,'message'=>'Saldo PH <img src=x> tidak cukup']]));
    $check(!str_contains($error,'Presensi PH sudah tercatat')&&str_contains($error,'Shift PH belum dapat ditandai hadir')&&!str_contains($error,'<img src=x>'),'PH error remains clear and escaped, never success');
    $plain=$render(array_replace($base,['today_schedule'=>$portal->get_schedule_with_shift($regular,$today),'ph_auto_result'=>null]));
    $check(substr_count($plain,'<form class="my-attendance-check-form"')===2&&str_contains($plain,'id="gps-status"'),'ordinary shift keeps check-in/out and GPS UI');
    $missing=$render(array_replace($base,['employee'=>null]));$check(!str_contains($missing,'<form class="my-attendance-check-form"'),'missing employee cannot access attendance actions');
}finally{restore_error_handler();}
$controller=file_get_contents(APPPATH.'controllers/My.php');
$check(str_contains($controller,'ensure_auto_ph_presence((int)$employee[\'id\'], $today, $policy)'),'real attendance page invokes auto PH for selected employee and current date');
$check(!preg_match('/method_exists\(\$CI, [\x27\x22]load[\x27\x22]\)/',file_get_contents(APPPATH.'models/My_portal_model.php')),'no loader-method guard remains in portal');
echo 'Attendance auto PH: '.$checks.' PASS (SQLite :memory:, actual PH/portal models, compensation adapter; not live DB/MariaDB/browser UAT).'.PHP_EOL;
