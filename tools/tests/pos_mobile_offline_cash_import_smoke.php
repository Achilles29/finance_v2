<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
class CI_Model { public $db; }
require $root . '/application/models/Pos_mobile_offline_import_model.php';

final class CashImportResult
{
    public function row_array(): array
    {
        return ['id' => 3, 'is_active' => 1, 'method_type' => 'CASH'];
    }
}
final class CashImportDb
{
    public function from(): self { return $this; }
    public function where(): self { return $this; }
    public function limit(): self { return $this; }
    public function get(): CashImportResult { return new CashImportResult(); }
}

function cash_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
}

$model = (new ReflectionClass(Pos_mobile_offline_import_model::class))->newInstanceWithoutConstructor();
$model->db = new CashImportDb();
$validate = new ReflectionMethod(Pos_mobile_offline_import_model::class, 'validate_cash_sale');
$validate->setAccessible(true);
$binding = ['outlet_id' => 1, 'terminal_id' => 2, 'employee_id' => 3];
$session = ['id' => 4, 'session_status' => 'OPEN', 'outlet_id' => 1, 'employee_id' => 3];
$sale = [
    'outlet_id' => 1, 'terminal_id' => 2, 'cashier_session_id' => 4,
    'confirm_order' => true, 'lines' => [['product_id' => 5, 'qty' => 1]],
    'payment_method_id' => 3, 'local_payment_uuid' => 'PAY-00000000-0000-4000-8000-000000000001',
    'local_payment_no' => 'OFF-00000000-0000-4000-8000-000000000001', 'paid_amount' => 25000,
    'received_amount' => 30000, 'change_amount' => 5000,
    'grand_total' => 25000,
];
$validate->invoke($model, $sale, $binding, $session);
cash_check(true, 'single cash sale with bound session and exact amounts is eligible');
foreach ([
    ['sale' => ['outlet_id' => 9], 'message' => 'foreign outlet'],
    ['sale' => ['terminal_id' => 9], 'message' => 'foreign terminal'],
    ['sale' => ['cashier_session_id' => 9], 'message' => 'stale session'],
    ['sale' => ['id' => 12], 'message' => 'existing server order'],
    ['sale' => ['received_amount' => 24000], 'message' => 'insufficient cash'],
    ['sale' => ['grand_total' => 26000], 'message' => 'changed total'],
    ['sale' => ['local_payment_no' => 'OFF-00000000-0000-4000-8000-000000000002'], 'message' => 'mismatched payment proof'],
] as $case) {
    try {
        $validate->invoke($model, array_replace($sale, $case['sale']), $binding, $session);
        throw new RuntimeException('Rejected case passed: ' . $case['message']);
    } catch (RuntimeException $e) {
        cash_check(!str_starts_with($e->getMessage(), 'Rejected case passed'), 'rejects ' . $case['message']);
    }
}

$source = file_get_contents($root . '/application/models/Pos_mobile_offline_import_model.php');
$sql = file_get_contents($root . '/sql/2026-10-03b_pos_mobile_offline_cash_import.sql');
cash_check(str_contains($source, '$this->db->trans_begin()')
    && str_contains($source, 'save_order_draft(')
    && str_contains($source, 'confirm_order(')
    && str_contains($source, 'save_cashier_payment(')
    && str_contains($source, 'import_status\' => \'POSTED')
    && str_contains($source, '$this->db->trans_commit()')
    && str_contains($source, '$this->db->trans_rollback()'), 'order, stock queue, cash and import receipt share one outer transaction');
cash_check(str_contains($sql, 'UNIQUE KEY uq_pos_mobile_offline_sale_event')
    && str_contains($sql, 'UNIQUE KEY uq_pos_mobile_offline_sale_order')
    && str_contains($sql, 'UNIQUE KEY uq_pos_mobile_offline_sale_ledger')
    && str_contains($sql, 'UNIQUE KEY uq_pos_mobile_offline_sale_payment'), 'all local identities have unique recovery mapping');
cash_check(str_contains($source, 'same_binding($existing, $binding)')
    && str_contains($source, 'hash_equals((string)$existing[\'payload_hash\']'), 'duplicate import requires matching device binding and exact payload hash');
