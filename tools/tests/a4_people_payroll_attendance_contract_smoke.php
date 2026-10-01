<?php

declare(strict_types=1);

/**
 * A4.3 DB/bootstrap/network-free source contract for People, Attendance and Payroll.
 */
$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];

function a4PeopleCheck(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    }
}

function a4PeopleSource(string $path): string
{
    $source = @file_get_contents($path);
    a4PeopleCheck(is_string($source), 'source is readable: ' . basename($path));
    return is_string($source) ? $source : '';
}

function a4PeopleMethod(string $source, string $method): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $name = '';
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                break;
            }
            if ($tokens[$j] === '(') {
                break;
            }
        }
        if ($name !== $method) {
            continue;
        }

        $out = '';
        $depth = 0;
        $started = false;
        for ($j = $i; $j < $count; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $out .= $text;
            if ($text === '{') {
                $depth++;
                $started = true;
            } elseif ($text === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    return $out;
                }
            }
        }
    }
    return '';
}

function a4PeopleContainsAll(string $haystack, array $needles, string $label): void
{
    foreach ($needles as $needle) {
        a4PeopleCheck(strpos($haystack, $needle) !== false, $label . ' contains ' . $needle);
    }
}

$attendanceController = a4PeopleSource($root . '/application/controllers/Attendance.php');
$attendanceModel = a4PeopleSource($root . '/application/models/Attendance_model.php');
$myController = a4PeopleSource($root . '/application/controllers/My.php');
$myModel = a4PeopleSource($root . '/application/models/My_portal_model.php');
$payrollController = a4PeopleSource($root . '/application/controllers/Payroll.php');
$payrollModel = a4PeopleSource($root . '/application/models/Payroll_model.php');

// Mutating endpoints must enforce both the HTTP method and server-side RBAC.
$mutationContracts = [
    [$attendanceController, 'schedule_store', "require_permission('attendance.schedules.index', 'edit')"],
    [$attendanceController, 'schedule_update', "require_permission('attendance.schedules.index', 'edit')"],
    [$attendanceController, 'schedule_delete', "require_permission('attendance.schedules.index', 'edit')"],
    [$attendanceController, 'schedule_bulk_store', "require_permission('attendance.schedules.index', 'edit')"],
    [$attendanceController, 'pending_request_action', "require_permission('attendance.pending.index', 'edit')"],
    [$attendanceController, 'pending_request_bulk_action', "require_permission('attendance.pending.index', 'edit')"],
    [$attendanceController, 'ph_assignment_save', "require_permission('attendance.ph.assignment.index', 'edit')"],
    [$attendanceController, 'ph_assignment_delete', "require_permission('attendance.ph.assignment.index', 'delete')"],
    [$attendanceController, 'ph_ledger_store', "require_permission('attendance.ph.ledger.index', 'create')"],
    [$attendanceController, 'ph_ledger_update', "require_permission('attendance.ph.ledger.index', 'edit')"],
    [$attendanceController, 'ph_ledger_delete', "require_permission('attendance.ph.ledger.index', 'delete')"],
    [$attendanceController, 'ph_ledger_sync_grants', "require_permission('attendance.ph.ledger.index', 'edit')"],
    [$payrollController, 'payroll_period_generate', "require_permission('payroll.salary_disbursement.index', 'create')"],
    [$payrollController, 'payroll_period_void', "require_permission('payroll.salary_disbursement.index', 'delete')"],
    [$payrollController, 'salary_disbursement_generate', "require_permission('payroll.salary_disbursement.index', 'create')"],
    [$payrollController, 'salary_disbursement_mark_paid', "require_permission('payroll.salary_disbursement.index', 'edit')"],
    [$payrollController, 'salary_disbursement_void', "require_permission('payroll.salary_disbursement.index', 'delete')"],
    [$payrollController, 'bonus_pool_generate', "require_permission('payroll.bonus.index', 'create')"],
    [$payrollController, 'bonus_auto_penalty_sync', "require_permission('payroll.bonus.index', 'edit')"],
    [$payrollController, 'bonus_pool_approve', "require_permission('payroll.bonus.index', 'edit')"],
    [$payrollController, 'bonus_pool_void', "require_permission('payroll.bonus.index', 'delete')"],
    [$payrollController, 'bonus_penalty_event_save', 'require_permission('],
    [$payrollController, 'bonus_penalty_event_void', "require_permission('payroll.bonus.index', 'delete')"],
];
foreach ($mutationContracts as [$source, $method, $permission]) {
    $block = a4PeopleMethod($source, $method);
    a4PeopleCheck($block !== '', 'mutation method exists: ' . $method);
    a4PeopleContainsAll($block, ["method() !== 'post'", 'show_404()', $permission], $method);
}

