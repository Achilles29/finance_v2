<?php

declare(strict_types=1);

// DB-free A4.3 source contracts for Purchase Order and Store Request lifecycles.
$root = dirname(__DIR__, 2);
$csrfOnly = in_array('--csrf-only', $argv, true);
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

if (!$csrfOnly) {
    $routes = (string)file_get_contents($root . '/application/config/routes.php');
    $routeContracts = [
    ['purchase/order/store', 'purchase/order_store'],
    ['purchase/order/status-update', 'purchase/order_status_update'],
    ['purchase/receipt/store', 'purchase/receipt_store'],
    ['procurement/store-request/store', 'procurement/store_request_store'],
    ['procurement/store-request/action/(:num)', 'procurement/store_request_action/$1'],
    ['procurement/store-request/fulfill/(:num)', 'procurement/store_request_fulfill/$1'],
    ['procurement/division-po-sr/action/(:num)', 'procurement/division_po_sr_action/$1'],
    ];
    foreach ($routeContracts as [$route, $target]) {
        $checks++;
        $needle = "\$route['{$route}'] = '{$target}';";
        if (strpos($routes, $needle) === false) $fail('route drift: ' . $needle);
    }

    $contracts = [
    ['PO create RBAC', 'application/controllers/Purchase.php', 'order_store', ["require_permission(self::PAGE_ORDER, 'create')", 'store_order_with_lines(']],
    ['PO create atomic log', 'application/models/Purchase_model.php', 'store_order_with_lines', ['trans_begin(', "'action_code' => 'PO_CREATE'", 'trans_rollback(', 'trans_commit(']],
    ['PO approve/reject/cancel transitions', 'application/models/Purchase_model.php', 'update_order_status', ["'APPROVED'", "'REJECTED'", "'VOID'", 'rollbackPurchaseOnVoid(', 'trans_begin(', 'trans_commit(']],
    ['receipt RBAC', 'application/controllers/Purchase.php', 'receipt_store', ["can(self::PAGE_RECEIPT, 'create')", 'store_receipt_and_post(']],
    ['receipt atomic FIFO/ledger', 'application/models/Purchase_model.php', 'store_receipt_and_post', ['trans_begin(', 'registerReceiptInboundLot(', "'movement_type' => 'PURCHASE_IN'", 'trans_rollback(', 'trans_commit(']],
    ['receipt concurrent quantity guard', 'application/models/Purchase_model.php', 'store_receipt_and_post', ['SELECT * FROM pur_purchase_order WHERE id = ? LIMIT 1 FOR UPDATE', 'SELECT id FROM pur_purchase_order_line WHERE purchase_order_id = ? ORDER BY id ASC FOR UPDATE', '$currentReceiptQtyByPoLine', 'sisa terkini']],
    ['receipt cumulative status', 'application/models/Purchase_model.php', 'store_receipt_and_post', ['$eligiblePositiveQtyLineIds', "'PARTIAL_RECEIVED'", "'RECEIVED'", '$terminalStatuses', "update('pur_purchase_order'"]],
    ['receipt actual-status audit/fail-closed', 'application/models/Purchase_model.php', 'store_receipt_and_post', ["'status_before' => \$statusBefore", "'status_after' => \$statusAfter", '$auditInserted = $this->db->insert', '$auditInserted === false', 'trans_commit() === false']],
    ['receipt rejects terminal invalid PO before writes', 'application/models/Purchase_model.php', 'store_receipt_and_post', ["in_array(\$statusBefore, ['REJECTED', 'CLOSED', 'VOID'], true)", 'Receipt tidak dapat diposting']],
    ['receipt non-stock/zero-qty safety', 'application/models/Purchase_model.php', 'store_receipt_and_post', ['$isStockPurchase', 'non-stock', '$orderedQtyBuy <= 0']],
    ['non-stock PO bypass', 'application/models/Purchase_model.php', 'autoPostOutstandingReceiptOnStatusReached', ["'affects_inventory'", "'skipped' => 'NOT_INVENTORY'"]],
    ['SR create RBAC', 'application/controllers/Procurement.php', 'store_request_store', ["require_permission(self::PAGE_SR, 'create')", 'create_store_request(']],
    ['SR create atomic', 'application/models/Procurement_model.php', 'create_store_request', ['trans_begin(', 'trans_rollback(', 'trans_commit(']],
    ['SR approve/reject/cancel', 'application/models/Procurement_model.php', 'apply_store_request_action', ["'SUBMIT', 'APPROVE', 'REJECT', 'VOID'", 'reverse_fulfillments_before_void(', 'trans_begin(', 'trans_commit(']],
    ['SR fulfill RBAC', 'application/controllers/Procurement.php', 'store_request_fulfill', ["require_permission(self::PAGE_SR, 'edit')", 'fulfill_auto_from_warehouse(']],
    ['SR partial fulfill atomic FIFO', 'application/models/Procurement_model.php', 'fulfill_auto_from_warehouse', ['transferWarehouseToDivision(', "'PARTIAL_FULFILLED'", 'trans_begin(', 'trans_rollback(', 'trans_commit(']],
    ['division request reject/void scope', 'application/controllers/Procurement.php', 'division_po_sr_action', ['$action === \'REJECT\'', '$action === \'VOID\'', "scope['can_verify']", 'isDivisionRequestAccessible(']],
    ['PO rollback receipt/payment', 'application/models/Purchase_model.php', 'rollbackPurchaseOnVoid', ['rollbackPostedReceiptsOnVoid(', 'rollbackPaidPlansOnVoid(']],
    ];
    foreach ($contracts as [$label, $file, $method, $needles]) {
        $checks++;
        $slice = $methodSlice($file, $method);
        if ($slice === null) { $fail($label . ': missing method ' . $file . '::' . $method); continue; }
        $missing = array_values(array_filter($needles, static fn(string $needle): bool => strpos($slice, $needle) === false));
        if ($missing !== []) $fail($label . ': missing exact token(s) in ' . $method . ': ' . implode(', ', $missing));
    }
}

