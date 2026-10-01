<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only report builder shared by scheduler and queue workers. */
class TelegramReportService
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function build(string $reportType, string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1) {
            $date = date('Y-m-d');
        }

        switch (strtoupper(trim($reportType))) {
            case 'MENU':
                return $this->menu_message();
            case 'OMZET_TODAY':
                return $this->omzet_message($date);
            case 'PURCHASE_TODAY':
                return $this->purchase_message($date);
            default:
                return 'Tipe laporan Telegram tidak dikenali.';
        }
    }

    public function menu_message(): string
    {
        return "Menu Finance Bot\n"
            . "/menu - daftar perintah\n"
            . "/omzet - ringkasan omzet hari ini\n"
            . "/belanja - ringkasan belanja hari ini";
    }

    private function omzet_message(string $date): string
    {
        if (!$this->ci->db->table_exists('pos_payment') && !$this->ci->db->table_exists('pos_refund')) {
            return 'Omzet ' . $this->date_label($date) . "\nData POS belum tersedia.";
        }

        $start = $date . ' 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($date . ' +1 day'));
        $receipt = ['gross_receipt' => 0, 'transaction_count' => 0];
        if ($this->ci->db->table_exists('pos_payment')) {
            $receipt = $this->ci->db
                ->select('COALESCE(SUM(net_amount),0) AS gross_receipt, COUNT(*) AS transaction_count', false)
                ->from('pos_payment')
                ->where('payment_status', 'PAID')
                ->where_in('payment_type', ['FINAL', 'DEPOSIT'])
                ->where('COALESCE(paid_at, created_at) >= ' . $this->ci->db->escape($start), null, false)
                ->where('COALESCE(paid_at, created_at) < ' . $this->ci->db->escape($end), null, false)
                ->get()->row_array() ?: $receipt;
        }

        $refundTotal = 0.0;
        if ($this->ci->db->table_exists('pos_refund')) {
            $refund = $this->ci->db
                ->select('COALESCE(SUM(refund_amount),0) AS total', false)
                ->from('pos_refund')
                ->where('refund_status', 'POSTED')
                ->where('refunded_at >=', $start)
                ->where('refunded_at <', $end)
                ->get()->row_array() ?: [];
            $refundTotal = (float)($refund['total'] ?? 0);
        }

        $grossReceipt = (float)($receipt['gross_receipt'] ?? 0);
        return implode("\n", [
            'Omzet ' . $this->date_label($date),
            'Gross receipt: ' . $this->money($grossReceipt),
            'Refund: ' . $this->money($refundTotal),
            'Net: ' . $this->money($grossReceipt - $refundTotal),
            'Jumlah transaksi: ' . number_format((int)($receipt['transaction_count'] ?? 0), 0, ',', '.'),
        ]);
    }

    /** Pure DB-free mirror of the receipt/refund date contract for fixtures. */
    public static function summarize_omzet_fixture(array $payments, array $refunds, string $date): array
    {
        $grossReceipt = 0.0;
        $transactionCount = 0;
        foreach ($payments as $payment) {
            $receiptDate = (string)(!empty($payment['paid_at']) ? $payment['paid_at'] : ($payment['created_at'] ?? ''));
            if (($payment['payment_status'] ?? '') !== 'PAID'
                || !in_array(($payment['payment_type'] ?? ''), ['FINAL', 'DEPOSIT'], true)
                || substr($receiptDate, 0, 10) !== $date) {
                continue;
            }
            $grossReceipt += (float)($payment['net_amount'] ?? 0);
            $transactionCount++;
        }

        $refundTotal = 0.0;
        foreach ($refunds as $refund) {
            if (($refund['refund_status'] ?? '') === 'POSTED'
                && substr((string)($refund['refunded_at'] ?? ''), 0, 10) === $date) {
                $refundTotal += (float)($refund['refund_amount'] ?? 0);
            }
        }
        return [
            'gross_receipt' => round($grossReceipt, 2),
            'refund' => round($refundTotal, 2),
            'net' => round($grossReceipt - $refundTotal, 2),
            'transaction_count' => $transactionCount,
        ];
    }

    private function purchase_message(string $date): string
    {
        if (!$this->ci->db->table_exists('pur_purchase_order')) {
            return 'Belanja ' . $this->date_label($date) . "\nData purchase order belum tersedia.";
        }

        $summary = $this->ci->db
            ->select('COUNT(*) AS po_count, COALESCE(SUM(grand_total),0) AS total', false)
            ->select("COALESCE(SUM(CASE WHEN status = 'PAID' THEN grand_total ELSE 0 END),0) AS paid_total", false)
            ->select("COALESCE(SUM(CASE WHEN status <> 'PAID' THEN grand_total ELSE 0 END),0) AS unpaid_total", false)
            ->from('pur_purchase_order')
            ->where('request_date', $date)
            ->where('status !=', 'VOID')
            ->get()->row_array() ?: [];

        $rows = $this->ci->db
            ->select('po_no, status, grand_total')
            ->from('pur_purchase_order')
            ->where('request_date', $date)
            ->where('status !=', 'VOID')
            ->order_by('grand_total', 'DESC')
            ->order_by('id', 'ASC')
            ->limit(10)
            ->get()->result_array();

        $lines = [
            'Belanja ' . $this->date_label($date),
            'Total: ' . $this->money((float)($summary['total'] ?? 0)) . ' (' . number_format((int)($summary['po_count'] ?? 0), 0, ',', '.') . ' PO)',
            'PAID: ' . $this->money((float)($summary['paid_total'] ?? 0)),
            'Belum PAID: ' . $this->money((float)($summary['unpaid_total'] ?? 0)),
        ];
        if ($rows) {
            $lines[] = '';
            $lines[] = 'PO terbesar:';
            foreach ($rows as $row) {
                $lines[] = '- ' . (string)($row['po_no'] ?? '-') . ' [' . (string)($row['status'] ?? '-') . '] ' . $this->money((float)($row['grand_total'] ?? 0));
            }
        }

        return implode("\n", $lines);
    }

    private function money(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function date_label(string $date): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : $date;
    }
}
