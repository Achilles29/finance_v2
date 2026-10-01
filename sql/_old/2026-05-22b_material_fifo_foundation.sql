SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-22b_material_fifo_foundation.sql
-- Tujuan :
-- 1) Fondasi FIFO bahan baku agar HPP live formula/batch bisa akurat.
-- 2) Menyiapkan lot layer + issue log (audit jejak konsumsi).
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_material_fifo_lot (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  lot_no VARCHAR(80) NOT NULL,
  location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  receipt_date DATE NOT NULL,
  expiry_date DATE NULL,
  division_id BIGINT UNSIGNED NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  qty_in DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  source_table VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  receipt_id BIGINT UNSIGNED NULL,
  receipt_line_id BIGINT UNSIGNED NULL,
  parent_lot_id BIGINT UNSIGNED NULL,
  status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_material_fifo_scope_lot (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, lot_no),
  KEY idx_inv_material_fifo_pick_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, status, receipt_date, id),
  KEY idx_inv_material_fifo_source (source_table, source_id, source_line_id),
  KEY idx_inv_material_fifo_receipt_line (receipt_line_id),
  KEY idx_inv_material_fifo_parent (parent_lot_id),
  CONSTRAINT fk_inv_material_fifo_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_material_fifo_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_material_fifo_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_material_fifo_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  issue_no VARCHAR(60) NOT NULL,
  issue_date DATE NOT NULL,
  issue_datetime DATETIME NOT NULL,
  location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  division_id BIGINT UNSIGNED NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  target_scope ENUM('WAREHOUSE','DIVISION') NULL,
  target_division_id BIGINT UNSIGNED NULL,
  target_destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  issue_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_module VARCHAR(50) NOT NULL,
  source_table VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_material_fifo_issue_no (issue_no),
  KEY idx_inv_material_fifo_issue_source (source_table, source_id, source_line_id, status),
  KEY idx_inv_material_fifo_issue_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, issue_date),
  CONSTRAINT fk_inv_material_fifo_issue_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_material_fifo_issue_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_material_fifo_issue_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_material_fifo_issue_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  issue_id BIGINT UNSIGNED NOT NULL,
  lot_id BIGINT UNSIGNED NOT NULL,
  target_lot_id BIGINT UNSIGNED NULL,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_material_fifo_issue_line_issue (issue_id),
  KEY idx_inv_material_fifo_issue_line_lot (lot_id),
  KEY idx_inv_material_fifo_issue_line_target (target_lot_id),
  CONSTRAINT fk_inv_material_fifo_issue_line_issue FOREIGN KEY (issue_id) REFERENCES inv_material_fifo_issue_log(id),
  CONSTRAINT fk_inv_material_fifo_issue_line_lot FOREIGN KEY (lot_id) REFERENCES inv_material_fifo_lot(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pur_purchase_receipt_line
  ADD COLUMN IF NOT EXISTS lot_id BIGINT UNSIGNED NULL AFTER profile_key,
  ADD COLUMN IF NOT EXISTS lot_no VARCHAR(80) NULL AFTER lot_id;

ALTER TABLE pur_store_request_fulfillment_line
  ADD COLUMN IF NOT EXISTS fifo_issue_id BIGINT UNSIGNED NULL AFTER unit_cost_snapshot,
  ADD COLUMN IF NOT EXISTS fifo_issue_no VARCHAR(60) NULL AFTER fifo_issue_id;

COMMIT;
