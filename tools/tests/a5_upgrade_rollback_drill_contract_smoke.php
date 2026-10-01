<?php

declare(strict_types=1);

define('A513_UPGRADE_ROLLBACK_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/disposable_upgrade_rollback_drill.php';

$checks = 0;
$failures = [];
$check = static function (bool $ok, string $label) use (&$checks, &$failures): void {
    $checks++;
    if ($ok) echo 'PASS: ' . $label . PHP_EOL;
    else { $failures[] = $label; fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); }
};
$remove = static function (string $root, string $path) use (&$remove): void {
    if (!str_starts_with($path, $root)) throw new RuntimeException('unsafe cleanup');
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($root, $path . '/' . $entry);
    @rmdir($path);
};

$root = dirname(__DIR__, 2);
$source = (string)file_get_contents($root . '/tools/db/disposable_upgrade_rollback_drill.php');
$check(strpos($source, "define('A511_DISPOSABLE_RESTORE_LIBRARY_ONLY', true)") !== false, 'rollback drill reuses the hardened disposable restore boundary');
$check(strpos($source, "define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true)") !== false, 'rollback drill uses the canonical post-install health checker');
$check(strpos($source, "count(a5_plan(\$release['catalog'], 'upgrade'))") !== false && strpos($source, "'applied'] ?? null) !== \$upgradePlanCount") !== false && strpos($source, "'migration_ledger_rows'] ?? null) !== \$upgradePlanCount") !== false, 'upgrade derives the exact migration count from the release catalog');
$check(strpos($source, 'a513_rollback_canary') !== false && strpos($source, "'migration_ledger_count'") !== false, 'controlled failed-upgrade state must be detected before rollback');
$check(substr_count($source, 'a511_restore_archive($archive, $targetOption, $target, $timeout)') === 2, 'the same verified backup is restored before upgrade and during rollback');
$check(substr_count($source, 'a510_load_bundle($bundleDir)') >= 2, 'backup bundle is reverified immediately before rollback restore');
$check(strpos($source, "TABLE_NAME='a513_rollback_canary'") !== false && strpos($source, "TABLE_NAME='sys_schema_migration'") !== false, 'rollback verifies both schema canary and prior ledger state');
$check(strpos($source, 'a513r_seed_digest') !== false && strpos($source, 'hash_equals((string)$seedBefore, $seedAfter)') !== false, 'reference seed digest must match before and after rollback');
$check(strpos($source, "\$release['root'].'/tools/db/migration_runner.php'") !== false && strpos($source, 'a511_same_source($sourceBefore, $sourceAfter)') !== false && strpos($source, 'hash_equals($archiveHash, $hashAfter)') !== false, 'upgrade executes from the verified release root and backup identity remains immutable');
$check(strpos($source, "DROP DATABASE IF EXISTS") !== false && strpos($source, 'a511_counts($adminOption, $target, $target, $accountHost, $timeout)') !== false, 'disposable database and account cleanup is verified');
$check(strpos($source, 'credential_cli') !== false && strpos($source, "PHP_SAPI !== 'cli'") !== false, 'drill is CLI-only and forbids credential values in arguments');

$tmp = sys_get_temp_dir() . '/finance-a513-rollback-contract-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$evidence = ['format'=>'finance-a5-upgrade-rollback','version'=>1,'status'=>'error','failure_code'=>'fixture'];
$path = a513r_write_evidence($tmp, '0123456789abcdef', $evidence);
$check(is_file($path) && (fileperms($path) & 0777) === 0600 && json_decode((string)file_get_contents($path), true) === $evidence, 'rollback evidence is private and canonical JSON');
$remove($tmp, $tmp);
$check(!file_exists($tmp), 'rollback contract fixtures are cleaned');

if ($failures !== []) { fwrite(STDERR, count($failures) . ' A5.13 rollback contract check(s) failed.' . PHP_EOL); exit(1); }
echo 'All ' . $checks . ' A5.13 rollback contract checks passed.' . PHP_EOL;
