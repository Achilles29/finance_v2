<?php

declare(strict_types=1);

// DB-free regression contract: account balances are a posting-order chain,
// while users can still filter and inspect the original business date.
$root = dirname(__DIR__, 2);
$model = file_get_contents($root . '/application/models/Purchase_model.php');
$view = file_get_contents($root . '/application/views/purchase/finance_mutation_index.php');
$controller = file_get_contents($root . '/application/controllers/Purchase.php');
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

$check(is_string($model) && is_string($view) && is_string($controller), 'account mutation sources are readable');
$check(
    strpos($model, "->select('m.ref_module, m.ref_table, m.ref_id, m.ref_no, m.notes, m.created_at, a.account_code, a.account_name')") !== false
        && strpos($model, "->order_by('m.id', 'DESC')") !== false
        && strpos($model, "->order_by('m.mutation_date', 'DESC')") === false,
    'ledger list retains posting timestamp and orders the balance chain by append-only posting ID'
);
$check(
    strpos($controller, "->input->get('date_from'") !== false
        && strpos($controller, "->input->get('date_to'") !== false
        && strpos($controller, 'list_account_mutations($accountId, $dateFrom, $dateTo') !== false,
    'controller preserves business-date filters independently from posting order'
);
$check(
    strpos($view, 'Tanggal Bisnis Dari') !== false
        && strpos($view, 'Tanggal Bisnis Sampai') !== false
        && strpos($view, 'Urutan riwayat mengikuti <strong>waktu posting</strong>') !== false
        && strpos($view, 'label <strong>Backdate</strong>') !== false,
    'history UI explains business date, posting order, and backdate semantics'
);
$check(
    strpos($view, '$isBackdated') !== false
        && strpos($view, 'data-label="Diposting"') !== false
        && strpos($view, 'Backdate</span>') !== false
        && strpos($view, 'colspan="11"') !== false,
    'history rows expose the posting timestamp and a safe backdate marker on desktop and mobile'
);
$check(
    strpos($model, "mutation_date berada pada periode keuangan CLOSED") !== false
        && strpos($model, "if (\$mutationDate > date('Y-m-d'))") !== false,
    'mutation writers continue to reject future dates and dates inside a closed finance period'
);
$check(
    strpos($model, 'function get_account_mutation_as_of_snapshot') !== false
        && strpos($model, "->where('mutation_date <=', \$normalizedDate)") !== false
        && strpos($model, "CASE WHEN mutation_type = 'IN' THEN amount ELSE -amount END") !== false
        && strpos($model, '$businessBalance = round($openingBalance + $businessNetAmount, 2);') !== false,
    'as-of business balance is read-only, calculated from ledger opening plus signed business-date mutations'
);
$check(
    strpos($model, '$ledgerMatchesLive = abs($expectedLiveBalance - $liveBalance) < 0.01') !== false
        && strpos($view, 'Saldo bisnis per') !== false
        && strpos($view, 'Snapshot ini selalu memakai seluruh jurnal rekening') !== false
        && strpos($view, 'jangan melakukan rebuild otomatis') !== false,
    'selected account exposes a separate as-of snapshot and warns instead of rebuilding a mismatched ledger'
);
$check(
    strpos($controller, 'get_account_mutation_as_of_snapshot($accountId, $dateTo)') !== false
        && strpos($view, 'Sebelum Diposting') !== false
        && strpos($view, 'Sesudah Diposting') !== false,
    'controller supplies the business-date cut-off and the list labels posting-time balances explicitly'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A2 account mutation history check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' A2 account mutation history checks passed.' . PHP_EOL;
