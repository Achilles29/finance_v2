<?php
// Opt-in integration test. Refuses network DBs and non-disposable datadirs.
require __DIR__ . '/component_fifo_value_regression_smoke.php';
date_default_timezone_set('Asia/Jakarta');
function log_message($level, $message) {}
function is_php($version) { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message, ...$args) { throw new RuntimeException('Framework error: ' . $message); }
require BASEPATH . 'database/DB_driver.php';
require BASEPATH . 'database/DB_query_builder.php';
class CI_DB extends CI_DB_query_builder {}
require BASEPATH . 'database/drivers/mysqli/mysqli_driver.php';
require APPPATH . 'libraries/InventoryPeriodGuard.php';
require APPPATH . 'libraries/InventoryCutoffAudit.php';

$socket = getenv('FINANCE_COMPONENT_TEST_SOCKET') ?: '';
if (!preg_match('#^/tmp/finance-component-regression\.[A-Za-z0-9]+/mysql\.sock$#D', $socket) || is_link(dirname($socket))) {
    throw new RuntimeException('Use a dedicated temporary MariaDB socket; active databases are forbidden.');
}
$db = new CI_DB_mysqli_driver(['hostname' => $socket, 'username' => 'root', 'password' => '',
    'database' => '', 'dbdriver' => 'mysqli', 'db_debug' => false, 'pconnect' => false, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_general_ci']);
check($db->initialize(), 'connect to isolated Unix socket');
$server = $db->query('SELECT @@datadir AS datadir, @@skip_networking AS isolated, VERSION() AS version')->row_array();
check(realpath($server['datadir']) === realpath(dirname($socket) . '/data') && (int)$server['isolated'] === 1, 'datadir isolated and TCP disabled');
$schema = 'component_regression_' . bin2hex(random_bytes(5));
check($db->query('CREATE DATABASE `' . $schema . '`') !== false && $db->db_select($schema), 'fresh disposable schema');
echo 'MariaDB: ' . $server['version'] . "\n";

// Schema only, no baseline seeds/customer data. Disable FK checks only while
// creating mutually-referencing empty tables, re-enable before any test writes.
$sql = file_get_contents(dirname(__DIR__, 2) . '/sql/baseline/2026-09-05_clean_install_schema.sql');
preg_match_all('/^CREATE TABLE `([^`]+)` \(.*?^\) ENGINE=[^\r\n]+;/ms', $sql, $definitions, PREG_SET_ORDER);
$needed = ['inv_component_monthly_stock', 'inv_component_movement_log', 'inv_component_lot',
    'inv_component_lot_issue_log', 'inv_component_lot_issue_line', 'inv_stock_value_reconciliation',
    'inv_stock_period', 'inv_stock_cutoff_event', 'inv_component_monthly_opening', 'mst_component', 'mst_component_category',
    'mst_product_division', 'mst_operational_division', 'mst_uom', 'org_employee', 'auth_user'];
$db->query('SET FOREIGN_KEY_CHECKS=0');
foreach ($definitions as $definition) {
    if (!in_array($definition[1], $needed, true)) { continue; }
    if ($db->query($definition[0]) === false) { throw new RuntimeException('Fixture schema: ' . json_encode($db->error())); }
}
$db->query('SET FOREIGN_KEY_CHECKS=1');
same((float)$db->query('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->row_array()['enabled'], 1, 'FK checks active during test');
$db->insert('mst_operational_division', ['id' => 1, 'code' => 'TEST-KITCHEN', 'name' => 'Synthetic Kitchen']);
$db->insert('mst_product_division', ['id' => 1, 'code' => 'TEST', 'name' => 'Synthetic']);
$db->insert('mst_component_category', ['id' => 1, 'code' => 'TEST', 'name' => 'Synthetic']);
$db->insert('mst_uom', ['id' => 1, 'code' => 'PCS', 'name' => 'Pieces']);
check($db->insert('mst_component', ['id' => 1, 'component_code' => 'TEST-1', 'component_name' => 'Synthetic component',
    'component_type' => 'BASE', 'product_division_id' => 1, 'operational_division_id' => 1, 'component_category_id' => 1, 'uom_id' => 1]), 'synthetic master references valid');
$day = date('Y-m-d'); $month = date('Y-m-01');
$identity = ['location_type' => 'KITCHEN', 'division_id' => 1, 'component_id' => 1, 'uom_id' => 1];
check($db->insert('inv_component_monthly_stock', $identity + ['month_key' => $month, 'opening_qty' => 20,
    'opening_total_value' => 300, 'closing_qty' => 20, 'total_value' => 300, 'avg_cost' => 15]), 'opening stock seeded');
$stockId = (int)$db->insert_id();
$model = instanceWithoutBootstrap(Production_model::class, $db);
$pos = instanceWithoutBootstrap(PosOrderStockService::class, $db);
$writer = instanceWithoutBootstrap(ComponentStockWriter::class, $db);
$lots = instanceWithoutBootstrap(ComponentLotManager::class, $db);
$guard = instanceWithoutBootstrap(InventoryPeriodGuard::class, $db);
$ciProperty = new ReflectionProperty($lots, 'ci'); $ciProperty->setAccessible(true);
$ciProperty->setValue($lots, (object)['db' => $db, 'load' => new FixtureLoader(), 'inventoryperiodguard' => $guard,
    'inventorycutoffaudit' => instanceWithoutBootstrap(InventoryCutoffAudit::class, $db)]);
$model->inventoryperiodguard = $guard;
check($lots->ensureReady()['ok'], 'actual lot schema checks');
foreach ([10, 20] as $index => $cost) {
    check($db->insert('inv_component_lot', $identity + ['lot_no' => 'TEST-' . $index, 'receipt_date' => $day,
        'unit_cost' => $cost, 'qty_in_total' => 10, 'qty_balance' => 10, 'created_at' => $day . ' 00:00:00', 'updated_at' => $day . ' 00:00:00']), 'FIFO lot seeded');
}
$readBalance = static function () use ($db, $stockId): array { return $db->from('inv_component_monthly_stock')->where('id', $stockId)->get()->row_array(); };
$lotValue = static function () use ($db): float { return (float)$db->query("SELECT COALESCE(SUM(qty_balance * unit_cost),0) AS value FROM inv_component_lot WHERE status = 'OPEN'")->row_array()['value']; };
$payload = $identity + ['movement_date' => $day, 'movement_type' => 'USAGE', 'qty' => 12,
    'source_module' => 'POS', 'source_table' => 'pos_stock_commit', 'source_id' => 10, 'source_line_id' => 11, 'notes' => '', 'actor_employee_id' => 0];
$db->trans_begin();
$issue = $lots->consumeUsage($identity + ['issue_date' => $day, 'qty_out' => 12, 'source_table' => 'pos_stock_commit', 'source_id' => 10, 'source_line_id' => 11]);
check($issue['ok'], 'actual mixed-lot FIFO issue'); same((float)$issue['data']['total_cost'], 140, '10 at 10 plus 2 at 20');
$sale = invoke($pos, 'post_component_aggregate_movement', $payload + ['unit_cost' => $issue['data']['avg_unit_cost'], 'total_cost' => $issue['data']['total_cost']]);
check($sale['ok'] && $db->trans_status(), 'POS aggregate posted transactionally'); $db->trans_commit();
same((float)$readBalance()['total_value'], 160, 'remaining FIFO monthly value'); same(round($lotValue(), 2), 160, 'remaining actual lot value');
$db->trans_begin();
$return = $lots->rollbackIssueLotsBySource('pos_stock_commit', 10, 11, 'Partial test', 1);
check($return['ok'], 'actual partial lot return'); same($return['data']['rolled_cost'], 20, 'partial return from last allocated lot, not blended 11.666667');
$void = invoke($pos, 'rollback_component_usage_movement', 10, 11, 1.0, $sale['movement_id'], $identity + ['movement_date' => $day, 'returned_cost' => $return['data']['rolled_cost']]);
check($void['ok'] && $model->rebuild_component_history_for_identity($identity)['ok'], 'partial refund and actual rebuild');
$db->trans_commit(); same((float)$readBalance()['total_value'], round($lotValue(), 2), 'refund monthly/lot values equal');
$db->trans_begin();
$return = $lots->rollbackIssueLotsBySource('pos_stock_commit', 10, 11, 'Rest test', 11);
$void = invoke($pos, 'rollback_component_usage_movement', 10, 11, 11.0, $sale['movement_id'], $identity + ['movement_date' => $day, 'returned_cost' => $return['data']['rolled_cost']]);
check($return['ok'] && $void['ok'] && $model->rebuild_component_history_for_identity($identity)['ok'], 'full refund and rebuild');
$db->trans_commit(); same((float)$readBalance()['total_value'], 300, 'full refund restores initial monthly value'); same(round($lotValue(), 2), 300, 'full refund restores initial lot value');

$db->trans_begin();
$issue = $lots->consumeUsage($identity + ['issue_date' => $day, 'qty_out' => 5, 'source_table' => 'inv_component_adjustment', 'source_id' => 20, 'source_line_id' => 21]);
check($issue['ok'], 'adjustment consumes actual FIFO');
invoke($writer, 'post_single_movement', array_replace($payload, ['movement_type' => 'ADJUSTMENT_MINUS', 'qty' => 5,
    'source_table' => 'inv_component_adjustment', 'source_id' => 20, 'source_line_id' => 21,
    'unit_cost' => $issue['data']['avg_unit_cost'], 'total_cost' => $issue['data']['total_cost']]));
$adjustmentMovement = $db->from('inv_component_movement_log')->where('source_table', 'inv_component_adjustment')->where('source_id', 20)->get()->row_array();
same((float)$readBalance()['total_value'], round($lotValue(), 2), 'adjustment stock and lot value match');
$returned = $lots->rollbackIssueLotsBySource('inv_component_adjustment', 20);
check($returned['ok'], 'adjustment issue rollback');
$reversed = invoke($model, 'reverse_component_document_movement_row', $adjustmentMovement, $day, 'inv_component_adjustment', 20,
    'PRODUCTION_ADJUSTMENT_VOID', 0, ['returned_lots' => $returned['data']['allocations']]);
check($reversed['ok'] && $model->rebuild_component_history_for_identity($identity)['ok'], 'adjustment void uses actual return cost');
same((float)$readBalance()['total_value'], 300, 'adjustment void restores value');
same(round($lotValue(), 2), 300, 'adjustment void restores lot value');
same((float)$readBalance()['adjustment_minus_qty'], 0, 'adjustment minus bucket cleared by reversal');
same((float)$readBalance()['adjustment_minus_total_value'], 0, 'adjustment minus value bucket cleared');
$db->trans_rollback();

// Shared component input path used by production, followed by output removal.
$db->trans_begin();
foreach (['WASTE' => 2, 'SPOIL' => 3] as $kind => $qty) {
    $issue = $lots->consumeUsage($identity + ['issue_date' => $day, 'qty_out' => $qty, 'source_table' => 'inv_component_adjustment', 'source_id' => 25, 'source_line_id' => 26]);
    check($issue['ok'], 'same-line ' . $kind . ' lot issue');
    invoke($writer, 'post_single_movement', array_replace($payload, ['movement_type' => $kind, 'qty' => $qty,
        'unit_cost' => $issue['data']['avg_unit_cost'], 'total_cost' => $issue['data']['total_cost'],
        'source_table' => 'inv_component_adjustment', 'source_id' => 25, 'source_line_id' => 26]));
}
$movements = $db->from('inv_component_movement_log')->where('source_table', 'inv_component_adjustment')->where('source_id', 25)->get()->result_array();
$returned = $lots->rollbackIssueLotsBySource('inv_component_adjustment', 25);
foreach ($movements as $movement) {
    check(invoke($model, 'reverse_component_document_movement_row', $movement, $day, 'inv_component_adjustment', 25,
        'PRODUCTION_ADJUSTMENT_VOID', 0, ['returned_lots' => $returned['data']['allocations']])['ok'], 'same-line ' . $movement['movement_type'] . ' reverse');
}
check($model->rebuild_component_history_for_identity($identity)['ok'], 'same-line multiple movement rebuild');
same((float)$readBalance()['total_value'], round($lotValue(), 2), 'same-line return cost not counted twice');
$db->trans_rollback();

// Shared component input path used by production, followed by output removal.
$db->trans_begin();
invoke($writer, 'post_single_movement', array_replace($payload, ['movement_type' => 'PRODUCTION_IN', 'qty' => 4,
    'unit_cost' => 10, 'total_cost' => 40, 'source_table' => 'inv_component_batch', 'source_id' => 30, 'source_line_id' => null]));
$newLot = $lots->registerProductionInboundLot($identity + ['qty_in' => 4, 'unit_cost' => 10, 'receipt_date' => $day,
    'lot_no' => 'TEST-BATCH', 'source_table' => 'inv_component_batch', 'source_id' => 30, 'resolve_open_deficit' => false]);
check($newLot['ok'], 'actual production lot registered');
$used = $lots->consumeUsage($identity + ['issue_date' => $day, 'lot_id' => $newLot['data']['id'], 'qty_out' => 1,
    'source_table' => 'inv_component_adjustment', 'source_id' => 40, 'source_line_id' => 41]);
check($used['ok'], 'batch output used by adjustment');
check(!$lots->voidInboundLotsBySource('inv_component_batch', 30)['ok'], 'batch with outstanding consumption cannot void');
check($lots->rollbackIssueLotsBySource('inv_component_adjustment', 40)['ok'], 'usage returned after adjustment void');
$productionMovement = $db->from('inv_component_movement_log')->where('source_table', 'inv_component_batch')->where('source_id', 30)->get()->row_array();
$removed = $lots->voidInboundLotsBySource('inv_component_batch', 30);
check($removed['ok'], 'unused batch lot can be voided');
$reversed = invoke($model, 'reverse_component_document_movement_row', $productionMovement, $day, 'inv_component_batch', 30,
    'PRODUCTION_BATCH_VOID', 0, ['removed_lots' => $removed['data']['allocations']]);
check($reversed['ok'] && $model->rebuild_component_history_for_identity($identity)['ok'], 'batch output void and rebuild');
same((float)$readBalance()['total_value'], 300, 'batch void removes output cost, not mixed balance average');
same(round($lotValue(), 2), 300, 'batch void matches lots');
$db->trans_rollback();

// New-month writers must carry both quantity and value, not a zero opening value.
$db->trans_begin();
$db->where('id', $stockId)->update('inv_component_monthly_stock', ['month_key' => date('Y-m-01', strtotime($month . ' -1 month'))]);
invoke($writer, 'post_single_movement', array_replace($payload, ['qty' => 5, 'unit_cost' => 10, 'total_cost' => 50]));
$newMonth = $db->from('inv_component_monthly_stock')->where('month_key', $month)->get()->row_array();
same((float)$newMonth['opening_total_value'], 300, 'first production/adjustment movement carries opening value');
$db->trans_rollback();
$db->trans_begin();
$db->where('id', $stockId)->update('inv_component_monthly_stock', ['month_key' => date('Y-m-01', strtotime($month . ' -1 month'))]);
check(invoke($pos, 'post_component_aggregate_movement', array_replace($payload, ['qty' => 5, 'unit_cost' => 10, 'total_cost' => 50]))['ok'], 'first POS movement in fresh month');
$newMonth = $db->from('inv_component_monthly_stock')->where('month_key', $month)->get()->row_array();
same((float)$newMonth['opening_total_value'], 300, 'first POS movement carries opening value');
$db->trans_rollback();

$db->trans_begin();
$quote = $lots->quotePhysicalCountReduction($identity + ['reference_date' => $day], 20, 20, 5);
check($quote['ok'] && $quote['matched'], 'physical-count quote with consistent opening stock');
same($quote['total_cost'], 100, 'physical count preserves existing LIFO correction policy: 5 at 20');
$nextQuote = $lots->quotePhysicalCountReduction($identity + ['reference_date' => $day], 20, 15, 6);
same($nextQuote['total_cost'], 110, 'physical quote respects earlier pending reduction: 5 at 20 plus 1 at 10');
check(!$lots->quotePhysicalCountReduction($identity + ['reference_date' => $day], 99, 99, 5)['matched'], 'legacy quantity drift is not mislabeled as normal physical reduction');
invoke($writer, 'post_single_movement', array_replace($payload, ['movement_type' => 'ADJUSTMENT_MINUS', 'qty' => 5,
    'unit_cost' => 20, 'total_cost' => $quote['total_cost'], 'source_table' => 'inv_component_adjustment', 'source_id' => 50]));
$synced = $lots->reconcileLotsToAuthoritativeBalance($identity + ['event_date' => $day, 'target_qty' => 15,
    'unit_cost' => $readBalance()['avg_cost'], 'source_table' => 'inv_component_adjustment', 'source_id' => 50]);
check($synced['ok'], 'actual physical lot reconciliation with audit');
same((float)$readBalance()['total_value'], round($lotValue(), 2), 'physical-count stock value matches actual structural lots');
check($model->rebuild_component_history_for_identity($identity)['ok'], 'physical-count rebuild');
same((float)$readBalance()['total_value'], round($lotValue(), 2), 'physical-count value remains consistent after rebuild');
$db->trans_rollback();

// Revaluation checkpoint at a deterministic timestamp, followed by a backdated
// void whose business timestamp precedes it but posting timestamp follows it.
$db->where('id >', 0)->update('inv_component_movement_log', ['created_at' => $day . ' 00:30:00']);
check($db->insert('inv_stock_value_reconciliation', ['revaluation_no' => 'TEST-REVALUE', 'revaluation_date' => $day,
    'period_month' => $month, 'stock_domain' => 'COMPONENT', 'stock_scope' => 'KITCHEN', 'location_type' => 'KITCHEN',
    'division_id' => 1, 'component_id' => 1, 'content_uom_id' => 1, 'monthly_stock_id' => $stockId,
    'stock_qty_snapshot' => 20, 'lot_qty_snapshot' => 20, 'stock_value_before' => 300, 'stock_value_after' => 400,
    'lot_value_after' => 400, 'resolution_mode' => 'MANUAL_TOTAL_VALUE', 'reason' => 'Synthetic test', 'posted_at' => $day . ' 01:00:00']), 'correction evidence seeded');
$db->where('id', $stockId)->update('inv_component_monthly_stock', ['total_value' => 400, 'avg_cost' => 20]);
check($model->rebuild_component_history_for_identity($identity)['ok'], 'real SQL rebuild honors correction');
same((float)$readBalance()['total_value'], 400, 'correction not erased');
check($model->rebuild_component_history_for_identity($identity)['ok'], 'real SQL rebuild replay'); same((float)$readBalance()['total_value'], 400, 'replay stable');
$daily = invoke($model, 'fetch_component_daily_projection_rows', ['component_id' => 1], $month, $day);
check(count($daily) > 0, 'actual daily report projection');
same((float)end($daily)['total_value'], 400, 'daily report includes correction');
same((float)$readBalance()['id'], $stockId, 'monthly ID retained');

$db->where('revaluation_no', 'TEST-REVALUE')->update('inv_stock_value_reconciliation', ['stock_qty_snapshot' => 999]);
$before = $readBalance();
check(!$model->rebuild_component_history_for_identity($identity)['ok'], 'inconsistent checkpoint refused on MariaDB');
check($readBalance() === $before, 'real rollback leaves stock unchanged');
check($db->trans_status(), 'no hidden SQL errors');
echo 'PASS: ' . $GLOBALS['checks'] . ' MariaDB checks; schema retained for inspection: ' . $schema . ". No active database connected.\n";
