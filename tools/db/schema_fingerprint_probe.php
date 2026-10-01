<?php

declare(strict_types=1);

define('A5_MIGRATION_LIBRARY_ONLY', true);
require __DIR__ . '/migration_runner.php';

function a55_safe_select(string $sql): bool
{
    if (preg_match('/^SELECT\b/i', trim($sql)) !== 1 || preg_match('/(--|#|\/\*|\*\/|;)/', $sql) === 1) return false;
    $withoutLiterals = preg_replace("/'(?:''|\\\\.|[^'])*'/", "''", $sql);
    if (!is_string($withoutLiterals)
        || preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE|CALL|DO|LOAD|HANDLER|SET|USE|GRANT|REVOKE|LOCK|UNLOCK|START|COMMIT|ROLLBACK|PREPARE|EXECUTE|DEALLOCATE)\b|\bREPLACE(?!\s*\()/i', $withoutLiterals) === 1) {
        return false;
    }
    return preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE|@[A-Za-z_][A-Za-z0-9_$]*)\b|\bFOR\s+(?:UPDATE|SHARE)\b|\bLOCK\s+IN\s+SHARE\s+MODE\b|@[A-Za-z_][A-Za-z0-9_$]*\s*(?::=|=)|\b(?:GET_LOCK|RELEASE_LOCK|RELEASE_ALL_LOCKS|IS_FREE_LOCK|IS_USED_LOCK|SLEEP|BENCHMARK|MASTER_POS_WAIT|WAIT_FOR_EXECUTED_GTID_SET|LOAD_FILE)\s*\(/i', $withoutLiterals) !== 1;
}

