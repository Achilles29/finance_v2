<?php

declare(strict_types=1);

define('FINANCE_RETENTION_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/release/retention_manager.php';
define('A515_RETENTION_PREFLIGHT_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/retention_preflight.php';

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $removeTree($path . '/' . $entry);
        }
    }
    @rmdir($path);
};

$fixtureRoot = sys_get_temp_dir() . '/finance-a515-' . bin2hex(random_bytes(6));
$a5 = $fixtureRoot . '/a5';
$backup = $fixtureRoot . '/backup';
$now = 1800000000;

try {
    foreach ([$a5 . '/run', $a5 . '/evidence', $a5 . '/backups', $backup . '/dumps', $backup . '/logs'] as $directory) {
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('fixture directory creation failed');
        }
    }
    $write = static function (string $path, string $content, int $mtime): void {
        file_put_contents($path, $content);
        touch($path, $mtime);
    };
    $writeBackup = static function (string $path, string $content, int $mtime, bool $valid = true) use ($write): void {
        $write($path, $content, $mtime);
        $hash = $valid ? hash('sha256', $content) : str_repeat('0', 64);
        $write($path . '.sha256', $hash . '  ' . basename($path) . PHP_EOL, $mtime);
    };
    $old = $now - (5 * 86400);
    $recent = $now - 3600;
    $write($a5 . '/run/old.cnf', 'secret-free-fixture', $old);
    $write($a5 . '/run/recent.cnf', 'recent', $recent);
    symlink('/etc/passwd', $a5 . '/run/unsafe.cnf');
    $writeBackup($a5 . '/backups/old.sql.gz', 'old-a5', $old);
    $writeBackup($a5 . '/backups/recent.sql.gz', 'recent-a5', $recent);
    $writeBackup($a5 . '/backups/unverified.sql.gz', 'bad-a5', $old, false);
    $writeBackup($backup . '/dumps/old.sql.gz', 'old-regular', $old);
    $writeBackup($backup . '/dumps/recent.sql.gz', 'recent-regular', $recent);
    $write($backup . '/logs/old.log', 'old-log', $old);
    $write($backup . '/logs/recent.log', 'recent-log', $recent);

    $policy = financeRetentionLoadPolicy($root);
    $check(financeRetentionPolicyErrors($policy) === [], 'retention policy schema and safety inventory are valid');
    foreach ($policy['filesystem_rules'] as &$rule) {
        $rule['minimum_age_days'] = 2;
        $rule['retain_newest'] = in_array($rule['id'], ['a5_temporary_files'], true) ? 0 : 1;
    }
    unset($rule);
    $roots = financeRetentionResolveRoots($policy, ['a5_runtime' => $a5, 'backup_runtime' => $backup]);
    $plan = financeRetentionPlan($root, $policy, $roots, $now);
    $check($plan['totals']['candidates'] === 4, 'only old eligible files outside retain-newest protection are planned');
    $check($plan['totals']['invalid_or_unverified'] === 2, 'symlink and bad-checksum backup are rejected');
    $plannedNames = [];
    foreach ($plan['rules'] as $rule) {
        foreach ($rule['candidates'] as $candidate) {
            $plannedNames[] = $rule['id'] . ':' . $candidate['name'];
        }
    }
    sort($plannedNames);
    $check($plannedNames === [
        'a5_prechange_backups:old.sql.gz',
        'a5_temporary_files:old.cnf',
        'backup_job_logs:old.log',
        'regular_database_backups:old.sql.gz',
    ], 'plan identifies the expected four files exactly');

    $wrongConfirmationRejected = false;
    try {
        financeRetentionApply($root, $policy, $roots, $plan, 'wrong-confirmation');
    } catch (FinanceRetentionFailure $error) {
        $wrongConfirmationRejected = true;
    }
    $check($wrongConfirmationRejected, 'apply rejects an incorrect policy confirmation');
    $result = financeRetentionApply($root, $policy, $roots, $plan, $plan['confirmation']);
    $check(($result['status'] ?? '') === 'quarantined' && ($result['moved'] ?? 0) === 4, 'apply quarantines all planned primaries');
    $check(
        !file_exists($a5 . '/run/old.cnf')
            && file_exists($a5 . '/run/recent.cnf')
            && is_link($a5 . '/run/unsafe.cnf')
            && file_exists($a5 . '/backups/unverified.sql.gz'),
        'old candidate moves while recent, symlink, and unverified files remain untouched'
    );
    $check(
        !file_exists($a5 . '/backups/old.sql.gz.sha256')
            && !file_exists($backup . '/dumps/old.sql.gz.sha256')
            && count(glob($result['quarantine'] . '/*.sha256') ?: []) === 2,
        'verified backup checksum companions move with their dumps'
    );
    $audit = json_decode((string)file_get_contents($result['audit_path']), true);
    $mode = fileperms($result['audit_path']);
    $check(
        is_array($audit)
            && ($audit['schema'] ?? '') === 'finance.retention-run'
            && count($audit['moved'] ?? []) === 4
            && is_int($mode) && ($mode & 0777) === 0600,
        'apply writes a private, complete retention audit record'
    );
    $check(is_dir($result['quarantine']), 'apply is recoverable because it creates quarantine instead of deleting');

    $databaseRules = array_column($policy['database_rules'], null, 'id');
    $check(
        isset($databaseRules['availability_rebuild_success_detail'])
            && $databaseRules['availability_rebuild_success_detail']['predicate'] === 'mismatch_flag = 0'
            && $databaseRules['availability_rebuild_success_detail']['enabled'] === false,
        'availability detail retention preserves mismatch rows and remains archive-gated'
    );
    $databaseQueries = financeRetentionDatabaseQueries($policy);
    $check(
        count($databaseQueries) === 5
            && strpos($databaseQueries['availability_rebuild_success_detail'], 'mismatch_flag = 0') !== false
            && strpos($databaseQueries['availability_rebuild_success_detail'], 'INTERVAL 90 DAY') !== false,
        'read-only database preflight covers all rules and targets only old non-mismatch availability detail'
    );
    $check(
        preg_match('/\b(?:DELETE|UPDATE|INSERT|REPLACE|TRUNCATE|DROP|ALTER|CREATE)\b/i', implode("\n", $databaseQueries)) !== 1,
        'database preflight generates SELECT statements only'
    );
    $tamperedPolicy = $policy;
    $tamperedPolicy['database_rules'][0]['predicate'] = '1 = 1';
    $check(financeRetentionPolicyErrors($tamperedPolicy) !== [], 'broadened database purge predicate is rejected');
    $check(
        in_array('uploads', $policy['never_age_delete'], true)
            && in_array('financial_journals', $policy['never_age_delete'], true)
            && in_array('active_queue_rows', $policy['never_age_delete'], true),
        'uploads, financial records, and active queues can never be age-deleted'
    );
    $linuxBackup = (string)file_get_contents($root . '/scripts/backup/backup_full.sh');
    $windowsBackup = (string)file_get_contents($root . '/scripts/backup/backup_full.bat');
    $backupExample = (string)file_get_contents($root . '/scripts/backup/.env.example');
    $check(
        strpos($linuxBackup, 'sha256sum "$DUMPFILE"') !== false
            && strpos($linuxBackup, 'find "$BACKUP_DIR"') === false
            && strpos($linuxBackup, '-delete') === false,
        'Linux backup creates SHA-256 and no longer deletes old backups directly'
    );
    $check(
        strpos($windowsBackup, 'Get-FileHash -Algorithm SHA256') !== false
            && stripos($windowsBackup, 'forfiles ') === false
            && stripos($windowsBackup, ' del @path') === false,
        'Windows backup creates SHA-256 and no longer deletes old backups directly'
    );
    $check(
        strpos($backupExample, 'BACKUP_DIR=/var/lib/finance-backup/dumps') !== false
            && strpos($backupExample, 'RETENTION_DAYS=') === false,
        'Linux deployment example stores backups outside the document root'
    );
} finally {
    $removeTree($fixtureRoot);
}

$check(!file_exists($fixtureRoot), 'fixture cleanup leaves no retention test data');
if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A5.15 retention contract check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' A5.15 retention lifecycle contract checks passed.' . PHP_EOL;
