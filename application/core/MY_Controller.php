<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Base controller untuk semua halaman yang butuh login
 *
 * Semua controller kecuali Auth extend class ini.
 * Menangani: cek login, load izin ke session, helper cek izin per aksi.
 */
class MY_Controller extends CI_Controller
{
    private const SIDEBAR_CACHE_TTL_SECONDS = 300;
    private const SIDEBAR_CACHE_DIR = 'sidebar';
    protected const SIDEBAR_FAVORITE_CSRF_SESSION_KEY = 'sidebar_favorite_csrf';
    protected const SIDEBAR_FAVORITE_CSRF_HEADER = 'X-Sidebar-Favorite-CSRF';
    protected const SIDEBAR_FAVORITE_CSRF_CI_HEADER = 'X-Sidebar-Favorite-Csrf';
    private const INVALID_SCOPE_MESSAGE = 'Konfigurasi akses akun belum lengkap. Hubungi administrator.';

    /** Data user yang sedang login (dari session) */
    protected $current_user = [];

    /** Cache izin user: ['page_code' => ['view'=>1, 'create'=>0, ...]] */
    protected $user_perms = [];

    /**
     * Satu kali pemulihan izin per request bila session tampak tertinggal.
     * Ini penting setelah role/override diubah saat user masih login.
     */
    private $permission_recovery_attempted = false;

    /** @var array<string,string>|null Alias aktif ke page_code kanonis, dimuat sekali per request. */
    private $page_permission_aliases = null;

    /** Menutup request bila pemulihan permission gagal, termasuk sesi superadmin. */
    private $permission_refresh_failed = false;

    /** Mencegah satu halaman layout tercatat lebih dari sekali per request. */
    private $access_event_recorded = false;

    /** Mencegah lebih dari satu pemulihan scope sesi lama dalam satu request. */
    private $division_scope_recovery_attempted = false;

    /** Profil usaha dibaca sekali per request untuk shell dan dokumen aplikasi. */
    private $business_profile_for_request = null;

    public function __construct()
    {
        parent::__construct();
        $this->_check_auth();
    }

    // ---------------------------------------------------------------
    // AUTH CHECK
    // ---------------------------------------------------------------

