<?php

// Focused source assertions + small table-driven boundary simulation.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Purchase.php');
$view = file_get_contents($root . '/application/views/purchase/stock_division_reconcile_index.php');
$checks = 0;
$failures = [];

function idr_check(bool $ok, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function idr_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function idr_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

function idr_simulate(bool $editor, string $method, string $provided, string $expected): array
{
    $trace = ['status' => 200, 'payload' => 0, 'model' => 0];
    if (!$editor) {
        $trace['status'] = 403;
        return $trace;
    }
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    if (
        preg_match('/\A[0-9a-fA-F]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-fA-F]{64}\z/D', $expected) !== 1
        || !hash_equals($expected, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['payload']++;
    $trace['model']++;
    return $trace;
}

$index = idr_method($controller, 'stock_division_reconcile_index');
$generator = idr_method($controller, 'inventory_division_reconcile_csrf');
$guard = idr_method($controller, 'require_inventory_division_reconcile_csrf');

idr_check(
    strpos($controller, "INVENTORY_DIVISION_RECONCILE_CSRF_SESSION_KEY = 'inventory_division_reconcile_csrf'") !== false
        && strpos($controller, "INVENTORY_DIVISION_RECONCILE_CSRF_HEADER = 'X-Inventory-Reconcile-CSRF'") !== false
        && strpos($controller, "INVENTORY_DIVISION_RECONCILE_CSRF_CI_HEADER = 'X-Inventory-Reconcile-Csrf'") !== false
        && strpos($generator, 'bin2hex(random_bytes(32))') !== false,
    'dedicated inventory reconcile token and browser/CI header spellings exist'
);

idr_check(
    idr_ordered($index, ["can(self::PAGE_STOCK_DIVISION, 'view')", "require_permission(self::PAGE_ORDER, 'view')", 'inventory_division_reconcile_csrf()', 'list_active_operational_divisions(', "render('purchase/stock_division_reconcile_index'", "'inventory_division_reconcile_csrf_token'"])
        && strpos($index, "'pos_transaction_csrf_token'") !== false,
    'reconcile index generates and passes dedicated token after view RBAC'
);

idr_check(
    idr_ordered($guard, ["method(true)) !== 'POST'", "set_header('Allow: POST')", 'jsonError(', 'INVENTORY_DIVISION_RECONCILE_CSRF_CI_HEADER', 'INVENTORY_DIVISION_RECONCILE_CSRF_SESSION_KEY', 'hash_equals('])
        && strpos($guard, "jsonError('Permintaan rekonsiliasi inventori tidak valid.', 405)") !== false
        && strpos($guard, "jsonError('Permintaan rekonsiliasi inventori tidak valid.', 403)") !== false,
    'guard returns generic JSON 405/403 and Allow POST before payload'
);

$endpoints = [
    'repair material id' => ['stock_division_reconcile_repair_material_id', 'repair_monthly_stock_missing_material_id(', true],
    'profile repair' => ['stock_division_reconcile_profile_repair', 'repair_division_material_profile(', false],
    'profile merge' => ['stock_division_reconcile_profile_merge', 'merge_division_material_profiles(', false],
];
foreach ($endpoints as $label => [$method, $modelCall, $checksZeroDivision]) {
    $block = idr_method($controller, $method);
    idr_check(
        idr_ordered($block, ["require_permission(self::PAGE_STOCK_DIVISION, 'edit')", 'require_inventory_division_reconcile_csrf()', 'raw_input_stream', 'post(null, true)', $modelCall])
            && substr_count($block, 'require_inventory_division_reconcile_csrf()') === 1
            && strpos($block, 'require_pos_transaction_csrf') === false
            && (!$checksZeroDivision || strpos($block, '$divisionId > 0 ? $divisionId : null') !== false),
        $label . ' guards before raw/form payload and model while preserving scope'
    );
}

$profileRepair = idr_method($controller, 'stock_division_reconcile_profile_repair');
$profileMerge = idr_method($controller, 'stock_division_reconcile_profile_merge');
idr_check(
    strpos($profileRepair, "!empty(\$result['ok']) ? 200 : 422") !== false
        && strpos($profileMerge, "!empty(\$result['ok']) ? 200 : 422") !== false,
    'profile endpoints preserve success and 422 response contracts'
);

$wrapperAt = strpos($view, 'async function postInventoryReconcileJson(');
$wrapper = $wrapperAt === false ? '' : substr($view, $wrapperAt, 900);
$sharedAt = strpos($view, 'async function postJson(');
$posAt = strpos($view, 'async function postPosTransactionJson(');
$shared = ($sharedAt === false || $posAt === false) ? '' : substr($view, $sharedAt, $posAt - $sharedAt);
idr_check(
    strpos($view, 'inventory_division_reconcile_csrf_token') !== false
        && strpos($wrapper, "'X-Inventory-Reconcile-CSRF':inventoryDivisionReconcileCsrfToken") !== false
        && substr_count($view, 'postInventoryReconcileJson(') === 4
        && substr_count($view, 'X-Inventory-Reconcile-CSRF') === 1
        && strpos($shared, 'X-Inventory-Reconcile-CSRF') === false
        && strpos($view, 'async function postPosTransactionJson(') !== false,
    'dedicated JS wrapper/header is isolated to its three callers'
);

idr_check(
    strpos($view, "postInventoryReconcileJson('<?php echo \$repairMaterialIdUrl; ?>'") !== false
        && strpos($view, 'postInventoryReconcileJson(profileRepairUrl,') !== false
        && strpos($view, 'postInventoryReconcileJson(profileMergeUrl,') !== false,
    'repair-material, profile-repair, and profile-merge use dedicated wrapper'
);

$token = str_repeat('a', 64);
$cases = [
    'view-only' => [false, 'POST', $token, 403],
    'GET' => [true, 'GET', $token, 405],
    'PUT' => [true, 'PUT', $token, 405],
    'PATCH' => [true, 'PATCH', $token, 405],
    'missing token' => [true, 'POST', '', 403],
    'bad token' => [true, 'POST', str_repeat('b', 64), 403],
];
foreach ($endpoints as $endpoint => $_config) {
    foreach ($cases as $case => [$editor, $method, $provided, $status]) {
        $trace = idr_simulate($editor, $method, $provided, $token);
        idr_check($trace === ['status' => $status, 'payload' => 0, 'model' => 0], $endpoint . ' ' . $case . ' has no payload/model work');
    }
}
$valid = idr_simulate(true, 'POST', $token, $token);
idr_check($valid === ['status' => 200, 'payload' => 1, 'model' => 1], 'valid request reaches payload and model once');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' inventory division reconcile smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' inventory division reconcile mutation CSRF smoke checks passed.' . PHP_EOL;
