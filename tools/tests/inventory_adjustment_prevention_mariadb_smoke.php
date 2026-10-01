<?php
declare(strict_types=1);
// Never reads application DB credentials. Only a disposable, non-networked server is accepted.
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
define('ENVIRONMENT', 'testing');
date_default_timezone_set('Asia/Jakarta');
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message, ...$args): void { throw new RuntimeException((string)$message); }
$ci = new stdClass();
function &get_instance() { return $GLOBALS['ci']; }
require BASEPATH . 'core/Model.php';
require BASEPATH . 'database/DB.php';
$socket = getenv('FINANCE_COMPONENT_TEST_SOCKET') ?: '';
if (!preg_match('#^/tmp/finance-component-regression\.[A-Za-z0-9]+/mysql\.sock$#D', $socket) || is_link(dirname($socket))) {
    throw new RuntimeException('Only a disposable local test socket is permitted.');
}
$db = DB(['hostname' => $socket, 'username' => 'root', 'password' => '', 'database' => '',
    'dbdriver' => 'mysqli', 'db_debug' => false, 'pconnect' => false, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_general_ci'], true);
$server = $db->query('SELECT @@datadir datadir, @@skip_networking isolated')->row_array();
if (realpath($server['datadir']) !== realpath(dirname($socket) . '/data') || (int)$server['isolated'] !== 1) {
    throw new RuntimeException('Refusing non-disposable MariaDB.');
}
$schema = getenv('FINANCE_ADJUSTMENT_TEST_SCHEMA') ?: ('adjustment_regression_' . bin2hex(random_bytes(5)));
if (!preg_match('/^adjustment_regression_[a-f0-9]{10}$/D', $schema)) { throw new RuntimeException('Invalid disposable schema name.'); }
$db->query('CREATE DATABASE IF NOT EXISTS `' . $schema . '`');
$db->db_select($schema);
$ci->db = $db;
class AdjustmentTestLoader
{
    public function database(): void {}
    public function library($name): void {
        $key = strtolower($name);
        $ci = get_instance();
        if (isset($ci->$key)) { return; }
        if ($name === 'PosAvailabilityRebuildService') {
            $ci->$key = new class {
                public function handle_component_change(...$args): array { return ['success_count' => 0, 'failed_count' => 0]; }
                public function handle_material_change(...$args): array { return ['success_count' => 0, 'failed_count' => 0]; }
            };
            return;
        }
        $allowed = ['InventoryPeriodGuard', 'InventoryCutoffAudit', 'InventoryStructuralLotReversal', 'InventoryAdjustmentProfile',
            'InventoryAdjustmentGuard', 'MaterialFifoManager', 'InventoryLedger', 'InventoryMovementReversalService',
            'InventoryDeficitService', 'InventoryAdjustmentIntent', 'ComponentStockWriter', 'ComponentLotManager'];
        if (!in_array($name, $allowed, true)) { throw new RuntimeException('Unexpected library: ' . $name); }
        require_once APPPATH . 'libraries/' . $name . '.php';
        $ci->$key = new $name();
    }
    public function model($name): void {
        if (!in_array($name, ['Purchase_model', 'Production_model'], true)) { throw new RuntimeException('Unexpected model: ' . $name); }
        if (!isset(get_instance()->$name)) { require_once APPPATH . 'models/' . $name . '.php'; get_instance()->$name = new $name(); }
    }
    public function helper($name): void { require_once APPPATH . 'helpers/' . $name . '_helper.php'; }
}
$ci->load = new AdjustmentTestLoader();
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks, $db;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label . ' DB: ' . json_encode($db->error())); }
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
function insertRow(string $table, array $row): int {
    global $db;
    if (!$db->insert($table, $row)) { throw new RuntimeException($table . ': ' . json_encode($db->error())); }
    return (int)$db->insert_id();
}
function row(string $table, int $id): array { global $db; return $db->get_where($table, ['id' => $id])->row_array(); }
function checkResult(array $result, string $label): void { check(!empty($result['ok']), $label . ' ' . ($result['message'] ?? '') . (empty($result['ok']) ? ' ' . json_encode($result['data'] ?? []) : '')); }
preg_match_all('/^CREATE TABLE `([^`]+)` \(.*?^\) ENGINE=[^\r\n]+;/ms', file_get_contents(dirname(__DIR__, 2) . '/sql/baseline/2026-09-05_clean_install_schema.sql'), $definitions, PREG_SET_ORDER);
$db->query('SET FOREIGN_KEY_CHECKS=0');
foreach ($definitions as $definition) {
    if (!preg_match('/^(inv_|mst_(?:item|material|purchase_catalog|operational_division|uom|product_division|component)|auth_user$|org_employee$|aud_transaction_log$|sys_setting)/', $definition[1])) { continue; }
    if (!$db->table_exists($definition[1]) && !$db->query($definition[0])) { throw new RuntimeException('Fixture schema: ' . json_encode($db->error())); }
    $db->query('DELETE FROM `' . $definition[1] . '`');
}
$db->query('SET FOREIGN_KEY_CHECKS=1');
insertRow('mst_operational_division', ['id' => 1, 'code' => 'KITCHEN', 'name' => 'Synthetic Kitchen']);
insertRow('mst_product_division', ['id' => 1, 'code' => 'TEST', 'name' => 'Synthetic']);
insertRow('mst_component_category', ['id' => 1, 'code' => 'TEST', 'name' => 'Synthetic']);
insertRow('mst_item_category', ['id' => 1, 'code' => 'TEST', 'name' => 'Synthetic']);
insertRow('mst_uom', ['id' => 1, 'code' => 'GR', 'name' => 'Gram']);
insertRow('mst_material', ['id' => 1, 'material_code' => 'TEST', 'material_name' => 'Synthetic material', 'content_uom_id' => 1]);
insertRow('mst_item', ['id' => 1, 'item_code' => 'TEST', 'item_name' => 'Synthetic material', 'item_category_id' => 1, 'buy_uom_id' => 1, 'content_uom_id' => 1, 'material_id' => 1]);
insertRow('mst_component', ['id' => 1, 'component_code' => 'TEST', 'component_name' => 'Synthetic component', 'component_type' => 'BASE', 'product_division_id' => 1, 'operational_division_id' => 1, 'component_category_id' => 1, 'uom_id' => 1]);
$day = date('Y-m-d'); $month = date('Y-m-01'); $pk = hash('sha256', 'synthetic-profile');
$identity = ['item_id' => 1, 'material_id' => 1, 'buy_uom_id' => 1, 'content_uom_id' => 1, 'profile_key' => $pk];
insertRow('mst_purchase_catalog', $identity + ['catalog_name' => 'Synthetic material', 'content_per_buy' => 1, 'standard_price' => 310]);
$materialStock = insertRow('inv_division_monthly_stock', $identity + ['month_key' => $month, 'division_id' => 1, 'destination_type' => 'KITCHEN', 'identity_key' => $pk, 'profile_content_per_buy' => 1, 'opening_qty_buy' => 371, 'opening_qty_content' => 371, 'opening_total_value' => 115010, 'closing_qty_buy' => 371, 'closing_qty_content' => 371, 'avg_cost_per_content' => 310, 'total_value' => 115010]);
insertRow('inv_division_stock_opening_snapshot', $identity + ['snapshot_month' => $month, 'division_id' => 1, 'destination_type' => 'KITCHEN', 'opening_qty_buy' => 371, 'opening_qty_content' => 371, 'opening_avg_cost_per_content' => 310, 'opening_total_value' => 115010]);
$materialLot = insertRow('inv_material_fifo_lot', $identity + ['lot_no' => 'TEST-MATERIAL', 'location_scope' => 'DIVISION', 'division_id' => 1, 'destination_type' => 'KITCHEN', 'receipt_date' => $month, 'qty_in' => 371, 'qty_balance' => 371, 'unit_cost' => 310]);
$ci->load->model('Purchase_model'); $purchase = $ci->Purchase_model;
$ci->load->model('Production_model'); $production = $ci->Production_model;
$ci->load->library('InventoryAdjustmentProfile');
$ci->load->library('InventoryAdjustmentGuard');
$ci->load->library('InventoryStructuralLotReversal');
$materialHeader = ['adjustment_date' => $day, 'stock_scope' => 'DIVISION', 'division_id' => 1, 'destination_type' => 'KITCHEN'];
$materialLine = $identity + ['profile_content_per_buy' => 1, 'unit_cost' => 310];
$resolved = $ci->inventoryadjustmentprofile->resolve($materialHeader, array_replace($materialLine, ['profile_key' => null]));
checkResult($resolved, 'unambiguous NULL profile resolved');
check($resolved['line']['profile_key'] === $pk, 'FIFO and ledger receive the same canonical key');
insertRow('mst_purchase_catalog', array_replace($identity, ['profile_key' => hash('sha256', 'other')]) + ['catalog_name' => 'Other profile', 'content_per_buy' => 1]);
check(!$ci->inventoryadjustmentprofile->resolve($materialHeader, array_replace($materialLine, ['profile_key' => null]))['ok'], 'ambiguous profile rejected instead of picking newest');
checkResult($ci->inventoryadjustmentprofile->resolve($materialHeader, $materialLine), 'explicit profile remains valid with multiple brands');
$db->where('profile_key <>', $pk)->delete('mst_purchase_catalog');

