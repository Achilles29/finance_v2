<?php

declare(strict_types=1);

$checks = 0; $failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) { $failures[] = $message; fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); return; }
    echo 'PASS: ' . $message . PHP_EOL;
};
function a52_rm(string $root, string $path): void {
    if (strpos($path, $root) !== 0) throw new RuntimeException('unsafe cleanup');
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') a52_rm($root, $path . '/' . $entry);
    @rmdir($path);
}
function a52_run(string $runner, array $args, array $env): array {
    $process = proc_open(array_merge([PHP_BINARY, '-d', 'variables_order=GPCS', $runner], $args), [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($runner), $env);
    if (!is_resource($process)) return ['code' => 255, 'out' => '', 'err' => ''];
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string)$out, 'err' => (string)$err];
}
function a52_code(array $result): string {
    $json = json_decode(trim($result['err']), true);
    return is_array($json) ? (string)($json['code'] ?? '') : '';
}

$root = dirname(__DIR__, 2);
$runner = $root . '/tools/db/migration_runner.php';
$runnerSource = (string)file_get_contents($runner);
$catalog = json_decode((string)file_get_contents($root . '/tools/db/migration_catalog.json'), true);
$migrations = array_values(array_filter($catalog['migrations'], static function (array $migration): bool {
    return in_array('upgrade', $migration['policies'] ?? [], true);
}));
$migrationFixture = [];
foreach ($migrations as $migration) {
    $migrationFixture[bin2hex($migration['id'])] = ['path_hex' => bin2hex($migration['path']), 'checksum' => $migration['sha256']];
}
$tmp = sys_get_temp_dir() . '/finance-a52-' . bin2hex(random_bytes(6));
mkdir($tmp . '/bin', 0700, true);
register_shutdown_function(static function () use ($tmp): void { if (is_dir($tmp)) a52_rm($tmp, $tmp); });
$option = $tmp . '/client.cnf'; file_put_contents($option, "[client]\nuser=fixture\n"); chmod($option, 0600);
$databaseName = 'db_finance_secret_name';
$databaseNameFile = $tmp . '/database-name'; file_put_contents($databaseNameFile, $databaseName . "\n"); chmod($databaseNameFile, 0600);
$capture = $tmp . '/capture.sql'; $count = $tmp . '/count'; $pidFile = $tmp . '/pid'; $argvCapture = $tmp . '/argv.json';
$fake = <<<'PHP'
#!/usr/bin/php
<?php
$mode = getenv('A5_FAKE_MODE') ?: 'success';
$capture = getenv('A5_CAPTURE'); $count = getenv('A5_COUNT');
$expectedDatabase = getenv('A5_EXPECTED_DATABASE');
file_put_contents(getenv('A5_ARGV_CAPTURE'), json_encode($argv, JSON_UNESCAPED_SLASHES));
if (!is_string($expectedDatabase) || $expectedDatabase === '' || end($argv) !== $expectedDatabase) {
    fwrite(STDERR, "RAW SECRET selected database missing\n");
    exit(12);
}
file_put_contents($count, (string)(((int)@file_get_contents($count)) + 1));
file_put_contents(getenv('A5_PID_FILE'), (string)getmypid());
$registry = !in_array($mode, ['success', 'sql_failure'], true);
$migrations = json_decode((string)getenv('A5_MIGRATIONS_JSON'), true) ?: [];
$toolHex = bin2hex('1.0.0');
while (($line = fgets(STDIN)) !== false) {
    file_put_contents($capture, $line, FILE_APPEND);
    if (strpos($line, '__A5_LOCK_REQUEST__') !== false) {
        if ($mode === 'timeout') { if (function_exists('pcntl_async_signals')) { pcntl_async_signals(true); pcntl_signal(SIGTERM, SIG_IGN); } while (true) usleep(100000); }
        if ($mode === 'malformed') { fwrite(STDOUT, "RAW SECRET {$expectedDatabase}\n"); fflush(STDOUT); exit(8); }
        fwrite(STDOUT, "__A5_LOCK__\t" . ($mode === 'lock' ? '0' : '1') . "\n"); fflush(STDOUT);
    } elseif (strpos($line, '__A5_REGISTRY_REQUEST__') !== false) {
        $state = in_array($mode, ['incompatible', 'wrong_columns', 'missing_indexes'], true) ? 'INCOMPATIBLE' : ($registry ? 'COMPATIBLE_V1' : 'ABSENT');
        fwrite(STDOUT, "__A5_REGISTRY__\t{$state}\n"); fflush(STDOUT);
    } elseif (stripos($line, 'CREATE TABLE IF NOT EXISTS `sys_schema_migration`') !== false) {
        if ($mode === 'sql_failure') { fwrite(STDERR, "RAW SECRET /secure/client.cnf {$expectedDatabase}\n"); exit(9); }
        $registry = true;
    } elseif (strpos($line, '__A5_PROOF__') !== false) {
        // Protocol-only fixture: new schema is already installed manually. Real DDL/adoption
        // and drift are exercised by managed_migration_database_smoke on disposable MariaDB.
        $proofs=json_decode((string)getenv('A5_PROOF_HASHES'),true);
        $value='1';
        if(strpos($line,'SHA2(')!==false) {
            $value='';
            foreach($proofs as $table=>$hash)if(strpos($line,"TABLE_NAME='".$table."'")!==false)$value=$hash;
            if(strpos($line,"TABLE_NAME='fin_revenue_reconciliation_line'")!==false)$value=$proofs['reconciliation_columns'];
        } elseif(strpos($line,"CONCAT_WS('|',COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA)")!==false)$value="enum('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED','NOT_REQUIRED')|NO|'PENDING'|";
        elseif(strpos($line,'FROM pos_order WHERE stock_commit_status')!==false)$value='0';
        elseif(strpos($line,'information_schema.TRIGGERS')!==false)$value='0';
        elseif(strpos($line,'FROM fin_gl_account WHERE code IN')!==false){preg_match_all("/'[0-9]{4}'/",explode(' AND BINARY',$line)[0],$codes);$value=(string)count($codes[0]);}
        fwrite(STDOUT,"__A5_PROOF__\t".bin2hex($value)."\n");fflush(STDOUT);
    } elseif (strpos($line, '__A5_STATE_REQUEST__') !== false) {
        preg_match("/UNHEX\\('([0-9a-f]+)'\\)/i", $line, $matches);
        $migration = $migrations[strtolower($matches[1] ?? '')] ?? null;
        if ($mode === 'replay' && is_array($migration)) fwrite(STDOUT, "__A5_STATE__\t{$migration['path_hex']}\t{$migration['checksum']}\t1\t{$toolHex}\n");
        elseif ($mode === 'drift' && is_array($migration)) fwrite(STDOUT, "__A5_STATE__\t" . bin2hex('sql/drift.sql') . "\t{$migration['checksum']}\t1\t{$toolHex}\n");
        else fwrite(STDOUT, "__A5_STATE__\tNONE\n");
        fflush(STDOUT);
    } elseif (strpos($line, "SELECT '__A5_MIGRATION_DONE__") !== false) {
        preg_match('/__A5_MIGRATION_DONE__\\\\t([0-9a-f]+)/i', $line, $matches);
        fwrite(STDOUT, "__A5_MIGRATION_DONE__\t" . strtolower($matches[1] ?? '') . "\n"); fflush(STDOUT);
    } elseif (strpos($line, "SELECT '__A5_LEDGER_DONE__") !== false) {
        preg_match('/__A5_LEDGER_DONE__\\\\t([0-9a-f]+)/i', $line, $matches);
        fwrite(STDOUT, "__A5_LEDGER_DONE__\t" . strtolower($matches[1] ?? '') . "\n"); fflush(STDOUT);
    } elseif (strpos($line, '__A5_RELEASE_REQUEST__') !== false) {
        fwrite(STDOUT, "__A5_RELEASE__\t1\n"); fflush(STDOUT);
    }
}
PHP;
file_put_contents($tmp . '/bin/mariadb', $fake); chmod($tmp . '/bin/mariadb', 0700);
$baseEnv = ['PATH' => $tmp . '/bin', 'A5_CAPTURE' => $capture, 'A5_COUNT' => $count, 'A5_PID_FILE' => $pidFile, 'A5_ARGV_CAPTURE' => $argvCapture, 'A5_EXPECTED_DATABASE' => $databaseName, 'A5_MIGRATIONS_JSON' => json_encode($migrationFixture, JSON_UNESCAPED_SLASHES)];
$args = ['apply', '--policy=upgrade', '--defaults-extra-file=' . $option, '--database-name-file=' . $databaseNameFile];
$baseEnv['A5_PROOF_HASHES']=json_encode(json_decode(file_get_contents($root.'/tools/db/managed_migration_proofs.json'),true)['schemas']);

