<?php
declare(strict_types=1);

/**
 * Payroll audit checker (att_daily vs pay_payroll_result vs active disbursement lines).
 *
 * Usage:
 *   php tools/payroll_audit_checker.php
 *   php tools/payroll_audit_checker.php --period-id=3
 *   php tools/payroll_audit_checker.php --period-code=2026-05
 */

$opts = getopt('', ['period-id::', 'period-code::', 'db-host::', 'db-user::', 'db-pass::', 'db-name::']);
$dbHost = isset($opts['db-host']) ? (string)$opts['db-host'] : '127.0.0.1';
$dbUser = isset($opts['db-user']) ? (string)$opts['db-user'] : 'root';
$dbPass = isset($opts['db-pass']) ? (string)$opts['db-pass'] : '';
$dbName = isset($opts['db-name']) ? (string)$opts['db-name'] : 'db_finance';
$periodIdArg = isset($opts['period-id']) ? (int)$opts['period-id'] : 0;
$periodCodeArg = isset($opts['period-code']) ? trim((string)$opts['period-code']) : '';

$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($db->connect_errno) {
    fwrite(STDERR, 'DB connection failed: ' . $db->connect_error . PHP_EOL);
    exit(1);
}
$db->set_charset('utf8mb4');

function queryOne(mysqli $db, string $sql): ?array
{
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Query failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    $row = $rs->fetch_assoc();
    return $row ?: null;
}

function queryAll(mysqli $db, string $sql): array
{
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Query failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

try {
    if ($periodIdArg > 0) {
        $period = queryOne($db, 'SELECT * FROM pay_payroll_period WHERE id=' . $periodIdArg . ' LIMIT 1');
    } elseif ($periodCodeArg !== '') {
        $period = queryOne($db, "SELECT * FROM pay_payroll_period WHERE period_code='" . $db->real_escape_string($periodCodeArg) . "' ORDER BY id DESC LIMIT 1");
    } else {
        $period = queryOne($db, 'SELECT * FROM pay_payroll_period ORDER BY id DESC LIMIT 1');
    }

    if (!$period) {
        throw new RuntimeException('Payroll period tidak ditemukan.');
    }

    $periodId = (int)($period['id'] ?? 0);
    $periodStart = (string)($period['period_start'] ?? '');
    $periodEnd = (string)($period['period_end'] ?? '');

    $rows = queryAll($db, "
        SELECT
          r.id AS payroll_result_id,
          r.employee_id,
          r.employee_code_snapshot,
          r.employee_name_snapshot,
          COALESCE(r.net_pay_raw, r.net_pay, 0) AS result_net_raw,
          COALESCE(r.net_pay, 0) AS result_net_final,
          COALESCE(att.net_attendance, 0) AS attendance_net,
          COALESCE(disb.transfer_total, 0) AS active_transfer_total
        FROM pay_payroll_result r
        LEFT JOIN (
          SELECT ad.employee_id, SUM(COALESCE(ad.daily_salary_amount,0)) AS net_attendance
          FROM att_daily ad
          WHERE ad.attendance_date >= '" . $db->real_escape_string($periodStart) . "'
            AND ad.attendance_date <= '" . $db->real_escape_string($periodEnd) . "'
            AND ad.checkout_at IS NOT NULL
          GROUP BY ad.employee_id
        ) att ON att.employee_id = r.employee_id
        LEFT JOIN (
          SELECT l.payroll_result_id, SUM(COALESCE(l.transfer_amount,0)) AS transfer_total
          FROM pay_salary_disbursement_line l
          INNER JOIN pay_salary_disbursement h
            ON h.id = l.disbursement_id
           AND h.status <> 'VOID'
          GROUP BY l.payroll_result_id
        ) disb ON disb.payroll_result_id = r.id
        WHERE r.payroll_period_id = " . $periodId . "
        ORDER BY r.employee_name_snapshot ASC
    ");

    $dupResult = queryAll($db, "
        SELECT employee_id, COUNT(*) AS duplicate_count, GROUP_CONCAT(id ORDER BY id) AS payroll_result_ids
        FROM pay_payroll_result
        WHERE payroll_period_id = " . $periodId . "
        GROUP BY employee_id
        HAVING COUNT(*) > 1
    ");

    $dupDisb = queryAll($db, "
        SELECT l.payroll_result_id, COUNT(*) AS duplicate_count, GROUP_CONCAT(CONCAT(h.disbursement_no, '#', l.id) ORDER BY l.id) AS line_refs
        FROM pay_salary_disbursement_line l
        INNER JOIN pay_salary_disbursement h ON h.id = l.disbursement_id
        WHERE h.payroll_period_id = " . $periodId . "
          AND h.status <> 'VOID'
        GROUP BY l.payroll_result_id
        HAVING COUNT(*) > 1
    ");

    $summary = [
        'result_rows' => count($rows),
        'result_net_raw_total' => 0.0,
        'result_net_final_total' => 0.0,
        'attendance_net_total' => 0.0,
        'active_disbursement_transfer_total' => 0.0,
        'raw_vs_attendance_diff_total' => 0.0,
        'transfer_vs_result_final_diff_total' => 0.0,
        'result_duplicates' => count($dupResult),
        'active_disbursement_duplicates' => count($dupDisb),
        'mismatch_rows' => 0,
    ];
    $mismatchRows = [];
    foreach ($rows as $row) {
        $resultRaw = round((float)($row['result_net_raw'] ?? 0), 2);
        $resultFinal = round((float)($row['result_net_final'] ?? 0), 2);
        $attendance = round((float)($row['attendance_net'] ?? 0), 2);
        $transfer = round((float)($row['active_transfer_total'] ?? 0), 2);
        $summary['result_net_raw_total'] += $resultRaw;
        $summary['result_net_final_total'] += $resultFinal;
        $summary['attendance_net_total'] += $attendance;
        $summary['active_disbursement_transfer_total'] += $transfer;
        $diffAtt = round($resultRaw - $attendance, 2);
        $diffTransferFinal = round($transfer - $resultFinal, 2);
        if (abs($diffAtt) > 0.009 || abs($diffTransferFinal) > 0.009) {
            $row['diff_raw_vs_attendance'] = $diffAtt;
            $row['diff_transfer_vs_final'] = $diffTransferFinal;
            $mismatchRows[] = $row;
        }
    }
    $summary['result_net_raw_total'] = round((float)$summary['result_net_raw_total'], 2);
    $summary['result_net_final_total'] = round((float)$summary['result_net_final_total'], 2);
    $summary['attendance_net_total'] = round((float)$summary['attendance_net_total'], 2);
    $summary['active_disbursement_transfer_total'] = round((float)$summary['active_disbursement_transfer_total'], 2);
    $summary['raw_vs_attendance_diff_total'] = round($summary['result_net_raw_total'] - $summary['attendance_net_total'], 2);
    $summary['transfer_vs_result_final_diff_total'] = round($summary['active_disbursement_transfer_total'] - $summary['result_net_final_total'], 2);
    $summary['mismatch_rows'] = count($mismatchRows);

    $output = [
        'generated_at' => date('c'),
        'period' => $period,
        'summary' => $summary,
        'duplicates_result' => $dupResult,
        'duplicates_disbursement' => $dupDisb,
        'mismatch_rows' => $mismatchRows,
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
