SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-27d_drop_component_category_parent.sql
-- Tujuan : Menghapus parent_id dari mst_component_category karena
--          hierarki parent category tidak lagi dipakai di finance.
-- Catatan: Aman di-run berulang.
-- ============================================================

START TRANSACTION;

SET @schema_name := DATABASE();

SET @fk_name := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_component_category'
    AND COLUMN_NAME = 'parent_id'
    AND REFERENCED_TABLE_NAME = 'mst_component_category'
  LIMIT 1
);
SET @drop_fk_sql := IF(
  @fk_name IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE mst_component_category DROP FOREIGN KEY `', @fk_name, '`')
);
PREPARE stmt FROM @drop_fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_name := (
  SELECT INDEX_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_component_category'
    AND COLUMN_NAME = 'parent_id'
    AND INDEX_NAME <> 'PRIMARY'
  LIMIT 1
);
SET @drop_idx_sql := IF(
  @idx_name IS NULL,
  'SELECT 1',
  CONCAT('ALTER TABLE mst_component_category DROP INDEX `', @idx_name, '`')
);
PREPARE stmt FROM @drop_idx_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_parent_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'mst_component_category'
    AND COLUMN_NAME = 'parent_id'
);
SET @drop_col_sql := IF(
  @has_parent_col > 0,
  'ALTER TABLE mst_component_category DROP COLUMN parent_id',
  'SELECT 1'
);
PREPARE stmt FROM @drop_col_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;