$libraryProbe = $tmp . '/library-probe.php';
$librarySource = "<?php\ndefine('A5_MIGRATION_LIBRARY_ONLY', true);\nrequire " . var_export($runner, true) . ";\n"
    . "if (!function_exists('a5_registry_probe_sql')) { exit(3); }\n"
    . "\$sql = a5_registry_probe_sql();\n"
    . "echo (strpos(\$sql, '__A5_REGISTRY_REQUEST__') !== false && strpos(\$sql, 'COMPATIBLE_V1') !== false) ? 'LIBRARY_OK' : 'LIBRARY_BAD';\n";
file_put_contents($libraryProbe, $librarySource);
$library = a52_run($libraryProbe, [], $baseEnv);
$check($library['code'] === 0 && $library['out'] === 'LIBRARY_OK' && $library['err'] === '', 'library-only guard exposes registry SELECT builder without running CLI main');

$normalCli = a52_run($runner, ['validate'], $baseEnv);
$normalCliJson = json_decode(trim($normalCli['out']), true);
$check($normalCli['code'] === 0 && ($normalCliJson['mode'] ?? '') === 'validate' && ($normalCliJson['status'] ?? '') === 'ok', 'normal CLI execution remains unchanged when library-only guard is absent');

$dry = a52_run($runner, array_merge($args, ['--dry-run']), $baseEnv + ['A5_FAKE_MODE' => 'success']);
$check($dry['code'] === 0 && !file_exists($count) && strpos($dry['out'], '"dry_run":true') !== false, 'dry-run validates and plans without starting a client');

