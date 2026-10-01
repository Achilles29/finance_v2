SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-15c_repair_house_blend_to_house_blend_70.sql
-- Tujuan :
-- 1) Memperbaiki salah input purchase "HOUSE BLEND" yang seharusnya
--    memakai item/material "HOUSE BLEND 70"
-- 2) Menyatukan jejak purchase, receipt, stok divisi, movement, dan
--    FIFO lot ke item kanonik HOUSE BLEND 70
-- 3) Membersihkan lot koreksi ganda yang sempat ditambahkan sebagai
--    tambalan agar saldo divisi tidak dobel
--
-- Fakta audit:
-- - Item salah   : mst_item.id = 556, item_name = HOUSE BLEND, material_id NULL
-- - Item benar   : mst_item.id = 160, item_name = HOUSE BLEND 70, material_id = 223
-- - Profile salah:
--   a) 20b5554313d881041ff3f1d9dda42ad9cae8df92938f89584fad14ac9592aff4 (SAKHA)
--   b) d02bb14e501dd781db6394975266216666fcbb1415137238a5731ac604412c8c (JAVA JOE BEAN)
--   c) 733d30382913bba30f3c8a4864d941ffa5885516210908982605dbef54ec9269 (JAVA JOE BLEND, receipt VOID)
--
-- Catatan:
-- - Script ini sengaja sempit hanya untuk kasus HOUSE BLEND -> HOUSE BLEND 70
-- - Tidak mengubah qty, hanya koreksi referensi item/material/nama
-- - Tidak menghitung ulang profile_key; profile lama dipertahankan supaya
--   identitas historis yang sudah terlanjur dipakai tetap konsisten
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair HOUSE BLEND -> HOUSE BLEND 70 2026-06-15';
SET @legacy_item_id := 556;
SET @canonical_item_id := 160;
SET @canonical_material_id := 223;
SET @canonical_item_name := 'HOUSE BLEND 70';
SET @canonical_material_name := 'HOUSE BLEND 70';

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_target_profiles;
CREATE TEMPORARY TABLE tmp_house_blend_target_profiles (
  profile_key CHAR(64) NOT NULL,
  PRIMARY KEY (profile_key)
);

INSERT INTO tmp_house_blend_target_profiles (profile_key) VALUES
  ('20b5554313d881041ff3f1d9dda42ad9cae8df92938f89584fad14ac9592aff4'),
  ('d02bb14e501dd781db6394975266216666fcbb1415137238a5731ac604412c8c'),
  ('733d30382913bba30f3c8a4864d941ffa5885516210908982605dbef54ec9269');

-- ------------------------------------------------------------
-- A. Backup master + transaksi purchase
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_item_backup;
CREATE TEMPORARY TABLE tmp_house_blend_item_backup AS
SELECT *
FROM mst_item
WHERE id IN (@legacy_item_id, @canonical_item_id);

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_catalog_backup;
CREATE TEMPORARY TABLE tmp_house_blend_catalog_backup AS
SELECT c.*
FROM mst_purchase_catalog c
JOIN tmp_house_blend_target_profiles t ON t.profile_key = c.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_po_backup;
CREATE TEMPORARY TABLE tmp_house_blend_po_backup AS
SELECT l.*
FROM pur_purchase_order_line l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_receipt_backup;
CREATE TEMPORARY TABLE tmp_house_blend_receipt_backup AS
SELECT l.*
FROM pur_purchase_receipt_line l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key;

-- ------------------------------------------------------------
-- B. Backup stok/movement aktif
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_movement_backup;
CREATE TEMPORARY TABLE tmp_house_blend_movement_backup AS
SELECT l.*
FROM inv_stock_movement_log l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key
WHERE l.item_id = @legacy_item_id;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_division_monthly_backup;
CREATE TEMPORARY TABLE tmp_house_blend_division_monthly_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key
WHERE s.item_id = @legacy_item_id OR COALESCE(s.material_id, 0) = @canonical_material_id;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_warehouse_monthly_backup;
CREATE TEMPORARY TABLE tmp_house_blend_warehouse_monthly_backup AS
SELECT s.*
FROM inv_warehouse_monthly_stock s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_division_opening_backup;
CREATE TEMPORARY TABLE tmp_house_blend_division_opening_backup AS
SELECT s.*
FROM inv_division_stock_opening_snapshot s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_lot_backup;
CREATE TEMPORARY TABLE tmp_house_blend_lot_backup AS
SELECT l.*
FROM inv_material_fifo_lot l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_duplicate_lot_ids;
CREATE TEMPORARY TABLE tmp_house_blend_duplicate_lot_ids AS
SELECT id
FROM inv_material_fifo_lot
WHERE source_table = 'lot_repair'
  AND item_id = @legacy_item_id
  AND COALESCE(material_id, 0) = @canonical_material_id
  AND profile_key IN (
    '20b5554313d881041ff3f1d9dda42ad9cae8df92938f89584fad14ac9592aff4',
    'd02bb14e501dd781db6394975266216666fcbb1415137238a5731ac604412c8c'
  );