$csrfModules = [
    [
        'label' => 'Purchase',
        'file' => 'application/controllers/Purchase.php',
        'session_key' => "PURCHASE_MUTATION_CSRF_SESSION_KEY = 'purchase_mutation_csrf'",
        'ci_header' => "PURCHASE_MUTATION_CSRF_CI_HEADER = 'X-Purchase-Mutation-Csrf'",
        'token_helper' => 'purchase_mutation_csrf',
        'guard_helper' => 'require_purchase_mutation_csrf',
        'writers' => ['order_store', 'order_update', 'order_status_update', 'receipt_store', 'finance_mutation_store'],
        'renderers' => ['index', 'order_detail', 'order_create', 'order_edit', 'receipt_index', 'finance_mutation_index'],
        'view_token' => 'purchase_mutation_csrf_token',
        'views' => [
            'application/views/purchase/index.php',
            'application/views/purchase/order_detail.php',
            'application/views/purchase/order_create.php',
            'application/views/purchase/receipt_index.php',
            'application/views/purchase/finance_mutation_index.php',
        ],
        'browser_header' => 'X-Purchase-Mutation-CSRF',
    ],
    [
        'label' => 'Procurement',
        'file' => 'application/controllers/Procurement.php',
        'session_key' => "PROCUREMENT_MUTATION_CSRF_SESSION_KEY = 'procurement_mutation_csrf'",
        'ci_header' => "PROCUREMENT_MUTATION_CSRF_CI_HEADER = 'X-Procurement-Mutation-Csrf'",
        'token_helper' => 'procurement_mutation_csrf',
        'guard_helper' => 'require_procurement_mutation_csrf',
        'writers' => ['store_request_store', 'store_request_update', 'store_request_action', 'store_request_fulfill'],
        'renderers' => ['store_requests', 'store_request_create', 'store_request_edit'],
        'view_token' => 'procurement_mutation_csrf_token',
        'views' => [
            'application/views/procurement/store_requests.php',
            'application/views/procurement/store_request_form.php',
        ],
        'browser_header' => 'X-Procurement-Mutation-CSRF',
    ],
];

