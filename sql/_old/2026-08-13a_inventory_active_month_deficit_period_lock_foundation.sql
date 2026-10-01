SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-13a_inventory_active_month_deficit_period_lock_foundation.sql
-- Tujuan :
-- 1) Menyediakan period lock material/component per bulan
-- 2) Mencatat defisit stok saat POS/pemakaian melampaui lot
-- 3) Menyimpan penyelesaian defisit oleh receipt/adjustment
-- 4) Menyediakan audit event untuk koreksi lot saat cut-off
--
-- Catatan:
-- - Script ini hanya menambah fondasi tabel.
-- - Script ini TIDAK mengubah stok, lot, movement, maupun data lama.
-- - Jalankan sebelum deploy perubahan writer inventory.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_stock_period (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  period_month DATE NOT NULL,
  status ENUM('OPEN','CLOSING','CLOSED','REOPENED') NOT NULL DEFAULT 'OPEN',
  close_mode ENUM('MONTHLY_OPNAME','MANUAL') NOT NULL DEFAULT 'MONTHLY_OPNAME',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  closed_by BIGINT UNSIGNED NULL,
  reopened_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  reopened_at DATETIME NULL,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_stock_period_domain_month (stock_domain, period_month),
  KEY idx_inv_stock_period_status_month (status, period_month),
  CONSTRAINT fk_inv_stock_period_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_period_closed_by FOREIGN KEY (closed_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_period_reopened_by FOREIGN KEY (reopened_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_deficit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deficit_key CHAR(64) NOT NULL,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  deficit_date DATE NOT NULL,
  location_scope VARCHAR(30) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  destination_type VARCHAR(30) NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NULL,
  requested_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  issued_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  settled_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  reversed_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_remaining DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  estimated_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  estimated_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  status ENUM('OPEN','SETTLED','VOID') NOT NULL DEFAULT 'OPEN',
  source_module VARCHAR(50) NOT NULL,
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_stock_deficit_key (deficit_key),
  KEY idx_inv_stock_deficit_open_scope (stock_domain, status, deficit_date, location_scope, division_id),
  KEY idx_inv_stock_deficit_identity (stock_domain, item_id, material_id, component_id, content_uom_id, profile_key),
  KEY idx_inv_stock_deficit_source (source_table, source_id, source_line_id, status),
  CONSTRAINT fk_inv_stock_deficit_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_deficit_item FOREIGN KEY (item_id) REFERENCES mst_item(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_deficit_material FOREIGN KEY (material_id) REFERENCES mst_material(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_deficit_component FOREIGN KEY (component_id) REFERENCES mst_component(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_deficit_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_deficit_voided_by FOREIGN KEY (voided_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sebelumnya kolom ini dibuat saat request aplikasi. Pindahkan ke migration
-- agar deploy dapat diverifikasi sebelum halaman adjustment dipakai.
ALTER TABLE inv_component_adjustment_line
  ADD COLUMN IF NOT EXISTS selected_lot_id BIGINT UNSIGNED NULL AFTER uom_id,
  ADD COLUMN IF NOT EXISTS input_mode ENUM('DELTA','PHYSICAL_COUNT') NOT NULL DEFAULT 'DELTA' AFTER selected_lot_id,
  ADD COLUMN IF NOT EXISTS system_qty_snapshot DECIMAL(18,4) NULL AFTER available_qty,
  ADD COLUMN IF NOT EXISTS physical_qty_snapshot DECIMAL(18,4) NULL AFTER system_qty_snapshot,
  ADD COLUMN IF NOT EXISTS deficit_settled_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER physical_qty_snapshot,
  ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000 AFTER qty_adjust_neg,
  ADD COLUMN IF NOT EXISTS waste_reason_code VARCHAR(50) NULL AFTER qty_waste,
  ADD COLUMN IF NOT EXISTS spoil_reason_code VARCHAR(50) NULL AFTER qty_spoil,
  ADD COLUMN IF NOT EXISTS adjustment_plus_reason_code VARCHAR(50) NULL AFTER qty_adjust_pos,
  ADD COLUMN IF NOT EXISTS adjustment_minus_reason_code VARCHAR(50) NULL AFTER unit_cost;

ALTER TABLE inv_stock_adjustment_line
  ADD COLUMN IF NOT EXISTS input_mode ENUM('DELTA','PHYSICAL_COUNT') NOT NULL DEFAULT 'DELTA' AFTER line_no,
  ADD COLUMN IF NOT EXISTS system_qty_snapshot_content DECIMAL(18,4) NULL AFTER available_qty_content,
  ADD COLUMN IF NOT EXISTS physical_qty_snapshot_content DECIMAL(18,4) NULL AFTER system_qty_snapshot_content,
  ADD COLUMN IF NOT EXISTS deficit_settled_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER physical_qty_snapshot_content;

CREATE TABLE IF NOT EXISTS inv_stock_deficit_settlement (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deficit_id BIGINT UNSIGNED NOT NULL,
  settlement_date DATE NOT NULL,
  qty_settled DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_module VARCHAR(50) NOT NULL,
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inv_stock_deficit_settlement_deficit (deficit_id, settlement_date),
  KEY idx_inv_stock_deficit_settlement_source (source_table, source_id, source_line_id),
  CONSTRAINT fk_inv_stock_deficit_settlement_deficit FOREIGN KEY (deficit_id) REFERENCES inv_stock_deficit(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_stock_deficit_settlement_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_cutoff_event (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  event_date DATE NOT NULL,
  period_month DATE NOT NULL,
  location_scope VARCHAR(30) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  destination_type VARCHAR(30) NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NULL,
  lot_id BIGINT UNSIGNED NULL,
  direction ENUM('IN','OUT') NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  movement_table VARCHAR(80) NULL,
  movement_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inv_stock_cutoff_event_period (stock_domain, period_month, event_date),
  KEY idx_inv_stock_cutoff_event_identity (stock_domain, item_id, material_id, component_id, content_uom_id, profile_key),
  KEY idx_inv_stock_cutoff_event_source (source_table, source_id, source_line_id),
  CONSTRAINT fk_inv_stock_cutoff_event_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_cutoff_event_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'inv_stock_period' AS table_name, COUNT(*) AS total_rows FROM inv_stock_period
UNION ALL
SELECT 'inv_stock_deficit', COUNT(*) FROM inv_stock_deficit
UNION ALL
SELECT 'inv_stock_deficit_settlement', COUNT(*) FROM inv_stock_deficit_settlement
UNION ALL
SELECT 'inv_stock_cutoff_event', COUNT(*) FROM inv_stock_cutoff_event;
