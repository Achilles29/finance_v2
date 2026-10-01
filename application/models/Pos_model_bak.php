<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_model extends CI_Model
{
    /** @var CI_DB_query_builder */
    protected $coredb;

    /** @var bool */
    protected $posOrderCustomerNameReady = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('member_phone');
        $this->coredb = $this->resolveCoreDatabase();
    }

    private function resolveCoreDatabase()
    {
        if (isset($this->db) && $this->db instanceof CI_DB_query_builder) {
            return $this->db;
        }

        return $this->load->database('default', true);
    }

    /** Resolve the single printer configuration source used by POS runtime. */
    private function printer_config_model(): ?Pos_print_model
    {
        // CI3 loads models on the controller super-object. Accessing it with
        // isset() from another model always returns false because CI_Model
        // proxies properties through __get() only.
        $ci =& get_instance();
        if (!isset($ci->Pos_print_model) || !($ci->Pos_print_model instanceof Pos_print_model)) {
            $ci->load->model('Pos_print_model');
        }
        return isset($ci->Pos_print_model) && $ci->Pos_print_model instanceof Pos_print_model
            ? $ci->Pos_print_model
            : null;
    }

    /** Customer-review table is optional until the printer/review migration runs. */
    private function customer_review_model(): ?Pos_customer_review_model
    {
        if (!$this->db->table_exists('pos_customer_review')) {
            return null;
        }
        // See printer_config_model(): models loaded from a model must be read
        // from CI's controller super-object rather than $this.
        $ci =& get_instance();
        if (!isset($ci->Pos_customer_review_model) || !($ci->Pos_customer_review_model instanceof Pos_customer_review_model)) {
            $ci->load->model('Pos_customer_review_model');
        }
        return isset($ci->Pos_customer_review_model) && $ci->Pos_customer_review_model instanceof Pos_customer_review_model
            ? $ci->Pos_customer_review_model
            : null;
    }

    private function empty_printer_rows(array $filters = []): array
    {
        $limit = max(1, min(100, (int)($filters['limit'] ?? 25)));
        return [
            'rows' => [],
            'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1],
        ];
    }

    private function retired_printer_configuration_response(string $destination = ''): array
    {
        $message = 'Pengaturan printer lama sudah dihentikan.';
        if ($destination !== '') {
            $message .= ' Gunakan halaman ' . $destination . '.';
        }
        return ['ok' => false, 'message' => $message];
    }

    private function printer_runtime_not_ready(string $action): array
    {
        return [
            'ok' => false,
            'message' => 'Konfigurasi printer belum siap untuk ' . $action . '. Periksa Koneksi Printer, Layout Dokumen, dan Aturan Cetak.',
        ];
    }

    public function member_filter_options(): array
    {
        return [
            'tiers' => $this->distinct_non_empty_values($this->coredb, 'crm_member', 'member_tier'),
        ];
    }

    public function member_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $memberStatus = strtoupper(trim((string)($filters['member_status'] ?? 'ALL')));
        $tier = trim((string)($filters['tier'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('crm_member m');

        if ($q !== '') {
            $db->group_start()
                ->like('m.member_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->or_like('m.email', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('m.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('m.is_active', 0);
        }
        if (in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $db->where('m.member_status', $memberStatus);
        }
        if ($tier !== '') {
            $db->where('m.member_tier', $tier);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
            m.member_no,
            m.id,
            m.member_name,
            m.mobile_phone,
            m.email,
            m.birth_date,
            m.gender,
            m.address,
            m.city,
            m.postal_code,
            m.notes,
            m.is_active,
            m.member_tier,
            m.joined_at,
            m.expired_at,
            m.member_status
        ')
            ->order_by('m.member_name', 'ASC')
            ->order_by('m.member_no', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_member(int $id): ?array
    {
        return $this->coredb->from('crm_member m')
            ->select('
                m.member_no,
                m.id,
                m.member_name,
                m.mobile_phone,
                m.email,
                m.birth_date,
                m.gender,
                m.address,
                m.city,
                m.postal_code,
                m.notes,
                m.is_active,
                m.member_tier,
                m.joined_at,
                m.expired_at,
                m.member_status
            ')
            ->where('m.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_member(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['member_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama member wajib diisi.'];
        }

        $memberStatus = strtoupper(trim((string)($data['member_status'] ?? 'ACTIVE')));
        if (!in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $memberStatus = 'ACTIVE';
        }
        $gender = strtoupper(trim((string)($data['gender'] ?? '')));
        if (!in_array($gender, ['L', 'P'], true)) {
            $gender = null;
        }

        $db->trans_begin();
        try {
            $memberNo = strtoupper(trim((string)($data['member_no'] ?? '')));
            $joinedAt = $this->nullable_datetime($data['joined_at'] ?? '') ?: date('Y-m-d H:i:s');
            $mobilePhoneInput = trim((string)($data['mobile_phone'] ?? ''));
            $mobilePhone = member_phone_normalize($mobilePhoneInput);
            $payload = [
                'member_name' => $name,
                'mobile_phone' => $mobilePhone !== '' ? $mobilePhone : $this->nullable_text($mobilePhoneInput),
                'email' => $this->nullable_text($data['email'] ?? ''),
                'birth_date' => $this->nullable_date($data['birth_date'] ?? ''),
                'gender' => $gender,
                'address' => $this->nullable_text($data['address'] ?? ''),
                'city' => $this->nullable_text($data['city'] ?? ''),
                'postal_code' => $this->nullable_text($data['postal_code'] ?? ''),
                'member_tier' => $this->nullable_text($data['member_tier'] ?? ''),
                'joined_at' => $joinedAt,
                'expired_at' => $this->nullable_datetime($data['expired_at'] ?? ''),
                'member_status' => $memberStatus,
                'notes' => $this->nullable_text($data['notes'] ?? ''),
                'is_active' => 1,
            ];

            $phoneMatches = $mobilePhone !== ''
                ? $this->member_phone_matches($db, $mobilePhone, $id)
                : [];
            if ($id > 0 && !empty($phoneMatches)) {
                throw new RuntimeException($this->member_phone_registered_message($phoneMatches[0], $mobilePhone));
            }
            if ($id <= 0 && !empty($phoneMatches)) {
                foreach ($phoneMatches as $match) {
                    if (member_names_match($name, (string)($match['member_name'] ?? ''))) {
                        $db->trans_commit();
                        return ['ok' => true, 'id' => (int)$match['id'], 'existing' => true];
                    }
                }
                throw new RuntimeException($this->member_phone_registered_message($phoneMatches[0], $mobilePhone));
            }

            if ($id > 0) {
                $existing = $this->find_member($id);
                if (!$existing) {
                    throw new RuntimeException('Member tidak ditemukan.');
                }
                if ($memberNo === '') {
                    $memberNo = (string)$existing['member_no'];
                }
                if ($this->code_exists($db, 'crm_member', 'member_no', $memberNo, $id)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                $db->where('id', $id)->update('crm_member', $payload);
            } else {
                if ($memberNo === '') {
                    $memberNo = $this->generate_member_no_core($joinedAt);
                } elseif ($this->code_exists($db, 'crm_member', 'member_no', $memberNo, 0)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                $db->insert('crm_member', $payload);
                $id = (int)$db->insert_id();
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan member.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_member(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_member($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->trans_begin();
        try {
            $memberStatus = strtoupper((string)($row['member_status'] ?? 'ACTIVE'));
            if ($newValue === 0 && $memberStatus === 'ACTIVE') {
                $memberStatus = 'CLOSED';
            } elseif ($newValue === 1 && $memberStatus === 'CLOSED') {
                $memberStatus = 'ACTIVE';
            }
            $db->where('id', $id)->update('crm_member', ['is_active' => $newValue, 'member_status' => $memberStatus]);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status member.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function payment_method_filter_options(): array
    {
        return [
            'accounts' => $this->active_company_accounts(),
        ];
    }

    public function sales_channel_filter_options(): array
    {
        return [
            'service_types' => $this->sales_channel_service_type_options(),
        ];
    }

    public function sales_channel_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $serviceType = strtoupper(trim((string)($filters['service_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        if (!$this->db->table_exists('pos_sales_channel')) {
            return [
                'rows' => [],
                'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1],
            ];
        }

        $db = $this->coredb;
        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $hasSortOrder = $this->db->field_exists('sort_order', 'pos_sales_channel');
        $selectAllowedTypes = $hasAllowedTypes ? 'sc.allowed_service_types' : "'' AS allowed_service_types";
        $selectSortOrder = $hasSortOrder ? 'sc.sort_order' : '0 AS sort_order';
        $db->from('pos_sales_channel sc');
        if ($q !== '') {
            $db->group_start()
                ->like('sc.channel_code', $q)
                ->or_like('sc.channel_name', $q)
                ->or_like('sc.notes', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('sc.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('sc.is_active', 0);
        }
        if (in_array($serviceType, $this->sales_channel_service_type_options(), true)) {
            $db->group_start()
                ->where('sc.service_type_default', $serviceType);
            if ($hasAllowedTypes) {
                $db->or_like('sc.allowed_service_types', $serviceType);
            }
            $db
                ->group_end();
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
            sc.id,
            sc.channel_code,
            sc.channel_name,
            sc.service_type_default,
            ' . $selectAllowedTypes . ',
            sc.marketplace_fee_percent,
            sc.is_default,
            sc.is_active,
            ' . $selectSortOrder . ',
            sc.notes
        ')
            ->order_by('sc.is_default', 'DESC')
            ->order_by($hasSortOrder ? 'sc.sort_order' : 'sc.id', 'ASC')
            ->order_by('sc.channel_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
            $row['allowed_service_type_list'] = $allowedRaw !== ''
                ? $this->explode_service_types($allowedRaw)
                : [$row['service_type_default'] ?? 'DINE_IN'];
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_sales_channel(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('pos_sales_channel')) {
            return null;
        }

        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $hasSortOrder = $this->db->field_exists('sort_order', 'pos_sales_channel');
        $row = $this->coredb
            ->from('pos_sales_channel sc')
            ->select('
                sc.id,
                sc.channel_code,
                sc.channel_name,
                sc.service_type_default,
                ' . ($hasAllowedTypes ? 'sc.allowed_service_types' : "'' AS allowed_service_types") . ',
                sc.marketplace_fee_percent,
                sc.is_default,
                sc.is_active,
                ' . ($hasSortOrder ? 'sc.sort_order' : '0 AS sort_order') . ',
                sc.notes
            ')
            ->where('sc.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }
        $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
        $row['allowed_service_type_list'] = $allowedRaw !== ''
            ? $this->explode_service_types($allowedRaw)
            : [$row['service_type_default'] ?? 'DINE_IN'];
        return $row;
    }

    public function save_sales_channel(array $data): array
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return ['ok' => false, 'message' => 'Schema sales channel POS belum tersedia. Jalankan migration sales channel terlebih dulu.'];
        }

        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['channel_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama sales channel wajib diisi.'];
        }

        $serviceTypeDefault = strtoupper(trim((string)($data['service_type_default'] ?? 'DINE_IN')));
        $allowedTypes = $this->normalize_allowed_service_types($data['allowed_service_types'] ?? []);
        if (empty($allowedTypes)) {
            return ['ok' => false, 'message' => 'Minimal pilih 1 service type yang diizinkan untuk channel ini.'];
        }
        if (!in_array($serviceTypeDefault, $allowedTypes, true)) {
            return ['ok' => false, 'message' => 'Service type default harus termasuk ke daftar service type yang diizinkan.'];
        }

        $db->trans_begin();
        try {
            $channelCode = strtoupper(trim((string)($data['channel_code'] ?? '')));
            $isDefault = !empty($data['is_default']) ? 1 : 0;
            $payload = [
                'channel_name' => $name,
                'service_type_default' => $serviceTypeDefault,
                'marketplace_fee_percent' => round(max(0, (float)($data['marketplace_fee_percent'] ?? 0)), 4),
                'is_default' => $isDefault,
                'is_active' => !isset($data['is_active']) || (int)$data['is_active'] === 1 ? 1 : 0,
                'notes' => $this->nullable_text($data['notes'] ?? ''),
            ];
            if ($this->db->field_exists('allowed_service_types', 'pos_sales_channel')) {
                $payload['allowed_service_types'] = implode(',', $allowedTypes);
            }
            if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
                $payload['sort_order'] = max(0, (int)($data['sort_order'] ?? 0));
            }

            if ($id > 0) {
                $existing = $this->find_sales_channel($id);
                if (!$existing) {
                    throw new RuntimeException('Sales channel tidak ditemukan.');
                }
                if ($channelCode === '') {
                    $channelCode = (string)$existing['channel_code'];
                }
                if ($this->code_exists($db, 'pos_sales_channel', 'channel_code', $channelCode, $id)) {
                    throw new RuntimeException('Kode sales channel sudah dipakai.');
                }
                $payload['channel_code'] = $channelCode;
                $db->where('id', $id)->update('pos_sales_channel', $payload);
            } else {
                if ($channelCode === '') {
                    $channelCode = $this->generate_pos_sales_channel_code($name);
                } elseif ($this->code_exists($db, 'pos_sales_channel', 'channel_code', $channelCode, 0)) {
                    throw new RuntimeException('Kode sales channel sudah dipakai.');
                }
                $payload['channel_code'] = $channelCode;
                $db->insert('pos_sales_channel', $payload);
                $id = (int)$db->insert_id();
            }

            if ($isDefault === 1) {
                $db->where('id !=', $id)->update('pos_sales_channel', ['is_default' => 0]);
            } else {
                $defaultCount = (int)$db->where('is_default', 1)->count_all_results('pos_sales_channel');
                if ($defaultCount === 0) {
                    $db->where('id', $id)->update('pos_sales_channel', ['is_default' => 1]);
                }
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_sales_channel(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_sales_channel($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Sales channel tidak ditemukan.'];
        }

        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->trans_begin();
        try {
            if ((int)($row['is_default'] ?? 0) === 1 && $newValue === 0) {
                throw new RuntimeException('Sales channel default tidak boleh dinonaktifkan sebelum ada default lain yang aktif.');
            }
            $db->where('id', $id)->update('pos_sales_channel', ['is_active' => $newValue]);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete_sales_channel(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_sales_channel($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Sales channel tidak ditemukan.'];
        }
        if ((int)($row['is_default'] ?? 0) === 1) {
            return ['ok' => false, 'message' => 'Sales channel default tidak boleh dihapus. Pindahkan default ke channel lain dulu.'];
        }
        if ($this->db->table_exists('pos_order') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $used = (int)$db->where('sales_channel_id', $id)->count_all_results('pos_order');
            if ($used > 0) {
                return ['ok' => false, 'message' => 'Sales channel sudah dipakai di transaksi POS. Nonaktifkan saja bila tidak ingin dipakai lagi.'];
            }
        }

        $db->trans_begin();
        try {
            $db->where('id', $id)->delete('pos_sales_channel');
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menghapus sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function self_order_settings(): array
    {
        $settings = [
            'is_enabled' => 1,
            'member_base_url' => $this->default_self_order_member_base_url(),
            'table_qr_secret' => '',
            'table_qr_enforce' => 1,
            'midtrans_is_enabled' => 0,
            'midtrans_server_key' => '',
            'midtrans_client_key' => '',
            'midtrans_is_production' => 0,
            'qris_payment_method_id' => 0,
            'qris_notes' => '',
        ];

        if ($this->db->table_exists('pos_self_order_setting')) {
            $row = $this->db->get_where('pos_self_order_setting', ['id' => 1])->row_array();
            if ($row) {
                $settings['is_enabled'] = (int)($row['is_enabled'] ?? 1);
                $settings['member_base_url'] = trim((string)($row['member_base_url'] ?? $settings['member_base_url']));
                $settings['qris_notes'] = (string)($row['notes'] ?? '');
            }
        }

        $qrSecretTable = $this->self_order_qr_secret_table();
        if ($qrSecretTable !== null) {
            $row = $this->db->get_where($qrSecretTable, ['id' => 1])->row_array();
            if ($row) {
                $settings['table_qr_secret'] = trim((string)($row['secret'] ?? ''));
                $settings['table_qr_enforce'] = (int)($row['enforce'] ?? 1);
            }
        }

        $qrisTable = $this->self_order_qris_table();
        if ($qrisTable !== null) {
            $row = $this->db->get_where($qrisTable, ['id' => 1])->row_array();
            if ($row) {
                $settings['midtrans_is_enabled'] = (int)($row['is_enabled'] ?? 0);
                $settings['midtrans_server_key'] = (string)($row['midtrans_server_key'] ?? '');
                $settings['midtrans_client_key'] = (string)($row['midtrans_client_key'] ?? '');
                $settings['midtrans_is_production'] = (int)($row['midtrans_is_production'] ?? 0);
                if (array_key_exists('payment_method_id', $row)) {
                    $settings['qris_payment_method_id'] = (int)($row['payment_method_id'] ?? 0);
                }
            }
        }

        $settings['member_base_url'] = $this->normalize_member_base_url($settings['member_base_url']);
        $settings['qr_scan_example'] = $settings['member_base_url'] . 'meja/{id}/{signature}';
        return $settings;
    }

    public function save_self_order_settings(array $data): array
    {
        $qrSecretTable = $this->self_order_qr_secret_table();
        $qrisTable = $this->self_order_qris_table();
        if (
            !$this->db->table_exists('pos_self_order_setting')
            || $qrSecretTable === null
            || $qrisTable === null
        ) {
            return ['ok' => false, 'message' => 'Schema self order belum lengkap. Jalankan migration self order terlebih dulu.'];
        }

        $db = $this->db;
        $memberBaseUrl = $this->normalize_member_base_url((string)($data['member_base_url'] ?? ''));
        $secret = trim((string)($data['table_qr_secret'] ?? ''));
        $qrisPaymentMethodId = max(0, (int)($data['qris_payment_method_id'] ?? 0));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(24));
        }

        if ($qrisPaymentMethodId > 0) {
            $method = $this->find_payment_method($qrisPaymentMethodId);
            if (!$method || (int)($method['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Metode pembayaran self order tidak ditemukan atau tidak aktif.'];
            }
            if ($this->db->table_exists('fin_company_account') && (int)($method['company_account_id'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Metode pembayaran self order wajib terhubung ke rekening perusahaan.'];
            }
        } elseif (!empty($data['midtrans_is_enabled'])) {
            return ['ok' => false, 'message' => 'Pilih metode pembayaran self order agar settlement masuk ke rekening yang benar.'];
        }

        $db->trans_begin();
        try {
            $db->replace('pos_self_order_setting', $this->filter_table_payload('pos_self_order_setting', [
                'id' => 1,
                'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
                'member_base_url' => $memberBaseUrl,
                'notes' => $this->nullable_text($data['qris_notes'] ?? ''),
            ]));

            $db->replace($qrSecretTable, $this->filter_table_payload($qrSecretTable, [
                'id' => 1,
                'secret' => $secret,
                'enforce' => !empty($data['table_qr_enforce']) ? 1 : 0,
            ]));

            $db->replace($qrisTable, $this->filter_table_payload($qrisTable, [
                'id' => 1,
                'is_enabled' => !empty($data['midtrans_is_enabled']) ? 1 : 0,
                'midtrans_server_key' => trim((string)($data['midtrans_server_key'] ?? '')),
                'midtrans_client_key' => trim((string)($data['midtrans_client_key'] ?? '')),
                'midtrans_is_production' => !empty($data['midtrans_is_production']) ? 1 : 0,
                'payment_method_id' => $qrisPaymentMethodId > 0 ? $qrisPaymentMethodId : null,
            ]));

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan pengaturan self order.');
            }

            $db->trans_commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function online_food_settings(): array
    {
        $settings = [
            'is_enabled' => 1,
            'open_mode' => 'MANUAL',
            'manual_status' => 'OPEN',
            'timezone' => 'Asia/Jakarta',
            'open_time' => '08:00',
            'close_time' => '22:00',
            'schedule_days' => ['1', '2', '3', '4', '5', '6', '0'],
            'allow_cod' => 1,
            'allow_qris' => 0,
            'payment_default' => 'MANUAL',
            'payment_auto_enabled' => 0,
            'payment_manual_enabled' => 1,
            'auto_payment_provider' => 'MIDTRANS',
            'midtrans_server_key' => '',
            'midtrans_client_key' => '',
            'midtrans_is_production' => 0,
            'qris_payment_method_id' => 0,
            'delivery_fee_mode' => 'DISTANCE',
            'delivery_fee_charge_mode' => 'CUSTOMER_TO_DRIVER',
            'delivery_flat_fee' => 0,
            'delivery_base_fee' => 5000,
            'delivery_base_km' => 2,
            'delivery_per_km_fee' => 2500,
            'delivery_min_fee' => 5000,
            'delivery_max_distance_km' => 10,
            'free_delivery_min_order' => 0,
            'free_delivery_distance_km' => 0,
            'packaging_fee_default' => 0,
            'min_order_amount' => 0,
            'outlet_lat' => '',
            'outlet_lng' => '',
            'manual_whatsapp_number' => '',
            'manual_whatsapp_template' => 'Halo admin, saya mau konfirmasi pesanan Online Food {order_no} dengan total Rp {total}. Mohon dibantu untuk metode pembayaran manual/COD dan estimasi pengantarannya.',
            'manual_payment_instructions' => 'Untuk pembayaran manual, hubungi admin melalui tombol WhatsApp. Setelah admin mengonfirmasi pesanan, kasir akan memproses order dan pembayaran dilakukan melalui POS.',
            'member_base_url' => $this->default_self_order_member_base_url(),
            'notes' => '',
            'payment_method_ids' => [],
        ];

        if ($this->db->table_exists('pos_online_food_setting')) {
            $row = $this->db->get_where('pos_online_food_setting', ['id' => 1])->row_array();
            if ($row) {
                foreach ($settings as $key => $default) {
                    if ($key === 'payment_method_ids' || !array_key_exists($key, $row)) {
                        continue;
                    }
                    $settings[$key] = $row[$key];
                }
                $settings['schedule_days'] = array_values(array_filter(array_map('trim', explode(',', (string)($row['schedule_days'] ?? ''))), static function ($value) {
                    return $value !== '';
                }));
            }
        }

        if ($this->db->table_exists('pos_online_food_payment_method')) {
            $rows = $this->db->select('payment_method_id')
                ->from('pos_online_food_payment_method')
                ->where('setting_id', 1)
                ->where('is_active', 1)
                ->get()
                ->result_array();
            $settings['payment_method_ids'] = array_map('intval', array_column($rows, 'payment_method_id'));
        }

        $settings['open_time'] = substr((string)($settings['open_time'] ?? '08:00'), 0, 5);
        $settings['close_time'] = substr((string)($settings['close_time'] ?? '22:00'), 0, 5);
        $settings['schedule_days_csv'] = implode(',', array_map('strval', (array)($settings['schedule_days'] ?? [])));
        $settings['member_base_url'] = $this->normalize_member_base_url((string)($settings['member_base_url'] ?? ''));
        $settings['online_order_url'] = $settings['member_base_url'] . 'online-order';
        return $settings;
    }

    public function save_online_food_settings(array $data): array
    {
        if (!$this->db->table_exists('pos_online_food_setting') || !$this->db->table_exists('pos_online_food_payment_method')) {
            return ['ok' => false, 'message' => 'Schema online food belum lengkap. Jalankan migration online food terlebih dulu.'];
        }

        $openMode = strtoupper(trim((string)($data['open_mode'] ?? 'MANUAL')));
        if (!in_array($openMode, ['MANUAL', 'SCHEDULE'], true)) {
            $openMode = 'MANUAL';
        }
        $manualStatus = strtoupper(trim((string)($data['manual_status'] ?? 'OPEN')));
        if (!in_array($manualStatus, ['OPEN', 'CLOSED'], true)) {
            $manualStatus = 'OPEN';
        }
        $deliveryFeeMode = strtoupper(trim((string)($data['delivery_fee_mode'] ?? 'DISTANCE')));
        if (!in_array($deliveryFeeMode, ['FLAT', 'DISTANCE'], true)) {
            $deliveryFeeMode = 'DISTANCE';
        }
        $deliveryFeeChargeMode = strtoupper(trim((string)($data['delivery_fee_charge_mode'] ?? 'CUSTOMER_TO_DRIVER')));
        if ($deliveryFeeChargeMode === 'RECORD_ONLY') {
            $deliveryFeeChargeMode = 'CUSTOMER_TO_DRIVER';
        }
        if (!in_array($deliveryFeeChargeMode, ['CUSTOMER_TO_DRIVER', 'MERCHANT_COLLECT'], true)) {
            $deliveryFeeChargeMode = 'CUSTOMER_TO_DRIVER';
        }
        $paymentDefault = strtoupper(trim((string)($data['payment_default'] ?? 'MANUAL')));
        if (!in_array($paymentDefault, ['AUTO', 'MANUAL'], true)) {
            $paymentDefault = 'MANUAL';
        }
        $days = $data['schedule_days'] ?? ($data['schedule_days_csv'] ?? []);
        if (!is_array($days)) {
            $days = explode(',', (string)$days);
        }
        $days = array_values(array_intersect(['1', '2', '3', '4', '5', '6', '0'], array_map(static function ($day) {
            return trim((string)$day);
        }, $days)));
        if (empty($days)) {
            $days = ['1', '2', '3', '4', '5', '6', '0'];
        }

        $paymentMethodIds = $data['payment_method_ids'] ?? [];
        if (!is_array($paymentMethodIds)) {
            $paymentMethodIds = [$paymentMethodIds];
        }
        $paymentMethodIds = array_values(array_unique(array_filter(array_map('intval', $paymentMethodIds))));
        $qrisPaymentMethodId = max(0, (int)($data['qris_payment_method_id'] ?? 0));

        foreach ($paymentMethodIds as $methodId) {
            $method = $this->find_payment_method($methodId);
            if (!$method || (int)($method['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Ada metode pembayaran online food yang tidak ditemukan atau tidak aktif.'];
            }
        }

        $autoEnabled = !empty($data['payment_auto_enabled']);
        $manualEnabled = !empty($data['payment_manual_enabled']);
        if (!$autoEnabled && !$manualEnabled) {
            $manualEnabled = true;
            $paymentDefault = 'MANUAL';
        }
        if ($paymentDefault === 'AUTO' && !$autoEnabled) {
            $paymentDefault = 'MANUAL';
        }
        if ($paymentDefault === 'MANUAL' && !$manualEnabled) {
            $paymentDefault = 'AUTO';
        }

        if ($autoEnabled) {
            if ($qrisPaymentMethodId <= 0) {
                return ['ok' => false, 'message' => 'Pilih metode pembayaran QRIS untuk payment otomatis online food.'];
            }
            $qrisMethod = $this->find_payment_method($qrisPaymentMethodId);
            if (!$qrisMethod || (int)($qrisMethod['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Metode pembayaran QRIS online food tidak ditemukan atau tidak aktif.'];
            }
            if (!in_array($qrisPaymentMethodId, $paymentMethodIds, true)) {
                $paymentMethodIds[] = $qrisPaymentMethodId;
            }
        } else {
            $qrisPaymentMethodId = 0;
        }

        $number = static function ($value): float {
            return max(0, round((float)$value, 2));
        };
        $nullableDecimal = static function ($value) {
            $value = trim((string)$value);
            return $value === '' ? null : (float)$value;
        };
        $memberBaseInput = trim((string)($data['online_order_url'] ?? ''));
        if ($memberBaseInput !== '') {
            $memberBaseInput = preg_replace('#/online-order/?$#i', '/', $memberBaseInput);
        } else {
            $memberBaseInput = (string)($data['member_base_url'] ?? '');
        }

        $this->db->trans_begin();
        try {
            $this->db->replace('pos_online_food_setting', $this->filter_table_payload('pos_online_food_setting', [
                'id' => 1,
                'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
                'open_mode' => $openMode,
                'manual_status' => $manualStatus,
                'timezone' => trim((string)($data['timezone'] ?? 'Asia/Jakarta')) ?: 'Asia/Jakarta',
                'open_time' => trim((string)($data['open_time'] ?? '08:00')) ?: null,
                'close_time' => trim((string)($data['close_time'] ?? '22:00')) ?: null,
                'schedule_days' => implode(',', $days),
                'allow_cod' => $manualEnabled ? 1 : 0,
                'allow_qris' => $autoEnabled ? 1 : 0,
                'payment_default' => $paymentDefault,
                'payment_auto_enabled' => $autoEnabled ? 1 : 0,
                'payment_manual_enabled' => $manualEnabled ? 1 : 0,
                'auto_payment_provider' => 'MIDTRANS',
                'midtrans_server_key' => trim((string)($data['midtrans_server_key'] ?? '')),
                'midtrans_client_key' => trim((string)($data['midtrans_client_key'] ?? '')),
                'midtrans_is_production' => !empty($data['midtrans_is_production']) ? 1 : 0,
                'qris_payment_method_id' => $qrisPaymentMethodId > 0 ? $qrisPaymentMethodId : null,
                'delivery_fee_mode' => $deliveryFeeMode,
                'delivery_fee_charge_mode' => $deliveryFeeChargeMode,
                'delivery_flat_fee' => $number($data['delivery_flat_fee'] ?? 0),
                'delivery_base_fee' => $number($data['delivery_base_fee'] ?? 0),
                'delivery_base_km' => $number($data['delivery_base_km'] ?? 0),
                'delivery_per_km_fee' => $number($data['delivery_per_km_fee'] ?? 0),
                'delivery_min_fee' => $number($data['delivery_min_fee'] ?? 0),
                'delivery_max_distance_km' => $number($data['delivery_max_distance_km'] ?? 0),
                'free_delivery_min_order' => $number($data['free_delivery_min_order'] ?? 0),
                'free_delivery_distance_km' => $number($data['free_delivery_distance_km'] ?? 0),
                'packaging_fee_default' => $number($data['packaging_fee_default'] ?? 0),
                'min_order_amount' => $number($data['min_order_amount'] ?? 0),
                'outlet_lat' => $nullableDecimal($data['outlet_lat'] ?? ''),
                'outlet_lng' => $nullableDecimal($data['outlet_lng'] ?? ''),
                'manual_whatsapp_number' => $this->nullable_text($data['manual_whatsapp_number'] ?? ''),
                'manual_whatsapp_template' => $this->nullable_text($data['manual_whatsapp_template'] ?? ''),
                'manual_payment_instructions' => $this->nullable_text($data['manual_payment_instructions'] ?? ''),
                'member_base_url' => $this->normalize_member_base_url($memberBaseInput),
                'notes' => $this->nullable_text($data['notes'] ?? ''),
            ]));

            $this->db->where('setting_id', 1)->delete('pos_online_food_payment_method');
            foreach ($paymentMethodIds as $methodId) {
                $this->db->insert('pos_online_food_payment_method', [
                    'setting_id' => 1,
                    'payment_method_id' => $methodId,
                    'is_active' => 1,
                ]);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan pengaturan online food.');
            }
            $this->db->trans_commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function online_food_payment_method_options(): array
    {
        return $this->deposit_payment_method_options();
    }

    public function online_food_location_rows(array $filters = []): array
    {
        $db = $this->coredb;
        if (!$db->table_exists('crm_member_delivery_location') || !$db->table_exists('crm_member')) {
            return [
                'rows' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => max(1, min(200, (int)($filters['limit'] ?? 50))),
                    'total_rows' => 0,
                    'total_pages' => 1,
                ],
            ];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $freeStatus = strtoupper(trim((string)($filters['free_status'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db->from('crm_member_delivery_location l')
            ->join('crm_member m', 'm.id = l.member_id', 'left');

        if ($q !== '') {
            $db->group_start()
                ->like('m.member_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->or_like('l.label', $q)
                ->or_like('l.address', $q)
                ->or_like('l.address_note', $q)
                ->or_like('l.recipient_name', $q)
                ->or_like('l.recipient_phone', $q)
                ->group_end();
        }
        if ($freeStatus === 'FREE') {
            $db->where('l.free_delivery_enabled', 1);
        } elseif ($freeStatus === 'NORMAL') {
            $db->where('l.free_delivery_enabled', 0);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
                l.id,
                l.member_id,
                l.label,
                l.recipient_name,
                l.recipient_phone,
                l.address,
                l.address_note,
                l.latitude,
                l.longitude,
                l.location_accuracy,
                l.is_default,
                l.free_delivery_enabled,
                l.free_delivery_reason,
                l.last_used_at,
                l.created_at,
                l.updated_at,
                m.member_no,
                m.member_name,
                m.mobile_phone
            ', false)
            ->order_by('l.free_delivery_enabled', 'DESC')
            ->order_by('l.last_used_at IS NULL', 'ASC', false)
            ->order_by('l.last_used_at', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['member_id'] = (int)($row['member_id'] ?? 0);
            $row['is_default'] = (int)($row['is_default'] ?? 0);
            $row['free_delivery_enabled'] = (int)($row['free_delivery_enabled'] ?? 0);
            $row['latitude'] = isset($row['latitude']) ? (float)$row['latitude'] : null;
            $row['longitude'] = isset($row['longitude']) ? (float)$row['longitude'] : null;
        }
        unset($row);

        return [
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_rows' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function save_online_food_location(array $data): array
    {
        $db = $this->coredb;
        if (!$db->table_exists('crm_member_delivery_location') || !$db->table_exists('crm_member')) {
            return ['ok' => false, 'message' => 'Tabel alamat online food belum tersedia. Jalankan SQL migration online food terlebih dulu.'];
        }

        $id = (int)($data['id'] ?? 0);
        $existing = null;
        if ($id > 0) {
            $existing = $db->from('crm_member_delivery_location')->where('id', $id)->limit(1)->get()->row_array();
            if (!$existing) {
                return ['ok' => false, 'message' => 'Alamat customer tidak ditemukan.'];
            }
        }

        $memberId = (int)($data['member_id'] ?? ($existing['member_id'] ?? 0));
        if ($memberId <= 0) {
            return ['ok' => false, 'message' => 'Member wajib dipilih.'];
        }
        $member = $db->select('id')->from('crm_member')->where('id', $memberId)->limit(1)->get()->row_array();
        if (!$member) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        $label = $this->nullable_text($data['label'] ?? '');
        $address = $this->nullable_text($data['address'] ?? '');
        if ($label === null) {
            return ['ok' => false, 'message' => 'Label alamat wajib diisi.'];
        }
        if ($address === null) {
            return ['ok' => false, 'message' => 'Alamat wajib diisi.'];
        }

        $lat = is_numeric($data['latitude'] ?? null) ? (float)$data['latitude'] : null;
        $lng = is_numeric($data['longitude'] ?? null) ? (float)$data['longitude'] : null;
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return ['ok' => false, 'message' => 'Koordinat alamat belum valid.'];
        }

        $enabled = (int)($data['free_delivery_enabled'] ?? 0) === 1 ? 1 : 0;
        $reason = $this->nullable_text($data['free_delivery_reason'] ?? '');
        if ($enabled === 1 && $reason === null) {
            $reason = 'Langganan';
        }

        $payload = [
            'member_id' => $memberId,
            'label' => substr($label, 0, 80),
            'recipient_name' => $this->nullable_text($data['recipient_name'] ?? ''),
            'recipient_phone' => $this->nullable_text($data['recipient_phone'] ?? ''),
            'address' => substr($address, 0, 255),
            'address_note' => $this->nullable_text($data['address_note'] ?? ''),
            'latitude' => $lat,
            'longitude' => $lng,
            'location_accuracy' => is_numeric($data['location_accuracy'] ?? null) ? max(0, (float)$data['location_accuracy']) : null,
            'is_default' => (int)($data['is_default'] ?? 0) === 1 ? 1 : 0,
            'free_delivery_enabled' => $enabled,
            'free_delivery_reason' => $enabled === 1 ? $reason : null,
        ];
        if ($db->field_exists('updated_at', 'crm_member_delivery_location')) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        $db->trans_begin();
        try {
            if ((int)$payload['is_default'] === 1) {
                $db->where('member_id', $memberId);
                if ($id > 0) {
                    $db->where('id !=', $id);
                }
                $db->update('crm_member_delivery_location', ['is_default' => 0]);
            }

            if ($id > 0) {
                $db->where('id', $id)->update('crm_member_delivery_location', $payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $db->insert('crm_member_delivery_location', $payload);
                $id = (int)$db->insert_id();
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan alamat online food.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function save_online_food_location_free_delivery(array $data): array
    {
        if ((int)($data['id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Alamat customer belum dipilih.'];
        }

        $db = $this->coredb;
        if (!$db->table_exists('crm_member_delivery_location')) {
            return ['ok' => false, 'message' => 'Tabel alamat online food belum tersedia. Jalankan SQL migration online food terlebih dulu.'];
        }

        $row = $db->from('crm_member_delivery_location')->where('id', (int)$data['id'])->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Alamat customer tidak ditemukan.'];
        }

        return $this->save_online_food_location($data + $row);
    }

    public function delete_online_food_location(int $id): array
    {
        $db = $this->coredb;
        if (!$db->table_exists('crm_member_delivery_location')) {
            return ['ok' => false, 'message' => 'Tabel alamat online food belum tersedia.'];
        }
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Alamat customer belum dipilih.'];
        }

        $row = $db->select('id')->from('crm_member_delivery_location')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Alamat customer tidak ditemukan.'];
        }
        $db->where('id', $id)->delete('crm_member_delivery_location');
        return ['ok' => true, 'id' => $id];
    }

    public function self_order_qris_payment_method_options(): array
    {
        $db = $this->coredb;
        if (!$db->table_exists('pos_payment_method')) {
            return [];
        }

        $db->from('pos_payment_method pm');
        $select = 'pm.id, pm.method_code, pm.method_name, pm.method_type, pm.company_account_id';
        if ($db->table_exists('fin_company_account')) {
            $db->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
            $select .= ', acc.account_name, acc.account_type, acc.bank_name, acc.account_no, acc.account_holder';
        }

        return $db->select($select, false)
            ->where('pm.is_active', 1)
            ->where('pm.company_account_id IS NOT NULL', null, false)
            ->order_by('pm.method_name', 'ASC')
            ->order_by('pm.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function self_order_table_rows(array $filters = []): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $tableName = $this->self_order_table_master();

        if ($tableName === null) {
            return ['rows' => []];
        }

        $db = $this->db;
        $db->from($tableName);
        if ($q !== '') {
            $db->group_start()
                ->like('nama_meja', $q);
            if ($this->db->field_exists('qr_label', $tableName)) {
                $db->or_like('qr_label', $q);
            }
            $db->group_end();
        }
        if ($this->db->field_exists('is_active', $tableName)) {
            if ($status === 'ACTIVE') {
                $db->where('is_active', 1);
            } elseif ($status === 'INACTIVE') {
                $db->where('is_active', 0);
            }
        }

        $sortField = $this->db->field_exists('sort_order', $tableName) ? 'sort_order' : 'id';
        $rows = $db->order_by($sortField, 'ASC')
            ->order_by('nama_meja', 'ASC')
            ->get()
            ->result_array();

        $settings = $this->self_order_settings();
        foreach ($rows as &$row) {
            $row['is_active'] = isset($row['is_active']) ? (int)$row['is_active'] : 1;
            $row['capacity'] = isset($row['capacity']) ? (int)$row['capacity'] : 0;
            $row['sort_order'] = isset($row['sort_order']) ? (int)$row['sort_order'] : 0;
            $row['qr_label'] = trim((string)($row['qr_label'] ?? ''));
            $row['qr_url'] = $this->build_self_order_table_url((int)($row['id'] ?? 0), $settings);
        }
        unset($row);

        return ['rows' => $rows];
    }

    public function find_self_order_table(int $id): ?array
    {
        $tableName = $this->self_order_table_master();
        if ($id <= 0 || $tableName === null) {
            return null;
        }
        return $this->db->from($tableName)->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function save_self_order_table(array $data): array
    {
        $tableName = $this->self_order_table_master();
        if ($tableName === null) {
            return ['ok' => false, 'message' => 'Schema meja self order belum tersedia. Jalankan migration self order terlebih dulu.'];
        }

        $db = $this->db;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['nama_meja'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama meja wajib diisi.'];
        }

        $payload = [
            'nama_meja' => $name,
            'qr_label' => $this->nullable_text($data['qr_label'] ?? ''),
            'capacity' => max(0, (int)($data['capacity'] ?? 0)),
            'sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
            'is_active' => !isset($data['is_active']) || (int)$data['is_active'] === 1 ? 1 : 0,
        ];
        $payload = $this->filter_table_payload($tableName, $payload);

        $db->trans_begin();
        try {
            if ($id > 0) {
                if (!$this->find_self_order_table($id)) {
                    throw new RuntimeException('Meja tidak ditemukan.');
                }
                $db->where('id', $id)->update($tableName, $payload);
            } else {
                $db->insert($tableName, $payload);
                $id = (int)$db->insert_id();
            }
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan meja self order.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function bulk_save_self_order_tables(array $items): array
    {
        $tableName = $this->self_order_table_master();
        if ($tableName === null) {
            return ['ok' => false, 'message' => 'Schema meja self order belum tersedia.'];
        }
        if (empty($items)) {
            return ['ok' => false, 'message' => 'Tidak ada meja untuk disimpan.'];
        }

        $rows = [];
        foreach ($items as $item) {
            $name = trim((string)($item['nama_meja'] ?? ''));
            if ($name === '') continue;
            $row = [
                'nama_meja'  => $name,
                'qr_label'   => $this->nullable_text($item['qr_label'] ?? ''),
                'capacity'   => max(0, (int)($item['capacity'] ?? 0)),
                'sort_order' => max(0, (int)($item['sort_order'] ?? 0)),
                'is_active'  => !isset($item['is_active']) || (int)$item['is_active'] === 1 ? 1 : 0,
            ];
            $rows[] = $this->filter_table_payload($tableName, $row);
        }
        if (empty($rows)) {
            return ['ok' => false, 'message' => 'Semua nama meja kosong, tidak ada yang disimpan.'];
        }

        $this->db->trans_begin();
        try {
            foreach ($rows as $row) {
                $this->db->insert($tableName, $row);
            }
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan meja (bulk insert).');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'count' => count($rows)];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete_self_order_table(int $id): array
    {
        $tableName = $this->self_order_table_master();
        if ($id <= 0 || $tableName === null) {
            return ['ok' => false, 'message' => 'Meja tidak ditemukan.'];
        }
        $row = $this->find_self_order_table($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Meja tidak ditemukan.'];
        }

        $used = 0;
        if ($this->db->table_exists('pos_order') && $this->db->field_exists('table_no', 'pos_order')) {
            $used = (int)$this->db->where('table_no', (string)($row['nama_meja'] ?? ''))->count_all_results('pos_order');
        }
        if ($used > 0) {
            return ['ok' => false, 'message' => 'Meja sudah dipakai di transaksi POS. Nonaktifkan saja bila tidak ingin dipakai lagi.'];
        }

        $this->db->trans_begin();
        try {
            $this->db->where('id', $id)->delete($tableName);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menghapus meja self order.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function self_order_print_rows(): array
    {
        return $this->self_order_table_rows(['status' => 'ACTIVE'])['rows'] ?? [];
    }

    public function self_order_order_filter_options(): array
    {
        return [
            'outlets' => $this->local_outlet_options(),
        ];
    }

    public function self_order_order_rows(array $filters = []): array
    {
        if (!$this->db->table_exists('pos_order')) {
            return [
                'rows' => [],
                'counts' => $this->self_order_empty_counts(),
                'meta' => ['total' => 0, 'page' => 1, 'limit' => 20, 'total_pages' => 1],
            ];
        }

        $hasCustomerName = $this->ensure_pos_order_customer_name_column();
        $hasTableNo = $this->db->field_exists('table_no', 'pos_order');
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $paymentTab = strtoupper(trim((string)($filters['payment_tab'] ?? 'ALL')));
        if (!in_array($paymentTab, ['ALL', 'KASIR', 'QRIS'], true)) {
            $paymentTab = 'ALL';
        }
        $statusTab = strtoupper(trim((string)($filters['status_tab'] ?? 'ALL')));
        if (!in_array($statusTab, ['ALL', 'NEEDS_VERIFY', 'WAITING_PAYMENT', 'ACTIVE_CASHIER', 'PAID_ORDER', 'REJECTED'], true)) {
            $statusTab = 'ALL';
        }
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $dateFrom = $this->normalize_self_order_filter_date((string)($filters['date_from'] ?? ''));
        $dateTo = $this->normalize_self_order_filter_date((string)($filters['date_to'] ?? ''));
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d');
        }
        if ($dateTo === '') {
            $dateTo = $dateFrom;
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $customerDisplayExpr = $this->order_customer_display_expr('o', 'm');
        $hasOnlineDeliveryTable = $this->db->table_exists('pos_online_food_delivery_order');
        $rows = $this->db
            ->select('
                o.id,
                o.order_no,
                o.order_channel,
                o.outlet_id,
                o.service_type,
                o.status,
                o.stock_commit_status,
                o.kitchen_status,
                o.ordered_at,
                o.confirmed_at,
                o.paid_at,
                o.grand_total,
                o.paid_total,
                o.guest_count,
                o.notes,
                ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ',
                ' . ($hasCustomerName ? 'o.customer_name' : 'NULL AS customer_name') . ',
                ' . $customerDisplayExpr . ' AS customer_display_name,
                po.outlet_name,
                m.member_no,
                m.member_name,
                m.mobile_phone AS member_mobile_phone,
                e.employee_name AS cashier_employee_name,
                UPPER(COALESCE(au.username, \'\')) AS cashier_username
            ', false)
            ->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = o.cashier_employee_id', 'left')
            ->where('o.order_channel', 'SELF_ORDER')
            ->where('DATE(o.ordered_at) >=', $dateFrom)
            ->where('DATE(o.ordered_at) <=', $dateTo)
            ->order_by('o.ordered_at', 'DESC')
            ->order_by('o.id', 'DESC')
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [
                'rows' => [],
                'counts' => $this->self_order_empty_counts(),
                'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1],
            ];
        }

        $orderIds = array_map('intval', array_column($rows, 'id'));
        $paymentMap = $this->self_order_final_payment_map($orderIds);
        $rejectionMap = $this->self_order_rejection_info_map($orderIds);
        $prepared = [];
        foreach ($rows as $row) {
            $orderId = (int)($row['id'] ?? 0);
            $payment = (array)($paymentMap[$orderId] ?? []);
            $rejection = (array)($rejectionMap[$orderId] ?? []);
            $row = $this->self_order_apply_reject_state($row, $rejection);
            $flow = $this->self_order_flow_state($row, $payment);

            $customerName = $this->resolve_order_customer_name($row['customer_name'] ?? '', $row['member_name'] ?? '');
            $searchHaystack = strtoupper(implode(' ', array_filter([
                (string)($row['order_no'] ?? ''),
                (string)$customerName,
                (string)($row['member_no'] ?? ''),
                (string)($row['member_mobile_phone'] ?? ''),
                (string)($row['table_no'] ?? ''),
                (string)($payment['payment_mode'] ?? ''),
                (string)($payment['method_name'] ?? ''),
            ])));
            if ($q !== '' && strpos($searchHaystack, strtoupper($q)) === false) {
                continue;
            }
            if ($outletId > 0 && (int)($row['outlet_id'] ?? 0) !== $outletId) {
                continue;
            }
            if ($paymentTab !== 'ALL' && strtoupper((string)($flow['payment_mode'] ?? 'KASIR')) !== $paymentTab) {
                continue;
            }

            $prepared[] = $row + $flow + [
                'customer_name_display' => $customerName !== '' ? $customerName : 'Walk in',
                'cashier_name_display' => trim((string)($row['cashier_username'] ?? '')) !== ''
                    ? strtoupper(trim((string)$row['cashier_username']))
                    : trim((string)($row['cashier_employee_name'] ?? '-')),
                'payment_reference' => (string)($payment['reference_no'] ?? ''),
                'payment_method_name' => (string)($payment['method_name'] ?? ''),
                'payment_method_type' => (string)($payment['method_type'] ?? ''),
                'rejected_reason' => (string)($rejection['reason'] ?? ''),
                'rejected_by_name' => (string)($rejection['actor_name'] ?? ''),
            ];
        }

        $counts = $this->self_order_empty_counts();
        foreach ($prepared as $row) {
            $counts['ALL']++;
            $code = strtoupper((string)($row['flow_code'] ?? ''));
            if (isset($counts[$code])) {
                $counts[$code]++;
            }
        }

        if ($statusTab !== 'ALL') {
            $prepared = array_values(array_filter($prepared, static function (array $row) use ($statusTab): bool {
                return strtoupper((string)($row['flow_code'] ?? '')) === $statusTab;
            }));
        }

        $total = count($prepared);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = array_slice($prepared, $offset, $limit);

        return [
            'rows' => $rows,
            'counts' => $counts,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'payment_tab' => $paymentTab,
                'status_tab' => $statusTab,
            ],
        ];
    }

    public function online_food_order_filter_options(): array
    {
        return [
            'outlets' => $this->local_outlet_options(),
        ];
    }

    public function online_food_order_rows(array $filters = []): array
    {
        if (!$this->db->table_exists('pos_order')) {
            return [
                'rows' => [],
                'counts' => $this->self_order_empty_counts(),
                'meta' => ['total' => 0, 'page' => 1, 'limit' => 20, 'total_pages' => 1],
            ];
        }

        $hasCustomerName = $this->ensure_pos_order_customer_name_column();
        $hasTableNo = $this->db->field_exists('table_no', 'pos_order');
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $paymentTab = strtoupper(trim((string)($filters['payment_tab'] ?? 'ALL')));
        if (!in_array($paymentTab, ['ALL', 'KASIR', 'QRIS'], true)) {
            $paymentTab = 'ALL';
        }
        $statusTab = strtoupper(trim((string)($filters['status_tab'] ?? 'ALL')));
        if (!in_array($statusTab, ['ALL', 'NEEDS_VERIFY', 'WAITING_PAYMENT', 'ACTIVE_CASHIER', 'PAID_ORDER', 'REJECTED'], true)) {
            $statusTab = 'ALL';
        }
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $dateFrom = $this->normalize_self_order_filter_date((string)($filters['date_from'] ?? ''));
        $dateTo = $this->normalize_self_order_filter_date((string)($filters['date_to'] ?? ''));
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d');
        }
        if ($dateTo === '') {
            $dateTo = $dateFrom;
        }
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $customerDisplayExpr = $this->order_customer_display_expr('o', 'm');
        $hasOnlineDeliveryTable = $this->db->table_exists('pos_online_food_delivery_order');
        $rows = $this->db
            ->select('
                o.id,
                o.order_no,
                o.order_channel,
                o.outlet_id,
                o.service_type,
                o.status,
                o.stock_commit_status,
                o.kitchen_status,
                o.ordered_at,
                o.confirmed_at,
                o.paid_at,
                o.grand_total,
                o.paid_total,
                o.guest_count,
                o.notes,
                ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ',
                ' . ($hasCustomerName ? 'o.customer_name' : 'NULL AS customer_name') . ',
                ' . $customerDisplayExpr . ' AS customer_display_name,
                po.outlet_name,
                m.member_no,
                m.member_name,
                m.mobile_phone AS member_mobile_phone,
                e.employee_name AS cashier_employee_name,
                UPPER(COALESCE(au.username, \'\')) AS cashier_username,
                ' . ($hasOnlineDeliveryTable ? 'd.fee_charge_mode' : 'NULL') . ' AS delivery_fee_charge_mode,
                ' . ($hasOnlineDeliveryTable ? 'd.fee_paid_by' : 'NULL') . ' AS delivery_fee_paid_by,
                ' . ($hasOnlineDeliveryTable ? 'd.fee_amount' : '0') . ' AS delivery_fee_amount,
                ' . ($hasOnlineDeliveryTable ? 'd.delivery_address' : 'NULL') . ' AS delivery_address
            ', false)
            ->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = o.cashier_employee_id', 'left')
            ->join($hasOnlineDeliveryTable ? 'pos_online_food_delivery_order d' : 'pos_order d', $hasOnlineDeliveryTable ? 'd.order_id = o.id' : '1 = 0', 'left', false)
            ->where('o.order_channel', 'DELIVERY')
            ->where('DATE(o.ordered_at) >=', $dateFrom)
            ->where('DATE(o.ordered_at) <=', $dateTo)
            ->order_by('o.ordered_at', 'DESC')
            ->order_by('o.id', 'DESC')
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [
                'rows' => [],
                'counts' => $this->self_order_empty_counts(),
                'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1],
            ];
        }

        $orderIds = array_map('intval', array_column($rows, 'id'));
        $paymentMap = $this->self_order_final_payment_map($orderIds);
        $rejectionMap = $this->self_order_rejection_info_map($orderIds);
        $prepared = [];
        foreach ($rows as $row) {
            $orderId = (int)($row['id'] ?? 0);
            $payment = (array)($paymentMap[$orderId] ?? []);
            $rejection = (array)($rejectionMap[$orderId] ?? []);
            $row = $this->self_order_apply_reject_state($row, $rejection);
            $flow = $this->self_order_flow_state($row, $payment);

            $customerName = $this->resolve_order_customer_name($row['customer_name'] ?? '', $row['member_name'] ?? '');
            $searchHaystack = strtoupper(implode(' ', array_filter([
                (string)($row['order_no'] ?? ''),
                (string)$customerName,
                (string)($row['member_no'] ?? ''),
                (string)($row['member_mobile_phone'] ?? ''),
                (string)($row['table_no'] ?? ''),
                (string)($payment['payment_mode'] ?? ''),
                (string)($payment['method_name'] ?? ''),
            ])));
            if ($q !== '' && strpos($searchHaystack, strtoupper($q)) === false) {
                continue;
            }
            if ($outletId > 0 && (int)($row['outlet_id'] ?? 0) !== $outletId) {
                continue;
            }
            if ($paymentTab !== 'ALL' && strtoupper((string)($flow['payment_mode'] ?? 'KASIR')) !== $paymentTab) {
                continue;
            }

            $prepared[] = $row + $flow + [
                'customer_name_display' => $customerName !== '' ? $customerName : 'Walk in',
                'cashier_name_display' => trim((string)($row['cashier_username'] ?? '')) !== ''
                    ? strtoupper(trim((string)$row['cashier_username']))
                    : trim((string)($row['cashier_employee_name'] ?? '-')),
                'payment_reference' => (string)($payment['reference_no'] ?? ''),
                'payment_method_name' => (string)($payment['method_name'] ?? ''),
                'payment_method_type' => (string)($payment['method_type'] ?? ''),
                'rejected_reason' => (string)($rejection['reason'] ?? ''),
                'rejected_by_name' => (string)($rejection['actor_name'] ?? ''),
            ];
        }

        $counts = $this->self_order_empty_counts();
        foreach ($prepared as $row) {
            $counts['ALL']++;
            $code = strtoupper((string)($row['flow_code'] ?? ''));
            if (isset($counts[$code])) {
                $counts[$code]++;
            }
        }

        if ($statusTab !== 'ALL') {
            $prepared = array_values(array_filter($prepared, static function (array $row) use ($statusTab): bool {
                return strtoupper((string)($row['flow_code'] ?? '')) === $statusTab;
            }));
        }

        $total = count($prepared);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = array_slice($prepared, $offset, $limit);

        return [
            'rows' => $rows,
            'counts' => $counts,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'payment_tab' => $paymentTab,
                'status_tab' => $statusTab,
            ],
        ];
    }

    public function self_order_verification_context(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order self order tidak valid.'];
        }

        $order = $this->find_self_order_order($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order self order tidak ditemukan.'];
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'SELF_ORDER') {
            return ['ok' => false, 'message' => 'Order ini bukan order self order.'];
        }

        $paymentMap = $this->self_order_final_payment_map([$orderId]);
        $payment = (array)($paymentMap[$orderId] ?? []);
        $flow = $this->self_order_flow_state($header, $payment);
        if (empty($flow['can_verify'])) {
            return [
                'ok' => false,
                'message' => (string)($flow['verify_message'] ?? 'Order self order belum siap diverifikasi.'),
                'flow' => $flow,
            ];
        }

        return [
            'ok' => true,
            'header' => $header,
            'payment' => $payment,
            'flow' => $flow,
            'payment_mode' => (string)($flow['payment_mode'] ?? 'KASIR'),
            'payment_status' => (string)($flow['payment_status'] ?? 'PENDING'),
            'is_paid' => !empty($flow['is_paid']),
        ];
    }

    public function self_order_rejection_context(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order self order tidak valid.'];
        }

        $order = $this->find_self_order_order($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order self order tidak ditemukan.'];
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'SELF_ORDER') {
            return ['ok' => false, 'message' => 'Order ini bukan order self order.'];
        }

        $paymentMap = $this->self_order_final_payment_map([$orderId]);
        $payment = (array)($paymentMap[$orderId] ?? []);
        $flow = $this->self_order_flow_state($header, $payment);
        if (empty($flow['can_reject'])) {
            return [
                'ok' => false,
                'message' => (string)($flow['reject_message'] ?? 'Order self order ini tidak bisa ditolak.'),
                'flow' => $flow,
            ];
        }

        return [
            'ok' => true,
            'header' => $header,
            'payment' => $payment,
            'flow' => $flow,
        ];
    }

    public function find_self_order_order(int $id): ?array
    {
        $order = $this->find_order_draft($id);
        if (!$order) {
            return null;
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'SELF_ORDER') {
            return null;
        }

        $rejection = $this->self_order_rejection_info_map([$id]);
        $rejectInfo = (array)($rejection[$id] ?? []);
        if (!empty($rejectInfo)) {
            $order['header'] = $this->self_order_apply_reject_state((array)$order['header'], $rejectInfo);
            $order['reject_info'] = $rejectInfo;
            $order['header']['reject_reason'] = (string)($rejectInfo['reason'] ?? '');
            $order['header']['reject_actor_name'] = (string)($rejectInfo['actor_name'] ?? '');
        }

        return $order;
    }

    public function online_food_verification_context(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order online food tidak valid.'];
        }

        $order = $this->find_online_food_order($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order online food tidak ditemukan.'];
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'DELIVERY') {
            return ['ok' => false, 'message' => 'Order ini bukan order online food.'];
        }

        $paymentMap = $this->self_order_final_payment_map([$orderId]);
        $payment = (array)($paymentMap[$orderId] ?? []);
        $flow = $this->self_order_flow_state($header, $payment);
        if (empty($flow['can_verify'])) {
            return [
                'ok' => false,
                'message' => (string)($flow['verify_message'] ?? 'Order online food belum siap diverifikasi.'),
                'flow' => $flow,
            ];
        }

        return [
            'ok' => true,
            'header' => $header,
            'payment' => $payment,
            'flow' => $flow,
            'payment_mode' => (string)($flow['payment_mode'] ?? 'KASIR'),
            'payment_status' => (string)($flow['payment_status'] ?? 'PENDING'),
            'is_paid' => !empty($flow['is_paid']),
        ];
    }

    public function online_food_rejection_context(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order online food tidak valid.'];
        }

        $order = $this->find_online_food_order($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order online food tidak ditemukan.'];
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'DELIVERY') {
            return ['ok' => false, 'message' => 'Order ini bukan order online food.'];
        }

        $paymentMap = $this->self_order_final_payment_map([$orderId]);
        $payment = (array)($paymentMap[$orderId] ?? []);
        $flow = $this->self_order_flow_state($header, $payment);
        if (empty($flow['can_reject'])) {
            return [
                'ok' => false,
                'message' => (string)($flow['reject_message'] ?? 'Order online food ini tidak bisa ditolak.'),
                'flow' => $flow,
            ];
        }

        return [
            'ok' => true,
            'header' => $header,
            'payment' => $payment,
            'flow' => $flow,
        ];
    }

    public function find_online_food_order(int $id): ?array
    {
        $order = $this->find_order_draft($id);
        if (!$order) {
            return null;
        }

        $header = (array)($order['header'] ?? []);
        if (strtoupper(trim((string)($header['order_channel'] ?? ''))) !== 'DELIVERY') {
            return null;
        }

        $rejection = $this->self_order_rejection_info_map([$id]);
        $rejectInfo = (array)($rejection[$id] ?? []);
        if (!empty($rejectInfo)) {
            $order['header'] = $this->self_order_apply_reject_state((array)$order['header'], $rejectInfo);
            $order['reject_info'] = $rejectInfo;
            $order['header']['reject_reason'] = (string)($rejectInfo['reason'] ?? '');
            $order['header']['reject_actor_name'] = (string)($rejectInfo['actor_name'] ?? '');
        }

        return $order;
    }

    public function finalize_self_order_verification(int $orderId, int $snapshotId, int $actorEmployeeId, array $context = []): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order self order tidak ditemukan saat finalisasi verifikasi.'];
        }

        $orderLabel = trim((string)($context['order_label'] ?? 'Self order'));
        if ($orderLabel === '') {
            $orderLabel = 'Self order';
        }
        $eventPrefix = strtoupper(trim((string)($context['event_prefix'] ?? 'SELF_ORDER')));
        if ($eventPrefix === '') {
            $eventPrefix = 'SELF_ORDER';
        }
        $paymentMode = strtoupper(trim((string)($context['payment_mode'] ?? 'KASIR')));
        $isPaid = !empty($context['is_paid']);
        $paymentContext = (array)($context['payment'] ?? []);
        $memberIdForLoyalty = !empty($row['member_id'])
            ? (int)$row['member_id']
            : (!empty($paymentContext['member_id']) ? (int)$paymentContext['member_id'] : 0);
        $verifyDestination = strtoupper(trim((string)($context['verify_destination'] ?? '')));
        // Self order QRIS has always been allowed to go straight to paid
        // orders. Reservation verification explicitly opts in too when its
        // linked DP has covered the full invoice; other flows keep the old
        // cashier destination rule.
        $allowPaidDestination = !empty($context['allow_paid_destination']);
        if ($paymentMode === 'QRIS' && $isPaid) {
            $verifyDestination = 'PAID_ORDER';
        } else {
            if (!in_array($verifyDestination, ['ACTIVE_CASHIER', 'PAID_ORDER'], true)) {
                $verifyDestination = $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER';
            }
            if (!$isPaid || ($paymentMode !== 'QRIS' && !$allowPaidDestination)) {
                $verifyDestination = 'ACTIVE_CASHIER';
            }
        }
        $targetStatus = $verifyDestination === 'PAID_ORDER' ? 'PAID' : 'CONFIRMED';
        $stockCommitStatus = strtoupper(trim((string)($context['stock_commit_status'] ?? 'QUEUED')));
        if (!in_array($stockCommitStatus, ['PENDING', 'QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED', 'NOT_REQUIRED'], true)) {
            $stockCommitStatus = 'QUEUED';
        }
        $activeSession = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $payload = [
                'status' => $targetStatus,
                'stock_commit_status' => $stockCommitStatus,
            ];
            $loyaltySummary = ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
            if (empty($row['confirmed_at'])) {
                $payload['confirmed_at'] = date('Y-m-d H:i:s');
            }
            if ($stockCommitStatus === 'POSTED') {
                $payload['stock_committed_at'] = date('Y-m-d H:i:s');
            }
            if ($isPaid && empty($row['paid_at'])) {
                $payload['paid_at'] = date('Y-m-d H:i:s');
                $payload['paid_total'] = (float)($row['grand_total'] ?? 0);
            }
            if ($memberIdForLoyalty > 0 && empty($row['member_id']) && $this->db->field_exists('member_id', 'pos_order')) {
                $payload['member_id'] = $memberIdForLoyalty;
            }
            if ($actorEmployeeId > 0 && $this->db->field_exists('cashier_employee_id', 'pos_order')) {
                $payload['cashier_employee_id'] = $actorEmployeeId;
            }
            if (!empty($activeSession)) {
                if (empty($row['terminal_id']) && $this->db->field_exists('terminal_id', 'pos_order')) {
                    $payload['terminal_id'] = !empty($activeSession['terminal_id']) ? (int)$activeSession['terminal_id'] : null;
                }
                if (empty($row['shift_id']) && $this->db->field_exists('shift_id', 'pos_order')) {
                    $payload['shift_id'] = !empty($activeSession['shift_id']) ? (int)$activeSession['shift_id'] : null;
                }
                if (empty($row['cashier_session_id']) && $this->db->field_exists('cashier_session_id', 'pos_order')) {
                    $payload['cashier_session_id'] = !empty($activeSession['id']) ? (int)$activeSession['id'] : null;
                }
                if (empty($row['outlet_id']) && $this->db->field_exists('outlet_id', 'pos_order')) {
                    $payload['outlet_id'] = !empty($activeSession['outlet_id']) ? (int)$activeSession['outlet_id'] : null;
                }
            }

            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $payload));
            if ($this->db->table_exists('pos_payment')) {
                $paymentPayload = [];
                if ($actorEmployeeId > 0 && $this->db->field_exists('cashier_employee_id', 'pos_payment')) {
                    $paymentPayload['cashier_employee_id'] = $actorEmployeeId;
                }
                if ($this->db->field_exists('shift_id', 'pos_payment')) {
                    $paymentPayload['shift_id'] = array_key_exists('shift_id', $payload)
                        ? $payload['shift_id']
                        : (!empty($row['shift_id']) ? (int)$row['shift_id'] : null);
                }
                if ($this->db->field_exists('cashier_session_id', 'pos_payment')) {
                    $paymentPayload['cashier_session_id'] = array_key_exists('cashier_session_id', $payload)
                        ? $payload['cashier_session_id']
                        : (!empty($row['cashier_session_id']) ? (int)$row['cashier_session_id'] : null);
                }
                if ($memberIdForLoyalty > 0 && $this->db->field_exists('member_id', 'pos_payment')) {
                    $paymentPayload['member_id'] = $memberIdForLoyalty;
                }
                if ($isPaid) {
                    if ($this->db->field_exists('payment_status', 'pos_payment')) {
                        $paymentPayload['payment_status'] = 'PAID';
                    }
                    if ($this->db->field_exists('paid_at', 'pos_payment') && !empty($payload['paid_at'])) {
                        $paymentPayload['paid_at'] = $payload['paid_at'];
                    }
                }
                if (!empty($paymentPayload)) {
                    $this->db->where('order_id', $orderId)
                        ->where('payment_type', 'FINAL')
                        ->update('pos_payment', $this->filter_table_payload('pos_payment', $paymentPayload));
                }
            }

            if ($isPaid) {
                $loyaltySummary = $this->apply_paid_order_loyalty_by_order_id(
                    $orderId,
                    $memberIdForLoyalty,
                    (string)($payload['paid_at'] ?? date('Y-m-d H:i:s'))
                );
            }

            if ($isPaid) {
                $note = $orderLabel . ' diverifikasi setelah pembayaran diterima.';
            } else {
                $note = $orderLabel . ' diverifikasi untuk dibayar di kasir.';
            }
            if ($stockCommitStatus === 'NOT_REQUIRED' || $snapshotId <= 0) {
                $note .= ' Snapshot stock commit dilewati karena recipe product belum tersedia.';
            } else {
                $note .= ' Snapshot stock commit #' . $snapshotId . ' dibuat.';
            }
            $this->insert_order_state_log(
                $orderId,
                (string)($row['status'] ?? 'PENDING'),
                $targetStatus,
                $isPaid
                    ? $eventPrefix . '_VERIFY_PAID'
                    : $eventPrefix . '_VERIFY_CASHIER',
                $actorEmployeeId,
                $note
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memfinalkan verifikasi order ' . strtolower($orderLabel) . '.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return [
                'ok' => true,
                'id' => $orderId,
                'target_status' => $targetStatus,
                'workspace_bucket' => $verifyDestination,
                'payment_mode' => $paymentMode,
                'is_paid' => $isPaid ? 1 : 0,
                'loyalty' => $loyaltySummary,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function reject_self_order_order(int $orderId, int $actorEmployeeId, string $reason = ''): array
    {
        $context = $this->self_order_rejection_context($orderId);
        if (!($context['ok'] ?? false)) {
            return $context;
        }

        $header = (array)($context['header'] ?? []);
        $payment = (array)($context['payment'] ?? []);
        $existingStatus = strtoupper(trim((string)($header['status'] ?? 'PENDING')));
        if ($this->self_order_is_rejected_status($existingStatus)) {
            return ['ok' => true, 'id' => $orderId, 'status' => 'REJECTED', 'reason' => trim($reason)];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan penolakan wajib diisi.'];
        }

        $paymentId = (int)($payment['id'] ?? 0);
        $paymentMeta = $this->decode_json_assoc((string)($payment['notes'] ?? ''));
        $paymentMeta['payment_status_label'] = 'VOID';
        unset($paymentMeta['payment_qr_url'], $paymentMeta['payment_qr_string']);
        $paymentNotes = $this->encode_self_order_payment_meta($paymentMeta);

        $this->db->trans_begin();
        try {
            $orderPayload = [
                'status' => 'VOID',
                'kitchen_status' => 'VOID',
                'paid_at' => null,
                'paid_total' => 0,
            ];
            if ($actorEmployeeId > 0 && $this->db->field_exists('cashier_employee_id', 'pos_order')) {
                $orderPayload['cashier_employee_id'] = $actorEmployeeId;
            }
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $orderPayload));

            if ($paymentId > 0) {
                $paymentPayload = ['payment_status' => 'VOID', 'paid_at' => null];
                if ($paymentNotes !== null && $this->db->field_exists('notes', 'pos_payment')) {
                    $paymentPayload['notes'] = $paymentNotes;
                }
                $this->db->where('id', $paymentId)->update('pos_payment', $this->filter_table_payload('pos_payment', $paymentPayload));
                if ($this->db->table_exists('pos_payment_line')) {
                    $linePayload = ['status' => 'VOID'];
                    if ($this->db->field_exists('received_at', 'pos_payment_line')) {
                        $linePayload['received_at'] = null;
                    }
                    $this->db->where('payment_id', $paymentId)->update('pos_payment_line', $this->filter_table_payload('pos_payment_line', $linePayload));
                }
            }

            $note = 'Self order ditolak. Alasan penolakan: ' . $reason;
            $this->insert_order_state_log($orderId, $existingStatus !== '' ? $existingStatus : 'PENDING', 'REJECTED', 'SELF_ORDER_REJECT', $actorEmployeeId, $note);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan penolakan self order.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $orderId, 'status' => 'REJECTED', 'reason' => $reason];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function reject_online_food_order(int $orderId, int $actorEmployeeId, string $reason = ''): array
    {
        $context = $this->online_food_rejection_context($orderId);
        if (!($context['ok'] ?? false)) {
            return $context;
        }

        $header = (array)($context['header'] ?? []);
        $payment = (array)($context['payment'] ?? []);
        $existingStatus = strtoupper(trim((string)($header['status'] ?? 'PENDING')));
        if ($this->self_order_is_rejected_status($existingStatus)) {
            return ['ok' => true, 'id' => $orderId, 'status' => 'REJECTED', 'reason' => trim($reason)];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan penolakan wajib diisi.'];
        }

        $paymentId = (int)($payment['id'] ?? 0);
        $paymentMeta = $this->decode_json_assoc((string)($payment['notes'] ?? ''));
        $paymentMeta['payment_status_label'] = 'VOID';
        unset($paymentMeta['payment_qr_url'], $paymentMeta['payment_qr_string']);
        $paymentNotes = $this->encode_self_order_payment_meta($paymentMeta);

        $this->db->trans_begin();
        try {
            $orderPayload = [
                'status' => 'VOID',
                'kitchen_status' => 'VOID',
                'paid_at' => null,
                'paid_total' => 0,
            ];
            if ($actorEmployeeId > 0 && $this->db->field_exists('cashier_employee_id', 'pos_order')) {
                $orderPayload['cashier_employee_id'] = $actorEmployeeId;
            }
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $orderPayload));

            if ($paymentId > 0) {
                $paymentPayload = ['payment_status' => 'VOID', 'paid_at' => null];
                if ($paymentNotes !== null && $this->db->field_exists('notes', 'pos_payment')) {
                    $paymentPayload['notes'] = $paymentNotes;
                }
                $this->db->where('id', $paymentId)->update('pos_payment', $this->filter_table_payload('pos_payment', $paymentPayload));
                if ($this->db->table_exists('pos_payment_line')) {
                    $linePayload = ['status' => 'VOID'];
                    if ($this->db->field_exists('received_at', 'pos_payment_line')) {
                        $linePayload['received_at'] = null;
                    }
                    $this->db->where('payment_id', $paymentId)->update('pos_payment_line', $this->filter_table_payload('pos_payment_line', $linePayload));
                }
            }

            $note = 'Online food ditolak. Alasan penolakan: ' . $reason;
            $this->insert_order_state_log($orderId, $existingStatus !== '' ? $existingStatus : 'PENDING', 'REJECTED', 'ONLINE_FOOD_REJECT', $actorEmployeeId, $note);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan penolakan online food.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $orderId, 'status' => 'REJECTED', 'reason' => $reason];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function self_order_table_master(): ?string
    {
        if ($this->db->table_exists('pos_self_order_table')) {
            return 'pos_self_order_table';
        }
        if ($this->db->table_exists('pr_meja')) {
            return 'pr_meja';
        }

        return null;
    }

    private function self_order_qr_secret_table(): ?string
    {
        if ($this->db->table_exists('pos_self_order_qr_secret')) {
            return 'pos_self_order_qr_secret';
        }
        if ($this->db->table_exists('pr_table_qr_secret')) {
            return 'pr_table_qr_secret';
        }

        return null;
    }

    private function self_order_qris_table(): ?string
    {
        if ($this->db->table_exists('pos_self_order_qris_setting')) {
            return 'pos_self_order_qris_setting';
        }
        if ($this->db->table_exists('pr_qris_setting')) {
            return 'pr_qris_setting';
        }

        return null;
    }

    private function self_order_empty_counts(): array
    {
        return [
            'ALL' => 0,
            'NEEDS_VERIFY' => 0,
            'WAITING_PAYMENT' => 0,
            'ACTIVE_CASHIER' => 0,
            'PAID_ORDER' => 0,
            'REJECTED' => 0,
        ];
    }

    private function normalize_self_order_filter_date(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function decode_json_assoc(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encode_self_order_payment_meta(array $meta): ?string
    {
        $filtered = [];
        foreach ($meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }
        $json = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }
        if (strlen($json) <= 255) {
            return $json;
        }
        unset($filtered['rejected_reason'], $filtered['payment_qr_url'], $filtered['payment_qr_string']);
        $json = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }
        return strlen($json) <= 255 ? $json : substr($json, 0, 255);
    }

    private function self_order_rejected_statuses(): array
    {
        return ['REJECTED', 'CANCELLED', 'CANCEL', 'VOID'];
    }

    private function self_order_is_rejected_status(string $status): bool
    {
        return in_array(strtoupper(trim($status)), $this->self_order_rejected_statuses(), true);
    }

    private function self_order_extract_rejection_reason(string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return '';
        }
        $marker = 'Alasan penolakan:';
        $pos = strpos($note, $marker);
        if ($pos === false) {
            return $note;
        }
        return trim(substr($note, $pos + strlen($marker)));
    }

    private function self_order_apply_reject_state(array $order, array $rejectInfo = []): array
    {
        if (empty($rejectInfo)) {
            return $order;
        }

        $status = strtoupper(trim((string)($order['status'] ?? '')));
        if ($status === '' || in_array($status, ['DRAFT', 'PENDING', 'REJECTED'], true)) {
            $order['status'] = 'VOID';
        }
        if (empty($order['kitchen_status'])) {
            $order['kitchen_status'] = 'VOID';
        }

        return $order;
    }

    private function self_order_rejection_info_map(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds) || !$this->db->table_exists('pos_order_state_log')) {
            return [];
        }

        $hasCreatedAt = $this->db->field_exists('created_at', 'pos_order_state_log');
        $select = 'l.id, l.order_id, l.actor_employee_id, l.notes';
        if ($hasCreatedAt) {
            $select .= ', l.created_at';
        }

        $rows = $this->db->select($select . ', e.employee_name AS actor_name', false)
            ->from('pos_order_state_log l')
            ->join('org_employee e', 'e.id = l.actor_employee_id', 'left')
            ->where_in('l.order_id', $orderIds)
            ->where_in('l.event_code', ['SELF_ORDER_REJECT', 'ONLINE_FOOD_REJECT'])
            ->order_by('l.id', 'DESC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int)($row['order_id'] ?? 0);
            if ($orderId <= 0 || isset($map[$orderId])) {
                continue;
            }
            $map[$orderId] = [
                'reason' => $this->self_order_extract_rejection_reason((string)($row['notes'] ?? '')),
                'note' => (string)($row['notes'] ?? ''),
                'actor_name' => trim((string)($row['actor_name'] ?? '')),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        return $map;
    }

    private function self_order_final_payment_map(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds) || !$this->db->table_exists('pos_payment')) {
            return [];
        }

        $rows = $this->db->select('
                p.*,
                pl.payment_method_id,
                pl.reference_no,
                pm.method_name,
                pm.method_type
            ')
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id AND pl.line_no = 1', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where_in('p.order_id', $orderIds)
            ->where('p.payment_type', 'FINAL')
            ->order_by('p.order_id', 'ASC')
            ->order_by('p.id', 'DESC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int)($row['order_id'] ?? 0);
            if ($orderId > 0 && !isset($map[$orderId])) {
                $meta = $this->decode_json_assoc((string)($row['notes'] ?? ''));
                $row['payment_mode'] = $this->self_order_payment_mode($row, $meta);
                $row['payment_status_label'] = strtoupper(trim((string)($meta['payment_status_label'] ?? ($row['payment_status'] ?? 'PENDING'))));
                $row['payment_provider_label'] = trim((string)($meta['payment_provider'] ?? ''));
                $row['payment_ref_label'] = trim((string)($meta['payment_ref'] ?? ($row['reference_no'] ?? '')));
                $map[$orderId] = $row;
            }
        }

        return $map;
    }

    private function self_order_payment_mode(array $payment, array $meta = []): string
    {
        $mode = strtoupper(trim((string)($meta['payment_method'] ?? '')));
        if (!in_array($mode, ['KASIR', 'QRIS'], true)) {
            $methodType = strtoupper(trim((string)($payment['method_type'] ?? '')));
            $mode = $methodType === 'QRIS' ? 'QRIS' : 'KASIR';
        }
        return $mode !== '' ? $mode : 'KASIR';
    }

    private function self_order_flow_state(array $order, array $payment): array
    {
        $paymentMode = strtoupper(trim((string)($payment['payment_mode'] ?? 'KASIR')));
        $paymentStatus = strtoupper(trim((string)($payment['payment_status_label'] ?? ($payment['payment_status'] ?? 'PENDING'))));
        $orderStatus = strtoupper(trim((string)($order['status'] ?? 'PENDING')));
        $stockCommitStatus = strtoupper(trim((string)($order['stock_commit_status'] ?? 'PENDING')));
        $isRejected = $this->self_order_is_rejected_status($orderStatus);

        $isPaid = in_array($paymentStatus, ['PAID', 'SUCCESS', 'SETTLED'], true)
            || in_array($orderStatus, ['PAID', 'PAID_PARTIAL', 'READY', 'SERVED', 'REFUND_PARTIAL', 'REFUND_FULL', 'REFUNDED_FULL'], true)
            || round((float)($order['paid_total'] ?? 0), 2) >= round((float)($order['grand_total'] ?? 0), 2);

        $verified = in_array($stockCommitStatus, ['QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED', 'NOT_REQUIRED'], true);
        $canVerify = !$isRejected && !$verified && ($paymentMode === 'KASIR' || ($paymentMode === 'QRIS' && $isPaid));
        $canReject = !$isRejected && !$verified && !$isPaid;
        $verifyMessage = '';
        $rejectMessage = '';
        $paidWorkspaceStatuses = ['PAID', 'PAID_PARTIAL', 'REFUND_PARTIAL', 'REFUND_FULL', 'REFUNDED_FULL'];
        $isPaidWorkspace = in_array($orderStatus, $paidWorkspaceStatuses, true);

        if ($isRejected) {
            $flowCode = 'REJECTED';
            $flowLabel = 'Ditolak Kasir';
            $verifyMessage = 'Order ini sudah ditolak dan tidak bisa diverifikasi lagi.';
            $rejectMessage = 'Order ini sudah ditolak.';
        } elseif ($verified) {
            $flowCode = $isPaidWorkspace ? 'PAID_ORDER' : 'ACTIVE_CASHIER';
            $flowLabel = $isPaidWorkspace ? 'Masuk Pesanan Terbayar' : 'Masuk Order Aktif';
            $rejectMessage = 'Order sudah diverifikasi. Gunakan void/refund dari workspace POS bila perlu pembatalan lanjutan.';
        } elseif ($paymentMode === 'QRIS' && !$isPaid) {
            $flowCode = 'WAITING_PAYMENT';
            $flowLabel = 'Menunggu Pembayaran QRIS';
            $verifyMessage = 'Order ini masih menunggu pembayaran QRIS dari customer.';
            $rejectMessage = 'Order bisa ditolak selama pembayaran QRIS belum masuk.';
        } else {
            $flowCode = 'NEEDS_VERIFY';
            $flowLabel = 'Perlu Verifikasi Kasir';
            $verifyMessage = 'Order siap diverifikasi untuk dicetak dan diproses.';
            $rejectMessage = 'Order bisa ditolak sebelum diverifikasi.';
        }

        if (!$isRejected && !$verified && $isPaid) {
            $rejectMessage = 'Order QRIS yang sudah dibayar tidak bisa ditolak dari halaman ini. Lanjutkan dengan verifikasi lalu refund bila memang harus dibatalkan.';
        }

        return [
            'payment_mode' => $paymentMode,
            'payment_status' => $paymentStatus !== '' ? $paymentStatus : 'PENDING',
            'payment_provider' => trim((string)($payment['payment_provider_label'] ?? '')),
            'flow_code' => $flowCode,
            'flow_label' => $flowLabel,
            'is_paid' => $isPaid ? 1 : 0,
            'is_rejected' => $isRejected ? 1 : 0,
            'is_verified' => $verified ? 1 : 0,
            'can_verify' => $canVerify ? 1 : 0,
            'verify_message' => $verifyMessage,
            'can_reject' => $canReject ? 1 : 0,
            'reject_message' => $rejectMessage,
        ];
    }

    public function payment_method_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $methodType = strtoupper(trim((string)($filters['method_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('pos_payment_method pm')
            ->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('pm.method_code', $q)
                ->or_like('pm.method_name', $q)
                ->or_like('pm.method_type', $q)
                ->or_like('acc.bank_name', $q)
                ->or_like('acc.account_name', $q)
                ->or_like('acc.account_no', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('pm.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('pm.is_active', 0);
        }
        if (in_array($methodType, ['CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $db->where('pm.method_type', $methodType);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('pm.*, acc.bank_name, acc.account_name, acc.account_no, acc.account_type')
            ->order_by('pm.method_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function deposit_rows(array $filters): array
    {
        if (!$this->db->table_exists('pos_payment')) {
            return [
                'rows' => [],
                'summary' => [
                    'deposit_count' => 0,
                    'total_deposit_amount' => 0,
                    'total_applied_amount' => 0,
                    'total_remaining_amount' => 0,
                    'open_deposit_count' => 0,
                ],
                'meta' => ['total' => 0, 'page' => 1, 'limit' => 50, 'total_pages' => 1],
            ];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $paymentStatus = strtoupper(trim((string)($filters['payment_status'] ?? 'PAID')));
        $settlementStatus = strtoupper(trim((string)($filters['settlement_status'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $hasApplyTable = $this->db->table_exists('pos_payment_deposit_apply');
        $hasPaymentLine = $this->db->table_exists('pos_payment_line');

        $db = $this->coredb;
        $db->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left');

        if ($hasApplyTable) {
            $db->join('pos_payment_deposit_apply dpa', "dpa.deposit_payment_id = p.id AND dpa.apply_status = 'APPLIED'", 'left');
        }
        if ($hasPaymentLine) {
            $db->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left');
            $db->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');
        }

        $db->where('p.payment_type', 'DEPOSIT');
        if ($q !== '') {
            $db->group_start()
                ->like('p.payment_no', $q)
                ->or_like('o.order_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->group_end();
        }
        if (in_array($paymentStatus, ['PAID', 'PENDING', 'VOID', 'FAILED'], true)) {
            $db->where('p.payment_status', $paymentStatus);
        }

        $remainingExpr = '(COALESCE(p.net_amount,0) - COALESCE(p.deposit_applied_amount,0))';
        if ($settlementStatus === 'OPEN') {
            $db->where($remainingExpr . ' >', 0, false);
            $db->where('COALESCE(p.deposit_applied_amount,0) =', 0, false);
        } elseif ($settlementStatus === 'PARTIAL') {
            $db->where('COALESCE(p.deposit_applied_amount,0) >', 0, false);
            $db->where($remainingExpr . ' >', 0, false);
        } elseif ($settlementStatus === 'FULL') {
            $db->where($remainingExpr . ' <=', 0, false);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $applyCountSelect = $hasApplyTable ? 'COUNT(DISTINCT dpa.id)' : '0';
        $methodSelect = $hasPaymentLine ? "GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ')" : "''";

        $rows = $db->select("
                p.id,
                p.payment_no,
                p.order_id,
                p.member_id,
                p.payment_status,
                p.net_amount,
                p.deposit_applied_amount,
                p.paid_at,
                  p.created_at,
                  o.order_no,
                  m.member_name,
                  m.mobile_phone,
                  GREATEST(COALESCE(p.net_amount,0) - COALESCE(p.deposit_applied_amount,0), 0) AS remaining_amount,
                {$applyCountSelect} AS apply_count,
                {$methodSelect} AS payment_method_names
            ", false)
            ->group_by('p.id')
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'DESC', false)
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        $summaryDb = $this->coredb
            ->select("
                COUNT(*) AS deposit_count,
                COALESCE(SUM(COALESCE(net_amount,0)),0) AS total_deposit_amount,
                COALESCE(SUM(COALESCE(deposit_applied_amount,0)),0) AS total_applied_amount,
                COALESCE(SUM(GREATEST(COALESCE(net_amount,0) - COALESCE(deposit_applied_amount,0), 0)),0) AS total_remaining_amount,
                COALESCE(SUM(CASE WHEN GREATEST(COALESCE(net_amount,0) - COALESCE(deposit_applied_amount,0), 0) > 0 THEN 1 ELSE 0 END),0) AS open_deposit_count
            ", false)
            ->from('pos_payment')
            ->where('payment_type', 'DEPOSIT');
        if (in_array($paymentStatus, ['PAID', 'PENDING', 'VOID', 'FAILED'], true)) {
            $summaryDb->where('payment_status', $paymentStatus);
        }
        $summary = $summaryDb->get()->row_array() ?: [];

        return [
            'rows' => $rows,
            'summary' => [
                'deposit_count' => (int)($summary['deposit_count'] ?? 0),
                'total_deposit_amount' => round((float)($summary['total_deposit_amount'] ?? 0), 2),
                'total_applied_amount' => round((float)($summary['total_applied_amount'] ?? 0), 2),
                'total_remaining_amount' => round((float)($summary['total_remaining_amount'] ?? 0), 2),
                'open_deposit_count' => (int)($summary['open_deposit_count'] ?? 0),
            ],
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function save_deposit(array $payload, int $actorEmployeeId, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('pos_payment') || !$this->db->table_exists('pos_payment_line')) {
            return ['ok' => false, 'message' => 'Schema payment POS belum tersedia. Jalankan migration payment terlebih dulu.'];
        }
        if (!$this->db->field_exists('order_id', 'pos_payment')) {
            return ['ok' => false, 'message' => 'Schema payment POS belum lengkap.'];
        }
        $orderIdNullable = $this->column_is_nullable('pos_payment', 'order_id');
        if (!$orderIdNullable) {
            return ['ok' => false, 'message' => 'Schema DP belum siap. Jalankan patch agar pos_payment.order_id boleh NULL untuk deposit mandiri.'];
        }

        $memberId = !empty($payload['member_id']) ? (int)$payload['member_id'] : null;
        $memberName = trim((string)($payload['member_name'] ?? ''));
        $mobilePhone = trim((string)($payload['mobile_phone'] ?? ''));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $paymentMethodId = !empty($payload['payment_method_id']) ? (int)$payload['payment_method_id'] : 0;
        $notes = $this->nullable_text($payload['notes'] ?? '');

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal DP wajib lebih besar dari nol.'];
        }
        if ($paymentMethodId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP wajib dipilih.'];
        }
        $method = $this->find_payment_method($paymentMethodId);
        if (!$method || (int)($method['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP tidak valid atau tidak aktif.'];
        }
        $companyAccountId = (int)($method['company_account_id'] ?? 0);
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP belum terhubung ke rekening perusahaan.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Schema keuangan belum siap. DP harus terhubung ke rekening perusahaan terlebih dulu.'];
        }
        $companyAccount = $this->db->query(
            'SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1',
            [$companyAccountId]
        )->row_array();
        if (!$companyAccount) {
            return ['ok' => false, 'message' => 'Rekening perusahaan untuk metode pembayaran ini tidak ditemukan atau tidak aktif.'];
        }
        if ($memberId === null && $memberName === '') {
            return ['ok' => false, 'message' => 'Pilih member atau isi nama customer untuk DP.'];
        }
        if ($memberId !== null && !$this->local_record_exists('crm_member', $memberId)) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        $activeSession = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        $cashierEmployeeId = $actorEmployeeId > 0 ? $actorEmployeeId : null;
        $mutationCreatedBy = $actorUserId > 0
            ? $actorUserId
            : ($actorEmployeeId > 0 ? $actorEmployeeId : null);
        $this->db->trans_begin();
        try {
            if ($memberId === null) {
                $memberSave = $this->save_member([
                    'member_name' => $memberName,
                    'mobile_phone' => $mobilePhone,
                    'member_status' => 'ACTIVE',
                    'joined_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Auto dibuat dari input DP POS',
                ]);
                if (!($memberSave['ok'] ?? false)) {
                    throw new RuntimeException((string)($memberSave['message'] ?? 'Gagal membuat member dari input DP.'));
                }
                $memberId = (int)($memberSave['id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Member DP gagal dibuat.');
                }
            }

            $now = date('Y-m-d H:i:s');
            $paymentNo = $this->generate_pos_payment_no('DEPOSIT', $now);
            $paymentPayload = [
                'payment_no' => $paymentNo,
                'order_id' => null,
                'shift_id' => !empty($activeSession['shift_id']) ? (int)$activeSession['shift_id'] : null,
                'cashier_session_id' => !empty($activeSession['id']) ? (int)$activeSession['id'] : null,
                // Reservation DP may be received before a cashier is opened.
                // Keep the employee nullable in that case; the reservation and
                // account mutation retain the logged-in user as the auditor.
                'cashier_employee_id' => $cashierEmployeeId,
                'member_id' => $memberId,
                'payment_type' => 'DEPOSIT',
                'payment_status' => 'PAID',
                'paid_at' => $now,
                'gross_amount' => $amount,
                'discount_amount' => 0,
                'promo_amount' => 0,
                'voucher_amount' => 0,
                'point_redeem_amount' => 0,
                'compliment_amount' => 0,
                'deposit_applied_amount' => 0,
                'net_amount' => $amount,
                'change_amount' => 0,
                'notes' => $notes,
                'created_at' => $now,
            ];
            $this->db->insert('pos_payment', $paymentPayload);
            $paymentId = (int)$this->db->insert_id();
            if ($paymentId <= 0) {
                throw new RuntimeException('Gagal membuat header DP.');
            }

            $paymentLinePayload = [
                'payment_id' => $paymentId,
                'line_no' => 1,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'reference_no' => $this->nullable_text($payload['reference_no'] ?? ''),
                'gateway_txn_id' => null,
                'received_at' => $now,
                'status' => 'PAID',
                'created_at' => $now,
            ];
            if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
                $paymentLinePayload['company_account_id'] = $companyAccountId;
            }
            $this->db->insert('pos_payment_line', $paymentLinePayload);

            $financeResult = $this->post_company_account_mutation([
                'account_id' => $companyAccountId,
                'mutation_type' => 'IN',
                'amount' => $amount,
                'mutation_date' => $now,
                'ref_module' => 'POS',
                'ref_table' => 'pos_payment',
                'ref_id' => $paymentId,
                'ref_no' => $paymentNo,
                'notes' => 'DP POS diterima',
                'created_by' => $mutationCreatedBy,
            ]);
            if (!($financeResult['ok'] ?? false)) {
                throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal posting DP ke rekening perusahaan.'));
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan DP.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'id' => $paymentId,
                'payment_no' => $paymentNo,
                'member_id' => $memberId,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function void_deposit(int $id, int $actorEmployeeId, int $actorUserId = 0): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Deposit tidak valid.'];
        }
        $row = $this->db->from('pos_payment')
            ->where('id', $id)
            ->where('payment_type', 'DEPOSIT')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Deposit tidak ditemukan.'];
        }
        if (round((float)($row['deposit_applied_amount'] ?? 0), 2) > 0) {
            return ['ok' => false, 'message' => 'Deposit sudah dipakai sebagian/seluruhnya dan tidak bisa di-void langsung.'];
        }
        if (strtoupper((string)($row['payment_status'] ?? '')) === 'VOID') {
            return ['ok' => true, 'id' => $id];
        }

        $line = $this->db->from('pos_payment_line')
            ->where('payment_id', $id)
            ->where('status', 'PAID')
            ->order_by('line_no', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$line) {
            return ['ok' => false, 'message' => 'Line pembayaran DP tidak ditemukan.'];
        }
        $companyAccountId = 0;
        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $companyAccountId = (int)($line['company_account_id'] ?? 0);
        }
        if ($companyAccountId <= 0) {
            $method = $this->find_payment_method((int)($line['payment_method_id'] ?? 0));
            $companyAccountId = (int)($method['company_account_id'] ?? 0);
        }
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening perusahaan untuk reversal DP tidak ditemukan.'];
        }

        if (!$this->db->field_exists('reversal_of_mutation_id', 'fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'VOID DP aman belum siap. Jalankan migration 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql terlebih dahulu.'];
        }
        $originMutation = $this->db->select('m.id')
            ->from('fin_account_mutation_log m')
            ->where('m.ref_module', 'POS')
            ->where('m.ref_table', 'pos_payment')
            ->where('m.ref_id', $id)
            ->where('m.account_id', $companyAccountId)
            ->where('m.mutation_type', 'IN')
            ->where('m.reversal_of_mutation_id IS NULL', null, false)
            ->where('NOT EXISTS (SELECT 1 FROM fin_account_mutation_log r WHERE r.reversal_of_mutation_id = m.id)', null, false)
            ->order_by('m.id', 'DESC')
            ->limit(1)
            ->get()->row_array();
        if (!$originMutation) {
            return ['ok' => false, 'message' => 'Mutasi DP asal tidak ditemukan atau sudah pernah dibatalkan.'];
        }

        $this->db->trans_begin();
        try {
            $actorLabel = $actorUserId > 0
                ? 'user ' . $actorUserId
                : ($actorEmployeeId > 0 ? 'employee ' . $actorEmployeeId : 'system');
            $mutationCreatedBy = $actorUserId > 0
                ? $actorUserId
                : ($actorEmployeeId > 0 ? $actorEmployeeId : null);
            $this->db->where('id', $id)->update('pos_payment', [
                'payment_status' => 'VOID',
                'notes' => trim((string)($row['notes'] ?? '') . ' | VOID by ' . $actorLabel),
            ]);
            $this->db->where('payment_id', $id)->update('pos_payment_line', ['status' => 'VOID']);

            $financeResult = $this->post_company_account_mutation([
                'account_id' => $companyAccountId,
                'mutation_type' => 'OUT',
                'amount' => round((float)($row['net_amount'] ?? 0), 2),
                'mutation_date' => date('Y-m-d H:i:s'),
                'ref_module' => 'POS',
                'ref_table' => 'pos_payment',
                'ref_id' => $id,
                'ref_no' => (string)($row['payment_no'] ?? null),
                'notes' => 'Pembatalan DP POS',
                'created_by' => $mutationCreatedBy,
                'reversal_of_mutation_id' => (int)($originMutation['id'] ?? 0),
            ]);
            if (!($financeResult['ok'] ?? false)) {
                throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal reversal DP dari rekening perusahaan.'));
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal void DP.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function cashier_payment_prepare(int $orderId, int $actorEmployeeId): array
    {
        $session = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        if (!$session) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka.'];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
        }

        $header = (array)($order['header'] ?? []);
        if ((int)($header['cashier_session_id'] ?? 0) !== (int)($session['id'] ?? 0)) {
            return ['ok' => false, 'message' => 'Order ini bukan bagian dari sesi kasir yang sedang aktif.'];
        }

        $status = strtoupper(trim((string)($header['status'] ?? 'DRAFT')));
        if (!$this->is_cashier_payment_allowed_status($status)) {
            return ['ok' => false, 'message' => 'Order ini belum masuk tahap yang bisa dibayar dari kasir.'];
        }

        $baseTotal = $this->current_order_payment_base_total($orderId);
        $currentGrandTotal = round((float)($header['grand_total'] ?? $baseTotal), 2);
        if ($currentGrandTotal <= 0) {
            $currentGrandTotal = $baseTotal;
        }
        $paidTotal = round((float)($header['paid_total'] ?? 0), 2);
        $dueTotal = round(max(0, $currentGrandTotal - $paidTotal), 2);
        if ($dueTotal <= 0) {
            return ['ok' => false, 'message' => 'Order ini sudah tidak memiliki tagihan pembayaran.'];
        }
        $memberId = !empty($header['member_id']) ? (int)$header['member_id'] : 0;
        $memberRow = $memberId > 0 ? $this->find_member($memberId) : null;
        $memberPointBalance = round((float)($memberRow['point_balance_cache'] ?? 0), 4);
        $memberStampBalance = $memberId > 0 ? $this->current_member_stamp_balance($memberId) : 0.0;
        $memberVoucherRows = $this->cashier_member_voucher_rows($memberId, $order, $baseTotal);
        $depositPreview = $this->cashier_member_deposit_preview($memberId, $dueTotal);

        return [
            'ok' => true,
            'payment' => [
                'order_id' => (int)($header['id'] ?? 0),
                'order_no' => (string)($header['order_no'] ?? ''),
                'order_status' => $status,
                'customer_name' => $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? ''),
                'member_id' => $memberId > 0 ? $memberId : null,
                'member_name' => (string)($header['member_name'] ?? ''),
                'member_point_balance' => $memberPointBalance,
                'member_stamp_balance' => $memberStampBalance,
                'base_total' => $baseTotal,
                'grand_total' => $currentGrandTotal,
                'paid_total' => $paidTotal,
                'due_total_before_deposit' => $dueTotal,
                'due_total' => round((float)($depositPreview['cash_due_total'] ?? $dueTotal), 2),
                'can_edit_adjustment' => $paidTotal <= 0.009,
                'payment_methods' => $this->deposit_payment_method_options(),
                'voucher_options' => $this->cashier_voucher_options_for_order($order, $baseTotal),
                'deposit_available_total' => round((float)($depositPreview['available_total'] ?? 0), 2),
                'deposit_applied_total' => round((float)($depositPreview['applied_total'] ?? 0), 2),
                'deposit_remaining_total' => round((float)($depositPreview['remaining_total'] ?? 0), 2),
                'deposit_rows' => (array)($depositPreview['rows'] ?? []),
                'loyalty_summary' => [
                    'open_voucher_count' => count($memberVoucherRows),
                    'point_balance' => $memberPointBalance,
                    'stamp_balance' => $memberStampBalance,
                ],
            ],
        ];
    }

    private function cashier_member_open_deposit_rows(int $memberId, bool $forUpdate = false): array
    {
        if ($memberId <= 0 || !$this->db->table_exists('pos_payment')) {
            return [];
        }

        $hasPaymentLine = $this->db->table_exists('pos_payment_line');
        $remainingExpr = 'GREATEST(COALESCE(p.net_amount,0) - COALESCE(p.deposit_applied_amount,0), 0)';
        $paymentMethodSelect = $hasPaymentLine
            ? ", GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_method_names"
            : ", '' AS payment_method_names";
        $paymentLineJoin = $hasPaymentLine
            ? ' LEFT JOIN pos_payment_line pl ON pl.payment_id = p.id LEFT JOIN pos_payment_method pm ON pm.id = pl.payment_method_id '
            : '';
        $forUpdateSql = $forUpdate ? ' FOR UPDATE' : '';
        // A DP attached to an unverified reservation is not generic member
        // credit yet. It becomes available again only when the reservation is
        // rejected/cancelled without a refund, or is applied during verify.
        $reservationExclusion = '';
        if ($this->db->table_exists('pos_reservation_payment') && $this->db->table_exists('pos_reservation')) {
            $reservationExclusion = "\n              AND NOT EXISTS (\n                SELECT 1\n                FROM pos_reservation_payment reservation_payment\n                JOIN pos_reservation reservation ON reservation.id = reservation_payment.reservation_id\n                WHERE reservation_payment.payment_id = p.id\n                  AND reservation.status = 'PENDING'\n                  AND reservation_payment.link_status IN ('OPEN', 'PARTIAL')\n              )";
        }

        $sql = "
            SELECT
                p.id,
                p.payment_no,
                p.member_id,
                p.net_amount,
                p.deposit_applied_amount,
                COALESCE(p.paid_at, p.created_at) AS paid_at,
                {$remainingExpr} AS remaining_amount
                {$paymentMethodSelect}
            FROM pos_payment p
            {$paymentLineJoin}
            WHERE p.member_id = ?
              AND p.payment_type = 'DEPOSIT'
              AND p.payment_status = 'PAID'
              AND {$remainingExpr} > 0
              {$reservationExclusion}
            GROUP BY p.id
            ORDER BY COALESCE(p.paid_at, p.created_at) ASC, p.id ASC
            {$forUpdateSql}
        ";

        return $this->db->query($sql, [$memberId])->result_array();
    }

    private function cashier_member_deposit_preview(int $memberId, float $dueTotal, bool $forUpdate = false): array
    {
        $targetDue = round(max(0, $dueTotal), 2);
        $availableTotal = 0.0;
        $appliedTotal = 0.0;
        $remainingNeed = $targetDue;
        $rows = [];

        foreach ($this->cashier_member_open_deposit_rows($memberId, $forUpdate) as $row) {
            $remainingAmount = round(max(0, (float)($row['remaining_amount'] ?? 0)), 2);
            if ($remainingAmount <= 0) {
                continue;
            }

            $previewApplied = round(min($remainingAmount, $remainingNeed), 2);
            $availableTotal += $remainingAmount;
            $appliedTotal += $previewApplied;
            $remainingNeed = round(max(0, $remainingNeed - $previewApplied), 2);

            $row['net_amount'] = round((float)($row['net_amount'] ?? 0), 2);
            $row['deposit_applied_amount'] = round((float)($row['deposit_applied_amount'] ?? 0), 2);
            $row['remaining_amount'] = $remainingAmount;
            $row['preview_applied_amount'] = $previewApplied;
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'available_total' => round($availableTotal, 2),
            'applied_total' => round($appliedTotal, 2),
            'remaining_total' => round(max(0, $availableTotal - $appliedTotal), 2),
            'cash_due_total' => round(max(0, $targetDue - $appliedTotal), 2),
        ];
    }

    private function apply_cashier_member_deposit_usage(int $orderId, int $paymentId, array $depositRows, string $now): void
    {
        $hasApplyTable = $this->db->table_exists('pos_payment_deposit_apply');

        foreach ($depositRows as $row) {
            $depositId = (int)($row['id'] ?? 0);
            $appliedAmount = round((float)($row['preview_applied_amount'] ?? 0), 2);
            if ($depositId <= 0 || $appliedAmount <= 0) {
                continue;
            }

            $this->db->set('deposit_applied_amount', 'COALESCE(deposit_applied_amount,0)+' . $this->db->escape($appliedAmount), false)
                ->where('id', $depositId)
                ->update('pos_payment');

            if ($hasApplyTable) {
                $this->db->insert('pos_payment_deposit_apply', $this->filter_table_payload('pos_payment_deposit_apply', [
                    'deposit_payment_id' => $depositId,
                    'applied_payment_id' => $paymentId,
                    'order_id' => $orderId > 0 ? $orderId : null,
                    'applied_amount' => $appliedAmount,
                    'apply_status' => 'APPLIED',
                    'notes' => 'Auto apply DP saat pembayaran kasir POS',
                    'applied_at' => $now,
                    'created_at' => $now,
                ]));
            }
        }
    }

    public function save_cashier_payment(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee.'];
        }
        if (!$this->db->table_exists('pos_payment') || !$this->db->table_exists('pos_payment_line')) {
            return ['ok' => false, 'message' => 'Schema payment POS belum siap.'];
        }

        $session = $this->find_active_cashier_session($actorEmployeeId);
        if (!$session) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka.'];
        }

        $orderId = (int)($payload['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order pembayaran tidak valid.'];
        }

        $now = date('Y-m-d H:i:s');
        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $orderRow = $this->db->query('SELECT * FROM pos_order WHERE id = ? LIMIT 1 FOR UPDATE', [$orderId])->row_array();
            if (!$orderRow) {
                throw new RuntimeException('Order POS tidak ditemukan.');
            }
            if ((int)($orderRow['cashier_session_id'] ?? 0) !== (int)($session['id'] ?? 0)) {
                throw new RuntimeException('Order ini bukan bagian dari sesi kasir yang sedang aktif.');
            }

            $status = strtoupper(trim((string)($orderRow['status'] ?? 'DRAFT')));
            if (!$this->is_cashier_payment_allowed_status($status)) {
                throw new RuntimeException('Order ini belum masuk tahap yang bisa dibayar dari kasir.');
            }

            $baseTotal = $this->current_order_payment_base_total($orderId);
            $existingPaidTotal = round((float)($orderRow['paid_total'] ?? 0), 2);
            $canEditAdjustment = $existingPaidTotal <= 0.009;

            $discountAmount = 0.0;
            $promoAmount = 0.0;
            $voucherAmount = 0.0;
            $pointRedeemAmount = 0.0;
            $complimentAmount = 0.0;
            $voucherRedemption = null;

            if ($canEditAdjustment) {
                $voucherResolution = $this->resolve_cashier_voucher_application($orderId, $payload, $baseTotal);
                if (!($voucherResolution['ok'] ?? false)) {
                    throw new RuntimeException((string)($voucherResolution['message'] ?? 'Voucher pembayaran tidak valid.'));
                }
                $voucherAmount = round((float)($voucherResolution['voucher_amount'] ?? 0), 2);
                $promoAmount = round((float)($voucherResolution['promo_amount'] ?? 0), 2);
                $voucherRedemption = !empty($voucherResolution['redemption']) && is_array($voucherResolution['redemption'])
                    ? (array)$voucherResolution['redemption']
                    : null;
                $complimentAmount = round(max(0, (float)($payload['compliment_amount'] ?? 0)), 2);
            } else {
                $discountAmount = round((float)($orderRow['discount_amount'] ?? 0), 2);
                $promoAmount = round((float)($orderRow['promo_amount'] ?? 0), 2);
                $voucherAmount = round((float)($orderRow['voucher_amount'] ?? 0), 2);
                $pointRedeemAmount = round((float)($orderRow['point_redeem_amount'] ?? 0), 2);
                $complimentAmount = round((float)($orderRow['compliment_amount'] ?? 0), 2);
            }

            $grandTotal = round(max(0, $baseTotal - $discountAmount - $promoAmount - $voucherAmount - $pointRedeemAmount - $complimentAmount), 2);
            $dueTotal = round(max(0, $grandTotal - $existingPaidTotal), 2);
            // Voucher/promo may legitimately close a fresh order without a cash payment line.
            $closedByAdjustment = $canEditAdjustment
                && $dueTotal <= 0.009
                && ($voucherAmount + $promoAmount + $pointRedeemAmount + $complimentAmount) > 0.009;
            if ($dueTotal <= 0.009 && !$closedByAdjustment) {
                throw new RuntimeException('Order ini sudah tidak memiliki sisa tagihan.');
            }

            $memberId = !empty($orderRow['member_id']) ? (int)$orderRow['member_id'] : 0;
            $depositPreview = $this->cashier_member_deposit_preview($memberId, $dueTotal, true);
            $depositAppliedAmount = round((float)($depositPreview['applied_total'] ?? 0), 2);
            $cashDueTotal = round((float)($depositPreview['cash_due_total'] ?? $dueTotal), 2);

            $paymentLines = [];
            $paidNow = 0.0;
            $enteredNow = 0.0;
            $changeTotal = 0.0;
            $remainingDue = $cashDueTotal;
            if ($cashDueTotal > 0.009) {
                $normalizedLines = $this->normalize_cashier_payment_lines($payload, $cashDueTotal);
                if (!($normalizedLines['ok'] ?? false)) {
                    throw new RuntimeException((string)($normalizedLines['message'] ?? 'Metode pembayaran tidak valid.'));
                }
                $paymentLines = (array)($normalizedLines['rows'] ?? []);
                $paidNow = round((float)($normalizedLines['total_amount'] ?? 0), 2);
                $enteredNow = round((float)($normalizedLines['entered_total'] ?? $paidNow), 2);
                $changeTotal = round((float)($normalizedLines['change_total'] ?? 0), 2);
                $remainingDue = round(max(0, $cashDueTotal - $paidNow), 2);
                if ($paidNow <= 0) {
                    throw new RuntimeException('Masukkan minimal satu metode pembayaran dengan nominal valid.');
                }
            }
            $isFullyPaid = $remainingDue <= 0.009;
            $nextOrderStatus = $isFullyPaid ? 'PAID' : 'PAID_PARTIAL';

            $reservationSettlement = $this->pending_reservation_settlement_payment($orderId);
            $reservationDepositApplied = round((float)($reservationSettlement['deposit_applied_amount'] ?? 0), 2);
            $paymentNo = $reservationSettlement
                ? (string)($reservationSettlement['payment_no'] ?? '')
                : $this->generate_pos_payment_no('FINAL', $now);
            if ($paymentNo === '') {
                $paymentNo = $this->generate_pos_payment_no('FINAL', $now);
            }
            $sessionShiftId = !empty($session['shift_id']) ? (int)$session['shift_id'] : 0;
            $paymentShiftId = $this->local_record_exists('pos_shift', $sessionShiftId) ? $sessionShiftId : null;
            $paymentPayload = [
                'payment_no' => $paymentNo,
                'order_id' => $orderId,
                'shift_id' => $paymentShiftId,
                'cashier_session_id' => !empty($session['id']) ? (int)$session['id'] : null,
                'cashier_employee_id' => $actorEmployeeId,
                'member_id' => !empty($orderRow['member_id']) ? (int)$orderRow['member_id'] : null,
                'payment_type' => 'FINAL',
                'payment_status' => 'PAID',
                'paid_at' => $now,
                'gross_amount' => $baseTotal,
                'discount_amount' => $discountAmount,
                'promo_amount' => $promoAmount,
                'voucher_amount' => $voucherAmount,
                'point_redeem_amount' => $pointRedeemAmount,
                'compliment_amount' => $complimentAmount,
                // The reservation DP was already applied at verification. A
                // subsequent cashier payment may still use another open DP.
                'deposit_applied_amount' => round($reservationDepositApplied + $depositAppliedAmount, 2),
                'net_amount' => $grandTotal,
                'change_amount' => $changeTotal,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
                'created_at' => $now,
            ];
            if ($reservationSettlement) {
                $paymentId = (int)($reservationSettlement['id'] ?? 0);
                $this->db->where('id', $paymentId)->update('pos_payment', $this->filter_table_payload('pos_payment', $paymentPayload));
            } else {
                $this->db->insert('pos_payment', $this->filter_table_payload('pos_payment', $paymentPayload));
                $paymentId = (int)$this->db->insert_id();
            }
            if ($paymentId <= 0) {
                throw new RuntimeException($this->db_error_message('Gagal membuat dokumen pembayaran POS.'));
            }

            $paymentLineNo = 1;
            foreach ($paymentLines as $line) {
                $paymentLinePayload = [
                    'payment_id' => $paymentId,
                    'line_no' => $paymentLineNo++,
                    'payment_method_id' => (int)$line['payment_method_id'],
                    'amount' => round((float)$line['amount'], 2),
                    'reference_no' => $this->nullable_text($line['reference_no'] ?? ''),
                    'gateway_txn_id' => null,
                    'received_at' => $now,
                    'status' => 'PAID',
                    'created_at' => $now,
                ];
                $this->db->insert('pos_payment_line', $this->filter_table_payload('pos_payment_line', $paymentLinePayload));
                $paymentLineId = (int)$this->db->insert_id();
                if ($paymentLineId <= 0) {
                    throw new RuntimeException($this->db_error_message('Gagal menyimpan detail pembayaran POS.'));
                }

                $financeResult = $this->post_company_account_mutation([
                    'account_id' => (int)($line['company_account_id'] ?? 0),
                    'mutation_type' => 'IN',
                    'amount' => round((float)$line['amount'], 2),
                    'mutation_date' => $now,
                    'ref_module' => 'POS',
                    'ref_table' => 'pos_payment_line',
                    'ref_id' => $paymentLineId,
                    'ref_no' => $paymentNo,
                    'notes' => 'Pembayaran POS via ' . (string)($line['method_name'] ?? 'metode pembayaran'),
                    'created_by' => $actorEmployeeId,
                ]);
                if (!($financeResult['ok'] ?? false)) {
                    throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal posting pembayaran POS ke mutasi rekening.'));
                }
            }

            if ($depositAppliedAmount > 0) {
                $this->apply_cashier_member_deposit_usage($orderId, $paymentId, (array)($depositPreview['rows'] ?? []), $now);
            }

            $orderUpdate = [
                'status' => $nextOrderStatus,
                'discount_amount' => $discountAmount,
                'promo_amount' => $promoAmount,
                'voucher_amount' => $voucherAmount,
                'point_redeem_amount' => $pointRedeemAmount,
                'compliment_amount' => $complimentAmount,
                'grand_total' => $grandTotal,
                'paid_total' => round($existingPaidTotal + $depositAppliedAmount + $paidNow, 2),
                'change_total' => $changeTotal,
            ];
            if ($isFullyPaid) {
                $orderUpdate['paid_at'] = $now;
            }
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $orderUpdate));
            $this->insert_order_state_log($orderId, $status, $nextOrderStatus, 'ORDER_PAYMENT', $actorEmployeeId, 'Pembayaran POS #' . $paymentNo);

            if ($voucherRedemption) {
                $this->apply_cashier_voucher_redemption($voucherRedemption, $orderId, $paymentId, $actorEmployeeId, $now);
            }

            $loyaltySummary = ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
            if ($isFullyPaid) {
                $updatedOrder = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array() ?: $orderRow;
                $loyaltySummary = $this->apply_cashier_payment_loyalty((array)$updatedOrder, $paymentId, $now);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal menyimpan pembayaran POS.'));
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;

            return [
                'ok' => true,
                'id' => $paymentId,
                'payment_no' => $paymentNo,
                'order_status' => $nextOrderStatus,
                'paid_now' => $paidNow,
                'entered_now' => $enteredNow,
                'change_total' => $changeTotal,
                'deposit_applied_amount' => round($reservationDepositApplied + $depositAppliedAmount, 2),
                'remaining_due' => $remainingDue,
                'loyalty' => $loyaltySummary,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function is_cashier_payment_allowed_status(string $status): bool
    {
        return in_array($status, ['CONFIRMED', 'PAID_PARTIAL', 'IN_KITCHEN', 'READY', 'SERVED'], true);
    }

    /**
     * A reservation can have a partial DP before it reaches the cashier.
     * Reuse its pending FINAL payment so the later cash settlement remains
     * one payment document rather than creating a duplicate sale record.
     */
    private function pending_reservation_settlement_payment(int $orderId): ?array
    {
        if ($orderId <= 0
            || !$this->db->table_exists('pos_reservation')
            || !$this->db->field_exists('settlement_payment_id', 'pos_reservation')) {
            return null;
        }

        $row = $this->db
            ->select('p.id, p.payment_no, p.deposit_applied_amount')
            ->from('pos_reservation r')
            ->join('pos_payment p', 'p.id = r.settlement_payment_id', 'inner')
            ->where('r.order_id', $orderId)
            ->where('p.payment_type', 'FINAL')
            ->where('p.payment_status', 'PENDING')
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    private function current_order_payment_base_total(int $orderId): float
    {
        if ($orderId <= 0) {
            return 0.0;
        }

        $productAgg = $this->db->select('COALESCE(SUM(net_amount), 0) AS total', false)
            ->from('pos_order_line')
            ->where('order_id', $orderId)
            ->where('qty >', 0)
            ->where_not_in('line_status', ['VOID', 'REFUNDED_FULL'])
            ->get()
            ->row_array() ?: [];
        $extraAgg = $this->db->select('COALESCE(SUM(net_amount), 0) AS total', false)
            ->from('pos_order_line_extra')
            ->where('order_id', $orderId)
            ->where('qty >', 0)
            ->get()
            ->row_array() ?: [];

        return round((float)($productAgg['total'] ?? 0) + (float)($extraAgg['total'] ?? 0), 2);
    }

    private function normalize_cashier_payment_lines(array $payload, float $dueAmount): array
    {
        $methodIds = $payload['payment_method_ids'] ?? [];
        $amounts = $payload['paid_amounts'] ?? [];
        $refs = $payload['reference_nos'] ?? [];

        if (!is_array($methodIds)) {
            $methodIds = [$payload['payment_method_id'] ?? 0];
        }
        if (!is_array($amounts)) {
            $amounts = [$payload['paid_amount'] ?? 0];
        }
        if (!is_array($refs)) {
            $refs = [$payload['reference_no'] ?? ''];
        }

        $candidateRows = [];
        $enteredTotal = 0.0;
        foreach ($methodIds as $index => $methodId) {
            $methodId = (int)$methodId;
            $enteredAmount = round((float)($amounts[$index] ?? 0), 2);
            if ($methodId <= 0 || $enteredAmount <= 0) {
                continue;
            }

            $method = $this->find_payment_method($methodId);
            if (!$method || (int)($method['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Salah satu metode pembayaran tidak aktif atau tidak ditemukan.'];
            }
            $companyAccountId = (int)($method['company_account_id'] ?? 0);
            if ($companyAccountId <= 0) {
                return ['ok' => false, 'message' => 'Metode pembayaran ' . (string)($method['method_name'] ?? 'POS') . ' belum terhubung ke rekening perusahaan.'];
            }

            $methodType = strtoupper(trim((string)($method['method_type'] ?? 'OTHER')));
            $candidateRows[] = [
                'payment_method_id' => $methodId,
                'method_name' => (string)($method['method_name'] ?? ''),
                'method_type' => $methodType,
                'company_account_id' => $companyAccountId,
                'entered_amount' => $enteredAmount,
                'reference_no' => trim((string)($refs[$index] ?? '')),
            ];
            $enteredTotal += $enteredAmount;
        }

        if (count($candidateRows) > 1 && $enteredTotal > round(max(0, $dueAmount), 2) + 0.009) {
            return [
                'ok' => false,
                'message' => 'Jika metode pembayaran lebih dari satu, total input tidak boleh melebihi sisa tagihan. Kembalian hanya berlaku untuk pembayaran tunai tunggal.',
            ];
        }

        $rows = [];
        $remainingDue = round(max(0, $dueAmount), 2);
        $totalAmount = 0.0;
        $changeTotal = 0.0;
        $singleCashMode = count($candidateRows) === 1 && (string)($candidateRows[0]['method_type'] ?? '') === 'CASH';

        foreach ($candidateRows as $row) {
            $enteredAmount = round((float)($row['entered_amount'] ?? 0), 2);
            $methodType = strtoupper(trim((string)($row['method_type'] ?? 'OTHER')));
            if ($methodType !== 'CASH' && $enteredAmount > $remainingDue + 0.009) {
                return ['ok' => false, 'message' => 'Nominal metode non tunai tidak boleh melebihi sisa tagihan.'];
            }

            $amount = round(min($enteredAmount, $remainingDue), 2);
            if ($amount <= 0) {
                continue;
            }

            $rows[] = [
                'payment_method_id' => (int)$row['payment_method_id'],
                'method_name' => (string)$row['method_name'],
                'company_account_id' => (int)$row['company_account_id'],
                'amount' => $amount,
                'reference_no' => (string)$row['reference_no'],
            ];
            $totalAmount += $amount;
            $remainingDue = round(max(0, $remainingDue - $amount), 2);
            if ($singleCashMode) {
                $changeTotal = round(max(0, $enteredAmount - $amount), 2);
            }
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'total_amount' => round($totalAmount, 2),
            'entered_total' => round($enteredTotal, 2),
            'change_total' => round($changeTotal, 2),
            'remaining_due' => round($remainingDue, 2),
        ];
    }

    private function cashier_voucher_options_for_order(array $order, float $baseAmount): array
    {
        $rows = [];
        foreach ($this->cashier_member_voucher_rows((int)(($order['header']['member_id'] ?? 0) ?: 0), $order, $baseAmount) as $row) {
            $rows[] = $row;
        }

        $today = date('Y-m-d');
        $campaigns = $this->db->from('pos_voucher_campaign')
            ->where('is_active', 1)
            ->where_in('issue_mode', ['PUBLIC', 'MANUAL'])
            ->group_start()
                ->where('start_date IS NULL', null, false)
                ->or_where('start_date <=', $today)
            ->group_end()
            ->group_start()
                ->where('end_date IS NULL', null, false)
                ->or_where('end_date >=', $today)
            ->group_end()
            ->order_by('campaign_name', 'ASC')
            ->get()
            ->result_array();

        foreach ($campaigns as $campaign) {
            $preview = $this->evaluate_cashier_voucher_discount($campaign, $order, $baseAmount, null);
            $rows[] = [
                'value'             => 'CAMPAIGN:' . (int)($campaign['id'] ?? 0),
                'label'             => trim((string)($campaign['campaign_code'] ?? '') . ' | ' . (string)($campaign['campaign_name'] ?? 'Promo voucher')),
                'notes'             => '',
                'kind'              => 'CAMPAIGN',
                'campaign_id'       => (int)($campaign['id'] ?? 0),
                'voucher_code'      => (string)($campaign['campaign_code'] ?? ''),
                'campaign_name'     => (string)($campaign['campaign_name'] ?? ''),
                'expired_at'        => (string)($campaign['end_date'] ?? ''),
                'estimated_discount'=> round((float)($preview['discount_amount'] ?? 0), 2),
                'reward_kind'       => (string)($preview['reward_kind'] ?? 'DISCOUNT'),
                'message'           => (string)($preview['message'] ?? ''),
                'valid'             => !empty($preview['ok']),
            ];
        }

        return $rows;
    }

    private function cashier_member_voucher_rows(int $memberId, array $order, float $baseAmount): array
    {
        if ($memberId <= 0 || !$this->db->table_exists('pos_voucher_issue')) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $issues = $this->db->query("
            SELECT v.*,
                c.campaign_name, c.campaign_code, c.voucher_type, c.discount_value,
                c.max_discount_amount,
                COALESCE(v.min_spend_amount, c.min_spend_amount) AS min_spend_amount,
                c.trigger_product_id, c.free_product_id, c.free_qty
            FROM pos_voucher_issue v
            LEFT JOIN pos_voucher_campaign c ON c.id = v.campaign_id
            WHERE v.member_id = ? AND v.voucher_status = 'OPEN'
              AND (v.expired_at IS NULL OR v.expired_at >= ?)
            ORDER BY v.issued_at DESC
        ", [$memberId, $now])->result_array();

        $rows = [];
        foreach ($issues as $issue) {
            $preview  = $this->evaluate_cashier_voucher_discount($issue, $order, $baseAmount, $issue);
            $dispName = (string)($issue['campaign_name'] ?: ($issue['notes'] ?: 'Voucher member'));
            $rows[] = [
                'value'             => 'ISSUE:' . (int)($issue['id'] ?? 0),
                'label'             => trim((string)($issue['voucher_code'] ?? '') . ' | ' . $dispName),
                'notes'             => (string)($issue['notes'] ?? ''),
                'kind'              => 'ISSUE',
                'voucher_issue_id'  => (int)($issue['id'] ?? 0),
                'campaign_id'       => (int)($issue['campaign_id'] ?? 0),
                'redeem_rule_id'    => (int)($issue['redeem_rule_id'] ?? 0),
                'voucher_code'      => (string)($issue['voucher_code'] ?? ''),
                'campaign_name'     => $dispName,
                'expired_at'        => (string)($issue['expired_at'] ?? ''),
                'estimated_discount'=> round((float)($preview['discount_amount'] ?? 0), 2),
                'reward_kind'       => (string)($preview['reward_kind'] ?? 'DISCOUNT'),
                'message'           => (string)($preview['message'] ?? ''),
                'valid'             => !empty($preview['ok']),
            ];
        }

        return $rows;
    }

    public function search_cashier_vouchers(int $orderId, int $actorEmployeeId, string $keyword = '', int $limit = 12): array
    {
        $session = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        if (!$session) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka.', 'rows' => []];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan.', 'rows' => []];
        }

        $header = (array)($order['header'] ?? []);
        if ((int)($header['cashier_session_id'] ?? 0) !== (int)($session['id'] ?? 0)) {
            return ['ok' => false, 'message' => 'Order ini bukan bagian dari sesi kasir yang sedang aktif.', 'rows' => []];
        }

        $status = strtoupper(trim((string)($header['status'] ?? 'DRAFT')));
        if (!$this->is_cashier_payment_allowed_status($status)) {
            return ['ok' => false, 'message' => 'Order ini belum siap untuk pengecekan voucher.', 'rows' => []];
        }

        $baseTotal = $this->current_order_payment_base_total($orderId);
        $keyword = strtoupper(trim($keyword));
        $sourceRows = $this->cashier_voucher_options_for_order($order, $baseTotal);
        $rows = [];

        foreach ($sourceRows as $row) {
            $candidate = [
                'source_type'     => (string)($row['kind'] ?? ''),
                'source_id'       => (int)(($row['voucher_issue_id'] ?? 0) ?: ($row['campaign_id'] ?? 0)),
                'selection_value' => (string)($row['value'] ?? ''),
                'voucher_code'    => (string)($row['voucher_code'] ?? ''),
                'label'           => (string)($row['label'] ?? ''),
                'notes'           => (string)($row['notes'] ?? ''),
                'rule_name'       => (string)($row['campaign_name'] ?? ''),
                'reward_kind'     => (string)($row['reward_kind'] ?? 'DISCOUNT'),
                'discount_amount' => round((float)($row['estimated_discount'] ?? 0), 2),
                'ok'              => !empty($row['valid']),
                'message'         => (string)($row['message'] ?? ''),
                'expired_at'      => (string)($row['expired_at'] ?? ''),
            ];
            if ($keyword !== '') {
                $haystack = strtoupper(implode(' ', [
                    (string)$candidate['voucher_code'],
                    (string)$candidate['label'],
                    (string)$candidate['rule_name'],
                ]));
                if (strpos($haystack, $keyword) === false) {
                    continue;
                }
            }
            $rows[] = $candidate;
        }

        usort($rows, function (array $left, array $right) use ($keyword) {
            $leftExact = $keyword !== '' && strtoupper((string)($left['voucher_code'] ?? '')) === $keyword;
            $rightExact = $keyword !== '' && strtoupper((string)($right['voucher_code'] ?? '')) === $keyword;
            if ($leftExact !== $rightExact) {
                return $leftExact ? -1 : 1;
            }
            if (!empty($left['ok']) !== !empty($right['ok'])) {
                return !empty($left['ok']) ? -1 : 1;
            }
            if ((string)($left['source_type'] ?? '') !== (string)($right['source_type'] ?? '')) {
                return (string)($left['source_type'] ?? '') === 'ISSUE' ? -1 : 1;
            }
            if ((float)($left['discount_amount'] ?? 0) !== (float)($right['discount_amount'] ?? 0)) {
                return ((float)($left['discount_amount'] ?? 0) > (float)($right['discount_amount'] ?? 0)) ? -1 : 1;
            }
            return strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return [
            'ok' => true,
            'rows' => array_slice($rows, 0, max(1, $limit)),
        ];
    }

    private function resolve_cashier_voucher_application(int $orderId, array $payload, float $baseAmount): array
    {
        $selection = strtoupper(trim((string)($payload['voucher_selection'] ?? '')));
        $selectionCode = trim((string)($payload['voucher_code'] ?? ''));
        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan untuk validasi voucher.'];
        }

        if ($selection === '' && $selectionCode === '') {
            return ['ok' => true, 'voucher_amount' => 0, 'promo_amount' => 0, 'redemption' => null];
        }

        if ($selectionCode !== '' && $selection === '') {
            $issue = $this->db->query("
                SELECT v.*,
                    c.campaign_name, c.campaign_code, c.voucher_type, c.discount_value,
                    c.max_discount_amount, c.trigger_product_id, c.free_product_id, c.free_qty,
                    COALESCE(v.min_spend_amount, c.min_spend_amount) AS min_spend_amount
                FROM pos_voucher_issue v
                LEFT JOIN pos_voucher_campaign c ON c.id = v.campaign_id
                WHERE UPPER(v.voucher_code) = UPPER(?) AND v.voucher_status = 'OPEN'
                LIMIT 1
            ", [strtoupper($selectionCode)])->row_array();
            if ($issue) {
                $selection = 'ISSUE:' . (int)($issue['id'] ?? 0);
            } else {
                $campaign = $this->db->from('pos_voucher_campaign')
                    ->where('UPPER(campaign_code)', strtoupper($selectionCode))
                    ->where('is_active', 1)
                    ->where_in('issue_mode', ['PUBLIC', 'MANUAL'])
                    ->limit(1)
                    ->get()
                    ->row_array();
                if ($campaign) {
                    $selection = 'CAMPAIGN:' . (int)($campaign['id'] ?? 0);
                }
            }
        }

        if (strpos($selection, 'ISSUE:') === 0) {
            $issueId = (int)substr($selection, 6);
            $issue = $this->db->query("
                SELECT v.*,
                    c.campaign_name, c.campaign_code, c.voucher_type, c.discount_value,
                    c.max_discount_amount, c.trigger_product_id, c.free_product_id, c.free_qty,
                    COALESCE(v.min_spend_amount, c.min_spend_amount) AS min_spend_amount
                FROM pos_voucher_issue v
                LEFT JOIN pos_voucher_campaign c ON c.id = v.campaign_id
                WHERE v.id = ? AND v.voucher_status = 'OPEN'
                LIMIT 1
            ", [$issueId])->row_array();
            if (!$issue) {
                return ['ok' => false, 'message' => 'Voucher member tidak ditemukan atau sudah tidak aktif.'];
            }
            $orderMemberId = !empty($order['header']['member_id']) ? (int)$order['header']['member_id'] : 0;
            $issueMemberId = !empty($issue['member_id']) ? (int)$issue['member_id'] : 0;
            if ($issueMemberId > 0 && $orderMemberId > 0 && $issueMemberId !== $orderMemberId) {
                return ['ok' => false, 'message' => 'Voucher ini terdaftar untuk member lain.'];
            }
            if (!empty($issue['expired_at']) && strtotime((string)$issue['expired_at']) < time()) {
                return ['ok' => false, 'message' => 'Voucher member sudah kedaluwarsa.'];
            }
            $preview = $this->evaluate_cashier_voucher_discount($issue, $order, $baseAmount, $issue);
            if (!($preview['ok'] ?? false)) {
                return $preview;
            }

            $discountAmt = round((float)($preview['discount_amount'] ?? 0), 2);
            return [
                'ok'             => true,
                'voucher_amount' => $discountAmt,
                'promo_amount'   => 0,
                'reward_kind'    => (string)($preview['reward_kind'] ?? 'DISCOUNT'),
                'notes'          => (string)($issue['notes'] ?? ''),
                'redemption'     => [
                    'voucher_issue_id' => $issueId,
                    'member_id'        => $issueMemberId > 0 ? $issueMemberId : null,
                'redeem_amount'    => $discountAmt,
                'voucher_code'     => (string)($issue['voucher_code'] ?? ''),
                'voucher_kind'     => 'ISSUE',
                'campaign_id'      => !empty($issue['campaign_id']) ? (int)$issue['campaign_id'] : null,
                'voucher_label'    => (string)($issue['campaign_name'] ?? $issue['voucher_code'] ?? 'Voucher member'),
                'face_value_amount'=> round((float)($issue['amount_snapshot'] ?? 0), 2),
                'face_value_percent' => round((float)($issue['percent_snapshot'] ?? 0), 4),
            ],
        ];
        }

        if (strpos($selection, 'CAMPAIGN:') === 0) {
            $campaignId = (int)substr($selection, 9);
            $campaign = $this->db->from('pos_voucher_campaign')
                ->where('id', $campaignId)
                ->where('is_active', 1)
                ->where_in('issue_mode', ['PUBLIC', 'MANUAL'])
                ->limit(1)
                ->get()
                ->row_array();
            if (!$campaign) {
                return ['ok' => false, 'message' => 'Promo voucher tidak ditemukan atau tidak aktif.'];
            }
            $preview = $this->evaluate_cashier_voucher_discount($campaign, $order, $baseAmount, null);
            if (!($preview['ok'] ?? false)) {
                return $preview;
            }

            $discountAmt = round((float)($preview['discount_amount'] ?? 0), 2);
            return [
                'ok' => true,
                'voucher_amount' => 0,
                'promo_amount' => $discountAmt,
                'redemption' => [
                    'voucher_issue_id' => 0,
                    'member_id' => !empty($order['header']['member_id']) ? (int)$order['header']['member_id'] : null,
                    'redeem_amount' => $discountAmt,
                    'voucher_code' => (string)($campaign['campaign_code'] ?? ''),
                    'voucher_kind' => 'CAMPAIGN',
                    'campaign_id' => $campaignId,
                    'voucher_label' => (string)($campaign['campaign_name'] ?? $campaign['campaign_code'] ?? 'Promo voucher'),
                    'face_value_amount' => strtoupper((string)($campaign['voucher_type'] ?? 'AMOUNT')) === 'AMOUNT'
                        ? round((float)($campaign['discount_value'] ?? 0), 2)
                        : 0.0,
                    'face_value_percent' => strtoupper((string)($campaign['voucher_type'] ?? 'AMOUNT')) === 'PERCENT'
                        ? round((float)($campaign['discount_value'] ?? 0), 4)
                        : 0.0,
                ],
            ];
        }

        return ['ok' => false, 'message' => 'Pilihan voucher tidak dikenali.'];
    }

    private function evaluate_redeem_voucher_discount(array $issueRow, array $order, float $baseAmount): array
    {
        $minSpend = round((float)($issueRow['min_spend_amount'] ?? 0), 2);
        if ($minSpend > 0 && $baseAmount < $minSpend) {
            return ['ok' => false, 'discount_amount' => 0, 'reward_kind' => 'DISCOUNT',
                'message' => 'Belum memenuhi minimal transaksi Rp ' . number_format($minSpend, 0, ',', '.') . '.'];
        }

        $amountSnapshot  = round((float)($issueRow['amount_snapshot']  ?? 0), 2);
        $percentSnapshot = round((float)($issueRow['percent_snapshot'] ?? 0), 4);
        $notes           = trim((string)($issueRow['notes'] ?? ''));

        if ($amountSnapshot > 0) {
            $discount = round(min($baseAmount, $amountSnapshot), 2);
            return ['ok' => $discount > 0, 'discount_amount' => $discount, 'reward_kind' => 'DISCOUNT', 'message' => ''];
        }

        if ($percentSnapshot > 0) {
            $discount = round($baseAmount * $percentSnapshot / 100, 2);
            return ['ok' => $discount > 0, 'discount_amount' => $discount, 'reward_kind' => 'DISCOUNT', 'message' => ''];
        }

        if (stripos($notes, 'Gratis: ') === 0) {
            $after      = substr($notes, strlen('Gratis: '));
            $voucherQty = 1.0;
            if (preg_match('/^(.+)\s+x([\d.]+)$/u', $after, $m)) {
                $productName = trim($m[1]);
                $voucherQty  = (float)$m[2];
            } else {
                $productName = $after;
            }

            $discount = 0.0;
            $found    = false;
            foreach ((array)($order['lines'] ?? []) as $line) {
                if (strcasecmp(trim((string)($line['product_name'] ?? '')), $productName) === 0) {
                    $lineQty   = (float)($line['qty']        ?? 0);
                    $linePrice = (float)($line['unit_price'] ?? 0);
                    if ($lineQty > 0 && $linePrice > 0) {
                        $discount = round(min($lineQty, $voucherQty) * $linePrice, 2);
                        $found    = true;
                    }
                    break;
                }
            }

            if (!$found) {
                return [
                    'ok'             => false,
                    'discount_amount' => 0,
                    'reward_kind'    => 'FREE_PRODUCT',
                    'message'        => 'Produk "' . $productName . '" belum ada di order. Tambahkan produk tersebut terlebih dahulu.',
                ];
            }

            return ['ok' => true, 'discount_amount' => $discount, 'reward_kind' => 'FREE_PRODUCT', 'message' => ''];
        }

        $rewardKind = stripos($notes, 'Merchandise: ') === 0 ? 'MERCHANDISE' : 'OTHER';
        return ['ok' => true, 'discount_amount' => 0, 'reward_kind' => $rewardKind, 'message' => ''];
    }

    private function evaluate_cashier_voucher_discount(array $campaignRow, array $order, float $baseAmount, ?array $issueRow = null): array
    {
        // Standalone redeem voucher (tidak dari campaign) – route ke evaluator khusus
        if ($issueRow !== null && !empty($issueRow['redeem_rule_id']) && empty($issueRow['campaign_id'])) {
            return $this->evaluate_redeem_voucher_discount($issueRow, $order, $baseAmount);
        }

        $minSpend = round((float)($campaignRow['min_spend_amount'] ?? 0), 2);
        if ($minSpend > 0 && $baseAmount < $minSpend) {
            return ['ok' => false, 'message' => 'Voucher belum memenuhi minimal transaksi.'];
        }

        $productQtyMap = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $productQtyMap[$productId] = round((float)($productQtyMap[$productId] ?? 0) + (float)($line['qty'] ?? 0), 4);
        }

        $triggerProductId = (int)($campaignRow['trigger_product_id'] ?? 0);
        if ($triggerProductId > 0 && round((float)($productQtyMap[$triggerProductId] ?? 0), 4) <= 0) {
            return ['ok' => false, 'message' => 'Voucher ini mensyaratkan item tertentu ada di order.'];
        }

        $voucherType = strtoupper(trim((string)($campaignRow['voucher_type'] ?? 'AMOUNT')));
        $discountAmount = 0.0;
        if ($voucherType === 'PERCENT') {
            $percent = $issueRow ? round((float)($issueRow['percent_snapshot'] ?? 0), 4) : round((float)($campaignRow['discount_value'] ?? 0), 4);
            $discountAmount = round($baseAmount * max(0, $percent) / 100, 2);
            $maxDiscount = round((float)($campaignRow['max_discount_amount'] ?? 0), 2);
            if ($maxDiscount > 0) {
                $discountAmount = min($discountAmount, $maxDiscount);
            }
        } elseif ($voucherType === 'FREE_PRODUCT') {
            $freeProductId = (int)($campaignRow['free_product_id'] ?? 0);
            $freeQty = round((float)($campaignRow['free_qty'] ?? 0), 4);
            if ($freeProductId <= 0 || $freeQty <= 0) {
                return ['ok' => false, 'message' => 'Setting voucher free product belum lengkap.'];
            }
            $matchedQty = 0.0;
            $matchedPrice = 0.0;
            foreach ((array)($order['lines'] ?? []) as $line) {
                if ((int)($line['product_id'] ?? 0) !== $freeProductId) {
                    continue;
                }
                $matchedQty += (float)($line['qty'] ?? 0);
                $matchedPrice = max($matchedPrice, (float)($line['unit_price'] ?? 0));
            }
            if ($matchedQty <= 0 || $matchedPrice <= 0) {
                return ['ok' => false, 'message' => 'Produk gratis dari voucher ini belum ada di order.'];
            }
            $discountAmount = round(min($matchedQty, $freeQty) * $matchedPrice, 2);
        } else {
            $discountAmount = $issueRow
                ? round((float)($issueRow['amount_snapshot'] ?? 0), 2)
                : round((float)($campaignRow['discount_value'] ?? 0), 2);
        }

        $discountAmount = round(max(0, min($baseAmount, $discountAmount)), 2);
        return [
            'ok' => $discountAmount > 0,
            'message' => $discountAmount > 0 ? '' : 'Voucher tidak menghasilkan potongan nominal untuk order ini.',
            'discount_amount' => $discountAmount,
            'reward_kind' => 'DISCOUNT',
        ];
    }

    private function apply_cashier_voucher_redemption(array $redemption, int $orderId, int $paymentId, int $actorEmployeeId, string $now): void
    {
        $voucherIssueId = (int)($redemption['voucher_issue_id'] ?? 0);
        $campaignId = (int)($redemption['campaign_id'] ?? 0);
        $voucherKind = strtoupper(trim((string)($redemption['voucher_kind'] ?? ($voucherIssueId > 0 ? 'ISSUE' : 'CAMPAIGN'))));
        if ($paymentId <= 0 || ($voucherIssueId <= 0 && $campaignId <= 0)) {
            return;
        }

        if ($voucherIssueId > 0) {
            $existing = $this->db->from('pos_voucher_redemption')
                ->where('voucher_issue_id', $voucherIssueId)
                ->where('payment_id', $paymentId)
                ->limit(1)
                ->get()
                ->row_array();
            if (!$existing) {
                $this->db->where('id', $voucherIssueId)->update('pos_voucher_issue', [
                    'voucher_status' => 'REDEEMED',
                    'source_order_id' => $orderId,
                    'source_payment_id' => $paymentId,
                    'redeemed_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->db->insert('pos_voucher_redemption', [
                    'voucher_issue_id' => $voucherIssueId,
                    'member_id' => !empty($redemption['member_id']) ? (int)$redemption['member_id'] : null,
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'redeem_amount' => round((float)($redemption['redeem_amount'] ?? 0), 2),
                    'redeemed_at' => $now,
                    'notes' => 'Redeem voucher saat pembayaran POS',
                ]);
            }
        }

        $this->record_cashier_voucher_usage([
            'voucher_issue_id' => $voucherIssueId > 0 ? $voucherIssueId : null,
            'campaign_id' => $campaignId > 0 ? $campaignId : null,
            'voucher_kind' => $voucherKind === 'ISSUE' ? 'ISSUE' : 'CAMPAIGN',
            'voucher_code' => (string)($redemption['voucher_code'] ?? ''),
            'voucher_label' => (string)($redemption['voucher_label'] ?? $redemption['voucher_code'] ?? 'Voucher'),
            'member_id' => !empty($redemption['member_id']) ? (int)$redemption['member_id'] : null,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'applied_amount' => round((float)($redemption['redeem_amount'] ?? 0), 2),
            'face_value_amount' => round((float)($redemption['face_value_amount'] ?? 0), 2),
            'face_value_percent' => round((float)($redemption['face_value_percent'] ?? 0), 4),
            'cashier_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'used_at' => $now,
        ]);
    }

    private function record_cashier_voucher_usage(array $usage): void
    {
        if (!$this->db->table_exists('pos_voucher_usage')) {
            return;
        }

        $voucherKind = strtoupper(trim((string)($usage['voucher_kind'] ?? 'ISSUE')));
        $voucherIssueId = (int)($usage['voucher_issue_id'] ?? 0);
        $campaignId = (int)($usage['campaign_id'] ?? 0);
        $sourceKey = $voucherKind === 'ISSUE'
            ? 'ISSUE:' . $voucherIssueId
            : 'CAMPAIGN:' . $campaignId;
        $paymentId = (int)($usage['payment_id'] ?? 0);
        if ($paymentId <= 0 || ($voucherKind === 'ISSUE' && $voucherIssueId <= 0) || ($voucherKind === 'CAMPAIGN' && $campaignId <= 0)) {
            return;
        }

        $existing = $this->db->from('pos_voucher_usage')
            ->where('payment_id', $paymentId)
            ->where('source_key', $sourceKey)
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            return;
        }

        $payload = [
            'source_key' => $sourceKey,
            'voucher_kind' => $voucherKind,
            'voucher_issue_id' => $voucherIssueId > 0 ? $voucherIssueId : null,
            'campaign_id' => $campaignId > 0 ? $campaignId : null,
            'voucher_code' => $this->nullable_text($usage['voucher_code'] ?? ''),
            'voucher_label' => $this->nullable_text($usage['voucher_label'] ?? ''),
            'member_id' => !empty($usage['member_id']) ? (int)$usage['member_id'] : null,
            'order_id' => !empty($usage['order_id']) ? (int)$usage['order_id'] : null,
            'payment_id' => $paymentId,
            'cashier_employee_id' => !empty($usage['cashier_employee_id']) ? (int)$usage['cashier_employee_id'] : null,
            'face_value_amount' => round((float)($usage['face_value_amount'] ?? 0), 2),
            'face_value_percent' => round((float)($usage['face_value_percent'] ?? 0), 4),
            'applied_amount' => round((float)($usage['applied_amount'] ?? 0), 2),
            'usage_status' => 'APPLIED',
            'used_at' => (string)($usage['used_at'] ?? date('Y-m-d H:i:s')),
            'notes' => 'Voucher diterapkan saat pembayaran POS',
            'created_at' => (string)($usage['used_at'] ?? date('Y-m-d H:i:s')),
        ];
        $this->db->insert('pos_voucher_usage', $this->filter_table_payload('pos_voucher_usage', $payload));
    }

    private function apply_cashier_payment_loyalty(array $orderRow, int $paymentId, string $paidAt): array
    {
        $memberId = (int)($orderRow['member_id'] ?? 0);
        $orderId = (int)($orderRow['id'] ?? 0);
        if ($orderId <= 0 || $paymentId <= 0) {
            return ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
        }

        $pointEarned = $memberId > 0
            ? $this->award_cashier_payment_points($memberId, $orderId, $paymentId, $paidAt, $orderRow)
            : 0.0;
        $stampEarned = $memberId > 0
            ? $this->award_cashier_payment_stamps($memberId, $orderId, $paymentId, $paidAt, $orderRow)
            : 0.0;
        $issuedVouchers = $this->issue_cashier_payment_vouchers($memberId, $orderId, $paymentId, $paidAt, $orderRow);
        if ($memberId > 0) {
            $this->sync_member_loyalty_cache($memberId);
            $this->sync_member_total_spending_cache($memberId);
        }

        return [
            'point_earned' => round((float)$pointEarned, 4),
            'stamp_earned' => round((float)$stampEarned, 4),
            'issued_vouchers' => $issuedVouchers,
        ];
    }

    private function apply_paid_order_loyalty_by_order_id(int $orderId, int $memberIdFallback = 0, ?string $paidAtFallback = null): array
    {
        if ($orderId <= 0 || !$this->db->table_exists('pos_order') || !$this->db->table_exists('pos_payment')) {
            return ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
        }

        $orderRow = $this->db->from('pos_order')
            ->where('id', $orderId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$orderRow) {
            return ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
        }

        $paymentRow = $this->db->from('pos_payment')
            ->where('order_id', $orderId)
            ->where('payment_type', 'FINAL')
            ->where('payment_status', 'PAID')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$paymentRow) {
            return ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
        }

        $memberId = (int)($orderRow['member_id'] ?? 0);
        if ($memberId <= 0 && $memberIdFallback > 0) {
            $memberId = $memberIdFallback;
            if ($this->db->field_exists('member_id', 'pos_order')) {
                $this->db->where('id', $orderId)->update('pos_order', ['member_id' => $memberId]);
            }
            $orderRow['member_id'] = $memberId;
        }
        if ($memberId <= 0) {
            return ['point_earned' => 0.0, 'stamp_earned' => 0.0, 'issued_vouchers' => []];
        }

        if (empty($paymentRow['member_id']) && $this->db->field_exists('member_id', 'pos_payment')) {
            $this->db->where('id', (int)$paymentRow['id'])->update('pos_payment', ['member_id' => $memberId]);
        }

        $paidAt = trim((string)($paymentRow['paid_at'] ?? ''));
        if ($paidAt === '') {
            $paidAt = trim((string)($paidAtFallback ?? ''));
        }
        if ($paidAt === '') {
            $paidAt = date('Y-m-d H:i:s');
        }

        return $this->apply_cashier_payment_loyalty((array)$orderRow, (int)$paymentRow['id'], $paidAt);
    }

    public function ensure_paid_order_loyalty(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order tidak valid.'];
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $summary = $this->apply_paid_order_loyalty_by_order_id($orderId);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal memastikan loyalty order POS.'));
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'loyalty' => $summary];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function award_cashier_payment_points(int $memberId, int $orderId, int $paymentId, string $paidAt, array $orderRow): float
    {
        if (!$this->db->table_exists('pos_point_rule') || !$this->db->table_exists('pos_point_ledger')) {
            return 0.0;
        }

        $grossAmount = round((float)($orderRow['subtotal_amount'] ?? 0), 2);
        $netAmount = round((float)($orderRow['grand_total'] ?? 0), 2);
        $productQtyMap = $this->order_product_qty_map($orderId);
        $rules = $this->active_cashier_point_rules($productQtyMap);
        if (empty($rules)) {
            return 0.0;
        }

        $runningBalance = $this->current_member_point_balance($memberId);
        $awarded = 0.0;
        foreach ($rules as $rule) {
            $already = (int)$this->db->from('pos_point_ledger')
                ->where('member_id', $memberId)
                ->where('order_id', $orderId)
                ->where('payment_id', $paymentId)
                ->where('rule_id', (int)$rule['id'])
                ->where('ledger_type', 'EARN')
                ->count_all_results();
            if ($already > 0) {
                continue;
            }

            $basis = strtoupper(trim((string)($rule['spend_basis'] ?? 'NET'))) === 'GROSS' ? $grossAmount : $netAmount;
            if ($basis <= 0) {
                continue;
            }
            if ($basis < round((float)($rule['min_spend_amount'] ?? 0), 2)) {
                continue;
            }
            $requiredProductId = (int)($rule['required_product_id'] ?? 0);
            $requiredQty = $requiredProductId > 0 ? round((float)($productQtyMap[$requiredProductId] ?? 0), 4) : 0;
            if ($requiredProductId > 0 && $requiredQty <= 0) {
                continue;
            }

            $points = 0.0;
            $earnMode = strtoupper(trim((string)($rule['earn_mode'] ?? 'AMOUNT')));
            if ($earnMode === 'FLAT') {
                $points = round((float)($rule['flat_point'] ?? 0), 4);
            } elseif ($earnMode === 'PRODUCT') {
                $basePoint = round((float)($rule['flat_point'] ?? 0), 4);
                $points = round(($basePoint > 0 ? $basePoint : 1) * max(1, $requiredQty), 4);
            } else {
                $amountPerPoint = round((float)($rule['amount_per_point'] ?? 0), 2);
                if ($amountPerPoint > 0) {
                    $points = round(floor($basis / $amountPerPoint), 4);
                }
            }
            if ($points <= 0) {
                continue;
            }

            $expiresAt = $this->cashier_loyalty_expired_at($paidAt, (int)($rule['point_expiry_days'] ?? 0));
            $runningBalance = round($runningBalance + $points, 4);
            $this->db->insert('pos_point_ledger', [
                'member_id' => $memberId,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'rule_id' => (int)$rule['id'],
                'ledger_type' => 'EARN',
                'points_in' => $points,
                'points_out' => 0,
                'balance_after' => $runningBalance,
                'expired_at' => $expiresAt,
                'notes' => 'Poin otomatis dari pembayaran POS',
                'created_at' => $paidAt,
            ]);
            $awarded = round($awarded + $points, 4);
        }

        return $awarded;
    }

    private function award_cashier_payment_stamps(int $memberId, int $orderId, int $paymentId, string $paidAt, array $orderRow): float
    {
        if (!$this->db->table_exists('pos_stamp_campaign') || !$this->db->table_exists('pos_stamp_ledger')) {
            return 0.0;
        }

        $grossAmount = round((float)($orderRow['subtotal_amount'] ?? 0), 2);
        $netAmount = round((float)($orderRow['grand_total'] ?? 0), 2);
        $productQtyMap = $this->order_product_qty_map($orderId);
        $campaigns = $this->active_cashier_stamp_campaigns($paidAt);
        if (empty($campaigns)) {
            return 0.0;
        }

        $runningBalance = $this->current_member_stamp_balance($memberId);
        $awarded = 0.0;
        foreach ($campaigns as $campaign) {
            $already = (int)$this->db->from('pos_stamp_ledger')
                ->where('member_id', $memberId)
                ->where('order_id', $orderId)
                ->where('payment_id', $paymentId)
                ->where('campaign_id', (int)$campaign['id'])
                ->where('ledger_type', 'EARN')
                ->count_all_results();
            if ($already > 0) {
                continue;
            }

            $stamps = 0.0;
            $earnMode = strtoupper(trim((string)($campaign['earn_mode'] ?? 'TXN')));
            if ($earnMode === 'PRODUCT') {
                $requiredProductId = (int)($campaign['required_product_id'] ?? 0);
                $qty = $requiredProductId > 0 ? round((float)($productQtyMap[$requiredProductId] ?? 0), 4) : 0;
                if ($requiredProductId <= 0 || $qty <= 0) {
                    continue;
                }
                $stamps = round((float)($campaign['stamp_per_earn'] ?? 1) * $qty, 4);
            } elseif ($earnMode === 'AMOUNT') {
                $step = round((float)($campaign['amount_step'] ?? 0), 2);
                if ($step <= 0 || $netAmount <= 0) {
                    continue;
                }
                $multiplier = floor($netAmount / $step);
                if ($multiplier <= 0) {
                    continue;
                }
                $stamps = round((float)($campaign['stamp_per_earn'] ?? 1) * $multiplier, 4);
            } else {
                $stamps = round((float)($campaign['stamp_per_earn'] ?? 1), 4);
                if ($grossAmount <= 0 && $netAmount <= 0) {
                    continue;
                }
            }
            if ($stamps <= 0) {
                continue;
            }

            $expiresAt = $this->cashier_loyalty_expired_at($paidAt, (int)($campaign['stamp_expiry_days'] ?? 0));
            $runningBalance = round($runningBalance + $stamps, 4);
            $this->db->insert('pos_stamp_ledger', [
                'member_id' => $memberId,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'campaign_id' => (int)$campaign['id'],
                'ledger_type' => 'EARN',
                'stamp_in' => $stamps,
                'stamp_out' => 0,
                'balance_after' => $runningBalance,
                'expired_at' => $expiresAt,
                'notes' => 'Stamp otomatis dari pembayaran POS',
                'created_at' => $paidAt,
            ]);
            $awarded = round($awarded + $stamps, 4);
        }

        return $awarded;
    }

    private function issue_cashier_payment_vouchers(int $memberId, int $orderId, int $paymentId, string $paidAt, array $orderRow): array
    {
        if (!$this->db->table_exists('pos_voucher_campaign') || !$this->db->table_exists('pos_voucher_issue')) {
            return [];
        }

        $netAmount = round((float)($orderRow['grand_total'] ?? 0), 2);
        $productQtyMap = $this->order_product_qty_map($orderId);
        $campaigns = $this->active_cashier_auto_voucher_campaigns($paidAt);
        if (empty($campaigns)) {
            return [];
        }

        $issuedCodes = [];
        foreach ($campaigns as $campaign) {
            $exists = (int)$this->db->from('pos_voucher_issue')
                ->where('campaign_id', (int)$campaign['id'])
                ->where('source_order_id', $orderId)
                ->where('source_payment_id', $paymentId)
                ->count_all_results();
            if ($exists > 0) {
                continue;
            }

            $minSpend = round((float)($campaign['min_spend_amount'] ?? 0), 2);
            if ($minSpend > 0 && $netAmount < $minSpend) {
                continue;
            }
            $triggerProductId = (int)($campaign['trigger_product_id'] ?? 0);
            if ($triggerProductId > 0 && round((float)($productQtyMap[$triggerProductId] ?? 0), 4) <= 0) {
                continue;
            }

            $voucherType = strtoupper(trim((string)($campaign['voucher_type'] ?? 'AMOUNT')));
            $payload = [
                'voucher_issue_no' => $this->generate_cashier_voucher_issue_no($paidAt),
                'campaign_id' => (int)$campaign['id'],
                'member_id' => $memberId > 0 ? $memberId : null,
                'source_order_id' => $orderId,
                'source_payment_id' => $paymentId,
                'voucher_code' => $this->generate_cashier_random_code('VCR-', 12, 'pos_voucher_issue', 'voucher_code'),
                'voucher_status' => 'OPEN',
                'amount_snapshot' => $voucherType === 'PERCENT' ? 0 : round((float)($campaign['discount_value'] ?? 0), 2),
                'percent_snapshot' => $voucherType === 'PERCENT' ? round((float)($campaign['discount_value'] ?? 0), 4) : 0,
                'issued_at' => $paidAt,
                'expired_at' => (int)($campaign['valid_day_count'] ?? 0) > 0
                    ? date('Y-m-d H:i:s', strtotime($paidAt . ' +' . (int)$campaign['valid_day_count'] . ' days'))
                    : null,
                'notes' => 'Voucher otomatis dari pembayaran POS',
                'created_at' => $paidAt,
            ];
            $this->db->insert('pos_voucher_issue', $this->filter_table_payload('pos_voucher_issue', $payload));
            if ((int)$this->db->insert_id() > 0) {
                $issuedCodes[] = (string)$payload['voucher_code'];
            }
        }

        return $issuedCodes;
    }

    private function active_cashier_point_rules(array $productQtyMap = []): array
    {
        if (!$this->db->table_exists('pos_point_rule')) {
            return [];
        }

        $rows = $this->db->from('pos_point_rule')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($rows)) {
            return [];
        }

        $allowed = [];
        foreach ($rows as $row) {
            $requiredProductId = (int)($row['required_product_id'] ?? 0);
            if ($requiredProductId > 0 && round((float)($productQtyMap[$requiredProductId] ?? 0), 4) <= 0) {
                continue;
            }
            $allowed[] = $row;
        }

        return $allowed;
    }

    private function active_cashier_stamp_campaigns(string $paidAt): array
    {
        if (!$this->db->table_exists('pos_stamp_campaign')) {
            return [];
        }

        $paidDate = date('Y-m-d', strtotime($paidAt));
        $rows = $this->db->from('pos_stamp_campaign')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($rows)) {
            return [];
        }

        $allowed = [];
        foreach ($rows as $row) {
            $startDate = trim((string)($row['start_date'] ?? ''));
            $endDate = trim((string)($row['end_date'] ?? ''));
            if ($startDate !== '' && $startDate > $paidDate) {
                continue;
            }
            if ($endDate !== '' && $endDate < $paidDate) {
                continue;
            }
            $allowed[] = $row;
        }

        return $allowed;
    }

    private function active_cashier_auto_voucher_campaigns(string $paidAt): array
    {
        if (!$this->db->table_exists('pos_voucher_campaign')) {
            return [];
        }

        $paidDate = date('Y-m-d', strtotime($paidAt));
        $rows = $this->db->from('pos_voucher_campaign')
            ->where('is_active', 1)
            ->where('issue_mode', 'AUTO_FROM_TXN')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($rows)) {
            return [];
        }

        $allowed = [];
        foreach ($rows as $row) {
            $startDate = trim((string)($row['start_date'] ?? ''));
            $endDate = trim((string)($row['end_date'] ?? ''));
            if ($startDate !== '' && $startDate > $paidDate) {
                continue;
            }
            if ($endDate !== '' && $endDate < $paidDate) {
                continue;
            }
            $allowed[] = $row;
        }

        return $allowed;
    }

    private function cashier_loyalty_expired_at(string $paidAt, int $expiryDays): ?string
    {
        if ($expiryDays <= 0) {
            return null;
        }

        $paidDate = date('Y-m-d', strtotime($paidAt));
        return date('Y-m-d H:i:s', strtotime($paidDate . ' +' . $expiryDays . ' days'));
    }

    private function order_product_qty_map(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $rows = $this->db->select('product_id, COALESCE(SUM(qty),0) AS qty_total', false)
            ->from('pos_order_line')
            ->where('order_id', $orderId)
            ->where('qty >', 0)
            ->where_not_in('line_status', ['VOID', 'REFUNDED_FULL'])
            ->group_by('product_id')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $map[$productId] = round((float)($row['qty_total'] ?? 0), 4);
        }

        return $map;
    }

    private function current_member_point_balance(int $memberId): float
    {
        if ($memberId <= 0) {
            return 0.0;
        }

        return round((float)$this->db->select('COALESCE(SUM(points_in - points_out), 0) AS total', false)
            ->from('pos_point_ledger')
            ->where('member_id', $memberId)
            ->get()
            ->row('total'), 4);
    }

    private function current_member_stamp_balance(int $memberId): float
    {
        if ($memberId <= 0) {
            return 0.0;
        }

        return round((float)$this->db->select('COALESCE(SUM(stamp_in - stamp_out), 0) AS total', false)
            ->from('pos_stamp_ledger')
            ->where('member_id', $memberId)
            ->get()
            ->row('total'), 4);
    }

    private function sync_member_loyalty_cache(int $memberId): void
    {
        if ($memberId <= 0 || !$this->db->table_exists('crm_member')) {
            return;
        }

        $this->db->where('id', $memberId)->update('crm_member', [
            'point_balance_cache' => $this->current_member_point_balance($memberId),
            'stamp_balance_cache' => $this->current_member_stamp_balance($memberId),
        ]);
    }

    private function sync_member_total_spending_cache(int $memberId): void
    {
        if ($memberId <= 0 || !$this->db->table_exists('crm_member')) {
            return;
        }

        $paidTotal = (float)$this->db->select('COALESCE(SUM(net_amount), 0) AS total', false)
            ->from('pos_payment')
            ->where('member_id', $memberId)
            ->where('payment_type', 'FINAL')
            ->where('payment_status', 'PAID')
            ->get()
            ->row('total');
        $refundTotal = $this->db->table_exists('pos_refund')
            ? (float)$this->db->select('COALESCE(SUM(refund_amount), 0) AS total', false)
                ->from('pos_refund')
                ->where('member_id', $memberId)
                ->where('refund_status', 'POSTED')
                ->get()
                ->row('total')
            : 0.0;

        $this->db->where('id', $memberId)->update('crm_member', [
            'total_spending' => round(max(0, $paidTotal - $refundTotal), 2),
        ]);
    }

    private function generate_cashier_voucher_issue_no(?string $issuedAt = null): string
    {
        $issuedAt = $issuedAt ?: date('Y-m-d H:i:s');
        $prefix = 'VCH-' . date('Ymd', strtotime($issuedAt));
        $row = $this->db->query(
            'SELECT voucher_issue_no FROM pos_voucher_issue WHERE voucher_issue_no LIKE ? ORDER BY voucher_issue_no DESC LIMIT 1',
            [$prefix . '-%']
        )->row_array();
        $next = 1;
        if (!empty($row['voucher_issue_no'])) {
            $parts = explode('-', (string)$row['voucher_issue_no']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_cashier_random_code(string $prefix, int $length, string $table, string $column): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bodyLength = max(4, $length - strlen($prefix));
        do {
            $body = '';
            for ($i = 0; $i < $bodyLength; $i++) {
                $body .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $prefix . $body;
        } while ($this->db->from($table)->where($column, $code)->count_all_results() > 0);

        return $code;
    }

    public function find_payment_method(int $id): ?array
    {
        return $this->coredb->from('pos_payment_method')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function update_payment_line_method(int $paymentLineId, int $paymentMethodId, int $actorEmployeeId): array
    {
        if ($paymentLineId <= 0) {
            return ['ok' => false, 'message' => 'Line pembayaran tidak valid.', 'status_code' => 422];
        }
        if ($paymentMethodId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran wajib dipilih.', 'status_code' => 422];
        }

        $line = $this->find_payment_line_detail($paymentLineId);
        if (!$line) {
            return ['ok' => false, 'message' => 'Line pembayaran tidak ditemukan.', 'status_code' => 404];
        }

        if (strtoupper((string)($line['status'] ?? '')) === 'VOID' || strtoupper((string)($line['payment_status'] ?? '')) === 'VOID') {
            return ['ok' => false, 'message' => 'Line pembayaran yang sudah void tidak bisa diubah.', 'status_code' => 422];
        }

        $method = $this->find_payment_method($paymentMethodId);
        if (!$method) {
            return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.', 'status_code' => 404];
        }
        if (isset($method['is_active']) && (int)$method['is_active'] !== 1) {
            return ['ok' => false, 'message' => 'Metode pembayaran yang dipilih tidak aktif.', 'status_code' => 422];
        }

        $currentMethodId = (int)($line['payment_method_id'] ?? 0);
        if ($currentMethodId === $paymentMethodId) {
            return ['ok' => true, 'message' => 'Metode pembayaran sudah sesuai.', 'line' => $line];
        }

        $oldMethodName = (string)($line['method_name'] ?? 'Metode lama');
        $newMethodName = (string)($method['method_name'] ?? 'Metode baru');
        $amount = round((float)($line['amount'] ?? 0), 2);
        $oldAccountId = (int)($line['company_account_id'] ?? 0);
        if ($oldAccountId <= 0) {
            $oldAccountId = (int)($line['method_company_account_id'] ?? 0);
        }
        $newAccountId = (int)($method['company_account_id'] ?? 0);
        if ($this->db->table_exists('fin_company_account') && $this->db->table_exists('fin_account_mutation_log') && $newAccountId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran tujuan belum terhubung ke rekening perusahaan.', 'status_code' => 422];
        }

        $now = date('Y-m-d H:i:s');
        $paymentNo = (string)($line['payment_no'] ?? '-');
        $lineNo = (int)($line['line_no'] ?? 0);

        $this->db->trans_begin();
        try {
            $updatePayload = ['payment_method_id' => $paymentMethodId];
            if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
                $updatePayload['company_account_id'] = $newAccountId > 0 ? $newAccountId : null;
            }
            if ($this->db->field_exists('updated_at', 'pos_payment_line')) {
                $updatePayload['updated_at'] = $now;
            }
            $this->db->where('id', $paymentLineId)->update('pos_payment_line', $this->filter_table_payload('pos_payment_line', $updatePayload));

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal memperbarui line pembayaran POS.'));
            }

            if ($this->db->table_exists('fin_company_account') && $this->db->table_exists('fin_account_mutation_log') && $amount > 0 && $oldAccountId !== $newAccountId) {
                $notes = sprintf('Koreksi metode pembayaran POS %s line %d: %s -> %s', $paymentNo, $lineNo, $oldMethodName, $newMethodName);
                if ($oldAccountId > 0) {
                    $outResult = $this->post_company_account_mutation([
                        'account_id' => $oldAccountId,
                        'mutation_type' => 'OUT',
                        'amount' => $amount,
                        'mutation_date' => $now,
                        'ref_module' => 'POS',
                        'ref_table' => 'pos_payment_line',
                        'ref_id' => $paymentLineId,
                        'ref_no' => $paymentNo,
                        'notes' => $notes,
                        'created_by' => $actorEmployeeId,
                    ]);
                    if (!($outResult['ok'] ?? false)) {
                        throw new RuntimeException((string)($outResult['message'] ?? 'Gagal membuat koreksi mutasi rekening lama.'));
                    }
                }
                if ($newAccountId > 0) {
                    $inResult = $this->post_company_account_mutation([
                        'account_id' => $newAccountId,
                        'mutation_type' => 'IN',
                        'amount' => $amount,
                        'mutation_date' => $now,
                        'ref_module' => 'POS',
                        'ref_table' => 'pos_payment_line',
                        'ref_id' => $paymentLineId,
                        'ref_no' => $paymentNo,
                        'notes' => $notes,
                        'created_by' => $actorEmployeeId,
                    ]);
                    if (!($inResult['ok'] ?? false)) {
                        throw new RuntimeException((string)($inResult['message'] ?? 'Gagal membuat mutasi rekening baru.'));
                    }
                }
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal menyimpan perubahan metode pembayaran POS.'));
            }

            $this->db->trans_commit();
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $exception->getMessage(), 'status_code' => 422];
        }

        return [
            'ok' => true,
            'message' => 'Metode pembayaran berhasil diperbarui.',
            'line' => $this->find_payment_line_detail($paymentLineId) ?? [],
        ];
    }

    private function post_company_account_mutation(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $mutationType = strtoupper(trim((string)($payload['mutation_type'] ?? 'IN')));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $mutationDate = (string)($payload['mutation_date'] ?? date('Y-m-d H:i:s'));
        $reversalOfMutationId = (int)($payload['reversal_of_mutation_id'] ?? 0);
        if ($accountId <= 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'Payload mutasi rekening POS tidak valid.'];
        }
        if (!in_array($mutationType, ['IN', 'OUT'], true)) {
            return ['ok' => false, 'message' => 'Jenis mutasi rekening POS tidak valid.'];
        }
        if ($reversalOfMutationId > 0 && !$this->db->field_exists('reversal_of_mutation_id', 'fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Schema tautan pembatalan mutasi rekening belum tersedia.'];
        }

        $account = $this->db->query(
            'SELECT * FROM fin_company_account WHERE id = ? LIMIT 1 FOR UPDATE',
            [$accountId]
        )->row_array();
        if (!$account) {
            return ['ok' => false, 'message' => 'Rekening perusahaan tidak ditemukan saat posting mutasi POS.'];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $balanceAfter = $mutationType === 'IN'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);
        if ($this->db->affected_rows() < 0 || $this->db->trans_status() === false) {
            return ['ok' => false, 'message' => $this->db_error_message('Gagal update saldo rekening POS.')];
        }

        $mutationPayload = [
            'mutation_no' => $this->generate_account_mutation_no($mutationDate),
            'mutation_date' => date('Y-m-d', strtotime($mutationDate)),
            'account_id' => $accountId,
            'mutation_type' => $mutationType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => (string)($payload['ref_module'] ?? 'POS'),
            'ref_table' => (string)($payload['ref_table'] ?? 'pos_payment'),
            'ref_id' => !empty($payload['ref_id']) ? (int)$payload['ref_id'] : null,
            'ref_no' => $this->nullable_text($payload['ref_no'] ?? null),
            'notes' => $this->nullable_text($payload['notes'] ?? null),
            'created_by' => !empty($payload['created_by']) ? (int)$payload['created_by'] : null,
        ];
        if ($reversalOfMutationId > 0) {
            $mutationPayload['reversal_of_mutation_id'] = $reversalOfMutationId;
        }
        $this->db->insert('fin_account_mutation_log', $mutationPayload);
        if ((int)$this->db->insert_id() <= 0) {
            return ['ok' => false, 'message' => $this->db_error_message('Gagal menyimpan mutasi rekening POS.')];
        }

        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => $this->db_error_message('Gagal menyimpan mutasi rekening POS.')];
        }

        return [
            'ok' => true,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    private function find_payment_line_detail(int $paymentLineId): ?array
    {
        $select = 'pl.*, p.payment_no, p.payment_status, p.payment_type, p.order_id, p.paid_at, p.created_at AS payment_created_at, pm.method_name, pm.method_type';
        $db = $this->db->from('pos_payment_line pl')
            ->join('pos_payment p', 'p.id = pl.payment_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');

        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $select .= ', pl.company_account_id, acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name, pm.company_account_id AS method_company_account_id';
            $db->join('fin_company_account acc', 'acc.id = pl.company_account_id', 'left');
        } else {
            $select .= ', pm.company_account_id AS method_company_account_id';
            if ($this->db->field_exists('company_account_id', 'pos_payment_method')) {
                $select .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
                $db->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
            }
        }

        return $db->select($select, false)
            ->where('pl.id', $paymentLineId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function column_is_nullable(string $table, string $column): bool
    {
        $row = $this->db
            ->select('IS_NULLABLE')
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->limit(1)
            ->get()
            ->row_array();

        return strtoupper((string)($row['IS_NULLABLE'] ?? 'NO')) === 'YES';
    }

    public function deposit_payment_method_options(): array
    {
        $db = $this->coredb;
        if (!$db->table_exists('pos_payment_method')) {
            return [];
        }

        $db->from('pos_payment_method pm');
        $select = 'pm.id, pm.method_code, pm.method_name, pm.method_type, pm.company_account_id';
        if ($db->table_exists('fin_company_account')) {
            $db->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
            $select .= ', acc.account_name, acc.account_type, acc.bank_name, acc.account_no, acc.account_holder';
        }

        return $db
            ->select($select, false)
            ->where('pm.is_active', 1)
            ->where_not_in('pm.method_type', ['COMPLIMENT'])
            ->order_by('pm.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function save_payment_method(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['method_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama metode pembayaran wajib diisi.'];
        }

        $type = strtoupper(trim((string)($data['method_type'] ?? 'CASH')));
        if (!in_array($type, ['CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $type = 'CASH';
        }

        $methodCode = strtoupper(trim((string)($data['method_code'] ?? '')));
        if ($methodCode === '') {
            $methodCode = $this->generate_named_code($db, 'pos_payment_method', 'method_code', $name, 'PM-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_payment_method', 'method_code', $methodCode, $id)) {
            return ['ok' => false, 'message' => 'Kode metode pembayaran sudah dipakai.'];
        }

        $companyAccountId = !empty($data['company_account_id']) ? (int)$data['company_account_id'] : null;
        if ($companyAccountId !== null && !$this->company_account_exists($companyAccountId)) {
            return ['ok' => false, 'message' => 'Rekening perusahaan tidak ditemukan.'];
        }

        $payload = [
            'method_code' => $methodCode,
            'method_name' => $name,
            'method_type' => $type,
            'company_account_id' => $companyAccountId,
        ];

        if ($id > 0) {
            if (!$this->find_payment_method($id)) {
                return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
            }
            $db->where('id', $id)->update('pos_payment_method', $payload);
        } else {
            $db->insert('pos_payment_method', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_payment_method(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_payment_method($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_payment_method', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function outlet_terminal_filter_options(): array
    {
        return [
            'outlets' => $this->active_outlets(),
        ];
    }

    public function outlet_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('pos_outlet o');
        if ($q !== '') {
            $db->group_start()
                ->like('o.outlet_code', $q)
                ->or_like('o.outlet_name', $q)
                ->or_like('o.address', $q)
                ->or_like('o.phone', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('o.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('o.is_active', 0);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('o.*')
            ->order_by('o.outlet_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function terminal_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('pos_terminal t')
            ->join('pos_outlet o', 'o.id = t.outlet_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('t.terminal_code', $q)
                ->or_like('t.terminal_name', $q)
                ->or_like('t.device_key', $q)
                ->or_like('o.outlet_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('t.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('t.is_active', 0);
        }
        if ($outletId > 0) {
            $db->where('t.outlet_id', $outletId);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('t.*, o.outlet_name')
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('t.terminal_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_outlet(int $id): ?array
    {
        return $this->coredb->from('pos_outlet')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_outlet(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['outlet_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama outlet wajib diisi.'];
        }

        $scope = strtoupper(trim((string)($data['outlet_scope'] ?? 'REGULAR')));
        if (!in_array($scope, ['REGULAR', 'EVENT', 'MIXED'], true)) {
            $scope = 'REGULAR';
        }

        $code = strtoupper(trim((string)($data['outlet_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($db, 'pos_outlet', 'outlet_code', $name, 'OUT-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_outlet', 'outlet_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode outlet sudah dipakai di core.'];
        }

        $payload = [
            'outlet_code' => $code,
            'outlet_name' => $name,
            'outlet_scope' => $scope,
            'address' => $this->nullable_text($data['address'] ?? ''),
            'phone' => $this->nullable_text($data['phone'] ?? ''),
        ];

        if ($id > 0) {
            if (!$this->find_outlet($id)) {
                return ['ok' => false, 'message' => 'Outlet tidak ditemukan di core.'];
            }
            $db->where('id', $id)->update('pos_outlet', $payload);
        } else {
            $db->insert('pos_outlet', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_outlet(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_outlet($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Outlet tidak ditemukan di core.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_outlet', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function find_terminal(int $id): ?array
    {
        return $this->coredb->from('pos_terminal')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_terminal(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['terminal_name'] ?? ''));
        $outletId = (int)($data['outlet_id'] ?? 0);
        if ($name === '' || $outletId <= 0) {
            return ['ok' => false, 'message' => 'Outlet dan nama terminal wajib diisi.'];
        }
        if (!$this->find_outlet($outletId)) {
            return ['ok' => false, 'message' => 'Outlet terminal tidak ditemukan di core.'];
        }

        $osType = strtoupper(trim((string)($data['os_type'] ?? 'WEB')));
        if (!in_array($osType, ['WINDOWS', 'UBUNTU', 'ANDROID', 'IOS', 'WEB', 'OTHER'], true)) {
            $osType = 'WEB';
        }

        $code = strtoupper(trim((string)($data['terminal_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($db, 'pos_terminal', 'terminal_code', $name, 'TERM-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_terminal', 'terminal_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode terminal sudah dipakai di core.'];
        }

        $deviceKey = trim((string)($data['device_key'] ?? ''));
        if ($deviceKey !== '' && $this->terminal_device_key_exists($deviceKey, $id)) {
            return ['ok' => false, 'message' => 'Device key terminal sudah dipakai di core.'];
        }

        $payload = [
            'outlet_id' => $outletId,
            'terminal_code' => $code,
            'terminal_name' => $name,
            'device_key' => $deviceKey === '' ? null : $deviceKey,
            'os_type' => $osType,
        ];

        if ($id > 0) {
            if (!$this->find_terminal($id)) {
                return ['ok' => false, 'message' => 'Terminal tidak ditemukan di core.'];
            }
            $db->where('id', $id)->update('pos_terminal', $payload);
        } else {
            $db->insert('pos_terminal', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_terminal(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_terminal($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Terminal tidak ditemukan di core.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_terminal', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function active_company_accounts(): array
    {
        return $this->coredb->select('id, account_name, account_type, bank_name, account_no, account_holder')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('account_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function active_outlets(): array
    {
        return $this->coredb->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Compatibility shape for retired routes. Runtime and active screens use
     * Pos_print_model directly; this method never reads legacy printer tables.
     */
    public function printer_filter_options(): array
    {
        $printerConfig = $this->printer_config_model();
        $options = $printerConfig ? $printerConfig->options() : [];
        return [
            'template_masters' => [],
            'templates' => (array)($options['layouts'] ?? []),
            'outlets' => (array)($options['outlets'] ?? []),
            'terminals' => (array)($options['terminals'] ?? []),
            'printers' => (array)($options['connections'] ?? []),
        ];
    }

    public function printer_general_settings(): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->general_settings() : ['row' => null, 'payload' => []];
    }

    public function save_printer_general_settings(array $data): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig
            ? $printerConfig->save_general_settings($data)
            : $this->retired_printer_configuration_response();
    }

    public function printer_template_rows(array $filters): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig
            ? $printerConfig->layout_rows($filters)
            : $this->empty_printer_rows($filters);
    }

    public function find_printer_template(int $id): ?array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->find_layout($id) : null;
    }

    public function active_printer_template_options(): array
    {
        $printerConfig = $this->printer_config_model();
        $options = $printerConfig ? $printerConfig->options() : [];
        return (array)($options['layouts'] ?? []);
    }

    public function save_printer_template(array $data): array
    {
        return $this->retired_printer_configuration_response('Layout Dokumen');
    }

    public function toggle_printer_template(int $id): array
    {
        return $this->retired_printer_configuration_response('Layout Dokumen');
    }

    public function printer_profile_rows(array $filters): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig
            ? $printerConfig->route_rows($filters)
            : $this->empty_printer_rows($filters);
    }

    public function find_printer_profile(int $id): ?array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->find_route($id) : null;
    }

    public function save_printer_profile(array $data): array
    {
        return $this->retired_printer_configuration_response('Aturan Cetak');
    }

    public function toggle_printer_profile(int $id): array
    {
        return $this->retired_printer_configuration_response('Aturan Cetak');
    }

    public function printer_device_rows(array $filters): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig
            ? $printerConfig->connection_rows($filters)
            : $this->empty_printer_rows($filters);
    }

    public function find_printer_device(int $id): ?array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->find_connection($id) : null;
    }

    public function active_printer_preview_options(): array
    {
        $printerConfig = $this->printer_config_model();
        $options = $printerConfig ? $printerConfig->options() : [];
        return array_values(array_filter((array)($options['connections'] ?? []), static function (array $row): bool {
            return !empty($row['is_active']);
        }));
    }

    public function save_printer_device(array $data): array
    {
        return $this->retired_printer_configuration_response('Koneksi Printer');
    }

    public function toggle_printer_device(int $id): array
    {
        return $this->retired_printer_configuration_response('Koneksi Printer');
    }

    public function active_printer_devices_for_agent_config(string $agentHost = ''): array
    {
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->agent_connection_ready()) {
            return [];
        }
        return $printerConfig->agent_devices($agentHost);
    }
    public function order_draft_filter_options(): array
    {
        return [
            'outlets' => $this->local_outlet_options(),
            'terminals' => $this->local_terminal_options(),
            'refund_payment_methods' => $this->deposit_payment_method_options(),
            'reversal_reason_options' => $this->reversal_reason_options(),
        ];
    }

    private function reversal_reason_options(): array
    {
        return [
            'VOID' => [
                ['code' => 'SALAH_INPUT', 'label' => 'Salah input kasir'],
                ['code' => 'PERMINTAAN_CUSTOMER', 'label' => 'Permintaan customer sebelum dibayar'],
                ['code' => 'STOK_TIDAK_SIAP', 'label' => 'Stok / bahan tidak siap'],
                ['code' => 'PRODUK_BERMASALAH', 'label' => 'Produk bermasalah atau gagal disiapkan'],
                ['code' => 'KOREKSI_SHIFT', 'label' => 'Koreksi shift / transaksi'],
                ['code' => 'OTHER', 'label' => 'Other'],
            ],
            'REFUND' => [
                ['code' => 'SALAH_ITEM', 'label' => 'Item yang diterima tidak sesuai'],
                ['code' => 'KUALITAS_PRODUK', 'label' => 'Kualitas produk tidak sesuai'],
                ['code' => 'KELUHAN_RASA', 'label' => 'Keluhan rasa / kualitas layanan'],
                ['code' => 'PERMINTAAN_CUSTOMER', 'label' => 'Permintaan customer setelah dibayar'],
                ['code' => 'KETERLAMBATAN_LAYANAN', 'label' => 'Keterlambatan layanan'],
                ['code' => 'OTHER', 'label' => 'Other'],
            ],
        ];
    }

    public function cashier_bootstrap_options(int $employeeId = 0): array
    {
        $outlets = $this->local_outlet_options();
        $terminals = $this->local_terminal_options();
        $activeSession = $employeeId > 0 ? $this->find_active_cashier_session($employeeId) : null;
        $salesChannels = $this->sales_channel_options();
        $defaultSalesChannelId = $this->default_sales_channel_id();
        $defaultOutletId = 0;
        $defaultTerminalId = 0;

        if (!empty($outlets)) {
            $outletIds = array_values(array_filter(array_map(static function (array $row): int {
                return (int)($row['id'] ?? 0);
            }, $outlets)));
            if (!empty($outletIds)) {
                $defaultOutletId = min($outletIds);
            }
        }

        if (!empty($terminals)) {
            $terminalCandidates = array_values(array_filter($terminals, static function (array $row) use ($defaultOutletId): bool {
                return (int)($row['outlet_id'] ?? 0) === $defaultOutletId;
            }));
            if (empty($terminalCandidates)) {
                $terminalCandidates = $terminals;
            }
            $terminalIds = array_values(array_filter(array_map(static function (array $row): int {
                return (int)($row['id'] ?? 0);
            }, $terminalCandidates)));
            if (!empty($terminalIds)) {
                $defaultTerminalId = min($terminalIds);
            }
        }

        $printerOutletId = (int)($activeSession['outlet_id'] ?? $defaultOutletId);

        return [
            'outlets' => $outlets,
            'terminals' => $terminals,
            'sales_channels' => $salesChannels,
            'default_sales_channel_id' => $defaultSalesChannelId,
            'default_outlet_id' => $defaultOutletId,
            'default_terminal_id' => $defaultTerminalId,
            'default_opening_cash' => 300000,
            'order_reprint_printers' => $this->cashier_order_reprint_printer_options($printerOutletId),
            'active_session' => $activeSession,
            'active_sessions' => $this->active_cashier_sessions(),
        ];
    }

    public function cashier_order_reprint_printer_options(int $outletId = 0): array
    {
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->ready()) {
            return [];
        }

        $connections = (array)($printerConfig->connection_rows([
            'status' => 'ACTIVE',
            'limit' => 100,
        ])['rows'] ?? []);
        $routes = (array)($printerConfig->route_rows([
            'status' => 'ACTIVE',
            'limit' => 100,
        ])['rows'] ?? []);
        $options = [];
        foreach ($connections as $connection) {
            $connectionId = (int)($connection['id'] ?? 0);
            $connectionOutletId = (int)($connection['outlet_id'] ?? 0);
            if ($connectionId <= 0 || ($outletId > 0 && $connectionOutletId > 0 && $connectionOutletId !== $outletId)) {
                continue;
            }

            $connectionRoutes = array_values(array_filter($routes, static function (array $route) use ($connectionId): bool {
                return (int)($route['connection_id'] ?? 0) === $connectionId;
            }));
            $hasPreBill = false;
            $hasKot = false;
            foreach ($connectionRoutes as $route) {
                $eventCode = strtoupper(trim((string)($route['event_code'] ?? '')));
                $hasPreBill = $hasPreBill || $eventCode === 'ORDER_PRE_BILL';
                $hasKot = $hasKot || $eventCode === 'ORDER_CONFIRM_KOT';
            }

            $printerId = $connectionId;
            if ($printerId <= 0) {
                continue;
            }
            $role = strtoupper(trim((string)($connection['location_label'] ?? 'CUSTOM')));
            if ($role === 'KASIR' && !$hasPreBill) {
                continue;
            }
            if ($role !== 'KASIR' && !$hasKot) {
                continue;
            }
            $printerName = trim((string)($connection['connection_name'] ?? ''));
            $printerCode = trim((string)($connection['connection_code'] ?? ''));
            $labelParts = [
                $printerName !== '' ? $printerName : ($printerCode !== '' ? $printerCode : 'Printer POS'),
            ];
            if ($role === 'KASIR') {
                $labelParts[] = 'BILL';
            }
            if ($role !== '' && $role !== 'CUSTOM') {
                $labelParts[] = $role;
            }
            if ($printerCode !== '' && strcasecmp($printerCode, $printerName) !== 0) {
                $labelParts[] = $printerCode;
            }
            $options[] = [
                'id' => $printerId,
                'printer_name' => $printerName,
                'printer_code' => $printerCode,
                'printer_role' => $role,
                'print_scope' => $role === 'KASIR' ? 'ALL' : 'DIVISION',
                'print_mode' => $role === 'KASIR' ? 'PRE_BILL' : 'KOT',
                'label' => implode(' | ', array_values(array_filter($labelParts, static function ($value): bool {
                    return trim((string)$value) !== '';
                }))),
            ];
        }

        return $options;
    }

    public function daily_recon_gate_status(string $stage, string $date = ''): array
    {
        $stage = strtoupper(trim($stage));
        if (!in_array($stage, ['OPEN', 'CLOSE'], true)) {
            $stage = 'OPEN';
        }

        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $mode = strtoupper(trim($this->sys_app_config_value('pos.daily_recon_gate_mode', 'OFF')));
        if (!in_array($mode, ['OFF', 'OPEN_ONLY', 'CLOSE_ONLY', 'OPEN_AND_CLOSE'], true)) {
            $mode = 'OFF';
        }

        $enabled = $mode === 'OPEN_AND_CLOSE'
            || ($mode === 'OPEN_ONLY' && $stage === 'OPEN')
            || ($mode === 'CLOSE_ONLY' && $stage === 'CLOSE');

        if (!$enabled) {
            return [
                'enabled' => false,
                'stage' => $stage,
                'date' => $date,
                'complete' => true,
                'missing' => [],
                'message' => 'Gate daily recon POS sedang nonaktif untuk tahap ini.',
            ];
        }

        if (!$this->db->table_exists('inv_daily_recon_checkpoint')) {
            return [
                'enabled' => true,
                'stage' => $stage,
                'date' => $date,
                'complete' => false,
                'missing' => [['division_name' => 'Setup', 'domain_label' => 'Tabel checkpoint']],
                'message' => 'Tabel checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a terlebih dahulu.',
            ];
        }

        $requiredDivisions = $this->pos_stock_recon_required_divisions();
        if (empty($requiredDivisions)) {
            return [
                'enabled' => true,
                'stage' => $stage,
                'date' => $date,
                'complete' => true,
                'missing' => [],
                'message' => 'Tidak ada divisi POS yang perlu dicek.',
            ];
        }

        $divisionIds = array_map(static fn($row) => (int)$row['id'], $requiredDivisions);
        $rows = $this->db->select('division_id, recon_domain')
            ->from('inv_daily_recon_checkpoint')
            ->where('checkpoint_date', $date)
            ->where('checkpoint_stage', $stage)
            ->where_in('division_id', $divisionIds)
            ->where_in('recon_domain', ['MATERIAL', 'COMPONENT'])
            ->get()
            ->result_array();

        $done = [];
        foreach ($rows as $row) {
            $done[(int)$row['division_id'] . '|' . strtoupper((string)$row['recon_domain'])] = true;
        }

        $missing = [];
        foreach ($requiredDivisions as $division) {
            foreach (['MATERIAL' => 'Bahan baku', 'COMPONENT' => 'Component'] as $domain => $label) {
                if (!isset($done[(int)$division['id'] . '|' . $domain])) {
                    $missing[] = [
                        'division_id' => (int)$division['id'],
                        'division_code' => (string)($division['code'] ?? ''),
                        'division_name' => (string)($division['name'] ?? ''),
                        'domain' => $domain,
                        'domain_label' => $label,
                    ];
                }
            }
        }

        if ($this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            foreach ($requiredDivisions as $division) {
                $divisionId = (int)($division['id'] ?? 0);
                if ($divisionId <= 0) {
                    continue;
                }
                foreach ($this->daily_recon_required_material_line_missing($date, $stage, $divisionId) as $line) {
                    $line['division_id'] = $divisionId;
                    $line['division_code'] = (string)($division['code'] ?? '');
                    $line['division_name'] = (string)($division['name'] ?? '');
                    $missing[] = $line;
                }
                foreach ($this->daily_recon_required_component_line_missing($date, $stage, $divisionId) as $line) {
                    $line['division_id'] = $divisionId;
                    $line['division_code'] = (string)($division['code'] ?? '');
                    $line['division_name'] = (string)($division['name'] ?? '');
                    $missing[] = $line;
                }
            }
        }

        $complete = empty($missing);
        $stageLabel = $stage === 'OPEN' ? 'buka kasir' : 'tutup kasir';
        return [
            'enabled' => true,
            'stage' => $stage,
            'date' => $date,
            'complete' => $complete,
            'policy' => strtoupper(trim($this->sys_app_config_value('pos.daily_recon_gate_policy', 'WARN_ONLY'))),
            'missing' => $missing,
            'message' => $complete
                ? 'Daily recon bahan baku dan component sudah lengkap untuk ' . $stageLabel . '.'
                : 'Daily recon belum lengkap untuk ' . $stageLabel . '. Mohon lakukan konfirmasi di halaman Daily Recon bahan baku dan component.',
        ];
    }

    private function daily_recon_required_material_line_missing(string $date, string $stage, int $divisionId): array
    {
        if (!$this->db->table_exists('inv_division_monthly_stock') || !$this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            return [];
        }

        $month = date('Y-m-01', strtotime($date));
        $confirmMode = strtoupper(trim($this->sys_app_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        $requiredTokens = $this->daily_recon_required_tokens('pos.daily_recon_required_materials');
        $activeMaterialWhere = $this->db->field_exists('is_active', 'mst_material') ? 'AND (m.id IS NULL OR COALESCE(m.is_active,1)=1)' : '';
        $activeItemWhere = $this->db->field_exists('is_active', 'mst_item') ? 'AND (i.id IS NULL OR COALESCE(i.is_active,1)=1)' : '';

        $lotSub = "
            SELECT
              division_id,
              COALESCE(destination_type, 'OTHER') AS destination_type,
              item_id,
              COALESCE(material_id, 0) AS material_id,
              buy_uom_id,
              content_uom_id,
              COALESCE(profile_key, '') AS profile_key,
              COUNT(*) AS lot_count
            FROM inv_material_fifo_lot
            WHERE location_scope = 'DIVISION'
              AND division_id = " . (int)$divisionId . "
              AND COALESCE(material_id, 0) > 0
              AND ABS(COALESCE(qty_balance, 0)) > 0.0001
              AND (status IS NULL OR status = 'OPEN')
            GROUP BY
              division_id,
              COALESCE(destination_type, 'OTHER'),
              item_id,
              COALESCE(material_id, 0),
              buy_uom_id,
              content_uom_id,
              COALESCE(profile_key, '')
        ";

        $sql = "
            SELECT
              s.division_id,
              COALESCE(s.destination_type, 'OTHER') AS destination_type,
              s.item_id,
              COALESCE(s.material_id, i.material_id, 0) AS material_id,
              s.buy_uom_id,
              s.content_uom_id,
              COALESCE(s.identity_key, s.profile_key, '') AS identity_key,
              COALESCE(s.profile_key, '') AS profile_key,
              COALESCE(s.profile_name, m.material_name, i.item_name, '') AS label,
              COALESCE(m.material_code, '') AS material_code,
              COALESCE(m.material_name, '') AS material_name,
              COALESCE(i.item_code, '') AS item_code,
              COALESCE(i.item_name, '') AS item_name,
              ROUND(COALESCE(s.closing_qty_content, 0), 4) AS closing_qty,
              COALESCE(lc.lot_count, 0) AS lot_count
            FROM inv_division_monthly_stock s
            LEFT JOIN mst_item i ON i.id = s.item_id
            LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
            LEFT JOIN ({$lotSub}) lc
              ON lc.division_id = s.division_id
             AND lc.destination_type = COALESCE(s.destination_type, 'OTHER')
             AND lc.item_id <=> s.item_id
             AND lc.material_id <=> COALESCE(s.material_id, i.material_id, 0)
             AND lc.buy_uom_id <=> s.buy_uom_id
             AND lc.content_uom_id <=> s.content_uom_id
             AND lc.profile_key <=> COALESCE(s.profile_key, '')
            WHERE s.month_key = " . $this->db->escape($month) . "
              AND s.division_id = " . (int)$divisionId . "
              AND COALESCE(s.material_id, i.material_id, 0) > 0
              AND (s.profile_key IS NULL OR COALESCE(s.identity_key, s.profile_key, '') = COALESCE(s.profile_key, ''))
              {$activeMaterialWhere}
              {$activeItemWhere}
        ";

        $oldDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $query = $this->db->query($sql);
        $this->db->db_debug = $oldDebug;
        if (!$query) {
            return [[
                'domain' => 'MATERIAL',
                'domain_label' => 'Bahan baku',
                'line_label' => 'Audit bahan baku gagal dibaca',
                'reason' => 'audit_error',
            ]];
        }

        $missing = [];
        foreach ($query->result_array() as $row) {
            $lineKey = $this->daily_recon_material_line_key($row);
            $isMinus = (float)($row['closing_qty'] ?? 0) < -0.0001;
            $isMultiLot = (int)($row['lot_count'] ?? 0) > 1;
            $isConfigured = $this->daily_recon_token_matches($requiredTokens, [
                $row['material_id'] ?? '',
                $row['material_code'] ?? '',
                $row['material_name'] ?? '',
                $row['item_code'] ?? '',
                $row['item_name'] ?? '',
                $row['label'] ?? '',
            ]);
            $mustConfirm = $confirmMode === 'ROW_REQUIRED' || $isMinus || $isMultiLot || $isConfigured;
            if (!$mustConfirm || $lineKey === '') {
                continue;
            }
            if ($this->daily_recon_line_confirmed($date, 'MATERIAL', $divisionId, $stage, $lineKey)) {
                continue;
            }
            $reason = $isMinus ? 'stok minus' : ($isMultiLot ? 'lebih dari 1 lot' : ($isConfigured ? 'wajib recon' : 'mode satu per satu'));
            $missing[] = [
                'domain' => 'MATERIAL',
                'domain_label' => 'Bahan baku',
                'line_key' => $lineKey,
                'line_label' => (string)($row['label'] ?: $row['material_name'] ?: $row['item_name'] ?: 'Bahan baku'),
                'reason' => $reason,
            ];
        }
        return $missing;
    }

    private function daily_recon_required_component_line_missing(string $date, string $stage, int $divisionId): array
    {
        if (!$this->db->table_exists('inv_component_monthly_stock') || !$this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            return [];
        }

        $month = date('Y-m-01', strtotime($date));
        $confirmMode = strtoupper(trim($this->sys_app_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        $requiredTokens = $this->daily_recon_required_tokens('pos.daily_recon_required_components');
        $activeComponentWhere = $this->db->field_exists('is_active', 'mst_component') ? 'AND (c.id IS NULL OR COALESCE(c.is_active,1)=1)' : '';

        $sql = "
            SELECT
              s.division_id,
              COALESCE(s.location_type, 'OTHER') AS location_type,
              s.component_id,
              s.uom_id,
              ROUND(COALESCE(s.closing_qty, 0), 4) AS closing_qty,
              COALESCE(c.component_code, '') AS component_code,
              COALESCE(c.component_name, '') AS component_name,
              COALESCE(lc.lot_count, 0) AS lot_count
            FROM inv_component_monthly_stock s
            LEFT JOIN mst_component c ON c.id = s.component_id
            LEFT JOIN (
              SELECT
                division_id,
                COALESCE(location_type, 'OTHER') AS location_type,
                component_id,
                uom_id,
                COUNT(*) AS lot_count
              FROM inv_component_lot
              WHERE division_id = " . (int)$divisionId . "
                AND ABS(COALESCE(qty_balance, 0)) > 0.0001
                AND (status IS NULL OR status = 'OPEN')
              GROUP BY division_id, COALESCE(location_type, 'OTHER'), component_id, uom_id
            ) lc
              ON lc.division_id = s.division_id
             AND lc.location_type = COALESCE(s.location_type, 'OTHER')
             AND lc.component_id = s.component_id
             AND lc.uom_id <=> s.uom_id
            WHERE s.month_key = " . $this->db->escape($month) . "
              AND s.division_id = " . (int)$divisionId . "
              AND COALESCE(s.component_id, 0) > 0
              {$activeComponentWhere}
        ";

        $oldDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $query = $this->db->query($sql);
        $this->db->db_debug = $oldDebug;
        if (!$query) {
            return [[
                'domain' => 'COMPONENT',
                'domain_label' => 'Component',
                'line_label' => 'Audit component gagal dibaca',
                'reason' => 'audit_error',
            ]];
        }

        $missing = [];
        foreach ($query->result_array() as $row) {
            $lineKey = $this->daily_recon_component_line_key($row);
            $isMinus = (float)($row['closing_qty'] ?? 0) < -0.0001;
            $isMultiLot = (int)($row['lot_count'] ?? 0) > 1;
            $isConfigured = $this->daily_recon_token_matches($requiredTokens, [
                $row['component_id'] ?? '',
                $row['component_code'] ?? '',
                $row['component_name'] ?? '',
            ]);
            $mustConfirm = $confirmMode === 'ROW_REQUIRED' || $isMinus || $isMultiLot || $isConfigured;
            if (!$mustConfirm || $lineKey === '') {
                continue;
            }
            if ($this->daily_recon_line_confirmed($date, 'COMPONENT', $divisionId, $stage, $lineKey)) {
                continue;
            }
            $reason = $isMinus ? 'stok minus' : ($isMultiLot ? 'lebih dari 1 lot' : ($isConfigured ? 'wajib recon' : 'mode satu per satu'));
            $missing[] = [
                'domain' => 'COMPONENT',
                'domain_label' => 'Component',
                'line_key' => $lineKey,
                'line_label' => (string)($row['component_name'] ?: $row['component_code'] ?: 'Component'),
                'reason' => $reason,
            ];
        }
        return $missing;
    }

    private function daily_recon_line_confirmed(string $date, string $domain, int $divisionId, string $stage, string $lineKey): bool
    {
        $row = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint_line')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->where('line_key', $lineKey)
            ->limit(1)
            ->get()
            ->row_array();
        return !empty($row);
    }

    private function daily_recon_material_line_key(array $row): string
    {
        return implode('|', [
            'M',
            (int)($row['division_id'] ?? 0),
            strtoupper(trim((string)($row['destination_type'] ?? 'OTHER'))),
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            trim((string)($row['identity_key'] ?? ($row['profile_key'] ?? ''))),
        ]);
    }

    private function daily_recon_component_line_key(array $row): string
    {
        return implode('|', [
            'C',
            (int)($row['division_id'] ?? 0),
            strtoupper(trim((string)($row['location_type'] ?? 'OTHER'))),
            (int)($row['component_id'] ?? 0),
            (int)($row['uom_id'] ?? 0),
            0,
        ]);
    }

    private function daily_recon_required_tokens(string $configKey): array
    {
        $raw = (string)$this->sys_app_config_value($configKey, '');
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtoupper(trim((string)$part));
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return $tokens;
    }

    private function daily_recon_token_matches(array $tokens, array $candidates): bool
    {
        if (empty($tokens)) {
            return false;
        }
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string)$candidate));
            if ($value !== '' && isset($tokens[$value])) {
                return true;
            }
        }
        return false;
    }

    private function pos_stock_recon_required_divisions(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        $codeCol = $this->db->field_exists('code', 'mst_operational_division') ? 'code' : 'id';
        $nameCol = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : $codeCol);

        $this->db->select('id, ' . $codeCol . ' AS code, ' . $nameCol . ' AS name', false)
            ->from('mst_operational_division')
            ->where("UPPER(TRIM({$codeCol})) IN ('BAR','KITCHEN','ROASTERY')", null, false);

        if ($this->db->field_exists('is_active', 'mst_operational_division')) {
            $this->db->where('COALESCE(is_active, 1) = 1', null, false);
        }

        return $this->db->order_by($codeCol, 'ASC')->get()->result_array();
    }

    public function sales_channel_options(): array
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return [];
        }

        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $db = $this->db
            ->select('id, channel_code, channel_name, service_type_default, ' . ($hasAllowedTypes ? 'allowed_service_types' : "'' AS allowed_service_types") . ', marketplace_fee_percent, is_default')
            ->from('pos_sales_channel')
            ->where('is_active', 1);
        if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
            $db->order_by('sort_order', 'ASC');
        }

        $rows = $db
            ->order_by('channel_name', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
            $row['allowed_service_type_list'] = $allowedRaw !== ''
                ? $this->explode_service_types($allowedRaw)
                : [$row['service_type_default'] ?? 'DINE_IN'];
        }
        unset($row);

        return $rows;
    }

    public function default_sales_channel_id(): ?int
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return null;
        }

        $db = $this->db
            ->select('id')
            ->from('pos_sales_channel')
            ->where('is_active', 1);
        if ($this->db->field_exists('is_default', 'pos_sales_channel')) {
            $db->order_by('is_default', 'DESC');
        }
        if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
            $db->order_by('sort_order', 'ASC');
        }

        $row = $db
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        $id = (int)($row['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function cashier_catalog_filter_options(): array
    {
        $divisions = $this->db
            ->select('pd.id, pd.code, pd.name, COUNT(p.id) AS product_count', false)
            ->from('mst_product_division pd')
            ->join('mst_product p', 'p.product_division_id = pd.id', 'inner')
            ->where('pd.is_active', 1)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $divisions->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $divisions->where('p.show_in_cashier', 1);
        }
        $divisionRows = $divisions
            ->group_by(['pd.id', 'pd.code', 'pd.name'])
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('pd.name', 'ASC')
            ->get()
            ->result_array();

        $categories = $this->db
            ->select('pc.id, pc.code, pc.name, pc.product_division_id, COUNT(p.id) AS product_count', false)
            ->from('mst_product_category pc')
            ->join('mst_product p', 'p.product_category_id = pc.id', 'inner')
            ->where('pc.is_active', 1)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $categories->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $categories->where('p.show_in_cashier', 1);
        }
        $categoryRows = $categories
            ->group_by(['pc.id', 'pc.code', 'pc.name', 'pc.product_division_id'])
            ->order_by('pc.sort_order', 'ASC')
            ->order_by('pc.name', 'ASC')
            ->get()
            ->result_array();

        return [
            'divisions' => $divisionRows,
            'categories' => $categoryRows,
        ];
    }

    public function stock_live_filter_options(): array
    {
        $divisions = [];
        if ($this->db->table_exists('mst_product_division')) {
            $db = $this->db
                ->select('id, code, name')
                ->from('mst_product_division')
                ->where('is_active', 1);
            if ($this->db->field_exists('sort_order', 'mst_product_division')) {
                $db->order_by('sort_order', 'ASC');
            }
            $divisions = $db
                ->order_by('name', 'ASC')
                ->get()
                ->result_array();
        }

        return [
            'outlets' => $this->local_outlet_options(),
            'divisions' => $divisions,
        ];
    }

    public function stock_live_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if (!in_array($status, ['ALL', 'AVAILABLE', 'LIMITED', 'OUT', 'HIDDEN'], true)) {
            $status = 'ALL';
        }
        $dirtyOnly = !empty($filters['dirty_only']);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 25)));

        $db = $this->db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_classification pc', 'pc.id = p.classification_id', 'left')
            ->join('mst_product_category cat', 'cat.id = p.product_category_id', 'left')
            ->where('p.is_active', 1);

        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.product_name', $q)
                ->or_like('p.product_code', $q)
                ->group_end();
        }
        if ($divisionId > 0) {
            $db->where('p.product_division_id', $divisionId);
        }
        if ($outletId > 0 && $this->db->table_exists('pos_product_availability_cache')) {
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($outletId), 'left', false);
            if ($status !== 'ALL') {
                $db->where('pac.availability_status', $status);
            }
            if ($dirtyOnly && $this->db->field_exists('is_dirty', 'pos_product_availability_cache')) {
                $db->where('pac.is_dirty', 1);
            }
        }

        $total = (int)$db->count_all_results('', false);
        $totalPages = max(1, (int)ceil($total / max(1, $limit)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.product_division_id',
            'p.default_operational_division_id',
            'p.uom_id',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'p.stock_mode',
            'pd.code AS product_division_code',
            'pd.name AS product_division_name',
            'pc.id AS classification_id',
            'pc.code AS classification_code',
            'pc.name AS classification_name',
            'cat.id AS product_category_id',
            'cat.code AS product_category_code',
            'cat.name AS product_category_name',
        ];
        if ($outletId > 0 && $this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.id AS cache_id';
            $select[] = 'pac.availability_status AS cache_availability_status';
            $select[] = 'pac.source_mode AS cache_source_mode';
            $select[] = 'pac.estimated_available_qty AS cache_estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot AS cache_bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed AS cache_override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS cache_hpp_live_snapshot';
            $select[] = 'pac.computed_at AS cache_computed_at';
            if ($this->db->field_exists('last_commit_event', 'pos_product_availability_cache')) {
                $select[] = 'pac.last_commit_event AS cache_last_commit_event';
            }
            if ($this->db->field_exists('is_dirty', 'pos_product_availability_cache')) {
                $select[] = 'pac.is_dirty AS cache_is_dirty';
            }
        }

        $db->select(implode(",\n", $select));
        if ($this->db->field_exists('sort_order', 'mst_product_division')) {
            $db->order_by('COALESCE(pd.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('pd.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_classification')) {
            $db->order_by('COALESCE(pc.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('pc.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_category')) {
            $db->order_by('COALESCE(cat.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('cat.name', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product')) {
            $db->order_by('COALESCE(p.sort_order, 999999)', 'ASC', false);
        }
        $rows = $db->order_by('p.product_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function stock_live_latest_log_map(int $outletId, array $productIds): array
    {
        $outletId = max(0, $outletId);
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($outletId <= 0 || empty($productIds) || !$this->db->table_exists('pos_product_availability_rebuild_log')) {
            return [];
        }

        $sub = $this->db
            ->select('product_id, MAX(id) AS max_id', false)
            ->from('pos_product_availability_rebuild_log')
            ->where('outlet_id', $outletId)
            ->where_in('product_id', $productIds)
            ->group_by('product_id')
            ->get_compiled_select();

        $rows = $this->db
            ->select('l.product_id, l.event_source, l.rebuilt_at, l.mismatch_flag, l.mismatch_note')
            ->from('pos_product_availability_rebuild_log l')
            ->join('(' . $sub . ') x', 'x.max_id = l.id', 'inner', false)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['product_id'] ?? 0)] = $row;
        }
        return $map;
    }

    public function find_active_cashier_session(int $employeeId): ?array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pos_cashier_session')) {
            return null;
        }

        return $this->db->select('
                s.*,
                o.outlet_name,
                t.terminal_name,
                e.employee_name AS cashier_name,
                sh.shift_no,
                sh.opening_cash,
                sh.opened_at
            ')
            ->from('pos_cashier_session s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = s.terminal_id', 'left')
            ->join('org_employee e', 'e.id = s.employee_id', 'left')
            ->join('pos_shift sh', 'sh.id = s.shift_id', 'left')
            ->where('s.employee_id', $employeeId)
            ->where('s.session_status', 'OPEN')
            ->order_by('s.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function active_cashier_sessions(): array
    {
        if (!$this->db->table_exists('pos_cashier_session')) {
            return [];
        }

        return $this->db->select('
                s.*,
                o.outlet_name,
                t.terminal_name,
                e.employee_name AS cashier_name,
                sh.shift_no,
                sh.opening_cash,
                sh.opened_at
            ')
            ->from('pos_cashier_session s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = s.terminal_id', 'left')
            ->join('org_employee e', 'e.id = s.employee_id', 'left')
            ->join('pos_shift sh', 'sh.id = s.shift_id', 'left')
            ->where('s.session_status', 'OPEN')
            ->order_by('s.id', 'DESC')
            ->get()
            ->result_array();
    }

    public function open_cashier_session(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee. Kasir belum bisa dibuka.'];
        }
        if (!$this->db->table_exists('pos_shift') || !$this->db->table_exists('pos_cashier_session')) {
            return ['ok' => false, 'message' => 'Schema shift/session POS belum siap.'];
        }

        $existing = $this->find_active_cashier_session($actorEmployeeId);
        if ($existing) {
            return ['ok' => true, 'session' => $existing, 'already_open' => true];
        }

        $outletId = (int)($payload['outlet_id'] ?? 0);
        $terminalId = (int)($payload['terminal_id'] ?? 0);
        $openingCash = round((float)($payload['opening_cash'] ?? 0), 2);
        if ($outletId <= 0 || !$this->local_record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet kasir wajib dipilih.'];
        }
        if ($terminalId <= 0 || !$this->local_record_exists('pos_terminal', $terminalId)) {
            return ['ok' => false, 'message' => 'Device/terminal kasir wajib dipilih.'];
        }
        if ($openingCash < 0) {
            return ['ok' => false, 'message' => 'Modal awal tidak boleh minus.'];
        }

        foreach ($this->active_cashier_sessions() as $activeSession) {
            if ((int)($activeSession['terminal_id'] ?? 0) !== $terminalId) {
                continue;
            }
            $cashierName = trim((string)($activeSession['cashier_name'] ?? '')) ?: 'kasir lain';
            return [
                'ok' => false,
                'code' => 'TERMINAL_BUSY',
                'active_session' => $activeSession,
                'message' => 'Terminal ini masih dipakai oleh kasir ' . $cashierName . '. Tutup sesi tersebut atau pilih device lain.',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        try {
            $shiftNo = $this->generate_pos_shift_no($outletId, $now);
            $this->db->insert('pos_shift', [
                'shift_no' => $shiftNo,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'cashier_open_employee_id' => $actorEmployeeId,
                'status' => 'OPEN',
                'opened_at' => $now,
                'opening_cash' => $openingCash,
                'expected_cash' => $openingCash,
                'actual_cash' => 0,
                'variance_cash' => 0,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);
            $shiftId = (int)$this->db->insert_id();
            if ($shiftId <= 0) {
                throw new RuntimeException('Gagal membuat shift kasir POS.');
            }

            $this->db->insert('pos_shift_summary', $this->filter_table_payload('pos_shift_summary', [
                'shift_id' => $shiftId,
                'total_order_count' => 0,
                'total_gross_sales' => 0,
                'total_discount' => 0,
                'total_promo' => 0,
                'total_net_sales' => 0,
                'total_cash_sales' => 0,
                'total_non_cash_sales' => 0,
                'total_refund' => 0,
                'total_void' => 0,
                'total_deposit_receipts' => 0,
                'total_cash_deposit_receipts' => 0,
            ]));

            $sessionKey = $this->generate_pos_session_key($outletId, $terminalId, $actorEmployeeId, $now);
            $this->db->insert('pos_cashier_session', [
                'session_key' => $sessionKey,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'shift_id' => $shiftId,
                'employee_id' => $actorEmployeeId,
                'session_status' => 'OPEN',
                'login_at' => $now,
                'last_ping_at' => $now,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);
            $sessionId = (int)$this->db->insert_id();
            if ($sessionId <= 0) {
                throw new RuntimeException('Gagal membuat cashier session POS.');
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal membuka kasir POS.');
            }
            $this->db->trans_commit();

            $session = $this->find_active_cashier_session($actorEmployeeId);
            return ['ok' => true, 'session' => $session, 'session_id' => $sessionId, 'shift_id' => $shiftId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function close_cashier_session(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee.'];
        }
        $session = $this->find_active_cashier_session($actorEmployeeId);
        if (!$session) {
            return ['ok' => false, 'message' => 'Tidak ada sesi kasir aktif yang bisa ditutup.'];
        }

        $shiftId = (int)($session['shift_id'] ?? 0);
        $cashBreakdown = $this->normalize_cash_breakdown($payload['cash_breakdown'] ?? []);
        $actualCash = !empty($cashBreakdown)
            ? round(array_sum(array_map(static function ($row) {
                return (float)($row['amount'] ?? 0);
            }, $cashBreakdown)), 2)
            : round((float)($payload['actual_cash'] ?? 0), 2);
        if ($actualCash < 0) {
            return ['ok' => false, 'message' => 'Kas aktual tidak boleh minus.'];
        }
        $summary = $this->calculate_shift_summary($shiftId);
        $expectedCash = round(
            (float)($session['opening_cash'] ?? 0)
            + (float)($summary['total_cash_sales'] ?? 0)
            + (float)($summary['total_cash_deposit_receipts'] ?? 0)
            - (float)($summary['total_refund'] ?? 0),
            2
        );
        $varianceCash = round($actualCash - $expectedCash, 2);
        $now = date('Y-m-d H:i:s');
        $closeNotes = $this->compose_shift_close_notes((string)($payload['notes'] ?? ''), $cashBreakdown);
        $accountSummaryRows = $this->shift_close_rows_by_account($shiftId);

        $this->db->trans_begin();
        try {
            $this->db->where('shift_id', $shiftId)->update('pos_shift_summary', $this->filter_table_payload('pos_shift_summary', $summary));
            $this->db->where('id', $shiftId)->update('pos_shift', [
                'cashier_close_employee_id' => $actorEmployeeId,
                'status' => 'CLOSED',
                'closed_at' => $now,
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'variance_cash' => $varianceCash,
                'notes' => $this->nullable_text($closeNotes),
            ]);
            $this->db->where('id', (int)$session['id'])->update('pos_cashier_session', [
                'session_status' => 'CLOSED',
                'logout_at' => $now,
                'last_ping_at' => $now,
                'notes' => $this->nullable_text($closeNotes),
            ]);
            $persistBreakdown = $this->persist_shift_cash_breakdown($shiftId, $cashBreakdown);
            if (!($persistBreakdown['ok'] ?? false)) {
                throw new RuntimeException((string)($persistBreakdown['message'] ?? 'Gagal menyimpan detail pecahan kasir.'));
            }
            $persistAccountSummary = $this->persist_shift_account_summary($shiftId, $accountSummaryRows);
            if (!($persistAccountSummary['ok'] ?? false)) {
                throw new RuntimeException((string)($persistAccountSummary['message'] ?? 'Gagal menyimpan snapshot rekening tutup shift.'));
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menutup sesi kasir.');
            }
            $this->db->trans_commit();

            $report = $this->shift_close_report($shiftId, $actualCash);

            return [
                'ok' => true,
                'shift_id' => $shiftId,
                'report' => $report,
                'summary' => $summary + [
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $actualCash,
                    'variance_cash' => $varianceCash,
                ],
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function cashier_close_preview(int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee.'];
        }

        $session = $this->find_active_cashier_session($actorEmployeeId);
        if (!$session) {
            return ['ok' => false, 'message' => 'Tidak ada sesi kasir aktif.'];
        }

        $shiftId = (int)($session['shift_id'] ?? 0);
        $report = $this->shift_close_report($shiftId);
        if (!$report) {
            return ['ok' => false, 'message' => 'Preview tutup kasir belum tersedia.'];
        }

        return [
            'ok' => true,
            'shift_id' => $shiftId,
            'session' => $session,
            'report' => $report,
        ];
    }

    public function order_draft_rows(array $filters): array
    {
        $hasCustomerName = $this->ensure_pos_order_customer_name_column();
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $workspaceMode = strtoupper(trim((string)($filters['workspace_mode'] ?? 'MIXED')));
        $cashierRecent = !empty($filters['cashier_recent']);
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo   = trim((string)($filters['date_to'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $db = $this->db;
        $hasTableNo = $this->db->field_exists('table_no', 'pos_order');
        $db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('pos_terminal pt', 'pt.id = o.terminal_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left');
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $db->join('pos_sales_channel sc', 'sc.id = o.sales_channel_id', 'left');
        }
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like($hasCustomerName ? 'o.customer_name' : 'm.member_name', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.member_no', $q);
            if ($hasTableNo) {
                $db->or_like('o.table_no', $q);
            }
            if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
                $db->or_like('sc.channel_name', $q);
            }
            $db->group_end();
        }
        if ($status === 'DRAFT') {
            $db->where_in('o.status', ['DRAFT', 'PENDING']);
        } elseif ($status === 'CONFIRMED') {
            $db->where('o.status', 'CONFIRMED');
        } elseif ($status === 'PAID') {
            $db->where_in('o.status', ['PAID', 'PAID_PARTIAL', 'READY', 'SERVED', 'REFUND_PARTIAL', 'REFUND_FULL', 'REFUNDED_FULL']);
        } else {
            if ($workspaceMode === 'PAID') {
                $db->where_in('o.status', ['PAID', 'PAID_PARTIAL', 'READY', 'SERVED', 'REFUND_PARTIAL', 'REFUND_FULL', 'REFUNDED_FULL']);
            } else {
                $db->where_in('o.status', ['DRAFT', 'PENDING', 'CONFIRMED', 'PAID_PARTIAL', 'IN_KITCHEN', 'READY', 'SERVED']);
            }
        }
        if ($cashierRecent) {
            // A self order only becomes a cashier order after it has been
            // verified/confirmed. Pending QRIS or abandoned self orders are
            // handled in the Self Order queue, not in the cashier workspace.
            $db->where("(UPPER(COALESCE(o.order_channel, '')) <> 'SELF_ORDER' OR UPPER(COALESCE(o.status, '')) NOT IN ('DRAFT', 'PENDING'))", null, false);
        }
        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }
        if ($dateFrom !== '') {
            $db->where('DATE(o.ordered_at) >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $db->where('DATE(o.ordered_at) <=', $dateTo);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $customerDisplayExpr = $this->order_customer_display_expr('o', 'm');
        $select = '
            o.id, o.order_no, o.service_type, o.status, o.stock_commit_status, o.ordered_at, o.confirmed_at,
            o.guest_count, ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ', o.grand_total, o.notes,
            po.outlet_name, pt.terminal_name, e.employee_name,
            m.member_no, m.member_name,
            ' . ($hasCustomerName ? 'o.customer_name' : 'NULL AS customer_name') . ',
            ' . $customerDisplayExpr . ' AS customer_display_name';
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $select .= ',
            o.sales_channel_id, sc.channel_code AS sales_channel_code, sc.channel_name AS sales_channel_name';
        }

        $rows = $db->select($select, false)
            ->order_by('o.ordered_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function final_payment_id_for_order(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        $row = $this->db->select('p.id')
            ->from('pos_payment p')
            ->where('p.order_id', $orderId)
            ->where('p.payment_type', 'FINAL')
            ->where_in('p.payment_status', ['PAID', 'PENDING'])
            ->order_by('p.id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        return (int)($row['id'] ?? 0);
    }

    public function find_order_draft(int $id): ?array
    {
        $this->ensure_pos_order_customer_name_column();
        $headerDb = $this->db
            ->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('pos_terminal pt', 'pt.id = o.terminal_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = o.cashier_employee_id', 'left');
        $hasOnlineDeliveryTable = $this->db->table_exists('pos_online_food_delivery_order');
        if ($hasOnlineDeliveryTable) {
            $headerDb->join('pos_online_food_delivery_order d', 'd.order_id = o.id', 'left');
        }
        $customerDisplayExpr = $this->order_customer_display_expr('o', 'm');
        $headerSelect = '
                o.*,
                po.outlet_name,
                pt.terminal_name,
                m.member_no,
                m.member_name,
                ' . $customerDisplayExpr . ' AS customer_display_name,
                m.mobile_phone AS member_mobile_phone,
                e.employee_name AS cashier_employee_name,
                UPPER(COALESCE(au.username, \'\')) AS cashier_username';
        if ($hasOnlineDeliveryTable) {
            $headerSelect .= ',
                d.delivery_status,
                d.delivery_provider,
                d.fee_charge_mode AS delivery_fee_charge_mode,
                d.fee_paid_by AS delivery_fee_paid_by,
                d.fee_amount AS delivery_fee_amount,
                d.estimated_fee_amount AS delivery_estimated_fee_amount,
                d.distance_km AS delivery_distance_km,
                d.route_distance_km AS delivery_route_distance_km,
                d.duration_min AS delivery_duration_min,
                d.recipient_name AS delivery_recipient_name,
                d.recipient_phone AS delivery_recipient_phone,
                d.delivery_address,
                d.address_note AS delivery_address_note,
                d.customer_lat,
                d.customer_lng';
        }
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $headerDb->join('pos_sales_channel sc', 'sc.id = o.sales_channel_id', 'left');
            $headerSelect .= ',
                sc.channel_code AS sales_channel_code,
                sc.channel_name AS sales_channel_name';
        }

        $header = $headerDb
            ->select($headerSelect, false)
            ->where('o.id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $lines = $this->db->select('
                l.*,
                p.product_code,
                p.product_name,
                b.bundle_code,
                b.bundle_name,
                bl.qty AS bundle_component_qty,
                pd.name AS product_division_name,
                u.code AS uom_code
            ')
            ->from('pos_order_line l')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('pos_product_bundle_line bl', 'bl.bundle_id = l.bundle_id AND bl.product_id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.order_id', $id)
            ->where('l.qty >', 0)
            ->where_not_in('l.line_status', ['VOID', 'REFUNDED_FULL'])
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $extras = $this->db->select('
                x.*,
                e.extra_code,
                e.extra_name,
                e.extra_type,
                e.source_kind,
                e.source_product_id,
                e.source_component_id,
                e.source_material_id,
                e.source_qty,
                e.replacement_kind,
                e.replacement_product_id,
                e.replacement_component_id,
                e.replacement_material_id,
                e.replacement_qty
            ')
            ->from('pos_order_line_extra x')
            ->join('mst_extra e', 'e.id = x.extra_id', 'left')
            ->where('x.order_id', $id)
            ->where('x.qty >', 0)
            ->order_by('x.order_line_id', 'ASC')
            ->order_by('x.line_no', 'ASC')
            ->get()
            ->result_array();
        $extraMap = [];
        foreach ($extras as $extra) {
            $lineId = (int)($extra['order_line_id'] ?? 0);
            if (!isset($extraMap[$lineId])) {
                $extraMap[$lineId] = [];
            }
            $extraMap[$lineId][] = $extra;
        }
        foreach ($lines as &$line) {
            $line['extras'] = $extraMap[(int)($line['id'] ?? 0)] ?? [];
        }
        unset($line);

        return ['header' => $header, 'lines' => $lines];
    }

    public function order_product_search(string $q, int $outletId = 0, int $limit = 12): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'pd.name AS product_division_name',
            'u.code AS uom_code',
        ];
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.availability_status';
            $select[] = 'pac.source_mode';
            $select[] = 'pac.estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS availability_hpp_live_snapshot';
            $select[] = 'pac.last_commit_event';
        }

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_uom u', 'u.id = p.uom_id', 'left');
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $joinOutletId = $outletId > 0 ? $outletId : -1;
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($joinOutletId), 'left', false);
        }
        $db->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        $db->group_start()
            ->like('p.product_code', $q)
            ->or_like('p.product_name', $q)
            ->group_end();

        return $db->select(implode(",\n", $select))
            ->order_by('pd.name', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_product_catalog(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $categoryId = max(0, (int)($filters['category_id'] ?? 0));
        $limit = max(1, min(120, (int)($filters['limit'] ?? 32)));

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'p.photo_path',
            'pd.id AS product_division_id',
            'pd.code AS product_division_code',
            'pd.name AS product_division_name',
            'pc.id AS product_category_id',
            'pc.code AS product_category_code',
            'pc.name AS product_category_name',
            'u.code AS uom_code',
        ];
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.availability_status';
            $select[] = 'pac.source_mode';
            $select[] = 'pac.estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS availability_hpp_live_snapshot';
        }

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->join('mst_uom u', 'u.id = p.uom_id', 'left');
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $joinOutletId = $outletId > 0 ? $outletId : -1;
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($joinOutletId), 'left', false);
        }
        $db->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        if ($divisionId > 0) {
            $db->where('p.product_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $db->where('p.product_category_id', $categoryId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.product_code', $q)
                ->or_like('p.product_name', $q)
                ->group_end();
        }

        return $db->select(implode(",\n", $select))
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('pc.sort_order', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_member_search(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        return $this->db
            ->select('id, member_no, member_name, mobile_phone, member_tier, member_status, point_balance_cache, stamp_balance_cache, total_spending')
            ->from('crm_member')
            ->where('is_active', 1)
            ->where('member_status !=', 'CLOSED')
            ->group_start()
                ->like('member_no', $q)
                ->or_like('member_name', $q)
                ->or_like('mobile_phone', $q)
            ->group_end()
            ->order_by('member_name', 'ASC')
            ->order_by('member_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_bundle_search(string $q, int $outletId = 0, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '' || !$this->db->table_exists('pos_product_bundle') || !$this->db->table_exists('pos_product_bundle_line')) {
            return [];
        }

        $bundleRows = $this->db
            ->select('b.id, b.bundle_code, b.bundle_name, b.selling_price, pd.name AS product_division_name')
            ->from('pos_product_bundle b')
            ->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left')
            ->where('b.is_active', 1)
            ->group_start()
                ->like('b.bundle_code', $q)
                ->or_like('b.bundle_name', $q)
            ->group_end()
            ->order_by('b.bundle_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
        if (empty($bundleRows)) {
            return [];
        }

        $this->load->library('PosBundlePricingService');
        $results = [];
        foreach ($bundleRows as $bundle) {
            $bundleId = (int)($bundle['id'] ?? 0);
            $bundleLines = $this->db
                ->select('
                    bl.product_id,
                    bl.qty,
                    bl.unit_price_override,
                    p.product_code,
                    p.product_name,
                    p.selling_price,
                    p.hpp_standard,
                    p.hpp_live_cache,
                    pd.name AS product_division_name,
                    u.code AS uom_code
                ')
                ->from('pos_product_bundle_line bl')
                ->join('mst_product p', 'p.id = bl.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->join('mst_uom u', 'u.id = p.uom_id', 'left')
                ->where('bl.bundle_id', $bundleId)
                ->order_by('bl.sort_order', 'ASC')
                ->order_by('bl.id', 'ASC')
                ->get()
                ->result_array();
            if (empty($bundleLines)) {
                continue;
            }

            $availabilityMap = $this->load_product_availability_map(array_column($bundleLines, 'product_id'), $outletId);
            $pricingLines = [];
            $availabilityStatus = 'AVAILABLE';
            $bottlenecks = [];
            $bundleEstimatedQty = null;

            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $availability = $availabilityMap[$productId] ?? [];
                $lineStatus = strtoupper((string)($availability['availability_status'] ?? 'CHECK'));
                if (in_array($lineStatus, ['OUT', 'EMPTY'], true)) {
                    $availabilityStatus = 'OUT';
                } elseif ($availabilityStatus !== 'OUT' && !in_array($lineStatus, ['AVAILABLE', 'OK'], true)) {
                    $availabilityStatus = 'CHECK';
                }

                $bottleneck = trim((string)($availability['bottleneck_name_snapshot'] ?? ''));
                if ($bottleneck !== '') {
                    $bottlenecks[$bottleneck] = $bottleneck;
                }

                $pricingLines[] = [
                    'product_id' => $productId,
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'qty' => (float)($line['qty'] ?? 0),
                    'base_unit_price' => (float)($line['selling_price'] ?? 0),
                    'override_unit_price' => $line['unit_price_override'] !== null ? (float)$line['unit_price_override'] : null,
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'division_name' => (string)($line['product_division_name'] ?? ''),
                    'hpp_live_unit' => (float)($availability['hpp_live_snapshot'] ?? $line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0),
                    'hpp_standard_unit' => (float)($line['hpp_standard'] ?? 0),
                    'cost_source_label' => 'Bundle POS',
                ];
            }

            $pricing = $this->posbundlepricingservice->allocate((float)($bundle['selling_price'] ?? 0), $pricingLines);
            $items = [];
            foreach ((array)($pricing['lines'] ?? []) as $pricingLine) {
                $productId = (int)($pricingLine['product_id'] ?? 0);
                $sourceRow = null;
                foreach ($bundleLines as $line) {
                    if ((int)$line['product_id'] === $productId) {
                        $sourceRow = $line;
                        break;
                    }
                }
                if ($sourceRow === null) {
                    continue;
                }
                $availability = $availabilityMap[$productId] ?? [];
                $items[] = [
                    'product_id' => $productId,
                    'product_code' => (string)($sourceRow['product_code'] ?? ''),
                    'product_name' => (string)($sourceRow['product_name'] ?? ''),
                    'product_division_name' => (string)($sourceRow['product_division_name'] ?? ''),
                    'uom_code' => (string)($sourceRow['uom_code'] ?? ''),
                    'qty' => (float)($pricingLine['qty'] ?? 0),
                    'unit_price' => (float)($pricingLine['allocated_unit_price'] ?? 0),
                    'allocated_total' => (float)($pricingLine['allocated_total'] ?? 0),
                    'hpp_standard' => (float)($pricingLine['hpp_standard_unit'] ?? 0),
                    'hpp_live_snapshot' => round((float)($availability['hpp_live_snapshot'] ?? $sourceRow['hpp_live_cache'] ?? $sourceRow['hpp_standard'] ?? 0), 6),
                    'availability_status' => (string)($availability['availability_status'] ?? 'CHECK'),
                    'estimated_available_qty' => (float)($availability['estimated_available_qty'] ?? 0),
                    'bottleneck_name_snapshot' => (string)($availability['bottleneck_name_snapshot'] ?? ''),
                ];
            }

            $results[] = [
                'id' => $bundleId,
                'bundle_code' => (string)($bundle['bundle_code'] ?? ''),
                'bundle_name' => (string)($bundle['bundle_name'] ?? ''),
                'selling_price' => (float)($bundle['selling_price'] ?? 0),
                'product_division_name' => (string)($bundle['product_division_name'] ?? 'Campuran Divisi'),
                'availability_status' => $availabilityStatus,
                'bottleneck_name_snapshot' => implode(', ', array_values($bottlenecks)),
                'line_count' => count($items),
                'items' => $items,
                'pricing_summary' => (array)($pricing['summary'] ?? []),
            ];
        }

        return $results;
    }

    public function order_bundle_catalog(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $limit = max(1, min(60, (int)($filters['limit'] ?? 24)));

        if (!$this->db->table_exists('pos_product_bundle') || !$this->db->table_exists('pos_product_bundle_line')) {
            return [];
        }

        $db = $this->db
            ->select("
                b.id,
                b.bundle_code,
                b.bundle_name,
                b.selling_price,
                b.product_division_id,
                pd.code AS product_division_code,
                pd.name AS product_division_name,
                (
                    SELECT COUNT(*)
                    FROM pos_product_bundle_line blc
                    WHERE blc.bundle_id = b.id
                ) AS line_count,
                (
                    SELECT p2.photo_path
                    FROM pos_product_bundle_line bl2
                    INNER JOIN mst_product p2 ON p2.id = bl2.product_id
                    WHERE bl2.bundle_id = b.id
                    ORDER BY bl2.sort_order ASC, bl2.id ASC
                    LIMIT 1
                ) AS photo_path
            ", false)
            ->from('pos_product_bundle b')
            ->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left')
            ->where('b.is_active', 1);

        if ($divisionId > 0) {
            $db->where('b.product_division_id', $divisionId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('b.bundle_code', $q)
                ->or_like('b.bundle_name', $q)
                ->group_end();
        }

        $bundleRows = $db
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('b.bundle_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        if (empty($bundleRows)) {
            return [];
        }

        $this->load->library('PosBundlePricingService');
        $results = [];
        foreach ($bundleRows as $bundle) {
            $bundleId = (int)($bundle['id'] ?? 0);
            $bundleLines = $this->db
                ->select('
                    bl.product_id,
                    bl.qty,
                    bl.unit_price_override,
                    p.product_code,
                    p.product_name,
                    p.selling_price,
                    p.hpp_standard,
                    p.hpp_live_cache,
                    pd.name AS product_division_name,
                    u.code AS uom_code
                ')
                ->from('pos_product_bundle_line bl')
                ->join('mst_product p', 'p.id = bl.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->join('mst_uom u', 'u.id = p.uom_id', 'left')
                ->where('bl.bundle_id', $bundleId)
                ->order_by('bl.sort_order', 'ASC')
                ->order_by('bl.id', 'ASC')
                ->get()
                ->result_array();
            if (empty($bundleLines)) {
                continue;
            }

            $availabilityMap = $this->load_product_availability_map(array_column($bundleLines, 'product_id'), $outletId);
            $pricingLines = [];
            $availabilityStatus = 'AVAILABLE';
            $bottlenecks = [];

            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $availability = $availabilityMap[$productId] ?? [];
                $lineStatus = strtoupper((string)($availability['availability_status'] ?? 'CHECK'));
                if (in_array($lineStatus, ['OUT', 'EMPTY'], true)) {
                    $availabilityStatus = 'OUT';
                } elseif ($availabilityStatus !== 'OUT' && !in_array($lineStatus, ['AVAILABLE', 'OK'], true)) {
                    $availabilityStatus = 'CHECK';
                }

                $bottleneck = trim((string)($availability['bottleneck_name_snapshot'] ?? ''));
                if ($bottleneck !== '') {
                    $bottlenecks[$bottleneck] = $bottleneck;
                }

                $availableQty = (float)($availability['estimated_available_qty'] ?? 0);
                $bundleLineQty = max((float)($line['qty'] ?? 0), 0.000001);
                $bundleAvailableByLine = $availableQty > 0 ? floor($availableQty / $bundleLineQty) : 0;
                if (!isset($bundleEstimatedQty)) {
                    $bundleEstimatedQty = $bundleAvailableByLine;
                } else {
                    $bundleEstimatedQty = min($bundleEstimatedQty, $bundleAvailableByLine);
                }

                $pricingLines[] = [
                    'product_id' => $productId,
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'reference_unit_price' => (float)($line['selling_price'] ?? 0),
                    'override_unit_price' => isset($line['unit_price_override']) ? (float)$line['unit_price_override'] : null,
                    'qty' => (float)($line['qty'] ?? 0),
                    'hpp_live_snapshot' => (float)($availability['hpp_live_snapshot'] ?? $line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0),
                ];
            }

            $allocation = $this->posbundlepricingservice->allocate((float)($bundle['selling_price'] ?? 0), $pricingLines);
            $allocatedMap = [];
            foreach ((array)($allocation['lines'] ?? []) as $allocatedLine) {
                $allocatedMap[(int)($allocatedLine['product_id'] ?? 0)] = $allocatedLine;
            }

            $items = [];
            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $allocated = $allocatedMap[$productId] ?? [];
                $availability = $availabilityMap[$productId] ?? [];
                $items[] = [
                    'product_id' => $productId,
                    'product_code' => (string)($line['product_code'] ?? ''),
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'product_division_name' => (string)($line['product_division_name'] ?? ''),
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'qty' => round((float)($line['qty'] ?? 0), 4),
                    'unit_price' => round((float)($allocated['allocated_unit_price'] ?? 0), 2),
                    'line_total' => round((float)($allocated['allocated_line_total'] ?? 0), 2),
                    'hpp_live_snapshot' => round((float)($availability['hpp_live_snapshot'] ?? $line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0), 6),
                    'availability_status' => (string)($availability['availability_status'] ?? 'CHECK'),
                    'estimated_available_qty' => round((float)($availability['estimated_available_qty'] ?? 0), 4),
                ];
            }

            $bundle['availability_status'] = $availabilityStatus;
            $bundle['bottleneck_name_snapshot'] = implode(', ', array_values($bottlenecks));
            $bundle['estimated_available_qty'] = max(0, (int)round((float)($bundleEstimatedQty ?? 0), 0));
            $bundle['items'] = $items;
            $results[] = $bundle;
        }

        return $results;
    }

    public function order_extra_options(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }
        $map = $this->load_product_extra_group_map([$productId]);
        return $map[$productId] ?? [];
    }

    public function save_order_draft(array $payload, int $actorEmployeeId, bool $allowMobileBackup = false): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee. Order draft POS belum bisa dibuat.'];
        }

        $this->ensure_pos_order_customer_name_column();

        $id = (int)($payload['id'] ?? 0);
        $outletId = (int)($payload['outlet_id'] ?? 0);
        $terminalId = !empty($payload['terminal_id']) ? (int)$payload['terminal_id'] : null;
        if ($outletId <= 0 || !$this->local_record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet POS wajib dipilih dan harus tersedia di database lokal POS.'];
        }
        if ($terminalId !== null && !$this->local_record_exists('pos_terminal', $terminalId)) {
            return ['ok' => false, 'message' => 'Terminal POS tidak ditemukan di database lokal POS.'];
        }
        $memberId = !empty($payload['member_id']) ? (int)$payload['member_id'] : null;
        if ($memberId !== null && !$this->local_record_exists('crm_member', $memberId)) {
            return ['ok' => false, 'message' => 'Member POS tidak ditemukan. Pilih member yang valid atau kosongkan transaksi untuk walk in.'];
        }
        $memberName = '';
        if ($memberId !== null) {
            $memberRow = $this->find_member($memberId);
            $memberName = trim((string)($memberRow['member_name'] ?? ''));
        }
        $customerName = $this->resolve_order_customer_name($payload['customer_name'] ?? '', $memberName);

        $serviceType = strtoupper(trim((string)($payload['service_type'] ?? 'DINE_IN')));
        if (!in_array($serviceType, ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'DINE_IN';
        }
        $hasSalesChannelSchema = $this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order');
        $salesChannelId = !empty($payload['sales_channel_id']) ? (int)$payload['sales_channel_id'] : 0;
        $salesChannel = null;
        if ($hasSalesChannelSchema) {
            if ($salesChannelId <= 0) {
                $salesChannelId = (int)($this->default_sales_channel_id() ?? 0);
            }
            if ($salesChannelId > 0 && !$this->local_record_exists('pos_sales_channel', $salesChannelId)) {
                return ['ok' => false, 'message' => 'Sales channel POS tidak ditemukan. Pilih channel penjualan yang valid.'];
            }
            if ($salesChannelId > 0) {
                $salesChannel = $this->find_sales_channel($salesChannelId);
                if (!$salesChannel) {
                    return ['ok' => false, 'message' => 'Sales channel POS tidak ditemukan.'];
                }
                $allowedTypes = (array)($salesChannel['allowed_service_type_list'] ?? []);
                if (!empty($allowedTypes) && !in_array($serviceType, $allowedTypes, true)) {
                    return ['ok' => false, 'message' => 'Service type tidak diizinkan untuk sales channel yang dipilih.'];
                }
            }
        } else {
            $salesChannelId = 0;
        }
        $requireActiveSession = !empty($payload['require_active_session']);
        $activeSession = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        if ($requireActiveSession && !$activeSession) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka. Pilih outlet, device, dan modal awal dulu sebelum input transaksi.'];
        }
        if ($activeSession) {
            if ((int)($activeSession['outlet_id'] ?? 0) !== $outletId) {
                return ['ok' => false, 'message' => 'Outlet transaksi harus sama dengan outlet sesi kasir yang sedang aktif.'];
            }
            $mobileBackupMode = $allowMobileBackup && !empty($payload['mobile_backup_mode']);
            if (
                $terminalId !== null
                && (int)($activeSession['terminal_id'] ?? 0) !== $terminalId
                && !$mobileBackupMode
            ) {
                return ['ok' => false, 'message' => 'Device transaksi harus sama dengan device sesi kasir yang sedang aktif.'];
            }
            $terminalId = (int)($activeSession['terminal_id'] ?? $terminalId);
        }

        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $normalized = $this->normalize_order_draft_lines($lines, $outletId);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }

        $appendedLineIds = [];
        $isConfirmedAppend = false;
        $headerOnlyUpdate = false;

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $existing = null;
            $existingOrder = null;
            $existingPaidTotal = 0.0;
            $existingHasSettlement = false;
            if ($id > 0) {
                $existing = $this->db->query('SELECT * FROM pos_order WHERE id = ? LIMIT 1 FOR UPDATE', [$id])->row_array();
                if (!$existing) {
                    throw new RuntimeException('Draft order POS tidak ditemukan.');
                }
                if (!in_array((string)($existing['status'] ?? ''), ['DRAFT', 'PENDING', 'CONFIRMED'], true)) {
                    throw new RuntimeException('Hanya order POS berstatus DRAFT/PENDING/CONFIRMED yang bisa dibuka dari kasir.');
                }
                $isConfirmedAppend = strtoupper((string)($existing['status'] ?? '')) === 'CONFIRMED';
                if ($isConfirmedAppend) {
                    $existingOrder = $this->find_order_draft($id);
                    if (!$existingOrder) {
                        throw new RuntimeException('Order POS tersimpan tidak ditemukan saat append transaksi.');
                    }
                }
                $existingPaidTotal = round((float)($existing['paid_total'] ?? 0), 2);
                $existingHasSettlement = $existingPaidTotal > 0.009 || !empty($existing['paid_at']);
            }

            $headerTotals = $this->calculate_order_totals((array)($normalized['rows'] ?? []));
            $nextPersistedStatus = $isConfirmedAppend ? 'CONFIRMED' : 'DRAFT';
            if ($isConfirmedAppend && $existingHasSettlement && round((float)($headerTotals['grand_total'] ?? 0), 2) <= ($existingPaidTotal + 0.009)) {
                $nextPersistedStatus = 'PAID';
            }
            $headerPayload = [
                'order_channel' => 'CASHIER',
                'order_scope' => 'REGULAR',
                'service_type' => $serviceType,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'shift_id' => !empty($activeSession['shift_id']) ? (int)$activeSession['shift_id'] : null,
                'cashier_session_id' => !empty($activeSession['id']) ? (int)$activeSession['id'] : null,
                'cashier_employee_id' => $actorEmployeeId,
                'member_id' => $memberId,
                'customer_name' => $this->nullable_text($customerName),
                'guest_count' => max(1, (int)($payload['guest_count'] ?? 1)),
                'table_no' => $this->nullable_text($payload['table_no'] ?? ''),
                'subtotal_amount' => $headerTotals['subtotal_amount'],
                'grand_total' => $headerTotals['grand_total'],
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
                'status' => $nextPersistedStatus,
                'kitchen_status' => $isConfirmedAppend ? (string)($existing['kitchen_status'] ?? 'PENDING') : 'PENDING',
                'stock_commit_status' => $isConfirmedAppend ? (string)($existing['stock_commit_status'] ?? 'POSTED') : 'PENDING',
            ];
            if ($nextPersistedStatus === 'PAID' && empty($existing['paid_at'])) {
                $headerPayload['paid_at'] = date('Y-m-d H:i:s');
            }
            if ($hasSalesChannelSchema) {
                $headerPayload['sales_channel_id'] = $salesChannelId > 0 ? $salesChannelId : null;
            }

            if ($id > 0) {
                $this->db->where('id', $id)->update('pos_order', $this->filter_table_payload('pos_order', $headerPayload));
                if (!$isConfirmedAppend) {
                    $this->db->where('order_id', $id)->delete('pos_order_line_extra');
                    $this->db->where('order_id', $id)->delete('pos_order_line');
                }
            } else {
                $headerPayload['order_no'] = $this->generate_pos_order_no();
                $headerPayload['ordered_at'] = date('Y-m-d H:i:s');
                $this->db->insert('pos_order', $this->filter_table_payload('pos_order', $headerPayload));
                $id = (int)$this->db->insert_id();
                if ($id <= 0) {
                    throw new RuntimeException($this->db_error_message('Gagal membuat header draft order POS.'));
                }
            }

            $rowsToPersist = (array)($normalized['rows'] ?? []);
            $lineNo = 1;
            if ($isConfirmedAppend) {
                $appendPlan = $this->prepare_confirmed_order_append((array)$existingOrder, $rowsToPersist);
                if (!($appendPlan['ok'] ?? false)) {
                    throw new RuntimeException((string)($appendPlan['message'] ?? 'Order confirmed tidak bisa diappend dari kasir.'));
                }
                $rowsToPersist = (array)($appendPlan['new_rows'] ?? []);
                $headerOnlyUpdate = empty($rowsToPersist);
                $lineNoRow = $this->db->select('COALESCE(MAX(line_no), 0) AS max_line_no', false)
                    ->from('pos_order_line')
                    ->where('order_id', $id)
                    ->get()
                    ->row_array();
                $lineNo = (int)($lineNoRow['max_line_no'] ?? 0) + 1;
            }

            foreach ($rowsToPersist as $row) {
                $linePayload = [
                    'order_id' => $id,
                    'line_no' => $lineNo++,
                    'product_id' => (int)$row['product_id'],
                    'bundle_id' => !empty($row['bundle_id']) ? (int)$row['bundle_id'] : null,
                    'line_type' => 'PRODUCT',
                    'product_division_id_snapshot' => $row['product_division_id_snapshot'],
                    'operational_division_id' => $row['operational_division_id'],
                    'uom_id' => $row['uom_id'],
                    'qty' => $row['qty'],
                    'unit_price' => $row['unit_price'],
                    'discount_amount' => 0,
                    'net_amount' => $row['net_amount'],
                    'hpp_standard_snapshot' => $row['hpp_standard_snapshot'],
                    'hpp_live_snapshot' => $row['hpp_live_snapshot'],
                    'cogs_amount' => $row['cogs_amount'],
                    'availability_mode_snapshot' => $row['availability_mode_snapshot'],
                    'line_status' => 'OPEN',
                    'process_status' => 'NOT_PROCESSED',
                    'notes' => $row['notes'],
                ];
                $this->db->insert('pos_order_line', $this->filter_table_payload('pos_order_line', $linePayload));
                $orderLineId = (int)$this->db->insert_id();
                if ($orderLineId <= 0) {
                    throw new RuntimeException($this->db_error_message('Gagal membuat line draft order POS.'));
                }
                $appendedLineIds[] = $orderLineId;
                $extraLineNo = 1;
                foreach ((array)($row['extras'] ?? []) as $extra) {
                    $this->db->insert('pos_order_line_extra', $this->filter_table_payload('pos_order_line_extra', [
                        'order_id' => $id,
                        'order_line_id' => $orderLineId,
                        'line_no' => $extraLineNo++,
                        'extra_id' => (int)$extra['extra_id'],
                        'qty' => $extra['qty'],
                        'unit_price' => $extra['unit_price'],
                        'net_amount' => $extra['net_amount'],
                        'cost_amount_snapshot' => $extra['cost_amount_snapshot'],
                        'notes' => $extra['notes'],
                    ]));
                    if ((int)$this->db->insert_id() <= 0) {
                        throw new RuntimeException($this->db_error_message('Gagal membuat extra draft order POS.'));
                    }
                }
            }

            if ($id > 0) {
                if ($isConfirmedAppend) {
                    $appendCount = count($appendedLineIds);
                    $statusAfterSave = (string)($headerPayload['status'] ?? 'CONFIRMED');
                    $this->insert_order_state_log(
                        $id,
                        (string)($existing['status'] ?? 'CONFIRMED'),
                        $statusAfterSave,
                        $statusAfterSave === 'PAID' ? 'ORDER_APPEND_SETTLED' : 'ORDER_APPEND_UPDATE',
                        $actorEmployeeId,
                        $statusAfterSave === 'PAID'
                            ? (
                                $headerOnlyUpdate
                                    ? 'Header order confirmed diperbarui dan totalnya tetap tertutup pembayaran yang sudah ada, sehingga order masuk ke pesanan terbayar.'
                                    : ('Order confirmed diperbarui dengan ' . $appendCount . ' line baru dan totalnya tertutup pembayaran yang sudah ada, sehingga order masuk ke pesanan terbayar.')
                            )
                            : (
                                $headerOnlyUpdate
                                    ? 'Header order confirmed diperbarui tanpa item baru.'
                                    : ('Order confirmed diperbarui dengan ' . $appendCount . ' line baru dari kasir.')
                            )
                    );
                } else {
                    $this->insert_order_state_log($id, (string)($existing['status'] ?? 'DRAFT'), 'DRAFT', 'ORDER_DRAFT_UPDATE', $actorEmployeeId, 'Draft order diperbarui.');
                }
            } else {
                $this->insert_order_state_log($id, null, 'DRAFT', 'ORDER_DRAFT_CREATE', $actorEmployeeId, 'Draft order dibuat.');
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal menyimpan draft order POS.'));
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return [
                'ok' => true,
                'id' => $id,
                'order_no' => (string)($headerPayload['order_no'] ?? ($existing['order_no'] ?? '')),
                'append_mode' => $isConfirmedAppend,
                'header_only_update' => $isConfirmedAppend && $headerOnlyUpdate,
                'appended_line_ids' => $isConfirmedAppend ? $appendedLineIds : [],
                'appended_line_count' => $isConfirmedAppend ? count($appendedLineIds) : 0,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function missing_recipe_stock_commit_warning(array $productNames): string
    {
        $names = [];
        foreach ($productNames as $name) {
            $clean = trim((string)$name);
            if ($clean !== '') {
                $names[] = $clean;
            }
        }
        $names = array_values(array_unique($names));
        if (empty($names)) {
            return '';
        }
        return 'Recipe product belum tersedia untuk: ' . implode(', ', $names) . '. Transaksi tetap tersimpan dan pembayaran tetap bisa diproses, tetapi stock commit untuk item tersebut belum dijalankan.';
    }

    public function delete_order_draft(int $orderId, int $actorEmployeeId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Draft order POS tidak valid.'];
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $row = $this->db->query('SELECT * FROM pos_order WHERE id = ? LIMIT 1 FOR UPDATE', [$orderId])->row_array();
            if (!$row) {
                throw new RuntimeException('Draft order POS tidak ditemukan.');
            }

            $status = strtoupper(trim((string)($row['status'] ?? 'DRAFT')));
            if (!in_array($status, ['DRAFT', 'PENDING', 'CONFIRMED'], true)) {
                throw new RuntimeException('Order POS dengan status ' . $status . ' tidak bisa dihapus dari sini.');
            }

            if ($this->db->table_exists('pos_runtime_job')) {
                $activeJobCount = (int)$this->db->from('pos_runtime_job')
                    ->where('order_id', $orderId)
                    ->where_in('status', ['QUEUED', 'PROCESSING'])
                    ->count_all_results();
                if ($activeJobCount > 0) {
                    throw new RuntimeException('Draft ini masih punya job stock commit yang sedang queued/processing, jadi belum aman dihapus.');
                }
            }

            $paymentCount = 0;
            if ($this->db->table_exists('pos_payment')) {
                $paymentCount = (int)$this->db->from('pos_payment')
                    ->where('order_id', $orderId)
                    ->count_all_results();
            }
            if ($paymentCount > 0) {
                throw new RuntimeException('Draft ini sudah memiliki jejak pembayaran, jadi tidak bisa dihapus langsung.');
            }

            // Runtime jobs keep a foreign key to the stock snapshot. Remove
            // completed jobs before a fully reversed snapshot is purged.
            if ($this->db->table_exists('pos_runtime_job')) {
                $this->db->where('order_id', $orderId)->delete('pos_runtime_job');
            }

            if ($this->db->table_exists('pos_stock_commit')) {
                $snapshotRows = $this->db->select('id, commit_status')
                    ->from('pos_stock_commit')
                    ->where('order_id', $orderId)
                    ->get()
                    ->result_array();
                $snapshotIds = [];
                foreach ($snapshotRows as $snapshotRow) {
                    $snapshotIds[] = (int)($snapshotRow['id'] ?? 0);
                    $commitStatus = strtoupper(trim((string)($snapshotRow['commit_status'] ?? 'DRAFT')));
                    // A fully reversed or voided snapshot no longer affects
                    // inventory, so a payment-free draft may be purged safely.
                    if (in_array($commitStatus, ['COMMITTED', 'PARTIAL_REVERSED'], true)) {
                        throw new RuntimeException('Draft ini sudah punya snapshot stock commit yang terlanjur memengaruhi stok, jadi tidak bisa dihapus langsung.');
                    }
                }
                $snapshotIds = array_values(array_filter($snapshotIds));
                if (!empty($snapshotIds) && $this->db->table_exists('pos_stock_commit_line')) {
                    $this->db->where_in('commit_id', $snapshotIds)->delete('pos_stock_commit_line');
                }
                if (!empty($snapshotIds)) {
                    $this->db->where('order_id', $orderId)->delete('pos_stock_commit');
                }
            }

            if ($this->db->table_exists('pos_order_monitor_task')) {
                $this->db->where('order_id', $orderId)->delete('pos_order_monitor_task');
            }
            if ($this->db->table_exists('pos_order_state_log')) {
                $this->db->where('order_id', $orderId)->delete('pos_order_state_log');
            }
            if ($this->db->table_exists('pos_order_line_extra')) {
                $this->db->where('order_id', $orderId)->delete('pos_order_line_extra');
            }
            if ($this->db->table_exists('pos_order_line')) {
                $this->db->where('order_id', $orderId)->delete('pos_order_line');
            }
            $this->db->where('id', $orderId)->delete('pos_order');

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menghapus draft order POS.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'id' => $orderId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolve_order_stock_commit_payload(int $orderId, int $actorEmployeeId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order draft POS tidak valid.'];
        }
        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order draft POS tidak ditemukan.'];
        }
        $header = (array)($order['header'] ?? []);
        $lines = (array)($order['lines'] ?? []);
        $targetLineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        $allowedStatusesOverride = array_values(array_unique(array_filter(array_map(static function ($status): string {
            return strtoupper(trim((string)$status));
        }, (array)($options['allowed_statuses'] ?? [])))));
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Order draft belum memiliki produk.'];
        }
        $allowedStatuses = !empty($allowedStatusesOverride)
            ? $allowedStatusesOverride
            : (empty($targetLineIds) ? ['DRAFT', 'PENDING'] : ['DRAFT', 'PENDING', 'CONFIRMED']);
        if (!in_array((string)($header['status'] ?? ''), $allowedStatuses, true)) {
            return ['ok' => false, 'message' => 'Status order POS tidak valid untuk konfirmasi transaksi dari kasir.'];
        }
        if (empty($targetLineIds) && (string)($header['stock_commit_status'] ?? '') === 'POSTED') {
            return ['ok' => false, 'message' => 'Order ini sudah pernah melakukan stock commit.'];
        }
        if (!empty($targetLineIds)) {
            $lineIdMap = array_fill_keys($targetLineIds, true);
            $lines = array_values(array_filter($lines, static function ($line) use ($lineIdMap) {
                $lineId = (int)($line['id'] ?? 0);
                return $lineId > 0 && isset($lineIdMap[$lineId]);
            }));
            if (empty($lines)) {
                return ['ok' => false, 'message' => 'Line transaksi baru tidak ditemukan untuk stock commit tambahan POS.'];
            }
        }

        $resolvedLines = [];
        $resolvedLineNo = 1;
        $missingRecipeProducts = [];
        foreach ($lines as $line) {
            $recipeRows = $this->load_order_product_recipe_rows((int)($line['product_id'] ?? 0));
            if (empty($recipeRows)) {
                $missingRecipeProducts[] = (string)($line['product_name'] ?? '-');
                continue;
            }
            $productResolvedLines = [];
            foreach ($recipeRows as $recipeRow) {
                $resolved = $this->resolve_order_recipe_consumption_line($header, $line, $recipeRow);
                if (!($resolved['ok'] ?? false)) {
                    return $resolved;
                }
                $productResolvedLines[] = (array)($resolved['line'] ?? []);
            }

            $extraResolvedLines = [];
            foreach ((array)($line['extras'] ?? []) as $extraLine) {
                $extraType = strtoupper(trim((string)($extraLine['extra_type'] ?? 'ADD')));
                if (in_array($extraType, ['REMOVE', 'CHOICE'], true)) {
                    $adjusted = $this->apply_order_extra_removal_to_resolved_lines($header, $line, $productResolvedLines, $extraLine);
                    if (!($adjusted['ok'] ?? false)) {
                        return $adjusted;
                    }
                    $productResolvedLines = (array)($adjusted['lines'] ?? []);
                }

                if ($extraType === 'ADD') {
                    $resolvedExtra = $this->resolve_order_extra_consumption_line($header, $line, $extraLine);
                    if (!($resolvedExtra['ok'] ?? false)) {
                        return $resolvedExtra;
                    }
                    foreach ((array)($resolvedExtra['lines'] ?? []) as $resolvedExtraLine) {
                        $extraResolvedLines[] = $resolvedExtraLine;
                    }
                }

                if (in_array($extraType, ['REMOVE', 'CHOICE'], true)) {
                    $replacement = $this->resolve_order_extra_replacement_consumption_line($header, $line, $extraLine);
                    if (!($replacement['ok'] ?? false)) {
                        return $replacement;
                    }
                    foreach ((array)($replacement['lines'] ?? []) as $replacementLine) {
                        $extraResolvedLines[] = $replacementLine;
                    }
                }
            }

            foreach ($productResolvedLines as $resolvedProductLine) {
                $resolvedLines[] = [
                    'line_no' => $resolvedLineNo++,
                ] + $resolvedProductLine;
            }
            foreach ($extraResolvedLines as $resolvedExtraLine) {
                $resolvedLines[] = [
                    'line_no' => $resolvedLineNo++,
                ] + $resolvedExtraLine;
            }
        }

        $warningMessage = $this->missing_recipe_stock_commit_warning($missingRecipeProducts);

        if (empty($resolvedLines)) {
            if ($warningMessage !== '') {
                return [
                    'ok' => true,
                    'header' => [
                        'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                        'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                        'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                        'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                        'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : (!empty($header['cashier_employee_id']) ? (int)$header['cashier_employee_id'] : null),
                        'commit_status' => 'DRAFT',
                        'commit_reason' => 'ORDER_CONFIRM',
                        'process_state_snapshot' => 'NONE',
                        'notes' => empty($targetLineIds)
                            ? ('Confirm order POS tanpa snapshot stok karena recipe belum tersedia untuk ' . implode(', ', array_unique($missingRecipeProducts)) . '.')
                            : ('Append transaksi POS tanpa snapshot stok karena recipe belum tersedia untuk ' . implode(', ', array_unique($missingRecipeProducts)) . '.'),
                    ],
                    'lines' => [],
                    'resolved_line_count' => 0,
                    'warning_message' => $warningMessage,
                    'missing_recipe_products' => array_values(array_unique($missingRecipeProducts)),
                ];
            }
            return ['ok' => false, 'message' => 'Tidak ada konsumsi stok yang berhasil di-resolve dari recipe produk.'];
        }

        return [
            'ok' => true,
            'header' => [
                'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : (!empty($header['cashier_employee_id']) ? (int)$header['cashier_employee_id'] : null),
                'commit_status' => 'DRAFT',
                'commit_reason' => 'ORDER_CONFIRM',
                'process_state_snapshot' => 'NONE',
                'notes' => empty($targetLineIds)
                    ? ('Snapshot commit dari confirm draft order POS ' . (string)($header['order_no'] ?? ''))
                    : ('Snapshot commit append transaksi POS ' . (string)($header['order_no'] ?? '')),
            ],
            'lines' => $resolvedLines,
            'resolved_line_count' => count($resolvedLines),
            'warning_message' => $warningMessage,
            'missing_recipe_products' => array_values(array_unique($missingRecipeProducts)),
        ];
    }

    public function finalize_order_confirmation(int $orderId, int $snapshotId, int $actorEmployeeId, string $stockCommitStatus = 'POSTED'): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan saat finalisasi confirm.'];
        }

        $stockCommitStatus = strtoupper(trim($stockCommitStatus));
        if (!in_array($stockCommitStatus, ['PENDING', 'QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED', 'NOT_REQUIRED'], true)) {
            $stockCommitStatus = 'POSTED';
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $isAppendConfirm = strtoupper((string)($row['status'] ?? 'DRAFT')) === 'CONFIRMED';
            $orderPayload = [
                'status' => 'CONFIRMED',
                'stock_commit_status' => $stockCommitStatus,
            ];
            if (!$isAppendConfirm || empty($row['confirmed_at'])) {
                $orderPayload['confirmed_at'] = date('Y-m-d H:i:s');
            }
            if ($stockCommitStatus === 'POSTED') {
                $orderPayload['stock_committed_at'] = date('Y-m-d H:i:s');
            }
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $orderPayload));

            if ($stockCommitStatus === 'NOT_REQUIRED' || $snapshotId <= 0) {
                $note = $isAppendConfirm
                    ? 'Order confirmed diappend dari kasir tanpa snapshot stock commit.'
                    : 'Draft order dikonfirmasi tanpa snapshot stock commit.';
            } else {
                $note = $isAppendConfirm
                    ? ('Order confirmed diappend dari kasir dan snapshot stock commit #' . $snapshotId . ' dibuat.')
                    : ('Draft order dikonfirmasi dan snapshot stock commit #' . $snapshotId . ' dibuat.');
            }
            if ($stockCommitStatus === 'QUEUED') {
                $note .= ' Posting stok dipindah ke queue runtime POS.';
            } elseif ($stockCommitStatus === 'NOT_REQUIRED') {
                $note .= ' Snapshot stock commit dilewati karena recipe product belum tersedia.';
            }
            $this->insert_order_state_log(
                $orderId,
                (string)($row['status'] ?? 'DRAFT'),
                'CONFIRMED',
                $isAppendConfirm ? 'ORDER_APPEND_CONFIRM' : 'ORDER_CONFIRM',
                $actorEmployeeId,
                $note
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memfinalkan status confirm order POS.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'id' => $orderId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function update_order_stock_commit_state(int $orderId, string $stockCommitStatus, array $meta = []): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan saat update stock commit state.'];
        }

        $stockCommitStatus = strtoupper(trim($stockCommitStatus));
        if (!in_array($stockCommitStatus, ['PENDING', 'QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED', 'NOT_REQUIRED'], true)) {
            return ['ok' => false, 'message' => 'Status stock commit POS tidak valid.'];
        }

        $currentOrderStatus = strtoupper(trim((string)($row['status'] ?? '')));
        $currentStockCommitStatus = strtoupper(trim((string)($row['stock_commit_status'] ?? '')));
        if ($currentStockCommitStatus === 'REVERSED' && $stockCommitStatus !== 'REVERSED') {
            return ['ok' => false, 'message' => 'Stock commit order sudah direversal dan tidak boleh diproses ulang.'];
        }
        if (in_array($currentOrderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true) && $stockCommitStatus !== 'REVERSED') {
            return ['ok' => false, 'message' => 'Order sudah ' . $currentOrderStatus . '; status stock commit tidak boleh diubah lagi.'];
        }

        $eventCode = strtoupper(trim((string)($meta['event_code'] ?? 'ORDER_STOCK_COMMIT_STATUS')));
        $actorEmployeeId = max(0, (int)($meta['actor_employee_id'] ?? 0));
        $note = trim((string)($meta['note'] ?? ''));
        $orderStatus = strtoupper(trim((string)($meta['status'] ?? ($row['status'] ?? 'CONFIRMED'))));

        $payload = [
            'stock_commit_status' => $stockCommitStatus,
        ];
        if ($orderStatus !== '') {
            $payload['status'] = $orderStatus;
        }
        if ($stockCommitStatus === 'POSTED') {
            $payload['stock_committed_at'] = (string)($meta['stock_committed_at'] ?? date('Y-m-d H:i:s'));
        }
        if ($stockCommitStatus === 'REVERSED') {
            $payload['stock_reversed_at'] = (string)($meta['stock_reversed_at'] ?? date('Y-m-d H:i:s'));
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $payload));
            $this->insert_order_state_log(
                $orderId,
                (string)($row['status'] ?? 'CONFIRMED'),
                (string)($payload['status'] ?? $row['status'] ?? 'CONFIRMED'),
                $eventCode,
                $actorEmployeeId,
                $note !== '' ? $note : ('Status stock commit POS berubah ke ' . $stockCommitStatus . '.')
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan perubahan stock commit state POS.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return [
                'ok' => true,
                'id' => $orderId,
                'status' => (string)($payload['status'] ?? $row['status'] ?? 'CONFIRMED'),
                'stock_commit_status' => $stockCommitStatus,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function direct_print_targets_for_order_confirm(int $orderId, int $snapshotId = 0): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk cetak pesanan.'];
        }
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->routes_enabled()) {
            return $this->printer_runtime_not_ready('cetak pesanan');
        }
        return $this->direct_print_targets_from_config_for_order_confirm($orderId, $snapshotId);
    }
    public function direct_print_targets_for_order_reprint(int $orderId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk cetak ulang.'];
        }
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->routes_enabled()) {
            return $this->printer_runtime_not_ready('cetak ulang pesanan');
        }
        return $this->direct_print_targets_from_config_for_order_reprint($orderId, $options);
    }
    private function direct_print_targets_from_config_for_order_confirm(int $orderId, int $snapshotId = 0): array
    {
        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan untuk direct print.'];
        }
        $lines = (array)($order['lines'] ?? []);
        $snapshotLineIds = $this->resolve_direct_print_line_ids_for_snapshot($orderId, $snapshotId);
        if (is_array($snapshotLineIds)) {
            if (empty($snapshotLineIds)) {
                return ['ok' => true, 'targets' => []];
            }
            $lineIdMap = array_fill_keys($snapshotLineIds, true);
            $lines = array_values(array_filter($lines, static function ($line) use ($lineIdMap) {
                $lineId = (int)($line['id'] ?? 0);
                return $lineId > 0 && isset($lineIdMap[$lineId]);
            }));
        }
        if (empty($lines)) {
            return ['ok' => true, 'targets' => []];
        }
        return [
            'ok' => true,
            'targets' => $this->configured_order_kot_targets($order, $lines, 'AUTO'),
        ];
    }

    private function direct_print_targets_from_config_for_order_reprint(int $orderId, array $options = []): array
    {
        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan untuk cetak ulang.'];
        }
        $outletId = (int)($order['header']['outlet_id'] ?? 0);
        $terminalId = (int)($order['header']['terminal_id'] ?? 0);
        $selectedPrinterId = max(0, (int)($options['printer_id'] ?? 0));
        $routes = $this->configured_print_routes('ORDER_CONFIRM_KOT', $outletId, $terminalId);
        $routes = $this->filter_configured_routes_by_printer($routes, $selectedPrinterId);
        if ($selectedPrinterId > 0 && empty($routes)) {
            $prebillRoutes = $this->filter_configured_routes_by_printer(
                $this->configured_print_routes('ORDER_PRE_BILL', $outletId, $terminalId),
                $selectedPrinterId
            );
            if (!empty($prebillRoutes)) {
                if ($this->order_remaining_due_amount((array)($order['header'] ?? [])) <= 0.009) {
                    return ['ok' => false, 'message' => 'Order ini sudah lunas. Bill sementara hanya dapat dicetak untuk order yang belum terbayar.'];
                }
                return ['ok' => true, 'targets' => $this->configured_prebill_targets($order, $prebillRoutes, 'REPRINT')];
            }
            return ['ok' => false, 'message' => 'Koneksi printer tujuan tidak memiliki aturan cetak aktif untuk order ini.'];
        }
        $lines = (array)($order['lines'] ?? []);
        if (strtoupper(trim((string)($options['line_scope'] ?? 'ALL'))) === 'LATEST') {
            $snapshotLineIds = $this->resolve_direct_print_line_ids_for_snapshot($orderId, 0);
            if (!is_array($snapshotLineIds) || empty($snapshotLineIds)) {
                return ['ok' => false, 'message' => 'Belum ada snapshot item terbaru untuk order ini. Pilih semua item untuk mencetak ulang seluruh pesanan.'];
            }
            $lineIdMap = array_fill_keys($snapshotLineIds, true);
            $lines = array_values(array_filter($lines, static function ($line) use ($lineIdMap) {
                return isset($lineIdMap[(int)($line['id'] ?? 0)]);
            }));
        }
        return ['ok' => true, 'targets' => $this->configured_order_kot_targets($order, $lines, 'REPRINT', $routes)];
    }

    private function direct_print_targets_from_config_for_payment(int $paymentId, bool $respectAutoPrint): array
    {
        $document = $this->find_payment_print_document($paymentId);
        if (!$document) {
            return ['ok' => false, 'message' => 'Dokumen payment POS tidak ditemukan untuk direct print.'];
        }
        $header = (array)($document['header'] ?? []);
        $routes = $this->configured_print_routes(
            'ORDER_PAID_RECEIPT',
            (int)($header['outlet_id'] ?? 0),
            (int)($header['terminal_id'] ?? 0)
        );
        $targets = [];
        foreach ($routes as $route) {
            $template = $this->configured_print_template($route);
            $printer = $this->configured_printer_payload($route);
            $text = $this->build_direct_payment_receipt_text($document, $printer, $template);
            $target = $this->configured_print_target($route, $template, $text, [
                'event_code' => 'ORDER_PAID_RECEIPT',
                'document_type' => 'RECEIPT',
                'order_id' => (int)($header['order_id'] ?? 0),
                'payment_id' => $paymentId,
                'outlet_id' => (int)($header['outlet_id'] ?? 0),
                'terminal_id' => (int)($header['terminal_id'] ?? 0),
                'line_count' => count((array)($document['lines'] ?? [])),
                'attempt_kind' => $respectAutoPrint ? 'AUTO' : 'REPRINT',
            ]);
            if ($target) {
                $targets[] = $target;
            }
        }
        return ['ok' => true, 'targets' => $targets];
    }

    private function direct_print_targets_from_config_for_shift_close(int $shiftId, array $report = []): array
    {
        if (empty($report)) {
            $report = (array)$this->shift_close_report($shiftId);
        }
        $shift = (array)($report['shift'] ?? []);
        if (empty($shift)) {
            return ['ok' => false, 'message' => 'Laporan tutup kasir tidak tersedia untuk direct print.'];
        }
        $routes = $this->configured_print_routes('SHIFT_CLOSE_SUMMARY', (int)($shift['outlet_id'] ?? 0), (int)($shift['terminal_id'] ?? 0));
        $targets = [];
        foreach ($routes as $route) {
            $template = $this->configured_print_template($route);
            $printer = $this->configured_printer_payload($route);
            $text = $this->build_direct_shift_close_receipt_text($report, $printer, $template);
            $target = $this->configured_print_target($route, $template, $text, [
                'event_code' => 'SHIFT_CLOSE_SUMMARY',
                'document_type' => 'SHIFT_CLOSE',
                'outlet_id' => (int)($shift['outlet_id'] ?? 0),
                'terminal_id' => (int)($shift['terminal_id'] ?? 0),
                'attempt_kind' => 'AUTO',
            ]);
            if ($target) {
                $targets[] = $target;
            }
        }
        return ['ok' => true, 'targets' => $targets];
    }

    private function direct_print_targets_from_config_for_reversal(string $documentType, int $documentId): array
    {
        $documentType = strtoupper(trim($documentType));
        $document = $documentType === 'VOID_SLIP'
            ? $this->find_void_print_document($documentId)
            : $this->find_refund_print_document($documentId);
        if (!$document) {
            return ['ok' => false, 'message' => 'Dokumen reversal POS tidak ditemukan untuk direct print.'];
        }
        $header = (array)($document['header'] ?? []);
        $routes = $this->configured_print_routes($documentType, (int)($header['outlet_id'] ?? 0), (int)($header['terminal_id'] ?? 0));
        $targets = [];
        foreach ($routes as $route) {
            $selectedLines = $this->configured_route_lines((array)($document['lines'] ?? []), $route);
            if (empty($selectedLines)) {
                continue;
            }
            $template = $this->configured_print_template($route);
            $printer = $this->configured_printer_payload($route);
            $text = $this->build_direct_reversal_slip_text($documentType, [
                'header' => $header,
                'lines' => $selectedLines,
            ], $printer, $template);
            $target = $this->configured_print_target($route, $template, $text, [
                'event_code' => $documentType,
                'document_type' => $documentType,
                'order_id' => (int)($header['order_id'] ?? 0),
                'void_id' => $documentType === 'VOID_SLIP' ? $documentId : 0,
                'refund_id' => $documentType === 'REFUND_SLIP' ? $documentId : 0,
                'outlet_id' => (int)($header['outlet_id'] ?? 0),
                'terminal_id' => (int)($header['terminal_id'] ?? 0),
                'line_count' => count($selectedLines),
                'attempt_kind' => 'AUTO',
            ]);
            if ($target) {
                $targets[] = $target;
            }
        }
        return ['ok' => true, 'targets' => $targets];
    }

    private function configured_order_kot_targets(array $order, array $lines, string $attemptKind = 'AUTO', array $routes = []): array
    {
        $header = (array)($order['header'] ?? []);
        if (empty($routes)) {
            $routes = $this->configured_print_routes('ORDER_CONFIRM_KOT', (int)($header['outlet_id'] ?? 0), (int)($header['terminal_id'] ?? 0));
        }
        $targets = [];
        foreach ($routes as $route) {
            $selectedLines = $this->configured_route_lines($lines, $route);
            if (empty($selectedLines)) {
                continue;
            }
            $printLines = array_map(function ($line) {
                return $this->build_direct_print_order_line((array)$line);
            }, $selectedLines);
            $template = $this->configured_print_template($route);
            $printer = $this->configured_printer_payload($route);
            $text = $this->build_direct_kot_text($header, $printLines, $printer, $template);
            $target = $this->configured_print_target($route, $template, $text, [
                'event_code' => 'ORDER_CONFIRM_KOT',
                'document_type' => 'KITCHEN_TICKET',
                'order_id' => (int)($header['id'] ?? 0),
                'outlet_id' => (int)($header['outlet_id'] ?? 0),
                'terminal_id' => (int)($header['terminal_id'] ?? 0),
                'line_count' => count($printLines),
                'attempt_kind' => $attemptKind,
            ]);
            if ($target) {
                $targets[] = $target;
            }
        }
        return $targets;
    }

    private function configured_prebill_targets(array $order, array $routes, string $attemptKind = 'REPRINT'): array
    {
        $header = (array)($order['header'] ?? []);
        $targets = [];
        foreach ($routes as $route) {
            $template = $this->configured_print_template($route);
            $printer = $this->configured_printer_payload($route);
            $text = $this->build_direct_order_prebill_text($order, $printer, $template);
            $target = $this->configured_print_target($route, $template, $text, [
                'event_code' => 'ORDER_PRE_BILL',
                'document_type' => 'RECEIPT',
                'order_id' => (int)($header['id'] ?? 0),
                'outlet_id' => (int)($header['outlet_id'] ?? 0),
                'terminal_id' => (int)($header['terminal_id'] ?? 0),
                'line_count' => count((array)($order['lines'] ?? [])),
                'attempt_kind' => $attemptKind,
            ]);
            if ($target) {
                $targets[] = $target;
            }
        }
        return $targets;
    }

    private function configured_print_routes(string $eventCode, int $outletId, int $terminalId): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->active_routes($eventCode, $outletId, $terminalId) : [];
    }

    private function filter_configured_routes_by_printer(array $routes, int $printerId): array
    {
        if ($printerId <= 0) {
            return $routes;
        }
        return array_values(array_filter($routes, static function (array $route) use ($printerId): bool {
            return (int)($route['connection_id'] ?? 0) === $printerId
                || (int)($route['legacy_printer_id'] ?? 0) === $printerId;
        }));
    }

    private function configured_route_lines(array $lines, array $route): array
    {
        if (strtoupper((string)($route['content_scope'] ?? 'ALL_ITEMS')) === 'ALL_ITEMS') {
            return array_values(array_filter($lines, 'is_array'));
        }
        $operationalDivisionId = (int)($route['operational_division_id'] ?? 0);
        $productDivisionId = (int)($route['product_division_id'] ?? 0);
        return array_values(array_filter($lines, static function ($line) use ($operationalDivisionId, $productDivisionId): bool {
            if (!is_array($line)) {
                return false;
            }
            if ($operationalDivisionId > 0 && (int)($line['operational_division_id'] ?? 0) !== $operationalDivisionId) {
                return false;
            }
            if ($productDivisionId > 0 && (int)($line['product_division_id_snapshot'] ?? $line['product_division_id'] ?? 0) !== $productDivisionId) {
                return false;
            }
            return true;
        }));
    }

    private function configured_print_template(array $route): array
    {
        $printerConfig = $this->printer_config_model();
        return $printerConfig ? $printerConfig->runtime_template($route) : ['document_type' => 'RECEIPT', 'payload' => []];
    }

    private function configured_printer_payload(array $route): array
    {
        $paperWidth = (int)($route['paper_width_mm'] ?? 80) === 58 ? 58 : 80;
        return [
            'printer_id' => (int)($route['connection_id'] ?? 0),
            'printer_code' => (string)($route['agent_printer_code'] ?? ''),
            'printer_name' => (string)($route['connection_name'] ?? ''),
            'printer_role' => (string)($route['location_label'] ?? 'CUSTOM'),
            'print_scope' => (string)($route['content_scope'] ?? 'ALL_ITEMS'),
            'agent_host' => (string)($route['agent_host'] ?? ''),
            'python_port' => (int)($route['python_port'] ?? 0),
            'paper_width_mm' => $paperWidth,
            'chars_per_line' => $this->normalize_printer_chars_per_line($paperWidth, (int)($route['chars_per_line'] ?? 48)),
            'copies' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
            'cut_mode' => in_array(strtoupper((string)($route['cut_mode'] ?? 'PARTIAL')), ['NONE', 'PARTIAL', 'FULL'], true)
                ? strtoupper((string)($route['cut_mode'] ?? 'PARTIAL'))
                : 'PARTIAL',
            'open_drawer' => !empty($route['open_drawer']) ? 1 : 0,
        ];
    }

    private function configured_print_target(array $route, array $template, string $text, array $meta): ?array
    {
        $printer = $this->configured_printer_payload($route);
        if (trim((string)($printer['printer_code'] ?? '')) === '' || (int)($printer['python_port'] ?? 0) <= 0) {
            return null;
        }
        $printerConfig = $this->printer_config_model();
        $attemptId = $printerConfig ? $printerConfig->create_attempt(array_merge($meta, [
            'route_id' => (int)($route['id'] ?? 0),
            'connection_id' => (int)($route['connection_id'] ?? 0),
            'layout_id' => (int)($route['layout_id'] ?? 0),
            'connection_name' => (string)($route['connection_name'] ?? ''),
            'connection_code' => (string)($route['connection_code'] ?? ''),
            'route_name' => (string)($route['route_name'] ?? ''),
            'route_code' => (string)($route['route_code'] ?? ''),
            'layout_name' => (string)($route['layout_name'] ?? ''),
            'layout_code' => (string)($route['layout_code'] ?? ''),
            'document_type' => (string)($template['document_type'] ?? $meta['document_type'] ?? 'RECEIPT'),
            'copy_count' => max(1, (int)($printer['copies'] ?? 1)),
        ])) : 0;
        return array_merge($printer, [
            'route_id' => (int)($route['id'] ?? 0),
            'route_code' => (string)($route['route_code'] ?? ''),
            'layout_id' => (int)($route['layout_id'] ?? 0),
            'layout_code' => (string)($route['layout_code'] ?? ''),
            'document_type' => (string)($template['document_type'] ?? $meta['document_type'] ?? 'RECEIPT'),
            'print_mode' => strtoupper((string)($route['print_mode'] ?? 'AUTO')),
            'print_attempt_id' => $attemptId,
            'text' => $text,
        ]);
    }

    public function direct_print_targets_for_payment(int $paymentId, bool $respectAutoPrint = true): array
    {
        if ($paymentId <= 0) {
            return ['ok' => false, 'message' => 'Pembayaran POS tidak valid untuk cetak struk.'];
        }
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->routes_enabled()) {
            return $this->printer_runtime_not_ready('cetak struk pembayaran');
        }
        return $this->direct_print_targets_from_config_for_payment($paymentId, $respectAutoPrint);
    }
    public function direct_print_targets_for_void(int $voidId): array
    {
        return $this->direct_print_targets_for_reversal_document('VOID_SLIP', $voidId);
    }

    public function direct_print_targets_for_refund(int $refundId): array
    {
        return $this->direct_print_targets_for_reversal_document('REFUND_SLIP', $refundId);
    }

    public function find_mobile_print_document_context(string $documentType, int $documentId): ?array
    {
        $documentType = strtoupper(trim($documentType));
        $documents = [
            'VOID' => ['table' => 'pos_void', 'alias' => 'v'],
            'REFUND' => ['table' => 'pos_refund', 'alias' => 'r'],
            'PAYMENT' => ['table' => 'pos_payment', 'alias' => 'p'],
        ];
        if ($documentId <= 0 || !isset($documents[$documentType])) {
            return null;
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $document = $documents[$documentType];
            if (!$this->db->table_exists($document['table']) || !$this->db->table_exists('pos_order')) {
                return null;
            }

            $alias = $document['alias'];
            $query = $this->db
                ->select($alias . '.id AS document_id, o.id AS order_id, o.outlet_id, o.terminal_id')
                ->from($document['table'] . ' ' . $alias)
                ->join('pos_order o', 'o.id = ' . $alias . '.order_id', 'inner')
                ->where($alias . '.id', $documentId)
                ->limit(1)
                ->get();
            if (!$query) {
                return null;
            }
            $row = $query->row_array();
        } catch (Throwable $e) {
            return null;
        } finally {
            $this->db->db_debug = $previousDbDebug;
        }
        if (!$row) {
            return null;
        }

        return [
            'document_id' => (int)($row['document_id'] ?? 0),
            'order_id' => (int)($row['order_id'] ?? 0),
            'outlet_id' => (int)($row['outlet_id'] ?? 0),
            'terminal_id' => (int)($row['terminal_id'] ?? 0),
        ];
    }

    public function direct_print_targets_for_shift_close(int $shiftId, array $report = []): array
    {
        if ($shiftId <= 0) {
            return ['ok' => false, 'message' => 'Shift POS tidak valid untuk cetak penutupan kasir.'];
        }
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->routes_enabled()) {
            return $this->printer_runtime_not_ready('cetak penutupan kasir');
        }
        return $this->direct_print_targets_from_config_for_shift_close($shiftId, $report);
    }
    private function direct_print_targets_for_reversal_document(string $documentType, int $documentId): array
    {
        $documentType = strtoupper(trim($documentType));
        if ($documentId <= 0) {
            return ['ok' => false, 'message' => 'Dokumen pembatalan POS tidak valid untuk dicetak.'];
        }
        if (!in_array($documentType, ['VOID_SLIP', 'REFUND_SLIP'], true)) {
            return ['ok' => false, 'message' => 'Jenis dokumen pembatalan tidak dikenali.'];
        }
        $printerConfig = $this->printer_config_model();
        if (!$printerConfig || !$printerConfig->routes_enabled()) {
            return $this->printer_runtime_not_ready('cetak dokumen pembatalan');
        }
        return $this->direct_print_targets_from_config_for_reversal($documentType, $documentId);
    }
    public function order_reversal_preview(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid.'];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
        }

        $this->load->library('PosStockCommitService');

        $effectiveHeaderCommitStatus = strtoupper(trim((string)($order['header']['stock_commit_status'] ?? '')));
        $processByOrderLine = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $processByOrderLine[(int)($line['id'] ?? 0)] = $this->effective_reversal_process_status($effectiveHeaderCommitStatus, $line);
        }
        foreach ((array)($order['lines'] ?? []) as $index => $line) {
            $orderLineId = (int)($line['id'] ?? 0);
            $effectiveProcessStatus = $processByOrderLine[$orderLineId] ?? 'NOT_PROCESSED';
            $order['lines'][$index]['process_status'] = $effectiveProcessStatus;
            foreach ((array)($line['extras'] ?? []) as $extraIndex => $extra) {
                $order['lines'][$index]['extras'][$extraIndex]['process_status'] = $effectiveProcessStatus;
            }
        }

        $plan = $this->build_order_reversal_plan($orderId, $order, $processByOrderLine);
        if (!($plan['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($plan['message'] ?? 'Gagal menyiapkan reversal plan.')];
        }

        return [
            'ok' => true,
            'order' => $order,
            'plan' => $plan,
        ];
    }

        public function save_order_void(array $payload, int $actorEmployeeId): array
        {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee untuk void POS.'];
        }

        $orderId = (int)($payload['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk void.'];
        }

        $this->load->library('PosOrderStockService');

        $this->db->trans_begin();
        try {
            // Void shares this lock with the stock worker. The reversal plan is
            // rebuilt only after the latest order state is safely locked.
            if (!$this->lock_order_for_update($orderId)) {
                throw new RuntimeException('Order POS tidak ditemukan untuk void.');
            }
            $preview = $this->order_reversal_preview($orderId);
            if (!($preview['ok'] ?? false)) {
                throw new RuntimeException((string)($preview['message'] ?? 'Gagal membaca order terbaru untuk void.'));
            }

            $order = (array)($preview['order'] ?? []);
            $header = (array)($order['header'] ?? []);
            if (in_array((string)($header['status'] ?? ''), ['VOID', 'REFUND_FULL'], true)) {
                throw new RuntimeException('Order ini sudah tidak bisa di-void lagi.');
            }
            $hasRecordedPayment = (float)($header['paid_total'] ?? 0) > 0.009 || !empty($header['paid_at']);
            if (in_array((string)($header['status'] ?? ''), ['PAID', 'PAID_PARTIAL', 'REFUND_PARTIAL', 'SERVED'], true) || $hasRecordedPayment) {
                throw new RuntimeException('Order yang sudah dibayar/disajikan tidak boleh di-void. Gunakan refund agar jejak pembayarannya tetap rapi.');
            }

            $selection = $this->build_reversal_selection($order, (array)($preview['plan']['lines'] ?? []), (array)($payload['lines'] ?? []), [
                'default_return_to_stock' => !empty($payload['return_to_stock']),
                'default_processed_state' => array_key_exists('processed_state', $payload)
                    ? strtoupper(trim((string)$payload['processed_state']))
                    : '',
            ]);
            if (empty($selection['product_lines']) && empty($selection['extra_lines'])) {
                throw new RuntimeException('Tidak ada line void yang valid.');
            }

            $voidPayload = [
                'void_no' => $this->generate_pos_void_no(),
                'order_id' => $orderId,
                'payment_id' => null,
                'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                'member_id' => !empty($header['member_id']) ? (int)$header['member_id'] : null,
                'actor_employee_id' => $actorEmployeeId,
                'approved_by' => null,
                'void_scope' => !empty($selection['is_full']) ? 'FULL' : 'PARTIAL',
                'processed_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                'return_to_stock' => !empty($selection['return_to_stock']) ? 1 : 0,
                'adjustment_mode' => $this->normalize_pos_adjustment_mode((string)($payload['adjustment_mode'] ?? 'NONE')),
                'order_status_before' => (string)($header['status'] ?? 'CONFIRMED'),
                'order_status_after' => !empty($selection['is_full']) ? 'VOID' : (string)($header['status'] ?? 'CONFIRMED'),
                'order_no_snapshot' => (string)($header['order_no'] ?? ''),
                'member_name_snapshot' => $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? ''),
                'service_type_snapshot' => (string)($header['service_type'] ?? ''),
                'reason' => $this->nullable_text($payload['reason'] ?? ''),
                'line_count' => count((array)($selection['product_lines'] ?? [])),
                'extra_count' => count((array)($selection['extra_lines'] ?? [])),
                'total_qty_void' => round((float)($selection['total_qty'] ?? 0), 4),
                'amount_void' => round((float)($selection['total_amount'] ?? 0), 2),
            ];
            $this->db->insert('pos_void', $voidPayload);
            $voidId = (int)$this->db->insert_id();
            if ($voidId <= 0) {
                throw new RuntimeException('Gagal membuat dokumen void POS.');
            }

            $voidLineMap = [];
            foreach ((array)($selection['product_lines'] ?? []) as $idx => $line) {
                $this->db->insert('pos_void_line', [
                    'void_id' => $voidId,
                    'order_id' => $orderId,
                    'order_line_id' => (int)$line['order_line_id'],
                    'product_id' => !empty($line['product_id']) ? (int)$line['product_id'] : null,
                    'line_no_snapshot' => (int)($line['line_no_snapshot'] ?? ($idx + 1)),
                    'item_name_snapshot' => (string)$line['item_name_snapshot'],
                    'qty_before' => round((float)$line['qty_before'], 4),
                    'qty_void' => round((float)$line['qty_void'], 4),
                    'qty_after' => round((float)$line['qty_after'], 4),
                    'unit_price' => round((float)$line['unit_price'], 2),
                    'subtotal_void' => round((float)$line['subtotal_void'], 2),
                    'hpp_live_snapshot' => round((float)$line['hpp_live_snapshot'], 6),
                    'line_process_state' => (string)$line['line_process_state'],
                    'line_status_after' => (string)$line['line_status_after'],
                    'notes' => $this->nullable_text($line['notes'] ?? ''),
                ]);
                $voidLineMap[(int)$line['order_line_id']] = (int)$this->db->insert_id();
            }

            foreach ((array)($selection['extra_lines'] ?? []) as $extra) {
                $this->db->insert('pos_void_line_extra', [
                    'void_id' => $voidId,
                    'void_line_id' => $voidLineMap[(int)$extra['order_line_id']] ?? null,
                    'order_id' => $orderId,
                    'order_line_id' => (int)$extra['order_line_id'],
                    'order_line_extra_id' => (int)$extra['order_line_extra_id'],
                    'extra_id' => !empty($extra['extra_id']) ? (int)$extra['extra_id'] : null,
                    'extra_name_snapshot' => (string)$extra['extra_name_snapshot'],
                    'qty_per_unit' => round((float)$extra['qty_per_unit'], 4),
                    'line_qty_affected' => round((float)$extra['line_qty_affected'], 4),
                    'unit_price' => round((float)$extra['unit_price'], 2),
                    'subtotal_void' => round((float)$extra['subtotal_void'], 2),
                    'status_after' => (string)$extra['status_after'],
                ]);
            }

            $reverse = $this->reverse_order_commit_decisions((array)($selection['decisions'] ?? []), $actorEmployeeId, 'Void POS #' . $voidId, [
                'adjustment_mode' => (string)($voidPayload['adjustment_mode'] ?? 'NONE'),
                'reason' => (string)($payload['reason'] ?? ''),
                'reason_code' => (string)($payload['reason_code'] ?? ''),
                'document_type' => 'VOID',
                'document_id' => $voidId,
                'document_no' => (string)($voidPayload['void_no'] ?? ''),
            ]);
            if (!($reverse['ok'] ?? false)) {
                throw new RuntimeException((string)($reverse['message'] ?? 'Gagal reversal stok untuk void POS.'));
            }

            $reversalState = $this->apply_order_line_reversal_updates(
                $orderId,
                (array)($selection['product_lines'] ?? []),
                (array)($selection['extra_lines'] ?? []),
                'VOID'
            );
            $newOrderStatus = !empty($reversalState['has_active_lines']) ? (string)($header['status'] ?? 'CONFIRMED') : 'VOID';
            $orderUpdate = ['status' => $newOrderStatus];
            if ($newOrderStatus === 'VOID') {
                $orderUpdate['kitchen_status'] = 'VOID';
            }
            $this->db->where('id', $orderId)->update('pos_order', $orderUpdate);
            $this->insert_order_state_log($orderId, (string)($header['status'] ?? 'CONFIRMED'), $newOrderStatus, 'ORDER_VOID', $actorEmployeeId, 'Void POS #' . $voidId);

            $runtimeCancelledCount = 0;
            if ($newOrderStatus === 'VOID') {
                $runtimeCancel = $this->cancel_active_runtime_jobs_for_terminal_order(
                    $orderId,
                    'Order di-void; job stock commit dibatalkan agar stok tidak diposting ulang.'
                );
                if (!($runtimeCancel['ok'] ?? false)) {
                    throw new RuntimeException((string)($runtimeCancel['message'] ?? 'Gagal membatalkan job stock POS setelah void.'));
                }
                $runtimeCancelledCount = (int)($runtimeCancel['cancelled_count'] ?? 0);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan void POS.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $availabilityRebuild = $this->refresh_pos_reversal_availability_after_commit(
            $order,
            $actorEmployeeId,
            'ORDER_VOID',
            $voidId
        );
        return [
            'ok' => true,
            'id' => $voidId,
            'void_no' => (string)$voidPayload['void_no'],
            'order_status' => $newOrderStatus,
            'adjustment_doc_count' => (int)($reverse['adjustment_doc_count'] ?? 0),
            'runtime_job_cancelled_count' => $runtimeCancelledCount,
            'availability_rebuild' => $availabilityRebuild,
            'warning' => $availabilityRebuild['warning'],
        ];
    }

    public function save_order_refund(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee untuk refund POS.'];
        }

        $orderId = (int)($payload['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS untuk refund tidak valid.'];
        }

        $refundPaymentMethodId = !empty($payload['payment_method_id']) ? (int)$payload['payment_method_id'] : 0;
        if ($refundPaymentMethodId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran pengembalian wajib dipilih untuk refund POS.'];
        }
        $refundMethod = $this->find_payment_method($refundPaymentMethodId);
        if (!$refundMethod || (int)($refundMethod['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Metode pembayaran pengembalian tidak valid atau tidak aktif.'];
        }
        $companyAccountId = (int)($refundMethod['company_account_id'] ?? 0);
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran refund belum terhubung ke rekening perusahaan.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Schema keuangan belum siap, refund belum bisa diposting ke mutasi rekening.'];
        }
        $companyAccount = $this->db->query(
            'SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1',
            [$companyAccountId]
        )->row_array();
        if (!$companyAccount) {
            return ['ok' => false, 'message' => 'Rekening perusahaan untuk metode refund tidak ditemukan atau tidak aktif.'];
        }

        $this->load->library('PosOrderStockService');

        $this->db->trans_begin();
        try {
            // Serialize refunds for one order. A second request must read the
            // result of the first refund instead of using a stale preview.
            if (!$this->lock_order_for_update($orderId)) {
                throw new RuntimeException('Order POS tidak ditemukan untuk refund.');
            }

            $preview = $this->order_reversal_preview($orderId);
            if (!($preview['ok'] ?? false)) {
                throw new RuntimeException((string)($preview['message'] ?? 'Gagal membaca order terbaru untuk refund.'));
            }

            $order = (array)($preview['order'] ?? []);
            $header = (array)($order['header'] ?? []);
            $hasRecordedPayment = (float)($header['paid_total'] ?? 0) > 0.009 || !empty($header['paid_at']);
            if (!in_array((string)($header['status'] ?? ''), ['PAID', 'PAID_PARTIAL', 'READY', 'SERVED', 'REFUND_PARTIAL'], true) && !$hasRecordedPayment) {
                throw new RuntimeException('Refund hanya boleh dipakai untuk order yang sudah masuk tahap bayar / siap saji / served.');
            }
            if ((float)($header['paid_total'] ?? 0) <= 0 && empty($header['paid_at'])) {
                throw new RuntimeException('Order ini belum memiliki pembayaran tercatat, jadi refund belum bisa dibuat.');
            }

            $selection = $this->build_reversal_selection($order, (array)($preview['plan']['lines'] ?? []), (array)($payload['lines'] ?? []), [
                'default_return_to_stock' => !empty($payload['return_to_stock']),
                'default_processed_state' => array_key_exists('processed_state', $payload)
                    ? strtoupper(trim((string)$payload['processed_state']))
                    : '',
            ]);
            if (empty($selection['product_lines']) && empty($selection['extra_lines'])) {
                throw new RuntimeException('Tidak ada line refund yang masih dapat dikembalikan. Muat ulang order lalu periksa line yang tersisa.');
            }

            $alreadyRefunded = $this->posted_order_refund_total_for_update($orderId);
            $refundPricing = $this->calculate_order_refund_pricing($header, $selection, $alreadyRefunded);
            if (!($refundPricing['ok'] ?? false)) {
                throw new RuntimeException((string)($refundPricing['message'] ?? 'Nilai refund tidak dapat dihitung.'));
            }
            $refundAmount = round((float)($refundPricing['refund_amount'] ?? 0), 2);

            $refundPayload = [
                'refund_no' => $this->generate_pos_refund_no(),
                'order_id' => $orderId,
                'payment_id' => null,
                'member_id' => !empty($header['member_id']) ? (int)$header['member_id'] : null,
                'payment_method_id' => $refundPaymentMethodId,
                'company_account_id' => $companyAccountId,
                'reference_no' => $this->nullable_text($payload['reference_no'] ?? ''),
                'refund_status' => 'POSTED',
                'processed_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                'return_to_stock' => !empty($selection['return_to_stock']) ? 1 : 0,
                'adjustment_mode' => $this->normalize_pos_adjustment_mode((string)($payload['adjustment_mode'] ?? 'NONE')),
                'refund_amount' => $refundAmount,
                'reason' => $this->nullable_text($payload['reason'] ?? ''),
                'refunded_by' => $actorEmployeeId,
                'refunded_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert('pos_refund', $this->filter_table_payload('pos_refund', $refundPayload));
            $refundId = (int)$this->db->insert_id();
            if ($refundId <= 0) {
                throw new RuntimeException('Gagal membuat dokumen refund POS.');
            }

            $lineNo = 1;
            foreach ((array)($selection['product_lines'] ?? []) as $line) {
                $this->db->insert('pos_refund_line', $this->filter_table_payload('pos_refund_line', [
                    'refund_id' => $refundId,
                    'line_no' => $lineNo++,
                    'line_type' => 'PRODUCT',
                    'order_line_id' => (int)$line['order_line_id'],
                    'order_extra_line_id' => null,
                    'product_id' => !empty($line['product_id']) ? (int)$line['product_id'] : null,
                    'extra_id' => null,
                    'qty_refunded' => round((float)$line['qty_void'], 4),
                    // amount_refunded is the actual cash returned. The gross
                    // line value is retained separately for margin reporting.
                    'amount_refunded' => round((float)($refundPricing['product_line_amounts'][(int)$line['order_line_id']] ?? 0), 2),
                    'gross_amount_refunded' => round((float)$line['subtotal_void'], 2),
                    'cost_reversed' => round((float)$line['qty_void'] * (float)$line['hpp_live_snapshot'], 2),
                    'line_process_state' => (string)$line['line_process_state'],
                    'notes' => $this->nullable_text($line['notes'] ?? ''),
                ]));
            }
            foreach ((array)($selection['extra_lines'] ?? []) as $extra) {
                $this->db->insert('pos_refund_line', $this->filter_table_payload('pos_refund_line', [
                    'refund_id' => $refundId,
                    'line_no' => $lineNo++,
                    'line_type' => 'EXTRA',
                    'order_line_id' => (int)$extra['order_line_id'],
                    'order_extra_line_id' => (int)$extra['order_line_extra_id'],
                    'product_id' => null,
                    'extra_id' => !empty($extra['extra_id']) ? (int)$extra['extra_id'] : null,
                    'qty_refunded' => round((float)$extra['line_qty_affected'], 4),
                    'amount_refunded' => round((float)($refundPricing['extra_line_amounts'][(int)$extra['order_line_extra_id']] ?? 0), 2),
                    'gross_amount_refunded' => round((float)$extra['subtotal_void'], 2),
                    'cost_reversed' => round((float)$extra['line_qty_affected'] * (float)($extra['cost_amount_snapshot'] ?? 0), 2),
                    'line_process_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                    'notes' => null,
                ]));
            }

            $reverse = $this->reverse_order_commit_decisions((array)($selection['decisions'] ?? []), $actorEmployeeId, 'Refund POS #' . $refundId, [
                'adjustment_mode' => (string)($refundPayload['adjustment_mode'] ?? 'NONE'),
                'reason' => (string)($payload['reason'] ?? ''),
                'reason_code' => (string)($payload['reason_code'] ?? ''),
                'document_type' => 'REFUND',
                'document_id' => $refundId,
                'document_no' => (string)($refundPayload['refund_no'] ?? ''),
            ]);
            if (!($reverse['ok'] ?? false)) {
                throw new RuntimeException((string)($reverse['message'] ?? 'Gagal reversal stok untuk refund POS.'));
            }

            $financeResult = $this->post_company_account_mutation([
                'account_id' => $companyAccountId,
                'mutation_type' => 'OUT',
                'amount' => $refundAmount,
                'mutation_date' => date('Y-m-d H:i:s'),
                'ref_module' => 'POS',
                'ref_table' => 'pos_refund',
                'ref_id' => $refundId,
                'ref_no' => (string)($refundPayload['refund_no'] ?? ''),
                'notes' => 'Refund POS via ' . (string)($refundMethod['method_name'] ?? 'metode pembayaran'),
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
            if (!($financeResult['ok'] ?? false)) {
                throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal posting refund POS ke mutasi rekening.'));
            }

            $reversalState = $this->apply_order_line_reversal_updates(
                $orderId,
                (array)($selection['product_lines'] ?? []),
                (array)($selection['extra_lines'] ?? []),
                'REFUND'
            );
            $newOrderStatus = !empty($reversalState['has_active_lines']) ? 'REFUND_PARTIAL' : 'REFUND_FULL';
            $this->db->where('id', $orderId)->update('pos_order', ['status' => $newOrderStatus]);
            $this->insert_order_state_log($orderId, (string)($header['status'] ?? 'PAID'), $newOrderStatus, 'ORDER_REFUND', $actorEmployeeId, 'Refund POS #' . $refundId);

            $runtimeCancelledCount = 0;
            if ($newOrderStatus === 'REFUND_FULL') {
                $runtimeCancel = $this->cancel_active_runtime_jobs_for_terminal_order(
                    $orderId,
                    'Order direfund penuh; job stock commit dibatalkan agar stok tidak diposting ulang.'
                );
                if (!($runtimeCancel['ok'] ?? false)) {
                    throw new RuntimeException((string)($runtimeCancel['message'] ?? 'Gagal membatalkan job stock POS setelah refund penuh.'));
                }
                $runtimeCancelledCount = (int)($runtimeCancel['cancelled_count'] ?? 0);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan refund POS.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $availabilityRebuild = $this->refresh_pos_reversal_availability_after_commit(
            $order,
            $actorEmployeeId,
            'ORDER_REFUND',
            $refundId
        );
        return [
            'ok' => true,
            'id' => $refundId,
            'refund_no' => (string)$refundPayload['refund_no'],
            'order_status' => $newOrderStatus,
            'adjustment_doc_count' => (int)($reverse['adjustment_doc_count'] ?? 0),
            'runtime_job_cancelled_count' => $runtimeCancelledCount,
            'availability_rebuild' => $availabilityRebuild,
            'warning' => $availabilityRebuild['warning'],
        ];
    }

    private function refresh_pos_reversal_availability_after_commit(array $order, int $actorEmployeeId, string $eventSource, int $eventId): array
    {
        $header = (array)($order['header'] ?? []);
        $outletId = max(0, (int)($header['outlet_id'] ?? 0));
        $productIds = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }
        $productIds = array_values($productIds);
        $metadata = [
            'ok' => false,
            'success_count' => 0,
            'failed_count' => 0,
            'marked_product_count' => count($productIds),
            'dirty' => null,
            'warning' => null,
        ];

        if ($outletId <= 0 || empty($productIds)) {
            $metadata['warning'] = 'Reversal POS sudah tersimpan, tetapi refresh availability dilewati karena outlet atau produk order tidak lengkap.';
            return $metadata;
        }

        try {
            $this->load->library('PosAvailabilityRebuildService');
            $context = [
                'trigger_context' => 'POS_ORDER_REVERSAL',
                'event_source' => $eventSource,
                'event_table' => 'pos_order',
                'event_id' => $eventId,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ];
            $metadata['dirty'] = $this->posavailabilityrebuildservice->mark_dirty($outletId, $productIds, $context);
            $rebuild = $this->posavailabilityrebuildservice->rebuild_products($outletId, $productIds, $context);
            $metadata = $rebuild + $metadata;
            if (!($rebuild['ok'] ?? false)) {
                $metadata['warning'] = 'Reversal POS sudah tersimpan, tetapi refresh availability gagal: '
                    . (string)($rebuild['message'] ?? 'hasil rebuild tidak berhasil.');
            }
        } catch (Throwable $e) {
            $metadata['warning'] = 'Reversal POS sudah tersimpan, tetapi refresh availability gagal: ' . $e->getMessage();
        }

        if ($metadata['warning'] !== null) {
            try {
                log_message('error', 'POS reversal availability refresh failed [' . $eventSource . ' #' . $eventId . ']: ' . $metadata['warning']);
            } catch (Throwable $ignored) {
                // Best-effort logging must not alter an already committed reversal.
            }
        }
        return $metadata;
    }

    /**
     * Locks the order header so stock workers, void, and refund writers read
     * one final state at a time.
     */
    private function lock_order_for_update(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $row = $this->db->query(
            'SELECT id, status, stock_commit_status, paid_total, paid_at FROM pos_order WHERE id = ? FOR UPDATE',
            [$orderId]
        )->row_array();

        return $row ?: null;
    }

    private function cancel_active_runtime_jobs_for_terminal_order(int $orderId, string $reason): array
    {
        if ($orderId <= 0 || !$this->db->table_exists('pos_runtime_job')) {
            return ['ok' => true, 'cancelled_count' => 0];
        }

        $this->db
            ->where('job_type', 'ORDER_CONFIRM_STOCK_COMMIT')
            ->where('order_id', $orderId)
            ->where_in('status', ['QUEUED', 'PROCESSING', 'FAILED'])
            ->update('pos_runtime_job', [
                'status' => 'CANCELLED',
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => trim($reason) !== '' ? trim($reason) : 'Job stock commit POS dibatalkan karena order sudah terminal.',
            ]);

        $dbError = $this->db->error();
        if (!empty($dbError['code'])) {
            return ['ok' => false, 'message' => (string)($dbError['message'] ?? 'Gagal membatalkan job stock POS.')];
        }

        return ['ok' => true, 'cancelled_count' => max(0, (int)$this->db->affected_rows())];
    }

    /**
     * Reads posted refund documents under the same transaction lock. The
     * caller can then safely compare a new refund with the remaining payment.
     */
    private function posted_order_refund_total_for_update(int $orderId): float
    {
        if ($orderId <= 0) {
            return 0.0;
        }

        $rows = $this->db->query(
            "SELECT id, refund_amount
             FROM pos_refund
             WHERE order_id = ?
               AND refund_status = 'POSTED'
             FOR UPDATE",
            [$orderId]
        )->result_array();

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)($row['refund_amount'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Converts selected gross line value into cash that may really be returned.
     * Discounts are spread proportionally over the still-active order value,
     * while tax and service remain revenue according to the reporting policy.
     */
    private function calculate_order_refund_pricing(array $header, array $selection, float $alreadyRefunded): array
    {
        $selectedGross = round(max(0, (float)($selection['total_amount'] ?? 0)), 2);
        if ($selectedGross <= 0.009) {
            return ['ok' => false, 'message' => 'Nilai produk atau extra yang dipilih untuk refund harus lebih besar dari nol.'];
        }

        $currentGross = round(max(0, (float)($header['subtotal_amount'] ?? 0)), 2);
        if ($currentGross <= 0.009) {
            return ['ok' => false, 'message' => 'Order tidak lagi memiliki nilai produk atau extra yang dapat direfund. Muat ulang order terlebih dahulu.'];
        }
        if ($selectedGross > $currentGross + 0.009) {
            return ['ok' => false, 'message' => 'Nilai line refund melebihi nilai order terbaru. Muat ulang order lalu pilih kembali line yang tersisa.'];
        }

        $paidTotal = round(max(0, (float)($header['paid_total'] ?? 0)), 2);
        $remainingCash = round(max(0, $paidTotal - max(0, $alreadyRefunded)), 2);
        $retainedTaxAndService = round(
            max(0, (float)($header['tax_amount'] ?? 0))
            + max(0, (float)($header['service_amount'] ?? 0)),
            2
        );

        // Only the product/extra portion is refundable. Tax and service stay
        // as cafe revenue; discounts are represented by the proportional ratio.
        $refundableProductPool = round(min(
            $currentGross,
            max(0, $remainingCash - $retainedTaxAndService)
        ), 2);
        if ($refundableProductPool <= 0.009) {
            return ['ok' => false, 'message' => 'Tidak ada nilai produk atau extra yang masih dapat dikembalikan. Sisa pembayaran sudah direfund atau hanya tersisa pajak/service.'];
        }

        $ratio = min(1, max(0, $selectedGross / $currentGross));
        $refundAmount = round($refundableProductPool * $ratio, 2);
        if (abs($selectedGross - $currentGross) <= 0.009) {
            $refundAmount = $refundableProductPool;
        }
        if ($refundAmount <= 0.009 || $refundAmount > $remainingCash + 0.009) {
            return ['ok' => false, 'message' => 'Refund ditolak karena nilai pengembalian melebihi sisa uang yang boleh dikembalikan (' . $this->format_pos_currency($remainingCash) . ').'];
        }

        $allocationRows = [];
        foreach ((array)($selection['product_lines'] ?? []) as $line) {
            $orderLineId = (int)($line['order_line_id'] ?? 0);
            $grossAmount = round(max(0, (float)($line['subtotal_void'] ?? 0)), 2);
            if ($orderLineId > 0 && $grossAmount > 0) {
                $allocationRows[] = ['type' => 'PRODUCT', 'id' => $orderLineId, 'gross_amount' => $grossAmount];
            }
        }
        foreach ((array)($selection['extra_lines'] ?? []) as $line) {
            $orderExtraLineId = (int)($line['order_line_extra_id'] ?? 0);
            $grossAmount = round(max(0, (float)($line['subtotal_void'] ?? 0)), 2);
            if ($orderExtraLineId > 0 && $grossAmount > 0) {
                $allocationRows[] = ['type' => 'EXTRA', 'id' => $orderExtraLineId, 'gross_amount' => $grossAmount];
            }
        }
        if (empty($allocationRows)) {
            return ['ok' => false, 'message' => 'Line refund tidak memiliki nilai yang dapat dialokasikan.'];
        }

        $productLineAmounts = [];
        $extraLineAmounts = [];
        $allocated = 0.0;
        $lastIndex = count($allocationRows) - 1;
        foreach ($allocationRows as $index => $row) {
            $amount = $index === $lastIndex
                ? round(max(0, $refundAmount - $allocated), 2)
                : round($refundAmount * ((float)$row['gross_amount'] / $selectedGross), 2);
            $allocated = round($allocated + $amount, 2);
            if ($row['type'] === 'PRODUCT') {
                $productLineAmounts[(int)$row['id']] = $amount;
            } else {
                $extraLineAmounts[(int)$row['id']] = $amount;
            }
        }

        return [
            'ok' => true,
            'refund_amount' => $refundAmount,
            'gross_amount' => $selectedGross,
            'product_line_amounts' => $productLineAmounts,
            'extra_line_amounts' => $extraLineAmounts,
        ];
    }

    private function format_pos_currency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 2, ',', '.');
    }

    private function local_record_exists(string $table, int $id): bool
    {
        if ($id <= 0 || !$this->db->table_exists($table)) {
            return false;
        }
        return (int)$this->db->from($table)->where('id', $id)->count_all_results() > 0;
    }

    private function local_code_exists(string $table, string $column, string $code, int $excludeId = 0): bool
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists($column, $table)) {
            return false;
        }
        return $this->code_exists($this->db, $table, $column, $code, $excludeId);
    }

    private function generate_local_named_code(string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        return $this->generate_named_code($this->db, $table, $column, $name, $prefix, $excludeId, $maxLen);
    }

    public function normalize_reservation_lines(array $lines, int $outletId = 0): array
    {
        $result = $this->normalize_order_draft_lines($lines, $outletId);
        if (!($result['ok'] ?? false)) {
            $message = (string)($result['message'] ?? 'Produk reservasi belum valid.');
            $result['message'] = str_replace(['draft order POS', 'Draft order'], ['reservasi', 'Reservasi'], $message);
        }

        return $result;
    }

    private function normalize_order_draft_lines(array $lines, int $outletId = 0): array
    {
        $normalized = [];
        $productIds = [];
        $extraIds = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($productId > 0 && $qty > 0) {
                $productIds[$productId] = $productId;
                foreach ((array)($line['extras'] ?? []) as $extraRow) {
                    $extraId = (int)($extraRow['extra_id'] ?? 0);
                    if ($extraId > 0) {
                        $extraIds[$extraId] = $extraId;
                    }
                }
            }
        }

        if (empty($productIds)) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 produk dengan qty valid di draft order POS.'];
        }

        $productRows = $this->load_order_products(array_values($productIds));
        if (empty($productRows)) {
            return ['ok' => false, 'message' => 'Produk draft POS tidak ditemukan.'];
        }

        $productMap = [];
        foreach ($productRows as $row) {
            $productMap[(int)$row['id']] = $row;
        }
        // HPP is server-owned. Cashier input can display the value, but the
        // saved order snapshot must come from the outlet stock-live cache.
        $availabilityMap = $this->load_product_availability_map(array_values($productIds), $outletId);
        $extraOptionMap = $this->load_product_extra_group_map(array_values($productIds));
        $extraMasterMap = $this->load_extra_master_map(array_values($extraIds));

        $normalized = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if (empty($productMap[$productId])) {
                return ['ok' => false, 'message' => 'Baris #' . ($index + 1) . ': produk tidak ditemukan atau tidak aktif di POS.'];
            }

            $product = $productMap[$productId];
            $availability = $availabilityMap[$productId] ?? [];
            $unitPrice = round((float)($line['unit_price'] ?? $product['selling_price'] ?? 0), 2);
            $hppStandard = round((float)($product['hpp_standard'] ?? 0), 6);
            $hppLive = round((float)($availability['hpp_live_snapshot'] ?? 0), 6);
            if ($hppLive <= 0) {
                $hppLive = round((float)($product['hpp_live_cache'] ?? 0), 6);
            }
            if ($hppLive <= 0) {
                $hppLive = $hppStandard;
            }
            $bundleId = !empty($line['bundle_id']) ? (int)$line['bundle_id'] : null;
            $normalizedExtras = [];
            $allowedExtraIds = [];
            foreach ((array)($extraOptionMap[$productId] ?? []) as $group) {
                foreach ((array)($group['items'] ?? []) as $item) {
                    $allowedExtraIds[(int)$item['extra_id']] = true;
                }
            }
            foreach ((array)($line['extras'] ?? []) as $extraIndex => $extraLine) {
                if (!is_array($extraLine)) {
                    continue;
                }
                $extraId = (int)($extraLine['extra_id'] ?? 0);
                $extraQty = round((float)($extraLine['qty'] ?? 0), 4);
                if ($extraId <= 0 || $extraQty <= 0) {
                    continue;
                }
                if (!isset($allowedExtraIds[$extraId]) || empty($extraMasterMap[$extraId])) {
                    return ['ok' => false, 'message' => 'Baris #' . ($index + 1) . ' memiliki extra yang tidak valid untuk produk ini.'];
                }
                $extraMaster = $extraMasterMap[$extraId];
                $extraUnitPrice = round((float)($extraLine['unit_price'] ?? $extraMaster['selling_price'] ?? 0), 2);
                $extraCost = round((float)($extraMaster['cost_amount'] ?? 0), 6);
                $normalizedExtras[] = [
                    'extra_id' => $extraId,
                    'extra_code' => (string)($extraMaster['extra_code'] ?? ''),
                    'extra_name' => (string)($extraMaster['extra_name'] ?? ''),
                    'extra_type' => (string)($extraMaster['extra_type'] ?? ''),
                    'qty' => $extraQty,
                    'unit_price' => $extraUnitPrice,
                    'net_amount' => round($extraQty * $extraUnitPrice, 2),
                    'cost_amount_snapshot' => $extraCost,
                    'source_kind' => (string)($extraMaster['source_kind'] ?? 'NONE'),
                    'source_product_id' => !empty($extraMaster['source_product_id']) ? (int)$extraMaster['source_product_id'] : null,
                    'source_component_id' => !empty($extraMaster['source_component_id']) ? (int)$extraMaster['source_component_id'] : null,
                    'source_material_id' => !empty($extraMaster['source_material_id']) ? (int)$extraMaster['source_material_id'] : null,
                    'source_qty' => round((float)($extraMaster['source_qty'] ?? 0), 4),
                    'replacement_kind' => (string)($extraMaster['replacement_kind'] ?? 'NONE'),
                    'replacement_product_id' => !empty($extraMaster['replacement_product_id']) ? (int)$extraMaster['replacement_product_id'] : null,
                    'replacement_component_id' => !empty($extraMaster['replacement_component_id']) ? (int)$extraMaster['replacement_component_id'] : null,
                    'replacement_material_id' => !empty($extraMaster['replacement_material_id']) ? (int)$extraMaster['replacement_material_id'] : null,
                    'replacement_qty' => round((float)($extraMaster['replacement_qty'] ?? 0), 4),
                    'notes' => $this->nullable_text($extraLine['notes'] ?? ''),
                ];
            }
            $extraTotal = 0.0;
            foreach ($normalizedExtras as $extraRow) {
                $extraTotal += (float)($extraRow['net_amount'] ?? 0);
            }
            $extraTotal = round($extraTotal, 2);

            $normalized[] = [
                'order_line_id' => !empty($line['id']) ? (int)$line['id'] : 0,
                'product_id' => $productId,
                'bundle_id' => $bundleId,
                'product_code' => (string)($product['product_code'] ?? ''),
                'product_name' => (string)($product['product_name'] ?? ''),
                'product_division_id_snapshot' => !empty($product['product_division_id']) ? (int)$product['product_division_id'] : null,
                'operational_division_id' => $this->resolve_product_default_operational_division($product),
                'uom_id' => !empty($product['uom_id']) ? (int)$product['uom_id'] : null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'net_amount' => round($qty * $unitPrice, 2),
                'extra_total' => $extraTotal,
                'hpp_standard_snapshot' => $hppStandard,
                'hpp_live_snapshot' => $hppLive,
                'cogs_amount' => round($qty * $hppLive, 2),
                'availability_mode_snapshot' => 'AUTO',
                'extras' => $normalizedExtras,
                'notes' => $this->nullable_text($line['notes'] ?? ''),
            ];
        }

        if (empty($normalized)) {
            return ['ok' => false, 'message' => 'Draft order belum punya line produk yang valid.'];
        }

        return ['ok' => true, 'rows' => $normalized];
    }

    private function prepare_confirmed_order_append(array $existingOrder, array $incomingRows): array
    {
        $existingLines = [];
        foreach ((array)($existingOrder['lines'] ?? []) as $line) {
            $lineId = (int)($line['id'] ?? 0);
            if ($lineId > 0) {
                $existingLines[$lineId] = $line;
            }
        }

        if (empty($existingLines)) {
            return ['ok' => false, 'message' => 'Order confirmed belum memiliki line tersimpan untuk diappend.'];
        }

        $seenExisting = [];
        $newRows = [];
        foreach ($incomingRows as $row) {
            $orderLineId = (int)($row['order_line_id'] ?? 0);
            if ($orderLineId > 0) {
                if (empty($existingLines[$orderLineId])) {
                    return ['ok' => false, 'message' => 'Line transaksi lama tidak cocok dengan data order POS tersimpan. Muat ulang order lalu coba lagi.'];
                }
                if (!$this->confirmed_order_existing_line_unchanged((array)$existingLines[$orderLineId], $row)) {
                    return ['ok' => false, 'message' => 'Line transaksi lama tidak boleh diubah dari kasir. Pengurangan atau hapus item harus melalui void POS.'];
                }
                $seenExisting[$orderLineId] = true;
                continue;
            }
            $newRows[] = $row;
        }

        foreach (array_keys($existingLines) as $orderLineId) {
            if (!isset($seenExisting[$orderLineId])) {
                return ['ok' => false, 'message' => 'Line transaksi lama tidak boleh dihapus dari kasir. Gunakan void POS untuk pengurangan atau pembatalan item.'];
            }
        }

        return [
            'ok' => true,
            'new_rows' => array_values($newRows),
        ];
    }

    private function confirmed_order_existing_line_unchanged(array $existingLine, array $incomingLine): bool
    {
        if ((int)($existingLine['product_id'] ?? 0) !== (int)($incomingLine['product_id'] ?? 0)) {
            return false;
        }
        if ((int)($existingLine['bundle_id'] ?? 0) !== (int)($incomingLine['bundle_id'] ?? 0)) {
            return false;
        }
        if (round((float)($existingLine['qty'] ?? 0), 4) !== round((float)($incomingLine['qty'] ?? 0), 4)) {
            return false;
        }
        if (round((float)($existingLine['unit_price'] ?? 0), 2) !== round((float)($incomingLine['unit_price'] ?? 0), 2)) {
            return false;
        }
        if (trim((string)($existingLine['notes'] ?? '')) !== trim((string)($incomingLine['notes'] ?? ''))) {
            return false;
        }

        $existingExtras = array_values((array)($existingLine['extras'] ?? []));
        $incomingExtras = array_values((array)($incomingLine['extras'] ?? []));
        if (count($existingExtras) !== count($incomingExtras)) {
            return false;
        }

        foreach ($existingExtras as $index => $existingExtra) {
            $incomingExtra = (array)($incomingExtras[$index] ?? []);
            if ((int)($existingExtra['extra_id'] ?? 0) !== (int)($incomingExtra['extra_id'] ?? 0)) {
                return false;
            }
            if (round((float)($existingExtra['qty'] ?? 0), 4) !== round((float)($incomingExtra['qty'] ?? 0), 4)) {
                return false;
            }
            if (round((float)($existingExtra['unit_price'] ?? 0), 2) !== round((float)($incomingExtra['unit_price'] ?? 0), 2)) {
                return false;
            }
            if (trim((string)($existingExtra['notes'] ?? '')) !== trim((string)($incomingExtra['notes'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private function load_order_products(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return [];
        }

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.product_division_id',
            'p.uom_id',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
        ];
        if ($this->db->field_exists('default_operational_division_id', 'mst_product')) {
            $select[] = 'p.default_operational_division_id';
        }
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $select[] = 'p.show_pos';
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $select[] = 'p.show_in_cashier';
        }
        $select[] = 'pd.name AS product_division_name';
        $select[] = 'pd.code AS product_division_code';

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where_in('p.id', $productIds)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }

        return $db->select(implode(",\n", $select))->get()->result_array();
    }

    private function load_product_availability_map(array $productIds, int $outletId = 0): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($outletId <= 0 || empty($productIds) || !$this->db->table_exists('pos_product_availability_cache')) {
            return [];
        }

        $rows = $this->db
            ->select('product_id, availability_status, estimated_available_qty, bottleneck_name_snapshot, hpp_live_snapshot')
            ->from('pos_product_availability_cache')
            ->where('outlet_id', $outletId)
            ->where_in('product_id', $productIds)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['product_id']] = $row;
        }
        return $map;
    }

    private function load_extra_master_map(array $extraIds): array
    {
        $extraIds = array_values(array_filter(array_map('intval', $extraIds)));
        if (empty($extraIds)) {
            return [];
        }

        $rows = $this->db
            ->select('id, extra_code, extra_name, extra_type, selling_price, cost_amount, source_kind, source_product_id, source_component_id, source_material_id, source_qty, replacement_kind, replacement_product_id, replacement_component_id, replacement_material_id, replacement_qty')
            ->from('mst_extra')
            ->where_in('id', $extraIds)
            ->where('is_active', 1)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    private function load_product_extra_group_map(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($productIds) || !$this->db->table_exists('mst_product_extra_map')) {
            return [];
        }

        $rows = $this->db
            ->select('
                m.product_id,
                g.id AS extra_group_id,
                g.group_code,
                g.group_name,
                g.is_required,
                g.min_select,
                g.max_select,
                gi.sort_order AS item_sort_order,
                e.id AS extra_id,
                e.extra_code,
                e.extra_name,
                e.extra_type,
                e.selling_price,
                e.cost_amount
            ')
            ->from('mst_product_extra_map m')
            ->join('mst_extra_group g', 'g.id = m.extra_group_id AND g.is_active = 1', 'inner')
            ->join('mst_extra_group_item gi', 'gi.extra_group_id = g.id', 'inner')
            ->join('mst_extra e', 'e.id = gi.extra_id AND e.is_active = 1', 'inner')
            ->where_in('m.product_id', $productIds)
            ->order_by('m.sort_order', 'ASC')
            ->order_by('g.sort_order', 'ASC')
            ->order_by('gi.sort_order', 'ASC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            $groupId = (int)$row['extra_group_id'];
            if (!isset($map[$productId])) {
                $map[$productId] = [];
            }
            if (!isset($map[$productId][$groupId])) {
                $map[$productId][$groupId] = [
                    'extra_group_id' => $groupId,
                    'group_code' => (string)($row['group_code'] ?? ''),
                    'group_name' => (string)($row['group_name'] ?? ''),
                    'is_required' => (int)($row['is_required'] ?? 0),
                    'min_select' => (int)($row['min_select'] ?? 0),
                    'max_select' => (int)($row['max_select'] ?? 1),
                    'items' => [],
                ];
            }
            $map[$productId][$groupId]['items'][] = [
                'extra_id' => (int)$row['extra_id'],
                'extra_code' => (string)($row['extra_code'] ?? ''),
                'extra_name' => (string)($row['extra_name'] ?? ''),
                'extra_type' => (string)($row['extra_type'] ?? ''),
                'selling_price' => (float)($row['selling_price'] ?? 0),
                'cost_amount' => (float)($row['cost_amount'] ?? 0),
            ];
        }

        $normalized = [];
        foreach ($map as $productId => $groups) {
            $normalized[$productId] = array_values($groups);
        }
        return $normalized;
    }

    private function calculate_order_totals(array $rows): array
    {
        $subtotal = 0.0;
        foreach ($rows as $row) {
            $subtotal += (float)($row['net_amount'] ?? 0);
            $subtotal += (float)($row['extra_total'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        return [
            'subtotal_amount' => $subtotal,
            'grand_total' => $subtotal,
        ];
    }

    private function build_order_kot_print_payload(array $order): array
    {
        $header = (array)($order['header'] ?? []);
        $customerName = $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? '');
        $lines = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $lines[] = [
                'product_name' => (string)($line['product_name'] ?? ''),
                'qty' => (float)($line['qty'] ?? 0),
                'uom_code' => (string)($line['uom_code'] ?? ''),
                'bundle_name' => (string)($line['bundle_name'] ?? ''),
                'notes' => (string)($line['notes'] ?? ''),
            ];
        }

        return [
            'document_type' => 'KOT',
            'event_code' => 'ORDER_CONFIRM_KOT',
            'order_id' => (int)($header['id'] ?? 0),
            'order_no' => (string)($header['order_no'] ?? ''),
            'service_type' => (string)($header['service_type'] ?? ''),
            'guest_count' => (int)($header['guest_count'] ?? 1),
            'ordered_at' => (string)($header['ordered_at'] ?? ''),
            'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
            'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
            'member_name' => $customerName,
            'customer_name' => $customerName,
            'lines' => $lines,
        ];
    }

    private function build_direct_print_order_line(array $line): array
    {
        $extras = [];
        foreach ((array)($line['extras'] ?? []) as $extra) {
            $extraName = trim((string)($extra['extra_name'] ?? ''));
            if ($extraName === '') {
                continue;
            }
            $extraQty = max(0, (float)($extra['qty'] ?? 0));
            $extras[] = [
                'name' => $extraName,
                'qty' => $extraQty,
            ];
        }

        return [
            'order_line_id' => (int)($line['id'] ?? 0),
            'operational_division_id' => (int)($line['operational_division_id'] ?? 0),
            'product_division_id' => (int)($line['product_division_id_snapshot'] ?? 0),
            'bundle_id' => (int)($line['bundle_id'] ?? 0),
            'bundle_code' => (string)($line['bundle_code'] ?? ''),
            'bundle_name' => (string)($line['bundle_name'] ?? ''),
            'line_type' => (string)($line['line_type'] ?? 'PRODUCT'),
            'product_name' => (string)($line['product_name'] ?? ''),
            'qty' => (float)($line['qty'] ?? 0),
            'amount' => (float)($line['net_amount'] ?? 0),
            'notes' => trim((string)($line['notes'] ?? '')),
            'extras' => $extras,
        ];
    }

    /**
     * Bundle is persisted through bundle_id on every component line. Older
     * transactions can also contain a BUNDLE_HEADER line, therefore it is
     * ignored as an item but still contributes its bundle label.
     */
    private function direct_print_bundle_groups(array $lines): array
    {
        $groups = [];
        $bundleIndexes = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $bundleId = max(0, (int)($line['bundle_id'] ?? 0));
            $lineType = strtoupper(trim((string)($line['line_type'] ?? 'PRODUCT')));
            if ($bundleId <= 0) {
                if ($lineType !== 'BUNDLE_HEADER') {
                    $groups[] = ['type' => 'LINE', 'line' => $line];
                }
                continue;
            }

            $key = 'BUNDLE:' . $bundleId;
            if (!isset($bundleIndexes[$key])) {
                $bundleIndexes[$key] = count($groups);
                $groups[] = [
                    'type' => 'BUNDLE',
                    'bundle_id' => $bundleId,
                    'bundle_name' => trim((string)($line['bundle_name'] ?? '')),
                    'bundle_code' => trim((string)($line['bundle_code'] ?? '')),
                    'lines' => [],
                ];
            }

            $groupIndex = $bundleIndexes[$key];
            if ($groups[$groupIndex]['bundle_name'] === '') {
                $groups[$groupIndex]['bundle_name'] = trim((string)($line['bundle_name'] ?? ''));
            }
            if ($groups[$groupIndex]['bundle_code'] === '') {
                $groups[$groupIndex]['bundle_code'] = trim((string)($line['bundle_code'] ?? ''));
            }
            if ($lineType !== 'BUNDLE_HEADER') {
                $groups[$groupIndex]['lines'][] = $line;
            }
        }

        return array_values(array_filter($groups, static function (array $group): bool {
            return $group['type'] !== 'BUNDLE' || !empty($group['lines']);
        }));
    }

    private function direct_print_line_name(array $line): string
    {
        return trim((string)($line['product_name'] ?? $line['item_name'] ?? '-')) ?: '-';
    }

    private function direct_print_qty_label($qty): string
    {
        return rtrim(rtrim(number_format((float)$qty, 2, '.', ''), '0'), '.');
    }

    /**
     * Bundle labels are stored in older order-line notes as an internal marker.
     * The bundle-aware renderer already prints that label once as a group title,
     * so keep any real customer note but drop the duplicate marker per item.
     */
    private function direct_print_without_bundle_marker(string $notes): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return '';
        }

        $keptLines = [];
        foreach (preg_split('/\r\n|\r|\n/', $notes) ?: [] as $noteLine) {
            $noteLine = trim((string)$noteLine);
            if ($noteLine === '' || preg_match('/^\[BUNDLE\](?:\s|$)/i', $noteLine)) {
                continue;
            }
            $keptLines[] = $noteLine;
        }

        return trim(implode("\n", $keptLines));
    }

    private function append_direct_print_item_block(
        array &$chunks,
        array $line,
        int $width,
        bool $showQty,
        bool $showExtra,
        bool $showNotes,
        bool $showPrice,
        int $labelWidth,
        int $amountWidth,
        string $indent = ''
    ): void {
        $qtyLabel = $this->direct_print_qty_label($line['qty'] ?? 0);
        $prefix = $showQty ? ($qtyLabel . ' x ') : '';
        $itemLine = $indent . $prefix . $this->direct_print_line_name($line);
        if ($showPrice) {
            $wrapped = $this->wrap_print_text($itemLine, $labelWidth);
            $lastIndex = max(0, count($wrapped) - 1);
            foreach ($wrapped as $index => $wrappedLine) {
                if ($index === $lastIndex) {
                    $chunks[] = $this->pad_right_print($wrappedLine, $labelWidth)
                        . $this->pad_left_print($this->format_number_print($line['amount'] ?? $line['net_amount'] ?? 0), $amountWidth);
                } else {
                    $chunks[] = $wrappedLine;
                }
            }
        } else {
            foreach ($this->wrap_print_text($itemLine, $width) as $wrappedLine) {
                $chunks[] = $wrappedLine;
            }
        }

        if ($showExtra) {
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $extraName = trim((string)($extra['name'] ?? $extra['extra_name'] ?? ''));
                if ($extraName === '') {
                    continue;
                }
                $extraQty = $this->direct_print_qty_label($extra['qty'] ?? 0);
                $extraSuffix = $showQty && $extraQty !== '' ? ' x' . $extraQty : '';
                $extraLine = $indent . '  + ' . $extraName . $extraSuffix;
                if ($showPrice) {
                    $wrapped = $this->wrap_print_text($extraLine, $labelWidth);
                    $lastIndex = max(0, count($wrapped) - 1);
                    foreach ($wrapped as $index => $wrappedLine) {
                        if ($index === $lastIndex) {
                            $chunks[] = $this->pad_right_print($wrappedLine, $labelWidth)
                                . $this->pad_left_print($this->format_number_print($extra['amount'] ?? $extra['net_amount'] ?? 0), $amountWidth);
                        } else {
                            $chunks[] = $wrappedLine;
                        }
                    }
                } else {
                    foreach ($this->wrap_print_text($extraLine, $width) as $wrappedLine) {
                        $chunks[] = $wrappedLine;
                    }
                }
            }
        }

        $notes = trim((string)($line['notes'] ?? ''));
        if ($showNotes && $notes !== '') {
            foreach ($this->wrap_print_text($indent . '  NOTE: ' . $notes, $width) as $noteLine) {
                $chunks[] = $noteLine;
            }
        }
    }

    private function append_direct_print_bundle_aware_lines(
        array &$chunks,
        array $lines,
        int $width,
        bool $showProductName,
        bool $showQty,
        bool $showExtra,
        bool $showNotes,
        bool $showPrice = false,
        int $labelWidth = 0,
        int $amountWidth = 0,
        string $entryDivider = ''
    ): void {
        if (!$showProductName) {
            return;
        }

        $labelWidth = $labelWidth > 0 ? $labelWidth : $width;
        $amountWidth = $amountWidth > 0 ? $amountWidth : 0;
        foreach ($this->direct_print_bundle_groups($lines) as $group) {
            if ($group['type'] === 'BUNDLE') {
                $bundleName = trim((string)($group['bundle_name'] ?? ''));
                if ($bundleName === '') {
                    $bundleName = trim((string)($group['bundle_code'] ?? 'PAKET'));
                }
                foreach ($this->wrap_print_text(strtoupper(rtrim($bundleName, ':')) . ':', $width) as $bundleLine) {
                    $chunks[] = $bundleLine;
                }
                foreach ((array)$group['lines'] as $line) {
                    // Existing bundle component rows can carry an internal
                    // [BUNDLE] marker in notes. The group heading above is
                    // the single source of that information on paper.
                    $line['notes'] = $this->direct_print_without_bundle_marker((string)($line['notes'] ?? ''));
                    $this->append_direct_print_item_block($chunks, $line, $width, $showQty, $showExtra, $showNotes, $showPrice, $labelWidth, $amountWidth, '  ');
                }
            } else {
                $this->append_direct_print_item_block($chunks, (array)$group['line'], $width, $showQty, $showExtra, $showNotes, $showPrice, $labelWidth, $amountWidth);
            }
            if ($entryDivider !== '') {
                $chunks[] = $entryDivider;
            }
        }
    }

    private function find_payment_print_document(int $paymentId): ?array
    {
        $header = $this->db->select('p.*, o.order_no, o.table_no, o.service_type, o.guest_count, o.customer_name, o.outlet_id, o.terminal_id, o.subtotal_amount, o.grand_total AS order_grand_total, o.paid_total AS order_paid_total, o.change_total AS order_change_total, m.member_name, COALESCE(au.username, e.employee_name) AS actor_name')
            ->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = p.cashier_employee_id', 'left')
            ->where('p.id', $paymentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $order = $this->find_order_draft((int)($header['order_id'] ?? 0));
        if (!$order) {
            return null;
        }

        $lines = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $extras = [];
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $extras[] = [
                    'extra_name' => (string)($extra['extra_name'] ?? '-'),
                    'qty' => (float)($extra['qty'] ?? 0),
                    'net_amount' => (float)($extra['net_amount'] ?? 0),
                    'notes' => (string)($extra['notes'] ?? ''),
                ];
            }
            $lines[] = [
                'item_name' => (string)($line['product_name'] ?? '-'),
                'product_name' => (string)($line['product_name'] ?? '-'),
                'bundle_id' => (int)($line['bundle_id'] ?? 0),
                'bundle_code' => (string)($line['bundle_code'] ?? ''),
                'bundle_name' => (string)($line['bundle_name'] ?? ''),
                'line_type' => (string)($line['line_type'] ?? 'PRODUCT'),
                'qty' => (float)($line['qty'] ?? 0),
                'amount' => (float)($line['net_amount'] ?? 0),
                'notes' => (string)($line['notes'] ?? ''),
                'is_extra' => 0,
                'extras' => $extras,
            ];
        }

        $paymentLines = $this->db->select('pl.amount, pl.reference_no, pm.method_name, pm.method_type')
            ->from('pos_payment_line pl')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('pl.payment_id', $paymentId)
            ->order_by('pl.line_no', 'ASC')
            ->order_by('pl.id', 'ASC')
            ->get()
            ->result_array();

        $issuedVouchers = [];
        if ($this->db->table_exists('pos_voucher_issue')) {
            $issuedVouchers = $this->db
                ->select('vi.voucher_code, vi.voucher_issue_no, vi.voucher_status, vi.amount_snapshot, vi.percent_snapshot, vi.issued_at, vi.expired_at, vi.notes, vc.campaign_name, vc.voucher_type, vc.discount_value, vc.max_discount_amount')
                ->from('pos_voucher_issue vi')
                ->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left')
                ->where('vi.source_payment_id', $paymentId)
                ->order_by('vi.issued_at', 'ASC')
                ->order_by('vi.id', 'ASC')
                ->get()
                ->result_array();
        }

        $cashPaidNow = 0.0;
        foreach ($paymentLines as $paymentLine) {
            $cashPaidNow += (float)($paymentLine['amount'] ?? 0);
        }
        $cashPaidNow = round($cashPaidNow, 2);
        $depositAppliedNow = round((float)($header['deposit_applied_amount'] ?? 0), 2);
        $changeAmount = round((float)($header['change_amount'] ?? 0), 2);
        $enteredNow = round($cashPaidNow + max(0, $changeAmount), 2);
        $orderGrandTotal = round((float)($header['order_grand_total'] ?? $header['net_amount'] ?? 0), 2);
        $orderPaidTotal = round((float)($header['order_paid_total'] ?? ($cashPaidNow + $depositAppliedNow)), 2);
        $previousPaid = round(max(0, $orderPaidTotal - $cashPaidNow - $depositAppliedNow), 2);
        $remainingDue = round(max(0, $orderGrandTotal - $orderPaidTotal), 2);

        $header['customer_name'] = $order['header']['customer_name'] ?? $header['customer_name'] ?? '';
        $header['member_name'] = $order['header']['member_name'] ?? $header['member_name'] ?? '';
        $header['ordered_at'] = $order['header']['ordered_at'] ?? $order['header']['created_at'] ?? $header['ordered_at'] ?? '';
        $header['cashier_username'] = $order['header']['cashier_username'] ?? '';
        $header['cashier_employee_name'] = $order['header']['cashier_employee_name'] ?? '';
        $memberId = max(0, (int)($header['member_id'] ?? $order['header']['member_id'] ?? 0));
        $header['member_id'] = $memberId;
        if ($memberId > 0 && $this->db->table_exists('pos_point_ledger')) {
            $header['member_point_balance'] = $this->current_member_point_balance($memberId);
        }
        if ($memberId > 0 && $this->db->table_exists('pos_stamp_ledger')) {
            $header['member_stamp_balance'] = $this->current_member_stamp_balance($memberId);
        }
        $header['paid_now'] = $cashPaidNow;
        $header['deposit_applied_now'] = $depositAppliedNow;
        $header['settled_now'] = round($cashPaidNow + $depositAppliedNow, 2);
        $header['entered_now'] = $enteredNow;
        $header['previous_paid'] = $previousPaid;
        $header['remaining_due'] = $remainingDue;

        return [
            'header' => $header,
            'lines' => $lines,
            'payment_lines' => $paymentLines,
            'issued_vouchers' => $issuedVouchers,
        ];
    }

    private function find_void_print_document(int $voidId): ?array
    {
        $header = $this->db->select('v.*, o.outlet_id, o.terminal_id, o.order_no, o.table_no, o.service_type, o.guest_count, o.customer_name, m.member_name, COALESCE(au.username, e.employee_name) AS actor_name')
            ->from('pos_void v')
            ->join('pos_order o', 'o.id = v.order_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = v.actor_employee_id', 'left')
            ->where('v.id', $voidId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $lines = $this->db->select('vl.order_line_id, vl.item_name_snapshot AS item_name, vl.qty_void AS qty, vl.subtotal_void AS amount, vl.notes, 0 AS is_extra, ol.bundle_id, ol.line_type, ol.operational_division_id, ol.product_division_id_snapshot, b.bundle_code, b.bundle_name, pd.name AS product_division_name')
            ->from('pos_void_line vl')
            ->join('pos_order_line ol', 'ol.id = vl.order_line_id', 'left')
            ->join('pos_product_bundle b', 'b.id = ol.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = ol.product_division_id_snapshot', 'left')
            ->where('vl.void_id', $voidId)
            ->order_by('line_no_snapshot', 'ASC')
            ->order_by('vl.id', 'ASC')
            ->get()
            ->result_array();
        $extras = $this->db->select("vx.order_line_id, vx.extra_name_snapshot AS extra_name, vx.line_qty_affected AS qty, vx.subtotal_void AS amount, '' AS notes, ol.bundle_id, ol.line_type, ol.operational_division_id, ol.product_division_id_snapshot, b.bundle_code, b.bundle_name, pd.name AS product_division_name", false)
            ->from('pos_void_line_extra vx')
            ->join('pos_order_line ol', 'ol.id = vx.order_line_id', 'left')
            ->join('pos_product_bundle b', 'b.id = ol.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = ol.product_division_id_snapshot', 'left')
            ->where('vx.void_id', $voidId)
            ->order_by('vx.id', 'ASC')
            ->get()
            ->result_array();

        $extrasByOrderLine = [];
        foreach ($extras as $extra) {
            $orderLineId = (int)($extra['order_line_id'] ?? 0);
            if ($orderLineId <= 0) {
                continue;
            }
            $extrasByOrderLine[$orderLineId][] = [
                'extra_name' => (string)($extra['extra_name'] ?? '-'),
                'qty' => (float)($extra['qty'] ?? 0),
                'amount' => (float)($extra['amount'] ?? 0),
                'notes' => (string)($extra['notes'] ?? ''),
            ];
        }
        foreach ($lines as &$line) {
            $line['extras'] = $extrasByOrderLine[(int)($line['order_line_id'] ?? 0)] ?? [];
        }
        unset($line);

        return [
            'header' => $header,
            'lines' => $lines,
        ];
    }

    private function find_refund_print_document(int $refundId): ?array
    {
        $header = $this->db->select('r.*, o.outlet_id, o.terminal_id, o.order_no, o.table_no, o.service_type, o.guest_count, o.customer_name, m.member_name, pm.method_name, COALESCE(au.username, e.employee_name) AS actor_name')
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left')
            ->join('auth_user au', 'au.employee_id = r.refunded_by', 'left')
            ->where('r.id', $refundId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $rawLines = $this->db->select("rl.order_line_id, COALESCE(p.product_name, ex.extra_name, CASE WHEN rl.line_type = 'EXTRA' THEN 'Extra Refund' ELSE 'Item Refund' END) AS item_name, rl.qty_refunded AS qty, rl.amount_refunded AS amount, rl.notes, CASE WHEN rl.line_type = 'EXTRA' THEN 1 ELSE 0 END AS is_extra, ol.bundle_id, ol.line_type AS order_line_type, ol.operational_division_id, ol.product_division_id_snapshot, b.bundle_code, b.bundle_name, pd.name AS product_division_name", false)
            ->from('pos_refund_line rl')
            ->join('pos_order_line ol', 'ol.id = rl.order_line_id', 'left')
            ->join('pos_product_bundle b', 'b.id = ol.bundle_id', 'left')
            ->join('mst_product p', 'p.id = rl.product_id', 'left')
            ->join('mst_extra ex', 'ex.id = rl.extra_id', 'left')
            ->join('mst_product_division pd', 'pd.id = ol.product_division_id_snapshot', 'left')
            ->where('rl.refund_id', $refundId)
            ->order_by('rl.line_no', 'ASC')
            ->order_by('rl.id', 'ASC')
            ->get()
            ->result_array();

        $lines = [];
        $lineIndexes = [];
        foreach ($rawLines as $line) {
            $orderLineId = (int)($line['order_line_id'] ?? 0);
            if (!empty($line['is_extra'])) {
                if (isset($lineIndexes[$orderLineId])) {
                    $lines[$lineIndexes[$orderLineId]]['extras'][] = [
                        'extra_name' => (string)($line['item_name'] ?? '-'),
                        'qty' => (float)($line['qty'] ?? 0),
                        'amount' => (float)($line['amount'] ?? 0),
                        'notes' => (string)($line['notes'] ?? ''),
                    ];
                } else {
                    // An extra can be refunded on its own. Keep it as a
                    // printable line so the matching production division is
                    // still told to stop work even without its parent item.
                    $line['line_type'] = 'EXTRA';
                    $line['extras'] = [];
                    $lineIndexes[$orderLineId] = count($lines);
                    $lines[] = $line;
                }
                continue;
            }
            $line['line_type'] = (string)($line['order_line_type'] ?? 'PRODUCT');
            $line['extras'] = [];
            $lineIndexes[$orderLineId] = count($lines);
            $lines[] = $line;
        }

        return [
            'header' => $header,
            'lines' => $lines,
        ];
    }

    private function preferred_print_role_for_order_line(array $line): string
    {
        $preferredRole = strtoupper(trim((string)($line['preferred_printer_role'] ?? '')));
        if (in_array($preferredRole, ['BAR', 'KITCHEN'], true)) {
            return $preferredRole;
        }
        $division = strtoupper(trim((string)($line['product_division_name'] ?? '')));
        if ($division === 'BEVERAGE') {
            return 'BAR';
        }
        if ($division === 'FOOD' || $division === 'EVENT') {
            return 'KITCHEN';
        }
        return 'KITCHEN';
    }

    private function append_direct_print_header(array &$chunks, array $payload, int $width, string $align, string $dash, string $fallbackTitle = ''): void
    {
        $logoUrl = trim((string)($payload['logo_url'] ?? ''));
        if (!empty($payload['show_logo']) && $logoUrl !== '') {
            $logoMarker = $this->direct_print_logo_marker($logoUrl);
            if ($logoMarker !== '') {
                // Marker ini diterjemahkan Local Agent menjadi gambar ESC/POS.
                $chunks[] = $logoMarker;
            }
        }
        if (empty($payload['show_header'])) {
            return;
        }
        $hasHeaderContent = false;
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            $title = $fallbackTitle;
        }
        if ($title !== '') {
            $this->append_wrapped_aligned_print_text($chunks, $title, $width, $align);
            $hasHeaderContent = true;
        }
        $subtitle = trim((string)($payload['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $this->append_wrapped_aligned_print_text($chunks, $subtitle, $width, $align);
            $hasHeaderContent = true;
        }
        foreach ((array)($payload['header_lines'] ?? []) as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $this->append_wrapped_aligned_print_text($chunks, $line, $width, $align);
                $hasHeaderContent = true;
            }
        }
        if ($hasHeaderContent) {
            $chunks[] = $dash;
        }
    }

    private function direct_print_logo_marker(string $logoUrl): string
    {
        $logoUrl = trim($logoUrl);
        if ($logoUrl === '') {
            return '';
        }

        $ci = function_exists('get_instance') ? get_instance() : null;
        if ($ci && method_exists($ci, 'load') && (!isset($ci->posprinterpreviewservice) || !is_object($ci->posprinterpreviewservice))) {
            $ci->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        }
        if ($ci && isset($ci->posprinterpreviewservice) && is_object($ci->posprinterpreviewservice)
            && method_exists($ci->posprinterpreviewservice, 'buildLogoPrintMarker')) {
            return (string)$ci->posprinterpreviewservice->buildLogoPrintMarker($logoUrl);
        }

        return '[[LOGO_URL:' . $logoUrl . ']]';
    }

    private function direct_print_footer_barcode_value(array $payload, array $values): string
    {
        $source = strtoupper(trim((string)($payload['footer_barcode_source'] ?? 'ORDER_NO')));
        $map = [
            'ORDER_NO' => 'order_no',
            'PAYMENT_NO' => 'payment_no',
            'VOID_NO' => 'void_no',
            'REFUND_NO' => 'refund_no',
            'VOUCHER_CODE' => 'voucher_code',
            'CUSTOM' => 'custom',
        ];
        $key = $map[$source] ?? 'order_no';
        $value = $key === 'custom'
            ? (string)($payload['footer_barcode_custom'] ?? '')
            : (string)($values[$key] ?? '');
        return trim((string)preg_replace('/[\r\n\[\]]+/', '', $value));
    }

    private function direct_print_voucher_footer_lines(array $payload, array $issuedVouchers, int $width): array
    {
        if (empty($payload['show_customer_voucher']) || empty($issuedVouchers)) {
            return [];
        }
        $voucherLimit = max(1, min(5, (int)($payload['customer_voucher_limit'] ?? count($issuedVouchers))));
        $voucherAlign = strtoupper((string)($payload['customer_voucher_align'] ?? 'CENTER'));
        $voucherTemplate = trim((string)($payload['customer_voucher_message_template'] ?? ''));
        if ($voucherTemplate === '') {
            $voucherTemplate = "Selamat, Anda mendapat voucher {voucher_benefit}.\nKode: {voucher_code}\nGunakan sebelum {voucher_expiry}.";
        }
        if (strpos($voucherTemplate, '{voucher_code}') === false) {
            $voucherTemplate = rtrim($voucherTemplate) . "\nKode voucher: {voucher_code}";
        }
        $lines = [];
        foreach (array_slice($issuedVouchers, 0, $voucherLimit) as $index => $voucher) {
            $voucherCode = trim((string)($voucher['voucher_code'] ?? $voucher['voucher_issue_no'] ?? ''));
            if ($voucherCode === '') {
                continue;
            }
            if ($index === 0) {
                $lines[] = $this->align_text_line('VOUCHER BARU', $width, $voucherAlign ?: 'CENTER');
            }
            $voucherType = strtoupper(trim((string)($voucher['voucher_type'] ?? '')));
            if ($voucherType === 'PERCENT') {
                $benefit = rtrim(rtrim(number_format((float)($voucher['percent_snapshot'] ?? $voucher['discount_value'] ?? 0), 2, ',', '.'), '0'), ',') . '%';
                $maxDiscount = (float)($voucher['max_discount_amount'] ?? 0);
                if ($maxDiscount > 0) {
                    $benefit .= ' maks ' . $this->format_number_print($maxDiscount);
                }
            } elseif ($voucherType === 'FREE_PRODUCT') {
                $benefit = 'produk gratis';
            } else {
                $benefit = $this->format_number_print((float)($voucher['amount_snapshot'] ?? $voucher['discount_value'] ?? 0));
            }
            $expiry = !empty($voucher['expired_at']) ? date('d-m-Y', strtotime((string)$voucher['expired_at'])) : 'tanpa batas';
            $message = strtr($voucherTemplate, [
                '{voucher_code}' => $voucherCode,
                '{voucher_benefit}' => $benefit,
                '{voucher_expiry}' => $expiry,
                '{campaign_name}' => (string)($voucher['campaign_name'] ?? 'Voucher'),
            ]);
            $lines[] = $this->align_text_line('KODE: ' . $voucherCode, $width, $voucherAlign ?: 'CENTER');
            foreach ($this->wrap_print_text($message, $width) as $messageLine) {
                $lines[] = $this->align_text_line($messageLine, $width, $voucherAlign ?: 'CENTER');
            }
        }
        return $lines;
    }

    private function append_direct_print_footer(array &$chunks, array $payload, int $width, string $align, string $dash, array $barcodeValues = [], array $loyalty = [], array $issuedVouchers = []): void
    {
        $reviewUrl = trim((string)($loyalty['customer_review_url'] ?? ''));
        $showReviewQr = !empty($payload['show_customer_review_qr'])
            && !empty($payload['customer_review_qr_enabled'])
            && $reviewUrl !== '';
        if (empty($payload['show_footer']) && !$showReviewQr) {
            return;
        }
        $footerLines = [];
        if (!empty($payload['show_footer'])) {
            foreach ((array)($payload['footer_lines'] ?? []) as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $this->append_wrapped_aligned_print_text($footerLines, $line, $width, $align);
                }
            }
        }
        if (!empty($payload['show_footer']) && !empty($payload['show_wifi_info'])) {
            $wifiName = trim((string)($payload['wifi_name'] ?? ''));
            $wifiPassword = trim((string)($payload['wifi_password'] ?? ''));
            if ($wifiName !== '') {
                $footerLines[] = 'WIFI: ' . $wifiName;
            }
            if ($wifiPassword !== '') {
                $footerLines[] = 'PASS: ' . $wifiPassword;
            }
        }
        if (!empty($payload['show_footer']) && !empty($loyalty['has_member']) && !empty($payload['show_customer_point_info'])) {
            $footerLines[] = 'POIN ANDA  ' . $this->format_number_print($loyalty['point_balance'] ?? 0);
        }
        if (!empty($payload['show_footer']) && !empty($loyalty['has_member']) && !empty($payload['show_customer_stamp_info'])) {
            $footerLines[] = 'STAMP ANDA ' . $this->format_number_print($loyalty['stamp_balance'] ?? 0);
        }
        if (!empty($payload['show_footer'])) {
            foreach ($this->direct_print_voucher_footer_lines($payload, $issuedVouchers, $width) as $line) {
                $footerLines[] = $line;
            }
        }
        if (!empty($payload['show_footer']) && !empty($payload['show_footer_barcode'])) {
            $barcode = $this->direct_print_footer_barcode_value($payload, $barcodeValues);
            if ($barcode !== '') {
                // Local Agent menerjemahkan marker menjadi barcode Code128.
                $footerLines[] = '[[BARCODE:' . $barcode . ']]';
                $footerLines[] = $this->align_text_line($barcode, $width, $align);
            }
        }
        if ($showReviewQr) {
            $message = trim((string)($payload['customer_review_message'] ?? 'Bagikan ulasan Anda dengan scan QR berikut.'));
            if ($message !== '') {
                foreach ($this->wrap_print_text($message, $width) as $line) {
                    $footerLines[] = $this->align_text_line($line, $width, $align);
                }
            }
            // Local Agent menerjemahkan marker ini menjadi QR. URL tidak dicetak
            // ulang agar footer struk tetap ringkas dan mudah dibaca.
            $footerLines[] = '[[QRCODE:' . $reviewUrl . ']]';
        }
        if (!empty($footerLines)) {
            $chunks[] = $dash;
            foreach ($footerLines as $line) {
                $chunks[] = $line;
            }
        }
    }

    private function customer_review_url_for_payment(array $header, array $payload): string
    {
        if (empty($payload['show_customer_review_qr']) || empty($payload['customer_review_qr_enabled'])) {
            return '';
        }
        $reviewModel = $this->customer_review_model();
        if (!$reviewModel) {
            return '';
        }
        $review = $reviewModel->ensure_for_order($header);
        $token = trim((string)($review['review_token'] ?? ''));
        return preg_match('/^[a-f0-9]{64}$/i', $token) ? site_url('review/' . $token) : '';
    }

    private function build_direct_kot_text(array $header, array $lines, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $showInvoiceNo = !empty($payload['show_invoice_no']);
        $showCustomer = !empty($payload['show_customer']);
        $showTableNo = !empty($payload['show_table_no']);
        $showOrderTime = !empty($payload['show_order_time']);
        $showCashier = !empty($payload['show_cashier_order']);
        $showProductName = !empty($payload['show_product_name']);
        $showQty = !empty($payload['show_qty']);
        $showExtra = !empty($payload['show_extra']);
        $showNotes = !empty($payload['show_notes']);
        $customerName = $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? '');
        $chunks = [$divider];
        $this->append_direct_print_header($chunks, $payload, $width, $headerAlign, $dash, 'KOT');
        $roleBanner = $this->printer_role_banner_label((string)($printer['printer_role'] ?? ''));
        if ($roleBanner !== '') {
            $chunks[] = $this->align_text_line($roleBanner, $width, 'CENTER');
            $chunks[] = $dash;
        }
        if ($showInvoiceNo && !empty($header['order_no'])) {
            $chunks[] = 'ORDER  ' . (string)$header['order_no'];
        }
        if ($showTableNo && !empty($header['table_no'])) {
            $chunks[] = 'MEJA   ' . (string)$header['table_no'];
        }
        $chunks[] = 'LAYANAN ' . (string)($header['service_type'] ?? '-');
        $chunks[] = 'GUEST   ' . (int)($header['guest_count'] ?? 1);
        if ($showCustomer && $customerName !== '') {
            $chunks[] = 'CUSTOMER ' . $customerName;
        }
        $cashierLabel = trim((string)($header['cashier_username'] ?? ''));
        if ($cashierLabel === '') {
            $cashierLabel = trim((string)($header['cashier_employee_name'] ?? ''));
        }
        if ($showCashier && $cashierLabel !== '') {
            $chunks[] = 'KASIR   ' . strtoupper($cashierLabel);
        }
        if ($showOrderTime && !empty($header['ordered_at'])) {
            $chunks[] = 'WAKTU   ' . date('d-m-Y H:i', strtotime((string)$header['ordered_at']));
        }
        if (!empty($payload['show_order_notes']) && !empty($header['notes'])) {
            $chunks[] = 'CATATAN';
            foreach ($this->wrap_print_text((string)$header['notes'], $width) as $noteLine) {
                $chunks[] = $noteLine;
            }
        }
        $chunks[] = $dash;

        $this->append_direct_print_bundle_aware_lines(
            $chunks,
            $lines,
            $width,
            $showProductName,
            $showQty,
            $showExtra,
            $showNotes,
            false,
            0,
            0,
            $dash
        );

        $this->append_direct_print_footer($chunks, $payload, $width, $footerAlign, $dash, [
            'order_no' => (string)($header['order_no'] ?? ''),
        ]);
        return $this->finalize_direct_print_text($chunks, $width);
    }

    private function build_direct_reversal_slip_text(string $documentType, array $document, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $header = (array)($document['header'] ?? []);
        $lines = (array)($document['lines'] ?? []);
        $customerName = $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? '');
        $documentType = strtoupper(trim($documentType));
        $isVoid = $documentType === 'VOID_SLIP';
        $documentNo = $isVoid ? (string)($header['void_no'] ?? '-') : (string)($header['refund_no'] ?? '-');
        $documentAmount = $isVoid ? (float)($header['amount_void'] ?? 0) : (float)($header['refund_amount'] ?? 0);
        $actorName = trim((string)($header['actor_name'] ?? ''));
        $reason = trim((string)($header['reason'] ?? ''));
        $timeValue = $isVoid ? (string)($header['created_at'] ?? '') : (string)($header['refunded_at'] ?? '');

        $chunks = [$divider];
        $this->append_direct_print_header($chunks, $payload, $width, $headerAlign, $dash, $isVoid ? 'SLIP VOID' : 'SLIP REFUND');
        $roleBanner = $this->printer_role_banner_label((string)($printer['printer_role'] ?? ''));
        if ($roleBanner !== '') {
            $chunks[] = $this->align_text_line($roleBanner, $width, 'CENTER');
            $chunks[] = $dash;
        }

        if (!empty($payload['show_invoice_no']) && !empty($header['order_no'])) {
            $chunks[] = 'ORDER      ' . (string)$header['order_no'];
        }
        $chunks[] = ($isVoid ? 'VOID' : 'REFUND') . str_repeat(' ', $isVoid ? 7 : 5) . $documentNo;
        if (!empty($payload['show_customer']) && $customerName !== '') {
            $chunks[] = 'CUSTOMER   ' . $customerName;
        }
        if (!empty($payload['show_table_no']) && !empty($header['table_no'])) {
            $chunks[] = 'MEJA       ' . (string)$header['table_no'];
        }
        if ($timeValue !== '' && (!empty($payload['show_order_time']) || !empty($payload['show_payment_time']))) {
            $chunks[] = ($isVoid ? 'WAKTU      ' : 'REFUND AT  ') . date('d-m-Y H:i', strtotime($timeValue));
        }
        if ((!empty($payload['show_cashier_order']) || !empty($payload['show_cashier_payment'])) && $actorName !== '') {
            $chunks[] = 'PETUGAS    ' . strtoupper($actorName);
        }
        if (!$isVoid && !empty($payload['show_payment_breakdown']) && !empty($header['method_name'])) {
            $chunks[] = 'METODE     ' . (string)$header['method_name'];
        }
        if ((!empty($payload['show_void_reason']) || !empty($payload['show_refund_reason'])) && $reason !== '') {
            $chunks[] = 'ALASAN     ' . $reason;
        }
        $chunks[] = $dash;

        // A matched-division reversal is a production notification, not a
        // financial slip. Make the instruction explicit so BAR/KITCHEN can
        // stop work before the affected item is prepared.
        $isDivisionNotice = strtoupper((string)($printer['print_scope'] ?? 'ALL_ITEMS')) === 'MATCHED_DIVISION';
        if ($isDivisionNotice) {
            $this->append_wrapped_aligned_print_text(
                $chunks,
                $isVoid ? 'JANGAN DIPROSES' : 'JANGAN DIPROSES BILA BELUM DIBUAT',
                $width,
                'CENTER'
            );
            $chunks[] = $dash;
        }

        $priceWidth = min(12, max(9, (int)round($width * 0.28)));
        $nameWidth = max(10, $width - $priceWidth);
        $this->append_direct_print_bundle_aware_lines(
            $chunks,
            $lines,
            $width,
            !empty($payload['show_product_name']),
            !empty($payload['show_qty']),
            !empty($payload['show_extra']),
            !empty($payload['show_notes']),
            !empty($payload['show_price']),
            $nameWidth,
            $priceWidth
        );

        if (!empty($payload['show_grand_total'])) {
            $chunks[] = $dash;
            $chunks[] = 'NILAI      ' . $this->format_number_print($documentAmount);
        }

        $this->append_direct_print_footer($chunks, $payload, $width, $footerAlign, $dash, [
            'order_no' => (string)($header['order_no'] ?? ''),
            'void_no' => (string)($header['void_no'] ?? ''),
            'refund_no' => (string)($header['refund_no'] ?? ''),
        ]);

        return $this->finalize_direct_print_text($chunks, $width);
    }

    private function build_direct_order_prebill_text(array $order, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $header = (array)($order['header'] ?? []);
        $orderLines = (array)($order['lines'] ?? []);
        $customerName = $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? '');
        $cashierLabel = trim((string)($header['cashier_username'] ?? ''));
        if ($cashierLabel === '') {
            $cashierLabel = trim((string)($header['cashier_employee_name'] ?? ''));
        }
        $showPrice = !empty($payload['show_price']);
        $showProductName = !empty($payload['show_product_name']);
        $amountWidth = min(14, max(11, (int)round($width * 0.3)));
        $labelWidth = max(10, $width - $amountWidth);
        $chunks = [$divider];
        $this->append_direct_print_header($chunks, $payload, $width, $headerAlign, $dash, 'BILL SEMENTARA');
        $roleBanner = $this->printer_role_banner_label((string)($printer['printer_role'] ?? ''));
        if ($roleBanner !== '') {
            $chunks[] = $this->align_text_line($roleBanner, $width, 'CENTER');
            $chunks[] = $dash;
        }

        if (!empty($payload['show_invoice_no']) && !empty($header['order_no'])) {
            $chunks[] = 'ORDER      ' . (string)$header['order_no'];
        }
        if (!empty($payload['show_customer']) && $customerName !== '') {
            $chunks[] = 'CUSTOMER   ' . $customerName;
        }
        if (!empty($payload['show_table_no']) && !empty($header['table_no'])) {
            $chunks[] = 'MEJA       ' . (string)$header['table_no'];
        }
        if (!empty($header['service_type'])) {
            $chunks[] = 'LAYANAN    ' . (string)$header['service_type'];
        }
        $chunks[] = 'GUEST      ' . (int)($header['guest_count'] ?? 1);
        $timeSource = (string)($header['ordered_at'] ?? $header['created_at'] ?? '');
        if (!empty($payload['show_order_time']) && $timeSource !== '') {
            $chunks[] = 'WAKTU      ' . date('d-m-Y H:i', strtotime($timeSource));
        }
        if (!empty($payload['show_cashier_order']) && $cashierLabel !== '') {
            $chunks[] = 'KASIR      ' . strtoupper($cashierLabel);
        }
        $chunks[] = $dash;

        $this->append_direct_print_bundle_aware_lines(
            $chunks,
            $orderLines,
            $width,
            $showProductName,
            !empty($payload['show_qty']),
            !empty($payload['show_extra']),
            !empty($payload['show_notes']),
            $showPrice,
            $labelWidth,
            $amountWidth
        );

        $summaryRows = [];
        if (!empty($payload['show_subtotal'])) {
            $summaryRows[] = $this->pad_right_print('SUBTOTAL', $labelWidth) . $this->pad_left_print($this->format_number_print($header['subtotal_amount'] ?? 0), $amountWidth);
        }
        foreach ([
            'discount_amount' => 'DISKON',
            'promo_amount' => 'PROMO',
            'voucher_amount' => 'VOUCHER',
            'point_redeem_amount' => 'POINT',
            'compliment_amount' => 'COMPLIMENT',
        ] as $key => $label) {
            $value = round((float)($header[$key] ?? 0), 2);
            $enabled = $key === 'compliment_amount' ? !empty($payload['show_compliment']) : !empty($payload['show_discount']);
            if ($enabled && $value > 0.009) {
                $summaryRows[] = $this->pad_right_print($label, $labelWidth) . $this->pad_left_print('-' . $this->format_number_print($value), $amountWidth);
            }
        }
        if (!empty($payload['show_grand_total'])) {
            $summaryRows[] = $this->pad_right_print('TOTAL', $labelWidth) . $this->pad_left_print($this->format_number_print($header['grand_total'] ?? 0), $amountWidth);
        }
        $paidTotal = round((float)($header['paid_total'] ?? 0), 2);
        $remainingDue = $this->order_remaining_due_amount($header);
        if (!empty($payload['show_paid_amount']) && $paidTotal > 0.009) {
            $summaryRows[] = $this->pad_right_print('SUDAH BAYAR', $labelWidth) . $this->pad_left_print($this->format_number_print($paidTotal), $amountWidth);
        }
        if (!empty($payload['show_balance_due'])) {
            $summaryRows[] = $this->pad_right_print('SISA', $labelWidth) . $this->pad_left_print($this->format_number_print($remainingDue), $amountWidth);
        }
        if (!empty($summaryRows)) {
            $chunks[] = $dash;
            foreach ($summaryRows as $summaryRow) {
                $chunks[] = $summaryRow;
            }
        }

        if (!empty($payload['show_order_notes']) && !empty($header['notes'])) {
            $chunks[] = $dash;
            $chunks[] = 'CATATAN';
            foreach ($this->wrap_print_text((string)$header['notes'], $width) as $noteLine) {
                $chunks[] = $noteLine;
            }
        }

        $this->append_direct_print_footer($chunks, $payload, $width, $footerAlign, $dash, [
            'order_no' => (string)($header['order_no'] ?? ''),
        ]);

        return $this->finalize_direct_print_text($chunks, $width);
    }

    private function build_direct_shift_close_receipt_text(array $report, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $shift = (array)($report['shift'] ?? []);
        $summary = (array)($report['summary'] ?? []);
        $byMethod = (array)($report['by_method'] ?? []);
        $byAccount = (array)($report['by_account'] ?? []);
        $cashBreakdown = (array)($report['cash_breakdown'] ?? []);

        $chunks = [$divider];
        $this->append_direct_print_header($chunks, $payload, $width, $headerAlign, $dash, 'LAPORAN TUTUP KASIR');
        $roleBanner = $this->printer_role_banner_label((string)($printer['printer_role'] ?? ''));
        if ($roleBanner !== '') {
            $chunks[] = $this->align_text_line($roleBanner, $width, 'CENTER');
            $chunks[] = $dash;
        }

        $chunks[] = 'SHIFT      ' . (string)($shift['shift_no'] ?? '-');
        $chunks[] = 'OUTLET     ' . (string)($shift['outlet_name'] ?? '-');
        $chunks[] = 'TERMINAL   ' . (string)($shift['terminal_name'] ?? '-');
        $chunks[] = 'KASIR BUKA ' . strtoupper(trim((string)($shift['cashier_open_name'] ?? '-')));
        if (!empty($shift['cashier_close_name'])) {
            $chunks[] = 'KASIR TTP  ' . strtoupper(trim((string)$shift['cashier_close_name']));
        }
        if (!empty($shift['opened_at'])) {
            $chunks[] = 'BUKA       ' . date('d-m-Y H:i', strtotime((string)$shift['opened_at']));
        }
        if (!empty($shift['closed_at'])) {
            $chunks[] = 'TUTUP      ' . date('d-m-Y H:i', strtotime((string)$shift['closed_at']));
        }

        $chunks[] = $dash;
        $chunks[] = $this->pad_right_print('Modal awal', $width - 14) . $this->pad_left_print($this->format_number_print($summary['opening_cash'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Penerimaan', $width - 14) . $this->pad_left_print($this->format_number_print($summary['gross_receipts'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Refund', $width - 14) . $this->pad_left_print($this->format_number_print($summary['refund_total'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Net kasir', $width - 14) . $this->pad_left_print($this->format_number_print($summary['net_receipts'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Expected', $width - 14) . $this->pad_left_print($this->format_number_print($summary['expected_cash'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Aktual', $width - 14) . $this->pad_left_print($this->format_number_print($summary['actual_cash'] ?? 0), 14);
        $chunks[] = $this->pad_right_print('Selisih', $width - 14) . $this->pad_left_print($this->format_number_print($summary['variance_cash'] ?? 0), 14);

        $chunks[] = $dash;
        $chunks[] = 'TOTAL ORDER ' . (int)($summary['total_order_count'] ?? 0);
        $chunks[] = 'CASH SALES  ' . $this->format_number_print($summary['total_cash_sales'] ?? 0);
        $chunks[] = 'NON CASH    ' . $this->format_number_print($summary['total_non_cash_sales'] ?? 0);
        $chunks[] = 'DEPOSIT     ' . $this->format_number_print($summary['total_deposit_receipts'] ?? 0);
        $chunks[] = 'VOID        ' . $this->format_number_print($summary['total_void'] ?? 0);

        $chunks[] = $dash;
        $chunks[] = '[ PER METODE ]';
        if (empty($byMethod)) {
            $chunks[] = 'Belum ada penerimaan.';
        } else {
            foreach ($byMethod as $row) {
                $chunks[] = $this->pad_right_print(strtoupper(trim((string)($row['method_name'] ?? 'METODE'))), $width - 14)
                    . $this->pad_left_print($this->format_number_print($row['net_amount'] ?? 0), 14);
            }
        }

        $chunks[] = $dash;
        $chunks[] = '[ PER REKENING ]';
        if (empty($byAccount)) {
            $chunks[] = 'Belum ada rekening terkait.';
        } else {
            foreach ($byAccount as $row) {
                $label = trim((string)($row['account_label'] ?? 'REKENING'));
                $chunks[] = $this->pad_right_print(strtoupper($label), $width - 14)
                    . $this->pad_left_print($this->format_number_print($row['net_amount'] ?? 0), 14);
            }
        }

        if (!empty($cashBreakdown)) {
            $chunks[] = $dash;
            $chunks[] = '[ PECAHAN CASH ]';
            foreach ($cashBreakdown as $row) {
                $label = $this->format_number_print($row['denomination_amount'] ?? 0) . ' x ' . (int)($row['qty_count'] ?? 0);
                $chunks[] = $this->pad_right_print($label, $width - 14)
                    . $this->pad_left_print($this->format_number_print($row['total_amount'] ?? 0), 14);
            }
        }

        if (!empty($shift['notes'])) {
            $chunks[] = $dash;
            $chunks[] = 'CATATAN';
            foreach ($this->wrap_print_text((string)$shift['notes'], $width) as $line) {
                $chunks[] = $line;
            }
        }

        $this->append_direct_print_footer($chunks, $payload, $width, $footerAlign, $dash, [
            'custom' => (string)($shift['shift_no'] ?? ''),
        ]);

        return $this->finalize_direct_print_text($chunks, $width);
    }

    private function build_direct_payment_receipt_text(array $document, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $header = (array)($document['header'] ?? []);
        $lines = (array)($document['lines'] ?? []);
        $paymentLines = (array)($document['payment_lines'] ?? []);
        $issuedVouchers = (array)($document['issued_vouchers'] ?? []);
        $customerName = $this->resolve_order_customer_name($header['customer_name'] ?? '', $header['member_name'] ?? '');
        $cashierLabel = trim((string)($header['cashier_username'] ?? ''));
        if ($cashierLabel === '') {
            $cashierLabel = trim((string)($header['actor_name'] ?? ($header['cashier_employee_name'] ?? '')));
        }
        $showPrice = !empty($payload['show_price']);
        $showProductName = !empty($payload['show_product_name']);
        $amountWidth = min(14, max(11, (int)round($width * 0.3)));
        $labelWidth = max(10, $width - $amountWidth);

        $chunks = [$divider];
        $this->append_direct_print_header($chunks, $payload, $width, $headerAlign, $dash, 'STRUK PEMBAYARAN');
        $roleBanner = $this->printer_role_banner_label((string)($printer['printer_role'] ?? ''));
        if ($roleBanner !== '') {
            $chunks[] = $this->align_text_line($roleBanner, $width, 'CENTER');
            $chunks[] = $dash;
        }

        if (!empty($payload['show_invoice_no']) && !empty($header['order_no'])) {
            $chunks[] = 'ORDER      ' . (string)$header['order_no'];
        }
        if (!empty($payload['show_payment_no']) && !empty($header['payment_no'])) {
            $chunks[] = 'PAYMENT    ' . (string)$header['payment_no'];
        }
        if (!empty($payload['show_customer']) && $customerName !== '') {
            $chunks[] = 'CUSTOMER   ' . $customerName;
        }
        if (!empty($payload['show_table_no']) && !empty($header['table_no'])) {
            $chunks[] = 'MEJA       ' . (string)$header['table_no'];
        }
        if (!empty($header['service_type'])) {
            $chunks[] = 'LAYANAN    ' . (string)$header['service_type'];
        }
        $chunks[] = 'GUEST      ' . (int)($header['guest_count'] ?? 1);
        if (!empty($payload['show_order_time']) && !empty($header['ordered_at'])) {
            $chunks[] = 'ORDER AT   ' . date('d-m-Y H:i', strtotime((string)$header['ordered_at']));
        }
        if (!empty($payload['show_payment_time']) && !empty($header['paid_at'])) {
            $chunks[] = 'WAKTU      ' . date('d-m-Y H:i', strtotime((string)$header['paid_at']));
        }
        if ((!empty($payload['show_cashier_order']) || !empty($payload['show_cashier_payment'])) && $cashierLabel !== '') {
            $chunks[] = 'KASIR      ' . strtoupper($cashierLabel);
        }
        $chunks[] = $dash;

        $this->append_direct_print_bundle_aware_lines(
            $chunks,
            $lines,
            $width,
            $showProductName,
            !empty($payload['show_qty']),
            !empty($payload['show_extra']),
            !empty($payload['show_notes']),
            $showPrice,
            $labelWidth,
            $amountWidth
        );

        $summaryRows = [];
        if (!empty($payload['show_subtotal'])) {
            $summaryRows[] = $this->pad_right_print('SUBTOTAL', $labelWidth) . $this->pad_left_print($this->format_number_print($header['gross_amount'] ?? $header['subtotal_amount'] ?? 0), $amountWidth);
        }
        foreach ([
            'discount_amount' => 'DISKON',
            'promo_amount' => 'PROMO',
            'voucher_amount' => 'VOUCHER',
            'point_redeem_amount' => 'POINT',
            'compliment_amount' => 'COMPLIMENT',
        ] as $key => $label) {
            $value = round((float)($header[$key] ?? 0), 2);
            $enabled = $key === 'compliment_amount' ? !empty($payload['show_compliment']) : !empty($payload['show_discount']);
            if ($enabled && $value > 0.009) {
                $summaryRows[] = $this->pad_right_print($label, $labelWidth) . $this->pad_left_print('-' . $this->format_number_print($value), $amountWidth);
            }
        }
        if (!empty($payload['show_grand_total'])) {
            $summaryRows[] = $this->pad_right_print('TOTAL', $labelWidth) . $this->pad_left_print($this->format_number_print($header['net_amount'] ?? $header['order_grand_total'] ?? 0), $amountWidth);
        }
        $previousPaid = round((float)($header['previous_paid'] ?? 0), 2);
        if (!empty($payload['show_paid_amount']) && $previousPaid > 0.009) {
            $summaryRows[] = $this->pad_right_print('BAYAR LALU', $labelWidth) . $this->pad_left_print($this->format_number_print($previousPaid), $amountWidth);
        }
        if (!empty($payload['show_deposit_applied']) && (float)($header['deposit_applied_now'] ?? 0) > 0.009) {
            $summaryRows[] = $this->pad_right_print('PAKAI DP', $labelWidth) . $this->pad_left_print($this->format_number_print($header['deposit_applied_now'] ?? 0), $amountWidth);
        }
        if (!empty($payload['show_paid_amount']) && ((float)($header['paid_now'] ?? 0) > 0.009 || (float)($header['deposit_applied_now'] ?? 0) <= 0.009)) {
            $summaryRows[] = $this->pad_right_print('BAYAR NOW', $labelWidth) . $this->pad_left_print($this->format_number_print($header['paid_now'] ?? 0), $amountWidth);
        }
        if (!empty($payload['show_paid_amount']) && (float)($header['change_amount'] ?? 0) > 0.009) {
            $summaryRows[] = $this->pad_right_print('KEMBALI', $labelWidth) . $this->pad_left_print($this->format_number_print($header['change_amount'] ?? 0), $amountWidth);
        }
        if (!empty($payload['show_balance_due']) && (float)($header['remaining_due'] ?? 0) > 0.009) {
            $summaryRows[] = $this->pad_right_print('SISA', $labelWidth) . $this->pad_left_print($this->format_number_print($header['remaining_due'] ?? 0), $amountWidth);
        }
        if (!empty($summaryRows)) {
            $chunks[] = $dash;
            foreach ($summaryRows as $summaryRow) {
                $chunks[] = $summaryRow;
            }
        }

        if (!empty($payload['show_payment_breakdown']) && !empty($paymentLines)) {
            $chunks[] = $dash;
            $chunks[] = 'METODE BAYAR';
            foreach ($paymentLines as $paymentLine) {
                $methodName = trim((string)($paymentLine['method_name'] ?? 'Metode'));
                $chunks[] = $this->pad_right_print($methodName, $labelWidth) . $this->pad_left_print($this->format_number_print($paymentLine['amount'] ?? 0), $amountWidth);
                $referenceNo = trim((string)($paymentLine['reference_no'] ?? ''));
                if ($referenceNo !== '') {
                    $chunks[] = '  REF: ' . $referenceNo;
                }
            }
        }

        if (!empty($payload['show_order_notes']) && !empty($header['notes'])) {
            $chunks[] = $dash;
            $chunks[] = 'CATATAN';
            foreach ($this->wrap_print_text((string)$header['notes'], $width) as $noteLine) {
                $chunks[] = $noteLine;
            }
        }

        $firstVoucher = (array)reset($issuedVouchers);
        $customerReviewUrl = $this->customer_review_url_for_payment($header, $payload);
        $this->append_direct_print_footer($chunks, $payload, $width, $footerAlign, $dash, [
            'order_no' => (string)($header['order_no'] ?? ''),
            'payment_no' => (string)($header['payment_no'] ?? ''),
            'voucher_code' => (string)($firstVoucher['voucher_code'] ?? $firstVoucher['voucher_issue_no'] ?? ''),
        ], [
            'has_member' => (int)($header['member_id'] ?? 0) > 0,
            'point_balance' => (float)($header['member_point_balance'] ?? 0),
            'stamp_balance' => (float)($header['member_stamp_balance'] ?? 0),
            'customer_review_url' => $customerReviewUrl,
        ], $issuedVouchers);

        return $this->finalize_direct_print_text($chunks, $width);
    }

    private function order_remaining_due_amount(array $header): float
    {
        $grandTotal = round((float)($header['grand_total'] ?? 0), 2);
        $paidTotal = round((float)($header['paid_total'] ?? 0), 2);
        return round(max(0, $grandTotal - $paidTotal), 2);
    }

    private function normalize_printer_chars_per_line(int $paperWidthMm, int $charsPerLine): int
    {
        $paperWidthMm = $paperWidthMm === 58 ? 58 : 80;
        // Font density is fixed by the printer agent. Keeping one value per
        // paper width prevents output from overflowing on 58 mm printers.
        return $paperWidthMm === 58 ? 32 : 48;
    }

    private function printer_role_banner_label(string $role): string
    {
        $normalizedRole = strtoupper(trim($role));
        $map = [
            'BAR' => 'AREA CETAK BAR',
            'KITCHEN' => 'AREA CETAK KITCHEN',
            'CHECKER' => 'AREA CETAK CHECKER',
        ];
        return $map[$normalizedRole] ?? '';
    }

    private function center_text_line(string $text, int $width): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len >= $width) {
            return $text;
        }
        $left = (int)floor(($width - $len) / 2);
        return str_repeat(' ', max(0, $left)) . $text;
    }

    private function align_text_line(string $text, int $width, string $align = 'CENTER'): string
    {
        $text = trim($text);
        $align = strtoupper(trim($align));
        if ($text === '') {
            return '';
        }
        if ($align === 'LEFT' || $align === 'JUSTIFY') {
            return $text;
        }
        if ($align === 'RIGHT') {
            $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
            if ($len >= $width) {
                return $text;
            }
            return str_repeat(' ', max(0, $width - $len)) . $text;
        }
        return $this->center_text_line($text, $width);
    }

    private function append_wrapped_aligned_print_text(array &$lines, string $text, int $width, string $align): void
    {
        foreach ($this->wrap_print_text($text, $width) as $line) {
            $lines[] = $this->align_text_line($line, $width, $align);
        }
    }

    private function finalize_direct_print_text(array $chunks, int $width): string
    {
        $lines = [];
        foreach ($chunks as $chunk) {
            foreach (preg_split('/\r?\n/', (string)$chunk) ?: [] as $line) {
                $line = rtrim((string)$line);
                if ($line === '' || preg_match('/^\[\[(?:LOGO_URL|QRCODE|BARCODE):/', $line)) {
                    $lines[] = $line;
                    continue;
                }
                if ($this->print_text_width($line) <= $width) {
                    $lines[] = $line;
                    continue;
                }
                foreach ($this->wrap_print_text($line, $width) as $wrappedLine) {
                    $lines[] = $wrappedLine;
                }
            }
        }
        return rtrim(implode("\n", $lines), "\n") . "\n\n\n";
    }

    private function print_text_width(string $text): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($text, 'UTF-8') : strlen($text);
    }

    private function split_print_word(string $word, int $width): array
    {
        $width = max(1, $width);
        $parts = [];
        $current = '';
        $length = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
        for ($index = 0; $index < $length; $index++) {
            $character = function_exists('mb_substr')
                ? mb_substr($word, $index, 1, 'UTF-8')
                : substr($word, $index, 1);
            if ($current !== '' && $this->print_text_width($current . $character) > $width) {
                $parts[] = $current;
                $current = $character;
                continue;
            }
            $current .= $character;
        }
        if ($current !== '') {
            $parts[] = $current;
        }
        return $parts ?: [''];
    }

    private function wrap_print_text(string $text, int $width): array
    {
        $width = max(1, $width);
        $rows = [];
        foreach (preg_split('/\r?\n/', (string)$text) ?: [] as $sourceLine) {
            $sourceLine = trim((string)preg_replace('/[ \t]+/u', ' ', (string)$sourceLine));
            if ($sourceLine === '') {
                continue;
            }
            $current = '';
            foreach (preg_split('/\s+/u', $sourceLine, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $parts = $this->split_print_word($word, $width);
                if (count($parts) > 1) {
                    if ($current !== '') {
                        $rows[] = $current;
                        $current = '';
                    }
                    $lastIndex = count($parts) - 1;
                    foreach ($parts as $index => $part) {
                        if ($index === $lastIndex) {
                            $current = $part;
                        } else {
                            $rows[] = $part;
                        }
                    }
                    continue;
                }
                $part = (string)$parts[0];
                $candidate = $current === '' ? $part : ($current . ' ' . $part);
                if ($this->print_text_width($candidate) <= $width) {
                    $current = $candidate;
                    continue;
                }
                if ($current !== '') {
                    $rows[] = $current;
                }
                $current = $part;
            }
            if ($current !== '') {
                $rows[] = $current;
            }
        }
        return $rows;
    }

    private function pad_right_print(string $text, int $width): string
    {
        $text = trim($text);
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len >= $width) {
            return $text;
        }
        return $text . str_repeat(' ', max(0, $width - $len));
    }

    private function pad_left_print(string $text, int $width): string
    {
        $text = trim($text);
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len >= $width) {
            return $text;
        }
        return str_repeat(' ', max(0, $width - $len)) . $text;
    }

    private function format_number_print($amount): string
    {
        return number_format((float)$amount, 0, ',', '.');
    }

    private function load_order_product_recipe_rows(int $productId): array
    {
        if ($productId <= 0 || !$this->db->table_exists('mst_product_recipe')) {
            return [];
        }

        $select = [
            'r.id',
            'r.product_id',
            'r.line_type',
            'r.qty',
            'r.source_division_id',
            'sd.code AS source_division_code',
            'sd.name AS source_division_name',
            'r.material_item_id',
            'r.component_id',
            'i.material_id',
            'i.item_name',
            'i.content_uom_id AS item_uom_id',
            'm.material_name',
            'm.content_uom_id AS material_uom_id',
            'c.component_name',
            'c.operational_division_id AS component_operational_division_id',
            'c.uom_id AS component_uom_id',
        ];
        if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            $select[] = 'r.ingredient_role';
        }
        if ($this->db->field_exists('uom_id', 'mst_product_recipe')) {
            $select[] = 'r.uom_id AS recipe_uom_id';
        }

        return $this->db->select(implode(",\n", $select))
            ->from('mst_product_recipe r')
            ->join('mst_operational_division sd', 'sd.id = r.source_division_id', 'left')
            ->join('mst_item i', 'i.id = r.material_item_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_component c', 'c.id = r.component_id', 'left')
            ->where('r.product_id', $productId)
            ->order_by('r.sort_order', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function resolve_order_recipe_consumption_line(array $header, array $orderLine, array $recipeRow): array
    {
        $lineType = strtoupper(trim((string)($recipeRow['line_type'] ?? 'MATERIAL')));
        $orderedQty = (float)($orderLine['qty'] ?? 0);
        $recipeQty = (float)($recipeRow['qty'] ?? 0);
        if ($orderedQty <= 0 || $recipeQty <= 0) {
            return ['ok' => false, 'message' => 'Qty recipe/order tidak valid untuk produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
        }

        $requiredQty = round($orderedQty * $recipeQty, 4);
        $sourceRole = $this->normalize_stock_source_role((string)($recipeRow['ingredient_role'] ?? 'MAIN'));
        $resolvedDivisionId = 0;
        $requiredUomId = 0;
        $sourceName = '';
        $unitCost = 0.0;
        $costSource = 'STANDARD_FALLBACK';
        $materialId = null;
        $componentId = null;

        if ($lineType === 'COMPONENT') {
            $componentId = (int)($recipeRow['component_id'] ?? 0);
            if ($componentId <= 0) {
                return ['ok' => false, 'message' => 'Recipe produk memakai component yang tidak valid pada produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
            }
            $resolvedDivisionId = (int)($recipeRow['source_division_id'] ?? 0);
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($recipeRow['component_operational_division_id'] ?? 0);
            }
            $requiredUomId = (int)($recipeRow['recipe_uom_id'] ?? 0);
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['component_uom_id'] ?? 0);
            }
            $sourceName = trim((string)($recipeRow['component_name'] ?? ''));
            $cost = $this->resolve_order_component_live_cost($componentId, $resolvedDivisionId);
            $unitCost = (float)($cost['unit_cost'] ?? 0);
            $costSource = (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK');
        } else {
            $materialId = (int)($recipeRow['material_id'] ?? 0);
            if ($materialId <= 0) {
                return ['ok' => false, 'message' => 'Recipe produk memakai bahan baku yang belum tersambung material pada produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
            }
            $resolvedDivisionId = (int)($recipeRow['source_division_id'] ?? 0);
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
            }
            $requiredUomId = (int)($recipeRow['recipe_uom_id'] ?? 0);
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['item_uom_id'] ?? 0);
            }
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['material_uom_id'] ?? 0);
            }
            $sourceName = trim((string)($recipeRow['material_name'] ?? ($recipeRow['item_name'] ?? '')));
            $cost = $this->resolve_order_material_live_cost($materialId, $resolvedDivisionId, $requiredUomId);
            $unitCost = (float)($cost['unit_cost'] ?? 0);
            $costSource = (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK');
        }

        if ($resolvedDivisionId <= 0) {
            $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
        }

        return [
            'ok' => true,
            'line' => [
                'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
                'order_line_id' => !empty($orderLine['id']) ? (int)$orderLine['id'] : null,
                'order_line_extra_id' => null,
                'line_type' => 'PRODUCT',
                'product_id' => (int)($orderLine['product_id'] ?? 0),
                'extra_id' => null,
                'source_kind' => $lineType === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
                'source_role' => $sourceRole,
                'material_id' => $materialId ?: null,
                'component_id' => $componentId ?: null,
                'source_name_snapshot' => $sourceName !== '' ? $sourceName : null,
                'required_qty' => $requiredQty,
                'required_uom_id' => $requiredUomId > 0 ? $requiredUomId : null,
                'committed_qty' => $requiredQty,
                'reversed_qty' => 0,
                'unit_cost_live' => round($unitCost, 6),
                'total_cost_live' => round($requiredQty * $unitCost, 6),
                'cost_source' => $costSource,
                'resolved_source_division_id' => $resolvedDivisionId > 0 ? $resolvedDivisionId : null,
                'resolved_source_division_code' => trim((string)($recipeRow['source_division_code'] ?? '')) !== '' ? trim((string)$recipeRow['source_division_code']) : null,
                'resolved_source_division_name' => trim((string)($recipeRow['source_division_name'] ?? '')) !== '' ? trim((string)$recipeRow['source_division_name']) : null,
                'movement_ref_type' => 'NONE',
                'movement_ref_id' => null,
                'return_policy' => 'RETURN_TO_STOCK',
                'reversal_status' => 'NONE',
                'notes' => 'Resolved dari recipe produk line #' . (int)($orderLine['line_no'] ?? 0),
            ],
        ];
    }

    private function resolve_order_extra_consumption_line(array $header, array $orderLine, array $extraLine): array
    {
        $sourceKind = strtoupper(trim((string)($extraLine['source_kind'] ?? 'NONE')));
        if ($sourceKind === 'NONE') {
            return ['ok' => true, 'lines' => []];
        }

        $extraQty = (float)($extraLine['qty'] ?? 0);
        $sourceQty = (float)($extraLine['source_qty'] ?? 0);
        if ($extraQty <= 0 || $sourceQty <= 0) {
            return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source qty yang valid untuk stock commit.'];
        }

        $requiredQty = round($extraQty * $sourceQty, 4);
        $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
        $resolvedDivisionCode = null;
        $resolvedDivisionName = null;
        $line = [
            'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
            'order_line_id' => !empty($orderLine['id']) ? (int)$orderLine['id'] : null,
            'order_line_extra_id' => !empty($extraLine['id']) ? (int)$extraLine['id'] : null,
            'line_type' => 'EXTRA',
            'product_id' => (int)($orderLine['product_id'] ?? 0),
            'extra_id' => (int)($extraLine['extra_id'] ?? 0),
            'source_role' => 'OPTIONAL',
            'movement_ref_type' => 'NONE',
            'movement_ref_id' => null,
            'return_policy' => 'RETURN_TO_STOCK',
            'reversal_status' => 'NONE',
            'notes' => 'Resolved dari extra order line #' . (int)($orderLine['line_no'] ?? 0),
        ];

        if ($sourceKind === 'COMPONENT') {
            $componentId = (int)($extraLine['source_component_id'] ?? 0);
            if ($componentId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source component yang valid.'];
            }
            $component = $this->db->select('component_name, uom_id, operational_division_id')
                ->from('mst_component')
                ->where('id', $componentId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($component['operational_division_id'] ?? 0);
            }
            $divisionMeta = $this->resolve_order_operational_division_meta($resolvedDivisionId);
            $resolvedDivisionCode = $divisionMeta['code'] ?? null;
            $resolvedDivisionName = $divisionMeta['name'] ?? null;
            $cost = $this->resolve_order_component_live_cost($componentId, $resolvedDivisionId);
            return [
                'ok' => true,
                'lines' => [[
                    'source_kind' => 'COMPONENT',
                    'material_id' => null,
                    'component_id' => $componentId,
                    'source_name_snapshot' => trim((string)($component['component_name'] ?? ($extraLine['extra_name'] ?? ''))) ?: null,
                    'required_qty' => $requiredQty,
                    'required_uom_id' => !empty($component['uom_id']) ? (int)$component['uom_id'] : null,
                    'committed_qty' => $requiredQty,
                    'reversed_qty' => 0,
                    'unit_cost_live' => round((float)($cost['unit_cost'] ?? 0), 6),
                    'total_cost_live' => round($requiredQty * (float)($cost['unit_cost'] ?? 0), 6),
                    'cost_source' => (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK'),
                    'resolved_source_division_id' => $resolvedDivisionId > 0 ? $resolvedDivisionId : null,
                    'resolved_source_division_code' => $resolvedDivisionCode,
                    'resolved_source_division_name' => $resolvedDivisionName,
                ] + $line],
            ];
        }

        if ($sourceKind === 'MATERIAL') {
            $materialId = (int)($extraLine['source_material_id'] ?? 0);
            if ($materialId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source material yang valid.'];
            }
            $material = $this->db->select('material_name, content_uom_id')
                ->from('mst_material')
                ->where('id', $materialId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            $divisionMeta = $this->resolve_order_operational_division_meta($resolvedDivisionId);
            $requiredUomId = !empty($material['content_uom_id']) ? (int)$material['content_uom_id'] : null;
            $cost = $this->resolve_order_material_live_cost($materialId, $resolvedDivisionId, (int)$requiredUomId);
            return [
                'ok' => true,
                'lines' => [[
                    'source_kind' => 'MATERIAL',
                    'material_id' => $materialId,
                    'component_id' => null,
                    'source_name_snapshot' => trim((string)($material['material_name'] ?? ($extraLine['extra_name'] ?? ''))) ?: null,
                    'required_qty' => $requiredQty,
                    'required_uom_id' => $requiredUomId,
                    'committed_qty' => $requiredQty,
                    'reversed_qty' => 0,
                    'unit_cost_live' => round((float)($cost['unit_cost'] ?? 0), 6),
                    'total_cost_live' => round($requiredQty * (float)($cost['unit_cost'] ?? 0), 6),
                    'cost_source' => (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK'),
                    'resolved_source_division_id' => $resolvedDivisionId > 0 ? $resolvedDivisionId : null,
                    'resolved_source_division_code' => $divisionMeta['code'] ?? null,
                    'resolved_source_division_name' => $divisionMeta['name'] ?? null,
                ] + $line],
            ];
        }

        if ($sourceKind === 'PRODUCT') {
            $sourceProductId = (int)($extraLine['source_product_id'] ?? 0);
            if ($sourceProductId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source produk yang valid.'];
            }

            $sourceProduct = $this->db->select('id, product_name, product_division_id')
                ->from('mst_product')
                ->where('id', $sourceProductId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (empty($sourceProduct['id'])) {
                return ['ok' => false, 'message' => 'Produk sumber untuk extra ' . (string)($extraLine['extra_name'] ?? '-') . ' tidak ditemukan.'];
            }

            $sourceRecipeRows = $this->load_order_product_recipe_rows($sourceProductId);
            if (empty($sourceRecipeRows)) {
                return ['ok' => false, 'message' => 'Produk sumber untuk extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya recipe aktif.'];
            }

            $pseudoLine = [
                'id' => (int)($orderLine['id'] ?? 0),
                'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
                'line_no' => (int)($orderLine['line_no'] ?? 0),
                'product_id' => $sourceProductId,
                'product_name' => (string)($sourceProduct['product_name'] ?? ($extraLine['extra_name'] ?? '')),
                'qty' => $requiredQty,
                'operational_division_id' => $resolvedDivisionId > 0 ? $resolvedDivisionId : (int)($orderLine['operational_division_id'] ?? 0),
            ];

            $resolvedLines = [];
            foreach ($sourceRecipeRows as $recipeRow) {
                $recipeResult = $this->resolve_order_recipe_consumption_line($header, $pseudoLine, $recipeRow);
                if (!($recipeResult['ok'] ?? false)) {
                    return $recipeResult;
                }
                $recipeLine = (array)($recipeResult['line'] ?? []);
                $recipeLine['order_line_id'] = !empty($orderLine['id']) ? (int)$orderLine['id'] : null;
                $recipeLine['order_line_extra_id'] = !empty($extraLine['id']) ? (int)$extraLine['id'] : null;
                $recipeLine['line_type'] = 'EXTRA';
                $recipeLine['product_id'] = (int)($orderLine['product_id'] ?? 0);
                $recipeLine['extra_id'] = (int)($extraLine['extra_id'] ?? 0);
                $recipeLine['source_role'] = 'OPTIONAL';
                $recipeLine['notes'] = 'Resolved dari extra produk "' . (string)($sourceProduct['product_name'] ?? '-') . '" pada order line #' . (int)($orderLine['line_no'] ?? 0);
                $resolvedLines[] = $recipeLine;
            }

            return ['ok' => true, 'lines' => $resolvedLines];
        }

        return ['ok' => true, 'lines' => []];
    }

    private function resolve_order_extra_replacement_consumption_line(array $header, array $orderLine, array $extraLine): array
    {
        $replacementKind = strtoupper(trim((string)($extraLine['replacement_kind'] ?? 'NONE')));
        if ($replacementKind === 'NONE') {
            return ['ok' => true, 'lines' => []];
        }

        $mapped = $extraLine;
        $mapped['source_kind'] = $replacementKind;
        $mapped['source_product_id'] = !empty($extraLine['replacement_product_id']) ? (int)$extraLine['replacement_product_id'] : null;
        $mapped['source_component_id'] = !empty($extraLine['replacement_component_id']) ? (int)$extraLine['replacement_component_id'] : null;
        $mapped['source_material_id'] = !empty($extraLine['replacement_material_id']) ? (int)$extraLine['replacement_material_id'] : null;
        $mapped['source_qty'] = round((float)($extraLine['replacement_qty'] ?? 0), 4);

        $resolved = $this->resolve_order_extra_consumption_line($header, $orderLine, $mapped);
        if (!($resolved['ok'] ?? false)) {
            $message = (string)($resolved['message'] ?? 'Pengganti extra gagal di-resolve untuk stock commit.');
            return ['ok' => false, 'message' => str_replace('source', 'replacement', $message)];
        }

        $lines = [];
        foreach ((array)($resolved['lines'] ?? []) as $line) {
            $line['notes'] = 'Resolved dari pengganti extra order line #' . (int)($orderLine['line_no'] ?? 0);
            $lines[] = $line;
        }

        return ['ok' => true, 'lines' => $lines];
    }

    private function apply_order_extra_removal_to_resolved_lines(array $header, array $orderLine, array $resolvedLines, array $extraLine): array
    {
        $targets = $this->resolve_order_extra_consumption_line($header, $orderLine, $extraLine);
        if (!($targets['ok'] ?? false)) {
            return $targets;
        }

        $targetLines = (array)($targets['lines'] ?? []);
        if (empty($targetLines)) {
            return ['ok' => true, 'lines' => $resolvedLines];
        }

        foreach ($targetLines as $targetLine) {
            $remainingQty = round((float)($targetLine['required_qty'] ?? 0), 4);
            if ($remainingQty <= 0) {
                continue;
            }

            foreach ($resolvedLines as $index => $resolvedLine) {
                if ($remainingQty <= 0) {
                    break;
                }
                if (!$this->stock_commit_lines_match($resolvedLine, $targetLine)) {
                    continue;
                }

                $availableQty = round((float)($resolvedLine['required_qty'] ?? 0), 4);
                if ($availableQty <= 0) {
                    continue;
                }

                $deductQty = min($availableQty, $remainingQty);
                $nextQty = round($availableQty - $deductQty, 4);
                $remainingQty = round($remainingQty - $deductQty, 4);
                $resolvedLines[$index]['required_qty'] = $nextQty;
                $resolvedLines[$index]['committed_qty'] = $nextQty;
                $resolvedLines[$index]['total_cost_live'] = round($nextQty * (float)($resolvedLines[$index]['unit_cost_live'] ?? 0), 6);
                $resolvedLines[$index]['notes'] = $this->merge_note(
                    (string)($resolvedLines[$index]['notes'] ?? ''),
                    'Dikurangi extra ' . (string)($extraLine['extra_name'] ?? '-')
                );
            }

            if ($remainingQty > 0.0001) {
                return [
                    'ok' => false,
                    'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' tidak sinkron dengan recipe produk ' . (string)($orderLine['product_name'] ?? '-') . '. Source ' . (string)($targetLine['source_name_snapshot'] ?? '-') . ' tidak cukup untuk dikurangi.',
                ];
            }
        }

        $resolvedLines = array_values(array_filter($resolvedLines, static function ($line): bool {
            return round((float)($line['required_qty'] ?? 0), 4) > 0;
        }));

        return ['ok' => true, 'lines' => $resolvedLines];
    }

    private function stock_commit_lines_match(array $left, array $right): bool
    {
        $leftKind = strtoupper(trim((string)($left['source_kind'] ?? 'NONE')));
        $rightKind = strtoupper(trim((string)($right['source_kind'] ?? 'NONE')));
        if ($leftKind === '' || $rightKind === '' || $leftKind !== $rightKind) {
            return false;
        }

        if ($leftKind === 'COMPONENT') {
            return (int)($left['component_id'] ?? 0) > 0
                && (int)($left['component_id'] ?? 0) === (int)($right['component_id'] ?? 0);
        }

        if ($leftKind === 'MATERIAL') {
            return (int)($left['material_id'] ?? 0) > 0
                && (int)($left['material_id'] ?? 0) === (int)($right['material_id'] ?? 0);
        }

        return false;
    }

    private function resolve_direct_print_line_ids_for_snapshot(int $orderId, int $snapshotId = 0): ?array
    {
        if (
            $orderId <= 0
            || !$this->db->table_exists('pos_stock_commit')
            || !$this->db->table_exists('pos_stock_commit_line')
        ) {
            return null;
        }

        $commitDb = $this->db->from('pos_stock_commit')->where('order_id', $orderId);
        if ($snapshotId > 0) {
            $commitDb->where('id', $snapshotId);
        } else {
            $commitDb->order_by('id', 'DESC');
        }
        $commit = $commitDb->limit(1)->get()->row_array();
        if (!$commit) {
            return $snapshotId > 0 ? [] : null;
        }

        $rows = $this->db->select('order_line_id')
            ->from('pos_stock_commit_line')
            ->where('commit_id', (int)($commit['id'] ?? 0))
            ->where('order_line_id IS NOT NULL', null, false)
            ->get()
            ->result_array();

        $lineIds = [];
        foreach ($rows as $row) {
            $lineId = (int)($row['order_line_id'] ?? 0);
            if ($lineId > 0) {
                $lineIds[$lineId] = $lineId;
            }
        }

        return array_values($lineIds);
    }

    private function merge_note(string $base, string $append): ?string
    {
        $base = trim($base);
        $append = trim($append);
        if ($append === '') {
            return $base !== '' ? $base : null;
        }
        if ($base === '') {
            return $append;
        }
        return $base . ' | ' . $append;
    }

    private function resolve_order_operational_division_meta(int $divisionId): array
    {
        if ($divisionId <= 0) {
            return [];
        }

        return $this->db->select('id, code, name')
            ->from('mst_operational_division')
            ->where('id', $divisionId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];
    }

    private function resolve_order_material_live_cost(int $materialId, int $divisionId, int $uomId = 0): array
    {
        if ($materialId <= 0) {
            return ['unit_cost' => 0.0, 'cost_source' => 'STANDARD_FALLBACK'];
        }

        $material = $this->db->select('id, hpp_standard')
            ->from('mst_material')
            ->where('id', $materialId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $fallback = round((float)($material['hpp_standard'] ?? 0), 6);
        $lotLive = $this->resolve_order_material_active_lot_cost($materialId, $divisionId, $uomId);
        if (($lotLive['unit_cost'] ?? 0) > 0) {
            return ['unit_cost' => round((float)$lotLive['unit_cost'], 6), 'cost_source' => 'FIFO_ACTIVE_LOT'];
        }
        if ($divisionId > 0 && $this->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['division_id', 'destination_type', 'identity_key'])
                ->get_compiled_select();

            $select = "
                COALESCE(
                    CASE WHEN ABS(SUM(s.closing_qty_content)) > 0.000001
                        THEN SUM(s.closing_qty_content * s.avg_cost_per_content) / SUM(s.closing_qty_content)
                        ELSE MAX(s.avg_cost_per_content)
                    END,
                    0
                ) AS avg_cost_per_content
            ";
            $this->db->select($select, false)
                ->from('inv_division_monthly_stock s')
                ->join('mst_item mi', 'mi.id = s.item_id', 'left')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.division_id', $divisionId)
                ->group_start()
                    ->where('s.material_id', $materialId)
                    ->or_group_start()
                        ->where('s.item_id IS NOT NULL', null, false)
                        ->where('mi.material_id', $materialId)
                    ->group_end()
                ->group_end();
            if ($uomId > 0) {
                $this->db->where('s.content_uom_id', $uomId);
            }
            $liveRow = $this->db->get()->row_array() ?: [];
            $live = round((float)($liveRow['avg_cost_per_content'] ?? 0), 6);
            if ($live > 0) {
                return ['unit_cost' => $live, 'cost_source' => 'LAST_LIVE'];
            }
        }

        $catalog = $this->resolve_order_material_catalog_unit_cost($materialId, $uomId);
        if (($catalog['unit_cost'] ?? 0) > 0) {
            return ['unit_cost' => round((float)$catalog['unit_cost'], 6), 'cost_source' => 'CATALOG_FALLBACK'];
        }

        return ['unit_cost' => $fallback, 'cost_source' => 'STANDARD_FALLBACK'];
    }

    /**
     * Use the same catalog fallback as the POS stock-live cache when no lot
     * and no last live balance exist. This keeps commit audit cost consistent
     * with the quick HPP snapshot saved on the order.
     */
    private function resolve_order_material_catalog_unit_cost(int $materialId, int $contentUomId = 0): array
    {
        if ($materialId <= 0 || !$this->db->table_exists('mst_purchase_catalog')) {
            return ['unit_cost' => 0.0];
        }

        $select = "
            COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), 0) AS buy_price,
            CASE
                WHEN COALESCE(c.conversion_factor_to_content, 0) > 0 THEN c.conversion_factor_to_content
                WHEN COALESCE(c.content_per_buy, 0) > 0 THEN c.content_per_buy
                ELSE 1
            END AS content_per_buy
        ";
        $db = $this->db->select($select, false)
            ->from('mst_purchase_catalog c')
            ->join('mst_item i', 'i.id = c.item_id', 'left')
            ->where('COALESCE(c.is_active, 1) = 1', null, false)
            ->group_start()
                ->where('c.material_id', $materialId)
                ->or_where('i.material_id', $materialId)
            ->group_end()
            ->where('COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), 0) > 0', null, false);
        if ($contentUomId > 0) {
            $db->where('c.content_uom_id', $contentUomId);
        }

        $row = $db
            ->order_by('c.last_purchase_date IS NULL', 'ASC', false)
            ->order_by('c.last_purchase_date', 'DESC')
            ->order_by('c.updated_at', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: [];
        $buyPrice = round((float)($row['buy_price'] ?? 0), 6);
        $contentPerBuy = round((float)($row['content_per_buy'] ?? 0), 6);
        if ($buyPrice <= 0 || $contentPerBuy <= 0) {
            return ['unit_cost' => 0.0];
        }

        return ['unit_cost' => round($buyPrice / $contentPerBuy, 6)];
    }

    private function resolve_order_component_live_cost(int $componentId, int $divisionId = 0): array
    {
        if ($componentId <= 0) {
            return ['unit_cost' => 0.0, 'cost_source' => 'STANDARD_FALLBACK'];
        }

        $component = $this->db->select('id, hpp_standard')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $fallback = round((float)($component['hpp_standard'] ?? 0), 6);
        $lotLive = $this->resolve_order_component_active_lot_cost($componentId, $divisionId);
        if (($lotLive['unit_cost'] ?? 0) > 0) {
            return ['unit_cost' => round((float)$lotLive['unit_cost'], 6), 'cost_source' => 'FIFO_ACTIVE_LOT'];
        }
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key', false)
                ->from('inv_component_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['location_type', 'division_id', 'component_id', 'uom_id'])
                ->get_compiled_select();

            $select = "
                COALESCE(
                    CASE WHEN ABS(SUM(s.closing_qty)) > 0.000001
                        THEN SUM(s.closing_qty * s.avg_cost) / SUM(s.closing_qty)
                        ELSE MAX(s.avg_cost)
                    END,
                    0
                ) AS avg_cost
            ";
            $this->db->select($select, false)
                ->from('inv_component_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.component_id', $componentId);
            if ($divisionId > 0) {
                $this->db->where('s.division_id', $divisionId);
            }
            $liveRow = $this->db->get()->row_array() ?: [];
            $live = round((float)($liveRow['avg_cost'] ?? 0), 6);
            if ($live > 0) {
                return ['unit_cost' => $live, 'cost_source' => 'LAST_LIVE'];
            }
        }

        return ['unit_cost' => $fallback, 'cost_source' => 'STANDARD_FALLBACK'];
    }

    private function resolve_order_material_active_lot_cost(int $materialId, int $divisionId, int $uomId = 0): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || !$this->db->table_exists('inv_material_fifo_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $divisionMeta = $this->resolve_order_operational_division_meta($divisionId);
        $preferredDestination = strtoupper(trim((string)($divisionMeta['code'] ?? '')));
        if (in_array($preferredDestination, ['BAR', 'KITCHEN', 'ROASTERY'], true)) {
            $sql = "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                    FROM inv_material_fifo_lot
                    WHERE location_scope = 'DIVISION'
                      AND division_id = ?
                      AND destination_type = ?
                      AND COALESCE(material_id, 0) = ?
                      AND qty_balance > 0";
            $params = [$divisionId, $preferredDestination, $materialId];
            if ($uomId > 0) {
                $sql .= " AND content_uom_id = ? ";
                $params[] = $uomId;
            }
            $sql .= " ORDER BY receipt_date ASC, id ASC LIMIT 1";
            $frontPreferred = $this->db->query($sql, $params)->row_array();
            if (!empty($frontPreferred)) {
                $qtySql = "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                           FROM inv_material_fifo_lot
                           WHERE location_scope = 'DIVISION'
                             AND division_id = ?
                             AND destination_type = ?
                             AND COALESCE(material_id, 0) = ?
                             AND qty_balance > 0";
                $qtyParams = [$divisionId, $preferredDestination, $materialId];
                if ($uomId > 0) {
                    $qtySql .= " AND content_uom_id = ? ";
                    $qtyParams[] = $uomId;
                }
                $qtyPreferred = $this->db->query($qtySql, $qtyParams)->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        $sql = "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                FROM inv_material_fifo_lot
                WHERE location_scope = 'DIVISION'
                  AND division_id = ?
                  AND COALESCE(material_id, 0) = ?
                  AND qty_balance > 0";
        $params = [$divisionId, $materialId];
        if ($uomId > 0) {
            $sql .= " AND content_uom_id = ? ";
            $params[] = $uomId;
        }
        $sql .= " ORDER BY receipt_date ASC, id ASC LIMIT 1";
        $front = $this->db->query($sql, $params)->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qtySql = "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                   FROM inv_material_fifo_lot
                   WHERE location_scope = 'DIVISION'
                     AND division_id = ?
                     AND COALESCE(material_id, 0) = ?
                     AND qty_balance > 0";
        $qtyParams = [$divisionId, $materialId];
        if ($uomId > 0) {
            $qtySql .= " AND content_uom_id = ? ";
            $qtyParams[] = $uomId;
        }
        $qty = $this->db->query($qtySql, $qtyParams)->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function resolve_order_component_active_lot_cost(int $componentId, int $divisionId = 0): array
    {
        if ($componentId <= 0 || !$this->db->table_exists('inv_component_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $divisionMeta = $divisionId > 0 ? $this->resolve_order_operational_division_meta($divisionId) : [];
        $preferredLocation = strtoupper(trim((string)($divisionMeta['code'] ?? '')));
        if ($divisionId > 0 && in_array($preferredLocation, ['BAR', 'KITCHEN', 'ROASTERY'], true)) {
            $frontPreferred = $this->db->query(
                "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                 FROM inv_component_lot
                 WHERE component_id = ?
                   AND division_id = ?
                   AND location_type = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$componentId, $divisionId, $preferredLocation]
            )->row_array();
            if (!empty($frontPreferred)) {
                $qtyPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_component_lot
                     WHERE component_id = ?
                       AND division_id = ?
                       AND location_type = ?
                       AND qty_balance > 0",
                    [$componentId, $divisionId, $preferredLocation]
                )->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        $sql = "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                FROM inv_component_lot
                WHERE component_id = ?
                  AND qty_balance > 0";
        $params = [$componentId];
        if ($divisionId > 0) {
            $sql .= " AND division_id = ? ";
            $params[] = $divisionId;
        }
        $sql .= " ORDER BY receipt_date ASC, id ASC LIMIT 1";
        $front = $this->db->query($sql, $params)->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qtySql = "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                   FROM inv_component_lot
                   WHERE component_id = ?
                     AND qty_balance > 0";
        $qtyParams = [$componentId];
        if ($divisionId > 0) {
            $qtySql .= " AND division_id = ? ";
            $qtyParams[] = $divisionId;
        }
        $qty = $this->db->query($qtySql, $qtyParams)->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function normalize_stock_source_role(string $role): string
    {
        $role = strtoupper(trim($role));
        if (in_array($role, ['GARNISH', 'SAUCE', 'TOPPING'], true)) {
            return 'COMPLEMENT';
        }
        if (in_array($role, ['SUPPORT', 'OTHER'], true)) {
            return 'SUPPORT';
        }
        if ($role === 'OPTIONAL') {
            return 'OPTIONAL';
        }
        return 'MAIN';
    }

    private function resolve_product_default_operational_division(array $product): ?int
    {
        $divisionId = (int)($product['default_operational_division_id'] ?? 0);

        // This is the KOT station explicitly selected for the product. Recipe
        // sources may span several divisions and must not reroute the KOT.
        if ($divisionId > 0) {
            return $divisionId;
        }

        $productId = (int)($product['id'] ?? 0);
        $recipeDivisionId = 0;

        if ($productId > 0 && $this->db->table_exists('mst_product_recipe')) {
            $recipeRow = $this->db->select('source_division_id')
                ->from('mst_product_recipe')
                ->where('product_id', $productId)
                ->where('source_division_id IS NOT NULL', null, false)
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array();
            $recipeDivisionId = (int)($recipeRow['source_division_id'] ?? 0);
        }

        if ($recipeDivisionId > 0) {
            return $recipeDivisionId;
        }

        $divisionKey = strtoupper(trim((string)($product['product_division_code'] ?? ($product['product_division_name'] ?? ''))));
        $preferred = '';
        if ($divisionKey === 'BEVERAGE') {
            $preferred = 'BAR';
        } elseif ($divisionKey === 'FOOD') {
            $preferred = 'KITCHEN';
        } elseif ($divisionKey === 'ROASTERY') {
            $preferred = 'ROASTERY';
        }
        if ($preferred === '') {
            return null;
        }

        $row = $this->db->select('id')
            ->from('mst_operational_division')
            ->where('UPPER(name) =', $preferred)
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row['id']) ? (int)$row['id'] : null;
    }

    private function insert_order_state_log(int $orderId, ?string $fromStatus, string $toStatus, string $eventCode, int $actorEmployeeId, ?string $notes = null): void
    {
        $this->db->insert('pos_order_state_log', [
            'order_id' => $orderId,
            'from_status' => $fromStatus !== '' ? $fromStatus : null,
            'to_status' => $toStatus,
            'event_code' => $eventCode,
            'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'notes' => $notes,
        ]);
    }

    private function snapshot_line_key(array $line): string
    {
        return 'commit_line:' . (int)($line['id'] ?? 0);
    }

    private function build_order_reversal_plan(int $orderId, array $order, array $processByOrderLine): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk reversal.'];
        }

        $snapshotHeaders = $this->db->select('id, order_id, commit_no, commit_status, process_state_snapshot, created_at')
            ->from('pos_stock_commit')
            ->where('order_id', $orderId)
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();
        if (empty($snapshotHeaders)) {
            return ['ok' => false, 'message' => 'Snapshot stock commit belum tersedia.'];
        }

        // All order lines with committed stock need to be included in the reversal plan,
        // regardless of whether they were physically processed. The physical process status
        // only determines the POLICY (return-to-stock vs adjustment), not plan inclusion.
        $activeOrderLineIds = [];
        $activeExtraLineIds = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $orderLineId = (int)($line['id'] ?? 0);
            if ($orderLineId > 0) {
                $activeOrderLineIds[$orderLineId] = true;
            }
            foreach ((array)($line['extras'] ?? []) as $extraLine) {
                $extraLineId = (int)($extraLine['id'] ?? 0);
                if ($extraLineId > 0) {
                    $activeExtraLineIds[$extraLineId] = true;
                }
            }
        }

        $mergedPlanLines = [];
        foreach ($snapshotHeaders as $snapshotHeader) {
            $commitId = (int)($snapshotHeader['id'] ?? 0);
            if ($commitId <= 0) {
                continue;
            }

            $snapshotLines = $this->db->select('id, commit_id, line_type, order_line_id, order_line_extra_id')
                ->from('pos_stock_commit_line')
                ->where('commit_id', $commitId)
                ->order_by('line_no', 'ASC')
                ->get()
                ->result_array();

            $processedMap = [];
            foreach ($snapshotLines as $snapshotLine) {
                $orderLineId = (int)($snapshotLine['order_line_id'] ?? 0);
                $state = $processByOrderLine[$orderLineId] ?? 'NOT_PROCESSED';
                $processedMap[$this->snapshot_line_key($snapshotLine)] = $state === 'NOT_PROCESSED' ? 'NONE' : 'FULL';
            }

            $plan = $this->posstockcommitservice->build_reversal_plan($commitId, $processedMap);
            if (!($plan['ok'] ?? false)) {
                return $plan;
            }

            foreach ((array)($plan['lines'] ?? []) as $planLine) {
                $orderLineId = (int)($planLine['order_line_id'] ?? 0);
                $orderLineExtraId = (int)($planLine['order_line_extra_id'] ?? 0);
                $isRelevant = $orderLineExtraId > 0
                    ? isset($activeExtraLineIds[$orderLineExtraId])
                    : isset($activeOrderLineIds[$orderLineId]);
                if (!$isRelevant) {
                    continue;
                }

                if (round((float)($planLine['remaining_qty'] ?? 0), 4) <= 0) {
                    continue;
                }

                $mergedPlanLines[] = $planLine + [
                    'commit_id' => $commitId,
                    'commit_no' => (string)($snapshotHeader['commit_no'] ?? ''),
                    'commit_status' => (string)($snapshotHeader['commit_status'] ?? ''),
                ];
            }
        }

        return [
            'ok' => true,
            'headers' => $snapshotHeaders,
            'lines' => $mergedPlanLines,
        ];
    }

    private function reverse_order_commit_decisions(array $decisions, int $actorEmployeeId, string $note, array $extraMeta = []): array
    {
        $groupedDecisions = [];
        foreach ($decisions as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $commitId = (int)($decision['commit_id'] ?? 0);
            if ($commitId <= 0) {
                continue;
            }
            $decisionPayload = $decision;
            unset($decisionPayload['commit_id']);
            $groupedDecisions[$commitId][] = $decisionPayload;
        }

        if (empty($groupedDecisions)) {
            return ['ok' => true, 'affected_lines' => 0, 'adjustment_doc_count' => 0];
        }

        $affectedLines = 0;
        foreach ($groupedDecisions as $commitId => $commitDecisions) {
            $reverse = $this->posorderstockservice->reverse_commit_snapshot($commitId, $commitDecisions, [
                'actor_employee_id' => $actorEmployeeId,
                'notes' => $note,
            ] + $extraMeta);
            if (!($reverse['ok'] ?? false)) {
                return $reverse;
            }
            $affectedLines += (int)($reverse['affected_lines'] ?? 0);
        }

        return ['ok' => true, 'affected_lines' => $affectedLines];
    }

    private function build_reversal_selection(array $order, array $planLines, array $requestedLines, array $options = []): array
    {
        $orderLines = [];
        $headerCommitStatus = strtoupper(trim((string)($order['header']['stock_commit_status'] ?? '')));
        foreach ((array)($order['lines'] ?? []) as $line) {
            $orderLines[(int)($line['id'] ?? 0)] = $line;
        }

        $requestedMap = [];
        $requestedExtraMap = [];
        foreach ($requestedLines as $requestedLine) {
            if (!is_array($requestedLine)) {
                continue;
            }
            $orderLineId = (int)($requestedLine['order_line_id'] ?? 0);
            if ($orderLineId > 0) {
                $requestedMap[$orderLineId] = $requestedLine;
            }
            foreach ((array)($requestedLine['extras'] ?? []) as $requestedExtra) {
                if (!is_array($requestedExtra)) {
                    continue;
                }
                $orderLineExtraId = (int)($requestedExtra['order_line_extra_id'] ?? 0);
                if ($orderLineId > 0 && $orderLineExtraId > 0) {
                    if (!isset($requestedExtraMap[$orderLineId])) {
                        $requestedExtraMap[$orderLineId] = [];
                    }
                    $requestedExtraMap[$orderLineId][$orderLineExtraId] = $requestedExtra;
                }
            }
        }
        if (empty($requestedMap) && empty($requestedExtraMap)) {
            foreach ($orderLines as $orderLineId => $line) {
                $requestedMap[$orderLineId] = ['order_line_id' => $orderLineId, 'qty' => (float)($line['qty'] ?? 0)];
            }
        }
        $planByOrderLine = [];
        $planByExtraLine = [];
        foreach ($planLines as $planLine) {
            $orderLineId = (int)($planLine['order_line_id'] ?? 0);
            if ($orderLineId <= 0) {
                continue;
            }
            if (!isset($planByOrderLine[$orderLineId])) {
                $planByOrderLine[$orderLineId] = [];
            }
            $planByOrderLine[$orderLineId][] = $planLine;
            $orderLineExtraId = (int)($planLine['order_line_extra_id'] ?? 0);
            if ($orderLineExtraId > 0) {
                if (!isset($planByExtraLine[$orderLineExtraId])) {
                    $planByExtraLine[$orderLineExtraId] = [];
                }
                $planByExtraLine[$orderLineExtraId][] = $planLine;
            }
        }

        $productLines = [];
        $extraLines = [];
        $decisions = [];
        $totalQty = 0.0;
        $totalAmount = 0.0;
        $returnToStock = false;
        $hasProcessed = false;
        $isFull = true;
        if (count($requestedMap) < count($orderLines)) {
            $isFull = false;
        }

        $appendedExtraIds = [];

        foreach ($orderLines as $orderLineId => $line) {
            $requestedLine = $requestedMap[$orderLineId] ?? [];
            $requestedExtras = (array)($requestedExtraMap[$orderLineId] ?? []);
            // An entry may intentionally contain only extras. Presence of order_line_id
            // alone must not promote that selection to a full product reversal.
            $hasRequestedProduct = !empty($requestedLine)
                && round((float)($requestedLine['qty'] ?? 0), 4) > 0;
            $hasRequestedExtras = !empty($requestedExtras);
            if (!$hasRequestedProduct && !$hasRequestedExtras) {
                continue;
            }
            if (empty($orderLines[$orderLineId])) {
                continue;
            }
            $qtyBefore = round((float)($line['qty'] ?? 0), 4);
            $defaultLineProcessStatus = $this->effective_reversal_process_status($headerCommitStatus, $line);
            $fallbackProcessedState = strtoupper(trim((string)($options['default_processed_state'] ?? '')));
            if ($fallbackProcessedState === '') {
                $fallbackProcessedState = $defaultLineProcessStatus;
            }
            $processedState = strtoupper(trim((string)($requestedLine['processed_state'] ?? $fallbackProcessedState)));
            if ($processedState === '') {
                $processedState = $defaultLineProcessStatus;
            }
            $isProcessed = in_array($processedState, ['PROCESSED', 'SERVED'], true);
            $lineReturnToStock = !empty($requestedLine['return_to_stock']) || !empty($options['default_return_to_stock']);

            if ($hasRequestedProduct) {
                $qtySelected = round((float)($requestedLine['qty'] ?? $qtyBefore), 4);
                if ($qtyBefore <= 0 || $qtySelected <= 0) {
                    continue;
                }
                $qtySelected = min($qtyBefore, $qtySelected);
                $ratio = $qtyBefore > 0 ? round($qtySelected / $qtyBefore, 8) : 0.0;
                $lineStatusAfter = $qtySelected >= $qtyBefore ? 'VOID' : 'OPEN';

                $productLines[] = [
                    'order_line_id' => $orderLineId,
                    'product_id' => (int)($line['product_id'] ?? 0),
                    'line_no_snapshot' => (int)($line['line_no'] ?? 0),
                    'item_name_snapshot' => (string)($line['product_name'] ?? ''),
                    'qty_before' => $qtyBefore,
                    'qty_void' => $qtySelected,
                    'qty_after' => round($qtyBefore - $qtySelected, 4),
                    'unit_price' => round((float)($line['unit_price'] ?? 0), 2),
                    'subtotal_void' => round($qtySelected * (float)($line['unit_price'] ?? 0), 2),
                    'hpp_live_snapshot' => round((float)($line['hpp_live_snapshot'] ?? 0), 6),
                    'line_process_state' => $isProcessed ? 'PROCESSED' : 'NOT_PROCESSED',
                    'line_status_after' => $lineStatusAfter,
                    'notes' => $this->nullable_text($requestedLine['notes'] ?? ''),
                ];
                $totalQty += $qtySelected;
                $totalAmount += round($qtySelected * (float)($line['unit_price'] ?? 0), 2);
                $returnToStock = $returnToStock || $lineReturnToStock;
                $hasProcessed = $hasProcessed || $isProcessed;
                if ($qtySelected + 0.0001 < $qtyBefore) {
                    $isFull = false;
                }

                foreach ((array)($line['extras'] ?? []) as $extra) {
                    $extraQty = round((float)($extra['qty'] ?? 0), 4);
                    $extraAffected = round($extraQty * $ratio, 4);
                    if ($extraAffected <= 0) {
                        continue;
                    }
                    $extraId = (int)($extra['id'] ?? 0);
                    $appendedExtraIds[$extraId] = true;
                    $extraLines[] = [
                        'order_line_id' => $orderLineId,
                        'order_line_extra_id' => $extraId,
                        'extra_id' => (int)($extra['extra_id'] ?? 0),
                        'extra_name_snapshot' => (string)($extra['extra_name'] ?? ''),
                        'qty_per_unit' => $extraQty,
                        'line_qty_affected' => $extraAffected,
                        'unit_price' => round((float)($extra['unit_price'] ?? 0), 2),
                        'subtotal_void' => round($extraAffected * (float)($extra['unit_price'] ?? 0), 2),
                        'status_after' => $lineStatusAfter,
                        'cost_amount_snapshot' => round((float)($extra['cost_amount_snapshot'] ?? 0), 6),
                    ];
                    $totalAmount += round($extraAffected * (float)($extra['unit_price'] ?? 0), 2);
                }

                foreach ((array)($planByOrderLine[$orderLineId] ?? []) as $planLine) {
                    $remainingQty = round((float)($planLine['remaining_qty'] ?? 0), 4);
                    if ($remainingQty <= 0) {
                        continue;
                    }
                    $reverseQty = round($remainingQty * $ratio, 4);
                    if ($reverseQty <= 0) {
                        continue;
                    }
                    $decisions[] = [
                        'commit_id' => (int)($planLine['commit_id'] ?? 0),
                        'line_key' => $this->snapshot_line_key($planLine),
                        'line_id' => (int)($planLine['id'] ?? 0),
                        'return_policy' => $lineReturnToStock ? 'RETURN_TO_STOCK' : 'ADJUSTMENT_ONLY',
                        'reverse_qty' => $reverseQty,
                        'notes' => (string)($requestedLine['notes'] ?? ''),
                    ];
                }
                continue;
            }

            $isFull = false;
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $extraId = (int)($extra['id'] ?? 0);
                $requestedExtra = (array)($requestedExtras[$extraId] ?? []);
                if (empty($requestedExtra)) {
                    continue;
                }
                $extraQty = round((float)($extra['qty'] ?? 0), 4);
                $extraSelected = round((float)($requestedExtra['qty'] ?? $extraQty), 4);
                if ($extraQty <= 0 || $extraSelected <= 0) {
                    continue;
                }
                $extraSelected = min($extraQty, $extraSelected);
                $extraStatusAfter = $extraSelected >= $extraQty ? 'VOID' : 'OPEN';
                $appendedExtraIds[$extraId] = true;
                $extraLines[] = [
                    'order_line_id' => $orderLineId,
                    'order_line_extra_id' => $extraId,
                    'extra_id' => (int)($extra['extra_id'] ?? 0),
                    'extra_name_snapshot' => (string)($extra['extra_name'] ?? ''),
                    'qty_per_unit' => $extraQty,
                    'line_qty_affected' => $extraSelected,
                    'unit_price' => round((float)($extra['unit_price'] ?? 0), 2),
                    'subtotal_void' => round($extraSelected * (float)($extra['unit_price'] ?? 0), 2),
                    'status_after' => $extraStatusAfter,
                    'cost_amount_snapshot' => round((float)($extra['cost_amount_snapshot'] ?? 0), 6),
                ];
                $totalAmount += round($extraSelected * (float)($extra['unit_price'] ?? 0), 2);
                $returnToStock = $returnToStock || $lineReturnToStock;
                $hasProcessed = $hasProcessed || $isProcessed;

                foreach ((array)($planByExtraLine[$extraId] ?? []) as $planLine) {
                    $remainingQty = round((float)($planLine['remaining_qty'] ?? 0), 4);
                    if ($remainingQty <= 0) {
                        continue;
                    }
                    $ratio = $extraQty > 0 ? round($extraSelected / $extraQty, 8) : 0.0;
                    $reverseQty = round($remainingQty * $ratio, 4);
                    if ($reverseQty <= 0) {
                        continue;
                    }
                    $decisions[] = [
                        'commit_id' => (int)($planLine['commit_id'] ?? 0),
                        'line_key' => $this->snapshot_line_key($planLine),
                        'line_id' => (int)($planLine['id'] ?? 0),
                        'return_policy' => $lineReturnToStock ? 'RETURN_TO_STOCK' : 'ADJUSTMENT_ONLY',
                        'reverse_qty' => $reverseQty,
                        'notes' => (string)($requestedExtra['notes'] ?? ($requestedLine['notes'] ?? '')),
                    ];
                }
            }
        }

        return [
            'product_lines' => $productLines,
            'extra_lines' => $extraLines,
            'decisions' => $decisions,
            'total_qty' => round($totalQty, 4),
            'total_amount' => round($totalAmount, 2),
            'return_to_stock' => $returnToStock,
            'has_processed' => $hasProcessed,
            'is_full' => $isFull,
        ];
    }

    private function effective_reversal_process_status(string $headerCommitStatus, array $line): string
    {
        $lineStatus = strtoupper(trim((string)($line['process_status'] ?? 'NOT_PROCESSED')));
        if ($lineStatus !== '' && $lineStatus !== 'NOT_PROCESSED') {
            return $lineStatus;
        }

        // A POSTED stock-commit means the stock was deducted, not that the item was physically
        // served. Return the actual physical process status so NOT_PROCESSED items can still
        // be returned to stock on void/refund.
        return 'NOT_PROCESSED';
    }

    private function apply_order_line_reversal_updates(int $orderId, array $productLines, array $extraLines, string $mode): array
    {
        $extraQtyReduction = [];
        foreach ($extraLines as $extraLine) {
            $orderLineExtraId = (int)($extraLine['order_line_extra_id'] ?? 0);
            if ($orderLineExtraId <= 0) {
                continue;
            }
            if (!isset($extraQtyReduction[$orderLineExtraId])) {
                $extraQtyReduction[$orderLineExtraId] = 0.0;
            }
            $extraQtyReduction[$orderLineExtraId] += round((float)($extraLine['line_qty_affected'] ?? 0), 4);
        }

        foreach ($productLines as $line) {
            $orderLineId = (int)($line['order_line_id'] ?? 0);
            if ($orderLineId <= 0) {
                continue;
            }
            $qtyAfter = max(0, round((float)($line['qty_after'] ?? 0), 4));
            $statusAfter = (string)($line['line_status_after'] ?? 'OPEN');
            if ($mode === 'REFUND' && $statusAfter === 'VOID') {
                $statusAfter = 'REFUNDED_FULL';
            } elseif ($mode === 'REFUND' && $statusAfter === 'OPEN') {
                $statusAfter = 'REFUNDED_PARTIAL';
            }
            $updatePayload = [
                'qty' => $qtyAfter,
                'net_amount' => round($qtyAfter * (float)($line['unit_price'] ?? 0), 2),
                'line_status' => $statusAfter,
            ];
            if ($this->db->field_exists('cogs_amount', 'pos_order_line')) {
                $updatePayload['cogs_amount'] = round($qtyAfter * (float)($line['hpp_live_snapshot'] ?? 0), 2);
            }
            $this->db->where('id', $orderLineId)->update('pos_order_line', $updatePayload);
        }

        $extraIds = array_keys($extraQtyReduction);
        if (!empty($extraIds)) {
            $extraRows = $this->db->select('id, qty, unit_price')
                ->from('pos_order_line_extra')
                ->where_in('id', $extraIds)
                ->get()
                ->result_array();
            $extraMap = [];
            foreach ($extraRows as $extraRow) {
                $extraMap[(int)($extraRow['id'] ?? 0)] = $extraRow;
            }

            foreach ($extraQtyReduction as $orderLineExtraId => $qtyReduced) {
                $current = (array)($extraMap[$orderLineExtraId] ?? []);
                if (empty($current)) {
                    continue;
                }
                $qtyAfter = max(0, round((float)($current['qty'] ?? 0) - (float)$qtyReduced, 4));
                $this->db->where('id', $orderLineExtraId)->update('pos_order_line_extra', [
                    'qty' => $qtyAfter,
                    'net_amount' => round($qtyAfter * (float)($current['unit_price'] ?? 0), 2),
                ]);
            }
        }

        return $this->recalculate_order_header_after_reversal($orderId);
    }

    private function recalculate_order_header_after_reversal(int $orderId): array
    {
        $base = [
            'subtotal_amount' => 0.0,
            'grand_total' => 0.0,
            'has_active_lines' => false,
        ];
        if ($orderId <= 0) {
            return $base;
        }

        $header = $this->db->select('subtotal_amount, discount_amount, promo_amount, voucher_amount, point_redeem_amount, compliment_amount, tax_amount, service_amount, rounding_amount')
            ->from('pos_order')
            ->where('id', $orderId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $productAgg = $this->db->select("COALESCE(SUM(net_amount), 0) AS product_total, COUNT(CASE WHEN qty > 0 AND line_status NOT IN ('VOID', 'REFUNDED_FULL') THEN 1 END) AS active_line_count", false)
            ->from('pos_order_line')
            ->where('order_id', $orderId)
            ->get()
            ->row_array() ?: [];

        $extraAgg = $this->db->select('COALESCE(SUM(net_amount), 0) AS extra_total', false)
            ->from('pos_order_line_extra')
            ->where('order_id', $orderId)
            ->where('qty >', 0)
            ->get()
            ->row_array() ?: [];

        $subtotal = round((float)($productAgg['product_total'] ?? 0) + (float)($extraAgg['extra_total'] ?? 0), 2);
        $previousSubtotal = round(max(0, (float)($header['subtotal_amount'] ?? 0)), 2);
        $remainingRatio = $previousSubtotal > 0.009
            ? min(1, max(0, $subtotal / $previousSubtotal))
            : ($subtotal > 0.009 ? 1.0 : 0.0);
        $reductionFields = ['discount_amount', 'promo_amount', 'voucher_amount', 'point_redeem_amount', 'compliment_amount'];
        $headerUpdate = ['subtotal_amount' => $subtotal];
        $remainingReduction = 0.0;
        foreach ($reductionFields as $field) {
            $value = round(max(0, (float)($header[$field] ?? 0)) * $remainingRatio, 2);
            $headerUpdate[$field] = $value;
            $remainingReduction += $value;
        }
        $taxAmount = round((float)($header['tax_amount'] ?? 0), 2);
        $serviceAmount = round((float)($header['service_amount'] ?? 0), 2);
        $roundingAmount = round((float)($header['rounding_amount'] ?? 0), 2);
        $grandTotal = round(max(0, $subtotal - $remainingReduction + $taxAmount + $serviceAmount + $roundingAmount), 2);
        $headerUpdate['grand_total'] = $grandTotal;
        $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $headerUpdate));

        return [
            'subtotal_amount' => $subtotal,
            'grand_total' => $grandTotal,
            'has_active_lines' => (int)($productAgg['active_line_count'] ?? 0) > 0,
        ];
    }

    private function normalize_pos_adjustment_mode(string $mode): string
    {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, ['NONE', 'AUTO_WASTE', 'AUTO_SPOIL', 'AUTO_ADJUSTMENT'], true)) {
            return 'NONE';
        }
        return $mode;
    }

    private function calculate_shift_summary(int $shiftId): array
    {
        $base = [
            'total_order_count' => 0,
            'total_gross_sales' => 0,
            'total_discount' => 0,
            'total_promo' => 0,
            'total_net_sales' => 0,
            'total_cash_sales' => 0,
            'total_non_cash_sales' => 0,
            'total_deposit_receipts' => 0,
            'total_cash_deposit_receipts' => 0,
            'total_refund' => 0,
            'total_void' => 0,
        ];
        if ($shiftId <= 0) {
            return $base;
        }

        $orderAgg = $this->db->select('
                COUNT(*) AS total_order_count,
                COALESCE(SUM(subtotal_amount),0) AS total_gross_sales,
                COALESCE(SUM(discount_amount),0) AS total_discount,
                COALESCE(SUM(promo_amount),0) AS total_promo,
                COALESCE(SUM(grand_total),0) AS total_net_sales
            ', false)
            ->from('pos_order')
            ->where('shift_id', $shiftId)
            ->where_not_in('status', ['DRAFT', 'PENDING'])
            ->get()
            ->row_array() ?: [];

        $cashAgg = $this->db->select("
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) = 'CASH' THEN pl.amount ELSE 0 END),0) AS total_cash_sales,
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) <> 'CASH' THEN pl.amount ELSE 0 END),0) AS total_non_cash_sales
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->where('p.payment_type', 'FINAL')
            ->get()
            ->row_array() ?: [];

        $depositAgg = $this->db->select('COALESCE(SUM(net_amount),0) AS total_deposit_receipts', false)
            ->from('pos_payment')
            ->where('shift_id', $shiftId)
            ->where('payment_status', 'PAID')
            ->where('payment_type', 'DEPOSIT')
            ->get()
            ->row_array() ?: [];

        $depositCashAgg = $this->db->select("
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) = 'CASH' THEN pl.amount ELSE 0 END),0) AS total_cash_deposit_receipts
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->where('p.payment_type', 'DEPOSIT')
            ->get()
            ->row_array() ?: [];

        $refundAgg = $this->db->query(
            "SELECT COALESCE(SUM(r.refund_amount),0) AS total_refund
             FROM pos_refund r
             INNER JOIN pos_order o ON o.id = r.order_id
             WHERE o.shift_id = ? AND r.refund_status = 'POSTED'",
            [$shiftId]
        )->row_array() ?: [];

        $voidAgg = $this->db->query(
            "SELECT COALESCE(SUM(v.amount_void),0) AS total_void
             FROM pos_void v
             INNER JOIN pos_order o ON o.id = v.order_id
             WHERE o.shift_id = ?",
            [$shiftId]
        )->row_array() ?: [];

        return [
            'total_order_count' => (int)($orderAgg['total_order_count'] ?? 0),
            'total_gross_sales' => round((float)($orderAgg['total_gross_sales'] ?? 0), 2),
            'total_discount' => round((float)($orderAgg['total_discount'] ?? 0), 2),
            'total_promo' => round((float)($orderAgg['total_promo'] ?? 0), 2),
            'total_net_sales' => round((float)($orderAgg['total_net_sales'] ?? 0), 2),
            'total_cash_sales' => round((float)($cashAgg['total_cash_sales'] ?? 0), 2),
            'total_non_cash_sales' => round((float)($cashAgg['total_non_cash_sales'] ?? 0), 2),
            'total_deposit_receipts' => round((float)($depositAgg['total_deposit_receipts'] ?? 0), 2),
            'total_cash_deposit_receipts' => round((float)($depositCashAgg['total_cash_deposit_receipts'] ?? 0), 2),
            'total_refund' => round((float)($refundAgg['total_refund'] ?? 0), 2),
            'total_void' => round((float)($voidAgg['total_void'] ?? 0), 2),
        ];
    }

    public function shift_close_report(int $shiftId, ?float $actualCash = null): ?array
    {
        $shiftId = (int)$shiftId;
        if ($shiftId <= 0) {
            return null;
        }

        $shift = $this->db->select('sh.*, o.outlet_name, t.terminal_name, eo.employee_name AS cashier_open_name, ec.employee_name AS cashier_close_name')
            ->from('pos_shift sh')
            ->join('pos_outlet o', 'o.id = sh.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = sh.terminal_id', 'left')
            ->join('org_employee eo', 'eo.id = sh.cashier_open_employee_id', 'left')
            ->join('org_employee ec', 'ec.id = sh.cashier_close_employee_id', 'left')
            ->where('sh.id', $shiftId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$shift) {
            return null;
        }

        $summary = $this->calculate_shift_summary($shiftId);
        $openingCash = round((float)($shift['opening_cash'] ?? 0), 2);
        $expectedCash = round(
            $openingCash
            + (float)($summary['total_cash_sales'] ?? 0)
            + (float)($summary['total_cash_deposit_receipts'] ?? 0)
            - (float)($summary['total_refund'] ?? 0),
            2
        );
        $resolvedActualCash = $actualCash === null
            ? round((float)($shift['actual_cash'] ?? 0), 2)
            : round((float)$actualCash, 2);

        $reportSummary = $summary + [
            'opening_cash' => $openingCash,
            'gross_receipts' => round(
                (float)($summary['total_cash_sales'] ?? 0)
                + (float)($summary['total_non_cash_sales'] ?? 0)
                + (float)($summary['total_deposit_receipts'] ?? 0),
                2
            ),
            'refund_total' => round((float)($summary['total_refund'] ?? 0), 2),
            'net_receipts' => round(
                (float)($summary['total_cash_sales'] ?? 0)
                + (float)($summary['total_non_cash_sales'] ?? 0)
                + (float)($summary['total_deposit_receipts'] ?? 0)
                - (float)($summary['total_refund'] ?? 0),
                2
            ),
            'cash_net' => round(
                (float)($summary['total_cash_sales'] ?? 0)
                + (float)($summary['total_cash_deposit_receipts'] ?? 0)
                - (float)($summary['total_refund'] ?? 0),
                2
            ),
            'expected_cash' => $expectedCash,
            'actual_cash' => $resolvedActualCash,
            'variance_cash' => round($resolvedActualCash - $expectedCash, 2),
        ];

        return [
            'shift' => $shift,
            'summary' => $reportSummary,
            'by_method' => $this->shift_close_rows_by_method($shiftId),
            'by_account' => $this->shift_close_account_rows($shiftId),
            'cash_breakdown' => $this->shift_cash_breakdown_rows($shiftId),
        ];
    }

    private function shift_close_rows_by_method(int $shiftId): array
    {
        if ($shiftId <= 0) {
            return [];
        }

        $payments = $this->db->select("
                COALESCE(pm.id, 0) AS payment_method_id,
                COALESCE(pm.method_name, 'Tanpa Metode') AS method_name,
                COALESCE(pm.method_type, 'OTHER') AS method_type,
                COALESCE(SUM(pl.amount), 0) AS gross_amount
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->group_by(['pm.id', 'pm.method_name', 'pm.method_type'])
            ->get()
            ->result_array();

        $refunds = $this->db->select("
                COALESCE(pm.id, 0) AS payment_method_id,
                COALESCE(pm.method_name, 'Tanpa Metode') AS method_name,
                COALESCE(pm.method_type, 'OTHER') AS method_type,
                COALESCE(SUM(r.refund_amount), 0) AS refund_amount
            ", false)
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->where('o.shift_id', $shiftId)
            ->where('r.refund_status', 'POSTED')
            ->group_by(['pm.id', 'pm.method_name', 'pm.method_type'])
            ->get()
            ->result_array();

        $map = [];
        foreach ($payments as $row) {
            $key = (string)($row['payment_method_id'] ?? '0');
            $map[$key] = [
                'payment_method_id' => (int)($row['payment_method_id'] ?? 0),
                'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                'method_type' => (string)($row['method_type'] ?? 'OTHER'),
                'gross_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'refund_amount' => 0,
                'net_amount' => round((float)($row['gross_amount'] ?? 0), 2),
            ];
        }
        foreach ($refunds as $row) {
            $key = (string)($row['payment_method_id'] ?? '0');
            if (!isset($map[$key])) {
                $map[$key] = [
                    'payment_method_id' => (int)($row['payment_method_id'] ?? 0),
                    'method_name' => (string)($row['method_name'] ?? 'Tanpa Metode'),
                    'method_type' => (string)($row['method_type'] ?? 'OTHER'),
                    'gross_amount' => 0,
                    'refund_amount' => 0,
                    'net_amount' => 0,
                ];
            }
            $map[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $map[$key]['net_amount'] = round((float)$map[$key]['gross_amount'] - (float)$map[$key]['refund_amount'], 2);
        }

        usort($map, static function ($left, $right) {
            return strcasecmp((string)($left['method_name'] ?? ''), (string)($right['method_name'] ?? ''));
        });

        return array_values($map);
    }

    private function shift_close_account_rows(int $shiftId): array
    {
        $snapshotRows = $this->shift_account_summary_rows($shiftId);
        if (!empty($snapshotRows)) {
            return $snapshotRows;
        }

        return $this->shift_close_rows_by_account($shiftId);
    }

    private function shift_close_rows_by_account(int $shiftId): array
    {
        if ($shiftId <= 0 || !$this->db->table_exists('fin_company_account')) {
            return [];
        }

        $hasPaymentLineAccount = $this->db->field_exists('company_account_id', 'pos_payment_line');
        $hasPaymentMethodAccount = $this->db->field_exists('company_account_id', 'pos_payment_method');
        $hasRefundAccount = $this->db->field_exists('company_account_id', 'pos_refund');

        $paymentAccountExpr = 'NULL';
        if ($hasPaymentLineAccount && $hasPaymentMethodAccount) {
            $paymentAccountExpr = 'COALESCE(pl.company_account_id, pm.company_account_id)';
        } elseif ($hasPaymentLineAccount) {
            $paymentAccountExpr = 'pl.company_account_id';
        } elseif ($hasPaymentMethodAccount) {
            $paymentAccountExpr = 'pm.company_account_id';
        }

        $refundAccountExpr = 'NULL';
        if ($hasRefundAccount && $hasPaymentMethodAccount) {
            $refundAccountExpr = 'COALESCE(r.company_account_id, pm.company_account_id)';
        } elseif ($hasRefundAccount) {
            $refundAccountExpr = 'r.company_account_id';
        } elseif ($hasPaymentMethodAccount) {
            $refundAccountExpr = 'pm.company_account_id';
        }

        if ($paymentAccountExpr === 'NULL' && $refundAccountExpr === 'NULL') {
            return [];
        }

        $payments = $this->db->select("
                COALESCE(acc.id, 0) AS company_account_id,
                acc.account_code,
                acc.account_name,
                acc.bank_name,
                COALESCE(SUM(pl.amount), 0) AS gross_amount
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = ' . $paymentAccountExpr, 'left', false)
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->group_by(['acc.id', 'acc.account_code', 'acc.account_name', 'acc.bank_name'])
            ->get()
            ->result_array();

        $refunds = $this->db->select("
                COALESCE(acc.id, 0) AS company_account_id,
                acc.account_code,
                acc.account_name,
                acc.bank_name,
                COALESCE(SUM(r.refund_amount), 0) AS refund_amount
            ", false)
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'inner')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = ' . $refundAccountExpr, 'left', false)
            ->where('o.shift_id', $shiftId)
            ->where('r.refund_status', 'POSTED')
            ->group_by(['acc.id', 'acc.account_code', 'acc.account_name', 'acc.bank_name'])
            ->get()
            ->result_array();

        $map = [];
        foreach ($payments as $row) {
            $key = (string)($row['company_account_id'] ?? '0');
            $map[$key] = [
                'company_account_id' => (int)($row['company_account_id'] ?? 0),
                'account_code' => (string)($row['account_code'] ?? ''),
                'account_name' => (string)($row['account_name'] ?? 'Tanpa Rekening'),
                'bank_name' => (string)($row['bank_name'] ?? ''),
                'account_label' => $this->company_account_label($row),
                'gross_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'refund_amount' => 0,
                'net_amount' => round((float)($row['gross_amount'] ?? 0), 2),
            ];
        }
        foreach ($refunds as $row) {
            $key = (string)($row['company_account_id'] ?? '0');
            if (!isset($map[$key])) {
                $map[$key] = [
                    'company_account_id' => (int)($row['company_account_id'] ?? 0),
                    'account_code' => (string)($row['account_code'] ?? ''),
                    'account_name' => (string)($row['account_name'] ?? 'Tanpa Rekening'),
                    'bank_name' => (string)($row['bank_name'] ?? ''),
                    'account_label' => $this->company_account_label($row),
                    'gross_amount' => 0,
                    'refund_amount' => 0,
                    'net_amount' => 0,
                ];
            }
            $map[$key]['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $map[$key]['net_amount'] = round((float)$map[$key]['gross_amount'] - (float)$map[$key]['refund_amount'], 2);
        }

        usort($map, static function ($left, $right) {
            return strcasecmp((string)($left['account_label'] ?? ''), (string)($right['account_label'] ?? ''));
        });

        $rows = array_values($map);
        foreach ($rows as $index => &$row) {
            $row['sort_order'] = $index;
        }
        unset($row);

        return $rows;
    }

    private function normalize_cash_breakdown($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $rows = [];
        $index = 0;
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }
            $denomination = max(0, (int)($row['denomination'] ?? 0));
            $qty = max(0, (int)($row['qty'] ?? 0));
            if ($denomination <= 0) {
                continue;
            }
            $rows[] = [
                'denomination' => $denomination,
                'qty' => $qty,
                'amount' => $denomination * $qty,
                'sort_order' => $index,
            ];
            $index++;
        }

        usort($rows, static function ($left, $right) {
            return (int)($right['denomination'] ?? 0) <=> (int)($left['denomination'] ?? 0);
        });

        return $rows;
    }

    private function persist_shift_cash_breakdown(int $shiftId, array $cashBreakdown): array
    {
        if ($shiftId <= 0) {
            return ['ok' => false, 'message' => 'Shift POS tidak valid untuk menyimpan pecahan kasir.'];
        }
        if (!$this->db->table_exists('pos_shift_cash_denomination')) {
            return ['ok' => true, 'skipped' => true];
        }

        $this->db->where('shift_id', $shiftId)->delete('pos_shift_cash_denomination');
        foreach ($cashBreakdown as $row) {
            $qty = (int)($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $payload = $this->filter_table_payload('pos_shift_cash_denomination', [
                'shift_id' => $shiftId,
                'denomination_amount' => round((float)($row['denomination'] ?? 0), 2),
                'qty_count' => $qty,
                'total_amount' => round((float)($row['amount'] ?? 0), 2),
                'sort_order' => (int)($row['sort_order'] ?? 0),
            ]);
            $this->db->insert('pos_shift_cash_denomination', $payload);
        }

        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => $this->db_error_message('Gagal menyimpan detail pecahan kasir.')];
        }

        return ['ok' => true];
    }

    private function persist_shift_account_summary(int $shiftId, array $rows): array
    {
        if ($shiftId <= 0) {
            return ['ok' => false, 'message' => 'Shift POS tidak valid untuk menyimpan snapshot rekening.'];
        }
        if (!$this->db->table_exists('pos_shift_account_summary')) {
            return ['ok' => true, 'skipped' => true];
        }

        $this->db->where('shift_id', $shiftId)->delete('pos_shift_account_summary');
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $payload = $this->filter_table_payload('pos_shift_account_summary', [
                'shift_id' => $shiftId,
                'company_account_id' => !empty($row['company_account_id']) ? (int)$row['company_account_id'] : null,
                'account_code' => $this->nullable_text($row['account_code'] ?? ''),
                'account_name' => $this->nullable_text($row['account_name'] ?? ''),
                'bank_name' => $this->nullable_text($row['bank_name'] ?? ''),
                'account_label' => $this->nullable_text($row['account_label'] ?? $this->company_account_label($row)),
                'gross_amount' => round((float)($row['gross_amount'] ?? 0), 2),
                'refund_amount' => round((float)($row['refund_amount'] ?? 0), 2),
                'net_amount' => round((float)($row['net_amount'] ?? 0), 2),
                'sort_order' => (int)($row['sort_order'] ?? $index),
            ]);
            $this->db->insert('pos_shift_account_summary', $payload);
        }

        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => $this->db_error_message('Gagal menyimpan snapshot rekening tutup shift.')];
        }

        return ['ok' => true];
    }

    private function shift_account_summary_rows(int $shiftId): array
    {
        if ($shiftId <= 0 || !$this->db->table_exists('pos_shift_account_summary')) {
            return [];
        }

        $rows = $this->db->select('company_account_id, account_code, account_name, bank_name, account_label, gross_amount, refund_amount, net_amount, sort_order')
            ->from('pos_shift_account_summary')
            ->where('shift_id', $shiftId)
            ->order_by('sort_order', 'ASC')
            ->order_by('account_label', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['company_account_id'] = (int)($row['company_account_id'] ?? 0);
            $row['gross_amount'] = round((float)($row['gross_amount'] ?? 0), 2);
            $row['refund_amount'] = round((float)($row['refund_amount'] ?? 0), 2);
            $row['net_amount'] = round((float)($row['net_amount'] ?? 0), 2);
            $row['sort_order'] = (int)($row['sort_order'] ?? 0);
            $row['account_label'] = trim((string)($row['account_label'] ?? '')) !== ''
                ? (string)$row['account_label']
                : $this->company_account_label($row);
        }
        unset($row);

        return $rows;
    }

    private function shift_cash_breakdown_rows(int $shiftId): array
    {
        if ($shiftId <= 0 || !$this->db->table_exists('pos_shift_cash_denomination')) {
            return [];
        }

        return $this->db->select('denomination_amount, qty_count, total_amount, sort_order')
            ->from('pos_shift_cash_denomination')
            ->where('shift_id', $shiftId)
            ->order_by('denomination_amount', 'DESC')
            ->order_by('sort_order', 'ASC')
            ->get()
            ->result_array();
    }

    private function compose_shift_close_notes(string $notes, array $cashBreakdown): ?string
    {
        $notes = trim($notes);
        $pieces = [];
        if ($notes !== '') {
            $pieces[] = $notes;
        }

        $breakdownNotes = [];
        foreach ($cashBreakdown as $row) {
            $qty = (int)($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $breakdownNotes[] = (int)($row['denomination'] ?? 0) . 'x' . $qty;
        }
        if (!empty($breakdownNotes)) {
            $pieces[] = 'Pecahan: ' . implode(', ', $breakdownNotes);
        }

        $text = trim(implode(' | ', $pieces));
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, 255);
    }

    private function generate_pos_shift_no(int $outletId, ?string $openedAt = null): string
    {
        $openedAt = $openedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($openedAt));
        $prefix = 'SHIFT-' . $dateKey . '-' . str_pad((string)$outletId, 2, '0', STR_PAD_LEFT);
        $row = $this->db->query(
            'SELECT shift_no FROM pos_shift WHERE shift_no LIKE ? ORDER BY shift_no DESC LIMIT 1',
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['shift_no'])) {
            $parts = explode('-', (string)$row['shift_no']);
            if (!empty($parts)) {
                $next = ((int)end($parts)) + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function company_account_label(array $row): string
    {
        $accountCode = trim((string)($row['account_code'] ?? ''));
        $accountName = trim((string)($row['account_name'] ?? ''));
        $bankName = trim((string)($row['bank_name'] ?? ''));

        $parts = [];
        if ($accountCode !== '') {
            $parts[] = $accountCode;
        }
        if ($accountName !== '') {
            $parts[] = $accountName;
        }
        if ($bankName !== '') {
            $parts[] = $bankName;
        }

        return !empty($parts) ? implode(' • ', $parts) : 'Tanpa Rekening';
    }

    private function generate_pos_session_key(int $outletId, int $terminalId, int $employeeId, ?string $loginAt = null): string
    {
        $loginAt = $loginAt ?: date('Y-m-d H:i:s');
        return strtoupper(implode('-', [
            'SESS',
            date('YmdHis', strtotime($loginAt)),
            str_pad((string)$outletId, 2, '0', STR_PAD_LEFT),
            str_pad((string)$terminalId, 2, '0', STR_PAD_LEFT),
            str_pad((string)$employeeId, 3, '0', STR_PAD_LEFT),
        ]));
    }

    private function ensure_pos_order_customer_name_column(): bool
    {
        if ($this->posOrderCustomerNameReady) {
            return $this->db->field_exists('customer_name', 'pos_order');
        }

        $this->posOrderCustomerNameReady = true;
        if (!$this->db->table_exists('pos_order')) {
            return false;
        }
        return $this->db->field_exists('customer_name', 'pos_order');
    }

    private function order_customer_display_expr(string $orderAlias = 'o', string $memberAlias = 'm'): string
    {
        $memberExpr = $memberAlias !== '' ? ($memberAlias . '.member_name') : "''";
        if ($this->ensure_pos_order_customer_name_column()) {
            return "COALESCE(NULLIF(TRIM({$orderAlias}.customer_name), ''), {$memberExpr})";
        }

        return $memberExpr;
    }

    private function resolve_order_customer_name($customerName, $memberName = ''): string
    {
        $directName = trim((string)$customerName);
        if ($directName !== '') {
            return $directName;
        }

        return trim((string)$memberName);
    }

    private function generate_pos_order_no(?string $orderedAt = null): string
    {
        $orderedAt = $orderedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($orderedAt));
        $prefix = 'POS-' . $dateKey;
        $row = $this->db->query(
            "SELECT order_no FROM pos_order WHERE order_no LIKE ? ORDER BY order_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['order_no'])) {
            $parts = explode('-', (string)$row['order_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_pos_void_no(?string $voidedAt = null): string
    {
        $voidedAt = $voidedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($voidedAt));
        $prefix = 'VOID-' . $dateKey;
        $row = $this->db->query(
            "SELECT void_no FROM pos_void WHERE void_no LIKE ? ORDER BY void_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['void_no'])) {
            $parts = explode('-', (string)$row['void_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_pos_refund_no(?string $refundedAt = null): string
    {
        $refundedAt = $refundedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($refundedAt));
        $prefix = 'RFD-' . $dateKey;
        $row = $this->db->query(
            "SELECT refund_no FROM pos_refund WHERE refund_no LIKE ? ORDER BY refund_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['refund_no'])) {
            $parts = explode('-', (string)$row['refund_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_pos_payment_no(string $paymentType = 'FINAL', ?string $paidAt = null): string
    {
        $paidAt = $paidAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($paidAt));
        $type = strtoupper(trim($paymentType));
        $typeCodeMap = [
            'FINAL' => 'PAY',
            'DEPOSIT' => 'DP',
            'REFUND' => 'RFD',
        ];
        $typeCode = $typeCodeMap[$type] ?? 'PAY';
        $prefix = $typeCode . '-' . $dateKey;
        $row = $this->db->query(
            "SELECT payment_no FROM pos_payment WHERE payment_no LIKE ? ORDER BY payment_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['payment_no'])) {
            $parts = explode('-', (string)$row['payment_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_account_mutation_no(string $mutationDate): string
    {
        $datePart = date('Ymd', strtotime($mutationDate));
        $prefix = 'MUT' . $datePart;

        $row = $this->db->select('mutation_no')
            ->from('fin_account_mutation_log')
            ->like('mutation_no', $prefix, 'after')
            ->order_by('mutation_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['mutation_no'])) {
            $suffix = substr((string)$row['mutation_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->db->where('mutation_no', $no)->count_all_results('fin_account_mutation_log') > 0);

        return $no;
    }

    public function local_outlet_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }
        return $this->db->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function local_terminal_options(int $outletId = 0): array
    {
        if (!$this->db->table_exists('pos_terminal')) {
            return [];
        }
        $this->db->select('id, outlet_id, terminal_code, terminal_name')
            ->from('pos_terminal')
            ->where('is_active', 1);
        if ($outletId > 0) {
            $this->db->where('outlet_id', $outletId);
        }
        return $this->db->order_by('terminal_name', 'ASC')->get()->result_array();
    }

    private function default_self_order_member_base_url(): string
    {
        $env = trim((string)getenv('MEMBER_BASE_URL'));
        if ($env !== '') {
            return $this->normalize_member_base_url($env);
        }
        return $this->normalize_member_base_url(base_url('../member/'));
    }

    private function normalize_member_base_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            $env = trim((string)getenv('MEMBER_BASE_URL'));
            if ($env !== '') {
                return rtrim($env, '/') . '/';
            }
            return rtrim((string)base_url('../member/'), '/') . '/';
        }
        return rtrim($value, '/') . '/';
    }

    private function build_self_order_table_url(int $tableId, array $settings = []): string
    {
        $tableId = max(0, $tableId);
        $settings = !empty($settings) ? $settings : $this->self_order_settings();
        $base = $this->normalize_member_base_url((string)($settings['member_base_url'] ?? ''));
        $secret = trim((string)($settings['table_qr_secret'] ?? ''));
        if ($tableId <= 0) {
            return $base . 'meja';
        }
        if ($secret === '') {
            return $base . 'meja/' . $tableId;
        }
        $sig = hash_hmac('sha256', (string)$tableId, $secret);
        return $base . 'meja/' . $tableId . '/' . $sig;
    }

    private function distinct_non_empty_values(CI_DB_query_builder $db, string $table, string $column): array
    {
        if (!$db->table_exists($table) || !$db->field_exists($column, $table)) {
            return [];
        }
        $rows = $db->select($column)
            ->from($table)
            ->where($column . ' IS NOT NULL', null, false)
            ->where('TRIM(' . $column . ") <> ''", null, false)
            ->group_by($column)
            ->order_by($column, 'ASC')
            ->get()
            ->result_array();

        return array_values(array_filter(array_map(static function (array $row) use ($column): string {
            return trim((string)($row[$column] ?? ''));
        }, $rows)));
    }

    private function company_account_exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        return (int)$this->coredb->from('fin_company_account')->where('id', $id)->count_all_results() > 0;
    }

    private function terminal_device_key_exists(string $deviceKey, int $excludeId = 0): bool
    {
        $this->coredb->from('pos_terminal')->where('device_key', $deviceKey);
        if ($excludeId > 0) {
            $this->coredb->where('id !=', $excludeId);
        }
        return (int)$this->coredb->count_all_results() > 0;
    }

    private function code_exists(CI_DB_query_builder $db, string $table, string $column, string $code, int $excludeId = 0): bool
    {
        $db->from($table)->where($column, $code);
        if ($excludeId > 0) {
            $db->where('id !=', $excludeId);
        }
        return (int)$db->count_all_results() > 0;
    }

    /** Return every record whose stored number is the same after normalization. */
    private function member_phone_matches(CI_DB_query_builder $db, string $phone, int $excludeId = 0): array
    {
        if ($phone === '') {
            return [];
        }

        $query = $db->select('id, member_no, member_name, mobile_phone')
            ->from('crm_member');
        if ($excludeId > 0) {
            $query->where('id !=', $excludeId);
        }

        $rows = $query->order_by('id', 'ASC')->get()->result_array();
        return array_values(array_filter($rows, static function (array $row) use ($phone): bool {
            return member_phone_normalize($row['mobile_phone'] ?? '') === $phone;
        }));
    }

    private function member_phone_registered_message(array $member, string $phone): string
    {
        $owner = trim((string)($member['member_name'] ?? '')) ?: 'member lain';
        $memberNo = trim((string)($member['member_no'] ?? ''));
        $suffix = $memberNo !== '' ? ' (' . $memberNo . ')' : '';
        return 'Nomor HP ' . member_phone_display($phone) . ' sudah terdaftar atas nama ' . $owner . $suffix . '.';
    }

    private function generate_member_no_core(?string $joinedAt = null): string
    {
        $joinedAt = $joinedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($joinedAt));
        $prefix = 'MBR-' . $dateKey;
        $row = $this->coredb->query(
            "SELECT member_no FROM crm_member WHERE member_no LIKE ? ORDER BY member_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if ($row && !empty($row['member_no'])) {
            $parts = explode('-', (string)$row['member_no']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_named_code(CI_DB_query_builder $db, string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        $slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $base = $prefix . substr($slug, 0, max(1, $maxLen - strlen($prefix) - 5));
        $candidate = $base;
        $seq = 1;
        while ($this->code_exists($db, $table, $column, $candidate, $excludeId)) {
            $suffix = '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            $cut = max(1, $maxLen - strlen($suffix));
            $candidate = substr($base, 0, $cut) . $suffix;
            $seq++;
        }
        return $candidate;
    }

    private function sales_channel_service_type_options(): array
    {
        return ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'];
    }

    private function explode_service_types(string $raw): array
    {
        $parts = preg_split('/\s*,\s*/', strtoupper(trim($raw)));
        if (!is_array($parts)) {
            return [];
        }

        $allowed = $this->sales_channel_service_type_options();
        $normalized = [];
        foreach ($parts as $part) {
            $value = trim((string)$part);
            if ($value === '' || !in_array($value, $allowed, true)) {
                continue;
            }
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    private function normalize_allowed_service_types($value): array
    {
        if (is_array($value)) {
            $joined = implode(',', array_map(static function ($item) {
                return strtoupper(trim((string)$item));
            }, $value));
            return $this->explode_service_types($joined);
        }

        return $this->explode_service_types((string)$value);
    }

    private function generate_pos_sales_channel_code(string $name): string
    {
        return $this->generate_named_code($this->coredb, 'pos_sales_channel', 'channel_code', $name, 'CH', 0, 40);
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        return [$page, $offset, $totalPages];
    }

    private function nullable_text($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function sys_app_config_value(string $key, string $default = ''): string
    {
        $key = trim($key);
        if ($key === '' || !$this->db->table_exists('sys_app_config')) {
            return $default;
        }

        $row = $this->db->select('config_value')
            ->from('sys_app_config')
            ->where('config_key', $key)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function filter_table_payload(string $table, array $payload): array
    {
        if (empty($payload) || !$this->db->table_exists($table)) {
            return $payload;
        }

        $fields = $this->db->list_fields($table);
        if (empty($fields)) {
            return $payload;
        }

        $fieldMap = array_fill_keys($fields, true);
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (isset($fieldMap[$key])) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function db_error_message(string $fallback): string
    {
        $error = $this->db->error();
        $message = trim((string)($error['message'] ?? ''));
        if ($message !== '') {
            return $fallback . ' ' . $message;
        }

        return $fallback;
    }

    private function nullable_date($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function nullable_datetime($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }


}
