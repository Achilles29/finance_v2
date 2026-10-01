<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $user = $this->db
            ->select('id, username, email, is_active, last_login_at')
            ->where('id', (int)($this->current_user['id'] ?? 0))
            ->get('auth_user')
            ->row_array();

        $this->render('settings/index', [
            'title'       => 'Pengaturan Akun',
            'active_menu' => 'settings',
            'can_manage_roast_connect' => $this->is_superadmin(),
            'user'        => $user ?: $this->current_user,
        ]);
    }

    public function change_password()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        if ($userId <= 0) {
            $this->jsonOut(['ok' => false, 'message' => 'Sesi tidak valid.']);
            return;
        }

        $currentPassword = (string)($this->input->post('current_password') ?? '');
        $newPassword     = (string)($this->input->post('new_password') ?? '');
        $confirmPassword = (string)($this->input->post('confirm_password') ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $this->jsonOut(['ok' => false, 'message' => 'Semua field wajib diisi.']);
            return;
        }

        if (strlen($newPassword) < 6) {
            $this->jsonOut(['ok' => false, 'message' => 'Password baru minimal 6 karakter.']);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->jsonOut(['ok' => false, 'message' => 'Konfirmasi password tidak cocok.']);
            return;
        }

        $row = $this->db
            ->select('id, password_hash')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->get('auth_user')
            ->row_array();

        if (!$row) {
            $this->jsonOut(['ok' => false, 'message' => 'Akun tidak ditemukan.']);
            return;
        }

        if (!password_verify($currentPassword, (string)($row['password_hash'] ?? ''))) {
            $this->jsonOut(['ok' => false, 'message' => 'Password saat ini tidak cocok.']);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->where('id', $userId)->update('auth_user', [
            'password_hash' => $newHash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->jsonOut(['ok' => true, 'message' => 'Password berhasil diubah.']);
    }

    private function jsonOut(array $data): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = '{"ok":false,"message":"JSON encode error"}';
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $json;
        exit;
    }
}
