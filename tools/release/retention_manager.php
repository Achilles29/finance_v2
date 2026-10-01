<?php

declare(strict_types=1);

/** A5.15 filesystem retention manager. Apply moves files to quarantine; it never unlinks them. */

final class FinanceRetentionFailure extends RuntimeException
{
}

function financeRetentionLoadPolicy(string $root): array
{
    $path = $root . '/tools/release/retention_policy.json';
    $policy = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
    if (!is_array($policy) || json_last_error() !== JSON_ERROR_NONE) {
        throw new FinanceRetentionFailure('retention policy is missing or invalid JSON');
    }
    return $policy;
}

/** @return string[] */
function financeRetentionPolicyErrors(array $policy): array
{
    $errors = [];
    if (($policy['schema'] ?? null) !== 'finance.retention-policy'
        || ($policy['schema_version'] ?? null) !== 1
        || ($policy['status'] ?? null) !== 'safe_baseline'
        || !is_int($policy['quarantine_grace_days'] ?? null)
        || $policy['quarantine_grace_days'] < 7
    ) {
        $errors[] = 'policy header is invalid';
    }
    $roots = $policy['roots'] ?? null;
    if (!is_array($roots) || array_keys($roots) !== ['a5_runtime', 'backup_runtime']) {
        $errors[] = 'runtime root registry is invalid';
    } else {
        foreach ($roots as $id => $root) {
            if (!is_array($root)
                || preg_match('/\AFINANCE_[A-Z0-9_]+_DIR\z/D', (string)($root['environment'] ?? '')) !== 1
                || preg_match('#\A/[^\x00]+\z#D', (string)($root['default'] ?? '')) !== 1
            ) {
                $errors[] = 'invalid runtime root: ' . $id;
            }
        }
    }
    $seen = [];
    foreach (($policy['filesystem_rules'] ?? []) as $rule) {
        $id = (string)($rule['id'] ?? '');
        if ($id === '' || isset($seen[$id]) || !isset($roots[$rule['root'] ?? ''])
            || preg_match('/\A[A-Za-z0-9_-]+\z/D', $id) !== 1
            || preg_match('#\A[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*\z#D', (string)($rule['directory'] ?? '')) !== 1
            || !is_int($rule['minimum_age_days'] ?? null) || $rule['minimum_age_days'] < 1
            || !is_int($rule['retain_newest'] ?? null) || $rule['retain_newest'] < 0
            || !is_int($rule['maximum_items_per_run'] ?? null) || $rule['maximum_items_per_run'] < 1
            || $rule['maximum_items_per_run'] > 100
            || !is_array($rule['suffixes'] ?? null) || $rule['suffixes'] === []
            || !is_bool($rule['require_sha256_companion'] ?? null)
        ) {
            $errors[] = 'invalid filesystem rule: ' . ($id !== '' ? $id : '(missing)');
            continue;
        }
        $seen[$id] = true;
        foreach ($rule['suffixes'] as $suffix) {
            if (!is_string($suffix) || preg_match('/\A\.[A-Za-z0-9.]+\z/D', $suffix) !== 1) {
                $errors[] = 'invalid suffix in rule: ' . $id;
                break;
            }
        }
    }
    $requiredDatabaseRules = [
        'availability_rebuild_success_detail' => ['pos_product_availability_rebuild_log', 'rebuilt_at', 90, 'mismatch_flag = 0'],
        'authentication_failures' => ['auth_login_failure', 'failed_at', 180, '1 = 1'],
        'authentication_sessions' => ['auth_session_log', 'login_at', 365, '1 = 1'],
        'whatsapp_delivery_log' => ['wa_send_log', 'sent_at', 365, "status IN ('SENT','FAILED')"],
        'telegram_delivery_log' => ['tg_delivery_log', 'created_at', 365, "delivery_status IN ('SENT','FAILED')"],
    ];
    $actualDatabaseRules = [];
    foreach (($policy['database_rules'] ?? []) as $rule) {
        $id = (string)($rule['id'] ?? '');
        $actualDatabaseRules[] = $id;
        $expected = $requiredDatabaseRules[$id] ?? null;
        if (!is_array($expected)
            || ($rule['table'] ?? null) !== $expected[0]
            || ($rule['timestamp_column'] ?? null) !== $expected[1]
            || ($rule['minimum_age_days'] ?? null) !== $expected[2]
            || ($rule['predicate'] ?? null) !== $expected[3]
            || ($rule['action'] ?? null) !== 'archive_then_batch_purge'
            || ($rule['enabled'] ?? null) !== false
        ) {
            $errors[] = 'unsafe database retention rule: ' . $id;
        }
    }
    if ($actualDatabaseRules !== array_keys($requiredDatabaseRules)) {
        $errors[] = 'database retention inventory is incomplete or out of order';
    }
    $neverDelete = $policy['never_age_delete'] ?? null;
    foreach (['uploads', 'database_transaction_ledgers', 'audit_transaction_log', 'financial_journals', 'active_queue_rows', 'service_credentials'] as $required) {
        if (!is_array($neverDelete) || !in_array($required, $neverDelete, true)) {
            $errors[] = 'never-delete class is missing: ' . $required;
        }
    }
    return $errors;
}

