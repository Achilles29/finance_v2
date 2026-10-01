<?php

declare(strict_types=1);

/** A4.4-3b bounded PHPStan semantic gate. It never bootstraps CodeIgniter or a database. */

function a4StaticToolchainValid($config): bool
{
    if (!is_array($config)
        || ($config['schema'] ?? null) !== 'finance.a4-static-toolchain'
        || ($config['schema_version'] ?? null) !== 1
    ) {
        return false;
    }
    $runtime = $config['runtime'] ?? null;
    $tool = $config['phpstan'] ?? null;
    $analysis = $config['analysis'] ?? null;
    return is_array($runtime)
        && is_array($tool)
        && is_array($analysis)
        && ($runtime['environment'] ?? null) === 'A4_STATIC_RUNTIME_DIR'
        && ($runtime['default_directory'] ?? null) === '/var/lib/finance-a4-static'
        && ($runtime['vendor_relative_path'] ?? null) === 'vendor'
        && ($tool['package'] ?? null) === 'phpstan/phpstan'
        && ($tool['version'] ?? null) === '1.12.27'
        && ($tool['reference'] ?? null) === '3a6e423c076ab39dfedc307e2ac627ef579db162'
        && ($tool['binary_relative_path'] ?? null) === 'vendor/bin/phpstan'
        && ($analysis['config_relative_path'] ?? null) === 'tools/static/phpstan.neon'
        && ($analysis['baseline_relative_path'] ?? null) === 'tools/static/phpstan-baseline.neon'
        && ($analysis['scope_relative_path'] ?? null) === 'application'
        && is_int($analysis['baseline_error_count'] ?? null)
        && $analysis['baseline_error_count'] >= 0
        && is_int($analysis['maximum_baseline_errors'] ?? null)
        && $analysis['maximum_baseline_errors'] >= 0
        && $analysis['baseline_error_count'] <= $analysis['maximum_baseline_errors']
        && is_int($analysis['timeout_seconds'] ?? null)
        && $analysis['timeout_seconds'] >= 10
        && $analysis['timeout_seconds'] <= 360
        && is_int($analysis['maximum_captured_bytes'] ?? null)
        && $analysis['maximum_captured_bytes'] >= 65536
        && $analysis['maximum_captured_bytes'] <= 1048576;
}

function a4StaticComposerPinValid(string $composerJson, string $composerLock, array $config): bool
{
    $manifest = json_decode($composerJson, true);
    $lock = json_decode($composerLock, true);
    if (!is_array($manifest) || !is_array($lock)
        || ($manifest['require-dev']['phpstan/phpstan'] ?? null) !== $config['phpstan']['version']
    ) {
        return false;
    }
    $matches = [];
    foreach ($lock['packages-dev'] ?? [] as $package) {
        if (is_array($package) && ($package['name'] ?? null) === $config['phpstan']['package']) {
            $matches[] = $package;
        }
    }
    return count($matches) === 1
        && ($matches[0]['version'] ?? null) === $config['phpstan']['version']
        && ($matches[0]['source']['reference'] ?? null) === $config['phpstan']['reference']
        && ($matches[0]['dist']['reference'] ?? null) === $config['phpstan']['reference'];
}

/** @return array{entries:int,errors:int}|null */
function a4StaticBaselineStats(string $baseline): ?array
{
    if (preg_match('/\Aparameters:\R\s+ignoreErrors:\s*\[\]\s*\z/D', trim($baseline)) === 1) {
        return ['entries' => 0, 'errors' => 0];
    }
    preg_match_all('/^\s+message:\s+.+$/m', $baseline, $messages);
    preg_match_all('/^\s+path:\s+.+$/m', $baseline, $paths);
    preg_match_all('/^\s+count:\s+([1-9][0-9]*)\s*$/m', $baseline, $counts);
    $entryCount = count($messages[0]);
    if (strpos($baseline, "parameters:\n") !== 0
        || strpos($baseline, 'ignoreErrors:') === false
        || $entryCount === 0
        || count($paths[0]) !== $entryCount
        || count($counts[1]) !== $entryCount
    ) {
        return null;
    }
    return ['entries' => $entryCount, 'errors' => array_sum(array_map('intval', $counts[1]))];
}

