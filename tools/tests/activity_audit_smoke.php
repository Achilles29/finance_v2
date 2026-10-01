<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$base = (string)file_get_contents($root . '/application/core/MY_Controller.php');
$controller = (string)file_get_contents($root . '/application/controllers/Activity_audit.php');
$model = (string)file_get_contents($root . '/application/models/Activity_audit_model.php');
$view = (string)file_get_contents($root . '/application/views/system/activity_audit_index.php');
$route = (string)file_get_contents($root . '/application/config/routes.php');
$migration = (string)file_get_contents($root . '/sql/2026-09-06e_activity_audit_foundation.sql');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$check($base !== '' && $controller !== '' && $model !== '' && $view !== '' && $migration !== '', 'activity audit source files are readable');
$check(strpos($route, "\$route['system/activity-audit']                 = 'activity_audit/index'") !== false, 'activity audit has an explicit canonical route');
$check(strpos($controller, "private const PAGE_CODE = 'system.activity_audit.index'") !== false && strpos($controller, 'require_permission(self::PAGE_CODE, \'view\')') !== false, 'activity registry is protected by a dedicated RBAC page permission');
$check(substr_count($base, "record_page_access((string)(\$data['active_menu'] ?? ''))") >= 2 && strpos($base, "insert('aud_access_event'") !== false, 'authenticated standard and cashier pages are recorded centrally');
$check(strpos($base, "strtoupper((string)\$this->input->method(true)) !== 'GET'") !== false && strpos($base, '$this->input->is_ajax_request()') !== false, 'page event recorder excludes mutations and AJAX/API traffic');
$check(strpos($base, 'Tidak merekam query string, request body, password, token') !== false && strpos($base, 'uri_string()') !== false, 'page event recorder stores route metadata without request query/body');
$check(strpos($model, "FROM aud_access_event e") !== false && strpos($model, "FROM auth_session_log s") !== false && strpos($model, "FROM aud_transaction_log t") !== false, 'registry combines page access, login, and transaction sources');
$check(strpos($model, 'before_payload') === false && strpos($model, 'after_payload') === false, 'registry never selects transaction payload snapshots');
$check(strpos($model, 't.source_ip') !== false && strpos($model, 'SELECT s.user_agent') !== false && strpos($model, 'MATCHED_SESSION') !== false, 'transactions retain source IP and only infer device from a matching login session');
$check(strpos($migration, 'CREATE TABLE IF NOT EXISTS `aud_access_event`') !== false && strpos($migration, 'datetime(6)') !== false && strpos($migration, 'fk_aud_access_event_session') !== false, 'activity schema is timestamped, indexed, and linked to the login session');
$check(strpos($migration, "'system.activity_audit.index'") !== false && strpos($migration, "'grp.system'") !== false && strpos($migration, "role.role_code = 'SUPERADMIN'") !== false && strpos($migration, 'SELECT role.id, page.id, 1, 1, 1, 1, 1, NOW()') !== false, 'migration registers sidebar menu and preserves full default access only for SUPERADMIN');
$check(strpos($view, 'Akses halaman mulai tercatat sejak fitur ini aktif') !== false && strpos($view, 'Tidak menyimpan password, token') !== false, 'UI explains forward-only coverage and safe metadata boundary');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " activity-audit check(s) failed.\n");
    exit(1);
}
echo 'All ' . $checks . " activity-audit checks passed.\n";
