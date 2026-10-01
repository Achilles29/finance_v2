<?php
declare(strict_types=1);
require __DIR__ . '/FinanceLicenseAgent.php';

try {
    if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0
        || !in_array(strtolower(php_uname('m')), ['x86_64','amd64'], true)) throw new RuntimeException('LINUX_AMD64_ROOT_REQUIRED');
    umask(0077);
    $mode = $argv[1] ?? ''; $options = [];
    if (!in_array($mode, ['init','activate','poll','recover'], true)) throw new RuntimeException('USAGE_INIT_ACTIVATE_POLL_RECOVER');
    foreach (array_slice($argv, 2) as $arg) {
        if (preg_match('/\A--(private-dir|public-dir|web-group|instance-id|control-origin|trust-file|code-file)=(.+)\z/D', $arg, $m) !== 1
            || isset($options[$m[1]])) throw new RuntimeException('USAGE_FILE_ARGUMENTS_ONLY');
        $options[$m[1]] = $m[2];
    }
    $expected = ['private-dir','public-dir','web-group'];
    if ($mode === 'init') $expected = array_merge($expected, ['instance-id','control-origin','trust-file']);
    if (in_array($mode,['activate','recover'],true)) $expected[] = 'code-file';
    if (array_diff($expected, array_keys($options)) || array_diff(array_keys($options), $expected)) throw new RuntimeException('USAGE_INCOMPLETE');
    $group = posix_getgrnam($options['web-group']);
    if (!$group || $group['gid'] === 0) throw new RuntimeException('WEB_GROUP_INVALID');
    $root = dirname(__DIR__, 2);
    $private = new LicenseAgentFiles($options['private-dir'], $root, 0, true);
    $public = new LicenseAgentFiles($options['public-dir'], $root, $group['gid'], false);
    $machine = trim((string)file_get_contents('/etc/machine-id'));
    if (preg_match('/\A[a-f0-9]{32}\z/D', $machine) !== 1) throw new RuntimeException('MACHINE_ID_UNAVAILABLE');
    $agent = new FinanceLicenseAgent($private, $public, hash('sha256', 'NAMUA_FINANCE' . "\0" . $machine . "\0linux-amd64")); unset($machine);
    if ($mode === 'init') {
        $trust = Control_license_verifier::deployment_document($options['trust-file'], $root);
        if (!$trust) throw new RuntimeException('TRUST_UNAVAILABLE');
        $result = $agent->initialize($options['instance-id'], $options['control-origin'], $trust);
    } elseif (in_array($mode,['activate','recover'],true)) {
        $file = $options['code-file']; LicenseAgentFiles::securePath($file, $root);
        if ((fileperms($file) & 0777) !== 0600 || filesize($file) > 128) throw new RuntimeException('ACTIVATION_CODE_FILE_UNSAFE');
        $code = trim((string)file_get_contents($file));
        $result = $mode === 'activate' ? $agent->activate($code) : $agent->recover($code); sodium_memzero($code);
    } else { $result = $agent->poll(); }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(($result['connection'] ?? '') === 'SYNC_UNAVAILABLE' ? 2 : 0);
} catch (Throwable $e) {
    $code = $e instanceof RuntimeException && preg_match('/\A[A-Z_]+\z/D', $e->getMessage()) ? $e->getMessage() : 'LICENSE_AGENT_FAILED';
    fwrite(STDERR, json_encode(['status'=>'ACTION_REQUIRED','code'=>$code,'recovery'=>FinanceLicenseAgent::recoveryMessage($code),'enforcement_changed'=>false]) . "\n");
    exit(1);
}
