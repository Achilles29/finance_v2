SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6F - Split opening snapshot table (Gudang vs Divisi)
-- ============================================================
START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_warehouse_stock_opening_snapshot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_month DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(40) NOT NULL,
  profile_name VARCHAR(200) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  profile_buy_uom_code VARCHAR(30) NULL,
  profile_content_uom_code VARCHAR(30) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_type ENUM('MANUAL','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_wh_opening_profile_month (snapshot_month, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_wh_opening_month (snapshot_month),
  KEY idx_inv_wh_opening_item (item_id),
  KEY idx_inv_wh_opening_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inv_division_stock_opening_snapshot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_month DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(40) NOT NULL,
  profile_name VARCHAR(200) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  profile_buy_uom_code VARCHAR(30) NULL,
  profile_content_uom_code VARCHAR(30) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_type ENUM('MANUAL','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_div_opening_profile_month (snapshot_month, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_div_opening_month (snapshot_month),
  KEY idx_inv_div_opening_division (division_id, destination_type),
  KEY idx_inv_div_opening_item (item_id),
  KEY idx_inv_div_opening_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @legacy_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
);

SET @has_legacy_destination := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND COLUMN_NAME = 'destination_type'
);

SET @has_legacy_expired := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND COLUMN_NAME = 'profile_expired_date'
);

SET @legacy_destination_expr := IF(@has_legacy_destination = 1, "COALESCE(destination_type, 'OTHER')", "'OTHER'");
SET @legacy_expired_expr := IF(@has_legacy_expired = 1, "profile_expired_date", "NULL");

SET @sql_migrate_wh := IF(
  @legacy_exists = 1,
  CONCAT(
    "INSERT INTO inv_warehouse_stock_opening_snapshot (",
      "snapshot_month, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key, ",
      "profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy, ",
      "profile_buy_uom_code, profile_content_uom_code, opening_qty_buy, opening_qty_content, ",
      "opening_avg_cost_per_content, opening_total_value, source_type, notes, created_by",
    ") SELECT ",
      "snapshot_month, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key, ",
      "profile_name, profile_brand, profile_description, ", @legacy_expired_expr, ", profile_content_per_buy, ",
      "profile_buy_uom_code, profile_content_uom_code, opening_qty_buy, opening_qty_content, ",
      "opening_avg_cost_per_content, opening_total_value, source_type, notes, created_by ",
    "FROM inv_stock_opening_snapshot WHERE stock_scope = 'WAREHOUSE' ",
    "ON DUPLICATE KEY UPDATE ",
      "profile_name = VALUES(profile_name), ",
      "profile_brand = VALUES(profile_brand), ",
      "profile_description = VALUES(profile_description), ",
      "profile_expired_date = VALUES(profile_expired_date), ",
      "profile_content_per_buy = VALUES(profile_content_per_buy), ",
      "profile_buy_uom_code = VALUES(profile_buy_uom_code), ",
      "profile_content_uom_code = VALUES(profile_content_uom_code), ",
      "opening_qty_buy = VALUES(opening_qty_buy), ",
      "opening_qty_content = VALUES(opening_qty_content), ",
      "opening_avg_cost_per_content = VALUES(opening_avg_cost_per_content), ",
      "opening_total_value = VALUES(opening_total_value), ",
      "source_type = VALUES(source_type), ",
      "notes = VALUES(notes), ",
      "updated_at = CURRENT_TIMESTAMP"
  ),
  "SELECT 'skip_warehouse_migrate_no_legacy' AS info"
);

PREPARE stmt_migrate_wh FROM @sql_migrate_wh;
EXECUTE stmt_migrate_wh;
DEALLOCATE PREPARE stmt_migrate_wh;

SET @sql_migrate_div := IF(
  @legacy_exists = 1,
  CONCAT(
    "INSERT INTO inv_division_stock_opening_snapshot (",
      "snapshot_month, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key, ",
      "profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy, ",
      "profile_buy_uom_code, profile_content_uom_code, opening_qty_buy, opening_qty_content, ",
      "opening_avg_cost_per_content, opening_total_value, source_type, notes, created_by",
    ") SELECT ",
      "snapshot_month, COALESCE(division_id, 0), ", @legacy_destination_expr, ", stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key, ",
      "profile_name, profile_brand, profile_description, ", @legacy_expired_expr, ", profile_content_per_buy, ",
      "profile_buy_uom_code, profile_content_uom_code, opening_qty_buy, opening_qty_content, ",
      "opening_avg_cost_per_content, opening_total_value, source_type, notes, created_by ",
    "FROM inv_stock_opening_snapshot WHERE stock_scope = 'DIVISION' ",
    "ON DUPLICATE KEY UPDATE ",
      "profile_name = VALUES(profile_name), ",
      "profile_brand = VALUES(profile_brand), ",
      "profile_description = VALUES(profile_description), ",
      "profile_expired_date = VALUES(profile_expired_date), ",
      "profile_content_per_buy = VALUES(profile_content_per_buy), ",
      "profile_buy_uom_code = VALUES(profile_buy_uom_code), ",
      "profile_content_uom_code = VALUES(profile_content_uom_code), ",
      "opening_qty_buy = VALUES(opening_qty_buy), ",
      "opening_qty_content = VALUES(opening_qty_content), ",
      "opening_avg_cost_per_content = VALUES(opening_avg_cost_per_content), ",
      "opening_total_value = VALUES(opening_total_value), ",
      "source_type = VALUES(source_type), ",
      "notes = VALUES(notes), ",
      "updated_at = CURRENT_TIMESTAMP"
  ),
  "SELECT 'skip_division_migrate_no_legacy' AS info"
);

PREPARE stmt_migrate_div FROM @sql_migrate_div;
EXECUTE stmt_migrate_div;
DEALLOCATE PREPARE stmt_migrate_div;

COMMIT;

SELECT 'inv_warehouse_stock_opening_snapshot' AS table_name, COUNT(*) AS total_rows FROM inv_warehouse_stock_opening_snapshot
UNION ALL
SELECT 'inv_division_stock_opening_snapshot', COUNT(*) FROM inv_division_stock_opening_snapshot;
