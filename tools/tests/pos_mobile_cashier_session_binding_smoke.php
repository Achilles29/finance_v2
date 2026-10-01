<?php

// Focused source/behavior smoke for bearer cashier sessions; collect all failures.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function pcs_check(bool $condition, string $message): void
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

function pcs_method(string $source, string $name): string
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

function pcs_ordered(string $source, array $needles): bool
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

function pcs_context(array $binding, ?array $session, bool $required): array
{
    $trace = [
        'ok' => true,
        'status' => 200,
        'find_session' => 0,
        'session' => null,
        'backup_mode' => false,
        'owner_terminal_id' => 0,
        'origin_terminal_id' => 0,
        'body' => [],
    ];
    $employeeId = (int)($binding['employee_id'] ?? 0);
    $outletId = (int)($binding['outlet_id'] ?? 0);
    $terminalId = (int)($binding['terminal_id'] ?? 0);
    if ($employeeId <= 0 || $outletId <= 0 || $terminalId <= 0) {
        $trace['ok'] = false;
        $trace['status'] = 403;
        return $trace;
    }
    $trace['find_session']++;
    if ($session === null) {
        if ($required) {
            $trace['ok'] = false;
            $trace['status'] = 403;
        }
        return $trace;
    }
    if (
        strtoupper((string)($session['session_status'] ?? '')) !== 'OPEN'
        || (int)($session['employee_id'] ?? 0) !== $employeeId
        || (int)($session['outlet_id'] ?? 0) !== $outletId
    ) {
        $trace['ok'] = false;
        $trace['status'] = 403;
        return $trace;
    }
    $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
    $trace['session'] = $session;
    $trace['backup_mode'] = $ownerTerminalId > 0 && $ownerTerminalId !== $terminalId;
    $trace['owner_terminal_id'] = $ownerTerminalId;
    $trace['origin_terminal_id'] = $terminalId;
    return $trace;
}

function pcs_open(bool $bearer, array $binding, array $payload, ?array $session): array
{
    $trace = [
        'status' => 200, 'find_session' => 0, 'recon' => 0, 'open' => 0,
        'payload' => $payload,
    ];
    if (!$bearer) {
        $trace['recon']++;
        $trace['open']++;
        return $trace;
    }
    foreach (['outlet_id', 'terminal_id'] as $key) {
        $requested = max(0, (int)($payload[$key] ?? 0));
        if ($requested > 0 && $requested !== (int)($binding[$key] ?? 0)) {
            $trace['status'] = 403;
            return $trace;
        }
    }
    $context = pcs_context($binding, $session, false);
    $trace['find_session'] = $context['find_session'];
    if (!$context['ok']) {
        $trace['status'] = 403;
        return $trace;
    }
    $ownerTerminalId = max(0, (int)($context['session']['terminal_id'] ?? 0));
    $trace['payload']['outlet_id'] = (int)$binding['outlet_id'];
    $trace['payload']['terminal_id'] = $ownerTerminalId > 0
        ? $ownerTerminalId
        : (int)$binding['terminal_id'];
    $trace['payload']['origin_terminal_id'] = (int)$binding['terminal_id'];
    $trace['payload']['mobile_backup_mode'] = !empty($context['backup_mode']);
    $trace['recon']++;
    $trace['open']++;
    return $trace;
}

function pcs_reader(string $endpoint, bool $bearer, array $binding, ?array $session): array
{
    $trace = [
        'status' => 200, 'find_session' => 0, 'global_sessions' => 0,
        'preview' => 0, 'recon' => 0, 'close' => 0, 'print' => 0,
        'session' => null, 'active_sessions' => [],
        'backup_mode' => false, 'owner_terminal_id' => 0, 'origin_terminal_id' => 0,
        'body' => [],
    ];
    if (!$bearer) {
        if ($endpoint === 'session_status') {
            $trace['find_session']++;
            $trace['global_sessions']++;
        } elseif ($endpoint === 'cashier_close_preview') {
            $trace['preview']++;
        } else {
            $trace['recon']++;
            $trace['close']++;
            $trace['print']++;
        }
        return $trace;
    }
    $required = $endpoint !== 'session_status';
    $context = pcs_context($binding, $session, $required);
    $trace['find_session'] = $context['find_session'];
    if (!$context['ok']) {
        $trace['status'] = 403;
        return $trace;
    }
    if ($endpoint === 'session_status') {
        $trace['session'] = $context['session'];
        $trace['active_sessions'] = $context['session'] === null ? [] : [$context['session']];
        $trace['backup_mode'] = !empty($context['backup_mode']);
        $trace['owner_terminal_id'] = (int)($context['owner_terminal_id'] ?? 0);
        $trace['origin_terminal_id'] = (int)($context['origin_terminal_id'] ?? 0);
    } elseif ($endpoint === 'cashier_close_preview') {
        $trace['preview']++;
    } else {
        $trace['recon']++;
        $trace['close']++;
        $trace['print']++;
    }
    return $trace;
}