// Schedule semantics distinguish ordinary OFF from PH/PHB and reserve PH balance.
$scheduleContracts = [
    ['is_ph_shift_code', ["['PH', 'PHB']", 'strtoupper', 'in_array']],
    ['validate_ph_schedule_capacity', ["tx_type' => 'USE'", "order_by('tx_date', 'ASC')", "order_by('id', 'ASC')", 'validate_ph_schedule_capacity']],
    ['validate_schedule_change', ['validate_schedule_contract_coverage', 'validate_ph_schedule_capacity', 'default_work_days_per_month']],
    ['upsert_schedule_by_shift_code', ['shiftCode', 'employeeId', 'date']],
    ['bulk_save_schedule', ['trans_begin', 'trans_rollback', 'trans_commit', 'trans_status']],
];
foreach ($scheduleContracts as [$method, $needles]) {
    $block = a4PeopleMethod($attendanceModel, $method);
    a4PeopleCheck($block !== '', 'schedule/PH/OFF method exists: ' . $method);
    a4PeopleContainsAll($block, $needles, $method);
}
a4PeopleCheck(strpos($myController, "'OFF'") !== false, 'employee attendance surfaces preserve explicit OFF status');

// Attendance is employee-bound, schedule-bound, geofenced when configured, and atomic.
$attendanceContracts = [
    [$myController, 'attendance_mark', ['selected_employee_id', "method() !== 'post'", 'location_id', 'latitude', 'longitude', 'mark_attendance']],
    [$myController, 'leave_requests', ['selected_employee_id', 'create_leave_request', 'request_type', 'requested_status']],
    [$myModel, 'create_leave_request', ['employeeId', 'attendance_revision_window_rule', "status' => 'PENDING'", 'att_pending_request']],
    [$myModel, 'mark_attendance', ['employeeId', 'att_location', 'enforce_geofence', 'haversine_meter', 'trans_begin', 'trans_rollback', 'trans_commit']],
    [$attendanceModel, 'process_pending_request_action', ['actorEmployeeId', 'can_verify_level', 'trans_start', 'trans_complete', 'trans_status']],
];
foreach ($attendanceContracts as [$source, $method, $needles]) {
    $block = a4PeopleMethod($source, $method);
    a4PeopleCheck($block !== '', 'attendance request/location method exists: ' . $method);
    a4PeopleContainsAll($block, $needles, $method);
}

// PH grants are idempotent, expire after the inclusive valid-through date, and consume oldest lots first.
$phContracts = [
    ['sync_ph_expiry_ledger', ["where('tx_type', 'GRANT')", "where('expired_at <', \$asOfDate)", "order_by('tx_date', 'ASC')", "order_by('id', 'ASC')", "'tx_type' => 'EXPIRE'", "strtotime(\$expiredAt . ' +1 day')"]],
    ['sync_ph_grants_from_attendance', ['list_ph_grant_attendance_candidates', 'create_ph_grant_from_attendance_row', 'inserted', 'skipped']],
    ['sync_ph_grant_for_employee_date', ['create_ph_grant_from_attendance_row', 'employeeId', 'date']],
    ['sync_ph_use_for_employee_date', ["where('tx_type', 'USE')", "where('ref_table', 'att_daily')", 'sync_ph_expiry_ledger', 'validate_ph_schedule_capacity']],
    ['ph_validate_rows_integrity', ["'GRANT'", "'USE'", "'EXPIRE'", 'remaining']],
];
foreach ($phContracts as [$method, $needles]) {
    $block = a4PeopleMethod($attendanceModel, $method);
    a4PeopleCheck($block !== '', 'PH FIFO/expiry/grant method exists: ' . $method);
    a4PeopleContainsAll($block, $needles, $method);
}

