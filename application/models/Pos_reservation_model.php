<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reservation is intentionally a staging document. It holds customer intent
 * and a linked POS deposit, then becomes a normal POS order only after a
 * cashier verifies it.
 */
class Pos_reservation_model extends CI_Model
{
    private const STATUS_PENDING = 'PENDING';
    private const STATUS_VERIFIED_ACTIVE = 'VERIFIED_ACTIVE';
    private const STATUS_VERIFIED_PAID = 'VERIFIED_PAID';
    private const STATUS_REJECTED = 'REJECTED';
    private const STATUS_CANCELLED = 'CANCELLED';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
    }

    public function schema_ready(): bool
    {
        foreach ([
            'pos_reservation',
            'pos_reservation_line',
            'pos_reservation_line_extra',
            'pos_reservation_payment',
            'pos_reservation_state_log',
        ] as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }

        if (!$this->db->field_exists('settlement_payment_id', 'pos_reservation')) {
            return false;
        }

        // The follow-up migration makes reservation creation independent of a
        // cashier session while preserving the logged-in account in the audit
        // trail. Do not present a writable screen against the half schema.
        foreach ([
            'created_by_user_id',
            'updated_by_user_id',
        ] as $column) {
            if (!$this->db->field_exists($column, 'pos_reservation')) {
                return false;
            }
        }
        if (!$this->db->field_exists('actor_user_id', 'pos_reservation_state_log')) {
            return false;
        }

        return true;
    }

    public function page_options(int $actorEmployeeId = 0): array
    {
        $outlets = $this->Pos_model->local_outlet_options();
        $activeSession = $actorEmployeeId > 0
            ? $this->Pos_model->find_active_cashier_session($actorEmployeeId)
            : null;
        $outletIds = array_values(array_filter(array_map(static function (array $outlet): int {
            return (int)($outlet['id'] ?? 0);
        }, $outlets)));
        $sessionOutletId = (int)($activeSession['outlet_id'] ?? 0);
        $defaultOutletId = in_array($sessionOutletId, $outletIds, true)
            ? $sessionOutletId
            : (!empty($outletIds) ? $outletIds[0] : 0);

        return [
            'outlets' => $outlets,
            'sales_channels' => $this->Pos_model->sales_channel_options(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
            'default_sales_channel_id' => (int)($this->Pos_model->default_sales_channel_id() ?? 0),
            // This is a convenience default only. Creating a reservation does
            // not require an active cashier session.
            'default_outlet_id' => $defaultOutletId,
            'default_outlet_source' => $sessionOutletId === $defaultOutletId && $defaultOutletId > 0
                ? 'ACTIVE_CASHIER_SESSION'
                : 'ACTIVE_OUTLET_FALLBACK',
        ];
    }

    public function reservation_rows(array $filters): array
    {
        if (!$this->schema_ready()) {
            return [
                'rows' => [],
                'meta' => $this->empty_meta($filters),
                'schema_ready' => false,
            ];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(10, min(100, (int)($filters['limit'] ?? 25)));

        $this->reservation_list_base_query($filters);
        $total = (int)$this->db->count_all_results();

        $this->reservation_list_base_query($filters);
        $rows = $this->db
            ->select("\n                r.*,\n                linked_order.order_no AS linked_order_no,\n                linked_order.status AS linked_order_status,\n                linked_order.paid_total AS linked_order_paid_total,\n                o.outlet_code,\n                o.outlet_name,\n                sc.channel_code,\n                sc.channel_name,\n                member.member_no,\n                member.member_name,\n                verified.employee_name AS verified_by_name,\n                (SELECT COUNT(*) FROM pos_reservation_line rl WHERE rl.reservation_id = r.id) AS line_count,\n                (SELECT GROUP_CONCAT(DISTINCT plm.method_name ORDER BY plm.method_name SEPARATOR ', ')\n                 FROM pos_reservation_payment rp\n                 JOIN pos_payment_line ppl ON ppl.payment_id = rp.payment_id\n                 JOIN pos_payment_method plm ON plm.id = ppl.payment_method_id\n                 WHERE rp.reservation_id = r.id\n                   AND rp.link_status <> 'VOID') AS deposit_method_names\n            ", false)
            ->order_by("CASE WHEN r.status = 'PENDING' THEN 0 ELSE 1 END", 'ASC', false)
            ->order_by('r.reservation_at', 'ASC')
            ->order_by('r.id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row = $this->present_header($row);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, (int)ceil($total / $limit)),
            ],
            'schema_ready' => true,
        ];
    }

    public function reservation_product_rows(array $filters): array
    {
        if (!$this->schema_ready()) {
            return [
                'rows' => [],
                'meta' => $this->empty_meta($filters),
                'schema_ready' => false,
            ];
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(10, min(200, (int)($filters['limit'] ?? 50)));

        $this->reservation_product_base_query($filters);
        $total = (int)$this->db->count_all_results();

        $this->reservation_product_base_query($filters);
        $rows = $this->db
            ->select("\n                rl.*,\n                r.reservation_no,\n                r.status AS reservation_status,\n                r.reservation_at,\n                r.customer_name,\n                r.customer_phone,\n                r.guest_count,\n                r.table_no,\n                r.remaining_amount,\n                r.order_id,\n                linked_order.order_no AS linked_order_no,\n                linked_order.status AS linked_order_status,\n                o.outlet_name,\n                p.product_code,\n                p.product_name,\n                b.bundle_code,\n                b.bundle_name,\n                operational.name AS operational_division_name,\n                product_division.name AS product_division_name,\n                u.code AS uom_code,\n                (SELECT COALESCE(SUM(extra.net_amount), 0)\n                 FROM pos_reservation_line_extra extra\n                 WHERE extra.reservation_line_id = rl.id) AS extra_total,\n                (SELECT GROUP_CONCAT(CONCAT(extra.qty, 'x ', em.extra_name) ORDER BY extra.line_no SEPARATOR ' | ')\n                 FROM pos_reservation_line_extra extra\n                 JOIN mst_extra em ON em.id = extra.extra_id\n                 WHERE extra.reservation_line_id = rl.id) AS extras_summary\n            ", false)
            ->order_by("CASE WHEN r.status = 'PENDING' THEN 0 ELSE 1 END", 'ASC', false)
            ->order_by('r.reservation_at', 'ASC')
            ->order_by('rl.operational_division_id', 'ASC')
            ->order_by('rl.line_no', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $effectiveStatus = $this->effective_status([
                'status' => (string)($row['reservation_status'] ?? ''),
                'linked_order_status' => (string)($row['linked_order_status'] ?? ''),
            ]);
            $row['reservation_status'] = $effectiveStatus;
            $row['reservation_status_label'] = $this->status_label($effectiveStatus);
            $row['line_total'] = round((float)($row['net_amount'] ?? 0) + (float)($row['extra_total'] ?? 0), 2);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, (int)ceil($total / $limit)),
            ],
            'schema_ready' => true,
        ];
    }

    public function find_reservation(int $id): ?array
    {
        if (!$this->schema_ready() || $id <= 0) {
            return null;
        }

        $header = $this->db
            ->select('r.*, o.outlet_code, o.outlet_name, sc.channel_code, sc.channel_name, member.member_no, member.member_name, member.mobile_phone AS member_mobile_phone, verified.employee_name AS verified_by_name, rejected.employee_name AS rejected_by_name, cancelled.employee_name AS cancelled_by_name, linked_order.order_no AS linked_order_no, linked_order.status AS linked_order_status, linked_order.paid_total AS linked_order_paid_total')
            ->from('pos_reservation r')
            ->join('pos_outlet o', 'o.id = r.outlet_id', 'left')
            ->join('pos_sales_channel sc', 'sc.id = r.sales_channel_id', 'left')
            ->join('crm_member member', 'member.id = r.member_id', 'left')
            ->join('org_employee verified', 'verified.id = r.verified_by', 'left')
            ->join('org_employee rejected', 'rejected.id = r.rejected_by', 'left')
            ->join('org_employee cancelled', 'cancelled.id = r.cancelled_by', 'left')
            ->join('pos_order linked_order', 'linked_order.id = r.order_id', 'left')
            ->where('r.id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $lines = $this->db
            ->select('rl.*, p.product_code, p.product_name, b.bundle_code, b.bundle_name, product_division.code AS product_division_code, product_division.name AS product_division_name, operational.code AS operational_division_code, operational.name AS operational_division_name, u.code AS uom_code')
            ->from('pos_reservation_line rl')
            ->join('mst_product p', 'p.id = rl.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = rl.bundle_id', 'left')
            ->join('mst_product_division product_division', 'product_division.id = rl.product_division_id_snapshot', 'left')
            ->join('mst_operational_division operational', 'operational.id = rl.operational_division_id', 'left')
            ->join('mst_uom u', 'u.id = rl.uom_id', 'left')
            ->where('rl.reservation_id', $id)
            ->order_by('rl.line_no', 'ASC')
            ->get()
            ->result_array();

        $lineIds = array_values(array_filter(array_map(static function ($line): int {
            return (int)($line['id'] ?? 0);
        }, $lines)));
        $extrasByLine = [];
        if (!empty($lineIds)) {
            $extras = $this->db
                ->select('extra.*, master.extra_code, master.extra_name, master.extra_type')
                ->from('pos_reservation_line_extra extra')
                ->join('mst_extra master', 'master.id = extra.extra_id', 'left')
                ->where_in('extra.reservation_line_id', $lineIds)
                ->order_by('extra.reservation_line_id', 'ASC')
                ->order_by('extra.line_no', 'ASC')
                ->get()
                ->result_array();
            foreach ($extras as $extra) {
                $extrasByLine[(int)$extra['reservation_line_id']][] = $extra;
            }
        }
        foreach ($lines as &$line) {
            $line['extras'] = $extrasByLine[(int)$line['id']] ?? [];
            $line['extra_total'] = round(array_sum(array_map(static function ($extra): float {
                return (float)($extra['net_amount'] ?? 0);
            }, $line['extras'])), 2);
        }
        unset($line);

        $payments = $this->db
            ->select("\n                rp.*, p.payment_no, p.payment_status, p.payment_type, p.net_amount AS payment_amount,\n                p.deposit_applied_amount, p.paid_at,\n                GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ') AS payment_method_names\n            ", false)
            ->from('pos_reservation_payment rp')
            ->join('pos_payment p', 'p.id = rp.payment_id', 'left')
            ->join('pos_payment_line ppl', 'ppl.payment_id = p.id', 'left')
            ->join('pos_payment_method pm', 'pm.id = ppl.payment_method_id', 'left')
            ->where('rp.reservation_id', $id)
            ->group_by('rp.id')
            ->order_by('rp.id', 'ASC')
            ->get()
            ->result_array();

        $hasActorUserColumn = $this->db->field_exists('actor_user_id', 'pos_reservation_state_log');
        $stateLogQuery = $this->db
            ->select($hasActorUserColumn
                ? 'log.*, COALESCE(employee.employee_name, actor_user.username) AS actor_name'
                : 'log.*, employee.employee_name AS actor_name', false)
            ->from('pos_reservation_state_log log')
            ->join('org_employee employee', 'employee.id = log.actor_employee_id', 'left');
        if ($hasActorUserColumn) {
            $stateLogQuery->join('auth_user actor_user', 'actor_user.id = log.actor_user_id', 'left');
        }
        $stateLog = $stateLogQuery
            ->where('log.reservation_id', $id)
            ->order_by('log.id', 'DESC')
            ->get()
            ->result_array();

        $header = $this->present_header($header);
        $header['lines'] = $lines;
        $header['payments'] = $payments;
        $header['state_log'] = $stateLog;

        return $header;
    }

    public function save_reservation(array $payload, int $actorEmployeeId, int $actorUserId = 0): array
    {
        if (!$this->schema_ready()) {
            return ['ok' => false, 'message' => 'Schema reservasi belum tersedia. Jalankan migration reservasi terlebih dahulu.'];
        }
        $actorUserId = max(0, $actorUserId);
        if ($actorUserId <= 0) {
            return ['ok' => false, 'message' => 'Sesi akun login tidak ditemukan. Silakan login ulang sebelum menyimpan reservasi.'];
        }

        $id = max(0, (int)($payload['id'] ?? 0));
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        if (!$this->record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet reservasi wajib dipilih.'];
        }

        $memberId = !empty($payload['member_id']) ? (int)$payload['member_id'] : null;
        $member = $memberId ? $this->member_row($memberId) : null;
        if ($memberId && !$member) {
            return ['ok' => false, 'message' => 'Member reservasi tidak ditemukan atau sudah tidak aktif.'];
        }
        $customerName = trim((string)($payload['customer_name'] ?? ''));
        if ($customerName === '' && $member) {
            $customerName = trim((string)($member['member_name'] ?? ''));
        }
        if ($customerName === '') {
            return ['ok' => false, 'message' => 'Nama customer reservasi wajib diisi.'];
        }

        $reservationAt = $this->normalize_datetime($payload['reservation_at'] ?? '');
        if ($reservationAt === null) {
            return ['ok' => false, 'message' => 'Tanggal dan jam reservasi wajib diisi dengan format yang valid.'];
        }
        $reservationEndAt = $this->normalize_datetime($payload['reservation_end_at'] ?? '', true);
        if ($reservationEndAt === null) {
            $reservationEndAt = date('Y-m-d H:i:s', strtotime('+4 hours', strtotime($reservationAt)));
        }
        if ($reservationEndAt !== null && strtotime($reservationEndAt) <= strtotime($reservationAt)) {
            return ['ok' => false, 'message' => 'Jam selesai reservasi harus setelah jam mulai.'];
        }

        $salesChannelId = max(0, (int)($payload['sales_channel_id'] ?? 0));
        if ($salesChannelId <= 0) {
            $salesChannelId = (int)($this->Pos_model->default_sales_channel_id() ?? 0);
        }
        $salesChannel = $salesChannelId > 0 ? $this->sales_channel_row($salesChannelId) : null;
        if ($salesChannelId > 0 && !$salesChannel) {
            return ['ok' => false, 'message' => 'Sales channel reservasi tidak ditemukan atau tidak aktif.'];
        }

        $serviceType = strtoupper(trim((string)($payload['service_type'] ?? 'DINE_IN')));
        if (!in_array($serviceType, ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'DINE_IN';
        }
        if ($salesChannel && !empty($salesChannel['allowed_service_type_list']) && !in_array($serviceType, (array)$salesChannel['allowed_service_type_list'], true)) {
            return ['ok' => false, 'message' => 'Tipe layanan tidak diizinkan oleh sales channel yang dipilih.'];
        }

        $normalized = $this->Pos_model->normalize_reservation_lines((array)($payload['lines'] ?? []), $outletId);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }
        $totals = $this->reservation_totals((array)($normalized['rows'] ?? []));
        $initialDeposit = $this->deposit_payload($payload);

        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $existing = null;
            if ($id > 0) {
                $existing = $this->db->query('SELECT * FROM pos_reservation WHERE id = ? LIMIT 1 FOR UPDATE', [$id])->row_array();
                if (!$existing) {
                    throw new RuntimeException('Reservasi tidak ditemukan.');
                }
                if ((string)($existing['status'] ?? '') !== self::STATUS_PENDING) {
                    throw new RuntimeException('Reservasi yang sudah diverifikasi, ditolak, atau dibatalkan tidak dapat diedit lagi.');
                }
                if ((int)($existing['member_id'] ?? 0) !== (int)($memberId ?? 0) && (float)($existing['deposit_total'] ?? 0) > 0.009) {
                    throw new RuntimeException('Member tidak dapat diganti karena reservasi sudah memiliki DP.');
                }
                if ((float)($existing['deposit_total'] ?? 0) > (float)($totals['grand_total'] ?? 0) + 0.009) {
                    throw new RuntimeException('Total tagihan baru lebih kecil dari DP yang sudah diterima. Kurangi atau kembalikan DP melalui pembatalan reservasi terlebih dahulu.');
                }
                if ((float)($initialDeposit['amount'] ?? 0) > 0.009) {
                    throw new RuntimeException('Tambahkan DP berikutnya melalui rincian reservasi agar jejaknya jelas.');
                }
            }

            $now = date('Y-m-d H:i:s');
            $headerPayload = [
                'reservation_at' => $reservationAt,
                'reservation_end_at' => $reservationEndAt,
                'outlet_id' => $outletId,
                'sales_channel_id' => $salesChannelId > 0 ? $salesChannelId : null,
                'service_type' => $serviceType,
                'member_id' => $memberId,
                'customer_name' => $this->limit_text($customerName, 150),
                'customer_phone' => $this->nullable_text($payload['customer_phone'] ?? ($member['mobile_phone'] ?? ''), 30),
                'customer_email' => $this->nullable_text($payload['customer_email'] ?? '', 150),
                'guest_count' => max(1, min(999, (int)($payload['guest_count'] ?? 1))),
                'table_no' => $this->nullable_text($payload['table_no'] ?? '', 40),
                'notes' => $this->nullable_text($payload['notes'] ?? '', 255),
                'subtotal_amount' => (float)$totals['subtotal_amount'],
                'discount_amount' => 0,
                'tax_amount' => 0,
                'service_amount' => 0,
                'grand_total' => (float)$totals['grand_total'],
                'remaining_amount' => (float)$totals['grand_total'],
                'updated_at' => $now,
            ];
            if ($this->db->field_exists('updated_by_user_id', 'pos_reservation')) {
                $headerPayload['updated_by_user_id'] = $actorUserId;
            }

            if ($existing) {
                $headerPayload['deposit_total'] = round((float)($existing['deposit_total'] ?? 0), 2);
                $headerPayload['deposit_applied_total'] = round((float)($existing['deposit_applied_total'] ?? 0), 2);
                $headerPayload['remaining_amount'] = round(max(0, (float)$totals['grand_total'] - (float)($existing['deposit_total'] ?? 0)), 2);
                $this->db->where('id', $id)->update('pos_reservation', $headerPayload);
                $this->delete_reservation_lines($id);
                $reservationId = $id;
                $reservationNo = (string)$existing['reservation_no'];
                $this->insert_state_log($reservationId, self::STATUS_PENDING, self::STATUS_PENDING, 'UPDATED', $actorEmployeeId, 'Rincian reservasi diperbarui.', $actorUserId);
            } else {
                $headerPayload += [
                    'reservation_no' => $this->generate_reservation_no($reservationAt),
                    'status' => self::STATUS_PENDING,
                    'deposit_total' => 0,
                    'deposit_applied_total' => 0,
                    'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                    'created_at' => $now,
                ];
                if ($this->db->field_exists('created_by_user_id', 'pos_reservation')) {
                    $headerPayload['created_by_user_id'] = $actorUserId;
                }
                $this->db->insert('pos_reservation', $headerPayload);
                $reservationId = (int)$this->db->insert_id();
                if ($reservationId <= 0) {
                    throw new RuntimeException('Gagal membuat nomor reservasi.');
                }
                $reservationNo = (string)$headerPayload['reservation_no'];
                $this->insert_state_log($reservationId, null, self::STATUS_PENDING, 'CREATED', $actorEmployeeId, 'Reservasi dibuat.', $actorUserId);
            }

            $this->insert_reservation_lines($reservationId, (array)($normalized['rows'] ?? []), $now);

            $depositResult = null;
            if (!$existing && (float)($initialDeposit['amount'] ?? 0) > 0.009) {
                if ((float)$initialDeposit['amount'] > (float)$totals['grand_total'] + 0.009) {
                    throw new RuntimeException('DP reservasi tidak boleh lebih besar dari total tagihan.');
                }
                $currentReservation = $this->db->from('pos_reservation')->where('id', $reservationId)->limit(1)->get()->row_array() ?: [];
                $depositResult = $this->create_and_link_deposit($currentReservation, $initialDeposit, $actorEmployeeId, $now, $actorUserId);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan reservasi.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;

            $saved = $this->find_reservation($reservationId);
            return [
                'ok' => true,
                'id' => $reservationId,
                'reservation_no' => $reservationNo,
                'deposit' => $depositResult,
                'reservation' => $saved,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function add_deposit(int $reservationId, array $payload, int $actorEmployeeId, int $actorUserId = 0): array
    {
        if (!$this->schema_ready()) {
            return ['ok' => false, 'message' => 'Schema reservasi belum tersedia. Jalankan migration reservasi terlebih dahulu.'];
        }
        $actorUserId = max(0, $actorUserId);
        if ($reservationId <= 0 || $actorUserId <= 0) {
            return ['ok' => false, 'message' => 'Reservasi atau sesi akun login tidak valid.'];
        }
        $depositPayload = $this->deposit_payload($payload);
        if ((float)($depositPayload['amount'] ?? 0) <= 0.009) {
            return ['ok' => false, 'message' => 'Nominal DP wajib lebih besar dari nol.'];
        }

        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $reservation = $this->db->query('SELECT * FROM pos_reservation WHERE id = ? LIMIT 1 FOR UPDATE', [$reservationId])->row_array();
            if (!$reservation) {
                throw new RuntimeException('Reservasi tidak ditemukan.');
            }
            if ((string)($reservation['status'] ?? '') !== self::STATUS_PENDING) {
                throw new RuntimeException('DP baru hanya dapat ditambahkan sebelum reservasi diverifikasi.');
            }

            $remaining = round(max(0, (float)($reservation['grand_total'] ?? 0) - (float)($reservation['deposit_total'] ?? 0)), 2);
            if ((float)$depositPayload['amount'] > $remaining + 0.009) {
                throw new RuntimeException('DP melebihi sisa tagihan reservasi. Sisa yang dapat diterima: Rp ' . number_format($remaining, 0, ',', '.'));
            }

            $result = $this->create_and_link_deposit($reservation, $depositPayload, $actorEmployeeId, date('Y-m-d H:i:s'), $actorUserId);
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menambahkan DP reservasi.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;

            return [
                'ok' => true,
                'deposit' => $result,
                'reservation' => $this->find_reservation($reservationId),
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Creates a normal POS order and applies linked DP. This method does not
     * verify the reservation status; the controller only completes it after
     * stock-commit queueing and printer payload preparation have succeeded.
     */
    public function prepare_verification(int $reservationId, int $actorEmployeeId, int $actorUserId = 0): array
    {
        if (!$this->schema_ready()) {
            return ['ok' => false, 'message' => 'Schema reservasi belum tersedia.'];
        }
        if ($reservationId <= 0 || $actorEmployeeId <= 0 || $actorUserId <= 0) {
            return ['ok' => false, 'message' => 'Reservasi atau sesi kasir tidak valid.'];
        }
        $session = $this->Pos_model->find_active_cashier_session($actorEmployeeId);
        if (!$session) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka. Verifikasi reservasi harus dilakukan dari sesi kasir aktif agar DP dan transaksi dapat diaudit.'];
        }

        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $reservation = $this->db->query('SELECT * FROM pos_reservation WHERE id = ? LIMIT 1 FOR UPDATE', [$reservationId])->row_array();
            if (!$reservation) {
                throw new RuntimeException('Reservasi tidak ditemukan.');
            }
            $status = strtoupper((string)($reservation['status'] ?? ''));
            if (in_array($status, [self::STATUS_VERIFIED_ACTIVE, self::STATUS_VERIFIED_PAID], true)) {
                $this->db->trans_commit();
                $this->db->db_debug = $previousDbDebug;
                return [
                    'ok' => true,
                    'already_verified' => true,
                    'order_id' => (int)($reservation['order_id'] ?? 0),
                    'is_paid' => $status === self::STATUS_VERIFIED_PAID,
                    'payment_id' => 0,
                ];
            }
            if ($status !== self::STATUS_PENDING) {
                throw new RuntimeException('Hanya reservasi yang masih menunggu dapat diterima kasir.');
            }
            if ((int)($session['outlet_id'] ?? 0) !== (int)($reservation['outlet_id'] ?? 0)) {
                throw new RuntimeException('Outlet reservasi harus sama dengan outlet sesi kasir yang sedang aktif.');
            }

            $now = date('Y-m-d H:i:s');
            $orderId = (int)($reservation['order_id'] ?? 0);
            $paymentId = 0;
            $depositApplied = round((float)($reservation['deposit_applied_total'] ?? 0), 2);
            if ($orderId > 0) {
                $order = $this->db->query('SELECT * FROM pos_order WHERE id = ? LIMIT 1 FOR UPDATE', [$orderId])->row_array();
                if (!$order) {
                    throw new RuntimeException('Order POS hasil reservasi tidak ditemukan. Hubungi administrator agar jejak reservasi tidak dilompati.');
                }
                if (!in_array((string)($order['status'] ?? ''), ['PENDING', 'CONFIRMED', 'PAID'], true)) {
                    throw new RuntimeException('Order POS hasil reservasi sudah berubah sehingga tidak dapat diverifikasi ulang dari reservasi.');
                }
                $payment = $this->db->from('pos_payment')
                    ->where('order_id', $orderId)
                    ->where('payment_type', 'FINAL')
                    ->order_by('id', 'ASC')
                    ->limit(1)
                    ->get()
                    ->row_array();
                $paymentId = (int)($payment['id'] ?? 0);
                $depositApplied = round((float)($order['paid_total'] ?? $depositApplied), 2);
                $existingOrderStatus = strtoupper(trim((string)($order['status'] ?? '')));
                if (in_array($existingOrderStatus, ['CONFIRMED', 'PAID'], true)) {
                    $this->db->trans_commit();
                    $this->db->db_debug = $previousDbDebug;
                    $grandTotal = round((float)($reservation['grand_total'] ?? 0), 2);
                    return [
                        'ok' => true,
                        'already_pos_finalized' => true,
                        'reservation_id' => $reservationId,
                        'order_id' => $orderId,
                        'payment_id' => $paymentId,
                        'deposit_applied_amount' => $depositApplied,
                        'remaining_amount' => round(max(0, $grandTotal - $depositApplied), 2),
                        'is_paid' => $existingOrderStatus === 'PAID' || $depositApplied + 0.009 >= $grandTotal,
                    ];
                }
            } else {
                $storedLines = $this->reservation_lines_for_order($reservationId);
                if (empty($storedLines)) {
                    throw new RuntimeException('Reservasi belum memiliki produk.');
                }

                // Harga yang sudah disetujui saat reservasi tetap dipakai. HPP
                // tidak ikut disalin dari dokumen reservasi karena harus menjadi
                // snapshot saat order benar-benar diterima kasir ke POS.
                $recalculated = $this->refresh_lines_for_pos_verification(
                    $storedLines,
                    (int)$reservation['outlet_id']
                );
                if (!($recalculated['ok'] ?? false)) {
                    throw new RuntimeException((string)($recalculated['message'] ?? 'Produk reservasi tidak dapat dihitung ulang saat verifikasi.'));
                }
                $lines = (array)($recalculated['rows'] ?? []);
                $recalculatedTotals = $this->reservation_totals($lines);
                if (abs((float)$recalculatedTotals['grand_total'] - (float)$reservation['grand_total']) > 0.01) {
                    throw new RuntimeException('Nilai reservasi tidak konsisten dengan rincian produk saat verifikasi. Periksa kembali reservasi sebelum diterima ke POS.');
                }

                $orderNo = $this->generate_order_no($now);
                $orderPayload = [
                    'order_no' => $orderNo,
                    'order_channel' => 'RESERVATION',
                    'order_scope' => 'REGULAR',
                    'service_type' => (string)$reservation['service_type'],
                    'sales_channel_id' => !empty($reservation['sales_channel_id']) ? (int)$reservation['sales_channel_id'] : null,
                    'outlet_id' => (int)$reservation['outlet_id'],
                    'terminal_id' => !empty($session['terminal_id']) ? (int)$session['terminal_id'] : null,
                    'shift_id' => !empty($session['shift_id']) ? (int)$session['shift_id'] : null,
                    'cashier_session_id' => !empty($session['id']) ? (int)$session['id'] : null,
                    'cashier_employee_id' => $actorEmployeeId,
                    'member_id' => !empty($reservation['member_id']) ? (int)$reservation['member_id'] : null,
                    'customer_name' => (string)$reservation['customer_name'],
                    'status' => 'PENDING',
                    'kitchen_status' => 'PENDING',
                    'stock_commit_status' => 'PENDING',
                    'ordered_at' => $now,
                    'guest_count' => max(1, (int)($reservation['guest_count'] ?? 1)),
                    'table_no' => $this->nullable_text($reservation['table_no'] ?? '', 40),
                    'subtotal_amount' => (float)$reservation['subtotal_amount'],
                    'discount_amount' => (float)$reservation['discount_amount'],
                    'promo_amount' => 0,
                    'voucher_amount' => 0,
                    'point_redeem_amount' => 0,
                    'compliment_amount' => 0,
                    'tax_amount' => (float)$reservation['tax_amount'],
                    'service_amount' => (float)$reservation['service_amount'],
                    'rounding_amount' => 0,
                    'grand_total' => (float)$reservation['grand_total'],
                    'paid_total' => 0,
                    'change_total' => 0,
                    'notes' => $this->reservation_order_notes($reservation),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $this->db->insert('pos_order', $orderPayload);
                $orderId = (int)$this->db->insert_id();
                if ($orderId <= 0) {
                    throw new RuntimeException('Gagal membentuk order POS dari reservasi.');
                }
                $this->insert_order_lines($orderId, $lines, $now);

                $depositApplications = $this->locked_reservation_deposits($reservationId);
                $remainingDue = round(max(0, (float)$reservation['grand_total']), 2);
                $appliedRows = [];
                foreach ($depositApplications as $deposit) {
                    if ($remainingDue <= 0.009) {
                        break;
                    }
                    $available = round(max(0, min(
                        (float)($deposit['linked_amount'] ?? 0) - (float)($deposit['applied_amount'] ?? 0),
                        (float)($deposit['payment_amount'] ?? 0) - (float)($deposit['payment_applied_amount'] ?? 0)
                    )), 2);
                    if ($available <= 0.009) {
                        continue;
                    }
                    $taken = round(min($available, $remainingDue), 2);
                    $remainingDue = round(max(0, $remainingDue - $taken), 2);
                    $appliedRows[] = $deposit + ['taken_amount' => $taken];
                    $depositApplied = round($depositApplied + $taken, 2);
                }

                $isDepositFullyCoveringOrder = $depositApplied + 0.009 >= (float)$reservation['grand_total'];
                if ($depositApplied > 0.009) {
                    // A partial DP is deliberately kept PENDING. The cashier
                    // later completes this same payment document, preventing
                    // a second FINAL payment from double-counting the sale.
                    $paymentId = $this->create_reservation_final_payment(
                        $reservation,
                        $orderId,
                        $session,
                        $actorEmployeeId,
                        $depositApplied,
                        $isDepositFullyCoveringOrder,
                        $now
                    );
                    foreach ($appliedRows as $deposit) {
                        $depositPaymentId = (int)($deposit['payment_id'] ?? 0);
                        $taken = round((float)($deposit['taken_amount'] ?? 0), 2);
                        if ($depositPaymentId <= 0 || $taken <= 0.009) {
                            continue;
                        }
                        $this->db->set('deposit_applied_amount', 'COALESCE(deposit_applied_amount,0)+' . $this->db->escape($taken), false)
                            ->where('id', $depositPaymentId)
                            ->update('pos_payment');
                        $newApplied = round((float)($deposit['applied_amount'] ?? 0) + $taken, 2);
                        $linkStatus = $newApplied + 0.009 >= (float)($deposit['linked_amount'] ?? 0) ? 'APPLIED' : 'PARTIAL';
                        $this->db->where('id', (int)$deposit['link_id'])->update('pos_reservation_payment', [
                            'applied_amount' => $newApplied,
                            'link_status' => $linkStatus,
                            'updated_at' => $now,
                        ]);
                        $this->db->insert('pos_payment_deposit_apply', [
                            'deposit_payment_id' => $depositPaymentId,
                            'applied_payment_id' => $paymentId,
                            'order_id' => $orderId,
                            'applied_amount' => $taken,
                            'apply_status' => 'APPLIED',
                            'notes' => 'DP reservasi ' . (string)$reservation['reservation_no'] . ' dipakai saat verifikasi kasir.',
                            'applied_at' => $now,
                            'created_at' => $now,
                        ]);
                    }
                }

                $this->db->where('id', $orderId)->update('pos_order', [
                    'paid_total' => $depositApplied,
                    'updated_at' => $now,
                ]);
                $this->db->insert('pos_order_state_log', [
                    'order_id' => $orderId,
                    'from_status' => 'DRAFT',
                    'to_status' => 'PENDING',
                    'event_code' => 'RESERVATION_ACCEPTED',
                    'actor_employee_id' => $actorEmployeeId,
                    'notes' => 'Order dibuat dari reservasi ' . (string)$reservation['reservation_no'] . '.',
                    'created_at' => $now,
                ]);
                $reservationPayload = [
                    'order_id' => $orderId,
                    'settlement_payment_id' => $paymentId > 0 ? $paymentId : null,
                    'deposit_applied_total' => $depositApplied,
                    'remaining_amount' => round(max(0, (float)$reservation['grand_total'] - $depositApplied), 2),
                    'updated_at' => $now,
                ];
                if ($this->db->field_exists('updated_by_user_id', 'pos_reservation')) {
                    $reservationPayload['updated_by_user_id'] = $actorUserId;
                }
                $this->db->where('id', $reservationId)->update('pos_reservation', $reservationPayload);
                $this->insert_state_log($reservationId, self::STATUS_PENDING, self::STATUS_PENDING, 'POS_ORDER_CREATED', $actorEmployeeId, 'Order POS ' . $orderNo . ' dibuat; harga reservasi dipertahankan dan HPP stok aktif dihitung ulang saat verifikasi kasir.', $actorUserId);
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyiapkan verifikasi reservasi.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;

            $grandTotal = round((float)($reservation['grand_total'] ?? 0), 2);
            return [
                'ok' => true,
                'reservation_id' => $reservationId,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'deposit_applied_amount' => $depositApplied,
                'remaining_amount' => round(max(0, $grandTotal - $depositApplied), 2),
                'is_paid' => $depositApplied + 0.009 >= $grandTotal,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function complete_verification(int $reservationId, int $orderId, int $actorEmployeeId, bool $isPaid, int $actorUserId = 0): array
    {
        if (!$this->schema_ready() || $reservationId <= 0 || $orderId <= 0) {
            return ['ok' => false, 'message' => 'Data finalisasi reservasi tidak lengkap.'];
        }

        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $reservation = $this->db->query('SELECT * FROM pos_reservation WHERE id = ? LIMIT 1 FOR UPDATE', [$reservationId])->row_array();
            if (!$reservation) {
                throw new RuntimeException('Reservasi tidak ditemukan saat finalisasi.');
            }
            if ((int)($reservation['order_id'] ?? 0) !== $orderId) {
                throw new RuntimeException('Order POS tidak cocok dengan reservasi yang diverifikasi.');
            }
            $targetStatus = $isPaid ? self::STATUS_VERIFIED_PAID : self::STATUS_VERIFIED_ACTIVE;
            if ((string)($reservation['status'] ?? '') === self::STATUS_PENDING) {
                $now = date('Y-m-d H:i:s');
                $reservationPayload = [
                    'status' => $targetStatus,
                    'verified_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                    'verified_at' => $now,
                    'updated_at' => $now,
                ];
                if ($this->db->field_exists('verified_by_user_id', 'pos_reservation')) {
                    $reservationPayload['verified_by_user_id'] = $actorUserId > 0 ? $actorUserId : null;
                }
                if ($this->db->field_exists('updated_by_user_id', 'pos_reservation')) {
                    $reservationPayload['updated_by_user_id'] = $actorUserId > 0 ? $actorUserId : null;
                }
                $this->db->where('id', $reservationId)->update('pos_reservation', $reservationPayload);
                $this->insert_state_log($reservationId, self::STATUS_PENDING, $targetStatus, 'VERIFIED', $actorEmployeeId, $isPaid ? 'Reservasi diterima dan sudah lunas.' : 'Reservasi diterima dan masuk ke order aktif POS.', $actorUserId);
            }
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memfinalkan reservasi.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'status' => $targetStatus];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function reject_reservation(int $reservationId, int $actorEmployeeId, int $actorUserId, string $reason, bool $refundDeposit = false): array
    {
        return $this->close_reservation($reservationId, $actorEmployeeId, $actorUserId, $reason, $refundDeposit, self::STATUS_REJECTED);
    }

    public function cancel_reservation(int $reservationId, int $actorEmployeeId, int $actorUserId, string $reason, bool $refundDeposit = false): array
    {
        return $this->close_reservation($reservationId, $actorEmployeeId, $actorUserId, $reason, $refundDeposit, self::STATUS_CANCELLED);
    }

    private function close_reservation(int $reservationId, int $actorEmployeeId, int $actorUserId, string $reason, bool $refundDeposit, string $targetStatus): array
    {
        if (!$this->schema_ready()) {
            return ['ok' => false, 'message' => 'Schema reservasi belum tersedia.'];
        }
        $reason = $this->nullable_text($reason, 255);
        if ($reservationId <= 0 || $actorUserId <= 0 || $reason === null) {
            return ['ok' => false, 'message' => 'Alasan penolakan atau pembatalan wajib diisi.'];
        }

        $previousDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $reservation = $this->db->query('SELECT * FROM pos_reservation WHERE id = ? LIMIT 1 FOR UPDATE', [$reservationId])->row_array();
            if (!$reservation) {
                throw new RuntimeException('Reservasi tidak ditemukan.');
            }
            if ((string)($reservation['status'] ?? '') !== self::STATUS_PENDING) {
                throw new RuntimeException('Reservasi yang sudah diverifikasi harus dibatalkan melalui void atau refund POS agar stok dan keuangannya tetap konsisten.');
            }

            $now = date('Y-m-d H:i:s');
            $refundCount = 0;
            if ($refundDeposit) {
                $links = $this->db
                    ->select('rp.*, p.deposit_applied_amount, p.payment_status')
                    ->from('pos_reservation_payment rp')
                    ->join('pos_payment p', 'p.id = rp.payment_id', 'inner')
                    ->where('rp.reservation_id', $reservationId)
                    ->where_in('rp.link_status', ['OPEN', 'PARTIAL'])
                    ->get()
                    ->result_array();
                foreach ($links as $link) {
                    if (round((float)($link['applied_amount'] ?? 0), 2) > 0.009 || round((float)($link['deposit_applied_amount'] ?? 0), 2) > 0.009) {
                        throw new RuntimeException('DP yang sudah dipakai tidak dapat dikembalikan dari reservasi. Gunakan refund POS.');
                    }
                    if ((string)($link['payment_status'] ?? '') === 'VOID') {
                        continue;
                    }
                    $void = $this->Pos_model->void_deposit((int)$link['payment_id'], $actorEmployeeId, $actorUserId);
                    if (!($void['ok'] ?? false)) {
                        throw new RuntimeException((string)($void['message'] ?? 'Gagal mengembalikan DP reservasi.'));
                    }
                    $this->db->where('id', (int)$link['id'])->update('pos_reservation_payment', [
                        'link_status' => 'VOID',
                        'voided_at' => $now,
                        'notes' => $this->limit_text('DP dikembalikan saat ' . strtolower($targetStatus) . ' reservasi.', 255),
                        'updated_at' => $now,
                    ]);
                    $refundCount++;
                }
            }

            $payload = [
                'status' => $targetStatus,
                'updated_at' => $now,
            ];
            if ($this->db->field_exists('updated_by_user_id', 'pos_reservation')) {
                $payload['updated_by_user_id'] = $actorUserId;
            }
            if ($targetStatus === self::STATUS_REJECTED) {
                $payload['rejected_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
                $payload['rejected_at'] = $now;
                $payload['rejection_reason'] = $reason;
                if ($this->db->field_exists('rejected_by_user_id', 'pos_reservation')) {
                    $payload['rejected_by_user_id'] = $actorUserId;
                }
            } else {
                $payload['cancelled_by'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
                $payload['cancelled_at'] = $now;
                $payload['cancellation_reason'] = $reason;
                if ($this->db->field_exists('cancelled_by_user_id', 'pos_reservation')) {
                    $payload['cancelled_by_user_id'] = $actorUserId;
                }
            }
            $this->db->where('id', $reservationId)->update('pos_reservation', $payload);
            $this->insert_state_log(
                $reservationId,
                self::STATUS_PENDING,
                $targetStatus,
                $targetStatus,
                $actorEmployeeId,
                $reason . ($refundCount > 0 ? ' DP dikembalikan ke rekening asal.' : ' DP yang ada tetap menjadi kredit customer.'),
                $actorUserId
            );

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memperbarui status reservasi.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'status' => $targetStatus, 'refunded_deposit_count' => $refundCount];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function reservation_list_base_query(array $filters): void
    {
        $this->db
            ->from('pos_reservation r')
            ->join('pos_outlet o', 'o.id = r.outlet_id', 'left')
            ->join('pos_sales_channel sc', 'sc.id = r.sales_channel_id', 'left')
            ->join('crm_member member', 'member.id = r.member_id', 'left')
            ->join('org_employee verified', 'verified.id = r.verified_by', 'left')
            ->join('pos_order linked_order', 'linked_order.id = r.order_id', 'left');
        $this->apply_reservation_filters($filters, false);
    }

    private function reservation_product_base_query(array $filters): void
    {
        $this->db
            ->from('pos_reservation_line rl')
            ->join('pos_reservation r', 'r.id = rl.reservation_id', 'inner')
            ->join('pos_outlet o', 'o.id = r.outlet_id', 'left')
            ->join('mst_product p', 'p.id = rl.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = rl.bundle_id', 'left')
            ->join('mst_operational_division operational', 'operational.id = rl.operational_division_id', 'left')
            ->join('mst_product_division product_division', 'product_division.id = rl.product_division_id_snapshot', 'left')
            ->join('mst_uom u', 'u.id = rl.uom_id', 'left')
            ->join('pos_order linked_order', 'linked_order.id = r.order_id', 'left');
        $this->apply_reservation_filters($filters, true);
    }

    private function apply_reservation_filters(array $filters, bool $lineQuery): void
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $statusTab = strtoupper(trim((string)($filters['status_tab'] ?? 'ACTIVE')));
        if (!in_array($statusTab, ['ACTIVE', 'COMPLETED', 'OVERDUE', 'ALL'], true)) {
            $statusTab = 'ACTIVE';
        }
        $dateFrom = $this->normalize_date($filters['date_from'] ?? '');
        $dateTo = $this->normalize_date($filters['date_to'] ?? '');

        if ($outletId > 0) {
            $this->db->where('r.outlet_id', $outletId);
        }
        if ($dateFrom !== null) {
            $this->db->where('r.reservation_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== null) {
            $this->db->where('r.reservation_at <=', $dateTo . ' 23:59:59');
        }
        $terminalOrderStatuses = ['PAID', 'SERVED', 'VOID', 'REFUND_FULL'];
        if ($statusTab === 'ACTIVE') {
            $this->db->group_start()
                ->group_start()
                    ->where('r.status', self::STATUS_PENDING)
                    ->where('r.reservation_at >=', date('Y-m-d 00:00:00'))
                ->group_end()
                ->or_group_start()
                    ->where('r.status', self::STATUS_VERIFIED_ACTIVE)
                    ->group_start()
                        ->where('linked_order.status IS NULL', null, false)
                        ->or_where_not_in('linked_order.status', $terminalOrderStatuses)
                    ->group_end()
                ->group_end()
            ->group_end();
        } elseif ($statusTab === 'COMPLETED') {
            $this->db->group_start()
                ->where_in('r.status', [self::STATUS_VERIFIED_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED])
                ->or_group_start()
                    ->where('r.status', self::STATUS_VERIFIED_ACTIVE)
                    ->where_in('linked_order.status', $terminalOrderStatuses)
                ->group_end()
            ->group_end();
        } elseif ($statusTab === 'OVERDUE') {
            // Keep both overdue branches together before applying the
            // optional text filter. Without this outer group, a search term
            // would only filter the VERIFIED_ACTIVE branch.
            $this->db->group_start()
                ->group_start()
                    ->where('r.status', self::STATUS_PENDING)
                    ->where('r.reservation_at <', date('Y-m-d H:i:s'))
                ->group_end()
                ->or_group_start()
                    ->where('r.status', self::STATUS_VERIFIED_ACTIVE)
                    ->where('r.reservation_at <', date('Y-m-d H:i:s'))
                    ->group_start()
                        ->where('linked_order.status IS NULL', null, false)
                        ->or_where_not_in('linked_order.status', $terminalOrderStatuses)
                    ->group_end()
                ->group_end()
            ->group_end();
        }
        if ($q === '') {
            return;
        }

        $this->db->group_start()
            ->like('r.reservation_no', $q)
            ->or_like('r.customer_name', $q)
            ->or_like('r.customer_phone', $q)
            ->or_like('r.table_no', $q)
            ->or_like('member.member_name', $q);
        if ($lineQuery) {
            $this->db->or_like('p.product_name', $q)
                ->or_like('p.product_code', $q)
                ->or_like('b.bundle_name', $q)
                ->or_like('b.bundle_code', $q);
        }
        $this->db->group_end();
    }

    private function insert_reservation_lines(int $reservationId, array $lines, string $now): void
    {
        $lineNo = 1;
        foreach ($lines as $line) {
            $payload = [
                'reservation_id' => $reservationId,
                'line_no' => $lineNo++,
                'product_id' => (int)$line['product_id'],
                'bundle_id' => !empty($line['bundle_id']) ? (int)$line['bundle_id'] : null,
                'product_division_id_snapshot' => !empty($line['product_division_id_snapshot']) ? (int)$line['product_division_id_snapshot'] : null,
                'operational_division_id' => !empty($line['operational_division_id']) ? (int)$line['operational_division_id'] : null,
                'uom_id' => !empty($line['uom_id']) ? (int)$line['uom_id'] : null,
                'qty' => (float)$line['qty'],
                'unit_price' => (float)$line['unit_price'],
                'discount_amount' => 0,
                'net_amount' => (float)$line['net_amount'],
                'hpp_standard_snapshot' => (float)$line['hpp_standard_snapshot'],
                'hpp_live_snapshot' => (float)$line['hpp_live_snapshot'],
                'cogs_amount' => (float)$line['cogs_amount'],
                'availability_mode_snapshot' => (string)($line['availability_mode_snapshot'] ?? 'AUTO'),
                'notes' => $this->nullable_text($line['notes'] ?? '', 255),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->db->insert('pos_reservation_line', $payload);
            $reservationLineId = (int)$this->db->insert_id();
            if ($reservationLineId <= 0) {
                throw new RuntimeException('Gagal menyimpan rincian produk reservasi.');
            }

            $extraNo = 1;
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $this->db->insert('pos_reservation_line_extra', [
                    'reservation_id' => $reservationId,
                    'reservation_line_id' => $reservationLineId,
                    'line_no' => $extraNo++,
                    'extra_id' => (int)$extra['extra_id'],
                    'qty' => (float)$extra['qty'],
                    'unit_price' => (float)$extra['unit_price'],
                    'net_amount' => (float)$extra['net_amount'],
                    'cost_amount_snapshot' => (float)$extra['cost_amount_snapshot'],
                    'notes' => $this->nullable_text($extra['notes'] ?? '', 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function delete_reservation_lines(int $reservationId): void
    {
        $lineIds = $this->db->select('id')->from('pos_reservation_line')->where('reservation_id', $reservationId)->get()->result_array();
        $lineIds = array_values(array_filter(array_map(static function ($row): int {
            return (int)($row['id'] ?? 0);
        }, $lineIds)));
        if (!empty($lineIds)) {
            $this->db->where_in('reservation_line_id', $lineIds)->delete('pos_reservation_line_extra');
        }
        $this->db->where('reservation_id', $reservationId)->delete('pos_reservation_line');
    }

    private function reservation_lines_for_order(int $reservationId): array
    {
        $lines = $this->db
            ->from('pos_reservation_line')
            ->where('reservation_id', $reservationId)
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();
        if (empty($lines)) {
            return [];
        }
        $lineIds = array_values(array_map(static function ($line): int {
            return (int)$line['id'];
        }, $lines));
        $extras = $this->db
            ->from('pos_reservation_line_extra')
            ->where_in('reservation_line_id', $lineIds)
            ->order_by('reservation_line_id', 'ASC')
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();
        $extraMap = [];
        foreach ($extras as $extra) {
            $extraMap[(int)$extra['reservation_line_id']][] = $extra;
        }
        foreach ($lines as &$line) {
            $line['extras'] = $extraMap[(int)$line['id']] ?? [];
        }
        unset($line);

        return $lines;
    }

    /**
     * Build fresh POS snapshots from the reservation's locked selling prices.
     * This preserves the customer's agreed bill while taking HPP and extra cost
     * from the active outlet data at the moment cashier accepts the order.
     */
    private function refresh_lines_for_pos_verification(array $storedLines, int $outletId): array
    {
        $payloadLines = [];
        foreach ($storedLines as $line) {
            $extras = [];
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $extras[] = [
                    'extra_id' => (int)($extra['extra_id'] ?? 0),
                    'qty' => (float)($extra['qty'] ?? 0),
                    // Keep the booked selling price; the normalizer refreshes cost.
                    'unit_price' => (float)($extra['unit_price'] ?? 0),
                    'notes' => $this->nullable_text($extra['notes'] ?? '', 255),
                ];
            }
            $payloadLines[] = [
                'product_id' => (int)($line['product_id'] ?? 0),
                'bundle_id' => !empty($line['bundle_id']) ? (int)$line['bundle_id'] : null,
                'qty' => (float)($line['qty'] ?? 0),
                // Keep the booked selling price; the normalizer refreshes HPP.
                'unit_price' => (float)($line['unit_price'] ?? 0),
                'extras' => $extras,
                'notes' => $this->nullable_text($line['notes'] ?? '', 255),
            ];
        }

        return $this->Pos_model->normalize_reservation_lines($payloadLines, $outletId);
    }

    private function insert_order_lines(int $orderId, array $lines, string $now): void
    {
        $lineNo = 1;
        foreach ($lines as $line) {
            $this->db->insert('pos_order_line', [
                'order_id' => $orderId,
                'line_no' => $lineNo++,
                'product_id' => (int)$line['product_id'],
                'bundle_id' => !empty($line['bundle_id']) ? (int)$line['bundle_id'] : null,
                'line_type' => 'PRODUCT',
                'product_division_id_snapshot' => !empty($line['product_division_id_snapshot']) ? (int)$line['product_division_id_snapshot'] : null,
                'operational_division_id' => !empty($line['operational_division_id']) ? (int)$line['operational_division_id'] : null,
                'uom_id' => !empty($line['uom_id']) ? (int)$line['uom_id'] : null,
                'qty' => (float)$line['qty'],
                'unit_price' => (float)$line['unit_price'],
                'discount_amount' => (float)($line['discount_amount'] ?? 0),
                'net_amount' => (float)$line['net_amount'],
                'hpp_standard_snapshot' => (float)$line['hpp_standard_snapshot'],
                'hpp_live_snapshot' => (float)$line['hpp_live_snapshot'],
                'cogs_amount' => (float)$line['cogs_amount'],
                'availability_mode_snapshot' => (string)($line['availability_mode_snapshot'] ?? 'AUTO'),
                'line_status' => 'OPEN',
                'process_status' => 'NOT_PROCESSED',
                'notes' => $this->nullable_text($line['notes'] ?? '', 255),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $orderLineId = (int)$this->db->insert_id();
            if ($orderLineId <= 0) {
                throw new RuntimeException('Gagal menyalin produk reservasi ke order POS.');
            }
            $extraNo = 1;
            foreach ((array)($line['extras'] ?? []) as $extra) {
                $this->db->insert('pos_order_line_extra', [
                    'order_id' => $orderId,
                    'order_line_id' => $orderLineId,
                    'line_no' => $extraNo++,
                    'extra_id' => (int)$extra['extra_id'],
                    'qty' => (float)$extra['qty'],
                    'unit_price' => (float)$extra['unit_price'],
                    'net_amount' => (float)$extra['net_amount'],
                    'cost_amount_snapshot' => (float)$extra['cost_amount_snapshot'],
                    'notes' => $this->nullable_text($extra['notes'] ?? '', 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function create_and_link_deposit(array $reservation, array $deposit, int $actorEmployeeId, string $now, int $actorUserId = 0): array
    {
        $reservationId = (int)($reservation['id'] ?? 0);
        if ($reservationId <= 0) {
            throw new RuntimeException('Reservasi DP tidak valid.');
        }
        $amount = round((float)($deposit['amount'] ?? 0), 2);
        if ($amount <= 0.009) {
            throw new RuntimeException('Nominal DP wajib lebih besar dari nol.');
        }

        $result = $this->Pos_model->save_deposit([
            'member_id' => !empty($reservation['member_id']) ? (int)$reservation['member_id'] : null,
            'member_name' => (string)($reservation['customer_name'] ?? ''),
            'mobile_phone' => (string)($reservation['customer_phone'] ?? ''),
            'amount' => $amount,
            'payment_method_id' => (int)($deposit['payment_method_id'] ?? 0),
            'reference_no' => (string)($deposit['reference_no'] ?? ''),
            'notes' => $this->limit_text('DP reservasi ' . (string)($reservation['reservation_no'] ?? ''), 255),
        ], $actorEmployeeId, $actorUserId);
        if (!($result['ok'] ?? false)) {
            throw new RuntimeException((string)($result['message'] ?? 'Gagal menerima DP reservasi.'));
        }
        $paymentId = (int)($result['id'] ?? 0);
        $memberId = (int)($result['member_id'] ?? 0);
        if ($paymentId <= 0 || $memberId <= 0) {
            throw new RuntimeException('DP reservasi tidak menghasilkan dokumen pembayaran yang valid.');
        }

        $this->db->insert('pos_reservation_payment', [
            'reservation_id' => $reservationId,
            'payment_id' => $paymentId,
            'link_status' => 'OPEN',
            'linked_amount' => $amount,
            'applied_amount' => 0,
            'notes' => 'DP diterima untuk reservasi.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ((int)$this->db->insert_id() <= 0) {
            throw new RuntimeException('DP berhasil diterima tetapi gagal ditautkan ke reservasi. Transaksi dibatalkan agar tidak menjadi kredit tanpa tujuan.');
        }

        $reservationPayload = [
            'member_id' => $memberId,
            'updated_at' => $now,
        ];
        if ($this->db->field_exists('updated_by_user_id', 'pos_reservation')) {
            $reservationPayload['updated_by_user_id'] = $actorUserId > 0 ? $actorUserId : null;
        }
        $this->db->where('id', $reservationId)->update('pos_reservation', $reservationPayload);
        $this->recalculate_deposit_totals($reservationId, $now);
        $this->insert_state_log($reservationId, self::STATUS_PENDING, self::STATUS_PENDING, 'DEPOSIT_RECORDED', $actorEmployeeId, 'DP ' . (string)($result['payment_no'] ?? '') . ' diterima.', $actorUserId);

        return [
            'payment_id' => $paymentId,
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'amount' => $amount,
            'member_id' => $memberId,
        ];
    }

    private function locked_reservation_deposits(int $reservationId): array
    {
        return $this->db->query("\n            SELECT\n                rp.id AS link_id, rp.payment_id, rp.linked_amount, rp.applied_amount,\n                p.net_amount AS payment_amount, p.deposit_applied_amount AS payment_applied_amount,\n                p.payment_status, p.payment_type\n            FROM pos_reservation_payment rp\n            JOIN pos_payment p ON p.id = rp.payment_id\n            WHERE rp.reservation_id = ?\n              AND rp.link_status IN ('OPEN', 'PARTIAL')\n              AND p.payment_type = 'DEPOSIT'\n              AND p.payment_status = 'PAID'\n            ORDER BY p.paid_at ASC, p.id ASC\n            FOR UPDATE\n        ", [$reservationId])->result_array();
    }

    private function create_reservation_final_payment(array $reservation, int $orderId, array $session, int $actorEmployeeId, float $depositApplied, bool $isFullyPaid, string $now): int
    {
        $paymentNo = $this->generate_payment_no($now);
        $this->db->insert('pos_payment', [
            'payment_no' => $paymentNo,
            'order_id' => $orderId,
            'shift_id' => !empty($session['shift_id']) ? (int)$session['shift_id'] : null,
            'cashier_session_id' => !empty($session['id']) ? (int)$session['id'] : null,
            'cashier_employee_id' => $actorEmployeeId,
            'member_id' => !empty($reservation['member_id']) ? (int)$reservation['member_id'] : null,
            'payment_type' => 'FINAL',
            'payment_status' => $isFullyPaid ? 'PAID' : 'PENDING',
            'paid_at' => $isFullyPaid ? $now : null,
            'gross_amount' => (float)$reservation['subtotal_amount'],
            'discount_amount' => (float)$reservation['discount_amount'],
            'promo_amount' => 0,
            'voucher_amount' => 0,
            'point_redeem_amount' => 0,
            'compliment_amount' => 0,
            'deposit_applied_amount' => $depositApplied,
            'net_amount' => (float)$reservation['grand_total'],
            'change_amount' => 0,
            'notes' => $this->limit_text(
                $isFullyPaid
                    ? 'Pelunasan dari DP reservasi ' . (string)$reservation['reservation_no']
                    : 'DP reservasi ' . (string)$reservation['reservation_no'] . '; menunggu pelunasan kasir.',
                255
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $paymentId = (int)$this->db->insert_id();
        if ($paymentId <= 0) {
            throw new RuntimeException('Gagal membuat dokumen pembayaran DP untuk order reservasi.');
        }

        return $paymentId;
    }

    private function recalculate_deposit_totals(int $reservationId, string $now): void
    {
        $summary = $this->db->select("\n                COALESCE(SUM(CASE WHEN link_status <> 'VOID' THEN linked_amount ELSE 0 END), 0) AS deposit_total,\n                COALESCE(SUM(CASE WHEN link_status <> 'VOID' THEN applied_amount ELSE 0 END), 0) AS applied_total\n            ", false)
            ->from('pos_reservation_payment')
            ->where('reservation_id', $reservationId)
            ->get()
            ->row_array() ?: [];
        $reservation = $this->db->select('grand_total')->from('pos_reservation')->where('id', $reservationId)->limit(1)->get()->row_array() ?: [];
        $depositTotal = round((float)($summary['deposit_total'] ?? 0), 2);
        $appliedTotal = round((float)($summary['applied_total'] ?? 0), 2);
        $this->db->where('id', $reservationId)->update('pos_reservation', [
            'deposit_total' => $depositTotal,
            'deposit_applied_total' => $appliedTotal,
            'remaining_amount' => round(max(0, (float)($reservation['grand_total'] ?? 0) - $depositTotal), 2),
            'updated_at' => $now,
        ]);
    }

    private function insert_state_log(int $reservationId, ?string $fromStatus, string $toStatus, string $eventCode, int $actorEmployeeId, ?string $notes, int $actorUserId = 0): void
    {
        $payload = [
            'reservation_id' => $reservationId,
            'from_status' => $fromStatus ?: null,
            'to_status' => $toStatus,
            'event_code' => $eventCode,
            'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'notes' => $this->nullable_text($notes ?? '', 255),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db->field_exists('actor_user_id', 'pos_reservation_state_log')) {
            $payload['actor_user_id'] = $actorUserId > 0 ? $actorUserId : null;
        }
        $this->db->insert('pos_reservation_state_log', $payload);
    }

    private function reservation_totals(array $rows): array
    {
        $subtotal = 0.0;
        foreach ($rows as $row) {
            $subtotal += (float)($row['net_amount'] ?? 0);
            $subtotal += (float)($row['extra_total'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        return ['subtotal_amount' => $subtotal, 'grand_total' => $subtotal];
    }

    private function deposit_payload(array $payload): array
    {
        $nested = is_array($payload['deposit'] ?? null) ? (array)$payload['deposit'] : [];
        return [
            'amount' => round((float)($nested['amount'] ?? $payload['deposit_amount'] ?? 0), 2),
            'payment_method_id' => max(0, (int)($nested['payment_method_id'] ?? $payload['deposit_payment_method_id'] ?? 0)),
            'reference_no' => trim((string)($nested['reference_no'] ?? $payload['deposit_reference_no'] ?? '')),
        ];
    }

    private function member_row(int $memberId): ?array
    {
        if ($memberId <= 0) {
            return null;
        }
        $row = $this->db
            ->from('crm_member')
            ->where('id', $memberId)
            ->where('is_active', 1)
            ->where('member_status !=', 'CLOSED')
            ->limit(1)
            ->get()
            ->row_array();
        return $row ?: null;
    }

    private function sales_channel_row(int $salesChannelId): ?array
    {
        foreach ($this->Pos_model->sales_channel_options() as $row) {
            if ((int)($row['id'] ?? 0) === $salesChannelId) {
                return $row;
            }
        }
        return null;
    }

    private function record_exists(string $table, int $id): bool
    {
        return $id > 0 && $this->db->table_exists($table)
            && $this->db->where('id', $id)->count_all_results($table) > 0;
    }

    private function generate_reservation_no(string $reservationAt): string
    {
        $dateKey = date('Ymd', strtotime($reservationAt));
        $prefix = 'RSV-' . $dateKey;
        $row = $this->db->query('SELECT reservation_no FROM pos_reservation WHERE reservation_no LIKE ? ORDER BY reservation_no DESC LIMIT 1 FOR UPDATE', [$prefix . '-%'])->row_array();
        $next = !empty($row['reservation_no']) ? ((int)substr((string)$row['reservation_no'], -4)) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_order_no(string $now): string
    {
        $dateKey = date('Ymd', strtotime($now));
        $prefix = 'POS-' . $dateKey;
        $row = $this->db->query('SELECT order_no FROM pos_order WHERE order_no LIKE ? ORDER BY order_no DESC LIMIT 1 FOR UPDATE', [$prefix . '-%'])->row_array();
        $next = !empty($row['order_no']) ? ((int)substr((string)$row['order_no'], -4)) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_payment_no(string $now): string
    {
        $dateKey = date('Ymd', strtotime($now));
        $prefix = 'PAY-' . $dateKey;
        $row = $this->db->query('SELECT payment_no FROM pos_payment WHERE payment_no LIKE ? ORDER BY payment_no DESC LIMIT 1 FOR UPDATE', [$prefix . '-%'])->row_array();
        $next = !empty($row['payment_no']) ? ((int)substr((string)$row['payment_no'], -4)) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function reservation_order_notes(array $reservation): ?string
    {
        $notes = trim((string)($reservation['notes'] ?? ''));
        $prefix = 'Reservasi ' . (string)($reservation['reservation_no'] ?? '') . ' jadwal ' . date('d/m/Y H:i', strtotime((string)($reservation['reservation_at'] ?? 'now')));
        return $this->nullable_text($notes === '' ? $prefix : $prefix . ' | ' . $notes, 255);
    }

    private function normalize_datetime($value, bool $optional = false): ?string
    {
        $value = trim(str_replace('T', ' ', (string)$value));
        if ($value === '') {
            return $optional ? null : null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            $errors = DateTime::getLastErrors();
            if ($date && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private function normalize_date($value): ?string
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        return $date && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))
            ? $date->format('Y-m-d')
            : null;
    }

    private function nullable_text($value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        return $this->limit_text($value, $maxLength);
    }

    private function limit_text(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }
        return substr($value, 0, $maxLength);
    }

    private function status_label(string $status): string
    {
        $labels = [
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_VERIFIED_ACTIVE => 'Masuk Order Aktif',
            self::STATUS_VERIFIED_PAID => 'Selesai dan Lunas',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * A reservation stays as VERIFIED_ACTIVE in its original audit trail.
     * The screen, however, must follow its normal POS order when that order
     * is later paid, served, voided, or fully refunded.
     */
    private function effective_status(array $row): string
    {
        $status = strtoupper(trim((string)($row['status'] ?? '')));
        $orderStatus = strtoupper(trim((string)($row['linked_order_status'] ?? '')));
        if ($status === self::STATUS_VERIFIED_ACTIVE && in_array($orderStatus, ['PAID', 'SERVED'], true)) {
            return self::STATUS_VERIFIED_PAID;
        }
        if ($status === self::STATUS_VERIFIED_ACTIVE && in_array($orderStatus, ['VOID', 'REFUND_FULL'], true)) {
            return self::STATUS_CANCELLED;
        }
        return $status;
    }

    private function present_header(array $row): array
    {
        $effectiveStatus = $this->effective_status($row);
        $row['effective_status'] = $effectiveStatus;
        $row['status_label'] = $this->status_label($effectiveStatus);
        $row['is_overdue'] = in_array($effectiveStatus, [self::STATUS_PENDING, self::STATUS_VERIFIED_ACTIVE], true)
            && strtotime((string)($row['reservation_at'] ?? '')) < time();
        $row['is_pending'] = $effectiveStatus === self::STATUS_PENDING;
        $row['is_verified'] = in_array($effectiveStatus, [self::STATUS_VERIFIED_ACTIVE, self::STATUS_VERIFIED_PAID], true);
        return $row;
    }

    private function empty_meta(array $filters): array
    {
        $limit = max(10, min(100, (int)($filters['limit'] ?? 25)));
        return ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1];
    }
}
