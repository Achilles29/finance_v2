<?php
declare(strict_types=1);

// Source contract only: no database, login, stock, lot, deficit, or component
// ledger is read or written by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$routes = (string)file_get_contents($root . '/application/config/routes.php');
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$view = (string)file_get_contents($root . '/application/views/production/component_daily_recon_index.php');
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

$check(strpos($routes, "\$route['production/component-daily-recon/step-up/verify'] = 'production/component_daily_recon_step_up_verify';") !== false, 'Daily Recon step-up verification route is registered');
$check(strpos($service, "'COMPONENT_DAILY_RECON_POST'") !== false, 'proof service explicitly permits the Daily Recon component action');
$check(strpos($controller, "'component_daily_recon_csrf_token' => \$this->component_daily_recon_csrf()") !== false, 'Daily Recon page receives its endpoint-scoped CSRF token');

$save = $block($controller, 'component_daily_recon_save');
$check($ordered($save, ['require_permission(', 'require_component_daily_recon_csrf()', 'release_session_lock()', '$this->request_payload()', "'inv_component_stock_opname'"]), 'saving a physical count applies the scoped CSRF boundary before payload and opname mutation');
$adjust = $block($controller, 'component_daily_recon_adjust');
$check($ordered($adjust, ["require_permission('production.component.adjustment.index', 'create')", 'require_component_daily_recon_csrf()', '$this->request_payload()', '$componentIdForProof', 'consume_component_daily_recon_step_up(', 'release_session_lock()', 'save_component_adjustment(', 'post_component_adjustment_document(']), 'generated adjustment consumes a component-bound proof before any adjustment save or stock writer path');
$check(strpos($adjust, "\$payload['password']") === false, 'Daily Recon adjustment writer never receives a password payload');
$confirm = $block($controller, 'component_daily_recon_confirm');
$check($ordered($confirm, ['require_permission(', 'require_component_daily_recon_csrf()', '$this->request_payload()', 'upsert_daily_recon_checkpoint']), 'Daily Recon checkpoint confirmation is protected by its scoped CSRF boundary');
$verify = $block($controller, 'component_daily_recon_step_up_verify');
$check($ordered($verify, ["require_permission('production.component.adjustment.index', 'create')", 'require_component_daily_recon_csrf()', '$this->request_payload()', '->issue(', "'COMPONENT_DAILY_RECON_POST'", '$this->json_ok(']), 'verification checks both Daily Recon and adjustment permission before issuing the component-bound proof');
$consume = $block($controller, 'consume_component_daily_recon_step_up');
$check($ordered($consume, ["'COMPONENT_DAILY_RECON_POST'", "\$payload['step_up_proof']", "['step_up_required' => true]"]), 'missing or invalid proof is rejected before the Daily Recon writer');
$csrf = $block($controller, 'require_component_daily_recon_csrf');
$check(strpos($csrf, 'COMPONENT_DAILY_RECON_CSRF_CI_HEADER') !== false && strpos($csrf, 'get_request_header(') !== false && strpos($csrf, 'hash_equals(') !== false, 'Daily Recon CSRF is header-only and session-bound');

$check(strpos($view, 'type="password" class="form-control" id="component_daily_recon_step_up_password"') !== false && strpos($view, 'autocomplete="current-password"') !== false, 'Daily Recon UI uses a masked current-password field');
$check(strpos($view, "'X-Production-Component-Daily-Recon-Csrf': COMPONENT_DAILY_RECON_CSRF_TOKEN") !== false, 'all Daily Recon mutations can use the dedicated scoped CSRF header');
$check($ordered($view, ["const password = String(dailyReconStepUpPassword?.value || '');", "dailyReconStepUpPassword.value = '';", 'postDailyReconJson(ADJ_STEP_UP_URL', 'step_up_proof', 'postDailyReconJson(ADJ_URL']), 'browser clears the Daily Recon password then forwards only the proof to its writer');
$check(strpos($view, 'headers: dailyReconMutationHeaders()') !== false && substr_count($view, 'headers: dailyReconMutationHeaders()') >= 4, 'physical save, checkpoint confirmation, verification, and adjustment writer use the same CSRF wrapper');
$check(strpos($view, 'fetch(ADJ_URL') === false, 'Daily Recon no longer posts its sensitive adjustment through an unscoped raw fetch');
$check(strpos($view, 'Pos_mobile') === false && strpos($controller, 'Pos_mobile') === false, 'Daily Recon hardening does not alter POS Mobile/APK contracts');

echo 'PASS component-daily-recon-step-up checks=' . $checks . PHP_EOL;
