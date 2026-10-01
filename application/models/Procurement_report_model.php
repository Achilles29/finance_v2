<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Report_workspace.php';

class Procurement_report_model extends CI_Model
{
    private function rows(string $sql, array $binds = []): array
    {
        $q = $this->db->query($sql, $binds);
        if (!$q) { throw new RuntimeException('Query laporan pengadaan gagal.'); }
        return $q->result_array();
    }

    public function divisions(): array
    {
        return $this->rows('SELECT id, name FROM mst_operational_division ORDER BY name');
    }

    public function scopedDivision(int $orgDivisionId): int
    {
        // Role scopes reference org_division, not the operational master ID.
        $rows = $this->rows('SELECT d.id FROM org_division o JOIN mst_operational_division d ON d.code=o.division_code WHERE o.id=?', [$orgDivisionId]);
        if (count($rows) !== 1) { throw new RuntimeException('Divisi operasional untuk hak akses belum dapat dipastikan.'); }
        return (int)$rows[0]['id'];
    }

    private function srSql(bool $request): string
    {
        if ($request) {
            // Aggregate fulfillments before joining requests: partial deliveries
            // must not multiply requested quantities or count the same line twice.
            return "SELECT CONCAT('REQ-',l.id) row_key, 'SR' source, s.id document_id, s.sr_no document_no,
                s.sr_no reference_no, s.request_date event_date, s.status, s.request_division_id division_id,
                s.destination_type destination, l.item_id, l.material_id, l.profile_key, l.profile_name item_name,
                l.profile_brand brand, l.profile_description description, l.buy_uom_id, l.content_uom_id,
                l.profile_buy_uom_code buy_uom, l.profile_content_uom_code content_uom,
                l.qty_buy_requested qty_buy, l.qty_content_requested qty_content,
                COALESCE(x.qty,0) fulfilled_qty, CASE WHEN l.line_status='CANCELLED' THEN 0 ELSE GREATEST(0,l.qty_content_requested-COALESCE(x.qty,0)) END pending_qty,
                COALESCE(x.value,0) value, l.usage_purpose, '' delivery_no, 'IDR' currency,
                CASE WHEN COALESCE(x.qty,0)>0 THEN x.value/x.qty ELSE 0 END unit_cost, l.line_status
                FROM pur_store_request s JOIN pur_store_request_line l ON l.store_request_id=s.id
                LEFT JOIN (SELECT fl.store_request_line_id, SUM(fl.qty_content_posted) qty,
                    SUM(ROUND(fl.qty_content_posted*fl.unit_cost_snapshot,2)) value
                    FROM pur_store_request_fulfillment_line fl JOIN pur_store_request_fulfillment f ON f.id=fl.fulfillment_id
                    WHERE f.status='POSTED' GROUP BY fl.store_request_line_id) x ON x.store_request_line_id=l.id";
        }
        return "SELECT CONCAT('SR-',l.id) row_key, 'SR' source, s.id document_id, s.sr_no document_no,
            s.sr_no reference_no, f.fulfillment_date event_date, s.status, s.request_division_id division_id,
            s.destination_type destination, l.item_id, l.material_id, l.profile_key, l.profile_name item_name,
            l.profile_brand brand, l.profile_description description, l.buy_uom_id, l.content_uom_id,
            l.profile_buy_uom_code buy_uom, l.profile_content_uom_code content_uom,
            l.qty_buy_posted qty_buy, l.qty_content_posted qty_content, l.qty_content_posted fulfilled_qty,
            0 pending_qty, ROUND(l.qty_content_posted*l.unit_cost_snapshot,2) value,
            l.usage_purpose, f.fulfillment_no delivery_no, 'IDR' currency, l.unit_cost_snapshot unit_cost, '' line_status
            FROM pur_store_request_fulfillment f JOIN pur_store_request_fulfillment_line l ON l.fulfillment_id=f.id
            JOIN pur_store_request s ON s.id=f.store_request_id
            WHERE f.status='POSTED' AND s.status NOT IN ('VOID','REJECTED')";
    }

