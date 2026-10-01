<?php

// DB-free aggregate runner for the direct-URL guards covered by Finance A1.
$root = dirname(__DIR__, 2);
$routesPath = $root . '/application/config/routes.php';
$routesSource = (string)file_get_contents($routesPath);

$manifest = [
    ['family' => 'Master form', 'route' => 'master/(:any)/store', 'target' => 'master/store/$1', 'controller' => 'Master.php', 'method' => 'store', 'policy' => 'entity:create + POST/scoped CSRF', 'smoke' => 'master_generic_form_mutation_csrf_smoke.php'],
    ['family' => 'Master inline', 'route' => 'master/(:any)/toggle/(:num)', 'target' => 'master/toggle/$1/$2', 'controller' => 'Master.php', 'method' => 'toggle', 'policy' => 'entity:edit + POST/scoped CSRF', 'smoke' => 'master_generic_inline_mutation_csrf_smoke.php'],
    ['family' => 'Master holiday', 'route' => 'master/att-holiday/generate-year', 'target' => 'master/att_holiday_generate_year', 'controller' => 'Master.php', 'method' => 'att_holiday_generate_year', 'policy' => 'attendance holiday:create + POST/scoped CSRF', 'smoke' => 'master_att_holiday_generate_csrf_smoke.php'],
    ['family' => 'Relation formula compatibility', 'route' => 'master/relation/component-formula/(:num)/store', 'target' => 'master_relation/component_formula_store/$1', 'controller' => 'Master_relation.php', 'method' => 'component_formula_store', 'policy' => 'formula:create + POST/scoped CSRF + canonical redirect/no DML', 'smoke' => 'master_relation_component_formula_canonical_redirect_smoke.php'],
    ['family' => 'Formula restore proof', 'route' => 'production/component-formulas/restore-step-up/verify', 'target' => 'production/component_formula_restore_step_up_verify', 'controller' => 'Production.php', 'method' => 'component_formula_restore_step_up_verify', 'policy' => 'formula:edit + POST/header CSRF + version-bound password proof', 'smoke' => 'component_formula_restore_smoke.php'],
    ['family' => 'Formula restore writer', 'route' => 'production/component-formulas/restore', 'target' => 'production/component_formula_restore', 'controller' => 'Production.php', 'method' => 'component_formula_restore', 'policy' => 'formula:edit + POST/header CSRF + revision + one-use version proof', 'smoke' => 'component_formula_restore_smoke.php'],
    ['family' => 'Relation extra checklist', 'route' => 'master/relation/extra-group/(:num)/save', 'target' => 'master_relation/extra_group_products_save/$1', 'controller' => 'Master_relation.php', 'method' => 'extra_group_products_save', 'policy' => 'extra-group:edit + POST/scoped CSRF', 'smoke' => 'master_relation_extra_group_checklist_mutation_csrf_smoke.php'],
    ['family' => 'Relation extra AJAX', 'route' => 'master/relation/extra-group/(:num)/extras/save', 'target' => 'master_relation/extra_group_items_save_ajax/$1', 'controller' => 'Master_relation.php', 'method' => 'extra_group_items_save_ajax', 'policy' => 'extra-group:edit + POST/header CSRF', 'smoke' => 'master_relation_extra_group_mutation_csrf_smoke.php'],
    ['family' => 'Relation bundle', 'route' => 'master/relation/product-bundle/create/save', 'target' => 'master_relation/product_bundle_store', 'controller' => 'Master_relation.php', 'method' => 'product_bundle_store', 'policy' => 'bundle:create + POST/scoped CSRF', 'smoke' => 'master_relation_product_bundle_mutation_csrf_smoke.php'],
    ['family' => 'Relation product extra', 'route' => 'master/relation/product-extra/(:num)/store', 'target' => 'master_relation/product_extra_store/$1', 'controller' => 'Master_relation.php', 'method' => 'product_extra_store', 'policy' => 'extra:create + POST/scoped CSRF', 'smoke' => 'master_relation_product_extra_mutation_csrf_smoke.php'],
    ['family' => 'Relation recipe', 'route' => 'master/relation/product-recipe/(:num)/store', 'target' => 'master_relation/product_recipe_store/$1', 'controller' => 'Master_relation.php', 'method' => 'product_recipe_store', 'policy' => 'recipe:create + POST/scoped CSRF', 'smoke' => 'master_relation_product_recipe_mutation_csrf_smoke.php'],
    ['family' => 'Sidebar admin', 'route' => 'sidebar/manage/menu/store', 'target' => 'sidebar/menu_store', 'controller' => 'Sidebar.php', 'method' => 'menu_store', 'policy' => 'superadmin + POST/scoped CSRF', 'smoke' => 'sidebar_admin_menu_mutation_csrf_smoke.php'],
    ['family' => 'Sidebar favorite', 'route' => 'sidebar/pin', 'target' => 'sidebar/pin', 'controller' => 'Sidebar.php', 'method' => 'pin', 'policy' => 'owned menu + POST/header CSRF', 'smoke' => 'sidebar_favorite_mutation_csrf_smoke.php'],
    ['family' => 'Sidebar structure', 'route' => 'sidebar/manage/save', 'target' => 'sidebar/save_structure', 'controller' => 'Sidebar.php', 'method' => 'save_structure', 'policy' => 'superadmin + POST/AJAX/header CSRF', 'smoke' => 'sidebar_structure_mutation_csrf_smoke.php'],
    ['family' => 'Purchase maintenance', 'route' => 'purchase/rebuild-impact/run', 'target' => 'purchase/rebuild_impact_run', 'controller' => 'Purchase.php', 'method' => 'rebuild_impact_run', 'policy' => 'maintenance:edit + POST/header CSRF', 'smoke' => 'purchase_maintenance_mutation_csrf_smoke.php'],
    ['family' => 'Purchase opening export', 'route' => 'inventory/stock/opening/division/export-existing', 'target' => 'purchase/stock_opening_division_export_existing', 'controller' => 'Purchase.php', 'method' => 'stock_opening_division_export_existing', 'policy' => 'stock-opening-division:export', 'smoke' => 'purchase_stock_opening_authorization_smoke.php'],
    ['family' => 'Division reconcile', 'route' => 'inventory/stock/division/reconcile/repair-material-id', 'target' => 'inventory_division/reconcile_repair_material_id', 'controller' => 'Inventory_division.php', 'method' => 'reconcile_repair_material_id', 'policy' => 'stock-division:edit + POST/scoped CSRF', 'smoke' => 'inventory_division_reconcile_mutation_csrf_smoke.php'],
    ['family' => 'System tools', 'route' => 'dbtools/settings/save', 'target' => 'system_tools/settings_save', 'controller' => 'System_tools.php', 'method' => 'settings_save', 'policy' => 'system-tools:edit + POST/scoped CSRF', 'smoke' => 'system_tools_mutation_csrf_smoke.php'],
    ['family' => 'POS transaction', 'route' => 'pos/orders/draft/save', 'target' => 'pos/order_draft_save', 'controller' => 'Pos.php', 'method' => 'order_draft_save', 'policy' => 'POS:create + POST/header CSRF', 'smoke' => 'pos_transaction_csrf_smoke.php'],
    ['family' => 'POS runtime sync', 'route' => 'pos/orders/runtime-sync/(:num)', 'target' => 'pos/order_runtime_sync/$1', 'controller' => 'Pos.php', 'method' => 'order_runtime_sync', 'policy' => 'POS:edit + POST/header CSRF', 'smoke' => 'pos_runtime_sync_csrf_smoke.php'],
    ['family' => 'POS availability queue', 'route' => 'pos/availability-queue/process', 'target' => 'pos/availability_queue_process', 'controller' => 'Pos.php', 'method' => 'availability_queue_process', 'policy' => 'availability-queue:edit + POST/scoped CSRF', 'smoke' => 'pos_availability_queue_csrf_smoke.php'],
    ['family' => 'POS Mobile/APK', 'route' => 'pos-mobile/orders/save', 'target' => 'pos_mobile/order_save', 'controller' => 'Pos_mobile.php', 'method' => 'order_save', 'policy' => 'bearer device/outlet + RBAC + POST', 'smoke' => 'pos_mobile_authorization_smoke.php'],
    ['family' => 'POS Mobile reversal proof', 'route' => 'pos-mobile/orders/reversal-step-up/verify', 'target' => 'pos_mobile/order_reversal_step_up_verify', 'controller' => 'Pos_mobile.php', 'method' => 'order_reversal_step_up_verify', 'policy' => 'bearer device/outlet + POS edit + password proof bound to action/order', 'smoke' => 'pos_mobile_reversal_step_up_smoke.php'],
    ['family' => 'POS Mobile reprint proof', 'route' => 'pos-mobile/orders/reprint-step-up/verify', 'target' => 'pos_mobile/order_reprint_step_up_verify', 'controller' => 'Pos_mobile.php', 'method' => 'order_reprint_step_up_verify', 'policy' => 'bearer device/outlet + POS view + password proof bound to ORDER_REPRINT/order', 'smoke' => 'pos_mobile_reversal_step_up_smoke.php'],
    ['family' => 'POS Mobile printer discovery', 'route' => 'pos-mobile/printers', 'target' => 'pos_mobile/printers', 'controller' => 'Pos_mobile.php', 'method' => 'printers', 'policy' => 'bearer device/outlet + scoped view + redaction', 'smoke' => 'pos_mobile_printer_binding_smoke.php'],
    ['family' => 'POS Mobile printer test', 'route' => 'pos-mobile/printers/test/(:num)', 'target' => 'pos_mobile/printer_test/$1', 'controller' => 'Pos_mobile.php', 'method' => 'printer_test', 'policy' => 'bearer device/outlet + edit + POST + scoped attempt', 'smoke' => 'pos_mobile_printer_binding_smoke.php'],
];

