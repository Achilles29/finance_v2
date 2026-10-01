SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08g_cleanup_staging_stock_domain_legacy.sql
-- Tujuan :
-- 1) Membersihkan stock_domain='MATERIAL' pada staging opening gudang
-- 2) HANYA untuk row item-linked (item_id > 0)
-- 3) Aman dijalankan ulang walau kolom stock_domain sudah di-drop
-- ============================================================

START TRANSACTION;

SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stg_core_inventory_warehouse_opening'
    AND COLUMN_NAME = 'stock_domain'
);

DROP TEMPORARY TABLE IF EXISTS tmp_stg_opening_material_domain_backup;

SET @sql_create_backup := IF(
  @has_stock_domain > 0,
  'CREATE TEMPORARY TABLE tmp_stg_opening_material_domain_backup AS
   SELECT *
   FROM stg_core_inventory_warehouse_opening
   WHERE UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL''
     AND COALESCE(item_id, 0) > 0',
  'CREATE TEMPORARY TABLE tmp_stg_opening_material_domain_backup AS
   SELECT *
   FROM stg_core_inventory_warehouse_opening
   WHERE 1 = 0'
);
PREPARE stmt_create_backup FROM @sql_create_backup;
EXECUTE stmt_create_backup;
DEALLOCATE PREPARE stmt_create_backup;

SET @sql_cleanup := IF(
  @has_stock_domain > 0,
  'UPDATE stg_core_inventory_warehouse_opening
   SET stock_domain = NULL
   WHERE UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL''
     AND COALESCE(item_id, 0) > 0',
  'DO 0'
);
PREPARE stmt_cleanup FROM @sql_cleanup;
EXECUTE stmt_cleanup;
DEALLOCATE PREPARE stmt_cleanup;

COMMIT;

SELECT COUNT(*) AS cleaned_rows
FROM tmp_stg_opening_material_domain_backup;
