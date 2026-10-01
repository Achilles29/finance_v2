<?php

declare(strict_types=1);

define('A4_PREFLIGHT_LIBRARY_ONLY', true);
require __DIR__ . '/a4_release_preflight_smoke.php';

$passed = 0;
$failed = 0;

function preflightContractCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failed++;
    echo 'FAIL: ' . $label . PHP_EOL;
}

$policyPath = dirname(__DIR__) . '/release/package_policy.json';
$policy = json_decode((string)file_get_contents($policyPath), true);
preflightContractCheck(a4PreflightPolicyValid($policy), 'repository package policy is valid and complete');
preflightContractCheck(class_exists('ReleasePackagePolicy'), 'preflight loads the shared release package policy');
preflightContractCheck(
    !ReleasePackagePolicy::relativePathValid('../escape.php')
        && !ReleasePackagePolicy::relativePathValid('application/../escape.php'),
    'shared policy rejects traversal paths'
);
preflightContractCheck(!a4PreflightPolicyValid(json_decode('{', true)), 'malformed policy fails closed');

$emptyScope = $policy;
$emptyScope['include_roots'] = [];
preflightContractCheck(!a4PreflightPolicyValid($emptyScope), 'empty include scope fails closed');

$reducedRootScope = $policy;
$reducedRootScope['include_roots'] = array_values(array_diff($policy['include_roots'], ['application/']));
preflightContractCheck(!a4PreflightPolicyValid($reducedRootScope), 'removing a required application root fails closed');

$reducedFileScope = $policy;
$reducedFileScope['include_files'] = array_values(array_diff($policy['include_files'], ['index.php']));
preflightContractCheck(!a4PreflightPolicyValid($reducedFileScope), 'removing a required root file fails closed');

$validPythonLock = [
    'flask==3.1.3 \\',
    '  --hash=sha256:' . str_repeat('a', 64) . ' \\',
    '  --hash=sha256:' . str_repeat('b', 64),
];
preflightContractCheck(a4PreflightInvalidPythonLockLines($validPythonLock) === [], 'multiline Python hash lock is accepted');
preflightContractCheck(
    a4PreflightInvalidPythonLockLines(['flask>=3.0.0']) === [1]
    && a4PreflightInvalidPythonLockLines(['flask==3.1.3']) === [1],
    'unpinned or unhashed Python dependency fails closed'
);

$missingConfigType = $policy;
$missingConfigType['secret_scan_extensions'] = array_values(array_diff($policy['secret_scan_extensions'], ['ini']));
preflightContractCheck(!a4PreflightPolicyValid($missingConfigType), 'missing configuration extension fails closed');
preflightContractCheck(
    count(array_intersect(['ini', 'yaml', 'yml', 'xml', 'sql'], $policy['secret_scan_extensions'])) === 5,
    'configuration extensions remain in secret scan scope'
);

$wildcard = $policy;
$wildcard['secret_fixture_exceptions'][] = [
    'path' => 'tools/tests/fixture.php',
    'line' => 1,
    'category' => 'HARDCODED_DB_CREDENTIAL',
    'sha256' => str_repeat('a', 64),
];
$wildcard['secret_fixture_exceptions'][count($wildcard['secret_fixture_exceptions']) - 1]['path'] = 'tools/tests/*';
preflightContractCheck(!a4PreflightPolicyValid($wildcard), 'wildcard fixture exception is rejected');

$fixtureLine = '$fixtureValue = ' . "'contract-value';";
$fixtureException = [[
    'path' => 'tools/tests/fixture.php',
    'line' => 7,
    'category' => 'HARDCODED_DB_CREDENTIAL',
    'sha256' => hash('sha256', $fixtureLine),
]];
preflightContractCheck(
    a4PreflightFixtureAllowed($fixtureException, 'tools/tests/fixture.php', 7, 'HARDCODED_DB_CREDENTIAL', $fixtureLine),
    'exact path/line/category/fingerprint exception is accepted'
);
preflightContractCheck(
    !a4PreflightFixtureAllowed($fixtureException, 'tools/tests/fixture.php', 8, 'HARDCODED_DB_CREDENTIAL', $fixtureLine)
    && !a4PreflightFixtureAllowed($fixtureException, 'tools/tests/fixture.php', 7, 'HARDCODED_DB_CREDENTIAL', $fixtureLine . ' changed'),
    'line or content change invalidates fixture exception'
);

