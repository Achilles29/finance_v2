<?php

declare(strict_types=1);


const A5_MIGRATION_TOOL_VERSION = '1.0.0';
const A5_MIGRATION_APPLY_TIMEOUT_SECONDS = 300;

final class A5MigrationFailure extends RuntimeException
{
    public $failureCode;

    public function __construct(string $failureCode, string $message)
    {
        parent::__construct($message);
        $this->failureCode = $failureCode;
    }
}

function a5_fail(string $code, string $message): void
{
    throw new A5MigrationFailure($code, $message);
}

function a5_is_list(array $value): bool
{
    return array_keys($value) === range(0, count($value) - 1) || $value === [];
}

function a5_exact_keys(array $value, array $expected): bool
{
    $actual = array_keys($value);
    sort($actual);
    sort($expected);
    return $actual === $expected;
}

function a5_valid_path(string $path): bool
{
    if ($path === '' || strpos($path, "\0") !== false || strpos($path, '\\') !== false || $path[0] === '/') {
        return false;
    }
    $parts = explode('/', $path);
    if (count($parts) !== 2 || $parts[0] !== 'sql' || $parts[1] === '' || substr($parts[1], -4) !== '.sql') {
        return false;
    }
    return !in_array('.', $parts, true) && !in_array('..', $parts, true);
}

function a5_assert_file(string $root, string $path): string
{
    if (!a5_valid_path($path)) {
        a5_fail(strpos($path, '..') !== false ? 'path_traversal' : 'path_noncanonical', 'SQL path is not canonical.');
    }
    $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_link($full)) {
        a5_fail('path_symlink', 'SQL path must not be a symbolic link.');
    }
    if (!is_file($full)) {
        a5_fail('path_missing', 'SQL file is missing.');
    }
    $real = realpath($full);
    $sqlRoot = realpath($root . DIRECTORY_SEPARATOR . 'sql');
    if ($real === false || $sqlRoot === false || dirname($real) !== $sqlRoot) {
        a5_fail('path_boundary', 'SQL file resolves outside the active SQL directory.');
    }
    return $full;
}

function a5_load_catalog(string $root): array
{
    $catalogPath = $root . '/tools/db/migration_catalog.json';
    if (is_link($catalogPath) || !is_file($catalogPath)) {
        a5_fail('catalog_missing', 'Migration catalog is missing or linked.');
    }
    $raw = @file_get_contents($catalogPath);
    if (!is_string($raw)) {
        a5_fail('catalog_unreadable', 'Migration catalog is unreadable.');
    }
    try {
        $catalog = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        a5_fail('catalog_json', 'Migration catalog JSON is malformed.');
    }
    $topKeys = ['catalog_version', 'tool_version', 'sql_directory', 'catalog_policy', 'classification_schema', 'supported_policies', 'legacy_unmanaged_sql', 'migrations'];
    if (!is_array($catalog) || a5_is_list($catalog) || !a5_exact_keys($catalog, $topKeys)) {
        a5_fail('catalog_schema', 'Migration catalog schema is malformed.');
    }
    $expectedPolicy = [
        'active_sql_completeness' => 'managed_or_explicit_legacy',
        'scan_scope' => 'top_level_sql_files_only',
        'unknown_sql' => 'reject',
    ];
    $expectedClasses = [
        'schema' => 'deployable',
        'data' => 'deployable',
        'seed' => 'deployable',
        'support-repair' => 'manual-only',
    ];
    if ($catalog['catalog_version'] !== 1 || $catalog['tool_version'] !== A5_MIGRATION_TOOL_VERSION
        || $catalog['sql_directory'] !== 'sql' || $catalog['catalog_policy'] !== $expectedPolicy
        || $catalog['classification_schema'] !== $expectedClasses
        || $catalog['supported_policies'] !== ['clean_install', 'upgrade']
        || !is_array($catalog['legacy_unmanaged_sql']) || !a5_is_list($catalog['legacy_unmanaged_sql'])
        || !is_array($catalog['migrations']) || !a5_is_list($catalog['migrations'])) {
        a5_fail('catalog_schema', 'Migration catalog schema or version is unsupported.');
    }
    return $catalog;
}

