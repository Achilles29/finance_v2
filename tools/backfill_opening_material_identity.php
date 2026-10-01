<?php
declare(strict_types=1);

/**
 * Backfill opening rows that were incorrectly stored as ITEM + material_id NULL
 * for material-linked items.
 *
 * Usage:
 *   php tools/backfill_opening_material_identity.php             # dry-run
 *   php tools/backfill_opening_material_identity.php --apply     # apply in transaction
 *   php tools/backfill_opening_material_identity.php --apply --item-code=ITM-MAT-000173
 */

$opts = getopt('', ['apply', 'item-code::', 'db-host::', 'db-user::', 'db-pass::', 'db-name::']);
$apply = array_key_exists('apply', $opts);
$itemCode = isset($opts['item-code']) ? trim((string)$opts['item-code']) : '';
$dbHost = isset($opts['db-host']) ? (string)$opts['db-host'] : '127.0.0.1';
$dbUser = isset($opts['db-user']) ? (string)$opts['db-user'] : 'root';
$dbPass = isset($opts['db-pass']) ? (string)$opts['db-pass'] : '';
$dbName = isset($opts['db-name']) ? (string)$opts['db-name'] : 'db_finance';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, 'DB connection failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$itemClause = '';
if ($itemCode !== '') {
    $safeItem = $mysqli->real_escape_string($itemCode);
    $itemClause = " AND i.item_code = '{$safeItem}'";
}

$countSql = [
    'warehouse_opening_snapshot_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_warehouse_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          {$itemClause}
    ",
    'division_opening_snapshot_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_division_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          {$itemClause}
    ",
    'movement_opening_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_stock_movement_log l
        JOIN mst_item i ON i.id = l.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE l.ref_table IN ('inv_warehouse_stock_opening_snapshot', 'inv_division_stock_opening_snapshot')
          AND l.item_id IS NOT NULL
          AND l.material_id IS NULL
          {$itemClause}
    ",
    'warehouse_daily_opening_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_warehouse_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_warehouse_stock_opening_snapshot'
                  AND m.movement_scope = 'WAREHOUSE'
                  AND m.item_id = d.item_id
                  AND m.movement_date = d.movement_date
                  AND m.buy_uom_id <=> d.buy_uom_id
                  AND m.content_uom_id = d.content_uom_id
                  AND m.profile_key <=> d.profile_key
          )
          {$itemClause}
    ",
    'division_daily_opening_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_division_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_division_stock_opening_snapshot'
                  AND m.movement_scope = 'DIVISION'
                  AND m.division_id = d.division_id
                  AND m.destination_type <=> d.destination_type
                  AND m.item_id = d.item_id
                  AND m.movement_date = d.movement_date
                  AND m.buy_uom_id <=> d.buy_uom_id
                  AND m.content_uom_id = d.content_uom_id
                  AND m.profile_key <=> d.profile_key
          )
          {$itemClause}
    ",
    'division_balance_opening_candidates' => "
        SELECT COUNT(*) AS c
        FROM inv_division_stock_balance b
        JOIN mst_item i ON i.id = b.item_id AND IFNULL(i.material_id, 0) > 0
        WHERE b.item_id IS NOT NULL
          AND b.material_id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_division_stock_opening_snapshot'
                  AND m.movement_scope = 'DIVISION'
                  AND m.division_id = b.division_id
                  AND m.destination_type <=> b.destination_type
                  AND m.item_id = b.item_id
                  AND m.buy_uom_id <=> b.buy_uom_id
                  AND m.content_uom_id = b.content_uom_id
                  AND m.profile_key <=> b.profile_key
          )
          {$itemClause}
    ",
];

