<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)@file_get_contents($root . '/application/controllers/Purchase.php');
$model = (string)@file_get_contents($root . '/application/models/Purchase_model.php');
$view = (string)@file_get_contents($root . '/application/views/purchase/stock_division_movement_index.php');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};

$check($controller !== '' && $model !== '' && $view !== '', 'division movement UI implementation files are readable');
$check(
    strpos($controller, '$offset = ($page - 1) * $perPage;') !== false
        && strpos($controller, '$maxPage = max(1, (int)floor(100000 / $perPage) + 1);') !== false
        && strpos($controller, '$perPage + 1') !== false
        && strpos($controller, '$hasMore = count($rows) > $perPage;') !== false
        && strpos($controller, "'has_more'     => \$hasMore") !== false,
    'controller requests one bounded extra row and exposes only a read-only next-page signal'
);
$check(
    strpos($model, 'int $limit, ?string $destinationFilter = null, int $offset = 0') !== false
        && strpos($model, '$offset = min(100000, max(0, $offset));') !== false
        && strpos($model, '->limit($limit, $offset)') !== false,
    'movement reader bounds offset and paginates in the database query'
);
$check(
    strpos($view, '$hasMore  = !empty($has_more);') !== false
        && strpos($view, 'array_slice($rows') === false
        && strpos($view, 'Halaman berikutnya') !== false
        && strpos($view, 'mvt-empty-state') !== false,
    'view renders the server page directly with accessible next/previous and empty states'
);
$check(
    strpos($view, 'id="mvt-filter-status"') !== false
        && strpos($view, "setAttribute('aria-busy', 'true')") !== false
        && strpos($view, 'mvt-row-hidden') === false,
    'filter and page navigation provide loading feedback without misleading client-only type filtering'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' division movement pagination check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'All ' . $checks . ' division movement pagination checks passed.' . PHP_EOL;
