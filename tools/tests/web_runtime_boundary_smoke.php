<?php

declare(strict_types=1);

/**
 * DB-free smoke test for the P0-06A-2 web runtime boundary.
 *
 * It inspects application source and loads config.php in isolated child PHP
 * processes for development and production. It does not bootstrap the
 * framework, connect to a database, or write runtime/bytecode artifacts.
 */

$root = dirname(__DIR__, 2);
$configRelative = 'application/config/config.php';
$configPath = $root . '/' . $configRelative;
$checks = 0;
$failures = [];

function web_boundary_read(string $path): string
{
    $source = @file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "FAIL: missing source file {$path}\n");
        exit(1);
    }

    return $source;
}

function web_boundary_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function web_boundary_load_config(string $root, string $environment): array
{
    $resolverPath = $root . '/application/libraries/DeploymentConfig.php';
    $configPath = $root . '/application/config/config.php';
    $script = sprintf(
        'define("BASEPATH", %s); '
        . 'define("ENVIRONMENT", getenv("CI_ENV") ?: "development"); '
        . 'require_once %s; '
        . '$config = []; '
        . '$finance_deployment_config = DeploymentConfig::fromSnapshot([]); '
        . 'include %s; '
        . 'echo json_encode(['
        . '"cookie_secure" => $config["cookie_secure"] ?? null, '
        . '"cookie_httponly" => $config["cookie_httponly"] ?? null, '
        . '"cookie_samesite" => $config["cookie_samesite"] ?? null, '
        . '"sess_expiration" => $config["sess_expiration"] ?? null, '
        . '"sess_time_to_update" => $config["sess_time_to_update"] ?? null, '
        . '"sess_regenerate_destroy" => $config["sess_regenerate_destroy"] ?? null'
        . ']);',
        var_export($root . '/system/', true),
        var_export($resolverPath, true),
        var_export($configPath, true)
    );

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environmentVariables = [
        'CI_ENV' => $environment,
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    ];
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
    $process = proc_open($command, $descriptors, $pipes, $root, $environmentVariables);
    if (!is_resource($process)) {
        return [1, '', ''];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    return [$status, trim($stdout), trim($stderr)];
}

$configSource = web_boundary_read($configPath);

web_boundary_check(
    preg_match(
        '~\$config\[["\']cookie_secure["\']\]\s*=\s*\(\s*ENVIRONMENT\s*===\s*["\']production["\']\s*\)\s*;~',
        $configSource
    ) === 1,
    'cookie_secure is not explicitly production-only'
);
web_boundary_check(
    preg_match('~\$config\[["\']cookie_httponly["\']\]\s*=\s*TRUE\s*;~i', $configSource) === 1,
    'cookie_httponly is not explicitly TRUE'
);
web_boundary_check(
    preg_match('~\$config\[["\']cookie_samesite["\']\]\s*=\s*["\']Lax["\']\s*;~', $configSource) === 1,
    'cookie_samesite is not explicitly Lax'
);
web_boundary_check(
    preg_match('~\$config\[["\']sess_expiration["\']\]\s*=\s*43200\s*;~', $configSource) === 1,
    'session expiration is not bounded to twelve hours'
);
web_boundary_check(
    preg_match('~\$config\[["\']sess_time_to_update["\']\]\s*=\s*300\s*;~', $configSource) === 1,
    'session identifier rotation is not bounded to five minutes'
);
web_boundary_check(
    preg_match('~\$config\[["\']sess_regenerate_destroy["\']\]\s*=\s*TRUE\s*;~i', $configSource) === 1,
    'rotated session identifiers are not destroyed'
);
web_boundary_check(
    preg_match('/Access-Control-Allow-/i', $configSource) === 0,
    'application config still contains a global CORS header'
);
web_boundary_check(
    preg_match('/header\\s*\\(\\s*[\'\"]Access-Control-Allow-/i', $configSource) === 0,
    'application config still emits a global CORS header'
);

$applicationPath = $root . '/application';
$printerAgentRelativePath = 'controllers/Pos_printer_agent.php';
$corsFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($applicationPath, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $relativePath = substr($fileInfo->getPathname(), strlen($applicationPath) + 1);
    if ($relativePath === $printerAgentRelativePath) {
        continue;
    }

    if (preg_match('/Access-Control-Allow-/i', web_boundary_read($fileInfo->getPathname())) === 1) {
        $corsFiles[] = $relativePath;
    }
}
web_boundary_check(
    $corsFiles === [],
    'application source contains CORS headers outside the Printer Agent boundary'
);

foreach (['development' => false, 'production' => true] as $environment => $secureExpected) {
    [$status, $stdout, $stderr] = web_boundary_load_config($root, $environment);
    $snapshot = json_decode($stdout, true);
    web_boundary_check(
        $status === 0 && is_array($snapshot) && $stderr === '',
        "{$environment} config did not load in an isolated process"
    );
    if (!is_array($snapshot)) {
        continue;
    }

    web_boundary_check(
        ($snapshot['cookie_secure'] ?? null) === $secureExpected,
        "{$environment} cookie_secure boundary is incorrect"
    );
    web_boundary_check(
        ($snapshot['cookie_httponly'] ?? null) === true,
        "{$environment} cookie_httponly is not enabled"
    );
    web_boundary_check(
        ($snapshot['cookie_samesite'] ?? null) === 'Lax',
        "{$environment} SameSite boundary is not Lax"
    );
    web_boundary_check(
        ($snapshot['sess_expiration'] ?? null) === 43200,
        "{$environment} session expiration is not twelve hours"
    );
    web_boundary_check(
        ($snapshot['sess_time_to_update'] ?? null) === 300,
        "{$environment} session identifier rotation is not five minutes"
    );
    web_boundary_check(
        ($snapshot['sess_regenerate_destroy'] ?? null) === true,
        "{$environment} rotated session identifier is not destroyed"
    );
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: web runtime boundary smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: {$checks} web runtime boundary checks\n";
