SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08b_make_stock_domain_passive_phase1.sql
-- Tujuan :
-- 1) Menjadikan stock_domain pasif pada tabel stok aktif
-- 2) Menghapus stock_domain dari unique index pembentuk identity
-- 3) Membuat kolom stock_domain aktif menjadi nullable/default NULL
--    agar writer baru tidak lagi dipaksa menulis ITEM/MATERIAL
--
-- Catatan:
-- - Script ini BELUM drop kolom stock_domain
-- - Script ini fokus pada tabel aktif, bukan backup/legacy table
-- - Jalankan setelah codepath item-centric aktif terdeploy
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Lepas stock_domain dari unique index identity aktif
-- ------------------------------------------------------------
ALTER TABLE inv_division_monthly_opname
  DROP INDEX uk_inv_div_opname_profile_month,
  ADD UNIQUE KEY uk_inv_div_opname_profile_month (
    month_key, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

ALTER TABLE inv_division_stock_opening_snapshot
  DROP INDEX uk_inv_div_opening_profile_month,
  ADD UNIQUE KEY uk_inv_div_opening_profile_month (
    snapshot_month, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

ALTER TABLE inv_stock_opening_snapshot
  DROP INDEX uk_inv_opening_scope_profile_month,
  ADD UNIQUE KEY uk_inv_opening_scope_profile_month (
    snapshot_month, stock_scope, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

ALTER TABLE inv_warehouse_monthly_opname
  DROP INDEX uk_inv_wh_opname_profile_month,
  ADD UNIQUE KEY uk_inv_wh_opname_profile_month (
    month_key, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

ALTER TABLE inv_warehouse_stock_opening_snapshot
  DROP INDEX uk_inv_wh_opening_profile_month,
  ADD UNIQUE KEY uk_inv_wh_opening_profile_month (
    snapshot_month, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

-- ------------------------------------------------------------
-- B. Buat stock_domain aktif menjadi nullable/default NULL
-- ------------------------------------------------------------
ALTER TABLE inv_division_monthly_opening
  MODIFY stock_domain enum('ITEM','MATERIAL') NULL DEFAULT NULL;

ALTER TABLE inv_warehouse_monthly_opening
  MODIFY stock_domain enum('ITEM','MATERIAL') NULL DEFAULT NULL;

ALTER TABLE inv_stock_adjustment_line
  MODIFY stock_domain enum('ITEM','MATERIAL') NULL DEFAULT NULL;

ALTER TABLE stg_core_inventory_warehouse_opening
  MODIFY stock_domain enum('ITEM','MATERIAL') NULL DEFAULT NULL;

COMMIT;

-- ------------------------------------------------------------
-- C. Verifikasi
-- ------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'inv_division_monthly_opening',
    'inv_warehouse_monthly_opening',
    'inv_stock_adjustment_line',
    'stg_core_inventory_warehouse_opening'
  )
  AND COLUMN_NAME = 'stock_domain'
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'inv_division_monthly_opname',
    'inv_division_stock_opening_snapshot',
    'inv_stock_opening_snapshot',
    'inv_warehouse_monthly_opname',
    'inv_warehouse_stock_opening_snapshot'
  )
  AND INDEX_NAME IN (
    'uk_inv_div_opname_profile_month',
    'uk_inv_div_opening_profile_month',
    'uk_inv_opening_scope_profile_month',
    'uk_inv_wh_opname_profile_month',
    'uk_inv_wh_opening_profile_month'
  )
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
