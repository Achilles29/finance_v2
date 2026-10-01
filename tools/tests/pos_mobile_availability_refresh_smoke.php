<?php

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$model = file_get_contents($root . '/application/models/Pos_model.php');
$routes = file_get_contents($root . '/application/config/routes.php');

function method_source(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

$refresh = method_source($controller, 'catalog_availability_refresh');
$catalog = method_source($model, 'order_product_catalog');
$checks = [
    'route points to dedicated refresh endpoint' => strpos($routes, "'pos-mobile/catalog/availability/refresh'] = 'pos_mobile/catalog_availability_refresh'") !== false,
    'refresh requires POST, auth, permission, and bearer binding' =>
        strpos($refresh, 'require_mobile_post()') !== false
        && strpos($refresh, 'authorize_mobile(true)') !== false
        && strpos($refresh, 'mobile_permission(') !== false
        && strpos($refresh, 'is_array($this->mobileUser)') !== false
        && strpos($refresh, 'mobile_reader_binding_context()') !== false,
    'refresh does not require an open web cashier session' => strpos($refresh, 'mobile_reader_session_context(') === false,
    'refresh is bounded and validates POS-visible products' =>
        strpos($refresh, 'count($requested) > 8') !== false
        && strpos($refresh, "where('p.is_active', 1)") !== false
        && strpos($refresh, "where('p.show_pos', 1)") !== false
        && strpos($refresh, "where('p.show_in_cashier', 1)") !== false,
    'refresh only computes absent or dirty bound-outlet cache rows' =>
        strpos($refresh, "pac.outlet_id = '") !== false
        && strpos($refresh, '!empty($cache[\'is_dirty\'])') !== false
        && strpos($refresh, '->rebuild_product(') !== false,
    'catalog exposes dirty flag for mobile without altering web availability' =>
        strpos($catalog, "'pac.availability_status'") !== false
        && strpos($catalog, "'pac.is_dirty'") !== false,
];

foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}
exit(in_array(false, $checks, true) ? 1 : 0);
