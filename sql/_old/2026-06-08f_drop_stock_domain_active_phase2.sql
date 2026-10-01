SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08f_drop_stock_domain_active_phase2.sql
-- Tujuan :
-- 1) Menghapus kolom stock_domain dari tabel stok aktif
-- 2) Aman dijalankan ulang walau sebagian tabel sudah tidak punya kolom
-- ============================================================

START TRANSACTION;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_adjustment_line' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_stock_adjustment_line DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_division_monthly_opening DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opname' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_division_monthly_opname DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_stock' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_division_monthly_stock DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_division_stock_opening_snapshot DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_stock_opening_snapshot DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_warehouse_monthly_opening DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opname' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_warehouse_monthly_opname DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_stock' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_warehouse_monthly_stock DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE inv_warehouse_stock_opening_snapshot DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stg_core_inventory_warehouse_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0, 'ALTER TABLE stg_core_inventory_warehouse_opening DROP COLUMN stock_domain', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'inv_stock_adjustment_line',
    'inv_division_monthly_opening',
    'inv_division_monthly_opname',
    'inv_division_monthly_stock',
    'inv_division_stock_opening_snapshot',
    'inv_stock_opening_snapshot',
    'inv_warehouse_monthly_opening',
    'inv_warehouse_monthly_opname',
    'inv_warehouse_monthly_stock',
    'inv_warehouse_stock_opening_snapshot',
    'stg_core_inventory_warehouse_opening'
  )
  AND COLUMN_NAME = 'stock_domain';