$selectedTier = 'all';
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (strpos($argument, '--tier=') === 0) {
        $selectedTier = substr($argument, strlen('--tier='));
        continue;
    }
    fwrite(STDERR, 'Usage: php a1_direct_url_guard_matrix_smoke.php [--tier=required|development|all]' . PHP_EOL);
    exit(2);
}
if (!in_array($selectedTier, ['required', 'development', 'all'], true)) {
    fwrite(STDERR, 'Invalid A1 tier: ' . $selectedTier . PHP_EOL);
    exit(2);
}

foreach ($manifest as &$entry) {
    $entry['tier'] = $entry['smoke'] === 'pos_mobile_authorization_smoke.php'
        ? 'development'
        : 'required';
}
unset($entry);

$selectedManifest = array_values(array_filter($manifest, static function (array $entry) use ($selectedTier): bool {
    return $selectedTier === 'all' || $entry['tier'] === $selectedTier;
}));

$failures = [];
$controllerCache = [];
$smokeFamilies = [];

foreach ($selectedManifest as $entry) {
    $routeNeedle = "\$route['" . $entry['route'] . "']";
    $routePattern = '/^[\\t ]*\\$route\\[\'' . preg_quote($entry['route'], '/') . '\'\\]\\s*=\\s*\''
        . preg_quote($entry['target'], '/') . '\';[\\t ]*$/m';
    if ($routesSource === '' || preg_match($routePattern, $routesSource) !== 1) {
        $failures[] = $entry['family'] . ': route drift ' . $routeNeedle . ' => ' . $entry['target'];
    }

    $controllerPath = $root . '/application/controllers/' . $entry['controller'];
    if (!array_key_exists($controllerPath, $controllerCache)) {
        $controllerCache[$controllerPath] = is_file($controllerPath)
            ? (string)file_get_contents($controllerPath)
            : '';
    }
    $methodPattern = '/^[\\t ]*public\\s+function\\s+' . preg_quote($entry['method'], '/') . '\\s*\\(/m';
    if ($controllerCache[$controllerPath] === '' || preg_match($methodPattern, $controllerCache[$controllerPath]) !== 1) {
        $failures[] = $entry['family'] . ': controller method missing '
            . $entry['controller'] . '::' . $entry['method'];
    }

    $smokeFamilies[$entry['smoke']][] = $entry['family'];
}

