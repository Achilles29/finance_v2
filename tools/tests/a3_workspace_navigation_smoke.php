<?php

declare(strict_types=1);

/**
 * Source-only regression for A3 workspace navigation.
 *
 * Linked pages must use normal links with aria-current, while Bootstrap tab
 * widgets remain only for content panes that do not navigate away. No database
 * connection, HTTP request, or application writer is used here.
 */

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $contents = @file_get_contents($root . '/' . $path);
    return is_string($contents) ? $contents : '';
};

$shellCss = $read('assets/css/theme-custom.css');
$partial = $read('application/views/layout/_workspace_tabs.php');
$financeTabs = $read('application/views/finance/_tabs.php');
$purchaseReport = $read('application/views/purchase/report_index.php');
$attendanceDaily = $read('application/views/attendance/daily.php');
$attendancePending = $read('application/views/attendance/pending_requests.php');
$cashAdvances = $read('application/views/payroll/cash_advances.php');
$payrollPeriods = $read('application/views/payroll/payroll_periods.php');
$salaryDisbursements = $read('application/views/payroll/salary_disbursements.php');
$bonusWorkspace = $read('application/views/payroll/bonus_workspace.php');
$accessAudit = $read('application/views/users/access_audit.php');
$assetDepreciation = $read('application/views/assets/depreciation.php');
$assetRecon = $read('application/views/assets/recon_index.php');
$assetNav = $read('application/views/assets/_nav.php');
$loyaltyTabs = $read('application/views/loyalty/_tabs.php');
$extraTabs = $read('application/views/master/_extra_tabs.php');
$poSrTabs = $read('application/views/purchase/_po_sr_tabs.php');

$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$check(
    $shellCss !== '' && $partial !== '' && $financeTabs !== '' && $purchaseReport !== ''
    && $attendanceDaily !== '' && $attendancePending !== '' && $cashAdvances !== ''
    && $payrollPeriods !== '' && $salaryDisbursements !== '' && $bonusWorkspace !== ''
    && $accessAudit !== '' && $assetDepreciation !== '' && $assetRecon !== ''
    && $assetNav !== '' && $loyaltyTabs !== '' && $extraTabs !== '' && $poSrTabs !== '',
    'all A3 workspace navigation sources are readable'
);
$check(
    strpos($shellCss, '.finance-workspace-tabs') !== false
    && strpos($shellCss, '.finance-workspace-tabs__list') !== false
    && strpos($shellCss, 'overflow-x: auto') !== false
    && strpos($shellCss, '.finance-workspace-tabs__link:focus-visible') !== false,
    'global workspace shell supports responsive horizontal navigation and keyboard focus'
);
$check(
    strpos($partial, 'aria-current="page"') !== false
    && strpos($partial, 'role="list"') !== false
    && strpos($partial, '$workspaceTabEscape($workspaceTabUrl)') !== false
    && strpos($partial, "function_exists('html_escape')") !== false
    && strpos($partial, 'Bootstrap') !== false,
    'shared workspace partial escapes URLs and keeps link navigation distinct from in-page tab widgets'
);
$check(
    strpos($financeTabs, "'layout/_workspace_tabs'") !== false
    && strpos($financeTabs, "'label' => 'Tutup Periode'") !== false
    && strpos($financeTabs, "'label' => 'Target Keuangan'") !== false,
    'finance reports share one labelled and active-aware navigation strip'
);
$check(
    strpos($purchaseReport, "'layout/_workspace_tabs'") !== false
    && strpos($purchaseReport, "'label' => 'Ringkasan'") !== false
    && strpos($purchaseReport, "'label' => 'Matrix'") !== false
    && strpos($purchaseReport, 'nav nav-pills gap-2 mb-3') === false,
    'purchase report view switch uses the responsive linked workspace pattern'
);
$check(
    strpos($attendanceDaily, "'layout/_workspace_tabs'") !== false
    && strpos($attendancePending, "'layout/_workspace_tabs'") !== false
    && strpos($attendanceDaily, 'aria-label="Daftar stok') === false,
    'attendance recap and approval status filters have their own labelled workspace navigation'
);
$check(
    strpos($cashAdvances, "'layout/_workspace_tabs'") !== false
    && strpos($payrollPeriods, "'layout/_workspace_tabs'") !== false
    && strpos($salaryDisbursements, "'layout/_workspace_tabs'") !== false
    && strpos($bonusWorkspace, "'layout/_workspace_tabs'") !== false,
    'payroll workflow and bonus workspace use one navigation primitive without changing their in-page tabs'
);
$check(
    strpos($accessAudit, 'views/layout/_workspace_tabs.php') !== false
    && strpos($accessAudit, "'baseline' => 'Baseline Paket'") !== false
    && strpos($assetDepreciation, "'layout/_workspace_tabs'") !== false
    && strpos($assetRecon, "'layout/_workspace_tabs'") !== false,
    'access audit and asset drill-downs use active-aware linked navigation'
);
$check(
    strpos($assetNav, 'asset-module-nav-list') !== false
    && strpos($assetNav, 'aria-current="page"') !== false
    && strpos($assetNav, 'asset-section-tabs') === false,
    'asset module navigation has no stale local section-tab CSS after migration'
);
$check(
    strpos($loyaltyTabs, 'loyalty-tab-list') !== false
    && strpos($loyaltyTabs, 'aria-current="page"') !== false
    && strpos($extraTabs, 'extra-workspace-tabs__list') !== false
    && strpos($extraTabs, 'aria-current="page"') !== false,
    'loyalty and master-extra navigation remain responsive and expose the active page'
);
$check(
    strpos($poSrTabs, 'po-sr-nav-list') !== false
    && strpos($poSrTabs, 'aria-current="page"') !== false
    && strpos($poSrTabs, 'html_escape((string)$tab[\'url\'])') !== false,
    'purchase and store-request navigation remains escaped, responsive, and active-aware'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A3 workspace navigation check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' A3 workspace navigation checks passed.' . PHP_EOL;