ALTER TABLE tmp_house_blend_duplicate_lot_ids
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_house_blend_duplicate_lot_backup;
CREATE TEMPORARY TABLE tmp_house_blend_duplicate_lot_backup AS
SELECT l.*
FROM inv_material_fifo_lot l
JOIN tmp_house_blend_duplicate_lot_ids d ON d.id = l.id;

-- ------------------------------------------------------------
-- C. Repair master purchase agar profile salah menjadi HOUSE BLEND 70
-- ------------------------------------------------------------
UPDATE mst_purchase_catalog c
JOIN tmp_house_blend_target_profiles t ON t.profile_key = c.profile_key
SET
  c.item_id = @canonical_item_id,
  c.material_id = @canonical_material_id,
  c.catalog_name = @canonical_item_name,
  c.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_order_line l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key
SET
  l.item_id = @canonical_item_id,
  l.material_id = @canonical_material_id,
  l.snapshot_item_name = @canonical_item_name,
  l.snapshot_material_name = @canonical_material_name,
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_receipt_line l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key
SET
  l.item_id = @canonical_item_id,
  l.material_id = @canonical_material_id,
  l.updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- D. Repair movement + stok divisi
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key
SET
  l.item_id = @canonical_item_id,
  l.material_id = @canonical_material_id,
  l.profile_name = @canonical_item_name,
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255)
WHERE l.item_id = @legacy_item_id;

UPDATE inv_division_monthly_stock s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key
SET
  s.item_id = @canonical_item_id,
  s.material_id = @canonical_material_id,
  s.profile_name = @canonical_item_name,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.item_id = @legacy_item_id OR COALESCE(s.material_id, 0) = @canonical_material_id;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key
SET
  s.item_id = @canonical_item_id,
  s.material_id = @canonical_material_id,
  s.profile_name = @canonical_item_name,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_house_blend_target_profiles t ON t.profile_key = s.profile_key
SET
  s.item_id = @canonical_item_id,
  s.material_id = @canonical_material_id,
  s.profile_name = @canonical_item_name,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- E. Repair FIFO lot asli, lalu hapus lot tambalan yang dobel
-- ------------------------------------------------------------
UPDATE inv_material_fifo_lot l
JOIN tmp_house_blend_target_profiles t ON t.profile_key = l.profile_key
SET
  l.item_id = @canonical_item_id,
  l.material_id = @canonical_material_id,
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.source_table <> 'lot_repair';

DELETE l
FROM inv_material_fifo_lot l
JOIN tmp_house_blend_duplicate_lot_ids d ON d.id = l.id;

-- ------------------------------------------------------------
-- F. Matikan item legacy agar tidak dipakai lagi
-- ------------------------------------------------------------
UPDATE mst_item
SET
  material_id = @canonical_material_id,
  is_active = 0,
  updated_at = CURRENT_TIMESTAMP
WHERE id = @legacy_item_id;

COMMIT;

-- ------------------------------------------------------------
-- G. Ringkasan hasil repair
-- ------------------------------------------------------------
SELECT 'catalog_rows_repaired' AS metric, COUNT(*) AS total
FROM tmp_house_blend_catalog_backup

UNION ALL

SELECT 'po_rows_repaired', COUNT(*)
FROM tmp_house_blend_po_backup

UNION ALL

SELECT 'receipt_rows_repaired', COUNT(*)
FROM tmp_house_blend_receipt_backup

UNION ALL

SELECT 'movement_rows_repaired', COUNT(*)
FROM tmp_house_blend_movement_backup

UNION ALL

SELECT 'division_monthly_rows_repaired', COUNT(*)
FROM tmp_house_blend_division_monthly_backup

UNION ALL

SELECT 'warehouse_monthly_rows_repaired', COUNT(*)
FROM tmp_house_blend_warehouse_monthly_backup

UNION ALL

SELECT 'division_opening_rows_repaired', COUNT(*)
FROM tmp_house_blend_division_opening_backup

UNION ALL

SELECT 'lot_rows_repaired', COUNT(*)
FROM tmp_house_blend_lot_backup

UNION ALL

SELECT 'duplicate_lot_rows_deleted', COUNT(*)
FROM tmp_house_blend_duplicate_lot_backup

UNION ALL

SELECT 'legacy_item_rows_updated', COUNT(*)
FROM tmp_house_blend_item_backup
WHERE id = @legacy_item_id;