// Reviewed disposable-only Finance fixtures. An exception is NOT permission to
// package a test router or ignore later credentials added anywhere in the file.
require_once dirname(__DIR__) . '/release/CustomerReleaseProfile.php';
$sourceRoot = dirname(__DIR__, 2);
$customerProfile = CustomerReleaseProfile::fromRoot($sourceRoot);
$packagePolicy = new ReleasePackagePolicy($policy);
$fixturePaths = [
    'tools/tests/finance_control_operations_cases.php' => 16,
    'tools/tests/finance_control_operations_http_fixture.php' => 13,
    'tools/tests/finance_mutation_reporting_smoke.php' => 66,
];
foreach ($fixturePaths as $path => $lineNumber) {
    $lines = file($sourceRoot . '/' . $path, FILE_IGNORE_NEW_LINES);
    $line = $lines[$lineNumber - 1];
    $exceptions = $policy['secret_fixture_exceptions'];
    $category = 'HARDCODED_DB_CREDENTIAL';
    preflightContractCheck(
        a4PreflightFixtureAllowed($exceptions, $path, $lineNumber, $category, $line),
        'reviewed fixture exact fingerprint accepted: ' . $path
    );
    preflightContractCheck(
        !$customerProfile->allows($path) && $packagePolicy->included($path) && !$packagePolicy->denied($path),
        'fixture remains scanned but excluded from customer package: ' . $path
    );
    foreach ([
        ['tools/tests/unreviewed.php', $lineNumber, $category, $line],
        [$path, $lineNumber + 1, $category, $line],
        [$path, $lineNumber, 'HARDCODED_SECRET_LITERAL', $line],
        [$path, $lineNumber, $category, $line . ' changed'],
    ] as $index => $mismatch) {
        preflightContractCheck(
            !a4PreflightFixtureAllowed($exceptions, ...$mismatch),
            'fixture binding mismatch ' . $index . ' rejected: ' . $path
        );
    }
    $altered = $exceptions;
    foreach ($altered as &$exception) {
        if ($exception['path'] === $path) $exception['sha256'] = str_repeat('0', 64);
    }
    unset($exception);
    preflightContractCheck(
        !a4PreflightFixtureAllowed($altered, $path, $lineNumber, $category, $line),
        'altered exception digest rejected: ' . $path
    );
}

$temporaryDirectory = sys_get_temp_dir() . '/finance-a4-preflight-contract-' . bin2hex(random_bytes(6));
mkdir($temporaryDirectory, 0700);
$smallFile = $temporaryDirectory . '/small.ini';
$largeFile = $temporaryDirectory . '/large.ini';
$linkFile = $temporaryDirectory . '/linked.ini';
file_put_contents($smallFile, 'small');
file_put_contents($largeFile, str_repeat('x', 9));
$linkCreated = @symlink($smallFile, $linkFile);

preflightContractCheck(
    a4PreflightFileProblem($largeFile, true, 8) === 'SECRET_SCAN_OVERSIZE',
    'oversize secret-scan candidate fails closed'
);
preflightContractCheck(
    $linkCreated && a4PreflightFileProblem($linkFile, true, 8) === 'PACKAGE_SYMLINK',
    'symlink candidate fails closed without following target'
);
preflightContractCheck(
    a4PreflightFileProblem($temporaryDirectory . '/missing.ini', true, 8) === 'PACKAGE_FILE_UNREADABLE',
    'missing or unreadable candidate fails closed'
);

$secretMarker = 'never-print-this-secret';
$formatted = a4PreflightFormatIssue([
    'category' => 'HARDCODED_SECRET_LITERAL',
    'path' => 'fixture.ini',
    'line' => 4,
    'matched_value' => $secretMarker,
]);
preflightContractCheck(
    $formatted === 'FINDING HARDCODED_SECRET_LITERAL fixture.ini:4'
    && strpos($formatted, $secretMarker) === false,
    'finding output contains only category/path/line'
);

if ($linkCreated) {
    unlink($linkFile);
}
unlink($smallFile);
unlink($largeFile);
rmdir($temporaryDirectory);

echo 'A4 RELEASE PREFLIGHT CONTRACT ' . ($failed === 0 ? 'PASS' : 'FAIL')
    . ' passed=' . $passed . ' failed=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
