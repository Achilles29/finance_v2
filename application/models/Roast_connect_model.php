<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Optional catalog access. This model never writes inventory or production. */
class Roast_connect_model extends CI_Model
{
    public function ready(): bool
    {
        return $this->db->table_exists('sys_roast_connect') && $this->db->table_exists('sys_roast_connect_audit');
    }

    private function row(bool $lock = false): array
    {
        if (!$this->ready()) throw new RuntimeException('Modul konektor belum dipasang. Jalankan migration Roast Connect.');
        $row = $this->db->query('SELECT * FROM sys_roast_connect WHERE id=1' . ($lock ? ' FOR UPDATE' : ''))->row_array();
        if (!$row) throw new RuntimeException('Pengaturan konektor belum tersedia.');
        return $row;
    }

    private function visible(array $row): array
    {
        $row['has_token'] = !empty($row['token_hash']);
        unset($row['token_hash']);
        $row['enabled'] = (bool)$row['enabled'];
        $row['revision'] = (int)$row['revision'];
        $row['division_id'] = $row['division_id'] === null ? null : (int)$row['division_id'];
        $row['posting_enabled'] = false;
        return $row;
    }

    public function settings(): array { return $this->visible($this->row()); }

    public function divisions(): array
    {
        return $this->db->query('SELECT id,code,name FROM mst_operational_division WHERE is_active=1 ORDER BY sort_order,name')->result_array();
    }

    public function audit(): array
    {
        return $this->db->query('SELECT id,event_type,actor_id,details,created_at FROM sys_roast_connect_audit ORDER BY id DESC LIMIT 20')->result_array();
    }

    public function change(array $input, int $actorId, bool $rotate = false): array
    {
        if ($actorId < 1) throw new RuntimeException('Administrator tidak valid.');
        if (!is_string($input['name'] ?? null) || !is_bool($input['enabled'] ?? null)) throw new RuntimeException('Format pengaturan tidak valid.');
        $name = trim($input['name']);
        if ($name === '' || strlen($name) > 160) throw new RuntimeException('Isi nama koneksi maksimal 160 karakter.');
        $division = filter_var($input['division_id'] ?? null, FILTER_VALIDATE_INT);
        $revision = filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT);
        $destination = $input['destination_type'] ?? '';
        if (!$division || !in_array($destination, ['ROASTERY','BAR','KITCHEN','EVENT'], true)) throw new RuntimeException('Pilih divisi dan lokasi stok yang valid.');
        $days = $input['valid_days'] ?? 365;
        if ($rotate && !in_array($days, [30,90,365], true)) throw new RuntimeException('Pilih masa berlaku token 30, 90, atau 365 hari.');
        $this->db->trans_begin();
        try {
            $old = $this->row(true);
            if ($revision === false || $revision !== (int)$old['revision']) throw new RuntimeException('Pengaturan berubah di perangkat lain. Muat ulang sebelum menyimpan.');
            $location = $this->db->query('SELECT id FROM mst_operational_division WHERE id=? AND is_active=1', [$division])->row_array();
            if (!$location) throw new RuntimeException('Divisi stok tidak aktif atau tidak ditemukan.');
            $token = $rotate ? bin2hex(random_bytes(32)) : null;
            $hash = $token === null ? $old['token_hash'] : hash('sha256', $token);
            $expires = $rotate ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : $old['expires_at'];
            if ($input['enabled'] && (!$hash || ($expires && strtotime($expires . ' UTC') <= time()))) throw new RuntimeException('Buat token yang masih berlaku sebelum mengaktifkan konektor.');
            $tail = $token === null ? $old['token_tail'] : substr($token, -4);
            $tokenCreated = $rotate ? gmdate('Y-m-d H:i:s') : $old['token_created_at'];
            $this->db->query('UPDATE sys_roast_connect SET name=?,enabled=?,division_id=?,destination_type=?,token_hash=?,token_tail=?,token_created_at=?,expires_at=?,revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=1', [
                $name, (int)$input['enabled'], $division, $destination, $hash, $tail, $tokenCreated, $expires, $actorId,
            ]);
            $event = $rotate ? ($old['token_hash'] ? 'TOKEN_ROTATED' : 'TOKEN_CREATED') : 'SETTINGS_SAVED';
            $details = json_encode(['name'=>$name,'enabled'=>$input['enabled'],'division_id'=>$division,'destination_type'=>$destination,'token_tail'=>$tail,'expires_at'=>$expires,'revision'=>$revision+1], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->db->query('INSERT INTO sys_roast_connect_audit(event_type,actor_id,details,created_at) VALUES(?,?,?,UTC_TIMESTAMP())', [$event,$actorId,$details]);
            if (!$this->db->trans_status()) throw new RuntimeException('Pengaturan belum tersimpan. Coba kembali.');
            $result = ['settings'=>$this->settings()];
            if (!$this->db->trans_commit()) throw new RuntimeException('Pengaturan belum tersimpan. Muat ulang dan coba kembali.');
            if ($token !== null) $result['token'] = $token; // returned once; never persisted as plaintext
            return $result;
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            throw $error;
        }
    }

