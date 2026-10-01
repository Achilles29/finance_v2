<?php

declare(strict_types=1);

const A512_OWNER_FILE_MAX_BYTES = 4096;
if (!defined('A5_MIGRATION_LIBRARY_ONLY')) define('A5_MIGRATION_LIBRARY_ONLY', true);
require_once __DIR__ . '/migration_runner.php';
if (!defined('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY')) define('FINANCE_A512_BASELINE_GUARD_LIBRARY_ONLY', true);
require_once __DIR__ . '/clean_install_baseline_guard.php';

function a512_owner_seed_counts(string $root): array
{
    $path = $root . '/tools/db/clean_install_baseline_policy.json';
    $checked = a512_validate_baseline($root, $path);
    if (empty($checked['ok'])) a5_fail('owner_seed_policy', 'Approved clean-install baseline is invalid.');
    $policy = json_decode((string)file_get_contents($path), true);
    $counts = $policy['seed']['post_apply_counts'] ?? [];
    foreach (['sys_page','sys_menu','auth_role_permission'] as $key) {
        if (!is_int($counts[$key] ?? null) || $counts[$key] < 1) a5_fail('owner_seed_policy', 'Expected clean-install counts are invalid.');
    }
    return $counts;
}

function a512_owner_file(string $root, string $path): array
{
    clearstatcache(true, $path);
    if ($path === '' || strpos($path, "\0") !== false || $path[0] !== '/'
        || is_link($path) || !is_file($path) || !is_readable($path)) {
        a5_fail('owner_file', 'Owner file must be an absolute readable regular non-link file.');
    }
    $real = realpath($path);
    $mode = fileperms($path);
    if ($real === false || $mode === false || (($mode & 0777) !== 0600)) {
        a5_fail('owner_file_permissions', 'Owner file permissions must be exactly 0600.');
    }
    if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        a5_fail('owner_file_repository', 'Owner file must be outside the repository.');
    }
    $raw = file_get_contents($real);
    if (!is_string($raw) || strlen($raw) < 2 || strlen($raw) > A512_OWNER_FILE_MAX_BYTES) {
        a5_fail('owner_file', 'Owner file is unreadable or has an invalid size.');
    }
    try {
        $owner = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        a5_fail('owner_file_json', 'Owner file JSON is malformed.');
    }
    if (!is_array($owner) || a5_is_list($owner) || !a5_exact_keys($owner, ['username','email','password'])) {
        a5_fail('owner_file_schema', 'Owner file must contain exactly username, email, and password.');
    }

    $username = is_string($owner['username']) ? trim($owner['username']) : '';
    $email = is_string($owner['email']) ? trim($owner['email']) : '';
    $password = is_string($owner['password']) ? $owner['password'] : '';
    if (preg_match('/\A[A-Za-z][A-Za-z0-9._-]{2,59}\z/D', $username) !== 1) {
        a5_fail('owner_username', 'Owner username must be 3-60 characters using letters, digits, dot, underscore, or dash.');
    }
    if ($email !== '' && (strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        a5_fail('owner_email', 'Owner email is invalid.');
    }
    $passwordLength = strlen($password);
    $classes = (int)(preg_match('/[a-z]/', $password) === 1)
        + (int)(preg_match('/[A-Z]/', $password) === 1)
        + (int)(preg_match('/[0-9]/', $password) === 1)
        + (int)(preg_match('/[^A-Za-z0-9]/', $password) === 1);
    if ($passwordLength < 12 || $passwordLength > 72 || $classes < 3
        || stripos($password, $username) !== false) {
        a5_fail('owner_password', 'Owner password does not satisfy the bootstrap strength policy.');
    }

    return ['username'=>$username, 'email'=>$email, 'password'=>$password];
}

