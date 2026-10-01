SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16c_repair_division_fifo_fallback_overshoot.sql
-- Tujuan :
-- 1) Memperbaiki movement log fallback FIFO divisi yang overshoot
-- 2) Membatasi qty fallback agar tidak mengurangi lebih besar dari
--    saldo running yang benar pada saat row itu terjadi
-- 3) Rebuild qty_after movement per profil agar trail kembali konsisten
--
-- Prinsip repair:
-- - HANYA menyentuh row movement yang mengandung catatan "FIFO fallback"
-- - qty_content_delta fallback negatif akan di-cap ke saldo running:
--   target_delta = -MIN(abs(requested), saldo_tersedia_running)
-- - Jika saldo running sudah 0, delta fallback dipaksa menjadi 0
-- - qty_buy_delta diturunkan proporsional dari delta content asal
-- - Row non-fallback tidak diubah
--
-- Catatan:
-- - Jalankan setelah 2026-06-16a agar kasus opening stale sudah dibereskan
-- - Tidak menyentuh monthly closing; monthly tetap source-of-truth
-- ============================================================

START TRANSACTION;

SET @target_month := '2026-06-01';
SET @repair_tag := 'Repair division FIFO fallback overshoot 2026-06-16';

-- ------------------------------------------------------------
-- A. Profil kandidat:
--    gunakan bucket yang masih bermasalah karena fallback FIFO
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_fallback_gap_profiles;
CREATE TEMPORARY TABLE tmp_fallback_gap_profiles AS
SELECT
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key
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
    SUM(CASE WHEN COALESCE(notes, '') LIKE '%FIFO fallback%' THEN 1 ELSE 0 END) AS fallback_rows
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
  AND COALESCE(mv.fallback_rows, 0) > 0
  AND ABS(
    COALESCE(s.closing_qty_content, 0)
    - (COALESCE(os.opening_qty_content, s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))
  ) > 0.0001;

ALTER TABLE tmp_fallback_gap_profiles
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, profile_key);

-- ------------------------------------------------------------
-- B. Backup seluruh movement row profil kandidat
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_fallback_gap_movement_backup;
CREATE TEMPORARY TABLE tmp_fallback_gap_movement_backup AS
SELECT l.*
FROM inv_stock_movement_log l
JOIN tmp_fallback_gap_profiles p
  ON p.division_id = l.division_id
 AND p.destination_type = COALESCE(l.destination_type, 'OTHER')
 AND p.item_id = l.item_id
 AND p.material_id = COALESCE(l.material_id, 0)
 AND p.profile_key = COALESCE(l.profile_key, '')
WHERE l.movement_scope = 'DIVISION';

ALTER TABLE tmp_fallback_gap_movement_backup
  ADD PRIMARY KEY (id),
  ADD KEY idx_fallback_gap_backup_profile (division_id, destination_type, material_id, item_id, profile_key(32));

-- ------------------------------------------------------------
-- C. Hitung delta target + running after baru
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_fallback_gap_recalc;
CREATE TEMPORARY TABLE tmp_fallback_gap_recalc AS
SELECT
  calc.id,
  calc.division_id,
  calc.destination_type,
  calc.item_id,
  calc.material_id,
  calc.profile_key,
  ROUND(calc.original_qty_buy_delta, 4) AS original_qty_buy_delta,
  ROUND(calc.original_qty_content_delta, 4) AS original_qty_content_delta,
  ROUND(calc.target_qty_buy_delta, 4) AS target_qty_buy_delta,
  ROUND(calc.target_qty_content_delta, 4) AS target_qty_content_delta,
  ROUND(calc.target_qty_buy_after, 4) AS target_qty_buy_after,
  ROUND(calc.target_qty_content_after, 4) AS target_qty_content_after,
  calc.is_fallback
  FROM (
    SELECT
      ordered.id,
    ordered.division_id,
    ordered.destination_type,
    ordered.item_id,
    ordered.material_id,
    ordered.profile_key,
    ordered.original_qty_buy_delta,
    ordered.original_qty_content_delta,
    ordered.is_fallback,
    @target_content_delta := CASE
      WHEN ordered.is_fallback = 1
           AND ordered.original_qty_content_delta < 0
        THEN -LEAST(
          ABS(ordered.original_qty_content_delta),
          IF(@prev_group_key = ordered.group_key, GREATEST(@running_content_after, 0), 0)
        )
      ELSE ordered.original_qty_content_delta
    END AS target_qty_content_delta,
    @target_buy_delta := CASE
      WHEN ordered.is_fallback = 1
           AND ordered.original_qty_content_delta < 0
           AND ABS(ordered.original_qty_content_delta) > 0.0001
        THEN ROUND(
          ordered.original_qty_buy_delta
          * (@target_content_delta / ordered.original_qty_content_delta),
          4
        )
      ELSE ordered.original_qty_buy_delta
    END AS target_qty_buy_delta,
    @running_content_after := ROUND(
      IF(@prev_group_key = ordered.group_key, @running_content_after, 0)
      + @target_content_delta,
      4
    ) AS target_qty_content_after,
    @running_buy_after := ROUND(
      IF(@prev_group_key = ordered.group_key, @running_buy_after, 0)
      + @target_buy_delta,
      4
    ) AS target_qty_buy_after,
    @prev_group_key := ordered.group_key AS applied_group_key
  FROM (
    SELECT
      b.id,
      b.division_id,
      b.destination_type,
      b.item_id,
      b.material_id,
      b.profile_key,
      b.movement_date,
      b.original_qty_buy_delta,
      b.original_qty_content_delta,
      b.is_fallback,
      CONCAT_WS('|',
        CAST(b.division_id AS CHAR),
        b.destination_type,
        CAST(b.item_id AS CHAR),
        CAST(b.material_id AS CHAR),
        b.profile_key
      ) AS group_key
    FROM (
      SELECT
        l.id,
        l.division_id,
        COALESCE(l.destination_type, 'OTHER') AS destination_type,
        l.item_id,
        COALESCE(l.material_id, 0) AS material_id,
        COALESCE(l.profile_key, '') AS profile_key,
        l.movement_date,
        ROUND(COALESCE(l.qty_buy_delta, 0), 4) AS original_qty_buy_delta,
        ROUND(COALESCE(l.qty_content_delta, 0), 4) AS original_qty_content_delta,
        CASE WHEN COALESCE(l.notes, '') LIKE '%FIFO fallback%' THEN 1 ELSE 0 END AS is_fallback
      FROM tmp_fallback_gap_movement_backup l
    ) b
    ORDER BY
      b.division_id,
      b.destination_type,
      b.item_id,
      b.material_id,
      b.profile_key,
      b.movement_date,
      b.id
  ) ordered
  CROSS JOIN (
    SELECT
      @prev_group_key := '',
      @running_content_after := 0,
      @running_buy_after := 0,
      @target_content_delta := 0,
      @target_buy_delta := 0
  ) vars
  ORDER BY
    ordered.division_id,
    ordered.destination_type,
    ordered.item_id,
    ordered.material_id,
    ordered.profile_key,
    ordered.movement_date,
    ordered.id
) calc;

