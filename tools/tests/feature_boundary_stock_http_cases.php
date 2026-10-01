<?php
// ONLY included after authenticated Starter sale in the disposable HTTPS acceptance harness.
if(!isset($base,$browser,$insert,$headers)||!preg_match('#\A/var/lib/finance-portable-[a-f0-9]{16}\z#D',$base))throw new RuntimeException('DISPOSABLE_FIXTURE_REQUIRED');
$createConfirmed=static function(int $pid)use($browser,$check,$headers,$outlet,$terminal):int{
 $r=$browser('/pos/orders/draft/save',['outlet_id'=>$outlet,'terminal_id'=>$terminal,'service_type'=>'DINE_IN','lines'=>[['product_id'=>$pid,'qty'=>1]]],$headers);
 $check($r['status']===200&&($r['json']['id']??0)>0,'Starter creates regression order');$id=(int)$r['json']['id'];
 $r=$browser('/pos/order_draft_confirm/'.$id,[],$headers);$check($r['status']===200&&!empty($r['json']['ok']),'Starter confirms regression order '.json_encode($r['json']));return $id;
};
$reverse=static function(int $id,string $action)use($browser,$check,$headers,$fixture,$method):void{
 $r=$browser('/pos/orders/reversal-step-up/verify',['order_id'=>$id,'action'=>$action,'password'=>$fixture['owner']['password']],$headers);
 $check($r['status']===200&&is_string($r['json']['step_up_proof']??null),'Starter obtains '.$action.' one-use password verification');
 $proof=$r['json']['step_up_proof'];
 $r=$browser('/pos/orders/'.strtolower($action).'/save',['order_id'=>$id,'step_up_proof'=>$proof,'return_to_stock'=>true,'processed_state'=>'NOT_PROCESSED','payment_method_id'=>$method,'reason'=>'Disposable Starter regression'], $headers);
 $check($r['status']===200&&!empty($r['json']['ok']),'Starter '.$action.' does not depend on paid Inventory/Finance screens '.json_encode($r['json']));
};
$voidOrder=$createConfirmed($product);$reverse($voidOrder,'VOID');
$check($pdo->query('SELECT status FROM pos_order WHERE id='.$voidOrder)->fetchColumn()==='VOID','no-recipe order really voided');
$refundOrder=$createConfirmed($product);
$r=$browser('/pos/orders/payment/save',['order_id'=>$refundOrder,'payment_method_ids'=>[$method],'paid_amounts'=>[10000],'reference_nos'=>['FIXTURE-REFUND']],$headers);
$check($r['status']===200&&!empty($r['json']['ok']),'no-recipe refund fixture payment');$reverse($refundOrder,'REFUND');
$check($pdo->query('SELECT status FROM pos_order WHERE id='.$refundOrder)->fetchColumn()==='REFUND_FULL','no-recipe refund reaches final state');
$check((float)$pdo->query('SELECT current_balance FROM fin_company_account WHERE id='.$account)->fetchColumn()===20000.0,'refund reverses actual cash without unlocking finance administration');
// Existing recipes may remain after a downgrade. Starter must execute their internal stock path,
// even though the recipe editor / inventory management screens remain upgrade-locked.
$recipeDivision=$insert('mst_operational_division',['code'=>'KITCHEN','name'=>'Fixture kitchen','is_active'=>1]);
$componentCategory=$insert('mst_component_category',['code'=>'TEST','name'=>'Fixture component','is_active'=>1]);
$component=$insert('mst_component',['component_code'=>'FIXTURE','component_name'=>'Disposable recipe component','component_type'=>'PREPARE','product_division_id'=>$division,'operational_division_id'=>$recipeDivision,'component_category_id'=>$componentCategory,'uom_id'=>$uom,'yield_qty'=>1,'hpp_standard'=>100,'is_active'=>1]);
// Reversal rebuilds monthly stock from movement history. A balance without its opening
// evidence is not a valid existing-customer fixture; supply the complete synthetic history.
$opening=$insert('inv_component_opening',['opening_no'=>'FIXTURE-OPENING','opening_date'=>date('Y-m-d'),'location_type'=>'KITCHEN','division_id'=>$recipeDivision,'status'=>'POSTED','posted_at'=>date('Y-m-d').' 00:00:00','posted_by'=>$employee]);
$openingLine=$insert('inv_component_opening_line',['opening_id'=>$opening,'line_no'=>1,'component_id'=>$component,'uom_id'=>$uom,'opening_qty'=>10,'unit_cost'=>100,'total_value'=>1000]);
$insert('inv_component_movement_log',['movement_no'=>'FIXTURE-OPENING','movement_date'=>date('Y-m-d'),'movement_datetime'=>date('Y-m-d').' 00:00:00','location_type'=>'KITCHEN','division_id'=>$recipeDivision,'component_id'=>$component,'uom_id'=>$uom,'movement_type'=>'OPENING','qty_in'=>10,'unit_cost'=>100,'total_cost'=>1000,'source_module'=>'OPENING','source_table'=>'inv_component_opening','source_id'=>$opening,'source_line_id'=>$openingLine]);
$lot=$insert('inv_component_lot',['location_type'=>'KITCHEN','division_id'=>$recipeDivision,'component_id'=>$component,'uom_id'=>$uom,'lot_no'=>'FIXTURE-LOT','receipt_date'=>date('Y-m-d'),'unit_cost'=>100,'qty_in_total'=>10,'qty_balance'=>10,'source_module'=>'OPENING','source_table'=>'inv_component_opening','source_id'=>$opening,'source_line_id'=>$openingLine,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
$insert('inv_component_monthly_stock',['month_key'=>date('Y-m-01'),'location_type'=>'KITCHEN','division_id'=>$recipeDivision,'component_id'=>$component,'uom_id'=>$uom,'opening_qty'=>10,'opening_total_value'=>1000,'closing_qty'=>10,'avg_cost'=>100,'total_value'=>1000]);
$rp=$pdo->query('SELECT * FROM mst_product WHERE id='.$product)->fetch(PDO::FETCH_ASSOC);unset($rp['id']);$rp['product_code']='FIXTURE-RECIPE';$rp['product_name']='Disposable recipe product';$rp['default_operational_division_id']=$recipeDivision;
$recipeProduct=$insert('mst_product',$rp);
$insert('mst_product_recipe',['product_id'=>$recipeProduct,'line_type'=>'COMPONENT','component_id'=>$component,'source_division_id'=>$recipeDivision,'qty'=>2,'uom_id'=>$uom]);
$waitForStock=static function(int $orderId)use($pdo,$lot):void{
 // The real after-response worker is asynchronous. Never mark a job successful in the fixture.
 for($try=0;$try<300;$try++){
  if((float)$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn()===8.0)return;
  $job=$pdo->query('SELECT status,attempts,last_error FROM pos_runtime_job WHERE order_id='.$orderId.' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  if(in_array($job['status']??'',['FAILED','CANCELLED'],true))break;
  usleep(100000);
 }
 echo 'STOCK fixture diagnostic '.json_encode(['order_id'=>$orderId,'lot_qty'=>$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn(),'job'=>$job??null],JSON_UNESCAPED_SLASHES)."\n";
};
$recipeOrder=$createConfirmed($recipeProduct);
$waitForStock($recipeOrder);
$check((float)$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn()===8.0,'Starter internal recipe consumes exactly two FIFO units');
$reverse($recipeOrder,'VOID');
$check((float)$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn()===10.0,'Starter recipe void restores the original FIFO lot exactly');
$check((float)$pdo->query('SELECT closing_qty FROM inv_component_monthly_stock WHERE component_id='.$component)->fetchColumn()===10.0,'recipe void restores aggregate stock from complete movement history');
$recipeRefund=$createConfirmed($recipeProduct);
$waitForStock($recipeRefund);
$check((float)$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn()===8.0,'recipe refund fixture commits FIFO through the normal worker');
$r=$browser('/pos/orders/payment/save',['order_id'=>$recipeRefund,'payment_method_ids'=>[$method],'paid_amounts'=>[10000],'reference_nos'=>['FIXTURE-RECIPE-REFUND']],$headers);
$check($r['status']===200&&!empty($r['json']['ok']),'Starter pays existing recipe order without recipe-management entitlement');
$reverse($recipeRefund,'REFUND');
$check($pdo->query('SELECT status FROM pos_order WHERE id='.$recipeRefund)->fetchColumn()==='REFUND_FULL','recipe refund reaches final state');
$check((float)$pdo->query('SELECT qty_balance FROM inv_component_lot WHERE id='.$lot)->fetchColumn()===10.0,'Starter paid recipe refund restores the same FIFO lot');
$check((float)$pdo->query('SELECT closing_qty FROM inv_component_monthly_stock WHERE component_id='.$component)->fetchColumn()===10.0&&(float)$pdo->query('SELECT total_value FROM inv_component_monthly_stock WHERE component_id='.$component)->fetchColumn()===1000.0,'recipe refund preserves aggregate quantity and valuation');
$check((float)$pdo->query('SELECT current_balance FROM fin_company_account WHERE id='.$account)->fetchColumn()===20000.0,'recipe refund reverses cash exactly once');
$check($browser('/inventory/stock/component')['status']===403,'internal stock capability does not unlock inventory management');
