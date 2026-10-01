SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16b_audit_remaining_division_daily_log_gap.sql
-- Tujuan :
-- 1) Menampilkan sisa profil bahan baku divisi yang masih punya
--    gap daily setelah asumsi opening monthly direstore dari snapshot
-- 2) Membantu membedakan:
--    - kasus fallback FIFO overshoot
--    - kasus opening snapshot memang tidak ada
--    - kasus histori lain yang perlu repair sempit
-- 3) Menjadi daftar kerja setelah SQL 2026-06-16a dijalankan
-- ============================================================

SET @target_month := '2026-06-01';

DROP TEMPORARY TABLE IF EXISTS tmp_remaining_division_daily_log_gap;
CREATE TEMPORARY TABLE tmp_remaining_division_daily_log_gap AS
SELECT
  s.id AS monthly_id,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  s.profile_name,
  ROUND(COALESCE(s.opening_qty_buy, 0), 4) AS monthly_opening_qty_buy,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS monthly_opening_qty_content,
  ROUND(COALESCE(os.opening_qty_buy, 0), 4) AS snapshot_opening_qty_buy,
  ROUND(COALESCE(os.opening_qty_content, 0), 4) AS snapshot_opening_qty_content,
  ROUND(COALESCE(s.closing_qty_buy, 0), 4) AS monthly_closing_qty_buy,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS monthly_closing_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS simulated_remaining_gap,
  COALESCE(mv.fallback_rows, 0) AS fallback_rows,
  COALESCE(mv.opening_repost_rows, 0) AS opening_repost_rows,
  CASE
    WHEN COALESCE(mv.fallback_rows, 0) > 0 THEN 'FIFO_FALLBACK_OR_OVERSHOOT'
    WHEN os.id IS NULL THEN 'NO_OPENING_SNAPSHOT'
    WHEN COALESCE(mv.opening_repost_rows, 0) = 0 THEN 'NON_OPENING_HISTORICAL_DRIFT'
    ELSE 'OPENING_RESTORED_BUT_STILL_GAP'
  END AS repair_bucket
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
    SUM(CASE WHEN COALESCE(notes, '') LIKE '%FIFO fallback%' THEN 1 ELSE 0 END) AS fallback_rows,
    SUM(CASE WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 1 ELSE 0 END) AS opening_repost_rows
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
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) > 0.0001;

ALTER TABLE tmp_remaining_division_daily_log_gap
  ADD KEY idx_remaining_gap_bucket (repair_bucket, division_id, material_id),
  ADD KEY idx_remaining_gap_profile (profile_key(32));

SELECT
  repair_bucket,
  COUNT(*) AS total_profiles
FROM tmp_remaining_division_daily_log_gap
GROUP BY repair_bucket
ORDER BY total_profiles DESC, repair_bucket;

SELECT
  g.division_id,
  d.code AS division_code,
  d.name AS division_name,
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
  g.monthly_closing_qty_content,
  g.net_non_opening_delta,
  g.simulated_remaining_gap,
  g.fallback_rows,
  g.opening_repost_rows,
  g.repair_bucket
FROM tmp_remaining_division_daily_log_gap g
LEFT JOIN mst_material m ON m.id = g.material_id
LEFT JOIN mst_item i ON i.id = g.item_id
LEFT JOIN mst_operational_division d ON d.id = g.division_id
ORDER BY
  CASE g.repair_bucket
    WHEN 'FIFO_FALLBACK_OR_OVERSHOOT' THEN 0
    WHEN 'OPENING_RESTORED_BUT_STILL_GAP' THEN 1
    WHEN 'NO_OPENING_SNAPSHOT' THEN 2
    ELSE 3
  END,
  ABS(g.simulated_remaining_gap) DESC,
  g.material_id,
  g.item_id,
  g.profile_key;
