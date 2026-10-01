<?php

declare(strict_types=1);

/**
 * Read-only staging probe for effective multi-role/division scope and POS
 * terminal/token binding. This file deliberately accepts SELECT only.
 */

$root = dirname(__DIR__, 2);
defined('BASEPATH') || define('BASEPATH', $root . '/system/');
defined('APPPATH') || define('APPPATH', $root . '/application/');
defined('ENVIRONMENT') || define('ENVIRONMENT', getenv('CI_ENV') ?: 'production');

$db = [];
$active_group = 'default';
$query_builder = true;
require APPPATH . 'config/database.php';
$environmentConfig = APPPATH . 'config/' . ENVIRONMENT . '/database.php';
if (is_file($environmentConfig)) {
    require $environmentConfig;
}

$group = isset($db[$active_group]) && is_array($db[$active_group]) ? $db[$active_group] : null;
if ($group === null || strtolower((string)($group['dbdriver'] ?? 'mysqli')) !== 'mysqli') {
    fwrite(STDERR, 'RBAC scope probe unavailable: active mysqli configuration was not found.' . PHP_EOL);
    exit(2);
}

mysqli_report(MYSQLI_REPORT_OFF);
$connection = @new mysqli(
    (string)($group['hostname'] ?? ''),
    (string)($group['username'] ?? ''),
    (string)($group['password'] ?? ''),
    (string)($group['database'] ?? ''),
    (int)($group['port'] ?? ini_get('mysqli.default_port')),
    isset($group['socket']) ? (string)$group['socket'] : null
);
if ($connection->connect_errno) {
    fwrite(STDERR, 'RBAC scope probe unavailable: connection failed (credentials hidden).' . PHP_EOL);
    exit(2);
}
$connection->set_charset('utf8mb4');

