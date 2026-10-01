<?php

// Focused table-driven behavioral/source smoke; collect all failures.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$checks = 0;
$failures = [];

function prb_check(bool $ok, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    } else {
        echo 'PASS: ' . $message . PHP_EOL;
    }
}

function prb_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function prb_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

function prb_reader(array $binding, array $request, ?array $session, string $mode = 'PRODUCT'): array
{
    $trace = [
        'status' => 200,
        'session' => 0,
        'bootstrap' => 0,
        'product' => 0,
        'bundle' => 0,
        'outlet_id' => 0,
        'backup_mode' => false,
        'owner_terminal_id' => 0,
        'origin_terminal_id' => 0,
    ];
    $employeeId = max(0, (int)($binding['employee_id'] ?? 0));
    $outletId = max(0, (int)($binding['outlet_id'] ?? 0));
    $terminalId = max(0, (int)($binding['terminal_id'] ?? 0));
    if ($employeeId <= 0 || $outletId <= 0 || $terminalId <= 0) {
        $trace['status'] = 403;
        return $trace;
    }
    foreach (['outlet_id' => $outletId, 'default_outlet_id' => $outletId, 'terminal_id' => $terminalId] as $key => $boundId) {
        $requested = max(0, (int)($request[$key] ?? 0));
        if ($requested > 0 && $requested !== $boundId) {
            $trace['status'] = 403;
            return $trace;
        }
    }
    $trace['session']++;
    if ($session !== null) {
        if (
            strtoupper(trim((string)($session['session_status'] ?? ''))) !== 'OPEN'
            || (int)($session['employee_id'] ?? 0) !== $employeeId
            || (int)($session['outlet_id'] ?? 0) !== $outletId
        ) {
            $trace['status'] = 403;
            return $trace;
        }
        $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
        $trace['backup_mode'] = $ownerTerminalId > 0 && $ownerTerminalId !== $terminalId;
        $trace['owner_terminal_id'] = $ownerTerminalId;
        $trace['origin_terminal_id'] = $terminalId;
    }
    $trace['outlet_id'] = $outletId;
    if ($mode === 'BOOTSTRAP') {
        $trace['bootstrap']++;
        $trace['product']++;
        $trace['bundle']++;
    } elseif ($mode === 'BUNDLE') {
        $trace['bundle']++;
    } else {
        $trace['product']++;
    }
    return $trace;
}

function prb_scope(array $options, int $outletId, int $terminalId, ?array $session): array
{
    $options['outlets'] = array_values(array_filter((array)($options['outlets'] ?? []), static fn(array $row): bool => (int)($row['id'] ?? 0) === $outletId));
    $options['terminals'] = array_values(array_filter((array)($options['terminals'] ?? []), static fn(array $row): bool => (int)($row['id'] ?? 0) === $terminalId && (int)($row['outlet_id'] ?? 0) === $outletId));
    $options['default_outlet_id'] = $outletId;
    $options['default_terminal_id'] = $terminalId;
    $options['active_session'] = $session;
    $options['active_sessions'] = $session === null ? [] : [$session];
    return $options;
}

$bootstrap = prb_method($source, 'bootstrap');
$catalog = prb_method($source, 'catalog');
$bindingHelper = prb_method($source, 'mobile_reader_binding_context');
$sessionHelper = prb_method($source, 'mobile_reader_session_context');
$scopeHelper = prb_method($source, 'scope_mobile_reader_option_lists');
$bootstrapBearerAt = strpos($bootstrap, 'if (is_array($this->mobileUser))');
$bootstrapLegacyAt = strpos($bootstrap, "\$outletId = max(0, (int)\$this->input->get('outlet_id'", $bootstrapBearerAt + 1);
$bootstrapBearer = ($bootstrapBearerAt === false || $bootstrapLegacyAt === false) ? '' : substr($bootstrap, $bootstrapBearerAt, $bootstrapLegacyAt - $bootstrapBearerAt);

