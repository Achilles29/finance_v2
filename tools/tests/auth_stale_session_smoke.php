<?php
declare(strict_types=1);

// Real controller methods, in-memory persistence/session doubles only.
// No application bootstrap, network, live session files, or database access.
define('BASEPATH', __DIR__);

class CI_Controller
{
    public $db;
    public $session;
    public $input;
    public $output;
    public $router;
    public $load;
    public $Business_profile_model;
}

final class StaleSessionResponse extends RuntimeException {}
function redirect($uri = '', $method = 'auto', $code = null): void
{
    throw new StaleSessionResponse((string)$uri, (int)$code);
}
function show_error($message, $status = 500, $heading = ''): void
{
    throw new StaleSessionResponse((string)$message, (int)$status);
}
function site_url($path = ''): string { return '/' . ltrim((string)$path, '/'); }
function uri_string(): string { return 'dashboard'; }
function log_message($level, $message): void { $GLOBALS['staleSessionLogs'][] = (string)$message; }

final class StaleSessionStore
{
    public bool $destroyed = false;
    public function __construct(public array $data) {}
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function flashdata($key) { return $this->data[$key] ?? null; }
    public function set_flashdata($key, $value): void { $this->data[$key] = $value; }
    public function sess_destroy(): void { $this->destroyed = true; $this->data = []; }
}

final class StaleSessionDb
{
    public bool $db_debug = true;
    public bool $queryFailure = false;
    public bool $throwOnQuery = false;
    public bool $auditTable = true;
    public int $auditError = 0;
    public bool $deleteBeforeInsert = false;
    public bool $throwOnInsert = false;
    public array $filters = [];
    public array $queries = [];
    public array $inserts = [];
    public string $table = '';
    public function __construct(public ?array $row) {}
    public function select($fields): self { return $this; }
    public function from($table): self { $this->table = $table; $this->filters = []; return $this; }
    public function join($table, $condition, $type = ''): self { return $this; }
    public function where($field, $value = null): self { $this->filters[$field] = $value; return $this; }
    public function limit($limit): self { return $this; }
    public function get()
    {
        $this->queries[] = [$this->table, $this->filters, $this->db_debug];
        if ($this->throwOnQuery) throw new RuntimeException('Synthetic query unavailable.');
        if ($this->queryFailure) return false;
        $match = $this->row !== null;
        foreach ($this->filters as $key => $value) {
            $field = substr($key, strrpos($key, '.') + 1);
            if ($value === null) {
                $match = $match && ($this->row[$field] ?? null) === null;
            } else {
                $match = $match && (string)($this->row[$field] ?? '') === (string)$value;
            }
        }
        return new class($match) {
            public function __construct(private bool $match) {}
            public function num_rows(): int { return $this->match ? 1 : 0; }
        };
    }
    public function table_exists($table): bool { return $this->auditTable; }
    public function insert($table, $data): bool
    {
        $this->inserts[] = [$table, $data, $this->db_debug];
        if ($this->deleteBeforeInsert) { $this->row = null; $this->auditError = 1452; }
        if ($this->throwOnInsert) throw new RuntimeException('Synthetic audit failure.', $this->auditError);
        return $this->auditError === 0;
    }
    public function error(): array { return ['code' => $this->auditError, 'message' => 'Synthetic only']; }
}

final class StaleSessionInput
{
    public bool $ajax = false;
    public bool $json = false;
    public bool $cli = false;
    public string $verb = 'GET';
    public array $query = [];
    public function is_ajax_request(): bool { return $this->ajax; }
    public function is_cli_request(): bool { return $this->cli; }
    public function method($upper = false): string { return $this->verb; }
    public function get_request_header($name, $clean = false): string { return $this->json ? 'application/json' : 'text/html'; }
    public function get($key, $clean = false) { return $this->query[$key] ?? null; }
    public function user_agent(): string { return 'Windows Firefox/fixture'; }
    public function ip_address(): string { return '127.0.0.1'; }
}

