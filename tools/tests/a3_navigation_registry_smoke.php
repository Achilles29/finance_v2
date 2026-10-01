<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Sidebar.php';
$migrationPath = $root . '/sql/2026-09-04a_a3_navigation_registry_canonicalization.sql';
$controller = file_get_contents($controllerPath);
$migration = file_get_contents($migrationPath);

if ($controller === false || $migration === false) {
    fwrite(STDERR, "FAIL: cannot read A3-1 sources\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(
    preg_match(
        '/private function build_sidebar_preview_tree\(string \$type\): array\s*\{\s*return \$this->Menu_model->get_sidebar_tree_raw\(\$type\);\s*\}/s',
        $controller
    ) === 1,
    'sidebar preview must return the raw DB tree directly'
);
$expect(strpos($controller, 'regroup_pos_preview_tree') === false, 'preview POS regroup helper remains');
$expect(strpos($controller, 'regroup_master_preview_tree') === false, 'preview Master regroup helper remains');
$expect(strpos($controller, 'regroup_inventory_preview_tree') === false, 'preview Inventory regroup helper remains');
$expect(strpos($controller, 'regroup_product_preview_tree') === false, 'preview Product regroup helper remains');

$syntheticCodes = [
    'master.group.product', 'master.group.inventory', 'master.group.relation', 'master.group.config',
    'inventory.stock.group.warehouse', 'inventory.stock.group.division',
    'inventory.stock.opname.warehouse.monthly', 'inventory.stock.opname.division.monthly',
    'inventory.stock.opening.division.generated', 'inventory.stock.opening.warehouse.generated',
    'product.monitoring.stock', 'product.monitoring.availability',
    'production.component.group.transaction', 'production.component.daily.recon',
    'production.component.reconcile', 'production.component.lot',
    'production.component.opening.monthly', 'production.component.opname.monthly',
    'pos.cashier', 'pos.order.monitor', 'pos.order.paid.index', 'pos.report.group',
    'pos.report.sales', 'pos.report.cost_control', 'pos.report.sales.detail',
    'pos.report.sales.extra', 'pos.report.payment', 'pos.report.refund', 'pos.report.void',
];
foreach ($syntheticCodes as $code) {
    $expect(strpos($migration, "'" . $code . "'") !== false, 'migration misses synthetic code ' . $code);
}

foreach ([337, 339, 335, 365, 341] as $id) {
    $expect(preg_match('/\bid\s*=\s*' . $id . '\b/', $migration) === 1, 'migration misses icon finding id ' . $id);
}
foreach ([[2, 539], [447, 517], [259, 531], [308, 535], [379, 541], [387, 521], [293, 525], [465, 529], [509, 511]] as $pair) {
    $expect(strpos($migration, $pair[0] . '/' . $pair[1]) !== false, 'migration misses sort collision ' . $pair[0] . '/' . $pair[1]);
}

$favoriteMove = strpos($migration, 'INSERT INTO sys_sidebar_favorite');
$aliasDeactivate = strrpos($migration, "WHERE menu_code = 'finance.sales_margin.pos'");
$normalizedAliasUrl = strpos($migration, "LOWER(TRIM(BOTH '/' FROM TRIM(COALESCE(url, '')))) = 'pos/reports/sales'");
$expect($favoriteMove !== false && $aliasDeactivate !== false && $favoriteMove < $aliasDeactivate, 'favorites must move before duplicate URL alias is disabled');
$expect($normalizedAliasUrl !== false && $aliasDeactivate < $normalizedAliasUrl, 'duplicate URL alias predicate must accept URLs with or without a leading slash');
$expect(substr_count($migration, "current_parent.menu_code IN ('grp.master', 'master.group.") === 4, 'Master reparenting must be limited to direct root/target-group children');
$expect(strpos($migration, "'production.component.group.monitoring'") === false, 'migration must not activate the production group under inactive grp.production');
$expect(strpos($migration, "'inventory.group.warehouse'") === false, 'migration must reuse the canonical warehouse group');
$expect(strpos($migration, "'inventory.group.division'") === false, 'migration must reuse the canonical division group');
$expect(
    strpos($migration, "parent.menu_code = 'pos.online_food'") !== false
        && strpos($migration, 'SET parent.url = NULL, parent.page_id = NULL') !== false,
    'Online Food parent must become a pure group while its action leaves remain canonical'
);
$expect(strpos($migration, "'product.availability'") !== false, 'Product Availability must use its verified page code');
$expect(strpos($migration, "IF(VALUES(menu_code) = 'product.monitoring.availability'") !== false, 'only Product Availability may be forcibly reparented by the leaf upsert');
$expect(strpos($migration, 'ON DUPLICATE KEY UPDATE') !== false, 'migration lacks repeat-safe upserts');
$expect(
    preg_match('/sort_order\s*=\s*VALUES\s*\(\s*sort_order\s*\)/i', $migration) !== 1,
    'rerun must not reset existing sibling order to seed values before normalization'
);
$expect(strpos($migration, 'START TRANSACTION;') !== false && strpos($migration, 'COMMIT;') !== false, 'migration lacks transaction boundary');
$procedureCreate = strpos($migration, 'CREATE PROCEDURE sp_a3_navigation_registry_assert_20260904a');
$preflightCall = strpos($migration, "CALL sp_a3_navigation_registry_assert_20260904a('PRE');");
$transactionStart = strpos($migration, 'START TRANSACTION;');
$postflightCall = strpos($migration, "CALL sp_a3_navigation_registry_assert_20260904a('POST');");
$transactionCommit = strpos($migration, 'COMMIT;');
$procedureDrop = strrpos($migration, 'DROP PROCEDURE IF EXISTS sp_a3_navigation_registry_assert_20260904a;');
$expect($procedureCreate !== false && strpos($migration, 'DELIMITER $$') !== false, 'migration lacks delimiter-aware assertion procedure');
$expect($preflightCall !== false && $transactionStart !== false && $preflightCall < $transactionStart, 'preflight must run before START TRANSACTION');
$expect($postflightCall !== false && $transactionCommit !== false && $postflightCall < $transactionCommit, 'postflight must run before COMMIT');
$expect($procedureDrop !== false && $transactionCommit < $procedureDrop, 'assertion procedure must only be dropped after successful COMMIT');
foreach ([
    'required active navigation parent is missing',
    'required active page registry is missing',
    'active URL without active page',
    'active menu icon is missing',
    'duplicate active menu code',
    'duplicate active menu URL',
    'active sibling sort collision',
    'invalid sidebar favorite',
    'active group has a real URL',
    'canonical menu parent mismatch',
    'finance sales alias remains active',
] as $assertionMessage) {
    $expect(strpos($migration, $assertionMessage) !== false, 'migration misses fail-closed assertion: ' . $assertionMessage);
}
$expect(substr_count($migration, "SIGNAL SQLSTATE '45000'") >= 12, 'migration assertions must fail closed with SIGNAL');
$expect(preg_match('/DELETE\s+(?:FROM\s+)?sys_menu\b/i', $migration) !== 1, 'migration must not delete menu rows');
$expect(preg_match('/DELETE\s+(?:FROM\s+)?auth_role_permission\b/i', $migration) !== 1, 'migration must not delete permissions');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "A3 navigation registry source smoke: PASS\n");
