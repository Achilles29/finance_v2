SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07a_item_centric_profile_key_collision_candidates.sql
-- Tujuan :
-- 1) Mendaftar profile_key yang masih punya jejak ganda ITEM/MATERIAL
-- 2) Menentukan kandidat phase 2 yang cocok diproses lewat tool
--    reclassify profile domain
-- 3) Memberi ringkasan tabel mana saja yang masih menyimpan collision
--
-- Catatan:
-- - Script ini TIDAK mengubah data apa pun
-- - Fokus pada profile_key, karena tool reclassify bekerja per profile_key
-- - Monthly stock ditampilkan sebagai konteks, tetapi merge phase 2
--   utama diarahkan dari snapshot/daily terlebih dahulu
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_profile_domain_collision_sources;
CREATE TEMPORARY TABLE tmp_profile_domain_collision_sources (
  source_table VARCHAR(100) NOT NULL,
  profile_key CHAR(64) NOT NULL,
  stock_domain VARCHAR(20) NOT NULL,
  row_count BIGINT NOT NULL DEFAULT 0,
  item_id BIGINT NULL,
  material_id BIGINT NULL
);

SET @table_name := 'inv_stock_movement_log';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_monthly_stock';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_monthly_stock';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_daily_rollup';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_daily_rollup';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_stock_opening_snapshot';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_stock_opening_snapshot';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_stock_opening_snapshot';
SET @has_profile_key := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'profile_key'
);
SET @has_stock_domain := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = @table_name
    AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_profile_key > 0 AND @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_profile_domain_collision_sources (source_table, profile_key, stock_domain, row_count, item_id, material_id) ',
    "SELECT '", @table_name, "', profile_key, stock_domain, COUNT(*) AS row_count, MAX(item_id) AS item_id, MAX(material_id) AS material_id ",
    'FROM ', @table_name, " WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> '' AND stock_domain IN ('ITEM','MATERIAL') ",
    'GROUP BY profile_key, stock_domain'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TEMPORARY TABLE IF EXISTS tmp_profile_domain_collision_keys;
CREATE TEMPORARY TABLE tmp_profile_domain_collision_keys AS
SELECT
  s.profile_key
FROM tmp_profile_domain_collision_sources s
GROUP BY s.profile_key
HAVING SUM(CASE WHEN s.stock_domain = 'ITEM' THEN 1 ELSE 0 END) > 0
   AND SUM(CASE WHEN s.stock_domain = 'MATERIAL' THEN 1 ELSE 0 END) > 0;

ALTER TABLE tmp_profile_domain_collision_keys
  ADD PRIMARY KEY (profile_key);

-- ------------------------------------------------------------
-- A. Ringkasan tabel mana saja yang masih punya profile collision
-- ------------------------------------------------------------
SELECT
  s.source_table,
  COUNT(DISTINCT s.profile_key) AS collision_profile_keys,
  SUM(CASE WHEN s.stock_domain = 'ITEM' THEN s.row_count ELSE 0 END) AS item_rows,
  SUM(CASE WHEN s.stock_domain = 'MATERIAL' THEN s.row_count ELSE 0 END) AS material_rows
FROM tmp_profile_domain_collision_sources s
JOIN tmp_profile_domain_collision_keys k ON k.profile_key = s.profile_key
GROUP BY s.source_table
ORDER BY s.source_table;

-- ------------------------------------------------------------
-- B. Daftar profile_key collision untuk phase 2
--    Ini kandidat terbaik untuk tool reclassify profile domain
-- ------------------------------------------------------------
SELECT
  k.profile_key,
  UPPER(TRIM(COALESCE(c.line_kind, ''))) AS catalog_line_kind,
  c.item_id AS catalog_item_id,
  c.material_id AS catalog_material_id,
  c.catalog_name,
  c.brand_name,
  COALESCE(item_map.material_id, c.material_id) AS inferred_material_id,
  SUM(CASE WHEN s.source_table = 'inv_stock_movement_log' AND s.stock_domain = 'MATERIAL' THEN s.row_count ELSE 0 END) AS movement_material_rows,
  SUM(CASE WHEN s.source_table = 'inv_division_monthly_stock' AND s.stock_domain = 'MATERIAL' THEN s.row_count ELSE 0 END) AS div_monthly_material_rows,
  SUM(CASE WHEN s.source_table = 'inv_warehouse_monthly_stock' AND s.stock_domain = 'MATERIAL' THEN s.row_count ELSE 0 END) AS wh_monthly_material_rows,
  SUM(CASE WHEN s.source_table = 'inv_division_daily_rollup' THEN s.row_count ELSE 0 END) AS div_daily_total_rows,
  SUM(CASE WHEN s.source_table = 'inv_warehouse_daily_rollup' THEN s.row_count ELSE 0 END) AS wh_daily_total_rows,
  SUM(CASE WHEN s.source_table = 'inv_stock_opening_snapshot' THEN s.row_count ELSE 0 END) AS unified_opening_total_rows,
  SUM(CASE WHEN s.source_table = 'inv_division_stock_opening_snapshot' THEN s.row_count ELSE 0 END) AS div_opening_total_rows,
  SUM(CASE WHEN s.source_table = 'inv_warehouse_stock_opening_snapshot' THEN s.row_count ELSE 0 END) AS wh_opening_total_rows
FROM tmp_profile_domain_collision_keys k
LEFT JOIN mst_purchase_catalog c ON c.profile_key = k.profile_key
LEFT JOIN mst_item item_map ON item_map.id = c.item_id
LEFT JOIN tmp_profile_domain_collision_sources s ON s.profile_key = k.profile_key
GROUP BY
  k.profile_key,
  UPPER(TRIM(COALESCE(c.line_kind, ''))),
  c.item_id,
  c.material_id,
  c.catalog_name,
  c.brand_name,
  COALESCE(item_map.material_id, c.material_id)
ORDER BY
  movement_material_rows DESC,
  div_monthly_material_rows DESC,
  wh_monthly_material_rows DESC,
  k.profile_key
LIMIT 300;

-- ------------------------------------------------------------
-- C. Summary singkat
-- ------------------------------------------------------------
SELECT 'collision_profile_keys_total' AS metric, COUNT(*) AS total
FROM tmp_profile_domain_collision_keys

UNION ALL

SELECT 'collision_profiles_with_catalog_item_target', COUNT(*)
FROM tmp_profile_domain_collision_keys k
JOIN mst_purchase_catalog c ON c.profile_key = k.profile_key
WHERE UPPER(TRIM(COALESCE(c.line_kind, ''))) = 'ITEM'
  AND c.item_id IS NOT NULL

UNION ALL

SELECT 'collision_profiles_with_catalog_material_target', COUNT(*)
FROM tmp_profile_domain_collision_keys k
JOIN mst_purchase_catalog c ON c.profile_key = k.profile_key
WHERE UPPER(TRIM(COALESCE(c.line_kind, ''))) = 'MATERIAL'
  AND COALESCE(c.material_id, 0) > 0;
