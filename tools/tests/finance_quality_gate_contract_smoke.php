<?php

declare(strict_types=1);

define('FINANCE_QUALITY_GATE_LIBRARY_ONLY', true);
require __DIR__ . '/finance_quality_gate.php';

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

$expectedRequired = [
    'a1-required',
    'a1-master-endpoint-registry',
    'a1-master-audit-trail',
    'a1-access-simulator',
    'a1-public-review',
    'a1-review-admin-csrf',
    'a1-landing-page-mutation-guard',
    'printer-general-logo-upload',
    'roastery-label-template-studio',
    'a1-finance-period-close-csrf',
    'a1-finance-period-close-reopen-atomic',
    'a1-pos-reversal-step-up',
    'a1-pos-reversal-no-stock',
    'a1-pos-mobile-reversal-step-up',
    'a1-pos-mobile-incoming-scope',
    'a1-pos-mobile-login-throttle',
    'a1-pos-mobile-reservation-refund-step-up',
    'a1-pos-reservation-refund-step-up',
    'a1-component-adjustment-step-up',
    'a1-stock-adjustment-step-up',
    'a1-component-batch-step-up',
    'a1-component-daily-recon-step-up',
    'a1-stock-transfer-step-up',
    'a1-stock-opening-csrf',
    'a1-stock-opening-step-up',
    'a1-stock-opening-import-step-up',
    'a1-product-recipe-revision-audit',
    'a1-component-formula-revision-audit',
    'a1-component-formula-version-history',
    'a1-component-formula-restore',
    'a1-product-bundle-revision-audit',
    'a2-matrix',
    'a2-component-fifo-value',
    'a2-account-mutation-history',
    'a2-payroll-meal-mode',
    'attendance-auto-ph',
    'purchase-item-price-history',
    'sr-roastery-mobile-layout',
    'pos-sales-order-sort',
    'procurement-stock-review',
    'procurement-current-stock',
    'procurement-notification-pdf-stock',
    'module-notifications',
    'module-notifications-ui',
    'daily-sales-notification',
    'procurement-stock-review-race',
    'procurement-stock-review-verify',
    'procurement-stock-review-client',
    'finance-mutation-reporting',
    'finance-control-workspace',
    'finance-control-operations',
    'finance-allocation-bank',
    'finance-allocation-postcheck',
    'finance-accounting',
    'application-user-guide',
    'application-user-guide-client',
    'activity-audit',
    'pos-multi-cashier',
    'pos-multi-cashier-ui',
    'auth-stale-session',
    'c2-c4-commercial-foundation',
    'c2-customer-publication',
    'c3-upload-storage',
    'c3-composer-compat',
    'c3-control-release-bridge',
    'c3-customer-clean-release',
    'customer-guided-setup',
    'customer-portable-contract',
    'feature-boundary-contract',
    'customer-update-authorization',
    'customer-source-coverage',
    'customer-durable-permission',
    'c3-control-build-adapter',
    'c3-clean-install-database',
    'c3-deployment-instance',
    'c3-linux-web-profile',
    'c3-control-delivery',
    'c4-control-license-verifier',
    'c4-control-license-protocol-cache',
    'a3-finance-ui-shell',
    'a3-division-movement-pagination',
    'a3-pos-reservation-ui',
    'a3-pos-cashier-recent-ui',
    'a3-pos-self-order-ui',
    'a3-pos-online-food-ui',
    'a3-component-stock-pagination',
    'a3-stock-list-consistency',
    'a3-stock-period-contract',
    'a3-workspace-navigation',
    'a3-navigation-registry',
    'a3-sidebar-information-architecture',
    'a3-page-alias-registry',
    'a3-route-collision',
    'a3-sidebar-favorite-registry',
    'a3-sidebar-renderer-single-source',
    'a4-cross-module',
    'a5-migration-catalog-runner-contract',
    'a5-migration-executor-contract',
    'a5-legacy-sql-inventory-contract',
    'a5-schema-fingerprint-contract',
    'a5-auth-login-failure-fk-probe-contract',
    'a5-auth-login-failure-fk-detail-probe-contract',
    'a5-auth-login-failure-named-fk-contract-probe-contract',
    'a5-backup-bundle-restore-preflight-contract',
    'a5-disposable-restore-drill-contract',
    'a5-clean-install-baseline-guard',
    'a5-first-owner-bootstrap-contract',
    'a5-post-install-health-contract',
    'a5-upgrade-rollback-contract',
    'a5-runtime-compatibility-contract',
    'a5-retention-lifecycle-contract',
    'a5-artifact-signature-contract',
    'gap07-legacy-upgrade-guard-contract',
    'a4-dependency-vulnerability-source',
    'a4-dependency-vulnerability-contract',
    'a4-static-analysis-source',
    'a4-static-analysis-contract',
    'a4-release-preflight-contract',
    'a4-release-artifact-contract',
    'web-runtime-boundary',
    'release-runtime-contract',
    'backup-source-isolation',
    'gap01-repository-runtime-boundary',
    'audit-roadmap-dashboard',
    'roadmap-consistency',
    'finance-quality-gate-contract',
    'pos-apk-web-backup-merge',
    'pos-mobile-device-binding-recovery',
    'pos-mobile-financial-writer-binding',
    'pos-mobile-order-action-reader-binding',
    'pos-mobile-order-reader-binding',
    'pos-mobile-print-document-binding',
    'pos-mobile-printer-binding',
    'pos-mobile-role-scope-negative',
    'telegram-module',
    'codex-telegram-summary',
];
$expectedDevelopment = [
    'a1-development',
    'pos-mobile-cashier-session-binding',
    'pos-mobile-draft-upsert-binding',
    'pos-mobile-reader-outlet-binding',
];
$expectedRelease = ['deployment-secret-config'];
$expectedRuntime = ['a4-browser-runtime', 'a4-printer-agent-http-runtime'];
$expectedPreflight = ['a4-release-preflight'];
$expectedSecurity = ['a4-dependency-vulnerability-runtime'];
$expectedStatic = ['a4-static-analysis-runtime'];
$manifestA = finance_quality_gate_manifest();
$manifestB = finance_quality_gate_manifest();
$requiredIds = array_column($manifestA['required'], 'id');
$developmentIds = array_column($manifestA['development'], 'id');
$releaseIds = array_column($manifestA['release'], 'id');
$runtimeIds = array_column($manifestA['runtime'], 'id');
$preflightIds = array_column($manifestA['preflight'], 'id');
$securityIds = array_column($manifestA['security'], 'id');
$staticIds = array_column($manifestA['static'], 'id');
$stagingIds = array_column($manifestA['staging'], 'id');

