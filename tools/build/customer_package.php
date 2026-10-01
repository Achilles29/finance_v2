<?php
declare(strict_types=1);

// Control uses PHP 8.4; Finance gates deliberately use the pinned application PHP 8.1 runtime.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/CustomerBuild.php';
$root = dirname(__DIR__, 2);
$requestPath = $output = '';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--request=')) $requestPath = substr($arg, 10);
    elseif (str_starts_with($arg, '--output=')) $output = substr($arg, 9);
    else { fwrite(STDERR, "Usage: customer_package.php --request=PRIVATE/request.json --output=PRIVATE/output\n"); exit(2); }
}
$r = null; $safeOutput = false; $scratch = null;
try {
    CustomerBuild::paths($requestPath, $output, $root); $safeOutput = true;
    $r = ControlReleaseBridge::json((string)file_get_contents($requestPath));
    CustomerBuild::validate($r, $root);
    if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80200) {
        $php = '/www/server/php/81/bin/php';
        CustomerBuild::need(is_executable($php) && fileowner($php) === 0 && (fileperms($php) & 0022) === 0, 'PHP_81_UNAVAILABLE');
        $process = proc_open([$php, __FILE__, '--request=' . $requestPath, '--output=' . $output],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root);
        CustomerBuild::need(is_resource($process), 'PHP_81_UNAVAILABLE');
        $interrupted = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $relay = static function () use (&$interrupted, $process): void {
                if (!$interrupted) { $interrupted = true; proc_terminate($process); }
            };
            pcntl_signal(SIGTERM, $relay); pcntl_signal(SIGINT, $relay);
        }
        // Do not let Control remove the workspace before the delegated adapter finishes its cleanup.
        do { $state = proc_get_status($process); if ($state['running']) usleep(20000); } while ($state['running']);
        $closed = proc_close($process);
        exit($interrupted ? 1 : ($state['exitcode'] >= 0 ? $state['exitcode'] : $closed));
    }
    // Explicit socket-only database drill; credentials inherited from other deployments are forbidden.
    foreach (getenv() as $key => $value) CustomerBuild::need($value === '' || preg_match('/\A(?:DATABASE_URL|FINANCE_DB_|MYSQL_|MARIADB_|DB_)/i', $key) !== 1, 'CREDENTIAL_ENV_FORBIDDEN');
    putenv('PATH=/www/server/php/81/bin:/www/server/mysql/bin:/usr/local/bin:/usr/bin:/bin');
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        $interrupt = static function (): void {
            pcntl_signal(SIGTERM, SIG_IGN); pcntl_signal(SIGINT, SIG_IGN);
            throw new RuntimeException('BUILD_INTERRUPTED');
        };
        pcntl_signal(SIGTERM, $interrupt); pcntl_signal(SIGINT, $interrupt);
    }
    $scratch = dirname($output) . '/finance-work-' . bin2hex(random_bytes(6));
    CustomerBuild::need(mkdir($scratch, 0700), 'BUILD_WORKSPACE_FAILED');
    putenv('TMPDIR=' . $scratch);
    umask(0077);
    $result = CustomerBuild::build($r, $root, $output, $scratch);
    CustomerBuild::writeJson($output . '/result.json', $result);
    echo "FINANCE CUSTOMER BUILD PASS\n";
} catch (Throwable $e) {
    $reason = property_exists($e, 'failureCode') ? strtoupper((string)$e->failureCode) : $e->getMessage();
    $code = preg_match('/\A[A-Z][A-Z0-9_]{2,79}\z/D', $reason) === 1 ? $reason : 'BUILD_VALIDATION_FAILED';
    if ($safeOutput && is_array($r) && !file_exists($output . '/result.json')) {
        try { CustomerBuild::writeJson($output . '/result.json', ['schema' => 1, 'protocol' => CustomerBuild::PROTOCOL, 'status' => 'FAIL',
            'request_id' => $r['request_id'] ?? '', 'request_sha256' => hash('sha256', CustomerBuild::canonical($r)),
            'product_code' => 'NAMUA_FINANCE', 'release_public_id' => $r['release_public_id'] ?? '', 'error_code' => $code,
            'diagnostics' => CustomerBuild::$diagnostics]); } catch (Throwable $ignored) { }
    }
    fwrite(STDERR, "FINANCE CUSTOMER BUILD FAIL {$code}\n");
    $exitCode = 1;
} finally {
    // Only our freshly generated workspace, never source, uploads, backups or Control's output.
    if ($scratch !== null) releaseArtifactRemoveTree($scratch);
}
exit($exitCode ?? 0);
