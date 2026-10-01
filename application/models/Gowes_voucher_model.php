<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Gowes_participant_import.php';

class Gowes_voucher_model extends CI_Model
{
    public const CAMPAIGN_CODE = 'GOWESVOL9-JF3X';

    public function claim(string $email): array
    {
        $email = Gowes_participant_import::email($email);
        if ($email === '') return ['ok' => false, 'message' => 'Masukkan alamat email yang valid.'];
        if (!$this->db->table_exists('evt_gowes_participant')) return ['ok' => false, 'status' => 503, 'message' => 'Klaim belum tersedia. Silakan hubungi panitia.'];
        $debug = $this->db->db_debug; $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $query = $this->db->query('SELECT * FROM evt_gowes_participant WHERE email = ? FOR UPDATE', [$email]);
            if (!$query) throw new RuntimeException('participant_lock');
            $participant = $query->row_array();
            if (!$participant || !(int)$participant['is_active']) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Email belum terdaftar sebagai peserta. Periksa email pendaftaran Anda atau hubungi panitia.'];
            }
            $existing = !empty($participant['voucher_issue_id']);
            if ($existing) {
                $query = $this->db->get_where('pos_voucher_issue', ['id' => (int)$participant['voucher_issue_id']], 1);
                $voucher = $query ? $query->row_array() : [];
                if (!$voucher) throw new RuntimeException('issued_voucher_missing');
            } else {
                $campaign = $this->db->get_where('pos_voucher_campaign', ['campaign_code' => self::CAMPAIGN_CODE, 'is_active' => 1], 1)->row_array();
                if (!$campaign || $campaign['voucher_type'] !== 'PERCENT' || (float)$campaign['discount_value'] !== 15.0
                    || (float)$campaign['min_spend_amount'] !== 0.0 || (float)$campaign['max_discount_amount'] !== 0.0
                    || $campaign['issue_mode'] !== 'MEMBER_TARGETED') {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'status' => 503, 'message' => 'Klaim voucher sedang tidak tersedia. Silakan hubungi panitia.'];
                }
                $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
                $voucher = [
                    'voucher_issue_no' => 'GW9-' . strtoupper(bin2hex(random_bytes(12))),
                    'campaign_id' => (int)$campaign['id'], 'member_id' => null,
                    'voucher_code' => $this->unused_voucher_code(),
                    'voucher_status' => 'OPEN', 'amount_snapshot' => 0, 'percent_snapshot' => 15,
                    'min_spend_amount' => 0, 'issued_at' => $now->format('Y-m-d H:i:s'),
                    'expired_at' => $now->modify('+7 days')->format('Y-m-d H:i:s'),
                    'notes' => 'Klaim peserta GOWES VOL9 / JAJAN FEST 3X #' . (int)$participant['id'],
                ];
                if (!$this->db->insert('pos_voucher_issue', $voucher)) throw new RuntimeException('voucher_insert');
                $id = (int)$this->db->insert_id();
                if (!$this->db->where('id', $participant['id'])->update('evt_gowes_participant', [
                    'voucher_issue_id' => $id, 'claimed_at' => $voucher['issued_at'], 'updated_at' => $voucher['issued_at'],
                ])) throw new RuntimeException('claim_link');
            }
            if (!$this->db->trans_status() || !$this->db->trans_commit()) throw new RuntimeException('claim_commit');
            return ['ok' => true, 'existing' => $existing, 'voucher' => self::public_voucher($voucher)];
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            log_message('error', 'GOWES voucher claim failed; no participant identity logged.');
            return ['ok' => false, 'status' => 503, 'message' => 'Klaim belum dapat diproses. Coba lagi; email yang sama tidak membuat voucher ganda.'];
        } finally { $this->db->db_debug = $debug; }
    }

    protected function generate_voucher_code(): string
    {
        // 32 unambiguous characters: eight symbols retain 40 bits of randomness.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bytes = random_bytes(8);
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $alphabet[ord($bytes[$i]) & 31];
        return $code;
    }

    private function unused_voucher_code(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->generate_voucher_code();
            $query = $this->db->get_where('pos_voucher_issue', ['voucher_code' => $code], 1);
            if (!$query) throw new RuntimeException('voucher_code_lookup');
            // The unique database index also protects against simultaneous claims.
            if (!$query->num_rows()) return $code;
        }
        throw new RuntimeException('voucher_code_collision');
    }

    public static function public_voucher(array $row): array
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $expires = new DateTimeImmutable($row['expired_at'], $zone);
        $status = $row['voucher_status'];
        if ($status === 'OPEN' && $expires->getTimestamp() < time()) $status = 'EXPIRED';
        return ['code' => $row['voucher_code'], 'percent' => (float)$row['percent_snapshot'],
            'issued_at' => (new DateTimeImmutable($row['issued_at'], $zone))->format(DateTimeInterface::ATOM),
            'expires_at' => $expires->format(DateTimeInterface::ATOM), 'status' => $status];
    }

    public function import_participants(array $prepared, string $sourceName, string $sourceHash, int $userId = 0): array
    {
        if (empty($prepared['ok']) || empty($prepared['rows'])) return ['ok' => false, 'message' => $prepared['message'] ?? 'Tidak ada email valid untuk diimpor.'];
        $debug = $this->db->db_debug; $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $added = 0; $updated = 0;
            foreach ($prepared['rows'] as $row) {
                // Upsert only registration details; never reset a claim, status, or expiry.
                $ok = $this->db->query('INSERT INTO evt_gowes_participant (email, participant_name) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE participant_name = VALUES(participant_name), updated_at = NOW()', [$row['email'], $row['participant_name']]);
                if (!$ok) throw new RuntimeException('participant_import');
                if ($this->db->affected_rows() === 1) $added++; else $updated++;
            }
            $summary = ['source_name' => mb_substr(basename($sourceName), 0, 200), 'source_sha256' => $sourceHash,
                'total_rows' => $prepared['total_rows'], 'added_rows' => $added, 'updated_rows' => $updated,
                'duplicate_rows' => $prepared['duplicate_rows'], 'invalid_rows' => $prepared['invalid_rows'],
                'invalid_row_numbers' => json_encode($prepared['invalid_row_numbers']), 'imported_by' => $userId ?: null];
            if (!$this->db->insert('evt_gowes_import', $summary) || !$this->db->trans_status() || !$this->db->trans_commit()) throw new RuntimeException('import_commit');
            return ['ok' => true, 'summary' => $summary];
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Impor gagal; perubahan dibatalkan. Periksa migrasi database dan coba lagi.'];
        } finally { $this->db->db_debug = $debug; }
    }

    public function admin_data(string $search, int $page): array
    {
        $stats = $this->db->query('SELECT COUNT(*) AS total, COALESCE(SUM(voucher_issue_id IS NOT NULL),0) AS claimed FROM evt_gowes_participant')->row_array();
        $this->db->select('p.*, v.voucher_code, v.voucher_status, v.expired_at')->from('evt_gowes_participant p')
            ->join('pos_voucher_issue v', 'v.id = p.voucher_issue_id', 'left');
        if ($search !== '') $this->db->group_start()->like('p.email', $search)->or_like('p.participant_name', $search)->group_end();
        $rows = $this->db->order_by('p.participant_name', 'ASC')->order_by('p.id', 'ASC')->limit(51, (max(1, $page) - 1) * 50)->get()->result_array();
        return ['stats' => $stats, 'participants' => array_slice($rows, 0, 50), 'has_next' => count($rows) > 50,
            'last_import' => $this->db->order_by('id', 'DESC')->limit(1)->get('evt_gowes_import')->row_array()];
    }
}
