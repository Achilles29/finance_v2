<?php
declare(strict_types=1);

// Source contract only: no web session, database, snapshot, FIFO lot, or
// stock balance is read or changed by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$service = (string)file_get_contents($root . '/application/libraries/SensitiveActionStepUp.php');
$warehouseView = (string)file_get_contents($root . '/application/views/purchase/stock_opening_index.php');
$divisionView = (string)file_get_contents($root . '/application/views/purchase/stock_opening_division_index.php');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$block = static function (string $source, string $method): string {
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $name = '';
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; }
            if ($tokens[$j] === '(') break;
        }
        if ($name !== $method) continue;
        $depth = 0; $opened = false; $result = '';
        for ($j = $i; $j < count($tokens); $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $result .= $text;
            if ($text === '{') { $opened = true; $depth++; }
            if ($text === '}' && $opened && --$depth === 0) return $result;
        }
    }
    return '';
};
$ordered = static function (string $source, array $needles): bool {
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle, $at + 1);
        if ($next === false) return false;
        $at = $next;
    }
    return true;
};

$check(strpos($routes, "\$route['inventory/stock/opening/step-up/verify'] = 'inventory/stock_opening_step_up_verify';") !== false, 'fixed local stock-opening step-up route is registered');
$check(strpos($service, "'STOCK_OPENING_POST'") !== false && strpos($service, "'STOCK_OPENING_VOID'") !== false, 'proof service explicitly permits opening post and void actions');

$store = $block($controller, 'stock_opening_store');
$check($ordered($store, ['require_stock_opening_csrf()', "\$this->requestPayload()", 'require_stock_opening_manual_create_permission(', "consume_stock_opening_step_up(\n            'STOCK_OPENING_POST'", "unset(\$payload['step_up_proof'])", 'store_warehouse_opening_and_post(']), 'manual opening validates CSRF, authoritative scope permission, and one-use proof before its atomic writer');
$check(strpos($store, "\$payload['password']") === false, 'manual opening writer never receives a password');

$void = $block($controller, 'stock_opening_void');
$check($ordered($void, ['require_stock_opening_csrf()', "\$this->requestPayload()", 'get_stock_opening_snapshot(', "consume_stock_opening_step_up('STOCK_OPENING_VOID'", "unset(\$payload['step_up_proof'])", 'void_stock_opening_snapshot(']), 'opening void resolves its exact snapshot and consumes one-use proof before rollback');
$check(strpos($void, "\$payload['password']") === false, 'opening void writer never receives a password');

$verify = $block($controller, 'stock_opening_step_up_verify');
$check($ordered($verify, ['require_stock_opening_csrf()', "\$this->requestPayload()", "'STOCK_OPENING_POST'", 'stock_opening_scope(', 'require_stock_opening_manual_create_permission(', 'get_stock_opening_snapshot(', "load->library('SensitiveActionStepUp'", '->issue(', "\$this->jsonOk("]), 'verification checks scoped permission and an exact void snapshot before issuing a proof');
$check(strpos($verify, "\$payload['password']") !== false && strpos($verify, 'store_warehouse_opening_and_post(') === false && strpos($verify, 'void_stock_opening_snapshot(') === false, 'password is accepted only by verifier, never an opening writer');

$scopeTarget = $block($controller, 'stock_opening_step_up_target');
$check(strpos($scopeTarget, "\$scope === 'DIVISION'") !== false && strpos($scopeTarget, "['division_id']") !== false && strpos($scopeTarget, ': 1') !== false, 'direct manual opening proof binds one use to division ID or stable warehouse scope target');
$permission = $block($controller, 'require_stock_opening_manual_create_permission');
$check(strpos($permission, "PAGE_STOCK_DIVISION, 'create'") !== false && strpos($permission, "PAGE_STOCK_WAREHOUSE, 'create'") !== false, 'manual opening creation permission is resolved by requested stock scope');
$consume = $block($controller, 'consume_stock_opening_step_up');
$check($ordered($consume, ["\$payload['step_up_proof']", "\$this->jsonError("]), 'missing or mismatched proof is rejected before every opening writer');

foreach ([$warehouseView, $divisionView] as $view) {
    $check(strpos($view, "input: 'password'") !== false && strpos($view, "autocomplete: 'current-password'") !== false, 'manual opening browser flow uses a masked current-password prompt');
    $check($ordered($view, ["postStockOpeningJson(stockOpeningStepUpUrl", 'password:', 'step_up_proof']), 'browser sends password only to verifier, then forwards a proof to the writer');
    $check(strpos($view, "'X-Stock-Opening-Csrf': stockOpeningCsrfToken") !== false && strpos($view, "credentials: 'same-origin'") !== false, 'browser sends scoped CSRF and same-origin credentials on opening mutations');
}
$check(strpos($controller, 'Pos_mobile') === false, 'opening reauthentication does not alter POS Mobile/APK contracts');

echo 'PASS stock-opening-step-up checks=' . $checks . PHP_EOL;
