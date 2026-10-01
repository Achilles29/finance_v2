<?php

declare(strict_types=1);

define('GAP07_LEGACY_UPGRADE_GUARD_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/legacy_upgrade_guard.php';

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $label . PHP_EOL;
        return;
    }
    $failures[] = $label;
    fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
};
$failsWith = static function (callable $operation, string $code): bool {
    try {
        $operation();
    } catch (A5MigrationFailure $failure) {
        return $failure->failureCode === $code;
    }
    return false;
};
$run = static function (array $command, string $cwd): array {
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd, ['PATH' => '/usr/bin:/bin', 'DB_PASSWORD' => 'GAP07_TRIPWIRE']);
    if (!is_resource($process)) {
        return ['code' => 127, 'out' => '', 'err' => ''];
    }
    $out = (string)stream_get_contents($pipes[1]);
    $err = (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
};

$validated = null;
try {
    $validated = gap07Validate($root);
} catch (Throwable $error) {
    fwrite(STDERR, 'VALIDATION ERROR: ' . $error->getMessage() . PHP_EOL);
}
$check(is_array($validated), 'final legacy disposition policy validates against immutable evidence and catalog');
$check(
    is_array($validated) && ($validated['actions'] ?? null) === [
        'baseline' => 1, 'replace' => 1, 'enroll' => 4, 'retire' => 1,
    ],
    'seven legacy files have one final baseline, four enroll, one replace, and one retire action'
);

$legacyPaths = $validated['catalog']['catalog']['legacy_unmanaged_sql'] ?? [];
$planPaths = is_array($validated) ? array_column($validated['upgrade_plan'], 'path') : [];
$planIds = is_array($validated) ? array_column($validated['upgrade_plan'], 'id') : [];
$check(array_intersect($legacyPaths, $planPaths) === [], 'no legacy SQL can enter the managed upgrade plan');
$check(
    $planIds === [
        '2026-09-04c-a5-schema-migration-registry-foundation',
        '2026-09-05e-whatsapp-safe-reference-seed',
        '2026-09-05a-telegram-bot-foundation',
        '2026-09-05b-telegram-setup-guide',
        '2026-09-05c-telegram-safe-activation-default',
        '2026-09-06a-component-formula-version-history',
        '2026-09-06b-component-formula-restore-action',
        '2026-09-06c-pos-mobile-reversal-step-up',
        '2026-09-06d-pos-mobile-reprint-step-up',
        '2026-09-06e-activity-audit-foundation',
        '2026-09-06f-pos-mobile-cashier-close-step-up',
        '2026-09-06g-pos-mobile-reservation-refund-step-up',
        '2026-09-06h-roastery-label-template-studio',
        '2026-09-06i-a3-sidebar-task-oriented-layout',
        '2026-09-07a-c2-c4-business-profile-license-runtime-foundation',
        '2026-09-12a-roast-connect-catalog',
        '2026-09-13a-finance-mutation-reporting-category', '2026-09-14a-finance-control-workspace', '2026-09-14b-finance-control-operations',
        '2026-09-14c-finance-allocation-bank-review', '2026-09-15a-finance-general-ledger', '2026-09-15b-finance-journal-assistant', '2026-09-15c-application-user-guide', '2026-09-16a-procurement-stock-review', '2026-09-20a-pos-stock-commit-not-required', '2026-09-23a-module-notifications',
    ],
    'managed-v1 upgrade plan is exact, ordered, and excludes clean-install-only navigation seed'
);

$policy = gap07LoadPolicy($root);
$check(
    array_column($policy['records'], 'automatic_execution') === array_fill(0, 7, false)
        && ($policy['upgrade_contract']['legacy_sql_execution'] ?? '') === 'forbidden'
        && ($policy['upgrade_contract']['legacy_migration_ledger_insertion'] ?? '') === 'forbidden',
    'legacy replay and false migration-ledger adoption are explicitly forbidden'
);
$enrollChecks = [];
foreach ($policy['records'] as $record) {
    if ($record['action'] === 'enroll') {
        $enrollChecks[$record['path']] = $record['bridge_fingerprint_checks'];
    }
}
$check(
    count($enrollChecks) === 4
        && $enrollChecks['sql/2026-09-03a_auth_login_throttle_foundation.sql'] === ['auth_login_failure_table_columns', 'auth_login_failure_fk']
        && count($enrollChecks['sql/2026-09-04b_a3_page_alias_registry.sql'] ?? []) === 5,
    'all enroll actions are bound to exact structural and semantic fingerprint IDs'
);

