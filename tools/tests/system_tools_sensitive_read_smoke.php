<?php

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root . '/application/controllers/System_tools.php');
$limited = (string) file_get_contents($root . '/application/views/system/dbtools_limited.php');
$dbtools = (string) file_get_contents($root . '/application/views/system/dbtools.php');
$settings = (string) file_get_contents($root . '/application/views/system/settings.php');
$checks = 0;
$failures = [];

$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$method = static function (string $name) use ($controller): string {
    $start = strpos($controller, 'function ' . $name . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $controller, $next, PREG_OFFSET_CAPTURE, $start + 1);
    return substr($controller, $start, isset($next[0][1]) ? (int)$next[0][1] - $start : null);
};

$check(
    strpos($controller, "private const SENSITIVE_READ_ACTION = 'export';") !== false,
    'existing Export action is the separate sensitive-read permission'
);
foreach (['action_list_tables','action_check_replication','action_compare_data','backup_status','replication_status'] as $name) {
    $block = $method($name);
    $check(
        strpos($block, "require_permission(self::PAGE_CODE, 'view')") !== false
            && strpos($block, 'require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION)') !== false,
        $name . ' requires both page view and sensitive-read permission'
    );
}
foreach (['settings_save','action_run_backup','action_test_db','action_apply_mysql_config','action_setup_master','action_initial_sync','action_failover','action_restart_replication'] as $name) {
    $block = $method($name);
    $check(
        strpos($block, "require_permission(self::PAGE_CODE, 'edit')") !== false
            && strpos($block, 'require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION)') !== false,
        $name . ' requires edit plus sensitive-read permission'
    );
}
$check(
    strpos($method('index'), 'render_limited_page()') !== false
        && strpos($method('settings'), 'render_limited_page()') !== false
        && strpos($limited, 'finance_root') === false
        && strpos($limited, 'recent_dumps') === false
        && strpos($limited, 'repl_status') === false,
    'view-only users receive a limited page without paths, dump metadata, or replication details'
);
$visibleConfig = $method('visible_config');
$check(
    strpos($visibleConfig, "'backup.db_pass'") === false
        && strpos($visibleConfig, "'repl.repl_pass'") === false
        && strpos($visibleConfig, "select('config_key, config_value')") !== false
        && strpos($visibleConfig, "where_in('config_key', \$allowed)") !== false,
    'full page receives an explicit config whitelist and never receives stored passwords'
);
$check(
    strpos($dbtools, "post('dbtools/action/test-db'") !== false
        && strpos($settings, "apiPost('dbtools/action/test-db'") !== false
        && strpos($dbtools, 'action/test-db?') === false
        && strpos($settings, 'action/test-db?') === false,
    'database test sends credentials in CSRF-protected POST body instead of URL query'
);
$testDb = $method('action_test_db');
$check(
    strpos($testDb, 'require_system_tools_mutation_csrf()') !== false
        && strpos($testDb, '$this->input->get(') === false
        && strpos($testDb, '$e->getMessage()') === false,
    'database test rejects non-CSRF requests and returns no raw connection exception'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' sensitive-read check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' System Tools sensitive-read checks passed.' . PHP_EOL;
