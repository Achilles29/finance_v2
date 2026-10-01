<?php

declare(strict_types=1);

/**
 * Source-only contract for the shared stock-list UX across warehouse,
 * materials/divisions, and components. It performs no database request.
 */

$root = dirname(__DIR__, 2);
$purchase = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$summaryCards = (string)file_get_contents($root . '/application/views/layout/_stock_summary_cards.php');
$stockGroupTabs = (string)file_get_contents($root . '/application/views/purchase/_stock_group_tabs.php');
$componentOpsTabs = (string)file_get_contents($root . '/application/views/production/_component_ops_tabs.php');
$componentTypeTabs = (string)file_get_contents($root . '/application/views/production/_component_type_tabs.php');
$warehouse = (string)file_get_contents($root . '/application/views/purchase/stock_warehouse_index.php');
$division = (string)file_get_contents($root . '/application/views/purchase/stock_division_index.php');
$component = (string)file_get_contents($root . '/application/views/production/component_stock_index.php');
$warehouseMonthly = (string)file_get_contents($root . '/application/views/purchase/stock_warehouse_daily_index.php');
$divisionMonthly = (string)file_get_contents($root . '/application/views/purchase/stock_division_daily_index.php');
$componentMonthly = (string)file_get_contents($root . '/application/views/production/component_monthly_index.php');
$warehouseDailyMatrix = (string)file_get_contents($root . '/application/views/purchase/inventory_warehouse_daily_index.php');
$divisionDailyMatrix = (string)file_get_contents($root . '/application/views/purchase/inventory_material_daily_index.php');
$componentDailyMatrix = (string)file_get_contents($root . '/application/views/production/component_daily_index.php');
$warehouseMovement = (string)file_get_contents($root . '/application/views/purchase/stock_warehouse_movement_index.php');
$fifoAudit = (string)file_get_contents($root . '/application/views/purchase/fifo_audit_index.php');
$divisionOpname = (string)file_get_contents($root . '/application/views/purchase/stock_division_opname_monthly_index.php');
$lotAudit = (string)file_get_contents($root . '/application/views/purchase/lot_audit_index.php');
$componentMovement = (string)file_get_contents($root . '/application/views/production/component_movement_index.php');
$componentLot = (string)file_get_contents($root . '/application/views/production/component_lot_index.php');
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

