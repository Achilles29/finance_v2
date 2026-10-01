<?php

declare(strict_types=1);

$mysqli = new mysqli('127.0.0.1', 'root', '', 'db_finance');
if ($mysqli->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

$mysqli->set_charset('utf8mb4');

$sqlMovement = <<<'SQL'
UPDATE inv_stock_movement_log m
JOIN pur_purchase_receipt r ON r.id = COALESCE(m.receipt_id, m.ref_id)
SET m.destination_type = r.destination_type
WHERE m.movement_scope = 'DIVISION'
  AND COALESCE(NULLIF(TRIM(m.destination_type), ''), 'OTHER') = 'OTHER'
  AND COALESCE(NULLIF(TRIM(r.destination_type), ''), '') <> ''
SQL;

$sqlRollup = <<<'SQL'
UPDATE inv_division_daily_rollup d
JOIN (
  SELECT
    movement_date,
    division_id,
    item_id,
    material_id,
    profile_key,
    MAX(destination_type) AS destination_type
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND COALESCE(NULLIF(TRIM(destination_type), ''), '') <> ''
  GROUP BY movement_date, division_id, item_id, material_id, profile_key
  HAVING COUNT(DISTINCT destination_type) = 1
) x
  ON d.movement_date = x.movement_date
 AND COALESCE(d.division_id, 0) = COALESCE(x.division_id, 0)
 AND COALESCE(d.item_id, 0) = COALESCE(x.item_id, 0)
 AND COALESCE(d.material_id, 0) = COALESCE(x.material_id, 0)
 AND COALESCE(d.profile_key, '') = COALESCE(x.profile_key, '')
SET d.destination_type = x.destination_type
WHERE COALESCE(NULLIF(TRIM(d.destination_type), ''), 'OTHER') = 'OTHER'
SQL;

$sqlBalanceDedupDelete = <<<'SQL'
DELETE s
FROM inv_division_stock_balance s
JOIN (
  SELECT
    division_id,
    item_id,
    material_id,
    profile_key,
    MAX(destination_type) AS destination_type
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND COALESCE(NULLIF(TRIM(destination_type), ''), '') <> ''
  GROUP BY division_id, item_id, material_id, profile_key
  HAVING COUNT(DISTINCT destination_type) = 1
) x
  ON COALESCE(x.division_id, 0) = COALESCE(s.division_id, 0)
 AND COALESCE(x.item_id, 0) = COALESCE(s.item_id, 0)
 AND COALESCE(x.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(x.profile_key, '') = COALESCE(s.profile_key, '')
JOIN inv_division_stock_balance t
  ON t.division_id = s.division_id
 AND t.destination_type = x.destination_type
 AND COALESCE(t.item_id, 0) = COALESCE(s.item_id, 0)
 AND COALESCE(t.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(t.buy_uom_id, 0) = COALESCE(s.buy_uom_id, 0)
 AND COALESCE(t.content_uom_id, 0) = COALESCE(s.content_uom_id, 0)
 AND COALESCE(t.profile_key, '') = COALESCE(s.profile_key, '')
WHERE COALESCE(NULLIF(TRIM(s.destination_type), ''), 'OTHER') = 'OTHER'
  AND s.qty_buy_balance = t.qty_buy_balance
  AND s.qty_content_balance = t.qty_content_balance
  AND s.avg_cost_per_content = t.avg_cost_per_content
SQL;

$sqlBalanceUpdateSafe = <<<'SQL'
UPDATE inv_division_stock_balance b
JOIN (
  SELECT
    division_id,
    item_id,
    material_id,
    profile_key,
    MAX(destination_type) AS destination_type
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND COALESCE(NULLIF(TRIM(destination_type), ''), '') <> ''
  GROUP BY division_id, item_id, material_id, profile_key
  HAVING COUNT(DISTINCT destination_type) = 1
) x
  ON COALESCE(x.division_id, 0) = COALESCE(b.division_id, 0)
 AND COALESCE(x.item_id, 0) = COALESCE(b.item_id, 0)
 AND COALESCE(x.material_id, 0) = COALESCE(b.material_id, 0)
 AND COALESCE(x.profile_key, '') = COALESCE(b.profile_key, '')
LEFT JOIN inv_division_stock_balance t
  ON t.division_id = b.division_id
 AND t.destination_type = x.destination_type
 AND COALESCE(t.item_id, 0) = COALESCE(b.item_id, 0)
 AND COALESCE(t.material_id, 0) = COALESCE(b.material_id, 0)
 AND COALESCE(t.buy_uom_id, 0) = COALESCE(b.buy_uom_id, 0)
 AND COALESCE(t.content_uom_id, 0) = COALESCE(b.content_uom_id, 0)
 AND COALESCE(t.profile_key, '') = COALESCE(b.profile_key, '')
SET b.destination_type = x.destination_type
WHERE COALESCE(NULLIF(TRIM(b.destination_type), ''), 'OTHER') = 'OTHER'
  AND t.id IS NULL
SQL;

$sqlBalanceFallbackFromReceipt = <<<'SQL'
UPDATE inv_division_stock_balance b
LEFT JOIN pur_purchase_receipt_line rl ON rl.id = b.last_receipt_line_id
LEFT JOIN pur_purchase_receipt r ON r.id = rl.purchase_receipt_id
SET b.destination_type = r.destination_type
WHERE COALESCE(NULLIF(TRIM(b.destination_type), ''), 'OTHER') = 'OTHER'
  AND COALESCE(NULLIF(TRIM(r.destination_type), ''), '') <> ''
  AND NOT EXISTS (
    SELECT 1
    FROM inv_division_stock_balance t
    WHERE t.division_id = b.division_id
      AND t.destination_type = r.destination_type
      AND COALESCE(t.item_id, 0) = COALESCE(b.item_id, 0)
      AND COALESCE(t.material_id, 0) = COALESCE(b.material_id, 0)
      AND COALESCE(t.buy_uom_id, 0) = COALESCE(b.buy_uom_id, 0)
      AND COALESCE(t.content_uom_id, 0) = COALESCE(b.content_uom_id, 0)
      AND COALESCE(t.profile_key, '') = COALESCE(b.profile_key, '')
  )
SQL;

$sqlRollupFallbackFromBalance = <<<'SQL'
UPDATE inv_division_daily_rollup d
JOIN inv_division_stock_balance b
  ON b.division_id = d.division_id
 AND COALESCE(b.item_id, 0) = COALESCE(d.item_id, 0)
 AND COALESCE(b.material_id, 0) = COALESCE(d.material_id, 0)
 AND COALESCE(b.buy_uom_id, 0) = COALESCE(d.buy_uom_id, 0)
 AND COALESCE(b.content_uom_id, 0) = COALESCE(d.content_uom_id, 0)
 AND COALESCE(b.profile_key, '') = COALESCE(d.profile_key, '')
SET d.destination_type = b.destination_type
WHERE COALESCE(NULLIF(TRIM(d.destination_type), ''), 'OTHER') = 'OTHER'
  AND COALESCE(NULLIF(TRIM(b.destination_type), ''), 'OTHER') <> 'OTHER'
SQL;

$summary = [
    'movement_log_updated' => 0,
    'daily_rollup_updated' => 0,
  'stock_balance_dedup_deleted' => 0,
  'stock_balance_updated' => 0,
  'stock_balance_fallback_updated' => 0,
  'daily_rollup_fallback_updated' => 0,
  'remaining_other_movement' => 0,
  'remaining_other_rollup' => 0,
  'remaining_other_balance' => 0,
];

try {
    $mysqli->begin_transaction();

    if (!$mysqli->query($sqlMovement)) {
        throw new RuntimeException('movement update failed: ' . $mysqli->error);
    }
    $summary['movement_log_updated'] = $mysqli->affected_rows;

    if (!$mysqli->query($sqlRollup)) {
        throw new RuntimeException('rollup update failed: ' . $mysqli->error);
    }
    $summary['daily_rollup_updated'] = $mysqli->affected_rows;

    if (!$mysqli->query($sqlBalanceDedupDelete)) {
      throw new RuntimeException('balance dedup delete failed: ' . $mysqli->error);
    }
    $summary['stock_balance_dedup_deleted'] = $mysqli->affected_rows;

    if (!$mysqli->query($sqlBalanceUpdateSafe)) {
      throw new RuntimeException('balance update failed: ' . $mysqli->error);
    }
    $summary['stock_balance_updated'] = $mysqli->affected_rows;

    if (!$mysqli->query($sqlBalanceFallbackFromReceipt)) {
      throw new RuntimeException('balance fallback update failed: ' . $mysqli->error);
    }
    $summary['stock_balance_fallback_updated'] = $mysqli->affected_rows;

    if (!$mysqli->query($sqlRollupFallbackFromBalance)) {
      throw new RuntimeException('rollup fallback update failed: ' . $mysqli->error);
    }
    $summary['daily_rollup_fallback_updated'] = $mysqli->affected_rows;

    $otherMovement = $mysqli->query("SELECT COUNT(*) AS c FROM inv_stock_movement_log WHERE movement_scope = 'DIVISION' AND COALESCE(NULLIF(TRIM(destination_type), ''), 'OTHER') = 'OTHER'");
    $otherRollup = $mysqli->query("SELECT COUNT(*) AS c FROM inv_division_daily_rollup WHERE COALESCE(NULLIF(TRIM(destination_type), ''), 'OTHER') = 'OTHER'");
    $otherBalance = $mysqli->query("SELECT COUNT(*) AS c FROM inv_division_stock_balance WHERE COALESCE(NULLIF(TRIM(destination_type), ''), 'OTHER') = 'OTHER'");
    if (!$otherMovement || !$otherRollup || !$otherBalance) {
      throw new RuntimeException('post-check failed: ' . $mysqli->error);
    }
    $summary['remaining_other_movement'] = (int)($otherMovement->fetch_assoc()['c'] ?? 0);
    $summary['remaining_other_rollup'] = (int)($otherRollup->fetch_assoc()['c'] ?? 0);
    $summary['remaining_other_balance'] = (int)($otherBalance->fetch_assoc()['c'] ?? 0);

    $mysqli->commit();
    echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    $mysqli->rollback();
    fwrite(STDERR, 'Repair failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
