<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_tools extends MY_Controller
{
    private const PAGE_CODE = 'system.dbtools.settings';
    private const SENSITIVE_READ_ACTION = 'export';
    private const SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY = 'system_tools_mutation_csrf';
    private const SYSTEM_TOOLS_MUTATION_CSRF_HEADER = 'X-System-Tools-CSRF';
    private const SYSTEM_TOOLS_MUTATION_CSRF_CI_HEADER = 'X-System-Tools-Csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file');
    }

    // ── Halaman Utama — semua tab digabung ────────────────────────
    public function index()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        if (!$this->can(self::PAGE_CODE, self::SENSITIVE_READ_ACTION)) {
            $this->render_limited_page();
            return;
        }
        $systemToolsMutationCsrfToken = $this->system_tools_mutation_csrf();
        $financeRoot  = FCPATH;
        $dumpDir      = $financeRoot . 'backup/dumps/';
        $envFile      = $financeRoot . 'scripts/backup/.env';
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $recentDumps  = $this->_listRecentFiles($dumpDir, '*.sql.gz', 5);
        $recentDumps  = array_merge($recentDumps, $this->_listRecentFiles($dumpDir, '*.sql', 5));
        usort($recentDumps, fn($a, $b) => $b['mtime'] - $a['mtime']);

        $cfg = $this->visible_config();

        $replStatus     = $this->visible_replication_status($statusFile);
        $failoverActive = file_exists($failoverFile);

        $this->render('system/dbtools', [
            'title'           => 'Perlindungan Database',
            'active_menu'     => 'system.dbtools.settings',
            'cfg'             => $cfg,
            'env_exists'      => file_exists($envFile),
            'recent_dumps'    => array_slice($recentDumps, 0, 5),
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
            'is_windows'      => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
            'system_tools_mutation_csrf_token' => $systemToolsMutationCsrfToken,
        ]);
    }

    // ── Halaman Panduan Backup ─────────────────────────────────────
    public function backup_guide()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        $financeRoot = FCPATH;
        $backupDir   = $financeRoot . 'backup/dumps/';

        $recentDumps = $this->_listRecentFiles($backupDir, '*.sql.gz', 10);
        $recentDumps = array_merge($recentDumps, $this->_listRecentFiles($backupDir, '*.sql', 10));
        usort($recentDumps, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        $recentDumps = array_slice($recentDumps, 0, 10);

        $envFile = $financeRoot . 'scripts/backup/.env';
        $this->render('system/backup_guide', [
            'title'          => 'Panduan Backup DB',
            'active_menu'    => 'system.backup.guide',
            'recent_dumps'   => $recentDumps,
            'env_configured' => file_exists($envFile) && filesize($envFile) > 10,
            'finance_root'   => $financeRoot,
        ]);
    }

    // ── Halaman Panduan Replication ────────────────────────────────
    public function replication_guide()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        $financeRoot  = FCPATH;
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $replStatus     = $this->visible_replication_status($statusFile);
        $failoverActive = file_exists($failoverFile);

        $this->render('system/replication_guide', [
            'title'           => 'Panduan Replication & Failover',
            'active_menu'     => 'system.replication.guide',
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
        ]);
    }

    // ── Settings page ──────────────────────────────────────────────
    public function settings()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        if (!$this->can(self::PAGE_CODE, self::SENSITIVE_READ_ACTION)) {
            $this->render_limited_page();
            return;
        }
        $systemToolsMutationCsrfToken = $this->system_tools_mutation_csrf();

        $financeRoot = FCPATH;
        $envFile     = $financeRoot . 'scripts/backup/.env';
        $envExists   = file_exists($envFile);

        // Hanya field operasional yang dikenal. Password tidak pernah dikirim ke view.
        $cfg = $this->visible_config();

        // Status file
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';
        $dumpDir      = $financeRoot . 'backup/dumps/';
        $recentDumps  = $this->_listRecentFiles($dumpDir, '*.sql.gz', 5);
        $recentDumps  = array_merge($recentDumps, $this->_listRecentFiles($dumpDir, '*.sql', 5));
        usort($recentDumps, fn($a, $b) => $b['mtime'] - $a['mtime']);

        $replStatus   = $this->visible_replication_status($statusFile);
        $failoverActive = file_exists($failoverFile);

        $this->render('system/settings', [
            'title'           => 'DB Tools — Pengaturan',
            'active_menu'     => 'system.dbtools.settings',
            'cfg'             => $cfg,
            'env_exists'      => $envExists,
            'recent_dumps'    => $recentDumps,
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
            'is_windows'      => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
            'system_tools_mutation_csrf_token' => $systemToolsMutationCsrfToken,
        ]);
    }

    // ── Save settings ──────────────────────────────────────────────
    public function settings_save()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $payload = $this->request_payload();

        $allowed = [
            'backup.db_host', 'backup.db_port', 'backup.db_user', 'backup.db_pass',
            'backup.db_name', 'backup.retention_days', 'backup.exclude_tables',
            'repl.server_role', 'repl.master_host', 'repl.master_port',
            'repl.repl_user', 'repl.repl_pass',
            'tunnel.enabled', 'tunnel.ssh_host', 'tunnel.ssh_port', 'tunnel.ssh_user',
            'tunnel.local_port', 'tunnel.remote_port',
        ];

        $group_map = [
            'backup.' => 'backup',
            'repl.'   => 'replication',
            'tunnel.' => 'tunnel',
        ];

        // Field password: jika dikirim kosong = "tidak diubah", jangan timpa nilai lama
        $skipIfEmpty = ['backup.db_pass', 'repl.repl_pass'];

        $this->db->trans_begin();
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $payload)) continue;
            if (in_array($key, $skipIfEmpty, true) && $payload[$key] === '') continue;
            $group = 'general';
            foreach ($group_map as $prefix => $g) {
                if (str_starts_with($key, $prefix)) { $group = $g; break; }
            }
            $this->db->replace('sys_app_config', [
                'config_group' => $group,
                'config_key'   => $key,
                'config_value' => (string)($payload[$key] ?? ''),
                'updated_by'   => (int)($this->current_user['id'] ?? 0),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->trans_commit();

        // Generate .env file dari config yang tersimpan; never hide a filesystem failure.
        if (!$this->_writeEnvFile()) {
            $this->json_error(
                'Pengaturan database tersimpan, tetapi file scripts/backup/.env tidak dapat ditulis. Siapkan izin tulis khusus pada file .env untuk user PHP-FPM, lalu simpan ulang.',
                500
            );
            return;
        }

        $this->json_ok(['message' => 'Pengaturan berhasil disimpan dan .env diperbarui.']);
    }

    // ── Aksi: List Tables ─────────────────────────────────────────
    public function action_list_tables()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);

        // Coba gunakan DB dari config yang tersimpan (bisa beda dari DB app)
        $host = $this->_cfg('backup.db_host', 'localhost');
        $port = (int)$this->_cfg('backup.db_port', '3306');
        $user = $this->_cfg('backup.db_user', 'root');
        $pass = $this->_cfg('backup.db_pass', '');
        $name = $this->_cfg('backup.db_name', 'db_finance');

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $tables = $pdo->query("SHOW TABLES FROM `{$name}`")->fetchAll(PDO::FETCH_COLUMN);

            // Estimasi ukuran per tabel
            $sizes = [];
            $sizeRows = $pdo->query(
                "SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 1) AS size_mb,
                        TABLE_ROWS AS est_rows
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = " . $pdo->quote($name) . "
                 ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sizeRows as $r) {
                $sizes[$r['TABLE_NAME']] = ['size_mb' => (float)$r['size_mb'], 'est_rows' => (int)$r['est_rows']];
            }

            $result = array_map(function($t) use ($sizes) {
                return [
                    'name'     => $t,
                    'size_mb'  => $sizes[$t]['size_mb'] ?? 0,
                    'est_rows' => $sizes[$t]['est_rows'] ?? 0,
                ];
            }, $tables);

            $this->json_ok(['tables' => $result, 'db' => $name]);
        } catch (Exception $e) {
            log_message('error', 'System Tools table inspection failed.');
            $this->json_error('Gagal memuat daftar tabel. Periksa konfigurasi server.', 422);
        }
    }

    // ── Aksi: Run Backup Now ───────────────────────────────────────
    public function action_run_backup()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $result = $this->_runScript('backup', 'backup_full');
        if ($result['ok']) {
            $this->json_ok(['output' => $result['output'], 'message' => 'Backup berhasil dijalankan.']);
        } else {
            $this->json_error($result['output'] ?: 'Backup gagal. Cek permission script dan konfigurasi .env.', 500);
        }
    }

    // ── Aksi: Test DB Connection ───────────────────────────────────
    public function action_test_db()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $payload = $this->request_payload();
        $host = trim((string)($payload['host'] ?? '')) ?: $this->_cfg('backup.db_host', 'localhost');
        $port = (int)($payload['port'] ?? $this->_cfg('backup.db_port', '3306'));
        $user = trim((string)($payload['user'] ?? '')) ?: $this->_cfg('backup.db_user', 'root');
        $pass = (string)($payload['pass'] ?? '');
        if ($pass === '') $pass = $this->_cfg('backup.db_pass', '');
        $name = trim((string)($payload['name'] ?? '')) ?: $this->_cfg('backup.db_name', 'db_finance');
        if (strlen($host) > 255 || preg_match('/[;\x00-\x20\x7F]/', $host) === 1
            || $port < 1 || $port > 65535 || strlen($user) > 128
            || preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name) !== 1) {
            $this->json_error('Parameter koneksi database tidak valid.', 422);
            return;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            $this->json_ok(['message' => "Koneksi berhasil! MySQL versi: {$ver}"]);
        } catch (Exception $e) {
            log_message('error', 'System Tools database connection test failed.');
            $this->json_error('Koneksi gagal. Periksa host, port, nama database, dan credential.', 422);
        }
    }

    // ── Aksi: Terapkan konfigurasi MySQL (SET GLOBAL + conf.d) ──
    public function action_apply_mysql_config()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $payload  = $this->request_payload();
        $role     = strtoupper(trim((string)($payload['role'] ?? 'MASTER')));
        $serverId = (int)($payload['server_id'] ?? ($role === 'MASTER' ? 1 : 2));

        if (!in_array($role, ['MASTER', 'SLAVE'], true)) {
            $this->json_error('Role tidak valid.', 422); return;
        }

        $applied = [];
        $failed  = [];

        // Nonaktifkan db_debug agar error SQL tidak di-output sebagai HTML (CI3 quirk)
        $origDebug          = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        // Deteksi MariaDB — beberapa variabel/perintah MySQL tidak tersedia di MariaDB
        $verRow    = $this->db->query("SELECT VERSION() AS v")->row_array();
        $isMariaDB = stripos($verRow['v'] ?? '', 'mariadb') !== false;

        // Terapkan via SET GLOBAL (langsung efektif, tidak butuh restart)
        $offset  = $role === 'MASTER' ? 1 : 2;
        $globals = [
            "SET GLOBAL server_id               = {$serverId}",
            "SET GLOBAL binlog_format           = 'ROW'",
            "SET GLOBAL auto_increment_offset   = {$offset}",
            "SET GLOBAL auto_increment_increment = 2",
        ];
        if ($role === 'SLAVE') {
            $globals[] = "SET GLOBAL read_only = ON";
            // super_read_only hanya ada di MySQL 5.7+ — tidak ada di MariaDB
            if (!$isMariaDB) {
                $globals[] = "SET GLOBAL super_read_only = ON";
            }
        }
        foreach ($globals as $sql) {
            $r = $this->db->query($sql);
            if ($r === FALSE) {
                $err      = $this->db->error();
                $failed[] = ['sql' => $sql, 'error' => $err['message'] ?? 'unknown'];
            } else {
                $applied[] = $sql;
            }
        }

        // Cek apakah binary logging sudah aktif
        $logBinRow = $this->db->query("SHOW VARIABLES LIKE 'log_bin'")->row_array();
        $binlogOn  = strtoupper($logBinRow['Value'] ?? 'OFF') === 'ON';

        // Khusus SLAVE MySQL: coba exclude sys_app_config (tidak didukung MariaDB)
        if ($role === 'SLAVE' && !$isMariaDB) {
            $dbName    = $this->db->database;
            $sqlFilter = "CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE = (`{$dbName}`.`sys_app_config`)";
            $r         = $this->db->query($sqlFilter);
            if ($r === FALSE) {
                $err      = $this->db->error();
                $failed[] = ['sql' => 'CHANGE REPLICATION FILTER (opsional)', 'error' => $err['message'] ?? 'Butuh SUPER/BINLOG MONITOR — sudah ditulis ke conf.d'];
            } else {
                $applied[] = $sqlFilter;
            }
        }

        $this->db->db_debug = $origDebug;

        // Snippet konfigurasi untuk my.cnf
        $readOnly    = $role === 'SLAVE' ? "\nread_only                = ON" . (!$isMariaDB ? "\nsuper_read_only          = ON" : '') : '';
        $ignoreTable = ($role === 'SLAVE' && !$isMariaDB) ? "\nreplicate-ignore-table   = " . $this->db->database . ".sys_app_config" : '';
        $snippet     = "[mysqld]\nserver-id                = {$serverId}\nlog_bin                  = mysql-bin\nbinlog_format            = ROW{$readOnly}\nauto_increment_offset    = {$offset}\nauto_increment_increment = 2{$ignoreTable}";

        // Coba tulis ke conf.d (agar permanen / survive restart)
        $confDirs    = ['/etc/mysql/conf.d', '/etc/mysql/mysql.conf.d', '/etc/my.cnf.d'];
        $confWritten = false;
        $confPath    = '';
        foreach ($confDirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                $confPath    = $dir . '/finance-replication.cnf';
                $confWritten = file_put_contents($confPath, $snippet . "\n") !== false;
                break;
            }
        }

        // Slave tidak butuh log_bin untuk replikasi dasar — hanya master yang wajib
        $needsRestart = !$binlogOn && $role === 'MASTER';

        $this->json_ok([
            'applied'      => $applied,
            'failed'       => $failed,
            'binlog_on'    => $binlogOn,
            'conf_written' => $confWritten,
            'conf_path'    => $confPath,
            'snippet'      => $snippet,
            'role'         => $role,
            'needs_restart'=> $needsRestart,
        ]);
    }

    // ── Aksi: Setup Master (buat replication user) ───────────────
    public function action_setup_master()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $payload  = $this->request_payload();
        $replUser = trim((string)($payload['repl_user'] ?? $this->_cfg('repl.repl_user', 'repl_user')));
        $replPass = (string)($payload['repl_pass'] ?? $this->_cfg('repl.repl_pass', ''));

        if ($replUser === '') { $this->json_error('Username replikasi tidak boleh kosong.', 422); return; }
        if ($replPass === '') { $this->json_error('Password replikasi tidak boleh kosong.', 422); return; }

        try {
            $u = $this->db->escape_str($replUser);
            $p = $this->db->escape_str($replPass);
            $this->db->query("CREATE USER IF NOT EXISTS '{$u}'@'%' IDENTIFIED BY '{$p}'");
            $this->db->query("ALTER USER '{$u}'@'%' IDENTIFIED BY '{$p}'");
            // REPLICATION CLIENT diperlukan untuk SHOW MASTER STATUS dari sisi slave
            $this->db->query("GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO '{$u}'@'%'");
            $this->db->query("FLUSH PRIVILEGES");
            $master = $this->db->query('SHOW MASTER STATUS')->row_array() ?: [];
            $this->json_ok([
                'message'  => "User '{$replUser}'@'%' berhasil dibuat dan diberi hak REPLICATION SLAVE.",
                'binlog'   => $master['File']     ?? '-',
                'position' => (int)($master['Position'] ?? 0),
            ]);
        } catch (Exception $e) {
            $this->json_error('Gagal setup master: ' . $e->getMessage(), 500);
        }
    }

    // ── Aksi: Check Replication Status ────────────────────────────
    public function action_check_replication()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        $role = strtoupper($this->_cfg('repl.server_role', 'STANDALONE'));

        $data = ['role' => $role, 'timestamp' => date('Y-m-d H:i:s')];

        if ($role === 'SLAVE') {
            try {
                $slaveStatus = $this->db->query('SHOW SLAVE STATUS')->row_array();
                if ($slaveStatus) {
                    $ioOk  = $slaveStatus['Slave_IO_Running']  === 'Yes';
                    $sqlOk = $slaveStatus['Slave_SQL_Running'] === 'Yes';
                    $data['status']         = ($ioOk && $sqlOk) ? 'OK' : 'ERROR';
                    $data['io_running']     = $slaveStatus['Slave_IO_Running'];
                    $data['sql_running']    = $slaveStatus['Slave_SQL_Running'];
                    $data['lag_seconds']    = (int)($slaveStatus['Seconds_Behind_Master'] ?? 0);
                    $data['master_host']    = $slaveStatus['Master_Host'] ?? '';
                    $data['last_error']     = $slaveStatus['Last_SQL_Error']  ?? '';
                    $data['last_io_error']  = $slaveStatus['Last_IO_Error']   ?? '';
                    // error = pesan utama untuk ditampilkan di UI
                    $data['error'] = $data['last_io_error'] ?: $data['last_error'];
                } else {
                    $data['status'] = 'NOT_CONFIGURED';
                }
            } catch (Exception $e) {
                $data['status'] = 'ERROR';
                $data['error']  = $e->getMessage();
            }
        } elseif ($role === 'MASTER') {
            try {
                $masterStatus = $this->db->query('SHOW MASTER STATUS')->row_array();
                $data['status']   = 'OK';
                $data['binlog']   = $masterStatus['File'] ?? '-';
                $data['position'] = $masterStatus['Position'] ?? 0;
            } catch (Exception $e) {
                $data['status'] = 'ERROR';
                $data['error']  = $e->getMessage();
            }
        } else {
            $data['status'] = 'STANDALONE';
        }

        // Simpan ke file untuk health check
        $statusFile = FCPATH . 'backup/logs/replication_status.json';
        @mkdir(dirname($statusFile), 0755, true);
        file_put_contents($statusFile, json_encode($data));

        $this->json_ok($data);
    }

    // ── Aksi: Sinkronisasi Data Awal (slave ← master) ────────────
    public function action_initial_sync()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        @set_time_limit(300);

        $tunnelOn   = $this->_cfg('tunnel.enabled', '0') === '1';
        $masterHost = $tunnelOn ? '127.0.0.1' : $this->_cfg('repl.master_host', '');
        $masterPort = $tunnelOn
            ? (int)$this->_cfg('tunnel.local_port', '3307')
            : (int)$this->_cfg('repl.master_port', '3306');
        $syncUser   = $this->_cfg('backup.db_user', 'root');
        $syncPass   = $this->_cfg('backup.db_pass', '');
        $dbName     = $this->db->database;

        if ($tunnelOn && $this->_cfg('tunnel.ssh_host', '') === '') {
            $this->json_error('SSH host tunnel belum dikonfigurasi.', 422); return;
        }
        if (empty($masterHost)) {
            $this->json_error('Alamat server utama belum dikonfigurasi.', 422); return;
        }

        // Tabel yang dikecualikan: dari config + sys_app_config selalu dikecualikan
        $cfgExclude  = array_filter(array_map('trim', explode(',', $this->_cfg('backup.exclude_tables', ''))));
        $excludeSet  = array_unique(array_merge($cfgExclude, ['sys_app_config']));

        $payload = $this->request_payload();

        try {
            // Satu koneksi PDO untuk semua tabel — hindari overhead SSH handshake per tabel
            $pdo = new PDO(
                "mysql:host={$masterHost};port={$masterPort};dbname={$dbName};charset=utf8mb4",
                $syncUser, $syncPass,
                [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $requestedTables = array_values(array_filter(array_map('trim', (array)($payload['tables'] ?? []))));
            $isFullSync      = empty($requestedTables);
            $tables          = $isFullSync
                ? $pdo->query("SHOW TABLES FROM `{$dbName}`")->fetchAll(PDO::FETCH_COLUMN)
                : $requestedTables;

            $synced  = $skipped = $errors = $remaining = [];
            $timeLimit  = 25; // detik per request — JS akan panggil ulang untuk sisa
            $startedAt  = time();

            // Nonaktifkan read_only sementara (STOP SLAVE tidak diperlukan untuk INSERT IGNORE incremental)
            $this->db->db_debug = FALSE;
            $roRow = $this->db->query("SELECT @@global.read_only AS ro")->row_array();
            $wasReadOnly = !empty($roRow['ro']);
            if ($wasReadOnly) { $this->db->query("SET GLOBAL read_only = OFF"); }
            $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->db->db_debug = TRUE;

            $isWin     = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            // Test exec() benar-benar bisa jalan (bukan hanya function_exists)
            $execAvail = false;
            if (function_exists('exec') && stripos(ini_get('disable_functions') . ',', 'exec,') === false) {
                @exec('echo test', $testOut, $testCode);
                $execAvail = ($testCode === 0 && !empty($testOut));
            }
            $method = $execAvail ? 'mysqldump (fast)' : 'PHP PDO (slow — exec() disabled)';
            $localUser = $this->db->username;
            $localPass = $this->db->password;

            foreach ($tables as $table) {
                if (in_array($table, $excludeSet, true)) {
                    $skipped[] = $table; continue;
                }
                // Waktu habis → simpan sisanya, return partial (JS panggil ulang)
                if ((time() - $startedAt) >= $timeLimit) {
                    $remaining[] = $table; continue;
                }

                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$dbName}`.`{$table}`")->fetchColumn();

                $slaveCount = (int)($this->db->query("SELECT COUNT(*) FROM `{$table}`")->row_array()['COUNT(*)'] ?? 0);
                $diff       = $count - $slaveCount;

                if ($diff === 0) { $skipped[] = "{$table} (sudah sama)"; continue; }

                try {
                    // ── Coba incremental: hanya tarik baris yang kurang via PK ──
                    if ($diff > 0 && $slaveCount > 0) {
                        $inc = $this->_syncIncremental($table, $pdo, $dbName);
                        if ($inc !== null) {
                            $synced[] = "{$table} (+{$inc} baris, incremental)";
                            continue;
                        }
                    }

                    // ── Full sync: slave kosong atau diff terlalu besar ──────
                    $bigDiff = $slaveCount === 0 || abs($diff) > ($count * 0.5);
                    if ($execAvail) {
                        $err = $this->_syncViaDump(
                            $table, $masterHost, $masterPort, $syncUser, $syncPass,
                            $localUser, $localPass, $dbName, $isWin, $bigDiff
                        );
                        if ($err !== '') { $errors[] = "{$table}: {$err}"; }
                        else             { $synced[] = "{$table} ({$count} baris, full)"; }
                    } else {
                        if ($count > 50000) { $errors[] = "{$table}: {$count} baris — sync manual"; continue; }
                        $rows = $pdo->query("SELECT * FROM `{$dbName}`.`{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                        if ($bigDiff) { $this->db->query("TRUNCATE TABLE `{$table}`"); }
                        if (!empty($rows)) {
                            $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($rows[0])));
                            $this->db->trans_begin();
                            foreach (array_chunk($rows, 1000) as $chunk) {
                                $vals = array_map(fn($row) =>
                                    '(' . implode(', ', array_map(
                                        fn($v) => $v === null ? 'NULL' : $this->db->escape($v), $row
                                    )) . ')', $chunk);
                                $this->db->query("REPLACE INTO `{$table}` ({$cols}) VALUES " . implode(',', $vals));
                            }
                            $this->db->trans_commit();
                        }
                        $synced[] = "{$table} ({$count} baris, full)";
                    }
                } catch (Exception $e) {
                    $errors[] = "{$table}: " . $e->getMessage();
                }
            }

            $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
            if ($wasReadOnly) { $this->db->query("SET GLOBAL read_only = ON"); }

            // Buat perintah alternatif di server (tanpa tunnel — jauh lebih cepat untuk tabel besar)
            $unsyncedTables = array_filter($tables, function($t) use ($synced, $skipped) {
                foreach ($synced as $s) { if (str_starts_with($s, $t . ' ')) return false; }
                return !in_array($t, $skipped);
            });
            $serverCmd = '';
            if (!empty($unsyncedTables)) {
                $tblList   = implode(' ', array_values($unsyncedTables));
                $serverCmd = "# Jalankan di SERVER (lebih cepat, tanpa tunnel):\n"
                           . "mysqldump -u root -p --single-transaction --no-tablespaces {$dbName} {$tblList} | gzip > /tmp/sync_partial.sql.gz\n\n"
                           . "# Download file lalu import di laptop:\n"
                           . "gunzip -c sync_partial.sql.gz | \"C:\\xampp\\mysql\\bin\\mysql.exe\" -u root -p {$dbName}";
            }

            // Safeguard: remaining hanya boleh berisi tabel dari request ini (cegah infinite loop)
            $inputSet  = array_flip(array_values($tables));
            $remaining = array_values(array_filter($remaining, fn($t) => isset($inputSet[$t])));

            $this->json_ok([
                'message'    => count($remaining) > 0
                    ? count($synced) . ' tabel selesai, ' . count($remaining) . ' dilanjutkan...'
                    : 'Sinkronisasi selesai: ' . count($synced) . ' tabel. (metode: ' . $method . ')',
                'synced'     => $synced,
                'skipped'    => $skipped,
                'errors'     => $errors,
                'remaining'  => $remaining,
                'server_cmd' => $serverCmd,
            ]);
        } catch (Exception $e) {
            @$this->db->query("SET FOREIGN_KEY_CHECKS = 1");
            if (!empty($wasReadOnly)) { @$this->db->query("SET GLOBAL read_only = ON"); }
            @$this->db->query("START SLAVE");
            $this->json_error('Gagal sinkronisasi: ' . $e->getMessage(), 500);
        }
    }

    // ── Helper: incremental sync — hanya baris yang kurang (via PK) ─
    private function _syncIncremental(string $table, PDO $pdo, string $dbName): ?int
    {
        // Cari primary key via INFORMATION_SCHEMA (kompatibel MySQL & MariaDB)
        $pkRows = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
               AND TABLE_NAME   = " . $pdo->quote($table)  . "
               AND CONSTRAINT_NAME = 'PRIMARY'
             ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (count($pkRows) !== 1) return null; // composite PK → fallback full sync

        $pkCol = $pkRows[0];
        $slaveMaxRow = $this->db->query("SELECT MAX(`{$pkCol}`) AS m FROM `{$table}`")->row_array();
        $slaveMax    = $slaveMaxRow['m'] ?? null;

        if ($slaveMax === null) return null; // slave kosong → perlu full sync

        // Ambil hanya baris yang belum ada di slave (PK > max slave)
        $stmt = $pdo->prepare("SELECT * FROM `{$dbName}`.`{$table}` WHERE `{$pkCol}` > ? ORDER BY `{$pkCol}`");
        $stmt->execute([$slaveMax]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return 0; // tidak ada yang kurang dari sisi PK

        $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($rows[0])));
        $origDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        foreach (array_chunk($rows, 500) as $chunk) {
            $vals = array_map(fn($row) =>
                '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $this->db->escape($v), $row)) . ')',
                $chunk
            );
            $this->db->query("INSERT IGNORE INTO `{$table}` ({$cols}) VALUES " . implode(',', $vals));
        }
        $this->db->trans_commit();
        $this->db->db_debug = $origDebug;

        return count($rows);
    }

    // ── Helper: sync satu tabel via mysqldump ────────────────────
    private function _syncViaDump(
        string $table, string $host, int $port,
        string $remoteUser, string $remotePass,
        string $localUser,  string $localPass,
        string $db, bool $isWin, bool $truncate = true
    ): string {
        $dumpBin  = $isWin ? $this->_findMysqlBin('mysqldump', $isWin) : 'mysqldump';
        $mysqlBin = $isWin ? $this->_findMysqlBin('mysql',     $isWin) : 'mysql';

        // --replace: REPLACE INTO (update baris yg ada + insert yg baru)
        // --no-create-info: jangan drop/recreate tabel
        $dumpOpts = "--no-create-info --replace --single-transaction --no-tablespaces --skip-lock-tables --compress";

        if ($isWin) {
            // Windows: dump ke temp file dulu (pipe antar process bermasalah)
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'finsync_' . md5($table) . '.sql';
            putenv("MYSQL_PWD={$remotePass}");
            exec("\"{$dumpBin}\" {$dumpOpts} -h {$host} -P {$port} -u {$remoteUser} {$db} {$table} > \"{$tmp}\" 2>&1", $o, $c1);
            putenv("MYSQL_PWD=");
            if ($c1 !== 0) { @unlink($tmp); return "dump gagal: " . implode(' ', $o); }
            if ($truncate) { $this->db->query("TRUNCATE TABLE `{$table}`"); }
            putenv("MYSQL_PWD={$localPass}");
            exec("\"{$mysqlBin}\" -u {$localUser} {$db} < \"{$tmp}\" 2>&1", $o2, $c2);
            putenv("MYSQL_PWD=");
            @unlink($tmp);
            return $c2 === 0 ? '' : "import gagal: " . implode(' ', $o2);
        } else {
            // Linux/Mac: pipe langsung
            if ($truncate) { $this->db->query("TRUNCATE TABLE `{$table}`"); }
            $cmd = sprintf(
                'MYSQL_PWD=%s %s %s -h %s -P %d -u %s %s %s | MYSQL_PWD=%s %s -u %s %s 2>&1',
                escapeshellarg($remotePass), $dumpBin, $dumpOpts,
                escapeshellarg($host), $port, escapeshellarg($remoteUser),
                escapeshellarg($db), escapeshellarg($table),
                escapeshellarg($localPass), $mysqlBin,
                escapeshellarg($localUser), escapeshellarg($db)
            );
            exec($cmd, $out, $code);
            return $code === 0 ? '' : implode("\n", $out);
        }
    }

    private function _findMysqlBin(string $bin, bool $isWin): string
    {
        $candidates = $isWin
            ? ["C:\\xampp\\mysql\\bin\\{$bin}.exe", "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\{$bin}.exe"]
            : ["/usr/bin/{$bin}", "/usr/local/bin/{$bin}"];
        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        return $bin; // fallback ke PATH
    }

    // ── Aksi: Bandingkan data master vs slave ────────────────────
    public function action_compare_data()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        @set_time_limit(120);

        $tunnelOn   = $this->_cfg('tunnel.enabled', '0') === '1';
        $masterHost = $tunnelOn ? '127.0.0.1' : $this->_cfg('repl.master_host', '');
        $masterPort = $tunnelOn
            ? (int)$this->_cfg('tunnel.local_port', '3307')
            : (int)$this->_cfg('repl.master_port', '3306');
        $syncUser   = $this->_cfg('backup.db_user', 'root');
        $syncPass   = $this->_cfg('backup.db_pass', '');
        $dbName     = $this->db->database;

        $cfgExclude = array_filter(array_map('trim', explode(',', $this->_cfg('backup.exclude_tables', ''))));
        $excludeSet = array_unique(array_merge($cfgExclude, ['sys_app_config']));

        if ($tunnelOn && $this->_cfg('tunnel.ssh_host', '') === '') {
            $this->json_error('SSH host tunnel belum dikonfigurasi.', 422); return;
        }
        if (empty($masterHost)) {
            $this->json_error('Alamat server utama belum dikonfigurasi.', 422); return;
        }

        try {
            $pdo = new PDO(
                "mysql:host={$masterHost};port={$masterPort};dbname={$dbName};charset=utf8mb4",
                $syncUser, $syncPass,
                [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $masterTables = $pdo->query("SHOW TABLES FROM `{$dbName}`")->fetchAll(PDO::FETCH_COLUMN);
            $slaveRows    = $this->db->query("SHOW TABLES FROM `{$dbName}`")->result_array();
            $slaveTables  = array_column($slaveRows, 'Tables_in_' . $dbName);

            $results = [];
            foreach ($masterTables as $table) {
                if (in_array($table, $excludeSet, true)) continue;
                $masterCount = (int)$pdo->query("SELECT COUNT(*) FROM `{$dbName}`.`{$table}`")->fetchColumn();
                if (in_array($table, $slaveTables, true)) {
                    $slaveCount = (int)($this->db->query("SELECT COUNT(*) FROM `{$table}`")->row_array()['COUNT(*)'] ?? 0);
                    $match      = $masterCount === $slaveCount;
                } else {
                    $slaveCount = null;
                    $match      = false;
                }
                $results[] = ['table' => $table, 'master' => $masterCount, 'slave' => $slaveCount, 'match' => $match];
            }

            $ok   = count(array_filter($results, fn($r) => $r['match']));
            $diff = count($results) - $ok;

            $this->json_ok(['results' => $results, 'ok' => $ok, 'diff' => $diff, 'total' => count($results)]);
        } catch (Exception $e) {
            $this->json_error('Gagal membandingkan data: ' . $e->getMessage(), 500);
        }
    }

    // ── Aksi: Failover ────────────────────────────────────────────
    public function action_failover()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $confirm = (string)($this->request_payload()['confirm'] ?? '');
        if ($confirm !== 'YES_FAILOVER') {
            $this->json_error('Konfirmasi tidak valid.', 422);
            return;
        }

        // Jalankan via SQL langsung (lebih safe dari shell_exec untuk failover)
        try {
            $this->db->query('STOP SLAVE');
            $this->db->query('RESET SLAVE ALL');
            $this->db->query('SET GLOBAL read_only = OFF');
            $this->db->query('SET GLOBAL super_read_only = OFF');

            // Catat waktu failover
            $failoverTime = date('Y-m-d H:i:s');
            $failoverFile = FCPATH . 'backup/logs/failover_time.txt';
            file_put_contents($failoverFile, $failoverTime . "\n");

            // Update role di config
            $this->db->where('config_key', 'repl.server_role')
                     ->update('sys_app_config', ['config_value' => 'STANDALONE', 'updated_at' => date('Y-m-d H:i:s')]);

            $this->json_ok(['message' => "Failover berhasil. Server ini sekarang mode standalone.", 'failover_time' => $failoverTime]);
        } catch (Exception $e) {
            $this->json_error('Failover gagal: ' . $e->getMessage(), 500);
        }
    }

    // ── Aksi: Restart Replication ─────────────────────────────────
    public function action_restart_replication()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) {
            return;
        }
        $payload     = $this->request_payload();
        $masterHost  = (string)($payload['master_host']  ?? $this->_cfg('repl.master_host', ''));
        $masterPort  = (int)($payload['master_port']     ?? $this->_cfg('repl.master_port', '3306'));
        $replUser    = (string)($payload['repl_user']    ?? $this->_cfg('repl.repl_user', 'repl_user'));
        // Jika dikirim kosong ("tidak diubah"), gunakan nilai tersimpan di DB
        $rawPass     = (string)($payload['repl_pass']    ?? '');
        $replPass    = $rawPass !== '' ? $rawPass : $this->_cfg('repl.repl_pass', '');
        $tunnelOn    = $this->_cfg('tunnel.enabled', '0') === '1';
        $connHost    = $tunnelOn ? '127.0.0.1' : $masterHost;
        $connPort    = $tunnelOn ? (int)$this->_cfg('tunnel.local_port', '3307') : $masterPort;

        if ($tunnelOn && $this->_cfg('tunnel.ssh_host', '') === '') {
            $this->json_error('SSH host tunnel belum dikonfigurasi.', 422);
            return;
        }
        if (!$tunnelOn && empty($masterHost)) {
            $this->json_error('Master host belum dikonfigurasi.', 422);
            return;
        }

        try {
            // Ambil posisi master via koneksi terpisah
            $dsn = "mysql:host={$connHost};port={$connPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $replUser, $replPass, [PDO::ATTR_TIMEOUT => 10]);
            $masterStatus = $pdo->query('SHOW MASTER STATUS')->fetch(PDO::FETCH_ASSOC);
            if (!$masterStatus) {
                $this->json_error('Tidak bisa baca MASTER STATUS dari Server 1.', 422);
                return;
            }

            $logFile = $masterStatus['File'];
            $logPos  = $masterStatus['Position'];

            $this->db->query("STOP SLAVE");
            $this->db->query("SET GLOBAL read_only = ON");
            $this->db->query("CHANGE MASTER TO
                MASTER_HOST='" . $this->db->escape_str($connHost) . "',
                MASTER_PORT=" . (int)$connPort . ",
                MASTER_USER='" . $this->db->escape_str($replUser) . "',
                MASTER_PASSWORD='" . $this->db->escape_str($replPass) . "',
                MASTER_LOG_FILE='" . $this->db->escape_str($logFile) . "',
                MASTER_LOG_POS=" . (int)$logPos
            );
            $this->db->query("START SLAVE");

            // Hapus failover marker
            @unlink(FCPATH . 'backup/logs/failover_time.txt');

            // Update role
            $this->db->where('config_key', 'repl.server_role')
                     ->update('sys_app_config', ['config_value' => 'SLAVE', 'updated_at' => date('Y-m-d H:i:s')]);

            $this->json_ok(['message' => "Replication berhasil di-restart. Server kembali jadi Slave.", 'log_file' => $logFile, 'log_pos' => $logPos]);
        } catch (Exception $e) {
            $hint = $tunnelOn
                ? " Pastikan SSH tunnel aktif pada 127.0.0.1:{$connPort}, SSH key/host key valid, dan port remote MariaDB benar."
                : '';
            $this->json_error('Gagal restart replication: ' . $e->getMessage() . $hint, 500);
        }
    }

    public function action_tunnel_generate_key()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        try {
            $dir = $this->_tunnel_state_dir();
            $keyPath = $dir . '/client_ed25519';
            $pubPath = $keyPath . '.pub';
            if (is_file($keyPath) && is_file($pubPath)) {
                $publicKey = trim((string)file_get_contents($pubPath));
            } elseif (file_exists($keyPath) || file_exists($pubPath)) {
                $this->json_error('Pasangan key SSH tidak lengkap; hubungi administrator untuk merapikan key.', 500);
                return;
            } else {
                $result = $this->_tunnel_process([
                    '/usr/bin/ssh-keygen', '-q', '-t', 'ed25519', '-N', '',
                    '-C', 'finance-db-tunnel', '-f', $keyPath,
                ]);
                if ($result['code'] !== 0 || !is_file($keyPath) || !is_file($pubPath)) {
                    $this->json_error('Gagal membuat SSH key. Pastikan ssh-keygen tersedia dan storage tunnel writable.', 500);
                    return;
                }
                @chmod($keyPath, 0600);
                @chmod($pubPath, 0644);
                $publicKey = trim((string)file_get_contents($pubPath));
            }

            if (!preg_match('/\Assh-ed25519\s+[A-Za-z0-9+\/=]+(?:\s+.*)?\z/D', $publicKey)) {
                $this->json_error('Format public key tidak valid.', 500);
                return;
            }

            $restrictedKey = 'command="/bin/false",restrict,port-forwarding,permitopen="127.0.0.1:3306" ' . $publicKey;
            $this->json_ok([
                'message' => 'Key siap. Pasang baris terbatas ini pada authorized_keys akun SSH server utama.',
                'public_key' => $publicKey,
                'authorized_key' => $restrictedKey,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'System Tools SSH key setup failed.');
            $this->json_error($e->getMessage(), 500);
        }
    }

    public function action_tunnel_scan_host_key()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        try {
            [$host, $port] = $this->_tunnel_endpoint();
            $scan = $this->_tunnel_process([
                '/usr/bin/ssh-keyscan', '-T', '5', '-p', (string)$port, $host,
            ]);
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $scan['stdout']) ?: []), static function($line) {
                return $line !== '' && $line[0] !== '#';
            }));
            if (!$lines) {
                $this->json_error('Tidak dapat mengambil SSH host key. Periksa alamat dan port SSH.', 422);
                return;
            }

            $fingerprints = [];
            foreach ($lines as $line) {
                $fingerprint = $this->_tunnel_fingerprint($line);
                if ($fingerprint !== '') $fingerprints[] = ['fingerprint' => $fingerprint, 'key_type' => explode(' ', $line)[1] ?? ''];
            }
            if (!$fingerprints) {
                $this->json_error('SSH host key diterima, tetapi fingerprint tidak dapat dihitung.', 422);
                return;
            }

            $dir = $this->_tunnel_state_dir();
            file_put_contents($dir . '/hostkey.pending', implode("\n", $lines) . "\n", LOCK_EX);
            @chmod($dir . '/hostkey.pending', 0600);
            $this->json_ok([
                'host' => $host,
                'port' => $port,
                'fingerprints' => $fingerprints,
                'message' => 'Bandingkan fingerprint dengan fingerprint SSH host utama yang diperoleh melalui kanal tepercaya sebelum menyetujuinya.',
            ]);
        } catch (Throwable $e) {
            log_message('error', 'System Tools SSH host-key scan failed.');
            $this->json_error($e->getMessage(), 500);
        }
    }

    public function action_local_ssh_fingerprint()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        $fingerprints = [];
        $keyFiles = glob('/etc/ssh/ssh_host_*_key.pub') ?: [];
        foreach ($keyFiles as $publicKeyFile) {
            if (!is_readable($publicKeyFile)) continue;
            $publicKey = trim((string)@file_get_contents($publicKeyFile));
            $parts = preg_split('/\s+/', $publicKey, 3);
            if (count($parts) !== 3 || !preg_match('/\Assh-[A-Za-z0-9@._+-]+\z/D', $parts[0])) continue;
            $blob = base64_decode($parts[1], true);
            if ($blob === false) continue;
            $fingerprint = 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=');
            $type = preg_replace('/^ssh_host_|_key\.pub$/', '', basename($publicKeyFile));
            $fingerprints[] = ['key_type' => $type, 'fingerprint' => $fingerprint];
        }

        if (!$fingerprints) {
            $this->json_error('PHP-FPM tidak dapat membaca public host key di /etc/ssh/ssh_host_*.pub. Minta administrator mengizinkan baca direktori /etc/ssh untuk pool aplikasi; private key tidak perlu dibuka.', 422);
            return;
        }
        $this->json_ok(['fingerprints' => $fingerprints, 'message' => 'Fingerprint dihitung dari public host key server yang menjalankan halaman DB Tools ini.']);
    }

    public function action_tunnel_trust_host_key()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        $payload = $this->request_payload();
        $expected = trim((string)($payload['fingerprint'] ?? ''));
        if (!preg_match('/\ASHA256:[A-Za-z0-9+\/=]+\z/D', $expected)) {
            $this->json_error('Fingerprint SSH tidak valid.', 422);
            return;
        }

        try {
            $dir = $this->_tunnel_state_dir();
            $pending = $dir . '/hostkey.pending';
            if (!is_file($pending)) {
                $this->json_error('Scan host key dahulu sebelum menyetujuinya.', 422);
                return;
            }

            $matchedLine = '';
            foreach (file($pending, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (hash_equals($expected, $this->_tunnel_fingerprint($line))) {
                    $matchedLine = trim($line);
                    break;
                }
            }
            if ($matchedLine === '') {
                $this->json_error('Fingerprint tidak cocok dengan hasil scan terbaru. Scan ulang dan verifikasi kembali.', 422);
                return;
            }

            $knownHosts = $dir . '/known_hosts';
            $existing = is_file($knownHosts) ? file($knownHosts, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            $entryHost = $this->_known_host_name();
            $keyParts = preg_split('/\s+/', $matchedLine, 3);
            if (count($keyParts) !== 3) {
                $this->json_error('Data host key tidak valid.', 422);
                return;
            }
            $newLine = $entryHost . ' ' . $keyParts[1] . ' ' . $keyParts[2];

            foreach ($existing as $line) {
                $parts = preg_split('/\s+/', trim($line), 3);
                if (count($parts) === 3 && $parts[0] === $entryHost && $parts[1] === $keyParts[1] && !hash_equals($parts[2], $keyParts[2])) {
                    $this->json_error('Host key berbeda dari key yang sudah dipercaya. Jangan timpa otomatis; verifikasi perubahan host key terlebih dahulu.', 409);
                    return;
                }
            }

            if (!in_array($newLine, $existing, true)) $existing[] = $newLine;
            file_put_contents($knownHosts, implode("\n", $existing) . "\n", LOCK_EX);
            @chmod($knownHosts, 0600);
            @unlink($pending);
            $this->json_ok(['message' => 'Fingerprint cocok; SSH host key dipercaya untuk tunnel ini.']);
        } catch (Throwable $e) {
            log_message('error', 'System Tools SSH host-key trust failed.');
            $this->json_error($e->getMessage(), 500);
        }
    }

    public function action_tunnel_status()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        try {
            $dir = $this->_tunnel_state_dir();
            $port = (int)$this->_cfg('tunnel.local_port', '3307');
            $socket = $dir . '/control.sock';
            $control = false;
            if (is_file($dir . '/client_ed25519') && file_exists($socket)) {
                [$host, $sshPort] = $this->_tunnel_endpoint();
                $user = $this->_tunnel_user();
                $probe = $this->_tunnel_process([
                    '/usr/bin/ssh', '-S', $socket, '-p', (string)$sshPort,
                    '-O', 'check', $user . '@' . $host,
                ]);
                $control = $probe['code'] === 0;
            }
            $errno = 0;
            $error = '';
            $fp = @fsockopen('127.0.0.1', $port, $errno, $error, 0.5);
            $localListener = is_resource($fp);
            if ($localListener) fclose($fp);
            $this->json_ok([
                'running' => $control && $localListener,
                'control_master' => $control,
                'local_listener' => $localListener,
                'local_port' => $port,
            ]);
        } catch (Throwable $e) {
            $this->json_error($e->getMessage(), 500);
        }
    }

    public function action_tunnel_start()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        if ($this->_cfg('repl.server_role', '') !== 'SLAVE' || $this->_cfg('tunnel.enabled', '0') !== '1') {
            $this->json_error('Pilih peran Server Cadangan (SLAVE) dan aktifkan SSH Tunnel terlebih dahulu.', 422);
            return;
        }

        try {
            [$host, $sshPort] = $this->_tunnel_endpoint();
            $user = $this->_tunnel_user();
            $localPort = (int)$this->_cfg('tunnel.local_port', '3307');
            $remotePort = (int)$this->_cfg('tunnel.remote_port', '3306');
            if ($localPort < 1 || $localPort > 65535 || $remotePort < 1 || $remotePort > 65535) {
                $this->json_error('Port tunnel harus berada pada rentang 1-65535.', 422);
                return;
            }

            $dir = $this->_tunnel_state_dir();
            $keyPath = $dir . '/client_ed25519';
            $knownHosts = $dir . '/known_hosts';
            if (!is_file($keyPath) || !is_file($knownHosts)) {
                $this->json_error('Buat SSH key dan verifikasi host key melalui panel ini terlebih dahulu.', 422);
                return;
            }
            $socket = $dir . '/control.sock';
            if (file_exists($socket)) {
                $current = $this->_tunnel_process([
                    '/usr/bin/ssh', '-S', $socket, '-p', (string)$sshPort,
                    '-O', 'check', $user . '@' . $host,
                ]);
                if ($current['code'] === 0) {
                    $this->json_ok(['message' => 'SSH tunnel sudah aktif.', 'local_port' => $localPort]);
                    return;
                }
                @unlink($socket);
            }

            $errno = 0;
            $error = '';
            $existing = @fsockopen('127.0.0.1', $localPort, $errno, $error, 0.3);
            if (is_resource($existing)) {
                fclose($existing);
                $this->json_error("Port lokal {$localPort} sudah dipakai proses lain; tunnel tidak dijalankan.", 409);
                return;
            }

            $result = $this->_tunnel_process([
                '/usr/bin/ssh', '-M', '-S', $socket,
                '-o', 'ControlMaster=yes',
                '-o', 'ExitOnForwardFailure=yes',
                '-o', 'BatchMode=yes',
                '-o', 'StrictHostKeyChecking=yes',
                '-o', 'UserKnownHostsFile=' . $knownHosts,
                '-o', 'IdentitiesOnly=yes',
                '-o', 'ServerAliveInterval=30',
                '-o', 'ServerAliveCountMax=3',
                '-i', $keyPath,
                '-p', (string)$sshPort,
                '-L', "127.0.0.1:{$localPort}:127.0.0.1:{$remotePort}",
                '-fN', $user . '@' . $host,
            ]);
            if ($result['code'] !== 0) {
                $detail = trim($result['stderr'] ?: $result['stdout']);
                $this->json_error('SSH tunnel gagal dimulai.' . ($detail !== '' ? ' ' . $detail : ''), 502);
                return;
            }

            usleep(250000);
            $probe = @fsockopen('127.0.0.1', $localPort, $errno, $error, 1.0);
            if (!is_resource($probe)) {
                $this->json_error('Proses SSH tidak membuka listener lokal. Periksa key dan hak port-forward akun SSH.', 502);
                return;
            }
            fclose($probe);
            $this->json_ok(['message' => 'SSH tunnel aktif.', 'local_port' => $localPort]);
        } catch (Throwable $e) {
            log_message('error', 'System Tools SSH tunnel start failed.');
            $this->json_error($e->getMessage(), 500);
        }
    }

    public function action_tunnel_stop()
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        if (!$this->require_system_tools_mutation_csrf()) return;

        try {
            [$host, $sshPort] = $this->_tunnel_endpoint();
            $user = $this->_tunnel_user();
            $socket = $this->_tunnel_state_dir() . '/control.sock';
            if (!file_exists($socket)) {
                $this->json_ok(['message' => 'Tidak ada tunnel UI yang aktif.']);
                return;
            }
            $result = $this->_tunnel_process([
                '/usr/bin/ssh', '-S', $socket, '-p', (string)$sshPort,
                '-O', 'exit', $user . '@' . $host,
            ]);
            @unlink($socket);
            if ($result['code'] !== 0) {
                $this->json_error('Gagal menghentikan tunnel. Listener mungkin sudah berhenti; periksa status tunnel.', 502);
                return;
            }
            $this->json_ok(['message' => 'SSH tunnel dihentikan.']);
        } catch (Throwable $e) {
            $this->json_error($e->getMessage(), 500);
        }
    }

    // ── AJAX: replication status ───────────────────────────────────
    public function backup_status()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        $dumpDir = FCPATH . 'backup/dumps/';
        $logFile = FCPATH . 'backup/logs/cron.log';
        $dumps   = $this->_listRecentFiles($dumpDir, 'backup_*.sql*', 20);
        usort($dumps, fn($a, $b) => $b['mtime'] - $a['mtime']);
        $lastLog = '';
        if (file_exists($logFile)) {
            $lines   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLog = implode("\n", array_slice($lines, -30));
        }
        $this->json_ok(['dumps' => $dumps, 'last_log' => $lastLog, 'dump_count' => count($dumps)]);
    }

    public function replication_status()
    {
        $this->require_permission(self::PAGE_CODE, 'view');
        $this->require_permission(self::PAGE_CODE, self::SENSITIVE_READ_ACTION);
        $statusFile   = FCPATH . 'backup/logs/replication_status.json';
        $failoverFile = FCPATH . 'backup/logs/failover_time.txt';
        $status = $this->visible_replication_status($statusFile);
        $status['failover_active'] = file_exists($failoverFile);
        $status['failover_time']   = $status['failover_active'] ? trim(file_get_contents($failoverFile)) : null;
        $this->json_ok($status);
    }

    // ── Helpers ────────────────────────────────────────────────────
    private function render_limited_page(): void
    {
        $dumpDir = FCPATH . 'backup/dumps/';
        $this->render('system/dbtools_limited', [
            'title' => 'Perlindungan Database',
            'active_menu' => self::PAGE_CODE,
            'backup_configured' => is_file(FCPATH . 'scripts/backup/.env'),
            'has_recent_backup' => !empty(glob($dumpDir . '*.sql*')),
            'failover_active' => is_file(FCPATH . 'backup/logs/failover_time.txt'),
        ]);
    }

    private function visible_config(): array
    {
        if (!$this->db->table_exists('sys_app_config')) return [];
        $allowed = [
            'backup.db_host', 'backup.db_port', 'backup.db_user', 'backup.db_name',
            'backup.retention_days', 'backup.exclude_tables', 'repl.server_role',
            'repl.master_host', 'repl.master_port', 'repl.repl_user',
            'tunnel.enabled', 'tunnel.ssh_host', 'tunnel.ssh_port', 'tunnel.ssh_user',
            'tunnel.local_port', 'tunnel.remote_port',
        ];
        $rows = $this->db->select('config_key, config_value')->from('sys_app_config')
            ->where_in('config_key', $allowed)->get()->result_array();
        $config = [];
        foreach ($rows as $row) {
            $key = (string)($row['config_key'] ?? '');
            if (in_array($key, $allowed, true)) $config[$key] = (string)($row['config_value'] ?? '');
        }
        return $config;
    }

    private function visible_replication_status(string $statusFile): array
    {
        $raw = is_file($statusFile) ? json_decode((string)file_get_contents($statusFile), true) : [];
        if (!is_array($raw)) return [];
        $allowed = ['role','timestamp','status','io_running','sql_running','lag_seconds','master_host','last_error','last_io_error','error','binlog','position'];
        return array_intersect_key($raw, array_flip($allowed));
    }

    private function _cfg(string $key, string $default = ''): string
    {
        if (!$this->db->table_exists('sys_app_config')) return $default;
        $row = $this->db->select('config_value')->where('config_key', $key)->get('sys_app_config')->row_array();
        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function _tunnel_endpoint(): array
    {
        $host = trim($this->_cfg('tunnel.ssh_host', ''));
        $port = (int)$this->_cfg('tunnel.ssh_port', '22');
        if ($host === '' || strlen($host) > 253
            || !preg_match('/\A(?:[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?|\d{1,3}(?:\.\d{1,3}){3})\z/D', $host)
            || $port < 1 || $port > 65535) {
            throw new RuntimeException('SSH host atau port tidak valid. Simpan pengaturan tunnel terlebih dahulu.');
        }
        return [$host, $port];
    }

    private function _tunnel_user(): string
    {
        $user = trim($this->_cfg('tunnel.ssh_user', ''));
        if (!preg_match('/\A[a-z_][a-z0-9_-]{0,31}\$?\z/D', $user)) {
            throw new RuntimeException('SSH user tidak valid.');
        }
        return $user;
    }

    private function _tunnel_state_dir(): string
    {
        $root = '/var/tmp/finance-dbtools-ssh-' . substr(hash('sha256', FCPATH), 0, 16);
        if (is_link($root)) throw new RuntimeException('Lokasi state SSH tunnel tidak aman.');
        if (!is_dir($root)) {
            $previousUmask = umask(0077);
            try {
                if (!@mkdir($root, 0700, true) && !is_dir($root)) {
                    throw new RuntimeException('PHP-FPM tidak dapat membuat storage privat tunnel di /var/tmp.');
                }
            } finally {
                umask($previousUmask);
            }
        }
        @chmod($root, 0700);
        if (!is_writable($root) || !is_readable($root)) {
            throw new RuntimeException('Storage tunnel tidak dapat diakses user PHP-FPM.');
        }
        return $root;
    }

    private function _tunnel_process(array $command): array
    {
        if (!function_exists('proc_open')) throw new RuntimeException('proc_open tidak tersedia pada PHP-FPM.');
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('PHP-FPM tidak dapat menjalankan utilitas SSH.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $output = [1 => '', 2 => ''];
        while ($open) {
            $read = array_values($open);
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 5);
            if ($ready === false) break;
            if ($ready === 0) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    foreach ($open as $index => $pipe) {
                        $output[$index] .= stream_get_contents($pipe);
                        fclose($pipe);
                        unset($open[$index]);
                    }
                }
                continue;
            }
            foreach ($read as $pipe) {
                $index = $pipe === $pipes[1] ? 1 : 2;
                $output[$index] .= (string)fread($pipe, 8192);
                if (feof($pipe)) {
                    fclose($pipe);
                    unset($open[$index]);
                }
            }
        }
        foreach ($open as $index => $pipe) {
            $output[$index] .= stream_get_contents($pipe);
            fclose($pipe);
        }
        $code = proc_close($process);
        return ['code' => $code, 'stdout' => $output[1], 'stderr' => $output[2]];
    }

    private function _tunnel_fingerprint(string $knownHostLine): string
    {
        $parts = preg_split('/\s+/', trim($knownHostLine), 3);
        if (count($parts) !== 3 || !preg_match('/\A[A-Za-z0-9@._+-]+\z/D', $parts[1])) return '';
        $dir = $this->_tunnel_state_dir();
        $temp = tempnam($dir, 'hostkey-');
        if ($temp === false) return '';
        try {
            if (file_put_contents($temp, $knownHostLine . "\n", LOCK_EX) === false) return '';
            @chmod($temp, 0600);
            $result = $this->_tunnel_process(['/usr/bin/ssh-keygen', '-lf', $temp]);
            if ($result['code'] !== 0) return '';
            return preg_match('/\bSHA256:[A-Za-z0-9+\/=]+/', $result['stdout'], $match) ? $match[0] : '';
        } finally {
            @unlink($temp);
        }
    }

    private function _known_host_name(): string
    {
        [$host, $port] = $this->_tunnel_endpoint();
        return $port === 22 ? $host : '[' . $host . ']:' . $port;
    }

    private function _writeEnvFile(): bool
    {
        $envPath = FCPATH . 'scripts/backup/.env';

        // Bungkus nilai dalam single-quote agar karakter spesial aman saat di-source bash.
        // Escape single-quote dalam nilai dengan cara: ' → '\''
        $q = static function(string $v): string {
            return "'" . str_replace("'", "'\\''", $v) . "'";
        };

        $lines = [
            "# Auto-generated oleh Finance App — " . date('Y-m-d H:i:s'),
            "# Jangan edit manual jika menggunakan UI settings",
            "",
            "# Database",
            "DB_HOST=" . $q($this->_cfg('backup.db_host', 'localhost')),
            "DB_PORT=" . $q($this->_cfg('backup.db_port', '3306')),
            "DB_USER=" . $q($this->_cfg('backup.db_user', 'root')),
            "DB_PASS=" . $q($this->_cfg('backup.db_pass', '')),
            "DB_NAME=" . $q($this->_cfg('backup.db_name', 'db_finance')),
            "",
            "# Backup",
            "BACKUP_DIR=backup/dumps",
            "LOG_DIR=backup/logs",
            "RETENTION_DAYS=" . $q($this->_cfg('backup.retention_days', '3')),
            "EXCLUDE_TABLES=" . $q($this->_cfg('backup.exclude_tables', '')),
            "",
            "# Replication",
            "SERVER_ROLE=" . $q($this->_cfg('repl.server_role', 'STANDALONE')),
            "MASTER_HOST=" . $q($this->_cfg('repl.master_host', '')),
            "MASTER_PORT=" . $q($this->_cfg('repl.master_port', '3306')),
            "REPL_USER=" . $q($this->_cfg('repl.repl_user', 'repl_user')),
            "REPL_PASS=" . $q($this->_cfg('repl.repl_pass', '')),
            "",
            "# SSH Tunnel",
            "TUNNEL_ENABLED=" . $q($this->_cfg('tunnel.enabled', '0')),
            "SSH_HOST=" . $q($this->_cfg('tunnel.ssh_host', '')),
            "SSH_PORT=" . $q($this->_cfg('tunnel.ssh_port', '22')),
            "SSH_USER=" . $q($this->_cfg('tunnel.ssh_user', 'root')),
            "TUNNEL_LOCAL_PORT=" . $q($this->_cfg('tunnel.local_port', '3307')),
            "TUNNEL_REMOTE_PORT=" . $q($this->_cfg('tunnel.remote_port', '3306')),
        ];
        $isNewFile = !is_file($envPath);
        $previousUmask = umask(0077);
        try {
            $written = @file_put_contents($envPath, implode("\n", $lines) . "\n", LOCK_EX);
        } finally {
            umask($previousUmask);
        }

        if ($written === false) {
            log_message('error', 'System Tools could not write the backup environment file.');
            return false;
        }

        // A newly-created file contains DB credentials; default it to owner-only.
        if ($isNewFile && !@chmod($envPath, 0600)) {
            @unlink($envPath);
            log_message('error', 'System Tools could not secure the new backup environment file.');
            return false;
        }

        return true;
    }

    private function system_tools_mutation_csrf(): string
    {
        $token = (string)$this->session->userdata(self::SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY);
        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata(self::SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY, $token);
        }
        return $token;
    }

    private function require_system_tools_mutation_csrf(): bool
    {
        if (strtoupper((string)$this->input->method(true)) !== 'POST') {
            $this->output->set_header('Allow: POST');
            $this->json_error('Permintaan System Tools tidak valid.', 405);
            return false;
        }

        $provided = trim((string)$this->input->get_request_header(self::SYSTEM_TOOLS_MUTATION_CSRF_CI_HEADER, true));
        $expected = (string)$this->session->userdata(self::SYSTEM_TOOLS_MUTATION_CSRF_SESSION_KEY);
        if (
            preg_match('/\A[0-9a-fA-F]{64}\z/D', $provided) !== 1
            || preg_match('/\A[0-9a-fA-F]{64}\z/D', $expected) !== 1
            || !hash_equals($expected, $provided)
        ) {
            $this->json_error('Permintaan System Tools tidak valid.', 403);
            return false;
        }

        return true;
    }

    private function _runScript(string $folder, string $name): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $ext       = $isWindows ? '.bat' : '.sh';
        $script    = FCPATH . "scripts/{$folder}/{$name}{$ext}";

        if (!file_exists($script)) {
            return ['ok' => false, 'output' => "Script tidak ditemukan: {$script}"];
        }

        if (!$isWindows) {
            @chmod($script, 0755);
        }

        $cmd    = $isWindows ? "cmd /c \"{$script}\" 2>&1" : "bash \"{$script}\" 2>&1";
        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        return ['ok' => $code === 0, 'output' => implode("\n", $output), 'code' => $code];
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) return $json;
        }
        $post = $this->input->post(null, true);
        return is_array($post) ? $post : [];
    }

    private function json_ok(array $data = []): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $status = 422): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function _listRecentFiles(string $dir, string $pattern, int $limit): array
    {
        if (!is_dir($dir)) return [];
        $files  = glob($dir . $pattern) ?: [];
        $result = [];
        foreach ($files as $f) {
            $result[] = [
                'name'  => basename($f),
                'size'  => $this->_humanFilesize(filesize($f) ?: 0),
                'mtime' => filemtime($f),
                'date'  => date('d M Y H:i', filemtime($f)),
            ];
        }
        usort($result, fn($a, $b) => $b['mtime'] - $a['mtime']);
        return array_slice($result, 0, $limit);
    }

    private function _humanFilesize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
