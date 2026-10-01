<?php

declare(strict_types=1);

if (!defined('A5_MIGRATION_LIBRARY_ONLY')) {
    define('A5_MIGRATION_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/migration_runner.php';

const GAP07_POLICY_SCHEMA = 'finance.legacy-sql-disposition';
const GAP07_POLICY_VERSION = 1;
const GAP07_MANAGED_SOURCE_LINE = 'finance-managed-v1';

function gap07JsonFile(string $root, string $relative, ?string $expectedHash = null): array
{
    if ($relative === '' || $relative[0] === '/' || strpos($relative, '\\') !== false
        || strpos($relative, "\0") !== false || in_array('..', explode('/', $relative), true)
        || in_array('.', explode('/', $relative), true)
    ) {
        a5_fail('gap07_path', 'GAP-07 evidence path is unsafe.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $real = realpath($path);
    if (is_link($path) || !is_file($path) || !is_string($real)
        || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0
    ) {
        a5_fail('gap07_path', 'GAP-07 evidence file is missing or unsafe.');
    }
    $raw = @file_get_contents($real);
    $hash = @hash_file('sha256', $real);
    if (!is_string($raw) || !is_string($hash)
        || ($expectedHash !== null && !hash_equals($expectedHash, $hash))
    ) {
        a5_fail('gap07_evidence_drift', 'GAP-07 evidence checksum has drifted.');
    }
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        a5_fail('gap07_json', 'GAP-07 evidence JSON is malformed.');
    }
    if (!is_array($decoded)) {
        a5_fail('gap07_json', 'GAP-07 evidence JSON root is invalid.');
    }
    return $decoded;
}

function gap07FileHash(string $root, string $relative, string $expectedHash): void
{
    if (preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1 || $relative === '' || $relative[0] === '/'
        || strpos($relative, '\\') !== false || in_array('..', explode('/', $relative), true)
        || in_array('.', explode('/', $relative), true)
    ) {
        a5_fail('gap07_path', 'GAP-07 covered file path or digest is unsafe.');
    }
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $real = realpath($path);
    $hash = @hash_file('sha256', $path);
    if (is_link($path) || !is_file($path) || !is_string($real)
        || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0
        || !is_string($hash) || !hash_equals($expectedHash, $hash)
    ) {
        a5_fail('gap07_coverage_drift', 'GAP-07 clean-install coverage has drifted.');
    }
}

function gap07LoadPolicy(string $root): array
{
    return gap07JsonFile($root, 'tools/db/legacy_sql_disposition_policy.json');
}

function gap07Validate(string $root, ?array $policyOverride = null): array
{
    $policy = $policyOverride ?? gap07LoadPolicy($root);
    if (!a5_exact_keys($policy, [
        'schema', 'schema_version', 'source_evidence', 'clean_install_coverage',
        'upgrade_contract', 'allowed_actions', 'records',
    ]) || ($policy['schema'] ?? null) !== GAP07_POLICY_SCHEMA
        || ($policy['schema_version'] ?? null) !== GAP07_POLICY_VERSION
        || ($policy['allowed_actions'] ?? null) !== ['baseline', 'enroll', 'replace', 'retire']
        || !is_array($policy['source_evidence']) || !is_array($policy['clean_install_coverage'])
        || !is_array($policy['upgrade_contract']) || !is_array($policy['records'])
        || !a5_is_list($policy['records']) || count($policy['records']) !== 7
    ) {
        a5_fail('gap07_policy_schema', 'GAP-07 disposition policy schema is invalid.');
    }

    $evidence = $policy['source_evidence'];
    if (!a5_exact_keys($evidence, ['inventory_path', 'inventory_sha256', 'fingerprint_path', 'fingerprint_sha256'])
        || !is_string($evidence['inventory_path']) || !is_string($evidence['fingerprint_path'])
        || !is_string($evidence['inventory_sha256']) || !is_string($evidence['fingerprint_sha256'])
        || preg_match('/^[a-f0-9]{64}$/D', $evidence['inventory_sha256']) !== 1
        || preg_match('/^[a-f0-9]{64}$/D', $evidence['fingerprint_sha256']) !== 1
    ) {
        a5_fail('gap07_policy_schema', 'GAP-07 source evidence schema is invalid.');
    }
    $inventory = gap07JsonFile($root, $evidence['inventory_path'], $evidence['inventory_sha256']);
    $fingerprints = gap07JsonFile($root, $evidence['fingerprint_path'], $evidence['fingerprint_sha256']);
    if (!is_array($inventory['records'] ?? null) || !is_array($fingerprints['records'] ?? null)
        || count($inventory['records']) !== 7 || count($fingerprints['records']) !== 7
    ) {
        a5_fail('gap07_evidence_schema', 'GAP-07 inventory or fingerprint scope is invalid.');
    }

    $coverage = $policy['clean_install_coverage'];
    $coverageKeys = [
        'policy_path', 'policy_sha256', 'schema_path', 'schema_sha256',
        'navigation_seed_path', 'navigation_seed_sha256', 'whatsapp_seed_path', 'whatsapp_seed_sha256',
    ];
    if (!a5_exact_keys($coverage, $coverageKeys)) {
        a5_fail('gap07_policy_schema', 'GAP-07 clean-install coverage schema is invalid.');
    }
    foreach (['policy_sha256', 'schema_sha256', 'navigation_seed_sha256', 'whatsapp_seed_sha256'] as $digestField) {
        if (!is_string($coverage[$digestField]) || preg_match('/^[a-f0-9]{64}$/D', $coverage[$digestField]) !== 1) {
            a5_fail('gap07_policy_schema', 'GAP-07 clean-install digest is invalid.');
        }
    }
    $baselinePolicy = gap07JsonFile($root, $coverage['policy_path'], $coverage['policy_sha256']);
    gap07FileHash($root, $coverage['schema_path'], $coverage['schema_sha256']);
    gap07FileHash($root, $coverage['navigation_seed_path'], $coverage['navigation_seed_sha256']);
    gap07FileHash($root, $coverage['whatsapp_seed_path'], $coverage['whatsapp_seed_sha256']);
    if (($baselinePolicy['schema']['path'] ?? null) !== $coverage['schema_path']
        || ($baselinePolicy['schema']['sha256'] ?? null) !== $coverage['schema_sha256']
        || ($baselinePolicy['seed']['path'] ?? null) !== $coverage['navigation_seed_path']
        || ($baselinePolicy['seed']['sha256'] ?? null) !== $coverage['navigation_seed_sha256']
        || !in_array('2026-09-05e-whatsapp-safe-reference-seed', $baselinePolicy['post_baseline_migrations'] ?? [], true)
    ) {
        a5_fail('gap07_coverage_drift', 'GAP-07 baseline policy does not cover the declared successors.');
    }

    $upgrade = $policy['upgrade_contract'];
    $requiredSource = [
        'installed_release_signature_verified', 'migration_ledger_exact', 'legacy_fingerprint_4_of_4',
        'post_install_health_pass', 'verified_backup_bundle_before_apply',
    ];
    $requiredSequence = [
        'verify_target_artifact_signature', 'verify_runtime_compatibility', 'verify_source_release_receipt',
        'validate_migration_catalog', 'verify_database_fingerprint_and_ledger',
        'build_and_verify_backup_bundle', 'plan_managed_migrations_only', 'apply_managed_migrations',
        'run_post_install_health_check', 'atomic_release_switch_or_rollback',
    ];
    if (!a5_exact_keys($upgrade, [
        'automatic_source_line', 'pre_catalog_source_action', 'legacy_sql_execution',
        'legacy_migration_ledger_insertion', 'managed_source_requirements', 'sequence',
    ]) || $upgrade['automatic_source_line'] !== GAP07_MANAGED_SOURCE_LINE
        || $upgrade['pre_catalog_source_action'] !== 'manual_bridge_required'
        || $upgrade['legacy_sql_execution'] !== 'forbidden'
        || $upgrade['legacy_migration_ledger_insertion'] !== 'forbidden'
        || $upgrade['managed_source_requirements'] !== $requiredSource
        || $upgrade['sequence'] !== $requiredSequence
    ) {
        a5_fail('gap07_upgrade_contract', 'GAP-07 updater sequence or source boundary is invalid.');
    }

    $inventoryByPath = [];
    foreach ($inventory['records'] as $record) {
        if (!is_array($record) || !is_string($record['path'] ?? null)) {
            a5_fail('gap07_evidence_schema', 'GAP-07 inventory record is invalid.');
        }
        $inventoryByPath[$record['path']] = $record;
    }
    $fingerprintByPath = [];
    foreach ($fingerprints['records'] as $record) {
        if (!is_array($record) || !is_string($record['path'] ?? null)) {
            a5_fail('gap07_evidence_schema', 'GAP-07 fingerprint record is invalid.');
        }
        $fingerprintByPath[$record['path']] = $record;
    }

    $validatedCatalog = a5_validate_catalog($root);
    $legacyPaths = $validatedCatalog['catalog']['legacy_unmanaged_sql'];
    $managedPaths = array_column($validatedCatalog['catalog']['migrations'], 'path');
    $actions = [];
    $policyPaths = [];
    $recordKeys = [
        'path', 'sha256', 'action', 'automatic_execution', 'clean_install_coverage',
        'bridge_fingerprint_checks', 'successor', 'decision',
    ];
    foreach ($policy['records'] as $record) {
        if (!is_array($record) || !a5_exact_keys($record, $recordKeys)
            || !is_string($record['path']) || isset($policyPaths[$record['path']])
            || !isset($inventoryByPath[$record['path']], $fingerprintByPath[$record['path']])
            || !is_string($record['sha256']) || preg_match('/^[a-f0-9]{64}$/D', $record['sha256']) !== 1
            || !hash_equals($inventoryByPath[$record['path']]['sha256'], $record['sha256'])
            || !hash_equals($fingerprintByPath[$record['path']]['sha256'], $record['sha256'])
            || !in_array($record['action'], $policy['allowed_actions'], true)
            || $record['automatic_execution'] !== false
            || !is_string($record['clean_install_coverage']) || $record['clean_install_coverage'] === ''
            || !is_array($record['bridge_fingerprint_checks']) || !a5_is_list($record['bridge_fingerprint_checks'])
            || !is_string($record['successor']) || $record['successor'] === ''
            || !is_string($record['decision']) || trim($record['decision']) === '' || strlen($record['decision']) > 500
        ) {
            a5_fail('gap07_record', 'GAP-07 disposition record is invalid.');
        }
        $policyPaths[$record['path']] = true;
        $actions[] = $record['action'];
        $fingerprint = $fingerprintByPath[$record['path']];
        $expectedChecks = array_column($fingerprint['checks'] ?? [], 'id');
        if ($record['action'] === 'enroll') {
            if (($inventoryByPath[$record['path']]['disposition'] ?? null) !== 'candidate-canonical'
                || ($fingerprint['eligibility'] ?? null) !== 'evidence_only' || $expectedChecks === []
                || $record['bridge_fingerprint_checks'] !== $expectedChecks
            ) {
                a5_fail('gap07_enrollment_evidence', 'Enroll disposition lacks exact fingerprint evidence.');
            }
        } elseif ($record['bridge_fingerprint_checks'] !== []) {
            a5_fail('gap07_record', 'Only enroll disposition may carry bridge fingerprints.');
        }
    }
    if (array_keys($policyPaths) !== $legacyPaths
        || array_intersect(array_keys($policyPaths), $managedPaths) !== []
        || array_count_values($actions) !== ['baseline' => 1, 'replace' => 1, 'enroll' => 4, 'retire' => 1]
    ) {
        a5_fail('gap07_scope', 'GAP-07 action count or legacy scope is invalid.');
    }

    $whatsappSeed = null;
    foreach ($validatedCatalog['catalog']['migrations'] as $migration) {
        if (($migration['id'] ?? '') === '2026-09-05e-whatsapp-safe-reference-seed') {
            $whatsappSeed = $migration;
        }
    }
    if (!is_array($whatsappSeed) || $whatsappSeed['path'] !== $coverage['whatsapp_seed_path']
        || $whatsappSeed['sha256'] !== $coverage['whatsapp_seed_sha256']
        || $whatsappSeed['classification'] !== 'seed'
        || $whatsappSeed['policies'] !== ['clean_install', 'upgrade']
    ) {
        a5_fail('gap07_successor_missing', 'Managed WhatsApp successor seed is missing or drifted.');
    }

    $upgradePlan = a5_plan($validatedCatalog, 'upgrade');
    if (array_intersect(array_column($upgradePlan, 'path'), $legacyPaths) !== []) {
        a5_fail('gap07_legacy_replay', 'Legacy SQL entered the managed upgrade plan.');
    }
    return [
        'policy' => $policy,
        'catalog' => $validatedCatalog,
        'actions' => array_count_values($actions),
        'upgrade_plan' => $upgradePlan,
    ];
}

if (defined('GAP07_LEGACY_UPGRADE_GUARD_LIBRARY_ONLY') && GAP07_LEGACY_UPGRADE_GUARD_LIBRARY_ONLY) {
    return;
}

try {
    $arguments = $argv ?? [];
    foreach ($arguments as $argument) {
        if (is_string($argument) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $argument)) {
            a5_fail('credential_cli', 'Credential arguments are forbidden.');
        }
    }
    $root = realpath(dirname(__DIR__, 2));
    if (!is_string($root)) {
        a5_fail('root_missing', 'Repository root is unavailable.');
    }
    $validated = gap07Validate($root);
    $mode = $arguments[1] ?? '';
    if ($mode === 'validate' && count($arguments) === 2) {
        a5_emit([
            'status' => 'ok', 'mode' => 'validate', 'policy_version' => GAP07_POLICY_VERSION,
            'legacy_records' => 7, 'actions' => $validated['actions'],
            'legacy_execution' => 'forbidden', 'managed_upgrade_migrations' => count($validated['upgrade_plan']),
        ]);
        exit(0);
    }
    if ($mode !== 'plan' || count($arguments) !== 3 || strpos($arguments[2], '--source-line=') !== 0) {
        a5_fail('usage', 'Use validate or plan --source-line=finance-managed-v1.');
    }
    $sourceLine = substr($arguments[2], 14);
    if ($sourceLine === 'pre-catalog') {
        a5_fail('manual_bridge_required', 'Pre-catalog installations cannot use automatic update.');
    }
    if ($sourceLine !== GAP07_MANAGED_SOURCE_LINE) {
        a5_fail('source_line_unsupported', 'Source release line is unsupported.');
    }
    $migrations = [];
    foreach ($validated['upgrade_plan'] as $migration) {
        $migrations[] = [
            'id' => $migration['id'], 'order' => $migration['order'], 'path' => $migration['path'],
            'sha256' => $migration['sha256'], 'dependencies' => $migration['dependencies'],
        ];
    }
    a5_emit([
        'status' => 'ok', 'mode' => 'plan', 'source_line' => GAP07_MANAGED_SOURCE_LINE,
        'source_requirements' => $validated['policy']['upgrade_contract']['managed_source_requirements'],
        'sequence' => $validated['policy']['upgrade_contract']['sequence'],
        'legacy_sql_execution' => 'forbidden', 'migrations' => $migrations,
        'database_action' => 'none',
    ]);
} catch (A5MigrationFailure $error) {
    fwrite(STDERR, json_encode([
        'status' => 'error', 'code' => $error->failureCode, 'message' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(in_array($error->failureCode, ['usage', 'source_line_unsupported'], true) ? 2 : 1);
}