final class StaleSessionOutput
{
    public int $status = 200;
    public string $body = '';
    public function set_status_header($status): self { $this->status = $status; return $this; }
    public function set_content_type($type, $charset = null): self { return $this; }
    public function set_output($body): self { $this->body = $body; return $this; }
    public function _display(): void { throw new StaleSessionResponse($this->body, $this->status); }
}

require dirname(__DIR__, 2) . '/application/core/MY_Controller.php';
require dirname(__DIR__, 2) . '/application/controllers/Auth.php';

function stale_fixture(?array $row, array $sessionOverrides = []): MY_Controller
{
    $c = (new ReflectionClass(MY_Controller::class))->newInstanceWithoutConstructor();
    $c->db = new StaleSessionDb($row);
    $c->session = new StaleSessionStore(array_replace([
        'auth_user' => ['id' => 1, 'is_superadmin' => false],
        'session_log_id' => 2576,
        'user_perms' => [],
        'user_division_scope_state' => 'GLOBAL',
        'perms_staleness_checked_at' => time(),
    ], $sessionOverrides));
    $c->input = new StaleSessionInput();
    $c->output = new StaleSessionOutput();
    $c->router = new class {
        public string $controller = 'dashboard';
        public string $action = 'index';
        public function fetch_class(): string { return $this->controller; }
        public function fetch_method(): string { return $this->action; }
    };
    return $c;
}

function stale_invoke(MY_Controller $controller, string $method, array $args = []): ?StaleSessionResponse
{
    try {
        $r = new ReflectionMethod(MY_Controller::class, $method);
        $r->setAccessible(true);
        $r->invokeArgs($controller, $args);
        return null;
    } catch (StaleSessionResponse $response) {
        return $response;
    }
}

$checks = 0;
$failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) $failures[] = $message;
};
$valid = ['id' => 2576, 'user_id' => 1, 'logout_at' => null, 'is_active' => 1];

foreach (['missing parent' => null, 'different user' => array_replace($valid, ['user_id' => 2]),
    'logged out' => array_replace($valid, ['logout_at' => '2026-09-25 01:00:00']),
    'disabled user' => array_replace($valid, ['is_active' => 0])] as $label => $row) {
    foreach ([false, true] as $superadmin) {
        $c = stale_fixture($row, ['auth_user' => ['id' => 1, 'is_superadmin' => $superadmin]]);
        $response = stale_invoke($c, '_check_auth');
        $check($response !== null && $response->getMessage() === 'login?reason=session_expired'
            && $response->getCode() === 303, $label . ' redirects safely, superadmin=' . (int)$superadmin);
        $check($c->session->destroyed && $c->session->data === [], $label . ' destroys only current session');
        $check($c->db->inserts === [] && $c->db->db_debug === true, $label . ' no writer and debug restored');
    }
}
foreach ([null, 0, -1] as $id) {
    $c = stale_fixture($valid, ['session_log_id' => $id]);
    $response = stale_invoke($c, '_check_auth');
    $check($response !== null && $c->session->destroyed && $c->db->queries === [], 'missing/invalid session ID requires fresh login');
}
foreach (['ajax', 'json'] as $format) {
    $c = stale_fixture(null); $c->input->{$format} = true; $c->input->verb = 'POST';
    $response = stale_invoke($c, '_check_auth');
    $body = json_decode($c->output->body, true);
    $check($response !== null && $response->getCode() === 401 && ($body['code'] ?? '') === 'AUTH_SESSION_EXPIRED'
        && ($body['login_url'] ?? '') === '/login?reason=session_expired' && $c->session->destroyed,
        $format . ' mutation returns bounded 401 with login link before any write');
}
foreach (['queryFailure', 'throwOnQuery'] as $failure) {
    foreach ([false, true] as $ajax) {
        $c = stale_fixture($valid); $c->db->{$failure} = true; $c->input->ajax = $ajax;
        $response = stale_invoke($c, '_check_auth');
        $check($response !== null && $response->getCode() === 503 && !$c->session->destroyed
            && $c->db->db_debug === true && $c->db->inserts === [], $failure . ' is unavailable, not expired');
    }
}

