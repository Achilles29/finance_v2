<?php

declare(strict_types=1);

/**
 * Strictly read-only A2 database invariant probe.
 * Only SELECT statements are accepted by the query wrapper.
 */

$root = dirname(__DIR__, 2);
defined('BASEPATH') || define('BASEPATH', $root . '/system/');
defined('APPPATH') || define('APPPATH', $root . '/application/');
defined('ENVIRONMENT') || define('ENVIRONMENT', getenv('CI_ENV') ?: 'production');

$db = [];
$active_group = 'default';
$query_builder = true;
require APPPATH . 'config/database.php';
$environmentConfig = APPPATH . 'config/' . ENVIRONMENT . '/database.php';
if (is_file($environmentConfig)) {
    require $environmentConfig;
}

$group = isset($db[$active_group]) && is_array($db[$active_group]) ? $db[$active_group] : null;
if ($group === null || strtolower((string)($group['dbdriver'] ?? 'mysqli')) !== 'mysqli') {
    fwrite(STDERR, 'Database probe unavailable: active mysqli configuration was not found.' . PHP_EOL);
    exit(2);
}
if (!extension_loaded('mysqli')) {
    fwrite(STDERR, 'Database probe unavailable: mysqli extension is not loaded.' . PHP_EOL);
    exit(2);
}

