<?php
declare(strict_types=1);

// Source contract only: no database, login, stock, lot, or ledger row is
// read or written by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$view = (string)file_get_contents($root . '/application/views/purchase/stock_adjustment_index.php');
$service = (string)file_get_contents($root . '/application/libraries/SensitiveActionStepUp.php');
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

$check(strpos($routes, "\$route['inventory/stock/adjustment/step-up/verify'] = 'inventory/stock_adjustment_step_up_verify';") !== false, 'fixed local stock-adjustment step-up route is registered');
$check(strpos($service, "'STOCK_ADJUSTMENT_POST'") !== false && strpos($service, "'STOCK_ADJUSTMENT_VOID'") !== false, 'proof service explicitly permits stock adjustment post and void actions');
foreach (['stock_adjustment_warehouse_index', 'stock_adjustment_division_index', 'inventory_warehouse_daily_index', 'inventory_material_daily_index'] as $method) {
    $check(strpos($block($controller, $method), "'stock_adjustment_csrf_token' => \$this->stock_adjustment_csrf()") !== false, $method . ' receives the scoped CSRF token');
}

$store = $block($controller, 'stock_adjustment_store');
$check(
    $ordered($store, ['require_stock_adjustment_csrf()', '$this->requestPayload()', '$this->require_permission(', 'if ($autoPost)'])
        && strpos($store, 'Posting langsung tidak diizinkan.') !== false
        && strpos($store, 'Posting langsung tidak diizinkan.') < strpos($store, 'save_stock_adjustment('),
    'save draft applies CSRF and rejects the unbound auto-post shortcut before model persistence'
);
$check(strpos($store, 'post_stock_adjustment(') === false, 'stock adjustment store cannot bypass reauthentication by directly posting a draft');

$post = $block($controller, 'stock_adjustment_post');
$check($ordered($post, ['require_stock_adjustment_csrf()', '$this->requestPayload()', 'get_stock_adjustment(', 'require_stock_adjustment_document_permission(', "consume_stock_adjustment_step_up('STOCK_ADJUSTMENT_POST'", "unset(\$payload['step_up_proof'])", 'post_stock_adjustment(']), 'post validates CSRF, authoritative scope permission, and proof before its writer');
$check(strpos($post, "\$payload['password']") === false, 'stock adjustment post never accepts a password at the writer');

$void = $block($controller, 'stock_adjustment_void');
$check($ordered($void, ['require_stock_adjustment_csrf()', '$this->requestPayload()', 'get_stock_adjustment(', 'require_stock_adjustment_document_permission(', "consume_stock_adjustment_step_up('STOCK_ADJUSTMENT_VOID'", "unset(\$payload['step_up_proof'])", 'void_posted_stock_adjustment(']), 'void validates CSRF, authoritative scope permission, and proof before its reversal writer');
$check(strpos($void, "\$payload['password']") === false, 'stock adjustment void never accepts a password at the writer');

$delete = $block($controller, 'stock_adjustment_delete');
$check($ordered($delete, ['require_stock_adjustment_csrf()', 'get_stock_adjustment(', 'require_stock_adjustment_document_permission(', 'delete_draft_stock_adjustment(']), 'draft deletion is also protected by the scoped CSRF boundary');
$verify = $block($controller, 'stock_adjustment_step_up_verify');
$check($ordered($verify, ['require_stock_adjustment_csrf()', '$this->requestPayload()', "'STOCK_ADJUSTMENT_POST'", 'get_stock_adjustment(', 'require_stock_adjustment_document_permission(', "load->library('SensitiveActionStepUp'", '->issue(', '$this->jsonOk(']), 'verification resolves the saved document scope before issuing proof');
$consume = $block($controller, 'consume_stock_adjustment_step_up');
$check($ordered($consume, ["\$payload['step_up_proof']", '$this->jsonError(']), 'missing or invalid proof is rejected before any stock-adjustment writer');
$csrf = $block($controller, 'require_stock_adjustment_csrf');
$check(strpos($csrf, 'STOCK_ADJUSTMENT_CSRF_CI_HEADER') !== false && strpos($csrf, 'get_request_header(') !== false && strpos($csrf, 'hash_equals(') !== false, 'stock adjustment CSRF is header-only and bound to the session token');

$check(strpos($view, 'type="password" class="form-control" id="stock_adjustment_step_up_password"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'stock adjustment UI uses a masked current-password field');
$check($ordered($view, ["const password = String(stockAdjustmentStepUpPasswordEl?.value || '');", "stockAdjustmentStepUpPasswordEl.value = '';", 'postStockAdjustmentJson(stockAdjustmentStepUpUrl', 'step_up_proof', 'postStockAdjustmentJson(action.writerBaseUrl']), 'browser clears password then forwards only one-use proof to the selected writer');
$check(strpos($view, "'X-Stock-Adjustment-Csrf': stockAdjustmentCsrfToken") !== false, 'browser sends the scoped CSRF header on save, delete, verify, post, and void requests');
$check(strpos($view, 'Pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'stock adjustment protection does not alter POS Mobile/APK contracts');

echo 'PASS stock-adjustment-step-up checks=' . $checks . PHP_EOL;