$c = stale_fixture($valid);
$check(stale_invoke($c, '_check_auth') === null && !$c->session->destroyed, 'valid session remains authenticated');
$query = $c->db->queries[0] ?? [];
$check(($query[0] ?? '') === 'auth_session_log s' && ($query[1] ?? []) === [
    's.id' => 2576, 's.user_id' => 1, 's.logout_at' => null, 'u.is_active' => 1,
] && ($query[2] ?? null) === false, 'identity, open audit and active user checked with debug off');
stale_invoke($c, 'record_page_access', ['dashboard']);
$check(count($c->db->inserts) === 1 && $c->db->inserts[0][1]['session_log_id'] === 2576
    && $c->db->inserts[0][2] === false && $c->db->db_debug === true, 'valid audit linked and debug restored');
stale_invoke($c, 'record_page_access', ['dashboard']);
$check(count($c->db->inserts) === 1, 'page audit records once');

foreach ([false, true] as $throws) {
    $c = stale_fixture($valid); stale_invoke($c, '_check_auth');
    $c->db->deleteBeforeInsert = true; $c->db->throwOnInsert = $throws;
    $response = stale_invoke($c, 'record_page_access', ['dashboard']);
    $check($response !== null && $c->session->destroyed && $c->db->db_debug === true,
        'parent deletion race expires session after debug restore, exception=' . (int)$throws);
}
foreach ([false, true] as $throws) {
    $c = stale_fixture($valid); stale_invoke($c, '_check_auth');
    $c->db->auditError = 1146; $c->db->throwOnInsert = $throws;
    $response = stale_invoke($c, 'record_page_access', ['dashboard']);
    $check($response === null && !$c->session->destroyed && $c->db->db_debug === true,
        'unrelated audit failure does not log out valid user, exception=' . (int)$throws);
}
$c = stale_fixture($valid); stale_invoke($c, '_check_auth'); $c->db->auditTable = false;
$check(stale_invoke($c, 'record_page_access', ['dashboard']) === null && $c->db->inserts === []
    && $c->db->db_debug === true, 'older audit schema remains optional');
$c = stale_fixture($valid); $c->db->db_debug = false; stale_invoke($c, '_check_auth');
stale_invoke($c, 'record_page_access', ['dashboard']);
$check($c->db->db_debug === false, 'production debug setting remains false');
foreach (['cli', 'service'] as $kind) {
    $c = stale_fixture(null, ['auth_user' => null]);
    if ($kind === 'cli') $c->input->cli = true;
    else { $c->router->controller = 'whatsapp'; $c->router->action = 'api_group_command'; $c->input->verb = 'POST'; }
    $check(stale_invoke($c, '_check_auth') === null && $c->db->queries === [] && !$c->session->destroyed,
        $kind . ' existing anonymous boundary unchanged');
}

$auth = (new ReflectionClass(Auth::class))->newInstanceWithoutConstructor();
$auth->session = new StaleSessionStore([]);
$auth->input = new StaleSessionInput();
$auth->Business_profile_model = new class { public function profile(): array { return []; } };
$auth->load = new class {
    public array $data = [];
    public function view($view, $data): void { $this->data = $data; }
};
$auth->input->query['reason'] = 'session_expired'; $auth->index();
$check(str_contains((string)($auth->load->data['error_msg'] ?? ''), 'Silakan login ulang'), 'fresh login page explains expired session');
$auth->input->query['reason'] = '<script>untrusted</script>'; $auth->index();
$check(!str_contains((string)($auth->load->data['error_msg'] ?? ''), 'untrusted'), 'untrusted query reason is not reflected');
$auth->session->data['login_error'] = 'Existing login error';
$auth->input->query['reason'] = 'session_expired'; $auth->index();
$check(($auth->load->data['error_msg'] ?? '') === 'Existing login error', 'existing login error retains precedence');

foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . "\n");
echo json_encode(['status' => $failures ? 'FAIL' : 'PASS', 'checks' => $checks,
    'failures' => count($failures), 'database_accessed' => false, 'live_sessions_accessed' => false]) . "\n";
exit($failures ? 1 : 0);
