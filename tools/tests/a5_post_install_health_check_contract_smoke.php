<?php

declare(strict_types=1);

define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/post_install_health_check.php';

$checks = 0;
$failures = [];
$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) echo 'PASS: ' . $label . PHP_EOL;
    else { $failures[] = $label; fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); }
};
$failureCode = static function (callable $callback): string {
    try { $callback(); } catch (A513HealthFailure $error) { return $error->failureCode; }
    return '';
};
$remove = static function (string $root, string $path) use (&$remove): void {
    if (!str_starts_with($path, $root)) throw new RuntimeException('unsafe cleanup');
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($root, $path . '/' . $entry);
    @rmdir($path);
};

$sourceRoot = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/finance-a513-health-' . bin2hex(random_bytes(6));
$releaseRoot = $tmp . '/release';
mkdir($releaseRoot . '/tools/db', 0700, true);
mkdir($releaseRoot . '/sql/baseline', 0700, true);
mkdir($tmp . '/bin', 0700, true);
register_shutdown_function(static function () use ($remove, $tmp): void { $remove($tmp, $tmp); });

$files = [
    'tools/db/migration_catalog.json',
    'tools/db/clean_install_baseline_policy.json',
    'tools/db/clean_install_baseline_guard.php',
    'tools/db/migration_runner.php',
    'tools/db/post_install_health_check.php',
    'sql/baseline/2026-09-05_clean_install_schema.sql',
];
$catalogSource = json_decode((string)file_get_contents($sourceRoot . '/tools/db/migration_catalog.json'), true);
// This fixture represents a release, not development's unregistered SQL drafts.
foreach ($catalogSource['migrations'] as $migration) $files[] = $migration['path'];
foreach ($catalogSource['legacy_unmanaged_sql'] as $path) $files[] = $path;
$files = array_values(array_unique($files));
sort($files, SORT_STRING);
foreach ($files as $relative) {
    $target = $releaseRoot . '/' . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
    copy($sourceRoot . '/' . $relative, $target);
    chmod($target, 0644);
}
$entries = [];
foreach ($files as $relative) {
    $path = $releaseRoot . '/' . $relative;
    $entries[] = ['path'=>$relative,'sha256'=>hash_file('sha256', $path),'size'=>filesize($path),'mode'=>'0644'];
}
$manifest = ['schema'=>'finance.release-artifact-manifest','schema_version'=>1,'source_epoch'=>1700000000,'files'=>$entries];
$manifestPath = $releaseRoot . '/RELEASE-MANIFEST.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
chmod($manifestPath, 0644);

$release = a513_validate_release($releaseRoot, $manifestPath);
$check(($release['baseline']['ok'] ?? false) === true && count($release['catalog']['migrations'] ?? []) === 27, 'release manifest binds the canonical baseline, seeds, catalog, and health checker');

$mutated = $releaseRoot . '/tools/db/post_install_health_check.php';
file_put_contents($mutated, "\n// drift", FILE_APPEND);
$check($failureCode(static fn() => a513_validate_release($releaseRoot, $manifestPath)) === 'release_file_drift', 'release file drift fails closed');
copy($sourceRoot . '/tools/db/post_install_health_check.php', $mutated);

