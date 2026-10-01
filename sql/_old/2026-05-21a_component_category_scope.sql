SET NAMES utf8mb4;

-- ============================================================
-- Tahap 8B - Revisi mst_component_category (Scope Base/Prepare)
-- File   : 2026-05-21a_component_category_scope.sql
-- Tujuan :
-- 1) Menambah pembeda kategori BASE/PREPARE/ALL
-- 2) Menjaga kompatibilitas data lama
-- ============================================================

START TRANSACTION;

SET @has_scope_type := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_category'
    AND COLUMN_NAME = 'scope_type'
);

SET @sql := IF(
  @has_scope_type = 0,
  "ALTER TABLE mst_component_category ADD COLUMN scope_type ENUM('BASE','PREPARE','ALL') NOT NULL DEFAULT 'ALL' AFTER name",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE mst_component_category
SET scope_type = 'ALL'
WHERE scope_type IS NULL OR scope_type = '';

SET @has_idx_scope_type := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_category'
    AND INDEX_NAME = 'idx_mst_component_category_scope_type'
);

SET @sql := IF(
  @has_idx_scope_type = 0,
  'ALTER TABLE mst_component_category ADD KEY idx_mst_component_category_scope_type (scope_type)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

SELECT id, code, name, scope_type, parent_id, is_active
FROM mst_component_category
ORDER BY sort_order ASC, name ASC
LIMIT 50;
