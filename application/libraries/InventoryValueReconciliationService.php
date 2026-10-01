<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlled reconciliation for a value-only stock health finding.
 *
 * Quantity reconciliation and value reconciliation are deliberately separate:
 * this service refuses to run unless the active monthly quantity already
 * matches the total of OPEN lots. The record is append-only; a later mistake
 * is corrected by another revaluation document rather than editing history.
 */
class InventoryValueReconciliationService
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        return $this->ci->db->table_exists('inv_stock_value_reconciliation')
            && $this->ci->db->table_exists('inv_stock_value_reconciliation_lot');
    }

    /**
     * Returns one exact active monthly profile plus its currently OPEN lots.
     * The caller never supplies an amount as the source of truth.
     */
    public function context(array $input): array
    {
        $normalized = $this->normalizeContextInput($input);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }

        $db = $this->ci->db;
        $domain = $normalized['stock_domain'];
        $stockTable = $domain === 'COMPONENT'
            ? 'inv_component_monthly_stock'
            : ($normalized['location_scope'] === 'WAREHOUSE'
                ? 'inv_warehouse_monthly_stock'
                : 'inv_division_monthly_stock');
        $lotTable = $domain === 'COMPONENT' ? 'inv_component_lot' : 'inv_material_fifo_lot';

        if (!$db->table_exists($stockTable) || !$db->table_exists($lotTable)) {
            return ['ok' => false, 'message' => 'Tabel stok atau lot untuk koreksi nilai belum tersedia.'];
        }

        $stock = $this->fetchStockRow($normalized, false);
        if (empty($stock)) {
            return ['ok' => false, 'message' => 'Saldo bulan aktif untuk identitas yang dipilih tidak ditemukan. Muat ulang Stock Health lalu pilih kembali barisnya.'];
        }
        $lots = $this->fetchOpenLots($normalized, false);

        $stockQty = round((float)($stock['stock_qty'] ?? 0), 4);
        $stockValue = round((float)($stock['stock_value'] ?? 0), 2);
        $lotQty = round(array_sum(array_map(static function (array $lot): float {
            return (float)($lot['qty_balance'] ?? 0);
        }, $lots)), 4);
        $lotValue = round(array_sum(array_map(static function (array $lot): float {
            return (float)($lot['qty_balance'] ?? 0) * (float)($lot['unit_cost'] ?? 0);
        }, $lots)), 2);
        $qtyGap = round($stockQty - $lotQty, 4);
        $valueGap = round($stockValue - $lotValue, 2);
        $isActiveMonth = $normalized['month'] === date('Y-m-01');
        $canPost = $isActiveMonth
            && abs($qtyGap) <= 0.0001
            && $stockQty > 0.0001
            && !empty($lots)
            && abs($valueGap) > 1;

        return [
            'ok' => true,
            'context' => $normalized,
            'stock' => $stock,
            'lots' => $lots,
            'stock_qty' => $stockQty,
            'lot_qty' => $lotQty,
            'stock_value' => $stockValue,
            'lot_value' => $lotValue,
            'qty_gap' => $qtyGap,
            'value_gap' => $valueGap,
            'is_active_month' => $isActiveMonth,
            'can_post' => $canPost,
            'block_message' => $this->contextBlockMessage($isActiveMonth, $stockQty, $lotQty, $qtyGap, $lots, $valueGap),
        ];
    }

    /**
     * Posts an append-only value alignment after the user chooses the
     * authoritative value. It changes no quantity and never touches CLOSED lots.
     */
    public function post(array $payload, int $actorUserId): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Fondasi Koreksi Nilai Persediaan belum tersedia. Jalankan SQL inventory terbaru terlebih dahulu.'];
        }

        $state = $this->context($payload);
        if (!($state['ok'] ?? false)) {
            return $state;
        }
        if (empty($state['can_post'])) {
            return ['ok' => false, 'message' => (string)($state['block_message'] ?? 'Koreksi nilai belum aman diposting.')];
        }

        $mode = strtoupper(trim((string)($payload['resolution_mode'] ?? 'LOT_TO_STOCK')));
        if (!in_array($mode, ['LOT_TO_STOCK', 'STOCK_TO_LOT', 'MANUAL_TOTAL_VALUE'], true)) {
            return ['ok' => false, 'message' => 'Pilih sumber nilai yang valid.'];
        }
        $reason = trim((string)($payload['reason'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));
        $confirmation = strtoupper(trim((string)($payload['confirmation'] ?? '')));
        if ($reason === '' || $confirmation !== 'NILAI') {
            return ['ok' => false, 'message' => 'Pilih alasan dan ketik NILAI untuk mengonfirmasi koreksi nilai.'];
        }

        $targetValue = 0.0;
        $manualUnitCost = null;
        if ($mode === 'LOT_TO_STOCK') {
            $targetValue = (float)($state['lot_value'] ?? 0);
        } elseif ($mode === 'STOCK_TO_LOT') {
            $targetValue = (float)($state['stock_value'] ?? 0);
        } else {
            $unitProvided = array_key_exists('manual_unit_cost', $payload)
                && trim((string)$payload['manual_unit_cost']) !== '';
            $totalProvided = array_key_exists('manual_total_value', $payload)
                && trim((string)$payload['manual_total_value']) !== '';
            if ($unitProvided && !is_numeric($payload['manual_unit_cost'])) {
                return ['ok' => false, 'message' => 'HPP per unit harus berupa angka yang valid.'];
            }
            if ($unitProvided) {
                $manualUnitCost = round((float)$payload['manual_unit_cost'], 6);
                if (!is_finite($manualUnitCost) || $manualUnitCost < -0.000001) {
                    return ['ok' => false, 'message' => 'HPP per unit tidak boleh negatif.'];
                }
                $targetValue = round((float)($state['stock_qty'] ?? 0) * $manualUnitCost, 2);
                if ($totalProvided && (!is_numeric($payload['manual_total_value']) || !is_finite((float)$payload['manual_total_value']))) {
                    return ['ok' => false, 'message' => 'Total nilai harus berupa angka yang valid.'];
                }
                if ($totalProvided && abs($targetValue - round((float)$payload['manual_total_value'], 2)) > 0.01) {
                    return ['ok' => false, 'message' => 'HPP per unit dan total nilai tidak konsisten. Muat ulang perhitungan lalu periksa kembali angkanya.'];
                }
                $unitNote = 'HPP/unit ' . number_format($manualUnitCost, 6, '.', '');
                $notes = $notes !== '' ? ($notes . ' | ' . $unitNote) : $unitNote;
            } elseif (!$totalProvided || !is_numeric($payload['manual_total_value']) || !is_finite((float)$payload['manual_total_value'])) {
                return ['ok' => false, 'message' => 'Isi HPP per unit atau total nilai yang benar untuk koreksi manual.'];
            } else {
                $targetValue = (float)$payload['manual_total_value'];
            }
        }
        $targetValue = round($targetValue, 2);
        if ($targetValue < -0.0001) {
            return ['ok' => false, 'message' => 'Nilai stok setelah koreksi tidak boleh negatif. Pilih nilai lot atau periksa sumber pergerakannya terlebih dahulu.'];
        }

        $context = (array)$state['context'];
        $this->ci->load->library('InventoryPeriodGuard');
        $period = $this->ci->inventoryperiodguard->assertOpen(
            (string)$context['stock_domain'],
            (string)$context['month'],
            'koreksi nilai persediaan'
        );
        if (!($period['ok'] ?? false)) {
            return $period;
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $period = $this->ci->inventoryperiodguard->lockActivePeriodsForWrite([[
                'stock_domain' => (string)$context['stock_domain'],
                'period_month' => (string)$context['month'],
            ]]);
            if (!($period['ok'] ?? false)) {
                throw new RuntimeException((string)($period['message'] ?? 'Periode stok gagal dikunci.'));
            }
            $lockedStock = $this->fetchStockRow($context, true);
            $lockedLots = $this->fetchOpenLots($context, true);
            if (empty($lockedStock)) {
                throw new RuntimeException('Saldo bulan aktif berubah atau hilang sebelum koreksi nilai diposting. Muat ulang halaman lalu coba kembali.');
            }

            $stockQty = round((float)($lockedStock['stock_qty'] ?? 0), 4);
            $stockValue = round((float)($lockedStock['stock_value'] ?? 0), 2);
            $lotQty = round(array_sum(array_map(static function (array $lot): float {
                return (float)($lot['qty_balance'] ?? 0);
            }, $lockedLots)), 4);
            $lotValue = round(array_sum(array_map(static function (array $lot): float {
                return (float)($lot['qty_balance'] ?? 0) * (float)($lot['unit_cost'] ?? 0);
            }, $lockedLots)), 2);

            if (abs($stockQty - $lotQty) > 0.0001 || $stockQty <= 0.0001 || empty($lockedLots)) {
                throw new RuntimeException('Jumlah stok atau lot sudah berubah. Selesaikan selisih jumlah melalui Recon Stok Fisik, lalu muat ulang halaman ini.');
            }
            if (abs($stockQty - (float)($state['stock_qty'] ?? 0)) > 0.0001 || abs($lotQty - (float)($state['lot_qty'] ?? 0)) > 0.0001) {
                throw new RuntimeException('Jumlah stok atau lot berubah sejak halaman dibuka. Muat ulang untuk menghitung HPP dengan saldo terbaru.');
            }
            if (abs($stockValue - (float)($state['stock_value'] ?? 0)) > 1 || abs($lotValue - (float)($state['lot_value'] ?? 0)) > 1) {
                throw new RuntimeException('Nilai stok atau lot sudah berubah sejak halaman dibuka. Muat ulang untuk memakai angka terbaru.');
            }

            if ($manualUnitCost !== null) {
                $targetValue = round($stockQty * $manualUnitCost, 2);
            }

            $revalueLots = $mode !== 'LOT_TO_STOCK';
            $lotPlan = $this->buildLotPlan($lockedLots, $revalueLots ? $targetValue : $lotValue);
            if (!$revalueLots) {
                $lotPlan = $this->buildLotPlan($lockedLots, $lotValue, true);
            }

            $now = date('Y-m-d H:i:s');
            $header = [
                'revaluation_no' => $this->generateRevaluationNo((string)$context['stock_domain'], (string)$context['month']),
                'revaluation_date' => date('Y-m-d'),
                'period_month' => (string)$context['month'],
                'stock_domain' => (string)$context['stock_domain'],
                'stock_scope' => (string)$context['location_scope'],
                'division_id' => !empty($context['division_id']) ? (int)$context['division_id'] : null,
                'location_type' => $this->nullableString($context['location_type'] ?? null),
                'item_id' => !empty($context['item_id']) ? (int)$context['item_id'] : null,
                'material_id' => !empty($context['material_id']) ? (int)$context['material_id'] : null,
                'component_id' => !empty($context['component_id']) ? (int)$context['component_id'] : null,
                'buy_uom_id' => !empty($context['buy_uom_id']) ? (int)$context['buy_uom_id'] : null,
                'content_uom_id' => !empty($context['uom_id']) ? (int)$context['uom_id'] : null,
                'profile_key' => $this->nullableString($context['profile_key'] ?? null),
                'monthly_stock_id' => (int)($lockedStock['id'] ?? 0),
                'stock_qty_snapshot' => $stockQty,
                'lot_qty_snapshot' => $lotQty,
                'stock_value_before' => $stockValue,
                'lot_value_before' => $lotValue,
                'stock_value_after' => $targetValue,
                'lot_value_after' => $targetValue,
                'resolution_mode' => $mode,
                'reason' => substr($reason, 0, 120),
                'notes' => $this->nullableString($notes),
                'status' => 'POSTED',
                'created_by' => $actorUserId > 0 ? $actorUserId : null,
                'posted_by' => $actorUserId > 0 ? $actorUserId : null,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $db->insert('inv_stock_value_reconciliation', $header);
            $revaluationId = (int)$db->insert_id();
            if ($revaluationId <= 0) {
                $error = $db->error();
                throw new RuntimeException('Gagal membuat dokumen koreksi nilai: ' . (string)($error['message'] ?? 'unknown error'));
            }

            $this->updateMonthlyStockValue($context, (int)$lockedStock['id'], $stockQty, $targetValue, $revaluationId, $now);
            foreach ($lotPlan as $lot) {
                $line = [
                    'revaluation_id' => $revaluationId,
                    'lot_id' => (int)$lot['id'],
                    'qty_balance_snapshot' => round((float)$lot['qty_balance'], 4),
                    'old_unit_cost' => round((float)$lot['old_unit_cost'], 6),
                    'new_unit_cost' => round((float)$lot['new_unit_cost'], 6),
                    'old_total_value' => round((float)$lot['old_total_value'], 2),
                    'new_total_value' => round((float)$lot['new_total_value'], 2),
                    'created_at' => $now,
                ];
                $db->insert('inv_stock_value_reconciliation_lot', $line);
                if ($revalueLots) {
                    $this->updateOpenLotUnitCost($context, (int)$lot['id'], (float)$lot['new_unit_cost'], $now);
                }
            }

            if ($db->table_exists('aud_transaction_log')) {
                $db->insert('aud_transaction_log', [
                    'module_code' => 'INVENTORY',
                    'action_code' => 'STOCK_VALUE_REVALUATION',
                    'entity_table' => 'inv_stock_value_reconciliation',
                    'entity_id' => $revaluationId,
                    'transaction_no' => $header['revaluation_no'],
                    'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'after_payload' => json_encode([
                        'domain' => $context['stock_domain'],
                        'scope' => $context['location_scope'],
                        'monthly_stock_id' => (int)$lockedStock['id'],
                        'stock_qty' => $stockQty,
                        'target_total_value' => $targetValue,
                        'resolution_mode' => $mode,
                        'open_lot_count' => count($lotPlan),
                    ]),
                    'notes' => 'Koreksi nilai stok dan/atau lot OPEN tanpa perubahan kuantitas.',
                ]);
            }

            if (!$db->trans_status()) {
                throw new RuntimeException('Transaksi koreksi nilai dibatalkan oleh database.');
            }
            $db->trans_commit();
        } catch (Throwable $e) {
            $db->trans_rollback();
            log_message('error', 'inventory value reconciliation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $availabilityRefresh = $this->refreshAvailabilityAfterCommit(
            $context,
            'STOCK_VALUE_REVALUATION',
            $revaluationId,
            $actorUserId
        );
        return [
            'ok' => true,
            'id' => $revaluationId,
            'revaluation_no' => $header['revaluation_no'],
            'target_value' => $targetValue,
            'availability_refresh' => $availabilityRefresh,
            'warning' => $availabilityRefresh['warning'],
        ];
    }

    public function listRecords(string $month, int $limit = 50): array
    {
        if (!$this->isReady()) {
            return [];
        }
        $month = $this->normalizeMonth($month) ?? date('Y-m-01');
        return $this->ci->db
            ->select('r.*, COALESCE(i.item_name, m.material_name, c.component_name, r.profile_key, \'-\') AS inventory_name', false)
            ->from('inv_stock_value_reconciliation r')
            ->join('mst_item i', 'i.id = r.item_id', 'left')
            ->join('mst_material m', 'm.id = r.material_id', 'left')
            ->join('mst_component c', 'c.id = r.component_id', 'left')
            ->where('r.period_month', $month)
            ->order_by('r.id', 'DESC')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->result_array();
    }

    /**
     * Voids the latest posted value correction only when its exact before/after
     * state is still present. The original document and lines remain intact;
     * the operational values are restored inside the same transaction.
     */
    public function voidRecord(int $revaluationId, int $actorUserId, string $voidNotes): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Fondasi Koreksi Nilai Persediaan belum tersedia.'];
        }
        if ($revaluationId <= 0 || trim($voidNotes) === '') {
            return ['ok' => false, 'message' => 'ID dokumen dan alasan void wajib diisi.'];
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $header = $db->query(
                'SELECT * FROM inv_stock_value_reconciliation WHERE id = ? FOR UPDATE',
                [$revaluationId]
            )->row_array();
            if (empty($header)) {
                throw new RuntimeException('Dokumen koreksi nilai tidak ditemukan.');
            }
            if (strtoupper(trim((string)($header['status'] ?? ''))) !== 'POSTED') {
                throw new RuntimeException('Dokumen koreksi ini sudah VOID atau tidak berstatus POSTED.');
            }

            $contextInput = [
                'month' => (string)($header['period_month'] ?? ''),
                'stock_domain' => (string)($header['stock_domain'] ?? ''),
                'location_scope' => (string)($header['stock_scope'] ?? ''),
                'location_type' => (string)($header['location_type'] ?? ''),
                'division_id' => (int)($header['division_id'] ?? 0),
                'item_id' => (int)($header['item_id'] ?? 0),
                'material_id' => (int)($header['material_id'] ?? 0),
                'component_id' => (int)($header['component_id'] ?? 0),
                'buy_uom_id' => (int)($header['buy_uom_id'] ?? 0),
                'uom_id' => (int)($header['content_uom_id'] ?? 0),
                'profile_key' => (string)($header['profile_key'] ?? ''),
                'monthly_stock_id' => (int)($header['monthly_stock_id'] ?? 0),
            ];
            $state = $this->context($contextInput);
            if (!($state['ok'] ?? false)) {
                throw new RuntimeException((string)($state['message'] ?? 'Konteks dokumen koreksi tidak dapat dibaca.'));
            }
            $context = (array)($state['context'] ?? []);

            $this->ci->load->library('InventoryPeriodGuard');
            $period = $this->ci->inventoryperiodguard->lockActivePeriodsForWrite([[
                'stock_domain' => (string)$context['stock_domain'],
                'period_month' => (string)$context['month'],
            ]]);
            if (!($period['ok'] ?? false)) {
                throw new RuntimeException((string)($period['message'] ?? 'Periode stok tidak terbuka.'));
            }

            $lockedStock = $this->fetchStockRow($context, true);
            $lockedLots = $this->fetchOpenLots($context, true);
            if (empty($lockedStock) || empty($lockedLots)) {
                throw new RuntimeException('Saldo stok atau lot OPEN tidak ditemukan. Void dibatalkan agar data tidak semakin tidak konsisten.');
            }

            $stockQty = round((float)($lockedStock['stock_qty'] ?? 0), 4);
            $stockValue = round((float)($lockedStock['stock_value'] ?? 0), 2);
            $lotQty = round(array_sum(array_map(static function (array $lot): float {
                return (float)($lot['qty_balance'] ?? 0);
            }, $lockedLots)), 4);
            $lotValue = round(array_sum(array_map(static function (array $lot): float {
                return (float)($lot['qty_balance'] ?? 0) * (float)($lot['unit_cost'] ?? 0);
            }, $lockedLots)), 2);
            if (abs($stockQty - (float)($header['stock_qty_snapshot'] ?? 0)) > 0.0001
                || abs($lotQty - (float)($header['lot_qty_snapshot'] ?? 0)) > 0.0001
                || abs($stockQty - $lotQty) > 0.0001) {
                throw new RuntimeException('Jumlah stok/lot sudah berubah setelah koreksi. Selesaikan audit terbaru lalu void dibatalkan.');
            }
            if (abs($stockValue - (float)($header['stock_value_after'] ?? 0)) > 1
                || abs($lotValue - (float)($header['lot_value_after'] ?? 0)) > 1) {
                throw new RuntimeException('Nilai stok/lot sudah berubah setelah koreksi. Void hanya boleh dilakukan pada dokumen terakhir; muat ulang dan periksa riwayat.');
            }

            $lotById = [];
            foreach ($lockedLots as $lot) {
                $lotById[(int)($lot['id'] ?? 0)] = $lot;
            }
            $lines = $db->where('revaluation_id', $revaluationId)
                ->order_by('id', 'ASC')
                ->get('inv_stock_value_reconciliation_lot')
                ->result_array();
            if (empty($lines)) {
                throw new RuntimeException('Rincian lot dokumen tidak ditemukan. Void dibatalkan.');
            }
            foreach ($lines as $line) {
                $lotId = (int)($line['lot_id'] ?? 0);
                $lot = $lotById[$lotId] ?? null;
                if ($lot === null) {
                    throw new RuntimeException('Lot pada dokumen sudah tidak OPEN atau tidak ditemukan. Void dibatalkan.');
                }
                if (abs((float)($lot['qty_balance'] ?? 0) - (float)($line['qty_balance_snapshot'] ?? 0)) > 0.0001
                    || abs((float)($lot['unit_cost'] ?? 0) - (float)($line['new_unit_cost'] ?? 0)) > 0.01) {
                    throw new RuntimeException('HPP lot sudah berubah setelah koreksi. Void dibatalkan agar tidak menimpa perubahan baru.');
                }
                if (abs((float)($line['old_unit_cost'] ?? 0) - (float)($line['new_unit_cost'] ?? 0)) > 0.000001) {
                    $this->updateOpenLotUnitCost($context, $lotId, (float)$line['old_unit_cost'], date('Y-m-d H:i:s'));
                }
            }

            $now = date('Y-m-d H:i:s');
            $this->updateMonthlyStockValue(
                $context,
                (int)$lockedStock['id'],
                $stockQty,
                round((float)($header['stock_value_before'] ?? 0), 2),
                $revaluationId,
                $now
            );
            $updated = $db->where('id', $revaluationId)->where('status', 'POSTED')->update('inv_stock_value_reconciliation', [
                'status' => 'VOID',
                'voided_by' => $actorUserId > 0 ? $actorUserId : null,
                'voided_at' => $now,
                'void_notes' => $this->nullableString($voidNotes),
                'updated_at' => $now,
            ]);
            if (!$updated) {
                throw new RuntimeException('Gagal menandai dokumen koreksi sebagai VOID.');
            }

            if ($db->table_exists('aud_transaction_log')) {
                $db->insert('aud_transaction_log', [
                    'module_code' => 'INVENTORY',
                    'action_code' => 'STOCK_VALUE_REVALUATION_VOID',
                    'entity_table' => 'inv_stock_value_reconciliation',
                    'entity_id' => $revaluationId,
                    'transaction_no' => (string)($header['revaluation_no'] ?? ''),
                    'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                    'after_payload' => json_encode([
                        'revaluation_id' => $revaluationId,
                        'restored_stock_value' => round((float)($header['stock_value_before'] ?? 0), 2),
                        'restored_lot_value' => round((float)($header['lot_value_before'] ?? 0), 2),
                    ]),
                    'notes' => 'Void koreksi nilai; nilai sebelum dokumen dipulihkan tanpa perubahan kuantitas.',
                ]);
            }

            if (!$db->trans_status()) {
                throw new RuntimeException('Transaksi void koreksi nilai dibatalkan oleh database.');
            }
            $db->trans_commit();
        } catch (Throwable $e) {
            $db->trans_rollback();
            log_message('error', 'inventory value reconciliation void failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $availabilityRefresh = $this->refreshAvailabilityAfterCommit(
            $context,
            'STOCK_VALUE_REVALUATION_VOID',
            $revaluationId,
            $actorUserId
        );
        return [
            'ok' => true,
            'id' => $revaluationId,
            'revaluation_no' => (string)($header['revaluation_no'] ?? ''),
            'availability_refresh' => $availabilityRefresh,
            'warning' => $availabilityRefresh['warning'],
        ];
    }

    private function normalizeContextInput(array $input): array
    {
        $month = $this->normalizeMonth((string)($input['month'] ?? $input['period_month'] ?? ''));
        $domain = strtoupper(trim((string)($input['stock_domain'] ?? '')));
        $scope = strtoupper(trim((string)($input['location_scope'] ?? $input['stock_scope'] ?? '')));
        $locationType = strtoupper(trim((string)($input['location_type'] ?? $input['destination_type'] ?? '')));
        $divisionId = (int)($input['division_id'] ?? 0);
        $stockId = (int)($input['monthly_stock_id'] ?? 0);
        if ($month === null || !in_array($domain, ['MATERIAL', 'COMPONENT'], true) || $stockId <= 0) {
            return ['ok' => false, 'message' => 'Konteks Stock Health tidak lengkap. Muat ulang halaman lalu pilih tindakan lagi.'];
        }
        if ($domain === 'MATERIAL') {
            if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
                return ['ok' => false, 'message' => 'Scope bahan baku untuk koreksi nilai tidak valid.'];
            }
            if ($scope === 'DIVISION' && ($divisionId <= 0 || $locationType === '')) {
                return ['ok' => false, 'message' => 'Koreksi nilai bahan baku divisi membutuhkan divisi dan area.'];
            }
        } elseif ($scope !== 'COMPONENT' || $locationType === '') {
            return ['ok' => false, 'message' => 'Koreksi nilai component membutuhkan lokasi component yang valid.'];
        }

        return [
            'ok' => true,
            'month' => $month,
            'stock_domain' => $domain,
            'location_scope' => $scope,
            'division_id' => $divisionId > 0 ? $divisionId : null,
            'location_type' => $locationType,
            'item_id' => (int)($input['item_id'] ?? 0) ?: null,
            'material_id' => (int)($input['material_id'] ?? 0) ?: null,
            'component_id' => (int)($input['component_id'] ?? 0) ?: null,
            'buy_uom_id' => (int)($input['buy_uom_id'] ?? 0) ?: null,
            'uom_id' => (int)($input['uom_id'] ?? $input['content_uom_id'] ?? 0) ?: null,
            'profile_key' => $this->nullableString($input['profile_key'] ?? null),
            'monthly_stock_id' => $stockId,
        ];
    }

    private function fetchStockRow(array $context, bool $forUpdate): array
    {
        $db = $this->ci->db;
        $domain = $context['stock_domain'];
        $table = $domain === 'COMPONENT'
            ? 'inv_component_monthly_stock'
            : ($context['location_scope'] === 'WAREHOUSE' ? 'inv_warehouse_monthly_stock' : 'inv_division_monthly_stock');

        if ($domain === 'COMPONENT') {
            $sql = 'SELECT s.id, s.month_key, s.location_type, s.division_id, s.component_id, s.uom_id, '
                . 's.closing_qty AS stock_qty, s.total_value AS stock_value, s.avg_cost AS stock_avg_cost, '
                . 'c.component_name AS inventory_name, u.code AS uom_code '
                . 'FROM ' . $table . ' s '
                . 'LEFT JOIN mst_component c ON c.id = s.component_id '
                . 'LEFT JOIN mst_uom u ON u.id = s.uom_id '
                . 'WHERE s.id = ? AND s.month_key = ? AND s.location_type = ? AND s.division_id <=> ? '
                . 'AND s.component_id <=> ? AND s.uom_id <=> ?'
                . ($forUpdate ? ' FOR UPDATE' : '');
            $query = $db->query($sql, [
                (int)$context['monthly_stock_id'], $context['month'], $context['location_type'], $context['division_id'],
                $context['component_id'], $context['uom_id'],
            ]);
            return $query ? ($query->row_array() ?: []) : [];
        }

        $scope = $context['location_scope'];
        $sql = 'SELECT s.id, s.month_key, s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_key, '
            . 's.closing_qty_content AS stock_qty, s.total_value AS stock_value, s.avg_cost_per_content AS stock_avg_cost, '
            . 'COALESCE(i.item_name, m.material_name, s.profile_name, \'-\') AS inventory_name, u.code AS uom_code '
            . 'FROM ' . $table . ' s '
            . 'LEFT JOIN mst_item i ON i.id = s.item_id '
            . 'LEFT JOIN mst_material m ON m.id = s.material_id '
            . 'LEFT JOIN mst_uom u ON u.id = s.content_uom_id '
            . 'WHERE s.id = ? AND s.month_key = ? '
            . 'AND s.item_id <=> ? AND s.material_id <=> ? AND s.buy_uom_id <=> ? AND s.content_uom_id <=> ? '
            . "AND COALESCE(s.profile_key, '') = ?";
        $bindings = [
            (int)$context['monthly_stock_id'], $context['month'], $context['item_id'], $context['material_id'],
            $context['buy_uom_id'], $context['uom_id'], $context['profile_key'] ?? '',
        ];
        if ($scope === 'DIVISION') {
            $sql .= ' AND s.division_id <=> ? AND s.destination_type = ?';
            $bindings[] = $context['division_id'];
            $bindings[] = $context['location_type'];
        }
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $query = $db->query($sql, $bindings);
        return $query ? ($query->row_array() ?: []) : [];
    }

    private function fetchOpenLots(array $context, bool $forUpdate): array
    {
        $db = $this->ci->db;
        if ($context['stock_domain'] === 'COMPONENT') {
            $sql = 'SELECT l.id, l.qty_balance, l.unit_cost, l.lot_no '
                . 'FROM inv_component_lot l '
                . 'WHERE UPPER(COALESCE(l.status, \'OPEN\')) = \'OPEN\' '
                . 'AND l.location_type = ? AND l.division_id <=> ? AND l.component_id <=> ? AND l.uom_id <=> ? '
                . 'AND COALESCE(l.qty_balance, 0) > 0.0001 '
                . 'ORDER BY l.receipt_date ASC, l.id ASC'
                . ($forUpdate ? ' FOR UPDATE' : '');
            $query = $db->query($sql, [$context['location_type'], $context['division_id'], $context['component_id'], $context['uom_id']]);
            return $query ? $query->result_array() : [];
        }

        $sql = 'SELECT l.id, l.qty_balance, l.unit_cost, l.lot_no '
            . 'FROM inv_material_fifo_lot l '
            . 'WHERE l.location_scope = ? AND UPPER(COALESCE(l.status, \'OPEN\')) = \'OPEN\' '
            . 'AND l.item_id <=> ? AND l.material_id <=> ? AND l.buy_uom_id <=> ? AND l.content_uom_id <=> ? '
            . "AND COALESCE(l.profile_key, '') = ? AND COALESCE(l.qty_balance, 0) > 0.0001";
        $bindings = [
            $context['location_scope'], $context['item_id'], $context['material_id'], $context['buy_uom_id'],
            $context['uom_id'], $context['profile_key'] ?? '',
        ];
        if ($context['location_scope'] === 'DIVISION') {
            $sql .= ' AND l.division_id <=> ? AND l.destination_type = ?';
            $bindings[] = $context['division_id'];
            $bindings[] = $context['location_type'];
        }
        $sql .= ' ORDER BY l.receipt_date ASC, l.id ASC' . ($forUpdate ? ' FOR UPDATE' : '');
        $query = $db->query($sql, $bindings);
        return $query ? $query->result_array() : [];
    }

    private function buildLotPlan(array $lots, float $targetValue, bool $keepExisting = false): array
    {
        $targetValue = round(max(0, $targetValue), 2);
        $totalQty = round(array_sum(array_map(static function (array $lot): float {
            return max(0, (float)($lot['qty_balance'] ?? 0));
        }, $lots)), 4);
        $oldTotal = round(array_sum(array_map(static function (array $lot): float {
            return max(0, (float)($lot['qty_balance'] ?? 0)) * (float)($lot['unit_cost'] ?? 0);
        }, $lots)), 2);
        $result = [];
        $accumulated = 0.0;
        $lastIndex = count($lots) - 1;

        foreach ($lots as $index => $lot) {
            $qty = round(max(0, (float)($lot['qty_balance'] ?? 0)), 4);
            $oldCost = round((float)($lot['unit_cost'] ?? 0), 6);
            $oldValue = round($qty * $oldCost, 2);
            $newCost = $oldCost;
            if (!$keepExisting && $qty > 0.0001) {
                if ($index === $lastIndex) {
                    $newCost = round(max(0, ($targetValue - $accumulated) / $qty), 6);
                } elseif ($oldTotal > 0.0001) {
                    $share = $oldValue / $oldTotal;
                    $newCost = round(max(0, ($targetValue * $share) / $qty), 6);
                } elseif ($totalQty > 0.0001) {
                    $newCost = round(max(0, $targetValue / $totalQty), 6);
                }
            }
            $newValue = round($qty * $newCost, 2);
            $accumulated = round($accumulated + $newValue, 6);
            $result[] = [
                'id' => (int)($lot['id'] ?? 0),
                'qty_balance' => $qty,
                'old_unit_cost' => $oldCost,
                'new_unit_cost' => $newCost,
                'old_total_value' => $oldValue,
                'new_total_value' => $newValue,
            ];
        }
        return $result;
    }

    private function updateMonthlyStockValue(array $context, int $stockId, float $qty, float $targetValue, int $revaluationId, string $now): void
    {
        $db = $this->ci->db;
        $avgCost = round($targetValue / max(0.0001, $qty), 6);
        $table = $context['stock_domain'] === 'COMPONENT'
            ? 'inv_component_monthly_stock'
            : ($context['location_scope'] === 'WAREHOUSE' ? 'inv_warehouse_monthly_stock' : 'inv_division_monthly_stock');
        $avgColumn = $context['stock_domain'] === 'COMPONENT' ? 'avg_cost' : 'avg_cost_per_content';
        $old = $db->select('notes')->where('id', $stockId)->get($table)->row_array() ?: [];
        $note = trim((string)($old['notes'] ?? ''));
        $append = 'Koreksi nilai #' . $revaluationId;
        $notes = substr($note !== '' ? ($note . ' | ' . $append) : $append, 0, 255);
        $db->where('id', $stockId)->update($table, [
            'total_value' => round($targetValue, 2),
            $avgColumn => $avgCost,
            'notes' => $notes,
            'updated_at' => $now,
        ]);
        if ((int)($db->error()['code'] ?? 0) !== 0) {
            throw new RuntimeException('Gagal memperbarui nilai saldo bulanan.');
        }
    }

    private function updateOpenLotUnitCost(array $context, int $lotId, float $unitCost, string $now): void
    {
        $table = $context['stock_domain'] === 'COMPONENT' ? 'inv_component_lot' : 'inv_material_fifo_lot';
        $updated = $this->ci->db
            ->where('id', $lotId)
            ->where("UPPER(COALESCE(status, 'OPEN')) = 'OPEN'", null, false)
            ->update($table, [
                'unit_cost' => round($unitCost, 6),
                'updated_at' => $now,
            ]);
        if (!$updated) {
            throw new RuntimeException('Lot aktif berubah sebelum koreksi nilai diposting. Muat ulang halaman lalu coba kembali.');
        }
    }

    /**
     * Availability is a derived cache. A refresh failure after the inventory
     * transaction commits must be visible to the caller, but must never turn
     * the committed value correction into a reported transaction failure.
     */
    private function refreshAvailabilityAfterCommit(array $context, string $eventSource, int $revaluationId, int $actorUserId): array
    {
        $domain = strtoupper(trim((string)($context['stock_domain'] ?? '')));
        $handler = 'handle_material_change';
        $identityType = 'material';
        $targetId = (int)($context['material_id'] ?? 0);
        if ($domain === 'COMPONENT') {
            $handler = 'handle_component_change';
            $identityType = 'component';
            $targetId = (int)($context['component_id'] ?? 0);
        } elseif ($domain === 'MATERIAL' && $targetId <= 0 && (int)($context['item_id'] ?? 0) > 0) {
            $handler = 'handle_item_change';
            $identityType = 'item';
            $targetId = (int)$context['item_id'];
        }
        $metadata = [
            'ok' => false,
            'status' => 'FAILED',
            'stock_domain' => $domain,
            'handler' => $handler,
            'target_id' => $targetId,
            'identity_type' => $identityType,
            'identity_id' => $targetId,
            'event_source' => $eventSource,
            'result' => null,
            'warning' => null,
        ];

        if (!in_array($domain, ['MATERIAL', 'COMPONENT'], true) || $targetId <= 0) {
            $metadata['status'] = 'SKIPPED';
            $metadata['warning'] = 'Koreksi nilai sudah tersimpan, tetapi refresh availability POS dilewati karena identitas stok tidak lengkap.';
            $this->logAvailabilityRefreshWarning($eventSource, $revaluationId, (string)$metadata['warning']);
            return $metadata;
        }

        try {
            $this->ci->load->library('PosAvailabilityRebuildService');
            $result = $this->ci->posavailabilityrebuildservice->{$handler}($targetId, [
                'trigger_context' => 'INVENTORY_VALUE_RECONCILIATION',
                'event_source' => $eventSource,
                'event_table' => 'inv_stock_value_reconciliation',
                'event_id' => $revaluationId,
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            ]);
            $metadata['result'] = $result;
            if (!($result['ok'] ?? false)) {
                $metadata['warning'] = 'Koreksi nilai sudah tersimpan, tetapi refresh availability POS gagal: '
                    . (string)($result['message'] ?? 'hasil refresh tidak berhasil.');
                $this->logAvailabilityRefreshWarning($eventSource, $revaluationId, (string)$metadata['warning']);
                return $metadata;
            }

            $metadata['ok'] = true;
            $metadata['status'] = 'SUCCESS';
            return $metadata;
        } catch (Throwable $e) {
            $metadata['warning'] = 'Koreksi nilai sudah tersimpan, tetapi refresh availability POS gagal: ' . $e->getMessage();
            $this->logAvailabilityRefreshWarning($eventSource, $revaluationId, (string)$metadata['warning']);
            return $metadata;
        }
    }

    private function logAvailabilityRefreshWarning(string $eventSource, int $revaluationId, string $warning): void
    {
        try {
            log_message(
                'error',
                'inventory value reconciliation availability refresh failed [' . $eventSource . ' #' . $revaluationId . ']: ' . $warning
            );
        } catch (Throwable $ignored) {
            // Logging is best-effort and must not alter an already committed correction.
        }
    }

    private function generateRevaluationNo(string $domain, string $month): string
    {
        $prefix = strtoupper($domain) === 'COMPONENT' ? 'CVR' : 'MVR';
        $day = date('Ymd');
        do {
            $candidate = $prefix . '-' . $day . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = (int)$this->ci->db->where('revaluation_no', $candidate)->count_all_results('inv_stock_value_reconciliation');
        } while ($exists > 0);
        return $candidate;
    }

    private function contextBlockMessage(bool $isActiveMonth, float $stockQty, float $lotQty, float $qtyGap, array $lots, float $valueGap): string
    {
        if (!$isActiveMonth) {
            return 'Koreksi nilai hanya boleh diposting pada bulan stok aktif. Temuan bulan lama dipakai sebagai audit/cut-off, bukan diedit kembali dari halaman ini.';
        }
        if (abs($qtyGap) > 0.0001) {
            return 'Jumlah stok dan lot belum sama. Selesaikan terlebih dahulu melalui Recon Stok Fisik; koreksi nilai tidak boleh mengubah jumlah.';
        }
        if ($stockQty <= 0.0001 || empty($lots)) {
            return 'Koreksi nilai membutuhkan stok dan lot OPEN yang masih bersaldo. Jika saldo sudah nol, telusuri movement/cut-off atau gunakan proses penutupan periode.';
        }
        if (abs($valueGap) <= 1) {
            return 'Nilai stok dan lot sudah cukup sama; tidak ada koreksi nilai yang perlu diposting.';
        }
        return '';
    }

    private function normalizeMonth(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('Y-m-01') : null;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : substr($value, 0, 255);
    }
}
