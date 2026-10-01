<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth_model — Login, logout, dan load permission ke session
 */
class Auth_model extends CI_Model
{
    private const LOGIN_WINDOW_MINUTES = 15;
    private const ACCOUNT_FAILURE_LIMIT = 5;
    private const IP_FAILURE_LIMIT = 20;
    private const MAX_IDENTIFIER_BYTES = 150;
    private const MAX_DELAY_MICROSECONDS = 500000;
    private const FAILED_RESPONSE_FLOOR_MICROSECONDS = 400000;
    private const LOGIN_LOCK_TIMEOUT_SECONDS = 2;
    private const DUMMY_PASSWORD_HASH = '$2y$12$60GwSNisezGRtTiIlOVFS.OkGJDwqvK/I07tMVyc1J3b4x7YFF2/u';
    /** @var string[] Advisory locks held by the current web-login attempt. */
    private $webLoginLocks = [];

    // ---------------------------------------------------------------
    // LOGIN
    // ---------------------------------------------------------------

    /**
     * Login legacy (dua parameter) tetap dipakai POS mobile. Parameter IP
     * ketiga mengaktifkan throttle login web tanpa mengubah bentuk hasil.
     *
     * @return array|false  Data user jika cocok, false jika gagal
     */
    public function attempt_login(string $identifier, string $password, ?string $ip_address = null)
    {
        $identifier = self::normalize_login_identifier($identifier);
        $isWebLogin = $ip_address !== null;
        $ipAddress = $isWebLogin ? self::normalize_login_ip($ip_address) : '';
        $ipFailures = 0;
        $accountFailures = 0;
        $startedAt = $isWebLogin ? $this->login_clock_microseconds() : 0;
        $retainWebLocks = false;

        if ($isWebLogin && $this->webLoginLocks !== []) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }

