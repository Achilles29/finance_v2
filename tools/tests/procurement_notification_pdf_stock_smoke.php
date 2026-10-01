<?php
declare(strict_types=1);
// Actual stock reader and SQL on SQLite memory; actual print template. No live DB/messages.
require __DIR__ . '/procurement_stock_review_smoke.php';
$initialChecks = $checks;
$runtime = sys_get_temp_dir() . '/finance-po-sr-pdf-' . bin2hex(random_bytes(8));
mkdir($runtime, 0700);
define('APPPATH', $runtime . '/');
class CI_Model { public $db; }
class MY_Controller { public $load, $Procurement_model; }
require $root . '/application/models/Procurement_model.php';
require $root . '/application/controllers/Procurement.php';
$reader = (new ReflectionClass(Procurement_model::class))->newInstanceWithoutConstructor();
$reader->db = $db;
$db->pdo->exec("UPDATE inv_division_monthly_stock SET closing_qty_content=1000 WHERE division_id=7 AND destination_type='BAR'");
$header = ['id'=>100,'request_no'=>'REQ-PDF-TEST','request_date'=>'2026-09-23','needed_date'=>'2026-09-24',
    'division_id'=>7,'division_name'=>'BAR fixture','destination_type'=>'BAR','status'=>'SUBMITTED'];
$baseLine = $line + ['line_no'=>1,'profile_buy_uom_code'=>'KG','profile_content_uom_code'=>'GR','request_uom_mode'=>'CONTENT',
    'qty_buy_requested'=>0.5,'qty_content_to_sr'=>500,'qty_content_to_po'=>0,'notes'=>'Catatan <aman> & lengkap'];
