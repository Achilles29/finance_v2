<?php
// Regression harness: all database operations are in-memory; no config/bootstrap or network.
// No application bootstrap, service constructor, configuration, or database connection.
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
function &get_instance() { throw new RuntimeException('Application bootstrap forbidden'); }
class CI_Model { public $db; public $load; public $inventoryperiodguard; }
final class FixtureLoader { public function library($name) {} }
final class FixturePeriodGuard { public bool $open = true; public function ensureActiveMonthOpen(...$args): array { return ["ok" => $this->open, "message" => "Period closed"]; } }
require APPPATH . 'libraries/PosOrderStockService.php';
require APPPATH . 'libraries/ComponentStockWriter.php';
require APPPATH . 'libraries/ComponentLotManager.php';
require APPPATH . 'models/Production_model.php';

final class MemoryResult {
    private array $rows;
    public function __construct(array $rows) { $this->rows = array_values($rows); }
    public function row_array() { return $this->rows[0] ?? null; }
    public function result_array(): array { return $this->rows; }
}

final class MemoryDb {
    public array $tables;
    public int $writes = 0;
    private int $lastId = 0;
    private array $snapshots = [];
    private string $table = '';
    private string $selection = '*';
    private array $filters = [];
    private array $sort = [];
    private ?int $maxRows = null;
    private bool $originJoin = false;
    public function __construct(array $tables) { $this->tables = $tables; }
    private function tableAllowed(string $table): void {
        if (!array_key_exists($table, $this->tables)) throw new RuntimeException('Unexpected table: ' . $table);
    }
    private function reset(): void {
        $this->table = ''; $this->selection = '*'; $this->filters = []; $this->sort = [];
        $this->maxRows = null; $this->originJoin = false;
    }
    private function column(string $column): string { return preg_replace('/^[a-z]+\./', '', $column); }
    public function table_exists(string $table): bool { return array_key_exists($table, $this->tables); }
    public function field_exists(string $field, string $table): bool {
        $this->tableAllowed($table);
        if ($field !== 'reversal_of_movement_id') return array_key_exists($field, reset($this->tables[$table]) ?: []);
        return true;
    }
    public function select($selection, $escape = true): self { $this->selection = $selection; return $this; }
    public function from(string $table): self {
        $this->table = explode(' ', $table)[0]; $this->tableAllowed($this->table); return $this;
    }
    public function where(string $field, $value = null, $escape = true): self {
        if (str_ends_with($field, ' IS NULL')) { $field = substr($field, 0, -8); $value = null; }
        $field = $this->column($field);
        if (preg_match('/^(.+) (>=|<=|<>|>|<)$/', $field, $m)) {
            $field = $m[1]; $operator = $m[2];
            $this->filters[] = static fn($row) => match($operator) {
                '>=' => ($row[$field] ?? null) >= $value, '<=' => ($row[$field] ?? null) <= $value,
                '>' => ($row[$field] ?? null) > $value, '<' => ($row[$field] ?? null) < $value,
                '<>' => ($row[$field] ?? null) != $value,
            };
        } else { $this->filters[] = static fn($row) => ($row[$field] ?? null) == $value; }
        return $this;
    }
    public function like(string $field, string $value, string $side): self {
        if ($side !== 'after') throw new RuntimeException('Unexpected LIKE mode');
        $this->filters[] = static fn($row) => str_starts_with((string)($row[$field] ?? ''), $value);
        return $this;
    }
    public function order_by(string $field, string $direction): self {
        $this->sort[] = [$this->column($field), strtoupper($direction)]; return $this;
    }
    public function limit(int $limit): self { $this->maxRows = $limit; return $this; }
    public function join(string $table, string $condition, string $type): self {
        if ($table !== 'inv_component_movement_log origin') throw new RuntimeException('Unexpected JOIN');
        $this->originJoin = true; return $this;
    }
    private function matches(array $row): bool {
        foreach ($this->filters as $filter) if (!$filter($row)) return false;
        return true;
    }
    public function get(?string $table = null): MemoryResult {
        if ($table !== null) $this->from($table);
        $this->tableAllowed($this->table);
        $rows = array_values(array_filter($this->tables[$this->table], fn($row) => $this->matches($row)));
        foreach (array_reverse($this->sort) as [$field, $direction]) {
            usort($rows, static fn($a, $b) => (($a[$field] ?? null) <=> ($b[$field] ?? null)) * ($direction === 'DESC' ? -1 : 1));
        }
        if (preg_match_all('/SUM\\((\\w+)\\),\\s*0\\) AS (\\w+)/i', $this->selection, $matches, PREG_SET_ORDER)) {
            $sums = []; foreach ($matches as $m) $sums[$m[2]] = array_sum(array_column($rows, $m[1]));
            $rows = [$sums];
        }
        if ($this->originJoin) {
            foreach ($rows as &$row) {
                $row['reversal_origin_type'] = $this->tables['inv_component_movement_log'][$row['reversal_of_movement_id'] ?? 0]['movement_type'] ?? null;
            }
            unset($row);
        }
        if ($this->maxRows !== null) $rows = array_slice($rows, 0, $this->maxRows);
        $this->reset(); return new MemoryResult($rows);
    }
    public function count_all_results(string $table): int { return count($this->get($table)->result_array()); }
    public function query(string $sql, array $params): MemoryResult {
        // Only the two actual monthly-balance reads are supported; never execute SQL.
        $flat = preg_replace('/\s+/', ' ', trim($sql));
        if ((str_contains($flat, 'FROM inv_component_monthly_stock') && str_contains($flat, 'closing_qty AS qty_on_hand'))) {
            [$location, $division, $component, $uom, $month] = $params;
        } elseif (str_starts_with($flat, 'SELECT * FROM inv_component_monthly_stock WHERE month_key = ?')) {
            [$month, $location, $division, $component, $uom] = $params;
        } elseif (str_contains($flat, 'FROM inv_component_lot WHERE id = ?')) {
            return new MemoryResult(isset($this->tables['inv_component_lot'][$params[0]]) ? [$this->tables['inv_component_lot'][$params[0]]] : []);
        } else { throw new RuntimeException('Unexpected SQL: ' . $flat); }
        $rows = array_values(array_filter($this->tables['inv_component_monthly_stock'], static fn($r) =>
            $r['location_type'] === $location && $r['division_id'] === $division &&
            $r['component_id'] === $component && $r['uom_id'] === $uom && $r['month_key'] === $month));
        foreach ($rows as &$row) $row['qty_on_hand'] = $row['closing_qty'];
        unset($row);
        return new MemoryResult($rows);
    }
    public function insert(string $table, array $row): bool {
        $this->tableAllowed($table);
        $id = empty($this->tables[$table]) ? 1 : max(array_keys($this->tables[$table])) + 1;
        $this->tables[$table][$id] = array_merge($row, ['id' => $id]);
        $this->lastId = $id; $this->writes++; $this->reset(); return true;
    }
    public function insert_id(): int { return $this->lastId; }
    public function update(string $table, array $changes): bool {
        $this->tableAllowed($table);
        foreach ($this->tables[$table] as &$row) if ($this->matches($row)) $row = array_merge($row, $changes);
        unset($row); $this->writes++; $this->reset(); return true;
    }
    public function delete(string $table): bool {
        $this->tableAllowed($table);
        foreach ($this->tables[$table] as $id => $row) if ($this->matches($row)) unset($this->tables[$table][$id]);
        $this->writes++; $this->reset(); return true;
    }
    public function trans_begin(): bool { $this->snapshots[] = $this->tables; return true; }
    public function trans_rollback(): bool { $this->tables = array_pop($this->snapshots); return true; }
    public function trans_commit(): bool { array_pop($this->snapshots); return true; }
    public function error(): array { return ["code" => 0]; }
    public function trans_status(): bool { return true; }
    public function __call($name, $args) { throw new RuntimeException('Unexpected DB method: ' . $name); }
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $GLOBALS['checks'] = ($GLOBALS['checks'] ?? 0) + 1;
    echo "PASS: {$message}\n";
}
function same(float $actual, float $expected, string $label): void {
    check(abs($actual - $expected) < 0.000001, $label . ': ' . $actual . ' != ' . $expected);
}
function state(MemoryDb $db, string $stage, float $lotQty, float $lotValue): array {
    $row = array_values($db->tables['inv_component_monthly_stock'])[0];
    return ['stage' => $stage, 'monthly_qty' => $row['closing_qty'], 'lot_qty_oracle' => $lotQty,
        'monthly_value' => $row['total_value'], 'lot_value_oracle' => $lotValue,
        'monthly_out_value' => $row['out_total_value'] ?? 0,
        'value_gap' => round($row['total_value'] - $lotValue, 2)];
}

