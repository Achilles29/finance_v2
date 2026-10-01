<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Canonical customer identity. Printer/outlet settings remain explicit overrides. */
class Business_profile_model extends CI_Model
{
    private const PROFILE_TABLE = 'sys_business_profile';
    private const AUDIT_TABLE = 'sys_business_profile_audit';

    public function menu_book_template(): string
    {
        $this->load->library('Customer_publication');
        if (!$this->db->table_exists('sys_app_config')) return Customer_publication::template(null);
        $row = $this->db->select('config_value')->from('sys_app_config')
            ->where('config_key', Customer_publication::TEMPLATE_KEY)->limit(1)->get()->row_array();
        return Customer_publication::template($row['config_value'] ?? null);
    }

    public function ready(): bool
    {
        return $this->db->table_exists(self::PROFILE_TABLE)
            && $this->db->table_exists(self::AUDIT_TABLE);
    }

    public function profile(): array
    {
        $defaults = [
            'id' => 1,
            'legal_name' => '',
            'display_name' => 'Finance POS',
            'short_name' => '',
            'tax_id' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website_url' => '',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
            'currency_code' => 'IDR',
            'logo_url' => '',
            'document_footer' => '',
            'updated_at' => null,
        ];
        if (!$this->ready()) {
            return $defaults;
        }
        $row = $this->db->from(self::PROFILE_TABLE)->where('id', 1)->limit(1)->get()->row_array();
        return array_merge($defaults, is_array($row) ? $row : []);
    }

