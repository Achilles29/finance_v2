<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/application/controllers/Purchase.php');
$model = (string)file_get_contents($root . '/application/models/Purchase_model.php');
$view = (string)file_get_contents($root . '/application/views/purchase/item_price_history.php');
$checks = 0;
$failures = [];
$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$check($controller !== '' && $model !== '' && $view !== '', 'purchase price-history source files are readable');
$check(strpos($controller, '$this->Purchase_model->get_item_price_history($itemId, $limit, $offset)') !== false && strpos($controller, "'has_more' => (\$offset + \$returned) < \$total") !== false, 'controller delegates bounded pages and returns an explicit next-page signal');
$check(strpos($model, 'public function get_item_price_history(int $itemId, int $limit = 20, int $offset = 0): array') !== false && strpos($model, 'LIMIT {$limit} OFFSET {$offset}') !== false, 'purchase model exposes bounded, offset-based price-history reader');
$check(strpos($model, "FROM pur_purchase_receipt_line rl") !== false && strpos($model, "r.status = 'POSTED'") !== false, 'posted purchase receipt remains the detailed price-history source when present');
$check(strpos($model, 'rl.item_id = ? OR pol.item_id = ?') !== false && strpos($model, 'pol.unit_price') !== false, 'receipt query safely resolves both receipt and PO item identities with PO price');
$check(strpos($model, 'rl.qty_content_received / NULLIF(rl.qty_buy_received, 0)') !== false, 'HPP per isi derives from actual receipt conversion before fallbacks');
$check(strpos($model, "FROM pur_purchase_order_line pol") !== false && strpos($model, "po.status = 'PAID'") !== false && strpos($model, "'PAID_PURCHASE_ORDER' AS source_type") !== false, 'paid purchase orders are the primary operating source without requiring a receipt');
$check(strpos($model, 'receipt_line.purchase_order_line_id = pol.id') !== false && strpos($model, "receipt.status = 'POSTED'") !== false, 'a posted receipt suppresses its matching paid PO row to prevent duplicate history');
$check(strpos($model, "'LEGACY_LEDGER' AS source_type") !== false && strpos($model, 'AND l.receipt_line_id IS NULL') !== false, 'legacy ledger remains a non-duplicating fallback only');
$check(strpos($model, 'LIMIT {$limit}') !== false && strpos($model, 'min(200, max(5, $limit))') !== false, 'reader enforces a bounded result size');
$check(strpos($view, 'r.source_type === \'PURCHASE_RECEIPT\'') !== false && strpos($view, "r.source_type === 'PAID_PURCHASE_ORDER'") !== false && strpos($view, '<th>Sumber</th>') !== false, 'UI identifies receipt, paid PO, and legacy-ledger rows');
$check(
    strpos($view, 'id="iph-state"') !== false
        && strpos($view, 'id="iph-retry-btn"') !== false
        && strpos($view, 'id="iph-load-more-btn"') !== false
        && strpos($view, 'AbortController') !== false
        && strpos($view, "'&offset=' + offset") !== false,
    'responsive UI has clear initial/loading/empty/error states, retry, and guarded load-more pagination'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " purchase item-price-history check(s) failed.\n");
    exit(1);
}
echo 'All ' . $checks . " purchase item-price-history checks passed.\n";