foreach ($csrfModules as $module) {
    $controller = (string)file_get_contents($root . '/' . $module['file']);
    $tokenHelper = $methodSlice($module['file'], $module['token_helper']) ?? '';
    $guardHelper = $methodSlice($module['file'], $module['guard_helper']) ?? '';

    $checks++;
    if (
        strpos($controller, $module['session_key']) === false
        || strpos($controller, $module['ci_header']) === false
        || strpos($tokenHelper, 'bin2hex(random_bytes(32))') === false
        || strpos($tokenHelper, "preg_match('/\\A[0-9a-f]{64}\\z/D'") === false
    ) {
        $fail($module['label'] . ' CSRF token/session contract drift');
    }

    $checks++;
    if (
        strpos($guardHelper, "method(true) !== 'POST'") === false
        || strpos($guardHelper, 'get_request_header(') === false
        || strpos($guardHelper, 'hash_equals(') === false
        || strpos($guardHelper, '->post(') !== false
        || strpos($guardHelper, 'raw_input_stream') !== false
    ) {
        $fail($module['label'] . ' CSRF guard must require POST and header/session equality without payload fallback');
    }

    foreach ($module['writers'] as $writer) {
        $slice = $methodSlice($module['file'], $writer) ?? '';
        $guardPos = strpos($slice, $module['guard_helper'] . '()');
        $payloadPos = strpos($slice, 'requestPayload()');
        $modelPos = strpos($slice, '_model->');
        $checks++;
        if (
            $guardPos === false
            || ($payloadPos !== false && $guardPos >= $payloadPos)
            || ($modelPos !== false && $guardPos >= $modelPos)
        ) {
            $fail($module['label'] . '::' . $writer . ' must guard before payload/model access');
        }
    }

    foreach ($module['renderers'] as $renderer) {
        $slice = $methodSlice($module['file'], $renderer) ?? '';
        $checks++;
        if (strpos($slice, "'" . $module['view_token'] . "' => \$this->" . $module['token_helper'] . '()') === false) {
            $fail($module['label'] . '::' . $renderer . ' does not render the scoped token');
        }
    }

    foreach ($module['views'] as $viewPath) {
        $view = (string)file_get_contents($root . '/' . $viewPath);
        $checks++;
        if (strpos($view, $module['view_token']) === false || strpos($view, $module['browser_header']) === false) {
            $fail($viewPath . ' does not send the canonical scoped CSRF header');
        }
    }
}

$checks++;
$receiptSlice = $methodSlice('application/models/Purchase_model.php', 'store_receipt_and_post') ?? '';
$terminalReject = strpos($receiptSlice, "in_array(\$statusBefore, ['REJECTED', 'CLOSED', 'VOID'], true)");
$receiptInsert = strpos($receiptSlice, "insert('pur_purchase_receipt'");
$lotWrite = strpos($receiptSlice, 'registerReceiptInboundLot(');
$ledgerWrite = strpos($receiptSlice, 'postInventoryLedgerEntry(');
if (
    $terminalReject === false
    || $receiptInsert === false
    || $lotWrite === false
    || $ledgerWrite === false
    || $terminalReject >= min($receiptInsert, $lotWrite, $ledgerWrite)
) {
    $fail('terminal PO rejection must happen before receipt, lot, or ledger writes');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' of ' . $checks . ' Purchase/SR contract(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'PASS: all ' . $checks . ($csrfOnly ? ' Purchase/Procurement CSRF' : ' Purchase/SR') . ' source contracts passed.' . PHP_EOL;
