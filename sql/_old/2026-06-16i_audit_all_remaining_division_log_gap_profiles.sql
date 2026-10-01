SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16i_audit_all_remaining_division_log_gap_profiles.sql
-- Tujuan :
-- 1) Mendaftar SEMUA profil bahan baku divisi yang masih punya
--    gap movement log untuk bulan target
-- 2) Mengelompokkan mana yang:
--    - bisa dipulihkan dari opening snapshot
--    - bisa di-seed dari closing bulan lalu
--    - tidak punya movement bulan berjalan
--    - masih perlu review histori movement
-- 3) Memberi konteks sibling item aktif per material agar kasus
--    legacy silang item bisa cepat terlihat
--
-- Catatan:
-- - Jalankan ini sebelum loncat ke audit sempit seperti 16h
-- - Jika setelah SQL 16g hasilnya tinggal 1, biasanya memang
--   tinggal kasus khusus seperti AIR MINERAL GALON
-- ============================================================

SET @target_month := '2026-06-01';
SET @next_month := DATE_ADD(@target_month, INTERVAL 1 MONTH);
SET @prev_month := DATE_SUB(@target_month, INTERVAL 1 MONTH);

DROP TEMPORARY TABLE IF EXISTS tmp_all_remaining_division_log_gaps;
CREATE TEMPORARY TABLE tmp_all_remaining_division_log_gaps AS
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
  ROUND(COALESCE(prev.closing_qty_buy, 0), 4) AS prev_closing_qty_buy,
  ROUND(COALESCE(prev.closing_qty_content, 0), 4) AS prev_closing_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta_buy, 0), 4) AS net_non_opening_delta_buy,
  ROUND(COALESCE(mv.net_non_opening_delta_content, 0), 4) AS net_non_opening_delta_content,
  COALESCE(mv.month_movement_rows, 0) AS month_movement_rows,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0)), 4) AS gap_from_monthly_opening,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0)), 4) AS gap_from_snapshot_opening,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(prev.closing_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0)), 4) AS gap_from_prev_month,
  COALESCE(sib.active_item_count, 0) AS active_item_count,
  CASE
    WHEN ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) <= 0.0001
      THEN 'RESTORE_OPENING_FROM_SNAPSHOT'
    WHEN ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(prev.closing_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) <= 0.0001
      THEN 'SEED_OPENING_FROM_PREV_MONTH_CLOSING'
    WHEN COALESCE(mv.month_movement_rows, 0) = 0
      THEN 'NO_MONTH_MOVEMENT_REVIEW_MONTHLY'
    WHEN COALESCE(sib.active_item_count, 0) > 1
      THEN 'REVIEW_MULTI_ACTIVE_ITEM_MATERIAL'
    ELSE 'REVIEW_MOVEMENT_HISTORY'
  END AS suggested_repair_path
FROM inv_division_monthly_stock s
LEFT JOIN inv_division_stock_opening_snapshot os
  ON os.snapshot_month = @target_month
 AND os.division_id = s.division_id
 AND COALESCE(os.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND os.item_id = s.item_id
 AND COALESCE(os.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(os.profile_key, '') = COALESCE(s.profile_key, '')
LEFT JOIN inv_division_monthly_stock prev
  ON prev.month_key = @prev_month
 AND prev.division_id = s.division_id
 AND COALESCE(prev.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND prev.item_id = s.item_id
 AND COALESCE(prev.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(prev.profile_key, '') = COALESCE(s.profile_key, '')
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
    ), 4) AS net_non_opening_delta_content,
    COUNT(*) AS month_movement_rows
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND movement_date >= @target_month
    AND movement_date < @next_month
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
LEFT JOIN (
  SELECT material_id, COUNT(*) AS active_item_count
  FROM mst_item
  WHERE COALESCE(material_id, 0) > 0
    AND COALESCE(is_active, 1) = 1
  GROUP BY material_id
) sib ON sib.material_id = COALESCE(s.material_id, 0)
WHERE s.month_key = @target_month
  AND COALESCE(s.material_id, 0) > 0
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta_content, 0))) > 0.0001;

ALTER TABLE tmp_all_remaining_division_log_gaps
  ADD PRIMARY KEY (monthly_id),
  ADD KEY idx_all_gap_path (suggested_repair_path, division_id, material_id),
  ADD KEY idx_all_gap_profile (profile_key(32));

-- ------------------------------------------------------------
-- A. Ringkasan bucket repair
-- ------------------------------------------------------------
SELECT
  suggested_repair_path,
  COUNT(*) AS total_profiles,
  ROUND(SUM(ABS(gap_from_monthly_opening)), 4) AS total_abs_gap
FROM tmp_all_remaining_division_log_gaps
GROUP BY suggested_repair_path
ORDER BY total_profiles DESC, suggested_repair_path;

-- ------------------------------------------------------------
-- B. Detail semua profil gap
-- ------------------------------------------------------------
SELECT
  g.division_id,
  COALESCE(d.code, CAST(g.division_id AS CHAR)) AS division_code,
  COALESCE(d.name, CAST(g.division_id AS CHAR)) AS division_name,
  g.destination_type,
  g.material_id,
  m.material_code,
  m.material_name,
  g.item_id,
  i.item_code,
  i.item_name,
  g.profile_key,
  g.profile_name,
  g.monthly_opening_qty_content,
  g.snapshot_opening_qty_content,
  g.prev_closing_qty_content,
  g.net_non_opening_delta_content,
  g.monthly_closing_qty_content,
  g.gap_from_monthly_opening,
  g.active_item_count,
  g.suggested_repair_path
FROM tmp_all_remaining_division_log_gaps g
LEFT JOIN mst_operational_division d ON d.id = g.division_id
LEFT JOIN mst_material m ON m.id = g.material_id
LEFT JOIN mst_item i ON i.id = g.item_id
ORDER BY
  CASE g.suggested_repair_path
    WHEN 'RESTORE_OPENING_FROM_SNAPSHOT' THEN 0
    WHEN 'SEED_OPENING_FROM_PREV_MONTH_CLOSING' THEN 1
    WHEN 'REVIEW_MULTI_ACTIVE_ITEM_MATERIAL' THEN 2
    WHEN 'NO_MONTH_MOVEMENT_REVIEW_MONTHLY' THEN 3
    ELSE 4
  END,
  ABS(g.gap_from_monthly_opening) DESC,
  g.material_id,
  g.item_id,
  g.profile_key;

-- ------------------------------------------------------------
-- C. Material gap yang punya lebih dari satu item aktif
-- ------------------------------------------------------------
SELECT
  g.material_id,
  m.material_code,
  m.material_name,
  g.active_item_count,
  COUNT(*) AS gap_profiles,
  GROUP_CONCAT(DISTINCT CONCAT(g.item_id, ':', COALESCE(i.item_code, '?'), ':', COALESCE(i.item_name, '?')) ORDER BY g.item_id SEPARATOR ' | ') AS active_items_in_gap
FROM tmp_all_remaining_division_log_gaps g
LEFT JOIN mst_material m ON m.id = g.material_id
LEFT JOIN mst_item i ON i.material_id = g.material_id AND COALESCE(i.is_active, 1) = 1
WHERE g.active_item_count > 1
GROUP BY g.material_id, m.material_code, m.material_name, g.active_item_count
ORDER BY gap_profiles DESC, g.material_id;
