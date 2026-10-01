<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = realpath(dirname(__DIR__, 2));
if ($root === false) {
    fwrite(STDERR, "Repository root unavailable.\n");
    exit(1);
}
$output = $root . '/sql/2026-09-05d_a5_clean_install_reference_seed.sql';

defined('BASEPATH') || define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') || define('ENVIRONMENT', 'development');
require $root . '/application/config/database.php';
$connection = $db['default'] ?? null;
if (!is_array($connection)) {
    fwrite(STDERR, "Database configuration unavailable.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli(
    (string)($connection['hostname'] ?? ''),
    (string)($connection['username'] ?? ''),
    (string)($connection['password'] ?? ''),
    (string)($connection['database'] ?? '')
);
if ($mysqli->connect_errno !== 0 || !$mysqli->set_charset('utf8mb4')) {
    fwrite(STDERR, "Reference database unavailable.\n");
    exit(1);
}

$fetch = static function (string $query) use ($mysqli): array {
    $result = $mysqli->query($query);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Reference query failed.');
    }
    return $result->fetch_all(MYSQLI_ASSOC);
};
$sqlValue = static function ($value) use ($mysqli): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || (is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1)) {
        return (string)(int)$value;
    }
    return "'" . $mysqli->real_escape_string((string)$value) . "'";
};
$insert = static function (string $table, array $columns, array $rows) use ($sqlValue): string {
    $blocks = [];
    foreach (array_chunk($rows, 100) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $items = [];
            foreach ($columns as $column) {
                $items[] = $sqlValue($row[$column] ?? null);
            }
            $values[] = '  (' . implode(', ', $items) . ')';
        }
        $blocks[] = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ") VALUES\n"
            . implode(",\n", $values) . ";\n";
    }
    return implode("\n", $blocks);
};

try {
    $groups = $fetch('SELECT id, group_code, group_label, icon, color, bg_color, sort_order FROM sys_matrix_group ORDER BY id');
    $pages = $fetch("SELECT id, page_code, page_name, module, matrix_group, description, is_active FROM sys_page WHERE module <> 'TELEGRAM' ORDER BY id");
    $menus = $fetch("SELECT id, parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type FROM sys_menu WHERE menu_code <> 'grp.telegram' AND menu_code NOT LIKE 'tg.%' ORDER BY id");
    $aliases = $fetch('SELECT id, alias_code, page_id, description, is_active FROM sys_page_alias ORDER BY id');
    $roles = $fetch("SELECT role_code, role_name, description, division_scope_id, is_active FROM auth_role WHERE role_code = 'SUPERADMIN'");

    $expectedCounts = [20, 201, 235, 10, 1];
    $actualCounts = [count($groups), count($pages), count($menus), count($aliases), count($roles)];
    if ($actualCounts !== $expectedCounts) {
        throw new RuntimeException('Reference inventory changed; classification must be reviewed again.');
    }
    if (($roles[0]['division_scope_id'] ?? null) !== null || (int)($roles[0]['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('SUPERADMIN reference role is not global and active.');
    }

    foreach ($pages as &$page) {
        if (($page['page_code'] ?? '') === 'menu_book.index') {
            $page['page_name'] = 'Menu Book';
            $page['description'] = 'Halaman indeks buku menu digital untuk usaha food and beverage.';
        }
    }
    unset($page);

    $pageIds = array_fill_keys(array_map('intval', array_column($pages, 'id')), true);
    $menuIds = array_fill_keys(array_map('intval', array_column($menus, 'id')), true);
    foreach ($menus as $menu) {
        if ($menu['parent_id'] !== null && !isset($menuIds[(int)$menu['parent_id']])) {
            throw new RuntimeException('Non-Telegram menu references an excluded parent.');
        }
        if ($menu['page_id'] !== null && !isset($pageIds[(int)$menu['page_id']])) {
            throw new RuntimeException('Non-Telegram menu references an excluded page.');
        }
        if (is_string($menu['url']) && preg_match('#^(?:https?:)?//#i', $menu['url']) === 1) {
            throw new RuntimeException('Absolute menu URL is forbidden.');
        }
    }
    foreach ($aliases as $alias) {
        if (!isset($pageIds[(int)$alias['page_id']])) {
            throw new RuntimeException('Page alias references an excluded page.');
        }
    }

    $payload = json_encode([$groups, $pages, $menus, $aliases, $roles], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || preg_match('/(?:NAMUA|CACACIA|@[A-Za-z]|https?:\/\/|\/www\/)/i', $payload) === 1) {
        throw new RuntimeException('Customer identity, address, or absolute URL found in reference rows.');
    }

    $sql = "-- A5.12 customer-neutral clean-install reference seed.\n"
        . "-- CLEAN INSTALL ONLY: never apply to an existing customer database.\n"
        . "-- Source allowlist: product navigation metadata and one global SUPERADMIN role.\n"
        . "-- Excluded: users, employees, other roles, customer permission choices, secrets,\n"
        . "-- transactions, balances, inventory, targets, queues, logs, and customer identity.\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n\n"
        . $insert('sys_matrix_group', ['id','group_code','group_label','icon','color','bg_color','sort_order'], $groups) . "\n"
        . $insert('sys_page', ['id','page_code','page_name','module','matrix_group','description','is_active'], $pages) . "\n"
        . $insert('sys_menu', ['id','parent_id','menu_code','menu_label','icon','url','page_id','sort_order','is_active','sidebar_type'], $menus) . "\n"
        . $insert('sys_page_alias', ['id','alias_code','page_id','description','is_active'], $aliases) . "\n"
        . $insert('auth_role', ['role_code','role_name','description','division_scope_id','is_active'], $roles) . "\n"
        . "INSERT INTO auth_role_permission\n"
        . "  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)\n"
        . "SELECT role.id, page.id, 1, 1, 1, 1, 1, CURRENT_TIMESTAMP\n"
        . "FROM auth_role role CROSS JOIN sys_page page\n"
        . "WHERE role.role_code = 'SUPERADMIN' AND role.is_active = 1;\n\n"
        . "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";

    $temporary = tempnam(dirname($output), '.a512-seed-');
    if ($temporary === false || file_put_contents($temporary, $sql, LOCK_EX) !== strlen($sql)
        || !chmod($temporary, 0644) || !rename($temporary, $output)) {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
        throw new RuntimeException('Reference seed could not be written atomically.');
    }
    echo json_encode([
        'status' => 'ok',
        'path' => 'sql/' . basename($output),
        'sha256' => hash('sha256', $sql),
        'rows' => [
            'sys_matrix_group' => count($groups),
            'sys_page' => count($pages),
            'sys_menu' => count($menus),
            'sys_page_alias' => count($aliases),
            'auth_role' => count($roles),
        ],
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['status'=>'error','message'=>$error->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    $mysqli->close();
}
