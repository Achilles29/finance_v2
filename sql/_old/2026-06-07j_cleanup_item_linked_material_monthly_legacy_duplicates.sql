SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07j_cleanup_item_linked_material_monthly_legacy_duplicates.sql
-- Tujuan :
-- 1) Menghapus row monthly legacy MATERIAL yang item-linked
--    bila pada identity/bulan yang sama sudah ada sibling ITEM
-- 2) Mengurangi double count saldo bulanan pada pembacaan item-centric
-- 3) Membersihkan jejak legacy yang membuat /pos/stock-commit-audit
--    tetap membaca mismatch palsu walau repair sudah dijalankan
--
-- Prinsip:
-- - HANYA hapus row MATERIAL item-linked yang punya sibling ITEM
--   exact identity pada bulan yang sama
-- - Tidak menyentuh movement log
-- - Tidak menyentuh row material-only legacy
-- - Aman sebagai cleanup legacy sebelum manual reclass lanjutan
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Kandidat division monthly legacy duplicate
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_material_duplicates;
CREATE TEMPORARY TABLE tmp_division_monthly_material_duplicates AS
SELECT s.id
FROM inv_division_monthly_stock s
JOIN inv_division_monthly_stock i
  ON i.month_key = s.month_key
 AND i.division_id = s.division_id
 AND COALESCE(i.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND i.identity_key = s.identity_key
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id <=> s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND COALESCE(i.stock_domain, 'ITEM') <> 'MATERIAL'
WHERE COALESCE(s.stock_domain, 'ITEM') = 'MATERIAL'
  AND COALESCE(s.item_id, 0) > 0;

ALTER TABLE tmp_division_monthly_material_duplicates
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_material_duplicates_backup;
CREATE TEMPORARY TABLE tmp_division_monthly_material_duplicates_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_division_monthly_material_duplicates d ON d.id = s.id;

DELETE s
FROM inv_division_monthly_stock s
JOIN tmp_division_monthly_material_duplicates d ON d.id = s.id;

-- ------------------------------------------------------------
-- B. Kandidat warehouse monthly legacy duplicate
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_material_duplicates;
CREATE TEMPORARY TABLE tmp_warehouse_monthly_material_duplicates AS
SELECT s.id
FROM inv_warehouse_monthly_stock s
JOIN inv_warehouse_monthly_stock i
  ON i.month_key = s.month_key
 AND i.identity_key = s.identity_key
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id <=> s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND COALESCE(i.stock_domain, 'ITEM') <> 'MATERIAL'
WHERE COALESCE(s.stock_domain, 'ITEM') = 'MATERIAL'
  AND COALESCE(s.item_id, 0) > 0;

ALTER TABLE tmp_warehouse_monthly_material_duplicates
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_material_duplicates_backup;
CREATE TEMPORARY TABLE tmp_warehouse_monthly_material_duplicates_backup AS
SELECT s.*
FROM inv_warehouse_monthly_stock s
JOIN tmp_warehouse_monthly_material_duplicates d ON d.id = s.id;

DELETE s
FROM inv_warehouse_monthly_stock s
JOIN tmp_warehouse_monthly_material_duplicates d ON d.id = s.id;

COMMIT;

-- ------------------------------------------------------------
-- C. Ringkasan cleanup
-- ------------------------------------------------------------
SELECT 'division_monthly_legacy_material_duplicates_deleted' AS metric, COUNT(*) AS total
FROM tmp_division_monthly_material_duplicates_backup

UNION ALL

SELECT 'warehouse_monthly_legacy_material_duplicates_deleted', COUNT(*)
FROM tmp_warehouse_monthly_material_duplicates_backup

UNION ALL

SELECT 'division_monthly_item_linked_material_remaining', COUNT(*)
FROM inv_division_monthly_stock
WHERE COALESCE(stock_domain, 'ITEM') = 'MATERIAL'
  AND COALESCE(item_id, 0) > 0

UNION ALL

SELECT 'warehouse_monthly_item_linked_material_remaining', COUNT(*)
FROM inv_warehouse_monthly_stock
WHERE COALESCE(stock_domain, 'ITEM') = 'MATERIAL'
  AND COALESCE(item_id, 0) > 0;
