<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MaterialFifoManager
{
    /** @var CI_Controller */
    protected $ci;

    /** @var bool */
    protected $schemaEnsured = false;

    /** @var bool */
    protected $warehouseAggregateMode = true;

    /** @var string|null */
    protected $lastBuilderQueryError = null;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function ensureReady(): array
    {
        return $this->ensureSchema();
    }

    public function registerReceiptInboundLot(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity($payload, true);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $qtyIn = round((float)($payload['qty_content_in'] ?? 0), 4);
        if ($qtyIn <= 0) {
            return ['ok' => false, 'message' => 'qty_content_in wajib lebih besar dari nol.'];
        }

        $receiptDate = $this->normalizeDate((string)($payload['receipt_date'] ?? ($payload['movement_date'] ?? '')));
        if ($receiptDate === null) {
            return ['ok' => false, 'message' => 'receipt_date tidak valid untuk membuat lot inbound.'];
        }
        $period = $this->ensureActiveMaterialPeriod($receiptDate, 'membuat lot material inbound');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        if ($this->isWarehouseAggregateMode() && strtoupper((string)($identity['location_scope'] ?? '')) === 'WAREHOUSE') {
            $result = $this->applyLotMutation([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => $this->buildWarehouseAggregateLotNo($identity),
                'receipt_date' => $receiptDate,
                'expiry_date' => null,
                'unit_cost' => max(0, round((float)($payload['unit_cost'] ?? 0), 6)),
                'source_table' => 'WAREHOUSE_PROFILE',
                'source_id' => null,
                'source_line_id' => null,
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => null,
            ], $qtyIn, 0.0);
            return $this->settleOpenDeficitAfterInbound($result, $payload, $identity, $qtyIn, $receiptDate);
        }

        $lotNo = $this->nullableString($payload['lot_no'] ?? null);
        if ($lotNo === null) {
            $lotNo = $this->generateLotNo($receiptDate, [
                $identity['location_scope'],
                $identity['destination_type'] ?? 'GUDANG',
                $identity['division_id'] ?? 0,
                $identity['item_id'] ?? 0,
                $identity['material_id'] ?? 0,
                $identity['profile_key'] ?? '',
                $payload['receipt_id'] ?? 0,
                $payload['receipt_line_id'] ?? 0,
                $payload['source_table'] ?? '',
                $payload['source_id'] ?? 0,
                $payload['source_line_id'] ?? 0,
            ]);
        }

        $result = $this->applyLotMutation([
            'location_scope' => $identity['location_scope'],
            'division_id' => $identity['division_id'],
            'destination_type' => $identity['destination_type'],
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => $identity['expiry_date'],
            'unit_cost' => max(0, round((float)($payload['unit_cost'] ?? 0), 6)),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'receipt_id' => $this->nullableInt($payload['receipt_id'] ?? null),
            'receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($payload['parent_lot_id'] ?? null),
        ], $qtyIn, 0.0);
        return $this->settleOpenDeficitAfterInbound($result, $payload, $identity, $qtyIn, $receiptDate);
    }

    /**
     * An inbound receipt keeps its full physical quantity in stock. When the
     * caller explicitly marks it as a deficit resolver, the same source is
     * also recorded against older shortages with the exact same identity.
     */
    private function settleOpenDeficitAfterInbound(
        array $result,
        array $payload,
        array $identity,
        float $qtyIn,
        string $receiptDate
    ): array {
        if (!($result['ok'] ?? false) || empty($payload['resolve_open_deficit']) || $qtyIn <= 0.0001) {
            return $result;
        }

        $sourceTable = $this->nullableString($payload['source_table'] ?? null);
        if ($sourceTable === null || !file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
            return $result;
        }

        $this->ci->load->library('InventoryDeficitService');
        if (!$this->ci->inventorydeficitservice->isReady()) {
            return $result;
        }

        $settlement = $this->ci->inventorydeficitservice->settle([
            'stock_domain' => 'MATERIAL',
            'deficit_date' => $receiptDate,
            'location_scope' => $identity['location_scope'],
            'division_id' => $identity['division_id'],
            'destination_type' => $identity['destination_type'],
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'qty_available' => $qtyIn,
            'estimated_unit_cost' => max(0, round((float)($payload['unit_cost'] ?? 0), 6)),
            'source_module' => (string)($payload['source_module'] ?? 'INVENTORY_RECEIPT'),
            'source_table' => $sourceTable,
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
            'notes' => 'Penerimaan stok menutup defisit dengan identitas profil yang sama.',
        ]);
        if (!($settlement['ok'] ?? false)) {
            return $settlement;
        }

        $result['data'] = is_array($result['data'] ?? null) ? $result['data'] : [];
        $result['data']['deficit_settlement'] = $settlement;
        return $result;
    }

    public function transferWarehouseToDivision(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);

        if ($divisionId === null || $destinationType === null || $issueDate === null) {
            return ['ok' => false, 'message' => 'Transfer FIFO membutuhkan division_id, destination_type, dan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }
        $period = $this->ensureActiveMaterialPeriod($issueDate, 'transfer material gudang ke divisi');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'destination_type' => 'GUDANG',
            'division_id' => null,
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        if ($this->isWarehouseAggregateMode()) {
            $sync = $this->syncWarehouseAggregateLotToMonthly($identity, $issueDate);
            if (!($sync['ok'] ?? false)) {
                return $sync;
            }

            $warehouseLot = $this->findWarehouseAggregateLot($identity, true);
            $available = round((float)($warehouseLot['qty_balance'] ?? 0), 4);
            if ($available + 0.0001 < $qtyNeed) {
                return [
                    'ok' => false,
                    'message' => 'Saldo profil gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
                ];
            }

            $issueNo = $this->generateIssueNo($issueDate);
            $issueData = [
                'issue_no' => $issueNo,
                'issue_date' => $issueDate,
                'issue_datetime' => date('Y-m-d H:i:s'),
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'target_scope' => 'DIVISION',
                'target_division_id' => $divisionId,
                'target_destination_type' => $destinationType,
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'issue_qty' => $qtyNeed,
                'total_cost' => 0,
                'source_module' => $this->nullableString($payload['source_module'] ?? 'PROCUREMENT'),
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'notes' => $this->nullableString($payload['notes'] ?? null),
                'status' => 'POSTED',
            ];
            $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
            $issueId = (int)$this->ci->db->insert_id();
            if ($issueId <= 0) {
                return ['ok' => false, 'message' => 'Gagal membuat log issue gudang.'];
            }

            $lotId = (int)($warehouseLot['id'] ?? 0);
            $takeQty = $qtyNeed;
            $unitCost = max(0, round((float)($warehouseLot['unit_cost'] ?? 0), 6));
            $warehouseMutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $this->nullableInt($warehouseLot['item_id'] ?? null),
                'material_id' => $this->nullableInt($warehouseLot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($warehouseLot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($warehouseLot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($warehouseLot['profile_key'] ?? null),
                'lot_no' => (string)($warehouseLot['lot_no'] ?? $this->buildWarehouseAggregateLotNo($identity)),
                'receipt_date' => (string)($warehouseLot['receipt_date'] ?? $issueDate),
                'expiry_date' => null,
                'unit_cost' => $unitCost,
                'source_table' => $this->nullableString($warehouseLot['source_table'] ?? 'WAREHOUSE_PROFILE'),
                'source_id' => $this->nullableInt($warehouseLot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($warehouseLot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($warehouseLot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($warehouseLot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($warehouseLot['parent_lot_id'] ?? null),
            ], 0.0, $takeQty);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $divisionLotNo = $this->nullableString($payload['target_lot_no'] ?? null);
            if ($divisionLotNo === null) {
                $divisionLotNo = $this->generateDivisionTransferLotNo($issueDate, $issueNo, $divisionId, $destinationType, $identity);
            }

            $divisionMutation = $this->applyLotMutation([
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => $divisionLotNo,
                'receipt_date' => $issueDate,
                'expiry_date' => $this->normalizeDate((string)($payload['expiry_date'] ?? ($payload['profile_expired_date'] ?? ''))),
                'unit_cost' => $unitCost,
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => $lotId > 0 ? $lotId : null,
            ], $takeQty, 0.0);
            if (!($divisionMutation['ok'] ?? false)) {
                return $divisionMutation;
            }

            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0) > 0 ? (int)($divisionMutation['data']['lot_id'] ?? 0) : null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $available,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => 0,
                'target_balance_after' => round((float)($divisionMutation['data']['qty_balance'] ?? 0), 4),
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail transfer gudang.'];
            }

            $result = [
                'ok' => true,
                'message' => 'Transfer stok profil gudang ke division berhasil.',
                'data' => [
                    'issue_id' => $issueId,
                    'issue_no' => $issueNo,
                    'allocations' => [[
                        'source_lot_id' => $lotId,
                        'source_lot_no' => (string)($warehouseLot['lot_no'] ?? $this->buildWarehouseAggregateLotNo($identity)),
                        'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0),
                        'qty_content' => $takeQty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineCost,
                    ]],
                    'total_cost' => $lineCost,
                    'avg_unit_cost' => $takeQty > 0 ? round($lineCost / $takeQty, 6) : 0.0,
                ],
            ];
            $targetIdentity = array_merge($identity, [
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
            ]);
            $settlementPayload = array_merge($payload, ['unit_cost' => $unitCost]);
            return $this->settleOpenDeficitAfterInbound($result, $settlementPayload, $targetIdentity, $takeQty, $issueDate);
        }

        $coverage = $this->synchronizeWarehouseLotsFromAggregate($identity);
        if (!($coverage['ok'] ?? false)) {
            return $coverage;
        }

        $warehouseLots = $this->findOpenLots([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
        ]);

        $lotIdentity = $identity;
        if (empty($warehouseLots) && ($identity['item_id'] ?? null) !== null && ($identity['material_id'] ?? null) !== null) {
            $warehouseLots = $this->findOpenLots([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $identity['item_id'],
                'material_id' => null,
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
            ]);
            if (!empty($warehouseLots)) {
                $lotIdentity['material_id'] = null;
            }
        }

        $available = 0.0;
        foreach ($warehouseLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo FIFO gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'target_scope' => 'DIVISION',
            'target_division_id' => $divisionId,
            'target_destination_type' => $destinationType,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PROCUREMENT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log issue FIFO.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($warehouseLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $warehouseMutation = $this->applyLotMutation([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $lotIdentity['item_id'],
                'material_id' => $lotIdentity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                'lot_id' => $lotId,
            ], 0.0, $takeQty);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $divisionMutation = $this->applyLotMutation([
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => $lotId,
            ], $takeQty, 0.0);
            if (!($divisionMutation['ok'] ?? false)) {
                return $divisionMutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0) > 0 ? (int)$divisionMutation['data']['lot_id'] : null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => 0,
                'target_balance_after' => round((float)($divisionMutation['data']['qty_balance'] ?? 0), 4),
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail issue FIFO.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO issue tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        $result = [
            'ok' => true,
            'message' => 'Transfer FIFO warehouse ke division berhasil.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
        $targetIdentity = array_merge($identity, [
            'location_scope' => 'DIVISION',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
        ]);
        $settlementPayload = array_merge($payload, [
            'unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
        ]);
        return $this->settleOpenDeficitAfterInbound($result, $settlementPayload, $targetIdentity, $qtyNeed, $issueDate);
    }

    public function transferDivisionToDivision(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $fromDivisionId = $this->nullableInt($payload['division_id'] ?? null);
        $fromDestinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        $toDivisionId = $this->nullableInt($payload['target_division_id'] ?? null);
        $toDestinationType = $this->normalizeDestinationType((string)($payload['target_destination_type'] ?? ''));
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);

        if ($fromDivisionId === null || $fromDestinationType === null || $fromDestinationType === 'GUDANG' || $issueDate === null) {
            return ['ok' => false, 'message' => 'Transfer divisi membutuhkan sumber division_id, destination_type, dan issue_date yang valid.'];
        }
        if ($toDivisionId === null || $toDestinationType === null || $toDestinationType === 'GUDANG') {
            return ['ok' => false, 'message' => 'Transfer divisi membutuhkan tujuan division_id dan destination_type yang valid.'];
        }
        if ($fromDivisionId === $toDivisionId && $fromDestinationType === $toDestinationType) {
            return ['ok' => false, 'message' => 'Sumber dan tujuan transfer tidak boleh sama.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }
        $period = $this->ensureActiveMaterialPeriod($issueDate, 'transfer material antar divisi');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
            'division_id' => $fromDivisionId,
            'destination_type' => $fromDestinationType,
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $divisionLots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => false,
            'allow_any_buy_uom' => false,
            'allow_any_content_uom' => false,
            'allow_any_profile_key' => false,
        ]);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => $this->lastBuilderQueryError];
        }

        $available = 0.0;
        foreach ($divisionLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo lot sumber tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'DIVISION',
            'division_id' => $fromDivisionId,
            'destination_type' => $fromDestinationType,
            'target_scope' => 'DIVISION',
            'target_division_id' => $toDivisionId,
            'target_destination_type' => $toDestinationType,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'INVENTORY_TRANSFER'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log transfer FIFO divisi.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($divisionLots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $sourceLotPayload = [
                'lot_id' => $lotId,
                'location_scope' => 'DIVISION',
                'division_id' => $fromDivisionId,
                'destination_type' => $fromDestinationType,
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ];
            $sourceMutation = $this->applyLotMutation($sourceLotPayload, 0.0, $takeQty);
            if (!($sourceMutation['ok'] ?? false)) {
                return $sourceMutation;
            }

            $targetIdentity = [
                'location_scope' => 'DIVISION',
                'division_id' => $toDivisionId,
                'destination_type' => $toDestinationType,
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => $lotId > 0 ? $lotId : null,
            ];
            $targetBefore = $this->findLotForUpdate($targetIdentity);
            $targetBalanceBefore = round((float)($targetBefore['qty_balance'] ?? 0), 4);
            $targetMutation = $this->applyLotMutation($targetIdentity, $takeQty, 0.0);
            if (!($targetMutation['ok'] ?? false)) {
                return $targetMutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => (int)($targetMutation['data']['lot_id'] ?? 0) > 0 ? (int)($targetMutation['data']['lot_id'] ?? 0) : null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($sourceMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => $targetBalanceBefore,
                'target_balance_after' => round((float)($targetMutation['data']['qty_balance'] ?? 0), 4),
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail transfer FIFO divisi.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'target_lot_id' => (int)($targetMutation['data']['lot_id'] ?? 0),
                'target_lot_no' => (string)($targetMutation['data']['lot_no'] ?? ($lot['lot_no'] ?? '')),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
            ];

            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'Transfer FIFO divisi tidak selesai karena lot sumber tidak cukup setelah alokasi.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        $result = [
            'ok' => true,
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];

        // A posted inter-division transfer is a real inbound source for the
        // target. Keep the transferred stock intact, but let it settle only
        // an older deficit with the exact same stock identity at that target.
        $targetDeficitIdentity = array_merge($identity, [
            'location_scope' => 'DIVISION',
            'division_id' => $toDivisionId,
            'destination_type' => $toDestinationType,
        ]);
        return $this->settleOpenDeficitAfterInbound(
            $result,
            [
                'resolve_open_deficit' => true,
                'unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
                'source_module' => 'INVENTORY_TRANSFER',
                'source_table' => 'inv_material_fifo_issue_log',
                'source_id' => $issueId,
                'source_line_id' => null,
                'created_by' => $this->nullableInt($payload['created_by'] ?? null),
            ],
            $targetDeficitIdentity,
            $qtyNeed,
            $issueDate
        );
    }

    public function consumeWarehouseUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);
        if ($issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian FIFO gudang membutuhkan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }
        $period = $this->ensureActiveMaterialPeriod($issueDate, 'pemakaian lot material gudang');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
        ]), true);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        if ($this->isWarehouseAggregateMode()) {
            $sync = $this->syncWarehouseAggregateLotToMonthly($identity, $issueDate);
            if (!($sync['ok'] ?? false)) {
                return $sync;
            }

            $warehouseLot = $this->findWarehouseAggregateLot($identity, true);
            $available = round((float)($warehouseLot['qty_balance'] ?? 0), 4);
            if ($available + 0.0001 < $qtyNeed) {
                return [
                    'ok' => false,
                    'message' => 'Saldo profil gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
                ];
            }

            $issueNo = $this->generateIssueNo($issueDate);
            $issueData = [
                'issue_no' => $issueNo,
                'issue_date' => $issueDate,
                'issue_datetime' => date('Y-m-d H:i:s'),
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'target_scope' => null,
                'target_division_id' => null,
                'target_destination_type' => null,
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'issue_qty' => $qtyNeed,
                'total_cost' => 0,
                'source_module' => $this->nullableString($payload['source_module'] ?? 'INVENTORY_ADJUSTMENT'),
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'notes' => $this->nullableString($payload['notes'] ?? null),
                'status' => 'POSTED',
            ];
            $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
            $issueId = (int)$this->ci->db->insert_id();
            if ($issueId <= 0) {
                return ['ok' => false, 'message' => 'Gagal membuat log usage gudang.'];
            }

            $lotId = (int)($warehouseLot['id'] ?? 0);
            $unitCost = max(0, round((float)($warehouseLot['unit_cost'] ?? 0), 6));
            $warehouseMutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $this->nullableInt($warehouseLot['item_id'] ?? null),
                'material_id' => $this->nullableInt($warehouseLot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($warehouseLot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($warehouseLot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($warehouseLot['profile_key'] ?? null),
                'lot_no' => (string)($warehouseLot['lot_no'] ?? $this->buildWarehouseAggregateLotNo($identity)),
                'receipt_date' => (string)($warehouseLot['receipt_date'] ?? $issueDate),
                'expiry_date' => null,
                'unit_cost' => $unitCost,
                'source_table' => $this->nullableString($warehouseLot['source_table'] ?? 'WAREHOUSE_PROFILE'),
                'source_id' => $this->nullableInt($warehouseLot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($warehouseLot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($warehouseLot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($warehouseLot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($warehouseLot['parent_lot_id'] ?? null),
            ], 0.0, $qtyNeed);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $lineCost = round($qtyNeed * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => null,
                'qty_out' => $qtyNeed,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $available,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => null,
                'target_balance_after' => null,
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail usage gudang.'];
            }

            return [
                'ok' => true,
                'message' => 'Pemakaian stok profil gudang berhasil diposting.',
                'data' => [
                    'issue_id' => $issueId,
                    'issue_no' => $issueNo,
                    'allocations' => [[
                        'source_lot_id' => $lotId,
                        'source_lot_no' => (string)($warehouseLot['lot_no'] ?? $this->buildWarehouseAggregateLotNo($identity)),
                        'qty_content' => $qtyNeed,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineCost,
                    ]],
                    'total_cost' => $lineCost,
                    'avg_unit_cost' => $qtyNeed > 0 ? round($lineCost / $qtyNeed, 6) : 0.0,
                ],
            ];
        }

        $coverage = $this->synchronizeWarehouseLotsFromAggregate($identity);
        if (!($coverage['ok'] ?? false)) {
            return $coverage;
        }

        $warehouseLots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => ($identity['item_id'] ?? null) === null && ($identity['material_id'] ?? null) !== null,
            'allow_any_buy_uom' => ($identity['buy_uom_id'] ?? null) === null,
            'allow_any_profile_key' => ($identity['profile_key'] ?? null) === null,
        ]);

        $available = 0.0;
        foreach ($warehouseLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo FIFO gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'target_scope' => null,
            'target_division_id' => null,
            'target_destination_type' => null,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'INVENTORY_ADJUSTMENT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log usage FIFO gudang.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($warehouseLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $warehouseMutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ], 0.0, $takeQty);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => null,
                'target_balance_after' => null,
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail usage FIFO gudang.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_lot' => [
                    'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? ''),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                ],
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO usage gudang tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        return [
            'ok' => true,
            'message' => 'Pemakaian FIFO gudang berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
    }

    public function consumeDivisionUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);
        $requestedQty = $qtyNeed;
        $allowPartialIssue = !empty($payload['allow_partial_issue']);

        if ($divisionId === null || $destinationType === null || $destinationType === 'GUDANG' || $issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian FIFO divisi membutuhkan division_id, destination_type, dan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }
        $period = $this->ensureActiveMaterialPeriod($issueDate, 'pemakaian lot material divisi');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }
        $identity['reference_date'] = $issueDate;

        $sourceModule = strtoupper(trim((string)($payload['source_module'] ?? '')));
        // POS and production consume the physical material, not a catalog profile.
        // Keep this explicit so a future caller cannot accidentally make either
        // transaction profile-strict and create a deficit while another valid lot exists.
        $forceCrossProfile = !empty($payload['force_cross_profile_fifo'])
            || in_array($sourceModule, ['POS', 'PRODUCTION_BATCH'], true);
        $strictProfileConsumption = !$forceCrossProfile && (
            !empty($payload['strict_profile_fifo'])
            || $sourceModule === 'INVENTORY_ADJUSTMENT'
        );
        $allowCrossProfile = !$strictProfileConsumption;

        $broadSearchOptions = [
            'allow_any_item_id' => true,
            'allow_any_buy_uom' => true,
            'allow_any_content_uom' => true,
            'allow_any_profile_key' => $allowCrossProfile,
        ];

        // Consumption is material-centric: find ALL lots for this material regardless of which
        // purchase catalog (profile_key / item_id) they came from. Profile-key filtering is only
        // meaningful for inbound receipts; for outbound consumption the primary key is material_id.
        // Exception: inventory adjustment / daily recon is profile-level. It must not consume lots
        // from another profile, otherwise FIFO changes while monthly_stock for that other profile
        // is not reduced.
        $hasMaterialId = ($identity['material_id'] ?? null) !== null;
        $hasItemId = ($identity['item_id'] ?? null) !== null;
        $hasCrossProfileIdentity = $hasMaterialId || $hasItemId;
        $divisionLots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id'  => $allowCrossProfile && $hasMaterialId,
            'allow_any_buy_uom'  => ($identity['buy_uom_id'] ?? null) === null
                || ($allowCrossProfile && $hasCrossProfileIdentity),
            'allow_any_profile_key' => $allowCrossProfile && $hasCrossProfileIdentity,
        ]);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => $this->lastBuilderQueryError];
        }

        // Broad fallback (also relax content_uom) when the above still finds nothing.
        if (empty($divisionLots) && $hasMaterialId && $allowCrossProfile) {
            $divisionLots = $this->findIssueSourceLots($identity, $broadSearchOptions);
            if ($this->lastBuilderQueryError !== null) {
                return ['ok' => false, 'message' => $this->lastBuilderQueryError];
            }
        }

        $available = 0.0;
        foreach ($divisionLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);

        // Broad fallback when strict search finds some lots but total balance is still insufficient.
        // This covers the case where stock came from multiple purchase batches with different
        // profile_key / item_id values, so only one batch was visible in the strict search.
        if ($available + 0.0001 < $qtyNeed && !empty($divisionLots) && ($identity['material_id'] ?? null) !== null && $allowCrossProfile) {
            $broadLots = $this->findIssueSourceLots($identity, $broadSearchOptions);
            if ($this->lastBuilderQueryError !== null) {
                return ['ok' => false, 'message' => $this->lastBuilderQueryError];
            }
            $broadAvailable = 0.0;
            foreach ($broadLots as $lot) {
                $broadAvailable += round((float)($lot['qty_balance'] ?? 0), 4);
            }
            $broadAvailable = round($broadAvailable, 4);
            if ($broadAvailable > $available) {
                $divisionLots = $broadLots;
                $available = $broadAvailable;
            }
        }

        if ($available + 0.0001 < $qtyNeed) {
            if ($allowPartialIssue && $available > 0.0001) {
                $qtyNeed = $available;
            } else {
                return [
                    'ok' => false,
                    'message' => 'Saldo FIFO divisi tidak cukup. Dibutuhkan ' . number_format($requestedQty, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
                ];
            }
        }

        if ($qtyNeed <= 0.0001) {
            return [
                'ok' => false,
                'message' => 'Tidak ada saldo FIFO divisi yang dapat dipakai.',
            ];
        }

        // Per-profile balance pre-check: ensure that consuming from these lots will not cause
        // any individual profile_key's monthly stock row to go negative. This catches the
        // "crossed-profile" mismatch (all lots are profile B but monthly stock B < consumption).
        if (($identity['material_id'] ?? null) !== null && $this->ci->db->table_exists('inv_division_monthly_stock')) {
            $plannedByProfile = [];
            $tempRem = $qtyNeed;
            foreach ($divisionLots as $lot) {
                if ($tempRem <= 0.0001) { break; }
                $lb = round((float)($lot['qty_balance'] ?? 0), 4);
                if ($lb <= 0) { continue; }
                $take = round(min($tempRem, $lb), 4);
                $pk   = (string)($lot['profile_key'] ?? '');
                $plannedByProfile[$pk] = round(($plannedByProfile[$pk] ?? 0.0) + $take, 4);
                $tempRem = round($tempRem - $take, 4);
            }

            if (!empty($plannedByProfile)) {
                $divId = (int)$divisionId;
                $matId = (int)$identity['material_id'];
                $destT = (string)$destinationType;

                $latestMonthSub = "SELECT ms2.division_id, ms2.destination_type, ms2.identity_key, MAX(ms2.month_key) AS max_month
                                   FROM inv_division_monthly_stock ms2
                                   WHERE ms2.division_id = {$divId} AND ms2.material_id = {$matId}
                                   GROUP BY ms2.division_id, ms2.destination_type, ms2.identity_key";

                $stockRows = $this->ci->db->query("
                    SELECT COALESCE(ms.profile_key, '') AS profile_key, SUM(ms.closing_qty_content) AS stock_balance
                    FROM inv_division_monthly_stock ms
                    INNER JOIN ({$latestMonthSub}) lm
                        ON  lm.division_id      = ms.division_id
                        AND lm.destination_type = ms.destination_type
                        AND lm.identity_key     = ms.identity_key
                        AND lm.max_month        = ms.month_key
                    WHERE ms.division_id = {$divId} AND ms.material_id = {$matId}
                      AND ms.destination_type = ?
                    GROUP BY ms.profile_key
                ", [$destT])->result_array();

                $stockByProfile = [];
                foreach ($stockRows as $sr) {
                    $pk = (string)($sr['profile_key'] ?? '');
                    $stockByProfile[$pk] = round((float)($sr['stock_balance'] ?? 0), 4);
                }

                // Only run check when monthly stock data exists for this material/destination
                if (!empty($stockByProfile)) {
                    $profileErrors = [];
                    foreach ($plannedByProfile as $pk => $plannedQty) {
                        $stockBal = $stockByProfile[$pk] ?? 0.0;
                        if ($stockBal - $plannedQty < -0.01) {
                            $pkLabel = $pk !== '' ? substr($pk, 0, 8) . '…' : '(no profile)';
                            $profileErrors[] = "profil {$pkLabel}: stok " . number_format($stockBal, 4, '.', '') . ', diambil ' . number_format($plannedQty, 4, '.', '');
                        }
                    }
                    if (!empty($profileErrors)) {
                        return [
                            'ok' => false,
                            'message' => 'Stok per profil tidak mencukupi (' . implode('; ', $profileErrors) . '). Jalankan Lot Repair di halaman rekonsiliasi terlebih dahulu.',
                            'profile_mismatch' => true,
                        ];
                    }
                }
            }
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'DIVISION',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'target_scope' => null,
            'target_division_id' => null,
            'target_destination_type' => null,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PRODUCTION_BATCH'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log usage FIFO divisi.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($divisionLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $lotPayload = [
                'lot_id' => $lotId,
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ];
            $divisionMutation = $this->applyLotMutation($lotPayload, 0.0, $takeQty);

            // Concurrent depletion: re-read the locked balance and take only what's left.
            if (!($divisionMutation['ok'] ?? false)) {
                $freshLot = $this->findLotById($lotId, true);
                $freshBalance = $freshLot ? round((float)($freshLot['qty_balance'] ?? 0), 4) : 0.0;
                if ($freshBalance <= 0) {
                    continue; // Lot was fully consumed concurrently; skip to next lot.
                }
                $takeQty = round(min($remaining, $freshBalance), 4);
                if ($takeQty <= 0) {
                    continue;
                }
                $divisionMutation = $this->applyLotMutation($lotPayload, 0.0, $takeQty);
                if (!($divisionMutation['ok'] ?? false)) {
                    return $divisionMutation; // Still failing — propagate error.
                }
                $lotBalance = $freshBalance;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($divisionMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => null,
                'target_balance_after' => null,
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail usage FIFO divisi.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_lot' => [
                    'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? ''),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                ],
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO usage divisi tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        $usedProfileKeys = [];
        foreach ($allocations as $allocation) {
            $profileKey = trim((string)($allocation['source_lot']['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $usedProfileKeys[$profileKey] = true;
            }
        }

        return [
            'ok' => true,
            'message' => 'Pemakaian FIFO divisi berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
                'requested_qty' => $requestedQty,
                'issued_qty' => $qtyNeed,
                'deficit_qty' => round(max(0, $requestedQty - $qtyNeed), 4),
                'is_partial' => $allowPartialIssue && $requestedQty > $qtyNeed + 0.0001,
                'cross_profile_fifo' => $allowCrossProfile,
                'used_lot_count' => count($allocations),
                'used_profile_count' => count($usedProfileKeys),
                'used_profile_keys' => array_keys($usedProfileKeys),
            ],
        ];
    }

    /**
     * Make FIFO lots represent an already-posted authoritative stock balance.
     *
     * This is only for a physical count/reconciliation. It never writes a
     * second stock movement; the normal ledger writer owns the stock quantity.
     * Any structural lot change is recorded in inv_stock_cutoff_event.
     */
    public function reconcileLotsToAuthoritativeBalance(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $scope = strtoupper(trim((string)($payload['location_scope'] ?? '')));
        if (!in_array($scope, ['DIVISION', 'WAREHOUSE'], true)) {
            return ['ok' => false, 'message' => 'Scope lot material untuk rekonsiliasi tidak valid.'];
        }

        $eventDate = $this->normalizeDate((string)($payload['event_date'] ?? $payload['movement_date'] ?? ''));
        $targetQty = round(max(0, (float)($payload['target_qty_content'] ?? 0)), 4);
        $sourceTable = trim((string)($payload['source_table'] ?? ''));
        if ($eventDate === null || $sourceTable === '') {
            return ['ok' => false, 'message' => 'Rekonsiliasi lot material membutuhkan tanggal dan sumber dokumen.'];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $this->nullableInt($payload['division_id'] ?? null) : null,
            'destination_type' => $scope === 'DIVISION'
                ? $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''))
                : 'GUDANG',
        ]), true);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }
        if ($scope === 'DIVISION' && ($identity['division_id'] ?? null) === null) {
            return ['ok' => false, 'message' => 'Rekonsiliasi lot divisi membutuhkan divisi yang valid.'];
        }
        $identity['reference_date'] = $eventDate;

        $lots = $this->findIssueSourceLots($identity, []);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => $this->lastBuilderQueryError];
        }

        $lotQtyBefore = 0.0;
        foreach ($lots as $lot) {
            $lotQtyBefore = round($lotQtyBefore + max(0, (float)($lot['qty_balance'] ?? 0)), 4);
        }
        $gap = round($lotQtyBefore - $targetQty, 4);
        if (abs($gap) <= 0.0001) {
            return [
                'ok' => true,
                'data' => ['action' => 'NONE', 'lot_qty_before' => $lotQtyBefore, 'target_qty_content' => $targetQty],
            ];
        }

        $this->ci->load->library('InventoryCutoffAudit');
        if (!$this->ci->inventorycutoffaudit->isReady()) {
            return ['ok' => false, 'message' => 'Audit koreksi lot belum tersedia. Jalankan SQL inventory foundation terlebih dahulu.'];
        }

        $unitCost = max(0, round((float)($payload['unit_cost'] ?? 0), 6));
        $notePrefix = 'Rekonsiliasi hitung fisik: lot disamakan dengan saldo stok otoritatif.';
        $operatorNote = trim((string)($payload['notes'] ?? ''));
        if ($operatorNote !== '') {
            $notePrefix .= ' Catatan operator: ' . substr($operatorNote, 0, 120);
        }

        if ($gap > 0) {
            $remaining = $gap;
            $changedLots = 0;
            // Use LIFO only for the structural correction so existing FIFO order stays intact.
            foreach (array_reverse($lots) as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $lotId = (int)($lot['id'] ?? 0);
                $lotQty = round(max(0, (float)($lot['qty_balance'] ?? 0)), 4);
                if ($lotId <= 0 || $lotQty <= 0.0001) {
                    continue;
                }
                $qty = round(min($remaining, $lotQty), 4);
                $lotMutation = $this->applyLotMutation([
                    'lot_id' => $lotId,
                    'location_scope' => $scope,
                    'division_id' => $identity['division_id'] ?? null,
                    'destination_type' => $identity['destination_type'] ?? null,
                    'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? $eventDate),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                    'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? $unitCost), 6)),
                    'source_table' => $this->nullableString($lot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                    'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                    'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                ], 0.0, $qty);
                if (!($lotMutation['ok'] ?? false)) {
                    return $lotMutation;
                }
                $audit = $this->ci->inventorycutoffaudit->record([
                    'stock_domain' => 'MATERIAL',
                    'event_date' => $eventDate,
                    'location_scope' => $scope,
                    'division_id' => $identity['division_id'] ?? null,
                    'destination_type' => $identity['destination_type'] ?? null,
                    'item_id' => $identity['item_id'] ?? null,
                    'material_id' => $identity['material_id'] ?? null,
                    'content_uom_id' => $identity['content_uom_id'] ?? null,
                    'profile_key' => $identity['profile_key'] ?? null,
                    'lot_id' => $lotId,
                    'direction' => 'OUT',
                    'qty' => $qty,
                    'unit_cost' => round((float)($lot['unit_cost'] ?? $unitCost), 6),
                    'source_table' => $sourceTable,
                    'source_id' => $payload['source_id'] ?? null,
                    'source_line_id' => $payload['source_line_id'] ?? null,
                    'notes' => $notePrefix . ' Lot berlebih dikurangi.',
                    'created_by' => $payload['created_by'] ?? null,
                ]);
                if (!($audit['ok'] ?? false)) {
                    return $audit;
                }
                $remaining = round($remaining - $qty, 4);
                $changedLots++;
            }
            if ($remaining > 0.0001) {
                return ['ok' => false, 'message' => 'Lot tidak cukup untuk menyamakan hasil hitung fisik.'];
            }

            return [
                'ok' => true,
                'data' => [
                    'action' => 'LOT_DECREASE',
                    'lot_qty_before' => $lotQtyBefore,
                    'target_qty_content' => $targetQty,
                    'qty_changed' => $gap,
                    'lot_count' => $changedLots,
                ],
            ];
        }

        $qtyIn = abs($gap);
        $correctionLotNo = 'RECON-' . date('Ymd', strtotime($eventDate))
            . '-M' . (int)($identity['material_id'] ?? 0)
            . '-D' . (int)($identity['division_id'] ?? 0)
            . '-S' . (int)($payload['source_line_id'] ?? 0);
        $inbound = $this->registerReceiptInboundLot(array_merge($identity, [
            'receipt_date' => $eventDate,
            'movement_date' => $eventDate,
            'qty_content_in' => $qtyIn,
            'unit_cost' => $unitCost,
            'lot_no' => $correctionLotNo,
            'source_table' => $sourceTable,
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
        ]));
        if (!($inbound['ok'] ?? false)) {
            return $inbound;
        }

        $audit = $this->ci->inventorycutoffaudit->record([
            'stock_domain' => 'MATERIAL',
            'event_date' => $eventDate,
            'location_scope' => $scope,
            'division_id' => $identity['division_id'] ?? null,
            'destination_type' => $identity['destination_type'] ?? null,
            'item_id' => $identity['item_id'] ?? null,
            'material_id' => $identity['material_id'] ?? null,
            'content_uom_id' => $identity['content_uom_id'] ?? null,
            'profile_key' => $identity['profile_key'] ?? null,
            'lot_id' => $inbound['data']['lot_id'] ?? null,
            'direction' => 'IN',
            'qty' => $qtyIn,
            'unit_cost' => $unitCost,
            'source_table' => $sourceTable,
            'source_id' => $payload['source_id'] ?? null,
            'source_line_id' => $payload['source_line_id'] ?? null,
            'notes' => $notePrefix . ' Lot koreksi dibuat untuk saldo fisik.',
            'created_by' => $payload['created_by'] ?? null,
        ]);
        if (!($audit['ok'] ?? false)) {
            return $audit;
        }

        return [
            'ok' => true,
            'data' => [
                'action' => 'LOT_INCREASE',
                'lot_qty_before' => $lotQtyBefore,
                'target_qty_content' => $targetQty,
                'qty_changed' => $qtyIn,
                'lot_id' => (int)($inbound['data']['lot_id'] ?? 0),
            ],
        ];
    }

    public function previewDivisionUsageState(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }
        $referenceDate = $this->normalizeDate((string)($payload['reference_date'] ?? $payload['issue_date'] ?? $payload['movement_date'] ?? $payload['as_of_date'] ?? ''));
        if ($referenceDate !== null) {
            $identity['reference_date'] = $referenceDate;
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => ($identity['item_id'] ?? null) === null && ($identity['material_id'] ?? null) !== null,
            'allow_any_buy_uom' => ($identity['buy_uom_id'] ?? null) === null,
            'allow_any_profile_key' => ($identity['profile_key'] ?? null) === null,
        ]);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => $this->lastBuilderQueryError];
        }
        $matchedMode = 'EXACT';
        if (empty($lots) && ($identity['material_id'] ?? null) !== null) {
            $lots = $this->findIssueSourceLots($identity, [
                'allow_any_item_id' => true,
                'allow_any_buy_uom' => true,
                'allow_any_content_uom' => true,
                'allow_any_profile_key' => true,
            ]);
            if ($this->lastBuilderQueryError !== null) {
                return ['ok' => false, 'message' => $this->lastBuilderQueryError];
            }
            $matchedMode = 'BROAD';
        }

        $availableQty = 0.0;
        $totalValue = 0.0;
        $profileKeys = [];
        foreach ($lots as $lot) {
            $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($qtyBalance <= 0) {
                continue;
            }
            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $availableQty = round($availableQty + $qtyBalance, 4);
            $totalValue = round($totalValue + round($qtyBalance * $unitCost, 2), 2);
            $profileKey = trim((string)($lot['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = true;
            }
        }
        $avgUnitCost = $availableQty > 0.0001
            ? round($totalValue / $availableQty, 6)
            : 0.0;

        $stockKeyParts = [
            'DIVISION',
            (string)($identity['division_id'] ?? 'NULL'),
            (string)($identity['destination_type'] ?? 'OTHER'),
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['content_uom_id'] ?? 0),
            $matchedMode,
        ];
        if ($matchedMode === 'EXACT') {
            $stockKeyParts[] = (string)($identity['buy_uom_id'] ?? 0);
            $stockKeyParts[] = (string)($identity['profile_key'] ?? '');
        }

        return [
            'ok' => true,
            'data' => [
                'identity' => $identity,
                'lots' => $lots,
                'available_qty' => round($availableQty, 4),
                'avg_unit_cost' => $avgUnitCost,
                'total_value' => round($totalValue, 2),
                'matched_mode' => $matchedMode,
                'matched_profile_keys' => array_keys($profileKeys),
                'stock_key' => implode('|', $stockKeyParts),
            ],
        ];
    }

    public function syncDivisionMonthlyStockFromLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }
        if (!$this->ci->db->table_exists('inv_division_monthly_stock')) {
            return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'missing_table']];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }
        $referenceDate = $this->normalizeDate((string)($payload['reference_date'] ?? $payload['movement_date'] ?? $payload['issue_date'] ?? ''));
        if ($referenceDate !== null) {
            $identity['reference_date'] = $referenceDate;
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => false,
            'allow_any_buy_uom' => false,
            'allow_any_content_uom' => false,
            'allow_any_profile_key' => false,
        ]);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => $this->lastBuilderQueryError];
        }

        // Fallback: when strict search finds no lots and profile_key is set, relax buy_uom
        // matching. Profile_key already uniquely identifies the catalog so buy_uom is redundant.
        // This handles legacy rows where buy_uom_id was null in the stock row but the actual
        // lots carry a specific buy_uom_id (common for data migrated before buy_uom tracking).
        if (empty($lots) && ($identity['profile_key'] ?? null) !== null && ($identity['material_id'] ?? null) !== null) {
            $lots = $this->findIssueSourceLots($identity, [
                'allow_any_item_id' => true,
                'allow_any_buy_uom' => true,
                'allow_any_content_uom' => false,
                'allow_any_profile_key' => false,
            ]);
            if ($this->lastBuilderQueryError !== null) {
                return ['ok' => false, 'message' => $this->lastBuilderQueryError];
            }
        }

        $qtyBalance = 0.0;
        $totalValue = 0.0;
        foreach ($lots as $lot) {
            $lotQty = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotQty <= 0) {
                continue;
            }
            $lotUnitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $qtyBalance = round($qtyBalance + $lotQty, 4);
            $totalValue = round($totalValue + round($lotQty * $lotUnitCost, 2), 2);
        }
        $avgCost = $qtyBalance > 0.0001 ? round($totalValue / $qtyBalance, 6) : 0.0;

        $monthKey = date('Y-m-01', strtotime((string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))));
        $identityKey = $this->buildMonthlyIdentityKeyFromLotIdentity($identity);

        $existing = $this->ci->db->query(
            'SELECT * FROM inv_division_monthly_stock
             WHERE month_key = ?
               AND division_id = ?
               AND destination_type = ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                (int)$identity['division_id'],
                (string)$identity['destination_type'],
                $this->nullableInt($identity['item_id'] ?? null),
                $this->nullableInt($identity['material_id'] ?? null),
                $this->nullableInt($identity['buy_uom_id'] ?? null),
                (int)$identity['content_uom_id'],
                $this->nullableString($identity['profile_key'] ?? null),
            ]
        )->row_array() ?: null;

        // Fallback tier 1: search by the computed identity_key directly.
        // Catches the case where a previous sync already normalised this row's identity_key
        // (e.g. from a prior partially-successful adjustment), avoiding a UNIQUE collision
        // if we later try to UPDATE a legacy row with the same identity_key.
        if (!$existing) {
            $existing = $this->ci->db->query(
                'SELECT * FROM inv_division_monthly_stock
                 WHERE month_key = ? AND division_id = ? AND destination_type = ?
                   AND identity_key = ?
                 ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [
                    $monthKey,
                    (int)$identity['division_id'],
                    (string)$identity['destination_type'],
                    $identityKey,
                ]
            )->row_array() ?: null;
        }

        // Fallback tier 2: search by profile_key for legacy rows whose identity_key or
        // buy_uom_id differs from the current payload (e.g. rows created before buy_uom
        // tracking was added). Only used when tier 1 also found nothing, so there is no
        // risk of a UNIQUE collision on identity_key.
        if (!$existing && ($identity['profile_key'] ?? null) !== null) {
            $existing = $this->ci->db->query(
                'SELECT * FROM inv_division_monthly_stock
                 WHERE month_key = ?
                   AND division_id = ?
                   AND destination_type = ?
                   AND material_id <=> ?
                   AND profile_key = ?
                 ORDER BY closing_qty_content DESC, id DESC
                 LIMIT 1 FOR UPDATE',
                [
                    $monthKey,
                    (int)$identity['division_id'],
                    (string)$identity['destination_type'],
                    $this->nullableInt($identity['material_id'] ?? null),
                    (string)$identity['profile_key'],
                ]
            )->row_array() ?: null;
        }

        $sameUom = ($identity['buy_uom_id'] ?? null) !== null
            && (int)($identity['buy_uom_id'] ?? 0) === (int)($identity['content_uom_id'] ?? 0);
        $contentPerBuy = $sameUom
            ? 1.0
            : max(0.000001, round((float)($existing['profile_content_per_buy'] ?? 1), 6));
        $qtyBuyBalance = $qtyBalance > 0.0001 ? round($qtyBalance / $contentPerBuy, 4) : 0.0;
        $syncNote = trim((string)($payload['sync_note'] ?? ''));
        if ($syncNote === '') {
            $syncNote = 'Synced from FIFO lots';
        }

        if ($existing) {
            $update = [
                'identity_key' => $identityKey,
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'notes' => $syncNote,
            ];
            $this->ci->db->where('id', (int)$existing['id'])->update('inv_division_monthly_stock', $update);
        } else {
            $insert = [
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
                'division_id' => (int)$identity['division_id'],
                'destination_type' => (string)$identity['destination_type'],
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => (int)$identity['content_uom_id'],
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'profile_content_per_buy' => $contentPerBuy,
                'opening_qty_buy' => $qtyBuyBalance,
                'opening_qty_content' => $qtyBalance,
                'opening_total_value' => round($totalValue, 2),
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'movement_day_count' => 0,
                'mutation_count' => 0,
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'source_mode' => 'LIVE',
                'notes' => $syncNote,
            ];
            $this->ci->db->insert('inv_division_monthly_stock', $insert);
        }

        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal sinkron saldo bulanan divisi dari FIFO lot.'];
        }

        return [
            'ok' => true,
            'data' => [
                'qty_balance' => $qtyBalance,
                'qty_buy_balance' => $qtyBuyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
            ],
        ];
    }

    public function syncWarehouseMonthlyStockFromLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'missing_table']];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        if ($this->isWarehouseAggregateMode()) {
            return $this->syncWarehouseAggregateLotToMonthly(
                $identity,
                (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))
            );
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => false,
            'allow_any_buy_uom' => false,
            'allow_any_content_uom' => false,
            'allow_any_profile_key' => false,
        ]);

        $qtyBalance = 0.0;
        $totalValue = 0.0;
        foreach ($lots as $lot) {
            $lotQty = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotQty <= 0) {
                continue;
            }
            $lotUnitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $qtyBalance = round($qtyBalance + $lotQty, 4);
            $totalValue = round($totalValue + round($lotQty * $lotUnitCost, 2), 2);
        }
        $avgCost = $qtyBalance > 0.0001 ? round($totalValue / $qtyBalance, 6) : 0.0;

        $monthKey = date('Y-m-01', strtotime((string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))));
        $identityKey = $this->buildMonthlyIdentityKeyFromLotIdentity($identity);

        $existing = $this->ci->db->query(
            'SELECT * FROM inv_warehouse_monthly_stock
             WHERE month_key = ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                $this->nullableInt($identity['item_id'] ?? null),
                $this->nullableInt($identity['material_id'] ?? null),
                $this->nullableInt($identity['buy_uom_id'] ?? null),
                (int)$identity['content_uom_id'],
                $this->nullableString($identity['profile_key'] ?? null),
            ]
        )->row_array();

        $sameUom = ($identity['buy_uom_id'] ?? null) !== null
            && (int)($identity['buy_uom_id'] ?? 0) === (int)($identity['content_uom_id'] ?? 0);
        $contentPerBuy = $sameUom
            ? 1.0
            : max(0.000001, round((float)($existing['profile_content_per_buy'] ?? 1), 6));
        $qtyBuyBalance = $qtyBalance > 0.0001 ? round($qtyBalance / $contentPerBuy, 4) : 0.0;
        $syncNote = trim((string)($payload['sync_note'] ?? ''));
        if ($syncNote === '') {
            $syncNote = 'Synced from FIFO lots';
        }

        if ($existing) {
            $update = [
                'identity_key' => $identityKey,
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'notes' => $syncNote,
            ];
            $this->ci->db->where('id', (int)$existing['id'])->update('inv_warehouse_monthly_stock', $update);
        } else {
            $insert = [
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => (int)$identity['content_uom_id'],
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'profile_content_per_buy' => $contentPerBuy,
                'opening_qty_buy' => $qtyBuyBalance,
                'opening_qty_content' => $qtyBalance,
                'opening_total_value' => round($totalValue, 2),
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'movement_day_count' => 0,
                'mutation_count' => 0,
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'source_mode' => 'LIVE',
                'notes' => $syncNote,
            ];
            $this->ci->db->insert('inv_warehouse_monthly_stock', $insert);
        }

        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal sinkron saldo bulanan gudang dari FIFO lot.'];
        }

        return [
            'ok' => true,
            'data' => [
                'qty_balance' => $qtyBalance,
                'qty_buy_balance' => $qtyBuyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
            ],
        ];
    }

    public function rollbackReceiptInboundLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $lots = $this->findLotsBySource($sourceTable, $sourceId, $sourceLineId);
        if (empty($lots)) {
            return ['ok' => true, 'data' => ['lot_count' => 0]];
        }

        foreach ($lots as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            $qtyIn = round((float)($lot['qty_in'] ?? 0), 4);
            $qtyOut = round((float)($lot['qty_out'] ?? 0), 4);
            $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);

            if ($lotId <= 0 || $qtyIn <= 0) {
                continue;
            }
            if ($qtyOut > 0.0001 || $qtyBalance + 0.0001 < $qtyIn) {
                return [
                    'ok' => false,
                    'message' => 'Rollback lot receipt ditolak karena lot ' . (string)($lot['lot_no'] ?? ('#' . $lotId)) . ' sudah terpakai.',
                ];
            }

            $this->ci->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                'qty_in' => 0,
                'qty_balance' => 0,
                'status' => 'CLOSED',
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal rollback lot receipt inbound.'];
            }
        }

        return ['ok' => true, 'data' => ['lot_count' => count($lots)]];
    }

    public function closeCarryForwardSourceLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $referenceDate = $this->normalizeDate((string)($payload['reference_date'] ?? $payload['movement_date'] ?? $payload['issue_date'] ?? ''));
        if ($referenceDate === null) {
            return ['ok' => false, 'message' => 'Tanggal carry-forward lot divisi tidak valid.'];
        }

        $monthStart = date('Y-m-01', strtotime($referenceDate));
        $now = date('Y-m-d H:i:s');

        $this->ci->db->from('inv_material_fifo_lot')
            ->where('location_scope', 'DIVISION')
            ->where('division_id', $this->nullableInt($identity['division_id'] ?? null))
            ->where('destination_type', (string)$identity['destination_type'])
            ->where('material_id', (int)$identity['material_id'])
            ->where('content_uom_id', (int)$identity['content_uom_id'])
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0, false)
            ->where('receipt_date <', $monthStart);

        if (($identity['item_id'] ?? null) === null) {
            $this->ci->db->where('item_id IS NULL', null, false);
        } else {
            $this->ci->db->where('item_id', (int)$identity['item_id']);
        }
        if (($identity['buy_uom_id'] ?? null) === null) {
            $this->ci->db->where('buy_uom_id IS NULL', null, false);
        } else {
            $this->ci->db->where('buy_uom_id', (int)$identity['buy_uom_id']);
        }
        if (($identity['profile_key'] ?? null) === null) {
            $this->ci->db->where('profile_key IS NULL', null, false);
        } else {
            $this->ci->db->where('profile_key', (string)$identity['profile_key']);
        }

        $lots = $this->safeBuilderResultArray('MaterialFifoManager::closeLotsBeforeCutoff');
        if (empty($lots)) {
            return ['ok' => true, 'data' => ['closed_count' => 0]];
        }

        $closed = 0;
        foreach ($lots as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            $balance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $balance <= 0.0001) {
                continue;
            }

            $newQtyOut = round((float)($lot['qty_out'] ?? 0) + $balance, 4);
            $this->ci->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                'qty_out' => $newQtyOut,
                'qty_balance' => 0,
                'status' => 'CLOSED',
                'updated_at' => $now,
            ]);
            $closed++;
        }

        return ['ok' => true, 'data' => ['closed_count' => $closed]];
    }

    public function rollbackTransferLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = ''): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $this->ci->db->from('inv_material_fifo_issue_log')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'POSTED');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $issues = $this->ci->db->order_by('id', 'DESC')->get()->result_array();

        if (empty($issues)) {
            return ['ok' => true, 'data' => ['issue_count' => 0, 'line_count' => 0]];
        }

        $lineCount = 0;
        foreach ($issues as $issue) {
            $issueId = (int)($issue['id'] ?? 0);
            if ($issueId <= 0) {
                continue;
            }

            $lines = $this->ci->db
                ->from('inv_material_fifo_issue_line')
                ->where('issue_id', $issueId)
                ->order_by('id', 'DESC')
                ->get()
                ->result_array();

            foreach ($lines as $line) {
                $qtyOut = round((float)($line['qty_out'] ?? 0), 4);
                if ($qtyOut <= 0) {
                    continue;
                }

                $targetLotId = $this->nullableInt($line['target_lot_id'] ?? null);
                if ($targetLotId !== null) {
                    $targetLot = $this->findLotById($targetLotId, true);
                    if (!$targetLot) {
                        return ['ok' => false, 'message' => 'Lot target transfer tidak ditemukan saat rollback FIFO.'];
                    }
                    $targetBalance = round((float)($targetLot['qty_balance'] ?? 0), 4);
                    if ($targetBalance + 0.0001 < $qtyOut) {
                        return [
                            'ok' => false,
                            'message' => 'Rollback fulfillment ditolak karena lot tujuan ' . (string)($targetLot['lot_no'] ?? ('#' . $targetLotId)) . ' sudah terpakai.',
                        ];
                    }

                    $rollbackDivision = $this->applyLotMutation([
                        'lot_id' => $targetLotId,
                        'location_scope' => (string)($targetLot['location_scope'] ?? 'DIVISION'),
                        'division_id' => $this->nullableInt($targetLot['division_id'] ?? null),
                        'destination_type' => $this->nullableString($targetLot['destination_type'] ?? null),
                        'item_id' => $this->nullableInt($targetLot['item_id'] ?? null),
                        'material_id' => $this->nullableInt($targetLot['material_id'] ?? null),
                        'buy_uom_id' => $this->nullableInt($targetLot['buy_uom_id'] ?? null),
                        'content_uom_id' => $this->nullableInt($targetLot['content_uom_id'] ?? null),
                        'profile_key' => $this->nullableString($targetLot['profile_key'] ?? null),
                        'lot_no' => (string)($targetLot['lot_no'] ?? ''),
                        'receipt_date' => (string)($targetLot['receipt_date'] ?? date('Y-m-d')),
                        'expiry_date' => $this->normalizeDate((string)($targetLot['expiry_date'] ?? '')),
                        'unit_cost' => max(0, round((float)($targetLot['unit_cost'] ?? 0), 6)),
                        'source_table' => $this->nullableString($targetLot['source_table'] ?? null),
                        'source_id' => $this->nullableInt($targetLot['source_id'] ?? null),
                        'source_line_id' => $this->nullableInt($targetLot['source_line_id'] ?? null),
                        'receipt_id' => $this->nullableInt($targetLot['receipt_id'] ?? null),
                        'receipt_line_id' => $this->nullableInt($targetLot['receipt_line_id'] ?? null),
                        'parent_lot_id' => $this->nullableInt($targetLot['parent_lot_id'] ?? null),
                    ], -1 * $qtyOut, 0.0);
                    if (!($rollbackDivision['ok'] ?? false)) {
                        return $rollbackDivision;
                    }
                }

                $sourceLot = $this->findLotById((int)($line['lot_id'] ?? 0), true);
                if (!$sourceLot) {
                    return ['ok' => false, 'message' => 'Lot sumber transfer tidak ditemukan saat rollback FIFO.'];
                }

                $rollbackWarehouse = $this->applyLotMutation([
                    'lot_id' => (int)($line['lot_id'] ?? 0),
                    'location_scope' => (string)($sourceLot['location_scope'] ?? 'WAREHOUSE'),
                    'division_id' => $this->nullableInt($sourceLot['division_id'] ?? null),
                    'destination_type' => $this->nullableString($sourceLot['destination_type'] ?? null),
                    'item_id' => $this->nullableInt($sourceLot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($sourceLot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($sourceLot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($sourceLot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($sourceLot['profile_key'] ?? null),
                    'lot_no' => (string)($sourceLot['lot_no'] ?? ''),
                    'receipt_date' => (string)($sourceLot['receipt_date'] ?? date('Y-m-d')),
                    'expiry_date' => $this->normalizeDate((string)($sourceLot['expiry_date'] ?? '')),
                    'unit_cost' => max(0, round((float)($sourceLot['unit_cost'] ?? 0), 6)),
                    'source_table' => $this->nullableString($sourceLot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($sourceLot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($sourceLot['source_line_id'] ?? null),
                    'receipt_id' => $this->nullableInt($sourceLot['receipt_id'] ?? null),
                    'receipt_line_id' => $this->nullableInt($sourceLot['receipt_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($sourceLot['parent_lot_id'] ?? null),
                ], 0.0, -1 * $qtyOut);
                if (!($rollbackWarehouse['ok'] ?? false)) {
                    return $rollbackWarehouse;
                }

                $lineCount++;
            }

            $note = trim($voidNote);
            $existingNotes = trim((string)($issue['notes'] ?? ''));
            if ($note === '') {
                $note = 'Rollback FIFO transfer';
            }
            $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
                'status' => 'VOID',
                'voided_at' => date('Y-m-d H:i:s'),
                'notes' => $existingNotes !== '' ? ($existingNotes . ' | ' . $note) : $note,
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal menutup issue FIFO saat rollback.'];
            }
        }

        return ['ok' => true, 'data' => ['issue_count' => count($issues), 'line_count' => $lineCount]];
    }

    public function rollbackDivisionUsageLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = '', ?float $rollbackQty = null): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        if ($sourceTable === '' || $sourceId <= 0) {
            return ['ok' => false, 'message' => 'Sumber rollback lot divisi tidak valid.'];
        }

        $this->ci->db->from('inv_material_fifo_issue_log')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'POSTED');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $issues = $this->ci->db->order_by('id', 'DESC')->get()->result_array();
        if (empty($issues)) {
            return ['ok' => true, 'data' => ['issue_count' => 0, 'line_count' => 0, 'rolled_qty' => 0.0, 'allocations' => []]];
        }

        $lineCount = 0;
        $now = date('Y-m-d H:i:s');
        $remaining = $rollbackQty !== null ? round(max(0, $rollbackQty), 4) : null;
        $rolledQty = 0.0;
        $allocations = [];

        foreach ($issues as $issue) {
            if ($remaining !== null && $remaining <= 0.0001) {
                break;
            }

            $issueId = (int)($issue['id'] ?? 0);
            if ($issueId <= 0) {
                continue;
            }

            $lines = $this->ci->db
                ->from('inv_material_fifo_issue_line')
                ->where('issue_id', $issueId)
                ->order_by('id', 'DESC')
                ->get()
                ->result_array();

            $issueRolledQty = 0.0;
            foreach ($lines as $line) {
                if ($remaining !== null && $remaining <= 0.0001) {
                    break;
                }

                $lotId = (int)($line['lot_id'] ?? 0);
                $qtyOut = round((float)($line['qty_out'] ?? 0), 4);
                if ($lotId <= 0 || $qtyOut <= 0) {
                    continue;
                }

                $rollbackLineQty = $remaining === null ? $qtyOut : round(min($qtyOut, $remaining), 4);
                if ($rollbackLineQty <= 0) {
                    continue;
                }

                $lot = $this->findLotById($lotId, true);
                if (!$lot) {
                    return ['ok' => false, 'message' => 'Lot sumber pemakaian divisi tidak ditemukan saat rollback FIFO.'];
                }

                $currentOut = round((float)($lot['qty_out'] ?? 0), 4);
                $currentBalance = round((float)($lot['qty_balance'] ?? 0), 4);
                $newOut = round($currentOut - $rollbackLineQty, 4);
                $newBalance = round($currentBalance + $rollbackLineQty, 4);

                if ($newOut < -0.0001 || $newBalance < -0.0001) {
                    return ['ok' => false, 'message' => 'Rollback pemakaian FIFO menghasilkan saldo lot tidak valid.'];
                }

                if (abs($newOut) < 0.0001) {
                    $newOut = 0.0;
                }
                if (abs($newBalance) < 0.0001) {
                    $newBalance = 0.0;
                }

                $this->ci->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                    'qty_out' => $newOut,
                    'qty_balance' => $newBalance,
                    'status' => $newBalance > 0 ? 'OPEN' : 'CLOSED',
                    'updated_at' => $now,
                ]);
                if ($this->ci->db->trans_status() === false) {
                    return ['ok' => false, 'message' => 'Gagal update lot FIFO saat rollback pemakaian divisi.'];
                }

                $lineUnitCost = max(0, round((float)($line['unit_cost'] ?? ($lot['unit_cost'] ?? 0)), 6));
                $newIssueQty = round($qtyOut - $rollbackLineQty, 4);
                $this->ci->db->where('id', (int)($line['id'] ?? 0))->update('inv_material_fifo_issue_line', [
                    'qty_out' => $newIssueQty,
                    'total_cost' => round($newIssueQty * $lineUnitCost, 2),
                    'source_balance_after' => round((float)($line['source_balance_after'] ?? $currentBalance) + $rollbackLineQty, 4),
                ]);
                if ($this->ci->db->trans_status() === false) {
                    return ['ok' => false, 'message' => 'Gagal update detail issue FIFO saat rollback pemakaian divisi.'];
                }

                $lineCount++;
                $issueRolledQty = round($issueRolledQty + $rollbackLineQty, 4);
                $rolledQty = round($rolledQty + $rollbackLineQty, 4);
                if ($remaining !== null) {
                    $remaining = round($remaining - $rollbackLineQty, 4);
                }
                $allocations[] = [
                    'issue_id' => $issueId,
                    'issue_line_id' => (int)($line['id'] ?? 0),
                    'lot_id' => $lotId,
                    'qty_rolled' => $rollbackLineQty,
                    'qty_remaining' => $newIssueQty,
                ];
            }

            $note = trim($voidNote);
            if ($note === '') {
                $note = 'Rollback FIFO usage';
            }
            $existingNotes = trim((string)($issue['notes'] ?? ''));
            $remainingIssue = $this->ci->db->select('COALESCE(SUM(qty_out),0) AS qty_out, COALESCE(SUM(total_cost),0) AS total_cost', false)
                ->from('inv_material_fifo_issue_line')
                ->where('issue_id', $issueId)
                ->get()
                ->row_array() ?: ['qty_out' => 0, 'total_cost' => 0];
            $issueQtyAfter = round((float)($remainingIssue['qty_out'] ?? 0), 4);
            $issueStatus = $issueQtyAfter <= 0.0001 ? 'VOID' : 'POSTED';
            $issueNote = $existingNotes;
            if ($issueStatus === 'VOID') {
                $issueNote = $issueNote !== '' ? ($issueNote . ' | ' . $note) : $note;
            } elseif ($issueRolledQty > 0) {
                $partialNote = 'Partial rollback ' . rtrim(rtrim(number_format($issueRolledQty, 4, '.', ''), '0'), '.');
                $issueNote = $issueNote !== '' ? ($issueNote . ' | ' . $partialNote) : $partialNote;
            }
            $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
                'issue_qty' => $issueQtyAfter,
                'total_cost' => round((float)($remainingIssue['total_cost'] ?? 0), 2),
                'status' => $issueStatus,
                'voided_at' => $issueStatus === 'VOID' ? $now : null,
                'notes' => $issueNote !== '' ? $issueNote : null,
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal menutup issue FIFO pemakaian divisi.'];
            }
        }

        if ($remaining !== null && $remaining > 0.0001) {
            return ['ok' => false, 'message' => 'Rollback FIFO bahan baku tidak lengkap.'];
        }

        return [
            'ok' => true,
            'data' => [
                'issue_count' => count($issues),
                'line_count' => $lineCount,
                'rolled_qty' => $rolledQty,
                'allocations' => $allocations,
            ],
        ];
    }

    private function ensureSchema(): array
    {
        if ($this->schemaEnsured) {
            return ['ok' => true];
        }

        $db = $this->ci->db;
        $requirements = [
            'inv_material_fifo_lot' => [
                'lot_no', 'location_scope', 'receipt_date', 'division_id',
                'destination_type', 'item_id', 'material_id', 'buy_uom_id',
                'content_uom_id', 'profile_key', 'qty_in', 'qty_out',
                'qty_balance', 'unit_cost', 'source_table', 'source_id',
                'source_line_id', 'receipt_id', 'receipt_line_id', 'parent_lot_id',
                'status', 'created_at', 'updated_at',
            ],
            'inv_material_fifo_issue_log' => [
                'issue_no', 'issue_date', 'issue_datetime', 'location_scope',
                'division_id', 'destination_type', 'target_scope',
                'target_division_id', 'target_destination_type', 'item_id',
                'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key',
                'issue_qty', 'total_cost', 'source_module', 'source_table',
                'source_id', 'source_line_id', 'status', 'voided_at', 'created_at',
            ],
            'inv_material_fifo_issue_line' => [
                'issue_id', 'lot_id', 'target_lot_id', 'qty_out', 'unit_cost',
                'total_cost', 'source_balance_before', 'source_balance_after',
                'target_balance_before', 'target_balance_after', 'created_at',
            ],
        ];

        foreach ($requirements as $table => $fields) {
            if (!$db->table_exists($table)) {
                return [
                    'ok' => false,
                    'message' => 'Tabel FIFO material ' . $table . ' belum tersedia. Jalankan migration 2026-08-17c_inventory_lot_schema_preflight.sql terlebih dahulu.',
                ];
            }

            $available = $db->list_fields($table) ?: [];
            $missing = array_values(array_diff($fields, $available));
            if ($missing) {
                return [
                    'ok' => false,
                    'message' => 'Struktur FIFO material ' . $table . ' belum lengkap (' . implode(', ', $missing) . '). Jalankan migration 2026-08-17c_inventory_lot_schema_preflight.sql terlebih dahulu.',
                ];
            }
        }

        $this->schemaEnsured = true;
        return ['ok' => true];
    }

    private function applyLotMutation(array $identity, float $qtyInDelta, float $qtyOutDelta): array
    {
        $lotId = isset($identity['lot_id']) ? (int)$identity['lot_id'] : 0;
        $existing = $lotId > 0
            ? $this->findLotById($lotId, true)
            : $this->findLotForUpdate($identity);

        if (!$existing) {
            if ($qtyInDelta < -0.0001 || $qtyOutDelta > 0.0001) {
                return ['ok' => false, 'message' => 'Lot FIFO tidak ditemukan untuk mutasi keluar.'];
            }

            $insert = [
                'lot_no' => (string)$identity['lot_no'],
                'location_scope' => strtoupper((string)$identity['location_scope']),
                'receipt_date' => (string)$identity['receipt_date'],
                'expiry_date' => $identity['expiry_date'],
                'division_id' => $this->nullableInt($identity['division_id'] ?? null),
                'destination_type' => $this->nullableString($identity['destination_type'] ?? null),
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($identity['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'qty_in' => round(max(0, $qtyInDelta), 4),
                'qty_out' => round(max(0, $qtyOutDelta), 4),
                'qty_balance' => round(max(0, $qtyInDelta - $qtyOutDelta), 4),
                'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($identity['source_table'] ?? null),
                'source_id' => $this->nullableInt($identity['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($identity['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($identity['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($identity['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($identity['parent_lot_id'] ?? null),
                'status' => round(max(0, $qtyInDelta - $qtyOutDelta), 4) > 0 ? 'OPEN' : 'CLOSED',
            ];
            $this->ci->db->insert('inv_material_fifo_lot', $insert);
            $newId = (int)$this->ci->db->insert_id();
            if ($newId <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan lot FIFO.'];
            }

            return [
                'ok' => true,
                'data' => [
                    'lot_id' => $newId,
                    'lot_no' => (string)$identity['lot_no'],
                    'qty_balance' => round(max(0, $qtyInDelta - $qtyOutDelta), 4),
                    'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
                ],
            ];
        }

        $oldIn = round((float)($existing['qty_in'] ?? 0), 4);
        $oldOut = round((float)($existing['qty_out'] ?? 0), 4);
        $oldBalance = round((float)($existing['qty_balance'] ?? 0), 4);
        $newIn = round($oldIn + $qtyInDelta, 4);
        $newOut = round($oldOut + $qtyOutDelta, 4);
        $newBalance = round($oldBalance + $qtyInDelta - $qtyOutDelta, 4);

        if ($newIn < -0.0001 || $newOut < -0.0001 || $newBalance < -0.0001) {
            return ['ok' => false, 'message' => 'Mutasi lot FIFO menyebabkan saldo negatif.'];
        }

        if (abs($newIn) < 0.0001) {
            $newIn = 0.0;
        }
        if (abs($newOut) < 0.0001) {
            $newOut = 0.0;
        }
        if (abs($newBalance) < 0.0001) {
            $newBalance = 0.0;
        }

        $update = [
            'qty_in' => $newIn,
            'qty_out' => $newOut,
            'qty_balance' => $newBalance,
            'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? ($existing['unit_cost'] ?? 0)), 6)),
            'status' => $newBalance > 0 ? 'OPEN' : 'CLOSED',
        ];
        $this->ci->db->where('id', (int)$existing['id'])->update('inv_material_fifo_lot', $update);
        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal update lot FIFO.'];
        }

        return [
            'ok' => true,
            'data' => [
                'lot_id' => (int)$existing['id'],
                'lot_no' => (string)($existing['lot_no'] ?? ''),
                'qty_balance' => $newBalance,
                'unit_cost' => max(0, round((float)($update['unit_cost'] ?? 0), 6)),
            ],
        ];
    }

    private function normalizeLotIdentity(array $payload, bool $allowWarehouseWithoutDivision): array
    {
        $locationScope = strtoupper(trim((string)($payload['location_scope'] ?? 'WAREHOUSE')));
        if (!in_array($locationScope, ['WAREHOUSE', 'DIVISION'], true)) {
            return ['ok' => false, 'message' => 'location_scope FIFO tidak valid.'];
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        if ($locationScope === 'WAREHOUSE') {
            if (!$allowWarehouseWithoutDivision) {
                $divisionId = null;
            }
            $destinationType = 'GUDANG';
        } elseif ($divisionId === null || $destinationType === null || $destinationType === 'GUDANG') {
            return ['ok' => false, 'message' => 'Lot FIFO divisi membutuhkan division_id dan destination_type non-gudang.'];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        if ($materialId === null && $itemId !== null) {
            $materialId = $this->resolveMaterialIdFromItem($itemId);
        }
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        if ($itemId === null && $materialId === null) {
            return ['ok' => false, 'message' => 'Lot FIFO membutuhkan item_id atau material_id.'];
        }
        if ($contentUomId === null) {
            return ['ok' => false, 'message' => 'content_uom_id FIFO wajib diisi.'];
        }

        return [
            'ok' => true,
            'location_scope' => $locationScope,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $this->nullableInt($payload['buy_uom_id'] ?? null),
            'content_uom_id' => $contentUomId,
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'expiry_date' => $this->normalizeDate((string)($payload['expiry_date'] ?? ($payload['profile_expired_date'] ?? ''))),
        ];
    }

    private function resolveMaterialIdFromItem(int $itemId): ?int
    {
        if ($itemId <= 0 || !$this->ci->db->table_exists('mst_item')) {
            return null;
        }

        $row = $this->ci->db
            ->select('material_id')
            ->from('mst_item')
            ->where('id', $itemId)
            ->limit(1)
            ->get()
            ->row_array();

        $materialId = $this->nullableInt($row['material_id'] ?? null);
        return $materialId !== null && $materialId > 0 ? $materialId : null;
    }

    private function findOpenLots(array $identity): array
    {
        $this->ci->db
            ->from('inv_material_fifo_lot')
            ->where('location_scope', strtoupper((string)$identity['location_scope']))
            ->where('status', 'OPEN')
            ->where('content_uom_id', (int)$identity['content_uom_id'])
            ->where('qty_balance >', 0, false)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC');

        if ($identity['division_id'] === null) {
            $this->ci->db->where('division_id IS NULL', null, false);
        } else {
            $this->ci->db->where('division_id', (int)$identity['division_id']);
        }
        if (($identity['destination_type'] ?? null) === null) {
            $this->ci->db->where('destination_type IS NULL', null, false);
        } else {
            $this->ci->db->where('destination_type', (string)$identity['destination_type']);
        }
        if (($identity['item_id'] ?? null) === null) {
            $this->ci->db->where('item_id IS NULL', null, false);
        } else {
            $this->ci->db->where('item_id', (int)$identity['item_id']);
        }
        if (($identity['material_id'] ?? null) === null) {
            $this->ci->db->where('material_id IS NULL', null, false);
        } else {
            $this->ci->db->where('material_id', (int)$identity['material_id']);
        }
        if (($identity['buy_uom_id'] ?? null) === null) {
            $this->ci->db->where('buy_uom_id IS NULL', null, false);
        } else {
            $this->ci->db->where('buy_uom_id', (int)$identity['buy_uom_id']);
        }
        if (($identity['profile_key'] ?? null) === null) {
            $this->ci->db->where('profile_key IS NULL', null, false);
        } else {
            $this->ci->db->where('profile_key', (string)$identity['profile_key']);
        }

        return $this->safeBuilderResultArray('MaterialFifoManager::findOpenLots');
    }

    private function findIssueSourceLots(array $identity, array $options = []): array
    {
        $allowAnyItemId = !empty($options['allow_any_item_id']);
        $allowAnyBuyUom = !empty($options['allow_any_buy_uom']);
        $allowAnyContentUom = !empty($options['allow_any_content_uom']);
        $allowAnyProfileKey = !empty($options['allow_any_profile_key']);

        $this->ci->db
            ->from('inv_material_fifo_lot')
            ->where('location_scope', strtoupper((string)$identity['location_scope']))
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0, false)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC');

        $referenceDate = $this->normalizeDate((string)($identity['reference_date'] ?? ''));
        if ($referenceDate !== null) {
            $this->ci->db->where('receipt_date <=', $referenceDate);
        }

        $divisionCutoff = $this->resolveDivisionLotCutoffWindow($identity, $referenceDate);
        if ($divisionCutoff['use_month_cutoff']) {
            $this->ci->db->where('receipt_date >=', $divisionCutoff['month_start']);
            $this->ci->db->where('receipt_date <', $divisionCutoff['next_month']);
        }

        if (!$allowAnyContentUom) {
            $this->ci->db->where('content_uom_id', (int)$identity['content_uom_id']);
        }

        if ($identity['division_id'] === null) {
            $this->ci->db->where('division_id IS NULL', null, false);
        } else {
            $this->ci->db->where('division_id', (int)$identity['division_id']);
        }
        if (($identity['destination_type'] ?? null) === null) {
            $this->ci->db->where('destination_type IS NULL', null, false);
        } else {
            $this->ci->db->where('destination_type', (string)$identity['destination_type']);
        }
        if (!$allowAnyItemId) {
            if (($identity['item_id'] ?? null) === null) {
                $this->ci->db->where('item_id IS NULL', null, false);
            } else {
                $this->ci->db->where('item_id', (int)$identity['item_id']);
            }
        }
        if (($identity['material_id'] ?? null) === null) {
            $this->ci->db->where('material_id IS NULL', null, false);
        } else {
            $this->ci->db->where('material_id', (int)$identity['material_id']);
        }
        if (!$allowAnyBuyUom) {
            if (($identity['buy_uom_id'] ?? null) === null) {
                $this->ci->db->where('buy_uom_id IS NULL', null, false);
            } else {
                $this->ci->db->where('buy_uom_id', (int)$identity['buy_uom_id']);
            }
        }
        if (!$allowAnyProfileKey) {
            if (($identity['profile_key'] ?? null) === null) {
                $this->ci->db->where('profile_key IS NULL', null, false);
            } else {
                $this->ci->db->where('profile_key', (string)$identity['profile_key']);
            }
        }

        return $this->safeBuilderResultArray('MaterialFifoManager::findIssueSourceLots');
    }

    private function resolveDivisionLotCutoffWindow(array $identity, ?string $referenceDate): array
    {
        $context = [
            'use_month_cutoff' => false,
            'month_start' => null,
            'next_month' => null,
        ];

        if (strtoupper((string)($identity['location_scope'] ?? '')) !== 'DIVISION') {
            return $context;
        }
        if ($referenceDate === null || !$this->ci->db->table_exists('inv_material_fifo_lot')) {
            return $context;
        }

        $divisionId = $this->nullableInt($identity['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($identity['destination_type'] ?? ''));
        $materialId = $this->nullableInt($identity['material_id'] ?? null);
        if ($divisionId === null || $destinationType === null || $materialId === null) {
            return $context;
        }

        $monthStart = date('Y-m-01', strtotime($referenceDate));
        $nextMonth = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        $hasOpeningRow = $this->ci->db->query(
            'SELECT id
             FROM inv_material_fifo_lot
             WHERE location_scope = ?
               AND status = ?
               AND qty_balance > 0
               AND source_table = ?
               AND division_id <=> ?
               AND destination_type = ?
               AND material_id = ?
               AND receipt_date >= ?
               AND receipt_date < ?
             LIMIT 1',
            [
                'DIVISION',
                'OPEN',
                'inv_division_stock_opening_snapshot',
                $divisionId,
                $destinationType,
                $materialId,
                $monthStart,
                $nextMonth,
            ]
        )->row_array();
        $hasOpening = !empty($hasOpeningRow);

        if (!$hasOpening) {
            return $context;
        }

        $context['use_month_cutoff'] = true;
        $context['month_start'] = $monthStart;
        $context['next_month'] = $nextMonth;
        return $context;
    }

    private function synchronizeWarehouseLotsFromAggregate(array $identity): array
    {
        if ($this->isWarehouseAggregateMode()) {
            return $this->syncWarehouseAggregateLotToMonthly($identity, date('Y-m-d'));
        }

        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $monthlyStock = $this->fetchWarehouseAggregateMonthlyStock($identity);
        if (!$monthlyStock) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $aggregateQty = round((float)($monthlyStock['closing_qty_content'] ?? 0), 4);
        if ($aggregateQty <= 0) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $lotRows = $this->findOpenLots([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
        ]);

        $lotQty = 0.0;
        foreach ($lotRows as $lotRow) {
            $lotQty = round($lotQty + (float)($lotRow['qty_balance'] ?? 0), 4);
        }

        $missingQty = round($aggregateQty - $lotQty, 4);
        if ($missingQty <= 0.0001) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $receiptDate = $this->resolveWarehouseBootstrapDate($identity);
        $lotNo = $this->generateLotNo($receiptDate, [
            'BOOT',
            $monthlyStock['id'] ?? 0,
            $identity['item_id'] ?? 0,
            $identity['material_id'] ?? 0,
            $identity['profile_key'] ?? '',
            $identity['buy_uom_id'] ?? 0,
            $identity['content_uom_id'] ?? 0,
        ]);

        $bootstrap = $this->applyLotMutation([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => null,
            'unit_cost' => max(0, round((float)($monthlyStock['avg_cost_per_content'] ?? 0), 6)),
            'source_table' => 'inv_warehouse_monthly_stock',
            'source_id' => $this->nullableInt($monthlyStock['id'] ?? null),
            'source_line_id' => null,
            'receipt_id' => null,
            'receipt_line_id' => null,
            'parent_lot_id' => null,
        ], $missingQty, 0.0);
        if (!($bootstrap['ok'] ?? false)) {
            return $bootstrap;
        }

        return ['ok' => true, 'data' => ['bootstrapped' => true, 'qty_added' => $missingQty]];
    }

    public function syncWarehouseAggregateProfile(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        return $this->syncWarehouseAggregateLotToMonthly(
            $identity,
            (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))
        );
    }

    /**
     * Bring the synthetic warehouse FIFO representation back to the active
     * monthly balance. This never changes the monthly stock ledger itself.
     */
    public function syncWarehouseAggregateProfilesForMonth(string $month, bool $dryRun = true): array
    {
        if (!preg_match('/^\d{4}-\d{2}(?:-\d{2})?$/', trim($month))) {
            return ['ok' => false, 'message' => 'Bulan sinkron lot gudang tidak valid.'];
        }

        $monthKey = date('Y-m-01', strtotime($month));
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel saldo bulanan gudang tidak tersedia.'];
        }

        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $rows = $this->ci->db
            ->select('item_id, material_id, buy_uom_id, content_uom_id, profile_key')
            ->from('inv_warehouse_monthly_stock')
            ->where('month_key', $monthKey)
            ->order_by('item_id', 'ASC')
            ->order_by('material_id', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        $targets = [];
        foreach ($rows as $row) {
            $identity = $this->normalizeLotIdentity([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $this->nullableInt($row['item_id'] ?? null),
                'material_id' => $this->nullableInt($row['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($row['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($row['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($row['profile_key'] ?? null),
            ], false);
            if (!($identity['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => 'Ada profil saldo gudang yang tidak valid untuk disinkronkan: ' . (string)($identity['message'] ?? 'unknown error'),
                ];
            }
            $targets[] = $identity;
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'data' => [
                    'month_key' => $monthKey,
                    'dry_run' => true,
                    'target_count' => count($targets),
                ],
            ];
        }

        $this->ci->db->trans_begin();
        $synced = 0;
        $duplicateLotCount = 0;
        foreach ($targets as $identity) {
            $sync = $this->syncWarehouseAggregateLotToMonthly($identity, $monthKey);
            if (!($sync['ok'] ?? false)) {
                $this->ci->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal sinkron lot agregat gudang: ' . (string)($sync['message'] ?? 'unknown error'),
                ];
            }
            $synced++;
            $duplicateLotCount += (int)($sync['data']['duplicate_lot_count'] ?? 0);
        }

        if ($this->ci->db->trans_status() === false) {
            $this->ci->db->trans_rollback();
            return ['ok' => false, 'message' => 'Transaksi sinkron lot agregat gudang gagal.'];
        }

        $this->ci->db->trans_commit();
        return [
            'ok' => true,
            'data' => [
                'month_key' => $monthKey,
                'dry_run' => false,
                'target_count' => count($targets),
                'synced_count' => $synced,
                'closed_duplicate_lot_count' => $duplicateLotCount,
            ],
        ];
    }

    private function fetchWarehouseAggregateMonthlyStock(array $identity, ?string $cutoffDate = null): ?array
    {
        $sql = 'SELECT id, month_key, profile_content_per_buy, closing_qty_content, avg_cost_per_content
            FROM inv_warehouse_monthly_stock
            WHERE item_id <=> ?
              AND buy_uom_id <=> ?
              AND content_uom_id = ?
              AND profile_key <=> ?
              AND month_key <= ?';
        $params = [
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
            date('Y-m-01', strtotime((string)($cutoffDate ?: date('Y-m-d')))),
        ];

        if ($this->ci->db->field_exists('material_id', 'inv_warehouse_monthly_stock')) {
            $sql .= ' AND material_id <=> ?';
            $params[] = $this->nullableInt($identity['material_id'] ?? null);
        }
        $sql .= ' ORDER BY month_key DESC, id DESC LIMIT 1';

        $row = $this->ci->db->query($sql, $params)->row_array();

        return $row ?: null;
    }

    private function resolveWarehouseBootstrapDate(array $identity): string
    {
        if (!$this->ci->db->table_exists('inv_stock_movement_log')) {
            return date('Y-m-d');
        }

        $sql = 'SELECT MIN(movement_date) AS first_movement_date
             FROM inv_stock_movement_log
             WHERE movement_scope = ?
               AND item_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?';
        $params = [
            'WAREHOUSE',
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
        ];
        if ($this->ci->db->field_exists('material_id', 'inv_stock_movement_log')) {
            $sql .= ' AND material_id <=> ?';
            $params[] = $this->nullableInt($identity['material_id'] ?? null);
        }

        $row = $this->ci->db->query($sql, $params)->row_array();

        $date = $this->normalizeDate((string)($row['first_movement_date'] ?? ''));
        return $date ?? date('Y-m-d');
    }

    private function syncWarehouseAggregateLotToMonthly(array $identity, string $referenceDate): array
    {
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $monthlyStock = $this->fetchWarehouseAggregateMonthlyStock($identity, $referenceDate);
        if (!$monthlyStock) {
            return [
                'ok' => true,
                'data' => [
                    'skipped' => true,
                    'reason' => 'monthly_stock_not_found',
                ],
            ];
        }
        $desiredQty = round((float)($monthlyStock['closing_qty_content'] ?? 0), 4);
        $unitCost = max(0, round((float)($monthlyStock['avg_cost_per_content'] ?? 0), 6));
        $aggregateLot = $this->findWarehouseAggregateLot($identity, true);

        if (!$aggregateLot) {
            if ($desiredQty <= 0.0001) {
                return [
                    'ok' => true,
                    'data' => [
                        'qty_balance' => 0.0,
                        'qty_buy_balance' => 0.0,
                        'avg_cost_per_content' => $unitCost,
                        'total_value' => 0.0,
                        'month_key' => $monthlyStock['month_key'] ?? date('Y-m-01', strtotime($referenceDate)),
                        'identity_key' => $this->buildMonthlyIdentityKeyFromLotIdentity($identity),
                    ],
                ];
            }

            $create = $this->applyLotMutation([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => $this->buildWarehouseAggregateLotNo($identity),
                'receipt_date' => $this->resolveWarehouseBootstrapDate($identity),
                'expiry_date' => null,
                'unit_cost' => $unitCost,
                'source_table' => 'WAREHOUSE_PROFILE',
                'source_id' => null,
                'source_line_id' => null,
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => null,
            ], $desiredQty, 0.0);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $aggregateLot = $this->findWarehouseAggregateLot($identity, true);
        }

        $duplicateLotCount = 0;
        if ($aggregateLot) {
            $qtyOut = round((float)($aggregateLot['qty_out'] ?? 0), 4);
            $qtyIn = $desiredQty > 0 ? round($qtyOut + $desiredQty, 4) : $qtyOut;
            $this->ci->db->where('id', (int)$aggregateLot['id'])->update('inv_material_fifo_lot', [
                'qty_in' => $qtyIn,
                'qty_balance' => $desiredQty,
                'unit_cost' => $unitCost,
                'status' => $desiredQty > 0 ? 'OPEN' : 'CLOSED',
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal sinkron saldo profil gudang.'];
            }

            $duplicateLotCount = $this->closeWarehouseAggregateLotDuplicates($identity, (int)$aggregateLot['id']);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal menutup lot agregat gudang duplikat.'];
            }
        }

        $sameUom = ($identity['buy_uom_id'] ?? null) !== null
            && (int)($identity['buy_uom_id'] ?? 0) === (int)($identity['content_uom_id'] ?? 0);
        $contentPerBuy = $sameUom ? 1.0 : max(0.000001, round((float)($monthlyStock['profile_content_per_buy'] ?? 1), 6));
        $qtyBuyBalance = $desiredQty > 0.0001 ? round($desiredQty / $contentPerBuy, 4) : 0.0;

        return [
            'ok' => true,
            'data' => [
                'qty_balance' => $desiredQty,
                'qty_buy_balance' => $qtyBuyBalance,
                'avg_cost_per_content' => $unitCost,
                'total_value' => round($desiredQty * $unitCost, 2),
                'month_key' => $monthlyStock['month_key'] ?? date('Y-m-01', strtotime($referenceDate)),
                'identity_key' => $this->buildMonthlyIdentityKeyFromLotIdentity($identity),
                'duplicate_lot_count' => $duplicateLotCount,
            ],
        ];
    }

    private function isWarehouseAggregateMode(): bool
    {
        return $this->warehouseAggregateMode === true;
    }

    private function buildWarehouseAggregateLotNo(array $identity): string
    {
        return 'WH-PROFILE-' . strtoupper(substr(hash('sha1', implode('|', [
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['buy_uom_id'] ?? 0),
            (string)($identity['content_uom_id'] ?? 0),
            (string)($identity['profile_key'] ?? ''),
        ])), 0, 16));
    }

    private function findWarehouseAggregateLot(array $identity, bool $forUpdate = false): ?array
    {
        $rows = $this->findWarehouseAggregateLots($identity, $forUpdate);
        return $rows[0] ?? null;
    }

    /**
     * Older installations used more than one aggregate lot-number formula.
     * Prefer the current formula but reuse a legacy row rather than creating a
     * second aggregate representation for the same monthly profile.
     */
    private function findWarehouseAggregateLots(array $identity, bool $forUpdate = false): array
    {
        $canonicalLotNo = $this->buildWarehouseAggregateLotNo($identity);
        $sql = 'SELECT * FROM inv_material_fifo_lot
            WHERE location_scope = ?
              AND division_id IS NULL
              AND destination_type = ?
              AND item_id <=> ?
              AND material_id <=> ?
              AND buy_uom_id <=> ?
              AND content_uom_id = ?
              AND profile_key <=> ?
              AND source_table = ?
            ORDER BY CASE WHEN lot_no = ? THEN 0 ELSE 1 END,
                     CASE WHEN UPPER(COALESCE(status, "OPEN")) = "OPEN" AND ABS(COALESCE(qty_balance, 0)) > 0.0001 THEN 0 ELSE 1 END,
                     id DESC' . ($forUpdate ? ' FOR UPDATE' : '');

        return $this->ci->db->query($sql, [
            'WAREHOUSE',
            'GUDANG',
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['material_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
            'WAREHOUSE_PROFILE',
            $canonicalLotNo,
        ])->result_array();
    }

    private function closeWarehouseAggregateLotDuplicates(array $identity, int $canonicalLotId): int
    {
        if ($canonicalLotId <= 0) {
            return 0;
        }

        $closedCount = 0;
        foreach ($this->findWarehouseAggregateLots($identity, true) as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            if ($lotId <= 0 || $lotId === $canonicalLotId) {
                continue;
            }

            $qtyIn = round((float)($lot['qty_in'] ?? 0), 4);
            $qtyOut = round((float)($lot['qty_out'] ?? 0), 4);
            $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if (abs($qtyBalance) <= 0.0001 && strtoupper((string)($lot['status'] ?? 'OPEN')) === 'CLOSED') {
                continue;
            }

            $this->ci->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                'qty_out' => max($qtyIn, round($qtyOut + $qtyBalance, 4)),
                'qty_balance' => 0.0,
                'status' => 'CLOSED',
            ]);
            $closedCount++;
        }

        return $closedCount;
    }

    private function generateDivisionTransferLotNo(string $issueDate, string $issueNo, int $divisionId, string $destinationType, array $identity): string
    {
        return substr('DIV-' . date('Ymd', strtotime($issueDate)) . '-' . strtoupper(substr(hash('sha1', implode('|', [
            $issueNo,
            $divisionId,
            $destinationType,
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['profile_key'] ?? ''),
        ])), 0, 12)), 0, 80);
    }

    private function findLotForUpdate(array $identity): ?array
    {
        $query = 'SELECT * FROM inv_material_fifo_lot
            WHERE location_scope = ?
              AND division_id <=> ?
              AND destination_type <=> ?
              AND item_id <=> ?
              AND material_id <=> ?
              AND buy_uom_id <=> ?
              AND content_uom_id = ?
              AND profile_key <=> ?
              AND lot_no = ?
            LIMIT 1 FOR UPDATE';

        $row = $this->ci->db->query($query, [
            strtoupper((string)$identity['location_scope']),
            $this->nullableInt($identity['division_id'] ?? null),
            $this->nullableString($identity['destination_type'] ?? null),
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['material_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
            (string)$identity['lot_no'],
        ])->row_array();

        return $row ?: null;
    }

    private function findLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null): array
    {
        $this->ci->db->from('inv_material_fifo_lot')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->order_by('id', 'ASC');
        if ($sourceLineId === null) {
            $this->ci->db->where('source_line_id IS NULL', null, false);
        } else {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        return $this->safeBuilderResultArray('MaterialFifoManager::findLotsBySource');
    }

    private function safeBuilderResultArray(string $context): array
    {
        $this->lastBuilderQueryError = null;
        $sql = $this->ci->db->get_compiled_select('', false);
        $query = $this->ci->db->get();
        if ($query === false) {
            $dbError = $this->ci->db->error();
            $this->lastBuilderQueryError = $context
                . ' query failed: '
                . (string)($dbError['message'] ?? 'unknown DB error');
            log_message(
                'error',
                $this->lastBuilderQueryError
                . ' | SQL: ' . preg_replace('/\s+/', ' ', trim((string)$sql))
            );
            return [];
        }

        return $query->result_array();
    }

    private function findLotById(int $lotId, bool $forUpdate = false): ?array
    {
        if ($lotId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM inv_material_fifo_lot WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $query = $this->ci->db->query($sql, [$lotId]);
        if ($query === false) {
            $error = $this->ci->db->error();
            $message = 'Gagal membaca lot FIFO #' . $lotId . ': ' . (string)($error['message'] ?? 'unknown database error');
            log_message('error', 'MaterialFifoManager::findLotById ' . $message);
            throw new RuntimeException($message);
        }
        $row = $query->row_array();
        return $row ?: null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = $this->ci->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName])->result_array();
        return !empty($rows);
    }

    private function buildMonthlyIdentityKeyFromLotIdentity(array $identity): string
    {
        $profileKey = $this->nullableString($identity['profile_key'] ?? null);
        if ($profileKey !== null) {
            return $profileKey;
        }

        return hash('sha256', implode('|', [
            (string)((int)($identity['item_id'] ?? 0)),
            (string)((int)($identity['material_id'] ?? 0)),
            (string)((int)($identity['buy_uom_id'] ?? 0)),
            (string)((int)($identity['content_uom_id'] ?? 0)),
        ]));
    }

    private function generateLotNo(string $date, array $seedParts): string
    {
        $token = date('Ymd', strtotime($date));
        $hash = strtoupper(substr(hash('sha1', implode('|', $seedParts)), 0, 12));
        return substr('LOT' . $token . '-' . $hash, 0, 80);
    }

    private function generateIssueNo(string $issueDate): string
    {
        $prefix = 'FIF' . date('Ymd', strtotime($issueDate));
        do {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = $prefix . $seq;
            $exists = (int)$this->ci->db->where('issue_no', $no)->count_all_results('inv_material_fifo_issue_log');
        } while ($exists > 0);

        return $no;
    }

    private function normalizeDestinationType(string $destination): ?string
    {
        $destination = strtoupper(trim($destination));
        $allowed = ['GUDANG', 'BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT', 'OFFICE', 'OTHER'];
        return in_array($destination, $allowed, true) ? $destination : null;
    }

    private function ensureActiveMaterialPeriod(string $eventDate, string $operation): array
    {
        if (!file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            return ['ok' => true];
        }

        $this->ci->load->library('InventoryPeriodGuard');
        return $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
            'MATERIAL',
            $eventDate,
            null,
            'Automatic material period from ' . $operation
        );
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = (int)$value;
        return $parsed > 0 ? $parsed : null;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }
}
