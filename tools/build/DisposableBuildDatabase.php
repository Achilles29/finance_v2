<?php
declare(strict_types=1);

if (!defined('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY')) define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/db/post_install_health_check.php';
if (!defined('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY')) define('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/db/bootstrap_first_owner.php';

/** All SQL is confined to a newly initialized, non-networked MariaDB owned by the build account. */
final class DisposableBuildDatabase
{
    private const MYSQL = '/www/server/mysql';
    private const REFERENCES = ['sys_matrix_group', 'sys_page', 'sys_menu', 'sys_page_alias', 'auth_role',
        'auth_role_permission', 'sys_schema_migration', 'tg_setting', 'wa_template', 'wa_session', 'coffee_packaging_label_template',
        'sys_roast_connect', 'fin_control_policy', 'fin_gl_guard', 'fin_gl_account'];

    private static function query(string $option, string $database, string $sql): string
    {
        return CustomerBuild::run([self::MYSQL . '/bin/mariadb', '--defaults-file=' . $option,
            '--batch', '--raw', '--skip-column-names', $database, '--execute=' . $sql], dirname($option), 'DISPOSABLE_QUERY_FAILED')['output'];
    }

    /** Exact counts and logical CHECKSUMs, never print row contents or owner secrets. */
    private static function inventory(string $option, string $database): array
    {
        $tables = explode("\n", self::query($option, $database, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY BINARY TABLE_NAME"));
        $counts = []; $hashes = [];
        foreach ($tables as $table) CustomerBuild::need(preg_match('/\A[A-Za-z0-9_]+\z/D', $table) === 1, 'DISPOSABLE_TABLE_NAME');
        // Small bounded batches keep captured output below 12 KiB and avoid hundreds of process startups.
        foreach (array_chunk($tables, 16) as $batch) {
            $selects = array_map(static fn(string $t): string => "SELECT '{$t}',COUNT(*) FROM `{$t}`", $batch);
            $rows = explode("\n", self::query($option, $database, implode(' UNION ALL ', $selects)));
            CustomerBuild::need(count($rows) === count($batch), 'DISPOSABLE_COUNT');
            foreach ($rows as $i => $row) {
                $parts = explode("\t", $row);
                CustomerBuild::need(count($parts) === 2 && $parts[0] === $batch[$i] && ctype_digit($parts[1]), 'DISPOSABLE_COUNT');
                $counts[$parts[0]] = (int)$parts[1];
            }
            $sql = 'CHECKSUM TABLE ' . implode(',', array_map(static fn(string $t): string => '`' . $t . '`', $batch)) . ' EXTENDED';
            $rows = explode("\n", self::query($option, $database, $sql));
            CustomerBuild::need(count($rows) === count($batch), 'DISPOSABLE_CHECKSUM');
            foreach ($rows as $i => $row) {
                $parts = explode("\t", $row);
                CustomerBuild::need(count($parts) === 2 && $parts[0] === $database . '.' . $batch[$i] && ctype_digit($parts[1]), 'DISPOSABLE_CHECKSUM');
                $hashes[$batch[$i]] = $parts[1];
            }
        }
        return ['counts' => $counts, 'checksums' => $hashes];
    }

    public static function safeDefaultQueries(): array
    {
        return [
            'ROAST_CONNECT_DEFAULT_UNSAFE' => "SELECT COUNT(*),COALESCE(SUM(id=1 AND instance_id REGEXP '^[a-f0-9]{32}$'
            AND BINARY name='Finance' AND enabled=0 AND division_id IS NULL AND BINARY destination_type='ROASTERY'
            AND token_hash IS NULL AND token_tail IS NULL AND token_created_at IS NULL AND expires_at IS NULL
            AND revision=0 AND updated_by IS NULL AND updated_at IS NULL),0) FROM sys_roast_connect",
            'FINANCE_CONTROL_DEFAULT_UNSAFE' => "SELECT COUNT(*),COALESCE(SUM(id=1 AND approval_enabled=0
            AND approval_threshold=1000000 AND evidence_required=0 AND payroll_day=1 AND revision=1
            AND updated_by IS NULL AND updated_at IS NOT NULL),0) FROM fin_control_policy",
        ];
    }

    private static function assertSafeDefaults(string $option, string $database): void
    {
        foreach (self::safeDefaultQueries() as $failure => $sql) {
            CustomerBuild::need(self::query($option, $database, $sql) === "1\t1", $failure);
        }
        CustomerBuild::need(self::query($option,$database,'SELECT COUNT(*),COALESCE(SUM(id=1),0) FROM fin_gl_guard')==="1\t1",'GL_GUARD_DEFAULT_UNSAFE');
        CustomerBuild::need(self::query($option,$database,self::accountReferenceQuery())==="24\t24",'GL_ACCOUNTS_DEFAULT_UNSAFE');
    }

    /** Only the 24 immutable generic reference tuples are allowed, not customer chart-of-account data. */
    public static function accountReferenceQuery(): string
    {
        $sql=(string)file_get_contents(dirname(__DIR__,2).'/sql/2026-09-15a_finance_general_ledger.sql');
        CustomerBuild::need(hash('sha256',$sql)==='03aeae127ea8431480f1553c43e5a8e2d581a453279c2ecf2dddbbb560b4c195','GL_REFERENCE_SQL_DRIFT');
        preg_match_all("/\\('[0-9]{4}','[^']+','(?:ASSET|LIABILITY|EQUITY|INCOME|EXPENSE)',[01]\\)/u",$sql,$m);
        CustomerBuild::need(count($m[0])===24,'GL_REFERENCE_INVENTORY');
        return 'SELECT COUNT(*),COALESCE(SUM((BINARY code,BINARY name,BINARY account_type,is_cash) IN ('.implode(',',$m[0]).') AND is_active=1),0) FROM fin_gl_account';
    }

    public static function test(string $root, string $scratch): array
    {
        CustomerBuild::need(posix_geteuid() !== 0 && realpath($scratch) === $scratch
            && fileowner($scratch) === posix_geteuid() && (fileperms($scratch) & 0077) === 0, 'DISPOSABLE_WORKSPACE_UNSAFE');
        $release = a513_validate_release($root, $root . '/RELEASE-MANIFEST.json');
        $dir = $scratch . '/database'; CustomerBuild::need(mkdir($dir, 0700), 'DISPOSABLE_CREATE_FAILED');
        // Unix socket paths are limited to 107 bytes; Control's per-build path can exceed that.
        $socketDir = '/tmp/finance-build-db-' . bin2hex(random_bytes(8));
        CustomerBuild::need(mkdir($socketDir, 0700), 'DISPOSABLE_SOCKET_FAILED');
        $process = null;
        try {
            foreach (['scripts/mariadb-install-db', 'bin/mariadbd', 'bin/mariadb', 'bin/mariadb-dump'] as $path)
                CustomerBuild::need(is_executable(self::MYSQL . '/' . $path), 'MARIADB_BUILD_RUNTIME_UNAVAILABLE');
            CustomerBuild::run([self::MYSQL . '/scripts/mariadb-install-db', '--no-defaults', '--basedir=' . self::MYSQL,
                '--datadir=' . $dir . '/data', '--auth-root-authentication-method=normal', '--skip-test-db'], $dir, 'DISPOSABLE_INIT_FAILED');
            $command = ['/usr/bin/setpriv', '--pdeathsig', 'TERM', self::MYSQL . '/bin/mariadbd', '--no-defaults',
                '--basedir=' . self::MYSQL, '--datadir=' . $dir . '/data', '--socket=' . $socketDir . '/db.sock',
                '--pid-file=' . $dir . '/db.pid', '--log-error=' . $dir . '/db.log', '--skip-networking',
                '--skip-log-bin', '--skip-name-resolve', '--innodb-buffer-pool-size=64M', '--tmpdir=' . $dir,
                '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci'];
            $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'],
                2 => ['file', '/dev/null', 'w']], $pipes, $dir);
            CustomerBuild::need(is_resource($process), 'DISPOSABLE_START_FAILED');
            $option = $dir . '/client.cnf';
            // Options here must be supported by both mariadb AND mariadb-dump.
            $config = "[client]\nprotocol=SOCKET\nsocket=" . $socketDir . "/db.sock\nhost=localhost\nuser=root\npassword=\n";
            CustomerBuild::need(file_put_contents($option, $config, LOCK_EX) === strlen($config) && chmod($option, 0600), 'DISPOSABLE_OPTION_FAILED');
            $ready = false;
            for ($i = 0; $i < 150; $i++) {
                $probe = releaseArtifactRun([self::MYSQL . '/bin/mariadb', '--defaults-file=' . $option,
                    '--connect-timeout=2', '--batch', '--skip-column-names', '--execute=SELECT VERSION()'], $dir, 3);
                if ($probe['code'] === 0) {
                    CustomerBuild::need(preg_match('/\A10\.11\.\d+.*MariaDB/i', $probe['output']) === 1, 'MARIADB_1011_REQUIRED');
                    $ready = true; break;
                }
                CustomerBuild::need(proc_get_status($process)['running'], 'DISPOSABLE_START_FAILED');
                usleep(100000);
            }
            CustomerBuild::need($ready, 'DISPOSABLE_START_TIMEOUT');
            $db = 'finance_build_test'; $restored = 'finance_build_restored';
            self::query($option, 'mysql', "CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE `{$restored}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $baseline = json_decode((string)file_get_contents($root . '/tools/db/clean_install_baseline_policy.json'), true);
            $client = a5_client_open(a5_find_client(), $option, $db, microtime(true) + 180);
            try {
                a5_client_send($client, (string)file_get_contents($root . '/' . $baseline['schema']['path']) . "\nSELECT '__BUILD_BASELINE__\\tOK';");
                CustomerBuild::need(a5_client_marker($client, '__BUILD_BASELINE__') === ['__BUILD_BASELINE__', 'OK'], 'DISPOSABLE_BASELINE_FAILED');
            } finally { a5_client_close($client, false); }
            $migrations = a5_apply($release['catalog'], $root, 'clean_install', $option, $db);
            // These migrations generate installation-local settings, never data
            // copied from Finance. Accept only their exact disabled/unconfigured defaults.
            self::assertSafeDefaults($option, $db);
            $beforeOwner = self::inventory($option, $db);
            $seedRecords = 0; $empty = 0;
            foreach ($beforeOwner['counts'] as $table => $count) {
                if (in_array($table, self::REFERENCES, true)) $seedRecords += $count;
                else { CustomerBuild::need($count === 0, 'CUSTOMER_DATA_FOUND'); $empty++; }
            }
            foreach ($baseline['seed']['post_apply_counts'] as $table => $expected)
                CustomerBuild::need(($beforeOwner['counts'][$table] ?? null) === $expected, 'REFERENCE_SEED_DRIFT');
            $clean = ['tables_checked' => count($beforeOwner['counts']), 'non_reference_tables_empty' => $empty,
                'system_seed_records' => $seedRecords, 'generic_sample_records' => 0, 'customer_data_findings' => 0,
                'source_database_accessed' => false, 'migrations' => $migrations];
            // Owner exists only in disposable DB; neither it nor this dump is ever packaged.
            $owner = ['username' => bin2hex(random_bytes(8)), 'email' => 'build-owner@example.invalid', 'password' => bin2hex(random_bytes(24))];
            a512_bootstrap_owner($root, $option, $db, $owner); unset($owner);
            $health = a513_check_database($release, 'clean_install', $option, $db);
            $beforeRestore = self::inventory($option, $db);
            $dump = $dir . '/restore-test.sql';
            CustomerBuild::run([self::MYSQL . '/bin/mariadb-dump', '--defaults-file=' . $option, '--single-transaction',
                '--skip-comments', '--routines', '--events', '--triggers', '--result-file=' . $dump, $db], $dir, 'DISPOSABLE_BACKUP_FAILED');
            $client = a5_client_open(a5_find_client(), $option, $restored, microtime(true) + 180);
            try {
                // mariaDB dump's client-only sandbox directive is not SQL for the streaming migration client.
                $sql = (string)file_get_contents($dump);
                $sql = preg_replace('/\A\/\*M!999999\\\\- enable the sandbox mode \*\/\r?\n/', '', $sql);
                a5_client_send($client, $sql . "\nSELECT '__BUILD_RESTORE__\\tOK';");
                CustomerBuild::need(a5_client_marker($client, '__BUILD_RESTORE__') === ['__BUILD_RESTORE__', 'OK'], 'DISPOSABLE_RESTORE_FAILED');
            } finally { a5_client_close($client, false); }
            CustomerBuild::need(self::inventory($option, $restored) === $beforeRestore, 'DISPOSABLE_RESTORE_MISMATCH');
            $restoreHealth = a513_check_database($release, 'clean_install', $option, $restored);
            return ['clean' => $clean, 'health' => $health, 'restore' => ['status' => 'PASS', 'tables_checked' => count($beforeRestore['counts']),
                'dump_sha256' => hash_file('sha256', $dump), 'logical_checksums_match' => true, 'health' => $restoreHealth]];
        } finally {
            if (is_resource($process)) {
                $s = proc_get_status($process);
                if ($s['running']) proc_terminate($process);
                for ($i = 0; $i < 100 && proc_get_status($process)['running']; $i++) usleep(100000);
                if (proc_get_status($process)['running']) proc_terminate($process, 9);
                proc_close($process);
            }
            releaseArtifactRemoveTree($socketDir);
        }
    }
}
