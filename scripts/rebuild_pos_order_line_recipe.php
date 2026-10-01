<?php
declare(strict_types=1);

/**
 * Rebuild one POS order line after a recipe quantity/source correction.
 *
 * Usage:
 *   php scripts/rebuild_pos_order_line_recipe.php ORDER_NO ORDER_LINE_ID SOURCE_COMMIT_ID [--apply]
 *
 * Run without --apply first. The source commit must contain only the target
 * order line, preventing an accidental reversal of another product.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$apply = false;
$arguments = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $apply = true;
        continue;
    }
    $arguments[] = $argument;
}

if (count($arguments) !== 3) {
    fwrite(STDERR, "Usage: php scripts/rebuild_pos_order_line_recipe.php ORDER_NO ORDER_LINE_ID SOURCE_COMMIT_ID [--apply]\n");
    exit(1);
}

$orderNo = trim((string)$arguments[0]);
$orderLineId = max(0, (int)$arguments[1]);
$sourceCommitId = max(0, (int)$arguments[2]);
if ($orderNo === '' || $orderLineId <= 0 || $sourceCommitId <= 0) {
    fwrite(STDERR, "ORDER_NO, ORDER_LINE_ID, and SOURCE_COMMIT_ID must be valid.\n");
    exit(1);
}

$root = dirname(__DIR__);
if (!function_exists('get_instance')) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $_SERVER['CI_ENV'] = 'production';
    $bootstrapArgv = $argv;
    $bootstrapServerArgv = $_SERVER['argv'] ?? null;
    $bootstrapServerArgc = $_SERVER['argc'] ?? null;
    // CodeIgniter maps CLI arguments to a controller URI. Do not let this
    // repair script's arguments be interpreted as a web route on bootstrap.
    $argv = [$argv[0]];
    $_SERVER['argv'] = $argv;
    $_SERVER['argc'] = 1;
    chdir($root);
    ob_start();
    require $root . '/index.php';
    ob_end_clean();
    $argv = $bootstrapArgv;
    if ($bootstrapServerArgv === null) {
        unset($_SERVER['argv']);
    } else {
        $_SERVER['argv'] = $bootstrapServerArgv;
    }
    if ($bootstrapServerArgc === null) {
        unset($_SERVER['argc']);
    } else {
        $_SERVER['argc'] = $bootstrapServerArgc;
    }
}

$CI = get_instance();
if (!$CI) {
    fwrite(STDERR, "CodeIgniter bootstrap failed.\n");
    exit(1);
}

$CI->load->database();
$CI->load->model('Pos_model');
$CI->load->library('PosStockCommitService');
$CI->load->library('PosOrderStockService');
$CI->load->library('PosAvailabilityRebuildService');

$db = $CI->db;
$db->db_debug = false;

$fail = static function (string $message, array $data = [], int $code = 1): void {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'message' => $message,
        'data' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($code);
};

$order = $db->select('id, order_no, status, stock_commit_status, stock_committed_at, outlet_id, cashier_employee_id')
    ->from('pos_order')
    ->where('order_no', $orderNo)
    ->limit(1)
    ->get()
    ->row_array();
if (!$order) {
    $fail('Order POS tidak ditemukan.', ['order_no' => $orderNo]);
}

$orderId = (int)$order['id'];
$orderStatus = strtoupper(trim((string)($order['status'] ?? '')));
if (in_array($orderStatus, ['VOID', 'REFUND_FULL', 'REFUNDED_FULL'], true)) {
    $fail('Order terminal tidak boleh direbuild.', ['order_no' => $orderNo, 'status' => $orderStatus]);
}

$orderLine = $db->select('ol.*, p.product_code, p.product_name')
    ->from('pos_order_line ol')
    ->join('mst_product p', 'p.id = ol.product_id', 'left')
    ->where('ol.id', $orderLineId)
    ->where('ol.order_id', $orderId)
    ->limit(1)
    ->get()
    ->row_array();
if (!$orderLine) {
    $fail('Order line tidak ditemukan pada order tersebut.', [
        'order_no' => $orderNo,
        'order_line_id' => $orderLineId,
    ]);
}

$sourceCommit = $db->from('pos_stock_commit')
    ->where('id', $sourceCommitId)
    ->where('order_id', $orderId)
    ->limit(1)
    ->get()
    ->row_array();
if (!$sourceCommit) {
    $fail('Source stock commit tidak ditemukan pada order tersebut.', [
        'source_commit_id' => $sourceCommitId,
        'order_no' => $orderNo,
    ]);
}

$sourceLines = $db->from('pos_stock_commit_line')
    ->where('commit_id', $sourceCommitId)
    ->order_by('line_no', 'ASC')
    ->get()
    ->result_array();
if (empty($sourceLines)) {
    $fail('Source stock commit tidak memiliki line.', ['source_commit_id' => $sourceCommitId]);
}

$foreignLines = array_values(array_filter($sourceLines, static function (array $line) use ($orderLineId): bool {
    return (int)($line['order_line_id'] ?? 0) !== $orderLineId;
}));
if (!empty($foreignLines)) {
    $fail('Source commit memuat order line lain dan ditolak demi keamanan.', [
        'source_commit_id' => $sourceCommitId,
        'foreign_order_line_ids' => array_values(array_unique(array_map(static function (array $line): int {
            return (int)($line['order_line_id'] ?? 0);
        }, $foreignLines))),
    ]);
}

$activeSourceLines = array_values(array_filter($sourceLines, static function (array $line): bool {
    return round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4) > 0.0001;
}));
if (empty($activeSourceLines)) {
    $fail('Source commit tidak memiliki pemakaian stok aktif untuk direbuild.', [
        'source_commit_id' => $sourceCommitId,
        'commit_status' => (string)($sourceCommit['commit_status'] ?? ''),
    ]);
}

$marker = 'RECIPE_REBUILD:' . $orderNo . ':' . $orderLineId . ':' . $sourceCommitId;
$existingReplacement = $db->from('pos_stock_commit')
    ->where('order_id', $orderId)
    ->where('id !=', $sourceCommitId)
    ->like('notes', $marker, 'both')
    ->order_by('id', 'DESC')
    ->limit(1)
    ->get()
    ->row_array();
if ($existingReplacement && strtoupper((string)($existingReplacement['commit_status'] ?? '')) === 'COMMITTED') {
    echo json_encode([
        'ok' => true,
        'already_rebuilt' => true,
        'message' => 'Recipe rebuild sebelumnya sudah committed. Tidak ada perubahan baru.',
        'replacement_commit_id' => (int)$existingReplacement['id'],
        'replacement_commit_no' => (string)($existingReplacement['commit_no'] ?? ''),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$actorEmployeeId = max(0, (int)($sourceCommit['actor_employee_id'] ?? $order['cashier_employee_id'] ?? 0));
$preResolved = $CI->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
    'allowed_statuses' => ['CONFIRMED', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED'],
    'line_ids' => [$orderLineId],
]);
if (!($preResolved['ok'] ?? false) || empty($preResolved['lines'])) {
    $fail((string)($preResolved['message'] ?? 'Recipe aktif gagal di-resolve.'), ['resolved' => $preResolved]);
}

$preResolvedLines = array_values(array_filter((array)$preResolved['lines'], static function (array $line) use ($orderLineId): bool {
    return (int)($line['order_line_id'] ?? 0) === $orderLineId;
}));
if (empty($preResolvedLines)) {
    $fail('Recipe aktif tidak menghasilkan konsumsi untuk order line target.', ['order_line_id' => $orderLineId]);
}

$dryRun = [
    'ok' => true,
    'dry_run' => !$apply,
    'order' => [
        'id' => $orderId,
        'order_no' => $orderNo,
        'status' => (string)($order['status'] ?? ''),
        'stock_commit_status' => (string)($order['stock_commit_status'] ?? ''),
    ],
    'order_line' => [
        'id' => $orderLineId,
        'product_code' => (string)($orderLine['product_code'] ?? ''),
        'product_name' => (string)($orderLine['product_name'] ?? ''),
        'qty' => (float)($orderLine['qty'] ?? 0),
        'hpp_before' => (float)($orderLine['hpp_live_snapshot'] ?? 0),
        'cogs_before' => (float)($orderLine['cogs_amount'] ?? 0),
    ],
    'source_commit' => [
        'id' => $sourceCommitId,
        'commit_no' => (string)($sourceCommit['commit_no'] ?? ''),
        'status' => (string)($sourceCommit['commit_status'] ?? ''),
        'lines' => array_map(static function (array $line): array {
            return [
                'id' => (int)$line['id'],
                'source_name' => (string)($line['source_name_snapshot'] ?? ''),
                'source_kind' => (string)($line['source_kind'] ?? ''),
                'required_qty' => (float)($line['required_qty'] ?? 0),
                'remaining_qty' => round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4),
                'total_cost_live' => (float)($line['total_cost_live'] ?? 0),
            ];
        }, $activeSourceLines),
    ],
    'replacement_recipe' => array_map(static function (array $line): array {
        return [
            'source_name' => (string)($line['source_name_snapshot'] ?? ''),
            'source_kind' => (string)($line['source_kind'] ?? ''),
            'component_id' => !empty($line['component_id']) ? (int)$line['component_id'] : null,
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'required_qty' => (float)($line['required_qty'] ?? 0),
            'resolved_division_id' => !empty($line['resolved_source_division_id']) ? (int)$line['resolved_source_division_id'] : null,
        ];
    }, $preResolvedLines),
];

if (!$apply) {
    echo json_encode($dryRun, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if (strtoupper((string)($sourceCommit['commit_status'] ?? '')) !== 'COMMITTED') {
    $fail('Source commit harus berstatus COMMITTED sebelum recipe rebuild.', [
        'source_commit_id' => $sourceCommitId,
        'commit_status' => (string)($sourceCommit['commit_status'] ?? ''),
    ]);
}

$repairNote = 'Recipe rebuild ' . $marker . ' - qty recipe lama dikoreksi dari master aktif.';
$decisions = array_map(static function (array $line) use ($repairNote): array {
    return [
        'line_id' => (int)$line['id'],
        'line_key' => 'commit_line:' . (int)$line['id'],
        'return_policy' => 'RETURN_TO_STOCK',
        'reverse_qty' => round((float)($line['committed_qty'] ?? 0) - (float)($line['reversed_qty'] ?? 0), 4),
        'notes' => $repairNote,
    ];
}, $activeSourceLines);

$reverse = $CI->posorderstockservice->reverse_commit_snapshot($sourceCommitId, $decisions, [
    'actor_employee_id' => $actorEmployeeId,
    'notes' => $repairNote,
    'document_type' => 'POS_RECIPE_REBUILD',
    'document_no' => $marker,
    'reason' => 'Koreksi quantity recipe POS.',
]);
if (!($reverse['ok'] ?? false)) {
    $fail((string)($reverse['message'] ?? 'Reversal source commit gagal.'), ['reverse' => $reverse]);
}
if (strtoupper((string)($reverse['commit_status'] ?? '')) !== 'REVERSED') {
    $fail('Source commit tidak fully reversed; replacement tidak dibuat demi keamanan.', ['reverse' => $reverse]);
}

// The reversal marks the order REVERSED. This is a controlled replacement,
// not a void: unlock the status only after the old commit is fully reversed.
$db->trans_begin();
try {
    $lockedOrder = $db->query('SELECT id, status, stock_commit_status FROM pos_order WHERE id = ? FOR UPDATE', [$orderId])->row_array();
    if (!$lockedOrder) {
        throw new RuntimeException('Order tidak ditemukan setelah reversal commit.');
    }
    $db->where('id', $orderId)->update('pos_order', ['stock_commit_status' => 'PROCESSING']);
    $db->insert('pos_order_state_log', [
        'order_id' => $orderId,
        'from_status' => (string)($lockedOrder['status'] ?? ''),
        'to_status' => (string)($lockedOrder['status'] ?? ''),
        'event_code' => 'ORDER_RECIPE_REBUILD_START',
        'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
        'notes' => $repairNote,
    ]);
    if ($db->trans_status() === false) {
        throw new RuntimeException('Gagal menyiapkan status order untuk replacement commit.');
    }
    $db->trans_commit();
} catch (Throwable $e) {
    $db->trans_rollback();
    $fail($e->getMessage(), ['reverse' => $reverse]);
}

// Resolve only after the erroneous stock usage is returned. This preserves
// the current FIFO unit cost in the replacement snapshot.
$resolved = $CI->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
    'allowed_statuses' => ['CONFIRMED', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED'],
    'line_ids' => [$orderLineId],
]);
if (!($resolved['ok'] ?? false) || empty($resolved['lines'])) {
    $fail((string)($resolved['message'] ?? 'Recipe aktif gagal di-resolve setelah reversal.'), ['resolved' => $resolved]);
}
$resolvedLines = array_values(array_filter((array)$resolved['lines'], static function (array $line) use ($orderLineId): bool {
    return (int)($line['order_line_id'] ?? 0) === $orderLineId;
}));
if (empty($resolvedLines)) {
    $fail('Recipe aktif tidak menghasilkan konsumsi setelah reversal.', ['order_line_id' => $orderLineId]);
}

$replacementHeader = (array)($resolved['header'] ?? []);
$replacementHeader['commit_status'] = 'DRAFT';
$replacementHeader['commit_reason'] = 'MANUAL';
$replacementHeader['actor_employee_id'] = $actorEmployeeId > 0 ? $actorEmployeeId : null;
$replacementHeader['notes'] = $marker . ' | ' . $repairNote;
$replacement = $CI->posstockcommitservice->create_snapshot($orderId, $replacementHeader, $resolvedLines);
if (!($replacement['ok'] ?? false)) {
    $fail((string)($replacement['message'] ?? 'Replacement snapshot gagal dibuat.'), [
        'reverse' => $reverse,
        'source_commit_id' => $sourceCommitId,
    ]);
}

$replacementCommitId = (int)($replacement['id'] ?? 0);
$post = $CI->posorderstockservice->post_commit_snapshot($replacementCommitId, [
    'actor_employee_id' => $actorEmployeeId,
    'notes' => $repairNote,
]);
if (!($post['ok'] ?? false)) {
    $CI->posstockcommitservice->mark_failed($replacementCommitId, (string)($post['message'] ?? 'Posting replacement gagal.'));
    $fail((string)($post['message'] ?? 'Posting replacement snapshot gagal.'), [
        'replacement_commit_id' => $replacementCommitId,
        'reverse' => $reverse,
    ]);
}

$commit = $CI->posstockcommitservice->mark_committed($replacementCommitId);
if (!($commit['ok'] ?? false)) {
    $fail((string)($commit['message'] ?? 'Replacement snapshot tidak dapat ditandai committed.'), [
        'replacement_commit_id' => $replacementCommitId,
        'post' => $post,
    ]);
}

$cost = $db->select('COALESCE(SUM(total_cost_live), 0) AS total_cost', false)
    ->from('pos_stock_commit_line')
    ->where('commit_id', $replacementCommitId)
    ->where('order_line_id', $orderLineId)
    ->get()
    ->row_array();
$totalCost = round((float)($cost['total_cost'] ?? 0), 6);
$lineQty = round((float)($orderLine['qty'] ?? 0), 4);
if ($lineQty <= 0 || $totalCost < 0) {
    $fail('Biaya replacement tidak valid untuk memperbarui HPP order line.', [
        'replacement_commit_id' => $replacementCommitId,
        'qty' => $lineQty,
        'total_cost' => $totalCost,
    ]);
}
$hppLive = round($totalCost / $lineQty, 6);
$db->where('id', $orderLineId)->update('pos_order_line', [
    'hpp_live_snapshot' => $hppLive,
    'cogs_amount' => round($totalCost, 2),
]);
if ($db->affected_rows() < 0) {
    $fail('Gagal memperbarui HPP snapshot order line.', ['order_line_id' => $orderLineId]);
}

$orderState = $CI->Pos_model->update_order_stock_commit_state($orderId, 'POSTED', [
    'actor_employee_id' => $actorEmployeeId,
    'event_code' => 'ORDER_RECIPE_REBUILD',
    'note' => $repairNote . ' | Replacement snapshot #' . $replacementCommitId . ' posted.',
    'stock_committed_at' => (string)($order['stock_committed_at'] ?? date('Y-m-d H:i:s')),
]);
if (!($orderState['ok'] ?? false)) {
    $fail((string)($orderState['message'] ?? 'Status stock commit order gagal dipulihkan.'), [
        'replacement_commit_id' => $replacementCommitId,
    ]);
}

$materialIds = [];
$componentIds = [];
foreach ($resolvedLines as $resolvedLine) {
    $materialId = (int)($resolvedLine['material_id'] ?? 0);
    $componentId = (int)($resolvedLine['component_id'] ?? 0);
    if ($materialId > 0) {
        $materialIds[$materialId] = $materialId;
    }
    if ($componentId > 0) {
        $componentIds[$componentId] = $componentId;
    }
}

$affectedProductIds = [(int)$orderLine['product_id'] => (int)$orderLine['product_id']];
foreach ($materialIds as $materialId) {
    foreach ($CI->posavailabilityrebuildservice->resolve_affected_products_from_material($materialId) as $productId) {
        $affectedProductIds[(int)$productId] = (int)$productId;
    }
}
foreach ($componentIds as $componentId) {
    foreach ($CI->posavailabilityrebuildservice->resolve_affected_products_from_component($componentId) as $productId) {
        $affectedProductIds[(int)$productId] = (int)$productId;
    }
}

$availability = [];
$outletIds = array_map(static function (array $row): int {
    return (int)($row['id'] ?? 0);
}, $db->select('id')->from('pos_outlet')->where('is_active', 1)->get()->result_array());
foreach (array_values(array_filter($outletIds)) as $outletId) {
    $CI->posavailabilityrebuildservice->mark_dirty($outletId, array_values($affectedProductIds), [
        'event_source' => 'POS_RECIPE_REBUILD',
    ]);
    $availability[] = ['outlet_id' => $outletId] + $CI->posavailabilityrebuildservice->rebuild_products($outletId, array_values($affectedProductIds), [
        'trigger_context' => 'POS_RECIPE_REBUILD',
        'event_source' => 'POS_RECIPE_REBUILD',
        'event_table' => 'pos_order',
        'event_id' => $orderId,
        'actor_employee_id' => $actorEmployeeId,
    ]);
}

echo json_encode([
    'ok' => true,
    'message' => 'Recipe rebuild selesai.',
    'order_no' => $orderNo,
    'order_line_id' => $orderLineId,
    'source_commit_id' => $sourceCommitId,
    'replacement_commit_id' => $replacementCommitId,
    'replacement_commit_no' => (string)($replacement['commit_no'] ?? ''),
    'hpp_before' => (float)($orderLine['hpp_live_snapshot'] ?? 0),
    'hpp_after' => $hppLive,
    'cogs_after' => round($totalCost, 2),
    'reverse' => $reverse,
    'post' => $post,
    'availability' => $availability,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
