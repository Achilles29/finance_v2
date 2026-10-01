<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sidebarPath = $root . '/application/views/layout/sidebar.php';
$sidebar = file_get_contents($sidebarPath);

if ($sidebar === false) {
    fwrite(STDERR, "FAIL: cannot read sidebar renderer source\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$requiredHelpers = [
    '_resolve_menu_icon',
    '_has_active_child_sidebar',
    '_normalize_sidebar_uri',
    '_is_sidebar_item_active',
    '_find_first_matching_menu_code',
    '_menu_has_real_link',
    'render_menu_tree',
];
foreach ($requiredHelpers as $helper) {
    $expect(
        preg_match('/function\s+' . preg_quote($helper, '/') . '\s*\(/', $sidebar) === 1,
        'generic helper is missing: ' . $helper
    );
}

$forbiddenPatterns = [
    '/function\s+_get_ri_icon\s*\(/' => 'runtime icon registry helper remains',
    '/function\s+_regroup_[a-z0-9_]*\s*\(/i' => 'runtime regroup helper remains',
    '/function\s+_append_[a-z0-9_]*\s*\(/i' => 'runtime append helper remains',
    '/function\s+_sidebar_tree_contains_menu_code\s*\(/' => 'runtime tree lookup helper remains',
    '/function\s+_sort_sidebar_children_by_order\s*\(/' => 'runtime sort helper remains',
    '/[\'\"]is_virtual[\'\"]\s*=>/' => 'virtual menu data remains',
    '/[\'\"]id[\'\"]\s*=>\s*-\d+/' => 'synthetic negative menu ID remains',
    '/\$syntheticItems\b/' => 'synthetic menu map remains',
    '/\$sidebar_(?:main|my)\s*=\s*_/' => 'runtime sidebar transform remains',
    '/\[\s*[\'\"]menu_label[\'\"]\s*\]\s*=/' => 'runtime menu relabel remains',
];
foreach ($forbiddenPatterns as $pattern => $message) {
    $expect(preg_match($pattern, $sidebar) !== 1, $message);
}

$expect(
    preg_match('/return\s+strpos\(\$db_icon,\s*[\'\"]ri-[\'\"]\)\s*===\s*0\s*\?\s*\$db_icon\s*:\s*[\'\"]ri-circle-line[\'\"]\s*;/', $sidebar) === 1,
    'icon resolver must preserve a DB RI icon and use the neutral fallback'
);
$expect(
    preg_match('/render_menu_tree\(\$sidebar_main\s*\?\?\s*\[\]/', $sidebar) === 1,
    'company renderer must consume sidebar_main directly'
);
$expect(
    preg_match('/render_menu_tree\(\$sidebar_my\s*\?\?\s*\[\]/', $sidebar) === 1,
    'employee renderer must consume sidebar_my directly'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "A3 sidebar renderer single-source smoke: PASS\n");