$saved = $purchase->save_stock_adjustment($materialHeader, [$materialLine + ['input_mode' => 'PHYSICAL_COUNT', 'qty_variance_content' => 31, 'physical_qty_snapshot_content' => 340]], 0);
checkResult($saved, 'material physical-count draft');
$id = (int)$saved['id'];
checkResult($purchase->post_stock_adjustment($id, 0), 'material physical-count post');
check((float)row('inv_material_fifo_lot', $materialLot)['qty_balance'] === 340.0, '31 grams actually removed from lot');
check((float)row('inv_division_monthly_stock', $materialStock)['closing_qty_content'] === 340.0, 'ledger also reduced by 31');
$before = row('inv_material_fifo_lot', $materialLot);
check(!$purchase->post_stock_adjustment($id, 0)['ok'] && row('inv_material_fifo_lot', $materialLot) === $before, 'duplicate post changes nothing');
checkResult($purchase->void_posted_stock_adjustment($id, 0), 'VOID physical count');
check((float)row('inv_material_fifo_lot', $materialLot)['qty_balance'] === 371.0, 'VOID restores all 31 grams to FIFO');
$materialStock = (int)$db->get_where('inv_division_monthly_stock', ['month_key' => $month, 'division_id' => 1, 'destination_type' => 'KITCHEN', 'profile_key' => $pk])->row_array()['id'];
check((float)row('inv_division_monthly_stock', $materialStock)['closing_qty_content'] === 371.0, 'VOID restores ledger equally');
$before = row('inv_material_fifo_lot', $materialLot);
check(!$purchase->void_posted_stock_adjustment($id, 0)['ok'] && row('inv_material_fifo_lot', $materialLot) === $before, 'duplicate VOID changes nothing');
$db->trans_begin();
$retry = $ci->inventorystructurallotreversal->reverse('MATERIAL', 'inv_stock_adjustment', $id, $day);
checkResult($retry, 'structural reversal retry');
check($retry['data']['reversed_count'] === 0, 'audit link prevents duplicate restoration');
$db->trans_rollback();

