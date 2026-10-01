SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-12c_repair_warehouse_same_uom_residuals.sql
-- Tujuan :
-- 1) Membersihkan residu same-UOM invalid yang masih tersisa di lane warehouse
-- 2) Menormalkan opening snapshot, monthly stock, dan movement warehouse
-- 3) Menyamakan qty_buy dengan qty_content untuk kasus:
--    buy_uom_id = content_uom_id tetapi profile_content_per_buy <> 1
--
-- Catatan:
-- - Script ini fokus ke warehouse residual.
-- - Master katalog aktif sudah dinormalisasi oleh 2026-06-11c.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair warehouse same-UOM residuals 2026-06-12';

DROP TEMPORARY TABLE IF EXISTS tmp_wh_same_uom_profiles;
CREATE TEMPORARY TABLE tmp_wh_same_uom_profiles AS
SELECT DISTINCT
  profile_key,
  MAX(item_id) AS item_id,
  MAX(material_id) AS material_id,
  MAX(buy_uom_id) AS buy_uom_id,
  MAX(content_uom_id) AS content_uom_id,
  ROUND(MAX(COALESCE(profile_content_per_buy, 1)), 6) AS old_cpb,
  MAX(COALESCE(NULLIF(profile_content_uom_code, ''), NULLIF(profile_buy_uom_code, ''))) AS target_uom_code
FROM (
  SELECT profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code
  FROM inv_warehouse_stock_opening_snapshot
  WHERE buy_uom_id IS NOT NULL
    AND content_uom_id IS NOT NULL
    AND buy_uom_id = content_uom_id
    AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
  UNION ALL
  SELECT profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code
  FROM inv_warehouse_monthly_stock
  WHERE buy_uom_id IS NOT NULL
    AND content_uom_id IS NOT NULL
    AND buy_uom_id = content_uom_id
    AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
  UNION ALL
  SELECT profile_key, item_id, material_id, buy_uom_id, content_uom_id, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code
  FROM inv_stock_movement_log
  WHERE movement_scope = 'WAREHOUSE'
    AND buy_uom_id IS NOT NULL
    AND content_uom_id IS NOT NULL
    AND buy_uom_id = content_uom_id
    AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
) z
GROUP BY profile_key;

ALTER TABLE tmp_wh_same_uom_profiles ADD PRIMARY KEY (profile_key);

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_wh_same_uom_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_wh_same_uom_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_stock_movement_log l
JOIN tmp_wh_same_uom_profiles p ON p.profile_key = l.profile_key
SET
  l.profile_content_per_buy = 1.000000,
  l.profile_buy_uom_code = COALESCE(p.target_uom_code, l.profile_buy_uom_code),
  l.profile_content_uom_code = COALESCE(p.target_uom_code, l.profile_content_uom_code),
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255)
WHERE l.movement_scope = 'WAREHOUSE'
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001;

COMMIT;

SELECT 'warehouse_profiles_repaired' AS metric, COUNT(*) AS total
FROM tmp_wh_same_uom_profiles
UNION ALL
SELECT 'warehouse_opening_invalid_remaining', COUNT(*)
FROM inv_warehouse_stock_opening_snapshot
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'warehouse_monthly_invalid_remaining', COUNT(*)
FROM inv_warehouse_monthly_stock
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'warehouse_movement_invalid_remaining', COUNT(*)
FROM inv_stock_movement_log
WHERE movement_scope = 'WAREHOUSE'
  AND buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001;
