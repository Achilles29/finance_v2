<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Report_workspace.php';

/** Same recognition basis as Pos_report_model::financial_profit_loss_basis,
 * without expensive inventory/HPP joins. No payment joins that multiply sales. */
class Monthly_finance_sales
{
    private static function rows($db, string $sql, array $binds): array
    {
        $q=$db->query($sql,$binds);
        if (!$q) { throw new RuntimeException('Sumber omzet POS tidak dapat dibaca.'); }
        return $q->result_array();
    }

    private static function saleSql(): string
    {
        // Refund reduces live order lines; paid_total retains the original bill.
        $bill='CASE WHEN COALESCE(rf.amount,0)>0 AND o.paid_total>0 THEN o.paid_total ELSE o.grand_total END';
        return "SELECT CONCAT('SALE-',o.id) row_key, DATE(o.paid_at) event_date, o.id order_id,
            o.order_no, o.order_no document_no, 'SALE' event_type, ($bill) billing,
            o.tax_amount tax, 0 refund, ($bill)-o.tax_amount value
            FROM pos_order o LEFT JOIN (SELECT order_id,SUM(refund_amount) amount FROM pos_refund
                WHERE refund_status='POSTED' GROUP BY order_id) rf ON rf.order_id=o.id
            WHERE o.paid_at>=? AND o.paid_at<? AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')";
    }

    private static function refundSql(): string
    {
        return "SELECT CONCAT('REFUND-',r.id) row_key,DATE(r.refunded_at) event_date,o.id order_id,
            o.order_no,r.refund_no document_no,'REFUND' event_type,0 billing,0 tax,r.refund_amount refund,-r.refund_amount value
            FROM pos_refund r JOIN pos_order o ON o.id=r.order_id
            WHERE r.refund_status='POSTED' AND r.refunded_at>=? AND r.refunded_at<?
            AND o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')";
    }

