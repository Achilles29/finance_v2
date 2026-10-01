<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Called only by the :memory: fixture. No app configuration, sockets or real DB. */
function finance_reconciliation_reporting_cases($db, $revenue, $cash, $op, $check, $put, $create, string $today): void
{
    if ($db->dbdriver !== 'sqlite3' || $db->database !== ':memory:') throw new RuntimeException('In-memory fixture required.');
    require_once APPPATH.'models/Finance_report_model.php';
    require_once APPPATH.'models/Purchase_model.php';
    require_once APPPATH.'models/Pos_report_model.php';
    $baseline=file_get_contents(APPPATH.'../sql/baseline/2026-09-05_clean_install_schema.sql');
    foreach (['pos_order','pos_order_line','pos_order_line_extra','pos_refund_line'] as $table) {
        preg_match('/CREATE TABLE `'.$table.'`.*?;\n/s',$baseline,$m); $create($m[0]);
    }
    $context=get_instance();
    $context->Pos_report_model=new Pos_report_model();
    $context->load=new class {
        public function model($name): void { if(!isset(get_instance()->$name)) throw new RuntimeException('Unexpected fixture model '.$name); }
    };
    $report=new Finance_report_model(); $purchase=new Purchase_model();
    $success=static function(array $r,string $label)use($check):array { $check(!empty($r['ok']),'integration '.$label.' '.($r['message']??''));return $r; };
    $reject=static function(array $r,string $label)use($check):void { $check(empty($r['ok']),'integration '.$label.' '.($r['message']??'')); };
    $cashTotal=static fn()=>round((float)$db->query('SELECT SUM(current_balance) n FROM fin_company_account')->row('n'),2);
    $accountBalance=static fn($id)=>round((float)$db->get_where('fin_company_account',['id'=>$id])->row('current_balance'),2);
    $overview=static fn()=>$report->financial_estimation_report((int)date('Y'),(int)date('n'))['overview'];
    $pnl=static fn()=>$op->profit_loss($today,$today);
    $sequence=0;
    $fixture=static function()use($db,$put,$today,&$sequence):array {
        $code='INTEGRATION-'.(++$sequence);
        $account=$put('fin_company_account',['account_code'=>$code,'account_name'=>$code,'opening_balance'=>1000,'current_balance'=>1100]);
        $counter=$put('fin_company_account',['account_code'=>$code.'-COUNTER','account_name'=>$code.' counter','opening_balance'=>500,'current_balance'=>500]);
        $method=$put('pos_payment_method',['method_code'=>$code,'method_name'=>$code,'method_type'=>'BANK','company_account_id'=>$account]);
        $payment=$put('pos_payment',['payment_no'=>$code,'order_id'=>9000+$sequence,'payment_type'=>'FINAL','payment_status'=>'PAID','paid_at'=>$today.' 10:00:00']);
        $put('pos_payment_line',['payment_id'=>$payment,'line_no'=>1,'payment_method_id'=>$method,'amount'=>100,'status'=>'PAID']);
        $put('fin_account_mutation_log',['mutation_no'=>$code.'-POS','mutation_date'=>$today,'account_id'=>$account,'mutation_type'=>'IN','amount'=>100,'balance_before'=>1000,'balance_after'=>1100,'ref_module'=>'POS','ref_table'=>'pos_payment','ref_id'=>$payment]);
        return ['reconciliation_date'=>$today,'revenue_date'=>$today,'payment_method_id'=>$method,'account_id'=>$account,'counter_account_id'=>$counter,'resolution_note'=>'Synthetic verified daily difference'];
    };
    $mutationFor=static function($lineId)use($db):array {
        $line=$db->get_where('fin_revenue_reconciliation_line',['id'=>$lineId])->row_array();
        return $db->get_where('fin_account_mutation_log',['id'=>(int)$line['mutation_id']])->row_array();
    };
    $resolved=static function(array $form,int $headerId)use($revenue,$today,$check):void {
        $rows=array_column($revenue->dashboard($today,$today,$headerId)['rows'],null,'payment_method_id');
        $check($rows[$form['payment_method_id']]['difference_amount']===0.,'integration dashboard remaining difference zero');
    };
    $verifyLedger=static function(int $id)use($purchase,$accountBalance,$today,$check):void {
        $rows=array_reverse($purchase->list_account_mutations($id,$today,$today,100));
        $previous=null;
        foreach($rows as $row) {
            $signed=$row['mutation_type']==='IN'?(float)$row['amount']:-(float)$row['amount'];
            $check(round((float)$row['balance_before']+$signed,2)===round((float)$row['balance_after'],2),'integration mutation ledger arithmetic');
            if($previous!==null)$check($previous===round((float)$row['balance_before'],2),'integration mutation ledger continuous');
            $previous=round((float)$row['balance_after'],2);
        }
        if($previous!==null)$check($previous===$accountBalance($id),'integration account balance equals latest ledger');
    };

    $form=$fixture()+['actual_amount'=>'90','resolution_type'=>'NONE'];
    $before=$overview();$cashBefore=$cashTotal();$count=$db->count_all('fin_account_mutation_log');
    $none=$success($revenue->save_line($form,1),'save NONE');
    $check($overview()===$before && $cashTotal()===$cashBefore && $db->count_all('fin_account_mutation_log')===$count,'integration NONE changes neither reports nor cash');
    $reject($revenue->post_line($none['line_id'],1),'NONE cannot post');

    $feeCase=[]; $ordinary=[];
    foreach(Finance_mutation_policy::categories() as $category=>$option) {
        foreach($option['direction']==='BOTH'?['IN','OUT']:[$option['direction']] as $direction) {
            $form=$fixture()+['actual_amount'=>$direction==='IN'?'110':'90','resolution_type'=>$direction,'report_category'=>$category];
            if(in_array($category,['PROMO_EXPENSE','PLATFORM_FEE'],true)) {
                $trace=Finance_settlement_control::trace($db,$today,$form['payment_method_id']);
                $caseId=$put('fin_settlement_control',['payment_method_id'=>$form['payment_method_id'],'revenue_date'=>$today,'account_id'=>$form['account_id'],'due_date'=>$today,'provider_reference'=>'INTEGRATION-'.$category,'source_fingerprint'=>$trace['source_fingerprint'],'expected_amount'=>100,'received_amount'=>90,'settlement_complete'=>1]);
                $chargeId=$put('fin_settlement_charge',['settlement_id'=>$caseId,'account_id'=>$form['account_id'],'charge_date'=>$today,'document_no'=>'INTEGRATION-'.$category,'line_reference'=>'1','identity_hash'=>hash('sha256',$category),'category'=>$category,'direction'=>$direction,'amount'=>10,'request_key'=>bin2hex(random_bytes(16)),'notes'=>'Synthetic fee']);
                $form+=['settlement_control_id'=>$caseId,'settlement_charge_id'=>$chargeId];
            }
            $before=$overview();$beforePnl=$pnl();$cashBefore=$cashTotal();
            $saved=$success($revenue->save_line($form,1),'save '.$category.' '.$direction);
            $check($overview()===$before && $cashTotal()===$cashBefore,'integration save never posts '.$category.' '.$direction);
            $success($revenue->post_line($saved['line_id'],1),'post '.$category.' '.$direction);
            $after=$overview();$afterPnl=$pnl();$mutation=$mutationFor($saved['line_id']);
            $sign=$direction==='IN'?10.:-10.;
            $profitDelta=$option['effect']==='balance'?0.:$sign;
            $bucket=$option['effect']==='balance'?'total_balance_only_'.strtolower($direction):($direction==='IN'?'total_other_income':'total_other_expense');
            $check($cashTotal()===$cashBefore+$sign && $after[$bucket]===$before[$bucket]+10.,'integration cash and report category agree '.$category.' '.$direction);
            $check($after['total_final_profit']===$before['total_final_profit']+$profitDelta && $after['total_sales']===$before['total_sales'],'integration monthly profit effect without duplicate sale '.$category.' '.$direction);
            $check($afterPnl['result']===$beforePnl['result']+$profitDelta && $afterPnl['basis']===$beforePnl['basis'],'integration management P&L changes only category effect '.$category.' '.$direction);
            $daily=array_column($report->financial_estimation_report((int)date('Y'),(int)date('n'))['rows'],null,'date');
            $check($daily[$today]['other_income_total']===$after['total_other_income'] && $daily[$today]['other_expense_total']===$after['total_other_expense'],'integration daily and monthly category totals agree');
            $listed=array_column($purchase->list_account_mutations($form['account_id'],$today,$today,100),null,'id');
            $check(isset($listed[$mutation['id']]) && $listed[$mutation['id']]['report_category']===$category && (float)$listed[$mutation['id']]['amount']===10.,'integration posted reconciliation discoverable in mutation page');
            $resolved($form,$saved['header_id']);$verifyLedger($form['account_id']);
            $count=$db->count_all('fin_account_mutation_log');
            $reject($revenue->post_line($saved['line_id'],1),'repeat '.$category);
            $check($overview()===$after && $db->count_all('fin_account_mutation_log')===$count,'integration repeat cannot duplicate reporting');
            if($category==='PLATFORM_FEE')$feeCase=compact('form','saved','mutation');
            if($category==='CASH_SHORTAGE')$ordinary=compact('form','saved','mutation');
        }
    }

    // A fee posted through revenue reconciliation cannot be charged again elsewhere.
    $cashBefore=$cashTotal();$before=$overview();$count=$db->count_all('fin_account_mutation_log');
    $duplicateCash=$success($cash->save_line(['reconciliation_date'=>$today,'account_id'=>$feeCase['form']['account_id'],
        'actual_balance'=>(string)($accountBalance($feeCase['form']['account_id'])-10.),'resolution_type'=>'OUT',
        'report_category'=>'PLATFORM_FEE','settlement_control_id'=>$feeCase['form']['settlement_control_id'],
        'settlement_charge_id'=>$feeCase['form']['settlement_charge_id'],'resolution_note'=>'Synthetic duplicate fee'],1),'prepare duplicate cash fee');
    $result=$cash->post_line((int)$duplicateCash['line']['id'],1);
    $reject($result,'cash fee already posted in revenue');
    $check(str_contains($result['message'],'sudah diposting'),'integration cash rejects exact fee identity, not an unrelated guard');
    $result=$purchase->apply_manual_account_mutation(['account_id'=>$feeCase['form']['account_id'],'mutation_date'=>$today,
        'mutation_type'=>'OUT','amount'=>10,'report_category'=>'PLATFORM_FEE','client_request_key'=>bin2hex(random_bytes(16)),
        'reference_no'=>'INTEGRATION-PLATFORM_FEE','settlement_confirmed'=>true,
        'settlement_control_id'=>$feeCase['form']['settlement_control_id'],'settlement_charge_id'=>$feeCase['form']['settlement_charge_id'],
        'notes'=>'Synthetic duplicate fee'],1);
    $reject($result,'manual fee already posted in revenue');
    $check(str_contains($result['message'],'sudah diposting'),'integration manual rejects exact fee identity, not an unrelated guard');
    $check($cashTotal()===$cashBefore && $overview()===$before && $db->count_all('fin_account_mutation_log')===$count,'integration cross-module duplicates change neither cash nor reports');

    // Reclassifying a reconciliation changes reports, not the posted cash chain.
    $before=$overview();$cashBefore=$cashTotal();
    $classification=['mutation_id'=>$ordinary['mutation']['id'],'expected_category'=>'CASH_SHORTAGE','report_category'=>'BALANCE_CORRECTION','reason'=>'Correction affects balance only'];
    $success($purchase->classify_account_mutation($classification,1),'reclassify daily shortfall');
    $after=$overview();
    $check($cashTotal()===$cashBefore && $after['total_other_expense']===$before['total_other_expense']-10. && $after['total_final_profit']===$before['total_final_profit']+10.,'integration reclassification only moves report buckets');
    $resolved($ordinary['form'],$ordinary['saved']['header_id']);
    $reject($purchase->classify_account_mutation($classification,1),'stale classification cannot replay');

    // Existing two-sided VOID of a linked fee restores reports/cash, then allows a new round.
    $before=$overview();$cashBefore=$cashTotal();
    $success($op->void_adjustment(['mutation_id'=>$feeCase['mutation']['id'],'reason'=>'Synthetic wrong fee'],1),'VOID linked daily fee');
    $after=$overview();
    $check($cashTotal()===$cashBefore+10. && $after['total_other_expense']===$before['total_other_expense']-10.,'integration VOID restores cash and excludes both entries from expense');
    $rows=array_column($revenue->dashboard($today,$today,$feeCase['saved']['header_id'])['rows'],null,'payment_method_id');
    $check($rows[$feeCase['form']['payment_method_id']]['difference_amount']===-10.,'integration VOID makes outstanding difference visible again');
    $success($op->void_adjustment(['mutation_id'=>$feeCase['mutation']['id'],'reason'=>'Retry'],1),'VOID retry is idempotent');
    $check($cashTotal()===$cashBefore+10. && $overview()===$after,'integration VOID retry changes nothing');
    $round=$success($revenue->create_round(['reconciliation_date'=>$today,'revenue_date'=>$today],1),'new round after VOID');
    $saved=$success($revenue->save_line($feeCase['form']+['reconciliation_id'=>$round['header']['id']],1),'save corrected fee in new round');
    $success($revenue->post_line($saved['line_id'],1),'repost corrected fee');
    $check($cashTotal()===$cashBefore && $overview()===$before,'integration corrected fee counted once after VOID');
    $verifyLedger($feeCase['form']['account_id']);

    foreach(['90','110'] as $actual) {
        $form=$fixture()+['actual_amount'=>$actual,'resolution_type'=>'TRANSFER'];
        $before=$overview();$cashBefore=$cashTotal();$beforePnl=$pnl();
        $saved=$success($revenue->save_line($form,1),'save standalone transfer '.$actual);
        $success($revenue->post_line($saved['line_id'],1),'post standalone transfer '.$actual);
        $check($overview()===$before && $cashTotal()===$cashBefore && $pnl()['result']===$beforePnl['result'],'integration transfer never changes total cash or profit');
        $primary=$mutationFor($saved['line_id']);
        $counter=$purchase->list_account_mutations($form['counter_account_id'],$today,$today,100);
        $check(count($counter)===1 && $counter[0]['mutation_type']!==$primary['mutation_type'] && (float)$counter[0]['amount']===(float)$primary['amount'],'integration both transfer legs listed on their accounts');
        $reject($purchase->classify_account_mutation(['mutation_id'=>$primary['id'],'expected_category'=>'','report_category'=>'OTHER_INCOME','reason'=>'Invalid transfer income'],1),'transfer cannot become income by classification');
        $reject($op->void_adjustment(['mutation_id'=>$primary['id'],'reason'=>'Invalid unilateral reversal'],1),'single transfer leg cannot use fee VOID');
        $reject($revenue->post_line($saved['line_id'],1),'transfer repeat');
        $resolved($form,$saved['header_id']);$verifyLedger($form['account_id']);$verifyLedger($form['counter_account_id']);
    }

    // Regression: transferring to an existing negative counter balance must not erase the debt.
    $form=$fixture();
    $db->where('id',$form['counter_account_id'])->update('fin_company_account',['opening_balance'=>-50,'current_balance'=>-50]);
    $cashBefore=$cashTotal();
    $saved=$success($cash->save_line(['reconciliation_date'=>$today,'account_id'=>$form['account_id'],'actual_balance'=>'1090','resolution_type'=>'TRANSFER','counter_account_id'=>$form['counter_account_id'],'resolution_note'=>'Synthetic cash transfer'],1),'save cash transfer to negative destination');
    $success($cash->post_line((int)$saved['line']['id'],1),'post cash transfer to negative destination');
    $check($cashTotal()===$cashBefore && $accountBalance($form['counter_account_id'])===-40.,'integration cash transfer cannot fabricate money by clamping negative destination');
    $verifyLedger($form['account_id']);$verifyLedger($form['counter_account_id']);
}