$saved = $purchase->save_stock_adjustment($materialHeader, [array_replace($materialLine, ['profile_key' => null, 'qty_adjustment_plus_content' => 6, 'input_mode' => 'DELTA'])], 0);
checkResult($saved, 'legacy NULL profile draft');
checkResult($purchase->post_stock_adjustment((int)$saved['id'], 0), 'NULL profile plus uses one canonical identity');
$line = $purchase->get_stock_adjustment_lines((int)$saved['id'])[0];
check($line['profile_key'] === $pk && row('inv_material_fifo_lot', (int)$line['adjustment_plus_lot_id'])['profile_key'] === $pk, 'profile persisted on document and lot');
checkResult($purchase->void_posted_stock_adjustment((int)$saved['id'], 0), 'ordinary delta plus VOID still works');

$newProfile = array_replace($materialLine, ['profile_key' => hash('sha256', 'new-profile-without-opening')]);
insertRow('mst_purchase_catalog', array_intersect_key($newProfile, $identity) + ['catalog_name' => 'New synthetic profile', 'content_per_buy' => 1]);
$saved = $purchase->save_stock_adjustment($materialHeader, [$newProfile + ['qty_adjustment_plus_content' => 6, 'input_mode' => 'DELTA']], 0);
checkResult($saved, 'new profile without opening draft');
checkResult($purchase->post_stock_adjustment((int)$saved['id'], 0), 'first stock for new profile');
checkResult($purchase->void_posted_stock_adjustment((int)$saved['id'], 0), 'VOID first stock returns new profile to empty');
checkResult($ci->inventoryadjustmentguard->checkMaterial($materialHeader, [$newProfile]), 'empty profile has no residual FIFO after VOID');

