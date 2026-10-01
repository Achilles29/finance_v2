<?php

// Focused source/behavior smoke for bearer draft/upsert/confirm; collect all failures.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function pdu_check(bool $condition, string $message): void
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

function pdu_method(string $source, string $name): string
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

function pdu_ordered(string $source, array $needles): bool
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

function pdu_context(array $payload, array $binding, ?array $order, ?array $session, bool $bearer = true): array
{
    $trace = [
        'ok' => true, 'status' => 200, 'payload' => $payload, 'find_order' => 0,
        'find_session' => 0, 'body' => [], 'context' => ['is_bearer' => $bearer],
    ];
    if (!$bearer) {
        return $trace;
    }
    $orderId = (int)($payload['id'] ?? 0);
    $employeeId = (int)($binding['employee_id'] ?? 0);
    $outletId = (int)($binding['outlet_id'] ?? 0);
    $terminalId = (int)($binding['terminal_id'] ?? 0);
    if ($employeeId <= 0 || $outletId <= 0 || $terminalId <= 0) {
        $trace['ok'] = false;
        $trace['status'] = 403;
        return $trace;
    }
    if ($orderId > 0) {
        $trace['find_order']++;
        $orderOutletId = (int)($order['header']['outlet_id'] ?? 0);
        if ($order === null || $orderOutletId <= 0 || $orderOutletId !== $outletId) {
            $trace['ok'] = false;
            $trace['status'] = 404;
            $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
            return $trace;
        }
    } else {
        $requestedOutletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $requestedTerminalId = max(0, (int)($payload['terminal_id'] ?? 0));
        if (($requestedOutletId > 0 && $requestedOutletId !== $outletId)
            || ($requestedTerminalId > 0 && $requestedTerminalId !== $terminalId)) {
            $trace['ok'] = false;
            $trace['status'] = 403;
            return $trace;
        }
    }
    $trace['payload']['outlet_id'] = $outletId;
    $trace['find_session']++;
    if ($session === null
        || strtoupper((string)($session['session_status'] ?? '')) !== 'OPEN'
        || (int)($session['employee_id'] ?? 0) !== $employeeId
        || (int)($session['outlet_id'] ?? 0) !== $outletId) {
        $trace['ok'] = false;
        $trace['status'] = 403;
        return $trace;
    }
    $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
    $backupMode = $ownerTerminalId > 0 && $ownerTerminalId !== $terminalId;
    $trace['payload']['terminal_id'] = $ownerTerminalId > 0 ? $ownerTerminalId : $terminalId;
    $trace['payload']['origin_terminal_id'] = $terminalId;
    $trace['payload']['mobile_backup_mode'] = $backupMode;
    $trace['context'] += compact(
        'orderId',
        'employeeId',
        'outletId',
        'terminalId',
        'ownerTerminalId',
        'backupMode'
    );
    return $trace;
}

function pdu_replay(array $event, array $context): array
{
    $request = json_decode((string)($event['request_json'] ?? ''), true);
    $response = json_decode((string)($event['response_json'] ?? ''), true);
    $request = is_array($request) ? $request : [];
    $response = is_array($response) ? $response : [];
    $serverOrderId = (int)($event['server_order_id'] ?? 0);
    $eventOrderId = (int)($request['id'] ?? $request['order_id'] ?? 0);
    $responseOrderId = (int)($response['server_id'] ?? $response['order_id'] ?? 0);
    $storedTerminalId = (int)($request['terminal_id'] ?? 0);
    $safeTerminal = $storedTerminalId === (int)$context['terminalId']
        || (!empty($request['mobile_backup_mode'])
            && (int)($request['origin_terminal_id'] ?? 0) === (int)$context['terminalId']);
    $safe = ((string)($event['event_type'] ?? '') === '' || strtoupper((string)$event['event_type']) === 'ORDER_UPSERT')
        && (int)($request['outlet_id'] ?? 0) === (int)$context['outletId']
        && $safeTerminal
        && $serverOrderId > 0
        && ((int)$context['orderId'] <= 0 || $serverOrderId === (int)$context['orderId'])
        && ($eventOrderId <= 0 || $eventOrderId === $serverOrderId)
        && $responseOrderId === $serverOrderId;
    return $safe
        ? ['ok' => true, 'status' => 200, 'body' => $response + ['duplicate' => true]]
        : ['ok' => false, 'status' => 404, 'body' => ['ok' => false, 'message' => 'Order POS tidak ditemukan.']];
}

