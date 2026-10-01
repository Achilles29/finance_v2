<?php
declare(strict_types=1);
// Current-period monitoring only. No repairs, migrations, messages or stock writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['output:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php tools/inventory/stock_integrity_audit.php [--output=/private/path/latest.json]\n"
        . "Read-only audit of current material FIFO/movement and component quantity/value.\n"
        . "Exit 0: matched; 2: discrepancy; 1: audit unavailable. Output is never a repair.\n";
    exit;
}
umask(0077);
date_default_timezone_set('Asia/Jakarta');
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
define('APPPATH', $root . '/application/');
define('FCPATH', $root . '/');
define('ENVIRONMENT', 'production');
function log_message($level, $message): void {}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function show_error($message = '', ...$args): void { throw new RuntimeException('Audit database tidak tersedia.'); }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
class InventoryAuditLoader
{
    public function database(): void {}
}
$context->load = new InventoryAuditLoader();
$report = ['checked_at' => date(DATE_ATOM), 'as_of_date' => date('Y-m-d'), 'read_only' => true];
$exit = 1;
try {
    require APPPATH . 'config/database.php';
    if (is_file(APPPATH . 'config/production/database.php')) { require APPPATH . 'config/production/database.php'; }
    $group = $active_group ?? 'default';
    if (($db[$group]['dbdriver'] ?? '') !== 'mysqli') { throw new RuntimeException('Driver audit tidak tersedia.'); }
    $db[$group]['db_debug'] = false;
    $db[$group]['pconnect'] = false;
    require BASEPATH . 'core/Model.php';
    require BASEPATH . 'database/DB.php';
    $context->db = DB($db[$group], true);
    $report['database'] = (string)$db[$group]['database'];
    if (!$context->db->conn_id) { throw new RuntimeException('Koneksi audit gagal.'); }
    $context->db->query('SET SESSION MAX_STATEMENT_TIME=45');
    if (!$context->db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')) {
        throw new RuntimeException('Transaksi read-only tidak tersedia.');
    }
    foreach (['inv_stock_period', 'inv_stock_movement_log', 'inv_material_fifo_lot', 'inv_division_monthly_stock',
        'inv_division_stock_opening_snapshot', 'inv_component_monthly_stock', 'inv_component_monthly_opening',
        'inv_component_lot', 'inv_component_movement_log', 'inv_stock_value_reconciliation', 'inv_stock_deficit'] as $table) {
        if (!$context->db->table_exists($table)) { throw new RuntimeException('Skema audit belum lengkap.'); }
    }
    $periods = $context->db->query('SELECT stock_domain, status FROM inv_stock_period WHERE period_month = ?', [date('Y-m-01')]);
    if (!$periods) { throw new RuntimeException('Periode audit tidak dapat dibaca.'); }
    $report['current_periods'] = $periods->result_array();
    require APPPATH . 'models/Purchase_model.php';
    require APPPATH . 'models/Production_model.php';
    $report['material'] = (new Purchase_model())->division_material_integrity_audit($report['as_of_date']);
    $components = (new Production_model())->component_reconcile_rows(['as_of_date' => $report['as_of_date']], 0);
    $componentErrors = array_values(array_filter($components['rows'] ?? [], static function (array $row): bool {
        return empty($row['is_match']);
    }));
    $report['component'] = ['summary' => $components['summary'] ?? [], 'mismatch_count' => count($componentErrors), 'samples' => array_slice($componentErrors, 0, 10)];
    $openingQuery = $context->db->query('SELECT s.id monthly_stock_id, s.component_id, s.location_type, s.division_id, s.opening_qty monthly_qty, o.opening_qty document_qty, s.opening_total_value monthly_value, o.opening_total_value document_value FROM inv_component_monthly_stock s JOIN inv_component_monthly_opening o ON o.month_key = s.month_key AND o.location_type = s.location_type AND o.division_id <=> s.division_id AND o.component_id = s.component_id AND o.uom_id = s.uom_id WHERE s.month_key = ? AND (ABS(s.opening_qty-o.opening_qty)>0.0001 OR ABS(s.opening_total_value-o.opening_total_value)>0.05)', [date('Y-m-01')]);
    if (!$openingQuery) { throw new RuntimeException('Pemeriksaan opening gagal.'); }
    $openingErrors = $openingQuery->result_array();
    $report['component_opening'] = ['mismatch_count' => count($openingErrors), 'samples' => array_slice($openingErrors, 0, 10)];
    $deficits = $context->db->query("SELECT stock_domain, COUNT(*) n FROM inv_stock_deficit WHERE status = 'OPEN' AND qty_remaining > 0.0001 GROUP BY stock_domain");
    if (!$deficits) { throw new RuntimeException('Pemeriksaan defisit gagal.'); }
    $report['open_deficits'] = $deficits->result_array();
    $ok = !empty($report['material']['fifo']['ok']) && !empty($report['material']['movement']['ok']) && !$componentErrors && !$openingErrors;
    $report['status'] = $ok ? 'MATCH' : 'MISMATCH';
    $exit = $ok ? 0 : 2;
} catch (Throwable $e) {
    $report['status'] = 'ERROR';
    $report['message'] = 'Audit tidak selesai. Periksa skema, koneksi, dan log aplikasi; hasil ini bukan bukti stok sinkron.';
} finally {
    if (isset($context->db)) { $context->db->query('ROLLBACK'); $context->db->close(); }
}
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (!empty($options['output'])) {
    $path = (string)$options['output'];
    $directory = realpath(dirname($path));
    if (!$directory || str_starts_with($directory . '/', realpath($root) . '/') || is_link($path)) {
        fwrite(STDERR, "Output harus di direktori privat di luar webroot aplikasi.\n"); exit(1);
    }
    $temporary = tempnam($directory, '.inventory-audit-');
    if ($temporary === false || file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $path)) {
        fwrite(STDERR, "Gagal menyimpan hasil audit.\n"); exit(1);
    }
}
echo $json;
exit($exit);
