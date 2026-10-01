<?php

declare(strict_types=1);

/** Real CI query builder/models on isolated SQLite fixtures; --staging uses SELECT-only MySQL. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
define('BASEPATH', $root . '/system/');
define('APPPATH', $root . '/application/');
define('ENVIRONMENT', getenv('CI_ENV') ?: 'production');
function log_message($level, $message): void {
    if ($level === 'error') throw new RuntimeException('Database operation failed (details hidden).');
}
function is_php($version): bool { return version_compare(PHP_VERSION, $version, '>='); }
function base_url($uri = ''): string { return '/' . ltrim($uri, '/'); }
final class AccessAuditHttpError extends RuntimeException {}
function show_error($message = '', $status = 500): void { throw new AccessAuditHttpError('', $status); }
function show_404(): void { throw new AccessAuditHttpError('', 404); }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
require BASEPATH . 'core/Model.php';
require BASEPATH . 'database/DB.php';
foreach (['Auth_model', 'Role_model', 'Menu_model', 'Access_audit_model'] as $modelName) {
    require APPPATH . 'models/' . $modelName . '.php';
    $context->$modelName = new $modelName();
}
$context->load = new class {
    public int $calls = 0;
    public function model($models): void {
        $this->calls++;
        foreach ((array)$models as $name) {
            if (!isset(get_instance()->$name)) throw new RuntimeException('Unexpected model load.');
        }
    }
};
$context->session = new class {
    public function __call($name, $arguments) { throw new RuntimeException('Simulator must not access or change session.'); }
};
$staging = in_array('--staging', $argv, true);
if ($staging) {
    $db = []; $active_group = 'default';
    require APPPATH . 'config/database.php';
    if (is_file(APPPATH . 'config/' . ENVIRONMENT . '/database.php')) {
        require APPPATH . 'config/' . ENVIRONMENT . '/database.php';
    }
    $settings = $db[$active_group] ?? [];
    if (($settings['dbdriver'] ?? '') !== 'mysqli') throw new RuntimeException('Staging probe requires mysqli.');
    foreach (['hostname', 'username', 'database'] as $required) {
        if (trim((string)($settings[$required] ?? '')) === '') {
            fwrite(STDERR, 'Staging database configuration unavailable. Set CI_ENV=staging or the deployment secret environment.' . PHP_EOL);
            exit(2);
        }
    }
    $settings['db_debug'] = false;
    $settings['save_queries'] = true;
    $settings['pconnect'] = false;
    $settings['cache_on'] = false;
    try {
        $context->db = @DB($settings, true);
    } catch (Throwable $error) {
        fwrite(STDERR, 'Staging connection failed (credentials hidden).' . PHP_EOL);
        exit(2);
    }
    if (!$context->db->simple_query('START TRANSACTION READ ONLY')) throw new RuntimeException('Read-only transaction unavailable.');
    register_shutdown_function(static function (): void { get_instance()->db->simple_query('ROLLBACK'); });
} else {
    $context->db = DB(['dbdriver' => 'sqlite3', 'database' => ':memory:', 'db_debug' => false, 'save_queries' => true], true);
    $schema = [
        'CREATE TABLE auth_user (id INTEGER PRIMARY KEY, username TEXT, is_active INTEGER)',
        'CREATE TABLE org_division (id INTEGER PRIMARY KEY, division_name TEXT)',
        'CREATE TABLE auth_role (id INTEGER PRIMARY KEY, role_code TEXT, role_name TEXT, division_scope_id INTEGER, is_active INTEGER)',
        'CREATE TABLE auth_user_role (user_id INTEGER, role_id INTEGER)',
        'CREATE TABLE sys_page (id INTEGER PRIMARY KEY, page_code TEXT, page_name TEXT, module TEXT, is_active INTEGER)',
        'CREATE TABLE auth_role_permission (role_id INTEGER, page_id INTEGER, can_view INTEGER, can_create INTEGER, can_edit INTEGER, can_delete INTEGER, can_export INTEGER)',
        'CREATE TABLE auth_user_permission_override (user_id INTEGER, page_id INTEGER, override_type TEXT, can_view INTEGER, can_create INTEGER, can_edit INTEGER, can_delete INTEGER, can_export INTEGER)',
        'CREATE TABLE sys_menu (id INTEGER PRIMARY KEY, parent_id INTEGER, menu_code TEXT, menu_label TEXT, icon TEXT, url TEXT, page_id INTEGER, sort_order INTEGER, is_active INTEGER, sidebar_type TEXT)',
        "INSERT INTO auth_user VALUES (1, '<script>alert(1)</script>', 1), (2, 'inactive fixture', 0), (3, 'admin fixture', 1)",
        "INSERT INTO org_division VALUES (10, 'Bar'), (20, 'Kitchen')",
        "INSERT INTO auth_role VALUES (1, 'STAFF', 'Staff', NULL, 1), (2, 'BARISTA', 'Barista', 10, 1), (3, 'CHEF', 'Chef', 20, 1), (4, 'SUPERADMIN', 'Superadmin', NULL, 1), (5, 'INACTIVE', 'Inactive', 20, 0)",
        'INSERT INTO auth_user_role VALUES (1, 1), (1, 2), (2, 1), (3, 4)',
        "INSERT INTO sys_page VALUES (1, 'self.view', 'Pribadi', 'staff', 1), (2, 'pos.cashier', 'Kasir', 'pos', 1), (3, 'legacy.hidden', 'Lama', 'legacy', 0)",
        'INSERT INTO auth_role_permission VALUES (1, 1, 1, 0, 0, 0, 0), (2, 2, 1, 1, 1, 0, 0), (3, 2, 1, 0, 0, 0, 1), (5, 1, 1, 1, 1, 1, 1), (1, 3, 1, 1, 1, 1, 1)',
        "INSERT INTO auth_user_permission_override VALUES (1, 2, 'GRANT', 0, 0, 0, 0, 1), (1, 2, 'REVOKE', 0, 0, 1, 0, 1), (1, 1, 'GRANT', 0, 1, 0, 0, 0), (1, 3, 'GRANT', 1, 1, 1, 1, 1)",
        "INSERT INTO sys_menu VALUES (1, NULL, 'group', 'Operasional', '', '#', NULL, 1, 1, 'MAIN'), (2, 1, 'cashier', 'Kasir', '', 'pos/cashier', 2, 1, 1, 'MAIN'), (3, NULL, 'self', 'Pribadi', '', 'self', 1, 2, 1, 'MY'), (4, NULL, 'hidden', 'Lama', '', 'legacy', 3, 3, 1, 'MAIN')",
    ];
    foreach ($schema as $sql) {
        if (!$context->db->query($sql)) throw new RuntimeException('Fixture setup failed.');
    }
    $context->db->simple_query('PRAGMA query_only = ON');
}
$queryStart = count($context->db->queries);
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
};
$audit = $context->Access_audit_model;
$auth = $context->Auth_model;

if ($staging) {
    $users = $context->db->select('id, is_active')->from('auth_user')->get()->result_array();
    $check(count($users) > 0, 'staging has users to inspect');
    $pageCount = 0;
    foreach ($users as $user) {
        $id = (int)$user['id'];
        $live = $audit->report($id);
        $preview = $audit->report($id, $live['assigned_ids']);
        $check($live['rows'] === $preview['rows'] && $preview['changes'] === [] && $live['scope'] === $preview['scope'], 'staging user #' . $id . ' real assignments equal preview');
        $empty = $audit->report($id, []);
        $check(!$empty['usable'] && $empty['menus'] === [] && $empty['scope']['state'] === 'NONE', 'staging user #' . $id . ' no-role preview fails closed');
        $pageCount = count($live['rows']);
    }
    echo 'STAGING users=' . count($users) . ' active_pages=' . $pageCount . ' database_writes=0' . PHP_EOL;
} else {
    $live = $audit->report(1);
    $check($live['usable'] && $live['scope'] === ['state' => 'SINGLE', 'division_id' => 10], 'STAFF + BARISTA keeps one operational division');
    $flags = array_column($live['rows'], 'flags', 'page_code');
    $check($flags['self.view']['can_view'] && $flags['pos.cashier']['can_create'], 'live role permissions are unioned');
    $check($flags['self.view']['can_create'] && !$flags['pos.cashier']['can_edit'] && !$flags['pos.cashier']['can_export'], 'GRANT applied then REVOKE wins');
    $check(count($live['overrides']) === 2 && count($live['menus']) === 2, 'override deltas and visible sidebar use real resolvers');
    $check(!isset($flags['legacy.hidden']), 'inactive pages and overrides excluded');
    $same = $audit->report(1, [2, 1, 2]);
    $check($same['changes'] === [] && $same['rows'] === $live['rows'], 'same assignments are equivalent; duplicate selection deduplicated');
    $staff = $audit->report(1, [1]);
    $check($staff['scope']['state'] === 'GLOBAL' && count($staff['changes']) > 0, 'preview removes actual BARISTA without changing assignments');
    $conflict = $audit->report(1, [1, 2, 3]);
    $check($conflict['scope']['state'] === 'AMBIGUOUS' && !$conflict['usable'] && $conflict['menus'] === [], 'two divisions block all usable access');
    $empty = $audit->report(1, []);
    $check($empty['selected_ids'] === [] && $empty['scope']['state'] === 'NONE' && !$empty['usable'], 'empty selection is not replaced with live assignments');
    $check($audit->report(1, [1, 2, 5])['rows'] === $live['rows'], 'inactive role contributes neither permission nor division conflict');
    $check(!$audit->report(2, [4])['usable'], 'inactive user stays blocked even when previewing superadmin');
    $check($audit->report(1, [4])['is_superadmin'] && $auth->preview_permissions(1, [4]) === ['__superadmin__' => true], 'active superadmin preview matches live bypass');
    $check(!$audit->report(3, [1])['is_superadmin'], 'preview cannot inherit actual superadmin role');
    $check($audit->report(1) === $live && $audit->report(999) === null, 'simulation leaves live report unchanged and missing user is bounded');
    foreach ([[0], ['2 OR 1=1'], [[1]], [999], ['1.1']] as $invalid) {
        $rejected = false;
        try { $audit->report(1, $invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
        $check($rejected, 'invalid or unknown role selection rejected');
    }
    $pages = $live['rows']; $roles = $live['roles'];
    $baseline = ['schema_version' => 1, 'approved' => true, 'roles' => ['STAFF' => ['self.view' => ['can_view' => 1]]]];
    $actual = [['role_code' => 'STAFF', 'page_code' => 'self.view', 'can_view' => 1]];
    $check(Access_audit_model::compare_baseline(null, $roles, $pages, $actual)['status'] === 'UNCONFIGURED', 'missing/unapproved baseline is not zero drift');
    $check(Access_audit_model::compare_baseline(array_replace($baseline, ['approved' => false]), $roles, $pages, $actual)['status'] === 'UNCONFIGURED', 'approval cannot be inferred from role contents');
    $matched = Access_audit_model::compare_baseline($baseline, $roles, $pages, $actual);
    $check($matched['status'] === 'READY' && $matched['rows'] === [] && in_array('BARISTA', $matched['unmanaged_roles'], true), 'baseline compares only explicitly selected roles');
    $actual[0]['can_delete'] = 1;
    $extra = Access_audit_model::compare_baseline($baseline, $roles, $pages, $actual);
    $check(count($extra['rows']) === 1 && $extra['rows'][0]['actual'] && !$extra['rows'][0]['expected'], 'extra grant produces precise action drift');
    $missing = Access_audit_model::compare_baseline($baseline, $roles, $pages, []);
    $check(count($missing['rows']) === 1 && $missing['rows'][0]['expected'] && !$missing['rows'][0]['actual'], 'missing grant detected');
    $bad = $baseline; $bad['roles']['STAFF']['self.view']['can_edti'] = 1;
    $check(Access_audit_model::compare_baseline($bad, $roles, $pages, $actual)['status'] === 'INVALID', 'unknown action cannot silently mean no drift');
    $bad = $baseline; $bad['roles']['STAFF']['self.view']['can_view'] = 'yes';
    $check(Access_audit_model::compare_baseline($bad, $roles, $pages, $actual)['status'] === 'INVALID', 'invalid flag values fail validation');
    $check(count(Access_audit_model::compare_baseline($baseline, [], [], [])['rows']) === 2, 'missing role and missing page reported');
    $superBaseline = $baseline; $superBaseline['roles'] = ['SUPERADMIN' => []];
    $check(count(Access_audit_model::compare_baseline($superBaseline, $roles, $pages, [])['rows']) === 10, 'superadmin bypass not hidden by absent stored flags');

    // Exercise the real endpoint body, without constructing a web session.
    class MY_Controller {
        public array $allowedPages = [];
        public array $guards = [];
        public array $rendered = [];
        public function __get($key) { return get_instance()->$key; }
        protected function require_permission($page, $action = 'view'): void {
            $this->guards[] = [$page, $action];
            if (!in_array($page, $this->allowedPages, true)) show_error('', 403);
        }
        protected function render($view, $data = []): void { $this->rendered = $data; }
    }
    require APPPATH . 'controllers/Users.php';
    $context->input = new class {
        public array $params = [];
        public string $verb = 'GET';
        public function method($upper = false): string { return $this->verb; }
        public function get($key = null, $xss = false) { return $key === null ? $this->params : ($this->params[$key] ?? null); }
    };
    $context->output = new class {
        public array $headers = [];
        public function set_header($header): self { $this->headers[] = $header; return $this; }
    };
    $context->User_model = new class {
        public function get_all(): array { return get_instance()->db->select('id, username, is_active')->from('auth_user')->get()->result_array(); }
    };
    $controller = (new ReflectionClass(Users::class))->newInstanceWithoutConstructor();
    foreach ([[], ['auth.users.index']] as $allowed) {
        $controller->allowedPages = $allowed;
        $beforeQueries = count($context->db->queries);
        $status = 0;
        try { $controller->access_audit(1); } catch (AccessAuditHttpError $e) { $status = $e->getCode(); }
        $check($status === 403 && count($context->db->queries) === $beforeQueries, 'both permissions required before reading user report');
    }
    $controller->allowedPages = ['auth.users.index', 'auth.users.permissions'];
    $context->input->verb = 'POST'; $status = 0;
    try { $controller->access_audit(1); } catch (AccessAuditHttpError $e) { $status = $e->getCode(); }
    $check($status === 405, 'simulation rejects state-changing HTTP methods');
    $context->input->verb = 'GET';
    $context->input->params = ['tab' => 'baseline', 'module' => 'pos'];
    $controller->access_audit(1);
    $check($controller->rendered['module'] === '' && in_array('Cache-Control: private, no-store', $context->output->headers, true), 'baseline ignores stale module filter; access report is no-store');
    $context->input->params = ['preview' => '1', 'role_ids' => '2']; $status = 0;
    try { $controller->access_audit(1); } catch (AccessAuditHttpError $e) { $status = $e->getCode(); }
    $check($status === 400, 'endpoint rejects scalar role list');
    $context->input->params = ['preview' => '1', 'tab' => 'changes'];
    $controller->access_audit(1);
    $check($controller->rendered['report']['selected_ids'] === [], 'unchecked role form preserves explicit empty preview');
    $context->input->params = ['tab' => ['invalid'], 'q' => ['invalid'], 'module' => ['invalid']];
    $controller->access_audit(1);
    $check($controller->rendered['tab'] === 'access' && $controller->rendered['search'] === '', 'malformed filters bounded without warnings');

    $render = static function (array $data): string {
        extract($data, EXTR_SKIP);
        ob_start();
        include APPPATH . 'views/users/access_audit.php';
        return (string)ob_get_clean();
    };
    foreach (['access', 'roles', 'changes', 'baseline'] as $tab) {
        $data = $controller->rendered;
        $data['tab'] = $tab;
        $data['report'] = $tab === 'changes' ? $conflict : $live;
        $html = $render($data);
        $check(strpos($html, '<script>alert(1)</script>') === false && strpos($html, '&lt;script&gt;') !== false, 'tab ' . $tab . ' renders escaped user content');
        if ($tab === 'changes') $check(strpos($html, 'Perbandingan scope:') !== false, 'scope-only changes visible without permission flag changes');
    }
    $data['report'] = null;
    $check(strpos($render($data), 'Mulai dengan memilih pengguna') !== false, 'empty selection has clear initial state');
    $data['report'] = $live;
    $data['tab'] = 'access';
    $data['report']['rows'] = array_fill(0, 60, $live['rows'][0]);
    $data['page'] = 2;
    $check(substr_count($render($data), '<td><strong>') === 25, 'matrix paginates to 25 rows');
}
$executed = array_slice($context->db->queries, $queryStart);
$check($executed !== [] && array_filter($executed, static function ($sql): bool { return preg_match('/^\s*SELECT\b/i', $sql) !== 1; }) === [], 'all report, preview, baseline, and controller SQL is SELECT-only');
echo 'ACCESS_SIMULATOR ' . ($staging ? 'STAGING ' : '') . 'PASS checks=' . $checks . PHP_EOL;
