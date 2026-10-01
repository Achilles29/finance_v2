<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Real POS preview/selection/pricing methods, synthetic fixtures only. No CI
// bootstrap, application DB, network, or stock/finance writes are performed.
define('BASEPATH', __DIR__);
class CI_Model
{
    public $db;
    public $load;
    public $posstockcommitservice;
    public $posorderstockservice;
}
require dirname(__DIR__, 2) . '/application/models/Pos_model.php';

final class NoStockTestDb
{
    public array $headers = [];
    public array $snapshotLines = [];
    private string $table = '';
    public function select($fields, $escape = true): self { return $this; }
    public function from($table): self { $this->table = $table; return $this; }
    public function where($key, $value): self { return $this; }
    public function order_by($key, $direction): self { return $this; }
    public function get(): self { return $this; }
    public function result_array(): array
    {
        if ($this->table === 'pos_stock_commit') return $this->headers;
        if ($this->table === 'pos_stock_commit_line') return $this->snapshotLines;
        throw new RuntimeException('Unexpected DB read: ' . $this->table);
    }
    public function __call($method, $args) { throw new RuntimeException('Unexpected DB operation: ' . $method); }
}
final class NoStockTestLoader
{
    public function library(string $name): void {}
}
final class NoStockTestSnapshots
{
    public array $calls = [];
    public array $plan = ['ok' => true, 'lines' => []];
    public function build_reversal_plan(int $id, array $map): array
    {
        $this->calls[] = [$id, $map];
        return $this->plan;
    }
}
final class NoStockTestWriter
{
    public int $calls = 0;
    public function reverse_commit_snapshot($id, $lines, $meta): array
    {
        $this->calls++;
        throw new RuntimeException('No-stock reversal must not call a stock writer.');
    }
}
final class NoStockTestModel extends Pos_model
{
    public array $fixture = [];
    public function __construct() {}
    public function find_order_draft(int $id): ?array { return $this->fixture; }
}

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
};
$reflection = new ReflectionClass(Pos_model::class);
$invoke = static function ($model, string $method, ...$args) use ($reflection) {
    $m = $reflection->getMethod($method);
    $m->setAccessible(true);
    return $m->invoke($model, ...$args);
};
$event = ['id' => 101, 'product_id' => 501, 'line_no' => 1, 'product_name' => 'EVENT TANPA RESEP',
    'qty' => 2, 'unit_price' => 100000, 'hpp_live_snapshot' => 0,
    'process_status' => 'NOT_PROCESSED', 'extras' => []];
$order = ['header' => ['id' => 77, 'status' => 'CONFIRMED', 'stock_commit_status' => 'NOT_REQUIRED',
    'subtotal_amount' => 200000, 'paid_total' => 0, 'tax_amount' => 0, 'service_amount' => 0],
    'lines' => [$event]];
$model = new NoStockTestModel();
$model->db = new NoStockTestDb();
$model->load = new NoStockTestLoader();
$model->posstockcommitservice = new NoStockTestSnapshots();
$model->posorderstockservice = new NoStockTestWriter();
$model->fixture = $order;

// This assertion reproduces the reported failure before the model patch.
$preview = $model->order_reversal_preview(77);
$check(($preview['ok'] ?? false) === true, 'NOT_REQUIRED event order without a snapshot opens reversal preview');
$check($preview['plan']['headers'] === [] && $preview['plan']['lines'] === [], 'no synthetic stock header or line is created');
$check(($preview['plan']['stock_reversal_required'] ?? true) === false, 'no-stock plan explicitly states no inventory reversal required');
$check(count($preview['order']['lines']) === 1 && $model->posstockcommitservice->calls === [], 'event remains selectable without asking stock snapshot service');

foreach (['NOT_PROCESSED', 'PROCESSED', 'SERVED'] as $process) {
    $model->fixture['lines'][0]['process_status'] = $process;
    $preview = $model->order_reversal_preview(77);
    $check(($preview['ok'] ?? false) === true, $process . ' event preview is not blocked by absent stock');
    $selection = $invoke($model, 'build_reversal_selection', $preview['order'], [], [['order_line_id' => 101, 'qty' => 2]], ['default_return_to_stock' => true]);
    $check(count($selection['product_lines']) === 1 && $selection['total_amount'] === 200000.0 && $selection['is_full'], $process . ' full cancellation keeps product value/quantity');
    $check($selection['decisions'] === [], $process . ' never manufactures stock reversal decisions');
    $result = $invoke($model, 'reverse_order_commit_decisions', $selection['decisions'], 9, 'Synthetic no-stock test');
    $check(($result['ok'] ?? false) && $result['affected_lines'] === 0 && $result['adjustment_doc_count'] === 0, $process . ' inventory path is a successful no-op');
}
$check($model->posorderstockservice->calls === 0, 'no stock, lot, adjustment writer called');

