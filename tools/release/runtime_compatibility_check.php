<?php

declare(strict_types=1);

/** A5.14 runtime/dependency compatibility contract and staging probe. */

function financeRuntimePolicy(string $root): array
{
    $path = $root . '/tools/release/runtime_compatibility.json';
    $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('runtime compatibility policy is missing or invalid JSON');
    }
    return $decoded;
}

function financeRuntimeVersionInRange(string $version, array $rule): bool
{
    return isset($rule['minimum'], $rule['maximum_exclusive'])
        && is_string($rule['minimum'])
        && is_string($rule['maximum_exclusive'])
        && version_compare($version, $rule['minimum'], '>=')
        && version_compare($version, $rule['maximum_exclusive'], '<');
}

/** @return string[] */
function financeRuntimePolicyErrors(array $policy): array
{
    $errors = [];
    if (($policy['schema'] ?? null) !== 'finance.runtime-compatibility'
        || ($policy['schema_version'] ?? null) !== 1
        || ($policy['status'] ?? null) !== 'staging_baseline'
    ) {
        $errors[] = 'policy header is invalid';
    }
    $requiredRuntimes = ['php', 'mariadb', 'node', 'npm', 'python', 'composer'];
    foreach ($requiredRuntimes as $name) {
        $rule = $policy['runtimes'][$name] ?? null;
        if (!is_array($rule)
            || !is_string($rule['minimum'] ?? null)
            || !is_string($rule['maximum_exclusive'] ?? null)
            || !is_string($rule['staging_tested'] ?? null)
            || !version_compare($rule['maximum_exclusive'], $rule['minimum'], '>')
            || !financeRuntimeVersionInRange($rule['staging_tested'], $rule)
        ) {
            $errors[] = 'invalid runtime rule: ' . $name;
        }
    }
    $extensions = $policy['runtimes']['php']['required_extensions'] ?? null;
    if (!is_array($extensions) || $extensions !== array_values(array_unique($extensions))) {
        $errors[] = 'required PHP extension list is invalid or duplicated';
    }
    $featureExtensions = $policy['runtimes']['php']['feature_extensions']['whatsapp_file_mime'] ?? null;
    if ($featureExtensions !== ['fileinfo']) {
        $errors[] = 'WhatsApp MIME feature must declare fileinfo';
    }
    if (($policy['dependency_locks'] ?? null) !== [
        'composer.lock',
        'wa-engine/package-lock.json',
        'tools/pos_printer_agent/requirements.txt',
    ]) {
        $errors[] = 'dependency lock list is incomplete or out of order';
    }
    return $errors;
}

/** @return string[] */
function financeRuntimeRepositoryErrors(string $root, array $policy): array
{
    $errors = [];
    foreach ($policy['dependency_locks'] as $relative) {
        if (!is_file($root . '/' . $relative) || !is_readable($root . '/' . $relative)) {
            $errors[] = 'dependency lock missing: ' . $relative;
        }
    }

    $composer = json_decode((string)@file_get_contents($root . '/composer.json'), true);
    $composerLock = json_decode((string)@file_get_contents($root . '/composer.lock'), true);
    $expectedPhp = $policy['runtimes']['php']['composer_constraint'] ?? null;
    if (!is_array($composer) || ($composer['require']['php'] ?? null) !== $expectedPhp) {
        $errors[] = 'composer PHP constraint does not match the runtime policy';
    }
    if (!is_array($composerLock)
        || preg_match('/\A[a-f0-9]{32}\z/D', (string)($composerLock['content-hash'] ?? '')) !== 1
        || !is_array($composerLock['packages'] ?? null)
        || !is_array($composerLock['packages-dev'] ?? null)
    ) {
        $errors[] = 'composer.lock is invalid or incomplete';
    }

    $package = json_decode((string)@file_get_contents($root . '/wa-engine/package.json'), true);
    $packageLock = json_decode((string)@file_get_contents($root . '/wa-engine/package-lock.json'), true);
    $rootLock = is_array($packageLock) ? ($packageLock['packages'][''] ?? null) : null;
    if (!is_array($package)
        || !is_array($packageLock)
        || ($packageLock['lockfileVersion'] ?? null) !== 3
        || !is_array($rootLock)
        || ($rootLock['dependencies'] ?? null) !== ($package['dependencies'] ?? null)
    ) {
        $errors[] = 'npm lock does not match wa-engine/package.json';
    } else {
        foreach ($packageLock['packages'] as $path => $entry) {
            if ($path === '' || !is_array($entry) || isset($entry['link'])) {
                continue;
            }
            $resolved = (string)($entry['resolved'] ?? '');
            $hasIntegrity = preg_match('/\Asha(1|256|384|512)-/D', (string)($entry['integrity'] ?? '')) === 1;
            $isPinnedGit = preg_match('/\Agit\+[^#]+#[a-f0-9]{40}\z/D', $resolved) === 1;
            if (!$hasIntegrity && !$isPinnedGit) {
                $errors[] = 'npm package lacks integrity or pinned commit: ' . $path;
                break;
            }
        }
    }

    $requirements = (string)@file_get_contents($root . '/tools/pos_printer_agent/requirements.txt');
    preg_match_all('/^[a-z0-9_.-]+(?:\[[a-z0-9_,.-]+\])?==[^\\\s]+\s*\\\\$/mi', $requirements, $pins);
    preg_match_all('/--hash=sha256:[a-f0-9]{64}/', $requirements, $hashes);
    if (count($pins[0]) < 4 || count($hashes[0]) < count($pins[0])) {
        $errors[] = 'Python requirements are not fully pinned with hashes';
    }
    return $errors;
}

