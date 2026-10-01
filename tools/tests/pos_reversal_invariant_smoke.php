<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/libraries/PosStockCommitService.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: {$label}\n");
        return;
    }
    echo "PASS: {$label}\n";
};

$lines = [
    ['id' => 11, 'line_type' => 'PRODUCT', 'order_line_id' => 101, 'committed_qty' => 5, 'reversed_qty' => 2],
    ['id' => 12, 'line_type' => 'PRODUCT', 'order_line_id' => 102, 'committed_qty' => 4, 'reversed_qty' => 0],
];

$oversize = PosStockCommitService::prepare_reversal_decisions($lines, [[
    'line_key' => 'commit_line:11', 'return_policy' => 'RETURN_TO_STOCK', 'reverse_qty' => 99,
]]);
$check(($oversize['ok'] ?? false) === true, 'oversize decision accepted consistently');
$check((float)($oversize['decisions'][0]['reverse_qty'] ?? 0) === 3.0, 'oversize quantity capped to authoritative residual');
$check(($oversize['commit_status'] ?? '') === 'PARTIAL_REVERSED', 'omitted residual line keeps header partial');

$invalid = PosStockCommitService::prepare_reversal_decisions($lines, [[
    'line_key' => 'commit_line:11', 'return_policy' => 'MAGIC', 'reverse_qty' => 1,
]]);
$check(($invalid['ok'] ?? true) === false, 'invalid return policy rejected');

$duplicate = PosStockCommitService::prepare_reversal_decisions($lines, [
    ['line_key' => 'commit_line:11', 'return_policy' => 'NO_RETURN', 'reverse_qty' => 1],
    ['line_key' => 'PRODUCT:101:11', 'return_policy' => 'NO_RETURN', 'reverse_qty' => 1],
]);
$check(($duplicate['ok'] ?? true) === false, 'duplicate aliases for one line rejected');

$unknown = PosStockCommitService::prepare_reversal_decisions($lines, [[
    'line_key' => 'commit_line:999', 'return_policy' => 'NO_RETURN', 'reverse_qty' => 1,
]]);
$check(($unknown['ok'] ?? true) === false, 'unknown line rejected');

$full = PosStockCommitService::prepare_reversal_decisions($lines, [
    ['line_id' => 11, 'return_policy' => 'RETURN_TO_STOCK', 'reverse_qty' => 3],
    ['line_id' => 12, 'return_policy' => 'ADJUSTMENT_ONLY', 'reverse_qty' => 4],
]);
$check(($full['ok'] ?? false) === true && ($full['commit_status'] ?? '') === 'REVERSED', 'all residual lines produce fully reversed header');

$alreadyReversed = [
    ['id' => 21, 'line_type' => 'PRODUCT', 'order_line_id' => 201, 'committed_qty' => 2, 'reversed_qty' => 2],
];
$retry = PosStockCommitService::prepare_reversal_decisions($alreadyReversed, [[
    'line_id' => 21, 'return_policy' => 'RETURN_TO_STOCK', 'reverse_qty' => 2,
]]);
$check(($retry['ok'] ?? true) === false, 'retry with no effective reversal rejected');

if ($failures) {
    exit(1);
}

echo "POS reversal invariant smoke passed.\n";