function a4StaticSourceContract(array $config, string $root): array
{
    $failures = [];
    $read = static function (string $relative) use ($root): ?string {
        $path = $root . '/' . $relative;
        $contents = is_file($path) ? @file_get_contents($path) : false;
        return is_string($contents) ? $contents : null;
    };
    $composerJson = $read('composer.json');
    $composerLock = $read('composer.lock');
    if ($composerJson === null || $composerLock === null
        || !a4StaticComposerPinValid($composerJson, $composerLock, $config)
    ) {
        $failures[] = 'composer_phpstan_pin_invalid';
    }
    $phpstanConfig = $read($config['analysis']['config_relative_path']);
    if ($phpstanConfig === null
        || preg_match('/^\s*paths:\R\s+- \.\.\/\.\.\/application\s*$/m', $phpstanConfig) !== 1
        || preg_match('/^\s*reportUnmatchedIgnoredErrors:\s*true\s*$/m', $phpstanConfig) !== 1
        || strpos($phpstanConfig, 'excludePaths:') !== false
        || strpos($phpstanConfig, 'bootstrapFiles:') !== false
        || strpos($phpstanConfig, 'ci3-stubs.php') === false
        || strpos($phpstanConfig, '%env.A4_STATIC_CACHE_DIR%') === false
    ) {
        $failures[] = 'phpstan_scope_or_policy_invalid';
    }
    $stub = $read('tools/static/ci3-stubs.php');
    if ($stub === null || strpos($stub, 'class CI_Controller') === false || strpos($stub, 'class CI_Model') === false) {
        $failures[] = 'ci3_static_stub_invalid';
    }
    $baseline = $read($config['analysis']['baseline_relative_path']);
    $stats = $baseline === null ? null : a4StaticBaselineStats($baseline);
    if ($stats === null
        || $stats['errors'] !== $config['analysis']['baseline_error_count']
        || $stats['errors'] > $config['analysis']['maximum_baseline_errors']
    ) {
        $failures[] = 'baseline_count_invalid_or_over_cap';
    }
    $scope = $root . '/' . $config['analysis']['scope_relative_path'];
    $requiredTargets = [
        $scope . '/controllers/Pos.php',
        $scope . '/controllers/Pos_mobile.php',
        $scope . '/models/Pos_model.php',
        $scope . '/views/pos/cashier_index.php',
    ];
    foreach ($requiredTargets as $target) {
        if (!is_file($target)) {
            $failures[] = 'required_analysis_target_missing';
            break;
        }
    }
    return ['ok' => $failures === [], 'failures' => $failures, 'baseline' => $stats];
}