    private function purchaseSql(): string
    {
        // Read posted inventory receipts, never PO/payment totals. One movement
        // is one row, including partial receipts; warehouse purchases are excluded.
        return "SELECT CONCAT('PO-',m.id) row_key, 'PURCHASE' source, p.id document_id, p.po_no document_no,
            p.po_no reference_no, m.movement_date event_date, p.status, m.division_id,
            m.destination_type destination, m.item_id, m.material_id, m.profile_key, m.profile_name item_name,
            m.profile_brand brand, m.profile_description description, m.buy_uom_id, m.content_uom_id,
            m.profile_buy_uom_code buy_uom, m.profile_content_uom_code content_uom,
            m.qty_buy_delta qty_buy, m.qty_content_delta qty_content, m.qty_content_delta fulfilled_qty,
            0 pending_qty, ROUND(m.qty_content_delta*m.unit_cost,2) value,
            rl.usage_purpose, r.receipt_no delivery_no, p.currency_code currency, m.unit_cost, '' line_status
            FROM inv_stock_movement_log m
            JOIN pur_purchase_receipt r ON r.id=m.ref_id AND m.ref_table='pur_purchase_receipt'
            JOIN pur_purchase_receipt_line rl ON rl.id=m.receipt_line_id AND rl.purchase_receipt_id=r.id
            JOIN pur_purchase_order p ON p.id=r.purchase_order_id
            WHERE m.movement_scope='DIVISION' AND m.movement_type='PURCHASE_IN' AND m.qty_content_delta>0
              AND m.reversal_of_movement_id IS NULL AND r.status='POSTED' AND p.status NOT IN ('VOID','REJECTED')
              AND NOT EXISTS (SELECT 1 FROM inv_stock_movement_log rev WHERE rev.reversal_of_movement_id=m.id)";
    }