// Payroll generation, payment and voiding preserve period/status and financial atomicity.
$payrollContracts = [
    ['generate_payroll_period_results', ['find_payroll_contract_coverage_gaps', "['PAID', 'CLOSED']", "where('status <>', 'VOID')", 'trans_start', "'status' => 'FINALIZED'", 'trans_complete', 'trans_status']],
    ['reset_payroll_period', ["where('status <>', 'VOID')", 'trans_start', "'status' => 'DRAFT'", 'trans_complete', 'trans_status']],
    ['generate_salary_disbursement', ['payroll_period_id', 'seenEmployee', "'status' => 'POSTED'", "'transfer_status' => 'PENDING'", 'trans_start', 'trans_complete', 'trans_status']],
    ['post_salary_disbursement_paid', ['FOR UPDATE', "'mutation_type' => 'OUT'", "'transfer_status' => 'PAID'", "'status' => 'PAID'", 'trans_start', 'trans_complete', 'trans_status']],
    ['void_salary_disbursement', ['FOR UPDATE', 'ensure_account_mutation_reversal_ready', 'reversal_of_mutation_id', "'status' => 'FINALIZED'", "'status' => 'VOID'", 'trans_start', 'trans_complete', 'trans_status']],
];
foreach ($payrollContracts as [$method, $needles]) {
    $block = a4PeopleMethod($payrollModel, $method);
    a4PeopleCheck($block !== '', 'payroll lifecycle method exists: ' . $method);
    a4PeopleContainsAll($block, $needles, $method);
}

// Bonus and penalty writes have lifecycle state, audit actor, and transaction boundaries.
$bonusContracts = [
    ['generate_bonus_pool_daily', ['actorUserId', 'trans_start', 'trans_complete', 'trans_status']],
    ['approve_bonus_pool_daily', ['APPROVED', 'actorUserId', 'poolId']],
    ['void_bonus_pool_daily', ['VOID', 'actorUserId', 'poolId']],
    ['save_bonus_penalty_event', ['actorUserId', 'employee_id', 'penalty_type_id']],
    ['void_bonus_penalty_event', ['actorUserId', 'VOID']],
];
foreach ($bonusContracts as [$method, $needles]) {
    $block = a4PeopleMethod($payrollModel, $method);
    a4PeopleCheck($block !== '', 'bonus/penalty method exists: ' . $method);
    a4PeopleContainsAll($block, $needles, $method);
}

// Employee-facing pages may only pivot employee identity for superadmin; slips/details bind employee ID.
$selectedEmployee = a4PeopleMethod($myController, 'selected_employee_id');
a4PeopleContainsAll($selectedEmployee, ["current_user['employee_id']", 'is_superadmin()', "input->get('employee_id'"], 'selected_employee_id privacy scope');
foreach (['payroll', 'payroll_slip', 'bonus', 'bonus_daily_detail'] as $method) {
    $block = a4PeopleMethod($myController, $method);
    a4PeopleCheck($block !== '', 'employee-private method exists: ' . $method);
    a4PeopleCheck(strpos($block, 'selected_employee_id') !== false, $method . ' is bound to selected employee scope');
}
a4PeopleContainsAll(
    a4PeopleMethod($myController, 'payroll_slip'),
    ['get_salary_disbursement_line_slip($lineId', "(int)\$employee['id']", "'context' => 'my'"],
    'employee payroll slip privacy'
);
a4PeopleContainsAll(
    a4PeopleMethod($myController, 'bonus_daily_detail'),
    ['get_my_bonus_daily_audit_detail($employeeDailyId', "(int)\$employee['id']"],
    'employee bonus audit privacy'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A4.3 People/Payroll/Attendance contract check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'A4.3 People/Payroll/Attendance contract smoke passed (' . $checks . ' checks; DB/bootstrap/network-free).' . PHP_EOL;
