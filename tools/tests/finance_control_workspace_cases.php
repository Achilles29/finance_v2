<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
function finance_control_workspace_fixture($db,$context,$check,string $root): void
{
    require APPPATH.'models/Pos_report_model.php';
    require APPPATH.'models/Finance_insight_model.php';
    $context->Pos_report_model=new Pos_report_model();$context->Finance_insight_model=new Finance_insight_model();
    $context->load=new class {
        public function __get($name) { return get_instance()->$name; }
        public function model($name): void { if(!isset(get_instance()->$name))throw new RuntimeException('Unexpected model '.$name); }
        public function view($name,$data=[]): void { if($name==='layout/_workspace_tabs')return;extract($data);include APPPATH.'views/'.$name.'.php'; }
    };
    $ddl=file_get_contents($root.'/sql/baseline/2026-09-05_clean_install_schema.sql');
    foreach(['pos_refund','pos_refund_line','pos_order','pos_order_line','pos_order_line_extra','pos_outlet','crm_member','org_employee',
        'inv_stock_deficit_cogs_adjustment','inv_stock_deficit_cogs_reversal','inv_stock_deficit','fin_payable','fin_receivable',
        'pay_payroll_period','pay_payroll_result','sys_page','sys_menu','auth_role','auth_role_permission'] as $table){
        if(!preg_match('/CREATE TABLE `'.$table.'` \(.*?;\n/s',$ddl,$m))throw new RuntimeException('DDL missing '.$table);
        $check($db->query($m[0])!==false,'control baseline '.$table);
    }
    $db->insert('sys_menu',['menu_code'=>'grp.finance','menu_label'=>'Keuangan','sort_order'=>7]);
    $db->insert('auth_role',['role_code'=>'SUPERADMIN','role_name'=>'Fixture admin']);
    $sql=preg_replace('/^--.*$/m','',file_get_contents($root.'/sql/2026-09-14a_finance_control_workspace.sql'));
    for($round=1;$round<=2;$round++)foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$check($db->query($statement)!==false,'control migration round '.$round);
    $db->data_cache=[];
    if(function_exists('finance_control_operations_fixture')) { finance_control_operations_fixture($db,$context,$check,$root);return; }
    $check($db->where('menu_code','finance.control')->count_all_results('sys_menu')===1,'sidebar seeded exactly once');
    $check($db->where('page_code','finance.control.index')->count_all_results('sys_page')===1,'page seeded once');
    $check($db->count_all('auth_role_permission')===1,'only superadmin default permission');
    $put=static function($table,$data)use($db):int { if(!$db->insert($table,$data))throw new RuntimeException('Fixture '.$table.': '.json_encode($db->error()));return (int)$db->insert_id(); };
    $today=date('Y-m-d');$tomorrow=date('Y-m-d',strtotime('+1 day'));$month=date('Y-m-01');$prev=date('Y-m-d',strtotime($month.' -1 day'));
    $put('fin_company_account',['id'=>1,'account_code'=>'TEST','account_name'=>'Fixture bank','current_balance'=>1000,'opening_balance'=>0]);
    $put('pos_payment_method',['id'=>1,'method_code'=>'ONLINE','method_name'=>'Platform fixture','method_type'=>'EWALLET','company_account_id'=>1]);
    $put('pos_payment_method',['id'=>2,'method_code'=>'CASH','method_name'=>'Cash fixture','method_type'=>'CASH','company_account_id'=>1]);
    $put('pos_payment',['id'=>1,'payment_no'=>'P-1','order_id'=>1,'payment_type'=>'FINAL','payment_status'=>'PAID','paid_at'=>$today.' 10:00:00','promo_amount'=>50]);
    $put('pos_payment_line',['id'=>1,'payment_id'=>1,'line_no'=>1,'payment_method_id'=>1,'amount'=>1000,'status'=>'PAID']);
    $put('fin_account_mutation_log',['mutation_no'=>'POS-1','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'IN','amount'=>1000,'ref_module'=>'POS','ref_table'=>'pos_payment','ref_id'=>1]);
    $insight=$context->Finance_insight_model;$purchase=$context->Purchase_model;$revenue=$context->Finance_revenue_reconciliation_model;
    $balance=static fn()=>(float)$db->get_where('fin_company_account',['id'=>1])->row('current_balance');
    $trace=Finance_settlement_control::trace($db,$today,1);
    $check($trace['expected_amount']===1000.0 && count($trace['payments'])===1,'POS already net of promo; no second discount subtraction');
    $casePayload=['revenue_date'=>$today,'payment_method_id'=>1,'provider_reference'=>'TEST-'.$today,'due_date'=>$tomorrow,'received_amount'=>'500.00','settlement_complete'=>false,'source_fingerprint'=>$trace['source_fingerprint'],'revision'=>0,'notes'=>'Synthetic half received'];
    $created=$insight->save_settlement($casePayload,0);$check($created['ok'],'settlement confirmation saved: '.($created['message']??''));$caseId=$created['id'];
    $check($balance()===1000.0 && $db->count_all('fin_account_mutation_log')===1,'confirmation never credits POS bank twice');
    $check(!$insight->save_settlement($casePayload,0)['ok'],'stale second writer cannot overwrite confirmation');
    $check(!$insight->save_settlement(array_replace($casePayload,['revision'=>1,'source_fingerprint'=>str_repeat('0',64)]),0)['ok'],'source fingerprint protects stale editor');
    $check($insight->settlement($today,1)['status']==='BELUM_CAIR_PENUH','partial receipt stays pending, not expense');
    $fee=['account_id'=>1,'mutation_type'=>'OUT','amount'=>100,'mutation_date'=>$today,'report_category'=>'PLATFORM_FEE','client_request_key'=>str_repeat('c',32),'reference_no'=>'FEE-1','settlement_confirmed'=>true,'settlement_control_id'=>$caseId,'notes'=>'Fixture fee'];
    $check(!$purchase->apply_manual_account_mutation(array_replace($fee,['settlement_control_id'=>0]),0)['ok'],'fee needs structured reference');
    $check($purchase->apply_manual_account_mutation($fee,0)['ok'],'known separate platform fee posts against same case');
    $check(!empty($purchase->apply_manual_account_mutation($fee,0)['replayed']) && $balance()===900.0,'fee retry idempotent');
    $check(!$purchase->apply_manual_account_mutation(array_replace($fee,['client_request_key'=>str_repeat('d',32)]),0)['ok'],'same category cannot be posted twice');
    $state=$insight->settlement($today,1);$check($state['expected_net']===900.0 && $state['remaining']===400.0,'settlement bridge net includes single linked fee');
    $rp=['reconciliation_date'=>$today,'revenue_date'=>$today,'payment_method_id'=>1,'account_id'=>1,'actual_amount'=>'500','resolution_type'=>'OUT','report_category'=>'CASH_SHORTAGE','settlement_control_id'=>$caseId];
    $draft=$revenue->save_line($rp,0);$check($draft['ok'],'linked revenue draft');
    $check(!$revenue->post_line($draft['line_id'],0)['ok'] && $balance()===900.0,'partial settlement cannot become expense in revenue reconciliation');
    $cash=$context->Finance_cash_reconciliation_model;
    $cp=['reconciliation_date'=>$today,'account_id'=>1,'actual_balance'=>'500','resolution_type'=>'OUT','report_category'=>'CASH_SHORTAGE','settlement_control_id'=>$caseId];
    $cs=$cash->save_line($cp,0);$check($cs['ok'],'linked cash draft');
    $check(!$cash->post_line($cs['line']['id'],0)['ok'] && $balance()===900.0,'partial receipt blocked in cash reconciliation too');
    $forecast=$insight->forecast(7);$check($forecast['book']===900.0 && $forecast['pending']===400.0 && $forecast['opening']===500.0 && $forecast['projected']===900.0,'forecast subtracts pending then adds once, not twice');
    $casePayload=array_replace($casePayload,['revision'=>1,'received_amount'=>'850','settlement_complete'=>true]);
    $check($insight->save_settlement($casePayload,0)['ok'],'complete batch confirmation');
    $draft=$revenue->save_line(array_replace($rp,['actual_amount'=>'850','report_category'=>'PLATFORM_FEE']),0);
    $check(!$revenue->post_line($draft['line_id'],0)['ok'],'manual fee cannot be repeated through revenue reconciliation');
    $draft=$revenue->save_line(array_replace($rp,['actual_amount'=>'850','report_category'=>'PROMO_EXPENSE']),0);
    $posted=$revenue->post_line($draft['line_id'],0);$check($posted['ok'] && $balance()===850.0,'only remaining separate promo fee 50 posted: '.($posted['message']??''));
    $check($insight->settlement($today,1)['status']==='SESUAI','cross-module costs reconcile to actual receipt');
    $check(!$purchase->apply_manual_account_mutation(array_replace($fee,['report_category'=>'PROMO_EXPENSE','client_request_key'=>str_repeat('e',32),'reference_no'=>'PROMO-1']),0)['ok'],'reverse direction cross-module duplicate guard');
    $db->where('id',1)->update('pos_payment_line',['amount'=>1001]);
    $check($insight->settlement($today,1)['stale']===true,'late POS change surfaces stale settlement');
    $check(!$purchase->apply_manual_account_mutation(array_replace($fee,['report_category'=>'OPERATING_EXPENSE','client_request_key'=>str_repeat('f',32)]),0)['ok'],'stale case blocks new adjustment');
    $db->where('id',1)->update('pos_payment_line',['amount'=>1000]);
    $plan=['request_key'=>str_repeat('1',32),'title'=>'Synthetic utility','direction'=>'OUT','amount'=>'25.25','due_date'=>$tomorrow,'certainty'=>'COMMITTED','status'=>'OPEN','notes'=>'Not present in automatic sources'];
    $saved=$insight->save_plan($plan,0);$check($saved['ok'],'manual cash plan');
    $check($insight->save_plan($plan,0)['ok'] && $db->count_all('fin_cash_plan')===1,'cash plan retry does not duplicate');
    $check(!$insight->save_plan(array_replace($plan,['amount'=>'26']),0)['ok'],'same plan key different amount rejected');
    $forecast=$insight->forecast(30);$check($forecast['projected']===824.75 && $forecast['committed']===824.75,'committed plan affects both cash trajectories');
    $check($balance()===850.0,'planning never changes account');
    $done=array_replace($plan,['id'=>$saved['id'],'revision'=>1,'status'=>'DONE']);
    $check($insight->save_plan($done,0)['ok'] && $insight->forecast(7)['projected']===850.0,'DONE plan removed without posting');
    $check(!$insight->save_plan($done,0)['ok'],'plan optimistic revision');
    foreach(['2026-02-30','tomorrow','2026-13-01'] as $bad){try{Finance_insight_model::date_value($bad);$valid=true;}catch(Throwable $e){$valid=false;}$check(!$valid,'invalid exact date rejected '.$bad);}
    // Paid sale originally 1100, tax 100, HPP 400. Current remaining HPP is 350 after a later refund.
    $put('pos_order',['id'=>1,'order_no'=>'O-1','outlet_id'=>1,'cashier_employee_id'=>1,'status'=>'REFUND_PARTIAL','ordered_at'=>$prev.' 09:00:00','paid_at'=>$prev.' 10:00:00','grand_total'=>1000,'paid_total'=>1100,'tax_amount'=>100]);
    $put('pos_order_line',['id'=>1,'order_id'=>1,'line_no'=>1,'product_id'=>1,'qty'=>1,'cogs_amount'=>300]);
    $put('pos_order_line_extra',['order_id'=>1,'order_line_id'=>1,'line_no'=>1,'extra_id'=>1,'qty'=>2,'cost_amount_snapshot'=>25]);
    $put('pos_refund',['id'=>1,'refund_no'=>'R-1','order_id'=>1,'payment_id'=>1,'payment_method_id'=>2,'refund_status'=>'POSTED','refund_amount'=>100,'refunded_at'=>$today.' 11:00:00','reason'=>'Fixture']);
    $put('pos_refund_line',['refund_id'=>1,'line_no'=>1,'line_type'=>'PRODUCT','order_line_id'=>1,'qty_refunded'=>1,'amount_refunded'=>100,'cost_reversed'=>50]);
    $prior=$context->Pos_report_model->financial_profit_loss_basis(substr($prev,0,7).'-01',$prev);
    $check((float)$prior['sale_hpp']===400.0 && $prior['net_sales']===1000.0 && $prior['hpp']===400.0,'prior month sale restores original HPP including extra/refund and excludes tax');
    $now=$context->Pos_report_model->financial_profit_loss_basis($month,date('Y-m-t'));
    $check($now['net_sales']===-100.0 && $now['hpp']===-50.0,'current refund recognized now, not rewriting prior month');
    $profit=$insight->profit_loss($month,date('Y-m-t'));
    $check($profit['result']===-200.0,'management P&L refund margin minus additional platform/promo cost, not purchase outflow');
    $put('fin_account_mutation_log',['mutation_no'=>'UNCLASSIFIED','mutation_date'=>$today,'account_id'=>1,'mutation_type'=>'IN','amount'=>123,'ref_module'=>'FINANCE']);
    $check($insight->profit_loss($month,date('Y-m-t'))['result']===-200.0,'unclassified receipts do not inflate management P&L');
    $quality=$insight->quality($month,date('Y-m-t'));
    $check($quality['issues'][1]['count']===1,'quality detects unclassified source');
    $check($quality['issues'][2]['count']===1,'quality detects ledger/current balance discrepancy without repair');
    $check($balance()===850.0,'all report reads leave account unchanged');
    $put('inv_stock_deficit_cogs_adjustment',['id'=>1,'deficit_id'=>1,'deficit_settlement_id'=>1,'stock_domain'=>'COMPONENT','order_id'=>1,'order_line_id'=>1,'sale_date'=>$prev,'settlement_date'=>$today,'recognition_date'=>$today,'recognition_period_month'=>$month,'variance_amount'=>20]);
    $put('inv_stock_deficit_cogs_reversal',['reversal_key'=>str_repeat('a',64),'cogs_adjustment_id'=>1,'deficit_id'=>1,'reversal_date'=>$today,'variance_amount_reversed'=>5,'source_document_type'=>'TEST']);
    $current=$context->Pos_report_model->financial_profit_loss_basis($month,date('Y-m-t'));
    $check($current['correction']===20.0 && $current['reversal']===5.0 && $current['hpp']===-35.0,'deficit variance and reversal recognized in correction month');
    $previous=$context->Pos_report_model->financial_profit_loss_basis(substr($prev,0,7).'-01',$prev);
    $check($previous['hpp']===400.0,'later correction does not move back into original sale month');
    $put('pos_order',['id'=>2,'order_no'=>'DRAFT-2','outlet_id'=>1,'cashier_employee_id'=>1,'status'=>'PAID_PARTIAL','ordered_at'=>$today.' 12:00:00','paid_at'=>$today.' 12:01:00','grand_total'=>900,'paid_total'=>100]);
    $check($context->Pos_report_model->financial_profit_loss_basis($month,date('Y-m-t'))['net_sales']===-100.0,'partial payment is not finalized sales');
    $put('fin_payable',['payable_no'=>'PAYABLE-1','party_id'=>1,'payable_date'=>$today,'due_date'=>$today,'payable_title'=>'Synthetic payable','amount'=>100,'outstanding_amount'=>100]);
    $put('fin_receivable',['receivable_no'=>'RECEIVABLE-1','party_id'=>1,'receivable_date'=>$today,'due_date'=>$tomorrow,'receivable_title'=>'Synthetic receivable','amount'=>70,'outstanding_amount'=>70]);
    $f=$insight->forecast(7);$check($f['committed']===750.0 && $f['projected']===820.0,'payable committed, receivable estimated on separate trajectories');
    $put('fin_company_account',['id'=>2,'account_code'=>'USD','account_name'=>'Foreign fixture','currency_code'=>'USD','current_balance'=>999999]);
    $check($insight->forecast(7)['book']===850.0,'foreign currency not silently added to IDR');
    $oldFee=$db->get_where('fin_account_mutation_log',['client_request_key'=>str_repeat('c',32)])->row_array();
    $check($insight->adjustment_permission((int)$oldFee['id'])==='purchase.order.index','void requires original mutation module edit permission');
    $void=$insight->void_adjustment(['mutation_id'=>$oldFee['id'],'reason'=>'Synthetic correction'],0);
    $check($void['ok']&&$balance()===950.0,'linked adjustment void restores exact account amount with audit');
    $check(!empty($insight->void_adjustment(['mutation_id'=>$oldFee['id'],'reason'=>'Retry'],0)['replayed'])&&$balance()===950.0,'void retry cannot restore twice');
    $check(!$insight->void_adjustment(['mutation_id'=>1,'reason'=>'Cannot void POS'],0)['ok'],'void endpoint refuses POS payment');
    $check($purchase->apply_manual_account_mutation(array_replace($fee,['client_request_key'=>str_repeat('b',32)]),0)['ok'] && $balance()===850.0,'VOID pair releases settlement category for corrected posting');
    $check($insight->settlement($today,1)['expected_net']===850.0,'VOID original and reversal excluded from settlement totals');
    $put('fin_period_close',['period_code'=>'TEST-CLOSE','period_year'=>(int)date('Y'),'period_start'=>$today,'period_end'=>$today,'status'=>'CLOSED']);
    $check(!$insight->save_plan(array_replace($plan,['request_key'=>str_repeat('3',32)]),0)['ok'],'closed current period rejects new plan metadata');
    $db->where('period_code','TEST-CLOSE')->update('fin_period_close',['status'=>'OPEN']);
    $put('pay_payroll_period',['id'=>1,'period_code'=>date('Y-m'),'period_start'=>$month,'period_end'=>date('Y-m-t'),'status'=>'FINALIZED']);
    $put('pay_payroll_result',['payroll_period_id'=>1,'employee_id'=>1,'employee_code_snapshot'=>'EMP-TEST','employee_name_snapshot'=>'Synthetic employee','gross_pay'=>200,'late_deduction_total'=>10,'cash_advance_cut_total'=>50,'net_pay'=>140,'status'=>'FINALIZED']);
    $pnl=$insight->profit_loss($month,date('Y-m-t'));$check($pnl['salary']===190.0,'payroll expense excludes cash advance principal deduction');
    $check($insight->forecast(30)['projected']===680.0,'forecast uses unpaid payroll cash amount, not gross payroll cost');
    $db->where('id',1)->update('pay_payroll_result',['status'=>'PAID','paid_at'=>$today.' 12:00:00']);
    $check($insight->forecast(30)['projected']===820.0,'paid payroll not forecast a second time');
    require APPPATH.'controllers/Finance_insights.php';
    $context->session=new class { public function userdata($key){return str_repeat('a',64);} };
    $context->input=new class {
        public string $token='';public string $verb='POST';public string $raw_input_stream='{}';
        public function get_request_header($key,$xss=false){return $this->token;}
        public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}
        public function ip_address(){return '127.0.0.1';}
    };
    $context->output=new class {
        public int $status=200;public string $body='';
        public function set_status_header($value){$this->status=$value;return $this;}
        public function set_content_type($value){return $this;}
        public function set_output($value){$this->body=$value;return $this;}
    };
    $http=(new ReflectionClass(Finance_insights::class))->newInstanceWithoutConstructor();
    $queries=count($db->queries);try{$http->save('cash-plan');$denied=false;}catch(RuntimeException $e){$denied=$e->getMessage()==='fixture_permission_denied';}
    $check($denied&&count($db->queries)===$queries,'controller denies write without edit permission before DB');
    $GLOBALS['finance_fixture_permission_allowed']=true;
    $http->save('cash-plan');$check($context->output->status===403&&count($db->queries)===$queries,'missing scoped CSRF rejected without DB');
    $context->input->token=str_repeat('a',64);$context->input->verb='GET';$http->save('cash-plan');
    $check($context->output->status===403&&count($db->queries)===$queries,'GET never writes with otherwise valid CSRF');
    $context->input->verb='POST';$http->save('not-allowed');$check($context->output->status===404,'unknown action rejected');
    $context->input->raw_input_stream='{"title":[]}';$http->save('cash-plan');$check($context->output->status===400&&count($db->queries)===$queries,'nested payload rejected without warning or DB');
    $replacementForPermission=$db->get_where('fin_account_mutation_log',['client_request_key'=>str_repeat('b',32)])->row_array();
    $context->input->raw_input_stream=json_encode(['mutation_id'=>$replacementForPermission['id'],'reason'=>'Should not pass origin permission']);
    $GLOBALS['finance_fixture_denied_page']='purchase.order.index';
    try{$http->save('void-adjustment');$denied=false;}catch(RuntimeException $e){$denied=$e->getMessage()==='fixture_permission_denied';}
    $check($denied&&$balance()===850.0,'control edit permission cannot bypass original module edit for void');
    unset($GLOBALS['finance_fixture_denied_page']);$GLOBALS['finance_fixture_permission_allowed']=false;
    // Auditing is atomic; fail closed if audit storage cannot accept metadata.
    $db->query("CREATE TRIGGER fixture_control_audit_failure BEFORE INSERT ON aud_transaction_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
    $replacement=$db->get_where('fin_account_mutation_log',['client_request_key'=>str_repeat('b',32)])->row_array();
    $check(!$insight->void_adjustment(['mutation_id'=>$replacement['id'],'reason'=>'Expected audit failure'],0)['ok'],'audit failure rejects linked void');
    $check($balance()===850.0 && $db->where('reversal_of_mutation_id',$replacement['id'])->count_all_results('fin_account_mutation_log')===0,'failed void rolls back both balance and reversal');
    $check(!$insight->save_plan(array_replace($plan,['request_key'=>str_repeat('2',32)]),0)['ok'],'audit failure rejects plan');
    $check($db->count_all('fin_cash_plan')===1,'audit failure rolls back plan insertion');
    $db->query('DROP TRIGGER fixture_control_audit_failure');
    if(in_array('--export-ui',$GLOBALS['argv'],true)){
        $dir=sys_get_temp_dir().'/finance-control-ui-'.bin2hex(random_bytes(6));if(!mkdir($dir,0700))throw new RuntimeException('UI fixture directory failed');
        foreach(['settlement','quality','cash-plan','profit-loss'] as $tab)foreach([true,false] as $edit){
            $result=match($tab){'settlement'=>$insight->settlement($today,1),'quality'=>$insight->quality($month,date('Y-m-t')),'cash-plan'=>$insight->forecast(7),'profit-loss'=>$insight->profit_loss($month,date('Y-m-t'))};
            $data=['tab'=>$tab,'result'=>$result,'can_edit'=>$edit,'csrf'=>str_repeat('a',64),'error'=>'','date'=>$today,'method_id'=>1,'methods'=>$insight->methods(),'month'=>date('Y-m'),'days'=>7];
            $data['can_void_modules']=array_fill_keys(['FINANCE','FINANCE_RECON','REVENUE_RECON'],$edit);
            ob_start();(function($data){extract($data);include APPPATH.'views/finance/control.php';})->call($context,$data);$html=ob_get_clean();
            file_put_contents($dir.'/'.$tab.($edit?'':'-readonly').'.html',$html);
        }
        echo 'UI_FIXTURE='.$dir.PHP_EOL;
    }
    foreach($GLOBALS['argv'] as $arg)if(str_starts_with($arg,'--render=')){
        $tab=substr($arg,9);$result=match($tab){'settlement'=>$insight->settlement($today,1),'quality'=>$quality,'cash-plan'=>$insight->forecast(7),'profit-loss'=>$profit,default=>throw new RuntimeException('Unknown fixture tab')};
        $data=['tab'=>$tab,'result'=>$result,'can_edit'=>!in_array('--readonly',$GLOBALS['argv'],true),'csrf'=>str_repeat('a',64),'error'=>'','date'=>$today,'method_id'=>1,'methods'=>$insight->methods(),'month'=>date('Y-m'),'days'=>7];
        (function($data){extract($data);include APPPATH.'views/finance/control.php';})->call($context,$data);return;
    }
    echo 'Finance control workspace fixture complete. No application DB accessed.'.PHP_EOL;
}
