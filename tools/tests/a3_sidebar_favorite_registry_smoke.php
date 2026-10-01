<?php

// A3-3 focused behavioral + source smoke; no CodeIgniter/database bootstrap.
$root = dirname(__DIR__, 2);
$modelSource = file_get_contents($root . '/application/models/Menu_model.php');
$controllerSource = file_get_contents($root . '/application/controllers/Sidebar.php');
$checks = 0;
$failures = [];

function a3_check(bool $condition, string $message): void
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

function a3_method(string $source, string $name): string
{
    $start = strpos($source, 'function ' . $name . '(');
    if ($start === false) {
        return '';
    }

    $brace = strpos($source, '{', $start);
    if ($brace === false) {
        return '';
    }

    $depth = 0;
    $length = strlen($source);
    for ($position = $brace; $position < $length; $position++) {
        if ($source[$position] === '{') {
            $depth++;
        } elseif ($source[$position] === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $position - $start + 1);
            }
        }
    }
    return '';
}

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!class_exists('CI_Model')) {
    class CI_Model
    {
        public $db;
    }
}
require_once $root . '/application/models/Menu_model.php';

final class A3FavoriteFakeDb
{
    public array $rows;
    public array $selects = [];

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function select(string $fields): self
    {
        $this->selects[] = $fields;
        return $this;
    }

    public function from(string $table): self
    {
        return $this;
    }

    public function join(string $table, string $condition, string $type = ''): self
    {
        return $this;
    }

    public function where(string $field, $value): self
    {
        return $this;
    }

    public function order_by(string $field, string $direction): self
    {
        return $this;
    }

    public function get(): self
    {
        return $this;
    }

    public function result_array(): array
    {
        return $this->rows;
    }
}

$model = new Menu_model();
$activeAction = [
    'is_active' => 1,
    'url' => 'reports/sales',
    'page_id' => 10,
    'page_code' => 'reports.sales',
    'page_is_active' => 1,
];

a3_check(
    !$model->is_menu_accessible_for_user(array_merge($activeAction, ['is_active' => 0]), [], true)
        && !$model->is_menu_accessible_for_user(array_merge($activeAction, ['is_active' => 2]), [], true),
    'only explicitly active menu is accepted, including for superadmin'
);
a3_check(
    !$model->is_menu_accessible_for_user([
        'is_active' => 1,
        'url' => 'reports/orphan',
        'page_id' => null,
        'page_code' => null,
        'page_is_active' => null,
    ], [], true),
    'real action without page fails closed even for superadmin'
);
a3_check(
    !$model->is_menu_accessible_for_user(array_merge($activeAction, ['page_is_active' => 0]), [], true)
        && !$model->is_menu_accessible_for_user(array_merge($activeAction, ['page_is_active' => 2]), [], true)
        && !$model->is_menu_accessible_for_user(array_merge($activeAction, ['page_code' => null]), [], true),
    'inactive or missing joined page fails closed before superadmin bypass'
);
a3_check(
    !$model->is_menu_accessible_for_user($activeAction, [], false)
        && $model->is_menu_accessible_for_user($activeAction, ['reports.sales' => ['can_view' => 1]], false)
        && $model->is_menu_accessible_for_user($activeAction, [], true),
    'active registered action uses can_view with superadmin bypass only after registry validation'
);

$group = [
    'is_active' => 1,
    'url' => '#',
    'page_id' => null,
    'page_code' => null,
    'page_is_active' => null,
];
a3_check(
    $model->is_menu_accessible_for_user($group, [], false)
        && !$model->is_favoritable_menu_for_user($group, [], true),
    'page-less non-action group stays accessible and is never favoritable'
);
a3_check(
    $model->is_favoritable_menu_for_user($activeAction, ['reports.sales' => ['can_view' => 1]], false),
    'accessible active registered action remains favoritable'
);

$model->db = new A3FavoriteFakeDb([
    array_merge($activeAction, ['id' => 1, 'menu_id' => 101]),
    array_merge($activeAction, ['id' => 2, 'menu_id' => 102, 'page_code' => 'reports.secret']),
    array_merge($activeAction, ['id' => 3, 'menu_id' => 103, 'page_is_active' => 0]),
    ['id' => 4, 'menu_id' => 104, 'is_active' => 1, 'url' => '', 'page_id' => null, 'page_code' => null, 'page_is_active' => null],
    ['id' => 5, 'menu_id' => 105, 'is_active' => 1, 'url' => 'reports/orphan', 'page_id' => null, 'page_code' => null, 'page_is_active' => null],
]);
$visibleIds = $model->get_favorite_menu_ids_for_user(
    77,
    ['reports.sales' => ['can_view' => 1]],
    false
);
a3_check(
    $visibleIds === [101],
    'reorder ownership set excludes stale, group, inactive-page, orphan, and permission-revoked favorites'
);

$tree = a3_method($modelSource, 'get_sidebar_tree');
$lookup = a3_method($modelSource, 'find_favoritable_menu_for_user');
$favorites = a3_method($modelSource, 'get_favorites');
$favoriteIds = a3_method($modelSource, 'get_favorite_menu_ids_for_user');
$resolver = a3_method($modelSource, 'is_menu_accessible_for_user');
$pin = a3_method($modelSource, 'pin_favorite');
$normalize = a3_method($modelSource, 'normalize_sidebar_menu_row');
$reorder = a3_method($controllerSource, 'reorder');

a3_check(
    strpos($tree, 'p.is_active AS page_is_active') !== false
        && strpos($lookup, 'p.is_active AS page_is_active') !== false
        && strpos($favorites, 'p.is_active AS page_is_active') !== false,
    'tree, pin lookup, and favorites select page active state'
);
a3_check(
    strpos($tree, 'is_menu_accessible_for_user(') !== false
        && strpos($lookup, 'is_favoritable_menu_for_user(') !== false
        && strpos($favorites, 'is_favoritable_menu_for_user(') !== false
        && strpos($favoriteIds, 'get_favorites($user_id, $perms, $is_superadmin)') !== false
        && strpos($resolver, "empty(\$row['page_is_active'])") !== false,
    'tree, pin, favorites, and reorder ID lookup converge on the fail-closed resolver'
);
a3_check(
    strpos($reorder, 'get_favorite_menu_ids_for_user(') !== false
        && strpos($reorder, '$this->user_perms') !== false
        && strpos($reorder, '$this->is_superadmin()') !== false,
    'controller resolves reorder IDs with the current permission context'
);
a3_check(
    strpos($pin, 'ON DUPLICATE KEY UPDATE') !== false
        && strpos($pin, 'WHERE user_id = ?') !== false
        && strpos($pin, 'get_where(') === false,
    'pin uses one user-scoped idempotent upsert without check-then-insert race'
);
a3_check(
    strpos($normalize, 'company-account') === false
        && strpos($normalize, "trim((string)\$row['url'])") !== false,
    'menu normalization only trims URL and leaves route aliases to the database'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' A3 sidebar favorite registry smoke check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' A3 sidebar favorite registry smoke checks passed.' . PHP_EOL;