function instanceWithoutBootstrap(string $class, $db): object {
    $rc = new ReflectionClass($class);
    $obj = $rc->newInstanceWithoutConstructor();
    if ($rc->hasProperty('ci')) {
        $property = $rc->getProperty('ci'); $property->setAccessible(true);
        $property->setValue($obj, (object)['db' => $db]);
    } else {
        $obj->db = $db; $obj->load = new FixtureLoader(); $obj->inventoryperiodguard = new FixturePeriodGuard();
    }
    return $obj;
}
function invoke(object $obj, string $method, ...$args) {
    $rm = new ReflectionMethod($obj, $method); $rm->setAccessible(true); return $rm->invoke($obj, ...$args);
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }
$day = date('Y-m-d'); $month = date('Y-m-01');
$identity = ['location_type' => 'KITCHEN', 'division_id' => 1, 'component_id' => 87, 'uom_id' => 1];
$monthly = $identity + ['id' => 1, 'month_key' => $month, 'opening_qty' => 20.0, 'opening_total_value' => 300.0,
    'closing_qty' => 20.0, 'avg_cost' => 15.0, 'total_value' => 300.0];
$db = new MemoryDb(['inv_component_monthly_stock' => [1 => $monthly], 'inv_component_movement_log' => [], 'inv_stock_value_reconciliation' => []]);
$model = instanceWithoutBootstrap(Production_model::class, $db);
$pos = instanceWithoutBootstrap(PosOrderStockService::class, $db);
$writer = instanceWithoutBootstrap(ComponentStockWriter::class, $db);
$payload = $identity + ['movement_date' => $day, 'movement_type' => 'USAGE', 'qty' => 5.0, 'unit_cost' => 10.0,
    'source_module' => 'POS', 'source_table' => 'pos_stock_commit', 'source_id' => 9001, 'source_line_id' => 9002,
    'notes' => '', 'actor_employee_id' => 0];
