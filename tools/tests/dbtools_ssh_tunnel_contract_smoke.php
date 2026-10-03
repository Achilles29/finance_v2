<?php

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/application/controllers/System_tools.php');
$mainView = file_get_contents($root . '/application/views/system/dbtools.php');
$settingsView = file_get_contents($root . '/application/views/system/settings.php');
$script = file_get_contents($root . '/scripts/replication/tunnel_start.sh');
$routes = file_get_contents($root . '/application/config/routes.php');
$featureAccess = file_get_contents($root . '/application/config/feature_access.php');
$checks = [];

foreach (['action_initial_sync', 'action_compare_data', 'action_restart_replication'] as $method) {
    $start = strpos($controller, 'function ' . $method . '(');
    $next = strpos($controller, '\n    public function ', $start + 1);
    $body = substr($controller, $start, $next === false ? null : $next - $start);
    $checks[$method . ' uses configured local tunnel port'] = strpos($body, "_cfg('tunnel.local_port'") !== false;
}

$checks['tunnel script reads SSH and remote tunnel settings'] =
    strpos($script, '${SSH_HOST:-}') !== false
    && strpos($script, '${SSH_PORT:-22}') !== false
    && strpos($script, '${TUNNEL_REMOTE_PORT:-3306}') !== false;
$checks['tunnel binds only to loopback and verifies host keys'] =
    strpos($script, '127.0.0.1:${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}') !== false
    && strpos($script, 'StrictHostKeyChecking=yes') !== false
    && strpos($script, 'StrictHostKeyChecking=no') === false;
$checks['both settings forms persist SSH port'] =
    strpos($mainView, "'tunnel.ssh_port': getVal('t_ssh_port')") !== false
    && strpos($settingsView, "'tunnel.ssh_port':   document.getElementById('cfg_tunnel_ssh_port')") !== false;
$checks['replication save allows SSH port'] = strpos($controller, "'tunnel.enabled', 'tunnel.ssh_host', 'tunnel.ssh_port', 'tunnel.ssh_user'") !== false;
$checks['settings save reports env write failure instead of false success'] =
    strpos($controller, 'if (!$this->_writeEnvFile())') !== false
    && strpos($controller, 'private function _writeEnvFile(): bool') !== false
    && strpos($controller, 'file_put_contents($envPath, implode("\\n", $lines) . "\\n", LOCK_EX)') !== false;

foreach ([
    'action_tunnel_generate_key', 'action_local_ssh_fingerprint',
    'action_tunnel_scan_host_key', 'action_tunnel_trust_host_key',
    'action_tunnel_status', 'action_tunnel_start', 'action_tunnel_stop',
] as $method) {
    $start = strpos($controller, 'function ' . $method . '(');
    $next = strpos($controller, '\n    public function ', $start + 1);
    $body = substr($controller, $start, $next === false ? null : $next - $start);
    $checks[$method . ' checks mutation CSRF'] = strpos($body, 'require_system_tools_mutation_csrf()') !== false;
}
$checks['SSH tunnel UI routes and permission allowlist exist'] =
    strpos($routes, "'dbtools/action/tunnel-start'") !== false
    && strpos($routes, "'dbtools/action/local-ssh-fingerprint'") !== false
    && strpos($featureAccess, 'action_tunnel_generate_key action_local_ssh_fingerprint') !== false
    && strpos($featureAccess, 'action_tunnel_start action_tunnel_stop') !== false;
$checks['primary SSH fingerprint button is wired'] =
    strpos($mainView, 'id="btn-master-ssh-fingerprint"') !== false
    && strpos($mainView, "getElementById('btn-master-ssh-fingerprint')?.addEventListener") !== false;
$checks['tunnel SSH invocation pins host keys and limits listener to loopback'] =
    strpos($controller, "'-o', 'StrictHostKeyChecking=yes'") !== false
    && strpos($controller, "'-L', \"127.0.0.1:{\$localPort}:127.0.0.1:{\$remotePort}\"") !== false
    && strpos($controller, 'proc_open($command') !== false;

$failed = false;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    $failed = $failed || !$passed;
}
exit($failed ? 1 : 0);