$conflictSql = [
    'warehouse_opening_snapshot_conflicts' => "
        SELECT COUNT(*) AS c
        FROM inv_warehouse_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        JOIN inv_warehouse_stock_opening_snapshot x
          ON x.snapshot_month = s.snapshot_month
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = s.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> s.buy_uom_id
         AND x.content_uom_id = s.content_uom_id
         AND x.profile_key <=> s.profile_key
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          {$itemClause}
    ",
    'division_opening_snapshot_conflicts' => "
        SELECT COUNT(*) AS c
        FROM inv_division_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        JOIN inv_division_stock_opening_snapshot x
          ON x.snapshot_month = s.snapshot_month
         AND x.division_id = s.division_id
         AND x.destination_type = s.destination_type
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = s.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> s.buy_uom_id
         AND x.content_uom_id = s.content_uom_id
         AND x.profile_key <=> s.profile_key
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          {$itemClause}
    ",
    'warehouse_daily_opening_conflicts' => "
        SELECT COUNT(*) AS c
        FROM inv_warehouse_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        JOIN inv_warehouse_daily_rollup x
          ON x.movement_date = d.movement_date
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = d.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> d.buy_uom_id
         AND x.content_uom_id = d.content_uom_id
         AND x.profile_key <=> d.profile_key
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          {$itemClause}
    ",
    'division_daily_opening_conflicts' => "
        SELECT COUNT(*) AS c
        FROM inv_division_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        JOIN inv_division_daily_rollup x
          ON x.movement_date = d.movement_date
         AND x.division_id = d.division_id
         AND x.destination_type = d.destination_type
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = d.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> d.buy_uom_id
         AND x.content_uom_id = d.content_uom_id
         AND x.profile_key <=> d.profile_key
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          {$itemClause}
    ",
    'division_balance_opening_conflicts' => "
        SELECT COUNT(*) AS c
        FROM inv_division_stock_balance b
        JOIN mst_item i ON i.id = b.item_id AND IFNULL(i.material_id, 0) > 0
        JOIN inv_division_stock_balance x
          ON x.division_id = b.division_id
         AND x.destination_type = b.destination_type
         AND x.item_id = b.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> b.buy_uom_id
         AND x.content_uom_id = b.content_uom_id
         AND x.profile_key <=> b.profile_key
        WHERE b.item_id IS NOT NULL
          AND b.material_id IS NULL
          {$itemClause}
    ",
];

