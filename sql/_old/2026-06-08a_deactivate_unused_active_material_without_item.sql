SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08a_deactivate_unused_active_material_without_item.sql
-- Tujuan :
-- 1) Menonaktifkan material aktif yang tidak punya item aktif
-- 2) HANYA untuk material yang benar-benar tidak dipakai di catalog,
--    transaksi, movement, dan snapshot/monthly stok
-- 3) Membersihkan blocker kecil sebelum phase hapus MATERIAL lebih keras
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_unused_active_material_without_item;
CREATE TEMPORARY TABLE tmp_unused_active_material_without_item AS
SELECT
  m.id,
  m.material_code,
  m.material_name
FROM mst_material m
WHERE COALESCE(m.is_active, 1) = 1
  AND NOT EXISTS (
    SELECT 1
    FROM mst_item i
    WHERE i.material_id = m.id
      AND COALESCE(i.is_active, 1) = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM mst_purchase_catalog c WHERE COALESCE(c.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM pur_division_request_line x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM pur_store_request_line x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM pur_purchase_order_line x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM pur_purchase_receipt_line x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM inv_stock_movement_log x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM inv_division_monthly_stock x WHERE COALESCE(x.material_id, 0) = m.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM inv_warehouse_monthly_stock x WHERE COALESCE(x.material_id, 0) = m.id
  );

DROP TEMPORARY TABLE IF EXISTS tmp_unused_active_material_without_item_backup;
CREATE TEMPORARY TABLE tmp_unused_active_material_without_item_backup AS
SELECT m.*
FROM mst_material m
JOIN tmp_unused_active_material_without_item t ON t.id = m.id;

UPDATE mst_material m
JOIN tmp_unused_active_material_without_item t ON t.id = m.id
SET m.is_active = 0;

COMMIT;

SELECT
  id,
  material_code,
  material_name
FROM tmp_unused_active_material_without_item_backup
ORDER BY id;

