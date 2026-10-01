<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

if (!function_exists('get_instance')) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    chdir(dirname(__DIR__));
    ob_start();
    require dirname(__DIR__) . '/index.php';
    ob_end_clean();
}

$CI = get_instance();
if (!$CI) {
    fwrite(STDERR, "bootstrap_failed\n");
    exit(1);
}

$CI->load->database();
$CI->load->model('Purchase_model');
$CI->load->library('MaterialFifoManager');

$db = $CI->db;

$summary = [
    'item_uom_fixed' => [],
    'opening_reposted' => [],
    'draft_adjustment_lines_fixed' => [],
    'warehouse_opening_lots_backfilled' => [],
    'adjustment_post_result' => null,
    'errors' => [],
];

$resolveUomCode = static function ($uomId) use ($db): ?string {
    $uomId = (int)$uomId;
    if ($uomId <= 0) {
        return null;
    }
    $row = $db->select('code')->from('mst_uom')->where('id', $uomId)->limit(1)->get()->row_array();
    return $row ? (string)($row['code'] ?? '') : null;
};

$fetchCanonicalProfileKey = static function (array $identity) use ($db): ?string {
    $db->select('profile_key')
        ->from('inv_warehouse_stock_opening_snapshot')
        ->where('item_id', (int)($identity['item_id'] ?? 0))
        ->where('buy_uom_id', (int)($identity['buy_uom_id'] ?? 0))
        ->where('content_uom_id', (int)($identity['content_uom_id'] ?? 0));

    $materialId = (int)($identity['material_id'] ?? 0);
    if ($materialId > 0) {
        $db->where('material_id', $materialId);
    } else {
        $db->where('material_id IS NULL', null, false);
    }

    $profileName = trim((string)($identity['profile_name'] ?? ''));
    if ($profileName !== '') {
        $db->where('profile_name', $profileName);
    }
    $profileBrand = trim((string)($identity['profile_brand'] ?? ''));
    if ($profileBrand !== '') {
        $db->where('profile_brand', $profileBrand);
    } else {
        $db->group_start()->where('profile_brand IS NULL', null, false)->or_where('profile_brand', '')->group_end();
    }
    $profileDescription = trim((string)($identity['profile_description'] ?? ''));
    if ($profileDescription !== '') {
        $db->where('profile_description', $profileDescription);
    } else {
        $db->group_start()->where('profile_description IS NULL', null, false)->or_where('profile_description', '')->group_end();
    }

    $row = $db->order_by('snapshot_month', 'DESC')->order_by('id', 'DESC')->limit(1)->get()->row_array();
    $profileKey = trim((string)($row['profile_key'] ?? ''));
    return $profileKey !== '' ? $profileKey : null;
};

