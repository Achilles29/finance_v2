<?php
declare(strict_types=1);
// Render real views using synthetic inputs. No application bootstrap or database.
if (PHP_SAPI !== 'cli') exit(1);
set_error_handler(static function($n,$m,$f,$l){throw new ErrorException($m,0,$n,$f,$l);});
function html_escape($s) {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function site_url($s='') {return 'https://fixture.invalid/'.$s;}
function base_url($s='') {return site_url($s);}
function ui_num($n) {return number_format((float)$n,2,',','.');}
class ProcurementViewFixture {
    public $load, $session;
    function __construct() {$this->load=$this;$this->session=$this;}
    function flashdata($key) {return null;}
    function view($name,$data=[],$return=false) {extract($data);ob_start();require dirname(__DIR__,2).'/application/views/'.$name.'.php';$html=ob_get_clean();if($return)return $html;echo $html;}
}
$line=['line_no'=>1,'request_id'=>12,'request_no'=>'TEST-001','request_date'=>date('Y-m-d'),'needed_date'=>date('Y-m-d'),
 'division_name'=>'BAR','destination_type'=>'BAR','profile_name'=>'Kopi Arabika fixture', 'profile_key'=>'p1','identity_key'=>'p1','source_type'=>'WAREHOUSE',
 'line_kind'=>'ITEM','item_id'=>1,'material_id'=>10,'buy_uom_id'=>2,'content_uom_id'=>1,'usage_purpose'=>'BAHAN_BAKU',
 'profile_buy_uom_code'=>'KG','profile_content_uom_code'=>'GR','profile_content_per_buy'=>1000,'content_per_buy'=>1000,
 'qty_buy_requested'=>0.5,'qty_content_requested'=>500,'qty_content_to_sr'=>500,'qty_content_to_po'=>0,
 'qty_content_available_snapshot'=>99,'qty_content_balance'=>99,'qty_buy_balance'=>0.099,'request_uom_mode'=>'CONTENT',
 'qty_buy'=>0.5,'unit_price'=>50000,'snapshot_item_name'=>'Kopi Arabika fixture','snapshot_buy_uom_code'=>'KG','snapshot_content_uom_code'=>'GR',
 'stock_checked_at'=>date('Y-m-d H:i:s'),'current_stock'=>['uom'=>'GR','division'=>['qty'=>1000],'warehouse'=>['qty'=>2000],
 'warning'=>'Stok tujuan masih mencukupi jumlah pengajuan. Konfirmasi kebutuhan tambahan.']];
$common=['title'=>'Pengajuan bahan baku (fixture)','mode'=>'create','header'=>['division_id'=>7,'request_division_id'=>7,'destination_type'=>'BAR'],'lines'=>[$line],
 'division_options'=>[['id'=>7,'code'=>'BAR','name'=>'BAR']],'destination_options'=>[['value'=>'BAR','label'=>'BAR']],
 'destination_guard_map'=>[7=>['BAR']], 'can_verify'=>false,'is_purchase_scope'=>true,'stock_review_csrf'=>'fixture',
 'procurement_mutation_csrf_token'=>'fixture','purchase_mutation_csrf_token'=>'fixture',
 'uom_options'=>[['id'=>1,'code'=>'GR'],['id'=>2,'code'=>'KG']], 'uoms'=>[['id'=>1,'code'=>'GR'],['id'=>2,'code'=>'KG']],
 'divisions'=>[['id'=>7,'code'=>'BAR','name'=>'BAR']], 'vendors'=>[['id'=>2,'vendor_code'=>'TEST','vendor_name'=>'Vendor fixture']],
 'detail'=>['order'=>['id'=>12,'request_date'=>date('Y-m-d'),'vendor_id'=>2,'destination_type'=>'BAR','destination_division_id'=>7,'purchase_type_id'=>1], 'lines'=>[$line]],
 'purchase_types'=>[['id'=>1,'name'=>'Bahan baku','type_name'=>'Bahan baku','code'=>'BAHAN_BAKU','is_inventory'=>1,'default_destination'=>'BAR']],
 'edit_mode'=>true,'editability'=>['ok'=>true,'data'=>['mode'=>'full']], 'line_rows'=>[$line],'show_print_controls'=>false,'pdf_mode'=>true];
$renderer=new ProcurementViewFixture();$result=[];
foreach(['division'=>'procurement/division_po_sr_form','sr'=>'procurement/store_request_form','po'=>'purchase/order_create','print'=>'procurement/division_po_sr_print'] as $key=>$view) {
    $result[$key]=$renderer->view($view,$common,true);
}
$result['division_verify']=$renderer->view('procurement/division_po_sr_form',array_replace($common,[
 'mode'=>'verify','can_verify'=>true,'request_id'=>12,
 'vendor_options'=>[['id'=>2,'vendor_name'=>'Vendor fixture']],
 'lines'=>[
   $line+['id'=>21,'review_status'=>'PENDING'],
   array_replace($line,['id'=>22,'line_no'=>2,'profile_name'=>'Rincian kedua','review_status'=>'PENDING']),
   array_replace($line,['id'=>23,'line_no'=>3,'profile_name'=>'Rincian ditolak','review_status'=>'REJECTED','review_notes'=>'Tidak dibutuhkan']),
   array_replace($line,['id'=>24,'line_no'=>4,'profile_name'=>'Rincian selesai','review_status'=>'VERIFIED'])
 ]]),true);
$staticHeader=['id'=>12,'request_no'=>'TEST-001','request_date'=>date('Y-m-d'),'division_name'=>'BAR','destination_type'=>'BAR','status'=>'SUBMITTED','line_total'=>1,'pending_count'=>1];
$staticLine=$line+['id'=>21,'line_id'=>21,'review_status'=>'PENDING','status'=>'SUBMITTED'];
$result['division_detail']=$renderer->view('procurement/division_po_sr_detail',array_replace($common,['can_verify'=>true,'detail'=>['header'=>$staticHeader,'lines'=>[$staticLine],'links'=>[]]]),true);
$result['division_list']=$renderer->view('procurement/division_po_sr',array_replace($common,['can_verify'=>true,'rows'=>[$staticHeader],'line_rows'=>[$staticLine]]),true);
$result['division_list_lines']=$renderer->view('procurement/division_po_sr',array_replace($common,['can_verify'=>true,'active_tab'=>'lines','rows'=>[$staticHeader],'line_rows'=>[$staticLine]]),true);
echo json_encode($result,JSON_THROW_ON_ERROR);
