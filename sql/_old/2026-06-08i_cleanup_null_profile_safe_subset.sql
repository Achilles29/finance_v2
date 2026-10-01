SET NAMES utf8mb4;

-- ============================================================
-- CLEANUP SAFE SUBSET: null profile rows yang relatif aman
-- Tanggal: 2026-06-08
--
-- Fokus:
-- 1. canonical-kan cluster AIR MINERAL GALON BAR null-profile
--    ke profile_key exact yang sudah ada di catalog
-- 2. hapus placeholder monthly null-profile qty=0 source_mode=REBUILD
--    yang tidak punya open lot pasangan
--
-- Catatan:
-- - script ini tidak menyentuh cluster null-profile yang kandidat
--   profilenya ambigu atau berpotensi collision
-- ============================================================

START TRANSACTION;


-- ============================================================
-- STEP 1. Canonical AIR MINERAL GALON BAR
-- ============================================================
SET @air_profile_key := '13d3bbf64f3dcb772b1924e4f8938c3766cd9460';

UPDATE inv_stock_movement_log
SET
  profile_key = @air_profile_key,
  notes = CASE
    WHEN COALESCE(notes, '') LIKE '%canonicalize null profile 2026-06-08%' THEN notes
    ELSE LEFT(CONCAT(COALESCE(notes, ''), ' | canonicalize null profile 2026-06-08'), 255)
  END
WHERE movement_scope = 'DIVISION'
  AND division_id = 2
  AND destination_type = 'BAR'
  AND item_id = 220
  AND material_id = 2
  AND buy_uom_id = 9
  AND content_uom_id = 9
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step1a_air_movement_rows;

UPDATE inv_material_fifo_issue_log
SET
  profile_key = @air_profile_key,
  notes = CASE
    WHEN COALESCE(notes, '') LIKE '%canonicalize null profile 2026-06-08%' THEN notes
    ELSE LEFT(CONCAT(COALESCE(notes, ''), ' | canonicalize null profile 2026-06-08'), 255)
  END
WHERE location_scope = 'DIVISION'
  AND division_id = 2
  AND destination_type = 'BAR'
  AND item_id = 220
  AND material_id = 2
  AND buy_uom_id = 9
  AND content_uom_id = 9
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step1b_air_issue_header_rows;

UPDATE inv_material_fifo_lot
SET profile_key = @air_profile_key
WHERE id = 839
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step1c_air_lot_rows;

UPDATE inv_division_monthly_stock
SET
  profile_key = @air_profile_key,
  identity_key = @air_profile_key,
  notes = CASE
    WHEN COALESCE(notes, '') LIKE '%canonicalize null profile 2026-06-08%' THEN notes
    ELSE LEFT(CONCAT(COALESCE(notes, ''), ' | canonicalize null profile 2026-06-08'), 255)
  END
WHERE id = 1811
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step1d_air_monthly_rows;


-- ============================================================
-- STEP 2. Hapus placeholder monthly null-profile qty=0 REBUILD
--         yang tidak punya open lot pasangan.
-- ============================================================
DELETE dms
FROM inv_division_monthly_stock dms
WHERE dms.id IN (1733, 1739, 1743, 1745, 1751, 1759, 1773, 1775, 1795)
  AND dms.source_mode = 'REBUILD'
  AND dms.profile_key IS NULL
  AND ABS(dms.closing_qty_content) <= 0.001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_material_fifo_lot fl
    WHERE fl.location_scope = 'DIVISION'
      AND fl.status = 'OPEN'
      AND fl.division_id = dms.division_id
      AND fl.destination_type = dms.destination_type
      AND COALESCE(fl.item_id, 0) = COALESCE(dms.item_id, 0)
      AND COALESCE(fl.material_id, 0) = COALESCE(dms.material_id, 0)
      AND COALESCE(fl.buy_uom_id, 0) = COALESCE(dms.buy_uom_id, 0)
      AND fl.content_uom_id = dms.content_uom_id
      AND COALESCE(fl.profile_key, '') = ''
      AND fl.qty_balance > 0.001
  );

SELECT ROW_COUNT() AS step2_deleted_zero_rebuild_monthly_rows;


-- ============================================================
-- STEP 3. Verifikasi
-- ============================================================
SELECT
  COUNT(*) AS monthly_null_profile_material_after
FROM inv_division_monthly_stock
WHERE material_id IS NOT NULL
  AND COALESCE(profile_key, '') = '';

SELECT
  COUNT(*) AS open_lot_null_profile_after
FROM inv_material_fifo_lot
WHERE location_scope = 'DIVISION'
  AND status = 'OPEN'
  AND qty_balance > 0.001
  AND COALESCE(profile_key, '') = '';

SELECT
  COUNT(*) AS division_movement_null_profile_after
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND material_id IS NOT NULL
  AND COALESCE(profile_key, '') = '';

SELECT
  id, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id,
  profile_key, identity_key, closing_qty_content
FROM inv_division_monthly_stock
WHERE id = 1811;

SELECT
  id, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id,
  profile_key, qty_balance, status
FROM inv_material_fifo_lot
WHERE id = 839;

COMMIT;
