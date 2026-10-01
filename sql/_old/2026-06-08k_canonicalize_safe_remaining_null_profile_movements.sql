SET NAMES utf8mb4;

-- ============================================================
-- CANONICALIZE SAFE REMAINING NULL-PROFILE MOVEMENTS
-- Tanggal: 2026-06-08
--
-- Fokus:
-- - hanya untuk movement history yang kandidat profile exact-nya
--   relatif aman dan unik aktif
-- - tidak menyentuh row yang masih ambigu
--
-- Kandidat aman:
-- 1. ICE CREAM VANILLA / buy_uom=11 / content_uom=11
--    -> 32cc7a69b9ae78102df0eb4d0b4e39c4067b342d516d78cdf78030381e57e427
-- 2. CARAMEL SAUCE / buy_uom=11 / content_uom=11
--    -> 40a64c7f0ba674acc28310c06fe28702feb9e57e24687c956d56a8ceffa0ce39
-- ============================================================

START TRANSACTION;

UPDATE inv_stock_movement_log
SET
  profile_key = '32cc7a69b9ae78102df0eb4d0b4e39c4067b342d516d78cdf78030381e57e427',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE '%canonicalize safe null profile 2026-06-08%' THEN notes
    ELSE LEFT(CONCAT(COALESCE(notes, ''), ' | canonicalize safe null profile 2026-06-08'), 255)
  END
WHERE movement_scope = 'DIVISION'
  AND division_id = 3
  AND destination_type = 'KITCHEN'
  AND item_id = 74
  AND material_id = 83
  AND buy_uom_id = 11
  AND content_uom_id = 11
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step1_ice_cream_rows;

UPDATE inv_stock_movement_log
SET
  profile_key = '40a64c7f0ba674acc28310c06fe28702feb9e57e24687c956d56a8ceffa0ce39',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE '%canonicalize safe null profile 2026-06-08%' THEN notes
    ELSE LEFT(CONCAT(COALESCE(notes, ''), ' | canonicalize safe null profile 2026-06-08'), 255)
  END
WHERE movement_scope = 'DIVISION'
  AND division_id = 3
  AND destination_type = 'KITCHEN'
  AND item_id = 47
  AND material_id = 43
  AND buy_uom_id = 11
  AND content_uom_id = 11
  AND profile_key IS NULL;

SELECT ROW_COUNT() AS step2_caramel_sauce_rows;

SELECT
  COUNT(*) AS remaining_division_material_null_profile_movements
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND material_id IS NOT NULL
  AND COALESCE(profile_key, '') = '';

COMMIT;