prb_check(
    prb_ordered($bindingHelper, ["mobileUser['outlet_id']", "mobileUser['terminal_id']", '$outletId <= 0 || $terminalId <= 0', "'outlet_id' => \$outletId", "'default_outlet_id' => \$outletId", "'terminal_id' => \$terminalId", '$requestedId > 0 && $requestedId !== $boundId'])
        && substr_count($bindingHelper, "json_error('Konteks outlet atau terminal perangkat tidak valid.', 403)") === 2,
    'bearer binding uses only mobileUser IDs and rejects positive request mismatches'
);
prb_check(
    prb_ordered($sessionHelper, [
        'find_active_cashier_session(', "session['session_status']", "session['employee_id']",
        "session['outlet_id']", 'json_error(', '$ownerTerminalId', '$backupMode',
    ])
        && strpos($sessionHelper, "'backup_mode' => \$backupMode") !== false
        && strpos($sessionHelper, "'owner_terminal_id' => \$ownerTerminalId") !== false
        && strpos($sessionHelper, "'origin_terminal_id' => \$terminalId") !== false
        && strpos($sessionHelper, "(int)(\$session['terminal_id'] ?? 0) !== \$terminalId") === false
        && prb_ordered($scopeHelper, ["options['outlets']", "(int)(\$row['id'] ?? 0) === \$outletId", "options['terminals']", "(int)(\$row['id'] ?? 0) === \$terminalId", "(int)(\$row['outlet_id'] ?? 0) === \$outletId"]),
    'session helper accepts same-employee/outlet OPEN owners and exposes backup provenance while options keep exact device binding'
);
prb_check(
    prb_ordered($bootstrapBearer, ['mobile_reader_binding_context()', 'mobile_reader_session_context(', 'cashier_bootstrap_options(', 'scope_mobile_reader_option_lists(', "default_outlet_id']", "default_terminal_id']", "active_session']", "active_sessions']", 'order_draft_filter_options()', 'order_product_catalog(', "'limit' => 120", 'order_bundle_catalog('])
        && strpos($bootstrapBearer, 'active_cashier_sessions()') === false,
    'bearer bootstrap scopes nested/top-level context and avoids global sessions'
);
prb_check(
    prb_ordered($catalog, ['is_array($this->mobileUser)', 'mobile_reader_binding_context()', 'mobile_reader_session_context(', "\$outletId = \$binding['outlet_id']", 'order_bundle_catalog(', 'order_product_catalog('])
        && strpos($catalog, 'if (!$isBearer && $outletId <= 0)') !== false,
    'catalog fixes bearer outlet while retaining fallback only for web sessions'
);

