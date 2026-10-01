<?php

declare(strict_types=1);

define('A511_DISPOSABLE_RESTORE_LIBRARY_ONLY', true);
require_once __DIR__ . '/disposable_restore_drill.php';
define('A513_POST_INSTALL_HEALTH_LIBRARY_ONLY', true);
require_once __DIR__ . '/post_install_health_check.php';

final class A513RollbackFailure extends RuntimeException
{
    public string $failureCode;
    public function __construct(string $code, string $message) { parent::__construct($message); $this->failureCode = $code; }
}

function a513r_fail(string $code, string $message): void
{
    throw new A513RollbackFailure($code, $message);
}

function a513r_seed_digest(string $optionFile, string $database, int $timeout): string
{
    $output = a511_client($optionFile, 'CHECKSUM TABLE `sys_matrix_group`,`sys_page`,`sys_menu`,`sys_page_alias`,`auth_role`,`auth_role_permission`', $timeout, $database);
    if ($output === '' || preg_match('/(?:^|\n)[^\t]+\t(?:NULL|[0-9]+)(?:\n|$)/', $output) !== 1) {
        a513r_fail('seed_digest', 'Reference seed digest could not be calculated.');
    }
    return hash('sha256', $output);
}

function a513r_json_tool(array $command, int $timeout, string $root): array
{
    $result = a511_process($command, '', $timeout, $root, ['PATH'=>'/usr/bin:/bin','TZ'=>'Asia/Jakarta','LANG'=>'C']);
    if ($result['exit'] !== 0) a513r_fail('verification_tool', 'Upgrade verification tool failed with redacted output.');
    $decoded = json_decode(trim($result['stdout']), true);
    if (!is_array($decoded)) a513r_fail('verification_protocol', 'Upgrade verification tool returned malformed output.');
    return $decoded;
}