@unlink($capture); @unlink($count);
$success = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => 'success']);
$successSql = (string)@file_get_contents($capture);
$clientArgv = json_decode((string)@file_get_contents($argvCapture), true);
$successResult = json_decode(trim($success['out']), true);
$check($success['code'] === 0 && trim((string)@file_get_contents($count)) === '1' && ($successResult['applied'] ?? null) === count($migrations) && ($successResult['skipped'] ?? null) === 0, 'apply uses one client session and records the exact managed upgrade plan');
$check(is_array($clientArgv) && array_slice($clientArgv, -2) === ['--unbuffered', $databaseName] && count(array_keys($clientArgv, $databaseName, true)) === 1 && !in_array('--database=' . $databaseName, $clientArgv, true), 'client argv selects the database once as the final positional argument after options');
$check(strpos($success['out'] . $success['err'], $databaseName) === false, 'successful apply does not disclose database-name file contents');
$check(strpos($successSql, 'GET_LOCK') < strpos($successSql, 'CREATE TABLE IF NOT EXISTS') && strpos($successSql, 'CREATE TABLE IF NOT EXISTS') < strpos($successSql, 'INSERT INTO sys_schema_migration') && strpos($successSql, 'INSERT INTO sys_schema_migration') < strpos($successSql, 'RELEASE_LOCK'), 'protocol orders lock, whole migration, ledger insert, and release');
$check(strpos($successSql, "'COMPATIBLE_V1'") !== false && strpos($successSql, "table_type='BASE TABLE'") !== false && strpos($successSql, "table_collation LIKE 'utf8mb4_%'") !== false && strpos($successSql, 'uq_sys_schema_migration_filename') !== false && strpos($successSql, 'idx_sys_schema_migration_applied_at') !== false && strpos($successSql, 'idx_sys_schema_migration_batch_id') !== false, 'registry probe carries the exact v1 table, column, unique, and index contract');
$check(substr_count($runnerSource, "(column_default IS NULL OR UPPER(column_default)='NULL')") === 4, 'registry probe accepts MariaDB literal NULL defaults only for four nullable metadata columns');