$select = static function (string $sql) use ($connection): array {
    if (preg_match('/^\s*SELECT\b/i', $sql) !== 1) {
        throw new RuntimeException('Read-only RBAC scope probe rejected a non-SELECT statement.');
    }
    $result = $connection->query($sql);
    if ($result === false) {
        throw new RuntimeException('Read-only RBAC scope query failed.');
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
};
$quote = static function (string $value) use ($connection): string {
    return "'" . $connection->real_escape_string($value) . "'";
};

$requiredSchema = [
    'auth_user' => ['id', 'is_active'],
    'auth_user_role' => ['user_id', 'role_id'],
    'auth_role' => ['id', 'role_code', 'division_scope_id', 'is_active'],
    'auth_role_permission' => ['role_id', 'page_id', 'can_view', 'can_create', 'can_edit', 'can_delete', 'can_export'],
    'sys_page' => ['id', 'is_active'],
    'pos_mobile_auth_token' => ['user_id', 'revoked_at', 'expires_at'],
    'pos_terminal' => ['device_key', 'outlet_id', 'is_active'],
    'pos_outlet' => ['id', 'is_active'],
];
$missingSchema = [];
foreach ($requiredSchema as $table => $columns) {
    $tableRows = $select(
        'SELECT COUNT(*) AS total FROM information_schema.tables '
        . 'WHERE table_schema = DATABASE() AND table_name = ' . $quote($table)
    );
    if ((int)($tableRows[0]['total'] ?? 0) !== 1) {
        $missingSchema[] = $table;
        continue;
    }
    $columnRows = $select(
        'SELECT column_name FROM information_schema.columns '
        . 'WHERE table_schema = DATABASE() AND table_name = ' . $quote($table)
    );
    $available = array_fill_keys(array_column($columnRows, 'column_name'), true);
    foreach ($columns as $column) {
        if (!isset($available[$column])) {
            $missingSchema[] = $table . '.' . $column;
        }
    }
}
if ($missingSchema !== []) {
    fwrite(STDERR, 'RBAC_SCOPE_STAGING FAIL missing_schema=' . implode(',', $missingSchema) . PHP_EOL);
    $connection->close();
    exit(1);
}

$assignmentRows = $select(
    'SELECT user.id AS user_id, role.id AS role_id, role.role_code, '
    . 'role.division_scope_id, role.is_active AS role_is_active '
    . 'FROM auth_user user '
    . 'LEFT JOIN auth_user_role assignment ON assignment.user_id = user.id '
    . 'LEFT JOIN auth_role role ON role.id = assignment.role_id '
    . 'WHERE user.is_active = 1 ORDER BY user.id, role.id'
);
$users = [];
foreach ($assignmentRows as $row) {
    $userId = (int)$row['user_id'];
    $users[$userId] ??= [];
    if ((int)($row['role_is_active'] ?? 0) === 1 && (int)($row['role_id'] ?? 0) > 0) {
        $users[$userId][] = $row;
    }
}

$states = [];
$summary = [
    'active_users' => count($users),
    'multi_role' => 0,
    'superadmin' => 0,
    'global' => 0,
    'single' => 0,
    'none' => 0,
    'ambiguous' => 0,
];
foreach ($users as $userId => $roles) {
    if (count($roles) > 1) {
        $summary['multi_role']++;
    }
    $isSuperadmin = false;
    $divisionIds = [];
    foreach ($roles as $role) {
        if ((string)$role['role_code'] === 'SUPERADMIN') {
            $isSuperadmin = true;
        }
        if ($role['division_scope_id'] !== null && (int)$role['division_scope_id'] > 0) {
            $divisionIds[(int)$role['division_scope_id']] = true;
        }
    }
    if ($isSuperadmin) {
        $state = 'SUPERADMIN';
    } elseif ($roles === []) {
        $state = 'NONE';
    } elseif (count($divisionIds) > 1) {
        $state = 'AMBIGUOUS';
    } elseif (count($divisionIds) === 1) {
        $state = 'SINGLE';
    } else {
        $state = 'GLOBAL';
    }
    $states[$userId] = $state;
    $summary[strtolower($state)]++;
}

$activeTokenRows = $select(
    'SELECT DISTINCT user_id FROM pos_mobile_auth_token '
    . 'WHERE revoked_at IS NULL AND expires_at >= NOW()'
);
$invalidActiveTokenScopes = 0;
foreach ($activeTokenRows as $row) {
    $state = $states[(int)$row['user_id']] ?? 'NONE';
    if (!in_array($state, ['SUPERADMIN', 'GLOBAL', 'SINGLE'], true)) {
        $invalidActiveTokenScopes++;
    }
}

$scalar = static function (string $sql) use ($select): int {
    $rows = $select($sql);
    return (int)($rows[0]['total'] ?? 0);
};
$orphanAssignments = $scalar(
    'SELECT COUNT(*) AS total FROM auth_user_role assignment '
    . 'LEFT JOIN auth_user user ON user.id = assignment.user_id '
    . 'LEFT JOIN auth_role role ON role.id = assignment.role_id '
    . 'WHERE user.id IS NULL OR role.id IS NULL'
);
$duplicateActiveDeviceKeys = $scalar(
    'SELECT COUNT(*) AS total FROM ('
    . 'SELECT device_key FROM pos_terminal WHERE is_active = 1 AND TRIM(device_key) <> "" '
    . 'GROUP BY device_key HAVING COUNT(*) > 1'
    . ') duplicates'
);
$invalidActiveTerminals = $scalar(
    'SELECT COUNT(*) AS total FROM pos_terminal terminal '
    . 'LEFT JOIN pos_outlet outlet ON outlet.id = terminal.outlet_id '
    . 'WHERE terminal.is_active = 1 '
    . 'AND (TRIM(COALESCE(terminal.device_key, "")) = "" OR outlet.id IS NULL OR outlet.is_active <> 1)'
);

$roleRows = $select(
    'SELECT role.role_code, role.is_active, role.division_scope_id, '
    . 'SUM(COALESCE(permission.can_view, 0)) AS views, '
    . 'SUM(COALESCE(permission.can_create, 0) + COALESCE(permission.can_edit, 0) '
    . '+ COALESCE(permission.can_delete, 0)) AS mutations, '
    . 'SUM(COALESCE(permission.can_export, 0)) AS exports '
    . 'FROM auth_role role LEFT JOIN auth_role_permission permission ON permission.role_id = role.id '
    . 'GROUP BY role.id ORDER BY role.role_code'
);
foreach ($roleRows as $role) {
    echo 'ROLE code=' . (string)$role['role_code']
        . ' active=' . (int)$role['is_active']
        . ' scope=' . ($role['division_scope_id'] === null ? 'GLOBAL' : (int)$role['division_scope_id'])
        . ' view=' . (int)$role['views']
        . ' mutation=' . (int)$role['mutations']
        . ' export=' . (int)$role['exports'] . PHP_EOL;
}

$blocking = $summary['none'] + $summary['ambiguous'] + $invalidActiveTokenScopes
    + $orphanAssignments + $duplicateActiveDeviceKeys + $invalidActiveTerminals;
$result = $blocking === 0 ? 'PASS' : 'FAIL';
echo 'RBAC_SCOPE_STAGING ' . $result
    . ' active_users=' . $summary['active_users']
    . ' multi_role=' . $summary['multi_role']
    . ' superadmin=' . $summary['superadmin']
    . ' global=' . $summary['global']
    . ' single=' . $summary['single']
    . ' none=' . $summary['none']
    . ' ambiguous=' . $summary['ambiguous']
    . ' active_mobile_token_users=' . count($activeTokenRows)
    . ' invalid_mobile_token_scopes=' . $invalidActiveTokenScopes
    . ' orphan_assignments=' . $orphanAssignments
    . ' duplicate_active_device_keys=' . $duplicateActiveDeviceKeys
    . ' invalid_active_terminals=' . $invalidActiveTerminals
    . PHP_EOL;

$connection->close();
exit($blocking === 0 ? 0 : 1);
