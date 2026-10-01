<?php
declare(strict_types=1);

/**
 * Rebuild component daily rollup directly from movement logs.
 *
 * Usage:
 *   php tools/rebuild_component_daily_rollup.php
 *   php tools/rebuild_component_daily_rollup.php --apply
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

$logs = [];
$sql = "SELECT * FROM inv_component_movement_log ORDER BY movement_date ASC, id ASC";
$result = $mysqli->query($sql);
if (!$result) {
    fwrite(STDERR, 'Load movement log failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

if (empty($logs)) {
    echo json_encode(['ok' => true, 'seeded' => false, 'rows' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$balances = [];
$daily = [];
$batchNo = 'AUTO-' . date('YmdHis');

foreach ($logs as $log) {
    $componentId = (int)($log['component_id'] ?? 0);
    $uomId = (int)($log['uom_id'] ?? 0);
    if ($componentId <= 0 || $uomId <= 0) {
        continue;
    }

    $locationType = strtoupper(trim((string)($log['location_type'] ?? '')));
    $divisionId = isset($log['division_id']) && $log['division_id'] !== null ? (int)$log['division_id'] : null;
    $movementDate = (string)($log['movement_date'] ?? '');
    $movementDateTime = (string)($log['movement_datetime'] ?? '');
    $movementType = strtoupper(trim((string)($log['movement_type'] ?? '')));
    $sourceTable = (string)($log['source_table'] ?? '');
    $qtyIn = round((float)($log['qty_in'] ?? 0), 4);
    $qtyOut = round((float)($log['qty_out'] ?? 0), 4);
    $unitCost = round((float)($log['unit_cost'] ?? 0), 6);

    $identityKey = implode('|', [
        $locationType,
        $divisionId !== null ? (string)$divisionId : 'NULL',
        (string)$componentId,
        (string)$uomId,
    ]);
    $balance = $balances[$identityKey] ?? ['qty' => 0.0, 'avg' => 0.0, 'value' => 0.0];

    $qtyBefore = round((float)$balance['qty'], 4);
    $avgBefore = round((float)$balance['avg'], 6);
    $valueBefore = round((float)$balance['value'], 2);
    $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
    if (abs($qtyAfter) < 0.0001) {
        $qtyAfter = 0.0;
    }
    if ($qtyAfter < 0) {
        $qtyAfter = 0.0;
    }

    if ($qtyIn > 0) {
        $valueAfter = round($valueBefore + round($qtyIn * $unitCost, 2), 2);
        $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
    } else {
        $valueOut = round($qtyOut * $avgBefore, 2);
        $valueAfter = round(max(0, $valueBefore - $valueOut), 2);
        $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
    }

    $dayKey = implode('|', [
        $movementDate,
        $locationType,
        $divisionId !== null ? (string)$divisionId : 'NULL',
        (string)$componentId,
        (string)$uomId,
    ]);

    if (!isset($daily[$dayKey])) {
        $daily[$dayKey] = [
            'month_key' => date('Y-m-01', strtotime($movementDate)),
            'movement_date' => $movementDate,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'opening_qty' => $movementType === 'OPENING' ? $qtyAfter : $qtyBefore,
            'in_qty' => 0.0,
            'out_qty' => 0.0,
            'waste_qty' => 0.0,
            'spoil_qty' => 0.0,
            'adjustment_qty' => 0.0,
            'closing_qty' => $qtyAfter,
            'avg_cost' => $avgAfter,
            'total_value' => $valueAfter,
            'mutation_count' => 0,
            'last_movement_at' => $movementDateTime,
            'rebuild_batch_no' => $batchNo,
            '_opening_snapshot_delta' => $movementType === 'OPENING' && $sourceTable === 'inv_component_opening' ? $qtyIn : 0.0,
            '_has_non_snapshot_activity' => false,
        ];
    } elseif ($movementType === 'OPENING') {
        $daily[$dayKey]['opening_qty'] = round((float)$daily[$dayKey]['opening_qty'] + $qtyIn, 4);
        if ($sourceTable === 'inv_component_opening') {
            $daily[$dayKey]['_opening_snapshot_delta'] = round((float)$daily[$dayKey]['_opening_snapshot_delta'] + $qtyIn, 4);
        }
    }

    $isOpeningSnapshotReverse = $sourceTable === 'inv_component_opening' && $qtyOut > 0;
    $handledAsOpeningSnapshotReverse = false;
    if ($isOpeningSnapshotReverse) {
        $daily[$dayKey]['opening_qty'] = round((float)$daily[$dayKey]['opening_qty'] - $qtyOut, 4);
        if (abs((float)$daily[$dayKey]['opening_qty']) < 0.0001) {
            $daily[$dayKey]['opening_qty'] = 0.0;
        }
        $daily[$dayKey]['_opening_snapshot_delta'] = round((float)$daily[$dayKey]['_opening_snapshot_delta'] - $qtyOut, 4);
        $handledAsOpeningSnapshotReverse = true;
    }

    switch ($movementType) {
        case 'PRODUCTION_IN':
        case 'TRANSFER_IN':
            $daily[$dayKey]['in_qty'] += $qtyIn;
            $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            break;
        case 'PRODUCTION_OUT':
        case 'TRANSFER_OUT':
            $daily[$dayKey]['out_qty'] += $qtyOut;
            $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            break;
        case 'WASTE':
            $daily[$dayKey]['waste_qty'] += $qtyOut;
            $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            break;
        case 'SPOIL':
            $daily[$dayKey]['spoil_qty'] += $qtyOut;
            $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            break;
        case 'ADJUSTMENT_PLUS':
        case 'VOID_REVERSE':
            $daily[$dayKey]['adjustment_qty'] += $qtyIn;
            $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            break;
        case 'ADJUSTMENT_MINUS':
        case 'VOID_OUT':
            if (!$handledAsOpeningSnapshotReverse) {
                $daily[$dayKey]['adjustment_qty'] -= $qtyOut;
                $daily[$dayKey]['_has_non_snapshot_activity'] = true;
            }
            break;
    }

    $daily[$dayKey]['closing_qty'] = $qtyAfter;
    $daily[$dayKey]['avg_cost'] = $avgAfter;
    $daily[$dayKey]['total_value'] = $valueAfter;
    $daily[$dayKey]['mutation_count'] = (int)$daily[$dayKey]['mutation_count'] + 1;
    $daily[$dayKey]['last_movement_at'] = $movementDateTime;

    $balances[$identityKey] = [
        'qty' => $qtyAfter,
        'avg' => $avgAfter,
        'value' => $valueAfter,
    ];
}

foreach ($daily as $dayKey => $row) {
    $openingSnapshotDelta = round((float)($row['_opening_snapshot_delta'] ?? 0), 4);
    $hasNonSnapshotActivity = !empty($row['_has_non_snapshot_activity']);
    unset($row['_opening_snapshot_delta'], $row['_has_non_snapshot_activity']);

    if (
        !$hasNonSnapshotActivity
        && abs($openingSnapshotDelta) < 0.0001
        && abs((float)($row['opening_qty'] ?? 0)) < 0.0001
        && abs((float)($row['in_qty'] ?? 0)) < 0.0001
        && abs((float)($row['out_qty'] ?? 0)) < 0.0001
        && abs((float)($row['adjustment_qty'] ?? 0)) < 0.0001
        && abs((float)($row['waste_qty'] ?? 0)) < 0.0001
        && abs((float)($row['spoil_qty'] ?? 0)) < 0.0001
        && abs((float)($row['closing_qty'] ?? 0)) < 0.0001
    ) {
        unset($daily[$dayKey]);
        continue;
    }

    $daily[$dayKey] = $row;
}

$summary = [
    'ok' => true,
    'mode' => $apply ? 'apply' : 'dry-run',
    'rows' => count($daily),
];

if (!$apply) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$insert = $mysqli->prepare(
    'INSERT INTO inv_component_daily_rollup
    (month_key, movement_date, location_type, division_id, component_id, uom_id, opening_qty, in_qty, out_qty, waste_qty, spoil_qty, adjustment_qty, closing_qty, avg_cost, total_value, mutation_count, last_movement_at, rebuild_batch_no)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$insert) {
    fwrite(STDERR, 'Prepare insert failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

$mysqli->begin_transaction();
if (!$mysqli->query('TRUNCATE TABLE inv_component_daily_rollup')) {
    $mysqli->rollback();
    fwrite(STDERR, 'Truncate rollup failed: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

foreach (array_values($daily) as $row) {
    $monthKey = (string)$row['month_key'];
    $movementDate = (string)$row['movement_date'];
    $locationType = (string)$row['location_type'];
    $divisionId = isset($row['division_id']) ? (int)$row['division_id'] : null;
    $componentId = (int)$row['component_id'];
    $uomId = (int)$row['uom_id'];
    $openingQty = round((float)$row['opening_qty'], 4);
    $inQty = round((float)$row['in_qty'], 4);
    $outQty = round((float)$row['out_qty'], 4);
    $wasteQty = round((float)$row['waste_qty'], 4);
    $spoilQty = round((float)$row['spoil_qty'], 4);
    $adjustmentQty = round((float)$row['adjustment_qty'], 4);
    $closingQty = round((float)$row['closing_qty'], 4);
    $avgCost = round((float)$row['avg_cost'], 6);
    $totalValue = round((float)$row['total_value'], 2);
    $mutationCount = (int)$row['mutation_count'];
    $lastMovementAt = (string)$row['last_movement_at'];
    $rebuildBatchNo = (string)$row['rebuild_batch_no'];

    $ok = $insert->bind_param(
        'sssiiiddddddddiss',
        $monthKey,
        $movementDate,
        $locationType,
        $divisionId,
        $componentId,
        $uomId,
        $openingQty,
        $inQty,
        $outQty,
        $wasteQty,
        $spoilQty,
        $adjustmentQty,
        $closingQty,
        $avgCost,
        $totalValue,
        $mutationCount,
        $lastMovementAt,
        $rebuildBatchNo
    );
    if (!$ok || !$insert->execute()) {
        $mysqli->rollback();
        fwrite(STDERR, 'Insert rollup failed: ' . $insert->error . PHP_EOL);
        exit(1);
    }
}

$mysqli->commit();
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;