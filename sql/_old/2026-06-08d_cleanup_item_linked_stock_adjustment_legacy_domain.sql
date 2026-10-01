SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08d_cleanup_item_linked_stock_adjustment_legacy_domain.sql
-- Tujuan :
-- 1) Membersihkan legacy stock_domain='MATERIAL' pada stock adjustment
--    yang sebenarnya sudah item-linked
-- 2) Menutup jalur adjustment sebagai sumber lahirnya mismatch baru
--
-- Prinsip:
-- - HANYA menyentuh row item-linked (item_id > 0)
-- - Kolom stock_domain dikosongkan menjadi NULL
-- - Tidak mengubah qty, cost, atau identity bisnis lain
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_adjustment_item_linked_material_domain_backup;
CREATE TEMPORARY TABLE tmp_adjustment_item_linked_material_domain_backup AS
SELECT *
FROM inv_stock_adjustment_line
WHERE UPPER(COALESCE(stock_domain, '')) = 'MATERIAL'
  AND COALESCE(item_id, 0) > 0;

UPDATE inv_stock_adjustment_line
SET stock_domain = NULL
WHERE UPPER(COALESCE(stock_domain, '')) = 'MATERIAL'
  AND COALESCE(item_id, 0) > 0;

COMMIT;

SELECT
  COUNT(*) AS cleaned_rows
FROM tmp_adjustment_item_linked_material_domain_backup;

SELECT
  id, adjustment_id, line_no, item_id, material_id, profile_key, profile_name, created_at
FROM tmp_adjustment_item_linked_material_domain_backup
ORDER BY id;
