<?php

declare(strict_types=1);

// DB-free source smoke for the A3-5 shared Finance UI shell contract.
$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/application/views/layout/main.php');
$manage = file_get_contents($root . '/application/views/sidebar/manage.php');
$css = file_get_contents($root . '/assets/css/theme-custom.css');
$core = file_get_contents($root . '/application/core/MY_Controller.php');
$footer = file_get_contents($root . '/application/views/layout/footer.php');
$app = file_get_contents($root . '/assets/js/app.js');
$componentMaster = file_get_contents($root . '/application/views/production/component_master_index.php');
$checks = 0;
$failures = [];

function a3_ui_check(bool $condition, string $message): void
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

function a3_ui_module(string $activeMenu): string
{
    $segments = explode('.', strtolower($activeMenu));
    $segment = (string) ($segments[0] ?? '');
    if ($segment === 'grp' && !empty($segments[1])) {
        $segment = (string) $segments[1];
    }
    $segment = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $segment), '-');
    return $segment !== '' ? $segment : 'general';
}

a3_ui_check(
    is_string($layout) && is_string($manage) && is_string($css) && is_string($core)
        && is_string($footer) && is_string($app) && is_string($componentMaster),
    'shell sources are readable'
);
a3_ui_check(
    strpos($core, "return \$this->load->view('layout/main', \$data, \$return);") !== false,
    'standard CodeIgniter render path uses the shared main layout'
);
a3_ui_check(
    strpos($core, "\$data['sidebar_favorite_csrf_token'] = \$this->sidebar_favorite_csrf();") !== false
        && strpos($layout, "'sidebar_favorite_csrf_token' => (string)(\$sidebar_favorite_csrf_token ?? '')") !== false
        && strpos($footer, 'window.FINANCE_SIDEBAR_FAVORITE_CSRF =') !== false
        && strpos($footer, "(string)(\$sidebar_favorite_csrf_token ?? '')") !== false
        && strpos($footer, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false
        && strpos($app, "'X-Sidebar-Favorite-CSRF': String(window.FINANCE_SIDEBAR_FAVORITE_CSRF || '')") !== false,
    'render hands the sidebar favorite CSRF token through main and safely into the app.js request header'
);
a3_ui_check(
    strpos($layout, 'finance-page-shell') !== false
        && strpos($layout, 'data-module="<?= htmlspecialchars($moduleSegment, ENT_QUOTES, \'UTF-8\') ?>"') !== false
        && strpos($layout, 'role="main"') !== false,
    'main content exposes the canonical shell, escaped module metadata, and main landmark'
);
a3_ui_check(
    strpos($layout, "explode('.', strtolower(\$activeMenuCode))") !== false
        && strpos($layout, "\$moduleSegment === 'grp'") !== false
        && strpos($layout, "preg_replace('/[^a-z0-9_-]+/'") !== false
        && strpos($layout, "'general'") !== false,
    'module metadata derives from the active-menu module, unwraps grp.*, and is safely normalized'
);

$moduleExamples = [
    'POS' => ['pos.cashier.index', 'pos'],
    'inventory' => ['grp.inventory', 'inventory'],
    'production' => ['production.component.formula', 'production'],
    'purchase' => ['purchase.order.index', 'purchase'],
    'finance' => ['finance.cash.position', 'finance'],
    'people' => ['people.attendance.index', 'people'],
    'asset' => ['asset.item.index', 'asset'],
    'master' => ['master.product.index', 'master'],
    'reports' => ['reports.sales.index', 'reports'],
];
foreach ($moduleExamples as $surface => $example) {
    a3_ui_check(a3_ui_module($example[0]) === $example[1], $surface . ' resolves through the global shell module contract');
}
a3_ui_check(a3_ui_module('<img src=x onerror=alert(1)>.index') === 'img-src-x-onerror-alert-1', 'unsafe active-menu characters cannot escape module metadata');
a3_ui_check(a3_ui_module('grp.inventory') === 'inventory', 'grp.inventory exposes inventory as the shell module');

a3_ui_check(
    substr_count($layout, "flashdata('success')") === 1
        && substr_count($layout, "flashdata('error')") === 1
        && substr_count($layout, "flashdata('warning')") === 1,
    'flash values are read once before rendering'
);
foreach (['success', 'error', 'warning'] as $flashType) {
    a3_ui_check(
        strpos($layout, "htmlspecialchars((string)\$flashMessages['{$flashType}'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')") !== false,
        $flashType . ' flash output is escaped with invalid-byte substitution'
    );
}
a3_ui_check(
    strpos($layout, 'class="finance-feedback-region" role="status" aria-live="polite" aria-atomic="false"') !== false
        && substr_count($layout, 'class="btn-close" data-bs-dismiss="alert" aria-label=') === 3,
    'flash feedback is grouped in a labelled aria-live status region'
);
a3_ui_check(
    stripos($layout, '<style') === false
        && strpos($layout, 'purchase-soft-ui') === false
        && strpos($layout, 'isPurchaseScope') === false,
    'layout contains no purchase-only inline typography or style divergence'
);

$primitives = [
    '.finance-page-shell' => 'canonical shell tokens',
    '.finance-page-header' => 'page header',
    '.finance-action-bar' => 'action bar',
    '.finance-card' => 'card',
    '.finance-table-region' => 'responsive table region',
    '.finance-filter-control' => 'filter control',
    '.finance-empty-state' => 'empty state',
    '.finance-error-state' => 'error state',
    '.finance-loading-state' => 'loading state',
];
foreach ($primitives as $selector => $label) {
    a3_ui_check(strpos($css, $selector) !== false, 'shared CSS provides ' . $label . ' primitive');
}
a3_ui_check(
    strpos($css, ':focus-visible') !== false && strpos($css, '--finance-ui-focus-ring') !== false,
    'shell provides visible keyboard focus using a shared token'
);
a3_ui_check(
    strpos($css, '.finance-icon-action') !== false
        && strpos($css, 'min-width: 40px !important;') !== false
        && strpos($css, 'min-height: 40px !important;') !== false,
    'recognized icon actions have a minimum 40px hit target inside the shell'
);
a3_ui_check(
    substr_count($css, '@media (max-width: 767.98px)') >= 2
        && strpos($css, '.finance-action-bar > .btn') !== false
        && strpos($css, 'overscroll-behavior-inline: contain') !== false,
    'shell action and table primitives include responsive behavior'
);
a3_ui_check(
    strpos($css, '@media (prefers-reduced-motion: reduce)') !== false
        && strpos($css, '.finance-page-shell *::after') !== false
        && strpos($css, 'animation-duration: 0.01ms !important') !== false,
    'shell honors reduced-motion preferences for content and pseudo-elements'
);
a3_ui_check(substr_count($css, '{') === substr_count($css, '}'), 'theme CSS braces remain balanced');

a3_ui_check(
    strpos($manage, '$registry_validation') !== false
        && strpos($manage, '<?php if ($registryValidation !== []): ?>') !== false
        && strpos($manage, 'finance-registry-health') !== false,
    'sidebar manage exposes registry health only when optional validation data exists'
);
a3_ui_check(
    strpos($manage, "\$registryValidation['total_issues']") !== false
        && strpos($manage, "\$registryValidation['issue_counts']") !== false
        && strpos($manage, "\$registryValidation['details']") !== false
        && strpos($manage, "'active_menu_count'") === false,
    'registry health consumes the real navigation validation shape without legacy count assumptions'
);
foreach (['missing_page', 'missing_icon', 'duplicate_url', 'duplicate_code', 'sort_collision', 'invalid_favorite', 'invalid_group_url'] as $issueCode) {
    a3_ui_check(strpos($manage, "'{$issueCode}'") !== false, 'registry health covers ' . $issueCode);
}
a3_ui_check(
    strpos($manage, 'html_escape($issueLabel)') !== false
        && strpos($manage, 'html_escape($detailLabel)') !== false
        && strpos($manage, 'html_escape($detailText)') !== false,
    'registry labels and details are safely escaped'
);
a3_ui_check(
    strpos($manage, 'finance-page-header') !== false
        && strpos($manage, 'finance-action-bar') !== false
        && strpos($manage, 'finance-table-region') !== false
        && strpos($manage, 'finance-empty-state') !== false,
    'sidebar manage adopts shared responsive header, actions, table, and empty state'
);
a3_ui_check(
    stripos($manage, '<style') === false
        && preg_match('/style="[^"]*(?:color\s*:|background(?:-color)?\s*:)/i', $manage) !== 1,
    'sidebar manage introduces no inline style or inline token colors'
);
a3_ui_check(
    strpos($manage, "alert.textContent = String(text || '');") !== false
        && strpos($manage, 'alertBox.replaceChildren(alert);') !== false,
    'sidebar AJAX feedback renders server text without HTML injection'
);
a3_ui_check(
    strpos($manage, 'id="btn_save_sidebar_structure"') !== false
        && strpos($manage, 'id="sidebar-tree-root"') !== false
        && strpos($manage, 'id="menu-list-table"') !== false
        && strpos($manage, 'id="menu-list-search"') !== false,
    'existing sidebar IDs and JavaScript hooks are preserved'
);
a3_ui_check(
    strpos($componentMaster, 'finance-page-header fin-page-header') !== false
        && strpos($componentMaster, 'finance-card') !== false
        && strpos($componentMaster, 'finance-action-bar') !== false
        && strpos($componentMaster, 'finance-filter-control') !== false,
    'component master adopts the shared page, action, card, and filter primitives'
);
a3_ui_check(
    strpos($componentMaster, 'finance-table-region') !== false
        && strpos($componentMaster, 'finance-table') !== false
        && strpos($componentMaster, 'finance-empty-state') !== false
        && strpos($componentMaster, 'id="component-list-feedback"') !== false,
    'component master supplies responsive table, empty, and live feedback regions'
);
a3_ui_check(
    strpos($componentMaster, "showListFeedback('loading', 'Memuat data component...')") !== false
        && strpos($componentMaster, "showListFeedback('error', `Data component belum dapat dimuat.") !== false
        && strpos($componentMaster, "retry.id = 'btn-retry-load';") !== false
        && strpos($componentMaster, "filterForm.setAttribute('aria-busy'") !== false,
    'component master exposes loading, recoverable error, and busy state for AJAX filtering'
);
a3_ui_check(
    strpos($componentMaster, "text.textContent = String(message || '');") !== false
        && strpos($componentMaster, 'listFeedback.replaceChildren();') !== false,
    'component master feedback inserts server error text without HTML injection'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A3 Finance UI shell smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' A3 Finance UI shell smoke checks passed.' . PHP_EOL;
