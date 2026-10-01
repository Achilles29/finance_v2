<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Keeps the POS HPP snapshot immutable while recording the later difference
 * between an estimated deficit cost and the actual inbound cost.
 */
class InventoryDeficitCogsService
{
    /** @var CI_Controller */
    private $ci;
    /** @var bool|null */
    private $readyCache = null;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        if ($this->readyCache === null) {
            $db = $this->ci->db;
            $this->readyCache = $db->table_exists('inv_stock_deficit_cogs_adjustment')
                && $db->table_exists('inv_stock_deficit_cogs_reversal')
                && $db->table_exists('pos_stock_commit')
                && $db->table_exists('pos_stock_commit_line')
                && $this->hasRequiredColumns('inv_stock_deficit_cogs_adjustment', [
                    'deficit_id', 'deficit_settlement_id', 'stock_commit_id',
                    'stock_commit_line_id', 'qty_adjusted', 'provisional_amount',
                    'actual_amount', 'variance_amount', 'status',
                ])
                && $this->hasRequiredColumns('inv_stock_deficit_cogs_reversal', [
                    'cogs_adjustment_id', 'deficit_id', 'qty_reversed',
                ]);
        }
        return $this->readyCache;
    }

    private function hasRequiredColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$this->ci->db->field_exists($column, $table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Creates one immutable correction for one actual deficit settlement.
     * This is deliberately limited to POS-originated deficits.
     */
    public function recordSettlement(array $deficit, array $settlement): array
    {
        if (!$this->isReady()) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Fondasi koreksi HPP defisit belum diaktifkan.'];
        }

        $deficitId = max(0, (int)($deficit['id'] ?? $settlement['deficit_id'] ?? 0));
        $settlementId = max(0, (int)($settlement['id'] ?? 0));
        $qty = round((float)($settlement['qty_settled'] ?? 0), 4);
        if ($deficitId <= 0 || $settlementId <= 0 || $qty <= 0.0001) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Data penyelesaian defisit belum cukup untuk koreksi HPP.'];
        }

        $existing = $this->ci->db
            ->select('id, variance_amount')
            ->from('inv_stock_deficit_cogs_adjustment')
            ->where('deficit_settlement_id', $settlementId)
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            return [
                'ok' => true,
                'duplicate' => true,
                'id' => (int)$existing['id'],
                'variance_amount' => round((float)($existing['variance_amount'] ?? 0), 2),
            ];
        }

        if (strtolower(trim((string)($deficit['source_table'] ?? ''))) !== 'pos_stock_commit') {
            return ['ok' => true, 'skipped' => true, 'message' => 'Defisit bukan berasal dari POS.'];
        }

        $commitId = max(0, (int)($deficit['source_id'] ?? 0));
        $commitLineId = max(0, (int)($deficit['source_line_id'] ?? 0));
        if ($commitId <= 0 || $commitLineId <= 0) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Referensi stock commit POS tidak lengkap.'];
        }

        $commitLine = $this->ci->db
            ->select('cl.id AS stock_commit_line_id, cl.commit_id AS stock_commit_id, cl.order_id, cl.order_line_id, cl.resolved_source_division_id AS operational_division_id, cl.unit_cost_live AS commit_unit_cost, c.committed_at, c.created_at AS commit_created_at, o.paid_at, o.ordered_at', false)
            ->from('pos_stock_commit_line cl')
            ->join('pos_stock_commit c', 'c.id = cl.commit_id', 'inner')
            ->join('pos_order o', 'o.id = c.order_id', 'left')
            ->where('cl.id', $commitLineId)
            ->where('cl.commit_id', $commitId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$commitLine) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Baris stock commit POS lama tidak ditemukan.'];
        }

        $actualUnitCost = round((float)($settlement['unit_cost'] ?? 0), 6);
        if ($actualUnitCost <= 0.000001) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Biaya sumber penyelesaian masih nol; koreksi HPP ditunda.'];
        }

        $provisionalUnitCost = round((float)($deficit['estimated_unit_cost'] ?? 0), 6);
        if ($provisionalUnitCost <= 0.000001) {
            $provisionalUnitCost = round((float)($commitLine['commit_unit_cost'] ?? 0), 6);
        }

        $saleDate = $this->firstDate([
            $commitLine['paid_at'] ?? null,
            $commitLine['ordered_at'] ?? null,
            $commitLine['committed_at'] ?? null,
            $commitLine['commit_created_at'] ?? null,
            $deficit['deficit_date'] ?? null,
        ]);
        $settlementDate = $this->normalizeDate((string)($settlement['settlement_date'] ?? '')) ?: date('Y-m-d');
        $recognition = $this->resolveRecognition($saleDate, $settlementDate);
        $provisionalAmount = round($qty * $provisionalUnitCost, 2);
        $actualAmount = round($qty * $actualUnitCost, 2);

        $this->ci->db->insert('inv_stock_deficit_cogs_adjustment', [
            'deficit_id' => $deficitId,
            'deficit_settlement_id' => $settlementId,
            'stock_domain' => strtoupper(trim((string)($deficit['stock_domain'] ?? 'MATERIAL'))) === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
            'order_id' => max(0, (int)($commitLine['order_id'] ?? 0)) ?: null,
            'order_line_id' => max(0, (int)($commitLine['order_line_id'] ?? 0)) ?: null,
            'stock_commit_id' => $commitId,
            'stock_commit_line_id' => $commitLineId,
            'operational_division_id' => max(0, (int)($commitLine['operational_division_id'] ?? 0)) ?: null,
            'sale_date' => $saleDate,
            'settlement_date' => $settlementDate,
            'recognition_date' => $recognition['date'],
            'recognition_period_month' => date('Y-m-01', strtotime($recognition['date'])),
            'recognition_policy' => $recognition['policy'],
            'qty_adjusted' => $qty,
            'provisional_unit_cost' => $provisionalUnitCost,
            'provisional_amount' => $provisionalAmount,
            'actual_unit_cost' => $actualUnitCost,
            'actual_amount' => $actualAmount,
            'variance_amount' => round($actualAmount - $provisionalAmount, 2),
            'status' => 'POSTED',
            'notes' => 'Koreksi HPP dari penyelesaian defisit POS #' . $deficitId . '.',
            'created_by' => $this->nullableInt($settlement['created_by'] ?? null),
        ]);
        $id = (int)$this->ci->db->insert_id();
        if ($id <= 0) {
            $error = $this->ci->db->error();
            return ['ok' => false, 'message' => 'Gagal mencatat koreksi HPP defisit: ' . (string)($error['message'] ?? 'unknown error')];
        }

        return [
            'ok' => true,
            'id' => $id,
            'recognition_date' => $recognition['date'],
            'recognition_policy' => $recognition['policy'],
            'variance_amount' => round($actualAmount - $provisionalAmount, 2),
        ];
    }

    /**
     * A later void/refund must also reverse the related HPP correction, but
     * must never invent a stock return for the old settled deficit.
     */
    public function reverseSettledBySourceQty(string $sourceTable, int $sourceId, ?int $sourceLineId, float $qty, ?int $actorUserId = null, string $note = '', array $document = []): array
    {
        if (!$this->isReady() || strtolower(trim($sourceTable)) !== 'pos_stock_commit' || $sourceId <= 0 || $sourceLineId === null || $sourceLineId <= 0 || $qty <= 0.0001) {
            return ['ok' => true, 'reversed_qty' => 0, 'skipped' => !$this->isReady()];
        }

        $rows = $this->ci->db
            ->select('a.*, COALESCE(SUM(r.qty_reversed), 0) AS reversed_qty, COALESCE(SUM(r.provisional_amount_reversed), 0) AS provisional_reversed, COALESCE(SUM(r.actual_amount_reversed), 0) AS actual_reversed, COALESCE(SUM(r.variance_amount_reversed), 0) AS variance_reversed', false)
            ->from('inv_stock_deficit_cogs_adjustment a')
            ->join('inv_stock_deficit d', 'd.id = a.deficit_id', 'inner')
            ->join('inv_stock_deficit_cogs_reversal r', 'r.cogs_adjustment_id = a.id', 'left')
            ->where('a.status', 'POSTED')
            ->where('d.source_table', $sourceTable)
            ->where('d.source_id', $sourceId)
            ->where('d.source_line_id', $sourceLineId)
            ->group_by('a.id')
            ->order_by('a.id', 'ASC')
            ->get()
            ->result_array();

        $remaining = round($qty, 4);
        $reversed = 0.0;
        $deficitReversals = [];
        foreach ($rows as $row) {
            if ($remaining <= 0.0001) {
                break;
            }
            $adjustmentId = (int)($row['id'] ?? 0);
            $available = round(max(0, (float)($row['qty_adjusted'] ?? 0) - (float)($row['reversed_qty'] ?? 0)), 4);
            $take = round(min($available, $remaining), 4);
            if ($adjustmentId <= 0 || $take <= 0.0001) {
                continue;
            }

            $documentType = strtoupper(trim((string)($document['document_type'] ?? 'POS_REVERSE')));
            $documentId = max(0, (int)($document['document_id'] ?? 0));
            $reversalKey = hash('sha256', implode('|', [$adjustmentId, $documentType, $documentId, $sourceLineId]));
            $exists = $this->ci->db->select('id')->from('inv_stock_deficit_cogs_reversal')->where('reversal_key', $reversalKey)->limit(1)->get()->row_array();
            if ($exists) {
                continue;
            }

            $isFullRemaining = abs($take - $available) <= 0.0001;
            $ratio = (float)($row['qty_adjusted'] ?? 0) > 0.0001 ? ($take / (float)$row['qty_adjusted']) : 0.0;
            $provisional = $isFullRemaining
                ? round((float)($row['provisional_amount'] ?? 0) - (float)($row['provisional_reversed'] ?? 0), 2)
                : round((float)($row['provisional_amount'] ?? 0) * $ratio, 2);
            $actual = $isFullRemaining
                ? round((float)($row['actual_amount'] ?? 0) - (float)($row['actual_reversed'] ?? 0), 2)
                : round((float)($row['actual_amount'] ?? 0) * $ratio, 2);
            $variance = $isFullRemaining
                ? round((float)($row['variance_amount'] ?? 0) - (float)($row['variance_reversed'] ?? 0), 2)
                : round((float)($row['variance_amount'] ?? 0) * $ratio, 2);

            $this->ci->db->insert('inv_stock_deficit_cogs_reversal', [
                'reversal_key' => $reversalKey,
                'cogs_adjustment_id' => $adjustmentId,
                'deficit_id' => (int)$row['deficit_id'],
                'reversal_date' => date('Y-m-d'),
                'qty_reversed' => $take,
                'provisional_amount_reversed' => $provisional,
                'actual_amount_reversed' => $actual,
                'variance_amount_reversed' => $variance,
                'source_document_type' => substr($documentType, 0, 30),
                'source_document_id' => $documentId ?: null,
                'source_document_no' => $this->nullableText($document['document_no'] ?? null, 80),
                'notes' => $this->nullableText($note, 255),
                'created_by' => $this->nullableInt($actorUserId),
            ]);
            if ((int)$this->ci->db->insert_id() <= 0) {
                $error = $this->ci->db->error();
                return ['ok' => false, 'message' => 'Gagal membalik koreksi HPP defisit: ' . (string)($error['message'] ?? 'unknown error')];
            }
            $reversed = round($reversed + $take, 4);
            $remaining = round($remaining - $take, 4);
            $deficitId = (int)($row['deficit_id'] ?? 0);
            if ($deficitId > 0) {
                $deficitReversals[$deficitId] = round(($deficitReversals[$deficitId] ?? 0) + $take, 4);
            }
        }

        return [
            'ok' => true,
            'reversed_qty' => $reversed,
            'remaining_qty' => max(0, $remaining),
            'deficit_reversals' => $deficitReversals,
        ];
    }

    private function resolveRecognition(string $saleDate, string $settlementDate): array
    {
        $sameMonth = date('Y-m', strtotime($saleDate)) === date('Y-m', strtotime($settlementDate));
        if ($sameMonth && !$this->isFinancePeriodClosed($saleDate)) {
            return ['date' => $saleDate, 'policy' => 'SALE_MONTH_OPEN'];
        }
        return ['date' => $settlementDate, 'policy' => 'SETTLEMENT_MONTH'];
    }

    private function isFinancePeriodClosed(string $date): bool
    {
        if (!$this->ci->db->table_exists('fin_period_close')) {
            return false;
        }
        $row = $this->ci->db
            ->select('status')
            ->from('fin_period_close')
            ->where('period_start <=', $date)
            ->where('period_end >=', $date)
            ->order_by('snapshot_version', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        return strtoupper(trim((string)($row['status'] ?? ''))) === 'CLOSED';
    }

    private function firstDate(array $values): string
    {
        foreach ($values as $value) {
            $date = $this->normalizeDate((string)$value);
            if ($date !== null) {
                return $date;
            }
        }
        return date('Y-m-d');
    }

    private function normalizeDate(string $value): ?string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function nullableInt($value): ?int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    private function nullableText($value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : substr($value, 0, $maxLength);
    }
}