@unlink($capture); @unlink($count);
$mariaDbDefaults = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => 'mariadb_null_defaults']);
$mariaDbDefaultsResult = json_decode(trim($mariaDbDefaults['out']), true);
$check($mariaDbDefaults['code'] === 0 && ($mariaDbDefaultsResult['applied'] ?? null) === count($migrations) && ($mariaDbDefaultsResult['skipped'] ?? null) === 0, 'MariaDB literal NULL metadata-default representation applies the exact managed upgrade plan');

@unlink($capture); @unlink($count);
$replay = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => 'replay']); $replaySql = (string)@file_get_contents($capture);
$replayResult = json_decode(trim($replay['out']), true);
$check($replay['code'] === 0 && ($replayResult['applied'] ?? null) === 0 && ($replayResult['skipped'] ?? null) === count($migrations) && strpos($replaySql, 'CREATE TABLE IF NOT EXISTS') === false && strpos($replaySql, 'INSERT INTO sys_schema_migration') === false, 'exact applied replay skips the full managed upgrade plan and is a no-op');
$statePredicatesPresent = true;
foreach ($migrations as $migration) {
    $statePredicatesPresent = $statePredicatesPresent && strpos($replaySql, "WHERE BINARY migration_id=UNHEX('" . bin2hex($migration['id']) . "')") !== false;
}
$check($statePredicatesPresent && strpos($replaySql, 'WHERE migration_id=CONVERT') === false && strpos($replaySql, 'USING utf8mb4') === false, 'all managed state probes use canonical byte-safe BINARY and UNHEX comparison without connection collation conversion');

foreach (['drift' => 'ledger_drift', 'lock' => 'lock_contention', 'incompatible' => 'registry_incompatible', 'wrong_columns' => 'registry_incompatible', 'missing_indexes' => 'registry_incompatible', 'malformed' => 'malformed_output'] as $mode => $expected) {
    @unlink($capture); @unlink($count);
    $result = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => $mode]);
    $protocol = (string)@file_get_contents($capture);
    $check($result['code'] !== 0 && a52_code($result) === $expected && strpos($result['err'], $option) === false && strpos($result['err'], $databaseName) === false && strpos($result['err'], 'RAW SECRET') === false, $mode . ' fails closed with redacted diagnostics');
    if (in_array($mode, ['drift', 'incompatible', 'wrong_columns', 'missing_indexes'], true)) $check(strpos($protocol, 'RELEASE_LOCK') !== false, $mode . ' releases the held advisory lock');
}

@unlink($capture); @unlink($count);
$sqlFailure = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => 'sql_failure']); $failedSql = (string)@file_get_contents($capture);
$check($sqlFailure['code'] !== 0 && a52_code($sqlFailure) === 'client_failure' && strpos($failedSql, 'INSERT INTO sys_schema_migration') === false && strpos($sqlFailure['err'], 'RAW SECRET') === false && strpos($sqlFailure['err'], $databaseName) === false, 'SQL/client failure never inserts ledger state and redacts client output');

@unlink($capture); @unlink($count); @unlink($pidFile);
$timeout = a52_run($runner, $args, $baseEnv + ['A5_FAKE_MODE' => 'timeout', 'A5_MIGRATION_TIMEOUT_SECONDS' => '1']);
$timedPid = (int)@file_get_contents($pidFile);
$stillRunning = $timedPid > 0 && function_exists('posix_kill') ? @posix_kill($timedPid, 0) : false;
$check($timeout['code'] !== 0 && a52_code($timeout) === 'client_timeout' && !$stillRunning && strpos((string)@file_get_contents($capture), 'INSERT INTO sys_schema_migration') === false, 'global deadline terminates a nonresponsive child and writes no ledger');

$credential = a52_run($runner, $args, $baseEnv + ['DB_PASSWORD' => 'TOP_SECRET']);
$check($credential['code'] !== 0 && a52_code($credential) === 'credential_env' && strpos($credential['err'], 'TOP_SECRET') === false, 'credential environment is rejected without disclosure');
$cliCredential = a52_run($runner, array_merge($args, ['--password=TOP_SECRET']), $baseEnv);
$check($cliCredential['code'] !== 0 && a52_code($cliCredential) === 'credential_cli' && strpos($cliCredential['err'], 'TOP_SECRET') === false, 'credential CLI arguments are rejected without disclosure');