function financeRetentionPolicyHash(string $root): string
{
    $hash = hash_file('sha256', $root . '/tools/release/retention_policy.json');
    if (!is_string($hash)) {
        throw new FinanceRetentionFailure('retention policy hash is unavailable');
    }
    return $hash;
}

function financeRetentionResolveRoots(array $policy, array $overrides = []): array
{
    $resolved = [];
    foreach ($policy['roots'] as $id => $definition) {
        $value = $overrides[$id] ?? trim((string)getenv($definition['environment']));
        if ($value === '') {
            $value = $definition['default'];
        }
        if (!is_string($value) || $value === '' || $value[0] !== '/' || strpos($value, "\0") !== false) {
            throw new FinanceRetentionFailure('runtime root must be an absolute path: ' . $id);
        }
        $resolved[$id] = rtrim($value, DIRECTORY_SEPARATOR);
    }
    return $resolved;
}

function financeRetentionHasSuffix(string $name, array $suffixes): bool
{
    foreach ($suffixes as $suffix) {
        if (str_ends_with($name, $suffix)) {
            return true;
        }
    }
    return false;
}

function financeRetentionChecksumCompanion(string $path): ?string
{
    $checksumPath = $path . '.sha256';
    if (is_link($checksumPath) || !is_file($checksumPath) || !is_readable($checksumPath)) {
        return null;
    }
    $source = (string)file_get_contents($checksumPath);
    if (preg_match('/\A\s*([a-f0-9]{64})(?:\s|\z)/D', $source, $match) !== 1) {
        return null;
    }
    $actual = hash_file('sha256', $path);
    return is_string($actual) && hash_equals($match[1], $actual) ? $checksumPath : null;
}

/** @return array{policy_sha256:string,confirmation:string,generated_at:string,rules:array<int,array<string,mixed>>,totals:array<string,int>} */
function financeRetentionPlan(string $projectRoot, array $policy, array $roots, int $now): array
{
    $policyHash = financeRetentionPolicyHash($projectRoot);
    $result = [
        'policy_sha256' => $policyHash,
        'confirmation' => substr($policyHash, 0, 16),
        'generated_at' => gmdate('c', $now),
        'rules' => [],
        'totals' => ['candidates' => 0, 'bytes' => 0, 'invalid_or_unverified' => 0],
    ];
    foreach ($policy['filesystem_rules'] as $rule) {
        $root = $roots[$rule['root']];
        $directory = $root . '/' . $rule['directory'];
        $entry = ['id' => $rule['id'], 'directory' => $directory, 'candidates' => [], 'invalid_or_unverified' => 0];
        if (!is_dir($directory)) {
            $entry['status'] = 'directory_absent';
            $result['rules'][] = $entry;
            continue;
        }
        if (is_link($root) || is_link($directory)) {
            throw new FinanceRetentionFailure('runtime root/directory may not be a symlink: ' . $rule['id']);
        }
        $rootReal = realpath($root);
        $directoryReal = realpath($directory);
        if ($rootReal === false || $directoryReal === false || !str_starts_with($directoryReal . '/', $rootReal . '/')) {
            throw new FinanceRetentionFailure('retention directory escapes its runtime root: ' . $rule['id']);
        }
        $eligible = [];
        foreach (scandir($directoryReal) ?: [] as $name) {
            if ($name === '.' || $name === '..' || !financeRetentionHasSuffix($name, $rule['suffixes'])) {
                continue;
            }
            $path = $directoryReal . '/' . $name;
            if (is_link($path) || !is_file($path)) {
                $entry['invalid_or_unverified']++;
                continue;
            }
            $mtime = filemtime($path);
            $size = filesize($path);
            $hash = hash_file('sha256', $path);
            if (!is_int($mtime) || !is_int($size) || !is_string($hash)) {
                $entry['invalid_or_unverified']++;
                continue;
            }
            $companion = null;
            if ($rule['require_sha256_companion']) {
                $companion = financeRetentionChecksumCompanion($path);
                if ($companion === null) {
                    $entry['invalid_or_unverified']++;
                    continue;
                }
            }
            $eligible[] = ['path' => $path, 'name' => $name, 'mtime' => $mtime, 'size' => $size, 'sha256' => $hash, 'companion' => $companion];
        }
        usort($eligible, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime'] ?: strcmp($a['name'], $b['name']));
        $eligible = array_slice($eligible, $rule['retain_newest']);
        $cutoff = $now - ($rule['minimum_age_days'] * 86400);
        foreach ($eligible as $candidate) {
            if ($candidate['mtime'] > $cutoff || count($entry['candidates']) >= $rule['maximum_items_per_run']) {
                continue;
            }
            $entry['candidates'][] = $candidate;
            $result['totals']['candidates']++;
            $result['totals']['bytes'] += $candidate['size'];
        }
        $entry['status'] = 'planned';
        $result['totals']['invalid_or_unverified'] += $entry['invalid_or_unverified'];
        $result['rules'][] = $entry;
    }
    return $result;
}

function financeRetentionWriteJson(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new FinanceRetentionFailure('retention audit JSON could not be encoded');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new FinanceRetentionFailure('retention audit could not be written');
    }
}

