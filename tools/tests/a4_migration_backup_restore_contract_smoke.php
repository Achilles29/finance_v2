<?php

declare(strict_types=1);

/**
 * A4 DB-free migration/backup/restore contract smoke.
 *
 * It reads only migration configuration, the two A3 SQL migrations, and the
 * backup program source. Every executable probe uses synthetic SQL and fake
 * clients inside one dedicated temporary directory.
 */

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

function a4_migration_remove_temp_tree(string $root, string $path): void
{
    $root = rtrim($root, DIRECTORY_SEPARATOR);
    if ($root === '' || strpos($path, $root) !== 0) {
        throw new RuntimeException('Refusing cleanup outside the dedicated fixture root.');
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        a4_migration_remove_temp_tree($root, $path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

/** @return array{exit_code:int,stdout:string,stderr:string} */
function a4_migration_run(array $command, string $cwd, array $environment): array
{
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd, $environment);
    if (!is_resource($process)) {
        return ['exit_code' => 255, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'exit_code' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function a4_migration_write_executable(string $path, string $source): bool
{
    return file_put_contents($path, $source) === strlen($source) && chmod($path, 0700);
}

$root = dirname(__DIR__, 2);
$migrationConfigPath = $root . '/application/config/migration.php';
$navigationSqlPath = $root . '/sql/2026-09-04a_a3_navigation_registry_canonicalization.sql';
$aliasSqlPath = $root . '/sql/2026-09-04b_a3_page_alias_registry.sql';
$backupScriptPath = $root . '/scripts/backup/backup_full.sh';

$migrationConfig = @file_get_contents($migrationConfigPath);
$navigationSql = @file_get_contents($navigationSqlPath);
$aliasSql = @file_get_contents($aliasSqlPath);
$backupScript = @file_get_contents($backupScriptPath);

$check(is_string($migrationConfig), 'migration configuration is readable');
$check(
    is_string($migrationConfig)
        && preg_match("/migration_enabled'\]\s*=\s*FALSE/", $migrationConfig) === 1
        && preg_match("/migration_auto_latest'\]\s*=\s*FALSE/", $migrationConfig) === 1,
    'web-request migration and auto-latest remain disabled'
);
$check(
    is_string($migrationConfig)
        && strpos($migrationConfig, "migration_type'] = 'timestamp'") !== false
        && strpos($migrationConfig, "migration_table'] = 'migrations'") !== false
        && strpos($migrationConfig, "migration_path'] = APPPATH.'migrations/'") !== false,
    'migration registry uses the explicit timestamp table and application path'
);

foreach ([
    'A3 navigation migration' => $navigationSql,
    'A3 page-alias migration' => $aliasSql,
] as $label => $sql) {
    $transaction = is_string($sql) ? strpos($sql, 'START TRANSACTION;') : false;
    $commit = is_string($sql) ? strrpos($sql, 'COMMIT;') : false;
    $check(
        is_string($sql) && $transaction !== false && $commit !== false && $transaction < $commit,
        $label . ' has an explicit ordered transaction boundary'
    );
    $check(
        is_string($sql)
            && strpos($sql, 'ON DUPLICATE KEY UPDATE') !== false
            && preg_match('/DROP PROCEDURE IF EXISTS\s+sp_a3_/i', $sql) === 1
            && preg_match('/CALL\s+sp_a3_.*\(\'POST\'\)/i', $sql) === 1,
        $label . ' has repeat-safe upsert, owned routine cleanup, and postflight assertion'
    );
}
$check(
    is_string($aliasSql) && strpos($aliasSql, 'CREATE TABLE IF NOT EXISTS sys_page_alias') !== false,
    'A3 page-alias schema creation is repeat-safe'
);

$check(is_string($backupScript), 'active Unix backup program source is readable');
$check(
    is_string($backupScript)
        && preg_match('/^set -euo pipefail$/m', $backupScript) === 1
        && strpos($backupScript, '--single-transaction') !== false
        && strpos($backupScript, '--skip-lock-tables') !== false
        && strpos($backupScript, '| gzip -9 > "$DUMPFILE"') !== false,
    'backup program uses fail-fast pipeline, consistent dump options, and compression'
);
$check(
    is_string($backupScript)
        && strpos($backupScript, '--password=') === false
        && strpos($backupScript, 'set -x') === false
        && strpos($backupScript, 'sha256sum "$DUMPFILE"') !== false
        && strpos($backupScript, 'find "$BACKUP_DIR"') === false
        && strpos($backupScript, '-delete') === false,
    'backup source avoids exposed passwords/debug tracing, hashes dumps, and delegates retention safely'
);

$fixtureRoot = sys_get_temp_dir() . '/finance-a4-migration-' . bin2hex(random_bytes(8));
$binDir = $fixtureRoot . '/bin';
$workDir = $fixtureRoot . '/work';
$fixtureReady = mkdir($binDir, 0700, true) && mkdir($workDir, 0700, true);
$check($fixtureReady, 'dedicated migration fixture directory was created');

register_shutdown_function(static function () use ($fixtureRoot): void {
    if (is_dir($fixtureRoot)) {
        a4_migration_remove_temp_tree($fixtureRoot, $fixtureRoot);
    }
});

$syntheticSql = implode("\n", [
    '-- synthetic A4 fixture; no customer data',
    'CREATE TABLE smoke_restore (id INT PRIMARY KEY, amount DECIMAL(12,2));',
    'INSERT INTO smoke_restore (id, amount) VALUES (1, 1250.50);',
    '',
]);
$syntheticSqlPath = $workDir . '/synthetic.sql';
$fakeDump = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$@" > "${FAKE_CAPTURE_DIR:?}/mysqldump.args"
if [[ "${FAKE_DUMP_FAIL:-0}" = "1" ]]; then
    head -c 24 "${FAKE_SQL_INPUT:?}"
    exit 42
fi
cat "${FAKE_SQL_INPUT:?}"
SH;
$fakeGzip = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$@" >> "${FAKE_CAPTURE_DIR:?}/gzip.args"
if [[ "${1:-}" = "-dc" ]]; then
    cat "${2:?archive required}"
else
    cat
fi
SH;
$fakeChecksum = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
mode="${1:?mode required}"
archive="${2:?archive required}"
checksum_file="${3:?checksum file required}"
current="$(cksum "$archive" | awk '{print $1 ":" $2}')"
if [[ "$mode" = "write" ]]; then
    printf '%s\n' "$current" > "$checksum_file"
elif [[ "$mode" = "verify" ]]; then
    read -r expected < "$checksum_file"
    [[ "$current" = "$expected" ]]
else
    exit 64
fi
SH;
$fakeMysql = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$@" > "${FAKE_CAPTURE_DIR:?}/mysql.args"
cat > "${FAKE_MYSQL_OUTPUT:?}"
if [[ "${FAKE_MYSQL_FAIL:-0}" = "1" ]]; then
    exit 43
fi
SH;
$contractRunner = <<<'SH'
#!/usr/bin/env bash
set -euo pipefail
mode="${1:?mode required}"
archive="${2:?archive required}"
checksum_file="${archive}.checksum"
partial_archive="${archive}.partial"
partial_checksum="${checksum_file}.partial"
restore_output="${3:-}"
restore_partial="${restore_output}.partial"
cleanup() {
    rm -f -- "$partial_archive" "$partial_checksum"
    if [[ -n "$restore_output" ]]; then
        rm -f -- "$restore_partial"
    fi
}
trap cleanup EXIT
if [[ "$mode" = "backup" ]]; then
    mysqldump --single-transaction --routines synthetic_finance | gzip -9 > "$partial_archive"
    checksum write "$partial_archive" "$partial_checksum"
    mv -f -- "$partial_archive" "$archive"
    mv -f -- "$partial_checksum" "$checksum_file"
elif [[ "$mode" = "restore" ]]; then
    checksum verify "$archive" "$checksum_file"
    FAKE_MYSQL_OUTPUT="$restore_partial" gzip -dc "$archive" | FAKE_MYSQL_OUTPUT="$restore_partial" mysql --database synthetic_finance
    mv -f -- "$restore_partial" "$restore_output"
else
    exit 64
fi
SH;

$filesReady = $fixtureReady
    && file_put_contents($syntheticSqlPath, $syntheticSql) === strlen($syntheticSql)
    && a4_migration_write_executable($binDir . '/mysqldump', $fakeDump)
    && a4_migration_write_executable($binDir . '/gzip', $fakeGzip)
    && a4_migration_write_executable($binDir . '/checksum', $fakeChecksum)
    && a4_migration_write_executable($binDir . '/mysql', $fakeMysql)
    && a4_migration_write_executable($fixtureRoot . '/contract-runner', $contractRunner);
$check($filesReady, 'synthetic SQL and fake mysql/mysqldump/gzip/checksum tools were created');

$baseEnvironment = [
    'PATH' => $binDir . ':/usr/bin:/bin',
    'FAKE_CAPTURE_DIR' => $workDir,
    'FAKE_SQL_INPUT' => $syntheticSqlPath,
    'LC_ALL' => 'C',
];
$archive = $workDir . '/backup.sql.gz';
$runner = $fixtureRoot . '/contract-runner';

if ($filesReady) {
    $backupFirst = a4_migration_run([$runner, 'backup', $archive], $fixtureRoot, $baseEnvironment);
    $firstArchive = @file_get_contents($archive);
    $firstChecksum = @file_get_contents($archive . '.checksum');
    $check(
        $backupFirst['exit_code'] === 0 && $firstArchive === $syntheticSql && is_string($firstChecksum),
        'fake dump/gzip/checksum backup preserves the synthetic SQL'
    );

    $backupSecond = a4_migration_run([$runner, 'backup', $archive], $fixtureRoot, $baseEnvironment);
    $check(
        $backupSecond['exit_code'] === 0
            && @file_get_contents($archive) === $firstArchive
            && @file_get_contents($archive . '.checksum') === $firstChecksum,
        'repeated backup is deterministic and replaces its owned artifacts'
    );

    $restored = $workDir . '/restored.sql';
    $restore = a4_migration_run([$runner, 'restore', $archive, $restored], $fixtureRoot, $baseEnvironment);
    $check(
        $restore['exit_code'] === 0 && @file_get_contents($restored) === $syntheticSql,
        'fake checksum/gzip/mysql restore round trip preserves synthetic SQL exactly'
    );

    $failedArchive = $workDir . '/failed.sql.gz';
    $failedBackup = a4_migration_run(
        [$runner, 'backup', $failedArchive],
        $fixtureRoot,
        $baseEnvironment + ['FAKE_DUMP_FAIL' => '1']
    );
    $check(
        $failedBackup['exit_code'] !== 0
            && !file_exists($failedArchive)
            && !file_exists($failedArchive . '.checksum')
            && !file_exists($failedArchive . '.partial')
            && !file_exists($failedArchive . '.checksum.partial'),
        'failed dump propagates failure and cleans every partial backup artifact'
    );

    $failedRestore = $workDir . '/failed-restore.sql';
    $restoreFailure = a4_migration_run(
        [$runner, 'restore', $archive, $failedRestore],
        $fixtureRoot,
        $baseEnvironment + ['FAKE_MYSQL_FAIL' => '1']
    );
    $check(
        $restoreFailure['exit_code'] !== 0
            && !file_exists($failedRestore)
            && !file_exists($failedRestore . '.partial'),
        'failed mysql restore propagates failure without publishing partial output'
    );
}

if (is_dir($fixtureRoot)) {
    a4_migration_remove_temp_tree($fixtureRoot, $fixtureRoot);
}
$check(!file_exists($fixtureRoot), 'dedicated migration fixture tree is fully cleaned');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A4 migration/backup/restore check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' A4 migration/backup/restore checks passed.' . PHP_EOL;
