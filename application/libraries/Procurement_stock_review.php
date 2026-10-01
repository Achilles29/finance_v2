<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only stock evidence; no seed, rebuild, reservation or inventory writes. */
class Procurement_stock_review
{
    private $db;
    private string $secret;
    private array $cache = [];
    public function __construct($db, string $secret = '') { $this->db = $db; $this->secret = $secret; }

    private function rows(string $sql, array $params = []): array
    {
        $query = $this->db->query($sql, $params);
        if (!$query) throw new RuntimeException('STOCK_READ_FAILED');
        return $query->result_array();
    }

    public function ready(): bool
    {
        if (!$this->db->table_exists('pur_division_stock_review')) return false;
        foreach (['request_id','reviewed_by','reviewed_at','source_ip','confirmed_with','reason','snapshot_hash','snapshot_json'] as $field) {
            if (!$this->db->field_exists($field, 'pur_division_stock_review')) return false;
        }
        return true;
    }

    public function snapshot(array $context, array $lines, bool $fresh = true): array
    {
        if ($fresh) $this->cache = []; // A later save must read again, not reuse the preview.
        $result = ['context'=>$context, 'lines_hash'=>hash('sha256', json_encode($lines, JSON_THROW_ON_ERROR)), 'rows'=>[]];
        foreach ($lines as $index=>$line) {
            // Material-linked items stay visible even if their usage is operational.
            $material = null;
            $mappingError = null;
            try { $material = $this->material($line); } catch (Throwable $e) { $mappingError = $e->getMessage(); }
            if (strtoupper((string)($line['usage_purpose'] ?? 'BAHAN_BAKU')) !== 'BAHAN_BAKU'
                && (int)($line['material_id'] ?? 0)<=0 && $mappingError === 'MATERIAL_UNMAPPED') continue;
            $row = ['line'=>$index+1, 'name'=>(string)($line['profile_name'] ?? 'Bahan baku'),
                'requested'=>round((float)($line['qty_content_requested'] ?? 0),4),
                'uom_id'=>(int)($line['content_uom_id'] ?? 0), 'uom'=>'?', 'material_id'=>null];
            try {
                $uom = $this->rows('SELECT code FROM mst_uom WHERE id=?', [$row['uom_id']]);
                if (!$uom) throw new RuntimeException('UNIT_UNKNOWN');
                $row['uom'] = (string)$uom[0]['code'];
                if (!$material) $material = $this->material($line);
                $row['material_id'] = (int)$material['id'];
                $row['division'] = $this->stock('DIVISION', (int)$material['id'], $row['uom_id'], $context);
                $row['warehouse'] = $this->stock('WAREHOUSE', (int)$material['id'], $row['uom_id'], $context);
            } catch (Throwable $e) {
                $row['division'] = $this->unknown('Material/satuan belum terhubung atau layanan stok belum dapat dibaca.');
                $row['warehouse'] = $this->unknown('Material/satuan belum terhubung atau layanan stok belum dapat dibaca.');
            }
            $row['needs_confirmation'] = $row['division']['state'] !== 'KNOWN'
                || abs((float)$row['division']['qty']) > 0.00001 || $row['warehouse']['state'] !== 'KNOWN'
                || (float)$row['warehouse']['qty'] < -0.00001;
            $target = ($context['destination_type'] ?? '') === 'WAREHOUSE' ? $row['warehouse'] : $row['division'];
            if (($context['destination_type'] ?? '') === 'WAREHOUSE') {
                $row['needs_confirmation'] = $target['state'] !== 'KNOWN' || abs((float)$target['qty']) > 0.00001;
            }
            $row['warning'] = $target['state'] !== 'KNOWN' ? 'Saldo belum pasti; periksa sebelum melanjutkan.'
                : ((float)$target['qty'] > 0.00001 ? ($row['requested'] > 0 && (float)$target['qty'] >= $row['requested']
                    ? 'Stok tujuan masih mencukupi jumlah pengajuan. Konfirmasi kebutuhan tambahan.'
                    : 'Stok tujuan masih tersisa. Konfirmasi kebutuhan tambahan.') : '');
            $result['rows'][] = $row;
        }
        return $result;
    }

    private function unknown(string $message): array
    {
        return ['state'=>'UNKNOWN', 'qty'=>null, 'message'=>$message, 'latest_at'=>null];
    }