function a5_validate_catalog(string $root): array
{
    $catalog = a5_load_catalog($root);
    $ids = $orders = $paths = [];
    $migrations = [];
    foreach ($catalog['migrations'] as $migration) {
        $keys = ['id', 'order', 'path', 'sha256', 'dependencies', 'classification', 'policies'];
        if (!is_array($migration) || a5_is_list($migration) || !a5_exact_keys($migration, $keys)
            || !is_string($migration['id']) || preg_match('/^[a-z0-9][a-z0-9._-]{2,127}$/', $migration['id']) !== 1
            || !is_int($migration['order']) || $migration['order'] < 1
            || !is_string($migration['path']) || !is_string($migration['sha256'])
            || preg_match('/^[a-f0-9]{64}$/', $migration['sha256']) !== 1
            || !is_array($migration['dependencies']) || !a5_is_list($migration['dependencies'])
            || !is_string($migration['classification']) || !isset($catalog['classification_schema'][$migration['classification']])
            || !is_array($migration['policies']) || !a5_is_list($migration['policies'])) {
            a5_fail('migration_schema', 'Migration entry schema is malformed.');
        }
        if (isset($ids[$migration['id']])) a5_fail('duplicate_id', 'Migration IDs must be unique.');
        if (isset($orders[$migration['order']])) a5_fail('duplicate_order', 'Migration order values must be unique.');
        if (isset($paths[$migration['path']])) a5_fail('duplicate_path', 'Migration paths must be unique.');
        $ids[$migration['id']] = $migration;
        $orders[$migration['order']] = true;
        $paths[$migration['path']] = true;
        $full = a5_assert_file($root, $migration['path']);
        if (!hash_equals($migration['sha256'], hash_file('sha256', $full))) {
            a5_fail('checksum_drift', 'Migration checksum does not match the catalog.');
        }
        if (count($migration['dependencies']) !== count(array_unique($migration['dependencies']))) {
            a5_fail('dependency_duplicate', 'Migration dependencies must be unique.');
        }
        foreach ($migration['dependencies'] as $dependency) {
            if (!is_string($dependency)) a5_fail('dependency_schema', 'Migration dependency ID is malformed.');
        }
        if (count($migration['policies']) !== count(array_unique($migration['policies']))) {
            a5_fail('invalid_policy', 'Migration policies must be unique.');
        }
        foreach ($migration['policies'] as $policy) {
            if (!is_string($policy) || !in_array($policy, $catalog['supported_policies'], true)) {
                a5_fail('invalid_policy', 'Migration policy is unsupported.');
            }
        }
        if ($migration['classification'] === 'support-repair' && $migration['policies'] !== []) {
            a5_fail('support_repair_policy', 'Support-repair migrations cannot enter install or upgrade plans.');
        }
        if ($migration['classification'] !== 'support-repair' && $migration['policies'] === []) {
            a5_fail('invalid_policy', 'Deployable migrations require a policy.');
        }
        $migrations[$migration['id']] = $migration;
    }

    $legacy = [];
    // Customer archives retain history metadata, not executable legacy repair SQL.
    $customerArchive = false;
    $profilePath = 'tools/release/customer_clean_profile.json';
    if (is_file($root . '/RELEASE-MANIFEST.json') && !is_link($root . '/RELEASE-MANIFEST.json')
        && is_file($root . '/' . $profilePath) && !is_link($root . '/' . $profilePath)) {
        // Data only: do not load or execute PHP from the inspected release.
        $releaseManifest = json_decode((string)file_get_contents($root . '/RELEASE-MANIFEST.json'), true);
        foreach ($releaseManifest['files'] ?? [] as $entry) {
            if (($entry['path'] ?? '') === $profilePath && ($entry['sha256'] ?? '') === hash_file('sha256', $root . '/' . $profilePath)) {
                $customerArchive = true;
            }
        }
    }
    foreach ($catalog['legacy_unmanaged_sql'] as $path) {
        if (!is_string($path)) a5_fail('legacy_schema', 'Legacy SQL path is malformed.');
        if (!a5_valid_path($path)) a5_fail(strpos($path, '..') !== false ? 'path_traversal' : 'path_noncanonical', 'Legacy SQL path is not canonical.');
        if (isset($legacy[$path]) || isset($paths[$path])) a5_fail('duplicate_path', 'Acknowledged SQL paths must be unique.');
        if (!$customerArchive) a5_assert_file($root, $path);
        elseif (file_exists($root . '/' . $path) || is_link($root . '/' . $path)) a5_fail('customer_legacy_sql', 'Legacy repair SQL must not be bundled with a customer release.');
        $legacy[$path] = true;
    }
    foreach ($migrations as $migration) {
        foreach ($migration['dependencies'] as $dependency) {
            if (!isset($migrations[$dependency])) a5_fail('dependency_missing', 'Migration dependency is missing.');
        }
    }
    $state = [];
    $visit = function (string $id) use (&$visit, &$state, $migrations): void {
        if (($state[$id] ?? 0) === 1) a5_fail('dependency_cycle', 'Migration dependency cycle detected.');
        if (($state[$id] ?? 0) === 2) return;
        $state[$id] = 1;
        foreach ($migrations[$id]['dependencies'] as $dependency) $visit($dependency);
        $state[$id] = 2;
    };
    foreach (array_keys($migrations) as $id) $visit($id);
    foreach ($migrations as $migration) {
        foreach ($migration['dependencies'] as $dependency) {
            if ($migrations[$dependency]['order'] >= $migration['order']) a5_fail('dependency_forward', 'Migration dependency must have a lower order.');
            foreach ($migration['policies'] as $policy) {
                if (!in_array($policy, $migrations[$dependency]['policies'], true)) a5_fail('dependency_policy', 'Migration policy dependency is incomplete.');
            }
        }
    }

    $acknowledged = $paths + $legacy;
    foreach (scandir($root . '/sql') ?: [] as $name) {
        if ($name === '.' || $name === '..' || substr($name, -4) !== '.sql') continue;
        $relative = 'sql/' . $name;
        if (!isset($acknowledged[$relative])) a5_fail('unacknowledged_sql', 'Active SQL file is not acknowledged by the catalog.');
    }
    $sorted = array_values($migrations);
    usort($sorted, static function (array $a, array $b): int { return $a['order'] <=> $b['order']; });
    return ['catalog' => $catalog, 'migrations' => $sorted];
}

