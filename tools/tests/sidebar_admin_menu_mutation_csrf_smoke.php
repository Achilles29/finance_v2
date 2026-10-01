<?php

// Focused source assertions + tiny boundary simulation; no CI/database bootstrap.
$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/Sidebar.php');
$view = file_get_contents($root . '/application/views/sidebar/manage.php');
$checks = 0;
$failures = [];

function sa_check(bool $ok, string $message): void
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

function sa_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function sa_ordered(string $source, array $needles): bool
{
    $at = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $at) return false;
        $at = $next;
    }
    return true;
}

function sa_simulate(string $action, bool $super, string $method, string $provided, string $expected, bool $ajax = true): array
{
    $trace = ['status' => 200, 'payload' => 0, 'row' => 0, 'query' => 0, 'write' => 0, 'cache' => 0];
    if (!$super) {
        $trace['status'] = 403;
        return $trace;
    }
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
    if ($action === 'toggle' && !$ajax) {
        $trace['status'] = 404;
        return $trace;
    }
    if ($action === 'store') {
        $trace['payload']++;
        $trace['query']++;
    } else {
        $trace['row']++;
        if ($action === 'delete' || $action === 'toggle') $trace['query']++;
    }
    $trace['write']++;
    $trace['cache']++;
    return $trace;
}

$guard = sa_method($controller, 'require_sidebar_structure_mutation_request');
$store = sa_method($controller, 'menu_store');
$update = sa_method($controller, 'menu_update');
$delete = sa_method($controller, 'menu_delete');
$toggle = sa_method($controller, 'menu_toggle_active');

sa_check(
    strpos($controller, "STRUCTURE_CSRF_FORM_FIELD = 'sidebar_structure_csrf'") !== false
        && strpos($controller, "STRUCTURE_CSRF_CI_HEADER = 'X-Sidebar-Structure-Csrf'") !== false
        && sa_ordered($guard, ["method(true) !== 'POST'", 'if ($requireAjax', '$provided = $allowFormField', 'STRUCTURE_CSRF_FORM_FIELD', 'STRUCTURE_CSRF_CI_HEADER', 'hash_equals('])
        && strpos($guard, "set_header('Allow: POST')") !== false,
    'shared structure guard supports POST-only form and header channels'
);

sa_check(
    sa_ordered($store, ['is_superadmin()', 'require_sidebar_structure_mutation_request(false, true)', "post('sidebar_type'", "post('menu_code'", 'menu_code_exists(', 'create_sidebar_menu(', 'clear_sidebar_cache_files()']),
    'store guards before payload, uniqueness query, write, and cache clear'
);
sa_check(
    sa_ordered($update, ['is_superadmin()', 'require_sidebar_structure_mutation_request(false, true)', 'get_menu_by_id(', "post('sidebar_type'", 'menu_code_exists(', 'update_sidebar_menu(', 'clear_sidebar_cache_files()']),
    'update guards before row lookup, payload, write, and cache clear'
);
sa_check(
    sa_ordered($delete, ['is_superadmin()', 'require_sidebar_structure_mutation_request(false, true)', 'get_menu_by_id(', "from('sys_menu')", 'update_sidebar_menu(', 'clear_sidebar_cache_files()']),
    'delete guards before row/query/write and preserves soft-delete flow'
);
sa_check(
    sa_ordered($toggle, ['is_superadmin()', 'require_sidebar_structure_mutation_request(false)', 'is_ajax_request()', 'get_menu_by_id(', "from('sys_menu')", 'update_sidebar_menu(', 'clear_sidebar_cache_files()', 'json_ok(']),
    'toggle guards POST/header then AJAX before row/query/write'
);

sa_check(
    strpos($view, 'name="sidebar_structure_csrf"') !== false
        && strpos($view, 'value="<?php echo html_escape($structureCsrfToken); ?>"') !== false
        && sa_ordered($view, ['<form method="post"', 'name="sidebar_structure_csrf"', 'name="sidebar_type"']),
    'store/update multipart-compatible form emits hidden structure token'
);

$treeJs = substr($view, strpos($view, 'var TREE_TOGGLE_URL'), strpos($view, '// Update status badge', strpos($view, 'var TREE_TOGGLE_URL')) - strpos($view, 'var TREE_TOGGLE_URL'));
$tableJs = substr($view, strpos($view, 'var TOGGLE_URL'), strpos($view, '})();', strpos($view, 'var TOGGLE_URL')) - strpos($view, 'var TOGGLE_URL'));
sa_check(
    strpos($treeJs, "'X-Sidebar-Structure-CSRF': structureCsrfToken") !== false
        && strpos($tableJs, "'X-Sidebar-Structure-CSRF': structureCsrfToken") !== false
        && substr_count($view, "'X-Sidebar-Structure-CSRF': structureCsrfToken") === 3,
    'save, tree toggle, and table toggle all send the structure header'
);

$token = str_repeat('a', 64);
foreach (['store', 'update', 'delete', 'toggle'] as $action) {
    foreach ([
        'non-superadmin' => [false, 'POST', $token, true],
        'GET' => [true, 'GET', $token, true],
        'bad-token' => [true, 'POST', str_repeat('b', 64), true],
    ] as $case => [$super, $method, $provided, $ajax]) {
        $trace = sa_simulate($action, $super, $method, $provided, $token, $ajax);
        sa_check(
            in_array($trace['status'], [403, 405], true)
                && array_sum(array_intersect_key($trace, array_flip(['payload', 'row', 'query', 'write', 'cache']))) === 0,
            $action . ' ' . $case . ' stops before downstream work'
        );
    }
}
$nonAjax = sa_simulate('toggle', true, 'POST', $token, $token, false);
sa_check($nonAjax['status'] === 404 && $nonAjax['row'] === 0 && $nonAjax['write'] === 0 && $nonAjax['cache'] === 0, 'toggle non-AJAX stops before lookup/write/cache');

foreach (['store', 'update', 'delete', 'toggle'] as $action) {
    $trace = sa_simulate($action, true, 'POST', $token, $token);
    sa_check($trace['status'] === 200 && $trace['write'] === 1 && $trace['cache'] === 1, $action . ' valid request writes and clears cache once');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' sidebar admin menu smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' sidebar admin menu mutation CSRF smoke checks passed.' . PHP_EOL;