function pdu_dispatch(string $endpoint, array $payload, array $binding, ?array $order, ?array $session, ?array $event = null, bool $bearer = true): array
{
    $trace = [
        'status' => 200, 'find_order' => 0, 'find_session' => 0, 'writer' => 0,
        'sync_read' => 0, 'sync_insert' => 0, 'sync_update' => 0,
        'confirm' => 0, 'monitor' => 0, 'print' => 0, 'body' => [], 'payload' => $payload,
    ];
    $scope = pdu_context($payload, $binding, $order, $session, $bearer);
    $trace['find_order'] = $scope['find_order'];
    $trace['find_session'] = $scope['find_session'];
    $trace['payload'] = $scope['payload'];
    if (!$scope['ok']) {
        $trace['status'] = $scope['status'];
        $trace['body'] = $scope['body'];
        return $trace;
    }
    if ($endpoint === 'orders_push') {
        $trace['sync_read']++;
        if ($event !== null) {
            if ($bearer) {
                $replay = pdu_replay($event, $scope['context']);
                $trace['status'] = $replay['status'];
                $trace['body'] = $replay['body'];
            } else {
                $stored = json_decode((string)($event['response_json'] ?? ''), true);
                $trace['body'] = (is_array($stored) ? $stored : []) + ['duplicate' => true];
            }
            return $trace;
        }
        $trace['sync_insert']++;
    }
    $trace['writer']++;
    if ($endpoint === 'order_confirm' || ($endpoint === 'orders_push' && !empty($payload['confirm_order']))) {
        $trace['confirm']++;
        $trace['print']++;
    }
    if ($endpoint === 'order_save') {
        $trace['monitor']++;
    }
    if ($endpoint === 'orders_push') {
        $trace['sync_update']++;
    }
    return $trace;
}

$context = pdu_method($source, 'mobile_draft_upsert_context');
 pdu_check(
    pdu_ordered($context, [
        "payload['id']", "mobileUser['employee_id']", "mobileUser['outlet_id']", "mobileUser['terminal_id']",
        'if ($orderId > 0)', '$this->Pos_model->find_order_draft($orderId)', "order['header']['outlet_id']",
        "json_error('Order POS tidak ditemukan.', 404)", '$requestedOutletId', '$requestedTerminalId',
        "payload['outlet_id'] = \$outletId", '$this->mobile_cashier_session_context(true)',
        '$ownerTerminalId', '$backupMode', "payload['terminal_id'] = \$ownerTerminalId",
        "payload['origin_terminal_id'] = \$terminalId", "payload['mobile_backup_mode'] = \$backupMode",
    ]),
    'draft helper validates context then writes owner terminal, device origin, and backup mode'
);
 pdu_check(strpos($context, "order['header']['terminal_id']") === false, 'existing same-outlet order does not depend on its original terminal');

$replay = pdu_method($source, 'mobile_order_push_replay_context');
 pdu_check(
    pdu_ordered($replay, [
        "event['request_json']", "event['response_json']", "event['server_order_id']",
        "eventRequest['outlet_id']", "eventRequest['terminal_id']", "eventRequest['mobile_backup_mode']",
        "eventRequest['origin_terminal_id']", '$serverOrderId <= 0',
        '$responseOrderId !== $serverOrderId', "json_error('Order POS tidak ditemukan.', 404)",
    ]),
    'orders_push replay accepts device-owned or proved backup-origin context plus matching order/server proof'
);

foreach (['order_save' => 'save_order_draft(', 'order_confirm' => 'save_order_draft(', 'orders_push' => "table_exists('pos_mobile_sync_event')"] as $endpoint => $downstream) {
    $method = pdu_method($source, $endpoint);
    $guardOrder = $endpoint === 'order_confirm'
        ? ['$this->require_mobile_post()', '$this->authorize_mobile(true)', '$this->mobile_permission(', '$this->request_payload()']
        : ['$this->require_mobile_post()', '$this->authorize_mobile(true)', '$this->mobile_order_upsert_permission_guard()', '$this->request_payload()', '$this->mobile_permission('];
    pdu_check(
        pdu_ordered($method, array_merge($guardOrder, ['$this->mobile_draft_upsert_context($payload)', $downstream])),
        $endpoint . ' performs method/auth/RBAC and bearer context before downstream work'
    );
}
$confirmSource = pdu_method($source, 'order_confirm');
 pdu_check(
    strpos($confirmSource, '$orderId = (int)($payload[\'id\'] ?? 0);') !== false
        && strpos($confirmSource, '$payload[\'order_id\']') === false,
    'order_confirm declares the canonical payload id marker before save'
);
$pushSource = pdu_method($source, 'orders_push');
 pdu_check(
    pdu_ordered($pushSource, [
        '$this->mobile_draft_upsert_context($payload)', "from('pos_mobile_sync_event')",
        '$this->mobile_order_push_replay_context(', "insert('pos_mobile_sync_event'",
        '$this->Pos_model->save_order_draft(', '$this->confirm_mobile_order(',
    ]),
    'orders_push binds before sync read/replay/insert/save/confirm'
);

