<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * InventoryLedger
 *
 * Library terpusat untuk memastikan setiap transaksi stok menulis konsisten ke:
 * 1) inv_stock_movement_log (source of truth)
 * 2) monthly stock 31B (warehouse/division)
 * 3) snapshot bulanan sebagai cache operasional
 */
class InventoryLedger
{
    /** @var CI_Controller */
    protected $ci;
    /** @var array<string, bool> */
    protected $columnNullableCache = [];
    /** @var array<int, string>|null */
    protected $movementTypeEnumValues = null;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function post(array $payload): array
    {
        $scope = strtoupper(trim((string)($payload['movement_scope'] ?? '')));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            return [
                'ok' => false,
                'message' => 'movement_scope wajib WAREHOUSE atau DIVISION.',
            ];
        }

        if (!$this->ci->db->table_exists('inv_stock_movement_log')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_stock_movement_log belum tersedia.',
            ];
        }

        $movementDate = $this->normalizeDate((string)($payload['movement_date'] ?? date('Y-m-d')));
        if ($movementDate === null) {
            return [
                'ok' => false,
                'message' => 'movement_date tidak valid.',
            ];
        }

        $movementType = strtoupper(trim((string)($payload['movement_type'] ?? '')));
        $allowedTypes = ['PURCHASE_IN', 'TRANSFER_IN', 'TRANSFER_OUT', 'USAGE_OUT', 'DISCARDED_OUT', 'SPOIL_OUT', 'WASTE_OUT', 'PROCESS_LOSS_OUT', 'VARIANCE_OUT', 'ADJUSTMENT', 'ADJUSTMENT_IN', 'VOID_REVERSE'];
        if (!in_array($movementType, $allowedTypes, true)) {
            return [
                'ok' => false,
                'message' => 'movement_type tidak valid.',
            ];
        }
        $movementTypeForStorage = $this->normalizeMovementTypeForStorage($movementType);
        if ($movementTypeForStorage === null) {
            return [
                'ok' => false,
                'message' => 'movement_type ' . $movementType . ' belum didukung oleh skema inv_stock_movement_log saat ini.',
            ];
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        if ($scope === 'DIVISION' && $divisionId === null) {
            return [
                'ok' => false,
                'message' => 'division_id wajib diisi untuk movement_scope DIVISION.',
            ];
        }

        $destinationType = null;
        if ($scope === 'DIVISION') {
            $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
            if ($destinationType === null) {
                $destinationType = 'OTHER';
            }
        }

        $payload = $this->normalizeCanonicalPayload($payload);

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        if ($itemId === null && $materialId === null) {
            return [
                'ok' => false,
                'message' => 'item_id atau material_id wajib diisi.',
            ];
        }

        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        if ($contentUomId === null) {
            return [
                'ok' => false,
                'message' => 'content_uom_id wajib diisi.',
            ];
        }

        $qtyBuyDelta = round((float)($payload['qty_buy_delta'] ?? 0), 4);
        $qtyContentDelta = round((float)($payload['qty_content_delta'] ?? 0), 4);
        if ($qtyBuyDelta == 0.0 && $qtyContentDelta == 0.0) {
            return [
                'ok' => false,
                'message' => 'qty delta tidak boleh keduanya nol.',
            ];
        }

        $adjustmentCategory = $this->normalizeAdjustmentCategory((string)($payload['adjustment_category'] ?? ''));
        if ($adjustmentCategory === null) {
            $adjustmentCategory = $this->resolveAdjustmentCategoryFromMovement($movementType, $qtyBuyDelta, $qtyContentDelta);
        }
        $adjustmentReasonCode = $this->normalizeAdjustmentReasonCode((string)($payload['adjustment_reason_code'] ?? ''), $adjustmentCategory);
        if ($adjustmentCategory !== null && $adjustmentReasonCode === null) {
            $adjustmentReasonCode = 'other';
        }

        $manageTransaction = array_key_exists('manage_transaction', $payload) ? (bool)$payload['manage_transaction'] : true;
        if (!$manageTransaction && !$this->ci->db->trans_active()) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_TRANSACTION_REQUIRED',
                'message' => 'Caller InventoryLedger dengan manage_transaction=false wajib memiliki transaksi database aktif.',
            ];
        }
        if ($manageTransaction) {
            $this->ci->db->trans_begin();
        }

        $this->ci->load->library('InventoryPeriodGuard');
        $period = $this->ci->inventoryperiodguard->lockActivePeriodsForWrite([[
            'stock_domain' => 'MATERIAL',
            'event_date' => $movementDate,
        ]]);
        if (!($period['ok'] ?? false)) {
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return $period;
        }

        $balanceResult = ($scope === 'WAREHOUSE')
            ? $this->upsertWarehouseBalance($payload, $qtyBuyDelta, $qtyContentDelta)
            : $this->upsertDivisionBalance($payload, $divisionId, $qtyBuyDelta, $qtyContentDelta);

        if (!($balanceResult['ok'] ?? false)) {
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return $balanceResult;
        }

        $movementNo = trim((string)($payload['movement_no'] ?? ''));
        if ($movementNo === '') {
            $movementNo = $this->generateMovementNo($movementDate);
        }

        $movementData = [
            'movement_no' => $movementNo,
            'movement_date' => $movementDate,
            'movement_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $divisionId : null,
            'movement_type' => $movementTypeForStorage,
            'ref_table' => $this->nullableString($payload['ref_table'] ?? null),
            'ref_id' => $this->nullableInt($payload['ref_id'] ?? null),
            'receipt_id' => $this->nullableInt($payload['receipt_id'] ?? null),
            'receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'qty_buy_delta' => $qtyBuyDelta,
            'qty_content_delta' => $qtyContentDelta,
            'qty_buy_after' => $balanceResult['qty_buy_after'],
            'qty_content_after' => $balanceResult['qty_content_after'],
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'profile_name' => $this->nullableString($payload['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($payload['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($payload['profile_description'] ?? null),
            'profile_content_per_buy' => $this->nullableDecimal($payload['profile_content_per_buy'] ?? null, 6),
            'profile_buy_uom_code' => $this->nullableString($payload['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($payload['profile_content_uom_code'] ?? null),
            'unit_cost' => $balanceResult['unit_cost'],
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
        ];
        if ($this->ci->db->field_exists('stock_domain', 'inv_stock_movement_log')) {
            $legacyStockDomain = $this->legacyStockDomainForStorage('inv_stock_movement_log');
            if ($legacyStockDomain !== null) {
                $movementData['stock_domain'] = $legacyStockDomain;
            }
        }
        if ($this->ci->db->field_exists('profile_expired_date', 'inv_stock_movement_log')) {
            $movementData['profile_expired_date'] = $this->normalizeDate((string)($payload['profile_expired_date'] ?? ''));
        }
        if ($this->ci->db->field_exists('adjustment_category', 'inv_stock_movement_log')) {
            $movementData['adjustment_category'] = $adjustmentCategory;
        }
        if ($this->ci->db->field_exists('adjustment_reason_code', 'inv_stock_movement_log')) {
            $movementData['adjustment_reason_code'] = $adjustmentReasonCode;
        }
        if ($this->ci->db->field_exists('reversal_of_movement_id', 'inv_stock_movement_log')) {
            $movementData['reversal_of_movement_id'] = $this->nullableInt($payload['reversal_of_movement_id'] ?? null);
        }

        if ($scope === 'DIVISION' && $this->ci->db->field_exists('destination_type', 'inv_stock_movement_log')) {
            $movementData['destination_type'] = $destinationType;
        }

        $this->ci->db->insert('inv_stock_movement_log', $movementData);
        $movementId = (int)$this->ci->db->insert_id();

        if ($movementId <= 0) {
            $dbError = $this->ci->db->error();
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return [
                'ok' => false,
                'message' => 'Gagal menulis movement log: ' . $this->formatDbErrorMessage($dbError),
            ];
        }

        if ($manageTransaction) {
            if ($this->ci->db->trans_status() === false) {
                $dbError = $this->ci->db->error();
                $this->ci->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal posting inventory ledger: ' . $this->formatDbErrorMessage($dbError),
                ];
            }
            $this->ci->db->trans_commit();
        }

        $skipAvailabilityRefresh = !empty($payload['skip_availability_refresh']);
        $availabilityRefresh = null;
        if (!$skipAvailabilityRefresh && $materialId !== null) {
            $this->ci->load->library('PosAvailabilityRebuildService');
            $availabilityRefresh = $this->ci->posavailabilityrebuildservice->handle_material_change((int)$materialId, [
                'trigger_context' => 'INVENTORY_LEDGER_POST',
                'event_source' => 'INVENTORY_LEDGER_' . $movementType,
                'event_table' => 'inv_stock_movement_log',
                'event_id' => $movementId,
                // inv_stock_movement_log.created_by stores an auth user ID.
                // The queue resolves it to the employee FK when available.
                'actor_user_id' => $this->nullableInt($payload['created_by'] ?? null),
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Inventory ledger berhasil diposting.',
            'data' => [
                'movement_id' => $movementId,
                'movement_no' => $movementNo,
                'qty_buy_after' => $balanceResult['qty_buy_after'],
                'qty_content_after' => $balanceResult['qty_content_after'],
                'avg_cost_per_content' => $balanceResult['avg_cost_per_content'],
                'availability_rebuild' => $availabilityRefresh,
            ],
        ];
    }

    private function upsertWarehouseBalance(array $payload, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_warehouse_monthly_stock belum tersedia.',
            ];
        }

        return $this->upsertWarehouseMonthlyStock($payload, $qtyBuyDelta, $qtyContentDelta);
    }

    private function upsertDivisionBalance(array $payload, ?int $divisionId, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        if (!$this->ci->db->table_exists('inv_division_monthly_stock')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_division_monthly_stock belum tersedia.',
            ];
        }

        return $this->upsertDivisionMonthlyStock($payload, $divisionId, $qtyBuyDelta, $qtyContentDelta);
    }

    private function upsertWarehouseMonthlyStock(array $payload, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        return $this->upsertMonthlyStock('WAREHOUSE', $payload, $qtyBuyDelta, $qtyContentDelta, []);
    }

    private function upsertDivisionMonthlyStock(array $payload, ?int $divisionId, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        return $this->upsertMonthlyStock('DIVISION', $payload, $qtyBuyDelta, $qtyContentDelta, [
            'division_id' => $divisionId,
        ]);
    }

    private function upsertMonthlyStock(string $scope, array $payload, float $qtyBuyDelta, float $qtyContentDelta, array $context): array
    {
        $table = $scope === 'WAREHOUSE' ? 'inv_warehouse_monthly_stock' : 'inv_division_monthly_stock';
        if (!$this->ci->db->table_exists($table)) {
            return [
                'ok' => false,
                'message' => 'Tabel monthly stock belum tersedia: ' . $table,
            ];
        }

        $payload = $this->normalizeCanonicalPayload($payload);

        $movementDate = $this->normalizeDate((string)($payload['movement_date'] ?? date('Y-m-d')));
        if ($movementDate === null) {
            return [
                'ok' => false,
                'message' => 'movement_date tidak valid untuk monthly stock.',
            ];
        }

        $monthKey = date('Y-m-01', strtotime($movementDate));
        $movementType = strtoupper(trim((string)($payload['movement_type'] ?? 'ADJUSTMENT')));
        $reversalOrigin = $this->resolveReversalOriginForMonthlyDelta($payload, $movementType);
        $divisionId = $scope === 'DIVISION' ? $this->nullableInt($context['division_id'] ?? null) : null;
        $destinationType = $scope === 'DIVISION'
            ? ($this->normalizeDestinationType((string)($payload['destination_type'] ?? '')) ?? 'OTHER')
            : null;
        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);

        if ($contentUomId === null) {
            return [
                'ok' => false,
                'message' => 'content_uom_id wajib diisi untuk monthly stock.',
            ];
        }
        if ($scope === 'DIVISION' && $divisionId === null) {
            return [
                'ok' => false,
                'message' => 'division_id wajib diisi untuk monthly stock divisi.',
            ];
        }

        $profileKey = $this->nullableString($payload['profile_key'] ?? null);
        $profileName = $this->nullableString($payload['profile_name'] ?? null);
        $profileBrand = $this->nullableString($payload['profile_brand'] ?? null);
        $profileDescription = $this->nullableString($payload['profile_description'] ?? null);
        $profileExpiredDate = $this->normalizeDate((string)($payload['profile_expired_date'] ?? ''));
        $profileContentPerBuy = $this->nullableDecimal($payload['profile_content_per_buy'] ?? null, 6);
        $profileBuyUomCode = $this->nullableString($payload['profile_buy_uom_code'] ?? null);
        $profileContentUomCode = $this->nullableString($payload['profile_content_uom_code'] ?? null);
        $identityKey = $this->buildInventoryMonthlyIdentityKey([
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $profileName,
            'profile_brand' => $profileBrand,
            'profile_description' => $profileDescription,
            'profile_expired_date' => $profileExpiredDate,
            'profile_content_per_buy' => $profileContentPerBuy,
        ]);

        // The balance read and its subsequent update must be serialized per
        // monthly identity. Without this row lock, simultaneous POS/batch
        // writes can both calculate from the same closing balance and leave
        // movement.qty_*_after out of step with the monthly ledger.
        $qb = $this->ci->db->from($table)
            ->where('month_key', $monthKey)
            ->where('identity_key', $identityKey);
        if ($scope === 'DIVISION') {
            $qb->where('division_id', $divisionId)
                ->where('destination_type', $destinationType);
        }
        $existingSql = $qb->limit(1)->get_compiled_select();
        $existingQuery = $this->ci->db->query($existingSql . ' FOR UPDATE');
        $existing = $existingQuery ? ($existingQuery->row_array() ?: null) : null;
        if (!$existing) {
            $existing = $this->findLegacyMonthlyStockRow($table, $scope, [
                'month_key' => $monthKey,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_expired_date' => $profileExpiredDate,
                'profile_content_per_buy' => $profileContentPerBuy,
            ], true);
        }

        $oldQtyBuy = round((float)($existing['closing_qty_buy'] ?? 0), 4);
        $oldQtyContent = round((float)($existing['closing_qty_content'] ?? 0), 4);
        $oldAvg = round((float)($existing['avg_cost_per_content'] ?? 0), 6);

        if (($profileContentPerBuy === null || (float)$profileContentPerBuy <= 0) && !empty($existing)) {
            $profileContentPerBuy = $this->nullableDecimal($existing['profile_content_per_buy'] ?? null, 6);
        }

        $qtyBuyAfter = round($oldQtyBuy + $qtyBuyDelta, 4);
        $qtyContentAfter = round($oldQtyContent + $qtyContentDelta, 4);

        // Qty isi is the canonical stock balance. Rebuild the displayed buy-unit
        // balance from it so partial movements cannot accumulate a 0.0001 rounding
        // residue and falsely trip the negative-stock guard.
        if ($qtyContentAfter >= 0 && $profileContentPerBuy !== null && (float)$profileContentPerBuy > 0) {
            $qtyBuyAfter = round($qtyContentAfter / (float)$profileContentPerBuy, 4);
        }

        $allowNegativeBalance = !empty($payload['allow_negative_balance']);
        if (!$allowNegativeBalance && ($qtyBuyAfter < 0 || $qtyContentAfter < 0)) {
            return [
                'ok' => false,
                'message' => 'Mutasi menyebabkan saldo bulanan negatif.',
            ];
        }

        $unitCost = max(0, round((float)($payload['unit_cost'] ?? 0), 6));
        $forcedAvg = null;
        if (array_key_exists('force_avg_cost_per_content', $payload) && $payload['force_avg_cost_per_content'] !== null && $payload['force_avg_cost_per_content'] !== '') {
            $forcedAvg = max(0, round((float)$payload['force_avg_cost_per_content'], 6));
        }

        $avgAfter = $oldAvg;
        if ($qtyContentAfter <= 0) {
            $avgAfter = 0;
        } elseif ($qtyContentDelta > 0) {
            $oldValue = $oldQtyContent * $oldAvg;
            $inValue = $qtyContentDelta * $unitCost;
            $avgAfter = round(($oldValue + $inValue) / max(0.000001, $qtyContentAfter), 6);
        }
        if ($qtyContentAfter > 0 && $forcedAvg !== null) {
            $avgAfter = $forcedAvg;
        }
        if ($allowNegativeBalance && $qtyContentAfter < 0) {
            if ($forcedAvg !== null) {
                $avgAfter = $forcedAvg;
            } elseif ($oldAvg > 0) {
                $avgAfter = $oldAvg;
            } elseif ($unitCost > 0) {
                $avgAfter = $unitCost;
            }
        }

        $isOpeningSnapshotMovement = in_array((string)($payload['ref_table'] ?? ''), ['inv_warehouse_stock_opening_snapshot', 'inv_division_stock_opening_snapshot'], true);
        $movementDayCount = (int)($existing['movement_day_count'] ?? 0);
        if ($existing === null || (string)($existing['last_movement_date'] ?? '') !== $movementDate) {
            $movementDayCount++;
        }

        $mutationValueBase = max($avgAfter, $unitCost, $oldAvg, 0);
        if (array_key_exists('mutation_value_override', $payload) && $payload['mutation_value_override'] !== null && $payload['mutation_value_override'] !== '') {
            $mutationValue = round(max(0, (float)$payload['mutation_value_override']), 2);
        } else {
            $mutationValue = round(abs($qtyContentDelta) * $mutationValueBase, 2);
        }
        $delta = $this->buildMonthlyMovementDelta(
            $movementType,
            $qtyBuyDelta,
            $qtyContentDelta,
            $mutationValue,
            $this->normalizeAdjustmentCategory((string)($payload['adjustment_category'] ?? '')),
            $isOpeningSnapshotMovement,
            $reversalOrigin
        );

        $openingQtyBuy = round((float)($existing['opening_qty_buy'] ?? 0), 4);
        $openingQtyContent = round((float)($existing['opening_qty_content'] ?? 0), 4);
        $openingTotalValue = round((float)($existing['opening_total_value'] ?? 0), 2);
        if ($isOpeningSnapshotMovement) {
            $openingQtyBuy = round($openingQtyBuy + $qtyBuyDelta, 4);
            $openingQtyContent = round($openingQtyContent + $qtyContentDelta, 4);
            $openingTotalValue = round($openingTotalValue + ($qtyContentDelta * $unitCost), 2);
        }

        $baseData = [
            'month_key' => $monthKey,
            'identity_key' => $identityKey,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $profileName,
            'profile_brand' => $profileBrand,
            'profile_description' => $profileDescription,
            'profile_expired_date' => $profileExpiredDate,
            'profile_content_per_buy' => $profileContentPerBuy,
            'profile_buy_uom_code' => $profileBuyUomCode,
            'profile_content_uom_code' => $profileContentUomCode,
            'opening_qty_buy' => $openingQtyBuy,
            'opening_qty_content' => $openingQtyContent,
            'opening_total_value' => $openingTotalValue,
            'closing_qty_buy' => $qtyBuyAfter,
            'closing_qty_content' => $qtyContentAfter,
            'avg_cost_per_content' => $avgAfter,
            'total_value' => round($qtyContentAfter * $avgAfter, 2),
            'movement_day_count' => $movementDayCount,
            'mutation_count' => ((int)($existing['mutation_count'] ?? 0)) + 1,
            'last_movement_date' => $movementDate,
            'last_movement_at' => date('Y-m-d H:i:s'),
            'last_movement_table' => $this->nullableString($payload['ref_table'] ?? null),
            'last_movement_id' => $this->nullableInt($payload['ref_id'] ?? null),
            'source_mode' => 'LIVE',
            'notes' => $this->nullableString($payload['notes'] ?? ($existing['notes'] ?? null)),
        ];
        if ($this->ci->db->field_exists('stock_domain', $table)) {
            $legacyStockDomain = $this->legacyStockDomainForStorage($table);
            if ($legacyStockDomain !== null) {
                $baseData['stock_domain'] = $legacyStockDomain;
            }
        }

        if ($scope === 'DIVISION') {
            $fifoValueSync = $this->currentMonthDivisionFifoValue([
                'month_key' => $monthKey,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_key' => $profileKey,
            ]);
            if ($fifoValueSync !== null && abs($qtyContentAfter - (float)$fifoValueSync['qty']) <= 0.01) {
                $baseData['avg_cost_per_content'] = (float)$fifoValueSync['avg_cost'];
                $baseData['total_value'] = (float)$fifoValueSync['total_value'];
                $avgAfter = (float)$fifoValueSync['avg_cost'];
            }
        }

        $numericFields = [
            'in_qty_buy', 'in_qty_content', 'in_total_value',
            'out_qty_buy', 'out_qty_content', 'out_total_value',
            'discarded_qty_buy', 'discarded_qty_content', 'discarded_total_value',
            'spoil_qty_buy', 'spoil_qty_content', 'spoilage_total_value',
            'waste_qty_buy', 'waste_qty_content', 'waste_total_value',
            'process_loss_qty_buy', 'process_loss_qty_content', 'process_loss_total_value',
            'variance_qty_buy', 'variance_qty_content', 'variance_total_value',
            'adjustment_plus_qty_buy', 'adjustment_plus_qty_content', 'adjustment_plus_total_value',
            'adjustment_minus_qty_buy', 'adjustment_minus_qty_content', 'adjustment_minus_total_value',
        ];
        foreach ($numericFields as $field) {
            $baseData[$field] = round((float)($existing[$field] ?? 0) + (float)($delta[$field] ?? 0), str_ends_with($field, '_value') ? 2 : 4);
        }

        if ($scope === 'DIVISION') {
            $baseData['division_id'] = $divisionId;
            $baseData['destination_type'] = $destinationType;
        }

        $origDbDebug = $this->ci->db->db_debug;
        $this->ci->db->db_debug = false;
        if ($existing && !empty($existing['id'])) {
            $this->ci->db->where('id', (int)$existing['id'])->update($table, $baseData);
        } else {
            $this->ci->db->insert($table, $baseData);
        }
        $this->ci->db->db_debug = $origDbDebug;

        if ($this->ci->db->trans_status() === false) {
            $dbErr = $this->ci->db->error();
            return [
                'ok' => false,
                'message' => 'Gagal update monthly stock: ' . (string)($dbErr['message'] ?? 'unknown DB error'),
            ];
        }

        return [
            'ok' => true,
            'qty_buy_after' => $qtyBuyAfter,
            'qty_content_after' => $qtyContentAfter,
            'avg_cost_per_content' => $avgAfter,
            'unit_cost' => $unitCost,
            'identity_key' => $identityKey,
        ];
    }

    private function currentMonthDivisionFifoValue(array $identity): ?array
    {
        if (!$this->ci->db->table_exists('inv_material_fifo_lot')) {
            return null;
        }

        $monthKey = $this->normalizeDate((string)($identity['month_key'] ?? ''));
        $divisionId = $this->nullableInt($identity['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($identity['destination_type'] ?? ''));
        $contentUomId = $this->nullableInt($identity['content_uom_id'] ?? null);
        if ($monthKey === null || $divisionId === null || $destinationType === null || $contentUomId === null) {
            return null;
        }

        $this->ci->db
            ->select('SUM(qty_balance) AS lot_qty, SUM(qty_balance * COALESCE(unit_cost, 0)) AS lot_value', false)
            ->from('inv_material_fifo_lot')
            ->where('location_scope', 'DIVISION')
            ->where('division_id', $divisionId)
            ->where('destination_type', $destinationType)
            ->where('content_uom_id', $contentUomId)
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0)
            ->where('receipt_date >=', $monthKey)
            ->where('receipt_date <=', date('Y-m-t', strtotime($monthKey)));

        $itemId = $this->nullableInt($identity['item_id'] ?? null);
        if ($itemId !== null) {
            $this->ci->db->where('item_id', $itemId);
        } else {
            $this->ci->db->where('item_id IS NULL', null, false);
        }

        $materialId = $this->nullableInt($identity['material_id'] ?? null);
        if ($materialId !== null) {
            $this->ci->db->where('material_id', $materialId);
        } else {
            $this->ci->db->where('material_id IS NULL', null, false);
        }

        $buyUomId = $this->nullableInt($identity['buy_uom_id'] ?? null);
        if ($buyUomId !== null) {
            $this->ci->db->where('buy_uom_id', $buyUomId);
        } else {
            $this->ci->db->where('buy_uom_id IS NULL', null, false);
        }

        $profileKey = $this->nullableString($identity['profile_key'] ?? null);
        if ($profileKey !== null) {
            $this->ci->db->where('profile_key', $profileKey);
        } else {
            $this->ci->db->where('profile_key IS NULL', null, false);
        }

        $row = $this->ci->db->get()->row_array();
        $qty = round((float)($row['lot_qty'] ?? 0), 4);
        if ($qty <= 0.0001) {
            return null;
        }

        $totalValue = round((float)($row['lot_value'] ?? 0), 2);
        return [
            'qty' => $qty,
            'total_value' => $totalValue,
            'avg_cost' => round($totalValue / max(0.000001, $qty), 6),
        ];
    }

    private function findLegacyMonthlyStockRow(string $table, string $scope, array $identity, bool $forUpdate = false): ?array
    {
        $monthKey = $this->normalizeDate((string)($identity['month_key'] ?? ''));
        $contentUomId = $this->nullableInt($identity['content_uom_id'] ?? null);
        if ($monthKey === null || $contentUomId === null) {
            return null;
        }

        $qb = $this->ci->db
            ->from($table)
            ->where('month_key', $monthKey)
            ->where('content_uom_id', $contentUomId);

        if ($scope === 'DIVISION') {
            $divisionId = $this->nullableInt($identity['division_id'] ?? null);
            if ($divisionId === null) {
                return null;
            }
            $qb->where('division_id', $divisionId)
                ->where('destination_type', (string)($identity['destination_type'] ?? 'OTHER'));
        }

        $itemId = $this->nullableInt($identity['item_id'] ?? null);
        $materialId = $this->nullableInt($identity['material_id'] ?? null);
        $buyUomId = $this->nullableInt($identity['buy_uom_id'] ?? null);
        $profileKey = $this->nullableString($identity['profile_key'] ?? null);
        $profileName = $this->nullableString($identity['profile_name'] ?? null);
        $profileBrand = $this->nullableString($identity['profile_brand'] ?? null);
        $profileDescription = $this->nullableString($identity['profile_description'] ?? null);
        $profileExpiredDate = $this->normalizeDate((string)($identity['profile_expired_date'] ?? ''));
        $profileContentPerBuy = round((float)($identity['profile_content_per_buy'] ?? 0), 6);

        if ($itemId !== null) {
            $qb->where('item_id', $itemId);
        } else {
            $qb->where('item_id IS NULL', null, false);
        }
        if ($materialId !== null) {
            $qb->where('material_id', $materialId);
        } else {
            $qb->where('material_id IS NULL', null, false);
        }
        if ($buyUomId !== null) {
            $qb->where('buy_uom_id', $buyUomId);
        }

        if ($profileKey !== null) {
            $qb->where('profile_key', $profileKey);
        } else {
            $qb->where('profile_key IS NULL', null, false);
            if ($profileName !== null) {
                $qb->where('COALESCE(profile_name, \'\') = ' . $this->ci->db->escape($profileName), null, false);
            }
            if ($profileBrand !== null) {
                $qb->where('COALESCE(profile_brand, \'\') = ' . $this->ci->db->escape($profileBrand), null, false);
            }
            if ($profileDescription !== null) {
                $qb->where('COALESCE(profile_description, \'\') = ' . $this->ci->db->escape($profileDescription), null, false);
            }
            if ($profileExpiredDate !== null) {
                $qb->where('profile_expired_date', $profileExpiredDate);
            }
            if ($profileContentPerBuy > 0) {
                $qb->where('ROUND(COALESCE(profile_content_per_buy, 0), 6) = ' . $this->ci->db->escape($profileContentPerBuy), null, false);
            }
        }

        $sql = $qb
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get_compiled_select();
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $query = $this->ci->db->query($sql);
        return $query ? ($query->row_array() ?: null) : null;
    }

    private function buildInventoryMonthlyIdentityKey(array $payload): string
    {
        $profileKey = $this->nullableString($payload['profile_key'] ?? null);
        if ($profileKey !== null) {
            return $profileKey;
        }

        return hash('sha256', implode('|', [
            (string)((int)($payload['item_id'] ?? 0)),
            (string)((int)($payload['material_id'] ?? 0)),
            (string)((int)($payload['buy_uom_id'] ?? 0)),
            (string)((int)($payload['content_uom_id'] ?? 0)),
            strtoupper(trim((string)($payload['profile_name'] ?? ''))),
            strtoupper(trim((string)($payload['profile_brand'] ?? ''))),
            strtoupper(trim((string)($payload['profile_description'] ?? ''))),
            number_format((float)($payload['profile_content_per_buy'] ?? 1), 6, '.', ''),
            trim((string)($payload['profile_expired_date'] ?? '')),
        ]));
    }

    private function normalizeCanonicalPayload(array $payload): array
    {
        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        $profileKey = $this->nullableString($payload['profile_key'] ?? null);

        $catalog = $this->resolveCanonicalCatalogRow($profileKey, $itemId, $materialId, $buyUomId, $contentUomId);
        if ($itemId === null) {
            $catalogItemId = (int)($catalog['item_id'] ?? 0);
            if ($catalogItemId > 0) {
                $itemId = $catalogItemId;
            } else {
                $itemId = $this->resolveCanonicalItemIdFromMaterial($materialId, $buyUomId, $contentUomId, $profileKey);
            }
        }

        if ($materialId === null) {
            $catalogMaterialId = (int)($catalog['material_id'] ?? 0);
            if ($catalogMaterialId > 0) {
                $materialId = $catalogMaterialId;
            } else {
                $materialId = $this->resolveMaterialIdFromItemId($itemId);
            }
        }

        if ($itemId !== null && $itemId > 0 && ($buyUomId === null || $contentUomId === null || empty($payload['profile_name']))) {
            $itemRow = $this->ci->db
                ->select('buy_uom_id, content_uom_id, item_name')
                ->from('mst_item')
                ->where('id', $itemId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if ($buyUomId === null && !empty($itemRow['buy_uom_id'])) {
                $buyUomId = (int)$itemRow['buy_uom_id'];
            }
            if ($contentUomId === null && !empty($itemRow['content_uom_id'])) {
                $contentUomId = (int)$itemRow['content_uom_id'];
            }
            if (empty($payload['profile_name']) && !empty($itemRow['item_name'])) {
                $payload['profile_name'] = (string)$itemRow['item_name'];
            }
        }

        $payload['item_id'] = $itemId;
        $payload['material_id'] = $materialId;
        $payload['buy_uom_id'] = $buyUomId;
        $payload['content_uom_id'] = $contentUomId;

        if (!empty($catalog)) {
            if ($profileKey === null && !empty($catalog['profile_key'])) {
                $payload['profile_key'] = $catalog['profile_key'];
            }
            $catalogFields = [
                'profile_name' => 'catalog_name',
                'profile_brand' => 'brand_name',
                'profile_description' => 'line_description',
                'profile_buy_uom_code' => 'buy_uom_code',
                'profile_content_uom_code' => 'content_uom_code',
            ];
            foreach ($catalogFields as $target => $source) {
                $current = $this->nullableString($payload[$target] ?? null);
                $sourceValue = $this->nullableString($catalog[$source] ?? null);
                if ($current === null && $sourceValue !== null) {
                    $payload[$target] = $sourceValue;
                }
            }

            $currentContentPerBuy = $payload['profile_content_per_buy'] ?? null;
            $catalogContentPerBuy = isset($catalog['content_per_buy']) ? round((float)$catalog['content_per_buy'], 6) : null;
            if (($currentContentPerBuy === null || (float)$currentContentPerBuy <= 0) && $catalogContentPerBuy !== null && $catalogContentPerBuy > 0) {
                $payload['profile_content_per_buy'] = $catalogContentPerBuy;
            }

            if ($buyUomId === null && !empty($catalog['buy_uom_id'])) {
                $payload['buy_uom_id'] = (int)$catalog['buy_uom_id'];
            }
            if ($contentUomId === null && !empty($catalog['content_uom_id'])) {
                $payload['content_uom_id'] = (int)$catalog['content_uom_id'];
            }
        }

        return $payload;
    }

    private function resolveCanonicalCatalogRow(?string $profileKey, ?int $itemId, ?int $materialId, ?int $buyUomId, ?int $contentUomId): array
    {
        if (!$this->ci->db->table_exists('mst_purchase_catalog')) {
            return [];
        }

        $profileKey = $this->nullableString($profileKey);
        $qb = $this->ci->db
            ->select('c.profile_key, c.item_id, c.material_id, c.buy_uom_id, c.content_uom_id, c.catalog_name, c.brand_name, c.line_description, c.content_per_buy, bu.code AS buy_uom_code, cu.code AS content_uom_code, c.is_active, c.id', false)
            ->from('mst_purchase_catalog c')
            ->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left');

        $applied = false;
        if ($profileKey !== null) {
            $qb->where('c.profile_key', $profileKey);
            $applied = true;
        } elseif ($itemId !== null && $itemId > 0) {
            $qb->where('c.item_id', $itemId);
            $applied = true;
        } elseif ($materialId !== null && $materialId > 0) {
            $qb->where('c.material_id', $materialId);
            if ($buyUomId !== null && $buyUomId > 0) {
                $qb->where('c.buy_uom_id', $buyUomId);
            }
            if ($contentUomId !== null && $contentUomId > 0) {
                $qb->where('c.content_uom_id', $contentUomId);
            }
            $applied = true;
        }

        if (!$applied) {
            return [];
        }

        return $qb
            ->order_by('c.is_active', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: [];
    }

    private function resolveCanonicalItemIdFromMaterial(?int $materialId, ?int $buyUomId, ?int $contentUomId, ?string $profileKey): ?int
    {
        $catalog = $this->resolveCanonicalCatalogRow($profileKey, null, $materialId, $buyUomId, $contentUomId);
        $catalogItemId = (int)($catalog['item_id'] ?? 0);
        if ($catalogItemId > 0) {
            return $catalogItemId;
        }

        if ($materialId === null || $materialId <= 0 || !$this->ci->db->table_exists('mst_item')) {
            return null;
        }

        $row = $this->ci->db
            ->select('id')
            ->from('mst_item')
            ->where('material_id', $materialId)
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        $itemId = (int)($row['id'] ?? 0);
        return $itemId > 0 ? $itemId : null;
    }

    private function resolveMaterialIdFromItemId(?int $itemId): ?int
    {
        if ($itemId === null || $itemId <= 0 || !$this->ci->db->table_exists('mst_item')) {
            return null;
        }

        $row = $this->ci->db
            ->select('material_id')
            ->from('mst_item')
            ->where('id', $itemId)
            ->limit(1)
            ->get()
            ->row_array();

        $materialId = (int)($row['material_id'] ?? 0);
        return $materialId > 0 ? $materialId : null;
    }

    private function legacyStockDomainForStorage(string $table): ?string
    {
        if (!$this->ci->db->field_exists('stock_domain', $table)) {
            return null;
        }

        if ($this->columnAllowsNull($table, 'stock_domain')) {
            return null;
        }

        return 'ITEM';
    }

    /**
     * Compatibility shim for older call sites that still reference the
     * previous helper name/signature.
     */
    private function resolveLegacyStockDomain(...$args): ?string
    {
        if (!empty($args) && is_string($args[0]) && $args[0] !== '') {
            return $this->legacyStockDomainForStorage((string)$args[0]);
        }

        $payload = (isset($args[0]) && is_array($args[0])) ? $args[0] : [];
        $explicit = strtoupper(trim((string)($payload['stock_domain'] ?? '')));
        if (in_array($explicit, ['ITEM', 'MATERIAL'], true)) {
            return $explicit;
        }

        $itemId = $this->nullableInt($args[1] ?? ($payload['item_id'] ?? null));
        $materialId = $this->nullableInt($args[2] ?? ($payload['material_id'] ?? null));
        if ($itemId !== null) {
            return 'ITEM';
        }
        if ($materialId !== null) {
            return 'MATERIAL';
        }

        return null;
    }

    private function columnAllowsNull(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnNullableCache)) {
            return $this->columnNullableCache[$cacheKey];
        }

        $row = $this->ci->db
            ->select('IS_NULLABLE')
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->ci->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->limit(1)
            ->get()
            ->row_array();

        $this->columnNullableCache[$cacheKey] = strtoupper((string)($row['IS_NULLABLE'] ?? 'NO')) === 'YES';
        return $this->columnNullableCache[$cacheKey];
    }

    private function buildMonthlyMovementDelta(
        string $movementType,
        float $qtyBuyDelta,
        float $qtyContentDelta,
        float $mutationValue,
        ?string $adjustmentCategory,
        bool $isOpeningSnapshotMovement,
        array $reversalOrigin = []
    ): array
    {
        $delta = [
            'in_qty_buy' => 0.0,
            'in_qty_content' => 0.0,
            'in_total_value' => 0.0,
            'out_qty_buy' => 0.0,
            'out_qty_content' => 0.0,
            'out_total_value' => 0.0,
            'discarded_qty_buy' => 0.0,
            'discarded_qty_content' => 0.0,
            'discarded_total_value' => 0.0,
            'spoil_qty_buy' => 0.0,
            'spoil_qty_content' => 0.0,
            'spoilage_total_value' => 0.0,
            'waste_qty_buy' => 0.0,
            'waste_qty_content' => 0.0,
            'waste_total_value' => 0.0,
            'process_loss_qty_buy' => 0.0,
            'process_loss_qty_content' => 0.0,
            'process_loss_total_value' => 0.0,
            'variance_qty_buy' => 0.0,
            'variance_qty_content' => 0.0,
            'variance_total_value' => 0.0,
            'adjustment_plus_qty_buy' => 0.0,
            'adjustment_plus_qty_content' => 0.0,
            'adjustment_plus_total_value' => 0.0,
            'adjustment_minus_qty_buy' => 0.0,
            'adjustment_minus_qty_content' => 0.0,
            'adjustment_minus_total_value' => 0.0,
        ];

        if ($isOpeningSnapshotMovement) {
            return $delta;
        }

        if (in_array($movementType, ['PURCHASE_IN', 'TRANSFER_IN'], true)) {
            $delta['in_qty_buy'] = max(0, $qtyBuyDelta);
            $delta['in_qty_content'] = max(0, $qtyContentDelta);
            $delta['in_total_value'] = $mutationValue;
        } elseif ($movementType === 'VOID_REVERSE') {
            // Reversal of an out movement — reduces out_qty (true rollback, not a new adjustment).
            $originType = strtoupper(trim((string)($reversalOrigin['movement_type'] ?? '')));
            $originCategory = $this->normalizeAdjustmentCategory((string)($reversalOrigin['adjustment_category'] ?? ''));
            $originQtyBuy = (float)($reversalOrigin['qty_buy_delta'] ?? 0);
            $originQtyContent = (float)($reversalOrigin['qty_content_delta'] ?? 0);
            $cancelBuy = -abs($qtyBuyDelta);
            $cancelContent = -abs($qtyContentDelta);
            $cancelValue = -$mutationValue;

            if (in_array($originType, ['PURCHASE_IN', 'TRANSFER_IN'], true)) {
                $delta['in_qty_buy'] = $cancelBuy;
                $delta['in_qty_content'] = $cancelContent;
                $delta['in_total_value'] = $cancelValue;
            } elseif (in_array($originType, ['TRANSFER_OUT', 'USAGE_OUT'], true)) {
                $delta['out_qty_buy'] = $cancelBuy;
                $delta['out_qty_content'] = $cancelContent;
                $delta['out_total_value'] = $cancelValue;
            } elseif ($originType === 'DISCARDED_OUT') {
                $delta['discarded_qty_buy'] = $cancelBuy;
                $delta['discarded_qty_content'] = $cancelContent;
                $delta['discarded_total_value'] = $cancelValue;
                $delta['waste_qty_buy'] = $cancelBuy;
                $delta['waste_qty_content'] = $cancelContent;
                $delta['waste_total_value'] = $cancelValue;
            } elseif ($originType === 'SPOIL_OUT') {
                $delta['spoil_qty_buy'] = $cancelBuy;
                $delta['spoil_qty_content'] = $cancelContent;
                $delta['spoilage_total_value'] = $cancelValue;
            } elseif ($originType === 'WASTE_OUT') {
                $delta['waste_qty_buy'] = $cancelBuy;
                $delta['waste_qty_content'] = $cancelContent;
                $delta['waste_total_value'] = $cancelValue;
            } elseif ($originType === 'PROCESS_LOSS_OUT') {
                $delta['process_loss_qty_buy'] = $cancelBuy;
                $delta['process_loss_qty_content'] = $cancelContent;
                $delta['process_loss_total_value'] = $cancelValue;
            } elseif ($originType === 'VARIANCE_OUT') {
                $delta['variance_qty_buy'] = $cancelBuy;
                $delta['variance_qty_content'] = $cancelContent;
                $delta['variance_total_value'] = $cancelValue;
            } elseif ($originType === 'ADJUSTMENT_IN') {
                $delta['adjustment_plus_qty_buy'] = $cancelBuy;
                $delta['adjustment_plus_qty_content'] = $cancelContent;
                $delta['adjustment_plus_total_value'] = $cancelValue;
            } elseif ($originType === 'ADJUSTMENT') {
                $originWasPlus = $originQtyBuy > 0 || $originQtyContent > 0;
                if ($originWasPlus) {
                    $delta['adjustment_plus_qty_buy'] = $cancelBuy;
                    $delta['adjustment_plus_qty_content'] = $cancelContent;
                    $delta['adjustment_plus_total_value'] = $cancelValue;
                } else {
                    $delta['adjustment_minus_qty_buy'] = $cancelBuy;
                    $delta['adjustment_minus_qty_content'] = $cancelContent;
                    $delta['adjustment_minus_total_value'] = $cancelValue;
                }

                if ($originCategory === 'WASTE') {
                    $delta['waste_qty_buy'] = $cancelBuy;
                    $delta['waste_qty_content'] = $cancelContent;
                    $delta['waste_total_value'] = $cancelValue;
                } elseif ($originCategory === 'SPOILAGE') {
                    $delta['spoil_qty_buy'] = $cancelBuy;
                    $delta['spoil_qty_content'] = $cancelContent;
                    $delta['spoilage_total_value'] = $cancelValue;
                } elseif ($originCategory === 'PROCESS_LOSS') {
                    $delta['process_loss_qty_buy'] = $cancelBuy;
                    $delta['process_loss_qty_content'] = $cancelContent;
                    $delta['process_loss_total_value'] = $cancelValue;
                } elseif ($originCategory === 'VARIANCE') {
                    $delta['variance_qty_buy'] = $cancelBuy;
                    $delta['variance_qty_content'] = $cancelContent;
                    $delta['variance_total_value'] = $cancelValue;
                }
            } elseif ($qtyContentDelta >= 0 || ($qtyContentDelta == 0.0 && $qtyBuyDelta >= 0)) {
                // Compatibility fallback for older callers without reversal metadata.
                $delta['out_qty_buy'] = $cancelBuy;
                $delta['out_qty_content'] = $cancelContent;
                $delta['out_total_value'] = $cancelValue;
            } else {
                $delta['in_qty_buy'] = $cancelBuy;
                $delta['in_qty_content'] = $cancelContent;
                $delta['in_total_value'] = $cancelValue;
            }
        } elseif (in_array($movementType, ['TRANSFER_OUT', 'USAGE_OUT'], true)) {
            $delta['out_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['out_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['out_total_value'] = $mutationValue;
        } elseif ($movementType === 'DISCARDED_OUT') {
            $delta['discarded_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['discarded_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['discarded_total_value'] = $mutationValue;
            $delta['waste_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['waste_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['waste_total_value'] = $mutationValue;
        } elseif ($movementType === 'SPOIL_OUT') {
            $delta['spoil_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['spoil_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['spoilage_total_value'] = $mutationValue;
        } elseif ($movementType === 'WASTE_OUT') {
            $delta['waste_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['waste_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['waste_total_value'] = $mutationValue;
        } elseif ($movementType === 'PROCESS_LOSS_OUT') {
            $delta['process_loss_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['process_loss_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['process_loss_total_value'] = $mutationValue;
        } elseif ($movementType === 'VARIANCE_OUT') {
            $delta['variance_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $delta['variance_qty_content'] = abs(min(0, $qtyContentDelta));
            $delta['variance_total_value'] = $mutationValue;
        } elseif ($movementType === 'ADJUSTMENT_IN') {
            $delta['adjustment_plus_qty_buy'] = max(0, $qtyBuyDelta);
            $delta['adjustment_plus_qty_content'] = max(0, $qtyContentDelta);
            $delta['adjustment_plus_total_value'] = $mutationValue;
        } elseif ($movementType === 'ADJUSTMENT') {
            if ($qtyBuyDelta > 0 || $qtyContentDelta > 0) {
                $delta['adjustment_plus_qty_buy'] = max(0, $qtyBuyDelta);
                $delta['adjustment_plus_qty_content'] = max(0, $qtyContentDelta);
                $delta['adjustment_plus_total_value'] = $mutationValue;
            } else {
                $delta['adjustment_minus_qty_buy'] = abs(min(0, $qtyBuyDelta));
                $delta['adjustment_minus_qty_content'] = abs(min(0, $qtyContentDelta));
                $delta['adjustment_minus_total_value'] = $mutationValue;
            }
        }

        if ($movementType !== 'VOID_REVERSE' && $adjustmentCategory === 'WASTE') {
            $delta['waste_qty_buy'] = max($delta['waste_qty_buy'], abs($qtyBuyDelta));
            $delta['waste_qty_content'] = max($delta['waste_qty_content'], abs($qtyContentDelta));
            $delta['waste_total_value'] = max($delta['waste_total_value'], $mutationValue);
        } elseif ($movementType !== 'VOID_REVERSE' && $adjustmentCategory === 'SPOILAGE') {
            $delta['spoil_qty_buy'] = max($delta['spoil_qty_buy'], abs($qtyBuyDelta));
            $delta['spoil_qty_content'] = max($delta['spoil_qty_content'], abs($qtyContentDelta));
            $delta['spoilage_total_value'] = max($delta['spoilage_total_value'], $mutationValue);
        } elseif ($movementType !== 'VOID_REVERSE' && $adjustmentCategory === 'PROCESS_LOSS') {
            $delta['process_loss_qty_buy'] = max($delta['process_loss_qty_buy'], abs($qtyBuyDelta));
            $delta['process_loss_qty_content'] = max($delta['process_loss_qty_content'], abs($qtyContentDelta));
            $delta['process_loss_total_value'] = max($delta['process_loss_total_value'], $mutationValue);
        } elseif ($movementType !== 'VOID_REVERSE' && $adjustmentCategory === 'VARIANCE') {
            $delta['variance_qty_buy'] = max($delta['variance_qty_buy'], abs($qtyBuyDelta));
            $delta['variance_qty_content'] = max($delta['variance_qty_content'], abs($qtyContentDelta));
            $delta['variance_total_value'] = max($delta['variance_total_value'], $mutationValue);
        } elseif ($movementType !== 'VOID_REVERSE' && $adjustmentCategory === 'ADJUSTMENT_PLUS') {
            $delta['adjustment_plus_qty_buy'] = max($delta['adjustment_plus_qty_buy'], max(0, $qtyBuyDelta));
            $delta['adjustment_plus_qty_content'] = max($delta['adjustment_plus_qty_content'], max(0, $qtyContentDelta));
            $delta['adjustment_plus_total_value'] = max($delta['adjustment_plus_total_value'], $mutationValue);
        }

        return $delta;
    }

    /**
     * Supplies the source bucket for a reversal. New writer calls pass this
     * metadata directly; the lookup keeps the ledger safe for older callers
     * that only provide the linked movement id.
     */
    private function resolveReversalOriginForMonthlyDelta(array $payload, string $movementType): array
    {
        $origin = [
            'movement_type' => strtoupper(trim((string)($payload['reversal_of_movement_type'] ?? ''))),
            'adjustment_category' => $payload['reversal_of_adjustment_category'] ?? null,
            'qty_buy_delta' => (float)($payload['reversal_of_qty_buy_delta'] ?? 0),
            'qty_content_delta' => (float)($payload['reversal_of_qty_content_delta'] ?? 0),
        ];

        if ($movementType !== 'VOID_REVERSE' || $origin['movement_type'] !== '') {
            return $origin;
        }

        $originId = $this->nullableInt($payload['reversal_of_movement_id'] ?? null);
        if ($originId === null
            || !$this->ci->db->field_exists('reversal_of_movement_id', 'inv_stock_movement_log')) {
            return $origin;
        }

        $query = $this->ci->db
            ->select('movement_type, qty_buy_delta, qty_content_delta')
            ->from('inv_stock_movement_log')
            ->where('id', $originId)
            ->limit(1);
        if ($this->ci->db->field_exists('adjustment_category', 'inv_stock_movement_log')) {
            $query->select('adjustment_category');
        }
        $row = $query->get()->row_array() ?: [];
        if (!empty($row)) {
            $origin['movement_type'] = strtoupper(trim((string)($row['movement_type'] ?? '')));
            $origin['adjustment_category'] = $row['adjustment_category'] ?? null;
            $origin['qty_buy_delta'] = (float)($row['qty_buy_delta'] ?? 0);
            $origin['qty_content_delta'] = (float)($row['qty_content_delta'] ?? 0);
        }

        return $origin;
    }

    private function generateMovementNo(string $movementDate): string
    {
        $prefix = 'MV' . date('Ymd', strtotime($movementDate));
        $row = $this->ci->db
            ->select('movement_no')
            ->from('inv_stock_movement_log')
            ->like('movement_no', $prefix, 'after')
            ->order_by('movement_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['movement_no'])) {
            $suffix = substr((string)$row['movement_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->ci->db->where('movement_no', $no)->count_all_results('inv_stock_movement_log') > 0);

        return $no;
    }

    private function normalizeMovementTypeForStorage(string $movementType): ?string
    {
        $movementType = strtoupper(trim($movementType));
        $available = $this->getMovementTypeEnumValues();
        if (empty($available) || in_array($movementType, $available, true)) {
            return $movementType;
        }

        if ($movementType === 'VOID_REVERSE') {
            if (in_array('ADJUSTMENT_IN', $available, true)) {
                return 'ADJUSTMENT_IN';
            }
            if (in_array('ADJUSTMENT', $available, true)) {
                return 'ADJUSTMENT';
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getMovementTypeEnumValues(): array
    {
        if ($this->movementTypeEnumValues !== null) {
            return $this->movementTypeEnumValues;
        }

        $this->movementTypeEnumValues = [];
        if (!$this->ci->db->table_exists('inv_stock_movement_log')) {
            return $this->movementTypeEnumValues;
        }

        $query = $this->ci->db->query("SHOW COLUMNS FROM inv_stock_movement_log LIKE 'movement_type'");
        $row = $query ? $query->row_array() : [];
        $type = (string)($row['Type'] ?? '');
        if ($type !== '' && preg_match_all("/'([^']+)'/", $type, $matches)) {
            $this->movementTypeEnumValues = array_values(array_unique(array_map('strval', (array)$matches[1])));
        }

        return $this->movementTypeEnumValues;
    }

    private function formatDbErrorMessage(array $dbError): string
    {
        $code = isset($dbError['code']) ? (int)$dbError['code'] : 0;
        $message = trim((string)($dbError['message'] ?? ''));
        if ($code > 0 && $message !== '') {
            return '[' . $code . '] ' . $message;
        }
        if ($message !== '') {
            return $message;
        }
        if ($code > 0) {
            return 'DB error code ' . $code;
        }
        return 'unknown DB error';
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function normalizeDestinationType(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        $allowed = ['GUDANG', 'BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'OFFICE', 'OTHER'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function resolveAdjustmentCategoryFromMovement(string $movementType, float $qtyBuyDelta, float $qtyContentDelta): ?string
    {
        if (in_array($movementType, ['DISCARDED_OUT', 'WASTE_OUT'], true)) {
            return 'WASTE';
        }
        if ($movementType === 'SPOIL_OUT') {
            return 'SPOILAGE';
        }
        if ($movementType === 'PROCESS_LOSS_OUT') {
            return 'PROCESS_LOSS';
        }
        if ($movementType === 'VARIANCE_OUT') {
            return 'VARIANCE';
        }
        if ($movementType === 'ADJUSTMENT_IN') {
            return 'ADJUSTMENT_PLUS';
        }

        if ($movementType === 'VOID_REVERSE') {
            return null;
        }

        if ($movementType === 'ADJUSTMENT') {
            if ($qtyBuyDelta > 0 || $qtyContentDelta > 0) {
                return 'ADJUSTMENT_PLUS';
            }
            return 'VARIANCE';
        }

        return null;
    }

    private function normalizeAdjustmentCategory(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        $allowed = ['WASTE', 'SPOILAGE', 'PROCESS_LOSS', 'VARIANCE', 'ADJUSTMENT_PLUS'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function normalizeAdjustmentReasonCode(string $value, ?string $category): ?string
    {
        if ($category === null) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $reasonMap = [
            'WASTE' => ['cancel_order', 'kitchen_error', 'overproduction', 'spillage', 'prep_trim_excess', 'expired_opened', 'other'],
            'SPOILAGE' => ['expired', 'temperature_abuse', 'contamination', 'overstock', 'improper_storage', 'other'],
            'PROCESS_LOSS' => ['defrost_loss', 'trimming_standard', 'cooking_loss', 'evaporation', 'brew_loss', 'absorption_loss', 'process_residue', 'variable_process_consumable', 'other'],
            'VARIANCE' => ['over_usage', 'under_usage', 'unrecorded_usage', 'counting_error', 'system_mismatch', 'theft_suspected', 'unknown_shrinkage', 'other'],
            'ADJUSTMENT_PLUS' => ['opening_correction', 'stock_found', 'manual_reclass', 'other'],
        ];

        if (!isset($reasonMap[$category])) {
            return null;
        }

        return in_array($value, $reasonMap[$category], true) ? $value : 'other';
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (int)$value;
        return $v > 0 ? $v : null;
    }

    private function nullableString($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function nullableDecimal($value, int $precision): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return round((float)$value, $precision);
    }


    private function whereNullable(CI_DB_query_builder $qb, string $column, $value): void
    {
        if ($value === null) {
            $qb->where($column . ' IS NULL', null, false);
        } else {
            $qb->where($column, $value);
        }
    }
}
