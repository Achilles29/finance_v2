<?php

declare(strict_types=1);

function a512_exact_keys(array $value, array $keys): bool
{
    $actual = array_keys($value);
    sort($actual);
    sort($keys);
    return $actual === $keys;
}

function a512_schema_errors(string $sql, array $schemaPolicy): array
{
    $errors = [];
    if (strlen($sql) < 100 || strlen($sql) > 16 * 1024 * 1024 || strpos($sql, "\0") !== false) {
        return ['schema_size_or_encoding_invalid'];
    }
    if (!a512_exact_keys($schemaPolicy, ['path','sha256','table_count','required_tables','forbidden_table_patterns'])) {
        return ['schema_policy_invalid'];
    }
    $forbiddenSql = [
        '/\b(?:INSERT\s+INTO|REPLACE\s+INTO|DELETE\s+FROM|LOAD\s+DATA|CREATE\s+DATABASE|DROP\s+DATABASE|CREATE\s+USER|ALTER\s+USER|IDENTIFIED\s+BY)\b/i',
        '/^\s*UPDATE\s+/mi',
        '/^\s*(?:GRANT|REVOKE|USE)\s+/mi',
        '/\b(?:DEFINER\s*=|DATA\s+DIRECTORY\s*=|INDEX\s+DIRECTORY\s*=|INTO\s+OUTFILE|INTO\s+DUMPFILE)\b/i',
        '#(?:/www/|/var/lib/|[A-Za-z]:\\\\)#',
        '/\bAUTO_INCREMENT\s*=\s*[0-9]+/i',
        '/`[^`]*(?:password|passwd|api_token|access_token|secret|private_key)[^`]*`[^,\n]*DEFAULT\s+\'(?!\')[^\']+\'/i',
    ];
    foreach ($forbiddenSql as $pattern) {
        if (preg_match($pattern, $sql) === 1) $errors[] = 'forbidden_schema_content';
    }

    preg_match_all('/^CREATE TABLE `([A-Za-z0-9_]+)`\s*\(/m', $sql, $matches);
    $tables = $matches[1] ?? [];
    if (count($tables) !== (int)($schemaPolicy['table_count'] ?? 0)
        || count(array_unique($tables)) !== count($tables)) {
        $errors[] = 'table_inventory_mismatch';
    }
    $tableSet = array_fill_keys($tables, true);
    foreach ((array)($schemaPolicy['required_tables'] ?? []) as $required) {
        if (!is_string($required) || !isset($tableSet[$required])) $errors[] = 'required_table_missing';
    }
    foreach ($tables as $table) {
        foreach ((array)($schemaPolicy['forbidden_table_patterns'] ?? []) as $pattern) {
            if (!is_string($pattern) || @preg_match('/' . str_replace('/', '\\/', $pattern) . '/D', $table) !== 1) continue;
            $errors[] = 'operational_table_forbidden';
        }
    }

    $firstCreate = strpos($sql, 'CREATE TABLE `');
    $disable = strpos($sql, 'SET FOREIGN_KEY_CHECKS=0;');
    $enable = strrpos($sql, 'SET FOREIGN_KEY_CHECKS=1;');
    if ($firstCreate === false || $disable === false || $enable === false
        || $disable > $firstCreate || $enable < $firstCreate) {
        $errors[] = 'foreign_key_restore_boundary_invalid';
    }
    return array_values(array_unique($errors));
}