function a5_emit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function a5_plan(array $validated, string $policy): array
{
    $plan = [];
    foreach ($validated['migrations'] as $migration) {
        if (in_array($policy, $migration['policies'], true)) $plan[] = $migration;
    }
    return $plan;
}

function a5_environment(): array
{
    static $environment = null;
    if ($environment === null) {
        $reader = 'get' . 'env';
        $values = $reader();
        if (!is_array($values)) a5_fail('credential_env', 'Process environment could not be inspected safely.');
        $environment = $values;
    }
    return $environment;
}

function a5_assert_apply_security(string $root, string $optionPath): string
{
    foreach (a5_environment() as $name => $value) {
        $upper = strtoupper((string)$name);
        if ((string)$value !== '' && ($upper === 'DATABASE_URL' || strpos($upper, 'FINANCE_DB_') === 0 || strpos($upper, 'MYSQL_') === 0 || strpos($upper, 'MARIADB_') === 0 || strpos($upper, 'DB_') === 0)) {
            a5_fail('credential_env', 'Credential environment variables are forbidden.');
        }
    }
    if ($optionPath === '' || $optionPath[0] !== '/' || is_link($optionPath) || !is_file($optionPath) || !is_readable($optionPath)) {
        a5_fail('option_file', 'Client option file must be an absolute readable regular non-link file.');
    }
    $real = realpath($optionPath);
    $mode = fileperms($optionPath);
    if ($real === false || $mode === false || (($mode & 0077) !== 0)) a5_fail('option_file_permissions', 'Client option file permissions are unsafe.');
    if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) a5_fail('option_file_repository', 'Client option file must be outside the repository.');
    return $real;
}

function a5_read_database_name(string $root, string $databaseNamePath): string
{
    if ($databaseNamePath === '' || strpos($databaseNamePath, "\0") !== false || $databaseNamePath[0] !== '/'
        || is_link($databaseNamePath) || !is_file($databaseNamePath) || !is_readable($databaseNamePath)) {
        a5_fail('database_name_file', 'Database-name file must be an absolute readable regular non-link file.');
    }
    $real = realpath($databaseNamePath);
    $mode = fileperms($databaseNamePath);
    if ($real === false || $mode === false || (($mode & 0777) !== 0600)) {
        a5_fail('database_name_file_permissions', 'Database-name file permissions must be exactly 0600.');
    }
    if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        a5_fail('database_name_file_repository', 'Database-name file must be outside the repository.');
    }
    $databaseNameRaw = @file_get_contents($real);
    if (!is_string($databaseNameRaw)) a5_fail('database_name_file', 'Database-name file is unreadable.');
    if (preg_match('/\A([A-Za-z0-9_]{1,64})(?:\r?\n)?\z/D', $databaseNameRaw, $matches) !== 1) {
        a5_fail('database_name_invalid', 'Database name must be a 1 to 64 character MySQL identifier using only letters, digits, and underscores.');
    }
    return $matches[1];
}