$sale = invoke($pos, 'post_component_aggregate_movement', $payload);
check($sale['ok'], 'POS FIFO posting');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 250, '5 units FIFO cost 50, not balance-average cost 75');
check($model->rebuild_component_history_for_identity($identity)['ok'], 'rebuild after sale');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 250, 'rebuild uses recorded FIFO cost');
same($db->tables['inv_component_monthly_stock'][1]['opening_total_value'], 300, 'opening value retained');
$void = invoke($pos, 'rollback_component_usage_movement', 9001, 9002, 2.0, $sale['movement_id'], $identity + ['movement_date' => $day]);
check($void['ok'], 'partial POS refund');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 270, 'partial refund restores original cost');
check(invoke($pos, 'rollback_component_usage_movement', 9001, 9002, 3.0, $sale['movement_id'], $identity + ['movement_date' => $day])['ok'], 'remaining POS refund');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 300, 'full refund restores both quantity and value');
$writes = $db->writes;
check(invoke($pos, 'rollback_component_usage_movement', 9001, 9002, 5.0, $sale['movement_id'], $identity)['ok'] && $db->writes === $writes, 'repeated refund does not write');
check($model->rebuild_component_history_for_identity($identity)['ok'], 'rebuild after refund');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 300, 'refund value survives rebuild, ID unchanged');

foreach (['PRODUCTION_OUT', 'ADJUSTMENT_MINUS', 'WASTE', 'SPOIL'] as $kind) {
    $db->tables['inv_component_monthly_stock'] = [1 => $monthly]; $db->tables['inv_component_movement_log'] = [];
    invoke($writer, 'post_single_movement', array_replace($payload, ['movement_type' => $kind, 'total_cost' => 50.01]));
    same($db->tables['inv_component_monthly_stock'][1]['total_value'], 249.99, $kind . ' subtracts exact allocated cost');
}
$db->tables['inv_component_monthly_stock'] = [1 => $monthly]; $db->tables['inv_component_movement_log'] = [];
invoke($writer, 'post_single_movement', array_replace($payload, ['unit_cost' => 0, 'total_cost' => 0]));
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 300, 'zero-cost lot is not replaced by average cost');

$correction = ['id' => 205, 'stock_domain' => 'COMPONENT', 'period_month' => $month, 'status' => 'POSTED',
    'location_type' => 'KITCHEN', 'division_id' => 1, 'component_id' => 87, 'content_uom_id' => 1,
    'stock_qty_snapshot' => 20, 'stock_value_before' => 300, 'stock_value_after' => 400,
    'posted_at' => $day . ' 01:00:00', 'voided_at' => null];
