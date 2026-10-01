<?php
declare(strict_types=1);

/**
 * Shift opening snapshot effect in daily rollup from adjustment -> opening.
 *
 * Usage:
 *   php tools/backfill_opening_rollup_daystart.php
 *   php tools/backfill_opening_rollup_daystart.php --apply
 */

$opts = getopt('', ['apply', 'db-host::', 'db-user::', 'db-pass::', 'db-name::']);
$apply = array_key_exists('apply', $opts);
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

$openingRefList = "'inv_warehouse_stock_opening_snapshot','inv_division_stock_opening_snapshot'";

$sqlAgg = "
    SELECT
        l.movement_scope,
        l.movement_date,
        l.division_id,
        l.destination_type,
        CASE WHEN COALESCE(l.material_id, 0) > 0 THEN 'MATERIAL' ELSE 'ITEM' END AS stock_domain,
        l.item_id,
        l.material_id,
        l.buy_uom_id,
        l.content_uom_id,
        l.profile_key,
        SUM(COALESCE(l.qty_buy_delta, 0)) AS delta_buy,
        SUM(COALESCE(l.qty_content_delta, 0)) AS delta_content
    FROM inv_stock_movement_log l
    WHERE l.ref_table IN ({$openingRefList})
    GROUP BY
        l.movement_scope,
        l.movement_date,
        l.division_id,
        l.destination_type,
        CASE WHEN COALESCE(l.material_id, 0) > 0 THEN 'MATERIAL' ELSE 'ITEM' END,
        l.item_id,
        l.material_id,
        l.buy_uom_id,
        l.content_uom_id,
        l.profile_key
";

