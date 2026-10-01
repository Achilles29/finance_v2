<?php
declare(strict_types=1);

// No CodeIgniter bootstrap, connection, or operational data. Render real views
// with synthetic records for both the PHP checks and the optional browser test.
$root = dirname(__DIR__, 2);
set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_url(string $path = ''): string { return '/' . ltrim($path, '/'); }

final class ProcurementLayoutFixture
{
    public $load;
    public function __construct() { $this->load = $this; }
    public function view(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/application/views/' . $path . '.php';
    }
    public function render(string $page): string
    {
        $po = [
            'id' => 17, 'po_no' => 'PO-FIXTURE-20260913-000017',
            'request_date' => '2026-09-13', 'paid_date' => '2026-09-13',
            'vendor_name' => 'Vendor Contoh dengan Nama Panjang untuk Uji Ponsel',
            'destination_type' => 'ROASTERY_EVENT', 'purchase_type_name' => 'Persediaan Produksi Roastery',
            'payment_account_name' => 'Rekening Operasional Contoh', 'grand_total' => 123456789.45,
            'status' => 'DRAFT', 'snapshot_item_name' => 'Bahan contoh berkemasan dengan deskripsi panjang',
            'snapshot_content_uom_code' => 'GR', 'qty_buy' => 1200.5,
            'content_per_buy' => 1000, 'line_subtotal' => 123456789.45,
        ];
        ob_start();
        if ($page === 'tx') {
            $this->view('pos/report_sales_transaction', [
                'order' => ['header' => [
                    'id' => 17, 'order_no' => 'POS-FIXTURE-20260913-000017', 'status' => 'PAID',
                    'customer_name' => 'Pelanggan Contoh dengan Nama Panjang',
                    'notes' => str_repeat('CatatanPanjang', 8), 'grand_total' => 123456789.45,
                ], 'lines' => [['id' => 31, 'product_name' => 'Produk Contoh', 'qty' => 2, 'net_amount' => 90000]]],
                'payments' => [['payment_no' => 'PAY-FIXTURE-000017', 'amount' => 123456789.45]],
            ]);
        } else {
            $this->view('purchase/index', [
                'q' => '', 'tab' => $page, 'status' => 'ALL', 'status_options' => ['ALL', 'DRAFT', 'PAID'],
                'rows' => [$po], 'line_rows' => [$po], 'paid_rows' => [array_replace($po, ['status' => 'PAID'])],
                'current_user' => ['is_superadmin' => true],
            ]);
        }
        $html = (string)ob_get_clean();
        // Browser fixtures are read-only: remove mutation scripts entirely.
        return (string)preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
    }
}

$fixture = new ProcurementLayoutFixture();
$render = (string)($argv[1] ?? '');
if (strpos($render, '--render=') === 0) {
    $page = substr($render, 9);
    if (!in_array($page, ['tx', 'nota', 'rincian', 'paid'], true)) { exit(2); }
    echo $fixture->render($page);
    exit;
}

define('BASEPATH', $root . '/system/');
class CI_Model { public $db; }
final class DivisionFixtureDatabase
{
    private $id = 0;
    public function field_exists(string $field, string $table): bool { return in_array($field, ['code', 'name'], true); }
    public function select($select, $escape = null): self { return $this; }
    public function from($table): self { return $this; }
    public function where($key, $id): self { $this->id = (int)$id; return $this; }
    public function limit($limit): self { return $this; }
    public function get(): self { return $this; }
    public function row_array(): array
    {
        $codes = [1 => 'BAR', 2 => 'KITCHEN', 3 => 'ROASTERY', 4 => 'OFFICE', 5 => 'LAIN'];
        return ['id' => $this->id, 'code' => $codes[$this->id] ?? '', 'name' => $codes[$this->id] ?? ''];
    }
}
require $root . '/application/models/Procurement_model.php';
$model = new Procurement_model();
$model->db = new DivisionFixtureDatabase();
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { throw new RuntimeException($message); }
};
$options = array_column($model->list_destination_options(), 'label', 'value');
$guard = $model->build_destination_guard_map(array_map(static fn($id): array => ['id' => $id], range(1, 5)));
$check($guard[3] === ['ROASTERY', 'ROASTERY_EVENT'], 'Roastery retains its regular/event guard');
foreach ($guard as $id => $allowed) {
    foreach ($allowed as $destination) {
        $check(isset($options[$destination]), 'Every allowed destination has a dropdown option: ' . $destination);
    }
}
$allowedMethod = new ReflectionMethod($model, 'is_destination_allowed_for_division');
$allowedMethod->setAccessible(true);
$normalize = new ReflectionMethod($model, 'normalize_destination');
$normalize->setAccessible(true);
foreach (['ROASTERY', 'ROASTERY_EVENT'] as $destination) {
    $check($allowedMethod->invoke($model, 3, $destination), 'Roastery accepts ' . $destination);
    $check(!$allowedMethod->invoke($model, 1, $destination), 'Bar cannot select ' . $destination);
    $check($normalize->invoke($model, $destination) === $destination, 'Store/update normalization accepts ' . $destination);
}
$check(!$allowedMethod->invoke($model, 3, 'KITCHEN'), 'Cross-division restriction stays enforced');
$check(!$allowedMethod->invoke($model, 0, 'ROASTERY'), 'Invalid division still rejected');

foreach (['nota', 'rincian', 'paid'] as $tab) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $fixture->render($tab), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $cells = $xpath->query('//*[@id="po-tab-' . $tab . '"]//tbody/tr/td[not(@colspan)]');
    $check($cells->length === ($tab === 'rincian' ? 8 : 6), $tab . ' retains all columns');
    foreach ($cells as $cell) {
        $check($cell->getAttribute('data-label') !== '', $tab . ' cell has a mobile label');
    }
    $check($xpath->query('//select[contains(@class,"po-status-next") and @data-id="17"]')->length === 1, 'No duplicated editable PO controls');
}
$tx = $fixture->render('tx');
$check(strpos($tx, 'class="pos-tx-header-actions"') !== false, 'Transaction actions use responsive wrapper');
foreach (['/invoice', '/receipt', 'Kembali ke Penjualan', 'Ke Penjualan Produk'] as $text) {
    $check(strpos($tx, $text) !== false, 'Transaction action retained: ' . $text);
}
echo 'PASS: ' . $checks . " Roastery destination and mobile-view checks (synthetic data only).\n";
