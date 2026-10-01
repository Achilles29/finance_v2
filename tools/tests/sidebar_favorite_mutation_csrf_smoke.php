<?php

// Focused source + boundary simulation smoke; no CI/database bootstrap.
$root = dirname(__DIR__, 2);
$sidebar = file_get_contents($root . '/application/controllers/Sidebar.php');
$menu = file_get_contents($root . '/application/models/Menu_model.php');
$base = file_get_contents($root . '/application/core/MY_Controller.php');
$footer = file_get_contents($root . '/application/views/layout/footer.php');
$layoutSidebar = file_get_contents($root . '/application/views/layout/sidebar.php');
$app = file_get_contents($root . '/assets/js/app.js');
$checks = 0;
$failures = [];

function sf_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function sf_method(string $source, string $method): string
{
    $start = strpos($source, 'function ' . $method . '(');
    if ($start === false) return '';
    preg_match('/\n    (?:public|private|protected) function /', $source, $next, PREG_OFFSET_CAPTURE, $start + 1);
    $end = isset($next[0][1]) ? (int)$next[0][1] : strlen($source);
    return substr($source, $start, $end - $start);
}

function sf_ordered(string $source, array $needles): bool
{
    $position = -1;
    foreach ($needles as $needle) {
        $next = strpos($source, $needle);
        if ($next === false || $next <= $position) return false;
        $position = $next;
    }
    return true;
}

function sf_guard(string $method, string $provided, string $expected): array
{
    $trace = ['status' => 200, 'payload' => 0, 'model' => 0, 'cache' => 0];
    if (strtoupper($method) !== 'POST') {
        $trace['status'] = 405;
        return $trace;
    }
    if (
        preg_match('/\A[0-9a-f]{64}\z/D', $provided) !== 1
        || preg_match('/\A[0-9a-f]{64}\z/D', $expected) !== 1
        || !hash_equals($expected, $provided)
    ) {
        $trace['status'] = 403;
        return $trace;
    }
    $trace['payload']++;
    $trace['model']++;
    $trace['cache']++;
    return $trace;
}

function sf_reorder(array $ids, array $owned): array
{
    $seen = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0 || isset($seen[$id])) return ['status' => 400, 'writes' => 0];
        $seen[$id] = true;
    }
    if ($seen === []) return ['status' => 400, 'writes' => 0];
    $ownedMap = array_fill_keys($owned, true);
    if (count($seen) !== count($ownedMap)) return ['status' => 400, 'writes' => 0];
    foreach (array_keys($seen) as $id) {
        if (empty($ownedMap[$id])) return ['status' => 400, 'writes' => 0];
    }
    foreach (array_keys($ownedMap) as $id) {
        if (empty($seen[$id])) return ['status' => 400, 'writes' => 0];
    }
    return ['status' => 200, 'writes' => count($seen)];
}

