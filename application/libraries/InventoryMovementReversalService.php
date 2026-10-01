<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Writes auditable inverse material movements instead of deleting history.
 *
 * A document VOID keeps the original movement intact and adds an opposite
 * VOID_REVERSE row. The document header remains the authority for business
 * and financial reports; the movement pair only restores operational stock.
 */
class InventoryMovementReversalService
{
    /** @var CI_Controller */
    private $ci;

    /** @var bool|null */
    private $hasMaterialReversalLink = null;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->ci->load->library('InventoryLedger');
    }

    public function isReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_movement_log');
    }

    /**
     * A reversal must always be traceable to its source movement. Failing
     * clearly is safer than silently writing an unlinked reversal when the
     * deployment migration has not been run yet.
     */
    public function ensureReady(): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Tabel movement bahan baku belum tersedia.'];
        }
        if (!$this->hasReversalLink()) {
            return [
                'ok' => false,
                'message' => 'Schema reversal belum siap. Jalankan migration 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql terlebih dahulu.',
            ];
        }

        return ['ok' => true];
    }

    /**
     * Reverse every original material movement belonging to a fully voided
     * document. The reverse keeps the original ref by default so daily views
     * can collapse a full document pair into a net-zero operational event.
     */
    public function reverseMaterialMovementsForSource(string $sourceTable, int $sourceId, array $options = []): array
    {
        $sourceTable = trim($sourceTable);
        if ($sourceTable === '' || $sourceId <= 0) {
            return [
                'ok' => true,
                'data' => [
                    'reversed_count' => 0,
                    'material_ids' => [],
                ],
            ];
        }
        $ready = $this->ensureReady();
        if (!($ready['ok'] ?? false)) {
            return $ready;
        }

        $query = $this->ci->db
            ->from('inv_stock_movement_log')
            ->where('ref_table', $sourceTable)
            ->where('ref_id', $sourceId)
            ->where("UPPER(COALESCE(movement_type, '')) <> 'VOID_REVERSE'", null, false)
            ->order_by('id', 'DESC')
            ->get();
        $rows = $query ? $query->result_array() : [];

        $reversedCount = 0;
        $movementIds = [];
        $materialIds = [];
        foreach ($rows as $row) {
            $reverse = $this->reverseMaterialMovementRow($row, $options + [
                'reversal_ref_table' => $sourceTable,
                'reversal_ref_id' => $sourceId,
            ]);
            if (!($reverse['ok'] ?? false)) {
                return $reverse;
            }
            if (!empty($reverse['skipped'])) {
                continue;
            }
            $reversedCount++;
            $movementId = (int)($reverse['data']['movement_id'] ?? 0);
            if ($movementId > 0) {
                $movementIds[] = $movementId;
            }
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $materialIds[$materialId] = $materialId;
            }
        }

        return [
            'ok' => true,
            'data' => [
                'reversed_count' => $reversedCount,
                'movement_ids' => $movementIds,
                'material_ids' => array_values($materialIds),
            ],
        ];
    }

    /**
     * Reverse all or part of one material movement. Partial reversals are
     * bounded by the linked reversal history when the migration is present.
     */
    public function reverseMaterialMovementRow(array $movement, array $options = []): array
    {
        $ready = $this->ensureReady();
        if (!($ready['ok'] ?? false)) {
            return $ready;
        }

        $movementId = (int)($movement['id'] ?? 0);
        $scope = strtoupper(trim((string)($movement['movement_scope'] ?? '')));
        $qtyContentOriginal = round((float)($movement['qty_content_delta'] ?? 0), 4);
        $qtyBuyOriginal = round((float)($movement['qty_buy_delta'] ?? 0), 4);
        $contentOriginalAbs = abs($qtyContentOriginal);
        $buyOriginalAbs = abs($qtyBuyOriginal);

        if ($movementId <= 0 || !in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            return ['ok' => false, 'message' => 'Movement asal tidak valid untuk pembatalan audit.'];
        }
        if ($contentOriginalAbs <= 0.0001 && $buyOriginalAbs <= 0.0001) {
            return ['ok' => true, 'skipped' => true];
        }

        $requestedContent = array_key_exists('qty_content', $options)
            ? abs(round((float)$options['qty_content'], 4))
            : $contentOriginalAbs;
        $requestedBuy = array_key_exists('qty_buy', $options)
            ? abs(round((float)$options['qty_buy'], 4))
            : $buyOriginalAbs;

        $alreadyReversed = $this->getReversedContentQty($movementId);
        $remainingContent = $contentOriginalAbs > 0.0001
            ? max(0.0, round($contentOriginalAbs - $alreadyReversed, 4))
            : 0.0;
        if ($contentOriginalAbs > 0.0001) {
            $effectiveContent = round(min($requestedContent, $remainingContent), 4);
            if ($effectiveContent <= 0.0001) {
                return ['ok' => true, 'skipped' => true, 'message' => 'Movement sudah seluruhnya dibalik sebelumnya.'];
            }
            $ratio = $contentOriginalAbs > 0 ? $effectiveContent / $contentOriginalAbs : 0.0;
            $effectiveBuy = round($buyOriginalAbs * $ratio, 4);
        } else {
            $effectiveContent = 0.0;
            $effectiveBuy = $requestedBuy;
            if ($effectiveBuy <= 0.0001) {
                return ['ok' => true, 'skipped' => true];
            }
        }

        $reverseContentDelta = $qtyContentOriginal === 0.0
            ? 0.0
            : round(-1 * ($qtyContentOriginal > 0 ? 1 : -1) * $effectiveContent, 4);
        $reverseBuyDelta = $qtyBuyOriginal === 0.0
            ? 0.0
            : round(-1 * ($qtyBuyOriginal > 0 ? 1 : -1) * $effectiveBuy, 4);

        $movementDate = trim((string)($options['movement_date'] ?? ($movement['movement_date'] ?? '')));
        if ($movementDate === '') {
            $movementDate = date('Y-m-d');
        }
        $reversalRefTable = trim((string)($options['reversal_ref_table'] ?? ($movement['ref_table'] ?? '')));
        $reversalRefId = isset($options['reversal_ref_id'])
            ? (int)$options['reversal_ref_id']
            : (int)($movement['ref_id'] ?? 0);
        $notePrefix = trim((string)($options['notes'] ?? 'Dokumen di-VOID'));
        $note = trim($notePrefix . ' | reverse movement #' . $movementId);

        $post = $this->ci->inventoryledger->post([
            'movement_scope' => $scope,
            'movement_date' => $movementDate,
            'movement_type' => 'VOID_REVERSE',
            'division_id' => !empty($movement['division_id']) ? (int)$movement['division_id'] : null,
            'destination_type' => $movement['destination_type'] ?? null,
            'ref_table' => $reversalRefTable !== '' ? $reversalRefTable : null,
            'ref_id' => $reversalRefId > 0 ? $reversalRefId : null,
            'receipt_id' => !empty($movement['receipt_id']) ? (int)$movement['receipt_id'] : null,
            'receipt_line_id' => !empty($movement['receipt_line_id']) ? (int)$movement['receipt_line_id'] : null,
            'item_id' => !empty($movement['item_id']) ? (int)$movement['item_id'] : null,
            'material_id' => !empty($movement['material_id']) ? (int)$movement['material_id'] : null,
            'buy_uom_id' => !empty($movement['buy_uom_id']) ? (int)$movement['buy_uom_id'] : null,
            'content_uom_id' => !empty($movement['content_uom_id']) ? (int)$movement['content_uom_id'] : null,
            'qty_buy_delta' => $reverseBuyDelta,
            'qty_content_delta' => $reverseContentDelta,
            'profile_key' => $movement['profile_key'] ?? null,
            'profile_name' => $movement['profile_name'] ?? null,
            'profile_brand' => $movement['profile_brand'] ?? null,
            'profile_description' => $movement['profile_description'] ?? null,
            'profile_expired_date' => $movement['profile_expired_date'] ?? null,
            'profile_content_per_buy' => $movement['profile_content_per_buy'] ?? null,
            'profile_buy_uom_code' => $movement['profile_buy_uom_code'] ?? null,
            'profile_content_uom_code' => $movement['profile_content_uom_code'] ?? null,
            // Metadata is not stored on the reverse row. It lets the ledger
            // cancel the original monthly bucket (spoil, waste, usage, etc.).
            'reversal_of_movement_type' => $movement['movement_type'] ?? null,
            'reversal_of_adjustment_category' => $movement['adjustment_category'] ?? null,
            'reversal_of_qty_buy_delta' => $movement['qty_buy_delta'] ?? null,
            'reversal_of_qty_content_delta' => $movement['qty_content_delta'] ?? null,
            'unit_cost' => round((float)($movement['unit_cost'] ?? 0), 6),
            'force_avg_cost_per_content' => round((float)($movement['unit_cost'] ?? 0), 6),
            'allow_negative_balance' => array_key_exists('allow_negative_balance', $options)
                ? (bool)$options['allow_negative_balance']
                : true,
            'reversal_of_movement_id' => $movementId,
            'notes' => $note !== '' ? $note : null,
            'created_by' => !empty($options['created_by']) ? (int)$options['created_by'] : null,
            'manage_transaction' => array_key_exists('manage_transaction', $options)
                ? (bool)$options['manage_transaction']
                : false,
            'skip_availability_refresh' => !empty($options['skip_availability_refresh']),
        ]);
        if (!($post['ok'] ?? false)) {
            return $post;
        }

        return [
            'ok' => true,
            'data' => [
                'original_movement_id' => $movementId,
                'movement_id' => (int)($post['data']['movement_id'] ?? 0),
                'qty_content_reversed' => $effectiveContent,
                'qty_buy_reversed' => $effectiveBuy,
            ],
        ];
    }

    private function getReversedContentQty(int $movementId): float
    {
        if ($movementId <= 0 || !$this->hasReversalLink()) {
            return 0.0;
        }

        $row = $this->ci->db
            ->select('COALESCE(SUM(ABS(qty_content_delta)), 0) AS total', false)
            ->from('inv_stock_movement_log')
            ->where('reversal_of_movement_id', $movementId)
            ->get()
            ->row_array();

        return round((float)($row['total'] ?? 0), 4);
    }

    private function hasReversalLink(): bool
    {
        if ($this->hasMaterialReversalLink === null) {
            $this->hasMaterialReversalLink = $this->ci->db->field_exists(
                'reversal_of_movement_id',
                'inv_stock_movement_log'
            );
        }

        return $this->hasMaterialReversalLink;
    }
}
