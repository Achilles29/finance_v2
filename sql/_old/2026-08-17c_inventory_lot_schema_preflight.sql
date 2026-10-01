SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-17c_inventory_lot_schema_preflight.sql
-- Tujuan :
-- 1) Memindahkan struktur FIFO material dan lot component dari
--    request aplikasi ke migration yang dijalankan sekali saat deploy
-- 2) Menjamin batch production tidak menjalankan CREATE/ALTER TABLE
-- 3) Menyatukan kolom audit yang dipakai lot, issue, receipt, dan SR
--
-- Jalankan pada lokal dan server SEBELUM deploy kode batch terbaru.
-- DDL MariaDB dapat melakukan implicit commit; script bersifat idempoten.
-- ============================================================

SET @schema_name := DATABASE();

-- ------------------------------------------------------------
-- A. FIFO material
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_material_fifo_lot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_no VARCHAR(80) NOT NULL,
  location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  receipt_date DATE NOT NULL,
  expiry_date DATE NULL,
  division_id BIGINT UNSIGNED NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  qty_in DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_balance DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  source_table VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  receipt_id BIGINT UNSIGNED NULL,
  receipt_line_id BIGINT UNSIGNED NULL,
  parent_lot_id BIGINT UNSIGNED NULL,
  status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_material_fifo_scope_lot (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, lot_no),
  KEY idx_inv_material_fifo_pick_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, status, receipt_date, id),
  KEY idx_inv_material_fifo_source (source_table, source_id, source_line_id),
  KEY idx_inv_material_fifo_receipt_line (receipt_line_id),
  KEY idx_inv_material_fifo_parent (parent_lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE inv_material_fifo_lot
  ADD COLUMN IF NOT EXISTS location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  ADD COLUMN IF NOT EXISTS destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  ADD COLUMN IF NOT EXISTS buy_uom_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS profile_key CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS receipt_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS receipt_line_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS parent_lot_id BIGINT UNSIGNED NULL;

ALTER TABLE inv_material_fifo_lot
  MODIFY COLUMN division_id BIGINT UNSIGNED NULL,
  MODIFY COLUMN item_id BIGINT UNSIGNED NULL,
  MODIFY COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL;

CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_no VARCHAR(60) NOT NULL,
  issue_date DATE NOT NULL,
  issue_datetime DATETIME NOT NULL,
  location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  division_id BIGINT UNSIGNED NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  target_scope ENUM('WAREHOUSE','DIVISION') NULL,
  target_division_id BIGINT UNSIGNED NULL,
  target_destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  issue_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_module VARCHAR(50) NOT NULL,
  source_table VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_material_fifo_issue_no (issue_no),
  KEY idx_inv_material_fifo_issue_source (source_table, source_id, source_line_id, status),
  KEY idx_inv_material_fifo_issue_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE inv_material_fifo_issue_log
  ADD COLUMN IF NOT EXISTS location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  ADD COLUMN IF NOT EXISTS destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  ADD COLUMN IF NOT EXISTS target_scope ENUM('WAREHOUSE','DIVISION') NULL,
  ADD COLUMN IF NOT EXISTS target_division_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS target_destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  ADD COLUMN IF NOT EXISTS buy_uom_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS profile_key CHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL;

ALTER TABLE inv_material_fifo_issue_log
  MODIFY COLUMN division_id BIGINT UNSIGNED NULL,
  MODIFY COLUMN item_id BIGINT UNSIGNED NULL,
  MODIFY COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL,
  MODIFY COLUMN target_destination_type ENUM('GUDANG','BAR','KITCHEN','ROASTERY','BAR_EVENT','KITCHEN_EVENT','ROASTERY_EVENT','OFFICE','OTHER') NULL;

CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_id BIGINT UNSIGNED NOT NULL,
  lot_id BIGINT UNSIGNED NOT NULL,
  target_lot_id BIGINT UNSIGNED NULL,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_balance_before DECIMAL(18,4) NULL,
  source_balance_after DECIMAL(18,4) NULL,
  target_balance_before DECIMAL(18,4) NULL,
  target_balance_after DECIMAL(18,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inv_material_fifo_issue_line_issue (issue_id),
  KEY idx_inv_material_fifo_issue_line_lot (lot_id),
  KEY idx_inv_material_fifo_issue_line_target (target_lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE inv_material_fifo_issue_line
  ADD COLUMN IF NOT EXISTS target_lot_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS source_balance_before DECIMAL(18,4) NULL,
  ADD COLUMN IF NOT EXISTS source_balance_after DECIMAL(18,4) NULL,
  ADD COLUMN IF NOT EXISTS target_balance_before DECIMAL(18,4) NULL,
  ADD COLUMN IF NOT EXISTS target_balance_after DECIMAL(18,4) NULL;

-- ------------------------------------------------------------
-- B. Lot component
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_lot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_type VARCHAR(20) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  lot_no VARCHAR(64) NOT NULL,
  receipt_date DATE NOT NULL,
  expiry_date DATE NULL,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  qty_in_total DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_out_total DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_balance DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  source_module VARCHAR(50) NULL,
  source_table VARCHAR(100) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  parent_lot_id BIGINT UNSIGNED NULL,
  last_issue_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_component_lot_scope (location_type, division_id, component_id, uom_id, lot_no),
  KEY idx_inv_component_lot_source (source_table, source_id, source_line_id),
  KEY idx_inv_component_lot_open (location_type, division_id, component_id, uom_id, status, receipt_date),
  KEY idx_inv_component_lot_component (component_id, receipt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE inv_component_lot
  ADD COLUMN IF NOT EXISTS parent_lot_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS last_issue_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS inv_component_lot_issue_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_no VARCHAR(32) NOT NULL,
  issue_date DATE NOT NULL,
  issue_datetime DATETIME NOT NULL,
  location_type VARCHAR(20) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  issue_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_module VARCHAR(50) NULL,
  source_table VARCHAR(100) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_component_lot_issue_no (issue_no),
  KEY idx_inv_component_lot_issue_source (source_table, source_id, source_line_id),
  KEY idx_inv_component_lot_issue_main (location_type, division_id, component_id, uom_id, issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_component_lot_issue_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_id BIGINT UNSIGNED NOT NULL,
  lot_id BIGINT UNSIGNED NOT NULL,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_balance_before DECIMAL(18,4) NULL,
  source_balance_after DECIMAL(18,4) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_inv_component_lot_issue_line_issue (issue_id),
  KEY idx_inv_component_lot_issue_line_lot (lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Kolom jejak dokumen pembelian / fulfillment
-- ------------------------------------------------------------
SET @has_receipt_line := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'pur_purchase_receipt_line'
);
SET @sql := IF(
  @has_receipt_line > 0,
  'ALTER TABLE pur_purchase_receipt_line ADD COLUMN IF NOT EXISTS lot_id BIGINT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS lot_no VARCHAR(80) NULL',
  "SELECT 'skip pur_purchase_receipt_line' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fulfillment_line := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'pur_store_request_fulfillment_line'
);
SET @sql := IF(
  @has_fulfillment_line > 0,
  'ALTER TABLE pur_store_request_fulfillment_line ADD COLUMN IF NOT EXISTS fifo_issue_id BIGINT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS fifo_issue_no VARCHAR(60) NULL',
  "SELECT 'skip pur_store_request_fulfillment_line' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- D. Index yang sebelumnya ditambahkan dari request aplikasi
-- ------------------------------------------------------------
SET @has_old_lot_unique := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'uk_inv_material_fifo_lot_scope'
);
SET @sql := IF(
  @has_old_lot_unique > 0,
  'ALTER TABLE inv_material_fifo_lot DROP INDEX uk_inv_material_fifo_lot_scope',
  "SELECT 'skip old fifo lot unique index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_new_lot_unique := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'uk_inv_material_fifo_scope_lot'
);
SET @sql := IF(
  @has_new_lot_unique = 0,
  'ALTER TABLE inv_material_fifo_lot ADD UNIQUE KEY uk_inv_material_fifo_scope_lot (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, lot_no)',
  "SELECT 'skip fifo lot unique index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lot_pick_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'idx_inv_material_fifo_pick_scope'
);
SET @sql := IF(
  @has_lot_pick_index = 0,
  'ALTER TABLE inv_material_fifo_lot ADD KEY idx_inv_material_fifo_pick_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, status, receipt_date, id)',
  "SELECT 'skip fifo lot pick index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lot_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'idx_inv_material_fifo_source'
);
SET @sql := IF(
  @has_lot_source_index = 0,
  'ALTER TABLE inv_material_fifo_lot ADD KEY idx_inv_material_fifo_source (source_table, source_id, source_line_id)',
  "SELECT 'skip fifo lot source index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lot_receipt_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'idx_inv_material_fifo_receipt_line'
);
SET @sql := IF(
  @has_lot_receipt_index = 0,
  'ALTER TABLE inv_material_fifo_lot ADD KEY idx_inv_material_fifo_receipt_line (receipt_line_id)',
  "SELECT 'skip fifo lot receipt index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_lot_parent_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_lot'
    AND INDEX_NAME = 'idx_inv_material_fifo_parent'
);
SET @sql := IF(
  @has_lot_parent_index = 0,
  'ALTER TABLE inv_material_fifo_lot ADD KEY idx_inv_material_fifo_parent (parent_lot_id)',
  "SELECT 'skip fifo lot parent index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_issue_source_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_issue_log'
    AND INDEX_NAME = 'idx_inv_material_fifo_issue_source'
);
SET @sql := IF(
  @has_issue_source_index = 0,
  'ALTER TABLE inv_material_fifo_issue_log ADD KEY idx_inv_material_fifo_issue_source (source_table, source_id, source_line_id, status)',
  "SELECT 'skip fifo issue source index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_issue_target_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'inv_material_fifo_issue_line'
    AND INDEX_NAME = 'idx_inv_material_fifo_issue_line_target'
);
SET @sql := IF(
  @has_issue_target_index = 0,
  'ALTER TABLE inv_material_fifo_issue_line ADD KEY idx_inv_material_fifo_issue_line_target (target_lot_id)',
  "SELECT 'skip fifo issue target index' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'inv_material_fifo_lot' AS table_name, COUNT(*) AS total_rows FROM inv_material_fifo_lot
UNION ALL
SELECT 'inv_material_fifo_issue_log', COUNT(*) FROM inv_material_fifo_issue_log
UNION ALL
SELECT 'inv_material_fifo_issue_line', COUNT(*) FROM inv_material_fifo_issue_line
UNION ALL
SELECT 'inv_component_lot', COUNT(*) FROM inv_component_lot
UNION ALL
SELECT 'inv_component_lot_issue_log', COUNT(*) FROM inv_component_lot_issue_log
UNION ALL
SELECT 'inv_component_lot_issue_line', COUNT(*) FROM inv_component_lot_issue_line;
