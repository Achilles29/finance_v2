<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ulasan dibuat per nota, bukan per kunjungan browser. Ini membuat QR pada
 * struk aman untuk dipakai sekali tanpa meminta pelanggan login.
 */
class Pos_customer_review_model extends CI_Model
{
    public function ready(): bool
    {
        return $this->db->table_exists('pos_customer_review')
            && $this->db->table_exists('pos_order');
    }

    public function ensure_for_order(array $header): ?array
    {
        if (!$this->ready()) {
            return null;
        }
        $orderId = max(0, (int)($header['order_id'] ?? $header['id'] ?? 0));
        if ($orderId <= 0) {
            return null;
        }
        $existing = $this->db->from('pos_customer_review')->where('order_id', $orderId)->limit(1)->get()->row_array();
        if ($existing) {
            return $existing;
        }

        $record = [
            'review_token' => $this->new_token($orderId),
            'order_id' => $orderId,
            'outlet_id' => $this->nullable_id($header['outlet_id'] ?? 0),
            'member_id' => $this->nullable_id($header['member_id'] ?? 0),
            'order_no_snapshot' => trim((string)($header['order_no'] ?? '')) ?: null,
            'customer_name_snapshot' => trim((string)($header['customer_name'] ?? $header['member_name'] ?? '')) ?: null,
            'review_status' => 'OPEN',
        ];
        // Versi awal tabel ulasan belum memiliki pembeda sumber. Tetap
        // izinkan QR struk berjalan sampai migration QR area dipasang.
        if ($this->db->field_exists('review_source', 'pos_customer_review')) {
            $record['review_source'] = 'RECEIPT';
        }
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->insert('pos_customer_review', $record);
        $ok = $this->db->affected_rows() > 0;
        $this->db->db_debug = $previousDebug;
        if ($ok) {
            $record['id'] = (int)$this->db->insert_id();
            return $record;
        }
        // Permintaan cetak serentak dapat membuat dua route mencoba membuat
        // token nota yang sama. Ambil baris pemenangnya tanpa gagal cetak.
        return $this->db->from('pos_customer_review')->where('order_id', $orderId)->limit(1)->get()->row_array() ?: null;
    }

    public function station_ready(): bool
    {
        return $this->ready()
            && $this->db->table_exists('pos_customer_review_station')
            && $this->db->field_exists('review_source', 'pos_customer_review')
            && $this->db->field_exists('station_id', 'pos_customer_review');
    }