mysqli_report(MYSQLI_REPORT_OFF);
$connection = @new mysqli(
    (string)($group['hostname'] ?? ''),
    (string)($group['username'] ?? ''),
    (string)($group['password'] ?? ''),
    (string)($group['database'] ?? ''),
    (int)($group['port'] ?? ini_get('mysqli.default_port')),
    isset($group['socket']) ? (string)$group['socket'] : null
);
if ($connection->connect_errno) {
    fwrite(STDERR, 'Database probe unavailable: connection failed (credentials are intentionally hidden).' . PHP_EOL);
    exit(2);
}
$select = static function (string $sql) use ($connection): array {
    if (!preg_match('/^\s*SELECT\b/i', $sql)) {
        throw new RuntimeException('Read-only probe rejected a non-SELECT statement.');
    }
    $result = $connection->query($sql);
    if ($result === false) {
        throw new RuntimeException('Read-only database query failed.');
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
};
$quote = static function (string $value) use ($connection): string {
    return "'" . $connection->real_escape_string($value) . "'";
};
$tableExists = static function (string $table) use ($select, $quote): bool {
    $rows = $select('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ' . $quote($table));
    return (int)($rows[0]['total'] ?? 0) === 1;
};
$hasColumns = static function (string $table, array $columns) use ($select, $quote): bool {
    if (!$columns) {
        return true;
    }
    $quoted = array_map($quote, $columns);
    $rows = $select(
        'SELECT COUNT(DISTINCT column_name) AS total FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = ' . $quote($table)
        . ' AND column_name IN (' . implode(', ', $quoted) . ')'
    );
    return (int)($rows[0]['total'] ?? 0) === count(array_unique($columns));
};
$count = static function (string $table, string $where = '1=1') use ($select): int {
    $rows = $select('SELECT COUNT(*) AS total FROM `' . $table . '` WHERE ' . $where);
    return (int)($rows[0]['total'] ?? 0);
};
$report = static function (string $label, $value): void {
    echo $label . '=' . (is_bool($value) ? ($value ? 'YES' : 'NO') : (string)$value) . PHP_EOL;
};
$optionalCount = static function (string $label, string $table, array $columns, string $where) use ($tableExists, $hasColumns, $count, $report): void {
    if (!$tableExists($table) || !$hasColumns($table, $columns)) {
        $report($label, 'N/A');
        return;
    }
    $report($label, $count($table, $where));
};

try {
    $databaseTables = $select("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'");
    $report('database.tables', (int)($databaseTables[0]['total'] ?? 0));

    if (!$tableExists('inv_stock_period') || !$hasColumns('inv_stock_period', ['stock_domain', 'period_month', 'status'])) {
        $report('period.rows', 'N/A');
        $report('period.engine', 'N/A');
        $report('period.unique_domain_month', 'N/A');
        $report('period.current_states', 'N/A');
    } else {
        $report('period.rows', $count('inv_stock_period'));
        $engine = $select("SELECT engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'inv_stock_period' LIMIT 1");
        $report('period.engine', strtoupper((string)($engine[0]['engine'] ?? 'UNKNOWN')));
        $unique = $select(
            "SELECT COUNT(*) AS total FROM ("
            . "SELECT index_name FROM information_schema.statistics "
            . "WHERE table_schema = DATABASE() AND table_name = 'inv_stock_period' AND non_unique = 0 "
            . "GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') = 'stock_domain,period_month'"
            . ') period_unique_key'
        );
        $report('period.unique_domain_month', (int)($unique[0]['total'] ?? 0) > 0);
        $states = $select(
            "SELECT stock_domain, status, COUNT(*) AS total FROM inv_stock_period "
            . "WHERE period_month = DATE_FORMAT(CURDATE(), '%Y-%m-01') "
            . 'GROUP BY stock_domain, status ORDER BY FIELD(stock_domain, \'COMPONENT\', \'MATERIAL\'), status'
        );
        $parts = [];
        foreach ($states as $state) {
            $parts[] = (string)$state['stock_domain'] . ':' . (string)$state['status'] . ':' . (int)$state['total'];
        }
        $report('period.current_states', $parts ? implode(',', $parts) : 'NONE');
    }

    $optionalCount('lots.material_open_negative_qty', 'inv_material_fifo_lot', ['status', 'qty_balance'], "UPPER(COALESCE(status, 'OPEN')) = 'OPEN' AND COALESCE(qty_balance, 0) < -0.0001");
    $optionalCount('lots.material_open_negative_unit_cost', 'inv_material_fifo_lot', ['status', 'unit_cost'], "UPPER(COALESCE(status, 'OPEN')) = 'OPEN' AND COALESCE(unit_cost, 0) < -0.000001");
    $optionalCount('lots.component_open_negative_qty', 'inv_component_lot', ['status', 'qty_balance'], "UPPER(COALESCE(status, 'OPEN')) = 'OPEN' AND COALESCE(qty_balance, 0) < -0.0001");
    $optionalCount('lots.component_open_negative_unit_cost', 'inv_component_lot', ['status', 'unit_cost'], "UPPER(COALESCE(status, 'OPEN')) = 'OPEN' AND COALESCE(unit_cost, 0) < -0.000001");
    if ($tableExists('inv_stock_deficit') && $hasColumns('inv_stock_deficit', ['qty_remaining'])) {
        $report('deficits.remaining_above_tolerance', $count('inv_stock_deficit', 'COALESCE(qty_remaining, 0) > 0.0001'));
    } elseif ($tableExists('inv_stock_deficit') && $hasColumns('inv_stock_deficit', ['status'])) {
        $report('deficits.remaining_above_tolerance', $count('inv_stock_deficit', "UPPER(COALESCE(status, '')) = 'OPEN'"));
    } else {
        $report('deficits.remaining_above_tolerance', 'N/A');
    }
    $optionalCount('availability_queue.failed', 'pos_product_availability_queue', ['status'], "UPPER(COALESCE(status, '')) = 'FAILED'");

    if (!$tableExists('pos_order') || !$tableExists('pos_stock_commit')
        || !$hasColumns('pos_order', ['id', 'status', 'stock_commit_status', 'ordered_at'])
        || !$hasColumns('pos_stock_commit', ['order_id', 'commit_status'])) {
        $report('pos.historical_cutoff', 'N/A');
        $report('pos.terminal_order_nonterminal_commit', 'N/A');
        $report('pos.terminal_order_active_snapshot', 'N/A');
    } else {
        $cutoff = $select("SELECT LAST_DAY(CURDATE() - INTERVAL 1 MONTH) AS cutoff_date");
        $report('pos.historical_cutoff', (string)($cutoff[0]['cutoff_date'] ?? 'UNKNOWN'));
        $terminalOrders = $select(
            "SELECT COUNT(DISTINCT order_header.id) AS total FROM pos_order order_header "
            . 'LEFT JOIN pos_stock_commit commit_snapshot ON commit_snapshot.order_id = order_header.id '
            . "WHERE order_header.ordered_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') "
            . "AND UPPER(COALESCE(order_header.status, '')) IN ('VOID', 'REFUND_FULL', 'REFUNDED_FULL') "
            . "AND UPPER(COALESCE(order_header.stock_commit_status, '')) <> 'REVERSED'"
        );
        $report('pos.terminal_order_nonterminal_commit', (int)($terminalOrders[0]['total'] ?? 0));
        $activeSnapshots = $select(
            "SELECT COUNT(*) AS total FROM pos_stock_commit commit_snapshot "
            . 'INNER JOIN pos_order order_header ON order_header.id = commit_snapshot.order_id '
            . "WHERE order_header.ordered_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') "
            . "AND UPPER(COALESCE(order_header.status, '')) IN ('VOID', 'REFUND_FULL', 'REFUNDED_FULL') "
            . "AND UPPER(COALESCE(commit_snapshot.commit_status, '')) "
            . "IN ('DRAFT', 'QUEUED', 'PROCESSING', 'COMMITTED', 'FAILED', 'PARTIAL_REVERSED')"
        );
        $report('pos.terminal_order_active_snapshot', (int)($activeSnapshots[0]['total'] ?? 0));
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Database invariant probe failed without exposing database details: ' . $error->getMessage() . PHP_EOL);
    $connection->close();
    exit(1);
}

$connection->close();
echo 'A2 database invariant probe complete (read-only).' . PHP_EOL;