function financeRetentionApply(string $projectRoot, array $policy, array $roots, array $plan, string $confirmation): array
{
    $expected = substr(financeRetentionPolicyHash($projectRoot), 0, 16);
    if (!hash_equals($expected, $confirmation) || !hash_equals($plan['policy_sha256'], financeRetentionPolicyHash($projectRoot))) {
        throw new FinanceRetentionFailure('retention confirmation or policy hash does not match');
    }
    $runtime = $roots['a5_runtime'];
    if (!is_dir($runtime) && !mkdir($runtime, 0700, true)) {
        throw new FinanceRetentionFailure('A5 runtime root could not be created');
    }
    $runtimeReal = realpath($runtime);
    if ($runtimeReal === false || is_link($runtime)) {
        throw new FinanceRetentionFailure('A5 runtime root is unsafe');
    }
    $runId = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $quarantine = $runtimeReal . '/retention-quarantine/' . $runId;
    $evidence = $runtimeReal . '/evidence';
    if ((!is_dir(dirname($quarantine)) && !mkdir(dirname($quarantine), 0700, true))
        || !mkdir($quarantine, 0700, false)
        || (!is_dir($evidence) && !mkdir($evidence, 0700, true))
    ) {
        throw new FinanceRetentionFailure('retention quarantine/evidence directory could not be created');
    }
    $moved = [];
    foreach ($plan['rules'] as $rule) {
        foreach ($rule['candidates'] as $candidate) {
            $path = $candidate['path'];
            if (is_link($path) || !is_file($path)
                || filemtime($path) !== $candidate['mtime']
                || filesize($path) !== $candidate['size']
                || !hash_equals($candidate['sha256'], (string)hash_file('sha256', $path))
            ) {
                throw new FinanceRetentionFailure('candidate changed after planning: ' . basename($path));
            }
            $target = $quarantine . '/' . $rule['id'] . '__' . basename($path);
            if (file_exists($target) || !rename($path, $target)) {
                throw new FinanceRetentionFailure('candidate could not be moved to quarantine');
            }
            $movedEntry = ['source' => $path, 'quarantine' => $target, 'sha256' => $candidate['sha256'], 'size' => $candidate['size']];
            if (is_string($candidate['companion'])) {
                $companionTarget = $target . '.sha256';
                if (!rename($candidate['companion'], $companionTarget)) {
                    throw new FinanceRetentionFailure('checksum companion could not be moved to quarantine');
                }
                $movedEntry['checksum_companion'] = $companionTarget;
            }
            $moved[] = $movedEntry;
        }
    }
    $audit = [
        'schema' => 'finance.retention-run',
        'schema_version' => 1,
        'run_id' => $runId,
        'action' => 'quarantine',
        'policy_sha256' => $plan['policy_sha256'],
        'created_at' => gmdate('c'),
        'quarantine_grace_days' => $policy['quarantine_grace_days'],
        'moved' => $moved,
    ];
    $auditPath = $evidence . '/retention_' . $runId . '.json';
    financeRetentionWriteJson($auditPath, $audit);
    return ['status' => 'quarantined', 'run_id' => $runId, 'moved' => count($moved), 'audit_path' => $auditPath, 'quarantine' => $quarantine];
}

if (defined('FINANCE_RETENTION_LIBRARY_ONLY') && FINANCE_RETENTION_LIBRARY_ONLY) {
    return;
}

try {
    if (PHP_SAPI !== 'cli') {
        throw new FinanceRetentionFailure('retention manager is CLI-only');
    }
    $projectRoot = dirname(__DIR__, 2);
    $policy = financeRetentionLoadPolicy($projectRoot);
    $errors = financeRetentionPolicyErrors($policy);
    if ($errors !== []) {
        throw new FinanceRetentionFailure(implode('; ', $errors));
    }
    $mode = $argv[1] ?? 'plan';
    if (!in_array($mode, ['validate', 'plan', 'apply'], true)) {
        throw new FinanceRetentionFailure('usage: retention_manager.php validate|plan|apply [--confirm=POLICY_PREFIX]');
    }
    if ($mode === 'validate') {
        echo json_encode(['status' => 'ok', 'policy_sha256' => financeRetentionPolicyHash($projectRoot)], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    $roots = financeRetentionResolveRoots($policy);
    $plan = financeRetentionPlan($projectRoot, $policy, $roots, time());
    if ($mode === 'plan') {
        echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }
    $confirmation = '';
    foreach (array_slice($argv, 2) as $argument) {
        if (str_starts_with($argument, '--confirm=') && $confirmation === '') {
            $confirmation = substr($argument, 10);
            continue;
        }
        throw new FinanceRetentionFailure('apply accepts only one --confirm=POLICY_PREFIX argument');
    }
    echo json_encode(financeRetentionApply($projectRoot, $policy, $roots, $plan, $confirmation), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['status' => 'error', 'message' => $error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