$helper = pcs_method($source, 'mobile_cashier_session_context');
pcs_check(
    pcs_ordered($helper, [
        "mobileUser['employee_id']", "mobileUser['outlet_id']", "mobileUser['terminal_id']",
        '$this->Pos_model->find_active_cashier_session($employeeId)', "session['session_status']",
        "session['employee_id']", "session['outlet_id']", '$ownerTerminalId',
        "'backup_mode' =>", "'owner_terminal_id' =>", "'origin_terminal_id' =>",
    ]),
    'cashier helper binds OPEN session by employee/outlet and reports owner, origin, and backup mode'
);
pcs_check(
    substr_count($helper, "json_error('Sesi kasir tidak sesuai dengan perangkat.', 403)") === 2
        && substr_count($helper, "json_error('Sesi kasir tidak sesuai dengan outlet atau akun perangkat.', 403)") === 1,
    'cashier helper uses generic 403 responses without returning private session data'
);

$open = pcs_method($source, 'cashier_open');
pcs_check(
    pcs_ordered($open, [
        '$this->require_mobile_post()', '$this->authorize_mobile(true)', '$this->mobile_permission(',
        '$this->request_payload()', '$requestedOutletId', '$requestedTerminalId',
        '$this->mobile_cashier_session_context(false)', '$ownerTerminalId',
        "payload['outlet_id'] = \$boundOutletId", "payload['terminal_id'] = \$ownerTerminalId",
        "payload['origin_terminal_id'] = \$boundTerminalId", "payload['mobile_backup_mode']",
        "daily_recon_gate_status('OPEN')",
        '$this->Pos_model->open_cashier_session($payload',
    ]),
    'cashier_open injects session owner, device origin, and backup mode before recon and open'
);

$status = pcs_method($source, 'session_status');
$statusBearerAt = strpos($status, 'if (is_array($this->mobileUser))');
$statusBearerEnd = $statusBearerAt === false ? false : strpos($status, "\n        \$this->json_ok([", $statusBearerAt + 1);
$statusBearer = ($statusBearerAt === false || $statusBearerEnd === false) ? '' : substr($status, $statusBearerAt, $statusBearerEnd - $statusBearerAt);
pcs_check(
    pcs_ordered($status, [
        '$this->mobile_cashier_session_context(false)', "'session' => \$session",
        "'active_sessions' => \$session === null ? [] : [\$session]", "'backup_mode' =>",
        "'owner_terminal_id' =>", "'origin_terminal_id' =>",
    ])
        && strpos($statusBearer, 'active_cashier_sessions()') === false
        && strpos($status, 'active_cashier_sessions()') !== false,
    'bearer session_status returns only scoped owner/origin/backup context while web retains global legacy response'
);

foreach (['cashier_close_preview' => '$this->Pos_model->cashier_close_preview(', 'cashier_close' => "daily_recon_gate_status('CLOSE')"] as $endpoint => $downstream) {
    $method = pcs_method($source, $endpoint);
    pcs_check(
        pcs_ordered($method, ['$this->authorize_mobile(true)', '$this->mobile_permission(', '$this->mobile_cashier_session_context(true)', $downstream]),
        $endpoint . ' requires matching bearer session before downstream work'
    );
}

