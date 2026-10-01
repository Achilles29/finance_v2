<?php

declare(strict_types=1);

/**
 * DB-free smoke test for the P0-06A-1 deployment configuration contract.
 *
 * It uses resolver snapshots and a child process with an empty environment;
 * it does not modify the parent process environment or connect to a database.
 */

$root = dirname(__DIR__, 2);
$resolverPath = $root . '/application/libraries/DeploymentConfig.php';
$configPath = $root . '/application/config/config.php';
$databasePath = $root . '/application/config/database.php';
$indexPath = $root . '/index.php';
$checks = 0;
$failures = [];

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

require_once $resolverPath;

function smoke_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function fixture_value(string $name): string
{
    return '__synthetic_fixture_' . strtolower($name) . '__';
}

function complete_fixture(): array
{
    $snapshot = [];
    foreach (DeploymentConfig::productionRequiredNames() as $name) {
        $snapshot[$name] = fixture_value($name);
    }
    return $snapshot;
}

function load_config_fixture(DeploymentConfig $resolver, string $path): array
{
    $config = [];
    $finance_deployment_config = $resolver;
    include $path;
    return $config;
}

function load_database_fixture(DeploymentConfig $resolver, string $path): array
{
    $db = [];
    $finance_deployment_config = $resolver;
    include $path;
    return $db;
}

function run_production_missing_preflight(string $indexPath, string $root): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $environment = [
        'CI_ENV' => 'production',
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
    ];
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($indexPath);
    $process = proc_open($command, $descriptors, $pipes, $root, $environment);
    if (!is_resource($process)) {
        return [1, ''];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    return [$status, $stdout . $stderr];
}

$required = DeploymentConfig::productionRequiredNames();
$missing = DeploymentConfig::fromSnapshot([]);
$complete = DeploymentConfig::fromSnapshot(complete_fixture());

smoke_check(
    $missing->missingRequired() === $required,
    'Missing snapshot did not report the complete required-name set'
);
smoke_check(!$missing->validateProductionSecretContract(), 'Missing snapshot passed production validation');
smoke_check($complete->validateProductionSecretContract(), 'Complete synthetic snapshot failed production validation');

foreach ($required as $name) {
    $blankFixture = complete_fixture();
    $blankFixture[$name] = '';
    $blankResolver = DeploymentConfig::fromSnapshot($blankFixture);
    smoke_check(!$blankResolver->validateProductionSecretContract(), "Blank value passed validation for {$name}");
    smoke_check(in_array($name, $blankResolver->missingRequired(), true), "Blank value was not reported for {$name}");

    $whitespaceFixture = complete_fixture();
    $whitespaceFixture[$name] = " \t\n ";
    $whitespaceResolver = DeploymentConfig::fromSnapshot($whitespaceFixture);
    smoke_check(!$whitespaceResolver->validateProductionSecretContract(), "Whitespace value passed validation for {$name}");
    smoke_check(in_array($name, $whitespaceResolver->missingRequired(), true), "Whitespace value was not reported for {$name}");
}

$requiredValue = $complete->required(DeploymentConfig::ENCRYPTION_KEY);
smoke_check(
    $requiredValue === fixture_value(DeploymentConfig::ENCRYPTION_KEY),
    'Required resolver value did not come from the fixture snapshot'
);

$requiredThrew = false;
try {
    $missing->required(DeploymentConfig::ENCRYPTION_KEY);
} catch (RuntimeException $exception) {
    $requiredThrew = true;
    smoke_check(
        !str_contains($exception->getMessage(), fixture_value(DeploymentConfig::ENCRYPTION_KEY)),
        'Required-value exception contained a fixture value'
    );
}
smoke_check($requiredThrew, 'Missing required value did not fail closed');

$config = load_config_fixture($complete, $configPath);
smoke_check(
    $config['encryption_key'] === fixture_value(DeploymentConfig::ENCRYPTION_KEY),
    'Application encryption configuration did not use the resolver'
);

$database = load_database_fixture($complete, $databasePath);
smoke_check(
    $database['default']['hostname'] === fixture_value(DeploymentConfig::DB_HOST)
        && $database['default']['username'] === fixture_value(DeploymentConfig::DB_USER)
        && $database['default']['password'] === fixture_value(DeploymentConfig::DB_PASSWORD)
        && $database['default']['database'] === fixture_value(DeploymentConfig::DB_NAME),
    'Database configuration did not use resolver values'
);
smoke_check($database['default']['dbdriver'] === 'mysqli', 'Database driver changed');
smoke_check($database['default']['pconnect'] === false, 'Database persistent connection option changed');
smoke_check($database['default']['save_queries'] === false, 'Database save_queries option changed');

[$preflightStatus, $preflightOutput] = run_production_missing_preflight($indexPath, $root);
smoke_check($preflightStatus !== 0, 'Production preflight did not fail for a missing contract');
smoke_check(
    str_contains($preflightOutput, 'Service temporarily unavailable.'),
    'Production preflight did not return the generic unavailable response'
);
smoke_check(!str_contains($preflightOutput, 'FINANCE_'), 'Preflight output contained a configuration name');
foreach (complete_fixture() as $fixture) {
    smoke_check(!str_contains($preflightOutput, $fixture), 'Preflight output contained a fixture value');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL: deployment secret config smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: {$checks} deployment secret config checks\n";