/** @return array{code:int,timeout:bool,overflow:bool,stdout:string,stderr:string} */
function a4StaticRun(array $command, string $cwd, int $timeoutSeconds, int $maximumBytes, ?array $environment = null): array
{
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd, $environment);
    if (!is_resource($process)) {
        return ['code' => 127, 'timeout' => false, 'overflow' => false, 'stdout' => '', 'stderr' => 'unable to start PHPStan'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $started = microtime(true);
    $stdout = '';
    $stderr = '';
    $captured = 0;
    $timeout = false;
    $overflow = false;
    $code = null;
    while (true) {
        foreach ([1 => 'stdout', 2 => 'stderr'] as $index => $streamName) {
            $chunk = stream_get_contents($pipes[$index]);
            if (!is_string($chunk) || $chunk === '') {
                continue;
            }
            $captured += strlen($chunk);
            if ($captured <= $maximumBytes) {
                ${$streamName} .= $chunk;
            } else {
                $overflow = true;
                proc_terminate($process);
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            $code = (int)$status['exitcode'];
            break;
        }
        if ((microtime(true) - $started) >= $timeoutSeconds) {
            $timeout = true;
            proc_terminate($process);
            usleep(100000);
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            $code = 124;
            break;
        }
        usleep(20000);
    }
    foreach ([1 => 'stdout', 2 => 'stderr'] as $index => $streamName) {
        $chunk = stream_get_contents($pipes[$index]);
        if (is_string($chunk) && $captured + strlen($chunk) <= $maximumBytes) {
            ${$streamName} .= $chunk;
            $captured += strlen($chunk);
        } elseif (is_string($chunk) && $chunk !== '') {
            $overflow = true;
        }
        fclose($pipes[$index]);
    }
    $closed = proc_close($process);
    if (!$timeout && ($code === null || $code < 0) && $closed >= 0) {
        $code = $closed;
    }
    return ['code' => $code ?? 127, 'timeout' => $timeout, 'overflow' => $overflow, 'stdout' => $stdout, 'stderr' => $stderr];
}

function a4StaticRuntimeDirectory(array $config): string
{
    $configured = trim((string)getenv($config['runtime']['environment']));
    return rtrim($configured !== '' ? $configured : $config['runtime']['default_directory'], DIRECTORY_SEPARATOR);
}

/** Stable per checkout: staging and a clean release build must not invalidate each other's cache. */
function a4StaticCacheDirectory(string $runtimeDirectory, string $root): string
{
    return $runtimeDirectory . '/tmp/' . hash('sha256', realpath($root) ?: $root);
}

if (defined('A4_STATIC_ANALYSIS_LIBRARY_ONLY') && A4_STATIC_ANALYSIS_LIBRARY_ONLY) {
    return;
}

$root = dirname(__DIR__, 2);
$lockPath = $root . '/tools/static/toolchain.lock.json';
$config = is_file($lockPath) ? json_decode((string)@file_get_contents($lockPath), true) : null;
if (!a4StaticToolchainValid($config)) {
    fwrite(STDERR, "A4 STATIC FAIL toolchain_config_invalid\n");
    exit(1);
}
$sourceContract = a4StaticSourceContract($config, $root);
if (!$sourceContract['ok']) {
    fwrite(STDERR, 'A4 STATIC FAIL ' . implode(',', $sourceContract['failures']) . PHP_EOL);
    exit(1);
}
if (in_array('--source-only', array_slice($argv ?? [], 1), true)) {
    echo 'A4 STATIC SOURCE PASS baseline_errors=' . $sourceContract['baseline']['errors'] . PHP_EOL;
    exit(0);
}

$runtimeDirectory = a4StaticRuntimeDirectory($config);
$binary = $runtimeDirectory . '/' . $config['phpstan']['binary_relative_path'];
if ($runtimeDirectory === '' || $runtimeDirectory === '/' || strpos($runtimeDirectory . '/', $root . '/') === 0
    || !is_file($binary) || !is_executable($binary)
) {
    fwrite(STDERR, "A4 STATIC FAIL runtime_or_phpstan_missing\n");
    exit(1);
}
$version = a4StaticRun([$binary, '--version'], $root, 10, 65536);
if ($version['code'] !== 0 || $version['timeout'] || $version['overflow']
    || preg_match('/PHPStan - PHP Static Analysis Tool 1\.12\.27(?:\s|$)/', $version['stdout'] . $version['stderr']) !== 1
) {
    fwrite(STDERR, "A4 STATIC FAIL phpstan_version_mismatch\n");
    exit(1);
}
if (in_array('--runtime-check-only', array_slice($argv ?? [], 1), true)) {
    echo "A4 STATIC RUNTIME PASS phpstan=1.12.27\n";
    exit(0);
}

$analysisEnvironment = getenv();
if (!is_array($analysisEnvironment)) {
    $analysisEnvironment = [];
}
$analysisEnvironment[$config['runtime']['environment']] = $runtimeDirectory;
$cacheDirectory = a4StaticCacheDirectory($runtimeDirectory, $root);
if (is_link($runtimeDirectory . '/tmp') || is_link($cacheDirectory)
    || (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0700, true))) {
    fwrite(STDERR, "A4 STATIC FAIL cache_directory_unsafe\n");
    exit(1);
}
$analysisEnvironment['A4_STATIC_CACHE_DIR'] = $cacheDirectory;
$result = a4StaticRun([
    $binary,
    'analyse',
    '--configuration=' . $root . '/' . $config['analysis']['config_relative_path'],
    '--error-format=json',
    '--no-progress',
    '--no-ansi',
    '--memory-limit=2G',
    $root . '/' . $config['analysis']['scope_relative_path'],
], $root, $config['analysis']['timeout_seconds'], $config['analysis']['maximum_captured_bytes'], $analysisEnvironment);
if ($result['timeout']) {
    fwrite(STDERR, "A4 STATIC FAIL timeout\n");
    exit(1);
}
if ($result['overflow']) {
    fwrite(STDERR, "A4 STATIC FAIL output_limit_exceeded\n");
    exit(1);
}
$report = json_decode($result['stdout'], true);
if (!is_array($report)
    || !is_array($report['totals'] ?? null)
    || !is_int($report['totals']['errors'] ?? null)
    || !is_int($report['totals']['file_errors'] ?? null)
) {
    $detail = preg_replace('/\s+/', ' ', trim($result['stderr']));
    fwrite(STDERR, 'A4 STATIC FAIL malformed_report detail=' . substr((string)$detail, 0, 500) . PHP_EOL);
    exit(1);
}
$errors = $report['totals']['errors'] + $report['totals']['file_errors'];
if ($result['code'] !== 0 || $errors !== 0) {
    fwrite(STDERR, 'A4 STATIC FAIL phpstan_exit=' . $result['code'] . ' errors=' . $errors . PHP_EOL);
    foreach (array_slice((array)($report['errors'] ?? []), 0, 3) as $error) {
        if (is_string($error)) fwrite(STDERR, 'GLOBAL ' . substr(preg_replace('/\s+/', ' ', $error), 0, 500) . PHP_EOL);
    }
    $shown = 0;
    foreach ($report['files'] ?? [] as $path => $fileReport) {
        foreach ($fileReport['messages'] ?? [] as $message) {
            if ($shown++ >= 12) {
                break 2;
            }
            echo 'FINDING ' . basename((string)$path) . ':' . (int)($message['line'] ?? 0) . ' '
                . substr(preg_replace('/\s+/', ' ', (string)($message['message'] ?? 'unknown')), 0, 300) . PHP_EOL;
        }
    }
    exit(1);
}
echo 'A4 STATIC ANALYSIS PASS scope=application baseline_errors=' . $sourceContract['baseline']['errors'] . PHP_EOL;
