SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16d_repair_residual_division_daily_log_gap_profiles.sql
-- Tujuan :
-- 1) Menutup 7 residual daily log gap setelah repair 2026-06-16c
-- 2) Memperbaiki row usage/fallback yang masih salah arah secara spesifik
-- 3) Rebuild ulang running after movement untuk tiap profil target
--
-- Profil target:
-- - CARAMEL CRUMB      BAR      : row 4163 harus 0
-- - ESPRESSO ARABIKA   BAR      : row 1990 harus consume 15 (bukan 18)
-- - SARI LEMON         BAR      : row 2665 harus 0
-- - SO GAYO WINE       BAR      : row 2236 harus 0
-- - JERUK NIPIS        KITCHEN  : row 4759 harus 0
-- - KANI STICK         KITCHEN  : row 6615 harus consume 17
-- - KECAP ASIN         KITCHEN  : row 1451 harus 0
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair residual division daily log gap profiles 2026-06-16';

-- ------------------------------------------------------------
-- A. Backup seluruh row movement profil target
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_residual_gap_target_profiles;
CREATE TEMPORARY TABLE tmp_residual_gap_target_profiles AS
SELECT 2 AS division_id, 'BAR' AS destination_type, 46 AS item_id, 42 AS material_id, 'a4a6fa2873231a18ebb8bec9c5333595db9901beddfd31599e25a371e20528f6' AS profile_key
UNION ALL SELECT 2, 'BAR', 165, 229, '7a01c2e626c2ae7c1ab5de1364b1c2d2ace087e6b5f8acc0b00ea642a9f91d85'
UNION ALL SELECT 2, 'BAR', 225, 243, 'e3a51cb637ea7bd62c872e345b1aef20fe8631841a561a050754ccc81dd72f27'
UNION ALL SELECT 2, 'BAR', 185, 245, ''
UNION ALL SELECT 3, 'KITCHEN', 4, 97, '87fbe181b2ec9c2224b7e91664847c3c000e0abc'
UNION ALL SELECT 3, 'KITCHEN', 90, 102, 'f0081d0ced187e315ef1e7f5518bd0ab203bfc9e'
UNION ALL SELECT 3, 'KITCHEN', 195, 107, '256d80f044b0f9f6f014c2434ac58f5f3a8776c3efef66a55564bd989135790d';

ALTER TABLE tmp_residual_gap_target_profiles
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_residual_gap_movement_backup;
CREATE TEMPORARY TABLE tmp_residual_gap_movement_backup AS
SELECT l.*
FROM inv_stock_movement_log l
JOIN tmp_residual_gap_target_profiles p
  ON p.division_id = l.division_id
 AND p.destination_type = COALESCE(l.destination_type, 'OTHER')
 AND p.item_id = l.item_id
 AND p.material_id = COALESCE(l.material_id, 0)
 AND p.profile_key = COALESCE(l.profile_key, '')
WHERE l.movement_scope = 'DIVISION';

ALTER TABLE tmp_residual_gap_movement_backup
  ADD PRIMARY KEY (id),
  ADD KEY idx_residual_gap_backup_profile (division_id, destination_type, material_id, item_id, profile_key(32));

-- ------------------------------------------------------------
-- B. Update row sumber masalah
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log
SET
  qty_buy_delta = 0.0000,
  qty_content_delta = 0.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | rollback residual overshoot row'
  )), 255)
WHERE id IN (1451, 2236, 2665, 4163, 4759);

UPDATE inv_stock_movement_log
SET
  qty_buy_delta = -0.0150,
  qty_content_delta = -15.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | cap residual usage to remaining 15'
  )), 255)
WHERE id = 1990;

UPDATE inv_stock_movement_log
SET
  qty_buy_delta = -0.0170,
  qty_content_delta = -17.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | restore residual fallback consume 17 before purchase'
  )), 255)
WHERE id = 6615;

-- ------------------------------------------------------------
-- C. Rebuild qty_after seluruh profil target
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_residual_gap_recalc;
CREATE TEMPORARY TABLE tmp_residual_gap_recalc AS
SELECT
  ordered.id,
  ordered.division_id,
  ordered.destination_type,
  ordered.item_id,
  ordered.material_id,
  ordered.profile_key,
  ROUND(ordered.qty_buy_delta, 4) AS qty_buy_delta,
  ROUND(ordered.qty_content_delta, 4) AS qty_content_delta,
  @running_content_after := ROUND(
    IF(@prev_group_key = ordered.group_key, @running_content_after, 0)
    + ordered.qty_content_delta,
    4
  ) AS target_qty_content_after,
  @running_buy_after := ROUND(
    IF(@prev_group_key = ordered.group_key, @running_buy_after, 0)
    + ordered.qty_buy_delta,
    4
  ) AS target_qty_buy_after,
  @prev_group_key := ordered.group_key AS applied_group_key
