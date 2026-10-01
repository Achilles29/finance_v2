<?php
declare(strict_types=1);

// Source contract only: no opening snapshot, lot, FIFO, ledger, upload, or
// database row is read or written by this test.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
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

$check(substr_count($controller, "'stock_opening_csrf_token' => \$this->stock_opening_csrf()") === 2, 'manual warehouse and division opening pages each receive the scoped CSRF token');
$store = $block($controller, 'stock_opening_store');
$check($ordered($store, ['require_permission(self::PAGE_ORDER', 'require_stock_opening_csrf()', "\$this->requestPayload()", 'store_warehouse_opening_and_post(']), 'manual opening validates CSRF before request payload and opening writer');
$void = $block($controller, 'stock_opening_void');
$check($ordered($void, ['require_stock_opening_csrf()', "\$this->requestPayload()", 'void_stock_opening_snapshot(']), 'opening void validates CSRF before scope payload and rollback writer');
$import = $block($controller, 'stock_opening_division_import');
$check($ordered($import, ["require_permission(self::PAGE_STOCK_DIVISION, 'create')", 'require_stock_opening_csrf()', "input->post('division_id'", 'parse_uploaded_file(', 'store_warehouse_opening_and_post(']), 'bulk division import validates CSRF before request fields, upload parsing, and every writer');
$csrf = $block($controller, 'require_stock_opening_csrf');
$check(strpos($csrf, 'STOCK_OPENING_CSRF_CI_HEADER') !== false && strpos($csrf, "input->post('stock_opening_csrf'") !== false && strpos($csrf, 'hash_equals(') !== false, 'opening CSRF accepts only the scoped header or same-form token and compares against the session');

foreach ([$warehouseView, $divisionView] as $view) {
    $check(strpos($view, "'X-Stock-Opening-Csrf': stockOpeningCsrfToken") !== false, 'manual opening browser mutation wrapper sends the scoped CSRF header');
}
$check(strpos($divisionView, 'name="stock_opening_csrf"') !== false, 'division import form sends a scoped hidden CSRF token');
$check(strpos($warehouseView, 'name="stock_opening_csrf"') !== false, 'shared opening import form sends a scoped hidden CSRF token when rendered for division scope');
$check(strpos($controller, 'Pos_mobile') === false, 'opening CSRF boundary does not alter POS Mobile/APK contracts');

echo 'PASS stock-opening-csrf checks=' . $checks . PHP_EOL;
