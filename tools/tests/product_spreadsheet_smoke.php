<?php
declare(strict_types=1);
// Synthetic fixtures only: no live database, Google credentials, or network requests.
if (PHP_SAPI !== 'cli') exit;
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
function log_message($level, $message): void {}
function show_error($message, $status = 500, $title = ''): void { throw new RuntimeException($message, $status); }
class MY_Controller {
    public $input; public $session; public $output; public $Master_model;
    public $permissions = ['view' => true, 'export' => true];
    protected function require_permission($page, $action = 'view'): void {
        if (empty($this->permissions[$action])) throw new RuntimeException('Forbidden', 403);
    }
}
require APPPATH . 'controllers/Master.php';
require APPPATH . 'libraries/Product_spreadsheet_export.php';
require APPPATH . 'libraries/Product_sheet_client.php';
require APPPATH . 'libraries/SimpleSpreadsheetIO.php';
$checks = 0;
function verify(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
function mustFail(callable $call, string $expected): void {
    try { $call(); } catch (RuntimeException $e) { verify(strpos($e->getMessage(), $expected) !== false, $expected); return; }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
$master = (new ReflectionClass(Master::class))->newInstanceWithoutConstructor();
$master->Master_model = new class { public function get_variable_cost_default_percent(...$args): float { return 25; } };
$cache = new ReflectionProperty(Master::class, 'productListLiveHppCache');
$cache->setAccessible(true);
$cache->setValue($master, [1 => 100.0, 2 => 0.0]);
$decorate = new ReflectionMethod(Master::class, 'decorateProductListRow');
$decorate->setAccessible(true);
$base = ['id' => 1, 'product_code' => '00123', 'product_name' => '=1+1', 'product_division_id_label' => 'BAR',
    'selling_price' => 250, 'online_food_price' => 300, 'variable_cost_mode' => 'DEFAULT', 'is_active' => 1,
    'show_pos' => 1, 'show_member' => 0, 'photo_path' => 'uploads/product.jpg'];
foreach (['DEFAULT' => 125.0, 'CUSTOM' => 110.0, 'NONE' => 100.0] as $mode => $expected) {
    $row = array_merge($base, ['variable_cost_mode' => $mode, 'variable_cost_percent' => 10]);
    $result = $decorate->invoke($master, $row);
    verify($result['hpp_live_total'] === $expected, $mode . ' reuses master cost and applies variable once');
    verify($result['estimated_profit'] === 250 - $expected, $mode . ' profit matches master');
    verify($result['hpp_direct_live'] === 100.0, 'direct cost kept separate');
}
$fallback = $decorate->invoke($master, ['id' => 2, 'hpp_live_cache' => 20, 'variable_cost_mode' => 'NONE']);
verify($fallback['hpp_basis_source'] === 'FALLBACK CACHE MASTER', 'fallback is explicitly labelled');
$zero = $decorate->invoke($master, ['id' => 2, 'variable_cost_mode' => 'NONE']);
verify($zero['hpp_basis_source'] === 'HPP BELUM TERSEDIA / NOL', 'unavailable HPP is labelled');
$decorated = array_merge($base, $decorate->invoke($master, $base));
$snapshot = Product_spreadsheet_export::snapshot([$decorated], '2026-09-25 12:00:00', 'https://fixture.invalid/');
$indexed = array_combine($snapshot['headers'], $snapshot['rows'][0]);
verify($indexed['Kode Produk'] === '00123' && $indexed['Nama Produk'] === '=1+1', 'text and leading zeros preserved');
verify($indexed['HPP / Harga Jual (%)'] === 50.0 && $indexed['Margin / Harga Jual (%)'] === 50.0, 'percentage denominator is selling price');
verify($indexed['Profit Online Sebelum Fee'] === 175.0, 'online profit does not pretend to include platform fees');
verify($indexed['Tampil POS'] === true && $indexed['Tampil Member'] === false, 'boolean values remain typed');
verify($indexed['URL Foto'] === 'https://fixture.invalid/uploads/product.jpg', 'relative photo URL is absolute');
$special = Product_spreadsheet_export::snapshot([array_merge($decorated, ['selling_price' => 0, 'online_food_price' => 0, 'is_active' => 0])], 'now', 'https://fixture.invalid');
$specialRow = array_combine($special['headers'], $special['rows'][0]);
verify($specialRow['HPP / Harga Jual (%)'] === '' && $specialRow['Margin / Harga Jual (%)'] === '' && $specialRow['HPP / Harga Online (%)'] === '', 'zero denominators are blank');
verify($special['inactive_count'] === 1, 'inactive products retained');

$lockFile = tempnam(sys_get_temp_dir(), 'sheet-test-');
final class FakeProductSheetClient extends Product_sheet_client {
    public array $calls = [];
    public array $existing = [];
    public ?array $marker = null;
    public bool $failBatch = false;
    protected function api(string $method, string $path, ?array $payload = null): array {
        $this->calls[] = [$method, $path, $payload];
        if ($method === 'POST') {
            if ($this->failBatch) throw new RuntimeException('Synthetic Google failure');
            return ['replies' => []];
        }
        if (strpos($path, '/values/') === 0) return ['values' => $this->existing];
        return ['sheets' => [['properties' => ['sheetId' => 7, 'title' => "PRODUK 'test'", 'sheetType' => 'GRID', 'gridProperties' => ['rowCount' => 100, 'columnCount' => 26]],
            'developerMetadata' => $this->marker ? [$this->marker] : []], ['properties' => ['sheetId' => 8, 'title' => 'Analisis Manual']]]];
    }
}
$settings = ['spreadsheet_id' => str_repeat('a', 30), 'sheet_id' => 7, 'lock_file' => $lockFile];
try {
    $client = new FakeProductSheetClient($settings);
    $result = $client->sync(static fn() => $snapshot);
    verify($result['count'] === 1 && substr($result['url'], -5) === 'gid=7', 'sync returns exact destination');
    $writes = array_values(array_filter($client->calls, static fn($call) => $call[0] === 'POST'));
    verify(count($writes) === 1 && $writes[0][1] === ':batchUpdate', 'all changes use one atomic batch, no prior clear');
    $requests = $writes[0][2]['requests'];
    $update = $requests[1]['updateCells'];
    verify($update['range']['sheetId'] === 7, 'data update targets only requested sheet');
    verify($update['rows'][1]['values'][4]['userEnteredValue'] === ['stringValue' => '=1+1'], 'formula injection is stored as literal text');
    verify($update['rows'][1]['values'][10]['userEnteredValue'] === ['numberValue' => 250.0], 'numeric cell is not Rupiah text');
    verify(strpos(json_encode($requests), '"sheetId":8') === false, 'other tabs never receive write requests');
    verify(isset(end($requests)['createDeveloperMetadata']), 'first empty target is marked as managed');
    $client = new FakeProductSheetClient($settings);
    $client->existing = [['Manual data']];
    $built = false;
    mustFail(function () use ($client, &$built, $snapshot) { $client->sync(function () use (&$built, $snapshot) { $built = true; return $snapshot; }); }, 'bukan milik ekspor');
    verify(!$built && !array_filter($client->calls, static fn($c) => $c[0] === 'POST'), 'nonempty unmanaged tab untouched');
    $client = new FakeProductSheetClient($settings);
    $client->marker = ['metadataId' => 100, 'metadataKey' => 'finance_product_snapshot', 'metadataValue' => json_encode(['version' => 1, 'rows' => 20, 'columns' => 45])];
    $client->sync(static fn() => $snapshot);
    $requests = end($client->calls)[2]['requests'];
    verify($requests[1]['updateCells']['range']['endRowIndex'] === 20, 'shrinking catalog removes stale rows in owned range');
    verify(isset(end($requests)['updateDeveloperMetadata']), 'repeat sync replaces rather than appends');
    $client->failBatch = true;
    mustFail(static fn() => $client->sync(static fn() => $snapshot), 'Synthetic Google failure');
    $client->failBatch = false;
    $client->sync(static fn() => $snapshot);
    verify(true, 'lock released after Google failure');
    $lock = fopen($lockFile, 'c'); flock($lock, LOCK_EX);
    mustFail(static fn() => $client->sync(static fn() => $snapshot), 'sedang berjalan');
    flock($lock, LOCK_UN); fclose($lock);
    mustFail(static fn() => $client->sync(static fn() => array_merge($snapshot, ['rows' => []])), 'Data ekspor kosong');

    $io = new SimpleSpreadsheetIO();
    $file = tempnam(sys_get_temp_dir(), 'xlsx-test-');
    try {
        $binary = $io->build_xlsx_binary(['Code', 'Number', 'Text', 'Flag'], [['Code' => '00123', 'Number' => 12.5, 'Text' => '=1+1', 'Flag' => false]], 'Products', true);
        verify(is_string($binary), 'typed XLSX generated');
        file_put_contents($file, $binary);
        $zip = new ZipArchive(); $zip->open($file); $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
        verify(strpos($xml, 'r="B2" t="n"><v>12.5</v>') !== false, 'Excel money uses numeric cells');
        verify(strpos($xml, 'r="A2" t="inlineStr"') !== false && strpos($xml, '>00123<') !== false, 'Excel preserves leading zeros');
        verify(strpos($xml, '<f>') === false && strpos($xml, '>=1+1<') !== false, 'Excel text cannot inject a formula');
        verify(strpos($xml, 'r="D2" t="b"><v>0</v>') !== false, 'Excel boolean false preserved');
        $binary = $io->build_xlsx_binary(['Number'], [['Number' => 12.5]]);
        file_put_contents($file, $binary);
        $zip->open($file); $xml = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
        verify(strpos($xml, 'r="A2" t="inlineStr"') !== false, 'existing spreadsheet exports retain their original text behavior');
    } finally { unlink($file); }
} finally { unlink($lockFile); }

// Rejected controller requests must terminate before a real Google client or DB is reached.
foreach ([['GET', '', true, true, 405], ['POST', '', true, true, 403], ['POST', str_repeat('b', 64), true, true, 403],
    ['POST', str_repeat('a', 64), false, true, 403], ['POST', str_repeat('a', 64), true, false, 403]] as [$verb, $token, $view, $export, $status]) {
    $c = (new ReflectionClass(Master::class))->newInstanceWithoutConstructor();
    $c->permissions = ['view' => $view, 'export' => $export];
    $c->input = new class($verb, $token) {
        private $verb; private $token;
        public function __construct($verb, $token) { $this->verb = $verb; $this->token = $token; }
        public function method($upper) { return $this->verb; }
        public function post(...$args) { return ''; }
        public function get_request_header(...$args) { return $this->token; }
        public function is_ajax_request() { return false; }
    };
    $c->session = new class { public function userdata($key) { return str_repeat('a', 64); } };
    $c->output = new class { public function set_header($header) { return $this; } };
    try { $c->product_sheet_sync(); throw new LogicException('Guard failed'); }
    catch (RuntimeException $e) { verify($e->getCode() === $status, 'controller rejects ' . $verb . ' before Google access'); }
}
$manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/app-manifest.json'), true);
$policy = require APPPATH . 'config/feature_access.php';
$entitlements = [];
foreach ($manifest['features'] as $feature) if ($feature['value_type'] === 'BOOLEAN') $entitlements[$feature['code']] = true;
$verification = ['verified' => true, 'status' => 'ACTIVE', 'payload' => ['edition' => 'ENTERPRISE', 'entitlements' => $entitlements]];
$full = new Feature_policy($manifest, $policy, $verification, true);
$verification['payload']['entitlements']['HPP_CONTROL'] = false;
$restricted = new Feature_policy($manifest, $policy, $verification, true);
$route = []; require APPPATH . 'config/routes.php';
foreach (['sync', 'export'] as $action) {
    verify($full->menu('master/product/spreadsheet/' . $action, $route)['allowed'], 'licensed ' . $action . ' route mapped');
    verify(!$restricted->menu('master/product/spreadsheet/' . $action, $route)['allowed'], 'HPP entitlement required for ' . $action);
}
echo 'PASS product spreadsheet checks=' . $checks . '; no live DB or network.' . PHP_EOL;
