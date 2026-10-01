<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Reuse the isolated socket-only MariaDB harness. Never load application DB config.
function finance_control_workspace_fixture($db, $context, $check, $root): void
{
    if (!str_starts_with($db->hostname, '/tmp/finance-mutation-test-') || $db->database !== 'fixture_finance') {
        throw new RuntimeException('Only the generated fixture database is allowed.');
    }
    define('FCPATH', $root . '/');
    $ddl = file_get_contents($root . '/sql/baseline/2026-09-05_clean_install_schema.sql');
    $tables = ['org_division','mst_operational_division','mst_item_category','mst_item','mst_uom','mst_purchase_type',
        'pur_purchase_order','pur_purchase_payment_plan','pur_purchase_receipt','pur_purchase_receipt_line',
        'pur_store_request','pur_store_request_line','pur_store_request_fulfillment','pur_store_request_fulfillment_line',
        'inv_stock_movement_log','sys_page','sys_menu','auth_role','auth_role_permission','pos_order','pos_refund',
        'mst_product_division','pos_order_line','pos_order_line_extra','pos_refund_line','mst_material','mst_component',
        'inv_division_monthly_stock','inv_division_monthly_opname','inv_component_monthly_stock','inv_component_monthly_opname',
        'inv_component_movement_log','inv_component_adjustment_line'];
    foreach ($tables as $table) {
        if (!preg_match('/CREATE TABLE `' . $table . '` \(.*?;\n/s', $ddl, $match)) { throw new RuntimeException('Missing DDL: '.$table); }
        $check($db->query($match[0]) !== false, 'schema '.$table);
    }
    if (!$db->field_exists('permissions_updated_at','auth_role')) { $db->query('ALTER TABLE auth_role ADD permissions_updated_at datetime NULL'); }
    $db->data_cache=[];
    // Fill required baseline fields with inert fixture values; report-relevant
    // fields are always explicit below. Foreign keys stay disabled for this fixture.
    $seq=0;
    $insert = static function (string $table, array $data) use ($db,&$seq): int {
        $seq++;
        foreach ($db->query('SHOW COLUMNS FROM `'.$table.'`')->result_array() as $col) {
            if (array_key_exists($col['Field'],$data) || $col['Null']==='YES' || $col['Default']!==null || str_contains($col['Extra'],'auto_increment')) { continue; }
            $type=$col['Type'];
            if (preg_match("/^enum\('([^']+)'/",$type,$m)) { $value=$m[1]; }
            elseif (preg_match('/int|decimal|float|double/',$type)) { $value=1; }
            elseif (preg_match('/date|timestamp/',$type)) { $value='2026-09-01'; }
            else { $value='F'.$seq; }
            $data[$col['Field']]=$value;
        }
        if (!$db->insert($table,$data)) { throw new RuntimeException('Insert '.$table.': '.json_encode($db->error())); }
        return (int)$db->insert_id();
    };
    $insert('org_division',['id'=>20,'division_code'=>'BAR','division_name'=>'BAR']);
    $insert('mst_operational_division',['id'=>2,'code'=>'BAR','name'=>'BAR']);
    $insert('mst_operational_division',['id'=>3,'code'=>'KITCHEN','name'=>'KITCHEN']);
    $insert('mst_item_category',['id'=>1,'name'=>'Coffee']);
    $insert('mst_item',['id'=>1,'item_name'=>'Coffee','item_category_id'=>1]);
    $insert('mst_uom',['id'=>1,'code'=>'GR']);
    $insert('mst_purchase_type',['id'=>1,'type_name'=>'Persediaan']);
    $insert('pur_purchase_order',['id'=>1,'po_no'=>'PO-DIRECT','status'=>'APPROVED','purchase_type_id'=>1,'currency_code'=>'IDR']);
    $insert('pur_purchase_payment_plan',['id'=>1,'purchase_order_id'=>1]);
    $profile=['item_id'=>1,'material_id'=>1,'profile_key'=>str_repeat('a',64),'profile_name'=>'Coffee <script>unsafe</script>',
        'profile_brand'=>'Brand A','buy_uom_id'=>1,'content_uom_id'=>1,'profile_buy_uom_code'=>'PACK','profile_content_uom_code'=>'GR'];
    $insert('pur_store_request',['id'=>1,'sr_no'=>'SR-PARTIAL','request_date'=>'2026-08-28','request_division_id'=>2,'destination_type'=>'BAR','status'=>'PARTIAL_FULFILLED']);
    $insert('pur_store_request_line',$profile+['id'=>1,'store_request_id'=>1,'line_no'=>1,'qty_content_requested'=>100,'qty_buy_requested'=>1]);
    foreach ([['2026-09-02','POSTED',30],['2026-09-03','POSTED',20],['2026-09-03','VOID',999],['2026-08-29','POSTED',10]] as $i=>$case) {
        $fid=$insert('pur_store_request_fulfillment',['id'=>$i+1,'store_request_id'=>1,'fulfillment_no'=>'SHIP-'.($i+1),'fulfillment_date'=>$case[0],'status'=>$case[1]]);
        $insert('pur_store_request_fulfillment_line',$profile+['fulfillment_id'=>$fid,'store_request_line_id'=>1,'qty_content_posted'=>$case[2],'qty_buy_posted'=>$case[2]/100,'unit_cost_snapshot'=>2]);
    }
    $insert('pur_store_request',['id'=>2,'sr_no'=>'SR-KITCHEN','request_date'=>'2026-09-01','request_division_id'=>3,'destination_type'=>'KITCHEN','status'=>'FULFILLED']);
    $insert('pur_store_request_line',array_replace($profile,['id'=>2,'store_request_id'=>2,'line_no'=>1,'qty_content_requested'=>7,'material_id'=>null]));
    $insert('pur_store_request_line',array_replace($profile,['id'=>3,'store_request_id'=>2,'line_no'=>2,'qty_content_requested'=>10,'line_status'=>'CANCELLED']));
    $insert('pur_store_request_fulfillment',['id'=>5,'store_request_id'=>2,'fulfillment_no'=>'SHIP-5','fulfillment_date'=>'2026-09-03','status'=>'POSTED']);
    $insert('pur_store_request_fulfillment_line',array_replace($profile,['fulfillment_id'=>5,'store_request_line_id'=>2,'qty_content_posted'=>7,'unit_cost_snapshot'=>3,'material_id'=>null,'usage_purpose'=>'KONSUMABEL']));
    foreach ([['DIVISION','POSTED','BAHAN_BAKU',false],['WAREHOUSE','POSTED','BAHAN_BAKU',false],['DIVISION','VOID','BAHAN_BAKU',false],['DIVISION','POSTED','KONSUMABEL',false],['DIVISION','POSTED','BAHAN_BAKU',true]] as $i=>$case) {
        $rid=$insert('pur_purchase_receipt',['purchase_order_id'=>1,'receipt_no'=>'REC-'.$i,'receipt_date'=>'2026-09-04','status'=>$case[1]]);
        $rlid=$insert('pur_purchase_receipt_line',['purchase_receipt_id'=>$rid,'purchase_order_line_id'=>$i+1,'buy_uom_id'=>1,'usage_purpose'=>$case[2]]);
        $mid=$insert('inv_stock_movement_log',$profile+['movement_no'=>'MOV-'.$i,'movement_date'=>'2026-09-04','movement_scope'=>$case[0],
            'division_id'=>2,'destination_type'=>'BAR','movement_type'=>'PURCHASE_IN','ref_table'=>'pur_purchase_receipt','ref_id'=>$rid,
            'receipt_line_id'=>$rlid,'qty_content_delta'=>10,'unit_cost'=>3]);
        if ($case[3]) { $insert('inv_stock_movement_log',$profile+['movement_no'=>'REVERSE-'.$i,'movement_date'=>'2026-09-05','movement_scope'=>'DIVISION','movement_type'=>'VOID_REVERSE','reversal_of_movement_id'=>$mid,'qty_content_delta'=>-10]); }
    }
    $insert('fin_company_account',['id'=>1,'account_code'=>'CASH','account_name'=>'Cash','opening_balance'=>100,'currency_code'=>'IDR']);
    $insert('fin_company_account',['id'=>2,'account_code'=>'OLD','account_name'=>'Inactive bank','opening_balance'=>0,'currency_code'=>'IDR','is_active'=>0]);
    $insert('fin_company_account',['id'=>3,'account_code'=>'USD','account_name'=>'USD','opening_balance'=>0,'currency_code'=>'USD']);
    $mutation = static function (array $data) use($insert): int {
        return $insert('fin_account_mutation_log',$data+['mutation_no'=>'M-'.bin2hex(random_bytes(4)),'mutation_date'=>'2026-09-02','account_id'=>1,'amount'=>1]);
    };
    $original=$mutation(['mutation_date'=>'2026-08-20','mutation_type'=>'IN','amount'=>10,'ref_module'=>'FINANCE','report_category'=>'OTHER_INCOME']);
    $mutation(['mutation_type'=>'OUT','amount'=>10,'ref_module'=>'FINANCE','reversal_of_mutation_id'=>$original]);
    $mutation(['mutation_type'=>'IN','amount'=>200,'ref_module'=>'POS']);
    $mutation(['mutation_type'=>'OUT','amount'=>40,'ref_module'=>'PURCHASE','ref_table'=>'pur_purchase_payment_plan','ref_id'=>1]);
    $mutation(['mutation_type'=>'OUT','amount'=>50,'ref_module'=>'FINANCE_TRANSFER']);
    $mutation(['account_id'=>2,'mutation_type'=>'IN','amount'=>50,'ref_module'=>'FINANCE_TRANSFER']);
    $mutation(['mutation_type'=>'IN','amount'=>1]);
    $mutation(['account_id'=>3,'mutation_type'=>'IN','amount'=>9999,'ref_module'=>'POS']);
    $today=date('Y-m-d');
    $mutation(['mutation_date'=>$today,'mutation_type'=>'IN','amount'=>80,'ref_module'=>'POS']);
    $prior=date('Y-m-d',strtotime(date('Y-m-01').' -1 month'));
    $mutation(['mutation_date'=>$prior,'mutation_type'=>'IN','amount'=>20,'ref_module'=>'POS']);

    // Balanced July movements test classification without changing September opening.
    $deposit=$insert('pos_payment',['payment_no'=>'DP','payment_type'=>'DEPOSIT','payment_status'=>'PAID']);
    $depositLine=$insert('pos_payment_line',['payment_id'=>$deposit,'line_no'=>1,'amount'=>60]);
    $dp=$mutation(['mutation_date'=>'2026-07-10','mutation_type'=>'IN','amount'=>60,'ref_module'=>'POS','ref_table'=>'pos_payment_line','ref_id'=>$depositLine]);
    $mutation(['mutation_date'=>'2026-07-11','mutation_type'=>'OUT','amount'=>60,'ref_module'=>'POS','reversal_of_mutation_id'=>$dp]);
    foreach ([['FINANCE_PAYABLE','fin_payable',100],['PAYROLL','pay_cash_advance',30],['PURCHASE','pur_purchase_payment_plan',40]] as [$module,$table,$amount]) {
        foreach(['IN','OUT'] as $direction) {$mutation(['mutation_date'=>'2026-07-12','mutation_type'=>$direction,'amount'=>$amount,'ref_module'=>$module,'ref_table'=>$table,'ref_id'=>1]);}
    }
    foreach(['OWNER_CAPITAL','BALANCE_CORRECTION'] as $category) foreach(['IN','OUT'] as $direction){
        $mutation(['mutation_date'=>'2026-07-12','mutation_type'=>$direction,'amount'=>20,'ref_module'=>'FINANCE','report_category'=>$category]);
    }
    $oldSale=$insert('pos_order',['order_no'=>'JUNE','status'=>'REFUND_PARTIAL','paid_at'=>'2026-06-05','grand_total'=>200,'paid_total'=>500]);
    $augSale=$insert('pos_order',['order_no'=>'AUG-SALE','status'=>'REFUND_PARTIAL','paid_at'=>'2026-08-05','grand_total'=>90,'paid_total'=>110,'tax_amount'=>10]);
    $splitSale=$insert('pos_order',['order_no'=>'SPLIT-SALE','status'=>'PAID','paid_at'=>'2026-09-05','grand_total'=>220,'paid_total'=>220,'tax_amount'=>20]);
    $sepSale=$insert('pos_order',['order_no'=>'SEP-REFUND','status'=>'REFUND_PARTIAL','paid_at'=>'2026-09-06','grand_total'=>40,'paid_total'=>100]);
    foreach([[$oldSale,'2026-07-05',300],[$augSale,'2026-09-07',20],[$sepSale,'2026-09-08',60]] as [$order,$date,$amount]){
        $insert('pos_refund',['order_id'=>$order,'refund_status'=>'POSTED','refunded_at'=>$date,'refund_amount'=>$amount]);
    }
    $insert('pos_refund',['order_id'=>$splitSale,'refund_status'=>'VOID','refunded_at'=>'2026-09-08','refund_amount'=>999]);
    $payment=$insert('pos_payment',['payment_no'=>'SPLIT','order_id'=>$splitSale,'payment_type'=>'FINAL','payment_status'=>'PAID']);
    foreach([1,2] as $line){$insert('pos_payment_line',['payment_id'=>$payment,'line_no'=>$line,'amount'=>110]);}
    foreach(['VOID','PAID_PARTIAL','DRAFT','PENDING'] as $status){$insert('pos_order',['status'=>$status,'paid_at'=>'2026-09-09','grand_total'=>999,'paid_total'=>999]);}
    $insert('pos_order',['status'=>'PAID','paid_at'=>null,'grand_total'=>999]);

    $insert('mst_product_division',['id'=>1,'code'=>'BAR','name'=>'BAR','pos_scope'=>'REGULAR']);
    $insert('mst_product_division',['id'=>2,'code'=>'EVENT','name'=>'EVENT','pos_scope'=>'EVENT']);
    $aLine=$insert('pos_order_line',['order_id'=>$augSale,'line_no'=>1,'operational_division_id'=>2,'product_division_id_snapshot'=>1,'net_amount'=>90]);
    $splitRegular=$insert('pos_order_line',['order_id'=>$splitSale,'line_no'=>1,'operational_division_id'=>2,'product_division_id_snapshot'=>1,'net_amount'=>110]);
    $splitEvent=$insert('pos_order_line',['order_id'=>$splitSale,'line_no'=>2,'operational_division_id'=>3,'product_division_id_snapshot'=>2,'net_amount'=>90]);
    $insert('pos_order_line_extra',['order_id'=>$splitSale,'order_line_id'=>$splitEvent,'line_no'=>1,'net_amount'=>20]);
    $cLine=$insert('pos_order_line',['order_id'=>$sepSale,'line_no'=>1,'operational_division_id'=>3,'product_division_id_snapshot'=>2,'net_amount'=>40]);
    foreach([[$augSale,$aLine,20],[$sepSale,$cLine,60]] as [$order,$line,$amount]){
        $refund=(int)$db->query('SELECT id FROM pos_refund WHERE order_id=? AND refund_status=?',[$order,'POSTED'])->row()->id;
        $insert('pos_refund_line',['refund_id'=>$refund,'line_no'=>1,'line_type'=>'PRODUCT','order_line_id'=>$line,'amount_refunded'=>$amount]);
    }
    $insert('mst_material',['id'=>1,'material_name'=>'Coffee']);
    $insert('mst_component',['id'=>1,'component_name'=>'Base tea','component_type'=>'BASE']);
    $insert('inv_division_monthly_stock',$profile+['month_key'=>'2026-09-01','division_id'=>2,'destination_type'=>'BAR','identity_key'=>str_repeat('a',64),
        'opening_total_value'=>100,'in_total_value'=>130,'out_total_value'=>40,'waste_total_value'=>5,'spoilage_total_value'=>10,
        'process_loss_total_value'=>2,'variance_total_value'=>3,'adjustment_plus_total_value'=>5,'total_value'=>175]);
    foreach([['2026-08-31',100],['2026-09-30',175]] as [$month,$amount]){$insert('inv_division_monthly_opname',$profile+['month_key'=>$month,'division_id'=>2,'destination_type'=>'BAR','total_value'=>$amount]);}
    $insert('inv_component_monthly_stock',['month_key'=>'2026-09-01','division_id'=>2,'location_type'=>'BAR','component_id'=>1,'uom_id'=>1,
        'opening_total_value'=>20,'in_total_value'=>10,'out_total_value'=>5,'waste_total_value'=>1,'spoil_total_value'=>1,'total_value'=>23]);
    foreach([['2026-08-31',20],['2026-09-30',23]] as [$month,$amount]){$insert('inv_component_monthly_opname',['month_key'=>$month,'division_id'=>2,'location_type'=>'BAR','component_id'=>1,'uom_id'=>1,'total_value'=>$amount]);}
    $insert('inv_component_monthly_stock',['month_key'=>'2026-09-01','division_id'=>3,'location_type'=>'KITCHEN_EVENT','component_id'=>1,'uom_id'=>1,'opening_total_value'=>-100,'total_value'=>50]);
    foreach([['USAGE_OUT',-20,null],['WASTE_OUT',-5,'WASTE'],['SPOIL_OUT',-5,'SPOILAGE'],['PROCESS_LOSS_OUT',-1,'PROCESS_LOSS'],['VARIANCE_OUT',-1.5,'VARIANCE']] as [$type,$qty,$category]){
        $id=$insert('inv_stock_movement_log',$profile+['movement_scope'=>'DIVISION','division_id'=>2,'destination_type'=>'BAR','movement_date'=>'2026-09-10','movement_type'=>$type,
            'qty_content_delta'=>$qty,'unit_cost'=>2,'adjustment_category'=>$category,'adjustment_reason_code'=>'fixture_reason']);
        if($type==='WASTE_OUT'){$insert('inv_stock_movement_log',$profile+['movement_scope'=>'DIVISION','division_id'=>2,'destination_type'=>'BAR','movement_date'=>'2026-09-11',
            'movement_type'=>'VOID_REVERSE','qty_content_delta'=>2.5,'unit_cost'=>2,'reversal_of_movement_id'=>$id]);}
    }
    $insert('inv_stock_movement_log',$profile+['movement_scope'=>'WAREHOUSE','movement_date'=>'2026-09-10','movement_type'=>'WASTE_OUT','qty_content_delta'=>-999,'unit_cost'=>2]);
    $cw=$insert('inv_component_movement_log',['division_id'=>2,'location_type'=>'BAR','component_id'=>1,'uom_id'=>1,'movement_date'=>'2026-09-10','movement_type'=>'WASTE','qty_out'=>2,'unit_cost'=>1,'total_cost'=>2]);
    $insert('inv_component_movement_log',['division_id'=>2,'location_type'=>'BAR','component_id'=>1,'uom_id'=>1,'movement_date'=>'2026-09-11','movement_type'=>'VOID_REVERSE','qty_in'=>1,'unit_cost'=>1,'total_cost'=>1,'reversal_of_movement_id'=>$cw]);
    $insert('inv_component_movement_log',['division_id'=>2,'location_type'=>'BAR','component_id'=>1,'uom_id'=>1,'movement_date'=>'2026-09-10','movement_type'=>'SPOIL','qty_out'=>1,'unit_cost'=>1,'total_cost'=>1]);

    // RBAC migration: copy only known read/export privileges and preserve overrides.
    foreach ([['SUPERADMIN',null],['VIEWER',null],['DIVISION',20]] as $i=>$role) {
        $insert('auth_role',['id'=>$i+1,'role_code'=>$role[0],'division_scope_id'=>$role[1]]);
    }
    foreach (['procurement.store_request.index','purchase.report.index','finance.accounting.index','finance.control.index'] as $code) {
        $pid=$insert('sys_page',['page_code'=>$code]);
        foreach ([1,2,3] as $rid) { $insert('auth_role_permission',['role_id'=>$rid,'page_id'=>$pid,'can_view'=>1,'can_export'=>0]); }
    }
    foreach (['grp.purchase','grp.finance'] as $code) { $insert('sys_menu',['menu_code'=>$code]); }
    $sql=preg_replace('/^--.*$/m','',file_get_contents($root.'/sql/2026-10-01a_monthly_reporting_workspace.sql'));
    $migrate=static function () use($db,$sql,$check): void {
        foreach(explode(';',$sql) as $statement) { if(trim($statement)!=='') { $check($db->query($statement)!==false,'report metadata migration'); } }
    };
    $migrate();
    $newWhere="p.page_code IN ('procurement.sr_report.index','procurement.material_report.index','finance.monthly_analysis.index')";
    $permissions=$db->query("SELECT a.*,p.page_code FROM auth_role_permission a JOIN sys_page p ON p.id=a.page_id WHERE $newWhere ORDER BY a.role_id,p.page_code")->result_array();
    $check(count($permissions)===8,'three global reports and two scoped procurement reports seeded');
    foreach($permissions as $perm) {
        $check((int)$perm['can_view']===1 && (int)$perm['can_export']===((int)$perm['role_id']===1?1:0),'least-privilege permission '.$perm['role_id'].' '.$perm['page_code']);
        $check(!(int)$perm['can_create'] && !(int)$perm['can_edit'] && !(int)$perm['can_delete'],'reports have no write capabilities');
    }
    $db->query("UPDATE auth_role_permission a JOIN sys_page p ON p.id=a.page_id SET a.can_view=0 WHERE a.role_id=2 AND p.page_code='procurement.sr_report.index'");
    $migrate();
    $check((int)$db->query("SELECT a.can_view FROM auth_role_permission a JOIN sys_page p ON p.id=a.page_id WHERE a.role_id=2 AND p.page_code='procurement.sr_report.index'")->row()->can_view===0,'rerun preserves administrator denial');
    $check((int)$db->query('SELECT COUNT(*) n FROM sys_menu')->row()->n===5,'exactly three new menus, idempotent');

    require APPPATH.'models/Procurement_report_model.php';
    require APPPATH.'models/Monthly_finance_model.php';
    require APPPATH.'models/Finance_inventory_analysis_model.php';
    $proc=new Procurement_report_model(); $fin=new Monthly_finance_model();
    $f=Report_workspace::filters(['month'=>'2026-09','compare'=>'2026-08']);
    $check(Report_workspace::month('2024-02')[1]==='2024-02-29','leap month');
    $check(Report_workspace::filters(['month'=>'2026-01'])['compare']==='2025-12','year rollover');
    foreach ([['month'=>'2026-13'],['month'=>'2026-02','day'=>'2026-02-30'],['q'=>['unsafe']]] as $bad) {
        try { Report_workspace::filters($bad); $check(false,'invalid filter accepted'); } catch(InvalidArgumentException $e) { $check(true,'invalid filter rejected'); }
    }
    $check(Report_workspace::csvCell("\t=HYPERLINK(1)")[0]==="'",'CSV formula injection neutralized');
    $check(Report_workspace::change(1,0)==='Belum ada basis pembanding','zero comparator never divides by zero');
    $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $check($proc->scopedDivision(20)===2,'role org ID mapped to operational ID by code');
    try { $proc->scopedDivision(999); $check(false,'invalid scope'); } catch(RuntimeException $e) { $check(true,'unknown division fails closed'); }
    $sr=$proc->report('sr',$f);
    $check($sr['totals']['lines']===3 && $sr['totals']['documents']===2 && $sr['totals']['value']===12100,'SR partial posted shipments, no VOID, unique documents');
    $request=$proc->report('sr',Report_workspace::filters(['month'=>'2026-08','basis'=>'request']));
    $check(count($request['rows'])===1 && (float)$request['rows'][0]['qty_content']===100.0 && (float)$request['rows'][0]['fulfilled_qty']===60.0 && (float)$request['rows'][0]['pending_qty']===40.0,'request quantity never multiplied by partial shipments');
    $cancelled=$proc->report('sr',array_replace($f,['basis'=>'request']));
    $cancelled=array_values(array_filter($cancelled['rows'],static fn($r)=>$r['line_status']==='CANCELLED'));
    $check(count($cancelled)===1 && (float)$cancelled[0]['pending_qty']===0.0,'cancelled request line not shown as outstanding');
    $material=$proc->report('materials',$f);
    $check($material['totals']['value']===13000 && $material['totals']['purchase']===3000 && $material['totals']['sr']===10000,'combined excludes warehouse, reversed, VOID, consumable sources');
    $check(count($proc->report('sr',array_replace($f,['division_id'=>3]))['rows'])===1,'division filter applied to all detail and aggregates');
    $check(count($proc->report('materials',array_replace($f,['source'=>'PURCHASE']))['rows'])===1,'purchase source drill-down');
    $check(count($proc->report('sr',array_replace($f,['q'=>"%' OR 1=1 --"]))['rows'])===0,'SQL injection and LIKE wildcard search treated as data');
    $cash=$fin->report($f);
    // The dynamic comparison fixture is September only when run in October 2026.
    $extra=substr($prior,0,7)==='2026-09'?2000:0;
    $check($cash['totals']['in']===20100+$extra && $cash['totals']['out']===5000,'cash in/out excludes transfers and USD');
    $check($cash['totals']['transfer_in']===5000 && $cash['totals']['transfer_out']===5000,'internal transfer shown separately');
    $check($cash['totals']['reversals']===1 && $cash['totals']['unclassified']===1,'dated reversal and unclassified retained');
    $check($cash['balances']['total']['opening']===11000,'opening includes pre-month movement');
    $check($cash['balances']['total']['closing']===26100+$extra,'opening plus entire cash ledger equals closing');
    $check($cash['compare']['in']===1000,'previous month original not retrospectively removed');
    $groups=array_column($cash['groups'],null,'code');
    $check(isset($groups['PURCHASE:1']) && $groups['PURCHASE:1']['out']===4000,'payment plan classified under actual PO purchase type');
    $filtered=$fin->report(array_replace($f,['category'=>'PURCHASE:1']));
    $check(count($filtered['rows'])===1 && $filtered['balances']===$cash['balances'],'detail filter does not corrupt account balance equation');
    $check($fin->report(array_replace($f,['currency'=>'USD']))['totals']['in']===999900,'currency isolated');
    try { $fin->report(array_replace($f,['account_id'=>3])); $check(false,'mixed currency'); } catch(InvalidArgumentException $e) { $check(true,'account currency mismatch rejected'); }
    $check($fin->report(array_replace($f,['account_id'=>2]))['totals']['transfer_in']===5000,'inactive account remains in history');
    $running=$fin->report(Report_workspace::filters([]));
    $check($running['totals']['in']>=8000 && $running['compare']['in']>=2000,'running month and elapsed-day comparison');
    $ff=Report_workspace::financialFilters(['month'=>'2026-09','month_from'=>'2026-07','month_to'=>'2026-09']);
    $cash=$fin->report($ff);
    $check($cash['months']===['2026-07','2026-08','2026-09'],'exact selected months, not a hard-coded window');
    $check($cash['sales']['totals']===['billing'=>32000,'tax'=>2000,'refund'=>8000,'value'=>22000,'orders'=>2],'actual net revenue excludes tax, canceled/unpaid sales and split-payment duplication');
    $check(array_column($cash['monthly'],'omzet')===[-30000,10000,22000],'refund recognized in its own month including a negative-revenue month');
    $sources=array_column($cash['matrix'],null,'key');
    $check($sources['OTHER_INCOME:IN']['values']['2026-08']===1000 && $sources['OTHER_INCOME:IN']['values']['2026-09']===0,'source present only in prior month retained and zero-filled');
    $july=$fin->report(array_replace($ff,['month'=>'2026-07']));
    $check($july['totals']['debt_in']===10000 && $july['totals']['business_out']===4000,'loans and kasbon excluded from business cash expenditure');
    $check($july['flows']['deposit']['in']===6000 && $july['flows']['deposit']['out']===6000,'deposit and its reversal classified through original payment line');
    $check($july['flows']['receivable']['out']===3000 && $july['flows']['equity']['in']===2000 && $july['flows']['correction']['in']===2000,'employee advances, capital and corrections separated');
    $check($cash['sales']['totals']['value']!==$cash['totals']['in'],'cash income never substituted for revenue');
    $salesDetail=$fin->report(array_replace($ff,['view'=>'sales']));
    $check(count($salesDetail['sales']['rows'])===4 && array_sum(array_column($salesDetail['sales']['rows'],'value_cents'))===22000,'sales drill-down sums exactly to net revenue');
    $dayDetail=$fin->report(array_replace($ff,['view'=>'sales','day'=>'2026-09-07']));
    $check($dayDetail['sales']['totals']['value']===-2000 && count($dayDetail['sales']['rows'])===1 && array_column($dayDetail['monthly'],'omzet')===array_column($cash['monthly'],'omzet'),'day filters details without changing month matrix');
    foreach([['account_id'=>1],['category'=>'PURCHASE:1'],['flow'=>'debt'],['q'=>'M-'],['direction'=>'IN'],['currency'=>'USD']] as $filter){
        $filtered=$fin->report(array_replace($ff,$filter));
        $check(!$filtered['sales']['available'] && $filtered['monthly'][0]['omzet']===null,'cash filter cannot misrepresent whole-order revenue');
    }
    foreach($cash['monthly'] as $month){
        foreach(['IN'=>'in','OUT'=>'out'] as $direction=>$field){
            $sum=0;foreach($cash['matrix'] as $source){if($source['direction']===$direction){$sum+=$source['values'][$month['label']];}}
            $check($sum===$month[$field]+$month['transfer_'.$field],'monthly matrix reconciles to cash incl transfers');
        }
    }
    $one=$fin->report(Report_workspace::financialFilters(['month_from'=>'2026-09','month_to'=>'2026-09']));
    $check(count($one['months'])===1 && !$one['compare_available'],'one-month range does not invent comparison');
    $empty=$fin->report(Report_workspace::financialFilters(['month_from'=>'2025-01','month_to'=>'2025-02']));
    $check(count($empty['months'])===2 && count($empty['matrix'])===0 && $empty['sales']['available'] && $empty['sales']['totals']['value']===0,'empty months retained and no revenue distinguished from unavailable');
    foreach([['month_from'=>'2025-09','month_to'=>'2026-09'],['month_from'=>'2026-10','month_to'=>'2026-09'],['month_from'=>['bad']]] as $invalid){
        try{Report_workspace::financialFilters($invalid);$check(false,'invalid range accepted');}catch(InvalidArgumentException $e){$check(true,'invalid range rejected');}
    }
    $page=Report_workspace::page(range(1,130),array_replace($f,['page'=>999]));
    $check($page['count']===130 && $page['page']===3 && count($page['rows'])===30,'pagination never clips totals or export rows');
    $inventoryModel=new Finance_inventory_analysis_model();$inventory=$inventoryModel->report($ff);
    $scope=array_column($inventory['focus_groups'],null,'key');
    $bar=$scope['2026-09|2|REGULAR'];$event=$scope['2026-09|3|EVENT'];
    $check($bar['revenue']===8000 && $event['revenue']===14000,'event products in regular orders allocated to event snapshot scope including extras/refunds');
    $check(array_sum(array_column($inventory['focus_groups'],'revenue'))===22000,'division allocation reconciles exactly to overall revenue');
    $check(Finance_division_revenue::allocate(1,[2=>1,3=>1])===[2=>1,3=>0] && array_sum(Finance_division_revenue::allocate(-11,[2=>1,3=>2]))===-11,'cent allocation deterministic for positive and negative amounts');
    $check($bar['supply']===13000 && $bar['used']===4000,'procurement values match material report; routine usage excludes losses');
    $check($bar['material']['opname_before']['value']===10000 && $bar['material']['opname_after']['value']===17500 && $bar['material']['reconciliation_gap']===0,'month-end opname keys and stock bridge reconcile');
    $check((float)$bar['usage_ratio']===50.0 && $event['component']['negative_profiles']===1 && $event['material']===null,'valid usage ratio, negative component history and missing material ledger not hidden');
    $check($inventory['loss_totals']['waste']===600 && $inventory['loss_totals']['spoil']===1100 && $inventory['loss_totals']['process_loss']===200 && $inventory['loss_totals']['correction']===300,'waste and spoil separate from process loss and stock discrepancy; warehouse excluded');
    $check($inventory['loss_totals']['reversals']===2,'dated waste reversals reduce original categories once');
    $filteredInventory=$inventoryModel->report(array_replace($ff,['division_id'=>3,'location'=>'EVENT']));
    $check(count($filteredInventory['focus_groups'])===1 && !$filteredInventory['losses'],'division/location filters apply to revenue, stock and loss details');
    $filteredInventory=$inventoryModel->report(array_replace($ff,['inventory_kind'=>'COMPONENT','loss_kind'=>'WASTE']));
    $check($filteredInventory['loss_totals']['waste']===100 && count($filteredInventory['losses'])===2,'component waste filter keeps original and reversal, not unrelated spoil');
    $check($inventoryModel->report(array_replace($ff,['asset_q'=>"%' OR 1=1 --"]))['losses']===[],'inventory text filtering treats hostile input as data');
    $check(count(Report_workspace::monthOptions($ff,'2025-01'))>=21 && Report_workspace::monthLabel('2026-09')==='September 2026','month dropdown includes historical data and Indonesian labels');
    $check(Report_workspace::financialFilters(array_replace($ff,['view'=>'efficiency','day'=>'2026-09-01']))['day']==='' && Report_workspace::financialFilters(array_replace($ff,['view'=>'waste','day'=>'2026-09-01']))['day']==='','inventory tabs cannot label whole-month snapshots as a single day');
    $check($bar['usage_reliable'] && $bar['usage_gap']===0 && !$event['usage_reliable'],'usage quality is explicit and reconciles stock outgoing with usage plus transfers');
    $comparison=Monthly_finance_sales::comparison($db,$ff);
    $check($comparison['net']!==$cash['sales']['totals']['value'],'POS order-cohort refund basis remains distinct from recognition-date refund');
    $db->query('ROLLBACK');

    // Deliberately inconsistent snapshots stay confined to this disposable DB.
    $db->query('START TRANSACTION');
    try {
        $db->query('UPDATE pos_order SET paid_total=180,grand_total=170,tax_amount=0 WHERE id=?',[$splitSale]);
        $db->query('UPDATE pos_order_line SET net_amount=90 WHERE id=?',[$splitRegular]);
        $discountRefund=$insert('pos_refund',['order_id'=>$splitSale,'refund_status'=>'POSTED','refunded_at'=>'2026-09-10','refund_amount'=>10]);
        $insert('pos_refund_line',['refund_id'=>$discountRefund,'line_no'=>1,'line_type'=>'PRODUCT','order_line_id'=>$splitRegular,'amount_refunded'=>10,'gross_amount_refunded'=>20]);
        $discounted=array_column(Finance_division_revenue::read($db,'2026-09-01','2026-09-30')['rows'],'revenue','division_id');
        $check($discounted[2]===6000 && $discounted[3]===13000,'discounted refund restores gross line allocation weights but deducts only cash refunded');
    } finally { $db->query('ROLLBACK'); }
    $db->query('START TRANSACTION');
    try {
        $db->query("UPDATE inv_division_monthly_stock SET opening_total_value=opening_total_value-10,adjustment_plus_total_value=adjustment_plus_total_value+10 WHERE division_id=2");
        $bad=array_column($inventoryModel->report($ff)['focus_groups'],null,'key')['2026-09|2|REGULAR'];
        $check($bad['material']['reconciliation_gap']===0 && $bad['material']['opening_gap']===-1000 && $bad['usage_ratio']===null,'opname opening mismatch blocks usage ratio even when book equation balances');
    } finally { $db->query('ROLLBACK'); }
    $db->query('START TRANSACTION');
    try {
        $db->query("UPDATE inv_division_monthly_stock SET out_total_value=out_total_value+10,total_value=total_value-10 WHERE division_id=2");
        $db->query("UPDATE inv_division_monthly_opname SET total_value=total_value-10 WHERE division_id=2 AND month_key='2026-09-30'");
        $bad=array_column($inventoryModel->report($ff)['focus_groups'],null,'key')['2026-09|2|REGULAR'];
        $check($bad['material']['reconciliation_gap']===0 && $bad['material']['closing_gap']===0 && $bad['usage_gap']===1000 && $bad['usage_ratio']===null,'movement/book outgoing mismatch blocks usage ratio even when opnames match');
    } finally { $db->query('ROLLBACK'); }

    if (in_array('--export-ui',$GLOBALS['argv'],true)) {
        $dir='/tmp/finance-report-ui-'.bin2hex(random_bytes(6)); mkdir($dir,0700);
        $renderer=new class($context) {
            public $load;
            private array $shared=[];
            public function __construct($context) { $this->load=$this; }
            public function view(string $view,array $data=[]): void { extract(array_replace($this->shared,$data),EXTR_SKIP); require APPPATH.'views/'.$view.'.php'; }
            public function render(string $view,array $data): string {
                $this->shared=$data+['current_user'=>['is_superadmin'=>true],'user_perms'=>[]];
                ob_start(); $this->view('reports/'.$view); return ob_get_clean();
            }
        };
        foreach(['sr','materials','financial'] as $kind) foreach($kind==='financial'?['overview','matrix','detail','sales','reconcile','efficiency','waste','balances']:['overview','matrix','detail'] as $view) {
            $filters=array_replace($kind==='financial'?$ff:$f,['view'=>$view]);
            $data=['kind'=>$kind,'filters'=>$filters,'report'=>$kind==='financial'?($view==='sales'?$salesDetail:$cash):($kind==='sr'?$sr:$material),
                'error'=>'','page_title'=>$kind==='sr'?'Laporan SR':'Bahan Baku ke Divisi','can_export'=>true,'can_sr'=>true,'can_purchase'=>true,
                'divisions'=>$proc->divisions(),'accounting'=>null,'accounting_error'=>'','can_accounting'=>false,'can_pos'=>true,
                'month_options'=>Report_workspace::monthOptions($ff),'inventory'=>$inventory,'comparison'=>$comparison];
            $html=$renderer->render($kind==='financial'?'financial':'procurement',$data);
            $check(!str_contains($html,'<script>unsafe</script>'),'view escapes item names');
            file_put_contents($dir.'/'.$kind.'-'.$view.'.html',$html);
            $data['can_export']=false;
            $readonly=$renderer->render($kind==='financial'?'financial':'procurement',$data);
            $check(!str_contains($readonly,'export=csv'),'export button hidden without export capability');
            $check(!str_contains($readonly,'export=matrix'),'matrix export also hidden without capability');
        }
        echo 'UI_FIXTURE='.$dir.PHP_EOL;
    }
    echo 'Monthly reporting fixture completed.'.PHP_EOL;
}
$argv[]='--mysql-fixture';
require __DIR__.'/finance_mutation_reporting_smoke.php';