    public function station_rows(bool $activeOnly = false): array
    {
        if (!$this->station_ready()) {
            return [];
        }
        $db = $this->db->from('pos_customer_review_station s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left');
        if ($activeOnly) {
            $db->where('s.is_active', 1);
        }
        return $db->select('s.*, o.outlet_name')
            ->order_by('s.is_active', 'DESC')
            ->order_by('s.station_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_station(int $id): ?array
    {
        if (!$this->station_ready() || $id <= 0) {
            return null;
        }
        return $this->db->from('pos_customer_review_station s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->select('s.*, o.outlet_name')
            ->where('s.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function find_station_by_code(string $code): ?array
    {
        if (!$this->station_ready()) {
            return null;
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        return $this->db->from('pos_customer_review_station s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->select('s.*, o.outlet_name')
            ->where('s.station_code', $code)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function default_station(): ?array
    {
        if (!$this->station_ready()) {
            return null;
        }
        return $this->db->from('pos_customer_review_station s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->select('s.*, o.outlet_name')
            ->where('s.is_active', 1)
            ->order_by("CASE WHEN s.station_code = 'GENERAL' THEN 0 ELSE 1 END", 'ASC', false)
            ->order_by('s.id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function station_url(?array $station = null): string
    {
        if (!$this->station_ready()) {
            return '';
        }
        $station = $station ?: $this->default_station();
        if (!$station) {
            return '';
        }
        $code = trim((string)($station['station_code'] ?? 'GENERAL'));
        return site_url('review/station/' . rawurlencode($code !== '' ? $code : 'GENERAL'));
    }

    public function save_station(array $data): array
    {
        if (!$this->station_ready()) {
            return ['ok' => false, 'message' => 'Fondasi QR ulasan belum tersedia. Jalankan migration ulasan pelanggan terbaru terlebih dahulu.'];
        }
        $id = max(0, (int)($data['id'] ?? 0));
        $name = trim((string)($data['station_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama area QR wajib diisi.'];
        }
        $code = strtoupper(trim((string)($data['station_code'] ?? '')));
        if ($code === '') {
            $code = 'REVIEW-' . strtoupper((string)preg_replace('/[^A-Z0-9]+/i', '-', $name));
        }
        $code = trim(substr($code, 0, 60), '-');
        if ($code === '') {
            return ['ok' => false, 'message' => 'Kode QR area tidak valid.'];
        }
        $duplicate = $this->db->from('pos_customer_review_station')
            ->where('station_code', $code);
        if ($id > 0) {
            $duplicate->where('id !=', $id);
        }
        if ((int)$duplicate->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Kode QR area sudah dipakai. Gunakan kode lain.'];
        }
        $outletId = max(0, (int)($data['outlet_id'] ?? 0));
        $record = [
            'station_code' => $code,
            'station_name' => $name,
            'outlet_id' => $outletId > 0 ? $outletId : null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            if (!$this->find_station($id)) {
                return ['ok' => false, 'message' => 'QR area tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pos_customer_review_station', $record);
            return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
        }
        $this->db->insert('pos_customer_review_station', $record);
        $id = (int)$this->db->insert_id();
        return $id > 0
            ? ['ok' => true, 'id' => $id]
            : ['ok' => false, 'message' => 'QR area gagal ditambahkan.'];
    }

    public function toggle_station(int $id): array
    {
        $station = $this->find_station($id);
        if (!$station) {
            return ['ok' => false, 'message' => 'QR area tidak ditemukan.'];
        }
        $active = empty($station['is_active']) ? 1 : 0;
        $this->db->where('id', $id)->update('pos_customer_review_station', ['is_active' => $active]);
        return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id, 'is_active' => $active];
    }

    public function submit_station_review(string $stationCode, array $input, string $ipAddress = '', string $userAgent = ''): array
    {
        $station = $this->find_station_by_code($stationCode);
        if (!$station || empty($station['is_active'])) {
            return ['ok' => false, 'message' => 'QR ulasan ini tidak ditemukan atau sedang tidak aktif.'];
        }
        $rating = (int)($input['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'message' => 'Pilih bintang dari 1 sampai 5 terlebih dahulu.'];
        }
        $name = trim((string)($input['customer_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama Anda perlu diisi agar ulasan dapat kami pahami.'];
        }
        if (mb_strlen($name) > 150) {
            return ['ok' => false, 'message' => 'Nama maksimal 150 karakter.'];
        }
        $phone = $this->normalize_phone((string)($input['mobile_phone'] ?? ''));
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Nomor WhatsApp perlu diisi untuk menghubungkan atau membuat Member Namua.'];
        }
        $reviewText = trim((string)($input['review_text'] ?? ''));
        if (mb_strlen($reviewText) > 1200) {
            return ['ok' => false, 'message' => 'Ulasan maksimal 1.200 karakter.'];
        }
        if (($input['join_member'] ?? '') !== '1') {
            return ['ok' => false, 'message' => 'Centang persetujuan penggunaan nomor WhatsApp sebelum mengirim ulasan.'];
        }
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            if ($this->db->trans_begin() === false) throw new RuntimeException('Transaksi ulasan belum tersedia.');
            $member = $this->find_member_by_phone($phone);
            $memberCreated = false;
            if (!$member) {
                $ci = &get_instance();
                $ci->load->model('Pos_model');
                $created = $ci->Pos_model->save_member([
                    'member_name' => $name,
                    'mobile_phone' => $phone,
                    'member_status' => 'ACTIVE',
                    'notes' => 'Daftar melalui QR ulasan pelanggan: ' . (string)($station['station_name'] ?? ''),
                ]);
                if (empty($created['ok'])) {
                    // Member errors may contain another person's name/number; never expose them publicly.
                    throw new RuntimeException('Member belum dapat diproses.');
                }
                $member = $ci->Pos_model->find_member((int)($created['id'] ?? 0));
                $memberCreated = empty($created['existing']);
            }
            if (!$member || empty($member['is_active']) || (string)$member['member_status'] !== 'ACTIVE') {
                throw new RuntimeException('Member belum dapat diproses.');
            }

            $record = [
                'review_token' => $this->new_token(0),
                'review_source' => 'STATION',
                'order_id' => null,
                'outlet_id' => $this->nullable_id($station['outlet_id'] ?? 0),
                'station_id' => (int)$station['id'],
                'member_id' => $this->nullable_id($member['id'] ?? 0),
                'order_no_snapshot' => null,
                // A public phone input is contact information, not proof of identity.
                'customer_name_snapshot' => $name,
                'visitor_phone_snapshot' => $phone,
                'rating' => $rating,
                'review_text' => $reviewText !== '' ? $reviewText : null,
                'review_status' => 'SUBMITTED',
                'submitted_at' => date('Y-m-d H:i:s'),
                'ip_hash' => null,
                'user_agent' => null,
            ];
            $inserted = $this->db->insert('pos_customer_review', $record);
            $reviewId = (int)$this->db->insert_id();
            if (!$inserted || $reviewId <= 0 || $this->db->trans_status() === false) {
                throw new RuntimeException('Ulasan belum dapat disimpan.');
            }
            if (!$this->db->trans_commit()) throw new RuntimeException('Ulasan belum dapat disimpan.');
            return [
                'ok' => true,
                'review_id' => $reviewId,
                'member_id' => (int)$member['id'],
                'member_created' => $memberCreated,
                'member_name' => $name,
            ];
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Ulasan atau pendaftaran belum dapat diproses. Silakan coba kembali nanti atau hubungi tim outlet.'];
        } finally {
            $this->db->db_debug = $previousDebug;
        }
    }

    public function find_by_token(string $token): ?array
    {
        if (!$this->ready() || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }
        return $this->db->select('r.*, o.outlet_id AS order_outlet_id, o.status AS order_status, out.outlet_name')
            ->from('pos_customer_review r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet out', 'out.id = r.outlet_id', 'left')
            ->where('r.review_token', strtolower($token))
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function submit(string $token, int $rating, string $reviewText, string $ipAddress = '', string $userAgent = ''): array
    {
        $review = $this->find_by_token($token);
        if (!$review) {
            return ['ok' => false, 'message' => 'Tautan ulasan tidak ditemukan atau sudah tidak valid.'];
        }
        if ((string)($review['review_status'] ?? '') === 'HIDDEN') {
            return ['ok' => false, 'message' => 'Tautan ulasan ini tidak lagi tersedia.'];
        }
        if ((string)($review['review_status'] ?? '') === 'SUBMITTED') {
            return ['ok' => false, 'message' => 'Ulasan untuk nota ini sudah diterima. Terima kasih.'];
        }
        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'message' => 'Pilih bintang dari 1 sampai 5 terlebih dahulu.'];
        }
        $reviewText = trim($reviewText);
        if (mb_strlen($reviewText) > 1200) {
            return ['ok' => false, 'message' => 'Ulasan maksimal 1.200 karakter.'];
        }
        $this->db->where('id', (int)$review['id'])->where('review_status', 'OPEN')->update('pos_customer_review', [
            'rating' => $rating,
            'review_text' => $reviewText !== '' ? $reviewText : null,
            'review_status' => 'SUBMITTED',
            'submitted_at' => date('Y-m-d H:i:s'),
            'ip_hash' => null,
            'user_agent' => null,
        ]);
        if ($this->db->affected_rows() <= 0) {
            return ['ok' => false, 'message' => 'Ulasan ini sudah diproses dari perangkat lain.'];
        }
        return ['ok' => true, 'review_id' => (int)$review['id']];
    }

    public function rows(array $filters = []): array
    {
        if (!$this->ready()) {
            return ['rows' => [], 'meta' => $this->meta(0, 1, 25)];
        }
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $from = $this->date_or_empty($filters['date_from'] ?? '');
        $to = $this->date_or_empty($filters['date_to'] ?? '');
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(10, min(100, (int)($filters['limit'] ?? 25)));
        $db = $this->base_rows_query();
        if ($q !== '') {
            $db->group_start()
                ->like('r.order_no_snapshot', $q)
                ->or_like('r.customer_name_snapshot', $q)
                ->or_like('r.review_text', $q)
                ->or_like('out.outlet_name', $q)
            ;
            if ($this->station_ready()) {
                $db->or_like('station.station_name', $q)
                    ->or_like('station.station_code', $q);
            }
            $db->group_end();
        }
        if (in_array($status, ['OPEN', 'SUBMITTED', 'HIDDEN'], true)) {
            $db->where('r.review_status', $status);
        }
        if ($from !== '') {
            $db->where('DATE(COALESCE(r.submitted_at, r.created_at)) >=', $from);
        }
        if ($to !== '') {
            $db->where('DATE(COALESCE(r.submitted_at, r.created_at)) <=', $to);
        }
        $total = (int)$db->count_all_results('', false);
        $pages = max(1, (int)ceil($total / $limit));
        $page = min($page, $pages);
        $offset = ($page - 1) * $limit;
        $select = 'r.*, out.outlet_name, member.member_name, o.order_no AS order_no_live';
        if ($this->station_ready()) {
            $select .= ', station.station_name, station.station_code';
        } else {
            $select .= ', NULL AS station_name, NULL AS station_code';
        }
        $rows = $db->select($select, false)
            ->order_by('COALESCE(r.submitted_at, r.created_at)', 'DESC', false)
            ->order_by('r.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        return ['rows' => $rows, 'meta' => $this->meta($total, $page, $limit)];
    }

    public function set_visibility(int $id, bool $hidden, int $actorUserId, string $reason = ''): array
    {
        if (!$this->ready() || $id <= 0) {
            return ['ok' => false, 'message' => 'Ulasan tidak ditemukan.'];
        }
        $row = $this->db->from('pos_customer_review')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Ulasan tidak ditemukan.'];
        }
        $payload = $hidden ? [
            'review_status' => 'HIDDEN',
            'hidden_by' => $actorUserId > 0 ? $actorUserId : null,
            'hidden_at' => date('Y-m-d H:i:s'),
            'hidden_reason' => trim($reason) ?: 'Disembunyikan moderator.',
        ] : [
            'review_status' => !empty($row['submitted_at']) ? 'SUBMITTED' : 'OPEN',
            'hidden_by' => null,
            'hidden_at' => null,
            'hidden_reason' => null,
        ];
        $this->db->where('id', $id)->update('pos_customer_review', $payload);
        return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id, 'review_status' => $payload['review_status']];
    }

    private function base_rows_query()
    {
        $db = $this->db->from('pos_customer_review r')
            ->join('pos_outlet out', 'out.id = r.outlet_id', 'left')
            ->join('crm_member member', 'member.id = r.member_id', 'left')
            ->join('pos_order o', 'o.id = r.order_id', 'left');
        if ($this->station_ready()) {
            $db->join('pos_customer_review_station station', 'station.id = r.station_id', 'left');
        }
        return $db;
    }

    private function find_member_by_phone(string $phone): ?array
    {
        if (!$this->db->table_exists('crm_member')) {
            return null;
        }
        $variants = $this->phone_variants($phone);
        if (empty($variants)) {
            return null;
        }
        return $this->db->from('crm_member')
            ->where_in('mobile_phone', $variants)
            ->where('is_active', 1)
            ->where('member_status', 'ACTIVE')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function normalize_phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        } elseif (strpos($digits, '8') === 0) {
            $digits = '0' . $digits;
        }
        return strlen($digits) >= 9 && strlen($digits) <= 16 ? $digits : '';
    }

    private function phone_variants(string $phone): array
    {
        $canonical = $this->normalize_phone($phone);
        if ($canonical === '') {
            return [];
        }
        $international = '62' . substr($canonical, 1);
        return array_values(array_unique([$canonical, $international, '+' . $international]));
    }

    private function new_token(int $orderId): string
    {
        try {
            return hash('sha256', random_bytes(32) . '|' . $orderId . '|' . microtime(true));
        } catch (Throwable $e) {
            return hash('sha256', uniqid((string)$orderId, true) . '|' . mt_rand());
        }
    }

    private function nullable_id($value): ?int
    {
        $id = max(0, (int)$value);
        return $id > 0 ? $id : null;
    }

    private function date_or_empty($value): string
    {
        $value = trim((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function meta(int $total, int $page, int $limit): array
    {
        return ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => max(1, (int)ceil($total / max(1, $limit)))];
    }
}
