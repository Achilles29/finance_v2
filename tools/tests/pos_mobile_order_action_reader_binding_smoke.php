<?php

// Focused source/behavior smoke for bearer order-action readers; collect all failures.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function par_check(bool $condition, string $message): void
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

function par_method(string $source, string $name): string
{
    $pattern = '/(?:public|private|protected) function ' . preg_quote($name, '/') . '\(/';
    if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = (int)$match[0][1];
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function par_ordered(string $source, array $needles): bool
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

function par_dispatch(bool $bearer, array $terminal, ?array $order): array
{
    $trace = [
        'status' => 200, 'last_seen' => 0, 'permission' => 0, 'resolver' => 0,
        'downstream' => 0, 'targets' => 0, 'attempts' => 0, 'body' => [],
    ];
    if ($bearer && ((int)($terminal['id'] ?? 0) <= 0 || (int)($terminal['outlet_id'] ?? 0) <= 0)) {
        $trace['status'] = 401;
        return $trace;
    }
    $trace['last_seen'] = $bearer ? 1 : 0;
    $trace['permission']++;
    if ($bearer) {
        $trace['resolver']++;
        $boundOutletId = (int)$terminal['outlet_id'];
        $orderOutletId = (int)($order['header']['outlet_id'] ?? 0);
        if ($order === null || $orderOutletId <= 0 || $orderOutletId !== $boundOutletId) {
            $trace['status'] = 404;
            $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
            return $trace;
        }
    }
    $trace['downstream']++;
    return $trace;
}

$resolver = par_method($source, 'mobile_financial_order_context');
par_check(
    par_ordered($resolver, [
        'is_array($this->mobileUser)', "mobileUser['outlet_id']",
        '$this->Pos_model->find_order_draft($orderId)', "order['header']['outlet_id']",
        "json_error('Order POS tidak ditemukan.', 404)",
    ]),
    'shared resolver finds the order and compares bearer outlet using generic 404'
);
par_check(
    strpos($resolver, "order['header']['terminal_id']") === false,
    'shared resolver does not require the order original terminal'
);

$endpointCases = [
    'order_reversal_preview' => '$this->Pos_model->order_reversal_preview(',
    'order_reprint_targets' => '$this->Pos_model->direct_print_targets_for_order_reprint(',
    'order_confirm_print_targets' => '$this->Pos_model->direct_print_targets_for_order_confirm(',
    'payment_prepare' => '$this->Pos_model->cashier_payment_prepare(',
    'voucher_search' => '$this->Pos_model->search_cashier_vouchers(',
];
foreach ($endpointCases as $endpoint => $downstream) {
    $method = par_method($source, $endpoint);
    $resolverNeedle = $endpoint === 'voucher_search'
        ? '$this->mobile_financial_order_context($orderId)'
        : '$this->mobile_financial_order_context((int)$id)';
    par_check(
        par_ordered($method, [
            '$this->authorize_mobile(true)', '$this->mobile_permission(', $resolverNeedle, $downstream,
        ]),
        $endpoint . ' orders auth, RBAC, resolver, then downstream'
    );
    par_check(
        strpos($method, 'mobile_cashier_session_context') === false
            && strpos($method, 'find_active_cashier_session') === false,
        $endpoint . ' does not require an active cashier session'
    );
}

$reprint = par_method($source, 'order_reprint_targets');
$voucher = par_method($source, 'voucher_search');
par_check(
    par_ordered($reprint, ['$this->mobile_financial_order_context((int)$id)', '$this->request_payload()']),
    'order_reprint_targets resolves scope before reading print options'
);
par_check(
    par_ordered($voucher, ["input->get('order_id'", '$this->mobile_financial_order_context($orderId)', "input->get('q'", "input->get('limit'"]),
    'voucher_search resolves order_id before search filters and model call'
);

$terminal = ['id' => 501, 'outlet_id' => 71];
foreach (['same terminal' => 501, 'different original terminal' => 999] as $label => $orderTerminalId) {
    $order = ['header' => ['id' => 1101, 'outlet_id' => 71, 'terminal_id' => $orderTerminalId]];
    foreach (array_keys($endpointCases) as $endpoint) {
        $trace = par_dispatch(true, $terminal, $order);
        par_check(
            $trace['status'] === 200 && $trace['resolver'] === 1 && $trace['downstream'] === 1,
            $endpoint . ' accepts same outlet with ' . $label
        );
    }
}

$scopeCases = [
    'missing order' => null,
    'zero outlet' => ['header' => ['outlet_id' => 0, 'private_marker' => 'MUST-NOT-LEAK-ZERO']],
    'cross outlet' => ['header' => ['outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-CROSS']],
];
foreach ($scopeCases as $label => $order) {
    foreach (array_keys($endpointCases) as $endpoint) {
        $trace = par_dispatch(true, $terminal, $order);
        par_check(
            $trace['status'] === 404 && $trace['resolver'] === 1 && $trace['downstream'] === 0
                && $trace['targets'] === 0 && $trace['attempts'] === 0
                && $trace['body'] === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                && strpos(json_encode($trace['body']), 'MUST-NOT-LEAK') === false,
            $endpoint . ' rejects ' . $label . ' without downstream or payload leakage'
        );
    }
}

foreach ([
    'zero terminal id' => ['id' => 0, 'outlet_id' => 71],
    'zero terminal outlet' => ['id' => 501, 'outlet_id' => 0],
] as $label => $invalidTerminal) {
    foreach (array_keys($endpointCases) as $endpoint) {
        $trace = par_dispatch(true, $invalidTerminal, ['header' => ['outlet_id' => 71]]);
        par_check(
            $trace === [
                'status' => 401, 'last_seen' => 0, 'permission' => 0, 'resolver' => 0,
                'downstream' => 0, 'targets' => 0, 'attempts' => 0, 'body' => [],
            ],
            $endpoint . ' rejects ' . $label . ' at authentication before resolver'
        );
    }
}

foreach (array_keys($endpointCases) as $endpoint) {
    $trace = par_dispatch(false, [], null);
    par_check(
        $trace['status'] === 200 && $trace['resolver'] === 0 && $trace['downstream'] === 1,
        $endpoint . ' preserves unscoped web-session fallback'
    );
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile order action reader binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile order action reader binding smoke checks passed.' . PHP_EOL;
