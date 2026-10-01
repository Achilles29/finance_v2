<?php
declare(strict_types=1);

// Source contract only: no upload, session, database, stock, lot, or FIFO
// writer is invoked by this check.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
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

$check(strpos($service, "'STOCK_OPENING_IMPORT'") !== false, 'proof service explicitly permits stock-opening import action');
$import = $block($controller, 'stock_opening_division_import');
$check($ordered($import, ["require_permission(self::PAGE_STOCK_DIVISION, 'create')", 'require_stock_opening_csrf()', "input->post('division_id'", 'stock_opening_division_map()', "consume_stock_opening_step_up('STOCK_OPENING_IMPORT'", 'parse_uploaded_file(', 'store_warehouse_opening_and_post(']), 'import validates CSRF, active selected division, and one-use proof before parsing upload or calling a writer');
$check(strpos($import, "'step_up_proof' => \$this->input->post('step_up_proof', true)") !== false, 'import consumes only a proof from its normal multipart form');
$check(strpos($import, "['payload']['division_id']") !== false && strpos($import, 'harus sama dengan divisi import yang dipilih') !== false, 'rows targeting another division are rejected before an opening writer is called');
$check(strpos($import, "\$payload['password']") === false, 'import writer never accepts a password');

$verify = $block($controller, 'stock_opening_step_up_verify');
$check($ordered($verify, ["'STOCK_OPENING_IMPORT'", "\$operation === 'IMPORT'", "PAGE_STOCK_DIVISION, 'create'", 'stock_opening_division_map()', "load->library('SensitiveActionStepUp'", '->issue(']), 'shared verifier resolves import as a division-scoped action and validates an active target before issuing proof');

foreach ([$warehouseView, $divisionView] as $view) {
    $check(strpos($view, "requestOpeningStepUp('IMPORT', { division_id: divisionId })") !== false, 'import browser flow requests an import-specific proof bound to selected division');
    $check(strpos($view, "proofField.name = 'step_up_proof'") !== false && strpos($view, 'form.submit();') !== false, 'multipart form forwards one-use proof without forwarding password');
    $check(strpos($view, 'Satu file hanya untuk divisi yang dipilih.') !== false, 'UI clearly states the one-division import safety boundary');
}
$check(strpos($controller, 'Pos_mobile') === false, 'import hardening does not alter POS Mobile/APK contracts');

echo 'PASS stock-opening-import-step-up checks=' . $checks . PHP_EOL;
