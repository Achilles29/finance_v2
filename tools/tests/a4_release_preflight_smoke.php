<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/release/ReleasePackagePolicy.php';

/** A4.4 read-only release preflight. It never prints matched secret values. */
$root = dirname(__DIR__, 2);
$policyPath = $root . '/tools/release/package_policy.json';
$issues = [];
$stats = ['candidate_files' => 0, 'excluded_files' => 0, 'php_linted' => 0, 'node_checked' => 0, 'python_checked' => 0];

function a4PreflightRun(array $command, string $cwd): array
{
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd);
    if (!is_resource($process)) {
        return ['code' => 127, 'output' => 'cannot start process'];
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => (string)$output];
}

function a4PreflightIssue(array &$issues, string $category, string $path, int $line = 0): void
{
    $key = $category . '|' . $path . '|' . $line;
    $issues[$key] = [
        'category' => $category,
        'path' => $path,
        'line' => max(0, $line),
    ];
}

function a4PreflightPolicyValid($policy): bool
{
    return ReleasePackagePolicy::isValid($policy);
}

function a4PreflightFixtureAllowed(array $exceptions, string $path, int $line, string $category, string $contents): bool
{
    $fingerprint = hash('sha256', $contents);
    foreach ($exceptions as $exception) {
        if (($exception['path'] ?? null) === $path
            && ($exception['line'] ?? null) === $line
            && ($exception['category'] ?? null) === $category
            && hash_equals((string)($exception['sha256'] ?? ''), $fingerprint)
        ) {
            return true;
        }
    }
    return false;
}

function a4PreflightFileProblem(string $path, bool $enforceSize = false, int $maxBytes = 2097152): ?string
{
    return ReleasePackagePolicy::absoluteFileProblem($path, $enforceSize, $maxBytes);
}

function a4PreflightFormatIssue(array $issue): string
{
    return 'FINDING ' . (string)$issue['category'] . ' ' . (string)$issue['path']
        . ((int)$issue['line'] > 0 ? ':' . (int)$issue['line'] : '');
}

/** @return int[] Source line numbers for invalid logical requirements. */
function a4PreflightInvalidPythonLockLines(array $lines): array
{
    $invalid = [];
    $logical = '';
    $startedAt = 0;
    foreach ($lines as $index => $rawLine) {
        $trimmed = trim((string)$rawLine);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }
        if ($logical === '') {
            $startedAt = $index + 1;
        }
        $continued = substr($trimmed, -1) === '\\';
        $piece = $continued ? rtrim(substr($trimmed, 0, -1)) : $trimmed;
        $logical .= ($logical === '' ? '' : ' ') . $piece;
        if ($continued) {
            continue;
        }
        $normalized = preg_replace('/\s+/', ' ', trim($logical));
        if (!is_string($normalized)
            || preg_match('/\A[A-Za-z0-9_.-]+(?:\[[A-Za-z0-9_,.-]+\])?==[^\s]+(?: --hash=sha256:[a-f0-9]{64})+\z/D', $normalized) !== 1
        ) {
            $invalid[] = $startedAt;
        }
        $logical = '';
        $startedAt = 0;
    }
    if ($logical !== '') {
        $invalid[] = $startedAt;
    }
    return $invalid;
}

if (defined('A4_PREFLIGHT_LIBRARY_ONLY') && A4_PREFLIGHT_LIBRARY_ONLY) {
    return;
}

$policyRaw = is_file($policyPath) ? file_get_contents($policyPath) : false;
$policy = is_string($policyRaw) ? json_decode($policyRaw, true) : null;
$packagePolicy = null;
if (!a4PreflightPolicyValid($policy)) {
    a4PreflightIssue($issues, 'POLICY_INVALID', 'tools/release/package_policy.json');
    $policy = [];
} else {
    $packagePolicy = new ReleasePackagePolicy($policy);
}
$enumerated = $packagePolicy instanceof ReleasePackagePolicy
    ? $packagePolicy->enumerate($root)
    : ['files' => [], 'excluded_files' => 0, 'issues' => []];
foreach ($enumerated['issues'] as $issue) {
    a4PreflightIssue($issues, $issue['category'], $issue['path']);
}
$candidates = $enumerated['files'];
$stats['excluded_files'] = $enumerated['excluded_files'];
$stats['candidate_files'] = count($candidates);

$readableCandidates = [];
foreach ($candidates as $relative) {
    $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
    $absolute = $root . '/' . $relative;
    $fileProblem = a4PreflightFileProblem($absolute);
    if ($fileProblem !== null) {
        a4PreflightIssue($issues, $fileProblem, $relative);
        continue;
    }
    $readableCandidates[] = $relative;
    if ($extension === 'php') {
        $lint = a4PreflightRun([PHP_BINARY, '-l', $absolute], $root);
        $stats['php_linted']++;
        if ($lint['code'] !== 0) {
            a4PreflightIssue($issues, 'PHP_LINT', $relative);
        }
    }
    if ($extension === 'js' && strpos($relative, 'wa-engine/') === 0) {
        $check = a4PreflightRun(['/usr/bin/node', '--check', $absolute], $root);
        $stats['node_checked']++;
        if ($check['code'] !== 0) {
            a4PreflightIssue($issues, 'NODE_SYNTAX', $relative);
        }
    }
    if ($extension === 'py' && strpos($relative, 'tools/pos_printer_agent/') === 0) {
        $script = 'import ast, pathlib, sys; ast.parse(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"), filename=sys.argv[1])';
        $check = a4PreflightRun(['/usr/bin/python3', '-c', $script, $absolute], $root);
        $stats['python_checked']++;
        if ($check['code'] !== 0) {
            a4PreflightIssue($issues, 'PYTHON_SYNTAX', $relative);
        }
    }
}

