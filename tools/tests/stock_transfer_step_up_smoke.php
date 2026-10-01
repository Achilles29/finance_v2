<?php
declare(strict_types=1);

// Source contract only: this check never loads a web session, database, FIFO
// lot, stock balance, movement ledger, or transfer document.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$view = (string)file_get_contents($root . '/application/views/purchase/stock_transfer_index.php');
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

$check(strpos($routes, "\$route['inventory/stock/transfer/step-up/verify'] = 'inventory/stock_transfer_step_up_verify';") !== false, 'fixed local stock-transfer step-up route is registered');
$check(strpos($service, "'STOCK_TRANSFER_POST'") !== false && strpos($service, "'STOCK_TRANSFER_VOID'") !== false, 'proof service explicitly permits stock transfer post and void actions');
$check(substr_count($controller, "'stock_transfer_csrf_token' => \$this->stock_transfer_csrf()") === 1, 'stock-transfer page receives one scoped CSRF token');

$store = $block($controller, 'stock_transfer_store');
$check(
    $ordered($store, ["require_permission(self::PAGE_STOCK_TRANSFER_DIVISION, 'create')", 'require_stock_transfer_csrf()', "\$this->requestPayload()", 'if ($autoPost)'])
        && strpos($store, 'Posting langsung tidak diizinkan.') !== false
        && strpos($store, 'Posting langsung tidak diizinkan.') < strpos($store, 'save_stock_transfer('),
    'transfer save applies CSRF and rejects the auto-post shortcut before draft persistence'
);
$check(strpos($store, 'post_stock_transfer(') === false, 'transfer save cannot bypass reauthentication by directly posting a draft');

$post = $block($controller, 'stock_transfer_post');
$check($ordered($post, ["require_permission(self::PAGE_STOCK_TRANSFER_DIVISION, 'edit')", 'require_stock_transfer_csrf()', "\$this->requestPayload()", 'get_stock_transfer(', "consume_stock_transfer_step_up('STOCK_TRANSFER_POST'", "unset(\$payload['step_up_proof'])", 'post_stock_transfer(']), 'post validates permission, CSRF, saved document, and one-use proof before FIFO writer');
$check(strpos($post, "\$payload['password']") === false, 'transfer post never accepts a password at the writer');

$void = $block($controller, 'stock_transfer_void');
$check($ordered($void, ["require_permission(self::PAGE_STOCK_TRANSFER_DIVISION, 'delete')", 'require_stock_transfer_csrf()', "\$this->requestPayload()", 'get_stock_transfer(', "consume_stock_transfer_step_up('STOCK_TRANSFER_VOID'", "unset(\$payload['step_up_proof'])", 'void_posted_stock_transfer(']), 'void validates permission, CSRF, saved document, and one-use proof before reversal writer');
$check(strpos($void, "\$payload['password']") === false, 'transfer void never accepts a password at the writer');

$delete = $block($controller, 'stock_transfer_delete');
$check($ordered($delete, ["require_permission(self::PAGE_STOCK_TRANSFER_DIVISION, 'delete')", 'require_stock_transfer_csrf()', 'get_stock_transfer(', 'delete_draft_stock_transfer(']), 'draft deletion is protected by transfer-specific permission and CSRF');
$verify = $block($controller, 'stock_transfer_step_up_verify');
$check($ordered($verify, ['require_stock_transfer_csrf()', "\$this->requestPayload()", "'STOCK_TRANSFER_POST'", 'get_stock_transfer(', '$status = strtoupper(', "load->library('SensitiveActionStepUp'", '->issue(', "\$this->jsonOk("]), 'verification resolves the transfer document and its current status before issuing a document-bound proof');
$consume = $block($controller, 'consume_stock_transfer_step_up');
$check($ordered($consume, ["\$payload['step_up_proof']", "\$this->jsonError("]), 'missing or invalid proof is rejected before any transfer writer');
$csrf = $block($controller, 'require_stock_transfer_csrf');
$check(strpos($csrf, 'STOCK_TRANSFER_CSRF_CI_HEADER') !== false && strpos($csrf, 'get_request_header(') !== false && strpos($csrf, 'hash_equals(') !== false, 'transfer CSRF is header-only and session-bound');

$check(strpos($view, 'type="password" class="form-control" id="stockTransferStepUpPassword"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'transfer UI uses a masked current-password field');
$check($ordered($view, ["const password = String(stepUpPasswordEl.value || '');", "stepUpPasswordEl.value = '';", 'postStockTransferJson(stockTransferStepUpUrl', 'const writerBaseUrl', 'postStockTransferJson(writerBaseUrl', 'step_up_proof']), 'browser clears password then forwards only one-use proof to the selected writer');
$check(strpos($view, "'X-Stock-Transfer-Csrf': stockTransferCsrfToken") !== false, 'browser sends scoped CSRF on save, delete, verify, post, and void requests');
$check(strpos($view, 'auto_post: false') !== false && strpos($view, 'saveTransfer(true)') !== false, 'new transfer is saved as draft before its password-verification flow');
$check(strpos($view, 'Pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'transfer protection does not alter POS Mobile/APK contracts');

echo 'PASS stock-transfer-step-up checks=' . $checks . PHP_EOL;
