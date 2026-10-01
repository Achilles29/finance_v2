SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08e_audit_stock_domain_drop_readiness.sql
-- Tujuan :
-- 1) Memastikan tabel aktif siap untuk drop kolom stock_domain
-- 2) Aman dijalankan ulang walau sebagian tabel sudah tidak punya kolom
-- 3) Memastikan tidak ada nilai MATERIAL tersisa dan tidak ada bentrok
--    identity pada unique key baru
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_stock_domain_readiness_material_rows;
CREATE TEMPORARY TABLE tmp_stock_domain_readiness_material_rows (
  table_name VARCHAR(128) NOT NULL,
  material_rows BIGINT NULL
);

-- ------------------------------------------------------------
-- A. Material rows tersisa per tabel aktif
-- ------------------------------------------------------------
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_adjustment_line' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_stock_adjustment_line'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_stock_adjustment_line',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_stock_adjustment_line'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_division_monthly_opening'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_division_monthly_opening',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_division_monthly_opening'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opname' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_division_monthly_opname'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_division_monthly_opname',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_division_monthly_opname'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_stock' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_division_monthly_stock'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_division_monthly_stock',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_division_monthly_stock'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_division_stock_opening_snapshot'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_division_stock_opening_snapshot',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_division_stock_opening_snapshot'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_stock_opening_snapshot'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_stock_opening_snapshot',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_stock_opening_snapshot'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_warehouse_monthly_opening'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_warehouse_monthly_opening',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_warehouse_monthly_opening'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opname' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_warehouse_monthly_opname'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_warehouse_monthly_opname',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_warehouse_monthly_opname'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_stock' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_warehouse_monthly_stock'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_warehouse_monthly_stock',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_warehouse_monthly_stock'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_stock_opening_snapshot' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''inv_warehouse_stock_opening_snapshot'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM inv_warehouse_stock_opening_snapshot',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''inv_warehouse_stock_opening_snapshot'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stg_core_inventory_warehouse_opening' AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(@has_col > 0,
  'INSERT INTO tmp_stock_domain_readiness_material_rows SELECT ''stg_core_inventory_warehouse_opening'', SUM(CASE WHEN UPPER(COALESCE(stock_domain, '''')) = ''MATERIAL'' THEN 1 ELSE 0 END) FROM stg_core_inventory_warehouse_opening',
  'INSERT INTO tmp_stock_domain_readiness_material_rows VALUES (''stg_core_inventory_warehouse_opening'', NULL)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT table_name, material_rows
FROM tmp_stock_domain_readiness_material_rows
ORDER BY table_name;

-- ------------------------------------------------------------
-- B. Cek bentrok identity bila stock_domain diabaikan
-- ------------------------------------------------------------
SELECT 'inv_division_monthly_opname' AS table_name, COUNT(*) AS conflict_groups
FROM (
  SELECT month_key, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key, COUNT(*) AS c
  FROM inv_division_monthly_opname
  GROUP BY month_key, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'inv_division_stock_opening_snapshot', COUNT(*)
FROM (
  SELECT snapshot_month, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key, COUNT(*) AS c
  FROM inv_division_stock_opening_snapshot
  GROUP BY snapshot_month, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'inv_stock_opening_snapshot', COUNT(*)
FROM (
  SELECT snapshot_month, stock_scope, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key, COUNT(*) AS c
  FROM inv_stock_opening_snapshot
  GROUP BY snapshot_month, stock_scope, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'inv_warehouse_monthly_opname', COUNT(*)
FROM (
  SELECT month_key, item_id, material_id, buy_uom_id, content_uom_id, profile_key, COUNT(*) AS c
  FROM inv_warehouse_monthly_opname
  GROUP BY month_key, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'inv_warehouse_stock_opening_snapshot', COUNT(*)
FROM (
  SELECT snapshot_month, item_id, material_id, buy_uom_id, content_uom_id, profile_key, COUNT(*) AS c
  FROM inv_warehouse_stock_opening_snapshot
  GROUP BY snapshot_month, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  HAVING COUNT(*) > 1
) x;

-- ------------------------------------------------------------
-- C. Kolom stock_domain aktif yang masih hidup
-- ------------------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'inv_stock_adjustment_line',
    'inv_division_monthly_opening',
    'inv_division_monthly_opname',
    'inv_division_monthly_stock',
    'inv_division_stock_opening_snapshot',
    'inv_stock_opening_snapshot',
    'inv_warehouse_monthly_opening',
    'inv_warehouse_monthly_opname',
    'inv_warehouse_monthly_stock',
    'inv_warehouse_stock_opening_snapshot',
    'stg_core_inventory_warehouse_opening'
  )
  AND COLUMN_NAME = 'stock_domain'
ORDER BY TABLE_NAME;