    public function authorize(string $header): array
    {
        $row = $this->row();
        if (!$row['enabled']) throw new RuntimeException('Konektor Finance belum diaktifkan.', 403);
        if (!preg_match('/^Bearer ([A-Za-z0-9_-]{32,256})$/D', $header, $match)
            || empty($row['token_hash']) || !hash_equals($row['token_hash'], hash('sha256', $match[1]))) {
            throw new RuntimeException('Token koneksi tidak valid.', 401);
        }
        if ($row['expires_at'] && strtotime($row['expires_at'] . ' UTC') <= time()) throw new RuntimeException('Token koneksi sudah kedaluwarsa.', 401);
        $division = $this->db->query('SELECT id FROM mst_operational_division WHERE id=? AND is_active=1', [$row['division_id']])->row_array();
        if (!$division || !in_array($row['destination_type'], ['ROASTERY','BAR','KITCHEN','EVENT'], true)) throw new RuntimeException('Lingkup stok konektor tidak tersedia.', 403);
        return $this->visible($row);
    }

    public function materials(array $scope, int $page = 1, ?int $materialId = null): array
    {
        if ($page < 1 || $page > 30) throw new RuntimeException('Halaman katalog tidak valid.', 422);
        foreach (['mst_material','mst_uom','inv_material_fifo_lot'] as $table) {
            if (!$this->db->table_exists($table)) throw new RuntimeException('Skema persediaan belum kompatibel.', 503);
        }
        $params = [(int)$scope['division_id'], $scope['destination_type']];
        $where = 'm.is_active=1';
        if ($materialId !== null) { $where .= ' AND m.id=?'; $params[] = $materialId; }
        $offset = ($page - 1) * 500;
        $sql = "SELECT m.id,m.material_code,m.material_name,m.content_uom_id,u.code AS uom,
            COALESCE(SUM(CASE WHEN l.content_uom_id=m.content_uom_id THEN l.qty_balance ELSE 0 END),0) AS qty,
            SUM(CASE WHEN l.id IS NOT NULL AND (l.content_uom_id IS NULL OR l.content_uom_id<>m.content_uom_id) THEN 1 ELSE 0 END) AS mixed_units
            FROM mst_material m LEFT JOIN mst_uom u ON u.id=m.content_uom_id
            LEFT JOIN inv_material_fifo_lot l ON l.material_id=m.id AND l.location_scope='DIVISION'
            AND l.division_id=? AND l.destination_type=? AND UPPER(COALESCE(l.status,'OPEN'))='OPEN' AND l.qty_balance>0
            WHERE $where GROUP BY m.id,m.material_code,m.material_name,m.content_uom_id,u.code ORDER BY m.id LIMIT 500 OFFSET $offset";
        $query = $this->db->query($sql, $params);
        if (!$query) throw new RuntimeException('Katalog Finance belum dapat dibaca.', 503);
        $items = [];
        foreach ($query->result_array() as $r) $items[] = ['id'=>(string)$r['id'],'code'=>$r['material_code'],'name'=>$r['material_name'],'uom'=>$r['uom'],'available_qty'=>(int)$r['mixed_units'] > 0 ? null : round((float)$r['qty'],4)];
        return $items;
    }
}
