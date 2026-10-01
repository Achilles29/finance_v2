<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Satu pintu konfigurasi printer POS.
 *
 * Tabel pos_print_* sengaja dipisahkan dari tabel printer lama agar koneksi,
 * branding, layout, dan routing tidak saling menimpa saat disunting admin.
 */
class Pos_print_model extends CI_Model
{
    private const DOCUMENT_TYPES = [
        'RECEIPT',
        'KITCHEN_TICKET',
        'VOID_SLIP',
        'REFUND_SLIP',
        'DEPOSIT_RECEIPT',
        'SHIFT_CLOSE',
    ];

    private const EVENT_TYPES = [
        'ORDER_CONFIRM_KOT',
        'ORDER_PRE_BILL',
        'ORDER_PAID_RECEIPT',
        'VOID_SLIP',
        'REFUND_SLIP',
        'SHIFT_CLOSE_SUMMARY',
    ];

    private const DOCUMENT_LABELS = [
        'RECEIPT' => 'Struk pembayaran',
        'KITCHEN_TICKET' => 'KOT / tiket produksi',
        'VOID_SLIP' => 'Slip void',
        'REFUND_SLIP' => 'Slip refund',
        'DEPOSIT_RECEIPT' => 'Struk deposit',
        'SHIFT_CLOSE' => 'Ringkasan tutup kasir',
    ];

    private const EVENT_LABELS = [
        'ORDER_CONFIRM_KOT' => 'Order dikonfirmasi (KOT)',
        'ORDER_PRE_BILL' => 'Bill sementara diminta',
        'ORDER_PAID_RECEIPT' => 'Pembayaran selesai (struk)',
        'VOID_SLIP' => 'Transaksi di-void',
        'REFUND_SLIP' => 'Refund diproses',
        'SHIFT_CLOSE_SUMMARY' => 'Tutup kasir',
    ];

    private const PRINT_MODE_LABELS = [
        'OFF' => 'Tidak cetak',
        'AUTO' => 'Cetak otomatis',
        'ASK' => 'Tanya dulu',
    ];

    public function ready(): bool
    {
        foreach (['pos_print_connection', 'pos_print_general_setting', 'pos_print_layout', 'pos_print_route', 'pos_print_attempt'] as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The local printer agent only needs a physical connection. Do not make
     * its bootstrap depend on layouts, routes, or the print-attempt history.
     */
    public function agent_connection_ready(): bool
    {
        if (!$this->db->table_exists('pos_print_connection')) {
            return false;
        }

        foreach ([
            'connection_name',
            'connection_type',
            'agent_host',
            'agent_printer_code',
            'mac_address',
            'python_port',
            'paper_width_mm',
            'chars_per_line',
            'default_copy_count',
            'open_drawer',
            'is_active',
        ] as $field) {
            if (!$this->db->field_exists($field, 'pos_print_connection')) {
                return false;
            }
        }

        return true;
    }

    public function routes_enabled(): bool
    {
        return $this->ready()
            && (int)$this->db->from('pos_print_route')->where('is_active', 1)->count_all_results() > 0;
    }

    public function document_types(): array
    {
        return self::DOCUMENT_TYPES;
    }

    public function event_types(): array
    {
        return self::EVENT_TYPES;
    }

    public function document_type_labels(): array
    {
        return self::DOCUMENT_LABELS;
    }

    public function event_type_labels(): array
    {
        return self::EVENT_LABELS;
    }

    public function document_type_label(string $documentType): string
    {
        $documentType = strtoupper(trim($documentType));
        return self::DOCUMENT_LABELS[$documentType] ?? $documentType;
    }

    public function event_type_label(string $eventCode): string
    {
        $eventCode = strtoupper(trim($eventCode));
        return self::EVENT_LABELS[$eventCode] ?? $eventCode;
    }

    public function print_mode_labels(): array
    {
        return self::PRINT_MODE_LABELS;
    }

    public function print_mode_label(string $mode): string
    {
        $mode = strtoupper(trim($mode));
        return self::PRINT_MODE_LABELS[$mode] ?? $mode;
    }

    public function general_settings(int $outletId = 0): array
    {
        // Branding printer saat ini sengaja satu sumber global. Outlet tetap
        // dibaca dari transaksi untuk data nota, bukan untuk menduplikasi logo/footer.
        $outletId = 0;
        $businessProfile = $this->business_profile_fallback();
        $profileFooter = trim((string)($businessProfile['document_footer'] ?? ''));
        $defaults = [
            // Urutan sumber identitas: override printer/outlet -> profil usaha
            // -> default produk. Data umum cetak yang telah ada tidak ditimpa.
            'title' => (string)($businessProfile['display_name'] ?? 'Finance POS'),
            'subtitle' => (string)($businessProfile['address'] ?? ''),
            'logo_url' => (string)($businessProfile['logo_url'] ?? $this->default_logo_url()),
            'wifi_name' => '',
            'wifi_password' => '',
            'customer_voucher_limit' => 1,
            'customer_voucher_message_template' => "Selamat, Anda mendapat voucher {voucher_benefit}.\nKode: {voucher_code}\nGunakan sebelum {voucher_expiry}.",
            'customer_voucher_align' => 'CENTER',
            'customer_review_qr_enabled' => 0,
            'customer_review_message' => 'Bagikan ulasan Anda dengan scan QR berikut.',
            'header_lines' => ['ORDER CEPAT, SAJI HANGAT.'],
            'footer_lines' => $profileFooter !== '' ? [$profileFooter] : ['TERIMA KASIH SUDAH BERKUNJUNG'],
        ];

        if (!$this->ready()) {
            return ['row' => null, 'payload' => $defaults];
        }

        $row = $this->db->from('pos_print_general_setting')
            ->where('is_active', 1)
            ->where('outlet_id IS NULL', null, false)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $payload = $this->decode_payload((string)($row['general_payload'] ?? ''));
        $payload = array_merge($defaults, $payload);
        if (trim((string)$payload['logo_url']) === '') {
            $payload['logo_url'] = (string)($businessProfile['logo_url'] ?? $this->default_logo_url());
        }
        $payload['logo_url'] = $this->normalize_logo_url_value((string)$payload['logo_url']);
        return ['row' => $row ?: null, 'payload' => $payload];
    }

    public function save_general_settings(array $data): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Fondasi konfigurasi printer belum tersedia. Jalankan migration printer terbaru terlebih dahulu.'];
        }

        // `setting_code` bersifat unik pada fondasi awal, jadi jangan membuat
        // baris branding ganda per outlet yang berpotensi saling menimpa.
        $outletId = 0;
        // Pengaturan ulasan juga disunting dari halaman Ulasan Pelanggan.
        // Mulai dari data tersimpan agar penyimpanan satu bagian tidak
        // mengosongkan branding atau data umum yang lain.
        $current = (array)($this->general_settings($outletId)['payload'] ?? []);
        $businessProfile = $this->business_profile_fallback();
        $value = static function (string $key, $fallback = '') use ($data, $current) {
            return array_key_exists($key, $data) ? $data[$key] : ($current[$key] ?? $fallback);
        };
        $payload = [
            'title' => trim((string)$value('title', (string)($businessProfile['display_name'] ?? 'Finance'))),
            'subtitle' => trim((string)$value('subtitle', '')),
            'logo_url' => $this->normalize_logo_url_value(trim((string)$value('logo_url', $this->default_logo_url()))),
            'wifi_name' => trim((string)$value('wifi_name', '')),
            'wifi_password' => trim((string)$value('wifi_password', '')),
            'customer_voucher_limit' => max(1, min(5, (int)$value('customer_voucher_limit', 1))),
            'customer_voucher_message_template' => trim((string)$value('customer_voucher_message_template', '')),
            'customer_voucher_align' => $this->enum_value($value('customer_voucher_align', 'CENTER'), ['LEFT', 'CENTER', 'RIGHT'], 'CENTER'),
            'customer_review_qr_enabled' => !empty($value('customer_review_qr_enabled', 0)) ? 1 : 0,
            'customer_review_message' => trim((string)$value('customer_review_message', '')),
            'header_lines' => $this->lines($value('header_lines', [])),
            'footer_lines' => $this->lines($value('footer_lines', [])),
        ];
        if ($payload['title'] === '') {
            return ['ok' => false, 'message' => 'Nama outlet atau judul cetak wajib diisi.'];
        }
        if ($payload['logo_url'] === '') {
            $payload['logo_url'] = $this->default_logo_url();
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'message' => 'Data tampilan umum tidak dapat dibaca.'];
        }

