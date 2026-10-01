<?php

declare(strict_types=1);

/**
 * Source-only regression contract for Component Stock pagination.
 * It intentionally performs no request, mutation, or database query.
 */

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Production.php');
$model = (string)file_get_contents($root . '/application/models/Production_model.php');
$view = (string)file_get_contents($root . '/application/views/production/component_stock_index.php');
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

$check($controller !== '' && $model !== '' && $view !== '', 'Component Stock implementation files are readable');
$check(
    strpos($controller, "(int)\$filters['per_page']") !== false
        && strpos($controller, "(int)\$filters['page']") !== false
        && strpos($controller, "'stock_meta'       => \$stockMeta") !== false,
    'controller passes the requested page and metadata to the read-only stock view'
);
$check(
    strpos($controller, "\$page = max(1, (int)\$this->input->get('page', true));") !== false
        && strpos($controller, "'page'          => \$page") !== false,
    'stock filter normalizes page input to a safe positive value'
);
$check(
    strpos($model, 'function component_stock_rows(array $filters, $limit = 200, int $page = 1, ?array &$meta = null)') !== false
        && strpos($model, '$page = min($page, $maxPage);') !== false
        && strpos($model, 'array_slice($rows, ($page - 1) * $limit, $limit)') !== false,
    'model slices the server result only after a bounded page is calculated'
);
$check(
    strpos($model, "'total_rows'     => \$totalRows") !== false
        && strpos($model, "'total_value'    => round(\$totalValue, 2)") !== false
        && strpos($model, "'negative_count' => \$negativeCount") !== false
        && strpos($model, "'max_page'       => \$maxPage") !== false,
    'model keeps full filtered totals separate from the visible page'
);
$check(
    strpos($model, 'return $this->attach_component_lot_summaries($rows);') !== false
        && strpos($model, 'array_slice($rows, ($page - 1) * $limit, $limit)') < strpos($model, 'return $this->attach_component_lot_summaries($rows);'),
    'lot details are attached only after the visible stock page is selected'
);
$check(
    strpos($view, 'Summary stats are calculated before server-side pagination.') !== false
        && strpos($view, "\$stockMeta['total_rows']") !== false
        && strpos($view, "\$stockMeta['total_value']") !== false,
    'view renders KPI totals from the full filtered result rather than the current page'
);
$check(
    strpos($view, 'Navigasi halaman stok komponen') !== false
        && strpos($view, 'Halaman <?php echo $currentPage; ?> dari <?php echo $maxPage; ?>') !== false
        && strpos($view, 'Sebelumnya') !== false
        && strpos($view, 'Berikutnya') !== false,
    'view exposes accessible previous and next page navigation'
);
$check(
    strpos($view, 'client-side pagination') === false
        && strpos($view, 'perPageSel.addEventListener') === false
        && strpos($view, 'Menampilkan semua <?php echo $totalComponents; ?> baris') !== false,
    'view no longer hides later rows in the browser and retains an explicit all-rows mode'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' Component Stock pagination check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'All ' . $checks . ' Component Stock pagination checks passed.' . PHP_EOL;
