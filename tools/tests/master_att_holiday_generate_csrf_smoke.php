<?php

// Short source + boundary simulation smoke; no CI bootstrap or database.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Master.php');
$view = file_get_contents($root . '/application/views/master/index.php');
$checks = 0;
$failures = [];

function hg_check(bool $condition, string $message): void
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

function hg_method(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function hg_ordered(string $source, array $needles): bool
{
    $position = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $position) {
            return false;
        }
        $position = $next;
    }
    return true;
}

/**
 * Simulate only the permission/POST/token boundary and count downstream stages.
 */
function hg_simulate(bool $canCreate, string $method, string $provided, string $expected): array
{
    $trace = [
        'permission' => 1,
        'token' => 0,
        'year' => 0,
        'core' => 0,
        'upsert' => 0,
        'status' => 200,
    ];
    if (!$canCreate) {
        $trace['status'] = 403;
        return $trace;
    }
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    $trace['token']++;
    if (
        preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
        || !hash_equals($expected, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['year']++;
    $trace['core']++;
    $trace['upsert']++;
    return $trace;
}

$holiday = hg_method($controller, 'att_holiday_generate_year');
$guard = hg_method($controller, 'requireMasterMutationRequest');
hg_check(
    hg_ordered($holiday, [
        "require_permission('attendance.holiday.index', 'create')",
        'requireMasterMutationRequest()',
        "post('year', true)",
        'information_schema.tables',
        'FROM core.att_holiday_calendar',
        "insert_string('att_holiday_calendar'",
        'ON DUPLICATE KEY UPDATE',
    ]),
    'permission and POST/token precede year, core source, and upsert'
);
hg_check(
    strpos($holiday, 'input->method(') === false
        && strpos($guard, "method(true) !== 'POST'") !== false
        && strpos($guard, 'MASTER_MUTATION_CSRF_FORM_FIELD') !== false
        && strpos($guard, 'hash_equals($sessionToken, $providedToken)') !== false,
    'old method check is removed and shared Master guard validates POST/form token'
);

$formStart = strpos($view, "site_url('master/att-holiday/generate-year')");
$form = $formStart === false ? '' : substr($view, $formStart, 900);
hg_check(
    hg_ordered($form, [
        'name="master_mutation_csrf"',
        'html_escape($masterMutationCsrfToken)',
        'name="year"',
        'Generate 1 Tahun',
    ]),
    'Generate 1 Tahun form emits escaped hidden token before year'
);
hg_check(
    strpos($holiday, "set_flashdata('error', 'Tahun generate libur tidak valid.')") !== false
        && strpos($holiday, "set_flashdata('warning', 'Tidak ada data libur tahun '") !== false
        && strpos($holiday, "set_flashdata('success', 'Generate hari libur tahun '") !== false
        && substr_count($holiday, "redirect('master/att-holiday')") >= 4,
    'legacy error/warning/success flash and redirect behavior remains'
);

$token = str_repeat('a', 64);
$blockedCases = [
    'view-only' => hg_simulate(false, 'POST', $token, $token),
    'GET' => hg_simulate(true, 'GET', $token, $token),
    'PUT' => hg_simulate(true, 'PUT', $token, $token),
    'wrong token' => hg_simulate(true, 'POST', str_repeat('b', 64), $token),
];
foreach ($blockedCases as $label => $trace) {
    hg_check(
        in_array($trace['status'], [403, 405], true)
            && $trace['year'] === 0 && $trace['core'] === 0 && $trace['upsert'] === 0,
        $label . ' stops before year/core/upsert'
    );
}

$valid = hg_simulate(true, 'POST', $token, $token);
hg_check(
    $valid['status'] === 200 && $valid['token'] === 1
        && $valid['year'] === 1 && $valid['core'] === 1 && $valid['upsert'] === 1,
    'valid writer crosses each protected stage exactly once'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' holiday generation CSRF check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' holiday generation CSRF smoke checks passed.' . PHP_EOL;
