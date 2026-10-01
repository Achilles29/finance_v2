-- Tambah kolom lot_id ke inv_component_stock_opname
-- lot_id = 0  → opname level komponen (perilaku lama)
-- lot_id > 0  → opname level lot spesifik
-- Jalankan sekali saja.

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME  = 'inv_component_stock_opname'
      AND COLUMN_NAME = 'lot_id'
);
SET @add_col = IF(@col_exists = 0,
    'ALTER TABLE inv_component_stock_opname ADD COLUMN lot_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER uom_id',
    'SELECT "lot_id column already exists"'
);
PREPARE stmt FROM @add_col; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rebuild unique key agar mencakup lot_id
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME  = 'inv_component_stock_opname'
      AND INDEX_NAME  = 'uk_component_stock_opname'
);
SET @drop_idx = IF(@idx_exists > 0,
    'ALTER TABLE inv_component_stock_opname DROP INDEX uk_component_stock_opname',
    'SELECT "index not found, skip drop"'
);
PREPARE stmt FROM @drop_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Buat ulang index dengan lot_id
ALTER TABLE inv_component_stock_opname
  ADD UNIQUE KEY uk_component_stock_opname
    (opname_date, location_type, division_id, component_id, uom_id, lot_id);