function a55_load(string $root): array
{
    $fingerprintPath = $root . '/tools/db/legacy_schema_fingerprints.json';
    $inventoryPath = $root . '/tools/db/legacy_sql_inventory.json';
    $raw = @file_get_contents($fingerprintPath);
    $inventoryRaw = @file_get_contents($inventoryPath);
    try {
        $manifest = json_decode((string)$raw, true, 64, JSON_THROW_ON_ERROR);
        $inventory = json_decode((string)$inventoryRaw, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) { a5_fail('fingerprint_json', 'Fingerprint evidence JSON is malformed.'); }
    if (!is_array($manifest) || !a5_exact_keys($manifest, ['fingerprint_version','inventory_sha256','scope','records'])
        || $manifest['fingerprint_version'] !== 1 || $manifest['scope'] !== 'legacy_sql_inventory_exact'
        || !is_string($inventoryRaw) || !hash_equals((string)$manifest['inventory_sha256'], hash('sha256', $inventoryRaw))
        || !is_array($manifest['records']) || !is_array($inventory['records'] ?? null) || count($manifest['records']) !== 7) a5_fail('fingerprint_schema', 'Fingerprint evidence schema is invalid.');
    $inventoryByPath = [];
    foreach ($inventory['records'] as $record) $inventoryByPath[$record['path']] = $record;
    $seen = [];
    foreach ($manifest['records'] as $record) {
        if (!is_array($record) || !a5_exact_keys($record, ['path','sha256','disposition','eligibility','checks']) || !is_string($record['path']) || isset($seen[$record['path']]) || !isset($inventoryByPath[$record['path']])
            || $record['sha256'] !== $inventoryByPath[$record['path']]['sha256'] || $record['disposition'] !== $inventoryByPath[$record['path']]['disposition'] || !is_array($record['checks']) || !a5_is_list($record['checks'])) a5_fail('fingerprint_schema', 'Fingerprint record is invalid.');
        $seen[$record['path']] = true;
        if ($record['disposition'] === 'historical-unverified') {
            if ($record['eligibility'] !== 'not_eligible' || $record['checks'] !== []) a5_fail('historical_eligibility', 'Historical SQL cannot have executable eligibility checks.');
            continue;
        }
        if ($record['disposition'] !== 'candidate-canonical' || $record['eligibility'] !== 'evidence_only' || $record['checks'] === []) a5_fail('candidate_schema', 'Candidate fingerprint is incomplete.');
        $checkIds = [];
        foreach ($record['checks'] as $check) {
            if (!is_array($check) || !a5_exact_keys($check, ['id','covers','sql']) || !is_string($check['id']) || preg_match('/^[a-z0-9_]+$/', $check['id']) !== 1 || isset($checkIds[$check['id']])
                || !is_array($check['covers']) || !a5_is_list($check['covers']) || $check['covers'] === [] || !is_string($check['sql']) || !a55_safe_select($check['sql'])) a5_fail('fingerprint_check', 'Fingerprint check is unsafe or malformed.');
            $checkIds[$check['id']] = true;
        }
    }
    if (array_keys($seen) !== array_column($inventory['records'], 'path')) a5_fail('fingerprint_scope', 'Fingerprint scope does not match inventory.');
    return $manifest;
}

function a55_probe(array $manifest, string $optionFile, string $databaseName): array
{
    $deadline = microtime(true) + a5_apply_timeout_seconds();
    $client = a5_client_open(a5_find_client(), $optionFile, $databaseName, $deadline);
    $results = [];
    try {
        foreach ($manifest['records'] as $record) {
            if ($record['eligibility'] === 'not_eligible') {
                $results[] = ['path'=>$record['path'],'disposition'=>$record['disposition'],'eligibility'=>'not_eligible','failed_checks'=>[]];
                continue;
            }
            $failed = [];
            foreach ($record['checks'] as $check) {
                a5_client_send($client, $check['sql'] . ';');
                $marker = a5_client_marker($client, '__A55_CHECK__');
                if (count($marker) !== 2 || !in_array($marker[1], ['0','1'], true)) a5_fail('malformed_output', 'Fingerprint client output is malformed.');
                if ($marker[1] !== '1') $failed[] = $check['id'];
            }
            $results[] = ['path'=>$record['path'],'disposition'=>$record['disposition'],'eligibility'=>$failed === [] ? 'eligible_evidence' : 'not_eligible','failed_checks'=>$failed];
        }
    } finally { a5_client_close($client, false); }
    $eligible = count(array_filter($results, static function (array $row): bool { return $row['eligibility'] === 'eligible_evidence'; }));
    return ['records'=>$results,'candidate_eligible'=>$eligible,'candidate_total'=>4,'overall_eligibility'=>$eligible === 4 ? 'candidate_evidence_complete_historical_excluded' : 'not_eligible'];
}

if (defined('A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY') && A55_SCHEMA_FINGERPRINT_LIBRARY_ONLY) {
    return;
}

try {
    $args = $argv ?? [];
    $mode = $args[1] ?? '';
    foreach ($args as $arg) if (is_string($arg) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $arg)) a5_fail('credential_cli', 'Credential command-line arguments are forbidden.');
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) a5_fail('root_missing', 'Repository root is unavailable.');
    $manifest = a55_load($root);
    if ($mode === 'validate' && count($args) === 2) {
        a5_emit(['status'=>'ok','mode'=>'validate','fingerprint_version'=>1,'records'=>7,'candidates'=>4,'historical_not_eligible'=>3]); exit(0);
    }
    if ($mode !== 'probe' || count($args) !== 4 || strpos($args[2], '--defaults-extra-file=') !== 0 || strpos($args[3], '--database-name-file=') !== 0) {
        a5_fail('usage', 'Use validate or probe with --defaults-extra-file=/absolute/private/file and --database-name-file=/absolute/private/file.');
    }
    $option = a5_assert_apply_security($root, substr($args[2], 22));
    $databaseName = a5_read_database_name($root, substr($args[3], 21));
    $result = a55_probe($manifest, $option, $databaseName);
    a5_emit(['status'=>'ok','mode'=>'probe'] + $result);
} catch (A5MigrationFailure $error) {
    fwrite(STDERR, json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage(),], JSON_UNESCAPED_SLASHES) . PHP_EOL); exit(1);
}