$composer = a4PreflightRun(['/usr/bin/composer', 'validate', '--no-check-publish', '--no-interaction'], $root);
if ($composer['code'] !== 0) {
    a4PreflightIssue($issues, 'COMPOSER_INVALID', 'composer.json');
}
if (is_file($root . '/composer.json') && !is_file($root . '/composer.lock')) {
    a4PreflightIssue($issues, 'DEPENDENCY_LOCK_MISSING', 'composer.lock');
} elseif (is_file($root . '/composer.lock')) {
    $composerLock = json_decode((string)@file_get_contents($root . '/composer.lock'), true);
    if (!is_array($composerLock)
        || preg_match('/\A[a-f0-9]{32}\z/D', (string)($composerLock['content-hash'] ?? '')) !== 1
        || !isset($composerLock['packages'], $composerLock['packages-dev'])
        || !is_array($composerLock['packages'])
        || !is_array($composerLock['packages-dev'])
    ) {
        a4PreflightIssue($issues, 'DEPENDENCY_LOCK_INVALID', 'composer.lock');
    }
}

$nodeManifest = json_decode((string)@file_get_contents($root . '/wa-engine/package.json'), true);
$nodeLock = json_decode((string)@file_get_contents($root . '/wa-engine/package-lock.json'), true);
if (!is_array($nodeManifest) || !is_array($nodeLock)
    || ($nodeManifest['name'] ?? null) !== ($nodeLock['name'] ?? null)
    || (int)($nodeLock['lockfileVersion'] ?? 0) < 2
) {
    a4PreflightIssue($issues, 'NODE_LOCK_INVALID', 'wa-engine/package-lock.json');
}

$requirementsPath = $root . '/tools/pos_printer_agent/requirements.txt';
$requirementsSourcePath = $root . '/tools/pos_printer_agent/requirements.in';
if (!is_file($requirementsSourcePath)) {
    a4PreflightIssue($issues, 'PYTHON_SOURCE_REQUIREMENTS_MISSING', 'tools/pos_printer_agent/requirements.in');
}
$requirements = is_file($requirementsPath) ? file($requirementsPath, FILE_IGNORE_NEW_LINES) : false;
if (!is_array($requirements)) {
    a4PreflightIssue($issues, 'PYTHON_LOCK_MISSING', 'tools/pos_printer_agent/requirements.txt');
} else {
    foreach (a4PreflightInvalidPythonLockLines($requirements) as $lineNumber) {
        a4PreflightIssue($issues, 'PYTHON_DEPENDENCY_NOT_HASH_PINNED', 'tools/pos_printer_agent/requirements.txt', $lineNumber);
    }
}

$scanExtensions = array_map('strtolower', $policy['secret_scan_extensions'] ?? []);
$fixtureExceptions = $policy['secret_fixture_exceptions'] ?? [];
foreach ($readableCandidates as $relative) {
    $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
    $absolute = $root . '/' . $relative;
    if (!in_array($extension, $scanExtensions, true)) {
        continue;
    }
    $fileProblem = a4PreflightFileProblem($absolute, true);
    if ($fileProblem !== null) {
        a4PreflightIssue($issues, $fileProblem, $relative);
        continue;
    }
    $lines = @file($absolute, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        a4PreflightIssue($issues, 'PACKAGE_FILE_UNREADABLE', $relative);
        continue;
    }
    foreach ($lines as $index => $line) {
        $categories = [];
        if (
            preg_match('/\$db\[[^\]]+\]\[[\'\"](?:username|password)[\'\"]\]\s*=\s*[\'\"][^\'\"]+/', $line) === 1
            || preg_match('/[\'\"](?:username|password)[\'\"]\s*=>\s*[\'\"][^\'\"]+/', $line) === 1
        ) {
            $categories[] = 'HARDCODED_DB_CREDENTIAL';
        }
        if (preg_match('/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', $line) === 1) {
            $categories[] = 'PRIVATE_KEY_MATERIAL';
        }
        if (preg_match('/\b(?:api[_-]?key|secret|password)\b\s*[:=]\s*[\'\"][A-Za-z0-9_+\/.=-]{8,}[\'\"]/i', $line) === 1) {
            $categories[] = 'HARDCODED_SECRET_LITERAL';
        }
        foreach ($categories as $category) {
            if (!a4PreflightFixtureAllowed($fixtureExceptions, $relative, $index + 1, $category, (string)$line)) {
                a4PreflightIssue($issues, $category, $relative, $index + 1);
            }
        }
    }
}

ksort($issues, SORT_STRING);
echo 'A4 RELEASE PREFLIGHT candidate_files=' . $stats['candidate_files']
    . ' excluded_files=' . $stats['excluded_files']
    . ' php_linted=' . $stats['php_linted']
    . ' node_checked=' . $stats['node_checked']
    . ' python_checked=' . $stats['python_checked'] . PHP_EOL;
foreach ($issues as $issue) {
    echo a4PreflightFormatIssue($issue) . PHP_EOL;
}
echo 'A4 RELEASE PREFLIGHT ' . ($issues === [] ? 'PASS' : 'FAIL')
    . ' findings=' . count($issues) . PHP_EOL;
exit($issues === [] ? 0 : 1);