$rs = $mysqli->query($sqlAgg);
if (!$rs) {
    fwrite(STDERR, 'Aggregate query failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

$targets = [];
while ($row = $rs->fetch_assoc()) {
    $targets[] = $row;
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'movement_groups' => count($targets),
    'warehouse_rows_considered' => 0,
    'division_rows_considered' => 0,
    'warehouse_rows_updated' => 0,
    'division_rows_updated' => 0,
    'applied_shift_content_total' => 0.0,
    'applied_shift_buy_total' => 0.0,
    'errors' => [],
];

$selectWarehouse = $mysqli->prepare(
    'SELECT id, opening_qty_buy, opening_qty_content, adjustment_qty_buy, adjustment_qty_content
     FROM inv_warehouse_daily_rollup
     WHERE movement_date = ?
       AND stock_domain = ?
       AND item_id <=> ?
       AND material_id <=> ?
       AND buy_uom_id <=> ?
       AND content_uom_id = ?
       AND profile_key <=> ?
     LIMIT 1'
);
$updateWarehouse = $mysqli->prepare(
    'UPDATE inv_warehouse_daily_rollup
     SET opening_qty_buy = ?,
         opening_qty_content = ?,
         adjustment_qty_buy = ?,
         adjustment_qty_content = ?,
         updated_at = NOW()
     WHERE id = ?'
);

$selectDivision = $mysqli->prepare(
    'SELECT id, opening_qty_buy, opening_qty_content, adjustment_qty_buy, adjustment_qty_content
     FROM inv_division_daily_rollup
     WHERE movement_date = ?
       AND division_id = ?
       AND destination_type <=> ?
       AND stock_domain = ?
       AND item_id <=> ?
       AND material_id <=> ?
       AND buy_uom_id <=> ?
       AND content_uom_id = ?
       AND profile_key <=> ?
     LIMIT 1'
);
$updateDivision = $mysqli->prepare(
    'UPDATE inv_division_daily_rollup
     SET opening_qty_buy = ?,
         opening_qty_content = ?,
         adjustment_qty_buy = ?,
         adjustment_qty_content = ?,
         updated_at = NOW()
     WHERE id = ?'
);

if (!$selectWarehouse || !$updateWarehouse || !$selectDivision || !$updateDivision) {
    fwrite(STDERR, 'Prepare statement failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

if ($apply) {
    $mysqli->begin_transaction();
}

foreach ($targets as $t) {
    $scope = strtoupper((string)($t['movement_scope'] ?? ''));
    $movementDate = (string)($t['movement_date'] ?? '');
    $stockDomain = strtoupper((string)($t['stock_domain'] ?? 'ITEM'));
    $itemId = isset($t['item_id']) ? (int)$t['item_id'] : null;
    $materialId = isset($t['material_id']) ? (int)$t['material_id'] : null;
    $buyUomId = isset($t['buy_uom_id']) ? (int)$t['buy_uom_id'] : null;
    $contentUomId = isset($t['content_uom_id']) ? (int)$t['content_uom_id'] : 0;
    $profileKey = (string)($t['profile_key'] ?? '');
    $deltaBuy = round((float)($t['delta_buy'] ?? 0), 4);
    $deltaContent = round((float)($t['delta_content'] ?? 0), 4);

    if ($scope === 'WAREHOUSE') {
        $summary['warehouse_rows_considered']++;

        $selectWarehouse->bind_param('ssiiiis', $movementDate, $stockDomain, $itemId, $materialId, $buyUomId, $contentUomId, $profileKey);
        if (!$selectWarehouse->execute()) {
            $summary['errors'][] = 'select warehouse failed: ' . $selectWarehouse->error;
            continue;
        }
        $row = $selectWarehouse->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }

        $openingBuy = round((float)$row['opening_qty_buy'], 4);
        $openingContent = round((float)$row['opening_qty_content'], 4);
        $adjBuy = round((float)$row['adjustment_qty_buy'], 4);
        $adjContent = round((float)$row['adjustment_qty_content'], 4);

        $appliedBuy = 0.0;
        $appliedContent = 0.0;

        if ($deltaBuy > 0 && $adjBuy > 0) {
            $appliedBuy = min($deltaBuy, $adjBuy);
        } elseif ($deltaBuy < 0 && $adjBuy < 0) {
            $appliedBuy = max($deltaBuy, $adjBuy);
        }
        if ($deltaContent > 0 && $adjContent > 0) {
            $appliedContent = min($deltaContent, $adjContent);
        } elseif ($deltaContent < 0 && $adjContent < 0) {
            $appliedContent = max($deltaContent, $adjContent);
        }

        if (abs($appliedBuy) < 0.0001 && abs($appliedContent) < 0.0001) {
            continue;
        }

        $newOpeningBuy = round($openingBuy + $appliedBuy, 4);
        $newOpeningContent = round($openingContent + $appliedContent, 4);
        $newAdjBuy = round($adjBuy - $appliedBuy, 4);
        $newAdjContent = round($adjContent - $appliedContent, 4);

        if ($apply) {
            $id = (int)$row['id'];
            $updateWarehouse->bind_param('ddddi', $newOpeningBuy, $newOpeningContent, $newAdjBuy, $newAdjContent, $id);
            if (!$updateWarehouse->execute()) {
                $summary['errors'][] = 'update warehouse failed: ' . $updateWarehouse->error;
                continue;
            }
        }

        $summary['warehouse_rows_updated']++;
        $summary['applied_shift_buy_total'] = round($summary['applied_shift_buy_total'] + $appliedBuy, 4);
        $summary['applied_shift_content_total'] = round($summary['applied_shift_content_total'] + $appliedContent, 4);
        continue;
    }

    if ($scope === 'DIVISION') {
        $summary['division_rows_considered']++;
        $divisionId = isset($t['division_id']) ? (int)$t['division_id'] : 0;
        $destinationType = (string)($t['destination_type'] ?? '');

        $selectDivision->bind_param('sissiiiis', $movementDate, $divisionId, $destinationType, $stockDomain, $itemId, $materialId, $buyUomId, $contentUomId, $profileKey);
        if (!$selectDivision->execute()) {
            $summary['errors'][] = 'select division failed: ' . $selectDivision->error;
            continue;
        }
        $row = $selectDivision->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }

        $openingBuy = round((float)$row['opening_qty_buy'], 4);
        $openingContent = round((float)$row['opening_qty_content'], 4);
        $adjBuy = round((float)$row['adjustment_qty_buy'], 4);
        $adjContent = round((float)$row['adjustment_qty_content'], 4);

        $appliedBuy = 0.0;
        $appliedContent = 0.0;

        if ($deltaBuy > 0 && $adjBuy > 0) {
            $appliedBuy = min($deltaBuy, $adjBuy);
        } elseif ($deltaBuy < 0 && $adjBuy < 0) {
            $appliedBuy = max($deltaBuy, $adjBuy);
        }
        if ($deltaContent > 0 && $adjContent > 0) {
            $appliedContent = min($deltaContent, $adjContent);
        } elseif ($deltaContent < 0 && $adjContent < 0) {
            $appliedContent = max($deltaContent, $adjContent);
        }

        if (abs($appliedBuy) < 0.0001 && abs($appliedContent) < 0.0001) {
            continue;
        }

        $newOpeningBuy = round($openingBuy + $appliedBuy, 4);
        $newOpeningContent = round($openingContent + $appliedContent, 4);
        $newAdjBuy = round($adjBuy - $appliedBuy, 4);
        $newAdjContent = round($adjContent - $appliedContent, 4);

        if ($apply) {
            $id = (int)$row['id'];
            $updateDivision->bind_param('ddddi', $newOpeningBuy, $newOpeningContent, $newAdjBuy, $newAdjContent, $id);
            if (!$updateDivision->execute()) {
                $summary['errors'][] = 'update division failed: ' . $updateDivision->error;
                continue;
            }
        }

        $summary['division_rows_updated']++;
        $summary['applied_shift_buy_total'] = round($summary['applied_shift_buy_total'] + $appliedBuy, 4);
        $summary['applied_shift_content_total'] = round($summary['applied_shift_content_total'] + $appliedContent, 4);
    }
}

if ($apply) {
    if (!empty($summary['errors'])) {
        $mysqli->rollback();
        $summary['status'] = 'failed';
        $summary['message'] = 'Rollback because one or more updates failed.';
    } else {
        $mysqli->commit();
        $summary['status'] = 'ok';
    }
} else {
    $summary['status'] = 'ok';
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
