<?php

// Focused source/behavior smoke for bearer financial writers; collect all failures.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function pfw_check(bool $condition, string $message): void
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

function pfw_method(string $source, string $name): string
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

function pfw_ordered(string $source, array $needles): bool
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

function pfw_replay_safe(array $event, int $orderId, int $outletId): bool
{
    $request = json_decode((string)($event['request_json'] ?? ''), true);
    $response = json_decode((string)($event['response_json'] ?? ''), true);
    $request = is_array($request) ? $request : [];
    $response = is_array($response) ? $response : [];
    $orderProof = array_filter([
        (int)($event['server_order_id'] ?? 0),
        (int)($request['order_id'] ?? 0),
        (int)($response['order_id'] ?? 0),
        (int)($response['server_order_id'] ?? 0),
    ], static function (int $id): bool {
        return $id > 0;
    });
    $outletProof = array_filter([
        (int)($event['outlet_id'] ?? 0),
        (int)($request['outlet_id'] ?? 0),
        (int)($request['default_outlet_id'] ?? 0),
        (int)($response['outlet_id'] ?? 0),
    ], static function (int $id): bool {
        return $id > 0;
    });
    $eventType = strtoupper(trim((string)($event['event_type'] ?? '')));
    return $orderId > 0
        && $outletId > 0
        && ($eventType === '' || $eventType === 'PAYMENT')
        && $orderProof !== []
        && count(array_filter($orderProof, static function (int $id) use ($orderId): bool {
            return $id !== $orderId;
        })) === 0
        && count(array_filter($outletProof, static function (int $id) use ($outletId): bool {
            return $id !== $outletId;
        })) === 0;
}

function pfw_dispatch(string $endpoint, bool $bearer, array $binding, ?array $order, ?array $event = null): array
{
    $trace = [
        'status' => 200, 'find_order' => 0, 'writer' => 0, 'monitor' => 0,
        'sync_read' => 0, 'sync_insert' => 0, 'sync_update' => 0, 'body' => [],
    ];
    if ($bearer && ((int)($binding['terminal_id'] ?? 0) <= 0 || (int)($binding['outlet_id'] ?? 0) <= 0)) {
        $trace['status'] = 401;
        return $trace;
    }

    $orderId = (int)($order['header']['id'] ?? 2100);
    $boundOutlet = (int)($binding['outlet_id'] ?? 0);
    if ($bearer) {
        $trace['find_order']++;
        $orderOutlet = (int)($order['header']['outlet_id'] ?? 0);
        if ($order === null || $orderOutlet <= 0 || $orderOutlet !== $boundOutlet) {
            $trace['status'] = 404;
            $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
            return $trace;
        }
    }

    if ($endpoint === 'payment_save') {
        $trace['sync_read']++;
        if ($event !== null) {
            if ($bearer && !pfw_replay_safe($event, $orderId, $boundOutlet)) {
                $trace['status'] = 404;
                $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
                return $trace;
            }
            $response = json_decode((string)($event['response_json'] ?? ''), true);
            $trace['body'] = is_array($response) ? $response : [];
            $trace['body']['duplicate'] = true;
            return $trace;
        }
        $trace['sync_insert']++;
    }

    $trace['writer']++;
    if ($endpoint !== 'payment_save') {
        $trace['monitor']++;
    } else {
        $trace['sync_update']++;
    }
    $trace['body'] = ['ok' => true];
    return $trace;
}

$context = pfw_method($source, 'mobile_financial_order_context');
$replay = pfw_method($source, 'mobile_payment_replay_context');
pfw_check(
    pfw_ordered($context, [
        'is_array($this->mobileUser)',
        "mobileUser['outlet_id']",
        '$this->Pos_model->find_order_draft($orderId)',
        "order['header']['outlet_id']",
        "json_error('Order POS tidak ditemukan.', 404)",
    ]),
    'canonical bearer order helper resolves header outlet and uses generic 404'
);
pfw_check(
    strpos($context, "'is_bearer' => false") < strpos($context, 'find_order_draft('),
    'web-session context preserves legacy path without order lookup'
);
pfw_check(
    pfw_ordered($replay, [
        "event['request_json']", "event['response_json']", "event['server_order_id']",
        "storedRequest['order_id']", "storedResponse['order_id']", 'provedOrderIds === []',
        "json_error('Order POS tidak ditemukan.', 404)",
    ]),
    'payment replay checks event request, response, server order, and safe proof'
);