$check($manifestA === $manifestB, 'manifest generation is deterministic');
$check($requiredIds === $expectedRequired, 'required manifest order and count are explicit and stable');
$check($developmentIds === $expectedDevelopment, 'development manifest order and count are explicit and stable');
$check($releaseIds === $expectedRelease, 'strict deployment-secret smoke is the explicit release tier');
$check($runtimeIds === $expectedRuntime, 'browser and Printer Agent HTTP are explicit runtime gates');
$check($preflightIds === $expectedPreflight, 'A4 release preflight is an explicit gate tier');
$check($securityIds === $expectedSecurity, 'offline dependency scan is an explicit security tier');
$check($staticIds === $expectedStatic, 'PHPStan analysis is an explicit static tier');
$printerRuntime = $manifestA['runtime'][1]['runtime'] ?? '';
$check(
    $printerRuntime === finance_quality_gate_runtime_dir() . '/printer-venv/bin/python',
    'Printer Agent runtime is pinned to the A4 venv Python executable'
);
$allAutomatedIds = array_merge($requiredIds, $developmentIds, $releaseIds, $runtimeIds, $preflightIds, $securityIds, $staticIds, $stagingIds);
$check(
    count(array_unique($allAutomatedIds)) === count($allAutomatedIds),
    'all automated manifest IDs are unique'
);
$check(
    $stagingIds === ['a2-database-invariant-probe', 'a1-rbac-scope-staging', 'a5-runtime-compatibility-staging'],
    'staging adds read-only A2, RBAC scope, and A5.14 runtime compatibility probes'
);
$check(
    array_column($manifestA['staging'], 'env') === [
        ['CI_ENV' => 'staging'],
        ['CI_ENV' => 'staging'],
        ['CI_ENV' => 'staging'],
    ],
    'staging probes explicitly load the non-production staging configuration contract'
);

