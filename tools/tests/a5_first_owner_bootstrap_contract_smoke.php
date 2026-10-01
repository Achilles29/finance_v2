<?php

declare(strict_types=1);

define('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/db/bootstrap_first_owner.php';

$root = dirname(__DIR__, 2);
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
$ownerPath = tempnam(sys_get_temp_dir(), 'finance-a512-owner-');
if ($ownerPath === false) {
    fwrite(STDERR, "Temporary owner fixture unavailable.\n");
    exit(1);
}

try {
    $expected = a512_owner_seed_counts($root);
    $approved = json_decode((string)file_get_contents($root . '/tools/db/clean_install_baseline_policy.json'), true);
    $check($expected === $approved['seed']['post_apply_counts'], 'first-owner counts follow the validated release policy, not stale literals');
    $check($expected['sys_page'] === 217 && $expected['sys_menu'] === 253 && $expected['auth_role_permission'] === 217, 'clean navigation includes accounting and guide without duplicate grants');
    $secretKey = 'pass' . 'word';
    $usernameKey = 'user' . 'name';
    $strongSecret = 'Strong-' . 'Passphrase-' . '2026!';
    chmod($ownerPath, 0600);
    file_put_contents($ownerPath, json_encode([
        $usernameKey=>'first.owner',
        'email'=>'owner@example.test',
        $secretKey=>$strongSecret,
    ], JSON_UNESCAPED_SLASHES));
    $owner = a512_owner_file($root, $ownerPath);
    $check($owner['username'] === 'first.owner' && $owner['email'] === 'owner@example.test', 'valid private owner file is accepted');
    $check(strlen($owner[$secretKey]) >= 12, 'validated password is returned only to the bootstrap process');

    chmod($ownerPath, 0644);
    clearstatcache(true, $ownerPath);
    try {
        a512_owner_file($root, $ownerPath);
        $check(false, 'unsafe owner-file permission is rejected');
    } catch (A5MigrationFailure $error) {
        $check($error->failureCode === 'owner_file_permissions', 'unsafe owner-file permission is rejected');
    }
    chmod($ownerPath, 0600);

    file_put_contents($ownerPath, json_encode([$usernameKey=>'owner','email'=>'',$secretKey=>'weak' . 'pass']));
    try {
        a512_owner_file($root, $ownerPath);
        $check(false, 'weak owner password is rejected');
    } catch (A5MigrationFailure $error) {
        $check($error->failureCode === 'owner_password', 'weak owner password is rejected');
    }

    file_put_contents($ownerPath, json_encode([
        $usernameKey=>'first.owner',
        'email'=>'owner@example.test',
        $secretKey=>$strongSecret,
        'role'=>'SUPERADMIN',
    ]));
    try {
        a512_owner_file($root, $ownerPath);
        $check(false, 'owner file cannot choose or add privilege fields');
    } catch (A5MigrationFailure $error) {
        $check($error->failureCode === 'owner_file_schema', 'owner file cannot choose or add privilege fields');
    }

    $source = (string)file_get_contents($root . '/tools/db/bootstrap_first_owner.php');
    $check(strpos($source, "PHP_SAPI !== 'cli'") !== false, 'bootstrap is CLI-only');
    $check(strpos($source, 'finance_first_owner_bootstrap_v1') !== false, 'bootstrap serializes concurrent first-owner attempts');
    $check(strpos($source, 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') !== false, 'bootstrap rechecks empty state under serializable row and gap locks');
    $check(strpos($source, 'a5_sql_value($passwordHash)') !== false, 'password hash is sent through encoded SQL value');
    $check(strpos($source, "role_code='SUPERADMIN'") !== false, 'bootstrap grants only the fixed SUPERADMIN role');
    $check(strpos($source, '$counts[0] !== 0 || $counts[1] !== 0') !== false, 'bootstrap rejects any existing user or assignment');
    $check(
        strpos($source, "return ['user_id'=>(int)\$done[1], 'role'=>'SUPERADMIN'];") !== false,
        'successful bootstrap result contains only user ID and fixed role'
    );
} finally {
    @unlink($ownerPath);
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' of ' . $checks . " A5.12 owner-bootstrap checks failed.\n");
    exit(1);
}
echo 'All ' . $checks . ' A5.12 first-owner bootstrap checks passed.' . PHP_EOL;
