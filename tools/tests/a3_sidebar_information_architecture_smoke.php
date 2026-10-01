<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migrationPath = $root . '/sql/2026-09-06i_a3_sidebar_task_oriented_layout.sql';
$modelPath = $root . '/application/models/Menu_model.php';
$migration = file_get_contents($migrationPath);
$model = file_get_contents($modelPath);

if ($migration === false || $model === false) {
    fwrite(STDERR, "FAIL: A3 sidebar information architecture source is unreadable\n");
    exit(1);
}

$checks = 0;
$failures = [];
$expect = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$expect(strpos($migration, "CALL sp_a3_sidebar_task_layout_assert_20260906i('PRE');") !== false, 'migration has a fail-closed preflight');
$expect(strpos($migration, "CALL sp_a3_sidebar_task_layout_assert_20260906i('POST');") !== false, 'migration has a fail-closed postflight');
$expect(strpos($migration, 'START TRANSACTION;') !== false && strpos($migration, 'COMMIT;') !== false, 'migration has a transaction boundary');
$expect(strpos($migration, 'sort_collision_count') === false, 'migration does not emit unframed client output after commit');
$expect(preg_match('/\bDELETE\s+(?:FROM\s+)?sys_menu\b/i', $migration) !== 1, 'migration never deletes sidebar rows');
$expect(preg_match('/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+)?auth_role_permission\b/i', $migration) !== 1, 'migration never changes role permissions');
$expect(strpos($migration, "'grp.people', 'SDM & Payroll'") !== false, 'people workspace group is seeded');
$expect(strpos($migration, "'grp.integration', 'Integrasi & Notifikasi'") !== false, 'integration workspace group is seeded');

foreach ([
    ['pos.cashier', 'pos.group.operation'],
    ['pos.self_order', 'pos.group.channel'],
    ['pos.reservation', 'pos.group.operation'],
    ['pos.report.group', 'grp.pos'],
    ['grp.hr', 'grp.people'],
    ['grp.payroll', 'grp.people'],
    ['grp.menu_book', 'produk'],
    ['grp.wa', 'grp.integration'],
    ['grp.telegram', 'grp.integration'],
] as [$child, $parent]) {
    $expect(
        strpos($migration, "'{$child}', '{$parent}'") !== false
            || strpos($migration, "'{$child}' THEN") !== false,
        "task layout maps {$child} into {$parent}"
    );
}

$expect(
    preg_match('/if\s*\(!\$hasRealUrl\s*&&\s*\$item\[\'children\'\]\s*===\s*\[\]\)\s*\{\s*continue;/s', $model) === 1,
    'sidebar tree prunes empty authorized group shells'
);

if ($failures !== []) {
    exit(1);
}

echo "A3 sidebar information architecture: {$checks} check(s) passed.\n";