$binding = ['employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 501];
$session = ['employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 501, 'session_status' => 'OPEN'];
$backupSession = array_merge($session, ['terminal_id' => 999]);
$sameOrder = ['header' => ['id' => 2100, 'outlet_id' => 71, 'terminal_id' => 999]];
foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    foreach (['empty context' => [], 'zero context' => ['outlet_id' => 0, 'terminal_id' => 0]] as $label => $extra) {
        $payload = $extra + ['id' => 0, 'confirm_order' => $endpoint === 'orders_push'];
        $trace = pdu_dispatch($endpoint, $payload, $binding, null, $session);
        pdu_check(
            $trace['status'] === 200 && $trace['writer'] === 1
                && $trace['payload']['outlet_id'] === 71 && $trace['payload']['terminal_id'] === 501
                && $trace['payload']['origin_terminal_id'] === 501
                && $trace['payload']['mobile_backup_mode'] === false,
            $endpoint . ' normalizes new order with ' . $label
        );
    }
    $existing = pdu_dispatch($endpoint, ['id' => 2100], $binding, $sameOrder, $session);
    pdu_check(
        $existing['status'] === 200 && $existing['find_order'] === 1
            && $existing['find_session'] === 1 && $existing['writer'] === 1
            && $existing['payload']['terminal_id'] === 501
            && $existing['payload']['origin_terminal_id'] === 501
            && $existing['payload']['mobile_backup_mode'] === false,
        $endpoint . ' accepts same-outlet existing order from another original terminal'
    );

    $backup = pdu_dispatch($endpoint, ['id' => 0], $binding, null, $backupSession);
    pdu_check(
        $backup['status'] === 200 && $backup['find_session'] === 1 && $backup['writer'] === 1
            && $backup['payload']['outlet_id'] === 71
            && $backup['payload']['terminal_id'] === 999
            && $backup['payload']['origin_terminal_id'] === 501
            && $backup['payload']['mobile_backup_mode'] === true,
        $endpoint . ' keeps the OPEN session owner terminal and records backup device origin'
    );
}

foreach (['outlet mismatch' => ['outlet_id' => 72], 'terminal mismatch' => ['terminal_id' => 502]] as $label => $extra) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $trace = pdu_dispatch($endpoint, ['id' => 0] + $extra, $binding, null, $session);
        pdu_check(
            $trace['status'] === 403 && $trace['find_order'] === 0 && $trace['find_session'] === 0
                && $trace['writer'] === 0 && $trace['sync_read'] === 0 && $trace['confirm'] === 0,
            $endpoint . ' rejects new-order ' . $label . ' before session/writer/sync'
        );
    }
}

foreach (['missing order' => null, 'zero order outlet' => ['header' => ['id' => 2100, 'outlet_id' => 0]], 'cross order' => ['header' => ['id' => 2100, 'outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-DRAFT']]] as $label => $order) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $trace = pdu_dispatch($endpoint, ['id' => 2100], $binding, $order, $session);
        pdu_check(
            $trace['status'] === 404 && $trace['find_order'] === 1 && $trace['find_session'] === 0
                && $trace['writer'] === 0 && $trace['sync_read'] === 0 && $trace['sync_insert'] === 0
                && $trace['confirm'] === 0 && $trace['monitor'] === 0 && $trace['print'] === 0
                && $trace['body'] === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                && strpos(json_encode($trace['body']), 'MUST-NOT-LEAK') === false,
            $endpoint . ' rejects ' . $label . ' with generic 404 and zero downstream'
        );
    }
}

