<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only audit for the active inventory month.
 *
 * This is intentionally separate from stock writers: it can be run after a
 * deploy or on a schedule without changing stock, lots, values, or documents.
 */
class InventoryActiveMonthIntegrityAudit
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function run(array $options = []): array
    {
        $month = $this->normalizeMonth((string)($options['month'] ?? date('Y-m-01')));
        $limit = max(1, min(100, (int)($options['limit'] ?? 20)));
        if ($month === null) {
            return [
                'ok' => false,
                'message' => 'Bulan audit tidak valid. Gunakan format YYYY-MM atau YYYY-MM-01.',
                'checks' => [],
            ];
        }

        $nextMonth = date('Y-m-01', strtotime($month . ' +1 month'));
        $checks = [];
        $checks[] = $this->checkRequiredTables();
        // Keep this separate from the generic table check. The audit can still
        // show the affected POS rows when the enum migration has not been run.
        $checks[] = $this->checkPosDeficitHppSchema();

        if ($this->requiredTablesReady($checks)) {
            $checks[] = $this->checkStockHealth($month, $limit);
            $checks[] = $this->checkPostedMaterialAdjustments($month, $nextMonth, $limit);
            $checks[] = $this->checkPostedComponentAdjustments($month, $nextMonth, $limit);
            $checks[] = $this->checkPostedComponentBatches($month, $nextMonth, $limit);
            $checks[] = $this->checkPostedTransfers($month, $nextMonth, $limit);
            $checks[] = $this->checkPosCommitLines($month, $nextMonth, $limit);
            $checks[] = $this->checkPosCancelledBeforePosting($month, $nextMonth, $limit);
            $checks[] = $this->checkPosFullDeficitProvisionalHpp($month, $nextMonth, $limit);
            $checks[] = $this->checkPosPartialDeficitProvisionalHpp($month, $nextMonth, $limit);
            $checks[] = $this->checkPosFullDeficitReferenceLabel($month, $nextMonth, $limit);
            $checks[] = $this->checkActiveDeficitArithmetic($month, $limit);
            $checks[] = $this->checkDeficitCogsFoundation();
            if ($this->deficitCogsFoundationReady()) {
                $checks[] = $this->checkPosDeficitSettlementCogs($month, $nextMonth, $limit);
                $checks[] = $this->checkPosDeficitSettlementCost($month, $nextMonth, $limit);
                $checks[] = $this->checkDeficitCogsArithmetic($month, $nextMonth, $limit);
            }
            $checks[] = $this->checkNegativeActiveBalances($month, $limit);
            $checks[] = $this->checkWritesAfterClosedPeriod($limit);
        }

        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $issueCount = 0;
        foreach ($checks as $check) {
            $count = (int)($check['issue_count'] ?? 0);
            $issueCount += $count;
            if ($count <= 0) {
                continue;
            }
            $severity = strtoupper((string)($check['severity'] ?? 'WARNING'));
            if ($severity === 'ERROR') {
                $errorCount += $count;
            } elseif ($severity === 'WARNING') {
                $warningCount += $count;
            } elseif ($severity === 'INFO') {
                $infoCount += $count;
            }
        }

        return [
            'ok' => $errorCount === 0,
            'mode' => 'READ_ONLY',
            'month' => $month,
            'as_of_date' => min(date('Y-m-t', strtotime($month)), date('Y-m-d')),
            'message' => $errorCount > 0
                ? 'Audit menemukan kegagalan integritas yang perlu ditelusuri. Tidak ada data yang diubah.'
                : ($warningCount > 0
                    ? 'Audit selesai. Ada antrean pemeriksaan operator, tetapi tidak ada jejak transaksi yang putus.'
                    : ($infoCount > 0
                        ? 'Audit selesai. Commit terminal sebelum posting ditampilkan sebagai informasi dan tidak memerlukan repair stok.'
                        : 'Audit selesai. Tidak ditemukan masalah integritas pada pemeriksaan ini.')),
            'summary' => [
                'check_count' => count($checks),
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
                'issue_count' => $issueCount,
            ],
            'checks' => $checks,
        ];
    }

    private function checkRequiredTables(): array
    {
        $required = [
            'inv_stock_adjustment',
            'inv_stock_adjustment_line',
            'inv_stock_movement_log',
            'inv_component_adjustment',
            'inv_component_adjustment_line',
            'inv_component_batch',
            'inv_component_batch_input',
            'inv_component_movement_log',
            'inv_stock_transfer',
            'inv_stock_transfer_line',
            'pos_stock_commit',
            'pos_stock_commit_line',
            'inv_stock_deficit',
            'inv_stock_deficit_settlement',
            'inv_stock_period',
        ];
        $requiredColumns = [
            'inv_component_batch_input' => ['plan_role'],
            'inv_stock_adjustment_line' => [
                'qty_waste_content', 'qty_spoil_content', 'qty_process_loss_content',
                'qty_variance_content', 'qty_adjustment_plus_content',
            ],
            'inv_component_adjustment_line' => ['qty_spoil', 'qty_waste', 'qty_adjust_pos', 'qty_adjust_neg'],
            'pos_stock_commit_line' => [
                'committed_qty', 'reversed_qty', 'movement_ref_type', 'movement_ref_id',
                'unit_cost_live', 'total_cost_live', 'cost_source',
            ],
            'inv_stock_deficit' => [
                'requested_qty', 'issued_qty', 'settled_qty', 'reversed_qty', 'qty_remaining',
                'estimated_unit_cost', 'source_table', 'source_id', 'source_line_id',
            ],
        ];
        $missing = [];
        foreach ($required as $table) {
            if (!$this->ci->db->table_exists($table)) {
                $missing[] = $table;
                continue;
            }
            foreach ((array)($requiredColumns[$table] ?? []) as $column) {
                if (!$this->ci->db->field_exists($column, $table)) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        return $this->result(
            'REQUIRED_SCHEMA',
            'Struktur tabel audit',
            'ERROR',
            count($missing),
            $missing,
            empty($missing)
                ? 'Struktur tabel yang dibutuhkan tersedia.'
                : 'Tabel audit belum lengkap: ' . implode(', ', $missing) . '.'
        );
    }

    private function requiredTablesReady(array $checks): bool
    {
        return empty($checks) || (int)($checks[0]['issue_count'] ?? 0) === 0;
    }

    /**
     * The writer deliberately falls back to the older enum values during a
     * rolling deploy. That keeps POS available, but an audit must not certify
     * the HPP-deficit contract until both explicit enum values are present.
     */
    private function checkPosDeficitHppSchema(): array
    {
        $missing = [];
        if ($this->ci->db->field_exists('movement_ref_type', 'pos_stock_commit_line')
            && !$this->enumSupportsValue('pos_stock_commit_line', 'movement_ref_type', 'INVENTORY_DEFICIT')) {
            $missing[] = 'pos_stock_commit_line.movement_ref_type: INVENTORY_DEFICIT';
        }
        if ($this->ci->db->field_exists('cost_source', 'pos_stock_commit_line')
            && !$this->enumSupportsValue('pos_stock_commit_line', 'cost_source', 'DEFICIT_PENDING')) {
            $missing[] = 'pos_stock_commit_line.cost_source: DEFICIT_PENDING';
        }

        return $this->result(
            'POS_DEFICIT_HPP_SCHEMA',
            'Label dan sumber biaya POS defisit',
            'ERROR',
            count($missing),
            $missing,
            empty($missing)
                ? 'Label referensi dan sumber biaya HPP defisit tersedia.'
                : 'Kontrak HPP defisit belum lengkap. Jalankan 2026-08-19h_pos_commit_deficit_reference_and_provisional_hpp.sql: ' . implode(', ', $missing) . '.'
        );
    }

    private function enumSupportsValue(string $table, string $column, string $value): bool
    {
        $row = $this->ci->db
            ->select('COLUMN_TYPE')
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->ci->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->limit(1)
            ->get()
            ->row_array();
        $needle = "'" . strtoupper(trim($value)) . "'";
        return strpos(strtoupper((string)($row['COLUMN_TYPE'] ?? '')), $needle) !== false;
    }

    private function checkStockHealth(string $month, int $limit): array
    {
        $this->ci->load->model('Inventory_control_model');
        $summary = $this->ci->Inventory_control_model->inventory_health_summary($month);
        $material = (array)($summary['material'] ?? []);
        $component = (array)($summary['component'] ?? []);
        $count = (int)($material['mismatch_rows'] ?? 0) + (int)($component['mismatch_rows'] ?? 0);
        $rows = [];
        if ($count > 0) {
            $rows = $this->ci->Inventory_control_model->list_inventory_health_rows([
                'month' => $month,
            ], $limit, 0);
        }

        return $this->result(
            'ACTIVE_STOCK_HEALTH',
            'Stok bulanan, lot OPEN, dan nilai',
            'WARNING',
            $count,
            $rows,
            $count > 0
                ? 'Ada selisih stok/lot/nilai aktif. Periksa melalui Kesehatan Stok Aktif; audit ini tidak melakukan recon otomatis.'
                : 'Stok bulanan, lot OPEN, dan nilai aktif sudah selaras.'
        );
    }

    private function checkPostedMaterialAdjustments(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT h.id, h.adjustment_no, h.adjustment_date, h.stock_scope, h.division_id, h.destination_type,
                       l.changed_line_count, COALESCE(m.movement_count, 0) AS movement_count,
                       COALESCE(d.deficit_count, 0) AS deficit_count
                FROM inv_stock_adjustment h
                INNER JOIN (
                    SELECT adjustment_id,
                           SUM(CASE WHEN ABS(COALESCE(qty_waste_content, 0))
                                          + ABS(COALESCE(qty_spoil_content, 0))
                                          + ABS(COALESCE(qty_process_loss_content, 0))
                                          + ABS(COALESCE(qty_variance_content, 0))
                                          + ABS(COALESCE(qty_adjustment_plus_content, 0)) > 0.0001
                                    THEN 1 ELSE 0 END) AS changed_line_count
                    FROM inv_stock_adjustment_line
                    GROUP BY adjustment_id
                ) l ON l.adjustment_id = h.id
                LEFT JOIN (
                    SELECT ref_id, COUNT(*) AS movement_count
                    FROM inv_stock_movement_log
                    WHERE ref_table = 'inv_stock_adjustment'
                    GROUP BY ref_id
                ) m ON m.ref_id = h.id
                LEFT JOIN (
                    SELECT source_id, COUNT(*) AS deficit_count
                    FROM inv_stock_deficit
                    WHERE stock_domain = 'MATERIAL'
                      AND source_table = 'inv_stock_adjustment'
                      AND status <> 'VOID'
                    GROUP BY source_id
                ) d ON d.source_id = h.id
                WHERE h.status = 'POSTED'
                  AND h.adjustment_date >= ? AND h.adjustment_date < ?
                  AND l.changed_line_count > 0
                  AND COALESCE(m.movement_count, 0) = 0
                  AND COALESCE(d.deficit_count, 0) = 0";
        return $this->queryCheck(
            'POSTED_MATERIAL_ADJUSTMENT_TRACE',
            'Adjustment bahan baku posted tanpa movement atau defisit',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Setiap adjustment bahan baku yang benar-benar mengubah stok harus memiliki jejak movement atau defisit eksplisit.'
        );
    }

    private function checkPostedComponentAdjustments(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT h.id, h.adjustment_no, h.adjustment_date, h.location_type, h.division_id,
                       l.changed_line_count, COALESCE(m.movement_count, 0) AS movement_count
                FROM inv_component_adjustment h
                INNER JOIN (
                    SELECT adjustment_id,
                           SUM(CASE WHEN ABS(COALESCE(qty_spoil, 0))
                                          + ABS(COALESCE(qty_waste, 0))
                                          + ABS(COALESCE(qty_adjust_pos, 0))
                                          + ABS(COALESCE(qty_adjust_neg, 0)) > 0.0001
                                    THEN 1 ELSE 0 END) AS changed_line_count
                    FROM inv_component_adjustment_line
                    GROUP BY adjustment_id
                ) l ON l.adjustment_id = h.id
                LEFT JOIN (
                    SELECT source_id, COUNT(*) AS movement_count
                    FROM inv_component_movement_log
                    WHERE source_table = 'inv_component_adjustment'
                    GROUP BY source_id
                ) m ON m.source_id = h.id
                WHERE h.status = 'POSTED'
                  AND h.adjustment_date >= ? AND h.adjustment_date < ?
                  AND l.changed_line_count > 0
                  AND COALESCE(m.movement_count, 0) = 0";
        return $this->queryCheck(
            'POSTED_COMPONENT_ADJUSTMENT_TRACE',
            'Adjustment component posted tanpa movement',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Setiap adjustment component yang mengubah stok harus memiliki jejak movement component.'
        );
    }

    private function checkPostedComponentBatches(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT h.id, h.batch_no, h.batch_date, h.location_type, h.division_id, h.component_id, h.output_qty,
                       COALESCE(i.material_input_count, 0) AS material_input_count,
                       COALESCE(i.component_input_count, 0) AS component_input_count,
                       COALESCE(cm.output_movement_count, 0) AS output_movement_count,
                       COALESCE(cm.component_usage_movement_count, 0) AS component_usage_movement_count,
                       COALESCE(mm.material_usage_movement_count, 0) AS material_usage_movement_count
                FROM inv_component_batch h
                LEFT JOIN (
                    SELECT batch_id,
                           SUM(CASE WHEN source_kind = 'MATERIAL'
                                          AND COALESCE(plan_role, 'INPUT') NOT IN ('INLINE_OUTPUT', 'INLINE_COMPONENT_USAGE')
                                          AND COALESCE(qty, 0) > 0.0001 THEN 1 ELSE 0 END) AS material_input_count,
                           SUM(CASE WHEN source_kind = 'COMPONENT'
                                          AND COALESCE(plan_role, 'INPUT') NOT IN ('INLINE_OUTPUT', 'INLINE_COMPONENT_USAGE')
                                          AND COALESCE(qty, 0) > 0.0001 THEN 1 ELSE 0 END) AS component_input_count
                    FROM inv_component_batch_input
                    GROUP BY batch_id
                ) i ON i.batch_id = h.id
                LEFT JOIN (
                    SELECT source_id,
                           SUM(CASE WHEN movement_type = 'PRODUCTION_IN' THEN 1 ELSE 0 END) AS output_movement_count,
                           SUM(CASE WHEN movement_type = 'PRODUCTION_OUT' THEN 1 ELSE 0 END) AS component_usage_movement_count
                    FROM inv_component_movement_log
                    WHERE source_table = 'inv_component_batch'
                    GROUP BY source_id
                ) cm ON cm.source_id = h.id
                LEFT JOIN (
                    SELECT ref_id,
                           SUM(CASE WHEN movement_type = 'USAGE_OUT' THEN 1 ELSE 0 END) AS material_usage_movement_count
                    FROM inv_stock_movement_log
                    WHERE ref_table = 'inv_component_batch'
                    GROUP BY ref_id
                ) mm ON mm.ref_id = h.id
                WHERE h.status = 'POSTED'
                  AND h.batch_date >= ? AND h.batch_date < ?
                  AND (
                    COALESCE(cm.output_movement_count, 0) = 0
                    OR COALESCE(cm.component_usage_movement_count, 0) < COALESCE(i.component_input_count, 0)
                    OR (COALESCE(i.material_input_count, 0) > 0 AND COALESCE(mm.material_usage_movement_count, 0) = 0)
                  )";
        return $this->queryCheck(
            'POSTED_COMPONENT_BATCH_TRACE',
            'Batch component posted dengan jejak input/output tidak lengkap',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Batch harus memiliki movement output dan movement pemakaian untuk setiap jenis input yang digunakan.'
        );
    }

    private function checkPostedTransfers(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT h.id, h.transfer_no, h.transfer_date, h.from_division_id, h.from_destination_type,
                       h.to_division_id, h.to_destination_type, l.line_count,
                       COALESCE(m.transfer_out_count, 0) AS transfer_out_count,
                       COALESCE(m.transfer_in_count, 0) AS transfer_in_count
                FROM inv_stock_transfer h
                INNER JOIN (
                    SELECT transfer_id, COUNT(*) AS line_count
                    FROM inv_stock_transfer_line
                    WHERE COALESCE(qty_transfer_content, 0) > 0.0001
                    GROUP BY transfer_id
                ) l ON l.transfer_id = h.id
                LEFT JOIN (
                    SELECT ref_id,
                           SUM(CASE WHEN movement_type = 'TRANSFER_OUT' THEN 1 ELSE 0 END) AS transfer_out_count,
                           SUM(CASE WHEN movement_type = 'TRANSFER_IN' THEN 1 ELSE 0 END) AS transfer_in_count
                    FROM inv_stock_movement_log
                    WHERE ref_table = 'inv_stock_transfer'
                    GROUP BY ref_id
                ) m ON m.ref_id = h.id
                WHERE h.status = 'POSTED'
                  AND h.transfer_date >= ? AND h.transfer_date < ?
                  AND (
                    COALESCE(m.transfer_out_count, 0) < l.line_count
                    OR COALESCE(m.transfer_in_count, 0) < l.line_count
                  )";
        return $this->queryCheck(
            'POSTED_TRANSFER_TRACE',
            'Transfer posted dengan jejak keluar/masuk tidak lengkap',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Setiap line transfer harus memiliki movement keluar dan masuk.'
        );
    }

    private function checkPosCommitLines(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT c.id, c.commit_no, c.order_id, c.commit_status, c.commit_reason, c.committed_at,
                       COUNT(l.id) AS line_count,
                       SUM(CASE WHEN COALESCE(l.committed_qty, 0) > 0.0001
                                      AND COALESCE(d.deficit_count, 0) = 0
                                      AND (COALESCE(l.movement_ref_type, 'NONE') = 'NONE'
                                           OR COALESCE(l.movement_ref_id, 0) = 0)
                                THEN 1 ELSE 0 END) AS missing_movement_ref_count,
                       SUM(CASE WHEN COALESCE(l.committed_qty, 0) > 0.0001
                                      AND COALESCE(l.movement_ref_type, 'NONE') = 'INVENTORY_DEFICIT'
                                      AND COALESCE(d.deficit_count, 0) = 0
                                THEN 1 ELSE 0 END) AS orphaned_deficit_ref_count,
                       SUM(CASE WHEN COALESCE(l.reversed_qty, 0) > COALESCE(l.committed_qty, 0) + 0.0001
                                THEN 1 ELSE 0 END) AS reverse_exceeds_commit_count,
                       SUM(CASE WHEN COALESCE(l.movement_ref_id, 0) > 0
                                      OR COALESCE(l.movement_ref_type, 'NONE') NOT IN ('', 'NONE')
                                      OR COALESCE(d.deficit_count, 0) > 0
                                THEN 1 ELSE 0 END) AS persisted_trace_count
                FROM pos_stock_commit c
                INNER JOIN pos_stock_commit_line l ON l.commit_id = c.id
                LEFT JOIN (
                    SELECT source_id, source_line_id, COUNT(*) AS deficit_count
                    FROM inv_stock_deficit
                    WHERE source_table = 'pos_stock_commit'
                    GROUP BY source_id, source_line_id
                ) d ON d.source_id = c.id AND d.source_line_id = l.id
                WHERE c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
                  AND COALESCE(c.committed_at, c.created_at) >= ?
                  AND COALESCE(c.committed_at, c.created_at) < ?
                GROUP BY c.id, c.commit_no, c.order_id, c.commit_status, c.commit_reason, c.committed_at
                HAVING (
                    missing_movement_ref_count > 0
                    OR orphaned_deficit_ref_count > 0
                    OR reverse_exceeds_commit_count > 0
                )
                AND NOT (
                    c.commit_status IN ('REVERSED', 'VOID')
                    AND c.committed_at IS NULL
                    AND persisted_trace_count = 0
                )";
        return $this->queryCheck(
            'POS_COMMIT_LINE_TRACE',
            'Commit POS dengan line tanpa jejak movement atau defisit',
            'ERROR',
            $sql,
            [$month . ' 00:00:00', $nextMonth . ' 00:00:00'],
            $limit,
            'Line POS yang benar-benar mengurangi stok harus memiliki movement atau defisit yang terhubung; qty reversal tidak boleh melebihi qty yang pernah dikomit.'
        );
    }

    /**
     * A cancelled order can retain a stock-commit snapshot even when the
     * worker never posted stock. It is terminal bookkeeping, not a broken
     * movement trace, so keep it visible without treating it as an error.
     */
    private function checkPosCancelledBeforePosting(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT c.id, c.commit_no, c.order_id, c.commit_status, c.commit_reason, c.created_at,
                       COUNT(l.id) AS line_count,
                       SUM(CASE WHEN COALESCE(l.movement_ref_id, 0) > 0
                                      OR COALESCE(l.movement_ref_type, 'NONE') NOT IN ('', 'NONE')
                                      OR COALESCE(d.deficit_count, 0) > 0
                                THEN 1 ELSE 0 END) AS persisted_trace_count
                FROM pos_stock_commit c
                INNER JOIN pos_stock_commit_line l ON l.commit_id = c.id
                LEFT JOIN (
                    SELECT source_id, source_line_id, COUNT(*) AS deficit_count
                    FROM inv_stock_deficit
                    WHERE source_table = 'pos_stock_commit'
                    GROUP BY source_id, source_line_id
                ) d ON d.source_id = c.id AND d.source_line_id = l.id
                WHERE c.commit_status IN ('REVERSED', 'VOID')
                  AND c.committed_at IS NULL
                  AND c.created_at >= ?
                  AND c.created_at < ?
                GROUP BY c.id, c.commit_no, c.order_id, c.commit_status, c.commit_reason, c.created_at
                HAVING persisted_trace_count = 0";
        return $this->queryCheck(
            'POS_COMMIT_CANCELLED_BEFORE_POSTING',
            'Commit POS dibatalkan sebelum stok diposting',
            'INFO',
            $sql,
            [$month . ' 00:00:00', $nextMonth . ' 00:00:00'],
            $limit,
            'Commit ini sudah terminal sebelum membuat movement, lot, atau defisit. Tidak perlu repair stok maupun HPP.'
        );
    }

    private function checkPosFullDeficitProvisionalHpp(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT d.id AS deficit_id, d.deficit_date, d.stock_domain, d.requested_qty, d.issued_qty,
                       d.estimated_unit_cost, c.id AS commit_id, c.commit_no, l.id AS commit_line_id,
                       l.source_name_snapshot, l.committed_qty, l.unit_cost_live, l.total_cost_live,
                       ROUND(
                         COALESCE(l.committed_qty, d.requested_qty, 0)
                         * COALESCE(NULLIF(d.estimated_unit_cost, 0), l.unit_cost_live, 0),
                         6
                       ) AS expected_provisional_total
                FROM inv_stock_deficit d
                INNER JOIN pos_stock_commit c
                  ON c.id = d.source_id
                 AND d.source_table = 'pos_stock_commit'
                INNER JOIN pos_stock_commit_line l
                  ON l.id = d.source_line_id
                 AND l.commit_id = c.id
                WHERE d.deficit_date >= ? AND d.deficit_date < ?
                  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
                  AND COALESCE(d.issued_qty, 0) <= 0.0001
                  AND ABS(
                    COALESCE(l.total_cost_live, 0)
                    - (
                      COALESCE(l.committed_qty, d.requested_qty, 0)
                      * COALESCE(NULLIF(d.estimated_unit_cost, 0), l.unit_cost_live, 0)
                    )
                  ) > 0.01";
        return $this->queryCheck(
            'POS_FULL_DEFICIT_PROVISIONAL_HPP',
            'HPP sementara untuk POS defisit penuh',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'POS dengan defisit penuh harus tetap menyimpan HPP sementara untuk seluruh kebutuhan resep, bukan nol.'
        );
    }

    /**
     * A partial FIFO issue has two cost sources: actual issued lots and the
     * unresolved shortage. It is not possible to reconstruct every historical
     * FIFO allocation from this check alone, but the deficit part may never be
     * missing entirely from the POS HPP snapshot.
     */
    private function checkPosPartialDeficitProvisionalHpp(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT d.id AS deficit_id, d.deficit_date, d.stock_domain, d.requested_qty, d.issued_qty,
                       d.estimated_unit_cost, c.id AS commit_id, c.commit_no, l.id AS commit_line_id,
                       l.source_name_snapshot, l.committed_qty, l.unit_cost_live, l.total_cost_live,
                       ROUND((d.requested_qty - d.issued_qty) * d.estimated_unit_cost, 6) AS minimum_deficit_cost
                FROM inv_stock_deficit d
                INNER JOIN pos_stock_commit c
                  ON c.id = d.source_id
                 AND d.source_table = 'pos_stock_commit'
                INNER JOIN pos_stock_commit_line l
                  ON l.id = d.source_line_id
                 AND l.commit_id = c.id
                WHERE d.deficit_date >= ? AND d.deficit_date < ?
                  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
                  AND COALESCE(d.issued_qty, 0) > 0.0001
                  AND COALESCE(d.requested_qty, 0) > COALESCE(d.issued_qty, 0) + 0.0001
                  AND COALESCE(d.estimated_unit_cost, 0) > 0.000001
                  AND COALESCE(l.total_cost_live, 0) + 0.01
                      < (COALESCE(d.requested_qty, 0) - COALESCE(d.issued_qty, 0))
                        * COALESCE(d.estimated_unit_cost, 0)";
        return $this->queryCheck(
            'POS_PARTIAL_DEFICIT_PROVISIONAL_HPP',
            'HPP sementara untuk POS defisit sebagian',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'POS dengan lot hanya cukup sebagian harus tetap membawa setidaknya biaya sementara untuk bagian yang menjadi defisit.'
        );
    }

    /**
     * Only a full shortage should use the deficit itself as the main movement
     * reference. Partial shortages continue to reference their FIFO/component
     * issue, while their deficit remains linked through source_id/source_line_id.
     */
    private function checkPosFullDeficitReferenceLabel(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT d.id AS deficit_id, d.deficit_date, d.stock_domain, d.requested_qty, d.issued_qty,
                       c.id AS commit_id, c.commit_no, l.id AS commit_line_id, l.source_name_snapshot,
                       l.movement_ref_type, l.movement_ref_id, l.cost_source
                FROM inv_stock_deficit d
                INNER JOIN pos_stock_commit c
                  ON c.id = d.source_id
                 AND d.source_table = 'pos_stock_commit'
                INNER JOIN pos_stock_commit_line l
                  ON l.id = d.source_line_id
                 AND l.commit_id = c.id
                WHERE d.deficit_date >= ? AND d.deficit_date < ?
                  AND c.commit_status IN ('COMMITTED', 'PARTIAL_REVERSED', 'REVERSED')
                  AND COALESCE(d.issued_qty, 0) <= 0.0001
                  AND (
                    COALESCE(l.movement_ref_type, 'NONE') <> 'INVENTORY_DEFICIT'
                    OR COALESCE(l.movement_ref_id, 0) <> d.id
                  )";
        return $this->queryCheck(
            'POS_FULL_DEFICIT_REFERENCE_LABEL',
            'Label referensi untuk POS defisit penuh',
            'WARNING',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Line POS defisit penuh masih memakai label referensi lama. Jalankan migration POS defisit agar audit dan detail transaksi membaca sumbernya dengan jelas.'
        );
    }

    private function checkActiveDeficitArithmetic(string $month, int $limit): array
    {
        $writtenOffQtySql = $this->deficitWrittenOffQtySql();
        $sql = "SELECT id, deficit_date, stock_domain, location_scope, division_id, destination_type,
                       item_id, material_id, component_id, profile_key, requested_qty, issued_qty,
                       settled_qty, reversed_qty, {$writtenOffQtySql} AS written_off_qty, qty_remaining, status,
                       ROUND(requested_qty - issued_qty - settled_qty - reversed_qty - {$writtenOffQtySql}, 4) AS expected_remaining
                FROM inv_stock_deficit
                WHERE deficit_date >= ?
                  AND (
                    ABS(COALESCE(qty_remaining, 0) - GREATEST(0, requested_qty - issued_qty - settled_qty - reversed_qty - {$writtenOffQtySql})) > 0.0001
                    OR (status = 'OPEN' AND COALESCE(qty_remaining, 0) <= 0.0001)
                    OR (status IN ('SETTLED', 'VOID', 'WRITTEN_OFF') AND COALESCE(qty_remaining, 0) > 0.0001)
                  )";
        return $this->queryCheck(
            'ACTIVE_DEFICIT_ARITHMETIC',
            'Aritmetika defisit bulan aktif',
            'ERROR',
            $sql,
            [$month],
            $limit,
            'Sisa defisit harus selalu sama dengan kebutuhan dikurangi lot terbit, penyelesaian, pembalikan, dan penutupan administratif bila ada.'
        );
    }

    private function checkDeficitCogsFoundation(): array
    {
        $required = [
            'inv_stock_deficit_cogs_adjustment' => [
                'deficit_id', 'deficit_settlement_id', 'qty_adjusted',
                'provisional_unit_cost', 'provisional_amount',
                'actual_unit_cost', 'actual_amount', 'variance_amount',
                'recognition_date', 'status',
            ],
            'inv_stock_deficit_cogs_reversal' => [
                'cogs_adjustment_id', 'deficit_id', 'qty_reversed',
                'provisional_amount_reversed', 'actual_amount_reversed',
                'variance_amount_reversed', 'reversal_date',
            ],
        ];
        $missing = [];
        foreach ($required as $table => $columns) {
            if (!$this->ci->db->table_exists($table)) {
                $missing[] = $table;
                continue;
            }
            foreach ($columns as $column) {
                if (!$this->ci->db->field_exists($column, $table)) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        return $this->result(
            'DEFICIT_COGS_FOUNDATION',
            'Fondasi koreksi HPP defisit',
            'WARNING',
            count($missing),
            $missing,
            empty($missing)
                ? 'Fondasi koreksi HPP defisit tersedia.'
                : 'Koreksi HPP saat defisit diselesaikan belum aktif. Jalankan migration 2026-08-19c: ' . implode(', ', $missing) . '.'
        );
    }

    private function deficitCogsFoundationReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_deficit_cogs_adjustment')
            && $this->ci->db->table_exists('inv_stock_deficit_cogs_reversal')
            && $this->ci->db->field_exists('deficit_settlement_id', 'inv_stock_deficit_cogs_adjustment')
            && $this->ci->db->field_exists('cogs_adjustment_id', 'inv_stock_deficit_cogs_reversal');
    }

    /**
     * Once POS deficit is settled by a source with a known cost, one immutable
     * HPP correction must be present for the exact settlement row.
     */
    private function checkPosDeficitSettlementCogs(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT s.id AS settlement_id, s.settlement_date, s.qty_settled, s.unit_cost,
                       d.id AS deficit_id, d.deficit_date, d.stock_domain, d.source_id AS commit_id,
                       d.source_line_id AS commit_line_id, a.id AS cogs_adjustment_id
                FROM inv_stock_deficit_settlement s
                INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
                LEFT JOIN inv_stock_deficit_cogs_adjustment a ON a.deficit_settlement_id = s.id
                WHERE d.source_table = 'pos_stock_commit'
                  AND s.settlement_date >= ? AND s.settlement_date < ?
                  AND COALESCE(s.qty_settled, 0) > 0.0001
                  AND COALESCE(s.unit_cost, 0) > 0.000001
                  AND a.id IS NULL";
        return $this->queryCheck(
            'POS_DEFICIT_SETTLEMENT_COGS',
            'Penyelesaian defisit POS tanpa koreksi HPP',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Defisit POS sudah ditutup oleh barang dengan biaya yang diketahui, tetapi koreksi HPP-nya belum tercatat.'
        );
    }

    /**
     * A zero-cost settlement is operationally valid for stock quantity, but it
     * cannot create a trustworthy financial correction yet.
     */
    private function checkPosDeficitSettlementCost(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT s.id AS settlement_id, s.settlement_date, s.qty_settled, s.unit_cost,
                       d.id AS deficit_id, d.deficit_date, d.stock_domain, d.source_id AS commit_id,
                       d.source_line_id AS commit_line_id
                FROM inv_stock_deficit_settlement s
                INNER JOIN inv_stock_deficit d ON d.id = s.deficit_id
                WHERE d.source_table = 'pos_stock_commit'
                  AND s.settlement_date >= ? AND s.settlement_date < ?
                  AND COALESCE(s.qty_settled, 0) > 0.0001
                  AND COALESCE(s.unit_cost, 0) <= 0.000001";
        return $this->queryCheck(
            'POS_DEFICIT_SETTLEMENT_ZERO_COST',
            'Penyelesaian defisit POS dengan biaya nol',
            'WARNING',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Defisit sudah selesai secara jumlah, tetapi biaya barang sumber nol sehingga koreksi HPP belum dapat dibuat. Lengkapi biaya receipt/adjustment atau katalog yang sah.'
        );
    }

    private function checkDeficitCogsArithmetic(string $month, string $nextMonth, int $limit): array
    {
        $sql = "SELECT a.id AS cogs_adjustment_id, a.deficit_id, a.deficit_settlement_id,
                       a.settlement_date, a.qty_adjusted, s.qty_settled,
                       a.provisional_unit_cost, a.provisional_amount,
                       a.actual_unit_cost, a.actual_amount, a.variance_amount,
                       COALESCE(SUM(r.qty_reversed), 0) AS qty_reversed,
                       COALESCE(SUM(r.provisional_amount_reversed), 0) AS provisional_reversed,
                       COALESCE(SUM(r.actual_amount_reversed), 0) AS actual_reversed,
                       COALESCE(SUM(r.variance_amount_reversed), 0) AS variance_reversed
                FROM inv_stock_deficit_cogs_adjustment a
                INNER JOIN inv_stock_deficit_settlement s ON s.id = a.deficit_settlement_id
                LEFT JOIN inv_stock_deficit_cogs_reversal r ON r.cogs_adjustment_id = a.id
                WHERE a.settlement_date >= ? AND a.settlement_date < ?
                GROUP BY a.id, a.deficit_id, a.deficit_settlement_id, a.settlement_date,
                         a.qty_adjusted, s.qty_settled, a.provisional_unit_cost, a.provisional_amount,
                         a.actual_unit_cost, a.actual_amount, a.variance_amount
                HAVING ABS(COALESCE(a.qty_adjusted, 0) - COALESCE(s.qty_settled, 0)) > 0.0001
                    OR ABS(COALESCE(a.provisional_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.provisional_unit_cost, 0)) > 0.01
                    OR ABS(COALESCE(a.actual_amount, 0) - COALESCE(a.qty_adjusted, 0) * COALESCE(a.actual_unit_cost, 0)) > 0.01
                    OR ABS(COALESCE(a.variance_amount, 0) - (COALESCE(a.actual_amount, 0) - COALESCE(a.provisional_amount, 0))) > 0.01
                    OR COALESCE(SUM(r.qty_reversed), 0) > COALESCE(a.qty_adjusted, 0) + 0.0001
                    OR ABS(COALESCE(SUM(r.provisional_amount_reversed), 0)) > ABS(COALESCE(a.provisional_amount, 0)) + 0.01
                    OR ABS(COALESCE(SUM(r.actual_amount_reversed), 0)) > ABS(COALESCE(a.actual_amount, 0)) + 0.01
                    OR ABS(COALESCE(SUM(r.variance_amount_reversed), 0)) > ABS(COALESCE(a.variance_amount, 0)) + 0.01";
        return $this->queryCheck(
            'DEFICIT_COGS_ARITHMETIC',
            'Aritmetika koreksi dan pembalikan HPP defisit',
            'ERROR',
            $sql,
            [$month, $nextMonth],
            $limit,
            'Jumlah atau nominal koreksi HPP defisit tidak cocok dengan settlement atau pembalikannya.'
        );
    }

    private function checkNegativeActiveBalances(string $month, int $limit): array
    {
        $sql = "SELECT * FROM (
                    SELECT 'MATERIAL_DIVISION' AS source, id, month_key, division_id, destination_type,
                           item_id, material_id, profile_key, closing_qty_content AS qty
                    FROM inv_division_monthly_stock
                    WHERE month_key = ? AND closing_qty_content < -0.0001
                    UNION ALL
                    SELECT 'MATERIAL_WAREHOUSE' AS source, id, month_key, NULL AS division_id, 'GUDANG' AS destination_type,
                           item_id, material_id, profile_key, closing_qty_content AS qty
                    FROM inv_warehouse_monthly_stock
                    WHERE month_key = ? AND closing_qty_content < -0.0001
                    UNION ALL
                    SELECT 'COMPONENT' AS source, id, month_key, division_id, location_type AS destination_type,
                           NULL AS item_id, NULL AS material_id, NULL AS profile_key, closing_qty AS qty
                    FROM inv_component_monthly_stock
                    WHERE month_key = ? AND closing_qty < -0.0001
                ) negative_balance";
        return $this->queryCheck(
            'ACTIVE_NEGATIVE_MONTHLY_BALANCE',
            'Saldo bulanan negatif pada bulan aktif',
            'WARNING',
            $sql,
            [$month, $month, $month],
            $limit,
            'Saldo negatif tidak boleh dibuat oleh transaksi baru. Periksa sumber historis dan gunakan Defisit Stok/Rekonsiliasi, bukan koreksi massal.'
        );
    }

    private function checkWritesAfterClosedPeriod(int $limit): array
    {
        $sql = "SELECT * FROM (
                    SELECT 'MATERIAL' AS stock_domain, p.id AS period_id, p.period_month, p.closed_at,
                           m.id AS movement_id, m.movement_date, m.created_at, m.ref_table AS source_table, m.ref_id AS source_id
                    FROM inv_stock_period p
                    INNER JOIN inv_stock_movement_log m
                            ON m.movement_date >= p.period_month
                           AND m.movement_date < DATE_ADD(p.period_month, INTERVAL 1 MONTH)
                           AND m.created_at > p.closed_at
                    WHERE p.stock_domain = 'MATERIAL' AND p.status = 'CLOSED' AND p.closed_at IS NOT NULL
                    UNION ALL
                    SELECT 'COMPONENT' AS stock_domain, p.id AS period_id, p.period_month, p.closed_at,
                           m.id AS movement_id, m.movement_date, m.created_at, m.source_table, m.source_id
                    FROM inv_stock_period p
                    INNER JOIN inv_component_movement_log m
                            ON m.movement_date >= p.period_month
                           AND m.movement_date < DATE_ADD(p.period_month, INTERVAL 1 MONTH)
                           AND m.created_at > p.closed_at
                    WHERE p.stock_domain = 'COMPONENT' AND p.status = 'CLOSED' AND p.closed_at IS NOT NULL
                ) post_close_write";
        return $this->queryCheck(
            'CLOSED_PERIOD_WRITE',
            'Movement ditulis setelah periode ditutup',
            'ERROR',
            $sql,
            [],
            $limit,
            'Periode yang sudah ditutup tidak boleh menerima movement baru tanpa proses buka kembali resmi.'
        );
    }

    private function queryCheck(string $code, string $title, string $severity, string $sql, array $bindings, int $limit, string $issueMessage): array
    {
        $countSql = 'SELECT COUNT(*) AS total FROM (' . $sql . ') inventory_integrity_check';
        $countRow = $this->ci->db->query($countSql, $bindings)->row_array();
        $total = (int)($countRow['total'] ?? 0);
        $rows = [];
        if ($total > 0) {
            $rows = $this->ci->db->query($sql . ' LIMIT ' . max(1, $limit), $bindings)->result_array();
        }

        return $this->result(
            $code,
            $title,
            $severity,
            $total,
            $rows,
            $total > 0 ? $issueMessage : 'Tidak ditemukan masalah.'
        );
    }

    private function result(string $code, string $title, string $severity, int $issueCount, array $rows, string $message): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'severity' => $severity,
            'issue_count' => $issueCount,
            'status' => $issueCount > 0 ? $severity : 'PASS',
            'message' => $message,
            'sample_rows' => $rows,
        ];
    }

    /**
     * Older installations may not yet have the administrative write-off
     * migration. Keep the audit read-only and compatible while still using
     * the field whenever it is available.
     */
    private function deficitWrittenOffQtySql(): string
    {
        return $this->ci->db->field_exists('written_off_qty', 'inv_stock_deficit')
            ? 'COALESCE(written_off_qty, 0)'
            : '0';
    }

    private function normalizeMonth(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }
        return $date->format('Y-m-01');
    }
}
