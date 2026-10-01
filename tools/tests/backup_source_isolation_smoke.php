<?php
declare(strict_types=1);

/**
 * Static smoke test for the P0-07A backup source isolation batch.
 *
 * This test reads source/configuration files only. It does not load the
 * application, connect to a database, run either backup runner, or mutate
 * any file or database data.
 */

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];

function read_source(string $root, string $relative): string
{
    $path = $root . '/' . $relative;
    $contents = @file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Missing source file: {$relative}\n");
        exit(1);
    }
    return $contents;
}

function check_source(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function executable_lines(string $source, bool $batch): string
{
    $lines = [];
    foreach (preg_split('/\R/', $source) ?: [] as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || (!$batch && str_starts_with($trimmed, '#'))) {
            continue;
        }
        if ($batch && preg_match('/^(?:REM\b|::)/i', $trimmed)) {
            continue;
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

$sh = read_source($root, 'scripts/backup/backup_full.sh');
$bat = read_source($root, 'scripts/backup/backup_full.bat');
$envExample = read_source($root, 'scripts/backup/.env.example');
// A clean release checkout must not need a copied staging secret to run tests.
$checkDeployment = ($argv[1] ?? '') === '--staging-env';
$env = $checkDeployment ? read_source($root, 'scripts/backup/.env') : $envExample;
$gitignore = read_source($root, '.gitignore');
$controller = read_source($root, 'application/controllers/System_tools.php');
$dbtools = read_source($root, 'application/views/system/dbtools.php');
$settings = read_source($root, 'application/views/system/settings.php');
$guide = read_source($root, 'application/views/system/backup_guide.php');

foreach ([
    'scripts/backup/backup_full.sh' => [$sh, false],
    'scripts/backup/backup_full.bat' => [$bat, true],
] as $label => [$runner, $batch]) {
    $code = executable_lines($runner, $batch);
    check_source(
        !preg_match('/\bgit(?:\.exe)?\b/i', $code),
        "{$label} contains an executable Git token"
    );
    check_source(str_contains($runner, 'mysqldump'), "{$label} no longer invokes mysqldump");
    check_source(
        preg_match('/(?:sha256sum|Get-FileHash).*SHA256|SHA-256/is', $runner) === 1,
        "{$label} no longer creates or records a backup checksum"
    );
    check_source(
        preg_match('/LOCAL-ONLY/i', $runner) === 1,
        "{$label} is missing local-only status wording"
    );
    check_source(
        preg_match('/SEPARATE.*(?:encrypted|off-site)|(?:encrypted|off-site).*SEPARATE/is', $runner) === 1,
        "{$label} is missing separate encrypted off-site status wording"
    );
}

check_source(str_contains($sh, 'LOGFILE=') && str_contains($sh, 'tee -a'), 'Shell runner logging was removed');
check_source(
    !str_contains($sh, 'find "$BACKUP_DIR"')
        && !str_contains($sh, '-delete')
        && str_contains($sh, 'retention_manager.php'),
    'Shell runner must delegate retention and never directly delete old backups'
);
check_source(str_contains($bat, 'LOGFILE=') && str_contains($bat, '>> "%LOGFILE%"'), 'Batch runner logging was removed');
check_source(
    stripos($bat, 'forfiles ') === false
        && stripos($bat, ' del @path') === false
        && stripos($bat, 'quarantine') !== false,
    'Batch runner must require review/quarantine and never directly delete old backups'
);

check_source(
    !preg_match('/^\s*BACKUP_REPO_REMOTE\s*=/m', $env),
    'Selected backup configuration still defines BACKUP_REPO_REMOTE'
);
check_source(
    !preg_match('/^\s*BACKUP_REPO_BRANCH\s*=/m', $env),
    'Selected backup configuration still defines BACKUP_REPO_BRANCH'
);

foreach ([$envExample, $sh, $bat] as $source) {
    check_source(
        !preg_match('/(?:BACKUP_REPO_(?:REMOTE|BRANCH|PATH)|repo_(?:remote|branch))/i', $source),
        'Repository settings remain in backup source/example configuration'
    );
}

check_source(
    !preg_match('/backup\.repo_(?:remote|branch)|BACKUP_REPO_(?:REMOTE|BRANCH)/i', $controller),
    'Controller still accepts or generates repository settings'
);

foreach ([
    'application/views/system/dbtools.php' => $dbtools,
    'application/views/system/settings.php' => $settings,
    'application/views/system/backup_guide.php' => $guide,
] as $label => $view) {
    check_source(
        !preg_match('/backup\.repo_(?:remote|branch)|cfg_backup_repo_(?:remote|branch)|id="b_(?:remote|branch)"|\bGitHub\b|git\s+(?:push|remote|add|commit|fetch|merge)/i', $view),
        "{$label} exposes repository settings or local GitHub push claims"
    );
}

check_source(str_contains($dbtools, 'id="btn-save-backup"'), 'Backup generation UI save action is missing');
check_source(str_contains($dbtools, "dbtools/settings/save"), 'Backup generation UI save endpoint is missing');
check_source(str_contains($settings, 'function collectBackupCfg'), 'Settings backup configuration UI is missing');

foreach ([
    '/backup/dumps/*',
    '!/backup/dumps/.gitkeep',
    '/backup/logs/*',
    '!/backup/logs/.gitkeep',
] as $rule) {
    check_source(str_contains($gitignore, $rule), "Missing .gitignore rule: {$rule}");
}

check_source(
    preg_match('/local(?:-only| saja| di host ini)/i', $sh . $bat . $dbtools . $settings . $guide) === 1,
    'No local-only wording is present in runners or backup UI'
);
check_source(
    preg_match('/off-site.*terenkripsi|terenkripsi.*off-site/i', $dbtools . $settings . $guide) === 1,
    'Backup UI does not explain separate encrypted off-site configuration'
);

if ($failures !== []) {
    fwrite(STDERR, "FAIL: backup source isolation smoke test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "PASS: {$checks} backup source isolation checks\n";
