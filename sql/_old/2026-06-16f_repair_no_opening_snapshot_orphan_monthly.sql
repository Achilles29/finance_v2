SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16f_repair_no_opening_snapshot_orphan_monthly.sql
-- Tujuan :
-- 1) Menutup 2 sisa gap harian divisi bucket NO_OPENING_SNAPSHOT
-- 2) Menormalkan opening monthly bulan Juni yang nyangkut tanpa:
--    - opening snapshot
--    - closing bulan sebelumnya
-- 3) HANYA menyentuh 2 row monthly sempit yang sudah terverifikasi:
--    - TEPUNG TERIGU / KITCHEN
--    - INDOMIE AYAM SPESIAL / KITCHEN
--
-- Ringkasan analisis:
-- - TEPUNG TERIGU:
--   opening monthly 891.8026 adalah orphan/stale.
--   Tidak ada opening snapshot, tidak ada prev monthly, movement pertama
--   Juni adalah receipt +4000 dan net delta bulan ini = closing 3000.
--   Maka opening yang benar = 0.
-- - INDOMIE AYAM SPESIAL:
--   opening monthly 1 juga orphan/stale.
--   Tidak ada opening snapshot, tidak ada prev monthly, dan tidak ada
--   movement bulan Juni. Maka opening yang benar = 0.
-- ============================================================

START TRANSACTION;

SET @target_month := '2026-06-01';
SET @repair_tag := 'Repair no-opening-snapshot orphan monthly 2026-06-16';

DROP TEMPORARY TABLE IF EXISTS tmp_no_opening_orphan_monthly_target;
CREATE TEMPORARY TABLE tmp_no_opening_orphan_monthly_target AS
SELECT
  s.id,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  COALESCE(s.profile_name, '') AS profile_name,
  ROUND(COALESCE(s.opening_qty_buy, 0), 4) AS old_opening_qty_buy,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS old_opening_qty_content
FROM inv_division_monthly_stock s
WHERE s.month_key = @target_month
  AND (
    (
      s.id = 2653
      AND s.division_id = 3
      AND COALESCE(s.destination_type, 'OTHER') = 'KITCHEN'
      AND s.item_id = 146
      AND COALESCE(s.material_id, 0) = 204
      AND COALESCE(s.profile_key, '') = '838c0e09a255ec4ed78eb8afba464f86d4f892bd'
    )
    OR
    (
      s.id = 1859
      AND s.division_id = 3
      AND COALESCE(s.destination_type, 'OTHER') = 'KITCHEN'
      AND s.item_id = 270
      AND COALESCE(s.material_id, 0) = 87
      AND COALESCE(s.profile_key, '') = ''
    )
  );

ALTER TABLE tmp_no_opening_orphan_monthly_target
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_no_opening_orphan_monthly_backup;
CREATE TEMPORARY TABLE tmp_no_opening_orphan_monthly_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_no_opening_orphan_monthly_target t ON t.id = s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_no_opening_orphan_monthly_target t ON t.id = s.id
SET
  s.opening_qty_buy = 0.0000,
  s.opening_qty_content = 0.0000,
  s.opening_total_value = 0.00,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | zero opening because no snapshot, no previous month anchor, and opening was stale orphan'
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'target_monthly_rows' AS metric, COUNT(*) AS total
FROM tmp_no_opening_orphan_monthly_target

UNION ALL

SELECT 'backed_up_monthly_rows', COUNT(*)
FROM tmp_no_opening_orphan_monthly_backup;

SELECT
  s.id,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.item_id,
  COALESCE(s.material_id, 0) AS material_id,
  COALESCE(s.profile_key, '') AS profile_key,
  COALESCE(s.profile_name, '') AS profile_name,
  ROUND(COALESCE(s.opening_qty_buy, 0), 4) AS opening_qty_buy_after_repair,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS opening_qty_content_after_repair,
  ROUND(COALESCE(s.closing_qty_buy, 0), 4) AS closing_qty_buy,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS closing_qty_content
FROM inv_division_monthly_stock s
JOIN tmp_no_opening_orphan_monthly_target t ON t.id = s.id
ORDER BY s.id;