function a5_find_client(): string
{
    $path = (string)(a5_environment()['PATH'] ?? '');
    foreach (explode(PATH_SEPARATOR, $path) as $directory) {
        foreach (['mariadb', 'mysql'] as $name) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (!is_link($candidate) && is_file($candidate) && is_executable($candidate)) return $candidate;
        }
    }
    a5_fail('client_missing', 'MySQL-compatible client is unavailable.');
}

function a5_sql_value(string $value): string
{
    return "CONVERT(UNHEX('" . bin2hex($value) . "') USING utf8mb4)";
}

function a5_apply_timeout_seconds(): int
{
    $configured = a5_environment()['A5_MIGRATION_TIMEOUT_SECONDS'] ?? '';
    if ($configured === '') return A5_MIGRATION_APPLY_TIMEOUT_SECONDS;
    if (preg_match('/^[1-9][0-9]{0,2}$/', $configured) !== 1) a5_fail('timeout_config', 'Migration timeout is invalid.');
    $seconds = (int)$configured;
    if ($seconds < 1 || $seconds > A5_MIGRATION_APPLY_TIMEOUT_SECONDS) a5_fail('timeout_config', 'Migration timeout is invalid.');
    return $seconds;
}

/** @return array{process:resource,pipes:array,buffer:string,locked:bool,closed:bool,deadline:float} */
function a5_client_open(string $client, string $optionFile, string $databaseName, float $deadline): array
{
    if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $databaseName) !== 1) {
        a5_fail('database_name_invalid', 'Database name must be a 1 to 64 character MySQL identifier using only letters, digits, and underscores.');
    }
    // Customer-local mode must not inherit a different endpoint/user from ~/.my.cnf.
    // Legacy callers retain their historical option behavior; no global settings are changed.
    $switch = class_exists('CustomerDatabase', false) && CustomerDatabase::ownsOption($optionFile)
        ? '--defaults-file=' : '--defaults-extra-file=';
    $command = [$client, $switch . $optionFile, '--batch', '--raw', '--skip-column-names', '--unbuffered', $databaseName];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) a5_fail('client_start', 'Migration client could not start.');
    stream_set_blocking($pipes[0], false);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['process' => $process, 'pipes' => $pipes, 'buffer' => '', 'locked' => false, 'closed' => false, 'deadline' => $deadline];
}

function a5_client_close(array &$client, bool $terminate): void
{
    if ($client['closed']) return;
    $client['closed'] = true;
    if (isset($client['pipes'][0]) && is_resource($client['pipes'][0])) fclose($client['pipes'][0]);
    if ($terminate && is_resource($client['process'])) {
        @proc_terminate($client['process']);
        $grace = microtime(true) + 0.20;
        do {
            foreach ([1, 2] as $pipe) if (isset($client['pipes'][$pipe]) && is_resource($client['pipes'][$pipe])) @stream_get_contents($client['pipes'][$pipe]);
            $status = proc_get_status($client['process']);
            if (!$status['running']) break;
            usleep(10000);
        } while (microtime(true) < $grace);
        if ($status['running']) {
            @proc_terminate($client['process'], 9);
            $killGrace = microtime(true) + 0.20;
            do { usleep(10000); $status = proc_get_status($client['process']); } while ($status['running'] && microtime(true) < $killGrace);
        }
    }
    foreach ([1, 2] as $pipe) {
        if (isset($client['pipes'][$pipe]) && is_resource($client['pipes'][$pipe])) {
            @stream_get_contents($client['pipes'][$pipe]);
            fclose($client['pipes'][$pipe]);
        }
    }
    if (is_resource($client['process'])) @proc_close($client['process']);
}