    private function material(array $line): array
    {
        $item = (int)($line['item_id'] ?? 0); $material = (int)($line['material_id'] ?? 0);
        if ($item > 0) {
            $items = $this->rows('SELECT material_id FROM mst_item WHERE id=?', [$item]);
            if (!$items) throw new RuntimeException('ITEM_UNKNOWN');
            if ((int)$items[0]['material_id'] <= 0) throw new RuntimeException('MATERIAL_UNMAPPED');
            $mapped = (int)$items[0]['material_id'];
            if ($material > 0 && $mapped !== $material) throw new RuntimeException('MATERIAL_CONFLICT');
            $material = $mapped;
        }
        if ($material <= 0) throw new RuntimeException('MATERIAL_UNMAPPED');
        $key = 'material:'.$material;
        if (!isset($this->cache[$key])) {
            $rows = $this->rows('SELECT id,material_name,content_uom_id FROM mst_material WHERE id=?', [$material]);
            if (!$rows) throw new RuntimeException('MATERIAL_UNKNOWN');
            $this->cache[$key] = $rows[0];
        }
        return $this->cache[$key];
    }

    private function factor(int $from, int $to): float
    {
        if ($from <= 0 || $to <= 0) throw new RuntimeException('UNIT_UNKNOWN');
        if ($from === $to) return 1.;
        $key = 'unit:'.$from.':'.$to;
        if (isset($this->cache[$key])) return $this->cache[$key];
        $rows = $this->rows('SELECT from_uom_id,to_uom_id,factor FROM mst_uom_conversion WHERE is_active=1 AND ((from_uom_id=? AND to_uom_id=?) OR (from_uom_id=? AND to_uom_id=?))', [$from,$to,$to,$from]);
        $factor = null;
        foreach ($rows as $row) {
            $value = (float)$row['factor'];
            if (!is_finite($value) || $value <= 0) throw new RuntimeException('UNIT_CONVERSION_INVALID');
            $value = (int)$row['from_uom_id'] === $from ? $value : 1/$value;
            if ($factor !== null && abs($factor-$value) > max(0.000001,abs($value)*0.000001)) throw new RuntimeException('UNIT_CONVERSION_CONFLICT');
            $factor = $value;
        }
        if ($factor === null) throw new RuntimeException('UNIT_CONVERSION_MISSING');
        return $this->cache[$key] = $factor;
    }

