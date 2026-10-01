SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16a_repair_division_monthly_opening_from_snapshot_mass.sql
-- Tujuan :
-- 1) Repair massal profil bahan baku divisi yang gap daily-nya
--    hanya karena opening monthly bulan berjalan masih 0 / stale
-- 2) Menyamakan opening_qty_buy / opening_qty_content monthly
--    dengan opening snapshot yang sudah ada
-- 3) HANYA menyentuh kasus yang setelah opening direstore,
--    gap movement harian langsung menjadi 0
--
-- Aman untuk dijalankan karena:
-- - tidak menyentuh movement log
-- - tidak menyentuh closing monthly
-- - tidak menyentuh kasus yang masih punya sisa gap setelah simulasi
-- ============================================================

START TRANSACTION;

SET @target_month := '2026-06-01';
SET @repair_tag := 'Mass restore monthly opening from opening snapshot 2026-06-16';

DROP TEMPORARY TABLE IF EXISTS tmp_mass_opening_restore_candidates;
CREATE TEMPORARY TABLE tmp_mass_opening_restore_candidates AS
SELECT
  s.id,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  ROUND(COALESCE(s.opening_qty_buy, 0), 4) AS current_opening_qty_buy,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS current_opening_qty_content,
  ROUND(COALESCE(os.opening_qty_buy, 0), 4) AS snapshot_opening_qty_buy,
  ROUND(COALESCE(os.opening_qty_content, 0), 4) AS snapshot_opening_qty_content,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS closing_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS current_log_gap,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS repaired_log_gap
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
        ELSE COALESCE(qty_content_delta, 0)
      END
    ), 4) AS net_non_opening_delta
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
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
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) > 0.0001
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) <= 0.0001
  AND (
    ABS(COALESCE(s.opening_qty_buy, 0) - COALESCE(os.opening_qty_buy, 0)) > 0.0001
    OR ABS(COALESCE(s.opening_qty_content, 0) - COALESCE(os.opening_qty_content, 0)) > 0.0001
  );

ALTER TABLE tmp_mass_opening_restore_candidates
  ADD PRIMARY KEY (id),
  ADD KEY idx_mass_opening_restore_scope (division_id, destination_type, material_id, item_id);

DROP TEMPORARY TABLE IF EXISTS tmp_mass_opening_restore_backup;
CREATE TEMPORARY TABLE tmp_mass_opening_restore_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_mass_opening_restore_candidates c ON c.id = s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_mass_opening_restore_candidates c ON c.id = s.id
SET
  s.opening_qty_buy = c.snapshot_opening_qty_buy,
  s.opening_qty_content = c.snapshot_opening_qty_content,
  s.opening_total_value = ROUND(c.snapshot_opening_qty_content * COALESCE(s.avg_cost_per_content, 0), 2),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'mass_opening_restore_candidates' AS metric, COUNT(*) AS total
FROM tmp_mass_opening_restore_candidates

UNION ALL

SELECT 'mass_opening_restore_rows_updated', COUNT(*)
FROM tmp_mass_opening_restore_backup;

SELECT
  c.division_id,
  c.destination_type,
  c.material_id,
  m.material_code,
  m.material_name,
  c.item_id,
  i.item_code,
  i.item_name,
  c.profile_key,
  c.current_opening_qty_content,
  c.snapshot_opening_qty_content,
  c.current_log_gap,
  c.repaired_log_gap
FROM tmp_mass_opening_restore_candidates c
LEFT JOIN mst_material m ON m.id = c.material_id
LEFT JOIN mst_item i ON i.id = c.item_id
ORDER BY ABS(c.current_log_gap) DESC, c.material_id, c.item_id, c.profile_key;
