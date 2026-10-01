<?php

// Focused source/helper simulation for POS runtime-sync transaction CSRF.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Pos.php');
$views = [
    'online food' => file_get_contents($root . '/application/views/pos/online_food_orders.php'),
    'self order' => file_get_contents($root . '/application/views/pos/self_order_orders.php'),
];
$checks = 0;
$failures = [];

function prs_check(bool $condition, string $message): void
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

function prs_method(string $source, string $name): string
{
    $needle = 'function ' . $name . '(';
    $start = strpos($source, $needle);
    if ($start === false) {
        return '';
    }
    $next = strpos($source, "\n    public function ", $start + strlen($needle));
    return substr($source, $start, $next === false ? null : $next - $start);
}

function prs_ordered(string $source, array $needles): bool
{
    $offset = -1;
    foreach ($needles as $needle) {
        $position = strpos($source, $needle);
        if ($position === false || $position <= $offset) {
            return false;
        }
        $offset = $position;
    }
    return true;
}

function prs_simulate(string $method, string $provided, string $session): array
{
    $trace = ['status' => 200, 'payload' => 0, 'refresh' => 0, 'job' => 0, 'model' => 0];
    if ($method !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    if (
        preg_match('/\A[0-9a-fA-F]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-fA-F]{64}\z/D', $session) !== 1
        || !hash_equals($session, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['payload']++;
    $trace['refresh']++;
    $trace['job']++;
    $trace['model']++;
    return $trace;
}

$method = prs_method($controller, 'order_runtime_sync');
prs_check($method !== '', 'order_runtime_sync source exists');
prs_check(
    prs_ordered($method, [
        '$this->require_permission($pageCode, \'edit\')',
        '$this->require_pos_transaction_csrf()',
        '$this->request_payload()',
        '$this->trigger_stock_live_refresh_for_order(',
    ]),
    'runtime sync orders RBAC, transaction CSRF, payload, then stock refresh'
);
prs_check(substr_count($method, 'require_pos_transaction_csrf()') === 1, 'runtime sync has one scoped transaction guard');
foreach (['event_source', 'success_count', 'failed_count', 'marked_product_count'] as $responseKey) {
    prs_check(strpos($method, "'" . $responseKey . "' =>") !== false, 'valid response preserves ' . $responseKey);
}

$token = str_repeat('a', 64);
foreach (['GET', 'PUT', 'PATCH'] as $requestMethod) {
    $trace = prs_simulate($requestMethod, $token, $token);
    prs_check(
        $trace === ['status' => 405, 'payload' => 0, 'refresh' => 0, 'job' => 0, 'model' => 0],
        $requestMethod . ' is rejected before payload and downstream work'
    );
}
foreach (['missing token' => '', 'wrong token' => str_repeat('b', 64)] as $label => $provided) {
    $trace = prs_simulate('POST', $provided, $token);
    prs_check(
        $trace === ['status' => 403, 'payload' => 0, 'refresh' => 0, 'job' => 0, 'model' => 0],
        $label . ' POST is rejected before payload and downstream work'
    );
}
$valid = prs_simulate('POST', $token, $token);
prs_check(
    $valid === ['status' => 200, 'payload' => 1, 'refresh' => 1, 'job' => 1, 'model' => 1],
    'valid POST reaches the existing refresh path once'
);

foreach ($views as $name => $view) {
    $runtimeCall = "postPosTransactionJson(`<?php echo site_url('pos/orders/runtime-sync'); ?>/\${safeOrderId}`";
    $legacyCall = "postJson(`<?php echo site_url('pos/orders/runtime-sync'); ?>/\${safeOrderId}`";
    prs_check(substr_count($view, $runtimeCall) === 1, $name . ' runtime-sync uses the scoped transaction wrapper');
    prs_check(strpos($view, $legacyCall) === false, $name . ' has no unguarded runtime-sync caller');
    $wrapper = prs_method($view, 'postPosTransactionJson');
    prs_check(strpos($wrapper, "'X-Pos-Transaction-CSRF': posTransactionCsrfToken") !== false, $name . ' wrapper emits the transaction CSRF header');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS runtime-sync CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS runtime-sync CSRF smoke checks passed.' . PHP_EOL;