    private function stock(string $scope, int $material, int $uom, array $context): array
    {
        $key = implode(':', [$scope,$material,$uom,$context['division_id'],$context['destination_type']]);
        if (isset($this->cache[$key])) return $this->cache[$key];
        try {
            $table = $scope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
            $scopeSql = $scope === 'DIVISION' ? ' AND s.division_id=? AND s.destination_type=?' : '';
            $latestScope = $scope === 'DIVISION' ? ' AND n.division_id=s.division_id AND n.destination_type=s.destination_type' : '';
            $args = [$material,$material,date('Y-m-01')];
            if ($scope === 'DIVISION') { $args[] = (int)$context['division_id']; $args[] = (string)$context['destination_type']; }
            $args[] = date('Y-m-01');
            // Same canonical profile/latest-month basis as the inventory UI. Do not sum monthly snapshots.
            $rows = $this->rows("SELECT s.id,s.identity_key,s.profile_key,s.item_id,s.material_id,s.buy_uom_id,s.content_uom_id,
                s.closing_qty_content,s.month_key,s.updated_at,s.last_movement_at,i.material_id AS item_material_id
                FROM {$table} s LEFT JOIN mst_item i ON i.id=s.item_id
                WHERE (s.material_id=? OR i.material_id=?) AND s.month_key<=? {$scopeSql}
                AND (s.profile_key IS NULL OR s.identity_key=s.profile_key)
                AND NOT EXISTS (SELECT 1 FROM {$table} n WHERE n.identity_key=s.identity_key {$latestScope} AND n.month_key>s.month_key AND n.month_key<=?)
                ORDER BY s.id ASC LIMIT 1001", $args);
            if (!$rows) return $this->cache[$key] = $this->unknown('Belum ada catatan saldo; bukan berarti stok nol.');
            if (count($rows) > 1000) throw new RuntimeException('STOCK_TOO_MANY_IDENTITIES');
            $best = [];
            foreach ($rows as $row) {
                if ((int)$row['material_id'] > 0 && (int)$row['item_material_id'] > 0 && (int)$row['material_id'] !== (int)$row['item_material_id']) throw new RuntimeException('STOCK_MAPPING_CONFLICT');
                $identity = implode('|', [$row['item_id'],$row['material_id'],$row['buy_uom_id'],$row['content_uom_id'],$row['profile_key'] ?? $row['identity_key']]);
                $old = $best[$identity] ?? null;
                if (!$old || [(string)$row['updated_at'],(int)$row['id']] > [(string)$old['updated_at'],(int)$old['id']]) $best[$identity] = $row;
            }
            $qty = 0.; $latest = ''; $hasNegative = false; $evidence = [];
            foreach ($best as $row) {
                $value = (float)$row['closing_qty_content'];
                if (!is_finite($value) || abs($value)>1e12) throw new RuntimeException('STOCK_VALUE_INVALID');
                if ($value < -0.00001) $hasNegative = true;
                $qty += $value * $this->factor((int)$row['content_uom_id'],$uom);
                $latest = max($latest,(string)($row['updated_at'] ?: $row['last_movement_at'] ?: $row['month_key']));
                $evidence[] = [$row['id'],$row['closing_qty_content'],$row['content_uom_id'],$row['updated_at'],$row['month_key']];
            }
            if (!is_finite($qty) || abs($qty)>1e12) throw new RuntimeException('STOCK_TOTAL_INVALID');
            return $this->cache[$key] = ['state'=>$hasNegative ? 'CHECK' : 'KNOWN','qty'=>round($qty,4),
                'message'=>$hasNegative ? 'Ada saldo profil negatif; periksa dengan divisi.' : 'Saldo sistem, bukan hasil hitung fisik atau reservasi.',
                'latest_at'=>$latest,'evidence_hash'=>hash('sha256',json_encode($evidence,JSON_THROW_ON_ERROR))];
        } catch (Throwable $e) {
            return $this->cache[$key] = $this->unknown('Saldo belum dapat dipastikan. Periksa pemetaan material, konversi satuan atau layanan stok.');
        }
    }

    public function preview(array $snapshot, int $now): array
    {
        $needs = false;
        foreach ($snapshot['rows'] as $row) $needs = $needs || $row['needs_confirmation'];
        $token = strlen($this->secret) >= 32 ? $now.'.'.$this->sign($snapshot,$now) : '';
        return ['rows'=>$snapshot['rows'],'checked_at'=>date('Y-m-d H:i:s',$now),'needs_confirmation'=>$needs,
            'token'=>$token,'ready'=>$this->ready(),'has_materials'=>!empty($snapshot['rows'])];
    }

    private function sign(array $snapshot, int $issued): string
    {
        return hash_hmac('sha256',json_encode([$snapshot,$issued],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$this->secret);
    }

    public function validate(array $snapshot, array $confirmation, int $now): array
    {
        if (!$snapshot['rows']) return []; // Operational/non-material request behavior is unchanged.
        if (!$this->ready()) throw new InvalidArgumentException('Pencatatan konfirmasi stok belum aktif. Admin perlu memasang SQL 2026-09-16a sebelum verifikasi bahan baku.');
        $token = $confirmation['token'] ?? '';
        if (!is_string($token) || !preg_match('/\A([0-9]{10})\.([a-f0-9]{64})\z/D',$token,$matches) || strlen($this->secret)<32
            || (int)$matches[1]>$now || $now-(int)$matches[1]>900
            || !hash_equals($this->sign($snapshot,(int)$matches[1]),$matches[2])) {
            throw new InvalidArgumentException('Stok atau pengajuan berubah, tinjauan kedaluwarsa, atau belum diperiksa. Perbarui cek stok dan konfirmasi kembali sebelum verifikasi.');
        }
        $needs = false;
        foreach ($snapshot['rows'] as $row) $needs = $needs || $row['needs_confirmation'];
        $contact = $confirmation['confirmed_with'] ?? ''; $reason = $confirmation['reason'] ?? '';
        if (!is_string($contact) || !is_string($reason) || !mb_check_encoding($contact.$reason,'UTF-8')
            || mb_strlen($contact)>150 || mb_strlen($reason)>1000) throw new InvalidArgumentException('Nama pihak konfirmasi/alasan tidak valid atau terlalu panjang.');
        $contact = trim($contact); $reason = trim($reason);
        if ($needs && (!in_array($confirmation['confirmed'] ?? false,[true,1,'1'],true) || mb_strlen($contact)<3 || mb_strlen($reason)<10)) {
            throw new InvalidArgumentException('Konfirmasi dulu ke divisi: isi nama pihak yang dikonfirmasi, alasan kebutuhan, dan centang pernyataan konfirmasi.');
        }
        return ['confirmed_with'=>$contact,'reason'=>$reason,'snapshot_json'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'snapshot_hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR))];
    }
}