function a513r_write_evidence(string $directory, string $runId, array $evidence): string
{
    $name = 'a5_upgrade_rollback_' . $runId . '.json';
    $final = $directory . DIRECTORY_SEPARATOR . $name;
    $stage = $directory . DIRECTORY_SEPARATOR . '.' . $name . '.stage';
    if (file_exists($final) || file_exists($stage) || is_link($final) || is_link($stage)) a513r_fail('evidence_exists', 'Evidence output already exists.');
    $handle = @fopen($stage, 'xb');
    if (!is_resource($handle)) a513r_fail('evidence_write', 'Evidence stage could not be created.');
    $json = json_encode($evidence, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $ok = is_string($json) && fwrite($handle, $json) === strlen($json) && fflush($handle)
        && (!function_exists('fsync') || fsync($handle));
    fclose($handle);
    if (!$ok || !chmod($stage, 0600) || !rename($stage, $final)) {
        @unlink($stage);
        a513r_fail('evidence_write', 'Evidence could not be published.');
    }
    return $final;
}

function a513r_run(string $bundleDir, string $adminOption, string $evidenceDir, string $releaseRoot, string $releaseManifest): array
{
    $root = a511_root();
    $adminOption = a511_private_file($adminOption, $root);
    $evidenceDir = a511_private_directory($evidenceDir, $root);
    $bundleDir = a510_bundle_directory($bundleDir);
    $release = a513_validate_release($releaseRoot, $releaseManifest);
    $timeout = a511_timeout_seconds();
    $runId = bin2hex(random_bytes(8));
    $target = a511_target_name($runId);
    $endpoint = a511_option_endpoint($adminOption);
    $accountHost = ($endpoint['host'] ?? '') === '127.0.0.1' ? '127.0.0.1' : 'localhost';
    $password = bin2hex(random_bytes(24));
    $targetOption = '';
    $targetDatabaseNameFile = '';
    $ownershipClaimed = false;
    $failureCode = null;
    $seedBefore = null;
    $seedAfter = null;
    $healthFailure = null;
    $phases = [
        'precondition'=>false,'provision'=>false,'restore_before'=>false,'upgrade'=>false,
        'health_pass'=>false,'failure_detected'=>false,'rollback_restore'=>false,
        'schema_restored'=>false,'ledger_restored'=>false,'seed_restored'=>false,
        'source_unchanged'=>false,'cleanup'=>false,
    ];
    $started = microtime(true);
    $manifest = a510_load_bundle($bundleDir);
    $archive = $bundleDir . DIRECTORY_SEPARATOR . $manifest['artifact']['path'];
    $sourceBefore = lstat($archive);
    $archiveHash = $manifest['artifact']['sha256'];
    try {
        if (!is_array($sourceBefore)) a513r_fail('source_identity', 'Backup source identity is unavailable.');
        [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
        if ($dbCount !== 0 || $userCount !== 0) a513r_fail('target_exists', 'Generated disposable target already exists.');
        $phases['precondition'] = true;
        $ownershipClaimed = true;
        $account = a511_account($target, $accountHost);
        a511_client($adminOption, 'CREATE DATABASE `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;CREATE USER ' . $account . " IDENTIFIED BY '" . $password . "';GRANT ALL PRIVILEGES ON `" . $target . '`.* TO ' . $account, $timeout);
        [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
        if ($dbCount !== 1 || $userCount !== 1) a513r_fail('provision_verify', 'Disposable target provisioning could not be verified.');
        $targetOption = a511_write_target_option($evidenceDir, $runId, $endpoint, $target, $password, $target);
        $targetDatabaseNameFile = a511_write_target_database_name($evidenceDir, $runId, $target);
        $phases['provision'] = true;

        a511_scan_archive($archive, $timeout);
        $manifest = a510_load_bundle($bundleDir);
        $archive = $bundleDir . DIRECTORY_SEPARATOR . $manifest['artifact']['path'];
        a511_restore_archive($archive, $targetOption, $target, $timeout);
        if (a511_client($targetOption, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_schema_migration'", $timeout, $target) !== '0') {
            a513r_fail('rollback_source_state', 'Rollback source must predate the managed migration registry.');
        }
        $seedBefore = a513r_seed_digest($targetOption, $target, $timeout);
        $phases['restore_before'] = true;

        $upgradePlanCount = count(a5_plan($release['catalog'], 'upgrade'));
        $upgrade = a513r_json_tool([PHP_BINARY,$release['root'].'/tools/db/migration_runner.php','apply','--policy=upgrade','--defaults-extra-file='.$targetOption,'--database-name-file='.$targetDatabaseNameFile], $timeout, $release['root']);
        if (($upgrade['applied'] ?? null) !== $upgradePlanCount || ($upgrade['skipped'] ?? null) !== 0) a513r_fail('upgrade_apply', 'Disposable upgrade did not apply the exact release plan.');
        $phases['upgrade'] = true;
        $health = a513_check_database($release, 'upgrade', $targetOption, $target);
        if (($health['status'] ?? '') !== 'ok' || ($health['migration_ledger_rows'] ?? null) !== $upgradePlanCount) a513r_fail('health_after_upgrade', 'Post-upgrade health check did not pass.');
        $phases['health_pass'] = true;

        a511_client($targetOption, "CREATE TABLE `a513_rollback_canary` (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB;INSERT INTO sys_schema_migration (migration_id,filename,checksum_sha256,catalog_version,tool_version,classification,policies,applied_by) VALUES ('a513-failed-upgrade-canary','sql/a513_failed_upgrade.sql',REPEAT('0',64),1,'fixture','schema','upgrade','rollback_drill')", $timeout, $target);
        try {
            a513_check_database($release, 'upgrade', $targetOption, $target);
        } catch (A513HealthFailure $error) {
            $healthFailure = $error->failureCode;
        }
        if ($healthFailure !== 'migration_ledger_count') a513r_fail('failure_detection', 'Injected failed-upgrade state was not rejected by health check.');
        $phases['failure_detected'] = true;

        a511_client($adminOption, 'DROP DATABASE `' . $target . '`;CREATE DATABASE `' . $target . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $timeout);
        $manifest = a510_load_bundle($bundleDir);
        $archive = $bundleDir . DIRECTORY_SEPARATOR . $manifest['artifact']['path'];
        a511_restore_archive($archive, $targetOption, $target, $timeout);
        $phases['rollback_restore'] = true;
        $schemaProbe = a513r_json_tool([PHP_BINARY,$root.'/tools/db/schema_fingerprint_probe.php','probe','--defaults-extra-file='.$targetOption,'--database-name-file='.$targetDatabaseNameFile], $timeout, $root);
        $canaryCount = a511_client($targetOption, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='a513_rollback_canary'", $timeout, $target);
        if (($schemaProbe['candidate_eligible'] ?? null) !== 4 || ($schemaProbe['candidate_total'] ?? null) !== 4 || $canaryCount !== '0') {
            a513r_fail('schema_rollback', 'Rollback did not restore the prior schema state.');
        }
        $phases['schema_restored'] = true;
        if (a511_client($targetOption, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sys_schema_migration'", $timeout, $target) !== '0') {
            a513r_fail('ledger_rollback', 'Rollback did not restore the prior migration-ledger state.');
        }
        $phases['ledger_restored'] = true;
        $seedAfter = a513r_seed_digest($targetOption, $target, $timeout);
        if (!hash_equals((string)$seedBefore, $seedAfter)) a513r_fail('seed_rollback', 'Rollback did not restore the prior reference seed state.');
        $phases['seed_restored'] = true;
        clearstatcache(true, $archive);
        $sourceAfter = lstat($archive);
        $hashAfter = hash_file('sha256', $archive);
        if (!is_array($sourceAfter) || !a511_same_source($sourceBefore, $sourceAfter) || !is_string($hashAfter) || !hash_equals($archiveHash, $hashAfter)) {
            a513r_fail('source_changed', 'Backup source changed during rollback drill.');
        }
        $phases['source_unchanged'] = true;
    } catch (Throwable $error) {
        $failureCode = $error instanceof A513RollbackFailure ? $error->failureCode
            : ($error instanceof A513HealthFailure ? 'health_' . $error->failureCode
                : ($error instanceof A511Failure ? 'restore_' . $error->failureCode
                    : ($error instanceof A510BundleFailure ? 'bundle_' . $error->failureCode : 'unexpected_failure')));
    } finally {
        if (!a511_remove_target_temporary_file($targetDatabaseNameFile, $evidenceDir, $runId)
            || !a511_remove_target_temporary_file($targetOption, $evidenceDir, $runId)) $failureCode = 'cleanup_failed';
        if ($ownershipClaimed) {
            try {
                a511_client($adminOption, 'DROP DATABASE IF EXISTS `' . $target . '`;DROP USER IF EXISTS ' . a511_account($target, $accountHost), $timeout);
                [$dbCount,$userCount] = a511_counts($adminOption, $target, $target, $accountHost, $timeout);
                if ($dbCount !== 0 || $userCount !== 0) a513r_fail('cleanup_verify', 'Disposable cleanup could not be verified.');
                $phases['cleanup'] = true;
            } catch (Throwable $cleanupError) { $failureCode = 'cleanup_failed'; }
        }
    }
    $status = $failureCode === null && !in_array(false, $phases, true) ? 'ok' : 'error';
    $evidence = [
        'format'=>'finance-a5-upgrade-rollback','version'=>1,'run_id'=>$runId,'status'=>$status,'failure_code'=>$failureCode,
        'backup_manifest_sha256'=>hash_file('sha256', $bundleDir . '/backup_bundle_manifest.json'),
        'backup_archive_sha256'=>$archiveHash,'release_manifest_sha256'=>$release['manifest_sha256'],
        'migration_catalog_sha256'=>hash_file('sha256', $release['root'] . '/tools/db/migration_catalog.json'),
        'health_failure_trigger'=>$healthFailure,'seed_digest_before'=>$seedBefore,'seed_digest_after'=>$seedAfter,
        'phases'=>$phases,'cleanup_verified'=>$phases['cleanup'],'duration_ms'=>(int)round((microtime(true)-$started)*1000),
    ];
    $evidencePath = a513r_write_evidence($evidenceDir, $runId, $evidence);
    if ($status !== 'ok') a513r_fail($failureCode ?? 'drill_failed', 'Disposable upgrade/rollback drill failed; redacted evidence was written.');
    return ['status'=>'ok','mode'=>'run','run_id'=>$runId,'health_after_upgrade'=>'ok','failure_detected'=>$healthFailure,'rollback'=>'verified','cleanup_verified'=>true,'evidence'=>basename($evidencePath)];
}

if (defined('A513_UPGRADE_ROLLBACK_LIBRARY_ONLY') && A513_UPGRADE_ROLLBACK_LIBRARY_ONLY) return;

try {
    if (PHP_SAPI !== 'cli') a513r_fail('cli_only', 'Upgrade/rollback drill is CLI-only.');
    $args = $argv ?? [];
    foreach ($args as $arg) if (is_string($arg) && preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database|target|run-id)(?:=|$)/i', $arg)) {
        a513r_fail('credential_cli', 'Credential and target arguments are forbidden.');
    }
    if (($args[1] ?? '') !== 'run') a513r_fail('usage', 'Use run with backup bundle, private admin option, evidence directory, release root, and manifest.');
    $options = [];
    foreach (array_slice($args, 2) as $arg) {
        if (!is_string($arg) || preg_match('/^--(bundle-dir|admin-defaults-extra-file|evidence-dir|release-root|release-manifest)=(.+)$/D', $arg, $match) !== 1 || isset($options[$match[1]])) a513r_fail('usage', 'Run arguments are malformed.');
        $options[$match[1]] = $match[2];
    }
    if (array_keys($options) !== ['bundle-dir','admin-defaults-extra-file','evidence-dir','release-root','release-manifest']) a513r_fail('usage', 'Run arguments are incomplete or out of order.');
    a511_assert_environment();
    a511_emit(a513r_run($options['bundle-dir'], $options['admin-defaults-extra-file'], $options['evidence-dir'], $options['release-root'], $options['release-manifest']));
} catch (A513RollbackFailure $error) {
    a511_emit(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()], STDERR);
    exit($error->failureCode === 'usage' ? 2 : 1);
} catch (A513HealthFailure $error) {
    a511_emit(['status'=>'error','code'=>'health_' . $error->failureCode,'message'=>'Release health contract validation failed.'], STDERR);
    exit(1);
} catch (A511Failure $error) {
    a511_emit(['status'=>'error','code'=>'restore_' . $error->failureCode,'message'=>'Disposable restore boundary validation failed.'], STDERR);
    exit(1);
} catch (A510BundleFailure $error) {
    a511_emit(['status'=>'error','code'=>'bundle_' . $error->failureCode,'message'=>'Backup bundle validation failed.'], STDERR);
    exit(1);
} catch (Throwable $error) {
    a511_emit(['status'=>'error','code'=>'unexpected_failure','message'=>'Upgrade/rollback drill failed.'], STDERR);
    exit(1);
}
