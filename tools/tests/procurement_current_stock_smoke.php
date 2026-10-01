<?php
declare(strict_types=1);
require __DIR__.'/procurement_stock_review_smoke.php';
class CI_Model { public $db; }
require $root.'/application/models/Procurement_model.php';
$model=(new ReflectionClass(Procurement_model::class))->newInstanceWithoutConstructor(); $model->db=$db;
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=1000;');
$db->queries=[];
$input=array_replace($line,['qty_content_requested'=>500,'profile_name'=>'Kopi <script>tidak dieksekusi</script>']);
$display=$model->current_stock_rows([$input],$context);
$check($display[0]['current_stock']['division']['qty']===2000.,'detail uses same canonical stock, not saved snapshot');
$check($display[0]['current_stock']['warehouse']['qty']===2000.,'warehouse converted KG to GR');
$check(str_contains($display[0]['current_stock']['warning'],'mencukupi'),'high stock warning compares request in same unit');
$check($db->db_debug===true,'display restores DB debug setting');
$warehouse=$model->preview_manual_stock(['header'=>['destination_type'=>'GUDANG','division_id'=>0],'lines'=>[$input]],true);
$check($warehouse['warehouse_only'] && $warehouse['rows'][0]['warehouse']['qty']===2000.,'manual PO GUDANG supports no destination division');
$check($warehouse['rows'][0]['needs_confirmation'],'warehouse PO warns about remaining warehouse stock');
$reject(fn()=>$model->preview_manual_stock(['header'=>[],'lines'=>array_fill(0,101,$input)],true),'bounded stock preview');
$reject(fn()=>$model->preview_manual_stock(['header'=>['division_id'=>[]],'lines'=>[$input]],true),'reject nested/malformed values');
$unknown=$model->current_stock_rows([array_replace($input,['item_id'=>2,'material_id'=>20])],$context);
$check($unknown[0]['current_stock']['division']['qty']===null,'missing stock cannot become zero in detail/PDF');
$db->fail='FROM mst_item WHERE id=';
$failure=$model->current_stock_rows([array_replace($input,['usage_purpose'=>'OPERASIONAL'])],$context);
$check(count($failure)===1 && $failure[0]['current_stock']['needs_confirmation'],'operational material lookup failure cannot skip stock check');
$db->fail=null;
foreach($db->queries as $sql) $check((bool)preg_match('/^\s*(SELECT|PRAGMA)\b/i',$sql),'stock display executes read-only queries');
$stock_line=$display[0];ob_start();require $root.'/application/views/procurement/_current_stock.php';$html=ob_get_clean();
$check(str_contains($html,'2.000 GR') && str_contains($html,'Divisi:') && str_contains($html,'Gudang:'),'shared PDF/detail cell has quantity and unit');
$stock_line=$unknown[0];ob_start();require $root.'/application/views/procurement/_current_stock.php';$html=ob_get_clean();
$check(str_contains($html,'Belum diketahui') && !str_contains($html,'>0 GR'),'unknown remains explicit in PDF/detail');
$profile=json_decode(file_get_contents($root.'/tools/release/customer_clean_profile.json'),true);
foreach(['application/libraries/Procurement_stock_review.php','application/views/procurement/_stock_review_panel.php',
 'application/views/procurement/_stock_review_history.php','application/views/procurement/_current_stock.php',
 'application/views/procurement/_manual_stock_toolbar.php','assets/js/procurement-stock-review.js','assets/js/procurement-current-stock.js'] as $file) {
    $check(in_array($file,$profile['code_files'],true),'customer package includes '.$file);
}
echo "Current stock: {$checks} cumulative PASS; SQLite memory only.\n";