FROM (
  SELECT
    l.id,
    l.division_id,
    COALESCE(l.destination_type, 'OTHER') AS destination_type,
    l.item_id,
    COALESCE(l.material_id, 0) AS material_id,
    COALESCE(l.profile_key, '') AS profile_key,
    l.movement_date,
    ROUND(COALESCE(l.qty_buy_delta, 0), 4) AS qty_buy_delta,
    ROUND(COALESCE(l.qty_content_delta, 0), 4) AS qty_content_delta,
    CONCAT_WS('|',
      CAST(l.division_id AS CHAR),
      COALESCE(l.destination_type, 'OTHER'),
      CAST(l.item_id AS CHAR),
      CAST(COALESCE(l.material_id, 0) AS CHAR),
      COALESCE(l.profile_key, '')
    ) AS group_key
  FROM inv_stock_movement_log l
  JOIN tmp_residual_gap_target_profiles p
    ON p.division_id = l.division_id
   AND p.destination_type = COALESCE(l.destination_type, 'OTHER')
   AND p.item_id = l.item_id
   AND p.material_id = COALESCE(l.material_id, 0)
   AND p.profile_key = COALESCE(l.profile_key, '')
  WHERE l.movement_scope = 'DIVISION'
  ORDER BY
    l.division_id,
    COALESCE(l.destination_type, 'OTHER'),
    l.item_id,
    COALESCE(l.material_id, 0),
    COALESCE(l.profile_key, ''),
    l.movement_date,
    l.id
) ordered
CROSS JOIN (
  SELECT
    @prev_group_key := '',
    @running_content_after := 0,
    @running_buy_after := 0
) vars
ORDER BY
  ordered.division_id,
  ordered.destination_type,
  ordered.item_id,
  ordered.material_id,
  ordered.profile_key,
  ordered.movement_date,
  ordered.id;

ALTER TABLE tmp_residual_gap_recalc
  ADD PRIMARY KEY (id),
  ADD KEY idx_residual_gap_recalc_profile (division_id, destination_type, material_id, item_id, profile_key(32));

UPDATE inv_stock_movement_log l
JOIN tmp_residual_gap_recalc r ON r.id = l.id
SET
  l.qty_buy_after = r.target_qty_buy_after,
  l.qty_content_after = r.target_qty_content_after;

COMMIT;

-- ------------------------------------------------------------
-- D. Ringkasan
-- ------------------------------------------------------------
SELECT 'target_profiles' AS metric, COUNT(*) AS total
FROM tmp_residual_gap_target_profiles

UNION ALL

SELECT 'movement_rows_backed_up', COUNT(*)
FROM tmp_residual_gap_movement_backup

UNION ALL

SELECT 'source_rows_adjusted', COUNT(*)
FROM inv_stock_movement_log
WHERE id IN (1451, 1990, 2236, 2665, 4163, 4759, 6615);

SELECT
  id,
  movement_date,
  division_id,
  COALESCE(destination_type, 'OTHER') AS destination_type,
  item_id,
  COALESCE(material_id, 0) AS material_id,
  COALESCE(profile_key, '') AS profile_key,
  qty_buy_delta,
  qty_content_delta,
  qty_buy_after,
  qty_content_after
FROM inv_stock_movement_log
WHERE id IN (1451, 1990, 2236, 2665, 4163, 4759, 6615)
ORDER BY id;

SELECT
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS monthly_closing_qty_content,
  ROUND(COALESCE(os.opening_qty_content, s.opening_qty_content, 0), 4) AS opening_anchor_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(
    COALESCE(s.closing_qty_content, 0)
    - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)),
    4
  ) AS remaining_gap_after_repair
FROM inv_division_monthly_stock s
LEFT JOIN inv_division_stock_opening_snapshot os
  ON os.snapshot_month = '2026-06-01'
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
JOIN tmp_residual_gap_target_profiles p
  ON p.division_id = s.division_id
 AND p.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND p.item_id = s.item_id
 AND p.material_id = COALESCE(s.material_id, 0)
 AND p.profile_key = COALESCE(s.profile_key, '')
WHERE s.month_key = '2026-06-01'
ORDER BY ABS(remaining_gap_after_repair) DESC, s.division_id, s.item_id, s.profile_key;
