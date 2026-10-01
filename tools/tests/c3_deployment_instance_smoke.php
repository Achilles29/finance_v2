<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/application/libraries/DeploymentConfig.php';
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++; echo 'PASS ' . $label . "\n";
};
$reject = static function (callable $f, string $label) use ($check): void {
    try { $f(); } catch (RuntimeException $e) { $check(true, $label); return; }
    $check(false, $label);
};
$stage = sys_get_temp_dir() . '/finance-instance-test-' . bin2hex(random_bytes(8));
mkdir($stage, 0700); mkdir($stage . '/runtime', 0700); mkdir($stage . '/web', 0700);
try {
    $empty = DeploymentConfig::fromSnapshot([]);
    $check($empty->canonicalBaseUrl('http://legacy.test/', false) === 'http://legacy.test/', 'existing installations keep explicit opt-in migration');
    $good = DeploymentConfig::fromSnapshot([DeploymentConfig::BASE_URL => 'https://customer.example/finance', DeploymentConfig::SESSION_PATH => $stage . '/runtime', DeploymentConfig::SESSION_COOKIE => 'finance_trial_123']);
    $check($good->canonicalBaseUrl('https://untrusted.example/', true) === 'https://customer.example/finance/', 'configured URL cannot be overridden by request host');
    $check($good->runtimeDirectory(DeploymentConfig::SESSION_PATH, $stage . '/web', '') === $stage . '/runtime/', 'session storage is isolated outside source');
    $check($good->sessionCookieName() === 'finance_trial_123', 'instance can isolate its cookie name');
    foreach (['http://customer.example','https://u:p@customer.example','https://customer.example/?x=1','https://customer.example/#x',"https://customer.example/\n"] as $bad) {
        $reject(fn() => DeploymentConfig::fromSnapshot([DeploymentConfig::BASE_URL => $bad])->canonicalBaseUrl('', true), 'unsafe production URL rejected');
    }
    foreach ([$stage . '/web', $stage . '/missing'] as $path) {
        $reject(fn() => DeploymentConfig::fromSnapshot([DeploymentConfig::SESSION_PATH => $path])->runtimeDirectory(DeploymentConfig::SESSION_PATH, $stage . '/web', ''), 'webroot or missing runtime directory rejected');
    }
    symlink($stage . '/runtime', $stage . '/link');
    $reject(fn() => DeploymentConfig::fromSnapshot([DeploymentConfig::SESSION_PATH => $stage . '/link'])->runtimeDirectory(DeploymentConfig::SESSION_PATH, $stage . '/web', ''), 'runtime symlink rejected');
    $reject(fn() => DeploymentConfig::fromSnapshot([DeploymentConfig::SESSION_COOKIE => 'bad; cookie'])->sessionCookieName(), 'invalid cookie name rejected');
    chmod($stage . '/runtime', 0777);
    clearstatcache();
    $reject(fn() => $good->runtimeDirectory(DeploymentConfig::SESSION_PATH, $stage . '/web', ''), 'world-readable session directory rejected');
    $env = ['PATH' => '/usr/bin:/bin', 'CI_ENV' => 'production', 'FINANCE_DEPLOYMENT_FILE' => $stage . '/missing.json'];
    $p = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/index.php'], [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $env);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($p);
    $check($code !== 0 && $out === 'Service temporarily unavailable.' && $err === '', 'invalid private deployment file returns generic pre-bootstrap failure');
    echo "All {$checks} deployment instance checks passed.\n";
} finally { unlink($stage . '/link'); rmdir($stage . '/runtime'); rmdir($stage . '/web'); rmdir($stage); }
