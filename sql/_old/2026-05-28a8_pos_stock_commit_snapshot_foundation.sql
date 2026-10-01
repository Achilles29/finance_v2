SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A8 - POS Stock Commit Snapshot Hardening
-- File   : 2026-05-28a8_pos_stock_commit_snapshot_foundation.sql
-- Tujuan :
-- 1) Menyimpan snapshot konsumsi stok POS per order saat stock commit
-- 2) Menjadi basis reversal void/refund yang lebih presisi
-- 3) Mengurangi kebutuhan hitung ulang resep saat return/reversal
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_stock_commit (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  commit_no VARCHAR(40) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  shift_id BIGINT UNSIGNED NULL,
  cashier_session_id BIGINT UNSIGNED NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  commit_status ENUM('DRAFT','COMMITTED','PARTIAL_REVERSED','REVERSED','VOID') NOT NULL DEFAULT 'DRAFT',
  commit_reason ENUM('ORDER_CONFIRM','VOID_REVERSAL','REFUND_REVERSAL','MANUAL') NOT NULL DEFAULT 'ORDER_CONFIRM',
  process_state_snapshot ENUM('NONE','PARTIAL','FULL') NOT NULL DEFAULT 'NONE',
  committed_at DATETIME NULL,
  reversed_at DATETIME NULL,
  last_rebuild_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_stock_commit_no (commit_no),
  KEY idx_pos_stock_commit_order (order_id),
  KEY idx_pos_stock_commit_status (commit_status),
  CONSTRAINT fk_pos_stock_commit_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_stock_commit_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_stock_commit_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_stock_commit_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id),
  CONSTRAINT fk_pos_stock_commit_session FOREIGN KEY (cashier_session_id) REFERENCES pos_cashier_session(id),
  CONSTRAINT fk_pos_stock_commit_actor FOREIGN KEY (actor_employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_stock_commit_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  commit_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NULL,
  order_line_extra_id BIGINT UNSIGNED NULL,
  line_type ENUM('PRODUCT','EXTRA') NOT NULL DEFAULT 'PRODUCT',
  product_id BIGINT UNSIGNED NULL,
  extra_id BIGINT UNSIGNED NULL,
  source_kind ENUM('MATERIAL','COMPONENT') NOT NULL,
  source_role ENUM('MAIN','SUPPORT','COMPLEMENT','OPTIONAL') NOT NULL DEFAULT 'MAIN',
  material_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  source_name_snapshot VARCHAR(150) NULL,
  required_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  required_uom_id BIGINT UNSIGNED NULL,
  committed_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  reversed_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost_live DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_cost_live DECIMAL(18,6) NOT NULL DEFAULT 0,
  cost_source ENUM('FIFO','LAST_LIVE','STANDARD_FALLBACK','MANUAL') NOT NULL DEFAULT 'FIFO',
  movement_ref_type ENUM('MATERIAL_LEDGER','COMPONENT_LEDGER','NONE') NULL,
  movement_ref_id BIGINT UNSIGNED NULL,
  return_policy ENUM('RETURN_TO_STOCK','ADJUSTMENT_ONLY','NO_RETURN') NOT NULL DEFAULT 'RETURN_TO_STOCK',
  reversal_status ENUM('NONE','RETURNED','ADJUSTED','SKIPPED') NOT NULL DEFAULT 'NONE',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_stock_commit_line_no (commit_id, line_no),
  KEY idx_pos_stock_commit_line_order (order_id),
  KEY idx_pos_stock_commit_line_order_line (order_line_id),
  KEY idx_pos_stock_commit_line_extra (order_line_extra_id),
  KEY idx_pos_stock_commit_line_material (material_id),
  KEY idx_pos_stock_commit_line_component (component_id),
  CONSTRAINT fk_pos_stock_commit_line_commit FOREIGN KEY (commit_id) REFERENCES pos_stock_commit(id),
  CONSTRAINT fk_pos_stock_commit_line_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_stock_commit_line_order_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id),
  CONSTRAINT fk_pos_stock_commit_line_order_extra FOREIGN KEY (order_line_extra_id) REFERENCES pos_order_line_extra(id),
  CONSTRAINT fk_pos_stock_commit_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_stock_commit_line_extra_master FOREIGN KEY (extra_id) REFERENCES mst_extra(id),
  CONSTRAINT fk_pos_stock_commit_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pos_stock_commit_line_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_pos_stock_commit_line_uom FOREIGN KEY (required_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- Quick check
SELECT 'pos_stock_commit' AS table_name, COUNT(*) AS total_rows FROM pos_stock_commit
UNION ALL
SELECT 'pos_stock_commit_line', COUNT(*) FROM pos_stock_commit_line;