function a512_bootstrap_owner(string $root, string $optionFile, string $databaseName, array $owner): array
{
    $expected = a512_owner_seed_counts($root);
    $passwordHash = password_hash($owner['password'], PASSWORD_BCRYPT, ['cost'=>12]);
    if (!is_string($passwordHash)) {
        a5_fail('owner_password_hash', 'Owner password could not be secured.');
    }
    $deadline = microtime(true) + a5_apply_timeout_seconds();
    $client = a5_client_open(a5_find_client(), $optionFile, $databaseName, $deadline);
    $locked = false;
    try {
        a5_client_send($client, "SELECT CONCAT('__A512_OWNER_LOCK__\\t',IF(GET_LOCK('finance_first_owner_bootstrap_v1',0)=1,'1','0')); ");
        $lock = a5_client_marker($client, '__A512_OWNER_LOCK__');
        if ($lock !== ['__A512_OWNER_LOCK__','1']) {
            a5_fail('owner_lock', 'First-owner bootstrap lock is unavailable.');
        }
        $locked = true;

        $probe = "SELECT CONCAT('__A512_OWNER_STATE__\\t',"
            . "(SELECT COUNT(*) FROM auth_user),'\\t',"
            . "(SELECT COUNT(*) FROM auth_user_role),'\\t',"
            . "(SELECT COUNT(*) FROM auth_role),'\\t',"
            . "(SELECT COUNT(*) FROM auth_role WHERE BINARY role_code='SUPERADMIN' AND is_active=1 AND division_scope_id IS NULL),'\\t',"
            . "(SELECT COUNT(*) FROM sys_page),'\\t',"
            . "(SELECT COUNT(*) FROM sys_menu),'\\t',"
            . "(SELECT COUNT(*) FROM auth_role_permission rp JOIN auth_role r ON r.id=rp.role_id WHERE r.role_code='SUPERADMIN'));";
        a5_client_send($client, $probe);
        $state = a5_client_marker($client, '__A512_OWNER_STATE__');
        if (count($state) !== 8 || array_filter(array_slice($state, 1), static fn($value): bool => !ctype_digit($value)) !== []) {
            a5_fail('owner_state', 'Clean-install owner state is malformed.');
        }
        $counts = array_map('intval', array_slice($state, 1));
        if ($counts[0] !== 0 || $counts[1] !== 0) {
            a5_fail('owner_exists', 'First-owner bootstrap is only allowed before any user or assignment exists.');
        }
        if ($counts[2] !== 1 || $counts[3] !== 1
            || $counts[4] !== $expected['sys_page']
            || $counts[5] !== $expected['sys_menu']
            || $counts[6] !== $expected['auth_role_permission']) {
            a5_fail('owner_seed', 'The approved clean-install seed has not been applied exactly.');
        }

        $emailSql = $owner['email'] === '' ? 'NULL' : a5_sql_value($owner['email']);
        $write = "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE; START TRANSACTION;"
            . " SELECT COUNT(*) INTO @a512_user_count FROM auth_user FOR UPDATE;"
            . " SELECT COUNT(*) INTO @a512_assignment_count FROM auth_user_role FOR UPDATE;"
            . " SELECT id INTO @a512_role_id FROM auth_role"
            . " WHERE BINARY role_code='SUPERADMIN' AND is_active=1 AND division_scope_id IS NULL FOR UPDATE;"
            . " INSERT INTO auth_user (employee_id,username,email,password_hash,is_active,created_at)"
            . " SELECT NULL," . a5_sql_value($owner['username']) . ',' . $emailSql . ','
            . a5_sql_value($passwordHash) . ",1,CURRENT_TIMESTAMP FROM DUAL"
            . " WHERE @a512_user_count=0 AND @a512_assignment_count=0 AND @a512_role_id IS NOT NULL;"
            . " SET @a512_owner_inserted=ROW_COUNT();"
            . " SET @a512_owner_id=IF(@a512_owner_inserted=1,LAST_INSERT_ID(),0);"
            . " INSERT INTO auth_user_role (user_id,role_id,assigned_by,assigned_at)"
            . " SELECT @a512_owner_id,@a512_role_id,@a512_owner_id,CURRENT_TIMESTAMP FROM DUAL"
            . " WHERE @a512_owner_inserted=1;"
            . " SET @a512_assignment_inserted=ROW_COUNT();"
            . " COMMIT;"
            . " SELECT CONCAT('__A512_OWNER_DONE__\\t',@a512_owner_id,'\\t',@a512_owner_inserted,'\\t',@a512_assignment_inserted);";
        a5_client_send($client, $write);
        $done = a5_client_marker($client, '__A512_OWNER_DONE__');
        if (count($done) !== 4 || !ctype_digit($done[1]) || (int)$done[1] < 1
            || $done[2] !== '1' || $done[3] !== '1') {
            a5_fail('owner_write', 'First-owner bootstrap postcondition failed.');
        }
        return ['user_id'=>(int)$done[1], 'role'=>'SUPERADMIN'];
    } finally {
        if ($locked && is_resource($client['process'])) {
            @fwrite($client['pipes'][0], "SELECT CONCAT('__A512_OWNER_RELEASE__\\t',IF(RELEASE_LOCK('finance_first_owner_bootstrap_v1')=1,'1','0'));\n");
            @fflush($client['pipes'][0]);
            try {
                a5_client_marker($client, '__A512_OWNER_RELEASE__');
            } catch (Throwable $ignored) {
            }
        }
        a5_client_close($client, false);
    }
}

