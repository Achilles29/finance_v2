<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ComponentStockWriter
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    private function resolve_inventory_destination_type_for_division(string $fallbackLocationType, ?int $divisionId): ?string
    {
        $fallbackLocationType = strtoupper(trim($fallbackLocationType));
        if ($divisionId === null || $divisionId <= 0) {
            return $this->resolve_inventory_destination_type($fallbackLocationType);
        }

        $division = $this->ci->db
            ->select('code, name')
            ->from('mst_operational_division')
            ->where('id', (int)$divisionId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $divisionCode = strtoupper(trim((string)($division['code'] ?? $division['name'] ?? '')));
        $isEvent = in_array($fallbackLocationType, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true);
        if ($divisionCode === 'BAR') {
            return $isEvent ? 'BAR_EVENT' : 'BAR';
        }
        if ($divisionCode === 'KITCHEN') {
            return $isEvent ? 'KITCHEN_EVENT' : 'KITCHEN';
        }
        if ($divisionCode === 'ROASTERY') {
            return $isEvent ? 'ROASTERY_EVENT' : 'ROASTERY';
        }

        return $this->resolve_inventory_destination_type($fallbackLocationType);
    }

    public function post_opening(array $header, array $lines, int $actorEmployeeId = 0): array
    {
        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header['opening_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal dokumen tidak valid.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris untuk diposting.'];
        }
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $movementDate,
                null,
                'Automatic component period from opening'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        $db->trans_start();
        try {
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $qty = round((float)($line['qty'] ?? $line['opening_qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                    continue;
                }

                $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== null
                    ? round((float)$line['unit_cost'], 6)
                    : $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);
                $sourceLineId = isset($line['source_line_id']) ? (int)$line['source_line_id'] : (isset($line['id']) ? (int)$line['id'] : null);
                $receiptDate = trim((string)($line['received_date'] ?? $movementDate));
                if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $receiptDate)) {
                    $receiptDate = $movementDate;
                }
                $lotNo = trim((string)($line['lot_no'] ?? ''));
                if ($lotNo === '') {
                    $lotNo = $this->generate_component_opening_lot_no($movementDate, $componentId, $sourceId, (int)$sourceLineId);
                }

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => 'OPENING',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'source_module' => 'PRODUCTION_OPENING',
                    'source_table' => 'inv_component_opening',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $sourceLineId,
                    'notes' => (string)($line['note'] ?? $line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);

                $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'qty_in' => $qty,
                    'unit_cost' => $unitCost,
                    'lot_no' => $lotNo,
                    'receipt_date' => $receiptDate,
                    'source_module' => 'PRODUCTION_OPENING',
                    'source_table' => 'inv_component_opening',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $sourceLineId,
                ]);
                if (!($lotRegister['ok'] ?? false)) {
                    throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot opening component gagal.'));
                }
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting opening gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_OPENING',
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    public function post_adjustment(array $header, array $lines, int $actorEmployeeId = 0, int $actorUserId = 0): array
    {
        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)($header['division_id']) : null;
        $movementDate = (string)($header['adjustment_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi adjustment tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal adjustment tidak valid.'];
        }
        if ($movementDate > date('Y-m-d')) {
            return [
                'ok' => false,
                'message' => 'Adjustment component bertanggal masa depan tidak dapat diposting karena akan langsung mengubah stok live.',
            ];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris adjustment untuk diposting.'];
        }
        if ($sourceId <= 0) {
            return ['ok' => false, 'message' => 'Dokumen adjustment tidak valid untuk posting.'];
        }
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $movementDate,
                $actorUserId > 0 ? $actorUserId : null,
                'Automatic component period from adjustment'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        $originalDbDebug = isset($db->db_debug) ? (bool)$db->db_debug : false;
        $db->db_debug = false;
        $db->trans_start();
        try {
            $this->ci->load->library('InventoryAdjustmentGuard');
            $locked = $this->ci->inventoryadjustmentguard->lock('COMPONENT', $sourceId, 'DRAFT', $movementDate);
            if (empty($locked['ok'])) { throw new RuntimeException($locked['message']); }
            $header = $locked['header'];
            $locationType = (string)$header['location_type'];
            $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
            $lineQuery = $db->from('inv_component_adjustment_line')->where('adjustment_id', $sourceId)->order_by('id', 'ASC')->get();
            $lines = $lineQuery ? $lineQuery->result_array() : [];
            if (empty($lines)) { throw new RuntimeException('Baris adjustment sudah berubah. Muat ulang.'); }
            $rebuildIdentities = [];
            $physicalReconcileIdentities = [];
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;
                $note = (string)($line['note'] ?? '');
                $isPhysicalCount = strtoupper(trim((string)($line['input_mode'] ?? 'DELTA'))) === 'PHYSICAL_COUNT';
                if ($componentId <= 0 || $uomId <= 0) {
                    continue;
                }

                $rebuildKey = implode('|', [
                    $locationType,
                    $divisionId !== null ? (string)$divisionId : 'NULL',
                    (string)$componentId,
                    (string)$uomId,
                ]);
                $rebuildIdentities[$rebuildKey] = [
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                ];
                if ($isPhysicalCount && !isset($physicalReconcileIdentities[$rebuildKey])) {
                    $physicalReconcileIdentities[$rebuildKey] = [
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'source_line_id' => $sourceLineId,
                        'opening_qty' => (float)($this->load_balance_state($locationType, $divisionId, $componentId, $uomId, $movementDate)['qty_on_hand'] ?? 0),
                    ];
                }

                $outMovements = [
                    'SPOIL' => round((float)($line['qty_spoil'] ?? 0), 4),
                    'WASTE' => round((float)($line['qty_waste'] ?? 0), 4),
                    'ADJUSTMENT_MINUS' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                ];
                foreach ($outMovements as $movementType => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }
                    // Physical count is authoritative. Post its stock movement first,
                    // then reconcile lot structure after all component movements rebuild.
                    $avgUnitCost = round((float)($line['unit_cost'] ?? 0), 6);
                    $allocations = [];
                    $issuedQty = 0.0;
                    $availableStockQty = max(0, round((float)($this->load_balance_state(
                        $locationType,
                        $divisionId,
                        $componentId,
                        $uomId,
                        $movementDate
                    )['qty_on_hand'] ?? 0), 4));
                    if (!$isPhysicalCount) {
                        if ($qty > $availableStockQty + 0.0001) {
                            throw new RuntimeException($this->format_component_adjustment_error(
                                $componentId,
                                $uomId,
                                'Qty minus ' . number_format($qty, 4, ',', '.')
                                    . ' melebihi stok aktif ' . number_format($availableStockQty, 4, ',', '.')
                                    . '. Gunakan Daily Recon bila angka tersebut adalah hasil hitung fisik.'
                            ));
                        }

                        $lotIssue = $this->ci->componentlotmanager->consumeUsage([
                            'issue_date' => $movementDate,
                            'location_type' => $locationType,
                            'division_id' => $divisionId,
                            'component_id' => $componentId,
                            'uom_id' => $uomId,
                            'lot_id' => !empty($line['selected_lot_id']) ? (int)$line['selected_lot_id'] : null,
                            'qty_out' => $qty,
                            'source_module' => 'PRODUCTION_ADJUSTMENT',
                            'source_table' => 'inv_component_adjustment',
                            'source_id' => $sourceId > 0 ? $sourceId : null,
                            'source_line_id' => $sourceLineId,
                            'notes' => $note !== '' ? $note : ('Adjustment ' . $movementType),
                            'allow_partial_issue' => false,
                        ]);
                        if (!($lotIssue['ok'] ?? false)) {
                            $lotMessage = (string)($lotIssue['message'] ?? 'Posting issue lot adjustment gagal.');
                            throw new RuntimeException($this->format_component_adjustment_error(
                                $componentId,
                                $uomId,
                                'Saldo component dan lot FIFO tidak dapat dikurangi penuh. Periksa Kesehatan Stok atau lakukan Daily Recon. Detail: ' . $lotMessage
                            ));
                        }

                        $issuedQty = round((float)($lotIssue['data']['issued_qty'] ?? $qty), 4);
                        if (abs($issuedQty - $qty) > 0.0001) {
                            throw new RuntimeException($this->format_component_adjustment_error(
                                $componentId,
                                $uomId,
                                'Alokasi lot hanya ' . number_format($issuedQty, 4, ',', '.')
                                    . ' dari ' . number_format($qty, 4, ',', '.') . '. Tidak ada defisit yang dibuat.'
                            ));
                        }
                        $avgUnitCost = round((float)($lotIssue['data']['avg_unit_cost'] ?? $avgUnitCost), 6);
                        $allocations = (array)($lotIssue['data']['allocations'] ?? []);
                    }

                    if ($isPhysicalCount && $avgUnitCost <= 0) {
                        $avgUnitCost = $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);
                    }
                    $qtyForStock = $isPhysicalCount ? $qty : $issuedQty;
                    $movementCost = $isPhysicalCount ? round($qtyForStock * $avgUnitCost, 2) : (float)$lotIssue['data']['total_cost'];
                    if ($isPhysicalCount) {
                        $quote = $this->ci->componentlotmanager->quotePhysicalCountReduction(
                            $rebuildIdentities[$rebuildKey] + ['reference_date' => $movementDate],
                            (float)$physicalReconcileIdentities[$rebuildKey]['opening_qty'], $availableStockQty, $qtyForStock
                        );
                        if (!($quote['ok'] ?? false)) { throw new RuntimeException($quote['message']); }
                        if (!empty($quote['matched'])) {
                            $movementCost = (float)$quote['total_cost'];
                            $avgUnitCost = $qtyForStock > 0 ? round($movementCost / $qtyForStock, 6) : 0.0;
                        }
                    }
                    if ($qtyForStock > 0.0001) {
                        $lotSnapshot = !empty($allocations[0]['lot_no']) ? (string)$allocations[0]['lot_no'] : null;
                        $movementNote = $note;
                        $this->post_single_movement([
                            'movement_date' => $movementDate,
                            'location_type' => $locationType,
                            'division_id' => $divisionId,
                            'component_id' => $componentId,
                            'uom_id' => $uomId,
                            'movement_type' => $movementType,
                            'qty' => $qtyForStock,
                            'unit_cost' => $avgUnitCost,
                            'total_cost' => $movementCost,
                            'source_module' => 'PRODUCTION_ADJUSTMENT',
                            'source_table' => 'inv_component_adjustment',
                            'source_id' => $sourceId > 0 ? $sourceId : null,
                            'source_line_id' => $sourceLineId,
                            'lot_no_snapshot' => $lotSnapshot,
                            'notes' => $movementNote,
                            'actor_employee_id' => $actorEmployeeId,
                        ]);
                    }
                }

                $qtyPlusInput = round((float)($line['qty_adjust_pos'] ?? 0), 4);
                $qtyPlus = $qtyPlusInput;
                if ($qtyPlusInput > 0 && file_exists(APPPATH . 'libraries/InventoryDeficitService.php')) {
                    $this->ci->load->library('InventoryAdjustmentIntent');
                    $settlementPlan = $this->ci->inventoryadjustmentintent->resolveDeficitSettlement(
                        (string)($line['input_mode'] ?? 'DELTA'),
                        !empty($line['settle_open_deficit']),
                        $qtyPlusInput,
                        $isPhysicalCount ? (float)($line['physical_qty_snapshot'] ?? 0) : null
                    );
                    if (!empty($settlementPlan['should_settle'])) {
                        $this->ci->load->library('InventoryDeficitService');
                        if ($this->ci->inventorydeficitservice->isReady()) {
                            $settlement = $this->ci->inventorydeficitservice->settle([
                                'stock_domain' => 'COMPONENT',
                                'deficit_date' => $movementDate,
                                'location_scope' => $locationType,
                                'division_id' => $divisionId,
                                'component_id' => $componentId,
                                'content_uom_id' => $uomId,
                                'qty_available' => (float)($settlementPlan['qty_available'] ?? 0),
                                'estimated_unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                                'source_module' => 'PRODUCTION_ADJUSTMENT',
                                'source_table' => 'inv_component_adjustment',
                                'source_id' => $sourceId > 0 ? $sourceId : null,
                                'source_line_id' => $sourceLineId,
                                'notes' => 'Adjustment component menyelesaikan defisit stok terlebih dahulu.',
                            ]);
                            if (!($settlement['ok'] ?? false)) {
                                throw new RuntimeException((string)($settlement['message'] ?? 'Gagal menyelesaikan defisit component.'));
                            }
                            if ($this->ci->db->field_exists('deficit_settled_qty', 'inv_component_adjustment_line')) {
                                $this->ci->db->where('id', $sourceLineId)->update('inv_component_adjustment_line', [
                                    'deficit_settled_qty' => round((float)($settlement['settled_qty'] ?? 0), 4),
                                ]);
                            }
                            // Do not consume newly counted stock merely to close a
                            // historical deficit. The inbound lot keeps full qty.
                            $qtyPlus = $qtyPlusInput;
                        }
                    }
                }

                if ($qtyPlus > 0) {
                    $unitCost = isset($line['unit_cost']) ? round((float)$line['unit_cost'], 6) : 0.0;
                    if ($unitCost <= 0) {
                        $unitCost = $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);
                    }
                    if ($isPhysicalCount) {
                        $physicalReconcileIdentities[$rebuildKey]['inbound_qty'] = (float)($physicalReconcileIdentities[$rebuildKey]['inbound_qty'] ?? 0) + $qtyPlus;
                        $physicalReconcileIdentities[$rebuildKey]['inbound_value'] = (float)($physicalReconcileIdentities[$rebuildKey]['inbound_value'] ?? 0) + round($qtyPlus * $unitCost, 2);
                        $physicalReconcileIdentities[$rebuildKey]['inbound_unit_cost'] = round($physicalReconcileIdentities[$rebuildKey]['inbound_value'] / $physicalReconcileIdentities[$rebuildKey]['inbound_qty'], 6);
                    }
                    $lotNo = $this->generate_component_adjustment_lot_no($movementDate, $componentId, $sourceId, (int)$sourceLineId, 'P');
                    $this->post_single_movement([
                        'movement_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'movement_type' => 'ADJUSTMENT_PLUS',
                        'qty' => $qtyPlus,
                        'unit_cost' => $unitCost,
                        'source_module' => 'PRODUCTION_ADJUSTMENT',
                        'source_table' => 'inv_component_adjustment',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                        'lot_no_snapshot' => $lotNo,
                        'received_date_snapshot' => $movementDate,
                        'notes' => $note,
                        'actor_employee_id' => $actorEmployeeId,
                    ]);

                    if (!$isPhysicalCount) {
                        $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                            'location_type' => $locationType,
                            'division_id' => $divisionId,
                            'component_id' => $componentId,
                            'uom_id' => $uomId,
                            'qty_in' => $qtyPlus,
                            'unit_cost' => $unitCost,
                            'lot_no' => $lotNo,
                            'receipt_date' => $movementDate,
                            'source_module' => 'PRODUCTION_ADJUSTMENT',
                            'source_table' => 'inv_component_adjustment',
                            'source_id' => $sourceId > 0 ? $sourceId : null,
                            'source_line_id' => $sourceLineId,
                        ]);
                        if (!($lotRegister['ok'] ?? false)) {
                            throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot adjustment plus gagal.'));
                        }
                    }
                }
            }

            if (!empty($rebuildIdentities)) {
                $this->ci->load->model('Production_model');
                foreach (array_values($rebuildIdentities) as $identity) {
                    $rebuild = $this->ci->Production_model->rebuild_component_history_for_identity($identity);
                    if (!($rebuild['ok'] ?? false)) {
                        throw new RuntimeException((string)($rebuild['message'] ?? 'Gagal sinkron saldo bulanan component setelah adjustment.'));
                    }
                }
            }

            foreach (array_values($physicalReconcileIdentities) as $identity) {
                $finalBalance = $this->load_balance_state(
                    (string)$identity['location_type'],
                    $identity['division_id'],
                    (int)$identity['component_id'],
                    (int)$identity['uom_id'],
                    $movementDate
                );
                $lotSync = $this->ci->componentlotmanager->reconcileLotsToAuthoritativeBalance([
                    'location_type' => (string)$identity['location_type'],
                    'division_id' => $identity['division_id'],
                    'component_id' => (int)$identity['component_id'],
                    'uom_id' => (int)$identity['uom_id'],
                    'event_date' => $movementDate,
                    'target_qty' => round(max(0, (float)($finalBalance['qty_on_hand'] ?? 0)), 4),
                    'unit_cost' => round((float)($identity['inbound_unit_cost'] ?? $finalBalance['avg_cost'] ?? 0), 6),
                    'source_table' => 'inv_component_adjustment',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $identity['source_line_id'],
                    'created_by' => $actorUserId > 0 ? $actorUserId : null,
                ]);
                if (!($lotSync['ok'] ?? false)) {
                    throw new RuntimeException((string)($lotSync['message'] ?? 'Gagal menyamakan lot component dengan hasil hitung fisik.'));
                }
            }

            $consistent = $this->ci->inventoryadjustmentguard->checkComponent($header, $lines);
            if (empty($consistent['ok'])) { throw new RuntimeException($consistent['message']); }

            $statusUpdated = $db
                ->where('id', $sourceId)
                ->where('status', 'DRAFT')
                ->update('inv_component_adjustment', [
                    'status' => 'POSTED',
                    'posted_at' => date('Y-m-d H:i:s'),
                    'posted_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                ]);
            $statusError = $db->error();
            if (
                !$statusUpdated
                || (int)($statusError['code'] ?? 0) !== 0
                || $db->affected_rows() !== 1
                || $db->trans_status() === false
            ) {
                throw new RuntimeException('Gagal memfinalkan status adjustment component. Dokumen mungkin sudah diposting atau berubah.');
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $db->trans_rollback();
            log_message('error', 'ComponentStockWriter::post_adjustment fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return ['ok' => false, 'message' => $this->formatThrowableMessage('Posting adjustment gagal di backend.', $e)];
        } finally {
            $db->db_debug = $originalDbDebug;
        }

        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting adjustment gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_ADJUSTMENT',
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    /**
     * Structural repair for the active component period. The monthly balance
     * remains untouched; only FIFO lots are aligned to it and every change is
     * written to inv_stock_cutoff_event.
     */
    public function reconcile_lots_to_monthly_stock(array $payload): array
    {
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = isset($payload['division_id']) && $payload['division_id'] !== null
            ? (int)$payload['division_id'] : null;
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $eventDate = (string)($payload['event_date'] ?? date('Y-m-d'));

        if (!$this->valid_location($locationType) || $componentId <= 0 || $uomId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            return ['ok' => false, 'message' => 'Repair lot component membutuhkan lokasi, komponen, satuan, dan tanggal yang valid.'];
        }
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $eventDate,
                null,
                'Automatic component period from lot repair'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }

        $monthKey = date('Y-m-01', strtotime($eventDate));
        $monthly = $this->ci->db->query(
            'SELECT id, closing_qty, avg_cost
             FROM inv_component_monthly_stock
             WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ?
             LIMIT 1',
            [$monthKey, $locationType, $divisionId, $componentId, $uomId]
        )->row_array();
        if (empty($monthly)) {
            return ['ok' => false, 'message' => 'Saldo bulanan component periode aktif tidak ditemukan. Gunakan Daily Recon untuk membuat koreksi stok fisik terlebih dahulu.'];
        }

        $this->ci->db->trans_begin();
        $this->ci->load->library('ComponentLotManager');
        $result = $this->ci->componentlotmanager->reconcileLotsToAuthoritativeBalance([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'event_date' => $eventDate,
            'target_qty' => round(max(0, (float)($monthly['closing_qty'] ?? 0)), 4),
            'unit_cost' => round((float)($monthly['avg_cost'] ?? 0), 6),
            'source_table' => 'inv_component_monthly_stock',
            'source_id' => (int)($monthly['id'] ?? 0),
            'source_line_id' => null,
            'created_by' => !empty($payload['created_by']) ? (int)$payload['created_by'] : null,
        ]);
        if (!($result['ok'] ?? false)) {
            $this->ci->db->trans_rollback();
            return $result;
        }
        if ($this->ci->db->trans_status() === false) {
            $this->ci->db->trans_rollback();
            return ['ok' => false, 'message' => 'Repair struktur lot component gagal disimpan.'];
        }
        $this->ci->db->trans_commit();

        $result['data']['monthly_stock_id'] = (int)($monthly['id'] ?? 0);
        $result['data']['target_qty'] = round(max(0, (float)($monthly['closing_qty'] ?? 0)), 4);
        return $result;
    }

    public function post_batch(array $header, array $inputs, int $actorEmployeeId = 0): array
    {
        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header['batch_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        $componentIdOutput = (int)($header['component_id'] ?? 0);
        $uomIdOutput = (int)($header['output_uom_id'] ?? 0);
        $outputQty = round((float)($header['output_qty'] ?? 0), 4);

        if ($sourceId <= 0 || $componentIdOutput <= 0 || $uomIdOutput <= 0 || $outputQty <= 0 || !$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Header batch tidak valid untuk posting.'];
        }
        if (file_exists(APPPATH . 'libraries/InventoryPeriodGuard.php')) {
            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->ensureActiveMonthOpen(
                'COMPONENT',
                $movementDate,
                null,
                'Automatic component period from batch'
            );
            if (!($period['ok'] ?? false)) {
                return $period;
            }
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal batch tidak valid.'];
        }

        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $destinationType = $this->resolve_inventory_destination_type($locationType);
        $hasMaterialInput = false;
        foreach ($inputs as $line) {
            if (strtoupper(trim((string)($line['source_kind'] ?? ''))) === 'MATERIAL') {
                $hasMaterialInput = true;
                break;
            }
        }
        if ($hasMaterialInput) {
            if ($divisionId === null || $divisionId <= 0 || $destinationType === null) {
                return ['ok' => false, 'message' => 'Batch dengan input MATERIAL wajib memiliki division_id dan location_type yang sinkron ke stok divisi.'];
            }
            $this->ci->load->library('MaterialFifoManager');
            $fifoReady = $this->ci->materialfifomanager->ensureReady();
            if (!($fifoReady['ok'] ?? false)) {
                return $fifoReady;
            }
            $this->ci->load->library('InventoryLedger');
        }

        $originalDbDebug = isset($db->db_debug) ? (bool)$db->db_debug : false;
        $db->db_debug = false;
        $db->trans_start();
        try {
            $totalInputCost = 0.0;
            $headerRecordedTotalInputCost = round((float)($header['total_input_cost'] ?? 0), 2);
            $pendingMaterialIds = [];
            $recoveryWarnings = [];  // notifikasi pemulihan stok negatif
            foreach ($inputs as $line) {
                $planRole = strtoupper(trim((string)($line['plan_role'] ?? 'INPUT')));
                $sourceKind = strtoupper(trim((string)($line['source_kind'] ?? '')));
                if ($planRole === 'INLINE_OUTPUT') {
                    $componentId = (int)($line['component_id'] ?? 0);
                    $uomId = (int)($line['uom_id'] ?? 0);
                    $qty = round((float)($line['qty'] ?? 0), 4);
                    if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                        continue;
                    }

                    // Catat jika inline output memulihkan stok dari kondisi negatif
                    $inlineBalState = $this->load_balance_state($locationType, $divisionId, $componentId, $uomId, $movementDate);
                    $inlineQtyBefore = round((float)($inlineBalState['qty_on_hand'] ?? 0), 4);
                    if ($inlineQtyBefore < -0.0001) {
                        $recoveryWarnings[] = $this->build_recovery_warning($db, $componentId, $uomId, $inlineQtyBefore, $qty);
                    }

                    $this->post_single_movement([
                        'movement_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'movement_type' => 'PRODUCTION_IN',
                        'qty' => $qty,
                        'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                        'source_module' => 'PRODUCTION_BATCH',
                        'source_table' => 'inv_component_batch',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => isset($line['id']) ? (int)$line['id'] : null,
                        'notes' => (string)($line['notes'] ?? 'Inline production output'),
                        'actor_employee_id' => $actorEmployeeId,
                    ]);
                    continue;
                }
                if ($sourceKind === 'MATERIAL') {
                    $lineDivisionId = !empty($line['division_id']) ? (int)$line['division_id'] : (int)$divisionId;
                    $lineDestType = $this->resolve_inventory_destination_type_for_division($locationType, $lineDivisionId) ?? (string)$destinationType;
                    $materialUsage = $this->post_material_input_usage($header, $line, $movementDate, $locationType, $lineDivisionId, $lineDestType, $actorEmployeeId);
                    if (!($materialUsage['ok'] ?? false)) {
                        throw new RuntimeException((string)($materialUsage['message'] ?? 'Posting material usage gagal.'));
                    }
                    $totalInputCost += round((float)($materialUsage['line_cost'] ?? 0), 2);
                    foreach ((array)($materialUsage['material_ids'] ?? []) as $materialId) {
                        $materialId = (int)$materialId;
                        if ($materialId > 0) {
                            $pendingMaterialIds[$materialId] = $materialId;
                        }
                    }
                    continue;
                }
                if ($sourceKind !== 'COMPONENT') {
                    continue;
                }
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $qty = round((float)($line['qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                    continue;
                }

                $compDivisionId = !empty($line['division_id']) ? (int)$line['division_id'] : $divisionId;
                $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;
                $lotNoSnapshot = null;
                $lotIssueId = null;
                $lotIssueNo = null;
                $unitCost = isset($line['unit_cost']) ? round((float)$line['unit_cost'], 6) : $this->current_avg_cost($locationType, $compDivisionId, $componentId, $uomId);

                // Direct component inputs must consume their actual FIFO lots.
                // Inline usage is produced and consumed inside this same batch, so
                // it does not need an external lot issue record.
                if ($planRole !== 'INLINE_COMPONENT_USAGE') {
                    $lotUsage = $this->ci->componentlotmanager->consumeUsage([
                        'issue_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $compDivisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'qty_out' => $qty,
                        'source_module' => 'PRODUCTION_BATCH',
                        'source_table' => 'inv_component_batch',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                        'notes' => 'Batch component memakai lot input component',
                    ]);
                    if (!($lotUsage['ok'] ?? false)) {
                        throw new RuntimeException((string)($lotUsage['message'] ?? 'Pemakaian lot input component gagal.'));
                    }

                    $lotData = (array)($lotUsage['data'] ?? []);
                    $lotIssueId = !empty($lotData['issue_id']) ? (int)$lotData['issue_id'] : null;
                    $lotIssueNo = trim((string)($lotData['issue_no'] ?? '')) ?: null;
                    $unitCost = round((float)($lotData['avg_unit_cost'] ?? 0), 6);
                    $firstAllocation = (array)($lotData['allocations'][0] ?? []);
                    $lotNoSnapshot = trim((string)($firstAllocation['lot_no'] ?? '')) ?: null;
                }
                $lineCost = $planRole !== 'INLINE_COMPONENT_USAGE'
                    ? round((float)$lotData['total_cost'], 2) : round($qty * $unitCost, 2);
                if ($planRole !== 'INLINE_COMPONENT_USAGE' && $sourceLineId !== null && $sourceLineId > 0) {
                    $inputTrace = [
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineCost,
                    ];
                    // The trace columns are installed by the batch preflight migration.
                    // Keep the write backward-compatible while an old environment is being deployed.
                    if ($this->ci->db->field_exists('fifo_issue_id', 'inv_component_batch_input')) {
                        $inputTrace['fifo_issue_id'] = $lotIssueId;
                    }
                    if ($this->ci->db->field_exists('fifo_issue_no', 'inv_component_batch_input')) {
                        $inputTrace['fifo_issue_no'] = $lotIssueNo;
                    }
                    $this->ci->db->where('id', $sourceLineId)->update('inv_component_batch_input', $inputTrace);
                }
                if ($planRole !== 'INLINE_COMPONENT_USAGE') {
                    $totalInputCost += $lineCost;
                }

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $compDivisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => 'PRODUCTION_OUT',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'source_module' => 'PRODUCTION_BATCH',
                    'source_table' => 'inv_component_batch',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $sourceLineId,
                    'lot_no_snapshot' => $lotNoSnapshot,
                    'notes' => (string)($line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);
            }

            $extraCost = max(0, round($headerRecordedTotalInputCost - $totalInputCost, 2));
            $finalTotalInputCost = round($totalInputCost + $extraCost, 2);
            $unitCostOutput = $outputQty > 0 ? round($finalTotalInputCost / $outputQty, 6) : 0.0;
            $outputLotNo = $this->generate_component_output_lot_no($movementDate, $componentIdOutput, $sourceId);

            // Catat jika main output memulihkan stok dari kondisi negatif
            $mainBalState = $this->load_balance_state($locationType, $divisionId, $componentIdOutput, $uomIdOutput, $movementDate);
            $mainQtyBefore = round((float)($mainBalState['qty_on_hand'] ?? 0), 4);
            if ($mainQtyBefore < -0.0001) {
                $recoveryWarnings[] = $this->build_recovery_warning($db, $componentIdOutput, $uomIdOutput, $mainQtyBefore, $outputQty);
            }

            $this->post_single_movement([
                'movement_date' => $movementDate,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentIdOutput,
                'uom_id' => $uomIdOutput,
                'movement_type' => 'PRODUCTION_IN',
                'qty' => $outputQty,
                'unit_cost' => $unitCostOutput,
                'source_module' => 'PRODUCTION_BATCH',
                'source_table' => 'inv_component_batch',
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'source_line_id' => null,
                'lot_no_snapshot' => $outputLotNo,
                'received_date_snapshot' => $movementDate,
                'notes' => (string)($header['notes'] ?? ''),
                'actor_employee_id' => $actorEmployeeId,
            ]);

            $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentIdOutput,
                'uom_id' => $uomIdOutput,
                'qty_in' => $outputQty,
                'unit_cost' => $unitCostOutput,
                'lot_no' => $outputLotNo,
                'receipt_date' => $movementDate,
                'resolve_open_deficit' => true,
                'source_module' => 'PRODUCTION_BATCH',
                'source_table' => 'inv_component_batch',
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'source_line_id' => null,
            ]);
            if (!($lotRegister['ok'] ?? false)) {
                throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot component gagal.'));
            }

            $statusUpdated = $db
                ->where('id', $sourceId)
                ->where('status', 'DRAFT')
                ->update('inv_component_batch', [
                    'total_input_cost' => $finalTotalInputCost,
                    'unit_cost' => $unitCostOutput,
                    'status' => 'POSTED',
                    'posted_at' => date('Y-m-d H:i:s'),
                    'posted_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                ]);
            $statusError = $db->error();
            if (
                !$statusUpdated
                || (int)($statusError['code'] ?? 0) !== 0
                || $db->affected_rows() !== 1
                || $db->trans_status() === false
            ) {
                throw new RuntimeException('Gagal memfinalkan status batch component. Dokumen mungkin sudah diposting atau berubah.');
            }

        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $db->trans_rollback();
            log_message('error', 'ComponentStockWriter::post_batch fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return ['ok' => false, 'message' => $this->formatThrowableMessage('Posting batch gagal di backend.', $e)];
        } finally {
            $db->db_debug = $originalDbDebug;
        }
        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting batch gagal.'];
        }
        $changedComponentIds = array_values(array_unique(array_filter(array_merge(
            [(int)$componentIdOutput],
            array_map(static function (array $line): int {
                return (int)($line['component_id'] ?? 0);
            }, $inputs)
        ))));
        $this->ci->load->library('PosAvailabilityRebuildService');
        $availabilityRefresh = $this->ci->posavailabilityrebuildservice->handle_inventory_changes(
            array_values($pendingMaterialIds),
            $changedComponentIds,
            [
                'trigger_context' => 'COMPONENT_BATCH',
                'event_source' => 'COMPONENT_BATCH',
                'event_table' => 'inv_component_batch',
                'event_id' => $sourceId,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]
        );

        return [
            'ok' => true,
            'availability_rebuild' => $availabilityRefresh,
            'recovery_warnings' => $recoveryWarnings,
        ];
    }

    private function build_recovery_warning($db, int $componentId, int $uomId, float $qtyBefore, float $qtyProduced): array
    {
        $nameRow = $db->select('component_name')->from('mst_component')->where('id', $componentId)->limit(1)->get()->row_array();
        $uomRow  = $db->select('code')->from('mst_uom')->where('id', $uomId)->limit(1)->get()->row_array();
        return [
            'component_id'   => $componentId,
            'component_name' => (string)($nameRow['component_name'] ?? 'Component #' . $componentId),
            'uom_code'       => (string)($uomRow['code'] ?? ''),
            'qty_before'     => $qtyBefore,
            'qty_produced'   => $qtyProduced,
            'qty_after'      => round($qtyBefore + $qtyProduced, 4),
        ];
    }

    private function post_document_movements(string $docType, array $header, array $lines, int $actorEmployeeId, array $sourceMeta): array
    {
        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header[strtolower($docType) . '_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal dokumen tidak valid.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris untuk diposting.'];
        }

        $db->trans_start();
        try {
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $movementType = strtoupper(trim((string)($line['movement_type'] ?? ($docType === 'OPENING' ? 'OPENING' : ''))));
                $qty = round((float)($line['qty'] ?? $line['opening_qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $movementType === '' || $qty <= 0) {
                    continue;
                }

                $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== null
                    ? round((float)$line['unit_cost'], 6)
                    : $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => $movementType,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'source_module' => (string)$sourceMeta['source_module'],
                    'source_table' => (string)$sourceMeta['source_table'],
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => isset($line['source_line_id']) ? (int)$line['source_line_id'] : (isset($line['id']) ? (int)$line['id'] : null),
                    'notes' => (string)($line['note'] ?? $line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting dokumen gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_' . strtoupper($docType),
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    private function trigger_availability_refresh(array $componentIds, string $eventSource, int $actorEmployeeId = 0, ?int $eventId = null): ?array
    {
        $componentIds = array_values(array_unique(array_filter(array_map('intval', $componentIds))));
        if (empty($componentIds)) {
            return null;
        }

        $this->ci->load->library('PosAvailabilityRebuildService');
        $success = 0;
        $failed = 0;
        foreach ($componentIds as $componentId) {
            $result = $this->ci->posavailabilityrebuildservice->handle_component_change($componentId, [
                'trigger_context' => $eventSource,
                'event_source' => $eventSource,
                'event_table' => 'inv_component_movement_log',
                'event_id' => $eventId,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
            $success += (int)($result['success_count'] ?? 0);
            $failed += (int)($result['failed_count'] ?? 0);
        }

        return [
            'component_count' => count($componentIds),
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }

    private function post_single_movement(array $p): void
    {
        $movementType = strtoupper((string)$p['movement_type']);
        $qty = round((float)$p['qty'], 4);
        $isIn = in_array($movementType, ['OPENING', 'PRODUCTION_IN', 'TRANSFER_IN', 'ADJUSTMENT_PLUS', 'VOID_REVERSE'], true);
        $qtyIn = $isIn ? $qty : 0.0;
        $qtyOut = $isIn ? 0.0 : $qty;
        $unitCost = round((float)($p['unit_cost'] ?? 0), 6);
        if ($unitCost < -0.000001) {
            throw new RuntimeException('Biaya unit component tidak boleh negatif. Perbaiki valuasi component sebelum diposting.');
        }
        $totalCost = round((float)($p['total_cost'] ?? ($qty * $unitCost)), 2);
        $reversalOriginType = strtoupper(trim((string)($p['reversal_of_movement_type'] ?? '')));
        if ($reversalOriginType === ''
            && !empty($p['reversal_of_movement_id'])
            && $this->ci->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log')) {
            $origin = $this->ci->db
                ->select('movement_type')
                ->from('inv_component_movement_log')
                ->where('id', (int)$p['reversal_of_movement_id'])
                ->limit(1)
                ->get()
                ->row_array();
            $reversalOriginType = strtoupper(trim((string)($origin['movement_type'] ?? '')));
        }

        $authoritativeBalance = $this->load_balance_state(
            (string)$p['location_type'],
            isset($p['division_id']) ? (int)$p['division_id'] : null,
            (int)$p['component_id'],
            (int)$p['uom_id'],
            (string)$p['movement_date']
        );

        $qtyBefore = (float)($authoritativeBalance['qty_on_hand'] ?? 0);
        $avgBefore = (float)($authoritativeBalance['avg_cost'] ?? 0);
        $valueBefore = round((float)($authoritativeBalance['total_value'] ?? ($qtyBefore * $avgBefore)), 2);
        $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
        if (!$isIn && $qtyAfter < -0.0001) {
            $componentName = $this->resolve_component_name((int)$p['component_id']);
            throw new RuntimeException(
                'Stok komponen ' . $componentName . ' tidak cukup untuk movement ' . $movementType . '. '
                . 'Stok saat ini: ' . number_format($qtyBefore, 2) . ', dibutuhkan: ' . number_format($qtyOut, 2) . '. '
                . 'Lakukan produksi batch atau adjustment plus terlebih dahulu untuk menutup deficit.'
            );
        }
        if (abs($qtyAfter) < 0.0001) {
            $qtyAfter = 0.0;
        }

        if ($qtyIn > 0) {
            $valueAfter = round($valueBefore + $totalCost, 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        } else {
            $valueAfter = round($valueBefore - $totalCost, 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        }

        $now = date('Y-m-d H:i:s');

        $movementData = [
            'movement_no' => $this->generate_movement_no((string)$p['movement_date']),
            'movement_date' => $p['movement_date'],
            'movement_datetime' => $p['movement_date'] . ' ' . date('H:i:s'),
            'location_type' => $p['location_type'],
            'division_id' => $p['division_id'],
            'component_id' => $p['component_id'],
            'uom_id' => $p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_module' => $p['source_module'],
            'source_table' => $p['source_table'],
            'source_id' => $p['source_id'],
            'source_line_id' => $p['source_line_id'],
            'lot_no_snapshot' => $p['lot_no_snapshot'] ?? null,
            'received_date_snapshot' => $p['received_date_snapshot'] ?? null,
            'notes' => $p['notes'] !== '' ? $p['notes'] : null,
            'created_by' => $p['actor_employee_id'] > 0 ? (int)$p['actor_employee_id'] : null,
            'created_at' => $now,
        ];
        if ($this->ci->db->field_exists('reversal_of_movement_id', 'inv_component_movement_log')) {
            $movementData['reversal_of_movement_id'] = !empty($p['reversal_of_movement_id'])
                ? (int)$p['reversal_of_movement_id']
                : null;
        }
        $this->ci->db->insert('inv_component_movement_log', $movementData);

        $this->sync_monthly_stock([
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $p['movement_date'] . ' ' . date('H:i:s'),
            'location_type' => (string)$p['location_type'],
            'division_id' => $p['division_id'],
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_table' => (string)($p['source_table'] ?? ''),
            'source_id' => isset($p['source_id']) ? (int)$p['source_id'] : null,
            'notes' => $p['notes'] ?? '',
            'reversal_of_movement_type' => $reversalOriginType,
        ], $qtyBefore, $qtyAfter, $avgAfter, $valueAfter);

    }

    private function sync_monthly_stock(array $movement, float $qtyBefore, float $qtyAfter, float $avgAfter, float $valueAfter): void
    {
        if (!$this->ci->db->table_exists('inv_component_monthly_stock')) {
            return;
        }

        $movementDate = (string)($movement['movement_date'] ?? '');
        $locationType = strtoupper(trim((string)($movement['location_type'] ?? '')));
        $divisionId = isset($movement['division_id']) && $movement['division_id'] !== null ? (int)$movement['division_id'] : null;
        $componentId = (int)($movement['component_id'] ?? 0);
        $uomId = (int)($movement['uom_id'] ?? 0);
        $movementType = strtoupper(trim((string)($movement['movement_type'] ?? '')));
        $qtyIn = round((float)($movement['qty_in'] ?? 0), 4);
        $qtyOut = round((float)($movement['qty_out'] ?? 0), 4);
        $reversalOriginType = strtoupper(trim((string)($movement['reversal_of_movement_type'] ?? '')));
        if ($movementDate === '' || $componentId <= 0 || $uomId <= 0 || $locationType === '') {
            return;
        }

        $monthKey = date('Y-m-01', strtotime($movementDate));
        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_monthly_stock WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [$monthKey, $locationType, $divisionId, $componentId, $uomId]
        )->row_array();

        $isOpeningSnapshot = $movementType === 'OPENING';
        $movementDayCount = (int)($row['movement_day_count'] ?? 0);
        if ($row === null || (string)($row['last_movement_date'] ?? '') !== $movementDate) {
            $movementDayCount++;
        }
        // Use the amount recorded for this movement. avgAfter is zero when the
        // final unit is consumed and must not erase the month's COGS value.
        $movementTotalCost = $movement['total_cost']
            ?? (($qtyIn > 0 ? $qtyIn : $qtyOut) * (float)($movement['unit_cost'] ?? 0));
        $movementValue = round(abs((float)$movementTotalCost), 2);

        $data = [
            'month_key' => $monthKey,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'opening_qty' => $isOpeningSnapshot
                ? ($row ? round((float)($row['opening_qty'] ?? 0) + $qtyIn, 4) : round($qtyAfter, 4))
                : ($row ? round((float)($row['opening_qty'] ?? 0), 4) : round($qtyBefore, 4)),
            'opening_total_value' => $row ? round((float)($row['opening_total_value'] ?? 0), 2)
                : round($valueAfter - ($qtyIn > 0 ? $movementValue : -$movementValue), 2),
            'in_qty' => $row ? round((float)($row['in_qty'] ?? 0), 4) : 0.0,
            'in_total_value' => $row ? round((float)($row['in_total_value'] ?? 0), 2) : 0.0,
            'out_qty' => $row ? round((float)($row['out_qty'] ?? 0), 4) : 0.0,
            'out_total_value' => $row ? round((float)($row['out_total_value'] ?? 0), 2) : 0.0,
            'waste_qty' => $row ? round((float)($row['waste_qty'] ?? 0), 4) : 0.0,
            'waste_total_value' => $row ? round((float)($row['waste_total_value'] ?? 0), 2) : 0.0,
            'spoil_qty' => $row ? round((float)($row['spoil_qty'] ?? 0), 4) : 0.0,
            'spoil_total_value' => $row ? round((float)($row['spoil_total_value'] ?? 0), 2) : 0.0,
            'adjustment_plus_qty' => $row ? round((float)($row['adjustment_plus_qty'] ?? 0), 4) : 0.0,
            'adjustment_plus_total_value' => $row ? round((float)($row['adjustment_plus_total_value'] ?? 0), 2) : 0.0,
            'adjustment_minus_qty' => $row ? round((float)($row['adjustment_minus_qty'] ?? 0), 4) : 0.0,
            'adjustment_minus_total_value' => $row ? round((float)($row['adjustment_minus_total_value'] ?? 0), 2) : 0.0,
            'closing_qty' => round($qtyAfter, 4),
            'avg_cost' => round($avgAfter, 6),
            'total_value' => round($valueAfter, 2),
            'movement_day_count' => $movementDayCount,
            'mutation_count' => $row ? ((int)($row['mutation_count'] ?? 0) + 1) : 1,
            'last_movement_date' => $movementDate,
            'last_movement_at' => (string)($movement['movement_datetime'] ?? ($movementDate . ' ' . date('H:i:s'))),
            'last_movement_table' => (string)($movement['source_table'] ?? '') !== '' ? (string)$movement['source_table'] : null,
            'last_movement_id' => !empty($movement['source_id']) ? (int)$movement['source_id'] : null,
            'source_mode' => 'LIVE',
            'notes' => trim((string)($movement['notes'] ?? '')) !== '' ? trim((string)$movement['notes']) : ($row['notes'] ?? null),
        ];
        if ($isOpeningSnapshot) {
            $data['opening_total_value'] = round((float)$data['opening_total_value'] + $movementValue, 2);
        }

        switch ($movementType) {
            case 'PRODUCTION_IN':
            case 'TRANSFER_IN':
                $data['in_qty'] = round((float)$data['in_qty'] + $qtyIn, 4);
                $data['in_total_value'] = round((float)$data['in_total_value'] + $movementValue, 2);
                break;
            case 'PRODUCTION_OUT':
            case 'TRANSFER_OUT':
                $data['out_qty'] = round((float)$data['out_qty'] + $qtyOut, 4);
                $data['out_total_value'] = round((float)$data['out_total_value'] + $movementValue, 2);
                break;
            case 'WASTE':
                $data['waste_qty'] = round((float)$data['waste_qty'] + $qtyOut, 4);
                $data['waste_total_value'] = round((float)$data['waste_total_value'] + $movementValue, 2);
                break;
            case 'SPOIL':
                $data['spoil_qty'] = round((float)$data['spoil_qty'] + $qtyOut, 4);
                $data['spoil_total_value'] = round((float)$data['spoil_total_value'] + $movementValue, 2);
                break;
            case 'ADJUSTMENT_PLUS':
                $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] + $qtyIn, 4);
                $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] + $movementValue, 2);
                break;
            case 'ADJUSTMENT_MINUS':
                $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] + $qtyOut, 4);
                $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] + $movementValue, 2);
                break;
            case 'VOID_REVERSE':
                if (in_array($reversalOriginType, ['PRODUCTION_OUT', 'TRANSFER_OUT', 'USAGE'], true)) {
                    $data['out_qty'] = round((float)$data['out_qty'] - $qtyIn, 4);
                    $data['out_total_value'] = round((float)$data['out_total_value'] - $movementValue, 2);
                } elseif ($reversalOriginType === 'WASTE') {
                    $data['waste_qty'] = round((float)$data['waste_qty'] - $qtyIn, 4);
                    $data['waste_total_value'] = round((float)$data['waste_total_value'] - $movementValue, 2);
                } elseif ($reversalOriginType === 'SPOIL') {
                    $data['spoil_qty'] = round((float)$data['spoil_qty'] - $qtyIn, 4);
                    $data['spoil_total_value'] = round((float)$data['spoil_total_value'] - $movementValue, 2);
                } elseif ($reversalOriginType === 'ADJUSTMENT_MINUS') {
                    $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] - $qtyIn, 4);
                    $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] - $movementValue, 2);
                } elseif (in_array($reversalOriginType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                    $data['in_qty'] = round((float)$data['in_qty'] - $qtyIn, 4);
                    $data['in_total_value'] = round((float)$data['in_total_value'] - $movementValue, 2);
                } else {
                    $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] + $qtyIn, 4);
                    $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] + $movementValue, 2);
                }
                break;
            case 'VOID_OUT':
                if (in_array($reversalOriginType, ['PRODUCTION_IN', 'TRANSFER_IN'], true)) {
                    $data['in_qty'] = round((float)$data['in_qty'] - $qtyOut, 4);
                    $data['in_total_value'] = round((float)$data['in_total_value'] - $movementValue, 2);
                } elseif ($reversalOriginType === 'ADJUSTMENT_PLUS') {
                    $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] - $qtyOut, 4);
                    $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] - $movementValue, 2);
                } else {
                    $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] + $qtyOut, 4);
                    $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] + $movementValue, 2);
                }
                break;
        }

        if ($row && !empty($row['id'])) {
            $this->ci->db->where('id', (int)$row['id'])->update('inv_component_monthly_stock', $data);
            return;
        }

        $this->ci->db->insert('inv_component_monthly_stock', $data);
    }

    private function post_material_input_usage(array $header, array $line, string $movementDate, string $locationType, int $divisionId, string $destinationType, int $actorEmployeeId): array
    {
        $sourceId = (int)($header['id'] ?? 0);
        $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
        $uomId = (int)($line['uom_id'] ?? 0);
        $qty = round((float)($line['qty'] ?? 0), 4);
        $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;

        if ($uomId <= 0 || $qty <= 0 || ($itemId === null && $materialId === null)) {
            return ['ok' => false, 'message' => 'Line MATERIAL batch belum memiliki item/material, UOM, atau qty yang valid.'];
        }

        $structureHealth = $this->validate_material_input_stock_structure($line, $divisionId, $destinationType);
        if (!($structureHealth['ok'] ?? false)) {
            return $structureHealth;
        }

        $fifoUsage = $this->ci->materialfifomanager->consumeDivisionUsage([
            'issue_date' => $movementDate,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'content_uom_id' => $uomId,
            'qty_content_out' => $qty,
            'source_module' => 'PRODUCTION_BATCH',
            'force_cross_profile_fifo' => true,
            'source_table' => 'inv_component_batch',
            'source_id' => $sourceId > 0 ? $sourceId : null,
            'source_line_id' => $sourceLineId,
            'notes' => 'Batch component memakai stok divisi ' . $locationType,
        ]);
        if (!($fifoUsage['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    (string)($fifoUsage['message'] ?? 'Pemakaian lot FIFO material gagal.'),
                    ['detail' => (string)($fifoUsage['message'] ?? '')]
                ),
            ];
        }

        $issueId = (int)($fifoUsage['data']['issue_id'] ?? 0);
        $issueNo = (string)($fifoUsage['data']['issue_no'] ?? '');
        $lineCost = round((float)($fifoUsage['data']['total_cost'] ?? 0), 2);
        $avgUnitCost = round((float)($fifoUsage['data']['avg_unit_cost'] ?? 0), 6);
        $allocations = (array)($fifoUsage['data']['allocations'] ?? []);
        $affectedMaterialIds = [];

        foreach ($allocations as $allocation) {
            $snapshot = $this->resolve_inventory_snapshot_for_allocation($allocation, $divisionId, $destinationType, $movementDate);
            $contentPerBuy = max(0.000001, round((float)($snapshot['profile_content_per_buy'] ?? 1), 6));
            $qtyContent = round((float)($allocation['qty_content'] ?? 0), 4);
            $qtyBuy = round($qtyContent / $contentPerBuy, 4);

            $post = $this->ci->inventoryledger->post([
                'movement_scope' => 'DIVISION',
                'movement_date' => $movementDate,
                'movement_type' => 'USAGE_OUT',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'ref_table' => 'inv_component_batch',
                'ref_id' => $sourceId > 0 ? $sourceId : null,
                'item_id' => $snapshot['item_id'],
                'material_id' => $snapshot['material_id'],
                'buy_uom_id' => $snapshot['buy_uom_id'],
                'content_uom_id' => $snapshot['content_uom_id'],
                'qty_buy_delta' => -1 * $qtyBuy,
                'qty_content_delta' => -1 * $qtyContent,
                'profile_key' => $snapshot['profile_key'],
                'profile_name' => $snapshot['profile_name'],
                'profile_brand' => $snapshot['profile_brand'],
                'profile_description' => $snapshot['profile_description'],
                'profile_expired_date' => $snapshot['profile_expired_date'],
                'profile_content_per_buy' => $snapshot['profile_content_per_buy'],
                'profile_buy_uom_code' => $snapshot['profile_buy_uom_code'],
                'profile_content_uom_code' => $snapshot['profile_content_uom_code'],
                'unit_cost' => round((float)($allocation['unit_cost'] ?? 0), 6),
                'notes' => 'Batch ' . (string)($header['batch_no'] ?? ('#' . $sourceId)) . ' pakai lot ' . (string)($allocation['source_lot_no'] ?? '-'),
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'manage_transaction' => false,
                'skip_availability_refresh' => true,
            ]);
            if (!($post['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $this->format_material_usage_error(
                        $line,
                        (string)($post['message'] ?? 'Gagal posting usage keluar stok divisi.'),
                        [
                            'profile_key' => $snapshot['profile_key'] ?? null,
                            'buy_uom_id' => $snapshot['buy_uom_id'] ?? null,
                            'content_uom_id' => $snapshot['content_uom_id'] ?? null,
                            'qty_content' => $qtyContent,
                            'source_lot_no' => (string)($allocation['source_lot_no'] ?? ''),
                        ]
                    ),
                ];
            }

            if (!empty($snapshot['material_id'])) {
                $affectedMaterialIds[(int)$snapshot['material_id']] = (int)$snapshot['material_id'];
            }
        }

        if ($sourceLineId !== null && $sourceLineId > 0) {
            $update = [
                'unit_cost' => $avgUnitCost,
                'total_cost' => $lineCost,
            ];
            if ($this->ci->db->field_exists('fifo_issue_id', 'inv_component_batch_input')) {
                $update['fifo_issue_id'] = $issueId > 0 ? $issueId : null;
            }
            if ($this->ci->db->field_exists('fifo_issue_no', 'inv_component_batch_input')) {
                $update['fifo_issue_no'] = $issueNo !== '' ? $issueNo : null;
            }
            $this->ci->db->where('id', $sourceLineId)->update('inv_component_batch_input', $update);
        }

        return [
            'ok' => true,
            'line_cost' => $lineCost,
            'unit_cost' => $avgUnitCost,
            'issue_id' => $issueId,
            'issue_no' => $issueNo,
            'material_ids' => array_values($affectedMaterialIds),
        ];
    }

    private function validate_material_input_stock_structure(array $line, int $divisionId, string $destinationType): array
    {
        $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
        if ($materialId === null || $materialId <= 0) {
            return ['ok' => true];
        }

        $sameUomDriftRows = $this->ci->db->query(
            'SELECT id, profile_key, buy_uom_id, content_uom_id, profile_content_per_buy, closing_qty_content
             FROM inv_division_monthly_stock
             WHERE division_id = ?
               AND destination_type = ?
               AND material_id = ?
               AND buy_uom_id IS NOT NULL
               AND buy_uom_id = content_uom_id
               AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
             ORDER BY id ASC',
            [$divisionId, $destinationType, $materialId]
        )->result_array();
        if (!empty($sameUomDriftRows)) {
            $row = $sameUomDriftRows[0];
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    'Struktur stok divisi masih memakai konversi profile lama yang tidak valid.',
                    [
                        'monthly_stock_id' => (int)($row['id'] ?? 0),
                        'profile_key' => (string)($row['profile_key'] ?? ''),
                        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                        'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                        'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 0), 6),
                        'closing_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
                        'hint' => 'UOM beli dan UOM isi sama, tetapi profile_content_per_buy bukan 1. Repair data profile ini dulu.',
                    ]
                ),
            ];
        }

        $negativeRows = $this->ci->db->query(
            'SELECT
                s.id,
                s.profile_key,
                s.buy_uom_id,
                s.content_uom_id,
                s.profile_content_per_buy,
                s.closing_qty_buy,
                s.closing_qty_content,
                COALESCE(l.live_qty, 0) AS fifo_live_qty
             FROM inv_division_monthly_stock s
             LEFT JOIN (
                SELECT division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key,
                       ROUND(SUM(CASE WHEN qty_balance > 0 THEN qty_balance ELSE 0 END), 4) AS live_qty
                FROM inv_material_fifo_lot
                GROUP BY division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
             ) l
               ON l.division_id = s.division_id
              AND l.destination_type = s.destination_type
              AND l.item_id <=> s.item_id
              AND l.material_id <=> s.material_id
              AND l.buy_uom_id <=> s.buy_uom_id
              AND l.content_uom_id = s.content_uom_id
              AND l.profile_key <=> s.profile_key
             WHERE s.division_id = ?
               AND s.destination_type = ?
               AND s.material_id = ?
               AND s.closing_qty_content < 0
             ORDER BY s.id ASC',
            [$divisionId, $destinationType, $materialId]
        )->result_array();
        if (!empty($negativeRows)) {
            $row = $negativeRows[0];
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    'Saldo bulanan profile sudah negatif sebelum batch diposting.',
                    [
                        'monthly_stock_id' => (int)($row['id'] ?? 0),
                        'profile_key' => (string)($row['profile_key'] ?? ''),
                        'closing_qty_buy' => round((float)($row['closing_qty_buy'] ?? 0), 4),
                        'closing_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
                        'fifo_live_qty' => round((float)($row['fifo_live_qty'] ?? 0), 4),
                        'hint' => 'Ini drift data historis. Repair saldo bulanan exact profile ini dulu sebelum posting ulang.',
                    ]
                ),
            ];
        }

        return ['ok' => true];
    }

    private function format_material_usage_error(array $line, string $reason, array $context = []): string
    {
        $name = trim((string)($line['material_name'] ?? $line['item_name'] ?? ''));
        if ($name === '') {
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : 0;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : 0;
            if ($itemId > 0) {
                $row = $this->ci->db->select('item_name')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
                $name = trim((string)($row['item_name'] ?? ''));
            }
            if ($name === '' && $materialId > 0) {
                $row = $this->ci->db->select('material_name')->from('mst_material')->where('id', $materialId)->limit(1)->get()->row_array();
                $name = trim((string)($row['material_name'] ?? ''));
            }
        }
        if ($name === '') {
            $name = 'item/material #' . (int)($line['item_id'] ?? $line['material_id'] ?? 0);
        }

        $parts = ['Batch gagal pada bahan ' . $name . ': ' . $reason];
        if (!empty($context['monthly_stock_id'])) {
            $parts[] = 'monthly_stock_id=' . (int)$context['monthly_stock_id'];
        }
        if (!empty($context['profile_key'])) {
            $parts[] = 'profile_key=' . (string)$context['profile_key'];
        }
        if (isset($context['buy_uom_id']) && isset($context['content_uom_id'])) {
            $parts[] = 'uom_beli=' . (string)$context['buy_uom_id'] . ', uom_isi=' . (string)$context['content_uom_id'];
        }
        if (isset($context['profile_content_per_buy'])) {
            $parts[] = 'profile_content_per_buy=' . (string)$context['profile_content_per_buy'];
        }
        if (isset($context['closing_qty_buy']) || isset($context['closing_qty_content'])) {
            $parts[] = 'saldo_bulanan=('
                . (isset($context['closing_qty_buy']) ? (string)$context['closing_qty_buy'] : '-')
                . ' buy / '
                . (isset($context['closing_qty_content']) ? (string)$context['closing_qty_content'] : '-')
                . ' content)';
        }
        if (isset($context['fifo_live_qty'])) {
            $parts[] = 'fifo_live_qty=' . (string)$context['fifo_live_qty'];
        }
        if (!empty($context['source_lot_no'])) {
            $parts[] = 'lot=' . (string)$context['source_lot_no'];
        }
        if (!empty($context['detail'])) {
            $parts[] = 'detail=' . (string)$context['detail'];
        }
        if (!empty($context['hint'])) {
            $parts[] = (string)$context['hint'];
        }

        return implode(' | ', $parts);
    }

    private function resolve_inventory_snapshot_for_allocation(array $allocation, int $divisionId, string $destinationType, string $movementDate): array
    {
        $sourceLot = (array)($allocation['source_lot'] ?? []);
        $snapshot = [
            'item_id' => !empty($sourceLot['item_id']) ? (int)$sourceLot['item_id'] : null,
            'material_id' => !empty($sourceLot['material_id']) ? (int)$sourceLot['material_id'] : null,
            'buy_uom_id' => !empty($sourceLot['buy_uom_id']) ? (int)$sourceLot['buy_uom_id'] : null,
            'content_uom_id' => !empty($sourceLot['content_uom_id']) ? (int)$sourceLot['content_uom_id'] : null,
            'profile_key' => !empty($sourceLot['profile_key']) ? (string)$sourceLot['profile_key'] : null,
            'profile_name' => null,
            'profile_brand' => null,
            'profile_description' => null,
            'profile_expired_date' => $sourceLot['expiry_date'] ?? null,
            'profile_content_per_buy' => 1.0,
            'profile_buy_uom_code' => null,
            'profile_content_uom_code' => null,
        ];

        if ($this->ci->db->table_exists('inv_stock_movement_log')) {
            $select = 'item_id, material_id, buy_uom_id, content_uom_id, profile_key, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code';
            $row = $this->ci->db->query(
                'SELECT ' . $select . '
                 FROM inv_stock_movement_log
                 WHERE movement_scope = ?
                   AND division_id = ?
                   AND destination_type = ?
                   AND item_id <=> ?
                   AND material_id <=> ?
                   AND buy_uom_id <=> ?
                   AND content_uom_id = ?
                   AND profile_key <=> ?
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'DIVISION',
                    $divisionId,
                    $destinationType,
                    $snapshot['item_id'],
                    $snapshot['material_id'],
                    $snapshot['buy_uom_id'],
                    (int)$snapshot['content_uom_id'],
                    $snapshot['profile_key'],
                ]
            )->row_array();
            if ($row) {
                $snapshot = array_merge($snapshot, $row);
            }
        }

        $needsProfileHydration =
            empty($snapshot['profile_name'])
            || empty($snapshot['buy_uom_id'])
            || empty($snapshot['content_uom_id'])
            || round((float)($snapshot['profile_content_per_buy'] ?? 0), 6) <= 0.000001;
        if ($needsProfileHydration && $this->ci->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($movementDate));
            $row = $this->ci->db->query(
                'SELECT item_id, material_id, buy_uom_id, content_uom_id, profile_key, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code
                 FROM inv_division_monthly_stock
                 WHERE division_id = ?
                   AND destination_type = ?
                   AND month_key <= ?
                   AND item_id <=> ?
                   AND material_id <=> ?
                   AND buy_uom_id <=> ?
                   AND content_uom_id = ?
                   AND profile_key <=> ?
                 ORDER BY month_key DESC, updated_at DESC, id DESC
                 LIMIT 1',
                [
                    $divisionId,
                    $destinationType,
                    $targetMonth,
                    $snapshot['item_id'],
                    $snapshot['material_id'],
                    $snapshot['buy_uom_id'],
                    (int)$snapshot['content_uom_id'],
                    $snapshot['profile_key'],
                ]
            )->row_array();
            if ($row) {
                $snapshot = array_merge($snapshot, $row);
            }
        }

        $sameUom = !empty($snapshot['buy_uom_id']) && !empty($snapshot['content_uom_id'])
            && (int)$snapshot['buy_uom_id'] === (int)$snapshot['content_uom_id'];
        if ($sameUom) {
            $snapshot['profile_content_per_buy'] = 1.0;
        } else {
            $snapshot['profile_content_per_buy'] = max(0.000001, round((float)($snapshot['profile_content_per_buy'] ?? 1), 6));
        }
        return $snapshot;
    }

    private function generate_component_output_lot_no(string $movementDate, int $componentId, int $sourceId): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICL' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT);
    }

    private function generate_component_opening_lot_no(string $movementDate, int $componentId, int $sourceId, int $sourceLineId): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICO' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceLineId), 4, '0', STR_PAD_LEFT);
    }

    private function generate_component_adjustment_lot_no(string $movementDate, int $componentId, int $sourceId, int $sourceLineId, string $suffix = 'A'): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICA' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceLineId), 4, '0', STR_PAD_LEFT) . strtoupper(substr($suffix, 0, 1));
    }

    /**
     * Setelah adjustment, rekonsiliasi lot ke saldo bulanan.
     * Aturan: total lot = max(0, closing_qty).
     * Jika closing_qty negatif (akibat POS flexibilitas), semua lot OPEN ditutup.
     * Jika closing_qty < lot total, trim lot terbaru (LIFO) sejumlah selisihnya.
     */
    private function trim_component_lots_to_monthly(
        string $locationType,
        ?int $divisionId,
        int $componentId,
        int $uomId
    ): void {
        $db = $this->ci->db;

        if (!$db->table_exists('inv_component_monthly_stock') || !$db->table_exists('inv_component_lot')) {
            return;
        }

        $divSql    = $divisionId !== null ? 'ms.division_id = ' . $divisionId : 'ms.division_id IS NULL';
        $divLotSql = $divisionId !== null ? 'l.division_id = ' . $divisionId  : 'l.division_id IS NULL';

        $monthRow = $db->query(
            "SELECT COALESCE(ms.closing_qty, 0) AS closing_qty
               FROM inv_component_monthly_stock ms
              WHERE ms.location_type = ?
                AND {$divSql}
                AND ms.component_id  = ?
                AND ms.uom_id        = ?
              ORDER BY ms.month_key DESC
              LIMIT 1",
            [$locationType, $componentId, $uomId]
        )->row_array();

        $closingQty = round((float)($monthRow['closing_qty'] ?? 0), 4);
        $targetLot  = max(0.0, $closingQty);

        $openLots = $db->query(
            "SELECT id, qty_balance
               FROM inv_component_lot l
              WHERE l.location_type = ?
                AND {$divLotSql}
                AND l.component_id  = ?
                AND l.uom_id        = ?
                AND l.status        = 'OPEN'
                AND l.qty_balance   > 0.0001
              ORDER BY l.receipt_date DESC, l.id DESC",
            [$locationType, $componentId, $uomId]
        )->result_array();

        if (empty($openLots)) {
            return;
        }

        $totalBalance = round(array_sum(array_column($openLots, 'qty_balance')), 4);
        if ($totalBalance <= $targetLot + 0.0001) {
            return;
        }

        $remaining = round($totalBalance - $targetLot, 4);
        $now       = date('Y-m-d H:i:s');

        foreach ($openLots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }
            $lotQty  = round((float)($lot['qty_balance'] ?? 0), 4);
            $trim    = min($lotQty, $remaining);
            $newQty  = round($lotQty - $trim, 4);
            $db->where('id', (int)$lot['id'])->update('inv_component_lot', [
                'qty_balance' => $newQty,
                'status'      => $newQty < 0.0001 ? 'CLOSED' : 'OPEN',
                'updated_at'  => $now,
            ]);
            $remaining = round($remaining - $trim, 4);
        }
    }

    /**
     * Reconcile FIFO lots to match inv_component_monthly_stock.closing_qty
     * for a specific month_key. Called after generate to ensure lots = stock.
     * Trims excess (LIFO) or registers a OPNAME_CUTOFF lot for any deficit.
     */
    public function cutoff_lots_to_monthly_stock(
        string $locationType,
        ?int $divisionId,
        int $componentId,
        int $uomId,
        string $monthKey
    ): void {
        $db = $this->ci->db;

        if (!$db->table_exists('inv_component_monthly_stock') || !$db->table_exists('inv_component_lot')) {
            return;
        }

        $divSql    = $divisionId !== null ? 'ms.division_id = ' . (int)$divisionId : 'ms.division_id IS NULL';
        $divLotSql = $divisionId !== null ? 'l.division_id  = ' . (int)$divisionId  : 'l.division_id  IS NULL';

        $monthRow = $db->query(
            "SELECT ms.id AS monthly_stock_id, COALESCE(ms.closing_qty, 0) AS closing_qty, COALESCE(ms.avg_cost, 0) AS avg_cost
               FROM inv_component_monthly_stock ms
              WHERE ms.location_type = ?
                AND {$divSql}
                AND ms.component_id  = ?
                AND ms.uom_id        = ?
                AND ms.month_key     = ?
              LIMIT 1",
            [$locationType, $componentId, $uomId, $monthKey]
        )->row_array();

        if (empty($monthRow)) {
            return;
        }

        $closingQty = round((float)($monthRow['closing_qty'] ?? 0), 4);
        $targetLot  = max(0.0, $closingQty);
        $avgCost    = round((float)($monthRow['avg_cost']    ?? 0), 6);

        $openLots = $db->query(
            "SELECT id, qty_balance
               FROM inv_component_lot l
              WHERE l.location_type = ?
                AND {$divLotSql}
                AND l.component_id  = ?
                AND l.uom_id        = ?
                AND l.status        = 'OPEN'
                AND l.qty_balance   > 0.0001
              ORDER BY l.receipt_date DESC, l.id DESC",
            [$locationType, $componentId, $uomId]
        )->result_array();

        $totalBalance = empty($openLots) ? 0.0 : round(array_sum(array_column($openLots, 'qty_balance')), 4);
        $now = date('Y-m-d H:i:s');

        if ($totalBalance > $targetLot + 0.0001) {
            $cutoffQty = round($totalBalance - $targetLot, 4);
            $this->ci->load->library('ComponentLotManager');
            $issue = $this->ci->componentlotmanager->consumeUsage([
                'issue_date' => $monthKey,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'qty_out' => $cutoffQty,
                'source_module' => 'OPNAME_CUTOFF',
                'source_table' => 'inv_component_monthly_stock',
                'source_id' => (int)($monthRow['monthly_stock_id'] ?? 0),
                'notes' => 'Cut-off reconciliation: lot disesuaikan ke saldo closing yang disetujui.',
            ]);
            if (!($issue['ok'] ?? false)) {
                throw new RuntimeException((string)($issue['message'] ?? 'Gagal membuat issue lot cut-off component.'));
            }
            foreach ((array)($issue['data']['allocations'] ?? []) as $allocation) {
                $this->record_component_cutoff_event([
                    'event_date' => $monthKey,
                    'period_month' => $monthKey,
                    'location_scope' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'content_uom_id' => $uomId,
                    'lot_id' => !empty($allocation['lot_id']) ? (int)$allocation['lot_id'] : null,
                    'direction' => 'OUT',
                    'qty' => round((float)($allocation['qty_out'] ?? 0), 4),
                    'unit_cost' => round((float)($allocation['unit_cost'] ?? 0), 6),
                    'source_table' => 'inv_component_monthly_stock',
                    'source_id' => (int)($monthRow['monthly_stock_id'] ?? 0),
                    'notes' => 'Cut-off reconciliation out; issue lot #' . (int)($issue['data']['issue_id'] ?? 0),
                ]);
            }
        } elseif ($targetLot > $totalBalance + 0.0001 && $avgCost > 0) {
            $deficitQty = round($targetLot - $totalBalance, 4);
            $this->ci->load->library('ComponentLotManager');
            $inbound = $this->ci->componentlotmanager->registerProductionInboundLot([
                'location_type' => $locationType,
                'division_id'   => $divisionId,
                'component_id'  => $componentId,
                'uom_id'        => $uomId,
                'qty_in'        => $deficitQty,
                'unit_cost'     => $avgCost,
                'receipt_date'  => $monthKey,
                'lot_no'        => 'CUTOFF-' . str_replace('-', '', substr($monthKey, 0, 7)) . '-' . $componentId,
                'source_module' => 'OPNAME_CUTOFF',
                'source_table'  => 'inv_component_monthly_stock',
                'source_id'     => (int)($monthRow['monthly_stock_id'] ?? 0),
            ]);
            if (!($inbound['ok'] ?? false)) {
                throw new RuntimeException((string)($inbound['message'] ?? 'Gagal membuat lot inbound cut-off component.'));
            }
            $this->record_component_cutoff_event([
                'event_date' => $monthKey,
                'period_month' => $monthKey,
                'location_scope' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'content_uom_id' => $uomId,
                'lot_id' => !empty($inbound['data']['lot_id']) ? (int)$inbound['data']['lot_id'] : null,
                'direction' => 'IN',
                'qty' => $deficitQty,
                'unit_cost' => $avgCost,
                'source_table' => 'inv_component_monthly_stock',
                'source_id' => (int)($monthRow['monthly_stock_id'] ?? 0),
                'notes' => 'Cut-off reconciliation in; opening lot created to match approved closing.',
            ]);
        }
    }

    private function record_component_cutoff_event(array $payload): void
    {
        if (!$this->ci->db->table_exists('inv_stock_cutoff_event')) {
            return;
        }

        $qty = round((float)($payload['qty'] ?? 0), 4);
        if ($qty <= 0.0001) {
            return;
        }
        $unitCost = round((float)($payload['unit_cost'] ?? 0), 6);
        $this->ci->db->insert('inv_stock_cutoff_event', [
            'stock_domain' => 'COMPONENT',
            'event_date' => (string)$payload['event_date'],
            'period_month' => date('Y-m-01', strtotime((string)$payload['period_month'])),
            'location_scope' => (string)$payload['location_scope'],
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'component_id' => !empty($payload['component_id']) ? (int)$payload['component_id'] : null,
            'content_uom_id' => !empty($payload['content_uom_id']) ? (int)$payload['content_uom_id'] : null,
            'lot_id' => !empty($payload['lot_id']) ? (int)$payload['lot_id'] : null,
            'direction' => (string)$payload['direction'],
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_value' => round($qty * $unitCost, 2),
            'source_table' => (string)$payload['source_table'],
            'source_id' => !empty($payload['source_id']) ? (int)$payload['source_id'] : null,
            'notes' => substr((string)($payload['notes'] ?? ''), 0, 255),
        ]);
    }

    private function load_balance_state(string $locationType, ?int $divisionId, int $componentId, int $uomId, string $movementDate): array
    {
        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($movementDate));
            $row = $this->ci->db->query(
                'SELECT month_key, closing_qty AS qty_on_hand, avg_cost, total_value
                 FROM inv_component_monthly_stock
                 WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND month_key <= ?
                 ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC
                 LIMIT 1 FOR UPDATE',
                [$locationType, $divisionId, $componentId, $uomId, $targetMonth]
            )->row_array();
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function resolve_component_name(int $componentId): string
    {
        if ($componentId <= 0) {
            return 'component';
        }

        $row = $this->ci->db
            ->select('component_name')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $name = trim((string)($row['component_name'] ?? ''));
        return $name !== '' ? $name : ('component #' . $componentId);
    }

    private function format_component_adjustment_error(int $componentId, int $uomId, string $message): string
    {
        $name = $this->resolve_component_name($componentId);
        return 'Komponen ' . $name . ': ' . trim($message);
    }

    private function current_avg_cost(string $locationType, ?int $divisionId, int $componentId, int $uomId): float
    {
        if ($this->ci->db->table_exists('inv_component_lot')) {
            $lotRow = $this->ci->db->query(
                "SELECT
                    ROUND(COALESCE(unit_cost, 0), 6) AS avg_cost
                 FROM inv_component_lot
                 WHERE component_id = ?
                   AND uom_id = ?
                   AND division_id <=> ?
                   AND location_type = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$componentId, $uomId, $divisionId, $locationType]
            )->row_array();
            $lotCost = round((float)($lotRow['avg_cost'] ?? 0), 6);
            if ($lotCost > 0) {
                return $lotCost;
            }
        }

        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $row = $this->ci->db->query(
                'SELECT avg_cost FROM inv_component_monthly_stock WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC LIMIT 1',
                [$locationType, $divisionId, $componentId, $uomId]
            )->row_array();
            if (!empty($row)) {
                return round((float)($row['avg_cost'] ?? 0), 6);
            }
        }

        return 0.0;
    }

    private function generate_movement_no(string $movementDate): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        $prefix = 'ICM' . $datePart;
        $row = $this->ci->db->select('movement_no')
            ->from('inv_component_movement_log')
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
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function valid_location(string $locationType): bool
    {
        return in_array($locationType, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true);
    }

    private function resolve_inventory_destination_type(string $locationType): ?string
    {
        $locationType = strtoupper(trim($locationType));
        return in_array($locationType, ['BAR', 'KITCHEN', 'ROASTERY', 'BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT'], true) ? $locationType : null;
    }

    private function formatThrowableMessage(string $prefix, Throwable $e): string
    {
        // The detailed exception is already recorded in the server log by the
        // caller. Returning file names or SQL text to the operator causes the
        // AJAX flow to look broken and reveals implementation details.
        return trim($prefix) . ' Muat ulang halaman lalu coba kembali. Jika masih berulang, hubungi administrator.';
    }
}
