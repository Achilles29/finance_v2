<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_report_model extends CI_Model
{
    /**
     * Normal POS cash reports must not count an original mutation and its
     * linked VOID reversal as two real cash events. Audit pages can still
     * read the raw mutation log when needed.
     */
    private function effective_account_mutation_sql_filter(string $alias = 'ml'): string
    {
        if (!$this->db->field_exists('reversal_of_mutation_id', 'fin_account_mutation_log')) {
            return '';
        }

        $alias = trim($alias);
        $prefix = $alias !== '' ? ($alias . '.') : '';
        return ' AND ' . $prefix . 'reversal_of_mutation_id IS NULL'
            . ' AND NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal'
            . ' WHERE reversal.reversal_of_mutation_id = ' . $prefix . 'id)';
    }

    private function order_customer_display_expr(string $orderAlias = 'o', string $memberAlias = 'm'): string
    {
        $memberExpr = $memberAlias !== '' ? ($memberAlias . '.member_name') : "''";
        if ($this->db->field_exists('customer_name', 'pos_order')) {
            return "COALESCE(NULLIF(TRIM({$orderAlias}.customer_name), ''), {$memberExpr})";
        }

        return $memberExpr;
    }

    public function outlet_options(): array
    {
        return $this->db->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function sales_summary_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_sales_summary($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->sales_summary_rows($filters, $limit, $offset),
            'overview' => $this->sales_summary_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function sales_detail_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_sales_detail($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->sales_detail_rows($filters, $limit, $offset),
            'overview' => $this->sales_detail_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function sales_extra_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_sales_extra($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->sales_extra_rows($filters, $limit, $offset),
            'overview' => $this->sales_extra_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    /**
     * Read-only audit for sales and COGS anomalies. It deliberately reuses
     * the same order/refund/HPP summaries as the margin reports, so audit
     * results never need to rebuild inventory or mutate POS history.
     */
    /** Management P&L: recognition dates, never live recipe costs or stock repairs. */
    public function financial_profit_loss_basis(string $from, string $to): array
    {
        $end = date('Y-m-d', strtotime($to . ' +1 day'));
        $read = function (string $sql, array $args): array {
            $q = $this->db->query($sql, $args);
            if ($q === false) throw new RuntimeException('Sumber laba-rugi tidak dapat dibaca.');
            return $q->row_array();
        };
        // Refund mutates remaining order lines but retains the original paid_total.
        // Restore original sale HPP first, then recognize refunds on their own date.
        $sales = $read("SELECT COUNT(*) order_count,
            COALESCE(SUM(CASE WHEN COALESCE(rf.amount,0)>0 AND o.paid_total>0 THEN o.paid_total ELSE o.grand_total END),0) billing,
            COALESCE(SUM(o.tax_amount),0) tax,
            COALESCE(SUM(COALESCE(hp.hpp_sale_amount,0)+COALESCE(rc.hpp_refund_reversed_amount,0)),0) sale_hpp
            FROM pos_order o LEFT JOIN " . $this->order_hpp_summary_subquery() . " hp ON hp.order_id=o.id
            LEFT JOIN " . $this->order_refund_hpp_summary_subquery() . " rc ON rc.order_id=o.id
            LEFT JOIN (SELECT order_id,SUM(refund_amount) amount FROM pos_refund WHERE refund_status='POSTED' GROUP BY order_id) rf ON rf.order_id=o.id
            WHERE o.paid_at>=? AND o.paid_at<? AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')", [$from,$end]);
        $refunds = $read("SELECT COALESCE(SUM(r.refund_amount),0) refund,
            COALESCE(SUM(COALESCE(rl.hpp,0)),0) refund_hpp FROM pos_refund r
            JOIN pos_order o ON o.id=r.order_id
            LEFT JOIN (SELECT refund_id,SUM(cost_reversed) hpp FROM pos_refund_line GROUP BY refund_id) rl ON rl.refund_id=r.id
            WHERE r.refund_status='POSTED' AND r.refunded_at>=? AND r.refunded_at<? AND o.paid_at IS NOT NULL
            AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')", [$from,$end]);
        $correction = 0.; $reversal = 0.;
        $correctionReady = $this->sales_hpp_audit_deficit_schema_ready();
        if ($correctionReady) {
            $correction = (float)$read("SELECT COALESCE(SUM(a.variance_amount),0) amount
                FROM inv_stock_deficit_cogs_adjustment a JOIN pos_order o ON o.id=a.order_id
                WHERE a.status='POSTED' AND a.recognition_date>=? AND a.recognition_date<? AND o.paid_at IS NOT NULL
                AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')", [$from,$end])['amount'];
            if ($this->db->table_exists('inv_stock_deficit_cogs_reversal')) {
                $reversal = (float)$read("SELECT COALESCE(SUM(r.variance_amount_reversed),0) amount
                    FROM inv_stock_deficit_cogs_reversal r JOIN inv_stock_deficit_cogs_adjustment a ON a.id=r.cogs_adjustment_id
                    JOIN pos_order o ON o.id=a.order_id WHERE a.status='POSTED' AND r.reversal_date>=? AND r.reversal_date<?
                    AND o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')", [$from,$end])['amount'];
            }
        }
        return $sales + $refunds + ['correction'=>$correction,'reversal'=>$reversal,'correction_ready'=>$correctionReady,
            'net_sales'=>round((float)$sales['billing']-(float)$sales['tax']-(float)$refunds['refund'],2),
            'hpp'=>round((float)$sales['sale_hpp']-(float)$refunds['refund_hpp']+$correction-$reversal,2)];
    }

    public function sales_hpp_integrity_audit(array $filters): array
    {
        $limit = max(1, min(50, (int)($filters['limit'] ?? 10)));
        // Read the selected orders once, then classify the same result set in
        // PHP. This keeps the audit responsive instead of repeating costly
        // HPP/refund aggregates for every check.
        $orderRows = $this->sales_hpp_audit_order_rows($filters);

        $definitions = [
            [
                'code' => 'REFUND_EXCEEDS_PAYMENT',
                'title' => 'Refund posted melebihi uang pembayaran',
                'severity' => 'ERROR',
                'matches' => static function (array $row): bool {
                    return (float)($row['refund_amount'] ?? 0) > (float)($row['paid_total'] ?? 0) + 0.009;
                },
                'issue_message' => 'Refund yang sudah diposting lebih besar daripada uang yang pernah diterima dari order. Ini adalah kelebihan pengembalian yang perlu ditelusuri dari dokumen refund asal.',
                'pass_message' => 'Tidak ada refund posted yang melebihi uang pembayaran pada periode ini.',
            ],
            [
                'code' => 'FINAL_HPP_NEGATIVE',
                'title' => 'HPP transaksi menjadi negatif',
                'severity' => 'ERROR',
                'matches' => static function (array $row): bool {
                    return (float)($row['hpp_final_amount'] ?? 0) < -0.009;
                },
                'issue_message' => 'HPP setelah refund dan koreksi defisit lebih kecil dari nol. Periksa pembalikan HPP refund dan koreksi HPP defisit sebelum angka margin dipakai untuk keputusan keuangan.',
                'pass_message' => 'Tidak ada HPP transaksi negatif pada periode ini.',
            ],
            [
                'code' => 'ZERO_HPP_WITH_NET_SALE',
                'title' => 'Ada penjualan tetapi HPP masih nol',
                'severity' => 'WARNING',
                'matches' => static function (array $row): bool {
                    return (float)($row['net_sales'] ?? 0) > 0.009
                        && abs((float)($row['hpp_final_amount'] ?? 0)) <= 0.009;
                },
                'issue_message' => 'Order memiliki penjualan bersih, tetapi HPP terkini masih nol. Ini dapat terjadi pada produk tanpa biaya, namun untuk produk yang memakai resep perlu dicek ke stock commit dan HPP snapshot.',
                'pass_message' => 'Tidak ada penjualan bersih dengan HPP nol pada periode ini.',
            ],
            [
                'code' => 'NEGATIVE_GROSS_PROFIT',
                'title' => 'Margin transaksi negatif',
                'severity' => 'WARNING',
                'matches' => static function (array $row): bool {
                    return (float)($row['net_sales'] ?? 0) > 0.009
                        && (float)($row['gross_profit'] ?? 0) < -0.009;
                },
                'issue_message' => 'HPP transaksi lebih besar daripada penjualan bersih. Kondisi ini belum tentu salah, tetapi harga jual, promo, refund, dan biaya snapshot perlu ditinjau sebelum laporan laba dipakai.',
                'pass_message' => 'Tidak ada margin transaksi negatif pada periode ini.',
            ],
        ];

        $checks = [];
        foreach ($definitions as $definition) {
            $matches = array_values(array_filter($orderRows, $definition['matches']));
            $checks[] = $this->sales_hpp_audit_order_check(
                (string)$definition['code'],
                (string)$definition['title'],
                (string)$definition['severity'],
                (string)$definition['issue_message'],
                (string)$definition['pass_message'],
                $matches,
                $limit
            );
        }

        if ($this->sales_hpp_audit_deficit_schema_ready()) {
            $checks[] = $this->sales_hpp_audit_deficit_link_check($filters, $limit);
        } else {
            $checks[] = [
                'code' => 'DEFICIT_HPP_SCHEMA_UNAVAILABLE',
                'title' => 'Fondasi koreksi HPP defisit belum tersedia',
                'status' => 'WARNING',
                'issue_count' => 1,
                'message' => 'Database ini belum memiliki struktur koreksi HPP defisit yang lengkap. Laporan penjualan tetap dapat dibuka, tetapi koreksi biaya setelah defisit selesai belum dapat diaudit dari halaman ini.',
                'sample_rows' => [],
                'source_type' => 'SCHEMA',
            ];
        }

        usort($checks, static function (array $left, array $right): int {
            $rank = ['ERROR' => 0, 'WARNING' => 1, 'PASS' => 2];
            $leftRank = $rank[strtoupper((string)($left['status'] ?? 'PASS'))] ?? 3;
            $rightRank = $rank[strtoupper((string)($right['status'] ?? 'PASS'))] ?? 3;
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return (int)($right['issue_count'] ?? 0) <=> (int)($left['issue_count'] ?? 0);
        });

        $summary = [
            'check_count' => count($checks),
            'error_issue_count' => 0,
            'warning_issue_count' => 0,
            'issue_count' => 0,
        ];
        foreach ($checks as $check) {
            $issueCount = max(0, (int)($check['issue_count'] ?? 0));
            $status = strtoupper((string)($check['status'] ?? 'PASS'));
            $summary['issue_count'] += $issueCount;
            if ($status === 'ERROR') {
                $summary['error_issue_count'] += $issueCount;
            } elseif ($status === 'WARNING') {
                $summary['warning_issue_count'] += $issueCount;
            }
        }

        return [
            'checks' => $checks,
            'summary' => $summary,
            'schema_ready' => $this->sales_hpp_audit_deficit_schema_ready(),
        ];
    }

    /**
     * Ringkasan margin sebuah order untuk halaman audit transaksi.
     * Nilai koreksi defisit dibaca terpisah dari HPP saat order dibuat so
     * riwayat harga saat jual tidak pernah ditulis ulang oleh laporan.
     */
    public function order_margin_audit(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $billingBeforeRefundExpression = $this->sales_order_billing_before_refund_expression();
        $netSalesExpression = $this->sales_order_net_sales_expression();
        $hppAtSaleExpression = $this->sales_order_hpp_at_sale_expression();
        $finalHppExpression = $this->sales_order_final_hpp_expression();

        $row = $this->db->select("\n                o.id AS order_id,\n                COALESCE(o.subtotal_amount, 0) + COALESCE(rf.refund_gross_amount, rf.refund_amount, 0) AS gross_sales,\n                COALESCE(o.discount_amount, 0)\n                    + COALESCE(o.promo_amount, 0)\n                    + COALESCE(o.voucher_amount, 0)\n                    + COALESCE(o.point_redeem_amount, 0)\n                    + COALESCE(o.compliment_amount, 0) AS sales_discount_amount,\n                {$billingBeforeRefundExpression} AS sales_after_discount,\n                COALESCE(rf.refund_amount, 0) AS refund_amount,\n                {$netSalesExpression} AS net_sales,\n                {$hppAtSaleExpression} AS hpp_sale_amount,\n                COALESCE(rc.hpp_refund_reversed_amount, 0) AS hpp_refund_reversed_amount,\n                COALESCE(dc.hpp_deficit_correction_amount, 0) AS hpp_deficit_correction_amount,\n                {$finalHppExpression} AS hpp_final_amount,\n                ({$netSalesExpression} - {$finalHppExpression}) AS gross_profit\n            ", false)
            ->from('pos_order o')
            ->join($this->refund_summary_subquery() . ' rf', 'rf.order_id = o.id', 'left', false)
            ->join($this->order_hpp_summary_subquery() . ' hp', 'hp.order_id = o.id', 'left', false)
            ->join($this->order_refund_hpp_summary_subquery() . ' rc', 'rc.order_id = o.id', 'left', false)
            ->join($this->order_deficit_hpp_correction_subquery() . ' dc', 'dc.order_id = o.id', 'left', false)
            ->where('o.id', $orderId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        if (empty($row)) {
            return [];
        }

        $lineRows = $this->db->select("\n                l.id AS order_line_id,\n                COALESCE(l.net_amount, 0) + COALESCE(xs.extra_amount, 0) + COALESCE(rr.refund_gross_amount, rr.refund_amount, 0) AS gross_sales,\n                COALESCE(l.cogs_amount, 0) AS product_hpp_current_amount,\n                COALESCE(xs.extra_hpp_sale_amount, 0) AS extra_hpp_current_amount,\n                COALESCE(l.cogs_amount, 0) + COALESCE(xs.extra_hpp_sale_amount, 0)\n                    + COALESCE(rr.hpp_refund_reversed_amount, 0) AS hpp_sale_amount,\n                COALESCE(rr.refund_amount, 0) AS refund_amount,\n                COALESCE(rr.hpp_refund_reversed_amount, 0) AS hpp_refund_reversed_amount,\n                COALESCE(dc.hpp_deficit_correction_amount, 0) AS hpp_deficit_correction_amount,\n                COALESCE(l.cogs_amount, 0) + COALESCE(xs.extra_hpp_sale_amount, 0)\n                    + COALESCE(dc.hpp_deficit_correction_amount, 0) AS hpp_final_amount\n            ", false)
            ->from('pos_order_line l')
            ->join($this->order_line_extra_summary_subquery() . ' xs', 'xs.order_line_id = l.id', 'left', false)
            ->join($this->order_line_refund_hpp_summary_subquery() . ' rr', 'rr.order_line_id = l.id', 'left', false)
            ->join($this->order_line_deficit_hpp_correction_subquery() . ' dc', 'dc.order_line_id = l.id', 'left', false)
            ->where('l.order_id', $orderId)
            ->get()
            ->result_array();

        $lineMap = [];
        foreach ($lineRows as $lineRow) {
            $lineMap[(int)($lineRow['order_line_id'] ?? 0)] = $lineRow;
        }

        $row['gross_sales'] = round((float)($row['gross_sales'] ?? 0), 2);
        $row['sales_discount_amount'] = round((float)($row['sales_discount_amount'] ?? 0), 2);
        $row['sales_after_discount'] = round((float)($row['sales_after_discount'] ?? 0), 2);
        $row['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
        $row['net_sales'] = round((float)($row['net_sales'] ?? 0), 2);
        $row['hpp_sale_amount'] = round((float)($row['hpp_sale_amount'] ?? 0), 2);
        $row['hpp_refund_reversed_amount'] = round((float)($row['hpp_refund_reversed_amount'] ?? 0), 2);
        $row['hpp_deficit_correction_amount'] = round((float)($row['hpp_deficit_correction_amount'] ?? 0), 2);
        $row['hpp_final_amount'] = round((float)($row['hpp_final_amount'] ?? 0), 2);
        $row['gross_profit'] = round((float)($row['gross_profit'] ?? 0), 2);
        $row['margin_percent'] = abs((float)$row['net_sales']) > 0.00001
            ? round(((float)$row['gross_profit'] / (float)$row['net_sales']) * 100, 2)
            : 0.0;
        $row['lines'] = $lineMap;

        return $row;
    }

    public function payment_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_payment_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->payment_rows($filters, $limit, $offset),
            'overview' => $this->payment_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function refund_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_refund_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->refund_rows($filters, $limit, $offset),
            'overview' => $this->refund_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function void_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_void_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->void_rows($filters, $limit, $offset),
            'overview' => $this->void_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function cashier_close_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_cashier_close_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->cashier_close_rows($filters, $limit, $offset),
            'overview' => $this->cashier_close_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function cashier_close_detail(int $shiftId, int $focusAccountId = 0): ?array
    {
        if ($shiftId <= 0 || !$this->db->table_exists('pos_shift')) {
            return null;
        }

        $hasShiftSummary = $this->db->table_exists('pos_shift_summary');
        $select = [
            'sh.id',
            'sh.shift_no',
            'sh.outlet_id',
            'o.outlet_name',
            'sh.terminal_id',
            't.terminal_name',
            'sh.cashier_open_employee_id',
            'eo.employee_name AS cashier_open_name',
            'sh.cashier_close_employee_id',
            'ec.employee_name AS cashier_close_name',
            'sh.status',
            'sh.opened_at',
            'sh.closed_at',
            'sh.opening_cash',
            'sh.expected_cash',
            'sh.actual_cash',
            'sh.variance_cash',
            'sh.notes',
            $hasShiftSummary ? 'COALESCE(ss.total_order_count, 0) AS total_order_count' : '0 AS total_order_count',
            $hasShiftSummary ? 'COALESCE(ss.total_gross_sales, 0) AS total_gross_sales' : '0 AS total_gross_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_discount, 0) AS total_discount' : '0 AS total_discount',
            $hasShiftSummary ? 'COALESCE(ss.total_promo, 0) AS total_promo' : '0 AS total_promo',
            $hasShiftSummary ? 'COALESCE(ss.total_net_sales, 0) AS total_net_sales' : '0 AS total_net_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_cash_sales, 0) AS total_cash_sales' : '0 AS total_cash_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_non_cash_sales, 0) AS total_non_cash_sales' : '0 AS total_non_cash_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_refund, 0) AS total_refund' : '0 AS total_refund',
            $hasShiftSummary ? 'COALESCE(ss.total_void, 0) AS total_void' : '0 AS total_void',
            $this->cashier_close_deposit_expr('sh.id') . ' AS total_deposit_receipts',
            $this->cashier_close_cash_deposit_expr('sh.id') . ' AS total_cash_deposit_receipts',
        ];

        $this->db->select(implode(', ', $select), false)
            ->from('pos_shift sh')
            ->join('pos_outlet o', 'o.id = sh.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = sh.terminal_id', 'left')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('org_employee ec', 'ec.id = sh.cashier_close_employee_id', 'left')
            ->where('sh.id', $shiftId)
            ->limit(1);

        if ($hasShiftSummary) {
            $this->db->join('pos_shift_summary ss', 'ss.shift_id = sh.id', 'left');
        }

        $row = $this->db->get()->row_array();
        if (!$row) {
            return null;
        }

        $snapshotMap = $this->cashier_close_snapshot_map([$shiftId]);
        $mutationMap = $this->cashier_close_mutation_map([$shiftId]);
        $accountRows = $this->merge_cashier_close_account_rows(
            $snapshotMap[$shiftId] ?? [],
            $mutationMap[$shiftId] ?? [],
            $focusAccountId
        );
        $focusRow = $this->locate_cashier_close_focus_account($accountRows, $focusAccountId, 'Brankas / rekening fokus');

        $row['total_recorded_receipts'] = round((float)($row['total_net_sales'] ?? 0) + (float)($row['total_deposit_receipts'] ?? 0), 2);
        $row['account_rows'] = $accountRows;
        $row['focus_account'] = $focusRow;
        $row['has_cash_variance'] = abs((float)($row['variance_cash'] ?? 0)) > 0.009;
        $row['has_focus_variance'] = abs((float)($focusRow['variance_net'] ?? 0)) > 0.009;

        return $row;
    }

    public function daily_sales_report(string $date, int $outletId = 0): array
    {
        $date = $this->normalize_report_date($date);
        $outletId = max(0, $outletId);

        $overview = $this->daily_sales_overview($date, $outletId);
        $payMethods = $this->daily_sales_payment_methods($date, $outletId);
        $payAccounts = $this->daily_sales_payment_accounts($date, $outletId);
        $shifts = $this->daily_sales_shifts($date, $outletId);
        $byDivision = $this->daily_sales_by_division($date, $outletId);
        $totalPurchase = $this->daily_sales_purchase_total($date);

        return [
            'date' => $date,
            'overview' => $overview,
            'pay_methods' => $payMethods,
            'pay_accounts' => $payAccounts,
            'shifts' => $shifts,
            'by_division' => $byDivision,
            'total_purchase' => $totalPurchase,
            'net_daily_sales' => round((float)($overview['net_sales'] ?? 0) - $totalPurchase, 2),
        ];
    }

    public function payment_method_report(array $filters): array
    {
        $rows = $this->payment_method_summary($filters);
        return [
            'rows' => $rows,
            'overview' => $this->payment_method_overview($filters),
        ];
    }

    public function payment_method_summary(array $filters = []): array
    {
        $receiptRows = $this->payment_method_receipt_summary_raw($filters);
        $refundRows = $this->payment_method_refund_summary_raw($filters);

        $merged = [];
        foreach ($receiptRows as $row) {
            $methodId = (int)($row['payment_method_id'] ?? 0);
            $key = $methodId > 0 ? 'id:' . $methodId : 'name:' . md5((string)($row['method_name'] ?? ''));
            $merged[$key] = [
                'payment_method_id' => $methodId,
                'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                'method_type' => (string)($row['method_type'] ?? ''),
                'line_count' => (int)($row['line_count'] ?? 0),
                'payment_count' => (int)($row['payment_count'] ?? 0),
                'total_amount' => round((float)($row['total_amount'] ?? 0), 2),
                'refund_amount' => 0.0,
                'net_amount' => round((float)($row['total_amount'] ?? 0), 2),
            ];
        }
        foreach ($refundRows as $row) {
            $methodId = (int)($row['payment_method_id'] ?? 0);
            $key = $methodId > 0 ? 'id:' . $methodId : 'name:' . md5((string)($row['method_name'] ?? ''));
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'payment_method_id' => $methodId,
                    'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                    'method_type' => (string)($row['method_type'] ?? ''),
                    'line_count' => 0,
                    'payment_count' => 0,
                    'total_amount' => 0.0,
                    'refund_amount' => 0.0,
                    'net_amount' => 0.0,
                ];
            }
            $merged[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $merged[$key]['net_amount'] = round((float)$merged[$key]['total_amount'] - (float)$merged[$key]['refund_amount'], 2);
        }

        usort($merged, static function (array $left, array $right): int {
            $amountCompare = (float)($right['net_amount'] ?? 0) <=> (float)($left['net_amount'] ?? 0);
            if ($amountCompare !== 0) {
                return $amountCompare;
            }
            return strcmp((string)($left['method_name'] ?? ''), (string)($right['method_name'] ?? ''));
        });

        return array_values($merged);
    }

    public function payment_method_overview(array $filters = []): array
    {
        $rows = $this->payment_method_summary($filters);
        $overview = [
            'line_count' => 0,
            'payment_count' => 0,
            'total_amount' => 0.0,
            'refund_amount' => 0.0,
            'net_amount' => 0.0,
        ];
        foreach ($rows as $row) {
            $overview['line_count'] += (int)($row['line_count'] ?? 0);
            $overview['payment_count'] += (int)($row['payment_count'] ?? 0);
            $overview['total_amount'] += (float)($row['total_amount'] ?? 0);
            $overview['refund_amount'] += (float)($row['refund_amount'] ?? 0);
            $overview['net_amount'] += (float)($row['net_amount'] ?? 0);
        }
        $overview['total_amount'] = round($overview['total_amount'], 2);
        $overview['refund_amount'] = round($overview['refund_amount'], 2);
        $overview['net_amount'] = round($overview['net_amount'], 2);

        return $overview;
    }

    public function payment_account_report(array $filters): array
    {
        $rows = $this->payment_account_summary($filters);
        return [
            'rows' => $rows,
            'overview' => $this->payment_account_overview($filters),
        ];
    }

    public function payment_account_summary(array $filters = []): array
    {
        $receiptRows = $this->payment_account_receipt_summary_raw($filters);
        $refundRows = $this->payment_account_refund_summary_raw($filters);

        $merged = [];
        foreach ($receiptRows as $row) {
            $accountId = (int)($row['account_id'] ?? 0);
            $key = $accountId > 0
                ? 'id:' . $accountId
                : 'name:' . md5((string)($row['bank_name'] ?? '') . '|' . (string)($row['account_name'] ?? '') . '|' . (string)($row['account_no'] ?? ''));
            $merged[$key] = [
                'account_id' => $accountId,
                'bank_name' => (string)($row['bank_name'] ?? 'Tanpa Rekening'),
                'account_name' => (string)($row['account_name'] ?? '-'),
                'account_no' => (string)($row['account_no'] ?? '-'),
                'line_count' => (int)($row['line_count'] ?? 0),
                'payment_count' => (int)($row['payment_count'] ?? 0),
                'total_amount' => round((float)($row['total_amount'] ?? 0), 2),
                'refund_amount' => 0.0,
                'net_amount' => round((float)($row['total_amount'] ?? 0), 2),
            ];
        }
        foreach ($refundRows as $row) {
            $accountId = (int)($row['account_id'] ?? 0);
            $key = $accountId > 0
                ? 'id:' . $accountId
                : 'name:' . md5((string)($row['bank_name'] ?? '') . '|' . (string)($row['account_name'] ?? '') . '|' . (string)($row['account_no'] ?? ''));
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'account_id' => $accountId,
                    'bank_name' => (string)($row['bank_name'] ?? 'Tanpa Rekening'),
                    'account_name' => (string)($row['account_name'] ?? '-'),
                    'account_no' => (string)($row['account_no'] ?? '-'),
                    'line_count' => 0,
                    'payment_count' => 0,
                    'total_amount' => 0.0,
                    'refund_amount' => 0.0,
                    'net_amount' => 0.0,
                ];
            }
            $merged[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $merged[$key]['net_amount'] = round((float)$merged[$key]['total_amount'] - (float)$merged[$key]['refund_amount'], 2);
        }

        usort($merged, static function (array $left, array $right): int {
            $amountCompare = (float)($right['net_amount'] ?? 0) <=> (float)($left['net_amount'] ?? 0);
            if ($amountCompare !== 0) {
                return $amountCompare;
            }
            $bankCompare = strcmp((string)($left['bank_name'] ?? ''), (string)($right['bank_name'] ?? ''));
            if ($bankCompare !== 0) {
                return $bankCompare;
            }
            return strcmp((string)($left['account_name'] ?? ''), (string)($right['account_name'] ?? ''));
        });

        return array_values($merged);
    }

    public function payment_account_overview(array $filters = []): array
    {
        $rows = $this->payment_account_summary($filters);
        $overview = [
            'line_count' => 0,
            'payment_count' => 0,
            'total_amount' => 0.0,
            'refund_amount' => 0.0,
            'net_amount' => 0.0,
        ];
        foreach ($rows as $row) {
            $overview['line_count'] += (int)($row['line_count'] ?? 0);
            $overview['payment_count'] += (int)($row['payment_count'] ?? 0);
            $overview['total_amount'] += (float)($row['total_amount'] ?? 0);
            $overview['refund_amount'] += (float)($row['refund_amount'] ?? 0);
            $overview['net_amount'] += (float)($row['net_amount'] ?? 0);
        }
        $overview['total_amount'] = round($overview['total_amount'], 2);
        $overview['refund_amount'] = round($overview['refund_amount'], 2);
        $overview['net_amount'] = round($overview['net_amount'], 2);

        return $overview;
    }

    public function find_payment(int $id): ?array
    {
        $row = $this->db->select('p.*, o.order_no, o.status AS order_status, o.service_type, o.order_scope, o.ordered_at, o.paid_at AS order_paid_at, po.outlet_name, m.member_no, m.member_name, e.employee_name AS cashier_name')
            ->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->where('p.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function payment_lines(int $paymentId): array
    {
        $select = 'pl.*, pm.method_name, pm.method_type';
        $db = $this->db->from('pos_payment_line pl')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');

        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $select .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $db->join('fin_company_account acc', 'acc.id = pl.company_account_id', 'left');
        } elseif ($this->db->field_exists('company_account_id', 'pos_payment_method')) {
            $select .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $db->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        }

        return $db->select($select)
            ->where('pl.payment_id', $paymentId)
            ->order_by('pl.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_refund(int $id): ?array
    {
        $row = $this->db->select('r.*, o.order_no, o.status AS order_status, o.service_type, o.order_scope, o.ordered_at, po.outlet_name, m.member_no, m.member_name, pm.method_name, acc.account_code AS company_account_code, acc.account_name AS company_account_name, e.employee_name AS refunded_by_name')
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left')
            ->where('r.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function refund_lines(int $refundId): array
    {
        return $this->db->select('rl.*, ol.line_no AS order_line_no, p.product_name, ex.extra_name, COALESCE(p.product_name, ex.extra_name) AS item_name')
            ->from('pos_refund_line rl')
            ->join('pos_order_line ol', 'ol.id = rl.order_line_id', 'left')
            ->join('mst_product p', 'p.id = rl.product_id', 'left')
            ->join('mst_extra ex', 'ex.id = rl.extra_id', 'left')
            ->where('rl.refund_id', $refundId)
            ->order_by('rl.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_void(int $id): ?array
    {
        $row = $this->db->select('v.*, po.outlet_name, m.member_no, m.member_name, e.employee_name AS actor_name')
            ->from('pos_void v')
            ->join('pos_outlet po', 'po.id = v.outlet_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left')
            ->where('v.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function void_lines(int $voidId): array
    {
        return $this->db->from('pos_void_line')
            ->where('void_id', $voidId)
            ->order_by('line_no_snapshot', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    public function void_extras(int $voidId): array
    {
        return $this->db->from('pos_void_line_extra')
            ->where('void_id', $voidId)
            ->order_by('void_line_id', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_payment_rows(int $orderId): array
    {
        $payments = $this->db->select('p.*, e.employee_name AS cashier_name')
            ->from('pos_payment p')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->where('p.order_id', $orderId)
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'ASC', false)
            ->order_by('p.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($payments)) {
            return [];
        }

        $paymentIds = array_map('intval', array_column($payments, 'id'));
        $lineSelect = 'pl.*, pm.method_name, pm.method_type';
        $lineDb = $this->db->from('pos_payment_line pl')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');
        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $lineSelect .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $lineDb->join('fin_company_account acc', 'acc.id = pl.company_account_id', 'left');
        } elseif ($this->db->field_exists('company_account_id', 'pos_payment_method')) {
            $lineSelect .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $lineDb->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        }

        $lines = $lineDb->select($lineSelect)
            ->where_in('pl.payment_id', $paymentIds)
            ->order_by('pl.payment_id', 'ASC')
            ->order_by('pl.line_no', 'ASC')
            ->get()
            ->result_array();

        $grouped = [];
        foreach ($lines as $line) {
            $grouped[(int)($line['payment_id'] ?? 0)][] = $line;
        }

        foreach ($payments as &$payment) {
            $payment['lines'] = $grouped[(int)($payment['id'] ?? 0)] ?? [];
        }
        unset($payment);

        return $payments;
    }

    public function order_refund_rows(int $orderId): array
    {
        return $this->db->select('r.*, e.employee_name AS refunded_by_name, pm.method_name, acc.account_name AS company_account_name')
            ->from('pos_refund r')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->where('r.order_id', $orderId)
            ->order_by('r.refunded_at', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_void_rows(int $orderId): array
    {
        return $this->db->select('v.*, e.employee_name AS actor_name')
            ->from('pos_void v')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left')
            ->where('v.order_id', $orderId)
            ->order_by('v.created_at', 'ASC')
            ->order_by('v.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_point_ledger_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_point_ledger')) {
            return [];
        }

        $db = $this->db->from('pos_point_ledger l');
        $select = 'l.*';
        if ($this->db->table_exists('pos_point_rule')) {
            $db->join('pos_point_rule r', 'r.id = l.rule_id', 'left');
            $select .= ', r.rule_name';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = l.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('l.order_id', $orderId)
            ->order_by('l.created_at', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_stamp_ledger_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_stamp_ledger')) {
            return [];
        }

        $db = $this->db->from('pos_stamp_ledger l');
        $select = 'l.*';
        if ($this->db->table_exists('pos_stamp_campaign')) {
            $db->join('pos_stamp_campaign c', 'c.id = l.campaign_id', 'left');
            $select .= ', c.campaign_name';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = l.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('l.order_id', $orderId)
            ->order_by('l.created_at', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_voucher_redemption_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_voucher_redemption')) {
            return [];
        }

        $db = $this->db->from('pos_voucher_redemption vr');
        $select = 'vr.*';
        if ($this->db->table_exists('pos_voucher_issue')) {
            $db->join('pos_voucher_issue vi', 'vi.id = vr.voucher_issue_id', 'left');
            $select .= ', vi.voucher_issue_no, vi.voucher_code';
            if ($this->db->table_exists('pos_voucher_campaign')) {
                $db->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left');
                $select .= ', vc.campaign_name, vc.campaign_code';
            }
        }
        if ($this->db->table_exists('pos_payment')) {
            $db->join('pos_payment p', 'p.id = vr.payment_id', 'left');
            $select .= ', p.payment_no';
        }

        return $db->select($select, false)
            ->where('vr.order_id', $orderId)
            ->order_by('vr.redeemed_at', 'ASC')
            ->order_by('vr.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_voucher_issue_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_voucher_issue') || !$this->db->field_exists('source_order_id', 'pos_voucher_issue')) {
            return [];
        }

        $db = $this->db->from('pos_voucher_issue vi');
        $select = 'vi.*';
        if ($this->db->table_exists('pos_voucher_campaign')) {
            $db->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left');
            $select .= ', vc.campaign_name, vc.campaign_code, vc.voucher_type';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = vi.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('vi.source_order_id', $orderId)
            ->order_by('vi.issued_at', 'ASC')
            ->order_by('vi.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function sales_summary_rows(array $filters, int $limit, int $offset): array
    {
        $this->sales_summary_base_query($filters);
        $customerExpr = $this->order_customer_display_expr('o', 'm');
        $billingBeforeRefundExpression = $this->sales_order_billing_before_refund_expression();
        $netSalesExpression = $this->sales_order_net_sales_expression();
        $hppAtSaleExpression = $this->sales_order_hpp_at_sale_expression();
        $finalHppExpression = $this->sales_order_final_hpp_expression();

        return $this->db->select("\n                o.id,\n                o.order_no,\n                o.status,\n                o.order_scope,\n                o.service_type,\n                o.table_no,\n                o.ordered_at,\n                o.confirmed_at,\n                o.paid_at,\n                po.outlet_name,\n                m.member_no,\n                m.member_name,\n                {$customerExpr} AS customer_display_name,\n                e.employee_name AS cashier_name,\n                COALESCE(ls.line_count, 0) AS line_count,\n                COALESCE(ls.qty_total, 0) AS qty_total,\n                COALESCE(o.subtotal_amount, 0) + COALESCE(rf.refund_gross_amount, rf.refund_amount, 0) AS subtotal_amount,\n                COALESCE(o.discount_amount, 0) AS discount_amount,\n                COALESCE(o.promo_amount, 0) AS promo_amount,\n                COALESCE(o.voucher_amount, 0) AS voucher_amount,\n                COALESCE(o.point_redeem_amount, 0) AS point_redeem_amount,\n                COALESCE(o.compliment_amount, 0) AS compliment_amount,\n                COALESCE(o.discount_amount, 0)\n                    + COALESCE(o.promo_amount, 0)\n                    + COALESCE(o.voucher_amount, 0)\n                    + COALESCE(o.point_redeem_amount, 0)\n                    + COALESCE(o.compliment_amount, 0) AS sales_discount_amount,\n                COALESCE(o.tax_amount, 0) AS tax_amount,\n                COALESCE(o.service_amount, 0) AS service_amount,\n                COALESCE(o.grand_total, 0) AS grand_total,\n                {$billingBeforeRefundExpression} AS billing_before_refund,\n                COALESCE(o.paid_total, 0) AS paid_total,\n                COALESCE(o.change_total, 0) AS change_total,\n                COALESCE(rf.refund_amount, 0) AS refund_amount,\n                COALESCE(vd.void_amount, 0) AS void_amount,\n                {$netSalesExpression} AS net_sales,\n                {$hppAtSaleExpression} AS hpp_sale_amount,\n                COALESCE(rc.hpp_refund_reversed_amount, 0) AS hpp_refund_reversed_amount,\n                COALESCE(dc.hpp_deficit_correction_amount, 0) AS hpp_deficit_correction_amount,\n                {$finalHppExpression} AS hpp_final_amount,\n                ({$netSalesExpression} - {$finalHppExpression}) AS gross_profit,\n                CASE\n                    WHEN ABS({$netSalesExpression}) > 0.00001\n                    THEN ROUND((({$netSalesExpression} - {$finalHppExpression})\n                        / {$netSalesExpression}) * 100, 2)\n                    ELSE 0\n                END AS margin_percent,\n                COALESCE(pm.method_names, '') AS payment_method_names\n            ", false)
            ->order_by('o.ordered_at', 'DESC')
            ->order_by('o.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_sales_summary(array $filters): int
    {
        $this->sales_summary_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function sales_summary_overview(array $filters): array
    {
        $this->sales_summary_base_query($filters);
        $billingBeforeRefundExpression = $this->sales_order_billing_before_refund_expression();
        $netSalesExpression = $this->sales_order_net_sales_expression();
        $hppAtSaleExpression = $this->sales_order_hpp_at_sale_expression();
        $finalHppExpression = $this->sales_order_final_hpp_expression();

        return $this->db->select("\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(ls.line_count), 0) AS line_count,\n                COALESCE(SUM(ls.qty_total), 0) AS qty_total,\n                COALESCE(SUM(COALESCE(o.subtotal_amount, 0) + COALESCE(rf.refund_gross_amount, rf.refund_amount, 0)), 0) AS gross_sales,\n                COALESCE(SUM({$billingBeforeRefundExpression}), 0) AS grand_total,\n                COALESCE(SUM(o.discount_amount), 0) AS discount_amount,\n                COALESCE(SUM(o.promo_amount), 0) AS promo_amount,\n                COALESCE(SUM(o.voucher_amount), 0) AS voucher_amount,\n                COALESCE(SUM(o.point_redeem_amount), 0) AS point_redeem_amount,\n                COALESCE(SUM(o.compliment_amount), 0) AS compliment_amount,\n                COALESCE(SUM(COALESCE(o.discount_amount, 0)\n                    + COALESCE(o.promo_amount, 0)\n                    + COALESCE(o.voucher_amount, 0)\n                    + COALESCE(o.point_redeem_amount, 0)\n                    + COALESCE(o.compliment_amount, 0)), 0) AS sales_discount_amount,\n                COALESCE(SUM(rf.refund_amount), 0) AS refund_amount,\n                COALESCE(SUM(vd.void_amount), 0) AS void_amount,\n                COALESCE(SUM(o.paid_total), 0) AS paid_total,\n                COALESCE(SUM(GREATEST(COALESCE(o.grand_total, 0) - COALESCE(o.paid_total, 0), 0)), 0) AS balance_due,\n                COALESCE(SUM({$netSalesExpression}), 0) AS net_sales,\n                COALESCE(SUM({$hppAtSaleExpression}), 0) AS hpp_sale_amount,\n                COALESCE(SUM(rc.hpp_refund_reversed_amount), 0) AS hpp_refund_reversed_amount,\n                COALESCE(SUM(dc.hpp_deficit_correction_amount), 0) AS hpp_deficit_correction_amount,\n                COALESCE(SUM({$finalHppExpression}), 0) AS hpp_final_amount,\n                COALESCE(SUM({$netSalesExpression} - {$finalHppExpression}), 0) AS gross_profit\n            ", false)
            ->get()
            ->row_array() ?: [];
    }

    private function sales_summary_base_query(array $filters): void
    {
        $this->db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join($this->order_line_summary_subquery() . ' ls', 'ls.order_id = o.id', 'left', false)
            ->join($this->refund_summary_subquery() . ' rf', 'rf.order_id = o.id', 'left', false)
            ->join($this->void_summary_subquery() . ' vd', 'vd.order_id = o.id', 'left', false)
            ->join($this->payment_method_summary_subquery() . ' pm', 'pm.order_id = o.id', 'left', false)
            ->join($this->order_hpp_summary_subquery() . ' hp', 'hp.order_id = o.id', 'left', false)
            ->join($this->order_refund_hpp_summary_subquery() . ' rc', 'rc.order_id = o.id', 'left', false)
            ->join($this->order_deficit_hpp_correction_subquery() . ' dc', 'dc.order_id = o.id', 'left', false)
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID']);

        $this->apply_order_filters($filters);
        $paymentMethodId = (int)($filters['payment_method_id'] ?? 0);
        if ($paymentMethodId > 0) {
            $this->db->where('FIND_IN_SET(' . $paymentMethodId . ', COALESCE(pm.method_ids, \'\')) > 0', null, false);
        }
    }

    private function sales_detail_rows(array $filters, int $limit, int $offset): array
    {
        $this->sales_detail_base_query($filters);
        $groupRows = $this->db->select("\n                CASE WHEN COALESCE(l.bundle_id, 0) > 0 THEN CONCAT('B:', l.bundle_id) ELSE CONCAT('P:', l.product_id) END AS group_key,\n                MAX(COALESCE(l.bundle_id, 0)) AS bundle_id,\n                MAX(b.bundle_code) AS bundle_code,\n                MAX(b.bundle_name) AS bundle_name,\n                MAX(p.id) AS product_id,\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(l.net_amount), 0) + COALESCE(SUM(xs.extra_amount), 0) AS gross_sales\n            ", false)
            ->group_by("CASE WHEN COALESCE(l.bundle_id, 0) > 0 THEN CONCAT('B:', l.bundle_id) ELSE CONCAT('P:', l.product_id) END", false)
            ->order_by('gross_sales', 'DESC', false)
            ->order_by('bundle_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        if (empty($groupRows)) {
            return [];
        }

        $bundleIds = [];
        $regularProductIds = [];
        foreach ($groupRows as $groupRow) {
            $bundleId = (int)($groupRow['bundle_id'] ?? 0);
            if ($bundleId > 0) {
                $bundleIds[] = $bundleId;
            } else {
                $regularProductIds[] = (int)($groupRow['product_id'] ?? 0);
            }
        }
        $bundleIds = array_values(array_unique(array_filter($bundleIds)));
        $regularProductIds = array_values(array_unique(array_filter($regularProductIds)));
        $rows = $this->sales_detail_component_rows($filters, $bundleIds, $regularProductIds);
        if (empty($rows)) {
            return [];
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'product_id')))));
        $refundMap = $this->sales_detail_refund_rows($filters, $productIds);
        $correctionMap = $this->sales_detail_hpp_correction_rows($filters, $productIds);
        foreach ($rows as &$row) {
            $productId = (int)($row['product_id'] ?? 0);
            $bundleId = (int)($row['bundle_id'] ?? 0);
            $itemKey = $this->sales_detail_item_key($bundleId, $productId);
            $refund = $refundMap[$itemKey] ?? ['refund_qty' => 0, 'refund_amount' => 0, 'refund_gross_amount' => 0, 'hpp_refund_reversed_amount' => 0];
            $correction = $correctionMap[$itemKey] ?? ['hpp_deficit_correction_amount' => 0];
            $refundAmount = (float)($refund['refund_amount'] ?? 0);
            $refundGrossAmount = (float)($refund['refund_gross_amount'] ?? $refundAmount);
            $hppRefund = (float)($refund['hpp_refund_reversed_amount'] ?? 0);
            // Active POS lines already contain the remaining value after a
            // refund. Add the historical refund back only for the "gross"
            // reference, then subtract it once when calculating net sales.
            $grossSales = (float)($row['gross_sales'] ?? 0) + $refundGrossAmount;
            $salesDiscount = (float)($row['sales_discount_amount'] ?? 0)
                + max(0, $refundGrossAmount - $refundAmount);
            $hppSale = (float)($row['hpp_sale_amount'] ?? 0) + $hppRefund;
            $hppCorrection = (float)($correction['hpp_deficit_correction_amount'] ?? 0);
            $row['refund_qty'] = (float)($refund['refund_qty'] ?? 0);
            $row['refund_amount'] = $refundAmount;
            $row['gross_sales'] = round($grossSales, 2);
            $row['sales_discount_amount'] = round($salesDiscount, 2);
            $row['net_sales'] = round($grossSales - $salesDiscount - $refundAmount, 2);
            $row['hpp_sale_amount'] = round($hppSale, 2);
            $row['hpp_refund_reversed_amount'] = round($hppRefund, 2);
            $row['hpp_deficit_correction_amount'] = round($hppCorrection, 2);
            $row['hpp_final_amount'] = round($hppSale - $hppRefund + $hppCorrection, 2);
            $row['gross_profit'] = round((float)$row['net_sales'] - (float)$row['hpp_final_amount'], 2);
            $row['margin_percent'] = abs((float)$row['net_sales']) > 0.00001
                ? round(((float)$row['gross_profit'] / (float)$row['net_sales']) * 100, 2)
                : 0.0;
            $row['row_type'] = $bundleId > 0 ? 'BUNDLE_ITEM' : 'PRODUCT';
        }
        unset($row);

        $childrenByGroup = [];
        foreach ($rows as $row) {
            $groupKey = $this->sales_detail_top_key((int)($row['bundle_id'] ?? 0), (int)($row['product_id'] ?? 0));
            $childrenByGroup[$groupKey][] = $row;
        }

        $result = [];
        foreach ($groupRows as $groupRow) {
            $bundleId = (int)($groupRow['bundle_id'] ?? 0);
            $productId = (int)($groupRow['product_id'] ?? 0);
            $groupKey = $this->sales_detail_top_key($bundleId, $productId);
            $children = $childrenByGroup[$groupKey] ?? [];
            if (empty($children)) {
                continue;
            }

            if ($bundleId <= 0) {
                $result[] = $children[0];
                continue;
            }

            $result[] = $this->sales_detail_bundle_row($groupRow, $children);
        }

        return $result;
    }

    private function count_sales_detail(array $filters): int
    {
        $this->sales_detail_base_query($filters);
        $row = $this->db->select("COUNT(DISTINCT CASE WHEN COALESCE(l.bundle_id, 0) > 0 THEN CONCAT('B:', l.bundle_id) ELSE CONCAT('P:', l.product_id) END) AS total", false)
            ->get()
            ->row_array();

        return (int)($row['total'] ?? 0);
    }

    private function sales_detail_component_rows(array $filters, array $bundleIds, array $regularProductIds): array
    {
        $conditions = [];
        if (!empty($bundleIds)) {
            $conditions[] = 'l.bundle_id IN (' . implode(',', array_map('intval', $bundleIds)) . ')';
        }
        if (!empty($regularProductIds)) {
            $conditions[] = '(COALESCE(l.bundle_id, 0) = 0 AND l.product_id IN (' . implode(',', array_map('intval', $regularProductIds)) . '))';
        }
        if (empty($conditions)) {
            return [];
        }

        $this->sales_detail_base_query($filters);
        return $this->db->select("\n                COALESCE(l.bundle_id, 0) AS bundle_id,\n                b.bundle_code,\n                b.bundle_name,\n                p.id AS product_id,\n                p.product_code,\n                p.product_name,\n                pd.name AS division_name,\n                pc.name AS category_name,\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(l.qty), 0) AS qty_total,\n                COALESCE(SUM(l.net_amount), 0) AS product_amount,\n                COALESCE(SUM(xs.extra_amount), 0) AS extra_amount,\n                COALESCE(SUM(l.net_amount), 0) + COALESCE(SUM(xs.extra_amount), 0) AS gross_sales,\n                COALESCE(SUM(\n                    CASE\n                        WHEN COALESCE(o.subtotal_amount, 0) > 0 THEN\n                            (COALESCE(l.net_amount, 0) + COALESCE(xs.extra_amount, 0))\n                            / o.subtotal_amount\n                            * (COALESCE(o.discount_amount, 0)\n                                + COALESCE(o.promo_amount, 0)\n                                + COALESCE(o.voucher_amount, 0)\n                                + COALESCE(o.point_redeem_amount, 0)\n                                + COALESCE(o.compliment_amount, 0))\n                        ELSE 0\n                    END\n                ), 0) AS sales_discount_amount,\n                COALESCE(SUM(l.cogs_amount), 0) + COALESCE(SUM(xs.extra_hpp_sale_amount), 0) AS hpp_sale_amount\n            ", false)
            ->where('(' . implode(' OR ', $conditions) . ')', null, false)
            ->group_by(['l.bundle_id', 'b.bundle_code', 'b.bundle_name', 'p.id', 'p.product_code', 'p.product_name', 'pd.name', 'pc.name'])
            ->order_by('b.bundle_name', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function sales_detail_top_key(int $bundleId, int $productId): string
    {
        return $bundleId > 0 ? 'B:' . $bundleId : 'P:' . $productId;
    }

    private function sales_detail_item_key(int $bundleId, int $productId): string
    {
        return $bundleId > 0 ? 'B:' . $bundleId . ':P:' . $productId : 'P:' . $productId;
    }

    private function sales_detail_bundle_row(array $groupRow, array $children): array
    {
        $bundleName = trim((string)($groupRow['bundle_name'] ?? ''));
        if ($bundleName === '') {
            $bundleName = trim((string)($groupRow['bundle_code'] ?? 'Paket bundle'));
        }
        $row = [
            'row_type' => 'BUNDLE',
            'bundle_id' => (int)($groupRow['bundle_id'] ?? 0),
            'bundle_code' => (string)($groupRow['bundle_code'] ?? ''),
            'bundle_name' => $bundleName,
            'product_id' => 0,
            'product_code' => (string)($groupRow['bundle_code'] ?? ''),
            'product_name' => $bundleName,
            'category_name' => 'Bundle',
            'division_name' => '',
            'order_count' => (int)($groupRow['order_count'] ?? 0),
            'children' => $children,
        ];
        $metrics = [
            'qty_total', 'product_amount', 'extra_amount', 'gross_sales', 'sales_discount_amount',
            'refund_qty', 'refund_amount', 'net_sales', 'hpp_sale_amount',
            'hpp_refund_reversed_amount', 'hpp_deficit_correction_amount', 'hpp_final_amount', 'gross_profit',
        ];
        foreach ($metrics as $metric) {
            $row[$metric] = 0.0;
        }
        $divisionNames = [];
        foreach ($children as $child) {
            foreach ($metrics as $metric) {
                $row[$metric] += (float)($child[$metric] ?? 0);
            }
            $divisionName = trim((string)($child['division_name'] ?? ''));
            if ($divisionName !== '') {
                $divisionNames[$divisionName] = true;
            }
        }
        foreach ($metrics as $metric) {
            $row[$metric] = round((float)$row[$metric], 2);
        }
        $row['division_name'] = count($divisionNames) === 1 ? (string)array_key_first($divisionNames) : 'Lintas divisi';
        $row['margin_percent'] = abs((float)$row['net_sales']) > 0.00001
            ? round(((float)$row['gross_profit'] / (float)$row['net_sales']) * 100, 2)
            : 0.0;

        return $row;
    }

    private function sales_detail_overview(array $filters): array
    {
        $this->sales_detail_base_query($filters);
        $overview = $this->db->select("\n                COUNT(DISTINCT p.id) AS product_count,\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(l.qty), 0) AS qty_total,\n                COALESCE(SUM(l.net_amount), 0) AS product_amount,\n                COALESCE(SUM(xs.extra_amount), 0) AS extra_amount,\n                COALESCE(SUM(l.net_amount), 0) + COALESCE(SUM(xs.extra_amount), 0) AS gross_sales,\n                COALESCE(SUM(\n                    CASE\n                        WHEN COALESCE(o.subtotal_amount, 0) > 0 THEN\n                            (COALESCE(l.net_amount, 0) + COALESCE(xs.extra_amount, 0))\n                            / o.subtotal_amount\n                            * (COALESCE(o.discount_amount, 0)\n                                + COALESCE(o.promo_amount, 0)\n                                + COALESCE(o.voucher_amount, 0)\n                                + COALESCE(o.point_redeem_amount, 0)\n                                + COALESCE(o.compliment_amount, 0))\n                        ELSE 0\n                    END\n                ), 0) AS sales_discount_amount,\n                COALESCE(SUM(l.cogs_amount), 0) + COALESCE(SUM(xs.extra_hpp_sale_amount), 0) AS hpp_sale_amount\n            ", false)
            ->get()
            ->row_array() ?: [];

        $refundOverview = $this->sales_detail_refund_overview($filters);
        $correctionOverview = $this->sales_detail_hpp_correction_overview($filters);
        $overview['refund_qty'] = (float)($refundOverview['refund_qty'] ?? 0);
        $overview['refund_amount'] = (float)($refundOverview['refund_amount'] ?? 0);
        $overview['hpp_refund_reversed_amount'] = (float)($refundOverview['hpp_refund_reversed_amount'] ?? 0);
        $overview['hpp_deficit_correction_amount'] = (float)($correctionOverview['hpp_deficit_correction_amount'] ?? 0);
        $overview['gross_sales'] = round((float)($overview['gross_sales'] ?? 0)
            + (float)($refundOverview['refund_gross_amount'] ?? $overview['refund_amount']), 2);
        $overview['sales_discount_amount'] = round((float)($overview['sales_discount_amount'] ?? 0)
            + max(0, (float)($refundOverview['refund_gross_amount'] ?? 0) - (float)$overview['refund_amount']), 2);
        $overview['hpp_sale_amount'] = round((float)($overview['hpp_sale_amount'] ?? 0) + (float)$overview['hpp_refund_reversed_amount'], 2);
        $overview['net_sales'] = round((float)($overview['gross_sales'] ?? 0)
            - (float)($overview['sales_discount_amount'] ?? 0)
            - (float)($overview['refund_amount'] ?? 0), 2);
        $overview['hpp_final_amount'] = round((float)($overview['hpp_sale_amount'] ?? 0)
            - (float)($overview['hpp_refund_reversed_amount'] ?? 0)
            + (float)($overview['hpp_deficit_correction_amount'] ?? 0), 2);
        $overview['gross_profit'] = round((float)($overview['net_sales'] ?? 0) - (float)($overview['hpp_final_amount'] ?? 0), 2);
        $overview['margin_percent'] = abs((float)$overview['net_sales']) > 0.00001
            ? round(((float)$overview['gross_profit'] / (float)$overview['net_sales']) * 100, 2)
            : 0.0;

        return $overview;
    }

    private function sales_detail_base_query(array $filters): void
    {
        $this->db->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->join($this->order_line_extra_summary_subquery() . ' xs', 'xs.order_line_id = l.id', 'left', false)
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('l.line_type', 'PRODUCT')
            ->where('l.line_status <>', 'VOID');

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'b.bundle_name', 'b.bundle_code', 'pd.name', 'pc.name', 'l.notes']);
    }

    private function sales_detail_refund_rows(array $filters, array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($productIds)) {
            return [];
        }

        $grossRefundExpression = $this->refund_line_gross_amount_expression('rl');
        $this->db->from('pos_refund_line rl')
            ->join('pos_refund r', 'r.id = rl.refund_id', 'inner')
            ->join('pos_order_line l', 'l.id = rl.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('r.refund_status', 'POSTED')
            ->where('rl.order_line_id IS NOT NULL', null, false)
            ->where_in('l.product_id', $productIds);

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'b.bundle_name', 'b.bundle_code', 'pd.name', 'pc.name']);

        $rows = $this->db->select("\n                COALESCE(l.bundle_id, 0) AS bundle_id,\n                l.product_id,\n                COALESCE(SUM(CASE WHEN rl.line_type = 'PRODUCT' THEN rl.qty_refunded ELSE 0 END), 0) AS refund_qty,\n                COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount,\n                COALESCE(SUM({$grossRefundExpression}), 0) AS refund_gross_amount,\n                COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount\n            ", false)
            ->group_by(['l.bundle_id', 'l.product_id'])
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[$this->sales_detail_item_key((int)($row['bundle_id'] ?? 0), (int)($row['product_id'] ?? 0))] = $row;
        }

        return $map;
    }

    private function sales_detail_refund_overview(array $filters): array
    {
        $grossRefundExpression = $this->refund_line_gross_amount_expression('rl');
        $this->db->from('pos_refund_line rl')
            ->join('pos_refund r', 'r.id = rl.refund_id', 'inner')
            ->join('pos_order_line l', 'l.id = rl.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('r.refund_status', 'POSTED')
            ->where('rl.order_line_id IS NOT NULL', null, false);

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'b.bundle_name', 'b.bundle_code', 'pd.name', 'pc.name']);

        return $this->db->select("\n                COALESCE(SUM(CASE WHEN rl.line_type = 'PRODUCT' THEN rl.qty_refunded ELSE 0 END), 0) AS refund_qty,\n                COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount,\n                COALESCE(SUM({$grossRefundExpression}), 0) AS refund_gross_amount,\n                COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount\n            ", false)
            ->get()
            ->row_array() ?: [];
    }

    private function sales_detail_hpp_correction_rows(array $filters, array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($productIds)
            || !$this->db->table_exists('inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('order_line_id', 'inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('variance_amount', 'inv_stock_deficit_cogs_adjustment')) {
            return [];
        }

        $reversalSubquery = '';
        $reversalAmountExpr = '0';
        if ($this->db->table_exists('inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('cogs_adjustment_id', 'inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('variance_amount_reversed', 'inv_stock_deficit_cogs_reversal')) {
            $reversalSubquery = "(\n                SELECT cogs_adjustment_id, COALESCE(SUM(variance_amount_reversed), 0) AS variance_reversed\n                FROM inv_stock_deficit_cogs_reversal\n                GROUP BY cogs_adjustment_id\n            ) rv";
            $reversalAmountExpr = 'COALESCE(rv.variance_reversed, 0)';
        }

        $this->db->from('inv_stock_deficit_cogs_adjustment a')
            ->join('pos_order_line l', 'l.id = a.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('a.status', 'POSTED')
            ->where_in('l.product_id', $productIds);
        if ($reversalSubquery !== '') {
            $this->db->join($reversalSubquery, 'rv.cogs_adjustment_id = a.id', 'left', false);
        }

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'b.bundle_name', 'b.bundle_code', 'pd.name', 'pc.name']);
        $rows = $this->db->select('COALESCE(l.bundle_id, 0) AS bundle_id, l.product_id, COALESCE(SUM(COALESCE(a.variance_amount, 0) - ' . $reversalAmountExpr . '), 0) AS hpp_deficit_correction_amount', false)
            ->group_by(['l.bundle_id', 'l.product_id'])
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[$this->sales_detail_item_key((int)($row['bundle_id'] ?? 0), (int)($row['product_id'] ?? 0))] = $row;
        }

        return $map;
    }

    private function sales_detail_hpp_correction_overview(array $filters): array
    {
        if (!$this->db->table_exists('inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('order_line_id', 'inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('variance_amount', 'inv_stock_deficit_cogs_adjustment')) {
            return ['hpp_deficit_correction_amount' => 0.0];
        }

        $reversalSubquery = '';
        $reversalAmountExpr = '0';
        if ($this->db->table_exists('inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('cogs_adjustment_id', 'inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('variance_amount_reversed', 'inv_stock_deficit_cogs_reversal')) {
            $reversalSubquery = "(\n                SELECT cogs_adjustment_id, COALESCE(SUM(variance_amount_reversed), 0) AS variance_reversed\n                FROM inv_stock_deficit_cogs_reversal\n                GROUP BY cogs_adjustment_id\n            ) rv";
            $reversalAmountExpr = 'COALESCE(rv.variance_reversed, 0)';
        }

        $this->db->from('inv_stock_deficit_cogs_adjustment a')
            ->join('pos_order_line l', 'l.id = a.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('a.status', 'POSTED');
        if ($reversalSubquery !== '') {
            $this->db->join($reversalSubquery, 'rv.cogs_adjustment_id = a.id', 'left', false);
        }

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'b.bundle_name', 'b.bundle_code', 'pd.name', 'pc.name']);
        return $this->db->select('COALESCE(SUM(COALESCE(a.variance_amount, 0) - ' . $reversalAmountExpr . '), 0) AS hpp_deficit_correction_amount', false)
            ->get()
            ->row_array() ?: ['hpp_deficit_correction_amount' => 0.0];
    }

    private function sales_extra_rows(array $filters, int $limit, int $offset): array
    {
        $this->sales_extra_base_query($filters);

        $rows = $this->db->select("\n                ex.id AS extra_id,\n                ex.extra_code,\n                ex.extra_name,\n                ex.extra_type,\n                ex.source_kind,\n                ex.selling_price,\n                ex.cost_amount,\n                COUNT(DISTINCT o.id) AS order_count,\n                COUNT(DISTINCT l.product_id) AS product_count,\n                COALESCE(SUM(x.qty), 0) AS qty_total,\n                COALESCE(SUM(x.net_amount), 0) AS gross_sales,\n                COALESCE(SUM(\n                    CASE\n                        WHEN COALESCE(o.subtotal_amount, 0) > 0 THEN\n                            COALESCE(x.net_amount, 0) / o.subtotal_amount\n                            * (COALESCE(o.discount_amount, 0)\n                                + COALESCE(o.promo_amount, 0)\n                                + COALESCE(o.voucher_amount, 0)\n                                + COALESCE(o.point_redeem_amount, 0)\n                                + COALESCE(o.compliment_amount, 0))\n                        ELSE 0\n                    END\n                ), 0) AS sales_discount_amount,\n                COALESCE(SUM(COALESCE(x.qty, 0) * COALESCE(x.cost_amount_snapshot, 0)), 0) AS hpp_sale_amount\n            ", false)
            ->group_by(['ex.id', 'ex.extra_code', 'ex.extra_name', 'ex.extra_type', 'ex.source_kind', 'ex.selling_price', 'ex.cost_amount'])
            ->order_by('gross_sales', 'DESC', false)
            ->order_by('ex.extra_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [];
        }

        $refundMap = $this->sales_extra_refund_rows($filters, array_map('intval', array_column($rows, 'extra_id')));
        foreach ($rows as &$row) {
            $extraId = (int)($row['extra_id'] ?? 0);
            $refund = $refundMap[$extraId] ?? ['refund_qty' => 0, 'refund_amount' => 0, 'refund_gross_amount' => 0, 'hpp_refund_reversed_amount' => 0];
            $refundAmount = (float)($refund['refund_amount'] ?? 0);
            $refundGrossAmount = (float)($refund['refund_gross_amount'] ?? $refundAmount);
            $hppRefund = (float)($refund['hpp_refund_reversed_amount'] ?? 0);
            $grossSales = (float)($row['gross_sales'] ?? 0) + $refundGrossAmount;
            $salesDiscount = (float)($row['sales_discount_amount'] ?? 0)
                + max(0, $refundGrossAmount - $refundAmount);
            $hppSale = (float)($row['hpp_sale_amount'] ?? 0) + $hppRefund;
            $row['refund_qty'] = (float)($refund['refund_qty'] ?? 0);
            $row['refund_amount'] = $refundAmount;
            $row['gross_sales'] = round($grossSales, 2);
            $row['sales_discount_amount'] = round($salesDiscount, 2);
            $row['net_sales'] = round($grossSales - $salesDiscount - $refundAmount, 2);
            $row['hpp_sale_amount'] = round($hppSale, 2);
            $row['hpp_refund_reversed_amount'] = round($hppRefund, 2);
            $row['hpp_deficit_correction_amount'] = 0.0;
            $row['hpp_final_amount'] = round($hppSale - $hppRefund, 2);
            $row['gross_profit'] = round((float)$row['net_sales'] - (float)$row['hpp_final_amount'], 2);
            $row['margin_percent'] = abs((float)$row['net_sales']) > 0.00001
                ? round(((float)$row['gross_profit'] / (float)$row['net_sales']) * 100, 2)
                : 0.0;
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            $netCompare = (float)($right['net_sales'] ?? 0) <=> (float)($left['net_sales'] ?? 0);
            if ($netCompare !== 0) {
                return $netCompare;
            }

            return strcasecmp((string)($left['extra_name'] ?? ''), (string)($right['extra_name'] ?? ''));
        });

        return $rows;
    }

    private function count_sales_extra(array $filters): int
    {
        $this->sales_extra_base_query($filters);
        $row = $this->db->select('COUNT(DISTINCT ex.id) AS total', false)
            ->get()
            ->row_array();

        return (int)($row['total'] ?? 0);
    }

    private function sales_extra_overview(array $filters): array
    {
        $this->sales_extra_base_query($filters);
        $overview = $this->db->select("\n                COUNT(DISTINCT ex.id) AS extra_count,\n                COUNT(DISTINCT o.id) AS order_count,\n                COUNT(DISTINCT l.product_id) AS product_count,\n                COALESCE(SUM(x.qty), 0) AS qty_total,\n                COALESCE(SUM(x.net_amount), 0) AS gross_sales,\n                COALESCE(SUM(\n                    CASE\n                        WHEN COALESCE(o.subtotal_amount, 0) > 0 THEN\n                            COALESCE(x.net_amount, 0) / o.subtotal_amount\n                            * (COALESCE(o.discount_amount, 0)\n                                + COALESCE(o.promo_amount, 0)\n                                + COALESCE(o.voucher_amount, 0)\n                                + COALESCE(o.point_redeem_amount, 0)\n                                + COALESCE(o.compliment_amount, 0))\n                        ELSE 0\n                    END\n                ), 0) AS sales_discount_amount,\n                COALESCE(SUM(COALESCE(x.qty, 0) * COALESCE(x.cost_amount_snapshot, 0)), 0) AS hpp_sale_amount\n            ", false)
            ->get()
            ->row_array() ?: [];

        $refundOverview = $this->sales_extra_refund_overview($filters);
        $overview['refund_qty'] = (float)($refundOverview['refund_qty'] ?? 0);
        $overview['refund_amount'] = (float)($refundOverview['refund_amount'] ?? 0);
        $overview['hpp_refund_reversed_amount'] = (float)($refundOverview['hpp_refund_reversed_amount'] ?? 0);
        $overview['hpp_deficit_correction_amount'] = 0.0;
        $overview['gross_sales'] = round((float)($overview['gross_sales'] ?? 0)
            + (float)($refundOverview['refund_gross_amount'] ?? $overview['refund_amount']), 2);
        $overview['sales_discount_amount'] = round((float)($overview['sales_discount_amount'] ?? 0)
            + max(0, (float)($refundOverview['refund_gross_amount'] ?? 0) - (float)$overview['refund_amount']), 2);
        $overview['hpp_sale_amount'] = round((float)($overview['hpp_sale_amount'] ?? 0) + (float)$overview['hpp_refund_reversed_amount'], 2);
        $overview['net_sales'] = round((float)($overview['gross_sales'] ?? 0)
            - (float)($overview['sales_discount_amount'] ?? 0)
            - (float)($overview['refund_amount'] ?? 0), 2);
        $overview['hpp_final_amount'] = round((float)($overview['hpp_sale_amount'] ?? 0)
            - (float)($overview['hpp_refund_reversed_amount'] ?? 0), 2);
        $overview['gross_profit'] = round((float)($overview['net_sales'] ?? 0) - (float)($overview['hpp_final_amount'] ?? 0), 2);
        $overview['margin_percent'] = abs((float)$overview['net_sales']) > 0.00001
            ? round(((float)$overview['gross_profit'] / (float)$overview['net_sales']) * 100, 2)
            : 0.0;

        return $overview;
    }

    private function sales_extra_base_query(array $filters): void
    {
        $this->db->from('pos_order_line_extra x')
            ->join('pos_order_line l', 'l.id = x.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = x.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_extra ex', 'ex.id = x.extra_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('l.line_status <>', 'VOID');

        $this->apply_order_filters($filters, 'o', ['ex.extra_name', 'ex.extra_code', 'ex.extra_type', 'ex.source_kind', 'p.product_name', 'p.product_code', 'pd.name', 'pc.name', 'x.notes']);
    }

    private function sales_extra_refund_rows(array $filters, array $extraIds): array
    {
        $extraIds = array_values(array_filter(array_map('intval', $extraIds), static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($extraIds)) {
            return [];
        }

        $grossRefundExpression = $this->refund_line_gross_amount_expression('rl');
        $this->sales_extra_refund_base_query($filters);
        $rows = $this->db->select("rl.extra_id, COALESCE(SUM(rl.qty_refunded), 0) AS refund_qty, COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount, COALESCE(SUM({$grossRefundExpression}), 0) AS refund_gross_amount, COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount", false)
            ->where_in('rl.extra_id', $extraIds)
            ->group_by('rl.extra_id')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['extra_id'] ?? 0)] = $row;
        }

        return $map;
    }

    private function sales_extra_refund_overview(array $filters): array
    {
        $grossRefundExpression = $this->refund_line_gross_amount_expression('rl');
        $this->sales_extra_refund_base_query($filters);
        return $this->db->select("COALESCE(SUM(rl.qty_refunded), 0) AS refund_qty, COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount, COALESCE(SUM({$grossRefundExpression}), 0) AS refund_gross_amount, COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount", false)
            ->get()
            ->row_array() ?: [];
    }

    private function sales_extra_refund_base_query(array $filters): void
    {
        $this->db->from('pos_refund_line rl')
            ->join('pos_refund r', 'r.id = rl.refund_id', 'inner')
            ->join('pos_order_line_extra x', 'x.id = rl.order_extra_line_id', 'left')
            ->join('pos_order_line l', 'l.id = COALESCE(rl.order_line_id, x.order_line_id)', 'left', false)
            ->join('pos_order o', 'o.id = COALESCE(x.order_id, l.order_id)', 'inner', false)
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_extra ex', 'ex.id = rl.extra_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('r.refund_status', 'POSTED')
            ->where('rl.line_type', 'EXTRA');

        $this->apply_order_filters($filters, 'o', ['ex.extra_name', 'ex.extra_code', 'ex.extra_type', 'ex.source_kind', 'p.product_name', 'p.product_code', 'pd.name', 'pc.name']);
    }

    private function payment_rows(array $filters, int $limit, int $offset): array
    {
        $this->payment_base_query($filters);
        return $this->db->select("p.*, o.order_no, o.status AS order_status, o.service_type, po.outlet_name, m.member_no, m.member_name, e.employee_name AS cashier_name, COALESCE(pl.amount_total, 0) AS amount_total, COALESCE(pl.method_names, '') AS method_names", false)
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'DESC', false)
            ->order_by('p.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_payment_rows(array $filters): int
    {
        $this->payment_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function payment_overview(array $filters): array
    {
        $this->payment_base_query($filters);
        return $this->db->select('COUNT(DISTINCT p.id) AS payment_count, COALESCE(SUM(p.net_amount), 0) AS net_amount, COALESCE(SUM(p.discount_amount), 0) AS discount_amount, COALESCE(SUM(p.promo_amount), 0) AS promo_amount, COALESCE(SUM(p.deposit_applied_amount), 0) AS deposit_applied_amount, COALESCE(SUM(p.change_amount), 0) AS change_amount', false)
            ->get()
            ->row_array() ?: [];
    }

    private function payment_base_query(array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'FINAL')));

        $this->db->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->join($this->payment_line_summary_subquery() . ' pl', 'pl.payment_id = p.id', 'left', false);

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'p.payment_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(COALESCE(p.paid_at, p.created_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('p.payment_status', $status);
        }
        if ($paymentType !== '' && $paymentType !== 'ALL') {
            $this->db->where('p.payment_type', $paymentType);
        }
    }

    private function payment_method_receipt_summary_raw(array $filters = []): array
    {
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'ALL')));
        if ($paymentType === 'REFUND') {
            return [];
        }

        $status = strtoupper(trim((string)($filters['status'] ?? 'PAID')));
        $this->db->from('pos_payment_line pl')
            ->join('pos_payment p', 'p.id = pl.payment_id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->where('pl.status', 'PAID');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'pm.method_name',
            'p.payment_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(COALESCE(p.paid_at, p.created_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('p.payment_status', $status);
        }
        if ($paymentType !== '' && $paymentType !== 'ALL') {
            $this->db->where('p.payment_type', $paymentType);
        }

        return $this->db->select('pm.id AS payment_method_id, COALESCE(pm.method_name, "Tanpa Metode") AS method_name, COALESCE(pm.method_type, "") AS method_type, COUNT(pl.id) AS line_count, COUNT(DISTINCT p.id) AS payment_count, COALESCE(SUM(pl.amount), 0) AS total_amount', false)
            ->group_by(['pm.id', 'pm.method_name', 'pm.method_type'])
            ->order_by('total_amount', 'DESC', false)
            ->get()
            ->result_array();
    }

    private function payment_method_refund_summary_raw(array $filters = []): array
    {
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'ALL')));
        if ($paymentType !== 'ALL' && $paymentType !== 'REFUND') {
            return [];
        }

        $this->db->from('pos_refund r')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->where('r.refund_status', 'POSTED');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'pm.method_name',
            'r.refund_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
            'r.reference_no',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(r.refunded_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        return $this->db->select('pm.id AS payment_method_id, COALESCE(pm.method_name, "Tanpa Metode") AS method_name, COALESCE(pm.method_type, "") AS method_type, COALESCE(SUM(r.refund_amount), 0) AS refund_amount', false)
            ->group_by(['pm.id', 'pm.method_name', 'pm.method_type'])
            ->get()
            ->result_array();
    }

    private function payment_account_receipt_summary_raw(array $filters = []): array
    {
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'ALL')));
        if ($paymentType === 'REFUND') {
            return [];
        }

        $accountExpr = $this->payment_company_account_expr('pl', 'pm');
        if ($accountExpr === 'NULL' || !$this->db->table_exists('fin_company_account')) {
            return [];
        }

        $status = strtoupper(trim((string)($filters['status'] ?? 'PAID')));
        $this->db->from('pos_payment_line pl')
            ->join('pos_payment p', 'p.id = pl.payment_id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = ' . $accountExpr, 'left', false)
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->where('pl.status', 'PAID');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'acc.bank_name',
            'acc.account_name',
            'acc.account_no',
            'p.payment_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(COALESCE(p.paid_at, p.created_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('p.payment_status', $status);
        }
        if ($paymentType !== '' && $paymentType !== 'ALL') {
            $this->db->where('p.payment_type', $paymentType);
        }

        return $this->db->select("COALESCE(acc.id, 0) AS account_id, COALESCE(acc.bank_name, 'Tanpa Rekening') AS bank_name, COALESCE(acc.account_name, '-') AS account_name, COALESCE(acc.account_no, '-') AS account_no, COUNT(pl.id) AS line_count, COUNT(DISTINCT p.id) AS payment_count, COALESCE(SUM(pl.amount), 0) AS total_amount", false)
            ->group_by(['acc.id', 'acc.bank_name', 'acc.account_name', 'acc.account_no'])
            ->order_by('total_amount', 'DESC', false)
            ->get()
            ->result_array();
    }

    private function payment_account_refund_summary_raw(array $filters = []): array
    {
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'ALL')));
        if ($paymentType !== 'ALL' && $paymentType !== 'REFUND') {
            return [];
        }

        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }

        $this->db->from('pos_refund r')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->where('r.refund_status', 'POSTED');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'acc.bank_name',
            'acc.account_name',
            'acc.account_no',
            'r.refund_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
            'r.reference_no',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(r.refunded_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        return $this->db->select("COALESCE(acc.id, 0) AS account_id, COALESCE(acc.bank_name, 'Tanpa Rekening') AS bank_name, COALESCE(acc.account_name, '-') AS account_name, COALESCE(acc.account_no, '-') AS account_no, COALESCE(SUM(r.refund_amount), 0) AS refund_amount", false)
            ->group_by(['acc.id', 'acc.bank_name', 'acc.account_name', 'acc.account_no'])
            ->get()
            ->result_array();
    }

    private function refund_rows(array $filters, int $limit, int $offset): array
    {
        $this->refund_base_query($filters);
        return $this->db->select('r.*, o.order_no, o.status AS order_status, o.service_type, po.outlet_name, m.member_no, m.member_name, pm.method_name, e.employee_name AS refunded_by_name', false)
            ->order_by('r.refunded_at', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_refund_rows(array $filters): int
    {
        $this->refund_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function refund_overview(array $filters): array
    {
        $this->refund_base_query($filters);
        return $this->db->select("COUNT(DISTINCT r.id) AS refund_count, COALESCE(SUM(r.refund_amount), 0) AS refund_amount, COALESCE(SUM(CASE WHEN r.return_to_stock = 1 THEN r.refund_amount ELSE 0 END), 0) AS return_to_stock_amount, COALESCE(SUM(CASE WHEN r.processed_state = 'PROCESSED' THEN r.refund_amount ELSE 0 END), 0) AS processed_amount", false)
            ->get()
            ->row_array() ?: [];
    }

    private function refund_base_query(array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));

        $this->db->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'r.refund_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'r.reference_no',
            'r.reason',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(r.refunded_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('r.refund_status', $status);
        }
    }

    private function void_rows(array $filters, int $limit, int $offset): array
    {
        $this->void_base_query($filters);
        return $this->db->select('v.*, po.outlet_name, m.member_no, m.member_name, e.employee_name AS actor_name', false)
            ->order_by('v.created_at', 'DESC')
            ->order_by('v.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_void_rows(array $filters): int
    {
        $this->void_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function void_overview(array $filters): array
    {
        $this->void_base_query($filters);
        return $this->db->select("COUNT(DISTINCT v.id) AS void_count, COALESCE(SUM(v.amount_void), 0) AS amount_void, COALESCE(SUM(v.total_qty_void), 0) AS total_qty_void, COALESCE(SUM(CASE WHEN v.void_scope = 'FULL' THEN 1 ELSE 0 END), 0) AS full_void_count, COALESCE(SUM(CASE WHEN v.processed_state = 'PROCESSED' THEN v.amount_void ELSE 0 END), 0) AS processed_amount", false)
            ->get()
            ->row_array() ?: [];
    }

    private function void_base_query(array $filters): void
    {
        $scope = strtoupper(trim((string)($filters['void_scope'] ?? 'ALL')));

        $this->db->from('pos_void v')
            ->join('pos_outlet po', 'po.id = v.outlet_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'v.void_no',
            'v.order_no_snapshot',
            'v.member_name_snapshot',
            'm.member_no',
            'm.member_name',
            'v.reason',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), 'v');
        $this->apply_date_range_filter('DATE(v.created_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($scope !== '' && $scope !== 'ALL') {
            $this->db->where('v.void_scope', $scope);
        }
    }

    private function sales_hpp_audit_order_check(
        string $code,
        string $title,
        string $severity,
        string $issueMessage,
        string $passMessage,
        array $matchingRows,
        int $limit
    ): array {
        $issueCount = count($matchingRows);

        return [
            'code' => $code,
            'title' => $title,
            'status' => $issueCount > 0 ? $severity : 'PASS',
            'issue_count' => $issueCount,
            'message' => $issueCount > 0 ? $issueMessage : $passMessage,
            'sample_rows' => $issueCount > 0 ? array_slice($matchingRows, 0, $limit) : [],
            'source_type' => 'ORDER',
        ];
    }

    private function sales_hpp_audit_order_rows(array $filters): array
    {
        $netSalesExpression = $this->sales_hpp_audit_net_sales_expression();
        $finalHppExpression = $this->sales_hpp_audit_final_hpp_expression();
        $billingBeforeRefundExpression = $this->sales_order_billing_before_refund_expression();
        $hppAtSaleExpression = $this->sales_order_hpp_at_sale_expression();
        $orderDateExpression = 'DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at))';

        $this->sales_hpp_audit_order_base_query($filters);

        return $this->db->select("\n                o.id AS order_id,\n                o.order_no,\n                {$orderDateExpression} AS order_date,\n                o.status,\n                COALESCE(po.outlet_name, '-') AS outlet_name,\n                COALESCE(o.grand_total, 0) AS grand_total,\n                COALESCE(o.paid_total, 0) AS paid_total,\n                {$billingBeforeRefundExpression} AS billing_before_refund,\n                COALESCE(rf.refund_amount, 0) AS refund_amount,\n                {$netSalesExpression} AS net_sales,\n                {$hppAtSaleExpression} AS hpp_sale_amount,\n                COALESCE(rc.hpp_refund_reversed_amount, 0) AS hpp_refund_reversed_amount,\n                COALESCE(dc.hpp_deficit_correction_amount, 0) AS hpp_deficit_correction_amount,\n                {$finalHppExpression} AS hpp_final_amount,\n                ({$netSalesExpression} - {$finalHppExpression}) AS gross_profit\n            ", false)
            ->order_by($orderDateExpression, 'DESC', false)
            ->order_by('o.id', 'DESC')
            ->get()
            ->result_array();
    }

    private function sales_hpp_audit_order_base_query(array $filters): void
    {
        $this->db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join($this->refund_summary_subquery() . ' rf', 'rf.order_id = o.id', 'left', false)
            ->join($this->order_hpp_summary_subquery() . ' hp', 'hp.order_id = o.id', 'left', false)
            ->join($this->order_refund_hpp_summary_subquery() . ' rc', 'rc.order_id = o.id', 'left', false)
            ->join($this->order_deficit_hpp_correction_subquery() . ' dc', 'dc.order_id = o.id', 'left', false)
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID']);

        $this->apply_sales_hpp_audit_order_filters($filters);
    }

    private function apply_sales_hpp_audit_order_filters(array $filters): void
    {
        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'o.order_no',
            'o.table_no',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), 'o');
        $this->apply_date_range_filter(
            'DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at))',
            (string)($filters['date_from'] ?? ''),
            (string)($filters['date_to'] ?? '')
        );
    }

    /**
     * A refund writer reduces the active POS lines, while paid_total remains
     * the original cash received. These expressions keep reports from
     * subtracting the same refund a second time.
     */
    private function sales_order_billing_before_refund_expression(): string
    {
        return '(CASE'
            . ' WHEN COALESCE(rf.refund_amount, 0) > 0'
            . '  AND COALESCE(o.paid_total, 0) > 0'
            . ' THEN COALESCE(o.paid_total, 0)'
            . ' ELSE COALESCE(o.grand_total, 0)'
            . ' END)';
    }

    private function sales_order_net_sales_expression(): string
    {
        return '(' . $this->sales_order_billing_before_refund_expression()
            . ' - COALESCE(rf.refund_amount, 0))';
    }

    private function sales_order_hpp_at_sale_expression(): string
    {
        return '(COALESCE(hp.hpp_sale_amount, 0)'
            . ' + COALESCE(rc.hpp_refund_reversed_amount, 0))';
    }

    private function sales_order_final_hpp_expression(): string
    {
        return '(COALESCE(hp.hpp_sale_amount, 0)'
            . ' + COALESCE(dc.hpp_deficit_correction_amount, 0))';
    }

    private function sales_hpp_audit_net_sales_expression(): string
    {
        return $this->sales_order_net_sales_expression();
    }

    private function sales_hpp_audit_final_hpp_expression(): string
    {
        return $this->sales_order_final_hpp_expression();
    }

    private function sales_hpp_audit_deficit_schema_ready(): bool
    {
        if (!$this->db->table_exists('inv_stock_deficit_cogs_adjustment')) {
            return false;
        }

        foreach (['status', 'order_id', 'order_line_id', 'recognition_date', 'variance_amount'] as $field) {
            if (!$this->db->field_exists($field, 'inv_stock_deficit_cogs_adjustment')) {
                return false;
            }
        }

        return true;
    }

    private function sales_hpp_audit_deficit_link_check(array $filters, int $limit): array
    {
        $issueCount = $this->sales_hpp_audit_deficit_link_count($filters);

        return [
            'code' => 'DEFICIT_HPP_CORRECTION_UNLINKED',
            'title' => 'Koreksi HPP defisit kehilangan tautan transaksi',
            'status' => $issueCount > 0 ? 'ERROR' : 'PASS',
            'issue_count' => $issueCount,
            'message' => $issueCount > 0
                ? 'Ada koreksi HPP defisit posted yang tidak lagi menunjuk ke order atau line order yang tepat. Jangan menghapus barisnya; telusuri defisit dan transaksi asal sebelum melakukan perbaikan terkontrol.'
                : 'Semua koreksi HPP defisit posted pada periode ini masih terhubung ke transaksi asalnya.',
            'sample_rows' => $issueCount > 0
                ? $this->sales_hpp_audit_deficit_link_rows($filters, $limit)
                : [],
            'source_type' => 'DEFICIT_HPP_ADJUSTMENT',
        ];
    }

    private function sales_hpp_audit_deficit_link_count(array $filters): int
    {
        $this->sales_hpp_audit_deficit_link_base_query($filters);

        return (int)$this->db
            ->where($this->sales_hpp_audit_deficit_link_condition(), null, false)
            ->count_all_results();
    }

    private function sales_hpp_audit_deficit_link_rows(array $filters, int $limit): array
    {
        $recognitionDateExpression = 'COALESCE(a.recognition_date, a.settlement_date, a.sale_date)';
        $this->sales_hpp_audit_deficit_link_base_query($filters);

        return $this->db->select("\n                a.id AS adjustment_id,\n                {$recognitionDateExpression} AS recognition_date,\n                a.order_id,\n                a.order_line_id,\n                COALESCE(a.variance_amount, 0) AS variance_amount,\n                o.order_no,\n                COALESCE(po.outlet_name, '-') AS outlet_name,\n                CASE\n                    WHEN a.order_id IS NULL THEN 'Order asal tidak diisi'\n                    WHEN o.id IS NULL THEN 'Order asal tidak ditemukan'\n                    WHEN a.order_line_id IS NOT NULL AND l.id IS NULL THEN 'Line order tidak ditemukan'\n                    WHEN a.order_line_id IS NOT NULL AND l.order_id <> a.order_id THEN 'Line bukan milik order asal'\n                    ELSE 'Tautan koreksi perlu ditinjau'\n                END AS issue_reason\n            ", false)
            ->where($this->sales_hpp_audit_deficit_link_condition(), null, false)
            ->order_by($recognitionDateExpression, 'DESC', false)
            ->order_by('a.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    private function sales_hpp_audit_deficit_link_base_query(array $filters): void
    {
        $this->db->from('inv_stock_deficit_cogs_adjustment a')
            ->join('pos_order o', 'o.id = a.order_id', 'left')
            ->join('pos_order_line l', 'l.id = a.order_line_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->where('a.status', 'POSTED');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'o.order_no',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), 'o');
        $this->apply_date_range_filter(
            'COALESCE(a.recognition_date, a.settlement_date, a.sale_date)',
            (string)($filters['date_from'] ?? ''),
            (string)($filters['date_to'] ?? '')
        );
    }

    private function sales_hpp_audit_deficit_link_condition(): string
    {
        return '(a.order_id IS NULL'
            . ' OR o.id IS NULL'
            . ' OR (a.order_line_id IS NOT NULL AND (l.id IS NULL OR l.order_id <> a.order_id)))';
    }

    private function apply_order_filters(array $filters, string $orderAlias = 'o', array $extraFields = []): void
    {
        $customerExpr = $this->order_customer_display_expr($orderAlias, 'm');
        $fields = [
            $orderAlias . '.order_no',
            $orderAlias . '.table_no',
            $customerExpr,
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ];
        foreach ($extraFields as $field) {
            $fields[] = $field;
        }

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), $fields);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), $orderAlias);
        $this->apply_date_range_filter('DATE(COALESCE(' . $orderAlias . '.paid_at, ' . $orderAlias . '.confirmed_at, ' . $orderAlias . '.ordered_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where($orderAlias . '.status', $status);
        }

        $scope = strtoupper(trim((string)($filters['order_scope'] ?? 'ALL')));
        if ($scope !== '' && $scope !== 'ALL') {
            $this->db->where($orderAlias . '.order_scope', $scope);
        }

        $serviceType = strtoupper(trim((string)($filters['service_type'] ?? 'ALL')));
        if ($serviceType !== '' && $serviceType !== 'ALL') {
            $this->db->where($orderAlias . '.service_type', $serviceType);
        }
    }

    private function apply_keyword_filter(string $q, array $fields): void
    {
        $q = trim($q);
        if ($q === '' || empty($fields)) {
            return;
        }

        $this->db->group_start();
        foreach (array_values($fields) as $index => $field) {
            if ($index === 0) {
                $this->db->like($field, $q);
            } else {
                $this->db->or_like($field, $q);
            }
        }
        $this->db->group_end();
    }

    private function apply_outlet_filter(int $outletId, string $alias = 'o'): void
    {
        if ($outletId > 0) {
            $this->db->where($alias . '.outlet_id', $outletId);
        }
    }

    private function apply_date_range_filter(string $expression, string $dateFrom, string $dateTo): void
    {
        if ($dateFrom !== '') {
            $this->db->where($expression . ' >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $this->db->where($expression . ' <=', $dateTo);
        }
    }

    private function order_line_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_id,\n                COUNT(*) AS line_count,\n                COALESCE(SUM(qty), 0) AS qty_total\n            FROM pos_order_line\n            WHERE line_status <> 'VOID'\n            GROUP BY order_id\n        )";
    }

    private function order_line_extra_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_line_id,\n                COALESCE(SUM(net_amount), 0) AS extra_amount,\n                COALESCE(SUM(COALESCE(qty, 0) * COALESCE(cost_amount_snapshot, 0)), 0) AS extra_hpp_sale_amount\n            FROM pos_order_line_extra\n            GROUP BY order_line_id\n        )";
    }


    /**
     * HPP snapshot saat penjualan dibuat. Extra dihitung per qty karena
     * cost_amount_snapshot pada extra adalah biaya per unit extra.
     */
    private function order_hpp_summary_subquery(): string
    {
        return "(\n            SELECT\n                l.order_id,\n                COALESCE(SUM(COALESCE(l.cogs_amount, 0) + COALESCE(xs.extra_hpp_sale_amount, 0)), 0) AS hpp_sale_amount\n            FROM pos_order_line l\n            LEFT JOIN " . $this->order_line_extra_summary_subquery() . " xs ON xs.order_line_id = l.id\n            WHERE l.line_status <> 'VOID'\n            GROUP BY l.order_id\n        )";
    }

    /**
     * HPP yang dibalik oleh refund posted. Biaya dikurangi agar margin order
     * tidak tetap menanggung line yang sudah dikembalikan/refund.
     */
    private function order_refund_hpp_summary_subquery(): string
    {
        return "(\n            SELECT\n                r.order_id,\n                COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount\n            FROM pos_refund r\n            INNER JOIN pos_refund_line rl ON rl.refund_id = r.id\n            WHERE r.refund_status = 'POSTED'\n            GROUP BY r.order_id\n        )";
    }

    private function order_line_refund_hpp_summary_subquery(): string
    {
        $grossAmountExpression = $this->refund_line_gross_amount_expression('rl');
        return "(\n            SELECT\n                rl.order_line_id,\n                COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount,\n                COALESCE(SUM(" . $grossAmountExpression . "), 0) AS refund_gross_amount,\n                COALESCE(SUM(rl.cost_reversed), 0) AS hpp_refund_reversed_amount\n            FROM pos_refund r\n            INNER JOIN pos_refund_line rl ON rl.refund_id = r.id\n            WHERE r.refund_status = 'POSTED'\n              AND rl.order_line_id IS NOT NULL\n            GROUP BY rl.order_line_id\n        )";
    }

    /**
     * Koreksi biaya setelah defisit terselesaikan. Tabel ini opsional agar
     * laporan tetap bisa dibuka pada database yang belum menjalankan migrasi
     * HPP defisit.
     */
    private function order_deficit_hpp_correction_subquery(): string
    {
        if (!$this->db->table_exists('inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('order_id', 'inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('variance_amount', 'inv_stock_deficit_cogs_adjustment')) {
            return '(SELECT NULL AS order_id, 0.00 AS hpp_deficit_correction_amount WHERE 1 = 0)';
        }

        $reversalJoin = '';
        $reversalAmountExpr = '0';
        if ($this->db->table_exists('inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('cogs_adjustment_id', 'inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('variance_amount_reversed', 'inv_stock_deficit_cogs_reversal')) {
            $reversalJoin = "\n                LEFT JOIN (\n                    SELECT cogs_adjustment_id, COALESCE(SUM(variance_amount_reversed), 0) AS variance_reversed\n                    FROM inv_stock_deficit_cogs_reversal\n                    GROUP BY cogs_adjustment_id\n                ) rv ON rv.cogs_adjustment_id = a.id";
            $reversalAmountExpr = 'COALESCE(rv.variance_reversed, 0)';
        }

        return "(\n            SELECT\n                a.order_id,\n                COALESCE(SUM(COALESCE(a.variance_amount, 0) - {$reversalAmountExpr}), 0) AS hpp_deficit_correction_amount\n            FROM inv_stock_deficit_cogs_adjustment a{$reversalJoin}\n            WHERE a.status = 'POSTED'\n              AND a.order_id IS NOT NULL\n            GROUP BY a.order_id\n        )";
    }

    private function order_line_deficit_hpp_correction_subquery(): string
    {
        if (!$this->db->table_exists('inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('order_line_id', 'inv_stock_deficit_cogs_adjustment')
            || !$this->db->field_exists('variance_amount', 'inv_stock_deficit_cogs_adjustment')) {
            return '(SELECT NULL AS order_line_id, 0.00 AS hpp_deficit_correction_amount WHERE 1 = 0)';
        }

        $reversalJoin = '';
        $reversalAmountExpr = '0';
        if ($this->db->table_exists('inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('cogs_adjustment_id', 'inv_stock_deficit_cogs_reversal')
            && $this->db->field_exists('variance_amount_reversed', 'inv_stock_deficit_cogs_reversal')) {
            $reversalJoin = "\n                LEFT JOIN (\n                    SELECT cogs_adjustment_id, COALESCE(SUM(variance_amount_reversed), 0) AS variance_reversed\n                    FROM inv_stock_deficit_cogs_reversal\n                    GROUP BY cogs_adjustment_id\n                ) rv ON rv.cogs_adjustment_id = a.id";
            $reversalAmountExpr = 'COALESCE(rv.variance_reversed, 0)';
        }

        return "(\n            SELECT\n                a.order_line_id,\n                COALESCE(SUM(COALESCE(a.variance_amount, 0) - {$reversalAmountExpr}), 0) AS hpp_deficit_correction_amount\n            FROM inv_stock_deficit_cogs_adjustment a{$reversalJoin}\n            WHERE a.status = 'POSTED'\n              AND a.order_line_id IS NOT NULL\n            GROUP BY a.order_line_id\n        )";
    }

    private function refund_line_gross_amount_expression(string $alias = 'rl'): string
    {
        if ($this->db->field_exists('gross_amount_refunded', 'pos_refund_line')) {
            return '(CASE WHEN COALESCE(' . $alias . '.gross_amount_refunded, 0) <> 0'
                . ' THEN COALESCE(' . $alias . '.gross_amount_refunded, 0)'
                . ' ELSE COALESCE(' . $alias . '.amount_refunded, 0) END)';
        }

        return 'COALESCE(' . $alias . '.amount_refunded, 0)';
    }

    private function refund_summary_subquery(): string
    {
        $grossAmountExpression = $this->refund_line_gross_amount_expression('rl');
        return "(\n            SELECT\n                r.order_id,\n                COUNT(*) AS refund_count,\n                COALESCE(SUM(r.refund_amount), 0) AS refund_amount,\n                COALESCE(SUM(COALESCE(rls.refund_gross_amount, 0)), 0) AS refund_gross_amount\n            FROM pos_refund r\n            LEFT JOIN (\n                SELECT rl.refund_id, COALESCE(SUM(" . $grossAmountExpression . "), 0) AS refund_gross_amount\n                FROM pos_refund_line rl\n                GROUP BY rl.refund_id\n            ) rls ON rls.refund_id = r.id\n            WHERE r.refund_status = 'POSTED'\n            GROUP BY r.order_id\n        )";
    }

    private function void_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_id,\n                COUNT(*) AS void_count,\n                COALESCE(SUM(amount_void), 0) AS void_amount\n            FROM pos_void\n            GROUP BY order_id\n        )";
    }

    private function payment_method_summary_subquery(): string
    {
        return "(\n            SELECT\n                p.order_id,\n                GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name ASC SEPARATOR ', ') AS method_names,\n                GROUP_CONCAT(DISTINCT pm.id ORDER BY pm.id ASC SEPARATOR ',') AS method_ids\n            FROM pos_payment p\n            INNER JOIN pos_payment_line pl ON pl.payment_id = p.id AND pl.status = 'PAID'\n            INNER JOIN pos_payment_method pm ON pm.id = pl.payment_method_id\n            WHERE p.payment_status = 'PAID' AND p.payment_type = 'FINAL'\n            GROUP BY p.order_id\n        )";
    }

    private function payment_line_summary_subquery(): string
    {
        return "(\n            SELECT\n                pl.payment_id,\n                COALESCE(SUM(pl.amount), 0) AS amount_total,\n                GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name ASC SEPARATOR ', ') AS method_names\n            FROM pos_payment_line pl\n            INNER JOIN pos_payment_method pm ON pm.id = pl.payment_method_id\n            GROUP BY pl.payment_id\n        )";
    }

    private function count_cashier_close_rows(array $filters): int
    {
        if (!$this->db->table_exists('pos_shift')) {
            return 0;
        }

        $this->db->from('pos_shift sh')
            ->join('pos_outlet o', 'o.id = sh.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = sh.terminal_id', 'left')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('org_employee ec', 'ec.id = sh.cashier_close_employee_id', 'left')
            ->where('sh.status', 'CLOSED');

        $this->apply_cashier_close_filters($filters);
        return (int)$this->db->count_all_results();
    }

    private function cashier_close_rows(array $filters, int $limit, int $offset): array
    {
        if (!$this->db->table_exists('pos_shift')) {
            return [];
        }

        $hasShiftSummary = $this->db->table_exists('pos_shift_summary');
        $depositExpr = $this->cashier_close_deposit_expr('sh.id');
        $cashDepositExpr = $this->cashier_close_cash_deposit_expr('sh.id');

        $select = [
            'sh.id',
            'sh.shift_no',
            'sh.outlet_id',
            'o.outlet_name',
            'sh.terminal_id',
            't.terminal_name',
            'sh.cashier_open_employee_id',
            'eo.employee_name AS cashier_open_name',
            'sh.cashier_close_employee_id',
            'ec.employee_name AS cashier_close_name',
            'sh.status',
            'sh.opened_at',
            'sh.closed_at',
            'sh.opening_cash',
            'sh.expected_cash',
            'sh.actual_cash',
            'sh.variance_cash',
            'sh.notes',
            $hasShiftSummary ? 'COALESCE(ss.total_order_count, 0) AS total_order_count' : '0 AS total_order_count',
            $hasShiftSummary ? 'COALESCE(ss.total_gross_sales, 0) AS total_gross_sales' : '0 AS total_gross_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_discount, 0) AS total_discount' : '0 AS total_discount',
            $hasShiftSummary ? 'COALESCE(ss.total_promo, 0) AS total_promo' : '0 AS total_promo',
            $hasShiftSummary ? 'COALESCE(ss.total_net_sales, 0) AS total_net_sales' : '0 AS total_net_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_cash_sales, 0) AS total_cash_sales' : '0 AS total_cash_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_non_cash_sales, 0) AS total_non_cash_sales' : '0 AS total_non_cash_sales',
            $hasShiftSummary ? 'COALESCE(ss.total_refund, 0) AS total_refund' : '0 AS total_refund',
            $hasShiftSummary ? 'COALESCE(ss.total_void, 0) AS total_void' : '0 AS total_void',
            $depositExpr . ' AS total_deposit_receipts',
            $cashDepositExpr . ' AS total_cash_deposit_receipts',
        ];

        $this->db->select(implode(', ', $select), false)
            ->from('pos_shift sh')
            ->join('pos_outlet o', 'o.id = sh.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = sh.terminal_id', 'left')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('org_employee ec', 'ec.id = sh.cashier_close_employee_id', 'left');

        if ($hasShiftSummary) {
            $this->db->join('pos_shift_summary ss', 'ss.shift_id = sh.id', 'left');
        }

        $this->db->where('sh.status', 'CLOSED');
        $this->apply_cashier_close_filters($filters);
        $rows = $this->db->order_by('sh.closed_at', 'DESC')
            ->order_by('sh.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [];
        }

        $shiftIds = array_values(array_unique(array_map(static function ($row): int {
            return (int)($row['id'] ?? 0);
        }, $rows)));
        $shiftIds = array_values(array_filter($shiftIds, static function ($id): bool {
            return $id > 0;
        }));

        $snapshotMap = $this->cashier_close_snapshot_map($shiftIds);
        $mutationMap = $this->cashier_close_mutation_map($shiftIds);
        $focusAccountId = max(0, (int)($filters['account_id'] ?? 0));
        $focusAccountLabel = trim((string)($filters['account_label'] ?? ''));

        foreach ($rows as &$row) {
            $shiftId = (int)($row['id'] ?? 0);
            $accountRows = $this->merge_cashier_close_account_rows(
                $snapshotMap[$shiftId] ?? [],
                $mutationMap[$shiftId] ?? [],
                $focusAccountId
            );

            $focusRow = $this->locate_cashier_close_focus_account($accountRows, $focusAccountId, $focusAccountLabel);
            $row['total_recorded_receipts'] = round((float)($row['total_net_sales'] ?? 0) + (float)($row['total_deposit_receipts'] ?? 0), 2);
            $row['account_rows'] = $accountRows;
            $row['focus_account'] = $focusRow;
            $row['has_cash_variance'] = abs((float)($row['variance_cash'] ?? 0)) > 0.009;
            $row['has_focus_variance'] = abs((float)($focusRow['variance_net'] ?? 0)) > 0.009;
            $row['has_any_variance'] = !empty($row['has_cash_variance']) || !empty($row['has_focus_variance']);
        }
        unset($row);

        return $rows;
    }

    private function cashier_close_overview(array $filters): array
    {
        if (!$this->db->table_exists('pos_shift')) {
            return [
                'shift_count' => 0,
                'total_orders' => 0,
                'total_net_sales' => 0.0,
                'total_deposit_receipts' => 0.0,
                'total_expected_cash' => 0.0,
                'total_actual_cash' => 0.0,
                'total_variance_cash' => 0.0,
                'variance_shift_count' => 0,
            ];
        }

        $hasShiftSummary = $this->db->table_exists('pos_shift_summary');
        $depositExpr = $this->cashier_close_deposit_expr('sh.id');

        $select = [
            'COUNT(*) AS shift_count',
            $hasShiftSummary ? 'COALESCE(SUM(ss.total_order_count), 0) AS total_orders' : '0 AS total_orders',
            $hasShiftSummary ? 'COALESCE(SUM(ss.total_net_sales), 0) AS total_net_sales' : '0 AS total_net_sales',
            'COALESCE(SUM(' . $depositExpr . '), 0) AS total_deposit_receipts',
            'COALESCE(SUM(sh.expected_cash), 0) AS total_expected_cash',
            'COALESCE(SUM(sh.actual_cash), 0) AS total_actual_cash',
            'COALESCE(SUM(sh.variance_cash), 0) AS total_variance_cash',
            'COALESCE(SUM(CASE WHEN ABS(sh.variance_cash) > 0.009 THEN 1 ELSE 0 END), 0) AS variance_shift_count',
        ];

        $this->db->select(implode(', ', $select), false)
            ->from('pos_shift sh')
            ->join('pos_outlet o', 'o.id = sh.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = sh.terminal_id', 'left')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('org_employee ec', 'ec.id = sh.cashier_close_employee_id', 'left');

        if ($hasShiftSummary) {
            $this->db->join('pos_shift_summary ss', 'ss.shift_id = sh.id', 'left');
        }

        $this->db->where('sh.status', 'CLOSED');
        $this->apply_cashier_close_filters($filters);
        $row = $this->db->get()->row_array();

        return [
            'shift_count' => (int)($row['shift_count'] ?? 0),
            'total_orders' => (int)($row['total_orders'] ?? 0),
            'total_net_sales' => round((float)($row['total_net_sales'] ?? 0), 2),
            'total_deposit_receipts' => round((float)($row['total_deposit_receipts'] ?? 0), 2),
            'total_expected_cash' => round((float)($row['total_expected_cash'] ?? 0), 2),
            'total_actual_cash' => round((float)($row['total_actual_cash'] ?? 0), 2),
            'total_variance_cash' => round((float)($row['total_variance_cash'] ?? 0), 2),
            'variance_shift_count' => (int)($row['variance_shift_count'] ?? 0),
        ];
    }

    private function apply_cashier_close_filters(array $filters): void
    {
        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'sh.shift_no',
            'o.outlet_name',
            't.terminal_name',
            'eo.employee_name',
            'ec.employee_name',
            'sh.notes',
        ]);

        if ((int)($filters['outlet_id'] ?? 0) > 0) {
            $this->db->where('sh.outlet_id', (int)$filters['outlet_id']);
        }

        $this->apply_date_range_filter(
            'DATE(COALESCE(sh.closed_at, sh.opened_at))',
            (string)($filters['date_from'] ?? ''),
            (string)($filters['date_to'] ?? '')
        );
    }

    private function cashier_close_deposit_expr(string $shiftExpr): string
    {
        if (!$this->db->table_exists('pos_payment')) {
            return '0';
        }

        return '(SELECT COALESCE(SUM(p.net_amount), 0) FROM pos_payment p WHERE p.shift_id = ' . $shiftExpr . " AND p.payment_status = 'PAID' AND p.payment_type = 'DEPOSIT')";
    }

    private function cashier_close_cash_deposit_expr(string $shiftExpr): string
    {
        if (!$this->db->table_exists('pos_payment') || !$this->db->table_exists('pos_payment_line') || !$this->db->table_exists('pos_payment_method')) {
            return '0';
        }

        return "(SELECT COALESCE(SUM(pl.amount), 0)\n            FROM pos_payment p\n            INNER JOIN pos_payment_line pl ON pl.payment_id = p.id AND pl.status = 'PAID'\n            INNER JOIN pos_payment_method pm ON pm.id = pl.payment_method_id\n            WHERE p.shift_id = " . $shiftExpr . " AND p.payment_status = 'PAID' AND p.payment_type = 'DEPOSIT' AND pm.method_type = 'CASH')";
    }

    private function cashier_close_snapshot_map(array $shiftIds): array
    {
        if (empty($shiftIds) || !$this->db->table_exists('pos_shift_account_summary')) {
            return [];
        }

        $rows = $this->db->select('shift_id, company_account_id, account_code, account_name, bank_name, account_label, gross_amount, refund_amount, net_amount, sort_order')
            ->from('pos_shift_account_summary')
            ->where_in('shift_id', $shiftIds)
            ->order_by('shift_id', 'ASC')
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $shiftId = (int)($row['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                continue;
            }
            $accountKey = $this->cashier_close_account_key((int)($row['company_account_id'] ?? 0), $this->cashier_close_account_label($row));
            if (!isset($map[$shiftId][$accountKey])) {
                $map[$shiftId][$accountKey] = [
                    'account_id' => (int)($row['company_account_id'] ?? 0),
                    'account_label' => $this->cashier_close_account_label($row),
                    'account_code' => (string)($row['account_code'] ?? ''),
                    'account_name' => (string)($row['account_name'] ?? ''),
                    'bank_name' => (string)($row['bank_name'] ?? ''),
                    'snapshot_gross' => 0.0,
                    'snapshot_refund' => 0.0,
                    'snapshot_net' => 0.0,
                    'mutation_in' => 0.0,
                    'mutation_out' => 0.0,
                    'mutation_net' => 0.0,
                    'variance_net' => 0.0,
                ];
            }

            $map[$shiftId][$accountKey]['snapshot_gross'] += round((float)($row['gross_amount'] ?? 0), 2);
            $map[$shiftId][$accountKey]['snapshot_refund'] += round((float)($row['refund_amount'] ?? 0), 2);
            $map[$shiftId][$accountKey]['snapshot_net'] += round((float)($row['net_amount'] ?? 0), 2);
        }

        return $map;
    }

    private function cashier_close_mutation_map(array $shiftIds): array
    {
        if (
            empty($shiftIds)
            || !$this->db->table_exists('fin_account_mutation_log')
            || !$this->db->table_exists('pos_payment')
            || !$this->db->table_exists('pos_payment_line')
            || !$this->db->table_exists('pos_refund')
            || !$this->db->table_exists('pos_order')
        ) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($shiftIds), '?'));
        $params = array_merge($shiftIds, $shiftIds, $shiftIds);
        $effectiveMutationFilter = $this->effective_account_mutation_sql_filter('ml');
        $sql = "
            SELECT
                src.shift_id,
                src.account_id,
                MAX(src.account_code) AS account_code,
                MAX(src.account_name) AS account_name,
                MAX(src.bank_name) AS bank_name,
                COALESCE(SUM(src.amount_in), 0) AS mutation_in,
                COALESCE(SUM(src.amount_out), 0) AS mutation_out
            FROM (
                SELECT
                    p.shift_id,
                    ml.account_id,
                    acc.account_code,
                    acc.account_name,
                    acc.bank_name,
                    CASE WHEN ml.mutation_type = 'IN' THEN ml.amount ELSE 0 END AS amount_in,
                    CASE WHEN ml.mutation_type = 'OUT' THEN ml.amount ELSE 0 END AS amount_out
                FROM fin_account_mutation_log ml
                INNER JOIN pos_payment_line pl ON ml.ref_table = 'pos_payment_line' AND ml.ref_id = pl.id
                INNER JOIN pos_payment p ON p.id = pl.payment_id
                LEFT JOIN fin_company_account acc ON acc.id = ml.account_id
                WHERE ml.ref_module = 'POS'
                  AND p.shift_id IN ($placeholders)
                  $effectiveMutationFilter

                UNION ALL

                SELECT
                    p.shift_id,
                    ml.account_id,
                    acc.account_code,
                    acc.account_name,
                    acc.bank_name,
                    CASE WHEN ml.mutation_type = 'IN' THEN ml.amount ELSE 0 END AS amount_in,
                    CASE WHEN ml.mutation_type = 'OUT' THEN ml.amount ELSE 0 END AS amount_out
                FROM fin_account_mutation_log ml
                INNER JOIN pos_payment p ON ml.ref_table = 'pos_payment' AND ml.ref_id = p.id
                LEFT JOIN fin_company_account acc ON acc.id = ml.account_id
                WHERE ml.ref_module = 'POS'
                  AND p.shift_id IN ($placeholders)
                  $effectiveMutationFilter

                UNION ALL

                SELECT
                    o.shift_id,
                    ml.account_id,
                    acc.account_code,
                    acc.account_name,
                    acc.bank_name,
                    CASE WHEN ml.mutation_type = 'IN' THEN ml.amount ELSE 0 END AS amount_in,
                    CASE WHEN ml.mutation_type = 'OUT' THEN ml.amount ELSE 0 END AS amount_out
                FROM fin_account_mutation_log ml
                INNER JOIN pos_refund r ON ml.ref_table = 'pos_refund' AND ml.ref_id = r.id
                INNER JOIN pos_order o ON o.id = r.order_id
                LEFT JOIN fin_company_account acc ON acc.id = ml.account_id
                WHERE ml.ref_module = 'POS'
                  AND o.shift_id IN ($placeholders)
                  $effectiveMutationFilter
            ) src
            GROUP BY src.shift_id, src.account_id
        ";

        $rows = $this->db->query($sql, $params)->result_array();
        $map = [];
        foreach ($rows as $row) {
            $shiftId = (int)($row['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                continue;
            }
            $accountId = (int)($row['account_id'] ?? 0);
            $accountLabel = $this->cashier_close_account_label($row);
            $accountKey = $this->cashier_close_account_key($accountId, $accountLabel);
            $map[$shiftId][$accountKey] = [
                'account_id' => $accountId,
                'account_label' => $accountLabel,
                'account_code' => (string)($row['account_code'] ?? ''),
                'account_name' => (string)($row['account_name'] ?? ''),
                'bank_name' => (string)($row['bank_name'] ?? ''),
                'snapshot_gross' => 0.0,
                'snapshot_refund' => 0.0,
                'snapshot_net' => 0.0,
                'mutation_in' => round((float)($row['mutation_in'] ?? 0), 2),
                'mutation_out' => round((float)($row['mutation_out'] ?? 0), 2),
                'mutation_net' => round((float)($row['mutation_in'] ?? 0) - (float)($row['mutation_out'] ?? 0), 2),
                'variance_net' => 0.0,
            ];
        }

        return $map;
    }

    private function merge_cashier_close_account_rows(array $snapshotRows, array $mutationRows, int $focusAccountId = 0): array
    {
        $keys = array_unique(array_merge(array_keys($snapshotRows), array_keys($mutationRows)));
        $rows = [];

        foreach ($keys as $accountKey) {
            $snapshot = $snapshotRows[$accountKey] ?? [];
            $mutation = $mutationRows[$accountKey] ?? [];
            $accountId = (int)($snapshot['account_id'] ?? $mutation['account_id'] ?? 0);
            $accountLabel = trim((string)($snapshot['account_label'] ?? $mutation['account_label'] ?? ''));
            $row = [
                'account_id' => $accountId,
                'account_label' => $accountLabel !== '' ? $accountLabel : 'Tanpa rekening',
                'snapshot_gross' => round((float)($snapshot['snapshot_gross'] ?? 0), 2),
                'snapshot_refund' => round((float)($snapshot['snapshot_refund'] ?? 0), 2),
                'snapshot_net' => round((float)($snapshot['snapshot_net'] ?? 0), 2),
                'mutation_in' => round((float)($mutation['mutation_in'] ?? 0), 2),
                'mutation_out' => round((float)($mutation['mutation_out'] ?? 0), 2),
                'mutation_net' => round((float)($mutation['mutation_net'] ?? 0), 2),
            ];
            $row['variance_net'] = round($row['snapshot_net'] - $row['mutation_net'], 2);
            $row['is_focus'] = $focusAccountId > 0 && $accountId === $focusAccountId;
            $row['has_variance'] = abs((float)$row['variance_net']) > 0.009;
            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            $focusCompare = ((int)!empty($right['is_focus'])) <=> ((int)!empty($left['is_focus']));
            if ($focusCompare !== 0) {
                return $focusCompare;
            }
            $varianceCompare = abs((float)($right['variance_net'] ?? 0)) <=> abs((float)($left['variance_net'] ?? 0));
            if ($varianceCompare !== 0) {
                return $varianceCompare;
            }
            $snapshotCompare = (float)($right['snapshot_net'] ?? 0) <=> (float)($left['snapshot_net'] ?? 0);
            if ($snapshotCompare !== 0) {
                return $snapshotCompare;
            }
            return strcmp((string)($left['account_label'] ?? ''), (string)($right['account_label'] ?? ''));
        });

        return $rows;
    }

    private function cashier_close_account_key(int $accountId, string $accountLabel): string
    {
        if ($accountId > 0) {
            return 'id:' . $accountId;
        }

        $normalized = strtoupper(trim($accountLabel));
        return 'label:' . md5($normalized !== '' ? $normalized : 'UNKNOWN');
    }

    private function cashier_close_account_label(array $row): string
    {
        $label = trim((string)($row['account_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $code = trim((string)($row['account_code'] ?? ''));
        $name = trim((string)($row['account_name'] ?? ''));
        $bank = trim((string)($row['bank_name'] ?? ''));

        $parts = [];
        if ($code !== '') {
            $parts[] = $code;
        }
        if ($name !== '') {
            $parts[] = $name;
        }

        $label = implode(' - ', $parts);
        if ($bank !== '') {
            $label .= ($label !== '' ? ' | ' : '') . $bank;
        }

        return $label !== '' ? $label : 'Tanpa rekening';
    }

    private function normalize_report_date(string $date): string
    {
        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return date('Y-m-d');
        }

        return $date;
    }

    private function locate_cashier_close_focus_account(array $accountRows, int $focusAccountId, string $fallbackLabel): array
    {
        foreach ($accountRows as $row) {
            if ($focusAccountId > 0 && (int)($row['account_id'] ?? 0) === $focusAccountId) {
                return $row;
            }
        }

        if (!empty($accountRows)) {
            return $accountRows[0];
        }

        return [
            'account_id' => $focusAccountId,
            'account_label' => $fallbackLabel,
            'snapshot_gross' => 0.0,
            'snapshot_refund' => 0.0,
            'snapshot_net' => 0.0,
            'mutation_in' => 0.0,
            'mutation_out' => 0.0,
            'mutation_net' => 0.0,
            'variance_net' => 0.0,
            'is_focus' => true,
            'has_variance' => false,
        ];
    }

    private function daily_sales_overview(string $date, int $outletId = 0): array
    {
        $dateSql = $this->db->escape($date);

        $this->db->from('pos_order o')
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) = ' . $dateSql, null, false);
        if ($outletId > 0) {
            $this->db->where('o.outlet_id', $outletId);
        }

        $sales = $this->db->select('COUNT(DISTINCT o.id) AS order_count, COALESCE(SUM(o.grand_total), 0) AS gross_sales, COALESCE(SUM(o.paid_total), 0) AS received_gross, COALESCE(SUM(GREATEST(o.grand_total - o.paid_total, 0)), 0) AS pending_amount', false)
            ->get()
            ->row_array() ?: [];

        $this->db->from('pos_order o')
            ->where_in('o.status', ['PENDING', 'PAID_PARTIAL']);
        if ($outletId > 0) {
            $this->db->where('o.outlet_id', $outletId);
        }
        $this->db->where('DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) = ' . $dateSql, null, false);
        $pendingCount = (int)$this->db->count_all_results();

        $refundQuery = $this->db->select('COALESCE(SUM(r.refund_amount), 0) AS refund_amount', false)
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'inner')
            ->where('r.refund_status', 'POSTED')
            ->where('DATE(r.refunded_at) = ' . $dateSql, null, false);
        if ($outletId > 0) {
            $refundQuery->where('o.outlet_id', $outletId);
        }
        $refund = $refundQuery->get()->row_array() ?: [];

        $grossSales = round((float)($sales['gross_sales'] ?? 0), 2);
        $refundAmount = round((float)($refund['refund_amount'] ?? 0), 2);
        $receivedGross = round((float)($sales['received_gross'] ?? 0), 2);
        $netSales = round($grossSales - $refundAmount, 2);
        $netReceived = round($receivedGross - $refundAmount, 2);
        $orderCount = (int)($sales['order_count'] ?? 0);

        return [
            'order_count' => $orderCount,
            'pending_count' => $pendingCount,
            'pending_amount' => round((float)($sales['pending_amount'] ?? 0), 2),
            'gross_sales' => $grossSales,
            'refund_amount' => $refundAmount,
            'net_sales' => $netSales,
            'received_gross' => $receivedGross,
            'refund_received' => $refundAmount,
            'net_received' => $netReceived,
            'selisih' => round($netSales - $netReceived, 2),
            'aov' => $orderCount > 0 ? round($grossSales / $orderCount, 2) : 0.0,
        ];
    }

    private function daily_sales_by_division(string $date, int $outletId = 0): array
    {
        $nameExpr = $this->product_division_name_expr('pd');
        $dateSql = $this->db->escape($date);

        $this->db->select($nameExpr . ' AS division_name, COALESCE(SUM(ol.net_amount), 0) AS revenue', false)
            ->from('pos_order_line ol')
            ->join('pos_order o', 'o.id = ol.order_id', 'inner')
            ->join('mst_product p', 'p.id = ol.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where('ol.line_type', 'PRODUCT')
            ->where('ol.line_status <>', 'VOID')
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('DATE(COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)) = ' . $dateSql, null, false)
            ->group_by('p.product_division_id')
            ->order_by('COALESCE(pd.sort_order, 9999)', 'ASC', false)
            ->order_by('division_name', 'ASC');
        if ($outletId > 0) {
            $this->db->where('o.outlet_id', $outletId);
        }

        return $this->db->get()->result_array();
    }

    private function daily_sales_payment_methods(string $date, int $outletId = 0): array
    {
        $dateSql = $this->db->escape($date);

        $query = $this->db->select("pm.id AS payment_method_id, pm.method_name, COALESCE(SUM(CASE WHEN p.payment_type IN ('FINAL', 'DEPOSIT') THEN pl.amount ELSE 0 END), 0) AS gross_amount, COUNT(DISTINCT CASE WHEN p.payment_type IN ('FINAL', 'DEPOSIT') THEN p.id END) AS trx_count", false)
            ->from('pos_payment_line pl')
            ->join('pos_payment p', 'p.id = pl.payment_id AND p.payment_status = "PAID" AND pl.status = "PAID"', 'inner', false)
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'inner')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->where('DATE(COALESCE(p.paid_at, p.created_at)) = ' . $dateSql, null, false)
            ->group_by(['pm.id', 'pm.method_name'])
            ->order_by('gross_amount', 'DESC', false);
        if ($outletId > 0) {
            $query->where('o.outlet_id', $outletId);
        }

        $receiptRows = $query->get()->result_array();
        $refundQuery = $this->db->select('pm.id AS payment_method_id, pm.method_name, COALESCE(SUM(r.refund_amount), 0) AS refund_amount', false)
            ->from('pos_refund r')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->where('r.refund_status', 'POSTED')
            ->where('r.payment_method_id IS NOT NULL', null, false)
            ->where('DATE(r.refunded_at) = ' . $dateSql, null, false)
            ->group_by(['pm.id', 'pm.method_name']);
        if ($outletId > 0) {
            $refundQuery->where('o.outlet_id', $outletId);
        }
        $refundRows = $refundQuery->get()->result_array();

        $merged = [];
        foreach ($receiptRows as $row) {
            $methodId = (int)($row['payment_method_id'] ?? 0);
            $key = $methodId > 0 ? 'id:' . $methodId : 'name:' . md5((string)($row['method_name'] ?? ''));
            $merged[$key] = [
                'payment_method_id' => $methodId,
                'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                'gross_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'refund_amount' => 0.0,
                'net_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'trx_count' => (int)($row['trx_count'] ?? 0),
            ];
        }
        foreach ($refundRows as $row) {
            $methodId = (int)($row['payment_method_id'] ?? 0);
            $key = $methodId > 0 ? 'id:' . $methodId : 'name:' . md5((string)($row['method_name'] ?? ''));
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'payment_method_id' => $methodId,
                    'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                    'gross_amount' => 0.0,
                    'refund_amount' => 0.0,
                    'net_amount' => 0.0,
                    'trx_count' => 0,
                ];
            }
            $merged[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $merged[$key]['net_amount'] = round((float)$merged[$key]['gross_amount'] - (float)$merged[$key]['refund_amount'], 2);
        }

        usort($merged, static function (array $left, array $right): int {
            return (float)($right['net_amount'] ?? 0) <=> (float)($left['net_amount'] ?? 0);
        });

        return array_values($merged);
    }

    private function daily_sales_payment_accounts(string $date, int $outletId = 0): array
    {
        $accountExpr = $this->payment_company_account_expr('pl', 'pm');
        if ($accountExpr === 'NULL' || !$this->db->table_exists('fin_company_account')) {
            return [];
        }

        $dateSql = $this->db->escape($date);

        $query = $this->db->select("acc.id AS account_id, COALESCE(acc.bank_name, 'Tanpa Rekening') AS bank_name, COALESCE(acc.account_name, '-') AS account_name, COALESCE(acc.account_no, '-') AS account_no, COALESCE(SUM(CASE WHEN p.payment_type IN ('FINAL', 'DEPOSIT') THEN pl.amount ELSE 0 END), 0) AS gross_amount", false)
            ->from('pos_payment_line pl')
            ->join('pos_payment p', 'p.id = pl.payment_id AND p.payment_status = "PAID" AND pl.status = "PAID"', 'inner', false)
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = ' . $accountExpr, 'left', false)
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->where('DATE(COALESCE(p.paid_at, p.created_at)) = ' . $dateSql, null, false)
            ->where($accountExpr . ' IS NOT NULL', null, false)
            ->group_by(['acc.id', 'acc.bank_name', 'acc.account_name', 'acc.account_no'])
            ->order_by('gross_amount', 'DESC', false);
        if ($outletId > 0) {
            $query->where('o.outlet_id', $outletId);
        }

        $receiptRows = $query->get()->result_array();
        $refundQuery = $this->db->select("acc.id AS account_id, COALESCE(acc.bank_name, 'Tanpa Rekening') AS bank_name, COALESCE(acc.account_name, '-') AS account_name, COALESCE(acc.account_no, '-') AS account_no, COALESCE(SUM(r.refund_amount), 0) AS refund_amount", false)
            ->from('pos_refund r')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->where('r.refund_status', 'POSTED')
            ->where('r.company_account_id IS NOT NULL', null, false)
            ->where('DATE(r.refunded_at) = ' . $dateSql, null, false)
            ->group_by(['acc.id', 'acc.bank_name', 'acc.account_name', 'acc.account_no']);
        if ($outletId > 0) {
            $refundQuery->where('o.outlet_id', $outletId);
        }
        $refundRows = $refundQuery->get()->result_array();

        $merged = [];
        foreach ($receiptRows as $row) {
            $accountId = (int)($row['account_id'] ?? 0);
            $key = $accountId > 0 ? 'id:' . $accountId : 'name:' . md5((string)($row['account_name'] ?? ''));
            $merged[$key] = [
                'account_id' => $accountId,
                'bank_name' => (string)($row['bank_name'] ?? 'Tanpa Rekening'),
                'account_name' => (string)($row['account_name'] ?? '-'),
                'account_no' => (string)($row['account_no'] ?? '-'),
                'gross_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'refund_amount' => 0.0,
                'net_amount' => round((float)($row['gross_amount'] ?? 0), 2),
            ];
        }
        foreach ($refundRows as $row) {
            $accountId = (int)($row['account_id'] ?? 0);
            $key = $accountId > 0 ? 'id:' . $accountId : 'name:' . md5((string)($row['account_name'] ?? ''));
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'account_id' => $accountId,
                    'bank_name' => (string)($row['bank_name'] ?? 'Tanpa Rekening'),
                    'account_name' => (string)($row['account_name'] ?? '-'),
                    'account_no' => (string)($row['account_no'] ?? '-'),
                    'gross_amount' => 0.0,
                    'refund_amount' => 0.0,
                    'net_amount' => 0.0,
                ];
            }
            $merged[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $merged[$key]['net_amount'] = round((float)$merged[$key]['gross_amount'] - (float)$merged[$key]['refund_amount'], 2);
        }

        usort($merged, static function (array $left, array $right): int {
            return (float)($right['net_amount'] ?? 0) <=> (float)($left['net_amount'] ?? 0);
        });

        return array_values($merged);
    }

    private function daily_sales_shifts(string $date, int $outletId = 0): array
    {
        // OPEN shifts have not written their closing snapshot yet. Read live
        // order aggregates without joining payment lines (which multiply sales).
        // CLOSED shifts retain the existing audited closing snapshot.
        $liveOrders = "SELECT COUNT(*) FROM pos_order so WHERE so.shift_id = sh.id AND so.status NOT IN ('DRAFT','PENDING','VOID')";
        $liveSales = "SELECT SUM(so.grand_total) FROM pos_order so WHERE so.shift_id = sh.id AND so.status NOT IN ('DRAFT','PENDING','VOID')";
        $query = $this->db->select('sh.id, sh.shift_no, sh.opened_at, sh.closed_at, sh.status AS shift_status,
                eo.employee_name AS cashier_name, outlet.outlet_name, terminal.terminal_name,
                CASE WHEN sh.status = "OPEN" THEN (' . $liveOrders . ') ELSE COALESCE(ss.total_order_count, 0) END AS trx_count,
                CASE WHEN sh.status = "OPEN" THEN COALESCE((' . $liveSales . '), 0) ELSE COALESCE(ss.total_net_sales, 0) END
                + COALESCE((SELECT SUM(p.net_amount) FROM pos_payment p WHERE p.shift_id = sh.id AND p.payment_type = "DEPOSIT" AND p.payment_status = "PAID"), 0) AS revenue', false)
            ->from('pos_shift sh')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('pos_outlet outlet', 'outlet.id = sh.outlet_id', 'left')
            ->join('pos_terminal terminal', 'terminal.id = sh.terminal_id', 'left')
            ->join('pos_shift_summary ss', 'ss.shift_id = sh.id', 'left')
            ->where('sh.opened_at <', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00')
            ->group_start()
            ->where('sh.closed_at >=', $date . ' 00:00:00')
            ->or_where('sh.closed_at', null)
            ->group_end()
            ->order_by('sh.opened_at', 'ASC')
            ->order_by('sh.id', 'ASC');
        if ($outletId > 0) {
            $query->where('sh.outlet_id', $outletId);
        }

        return $query->get()->result_array();
    }

    private function daily_sales_purchase_total(string $date): float
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return 0.0;
        }

        $row = $this->db->select('COALESCE(SUM(grand_total), 0) AS total_purchase', false)
            ->from('pur_purchase_order')
            ->where('request_date', $date)
            ->where_not_in('status', ['DRAFT', 'REJECTED', 'VOID'])
            ->get()
            ->row_array();

        return round((float)($row['total_purchase'] ?? 0), 2);
    }

    private function product_division_name_expr(string $alias = 'pd'): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'pd';
        if ($this->db->field_exists('division_name', 'mst_product_division')) {
            return 'COALESCE(' . $alias . '.division_name, "Lainnya")';
        }
        if ($this->db->field_exists('name', 'mst_product_division')) {
            return 'COALESCE(' . $alias . '.name, "Lainnya")';
        }

        return '"Lainnya"';
    }

    private function payment_company_account_expr(string $paymentLineAlias = 'pl', string $paymentMethodAlias = 'pm'): string
    {
        $hasPaymentLineAccount = $this->db->field_exists('company_account_id', 'pos_payment_line');
        $hasPaymentMethodAccount = $this->db->field_exists('company_account_id', 'pos_payment_method');

        if ($hasPaymentLineAccount && $hasPaymentMethodAccount) {
            return 'COALESCE(' . $paymentLineAlias . '.company_account_id, ' . $paymentMethodAlias . '.company_account_id)';
        }
        if ($hasPaymentLineAccount) {
            return $paymentLineAlias . '.company_account_id';
        }
        if ($hasPaymentMethodAccount) {
            return $paymentMethodAlias . '.company_account_id';
        }

        return 'NULL';
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        return [$page, $offset, $totalPages];
    }
}