$binding = ['employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 501];
$session = [
    'id' => 801,
    'employee_id' => 314,
    'outlet_id' => 71,
    'terminal_id' => 501,
    'session_status' => 'OPEN',
];
$requestCases = [
    'empty request' => [[], 200],
    'matching outlet' => [['outlet_id' => 71], 200],
    'matching defaults and terminal' => [['default_outlet_id' => 71, 'terminal_id' => 501], 200],
    'mismatched outlet' => [['outlet_id' => 72], 403],
    'mismatched default' => [['default_outlet_id' => 72], 403],
    'mismatched terminal' => [['terminal_id' => 502], 403],
];
foreach ($requestCases as $label => [$request, $status]) {
    $trace = prb_reader($binding, $request, $session);
    prb_check($trace['status'] === $status && ($status === 200 || array_sum(array_slice($trace, 1, 4)) === 0), $label . ' has expected pre-model boundary');
}
foreach ([
    'employee zero' => ['employee_id' => 0, 'outlet_id' => 71, 'terminal_id' => 501],
    'outlet zero' => ['employee_id' => 314, 'outlet_id' => 0, 'terminal_id' => 501],
    'terminal zero' => ['employee_id' => 314, 'outlet_id' => 71, 'terminal_id' => 0],
] as $label => $invalidBinding) {
    $trace = prb_reader($invalidBinding, [], $session);
    prb_check($trace['status'] === 403 && $trace['session'] === 0 && $trace['product'] === 0, $label . ' fails before model work');
}
foreach ([
    'wrong session employee' => array_merge($session, ['employee_id' => 315]),
    'wrong session outlet' => array_merge($session, ['outlet_id' => 72]),
    'closed session' => array_merge($session, ['session_status' => 'CLOSED']),
] as $label => $wrongSession) {
    $trace = prb_reader($binding, [], $wrongSession);
    prb_check(
        $trace['status'] === 403 && $trace['session'] === 1
            && $trace['bootstrap'] === 0 && $trace['product'] === 0 && $trace['bundle'] === 0,
        $label . ' stops before catalog/model work'
    );
}

$backupSession = array_merge($session, ['terminal_id' => 999]);
foreach (['BOOTSTRAP', 'PRODUCT', 'BUNDLE'] as $mode) {
    $trace = prb_reader($binding, [], $backupSession, $mode);
    prb_check(
        $trace['status'] === 200
            && $trace['backup_mode'] === true
            && $trace['owner_terminal_id'] === 999
            && $trace['origin_terminal_id'] === 501
            && ($mode !== 'BOOTSTRAP' || ($trace['product'] === 1 && $trace['bundle'] === 1))
            && ($mode !== 'PRODUCT' || ($trace['product'] === 1 && $trace['bundle'] === 0))
            && ($mode !== 'BUNDLE' || ($trace['bundle'] === 1 && $trace['product'] === 0)),
        strtolower($mode) . ' accepts backup device and retains owner/origin terminal provenance'
    );
}

$bootstrapTrace = prb_reader($binding, [], $session, 'BOOTSTRAP');
$productTrace = prb_reader($binding, [], $session, 'PRODUCT');
$bundleTrace = prb_reader($binding, ['outlet_id' => 71, 'default_outlet_id' => 71], $session, 'BUNDLE');
prb_check($bootstrapTrace['product'] === 1 && $bootstrapTrace['bundle'] === 1 && $bootstrapTrace['outlet_id'] === 71, 'bootstrap queries product and bundle at outlet 71');
prb_check(
    $bootstrapTrace['backup_mode'] === false
        && $bootstrapTrace['owner_terminal_id'] === 501
        && $bootstrapTrace['origin_terminal_id'] === 501,
    'owner device reports matching owner/origin terminal with backup mode disabled'
);
prb_check($productTrace['product'] === 1 && $productTrace['bundle'] === 0 && $productTrace['outlet_id'] === 71, 'product catalog always uses outlet 71');
prb_check($bundleTrace['bundle'] === 1 && $bundleTrace['product'] === 0 && $bundleTrace['outlet_id'] === 71, 'bundle catalog always uses outlet 71');

$fixture = [
    'outlets' => [['id' => 71], ['id' => 72, 'marker' => 'MUST-NOT-LEAK']],
    'terminals' => [['id' => 501, 'outlet_id' => 71], ['id' => 502, 'outlet_id' => 72, 'marker' => 'MUST-NOT-LEAK']],
    'sales_channels' => [['id' => 31]],
    'active_session' => ['outlet_id' => 72, 'marker' => 'MUST-NOT-LEAK'],
    'active_sessions' => [['outlet_id' => 72, 'marker' => 'MUST-NOT-LEAK']],
];
$scoped = prb_scope($fixture, 71, 501, null);
prb_check($scoped['active_session'] === null && $scoped['active_sessions'] === [], 'no active bearer session returns null and empty session collection');
prb_check(array_column($scoped['outlets'], 'id') === [71] && array_column($scoped['terminals'], 'id') === [501] && strpos(json_encode($scoped), 'MUST-NOT-LEAK') === false, 'scoped bootstrap does not leak another outlet or terminal');
prb_check($scoped['sales_channels'] === [['id' => 31]], 'scoping preserves non-context bootstrap collections');

$legacyOutlet = 72;
prb_check(
    strpos($bootstrap, 'active_cashier_sessions()') !== false
        && strpos($bootstrap, "input->get('default_outlet_id'") !== false
        && strpos($bootstrap, "'outlet_id' => \$outletId") !== false
        && $legacyOutlet === 72,
    'web-session bootstrap retains request precedence and legacy global response path'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile reader binding smoke check(s) failed.' . PHP_EOL);
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile reader outlet binding smoke checks passed.' . PHP_EOL;