        try {
            if ($isWebLogin) {
                $this->hold_web_login_lock(self::login_ip_lock_name($ipAddress));
                $ipFailures = $this->count_recent_ip_login_failures($ipAddress);
                if (self::is_login_throttled(0, $ipFailures, false)) {
                    return false;
                }
            }

            $row = $identifier === '' ? null : $this->find_login_candidate($identifier);
            if ($isWebLogin && $row !== null) {
                // Semua request mengambil lock IP lebih dulu, kemudian akun.
                $this->hold_web_login_lock(self::login_account_lock_name((int)$row['id']));
                $accountFailures = $this->count_recent_account_login_failures((int)$row['id']);
                if (self::is_login_throttled($accountFailures, $ipFailures, true)) {
                    return false;
                }
            }

            if (!$this->password_matches_candidate($password, $row)) {
                if ($isWebLogin) {
                    $this->record_login_failure($row === null ? null : (int)$row['id'], $ipAddress);
                }
                return false;
            }

            if ($isWebLogin) {
                // Lock dipertahankan sampai log_login() selesai agar login sukses
                // menjadi batas reset yang atomik terhadap request paralel.
                $row = $this->persist_successful_login_candidate($row, $password);
                $retainWebLocks = true;
                return $row;
            }

            return $this->persist_successful_login_candidate($row, $password);
        } finally {
            if ($isWebLogin && !$retainWebLocks) {
                $cleanupFailure = null;
                try {
                    $this->enforce_failed_login_response_floor(
                        $startedAt,
                        self::failed_login_response_floor_microseconds($accountFailures, $ipFailures)
                    );
                } catch (Throwable $e) {
                    $cleanupFailure = $e;
                }

                try {
                    $this->release_web_login_locks();
                } catch (Throwable $e) {
                    $cleanupFailure = $e;
                }

                if ($cleanupFailure !== null) {
                    throw new RuntimeException('Authentication serialization unavailable.', 0, $cleanupFailure);
                }
            }
        }
    }

    protected function persist_successful_login_candidate(array $row, string $password): array
    {
        // Cek apakah hash perlu di-rehash (cost naik)
        if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->db->where('id', $row['id']);
            $updated = $this->run_login_db_operation(function () use ($new_hash) {
                return $this->db->update('auth_user', [
                    'password_hash' => $new_hash,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            });
            if ($updated !== true) {
                throw new RuntimeException('Authentication persistence unavailable.');
            }
        }

        // Update last login
        $this->db->where('id', $row['id']);
        $updated = $this->run_login_db_operation(function () {
            return $this->db->update('auth_user', ['last_login_at' => date('Y-m-d H:i:s')]);
        });
        if ($updated !== true) {
            throw new RuntimeException('Authentication persistence unavailable.');
        }

        unset($row['password_hash']);
        return $row;
    }

    public static function normalize_login_identifier($identifier): string
    {
        if (!is_scalar($identifier)) {
            return '';
        }

        $identifier = trim((string)$identifier);
        if ($identifier === '' || strlen($identifier) > self::MAX_IDENTIFIER_BYTES) {
            return '';
        }

        return $identifier;
    }

    public static function normalize_login_ip($ip_address): string
    {
        $ipAddress = is_scalar($ip_address) ? trim((string)$ip_address) : '';
        if ($ipAddress === '' || strlen($ipAddress) > 45 || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return '0.0.0.0';
        }

        $packed = @inet_pton($ipAddress);
        $normalized = $packed === false ? false : @inet_ntop($packed);
        return is_string($normalized) && strlen($normalized) <= 45 ? $normalized : '0.0.0.0';
    }

    public static function is_login_throttled(
        int $account_failures,
        int $ip_failures,
        bool $has_account = true
    ): bool {
        return $ip_failures >= self::IP_FAILURE_LIMIT
            || ($has_account && $account_failures >= self::ACCOUNT_FAILURE_LIMIT);
    }

    public static function login_delay_microseconds(int $account_failures, int $ip_failures): int
    {
        $pressure = max($account_failures, (int)floor($ip_failures / 4));
        if ($pressure <= 0) {
            return 0;
        }

        return min(self::MAX_DELAY_MICROSECONDS, $pressure * 50000);
    }

    public static function failed_login_response_floor_microseconds(
        int $account_failures,
        int $ip_failures
    ): int {
        return max(
            self::FAILED_RESPONSE_FLOOR_MICROSECONDS,
            self::login_delay_microseconds($account_failures, $ip_failures)
        );
    }

    public static function login_ip_lock_name(string $normalized_ip): string
    {
        return 'finance:auth:ip:' . substr(hash('sha256', self::normalize_login_ip($normalized_ip)), 0, 40);
    }

    public static function login_account_lock_name(int $user_id): string
    {
        if ($user_id <= 0) {
            throw new InvalidArgumentException('Invalid authentication account lock target.');
        }

        return 'finance:auth:user:' . $user_id;
    }

    public static function account_failure_cutoff(string $window_start, ?string $latest_success_at): string
    {
        $window_start = self::login_datetime_with_microseconds($window_start);
        if ($latest_success_at === null || $latest_success_at === '') {
            return $window_start;
        }

        // Rows written before login_at became DATETIME(6) are returned without
        // a fraction. Treat them as an exact zero-microsecond boundary.
        $latest_success_at = self::login_datetime_with_microseconds($latest_success_at);
        return strcmp($latest_success_at, $window_start) > 0 ? $latest_success_at : $window_start;
    }

    private static function login_datetime_with_microseconds(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) === 1) {
            return $value . '.000000';
        }

        return $value;
    }

    /**
     * Append-only failure audit. Deliberately stores no identifier, password,
     * or user-agent.
     */
    public function record_login_failure(?int $user_id, string $ip_address): void
    {
        $inserted = $this->run_login_db_operation(function () use ($user_id, $ip_address) {
            return $this->db->insert('auth_login_failure', [
                'user_id' => $user_id !== null && $user_id > 0 ? $user_id : null,
                'ip_address' => self::normalize_login_ip($ip_address),
                'failed_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u'),
            ]);
        });

        if ($inserted !== true) {
            throw new RuntimeException('Authentication persistence unavailable.');
        }
    }

    protected function find_login_candidate(string $identifier): ?array
    {
        $query = $this->run_login_db_operation(function () use ($identifier) {
            return $this->db
                ->select('u.id, u.employee_id, u.username, u.email, u.password_hash, u.is_active')
                ->from('auth_user u')
                ->group_start()
                ->where('u.username', $identifier)
                ->or_where('u.email', $identifier)
                ->group_end()
                ->where('u.is_active', 1)
                ->limit(2)
                ->get();
        });

        if (!is_object($query) || !method_exists($query, 'result_array')) {
            throw new RuntimeException('Authentication persistence unavailable.');
        }

        $rows = $query->result_array();
        // Cross-field collision (username akun A = email akun B) ditolak agar
        // hasil autentikasi tidak bergantung pada ordering query.
        return count($rows) === 1 ? $rows[0] : null;
    }

    protected function count_recent_ip_login_failures(string $ip_address): int
    {
        $windowStart = (new DateTimeImmutable('now'))
            ->modify('-' . self::LOGIN_WINDOW_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s.u');
        $query = $this->run_login_db_operation(function () use ($ip_address, $windowStart) {
            return $this->db
                ->select('COUNT(*) AS failure_count', false)
                ->from('auth_login_failure')
                ->where('ip_address', $ip_address)
                ->where('failed_at >', $windowStart)
                ->get();
        });

        return $this->login_failure_count_from_query($query);
    }

    protected function count_recent_account_login_failures(int $user_id): int
    {
        $windowStart = (new DateTimeImmutable('now'))
            ->modify('-' . self::LOGIN_WINDOW_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s.u');
        $successQuery = $this->run_login_db_operation(function () use ($user_id) {
            return $this->db
                ->select('MAX(login_at) AS latest_success_at', false)
                ->from('auth_session_log')
                ->where('user_id', $user_id)
                ->get();
        });
        if (!is_object($successQuery) || !method_exists($successQuery, 'row_array')) {
            throw new RuntimeException('Authentication persistence unavailable.');
        }

        $successRow = $successQuery->row_array();
        $latestSuccessAt = isset($successRow['latest_success_at']) && is_string($successRow['latest_success_at'])
            ? $successRow['latest_success_at']
            : null;
        $cutoff = self::account_failure_cutoff($windowStart, $latestSuccessAt);

        $failureQuery = $this->run_login_db_operation(function () use ($user_id, $cutoff) {
            return $this->db
                ->select('COUNT(*) AS failure_count', false)
                ->from('auth_login_failure')
                ->where('user_id', $user_id)
                ->where('failed_at >', $cutoff)
                ->get();
        });

        return $this->login_failure_count_from_query($failureQuery);
    }

    protected function password_matches_candidate(string $password, ?array $candidate): bool
    {
        $hash = $candidate === null
            ? self::DUMMY_PASSWORD_HASH
            : (string)($candidate['password_hash'] ?? self::DUMMY_PASSWORD_HASH);
        $matches = password_verify($password, $hash);
        return $candidate !== null && $matches;
    }

    protected function login_clock_microseconds(): int
    {
        return (int)floor(microtime(true) * 1000000);
    }

    protected function sleep_login_microseconds(int $microseconds): void
    {
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    protected function enforce_failed_login_response_floor(int $started_at, int $floor_microseconds): void
    {
        $elapsed = max(0, $this->login_clock_microseconds() - $started_at);
        $remaining = max(0, min(self::MAX_DELAY_MICROSECONDS, $floor_microseconds) - $elapsed);
        $this->sleep_login_microseconds($remaining);
    }

    protected function acquire_named_login_lock(string $lock_name): void
    {
        if ($lock_name === '' || strlen($lock_name) > 64) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }

        $query = $this->run_login_db_operation(function () use ($lock_name) {
            return $this->db->query(
                'SELECT GET_LOCK(?, ?) AS lock_acquired',
                [$lock_name, self::LOGIN_LOCK_TIMEOUT_SECONDS]
            );
        });
        if (!is_object($query) || !method_exists($query, 'row_array')) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }

        $row = $query->row_array();
        if ((int)($row['lock_acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }
    }

    protected function release_named_login_lock(string $lock_name): void
    {
        $query = $this->run_login_db_operation(function () use ($lock_name) {
            return $this->db->query('SELECT RELEASE_LOCK(?) AS lock_released', [$lock_name]);
        });
        if (!is_object($query) || !method_exists($query, 'row_array')) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }

        $row = $query->row_array();
        if ((int)($row['lock_released'] ?? 0) !== 1) {
            throw new RuntimeException('Authentication serialization unavailable.');
        }
    }

    protected function hold_web_login_lock(string $lock_name): void
    {
        $this->acquire_named_login_lock($lock_name);
        $this->webLoginLocks[] = $lock_name;
    }

    public function release_web_login_locks(): void
    {
        $releaseFailure = null;
        while ($this->webLoginLocks !== []) {
            $lockName = array_pop($this->webLoginLocks);
            try {
                $this->release_named_login_lock($lockName);
            } catch (Throwable $e) {
                $releaseFailure = $e;
            }
        }

        if ($releaseFailure !== null) {
            throw new RuntimeException('Authentication serialization unavailable.', 0, $releaseFailure);
        }
    }

    public function cancel_web_login(): void
    {
        $this->release_web_login_locks();
    }

    private function login_failure_count_from_query($query): int
    {
        if (!is_object($query) || !method_exists($query, 'row_array')) {
            throw new RuntimeException('Authentication persistence unavailable.');
        }

        $row = $query->row_array();
        return max(0, (int)($row['failure_count'] ?? 0));
    }

    private function run_login_db_operation(callable $operation)
    {
        $canToggleDebug = is_object($this->db) && property_exists($this->db, 'db_debug');
        $previousDebug = $canToggleDebug ? $this->db->db_debug : null;
        if ($canToggleDebug) {
            $this->db->db_debug = false;
        }

        try {
            return $operation();
        } catch (Throwable $e) {
            throw new RuntimeException('Authentication persistence unavailable.', 0, $e);
        } finally {
            if ($canToggleDebug) {
                $this->db->db_debug = $previousDebug;
            }
        }
    }

    /**
     * Simpan log sesi login ke auth_session_log.
     * Kembalikan session_log_id agar bisa dipakai saat logout.
     */
    public function log_login(int $user_id, string $ip, string $user_agent = ''): int
    {
        $failure = null;
        $logId = 0;

        try {
            $inserted = $this->run_login_db_operation(function () use ($user_id, $ip, $user_agent) {
                return $this->db->insert('auth_session_log', [
                    'user_id'    => $user_id,
                    'ip_address' => self::normalize_login_ip($ip),
                    'user_agent' => substr($user_agent, 0, 255),
                    'login_at'   => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u'),
                ]);
            });
            if ($inserted !== true) {
                throw new RuntimeException('Authentication persistence unavailable.');
            }

            $logId = (int)$this->db->insert_id();
            if ($logId <= 0) {
                throw new RuntimeException('Authentication persistence unavailable.');
            }
        } catch (Throwable $e) {
            $failure = $e;
        }

        // Login web sukses mempertahankan advisory lock sampai audit sukses
        // tercatat. Jalur mobile tidak mempunyai lock sehingga tetap sama.
        try {
            $this->release_web_login_locks();
        } catch (Throwable $e) {
            $failure = $e;
        }

        if ($failure !== null) {
            throw new RuntimeException('Authentication persistence unavailable.', 0, $failure);
        }

        return $logId;
    }

    /**
     * Catat waktu logout ke auth_session_log.
     */
    public function log_logout(int $session_log_id): void
    {
        if ($session_log_id > 0) {
            $this->db->where('id', $session_log_id);
            $this->db->update('auth_session_log', ['logout_at' => date('Y-m-d H:i:s')]);
        }
    }

    // ---------------------------------------------------------------
    // LOAD PERMISSIONS
    // ---------------------------------------------------------------

    /**
     * Hitung izin final user, gabungkan semua role lalu terapkan override.
     * Hasilnya di-cache ke session agar tidak query DB tiap request.
     *
     * Return format: ['page_code' => ['can_view'=>1, 'can_create'=>0, ...], ...]
     */
    public function load_permissions(int $user_id): array
    {
        return $this->preview_permissions($user_id);
    }

    /** Read-only resolver: NULL uses live assignments; [] previews no roles. */
    public function preview_permissions(int $user_id, ?array $preview_role_ids = null, bool $include_overrides = true): array
    {
        // 1. Cek apakah user SUPERADMIN
        $is_superadmin = $this->_has_superadmin_role($user_id, $preview_role_ids);

        if ($is_superadmin) {
            return ['__superadmin__' => true];
        }

        // 2. Gabungkan izin dari semua role (OR)
        $perms = $this->_get_role_permissions($user_id, $preview_role_ids);
        if (!$include_overrides) {
            return $perms;
        }

        // 3. Terapkan override GRANT
        $grants = $this->_get_overrides($user_id, 'GRANT');
        foreach ($grants as $page_code => $flags) {
            if (!isset($perms[$page_code])) {
                $perms[$page_code] = ['can_view'=>0,'can_create'=>0,'can_edit'=>0,'can_delete'=>0,'can_export'=>0];
            }
            foreach ($flags as $k => $v) {
                if ($v) $perms[$page_code][$k] = 1;
            }
        }

        // 4. Terapkan override REVOKE
        $revokes = $this->_get_overrides($user_id, 'REVOKE');
        foreach ($revokes as $page_code => $flags) {
            if (isset($perms[$page_code])) {
                foreach ($flags as $k => $v) {
                    if ($v) $perms[$page_code][$k] = 0;
                }
            }
        }

        return $perms;
    }

    /**
     * Resolve division scope secara eksplisit.
     *
     * Role tanpa division scope adalah role global, misalnya STAFF untuk portal
     * pribadi dan aset yang memang dibuka lintas divisi. Role global tidak
     * menambah konflik pada role operasional yang sudah memiliki scope.
     *
     * Hanya scope operasional yang benar-benar memiliki division_id yang
     * dihitung. Satu division menghasilkan SINGLE; beberapa division berbeda
     * menghasilkan AMBIGUOUS; user dengan role global saja menghasilkan GLOBAL.
     * Nilai scope non-positif tetap dianggap invalid dan fail-closed.
     *
     * @return array{state:string, division_id:int|null}
     */
    public function resolve_division_scope(int $user_id): array
    {
        return $this->preview_division_scope($user_id);
    }

    /** Read-only scope preview; the live method keeps its original public signature. */
    public function preview_division_scope(int $user_id, ?array $preview_role_ids = null): array
    {
        $resolved = [
            'state'       => 'NONE',
            'division_id' => null,
        ];

        if ($user_id <= 0) {
            return $resolved;
        }

        $this->db->select('r.division_scope_id');
        if ($preview_role_ids === null) {
            $this->db->from('auth_user_role ur')
                ->join('auth_role r', 'r.id = ur.role_id')
                ->where('ur.user_id', $user_id);
        } else {
            $this->db->from('auth_role r')->where_in('r.id', $preview_role_ids ?: [0]);
        }
        $rows = $this->db->where('r.is_active', 1)
            ->get()
            ->result_array();

        $divisionIds = [];
        $hasInvalidScope = false;
        $hasActiveRole = false;

        foreach ($rows as $row) {
            $hasActiveRole = true;
            $rawDivisionId = $row['division_scope_id'] ?? null;

            // NULL means that this role is global by design. Its permissions
            // still come from the normal RBAC union, but it does not create a
            // fake division conflict when combined with a scoped role.
            if ($rawDivisionId === null || (is_string($rawDivisionId) && trim($rawDivisionId) === '')) {
                continue;
            }

            $divisionId = is_scalar($rawDivisionId)
                ? filter_var((string)$rawDivisionId, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ])
                : false;

            if ($divisionId === false) {
                $hasInvalidScope = true;
                continue;
            }

            $divisionIds[(int)$divisionId] = true;
        }

        if (count($divisionIds) === 1 && !$hasInvalidScope) {
            $resolved['state'] = 'SINGLE';
            $resolved['division_id'] = (int)array_key_first($divisionIds);
            return $resolved;
        }

        if (count($divisionIds) > 1 || ($hasInvalidScope && count($divisionIds) > 0)) {
            $resolved['state'] = 'AMBIGUOUS';
            return $resolved;
        }

        if (count($divisionIds) === 0 && !$hasInvalidScope && $hasActiveRole) {
            $resolved['state'] = 'GLOBAL';
        }

        return $resolved;
    }

    /**
     * Compatibility wrapper for callers that only need the old nullable ID.
     * GLOBAL and SUPERADMIN intentionally return NULL because their active
     * permissions are not restricted to one operational division.
     */
    public function get_division_scope(int $user_id): ?int
    {
        $resolved = $this->resolve_division_scope($user_id);
        return $resolved['state'] === 'SINGLE' ? (int)$resolved['division_id'] : null;
    }

    private function _has_superadmin_role(int $user_id, ?array $preview_role_ids = null): bool
    {
        $this->db->select('1');
        if ($preview_role_ids === null) {
            $this->db->from('auth_user_role ur');
            $this->db->join('auth_role r', 'r.id = ur.role_id');
            $this->db->where('ur.user_id', $user_id);
        } else {
            $this->db->from('auth_role r')->where_in('r.id', $preview_role_ids ?: [0]);
        }
        $this->db->where('r.role_code', 'SUPERADMIN');
        $this->db->where('r.is_active', 1);
        $this->db->limit(1);
        return (bool) $this->db->get()->num_rows();
    }

    private function _get_role_permissions(int $user_id, ?array $preview_role_ids = null): array
    {
        $this->db->select('p.page_code, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export');
        if ($preview_role_ids === null) {
            $this->db->from('auth_user_role ur');
            $this->db->join('auth_role r', 'r.id = ur.role_id');
            $this->db->join('auth_role_permission rp', 'rp.role_id = ur.role_id');
            $this->db->where('ur.user_id', $user_id);
        } else {
            $this->db->from('auth_role r')->where_in('r.id', $preview_role_ids ?: [0]);
            $this->db->join('auth_role_permission rp', 'rp.role_id = r.id');
        }
        $this->db->join('sys_page p', 'p.id = rp.page_id');
        $this->db->where('r.is_active', 1);
        $this->db->where('p.is_active', 1);
        $rows = $this->db->get()->result_array();

        $perms = [];
        foreach ($rows as $row) {
            $code = $row['page_code'];
            if (!isset($perms[$code])) {
                $perms[$code] = ['can_view'=>0,'can_create'=>0,'can_edit'=>0,'can_delete'=>0,'can_export'=>0];
            }
            // OR — jika salah satu role punya izin, user punya izin
            $perms[$code]['can_view']   = max($perms[$code]['can_view'],   (int)$row['can_view']);
            $perms[$code]['can_create'] = max($perms[$code]['can_create'], (int)$row['can_create']);
            $perms[$code]['can_edit']   = max($perms[$code]['can_edit'],   (int)$row['can_edit']);
            $perms[$code]['can_delete'] = max($perms[$code]['can_delete'], (int)$row['can_delete']);
            $perms[$code]['can_export'] = max($perms[$code]['can_export'], (int)$row['can_export']);
        }
        return $perms;
    }

    private function _get_overrides(int $user_id, string $type): array
    {
        $this->db->select('p.page_code, o.can_view, o.can_create, o.can_edit, o.can_delete, o.can_export');
        $this->db->from('auth_user_permission_override o');
        $this->db->join('sys_page p', 'p.id = o.page_id');
        $this->db->where('o.user_id', $user_id);
        $this->db->where('o.override_type', $type);
        $this->db->where('p.is_active', 1);
        $rows = $this->db->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['page_code']] = [
                'can_view'   => (int)$row['can_view'],
                'can_create' => (int)$row['can_create'],
                'can_edit'   => (int)$row['can_edit'],
                'can_delete' => (int)$row['can_delete'],
                'can_export' => (int)$row['can_export'],
            ];
        }
        return $result;
    }

    /**
     * Reload permission dari DB dan update session cache.
     * Dipanggil setelah perubahan role/override.
     */
    public function refresh_permissions(int $user_id): void
    {
        $perms = $this->load_permissions($user_id);
        $divisionScope = $this->resolve_division_scope($user_id);
        $this->session->set_userdata('user_perms', $perms);
        $this->session->set_userdata('user_perms_cached_at', time());
        $this->session->set_userdata('user_division_scope_state', $divisionScope['state']);
        $this->session->set_userdata(
            'user_division_scope',
            $divisionScope['state'] === 'SINGLE' ? $divisionScope['division_id'] : null
        );

        // Set flag is_superadmin di session user data
        $auth_user = $this->session->userdata('auth_user') ?? [];
        $auth_user['is_superadmin'] = isset($perms['__superadmin__']);
        $this->session->set_userdata('auth_user', $auth_user);
    }
}