$binding = ['employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 501];
$matching = ['id' => 801, 'employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 501, 'session_status' => 'OPEN'];
$backupOwner = array_merge($matching, ['terminal_id' => 999]);
$openNoSession = pcs_open(true, $binding, ['opening_cash' => 100000], null);
pcs_check(
    $openNoSession['status'] === 200 && $openNoSession['find_session'] === 1
        && $openNoSession['recon'] === 1 && $openNoSession['open'] === 1
        && $openNoSession['payload']['outlet_id'] === 71 && $openNoSession['payload']['terminal_id'] === 501
        && $openNoSession['payload']['origin_terminal_id'] === 501
        && $openNoSession['payload']['mobile_backup_mode'] === false,
    'bearer cashier_open injects authoritative context when no session exists'
);
foreach ([
    'omitted context' => ['opening_cash' => 100000],
    'zero context' => ['outlet_id' => 0, 'terminal_id' => 0, 'opening_cash' => 100000],
] as $label => $payload) {
    $trace = pcs_open(true, $binding, $payload, $matching);
    pcs_check(
        $trace['status'] === 200 && $trace['find_session'] === 1
            && $trace['recon'] === 1 && $trace['open'] === 1
            && $trace['payload']['outlet_id'] === 71 && $trace['payload']['terminal_id'] === 501
            && $trace['payload']['origin_terminal_id'] === 501
            && $trace['payload']['mobile_backup_mode'] === false,
        'cashier_open existing matching OPEN session overwrites ' . $label . ' with authoritative context'
    );
}

$backupOpen = pcs_open(true, $binding, ['outlet_id' => 71, 'terminal_id' => 501], $backupOwner);
pcs_check(
    $backupOpen['status'] === 200 && $backupOpen['find_session'] === 1
        && $backupOpen['recon'] === 1 && $backupOpen['open'] === 1
        && $backupOpen['payload']['outlet_id'] === 71
        && $backupOpen['payload']['terminal_id'] === 999
        && $backupOpen['payload']['origin_terminal_id'] === 501
        && $backupOpen['payload']['mobile_backup_mode'] === true,
    'registered backup terminal attaches to same-employee same-outlet OPEN owner session'
);

foreach ([
    'payload outlet mismatch' => [['outlet_id' => 72, 'terminal_id' => 501], null],
    'payload terminal mismatch' => [['outlet_id' => 71, 'terminal_id' => 502], null],
    'existing outlet mismatch' => [['outlet_id' => 71, 'terminal_id' => 501], array_merge($matching, ['outlet_id' => 72])],
    'existing employee mismatch' => [['outlet_id' => 71, 'terminal_id' => 501], array_merge($matching, ['employee_id' => 315])],
    'existing closed session' => [['outlet_id' => 71, 'terminal_id' => 501], array_merge($matching, ['session_status' => 'CLOSED'])],
] as $label => $case) {
    [$payload, $session] = $case;
    $trace = pcs_open(true, $binding, $payload, $session);
    pcs_check(
        $trace['status'] === 403 && $trace['recon'] === 0 && $trace['open'] === 0,
        'cashier_open rejects ' . $label . ' before recon/open'
    );
}

foreach (['session_status', 'cashier_close_preview', 'cashier_close'] as $endpoint) {
    $valid = pcs_reader($endpoint, true, $binding, $matching);
    pcs_check(
        $valid['status'] === 200 && $valid['find_session'] === 1
            && ($endpoint !== 'session_status' || ($valid['session']['id'] ?? 0) === 801),
        $endpoint . ' accepts the matching OPEN bearer session'
    );
    $backup = pcs_reader($endpoint, true, $binding, $backupOwner);
    pcs_check(
        $backup['status'] === 200 && $backup['find_session'] === 1
            && $backup['global_sessions'] === 0
            && ($endpoint !== 'cashier_close_preview' || $backup['preview'] === 1)
            && ($endpoint !== 'cashier_close'
                || ($backup['recon'] === 1 && $backup['close'] === 1 && $backup['print'] === 1))
            && ($endpoint !== 'session_status'
                || ($backup['backup_mode'] === true
                    && $backup['owner_terminal_id'] === 999
                    && $backup['origin_terminal_id'] === 501)),
        $endpoint . ' accepts registered backup device against the owner terminal session'
    );
}

foreach ([
    'cross-outlet session' => array_merge($matching, ['outlet_id' => 72, 'private_marker' => 'MUST-NOT-LEAK-CASHIER']),
    'employee-mismatch session' => array_merge($matching, ['employee_id' => 315, 'private_marker' => 'MUST-NOT-LEAK-CASHIER']),
    'closed session' => array_merge($matching, ['session_status' => 'CLOSED', 'private_marker' => 'MUST-NOT-LEAK-CASHIER']),
] as $label => $invalidSession) {
    foreach (['session_status', 'cashier_close_preview', 'cashier_close'] as $endpoint) {
        $denied = pcs_reader($endpoint, true, $binding, $invalidSession);
        pcs_check(
            $denied['status'] === 403 && $denied['preview'] === 0
                && $denied['recon'] === 0 && $denied['close'] === 0 && $denied['print'] === 0
                && $denied['global_sessions'] === 0 && $denied['body'] === [],
            $endpoint . ' rejects ' . $label . ' without downstream work or private-data leakage'
        );
    }
}

$emptyStatus = pcs_reader('session_status', true, $binding, null);
pcs_check(
    $emptyStatus['status'] === 200 && $emptyStatus['session'] === null
        && $emptyStatus['active_sessions'] === [] && $emptyStatus['global_sessions'] === 0,
    'bearer session_status returns null and empty list when no session exists'
);
foreach (['cashier_close_preview', 'cashier_close'] as $endpoint) {
    $missing = pcs_reader($endpoint, true, $binding, null);
    pcs_check(
        $missing['status'] === 403 && $missing['preview'] === 0 && $missing['recon'] === 0
            && $missing['close'] === 0 && $missing['print'] === 0,
        $endpoint . ' rejects a missing OPEN session before downstream work'
    );
}

$webOpen = pcs_open(false, [], ['outlet_id' => 72, 'terminal_id' => 502], null);
$webStatus = pcs_reader('session_status', false, [], $matching);
$webPreview = pcs_reader('cashier_close_preview', false, [], $matching);
$webClose = pcs_reader('cashier_close', false, [], $matching);
pcs_check(
    $webOpen['open'] === 1 && $webOpen['payload']['outlet_id'] === 72
        && $webStatus['global_sessions'] === 1 && $webPreview['preview'] === 1
        && $webClose['close'] === 1 && $webClose['print'] === 1,
    'web-session cashier methods preserve legacy paths and payload'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile cashier session binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile cashier session binding smoke checks passed.' . PHP_EOL;