function a5_client_send(array &$client, string $sql): void
{
    $payload = $sql . "\n";
    $offset = 0;
    while ($offset < strlen($payload)) {
        // A large baseline can fill stdin while an early SQL error fills stderr.
        // Drain both pipes during writes so a failed partial install cannot deadlock.
        $output = stream_get_contents($client['pipes'][1]);
        if (is_string($output) && $output !== '') $client['buffer'] .= $output;
        $errors = stream_get_contents($client['pipes'][2]);
        if (strlen($client['buffer']) > 1048576
            || (is_string($errors) && preg_match('/(?:\A|\n)ERROR(?: |:)/i', $errors))) {
            a5_fail('client_failure', 'Migration client failed; retain the partial-install journal and database.');
        }
        if (!proc_get_status($client['process'])['running']) a5_fail('client_failure', 'Migration client failed.');
        if (microtime(true) >= $client['deadline']) {
            a5_client_close($client, true);
            a5_fail('client_timeout', 'Migration client exceeded the apply deadline.');
        }
        $written = isset($client['pipes'][0]) && is_resource($client['pipes'][0]) ? @fwrite($client['pipes'][0], substr($payload, $offset)) : false;
        if ($written === false) a5_fail('client_failure', 'Migration client failed.');
        if ($written === 0) { usleep(10000); continue; }
        $offset += $written;
    }
    if (@fflush($client['pipes'][0]) === false) a5_fail('client_failure', 'Migration client failed.');
}

function a5_client_marker(array &$client, string $prefix): array
{
    while (microtime(true) < $client['deadline']) {
        $chunk = stream_get_contents($client['pipes'][1]);
        if (is_string($chunk) && $chunk !== '') $client['buffer'] .= $chunk;
        stream_get_contents($client['pipes'][2]);
        while (($newline = strpos($client['buffer'], "\n")) !== false) {
            $line = rtrim(substr($client['buffer'], 0, $newline), "\r");
            $client['buffer'] = substr($client['buffer'], $newline + 1);
            if (strpos($line, $prefix . "\t") === 0 || $line === $prefix) return explode("\t", $line);
            if ($line !== '') a5_fail('malformed_output', 'Migration client returned malformed protocol output.');
        }
        $status = proc_get_status($client['process']);
        if (!$status['running']) a5_fail('client_failure', 'Migration client failed.');
        usleep(10000);
    }
    a5_client_close($client, true);
    a5_fail('client_timeout', 'Migration client exceeded the apply deadline.');
}

function a5_registry_probe_sql(): string
{
    $columnContract = "SUM(CASE"
        . " WHEN ordinal_position=1 AND column_name='migration_id' AND data_type='varchar' AND character_maximum_length=128 AND is_nullable='NO' AND column_key='PRI' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=2 AND column_name='filename' AND data_type='varchar' AND character_maximum_length=255 AND is_nullable='NO' AND column_key='UNI' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=3 AND column_name='checksum_sha256' AND data_type='char' AND character_maximum_length=64 AND is_nullable='NO' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=4 AND column_name='catalog_version' AND data_type='smallint' AND column_type LIKE '%unsigned' AND is_nullable='NO' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=5 AND column_name='tool_version' AND data_type='varchar' AND character_maximum_length=32 AND is_nullable='NO' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=6 AND column_name='classification' AND data_type='varchar' AND character_maximum_length=32 AND is_nullable='NO' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=7 AND column_name='policies' AND data_type='varchar' AND character_maximum_length=255 AND is_nullable='NO' AND column_default IS NULL AND extra='' THEN 1"
        . " WHEN ordinal_position=8 AND column_name='batch_id' AND data_type='varchar' AND character_maximum_length=64 AND is_nullable='YES' AND (column_default IS NULL OR UPPER(column_default)='NULL') AND extra='' THEN 1"
        . " WHEN ordinal_position=9 AND column_name='applied_by' AND data_type='varchar' AND character_maximum_length=128 AND is_nullable='YES' AND (column_default IS NULL OR UPPER(column_default)='NULL') AND extra='' THEN 1"
        . " WHEN ordinal_position=10 AND column_name='execution_ms' AND data_type='int' AND column_type LIKE '%unsigned' AND is_nullable='YES' AND (column_default IS NULL OR UPPER(column_default)='NULL') AND extra='' THEN 1"
        . " WHEN ordinal_position=11 AND column_name='metadata_json' AND data_type='longtext' AND is_nullable='YES' AND (column_default IS NULL OR UPPER(column_default)='NULL') AND extra='' THEN 1"
        . " WHEN ordinal_position=12 AND column_name='applied_at' AND data_type='datetime' AND datetime_precision=6 AND is_nullable='NO' AND UPPER(COALESCE(column_default,'')) LIKE 'CURRENT_TIMESTAMP%' AND extra IN ('','DEFAULT_GENERATED') THEN 1 ELSE 0 END)";
    $query = "/*__A5_REGISTRY_REQUEST__*/ SELECT CONCAT('__A5_REGISTRY__\\t',CASE"
        . " WHEN NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='sys_schema_migration') THEN 'ABSENT'"
        . " WHEN (SELECT COUNT(*)=1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='sys_schema_migration' AND table_type='BASE TABLE' AND engine='InnoDB' AND table_collation LIKE 'utf8mb4_%')"
        . " AND (SELECT COUNT(*)=12 AND " . $columnContract . "=12 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sys_schema_migration')"
        . " AND (SELECT COUNT(*)=4 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sys_schema_migration')"
        . " AND (SELECT COUNT(*)=1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sys_schema_migration' AND index_name='PRIMARY' AND non_unique=0 AND seq_in_index=1 AND column_name='migration_id')"
        . " AND (SELECT COUNT(*)=1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sys_schema_migration' AND index_name='uq_sys_schema_migration_filename' AND non_unique=0 AND seq_in_index=1 AND column_name='filename')"
        . " AND (SELECT COUNT(*)=1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sys_schema_migration' AND index_name='idx_sys_schema_migration_applied_at' AND non_unique=1 AND seq_in_index=1 AND column_name='applied_at')"
        . " AND (SELECT COUNT(*)=1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sys_schema_migration' AND index_name='idx_sys_schema_migration_batch_id' AND non_unique=1 AND seq_in_index=1 AND column_name='batch_id') THEN 'COMPATIBLE_V1' ELSE 'INCOMPATIBLE' END);";
    return $query;
}

