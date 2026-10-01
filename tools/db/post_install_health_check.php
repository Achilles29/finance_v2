<?php

declare(strict_types=1);

if (!defined('A5_MIGRATION_LIBRARY_ONLY')) {
    define('A5_MIGRATION_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/migration_runner.php';

if (!defined('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY')) {
    define('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/clean_install_baseline_guard.php';

final class A513HealthFailure extends RuntimeException
{
    public string $failureCode;

    public function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->failureCode = $code;
    }
}

function a513_fail(string $code, string $message): void
{
    throw new A513HealthFailure($code, $message);
}

function a513_emit(array $payload, $stream = null): void
{
    fwrite($stream ?? STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function a513_exact_keys(array $value, array $expected): bool
{
    $actual = array_keys($value);
    sort($actual);
    sort($expected);
    return $actual === $expected;
}

function a513_release_path(string $root, string $relative): string
{
    if ($relative === '' || $relative[0] === '/' || strpos($relative, "\0") !== false
        || strpos($relative, '\\') !== false || preg_match('#(?:^|/)\.\.?(/|$)#', $relative) === 1) {
        a513_fail('release_manifest_path', 'Release manifest contains an unsafe path.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_link($path) || !is_file($path)) {
        a513_fail('release_file_missing', 'A release file is missing or unsafe.');
    }
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        a513_fail('release_manifest_path', 'A release file resolves outside the release root.');
    }
    return $real;
}

function a513_validate_release(string $releaseRoot, string $manifestPath): array
{
    if ($releaseRoot === '' || $releaseRoot[0] !== '/' || is_link($releaseRoot) || !is_dir($releaseRoot)) {
        a513_fail('release_root', 'Release root must be an absolute canonical directory.');
    }
    $root = realpath($releaseRoot);
    if ($root === false || $root !== rtrim($releaseRoot, DIRECTORY_SEPARATOR)) {
        a513_fail('release_root', 'Release root must be an absolute canonical directory.');
    }
    if ($manifestPath !== $root . DIRECTORY_SEPARATOR . 'RELEASE-MANIFEST.json'
        || is_link($manifestPath) || !is_file($manifestPath) || realpath($manifestPath) !== $manifestPath) {
        a513_fail('release_manifest', 'Release manifest must be the canonical manifest inside the release root.');
    }
    try {
        $manifest = json_decode((string)file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        a513_fail('release_manifest_json', 'Release manifest JSON is malformed.');
    }
    if (!is_array($manifest) || !a513_exact_keys($manifest, ['schema','schema_version','source_epoch','files'])
        || ($manifest['schema'] ?? '') !== 'finance.release-artifact-manifest'
        || ($manifest['schema_version'] ?? null) !== 1 || !is_int($manifest['source_epoch'])
        || !is_array($manifest['files']) || array_keys($manifest['files']) !== range(0, count($manifest['files']) - 1)) {
        a513_fail('release_manifest_schema', 'Release manifest schema is unsupported.');
    }
    $required = [
        'tools/db/migration_catalog.json',
        'tools/db/clean_install_baseline_policy.json',
        'tools/db/clean_install_baseline_guard.php',
        'tools/db/migration_runner.php',
        'tools/db/post_install_health_check.php',
        'sql/baseline/2026-09-05_clean_install_schema.sql',
        'sql/2026-09-05d_a5_clean_install_reference_seed.sql',
    ];
    $seen = [];
    foreach ($manifest['files'] as $entry) {
        if (!is_array($entry) || !a513_exact_keys($entry, ['path','sha256','size','mode'])
            || !is_string($entry['path']) || isset($seen[$entry['path']])
            || !is_string($entry['sha256']) || preg_match('/^[a-f0-9]{64}$/D', $entry['sha256']) !== 1
            || !is_int($entry['size']) || $entry['size'] < 0
            || !in_array($entry['mode'], ['0644','0755'], true)) {
            a513_fail('release_manifest_entry', 'Release manifest contains an invalid file entry.');
        }
        $path = a513_release_path($root, $entry['path']);
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        $mode = fileperms($path);
        $normalizedMode = is_int($mode) && ($mode & 0111) !== 0 ? '0755' : '0644';
        if (!is_int($size) || $size !== $entry['size'] || !is_string($hash)
            || !hash_equals($entry['sha256'], $hash) || $normalizedMode !== $entry['mode']) {
            a513_fail('release_file_drift', 'Installed release files do not match the release manifest.');
        }
        $seen[$entry['path']] = true;
    }
    foreach ($required as $path) {
        if (!isset($seen[$path])) a513_fail('release_contract_missing', 'Release manifest is missing an installation contract file.');
    }
    $baseline = a512_validate_baseline($root, $root . '/tools/db/clean_install_baseline_policy.json');
    if (empty($baseline['ok'])) a513_fail('baseline_contract', 'Release baseline contract is invalid.');
    try {
        $catalog = a5_validate_catalog($root);
    } catch (A5MigrationFailure $error) {
        a513_fail('migration_contract', 'Release migration catalog is invalid.');
    }
    $manifestHash = hash_file('sha256', $manifestPath);
    if (!is_string($manifestHash)) a513_fail('release_manifest_read', 'Release manifest digest is unavailable.');
    return ['root'=>$root,'manifest'=>$manifest,'manifest_sha256'=>$manifestHash,'catalog'=>$catalog,'baseline'=>$baseline];
}

function a513_query_marker(string $binary, string $optionFile, string $databaseName, string $marker, string $sql, int $fields, string $probe = 'database'): array
{
    $process = proc_open(
        [$binary, '--defaults-extra-file=' . $optionFile, '--batch', '--raw', '--skip-column-names', '--connect-timeout=5', $databaseName],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],
        $pipes,
        null,
        ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']
    );
    if (!is_resource($process)) a513_fail('database_client', 'Health-check ' . $probe . ' client could not start.');
    fwrite($pipes[0], $sql . ";\n");
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $deadline = microtime(true) + min(30, a5_apply_timeout_seconds());
    $exit = null;
    while (true) {
        $chunk = stream_get_contents($pipes[1]);
        if (is_string($chunk) && $chunk !== '') $stdout .= $chunk;
        stream_get_contents($pipes[2]);
        if (strlen($stdout) > 4096) { @proc_terminate($process, 9); $exit=125; break; }
        $status = proc_get_status($process);
        if (!$status['running']) { $exit = $status['exitcode']; break; }
        if (microtime(true) >= $deadline) { @proc_terminate($process, 15); usleep(100000); $status=proc_get_status($process); if ($status['running']) @proc_terminate($process, 9); $exit=124; break; }
        usleep(10000);
    }
    $chunk = stream_get_contents($pipes[1]);
    if (is_string($chunk) && $chunk !== '') $stdout .= $chunk;
    foreach ([1,2] as $index) fclose($pipes[$index]);
    $closed = proc_close($process);
    $resultCode = $closed >= 0 ? $closed : $exit;
    if ($exit === 125) a513_fail('database_protocol', 'Health-check database output is oversized.');
    if ($resultCode !== 0) a513_fail($exit === 124 ? 'database_timeout' : 'database_client', 'Health-check ' . $probe . ' query failed with redacted output.');
    $lines = preg_split('/\R/', trim($stdout)) ?: [];
    if (count($lines) !== 1) a513_fail('database_protocol', 'Health-check database output is malformed.');
    $row = explode("\t", $lines[0]);
    if (count($row) !== $fields + 1 || $row[0] !== $marker) {
        a513_fail('database_protocol', 'Health-check database output is malformed.');
    }
    return array_slice($row, 1);
}

function a513_ledger_probe_sql(array $migration): string
{
    return "SELECT CONCAT('__A513_LEDGER__\\t',COUNT(*),'\\t',COALESCE(SUM(BINARY filename=UNHEX('" . bin2hex($migration['path'])
        . "') AND BINARY checksum_sha256=UNHEX('" . bin2hex($migration['sha256']) . "') AND catalog_version=1 AND BINARY tool_version=UNHEX('" . bin2hex(A5_MIGRATION_TOOL_VERSION)
        . "') AND BINARY classification=UNHEX('" . bin2hex($migration['classification']) . "') AND BINARY policies=UNHEX('" . bin2hex(implode(',', $migration['policies'])) . "')),0)) FROM sys_schema_migration WHERE BINARY migration_id=UNHEX('" . bin2hex($migration['id']) . "')";
}

function a513_permission_match_sql(): string
{
    // Match the least-privilege permissions deliberately seeded by each module;
    // do not grant extra mutations merely to satisfy the installer assertion.
    $write = "(CASE WHEN BINARY p.page_code IN ('tg.guide','finance.control.approve','finance.control.settings','finance.accounting.settings','system.guide.index','system.guide.server') THEN 0 ELSE 1 END)";
    $edit = "(CASE WHEN BINARY p.page_code IN ('tg.guide','finance.accounting.index','system.guide.index','system.guide.server') THEN 0 ELSE 1 END)";
    $deleteExport = "(CASE WHEN BINARY p.page_code IN ('tg.guide','system.roast_connect','finance.control.index','finance.control.approve','finance.control.settings','finance.accounting.index','finance.accounting.settings','system.guide.index','system.guide.server') THEN 0 ELSE 1 END)";
    return 'rp.can_view=1 AND rp.can_create=' . $write . ' AND rp.can_edit=' . $edit
        . ' AND rp.can_delete=' . $deleteExport . ' AND rp.can_export=' . $deleteExport;
}

function a513_check_database(array $release, string $policy, string $optionFile, string $databaseName): array
{
    if (!in_array($policy, ['clean_install','upgrade'], true)) a513_fail('policy', 'Health-check policy is unsupported.');
    $plan = a5_plan($release['catalog'], $policy);
    $baselinePolicy = json_decode((string)file_get_contents($release['root'] . '/tools/db/clean_install_baseline_policy.json'), true);
    if (!is_array($baselinePolicy)) a513_fail('baseline_contract', 'Release baseline policy is unreadable.');
    $requiredTables = $baselinePolicy['schema']['required_tables'] ?? [];
    $postCounts = $baselinePolicy['seed']['post_apply_counts'] ?? [];
    $binary = a5_find_client();
    $ownerCount = 0;
    foreach ($requiredTables as $table) {
        if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/D', $table) !== 1) a513_fail('baseline_contract', 'Required table contract is invalid.');
        [$count] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_TABLE__', "SELECT CONCAT('__A513_TABLE__\\t',COUNT(*)) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND BINARY TABLE_NAME=UNHEX('" . bin2hex($table) . "')", 1, 'required-table');
        if ($count !== '1') a513_fail('required_table_missing', 'A required application table is missing.');
    }
        [$ledgerCount] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_LEDGER_COUNT__', "SELECT CONCAT('__A513_LEDGER_COUNT__\\t',COUNT(*)) FROM sys_schema_migration", 1, 'ledger-count');
        $expectedLedgerRows = count($plan);
        // An installation created by clean_install legitimately retains its seed
        // receipt when subsequently upgraded. Verify that receipt, never rerun it.
        if ($policy === 'upgrade') {
            foreach ($release['catalog']['migrations'] as $migration) {
                if ($migration['policies'] !== ['clean_install'] || $migration['classification'] !== 'seed') continue;
                [$count,$exact] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_LEDGER__', a513_ledger_probe_sql($migration), 2, 'historical-seed-identity');
                if ($count === '0' && $exact === '0') continue;
                if ($count !== '1' || $exact !== '1') a513_fail('migration_ledger_drift', 'Historical seed ledger does not match the release catalog.');
                $expectedLedgerRows++;
            }
        }
        if ($ledgerCount !== (string)$expectedLedgerRows) a513_fail('migration_ledger_count', 'Migration ledger row count does not match this release policy.');
        foreach ($plan as $migration) {
            $sql = a513_ledger_probe_sql($migration);
            [$count,$exact] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_LEDGER__', $sql, 2, 'ledger-identity');
            if ($count !== '1' || $exact !== '1') a513_fail('migration_ledger_drift', 'Migration ledger does not match the release catalog.');
        }
        [$superadmin,$permissionGap,$owner] = a513_query_marker(
            $binary,
            $optionFile,
            $databaseName,
            '__A513_AUTH__',
            "SELECT CONCAT('__A513_AUTH__\\t',(SELECT COUNT(*) FROM auth_role WHERE role_code='SUPERADMIN' AND is_active=1),'\\t',(SELECT COUNT(*) FROM sys_page p JOIN auth_role r ON r.role_code='SUPERADMIN' AND r.is_active=1 LEFT JOIN auth_role_permission rp ON rp.role_id=r.id AND rp.page_id=p.id AND " . a513_permission_match_sql() . " WHERE p.is_active=1 AND rp.id IS NULL),'\\t',(SELECT COUNT(*) FROM auth_user u JOIN auth_user_role ur ON ur.user_id=u.id JOIN auth_role r ON r.id=ur.role_id WHERE u.is_active=1 AND r.role_code='SUPERADMIN' AND r.is_active=1))",
            3,
            'authorization'
        );
        if ($superadmin !== '1' || ($policy === 'clean_install' && $permissionGap !== '0')) a513_fail('superadmin_contract', 'SUPERADMIN role or clean-install active-page permission coverage is incomplete.');
        $ownerCount = ctype_digit($owner) ? (int)$owner : -1;
        if ($ownerCount < 1) a513_fail('owner_missing', 'No active first-owner/SUPERADMIN assignment is available.');
        if ($policy === 'clean_install') {
            foreach (['sys_matrix_group','sys_page','sys_menu','sys_page_alias'] as $table) {
                $expected = $postCounts[$table] ?? null;
                if (!is_int($expected)) a513_fail('baseline_contract', 'Clean-install reference count contract is incomplete.');
                [$count] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_SEED__', "SELECT CONCAT('__A513_SEED__\\t',COUNT(*)) FROM `{$table}`", 1, 'reference-seed');
                if ($count !== (string)$expected) a513_fail('reference_seed_drift', 'Clean-install reference data count does not match the approved seed.');
            }
            [$telegramEnabled] = a513_query_marker($binary, $optionFile, $databaseName, '__A513_TELEGRAM__', "SELECT CONCAT('__A513_TELEGRAM__\\t',COUNT(*)) FROM tg_setting WHERE setting_key='telegram.enabled' AND setting_value='0'", 1, 'safe-default');
            if ($telegramEnabled !== '1') a513_fail('safe_default_drift', 'Clean-install Telegram safe default is not OFF.');
        }
    return [
        'status'=>'ok',
        'policy'=>$policy,
        'release_manifest_sha256'=>$release['manifest_sha256'],
        'release_files'=>count($release['manifest']['files']),
        'required_tables'=>count($requiredTables),
        'migration_ledger_rows'=>$expectedLedgerRows,
        'reference_seed'=>$policy === 'clean_install' ? 'exact' : 'preserved_customer_state',
        'active_superadmin_owners'=>$ownerCount,
    ];
}

if (defined('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY') && A513_POST_INSTALL_HEALTH_LIBRARY_ONLY) return;

try {
    if (PHP_SAPI !== 'cli') a513_fail('cli_only', 'Post-install health check is CLI-only.');
    $args = $argv ?? [];
    foreach ($args as $arg) {
        if (is_string($arg) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $arg)) {
            a513_fail('credential_cli', 'Credential arguments are forbidden.');
        }
    }
    if (($args[1] ?? '') !== 'check') a513_fail('usage', 'Use check with policy, private database files, release root, and release manifest.');
    $options = [];
    foreach (array_slice($args, 2) as $arg) {
        if (!is_string($arg) || preg_match('/^--(policy|defaults-extra-file|database-name-file|release-root|release-manifest)=(.+)$/D', $arg, $match) !== 1 || isset($options[$match[1]])) {
            a513_fail('usage', 'Health-check arguments are malformed.');
        }
        $options[$match[1]] = $match[2];
    }
    $expected = ['policy','defaults-extra-file','database-name-file','release-root','release-manifest'];
    if (array_keys($options) !== $expected) a513_fail('usage', 'Health-check arguments are incomplete or out of order.');
    $toolRoot = realpath(dirname(__DIR__, 2));
    if ($toolRoot === false) a513_fail('root_missing', 'Tool root is unavailable.');
    $optionFile = a5_assert_apply_security($toolRoot, $options['defaults-extra-file']);
    $databaseName = a5_read_database_name($toolRoot, $options['database-name-file']);
    $release = a513_validate_release($options['release-root'], $options['release-manifest']);
    a513_emit(a513_check_database($release, $options['policy'], $optionFile, $databaseName));
} catch (A513HealthFailure $error) {
    a513_emit(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()], STDERR);
    exit($error->failureCode === 'usage' ? 2 : 1);
} catch (A5MigrationFailure $error) {
    a513_emit(['status'=>'error','code'=>'migration_' . $error->failureCode,'message'=>'Migration or credential contract validation failed.'], STDERR);
    exit(1);
}
