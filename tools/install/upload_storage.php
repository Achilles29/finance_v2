<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/application/libraries/Upload_storage_policy.php';
try {
    if (PHP_SAPI !== 'cli' || count($argv) !== 2 || !in_array($argv[1], ['check', 'prepare'], true)) {
        throw new RuntimeException('usage: php tools/install/upload_storage.php check|prepare (run as PHP-FPM user)');
    }
    $root = dirname(__DIR__, 2);
    $result = $argv[1] === 'prepare' ? Upload_storage_policy::prepare($root) : Upload_storage_policy::inspect($root);
    $ready = count(array_filter($result, static fn(array $row): bool => $row['status'] !== 'READY')) === 0;
    $rootUser = function_exists('posix_geteuid') && posix_geteuid() === 0;
    echo json_encode(['status' => $ready && !$rootUser ? 'ok' : 'needs_attention', 'scope' => 'upload_directories_only', 'root_user_check_not_valid_for_php_fpm' => $rootUser, 'directories' => $result], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($ready && !$rootUser ? 0 : 1);
} catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
