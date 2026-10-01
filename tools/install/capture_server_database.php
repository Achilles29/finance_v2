<?php
declare(strict_types=1);

// One-time source-server migration. Never a customer installer; never runs SQL.
require_once dirname(__DIR__, 2).'/application/libraries/DeploymentConfig.php';
$root = dirname(__DIR__, 2);
try {
    if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || posix_geteuid() !== 0
        || CustomerLocalConfig::packaged($root)) throw new RuntimeException('SOURCE_SERVER_ROOT_CLI_REQUIRED');
    $groupName = $argv[1] ?? '';
    $group = posix_getgrnam($groupName);
    if (!$group || !preg_match('/\A[a-z_][a-z0-9_-]*\z/D', $groupName)) throw new RuntimeException('WEB_GROUP_REQUIRED');
    if (($argv[2] ?? '') !== '--http-protection-confirmed') {
        fwrite(STDERR, "Block HTTP /config and verify 403/404 first, then use: php tools/install/capture_server_database.php WEB_GROUP --http-protection-confirmed\n");
        exit(1);
    }
    if (CustomerLocalConfig::present($root)) throw new RuntimeException('LOCAL_CONFIG_EXISTS_NO_OVERWRITE');
    $file = $root.'/application/config/database.php';
    if (is_link($file) || !is_file($file) || str_contains((string)file_get_contents($file), 'DeploymentConfig')) {
        throw new RuntimeException('CAPTURE_CONVENTIONAL_DATABASE_BEFORE_REPLACING_LOADER');
    }
    define('BASEPATH', $root.'/system/');
    define('ENVIRONMENT', 'development');
    $db = [];
    require $file;
    $v = $db['default'] ?? [];
    foreach (['hostname','database','username','password'] as $key) {
        if (!is_string($v[$key] ?? null) || $v[$key] === '') throw new RuntimeException('DATABASE_CONFIG_INCOMPLETE');
    }
    if (($v['dbdriver'] ?? '') !== 'mysqli' || !empty($v['dsn']) || !empty($v['dbprefix'])
        || !empty($v['encrypt']) || !empty($v['pconnect']) || !empty($v['failover'])) {
        throw new RuntimeException('CUSTOM_CONNECTION_OPTIONS_REQUIRE_MANUAL_REVIEW');
    }
    foreach (['char_set'=>'utf8mb4','dbcollat'=>'utf8mb4_general_ci','stricton'=>false,
        'compress'=>false,'save_queries'=>false,'cache_on'=>false,'cachedir'=>'','swap_pre'=>''] as $key=>$expected) {
        if (array_key_exists($key, $v) && $v[$key] !== $expected) {
            throw new RuntimeException('CUSTOM_CONNECTION_OPTIONS_REQUIRE_MANUAL_REVIEW');
        }
    }
    $json = ['schema'=>1, 'scope'=>'database_only', 'database'=>[
        'host'=>$v['hostname'], 'port'=>(int)($v['port'] ?? 3306), 'socket'=>'',
        'name'=>$v['database'], 'user'=>$v['username'], 'password'=>$v['password']]];
    $mask = umask(0077);
    $backup = $root.'/tmp/config-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    if (!mkdir($backup, 0700) || !copy($file, $backup.'/database.php.before')
        || !copy($root.'/.user.ini', $backup.'/user.ini.before')) throw new RuntimeException('BACKUP_FAILED');
    chmod($backup.'/database.php.before', 0600);
    chmod($backup.'/user.ini.before', 0600);
    // Validate a private fixture first. Invalid credentials/schema/env conflicts must
    // never leave an invalid customer.json in the running application's config folder.
    $raw = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    if (!mkdir($backup.'/config', 0700)
        || file_put_contents($backup.'/config/customer.json', $raw) !== strlen($raw)) {
        throw new RuntimeException('PREFLIGHT_FILE_FAILED');
    }
    new DeploymentConfig(null, $backup);
    $dir = $root.'/config';
    if (is_link($dir) || realpath($dir) !== $dir || fileowner($dir) !== 0) throw new RuntimeException('CONFIG_DIRECTORY_UNSAFE');
    if (!chgrp($dir, $group['gid']) || !chmod($dir, 0750)) throw new RuntimeException('CONFIG_DIRECTORY_PERMISSION_FAILED');
    $path = $dir.'/customer.json';
    $handle = fopen($path, 'x');
    if ($handle === false) throw new RuntimeException('LOCAL_CONFIG_CREATE_FAILED');
    if (fwrite($handle, $raw) !== strlen($raw) || !fflush($handle)) throw new RuntimeException('LOCAL_CONFIG_WRITE_FAILED');
    fclose($handle);
    if (!chgrp($path, $group['gid']) || !chmod($path, 0640)) throw new RuntimeException('CONFIG_PERMISSION_FAILED');
    clearstatcache();
    $resolver = new DeploymentConfig(); // Includes conflict, path and schema checks. No database connection.
    if (!$resolver->hasExplicitDatabase() || CustomerLocalConfig::requiresProduction($root)) throw new RuntimeException('LOCAL_CONFIG_VALIDATION_FAILED');
    umask($mask);
    echo "PASS: local database settings captured; existing source config and database were not changed.\n";
    echo 'Private backup: '.substr($backup, strlen($root)+1)."\n";
} catch (Throwable $error) {
    // No exception details/credential values from included code or I/O are printed.
    fwrite(STDERR, "Capture failed. Check source-server mode, existing config, web group and file permissions. No SQL was run; no existing configuration was overwritten.\n");
    exit(1);
}