echo 'A1 SELECTED tier=' . $selectedTier
    . ' manifest=' . count($selectedManifest)
    . ' tests=' . count($smokeFamilies) . PHP_EOL;
foreach ($smokeFamilies as $smoke => $families) {
    $smokePath = __DIR__ . '/' . $smoke;
    if (!is_file($smokePath)) {
        $failures[] = implode(', ', $families) . ': source smoke missing ' . $smoke;
        continue;
    }

    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($smokePath) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        $tail = array_slice($output, -8);
        $failures[] = implode(', ', $families) . ': ' . $smoke . ' exit ' . $exitCode
            . ($tail === [] ? '' : PHP_EOL . '  ' . implode(PHP_EOL . '  ', $tail));
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    fwrite(STDERR, count($failures) . ' A1 direct-URL guard matrix failure(s).' . PHP_EOL);
    exit(1);
}

echo 'PASS: ' . count($selectedManifest) . ' selected manifest entries keep their route and controller-method contracts.' . PHP_EOL;
echo 'PASS: ' . count($smokeFamilies) . ' unique source smokes exited successfully in isolated processes.' . PHP_EOL;
echo 'A1 PASS tier=' . $selectedTier
    . ' manifest=' . count($selectedManifest)
    . ' tests=' . count($smokeFamilies) . PHP_EOL;
