SET NAMES utf8mb4;

-- ============================================================
-- BALANCE REMAINING MISMATCH:
-- latest monthly vs cumulative movement
-- Tanggal: 2026-06-08
--
-- Strategi:
-- - tambahkan movement positif targeted agar cumulative movement
--   menyamai saldo latest monthly yang saat ini sudah dianggap
--   canonical untuk runtime
-- - tidak mengubah monthly / lot lagi
-- ============================================================

START TRANSACTION;

INSERT INTO inv_stock_movement_log (
  movement_no,
  movement_date,
  movement_scope,
  division_id,
  destination_type,
  movement_type,
  adjustment_category,
  adjustment_reason_code,
  ref_table,
  ref_id,
  receipt_id,
  receipt_line_id,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  qty_buy_delta,
  qty_content_delta,
  qty_buy_after,
  qty_content_after,
  profile_key,
  profile_name,
  profile_brand,
  profile_description,
  profile_expired_date,
  profile_content_per_buy,
  profile_buy_uom_code,
  profile_content_uom_code,
  unit_cost,
  notes,
  created_by,
  created_at
)
SELECT
  CONCAT('MV20260608C', LPAD(dms.id, 4, '0')) AS movement_no,
  '2026-06-08',
  'DIVISION',
  dms.division_id,
  dms.destination_type,
  'ADJUSTMENT_IN',
  'ADJUSTMENT_PLUS',
  'CLEANUP_MISMATCH_20260608',
  'cleanup_balance_2026_06_08',
  dms.id,
  NULL,
  NULL,
  dms.item_id,
  dms.material_id,
  dms.buy_uom_id,
  dms.content_uom_id,
  ROUND(dms.closing_qty_buy - COALESCE(mv.cumulative_buy, 0), 4),
  ROUND(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0), 4),
  dms.closing_qty_buy,
  dms.closing_qty_content,
  dms.profile_key,
  dms.profile_name,
  dms.profile_brand,
  dms.profile_description,
  dms.profile_expired_date,
  dms.profile_content_per_buy,
  dms.profile_buy_uom_code,
  dms.profile_content_uom_code,
  dms.avg_cost_per_content,
  CONCAT('Balance latest monthly vs cumulative movement 2026-06-08 | monthly_id=', dms.id),
  NULL,
  NOW()
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key = dms.identity_key
AND latest.max_month = dms.month_key
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    COALESCE(item_id, 0) AS item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(qty_buy_delta), 4) AS cumulative_buy,
    ROUND(SUM(qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY
    division_id,
    destination_type,
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0),
    content_uom_id,
    COALESCE(profile_key, '')
) mv ON
    mv.division_id = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id = COALESCE(dms.item_id, 0)
AND mv.material_id = COALESCE(dms.material_id, 0)
AND mv.buy_uom_id = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id = dms.content_uom_id
AND mv.profile_key = COALESCE(dms.profile_key, '')
WHERE dms.id IN (1811, 1813, 1803, 1777, 1731, 1753)
  AND ROUND(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0), 4) > 0.001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_stock_movement_log chk
    WHERE chk.ref_table = 'cleanup_balance_2026_06_08'
      AND chk.ref_id = dms.id
  );

SELECT ROW_COUNT() AS inserted_balance_rows;

SELECT
  COUNT(*) AS mismatch_rows_after
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key = dms.identity_key
AND latest.max_month = dms.month_key
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    COALESCE(item_id, 0) AS item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY
    division_id,
    destination_type,
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0),
    content_uom_id,
    COALESCE(profile_key, '')
) mv ON
    mv.division_id = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id = COALESCE(dms.item_id, 0)
AND mv.material_id = COALESCE(dms.material_id, 0)
AND mv.buy_uom_id = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id = dms.content_uom_id
AND mv.profile_key = COALESCE(dms.profile_key, '')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0)) > 0.001;

COMMIT;
