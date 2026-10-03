<?php
// Executes the real controller methods with CI adapters and temporary state only.
// No application bootstrap, database connection, remote SSH host or real key.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('FCPATH', sys_get_temp_dir() . '/finance-tunnel-test-' . bin2hex(random_bytes(12)) . '/');

class MY_Controller
{
    public $db, $output, $input, $session;
    public $allowed = true;
    public function require_permission($page, $action) {
        if (!$this->allowed) throw new RuntimeException('RBAC_DENIED');
    }
}
class TunnelTestOutput
{
    public $status = 200, $type = '', $body = '';
    public function set_status_header($value) { $this->status = $value; return $this; }
    public function set_content_type($value) { $this->type = $value; return $this; }
    public function set_output($value) { $this->body = $value; return $this; }
    public function set_header($value) { return $this; }
    public function data() { return json_decode($this->body, true); }
}
class TunnelTestSettings
{
    public $values = [], $key, $reads = 0;
    public function table_exists($name) { $this->reads++; return $name === 'sys_app_config'; }
    public function select($value) { return $this; }
    public function where($key, $value) { $this->key = $value; return $this; }
    public function get($table) { return $this; }
    public function row_array() { return ['config_value' => $this->values[$this->key] ?? '']; }
}
class TunnelTestInput
{
    public $verb = 'POST', $token;
    public function method($upper) { return $this->verb; }
    public function get_request_header($name, $clean) { return $this->token; }
}
class TunnelTestSession
{
    public $token;
    public function userdata($key) { return $this->token; }
}
function log_message($level, $message) { throw new RuntimeException('Unexpected controller exception'); }
require dirname(__DIR__, 2) . '/application/controllers/System_tools.php';
$class = new ReflectionClass(System_tools::class);
$controller = $class->newInstanceWithoutConstructor();
$controller->output = new TunnelTestOutput();
$controller->db = new TunnelTestSettings();
$controller->input = new TunnelTestInput();
$controller->session = new TunnelTestSession();
$controller->input->token = $controller->session->token = bin2hex(random_bytes(32));
$invoke = static function ($method, ...$args) use ($class, $controller) {
    $ref = $class->getMethod($method); $ref->setAccessible(true);
    return $ref->invoke($controller, ...$args);
};
$checks = 0;
$check = static function ($ok, $label) use (&$checks) {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++; echo 'PASS: ' . $label . PHP_EOL;
};
$dir = null;
$listener = null;
try {
    foreach ([
        'Permission denied (publickey,password).' => 'SSH_AUTH_REJECTED',
        'Host key verification failed.' => 'SSH_HOST_KEY_REJECTED',
        'REMOTE HOST IDENTIFICATION HAS CHANGED!' => 'SSH_HOST_KEY_REJECTED',
        'UNPROTECTED PRIVATE KEY FILE! Permission denied (publickey).' => 'SSH_KEY_UNREADABLE',
        'Connection timed out' => 'SSH_TIMEOUT',
        'Could not resolve hostname' => 'SSH_HOST_UNRESOLVED',
        'Connection refused' => 'SSH_CONNECTION_REFUSED',
        'Address already in use' => 'SSH_PORT_IN_USE',
        'administratively prohibited' => 'SSH_FORWARD_DENIED',
        'unknown failure with PRIVATE_SENTINEL /internal/path' => 'SSH_START_FAILED',
    ] as $stderr => $expected) {
        $code = $invoke('_tunnel_failure_code', ['stderr' => $stderr]);
        $check($code === $expected, 'classify ' . $expected);
        $invoke('_tunnel_error', $code);
        $json = $controller->output->data();
        $check($controller->output->status === 422 && $controller->output->type === 'application/json'
            && $json['ok'] === false && $json['code'] === $expected
            && strpos($controller->output->body, 'PRIVATE_SENTINEL') === false
            && strpos($controller->output->body, '/internal/path') === false, 'safe JSON 422 ' . $expected);
    }
    $check($invoke('_tunnel_failure_message', 'PRIVATE_SENTINEL') === '', 'unknown diagnostic code is not reflected');
    $controller->input->verb = 'GET';
    $controller->action_tunnel_start();
    $check($controller->output->status === 405 && $controller->db->reads === 0, 'GET rejected before settings or process');
    $controller->input->verb = 'POST'; $controller->input->token = '';
    $controller->action_tunnel_start();
    $check($controller->output->status === 403 && $controller->db->reads === 0, 'missing CSRF rejected before settings or process');
    $controller->input->token = $controller->session->token;
    $controller->allowed = false;
    try { $controller->action_tunnel_start(); throw new LogicException('RBAC bypass'); }
    catch (RuntimeException $e) { $check($e->getMessage() === 'RBAC_DENIED' && $controller->db->reads === 0, 'RBAC remains required'); }
    $controller->allowed = true;

    $dir = $invoke('_tunnel_state_dir');
    $check((fileperms($dir) & 0777) === 0700, 'test state directory stays private');
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$listener) throw new RuntimeException('Cannot allocate temporary loopback test port');
    $port = (int)substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
    $controller->db->values = [
        'repl.server_role' => 'SLAVE', 'tunnel.enabled' => '1',
        'tunnel.ssh_host' => '127.0.0.1', 'tunnel.ssh_port' => (string)$port,
        'tunnel.ssh_user' => 'fixture', 'tunnel.local_port' => (string)$port, 'tunnel.remote_port' => '3306',
    ];
    file_put_contents($dir . '/client_ed25519', 'NOT A REAL KEY');
    file_put_contents($dir . '/known_hosts', 'NOT A REAL HOST KEY');
    $controller->output = new TunnelTestOutput();
    $controller->action_tunnel_start();
    $check($controller->output->status === 409 && !file_exists($dir . '/ssh-start.stderr'), 'occupied port does not launch a second tunnel');
    fclose($listener); $listener = null;
    $controller->output = new TunnelTestOutput();
    $controller->action_tunnel_start();
    $data = $controller->output->data();
    $check($controller->output->status === 422 && $data['code'] === 'SSH_CONNECTION_REFUSED', 'real start on closed disposable SSH port returns JSON 422');
    $diagnostic = json_decode(file_get_contents($dir . '/start_diagnostic.json'), true);
    $check($diagnostic['error_code'] === 'SSH_CONNECTION_REFUSED' && !file_exists($dir . '/control.sock'), 'failed process has a safe checkpoint and no master socket');
    $controller->action_tunnel_status();
    $data = $controller->output->data();
    $check(!$data['running'] && !$data['local_listener'] && !$data['control_master']
        && $data['last_start']['error_code'] === 'SSH_CONNECTION_REFUSED', 'status reports inactive tunnel and classified failure');

    // Old checkpoint (as on Server 2) gets a useful explanation without modifying it.
    $legacy = json_encode(['stage' => 'ssh_start_failed', 'at' => '2026-10-03T18:13:39+07:00']);
    file_put_contents($dir . '/start_diagnostic.json', $legacy);
    file_put_contents($dir . '/ssh-start.stderr', 'fixture@host: Permission denied (publickey,password). PRIVATE_SENTINEL');
    $controller->action_tunnel_status();
    $data = $controller->output->data();
    $check($data['last_start']['error_code'] === 'SSH_AUTH_REJECTED'
        && strpos($controller->output->body, 'PRIVATE_SENTINEL') === false
        && file_get_contents($dir . '/start_diagnostic.json') === $legacy, 'legacy checkpoint readable without secrets or rewrite');
    file_put_contents($dir . '/start_diagnostic.json', '{broken');
    $controller->action_tunnel_status();
    $check($controller->output->data()['last_start'] === null, 'corrupt checkpoint does not break status');

    $result = $invoke('_tunnel_start_process', [PHP_BINARY, '-r', 'echo "ok"; exit(0);'], $dir);
    $check($result['code'] === 0 && $result['stdout'] === 'ok' && !$result['timed_out'], 'real child success exit code retained');
    $result = $invoke('_tunnel_start_process', [PHP_BINARY, '-r', 'fwrite(STDERR,str_repeat("x",9000)); exit(7);'], $dir);
    $check($result['code'] === 7 && strlen($result['stderr']) === 8192, 'real child failure exit code retained and read bounded');
    $begin = microtime(true);
    $result = $invoke('_tunnel_start_process', [PHP_BINARY, '-r', 'echo getmypid(); flush(); sleep(30);'], $dir);
    $elapsed = microtime(true) - $begin;
    $pid = (int)$result['stdout'];
    $check($result['code'] === 124 && $result['timed_out'] && $elapsed < 18
        && $invoke('_tunnel_failure_code', $result) === 'SSH_TIMEOUT', '15-second deadline terminates the hanging child');
    $check($pid > 0 && !file_exists('/proc/' . $pid), 'timed-out child is reaped, not orphaned');
    echo "All $checks tunnel failure runtime checks passed. No database used.\n";
} finally {
    if (is_resource($listener)) fclose($listener);
    if ($dir !== null) {
        foreach (['client_ed25519', 'known_hosts', 'start_diagnostic.json', 'ssh-start.stdout', 'ssh-start.stderr'] as $file) {
            if (is_file($dir . '/' . $file)) unlink($dir . '/' . $file);
        }
        rmdir($dir);
    }
}