$db->trans_begin();
$stocks = []; $lotRows = [];
for ($i = 1; $i <= 350; $i++) {
    $extraKey = hash('sha256', 'pagination-profile-' . $i);
    $extra = array_replace($identity, ['profile_key' => $extraKey]);
    $stocks[] = $extra + ['month_key' => $month, 'division_id' => 1, 'destination_type' => 'KITCHEN', 'identity_key' => $extraKey,
        'profile_content_per_buy' => 1, 'opening_qty_content' => 1, 'closing_qty_content' => 1, 'avg_cost_per_content' => 1, 'total_value' => 1];
    $lotRows[] = $extra + ['lot_no' => 'PAGE-' . $i, 'location_scope' => 'DIVISION', 'division_id' => 1, 'destination_type' => 'KITCHEN', 'receipt_date' => $month, 'qty_in' => 1, 'qty_balance' => 1, 'unit_cost' => 1];
}
check($db->insert_batch('inv_division_monthly_stock', $stocks) === 350 && $db->insert_batch('inv_material_fifo_lot', $lotRows) === 350, 'more than 300 synthetic profiles seeded');
$compare = $purchase->list_division_material_stock_compare($day, '', 1, 1, 'KITCHEN');
check(count($compare['rows']) === 1 && (float)$compare['rows'][0]['balance_qty_content'] === 721.0, 'one-row UI limit still compares every underlying profile');
check(empty($compare['rows'][0]['has_lot_mismatch']) && empty($compare['rows'][0]['has_profile_lot_mismatch']), 'complete source scope creates no false FIFO mismatch');
$db->trans_rollback();

$warehouseHeader = ['adjustment_date' => $day, 'stock_scope' => 'WAREHOUSE', 'division_id' => null, 'destination_type' => null];
insertRow('inv_warehouse_monthly_stock', $identity + ['month_key' => $month, 'identity_key' => $pk, 'profile_content_per_buy' => 1,
    'opening_qty_buy' => 100, 'opening_qty_content' => 100, 'opening_total_value' => 31000,
    'closing_qty_buy' => 100, 'closing_qty_content' => 100, 'avg_cost_per_content' => 310, 'total_value' => 31000]);
insertRow('inv_warehouse_stock_opening_snapshot', $identity + ['snapshot_month' => $month, 'opening_qty_buy' => 100,
    'opening_qty_content' => 100, 'opening_avg_cost_per_content' => 310, 'opening_total_value' => 31000]);
