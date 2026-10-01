<?php

declare(strict_types=1);

define('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/clean_install_baseline_guard.php';

$root = dirname(__DIR__, 2);
$policy = json_decode((string)file_get_contents($root . '/tools/db/clean_install_baseline_policy.json'), true);
$schema = (string)file_get_contents($root . '/' . $policy['schema']['path']);
$seed = (string)file_get_contents($root . '/' . $policy['seed']['path']);
$checks = 0;
$failures = [];
$check = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$result = a512_validate_baseline($root, $root . '/tools/db/clean_install_baseline_policy.json');
$check($result['ok'] === true && $result['table_count'] === 296, 'canonical schema baseline and checksum validate');
$check($result['seed_status'] === 'approved_minimal', 'minimal customer-neutral seed is explicitly classified');
$check($result['seed_rows'] === $policy['seed']['artifact_rows'], 'seed row inventory is policy-locked');
$check(substr_count($schema, 'CREATE TABLE `') === 296, 'baseline contains exactly 296 unique schema tables');
$check(strpos($schema, "DEFAULT 'local-dev-token'") === false, 'baseline contains no local development bot token default');
$check(preg_match('/\b(?:INSERT\s+INTO|REPLACE\s+INTO|DELETE\s+FROM|LOAD\s+DATA)\b/i', $schema) !== 1, 'baseline contains no row data statements');
// These indexes are added unconditionally by immutable managed migrations.
// Pre-creating them in the baseline breaks clean install with duplicate-key errors.
foreach (['cashier' => '2026-09-06f_pos_mobile_cashier_close_step_up.sql',
    'reservation' => '2026-09-06g_pos_mobile_reservation_refund_step_up.sql'] as $kind => $file) {
    $index = 'idx_pos_mobile_sensitive_action_proof_' . $kind . '_consume';
    $migration = (string)file_get_contents($root . '/sql/' . $file);
    $check(strpos($schema, 'KEY `' . $index . '`') === false
        && strpos($migration, 'ADD KEY `' . $index . '`') !== false, 'managed migration, not baseline, owns ' . $kind . ' index');
}

$cases = [
    'customer insert' => $schema . "\nINSERT INTO auth_user (id) VALUES (1);",
    'credential default' => str_replace("`bot_api_token` varchar(100) NOT NULL DEFAULT ''", "`bot_api_token` varchar(100) NOT NULL DEFAULT 'secret-value'", $schema),
    'operational table' => str_replace('CREATE TABLE `asset_category`', 'CREATE TABLE `backup_asset_category`', $schema),
    'auto increment state' => str_replace('ENGINE=InnoDB DEFAULT', 'ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT', $schema),
    'absolute path' => $schema . "\n-- /www/customer/private",
    'missing FK disable' => str_replace('SET FOREIGN_KEY_CHECKS=0;', '', $schema),
];
foreach ($cases as $label => $fixture) {
    $errors = a512_schema_errors($fixture, $policy['schema']);
    $check($errors !== [], $label . ' is rejected fail-closed');
}

$seedCases = [
    'customer identity' => str_replace('Menu Book', 'Menu Book NAMUA', $seed),
    'owner account' => $seed . "\nINSERT INTO auth_user (username) VALUES ('owner');",
    'operational role' => str_replace("('SUPERADMIN', 'Super Admin'", "('ADMIN', 'Admin'", $seed),
    'upgrade upsert' => $seed . "\nON DUPLICATE KEY UPDATE is_active=1;",
    'absolute URL' => str_replace("'dashboard'", "'https://customer.example/dashboard'", $seed),
];
foreach ($seedCases as $label => $fixture) {
    $errors = a512_seed_errors($fixture, $policy['seed']);
    $check($errors !== [], $label . ' seed is rejected fail-closed');
}
$check(
    $policy['seed']['execution_policy'] === 'clean_install_only'
        && count($policy['seed']['forbidden_domains']) === 6
        && !in_array('auth_user', $policy['seed']['allowed_source_tables'], true),
    'seed policy excludes every customer and runtime domain'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A5.12 baseline guard check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' A5.12 clean-install baseline guard checks passed.' . PHP_EOL;
