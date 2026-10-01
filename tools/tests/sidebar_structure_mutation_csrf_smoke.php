<?php

// Focused source + boundary simulation smoke; no CI/database bootstrap.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Sidebar.php');
$view = file_get_contents($root . '/application/views/sidebar/manage.php');
$checks = 0;
$failures = [];

function ss_check(bool $condition, string $message): void
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

function ss_method(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function ss_ordered(string $source, array $needles): bool
{
    $position = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $position) return false;
        $position = $next;
    }
    return true;
}

function ss_simulate(bool $super, string $method, bool $ajax, string $provided, string $expected, bool $validJson): array
{
    $trace = ['status' => 200, 'payload' => 0, 'decode' => 0, 'trans' => 0, 'model' => 0, 'cache' => 0];
    if (!$super) {
        $trace['status'] = 403;
        return $trace;
    }
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    if (!$ajax) {
        $trace['status'] = 404;
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
    $trace['decode']++;
    if (!$validJson) {
        $trace['status'] = 400;
        return $trace;
    }
    $trace['trans']++;
    $trace['model']++;
    $trace['cache']++;
    return $trace;
}

$manage = ss_method($controller, 'manage');
$save = ss_method($controller, 'save_structure');
$guard = ss_method($controller, 'require_sidebar_structure_mutation_request');
$generator = ss_method($controller, 'sidebar_structure_csrf');

ss_check(
    strpos($controller, "STRUCTURE_CSRF_SESSION_KEY = 'sidebar_structure_csrf'") !== false
        && strpos($controller, "STRUCTURE_CSRF_CI_HEADER = 'X-Sidebar-Structure-Csrf'") !== false
        && strpos($generator, 'bin2hex(random_bytes(32))') !== false
        && strpos($generator, "preg_match('/\\A[0-9a-f]{64}\\z/D'") !== false,
    'structure token is separate, random, and 64-hex'
);
ss_check(
    ss_ordered($manage, ['is_superadmin()', 'sidebar_structure_csrf()', "'sidebar_structure_csrf_token'"])
        && strpos($manage, "render('sidebar/manage', \$data)") !== false,
    'manage generates and passes token only after superadmin gate'
);
ss_check(
    ss_ordered($guard, ["method(true) !== 'POST'", 'is_ajax_request()', 'STRUCTURE_CSRF_CI_HEADER', 'STRUCTURE_CSRF_SESSION_KEY', 'hash_equals('])
        && strpos($guard, "set_header('Allow: POST')") !== false
        && strpos($guard, 'structure_json_error(405') !== false
        && strpos($guard, 'structure_json_error(403') !== false,
    'guard enforces POST, AJAX, then dedicated CSRF with JSON 405/403'
);
ss_check(
    ss_ordered($save, [
        'is_superadmin()',
        'require_sidebar_structure_mutation_request()',
        "post('sidebar_type'",
        "post('tree_json'",
        'json_decode(',
        'trans_start()',
        'save_sidebar_structure(',
        'trans_complete()',
        'trans_status()',
        'clear_sidebar_cache_files()',
        "json_encode(['ok' => true])",
    ]),
    'save_structure preserves required authorization-to-success order'
);
ss_check(
    ss_ordered($save, ['json_decode(', "set_status_header(400)", 'trans_start()'])
        && substr_count($save, 'clear_sidebar_cache_files()') === 1,
    'invalid JSON returns 400 before transaction/cache and success clears once'
);
ss_check(
    strpos($view, 'var structureCsrfToken = <?php echo json_encode($structureCsrfToken); ?>;') !== false
        && strpos($view, '$.post(saveUrl') === false
        && ss_ordered($view, ['url: saveUrl', "type: 'POST'", "'X-Sidebar-Structure-CSRF': structureCsrfToken", 'tree_json: JSON.stringify(payload)']),
    'manage save UI uses AJAX POST with dedicated header and existing payload'
);

$token = str_repeat('a', 64);
$blocked = [
    'non-superadmin' => ss_simulate(false, 'POST', true, $token, $token, true),
    'GET' => ss_simulate(true, 'GET', true, $token, $token, true),
    'non-AJAX' => ss_simulate(true, 'POST', false, $token, $token, true),
    'bad token' => ss_simulate(true, 'POST', true, str_repeat('b', 64), $token, true),
];
foreach ($blocked as $label => $trace) {
    ss_check(
        in_array($trace['status'], [403, 404, 405], true)
            && $trace['payload'] === 0 && $trace['trans'] === 0 && $trace['model'] === 0 && $trace['cache'] === 0,
        $label . ' stops before payload/transaction/model/cache'
    );
}
$invalidJson = ss_simulate(true, 'POST', true, $token, $token, false);
ss_check($invalidJson['status'] === 400 && $invalidJson['trans'] === 0 && $invalidJson['cache'] === 0, 'invalid JSON stops before transaction/cache');
$valid = ss_simulate(true, 'POST', true, $token, $token, true);
ss_check($valid === ['status' => 200, 'payload' => 1, 'decode' => 1, 'trans' => 1, 'model' => 1, 'cache' => 1], 'valid request reaches each mutation stage once');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' sidebar structure smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' sidebar structure mutation CSRF smoke checks passed.' . PHP_EOL;
