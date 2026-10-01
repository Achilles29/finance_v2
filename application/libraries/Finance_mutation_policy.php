<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Reporting classification only: never changes account balances or guesses from notes. */
class Finance_mutation_policy
{
    public static function categories(): array
    {
        return [
            'OTHER_INCOME' => ['label' => 'Pendapatan lain-lain', 'direction' => 'IN', 'effect' => 'income'],
            'OPERATING_EXPENSE' => ['label' => 'Biaya operasional lain', 'direction' => 'OUT', 'effect' => 'expense'],
            'PROMO_EXPENSE' => ['label' => 'Promo ditanggung usaha', 'direction' => 'OUT', 'effect' => 'expense'],
            'PLATFORM_FEE' => ['label' => 'Komisi / biaya platform', 'direction' => 'OUT', 'effect' => 'expense'],
            'CASH_SURPLUS' => ['label' => 'Selisih kas lebih terverifikasi', 'direction' => 'IN', 'effect' => 'income'],
            'CASH_SHORTAGE' => ['label' => 'Selisih kas kurang terverifikasi', 'direction' => 'OUT', 'effect' => 'expense'],
            'OWNER_CAPITAL' => ['label' => 'Setoran modal pemilik', 'direction' => 'IN', 'effect' => 'balance'],
            'OWNER_DRAWING' => ['label' => 'Prive / penarikan pemilik', 'direction' => 'OUT', 'effect' => 'balance'],
            'BALANCE_CORRECTION' => ['label' => 'Koreksi saldo saja (bukan pendapatan/biaya)', 'direction' => 'BOTH', 'effect' => 'balance'],
        ];
    }

    public static function manual_module(string $module): bool
    {
        return in_array(strtoupper($module), ['FINANCE', 'FINANCE_RECON', 'REVENUE_RECON'], true);
    }

    public static function valid(string $category, string $direction): bool
    {
        $option = self::categories()[$category] ?? null;
        return $option !== null && in_array($direction, ['IN', 'OUT'], true)
            && ($option['direction'] === 'BOTH' || $option['direction'] === $direction);
    }

    public static function label(?string $category): string
    {
        return self::categories()[$category ?? '']['label'] ?? 'Belum diklasifikasikan';
    }

    public static function assert_open_period($db, string $date): void
    {
        if (!$db->table_exists('fin_period_close')) {
            return;
        }
        $query = $db->query('SELECT id, status FROM fin_period_close WHERE period_start <= ? AND period_end >= ? ORDER BY id FOR UPDATE', [$date, $date]);
        if ($query === false) {
            throw new RuntimeException('Status tutup periode tidak dapat diperiksa.');
        }
        foreach ($query->result_array() as $period) {
            if (strtoupper((string)$period['status']) === 'CLOSED') {
                throw new RuntimeException('Periode keuangan sudah CLOSED; penyesuaian ditolak.');
            }
        }
    }

    /** Fixed SQL fragments, never accepts request values as SQL. */
    public static function expressions(bool $hasCategory): array
    {
        $module = "COALESCE(ref_module, '')";
        $category = $hasCategory ? "COALESCE(report_category, '')" : "''";
        $manual = "$module IN ('FINANCE','FINANCE_RECON','REVENUE_RECON')";
        $income = "$manual AND mutation_type = 'IN' AND $category IN ('OTHER_INCOME','CASH_SURPLUS')";
        $expense = "$manual AND mutation_type = 'OUT' AND $category IN ('OPERATING_EXPENSE','PROMO_EXPENSE','PLATFORM_FEE','CASH_SHORTAGE')";
        $balance = "$manual AND (($category = 'OWNER_CAPITAL' AND mutation_type = 'IN') OR ($category = 'OWNER_DRAWING' AND mutation_type = 'OUT') OR $category = 'BALANCE_CORRECTION')";
        $unclassified = "$module NOT IN ('POS','PURCHASE','FINANCE_TRANSFER','FINANCE_PAYABLE','FINANCE_RECEIVABLE','PAYROLL') AND NOT (($income) OR ($expense) OR ($balance))";
        $sum = static function (string $condition): string {
            return "COALESCE(SUM(CASE WHEN $condition THEN amount ELSE 0 END), 0)";
        };
        return [
            'sales_total' => $sum("$module = 'POS' AND mutation_type = 'IN'"),
            'refund_total' => $sum("$module = 'POS' AND mutation_type = 'OUT' AND COALESCE(ref_table, '') = 'pos_refund'"),
            'purchase_total' => $sum("$module = 'PURCHASE' AND mutation_type = 'OUT'"),
            'other_income_total' => $sum($income),
            'other_expense_total' => $sum($expense),
            'promo_total' => $sum("$expense AND $category = 'PROMO_EXPENSE'"),
            'platform_fee_total' => $sum("$expense AND $category = 'PLATFORM_FEE'"),
            // Keep historical outflows in the provisional estimate, with an explicit warning.
            'unclassified_in_total' => $sum("($unclassified) AND mutation_type = 'IN'"),
            'unclassified_out_total' => $sum("($unclassified) AND mutation_type = 'OUT'"),
            'unclassified_count' => "COALESCE(SUM(CASE WHEN $unclassified THEN 1 ELSE 0 END), 0)",
            'balance_only_in_total' => $sum("($balance) AND mutation_type = 'IN'"),
            'balance_only_out_total' => $sum("($balance) AND mutation_type = 'OUT'"),
            'other_pos_out_total' => $sum("$module = 'POS' AND mutation_type = 'OUT' AND COALESCE(ref_table, '') <> 'pos_refund'"),
        ];
    }

    public static function totals(array $row): array
    {
        foreach (self::expressions(false) as $key => $_) {
            $row[$key] = round((float)($row[$key] ?? 0), 2);
        }
        $row['expense_total'] = round($row['purchase_total'] + $row['other_expense_total']
            + $row['unclassified_out_total'] + $row['other_pos_out_total'], 2);
        $row['net_revenue'] = round($row['sales_total'] - $row['refund_total'], 2);
        $row['gross_profit'] = round($row['net_revenue'] + $row['other_income_total'] - $row['expense_total'], 2);
        return $row;
    }
}
