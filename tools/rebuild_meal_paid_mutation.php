<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run via CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$dbConfigFile = $root . '/application/config/database.php';
if (!is_file($dbConfigFile)) {
    fwrite(STDERR, "database.php not found\n");
    exit(1);
}

if (!defined('BASEPATH')) {
    define('BASEPATH', $root . '/system/');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production');
}
require $dbConfigFile;
if (empty($db['default']) || !is_array($db['default'])) {
    fwrite(STDERR, "DB config invalid\n");
    exit(1);
}

$cfg = $db['default'];
$mysqli = new mysqli(
    (string)($cfg['hostname'] ?? '127.0.0.1'),
    (string)($cfg['username'] ?? ''),
    (string)($cfg['password'] ?? ''),
    (string)($cfg['database'] ?? '')
);
if ($mysqli->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

function nextMutationNo(mysqli $db, string $mutationDate): string
{
    $datePart = date('Ymd', strtotime($mutationDate));
    $prefix = 'MUT' . $datePart;

    $stmt = $db->prepare('SELECT mutation_no FROM fin_account_mutation_log WHERE mutation_no LIKE CONCAT(?, "%") ORDER BY mutation_no DESC LIMIT 1');
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $seq = 1;
    if (!empty($row['mutation_no'])) {
        $suffix = substr((string)$row['mutation_no'], strlen($prefix));
        if (ctype_digit($suffix)) {
            $seq = ((int)$suffix) + 1;
        }
    }

    while (true) {
        $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
        $seq++;

        $chk = $db->prepare('SELECT COUNT(*) AS c FROM fin_account_mutation_log WHERE mutation_no = ?');
        $chk->bind_param('s', $no);
        $chk->execute();
        $exists = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();

        if ($exists === 0) {
            return $no;
        }
    }
}

$summary = [
    'processed' => 0,
    'fixed' => 0,
    'skipped_has_mutation' => 0,
    'skipped_no_paid_lines' => 0,
    'skipped_invalid_account' => 0,
    'skipped_insufficient_balance' => 0,
    'errors' => 0,
    'total_posted' => 0.0,
];

$sql = <<<'SQL'
SELECT
  md.id,
  md.disbursement_no,
  md.disbursement_date,
  md.company_account_id,
  SUM(CASE WHEN l.transfer_status = 'PAID' THEN COALESCE(l.meal_amount,0) ELSE 0 END) AS paid_amount
FROM pay_meal_disbursement md
JOIN pay_meal_disbursement_line l ON l.disbursement_id = md.id
WHERE md.status = 'PAID'
  AND COALESCE(md.company_account_id, 0) > 0
GROUP BY md.id, md.disbursement_no, md.disbursement_date, md.company_account_id
ORDER BY md.id ASC
SQL;

$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, 'Query candidate failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    $summary['processed']++;

    $disbursementId = (int)($row['id'] ?? 0);
    $accountId = (int)($row['company_account_id'] ?? 0);
    $paidAmount = round((float)($row['paid_amount'] ?? 0), 2);
    $mutationDate = (string)($row['disbursement_date'] ?? date('Y-m-d'));
    $disbursementNo = (string)($row['disbursement_no'] ?? ('MEAL#' . $disbursementId));

    if ($disbursementId <= 0 || $accountId <= 0) {
        $summary['skipped_invalid_account']++;
        continue;
    }
    if ($paidAmount <= 0) {
        $summary['skipped_no_paid_lines']++;
        continue;
    }

    try {
        $mysqli->begin_transaction();

        $chk = $mysqli->prepare('SELECT COUNT(*) AS c FROM fin_account_mutation_log WHERE ref_module = "PAYROLL" AND ref_table = "pay_meal_disbursement" AND ref_id = ? AND mutation_type = "OUT"');
        $chk->bind_param('i', $disbursementId);
        $chk->execute();
        $hasMutation = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();

        if ($hasMutation > 0) {
            $summary['skipped_has_mutation']++;
            $mysqli->commit();
            continue;
        }

        $hdr = $mysqli->prepare('SELECT status, disbursement_date, disbursement_no, company_account_id FROM pay_meal_disbursement WHERE id = ? LIMIT 1 FOR UPDATE');
        $hdr->bind_param('i', $disbursementId);
        $hdr->execute();
        $header = $hdr->get_result()->fetch_assoc();
        $hdr->close();

        if (!$header || strtoupper((string)($header['status'] ?? '')) !== 'PAID') {
            $summary['skipped_no_paid_lines']++;
            $mysqli->commit();
            continue;
        }

        $mutationDate = (string)($header['disbursement_date'] ?? $mutationDate);
        $accountId = (int)($header['company_account_id'] ?? $accountId);
        if ($mutationDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $mutationDate)) {
            $mutationDate = date('Y-m-d');
        }
        $disbursementNo = (string)($header['disbursement_no'] ?? $disbursementNo);

        $sumStmt = $mysqli->prepare('SELECT SUM(CASE WHEN transfer_status = "PAID" THEN COALESCE(meal_amount,0) ELSE 0 END) AS paid_amount FROM pay_meal_disbursement_line WHERE disbursement_id = ?');
        $sumStmt->bind_param('i', $disbursementId);
        $sumStmt->execute();
        $paidAmount = round((float)($sumStmt->get_result()->fetch_assoc()['paid_amount'] ?? 0), 2);
        $sumStmt->close();

        if ($paidAmount <= 0) {
            $summary['skipped_no_paid_lines']++;
            $mysqli->commit();
            continue;
        }

        $acc = $mysqli->prepare('SELECT id, current_balance, is_active FROM fin_company_account WHERE id = ? LIMIT 1 FOR UPDATE');
        $acc->bind_param('i', $accountId);
        $acc->execute();
        $account = $acc->get_result()->fetch_assoc();
        $acc->close();

        if (!$account || (int)($account['is_active'] ?? 0) !== 1) {
            $summary['skipped_invalid_account']++;
            $mysqli->rollback();
            continue;
        }

        $before = round((float)($account['current_balance'] ?? 0), 2);
        if ($before < $paidAmount) {
            $summary['skipped_insufficient_balance']++;
            $mysqli->rollback();
            continue;
        }
        $after = round($before - $paidAmount, 2);

        $upd = $mysqli->prepare('UPDATE fin_company_account SET current_balance = ? WHERE id = ?');
        $upd->bind_param('di', $after, $accountId);
        $upd->execute();
        $upd->close();

        $mutationNo = nextMutationNo($mysqli, $mutationDate);
        $notes = 'Backfill mutasi OUT batch uang makan PAID ' . $disbursementNo;
        $ins = $mysqli->prepare('INSERT INTO fin_account_mutation_log (mutation_no, mutation_date, account_id, mutation_type, amount, balance_before, balance_after, ref_module, ref_table, ref_id, ref_no, notes, created_at) VALUES (?, ?, ?, "OUT", ?, ?, ?, "PAYROLL", "pay_meal_disbursement", ?, ?, ?, NOW())');
        $ins->bind_param('ssidddiss', $mutationNo, $mutationDate, $accountId, $paidAmount, $before, $after, $disbursementId, $disbursementNo, $notes);
        $ins->execute();
        $ins->close();

        $mysqli->commit();
        $summary['fixed']++;
        $summary['total_posted'] = round(((float)$summary['total_posted']) + $paidAmount, 2);
    } catch (Throwable $e) {
        $mysqli->rollback();
        $summary['errors']++;
        fwrite(STDERR, 'Failed disbursement_id=' . $disbursementId . ': ' . $e->getMessage() . PHP_EOL);
    }
}
$res->close();

echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
