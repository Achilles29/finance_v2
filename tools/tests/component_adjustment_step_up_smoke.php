<?php
declare(strict_types=1);

// Source contract only: no database, login, stock, lot, or component ledger is
// read or written by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$view = (string)file_get_contents($root . '/application/views/production/component_adjustment_index.php');
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

$check(strpos($routes, "\$route['production/component-adjustments/step-up/verify'] = 'production/component_adjustment_step_up_verify';") !== false, 'fixed local component-adjustment step-up route is registered');
$check(strpos($routes, "\$route['production/component-adjustments/void-step-up/verify'] = 'production/component_adjustment_void_step_up_verify';") !== false, 'fixed local component-adjustment void step-up route is registered');
$check(strpos($service, "'COMPONENT_ADJUSTMENT_POST'") !== false && strpos($service, "'COMPONENT_ADJUSTMENT_VOID'") !== false, 'proof service explicitly allows component-adjustment post and void actions');
$check(strpos($controller, "'component_adjustment_csrf_token' => \$this->component_adjustment_csrf()") !== false, 'adjustment page receives an endpoint-scoped CSRF token');

$verify = $block($controller, 'component_adjustment_step_up_verify');
$check($ordered($verify, ['require_permission(', 'require_component_adjustment_csrf()', '$this->request_payload()', "load->library('SensitiveActionStepUp'", '->issue(', "'COMPONENT_ADJUSTMENT_POST'", '$this->json_ok(']), 'verification endpoint applies RBAC and CSRF before issuing the proof');
$post = $block($controller, 'component_adjustment_post');
$check($ordered($post, ['require_permission(', 'require_component_adjustment_csrf()', '$this->request_payload()', 'consume_component_adjustment_step_up(', "unset(\$payload['step_up_proof'])", 'post_component_adjustment_document(']), 'posting consumes proof before the component stock writer path');
$check(strpos($post, "\$payload['password']") === false, 'component stock posting never accepts a password payload');
$consume = $block($controller, 'consume_component_adjustment_step_up');
$check($ordered($consume, ["'COMPONENT_ADJUSTMENT_POST'", "\$payload['step_up_proof']", "['step_up_required' => true]"]), 'missing or invalid proof returns the explicit reauthentication contract');
$voidVerify = $block($controller, 'component_adjustment_void_step_up_verify');
$check($ordered($voidVerify, ['require_permission(', 'require_component_adjustment_csrf()', '$this->request_payload()', "load->library('SensitiveActionStepUp'", '->issue(', "'COMPONENT_ADJUSTMENT_VOID'", '$this->json_ok(']), 'void verification applies delete permission and CSRF before issuing the proof');
$void = $block($controller, 'component_adjustment_void');
$check($ordered($void, ['require_permission(', 'require_component_adjustment_csrf()', '$this->request_payload()', 'consume_component_adjustment_void_step_up(', "unset(\$payload['step_up_proof'])", 'void_component_adjustment(']), 'void consumes proof before reversing component movements');
$check(strpos($void, "\$payload['password']") === false, 'component adjustment void never accepts a password payload');
$voidConsume = $block($controller, 'consume_component_adjustment_void_step_up');
$check($ordered($voidConsume, ["'COMPONENT_ADJUSTMENT_VOID'", "\$payload['step_up_proof']", "['step_up_required' => true]"]), 'void rejects a missing or invalid proof before the model reversal');
$csrf = $block($controller, 'require_component_adjustment_csrf');
$check(strpos($csrf, "COMPONENT_ADJUSTMENT_CSRF_CI_HEADER") !== false && strpos($csrf, 'get_request_header(') !== false && strpos($csrf, 'hash_equals(') !== false, 'component adjustment CSRF is header-only and bound to the session token');

$check(strpos($view, 'type="password" class="form-control" id="component_adjustment_step_up_password"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'component adjustment uses a masked current-password field');
$check($ordered($view, ["const password = String(adjustmentStepUpPassword?.value || '');", "adjustmentStepUpPassword.value = '';", 'postComponentAdjustmentJson(componentAdjustmentStepUpUrl', 'step_up_proof', 'postComponentAdjustmentJson(postBaseUrl']), 'browser clears password then forwards only one-use proof to posting');
$check(strpos($view, 'type="password" class="form-control" id="component_adjustment_void_step_up_password"') !== false && strpos($view, 'id="btn-component-adjustment-void-step-up-post"') !== false, 'component adjustment void uses a separate masked verification modal');
$check($ordered($view, ["const password = String(adjustmentVoidStepUpPassword?.value || '');", "adjustmentVoidStepUpPassword.value = '';", 'postComponentAdjustmentJson(componentAdjustmentVoidStepUpUrl', 'step_up_proof', 'postComponentAdjustmentJson(voidBaseUrl']), 'browser clears password then forwards only one-use proof to void');
$check(strpos($view, "'X-Production-Component-Adjustment-Csrf': componentAdjustmentCsrfToken") !== false, 'browser sends the scoped CSRF header only through the adjustment posting helper');
$check(strpos($view, 'pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'component adjustment reauth does not alter POS Mobile/APK contracts');

echo 'PASS component-adjustment-step-up checks=' . $checks . PHP_EOL;