if (defined('FINANCE_A512_OWNER_BOOTSTRAP_LIBRARY_ONLY')) {
    return;
}

try {
    if (PHP_SAPI !== 'cli') {
        a5_fail('cli_only', 'First-owner bootstrap is CLI-only.');
    }
    $root = realpath(dirname(__DIR__, 2));
    if ($root === false) {
        a5_fail('root_missing', 'Repository root is unavailable.');
    }
    $arguments = $argv ?? [];
    $mode = $arguments[1] ?? '';
    $optionPath = $databaseNamePath = $ownerPath = null;
    foreach (array_slice($arguments, 2) as $argument) {
        if (!is_string($argument)
            || preg_match('/^--(?:password|passwd|credential|secret|token|user|host|database)(?:=|$)/i', $argument) === 1) {
            a5_fail('credential_cli', 'Credential command-line arguments are forbidden.');
        }
        if (strpos($argument, '--defaults-extra-file=') === 0 && $optionPath === null) {
            $optionPath = substr($argument, 22);
        } elseif (strpos($argument, '--database-name-file=') === 0 && $databaseNamePath === null) {
            $databaseNamePath = substr($argument, 21);
        } elseif (strpos($argument, '--owner-file=') === 0 && $ownerPath === null) {
            $ownerPath = substr($argument, 13);
        } else {
            a5_fail('usage', 'Bootstrap arguments are malformed.');
        }
    }
    if (!is_string($ownerPath) || ($mode !== 'validate-owner' && $mode !== 'apply')) {
        a5_fail('usage', 'Use validate-owner --owner-file=FILE or apply with defaults, database-name, and owner files.');
    }
    $owner = a512_owner_file($root, $ownerPath);
    if ($mode === 'validate-owner') {
        if ($optionPath !== null || $databaseNamePath !== null) {
            a5_fail('usage', 'Owner validation accepts only --owner-file.');
        }
        a5_emit(['status'=>'ok','mode'=>'validate-owner']);
        exit(0);
    }
    if (!is_string($optionPath) || !is_string($databaseNamePath)) {
        a5_fail('usage', 'Apply requires defaults, database-name, and owner files.');
    }
    $optionFile = a5_assert_apply_security($root, $optionPath);
    $databaseName = a5_read_database_name($root, $databaseNamePath);
    $result = a512_bootstrap_owner($root, $optionFile, $databaseName, $owner);
    a5_emit(['status'=>'ok','mode'=>'apply'] + $result);
} catch (A5MigrationFailure $error) {
    fwrite(STDERR, json_encode(['status'=>'error','code'=>$error->failureCode,'message'=>$error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($error->failureCode === 'usage' ? 2 : 1);
}