$ready = $CI->materialfifomanager->ensureReady();
if (!($ready['ok'] ?? false)) {
    fwrite(STDERR, json_encode($ready, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

$mismatchItems = $db->query("
    SELECT
        i.id AS item_id,
        i.item_name,
        i.material_id,
        i.content_uom_id AS item_content_uom_id,
        m.content_uom_id AS material_content_uom_id,
        ui.code AS item_uom_code,
        um.code AS material_uom_code
    FROM mst_item i
    INNER JOIN mst_material m ON m.id = i.material_id
    LEFT JOIN mst_uom ui ON ui.id = i.content_uom_id
    LEFT JOIN mst_uom um ON um.id = m.content_uom_id
    WHERE COALESCE(i.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
    ORDER BY i.id ASC
")->result_array();

foreach ($mismatchItems as $row) {
    $itemId = (int)($row['item_id'] ?? 0);
    $materialUomId = (int)($row['material_content_uom_id'] ?? 0);
    if ($itemId <= 0 || $materialUomId <= 0) {
        continue;
    }
    $db->where('id', $itemId)->update('mst_item', ['content_uom_id' => $materialUomId]);
    if ((int)($db->error()['code'] ?? 0) !== 0) {
        $summary['errors'][] = [
            'stage' => 'item_uom_update',
            'item_id' => $itemId,
            'message' => (string)($db->error()['message'] ?? 'unknown_error'),
        ];
        continue;
    }
    $summary['item_uom_fixed'][] = [
        'item_id' => $itemId,
        'item_name' => (string)($row['item_name'] ?? ''),
        'material_id' => (int)($row['material_id'] ?? 0),
        'from_uom' => (string)($row['item_uom_code'] ?? ''),
        'to_uom' => (string)($row['material_uom_code'] ?? ''),
    ];
}

$openingMismatchRows = $db->query("
    SELECT
        s.*,
        m.content_uom_id AS master_content_uom_id
    FROM inv_warehouse_stock_opening_snapshot s
    INNER JOIN mst_material m ON m.id = s.material_id
    WHERE COALESCE(s.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
    ORDER BY s.snapshot_month ASC, s.id ASC
")->result_array();

foreach ($openingMismatchRows as $row) {
    $masterUomId = (int)($row['master_content_uom_id'] ?? 0);
    if ($masterUomId <= 0) {
        continue;
    }
    $payload = [
        'stock_scope' => 'WAREHOUSE',
        'snapshot_month' => (string)($row['snapshot_month'] ?? ''),
        'item_id' => (int)($row['item_id'] ?? 0),
        'material_id' => (int)($row['material_id'] ?? 0),
        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
        'content_uom_id' => $masterUomId,
        'profile_name' => $row['profile_name'] ?? null,
        'profile_brand' => $row['profile_brand'] ?? null,
        'profile_description' => $row['profile_description'] ?? null,
        'profile_expired_date' => $row['profile_expired_date'] ?? null,
        'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 1),
        'opening_qty_buy' => (float)($row['opening_qty_buy'] ?? 0),
        'opening_qty_content' => (float)($row['opening_qty_content'] ?? 0),
        'opening_avg_cost_per_content' => (float)($row['opening_avg_cost_per_content'] ?? 0),
        'replace_mode' => 1,
        'notes' => trim((string)($row['notes'] ?? '')) . ' | Auto repair warehouse content_uom from mst_material ' . date('Y-m-d H:i:s'),
    ];

    $result = $CI->Purchase_model->store_warehouse_opening_and_post($payload, 0, 'CLI_WAREHOUSE_UOM_REPAIR');
    if (!($result['ok'] ?? false)) {
        $summary['errors'][] = [
            'stage' => 'opening_repost',
            'snapshot_id' => (int)($row['id'] ?? 0),
            'item_id' => (int)($row['item_id'] ?? 0),
            'material_id' => (int)($row['material_id'] ?? 0),
            'message' => (string)($result['message'] ?? 'repost_failed'),
        ];
        continue;
    }

    $summary['opening_reposted'][] = [
        'old_snapshot_id' => (int)($row['id'] ?? 0),
        'snapshot_month' => (string)($row['snapshot_month'] ?? ''),
        'item_id' => (int)($row['item_id'] ?? 0),
        'material_id' => (int)($row['material_id'] ?? 0),
        'new_profile_key' => (string)($result['data']['profile_key'] ?? ''),
        'movement_no' => (string)($result['data']['movement_no'] ?? ''),
        'target_qty_content' => (float)($result['data']['target_qty_content'] ?? 0),
    ];
}

$draftLines = $db->query("
    SELECT
        l.id,
        l.adjustment_id,
        h.adjustment_no,
        l.line_no,
        l.item_id,
        l.material_id,
        l.buy_uom_id,
        l.content_uom_id,
        l.profile_name,
        l.profile_brand,
        l.profile_description,
        l.profile_content_per_buy,
        m.content_uom_id AS master_content_uom_id
    FROM inv_stock_adjustment_line l
    INNER JOIN inv_stock_adjustment h ON h.id = l.adjustment_id
    INNER JOIN mst_material m ON m.id = l.material_id
    WHERE h.stock_scope = 'WAREHOUSE'
      AND h.status = 'DRAFT'
      AND COALESCE(l.content_uom_id, 0) <> COALESCE(m.content_uom_id, 0)
    ORDER BY h.adjustment_no ASC, l.line_no ASC
")->result_array();

foreach ($draftLines as $line) {
    $masterUomId = (int)($line['master_content_uom_id'] ?? 0);
    if ($masterUomId <= 0) {
        continue;
    }

    $newProfileKey = $fetchCanonicalProfileKey([
        'item_id' => (int)($line['item_id'] ?? 0),
        'material_id' => (int)($line['material_id'] ?? 0),
        'buy_uom_id' => (int)($line['buy_uom_id'] ?? 0),
        'content_uom_id' => $masterUomId,
        'profile_name' => $line['profile_name'] ?? null,
        'profile_brand' => $line['profile_brand'] ?? null,
        'profile_description' => $line['profile_description'] ?? null,
    ]);

    $update = [
        'content_uom_id' => $masterUomId,
        'profile_content_uom_code' => $resolveUomCode($masterUomId),
    ];
    if ($newProfileKey !== null) {
        $update['profile_key'] = $newProfileKey;
    }

    $db->where('id', (int)$line['id'])->update('inv_stock_adjustment_line', $update);
    if ((int)($db->error()['code'] ?? 0) !== 0) {
        $summary['errors'][] = [
            'stage' => 'draft_adjustment_line_update',
            'line_id' => (int)($line['id'] ?? 0),
            'message' => (string)($db->error()['message'] ?? 'unknown_error'),
        ];
        continue;
    }

    $summary['draft_adjustment_lines_fixed'][] = [
        'line_id' => (int)($line['id'] ?? 0),
        'adjustment_no' => (string)($line['adjustment_no'] ?? ''),
        'line_no' => (int)($line['line_no'] ?? 0),
        'material_id' => (int)($line['material_id'] ?? 0),
        'new_content_uom_id' => $masterUomId,
        'new_profile_key' => $newProfileKey,
    ];
}

$missingLotRows = $db->query("
    SELECT s.*
    FROM inv_warehouse_stock_opening_snapshot s
    LEFT JOIN inv_material_fifo_lot l
      ON l.source_table = 'inv_warehouse_stock_opening_snapshot'
     AND l.source_id = s.id
    WHERE ROUND(COALESCE(s.opening_qty_content, 0), 4) > 0.0000
      AND l.id IS NULL
    ORDER BY s.snapshot_month ASC, s.id ASC
")->result_array();

foreach ($missingLotRows as $row) {
    $snapshotId = (int)($row['id'] ?? 0);
    if ($snapshotId <= 0) {
        continue;
    }

    $rollback = $CI->materialfifomanager->rollbackReceiptInboundLotsBySource('inv_warehouse_stock_opening_snapshot', $snapshotId, null);
    if (!($rollback['ok'] ?? false)) {
        $summary['errors'][] = [
            'stage' => 'lot_backfill_rollback',
            'snapshot_id' => $snapshotId,
            'message' => (string)($rollback['message'] ?? 'rollback_failed'),
        ];
        continue;
    }

    $register = $CI->materialfifomanager->registerReceiptInboundLot([
        'location_scope' => 'WAREHOUSE',
        'division_id' => null,
        'destination_type' => 'GUDANG',
        'receipt_date' => (string)($row['snapshot_month'] ?? date('Y-m-01')),
        'movement_date' => (string)($row['snapshot_month'] ?? date('Y-m-01')),
        'item_id' => !empty($row['item_id']) ? (int)$row['item_id'] : null,
        'material_id' => !empty($row['material_id']) ? (int)$row['material_id'] : null,
        'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
        'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : null,
        'profile_key' => $row['profile_key'] ?? null,
        'profile_expired_date' => $row['profile_expired_date'] ?? null,
        'qty_content_in' => (float)($row['opening_qty_content'] ?? 0),
        'unit_cost' => (float)($row['opening_avg_cost_per_content'] ?? 0),
        'source_table' => 'inv_warehouse_stock_opening_snapshot',
        'source_id' => $snapshotId,
        'source_line_id' => null,
    ]);
    if (!($register['ok'] ?? false)) {
        $summary['errors'][] = [
            'stage' => 'lot_backfill_register',
            'snapshot_id' => $snapshotId,
            'message' => (string)($register['message'] ?? 'register_failed'),
        ];
        continue;
    }

    $summary['warehouse_opening_lots_backfilled'][] = [
        'snapshot_id' => $snapshotId,
        'item_id' => (int)($row['item_id'] ?? 0),
        'material_id' => (int)($row['material_id'] ?? 0),
        'lot_id' => (int)($register['data']['lot_id'] ?? 0),
        'lot_no' => (string)($register['data']['lot_no'] ?? ''),
        'qty_content' => (float)($row['opening_qty_content'] ?? 0),
    ];
}

$adjRow = $db->select('id, adjustment_no, created_by, status')
    ->from('inv_stock_adjustment')
    ->where('adjustment_no', 'IAW20260630-8963')
    ->limit(1)
    ->get()
    ->row_array();

if ($adjRow) {
    $post = $CI->Purchase_model->post_stock_adjustment((int)$adjRow['id'], (int)($adjRow['created_by'] ?? 0), 'CLI_WAREHOUSE_UOM_REPAIR');
    $summary['adjustment_post_result'] = [
        'adjustment_no' => (string)($adjRow['adjustment_no'] ?? ''),
        'status_before' => (string)($adjRow['status'] ?? ''),
        'result' => $post,
    ];
    if (!($post['ok'] ?? false)) {
        $summary['errors'][] = [
            'stage' => 'post_adjustment',
            'adjustment_no' => (string)($adjRow['adjustment_no'] ?? ''),
            'message' => (string)($post['message'] ?? 'post_failed'),
        ];
    }
}

$output = [
    'ok' => empty($summary['errors']),
    'summary' => [
        'item_uom_fixed_count' => count($summary['item_uom_fixed']),
        'opening_reposted_count' => count($summary['opening_reposted']),
        'draft_adjustment_lines_fixed_count' => count($summary['draft_adjustment_lines_fixed']),
        'warehouse_opening_lots_backfilled_count' => count($summary['warehouse_opening_lots_backfilled']),
        'errors_count' => count($summary['errors']),
    ],
    'details' => $summary,
];

fwrite(STDOUT, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
exit(empty($summary['errors']) ? 0 : 1);
