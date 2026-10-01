SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16e_audit_no_opening_snapshot_gap_profiles.sql
-- Tujuan :
-- 1) Membeda detail sisa kasus gap harian divisi yang TIDAK punya
--    opening snapshot bulan berjalan
-- 2) Menunjukkan anchor paling aman untuk repair:
--    - closing bulan sebelumnya
--    - movement pertama di bulan berjalan
--    - total delta non-opening bulan berjalan
-- 3) Menjadi dasar repair sempit setelah residual fallback selesai
-- ============================================================

SET @target_month := '2026-06-01';
SET @next_month := DATE_ADD(@target_month, INTERVAL 1 MONTH);
SET @prev_month := DATE_SUB(@target_month, INTERVAL 1 MONTH);

DROP TEMPORARY TABLE IF EXISTS tmp_no_opening_snapshot_gap_profiles;
CREATE TEMPORARY TABLE tmp_no_opening_snapshot_gap_profiles AS
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
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS remaining_gap,
  COALESCE(mv.month_movement_rows, 0) AS month_movement_rows
FROM inv_division_monthly_stock s
LEFT JOIN inv_division_stock_opening_snapshot os
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
    ), 4) AS net_non_opening_delta,
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
WHERE s.month_key = @target_month
  AND COALESCE(s.material_id, 0) > 0
  AND os.id IS NULL
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) > 0.0001;

ALTER TABLE tmp_no_opening_snapshot_gap_profiles
  ADD PRIMARY KEY (monthly_id),
  ADD KEY idx_no_opening_scope (division_id, destination_type, material_id, item_id),
  ADD KEY idx_no_opening_profile (profile_key(32));

DROP TEMPORARY TABLE IF EXISTS tmp_no_opening_prev_month_anchor;
CREATE TEMPORARY TABLE tmp_no_opening_prev_month_anchor AS
SELECT
  p.monthly_id,
  prev.id AS prev_monthly_id,
  ROUND(COALESCE(prev.opening_qty_buy, 0), 4) AS prev_opening_qty_buy,
  ROUND(COALESCE(prev.opening_qty_content, 0), 4) AS prev_opening_qty_content,
  ROUND(COALESCE(prev.closing_qty_buy, 0), 4) AS prev_closing_qty_buy,
  ROUND(COALESCE(prev.closing_qty_content, 0), 4) AS prev_closing_qty_content
FROM tmp_no_opening_snapshot_gap_profiles p
LEFT JOIN inv_division_monthly_stock prev
  ON prev.month_key = @prev_month
 AND prev.division_id = p.division_id
 AND COALESCE(prev.destination_type, 'OTHER') = p.destination_type
 AND prev.item_id = p.item_id
 AND COALESCE(prev.material_id, 0) = p.material_id
 AND COALESCE(prev.profile_key, '') = p.profile_key;

ALTER TABLE tmp_no_opening_prev_month_anchor
  ADD PRIMARY KEY (monthly_id);

DROP TEMPORARY TABLE IF EXISTS tmp_no_opening_first_month_movement;
CREATE TEMPORARY TABLE tmp_no_opening_first_month_movement AS
SELECT
  picked.monthly_id,
  picked.movement_id,
  picked.movement_date,
  picked.ref_table,
  picked.ref_id,
  picked.qty_buy_delta,
  picked.qty_content_delta,
  picked.qty_buy_after,
  picked.qty_content_after,
  picked.notes
FROM (
  SELECT
    p.monthly_id,
    l.id AS movement_id,
    l.movement_date,
    COALESCE(l.ref_table, '') AS ref_table,
    l.ref_id,
    ROUND(COALESCE(l.qty_buy_delta, 0), 4) AS qty_buy_delta,
    ROUND(COALESCE(l.qty_content_delta, 0), 4) AS qty_content_delta,
    ROUND(COALESCE(l.qty_buy_after, 0), 4) AS qty_buy_after,
    ROUND(COALESCE(l.qty_content_after, 0), 4) AS qty_content_after,
    COALESCE(l.notes, '') AS notes,
    ROW_NUMBER() OVER (
      PARTITION BY p.monthly_id
      ORDER BY l.movement_date ASC, l.id ASC
    ) AS rn
  FROM tmp_no_opening_snapshot_gap_profiles p
  JOIN inv_stock_movement_log l
    ON l.movement_scope = 'DIVISION'
   AND l.division_id = p.division_id
   AND COALESCE(l.destination_type, 'OTHER') = p.destination_type
   AND l.item_id = p.item_id
   AND COALESCE(l.material_id, 0) = p.material_id
   AND COALESCE(l.profile_key, '') = p.profile_key
   AND l.movement_date >= @target_month
   AND l.movement_date < @next_month
) picked
WHERE picked.rn = 1;

ALTER TABLE tmp_no_opening_first_month_movement
  ADD PRIMARY KEY (monthly_id);

SELECT
  p.division_id,
  d.code AS division_code,
  d.name AS division_name,
  p.destination_type,
  p.material_id,
  m.material_code,
  m.material_name,
  p.item_id,
  i.item_code,
  i.item_name,
  p.profile_key,
  p.profile_name,
  p.monthly_opening_qty_content,
  p.monthly_closing_qty_content,
  p.net_non_opening_delta,
  p.remaining_gap,
  prev.prev_monthly_id,
  prev.prev_closing_qty_content,
  prev.prev_closing_qty_buy,
  mv.movement_id AS first_movement_id_in_month,
  mv.movement_date AS first_movement_date_in_month,
  mv.ref_table AS first_movement_ref_table,
  mv.ref_id AS first_movement_ref_id,
  mv.qty_content_delta AS first_movement_qty_content_delta,
  mv.qty_content_after AS first_movement_qty_content_after,
  p.month_movement_rows,
  CASE
    WHEN prev.prev_monthly_id IS NOT NULL THEN 'SEED_OPENING_FROM_PREV_MONTH_CLOSING'
    WHEN p.month_movement_rows > 0 THEN 'REVIEW_FIRST_MOVEMENT_OR_MISSING_OPENING_REPOST'
    ELSE 'ORPHAN_MONTHLY_WITHOUT_MONTH_MOVEMENT'
  END AS suggested_repair_path
FROM tmp_no_opening_snapshot_gap_profiles p
LEFT JOIN tmp_no_opening_prev_month_anchor prev ON prev.monthly_id = p.monthly_id
LEFT JOIN tmp_no_opening_first_month_movement mv ON mv.monthly_id = p.monthly_id
LEFT JOIN mst_material m ON m.id = p.material_id
LEFT JOIN mst_item i ON i.id = p.item_id
LEFT JOIN mst_operational_division d ON d.id = p.division_id
ORDER BY ABS(p.remaining_gap) DESC, p.material_id, p.item_id, p.profile_key;