$lines = [
    $baseLine,
    array_replace($baseLine,['line_no'=>2,'profile_name'=>'Material operasional','usage_purpose'=>'OPERASIONAL']),
    array_replace($baseLine,['line_no'=>3,'profile_name'=>'Gula tanpa saldo','item_id'=>2,'material_id'=>20]),
    array_replace($baseLine,['line_no'=>4,'profile_name'=>'Non bahan baku','item_id'=>3,'material_id'=>0,'usage_purpose'=>'OPERASIONAL']),
];
$model = new class($reader,$header,$lines) {
    public $reader, $header, $lines;
    public function __construct($reader,$header,$lines) { $this->reader=$reader; $this->header=$header; $this->lines=$lines; }
    public function current_stock_rows($lines,$header=[]) { return $this->reader->current_stock_rows($lines,$header); }
    public function list_division_requests($filters,$limit) { return [$this->header]; }
    public function list_division_request_line_rows($filters,$limit) {
        return array_map(function($line) {
            // Same aliases/context returned by the download SQL reader.
            return $line + ['request_id'=>$this->header['id'],'line_notes'=>$line['notes'],
                'request_no'=>$this->header['request_no'],'division_id'=>$this->header['division_id'],
                'division_name'=>$this->header['division_name'],'destination_type'=>$this->header['destination_type'],
                'needed_date'=>$this->header['needed_date']];
        }, $this->lines);
    }
    public function list_division_request_links_map($ids) { return []; }
};
$controller = (new ReflectionClass(Procurement::class))->newInstanceWithoutConstructor();
$controller->Procurement_model = $model;
$controller->load = new class {
    public $load, $captured;
    public function __construct() { $this->load=$this; }
    public function view($name,$data=[],$return=false) {
        if (!in_array($name,['procurement/division_po_sr_print','procurement/_current_stock'],true)) throw new RuntimeException('Unexpected view');
        if ($name === 'procurement/division_po_sr_print') $this->captured=$data;
        extract($data,EXTR_SKIP); ob_start(); require $GLOBALS['root'].'/application/views/'.$name.'.php'; $html=ob_get_clean();
        if ($return) return $html; echo $html;
    }
};
$prepare = new ReflectionMethod(Procurement::class,'prepareDivisionPoSrPrintRows'); $prepare->setAccessible(true);
$downloadMethod = new ReflectionMethod(Procurement::class,'buildDivisionPoSrReportPayload'); $downloadMethod->setAccessible(true);
$db->queries=[];
$waRows = $prepare->invoke($controller,$lines,$header);
$download = $downloadMethod->invoke($controller,[],2000,[]);
foreach ($waRows as $i=>$row) {
    $check($row['current_stock'] === $download['line_rows'][$i]['current_stock'], 'WA/download same stock and classification row '.($i+1));
    $check($row['line_notes'] === $download['line_rows'][$i]['line_notes'], 'WA/download same item notes row '.($i+1));
}
$check($waRows[0]['current_stock']['division']['qty']===2000.,'correct BAR/division scope, not other location/division');
$check($waRows[0]['current_stock']['warehouse']['qty']===2000.,'warehouse KG converted into requested GR');
$check($waRows[1]['current_stock']!==null,'material-linked operational item remains material');
$check($waRows[2]['current_stock']['division']['qty']===null,'unknown stock not converted into zero/non-material');
$check($waRows[3]['current_stock']===null,'genuine non-material stays non-material');
$vars=['title'=>'PDF fixture','filters'=>['date_start'=>$header['needed_date']],'printed_at'=>'2026-09-23 12:00:00','show_print_controls'=>false,'pdf_mode'=>true];
$waHtml=$controller->load->view('procurement/division_po_sr_print',$vars+['line_rows'=>$waRows],true);
$downloadHtml=$controller->load->view('procurement/division_po_sr_print',$vars+['line_rows'=>$download['line_rows']],true);
// Reading time is informational and may differ across a second boundary.
$normalize=static fn(string $html): string=>preg_replace('/Dibaca \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/','Dibaca [waktu baca]',$html);
$check($normalize($waHtml)===$normalize($downloadHtml),'complete PDF HTML identical for same request and read time');
$check(substr_count($waHtml,'Tidak terkait bahan baku')===1,'only non-material line gets non-material label');
$check(str_contains($waHtml,'2.000 GR') && str_contains($waHtml,'Belum diketahui'),'quantities and unknown fallback visible');
$check(str_contains($waHtml,'Catatan &lt;aman&gt; &amp; lengkap'),'notes retained and escaped');
foreach($db->queries as $sql) $check((bool)preg_match('/^\s*(SELECT|PRAGMA)\b/i',$sql),'PDF stock enrichment read-only');
$db->pdo->exec('UPDATE inv_division_monthly_stock SET closing_qty_content=250 WHERE division_id=7');
$fresh=$prepare->invoke($controller,$waRows,$header);
$check($fresh[0]['current_stock']['division']['qty']===500.,'new PDF reads live stock rather than previous snapshot');
$db->fail='FROM inv_division_monthly_stock';
$failed=$prepare->invoke($controller,[$baseLine],$header);
$check($failed[0]['current_stock']['division']['qty']===null && $failed[0]['current_stock']['warehouse']['qty']===2000.,'read failure remains unknown, not non-material');
$db->fail=null;

if (in_array('--pdf',$argv,true)) {
    $method=new ReflectionMethod(Procurement::class,'createDivisionNotificationPdf'); $method->setAccessible(true);
    $attachment=$method->invoke($controller,['header'=>$header,'lines'=>$lines]);
    $check(str_starts_with(file_get_contents($attachment['path']),'%PDF-'),'actual WA attachment rendered as PDF');
    $captured=$controller->load->captured['line_rows'];
    $check($captured[0]['current_stock']['division']['qty']===500.,'actual outgoing path calls live stock enrichment');
    $check($captured[0]['line_notes']===$baseLine['notes'],'actual outgoing path retains download notes');
    $text=shell_exec('pdftotext -layout '.escapeshellarg($attachment['path']).' -');
    $check(is_string($text) && str_contains($text,'500 GR') && str_contains($text,'2.000 GR'),'actual PDF contains both balances');
    $check(substr_count((string)$text,'Tidak terkait bahan baku')===1 && str_contains((string)$text,'Belum diketahui'),'actual PDF labels material/missing/non-material correctly');
    echo 'PDF evidence: '.$attachment['path']."\n";
}
echo 'PO/SR PDF parity: '.($checks-$initialChecks)." PASS; stock reader on SQLite fixture; no live DB or WhatsApp sends.\n";