ALTER TABLE tmp_fallback_gap_recalc
  ADD PRIMARY KEY (id),
  ADD KEY idx_fallback_gap_recalc_profile (division_id, destination_type, material_id, item_id, profile_key(32));

-- ------------------------------------------------------------
-- D. Terapkan hanya row yang memang berubah
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_fallback_gap_changed_rows;
CREATE TEMPORARY TABLE tmp_fallback_gap_changed_rows AS
SELECT *
FROM tmp_fallback_gap_recalc
WHERE ABS(original_qty_buy_delta - target_qty_buy_delta) > 0.0001
   OR ABS(original_qty_content_delta - target_qty_content_delta) > 0.0001
   OR ABS(target_qty_buy_after - (
        SELECT ROUND(COALESCE(l.qty_buy_after, 0), 4)
        FROM inv_stock_movement_log l
        WHERE l.id = tmp_fallback_gap_recalc.id
      )) > 0.0001
   OR ABS(target_qty_content_after - (
        SELECT ROUND(COALESCE(l.qty_content_after, 0), 4)
        FROM inv_stock_movement_log l
        WHERE l.id = tmp_fallback_gap_recalc.id
      )) > 0.0001;

ALTER TABLE tmp_fallback_gap_changed_rows
  ADD PRIMARY KEY (id),
  ADD KEY idx_fallback_gap_changed_profile (division_id, destination_type, material_id, item_id, profile_key(32));

UPDATE inv_stock_movement_log l
JOIN tmp_fallback_gap_changed_rows c ON c.id = l.id
SET
  l.qty_buy_delta = c.target_qty_buy_delta,
  l.qty_content_delta = c.target_qty_content_delta,
  l.qty_buy_after = c.target_qty_buy_after,
  l.qty_content_after = c.target_qty_content_after,
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | cap fallback to running available stock'
  )), 255);

COMMIT;

-- ------------------------------------------------------------
-- E. Ringkasan
-- ------------------------------------------------------------
SELECT 'fallback_gap_profiles' AS metric, COUNT(*) AS total
FROM tmp_fallback_gap_profiles

UNION ALL

SELECT 'movement_rows_backed_up', COUNT(*)
FROM tmp_fallback_gap_movement_backup

UNION ALL

SELECT 'movement_rows_changed', COUNT(*)
FROM tmp_fallback_gap_changed_rows

UNION ALL

SELECT 'fallback_rows_changed', COUNT(*)
FROM tmp_fallback_gap_changed_rows
WHERE is_fallback = 1;

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
  SUM(CASE WHEN c.is_fallback = 1 THEN 1 ELSE 0 END) AS fallback_rows_changed,
  ROUND(SUM(c.original_qty_content_delta), 4) AS total_original_content_delta,
  ROUND(SUM(c.target_qty_content_delta), 4) AS total_target_content_delta
FROM tmp_fallback_gap_changed_rows c
LEFT JOIN mst_material m ON m.id = c.material_id
LEFT JOIN mst_item i ON i.id = c.item_id
GROUP BY
  c.division_id,
  c.destination_type,
  c.material_id,
  m.material_code,
  m.material_name,
  c.item_id,
  i.item_code,
  i.item_name,
  c.profile_key
ORDER BY ABS(total_original_content_delta - total_target_content_delta) DESC, c.material_id, c.item_id, c.profile_key;
