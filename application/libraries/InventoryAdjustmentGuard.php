<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class InventoryAdjustmentGuard
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function lock(string $domain, int $id, string $status, string $date): array
    {
        $db = $this->ci->db;
        if (!$db->trans_active() || !in_array($domain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Penguncian adjustment membutuhkan transaksi aktif.'];
        }
        // Use the same period-before-document lock order for post and VOID.
        $this->ci->load->library('InventoryPeriodGuard');
        $period = $this->ci->inventoryperiodguard->lockActivePeriodsForWrite([['stock_domain' => $domain, 'event_date' => $date]]);
        if (empty($period['ok'])) { return $period; }
        $table = $domain === 'MATERIAL' ? 'inv_stock_adjustment' : 'inv_component_adjustment';
        $query = $db->query('SELECT * FROM ' . $table . ' WHERE id = ? FOR UPDATE', [$id]);
        $header = $query ? $query->row_array() : null;
        if (!$header || $header['status'] !== $status || $header['adjustment_date'] !== $date) {
            return ['ok' => false, 'message' => 'Adjustment sudah diproses atau berubah. Muat ulang; tidak ada posting ganda.'];
        }
        return ['ok' => true, 'header' => $header];
    }

    public function checkMaterial(array $header, array $lines): array
    {
        $db = $this->ci->db;
        $scope = strtoupper((string)$header['stock_scope']);
        $table = $scope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
        $seen = [];
        foreach ($lines as $line) {
            $params = [$line['item_id'] ?? null, $line['material_id'] ?? null, $line['buy_uom_id'] ?? null,
                $line['content_uom_id'] ?? null, $line['profile_key'] ?? null];
            $identity = 'item_id <=> ? AND material_id <=> ? AND buy_uom_id <=> ? AND content_uom_id <=> ? AND profile_key <=> ?';
            if ($scope === 'DIVISION') {
                $identity .= ' AND division_id = ? AND destination_type = ?';
                $params[] = (int)$header['division_id'];
                $params[] = $header['destination_type'];
            }
            $key = json_encode($params);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $stock = $db->query('SELECT COUNT(*) n, COALESCE(SUM(closing_qty_content),0) qty FROM ' . $table . ' WHERE ' . $identity . ' AND month_key = ?', array_merge($params, [substr($header['adjustment_date'], 0, 7) . '-01']));
            $lots = $db->query("SELECT COALESCE(SUM(qty_balance),0) qty FROM inv_material_fifo_lot WHERE " . $identity . " AND location_scope = ? AND status = 'OPEN'", array_merge($params, [$scope]));
            if (!$stock || !$lots) { return ['ok' => false, 'message' => 'Pemeriksaan saldo/FIFO gagal; adjustment dibatalkan.']; }
            $s = $stock->row_array(); $l = $lots->row_array();
            // VOID of the first receipt may remove an empty monthly projection.
            // It is consistent only when no physical lot balance remains.
            if ((int)$s['n'] > 1 || abs(max(0, (float)$s['qty']) - (float)$l['qty']) > 0.0001) {
                return ['ok' => false, 'message' => 'Saldo dan FIFO profil ' . ($line['profile_name'] ?? $line['profile_key'] ?? '')
                    . ' tidak sinkron setelah adjustment. Seluruh perubahan dibatalkan; periksa Daily Recon.',
                    'data' => ['monthly_rows' => (int)$s['n'], 'ledger_qty' => (float)$s['qty'], 'fifo_qty' => (float)$l['qty']]];
            }
        }
        return ['ok' => true];
    }

    public function checkComponent(array $header, array $lines): array
    {
        $db = $this->ci->db;
        $seen = [];
        foreach ($lines as $line) {
            $params = [$header['location_type'], $header['division_id'] ?? null, (int)$line['component_id'], (int)$line['uom_id']];
            $key = json_encode($params);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $identity = 'location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ?';
            $stock = $db->query('SELECT COUNT(*) n, COALESCE(SUM(closing_qty),0) qty, COALESCE(SUM(total_value),0) value FROM inv_component_monthly_stock WHERE ' . $identity . ' AND month_key = ?', array_merge($params, [substr($header['adjustment_date'], 0, 7) . '-01']));
            $lots = $db->query("SELECT COALESCE(SUM(qty_balance),0) qty, COALESCE(SUM(ROUND(qty_balance * unit_cost,2)),0) value FROM inv_component_lot WHERE " . $identity . " AND status = 'OPEN'", $params);
            if (!$stock || !$lots) { return ['ok' => false, 'message' => 'Pemeriksaan saldo/FIFO component gagal; adjustment dibatalkan.']; }
            $s = $stock->row_array(); $l = $lots->row_array();
            if ((int)$s['n'] !== 1 || abs(max(0, (float)$s['qty']) - (float)$l['qty']) > 0.0001) {
                return ['ok' => false, 'message' => 'Qty saldo dan lot component #' . $line['component_id'] . ' belum sinkron. Seluruh adjustment dibatalkan; periksa Daily Recon.'];
            }
            if (abs((float)$s['value'] - (float)$l['value']) > 0.05) {
                return ['ok' => false, 'message' => 'Nilai saldo dan FIFO component #' . $line['component_id'] . ' berbeda. Seluruh adjustment dibatalkan; gunakan rekonsiliasi nilai ter-audit, bukan repair qty.'];
            }
        }
        return ['ok' => true];
    }
}
