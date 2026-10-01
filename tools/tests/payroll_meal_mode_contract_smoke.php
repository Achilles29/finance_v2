<?php

declare(strict_types=1);

// DB-free policy contract. Meal rate is earned per eligible attendance day;
// MONTHLY settles it through payroll, CUSTOM settles it through the separate
// meal-disbursement workflow.
$root = dirname(__DIR__, 2);
$files = [
    'attendance' => $root . '/application/models/Attendance_model.php',
    'preview' => $root . '/application/models/Payroll_preview_model.php',
    'my_portal' => $root . '/application/models/My_portal_model.php',
    'payroll' => $root . '/application/models/Payroll_model.php',
    'settings' => $root . '/application/views/attendance/settings.php',
    'meal_batch' => $root . '/application/views/payroll/meal_disbursements.php',
    'slip' => $root . '/application/views/payroll/salary_slip.php',
];
$source = [];
foreach ($files as $key => $path) {
    $source[$key] = file_get_contents($path);
}

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

$check(
    !in_array(false, $source, true),
    'meal-policy source files are readable'
);
$check(
    strpos($source['attendance'], 'Both modes earn the same daily meal entitlement.') !== false
        && strpos($source['attendance'], 'if ($isPresentish && $isCheckedIn)') !== false
        && strpos($source['attendance'], '($mealMode === \'CUSTOM\') ? ($grossAmount - $mealAmount) : $grossAmount') !== false,
    'attendance earns a daily meal entitlement in both modes and excludes only CUSTOM from salary net'
);
$check(
    strpos($source['preview'], 'if ($isPresentish && $checkinTs > 0)') !== false
        && strpos($source['preview'], '$phGetsMealAllowance') !== false
        && strpos($source['my_portal'], '$mealEst   = $phGetsMeal ? $mealRate : 0.0;') !== false
        && strpos($source['my_portal'], '($mealMode === \'CUSTOM\') ? ($grossAmount - $mealEst) : $grossAmount') !== false,
    'preview and employee portal use the same monthly/custom settlement rule, including eligible PH'
);
$check(
    strpos($source['payroll'], "Snapshot mode uang makan belum tersedia") !== false
        && strpos($source['payroll'], "COALESCE(ad.meal_mode_snapshot, 'MONTHLY') = 'CUSTOM'") !== false
        && strpos($source['payroll'], "->or_where('ad.attendance_status', 'HOLIDAY')") !== false
        && strpos($source['payroll'], '$mealMonthlySelect') !== false
        && strpos($source['payroll'], '$mealCustomSelect') !== false,
    'custom disbursement fails closed without a mode snapshot and never selects MONTHLY attendance'
);
$check(
    strpos($source['payroll'], "'MEAL_MONTHLY'") !== false
        && strpos($source['payroll'], "'MEAL_CUSTOM'") !== false
        && strpos($source['payroll'], "'MEAL_CUSTOM_SETTLEMENT'") !== false,
    'payroll result lines explicitly show meal inside payroll, custom entitlement, and custom settlement'
);
$check(
    strpos($source['my_portal'], "COALESCE(ad.meal_mode_snapshot, 'MONTHLY') = 'CUSTOM'") !== false
        && strpos($source['attendance'], 'apply_custom_meal_calendar_mode_filter') !== false
        && strpos($source['meal_batch'], 'Hanya untuk mode uang makan <strong>Custom</strong>') !== false,
    'employee meal ledger, calendar, and payment page are limited to separately payable CUSTOM meals'
);
$check(
    strpos($source['settings'], '<strong>Bulanan:</strong>') !== false
        && strpos($source['slip'], 'Uang Makan Bulanan (masuk payroll)') !== false
        && strpos($source['slip'], 'Sisa Uang Makan Custom') !== false
        && strpos($source['slip'], 'Total Hak Pegawai') !== false,
    'settings and salary slip explain monthly inclusion, custom payment progress, and total entitlement'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' payroll meal-mode contract check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' payroll meal-mode contract checks passed.' . PHP_EOL;
