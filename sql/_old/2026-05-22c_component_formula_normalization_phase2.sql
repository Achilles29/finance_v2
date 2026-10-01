SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-22c_component_formula_normalization_phase2.sql
-- Tujuan :
-- 1) Normalisasi formula: sumber MATERIAL pakai mst_material (material_id)
-- 2) Drop kolom uom_id dari mst_component_formula (UOM diturunkan dari sumber)
-- 3) Menjaga kompatibilitas migrasi (idempotent + backfill aman)
-- ============================================================

START TRANSACTION;

-- A. tambah kolom material_id (jika belum ada)
SET @has_material_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND COLUMN_NAME = 'material_id'
);
SET @sql_add_material_id := IF(
  @has_material_id = 0,
  'ALTER TABLE mst_component_formula ADD COLUMN material_id BIGINT UNSIGNED NULL AFTER line_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_material_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- B. backfill material_id dari material_item_id -> mst_item.material_id (jika legacy column ada)
SET @has_material_item_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND COLUMN_NAME = 'material_item_id'
);
SET @sql_backfill := IF(
  @has_material_item_id > 0,
  'UPDATE mst_component_formula f
   JOIN mst_item i ON i.id = f.material_item_id
   SET f.material_id = i.material_id
   WHERE f.line_type = ''MATERIAL''
     AND f.material_id IS NULL
     AND i.material_id IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_backfill; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- C. index + FK material_id
SET @has_idx_material := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND INDEX_NAME = 'idx_mst_component_formula_material'
);
SET @sql_add_idx_material := IF(
  @has_idx_material = 0,
  'ALTER TABLE mst_component_formula ADD KEY idx_mst_component_formula_material (material_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_idx_material; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_material := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND CONSTRAINT_NAME = 'fk_mst_component_formula_material'
);
SET @sql_add_fk_material := IF(
  @has_fk_material = 0,
  'ALTER TABLE mst_component_formula
     ADD CONSTRAINT fk_mst_component_formula_material
     FOREIGN KEY (material_id) REFERENCES mst_material(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fk_material; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- D. drop FK lama ke mst_uom + drop kolom uom_id
SET @has_fk_uom := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND CONSTRAINT_NAME = 'fk_mst_component_formula_uom'
);
SET @sql_drop_fk_uom := IF(
  @has_fk_uom > 0,
  'ALTER TABLE mst_component_formula DROP FOREIGN KEY fk_mst_component_formula_uom',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_fk_uom; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_uom := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND INDEX_NAME = 'idx_mst_component_formula_uom'
);
SET @sql_drop_idx_uom := IF(
  @has_idx_uom > 0,
  'ALTER TABLE mst_component_formula DROP INDEX idx_mst_component_formula_uom',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_idx_uom; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col_uom := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_component_formula'
    AND COLUMN_NAME = 'uom_id'
);
SET @sql_drop_col_uom := IF(
  @has_col_uom > 0,
  'ALTER TABLE mst_component_formula DROP COLUMN uom_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_drop_col_uom; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- Quick check
SELECT
  COUNT(*) AS total_formula,
  SUM(CASE WHEN line_type='MATERIAL' AND material_id IS NULL THEN 1 ELSE 0 END) AS material_unmapped,
  SUM(CASE WHEN line_type='COMPONENT' AND sub_component_id IS NULL THEN 1 ELSE 0 END) AS component_unmapped
FROM mst_component_formula;