$method = static function (string $source, string $name): string {
    $start = strpos($source, 'public function ' . $name . '(');
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

$warehouseMethod = $method($purchase, 'stock_warehouse_index');
$divisionMethod = $method($purchase, 'stock_division_index');

$check($purchase !== '' && $summaryCards !== '' && $stockGroupTabs !== '' && $componentOpsTabs !== '' && $componentTypeTabs !== '' && $warehouse !== '' && $division !== '' && $component !== '' && $warehouseMonthly !== '' && $divisionMonthly !== '' && $componentMonthly !== '' && $warehouseDailyMatrix !== '' && $divisionDailyMatrix !== '' && $componentDailyMatrix !== '' && $warehouseMovement !== '' && $fifoAudit !== '' && $divisionOpname !== '' && $lotAudit !== '' && $componentMovement !== '' && $componentLot !== '', 'stock list implementation sources are readable');
$check(
    strpos($warehouseMethod, 'in_array($limit, [25, 50, 100, 200], true)') !== false
        && strpos($warehouseMethod, "\$page = max(1, (int)\$this->input->get('page', true));") !== false
        && strpos($warehouseMethod, 'normalizeStockSnapshotMonth') !== false
        && strpos($warehouseMethod, 'list_warehouse_stock($q, 2000, $month)') !== false,
    'warehouse reader uses the same safe page-size set and supplies a page-aware grouped source'
);
$check(
    strpos($divisionMethod, 'in_array($limit, [25, 50, 100, 200], true)') !== false
        && strpos($divisionMethod, "\$page = max(1, (int)\$this->input->get('page', true));") !== false
        && strpos($divisionMethod, 'list_division_stock(') !== false,
    'material/division reader uses the same safe page-size set and remains read-only'
);
$check(
    strpos($warehouse, 'Per Halaman') !== false
        && strpos($warehouse, 'array_slice($parentRows, ($currentPage - 1) * $perPage, $perPage)') !== false
        && strpos($warehouse, 'Navigasi halaman stok gudang') !== false
        && strpos($warehouse, 'role="region" aria-label="Daftar stok gudang"') !== false,
    'warehouse list provides server-rendered pages, accessible context, and next/previous navigation'
);
$check(
    strpos($division, 'Per Halaman') !== false
        && strpos($division, 'Navigasi halaman stok bahan baku') !== false
        && strpos($division, 'aria-current="page"') !== false
        && strpos($division, 'aria-disabled="true"') !== false
        && strpos($division, 'role="region" aria-label="Daftar stok bahan baku"') !== false,
    'material/division list exposes the same page-size choice and accessible pagination semantics'
);
$check(
    strpos($component, 'name="per_page"') !== false
        && strpos($component, 'Navigasi halaman stok komponen') !== false
        && strpos($component, "\$stockMeta['total_rows']") !== false
        && strpos($component, 'role="status"') !== false,
    'component list retains the shared page-size, total-result KPI, navigation, and empty-state contract'
);
$check(
    strpos($summaryCards, 'stock-summary-grid') !== false
        && strpos($summaryCards, 'stock-summary-card__label') !== false
        && strpos($summaryCards, '$allowedTones = [\'violet\', \'aqua\', \'blue\', \'amber\', \'teal\', \'danger\']') !== false
        && strpos($summaryCards, '.stock-summary-card.is-violet') !== false
        && strpos($summaryCards, '.stock-summary-card.is-danger') !== false
        && strpos($summaryCards, 'stock-summary-card__icon') !== false
        && strpos($warehouse, "'layout/_stock_summary_cards'") !== false
        && strpos($division, "'layout/_stock_summary_cards'") !== false
        && strpos($component, "'layout/_stock_summary_cards'") !== false,
    'warehouse, material, and component stock pages share one graphical colored summary-card shell with icon support'
);
$check(
    strpos($warehouse, "'tone' => 'violet'") !== false
        && strpos($warehouse, "'tone' => 'teal'") !== false
        && strpos($division, "'tone' => 'amber'") !== false
        && strpos($division, "'tone' => \$summaryAlertCount > 0 ? 'danger' : 'blue'") !== false
        && strpos($component, "'tone' => \$countNegative > 0 ? 'danger' : 'blue'") !== false,
    'card colors follow one fixed semantic order and reserve danger for active stock alerts'
);
$check(
    strpos($stockGroupTabs, 'stock-scope-tabs__list') !== false
        && strpos($stockGroupTabs, 'stock-scope-tab.is-active') !== false
        && strpos($stockGroupTabs, 'aria-current="page"') !== false
        && strpos($componentOpsTabs, 'component-workbench-tabs__links') !== false
        && strpos($componentOpsTabs, 'component-workbench-tab.is-active') !== false
        && strpos($componentOpsTabs, 'aria-current="page"') !== false
        && strpos($componentTypeTabs, 'component-workbench-tab') !== false
        && strpos($componentTypeTabs, 'aria-current="page"') !== false,
    'all warehouse/material and component tab strips use the shared responsive active-tab contract'
);
$check(
    strpos($warehouse, '<!-- Filter -->') < strpos($warehouse, "'layout/_stock_summary_cards'")
        && strpos($division, '<!-- Filter -->') < strpos($division, "'layout/_stock_summary_cards'")
        && strpos($component, '<!-- Filter -->') < strpos($component, "'layout/_stock_summary_cards'")
        && strpos($component, "'layout/_stock_summary_cards'") < strpos($component, '<!-- Table -->'),
    'all three live stock pages show filter results before their summary cards and tables'
);
$check(
    strpos($warehouseMonthly, "'layout/_stock_summary_cards'") !== false
        && strpos($divisionMonthly, "'layout/_stock_summary_cards'") !== false
        && strpos($componentMonthly, "'layout/_stock_summary_cards'") !== false
        && strpos($divisionMonthly, 'sdd-kpi-row') === false
        && strpos($componentMonthly, '<!-- Filter -->') < strpos($componentMonthly, "'layout/_stock_summary_cards'")
        && strpos($componentMonthly, "'layout/_stock_summary_cards'") < strpos($componentMonthly, '<!-- Table -->'),
    'warehouse, material, and component monthly snapshot tabs share the graphical summary-card shell after filters'
);
$check(
    strpos($warehouseDailyMatrix, 'pwd-stat-card is-violet') !== false
        && strpos($warehouseDailyMatrix, 'pwd-stat-card is-teal') !== false
        && strpos($divisionDailyMatrix, 'pmd-kpi-icon') !== false
        && strpos($divisionDailyMatrix, 'pmdStatAlertCard') !== false
        && strpos($divisionDailyMatrix, "classList.toggle('is-danger', alertCount > 0)") !== false
        && strpos($componentDailyMatrix, "'layout/_stock_summary_cards'") !== false
        && strpos($componentDailyMatrix, '<form method="get"') < strpos($componentDailyMatrix, "'layout/_stock_summary_cards'")
        && strpos($componentDailyMatrix, "'layout/_stock_summary_cards'") < strpos($componentDailyMatrix, '<div class="component-daily-matrix-shell"'),
    'all three daily matrices use the shared graphical KPI color semantics without changing their reader or AJAX contract'
);
$check(
    strpos($warehouseMovement, "'layout/_stock_summary_cards'") !== false
        && strpos($fifoAudit, "'layout/_stock_summary_cards'") !== false
        && strpos($divisionOpname, "'layout/_stock_summary_cards'") !== false
        && strpos($lotAudit, "'layout/_stock_summary_cards'") !== false
        && strpos($componentMovement, "'layout/_stock_summary_cards'") !== false
        && strpos($componentLot, "'layout/_stock_summary_cards'") !== false
        && strpos($componentMovement, '<form method="get"') < strpos($componentMovement, "'layout/_stock_summary_cards'")
        && strpos($componentLot, '<!-- Filter -->') < strpos($componentLot, "'layout/_stock_summary_cards'"),
    'movement, FIFO, opname, and lot operational tabs use the shared summary shell after their filters'
);
$check(
    strpos($warehouseMethod, 'save_') === false
        && strpos($warehouseMethod, 'post_') === false
        && strpos($divisionMethod, 'save_') === false
        && strpos($divisionMethod, 'post_') === false,
    'warehouse and material list changes cannot write stock, lot, cost, or transactions'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' stock-list consistency check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' stock-list consistency checks passed.' . PHP_EOL;
