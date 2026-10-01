SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A5 - POS Void / Refund
-- File   : 2026-05-28a5_pos_void_refund_foundation.sql
-- Tujuan :
-- 1) Menyiapkan audit void POS
-- 2) Menyiapkan refund POS dengan kontrol return-to-stock vs adjustment
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- H. Void / Refund
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_void (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  void_no VARCHAR(40) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  cashier_session_id BIGINT UNSIGNED NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  shift_id BIGINT UNSIGNED NULL,
  member_id BIGINT UNSIGNED NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  void_scope ENUM('FULL','PARTIAL') NOT NULL DEFAULT 'PARTIAL',
  processed_state ENUM('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  return_to_stock TINYINT(1) NOT NULL DEFAULT 0,
  adjustment_mode ENUM('NONE','AUTO_WASTE','AUTO_SPOIL','AUTO_ADJUSTMENT') NOT NULL DEFAULT 'NONE',
  order_status_before VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  order_status_after VARCHAR(30) NOT NULL DEFAULT 'VOID',
  order_no_snapshot VARCHAR(40) NOT NULL,
  member_name_snapshot VARCHAR(160) NULL,
  service_type_snapshot VARCHAR(30) NULL,
  reason TEXT NULL,
  line_count INT UNSIGNED NOT NULL DEFAULT 0,
  extra_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_qty_void DECIMAL(18,4) NOT NULL DEFAULT 0,
  amount_void DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_void_no (void_no),
  KEY idx_pos_void_order (order_id),
  CONSTRAINT fk_pos_void_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_void_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_void_session FOREIGN KEY (cashier_session_id) REFERENCES pos_cashier_session(id),
  CONSTRAINT fk_pos_void_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_void_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_void_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id),
  CONSTRAINT fk_pos_void_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_void_actor FOREIGN KEY (actor_employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_void_approved_by FOREIGN KEY (approved_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_void_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  void_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  line_no_snapshot INT NOT NULL DEFAULT 0,
  item_name_snapshot VARCHAR(255) NOT NULL,
  qty_before DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_void DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  subtotal_void DECIMAL(18,2) NOT NULL DEFAULT 0,
  hpp_live_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  line_process_state ENUM('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  line_status_after VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pos_void_line_void (void_id),
  CONSTRAINT fk_pos_void_line_void FOREIGN KEY (void_id) REFERENCES pos_void(id),
  CONSTRAINT fk_pos_void_line_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_void_line_order_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id),
  CONSTRAINT fk_pos_void_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_void_line_extra (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  void_id BIGINT UNSIGNED NOT NULL,
  void_line_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  order_line_extra_id BIGINT UNSIGNED NOT NULL,
  extra_id BIGINT UNSIGNED NULL,
  extra_name_snapshot VARCHAR(255) NOT NULL,
  qty_per_unit DECIMAL(18,4) NOT NULL DEFAULT 0,
  line_qty_affected DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  subtotal_void DECIMAL(18,2) NOT NULL DEFAULT 0,
  status_after VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pos_void_extra_void (void_id),
  CONSTRAINT fk_pos_void_line_extra_void FOREIGN KEY (void_id) REFERENCES pos_void(id),
  CONSTRAINT fk_pos_void_line_extra_void_line FOREIGN KEY (void_line_id) REFERENCES pos_void_line(id),
  CONSTRAINT fk_pos_void_line_extra_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_void_line_extra_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id),
  CONSTRAINT fk_pos_void_line_extra_line_extra FOREIGN KEY (order_line_extra_id) REFERENCES pos_order_line_extra(id),
  CONSTRAINT fk_pos_void_line_extra_extra FOREIGN KEY (extra_id) REFERENCES mst_extra(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_refund (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  refund_no VARCHAR(40) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  member_id BIGINT UNSIGNED NULL,
  payment_method_id BIGINT UNSIGNED NULL,
  company_account_id BIGINT UNSIGNED NULL,
  reference_no VARCHAR(100) NULL,
  refund_status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  processed_state ENUM('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  return_to_stock TINYINT(1) NOT NULL DEFAULT 0,
  adjustment_mode ENUM('NONE','AUTO_WASTE','AUTO_SPOIL','AUTO_ADJUSTMENT') NOT NULL DEFAULT 'NONE',
  refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reason TEXT NULL,
  refunded_by BIGINT UNSIGNED NULL,
  refunded_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_refund_no (refund_no),
  KEY idx_pos_refund_order (order_id),
  CONSTRAINT fk_pos_refund_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_refund_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_refund_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_refund_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id),
  CONSTRAINT fk_pos_refund_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id),
  CONSTRAINT fk_pos_refund_by FOREIGN KEY (refunded_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_refund_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  refund_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  line_type ENUM('PRODUCT','EXTRA') NOT NULL DEFAULT 'PRODUCT',
  order_line_id BIGINT UNSIGNED NULL,
  order_extra_line_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  extra_id BIGINT UNSIGNED NULL,
  qty_refunded DECIMAL(18,4) NOT NULL DEFAULT 0,
  amount_refunded DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_reversed DECIMAL(18,2) NOT NULL DEFAULT 0,
  line_process_state ENUM('NOT_PROCESSED','PROCESSED') NOT NULL DEFAULT 'NOT_PROCESSED',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_refund_line_no (refund_id, line_no),
  KEY idx_pos_refund_line_order_line (order_line_id),
  CONSTRAINT fk_pos_refund_line_refund FOREIGN KEY (refund_id) REFERENCES pos_refund(id),
  CONSTRAINT fk_pos_refund_line_order_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id),
  CONSTRAINT fk_pos_refund_line_extra FOREIGN KEY (order_extra_line_id) REFERENCES pos_order_line_extra(id),
  CONSTRAINT fk_pos_refund_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_refund_line_extra_master FOREIGN KEY (extra_id) REFERENCES mst_extra(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


COMMIT;

-- Quick check
SELECT 'pos_void' AS table_name, COUNT(*) AS total_rows FROM pos_void
UNION ALL
SELECT 'pos_void_line', COUNT(*) FROM pos_void_line
UNION ALL
SELECT 'pos_refund', COUNT(*) FROM pos_refund
UNION ALL
SELECT 'pos_refund_line', COUNT(*) FROM pos_refund_line;
