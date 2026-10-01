SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-04g_item_centric_nullable_legacy_enum_columns.sql
-- Tujuan :
-- 1) Menjadikan kolom enum legacy line_kind / stock_domain tidak wajib diisi
-- 2) Mendukung write path item-centric tanpa lagi menulis ITEM/MATERIAL
-- 3) TIDAK menghapus kolom; hanya memindahkan kolom ke mode compatibility
--
-- Catatan:
-- - Aman dijalankan ulang
-- - Tidak mengeksekusi perubahan data historis
-- - Aplikasi sudah dipatch untuk menulis NULL bila schema mengizinkan
-- ============================================================

START TRANSACTION;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mst_purchase_catalog' AND COLUMN_NAME = 'line_kind' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `mst_purchase_catalog` MODIFY COLUMN `line_kind` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mst_purchase_catalog' AND COLUMN_NAME = 'line_kind'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_purchase_order_line' AND COLUMN_NAME = 'line_kind' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `pur_purchase_order_line` MODIFY COLUMN `line_kind` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_purchase_order_line' AND COLUMN_NAME = 'line_kind'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_purchase_receipt_line' AND COLUMN_NAME = 'line_kind' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `pur_purchase_receipt_line` MODIFY COLUMN `line_kind` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_purchase_receipt_line' AND COLUMN_NAME = 'line_kind'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_store_request_line' AND COLUMN_NAME = 'line_kind' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `pur_store_request_line` MODIFY COLUMN `line_kind` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_store_request_line' AND COLUMN_NAME = 'line_kind'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_division_request_line' AND COLUMN_NAME = 'line_kind' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `pur_division_request_line` MODIFY COLUMN `line_kind` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pur_division_request_line' AND COLUMN_NAME = 'line_kind'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_movement_log' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_stock_movement_log` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_movement_log' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_stock' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_warehouse_monthly_stock` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_stock' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_stock' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_division_monthly_stock` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_stock' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_daily_rollup' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_warehouse_daily_rollup` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_daily_rollup' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_daily_rollup' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_division_daily_rollup` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_daily_rollup' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opname' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_warehouse_monthly_opname` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opname' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opname' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_division_monthly_opname` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opname' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_stock_opening_snapshot` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_warehouse_stock_opening_snapshot` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain' AND IS_NULLABLE <> 'YES'
    ),
    CONCAT(
      'ALTER TABLE `inv_division_stock_opening_snapshot` MODIFY COLUMN `stock_domain` ',
      (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'),
      ' NULL DEFAULT NULL'
    ),
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'mst_purchase_catalog' AND COLUMN_NAME = 'line_kind')
    OR (TABLE_NAME = 'pur_purchase_order_line' AND COLUMN_NAME = 'line_kind')
    OR (TABLE_NAME = 'pur_purchase_receipt_line' AND COLUMN_NAME = 'line_kind')
    OR (TABLE_NAME = 'pur_store_request_line' AND COLUMN_NAME = 'line_kind')
    OR (TABLE_NAME = 'pur_division_request_line' AND COLUMN_NAME = 'line_kind')
    OR (TABLE_NAME = 'inv_stock_movement_log' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_warehouse_monthly_stock' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_division_monthly_stock' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_warehouse_daily_rollup' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_division_daily_rollup' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_warehouse_monthly_opname' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_division_monthly_opname' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_warehouse_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain')
    OR (TABLE_NAME = 'inv_division_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain')
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
