<?php

// Focused table-driven source checks and boundary simulation; no CI/DB bootstrap.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/System_tools.php');
$dbtoolsView = file_get_contents($root . '/application/views/system/dbtools.php');
$settingsView = file_get_contents($root . '/application/views/system/settings.php');
$checks = 0;
$failures = [];

function stm_check(bool $ok, string $message): void
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

function stm_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function stm_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

function stm_simulate(bool $editor, string $method, string $provided, string $expected): array
{
    $trace = ['status' => 200, 'allow' => false, 'time_limit' => 0, 'payload' => 0, 'query' => 0, 'file' => 0, 'script' => 0];
    if (!$editor) {
        $trace['status'] = 403;
        return $trace;
    }
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        $trace['allow'] = true;
        return $trace;
    }
    if (
        preg_match('/\A[0-9a-fA-F]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-fA-F]{64}\z/D', $expected) !== 1
        || !hash_equals($expected, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['time_limit']++;
    $trace['payload']++;
    $trace['query']++;
    $trace['file']++;
    $trace['script']++;
    return $trace;
}

$generator = stm_method($controller, 'system_tools_mutation_csrf');
$guard = stm_method($controller, 'require_system_tools_mutation_csrf');
$index = stm_method($controller, 'index');
$settings = stm_method($controller, 'settings');

stm_check(
    strpos($controller, "SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY = 'system_tools_mutation_csrf'") !== false
        && strpos($controller, "SYSTEM_TOOLS_MUTATION_CSRF_HEADER = 'X-System-Tools-CSRF'") !== false
        && strpos($controller, "SYSTEM_TOOLS_MUTATION_CSRF_CI_HEADER = 'X-System-Tools-Csrf'") !== false
        && strpos($generator, 'bin2hex(random_bytes(32))') !== false
        && strpos($generator, "preg_match('/\\A[0-9a-fA-F]{64}\\z/D'") !== false,
    'dedicated System Tools token is generated as random 64-hex'
);

foreach (['index' => $index, 'settings' => $settings] as $name => $block) {
    stm_check(
        stm_ordered($block, ["require_permission(self::PAGE_CODE, 'view')", 'SENSITIVE_READ_ACTION', 'system_tools_mutation_csrf()', '$financeRoot', "render('system/", "'system_tools_mutation_csrf_token'"]),
        $name . ' generates and passes token after view RBAC'
    );
}

stm_check(
    stm_ordered($guard, ["method(true)) !== 'POST'", "set_header('Allow: POST')", 'json_error(', 'SYSTEM_TOOLS_MUTATION_CSRF_CI_HEADER', 'SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY', 'hash_equals('])
        && strpos($guard, "json_error('Permintaan System Tools tidak valid.', 405)") !== false
        && strpos($guard, "json_error('Permintaan System Tools tidak valid.', 403)") !== false
        && strpos($guard, 'SYSTEM_TOOLS_MUTATION_CSRF_HEADER') === false,
    'guard uses canonical CI header and generic JSON 405/403 responses'
);

$writers = [
    'settings_save' => ['request_payload()', 'trans_begin()', '_writeEnvFile()'],
    'action_run_backup' => ["_runScript('backup', 'backup_full')"],
    'action_test_db' => ['request_payload()', 'new PDO('],
    'action_apply_mysql_config' => ['request_payload()', '$this->db->db_debug', 'file_put_contents('],
    'action_setup_master' => ['request_payload()', '$this->db->query('],
    'action_initial_sync' => ['set_time_limit(300)', "_cfg('tunnel.enabled'", 'request_payload()', 'new PDO('],
    'action_failover' => ['request_payload()', "\$this->db->query('STOP SLAVE')", 'file_put_contents('],
    'action_restart_replication' => ['request_payload()', "_cfg('repl.master_host'", 'new PDO(', '$this->db->query('],
];
foreach ($writers as $name => $downstream) {
    $block = stm_method($controller, $name);
    stm_check(
        stm_ordered($block, array_merge(["require_permission(self::PAGE_CODE, 'edit')", 'SENSITIVE_READ_ACTION', 'require_system_tools_mutation_csrf()'], $downstream))
            && substr_count($block, 'require_system_tools_mutation_csrf()') === 1,
        $name . ' guards before time-limit/payload/query/file/script work'
    );
}

foreach (['action_list_tables', 'action_check_replication', 'backup_status'] as $readMethod) {
    stm_check(strpos(stm_method($controller, $readMethod), 'require_system_tools_mutation_csrf') === false, $readMethod . ' read contract remains outside mutation guard');
}

foreach (['dbtools' => $dbtoolsView, 'settings' => $settingsView] as $name => $view) {
    $postNeedle = $name === 'dbtools' ? 'async function post(' : 'async function apiPost(';
    $getNeedle = $name === 'dbtools' ? 'async function get(' : 'async function apiGet(';
    $postAt = strpos($view, $postNeedle);
    $getAt = strpos($view, $getNeedle);
    $postBlock = $postAt === false ? '' : substr($view, $postAt, 1000);
    $getBlock = $getAt === false ? '' : substr($view, $getAt, 700);
    stm_check(
        strpos($view, 'system_tools_mutation_csrf_token') !== false
            && strpos($view, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false
            && strpos($postBlock, 'X-System-Tools-CSRF') !== false
            && strpos($getBlock, 'X-System-Tools-CSRF') === false
            && substr_count($view, 'X-System-Tools-CSRF') === 1,
        $name . ' POST wrapper sends dedicated header without changing GET wrapper'
    );
}

$token = str_repeat('a', 64);
$rejections = [
    'view-only' => [false, 'POST', $token, 403, false],
    'GET' => [true, 'GET', $token, 405, true],
    'PUT' => [true, 'PUT', $token, 405, true],
    'PATCH' => [true, 'PATCH', $token, 405, true],
    'missing token' => [true, 'POST', '', 403, false],
    'malformed token' => [true, 'POST', 'nope', 403, false],
    'wrong token' => [true, 'POST', str_repeat('b', 64), 403, false],
];
foreach (array_keys($writers) as $writer) {
    foreach ($rejections as $case => [$editor, $method, $provided, $status, $allow]) {
        $trace = stm_simulate($editor, $method, $provided, $token);
        $downstream = $trace['time_limit'] + $trace['payload'] + $trace['query'] + $trace['file'] + $trace['script'];
        stm_check($trace['status'] === $status && $trace['allow'] === $allow && $downstream === 0, $writer . ' ' . $case . ' stops before downstream work');
    }
}
$valid = stm_simulate(true, 'POST', $token, $token);
stm_check($valid['status'] === 200 && !$valid['allow'] && $valid['payload'] === 1 && $valid['query'] === 1, 'valid POST reaches downstream once');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' System Tools mutation CSRF smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' System Tools mutation CSRF smoke checks passed.' . PHP_EOL;
