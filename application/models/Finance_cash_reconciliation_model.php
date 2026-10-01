<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__ . '/../libraries/Finance_mutation_policy.php';

/**
 * Rekonsiliasi kas disimpan terpisah dari mutasi rekening. Saldo fisik yang
 * diinput tidak pernah mengubah rekening sebelum penyesuaian diposting.
 */
class Finance_cash_reconciliation_model extends CI_Model
{
    private const HEADER_TABLE = 'fin_cash_reconciliation';
    private const LINE_TABLE = 'fin_cash_reconciliation_line';
    private const ACCOUNT_TABLE = 'fin_company_account';
    private const MUTATION_TABLE = 'fin_account_mutation_log';

    private function is_ready(): bool
    {
        return $this->db->table_exists(self::HEADER_TABLE)
            && $this->db->table_exists(self::LINE_TABLE)
            && $this->db->table_exists(self::ACCOUNT_TABLE)
            && $this->db->table_exists(self::MUTATION_TABLE);
    }

    private function normalize_date($value): ?string
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function normalize_amount($value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $value = trim(str_ireplace('rp', '', (string)$value));
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');
        if ($comma !== false && $dot !== false) {
            if ($comma > $dot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($comma !== false) {
            $fractionLength = strlen($value) - $comma - 1;
            $value = $fractionLength > 0 && $fractionLength <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif ($dot !== false) {
            $fractionLength = strlen($value) - $dot - 1;
            if ($fractionLength === 3) {
                $value = str_replace('.', '', $value);
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round((float)$value, 2);
    }

    private function normalize_note($value): ?string
    {
        $value = trim(strip_tags((string)$value));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }

    private function effective_mutation_where(string $alias = 'm'): string
    {
        $alias = trim($alias) !== '' ? trim($alias) : 'm';
        return "{$alias}.reversal_of_mutation_id IS NULL\n"
            . "AND NOT EXISTS (\n"
            . "  SELECT 1 FROM " . self::MUTATION_TABLE . " reversal\n"
            . "  WHERE reversal.reversal_of_mutation_id = {$alias}.id\n"
            . ")";
    }

    private function account_system_balance_as_of(int $accountId, string $reconciliationDate, float $openingBalance = 0.0): float
    {
        $sql = "SELECT COALESCE(SUM(CASE WHEN m.mutation_type = 'IN' THEN m.amount ELSE -m.amount END), 0) AS movement_total "
            . 'FROM ' . self::MUTATION_TABLE . ' m '
            . 'WHERE m.account_id = ? '
            . '  AND m.mutation_date <= ? '
            . '  AND ' . $this->effective_mutation_where('m');
        $row = $this->db->query($sql, [$accountId, $reconciliationDate])->row_array();

        return round($openingBalance + (float)($row['movement_total'] ?? 0), 2);
    }

    private function get_header_by_id(int $headerId, bool $forUpdate = false): ?array
    {
        if ($headerId <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM ' . self::HEADER_TABLE . ' WHERE id = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->query($sql, [$headerId])->row_array() ?: null;
    }

    private function get_latest_header(string $reconciliationDate, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM ' . self::HEADER_TABLE
            . ' WHERE reconciliation_date = ? ORDER BY round_no DESC, id DESC LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->query($sql, [$reconciliationDate])->row_array() ?: null;
    }

    private function live_system_balance_for_account(array $account, string $reconciliationDate): float
    {
        // Rekonsiliasi adalah alat cek, bukan pengunci saldo. Hari ini selalu
        // memakai saldo rekening live; tanggal lampau memakai posisi efektif.
        if ($reconciliationDate === date('Y-m-d')) {
            return round((float)($account['current_balance'] ?? 0), 2);
        }

        return $this->account_system_balance_as_of(
            (int)($account['id'] ?? 0),
            $reconciliationDate,
            (float)($account['opening_balance'] ?? 0)
        );
    }

    private function reconciliation_no(string $reconciliationDate, int $roundNo): string
    {
        $base = 'REK-KAS-' . date('Ymd', strtotime($reconciliationDate));
        return $roundNo > 1 ? $base . '-' . str_pad((string)$roundNo, 2, '0', STR_PAD_LEFT) : $base;
    }

    private function create_round_locked(string $reconciliationDate, int $actorUserId, string $sourceIp = ''): array
    {
        $roundRow = $this->db
            ->query(
                'SELECT COALESCE(MAX(round_no), 0) AS last_round FROM ' . self::HEADER_TABLE
                . ' WHERE reconciliation_date = ? FOR UPDATE',
                [$reconciliationDate]
            )
            ->row_array();
        $roundNo = max(1, (int)($roundRow['last_round'] ?? 0) + 1);
        $now = date('Y-m-d H:i:s');
        $reconciliationNo = $this->reconciliation_no($reconciliationDate, $roundNo);

        $this->db->insert(self::HEADER_TABLE, [
            'reconciliation_no' => $reconciliationNo,
            'reconciliation_date' => $reconciliationDate,
            'round_no' => $roundNo,
            'reconciled_at' => $now,
            'status' => 'OPEN',
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => $now,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => $now,
        ]);
        $headerId = (int)$this->db->insert_id();
        if ($headerId <= 0) {
            throw new RuntimeException('Sesi pengecekan rekonsiliasi tidak dapat dibuat.');
        }

        $activeAccountCount = (int)$this->db
            ->from(self::ACCOUNT_TABLE)
            ->where('is_active', 1)
            ->count_all_results();

        $header = $this->get_header_by_id($headerId, true);
        if (!$header) {
            throw new RuntimeException('Dokumen sesi pengecekan tidak dapat dibaca.');
        }

        $this->write_audit(
            'CASH_RECON_ROUND_CREATE',
            self::HEADER_TABLE,
            $headerId,
            $reconciliationNo,
            $actorUserId,
            $sourceIp,
            [],
            [
                'reconciliation_date' => $reconciliationDate,
                'round_no' => $roundNo,
                'reconciled_at' => $now,
                'active_account_count' => $activeAccountCount,
            ],
            'Membuat sesi pengecekan rekonsiliasi kas baru tanpa mengunci saldo sistem.'
        );

        return $header;
    }

    public function create_round(string $reconciliationDate, int $actorUserId = 0, string $sourceIp = ''): array
    {
        if (!$this->is_ready()) {
            return ['ok' => false, 'message' => 'Fondasi rekonsiliasi kas belum tersedia.'];
        }

        $reconciliationDate = $this->normalize_date($reconciliationDate);
        if ($reconciliationDate === null) {
            return ['ok' => false, 'message' => 'Tanggal rekonsiliasi tidak valid.'];
        }

        $this->db->trans_begin();
        try {
            $header = $this->create_round_locked($reconciliationDate, $actorUserId, $sourceIp);
            if (!$this->db->trans_status()) {
                throw new RuntimeException('Sesi pengecekan rekonsiliasi tidak selesai dibuat.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'message' => 'Sesi cek ' . (int)$header['round_no'] . ' siap untuk pengecekan saldo live.',
                'header' => $header,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Cash reconciliation round creation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage() ?: 'Gagal membuat sesi pengecekan.'];
        }
    }

    private function get_line(int $lineId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT l.*, h.reconciliation_no, h.reconciliation_date, h.round_no, h.reconciled_at, h.status AS reconciliation_status, '
            . 'a.account_code, a.account_name, a.account_type, a.current_balance AS current_system_balance, '
            . 'counter.account_code AS counter_account_code, counter.account_name AS counter_account_name, '
            . 'm.mutation_no, cm.mutation_no AS counter_mutation_no, '
            . 'entered.username AS entered_by_username, resolved.username AS resolved_by_username '
            . 'FROM ' . self::LINE_TABLE . ' l '
            . 'JOIN ' . self::HEADER_TABLE . ' h ON h.id = l.reconciliation_id '
            . 'JOIN ' . self::ACCOUNT_TABLE . ' a ON a.id = l.account_id '
            . 'LEFT JOIN ' . self::ACCOUNT_TABLE . ' counter ON counter.id = l.counter_account_id '
            . 'LEFT JOIN ' . self::MUTATION_TABLE . ' m ON m.id = l.mutation_id '
            . 'LEFT JOIN ' . self::MUTATION_TABLE . ' cm ON cm.id = l.counter_mutation_id '
            . 'LEFT JOIN auth_user entered ON entered.id = l.entered_by '
            . 'LEFT JOIN auth_user resolved ON resolved.id = l.resolved_by '
            . 'WHERE l.id = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->query($sql, [$lineId])->row_array() ?: null;
    }

    private function get_line_for_post(int $lineId): ?array
    {
        $stub = $this->db
            ->query('SELECT reconciliation_id FROM ' . self::LINE_TABLE . ' WHERE id = ? LIMIT 1', [$lineId])
            ->row_array();
        if (!$stub) {
            return null;
        }

        // Semua alur posting mengunci header, lalu line, lalu rekening.
        // Urutan konsisten ini mencegah deadlock dengan autosave rekonsiliasi.
        $header = $this->db
            ->query('SELECT * FROM ' . self::HEADER_TABLE . ' WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$stub['reconciliation_id']])
            ->row_array();
        if (!$header) {
            return null;
        }

        $line = $this->db
            ->query('SELECT * FROM ' . self::LINE_TABLE . ' WHERE id = ? AND reconciliation_id = ? LIMIT 1 FOR UPDATE', [$lineId, (int)$header['id']])
            ->row_array();
        if (!$line) {
            return null;
        }

        $line['reconciliation_no'] = (string)$header['reconciliation_no'];
        $line['reconciliation_date'] = (string)$header['reconciliation_date'];
        $line['round_no'] = (int)($header['round_no'] ?? 0);
        $line['reconciled_at'] = (string)($header['reconciled_at'] ?? '');
        $line['reconciliation_status'] = (string)$header['status'];
        return $line;
    }

    private function serialize_line(array $line): array
    {
        $actual = array_key_exists('actual_balance', $line) && $line['actual_balance'] !== null
            ? round((float)$line['actual_balance'], 2)
            : null;

        return [
            'id' => (int)($line['id'] ?? 0),
            'reconciliation_id' => (int)($line['reconciliation_id'] ?? 0),
            'reconciliation_no' => (string)($line['reconciliation_no'] ?? ''),
            'reconciliation_date' => (string)($line['reconciliation_date'] ?? ''),
            'round_no' => (int)($line['round_no'] ?? 0),
            'reconciled_at' => (string)($line['reconciled_at'] ?? ''),
            'reconciliation_status' => (string)($line['reconciliation_status'] ?? ''),
            'account_id' => (int)($line['account_id'] ?? 0),
            'account_code' => (string)($line['account_code'] ?? ''),
            'account_name' => (string)($line['account_name'] ?? ''),
            'account_type' => (string)($line['account_type'] ?? 'OTHER'),
            'current_system_balance' => round((float)($line['current_system_balance'] ?? 0), 2),
            'system_balance' => round((float)($line['system_balance'] ?? 0), 2),
            'actual_balance' => $actual,
            'difference_amount' => round((float)($line['difference_amount'] ?? 0), 2),
            'resolution_type' => (string)($line['resolution_type'] ?? 'NONE'),
            'report_category' => (string)($line['report_category'] ?? ''),
            'settlement_control_id' => (int)($line['settlement_control_id'] ?? 0),
            'settlement_charge_id' => (int)($line['settlement_charge_id'] ?? 0),
            'counter_account_id' => (int)($line['counter_account_id'] ?? 0),
            'counter_account_code' => (string)($line['counter_account_code'] ?? ''),
            'counter_account_name' => (string)($line['counter_account_name'] ?? ''),
            'resolution_note' => (string)($line['resolution_note'] ?? ''),
            'status' => (string)($line['status'] ?? 'UNCHECKED'),
            'mutation_id' => (int)($line['mutation_id'] ?? 0),
            'mutation_no' => (string)($line['mutation_no'] ?? ''),
            'counter_mutation_id' => (int)($line['counter_mutation_id'] ?? 0),
            'counter_mutation_no' => (string)($line['counter_mutation_no'] ?? ''),
            'entered_at' => (string)($line['entered_at'] ?? ''),
            'entered_by_username' => (string)($line['entered_by_username'] ?? ''),
            'resolved_at' => (string)($line['resolved_at'] ?? ''),
            'resolved_by_username' => (string)($line['resolved_by_username'] ?? ''),
        ];
    }

    private function load_lines_by_header(int $headerId): array
    {
        if ($headerId <= 0) {
            return [];
        }

        $rows = $this->db
            ->select('l.*, counter.account_code AS counter_account_code, counter.account_name AS counter_account_name, '
                . 'm.mutation_no, cm.mutation_no AS counter_mutation_no, '
                . 'entered.username AS entered_by_username, resolved.username AS resolved_by_username')
            ->from(self::LINE_TABLE . ' l')
            ->join(self::ACCOUNT_TABLE . ' counter', 'counter.id = l.counter_account_id', 'left')
            ->join(self::MUTATION_TABLE . ' m', 'm.id = l.mutation_id', 'left')
            ->join(self::MUTATION_TABLE . ' cm', 'cm.id = l.counter_mutation_id', 'left')
            ->join('auth_user entered', 'entered.id = l.entered_by', 'left')
            ->join('auth_user resolved', 'resolved.id = l.resolved_by', 'left')
            ->where('l.reconciliation_id', $headerId)
            ->get()
            ->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['account_id']] = $row;
        }
        return $result;
    }

    public function dashboard(string $reconciliationDate, int $reconciliationId = 0): array
    {
        if (!$this->is_ready()) {
            return [
                'ok' => false,
                'message' => 'Fondasi rekonsiliasi kas belum tersedia. Jalankan migration modul rekonsiliasi kas terlebih dahulu.',
            ];
        }

        $reconciliationDate = $this->normalize_date($reconciliationDate) ?: date('Y-m-d');
        $header = $reconciliationId > 0 ? $this->get_header_by_id($reconciliationId) : null;
        if (!$header || (string)$header['reconciliation_date'] !== $reconciliationDate) {
            $header = $this->get_latest_header($reconciliationDate);
        }
        $lineByAccount = $header ? $this->load_lines_by_header((int)$header['id']) : [];

        $sql = 'SELECT a.id, a.account_code, a.account_name, a.account_type, a.bank_name, a.account_no, '
            . 'a.currency_code, a.opening_balance, a.current_balance, a.is_default, '
            . "COALESCE((SELECT SUM(CASE WHEN m.mutation_type = 'IN' THEN m.amount ELSE -m.amount END) "
            . '          FROM ' . self::MUTATION_TABLE . ' m '
            . '          WHERE m.account_id = a.id '
            . '            AND m.mutation_date <= ? '
            . '            AND ' . $this->effective_mutation_where('m') . '), 0) AS movement_total '
            . 'FROM ' . self::ACCOUNT_TABLE . ' a '
            . 'WHERE a.is_active = 1 '
            . "ORDER BY FIELD(a.account_type, 'CASH', 'BANK', 'EWALLET', 'OTHER'), a.is_default DESC, a.account_name ASC";
        $accounts = $this->db->query($sql, [$reconciliationDate])->result_array();

        $rows = [];
        $summary = [
            'system_total' => 0.0,
            'actual_total' => 0.0,
            'difference_total' => 0.0,
            'counted_accounts' => 0,
            'matched_accounts' => 0,
            'open_accounts' => 0,
            'posted_accounts' => 0,
            'incoming_adjustment_total' => 0.0,
            'outgoing_adjustment_total' => 0.0,
            'open_difference_total' => 0.0,
        ];

        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];
            $line = $lineByAccount[$accountId] ?? null;
            $calculatedSystemBalance = round((float)$account['opening_balance'] + (float)$account['movement_total'], 2);
            $systemBalance = $this->live_system_balance_for_account($account, $reconciliationDate);
            $actualBalance = $line !== null && $line['actual_balance'] !== null
                ? round((float)$line['actual_balance'], 2)
                : null;
            $difference = $actualBalance === null ? null : round($actualBalance - $systemBalance, 2);
            $storedStatus = strtoupper((string)($line['status'] ?? ''));
            $status = $actualBalance === null
                ? 'UNCHECKED'
                : ($storedStatus === 'POSTED'
                    ? 'POSTED'
                    : (abs((float)$difference) < 0.005 ? 'MATCHED' : 'OPEN'));

            $row = [
                'id' => $line ? (int)$line['id'] : 0,
                'reconciliation_id' => $header ? (int)$header['id'] : 0,
                'reconciliation_no' => (string)($header['reconciliation_no'] ?? ''),
                'round_no' => (int)($header['round_no'] ?? 0),
                'reconciled_at' => (string)($header['reconciled_at'] ?? ''),
                'reconciliation_status' => (string)($header['status'] ?? ''),
                'reconciliation_date' => $reconciliationDate,
                'account_id' => $accountId,
                'account_code' => (string)$account['account_code'],
                'account_name' => (string)$account['account_name'],
                'account_type' => (string)$account['account_type'],
                'bank_name' => (string)($account['bank_name'] ?? ''),
                'account_no' => (string)($account['account_no'] ?? ''),
                'currency_code' => (string)($account['currency_code'] ?? 'IDR'),
                'current_system_balance' => $systemBalance,
                'calculated_system_balance' => $calculatedSystemBalance,
                'system_balance' => $systemBalance,
                'actual_balance' => $actualBalance,
                'difference_amount' => $difference,
                'resolution_type' => (string)($line['resolution_type'] ?? 'NONE'),
                'report_category' => (string)($line['report_category'] ?? ''),
                'settlement_control_id' => (int)($line['settlement_control_id'] ?? 0),
                'settlement_charge_id' => (int)($line['settlement_charge_id'] ?? 0),
                'counter_account_id' => (int)($line['counter_account_id'] ?? 0),
                'counter_account_code' => (string)($line['counter_account_code'] ?? ''),
                'counter_account_name' => (string)($line['counter_account_name'] ?? ''),
                'resolution_note' => (string)($line['resolution_note'] ?? ''),
                'status' => (string)$status,
                'mutation_id' => (int)($line['mutation_id'] ?? 0),
                'mutation_no' => (string)($line['mutation_no'] ?? ''),
                'counter_mutation_id' => (int)($line['counter_mutation_id'] ?? 0),
                'counter_mutation_no' => (string)($line['counter_mutation_no'] ?? ''),
                'entered_at' => (string)($line['entered_at'] ?? ''),
                'entered_by_username' => (string)($line['entered_by_username'] ?? ''),
                'resolved_at' => (string)($line['resolved_at'] ?? ''),
                'resolved_by_username' => (string)($line['resolved_by_username'] ?? ''),
            ];
            $rows[] = $row;

            $summary['system_total'] += $systemBalance;
            if ($actualBalance === null) {
                continue;
            }

            $summary['actual_total'] += $actualBalance;
            $summary['difference_total'] += (float)$difference;
            $summary['counted_accounts']++;
            if (abs((float)$difference) < 0.005) {
                $summary['matched_accounts']++;
            }
            if ($status === 'POSTED') {
                $summary['posted_accounts']++;
            } elseif (abs((float)$difference) >= 0.005) {
                $summary['open_accounts']++;
                $summary['open_difference_total'] += (float)$difference;
                if ($difference > 0) {
                    $summary['incoming_adjustment_total'] += (float)$difference;
                } else {
                    $summary['outgoing_adjustment_total'] += abs((float)$difference);
                }
            }
        }

        foreach ($summary as $key => $value) {
            if (is_float($value)) {
                $summary[$key] = round($value, 2);
            }
        }

        $recentSql = 'SELECT h.id, h.reconciliation_no, h.reconciliation_date, h.round_no, h.reconciled_at, h.status, h.updated_at, '
            . 'COUNT(l.id) AS line_count, '
            . 'SUM(CASE WHEN l.actual_balance IS NOT NULL THEN 1 ELSE 0 END) AS counted_count, '
            . "SUM(CASE WHEN l.status = 'POSTED' THEN 1 ELSE 0 END) AS posted_count, "
            . "SUM(CASE WHEN l.status = 'OPEN' THEN 1 ELSE 0 END) AS open_count "
            . 'FROM ' . self::HEADER_TABLE . ' h '
            . 'LEFT JOIN ' . self::LINE_TABLE . ' l ON l.reconciliation_id = h.id '
            . 'GROUP BY h.id, h.reconciliation_no, h.reconciliation_date, h.round_no, h.reconciled_at, h.status, h.updated_at '
            . 'ORDER BY h.reconciliation_date DESC, h.round_no DESC, h.id DESC LIMIT 7';
        $recent = $this->db->query($recentSql)->result_array();

        return [
            'ok' => true,
            'reconciliation_date' => $reconciliationDate,
            'header' => $header,
            'accounts' => $rows,
            'summary' => $summary,
            'recent' => $recent,
        ];
    }

    public function save_line(array $payload, int $actorUserId = 0, string $sourceIp = ''): array
    {
        if (!$this->is_ready()) {
            return ['ok' => false, 'message' => 'Fondasi rekonsiliasi kas belum tersedia.'];
        }

        $reconciliationDate = $this->normalize_date($payload['reconciliation_date'] ?? '');
        $reconciliationId = (int)($payload['reconciliation_id'] ?? 0);
        $accountId = (int)($payload['account_id'] ?? 0);
        $actualBalance = $this->normalize_amount($payload['actual_balance'] ?? null);
        $resolutionType = strtoupper(trim((string)($payload['resolution_type'] ?? 'NONE')));
        $reportCategory = strtoupper(trim((string)($payload['report_category'] ?? '')));
        if ($reportCategory !== '' && in_array($resolutionType, ['IN', 'OUT'], true)
            && !Finance_mutation_policy::valid($reportCategory, $resolutionType)) {
            return ['ok' => false, 'message' => 'Kategori laporan tidak sesuai arah mutasi.'];
        }
        $counterAccountId = (int)($payload['counter_account_id'] ?? 0);
        $resolutionNote = $this->normalize_note($payload['resolution_note'] ?? '');

        if ($reconciliationDate === null) {
            return ['ok' => false, 'message' => 'Tanggal rekonsiliasi tidak valid.'];
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening rekonsiliasi tidak valid.'];
        }
        if ($actualBalance === null || $actualBalance < 0) {
            return ['ok' => false, 'message' => 'Masukkan saldo riil rekening dengan angka nol atau lebih.'];
        }
        if (!in_array($resolutionType, ['NONE', 'IN', 'OUT', 'TRANSFER'], true)) {
            return ['ok' => false, 'message' => 'Pilihan tindak lanjut selisih tidak valid.'];
        }

        $this->db->trans_begin();
        try {
            $header = $reconciliationId > 0
                ? $this->get_header_by_id($reconciliationId, true)
                : $this->get_latest_header($reconciliationDate, true);
            if (!$header) {
                $header = $this->create_round_locked($reconciliationDate, $actorUserId, $sourceIp);
            }
            if (!$header) {
                throw new RuntimeException('Dokumen rekonsiliasi tidak dapat dikunci.');
            }
            if ((string)$header['reconciliation_date'] !== $reconciliationDate) {
                throw new RuntimeException('Sesi pengecekan tidak sesuai dengan tanggal yang dipilih. Muat ulang halaman lalu coba lagi.');
            }

            $existing = $this->db
                ->query('SELECT * FROM ' . self::LINE_TABLE . ' WHERE reconciliation_id = ? AND account_id = ? LIMIT 1 FOR UPDATE', [(int)$header['id'], $accountId])
                ->row_array();
            if ($existing && strtoupper((string)$existing['status']) === 'POSTED') {
                throw new RuntimeException('Penyesuaian rekening ini sudah diposting dan tidak dapat diubah dari rekonsiliasi.');
            }

            $account = $this->db
                ->query('SELECT * FROM ' . self::ACCOUNT_TABLE . ' WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])
                ->row_array();
            if (!$account) {
                throw new RuntimeException('Rekening tidak ditemukan atau sudah tidak aktif.');
            }

            // Selalu ambil ulang saldo sistem ketika hasil cek disimpan. Nilai
            // pada line adalah jejak cek terakhir, bukan saldo yang dikunci.
            $systemBalance = $this->live_system_balance_for_account($account, $reconciliationDate);
            $difference = round($actualBalance - $systemBalance, 2);

            if (abs($difference) < 0.005) {
                $resolutionType = 'NONE';
                $counterAccountId = 0;
                $status = 'MATCHED';
            } elseif ($resolutionType === 'NONE') {
                $counterAccountId = 0;
                $status = 'OPEN';
            } elseif ($resolutionType === 'IN') {
                if ($difference <= 0) {
                    throw new RuntimeException('Saldo riil lebih kecil dari sistem. Gunakan mutasi keluar atau transfer antar rekening.');
                }
                $counterAccountId = 0;
                $status = 'OPEN';
            } elseif ($resolutionType === 'OUT') {
                if ($difference >= 0) {
                    throw new RuntimeException('Saldo riil lebih besar dari sistem. Gunakan mutasi masuk atau transfer antar rekening.');
                }
                $counterAccountId = 0;
                $status = 'OPEN';
            } else {
                if ($counterAccountId <= 0 || $counterAccountId === $accountId) {
                    throw new RuntimeException('Pilih rekening lawan yang berbeda untuk transfer antar rekening.');
                }
                $counter = $this->db
                    ->query('SELECT id FROM ' . self::ACCOUNT_TABLE . ' WHERE id = ? AND is_active = 1 LIMIT 1', [$counterAccountId])
                    ->row_array();
                if (!$counter) {
                    throw new RuntimeException('Rekening lawan tidak ditemukan atau sudah tidak aktif.');
                }
                $status = 'OPEN';
            }

            $now = date('Y-m-d H:i:s');
            $linePayload = [
                'reconciliation_id' => (int)$header['id'],
                'account_id' => $accountId,
                'system_balance' => $systemBalance,
                'actual_balance' => $actualBalance,
                'difference_amount' => $difference,
                'resolution_type' => $resolutionType,
                'counter_account_id' => $counterAccountId > 0 ? $counterAccountId : null,
                'resolution_note' => $resolutionNote,
                'status' => $status,
                'entered_by' => $actorUserId > 0 ? $actorUserId : null,
                'entered_at' => $now,
                'updated_at' => $now,
            ];
            if ($this->db->field_exists('report_category', self::LINE_TABLE)) {
                $linePayload['report_category'] = in_array($resolutionType, ['IN', 'OUT'], true) ? ($reportCategory ?: null) : null;
            } elseif ($reportCategory !== '') {
                throw new RuntimeException('Jalankan migrasi kategori mutasi 2026-09-13a terlebih dahulu.');
            }

            if ($this->db->field_exists('settlement_control_id', self::LINE_TABLE)) {
                $linePayload['settlement_control_id'] = in_array($resolutionType,['IN','OUT'],true) ? (max(0,(int)($payload['settlement_control_id']??0)) ?: null) : null;
            }
            if ($this->db->field_exists('settlement_charge_id', self::LINE_TABLE)) $linePayload['settlement_charge_id']=in_array($resolutionType,['IN','OUT'],true)?(max(0,(int)($payload['settlement_charge_id']??0))?:null):null;
            if ($existing) {
                $this->db->where('id', (int)$existing['id'])->update(self::LINE_TABLE, $linePayload);
                $lineId = (int)$existing['id'];
            } else {
                $linePayload['created_at'] = $now;
                $this->db->insert(self::LINE_TABLE, $linePayload);
                $lineId = (int)$this->db->insert_id();
            }
            if ($lineId <= 0) {
                throw new RuntimeException('Baris rekonsiliasi tidak dapat disimpan.');
            }

            $this->sync_header_status((int)$header['id'], $actorUserId);
            $this->write_audit(
                'CASH_RECON_SAVE',
                self::LINE_TABLE,
                $lineId,
                (string)$header['reconciliation_no'],
                $actorUserId,
                $sourceIp,
                $existing ?: [],
                $linePayload,
                'Simpan saldo riil rekonsiliasi kas.'
            );

            if (!$this->db->trans_status()) {
                throw new RuntimeException('Penyimpanan rekonsiliasi tidak selesai.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'message' => abs($difference) < 0.005
                    ? 'Saldo riil cocok dengan saldo sistem.'
                    : 'Saldo riil disimpan. Pilih tindak lanjut lalu posting bila selisih sudah akan disesuaikan.',
                'line' => $this->serialize_line($this->get_line($lineId) ?: []),
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Cash reconciliation save failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage() ?: 'Gagal menyimpan rekonsiliasi kas.'];
        }
    }

    private function lock_accounts(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map('intval', $accountIds))));
        sort($accountIds);
        if (empty($accountIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $rows = $this->db
            ->query('SELECT * FROM ' . self::ACCOUNT_TABLE . ' WHERE id IN (' . $placeholders . ') AND is_active = 1 ORDER BY id FOR UPDATE', $accountIds)
            ->result_array();
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['id']] = $row;
        }
        return $result;
    }

    private function mutation_number(string $reconciliationDate, int $lineId, string $suffix): string
    {
        return 'RKMUT-' . date('Ymd', strtotime($reconciliationDate)) . '-' . str_pad((string)$lineId, 8, '0', STR_PAD_LEFT) . '-' . strtoupper($suffix);
    }

    private function post_locked_mutation(
        array &$account,
        string $mutationType,
        float $amount,
        string $mutationDate,
        string $moduleCode,
        int $lineId,
        string $reconciliationNo,
        string $notes,
        int $actorUserId,
        string $suffix,
        ?string $reportCategory = null,
        int $settlementId = 0,
        int $chargeId = 0
    ): array {
        require_once APPPATH . 'libraries/Finance_settlement_control.php';
        Finance_settlement_control::assert_post($this->db,$settlementId,(string)$reportCategory,(int)$account['id'],$mutationDate,$moduleCode,0,$chargeId,$amount,$mutationType);
        $mutationType = strtoupper($mutationType);
        $amount = round($amount, 2);
        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $balanceAfter = $mutationType === 'IN'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        if ($mutationType === 'OUT' && $balanceAfter < -0.004) {
            throw new RuntimeException('Saldo rekening sumber tidak cukup untuk menyelesaikan penyesuaian ini.');
        }
        // A destination may already have a negative balance. Credit exactly the
        // posted amount; clamping to zero would create cash without a mutation.

        $this->db->where('id', (int)$account['id'])->update(self::ACCOUNT_TABLE, [
            'current_balance' => $balanceAfter,
        ]);
        if (!$this->db->affected_rows() && abs($balanceAfter - $balanceBefore) >= 0.005) {
            throw new RuntimeException('Saldo rekening tidak dapat diperbarui.');
        }

        $mutationNo = $this->mutation_number($mutationDate, $lineId, $suffix);
        $this->db->insert(self::MUTATION_TABLE, [
            'mutation_no' => $mutationNo,
            'mutation_date' => $mutationDate,
            'account_id' => (int)$account['id'],
            'mutation_type' => $mutationType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => $moduleCode,
            'report_category' => $reportCategory,
            ...($this->db->field_exists('settlement_control_id',self::MUTATION_TABLE) ? ['settlement_control_id'=>$settlementId ?: null] : []),
            ...($this->db->field_exists('settlement_charge_id',self::MUTATION_TABLE) ? ['settlement_charge_id'=>$chargeId ?: null] : []),
            'ref_table' => self::LINE_TABLE,
            'ref_id' => $lineId,
            'ref_no' => $reconciliationNo,
            'notes' => $notes,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $mutationId = (int)$this->db->insert_id();
        if ($mutationId <= 0) {
            throw new RuntimeException('Mutasi penyesuaian tidak dapat dicatat.');
        }

        $account['current_balance'] = $balanceAfter;
        return [
            'mutation_id' => $mutationId,
            'mutation_no' => $mutationNo,
        ];
    }

    private function sync_header_status(int $headerId, int $actorUserId): void
    {
        $summary = $this->db
            ->select('COUNT(*) AS total_lines, '
                . "SUM(CASE WHEN actual_balance IS NOT NULL THEN 1 ELSE 0 END) AS counted_lines, "
                . "SUM(CASE WHEN status IN ('MATCHED', 'POSTED') THEN 1 ELSE 0 END) AS settled_lines", false)
            ->from(self::LINE_TABLE)
            ->where('reconciliation_id', $headerId)
            ->get()
            ->row_array();

        $activeAccounts = (int)$this->db
            ->from(self::ACCOUNT_TABLE)
            ->where('is_active', 1)
            ->count_all_results();
        $counted = (int)($summary['counted_lines'] ?? 0);
        $settled = (int)($summary['settled_lines'] ?? 0);
        $status = $activeAccounts > 0 && $counted >= $activeAccounts && $settled >= $activeAccounts
            ? 'COMPLETED'
            : 'OPEN';
        $this->db->where('id', $headerId)->update(self::HEADER_TABLE, [
            'status' => $status,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function post_line(int $lineId, int $actorUserId = 0, string $sourceIp = ''): array
    {
        if (!$this->is_ready()) {
            return ['ok' => false, 'message' => 'Fondasi rekonsiliasi kas belum tersedia.'];
        }
        if ($lineId <= 0) {
            return ['ok' => false, 'message' => 'Baris rekonsiliasi tidak valid.'];
        }

        $this->db->trans_begin();
        try {
            $line = $this->get_line_for_post($lineId);
            if (!$line) {
                throw new RuntimeException('Baris rekonsiliasi tidak ditemukan.');
            }
            if (strtoupper((string)$line['status']) === 'POSTED' || (int)$line['mutation_id'] > 0) {
                throw new RuntimeException('Penyesuaian ini sudah pernah diposting.');
            }
            if ($line['actual_balance'] === null) {
                throw new RuntimeException('Simpan saldo riil rekening terlebih dahulu.');
            }
            if ((string)$line['reconciliation_date'] !== date('Y-m-d')) {
                throw new RuntimeException('Penyesuaian hanya dapat diposting dari pengecekan hari ini agar memakai saldo sistem live.');
            }

            $resolutionType = strtoupper((string)$line['resolution_type']);
            if (!in_array($resolutionType, ['IN', 'OUT', 'TRANSFER'], true)) {
                throw new RuntimeException('Pilih mutasi masuk, keluar, atau transfer antar rekening sebelum posting.');
            }
            Finance_mutation_policy::assert_open_period($this->db, (string)$line['reconciliation_date']);
            $reportCategory = (string)($line['report_category'] ?? '');
            if (!$this->db->field_exists('report_category', self::MUTATION_TABLE)) {
                throw new RuntimeException('Jalankan migrasi kategori mutasi 2026-09-13a terlebih dahulu.');
            }
            if ($resolutionType !== 'TRANSFER' && !Finance_mutation_policy::valid($reportCategory, $resolutionType)) {
                throw new RuntimeException('Pilih kategori laporan dan simpan sebelum memposting penyesuaian.');
            }

            $accountId = (int)$line['account_id'];
            $counterAccountId = (int)($line['counter_account_id'] ?? 0);
            if ($resolutionType === 'TRANSFER' && ($counterAccountId <= 0 || $counterAccountId === $accountId)) {
                throw new RuntimeException('Rekening lawan transfer tidak valid.');
            }

            $lockedAccounts = $this->lock_accounts($resolutionType === 'TRANSFER'
                ? [$accountId, $counterAccountId]
                : [$accountId]);
            if (!isset($lockedAccounts[$accountId])) {
                throw new RuntimeException('Rekening rekonsiliasi sudah tidak aktif.');
            }
            if ($resolutionType === 'TRANSFER' && !isset($lockedAccounts[$counterAccountId])) {
                throw new RuntimeException('Rekening lawan transfer sudah tidak aktif.');
            }
            if ($resolutionType === 'TRANSFER' && (string)($lockedAccounts[$accountId]['currency_code']??'') !== (string)($lockedAccounts[$counterAccountId]['currency_code']??'')) {
                throw new RuntimeException('Transfer rekonsiliasi hanya untuk rekening dengan mata uang sama.');
            }

            // Rekening sudah terkunci dalam transaksi ini, sehingga selisih
            // berikut memakai saldo sistem live yang sama dengan yang diposting.
            $systemBalance = round((float)$lockedAccounts[$accountId]['current_balance'], 2);
            $difference = round((float)$line['actual_balance'] - $systemBalance, 2);
            if (abs($systemBalance-(float)$line['system_balance'])>=.005 || abs($difference-(float)$line['difference_amount'])>=.005) {
                throw new RuntimeException('Saldo berubah setelah hasil cek disimpan. Muat ulang, periksa saldo riil, lalu simpan dan konfirmasi kembali; nominal lama tidak diposting.');
            }
            if (abs($difference) < 0.005) {
                throw new RuntimeException('Tidak ada selisih pada saldo sistem live yang perlu diposting.');
            }
            if ($resolutionType === 'IN' && $difference <= 0) {
                throw new RuntimeException('Saldo riil lebih kecil dari saldo sistem live. Gunakan mutasi keluar atau transfer.');
            }
            if ($resolutionType === 'OUT' && $difference >= 0) {
                throw new RuntimeException('Saldo riil lebih besar dari saldo sistem live. Gunakan mutasi masuk atau transfer.');
            }

            $amount = abs($difference);
            $direction = $difference > 0 ? 'lebih besar' : 'lebih kecil';
            $note = 'Rekonsiliasi kas ' . (string)$line['reconciliation_no']
                . ': saldo riil ' . $direction . ' dari saldo sistem.';
            if (!empty($line['resolution_note'])) {
                $note .= ' ' . (string)$line['resolution_note'];
            }
            $note = mb_substr($note, 0, 255);

            $primaryMutation = null;
            $counterMutation = null;
            if ($resolutionType === 'IN') {
                $primaryMutation = $this->post_locked_mutation(
                    $lockedAccounts[$accountId], 'IN', $amount, (string)$line['reconciliation_date'], 'FINANCE_RECON',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'IN', $reportCategory, (int)($line['settlement_control_id']??0), (int)($line['settlement_charge_id']??0)
                );
            } elseif ($resolutionType === 'OUT') {
                $primaryMutation = $this->post_locked_mutation(
                    $lockedAccounts[$accountId], 'OUT', $amount, (string)$line['reconciliation_date'], 'FINANCE_RECON',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'OUT', $reportCategory, (int)($line['settlement_control_id']??0), (int)($line['settlement_charge_id']??0)
                );
            } elseif ($difference > 0) {
                $counterMutation = $this->post_locked_mutation(
                    $lockedAccounts[$counterAccountId], 'OUT', $amount, (string)$line['reconciliation_date'], 'FINANCE_TRANSFER',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'TRANSFER-OUT'
                );
                $primaryMutation = $this->post_locked_mutation(
                    $lockedAccounts[$accountId], 'IN', $amount, (string)$line['reconciliation_date'], 'FINANCE_TRANSFER',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'TRANSFER-IN'
                );
            } else {
                $primaryMutation = $this->post_locked_mutation(
                    $lockedAccounts[$accountId], 'OUT', $amount, (string)$line['reconciliation_date'], 'FINANCE_TRANSFER',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'TRANSFER-OUT'
                );
                $counterMutation = $this->post_locked_mutation(
                    $lockedAccounts[$counterAccountId], 'IN', $amount, (string)$line['reconciliation_date'], 'FINANCE_TRANSFER',
                    $lineId, (string)$line['reconciliation_no'], $note, $actorUserId, 'TRANSFER-IN'
                );
            }

            $now = date('Y-m-d H:i:s');
            $settledSystemBalance = round((float)$lockedAccounts[$accountId]['current_balance'], 2);
            $beforeLine = $line;
            $lineUpdate = [
                // Setelah mutasi, line mengikuti saldo sistem terbaru. Nilai
                // selisih sebelum penyesuaian tetap terlacak di mutasi/audit.
                'system_balance' => $settledSystemBalance,
                'difference_amount' => $difference,
                'status' => 'POSTED',
                'mutation_id' => (int)($primaryMutation['mutation_id'] ?? 0),
                'counter_mutation_id' => (int)($counterMutation['mutation_id'] ?? 0) ?: null,
                'resolved_by' => $actorUserId > 0 ? $actorUserId : null,
                'resolved_at' => $now,
                'updated_at' => $now,
            ];
            $this->db->where('id', $lineId)->update(self::LINE_TABLE, $lineUpdate);
            $this->sync_header_status((int)$line['reconciliation_id'], $actorUserId);
            $this->write_audit(
                'CASH_RECON_POST',
                self::LINE_TABLE,
                $lineId,
                (string)$line['reconciliation_no'],
                $actorUserId,
                $sourceIp,
                $beforeLine,
                $lineUpdate,
                'Posting penyesuaian rekonsiliasi kas.'
            );

            if (!$this->db->trans_status()) {
                throw new RuntimeException('Posting penyesuaian tidak selesai.');
            }
            if ($this->db->trans_commit() === false) {
                throw new RuntimeException('Posting penyesuaian gagal disimpan.');
            }

            return [
                'ok' => true,
                'message' => $resolutionType === 'TRANSFER'
                    ? 'Transfer penyesuaian berhasil diposting sebagai dua mutasi rekening terkait.'
                    : 'Penyesuaian saldo berhasil diposting ke mutasi rekening.',
                'line' => $this->serialize_line($this->get_line($lineId) ?: []),
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            log_message('error', 'Cash reconciliation post failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage() ?: 'Gagal memposting penyesuaian rekonsiliasi.'];
        }
    }

    private function write_audit(
        string $actionCode,
        string $entityTable,
        int $entityId,
        string $transactionNo,
        int $actorUserId,
        string $sourceIp,
        array $beforePayload,
        array $afterPayload,
        string $notes
    ): void {
        if (!$this->db->table_exists('aud_transaction_log')) {
            return;
        }

        $this->db->insert('aud_transaction_log', [
            'module_code' => 'FINANCE',
            'action_code' => $actionCode,
            'entity_table' => $entityTable,
            'entity_id' => $entityId > 0 ? $entityId : null,
            'transaction_no' => $transactionNo !== '' ? $transactionNo : null,
            'ref_table' => self::HEADER_TABLE,
            'ref_id' => null,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'source_ip' => $sourceIp !== '' ? $sourceIp : null,
            'before_payload' => !empty($beforePayload) ? json_encode($beforePayload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'after_payload' => !empty($afterPayload) ? json_encode($afterPayload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
