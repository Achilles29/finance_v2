SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-22a_inventory_void_reversal_movement_link_foundation.sql
-- Tujuan :
-- 1) Menautkan setiap movement pembatalan ke movement asalnya
-- 2) Menambah tipe VOID_OUT untuk membatalkan inbound component
-- 3) Menautkan pembatalan mutasi rekening ke mutasi asalnya
-- 4) Menjaga VOID sebagai audit, bukan penghapusan histori
--
-- Catatan:
-- - Script ini hanya menambah metadata/schema.
-- - Tidak mengubah stok, lot, HPP, transaksi, maupun nilai keuangan lama.
-- - Jalankan sebelum deploy refactor writer VOID/reversal.
-- ============================================================

START TRANSACTION;

SET @schema_name := DATABASE();

-- Material movement: tautan langsung ke movement asal untuk audit dan
-- pembatasan reversal parsial agar tidak dapat dibalik dua kali.
SET @has_material_log := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_stock_movement_log'
);
SET @has_material_reversal_link := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_stock_movement_log'
    AND COLUMN_NAME = 'reversal_of_movement_id'
);
SET @sql := IF(
  @has_material_log = 1 AND @has_material_reversal_link = 0,
  'ALTER TABLE inv_stock_movement_log ADD COLUMN reversal_of_movement_id BIGINT UNSIGNED NULL AFTER ref_id',
  "SELECT 'skip material reversal link' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_material_reversal_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_stock_movement_log'
    AND INDEX_NAME = 'idx_inv_stock_movement_reversal'
);
SET @sql := IF(
  @has_material_log = 1 AND @has_material_reversal_idx = 0,
  'ALTER TABLE inv_stock_movement_log ADD KEY idx_inv_stock_movement_reversal (reversal_of_movement_id, movement_date)',
  "SELECT 'skip material reversal index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Component movement uses the same audit link. VOID_OUT is needed when a
-- posted production batch creates an inbound component, then is voided.
SET @has_component_log := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_component_movement_log'
);
SET @has_component_reversal_link := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_component_movement_log'
    AND COLUMN_NAME = 'reversal_of_movement_id'
);
SET @sql := IF(
  @has_component_log = 1 AND @has_component_reversal_link = 0,
  'ALTER TABLE inv_component_movement_log ADD COLUMN reversal_of_movement_id BIGINT UNSIGNED NULL AFTER source_line_id',
  "SELECT 'skip component reversal link' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_component_reversal_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_component_movement_log'
    AND INDEX_NAME = 'idx_inv_component_movement_reversal'
);
SET @sql := IF(
  @has_component_log = 1 AND @has_component_reversal_idx = 0,
  'ALTER TABLE inv_component_movement_log ADD KEY idx_inv_component_movement_reversal (reversal_of_movement_id, movement_date)',
  "SELECT 'skip component reversal index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @component_movement_type := (
  SELECT COLUMN_TYPE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_component_movement_log'
    AND COLUMN_NAME = 'movement_type'
  LIMIT 1
);
SET @sql := IF(
  @has_component_log = 1 AND LOCATE("'VOID_OUT'", COALESCE(@component_movement_type, '')) = 0,
  "ALTER TABLE inv_component_movement_log MODIFY COLUMN movement_type ENUM('OPENING','PRODUCTION_IN','PRODUCTION_OUT','TRANSFER_IN','TRANSFER_OUT','USAGE','WASTE','SPOIL','ADJUSTMENT_PLUS','ADJUSTMENT_MINUS','VOID_REVERSE','VOID_OUT') NOT NULL",
  "SELECT 'skip component VOID_OUT enum' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mutasi rekening memakai tautan yang sama. Dengan pasangan ini, laporan
-- operasional dapat mengabaikan transaksi yang sudah dibatalkan tanpa
-- menghapus jejak kas asli dan jejak pengembaliannya.
SET @has_account_mutation_log := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'fin_account_mutation_log'
);
SET @has_account_reversal_link := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'fin_account_mutation_log'
    AND COLUMN_NAME = 'reversal_of_mutation_id'
);
SET @sql := IF(
  @has_account_mutation_log = 1 AND @has_account_reversal_link = 0,
  'ALTER TABLE fin_account_mutation_log ADD COLUMN reversal_of_mutation_id BIGINT UNSIGNED NULL AFTER ref_id',
  "SELECT 'skip account mutation reversal link' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_account_reversal_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'fin_account_mutation_log'
    AND INDEX_NAME = 'idx_fin_account_mutation_reversal'
);
SET @sql := IF(
  @has_account_mutation_log = 1 AND @has_account_reversal_idx = 0,
  'ALTER TABLE fin_account_mutation_log ADD KEY idx_fin_account_mutation_reversal (reversal_of_mutation_id, mutation_date)',
  "SELECT 'skip account mutation reversal index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'inv_stock_movement_log.reversal_of_movement_id' AS metric, COUNT(*) AS total
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'inv_stock_movement_log'
  AND COLUMN_NAME = 'reversal_of_movement_id'
UNION ALL
SELECT 'inv_component_movement_log.reversal_of_movement_id', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'inv_component_movement_log'
  AND COLUMN_NAME = 'reversal_of_movement_id'
UNION ALL
SELECT 'inv_component_movement_log.VOID_OUT', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'inv_component_movement_log'
  AND COLUMN_NAME = 'movement_type'
  AND LOCATE("'VOID_OUT'", COLUMN_TYPE) > 0
UNION ALL
SELECT 'fin_account_mutation_log.reversal_of_mutation_id', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'fin_account_mutation_log'
  AND COLUMN_NAME = 'reversal_of_mutation_id';
