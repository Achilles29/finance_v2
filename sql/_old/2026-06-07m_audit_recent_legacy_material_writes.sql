SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07m_audit_recent_legacy_material_writes.sql
-- Tujuan :
-- 1) Mengaudit apakah masih ada write baru yang menulis legacy
--    MATERIAL / line_kind MATERIAL pada jalur transaksi aktif
-- 2) Tahan terhadap schema server yang belum seragam
-- 3) Membedakan sisa legacy lama vs kebocoran write baru
-- ============================================================

SET @date_from := DATE_SUB(CURDATE(), INTERVAL 30 DAY);
SET @date_from_sql := QUOTE(DATE_FORMAT(@date_from, '%Y-%m-%d'));
SET @month_from_sql := QUOTE(DATE_FORMAT(@date_from, '%Y-%m-01'));

DROP TEMPORARY TABLE IF EXISTS tmp_recent_legacy_write_summary;
CREATE TEMPORARY TABLE tmp_recent_legacy_write_summary (
  table_name VARCHAR(100) NOT NULL,
  total BIGINT NOT NULL DEFAULT 0
);

SET @table_name := 'mst_purchase_catalog';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_item_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'item_id'
);
SET @sql := IF(
  @has_line_kind > 0 AND @has_item_id > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(item_id,0) > 0",
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'pur_purchase_order_line';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_updated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'updated_at'
);
SET @has_created_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
  @has_line_kind > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(",
    IF(@has_updated_at > 0, 'updated_at, ', ''),
    IF(@has_created_at > 0, 'created_at, ', ''),
    "NOW()) >= ", @date_from_sql,
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'pur_purchase_receipt_line';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_updated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'updated_at'
);
SET @has_created_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
  @has_line_kind > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(",
    IF(@has_updated_at > 0, 'updated_at, ', ''),
    IF(@has_created_at > 0, 'created_at, ', ''),
    "NOW()) >= ", @date_from_sql,
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'pur_store_request_line';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_updated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'updated_at'
);
SET @has_created_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
  @has_line_kind > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(",
    IF(@has_updated_at > 0, 'updated_at, ', ''),
    IF(@has_created_at > 0, 'created_at, ', ''),
    "NOW()) >= ", @date_from_sql,
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'pur_division_request_line';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_updated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'updated_at'
);
SET @has_created_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
  @has_line_kind > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(",
    IF(@has_updated_at > 0, 'updated_at, ', ''),
    IF(@has_created_at > 0, 'created_at, ', ''),
    "NOW()) >= ", @date_from_sql,
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'pur_store_request_fulfillment_line';
SET @has_line_kind := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'line_kind'
);
SET @has_updated_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'updated_at'
);
SET @has_created_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(
  @has_line_kind > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE COALESCE(",
    IF(@has_updated_at > 0, 'updated_at, ', ''),
    IF(@has_created_at > 0, 'created_at, ', ''),
    "NOW()) >= ", @date_from_sql,
    " AND UPPER(COALESCE(line_kind,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_stock_movement_log';
SET @has_stock_domain := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE movement_date >= ", @date_from_sql,
    " AND UPPER(COALESCE(stock_domain,'ITEM')) = 'MATERIAL'"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_division_monthly_stock';
SET @has_stock_domain := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE month_key >= ", @month_from_sql,
    " AND UPPER(COALESCE(stock_domain,'ITEM')) = 'MATERIAL'",
    " AND COALESCE(item_id,0) > 0"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name := 'inv_warehouse_monthly_stock';
SET @has_stock_domain := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'stock_domain'
);
SET @sql := IF(
  @has_stock_domain > 0,
  CONCAT(
    'INSERT INTO tmp_recent_legacy_write_summary ',
    "SELECT '", @table_name, "', COUNT(*) FROM ", @table_name,
    " WHERE month_key >= ", @month_from_sql,
    " AND UPPER(COALESCE(stock_domain,'ITEM')) = 'MATERIAL'",
    " AND COALESCE(item_id,0) > 0"
  ),
  CONCAT("INSERT INTO tmp_recent_legacy_write_summary SELECT '", @table_name, "', 0")
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT * FROM tmp_recent_legacy_write_summary ORDER BY table_name;
