<?php

// Focused source/behavior smoke for bearer print-document binding; collect all failures.
$root = dirname(__DIR__, 2);
$controllerSource = file_get_contents($root . '/application/controllers/Pos_mobile.php');
$modelSource = file_get_contents($root . '/application/models/Pos_model.php');
$checks = 0;
$failures = [];

function pmp_check(bool $condition, string $message): void
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

function pmp_method(string $source, string $name): string
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

function pmp_ordered(string $source, array $needles): bool
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

function pmp_dispatch(bool $bearer, array $terminal, ?array $context): array
{
    $trace = [
        'status' => 200, 'last_seen' => 0, 'rbac' => 0, 'resolver' => 0,
        'direct' => 0, 'targets' => 0, 'attempts' => 0, 'body' => [],
    ];
    if ($bearer && ((int)($terminal['id'] ?? 0) <= 0 || (int)($terminal['outlet_id'] ?? 0) <= 0)) {
        $trace['status'] = 401;
        return $trace;
    }
    $trace['last_seen'] = $bearer ? 1 : 0;
    $trace['rbac']++;
    if ($bearer) {
        $trace['resolver']++;
        $orderId = (int)($context['order_id'] ?? 0);
        $contextOutletId = (int)($context['outlet_id'] ?? 0);
        if ($context === null || $orderId <= 0 || $contextOutletId <= 0 || $contextOutletId !== (int)$terminal['outlet_id']) {
            $trace['status'] = 404;
            $trace['body'] = ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
            return $trace;
        }
    }
    $trace['direct']++;
    return $trace;
}

$guard = pmp_method($controllerSource, 'require_mobile_print_document_outlet');
pmp_check(
    pmp_ordered($guard, [
        'is_array($this->mobileUser)', "mobileUser['outlet_id']",
        '$this->Pos_model->find_mobile_print_document_context($documentType, $documentId)',
        "context['order_id']", "context['outlet_id']", "json_error('Order POS tidak ditemukan.', 404)",
    ]),
    'guard resolves the canonical document and enforces bearer outlet with generic 404'
);
pmp_check(strpos($guard, "context['terminal_id']") === false, 'guard allows a document from another original terminal');

$endpoints = [
    'order_void_print_targets' => ['VOID', '$this->Pos_model->direct_print_targets_for_void('],
    'order_refund_print_targets' => ['REFUND', '$this->Pos_model->direct_print_targets_for_refund('],
    'payment_print_targets' => ['PAYMENT', '$this->Pos_model->direct_print_targets_for_payment('],
];
foreach ($endpoints as $endpoint => [$type, $direct]) {
    $method = pmp_method($controllerSource, $endpoint);
    pmp_check(
        pmp_ordered($method, [
            '$this->authorize_mobile(true)', '$this->mobile_permission(',
            "require_mobile_print_document_outlet('" . $type . "', (int)\$id)", $direct,
        ]),
        $endpoint . ' orders auth, RBAC, canonical scope, then direct print'
    );
}

$modelResolver = pmp_method($modelSource, 'find_mobile_print_document_context');
pmp_check(
    pmp_ordered($modelResolver, ['$previousDbDebug = $this->db->db_debug', '$this->db->db_debug = false', 'try {', '} finally {', '$this->db->db_debug = $previousDbDebug']),
    'model resolver restores db_debug in finally'
);
pmp_check(
    strpos($modelResolver, "->join('pos_order o', 'o.id = ' . \$alias . '.order_id', 'inner')") !== false,
    'model resolver requires the canonical parent order'
);

$terminal = ['id' => 501, 'outlet_id' => 71];
foreach (['same terminal' => 501, 'different terminal' => 999] as $label => $documentTerminalId) {
    foreach (array_keys($endpoints) as $endpoint) {
        $trace = pmp_dispatch(true, $terminal, ['order_id' => 4101, 'outlet_id' => 71, 'terminal_id' => $documentTerminalId]);
        pmp_check(
            $trace['status'] === 200 && $trace['resolver'] === 1 && $trace['direct'] === 1,
            $endpoint . ' accepts same outlet with ' . $label
        );
    }
}

$rejects = [
    'missing document' => null,
    'missing parent order' => ['order_id' => 0, 'outlet_id' => 71, 'private_marker' => 'NO-LEAK-ORDER'],
    'zero outlet' => ['order_id' => 4102, 'outlet_id' => 0, 'private_marker' => 'NO-LEAK-ZERO'],
    'cross outlet' => ['order_id' => 4103, 'outlet_id' => 72, 'private_marker' => 'NO-LEAK-CROSS'],
];
foreach ($rejects as $label => $context) {
    foreach (array_keys($endpoints) as $endpoint) {
        $trace = pmp_dispatch(true, $terminal, $context);
        pmp_check(
            $trace['status'] === 404 && $trace['resolver'] === 1 && $trace['direct'] === 0
                && $trace['targets'] === 0 && $trace['attempts'] === 0
                && $trace['body'] === ['ok' => false, 'message' => 'Order POS tidak ditemukan.']
                && strpos(json_encode($trace['body']), 'NO-LEAK') === false,
            $endpoint . ' rejects ' . $label . ' without downstream or leakage'
        );
    }
}

foreach ([
    'zero terminal id' => ['id' => 0, 'outlet_id' => 71],
    'zero terminal outlet' => ['id' => 501, 'outlet_id' => 0],
] as $label => $invalidTerminal) {
    foreach (array_keys($endpoints) as $endpoint) {
        $trace = pmp_dispatch(true, $invalidTerminal, ['order_id' => 4104, 'outlet_id' => 71]);
        pmp_check(
            $trace['status'] === 401 && $trace['last_seen'] === 0 && $trace['rbac'] === 0
                && $trace['resolver'] === 0 && $trace['direct'] === 0 && $trace['attempts'] === 0,
            $endpoint . ' rejects ' . $label . ' at authentication before resolver'
        );
    }
}

foreach (array_keys($endpoints) as $endpoint) {
    $trace = pmp_dispatch(false, [], null);
    pmp_check(
        $trace['status'] === 200 && $trace['resolver'] === 0 && $trace['direct'] === 1,
        $endpoint . ' preserves unscoped web-session fallback'
    );
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS mobile print document binding smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS mobile print document binding smoke checks passed.' . PHP_EOL;
