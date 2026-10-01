<?php
declare(strict_types=1);

// Source contract only: no database, login, stock, lot, or component ledger is
// read or written by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$view = (string)file_get_contents($root . '/application/views/production/component_batch_index.php');
$dailyView = (string)file_get_contents($root . '/application/views/production/component_daily_index.php');
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

$check(strpos($routes, "\$route['production/component-batches/step-up/verify'] = 'production/component_batch_step_up_verify';") !== false, 'component-batch post step-up route is registered');
$check(strpos($routes, "\$route['production/component-batches/void-step-up/verify'] = 'production/component_batch_void_step_up_verify';") !== false, 'component-batch void step-up route is registered');
$check(strpos($service, "'COMPONENT_BATCH_POST'") !== false && strpos($service, "'COMPONENT_BATCH_VOID'") !== false, 'proof service explicitly allows component-batch post and void actions');
$check(substr_count($controller, "'component_batch_csrf_token' => \$this->component_batch_csrf()") === 2, 'dedicated batch and Daily Component pages each receive the scoped batch CSRF token');
$check(strpos($controller, "'component_adjustment_csrf_token' => \$this->component_adjustment_csrf()") !== false, 'Daily Component receives the adjustment token used by its quick-adjust flow');

$save = $block($controller, 'component_batch_save');
$check($ordered($save, ['require_permission(', 'require_component_batch_csrf()', 'release_session_lock()', '$this->request_payload()', 'save_component_batch(']), 'batch draft save is protected by permission and its scoped CSRF boundary before model mutation');
$post = $block($controller, 'component_batch_post');
$check($ordered($post, ['require_permission(', 'require_component_batch_csrf()', '$this->request_payload()', "consume_component_batch_step_up((int)\$id, \$payload, 'COMPONENT_BATCH_POST')", "unset(\$payload['step_up_proof'])", 'post_batch(']), 'batch posting consumes the one-use proof before the component stock writer');
$check(strpos($post, "\$payload['password']") === false, 'component batch writer never receives a password payload');
$void = $block($controller, 'component_batch_void');
$check($ordered($void, ['require_permission(', 'require_component_batch_csrf()', '$this->request_payload()', "consume_component_batch_step_up((int)\$id, \$payload, 'COMPONENT_BATCH_VOID')", "unset(\$payload['step_up_proof'])", 'void_component_batch(']), 'batch void consumes the matching proof before reversing its movements');
$delete = $block($controller, 'component_batch_delete');
$check($ordered($delete, ['require_permission(', 'require_component_batch_csrf()', 'delete_draft_doc(']), 'batch draft deletion is protected by the scoped CSRF boundary');
$verify = $block($controller, 'component_batch_step_up_verify');
$check($ordered($verify, ['require_permission(', 'require_component_batch_csrf()', '$this->request_payload()', '->issue(', "'COMPONENT_BATCH_POST'", '$this->json_ok(']), 'batch post verification applies RBAC and CSRF before issuing a proof');
$voidVerify = $block($controller, 'component_batch_void_step_up_verify');
$check($ordered($voidVerify, ['require_permission(', 'require_component_batch_csrf()', '$this->request_payload()', '->issue(', "'COMPONENT_BATCH_VOID'", '$this->json_ok(']), 'batch void verification applies delete permission and CSRF before issuing a proof');
$csrf = $block($controller, 'require_component_batch_csrf');
$check(strpos($csrf, 'COMPONENT_BATCH_CSRF_CI_HEADER') !== false && strpos($csrf, 'get_request_header(') !== false && strpos($csrf, 'hash_equals(') !== false, 'component batch CSRF is header-only and session-bound');

$check(strpos($view, 'type="password" class="form-control" id="component_batch_step_up_password"') !== false && strpos($view, 'type="password" class="form-control" id="component_batch_void_step_up_password"') !== false, 'dedicated batch page uses masked fields for post and void verification');
$check($ordered($view, ["const password = String(batchStepUpPassword?.value || '');", "batchStepUpPassword.value = '';", 'postJson(componentBatchStepUpUrl', 'step_up_proof', 'postVerifiedBatch(']), 'dedicated batch UI clears its password then forwards only the post proof');
$check($ordered($view, ["const password = String(batchVoidStepUpPassword?.value || '');", "batchVoidStepUpPassword.value = '';", 'postJson(componentBatchVoidStepUpUrl', 'step_up_proof', 'postJson(voidBaseUrl']), 'dedicated batch UI clears its password then forwards only the void proof');
$check(strpos($view, "'X-Production-Component-Batch-Csrf': componentBatchCsrfToken") !== false, 'dedicated batch UI sends its scoped CSRF header on save, post, void, and delete');
$check(strpos($dailyView, 'componentDailyAdjustmentStepUpModal') !== false && strpos($dailyView, 'componentDailyBatchStepUpModal') !== false, 'Daily Component has an explicit verification step for both quick adjustment and quick batch');
$check(strpos($dailyView, "'X-Production-Component-Adjustment-Csrf': componentAdjustmentCsrfToken") !== false && strpos($dailyView, "'X-Production-Component-Batch-Csrf': componentBatchCsrfToken") !== false, 'Daily Component sends each mutation endpoint its matching scoped CSRF header');
$check($ordered($dailyView, ["const password = String(batchStepUpPassword?.value || '');", "batchStepUpPassword.value = '';", 'postJson(componentBatchStepUpUrl', 'step_up_proof', 'postJson(postBaseUrl']), 'Daily Component clears the batch password before posting its one-use proof');
$check(strpos($dailyView, 'Pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'component batch protection does not alter POS Mobile/APK contracts');

echo 'PASS component-batch-step-up checks=' . $checks . PHP_EOL;