$updateSql = [
    'warehouse_opening_snapshot_updated' => "
        UPDATE inv_warehouse_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        LEFT JOIN inv_warehouse_stock_opening_snapshot x
          ON x.snapshot_month = s.snapshot_month
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = s.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> s.buy_uom_id
         AND x.content_uom_id = s.content_uom_id
         AND x.profile_key <=> s.profile_key
        SET s.stock_domain = 'MATERIAL',
            s.material_id = i.material_id,
            s.updated_at = NOW()
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          AND x.id IS NULL
          {$itemClause}
    ",
    'division_opening_snapshot_updated' => "
        UPDATE inv_division_stock_opening_snapshot s
        JOIN mst_item i ON i.id = s.item_id AND IFNULL(i.material_id, 0) > 0
        LEFT JOIN inv_division_stock_opening_snapshot x
          ON x.snapshot_month = s.snapshot_month
         AND x.division_id = s.division_id
         AND x.destination_type = s.destination_type
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = s.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> s.buy_uom_id
         AND x.content_uom_id = s.content_uom_id
         AND x.profile_key <=> s.profile_key
        SET s.stock_domain = 'MATERIAL',
            s.material_id = i.material_id,
            s.updated_at = NOW()
        WHERE s.stock_domain = 'ITEM'
          AND s.material_id IS NULL
          AND x.id IS NULL
          {$itemClause}
    ",
    'movement_opening_updated' => "
        UPDATE inv_stock_movement_log l
        JOIN mst_item i ON i.id = l.item_id AND IFNULL(i.material_id, 0) > 0
        SET l.material_id = i.material_id
        WHERE l.ref_table IN ('inv_warehouse_stock_opening_snapshot', 'inv_division_stock_opening_snapshot')
          AND l.item_id IS NOT NULL
          AND l.material_id IS NULL
          {$itemClause}
    ",
    'warehouse_daily_opening_updated' => "
        UPDATE inv_warehouse_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        LEFT JOIN inv_warehouse_daily_rollup x
          ON x.movement_date = d.movement_date
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = d.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> d.buy_uom_id
         AND x.content_uom_id = d.content_uom_id
         AND x.profile_key <=> d.profile_key
        SET d.stock_domain = 'MATERIAL',
            d.material_id = i.material_id,
            d.updated_at = NOW()
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          AND x.id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_warehouse_stock_opening_snapshot'
                  AND m.movement_scope = 'WAREHOUSE'
                  AND m.item_id = d.item_id
                  AND m.movement_date = d.movement_date
                  AND m.buy_uom_id <=> d.buy_uom_id
                  AND m.content_uom_id = d.content_uom_id
                  AND m.profile_key <=> d.profile_key
          )
          {$itemClause}
    ",
    'division_daily_opening_updated' => "
        UPDATE inv_division_daily_rollup d
        JOIN mst_item i ON i.id = d.item_id AND IFNULL(i.material_id, 0) > 0
        LEFT JOIN inv_division_daily_rollup x
          ON x.movement_date = d.movement_date
         AND x.division_id = d.division_id
         AND x.destination_type = d.destination_type
         AND x.stock_domain = 'MATERIAL'
         AND x.item_id = d.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> d.buy_uom_id
         AND x.content_uom_id = d.content_uom_id
         AND x.profile_key <=> d.profile_key
        SET d.stock_domain = 'MATERIAL',
            d.material_id = i.material_id,
            d.updated_at = NOW()
        WHERE d.stock_domain = 'ITEM'
          AND d.material_id IS NULL
          AND x.id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_division_stock_opening_snapshot'
                  AND m.movement_scope = 'DIVISION'
                  AND m.division_id = d.division_id
                  AND m.destination_type <=> d.destination_type
                  AND m.item_id = d.item_id
                  AND m.movement_date = d.movement_date
                  AND m.buy_uom_id <=> d.buy_uom_id
                  AND m.content_uom_id = d.content_uom_id
                  AND m.profile_key <=> d.profile_key
          )
          {$itemClause}
    ",
    'division_balance_opening_updated' => "
        UPDATE inv_division_stock_balance b
        JOIN mst_item i ON i.id = b.item_id AND IFNULL(i.material_id, 0) > 0
        LEFT JOIN inv_division_stock_balance x
          ON x.division_id = b.division_id
         AND x.destination_type = b.destination_type
         AND x.item_id = b.item_id
         AND x.material_id = i.material_id
         AND x.buy_uom_id <=> b.buy_uom_id
         AND x.content_uom_id = b.content_uom_id
         AND x.profile_key <=> b.profile_key
        SET b.material_id = i.material_id,
            b.updated_at = NOW()
        WHERE b.item_id IS NOT NULL
          AND b.material_id IS NULL
          AND x.id IS NULL
          AND EXISTS (
                SELECT 1
                FROM inv_stock_movement_log m
                WHERE m.ref_table = 'inv_division_stock_opening_snapshot'
                  AND m.movement_scope = 'DIVISION'
                  AND m.division_id = b.division_id
                  AND m.destination_type <=> b.destination_type
                  AND m.item_id = b.item_id
                  AND m.buy_uom_id <=> b.buy_uom_id
                  AND m.content_uom_id = b.content_uom_id
                  AND m.profile_key <=> b.profile_key
          )
          {$itemClause}
    ",
];

$getCount = static function (mysqli $db, string $sql): int {
    $rs = $db->query($sql);
    if (!$rs) {
        throw new RuntimeException('Count query failed: ' . $db->error);
    }
    $row = $rs->fetch_assoc();
    return (int)($row['c'] ?? 0);
};

$before = [];
foreach ($countSql as $k => $sql) {
    $before[$k] = $getCount($mysqli, $sql);
}

$conflicts = [];
foreach ($conflictSql as $k => $sql) {
    $conflicts[$k] = $getCount($mysqli, $sql);
}

$updated = array_fill_keys(array_keys($updateSql), 0);
$error = null;

if ($apply) {
    $mysqli->begin_transaction();
    try {
        foreach ($updateSql as $k => $sql) {
            if (!$mysqli->query($sql)) {
                throw new RuntimeException("Update failed ({$k}): " . $mysqli->error);
            }
            $updated[$k] = $mysqli->affected_rows;
        }
        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        $error = $e->getMessage();
    }
}

$after = [];
foreach ($countSql as $k => $sql) {
    $after[$k] = $getCount($mysqli, $sql);
}

$result = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'filter_item_code' => $itemCode !== '' ? $itemCode : null,
    'before_candidates' => $before,
    'conflicts' => $conflicts,
    'updated_rows' => $updated,
    'after_candidates' => $after,
    'status' => $error === null ? 'ok' : 'failed',
    'error' => $error,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$mysqli->close();
if ($error !== null) {
    exit(1);
}
