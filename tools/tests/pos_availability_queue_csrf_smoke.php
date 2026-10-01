<?php

// Focused source assertions + tiny guard simulation; no CI/database bootstrap.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Pos.php');
$view = file_get_contents($root . '/application/views/pos/availability_queue_index.php');
$checks = 0;
$failures = [];

function paq_check(bool $ok, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function paq_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function paq_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

function paq_simulate(string $method, string $provided, string $expected): array
{
    $trace = ['status' => 200, 'payload' => 0, 'load' => 0, 'call' => 0];
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    if (
        preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
        || !hash_equals($expected, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['payload']++;
    $trace['load']++;
    $trace['call']++;
    return $trace;
}

$index = paq_method($controller, 'availability_queue');
$process = paq_method($controller, 'availability_queue_process');
$retry = paq_method($controller, 'availability_queue_retry');
$runner = paq_method($controller, 'availability_queue_run');
$generator = paq_method($controller, 'pos_availability_queue_csrf');
$guard = paq_method($controller, 'require_pos_availability_queue_csrf');

paq_check(
    strpos($controller, "POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY = 'pos_availability_queue_csrf'") !== false
        && strpos($controller, "POS_AVAILABILITY_QUEUE_CSRF_FORM_FIELD = 'pos_availability_queue_csrf'") !== false
        && strpos($generator, 'bin2hex(random_bytes(32))') !== false
        && strpos($generator, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'dedicated availability queue token is random 64-hex and separate'
);

paq_check(
    paq_ordered($index, ["require_permission('pos.availability.queue.index', 'view')", 'pos_availability_queue_csrf()', 'availability_queue_filters()', 'Pos_availability_queue_model->rows(', "render('pos/availability_queue_index'", "'pos_availability_queue_csrf_token'"])
        && strpos($index, 'pos_transaction_csrf') === false,
    'view RBAC precedes dedicated token generation and queue reads/render'
);

paq_check(
    paq_ordered($guard, ["method(true) !== 'POST'", "set_header('Allow: POST')", 'POS_AVAILABILITY_QUEUE_CSRF_FORM_FIELD', 'POS_AVAILABILITY_QUEUE_CSRF_SESSION_KEY', 'hash_equals('])
        && strpos($guard, "show_error('Metode request tidak diizinkan.', 405") !== false
        && strpos($guard, "show_error('Permintaan antrean ketersediaan POS tidak valid.', 403") !== false,
    'guard returns 405 Allow POST or generic 403 before accepting request'
);

paq_check(
    paq_ordered($process, ["require_permission('pos.availability.queue.index', 'edit')", 'require_pos_availability_queue_csrf()', 'request_payload()', "load->library('PosAvailabilityQueueService')", 'processPendingJobs(', 'set_flashdata(', 'redirect('])
        && strpos($process, 'require_pos_transaction_csrf') === false,
    'manual process guards before payload/service while preserving flash redirect'
);
paq_check(
    paq_ordered($retry, ["require_permission('pos.availability.queue.index', 'edit')", 'require_pos_availability_queue_csrf()', 'request_payload()', "load->library('PosAvailabilityQueueService')", 'retryJob(', 'set_flashdata(', 'redirect('])
        && strpos($retry, 'require_pos_transaction_csrf') === false,
    'retry guards before payload/service while preserving flash redirect'
);

$processFormAt = strpos($view, "site_url('pos/availability-queue/process')");
$retryFormAt = strpos($view, "site_url('pos/availability-queue/retry/'");
$hidden = 'name="pos_availability_queue_csrf" value="<?php echo html_escape($availabilityQueueCsrfToken); ?>"';
paq_check(
    $processFormAt !== false && strpos(substr($view, $processFormAt, 900), $hidden) !== false
        && $retryFormAt !== false && strpos(substr($view, $retryFormAt, 900), $hidden) !== false
        && substr_count($view, $hidden) === 2
        && substr_count($view, '$csrfEnabled') >= 3
        && substr_count($view, '$returnInputs()') === 2,
    'process and every rendered retry form include dedicated token plus existing fields'
);

paq_check(
    paq_ordered($runner, ['is_cli_request()', "load->library('PosAvailabilityQueueService')", 'processPendingJobs(', 'echo json_encode('])
        && strpos($runner, 'require_pos_availability_queue_csrf') === false,
    'CLI availability queue runner remains outside the web CSRF boundary'
);

$token = str_repeat('a', 64);
foreach ([
    'GET' => paq_simulate('GET', $token, $token),
    'missing token' => paq_simulate('POST', '', $token),
    'foreign token' => paq_simulate('POST', str_repeat('b', 64), $token),
] as $label => $trace) {
    paq_check(
        in_array($trace['status'], [403, 405], true)
            && $trace['payload'] === 0 && $trace['load'] === 0 && $trace['call'] === 0,
        $label . ' stops before payload/service/call'
    );
}
$valid = paq_simulate('POST', $token, $token);
paq_check($valid === ['status' => 200, 'payload' => 1, 'load' => 1, 'call' => 1], 'valid dedicated token reaches service exactly once');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' POS availability queue CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' POS availability queue CSRF smoke checks passed.' . PHP_EOL;