/** @return array{code:int,output:string} */
function financeRuntimeCommand(array $command): array
{
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        return ['code' => 127, 'output' => ''];
    }
    $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => trim($output)];
}

function financeRuntimeExtractVersion(string $output): ?string
{
    return preg_match('/(?<![0-9])([0-9]+\.[0-9]+(?:\.[0-9]+)?)(?![0-9])/', $output, $match) === 1
        ? $match[1]
        : null;
}

function financeRuntimeExtractCommandVersion(string $name, string $output): ?string
{
    if ($name === 'mariadb'
        && preg_match('/\bDistrib\s+([0-9]+\.[0-9]+(?:\.[0-9]+)?)/i', $output, $match) === 1
    ) {
        return $match[1];
    }
    return financeRuntimeExtractVersion($output);
}

/** @return array{errors:string[],warnings:string[],facts:array<string,string>} */
function financeRuntimeStagingProbe(string $root, array $policy): array
{
    $errors = [];
    $warnings = [];
    $facts = [];
    $facts['php_cli'] = PHP_VERSION;
    if (!financeRuntimeVersionInRange(PHP_VERSION, $policy['runtimes']['php'])) {
        $errors[] = 'PHP CLI ' . PHP_VERSION . ' is outside the approved range';
    }
    foreach ($policy['runtimes']['php']['required_extensions'] as $extension) {
        if (!extension_loaded($extension)) {
            $errors[] = 'required PHP extension is missing: ' . $extension;
        }
    }
    foreach ($policy['runtimes']['php']['feature_extensions'] as $feature => $extensions) {
        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                $warnings[] = $feature . ' unavailable because PHP extension is missing: ' . $extension;
            }
        }
    }

    $commands = [
        'mariadb' => ['mariadb', '--version'],
        'node' => ['node', '--version'],
        'npm' => ['npm', '--version'],
        'python' => [
            is_executable('/var/lib/finance-a4-runtime/printer-venv/bin/python')
                ? '/var/lib/finance-a4-runtime/printer-venv/bin/python'
                : 'python3',
            '--version',
        ],
        'composer' => ['composer', '--version'],
    ];
    foreach ($commands as $name => $command) {
        $result = financeRuntimeCommand($command);
        $version = financeRuntimeExtractCommandVersion($name, $result['output']);
        if ($result['code'] !== 0 || $version === null) {
            $errors[] = $name . ' runtime could not be probed';
            continue;
        }
        if ($name === 'mariadb') {
            // Client distribution is not SELECT VERSION() on the target server.
            $facts['mariadb_client'] = $version;
            $warnings[] = 'MariaDB client detected only; server runtime must be verified on the target DB by the installer before DDL';
            continue;
        }
        $facts[$name] = $version;
        if (!financeRuntimeVersionInRange($version, $policy['runtimes'][$name])) {
            $errors[] = $name . ' ' . $version . ' is outside the approved range';
        }
    }
    $recommendedComposer = $policy['runtimes']['composer']['recommended_minimum'];
    if (isset($facts['composer']) && version_compare($facts['composer'], $recommendedComposer, '<')) {
        $warnings[] = 'Composer ' . $facts['composer'] . ' is below recommended ' . $recommendedComposer;
    }

    $fpmBinary = dirname(dirname(PHP_BINARY)) . '/sbin/php-fpm';
    if (!is_executable($fpmBinary)) {
        $warnings[] = 'matching PHP-FPM binary was not found beside PHP CLI';
    } else {
        $fpm = financeRuntimeCommand([$fpmBinary, '-v']);
        $fpmVersion = financeRuntimeExtractVersion($fpm['output']);
        if ($fpm['code'] !== 0 || $fpmVersion === null || !financeRuntimeVersionInRange($fpmVersion, $policy['runtimes']['php'])) {
            $errors[] = 'matching PHP-FPM is outside the approved PHP range';
        } else {
            $facts['php_fpm'] = $fpmVersion;
        }
    }
    return ['errors' => $errors, 'warnings' => $warnings, 'facts' => $facts];
}

if (defined('FINANCE_RUNTIME_COMPAT_LIBRARY_ONLY') && FINANCE_RUNTIME_COMPAT_LIBRARY_ONLY) {
    return;
}

$root = dirname(__DIR__, 2);
$mode = $argv[1] ?? '--contract';
if (!in_array($mode, ['--contract', '--staging'], true)) {
    fwrite(STDERR, "Usage: php tools/release/runtime_compatibility_check.php [--contract|--staging]\n");
    exit(2);
}

try {
    $policy = financeRuntimePolicy($root);
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
$errors = array_merge(financeRuntimePolicyErrors($policy), financeRuntimeRepositoryErrors($root, $policy));
$warnings = [];
$facts = [];
if ($mode === '--staging' && $errors === []) {
    $probe = financeRuntimeStagingProbe($root, $policy);
    $errors = array_merge($errors, $probe['errors']);
    $warnings = $probe['warnings'];
    $facts = $probe['facts'];
}
foreach ($facts as $name => $version) {
    echo 'FACT ' . $name . '=' . $version . PHP_EOL;
}
foreach ($warnings as $warning) {
    echo 'WARN ' . $warning . PHP_EOL;
}
foreach ($errors as $error) {
    fwrite(STDERR, 'FAIL ' . $error . PHP_EOL);
}
if ($errors !== []) {
    exit(1);
}
echo 'PASS A5.14 runtime compatibility ' . substr($mode, 2)
    . '; warnings=' . count($warnings) . PHP_EOL;
