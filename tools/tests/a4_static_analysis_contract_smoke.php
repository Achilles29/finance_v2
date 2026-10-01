<?php

declare(strict_types=1);

define('A4_STATIC_ANALYSIS_LIBRARY_ONLY', true);
require __DIR__ . '/a4_static_analysis_smoke.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failed++;
    echo 'FAIL: ' . $label . PHP_EOL;
};

$root = dirname(__DIR__, 2);
$config = json_decode((string)file_get_contents($root . '/tools/static/toolchain.lock.json'), true);
$check(a4StaticToolchainValid($config), 'static toolchain lock is exact and bounded');
$source = a4StaticSourceContract($config, $root);
$check($source['ok'], 'Composer pin, full application scope, stubs, config, and baseline cap are valid');

$badVersion = $config;
$badVersion['phpstan']['version'] = '^1.12';
$check(!a4StaticToolchainValid($badVersion), 'non-exact PHPStan version fails closed');
$badTimeout = $config;
$badTimeout['analysis']['timeout_seconds'] = 600;
$check(!a4StaticToolchainValid($badTimeout), 'unbounded timeout fails closed');
$check($config['analysis']['timeout_seconds'] === 360, 'cold analysis has a bounded six-minute budget');
$check(a4StaticCacheDirectory('/runtime', $root) === a4StaticCacheDirectory('/runtime', $root), 'cache is stable within one checkout');
$check(a4StaticCacheDirectory('/runtime', $root) !== a4StaticCacheDirectory('/runtime', '/different-checkout'), 'independent checkouts never share a result cache');
$badCapture = $config;
$badCapture['analysis']['maximum_captured_bytes'] = 2097152;
$check(!a4StaticToolchainValid($badCapture), 'unbounded captured output fails closed');

$emptyStats = a4StaticBaselineStats("parameters:\n    ignoreErrors: []\n");
$check($emptyStats === ['entries' => 0, 'errors' => 0], 'empty baseline has deterministic zero count');
$fixtureStats = a4StaticBaselineStats("parameters:\n    ignoreErrors:\n        -\n            message: '#^one$#'\n            count: 2\n            path: one.php\n        -\n            message: '#^two$#'\n            count: 3\n            path: two.php\n");
$check($fixtureStats === ['entries' => 2, 'errors' => 5], 'baseline count sums occurrences rather than entries');
$check(a4StaticBaselineStats("parameters:\n    ignoreErrors:\n        -\n            message: '#^missing count$#'\n            path: one.php\n") === null, 'malformed baseline fails closed');

$probe = a4StaticRun([PHP_BINARY, '-r', 'fwrite(STDOUT, "{\\"ok\\":true}");'], $root, 10, 65536);
$check($probe['code'] === 0 && !$probe['timeout'] && !$probe['overflow'] && json_decode($probe['stdout'], true)['ok'] === true, 'bounded subprocess captures valid JSON');
$overflow = a4StaticRun([PHP_BINARY, '-r', 'fwrite(STDOUT, str_repeat("x", 70000));'], $root, 10, 65536);
$check($overflow['overflow'] && strlen($overflow['stdout']) <= 65536, 'oversized subprocess output fails closed');

$bootstrap = (string)file_get_contents(__DIR__ . '/bootstrap_a4_static_runtime.sh');
$runner = (string)file_get_contents(__DIR__ . '/a4_static_analysis_smoke.php');
$check(strpos($bootstrap, 'COMPOSER_VENDOR_DIR="$VENDOR_DIR"') !== false, 'Composer vendor runtime is external to the repository');
$check(strpos($bootstrap, '--no-scripts') !== false, 'static bootstrap cannot execute project Composer scripts');
$check(strpos($runner, "'--error-format=json'") !== false, 'runtime requires a machine-readable PHPStan report');
$check(strpos($runner, "'application'") !== false || strpos($runner, "scope_relative_path") !== false, 'runtime analysis scope cannot omit application');
$check(strpos($runner, '$analysisEnvironment[$config[\'runtime\'][\'environment\']] = $runtimeDirectory;') !== false, 'runtime injects the resolved external tmp directory into PHPStan');
$check(strpos($runner, "\$analysisEnvironment['A4_STATIC_CACHE_DIR'] = \$cacheDirectory;") !== false, 'PHPStan receives the checkout-specific cache directory');
$builder = (string)file_get_contents(dirname(__DIR__) . '/release/build_release_artifact.php');
$gate = (string)file_get_contents(__DIR__ . '/finance_quality_gate.php');
$check(strpos($builder, "\$label === 'STATIC' ? 420 : 180") !== false && strpos($gate, "\$manifest['static'], \$root, 420") !== false, 'outer build and quality gate budgets exceed the inner cold-analysis budget');

echo 'A4 STATIC ANALYSIS CONTRACT ' . ($failed === 0 ? 'PASS' : 'FAIL')
    . ' passed=' . $passed . ' failed=' . $failed . PHP_EOL;
exit($failed === 0 ? 0 : 1);
