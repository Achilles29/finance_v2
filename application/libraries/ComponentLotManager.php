<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ComponentLotManager
{
    /** @var CI_Controller */
    protected $ci;

    /** @var bool */
    protected $schemaEnsured = false;

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

    public function registerProductionInboundLot(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $qtyIn = round((float)($payload['qty_in'] ?? 0), 4);
        $rawUnitCost = round((float)($payload['unit_cost'] ?? 0), 6);
        if ($rawUnitCost < -0.000001) {
            return ['ok' => false, 'message' => 'Biaya unit lot component tidak boleh negatif. Perbaiki valuasi component terlebih dahulu.'];
        }
        $unitCost = max(0, $rawUnitCost);
        $receiptDate = $this->normalizeDate((string)($payload['receipt_date'] ?? ($payload['movement_date'] ?? '')));
        $lotNo = trim((string)($payload['lot_no'] ?? ''));

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'Identitas lot component tidak valid.'];
        }
        if ($qtyIn <= 0) {
            return ['ok' => false, 'message' => 'qty_in lot component wajib lebih besar dari nol.'];
        }
        if ($receiptDate === null) {
            return ['ok' => false, 'message' => 'receipt_date lot component tidak valid.'];
        }
        $period = $this->ensureActiveComponentPeriod($receiptDate, 'membuat lot component inbound');
        if (!($period['ok'] ?? false)) {
            return $period;
        }
        if ($lotNo === '') {
            $lotNo = $this->generateLotNo($receiptDate, $componentId, (int)($payload['source_id'] ?? 0));
        }

        $result = $this->applyLotMutation([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => $this->normalizeDate((string)($payload['expiry_date'] ?? '')),
            'unit_cost' => $unitCost,
            'source_module' => $this->nullableString($payload['source_module'] ?? null),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($payload['parent_lot_id'] ?? null),
            'lot_id' => $this->nullableInt($payload['lot_id'] ?? null),
        ], $qtyIn, 0.0);
        return $this->settleOpenDeficitAfterInbound(
            $result,
            $payload,
            $locationType,
            $divisionId,
            $componentId,
            $uomId,
            $qtyIn,
            $unitCost,
            $receiptDate
        );
    }

    /**
     * A production batch is still a real inbound lot. When it is explicitly
     * marked as a resolver, it also closes older component deficits with the
     * same location, division, component, and UOM without reducing new stock.
     */
    private function settleOpenDeficitAfterInbound(
        array $result,
        array $payload,
        string $locationType,
        ?int $divisionId,
        int $componentId,
        int $uomId,
        float $qtyIn,
        float $unitCost,
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
            'stock_domain' => 'COMPONENT',
            'deficit_date' => $receiptDate,
            'location_scope' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'content_uom_id' => $uomId,
            'qty_available' => $qtyIn,
            'estimated_unit_cost' => $unitCost,
            'source_module' => (string)($payload['source_module'] ?? 'PRODUCTION_BATCH'),
            'source_table' => $sourceTable,
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
            'notes' => 'Batch component menutup defisit dengan identitas component yang sama.',
        ]);
        if (!($settlement['ok'] ?? false)) {
            return $settlement;
        }

        $result['data'] = is_array($result['data'] ?? null) ? $result['data'] : [];
        $result['data']['deficit_settlement'] = $settlement;
        return $result;
    }

    public function consumeUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $selectedLotId = $this->nullableInt($payload['lot_id'] ?? null);
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_out'] ?? ($payload['qty_content_out'] ?? 0)), 4);
        $allowPartialIssue = !empty($payload['allow_partial_issue']);

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0 || $issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian lot component membutuhkan lokasi, komponen, satuan, dan tanggal issue yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_out lot component wajib lebih besar dari nol.'];
        }
        $period = $this->ensureActiveComponentPeriod($issueDate, 'pemakaian lot component');
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $referenceDate = $issueDate;
        $cutoffWindow = $this->resolveMonthCutoffWindow([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
        ], $referenceDate);

        $lots = [];
        if ($selectedLotId !== null) {
            $selectedLot = $this->findLotById($selectedLotId, true);
            if (empty($selectedLot)) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih tidak ditemukan.'];
            }
            if (
                strtoupper(trim((string)($selectedLot['location_type'] ?? ''))) !== $locationType
                || $this->nullableInt($selectedLot['division_id'] ?? null) !== $divisionId
                || (int)($selectedLot['component_id'] ?? 0) !== $componentId
                || (int)($selectedLot['uom_id'] ?? 0) !== $uomId
            ) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih tidak cocok dengan lokasi, divisi, komponen, atau UOM adjustment.'];
            }
            if (strtoupper(trim((string)($selectedLot['status'] ?? ''))) !== 'OPEN' || (float)($selectedLot['qty_balance'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih sudah tidak aktif atau saldonya habis.'];
            }
            $selectedReceiptDate = $this->normalizeDate((string)($selectedLot['receipt_date'] ?? ''));
            if ($selectedReceiptDate !== null && $referenceDate !== null && $selectedReceiptDate > $referenceDate) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih berasal dari tanggal setelah transaksi.'];
            }
            if (
                $cutoffWindow['use_month_cutoff']
                && (
                    $selectedReceiptDate === null
                    || $selectedReceiptDate < (string)$cutoffWindow['month_start']
                    || $selectedReceiptDate >= (string)$cutoffWindow['next_month']
                )
            ) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih berasal dari bulan sebelumnya. Gunakan lot bulan berjalan.'];
            }
            $lots = [$selectedLot];
        } else {
            $lots = $this->findOpenLots([
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'reference_date' => $referenceDate,
            ]);
            if ($this->lastBuilderQueryError !== null) {
                return ['ok' => false, 'message' => $this->lastBuilderQueryError];
            }
        }

        $available = 0.0;
        foreach ($lots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            if (!$allowPartialIssue || $available <= 0.0001) {
                return [
                    'ok' => false,
                    'message' => ($selectedLotId !== null ? 'Saldo lot component yang dipilih tidak cukup. ' : 'Saldo lot component tidak cukup. ')
                        . 'Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
                ];
            }

            $qtyNeed = $available;
        }

        if ($qtyNeed <= 0.0001) {
            return [
                'ok' => false,
                'message' => 'Tidak ada saldo lot component yang dapat dipakai.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $now = date('Y-m-d H:i:s');
        $this->ci->db->insert('inv_component_lot_issue_log', [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => $now,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PRODUCTION_PRODUCT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log issue lot component.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($lots as $lot) {
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

            $mutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_module' => $this->nullableString($lot['source_module'] ?? null),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ], 0.0, $takeQty);
            if (!($mutation['ok'] ?? false)) {
                return $mutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            // Price the actual reduction of the lot's carrying value. Rounding
            // each tiny issue independently otherwise accumulates penny drift.
            $lineCost = round(round($lotBalance * $unitCost, 2)
                - round(($lotBalance - $takeQty) * $unitCost, 2), 2);
            $this->ci->db->insert('inv_component_lot_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($mutation['data']['qty_balance'] ?? 0), 4),
                'created_at' => $now,
            ]);
            if ((int)$this->ci->db->insert_id() <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail issue lot component.'];
            }

            $allocations[] = [
                'lot_id' => $lotId,
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'receipt_date' => (string)($lot['receipt_date'] ?? ''),
            ];
            $remaining = round($remaining - $takeQty, 4);
            $totalCost = round($totalCost + $lineCost, 2);
        }

        if ($remaining > 0.0001) {
            if (!$allowPartialIssue || empty($allocations)) {
                return ['ok' => false, 'message' => 'Issue lot component tidak lengkap.'];
            }
        }

        $this->ci->db->where('id', $issueId)->update('inv_component_lot_issue_log', [
            'total_cost' => $totalCost,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'message' => 'Pemakaian lot component berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
                'issued_qty' => round($qtyNeed, 4),
                'is_partial' => $allowPartialIssue && $available + 0.0001 < (float)($payload['qty_out'] ?? ($payload['qty_content_out'] ?? 0)),
            ],
        ];
    }

    /**
     * Read-only quote for the existing physical-count lot selection policy.
     * openingQty/stockQty account for earlier pending reductions in one document.
     */
    public function quotePhysicalCountReduction(array $identity, float $openingQty, float $stockQty, float $qtyOut): array
    {
        $ready = $this->ensureSchema();
        if (!($ready['ok'] ?? false)) {
            return $ready;
        }
        $lots = $this->findOpenLots($identity);
        if ($this->lastBuilderQueryError !== null) {
            return ['ok' => false, 'message' => 'Daftar lot hitung fisik belum dapat dibaca.'];
        }
        $lotQty = round(array_sum(array_column($lots, 'qty_balance')), 4);
        if (abs($lotQty - $openingQty) > 0.0001 || $stockQty > $openingQty + 0.0001 || $qtyOut > $stockQty + 0.0001) {
            // Existing quantity drift needs the structural reconciliation path;
            // do not pretend its value is an ordinary quantity reduction.
            return ['ok' => true, 'matched' => false];
        }
        $skip = round(max(0, $openingQty - $stockQty), 4);
        $remaining = round($qtyOut, 4);
        $cost = 0.0;
        // Exactly the same LIFO order as the physical-count structural repair.
        foreach (array_reverse($lots) as $lot) {
            $balance = (float)$lot['qty_balance'];
            $skipped = min($skip, $balance);
            $skip = round($skip - $skipped, 4);
            $balance = round($balance - $skipped, 4);
            $take = min($remaining, $balance);
            $unit = (float)$lot['unit_cost'];
            $cost += round(round($balance * $unit, 2) - round(($balance - $take) * $unit, 2), 2);
            $remaining = round($remaining - $take, 4);
            if ($remaining <= 0.0001) { break; }
        }
        return ['ok' => true, 'matched' => $remaining <= 0.0001, 'total_cost' => round($cost, 2)];
    }

    /** Align structural lots without posting a second quantity movement. */
    public function reconcileLotsToAuthoritativeBalance(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $eventDate = $this->normalizeDate((string)($payload['event_date'] ?? ($payload['movement_date'] ?? '')));
        $targetQty = round(max(0, (float)($payload['target_qty'] ?? ($payload['target_qty_content'] ?? 0))), 4);
        $sourceTable = trim((string)($payload['source_table'] ?? ''));

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0 || $eventDate === null || $sourceTable === '') {
            return ['ok' => false, 'message' => 'Rekonsiliasi lot component membutuhkan lokasi, komponen, satuan, tanggal, dan sumber dokumen yang valid.'];
        }

        $identity = [
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'reference_date' => $eventDate,
        ];
        $lots = $this->findOpenLots($identity);
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
                'data' => ['action' => 'NONE', 'lot_qty_before' => $lotQtyBefore, 'target_qty' => $targetQty],
            ];
        }

        $this->ci->load->library('InventoryCutoffAudit');
        if (!$this->ci->inventorycutoffaudit->isReady()) {
            return ['ok' => false, 'message' => 'Audit koreksi lot belum tersedia. Jalankan SQL inventory foundation terlebih dahulu.'];
        }

        $unitCost = max(0, round((float)($payload['unit_cost'] ?? 0), 6));
        $notePrefix = 'Rekonsiliasi hitung fisik: lot component disamakan dengan saldo stok otoritatif.';

        if ($gap > 0) {
            // Use LIFO only for the structural correction so existing FIFO order stays intact.
            $remaining = $gap;
            $changedLots = 0;
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
                $mutation = $this->applyLotMutation([
                    'lot_id' => $lotId,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? $eventDate),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                    'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? $unitCost), 6)),
                    'source_module' => $this->nullableString($lot['source_module'] ?? null),
                    'source_table' => $this->nullableString($lot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                ], 0.0, $qty);
                if (!($mutation['ok'] ?? false)) {
                    return $mutation;
                }

                $audit = $this->ci->inventorycutoffaudit->record([
                    'stock_domain' => 'COMPONENT',
                    'event_date' => $eventDate,
                    'location_scope' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'content_uom_id' => $uomId,
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
                return ['ok' => false, 'message' => 'Lot component tidak cukup untuk disamakan dengan hasil hitung fisik.'];
            }

            return [
                'ok' => true,
                'data' => [
                    'action' => 'LOT_DECREASE',
                    'lot_qty_before' => $lotQtyBefore,
                    'target_qty' => $targetQty,
                    'qty_changed' => $gap,
                    'lot_count' => $changedLots,
                ],
            ];
        }

        $qtyIn = abs($gap);
        $lotNo = 'RECON-' . date('Ymd', strtotime($eventDate))
            . '-C' . $componentId
            . '-D' . (int)($divisionId ?? 0)
            . '-S' . (int)($payload['source_line_id'] ?? 0);
        $inbound = $this->registerProductionInboundLot([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'qty_in' => $qtyIn,
            'unit_cost' => $unitCost,
            'receipt_date' => $eventDate,
            'lot_no' => $lotNo,
            'source_module' => 'INVENTORY_RECONCILIATION',
            'source_table' => $sourceTable,
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
        ]);
        if (!($inbound['ok'] ?? false)) {
            return $inbound;
        }

        $audit = $this->ci->inventorycutoffaudit->record([
            'stock_domain' => 'COMPONENT',
            'event_date' => $eventDate,
            'location_scope' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'content_uom_id' => $uomId,
            'lot_id' => $inbound['data']['id'] ?? null,
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
                'target_qty' => $targetQty,
                'qty_changed' => $qtyIn,
                'lot_id' => (int)($inbound['data']['id'] ?? 0),
            ],
        ];
    }

    public function rollbackIssueLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = '', ?float $rollbackQty = null, bool $allowPartialIncomplete = false): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        if ($sourceTable === '' || $sourceId <= 0) {
            return ['ok' => false, 'message' => 'Sumber rollback lot component tidak valid.'];
        }

        $this->ci->db->from('inv_component_lot_issue_log')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'POSTED');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $issueLogs = $this->ci->db->order_by('id', 'DESC')->get()->result_array();
        if (empty($issueLogs)) {
            return ['ok' => true, 'data' => ['issue_count' => 0, 'rolled_qty' => 0.0, 'allocations' => []]];
        }

        $voided = 0;
        $remaining = $rollbackQty !== null ? round(max(0, $rollbackQty), 4) : null;
        $rolledQty = 0.0;
        $rolledCost = 0.0;
        $allocations = [];
        foreach ($issueLogs as $log) {
            if ($remaining !== null && $remaining <= 0.0001) {
                break;
            }

            $lines = $this->ci->db->from('inv_component_lot_issue_line')
                ->where('issue_id', (int)($log['id'] ?? 0))
                ->order_by('id', 'DESC')
                ->get()
                ->result_array();
            $issueRolledQty = 0.0;
            foreach ($lines as $line) {
                if ($remaining !== null && $remaining <= 0.0001) {
                    break;
                }

                $lot = $this->findLotById((int)($line['lot_id'] ?? 0), true);
                if (!$lot) {
                    return ['ok' => false, 'message' => 'Lot component untuk rollback issue tidak ditemukan.'];
                }
                $qtyOut = round((float)($line['qty_out'] ?? 0), 4);
                $rollbackLineQty = $remaining === null ? $qtyOut : round(min($qtyOut, $remaining), 4);
                if ($rollbackLineQty <= 0) {
                    continue;
                }
                $unitCost = max(0, round((float)($line['unit_cost'] ?? ($lot['unit_cost'] ?? 0)), 6));
                $newIssueQty = round($qtyOut - $rollbackLineQty, 4);
                $returnCost = round((float)$line['total_cost'] - round($newIssueQty * $unitCost, 2), 2);
                // Preserve a legitimate revaluation of the remaining stock.
                // Returning old-cost stock must not reprice the entire lot.
                $lotQty = round((float)($lot['qty_balance'] ?? 0), 4);
                $restoredUnitCost = round((round($lotQty * (float)$lot['unit_cost'], 2) + $returnCost) / ($lotQty + $rollbackLineQty), 6);
                $rollback = $this->applyLotMutation([
                    'lot_id' => (int)$lot['id'],
                    'location_type' => (string)$lot['location_type'],
                    'division_id' => $this->nullableInt($lot['division_id'] ?? null),
                    'component_id' => (int)$lot['component_id'],
                    'uom_id' => (int)$lot['uom_id'],
                    'lot_no' => (string)$lot['lot_no'],
                    'receipt_date' => (string)$lot['receipt_date'],
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                    'unit_cost' => $restoredUnitCost,
                    'source_module' => $this->nullableString($lot['source_module'] ?? null),
                    'source_table' => $this->nullableString($lot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                ], 0.0, -1 * $rollbackLineQty);
                if (!($rollback['ok'] ?? false)) {
                    return $rollback;
                }

                $this->ci->db->where('id', (int)($line['id'] ?? 0))->update('inv_component_lot_issue_line', [
                    'qty_out' => $newIssueQty,
                    'total_cost' => round($newIssueQty * $unitCost, 2),
                    'source_balance_after' => round((float)($line['source_balance_after'] ?? 0) + $rollbackLineQty, 4),
                ]);
                if ($this->ci->db->trans_status() === false) {
                    return ['ok' => false, 'message' => 'Gagal update detail issue lot component saat rollback.'];
                }

                $issueRolledQty = round($issueRolledQty + $rollbackLineQty, 4);
                $rolledQty = round($rolledQty + $rollbackLineQty, 4);
                $rolledCost = round($rolledCost + $returnCost, 2);
                if ($remaining !== null) {
                    $remaining = round($remaining - $rollbackLineQty, 4);
                }
                $allocations[] = [
                    'issue_id' => (int)($log['id'] ?? 0),
                    'issue_line_id' => (int)($line['id'] ?? 0),
                    'component_id' => (int)$lot['component_id'],
                    'uom_id' => (int)$lot['uom_id'],
                    'location_type' => (string)$lot['location_type'],
                    'division_id' => $lot['division_id'],
                    'source_line_id' => $log['source_line_id'] ?? null,
                    'lot_id' => (int)($line['lot_id'] ?? 0),
                    'qty_rolled' => $rollbackLineQty,
                    'qty_remaining' => $newIssueQty,
                    'total_cost' => $returnCost,
                ];
            }

            $notes = trim((string)($log['notes'] ?? ''));
            $note = trim($voidNote);
            $remainingIssue = $this->ci->db->select('COALESCE(SUM(qty_out),0) AS qty_out, COALESCE(SUM(total_cost),0) AS total_cost', false)
                ->from('inv_component_lot_issue_line')
                ->where('issue_id', (int)($log['id'] ?? 0))
                ->get()
                ->row_array() ?: ['qty_out' => 0, 'total_cost' => 0];
            $issueQtyAfter = round((float)($remainingIssue['qty_out'] ?? 0), 4);
            $issueStatus = $issueQtyAfter <= 0.0001 ? 'VOID' : 'POSTED';
            if ($issueStatus === 'VOID' && $note !== '') {
                $notes = $notes !== '' ? ($notes . ' | ' . $note) : $note;
            } elseif ($issueRolledQty > 0) {
                $partialNote = 'Partial rollback ' . rtrim(rtrim(number_format($issueRolledQty, 4, '.', ''), '0'), '.');
                $notes = $notes !== '' ? ($notes . ' | ' . $partialNote) : $partialNote;
            }
            $this->ci->db->where('id', (int)($log['id'] ?? 0))->update('inv_component_lot_issue_log', [
                'issue_qty' => $issueQtyAfter,
                'total_cost' => round((float)($remainingIssue['total_cost'] ?? 0), 2),
                'status' => $issueStatus,
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($issueStatus === 'VOID') {
                $voided++;
            }
        }

        if ($remaining !== null && $remaining > 0.0001) {
            if (!$allowPartialIncomplete) {
                return ['ok' => false, 'message' => 'Rollback lot component tidak lengkap.'];
            }
            return [
                'ok' => true,
                'message' => 'Rollback lot component parsial. Sisa akan dipulihkan lewat fallback.',
                'data' => [
                    'issue_count' => $voided,
                    'rolled_qty' => $rolledQty,
                    'rolled_cost' => $rolledCost,
                    'remaining_qty' => round($remaining, 4),
                    'is_partial' => true,
                    'allocations' => $allocations,
                ],
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'issue_count' => $voided,
                'rolled_qty' => $rolledQty,
                'rolled_cost' => $rolledCost,
                'remaining_qty' => 0.0,
                'is_partial' => false,
                'allocations' => $allocations,
            ],
        ];
    }

    public function voidInboundLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = ''): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        if ($sourceTable === '' || $sourceId <= 0) {
            return ['ok' => false, 'message' => 'Sumber void lot component tidak valid.'];
        }

        $this->ci->db->from('inv_component_lot')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status <>', 'VOID');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $lots = $this->ci->db->order_by('id', 'DESC')->get()->result_array();
        if (empty($lots)) {
            return ['ok' => true, 'data' => ['lot_count' => 0]];
        }

        $allocations = [];
        foreach ($lots as $lot) {
            $lot = $this->findLotById((int)$lot['id'], true);
            if (round((float)($lot['qty_out_total'] ?? 0), 4) > 0.0001) {
                return ['ok' => false, 'message' => 'Lot output component sudah pernah dipakai sehingga tidak bisa di-void.'];
            }
            $allocations[] = [
                'lot_id' => (int)$lot['id'], 'component_id' => (int)$lot['component_id'],
                'uom_id' => (int)$lot['uom_id'], 'location_type' => (string)$lot['location_type'],
                'division_id' => $lot['division_id'], 'source_line_id' => $lot['source_line_id'] ?? null,
                'qty_rolled' => (float)$lot['qty_balance'],
                'total_cost' => round((float)$lot['qty_balance'] * (float)$lot['unit_cost'], 2),
            ];
            $notes = trim((string)($lot['source_module'] ?? ''));
            if ($voidNote !== '') {
                $notes = $notes !== '' ? ($notes . ' | ' . $voidNote) : $voidNote;
            }
            $this->ci->db->where('id', (int)($lot['id'] ?? 0))->update('inv_component_lot', [
                'qty_balance' => 0,
                'status' => 'VOID',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['ok' => true, 'data' => ['lot_count' => count($lots), 'allocations' => $allocations]];
    }

    public function closeCarryForwardSourceLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $referenceDate = $this->normalizeDate((string)($payload['reference_date'] ?? $payload['movement_date'] ?? $payload['issue_date'] ?? ''));

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0 || $referenceDate === null) {
            return ['ok' => false, 'message' => 'Identitas carry-forward lot component tidak valid.'];
        }

        $monthStart = date('Y-m-01', strtotime($referenceDate));
        $now = date('Y-m-d H:i:s');

        $this->ci->db->from('inv_component_lot')
            ->where('location_type', $locationType)
            ->where('division_id', $divisionId)
            ->where('component_id', $componentId)
            ->where('uom_id', $uomId)
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0, false)
            ->where('receipt_date <', $monthStart);
        $lots = $this->ci->db->get()->result_array();
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

            $newQtyOut = round((float)($lot['qty_out_total'] ?? 0) + $balance, 4);
            $this->ci->db->where('id', $lotId)->update('inv_component_lot', [
                'qty_out_total' => $newQtyOut,
                'qty_balance' => 0,
                'status' => 'CLOSED',
                'updated_at' => $now,
            ]);
            $closed++;
        }

        return ['ok' => true, 'data' => ['closed_count' => $closed]];
    }

    public function listLots(array $filters = [], int $limit = 200): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'OPEN')));
        if (!in_array($status, ['OPEN', 'CLOSED', 'VOID', 'ALL'], true)) {
            $status = 'OPEN';
        }
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = $this->nullableInt($filters['division_id'] ?? null);
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        if (!in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $componentType = '';
        }

        $db = $this->ci->db;
        $db->select('l.*, c.component_code, c.component_name, c.component_type, u.code AS uom_code, d.name AS division_name, b.batch_no, b.batch_date');
        $db->from('inv_component_lot l');
        $db->join('mst_component c', 'c.id = l.component_id', 'left');
        $db->join('mst_uom u', 'u.id = l.uom_id', 'left');
        $db->join('mst_operational_division d', 'd.id = l.division_id', 'left');
        if ($db->table_exists('inv_component_batch')) {
            $db->join('inv_component_batch b', 'b.id = l.source_id AND l.source_table = ' . $db->escape('inv_component_batch'), 'left', false);
        }

        if ($q !== '') {
            $db->group_start()
                ->like('l.lot_no', $q)
                ->or_like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->or_like('b.batch_no', $q)
                ->group_end();
        }
        if ($divisionId !== null) {
            $db->where('l.division_id', $divisionId);
        }
        if ($locationType === 'REGULER') {
            $db->where_in('l.location_type', ['BAR', 'KITCHEN', 'ROASTERY']);
        } elseif ($locationType === 'EVENT') {
            $db->where_in('l.location_type', ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT']);
        } elseif ($this->validLocation($locationType)) {
            $db->where('l.location_type', $locationType);
        }
        if ($status !== 'ALL') {
            $db->where('l.status', $status);
        }
        if ($componentType !== '') {
            $db->where('c.component_type', $componentType);
        }
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo   = trim((string)($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $db->where('l.receipt_date >=', $dateFrom);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $db->where('l.receipt_date <=', $dateTo);
        }

        $db->order_by('CASE WHEN l.status = "OPEN" THEN 0 WHEN l.status = "CLOSED" THEN 1 ELSE 2 END', '', false);
        $db->order_by('d.name', 'ASC');
        $db->order_by("CASE UPPER(c.component_type) WHEN 'BASE' THEN 0 WHEN 'PREPARE' THEN 1 ELSE 9 END", '', false);
        $db->order_by('c.component_name', 'ASC');
        $db->order_by('l.receipt_date', 'ASC');
        $db->order_by('l.id', 'ASC');
        $db->limit(max(1, $limit));
        return $db->get()->result_array();
    }

    private function ensureSchema(): array
    {
        if ($this->schemaEnsured) {
            return ['ok' => true];
        }

        $requirements = [
            'inv_component_lot' => [
                'location_type', 'division_id', 'component_id', 'uom_id', 'lot_no',
                'receipt_date', 'unit_cost', 'qty_in_total', 'qty_out_total',
                'qty_balance', 'source_table', 'source_id', 'source_line_id',
                'status', 'created_at', 'updated_at',
            ],
            'inv_component_lot_issue_log' => [
                'issue_no', 'issue_date', 'issue_datetime', 'location_type',
                'division_id', 'component_id', 'uom_id', 'issue_qty', 'total_cost',
                'source_table', 'source_id', 'source_line_id', 'status',
                'created_at', 'updated_at',
            ],
            'inv_component_lot_issue_line' => [
                'issue_id', 'lot_id', 'qty_out', 'unit_cost', 'total_cost',
                'source_balance_before', 'source_balance_after', 'created_at',
            ],
        ];

        foreach ($requirements as $table => $fields) {
            if (!$this->ci->db->table_exists($table)) {
                return [
                    'ok' => false,
                    'message' => 'Tabel lot component ' . $table . ' belum tersedia. Jalankan migration 2026-08-17c_inventory_lot_schema_preflight.sql terlebih dahulu.',
                ];
            }

            $available = $this->ci->db->list_fields($table) ?: [];
            $missing = array_values(array_diff($fields, $available));
            if ($missing) {
                return [
                    'ok' => false,
                    'message' => 'Struktur lot component ' . $table . ' belum lengkap (' . implode(', ', $missing) . '). Jalankan migration 2026-08-17c_inventory_lot_schema_preflight.sql terlebih dahulu.',
                ];
            }
        }

        $this->schemaEnsured = true;
        return ['ok' => true];
    }

    private function applyLotMutation(array $identity, float $qtyIn, float $qtyOut): array
    {
        $lot = [];
        $lotId = $this->nullableInt($identity['lot_id'] ?? null);
        if ($lotId !== null) {
            $lot = $this->findLotById($lotId, true);
        } else {
            $lot = $this->ci->db->query(
                'SELECT * FROM inv_component_lot WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND lot_no = ? LIMIT 1 FOR UPDATE',
                [
                    (string)$identity['location_type'],
                    $this->nullableInt($identity['division_id'] ?? null),
                    (int)$identity['component_id'],
                    (int)$identity['uom_id'],
                    (string)$identity['lot_no'],
                ]
            )->row_array();
        }

        $qtyIn = round($qtyIn, 4);
        $qtyOut = round($qtyOut, 4);
        $qtyInTotal = round((float)($lot['qty_in_total'] ?? 0) + $qtyIn, 4);
        $qtyOutTotal = round((float)($lot['qty_out_total'] ?? 0) + $qtyOut, 4);
        $qtyBalance = round((float)($lot['qty_balance'] ?? 0) + $qtyIn - $qtyOut, 4);
        if ($qtyBalance < -0.0001) {
            return ['ok' => false, 'message' => 'Saldo lot component menjadi negatif.'];
        }
        if (abs($qtyBalance) < 0.0001) {
            $qtyBalance = 0.0;
        }

        $status = $qtyBalance > 0 ? 'OPEN' : 'CLOSED';
        $now = date('Y-m-d H:i:s');
        $data = [
            'location_type' => (string)$identity['location_type'],
            'division_id' => $this->nullableInt($identity['division_id'] ?? null),
            'component_id' => (int)$identity['component_id'],
            'uom_id' => (int)$identity['uom_id'],
            'lot_no' => (string)$identity['lot_no'],
            'receipt_date' => (string)$identity['receipt_date'],
            'expiry_date' => $this->normalizeDate((string)($identity['expiry_date'] ?? '')),
            'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
            'qty_in_total' => $qtyInTotal,
            'qty_out_total' => $qtyOutTotal,
            'qty_balance' => $qtyBalance,
            'source_module' => $this->nullableString($identity['source_module'] ?? null),
            'source_table' => $this->nullableString($identity['source_table'] ?? null),
            'source_id' => $this->nullableInt($identity['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($identity['source_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($identity['parent_lot_id'] ?? null),
            'last_issue_at' => $qtyOut > 0 ? $now : ($lot['last_issue_at'] ?? null),
            'status' => $status,
            'updated_at' => $now,
        ];

        if (!empty($lot['id'])) {
            $this->ci->db->where('id', (int)$lot['id'])->update('inv_component_lot', $data);
            $lotId = (int)$lot['id'];
        } else {
            $data['created_at'] = $now;
            $this->ci->db->insert('inv_component_lot', $data);
            $lotId = (int)$this->ci->db->insert_id();
        }

        if ($lotId <= 0) {
            return ['ok' => false, 'message' => 'Gagal menyimpan mutasi lot component.'];
        }

        $saved = $this->findLotById($lotId, false);
        return ['ok' => true, 'data' => $saved ?: ['id' => $lotId, 'qty_balance' => $qtyBalance]];
    }

    private function findOpenLots(array $identity): array
    {
        $this->ci->db->from('inv_component_lot')
            ->where('location_type', (string)$identity['location_type'])
            ->where('division_id', $this->nullableInt($identity['division_id'] ?? null))
            ->where('component_id', (int)$identity['component_id'])
            ->where('uom_id', (int)$identity['uom_id'])
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC');

        $referenceDate = $this->normalizeDate((string)($identity['reference_date'] ?? ''));
        if ($referenceDate !== null) {
            $this->ci->db->where('receipt_date <=', $referenceDate);
        }

        $cutoffWindow = $this->resolveMonthCutoffWindow($identity, $referenceDate);
        if ($cutoffWindow['use_month_cutoff']) {
            $this->ci->db->where('receipt_date >=', (string)$cutoffWindow['month_start']);
            $this->ci->db->where('receipt_date <', (string)$cutoffWindow['next_month']);
        }

        return $this->safeBuilderResultArray('ComponentLotManager::findOpenLots');
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

    private function resolveMonthCutoffWindow(array $identity, ?string $referenceDate): array
    {
        $context = [
            'use_month_cutoff' => false,
            'month_start' => null,
            'next_month' => null,
        ];

        if ($referenceDate === null || !$this->ci->db->table_exists('inv_component_lot')) {
            return $context;
        }

        $locationType = strtoupper(trim((string)($identity['location_type'] ?? '')));
        $divisionId = $this->nullableInt($identity['division_id'] ?? null);
        $componentId = $this->nullableInt($identity['component_id'] ?? null);
        $uomId = $this->nullableInt($identity['uom_id'] ?? null);
        if (!$this->validLocation($locationType) || $componentId === null || $uomId === null) {
            return $context;
        }

        $monthStart = date('Y-m-01', strtotime($referenceDate));
        $nextMonth = date('Y-m-01', strtotime($monthStart . ' +1 month'));
        $hasOpeningRow = $this->ci->db->query(
            'SELECT id
             FROM inv_component_lot
             WHERE location_type = ?
               AND division_id <=> ?
               AND component_id = ?
               AND uom_id = ?
               AND status = ?
               AND qty_balance > 0
               AND source_table = ?
               AND receipt_date >= ?
               AND receipt_date < ?
             LIMIT 1',
            [
                $locationType,
                $divisionId,
                $componentId,
                $uomId,
                'OPEN',
                'inv_component_monthly_opening',
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

    private function findLotById(int $lotId, bool $forUpdate): ?array
    {
        if ($lotId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM inv_component_lot WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->ci->db->query($sql, [$lotId])->row_array();
        return $row ?: null;
    }

    private function validLocation(string $locationType): bool
    {
        return in_array($locationType, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true);
    }

    private function ensureActiveComponentPeriod(string $eventDate, string $operation): array
    {
        if (!file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            return ['ok' => true];
        }

        $this->ci->load->library('InventoryPeriodGuard');
        return $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
            'COMPONENT',
            $eventDate,
            null,
            'Automatic component period from ' . $operation
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
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function generateLotNo(string $receiptDate, int $componentId, int $sourceId): string
    {
        $datePart = date('Ymd', strtotime($receiptDate));
        return 'ICL' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT);
    }

    private function generateIssueNo(string $issueDate): string
    {
        $datePart = date('Ymd', strtotime($issueDate));
        $prefix = 'ICI' . $datePart;
        $row = $this->ci->db->select('issue_no')
            ->from('inv_component_lot_issue_log')
            ->like('issue_no', $prefix, 'after')
            ->order_by('issue_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $seq = 1;
        if (!empty($row['issue_no'])) {
            $suffix = substr((string)$row['issue_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}
