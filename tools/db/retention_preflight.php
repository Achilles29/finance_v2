<?php

declare(strict_types=1);

if (!defined('A5_MIGRATION_LIBRARY_ONLY')) {
    define('A5_MIGRATION_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/migration_runner.php';
if (!defined('FINANCE_RETENTION_LIBRARY_ONLY')) {
    define('FINANCE_RETENTION_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__) . '/release/retention_manager.php';

/** @return array<string,string> */
function financeRetentionDatabaseQueries(array $policy): array
{
    if (financeRetentionPolicyErrors($policy) !== []) {
        throw new FinanceRetentionFailure('database retention policy is invalid');
    }
    $queries = [];
    foreach ($policy['database_rules'] as $rule) {
        $id = $rule['id'];
        $table = $rule['table'];
        $timestamp = $rule['timestamp_column'];
        $days = $rule['minimum_age_days'];
        $predicate = $rule['predicate'];
        $queries[$id] = "SELECT CONCAT('__A515__\\t','{$id}','\\t',COUNT(*),'\\t',"
            . "COALESCE(DATE_FORMAT(MIN(`{$timestamp}`),'%Y-%m-%dT%H:%i:%s'),''),'\\t',"
            . "COALESCE(DATE_FORMAT(MAX(`{$timestamp}`),'%Y-%m-%dT%H:%i:%s'),'')) "
            . "FROM `{$table}` WHERE ({$predicate}) AND `{$timestamp}` < UTC_TIMESTAMP() - INTERVAL {$days} DAY";
    }
    return $queries;
}

function financeRetentionDatabaseProbe(string $optionFile, string $databaseName, array $queries): array
{
    $client = a5_find_client();
    $command = [$client, '--defaults-extra-file=' . $optionFile, '--batch', '--raw', '--skip-column-names', '--connect-timeout=5', $databaseName];
    $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
        'PATH' => '/usr/bin:/bin',
        'TZ' => 'Asia/Jakarta',
        'LANG' => 'C',
    ]);
    if (!is_resource($process)) {
        throw new FinanceRetentionFailure('database preflight client could not start');
    }
    fwrite($pipes[0], implode(";\n", $queries) . ";\n");
    fclose($pipes[0]);
    $stdout = (string)stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || strlen($stdout) > 16384) {
        throw new FinanceRetentionFailure('database preflight failed with redacted output');
    }
    $results = [];
    foreach (preg_split('/\R/', rtrim($stdout, "\r\n")) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        $fields = explode("\t", $line);
        if (count($fields) !== 5 || $fields[0] !== '__A515__' || !isset($queries[$fields[1]]) || !ctype_digit($fields[2])) {
            throw new FinanceRetentionFailure('database preflight output is malformed');
        }
        $results[$fields[1]] = ['candidate_rows' => (int)$fields[2], 'oldest' => $fields[3], 'newest' => $fields[4]];
    }
    if (array_keys($results) !== array_keys($queries)) {
        throw new FinanceRetentionFailure('database preflight output is incomplete');
    }
    return $results;
}

if (defined('A515_RETENTION_PREFLIGHT_LIBRARY_ONLY') && A515_RETENTION_PREFLIGHT_LIBRARY_ONLY) {
    return;
}

try {
    $root = dirname(__DIR__, 2);
    $policy = financeRetentionLoadPolicy($root);
    $queries = financeRetentionDatabaseQueries($policy);
    $mode = $argv[1] ?? 'validate';
    if ($mode === 'validate' && count($argv) === 2) {
        echo json_encode(['status' => 'ok', 'rules' => count($queries)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    if ($mode !== 'probe' || count($argv) !== 4
        || !str_starts_with($argv[2], '--defaults-extra-file=')
        || !str_starts_with($argv[3], '--database-name-file=')
    ) {
        throw new FinanceRetentionFailure('usage: retention_preflight.php validate OR probe --defaults-extra-file=/private/file --database-name-file=/private/file');
    }
    $option = a5_assert_apply_security($root, substr($argv[2], 22));
    $database = a5_read_database_name($root, substr($argv[3], 21));
    echo json_encode(['status' => 'ok', 'mode' => 'read_only', 'rules' => financeRetentionDatabaseProbe($option, $database, $queries)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['status' => 'error', 'message' => $error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