$db->trans_begin();
checkResult($ci->materialfifomanager->syncWarehouseAggregateProfile($identity + ['movement_date' => $day]), 'warehouse opening aggregate fixture');
$db->trans_commit();
foreach ([
    ['input_mode' => 'DELTA', 'qty_adjustment_plus_content' => 6],
    ['input_mode' => 'DELTA', 'qty_variance_content' => 6],
    ['input_mode' => 'PHYSICAL_COUNT', 'qty_variance_content' => 10, 'physical_qty_snapshot_content' => 90],
    ['input_mode' => 'PHYSICAL_COUNT', 'qty_adjustment_plus_content' => 10, 'physical_qty_snapshot_content' => 110],
] as $case => $quantity) {
    $saved = $purchase->save_stock_adjustment($warehouseHeader, [$quantity + $materialLine + ['profile_name' => 'Synthetic material']], 0);
    checkResult($saved, 'warehouse draft case ' . $case);
    checkResult($purchase->post_stock_adjustment((int)$saved['id'], 0), 'warehouse posting case ' . $case);
    checkResult($purchase->void_posted_stock_adjustment((int)$saved['id'], 0), 'warehouse VOID case ' . $case);
    $stock = $db->get_where('inv_warehouse_monthly_stock', ['month_key' => $month, 'profile_key' => $pk])->row_array();
    check((float)$stock['closing_qty_content'] === 100.0, 'warehouse VOID restores opening case ' . $case);
    checkResult($ci->inventoryadjustmentguard->checkMaterial($warehouseHeader, [$materialLine]), 'warehouse aggregate stays consistent case ' . $case);
}

