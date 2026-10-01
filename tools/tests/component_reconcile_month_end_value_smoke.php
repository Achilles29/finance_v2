<?php
declare(strict_types=1);
// Synthetic valuation selection only; no database or inventory writes.
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__, 2) . '/application/');
date_default_timezone_set('Asia/Jakarta');
class CI_Model {}
require APPPATH . 'models/Production_model.php';
$model = new Production_model();
$method = new ReflectionMethod($model, 'component_reconcile_uses_monthly_value');
$method->setAccessible(true);
$lastMonthEnd = date('Y-m-d', strtotime(date('Y-m-01') . ' -1 day'));
$lastMonthMiddle = substr($lastMonthEnd, 0, 7) . '-15';
$futureMonthEnd = date('Y-m-t', strtotime(date('Y-m-01') . ' +1 month'));
$checks = [
    [date('Y-m-d'), 100.0, 100.0, true],
    [$lastMonthEnd, 340.0, 340.0, true],
    [$lastMonthEnd, 0.0, 0.0, true],
    [$lastMonthEnd, 274.9984, 274.9984, true],
    [$lastMonthEnd, 340.0, 339.0, false],
    [$lastMonthMiddle, 340.0, 340.0, false],
    [$futureMonthEnd, 340.0, 340.0, false],
    ['2024-02-29', 10.0, 10.0, true],
    ['2024-02-28', 10.0, 10.0, false],
];
foreach ($checks as [$date, $monthly, $projected, $expected]) {
    if ($method->invoke($model, $date, $monthly, $projected) !== $expected) {
        throw new RuntimeException('Unexpected valuation source for ' . $date);
    }
}
echo 'PASS: ' . count($checks) . " month-end valuation checks; no stock writes.\n";
