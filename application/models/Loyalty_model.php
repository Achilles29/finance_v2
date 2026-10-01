<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Loyalty_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('member_phone');
    }

    public function member_filter_options(): array
    {
        return [
            'tiers' => $this->distinct_non_empty_values('crm_member', 'member_tier'),
        ];
    }

    public function product_options(): array
    {
        if (!$this->db->table_exists('mst_product')) {
            return [];
        }

        return $this->db->select('p.id, p.product_code, p.product_name, pd.name AS product_division_name')
            ->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where('p.is_active', 1)
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function product_search(string $q, int $limit = 20): array
    {
        if (!$this->db->table_exists('mst_product')) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $db = $this->db
            ->select('p.id, p.product_name, p.product_code, p.selling_price, p.photo_path, pd.name AS product_division_name')
            ->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where('p.is_active', 1);

        if ($q !== '') {
            $db->group_start()
                ->like('p.product_name', $q)
                ->or_like('p.product_code', $q)
                ->group_end();
        }

        return $db->order_by('p.product_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function member_search(string $q, int $limit = 20): array
    {
        if (!$this->db->table_exists('crm_member')) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $db = $this->db
            ->select('m.id, m.member_name, m.mobile_phone')
            ->from('crm_member m')
            ->where('m.is_active', 1)
            ->where('m.member_status', 'ACTIVE');

        if ($q !== '') {
            $db->group_start()
                ->like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->or_like('m.member_no', $q)
                ->group_end();
        }

        return $db->order_by('m.member_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function voucher_campaign_options(array $allowedIssueModes = []): array
    {
        if (!$this->db->table_exists('pos_voucher_campaign')) {
            return [];
        }

        $db = $this->db
            ->select('id, campaign_name, issue_mode')
            ->from('pos_voucher_campaign')
            ->where('is_active', 1);

        if (!empty($allowedIssueModes)) {
            $db->where_in('issue_mode', $allowedIssueModes);
        }

        $rows = $db->order_by('campaign_name', 'ASC')->get()->result_array();
        return array_map(static function (array $row): array {
            return [
                'value' => (int)($row['id'] ?? 0),
                'label' => (string)($row['campaign_name'] ?? ''),
            ];
        }, $rows);
    }

    public function member_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $memberStatus = strtoupper(trim((string)($filters['member_status'] ?? 'ALL')));
        $tier = trim((string)($filters['tier'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $where  = [];
        $params = [];

        if ($q !== '') {
            $like = '%' . $this->db->escape_like_str($q) . '%';
            $where[]  = '(m.member_no LIKE ? OR m.member_name LIKE ? OR m.mobile_phone LIKE ? OR m.email LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($status === 'ACTIVE') {
            $where[] = 'm.is_active = 1';
        } elseif ($status === 'INACTIVE') {
            $where[] = 'm.is_active = 0';
        }
        if (in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $where[]  = 'm.member_status = ?';
            $params[] = $memberStatus;
        }
        if ($tier !== '') {
            $where[]  = 'm.member_tier = ?';
            $params[] = $tier;
        }

        $sortBy = strtolower(trim((string)($filters['sort_by'] ?? 'member_name')));
        $sortDir = strtolower(trim((string)($filters['sort_dir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $sortMap = [
            'member_no' => 'm.member_no',
            'member_name' => 'm.member_name',
            'contact' => 'm.mobile_phone',
            'joined_at' => 'm.joined_at',
            'point' => 'point_balance',
            'stamp' => 'stamp_balance',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'm.member_name';

        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int)$this->db->query("SELECT COUNT(*) AS n FROM crm_member m $w", $params)->row()->n;
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $sql = "
            SELECT m.*,
                COALESCE((SELECT pl.balance_after FROM pos_point_ledger pl WHERE pl.member_id = m.id ORDER BY pl.id DESC LIMIT 1), m.point_balance_cache) AS point_balance,
                COALESCE((SELECT sl.balance_after FROM pos_stamp_ledger sl WHERE sl.member_id = m.id ORDER BY sl.id DESC LIMIT 1), 0) AS stamp_balance,
                CASE WHEN EXISTS (SELECT 1 FROM pos_stamp_ledger slx WHERE slx.member_id = m.id) THEN 1 ELSE 0 END AS stamp_has_ledger,
                (SELECT COUNT(*) FROM pos_voucher_issue vi WHERE vi.member_id = m.id AND vi.voucher_status = 'OPEN') AS open_voucher_count,
                (SELECT COUNT(*) FROM pos_order po WHERE po.member_id = m.id AND po.status IN ('PAID','PAID_PARTIAL','SERVED')) AS order_count
            FROM crm_member m
            $w
            ORDER BY $sortColumn $sortDir, m.member_no ASC
            LIMIT $limit OFFSET $offset
        ";

        $rows = $this->db->query($sql, $params)->result_array();

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

    public function member_order_rows(int $memberId, int $page = 1, int $limit = 15): array
    {
        if (!$this->db->table_exists('pos_order')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1]];
        }
        $db = $this->db->from('pos_order po')->where('po.member_id', $memberId);
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = $db
            ->select('po.id, po.order_no, po.ordered_at, po.status, po.grand_total, po.subtotal_amount, po.discount_amount')
            ->order_by('po.ordered_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();

        $orderIds = array_values(array_filter(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $rows)));
        if (!empty($orderIds) && $this->db->table_exists('pos_order_line')) {
            $lineRows = $this->db
                ->select('ol.id, ol.order_id, ol.line_no, ol.qty, ol.unit_price, ol.discount_amount, ol.net_amount, ol.notes, ol.line_status, p.product_name')
                ->from('pos_order_line ol')
                ->join('mst_product p', 'p.id = ol.product_id', 'left')
                ->where_in('ol.order_id', $orderIds)
                ->order_by('ol.order_id', 'DESC')
                ->order_by('ol.line_no', 'ASC')
                ->order_by('ol.id', 'ASC')
                ->get()
                ->result_array();
            $lineIds = array_values(array_filter(array_map(static function (array $line): int {
                return (int)($line['id'] ?? 0);
            }, $lineRows)));
            $extrasByLine = [];
            if (!empty($lineIds) && $this->db->table_exists('pos_order_line_extra')) {
                $extraRows = $this->db
                    ->select('oxe.order_line_id, oxe.qty, oxe.unit_price, oxe.net_amount, oxe.notes, ex.extra_name')
                    ->from('pos_order_line_extra oxe')
                    ->join('mst_extra ex', 'ex.id = oxe.extra_id', 'left')
                    ->where_in('oxe.order_line_id', $lineIds)
                    ->order_by('oxe.line_no', 'ASC')
                    ->order_by('oxe.id', 'ASC')
                    ->get()
                    ->result_array();
                foreach ($extraRows as $extra) {
                    $extrasByLine[(int)($extra['order_line_id'] ?? 0)][] = $extra;
                }
            }

            $itemsByOrder = [];
            foreach ($lineRows as $line) {
                $lineId = (int)($line['id'] ?? 0);
                $itemsByOrder[(int)($line['order_id'] ?? 0)][] = [
                    'line_id' => $lineId,
                    'item_name' => (string)($line['product_name'] ?? 'Produk'),
                    'qty' => (float)($line['qty'] ?? 0),
                    'unit_price' => (float)($line['unit_price'] ?? 0),
                    'discount_amount' => (float)($line['discount_amount'] ?? 0),
                    'net_amount' => (float)($line['net_amount'] ?? 0),
                    'line_status' => (string)($line['line_status'] ?? ''),
                    'notes' => (string)($line['notes'] ?? ''),
                    'extras' => array_map(static function (array $extra): array {
                        return [
                            'extra_name' => (string)($extra['extra_name'] ?? 'Extra'),
                            'qty' => (float)($extra['qty'] ?? 0),
                            'unit_price' => (float)($extra['unit_price'] ?? 0),
                            'net_amount' => (float)($extra['net_amount'] ?? 0),
                            'notes' => (string)($extra['notes'] ?? ''),
                        ];
                    }, $extrasByLine[$lineId] ?? []),
                ];
            }
            foreach ($rows as &$row) {
                $row['items'] = $itemsByOrder[(int)($row['id'] ?? 0)] ?? [];
                $row['item_count'] = count($row['items']);
            }
            unset($row);
        }

        return [
            'rows' => $rows,
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages],
        ];
    }

    public function member_point_rows(int $memberId, int $page = 1, int $limit = 15): array
    {
        if (!$this->db->table_exists('pos_point_ledger')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1]];
        }
        $db = $this->db->from('pos_point_ledger pl')
            ->join('pos_order po', 'po.id = pl.order_id', 'left')
            ->where('pl.member_id', $memberId);
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = $db
            ->select('pl.id, pl.ledger_type, pl.points_in, pl.points_out, pl.balance_after, pl.expired_at, pl.notes, pl.created_at, po.order_no')
            ->order_by('pl.created_at', 'DESC')
            ->order_by('pl.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function member_stamp_rows(int $memberId, int $page = 1, int $limit = 15): array
    {
        if (!$this->db->table_exists('pos_stamp_ledger')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1]];
        }
        $db = $this->db->from('pos_stamp_ledger sl')
            ->join('pos_stamp_campaign sc', 'sc.id = sl.campaign_id', 'left')
            ->join('pos_order po', 'po.id = sl.order_id', 'left')
            ->where('sl.member_id', $memberId);
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = $db
            ->select('sl.id, sl.ledger_type, sl.stamp_in, sl.stamp_out, sl.balance_after, sl.expired_at, sl.notes, sl.created_at, sc.campaign_name, po.order_no')
            ->order_by('sl.created_at', 'DESC')
            ->order_by('sl.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function member_voucher_rows(int $memberId, int $page = 1, int $limit = 15): array
    {
        if (!$this->db->table_exists('pos_voucher_issue')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1]];
        }
        $this->expire_elapsed_vouchers();
        $db = $this->db->from('pos_voucher_issue vi')
            ->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left')
            ->join('pos_order po', 'po.id = vi.source_order_id', 'left')
            ->where('vi.member_id', $memberId);
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = $db
            ->select('vi.id, vi.voucher_issue_no, vi.voucher_code, vi.voucher_status, vi.amount_snapshot, vi.percent_snapshot, vi.issued_at, vi.expired_at, vi.redeemed_at, vc.campaign_name, vc.voucher_type, po.order_no')
            ->order_by('vi.issued_at', 'DESC')
            ->order_by('vi.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_member(int $id): ?array
    {
        return $this->db->from('crm_member')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function save_member(array $data): array
    {
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
        $hasActiveInput = array_key_exists('is_active', $data);
        $isActive = $hasActiveInput && !empty($data['is_active']) ? 1 : 0;

        $this->db->trans_begin();
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
            ];

            $phoneMatches = $mobilePhone !== ''
                ? $this->member_phone_matches($mobilePhone, $id)
                : [];
            if ($id > 0 && !empty($phoneMatches)) {
                throw new RuntimeException($this->member_phone_registered_message($phoneMatches[0], $mobilePhone));
            }
            if ($id <= 0 && !empty($phoneMatches)) {
                foreach ($phoneMatches as $match) {
                    if (member_names_match($name, (string)($match['member_name'] ?? ''))) {
                        $this->db->trans_commit();
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
                if ($this->code_exists('crm_member', 'member_no', $memberNo, $id)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                // The member form does not submit this field; preserve status on edit.
                $payload['is_active'] = $hasActiveInput ? $isActive : (int)($existing['is_active'] ?? 1);
                $this->db->where('id', $id)->update('crm_member', $payload);
            } else {
                if ($memberNo === '') {
                    $memberNo = $this->generate_member_no($joinedAt);
                } elseif ($this->code_exists('crm_member', 'member_no', $memberNo)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                // New manual members are active unless an explicit status is supplied.
                $payload['is_active'] = $hasActiveInput ? $isActive : 1;
                $this->db->insert('crm_member', $payload);
                $id = (int)$this->db->insert_id();
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan member.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_member(int $id): array
    {
        return $this->toggle_active_record('crm_member', $id, function (array $row, int $newValue): array {
            $memberStatus = strtoupper((string)($row['member_status'] ?? 'ACTIVE'));
            if ($newValue === 0 && $memberStatus === 'ACTIVE') {
                $memberStatus = 'CLOSED';
            } elseif ($newValue === 1 && $memberStatus === 'CLOSED') {
                $memberStatus = 'ACTIVE';
            }
            return ['member_status' => $memberStatus];
        }, 'Member tidak ditemukan.', 'Gagal mengubah status member.');
    }

    public function delete_member(int $id): array
    {
        $row = $this->find_member($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        if ($this->db->table_exists('pos_order') && $this->db->field_exists('member_id', 'pos_order')) {
            $orderCount = (int)$this->db->from('pos_order')->where('member_id', $id)->count_all_results();
            if ($orderCount > 0) {
                return ['ok' => false, 'message' => 'Member sudah dipakai di transaksi POS dan tidak bisa dihapus. Nonaktifkan saja bila sudah tidak dipakai.'];
            }
        }

        if ($this->db->table_exists('pos_voucher_issue') && $this->db->field_exists('member_id', 'pos_voucher_issue')) {
            $voucherCount = (int)$this->db->from('pos_voucher_issue')->where('member_id', $id)->count_all_results();
            if ($voucherCount > 0) {
                return ['ok' => false, 'message' => 'Member sudah memiliki voucher terkait. Hapus/void voucher dulu atau nonaktifkan member.'];
            }
        }

        return $this->delete_record('crm_member', $id, 'Member tidak ditemukan.', 'Gagal menghapus member.');
    }

    public function point_rule_rows(array $filters): array
    {
        $db = $this->db->from('pos_point_rule r')->join('mst_product p', 'p.id = r.required_product_id', 'left');
        $this->apply_search_filter($db, trim((string)($filters['q'] ?? '')), ['r.rule_code', 'r.rule_name', 'p.product_name']);
        $this->apply_active_filter($db, strtoupper(trim((string)($filters['status'] ?? 'ACTIVE'))), 'r.is_active');
        $earnMode = strtoupper(trim((string)($filters['earn_mode'] ?? 'ALL')));
        if (in_array($earnMode, ['AMOUNT', 'PRODUCT', 'FLAT'], true)) {
            $db->where('r.earn_mode', $earnMode);
        }
        return $this->paginate_rows($db, [
            'r.*',
            'p.product_name AS required_product_name',
            "CASE r.earn_mode
                WHEN 'AMOUNT' THEN 'Dari nominal belanja'
                WHEN 'PRODUCT' THEN 'Dari produk tertentu'
                WHEN 'FLAT' THEN 'Poin tetap per transaksi'
                ELSE r.earn_mode
             END AS earn_mode_label",
        ], ['r.rule_name' => 'ASC'], max(1, (int)($filters['page'] ?? 1)), max(1, min(200, (int)($filters['limit'] ?? 25))));
    }

    public function save_point_rule(array $data): array
    {
        return $this->save_point_like_record('point', $data);
    }

    public function toggle_point_rule(int $id): array
    {
        return $this->toggle_active_record('pos_point_rule', $id, null, 'Rule poin tidak ditemukan.', 'Gagal mengubah status rule poin.');
    }

    public function delete_point_rule(int $id): array
    {
        return $this->delete_record('pos_point_rule', $id, 'Rule poin tidak ditemukan.', 'Gagal menghapus rule poin.');
    }

    public function stamp_campaign_rows(array $filters): array
    {
        $db = $this->db->from('pos_stamp_campaign c')->join('mst_product p', 'p.id = c.required_product_id', 'left');
        $this->apply_search_filter($db, trim((string)($filters['q'] ?? '')), ['c.campaign_code', 'c.campaign_name', 'p.product_name']);
        $this->apply_active_filter($db, strtoupper(trim((string)($filters['status'] ?? 'ACTIVE'))), 'c.is_active');
        $earnMode = strtoupper(trim((string)($filters['earn_mode'] ?? 'ALL')));
        if (in_array($earnMode, ['TXN', 'AMOUNT', 'PRODUCT'], true)) {
            $db->where('c.earn_mode', $earnMode);
        }
        return $this->paginate_rows($db, [
            'c.*',
            'p.product_name AS required_product_name',
            "CASE c.earn_mode
                WHEN 'TXN' THEN 'Setiap transaksi'
                WHEN 'AMOUNT' THEN 'Dari nominal belanja'
                WHEN 'PRODUCT' THEN 'Dari produk tertentu'
                ELSE c.earn_mode
             END AS earn_mode_label",
        ], ['c.campaign_name' => 'ASC'], max(1, (int)($filters['page'] ?? 1)), max(1, min(200, (int)($filters['limit'] ?? 25))));
    }

    public function save_stamp_campaign(array $data): array
    {
        return $this->save_point_like_record('stamp', $data);
    }

    public function toggle_stamp_campaign(int $id): array
    {
        return $this->toggle_active_record('pos_stamp_campaign', $id, null, 'Campaign stamp tidak ditemukan.', 'Gagal mengubah status campaign stamp.');
    }

    public function delete_stamp_campaign(int $id): array
    {
        return $this->delete_record('pos_stamp_campaign', $id, 'Campaign stamp tidak ditemukan.', 'Gagal menghapus campaign stamp.');
    }

    public function voucher_campaign_rows(array $filters): array
    {
        $db = $this->db->from('pos_voucher_campaign c')
            ->join('mst_product p1', 'p1.id = c.trigger_product_id', 'left')
            ->join('mst_product p2', 'p2.id = c.free_product_id', 'left');
        $this->apply_search_filter($db, trim((string)($filters['q'] ?? '')), ['c.campaign_code', 'c.campaign_name', 'p1.product_name', 'p2.product_name']);
        $this->apply_active_filter($db, strtoupper(trim((string)($filters['status'] ?? 'ACTIVE'))), 'c.is_active');
        $issueMode = strtoupper(trim((string)($filters['issue_mode'] ?? 'ALL')));
        if (in_array($issueMode, ['PUBLIC', 'AUTO_FROM_TXN', 'MEMBER_TARGETED', 'MANUAL'], true)) {
            $db->where('c.issue_mode', $issueMode);
        }
        return $this->paginate_rows($db, [
            'c.*',
            'p1.product_name AS trigger_product_name',
            'p2.product_name AS free_product_name',
            "CASE c.issue_mode
                WHEN 'PUBLIC' THEN 'Voucher umum'
                WHEN 'AUTO_FROM_TXN' THEN 'Otomatis dari transaksi'
                WHEN 'MEMBER_TARGETED' THEN 'Khusus member tertentu'
                WHEN 'MANUAL' THEN 'Diterbitkan manual'
                ELSE c.issue_mode
             END AS issue_mode_label",
            "CASE c.voucher_type
                WHEN 'AMOUNT' THEN 'Potongan nominal'
                WHEN 'PERCENT' THEN 'Potongan persen'
                WHEN 'FREE_PRODUCT' THEN 'Gratis produk'
                ELSE c.voucher_type
             END AS voucher_type_label",
        ], ['c.campaign_name' => 'ASC'], max(1, (int)($filters['page'] ?? 1)), max(1, min(200, (int)($filters['limit'] ?? 25))));
    }

    public function voucher_issue_rows(array $filters): array
    {
        $this->expire_elapsed_vouchers();
        $hasGowesParticipants = $this->db->table_exists('evt_gowes_participant');

        $db = $this->db->from('pos_voucher_issue v')
            ->join('pos_voucher_campaign c', 'c.id = v.campaign_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left');
        if ($hasGowesParticipants) {
            $db->join('evt_gowes_participant gp', 'gp.voucher_issue_id = v.id', 'left');
        }
        $this->apply_search_filter($db, trim((string)($filters['q'] ?? '')), ['v.voucher_issue_no', 'v.voucher_code', 'c.campaign_name', 'm.member_name', 'm.mobile_phone']);

        $activeFilter = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        if ($activeFilter === 'ACTIVE') {
            $db->where('v.voucher_status', 'OPEN')
                ->group_start()
                    ->where('v.expired_at IS NULL', null, false)
                    ->or_where('v.expired_at >=', date('Y-m-d H:i:s'))
                ->group_end();
        } elseif ($activeFilter === 'INACTIVE') {
            $db->group_start()
                ->where('v.voucher_status !=', 'OPEN')
                ->or_group_start()
                    ->where('v.voucher_status', 'OPEN')
                    ->where('v.expired_at IS NOT NULL', null, false)
                    ->where('v.expired_at <', date('Y-m-d H:i:s'))
                ->group_end()
            ->group_end();
        }

        $status = strtoupper(trim((string)($filters['voucher_status'] ?? 'ALL')));
        if (in_array($status, ['OPEN', 'REDEEMED', 'EXPIRED', 'VOID'], true)) {
            $db->where('v.voucher_status', $status);
            if ($status === 'OPEN') {
                $db->group_start()
                    ->where('v.expired_at IS NULL', null, false)
                    ->or_where('v.expired_at >=', date('Y-m-d H:i:s'))
                ->group_end();
            }
        }

        return $this->paginate_rows($db, [
            'v.*',
            'c.campaign_name',
            'c.voucher_type',
            $hasGowesParticipants ? '(gp.id IS NOT NULL) AS is_gowes_claim' : '(0) AS is_gowes_claim',
            "COALESCE(m.member_name, 'Umum / non-member') AS member_name",
            'm.mobile_phone',
            "CASE v.voucher_status
                WHEN 'OPEN' THEN 'Siap dipakai'
                WHEN 'REDEEMED' THEN 'Sudah dipakai'
                WHEN 'EXPIRED' THEN 'Kadaluarsa'
                WHEN 'VOID' THEN 'Dibatalkan'
                ELSE v.voucher_status
             END AS voucher_status_label",
        ], ['v.issued_at' => 'DESC', 'v.id' => 'DESC'], max(1, (int)($filters['page'] ?? 1)), max(1, min(200, (int)($filters['limit'] ?? 25))));
    }

    public function voucher_usage_filter_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }

        return $this->db->select('id, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function voucher_usage_rows(array $filters): array
    {
        if (!$this->db->table_exists('pos_voucher_usage')) {
            return [
                'rows' => [],
                'meta' => [
                    'total' => 0,
                    'page' => max(1, (int)($filters['page'] ?? 1)),
                    'limit' => max(1, min(200, (int)($filters['limit'] ?? 25))),
                    'total_pages' => 1,
                ],
                'schema_ready' => false,
                'message' => 'Log pemakaian voucher belum tersedia. Jalankan migration voucher terbaru terlebih dahulu.',
            ];
        }

        $db = $this->db->from('pos_voucher_usage u')
            ->join('pos_voucher_issue vi', 'vi.id = u.voucher_issue_id', 'left')
            ->join('pos_voucher_campaign vc', 'vc.id = u.campaign_id', 'left')
            ->join('pos_order po', 'po.id = u.order_id', 'left')
            ->join('pos_payment pp', 'pp.id = u.payment_id', 'left')
            ->join('crm_member m', 'm.id = u.member_id', 'left')
            ->join('pos_outlet o', 'o.id = po.outlet_id', 'left')
            ->join('org_employee e', 'e.id = u.cashier_employee_id', 'left');

        $this->apply_search_filter($db, trim((string)($filters['q'] ?? '')), [
            'u.voucher_code',
            'u.voucher_label',
            'vi.voucher_issue_no',
            'po.order_no',
            'po.customer_name',
            'm.member_name',
            'o.outlet_name',
        ]);

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateFrom !== '') {
            $db->where('u.used_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $db->where('u.used_at <=', $dateTo . ' 23:59:59');
        }

        $voucherKind = strtoupper(trim((string)($filters['voucher_kind'] ?? 'ALL')));
        if (in_array($voucherKind, ['ISSUE', 'CAMPAIGN'], true)) {
            $db->where('u.voucher_kind', $voucherKind);
        }
        $usageStatus = strtoupper(trim((string)($filters['usage_status'] ?? 'ALL')));
        if (in_array($usageStatus, ['APPLIED', 'REVERSED', 'VOID'], true)) {
            $db->where('u.usage_status', $usageStatus);
        }
        $outletId = (int)($filters['outlet_id'] ?? 0);
        if ($outletId > 0) {
            $db->where('po.outlet_id', $outletId);
        }

        $result = $this->paginate_rows($db, [
            'u.id',
            'u.voucher_kind',
            "COALESCE(NULLIF(u.voucher_code, ''), vi.voucher_code, vc.campaign_code, '-') AS voucher_code",
            "COALESCE(NULLIF(u.voucher_label, ''), vc.campaign_name, vi.voucher_code, 'Voucher') AS voucher_label",
            'vi.voucher_issue_no',
            'u.face_value_amount',
            'u.face_value_percent',
            'u.applied_amount',
            'u.usage_status',
            'u.used_at',
            'u.notes',
            'po.order_no',
            'po.status AS order_status',
            'po.ordered_at',
            'po.paid_at',
            'po.customer_name',
            "COALESCE(NULLIF(m.member_name, ''), NULLIF(po.customer_name, ''), 'Walk in') AS customer_display",
            'm.mobile_phone AS member_phone',
            'o.outlet_name',
            'e.employee_name AS cashier_name',
            'pp.payment_no',
        ], ['u.used_at' => 'DESC', 'u.id' => 'DESC'], max(1, (int)($filters['page'] ?? 1)), max(1, min(200, (int)($filters['limit'] ?? 25))));
        $result['schema_ready'] = true;
        return $result;
    }

    public function voucher_usage_detail(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('pos_voucher_usage')) {
            return null;
        }

        $usage = $this->db->select(implode(', ', [
            'u.*',
            'vi.voucher_issue_no',
            'vi.voucher_status',
            'vc.campaign_name',
            'vc.campaign_code',
            'po.order_no',
            'po.status AS order_status',
            'po.ordered_at',
            'po.paid_at',
            'po.customer_name',
            'po.subtotal_amount',
            'po.voucher_amount',
            'po.promo_amount',
            'po.grand_total',
            "COALESCE(NULLIF(m.member_name, ''), NULLIF(po.customer_name, ''), 'Walk in') AS customer_display",
            'm.member_no',
            'm.mobile_phone AS member_phone',
            'o.outlet_name',
            'e.employee_name AS cashier_name',
            'pp.payment_no',
            'pp.payment_status',
        ]), false)
            ->from('pos_voucher_usage u')
            ->join('pos_voucher_issue vi', 'vi.id = u.voucher_issue_id', 'left')
            ->join('pos_voucher_campaign vc', 'vc.id = u.campaign_id', 'left')
            ->join('pos_order po', 'po.id = u.order_id', 'left')
            ->join('pos_payment pp', 'pp.id = u.payment_id', 'left')
            ->join('crm_member m', 'm.id = u.member_id', 'left')
            ->join('pos_outlet o', 'o.id = po.outlet_id', 'left')
            ->join('org_employee e', 'e.id = u.cashier_employee_id', 'left')
            ->where('u.id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$usage) {
            return null;
        }

        $orderId = (int)($usage['order_id'] ?? 0);
        $paymentId = (int)($usage['payment_id'] ?? 0);
        $orderLines = [];
        if ($orderId > 0 && $this->db->table_exists('pos_order_line')) {
            $orderLines = $this->db->select('ol.line_no, ol.qty, ol.unit_price, ol.discount_amount, ol.net_amount, ol.line_status, p.product_name')
                ->from('pos_order_line ol')
                ->join('mst_product p', 'p.id = ol.product_id', 'left')
                ->where('ol.order_id', $orderId)
                ->order_by('ol.line_no', 'ASC')
                ->get()
                ->result_array();
        }

        $paymentLines = [];
        if ($paymentId > 0 && $this->db->table_exists('pos_payment_line')) {
            $paymentLines = $this->db->select('pl.line_no, pl.amount, pl.reference_no, pl.status, pm.method_name')
                ->from('pos_payment_line pl')
                ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
                ->where('pl.payment_id', $paymentId)
                ->order_by('pl.line_no', 'ASC')
                ->get()
                ->result_array();
        }

        return [
            'usage' => $usage,
            'order_lines' => $orderLines,
            'payment_lines' => $paymentLines,
        ];
    }

    private function expire_elapsed_vouchers(): void
    {
        if (!$this->db->table_exists('pos_voucher_issue')) {
            return;
        }
        $this->db->where('voucher_status', 'OPEN')
            ->where('expired_at IS NOT NULL', null, false)
            ->where('expired_at <', date('Y-m-d H:i:s'))
            ->update('pos_voucher_issue', ['voucher_status' => 'EXPIRED']);
    }

    public function save_voucher_campaign(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['campaign_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama campaign voucher wajib diisi.'];
        }

        $issueMode = strtoupper(trim((string)($data['issue_mode'] ?? 'PUBLIC')));
        if (!in_array($issueMode, ['PUBLIC', 'AUTO_FROM_TXN', 'MEMBER_TARGETED', 'MANUAL'], true)) {
            $issueMode = 'PUBLIC';
        }
        $voucherType = strtoupper(trim((string)($data['voucher_type'] ?? 'AMOUNT')));
        if (!in_array($voucherType, ['AMOUNT', 'PERCENT', 'FREE_PRODUCT'], true)) {
            $voucherType = 'AMOUNT';
        }

        $campaignCode = strtoupper(trim((string)($data['campaign_code'] ?? '')));
        $this->db->trans_begin();
        try {
            if ($campaignCode === '') {
                $campaignCode = $this->generate_named_code('pos_voucher_campaign', 'campaign_code', $name, 'VCH-');
            } elseif ($this->code_exists('pos_voucher_campaign', 'campaign_code', $campaignCode, $id)) {
                throw new RuntimeException('Kode campaign voucher sudah dipakai.');
            }

            $payload = [
                'campaign_code' => $campaignCode,
                'campaign_name' => $name,
                'issue_mode' => $issueMode,
                'voucher_type' => $voucherType,
                'discount_value' => max(0, (float)($data['discount_value'] ?? 0)),
                'max_discount_amount' => max(0, (float)($data['max_discount_amount'] ?? 0)),
                'min_spend_amount' => max(0, (float)($data['min_spend_amount'] ?? 0)),
                'trigger_product_id' => $this->nullable_int($data['trigger_product_id'] ?? null),
                'free_product_id' => $this->nullable_int($data['free_product_id'] ?? null),
                'free_qty' => max(0, (float)($data['free_qty'] ?? 0)),
                'valid_day_count' => max(0, (int)($data['valid_day_count'] ?? 0)),
                'point_cost' => max(0, (float)($data['point_cost'] ?? 0)),
                'stamp_cost' => max(0, (float)($data['stamp_cost'] ?? 0)),
                'start_date' => $this->nullable_date($data['start_date'] ?? ''),
                'end_date' => $this->nullable_date($data['end_date'] ?? ''),
                'is_active' => !empty($data['is_active']) ? 1 : 0,
            ];

            if ($id > 0) {
                if (!$this->record_exists('pos_voucher_campaign', $id)) {
                    throw new RuntimeException('Campaign voucher tidak ditemukan.');
                }
                $this->db->where('id', $id)->update('pos_voucher_campaign', $payload);
            } else {
                $this->db->insert('pos_voucher_campaign', $payload);
                $id = (int)$this->db->insert_id();
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan campaign voucher.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_voucher_campaign(int $id): array
    {
        return $this->toggle_active_record('pos_voucher_campaign', $id, null, 'Campaign voucher tidak ditemukan.', 'Gagal mengubah status campaign voucher.');
    }

    public function delete_voucher_campaign(int $id): array
    {
        if ((int)$this->db->from('pos_voucher_issue')->where('campaign_id', $id)->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Campaign voucher sudah punya voucher aktual. Hapus voucher aktualnya dulu atau nonaktifkan campaign.'];
        }
        return $this->delete_record('pos_voucher_campaign', $id, 'Campaign voucher tidak ditemukan.', 'Gagal menghapus campaign voucher.');
    }

    public function save_voucher_issue(array $data): array
    {
        if (!$this->db->table_exists('pos_voucher_issue')) {
            return ['ok' => false, 'message' => 'Tabel voucher aktual belum tersedia.'];
        }

        $id = (int)($data['id'] ?? 0);
        $campaignId = (int)($data['campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            return ['ok' => false, 'message' => 'Aturan voucher wajib dipilih.'];
        }

        $campaign = $this->db->from('pos_voucher_campaign')->where('id', $campaignId)->limit(1)->get()->row_array();
        if (!$campaign) {
            return ['ok' => false, 'message' => 'Aturan voucher tidak ditemukan.'];
        }

        $memberId = $this->nullable_int($data['member_id'] ?? null);
        $voucherCode = strtoupper(trim((string)($data['voucher_code'] ?? '')));
        $voucherIssueNo = strtoupper(trim((string)($data['voucher_issue_no'] ?? '')));
        $issuedAt = $this->nullable_datetime($data['issued_at'] ?? '') ?: date('Y-m-d H:i:s');
        $expiredAt = $this->nullable_datetime($data['expired_at'] ?? '');

        if ($memberId !== null && !$this->record_exists('crm_member', $memberId)) {
            return ['ok' => false, 'message' => 'Member voucher tidak ditemukan.'];
        }

        if ($expiredAt === null) {
            $validDayCount = (int)($campaign['valid_day_count'] ?? 0);
            if ($validDayCount > 0) {
                $expiredAt = date('Y-m-d H:i:s', strtotime($issuedAt . ' +' . $validDayCount . ' days'));
            }
        }

        $voucherType = strtoupper(trim((string)($campaign['voucher_type'] ?? 'AMOUNT')));
        $amountSnapshot = 0.0;
        $percentSnapshot = 0.0;
        if ($voucherType === 'PERCENT') {
            $percentSnapshot = round((float)($campaign['discount_value'] ?? 0), 4);
        } else {
            $amountSnapshot = round((float)($campaign['discount_value'] ?? 0), 2);
        }

        $this->db->trans_begin();
        try {
            if ($voucherIssueNo === '') {
                $voucherIssueNo = $this->generate_voucher_issue_no($issuedAt);
            } elseif ($this->code_exists('pos_voucher_issue', 'voucher_issue_no', $voucherIssueNo, $id)) {
                throw new RuntimeException('Nomor voucher sudah dipakai.');
            }

            if ($voucherCode === '') {
                $voucherCode = $this->generate_random_code('VCR-', 12, 'pos_voucher_issue', 'voucher_code', $id);
            } elseif ($this->code_exists('pos_voucher_issue', 'voucher_code', $voucherCode, $id)) {
                throw new RuntimeException('Kode voucher sudah dipakai.');
            }

            $payload = [
                'voucher_issue_no' => $voucherIssueNo,
                'campaign_id' => $campaignId,
                'member_id' => $memberId,
                'voucher_code' => $voucherCode,
                'voucher_status' => 'OPEN',
                'amount_snapshot' => $amountSnapshot,
                'percent_snapshot' => $percentSnapshot,
                'issued_at' => $issuedAt,
                'expired_at' => $expiredAt,
                'notes' => $this->nullable_text($data['notes'] ?? ''),
            ];

            if ($id > 0) {
                $existing = $this->db->from('pos_voucher_issue')->where('id', $id)->limit(1)->get()->row_array();
                if (!$existing) {
                    throw new RuntimeException('Voucher tidak ditemukan.');
                }
                if (strtoupper((string)($existing['voucher_status'] ?? 'OPEN')) === 'REDEEMED') {
                    throw new RuntimeException('Voucher yang sudah dipakai tidak bisa diubah manual.');
                }
                $payload['voucher_status'] = (string)($existing['voucher_status'] ?? 'OPEN');
                $this->db->where('id', $id)->update('pos_voucher_issue', $payload);
            } else {
                $this->db->insert('pos_voucher_issue', $payload);
                $id = (int)$this->db->insert_id();
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan voucher.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_voucher_issue(int $id): array
    {
        if (!$this->db->table_exists('pos_voucher_issue')) {
            return ['ok' => false, 'message' => 'Tabel voucher aktual belum tersedia.'];
        }

        $row = $this->db->from('pos_voucher_issue')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Voucher tidak ditemukan.'];
        }

        $status = strtoupper(trim((string)($row['voucher_status'] ?? 'OPEN')));
        if ($status === 'REDEEMED') {
            return ['ok' => false, 'message' => 'Voucher yang sudah dipakai tidak bisa dibatalkan.'];
        }

        $newStatus = $status === 'VOID' ? 'OPEN' : 'VOID';
        $this->db->trans_begin();
        try {
            $this->db->where('id', $id)->update('pos_voucher_issue', ['voucher_status' => $newStatus]);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status voucher.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'voucher_status' => $newStatus];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete_voucher_issue(int $id): array
    {
        if (!$this->db->table_exists('pos_voucher_issue')) {
            return ['ok' => false, 'message' => 'Tabel voucher aktual belum tersedia.'];
        }
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Voucher tidak ditemukan.'];
        }

        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        $started = false;
        try {
            if (!$this->db->trans_begin()) throw new RuntimeException('delete_begin');
            $started = true;
            $participants = [];
            if ($this->db->table_exists('evt_gowes_participant')) {
                // Match public claim lock order: participant first, then issued voucher.
                $query = $this->db->query('SELECT id FROM evt_gowes_participant WHERE voucher_issue_id = ? FOR UPDATE', [$id]);
                if (!$query) throw new RuntimeException('participant_lock');
                $participants = $query->result_array();
            }
            $query = $this->db->query('SELECT * FROM pos_voucher_issue WHERE id = ? FOR UPDATE', [$id]);
            if (!$query) throw new RuntimeException('voucher_lock');
            $row = $query->row_array();
            if (!$row) throw new DomainException('Voucher tidak ditemukan.');
            if (strtoupper((string)($row['voucher_status'] ?? 'OPEN')) === 'REDEEMED' || !empty($row['redeemed_at'])) {
                throw new DomainException('Voucher yang sudah dipakai tidak bisa dihapus.');
            }
            // Preserve transaction history even when a voucher status was changed manually.
            foreach (['pos_voucher_redemption', 'pos_voucher_usage', 'pos_redeem_transaction'] as $table) {
                if (!$this->db->table_exists($table)) continue;
                $query = $this->db->query('SELECT id FROM ' . $table . ' WHERE voucher_issue_id = ? LIMIT 1 FOR UPDATE', [$id]);
                if (!$query) throw new RuntimeException('voucher_history');
                if ($query->num_rows()) throw new DomainException('Voucher sudah memiliki riwayat pemakaian atau redeem dan tidak bisa dihapus.');
            }
            if ($participants) {
                if (!$this->db->where('voucher_issue_id', $id)->update('evt_gowes_participant', [
                    'voucher_issue_id' => null, 'claimed_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
                ]) || $this->db->affected_rows() !== count($participants)) throw new RuntimeException('claim_release');
            }
            if (!$this->db->where('id', $id)->delete('pos_voucher_issue') || $this->db->affected_rows() !== 1) {
                throw new RuntimeException('voucher_delete');
            }
            if (!$this->db->trans_status() || !$this->db->trans_commit()) throw new RuntimeException('delete_commit');
            return ['ok' => true, 'id' => $id, 'participant_claim_reset' => !empty($participants)];
        } catch (Throwable $error) {
            $dbError = $this->db->error();
            if ($started) $this->db->trans_rollback();
            if ($error instanceof DomainException) return ['ok' => false, 'message' => $error->getMessage()];
            log_message('error', 'Voucher deletion failed; database code ' . (int)($dbError['code'] ?? $error->getCode()));
            return ['ok' => false, 'message' => (int)($dbError['code'] ?? $error->getCode()) === 1451
                ? 'Voucher masih terkait data lain dan tidak bisa dihapus. Nonaktifkan voucher bila diperlukan.'
                : 'Gagal menghapus voucher. Perubahan dibatalkan; silakan coba lagi.'];
        } finally {
            $this->db->db_debug = $debug;
        }
    }

    private function save_point_like_record(string $type, array $data): array
    {
        $isPoint = $type === 'point';
        $table = $isPoint ? 'pos_point_rule' : 'pos_stamp_campaign';
        $codeColumn = $isPoint ? 'rule_code' : 'campaign_code';
        $nameColumn = $isPoint ? 'rule_name' : 'campaign_name';
        $prefix = $isPoint ? 'PNT-' : 'STP-';
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data[$nameColumn] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => $isPoint ? 'Nama rule poin wajib diisi.' : 'Nama campaign stamp wajib diisi.'];
        }

        $earnMode = strtoupper(trim((string)($data['earn_mode'] ?? ($isPoint ? 'AMOUNT' : 'TXN'))));
        $allowed = $isPoint ? ['AMOUNT', 'PRODUCT', 'FLAT'] : ['TXN', 'AMOUNT', 'PRODUCT'];
        if (!in_array($earnMode, $allowed, true)) {
            $earnMode = $allowed[0];
        }

        $code = strtoupper(trim((string)($data[$codeColumn] ?? '')));
        $this->db->trans_begin();
        try {
            if ($code === '') {
                $code = $this->generate_named_code($table, $codeColumn, $name, $prefix);
            } elseif ($this->code_exists($table, $codeColumn, $code, $id)) {
                throw new RuntimeException($isPoint ? 'Kode rule poin sudah dipakai.' : 'Kode campaign stamp sudah dipakai.');
            }

            if ($isPoint) {
                $spendBasis = strtoupper(trim((string)($data['spend_basis'] ?? 'NET')));
                if (!in_array($spendBasis, ['NET', 'GROSS'], true)) {
                    $spendBasis = 'NET';
                }
                $payload = [
                    'rule_code' => $code,
                    'rule_name' => $name,
                    'earn_mode' => $earnMode,
                    'spend_basis' => $spendBasis,
                    'amount_per_point' => max(0, (float)($data['amount_per_point'] ?? 0)),
                    'flat_point' => max(0, (float)($data['flat_point'] ?? 0)),
                    'required_product_id' => $this->nullable_int($data['required_product_id'] ?? null),
                    'min_spend_amount' => max(0, (float)($data['min_spend_amount'] ?? 0)),
                    'point_expiry_days' => max(0, (int)($data['point_expiry_days'] ?? 0)),
                    'is_active' => !empty($data['is_active']) ? 1 : 0,
                ];
            } else {
                $payload = [
                    'campaign_code' => $code,
                    'campaign_name' => $name,
                    'earn_mode' => $earnMode,
                    'amount_step' => max(0, (float)($data['amount_step'] ?? 0)),
                    'stamp_per_earn' => max(0, (float)($data['stamp_per_earn'] ?? 0)),
                    'required_product_id' => $this->nullable_int($data['required_product_id'] ?? null),
                    'redeem_required_stamp' => max(0, (float)($data['redeem_required_stamp'] ?? 0)),
                    'start_date' => $this->nullable_date($data['start_date'] ?? ''),
                    'end_date' => $this->nullable_date($data['end_date'] ?? ''),
                    'stamp_expiry_days' => max(0, (int)($data['stamp_expiry_days'] ?? 0)),
                    'is_active' => !empty($data['is_active']) ? 1 : 0,
                ];
            }

            if ($id > 0) {
                if (!$this->record_exists($table, $id)) {
                    throw new RuntimeException($isPoint ? 'Rule poin tidak ditemukan.' : 'Campaign stamp tidak ditemukan.');
                }
                $this->db->where('id', $id)->update($table, $payload);
            } else {
                $this->db->insert($table, $payload);
                $id = (int)$this->db->insert_id();
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($isPoint ? 'Gagal menyimpan rule poin.' : 'Gagal menyimpan campaign stamp.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function paginate_rows(CI_DB_query_builder $db, array $select, array $orderBy, int $page, int $limit): array
    {
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $db->select(implode(', ', $select));
        foreach ($orderBy as $column => $direction) {
            $db->order_by($column, $direction);
        }
        $rows = $db->limit($limit, $offset)->get()->result_array();
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

    private function apply_search_filter(CI_DB_query_builder $db, string $q, array $columns): void
    {
        if ($q === '') {
            return;
        }
        $db->group_start();
        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $db->like($column, $q);
            } else {
                $db->or_like($column, $q);
            }
        }
        $db->group_end();
    }

    private function apply_active_filter(CI_DB_query_builder $db, string $status, string $column = 'is_active'): void
    {
        if ($status === 'ACTIVE') {
            $db->where($column, 1);
        } elseif ($status === 'INACTIVE') {
            $db->where($column, 0);
        }
    }

    private function toggle_active_record(string $table, int $id, ?callable $extraPayload, string $notFoundMessage, string $defaultErrorMessage): array
    {
        $row = $this->db->from($table)->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => $notFoundMessage];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $payload = ['is_active' => $newValue];
        if ($extraPayload !== null) {
            $payload = array_merge($payload, (array)$extraPayload($row, $newValue));
        }

        $this->db->trans_begin();
        try {
            $this->db->where('id', $id)->update($table, $payload);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException($defaultErrorMessage);
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function delete_record(string $table, int $id, string $notFoundMessage, string $defaultErrorMessage): array
    {
        $row = $this->db->from($table)->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => $notFoundMessage];
        }

        $this->db->trans_begin();
        try {
            $this->db->where('id', $id)->delete($table);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException($defaultErrorMessage);
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function record_exists(string $table, int $id): bool
    {
        return (int)$this->db->from($table)->where('id', $id)->count_all_results() > 0;
    }

    private function distinct_non_empty_values(string $table, string $column): array
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists($column, $table)) {
            return [];
        }

        $rows = $this->db->select($column)
            ->from($table)
            ->where($column . ' IS NOT NULL', null, false)
            ->where("TRIM({$column}) != ''", null, false)
            ->group_by($column)
            ->order_by($column, 'ASC')
            ->get()
            ->result_array();

        return array_values(array_map(static function ($row) use ($column) {
            return trim((string)($row[$column] ?? ''));
        }, $rows));
    }

    private function code_exists(string $table, string $column, string $code, int $excludeId = 0): bool
    {
        $db = $this->db->from($table)->where($column, $code);
        if ($excludeId > 0) {
            $db->where('id !=', $excludeId);
        }
        return (int)$db->count_all_results() > 0;
    }

    /** Return every record whose stored number is the same after normalization. */
    private function member_phone_matches(string $phone, int $excludeId = 0): array
    {
        if ($phone === '') {
            return [];
        }

        $query = $this->db->select('id, member_no, member_name, mobile_phone')
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

    private function generate_member_no(?string $joinedAt = null): string
    {
        $joinedAt = $joinedAt ?: date('Y-m-d H:i:s');
        $prefix = 'MEM-' . date('Ymd', strtotime($joinedAt));
        $row = $this->db->query(
            'SELECT member_no FROM crm_member WHERE member_no LIKE ? ORDER BY member_no DESC LIMIT 1',
            [$prefix . '-%']
        )->row_array();
        $next = 1;
        if (!empty($row['member_no'])) {
            $parts = explode('-', (string)$row['member_no']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_named_code(string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        $slug = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim($name)));
        $slug = trim((string)$slug, '-');
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $base = trim($prefix . $slug, '-');
        $code = substr($base, 0, $maxLen);
        $seq = 1;
        while ($this->code_exists($table, $column, $code, $excludeId)) {
            $suffix = '-' . str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
            $code = substr($base, 0, max(1, $maxLen - strlen($suffix))) . $suffix;
            $seq++;
        }
        return $code;
    }

    private function generate_voucher_issue_no(?string $issuedAt = null): string
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

    private function generate_random_code(string $prefix, int $length, string $table, string $column, int $excludeId = 0): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bodyLength = max(4, $length - strlen($prefix));
        do {
            $body = '';
            for ($i = 0; $i < $bodyLength; $i++) {
                $body .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $prefix . $body;
        } while ($this->code_exists($table, $column, $code, $excludeId));
        return $code;
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $totalPages = max(1, (int)ceil($total / max(1, $limit)));
        $page = min(max(1, $page), $totalPages);
        $offset = ($page - 1) * $limit;
        return [$page, $offset, $totalPages];
    }

    private function nullable_text($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullable_date($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function nullable_datetime($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function nullable_int($value): ?int
    {
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function nullable_decimal($value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $f = (float)$value;
        return $f != 0.0 ? $f : null;
    }

    // ── Redeem ────────────────────────────────────────────────

    public function get_member_redeem_info(int $memberId): ?array
    {
        if (!$this->db->table_exists('crm_member')) {
            return null;
        }
        $member = $this->db->query(
            "SELECT m.*,
                COALESCE((SELECT pl.balance_after FROM pos_point_ledger pl WHERE pl.member_id = m.id ORDER BY pl.id DESC LIMIT 1), m.point_balance_cache) AS point_balance,
                COALESCE((SELECT sl.balance_after FROM pos_stamp_ledger sl WHERE sl.member_id = m.id ORDER BY sl.id DESC LIMIT 1), 0) AS stamp_balance
             FROM crm_member m WHERE m.id = ? LIMIT 1",
            [$memberId]
        )->row_array();
        if (!$member) {
            return null;
        }

        $pointBalance = (float)($member['point_balance'] ?? $member['point_balance_cache'] ?? 0);
        $stampBalance = (float)($member['stamp_balance'] ?? $member['stamp_balance_cache'] ?? 0);

        $openVouchers = [];
        if ($this->db->table_exists('pos_voucher_issue')) {
            $openVouchers = $this->db
                ->select('vi.id, vi.voucher_code, vi.voucher_issue_no, vi.amount_snapshot, vi.percent_snapshot, vi.expired_at, vi.notes AS voucher_notes, vc.campaign_name, vc.voucher_type, vc.discount_value, vc.max_discount_amount, vc.min_spend_amount')
                ->from('pos_voucher_issue vi')
                ->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left')
                ->where('vi.member_id', $memberId)
                ->where('vi.voucher_status', 'OPEN')
                ->order_by('vi.expired_at', 'ASC')
                ->order_by('vi.id', 'ASC')
                ->get()->result_array();
        }

        $stampCampaigns = [];
        if ($this->db->table_exists('pos_stamp_campaign')) {
            $stampCampaigns = $this->db
                ->select('id, campaign_code, campaign_name, redeem_required_stamp, start_date, end_date')
                ->from('pos_stamp_campaign')
                ->where('is_active', 1)
                ->order_by('redeem_required_stamp', 'ASC')
                ->order_by('campaign_name', 'ASC')
                ->get()->result_array();
        }

        return [
            'member'              => $member,
            'point_balance'       => $pointBalance,
            'stamp_balance'       => $stampBalance,
            'open_vouchers'       => $openVouchers,
            'open_voucher_count'  => count($openVouchers),
            'stamp_campaigns'     => $stampCampaigns,
        ];
    }

    public function redeem_rows(array $filters): array
    {
        if (!$this->db->table_exists('pos_redeem_transaction')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }

        $q         = trim((string)($filters['q'] ?? ''));
        $memberId  = (int)($filters['member_id'] ?? 0);
        $type      = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
        $dateFrom  = trim((string)($filters['date_from'] ?? ''));
        $dateTo    = trim((string)($filters['date_to'] ?? ''));
        $page      = max(1, (int)($filters['page'] ?? 1));
        $limit     = max(1, min(100, (int)($filters['limit'] ?? 25)));

        $db = $this->db
            ->from('pos_redeem_transaction rt')
            ->join('crm_member m', 'm.id = rt.member_id', 'left');

        if ($q !== '') {
            $db->group_start()
                ->like('rt.redeem_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.member_no', $q)
                ->or_like('rt.reward_desc', $q)
                ->or_like('rt.voucher_code', $q)
                ->group_end();
        }
        if ($memberId > 0) {
            $db->where('rt.member_id', $memberId);
        }
        if (in_array($type, ['POINT', 'STAMP', 'VOUCHER'], true)) {
            $db->where('rt.redeem_type', $type);
        }
        if ($dateFrom !== '') {
            $db->where('DATE(rt.created_at) >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $db->where('DATE(rt.created_at) <=', $dateTo);
        }

        return $this->paginate_rows(
            $db,
            ['rt.*', 'm.member_name', 'm.member_no', 'm.mobile_phone'],
            ['rt.created_at' => 'DESC'],
            $page,
            $limit
        );
    }

    public function process_point_redeem(array $data): array
    {
        if (!$this->db->table_exists('pos_redeem_transaction')) {
            return ['ok' => false, 'message' => 'Tabel redeem belum tersedia. Jalankan SQL migration terlebih dahulu.'];
        }

        $memberId   = (int)($data['member_id'] ?? 0);
        $pointsUsed = (float)($data['points_used'] ?? 0);
        $rewardDesc = trim((string)($data['reward_desc'] ?? ''));
        $notes      = trim((string)($data['notes'] ?? ''));

        if ($memberId <= 0) {
            return ['ok' => false, 'message' => 'Member wajib dipilih.'];
        }
        if ($pointsUsed <= 0) {
            return ['ok' => false, 'message' => 'Jumlah poin yang ditukarkan harus lebih dari 0.'];
        }
        if ($rewardDesc === '') {
            return ['ok' => false, 'message' => 'Keterangan reward wajib diisi.'];
        }

        $this->db->trans_begin();
        try {
            $member = $this->db->query('SELECT * FROM crm_member WHERE id = ? FOR UPDATE', [$memberId])->row_array();
            if (!$member) {
                throw new RuntimeException('Member tidak ditemukan.');
            }

            $currentBalance = (float)($member['point_balance_cache'] ?? 0);
            if ($pointsUsed > $currentBalance + 0.0001) {
                throw new RuntimeException(
                    sprintf('Saldo poin tidak cukup. Saldo saat ini: %s poin, dibutuhkan: %s poin.',
                        number_format($currentBalance, 2), number_format($pointsUsed, 2))
                );
            }

            $balanceAfter = round($currentBalance - $pointsUsed, 4);

            $ledgerNote = $rewardDesc . ($notes !== '' ? ' | ' . $notes : '');
            $this->db->insert('pos_point_ledger', [
                'member_id'     => $memberId,
                'order_id'      => null,
                'payment_id'    => null,
                'rule_id'       => null,
                'ledger_type'   => 'REDEEM',
                'points_in'     => 0,
                'points_out'    => $pointsUsed,
                'balance_after' => $balanceAfter,
                'notes'         => $ledgerNote,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            $ledgerId = (int)$this->db->insert_id();

            $this->db->where('id', $memberId)->update('crm_member', ['point_balance_cache' => $balanceAfter]);

            $redeemNo = $this->generate_redeem_no();
            $this->db->insert('pos_redeem_transaction', [
                'redeem_no'       => $redeemNo,
                'member_id'       => $memberId,
                'redeem_type'     => 'POINT',
                'point_ledger_id' => $ledgerId,
                'points_used'     => $pointsUsed,
                'stamp_ledger_id' => null,
                'stamps_used'     => null,
                'voucher_issue_id'=> null,
                'voucher_code'    => null,
                'reward_type'     => 'CUSTOM',
                'reward_desc'     => $rewardDesc,
                'reward_amount'   => $this->nullable_decimal($data['reward_amount'] ?? null),
                'notes'           => $notes ?: null,
                'redeemed_by'     => $this->nullable_int($this->session->userdata('user_id')),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->db->insert_id();

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan redeem poin.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'redeem_no' => $redeemNo, 'balance_after' => $balanceAfter];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function process_stamp_redeem(array $data): array
    {
        if (!$this->db->table_exists('pos_redeem_transaction')) {
            return ['ok' => false, 'message' => 'Tabel redeem belum tersedia. Jalankan SQL migration terlebih dahulu.'];
        }

        $memberId   = (int)($data['member_id'] ?? 0);
        $campaignId = (int)($data['campaign_id'] ?? 0);
        $notes      = trim((string)($data['notes'] ?? ''));

        if ($memberId <= 0) {
            return ['ok' => false, 'message' => 'Member wajib dipilih.'];
        }
        if ($campaignId <= 0) {
            return ['ok' => false, 'message' => 'Campaign stamp wajib dipilih.'];
        }

        $this->db->trans_begin();
        try {
            $member = $this->db->query('SELECT * FROM crm_member WHERE id = ? FOR UPDATE', [$memberId])->row_array();
            if (!$member) {
                throw new RuntimeException('Member tidak ditemukan.');
            }

            $campaign = $this->db->from('pos_stamp_campaign')->where('id', $campaignId)->where('is_active', 1)->limit(1)->get()->row_array();
            if (!$campaign) {
                throw new RuntimeException('Campaign stamp tidak ditemukan atau tidak aktif.');
            }

            $currentBalance = (float)($member['stamp_balance_cache'] ?? 0);
            $required       = (float)($campaign['redeem_required_stamp'] ?? 0);

            if ($required <= 0) {
                throw new RuntimeException('Campaign stamp ini tidak mempunyai syarat jumlah stamp untuk ditebus.');
            }
            if ($currentBalance < $required - 0.0001) {
                throw new RuntimeException(
                    sprintf('Stamp tidak cukup. Dibutuhkan %.2f stamp, saldo saat ini: %.2f stamp.', $required, $currentBalance)
                );
            }

            $balanceAfter = round($currentBalance - $required, 4);
            $rewardDesc   = 'Redeem stamp: ' . (string)($campaign['campaign_name'] ?? '');
            $ledgerNote   = $rewardDesc . ($notes !== '' ? ' | ' . $notes : '');

            $this->db->insert('pos_stamp_ledger', [
                'member_id'     => $memberId,
                'order_id'      => null,
                'payment_id'    => null,
                'campaign_id'   => $campaignId,
                'ledger_type'   => 'REDEEM',
                'stamp_in'      => 0,
                'stamp_out'     => $required,
                'balance_after' => $balanceAfter,
                'notes'         => $ledgerNote,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            $ledgerId = (int)$this->db->insert_id();

            $this->db->where('id', $memberId)->update('crm_member', ['stamp_balance_cache' => $balanceAfter]);

            $redeemNo = $this->generate_redeem_no();
            $this->db->insert('pos_redeem_transaction', [
                'redeem_no'       => $redeemNo,
                'member_id'       => $memberId,
                'redeem_type'     => 'STAMP',
                'point_ledger_id' => null,
                'points_used'     => null,
                'stamp_ledger_id' => $ledgerId,
                'stamps_used'     => $required,
                'voucher_issue_id'=> null,
                'voucher_code'    => null,
                'reward_type'     => 'CUSTOM',
                'reward_desc'     => $rewardDesc,
                'reward_amount'   => null,
                'notes'           => $notes ?: null,
                'redeemed_by'     => $this->nullable_int($this->session->userdata('user_id')),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->db->insert_id();

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan redeem stamp.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'redeem_no' => $redeemNo, 'balance_after' => $balanceAfter];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function process_voucher_redeem(array $data): array
    {
        if (!$this->db->table_exists('pos_redeem_transaction')) {
            return ['ok' => false, 'message' => 'Tabel redeem belum tersedia. Jalankan SQL migration terlebih dahulu.'];
        }

        $memberId       = (int)($data['member_id'] ?? 0);
        $voucherIssueId = (int)($data['voucher_issue_id'] ?? 0);
        $notes          = trim((string)($data['notes'] ?? ''));

        if ($memberId <= 0) {
            return ['ok' => false, 'message' => 'Member wajib dipilih.'];
        }
        if ($voucherIssueId <= 0) {
            return ['ok' => false, 'message' => 'Voucher wajib dipilih.'];
        }

        $this->db->trans_begin();
        try {
            $voucher = $this->db->query(
                'SELECT vi.*, vc.campaign_name, vc.voucher_type FROM pos_voucher_issue vi
                 LEFT JOIN pos_voucher_campaign vc ON vc.id = vi.campaign_id
                 WHERE vi.id = ? AND vi.member_id = ? FOR UPDATE',
                [$voucherIssueId, $memberId]
            )->row_array();

            if (!$voucher) {
                throw new RuntimeException('Voucher tidak ditemukan atau bukan milik member ini.');
            }

            $status = strtoupper((string)($voucher['voucher_status'] ?? 'OPEN'));
            if ($status !== 'OPEN') {
                throw new RuntimeException('Voucher sudah tidak bisa dipakai (status: ' . $status . ').');
            }

            $expiredAt = (string)($voucher['expired_at'] ?? '');
            if ($expiredAt !== '' && strtotime($expiredAt) < time()) {
                throw new RuntimeException('Voucher sudah kadaluarsa pada ' . date('d/m/Y', strtotime($expiredAt)) . '.');
            }

            $now = date('Y-m-d H:i:s');

            $this->db->where('id', $voucherIssueId)->update('pos_voucher_issue', [
                'voucher_status' => 'REDEEMED',
                'redeemed_at'    => $now,
                'updated_at'     => $now,
            ]);

            $redeemAmount = (float)($voucher['amount_snapshot'] ?? 0);
            $this->db->insert('pos_voucher_redemption', [
                'voucher_issue_id' => $voucherIssueId,
                'member_id'        => $memberId,
                'order_id'         => null,
                'payment_id'       => null,
                'redeem_amount'    => $redeemAmount,
                'redeemed_at'      => $now,
                'notes'            => $notes ?: null,
            ]);

            $redeemNo   = $this->generate_redeem_no();
            $campaignNm = (string)($voucher['campaign_name'] ?? $voucher['voucher_code'] ?? '');
            $rewardDesc = $campaignNm !== '' ? 'Voucher ' . $campaignNm : 'Voucher ' . (string)($voucher['voucher_code'] ?? '');

            $this->db->insert('pos_redeem_transaction', [
                'redeem_no'        => $redeemNo,
                'member_id'        => $memberId,
                'redeem_type'      => 'VOUCHER',
                'point_ledger_id'  => null,
                'points_used'      => null,
                'stamp_ledger_id'  => null,
                'stamps_used'      => null,
                'voucher_issue_id' => $voucherIssueId,
                'voucher_code'     => (string)($voucher['voucher_code'] ?? ''),
                'reward_type'      => 'DISCOUNT_AMOUNT',
                'reward_desc'      => $rewardDesc,
                'reward_amount'    => $redeemAmount > 0 ? $redeemAmount : null,
                'notes'            => $notes ?: null,
                'redeemed_by'      => $this->nullable_int($this->session->userdata('user_id')),
                'created_at'       => $now,
            ]);
            $id = (int)$this->db->insert_id();

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan redeem voucher.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'redeem_no' => $redeemNo];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Redeem Rules ──────────────────────────────────────────

    public function stamp_campaign_select_options(): array
    {
        if (!$this->db->table_exists('pos_stamp_campaign')) {
            return [];
        }
        $rows = $this->db->select('id, campaign_name, redeem_required_stamp')
            ->from('pos_stamp_campaign')
            ->where('is_active', 1)
            ->order_by('campaign_name', 'ASC')
            ->get()->result_array();
        return array_map(static function ($r): array {
            return ['value' => (int)$r['id'], 'label' => (string)$r['campaign_name']];
        }, $rows);
    }

    public function redeem_rule_rows(array $filters): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return ['rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'limit' => 25, 'total_pages' => 1]];
        }

        $q          = trim((string)($filters['q'] ?? ''));
        $status     = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $costType   = strtoupper(trim((string)($filters['cost_type'] ?? 'ALL')));
        $rewardType = strtoupper(trim((string)($filters['reward_type'] ?? 'ALL')));
        $page       = max(1, (int)($filters['page'] ?? 1));
        $limit      = max(1, min(100, (int)($filters['limit'] ?? 25)));

        $db = $this->db
            ->from('pos_redeem_rule rr')
            ->join('pos_stamp_campaign sc', 'sc.id = rr.stamp_campaign_id', 'left')
            ->join('pos_voucher_campaign vc', 'vc.id = rr.voucher_campaign_id', 'left');

        if ($q !== '') {
            $db->group_start()
                ->like('rr.rule_name', $q)
                ->or_like('rr.rule_code', $q)
                ->or_like('rr.reward_notes', $q)
                ->or_like('sc.campaign_name', $q)
                ->or_like('vc.campaign_name', $q)
                ->group_end();
        }
        $this->apply_active_filter($db, $status, 'rr.is_active');
        if (in_array($costType, ['POINT', 'STAMP', 'BOTH'], true)) {
            $db->where('rr.cost_type', $costType);
        }
        if (in_array($rewardType, ['VOUCHER','PRODUCT','MERCHANDISE','DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','OTHER'], true)) {
            $db->where('rr.reward_type', $rewardType);
        }

        return $this->paginate_rows(
            $db,
            ['rr.*', 'sc.campaign_name AS stamp_campaign_name', 'vc.campaign_name AS voucher_campaign_name'],
            ['rr.rule_name' => 'ASC'],
            $page,
            $limit
        );
    }

    public function save_redeem_rule(array $data): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return ['ok' => false, 'message' => 'Tabel redeem rule belum tersedia. Jalankan SQL migration terlebih dahulu.'];
        }

        $id         = (int)($data['id'] ?? 0);
        $name       = trim((string)($data['rule_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama rule wajib diisi.'];
        }

        $costType   = strtoupper(trim((string)($data['cost_type'] ?? 'POINT')));
        if (!in_array($costType, ['POINT', 'STAMP', 'BOTH'], true)) {
            $costType = 'POINT';
        }
        $rewardType = strtoupper(trim((string)($data['reward_type'] ?? 'DISCOUNT_AMOUNT')));
        if (!in_array($rewardType, ['VOUCHER','PRODUCT','MERCHANDISE','DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','OTHER'], true)) {
            $rewardType = 'DISCOUNT_AMOUNT';
        }

        $prevDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $code = strtoupper(trim((string)($data['rule_code'] ?? '')));
            if ($code === '') {
                $code = $this->generate_named_code('pos_redeem_rule', 'rule_code', $name, 'RR-', $id);
            } elseif ($this->code_exists('pos_redeem_rule', 'rule_code', $code, $id)) {
                throw new RuntimeException('Kode rule sudah digunakan oleh rule lain.');
            }

            $payload = [
                'rule_code'           => $code,
                'rule_name'           => $name,
                'description'         => $this->nullable_text($data['description'] ?? ''),
                'cost_type'           => $costType,
                'point_cost'          => in_array($costType, ['POINT','BOTH'], true) ? $this->nullable_decimal($data['point_cost'] ?? null) : null,
                'stamp_campaign_id'   => in_array($costType, ['STAMP','BOTH'], true) ? $this->nullable_int($data['stamp_campaign_id'] ?? null) : null,
                'stamp_cost'          => in_array($costType, ['STAMP','BOTH'], true) ? $this->nullable_decimal($data['stamp_cost'] ?? null) : null,
                'reward_type'         => $rewardType,
                'voucher_campaign_id' => $rewardType === 'VOUCHER' ? $this->nullable_int($data['voucher_campaign_id'] ?? null) : null,
                'product_id'          => in_array($rewardType, ['PRODUCT','FREE_PRODUCT'], true) ? $this->nullable_int($data['product_id'] ?? null) : null,
                'product_qty'         => in_array($rewardType, ['PRODUCT','FREE_PRODUCT'], true) ? $this->nullable_decimal($data['product_qty'] ?? null) : null,
                'discount_amount'     => $rewardType === 'DISCOUNT_AMOUNT' ? $this->nullable_decimal($data['discount_amount'] ?? null) : null,
                'discount_percent'    => $rewardType === 'DISCOUNT_PERCENT' ? $this->nullable_decimal($data['discount_percent'] ?? null) : null,
                'reward_notes'        => $this->nullable_text($data['reward_notes'] ?? ''),
                'min_spend_amount'    => $this->nullable_decimal($data['min_spend_amount'] ?? null),
                'stock_qty'           => $this->nullable_int($data['stock_qty'] ?? null),
                'valid_days'          => isset($data['valid_days']) && $data['valid_days'] > 0 ? (int)$data['valid_days'] : null,
                'is_active'           => !empty($data['is_active']) ? 1 : 0,
            ];

            if ($id > 0) {
                if (!$this->record_exists('pos_redeem_rule', $id)) {
                    throw new RuntimeException('Rule redeem tidak ditemukan.');
                }
                $this->db->where('id', $id)->update('pos_redeem_rule', $payload);
            } else {
                $payload['redeemed_count'] = 0;
                $this->db->insert('pos_redeem_rule', $payload);
                $id = (int)$this->db->insert_id();
            }

            if ($this->db->trans_status() === false) {
                $dbErr = $this->db->error();
                throw new RuntimeException($dbErr['message'] ?: 'Gagal menyimpan rule redeem. Pastikan SQL migration sudah dijalankan.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $prevDebug;
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $prevDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_redeem_rule(int $id): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return ['ok' => false, 'message' => 'Tabel redeem rule belum tersedia.'];
        }
        return $this->toggle_active_record('pos_redeem_rule', $id, null, 'Rule redeem tidak ditemukan.', 'Gagal mengubah status rule.');
    }

    public function delete_redeem_rule(int $id): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return ['ok' => false, 'message' => 'Tabel redeem rule belum tersedia.'];
        }
        $inUse = (int)$this->db->from('pos_redeem_transaction')->where('rule_id', $id)->count_all_results();
        if ($inUse > 0) {
            return ['ok' => false, 'message' => 'Rule tidak bisa dihapus karena sudah pernah digunakan dalam transaksi redeem (' . $inUse . ' transaksi).'];
        }
        return $this->delete_record('pos_redeem_rule', $id, 'Rule redeem tidak ditemukan.', 'Gagal menghapus rule.');
    }

    public function redeem_rule_options(string $costType = 'ALL'): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return [];
        }
        $db = $this->db
            ->select('rr.id, rr.rule_name, rr.cost_type, rr.reward_type, rr.point_cost, rr.stamp_cost, rr.discount_amount, rr.discount_percent, rr.reward_notes, rr.valid_days, rr.stock_qty, rr.redeemed_count, sc.campaign_name AS stamp_campaign_name, vc.campaign_name AS voucher_campaign_name')
            ->from('pos_redeem_rule rr')
            ->join('pos_stamp_campaign sc', 'sc.id = rr.stamp_campaign_id', 'left')
            ->join('pos_voucher_campaign vc', 'vc.id = rr.voucher_campaign_id', 'left')
            ->where('rr.is_active', 1);

        if (in_array($costType, ['POINT', 'STAMP', 'BOTH'], true)) {
            $db->group_start()
                ->where('rr.cost_type', $costType)
                ->or_where('rr.cost_type', 'BOTH')
                ->group_end();
        }

        return $db->order_by('rr.rule_name', 'ASC')->get()->result_array();
    }

    public function process_rule_redeem(int $memberId, int $ruleId, string $notes, int $operatorId): array
    {
        if (!$this->db->table_exists('pos_redeem_rule')) {
            return ['ok' => false, 'message' => 'Tabel redeem rule tidak tersedia.'];
        }
        if ($memberId <= 0) return ['ok' => false, 'message' => 'Member tidak valid.'];
        if ($ruleId <= 0)   return ['ok' => false, 'message' => 'Rule redeem tidak valid.'];

        $prevDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            // 1. Load rule
            $rule = $this->db->from('pos_redeem_rule')
                ->where('id', $ruleId)->where('is_active', 1)->limit(1)->get()->row_array();
            if (!$rule) throw new RuntimeException('Rule redeem tidak ditemukan atau tidak aktif.');

            // 2. Check stock
            if ($rule['stock_qty'] !== null && (int)$rule['stock_qty'] <= (int)($rule['redeemed_count'] ?? 0)) {
                throw new RuntimeException('Stok reward sudah habis.');
            }

            // 3. Check validity (created_at + valid_days)
            if (!empty($rule['valid_days'])) {
                $expiryTs = strtotime((string)$rule['created_at']) + ((int)$rule['valid_days'] * 86400);
                if (time() > $expiryTs) {
                    throw new RuntimeException('Rule redeem sudah kadaluarsa (berlaku ' . $rule['valid_days'] . ' hari sejak dibuat).');
                }
            }

            // 4. Lock member row, then read actual balance from ledger
            $member = $this->db->query('SELECT * FROM crm_member WHERE id = ? FOR UPDATE', [$memberId])->row_array();
            if (!$member) throw new RuntimeException('Member tidak ditemukan.');

            $pointRow = $this->db->query('SELECT balance_after FROM pos_point_ledger WHERE member_id = ? ORDER BY id DESC LIMIT 1', [$memberId])->row_array();
            $stampRow = $this->db->query('SELECT balance_after FROM pos_stamp_ledger WHERE member_id = ? ORDER BY id DESC LIMIT 1', [$memberId])->row_array();
            $pointBal = (float)($pointRow['balance_after'] ?? $member['point_balance_cache'] ?? 0);
            $stampBal = (float)($stampRow['balance_after'] ?? $member['stamp_balance_cache'] ?? 0);
            $costType = $rule['cost_type'];

            $pointLedgerId = null;
            $stampLedgerId = null;
            $ledgerNote    = $rule['rule_name'] . ($notes !== '' ? ' | ' . $notes : '');

            // 5. Deduct POINT
            if ($costType === 'POINT' || $costType === 'BOTH') {
                $cost = (float)($rule['point_cost'] ?? 0);
                if ($cost <= 0) throw new RuntimeException('Biaya poin pada rule ini tidak valid.');
                if ($pointBal < $cost - 0.0001) {
                    throw new RuntimeException(sprintf(
                        'Saldo poin tidak cukup. Dibutuhkan %s poin, saldo saat ini %s poin.',
                        number_format($cost, 0, ',', '.'), number_format($pointBal, 0, ',', '.')
                    ));
                }
                $pointAfter = round($pointBal - $cost, 4);
                $this->db->insert('pos_point_ledger', [
                    'member_id'     => $memberId,
                    'order_id'      => null,
                    'payment_id'    => null,
                    'rule_id'       => null,
                    'ledger_type'   => 'REDEEM',
                    'points_in'     => 0,
                    'points_out'    => $cost,
                    'balance_after' => $pointAfter,
                    'notes'         => $ledgerNote,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
                $pointLedgerId = (int)$this->db->insert_id();
                $this->db->where('id', $memberId)->update('crm_member', ['point_balance_cache' => $pointAfter]);
                $pointBal = $pointAfter;
            }

            // 6. Deduct STAMP
            if ($costType === 'STAMP' || $costType === 'BOTH') {
                $cost     = (float)($rule['stamp_cost'] ?? 0);
                $campId   = $this->nullable_int($rule['stamp_campaign_id'] ?? null);
                if ($cost <= 0) throw new RuntimeException('Biaya stamp pada rule ini tidak valid.');
                if ($stampBal < $cost - 0.0001) {
                    throw new RuntimeException(sprintf(
                        'Saldo stamp tidak cukup. Dibutuhkan %s stamp, saldo saat ini %s stamp.',
                        number_format($cost, 0, ',', '.'), number_format($stampBal, 0, ',', '.')
                    ));
                }
                $stampAfter = round($stampBal - $cost, 4);
                $this->db->insert('pos_stamp_ledger', [
                    'member_id'     => $memberId,
                    'order_id'      => null,
                    'payment_id'    => null,
                    'campaign_id'   => $campId,
                    'ledger_type'   => 'REDEEM',
                    'stamp_in'      => 0,
                    'stamp_out'     => $cost,
                    'balance_after' => $stampAfter,
                    'notes'         => $ledgerNote,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
                $stampLedgerId = (int)$this->db->insert_id();
                $this->db->where('id', $memberId)->update('crm_member', ['stamp_balance_cache' => $stampAfter]);
                $stampBal = $stampAfter;
            }

            // 7. Generate voucher untuk semua reward type
            $vCode          = 'VR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
            $rewardType     = $rule['reward_type'];
            $expiredAt      = !empty($rule['valid_days'])
                ? date('Y-m-d H:i:s', strtotime('+' . (int)$rule['valid_days'] . ' days'))
                : null;

            $voucherRow = [
                'campaign_id'      => null,
                'redeem_rule_id'   => $ruleId,
                'member_id'        => $memberId,
                'voucher_issue_no' => $vCode,
                'voucher_code'     => $vCode,
                'voucher_status'   => 'OPEN',
                'amount_snapshot'  => 0,
                'percent_snapshot' => 0,
                'min_spend_amount' => $rule['min_spend_amount'] ?? null,
                'issued_at'        => date('Y-m-d H:i:s'),
                'expired_at'       => $expiredAt,
                'notes'            => $rule['rule_name'],
            ];

            if ($rewardType === 'VOUCHER' && !empty($rule['voucher_campaign_id'])) {
                // Reward dari campaign voucher — tetap pakai campaign
                $vc = $this->db->query(
                    'SELECT * FROM pos_voucher_campaign WHERE id = ? AND is_active = 1 LIMIT 1',
                    [$rule['voucher_campaign_id']]
                )->row_array();
                if ($vc) {
                    $voucherRow['campaign_id']     = $vc['id'];
                    $voucherRow['amount_snapshot'] = (float)($vc['discount_value'] ?? 0);
                }
            } elseif ($rewardType === 'DISCOUNT_AMOUNT') {
                $voucherRow['amount_snapshot'] = (float)($rule['discount_amount'] ?? 0);
            } elseif ($rewardType === 'DISCOUNT_PERCENT') {
                $voucherRow['percent_snapshot'] = (float)($rule['discount_percent'] ?? 0);
            } elseif (in_array($rewardType, ['PRODUCT', 'FREE_PRODUCT'], true)) {
                // Ambil nama produk untuk notes
                $productName = '';
                if (!empty($rule['product_id'])) {
                    $prod = $this->db->query(
                        'SELECT product_name FROM mst_product WHERE id = ? LIMIT 1',
                        [$rule['product_id']]
                    )->row_array();
                    $productName = $prod['product_name'] ?? '';
                }
                $qty = $rule['product_qty'] ? (float)$rule['product_qty'] : 1;
                $voucherRow['notes'] = 'Gratis: ' . ($productName ?: $rule['rule_name'])
                    . ($qty != 1 ? ' x' . rtrim(rtrim(number_format($qty, 2), '0'), '.') : '');
            } elseif ($rewardType === 'MERCHANDISE') {
                $voucherRow['notes'] = 'Merchandise: ' . ($rule['reward_notes'] ?: $rule['rule_name']);
            } else {
                // OTHER
                $voucherRow['notes'] = $rule['reward_notes'] ?: $rule['rule_name'];
            }

            $this->db->insert('pos_voucher_issue', $voucherRow);
            $voucherIssueId = (int)$this->db->insert_id();

            // 8. Write redeem transaction
            $redeemNo   = $this->generate_redeem_no();
            $redeemType = ($costType === 'BOTH' || $costType === 'POINT') ? 'POINT' : 'STAMP';
            $ruleIdCol  = $this->db->field_exists('rule_id', 'pos_redeem_transaction') ? $ruleId : null;

            $txRow = [
                'redeem_no'        => $redeemNo,
                'member_id'        => $memberId,
                'redeem_type'      => $redeemType,
                'point_ledger_id'  => $pointLedgerId,
                'points_used'      => $pointLedgerId ? (float)$rule['point_cost'] : null,
                'stamp_ledger_id'  => $stampLedgerId,
                'stamps_used'      => $stampLedgerId ? (float)$rule['stamp_cost'] : null,
                'voucher_issue_id' => $voucherIssueId,
                'voucher_code'     => $vCode,
                'reward_type'      => 'CUSTOM',
                'reward_desc'      => $rule['rule_name'],
                'reward_amount'    => $rule['discount_amount'] ?? $rule['discount_percent'] ?? null,
                'notes'            => $notes ?: null,
                'redeemed_by'      => $operatorId ?: null,
                'created_at'       => date('Y-m-d H:i:s'),
            ];
            if ($ruleIdCol !== null) {
                $txRow['rule_id'] = $ruleIdCol;
            }
            $this->db->insert('pos_redeem_transaction', $txRow);

            // 9. Increment redeemed_count
            $this->db->query('UPDATE pos_redeem_rule SET redeemed_count = redeemed_count + 1 WHERE id = ?', [$ruleId]);

            if ($this->db->trans_status() === false) {
                $dbErr = $this->db->error();
                throw new RuntimeException($dbErr['message'] ?: 'Gagal memproses redeem.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $prevDebug;

            // Bangun deskripsi voucher untuk UI
            $voucherDesc = $voucherRow['notes'];
            if ($rewardType === 'DISCOUNT_AMOUNT' && $voucherRow['amount_snapshot'] > 0) {
                $voucherDesc = 'Diskon Rp ' . number_format($voucherRow['amount_snapshot'], 0, ',', '.');
                if (!empty($rule['min_spend_amount'])) {
                    $voucherDesc .= ' (min. belanja Rp ' . number_format((float)$rule['min_spend_amount'], 0, ',', '.') . ')';
                }
            } elseif ($rewardType === 'DISCOUNT_PERCENT' && $voucherRow['percent_snapshot'] > 0) {
                $voucherDesc = 'Diskon ' . rtrim(rtrim(number_format($voucherRow['percent_snapshot'], 2), '0'), '.') . '%';
                if (!empty($rule['min_spend_amount'])) {
                    $voucherDesc .= ' (min. belanja Rp ' . number_format((float)$rule['min_spend_amount'], 0, ',', '.') . ')';
                }
            }

            return [
                'ok'            => true,
                'redeem_no'     => $redeemNo,
                'voucher_code'  => $vCode,
                'voucher_desc'  => $voucherDesc,
                'point_balance' => $pointBal,
                'stamp_balance' => $stampBal,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $prevDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function generate_redeem_no(): string
    {
        $prefix = 'RDM-' . date('Ymd');
        $row    = $this->db->query(
            'SELECT redeem_no FROM pos_redeem_transaction WHERE redeem_no LIKE ? ORDER BY redeem_no DESC LIMIT 1',
            [$prefix . '-%']
        )->row_array();
        $next = 1;
        if (!empty($row['redeem_no'])) {
            $parts = explode('-', (string)$row['redeem_no']);
            $next  = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }
}