    private function _check_auth() 
    { 
        $user = $this->session->userdata('auth_user'); 
 
        if (empty($user)) { 
            $class  = strtolower((string)$this->router->fetch_class());
            $method = strtolower((string)$this->router->fetch_method());
            if ($this->input->is_cli_request() || $this->_allows_anonymous_http_request($class, $method)) {
                $this->current_user = [];
                $this->user_perms = [];
                return;
            }

            if ($this->input->is_ajax_request()) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                $this->output
                    ->set_status_header(401)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'ok' => false,
                        'message' => 'Sesi login sudah habis. Silakan login ulang.',
                    ], JSON_INVALID_UTF8_SUBSTITUTE));
                $this->output->_display();
                exit;
            }
            $this->session->set_flashdata('redirect_after_login', uri_string()); 
            redirect('login'); 
        } 

        // File session dapat bertahan setelah database dipulihkan/disalin.
        // Periksa sebelum permission maupun aksi controller turunan berjalan.
        $this->_assert_web_session_context($user);
        $this->current_user = $user;

        // Muat izin dari session (sudah di-cache saat login)
        $this->user_perms = $this->session->userdata('user_perms') ?? [];

        // Auto-refresh jika permission role telah diubah sejak cache terakhir
        $this->_maybe_refresh_stale_perms();

        // Semua non-superadmin harus memiliki scope operasional tunggal atau
        // role global yang sah sebelum controller turunan bekerja.
        if (!$this->_has_valid_division_scope()) {
            $this->_deny_invalid_division_scope();
            return;
        }
    }

    private function _allows_anonymous_http_request(string $class, string $method): bool
    {
        return $class === 'whatsapp'
            && $method === 'api_group_command'
            && $this->input->method(true) === 'POST';
    }

    private function _assert_web_session_context($user): void
    {
        if ($this->input->is_cli_request()) {
            return;
        }

        $userId = is_array($user) ? (int)($user['id'] ?? 0) : 0;
        $sessionLogId = (int)$this->session->userdata('session_log_id');
        if ($userId <= 0 || $sessionLogId <= 0) {
            $this->_reject_web_session(false);
        }

        $valid = false;
        $unavailable = false;
        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->db
                ->select('s.id')
                ->from('auth_session_log s')
                ->join('auth_user u', 'u.id = s.user_id', 'inner')
                ->where('s.id', $sessionLogId)
                ->where('s.user_id', $userId)
                ->where('s.logout_at', null)
                ->where('u.is_active', 1)
                ->limit(1)
                ->get();
            $unavailable = $result === false;
            $valid = !$unavailable && $result->num_rows() > 0;
        } catch (Throwable $e) {
            $unavailable = true;
        } finally {
            $this->db->db_debug = $previousDebug;
        }

        // Kegagalan query bukan bukti sesi kedaluwarsa. Tutup request tanpa
        // menghapus sesi atau membocorkan SQL; pengguna dapat mencoba kembali.
        if ($unavailable) {
            log_message('error', 'Authenticated session validation temporarily unavailable.');
            $this->_reject_web_session(true);
        }
        if (!$valid) {
            $this->_reject_web_session(false);
        }
    }

    private function _reject_web_session(bool $unavailable): void
    {
        $message = $unavailable
            ? 'Sesi login belum dapat diperiksa. Silakan coba lagi beberapa saat.'
            : 'Sesi login sudah tidak berlaku. Silakan login ulang.';
        $loginPath = 'login?reason=session_expired';
        if (!$unavailable) {
            // Hanya sesi request ini, bukan seluruh sesi pengguna lainnya.
            $this->session->sess_destroy();
            $_SESSION = [];
            $this->current_user = [];
            $this->user_perms = [];
        }

        $accept = (string)$this->input->get_request_header('Accept', false);
        if ($this->input->is_ajax_request() || stripos($accept, 'application/json') !== false) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $payload = [
                'ok' => false,
                'code' => $unavailable ? 'AUTH_SESSION_VALIDATION_UNAVAILABLE' : 'AUTH_SESSION_EXPIRED',
                'message' => $message,
            ];
            if (!$unavailable) {
                $payload['login_url'] = site_url($loginPath);
            }
            $this->output
                ->set_status_header($unavailable ? 503 : 401)
                ->set_content_type('application/json')
                ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
            $this->output->_display();
            exit;
        }
        if ($unavailable) {
            show_error($message, 503, 'Layanan sementara tidak tersedia');
            exit;
        }

        // Jangan meneruskan POST transaksi lama ke halaman login.
        redirect($loginPath, 'location', 303);
        exit;
    }

    private function _maybe_refresh_stale_perms(): void
    {
        $userId = (int)($this->current_user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        // Throttle: cek staleness maksimal sekali per 120 detik per sesi
        $lastCheck = (int)($this->session->userdata('perms_staleness_checked_at') ?? 0);
        if (time() - $lastCheck < 120) {
            return;
        }
        $this->session->set_userdata('perms_staleness_checked_at', time());

        $cachedAt = (int)($this->session->userdata('user_perms_cached_at') ?? 0);

        // Jika belum ada timestamp (session lama / baru deploy), refresh sekali
        if ($cachedAt <= 0) {
            $this->_refresh_authenticated_permissions($userId);
            return;
        }

        $cachedAtStr = date('Y-m-d H:i:s', $cachedAt);

        // Cek 1: role assignment atau override user ini berubah
        $isStale = (bool)$this->db
            ->select('1')
            ->from('auth_user')
            ->where('id', $userId)
            ->where('permissions_updated_at >', $cachedAtStr)
            ->limit(1)
            ->get()
            ->num_rows();

        // Cek 2: permission matrix atau konfigurasi role salah satu role user berubah.
        // Kedua kolom ini sudah ada pada auth_role; tidak ada perubahan schema di batch ini.
        if (!$isStale) {
            $isStale = (bool)$this->db
                ->select('1')
                ->from('auth_user_role ur')
                ->join('auth_role r', 'r.id = ur.role_id')
                ->where('ur.user_id', $userId)
                ->group_start()
                ->where('r.permissions_updated_at >', $cachedAtStr)
                ->or_where('r.updated_at >', $cachedAtStr)
                ->group_end()
                ->limit(1)
                ->get()
                ->num_rows();
        }

        if ($isStale) {
            $this->_refresh_authenticated_permissions($userId);
        }
    }

    private function _refresh_authenticated_permissions(int $userId): void
    {
        $this->division_scope_recovery_attempted = true;
        try {
            $this->load->model('Auth_model');
            $this->Auth_model->refresh_permissions($userId);

            // Refresh juga dapat mencabut SUPERADMIN. Jangan mempertahankan
            // flag lama hanya di property controller.
            $this->current_user = $this->session->userdata('auth_user') ?: [];
            $this->user_perms = $this->session->userdata('user_perms') ?? [];
        } catch (Throwable $e) {
            $this->permission_refresh_failed = true;
            log_message('error', 'Authenticated permission refresh failed.');
        }
    }

    private function _has_valid_division_scope(): bool
    {
        if ($this->permission_refresh_failed || $this->is_superadmin()) {
            return !$this->permission_refresh_failed;
        }

        $scopeState = $this->session->userdata('user_division_scope_state');
        if ($scopeState === 'SINGLE' && (int)$this->session->userdata('user_division_scope') > 0) {
            return true;
        }

        if ($scopeState === 'GLOBAL') {
            return true;
        }

        // Session sebelum P0-04A tidak punya state. Refresh satu kali dari DB;
        // hasil selain SINGLE tetap ditolak.
        if ($scopeState === null && !$this->division_scope_recovery_attempted) {
            $userId = (int)($this->current_user['id'] ?? 0);
            if ($userId > 0) {
                $this->_refresh_authenticated_permissions($userId);
                if ($this->permission_refresh_failed) {
                    return false;
                }
                if ($this->is_superadmin()) {
                    return true;
                }

                $scopeState = $this->session->userdata('user_division_scope_state');
            }
        }

        return $scopeState === 'GLOBAL'
            || ($scopeState === 'SINGLE' && (int)$this->session->userdata('user_division_scope') > 0);
    }

    private function _deny_invalid_division_scope(): void
    {
        if ($this->input->is_ajax_request()) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok' => false,
                    'message' => self::INVALID_SCOPE_MESSAGE,
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            $this->output->_display();
            exit;
        }

        show_error(self::INVALID_SCOPE_MESSAGE, 403, 'Akses Ditolak');
    }

    // ---------------------------------------------------------------
    // PERMISSION HELPERS
    // ---------------------------------------------------------------

    /**
     * Apakah user superadmin? (bypass semua cek)
     */
    protected function is_superadmin(): bool
    {
        return !empty($this->current_user['is_superadmin']);
    }

    /**
     * Cek izin user untuk halaman + aksi tertentu.
     *
     * @param string $page_code  Contoh: 'auth.users.index'
     * @param string $action     Salah satu: view|create|edit|delete|export
     * @return bool
     */
    protected function can(string $page_code, string $action = 'view'): bool
    {
        if ($this->is_superadmin()) {
            return true;
        }

        $permissionKey = 'can_' . $action;
        $resolvedPageCode = $this->_resolve_page_permission_alias($page_code);
        $permissionPageCode = $resolvedPageCode ?? $page_code;

        if (!empty($this->user_perms[$permissionPageCode][$permissionKey])) {
            return true;
        }

        // Session lama kadang masih membawa izin sebelum role/override diperbarui.
        // Refresh sekali saat izin yang seharusnya ada tidak terbaca, lalu cek ulang.
        $userId = (int)($this->current_user['id'] ?? 0);
        if ($this->permission_recovery_attempted || $userId <= 0) {
            return false;
        }

        $this->permission_recovery_attempted = true;
        try {
            $this->_refresh_authenticated_permissions($userId);
            if ($this->permission_refresh_failed) {
                return false;
            }
            if ($this->is_superadmin()) {
                return true;
            }
        } catch (Throwable $e) {
            log_message('error', 'Permission refresh recovery failed for user ' . $userId . ': ' . $e->getMessage());
            return false;
        }

        return !empty($this->user_perms[$permissionPageCode][$permissionKey]);
    }

    /**
     * Resolve hanya alias yang terdaftar aktif menuju page kanonis aktif.
     * Map kosong juga di-cache agar schema lama/error DB tetap fail closed.
     */
    private function _resolve_page_permission_alias(string $pageCode): ?string
    {
        if ($this->page_permission_aliases === null) {
            $this->page_permission_aliases = [];

            try {
                if ($this->db->table_exists('sys_page_alias')) {
                    $rows = $this->db
                        ->select('alias.alias_code, page.page_code AS canonical_page_code')
                        ->from('sys_page_alias alias')
                        ->join('sys_page page', 'page.id = alias.page_id')
                        ->where('alias.is_active', 1)
                        ->where('page.is_active', 1)
                        ->get()
                        ->result_array();

                    foreach ($rows as $row) {
                        $aliasCode = (string)($row['alias_code'] ?? '');
                        $canonicalPageCode = (string)($row['canonical_page_code'] ?? '');
                        if ($aliasCode !== '' && $canonicalPageCode !== '') {
                            $this->page_permission_aliases[$aliasCode] = $canonicalPageCode;
                        }
                    }
                }
            } catch (Throwable $e) {
                log_message('error', 'Page permission alias registry could not be loaded.');
            }
        }

        return $this->page_permission_aliases[$pageCode] ?? null;
    }

    /**
     * Paksa cek izin — redirect ke 403 jika tidak punya akses.
     */
    protected function require_permission(string $page_code, string $action = 'view'): void 
    { 
        if (!$this->can($page_code, $action)) { 
            if ($this->input->is_ajax_request()) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
                $this->output
                    ->set_status_header(403)
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'ok' => false,
                        'message' => 'Anda tidak memiliki izin untuk aksi ini.',
                        'page_code' => $page_code,
                        'action' => $action,
                    ], JSON_INVALID_UTF8_SUBSTITUTE));
                $this->output->_display();
                exit;
            }
            show_error('Anda tidak memiliki izin untuk mengakses halaman ini.', 403, 'Akses Ditolak'); 
        } 
    } 

    // ---------------------------------------------------------------
    // DIVISION SCOPE HELPER
    // ---------------------------------------------------------------

    /**
     * Kembalikan division_id yang berlaku untuk user yang sedang login.
     * - Superadmin → null (lihat semua divisi)
     * - User dengan role ber-scope → ID divisi tersebut
     * - Non-superadmin tanpa scope SINGLE → request sudah ditolak di entry.
     *
     * Cara pakai di controller:
     *   $divId = $this->active_division_id();
     *   if ($divId) $this->db->where('division_id', $divId);
     */
    protected function active_division_id(): ?int
    {
        if ($this->is_superadmin()) {
            return null;
        }

        $scopeState = $this->session->userdata('user_division_scope_state');
        $scope = $this->session->userdata('user_division_scope');
        if ($scopeState === 'SINGLE' && (int)$scope > 0) {
            return (int)$scope;
        }

        if ($scopeState === 'GLOBAL') {
            return null;
        }

        // Fail closed if called outside the normal constructor gate. Returning
        // NULL for an unresolved state would be interpreted as unrestricted by
        // legacy callers.
        throw new RuntimeException('Division scope is not resolved.');
    }

    // ---------------------------------------------------------------
    // VIEW LOADER HELPER
    // ---------------------------------------------------------------

    /**
     * Load view dengan layout (header + sidebar + content + footer).
     * Sidebar data (main tree, my tree, favorites) di-load otomatis di sini.
     *
     * @param string $view       Path view relatif dari application/views/
     * @param array  $data       Data yang dikirim ke view
     * @param bool   $return     Jika TRUE, kembalikan string bukan output langsung
     */
    protected function render(string $view, array $data = [], bool $return = false)
    {
        // Normalisasi judul tab browser:
        // beberapa halaman kirim `page_title`, sedangkan layout header memakai `title`.
        if (
            (!isset($data['title']) || trim((string)$data['title']) === '')
            && isset($data['page_title'])
            && trim((string)$data['page_title']) !== ''
        ) {
            $data['title'] = (string)$data['page_title'];
        }

        $data['current_user'] = $this->current_user;
        $data['user_perms']   = $this->user_perms;
        $data['sidebar_favorite_csrf_token'] = $this->sidebar_favorite_csrf();
        // Identitas customer bersifat data lokal. Controller/detail page tetap
        // dapat mengirim override eksplisit bila memang memiliki snapshot sendiri.
        if (!isset($data['business_profile'])) {
            $data['business_profile'] = $this->business_profile();
        }

        // Load sidebar data otomatis (kecuali sudah diset manual oleh controller)
        if (!isset($data['sidebar_main'])) {
            $sidebar = $this->load_sidebar_cached();
            $data['sidebar_main'] = $sidebar['sidebar_main'];
            $data['sidebar_my'] = $sidebar['sidebar_my'];
            $data['sidebar_favorites'] = $sidebar['sidebar_favorites'];
        }

        $data['content_view'] = $view;
        $data['content_data'] = $data;

        $this->record_page_access((string)($data['active_menu'] ?? ''));

        return $this->load->view('layout/main', $data, $return);
    }

    /**
     * Sumber identitas customer yang aman untuk UI. Kegagalan atau schema lama
     * tidak boleh menggagalkan halaman operasional; fallback tetap netral.
     */
    protected function business_profile(): array
    {
        if (is_array($this->business_profile_for_request)) {
            return $this->business_profile_for_request;
        }

        $fallback = [
            'display_name' => 'Finance',
            'short_name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website_url' => '',
            'logo_url' => '',
            'document_footer' => '',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id_ID',
            'currency_code' => 'IDR',
        ];

        try {
            $this->load->model('Business_profile_model');
            $profile = $this->Business_profile_model->profile();
            $this->business_profile_for_request = array_merge($fallback, is_array($profile) ? $profile : []);
        } catch (Throwable $error) {
            log_message('error', 'Business profile read failed; neutral application identity is used.');
            $this->business_profile_for_request = $fallback;
        }

        return $this->business_profile_for_request;
    }

    /**
     * Rekam satu akses halaman HTML yang sudah lolos autentikasi/permission.
     * Tidak merekam query string, request body, password, token, atau endpoint
     * AJAX/API. Kegagalan audit tidak boleh membuat halaman operasional mati
     * ketika migration belum diterapkan.
     */
    private function record_page_access(string $pageCode): void
    {
        if ($this->access_event_recorded || $this->input->is_cli_request()
            || strtoupper((string)$this->input->method(true)) !== 'GET'
            || $this->input->is_ajax_request()) {
            return;
        }
        $userId = (int)($this->current_user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }
        $this->access_event_recorded = true;

        $previousDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $foreignKeyFailure = false;
        try {
            if (!$this->db->table_exists('aud_access_event')) {
                return;
            }
            $path = trim((string)uri_string(), '/');
            if ($path === '') {
                $path = '/';
            }
            $normalizedPageCode = preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/i', $pageCode) === 1
                ? $pageCode
                : null;
            $userAgent = mb_substr(trim((string)$this->input->user_agent()), 0, 255);
            $recorded = $this->db->insert('aud_access_event', [
                'user_id' => $userId,
                'session_log_id' => max(0, (int)$this->session->userdata('session_log_id')) ?: null,
                'page_code' => $normalizedPageCode,
                'route_path' => mb_substr($path, 0, 255),
                'request_method' => 'GET',
                'ip_address' => mb_substr((string)$this->input->ip_address(), 0, 45),
                'user_agent' => $userAgent !== '' ? $userAgent : null,
                'device_label' => $this->access_device_label($userAgent),
            ]);
            if ($recorded === false) {
                $error = $this->db->error();
                $foreignKeyFailure = (int)($error['code'] ?? 0) === 1452;
                log_message('error', 'Authenticated page access audit could not be recorded.');
            }
        } catch (Throwable $e) {
            $foreignKeyFailure = (int)$e->getCode() === 1452;
            log_message('error', 'Authenticated page access audit could not be recorded.');
        } finally {
            $this->db->db_debug = $previousDebug;
        }

        // Parent bisa hilang di antara cek awal dan INSERT (misalnya restore).
        // Validasi ulang, bukan menghapus FK atau membuat session log palsu.
        if ($foreignKeyFailure) {
            $this->_assert_web_session_context($this->current_user);
        }
    }

    private function access_device_label(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }
        $platform = 'Perangkat lain';
        foreach (['Android' => '/android/i', 'iOS' => '/iphone|ipad|ipod/i', 'Windows' => '/windows/i', 'macOS' => '/macintosh|mac os/i', 'Linux' => '/linux/i'] as $label => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $platform = $label;
                break;
            }
        }
        $browser = 'Browser lain';
        foreach (['Edge' => '/edg\//i', 'Chrome' => '/chrome|crios/i', 'Firefox' => '/firefox|fxios/i', 'Safari' => '/safari/i'] as $label => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $browser = $label;
                break;
            }
        }

        return mb_substr($platform . ' / ' . $browser, 0, 80);
    }

    protected function sidebar_favorite_csrf(): string
    {
        $token = (string)$this->session->userdata(self::SIDEBAR_FAVORITE_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::SIDEBAR_FAVORITE_CSRF_SESSION_KEY, $token);
        }

        return $token;
    }

    protected function render_cashier(string $view, array $data = [], bool $return = false)
    {
        if (
            (!isset($data['title']) || trim((string)$data['title']) === '')
            && isset($data['page_title'])
            && trim((string)$data['page_title']) !== ''
        ) {
            $data['title'] = (string)$data['page_title'];
        }

        $data['current_user'] = $this->current_user;
        $data['user_perms'] = $this->user_perms;
        $data['content_view'] = $view;
        $data['content_data'] = $data;

        $this->record_page_access((string)($data['active_menu'] ?? ''));

        return $this->load->view('layout/cashier', $data, $return);
    }

    private function load_sidebar_cached(): array
    {
        $userId = (int)($this->current_user['id'] ?? 0);
        $isSuperadmin = $this->is_superadmin();
        $cacheSignature = $this->get_sidebar_cache_signature($userId, $isSuperadmin);
        $cacheFile = $this->get_sidebar_cache_file($userId, $isSuperadmin, $cacheSignature);
        $cachedSidebar = $this->read_sidebar_cache($cacheFile);
        if ($cachedSidebar !== null) {
            return $cachedSidebar;
        }

        $this->load->model('Menu_model');
        $sidebarData = [
            'sidebar_main' => $this->Menu_model->get_sidebar_tree($this->user_perms, $isSuperadmin, 'MAIN'),
            'sidebar_my' => $this->Menu_model->get_sidebar_tree($this->user_perms, $isSuperadmin, 'MY'),
            'sidebar_favorites' => $this->Menu_model->get_favorites($userId, $this->user_perms, $isSuperadmin),
        ];

        $this->write_sidebar_cache($cacheFile, $sidebarData);

        return $sidebarData;
    }

    private function get_sidebar_cache_signature(int $userId, bool $isSuperadmin): string
    {
        $permSignature = md5(json_encode($this->user_perms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $menuVersion = 'menu:none';
        if ($this->db->table_exists('sys_menu')) {
            $menuRow = $this->db
                ->select('COUNT(*) AS total_rows, MAX(COALESCE(updated_at, created_at)) AS latest_change', false)
                ->from('sys_menu')
                ->get()
                ->row_array() ?: [];
            $menuVersion = 'menu:'
                . (int)($menuRow['total_rows'] ?? 0)
                . ':'
                . (string)($menuRow['latest_change'] ?? '');
        }

        $favoriteVersion = 'fav:none';
        if ($userId > 0 && $this->db->table_exists('sys_sidebar_favorite')) {
            $favoriteRow = $this->db
                ->select("COUNT(*) AS total_rows, MAX(created_at) AS latest_change, GROUP_CONCAT(CONCAT(menu_id, ':', sort_order) ORDER BY menu_id SEPARATOR ',') AS order_fingerprint", false)
                ->from('sys_sidebar_favorite')
                ->where('user_id', $userId)
                ->get()
                ->row_array() ?: [];
            $favoriteVersion = 'fav:'
                . (int)($favoriteRow['total_rows'] ?? 0)
                . ':'
                . (string)($favoriteRow['latest_change'] ?? '')
                . ':'
                . (string)($favoriteRow['order_fingerprint'] ?? '');
        }

        return md5(implode('|', [
            $permSignature,
            $isSuperadmin ? 'super:1' : 'super:0',
            $menuVersion,
            $favoriteVersion,
        ]));
    }

    private function get_sidebar_cache_file(int $userId, bool $isSuperadmin, string $permSignature): ?string
    {
        if ($userId <= 0 || $permSignature === '') {
            return null;
        }

        $cacheDir = APPPATH . 'cache/' . self::SIDEBAR_CACHE_DIR;
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            return null;
        }

        $this->cleanup_sidebar_cache_dir($cacheDir);

        return $cacheDir
            . DIRECTORY_SEPARATOR
            . 'sidebar_'
            . $userId
            . '_'
            . ($isSuperadmin ? '1' : '0')
            . '_'
            . $permSignature
            . '.json';
    }

    private function read_sidebar_cache(?string $cacheFile): ?array
    {
        if ($cacheFile === null || !is_file($cacheFile)) {
            return null;
        }

        $modifiedAt = @filemtime($cacheFile);
        if (!$modifiedAt || $modifiedAt <= (time() - self::SIDEBAR_CACHE_TTL_SECONDS)) {
            @unlink($cacheFile);
            return null;
        }

        $payload = @file_get_contents($cacheFile);
        if ($payload === false || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (
            !is_array($decoded)
            || !isset($decoded['sidebar_main'], $decoded['sidebar_my'], $decoded['sidebar_favorites'])
        ) {
            return null;
        }

        return [
            'sidebar_main' => (array)$decoded['sidebar_main'],
            'sidebar_my' => (array)$decoded['sidebar_my'],
            'sidebar_favorites' => (array)$decoded['sidebar_favorites'],
        ];
    }

    private function write_sidebar_cache(?string $cacheFile, array $sidebarData): void
    {
        if ($cacheFile === null) {
            return;
        }

        $payload = json_encode($sidebarData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        @file_put_contents($cacheFile, $payload, LOCK_EX);
    }

    private function cleanup_sidebar_cache_dir(string $cacheDir): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'sidebar_*.json') ?: [] as $cacheFile) {
            $modifiedAt = @filemtime($cacheFile);
            if ($modifiedAt && $modifiedAt <= (time() - self::SIDEBAR_CACHE_TTL_SECONDS)) {
                @unlink($cacheFile);
            }
        }
    }
}
