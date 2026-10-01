<?php

declare(strict_types=1);

// DB-free A4.3 contracts for inventory, production and POS stock movement integrity.
$root = dirname(__DIR__, 2);
$failures = [];
$checks = 0;
$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
};
$methodSlice = static function (string $relativePath, string $method) use ($root): ?string {
    $path = $root . '/' . $relativePath;
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) return null;
    $tokens = token_get_all($source);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $name = null;
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) { $name = $tokens[$j][1]; break; }
            if ($tokens[$j] === '(') break;
        }
        if ($name !== $method) continue;
        $slice = ''; $depth = 0; $opened = false;
        for ($j = $i, $n = count($tokens); $j < $n; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            $slice .= $text;
            if ($text === '{') { $opened = true; $depth++; }
            elseif ($text === '}' && $opened && --$depth === 0) return $slice;
        }
    }
    return null;
};

$routes = (string)file_get_contents($root . '/application/config/routes.php');
$routeContracts = [
    ['purchase/receipt/store', 'purchase/receipt_store'],
    ['inventory/stock/transfer/post/(:num)', 'inventory/stock_transfer_post/$1'],
    ['inventory/stock/adjustment/post/(:num)', 'inventory/stock_adjustment_post/$1'],
    ['production/component-batches/post/(:num)', 'production/component_batch_post/$1'],
    ['production/component-batches/void/(:num)', 'production/component_batch_void/$1'],
    ['inventory/stock/value-reconciliation/post', 'inventory_control/value_reconciliation_post'],
    ['inventory/stock/periods/cutoff-post/(:num)', 'inventory_control/period_cutoff_post/$1'],
    ['pos/orders/draft/confirm/(:num)', 'pos/order_draft_confirm/$1'],
    ['pos/orders/void/save', 'pos/order_void_save'],
];
foreach ($routeContracts as [$route, $target]) {
    $checks++;
    $needle = "\$route['{$route}'] = '{$target}';";
    if (strpos($routes, $needle) === false) $fail('route drift: ' . $needle);
}

$contracts = [
    ['receive lot/HPP transaction', 'application/models/Purchase_model.php', 'store_receipt_and_post', ['trans_begin(', 'registerReceiptInboundLot(', "'unit_cost' => \$unitCost", "'movement_type' => 'PURCHASE_IN'", 'trans_rollback(', 'trans_commit(']],
    ['transfer paired movement transaction', 'application/models/Purchase_model.php', 'post_stock_transfer', ['trans_begin(', 'transferDivisionToDivision(', "'movement_type' => 'TRANSFER_OUT'", "'movement_type' => 'TRANSFER_IN'", 'trans_rollback(', 'trans_commit(']],
    ['material adjustment period/atomic', 'application/models/Purchase_model.php', 'post_stock_adjustment', ['ensureActiveMonthOpen(', 'trans_begin(', 'postStockAdjustmentLine(', "'status' => 'POSTED'", 'trans_rollback(', 'trans_commit(']],
    ['material void reversal', 'application/models/Purchase_model.php', 'void_posted_stock_adjustment', ['trans_begin(', 'reverseMaterialMovementsForSource(', "'status' => 'VOID'", 'trans_rollback(', 'trans_commit(']],
    ['production input/output/lot/HPP', 'application/libraries/ComponentStockWriter.php', 'post_batch', ['ensureActiveMonthOpen(', 'trans_start(', "'movement_type' => 'PRODUCTION_OUT'", "'movement_type' => 'PRODUCTION_IN'", 'registerProductionInboundLot(', '$unitCostOutput', 'trans_rollback(', 'trans_complete(']],
    ['POS sale transaction and period locks', 'application/libraries/PosOrderStockService.php', 'post_commit_snapshot', ['trans_begin(', 'prelock_snapshot_periods(', 'post_component_usage(', 'post_material_usage(', 'trans_rollback(', 'trans_commit(']],
    ['POS void/refund reversal transaction', 'application/libraries/PosOrderStockService.php', 'reverse_commit_snapshot', ['trans_begin(', 'prelock_snapshot_periods(', 'reverse_component_usage(', 'reverse_material_usage(', 'apply_reversal_plan(', 'trans_rollback(', 'trans_commit(']],
    ['ledger nested transaction barrier', 'application/libraries/InventoryLedger.php', 'post', ['manage_transaction', 'trans_active(', "'INVENTORY_TRANSACTION_REQUIRED'", 'lockActivePeriodsForWrite(']],
    ['value reconciliation guarded', 'application/controllers/Inventory_control.php', 'value_reconciliation_post', ['is_superadmin(', 'require_inventory_control_mutation_csrf(', 'inventoryvaluereconciliationservice->post(']],
    ['period cutoff guarded', 'application/controllers/Inventory_control.php', 'period_cutoff_post', ['is_superadmin(', 'require_inventory_control_mutation_csrf(', "'POST CUT-OFF'", 'inventorycutoffservice->post(']],
];
foreach ($contracts as [$label, $file, $method, $needles]) {
    $checks++;
    $slice = $methodSlice($file, $method);
    if ($slice === null) { $fail($label . ': missing method ' . $file . '::' . $method); continue; }
    $missing = array_values(array_filter($needles, static fn(string $needle): bool => strpos($slice, $needle) === false));
    if ($missing !== []) $fail($label . ': missing exact token(s) in ' . $method . ': ' . implode(', ', $missing));
}