    public function report(string $kind, array $f): array
    {
        if (!in_array($kind, ['sr', 'materials'], true)) { throw new InvalidArgumentException('Laporan tidak tersedia.'); }
        $request = $kind === 'sr' && $f['basis'] === 'request';
        $base = $this->srSql($request);
        if ($kind === 'materials') { $base .= ' UNION ALL ' . $this->purchaseSql(); }
        $sql = "SELECT x.*,COALESCE(d.name,'Tanpa divisi') division_name, COALESCE(c.name,'Tanpa kategori') category_name,
                COALESCE(c.id,0) category_id, COALESCE(NULLIF(x.item_name,''),i.item_name,'Tanpa nama') display_name,
                COALESCE(NULLIF(x.buy_uom,''),bu.code,'-') buy_unit, COALESCE(NULLIF(x.content_uom,''),cu.code,'-') content_unit
            FROM ($base) x LEFT JOIN mst_operational_division d ON d.id=x.division_id
            LEFT JOIN mst_item i ON i.id=x.item_id LEFT JOIN mst_item_category c ON c.id=i.item_category_id
            LEFT JOIN mst_uom bu ON bu.id=x.buy_uom_id LEFT JOIN mst_uom cu ON cu.id=x.content_uom_id
            WHERE ((x.event_date BETWEEN ? AND ?) OR (x.event_date BETWEEN ? AND ?))";
        $binds = Report_workspace::window($f);
        if ($kind === 'materials') { $sql .= " AND x.material_id IS NOT NULL AND x.usage_purpose='BAHAN_BAKU'"; }
        if ($request && $f['status'] === '') { $sql .= " AND x.status NOT IN ('VOID','REJECTED')"; }
        foreach (['division_id' => 'x.division_id', 'destination' => 'x.destination', 'status' => 'x.status', 'source' => 'x.source', 'profile' => 'x.profile_key'] as $key => $column) {
            if ($f[$key] !== '' && $f[$key] !== 0) { $sql .= " AND $column=?"; $binds[] = $f[$key]; }
        }
        if ($f['category'] !== '') { $sql .= ' AND COALESCE(c.id,0)=?'; $binds[] = (int)$f['category']; }
        if ($f['q'] !== '') {
            $sql .= " AND (x.document_no LIKE ? ESCAPE '!' OR x.item_name LIKE ? ESCAPE '!' OR x.brand LIKE ? ESCAPE '!' OR x.profile_key LIKE ? ESCAPE '!')";
            $q = '%' . $this->db->escape_like_str($f['q']) . '%';
            array_push($binds, $q, $q, $q, $q);
        }
        $rows = $this->rows($sql . ' ORDER BY x.event_date DESC,x.source,x.document_id DESC,x.row_key LIMIT 50001', $binds);
        if (count($rows) > 50000) { throw new InvalidArgumentException('Data melebihi 50.000 baris. Persempit filter divisi atau pencarian; laporan tidak dipotong diam-diam.'); }
        $selected = []; $monthly = []; $daily = []; $groups = []; $categories = []; $matrix = []; $documents = [];
        $totals = ['value' => 0, 'purchase' => 0, 'sr' => 0, 'documents' => 0, 'lines' => 0, 'zero_cost' => 0, 'foreign_currency' => 0];
        for ($i=5; $i>=0; $i--) { $m=date('Y-m',strtotime($f['from']." -$i months")); $monthly[$m]=['label'=>$m,'value'=>0,'purchase'=>0,'sr'=>0]; }
        foreach ($rows as $r) {
            $m = substr($r['event_date'],0,7); $value = Report_workspace::cents($r['value']);
            $source = $r['source'] === 'PURCHASE' ? 'purchase' : 'sr';
            if (!isset($monthly[$m])) { $monthly[$m]=['label'=>$m,'value'=>0,'purchase'=>0,'sr'=>0]; }
            $monthly[$m]['value'] += $value; $monthly[$m][$source] += $value;
            if ($m !== $f['month'] || ($f['day'] !== '' && $r['event_date'] !== $f['day'])) { continue; }
            $r['value_cents'] = $value; $selected[] = $r;
            $totals['value'] += $value; $totals[$source] += $value; $totals['lines']++;
            $totals['zero_cost'] += (float)$r['qty_content'] > 0 && (float)$r['unit_cost'] <= 0 ? 1 : 0;
            $totals['foreign_currency'] += $r['currency'] !== 'IDR' ? 1 : 0;
            $documents[$r['source'].':'.$r['document_id']] = true;
            $date = $r['event_date'];
            if (!isset($daily[$date])) { $daily[$date]=['label'=>$date,'value'=>0,'purchase'=>0,'sr'=>0]; }
            $daily[$date]['value'] += $value; $daily[$date][$source] += $value;
            $g = (int)$r['division_id'];
            if (!isset($groups[$g])) { $groups[$g]=['id'=>$g,'label'=>$r['division_name'],'value'=>0,'purchase'=>0,'sr'=>0]; }
            $groups[$g]['value'] += $value; $groups[$g][$source] += $value;
            $c = (int)$r['category_id'];
            if (!isset($categories[$c])) { $categories[$c]=['id'=>$c,'label'=>$r['category_name'],'value'=>0,'lines'=>0]; }
            $categories[$c]['value'] += $value; $categories[$c]['lines']++;
            $key = $g . '|' . $r['destination'];
            if (!isset($matrix[$key])) { $matrix[$key]=['label'=>$r['division_name'].' / '.$r['destination'],'division_id'=>$g,'destination'=>$r['destination'],'cells'=>[],'value'=>0]; }
            $matrix[$key]['cells'][$date] = ($matrix[$key]['cells'][$date] ?? 0) + $value;
            $matrix[$key]['value'] += $value;
        }
        $totals['documents'] = count($documents);
        ksort($daily); ksort($monthly);
        uasort($groups, static fn($a,$b) => $b['value'] <=> $a['value']);
        uasort($categories, static fn($a,$b) => $b['value'] <=> $a['value']);
        $previous = $monthly[$f['compare']]['value'] ?? 0;
        return ['totals'=>$totals,'previous'=>$previous,'monthly'=>array_values($monthly),'daily'=>array_values($daily),
            'groups'=>array_values($groups),'categories'=>array_values($categories),'matrix'=>array_values($matrix),
            'rows'=>$selected,'pager'=>Report_workspace::page($selected,$f),'request_basis'=>$request];
    }

    /** Same receipt/fulfillment valuation as the division-material report. */
    public function materialFlowRows(string $from, string $to): array
    {
        $sql='SELECT x.* FROM ('.$this->srSql(false).' UNION ALL '.$this->purchaseSql().") x
            WHERE x.event_date BETWEEN ? AND ? AND x.material_id IS NOT NULL AND x.usage_purpose='BAHAN_BAKU'
            ORDER BY x.event_date,x.row_key LIMIT 50001";
        $rows=$this->rows($sql,[$from,$to]);
        if(count($rows)>50000){throw new InvalidArgumentException('Penerimaan bahan melebihi 50.000 baris. Persempit rentang bulan.');}
        return $rows;
    }
}
