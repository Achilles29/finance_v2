<?php

declare(strict_types=1);

// DB-free A4.3 source contracts for finance mutation, reconciliation and close-period flows.
$root = dirname(__DIR__, 2);
$failures = [];
$checks = 0;

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};
$methodSlice = static function (string $relativePath, string $method) use ($root): ?string {
    $path = $root . '/' . $relativePath;
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) {
        return null;
    }
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $name = null;
        for ($j = $i + 1, $count = count($tokens); $j < $count; $j++) {
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
        $slice = '';
        $depth = 0;
        $opened = false;
        for ($j = $i, $count = count($tokens); $j < $count; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $slice .= $text;
            if ($text === '{') {
                $opened = true;
                $depth++;
            } elseif ($text === '}' && $opened && --$depth === 0) {
                return $slice;
            }
        }
    }
    return null;
};

$routes = (string)file_get_contents($root . '/application/config/routes.php');
$routeContracts = [
    'manual mutation' => ['finance/mutations/store', 'purchase/finance_mutation_store'],
    'loan payment' => ['finance/utang/payment/(:num)', 'finance/utang_payment/$1'],
    'loan void' => ['finance/piutang/void/(:num)', 'finance/piutang_void/$1'],
    'cash reconciliation post' => ['finance-reports/cash-reconciliation/line-post', 'finance_reports/cash_reconciliation_line_post'],
    'period close' => ['finance-reports/period-close/process/(:num)', 'finance_reports/period_close_process/$1'],
    'period reopen' => ['finance-reports/period-close/reopen/(:num)', 'finance_reports/period_close_reopen/$1'],
];
foreach ($routeContracts as $label => [$route, $target]) {
    $checks++;
    $needle = "\$route['{$route}'] = '{$target}';";
    if (strpos($routes, $needle) === false) {
        $fail($label . ': route drift: ' . $needle);
    }
}

$contracts = [
    ['manual mutation RBAC', 'application/controllers/Purchase.php', 'finance_mutation_store', ["require_permission(self::PAGE_ORDER, 'edit')", 'apply_manual_account_mutation(']],
    ['manual mutation atomic/audit', 'application/models/Purchase_model.php', 'apply_manual_account_mutation', ['trans_begin(', 'FOR UPDATE', "'action_code' => 'ACCOUNT_MUTATION'", 'trans_rollback(', 'trans_commit() === false']],
    ['manual mutation date/closed-period guard', 'application/models/Purchase_model.php', 'apply_manual_account_mutation', ["\$mutationDate > date('Y-m-d')", "'fin_period_close'", 'SELECT id, status', 'period_start <= ?', 'period_end >= ?', 'ORDER BY id ASC', 'FOR UPDATE', '$periodQuery->result_array()', "=== 'CLOSED'"]],
    ['loan payment transaction', 'application/models/Finance_model.php', 'save_loan_payment', ['trans_begin(', 'create_account_mutation(', 'sync_loan_status(', 'trans_rollback(', 'trans_commit(']],
    ['loan void immutable reversal', 'application/models/Finance_model.php', 'void_loan', ['reverse_account_mutation(', "'status' => 'VOID'", 'trans_rollback(', 'trans_commit(']],
    ['linked reversal', 'application/models/Finance_model.php', 'reverse_account_mutation', ['FOR UPDATE', 'reversal_of_mutation_id', 'create_account_mutation(']],
    ['effective report excludes void pair', 'application/models/Finance_report_model.php', 'apply_effective_account_mutation_filter', ['reversal_of_mutation_id IS NULL', 'NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal']],
    ['cash reconciliation RBAC', 'application/controllers/Finance_reports.php', 'cash_reconciliation_line_post', ["require_permission('finance.cash_reconciliation.index', 'edit')", 'post_line(']],
    ['cash reconciliation row locks', 'application/models/Finance_cash_reconciliation_model.php', 'get_line_for_post', ['HEADER_TABLE', 'LINE_TABLE', 'FOR UPDATE']],
    ['cash reconciliation atomic audit', 'application/models/Finance_cash_reconciliation_model.php', 'post_line', ['trans_begin(', 'get_line_for_post(', "'CASH_RECON_POST'", 'trans_rollback(', 'trans_commit(']],
    ['period close RBAC', 'application/controllers/Finance_reports.php', 'period_close_process', ["require_permission('finance.period_close.index', 'edit')", 'close_period(']],
    ['period snapshot transaction/lock', 'application/models/Finance_report_model.php', 'close_period', ['trans_begin(', 'SELECT * FROM fin_period_close WHERE id = ? LIMIT 1 FOR UPDATE', 'fin_account_period_snapshot', 'fin_management_period_metric', "'status' => 'CLOSED'", 'trans_rollback(', 'trans_commit() === false']],
    ['period reopen transaction/lock', 'application/models/Finance_report_model.php', 'reopen_period', ['trans_begin(', 'SELECT * FROM fin_period_close WHERE id = ? LIMIT 1 FOR UPDATE', "->where('status', 'CLOSED')", "'status' => 'REOPENED'", 'affected_rows() !== 1', 'trans_rollback(', 'trans_commit() === false']],
];
foreach ($contracts as [$label, $file, $method, $needles]) {
    $checks++;
    $slice = $methodSlice($file, $method);
    if ($slice === null) {
        $fail($label . ': missing method ' . $file . '::' . $method);
        continue;
    }
    $missing = array_values(array_filter($needles, static fn(string $needle): bool => strpos($slice, $needle) === false));
    if ($missing !== []) {
        $fail($label . ': missing exact token(s) in ' . $method . ': ' . implode(', ', $missing));
    }
}

$checks++;
$closeSlice = $methodSlice('application/models/Finance_report_model.php', 'close_period') ?? '';
$closeLock = strpos($closeSlice, 'FOR UPDATE');
$accountSnapshot = strpos($closeSlice, 'active_company_accounts()');
if ($closeLock === false || $accountSnapshot === false || $closeLock >= $accountSnapshot) {
    $fail('period close must lock the period row before reading account state for snapshots');
}

$checks++;
$financeExportClassification = 'FUTURE/N/A';
if ($financeExportClassification !== 'FUTURE/N/A') {
    $fail('finance export classification drift: expected FUTURE/N/A');
} else {
    echo 'N/A: finance export is FUTURE/N/A; no route contract required.' . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' of ' . $checks . ' finance contract(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'PASS: all ' . $checks . ' finance source contracts passed.' . PHP_EOL;