function a512_seed_errors(string $sql, array $seedPolicy): array
{
    $errors = [];
    $keys = ['status','path','sha256','execution_policy','allowed_source_tables','artifact_rows','post_apply_counts','transformations','forbidden_domains'];
    if (!a512_exact_keys($seedPolicy, $keys)
        || ($seedPolicy['status'] ?? '') !== 'approved_minimal'
        || ($seedPolicy['execution_policy'] ?? '') !== 'clean_install_only') {
        return ['seed_policy_invalid'];
    }
    if (strlen($sql) < 100 || strlen($sql) > 2 * 1024 * 1024 || strpos($sql, "\0") !== false) {
        return ['seed_size_or_encoding_invalid'];
    }

    $expectedSources = ['sys_matrix_group','sys_page','sys_menu','sys_page_alias','auth_role'];
    if (($seedPolicy['allowed_source_tables'] ?? null) !== $expectedSources
        || !is_array($seedPolicy['artifact_rows'] ?? null)
        || array_keys($seedPolicy['artifact_rows']) !== $expectedSources) {
        $errors[] = 'seed_allowlist_invalid';
    }

    preg_match_all('/^INSERT INTO\s+([A-Za-z0-9_]+)/mi', $sql, $insertMatches);
    $insertTables = array_values(array_unique($insertMatches[1] ?? []));
    sort($insertTables);
    $expectedInsertTables = array_merge($expectedSources, ['auth_role_permission']);
    sort($expectedInsertTables);
    if ($insertTables !== $expectedInsertTables) {
        $errors[] = 'seed_table_inventory_invalid';
    }

    $forbidden = [
        '/^\s*(?:UPDATE|DELETE\s+FROM|REPLACE\s+INTO|LOAD\s+DATA|CREATE\s+USER|ALTER\s+USER|GRANT|REVOKE)\b/mi',
        '/\bON\s+DUPLICATE\s+KEY\b/i',
        '/^\s*INSERT INTO\s+(?:auth_user|auth_user_role|org_employee|pos_order|crm_member|tg_target|tg_delivery_queue|wa_message_log)\b/mi',
        '/\b(?:password_hash|api_token|access_token|webhook_secret|private_key)\b/i',
        '/(?:NAMUA|CACACIA|@[A-Za-z]|https?:\/\/|\/www\/)/i',
    ];
    foreach ($forbidden as $pattern) {
        if (preg_match($pattern, $sql) === 1) {
            $errors[] = 'forbidden_seed_content';
        }
    }

    foreach ((array)($seedPolicy['artifact_rows'] ?? []) as $table => $expectedCount) {
        $pattern = '/INSERT INTO\s+' . preg_quote((string)$table, '/') . '\s+\([^)]+\)\s+VALUES\s*\n(.*?);/si';
        preg_match_all($pattern, $sql, $blocks);
        $actualCount = 0;
        foreach ($blocks[1] ?? [] as $block) {
            $actualCount += preg_match_all('/^\s{2}\(/m', $block);
        }
        if (!is_int($expectedCount) || $expectedCount < 1 || $actualCount !== $expectedCount) {
            $errors[] = 'seed_row_inventory_invalid';
        }
    }

    if (substr_count($sql, "('SUPERADMIN', 'Super Admin', 'Bypass semua izin, akses penuh', NULL, 1)") !== 1
        || preg_match('/FROM auth_role role CROSS JOIN sys_page page/i', $sql) !== 1
        || preg_match("/WHERE role\.role_code = 'SUPERADMIN' AND role\.is_active = 1/i", $sql) !== 1) {
        $errors[] = 'superadmin_seed_invalid';
    }
    $firstInsert = strpos($sql, 'INSERT INTO');
    $disable = strpos($sql, 'SET FOREIGN_KEY_CHECKS=0;');
    $start = strpos($sql, 'START TRANSACTION;');
    $commit = strrpos($sql, 'COMMIT;');
    $enable = strrpos($sql, 'SET FOREIGN_KEY_CHECKS=1;');
    if ($firstInsert === false || $disable === false || $start === false || $commit === false || $enable === false
        || $disable > $firstInsert || $start > $firstInsert || $commit < $firstInsert || $enable < $commit) {
        $errors[] = 'seed_transaction_boundary_invalid';
    }

    return array_values(array_unique($errors));
}

function a512_validate_baseline(string $root, string $policyPath): array
{
    $realRoot = realpath($root);
    $realPolicy = realpath($policyPath);
    if ($realRoot === false || $realPolicy === false || is_link($policyPath)
        || !str_starts_with($realPolicy, $realRoot . DIRECTORY_SEPARATOR)) {
        return ['ok'=>false,'errors'=>['policy_path_invalid']];
    }
    $policy = json_decode((string)file_get_contents($realPolicy), true);
    if (!is_array($policy) || !a512_exact_keys($policy, ['format','version','cutoff','schema','seed','post_baseline_migrations'])
        || ($policy['format'] ?? '') !== 'finance-clean-install-baseline-policy'
        || ($policy['version'] ?? null) !== 1 || !is_array($policy['schema'] ?? null)
        || !is_array($policy['seed'] ?? null) || ($policy['seed']['status'] ?? '') !== 'approved_minimal') {
        return ['ok'=>false,'errors'=>['policy_invalid']];
    }
    $schemaRelative = (string)($policy['schema']['path'] ?? '');
    $schemaPath = $realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $schemaRelative);
    $realSchema = realpath($schemaPath);
    if ($realSchema === false || is_link($schemaPath)
        || !str_starts_with($realSchema, $realRoot . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'baseline' . DIRECTORY_SEPARATOR)) {
        return ['ok'=>false,'errors'=>['schema_path_invalid']];
    }
    $sql = (string)file_get_contents($realSchema);
    $errors = a512_schema_errors($sql, $policy['schema']);
    $expectedHash = (string)($policy['schema']['sha256'] ?? '');
    $actualHash = hash('sha256', $sql);
    if (preg_match('/^[0-9a-f]{64}$/D', $expectedHash) !== 1 || !hash_equals($expectedHash, $actualHash)) {
        $errors[] = 'schema_checksum_mismatch';
    }

    $seedRelative = (string)($policy['seed']['path'] ?? '');
    $seedPath = $realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $seedRelative);
    $realSeed = realpath($seedPath);
    if ($realSeed === false || is_link($seedPath)
        || dirname($realSeed) !== $realRoot . DIRECTORY_SEPARATOR . 'sql') {
        $errors[] = 'seed_path_invalid';
    } else {
        $seedSql = (string)file_get_contents($realSeed);
        $errors = array_merge($errors, a512_seed_errors($seedSql, $policy['seed']));
        $expectedSeedHash = (string)($policy['seed']['sha256'] ?? '');
        $actualSeedHash = hash('sha256', $seedSql);
        if (preg_match('/^[0-9a-f]{64}$/D', $expectedSeedHash) !== 1
            || !hash_equals($expectedSeedHash, $actualSeedHash)) {
            $errors[] = 'seed_checksum_mismatch';
        }
    }
    return [
        'ok'=>$errors===[],
        'errors'=>array_values(array_unique($errors)),
        'table_count'=>(int)($policy['schema']['table_count'] ?? 0),
        'seed_status'=>(string)($policy['seed']['status'] ?? ''),
        'seed_rows'=>(array)($policy['seed']['artifact_rows'] ?? []),
    ];
}

if (!defined('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY')) {
    $root = dirname(__DIR__, 2);
    $result = a512_validate_baseline($root, $root . '/tools/db/clean_install_baseline_policy.json');
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}