    public static function read($db, array $f): array
    {
        $empty=['billing'=>0,'tax'=>0,'refund'=>0,'value'=>0,'orders'=>0];
        $result=['available'=>false,'reason'=>'','daily'=>[],'totals'=>$empty,'compare'=>$empty,'rows'=>[],
            'pager'=>Report_workspace::page([],$f)];
        if ($f['currency']!=='IDR') { $result['reason']='Omzet POS memakai IDR; tidak dikonversi ke mata uang rekening lain.'; return $result; }
        if ($f['account_id'] || $f['category']!=='' || $f['direction']!=='' || $f['q']!=='' || $f['flow']!=='') {
            $result['reason']='Omzet disembunyikan saat filter kas aktif. Satu nota bisa dibayar lewat beberapa rekening; omzet penuh tidak boleh dibebankan ke satu rekening/kategori. Reset filter kas untuk melihat omzet.';
            return $result;
        }
        if (!$db->table_exists('pos_order') || !$db->table_exists('pos_refund')) {
            $result['reason']='Sumber penjualan POS belum tersedia. Angka tidak diganti nol.';
            return $result;
        }
        $result['available']=true;
        $from=$f['month_from'].'-01';
        $to=min(date('Y-m-d'),Report_workspace::month($f['month_to'])[1]);
        if ($from>$to) { return $result; }
        $end=date('Y-m-d',strtotime($to.' +1 day'));
        $sql=self::saleSql().' UNION ALL '.self::refundSql();
        $rows=self::rows($db,"SELECT event_date,SUM(billing) billing,SUM(tax) tax,SUM(refund) refund,SUM(value) value,
            SUM(CASE WHEN event_type='SALE' THEN 1 ELSE 0 END) orders FROM ($sql) s GROUP BY event_date ORDER BY event_date",[$from,$end,$from,$end]);
        $compareEnd=Report_workspace::month($f['compare'])[1];
        if ($f['month']===date('Y-m')) { $compareEnd=$f['compare'].'-'.sprintf('%02d',min((int)date('d'),(int)substr($compareEnd,8,2))); }
        foreach ($rows as $row) {
            foreach (['billing','tax','refund','value'] as $key) { $row[$key]=Report_workspace::cents($row[$key]); }
            $row['orders']=(int)$row['orders'];
            $result['daily'][$row['event_date']]=$row;
            $month=substr($row['event_date'],0,7);
            if ($month===$f['month'] && ($f['day']==='' || $f['day']===$row['event_date'])) {
                foreach ($empty as $key=>$_) { $result['totals'][$key]+=$row[$key]; }
            }
            if ($month===$f['compare'] && $row['event_date']<=$compareEnd) {
                foreach ($empty as $key=>$_) { $result['compare'][$key]+=$row[$key]; }
            }
        }
        if ($f['view']==='sales') {
            $from=$f['day']?:$f['from'];
            $to=min($f['day']?:$f['to'],date('Y-m-d'));
            if ($from>$to) { return $result; }
            $end=date('Y-m-d',strtotime($to.' +1 day'));
            $detail=self::rows($db,"SELECT * FROM ($sql) s ORDER BY event_date DESC,row_key DESC LIMIT 50001",[$from,$end,$from,$end]);
            if (count($detail)>50000) { throw new InvalidArgumentException('Rincian omzet melebihi 50.000 baris. Pilih tanggal rincian; data tidak dipotong.'); }
            foreach ($detail as &$row) { $row['value_cents']=Report_workspace::cents($row['value']); }
            unset($row);
            $result['rows']=$detail;
            $result['pager']=Report_workspace::page($detail,$f);
        }
        return $result;
    }

    /** Mirrors the POS summary's order cohort, including refunds on any date.
     * Kept separate from the finance recognition-date basis above. */
    public static function comparison($db, array $f): array
    {
        $rows=self::rows($db,"SELECT COUNT(*) orders,COALESCE(SUM(o.paid_total),0) received,
            COALESCE(SUM(CASE WHEN COALESCE(rf.amount,0)>0 AND o.paid_total>0 THEN o.paid_total ELSE o.grand_total END),0) billing,
            COALESCE(SUM(rf.amount),0) refund,COALESCE(SUM(o.tax_amount),0) tax,
            SUM(CASE WHEN o.paid_at IS NULL OR o.status='PAID_PARTIAL' THEN 1 ELSE 0 END) unsettled
            FROM pos_order o LEFT JOIN (SELECT order_id,SUM(refund_amount) amount FROM pos_refund
                WHERE refund_status='POSTED' GROUP BY order_id) rf ON rf.order_id=o.id
            WHERE DATE(COALESCE(o.paid_at,o.confirmed_at,o.ordered_at)) BETWEEN ? AND ?
              AND o.status NOT IN ('DRAFT','PENDING','VOID')",[$f['day']?:$f['from'],min($f['day']?:$f['to'],date('Y-m-d'))]);
        $row=$rows[0];
        foreach(['received','billing','refund','tax'] as $key){$row[$key]=Report_workspace::cents($row[$key]);}
        $row['net']=$row['billing']-$row['refund'];
        $from=$f['day']?:$f['from'];$to=min($f['day']?:$f['to'],date('Y-m-d'));
        $row['cash_anomalies']=self::rows($db,"SELECT x.order_id,o.order_no,o.paid_total,x.cash_value,x.mutations,
            x.cash_value-o.paid_total difference FROM (
            SELECT p.order_id,SUM(CASE WHEN m.mutation_type='IN' THEN m.amount ELSE -m.amount END) cash_value,COUNT(*) mutations
            FROM fin_account_mutation_log m JOIN fin_company_account a ON a.id=m.account_id AND a.currency_code='IDR'
            LEFT JOIN fin_account_mutation_log orig ON orig.id=m.reversal_of_mutation_id
            JOIN pos_payment_line l ON COALESCE(orig.ref_table,m.ref_table)='pos_payment_line' AND l.id=COALESCE(orig.ref_id,m.ref_id)
            JOIN pos_payment p ON p.id=l.payment_id AND p.payment_type='FINAL'
            WHERE m.ref_module='POS' AND m.mutation_date BETWEEN ? AND ? GROUP BY p.order_id
            ) x JOIN pos_order o ON o.id=x.order_id
            WHERE o.paid_at>=? AND o.paid_at<? AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')
              AND ABS(x.cash_value-o.paid_total)>0.01 ORDER BY ABS(x.cash_value-o.paid_total) DESC LIMIT 100",
            [$from,$to,$from,date('Y-m-d',strtotime($to.' +1 day'))]);
        return $row;
    }
}