$unsafe = $tmp . '/unsafe.cnf'; file_put_contents($unsafe, "[client]\n"); chmod($unsafe, 0644);
$unsafeResult = a52_run($runner, ['apply', '--policy=upgrade', '--defaults-extra-file=' . $unsafe, '--database-name-file=' . $databaseNameFile, '--dry-run'], $baseEnv);
$check($unsafeResult['code'] !== 0 && a52_code($unsafeResult) === 'option_file_permissions' && strpos($unsafeResult['err'], $unsafe) === false, 'unsafe option-file permissions fail without printing its path');

$missingDatabaseFile = $tmp . '/missing-database-name';
$missingDatabaseResult = a52_run($runner, ['apply', '--policy=upgrade', '--defaults-extra-file=' . $option, '--database-name-file=' . $missingDatabaseFile, '--dry-run'], $baseEnv);
$check($missingDatabaseResult['code'] !== 0 && a52_code($missingDatabaseResult) === 'database_name_file' && strpos($missingDatabaseResult['err'], $missingDatabaseFile) === false, 'missing database-name file is rejected without printing its path');

$unsafeDatabaseFile = $tmp . '/unsafe-database-name'; file_put_contents($unsafeDatabaseFile, "unsafe_db\n"); chmod($unsafeDatabaseFile, 0644);
$unsafeDatabaseResult = a52_run($runner, ['apply', '--policy=upgrade', '--defaults-extra-file=' . $option, '--database-name-file=' . $unsafeDatabaseFile, '--dry-run'], $baseEnv);
$check($unsafeDatabaseResult['code'] !== 0 && a52_code($unsafeDatabaseResult) === 'database_name_file_permissions' && strpos($unsafeDatabaseResult['err'], $unsafeDatabaseFile) === false && strpos($unsafeDatabaseResult['err'], 'unsafe_db') === false, 'insecure database-name file permissions are rejected without path or content disclosure');

$invalidDatabaseSecret = 'invalid-db-name TOP_SECRET';
$invalidDatabaseFile = $tmp . '/invalid-database-name'; file_put_contents($invalidDatabaseFile, $invalidDatabaseSecret . "\n"); chmod($invalidDatabaseFile, 0600);
$invalidDatabaseResult = a52_run($runner, ['apply', '--policy=upgrade', '--defaults-extra-file=' . $option, '--database-name-file=' . $invalidDatabaseFile, '--dry-run'], $baseEnv);
$check($invalidDatabaseResult['code'] !== 0 && a52_code($invalidDatabaseResult) === 'database_name_invalid' && strpos($invalidDatabaseResult['err'], $invalidDatabaseFile) === false && strpos($invalidDatabaseResult['err'], $invalidDatabaseSecret) === false && strpos($invalidDatabaseResult['err'], 'TOP_SECRET') === false, 'invalid database name is rejected without path or content disclosure');

$invalidDatabaseBytes = [
    'leading space' => " db_finance\n",
    'trailing space' => "db_finance \n",
    'NUL byte' => "db_finance\0\n",
    'multiple blank lines' => "db_finance\n\n",
];
foreach ($invalidDatabaseBytes as $description => $contents) {
    $invalidBytesFile = $tmp . '/invalid-database-bytes'; file_put_contents($invalidBytesFile, $contents); chmod($invalidBytesFile, 0600);
    $invalidBytesResult = a52_run($runner, ['apply', '--policy=upgrade', '--defaults-extra-file=' . $option, '--database-name-file=' . $invalidBytesFile, '--dry-run'], $baseEnv);
    $check($invalidBytesResult['code'] !== 0 && a52_code($invalidBytesResult) === 'database_name_invalid' && strpos($invalidBytesResult['err'], $invalidBytesFile) === false && strpos($invalidBytesResult['err'], $contents) === false, 'database-name file rejects ' . $description . ' without disclosure');
}

a52_rm($tmp, $tmp);
$check(!file_exists($tmp), 'executor fixtures are fully cleaned');
if ($failures !== []) { fwrite(STDERR, count($failures) . ' A5.2 executor check(s) failed.' . PHP_EOL); exit(1); }
echo 'All ' . $checks . ' A5.2 migration executor checks passed.' . PHP_EOL;