foreach ([
    'missing session' => null,
    'employee mismatch session' => array_merge($session, ['employee_id' => 315]),
    'cross-outlet session' => array_merge($session, ['outlet_id' => 72]),
    'closed session' => array_merge($session, ['session_status' => 'CLOSED']),
] as $label => $activeSession) {
    foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
        $trace = pdu_dispatch($endpoint, ['id' => 0], $binding, null, $activeSession);
        pdu_check(
            $trace['status'] === 403 && $trace['find_session'] === 1 && $trace['writer'] === 0
                && $trace['sync_read'] === 0 && $trace['confirm'] === 0 && $trace['print'] === 0,
            $endpoint . ' rejects ' . $label . ' before writer/sync/confirm/print'
        );
    }
}

$validEvent = [
    'event_type' => 'ORDER_UPSERT', 'server_order_id' => 2300,
    'request_json' => json_encode(['id' => 0, 'outlet_id' => 71, 'terminal_id' => 501]),
    'response_json' => json_encode(['server_id' => 2300, 'replay_value' => 'SAFE']),
];
$backupEvent = array_replace($validEvent, [
    'request_json' => json_encode([
        'id' => 0,
        'outlet_id' => 71,
        'terminal_id' => 999,
        'origin_terminal_id' => 501,
        'mobile_backup_mode' => true,
    ]),
    'response_json' => json_encode(['server_id' => 2300, 'replay_value' => 'BACKUP-SAFE']),
]);
$replayCases = [
    'matching proof' => [$validEvent, 200],
    'backup owner with matching device origin' => [$backupEvent, 200],
    'cross outlet' => [array_replace($validEvent, ['request_json' => json_encode(['id' => 0, 'outlet_id' => 72, 'terminal_id' => 501]), 'response_json' => json_encode(['server_id' => 2300, 'private_marker' => 'MUST-NOT-LEAK-REPLAY'])]), 404],
    'cross terminal' => [array_replace($validEvent, ['request_json' => json_encode(['id' => 0, 'outlet_id' => 71, 'terminal_id' => 502]), 'response_json' => json_encode(['server_id' => 2300, 'private_marker' => 'MUST-NOT-LEAK-REPLAY'])]), 404],
    'backup owner with spoofed origin' => [array_replace($backupEvent, ['request_json' => json_encode(['id' => 0, 'outlet_id' => 71, 'terminal_id' => 999, 'origin_terminal_id' => 502, 'mobile_backup_mode' => true]), 'response_json' => json_encode(['server_id' => 2300, 'private_marker' => 'MUST-NOT-LEAK-REPLAY'])]), 404],
    'server mismatch' => [array_replace($validEvent, ['response_json' => json_encode(['server_id' => 9999, 'private_marker' => 'MUST-NOT-LEAK-REPLAY'])]), 404],
    'legacy no proof' => [['server_order_id' => 2300, 'request_json' => '{}', 'response_json' => json_encode(['private_marker' => 'MUST-NOT-LEAK-LEGACY'])], 404],
];
foreach ($replayCases as $label => $case) {
    [$event, $status] = $case;
    $trace = pdu_dispatch('orders_push', ['id' => 0], $binding, null, $session, $event);
    pdu_check(
        $trace['status'] === $status && $trace['find_session'] === 1 && $trace['sync_read'] === 1
            && $trace['sync_insert'] === 0 && $trace['sync_update'] === 0 && $trace['writer'] === 0
            && $trace['confirm'] === 0 && $trace['monitor'] === 0
            && ($status === 200
                ? in_array(($trace['body']['replay_value'] ?? ''), ['SAFE', 'BACKUP-SAFE'], true)
                : strpos(json_encode($trace['body']), 'MUST-NOT-LEAK') === false),
        'orders_push replay enforces ' . $label . ' without mutation or leakage'
    );
}

foreach (['order_save', 'order_confirm', 'orders_push'] as $endpoint) {
    $trace = pdu_dispatch($endpoint, ['id' => 0, 'outlet_id' => 72, 'terminal_id' => 502], [], null, null, null, false);
    pdu_check(
        $trace['status'] === 200 && $trace['find_order'] === 0 && $trace['find_session'] === 0
            && $trace['writer'] === 1 && $trace['payload']['outlet_id'] === 72 && $trace['payload']['terminal_id'] === 502,
        $endpoint . ' preserves web-session legacy payload and fallback'
    );
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile draft/upsert binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile draft/upsert binding smoke checks passed.' . PHP_EOL;
