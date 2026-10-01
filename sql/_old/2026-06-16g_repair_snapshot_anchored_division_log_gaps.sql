SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16g_repair_snapshot_anchored_division_log_gaps.sql
-- Tujuan :
-- 1) Memperbaiki gap movement log profil bahan baku divisi yang
--    ternyata hanya karena opening monthly bulan berjalan = 0
--    padahal opening snapshot tersedia dan valid
-- 2) HANYA menyentuh kasus yang exact-foot:
--      closing_monthly = opening_snapshot + delta_movement_non_opening
-- 3) Tidak menyentuh kasus yang masih perlu review histori movement
-- ============================================================

START TRANSACTION;

SET @target_month := '2026-06-01';
SET @repair_tag := 'Repair snapshot-anchored division log gaps 2026-06-16';

DROP TEMPORARY TABLE IF EXISTS tmp_snapshot_gap_candidates;
CREATE TEMPORARY TABLE tmp_snapshot_gap_candidates AS
SELECT
  s.id AS monthly_id,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  COALESCE(s.profile_name, '') AS profile_name,
  ROUND(COALESCE(s.opening_qty_buy, 0), 4) AS monthly_opening_qty_buy,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS monthly_opening_qty_content,
  ROUND(COALESCE(s.closing_qty_buy, 0), 4) AS monthly_closing_qty_buy,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS monthly_closing_qty_content,
  ROUND(COALESCE(os.opening_qty_buy, 0), 4) AS snapshot_opening_qty_buy,
  ROUND(COALESCE(os.opening_qty_content, 0), 4) AS snapshot_opening_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta_buy, 0), 4) AS net_non_opening_delta_buy,
  ROUND(COALESCE(mv.net_non_opening_delta_content, 0), 4) AS net_non_opening_delta_content,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0)), 4) AS gap_from_monthly_opening,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0)), 4) AS gap_from_snapshot_opening
FROM inv_division_monthly_stock s
JOIN inv_division_stock_opening_snapshot os
  ON os.snapshot_month = @target_month
 AND os.division_id = s.division_id
 AND COALESCE(os.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND os.item_id = s.item_id
 AND COALESCE(os.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(os.profile_key, '') = COALESCE(s.profile_key, '')
LEFT JOIN (
  SELECT
    division_id,
    COALESCE(destination_type, 'OTHER') AS destination_type,
    item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(
      CASE
        WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 0
        ELSE COALESCE(qty_buy_delta, 0)
      END
    ), 4) AS net_non_opening_delta_buy,
    ROUND(SUM(
      CASE
        WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 0
        ELSE COALESCE(qty_content_delta, 0)
      END
    ), 4) AS net_non_opening_delta_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND movement_date >= @target_month
    AND movement_date < DATE_ADD(@target_month, INTERVAL 1 MONTH)
  GROUP BY
    division_id,
    COALESCE(destination_type, 'OTHER'),
    item_id,
    COALESCE(material_id, 0),
    COALESCE(profile_key, '')
) mv
  ON mv.division_id = s.division_id
 AND mv.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND mv.item_id = s.item_id
 AND mv.material_id = COALESCE(s.material_id, 0)
 AND mv.profile_key = COALESCE(s.profile_key, '')
WHERE s.month_key = @target_month
  AND COALESCE(s.material_id, 0) > 0
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) > 0.0001
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) <= 0.0001;

ALTER TABLE tmp_snapshot_gap_candidates
  ADD PRIMARY KEY (monthly_id),
  ADD KEY idx_snapshot_gap_scope (division_id, destination_type, material_id, item_id),
  ADD KEY idx_snapshot_gap_profile (profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_snapshot_gap_candidates_backup;
CREATE TEMPORARY TABLE tmp_snapshot_gap_candidates_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_snapshot_gap_candidates t ON t.monthly_id = s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_snapshot_gap_candidates t ON t.monthly_id = s.id
SET
  s.opening_qty_buy = t.snapshot_opening_qty_buy,
  s.opening_qty_content = t.snapshot_opening_qty_content,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  'snapshot_gap_profiles_repaired' AS metric,
  COUNT(*) AS total
FROM tmp_snapshot_gap_candidates

UNION ALL

SELECT
  'remaining_snapshot_gap_profiles_after_update',
  COUNT(*)
FROM (
  SELECT s.id
  FROM inv_division_monthly_stock s
  JOIN tmp_snapshot_gap_candidates t ON t.monthly_id = s.id
  LEFT JOIN (
    SELECT
      division_id,
      COALESCE(destination_type, 'OTHER') AS destination_type,
      item_id,
      COALESCE(material_id, 0) AS material_id,
      COALESCE(profile_key, '') AS profile_key,
      ROUND(SUM(
        CASE
          WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 0
          ELSE COALESCE(qty_content_delta, 0)
        END
      ), 4) AS net_non_opening_delta_content
    FROM inv_stock_movement_log
    WHERE movement_scope = 'DIVISION'
      AND movement_date >= @target_month
      AND movement_date < DATE_ADD(@target_month, INTERVAL 1 MONTH)
    GROUP BY
      division_id,
      COALESCE(destination_type, 'OTHER'),
      item_id,
      COALESCE(material_id, 0),
      COALESCE(profile_key, '')
  ) mv
    ON mv.division_id = s.division_id
   AND mv.destination_type = COALESCE(s.destination_type, 'OTHER')
   AND mv.item_id = s.item_id
   AND mv.material_id = COALESCE(s.material_id, 0)
   AND mv.profile_key = COALESCE(s.profile_key, '')
  WHERE ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) > 0.0001
) x

UNION ALL

SELECT
  'profiles_touched_backup_rows',
  COUNT(*)
FROM tmp_snapshot_gap_candidates_backup;

SELECT
  t.division_id,
  COALESCE(d.code, CAST(t.division_id AS CHAR)) AS division_code,
  t.destination_type,
  t.material_id,
  m.material_code,
  m.material_name,
  t.item_id,
  i.item_code,
  i.item_name,
  t.profile_key,
  t.profile_name,
  t.monthly_opening_qty_content AS old_monthly_opening_content,
  t.snapshot_opening_qty_content AS new_opening_from_snapshot,
  t.net_non_opening_delta_content,
  t.monthly_closing_qty_content,
  t.gap_from_monthly_opening
FROM tmp_snapshot_gap_candidates t
LEFT JOIN mst_operational_division d ON d.id = t.division_id
LEFT JOIN mst_material m ON m.id = t.material_id
LEFT JOIN mst_item i ON i.id = t.item_id
ORDER BY ABS(t.gap_from_monthly_opening) DESC, t.material_id, t.item_id, t.profile_key;
