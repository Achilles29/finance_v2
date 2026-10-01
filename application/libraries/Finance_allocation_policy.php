<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Exact cents and read-only projections shared by allocation writers and forecasts. */
class Finance_allocation_policy
{
    public static function ready($db): bool
    {
        foreach (['fin_receipt_distribution','fin_receipt_allocation','fin_plan_allocation','fin_bank_statement_row'] as $table) {
            if (!$db->table_exists($table)) return false;
        }
        return true;
    }

    public static function cents($value, bool $zero = false): int
    {
        if (!is_scalar($value) || !preg_match('/\A(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', (string)$value, $m)) {
            throw new RuntimeException('Nominal wajib angka biasa, maksimal dua desimal.');
        }
        $cents = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
        if (!$zero && $cents <= 0) throw new RuntimeException('Nominal harus lebih dari nol.');
        return $cents;
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function allocations(string $json, int $available): array
    {
        $items = json_decode($json, true, 5, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items) || count($items)>25) throw new RuntimeException('Maksimal 25 pembagian per transfer.');
        $result=[]; $sum=0;
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['settlement_id'], $item['amount']) || !ctype_digit((string)$item['settlement_id'])) throw new RuntimeException('Rekap tujuan tidak valid.');
            $id=(int)$item['settlement_id'];
            if ($id<=0 || isset($result[$id])) throw new RuntimeException('Rekap tujuan kosong atau dipilih dua kali.');
            $amount=self::cents($item['amount']); $sum+=$amount;
            if ($sum>$available) throw new RuntimeException('Total pembagian melebihi nominal transfer.');
            $result[$id]=self::decimal($amount);
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    public static function receipt_total($db, int $caseId): float
    {
        $rows=Finance_settlement_control::rows($db,"SELECT r.amount FROM fin_settlement_receipt r
            WHERE r.settlement_id=? AND r.status='ACTIVE' AND NOT EXISTS(SELECT 1 FROM fin_receipt_distribution d WHERE d.receipt_id=r.id)
            UNION ALL SELECT a.amount FROM fin_receipt_allocation a JOIN fin_settlement_receipt r ON r.id=a.receipt_id
            WHERE a.settlement_id=? AND a.active_key IS NOT NULL AND r.status='ACTIVE'",[$caseId,$caseId]);
        $total=0; foreach ($rows as $row) $total+=self::cents((string)$row['amount']);
        return $total/100;
    }

    public static function used_plan_cents($db, int $mutationId): int
    {
        $legacy=Finance_settlement_control::rows($db,'SELECT m.amount FROM fin_cash_plan_realization r JOIN fin_account_mutation_log m ON m.id=r.mutation_id WHERE r.active_mutation_id=?',[$mutationId]);
        $parts=Finance_settlement_control::rows($db,'SELECT amount FROM fin_plan_allocation WHERE mutation_id=? AND active_key IS NOT NULL',[$mutationId]);
        $used=0; foreach(array_merge($legacy,$parts) as $row) $used+=self::cents((string)$row['amount']);
        return $used;
    }
}