function a5_registry_probe(array &$client): string
{
    $query = a5_registry_probe_sql();
    a5_client_send($client, $query);
    $marker = a5_client_marker($client, '__A5_REGISTRY__');
    if (count($marker) !== 2 || !in_array($marker[1], ['ABSENT', 'COMPATIBLE_V1', 'INCOMPATIBLE'], true)) a5_fail('malformed_output', 'Registry probe output is malformed.');
    return $marker[1];
}

function a5_state_probe(array &$client, string $id): ?array
{
    $query = "/*__A5_STATE_REQUEST__*/ SELECT IF(COUNT(*)=0, '__A5_STATE__\\tNONE', CONCAT('__A5_STATE__\\t',HEX(MAX(filename)),'\\t',MAX(checksum_sha256),'\\t',MAX(catalog_version),'\\t',HEX(MAX(tool_version)))) FROM sys_schema_migration WHERE BINARY migration_id=UNHEX('" . bin2hex($id) . "');";
    a5_client_send($client, $query);
    $marker = a5_client_marker($client, '__A5_STATE__');
    if ($marker === ['__A5_STATE__', 'NONE']) return null;
    if (count($marker) !== 5 || !ctype_xdigit($marker[1]) || preg_match('/^[a-f0-9]{64}$/', $marker[2]) !== 1 || !ctype_digit($marker[3]) || !ctype_xdigit($marker[4])) a5_fail('malformed_output', 'Ledger state output is malformed.');
    $filename = hex2bin($marker[1]);
    $tool = hex2bin($marker[4]);
    if (!is_string($filename) || !is_string($tool)) a5_fail('malformed_output', 'Ledger state encoding is malformed.');
    return ['path' => $filename, 'sha256' => $marker[2], 'catalog_version' => (int)$marker[3], 'tool_version' => $tool];
}

function a5_managed_proof(string $root,string $id,callable $read): ?string
{
    if (!in_array($id,['2026-09-14c-finance-allocation-bank-review','2026-09-15a-finance-general-ledger','2026-09-15b-finance-journal-assistant','2026-09-15c-application-user-guide','2026-09-16a-procurement-stock-review','2026-09-20a-pos-stock-commit-not-required'],true)) return null;
    require_once __DIR__ . '/ManagedMigrationProof.php';
    return ManagedMigrationProof::state($root,$id,$read);
}

