SET NAMES utf8mb4;

-- ============================================================
-- Tahap 7L - Monthly opname + opening carry-forward flow
-- Tujuan:
-- 1) Menyimpan hasil generate stok opname per bulan (gudang & divisi)
-- 2) Menyimpan opening bulan berikutnya dari hasil opname (qty akhir > 0)
-- 3) Menjaga dimensi tujuan (REGULER/EVENT) untuk stok divisi
-- ============================================================
START TRANSACTION;

SET @db_name = DATABASE();

-- ------------------------------------------------------------
-- A. Opening snapshot: tambah destination_type jika belum ada
-- ------------------------------------------------------------
SET @exists_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND COLUMN_NAME = 'destination_type'
);
SET @sql := IF(
  @exists_col = 0,
  "ALTER TABLE inv_stock_opening_snapshot ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL AFTER division_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE inv_stock_opening_snapshot
SET destination_type = 'OTHER'
WHERE stock_scope = 'DIVISION'
  AND (destination_type IS NULL OR destination_type = '');

-- Pastikan unique key opening memuat destination_type untuk DIVISION.
SET @uk_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND INDEX_NAME = 'uk_inv_opening_scope_profile_month'
);
SET @uk_has_dest := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND INDEX_NAME = 'uk_inv_opening_scope_profile_month'
    AND COLUMN_NAME = 'destination_type'
);
SET @sql := IF(
  @uk_exists = 0,
  'ALTER TABLE inv_stock_opening_snapshot ADD UNIQUE KEY uk_inv_opening_scope_profile_month (snapshot_month, stock_scope, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key)',
  IF(
    @uk_has_dest = 0,
    'ALTER TABLE inv_stock_opening_snapshot DROP INDEX uk_inv_opening_scope_profile_month, ADD UNIQUE KEY uk_inv_opening_scope_profile_month (snapshot_month, stock_scope, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'inv_stock_opening_snapshot'
    AND INDEX_NAME = 'idx_inv_opening_destination'
);
SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE inv_stock_opening_snapshot ADD KEY idx_inv_opening_destination (stock_scope, division_id, destination_type, snapshot_month)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- B. Opname bulanan gudang
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_warehouse_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,

  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,

  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_inv_wh_opname_profile_month (month_key, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_wh_opname_month (month_key),
  KEY idx_inv_wh_opname_item (item_id),
  KEY idx_inv_wh_opname_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Opname bulanan divisi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_division_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  adjustment_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,

  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,

  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,

  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_inv_div_opname_profile_month (month_key, division_id, destination_type, stock_domain, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_div_opname_month (month_key),
  KEY idx_inv_div_opname_division (division_id),
  KEY idx_inv_div_opname_destination (destination_type),
  KEY idx_inv_div_opname_item (item_id),
  KEY idx_inv_div_opname_material (material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'inv_stock_opening_snapshot' AS table_name, COUNT(*) AS total_rows FROM inv_stock_opening_snapshot
UNION ALL
SELECT 'inv_warehouse_monthly_opname', COUNT(*) FROM inv_warehouse_monthly_opname
UNION ALL
SELECT 'inv_division_monthly_opname', COUNT(*) FROM inv_division_monthly_opname;