$seedPath = $root . '/sql/2026-09-05e_whatsapp_safe_reference_seed.sql';
$seed = (string)file_get_contents($seedPath);
$withoutComments = preg_replace('/^\s*--.*$/m', '', $seed);
$check(
    substr_count($seed, 'WHERE NOT EXISTS') === 2
        && strpos($seed, "'REPORT_DEFAULT'") !== false
        && preg_match('/\b(CREATE|ALTER|DROP|TRUNCATE|DELETE|UPDATE|REPLACE)\b/i', (string)$withoutComments) !== 1,
    'canonical WhatsApp successor is repeat-safe seed-only SQL without DDL or destructive DML'
);
$check(
    hash_file('sha256', $seedPath) === ($policy['clean_install_coverage']['whatsapp_seed_sha256'] ?? '')
        && ($policy['records'][0]['successor'] ?? '') === '2026-09-05e-whatsapp-safe-reference-seed',
    'historical WhatsApp default seed is replaced by one checksum-bound managed successor'
);

$guard = dirname(__DIR__) . '/db/legacy_upgrade_guard.php';
$validateCli = $run([PHP_BINARY, $guard, 'validate'], $root);
$planA = $run([PHP_BINARY, $guard, 'plan', '--source-line=finance-managed-v1'], $root);
$planB = $run([PHP_BINARY, $guard, 'plan', '--source-line=finance-managed-v1'], $root);
$validateJson = json_decode(trim($validateCli['out']), true);
$planJson = json_decode(trim($planA['out']), true);
$check(
    $validateCli['code'] === 0 && ($validateJson['legacy_records'] ?? null) === 7
        && ($validateJson['managed_upgrade_migrations'] ?? null) === 26,
    'DB-free validate reports seven retired legacy paths and twenty-six managed upgrade migrations'
);
$check(
    $planA['code'] === 0 && $planA['out'] === $planB['out']
        && ($planJson['database_action'] ?? '') === 'none'
        && ($planJson['legacy_sql_execution'] ?? '') === 'forbidden'
        && count($planJson['sequence'] ?? []) === 10,
    'managed-v1 updater plan is deterministic, read-only, and carries the full fail-closed sequence'
);
$check(
    strpos($validateCli['out'] . $validateCli['err'] . $planA['out'] . $planA['err'], 'GAP07_TRIPWIRE') === false,
    'guard never reads or exposes database credential environment values'
);

$preCatalog = $run([PHP_BINARY, $guard, 'plan', '--source-line=pre-catalog'], $root);
$preCatalogError = json_decode(trim($preCatalog['err']), true);
$check(
    $preCatalog['code'] === 1 && ($preCatalogError['code'] ?? '') === 'manual_bridge_required',
    'pre-catalog installation is blocked from automatic update and routed to manual bridge review'
);
$unknown = $run([PHP_BINARY, $guard, 'plan', '--source-line=unknown'], $root);
$unknownError = json_decode(trim($unknown['err']), true);
$check(
    $unknown['code'] === 2 && ($unknownError['code'] ?? '') === 'source_line_unsupported',
    'unknown source release line fails closed'
);

$case = $policy;
$case['source_evidence']['inventory_sha256'] = str_repeat('0', 64);
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_evidence_drift'),
    'inventory checksum drift is rejected'
);
$case = $policy;
$case['clean_install_coverage']['schema_sha256'] = str_repeat('0', 64);
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_coverage_drift'),
    'baseline checksum drift is rejected'
);
$case = $policy;
$case['records'][2]['automatic_execution'] = true;
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_record'),
    'legacy automatic execution flag is rejected'
);
$case = $policy;
$case['records'][2]['bridge_fingerprint_checks'] = [];
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_enrollment_evidence'),
    'enroll action without exact fingerprint evidence is rejected'
);
$case = $policy;
$case['records'][1]['action'] = 'retire';
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_scope'),
    'disposition action-count drift is rejected'
);
$case = $policy;
$case['upgrade_contract']['legacy_sql_execution'] = 'allowed';
$check(
    $failsWith(static fn(): array => gap07Validate($root, $case), 'gap07_upgrade_contract'),
    'policy cannot enable legacy SQL replay'
);

echo 'GAP-07 LEGACY UPGRADE GUARD ' . ($failures === [] ? 'PASS' : 'FAIL')
    . ' passed=' . ($checks - count($failures)) . ' failed=' . count($failures) . PHP_EOL;
exit($failures === [] ? 0 : 1);