        $rowQuery = $this->db->from('pos_print_general_setting')
            ->where('setting_code', 'GLOBAL');
        if ($outletId > 0) {
            $rowQuery->where('outlet_id', $outletId);
        } else {
            $rowQuery->where('outlet_id IS NULL', null, false);
        }
        $row = $rowQuery->limit(1)->get()->row_array();
        $record = [
            'setting_code' => 'GLOBAL',
            'setting_name' => $outletId > 0 ? 'Tampilan Umum Outlet' : 'Tampilan Umum Semua Outlet',
            'outlet_id' => $outletId > 0 ? $outletId : null,
            'general_payload' => $json,
            'notes' => 'Sumber tunggal branding dan data umum cetak POS.',
            'is_active' => 1,
        ];
        if ($row) {
            $this->db->where('id', (int)$row['id'])->update('pos_print_general_setting', $record);
            return $this->db->affected_rows() >= 0
                ? ['ok' => true, 'id' => (int)$row['id']]
                : ['ok' => false, 'message' => 'Gagal menyimpan tampilan umum cetak.'];
        }
        $this->db->insert('pos_print_general_setting', $record);
        return $this->db->insert_id() > 0
            ? ['ok' => true, 'id' => (int)$this->db->insert_id()]
            : ['ok' => false, 'message' => 'Gagal menambah tampilan umum cetak.'];
    }

    public function default_logo_url(): string
    {
        if (defined('FCPATH') && !is_file(FCPATH . 'assets/img/logo.png')) return '';
        return base_url('assets/img/logo.png');
    }

    private function normalize_logo_url_value(string $logoUrl): string
    {
        $logoUrl = trim($logoUrl);
        if ($logoUrl === '') {
            return $this->default_logo_url();
        }

        $ci = function_exists('get_instance') ? get_instance() : null;
        if ($ci && method_exists($ci, 'load') && (!isset($ci->posprinterpreviewservice) || !is_object($ci->posprinterpreviewservice))) {
            $ci->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        }
        if ($ci && isset($ci->posprinterpreviewservice) && is_object($ci->posprinterpreviewservice)
            && method_exists($ci->posprinterpreviewservice, 'normalizedLogoUrl')) {
            $logoUrl = (string)$ci->posprinterpreviewservice->normalizedLogoUrl($logoUrl);
        }

        return $this->is_managed_logo_url($logoUrl) ? $logoUrl : $this->default_logo_url();
    }

    /** Printer markers may only load a verified application-managed logo. */
    private function is_managed_logo_url(string $logoUrl): bool
    {
        $parts = @parse_url(trim($logoUrl));
        if (!is_array($parts)) {
            return false;
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $currentHost = strtolower((string)parse_url(base_url(), PHP_URL_HOST));
        if ($host !== '' && ($currentHost === '' || !hash_equals($currentHost, $host))) {
            return false;
        }

        $path = rawurldecode((string)($parts['path'] ?? ''));
        $basePath = trim((string)parse_url(base_url(), PHP_URL_PATH), '/');
        if ($basePath !== '' && strpos(ltrim($path, '/'), $basePath . '/') === 0) {
            $path = substr(ltrim($path, '/'), strlen($basePath) + 1);
        }
        $relativePath = ltrim(str_replace('\\', '/', $path), '/');
        if (strpos($relativePath, '..') !== false || preg_match('~^(?:assets/img/logo\.png|assets/uploads/logo/logo\.png|assets/uploads/(?:pos-printer-logo|business-profile-logo)/[a-f0-9]{32}\.(?:png|jpe?g))$~i', $relativePath) !== 1) {
            return false;
        }

        $root = rtrim(str_replace('\\', '/', (string)FCPATH), '/');
        $realPath = realpath($root . '/' . $relativePath);
        return $realPath !== false && is_file($realPath) && strpos(str_replace('\\', '/', $realPath), $root . '/') === 0;
    }

    /** Safe read-only fallback; unavailable schema must not affect printing. */
    private function business_profile_fallback(): array
    {
        try {
            if (!$this->db->table_exists('sys_business_profile')) {
                return ['display_name' => 'Finance', 'address' => '', 'logo_url' => $this->default_logo_url(), 'document_footer' => ''];
            }
            $row = $this->db->select('display_name, address, logo_url, document_footer')->from('sys_business_profile')->where('id', 1)->limit(1)->get()->row_array();
            $name = trim((string)($row['display_name'] ?? ''));
            $address = trim((string)($row['address'] ?? ''));
            $logo = trim((string)($row['logo_url'] ?? ''));
            $footer = trim((string)($row['document_footer'] ?? ''));
            return ['display_name' => $name !== '' ? $name : 'Finance', 'address' => $address, 'logo_url' => $logo !== '' ? $logo : $this->default_logo_url(), 'document_footer' => $footer];
        } catch (Throwable $error) {
            return ['display_name' => 'Finance', 'address' => '', 'logo_url' => $this->default_logo_url(), 'document_footer' => ''];
        }
    }

    /**
     * Halaman ulasan hanya mengelola dua data milik QR. Metode ini menjaga
     * semua pengaturan umum lain tetap utuh ketika operator menyimpan QR.
     */
    public function save_customer_review_settings(array $data): array
    {
        $current = (array)($this->general_settings()['payload'] ?? []);
        return $this->save_general_settings(array_merge($current, [
            'customer_review_qr_enabled' => !empty($data['customer_review_qr_enabled']) ? 1 : 0,
            'customer_review_message' => trim((string)($data['customer_review_message'] ?? ($current['customer_review_message'] ?? ''))),
        ]));
    }

    public function options(): array
    {
        $result = [
            'outlets' => [],
            'terminals' => [],
            'operational_divisions' => [],
            'product_divisions' => [],
            'connections' => [],
            'layouts' => [],
        ];
        if (!$this->ready()) {
            return $result;
        }
        $result['outlets'] = $this->db->select('id, outlet_code, outlet_name')->from('pos_outlet')->where('is_active', 1)->order_by('outlet_name', 'ASC')->get()->result_array();
        $result['terminals'] = $this->db->select('id, terminal_code, terminal_name, outlet_id')->from('pos_terminal')->where('is_active', 1)->order_by('terminal_name', 'ASC')->get()->result_array();
        $result['operational_divisions'] = $this->db->select('id, code, name')->from('mst_operational_division')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
        $result['product_divisions'] = $this->db->select('id, code, name')->from('mst_product_division')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
        $result['connections'] = $this->db->select('id, connection_code, connection_name, outlet_id, operational_division_id, connection_type, paper_width_mm, chars_per_line, is_active')
            ->from('pos_print_connection')->order_by('is_active', 'DESC')->order_by('connection_name', 'ASC')->get()->result_array();
        $result['layouts'] = $this->db->select('id, layout_code, layout_name, document_type, is_active')
            ->from('pos_print_layout')->order_by('document_type', 'ASC')->order_by('layout_name', 'ASC')->get()->result_array();
        return $result;
    }

    public function connection_rows(array $filters = []): array
    {
        if (!$this->ready()) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(5, min(100, (int)($filters['limit'] ?? 25)));
        $db = $this->db->from('pos_print_connection c')
            ->join('pos_outlet o', 'o.id = c.outlet_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        if ($outletId > 0) {
            $db->where('c.outlet_id', $outletId);
        }
        if ($q !== '') {
            $db->group_start()->like('c.connection_code', $q)->or_like('c.connection_name', $q)
                ->or_like('c.location_label', $q)->or_like('c.agent_printer_code', $q)
                ->or_like('c.agent_host', $q)->or_like('c.device_name', $q)->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('c.is_active', 0);
        }
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $pages] = $this->paginate($total, $page, $limit);
        $rows = $db->select('c.*, o.outlet_name, d.name AS operational_division_name')
            ->order_by('c.is_active', 'DESC')->order_by('o.outlet_name', 'ASC')->order_by('c.connection_name', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $pages]];
    }

    public function find_connection(int $id): ?array
    {
        if (!$this->ready() || $id <= 0) {
            return null;
        }
        return $this->db->from('pos_print_connection')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function find_active_connection_at_outlet(int $id, int $outletId): ?array
    {
        if (!$this->ready() || $id <= 0 || $outletId <= 0) {
            return null;
        }
        return $this->db->from('pos_print_connection')
            ->where('id', $id)
            ->where('outlet_id', $outletId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_connection(array $data): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Fondasi konfigurasi printer belum tersedia.'];
        }
        $id = max(0, (int)($data['id'] ?? 0));
        $name = trim((string)($data['connection_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama koneksi printer wajib diisi.'];
        }
        $type = $this->enum_value($data['connection_type'] ?? 'LOCAL_AGENT', ['LOCAL_AGENT', 'LAN', 'USB'], 'LOCAL_AGENT');
        $agentPrinterCode = strtoupper(trim((string)($data['agent_printer_code'] ?? '')));
        if ($type === 'LOCAL_AGENT' && $agentPrinterCode === '') {
            return ['ok' => false, 'message' => 'Kode printer pada agent wajib diisi untuk koneksi Local Agent.'];
        }
        $code = strtoupper(trim((string)($data['connection_code'] ?? '')));
        if ($code === '') {
            $code = $this->named_code('pos_print_connection', 'connection_code', $name, 'PRN-', $id, 60);
        } elseif ($this->code_exists('pos_print_connection', 'connection_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode koneksi printer sudah dipakai.'];
        }
        // The current print font supports one reliable column density per paper
        // width. Do not preserve an old 48-column value on a 58 mm printer.
        $paperWidthMm = (int)($data['paper_width_mm'] ?? 80) === 58 ? 58 : 80;
        $record = [
            'connection_code' => $code,
            'connection_name' => $name,
            'outlet_id' => $this->nullable_id($data['outlet_id'] ?? 0),
            'operational_division_id' => $this->nullable_id($data['operational_division_id'] ?? 0),
            'location_label' => trim((string)($data['location_label'] ?? '')) ?: null,
            'connection_type' => $type,
            'agent_os' => $this->enum_value($data['agent_os'] ?? 'WINDOWS', ['WINDOWS', 'UBUNTU', 'OTHER'], 'WINDOWS'),
            'agent_host' => trim((string)($data['agent_host'] ?? '')) ?: null,
            'agent_printer_code' => $agentPrinterCode ?: null,
            'device_name' => trim((string)($data['device_name'] ?? '')) ?: null,
            'mac_address' => trim((string)($data['mac_address'] ?? '')) ?: null,
            'python_port' => $this->nullable_positive_int($data['python_port'] ?? 0),
            'ip_address' => trim((string)($data['ip_address'] ?? '')) ?: null,
            'port' => $this->nullable_positive_int($data['port'] ?? 0),
            'paper_width_mm' => $paperWidthMm,
            'chars_per_line' => $paperWidthMm === 58 ? 32 : 48,
            'default_copy_count' => max(1, min(10, (int)($data['default_copy_count'] ?? 1))),
            'cut_mode' => $this->enum_value($data['cut_mode'] ?? 'PARTIAL', ['NONE', 'PARTIAL', 'FULL'], 'PARTIAL'),
            'open_drawer' => !empty($data['open_drawer']) ? 1 : 0,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($type === 'LOCAL_AGENT' && empty($record['python_port'])) {
            return ['ok' => false, 'message' => 'Port Local Agent wajib diisi untuk koneksi Local Agent.'];
        }
        if ($type === 'LAN' && empty($record['ip_address'])) {
            return ['ok' => false, 'message' => 'IP printer wajib diisi untuk koneksi LAN.'];
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('pos_print_connection', $record);
            return ['ok' => true, 'id' => $id];
        }
        $this->db->insert('pos_print_connection', $record);
        return $this->db->insert_id() > 0 ? ['ok' => true, 'id' => (int)$this->db->insert_id()] : ['ok' => false, 'message' => 'Gagal menyimpan koneksi printer.'];
    }

    public function toggle_connection(int $id): array
    {
        $row = $this->find_connection($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Koneksi printer tidak ditemukan.'];
        }
        $active = (int)($row['is_active'] ?? 0) === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('pos_print_connection', ['is_active' => $active]);
        return ['ok' => true, 'id' => $id, 'is_active' => $active];
    }

    public function layout_rows(array $filters = []): array
    {
        if (!$this->ready()) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $documentType = strtoupper(trim((string)($filters['document_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(5, min(100, (int)($filters['limit'] ?? 25)));
        $db = $this->db->from('pos_print_layout l');
        if ($q !== '') {
            $db->group_start()->like('l.layout_code', $q)->or_like('l.layout_name', $q)->or_like('l.description', $q)->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('l.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('l.is_active', 0);
        }
        if (in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $db->where('l.document_type', $documentType);
        }
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $pages] = $this->paginate($total, $page, $limit);
        $rows = $db->select('l.*')->order_by('l.document_type', 'ASC')->order_by('l.is_default', 'DESC')->order_by('l.layout_name', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
        foreach ($rows as &$row) {
            $row['payload'] = $this->layout_payload($row);
        }
        unset($row);
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $pages]];
    }

    public function find_layout(int $id): ?array
    {
        if (!$this->ready() || $id <= 0) {
            return null;
        }
        $row = $this->db->from('pos_print_layout')->where('id', $id)->limit(1)->get()->row_array();
        if ($row) {
            $row['payload'] = $this->layout_payload($row);
        }
        return $row ?: null;
    }

    /**
     * Membaca route yang benar-benar memakai layout. Dipakai editor layout
     * agar tombol test tidak menebak printer tujuan.
     */
    public function routes_for_layout(int $layoutId, bool $onlyUsable = false): array
    {
        if (!$this->ready() || $layoutId <= 0) {
            return [];
        }
        $db = $this->route_base_query()
            ->select($this->route_select())
            ->where('r.layout_id', $layoutId);
        if ($onlyUsable) {
            $db->where('r.is_active', 1)
                ->where('c.is_active', 1)
                ->where('l.is_active', 1)
                ->where('c.connection_type', 'LOCAL_AGENT')
                ->where('c.python_port IS NOT NULL', null, false);
            if ($this->route_print_mode_supported()) {
                $db->where("COALESCE(r.print_mode, 'AUTO') != 'OFF'", null, false);
            }
        }
        return $db->order_by('r.event_code', 'ASC')->order_by('r.priority', 'ASC')->order_by('r.route_name', 'ASC')->get()->result_array();
    }

    /** Build the exact payload used by the live preview before a layout is saved. */
    public function preview_layout_payload(array $data): array
    {
        $documentType = strtoupper(trim((string)($data['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $documentType = 'RECEIPT';
        }
        $general = (array)($this->general_settings()['payload'] ?? []);
        $storagePayload = $this->layout_input_payload($data, $documentType, $general);
        $ci = &get_instance();
        if (!isset($ci->posprinterpreviewservice)) {
            $ci->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        }
        return $ci->posprinterpreviewservice->decodePayload($storagePayload, $documentType, $general);
    }

    public function layout_payload(array $row, array $general = []): array
    {
        $documentType = strtoupper(trim((string)($row['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            $documentType = 'RECEIPT';
        }
        $payload = $this->decode_payload((string)($row['layout_payload'] ?? '{}'));
        foreach ($this->general_only_payload_keys() as $key) {
            unset($payload[$key]);
        }
        $ci = &get_instance();
        if (!isset($ci->posprinterpreviewservice)) {
            $ci->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        }
        return $ci->posprinterpreviewservice->decodePayload($payload, $documentType, $general);
    }

    public function save_layout(array $data): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Fondasi konfigurasi printer belum tersedia.'];
        }
        $id = max(0, (int)($data['id'] ?? 0));
        $name = trim((string)($data['layout_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama layout wajib diisi.'];
        }
        $documentType = strtoupper(trim((string)($data['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            return ['ok' => false, 'message' => 'Jenis dokumen layout tidak valid.'];
        }
        $code = strtoupper(trim((string)($data['layout_code'] ?? '')));
        if ($code === '') {
            $code = $this->named_code('pos_print_layout', 'layout_code', $name, 'LAYOUT-', $id, 60);
        } elseif ($this->code_exists('pos_print_layout', 'layout_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode layout sudah dipakai.'];
        }
        $general = (array)($this->general_settings()['payload'] ?? []);
        $payload = $this->layout_input_payload($data, $documentType, $general);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'message' => 'Pilihan tampilan layout tidak valid.'];
        }
        $record = [
            'layout_code' => $code,
            'layout_name' => $name,
            'document_type' => $documentType,
            'layout_payload' => $json,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        $this->db->trans_begin();
        try {
            if (!empty($record['is_default'])) {
                $this->db->where('document_type', $documentType)->update('pos_print_layout', ['is_default' => 0]);
            }
            if ($id > 0) {
                $this->db->where('id', $id)->update('pos_print_layout', $record);
            } else {
                $this->db->insert('pos_print_layout', $record);
                $id = (int)$this->db->insert_id();
            }
            if ($id <= 0 || $this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan layout cetak.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_layout(int $id): array
    {
        $row = $this->find_layout($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Layout cetak tidak ditemukan.'];
        }
        $active = (int)($row['is_active'] ?? 0) === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('pos_print_layout', ['is_active' => $active]);
        return ['ok' => true, 'id' => $id, 'is_active' => $active];
    }

    public function route_rows(array $filters = []): array
    {
        if (!$this->ready()) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $event = strtoupper(trim((string)($filters['event_code'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(5, min(100, (int)($filters['limit'] ?? 25)));
        $db = $this->route_base_query();
        if ($q !== '') {
            $db->group_start()->like('r.route_code', $q)->or_like('r.route_name', $q)->or_like('c.connection_name', $q)->or_like('l.layout_name', $q)->group_end();
        }
        if ($status === 'ACTIVE') {
            if ($this->route_print_mode_supported()) {
                $db->where("COALESCE(r.print_mode, 'AUTO') != 'OFF'", null, false);
            } else {
                $db->where('r.is_active', 1);
            }
        } elseif ($status === 'INACTIVE') {
            if ($this->route_print_mode_supported()) {
                $db->where("COALESCE(r.print_mode, 'AUTO') = 'OFF'", null, false);
            } else {
                $db->where('r.is_active', 0);
            }
        }
        if (in_array($event, self::EVENT_TYPES, true)) {
            $db->where('r.event_code', $event);
        }
        $connectionOutletId = max(0, (int)($filters['connection_outlet_id'] ?? 0));
        if ($connectionOutletId > 0) {
            $db->where('c.outlet_id', $connectionOutletId);
        }
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        if ($outletId > 0) {
            $db->group_start()->where('r.outlet_id', $outletId)->or_where('r.outlet_id IS NULL', null, false)->group_end();
        }
        $terminalId = max(0, (int)($filters['terminal_id'] ?? 0));
        if ($terminalId > 0) {
            $db->group_start()->where('r.terminal_id', $terminalId)->or_where('r.terminal_id IS NULL', null, false)->group_end();
        }
        if (!empty($filters['runtime_ready'])) {
            $db->where('c.is_active', 1)
                ->where('l.is_active', 1)
                ->where('c.connection_type', 'LOCAL_AGENT')
                ->where('c.python_port IS NOT NULL', null, false);
        }
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $pages] = $this->paginate($total, $page, $limit);
        $rows = $db->select($this->route_select())
            ->order_by('r.event_code', 'ASC')->order_by('r.priority', 'ASC')->order_by('r.route_name', 'ASC')
            ->limit($limit, $offset)->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $pages]];
    }

    public function find_mobile_test_route(int $connectionId, int $outletId, int $terminalId): ?array
    {
        if (!$this->routes_enabled() || $connectionId <= 0 || $outletId <= 0 || $terminalId <= 0) {
            return null;
        }
        $db = $this->route_base_query()
            ->select($this->route_select())
            ->where('r.connection_id', $connectionId)
            ->where('r.is_active', 1)
            ->where('c.is_active', 1)
            ->where('c.outlet_id', $outletId)
            ->where('l.is_active', 1)
            ->where('c.connection_type', 'LOCAL_AGENT')
            ->where('c.python_port IS NOT NULL', null, false)
            ->group_start()->where('r.outlet_id', $outletId)->or_where('r.outlet_id IS NULL', null, false)->group_end()
            ->group_start()->where('r.terminal_id', $terminalId)->or_where('r.terminal_id IS NULL', null, false)->group_end();
        if ($this->route_print_mode_supported()) {
            $db->where("COALESCE(r.print_mode, 'AUTO') != 'OFF'", null, false);
        }
        return $db
            ->order_by('CASE WHEN r.outlet_id IS NULL THEN 0 ELSE 1 END', 'DESC', false)
            ->order_by('CASE WHEN r.terminal_id IS NULL THEN 0 ELSE 1 END', 'DESC', false)
            ->order_by('r.priority', 'ASC')
            ->order_by('r.id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function find_route(int $id): ?array
    {
        if (!$this->ready() || $id <= 0) {
            return null;
        }
        return $this->route_base_query()->select($this->route_select())->where('r.id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function save_route(array $data): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Fondasi konfigurasi printer belum tersedia.'];
        }
        $id = max(0, (int)($data['id'] ?? 0));
        $name = trim((string)($data['route_name'] ?? ''));
        $event = strtoupper(trim((string)($data['event_code'] ?? '')));
        $documentType = strtoupper(trim((string)($data['document_type'] ?? '')));
        $connectionId = max(0, (int)($data['connection_id'] ?? 0));
        $layoutId = max(0, (int)($data['layout_id'] ?? 0));
        if ($name === '' || !in_array($event, self::EVENT_TYPES, true) || !in_array($documentType, self::DOCUMENT_TYPES, true) || $connectionId <= 0 || $layoutId <= 0) {
            return ['ok' => false, 'message' => 'Nama, event, dokumen, koneksi, dan layout wajib dipilih.'];
        }
        $connection = $this->find_connection($connectionId);
        $layout = $this->find_layout($layoutId);
        if (!$connection || !$layout) {
            return ['ok' => false, 'message' => 'Koneksi atau layout yang dipilih tidak tersedia.'];
        }
        if (strtoupper((string)$layout['document_type']) !== $documentType) {
            return ['ok' => false, 'message' => 'Jenis dokumen route harus sama dengan jenis dokumen layout.'];
        }
        $printMode = $this->enum_value($data['print_mode'] ?? (!empty($data['is_active']) ? 'AUTO' : 'OFF'), ['OFF', 'AUTO', 'ASK'], 'AUTO');
        $willPrint = $printMode !== 'OFF';
        if ($willPrint && ((int)($connection['is_active'] ?? 0) !== 1 || (int)($layout['is_active'] ?? 0) !== 1)) {
            return ['ok' => false, 'message' => 'Aturan aktif hanya dapat memakai koneksi dan layout yang sama-sama aktif.'];
        }
        if ($willPrint && strtoupper((string)($connection['connection_type'] ?? '')) !== 'LOCAL_AGENT') {
            return ['ok' => false, 'message' => 'Koneksi produksi saat ini harus menggunakan Local Agent yang sudah didukung aplikasi.'];
        }
        $code = strtoupper(trim((string)($data['route_code'] ?? '')));
        if ($code === '') {
            $code = $this->named_code('pos_print_route', 'route_code', $name, 'ROUTE-', $id, 80);
        } elseif ($this->code_exists('pos_print_route', 'route_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode aturan cetak sudah dipakai.'];
        }
        $scope = $this->enum_value($data['content_scope'] ?? 'ALL_ITEMS', ['MATCHED_DIVISION', 'ALL_ITEMS'], 'ALL_ITEMS');
        if ($scope === 'MATCHED_DIVISION' && max(0, (int)($data['operational_division_id'] ?? 0)) <= 0 && max(0, (int)($data['product_division_id'] ?? 0)) <= 0) {
            return ['ok' => false, 'message' => 'Pilih divisi operasional atau divisi produk jika isi cetak hanya untuk divisi tertentu.'];
        }
        $record = [
            'route_code' => $code,
            'route_name' => $name,
            'event_code' => $event,
            'document_type' => $documentType,
            'outlet_id' => $this->nullable_id($data['outlet_id'] ?? 0),
            'terminal_id' => $this->nullable_id($data['terminal_id'] ?? 0),
            'operational_division_id' => $this->nullable_id($data['operational_division_id'] ?? 0),
            'product_division_id' => $this->nullable_id($data['product_division_id'] ?? 0),
            'content_scope' => $scope,
            'connection_id' => $connectionId,
            'layout_id' => $layoutId,
            'copy_count' => max(0, min(10, (int)($data['copy_count'] ?? 0))),
            'priority' => max(1, min(9999, (int)($data['priority'] ?? 100))),
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'is_active' => $willPrint ? 1 : 0,
        ];
        if ($this->route_print_mode_supported()) {
            $record['print_mode'] = $printMode;
        }
        if ((int)$record['is_active'] === 1 && $this->has_active_route_conflict($record, $id)) {
            return ['ok' => false, 'message' => 'Sudah ada aturan cetak aktif dengan kejadian dan cakupan yang sama. Edit atau nonaktifkan aturan lama agar satu kejadian tidak tercetak dua kali.'];
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('pos_print_route', $record);
            return ['ok' => true, 'id' => $id];
        }
        $this->db->insert('pos_print_route', $record);
        return $this->db->insert_id() > 0 ? ['ok' => true, 'id' => (int)$this->db->insert_id()] : ['ok' => false, 'message' => 'Gagal menyimpan aturan cetak.'];
    }

    public function toggle_route(int $id): array
    {
        $row = $this->find_route($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Aturan cetak tidak ditemukan.'];
        }
        $nextMode = strtoupper((string)($row['print_mode'] ?? ((int)($row['is_active'] ?? 0) === 1 ? 'AUTO' : 'OFF')));
        $nextMode = $nextMode === 'OFF' ? 'AUTO' : 'OFF';
        $active = $nextMode === 'OFF' ? 0 : 1;
        if ($active === 1) {
            if ((int)($row['connection_active'] ?? 0) !== 1 || (int)($row['layout_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Aturan tidak dapat diaktifkan karena koneksi printer atau layout-nya sedang nonaktif.'];
            }
            if (strtoupper((string)($row['connection_type'] ?? '')) !== 'LOCAL_AGENT' || max(0, (int)($row['python_port'] ?? 0)) <= 0) {
                return ['ok' => false, 'message' => 'Aturan aktif membutuhkan koneksi Local Agent dengan port agent yang valid.'];
            }
            if ($this->has_active_route_conflict($row, $id)) {
                return ['ok' => false, 'message' => 'Sudah ada aturan cetak aktif dengan kejadian dan cakupan yang sama. Nonaktifkan aturan yang lama terlebih dahulu.'];
            }
        }
        $update = ['is_active' => $active];
        if ($this->route_print_mode_supported()) {
            $update['print_mode'] = $nextMode;
        }
        $this->db->where('id', $id)->update('pos_print_route', $update);
        return ['ok' => true, 'id' => $id, 'is_active' => $active, 'print_mode' => $nextMode];
    }

    /** Returns routes in the exact order the runtime will use. */
    public function active_routes(string $eventCode, int $outletId = 0, int $terminalId = 0): array
    {
        if (!$this->routes_enabled()) {
            return [];
        }
        $eventCode = strtoupper(trim($eventCode));
        $db = $this->route_base_query()
            ->select($this->route_select())
            ->where('r.event_code', $eventCode)
            ->where('r.is_active', 1)
            ->where('c.is_active', 1)
            ->where('l.is_active', 1)
            ->where('c.connection_type', 'LOCAL_AGENT')
            ->where('c.python_port IS NOT NULL', null, false);
        if ($this->route_print_mode_supported()) {
            $db->where("COALESCE(r.print_mode, 'AUTO') != 'OFF'", null, false);
        }
        if ($outletId > 0) {
            $db->group_start()->where('r.outlet_id', $outletId)->or_where('r.outlet_id IS NULL', null, false)->group_end();
        } else {
            $db->where('r.outlet_id IS NULL', null, false);
        }
        if ($terminalId > 0) {
            $db->group_start()->where('r.terminal_id', $terminalId)->or_where('r.terminal_id IS NULL', null, false)->group_end();
        } else {
            $db->where('r.terminal_id IS NULL', null, false);
        }
        return $db
            ->order_by('CASE WHEN r.outlet_id IS NULL THEN 0 ELSE 1 END', 'DESC', false)
            ->order_by('CASE WHEN r.terminal_id IS NULL THEN 0 ELSE 1 END', 'DESC', false)
            ->order_by('r.priority', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function runtime_template(array $route): array
    {
        $general = (array)($this->general_settings((int)($route['outlet_id'] ?? 0))['payload'] ?? []);
        $layout = [
            'document_type' => (string)($route['document_type'] ?? 'RECEIPT'),
            'layout_payload' => (string)($route['layout_payload'] ?? '{}'),
        ];
        return [
            'document_type' => strtoupper((string)($route['document_type'] ?? 'RECEIPT')),
            'row' => [
                'id' => (int)($route['layout_id'] ?? 0),
                'layout_code' => (string)($route['layout_code'] ?? ''),
                'layout_name' => (string)($route['layout_name'] ?? ''),
            ],
            'payload' => $this->layout_payload($layout, $general),
        ];
    }

    public function create_attempt(array $data): int
    {
        if (!$this->ready()) {
            return 0;
        }
        $routeId = max(0, (int)($data['route_id'] ?? 0));
        $prefix = date('YmdHis') . '-' . max(0, $routeId) . '-';
        for ($try = 0; $try < 3; $try++) {
            $attemptNo = 'PRT-' . $prefix . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $record = [
                'attempt_no' => $attemptNo,
                'event_code' => strtoupper(trim((string)($data['event_code'] ?? 'OTHER'))),
                'document_type' => strtoupper(trim((string)($data['document_type'] ?? 'RECEIPT'))),
                'attempt_kind' => $this->enum_value($data['attempt_kind'] ?? 'AUTO', ['AUTO', 'REPRINT', 'TEST', 'PREVIEW'], 'AUTO'),
                'status' => 'GENERATED',
                'route_id' => $this->nullable_id($data['route_id'] ?? 0),
                'connection_id' => $this->nullable_id($data['connection_id'] ?? 0),
                'layout_id' => $this->nullable_id($data['layout_id'] ?? 0),
                'outlet_id' => $this->nullable_id($data['outlet_id'] ?? 0),
                'terminal_id' => $this->nullable_id($data['terminal_id'] ?? 0),
                'order_id' => $this->nullable_id($data['order_id'] ?? 0),
                'payment_id' => $this->nullable_id($data['payment_id'] ?? 0),
                'void_id' => $this->nullable_id($data['void_id'] ?? 0),
                'refund_id' => $this->nullable_id($data['refund_id'] ?? 0),
                'target_summary' => json_encode([
                    'connection_name' => (string)($data['connection_name'] ?? ''),
                    'connection_code' => (string)($data['connection_code'] ?? ''),
                    'route_name' => (string)($data['route_name'] ?? ''),
                    'route_code' => (string)($data['route_code'] ?? ''),
                    'layout_name' => (string)($data['layout_name'] ?? ''),
                    'layout_code' => (string)($data['layout_code'] ?? ''),
                    'document_type' => (string)($data['document_type'] ?? ''),
                    'copy_count' => max(1, (int)($data['copy_count'] ?? 1)),
                    'line_count' => max(0, (int)($data['line_count'] ?? 0)),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $previous = $this->db->db_debug;
            $this->db->db_debug = false;
            $ok = $this->db->insert('pos_print_attempt', $record);
            $this->db->db_debug = $previous;
            if ($ok) {
                return (int)$this->db->insert_id();
            }
        }
        return 0;
    }

    public function acknowledge_attempt(int $attemptId, string $status, string $message = '', int $employeeId = 0): array
    {
        if (!$this->ready() || $attemptId <= 0) {
            return ['ok' => false, 'message' => 'Riwayat cetak tidak ditemukan.'];
        }
        $status = $this->enum_value($status, ['SENT', 'FAILED', 'SKIPPED'], 'FAILED');
        $row = $this->db->from('pos_print_attempt')->where('id', $attemptId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Riwayat cetak tidak ditemukan.'];
        }
        $record = [
            'status' => $status,
            'agent_message' => mb_substr(trim($message), 0, 500),
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledged_by' => $employeeId > 0 ? $employeeId : null,
        ];
        if ($status === 'SENT') {
            $record['sent_at'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $attemptId)->update('pos_print_attempt', $record);
        return ['ok' => true, 'id' => $attemptId, 'status' => $status];
    }

    public function attempt_rows(array $filters = []): array
    {
        if (!$this->ready()) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $q = trim((string)($filters['q'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(5, min(100, (int)($filters['limit'] ?? 25)));
        $db = $this->db->from('pos_print_attempt a')
            ->join('pos_print_route r', 'r.id = a.route_id', 'left')
            ->join('pos_print_connection c', 'c.id = a.connection_id', 'left')
            ->join('pos_print_layout l', 'l.id = a.layout_id', 'left')
            ->join('pos_order o', 'o.id = a.order_id', 'left');
        if (in_array($status, ['GENERATED', 'SENT', 'FAILED', 'SKIPPED', 'VOID'], true)) {
            $db->where('a.status', $status);
        }
        if ($q !== '') {
            $db->group_start()->like('a.attempt_no', $q)->or_like('a.event_code', $q)->or_like('o.order_no', $q)
                ->or_like('r.route_name', $q)->or_like('c.connection_name', $q)->group_end();
        }
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $pages] = $this->paginate($total, $page, $limit);
        $rows = $db->select('a.*, r.route_code, r.route_name, c.connection_name, c.agent_printer_code, l.layout_name, o.order_no')
            ->order_by('a.requested_at', 'DESC')->order_by('a.id', 'DESC')->limit($limit, $offset)->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $pages]];
    }

    public function agent_devices(string $agentHost = ''): array
    {
        if (!$this->agent_connection_ready()) {
            return [];
        }
        $agentHost = strtoupper(trim($agentHost));
        $db = $this->db->from('pos_print_connection c')
            ->join('pos_outlet o', 'o.id = c.outlet_id', 'left')
            ->where('c.is_active', 1)
            ->where('c.connection_type', 'LOCAL_AGENT')
            ->where("TRIM(COALESCE(c.mac_address, '')) <> ''", null, false)
            ->where('c.python_port IS NOT NULL', null, false);
        if ($agentHost !== '') {
            $escaped = $this->db->escape($agentHost);
            $db->group_start()->where("UPPER(COALESCE(c.agent_host, '')) = {$escaped}", null, false)
                ->or_where("TRIM(COALESCE(c.agent_host, '')) = ''", null, false)->group_end();
        }
        return $db->select("c.id, c.agent_printer_code AS printer_code, c.connection_name AS printer_name,
                COALESCE(c.location_label, 'CUSTOM') AS printer_role, 'DATABASE_ROUTE' AS print_scope,
                c.agent_host, c.mac_address, c.python_port, c.paper_width_mm, c.chars_per_line,
                c.default_copy_count AS copies, c.open_drawer, o.outlet_name", false)
            ->order_by('o.outlet_name', 'ASC')->order_by('c.python_port', 'ASC')->order_by('c.connection_name', 'ASC')
            ->get()->result_array();
    }

    private function route_base_query()
    {
        return $this->db->from('pos_print_route r')
            ->join('pos_print_connection c', 'c.id = r.connection_id', 'inner')
            ->join('pos_print_layout l', 'l.id = r.layout_id', 'inner')
            ->join('pos_outlet o', 'o.id = r.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = r.terminal_id', 'left')
            ->join('mst_operational_division od', 'od.id = r.operational_division_id', 'left')
            ->join('mst_product_division pd', 'pd.id = r.product_division_id', 'left');
    }

    private function route_select(): string
    {
        return 'r.*, c.connection_code, c.connection_name, c.connection_type, c.agent_host, c.agent_printer_code, c.python_port, c.paper_width_mm, c.chars_per_line, c.default_copy_count, c.cut_mode, c.open_drawer, c.location_label, c.legacy_printer_id, c.is_active AS connection_active, l.layout_code, l.layout_name, l.layout_payload, l.is_active AS layout_active, o.outlet_name, t.terminal_name, od.name AS operational_division_name, pd.name AS product_division_name';
    }

    private function layout_input_payload(array $data, string $documentType, array $general): array
    {
        $ci = &get_instance();
        if (!isset($ci->posprinterpreviewservice)) {
            $ci->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        }
        $payload = $ci->posprinterpreviewservice->defaultPayload($documentType, $general);
        foreach ($this->general_only_payload_keys() as $key) {
            unset($payload[$key]);
        }
        $booleanKeys = [
            'show_logo', 'show_header', 'show_invoice_no', 'show_payment_no', 'show_customer', 'show_table_no', 'show_order_time',
            'show_payment_time', 'show_cashier_order', 'show_cashier_payment', 'show_product_name', 'show_qty', 'show_extra', 'show_notes',
            'show_order_notes', 'show_subtotal', 'show_payment_breakdown', 'show_discount', 'show_compliment', 'show_deposit_applied',
            'show_grand_total', 'show_paid_amount', 'show_balance_due', 'show_void_reason', 'show_refund_reason', 'show_footer',
            'show_price', 'show_footer_barcode', 'show_wifi_info', 'show_customer_point_info', 'show_customer_stamp_info',
            'show_customer_voucher', 'show_customer_review_qr',
        ];
        foreach ($booleanKeys as $key) {
            $payload[$key] = !empty($data[$key]) ? 1 : 0;
        }
        $payload['header_align'] = $this->enum_value($data['header_align'] ?? 'CENTER', ['LEFT', 'CENTER', 'RIGHT'], 'CENTER');
        $payload['footer_align'] = $this->enum_value($data['footer_align'] ?? 'CENTER', ['LEFT', 'CENTER', 'RIGHT'], 'CENTER');
        $payload['footer_barcode_source'] = $this->enum_value($data['footer_barcode_source'] ?? 'ORDER_NO', ['ORDER_NO', 'PAYMENT_NO', 'VOID_NO', 'REFUND_NO', 'VOUCHER_CODE', 'CUSTOM'], 'ORDER_NO');
        $payload['footer_barcode_custom'] = trim((string)($data['footer_barcode_custom'] ?? ''));
        return $payload;
    }

    private function general_only_payload_keys(): array
    {
        return ['title', 'subtitle', 'logo_url', 'wifi_name', 'wifi_password', 'header_lines', 'footer_lines', 'customer_voucher_limit', 'customer_voucher_message_template', 'customer_voucher_align', 'customer_review_qr_enabled', 'customer_review_message'];
    }

    private function decode_payload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function route_print_mode_supported(): bool
    {
        return $this->ready() && $this->db->field_exists('print_mode', 'pos_print_route');
    }

    private function lines($value): array
    {
        if (!is_array($value)) {
            $value = preg_split('/\r?\n/', trim((string)$value));
        }
        return array_values(array_filter(array_map(static function ($line): string {
            return trim((string)$line);
        }, $value), static function (string $line): bool {
            return $line !== '';
        }));
    }

    private function enum_value($value, array $allowed, string $default): string
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nullable_id($value): ?int
    {
        $value = max(0, (int)$value);
        return $value > 0 ? $value : null;
    }

    private function nullable_positive_int($value): ?int
    {
        $value = max(0, (int)$value);
        return $value > 0 ? $value : null;
    }

    private function named_code(string $table, string $field, string $name, string $prefix, int $excludeId, int $length): string
    {
        $slug = strtoupper((string)preg_replace('/[^A-Z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'CONFIG';
        }
        $base = substr($prefix . $slug, 0, max(1, $length - 5));
        $code = $base;
        $counter = 2;
        while ($this->code_exists($table, $field, $code, $excludeId)) {
            $suffix = '-' . $counter;
            $code = substr($base, 0, $length - strlen($suffix)) . $suffix;
            $counter++;
        }
        return $code;
    }

    private function code_exists(string $table, string $field, string $code, int $excludeId = 0): bool
    {
        $db = $this->db->from($table)->where($field, $code);
        if ($excludeId > 0) {
            $db->where('id !=', $excludeId);
        }
        return (int)$db->count_all_results() > 0;
    }

    /**
     * Satu event dengan filter sumber yang persis sama harus punya satu route
     * aktif. Jumlah salinan diatur oleh copy_count, bukan lewat route ganda.
     */
    private function has_active_route_conflict(array $record, int $excludeId = 0): bool
    {
        $db = $this->db->from('pos_print_route')
            ->where('is_active', 1)
            ->where('event_code', strtoupper((string)($record['event_code'] ?? '')))
            ->where('content_scope', strtoupper((string)($record['content_scope'] ?? 'ALL_ITEMS')));
        if ($excludeId > 0) {
            $db->where('id !=', $excludeId);
        }
        foreach (['outlet_id', 'terminal_id', 'operational_division_id', 'product_division_id'] as $field) {
            $value = $this->nullable_id($record[$field] ?? 0);
            if ($value === null) {
                $db->where($field . ' IS NULL', null, false);
            } else {
                $db->where($field, $value);
            }
        }
        return (int)$db->count_all_results() > 0;
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $pages = max(1, (int)ceil($total / max(1, $limit)));
        $page = min(max(1, $page), $pages);
        return [$page, ($page - 1) * $limit, $pages];
    }
}