$writers = [
    'order_void_save' => ['writer' => 'save_order_void(', 'proof' => "consume_mobile_order_reversal_step_up('VOID'"],
    'order_refund_save' => ['writer' => 'save_order_refund(', 'proof' => "consume_mobile_order_reversal_step_up('REFUND'"],
    'payment_save' => ['writer' => 'save_cashier_payment(', 'proof' => ''],
];
foreach ($writers as $endpoint => $writerPolicy) {
    $method = pfw_method($source, $endpoint);
    $needles = [
        '$this->require_mobile_post()', '$this->authorize_mobile(true)',
        '$this->mobile_permission(', '$this->request_payload()',
        '$this->mobile_financial_order_context(',
    ];
    if ($writerPolicy['proof'] !== '') {
        $needles[] = $writerPolicy['proof'];
        $needles[] = "unset(\$payload['step_up_proof'])";
    }
    $needles[] = $writerPolicy['writer'];
    pfw_check(
        pfw_ordered($method, $needles),
        $endpoint . ' orders POST, auth, RBAC, payload, canonical scope, proof when required, then writer'
    );
}
$payment = pfw_method($source, 'payment_save');
pfw_check(
    pfw_ordered($payment, [
        '$this->mobile_financial_order_context(', "table_exists('pos_mobile_sync_event')",
        "from('pos_mobile_sync_event')", '$this->mobile_payment_replay_context(',
        "insert('pos_mobile_sync_event'", '$this->Pos_model->save_cashier_payment(',
    ]),
    'payment binds the canonical order before sync read, replay, insert, and writer'
);

$sameOrder = ['header' => ['id' => 2100, 'outlet_id' => 71, 'terminal_id' => 999]];
foreach (array_keys($writers) as $endpoint) {
    $trace = pfw_dispatch($endpoint, true, ['outlet_id' => 71, 'terminal_id' => 501], $sameOrder);
    pfw_check(
        $trace['status'] === 200 && $trace['find_order'] === 1 && $trace['writer'] === 1
            && $trace['monitor'] === ($endpoint === 'payment_save' ? 0 : 1),
        $endpoint . ' accepts a same-outlet order from another terminal'
    );
}

foreach (['missing order' => null, 'zero outlet' => ['header' => ['id' => 2100, 'outlet_id' => 0]], 'cross outlet' => ['header' => ['id' => 2100, 'outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-WRITER']]] as $label => $order) {
    foreach (array_keys($writers) as $endpoint) {
        $trace = pfw_dispatch($endpoint, true, ['outlet_id' => 71, 'terminal_id' => 501], $order);
        pfw_check(
            $trace['status'] === 404 && $trace['find_order'] === 1 && $trace['writer'] === 0
                && $trace['monitor'] === 0 && $trace['sync_read'] === 0
                && $trace['sync_insert'] === 0 && $trace['sync_update'] === 0
                && $trace['body'] === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                && strpos(json_encode($trace['body']), 'MUST-NOT-LEAK') === false,
            $endpoint . ' rejects ' . $label . ' before writer, monitor, or sync access'
        );
    }
}

$replayCases = [
    'matching replay' => [[
        'event_type' => 'PAYMENT', 'server_order_id' => 2100,
        'request_json' => json_encode(['order_id' => 2100, 'outlet_id' => 71]),
        'response_json' => json_encode(['payment_no' => 'PAY-2100']),
    ], 200, true],
    'mismatched order replay' => [[
        'event_type' => 'PAYMENT', 'server_order_id' => 9999,
        'request_json' => json_encode(['order_id' => 2100, 'outlet_id' => 71]),
        'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-REPLAY']),
    ], 404, false],
    'mismatched outlet replay' => [[
        'event_type' => 'PAYMENT', 'server_order_id' => 2100,
        'request_json' => json_encode(['order_id' => 2100, 'outlet_id' => 72]),
        'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-REPLAY']),
    ], 404, false],
    'legacy replay without proof' => [[
        'event_status' => 'ACCEPTED', 'request_json' => '{}',
        'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-LEGACY']),
    ], 404, false],
];
foreach ($replayCases as $label => $case) {
    [$event, $status, $allowed] = $case;
    $trace = pfw_dispatch('payment_save', true, ['outlet_id' => 71, 'terminal_id' => 501], $sameOrder, $event);
    pfw_check(
        $trace['status'] === $status && $trace['find_order'] === 1 && $trace['sync_read'] === 1
            && $trace['writer'] === 0 && $trace['monitor'] === 0
            && $trace['sync_insert'] === 0 && $trace['sync_update'] === 0
            && ($allowed ? !empty($trace['body']['duplicate']) : strpos(json_encode($trace['body']), 'MUST-NOT-LEAK') === false),
        'payment ' . $label . ' obeys canonical replay proof without leakage or mutation'
    );
}

foreach (array_keys($writers) as $endpoint) {
    $trace = pfw_dispatch($endpoint, false, [], null);
    pfw_check($trace['status'] === 200 && $trace['find_order'] === 0 && $trace['writer'] === 1, $endpoint . ' preserves web-session legacy fallback');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile financial writer binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile financial writer binding smoke checks passed.' . PHP_EOL;