$fake = <<<'PHP'
#!/usr/bin/php
<?php
$database = (string)end($argv);
$policy = strpos($database, '_clean_') !== false ? 'clean_install' : 'upgrade';
$mode = preg_replace('/^a513_(?:clean|upgrade)_/', '', $database) ?: 'ok';
while (($line = fgets(STDIN)) !== false) {
    if (strpos($line, '__A513_TABLE__') !== false) {
        echo "__A513_TABLE__\t" . ($mode === 'missing_table' ? '0' : '1') . "\n";
    } elseif (strpos($line, '__A513_LEDGER_COUNT__') !== false) {
        echo "__A513_LEDGER_COUNT__\t" . ($mode === 'ledger_count' ? '99' : ($policy === 'clean_install' || $mode === 'installed' || $mode === 'seed_receipt_drift' ? '27' : '26')) . "\n";
    } elseif (strpos($line, '__A513_LEDGER__') !== false) {
        $seed = strpos($line, bin2hex('2026-09-05d-a5-clean-install-reference-seed')) !== false;
        if ($seed && $policy === 'upgrade' && !in_array($mode, ['installed','seed_receipt_drift'], true)) echo "__A513_LEDGER__\t0\t0\n";
        else echo "__A513_LEDGER__\t1\t" . ($mode === 'ledger_drift' || ($seed && $mode === 'seed_receipt_drift') ? '0' : '1') . "\n";
    } elseif (strpos($line, '__A513_AUTH__') !== false) {
        echo "__A513_AUTH__\t1\t" . ($mode === 'permission_gap' ? '1' : '0') . "\t" . ($mode === 'owner_missing' ? '0' : '1') . "\n";
    } elseif (strpos($line, '__A513_SEED__') !== false) {
        $count = strpos($line, '`sys_matrix_group`') !== false ? 20 : (strpos($line, '`sys_page`') !== false ? 217 : (strpos($line, '`sys_menu`') !== false ? 253 : 10));
        if ($mode === 'seed_drift') $count++;
        echo "__A513_SEED__\t{$count}\n";
    } elseif (strpos($line, '__A513_TELEGRAM__') !== false) {
        echo "__A513_TELEGRAM__\t" . ($mode === 'telegram_on' ? '0' : '1') . "\n";
    }
    fflush(STDOUT);
}
PHP;
$client = $tmp . '/bin/mariadb';
file_put_contents($client, $fake);
chmod($client, 0700);
$option = $tmp . '/client.cnf';
$databaseName = $tmp . '/database-name';
file_put_contents($option, "[client]\nuser=fixture\n");
file_put_contents($databaseName, "a513_fixture\n");
chmod($option, 0600);
chmod($databaseName, 0600);
putenv('PATH=' . $tmp . '/bin');
$upgrade = a513_check_database($release, 'upgrade', $option, 'a513_upgrade_ok');
$check(($upgrade['migration_ledger_rows'] ?? null) === 26 && ($upgrade['reference_seed'] ?? '') === 'preserved_customer_state', 'upgrade health checks release files, required schema, exact ledger, RBAC, and owner without replacing customer seed');
$installed = a513_check_database($release, 'upgrade', $option, 'a513_upgrade_installed');
$check($installed['migration_ledger_rows'] === 27, 'upgrade accepts verified historical clean-install seed without rerunning it');
$check($failureCode(static fn() => a513_check_database($release, 'upgrade', $option, 'a513_upgrade_seed_receipt_drift')) === 'migration_ledger_drift', 'historical seed checksum drift is rejected');

$clean = a513_check_database($release, 'clean_install', $option, 'a513_clean_ok');
$check(($clean['migration_ledger_rows'] ?? null) === 27 && ($clean['reference_seed'] ?? '') === 'exact', 'clean-install health additionally checks exact reference seed and Telegram safe default');
$check($failureCode(static fn() => a513_check_database($release, 'clean_install', $option, 'a513_clean_permission_gap')) === 'superadmin_contract', 'missing canonical permission still blocks clean install');
$check(strpos(a513_permission_match_sql(), "BINARY p.page_code IN ('tg.guide','finance.accounting.index','system.guide.index','system.guide.server') THEN 0 ELSE 1") !== false
    && strpos(a513_permission_match_sql(), 'rp.can_view=1') === 0, 'view permission stays mandatory and static guide cannot mutate; module-specific policies are tested by adapter contract');

foreach (['missing_table'=>'required_table_missing','ledger_count'=>'migration_ledger_count','ledger_drift'=>'migration_ledger_drift','owner_missing'=>'owner_missing','seed_drift'=>'reference_seed_drift','telegram_on'=>'safe_default_drift'] as $mode => $expected) {
    $testPolicy = in_array($mode, ['seed_drift','telegram_on'], true) ? 'clean_install' : 'upgrade';
    $database = 'a513_' . ($testPolicy === 'clean_install' ? 'clean' : 'upgrade') . '_' . $mode;
    $check($failureCode(static fn() => a513_check_database($release, $testPolicy, $option, $database)) === $expected, $mode . ' health failure is detected');
}

$source = (string)file_get_contents($sourceRoot . '/tools/db/post_install_health_check.php');
$check(strpos($source, "PHP_SAPI !== 'cli'") !== false && strpos($source, 'credential_cli') !== false, 'health checker is CLI-only and rejects credential arguments');
$check(strpos($source, "WHERE BINARY migration_id=UNHEX('") !== false && strpos($source, 'release_file_drift') !== false, 'ledger identity and installed source integrity are byte-bound');

$remove($tmp, $tmp);
$check(!file_exists($tmp), 'health-check fixtures are cleaned');
if ($failures !== []) { fwrite(STDERR, count($failures) . ' A5.13 health check(s) failed.' . PHP_EOL); exit(1); }
echo 'All ' . $checks . ' A5.13 post-install health checks passed.' . PHP_EOL;
