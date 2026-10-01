<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_mobile extends CI_Controller
{
    private const MOBILE_REVERSAL_STEP_UP_TTL_SECONDS = 180;
    private const MOBILE_REVERSAL_STEP_UP_FAILURE_WINDOW_SECONDS = 600;
    private const MOBILE_REVERSAL_STEP_UP_FAILURE_LIMIT = 5;

    private $mobileUser = null;
    private $mobilePermissions = null;
    private $mobilePermissionUserId = 0;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Auth_model');
        $this->load->model('Pos_model');
        $this->load->model('Pos_print_model');
    }

    public function ping(): void
    {
        $this->json_ok([
            'server_time' => date('c'),
            'service' => 'finance-pos-mobile',
        ]);
    }

    public function login(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->db->table_exists('pos_mobile_auth_token') || !$this->db->table_exists('pos_terminal')) {
            $this->json_error('Schema token mobile atau registry terminal belum siap.', 503);
            return;
        }

        $payload = $this->request_payload();
        $identifier = trim((string)($payload['identifier'] ?? $payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $deviceKey = trim((string)($payload['terminal_device_key'] ?? ''));
        if ($identifier === '' || $password === '' || $deviceKey === '') {
            $this->json_error('Username/email, password, dan terminal device key wajib diisi.', 422);
            return;
        }

        // Samakan boundary login APK dengan login web: Auth_model hanya
        // mengaktifkan throttle per akun/IP saat konteks IP diberikan.
        // Respons tetap generik agar username, terminal, maupun permission
        // tidak dapat dipakai untuk enumerasi akun dari perangkat mobile.
        $user = $this->Auth_model->attempt_login(
            $identifier,
            $password,
            (string)$this->input->ip_address()
        );
        if (!$user) {
            $this->json_error('Kredensial atau perangkat tidak valid.', 401);
            return;
        }

        $terminal = $this->unique_active_mobile_terminal($deviceKey);
        if (!$terminal) {
            $this->json_error('Kredensial atau perangkat tidak valid.', 401);
            return;
        }

        $perms = $this->Auth_model->load_permissions((int)$user['id']);
        $isSuperadmin = isset($perms['__superadmin__']);
        $canCashier = $isSuperadmin
            || !empty($perms['pos.cashier.index']['can_view'])
            || !empty($perms['pos.order.draft.index']['can_view']);
        if (!$canCashier) {
            $this->json_error('Akun belum memiliki akses POS kasir.', 403);
            return;
        }

        $divisionScope = $this->mobile_division_scope_context((int)$user['id'], $perms);
        if ($divisionScope === null) {
            return;
        }

        $employeeId = max(0, (int)($user['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            $this->json_error('Akun belum terhubung ke employee. Hubungkan user ke data employee dulu.', 422);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->db->insert('pos_mobile_auth_token', [
            'token_hash' => hash('sha256', $token),
            'user_id' => (int)$user['id'],
            'employee_id' => $employeeId,
            'terminal_device_key' => $deviceKey,
            'device_label' => trim((string)($payload['device_label'] ?? 'Android POS')),
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'last_seen_at' => $now,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr((string)$this->input->user_agent(), 0, 255),
        ]);

        $this->json_ok([
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int)$user['id'],
                'employee_id' => $employeeId,
                'username' => (string)($user['username'] ?? ''),
                'email' => (string)($user['email'] ?? ''),
                'is_superadmin' => $isSuperadmin,
            ],
            'access_context' => [
                'division_scope_state' => $divisionScope['state'],
                'division_id' => $divisionScope['division_id'],
                'outlet_id' => (int)$terminal['outlet_id'],
                'terminal_id' => (int)$terminal['id'],
            ],
        ]);
    }

    public function logout(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $token = $this->bearer_token();
        if ($token !== '' && $this->db->table_exists('pos_mobile_auth_token')) {
            $this->db
                ->where('token_hash', hash('sha256', $token))
                ->where('revoked_at IS NULL', null, false)
                ->update('pos_mobile_auth_token', [
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
        $this->json_ok(['logged_out' => true]);
    }

    public function bootstrap(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        if (is_array($this->mobileUser)) {
            $binding = $this->mobile_reader_binding_context();
            if ($binding === null) {
                return;
            }

            $employeeId = $this->current_actor_employee_id();
            $sessionContext = $this->mobile_reader_session_context(
                $employeeId,
                $binding['outlet_id'],
                $binding['terminal_id']
            );
            if (!$sessionContext['ok']) {
                return;
            }
            $activeSession = $sessionContext['session'];
            $activeSessions = $activeSession === null ? [] : [$activeSession];

            $cashierBootstrap = $this->Pos_model->cashier_bootstrap_options($employeeId);
            $cashierBootstrap = $this->scope_mobile_reader_option_lists(
                $cashierBootstrap,
                $binding['outlet_id'],
                $binding['terminal_id']
            );
            $cashierBootstrap['default_outlet_id'] = $binding['outlet_id'];
            $cashierBootstrap['default_terminal_id'] = $binding['terminal_id'];
            $cashierBootstrap['active_session'] = $activeSession;
            $cashierBootstrap['active_sessions'] = $activeSessions;

            $filterOptions = $this->scope_mobile_reader_option_lists(
                $this->Pos_model->order_draft_filter_options(),
                $binding['outlet_id'],
                $binding['terminal_id']
            );
            $products = $this->Pos_model->order_product_catalog([
                'outlet_id' => $binding['outlet_id'],
                'limit' => 120,
            ]);
            $bundles = $this->Pos_model->order_bundle_catalog([
                'outlet_id' => $binding['outlet_id'],
                'limit' => 60,
            ]);

            $this->json_ok([
                'sync_cursor' => date('c'),
                'server_time' => date('c'),
                'access_context' => $this->mobile_access_context_payload(),
                'sensitive_action_contract' => $this->mobile_sensitive_action_contract(),
                'cashier_bootstrap' => $cashierBootstrap,
                'active_sessions' => $activeSessions,
                'filter_options' => $filterOptions,
                'catalog_filters' => $this->Pos_model->cashier_catalog_filter_options(),
                'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
                'products' => $products,
                'bundles' => $bundles,
                'deleted' => [
                    'products' => [],
                    'bundles' => [],
                    'payment_methods' => [],
                    'printers' => [],
                ],
            ]);
            return;
        }

        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        if ($outletId <= 0) {
            $outletId = max(0, (int)$this->input->get('default_outlet_id', true));
        }

        $employeeId = $this->current_actor_employee_id();
        $cashierBootstrap = $this->Pos_model->cashier_bootstrap_options($employeeId);
        if ($outletId <= 0) {
            $outletId = (int)($cashierBootstrap['active_session']['outlet_id'] ?? $cashierBootstrap['default_outlet_id'] ?? 0);
        }

        $products = $this->Pos_model->order_product_catalog([
            'outlet_id' => $outletId,
            'limit' => 120,
        ]);
        $bundles = $this->Pos_model->order_bundle_catalog([
            'outlet_id' => $outletId,
            'limit' => 60,
        ]);

        $this->json_ok([
            'sync_cursor' => date('c'),
            'server_time' => date('c'),
            'cashier_bootstrap' => $cashierBootstrap,
            'active_sessions' => $this->Pos_model->active_cashier_sessions(),
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
            'catalog_filters' => $this->Pos_model->cashier_catalog_filter_options(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
            'products' => $products,
            'bundles' => $bundles,
            'deleted' => [
                'products' => [],
                'bundles' => [],
                'payment_methods' => [],
                'printers' => [],
            ],
        ]);
    }

    public function catalog(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        $isBearer = is_array($this->mobileUser);
        if ($isBearer) {
            $binding = $this->mobile_reader_binding_context();
            if ($binding === null) {
                return;
            }
            $sessionContext = $this->mobile_reader_session_context(
                $this->current_actor_employee_id(),
                $binding['outlet_id'],
                $binding['terminal_id']
            );
            if (!$sessionContext['ok']) {
                return;
            }
            $outletId = $binding['outlet_id'];
        } else {
            $outletId = max(0, (int)$this->input->get('outlet_id', true));
        }

        $q = trim((string)$this->input->get('q', true));
        $divisionId = max(0, (int)$this->input->get('division_id', true));
        $categoryId = max(0, (int)$this->input->get('category_id', true));
        $limit = max(1, min(120, (int)$this->input->get('limit', true) ?: 60));
        $mode = strtoupper(trim((string)$this->input->get('mode', true)));

        if (!$isBearer && $outletId <= 0) {
            $employeeId = $this->current_actor_employee_id();
            $session = $this->Pos_model->find_active_cashier_session($employeeId);
            $outletId = (int)($session['outlet_id'] ?? 0);
            if ($outletId <= 0) {
                $cashierBootstrap = $this->Pos_model->cashier_bootstrap_options($employeeId);
                $outletId = (int)(
                    $cashierBootstrap['active_session']['outlet_id']
                    ?? $cashierBootstrap['default_outlet_id']
                    ?? 0
                );
            }
        }

        $payload = [
            'q' => $q,
            'outlet_id' => $outletId,
            'division_id' => $divisionId,
            'category_id' => $categoryId,
            'limit' => $limit,
        ];
        $this->json_ok([
            'mode' => $mode === 'BUNDLE' ? 'BUNDLE' : 'PRODUCT',
            'query' => $q,
            'rows' => $mode === 'BUNDLE'
                ? $this->Pos_model->order_bundle_catalog($payload)
                : $this->Pos_model->order_product_catalog($payload),
        ]);
    }

    private function mobile_reader_binding_context(): ?array
    {
        $outletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if ($outletId <= 0 || $terminalId <= 0) {
            $this->json_error('Konteks outlet atau terminal perangkat tidak valid.', 403);
            return null;
        }

        foreach (['outlet_id' => $outletId, 'default_outlet_id' => $outletId, 'terminal_id' => $terminalId] as $key => $boundId) {
            $requestedId = max(0, (int)$this->input->get($key, true));
            if ($requestedId > 0 && $requestedId !== $boundId) {
                $this->json_error('Konteks outlet atau terminal perangkat tidak valid.', 403);
                return null;
            }
        }

        return ['outlet_id' => $outletId, 'terminal_id' => $terminalId];
    }

    private function mobile_reader_session_context(int $employeeId, int $outletId, int $terminalId): array
    {
        $session = $this->Pos_model->find_active_cashier_session($employeeId);
        if ($session === null) {
            return ['ok' => true, 'session' => null, 'backup_mode' => false];
        }
        if (
            strtoupper(trim((string)($session['session_status'] ?? ''))) !== 'OPEN'
            || (int)($session['employee_id'] ?? 0) !== $employeeId
            || (int)($session['outlet_id'] ?? 0) !== $outletId
        ) {
            $this->json_error('Sesi kasir tidak sesuai dengan outlet atau akun perangkat.', 403);
            return ['ok' => false, 'session' => null, 'backup_mode' => false];
        }

        $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
        $backupMode = $ownerTerminalId > 0 && $ownerTerminalId !== $terminalId;
        return [
            'ok' => true,
            'session' => $session,
            'backup_mode' => $backupMode,
            'owner_terminal_id' => $ownerTerminalId,
            'origin_terminal_id' => $terminalId,
        ];
    }

    private function scope_mobile_reader_option_lists(array $options, int $outletId, int $terminalId): array
    {
        $options['outlets'] = array_values(array_filter((array)($options['outlets'] ?? []), static function (array $row) use ($outletId): bool {
            return (int)($row['id'] ?? 0) === $outletId;
        }));
        $options['terminals'] = array_values(array_filter((array)($options['terminals'] ?? []), static function (array $row) use ($outletId, $terminalId): bool {
            return (int)($row['id'] ?? 0) === $terminalId
                && (int)($row['outlet_id'] ?? 0) === $outletId;
        }));
        return $options;
    }

    private function mobile_financial_order_context(int $orderId): ?array
    {
        if (!is_array($this->mobileUser)) {
            return ['is_bearer' => false, 'order_id' => $orderId, 'outlet_id' => 0];
        }

        $boundOutletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        $order = $this->Pos_model->find_order_draft($orderId);
        $orderOutletId = max(0, (int)($order['header']['outlet_id'] ?? 0));
        if (!$order || $boundOutletId <= 0 || $orderOutletId <= 0 || $orderOutletId !== $boundOutletId) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return null;
        }

        return ['is_bearer' => true, 'order_id' => $orderId, 'outlet_id' => $boundOutletId];
    }

    /**
     * Server-owned APK capability contract. It contains no secret and lets a
     * client choose the exact verification flow without guessing API behavior.
     */
    private function mobile_sensitive_action_contract(): array
    {
        return [
            'version' => 3,
            'proof_ttl_seconds' => self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS,
            'actions' => [
                'VOID' => [
                    'verify_route' => 'pos-mobile/orders/reversal-step-up/verify',
                    'submit_route' => 'pos-mobile/orders/void/save',
                    'submit_method' => 'POST',
                ],
                'REFUND' => [
                    'verify_route' => 'pos-mobile/orders/reversal-step-up/verify',
                    'submit_route' => 'pos-mobile/orders/refund/save',
                    'submit_method' => 'POST',
                ],
                'ORDER_REPRINT' => [
                    'verify_route' => 'pos-mobile/orders/reprint-step-up/verify',
                    'submit_route' => 'pos-mobile/orders/reprint-targets/{order_id}',
                    'submit_method' => 'POST',
                ],
                'CASHIER_CLOSE' => [
                    'verify_route' => 'pos-mobile/cashier/close-step-up/verify',
                    'submit_route' => 'pos-mobile/cashier/close',
                    'submit_method' => 'POST',
                ],
                'RESERVATION_DEPOSIT_REFUND' => [
                    'verify_route' => 'pos-mobile/reservations/reject-step-up/verify',
                    'submit_route' => 'pos-mobile/reservations/reject/{reservation_id}',
                    'submit_method' => 'POST',
                ],
            ],
        ];
    }

    private function mobile_order_reversal_permission(string $action): bool
    {
        if ($action === 'ORDER_REPRINT') {
            return $this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view');
        }

        $pageCode = $action === 'REFUND'
            ? $this->mobile_order_workspace_page_code('edit', 'pos.order.paid.index')
            : $this->mobile_order_workspace_page_code('edit');
        return $this->mobile_permission($pageCode, 'edit');
    }

    private function mobile_order_reversal_step_up_schema_ready(bool $reportError = true): bool
    {
        if (
            $this->db->table_exists('pos_mobile_auth_token')
            && $this->db->table_exists('pos_mobile_sensitive_action_proof')
        ) {
            return true;
        }

        if ($reportError) {
            $this->json_error('Schema verifikasi ulang POS Mobile belum siap. Jalankan updater aplikasi terlebih dahulu.', 503);
        }
        return false;
    }

    private function mobile_cashier_close_step_up_schema_ready(bool $reportError = true): bool
    {
        if (
            $this->db->table_exists('pos_mobile_auth_token')
            && $this->db->table_exists('pos_mobile_sensitive_action_proof')
            && $this->db->field_exists('cashier_session_id', 'pos_mobile_sensitive_action_proof')
        ) {
            return true;
        }

        if ($reportError) {
            $this->json_error('Schema verifikasi ulang Tutup Kasir POS Mobile belum siap. Jalankan updater aplikasi terlebih dahulu.', 503);
        }
        return false;
    }

    private function mobile_reservation_refund_step_up_schema_ready(bool $reportError = true): bool
    {
        if (
            $this->db->table_exists('pos_mobile_auth_token')
            && $this->db->table_exists('pos_mobile_sensitive_action_proof')
            && $this->db->field_exists('reservation_id', 'pos_mobile_sensitive_action_proof')
        ) {
            return true;
        }

        if ($reportError) {
            $this->json_error('Schema verifikasi ulang pengembalian DP reservasi belum siap. Jalankan updater aplikasi terlebih dahulu.', 503);
        }
        return false;
    }

    private function issue_mobile_order_reversal_step_up(string $action, int $orderId, $password): array
    {
        if (!is_array($this->mobileUser) || !$this->mobile_order_reversal_step_up_schema_ready(false)) {
            return ['ok' => false, 'status' => 503, 'message' => 'Schema verifikasi ulang POS Mobile belum siap.'];
        }

        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $orderId <= 0
            || !in_array($action, ['VOID', 'REFUND', 'ORDER_REPRINT'], true)
            || !is_string($password) || $password === '' || strlen($password) > 72
        ) {
            return ['ok' => false, 'status' => 422, 'message' => 'Data verifikasi ulang tidak valid.'];
        }

        $now = date('Y-m-d H:i:s');
        $token = $this->db
            ->select('id, user_id, expires_at, revoked_at, step_up_failure_window_at, step_up_failure_count, step_up_locked_until')
            ->from('pos_mobile_auth_token')
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL', null, false)
            ->where('expires_at >=', $now)
            ->limit(1)
            ->get()
            ->row_array();
        if (!is_array($token) || (int)($token['id'] ?? 0) !== $tokenId) {
            return ['ok' => false, 'status' => 401, 'message' => 'Token mobile tidak valid atau sudah kedaluwarsa.'];
        }

        $lockedUntil = trim((string)($token['step_up_locked_until'] ?? ''));
        if ($lockedUntil !== '' && $lockedUntil > $now) {
            return ['ok' => false, 'status' => 429, 'message' => 'Terlalu banyak verifikasi gagal. Tunggu sebentar lalu coba lagi.'];
        }

        $user = $this->db
            ->select('id, password_hash')
            ->from('auth_user')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        $valid = is_array($user) && !empty($user['password_hash'])
            && password_verify($password, (string)$user['password_hash']);
        if (!$valid) {
            $this->record_mobile_order_reversal_step_up_failure($token, $now);
            return ['ok' => false, 'status' => 403, 'message' => 'Verifikasi ulang tidak berhasil.'];
        }

        $proof = bin2hex(random_bytes(32));
        $created = $this->db->insert('pos_mobile_sensitive_action_proof', [
            'proof_hash' => hash('sha256', $proof),
            'mobile_token_id' => $tokenId,
            'user_id' => $userId,
            'terminal_id' => $terminalId,
            'action' => $action,
            'order_id' => $orderId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS),
            'ip_address' => substr((string)$this->input->ip_address(), 0, 64),
        ]);
        if (!$created) {
            return ['ok' => false, 'status' => 503, 'message' => 'Verifikasi ulang POS Mobile belum dapat disiapkan.'];
        }

        $this->db
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->update('pos_mobile_auth_token', [
                'step_up_failure_window_at' => null,
                'step_up_failure_count' => 0,
                'step_up_locked_until' => null,
                'updated_at' => $now,
            ]);

        return ['ok' => true, 'proof' => $proof, 'expires_in_seconds' => self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS];
    }

    private function record_mobile_order_reversal_step_up_failure(array $token, string $now): void
    {
        $tokenId = max(0, (int)($token['id'] ?? 0));
        $windowStart = trim((string)($token['step_up_failure_window_at'] ?? ''));
        $withinWindow = $windowStart !== '' && strtotime($windowStart) >= time() - self::MOBILE_REVERSAL_STEP_UP_FAILURE_WINDOW_SECONDS;
        $count = $withinWindow ? max(0, (int)($token['step_up_failure_count'] ?? 0)) + 1 : 1;
        $this->db
            ->where('id', $tokenId)
            ->update('pos_mobile_auth_token', [
                'step_up_failure_window_at' => $withinWindow ? $windowStart : $now,
                'step_up_failure_count' => $count,
                'step_up_locked_until' => $count >= self::MOBILE_REVERSAL_STEP_UP_FAILURE_LIMIT
                    ? date('Y-m-d H:i:s', time() + self::MOBILE_REVERSAL_STEP_UP_FAILURE_WINDOW_SECONDS)
                    : null,
                'updated_at' => $now,
            ]);
    }

    /**
     * Tutup kasir mengunci satu cashier session/shift, bukan order. Simpan
     * target pada kolom khusus agar proof tidak dapat dipakai pada VOID,
     * REFUND, atau aksi order lain.
     */
    private function issue_mobile_cashier_close_step_up(int $cashierSessionId, $password): array
    {
        if (!is_array($this->mobileUser) || !$this->mobile_cashier_close_step_up_schema_ready(false)) {
            return ['ok' => false, 'status' => 503, 'message' => 'Schema verifikasi ulang Tutup Kasir POS Mobile belum siap.'];
        }

        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $cashierSessionId <= 0
            || !is_string($password) || $password === '' || strlen($password) > 72
        ) {
            return ['ok' => false, 'status' => 422, 'message' => 'Data verifikasi ulang tidak valid.'];
        }

        $now = date('Y-m-d H:i:s');
        $token = $this->db
            ->select('id, user_id, expires_at, revoked_at, step_up_failure_window_at, step_up_failure_count, step_up_locked_until')
            ->from('pos_mobile_auth_token')
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL', null, false)
            ->where('expires_at >=', $now)
            ->limit(1)
            ->get()
            ->row_array();
        if (!is_array($token) || (int)($token['id'] ?? 0) !== $tokenId) {
            return ['ok' => false, 'status' => 401, 'message' => 'Token mobile tidak valid atau sudah kedaluwarsa.'];
        }

        $lockedUntil = trim((string)($token['step_up_locked_until'] ?? ''));
        if ($lockedUntil !== '' && $lockedUntil > $now) {
            return ['ok' => false, 'status' => 429, 'message' => 'Terlalu banyak verifikasi gagal. Tunggu sebentar lalu coba lagi.'];
        }

        $user = $this->db
            ->select('id, password_hash')
            ->from('auth_user')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        $valid = is_array($user) && !empty($user['password_hash'])
            && password_verify($password, (string)$user['password_hash']);
        if (!$valid) {
            $this->record_mobile_order_reversal_step_up_failure($token, $now);
            return ['ok' => false, 'status' => 403, 'message' => 'Verifikasi ulang tidak berhasil.'];
        }

        $proof = bin2hex(random_bytes(32));
        $created = $this->db->insert('pos_mobile_sensitive_action_proof', [
            'proof_hash' => hash('sha256', $proof),
            'mobile_token_id' => $tokenId,
            'user_id' => $userId,
            'terminal_id' => $terminalId,
            'action' => 'CASHIER_CLOSE',
            'order_id' => 0,
            'cashier_session_id' => $cashierSessionId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS),
            'ip_address' => substr((string)$this->input->ip_address(), 0, 64),
        ]);
        if (!$created) {
            return ['ok' => false, 'status' => 503, 'message' => 'Verifikasi ulang Tutup Kasir POS Mobile belum dapat disiapkan.'];
        }

        $this->db
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->update('pos_mobile_auth_token', [
                'step_up_failure_window_at' => null,
                'step_up_failure_count' => 0,
                'step_up_locked_until' => null,
                'updated_at' => $now,
            ]);

        return ['ok' => true, 'proof' => $proof, 'expires_in_seconds' => self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS];
    }

    /**
     * A reservation rejection is normally operational. It becomes a financial
     * reversal only when the caller also asks to return a paid deposit, so the
     * proof is required solely for that narrow path and is bound to the exact
     * reservation rather than an unrelated POS order.
     */
    private function issue_mobile_reservation_refund_step_up(int $reservationId, $password): array
    {
        if (!is_array($this->mobileUser) || !$this->mobile_reservation_refund_step_up_schema_ready(false)) {
            return ['ok' => false, 'status' => 503, 'message' => 'Schema verifikasi ulang pengembalian DP reservasi belum siap.'];
        }

        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $reservationId <= 0
            || !is_string($password) || $password === '' || strlen($password) > 72
        ) {
            return ['ok' => false, 'status' => 422, 'message' => 'Data verifikasi ulang tidak valid.'];
        }

        $now = date('Y-m-d H:i:s');
        $token = $this->db
            ->select('id, user_id, expires_at, revoked_at, step_up_failure_window_at, step_up_failure_count, step_up_locked_until')
            ->from('pos_mobile_auth_token')
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL', null, false)
            ->where('expires_at >=', $now)
            ->limit(1)
            ->get()
            ->row_array();
        if (!is_array($token) || (int)($token['id'] ?? 0) !== $tokenId) {
            return ['ok' => false, 'status' => 401, 'message' => 'Token mobile tidak valid atau sudah kedaluwarsa.'];
        }

        $lockedUntil = trim((string)($token['step_up_locked_until'] ?? ''));
        if ($lockedUntil !== '' && $lockedUntil > $now) {
            return ['ok' => false, 'status' => 429, 'message' => 'Terlalu banyak verifikasi gagal. Tunggu sebentar lalu coba lagi.'];
        }

        $user = $this->db
            ->select('id, password_hash')
            ->from('auth_user')
            ->where('id', $userId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        $valid = is_array($user) && !empty($user['password_hash'])
            && password_verify($password, (string)$user['password_hash']);
        if (!$valid) {
            $this->record_mobile_order_reversal_step_up_failure($token, $now);
            return ['ok' => false, 'status' => 403, 'message' => 'Verifikasi ulang tidak berhasil.'];
        }

        $proof = bin2hex(random_bytes(32));
        $created = $this->db->insert('pos_mobile_sensitive_action_proof', [
            'proof_hash' => hash('sha256', $proof),
            'mobile_token_id' => $tokenId,
            'user_id' => $userId,
            'terminal_id' => $terminalId,
            'action' => 'RESERVATION_DEPOSIT_REFUND',
            'order_id' => 0,
            'cashier_session_id' => null,
            'reservation_id' => $reservationId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS),
            'ip_address' => substr((string)$this->input->ip_address(), 0, 64),
        ]);
        if (!$created) {
            return ['ok' => false, 'status' => 503, 'message' => 'Verifikasi ulang pengembalian DP reservasi belum dapat disiapkan.'];
        }

        $this->db
            ->where('id', $tokenId)
            ->where('user_id', $userId)
            ->update('pos_mobile_auth_token', [
                'step_up_failure_window_at' => null,
                'step_up_failure_count' => 0,
                'step_up_locked_until' => null,
                'updated_at' => $now,
            ]);

        return ['ok' => true, 'proof' => $proof, 'expires_in_seconds' => self::MOBILE_REVERSAL_STEP_UP_TTL_SECONDS];
    }

    private function consume_mobile_order_reversal_step_up(string $action, int $orderId, array $payload): bool
    {
        if (!is_array($this->mobileUser)) {
            $this->load->library('SensitiveActionStepUp', null, 'sensitiveactionstepup');
            $result = $this->sensitiveactionstepup->consume(
                $this->current_actor_user_id(),
                $action,
                $orderId,
                $payload['step_up_proof'] ?? null
            );
            if (!($result['ok'] ?? false)) {
                $this->json_error((string)($result['message'] ?? 'Verifikasi ulang diperlukan.'), (int)($result['status'] ?? 428), ['step_up_required' => true]);
                return false;
            }
            return true;
        }

        if (!$this->mobile_order_reversal_step_up_schema_ready()) {
            return false;
        }
        $proof = $payload['step_up_proof'] ?? null;
        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            !is_string($proof) || preg_match('/\A[0-9a-f]{64}\z/D', $proof) !== 1
            || $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $orderId <= 0
            || !in_array($action, ['VOID', 'REFUND', 'ORDER_REPRINT'], true)
        ) {
            $this->json_error('Verifikasi ulang diperlukan sebelum aksi ini.', 428, ['step_up_required' => true]);
            return false;
        }

        $this->db
            ->where('proof_hash', hash('sha256', $proof))
            ->where('mobile_token_id', $tokenId)
            ->where('user_id', $userId)
            ->where('terminal_id', $terminalId)
            ->where('action', $action)
            ->where('order_id', $orderId)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->where('consumed_at IS NULL', null, false)
            ->update('pos_mobile_sensitive_action_proof', [
                'consumed_at' => date('Y-m-d H:i:s'),
            ]);
        if ($this->db->affected_rows() !== 1) {
            $this->json_error('Verifikasi ulang diperlukan sebelum aksi ini.', 428, ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_mobile_cashier_close_step_up(int $cashierSessionId, array $payload): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }
        if (!$this->mobile_cashier_close_step_up_schema_ready()) {
            return false;
        }

        $proof = $payload['step_up_proof'] ?? null;
        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            !is_string($proof) || preg_match('/\A[0-9a-f]{64}\z/D', $proof) !== 1
            || $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $cashierSessionId <= 0
        ) {
            $this->json_error('Verifikasi ulang diperlukan sebelum menutup kasir.', 428, ['step_up_required' => true]);
            return false;
        }

        $this->db
            ->where('proof_hash', hash('sha256', $proof))
            ->where('mobile_token_id', $tokenId)
            ->where('user_id', $userId)
            ->where('terminal_id', $terminalId)
            ->where('action', 'CASHIER_CLOSE')
            ->where('cashier_session_id', $cashierSessionId)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->where('consumed_at IS NULL', null, false)
            ->update('pos_mobile_sensitive_action_proof', [
                'consumed_at' => date('Y-m-d H:i:s'),
            ]);
        if ($this->db->affected_rows() !== 1) {
            $this->json_error('Verifikasi ulang diperlukan sebelum menutup kasir.', 428, ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function consume_mobile_reservation_refund_step_up(int $reservationId, array $payload): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }
        if (!$this->mobile_reservation_refund_step_up_schema_ready()) {
            return false;
        }

        $proof = $payload['step_up_proof'] ?? null;
        $tokenId = max(0, (int)($this->mobileUser['id'] ?? 0));
        $userId = max(0, (int)($this->mobileUser['user_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if (
            !is_string($proof) || preg_match('/\A[0-9a-f]{64}\z/D', $proof) !== 1
            || $tokenId <= 0 || $userId <= 0 || $terminalId <= 0 || $reservationId <= 0
        ) {
            $this->json_error('Verifikasi ulang diperlukan sebelum mengembalikan DP reservasi.', 428, ['step_up_required' => true]);
            return false;
        }

        $this->db
            ->where('proof_hash', hash('sha256', $proof))
            ->where('mobile_token_id', $tokenId)
            ->where('user_id', $userId)
            ->where('terminal_id', $terminalId)
            ->where('action', 'RESERVATION_DEPOSIT_REFUND')
            ->where('reservation_id', $reservationId)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->where('consumed_at IS NULL', null, false)
            ->update('pos_mobile_sensitive_action_proof', [
                'consumed_at' => date('Y-m-d H:i:s'),
            ]);
        if ($this->db->affected_rows() !== 1) {
            $this->json_error('Verifikasi ulang diperlukan sebelum mengembalikan DP reservasi.', 428, ['step_up_required' => true]);
            return false;
        }
        return true;
    }

    private function require_mobile_print_document_outlet(string $documentType, int $documentId): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }

        $boundOutletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        $context = $this->Pos_model->find_mobile_print_document_context($documentType, $documentId);
        $orderId = max(0, (int)($context['order_id'] ?? 0));
        $documentOutletId = max(0, (int)($context['outlet_id'] ?? 0));
        if (!$context || $orderId <= 0 || $boundOutletId <= 0 || $documentOutletId <= 0 || $documentOutletId !== $boundOutletId) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return false;
        }

        return true;
    }

    private function mobile_payment_replay_context(array $event, array $orderContext): ?array
    {
        $orderId = max(0, (int)($orderContext['order_id'] ?? 0));
        $outletId = max(0, (int)($orderContext['outlet_id'] ?? 0));
        $storedRequest = $this->decode_json_assoc((string)($event['request_json'] ?? ''));
        $storedResponse = $this->decode_json_assoc((string)($event['response_json'] ?? ''));
        $eventType = strtoupper(trim((string)($event['event_type'] ?? '')));

        $provedOrderIds = [];
        foreach ([
            $event['server_order_id'] ?? 0,
            $storedRequest['order_id'] ?? 0,
            $storedResponse['order_id'] ?? 0,
            $storedResponse['server_order_id'] ?? 0,
        ] as $candidateOrderId) {
            $candidateOrderId = max(0, (int)$candidateOrderId);
            if ($candidateOrderId > 0) {
                $provedOrderIds[] = $candidateOrderId;
            }
        }

        $storedOutletIds = [];
        foreach ([
            $event['outlet_id'] ?? 0,
            $storedRequest['outlet_id'] ?? 0,
            $storedRequest['default_outlet_id'] ?? 0,
            $storedResponse['outlet_id'] ?? 0,
        ] as $candidateOutletId) {
            $candidateOutletId = max(0, (int)$candidateOutletId);
            if ($candidateOutletId > 0) {
                $storedOutletIds[] = $candidateOutletId;
            }
        }

        if (
            $orderId <= 0
            || $outletId <= 0
            || ($eventType !== '' && $eventType !== 'PAYMENT')
            || $provedOrderIds === []
            || count(array_filter($provedOrderIds, static function (int $provedOrderId) use ($orderId): bool {
                return $provedOrderId !== $orderId;
            })) > 0
            || count(array_filter($storedOutletIds, static function (int $storedOutletId) use ($outletId): bool {
                return $storedOutletId !== $outletId;
            })) > 0
        ) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return null;
        }

        return ['response' => $storedResponse];
    }

    private function mobile_cashier_session_context(bool $requireSession): array
    {
        if (!is_array($this->mobileUser)) {
            return ['ok' => true, 'session' => null];
        }

        $employeeId = max(0, (int)($this->mobileUser['employee_id'] ?? 0));
        $outletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if ($employeeId <= 0 || $outletId <= 0 || $terminalId <= 0) {
            $this->json_error('Sesi kasir tidak sesuai dengan perangkat.', 403);
            return ['ok' => false, 'session' => null];
        }

        $session = $this->Pos_model->find_active_cashier_session($employeeId);
        if ($session === null) {
            if ($requireSession) {
                $this->json_error('Sesi kasir tidak sesuai dengan perangkat.', 403);
                return ['ok' => false, 'session' => null];
            }
            return ['ok' => true, 'session' => null];
        }

        if (
            strtoupper(trim((string)($session['session_status'] ?? ''))) !== 'OPEN'
            || (int)($session['employee_id'] ?? 0) !== $employeeId
            || (int)($session['outlet_id'] ?? 0) !== $outletId
        ) {
            $this->json_error('Sesi kasir tidak sesuai dengan outlet atau akun perangkat.', 403);
            return ['ok' => false, 'session' => null];
        }

        $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
        return [
            'ok' => true,
            'session' => $session,
            'backup_mode' => $ownerTerminalId > 0 && $ownerTerminalId !== $terminalId,
            'owner_terminal_id' => $ownerTerminalId,
            'origin_terminal_id' => $terminalId,
        ];
    }

    private function mobile_draft_upsert_context(array &$payload): ?array
    {
        if (!is_array($this->mobileUser)) {
            return ['is_bearer' => false, 'order_id' => (int)($payload['id'] ?? 0)];
        }

        $orderId = (int)($payload['id'] ?? 0);
        $employeeId = max(0, (int)($this->mobileUser['employee_id'] ?? 0));
        $outletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        $terminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
        if ($employeeId <= 0 || $outletId <= 0 || $terminalId <= 0) {
            $this->json_error('Sesi kasir tidak sesuai dengan perangkat.', 403);
            return null;
        }

        if ($orderId > 0) {
            $order = $this->Pos_model->find_order_draft($orderId);
            $orderOutletId = max(0, (int)($order['header']['outlet_id'] ?? 0));
            if (!$order || $orderOutletId <= 0 || $orderOutletId !== $outletId) {
                $this->json_error('Order POS tidak ditemukan.', 404);
                return null;
            }
        } else {
            $requestedOutletId = max(0, (int)($payload['outlet_id'] ?? 0));
            $requestedTerminalId = max(0, (int)($payload['terminal_id'] ?? 0));
            if (
                ($requestedOutletId > 0 && $requestedOutletId !== $outletId)
                || ($requestedTerminalId > 0 && $requestedTerminalId !== $terminalId)
            ) {
                $this->json_error('Sesi kasir tidak sesuai dengan perangkat.', 403);
                return null;
            }
        }

        $payload['outlet_id'] = $outletId;
        $sessionContext = $this->mobile_cashier_session_context(true);
        if (empty($sessionContext['ok'])) {
            return null;
        }
        $session = (array)($sessionContext['session'] ?? []);
        $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
        $backupMode = !empty($sessionContext['backup_mode']);
        $payload['terminal_id'] = $ownerTerminalId > 0 ? $ownerTerminalId : $terminalId;
        $payload['origin_terminal_id'] = $terminalId;
        $payload['mobile_backup_mode'] = $backupMode;

        return [
            'is_bearer' => true,
            'order_id' => $orderId,
            'employee_id' => $employeeId,
            'outlet_id' => $outletId,
            'terminal_id' => $terminalId,
            'owner_terminal_id' => $ownerTerminalId,
            'backup_mode' => $backupMode,
        ];
    }

    private function mobile_order_upsert_permission_guard(): bool
    {
        foreach (['edit', 'create'] as $action) {
            foreach (['pos.cashier.index', 'pos.order.draft.index'] as $pageCode) {
                if ($this->mobile_can($pageCode, $action)) {
                    return true;
                }
            }
        }

        $this->json_error('Anda tidak memiliki izin untuk aksi ini.', 403, [
            'page_code' => 'pos.order.draft.index',
            'action' => 'create_or_edit',
        ]);
        return false;
    }

    private function mobile_order_push_replay_context(array $event, array $context): ?array
    {
        $eventRequest = $this->decode_json_assoc((string)($event['request_json'] ?? ''));
        $eventResponse = $this->decode_json_assoc((string)($event['response_json'] ?? ''));
        $eventType = strtoupper(trim((string)($event['event_type'] ?? '')));
        $orderId = max(0, (int)($context['order_id'] ?? 0));
        $outletId = max(0, (int)($context['outlet_id'] ?? 0));
        $terminalId = max(0, (int)($context['terminal_id'] ?? 0));
        $serverOrderId = max(0, (int)($event['server_order_id'] ?? 0));
        $eventOrderId = max(0, (int)($eventRequest['id'] ?? $eventRequest['order_id'] ?? 0));
        $responseOrderId = max(0, (int)($eventResponse['server_id'] ?? $eventResponse['order_id'] ?? 0));

        if (
            ($eventType !== '' && $eventType !== 'ORDER_UPSERT')
            || $outletId <= 0
            || $terminalId <= 0
            || (int)($eventRequest['outlet_id'] ?? 0) !== $outletId
            || (
                (int)($eventRequest['terminal_id'] ?? 0) !== $terminalId
                && (
                    empty($eventRequest['mobile_backup_mode'])
                    || (int)($eventRequest['origin_terminal_id'] ?? 0) !== $terminalId
                )
            )
            || $serverOrderId <= 0
            || ($orderId > 0 && $serverOrderId !== $orderId)
            || ($eventOrderId > 0 && $eventOrderId !== $serverOrderId)
            || $responseOrderId !== $serverOrderId
        ) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return null;
        }

        return ['response' => $eventResponse, 'server_order_id' => $serverOrderId];
    }

    public function member_search(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function extra_options(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->json_ok([
            'product_id' => $productId,
            'groups' => $this->Pos_model->order_extra_options($productId),
        ]);
    }

    public function printers(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_printer_permission('view')) {
            return;
        }

        $isBearer = is_array($this->mobileUser);
        $binding = $isBearer ? $this->mobile_reader_binding_context() : null;
        if ($isBearer && $binding === null) {
            return;
        }

        if ($this->Pos_print_model->ready()) {
            $outletId = $isBearer
                ? (int)$binding['outlet_id']
                : max(0, (int)$this->input->get('outlet_id', true));
            $connectionFilters = [
                'q' => trim((string)$this->input->get('q', true)),
                'status' => $isBearer
                    ? 'ACTIVE'
                    : (strtoupper(trim((string)$this->input->get('status', true))) ?: 'ACTIVE'),
                'page' => max(1, (int)$this->input->get('page', true) ?: 1),
                'limit' => max(5, min(100, (int)$this->input->get('limit', true) ?: 100)),
            ];
            if ($isBearer) {
                $connectionFilters['outlet_id'] = $outletId;
            }
            $catalog = $this->Pos_print_model->connection_rows($connectionFilters);
            $routeFilters = [
                'status' => 'ACTIVE',
                'limit' => 100,
            ];
            if ($isBearer) {
                $routeFilters += [
                    'connection_outlet_id' => $outletId,
                    'outlet_id' => $outletId,
                    'terminal_id' => (int)$binding['terminal_id'],
                    'runtime_ready' => true,
                ];
            }
            $routes = $this->Pos_print_model->route_rows($routeFilters);
            $routeRows = (array)($routes['rows'] ?? []);
            $rows = [];
            foreach ((array)($catalog['rows'] ?? []) as $connection) {
                $connectionOutlet = max(0, (int)($connection['outlet_id'] ?? 0));
                if ($isBearer && $connectionOutlet !== $outletId) {
                    continue;
                }
                if ($outletId > 0 && $connectionOutlet > 0 && $connectionOutlet !== $outletId) {
                    continue;
                }
                $connectionId = (int)($connection['id'] ?? 0);
                $connectionRoutes = [];
                foreach ($routeRows as $route) {
                    if ((int)($route['connection_id'] ?? 0) !== $connectionId) {
                        continue;
                    }
                    $connectionRoutes[] = [
                        'id' => (int)($route['id'] ?? 0),
                        'event_code' => (string)($route['event_code'] ?? ''),
                        'document_type' => (string)($route['document_type'] ?? ''),
                        'route_name' => (string)($route['route_name'] ?? ''),
                        'layout_name' => (string)($route['layout_name'] ?? ''),
                        'print_mode' => (string)($route['print_mode'] ?? 'AUTO'),
                        'copy_count' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
                    ];
                }
                $rows[] = [
                    'id' => $connectionId,
                    'server_source' => 'pos_print_connection',
                    'device_code' => (string)($connection['connection_code'] ?? ''),
                    'device_name' => (string)($connection['connection_name'] ?? ''),
                    'outlet_id' => $connectionOutlet,
                    'outlet_name' => (string)($connection['outlet_name'] ?? ''),
                    'printer_role' => strtoupper(trim((string)($connection['location_label'] ?? 'CUSTOM'))) ?: 'CUSTOM',
                    'print_scope' => 'SERVER_RULE',
                    'connection_type' => (string)($connection['connection_type'] ?? 'LOCAL_AGENT'),
                    'paper_width_mm' => (int)($connection['paper_width_mm'] ?? 80),
                    'chars_per_line' => (int)($connection['chars_per_line'] ?? 48),
                    'copies' => max(1, (int)($connection['default_copy_count'] ?? 1)),
                    'cut_mode' => (string)($connection['cut_mode'] ?? 'PARTIAL'),
                    'open_drawer' => !empty($connection['open_drawer']) ? 1 : 0,
                    'template_name' => (string)($connectionRoutes[0]['layout_name'] ?? ''),
                    'template_document_type' => (string)($connectionRoutes[0]['document_type'] ?? ''),
                    'server_routes' => $connectionRoutes,
                    'is_active' => (int)($connection['is_active'] ?? 0),
                ];
            }
            $general = (array)($this->Pos_print_model->general_settings($outletId)['payload'] ?? []);
            if ($isBearer) {
                $general = $this->mobile_redact_printer_payload($general);
            }
            $this->json_ok([
                'rows' => $rows,
                'meta' => $catalog['meta'] ?? ['total' => count($rows), 'page' => 1, 'limit' => count($rows), 'total_pages' => 1],
                'config_source' => 'pos_print_connection/pos_print_layout/pos_print_route/pos_print_general_setting',
                'general' => $general,
            ]);
            return;
        }

        $this->json_error(
            'Konfigurasi printer pos_print_* belum siap. Jalankan migration konfigurasi printer baru terlebih dahulu.',
            503
        );
    }

    /**
     * Build a server-authoritative dummy print package for a mobile-bound
     * printer. The APK must not recreate Finance templates locally.
     */
    public function printer_test($id): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_printer_permission('test')) {
            return;
        }

        $isBearer = is_array($this->mobileUser);
        $binding = $isBearer ? $this->mobile_reader_binding_context() : null;
        if ($isBearer && $binding === null) {
            return;
        }

        if ($this->Pos_print_model->ready()) {
            $connection = $isBearer
                ? $this->Pos_print_model->find_active_connection_at_outlet((int)$id, (int)$binding['outlet_id'])
                : $this->Pos_print_model->find_connection((int)$id);
            if (!$connection || (int)($connection['is_active'] ?? 0) !== 1) {
                $this->json_error($isBearer ? 'Printer POS tidak ditemukan.' : 'Koneksi printer aktif tidak ditemukan di server.', 404);
                return;
            }
            if ($isBearer) {
                $route = $this->Pos_print_model->find_mobile_test_route(
                    (int)$id,
                    (int)$binding['outlet_id'],
                    (int)$binding['terminal_id']
                );
            } else {
                $routeRows = $this->Pos_print_model->route_rows(['status' => 'ACTIVE', 'limit' => 100]);
                $route = null;
                foreach ((array)($routeRows['rows'] ?? []) as $candidate) {
                    if ((int)($candidate['connection_id'] ?? 0) === (int)$id) {
                        $route = $candidate;
                        break;
                    }
                }
            }
            if (!$route) {
                $this->json_error($isBearer
                    ? 'Printer POS tidak ditemukan.'
                    : 'Printer belum memiliki aturan cetak aktif. Atur koneksi, layout, dan aturan cetak di Finance terlebih dahulu.', $isBearer ? 404 : 422);
                return;
            }
            $template = $this->Pos_print_model->runtime_template($route);
            $this->load->library('PosPrinterPreviewService');
            $preview = $this->posprinterpreviewservice->buildPreviewPackage(
                (array)($template['payload'] ?? []),
                [
                    'printer_name' => (string)($route['connection_name'] ?? $connection['connection_name'] ?? ''),
                    'printer_role' => (string)($route['location_label'] ?? $connection['location_label'] ?? 'CUSTOM'),
                    'print_scope' => (string)($route['content_scope'] ?? 'ALL_ITEMS'),
                    'connection_type' => (string)($route['connection_type'] ?? 'LOCAL_AGENT'),
                    'paper_width_mm' => (int)($route['paper_width_mm'] ?? $connection['paper_width_mm'] ?? 80),
                    'chars_per_line' => (int)($route['chars_per_line'] ?? $connection['chars_per_line'] ?? 48),
                    'python_port' => (int)($route['python_port'] ?? 0),
                    'agent_host' => (string)($route['agent_host'] ?? ''),
                ],
                (string)($template['document_type'] ?? 'RECEIPT')
            );
            if ($isBearer) {
                $preview = $this->mobile_redact_printer_payload($preview);
            }
            $previewLines = (array)($preview['lines'] ?? []);
            if ($isBearer && !empty($preview['logo_url'])) {
                $logoMarker = $this->posprinterpreviewservice->buildLogoPrintMarker((string)$preview['logo_url']);
                if ($logoMarker !== '') {
                    array_unshift($previewLines, $logoMarker);
                }
            }
            if ($isBearer && !empty($preview['customer_review_qr']['enabled'])
                && trim((string)($preview['customer_review_qr']['url'] ?? '')) !== '') {
                $previewLines[] = '[[QRCODE:' . trim((string)$preview['customer_review_qr']['url']) . ']]';
            }
            $rawPreviewText = implode("\n", $previewLines) . "\n";
            $text = $this->mobile_print_text($rawPreviewText);
            $attemptId = $this->Pos_print_model->create_attempt([
                'event_code' => 'TEST',
                'document_type' => (string)($template['document_type'] ?? 'RECEIPT'),
                'attempt_kind' => 'TEST',
                'route_id' => (int)($route['id'] ?? 0),
                'connection_id' => (int)$id,
                'layout_id' => (int)($route['layout_id'] ?? 0),
                'connection_name' => (string)($route['connection_name'] ?? $connection['connection_name'] ?? ''),
                'connection_code' => (string)($route['connection_code'] ?? $connection['connection_code'] ?? ''),
                'route_name' => (string)($route['route_name'] ?? ''),
                'route_code' => (string)($route['route_code'] ?? ''),
                'layout_name' => (string)($route['layout_name'] ?? ''),
                'layout_code' => (string)($route['layout_code'] ?? ''),
                'outlet_id' => $isBearer ? (int)$binding['outlet_id'] : (int)($route['outlet_id'] ?? $connection['outlet_id'] ?? 0),
                'terminal_id' => $isBearer ? (int)$binding['terminal_id'] : (int)($route['terminal_id'] ?? 0),
                'line_count' => count((array)($preview['lines'] ?? [])),
                'copy_count' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
            ]);
            $this->json_ok([
                'printer' => [
                    'id' => (int)$id,
                    'device_code' => (string)($connection['connection_code'] ?? ''),
                    'device_name' => (string)($connection['connection_name'] ?? ''),
                    'printer_role' => (string)($route['location_label'] ?? $connection['location_label'] ?? 'CUSTOM'),
                    'print_scope' => (string)($route['content_scope'] ?? 'ALL_ITEMS'),
                ],
                'route' => [
                    'id' => (int)($route['id'] ?? 0),
                    'event_code' => (string)($route['event_code'] ?? ''),
                    'route_name' => (string)($route['route_name'] ?? ''),
                ],
                'template' => [
                    'id' => (int)($route['layout_id'] ?? 0),
                    'template_name' => (string)($route['layout_name'] ?? 'Default Finance'),
                    'document_type' => (string)($template['document_type'] ?? 'RECEIPT'),
                    'division_filter' => (string)(($template['payload']['division_filter'] ?? 'ALL')),
                ],
                'preview' => $preview,
                'print_payload' => [
                    'text' => $text,
                    'print_segments' => $this->mobile_print_segments($rawPreviewText),
                    'paper_width_mm' => (int)($preview['paper_width_mm'] ?? 80),
                    'chars_per_line' => (int)($preview['chars_per_line'] ?? 48),
                    'copies' => max(1, (int)($route['copy_count'] ?? 0) ?: (int)($route['default_copy_count'] ?? 1)),
                    'cut_mode' => (string)($route['cut_mode'] ?? $connection['cut_mode'] ?? 'PARTIAL'),
                    'open_drawer' => !empty($route['open_drawer'] ?? $connection['open_drawer'] ?? 0) ? 1 : 0,
                    'print_attempt_id' => $attemptId,
                ],
            ]);
            return;
        }

        $this->json_error(
            'Konfigurasi printer pos_print_* belum siap. Atur koneksi, layout, dan aturan cetak di Finance terlebih dahulu.',
            503
        );
    }

    /**
     * Mobile verification inboxes intentionally reuse the Finance models.
     * The APK is a client of these projections; it must not rebuild channel
     * status, payment, stock, or HPP rules locally.
     */
    public function reservations(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'view')) {
            return;
        }
        if (!$this->mobile_incoming_scope_ready()) {
            return;
        }
        $this->load->model('Pos_reservation_model');
        $this->json_ok($this->Pos_reservation_model->reservation_rows($this->mobile_reservation_filters()));
    }

    public function reservation_products(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'view')) {
            return;
        }
        if (!$this->mobile_incoming_scope_ready()) {
            return;
        }
        $this->load->model('Pos_reservation_model');
        $this->json_ok($this->Pos_reservation_model->reservation_product_rows($this->mobile_reservation_filters()));
    }

    public function reservation_detail($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'view')) {
            return;
        }
        $this->load->model('Pos_reservation_model');
        $reservation = $this->Pos_reservation_model->find_reservation((int)$id);
        if (!$reservation) {
            $this->json_error('Reservasi tidak ditemukan.', 404);
            return;
        }
        if (!$this->mobile_document_outlet_allowed((int)($reservation['outlet_id'] ?? 0))) {
            return;
        }
        $this->json_ok(['reservation' => $reservation]);
    }

    public function reservation_verify($id): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'edit')) {
            return;
        }
        if (!$this->mobile_reservation_outlet_allowed((int)$id)) {
            return;
        }
        $this->verify_mobile_reservation((int)$id);
    }

    public function reservation_reject_step_up_verify(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!is_array($this->mobileUser)) {
            $this->json_error('Verifikasi ulang pengembalian DP reservasi membutuhkan token perangkat aktif.', 401);
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'edit')) {
            return;
        }

        $payload = $this->request_payload();
        $reservationId = max(0, (int)($payload['reservation_id'] ?? 0));
        if ($reservationId <= 0 || !$this->mobile_reservation_outlet_allowed($reservationId)) {
            return;
        }
        $result = $this->issue_mobile_reservation_refund_step_up($reservationId, $payload['password'] ?? null);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function reservation_reject($id): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission('pos.reservation.index', 'edit')) {
            return;
        }
        if (!$this->mobile_reservation_outlet_allowed((int)$id)) {
            return;
        }
        $payload = $this->request_payload();
        $refundDeposit = !empty($payload['refund_deposit']);
        if ($refundDeposit && is_array($this->mobileUser)) {
            if (!$this->consume_mobile_reservation_refund_step_up((int)$id, $payload)) {
                return;
            }
            unset($payload['step_up_proof']);
        }
        $this->load->model('Pos_reservation_model');
        $result = $this->Pos_reservation_model->reject_reservation(
            (int)$id,
            $this->current_actor_employee_id(),
            $this->current_actor_user_id(),
            trim((string)($payload['reason'] ?? '')),
            $refundDeposit
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menolak reservasi.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function self_order_inbox(): void
    {
        $this->incoming_order_list('SELF_ORDER');
    }

    public function self_order_inbox_detail($id): void
    {
        $this->incoming_order_detail((int)$id, 'SELF_ORDER');
    }

    public function online_food_inbox(): void
    {
        $this->incoming_order_list('DELIVERY');
    }

    public function online_food_inbox_detail($id): void
    {
        $this->incoming_order_detail((int)$id, 'DELIVERY');
    }

    public function self_order_inbox_verify($id): void
    {
        $this->verify_mobile_incoming((int)$id, 'SELF_ORDER');
    }

    public function self_order_inbox_reject($id): void
    {
        $this->reject_mobile_incoming((int)$id, 'SELF_ORDER');
    }

    public function online_food_inbox_verify($id): void
    {
        $this->verify_mobile_incoming((int)$id, 'DELIVERY');
    }

    public function online_food_inbox_reject($id): void
    {
        $this->reject_mobile_incoming((int)$id, 'DELIVERY');
    }

    public function orders(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        if (is_array($this->mobileUser)) {
            $outletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        } else {
            $session = $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id());
            $outletId = (int)($session['outlet_id'] ?? 0);
        }
        $requestedStatus = strtoupper(trim((string)$this->input->get('status', true)));
        $workspaceMode = $requestedStatus === 'PAID' ? 'PAID' : 'UNPAID';
        $status = in_array($requestedStatus, ['DRAFT', 'CONFIRMED', 'PAID'], true)
            ? $requestedStatus
            : 'ALL';
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'workspace_mode' => $workspaceMode,
            'outlet_id' => $outletId,
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d'),
            'page' => max(1, (int)$this->input->get('page', true) ?: 1),
            'limit' => max(1, min(50, (int)$this->input->get('limit', true) ?: 20)),
        ];
        $this->json_ok($this->Pos_model->order_draft_rows($filters));
    }

    public function order_load($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        $order = $this->Pos_model->find_order_draft((int)$id);
        if (!$order) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return;
        }
        if (is_array($this->mobileUser)) {
            $boundOutletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
            $orderOutletId = max(0, (int)($order['header']['outlet_id'] ?? 0));
            if ($boundOutletId <= 0 || $orderOutletId <= 0 || $orderOutletId !== $boundOutletId) {
                $this->json_error('Order POS tidak ditemukan.', 404);
                return;
            }
        }
        $this->json_ok($order);
    }

    public function order_reversal_preview($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if ($this->mobile_financial_order_context((int)$id) === null) {
            return;
        }
        $result = $this->Pos_model->order_reversal_preview((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview void/refund belum tersedia.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    /**
     * Reauthenticate a bearer-bound mobile cashier before a financial
     * reversal.  The password is deliberately used only here; the caller
     * receives a short-lived proof that is bound to one exact order/action.
     */
    public function order_reversal_step_up_verify(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!is_array($this->mobileUser)) {
            $this->json_error('Verifikasi ulang POS Mobile membutuhkan token perangkat aktif.', 401);
            return;
        }

        $payload = $this->request_payload();
        $action = strtoupper(trim((string)($payload['action'] ?? '')));
        if (!in_array($action, ['VOID', 'REFUND'], true)) {
            $this->json_error('Aksi verifikasi ulang tidak valid.', 422);
            return;
        }
        if (!$this->mobile_order_reversal_permission($action)) {
            return;
        }

        $orderId = max(0, (int)($payload['order_id'] ?? 0));
        if ($orderId <= 0 || $this->mobile_financial_order_context($orderId) === null) {
            return;
        }
        $result = $this->issue_mobile_order_reversal_step_up($action, $orderId, $payload['password'] ?? null);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    /**
     * Reauthenticate a bearer-bound mobile cashier before a reprint. Reprint
     * is deliberately separate from reversal so an APK can use a fixed action
     * and cannot turn a valid reprint proof into a financial reversal proof.
     */
    public function order_reprint_step_up_verify(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!is_array($this->mobileUser)) {
            $this->json_error('Verifikasi ulang POS Mobile membutuhkan token perangkat aktif.', 401);
            return;
        }
        if (!$this->mobile_order_reversal_permission('ORDER_REPRINT')) {
            return;
        }

        $payload = $this->request_payload();
        $orderId = max(0, (int)($payload['order_id'] ?? 0));
        if ($orderId <= 0 || $this->mobile_financial_order_context($orderId) === null) {
            return;
        }
        $result = $this->issue_mobile_order_reversal_step_up('ORDER_REPRINT', $orderId, $payload['password'] ?? null);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function cashier_close_step_up_verify(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!is_array($this->mobileUser)) {
            $this->json_error('Verifikasi ulang Tutup Kasir POS Mobile membutuhkan token perangkat aktif.', 401);
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }

        $sessionContext = $this->mobile_cashier_session_context(true);
        $cashierSessionId = max(0, (int)($sessionContext['session']['id'] ?? 0));
        if (empty($sessionContext['ok']) || $cashierSessionId <= 0) {
            return;
        }
        $payload = $this->request_payload();
        $result = $this->issue_mobile_cashier_close_step_up($cashierSessionId, $payload['password'] ?? null);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Verifikasi ulang tidak berhasil.'), (int)($result['status'] ?? 403));
            return;
        }
        $this->json_ok([
            'step_up_proof' => (string)$result['proof'],
            'expires_in_seconds' => (int)$result['expires_in_seconds'],
        ]);
    }

    public function order_void_save(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }
        $payload = $this->request_payload();
        $orderContext = $this->mobile_financial_order_context((int)($payload['order_id'] ?? 0));
        if ($orderContext === null) {
            return;
        }
        if (!$this->consume_mobile_order_reversal_step_up('VOID', (int)$orderContext['order_id'], $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_model->save_order_void(
            $payload,
            $this->current_actor_employee_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan void POS.'), 422);
            return;
        }
        $this->load->model('Pos_order_monitor_model');
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $this->json_ok($result);
    }

    public function order_refund_save(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission(
            $this->mobile_order_workspace_page_code('edit', 'pos.order.paid.index'),
            'edit'
        )) {
            return;
        }
        $payload = $this->request_payload();
        $orderContext = $this->mobile_financial_order_context((int)($payload['order_id'] ?? 0));
        if ($orderContext === null) {
            return;
        }
        if (!$this->consume_mobile_order_reversal_step_up('REFUND', (int)$orderContext['order_id'], $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_model->save_order_refund(
            $payload,
            $this->current_actor_employee_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan refund POS.'), 422);
            return;
        }
        $this->load->model('Pos_order_monitor_model');
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $this->json_ok($result);
    }

    public function order_void_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if (!$this->require_mobile_print_document_outlet('VOID', (int)$id)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_void((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak void.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'direct_print_targets' => $this->mobile_print_targets((array)($result['targets'] ?? []))]);
    }

    public function order_refund_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if (!$this->require_mobile_print_document_outlet('REFUND', (int)$id)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_refund((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak refund.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'direct_print_targets' => $this->mobile_print_targets((array)($result['targets'] ?? []))]);
    }

    public function order_reprint_targets($id): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        $orderContext = $this->mobile_financial_order_context((int)$id);
        if ($orderContext === null) {
            return;
        }

        $payload = $this->request_payload();
        if (!$this->consume_mobile_order_reversal_step_up('ORDER_REPRINT', (int)$orderContext['order_id'], $payload)) {
            return;
        }
        unset($payload['step_up_proof']);
        $result = $this->Pos_model->direct_print_targets_for_order_reprint((int)$id, [
            'printer_id' => max(0, (int)($payload['printer_id'] ?? 0)),
            'line_scope' => strtoupper(trim((string)($payload['line_scope'] ?? 'ALL'))),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak ulang order.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => $this->mobile_print_targets((array)($result['targets'] ?? [])),
        ]);
    }

    public function order_confirm_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if ($this->mobile_financial_order_context((int)$id) === null) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_order_confirm((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak order.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => $this->mobile_print_targets((array)($result['targets'] ?? [])),
        ]);
    }

    public function order_save(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_order_upsert_permission_guard()) {
            return;
        }

        $payload = $this->request_payload();
        $orderId = (int)($payload['id'] ?? 0);
        $action = $orderId > 0 ? 'edit' : 'create';
        $pageCode = $this->mobile_order_workspace_page_code($action);
        if (!$this->mobile_permission($pageCode, $action)) {
            return;
        }
        $orderContext = $this->mobile_draft_upsert_context($payload);
        if ($orderContext === null) {
            return;
        }
        $payload['require_active_session'] = true;
        $result = $this->Pos_model->save_order_draft(
            $payload,
            $this->current_actor_employee_id(),
            !empty($orderContext['is_bearer'])
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        if (
            !empty($result['append_mode'])
            && empty($result['header_only_update'])
            && (int)($result['appended_line_count'] ?? 0) > 0
        ) {
            $this->load->model('Pos_order_monitor_model');
            $this->Pos_order_monitor_model->sync_order_tasks((int)($result['id'] ?? 0));
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'order_no' => (string)($result['order_no'] ?? ''),
            'status' => 'DRAFT',
        ]);
    }

    public function order_confirm(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }

        $payload = $this->request_payload();
        $orderId = (int)($payload['id'] ?? 0);
        $orderContext = $this->mobile_draft_upsert_context($payload);
        if ($orderContext === null) {
            return;
        }
        $payload['require_active_session'] = true;
        $saved = $this->Pos_model->save_order_draft(
            $payload,
            $this->current_actor_employee_id(),
            !empty($orderContext['is_bearer'])
        );
        if (!($saved['ok'] ?? false)) {
            $this->json_error((string)($saved['message'] ?? 'Gagal menyimpan order POS.'), 422);
            return;
        }
        $savedOrderId = (int)($saved['id'] ?? 0);
        if ($savedOrderId <= 0) {
            $this->json_error('Order tersimpan tetapi ID order tidak valid.', 422);
            return;
        }

        $result = $this->confirm_mobile_order($savedOrderId, $this->current_actor_employee_id(), [
            'append_mode' => !empty($saved['append_mode']),
            'header_only_update' => !empty($saved['header_only_update']),
            'line_ids' => (array)($saved['appended_line_ids'] ?? []),
            'appended_line_count' => (int)($saved['appended_line_count'] ?? 0),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Order gagal dikonfirmasi.'), 422, $result);
            return;
        }
        $this->json_ok($result + [
            'id' => $savedOrderId,
            'order_no' => (string)($saved['order_no'] ?? ''),
        ]);
    }

    public function payment_prepare($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if ($this->mobile_financial_order_context((int)$id) === null) {
            return;
        }
        $result = $this->Pos_model->cashier_payment_prepare((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function voucher_search(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        $orderId = max(0, (int)$this->input->get('order_id', true));
        if ($this->mobile_financial_order_context($orderId) === null) {
            return;
        }
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(12, (int)$this->input->get('limit', true) ?: 8));
        $result = $this->Pos_model->search_cashier_vouchers($orderId, $this->current_actor_employee_id(), $q, $limit);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memeriksa voucher POS.'), 422, [
                'rows' => (array)($result['rows'] ?? []),
            ]);
            return;
        }
        $this->json_ok(['rows' => (array)($result['rows'] ?? [])]);
    }

    public function payment_save(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }
        $payload = $this->request_payload();
        $orderContext = $this->mobile_financial_order_context((int)($payload['order_id'] ?? 0));
        if ($orderContext === null) {
            return;
        }
        $clientEventId = trim((string)($payload['client_event_id'] ?? ''));
        $localUuid = trim((string)($payload['local_uuid'] ?? ''));
        $syncTableReady = $clientEventId !== '' && $this->db->table_exists('pos_mobile_sync_event');
        if ($syncTableReady) {
            $existing = $this->db
                ->from('pos_mobile_sync_event')
                ->where('client_event_id', $clientEventId)
                ->limit(1)
                ->get()
                ->row_array();
            if ($existing) {
                $replayContext = null;
                if (!empty($orderContext['is_bearer'])) {
                    $replayContext = $this->mobile_payment_replay_context($existing, $orderContext);
                    if ($replayContext === null) {
                        return;
                    }
                }
                $status = strtoupper(trim((string)($existing['event_status'] ?? '')));
                if ($status === 'PROCESSING') {
                    $this->json_error('Payment sedang diproses server. Jangan kirim ulang dengan event baru.', 503, [
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'PROCESSING',
                    ]);
                    return;
                }
                if ($status === 'REJECTED') {
                    $this->json_error((string)($existing['error_message'] ?? 'Payment ditolak server.'), 422, [
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'REJECTED',
                    ]);
                    return;
                }
                $stored = $replayContext !== null
                    ? (array)($replayContext['response'] ?? [])
                    : $this->decode_json_assoc((string)($existing['response_json'] ?? ''));
                $this->json_ok($stored + [
                    'duplicate' => true,
                    'client_event_id' => $clientEventId,
                ]);
                return;
            }

            $now = date('Y-m-d H:i:s');
            $this->db->insert('pos_mobile_sync_event', [
                'client_event_id' => $clientEventId,
                'local_uuid' => $localUuid !== '' ? $localUuid : 'PAY-' . $clientEventId,
                'event_type' => 'PAYMENT',
                'event_status' => 'PROCESSING',
                'request_json' => $this->encode_json($payload),
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $result = $this->Pos_model->save_cashier_payment($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            if ($syncTableReady) {
                $this->db->where('client_event_id', $clientEventId)->update('pos_mobile_sync_event', [
                    'event_status' => 'REJECTED',
                    'error_message' => (string)($result['message'] ?? 'Payment ditolak server.'),
                    'response_json' => $this->encode_json($result),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan pembayaran POS.'), 422);
            return;
        }
        $response = [
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? 'PAID'),
            'paid_now' => (float)($result['paid_now'] ?? 0),
            'entered_now' => (float)($result['entered_now'] ?? 0),
            'deposit_applied_amount' => (float)($result['deposit_applied_amount'] ?? 0),
            'change_total' => (float)($result['change_total'] ?? 0),
            'remaining_due' => (float)($result['remaining_due'] ?? 0),
            'loyalty' => (array)($result['loyalty'] ?? []),
        ];
        if ($syncTableReady) {
            $this->db->where('client_event_id', $clientEventId)->update('pos_mobile_sync_event', [
                'event_status' => 'ACCEPTED',
                'server_order_id' => (int)($payload['order_id'] ?? 0),
                'response_json' => $this->encode_json($response),
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->json_ok($response + ['client_event_id' => $clientEventId]);
    }

    public function payment_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if (!$this->require_mobile_print_document_outlet('PAYMENT', (int)$id)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_payment((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan struk POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => $this->mobile_print_targets((array)($result['targets'] ?? [])),
        ]);
    }

    public function session_status(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }

        if (is_array($this->mobileUser)) {
            $sessionContext = $this->mobile_cashier_session_context(false);
            if (empty($sessionContext['ok'])) {
                return;
            }
            $session = $sessionContext['session'];
            $this->json_ok([
                'server_time' => date('c'),
                'session' => $session,
                'active_sessions' => $session === null ? [] : [$session],
                'backup_mode' => !empty($sessionContext['backup_mode']),
                'owner_terminal_id' => (int)($sessionContext['owner_terminal_id'] ?? 0),
                'origin_terminal_id' => (int)($sessionContext['origin_terminal_id'] ?? 0),
            ]);
            return;
        }

        $this->json_ok([
            'server_time' => date('c'),
            'session' => $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id()),
            'active_sessions' => $this->Pos_model->active_cashier_sessions(),
        ]);
    }

    public function cashier_open(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }
        $payload = null;
        if (is_array($this->mobileUser)) {
            $payload = $this->request_payload();
            $boundOutletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
            $boundTerminalId = max(0, (int)($this->mobileUser['terminal_id'] ?? 0));
            $requestedOutletId = max(0, (int)($payload['outlet_id'] ?? 0));
            $requestedTerminalId = max(0, (int)($payload['terminal_id'] ?? 0));
            if (
                ($requestedOutletId > 0 && $requestedOutletId !== $boundOutletId)
                || ($requestedTerminalId > 0 && $requestedTerminalId !== $boundTerminalId)
            ) {
                $this->json_error('Sesi kasir tidak sesuai dengan perangkat.', 403);
                return;
            }
            $sessionContext = $this->mobile_cashier_session_context(false);
            if (empty($sessionContext['ok'])) {
                return;
            }
            $session = (array)($sessionContext['session'] ?? []);
            $ownerTerminalId = max(0, (int)($session['terminal_id'] ?? 0));
            $payload['outlet_id'] = $boundOutletId;
            $payload['terminal_id'] = $ownerTerminalId > 0 ? $ownerTerminalId : $boundTerminalId;
            $payload['origin_terminal_id'] = $boundTerminalId;
            $payload['mobile_backup_mode'] = !empty($sessionContext['backup_mode']);
        }
        $reconStatus = $this->Pos_model->daily_recon_gate_status('OPEN');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error((string)($reconStatus['message'] ?? 'Daily recon belum lengkap.'), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        if ($payload === null) {
            $payload = $this->request_payload();
        }
        $result = $this->Pos_model->open_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kasir POS.'), 422, [
                'code' => (string)($result['code'] ?? ''),
                'active_session' => (array)($result['active_session'] ?? []),
            ]);
            return;
        }
        $this->json_ok([
            'session' => (array)($result['session'] ?? []),
            'already_open' => !empty($result['already_open']),
            'backup_mode' => !empty($payload['mobile_backup_mode']),
            'attached_to_existing_session' => !empty($result['already_open']) && !empty($payload['mobile_backup_mode']),
        ]);
    }

    public function cashier_close_preview(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('view'), 'view')) {
            return;
        }
        if (is_array($this->mobileUser)) {
            $sessionContext = $this->mobile_cashier_session_context(true);
            if (empty($sessionContext['ok'])) {
                return;
            }
        }
        $result = $this->Pos_model->cashier_close_preview($this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview tutup kasir belum tersedia.'), 422);
            return;
        }
        $this->json_ok([
            'shift_id' => (int)($result['shift_id'] ?? 0),
            'session' => (array)($result['session'] ?? []),
            'report' => (array)($result['report'] ?? []),
        ]);
    }

    public function cashier_close(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_permission($this->mobile_order_workspace_page_code('edit'), 'edit')) {
            return;
        }
        $payload = $this->request_payload();
        if (is_array($this->mobileUser)) {
            $sessionContext = $this->mobile_cashier_session_context(true);
            if (empty($sessionContext['ok'])) {
                return;
            }
            $cashierSessionId = max(0, (int)($sessionContext['session']['id'] ?? 0));
            if (!$this->consume_mobile_cashier_close_step_up($cashierSessionId, $payload)) {
                return;
            }
            unset($payload['step_up_proof']);
        }
        $reconStatus = $this->Pos_model->daily_recon_gate_status('CLOSE');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error((string)($reconStatus['message'] ?? 'Daily recon belum lengkap.'), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        $result = $this->Pos_model->close_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menutup kasir POS.'), 422);
            return;
        }
        $shiftId = (int)($result['shift_id'] ?? 0);
        $print = $shiftId > 0
            ? $this->Pos_model->direct_print_targets_for_shift_close($shiftId, (array)($result['report'] ?? []))
            : ['targets' => []];
        $this->json_ok([
            'shift_id' => $shiftId,
            'summary' => (array)($result['summary'] ?? []),
            'report' => (array)($result['report'] ?? []),
            'direct_print_targets' => $this->mobile_print_targets((array)($print['targets'] ?? [])),
        ]);
    }

    public function orders_push(): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->mobile_order_upsert_permission_guard()) {
            return;
        }

        $payload = $this->request_payload();
        $action = !empty($payload['confirm_order'])
            ? 'edit'
            : ((int)($payload['id'] ?? 0) > 0 ? 'edit' : 'create');
        $pageCode = $this->mobile_order_workspace_page_code($action);
        if (!$this->mobile_permission($pageCode, $action)) {
            return;
        }

        $orderContext = $this->mobile_draft_upsert_context($payload);
        if ($orderContext === null) {
            return;
        }

        if (!$this->db->table_exists('pos_mobile_sync_event')) {
            $this->json_error('Schema mobile sync belum siap. Jalankan SQL 2026-08-20a_pos_mobile_sync_foundation.sql.', 503);
            return;
        }

        $clientEventId = trim((string)($payload['client_event_id'] ?? ''));
        $localUuid = trim((string)($payload['local_uuid'] ?? ''));
        if ($clientEventId === '' || $localUuid === '') {
            $this->json_error('Payload mobile wajib membawa client_event_id dan local_uuid.', 422);
            return;
        }

        $existing = $this->db
            ->from('pos_mobile_sync_event')
            ->where('client_event_id', $clientEventId)
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            $replayContext = null;
            if (!empty($orderContext['is_bearer'])) {
                $replayContext = $this->mobile_order_push_replay_context($existing, $orderContext);
                if ($replayContext === null) {
                    return;
                }
            }
            $existingStatus = strtoupper(trim((string)($existing['event_status'] ?? '')));
            if ($existingStatus === 'PROCESSING') {
                $this->json_error(
                    'Event transaksi sedang diproses server. APK akan mencoba ulang tanpa membuat order baru.',
                    503,
                    [
                        'local_uuid' => $localUuid,
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'PROCESSING',
                    ]
                );
                return;
            }
            if ($existingStatus === 'REJECTED') {
                $this->json_error(
                    (string)($existing['error_message'] ?? 'Transaksi ditolak server.'),
                    422,
                    [
                        'local_uuid' => $localUuid,
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'REJECTED',
                    ]
                );
                return;
            }
            $response = $replayContext !== null
                ? (array)($replayContext['response'] ?? [])
                : $this->decode_json_assoc((string)($existing['response_json'] ?? ''));
            $this->json_ok($response + [
                'duplicate' => true,
                'local_uuid' => $localUuid,
                'server_id' => $replayContext !== null
                    ? (int)($replayContext['server_order_id'] ?? 0)
                    : (int)($existing['server_order_id'] ?? 0),
                'status' => (string)($existing['event_status'] ?? 'ACCEPTED'),
            ]);
            return;
        }

        $actorEmployeeId = $this->current_actor_employee_id();
        if ($actorEmployeeId <= 0) {
            $this->json_error('User mobile belum terhubung ke employee. Login mobile/token employee perlu disiapkan sebelum push transaksi.', 401);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('pos_mobile_sync_event', [
            'client_event_id' => $clientEventId,
            'local_uuid' => $localUuid,
            'event_type' => 'ORDER_UPSERT',
            'event_status' => 'PROCESSING',
            'request_json' => $this->encode_json($payload),
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $eventId = (int)$this->db->insert_id();

        $orderPayload = $payload;
        $orderPayload['require_active_session'] = true;
        $result = $this->Pos_model->save_order_draft(
            $orderPayload,
            $actorEmployeeId,
            !empty($orderContext['is_bearer'])
        );
        if (!($result['ok'] ?? false)) {
            $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
                'event_status' => 'REJECTED',
                'error_message' => (string)($result['message'] ?? 'Order mobile ditolak server.'),
                'response_json' => $this->encode_json($result),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->json_error((string)($result['message'] ?? 'Order mobile ditolak server.'), 422, [
                'local_uuid' => $localUuid,
            ]);
            return;
        }

        $confirmed = !empty($payload['confirm_order']);
        $confirmResult = $confirmed
            ? $this->confirm_mobile_order((int)($result['id'] ?? 0), $actorEmployeeId, [
                'append_mode' => !empty($result['append_mode']),
                'header_only_update' => !empty($result['header_only_update']),
                'line_ids' => (array)($result['appended_line_ids'] ?? []),
                'appended_line_count' => (int)($result['appended_line_count'] ?? 0),
            ])
            : ['ok' => true, 'stock_commit_status' => 'PENDING'];
        if (!($confirmResult['ok'] ?? false)) {
            $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
                'event_status' => 'REJECTED',
                'error_message' => (string)($confirmResult['message'] ?? 'Order mobile gagal dikonfirmasi.'),
                'response_json' => $this->encode_json($confirmResult),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->json_error((string)($confirmResult['message'] ?? 'Order mobile gagal dikonfirmasi.'), 422, [
                'local_uuid' => $localUuid,
                'server_id' => (int)($result['id'] ?? 0),
            ]);
            return;
        }
        if (
            !$confirmed
            && !empty($result['append_mode'])
            && empty($result['header_only_update'])
            && (int)($result['appended_line_count'] ?? 0) > 0
        ) {
            $this->load->model('Pos_order_monitor_model');
            $this->Pos_order_monitor_model->sync_order_tasks((int)($result['id'] ?? 0));
        }

        $response = [
            'ok' => true,
            'local_uuid' => $localUuid,
            'server_id' => (int)($result['id'] ?? 0),
            'order_no' => (string)($result['order_no'] ?? ''),
            'status' => $confirmed ? 'SERVER_CONFIRMED' : 'SERVER_ACCEPTED',
            'stock_commit_status' => (string)($confirmResult['stock_commit_status'] ?? 'PENDING'),
            'confirmation' => $confirmResult,
            'result' => $result,
        ];
        $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
            'event_status' => 'ACCEPTED',
            'server_order_id' => (int)($result['id'] ?? 0),
            'response_json' => $this->encode_json($response),
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->json_ok($response);
    }

    private function confirm_mobile_order(int $orderId, int $actorEmployeeId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid.'];
        }

        $this->load->model('Pos_order_monitor_model');
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $appendMode = !empty($options['append_mode']);
        $headerOnlyUpdate = !empty($options['header_only_update']);
        $lineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        $appendedLineCount = (int)($options['appended_line_count'] ?? count($lineIds));

        if ($appendMode && $headerOnlyUpdate && empty($lineIds)) {
            return [
                'ok' => true,
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => true,
                'appended_line_count' => 0,
                'header_only_update' => true,
            ];
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'line_ids' => $lineIds,
        ]);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order POS.')];
        }

        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));
        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_order_confirmation($orderId, 0, $actorEmployeeId, 'NOT_REQUIRED');
            if (!($finalize['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($finalize['message'] ?? 'Order POS gagal difinalkan.')];
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            return [
                'ok' => true,
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => $appendMode,
                'appended_line_count' => $appendedLineCount,
                'header_only_update' => false,
                'warning_message' => $warningMessage,
            ];
        }

        $snapshot = $this->posstockcommitservice->create_snapshot(
            $orderId,
            (array)($resolved['header'] ?? []),
            (array)($resolved['lines'] ?? [])
        );
        if (!($snapshot['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit.')];
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit(
            $orderId,
            (int)$snapshot['id'],
            $actorEmployeeId,
            ['event_source' => 'ORDER_CONFIRM_MOBILE', 'event_id' => $orderId]
        );
        if (!($queued['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($queued['message'] ?? 'Queue runtime POS gagal dibuat.')];
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot stock commit gagal ditandai queued.');
            return ['ok' => false, 'message' => (string)($markQueued['message'] ?? 'Gagal menandai stock commit sebagai queued.')];
        }

        $finalize = $this->Pos_model->finalize_order_confirmation($orderId, (int)$snapshot['id'], $actorEmployeeId, 'QUEUED');
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order POS gagal difinalkan setelah queue dibuat.');
            return ['ok' => false, 'message' => (string)($finalize['message'] ?? 'Order POS gagal difinalkan.')];
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);
        // Mobile sync has no separate web request available to trigger the
        // runtime worker. Process this order's queued stock job before
        // returning so a confirmed offline transaction does not stay queued
        // indefinitely when no server worker is running.
        $processed = $this->posruntimejobservice->process_pending_jobs([
            'limit' => 1,
            'order_id' => $orderId,
            'job_id' => (int)($queued['job_id'] ?? 0),
        ]);
        $runtimeJob = [];
        if (!empty($processed['jobs'][0]['job']) && is_array($processed['jobs'][0]['job'])) {
            $runtimeJob = $processed['jobs'][0]['job'];
        }
        $runtimeStatus = strtoupper(trim((string)($runtimeJob['status'] ?? '')));
        $effectiveStockStatus = $runtimeStatus === 'SUCCESS'
            ? 'POSTED'
            : ($runtimeStatus === 'FAILED'
                ? 'FAILED'
                : ($runtimeStatus === 'PROCESSING' ? 'PROCESSING' : 'QUEUED'));
        return [
            'ok' => true,
            'snapshot_id' => (int)($snapshot['id'] ?? 0),
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'stock_commit_status' => $effectiveStockStatus,
            'runtime_processing' => [
                'ok' => !empty($processed['ok']),
                'status' => $runtimeStatus !== '' ? $runtimeStatus : 'QUEUED',
                'processed_count' => (int)($processed['processed_count'] ?? 0),
                'success_count' => (int)($processed['success_count'] ?? 0),
                'failed_count' => (int)($processed['failed_count'] ?? 0),
            ],
            'append_mode' => $appendMode,
            'appended_line_count' => $appendedLineCount,
            'header_only_update' => false,
            'warning_message' => $warningMessage,
        ];
    }

    private function verify_mobile_reservation(int $reservationId): void
    {
        $this->load->model('Pos_reservation_model');
        $this->load->model('Pos_order_monitor_model');
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $employeeId = $this->current_actor_employee_id();
        $userId = $this->current_actor_user_id();
        $prepared = $this->Pos_reservation_model->prepare_verification($reservationId, $employeeId, $userId);
        if (!($prepared['ok'] ?? false)) {
            $this->json_error((string)($prepared['message'] ?? 'Reservasi belum siap diverifikasi.'), 422);
            return;
        }
        $orderId = (int)($prepared['order_id'] ?? 0);
        $isPaid = !empty($prepared['is_paid']);
        if ($orderId <= 0) {
            $this->json_error('Order POS hasil reservasi tidak valid.', 422);
            return;
        }
        if (!empty($prepared['already_verified'])) {
            $this->json_ok([
                'id' => $orderId,
                'reservation_id' => $reservationId,
                'workspace_bucket' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
                'target_status' => $isPaid ? 'PAID' : 'CONFIRMED',
                'direct_print_targets' => [],
            ]);
            return;
        }
        if (!empty($prepared['already_pos_finalized'])) {
            $completed = $this->Pos_reservation_model->complete_verification($reservationId, $orderId, $employeeId, $isPaid, $userId);
            if (!($completed['ok'] ?? false)) {
                $this->json_error((string)($completed['message'] ?? 'Status reservasi belum dapat diselesaikan.'), 422);
                return;
            }
            $this->json_ok([
                'id' => $orderId,
                'reservation_id' => $reservationId,
                'workspace_bucket' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
                'target_status' => $isPaid ? 'PAID' : 'CONFIRMED',
                'direct_print_targets' => [],
            ]);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $employeeId, [
            'allowed_statuses' => ['PENDING'],
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit reservasi.'), 422);
            return;
        }

        $snapshotId = 0;
        $commitNo = '';
        $jobId = 0;
        $stockStatus = 'NOT_REQUIRED';
        if (!empty($resolved['lines'])) {
            $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
            if (!($snapshot['ok'] ?? false)) {
                $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stok reservasi.'), 422);
                return;
            }
            $snapshotId = (int)($snapshot['id'] ?? 0);
            $commitNo = (string)($snapshot['commit_no'] ?? '');
            $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, $snapshotId, $employeeId, [
                'event_source' => 'RESERVATION_VERIFY',
                'event_id' => $reservationId,
            ]);
            if (!($queued['ok'] ?? false)) {
                $this->json_error((string)($queued['message'] ?? 'Antrean stok reservasi gagal dibuat.'), 422, [
                    'snapshot_id' => $snapshotId,
                    'commit_no' => $commitNo,
                ]);
                return;
            }
            $jobId = (int)($queued['job_id'] ?? 0);
            $marked = $this->posstockcommitservice->mark_queued($snapshotId);
            if (!($marked['ok'] ?? false)) {
                $this->posruntimejobservice->cancel_job($jobId, 'Snapshot reservasi gagal ditandai queued.');
                $this->json_error((string)($marked['message'] ?? 'Stock commit reservasi gagal diantrekan.'), 422);
                return;
            }
            $stockStatus = 'QUEUED';
        }

        $finalize = $this->Pos_model->finalize_self_order_verification($orderId, $snapshotId, $employeeId, [
            'payment_mode' => 'RESERVATION',
            'is_paid' => $isPaid,
            'payment' => [],
            'stock_commit_status' => $stockStatus,
            'verify_destination' => $isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER',
            'allow_paid_destination' => true,
            'order_label' => 'Reservasi',
            'event_prefix' => 'RESERVATION',
        ]);
        if (!($finalize['ok'] ?? false)) {
            if ($jobId > 0) {
                $this->posruntimejobservice->cancel_job($jobId, 'Order reservasi gagal difinalkan.');
            }
            $this->json_error((string)($finalize['message'] ?? 'Order reservasi gagal difinalkan.'), 422);
            return;
        }
        $completed = $this->Pos_reservation_model->complete_verification($reservationId, $orderId, $employeeId, $isPaid, $userId);
        if (!($completed['ok'] ?? false)) {
            $this->json_error((string)($completed['message'] ?? 'Order dibuat, tetapi status reservasi belum selesai.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks($orderId);
        $targets = [];
        $kot = $this->Pos_model->direct_print_targets_for_order_confirm($orderId, $snapshotId);
        if ($kot['ok'] ?? false) {
            $targets = array_merge($targets, (array)($kot['targets'] ?? []));
        }
        $paymentId = (int)($prepared['payment_id'] ?? 0);
        if ($paymentId > 0 && $isPaid) {
            $receipt = $this->Pos_model->direct_print_targets_for_payment($paymentId, true);
            if ($receipt['ok'] ?? false) {
                $targets = array_merge($targets, (array)($receipt['targets'] ?? []));
            }
        }
        $this->json_ok([
            'id' => $orderId,
            'reservation_id' => $reservationId,
            'snapshot_id' => $snapshotId,
            'commit_no' => $commitNo,
            'runtime_job_id' => $jobId,
            'stock_commit_status' => $stockStatus,
            'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ($isPaid ? 'PAID_ORDER' : 'ACTIVE_CASHIER')),
            'target_status' => (string)($finalize['target_status'] ?? ($isPaid ? 'PAID' : 'CONFIRMED')),
            'direct_print_targets' => $this->mobile_print_targets($targets),
            'warning_message' => trim((string)($resolved['warning_message'] ?? '')),
        ]);
    }

    private function verify_mobile_incoming(int $orderId, string $channel): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $pageCode = $channel === 'DELIVERY' ? 'pos.online_food.index' : 'pos.self_order.index';
        if (!$this->mobile_permission($pageCode, 'edit')) {
            return;
        }
        if (!$this->mobile_incoming_order_outlet_allowed($orderId, $channel)) {
            return;
        }
        $contextMethod = $channel === 'DELIVERY' ? 'online_food_verification_context' : 'self_order_verification_context';
        $label = $channel === 'DELIVERY' ? 'online food' : 'self order';
        $context = $this->Pos_model->{$contextMethod}($orderId);
        if (!($context['ok'] ?? false)) {
            $this->json_error((string)($context['message'] ?? 'Order ' . $label . ' belum siap diverifikasi.'), 422);
            return;
        }
        $employeeId = $this->current_actor_employee_id();
        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $employeeId, [
            'allowed_statuses' => ['PENDING', 'PAID'],
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order ' . $label . '.'), 422);
            return;
        }
        $this->load->model('Pos_order_monitor_model');
        $snapshotId = 0;
        $jobId = 0;
        $commitNo = '';
        $stockStatus = 'NOT_REQUIRED';
        if (!empty($resolved['lines'])) {
            $this->load->library('PosStockCommitService');
            $this->load->library('PosRuntimeJobService');
            $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
            if (!($snapshot['ok'] ?? false)) {
                $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stok ' . $label . '.'), 422);
                return;
            }
            $snapshotId = (int)($snapshot['id'] ?? 0);
            $commitNo = (string)($snapshot['commit_no'] ?? '');
            $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, $snapshotId, $employeeId, [
                'event_source' => $channel === 'DELIVERY' ? 'ONLINE_FOOD_VERIFY' : 'SELF_ORDER_VERIFY',
                'event_id' => $orderId,
            ]);
            if (!($queued['ok'] ?? false)) {
                $this->json_error((string)($queued['message'] ?? 'Antrean stok gagal dibuat.'), 422, [
                    'snapshot_id' => $snapshotId,
                    'commit_no' => $commitNo,
                ]);
                return;
            }
            $jobId = (int)($queued['job_id'] ?? 0);
            $marked = $this->posstockcommitservice->mark_queued($snapshotId);
            if (!($marked['ok'] ?? false)) {
                $this->posruntimejobservice->cancel_job($jobId, 'Snapshot gagal ditandai queued.');
                $this->json_error((string)($marked['message'] ?? 'Stock commit gagal diantrekan.'), 422);
                return;
            }
            $stockStatus = 'QUEUED';
        }
        $finalize = $this->Pos_model->finalize_self_order_verification($orderId, $snapshotId, $employeeId, [
            'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
            'payment_status' => (string)($context['payment_status'] ?? 'PENDING'),
            'is_paid' => !empty($context['is_paid']),
            'payment' => (array)($context['payment'] ?? []),
            'stock_commit_status' => $stockStatus,
            'verify_destination' => trim((string)($this->request_payload()['verify_destination'] ?? '')),
            'order_label' => $channel === 'DELIVERY' ? 'Online Food' : 'Self Order',
            'event_prefix' => $channel === 'DELIVERY' ? 'ONLINE_FOOD' : 'SELF_ORDER',
        ]);
        if (!($finalize['ok'] ?? false)) {
            if ($jobId > 0) {
                $this->posruntimejobservice->cancel_job($jobId, 'Order gagal difinalkan setelah queue dibuat.');
            }
            $this->json_error((string)($finalize['message'] ?? 'Order gagal difinalkan.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks($orderId);
        $print = $this->Pos_model->direct_print_targets_for_order_confirm($orderId, $snapshotId);
        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => $snapshotId,
            'commit_no' => $commitNo,
            'runtime_job_id' => $jobId,
            'stock_commit_status' => $stockStatus,
            'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ''),
            'target_status' => (string)($finalize['target_status'] ?? ''),
            'direct_print_targets' => ($print['ok'] ?? false) ? $this->mobile_print_targets((array)($print['targets'] ?? [])) : [],
            'warning_message' => trim((string)($resolved['warning_message'] ?? '')),
        ]);
    }

    private function reject_mobile_incoming(int $orderId, string $channel): void
    {
        if (!$this->require_mobile_post()) {
            return;
        }
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $pageCode = $channel === 'DELIVERY' ? 'pos.online_food.index' : 'pos.self_order.index';
        if (!$this->mobile_permission($pageCode, 'edit')) {
            return;
        }
        if (!$this->mobile_incoming_order_outlet_allowed($orderId, $channel)) {
            return;
        }
        $payload = $this->request_payload();
        $reason = trim((string)($payload['reason'] ?? ''));
        $result = $channel === 'DELIVERY'
            ? $this->Pos_model->reject_online_food_order($orderId, $this->current_actor_employee_id(), $reason)
            : $this->Pos_model->reject_self_order_order($orderId, $this->current_actor_employee_id(), $reason);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Order tidak dapat ditolak.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    private function incoming_order_list(string $channel): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $pageCode = $channel === 'DELIVERY' ? 'pos.online_food.index' : 'pos.self_order.index';
        if (!$this->mobile_permission($pageCode, 'view')) {
            return;
        }

        if (!$this->mobile_incoming_scope_ready()) {
            return;
        }

        $session = $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id());
        $filters = $this->mobile_incoming_order_filters($session);
        $result = $channel === 'DELIVERY'
            ? $this->Pos_model->online_food_order_rows($filters)
            : $this->Pos_model->self_order_order_rows($filters);
        $this->json_ok($result + [
            'channel' => $channel,
            'server_time' => date('c'),
        ]);
    }

    private function incoming_order_detail(int $orderId, string $channel): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $pageCode = $channel === 'DELIVERY' ? 'pos.online_food.index' : 'pos.self_order.index';
        if (!$this->mobile_permission($pageCode, 'view')) {
            return;
        }

        $order = $channel === 'DELIVERY'
            ? $this->Pos_model->find_online_food_order($orderId)
            : $this->Pos_model->find_self_order_order($orderId);
        if (!$order) {
            $this->json_error($channel === 'DELIVERY'
                ? 'Order online food tidak ditemukan.'
                : 'Order self order tidak ditemukan.', 404);
            return;
        }
        if (!$this->mobile_document_outlet_allowed((int)($order['header']['outlet_id'] ?? 0))) {
            return;
        }

        $this->load->model('Pos_report_model');
        $this->json_ok($order + [
            'channel' => $channel,
            'payments' => $this->Pos_report_model->order_payment_rows($orderId),
            'refunds' => $this->Pos_report_model->order_refund_rows($orderId),
            'voids' => $this->Pos_report_model->order_void_rows($orderId),
        ]);
    }

    private function mobile_incoming_order_filters(?array $session = null): array
    {
        $statusTab = strtoupper(trim((string)$this->input->get('status_tab', true)));
        $validStatuses = ['ALL', 'NEEDS_VERIFY', 'WAITING_PAYMENT', 'ACTIVE_CASHIER', 'PAID_ORDER', 'REJECTED'];
        if (!in_array($statusTab, $validStatuses, true)) {
            $statusTab = 'ALL';
        }
        $paymentTab = strtoupper(trim((string)$this->input->get('payment_tab', true)));
        if (!in_array($paymentTab, ['ALL', 'KASIR', 'QRIS'], true)) {
            $paymentTab = 'ALL';
        }
        $outletId = is_array($this->mobileUser)
            ? max(0, (int)($this->mobileUser['outlet_id'] ?? 0))
            : max(0, (int)$this->input->get('outlet_id', true));
        if ($outletId <= 0) {
            $outletId = max(0, (int)($session['outlet_id'] ?? 0));
        }
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d');
        }
        if ($dateTo === '') {
            $dateTo = $dateFrom;
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => $outletId,
            'payment_tab' => $paymentTab,
            'status_tab' => $statusTab,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => max(1, (int)$this->input->get('page', true) ?: 1),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function mobile_incoming_scope_ready(): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }

        $outletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        if ($outletId <= 0) {
            $this->json_error('Konteks outlet perangkat tidak valid.', 403);
            return false;
        }
        $sessionContext = $this->mobile_cashier_session_context(false);
        return !empty($sessionContext['ok']);
    }

    private function mobile_incoming_order_outlet_allowed(int $orderId, string $channel): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }
        $order = $channel === 'DELIVERY'
            ? $this->Pos_model->find_online_food_order($orderId)
            : $this->Pos_model->find_self_order_order($orderId);
        if (!$order) {
            $this->json_error($channel === 'DELIVERY'
                ? 'Order online food tidak ditemukan.'
                : 'Order self order tidak ditemukan.', 404);
            return false;
        }
        return $this->mobile_document_outlet_allowed((int)($order['header']['outlet_id'] ?? 0));
    }

    private function mobile_document_outlet_allowed(int $documentOutletId): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }
        $boundOutletId = max(0, (int)($this->mobileUser['outlet_id'] ?? 0));
        if ($boundOutletId > 0 && $documentOutletId === $boundOutletId) {
            return true;
        }
        $this->json_error('Dokumen POS tidak ditemukan.', 404);
        return false;
    }

    private function mobile_reservation_outlet_allowed(int $reservationId): bool
    {
        if (!is_array($this->mobileUser)) {
            return true;
        }
        $this->load->model('Pos_reservation_model');
        $reservation = $this->Pos_reservation_model->find_reservation($reservationId);
        if (!$reservation) {
            $this->json_error('Reservasi tidak ditemukan.', 404);
            return false;
        }
        return $this->mobile_document_outlet_allowed((int)($reservation['outlet_id'] ?? 0));
    }

    private function mobile_reservation_filters(): array
    {
        $statusTab = strtoupper(trim((string)$this->input->get('status_tab', true)));
        if (!in_array($statusTab, ['ACTIVE', 'COMPLETED', 'OVERDUE', 'ALL'], true)) {
            $statusTab = 'ACTIVE';
        }
        $session = $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id());
        $outletId = is_array($this->mobileUser)
            ? max(0, (int)($this->mobileUser['outlet_id'] ?? 0))
            : max(0, (int)$this->input->get('outlet_id', true));
        if ($outletId <= 0) {
            $outletId = max(0, (int)($session['outlet_id'] ?? 0));
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => $outletId,
            'status_tab' => $statusTab,
            'date_from' => trim((string)$this->input->get('date_from', true)),
            'date_to' => trim((string)$this->input->get('date_to', true)),
            'page' => max(1, (int)$this->input->get('page', true) ?: 1),
            'limit' => max(10, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function mobile_permission(string $pageCode, string $action = 'view'): bool
    {
        $userId = $this->current_actor_user_id();
        if ($userId <= 0) {
            $this->json_error('Token mobile atau sesi login tidak tersedia.', 401);
            return false;
        }

        $permissions = $this->mobile_permissions_for_user($userId);
        if (isset($permissions['__superadmin__']) || !empty($permissions[$pageCode]['can_' . $action])) {
            return true;
        }

        $this->json_error('Anda tidak memiliki izin untuk aksi ini.', 403, [
            'page_code' => $pageCode,
            'action' => $action,
        ]);
        return false;
    }

    private function mobile_can(string $pageCode, string $action = 'view'): bool
    {
        $userId = $this->current_actor_user_id();
        if ($userId <= 0) {
            return false;
        }

        $permissions = $this->mobile_permissions_for_user($userId);
        return isset($permissions['__superadmin__'])
            || !empty($permissions[$pageCode]['can_' . $action]);
    }

    private function mobile_permissions_for_user(int $userId): array
    {
        if ($this->mobilePermissionUserId !== $userId || $this->mobilePermissions === null) {
            $this->mobilePermissions = (array)$this->Auth_model->load_permissions($userId);
            $this->mobilePermissionUserId = $userId;
        }

        return (array)$this->mobilePermissions;
    }

    private function mobile_division_scope_context(int $userId, ?array $permissions = null): ?array
    {
        if ($userId <= 0) {
            $this->json_error('Token mobile atau sesi login tidak tersedia.', 401);
            return null;
        }

        try {
            $permissions = $permissions ?? $this->mobile_permissions_for_user($userId);
            if (isset($permissions['__superadmin__'])) {
                return ['state' => 'GLOBAL', 'division_id' => null];
            }
            $scope = (array)$this->Auth_model->resolve_division_scope($userId);
        } catch (Throwable $e) {
            log_message('error', 'POS Mobile scope resolution failed for user ' . $userId . '.');
            $this->json_error('Konfigurasi akses POS belum dapat diverifikasi.', 503);
            return null;
        }

        $state = strtoupper(trim((string)($scope['state'] ?? 'NONE')));
        $divisionId = max(0, (int)($scope['division_id'] ?? 0));
        if ($state === 'GLOBAL') {
            return ['state' => 'GLOBAL', 'division_id' => null];
        }
        if ($state === 'SINGLE' && $divisionId > 0) {
            return ['state' => 'SINGLE', 'division_id' => $divisionId];
        }

        $this->json_error('Konfigurasi scope akses akun belum lengkap. Hubungi administrator.', 403);
        return null;
    }

    private function mobile_access_context_payload(): array
    {
        if (!is_array($this->mobileUser)) {
            return [];
        }

        return [
            'division_scope_state' => (string)($this->mobileUser['division_scope_state'] ?? ''),
            'division_id' => !empty($this->mobileUser['division_scope_id'])
                ? (int)$this->mobileUser['division_scope_id']
                : null,
            'outlet_id' => max(0, (int)($this->mobileUser['outlet_id'] ?? 0)),
            'terminal_id' => max(0, (int)($this->mobileUser['terminal_id'] ?? 0)),
        ];
    }

    private function mobile_order_workspace_page_code(string $ability = 'view', string $preferredPageCode = ''): string
    {
        if ($preferredPageCode !== '' && $this->mobile_can($preferredPageCode, $ability)) {
            return $preferredPageCode;
        }
        if ($this->mobile_can('pos.cashier.index', $ability)) {
            return 'pos.cashier.index';
        }
        return 'pos.order.draft.index';
    }

    private function mobile_printer_permission_page(): string
    {
        $hasRegistryPage = $this->db->table_exists('sys_page')
            && (int)$this->db->from('sys_page')->where('page_code', 'pos.printer.connection')->count_all_results() > 0;
        return $hasRegistryPage ? 'pos.printer.connection' : 'pos.printer.index';
    }

    private function mobile_printer_permission(string $action = 'view'): bool
    {
        $pageCode = $this->mobile_printer_permission_page();
        $permissionAction = $action === 'test' ? 'edit' : $action;
        if ($this->mobile_can($pageCode, $permissionAction)) {
            return true;
        }

        if (is_array($this->mobileUser)) {
            if ($action === 'view' && (
                $this->mobile_can('pos.cashier.index', 'view')
                || $this->mobile_can('pos.order.draft.index', 'view')
            )) {
                return true;
            }
            if ($action === 'test' && (
                $this->mobile_can('pos.cashier.index', 'edit')
                || $this->mobile_can('pos.order.draft.index', 'edit')
            )) {
                return true;
            }
        }

        return $this->mobile_permission($pageCode, $permissionAction);
    }

    private function mobile_redact_printer_payload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array((string)$key, ['wifi_password', 'agent_host', 'python_port'], true)) {
                unset($payload[$key]);
                continue;
            }
            if (is_array($value)) {
                $payload[$key] = $this->mobile_redact_printer_payload($value);
            }
        }
        return $payload;
    }

    private function current_actor_user_id(): int
    {
        if (is_array($this->mobileUser)) {
            return max(0, (int)($this->mobileUser['user_id'] ?? 0));
        }
        $user = $this->session->userdata('auth_user') ?: [];
        return max(0, (int)($user['id'] ?? 0));
    }

    private function authorize_mobile(bool $requireEmployee = false): bool
    {
        $token = $this->bearer_token();
        if ($token !== '') {
            $deviceKey = trim((string)$this->input->get_request_header('X-Pos-Mobile-Device-Key', true));
            if ($deviceKey === '') {
                $this->json_error('Device binding token mobile tidak valid.', 401);
                return false;
            }
            return $this->load_mobile_user_from_token($token, $deviceKey);
        }

        $expectedKey = trim((string)getenv('POS_MOBILE_API_KEY'));
        if ($expectedKey !== '') {
            $provided = trim((string)$this->input->get_request_header('X-Pos-Mobile-Key', true));
            if ($provided === '' || !hash_equals($expectedKey, $provided)) {
                $this->json_error('Mobile API key tidak valid.', 401);
                return false;
            }
            if (!$requireEmployee) {
                return true;
            }
        }

        $sessionUser = $this->session->userdata('auth_user') ?: [];
        if (empty($sessionUser)) {
            $this->json_error('Token mobile atau sesi login tidak tersedia.', 401);
            return false;
        }
        return $this->mobile_division_scope_context((int)($sessionUser['id'] ?? 0)) !== null;
    }

    private function current_actor_employee_id(): int
    {
        if (is_array($this->mobileUser)) {
            return max(0, (int)($this->mobileUser['employee_id'] ?? 0));
        }
        $user = $this->session->userdata('auth_user') ?: [];
        return max(0, (int)($user['employee_id'] ?? 0));
    }

    private function bearer_token(): string
    {
        $header = trim((string)$this->input->get_request_header('Authorization', true));
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return trim((string)$this->input->get_request_header('X-Pos-Mobile-Token', true));
    }

    private function load_mobile_user_from_token(string $token, string $deviceKey): bool
    {
        if (!$this->db->table_exists('pos_mobile_auth_token')) {
            return false;
        }

        $row = $this->db
            ->select('t.*, u.username, u.email, u.is_active')
            ->from('pos_mobile_auth_token t')
            ->join('auth_user u', 'u.id = t.user_id', 'inner')
            ->where('t.token_hash', hash('sha256', $token))
            ->where('t.revoked_at IS NULL', null, false)
            ->where('t.expires_at >=', date('Y-m-d H:i:s'))
            ->where('u.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            $this->json_error('Token mobile tidak valid atau sudah kedaluwarsa.', 401);
            return false;
        }

        $tokenDeviceKey = trim((string)($row['terminal_device_key'] ?? ''));
        if ($tokenDeviceKey === '' || !hash_equals($tokenDeviceKey, $deviceKey)) {
            $this->json_error('Device binding token mobile tidak valid.', 401);
            return false;
        }
        $terminal = $this->unique_active_mobile_terminal($deviceKey);
        if (!$terminal) {
            $this->json_error('Terminal mobile tidak terdaftar aktif atau konfigurasi device key tidak unik.', 401);
            return false;
        }

        $divisionScope = $this->mobile_division_scope_context((int)($row['user_id'] ?? 0));
        if ($divisionScope === null) {
            return false;
        }

        $row['terminal_id'] = (int)$terminal['id'];
        $row['outlet_id'] = (int)$terminal['outlet_id'];
        $row['division_scope_state'] = $divisionScope['state'];
        $row['division_scope_id'] = $divisionScope['division_id'];
        $this->mobileUser = $row;
        $this->db->where('id', (int)$row['id'])->update('pos_mobile_auth_token', [
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    private function unique_active_mobile_terminal(string $deviceKey): ?array
    {
        $deviceKey = trim($deviceKey);
        if ($deviceKey === '' || !$this->db->table_exists('pos_terminal')) {
            return null;
        }

        $rows = $this->db
            ->select('id, outlet_id, is_active')
            ->from('pos_terminal')
            ->where('device_key', $deviceKey)
            ->limit(2)
            ->get()
            ->result_array();
        if (count($rows) !== 1 || (int)($rows[0]['is_active'] ?? 0) !== 1) {
            return null;
        }
        if ((int)($rows[0]['id'] ?? 0) <= 0 || (int)($rows[0]['outlet_id'] ?? 0) <= 0) {
            return null;
        }
        return $rows[0];
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }

    /**
     * The print agent understands media markers, while Bluetooth SPP receives
     * plain thermal text. Mobile must keep the Finance layout but remove only
     * markers that cannot be rendered by the local printer.
     */
    private function mobile_print_text(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Bluetooth SPP receives plain text; remove media payload markers even
        // when a template places them beside other content on one line.
        $text = preg_replace('/\[\[(?:LOGO_URL|LOGO_BASE64|BARCODE|QRCODE)(?::.*?)?\]\]/is', '', $text) ?? $text;
        $text = preg_replace('/data:image\/[a-z0-9.+-]+;base64,[a-z0-9+\/=\r\n]+/i', '', $text) ?? $text;
        $text = rtrim($text);
        $text = preg_replace_callback('/\[\[FEED:(\d+)\]\]/i', static function (array $matches): string {
            return str_repeat("\n", min(5, max(1, (int)($matches[1] ?? 1))));
        }, $text) ?? $text;

        return $text !== '' && substr($text, -1) === "\n" ? $text : $text . "\n";
    }

    /**
     * Keep server layout order while giving Bluetooth printers structured
     * media instructions instead of leaking Finance markers as text.
     */
    private function mobile_print_segments(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $segments = [];
        foreach (preg_split('/\n/', $text) ?: [] as $line) {
            $line = (string)$line;
            if (preg_match('/^\s*\[\[LOGO_BASE64:(.*?)\]\]\s*$/is', $line, $match)) {
                $segments[] = ['type' => 'image_base64', 'data' => trim((string)$match[1])];
                continue;
            }
            if (preg_match('/^\s*\[\[LOGO_URL:(.*?)\]\]\s*$/is', $line, $match)) {
                $segments[] = ['type' => 'image_url', 'data' => trim((string)$match[1])];
                continue;
            }
            if (preg_match('/^\s*\[\[QRCODE:(.*?)\]\]\s*$/is', $line, $match)) {
                $segments[] = ['type' => 'qrcode', 'data' => trim((string)$match[1])];
                continue;
            }
            if (preg_match('/^\s*\[\[BARCODE:(.*?)\]\]\s*$/is', $line, $match)) {
                $segments[] = ['type' => 'barcode', 'data' => trim((string)$match[1])];
                continue;
            }
            if (preg_match('/^\s*\[\[FEED:(\d+)\]\]\s*$/i', $line, $match)) {
                $segments[] = ['type' => 'feed', 'lines' => min(5, max(1, (int)$match[1]))];
                continue;
            }
            $segments[] = ['type' => 'text', 'text' => $line];
        }
        return $segments;
    }

    private function mobile_print_targets(array $targets): array
    {
        $result = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }
            $rawText = (string)($target['text'] ?? '');
            $target['print_segments'] = $this->mobile_print_segments($rawText);
            $target['text'] = $this->mobile_print_text($rawText);
            $target['copies'] = max(1, min(10, (int)($target['copies'] ?? 1)));
            $target['cut_mode'] = in_array(strtoupper((string)($target['cut_mode'] ?? 'PARTIAL')), ['NONE', 'PARTIAL', 'FULL'], true)
                ? strtoupper((string)($target['cut_mode'] ?? 'PARTIAL'))
                : 'PARTIAL';
            $target['open_drawer'] = !empty($target['open_drawer']) ? 1 : 0;
            $result[] = $target;
        }
        return $result;
    }

    private function json_ok(array $data = [], int $status = 200): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['ok' => true] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function require_mobile_post(): bool
    {
        if (strtoupper((string)$this->input->method(true)) === 'POST') {
            return true;
        }

        $this->json_error('Endpoint ini hanya menerima metode POST.', 405, [
            'allowed_methods' => ['POST'],
        ]);
        return false;
    }

    private function json_error(string $message, int $status = 422, array $extra = []): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['ok' => false, 'message' => $message] + $extra, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function encode_json(array $payload): string
    {
        return json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    }

    private function decode_json_assoc(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