$db->tables['inv_component_monthly_stock'] = [1 => array_replace($monthly, ['total_value' => 400, 'avg_cost' => 20])];
$db->tables['inv_component_movement_log'] = [];
$db->tables['inv_stock_value_reconciliation'] = [205 => $correction];
$log = $identity + ['id' => 1, 'movement_date' => $day, 'movement_datetime' => $day . ' 02:00:00',
    'created_at' => $day . ' 02:00:00', 'movement_type' => 'USAGE', 'qty_in' => 0, 'qty_out' => 5,
    'unit_cost' => 10, 'total_cost' => 50, 'source_table' => 'pos_stock_commit', 'source_id' => 5];
$db->tables['inv_component_movement_log'] = [1 => $log];
$previous = array_replace($monthly, ['id' => 99, 'month_key' => date('Y-m-01', strtotime($month . ' -1 month')), 'total_value' => 123.45]);
$db->tables['inv_component_monthly_stock'][99] = $previous;
check($model->rebuild_component_history_for_identity($identity)['ok'], 'rebuild recognizes independent value correction');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 350, 'correction 400 minus FIFO 50 survives rebuild');
check($db->tables['inv_component_monthly_stock'][99] === $previous, 'prior month not rewritten');
check($model->rebuild_component_history_for_identity($identity)['ok'], 'second rebuild succeeds');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 350, 'second rebuild is idempotent');
check(isset($db->tables['inv_component_monthly_stock'][1]), 'monthly row ID retained for correction audit');

$reverseLog = array_replace($log, ['id' => 2, 'movement_type' => 'VOID_REVERSE', 'qty_in' => 5, 'qty_out' => 0,
    'created_at' => $day . ' 03:00:00', 'movement_datetime' => $day . ' 00:00:00', 'reversal_of_movement_id' => 1]);
$db->tables['inv_component_movement_log'][2] = $reverseLog;
check($model->rebuild_component_history_for_identity($identity)['ok'], 'backdated reversal ordered by actual posting timestamp');
same($db->tables['inv_component_monthly_stock'][1]['total_value'], 400, 'backdated refund does not discard correction');
$voidCorrection = array_replace($correction, ['status' => 'VOID', 'voided_at' => $day . ' 04:00:00']);
$v = invoke($model, 'component_value_projection', $identity, [$log, $reverseLog], [$voidCorrection], 20.0, 300.0, $day);
check($v['ok'], 'voided correction checkpoint accepted'); same($v['value'], 300, 'void correction restores before value');

$db->tables['inv_stock_value_reconciliation'][205]['stock_qty_snapshot'] = 999;
$before = $db->tables;
check(!$model->rebuild_component_history_for_identity($identity)['ok'], 'inconsistent correction evidence refuses rebuild');
check($db->tables === $before, 'refused rebuild leaves balances and audit intact');
$model->inventoryperiodguard->open = false;
check(!$model->rebuild_component_history_for_identity($identity)['ok'], 'closed period denied');
check($db->tables === $before, 'closed period has no writes');

// Ambiguous same-second quantity-neutral events must not silently choose a value.
$tie = array_replace($log, ['created_at' => $correction['posted_at']]);
$tieReturn = array_replace($reverseLog, ['created_at' => $correction['posted_at'], 'total_cost' => 60]);
$v = invoke($model, 'component_value_projection', $identity, [$tie, $tieReturn], [$correction], 20.0, 300.0, $day);
check(!$v['ok'], 'ambiguous same-second checkpoint rejected');

// Exercise real lot rollback, including a lot revalued after partial use.
$componentDb = new MemoryDb(['inv_component_monthly_stock' => [1 => $monthly], 'inv_component_movement_log' => []]);
$componentPos = instanceWithoutBootstrap(PosOrderStockService::class, $componentDb);
$lotSource = new class {
    public float $qty = 5;
    public function consumeUsage($payload): array { return ['ok' => true, 'data' => ['issue_id' => 1, 'issued_qty' => $this->qty, 'total_cost' => $this->qty > 0 ? 70 : 0]]; }
};
$deficits = new class {
    public bool $ready = true;
    public function isReady(): bool { return $this->ready; }
    public function record($payload): array { return ['ok' => true, 'id' => 1]; }
};
$ciProperty = new ReflectionProperty($componentPos, 'ci'); $ciProperty->setAccessible(true);
$ciProperty->setValue($componentPos, (object)['db' => $componentDb, 'load' => new FixtureLoader(),
    'componentlotmanager' => $lotSource, 'inventorydeficitservice' => $deficits, 'inventoryperiodguard' => new FixturePeriodGuard()]);