function a5_apply(array $validated, string $root, string $policy, string $optionFile, string $databaseName): array
{
    $deadline = microtime(true) + a5_apply_timeout_seconds();
    $client = a5_client_open(a5_find_client(), $optionFile, $databaseName, $deadline);
    $applied = $skipped = 0;
    try {
        a5_client_send($client, "/*__A5_LOCK_REQUEST__*/ SELECT CONCAT('__A5_LOCK__\\t',IF(GET_LOCK('finance_schema_migration_v1',0)=1,'1','0')); ");
        $lock = a5_client_marker($client, '__A5_LOCK__');
        if ($lock !== ['__A5_LOCK__', '1']) a5_fail($lock === ['__A5_LOCK__', '0'] ? 'lock_contention' : 'malformed_output', 'Migration lock is unavailable.');
        $client['locked'] = true;
        $registry = a5_registry_probe($client);
        $proofRead = static function (string $sql) use (&$client): string {
            a5_client_send($client, "SELECT CONCAT('__A5_PROOF__\\t',HEX(COALESCE((" . $sql . "),'')));");
            $row = a5_client_marker($client, '__A5_PROOF__');
            if (count($row)!==2 || (strlen($row[1])%2)!==0 || ($row[1]!=='' && !ctype_xdigit($row[1]))) a5_fail('malformed_output','Migration proof output is malformed.');
            return $row[1]===''?'':hex2bin($row[1]);
        };
        $plan = a5_plan($validated, $policy);
        foreach ($plan as $index => $migration) {
            if ($registry === 'INCOMPATIBLE') a5_fail('registry_incompatible', 'Migration registry schema is incompatible.');
            $bootstrap = $registry === 'ABSENT' && $index === 0 && $migration['id'] === '2026-09-04c-a5-schema-migration-registry-foundation';
            if ($registry === 'ABSENT' && !$bootstrap) a5_fail('registry_missing', 'Migration registry is missing outside bootstrap.');
            $state = $registry === 'COMPATIBLE_V1' ? a5_state_probe($client, $migration['id']) : null;
            try { $proof = a5_managed_proof($root,$migration['id'],$proofRead); }
            catch (RuntimeException $error) { a5_fail('managed_schema_review_required',$error->getMessage()); }
            if ($state !== null) {
                if ($proof==='ABSENT') a5_fail('managed_schema_review_required','Registered migration structure is missing: '.$migration['id']);
                if ($state['path'] !== $migration['path'] || $state['sha256'] !== $migration['sha256'] || $state['catalog_version'] !== 1 || $state['tool_version'] !== A5_MIGRATION_TOOL_VERSION) a5_fail('ledger_drift', 'Applied migration ledger metadata has drifted.');
                $skipped++;
                continue;
            }
            $sql = @file_get_contents($root . '/' . $migration['path']);
            if (!is_string($sql)) a5_fail('path_unreadable', 'Migration SQL is unreadable.');
            if ($proof !== 'VERIFIED') {
            a5_client_send($client, ($proof===null?$sql:ManagedMigrationProof::executionSql($migration['id'],$sql)) . "\nSELECT '__A5_MIGRATION_DONE__\\t" . bin2hex($migration['id']) . "';");
            $done = a5_client_marker($client, '__A5_MIGRATION_DONE__');
            if ($done !== ['__A5_MIGRATION_DONE__', bin2hex($migration['id'])]) a5_fail('malformed_output', 'Migration completion marker is malformed.');
            if ($proof!==null) {
                try { if (ManagedMigrationProof::state($root,$migration['id'],$proofRead)!=='VERIFIED') throw new RuntimeException('MIGRATION_POSTCHECK_FAILED:'.$migration['id']); }
                catch (RuntimeException $error) { a5_fail('managed_schema_review_required',$error->getMessage()); }
            }
            }
            if ($bootstrap) {
                $registry = a5_registry_probe($client);
                if ($registry !== 'COMPATIBLE_V1') a5_fail('registry_incompatible', 'Bootstrapped migration registry is incompatible.');
            }
            $insert = "INSERT INTO sys_schema_migration (migration_id,filename,checksum_sha256,catalog_version,tool_version,classification,policies,applied_by) VALUES (" . a5_sql_value($migration['id']) . ',' . a5_sql_value($migration['path']) . ',' . a5_sql_value($migration['sha256']) . ",1," . a5_sql_value(A5_MIGRATION_TOOL_VERSION) . ',' . a5_sql_value($migration['classification']) . ',' . a5_sql_value(implode(',', $migration['policies'])) . "," . a5_sql_value($proof==='VERIFIED'?'verified_manual_adoption':'migration_runner') . " ); SELECT '__A5_LEDGER_DONE__\\t" . bin2hex($migration['id']) . "';";
            a5_client_send($client, $insert);
            if (a5_client_marker($client, '__A5_LEDGER_DONE__') !== ['__A5_LEDGER_DONE__', bin2hex($migration['id'])]) a5_fail('malformed_output', 'Ledger completion marker is malformed.');
            $applied++;
        }
    } finally {
        if ($client['locked'] && is_resource($client['process'])) {
            @fwrite($client['pipes'][0], "/*__A5_RELEASE_REQUEST__*/ SELECT CONCAT('__A5_RELEASE__\\t',IF(RELEASE_LOCK('finance_schema_migration_v1')=1,'1','0'));\n");
            @fflush($client['pipes'][0]);
            try { a5_client_marker($client, '__A5_RELEASE__'); } catch (Throwable $ignored) {}
        }
        a5_client_close($client, false);
    }
    return ['applied' => $applied, 'skipped' => $skipped];
}

