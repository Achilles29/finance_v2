<?php
declare(strict_types=1);

/** Prints a non-mutating customer installation plan. Database mutation remains migration_runner's job. */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'plan' || count($argv) !== 3 || preg_match('/^--mode=(clean_install|upgrade)$/D', (string)$argv[2], $match) !== 1) {
    fwrite(STDERR, "Usage: php finance_install_plan.php plan --mode=clean_install|upgrade\n"); exit(2);
}
$root = realpath(dirname(__DIR__, 2));
if ($root === false) { fwrite(STDERR, "INSTALL PLAN BLOCKED root\n"); exit(1); }
$manifest = json_decode((string)file_get_contents($root . '/app-manifest.json'), true);
if (!is_array($manifest) || ($manifest['manifest_version'] ?? null) !== 2) { fwrite(STDERR, "INSTALL PLAN BLOCKED manifest\n"); exit(1); }
$command = [PHP_BINARY, $root . '/tools/db/migration_runner.php', 'plan', '--policy=' . $match[1]];
$process = proc_open($command, [0=>['file',PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
if (!is_resource($process)) { fwrite(STDERR, "INSTALL PLAN BLOCKED migration_runner\n"); exit(1); }
$stdout = (string)stream_get_contents($pipes[1]); $stderr = (string)stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
if ($code !== 0) { fwrite(STDERR, "INSTALL PLAN BLOCKED migration_catalog\n"); exit(1); }
$plan = json_decode(trim($stdout), true);
if (!is_array($plan) || ($plan['status'] ?? '') !== 'ok') { fwrite(STDERR, "INSTALL PLAN BLOCKED migration_output\n"); exit(1); }
$steps = ['verify_signed_artifact','verify_runtime_compatibility'];
if ($match[1] === 'clean_install') {
    $steps = array_merge($steps, ['create_empty_customer_database','apply_clean_baseline_and_managed_migrations','apply_clean_reference_seed','create_first_owner_via_onboarding','configure_business_profile','select_customer_menu_or_disable_legacy_content']);
} else {
    $steps = array_merge($steps, ['backup_customer_database_and_runtime','verify_restore_evidence','enable_maintenance_window','apply_managed_upgrade_migrations','preserve_customer_identity_and_accounts']);
}
$steps = array_merge($steps, ['prepare_upload_storage_as_php_fpm_user','run_post_install_health_check','verify_customer_urls_and_integrations','owner_acceptance_before_switch']);
echo json_encode(['status'=>'ok','execution'=>'plan_only','mode'=>$match[1],'product_code'=>$manifest['product_code'] ?? null,'version'=>$manifest['version'] ?? null,'steps'=>$steps,'migration_count'=>count((array)($plan['migrations'] ?? []))], JSON_UNESCAPED_SLASHES) . PHP_EOL;
