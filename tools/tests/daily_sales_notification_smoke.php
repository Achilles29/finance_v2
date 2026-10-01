<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
$runtime = sys_get_temp_dir() . '/finance-daily-sales-' . bin2hex(random_bytes(8));
mkdir($runtime, 0700);
define('APPPATH', $runtime . '/');
require $root . '/application/libraries/Daily_sales_pdf.php';
require $root . '/application/libraries/Module_notification.php';
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_url($path): string { return 'https://fixture.invalid/' . $path; }
function log_message($level, $message): void {}
$sample = ['overview'=>['order_count'=>3,'gross_sales'=>150000,'refund_amount'=>10000,'net_sales'=>140000],
    'pay_methods'=>[['method_name'=>'Tunai','gross_amount'=>150000,'refund_amount'=>10000,'net_amount'=>140000]],
    'pay_accounts'=>[], 'shifts'=>[], 'by_division'=>[['division_name'=>'FOOD','revenue'=>140000]],
    'total_purchase'=>40000, 'net_daily_sales'=>100000];
$render = static function (array $data, string $view = 'report_daily_sales_print') use ($root): string {
    extract($data, EXTR_SKIP); ob_start(); require $root . '/application/views/pos/' . $view . '.php'; return ob_get_clean();
};

// Exercise the actual controller action and CSRF guard in a child process (json_ok exits).
if (($argv[1] ?? '') === '--endpoint') {
    $scenario = $argv[2] ?? 'ok'; $reads = 0; $queued = 0; $passedFilters = null;
    class MY_Controller {
        public $input, $output, $session, $load, $Pos_report_model, $Module_notification_model, $daily_sales_pdf;
        protected $current_user = ['id'=>7];
        public function can($page, $ability): bool { return $GLOBALS['scenario'] !== 'rbac'; }
    }
    require $root . '/application/controllers/Pos.php';
    $controller = (new ReflectionClass(Pos::class))->newInstanceWithoutConstructor();
    $controller->input = new class {
        public function method($upper) { return $GLOBALS['scenario'] === 'get' ? 'GET' : 'POST'; }
        public function get_request_header($key, $clean) { return $GLOBALS['scenario'] === 'csrf' ? 'bad' : str_repeat('a',64); }
        public function get($key, $clean) {
            if ($key === 'date') return $GLOBALS['scenario'] === 'date' ? '2026-02-30' : '2026-09-23';
            return $GLOBALS['scenario'] === 'outlet' ? '999' : ($GLOBALS['scenario'] === 'all' ? '0' : '2');
        }
    };
    $controller->session = new class { public function userdata($key) { return str_repeat('a',64); } };
    $controller->output = new class {
        public $code = 200, $body;
        public function set_status_header($code) { $this->code=$code; return $this; }
        public function set_content_type($value) { return $this; }
        public function set_output($value) { $this->body=$value; return $this; }
        public function _display() { echo json_encode(['status'=>$this->code,'body'=>json_decode($this->body,true),'reads'=>$GLOBALS['reads'],'queued'=>$GLOBALS['queued'],'filters'=>$GLOBALS['passedFilters']], JSON_THROW_ON_ERROR); }
    };
    $controller->load = new class {
        public function model($name): void { if ($name !== 'Module_notification_model') throw new RuntimeException('Unexpected model'); }
        public function library($name): void { if ($name !== 'Daily_sales_pdf') throw new RuntimeException('Unexpected library'); }
        public function view($name, $data, $return) { return ($GLOBALS['render'])($data); }
    };
    $controller->Pos_report_model = new class {
        public function outlet_options(): array { return [['id'=>2,'outlet_name'=>'Outlet Fixture']]; }
        public function daily_sales_report($date,$outlet): array { $GLOBALS['reads']++; $GLOBALS['passedFilters']=[$date,$outlet]; return $GLOBALS['sample']; }
    };
    $controller->Module_notification_model = new class {
        public function enabled_channels($event): array { return $GLOBALS['scenario'] === 'disabled' ? [] : ['WA']; }
        public function enqueue_daily_sales($filters,$outlet,$snapshot,$actor,$attachment): array { $GLOBALS['queued']++; return ['message'=>'Queued PDF']; }
    };
    $controller->daily_sales_pdf = new class {
        public function create($html,$filters,$snapshot): array {
            if ($GLOBALS['scenario'] === 'renderer') throw new RuntimeException('PDF belum berhasil dibuat.');
            if (strpos($html,'window.print') !== false || strpos($html,'140.000') === false) throw new RuntimeException('Invalid rendered report');
            return ['path'=>'synthetic.pdf','name'=>'daily-sales.pdf'];
        }
    };
    $controller->report_daily_sales_notify();
    $controller->output->_display(); exit;
}

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; };
$reject = static function (callable $fn, string $label) use ($check): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException $e) { $check(true,$label); return; }
    $check(false,$label);
};
$check(isset(Module_notification::events('WA')['DAILY_SALES']) && !isset(Module_notification::events('TELEGRAM')['DAILY_SALES']), 'Daily Sales added only to WA');
$filters = Daily_sales_pdf::filters('2026-09-23','2');
$check($filters === ['date'=>'2026-09-23','outlet_id'=>2], 'date/outlet normalized');
foreach ([['2026-02-30','2'],['2026-09-23','-1'],['2026-09-23',[]],['../etc','0'],[null,'0']] as $bad) $reject(fn()=>Daily_sales_pdf::filters(...$bad),'invalid filters rejected');
$snapshot = Daily_sales_pdf::snapshot($filters,$sample,'Fixture');
$check($snapshot === Daily_sales_pdf::snapshot($filters,$sample,'Fixture'), 'same data stable dedup key');
$check($snapshot !== Daily_sales_pdf::snapshot($filters,$sample+['change'=>1],'Fixture'), 'updated report distinct snapshot');
$check($snapshot !== Daily_sales_pdf::snapshot(['date'=>'2026-09-23','outlet_id'=>0],$sample,'Fixture'), 'outlet is part of snapshot');
$html = $render($filters + ['outlet_name'=>'Fixture <script>unsafe()</script>','pdf_mode'=>true] + $sample);
$check(strpos($html,'<script>') === false && strpos($html,'window.print') === false, 'server PDF has no script / print controls');
$check(strpos($html,'140.000') !== false && strpos($html,'100.000') !== false && strpos($html,'landscape') !== false, 'report values and PDF landscape layout');
$browserHtml = $render($filters + $sample);
$check(strpos($browserHtml,'window.print') !== false, 'existing browser print unchanged');
foreach (['ok'=>200,'all'=>200,'get'=>405,'csrf'=>403,'rbac'=>403,'disabled'=>403,'date'=>422,'outlet'=>422,'renderer'=>422] as $scenario=>$expected) {
    $pipes=[]; $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',$scenario],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $response=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $check(proc_close($p) === 0,'endpoint process '.$scenario.' '.$error);
    $r=json_decode($response,true,512,JSON_THROW_ON_ERROR);
    $check($r['status']===$expected,'endpoint status '.$scenario);
    $check($r['queued']===($expected===200?1:0),'no enqueue on rejected action '.$scenario);
    if (!in_array($scenario,['ok','all','renderer'],true)) $check($r['reads']===0,'reject before report data read '.$scenario);
    if ($expected===200) $check($r['filters']===['2026-09-23',$scenario==='all'?0:2],'report filters preserved '.$scenario);
}
require $root . '/application/libraries/Feature_policy.php';
$manifest=json_decode(file_get_contents($root.'/app-manifest.json'),true,512,JSON_THROW_ON_ERROR);
$policy=require $root.'/application/config/feature_access.php';
$features=[]; foreach($manifest['features'] as $feature) $features[$feature['code']]=$feature['value_type']==='BOOLEAN'?true:999;
$license=['verified'=>true,'status'=>'ACTIVE','payload'=>['edition'=>'ENTERPRISE','entitlements'=>$features]];
$gate=new Feature_policy($manifest,$policy,$license,true);
$check($gate->route('pos','report_daily_sales_notify')['allowed'],'full license allows send');
foreach(['AUTOMATION_MESSAGING','SALES_REPORTING'] as $key) {
    $bad=$license; $bad['payload']['entitlements'][$key]=false;
    $check(!(new Feature_policy($manifest,$policy,$bad,true))->route('pos','report_daily_sales_notify')['allowed'],'missing entitlement blocks '.$key);
}
$pipes=[]; $p=proc_open(['/usr/bin/node',__DIR__.'/daily_sales_notification_client_smoke.cjs'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
$js=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$check(proc_close($p)===0,'daily sales JS: '.$error); echo $js;
if (in_array('--pdf',$argv,true)) {
    $renderer=new Daily_sales_pdf(); $attachment=$renderer->create($html,$filters,$snapshot);
    Daily_sales_pdf::assertAttachment($attachment);
    $check(substr(file_get_contents($attachment['path']),0,5)==='%PDF-','real Chrome PDF generated');
    $hash=hash_file('sha256',$attachment['path']);
    $check($renderer->create($html,$filters,$snapshot)===$attachment && hash_file('sha256',$attachment['path'])===$hash,'repeat retains exact file');
    $check((fileperms($attachment['path']) & 0777) === 0640,'attachment not world-readable');
    $check(glob(APPPATH.'cache/wa-attachments/.daily-*')===[],'own renderer scratch cleaned');
    $bad=$attachment; $bad['path']=$runtime.'/outside.pdf'; file_put_contents($bad['path'],'%PDF-fake');
    $reject(fn()=>Daily_sales_pdf::assertAttachment($bad),'outside storage attachment denied');
    $link=APPPATH.'cache/wa-attachments/daily-sales-'.str_repeat('a',64).'.pdf'; symlink($attachment['path'],$link);
    $bad['path']=$link; $reject(fn()=>Daily_sales_pdf::assertAttachment($bad),'symlink attachment denied');
    echo 'PDF evidence: '.$attachment['path']."\n";
}
echo "Daily Sales notification: {$checks} checks PASS; synthetic controller/report data, no active DB or sends.\n";