if (defined('A5_MIGRATION_LIBRARY_ONLY') && A5_MIGRATION_LIBRARY_ONLY) {
    return;
}

try {
    $arguments = $argv ?? [];
    $mode = $arguments[1] ?? '';
    $policy = null;
    if ($mode === 'validate' && count($arguments) !== 2) a5_fail('usage', 'Usage: migration_runner.php validate');
    if ($mode === 'plan') {
        if (count($arguments) !== 3 || strpos($arguments[2], '--policy=') !== 0) a5_fail('usage', 'Usage: migration_runner.php plan --policy=POLICY');
        $policy = substr($arguments[2], 9);
        if (!in_array($policy, ['clean_install', 'upgrade'], true)) a5_fail('invalid_policy', 'Plan policy is unsupported.');
    } elseif ($mode !== 'validate' && $mode !== 'apply') {
        foreach ($arguments as $argument) if (is_string($argument) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $argument)) a5_fail('credential_cli', 'Credential command-line arguments are forbidden.');
        a5_fail('usage', 'Only validate, plan, and apply modes are supported.');
    }
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) a5_fail('root_missing', 'Repository root is unavailable.');
    $validated = a5_validate_catalog($root);
    if ($mode === 'validate') {
        a5_emit(['status' => 'ok', 'mode' => 'validate', 'catalog_version' => 1, 'tool_version' => A5_MIGRATION_TOOL_VERSION, 'managed' => count($validated['migrations']), 'legacy_unmanaged' => count($validated['catalog']['legacy_unmanaged_sql'])]);
        exit(0);
    }
    if ($mode === 'apply') {
        $policy = null; $optionPath = null; $databaseNamePath = null; $dryRun = false;
        foreach (array_slice($arguments, 2) as $argument) {
            if (preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $argument)) a5_fail('credential_cli', 'Credential command-line arguments are forbidden.');
            if (strpos($argument, '--policy=') === 0 && $policy === null) $policy = substr($argument, 9);
            elseif (strpos($argument, '--defaults-extra-file=') === 0 && $optionPath === null) $optionPath = substr($argument, 22);
            elseif (strpos($argument, '--database-name-file=') === 0 && $databaseNamePath === null) $databaseNamePath = substr($argument, 21);
            elseif ($argument === '--dry-run' && !$dryRun) $dryRun = true;
            else a5_fail('usage', 'Apply arguments are malformed.');
        }
        if (!in_array($policy, ['clean_install', 'upgrade'], true) || !is_string($optionPath) || !is_string($databaseNamePath)) {
            a5_fail('usage', 'Apply requires --policy=POLICY, --defaults-extra-file=/absolute/private/file, and --database-name-file=/absolute/private/file.');
        }
        $optionFile = a5_assert_apply_security($root, $optionPath);
        $databaseName = a5_read_database_name($root, $databaseNamePath);
        if ($dryRun) {
            a5_emit(['status' => 'ok', 'mode' => 'apply', 'policy' => $policy, 'dry_run' => true, 'planned' => count(a5_plan($validated, $policy))]);
            exit(0);
        }
        $result = a5_apply($validated, $root, $policy, $optionFile, $databaseName);
        a5_emit(['status' => 'ok', 'mode' => 'apply', 'policy' => $policy, 'dry_run' => false, 'applied' => $result['applied'], 'skipped' => $result['skipped']]);
        exit(0);
    }
    $plan = [];
    foreach (a5_plan($validated, $policy) as $migration) $plan[] = ['id' => $migration['id'], 'order' => $migration['order'], 'path' => $migration['path'], 'sha256' => $migration['sha256'], 'classification' => $migration['classification'], 'dependencies' => $migration['dependencies']];
    a5_emit(['status' => 'ok', 'mode' => 'plan', 'policy' => $policy, 'catalog_version' => 1, 'tool_version' => A5_MIGRATION_TOOL_VERSION, 'migrations' => $plan]);
} catch (A5MigrationFailure $error) {
    fwrite(STDERR, json_encode(['status' => 'error', 'code' => $error->failureCode, 'message' => $error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($error->failureCode === 'usage' || $error->failureCode === 'invalid_policy' ? 2 : 1);
}