foreach (['parallel', 'release', 'staging'] as $profile) {
    $check(
        finance_quality_gate_profile_exit_code($profile, false, true, true, true) === 1,
        'required failure fails ' . $profile . ' profile'
    );
}
$check(
    finance_quality_gate_profile_exit_code('parallel', true, false, true, true) === 0,
    'development failure is nonblocking only in parallel profile'
);
$check(
    finance_quality_gate_profile_exit_code('release', true, false, true, true) === 1
        && finance_quality_gate_profile_exit_code('staging', true, false, true, true) === 1,
    'development failure blocks release and staging profiles'
);
$check(
    finance_quality_gate_profile_exit_code('parallel', true, true, false, true) === 0
        && finance_quality_gate_profile_exit_code('release', true, true, false, true) === 1
        && finance_quality_gate_profile_exit_code('staging', true, true, false, true) === 1,
    'release-tier failure is nonblocking only in parallel profile'
);
$check(
    finance_quality_gate_profile_exit_code('staging', true, true, true, false) === 1
        && finance_quality_gate_profile_exit_code('release', true, true, true, false) === 0,
    'database probe failure blocks staging without changing release policy'
);
$check(
    finance_quality_gate_profile_exit_code('parallel', true, true, true, true, false) === 0
        && finance_quality_gate_profile_exit_code('release', true, true, true, true, false) === 1
        && finance_quality_gate_profile_exit_code('staging', true, true, true, true, false) === 1,
    'browser/printer runtime capability is nonblocking only in parallel profile'
);
$check(
    finance_quality_gate_profile_exit_code('parallel', true, true, true, true, true, false) === 0
        && finance_quality_gate_profile_exit_code('release', true, true, true, true, true, false) === 1
        && finance_quality_gate_profile_exit_code('staging', true, true, true, true, true, false) === 1,
    'A4 release preflight is nonblocking only in parallel profile'
);
$check(
    finance_quality_gate_profile_exit_code('parallel', true, true, true, true, true, true, false) === 0
        && finance_quality_gate_profile_exit_code('release', true, true, true, true, true, true, false) === 1
        && finance_quality_gate_profile_exit_code('staging', true, true, true, true, true, true, false) === 1,
    'offline dependency scan is blocking only in release and staging profiles'
);
$check(
    finance_quality_gate_profile_exit_code('parallel', true, true, true, true, true, true, true, false) === 0
        && finance_quality_gate_profile_exit_code('release', true, true, true, true, true, true, true, false) === 1
        && finance_quality_gate_profile_exit_code('staging', true, true, true, true, true, true, true, false) === 1,
    'static analysis is skipped/nonblocking in parallel and blocking in release/staging'
);
$tail = finance_quality_gate_tail(implode("\n", range(1, 20)), 4, 12000);
$check($tail === "17\n18\n19\n20", 'failure output is bounded to the configured tail');

$phpCommand = finance_quality_gate_resolve_command(['file' => 'probe.php'], __FILE__);
$check(
    $phpCommand['ok'] && $phpCommand['command'][0] === PHP_BINARY,
    '.php resolves deterministically to PHP_BINARY'
);
$pythonCommand = finance_quality_gate_resolve_command(
    ['file' => 'probe.py', 'runtime' => '/usr/bin/python3'],
    __DIR__ . '/printer_agent_trust_smoke.py'
);
$check(
    $pythonCommand['ok']
        && $pythonCommand['command'][0] === '/usr/bin/python3'
        && $pythonCommand['command'][0] !== PHP_BINARY,
    '.py with explicit runtime cannot regress to PHP_BINARY'
);
$missingRuntime = finance_quality_gate_resolve_command(
    ['file' => 'probe.py', 'runtime' => '/definitely-missing/finance-a4-python'],
    __DIR__ . '/printer_agent_trust_smoke.py'
);
$check(
    !$missingRuntime['ok'] && strpos($missingRuntime['error'], 'missing or not executable') !== false,
    'missing explicit runtime fails as an environment error'
);
$extensionPython = finance_quality_gate_resolve_command(
    ['file' => 'probe.py'],
    __DIR__ . '/printer_agent_trust_smoke.py'
);
$check(
    !$extensionPython['ok'] || $extensionPython['command'][0] !== PHP_BINARY,
    'extension-derived .py runtime can never execute through PHP_BINARY'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' finance quality-gate contract check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' finance quality-gate contract checks passed.' . PHP_EOL;
