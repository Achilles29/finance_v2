<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth — Controller login dan logout
 * Tidak extend MY_Controller karena halaman login harus bisa diakses tanpa login.
 */
class Auth extends CI_Controller
{
    private const INVALID_SCOPE_MESSAGE = 'Konfigurasi akses akun belum lengkap. Hubungi administrator.';
    private const LOGIN_FAILURE_MESSAGE = 'Login tidak berhasil. Periksa kredensial atau coba lagi nanti.';
    private const LOGIN_MAINTENANCE_MESSAGE = 'Layanan login sementara tidak tersedia. Silakan coba lagi nanti.';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->model('Business_profile_model');
        $this->load->helper(['url', 'form']);
        $this->load->library(['session', 'form_validation']);
    }

    // ---------------------------------------------------------------
    // LOGIN
    // ---------------------------------------------------------------

    public function index()
    {
        // Jika sudah login, langsung ke halaman pertama yang memang boleh diakses
        if ($this->session->userdata('auth_user')) {
            $perms = (array)$this->session->userdata('user_perms');
            $target = $this->resolve_post_login_redirect($perms);
            if ($target === '') {
                $this->session->sess_destroy();
                $this->session->set_flashdata('login_error', 'Akun belum memiliki akses halaman. Hubungi admin.');
                redirect('login');
            }
            redirect($target);
        }

        $businessProfile = $this->Business_profile_model->profile();
        $businessName = trim((string)($businessProfile['display_name'] ?? '')) ?: 'Finance';
        $errorMessage = $this->session->flashdata('login_error');
        if (empty($errorMessage) && $this->input->get('reason', true) === 'session_expired') {
            $errorMessage = 'Sesi login sudah tidak berlaku. Silakan login ulang.';
        }
        $data = [
            'title'     => 'Login — ' . $businessName,
            'error_msg' => $errorMessage,
            'business_profile' => $businessProfile,
        ];

        $this->load->view('auth/login', $data);
    }

    public function do_login()
    {
        // Endpoint autentikasi tidak boleh dipanggil lewat GET/HEAD sebelum
        // pemeriksaan throttle, lookup akun, atau verifikasi password berjalan.
        $requestMethod = method_exists($this->input, 'method')
            ? strtoupper((string)$this->input->method(true))
            : strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'POST'));
        if ($requestMethod !== 'POST') {
            show_error('Metode request tidak diizinkan.', 405, 'Method Not Allowed');
            return;
        }

        if ($this->session->userdata('auth_user')) {
            $perms = (array)$this->session->userdata('user_perms');
            $target = $this->resolve_post_login_redirect($perms);
            if ($target === '') {
                $this->session->sess_destroy();
                $this->session->set_flashdata('login_error', 'Akun belum memiliki akses halaman. Hubungi admin.');
                redirect('login');
            }
            redirect($target);
        }

        // Validasi input
        $this->form_validation->set_rules('identifier', 'Username/Email', 'required|trim|min_length[3]|max_length[150]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]|max_length[72]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('login_error', self::LOGIN_FAILURE_MESSAGE);
            redirect('login');
            return;
        }

        $identifier = (string)$this->input->post('identifier', true);
        $password   = (string)$this->input->post('password');   // tidak di-XSS untuk password

        try {
            // Parameter IP ketiga mengaktifkan throttle khusus login web.
            $user = $this->Auth_model->attempt_login(
                $identifier,
                $password,
                (string)$this->input->ip_address()
            );
        } catch (Throwable $e) {
            log_message('error', 'Web login unavailable because an authentication persistence operation failed.');
            $this->session->set_flashdata('login_error', self::LOGIN_MAINTENANCE_MESSAGE);
            redirect('login');
            return;
        }

        if (!$user) {
            $this->session->set_flashdata('login_error', self::LOGIN_FAILURE_MESSAGE);
            redirect('login');
            return;
        }

        // Load permissions tanpa membuat session authenticated. Advisory lock
        // login web tetap ditahan sampai audit login sukses tersimpan.
        try {
            $perms = $this->Auth_model->load_permissions($user['id']);
            $is_superadmin = isset($perms['__superadmin__']);
        } catch (Throwable $e) {
            $this->cancel_pending_web_login();
            log_message('error', 'Web login finalization failed before permission resolution completed.');
            $this->session->set_flashdata('login_error', self::LOGIN_MAINTENANCE_MESSAGE);
            redirect('login');
            return;
        }

        // Resolusi scope harus selesai sebelum session authenticated dibuat.
        try {
            $divisionScope = $this->Auth_model->resolve_division_scope((int)$user['id']);
        } catch (Throwable $e) {
            $released = $this->cancel_pending_web_login();
            $this->session->set_flashdata(
                'login_error',
                $released ? self::INVALID_SCOPE_MESSAGE : self::LOGIN_MAINTENANCE_MESSAGE
            );
            redirect('login');
            return;
        }

        $hasUsableScope = $divisionScope['state'] === 'GLOBAL'
            || ($divisionScope['state'] === 'SINGLE' && (int)($divisionScope['division_id'] ?? 0) > 0);
        if (!$is_superadmin && !$hasUsableScope) {
            $released = $this->cancel_pending_web_login();
            $this->session->set_flashdata(
                'login_error',
                $released ? self::INVALID_SCOPE_MESSAGE : self::LOGIN_MAINTENANCE_MESSAGE
            );
            redirect('login');
            return;
        }

        // Audit login harus berhasil sebelum session authenticated dibuat.
        try {
            $log_id = $this->Auth_model->log_login(
                $user['id'],
                $this->input->ip_address(),
                (string)$this->input->user_agent()
            );
        } catch (Throwable $e) {
            $this->cancel_pending_web_login();
            log_message('error', 'Web login finalization failed while writing the session audit.');
            $this->session->set_flashdata('login_error', self::LOGIN_MAINTENANCE_MESSAGE);
            redirect('login');
            return;
        }

        // Invalidate the anonymous/pre-authentication ID before attaching the
        // authenticated identity to the session.
        $this->session->sess_regenerate(TRUE);

        // Simpan ke session hanya setelah auth_session_log berhasil.
        $this->session->set_userdata([
            'auth_user'            => array_merge($user, ['is_superadmin' => $is_superadmin]),
            'user_perms'           => $perms,
            'user_division_scope_state' => $divisionScope['state'],
            'user_division_scope'  => $divisionScope['state'] === 'SINGLE' ? $divisionScope['division_id'] : null,
            'user_perms_cached_at' => time(),
            'session_log_id'       => $log_id,
        ]);

        // Redirect ke halaman sebelumnya jika ada, fallback ke halaman pertama yang boleh diakses
        $redirect_to = $this->session->flashdata('redirect_after_login');
        if (defined('FINANCE_FEATURE_CONTEXT') && !empty(FINANCE_FEATURE_CONTEXT['managed']) && $redirect_to === null) {
            $redirect_to = '';
        }
        if ($redirect_to === '') {
            $redirect_to = $this->resolve_post_login_redirect($perms);
        }
        if ($redirect_to === '') {
            $this->Auth_model->log_logout($log_id);
            $this->session->sess_destroy();
            $this->session->set_flashdata('login_error', 'Akun berhasil diverifikasi, tetapi belum memiliki akses halaman. Hubungi admin.');
            redirect('login');
        }
        redirect($redirect_to);
    }

    // ---------------------------------------------------------------
    // LOGOUT
    // ---------------------------------------------------------------

    public function logout()
    {
        $log_id = (int) $this->session->userdata('session_log_id');
        $this->Auth_model->log_logout($log_id);
        $this->session->sess_destroy();
        redirect('login');
    }

    private function resolve_post_login_redirect(array $perms): string
    {
        if (isset($perms['__superadmin__'])) {
            return 'dashboard';
        }

        $candidates = [
            'dashboard.index' => 'dashboard',
            'my.home.index' => 'my',
            'my.attendance.index' => 'my/attendance',
            'my.profile.index' => 'my/profile',
            'purchase.order.index' => 'purchase-orders',
            'payroll.cash_advance.index' => 'payroll/cash-advances',
        ];

        $productPolicy = null;
        if (defined('FINANCE_FEATURE_CONTEXT') && !empty(FINANCE_FEATURE_CONTEXT['managed'])) {
            require_once APPPATH.'libraries/Feature_policy.php';
            $productPolicy = Feature_policy::runtime();
            // A customer cashier must not need a paid HR portal just to log in.
            $candidates = ['dashboard.index'=>'dashboard', 'pos.cashier.index'=>'pos/cashier',
                'pos.order.draft.index'=>'pos/orders/draft'] + $candidates;
        }

        foreach ($candidates as $pageCode => $url) {
            if (!empty($perms[$pageCode]['can_view'])) {
                if ($productPolicy !== null && !$productPolicy->menu($url, $this->router->routes)['allowed']) continue;
                return $url;
            }
        }

        if ($productPolicy !== null) foreach ($perms as $permission) {
            if (is_array($permission) && !empty($permission['can_view'])) return 'license';
        }

        return '';
    }

    private function cancel_pending_web_login(): bool
    {
        // method_exists menjaga compatibility test-double/legacy caller;
        // model runtime selalu menyediakan cancel_web_login().
        if (!method_exists($this->Auth_model, 'cancel_web_login')) {
            return true;
        }

        try {
            $this->Auth_model->cancel_web_login();
            return true;
        } catch (Throwable $e) {
            log_message('error', 'Web login advisory lock cleanup failed.');
            return false;
        }
    }
}