    public function save(array $input, int $actorId, string $requestIp = ''): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'message' => 'Fondasi Profil Usaha belum tersedia. Jalankan migration C2/C4 terlebih dahulu.'];
        }

        $current = $this->profile();
        $payload = $this->normalize($input, $current);
        $error = $this->validate($payload);
        if ($error !== '') {
            return ['ok' => false, 'message' => $error];
        }

        $record = $payload + [
            'id' => 1,
            'updated_by' => $actorId > 0 ? $actorId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $before = $this->audit_projection($current);
        $after = $this->audit_projection($record);
        $template = null;
        if (array_key_exists('menu_book_template', $input)) {
            $this->load->library('Customer_publication');
            if (!is_string($input['menu_book_template']) || !in_array($input['menu_book_template'], Customer_publication::TEMPLATES, true)
                || ($input['menu_book_template'] === 'legacy_namua' && !Customer_publication::legacy_available())
                || !$this->db->table_exists('sys_app_config')) {
                return ['ok' => false, 'message' => 'Pilihan template Menu Book tidak valid atau pengaturan belum tersedia.'];
            }
            $template = $input['menu_book_template'];
            $before['menu_book_template'] = $this->menu_book_template();
            $after['menu_book_template'] = $template;
        }
        $this->db->trans_begin();
        try {
            $exists = (bool)$this->db->from(self::PROFILE_TABLE)->where('id', 1)->limit(1)->count_all_results();
            $ok = $exists
                ? $this->db->where('id', 1)->update(self::PROFILE_TABLE, $record)
                : $this->db->insert(self::PROFILE_TABLE, $record);
            if (!$ok) {
                throw new RuntimeException('Penyimpanan profil usaha gagal.');
            }
            if ($template !== null && !$this->db->query(
                'INSERT INTO sys_app_config (config_group, config_key, config_value, description, updated_by) VALUES (?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)',
                ['customer', Customer_publication::TEMPLATE_KEY, $template, 'Public Menu Book template; legacy content retained separately', $actorId > 0 ? $actorId : null]
            )) throw new RuntimeException('Penyimpanan template gagal.');
            $audit = $this->db->insert(self::AUDIT_TABLE, [
                'actor_user_id' => $actorId > 0 ? $actorId : null,
                'event_code' => $exists ? 'PROFILE_UPDATED' : 'PROFILE_CREATED',
                'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'request_ip' => $requestIp !== '' ? mb_substr($requestIp, 0, 45) : null,
            ]);
            if (!$audit || $this->db->trans_status() === false) {
                throw new RuntimeException('Audit perubahan profil usaha gagal.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'profile' => array_merge($current, $record)];
        } catch (Throwable $error) {
            $this->db->trans_rollback();
            log_message('error', 'Business profile save failed.');
            return ['ok' => false, 'message' => 'Profil usaha tidak dapat disimpan. Tidak ada perubahan yang diterapkan.'];
        }
    }

    private function normalize(array $input, array $current): array
    {
        $text = static function ($value, int $limit = 0): string {
            $value = trim((string)$value);
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
            return $limit > 0 ? mb_substr($value, 0, $limit) : $value;
        };
        $url = static function ($value): string {
            $value = trim((string)$value);
            return $value === '' ? '' : mb_substr($value, 0, 255);
        };
        return [
            'legal_name' => $text($input['legal_name'] ?? '', 190),
            'display_name' => $text($input['display_name'] ?? '', 190),
            'short_name' => $text($input['short_name'] ?? '', 80),
            'tax_id' => $text($input['tax_id'] ?? '', 100),
            'address' => $text($input['address'] ?? '', 4000),
            'phone' => $text($input['phone'] ?? '', 50),
            'email' => strtolower($text($input['email'] ?? '', 190)),
            'website_url' => $url($input['website_url'] ?? ''),
            'timezone' => $text($input['timezone'] ?? 'Asia/Jakarta', 64),
            'locale' => $text($input['locale'] ?? 'id_ID', 20),
            'currency_code' => strtoupper($text($input['currency_code'] ?? 'IDR', 3)),
            'logo_url' => $this->normalize_logo_url((string)($input['logo_url'] ?? ($current['logo_url'] ?? ''))),
            'document_footer' => $text($input['document_footer'] ?? '', 500),
        ];
    }

    private function validate(array $profile): string
    {
        if ($profile['display_name'] === '') return 'Nama dagang/usaha wajib diisi.';
        if ($profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) return 'Alamat email tidak valid.';
        $this->load->library('Customer_publication');
        if ($profile['website_url'] !== '' && Customer_publication::public_url($profile['website_url']) === '') return 'Alamat website harus HTTP/HTTPS tanpa username atau password, misalnya https://usahaanda.id.';
        if (!in_array($profile['timezone'], timezone_identifiers_list(), true)) return 'Zona waktu tidak dikenal.';
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $profile['locale']) !== 1) return 'Locale harus memakai format seperti id_ID.';
        if (preg_match('/^[A-Z]{3}$/D', $profile['currency_code']) !== 1) return 'Kode mata uang harus tiga huruf, misalnya IDR.';
        return '';
    }

    private function normalize_logo_url(string $logoUrl): string
    {
        $logoUrl = trim($logoUrl);
        if ($logoUrl === '') return '';
        $parts = @parse_url($logoUrl);
        if (!is_array($parts)) return '';
        $host = strtolower((string)($parts['host'] ?? ''));
        $currentHost = strtolower((string)parse_url(base_url(), PHP_URL_HOST));
        if ($host !== '' && ($currentHost === '' || !hash_equals($currentHost, $host))) return '';
        $path = rawurldecode((string)($parts['path'] ?? ''));
        $basePath = trim((string)parse_url(base_url(), PHP_URL_PATH), '/');
        if ($basePath !== '' && strpos(ltrim($path, '/'), $basePath . '/') === 0) {
            $path = substr(ltrim($path, '/'), strlen($basePath) + 1);
        }
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if (strpos($relative, '..') !== false || preg_match('~^assets/uploads/business-profile-logo/[a-f0-9]{32}\.(?:png|jpe?g)$~i', $relative) !== 1) return '';
        $root = rtrim(str_replace('\\', '/', (string)FCPATH), '/');
        $real = realpath($root . '/' . $relative);
        return $real !== false && is_file($real) && strpos(str_replace('\\', '/', $real), $root . '/') === 0 ? base_url($relative) : '';
    }

    private function audit_projection(array $profile): array
    {
        return [
            'legal_name' => (string)($profile['legal_name'] ?? ''), 'display_name' => (string)($profile['display_name'] ?? ''),
            'short_name' => (string)($profile['short_name'] ?? ''), 'tax_id' => (string)($profile['tax_id'] ?? ''),
            'address' => (string)($profile['address'] ?? ''), 'phone' => (string)($profile['phone'] ?? ''),
            'email' => (string)($profile['email'] ?? ''), 'website_url' => (string)($profile['website_url'] ?? ''),
            'timezone' => (string)($profile['timezone'] ?? ''), 'locale' => (string)($profile['locale'] ?? ''),
            'currency_code' => (string)($profile['currency_code'] ?? ''), 'logo_url' => (string)($profile['logo_url'] ?? ''),
            'document_footer' => (string)($profile['document_footer'] ?? ''),
        ];
    }
}
