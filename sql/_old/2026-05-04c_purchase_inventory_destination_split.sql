-- Split stok divisi menjadi per tujuan (Reguler/Event) dan simpan dimensi tujuan di ledger.
-- Jalankan sekali pada database finance.

START TRANSACTION;

SET @db_name = DATABASE();

-- ------------------------------------------------------------
-- 1) Tambah kolom destination_type jika belum ada
-- ------------------------------------------------------------
SET @exists_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_stock_balance'
    AND COLUMN_NAME = 'destination_type'
);
SET @sql := IF(
  @exists_col = 0,
  "ALTER TABLE inv_division_stock_balance ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER' AFTER division_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_daily_rollup'
    AND COLUMN_NAME = 'destination_type'
);
SET @sql := IF(
  @exists_col = 0,
  "ALTER TABLE inv_division_daily_rollup ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER' AFTER division_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_movement_log'
    AND COLUMN_NAME = 'destination_type'
);
SET @sql := IF(
  @exists_col = 0,
  "ALTER TABLE inv_stock_movement_log ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL AFTER division_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) Normalisasi data existing
-- ------------------------------------------------------------
UPDATE inv_division_stock_balance
SET destination_type = 'OTHER'
WHERE destination_type IS NULL OR destination_type = '';

UPDATE inv_division_daily_rollup
SET destination_type = 'OTHER'
WHERE destination_type IS NULL OR destination_type = '';

UPDATE inv_stock_movement_log
SET destination_type = 'OTHER'
WHERE movement_scope = 'DIVISION'
  AND (destination_type IS NULL OR destination_type = '');

-- ------------------------------------------------------------
-- 3) Ubah unique key agar satu divisi bisa punya saldo REGULER + EVENT
-- ------------------------------------------------------------
SET @old_idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_stock_balance'
    AND INDEX_NAME = 'uk_inv_div_stock_scope'
);
SET @sql := IF(
  @old_idx_exists > 0,
  'ALTER TABLE inv_division_stock_balance DROP INDEX uk_inv_div_stock_scope, ADD UNIQUE KEY uk_inv_div_stock_scope (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @old_idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_daily_rollup'
    AND INDEX_NAME = 'uk_inv_div_daily_profile_day'
);
SET @sql := IF(
  @old_idx_exists > 0,
  'ALTER TABLE inv_division_daily_rollup DROP INDEX uk_inv_div_daily_profile_day, ADD UNIQUE KEY uk_inv_div_daily_profile_day (movement_date, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 4) Tambah index bantu filter tujuan
-- ------------------------------------------------------------
SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_stock_balance'
    AND INDEX_NAME = 'idx_inv_div_stock_destination'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE inv_division_stock_balance ADD KEY idx_inv_div_stock_destination (destination_type)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_division_daily_rollup'
    AND INDEX_NAME = 'idx_inv_div_daily_destination'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE inv_division_daily_rollup ADD KEY idx_inv_div_daily_destination (destination_type)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_movement_log'
    AND INDEX_NAME = 'idx_inv_stock_movement_destination'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE inv_stock_movement_log ADD KEY idx_inv_stock_movement_destination (movement_scope, division_id, destination_type, movement_date)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'inv_division_stock_balance' AS table_name, COUNT(*) AS total_rows FROM inv_division_stock_balance
UNION ALL
SELECT 'inv_division_daily_rollup', COUNT(*) FROM inv_division_daily_rollup
UNION ALL
SELECT 'inv_stock_movement_log', COUNT(*) FROM inv_stock_movement_log;
