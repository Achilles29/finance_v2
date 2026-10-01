<?php

declare(strict_types=1);

/**
 * Source-only period contract for stock snapshots and daily matrices.
 * It intentionally performs no database request or mutation.
 */

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$model = (string)file_get_contents($root . '/application/models/Purchase_model.php');
$warehouse = (string)file_get_contents($root . '/application/views/purchase/stock_warehouse_index.php');
$division = (string)file_get_contents($root . '/application/views/purchase/stock_division_index.php');
$warehouseDaily = (string)file_get_contents($root . '/application/views/purchase/stock_warehouse_daily_index.php');
$divisionDaily = (string)file_get_contents($root . '/application/views/purchase/stock_division_daily_index.php');
$warehouseMatrix = (string)file_get_contents($root . '/application/views/purchase/inventory_warehouse_daily_index.php');
$materialMatrix = (string)file_get_contents($root . '/application/views/purchase/inventory_material_daily_index.php');
$checks = 0;
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$function = static function (string $source, string $name, string $visibility = 'public'): string {
    $start = strpos($source, $visibility . ' function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    for ($index = $brace, $length = strlen($source); $index < $length; $index++) {
        if ($source[$index] === '{') {
            $depth++;
        } elseif ($source[$index] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $index - $start + 1);
            }
        }
    }
    return '';
};

$warehouseIndex = $function($controller, 'stock_warehouse_index');
$divisionIndex = $function($controller, 'stock_division_index');
$monthlyWindow = $function($controller, 'resolveMonthlyDateWindow', 'private');
$dailyWindow = $function($model, 'resolveDailyWindow', 'private');
$warehouseReader = $function($model, 'list_warehouse_stock_monthly', 'private');
$divisionReader = $function($model, 'list_division_stock_monthly', 'private');

$check($controller !== '' && $model !== '' && $warehouse !== '' && $division !== '' && $warehouseDaily !== '' && $divisionDaily !== '' && $warehouseMatrix !== '' && $materialMatrix !== '', 'stock period implementation sources are readable');
$check(
    strpos($warehouseIndex, 'normalizeStockSnapshotMonth') !== false
        && strpos($warehouseIndex, "'month' => \$month") !== false
        && strpos($warehouseIndex, 'list_warehouse_stock($q, 2000, $month)') !== false
        && strpos($warehouseIndex, 'date_from') === false,
    'warehouse live stock is selected by one snapshot month, not an activity range'
);
$check(
    strpos($divisionIndex, 'normalizeStockSnapshotMonth') !== false
        && strpos($divisionIndex, "'month' => \$month") !== false
        && strpos($divisionIndex, 'list_division_stock(') !== false
        && strpos($divisionIndex, 'date_from') === false,
    'material live stock is selected by one snapshot month, not an activity range'
);
$check(
    strpos($warehouseReader, 'normalizeMonth($month)') !== false
        && strpos($warehouseReader, 'activityDateExpr') === false
        && strpos($divisionReader, 'normalizeMonth($month)') !== false
        && strpos($divisionReader, 'activityDateExpr') === false,
    'monthly stock readers no longer hide inactive balances by last-movement date'
);
$check(
    strpos($monthlyWindow, 'min($monthEnd, max($monthStart') !== false
        && strpos($monthlyWindow, "'date_from' => \$from") !== false
        && strpos($monthlyWindow, "'date_to' => \$to") !== false
        && strpos($dailyWindow, 'min($defaultTo, max($defaultFrom, $from))') !== false,
    'daily backend clamps every display range to its selected month'
);
$check(
    substr_count($controller, 'resolveMonthlyDateWindow($month, $dateFrom, $dateTo)') >= 6,
    'all warehouse/material daily views and matrix readers use the monthly window guard'
);
$check(
    strpos($warehouse, 'name="month"') !== false
        && strpos($warehouse, 'Bulan Snapshot') !== false
        && strpos($warehouse, 'name="date_from"') === false
        && strpos($division, 'name="month"') !== false
        && strpos($division, 'Bulan Snapshot') !== false
        && strpos($division, 'name="date_from"') === false,
    'live stock views expose one consistent snapshot-month control'
);
$check(
    strpos($warehouseDaily, 'Mulai Tampilan') !== false
        && strpos($warehouseDaily, 'max="<?php echo html_escape($windowEnd); ?>"') !== false
        && strpos($divisionDaily, 'Mulai Tampilan') !== false
        && strpos($divisionDaily, 'max="<?php echo html_escape($windowEnd); ?>"') !== false,
    'server-rendered daily pages clearly label and bound their in-month display window'
);
$check(
    strpos($warehouseMatrix, 'syncMonthWindowInputs') !== false
        && strpos($warehouseMatrix, 'Mulai Tampilan') !== false
        && strpos($materialMatrix, 'syncMonthWindowInputs') !== false
        && strpos($materialMatrix, 'Mulai Tampilan') !== false,
    'interactive daily matrices synchronize their date inputs to the active month'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' stock period contract check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' stock period contract checks passed.' . PHP_EOL;
