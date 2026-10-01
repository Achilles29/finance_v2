SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30g_pos_order_table_no_nullable.sql
-- Tujuan :
-- 1) Menambah kolom nomor meja pada pos_order
-- 2) Nomor meja boleh teks/angka dan nullable
-- 3) Aman dijalankan ulang
-- ============================================================

START TRANSACTION;

SET @has_pos_order := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
);

SET @has_table_no := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
    AND COLUMN_NAME = 'table_no'
);

SET @sql_add_table_no := IF(
  @has_pos_order = 1 AND @has_table_no = 0,
  'ALTER TABLE pos_order ADD COLUMN table_no VARCHAR(40) NULL AFTER guest_count',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_table_no; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_order'
  AND COLUMN_NAME = 'table_no';