$atomicOrdering = [
    ['receipt status before commit', 'application/models/Purchase_model.php', 'store_receipt_and_post', "'status' => 'POSTED'", 'trans_commit('],
    ['transfer status before commit', 'application/models/Purchase_model.php', 'post_stock_transfer', "'status' => 'POSTED'", 'trans_commit('],
    ['material adjustment status before commit', 'application/models/Purchase_model.php', 'post_stock_adjustment', "'status' => 'POSTED'", 'trans_commit('],
    ['production batch status inside transaction', 'application/libraries/ComponentStockWriter.php', 'post_batch', "'status' => 'POSTED'", 'trans_complete('],
    ['component adjustment status inside transaction', 'application/libraries/ComponentStockWriter.php', 'post_adjustment', "'status' => 'POSTED'", 'trans_complete('],
];
foreach ($atomicOrdering as [$label, $file, $method, $writeToken, $commitToken]) {
    $checks++;
    $slice = $methodSlice($file, $method);
    $writeAt = $slice === null ? false : strpos($slice, $writeToken);
    $commitAt = $slice === null ? false : strrpos($slice, $commitToken);
    if ($writeAt === false || $commitAt === false || $writeAt > $commitAt) {
        $fail($label . ': status write is absent or occurs after the transaction commit boundary');
    }
}

$writerAtomicityContracts = [
    ['component adjustment', 'post_adjustment', 'inv_component_adjustment', 'trigger_availability_refresh('],
    ['production batch', 'post_batch', 'inv_component_batch', 'handle_inventory_changes('],
];
foreach ($writerAtomicityContracts as [$label, $method, $table, $availabilityToken]) {
    $checks++;
    $slice = $methodSlice('application/libraries/ComponentStockWriter.php', $method);
    if ($slice === null) {
        $fail($label . ': missing writer method ' . $method);
        continue;
    }

    $statusUpdateAt = strpos($slice, "->update('{$table}'");
    $commitAt = strrpos($slice, 'trans_complete(');
    $availabilityAt = strpos($slice, $availabilityToken);
    $required = [
        "->where('status', 'DRAFT')",
        "'status' => 'POSTED'",
        '$statusUpdated',
        '$statusError',
        'affected_rows() !== 1',
        'trans_status() === false',
        'throw new RuntimeException(',
    ];
    $missing = array_values(array_filter($required, static fn(string $needle): bool => strpos($slice, $needle) === false));
    if ($missing !== []) {
        $fail($label . ': status finalization is not fail-closed: ' . implode(', ', $missing));
    }
    if ($statusUpdateAt === false || $commitAt === false || $statusUpdateAt > $commitAt) {
        $fail($label . ': status finalization must be inside the writer transaction');
    }
    if ($availabilityAt === false || $commitAt === false || $availabilityAt < $commitAt) {
        $fail($label . ': availability rebuild must remain after commit');
    }
}

$checks++;
$adjustmentController = $methodSlice('application/controllers/Production.php', 'post_component_adjustment_document');
$writerCallAt = $adjustmentController === null
    ? false
    : strpos($adjustmentController, 'componentstockwriter->post_adjustment(');
$afterWriterCall = $writerCallAt === false
    ? ''
    : substr($adjustmentController, $writerCallAt);
if (
    $adjustmentController === null
    || $writerCallAt === false
    || strpos($afterWriterCall, "->update('inv_component_adjustment'") !== false
    || strpos($afterWriterCall, "'status' => 'POSTED'") !== false
) {
    $fail('production controller must delegate adjustment status finalization exclusively to ComponentStockWriter');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' of ' . $checks . ' inventory/production contract(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'PASS: all ' . $checks . ' inventory/production source contracts passed.' . PHP_EOL;
