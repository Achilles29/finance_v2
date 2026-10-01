<?php

declare(strict_types=1);

/**
 * Contract for the APK inboxes. These endpoints may create stock-commit jobs,
 * finalize orders, or reject a reservation; authorization and outlet binding
 * therefore must happen before their writers run.
 */

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/application/controllers/Pos_mobile.php';
$routesPath = $root . '/application/config/routes.php';
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$checks = 0;
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$method = static function (string $name) use ($controller): string {
    $needle = 'function ' . $name . '(';
    $start = strpos($controller, $needle);
    if ($start === false) {
        return '';
    }
    $brace = strpos($controller, '{', $start);
    if ($brace === false) {
        return '';
    }
    $depth = 0;
    $length = strlen($controller);
    for ($index = $brace; $index < $length; $index++) {
        if ($controller[$index] === '{') {
            $depth++;
        } elseif ($controller[$index] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($controller, $start, $index - $start + 1);
            }
        }
    }
    return '';
};

$before = static function (string $source, string $first, string $second): bool {
    $firstAt = strpos($source, $first);
    $secondAt = strpos($source, $second);
    return $firstAt !== false && $secondAt !== false && $firstAt < $secondAt;
};

$check($controller !== '' && $routes !== '', 'POS Mobile incoming controller and routes are readable');

$routeContracts = [
    "pos-mobile/reservations/verify/(:num)'] = 'pos_mobile/reservation_verify/\$1'",
    "pos-mobile/reservations/reject/(:num)'] = 'pos_mobile/reservation_reject/\$1'",
    "pos-mobile/incoming/self-order/verify/(:num)'] = 'pos_mobile/self_order_inbox_verify/\$1'",
    "pos-mobile/incoming/self-order/reject/(:num)'] = 'pos_mobile/self_order_inbox_reject/\$1'",
    "pos-mobile/incoming/online-food/verify/(:num)'] = 'pos_mobile/online_food_inbox_verify/\$1'",
    "pos-mobile/incoming/online-food/reject/(:num)'] = 'pos_mobile/online_food_inbox_reject/\$1'",
];
foreach ($routeContracts as $route) {
    $check(strpos($routes, $route) !== false, 'incoming route remains explicitly mapped: ' . $route);
}

$reservationVerify = $method('reservation_verify');
$reservationReject = $method('reservation_reject');
$incomingVerify = $method('verify_mobile_incoming');
$incomingReject = $method('reject_mobile_incoming');
$incomingList = $method('incoming_order_list');
$incomingDetail = $method('incoming_order_detail');
$reservationScope = $method('mobile_reservation_outlet_allowed');
$incomingScope = $method('mobile_incoming_order_outlet_allowed');

$check(
    $before($reservationVerify, 'require_mobile_post()', 'authorize_mobile(true)')
        && $before($reservationVerify, 'authorize_mobile(true)', "mobile_permission('pos.reservation.index', 'edit')")
        && $before($reservationVerify, "mobile_permission('pos.reservation.index', 'edit')", 'mobile_reservation_outlet_allowed')
        && $before($reservationVerify, 'mobile_reservation_outlet_allowed', 'verify_mobile_reservation'),
    'reservation verify requires POST, token, edit RBAC, and outlet scope before verification work'
);
$check(
    $before($reservationReject, 'require_mobile_post()', 'authorize_mobile(true)')
        && $before($reservationReject, 'authorize_mobile(true)', "mobile_permission('pos.reservation.index', 'edit')")
        && $before($reservationReject, "mobile_permission('pos.reservation.index', 'edit')", 'mobile_reservation_outlet_allowed')
        && $before($reservationReject, 'mobile_reservation_outlet_allowed', 'reject_reservation('),
    'reservation reject requires POST, token, edit RBAC, and outlet scope before its writer'
);
$check(
    $before($incomingVerify, 'require_mobile_post()', 'authorize_mobile(true)')
        && $before($incomingVerify, 'authorize_mobile(true)', "mobile_permission(\$pageCode, 'edit')")
        && $before($incomingVerify, "mobile_permission(\$pageCode, 'edit')", 'mobile_incoming_order_outlet_allowed')
        && $before($incomingVerify, 'mobile_incoming_order_outlet_allowed', 'resolve_order_stock_commit_payload'),
    'incoming verify requires POST, token, edit RBAC, and outlet scope before stock/finalization work'
);
$check(
    $before($incomingReject, 'require_mobile_post()', 'authorize_mobile(true)')
        && $before($incomingReject, 'authorize_mobile(true)', "mobile_permission(\$pageCode, 'edit')")
        && $before($incomingReject, "mobile_permission(\$pageCode, 'edit')", 'mobile_incoming_order_outlet_allowed')
        && $before($incomingReject, 'mobile_incoming_order_outlet_allowed', 'reject_online_food_order')
        && $before($incomingReject, 'mobile_incoming_order_outlet_allowed', 'reject_self_order_order'),
    'incoming reject requires POST, token, edit RBAC, and outlet scope before either writer'
);
$check(
    $before($incomingList, 'authorize_mobile(true)', "mobile_permission(\$pageCode, 'view')")
        && $before($incomingList, "mobile_permission(\$pageCode, 'view')", 'mobile_incoming_scope_ready')
        && $before($incomingList, 'mobile_incoming_scope_ready', 'online_food_order_rows')
        && $before($incomingList, 'mobile_incoming_scope_ready', 'self_order_order_rows'),
    'incoming list binds a valid mobile scope before either channel reader'
);
$check(
    $before($incomingDetail, 'authorize_mobile(true)', "mobile_permission(\$pageCode, 'view')")
        && $before($incomingDetail, "mobile_permission(\$pageCode, 'view')", 'find_online_food_order')
        && $before($incomingDetail, "mobile_permission(\$pageCode, 'view')", 'find_self_order_order')
        && $before($incomingDetail, 'find_online_food_order', 'mobile_document_outlet_allowed')
        && $before($incomingDetail, 'find_self_order_order', 'mobile_document_outlet_allowed')
        && $before($incomingDetail, 'mobile_document_outlet_allowed', 'order_payment_rows'),
    'incoming detail binds token, view RBAC, and canonical document outlet before payment/refund/void data'
);
$check(
    strpos($reservationScope, "json_error('Reservasi tidak ditemukan.', 404)") !== false
        && strpos($reservationScope, 'return false;') !== false
        && $before($reservationScope, 'find_reservation($reservationId)', 'mobile_document_outlet_allowed'),
    'missing mobile reservation fails closed with 404 instead of falling through to a writer'
);
$check(
    strpos($incomingScope, 'find_online_food_order') !== false
        && strpos($incomingScope, 'find_self_order_order') !== false
        && strpos($incomingScope, 'mobile_document_outlet_allowed') !== false,
    'incoming outlet resolver reads canonical channel documents and never trusts an APK outlet payload'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS Mobile incoming scope check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' POS Mobile incoming scope checks passed.' . PHP_EOL;