$componentIdentity = ['location_type' => 'KITCHEN', 'division_id' => 1, 'component_id' => 1, 'uom_id' => 1];
$componentStock = insertRow('inv_component_monthly_stock', $componentIdentity + ['month_key' => $month, 'opening_qty' => 20, 'opening_total_value' => 300, 'closing_qty' => 20, 'avg_cost' => 15, 'total_value' => 300]);
foreach ([10,20] as $i => $cost) { insertRow('inv_component_lot', $componentIdentity + ['lot_no' => 'TEST-COMP-' . $i, 'receipt_date' => $month, 'qty_in_total' => 10, 'qty_balance' => 10, 'unit_cost' => $cost, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); }
$ci->load->library('ComponentStockWriter');
function componentDraft(array $line): array {
    global $day, $componentIdentity;
    $id = insertRow('inv_component_adjustment', ['adjustment_no' => 'TEST-' . bin2hex(random_bytes(4)), 'adjustment_date' => $day, 'location_type' => 'KITCHEN', 'division_id' => 1]);
    insertRow('inv_component_adjustment_line', $line + ['adjustment_id' => $id, 'line_no' => 1, 'component_id' => 1, 'uom_id' => 1, 'unit_cost' => 15]);
    return row('inv_component_adjustment', $id);
}
$header = componentDraft(['input_mode' => 'PHYSICAL_COUNT', 'qty_adjust_neg' => 5, 'physical_qty_snapshot' => 15]);
checkResult($ci->componentstockwriter->post_adjustment($header, $production->get_component_adjustment_lines((int)$header['id'])), 'component physical count with mixed FIFO costs');
check((float)row('inv_component_monthly_stock', $componentStock)['total_value'] === 200.0, 'physical count uses actual lot correction cost, not average');
checkResult($production->void_component_adjustment((int)$header['id'], 0), 'component physical-count VOID');
check((float)row('inv_component_monthly_stock', $componentStock)['total_value'] === 300.0, 'component VOID restores original value');
checkResult($ci->inventoryadjustmentguard->checkComponent($header, [$componentIdentity]), 'component qty and value equal FIFO after VOID');

$header = componentDraft(['input_mode' => 'PHYSICAL_COUNT', 'qty_adjust_pos' => 5, 'physical_qty_snapshot' => 25]);
$lines = $production->get_component_adjustment_lines((int)$header['id']);
checkResult($ci->componentstockwriter->post_adjustment($header, $lines), 'component physical plus creates audited correction lot');
$before = row('inv_component_monthly_stock', $componentStock);
check(!$ci->componentstockwriter->post_adjustment($header, $lines)['ok'] && row('inv_component_monthly_stock', $componentStock) === $before, 'stale DRAFT request cannot repost component');
checkResult($production->void_component_adjustment((int)$header['id'], 0), 'VOID physical plus removes correction lot once');
checkResult($ci->inventoryadjustmentguard->checkComponent($header, [$componentIdentity]), 'plus/VOID leaves component qty and cost consistent');

$header = componentDraft(['input_mode' => 'PHYSICAL_COUNT', 'qty_adjust_pos' => 5, 'physical_qty_snapshot' => 25]);
checkResult($ci->componentstockwriter->post_adjustment($header, $production->get_component_adjustment_lines((int)$header['id'])), 'physical plus for consumed-lot rejection test');
$correctionLot = $db->get_where('inv_component_lot', ['source_table' => 'inv_component_adjustment', 'source_id' => $header['id'], 'status' => 'OPEN'])->row_array();
$db->trans_begin();
$issue = $ci->componentlotmanager->consumeUsage($componentIdentity + ['issue_date' => $day, 'lot_id' => (int)$correctionLot['id'], 'qty_out' => 1, 'source_table' => 'test_usage', 'source_id' => 1]);
checkResult($issue, 'consume one unit of correction lot');
$method = new ReflectionMethod(ComponentStockWriter::class, 'post_single_movement');
$method->setAccessible(true);
$method->invoke($ci->componentstockwriter, $componentIdentity + ['movement_date' => $day, 'movement_type' => 'USAGE', 'qty' => 1, 'unit_cost' => $issue['data']['avg_unit_cost'], 'total_cost' => $issue['data']['total_cost'], 'source_module' => 'TEST', 'source_table' => 'test_usage', 'source_id' => 1, 'source_line_id' => null, 'notes' => '', 'actor_employee_id' => 0]);
$db->trans_commit();
$before = [row('inv_component_lot', (int)$correctionLot['id']), row('inv_component_monthly_stock', $componentStock), row('inv_component_adjustment', (int)$header['id'])];
$blocked = $production->void_component_adjustment((int)$header['id'], 0);
check(empty($blocked['ok']) && str_contains($blocked['message'], 'dipakai'), 'consumed correction lot cannot be silently removed');
check($before === [row('inv_component_lot', (int)$correctionLot['id']), row('inv_component_monthly_stock', $componentStock), row('inv_component_adjustment', (int)$header['id'])], 'rejected VOID changes no lot, monthly stock or document');

// A failed guard must roll back header, monthly stock, movement, lots and audit.
$header = componentDraft(['input_mode' => 'DELTA', 'qty_adjust_neg' => 1]);
$db->where('id', $componentStock)->update('inv_component_monthly_stock', ['opening_total_value' => 999, 'total_value' => 999]);
$fingerprint = static function (): string {
    global $db;
    $data = [];
    foreach (['inv_component_monthly_stock','inv_component_lot','inv_component_lot_issue_log','inv_component_lot_issue_line','inv_component_movement_log','inv_stock_cutoff_event','inv_component_adjustment'] as $table) {
        $data[$table] = $db->order_by('id','ASC')->get($table)->result_array();
    }
    return hash('sha256', json_encode($data));
};
$before = $fingerprint();
$bad = $ci->componentstockwriter->post_adjustment($header, $production->get_component_adjustment_lines((int)$header['id']));
check(empty($bad['ok']) && str_contains($bad['message'], 'Nilai'), 'existing value inconsistency rejected with actionable message');
check($before === $fingerprint(), 'failed posting rolls back every inventory artifact');

$db->where('stock_domain', 'COMPONENT')->where('period_month', $month)->update('inv_stock_period', ['status' => 'CLOSED']);
$before = $fingerprint();
check(empty($ci->componentstockwriter->post_adjustment($header, [$componentIdentity])['ok']), 'closed period blocks adjustment');
check($before === $fingerprint(), 'closed-period rejection makes no inventory writes');
check($db->trans_status(), 'no hidden database errors');
echo 'PASS: ' . $checks . ' adjustment prevention checks; disposable schema ' . $schema . ". No production connection.\n";
