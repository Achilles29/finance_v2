<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_model extends CI_Model
{
    private $tableFieldCache = [];

    private function table_has_field(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->tableFieldCache)) {
            $this->tableFieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->tableFieldCache[$key];
    }

    private function nullable_text($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalize_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        return $value;
    }

    private function next_doc_no(string $table, string $column, string $prefix, ?string $docDate = null): string
    {
        $datePart = date('Ym', $docDate !== null ? strtotime($docDate) : time());
        $head = strtoupper($prefix) . '-' . $datePart . '-';
        $last = $this->db->select($column)
            ->from($table)
            ->like($column, $head, 'after')
            ->order_by($column, 'DESC')
            ->limit(1)
            ->get()->row_array();

        $next = 1;
        if (!empty($last[$column])) {
            $parts = explode('-', (string)$last[$column]);
            $run = (int)end($parts);
            $next = $run + 1;
        }

        return $head . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
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
            ->get()->row_array();

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

    private function ensure_account_mutation_reversal_ready(): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Tabel mutasi rekening belum tersedia.'];
        }
        if (!$this->table_has_field('fin_account_mutation_log', 'reversal_of_mutation_id')) {
            return [
                'ok' => false,
                'message' => 'Fitur VOID aman belum siap. Jalankan migration 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql terlebih dahulu.',
            ];
        }

        return ['ok' => true];
    }

    private function create_account_mutation(
        string $moduleCode,
        int $accountId,
        string $mutationType,
        float $amount,
        string $mutationDate,
        string $refTable,
        int $refId,
        string $refNo,
        string $notes,
        int $actorUserId = 0,
        int $reversalOfMutationId = 0
    ): array {
        $mutationType = strtoupper(trim($mutationType));
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return ['ok' => true, 'mutation_id' => null, 'mutation_no' => null];
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening wajib dipilih.'];
        }
        if (!in_array($mutationType, ['IN', 'OUT'], true)) {
            return ['ok' => false, 'message' => 'Tipe mutasi tidak valid.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Tabel rekening atau mutation log belum tersedia.'];
        }
        if ($reversalOfMutationId > 0) {
            $ready = $this->ensure_account_mutation_reversal_ready();
            if (!($ready['ok'] ?? false)) {
                return $ready;
            }
        }

        $account = $this->db
            ->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])
            ->row_array();
        if (!$account) {
            return ['ok' => false, 'message' => 'Rekening tidak ditemukan atau nonaktif.'];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $balanceAfter = $mutationType === 'IN'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        if ($mutationType === 'OUT' && $balanceAfter < 0) {
            return ['ok' => false, 'message' => 'Saldo rekening tidak cukup untuk mutasi keluar.'];
        }

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);

        $mutationNo = $this->generate_account_mutation_no($mutationDate);
        $mutationPayload = [
            'mutation_no' => $mutationNo,
            'mutation_date' => $mutationDate,
            'account_id' => $accountId,
            'mutation_type' => $mutationType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => $moduleCode,
            'ref_table' => $refTable,
            'ref_id' => $refId > 0 ? $refId : null,
            'ref_no' => $refNo !== '' ? $refNo : null,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($reversalOfMutationId > 0) {
            $mutationPayload['reversal_of_mutation_id'] = $reversalOfMutationId;
        }
        $inserted = $this->db->insert('fin_account_mutation_log', $mutationPayload);
        $mutationId = (int)$this->db->insert_id();
        if (!$inserted || $mutationId <= 0) {
            // This helper is normally called inside a transaction. Restore the
            // locked balance as an extra safeguard for a direct caller.
            $this->db->where('id', $accountId)->update('fin_company_account', [
                'current_balance' => $balanceBefore,
            ]);
            return ['ok' => false, 'message' => 'Gagal mencatat mutasi rekening.'];
        }

        return [
            'ok' => true,
            'mutation_id' => $mutationId,
            'mutation_no' => $mutationNo,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    private function reverse_account_mutation(
        int $mutationId,
        string $moduleCode,
        string $refTable,
        int $refId,
        string $refNo,
        string $notes,
        int $actorUserId = 0
    ): array {
        if ($mutationId <= 0) {
            return ['ok' => true];
        }
        $ready = $this->ensure_account_mutation_reversal_ready();
        if (!($ready['ok'] ?? false)) {
            return $ready;
        }
        $mutation = $this->db
            ->query('SELECT * FROM fin_account_mutation_log WHERE id = ? LIMIT 1 FOR UPDATE', [$mutationId])
            ->row_array();
        if (!$mutation) {
            return ['ok' => false, 'message' => 'Mutasi asli tidak ditemukan untuk dibalik.'];
        }
        if ((int)$this->db
            ->where('reversal_of_mutation_id', $mutationId)
            ->count_all_results('fin_account_mutation_log') > 0) {
            return ['ok' => true, 'message' => 'Mutasi ini sudah pernah dibalik.'];
        }

        $reverseType = strtoupper((string)($mutation['mutation_type'] ?? 'IN')) === 'IN' ? 'OUT' : 'IN';
        $amount = round((float)($mutation['amount'] ?? 0), 2);
        $mutationDate = $this->normalize_date((string)($mutation['mutation_date'] ?? '')) ?: date('Y-m-d');

        return $this->create_account_mutation(
            $moduleCode,
            (int)($mutation['account_id'] ?? 0),
            $reverseType,
            $amount,
            $mutationDate,
            $refTable,
            $refId,
            $refNo,
            $notes,
            $actorUserId,
            $mutationId
        );
    }

    private function sync_loan_status(string $kind, int $loanId): void
    {
        $cfg = $this->loan_config($kind);
        if ($loanId <= 0) {
            return;
        }

        $header = $this->db->select('id, amount, outstanding_amount, status')
            ->from($cfg['header_table'])
            ->where('id', $loanId)
            ->limit(1)
            ->get()->row_array();
        if (!$header) {
            return;
        }

        $paidTotal = (float)$this->db->select('COALESCE(SUM(amount),0) AS total', false)
            ->from($cfg['payment_table'])
            ->where($cfg['loan_fk'], $loanId)
            ->get()->row()->total;

        $amount = round((float)($header['amount'] ?? 0), 2);
        $outstanding = round(max(0, $amount - $paidTotal), 2);
        $status = 'OPEN';
        if ($outstanding <= 0.0001) {
            $status = 'SETTLED';
            $outstanding = 0.00;
        } elseif ($paidTotal > 0.0001) {
            $status = 'PARTIAL';
        }

        if (strtoupper((string)($header['status'] ?? '')) === 'VOID') {
            $status = 'VOID';
            $outstanding = 0.00;
        }

        $this->db->where('id', $loanId)->update($cfg['header_table'], [
            'outstanding_amount' => $outstanding,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function loan_config(string $kind): array
    {
        if ($kind === 'receivable') {
            return [
                'kind' => 'receivable',
                'header_table' => 'fin_receivable',
                'payment_table' => 'fin_receivable_payment',
                'loan_no_field' => 'receivable_no',
                'loan_date_field' => 'receivable_date',
                'title_field' => 'receivable_title',
                'loan_fk' => 'receivable_id',
                'doc_prefix' => 'PIT',
                'payment_prefix' => 'BAYAR-PIT',
                'page_code' => 'finance.receivable.index',
                'module_code' => 'FINANCE_RECEIVABLE',
                'menu_code' => 'finance.receivable',
                'base_url' => 'finance/piutang',
                'title' => 'Piutang',
                'title_plural' => 'Piutang',
                'counterparty_label' => 'Pihak yang berutang ke kita',
                'header_intro' => 'Piutang untuk pihak luar. Kalau mode saldo bergerak dipakai, sistem akan mencatat uang keluar saat piutang dibuat dan uang masuk saat ditagih.',
                'create_verb' => 'Tambah Piutang',
                'payment_verb' => 'Terima Pembayaran',
                'initial_mutation_type' => 'OUT',
                'payment_mutation_type' => 'IN',
                'initial_mutation_note' => 'Pencatatan piutang',
                'payment_mutation_note' => 'Penerimaan piutang',
                'void_reverse_note' => 'Pembalikan pencatatan piutang',
            ];
        }

        return [
            'kind' => 'payable',
            'header_table' => 'fin_payable',
            'payment_table' => 'fin_payable_payment',
            'loan_no_field' => 'payable_no',
            'loan_date_field' => 'payable_date',
            'title_field' => 'payable_title',
            'loan_fk' => 'payable_id',
            'doc_prefix' => 'UTG',
            'payment_prefix' => 'BAYAR-UTG',
            'page_code' => 'finance.payable.index',
            'module_code' => 'FINANCE_PAYABLE',
            'menu_code' => 'finance.payable',
            'base_url' => 'finance/utang',
            'title' => 'Utang',
            'title_plural' => 'Utang',
            'counterparty_label' => 'Pihak yang memberi utang',
            'header_intro' => 'Utang kepada pihak luar. Kalau mode saldo bergerak dipakai, sistem akan mencatat uang masuk saat utang dibuat dan uang keluar saat dilunasi.',
            'create_verb' => 'Tambah Utang',
            'payment_verb' => 'Bayar Utang',
            'initial_mutation_type' => 'IN',
            'payment_mutation_type' => 'OUT',
            'initial_mutation_note' => 'Pencatatan utang',
            'payment_mutation_note' => 'Pembayaran utang',
            'void_reverse_note' => 'Pembalikan pencatatan utang',
        ];
    }

    public function get_company_account_options(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }
        return $this->db->select("id, id AS value, CONCAT(account_code, ' - ', account_name) AS label, account_code, account_name, account_type, current_balance", false)
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->get()->result_array();
    }

    public function get_party_by_id(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('fin_relation_party')) {
            return null;
        }
        $db = $this->db->select('p.*')
            ->from('fin_relation_party p');
        if ($this->db->table_exists('crm_member')) {
            $db->select('m.member_name, m.mobile_phone AS member_mobile_phone')
                ->join('crm_member m', 'm.id = p.linked_member_id', 'left');
        }
        return $db->where('p.id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;
    }

    public function party_search(string $q, int $limit = 10): array
    {
        if (!$this->db->table_exists('fin_relation_party')) {
            return [];
        }
        $limit = max(1, min(25, $limit));
        $db = $this->db->select('p.id, p.party_name, p.party_code, p.party_type, p.mobile_phone, p.linked_member_id')
            ->from('fin_relation_party p')
            ->where('p.is_active', 1);
        if ($this->db->table_exists('crm_member')) {
            $db->select('m.member_name')
                ->join('crm_member m', 'm.id = p.linked_member_id', 'left');
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.party_name', $q)
                ->or_like('p.party_code', $q)
                ->or_like('p.mobile_phone', $q);
            if ($this->db->table_exists('crm_member')) {
                $db->or_like('m.member_name', $q);
            }
            $db->group_end();
        }
        return $db->order_by('p.party_name', 'ASC')
            ->limit($limit)
            ->get()->result_array();
    }

    public function member_search(string $q, int $limit = 10): array
    {
        if (!$this->db->table_exists('crm_member')) {
            return [];
        }
        $limit = max(1, min(25, $limit));
        $db = $this->db->select('id, member_name, mobile_phone')
            ->from('crm_member')
            ->where('is_active', 1);
        if ($q !== '') {
            $db->group_start()
                ->like('member_name', $q)
                ->or_like('mobile_phone', $q)
                ->group_end();
        }
        return $db->order_by('member_name', 'ASC')
            ->limit($limit)
            ->get()->result_array();
    }

    public function count_parties(array $filters): int
    {
        $this->build_party_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_parties(array $filters, int $limit, int $offset): array
    {
        $this->build_party_query($filters, true);
        return $this->db->order_by('p.party_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_party_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                p.*,
                COALESCE((SELECT COUNT(*) FROM fin_payable fp WHERE fp.party_id = p.id), 0) AS payable_count,
                COALESCE((SELECT COUNT(*) FROM fin_receivable fr WHERE fr.party_id = p.id), 0) AS receivable_count
            ", false);
        }
        $this->db->from('fin_relation_party p');
        if ($this->db->table_exists('crm_member')) {
            if ($withSelect) {
                $this->db->select('m.member_name, m.mobile_phone AS member_mobile_phone');
            }
            $this->db->join('crm_member m', 'm.id = p.linked_member_id', 'left');
        }

        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        if ($status === 'ACTIVE') {
            $this->db->where('p.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('p.is_active', 0);
        }

        $type = strtoupper(trim((string)($filters['party_type'] ?? '')));
        if ($type !== '' && in_array($type, ['PERSON', 'BUSINESS', 'MEMBER', 'OTHER'], true)) {
            $this->db->where('p.party_type', $type);
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('p.party_name', $q)
                ->or_like('p.party_code', $q)
                ->or_like('p.mobile_phone', $q);
            if ($this->db->table_exists('crm_member')) {
                $this->db->or_like('m.member_name', $q);
            }
            $this->db->group_end();
        }
    }

    public function save_party(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('fin_relation_party')) {
            return ['ok' => false, 'message' => 'Tabel relasi utang/piutang belum tersedia.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $partyType = strtoupper(trim((string)($payload['party_type'] ?? 'BUSINESS')));
        if (!in_array($partyType, ['PERSON', 'BUSINESS', 'MEMBER', 'OTHER'], true)) {
            $partyType = 'BUSINESS';
        }

        $linkedMemberId = (int)($payload['linked_member_id'] ?? 0);
        $member = null;
        if ($linkedMemberId > 0 && $this->db->table_exists('crm_member')) {
            $member = $this->db->select('id, member_name, mobile_phone')
                ->from('crm_member')
                ->where('id', $linkedMemberId)
                ->limit(1)
                ->get()->row_array();
            if (!$member) {
                return ['ok' => false, 'message' => 'Member yang dipilih tidak ditemukan.'];
            }
        }

        $partyName = trim((string)($payload['party_name'] ?? ''));
        if ($partyName === '' && $member) {
            $partyName = trim((string)($member['member_name'] ?? ''));
        }
        if ($partyName === '') {
            return ['ok' => false, 'message' => 'Nama pihak wajib diisi.'];
        }

        $mobilePhone = trim((string)($payload['mobile_phone'] ?? ''));
        if ($mobilePhone === '' && $member) {
            $mobilePhone = trim((string)($member['mobile_phone'] ?? ''));
        }

        $dbPayload = [
            'party_name' => $partyName,
            'party_type' => $partyType,
            'linked_member_id' => $linkedMemberId > 0 ? $linkedMemberId : null,
            'contact_person' => $this->nullable_text($payload['contact_person'] ?? ''),
            'mobile_phone' => $this->nullable_text($mobilePhone),
            'email' => $this->nullable_text($payload['email'] ?? ''),
            'address' => $this->nullable_text($payload['address'] ?? ''),
            'notes' => $this->nullable_text($payload['notes'] ?? ''),
            'is_active' => isset($payload['is_active']) ? (int)!empty($payload['is_active']) : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $exists = $this->get_party_by_id($id);
            if (!$exists) {
                return ['ok' => false, 'message' => 'Data pihak tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('fin_relation_party', $dbPayload);
            return ['ok' => true, 'id' => $id, 'message' => 'Data pihak berhasil diperbarui.', 'row' => $this->get_party_by_id($id)];
        }

        $dbPayload['party_code'] = $this->next_doc_no('fin_relation_party', 'party_code', 'PTY');
        $dbPayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('fin_relation_party', $dbPayload);
        $newId = (int)$this->db->insert_id();
        return ['ok' => true, 'id' => $newId, 'message' => 'Data pihak berhasil ditambahkan.', 'row' => $this->get_party_by_id($newId)];
    }

    public function toggle_party(int $id): array
    {
        $row = $this->get_party_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data pihak tidak ditemukan.'];
        }
        $newValue = (int)(empty($row['is_active']) ? 1 : 0);
        $this->db->where('id', $id)->update('fin_relation_party', [
            'is_active' => $newValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function delete_party(int $id): array
    {
        $row = $this->get_party_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data pihak tidak ditemukan.'];
        }
        $payableCount = $this->db->where('party_id', $id)->count_all_results('fin_payable');
        $receivableCount = $this->db->where('party_id', $id)->count_all_results('fin_receivable');
        if ($payableCount > 0 || $receivableCount > 0) {
            return ['ok' => false, 'message' => 'Pihak ini sudah dipakai di transaksi utang/piutang, jadi tidak bisa dihapus.'];
        }
        $this->db->where('id', $id)->delete('fin_relation_party');
        return ['ok' => true, 'id' => $id];
    }

    public function count_loan_docs(string $kind, array $filters): int
    {
        $this->build_loan_query($kind, $filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_loan_docs(string $kind, array $filters, int $limit, int $offset): array
    {
        $cfg = $this->loan_config($kind);
        $this->build_loan_query($kind, $filters, true);
        $rows = $this->db
            ->order_by('h.' . $cfg['loan_date_field'], 'DESC')
            ->order_by('h.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['paid_amount'] = round((float)($row['amount'] ?? 0) - (float)($row['outstanding_amount'] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    private function build_loan_query(string $kind, array $filters, bool $withSelect): void
    {
        $cfg = $this->loan_config($kind);
        if ($withSelect) {
            $this->db->select("
                h.*,
                p.party_name,
                p.party_code,
                p.mobile_phone AS party_mobile_phone,
                a.account_code,
                a.account_name,
                COALESCE((SELECT COUNT(*) FROM {$cfg['payment_table']} py WHERE py.{$cfg['loan_fk']} = h.id), 0) AS payment_count
            ", false);
        }

        $this->db->from($cfg['header_table'] . ' h')
            ->join('fin_relation_party p', 'p.id = h.party_id', 'inner')
            ->join('fin_company_account a', 'a.id = h.company_account_id', 'left');

        $this->apply_loan_filters($cfg, $filters);
    }

    private function apply_loan_filters(array $cfg, array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, ['OPEN', 'PARTIAL', 'SETTLED', 'VOID'], true)) {
            $this->db->where('h.status', $status);
        }

        $impactMode = strtoupper(trim((string)($filters['impact_mode'] ?? '')));
        if ($impactMode !== '' && in_array($impactMode, ['APPLY_ACCOUNT', 'KEEP_BALANCE'], true)) {
            $this->db->where('h.account_impact_mode', $impactMode);
        }

        $partyId = (int)($filters['party_id'] ?? 0);
        if ($partyId > 0) {
            $this->db->where('h.party_id', $partyId);
        }

        $accountId = (int)($filters['account_id'] ?? 0);
        if ($accountId > 0) {
            $this->db->where('h.company_account_id', $accountId);
        }

        $dateStart = $this->normalize_date((string)($filters['date_start'] ?? ''));
        if ($dateStart !== null) {
            $this->db->where('h.' . $cfg['loan_date_field'] . ' >=', $dateStart);
        }
        $dateEnd = $this->normalize_date((string)($filters['date_end'] ?? ''));
        if ($dateEnd !== null) {
            $this->db->where('h.' . $cfg['loan_date_field'] . ' <=', $dateEnd);
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.' . $cfg['loan_no_field'], $q)
                ->or_like('h.' . $cfg['title_field'], $q)
                ->or_like('p.party_name', $q)
                ->or_like('p.party_code', $q)
                ->or_like('a.account_name', $q)
                ->or_like('a.account_code', $q)
                ->group_end();
        }
    }

    public function count_loan_tab_rows(string $kind, array $filters, string $tab): int
    {
        if ($tab === 'party') {
            return count($this->list_loan_tab_rows($kind, $filters, $tab, 1000000, 0));
        }
        if ($tab === 'account') {
            return count($this->list_loan_tab_rows($kind, $filters, $tab, 1000000, 0));
        }
        if ($tab === 'party_account') {
            return count($this->list_loan_tab_rows($kind, $filters, $tab, 1000000, 0));
        }
        return 0;
    }

    public function list_loan_tab_rows(string $kind, array $filters, string $tab, int $limit, int $offset): array
    {
        $cfg = $this->loan_config($kind);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($tab === 'party') {
            $this->db->select("
                    h.party_id,
                    p.party_name,
                    p.party_code,
                    p.mobile_phone AS party_mobile_phone,
                    COUNT(*) AS doc_total,
                    COUNT(DISTINCT h.company_account_id) AS account_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN (h.amount - h.outstanding_amount) ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(CASE WHEN h.account_impact_mode = 'KEEP_BALANCE' AND h.status <> 'VOID' THEN 1 ELSE 0 END), 0) AS historical_doc_total,
                    MAX(h.{$cfg['loan_date_field']}) AS last_doc_date,
                    MIN(CASE WHEN h.status IN ('OPEN','PARTIAL') AND h.due_date IS NOT NULL THEN h.due_date ELSE NULL END) AS nearest_due_date
                ", false);
            $this->db->from($cfg['header_table'] . ' h')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'inner')
                ->join('fin_company_account a', 'a.id = h.company_account_id', 'left');
            $this->apply_loan_filters($cfg, $filters);
            return $this->db
                ->group_by(['h.party_id', 'p.party_name', 'p.party_code', 'p.mobile_phone'])
                ->order_by('outstanding_total', 'DESC', false)
                ->order_by('amount_total', 'DESC', false)
                ->limit($limit, $offset)
                ->get()->result_array();
        }

        if ($tab === 'account') {
            $this->db->select("
                    h.company_account_id,
                    a.account_code,
                    a.account_name,
                    a.account_type,
                    a.bank_name,
                    COUNT(*) AS doc_total,
                    COUNT(DISTINCT h.party_id) AS party_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN (h.amount - h.outstanding_amount) ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(CASE WHEN h.account_impact_mode = 'KEEP_BALANCE' AND h.status <> 'VOID' THEN 1 ELSE 0 END), 0) AS historical_doc_total,
                    MAX(h.{$cfg['loan_date_field']}) AS last_doc_date
                ", false);
            $this->db->from($cfg['header_table'] . ' h')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'inner')
                ->join('fin_company_account a', 'a.id = h.company_account_id', 'left');
            $this->apply_loan_filters($cfg, $filters);
            return $this->db
                ->group_by(['h.company_account_id', 'a.account_code', 'a.account_name', 'a.account_type', 'a.bank_name'])
                ->order_by('outstanding_total', 'DESC', false)
                ->order_by('amount_total', 'DESC', false)
                ->limit($limit, $offset)
                ->get()->result_array();
        }

        if ($tab === 'party_account') {
            $this->db->select("
                    h.party_id,
                    h.company_account_id,
                    p.party_name,
                    p.party_code,
                    p.mobile_phone AS party_mobile_phone,
                    a.account_code,
                    a.account_name,
                    a.account_type,
                    a.bank_name,
                    COUNT(*) AS doc_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total,
                    COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN (h.amount - h.outstanding_amount) ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(CASE WHEN h.account_impact_mode = 'KEEP_BALANCE' AND h.status <> 'VOID' THEN 1 ELSE 0 END), 0) AS historical_doc_total,
                    MAX(h.{$cfg['loan_date_field']}) AS last_doc_date
                ", false);
            $this->db->from($cfg['header_table'] . ' h')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'inner')
                ->join('fin_company_account a', 'a.id = h.company_account_id', 'left');
            $this->apply_loan_filters($cfg, $filters);
            return $this->db
                ->group_by([
                    'h.party_id',
                    'h.company_account_id',
                    'p.party_name',
                    'p.party_code',
                    'p.mobile_phone',
                    'a.account_code',
                    'a.account_name',
                    'a.account_type',
                    'a.bank_name',
                ])
                ->order_by('outstanding_total', 'DESC', false)
                ->order_by('amount_total', 'DESC', false)
                ->limit($limit, $offset)
                ->get()->result_array();
        }

        return [];
    }

    public function summarize_loan_recap(string $kind, array $filters): array
    {
        $cfg = $this->loan_config($kind);

        $this->build_loan_query($kind, $filters, false);
        $statusRows = $this->db->select("
                h.status,
                COUNT(*) AS doc_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total
            ", false)
            ->group_by('h.status')
            ->order_by("FIELD(h.status, 'OPEN', 'PARTIAL', 'SETTLED', 'VOID')", '', false)
            ->get()->result_array();

        $this->build_loan_query($kind, $filters, false);
        $modeRows = $this->db->select("
                h.account_impact_mode,
                COUNT(*) AS doc_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total
            ", false)
            ->group_by('h.account_impact_mode')
            ->order_by("FIELD(h.account_impact_mode, 'APPLY_ACCOUNT', 'KEEP_BALANCE')", '', false)
            ->get()->result_array();

        return [
            'status_rows' => $statusRows,
            'mode_rows' => $modeRows,
            'top_party_rows' => $this->list_loan_tab_rows($kind, $filters, 'party', 5, 0),
            'top_account_rows' => $this->list_loan_tab_rows($kind, $filters, 'account', 5, 0),
        ];
    }

    public function summarize_loan_docs(string $kind, array $filters): array
    {
        $cfg = $this->loan_config($kind);
        $this->build_loan_query($kind, $filters, false);
        $row = $this->db->select("
                COUNT(*) AS doc_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.amount ELSE 0 END), 0) AS amount_total,
                COALESCE(SUM(CASE WHEN h.status <> 'VOID' THEN h.outstanding_amount ELSE 0 END), 0) AS outstanding_total,
                COALESCE(SUM(CASE WHEN h.account_impact_mode = 'KEEP_BALANCE' AND h.status <> 'VOID' THEN 1 ELSE 0 END), 0) AS historical_doc_total
            ", false)
            ->get()->row_array();

        return [
            'doc_total' => (int)($row['doc_total'] ?? 0),
            'amount_total' => (float)($row['amount_total'] ?? 0),
            'outstanding_total' => (float)($row['outstanding_total'] ?? 0),
            'historical_doc_total' => (int)($row['historical_doc_total'] ?? 0),
            'paid_total' => round((float)($row['amount_total'] ?? 0) - (float)($row['outstanding_total'] ?? 0), 2),
        ];
    }

    public function get_loan_by_id(string $kind, int $id): ?array
    {
        $cfg = $this->loan_config($kind);
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->select("
                h.*,
                p.party_name,
                p.party_code,
                p.mobile_phone AS party_mobile_phone,
                p.linked_member_id,
                a.account_code,
                a.account_name
            ", false)
            ->from($cfg['header_table'] . ' h')
            ->join('fin_relation_party p', 'p.id = h.party_id', 'inner')
            ->join('fin_company_account a', 'a.id = h.company_account_id', 'left')
            ->where('h.id', $id)
            ->limit(1)
            ->get()->row_array();
        if ($row && $this->db->table_exists('crm_member') && !empty($row['linked_member_id'])) {
            $member = $this->db->select('member_name')
                ->from('crm_member')
                ->where('id', (int)$row['linked_member_id'])
                ->limit(1)
                ->get()->row_array();
            $row['member_name'] = (string)($member['member_name'] ?? '');
        }
        if (!$row) {
            return null;
        }
        $row['paid_amount'] = round((float)($row['amount'] ?? 0) - (float)($row['outstanding_amount'] ?? 0), 2);
        return $row;
    }

    public function list_loan_payments(string $kind, int $loanId): array
    {
        $cfg = $this->loan_config($kind);
        if ($loanId <= 0) {
            return [];
        }
        return $this->db->select("
                py.*,
                a.account_code,
                a.account_name,
                ml.mutation_no,
                ml.mutation_type
            ", false)
            ->from($cfg['payment_table'] . ' py')
            ->join('fin_company_account a', 'a.id = py.company_account_id', 'left')
            ->join('fin_account_mutation_log ml', 'ml.id = py.mutation_id', 'left')
            ->where('py.' . $cfg['loan_fk'], $loanId)
            ->order_by('py.payment_date', 'DESC')
            ->order_by('py.id', 'DESC')
            ->get()->result_array();
    }

    public function save_loan(string $kind, array $payload, int $actorUserId = 0): array
    {
        $cfg = $this->loan_config($kind);
        if (!$this->db->table_exists($cfg['header_table'])) {
            return ['ok' => false, 'message' => 'Tabel ' . $cfg['title_plural'] . ' belum tersedia.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $partyId = (int)($payload['party_id'] ?? 0);
        $loanDate = $this->normalize_date((string)($payload[$cfg['loan_date_field']] ?? ''));
        $dueDate = $this->normalize_date((string)($payload['due_date'] ?? ''));
        $title = trim((string)($payload[$cfg['title_field']] ?? ''));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $impactMode = strtoupper(trim((string)($payload['account_impact_mode'] ?? 'APPLY_ACCOUNT')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($partyId <= 0) {
            return ['ok' => false, 'message' => 'Pihak wajib dipilih lebih dulu.'];
        }
        if (!$this->get_party_by_id($partyId)) {
            return ['ok' => false, 'message' => 'Pihak yang dipilih tidak ditemukan.'];
        }
        if ($loanDate === null) {
            return ['ok' => false, 'message' => 'Tanggal transaksi wajib valid.'];
        }
        if ($title === '') {
            return ['ok' => false, 'message' => 'Judul transaksi wajib diisi.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal harus lebih besar dari nol.'];
        }
        if (!in_array($impactMode, ['APPLY_ACCOUNT', 'KEEP_BALANCE'], true)) {
            $impactMode = 'APPLY_ACCOUNT';
        }
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening wajib dipilih, termasuk untuk transaksi historis saldo tetap.'];
        }

        $dbPayload = [
            'party_id' => $partyId,
            $cfg['loan_date_field'] => $loanDate,
            'due_date' => $dueDate,
            $cfg['title_field'] => $title,
            'amount' => $amount,
            'account_impact_mode' => $impactMode,
            'company_account_id' => $companyAccountId,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->trans_begin();

        if ($id > 0) {
            $existing = $this->get_loan_by_id($kind, $id);
            if (!$existing) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Data ' . strtolower($cfg['title']) . ' tidak ditemukan.'];
            }
            if (strtoupper((string)($existing['status'] ?? 'OPEN')) === 'VOID') {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Data yang sudah VOID tidak bisa diubah lagi.'];
            }

            $paymentCount = (int)$this->db->where($cfg['loan_fk'], $id)->count_all_results($cfg['payment_table']);
            $paidAmount = round((float)($existing['amount'] ?? 0) - (float)($existing['outstanding_amount'] ?? 0), 2);
            if ($amount + 0.0001 < $paidAmount) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Nominal baru tidak boleh lebih kecil dari total pembayaran yang sudah masuk.'];
            }

            if ($paymentCount > 0) {
                $changedFinancialFields =
                    $loanDate !== (string)($existing[$cfg['loan_date_field']] ?? '') ||
                    $impactMode !== strtoupper((string)($existing['account_impact_mode'] ?? '')) ||
                    (int)$companyAccountId !== (int)($existing['company_account_id'] ?? 0) ||
                    abs($amount - (float)($existing['amount'] ?? 0)) > 0.0001;
                if ($changedFinancialFields) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => 'Transaksi yang sudah punya pembayaran tidak boleh mengubah tanggal, nominal, mode saldo, atau rekening awal.'];
                }
            } elseif ((int)($existing['initial_mutation_id'] ?? 0) > 0) {
                $reverse = $this->reverse_account_mutation(
                    (int)$existing['initial_mutation_id'],
                    $cfg['module_code'],
                    $cfg['header_table'],
                    $id,
                    (string)($existing[$cfg['loan_no_field']] ?? ''),
                    $cfg['void_reverse_note'] . ' saat edit',
                    $actorUserId
                );
                if (!($reverse['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return $reverse;
                }
                $dbPayload['initial_mutation_id'] = null;
            }

            $outstanding = round(max(0, $amount - $paidAmount), 2);
            $dbPayload['outstanding_amount'] = $outstanding;
            $dbPayload['status'] = $outstanding <= 0.0001 ? 'SETTLED' : ($paidAmount > 0.0001 ? 'PARTIAL' : 'OPEN');

            $this->db->where('id', $id)->update($cfg['header_table'], $dbPayload);

            if ($paymentCount === 0 && $impactMode === 'APPLY_ACCOUNT') {
                $posted = $this->create_account_mutation(
                    $cfg['module_code'],
                    $companyAccountId,
                    $cfg['initial_mutation_type'],
                    $amount,
                    $loanDate,
                    $cfg['header_table'],
                    $id,
                    (string)($existing[$cfg['loan_no_field']] ?? ''),
                    $cfg['initial_mutation_note'] . ' ' . strtolower($cfg['title']) . ' ' . $title,
                    $actorUserId
                );
                if (!($posted['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return $posted;
                }
                $this->db->where('id', $id)->update($cfg['header_table'], [
                    'initial_mutation_id' => (int)($posted['mutation_id'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal memperbarui ' . strtolower($cfg['title']) . '.'];
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'message' => $cfg['title'] . ' berhasil diperbarui.'];
        }

        $dbPayload[$cfg['loan_no_field']] = $this->next_doc_no($cfg['header_table'], $cfg['loan_no_field'], $cfg['doc_prefix'], $loanDate);
        $dbPayload['outstanding_amount'] = $amount;
        $dbPayload['status'] = 'OPEN';
        $dbPayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($cfg['header_table'], $dbPayload);
        $newId = (int)$this->db->insert_id();

        if ($impactMode === 'APPLY_ACCOUNT') {
            $posted = $this->create_account_mutation(
                $cfg['module_code'],
                $companyAccountId,
                $cfg['initial_mutation_type'],
                $amount,
                $loanDate,
                $cfg['header_table'],
                $newId,
                (string)$dbPayload[$cfg['loan_no_field']],
                $cfg['initial_mutation_note'] . ' ' . strtolower($cfg['title']) . ' ' . $title,
                $actorUserId
            );
            if (!($posted['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $posted;
            }
            $this->db->where('id', $newId)->update($cfg['header_table'], [
                'initial_mutation_id' => (int)($posted['mutation_id'] ?? 0),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan ' . strtolower($cfg['title']) . '.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $newId, 'message' => $cfg['title'] . ' berhasil ditambahkan.'];
    }

    public function save_loan_payment(string $kind, int $loanId, array $payload, int $actorUserId = 0): array
    {
        $cfg = $this->loan_config($kind);
        if (!$this->db->table_exists($cfg['payment_table'])) {
            return ['ok' => false, 'message' => 'Tabel pembayaran ' . strtolower($cfg['title']) . ' belum tersedia.'];
        }

        $header = $this->get_loan_by_id($kind, $loanId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Data ' . strtolower($cfg['title']) . ' tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? 'OPEN')) === 'VOID') {
            return ['ok' => false, 'message' => 'Transaksi yang sudah VOID tidak bisa menerima pembayaran lagi.'];
        }

        $paymentDate = $this->normalize_date((string)($payload['payment_date'] ?? ''));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $impactMode = strtoupper(trim((string)($payload['account_impact_mode'] ?? 'APPLY_ACCOUNT')));
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $transferRefNo = trim((string)($payload['transfer_ref_no'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($paymentDate === null) {
            return ['ok' => false, 'message' => 'Tanggal pembayaran wajib valid.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal pembayaran harus lebih besar dari nol.'];
        }
        if ($amount - (float)($header['outstanding_amount'] ?? 0) > 0.0001) {
            return ['ok' => false, 'message' => 'Nominal pembayaran melebihi sisa outstanding saat ini.'];
        }
        if (!in_array($impactMode, ['APPLY_ACCOUNT', 'KEEP_BALANCE'], true)) {
            $impactMode = 'APPLY_ACCOUNT';
        }
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening wajib dipilih, termasuk untuk pembayaran historis saldo tetap.'];
        }

        $this->db->trans_begin();

        $paymentNo = $this->next_doc_no($cfg['payment_table'], 'payment_no', $cfg['payment_prefix'], $paymentDate);
        $insert = [
            $cfg['loan_fk'] => $loanId,
            'payment_no' => $paymentNo,
            'payment_date' => $paymentDate,
            'company_account_id' => $companyAccountId,
            'amount' => $amount,
            'account_impact_mode' => $impactMode,
            'transfer_ref_no' => $transferRefNo !== '' ? $transferRefNo : null,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($impactMode === 'APPLY_ACCOUNT') {
            $posted = $this->create_account_mutation(
                $cfg['module_code'],
                $companyAccountId,
                $cfg['payment_mutation_type'],
                $amount,
                $paymentDate,
                $cfg['payment_table'],
                0,
                $paymentNo,
                $cfg['payment_mutation_note'] . ' ' . (string)($header[$cfg['loan_no_field']] ?? ''),
                $actorUserId
            );
            if (!($posted['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $posted;
            }
            $insert['mutation_id'] = (int)($posted['mutation_id'] ?? 0);
        }

        $this->db->insert($cfg['payment_table'], $insert);
        $paymentId = (int)$this->db->insert_id();
        if (!empty($insert['mutation_id'])) {
            $this->db->where('id', (int)$insert['mutation_id'])->update('fin_account_mutation_log', [
                'ref_id' => $paymentId,
            ]);
        }

        $this->sync_loan_status($kind, $loanId);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan pembayaran.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $paymentId, 'message' => 'Pembayaran berhasil disimpan.'];
    }

    public function void_loan(string $kind, int $id, string $notes, int $actorUserId = 0): array
    {
        $cfg = $this->loan_config($kind);
        $row = $this->get_loan_by_id($kind, $id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data ' . strtolower($cfg['title']) . ' tidak ditemukan.'];
        }
        if (strtoupper((string)($row['status'] ?? 'OPEN')) === 'VOID') {
            return ['ok' => false, 'message' => 'Data ini sudah VOID.'];
        }

        $paymentCount = (int)$this->db->where($cfg['loan_fk'], $id)->count_all_results($cfg['payment_table']);
        if ($paymentCount > 0) {
            return ['ok' => false, 'message' => $cfg['title'] . ' yang sudah punya pembayaran tidak bisa di-VOID otomatis.'];
        }

        $this->db->trans_begin();

        $initialMutationId = (int)($row['initial_mutation_id'] ?? 0);
        if ($initialMutationId > 0) {
            $reverse = $this->reverse_account_mutation(
                $initialMutationId,
                $cfg['module_code'],
                $cfg['header_table'],
                $id,
                (string)($row[$cfg['loan_no_field']] ?? ''),
                $cfg['void_reverse_note'] . ' ' . strtolower($cfg['title']) . ' ' . (string)($row[$cfg['loan_no_field']] ?? ''),
                $actorUserId
            );
            if (!($reverse['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $reverse;
            }
        }

        $mergedNotes = trim((string)($row['notes'] ?? ''));
        if ($notes !== '') {
            $mergedNotes = $mergedNotes !== '' ? ($mergedNotes . ' | ' . $notes) : $notes;
        }

        $this->db->where('id', $id)->update($cfg['header_table'], [
            'status' => 'VOID',
            'outstanding_amount' => 0.00,
            'notes' => $mergedNotes !== '' ? $mergedNotes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal VOID ' . strtolower($cfg['title']) . '.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'message' => $cfg['title'] . ' berhasil di-VOID.'];
    }

    public function delete_loan(string $kind, int $id): array
    {
        $cfg = $this->loan_config($kind);
        $row = $this->get_loan_by_id($kind, $id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data ' . strtolower($cfg['title']) . ' tidak ditemukan.'];
        }
        $paymentCount = (int)$this->db->where($cfg['loan_fk'], $id)->count_all_results($cfg['payment_table']);
        if ($paymentCount > 0) {
            return ['ok' => false, 'message' => 'Transaksi yang sudah punya pembayaran tidak bisa dihapus. Gunakan VOID bila perlu.'];
        }
        if ((int)($row['initial_mutation_id'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Transaksi ini sudah pernah memengaruhi saldo rekening. Gunakan VOID agar jejak mutasinya tetap rapi.'];
        }

        $this->db->where('id', $id)->delete($cfg['header_table']);
        return ['ok' => true, 'message' => $cfg['title'] . ' berhasil dihapus.'];
    }
}