$componentLine = ['id' => 10, 'component_id' => 87, 'required_uom_id' => 1, 'required_qty' => 8, 'unit_cost_live' => 99,
    'operational_division_id' => 1, 'operational_division_code' => 'KITCHEN', 'resolved_source_division_id' => 1,
    'resolved_source_division_code' => 'KITCHEN'];
$componentResult = invoke($componentPos, 'post_component_usage', ['id' => 1, 'created_at' => $day . ' 10:00:00'], $componentLine, []);
check($componentResult['ok'], 'actual POS component caller accepts partial issue plus deficit');
same($componentResult['total_cost_live'], 367, 'HPP = actual FIFO 70 + provisional missing 3 x 99');
same($componentDb->tables['inv_component_monthly_stock'][1]['total_value'], 230, 'stock subtracts only issued FIFO value, not full provisional HPP');
$lotSource->qty = 0;
$writes = $componentDb->writes;
$componentResult = invoke($componentPos, 'post_component_usage', ['id' => 2, 'created_at' => $day . ' 10:00:00'], $componentLine, []);
check($componentResult['ok'] && $componentDb->writes === $writes, 'pure deficit does not fabricate stock movement');
same($componentResult['total_cost_live'], 792, 'pure deficit keeps full provisional HPP');
$deficits->ready = false;
check(!invoke($componentPos, 'post_component_usage', ['id' => 3, 'created_at' => $day . ' 10:00:00'], $componentLine, [])['ok'], 'missing deficit foundation still fails closed');

// Exercise real lot rollback, including a lot revalued after partial use.
$lot = $identity + ['id' => 1, 'lot_no' => 'TEST-LOT', 'receipt_date' => $day, 'unit_cost' => 20,
    'qty_in_total' => 10, 'qty_out_total' => 5, 'qty_balance' => 5, 'status' => 'OPEN'];
$lotDb = new MemoryDb(['inv_component_lot' => [1 => $lot],
    'inv_component_lot_issue_log' => [1 => $identity + ['id' => 1, 'source_table' => 'test', 'source_id' => 1,
        'source_line_id' => 1, 'status' => 'POSTED', 'issue_qty' => 5, 'total_cost' => 50]],
    'inv_component_lot_issue_line' => [1 => ['id' => 1, 'issue_id' => 1, 'lot_id' => 1, 'qty_out' => 5, 'unit_cost' => 10,
        'total_cost' => 50, 'source_balance_after' => 5]]]);
$lotManager = instanceWithoutBootstrap(ComponentLotManager::class, $lotDb);
$ready = new ReflectionProperty($lotManager, 'schemaEnsured'); $ready->setAccessible(true); $ready->setValue($lotManager, true);
$returned = $lotManager->rollbackIssueLotsBySource('test', 1, 1, 'Test partial', 2);
check($returned['ok'], 'lot partial return succeeds');
same($returned['data']['rolled_cost'], 20, 'lot return carries original issue allocation cost');
same(round($lotDb->tables['inv_component_lot'][1]['qty_balance'] * $lotDb->tables['inv_component_lot'][1]['unit_cost'], 2), 120,
    'remaining revalued lot 100 plus returned original cost 20');
$returned = $lotManager->rollbackIssueLotsBySource('test', 1, 1, 'Test rest', 3);
same($returned['data']['rolled_cost'], 30, 'remaining lot return carries residual cost');
same(round($lotDb->tables['inv_component_lot'][1]['qty_balance'] * $lotDb->tables['inv_component_lot'][1]['unit_cost'], 2), 150,
    'full return preserves revaluation rather than repricing existing balance');
same($lotDb->tables['inv_component_lot'][1]['qty_out_total'], 0, 'returned consumption no longer blocks batch void');
$writes = $lotDb->writes;
check($lotManager->rollbackIssueLotsBySource('test', 1, 1)['ok'] && $lotDb->writes === $writes, 'lot rollback retry idempotent');

echo 'Component FIFO valuation regression passed: ' . $GLOBALS['checks'] . " checks; in-memory only.\n";
