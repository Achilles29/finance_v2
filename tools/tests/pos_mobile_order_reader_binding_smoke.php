<?php

// Focused source/behavior smoke for bearer order readers; collect every failure.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function por_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function por_method(string $source, string $name): string
{
    $start = strpos($source, 'public function ' . $name . '(');
    if ($start === false) {
        return '';
    }
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function por_ordered(string $source, array $needles): bool
{
    $offset = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $offset) {
            return false;
        }
        $offset = $next;
    }
    return true;
}

function por_auth(array $terminal): array
{
    $trace = ['status' => 200, 'last_seen' => 0, 'permission' => 0, 'model' => 0];
    if ((int)($terminal['id'] ?? 0) <= 0 || (int)($terminal['outlet_id'] ?? 0) <= 0) {
        $trace['status'] = 401;
        return $trace;
    }
    $trace['last_seen'] = 1;
    $trace['permission'] = 1;
    return $trace;
}

function por_orders(bool $bearer, array $binding, ?array $session, array $request = []): array
{
    $trace = ['status' => 200, 'find_session' => 0, 'session_userdata' => 0, 'model' => 0, 'outlet_id' => 0];
    if ($bearer) {
        $trace['outlet_id'] = max(0, (int)($binding['outlet_id'] ?? 0));
    } else {
        $trace['find_session']++;
        $trace['session_userdata']++;
        $trace['outlet_id'] = (int)($session['outlet_id'] ?? 0);
    }
    $trace['model']++;
    return $trace;
}

function por_order_load(bool $bearer, int $boundOutletId, ?array $order): array
{
    $trace = ['status' => 200, 'find_order' => 1, 'body' => $order];
    $orderOutletId = max(0, (int)($order['header']['outlet_id'] ?? 0));
    if ($order === null || ($bearer && ($boundOutletId <= 0 || $orderOutletId <= 0 || $orderOutletId !== $boundOutletId))) {
        $trace['status'] = 404;
        $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
    }
    return $trace;
}

$orders = por_method($source, 'orders');
$orderLoad = por_method($source, 'order_load');
$bearerStart = strpos($orders, 'if (is_array($this->mobileUser))');
$bearerEnd = $bearerStart === false ? false : strpos($orders, '} else {', $bearerStart);
$bearerOrders = ($bearerStart === false || $bearerEnd === false)
    ? ''
    : substr($orders, $bearerStart, $bearerEnd - $bearerStart);

por_check(
    por_ordered($orders, [
        '$this->authorize_mobile(true)',
        '$this->mobile_permission(',
        'if (is_array($this->mobileUser))',
        "mobileUser['outlet_id']",
        '$this->Pos_model->order_draft_rows($filters)',
    ]),
    'orders authenticates and authorizes before using bearer outlet and querying rows'
);
por_check(
    strpos($bearerOrders, 'find_active_cashier_session') === false
        && strpos($bearerOrders, 'session->userdata') === false
        && strpos($bearerOrders, "input->get('outlet_id'") === false
        && strpos($orders, 'else {') !== false
        && strpos($orders, 'find_active_cashier_session') !== false,
    'orders bearer avoids request/session fallback while web retains active-session lookup'
);
por_check(
    por_ordered($orderLoad, [
        '$this->authorize_mobile(true)',
        '$this->mobile_permission(',
        '$this->Pos_model->find_order_draft((int)$id)',
        "order['header']['outlet_id']",
        '$orderOutletId !== $boundOutletId',
        '$this->json_ok($order)',
    ]),
    'order_load finds the order then enforces bearer header outlet before response'
);
por_check(
    substr_count($orderLoad, "json_error('Order POS tidak ditemukan.', 404)") === 2
        && strpos($orderLoad, "order['header']['terminal_id']") === false,
    'order_load uses one generic missing/cross-outlet contract and does not restrict terminal'
);

$ordersTrace = por_orders(true, ['outlet_id' => 71, 'terminal_id' => 501], null, ['outlet_id' => 72]);
por_check(
    $ordersTrace['status'] === 200
        && $ordersTrace['outlet_id'] === 71
        && $ordersTrace['find_session'] === 0
        && $ordersTrace['session_userdata'] === 0
        && $ordersTrace['model'] === 1,
    'bearer orders without a cashier session queries only bound outlet 71'
);

$privateMarker = 'MUST-NOT-LEAK-ORDER-READER';
$orderCases = [
    'missing order' => [null, 404],
    'missing outlet' => [['header' => ['id' => 2, 'private_marker' => $privateMarker]], 404],
    'zero outlet' => [['header' => ['id' => 3, 'outlet_id' => 0, 'private_marker' => $privateMarker]], 404],
    'cross outlet' => [['header' => ['id' => 4, 'outlet_id' => 72, 'private_marker' => $privateMarker]], 404],
    'same outlet other terminal' => [['header' => ['id' => 5, 'outlet_id' => 71, 'terminal_id' => 999]], 200],
];
foreach ($orderCases as $label => $case) {
    [$order, $status] = $case;
    $trace = por_order_load(true, 71, $order);
    $encoded = json_encode($trace['body']);
    por_check(
        $trace['status'] === $status
            && $trace['find_order'] === 1
            && ($status === 200
                ? (int)($trace['body']['header']['terminal_id'] ?? 0) === 999
                : ($trace['body'] === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                    && strpos((string)$encoded, $privateMarker) === false)),
        'bearer order_load enforces ' . $label . ' without payload leakage'
    );
}

foreach ([
    'invalid terminal id' => ['id' => 0, 'outlet_id' => 71],
    'invalid terminal outlet' => ['id' => 501, 'outlet_id' => 0],
] as $label => $terminal) {
    $trace = por_auth($terminal);
    por_check(
        $trace === ['status' => 401, 'last_seen' => 0, 'permission' => 0, 'model' => 0],
        $label . ' is rejected at authentication before reader model work'
    );
}

$webOrders = por_orders(false, [], ['outlet_id' => 72]);
$webLoad = por_order_load(false, 0, ['header' => ['id' => 6, 'order_no' => 'WEB-LEGACY']]);
por_check(
    $webOrders['outlet_id'] === 72
        && $webOrders['find_session'] === 1
        && $webLoad['status'] === 200
        && ($webLoad['body']['header']['order_no'] ?? '') === 'WEB-LEGACY',
    'web-session orders and order_load preserve legacy behavior'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile order reader binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile order reader binding smoke checks passed.' . PHP_EOL;
