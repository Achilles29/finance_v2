SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19a_inventory_deficit_administrative_writeoff.sql
-- Tujuan :
-- 1) Menambah status penutupan administratif untuk defisit yang tidak
--    mungkin diselesaikan secara operasional, misalnya barang sudah tidak
--    digunakan lagi atau catatan historis lama setelah cut-off.
-- 2) Menjaga jejak audit tanpa mengubah stok, lot, movement, atau kas.
--
-- Catatan penting:
-- - VOID hanya untuk sumber transaksi yang dibatalkan/dibalik.
-- - WRITTEN_OFF bukan VOID dan bukan penerimaan barang.
-- - Nilai dan jumlah yang ditutup tetap disimpan pada kolom audit baru.
-- ============================================================

SET @schema_name := DATABASE();

ALTER TABLE inv_stock_deficit
  MODIFY COLUMN status ENUM('OPEN','SETTLED','VOID','WRITTEN_OFF') NOT NULL DEFAULT 'OPEN';

SET @has_written_off_qty := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_qty'
);
SET @sql := IF(
  @has_written_off_qty = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER reversed_qty',
  'SELECT ''skip written_off_qty'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_value := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_value'
);
SET @sql := IF(
  @has_written_off_value = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_value DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER estimated_total_value',
  'SELECT ''skip written_off_value'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_reason := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_reason_code'
);
SET @sql := IF(
  @has_written_off_reason = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_reason_code VARCHAR(50) NULL AFTER notes',
  'SELECT ''skip written_off_reason_code'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_notes := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_notes'
);
SET @sql := IF(
  @has_written_off_notes = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_notes VARCHAR(255) NULL AFTER written_off_reason_code',
  'SELECT ''skip written_off_notes'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_by'
);
SET @sql := IF(
  @has_written_off_by = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_by BIGINT UNSIGNED NULL AFTER written_off_notes',
  'SELECT ''skip written_off_by'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME = 'written_off_at'
);
SET @sql := IF(
  @has_written_off_at = 0,
  'ALTER TABLE inv_stock_deficit ADD COLUMN written_off_at DATETIME NULL AFTER written_off_by',
  'SELECT ''skip written_off_at'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Daily Recon creates a draft adjustment before posting it. Keep the
-- operator's explicit deficit-settlement confirmation on that draft so the
-- posting writer can execute it in the same database transaction.
SET @has_settle_open_deficit := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_adjustment_line' AND COLUMN_NAME = 'settle_open_deficit'
);
SET @sql := IF(
  @has_settle_open_deficit = 0,
  'ALTER TABLE inv_stock_adjustment_line ADD COLUMN settle_open_deficit TINYINT(1) NOT NULL DEFAULT 0 AFTER physical_qty_snapshot_content',
  'SELECT ''skip settle_open_deficit'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_settle_open_deficit_qty := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_adjustment_line' AND COLUMN_NAME = 'settle_open_deficit_qty_content'
);
SET @sql := IF(
  @has_settle_open_deficit_qty = 0,
  'ALTER TABLE inv_stock_adjustment_line ADD COLUMN settle_open_deficit_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER settle_open_deficit',
  'SELECT ''skip settle_open_deficit_qty_content'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_written_off_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'inv_stock_deficit' AND INDEX_NAME = 'idx_inv_stock_deficit_written_off'
);
SET @sql := IF(
  @has_written_off_index = 0,
  'ALTER TABLE inv_stock_deficit ADD KEY idx_inv_stock_deficit_written_off (status, written_off_at)',
  'SELECT ''skip idx_inv_stock_deficit_written_off'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'inv_stock_deficit' AND COLUMN_NAME IN (
      'status', 'written_off_qty', 'written_off_value', 'written_off_reason_code',
      'written_off_notes', 'written_off_by', 'written_off_at'
    ))
    OR
    (TABLE_NAME = 'inv_stock_adjustment_line' AND COLUMN_NAME IN (
      'settle_open_deficit', 'settle_open_deficit_qty_content'
    ))
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;
