<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Reverse physical-count lot corrections inside the document's transaction. */
class InventoryStructuralLotReversal
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function reverse(string $domain, string $sourceTable, int $sourceId, string $date, ?int $actorUserId = null): array
    {
        $db = $this->ci->db;
        $tables = ['MATERIAL' => ['inv_material_fifo_lot', 'qty_in', 'qty_out'],
            'COMPONENT' => ['inv_component_lot', 'qty_in_total', 'qty_out_total']];
        if (!isset($tables[$domain]) || $sourceId <= 0 || $sourceTable !== ($domain === 'MATERIAL' ? 'inv_stock_adjustment' : 'inv_component_adjustment')) {
            return ['ok' => false, 'message' => 'Sumber pembatalan koreksi lot tidak valid.'];
        }
        if (!$db->trans_active()) {
            return ['ok' => false, 'message' => 'Pembatalan koreksi lot wajib dalam transaksi dokumen.'];
        }
        $this->ci->load->library('InventoryPeriodGuard');
        $period = $this->ci->inventoryperiodguard->lockActivePeriodsForWrite([['stock_domain' => $domain, 'event_date' => $date]]);
        if (empty($period['ok'])) { return $period; }
        $this->ci->load->library('InventoryCutoffAudit');
        if (!$this->ci->inventorycutoffaudit->isReady()) {
            return ['ok' => false, 'message' => 'Audit koreksi lot belum tersedia; VOID tidak dapat diverifikasi.'];
        }
        $query = $db->query('SELECT * FROM inv_stock_cutoff_event WHERE stock_domain = ? AND source_table = ? AND source_id = ? ORDER BY id DESC FOR UPDATE', [$domain, $sourceTable, $sourceId]);
        if (!$query) { return ['ok' => false, 'message' => 'Gagal membaca koreksi lot asal.']; }
        [$lotTable, $inColumn, $outColumn] = $tables[$domain];
        $result = ['returned_lots' => [], 'removed_lots' => [], 'reversed_count' => 0];
        foreach ($query->result_array() as $event) {
            $reversed = $db->query("SELECT id FROM inv_stock_cutoff_event WHERE stock_domain = ? AND source_table = 'inv_stock_cutoff_event' AND source_id = ? LIMIT 1", [$domain, (int)$event['id']]);
            if (!$reversed) { return ['ok' => false, 'message' => 'Gagal memeriksa pembalikan koreksi lot.']; }
            if ($reversed->row_array()) { continue; }
            $lotQuery = $db->query('SELECT * FROM ' . $lotTable . ' WHERE id = ? FOR UPDATE', [(int)$event['lot_id']]);
            $lot = $lotQuery ? $lotQuery->row_array() : null;
            if (!$lot || !$this->sameIdentity($domain, $event, $lot) || ($lot['status'] ?? '') === 'VOID') {
                return ['ok' => false, 'message' => 'Lot koreksi asal hilang, berubah identitas, atau sudah VOID. Gunakan repair ter-audit.'];
            }
            $qty = round((float)$event['qty'], 4);
            $balance = round((float)$lot['qty_balance'], 4);
            $qtyIn = round((float)$lot[$inColumn], 4);
            $qtyOut = round((float)$lot[$outColumn], 4);
            if ($qty <= 0 || abs($qtyIn - $qtyOut - $balance) > 0.0001) {
                return ['ok' => false, 'message' => 'Struktur qty lot tidak konsisten; VOID dibatalkan tanpa perubahan.'];
            }
            if ($event['direction'] === 'OUT') {
                if ($qtyOut + 0.0001 < $qty) {
                    return ['ok' => false, 'message' => 'Pengurangan lot asal tidak lagi dapat dibalik.'];
                }
                $newBalance = round($balance + $qty, 4);
                $cost = round($qty * (float)$event['unit_cost'], 2);
                $update = [$outColumn => round($qtyOut - $qty, 4), 'qty_balance' => $newBalance,
                    'unit_cost' => round(($balance * (float)$lot['unit_cost'] + $cost) / $newBalance, 6), 'status' => 'OPEN'];
                $bucket = 'returned_lots';
                $direction = 'IN';
            } elseif ($event['direction'] === 'IN') {
                // A correction-created lot cannot be removed after consumption,
                // rollover, or merging with another receipt.
                if (abs($qtyIn - $qty) > 0.0001 || abs($balance - $qty) > 0.0001 || abs($qtyOut) > 0.0001
                    || ($lot['source_table'] ?? '') !== $sourceTable || (int)$lot['source_id'] !== $sourceId) {
                    return ['ok' => false, 'message' => 'Lot koreksi sudah dipakai atau berubah; batalkan transaksi pemakai terlebih dahulu.'];
                }
                $cost = round($balance * (float)$lot['unit_cost'], 2);
                $update = ['qty_balance' => 0, 'status' => $domain === 'COMPONENT' ? 'VOID' : 'CLOSED'];
                if ($domain === 'MATERIAL') { $update[$inColumn] = 0; }
                $bucket = 'removed_lots';
                $direction = 'OUT';
            } else {
                return ['ok' => false, 'message' => 'Arah koreksi lot asal tidak valid.'];
            }
            $update['updated_at'] = date('Y-m-d H:i:s');
            if (!$db->where('id', (int)$lot['id'])->update($lotTable, $update)) {
                return ['ok' => false, 'message' => 'Gagal membalik koreksi lot.'];
            }
            $audit = $this->ci->inventorycutoffaudit->record(array_replace($event, [
                'event_date' => $date, 'direction' => $direction,
                'unit_cost' => round($cost / $qty, 6), 'total_value' => $cost,
                'source_table' => 'inv_stock_cutoff_event', 'source_id' => (int)$event['id'],
                'source_line_id' => null, 'movement_table' => 'inv_stock_cutoff_event', 'movement_id' => (int)$event['id'],
                'created_by' => $actorUserId,
                'notes' => 'VOID ' . $sourceTable . ' #' . $sourceId . ': pembalik koreksi lot #' . $event['id'],
            ]));
            if (empty($audit['ok'])) { return $audit; }
            $result[$bucket][] = ['lot_id' => (int)$lot['id'], 'component_id' => (int)($event['component_id'] ?? 0),
                'uom_id' => (int)$event['content_uom_id'], 'location_type' => $event['location_scope'],
                'division_id' => $event['division_id'], 'source_line_id' => $event['source_line_id'],
                'qty_rolled' => $qty, 'total_cost' => $cost];
            $result['reversed_count']++;
        }
        return ['ok' => $db->trans_status() !== false, 'data' => $result];
    }

    private function sameIdentity(string $domain, array $event, array $lot): bool
    {
        $fields = $domain === 'MATERIAL'
            ? ['location_scope' => 'location_scope', 'division_id' => 'division_id', 'destination_type' => 'destination_type',
                'material_id' => 'material_id', 'item_id' => 'item_id', 'content_uom_id' => 'content_uom_id', 'profile_key' => 'profile_key']
            : ['location_scope' => 'location_type', 'division_id' => 'division_id', 'component_id' => 'component_id', 'content_uom_id' => 'uom_id'];
        foreach ($fields as $from => $to) {
            if ((string)($event[$from] ?? '') !== (string)($lot[$to] ?? '')) { return false; }
        }
        return true;
    }
}
