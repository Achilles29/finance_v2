<?php
declare(strict_types=1);

// Compile the real model's queries with CI3, without a DB connection or writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
function log_message($level, $message): void {}
require BASEPATH . 'core/Common.php';
require BASEPATH . 'database/DB_driver.php';
require BASEPATH . 'database/DB_query_builder.php';
class CI_Model { public $db; }
require dirname(__DIR__, 2) . '/application/models/Purchase_model.php';

final class AdjustmentFilterCompiler extends CI_DB_query_builder
{
    protected $_escape_char = '`';
    public string $sql = '';
    public function table_exists($table_name) { return true; }
    public function field_exists($field_name, $table_name) { return true; }
    public function get($table = '', $limit = null, $offset = null) {
        $this->sql = $this->get_compiled_select($table);
        return new class { public function result_array(): array { return []; } };
    }
}
$model = new Purchase_model();
$model->db = new AdjustmentFilterCompiler([]);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++;
};
foreach (['list_stock_adjustments', 'list_stock_adjustment_detail_rows'] as $method) {
    foreach (['KITCHEN', 'KITCHEN_EVENT', 'OTHER'] as $destination) {
        $model->$method('DIVISION', '2026-09', 'ADJ-test', 25, 3, $destination, '', '', 'DRAFT');
        $sql = $model->db->sql;
        $check(strpos($sql, "DATE_FORMAT(h.adjustment_date, '%Y-%m') = '2026-09'") !== false, $method . ' quotes month as a string, not subtraction');
        $check(strpos($sql, 'COALESCE(h.destination_type, "OTHER") = ' . "'" . $destination . "'") !== false, $method . ' quotes exact destination ' . $destination);
        $check(strpos($sql, "`h`.`status` = 'DRAFT'") !== false && strpos($sql, '`h`.`division_id` = 3') !== false, $method . ' retains status and division filters');
    }
    $model->$method('WAREHOUSE', '2026-09', '', 25);
    $check(strpos($model->db->sql, "DATE_FORMAT(h.adjustment_date, '%Y-%m') = '2026-09'") !== false, $method . ' warehouse month is quoted');
    $check(strpos($model->db->sql, 'COALESCE(h.destination_type') === false, $method . ' warehouse does not filter division destination');
    $model->$method('DIVISION', '2026-09', '', 25, 3, 'ALL', '2026-09-10', '2026-09-25', 'VOID');
    $sql = $model->db->sql;
    $check(strpos($sql, "`h`.`adjustment_date` >= '2026-09-10'") !== false && strpos($sql, "`h`.`adjustment_date` <= '2026-09-25'") !== false && strpos($sql, 'DATE_FORMAT(h.adjustment_date') === false, $method . ' date range takes precedence over month');
    $check(strpos($sql, 'COALESCE(h.destination_type') === false, $method . ' ALL has no exact destination filter');
}
echo 'PASS adjustment SQL filter checks=' . $checks . '; no DB connection or writes.' . PHP_EOL;
