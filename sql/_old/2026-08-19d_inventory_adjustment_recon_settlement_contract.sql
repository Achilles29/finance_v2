SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19d_inventory_adjustment_recon_settlement_contract.sql
-- Tujuan :
-- 1) Menyimpan pilihan operator untuk menyelesaikan defisit pada
--    Daily Recon component.
-- 2) Menyamakan kontrak bahan baku dan component: pilihan tersebut
--    dibaca kembali oleh writer saat draft diposting.
--
-- Catatan:
-- - Tidak mengubah stok, lot, movement, atau defisit lama.
-- - Aman dijalankan berulang.
-- - Jalankan setelah 2026-08-13a dan 2026-08-19a.
-- ============================================================

START TRANSACTION;

SET @schema_name := DATABASE();

SET @has_component_settle_open_deficit := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_component_adjustment_line'
    AND COLUMN_NAME = 'settle_open_deficit'
);

SET @sql := IF(
  @has_component_settle_open_deficit = 0,
  'ALTER TABLE inv_component_adjustment_line ADD COLUMN settle_open_deficit TINYINT(1) NOT NULL DEFAULT 0 AFTER physical_qty_snapshot',
  'SELECT ''skip inv_component_adjustment_line.settle_open_deficit'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('inv_stock_adjustment_line', 'inv_component_adjustment_line')
  AND COLUMN_NAME LIKE 'settle_open_deficit%'
ORDER BY TABLE_NAME, ORDINAL_POSITION;