$guard = sf_method($sidebar, 'require_favorite_mutation_request');
sf_check(
    strpos($base, "SIDEBAR_FAVORITE_CSRF_SESSION_KEY = 'sidebar_favorite_csrf'") !== false
        && strpos($base, "SIDEBAR_FAVORITE_CSRF_HEADER = 'X-Sidebar-Favorite-CSRF'") !== false
        && strpos($base, "SIDEBAR_FAVORITE_CSRF_CI_HEADER = 'X-Sidebar-Favorite-Csrf'") !== false
        && strpos(sf_method($base, 'sidebar_favorite_csrf'), 'bin2hex(random_bytes(32))') !== false,
    'layout foundation generates dedicated random 64-hex sidebar token'
);
sf_check(
    sf_ordered(sf_method($base, 'render'), ['sidebar_favorite_csrf()', "load->view('layout/main'"])
        && strpos($footer, 'window.FINANCE_SIDEBAR_FAVORITE_CSRF') !== false
        && strpos($footer, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false
        && strpos($footer, 'assets/js/app.js?v=20260903a') !== false,
    'render exports token safely before cache-busted app.js'
);
sf_check(
    strpos($guard, "method(true) !== 'POST'") !== false
        && strpos($guard, "set_header('Allow: POST')") !== false
        && strpos($guard, 'FAVORITE_CSRF_CI_HEADER') !== false
        && strpos($guard, 'hash_equals($expected, $provided)') !== false
        && strpos($guard, 'favorite_json_error(405') !== false
        && strpos($guard, 'favorite_json_error(403') !== false,
    'controller guard returns JSON 405/403 and validates dedicated header with hash_equals'
);

foreach (['pin', 'unpin', 'reorder'] as $action) {
    $block = sf_method($sidebar, $action);
    sf_check(
        sf_ordered($block, ['require_favorite_mutation_request()', 'input->post(', 'Menu_model->', 'clear_sidebar_favorite_cache_files(', 'favorite_json_ok()'])
            && substr_count($block, 'clear_sidebar_favorite_cache_files(') === 1,
        $action . ' guards before payload/model and invalidates cache once after success'
    );
}

$pin = sf_method($sidebar, 'pin');
sf_check(
    strpos($pin, 'find_favoritable_menu_for_user(') !== false
        && strpos($pin, "favorite_json_error(404, 'Menu favorit tidak ditemukan.')") !== false,
    'pin maps missing, inactive, or unauthorized menu to one generic 404'
);
$unpinModel = sf_method($menu, 'unpin_favorite');
sf_check(
    sf_ordered($unpinModel, ["where('user_id', \$user_id)", "where('menu_id', \$menu_id)", "delete('sys_sidebar_favorite')"]),
    'unpin is idempotent and scoped to user plus menu'
);

$reorder = sf_method($sidebar, 'reorder');
$reorderModel = sf_method($menu, 'reorder_favorites');
sf_check(
    sf_ordered($reorder, ['input->post(', 'isset($normalized[$menuId])', 'get_favorite_menu_ids_for_user(', 'empty($owned[$menuId])', 'reorder_favorites('])
        && strpos($reorder, 'count($normalizedSet) !== count($owned)') !== false
        && strpos($reorder, 'foreach ($owned as $menuId => $_owned)') !== false
        && strpos($reorderModel, "where('user_id', \$user_id)->where('menu_id', (int)\$menu_id)") !== false,
    'reorder requires the exact owned set before user-scoped transaction writes'
);

$access = sf_method($menu, 'is_menu_accessible_for_user');
$predicate = sf_method($menu, 'is_favoritable_menu_for_user');
sf_check(
    strpos($access, "empty(\$row['is_active'])") !== false
        && sf_ordered($access, ["empty(\$row['page_id'])", 'if ($is_superadmin)', "['can_view']"])
        && strpos($predicate, 'is_menu_accessible_for_user(') !== false
        && strpos($predicate, "\$url !== '' && \$url !== '#'") !== false
        && strpos($predicate, "stripos(\$url, 'javascript:') !== 0") !== false,
    'canonical access helper feeds real-link favorite predicate without permission drift'
);
sf_check(
    strpos(sf_method($menu, 'get_sidebar_tree'), 'is_menu_accessible_for_user(') !== false
        && strpos(sf_method($menu, 'find_favoritable_menu_for_user'), 'is_favoritable_menu_for_user(') !== false
        && strpos(sf_method($menu, 'get_favorites'), 'is_favoritable_menu_for_user(') !== false
        && strpos(sf_method($menu, 'get_favorites'), "join('sys_page p'") !== false,
    'tree, pin lookup, and favorites list reuse one row-access predicate with page join'
);
sf_check(
    strpos(sf_method($base, 'load_sidebar_cached'), 'get_favorites($userId, $this->user_perms, $isSuperadmin)') !== false
        && strpos(sf_method($base, 'get_sidebar_cache_signature'), 'order_fingerprint') !== false,
    'favorites receive permissions and cache fingerprint changes on reorder'
);
sf_check(
    strpos($app, "'X-Sidebar-Favorite-CSRF': String(window.FINANCE_SIDEBAR_FAVORITE_CSRF || '')") !== false
        && strpos($app, "sidebarFavoriteRequest('sidebar/reorder'") !== false
        && strpos($app, "'sidebar/unpin' : 'sidebar/pin'") !== false
        && strpos($app, 'location.reload') === false
        && strpos($app, "querySelectorAll('.sidebar-pin-toggle[data-menu-id=\"' + menuId + '\"]')") !== false
        && strpos($app, "document.createElement('li')") !== false
        && strpos($app, 'label.textContent = sourceLabel ? sourceLabel.textContent.trim()') !== false
        && strpos($app, "querySelectorAll('li[data-fav-id=\"' + menuId + '\"]')") !== false,
    'pin/unpin/reorder send the header and update safe local DOM without reload'
);
sf_check(
    substr_count($layoutSidebar, "!empty(\$item['is_favoritable']) && \$menu_id > 0") === 2
        && strpos($layoutSidebar, 'data-sidebar-favorites-header') !== false
        && strpos($layoutSidebar, 'data-sidebar-favorites-divider') !== false,
    'tree pin buttons require model favoritable predicate and favorite anchors always render'
);

$token = str_repeat('a', 64);
foreach (['GET' => $token, 'PUT' => $token, 'bad token' => str_repeat('b', 64)] as $case => $provided) {
    $trace = sf_guard($case === 'bad token' ? 'POST' : $case, $provided, $token);
    sf_check(
        in_array($trace['status'], [403, 405], true)
            && $trace['payload'] === 0 && $trace['model'] === 0 && $trace['cache'] === 0,
        $case . ' stops before payload/model/cache'
    );
}
$valid = sf_guard('POST', $token, $token);
sf_check($valid === ['status' => 200, 'payload' => 1, 'model' => 1, 'cache' => 1], 'valid token reaches each downstream stage once');
foreach ([[-1, 2], [1, 1], [1, 9], [1]] as $ids) {
    $result = sf_reorder($ids, [1, 2]);
    sf_check($result['status'] === 400 && $result['writes'] === 0, 'invalid reorder set is rejected before writes');
}
sf_check(sf_reorder([2, 1], [1, 2]) === ['status' => 200, 'writes' => 2], 'valid owned reorder writes each favorite once');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' sidebar favorite smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' sidebar favorite mutation CSRF smoke checks passed.' . PHP_EOL;