$model->fixture = $order;
$model->fixture['header']['status'] = 'PAID';
$model->fixture['header']['paid_total'] = 180000;
$preview = $model->order_reversal_preview(77);
$check($preview['ok'] === true, 'paid no-recipe order opens the same refund preview');
$partial = $invoke($model, 'build_reversal_selection', $preview['order'], [], [['order_line_id' => 101, 'qty' => 1]]);
$check($partial['decisions'] === [] && !$partial['is_full'] && $partial['product_lines'][0]['qty_after'] === 1.0, 'partial event refund keeps remaining quantity without inventory decisions');
$price = $invoke($model, 'calculate_order_refund_pricing', $preview['order']['header'], $partial, 0.0);
$check($price['ok'] === true && $price['refund_amount'] === 90000.0, 'discounted event partial refund uses actual paid product value');
$full = $invoke($model, 'build_reversal_selection', $preview['order'], [], [['order_line_id' => 101, 'qty' => 999]]);
$check($full['product_lines'][0]['qty_void'] === 2.0 && $full['product_lines'][0]['qty_after'] === 0.0, 'requested quantity remains capped to current order quantity');
$price = $invoke($model, 'calculate_order_refund_pricing', $preview['order']['header'], $full, 0.0);
$check($price['refund_amount'] === 180000.0, 'full event refund remains capped to paid value');
$price = $invoke($model, 'calculate_order_refund_pricing', $preview['order']['header'], $full, 180000.0);
$check($price['ok'] === false, 'already refunded payment cannot be refunded again');
$price = $invoke($model, 'calculate_order_refund_pricing', $order['header'], $full, 0.0);
$check($price['ok'] === false, 'unpaid event still cannot produce a monetary refund');

// Not-required is an authoritative saved stock state, not a guess based on the
// product's CURRENT recipe (which may have been changed since the sale).
foreach (['', 'PENDING', 'QUEUED', 'PROCESSING', 'FAILED', 'POSTED', 'REVERSED', 'PARTIAL_REVERSED', 'UNRECOGNIZED'] as $state) {
    $model->fixture['header']['stock_commit_status'] = $state;
    $preview = $model->order_reversal_preview(77);
    $check(($preview['ok'] ?? true) === false, $state . ' with a missing snapshot is not silently bypassed');
}
$model->fixture['header']['stock_commit_status'] = ' not_required ';
$check($model->order_reversal_preview(77)['ok'] === true, 'saved status normalization is consistent');

// Mixed/append order: a later event-only append may set NOT_REQUIRED on the
// header, while earlier product/extra stock snapshots still need reversal.
$food = $event;
$food['id'] = 102;
$food['product_name'] = 'FOOD DENGAN STOK';
$food['product_id'] = 502;
$model->fixture['lines'] = [$event, $food];
$model->fixture['header']['stock_commit_status'] = 'NOT_REQUIRED';
$model->db->headers = [['id' => 9, 'commit_no' => 'SYNTHETIC-9', 'commit_status' => 'POSTED']];
$snapshotLine = ['id' => 22, 'line_type' => 'PRODUCT', 'order_line_id' => 102, 'order_line_extra_id' => null, 'remaining_qty' => 4];
$model->db->snapshotLines = [$snapshotLine];
$model->posstockcommitservice->plan = ['ok' => true, 'lines' => [$snapshotLine]];
$preview = $model->order_reversal_preview(77);
$check($preview['ok'] === true && count($preview['plan']['lines']) === 1, 'existing snapshots still loaded even with a NOT_REQUIRED header');
$check(count($model->posstockcommitservice->calls) === 1, 'mixed order uses existing stock snapshot service');
$eventOnly = $invoke($model, 'build_reversal_selection', $preview['order'], $preview['plan']['lines'], [['order_line_id' => 101, 'qty' => 1]]);
$check($eventOnly['decisions'] === [] && count($eventOnly['product_lines']) === 1, 'event-only cancellation does not reverse stock of a different product');
$foodOnly = $invoke($model, 'build_reversal_selection', $preview['order'], $preview['plan']['lines'], [['order_line_id' => 102, 'qty' => 1]], ['default_return_to_stock' => true]);
$check(count($foodOnly['decisions']) === 1 && $foodOnly['decisions'][0]['reverse_qty'] === 2.0, 'stock product partial cancellation preserves proportional reversal');
$check($foodOnly['decisions'][0]['commit_id'] === 9 && $foodOnly['decisions'][0]['return_policy'] === 'RETURN_TO_STOCK', 'existing stock identity and policy stay intact');
$model->posstockcommitservice->plan = ['ok' => false, 'message' => 'Synthetic corrupt snapshot'];
$preview = $model->order_reversal_preview(77);
$check($preview['ok'] === false && $preview['message'] === 'Synthetic corrupt snapshot', 'stock snapshot errors are never hidden by the no-stock exception');

// Both transactional writers rebuild this same preview after locking the order;
// no change to cash posting, paid-order rejection, actor checks, or rollback.
$source = file(dirname(__DIR__, 2) . '/application/models/Pos_model.php');
foreach (['save_order_void', 'save_order_refund'] as $name) {
    $m = $reflection->getMethod($name);
    $body = implode('', array_slice($source, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
    $lock = strpos($body, '$this->lock_order_for_update($orderId)');
    $previewAt = strpos($body, '$this->order_reversal_preview($orderId)');
    $check($lock !== false && $previewAt !== false && $lock < $previewAt, $name . ' uses the patched preview only after acquiring its order lock');
    $check(strpos($body, '$this->reverse_order_commit_decisions(') !== false && strpos($body, '$this->db->trans_rollback();') !== false, $name . ' retains reversal and rollback path');
    if ($name === 'save_order_refund') $check(strpos($body, '$this->post_company_account_mutation(') !== false, 'refund still posts the outgoing financial mutation');
}
echo "POS no-stock reversal smoke: PASS ({$checks} checks; synthetic/DB-free).\n";
