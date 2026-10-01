SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A4 - POS Order / Payment
-- File   : 2026-05-28a4_pos_order_payment_foundation.sql
-- Tujuan :
-- 1) Menyiapkan header/detail order POS
-- 2) Menyiapkan payment header/detail dan state log order
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- F. Order / Payment POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_order (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_no VARCHAR(40) NOT NULL,
  order_channel ENUM('CASHIER','SELF_ORDER','RESERVATION','DELIVERY') NOT NULL DEFAULT 'CASHIER',
  order_scope ENUM('REGULAR','EVENT') NOT NULL DEFAULT 'REGULAR',
  service_type ENUM('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  outlet_id BIGINT UNSIGNED NOT NULL,
  terminal_id BIGINT UNSIGNED NULL,
  shift_id BIGINT UNSIGNED NULL,
  cashier_session_id BIGINT UNSIGNED NULL,
  cashier_employee_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','PENDING','CONFIRMED','PAID_PARTIAL','PAID','IN_KITCHEN','READY','SERVED','VOID','REFUND_PARTIAL','REFUND_FULL') NOT NULL DEFAULT 'DRAFT',
  kitchen_status ENUM('PENDING','SENT','IN_PROGRESS','READY','SERVED','VOID') NOT NULL DEFAULT 'PENDING',
  stock_commit_status ENUM('PENDING','POSTED','REVERSED') NOT NULL DEFAULT 'PENDING',
  ordered_at DATETIME NOT NULL,
  confirmed_at DATETIME NULL,
  stock_committed_at DATETIME NULL,
  stock_reversed_at DATETIME NULL,
  paid_at DATETIME NULL,
  served_at DATETIME NULL,
  guest_count INT UNSIGNED NOT NULL DEFAULT 1,
  subtotal_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  promo_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  voucher_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  point_redeem_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  compliment_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  service_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  rounding_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  change_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_order_no (order_no),
  KEY idx_pos_order_status_date (status, ordered_at),
  KEY idx_pos_order_outlet (outlet_id),
  KEY idx_pos_order_shift (shift_id),
  KEY idx_pos_order_member (member_id),
  CONSTRAINT fk_pos_order_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_order_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_order_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id),
  CONSTRAINT fk_pos_order_session FOREIGN KEY (cashier_session_id) REFERENCES pos_cashier_session(id),
  CONSTRAINT fk_pos_order_cashier FOREIGN KEY (cashier_employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_order_member FOREIGN KEY (member_id) REFERENCES crm_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_order_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  bundle_id BIGINT UNSIGNED NULL,
  line_type ENUM('PRODUCT','BUNDLE_HEADER','BUNDLE_ITEM') NOT NULL DEFAULT 'PRODUCT',
  product_division_id_snapshot BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  uom_id BIGINT UNSIGNED NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  hpp_standard_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  hpp_live_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  cogs_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  availability_mode_snapshot ENUM('AUTO','FORCE_AVAILABLE','FORCE_OUT','MANUAL_ALLOWED') NOT NULL DEFAULT 'AUTO',
  line_status ENUM('OPEN','SENT','READY','SERVED','VOID','REFUNDED_PARTIAL','REFUNDED_FULL') NOT NULL DEFAULT 'OPEN',
  process_status ENUM('NOT_PROCESSED','PROCESSED','SERVED') NOT NULL DEFAULT 'NOT_PROCESSED',
  processed_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_order_line_no (order_id, line_no),
  KEY idx_pos_order_line_product (product_id),
  KEY idx_pos_order_line_bundle (bundle_id),
  KEY idx_pos_order_line_process (process_status),
  CONSTRAINT fk_pos_order_line_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_order_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_order_line_bundle FOREIGN KEY (bundle_id) REFERENCES pos_product_bundle(id),
  CONSTRAINT fk_pos_order_line_division FOREIGN KEY (product_division_id_snapshot) REFERENCES mst_product_division(id),
  CONSTRAINT fk_pos_order_line_oper_div FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_pos_order_line_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_order_line_extra (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  order_line_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  extra_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_amount_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_order_line_extra_no (order_line_id, line_no),
  KEY idx_pos_order_line_extra_extra (extra_id),
  CONSTRAINT fk_pos_order_line_extra_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_order_line_extra_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id),
  CONSTRAINT fk_pos_order_line_extra_extra FOREIGN KEY (extra_id) REFERENCES mst_extra(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_order_state_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  event_code VARCHAR(40) NOT NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pos_order_state_log_order (order_id),
  KEY idx_pos_order_state_log_actor (actor_employee_id),
  CONSTRAINT fk_pos_order_state_log_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_order_state_log_actor FOREIGN KEY (actor_employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_payment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payment_no VARCHAR(40) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  cashier_session_id BIGINT UNSIGNED NULL,
  cashier_employee_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  payment_type ENUM('FINAL','DEPOSIT','REFUND') NOT NULL DEFAULT 'FINAL',
  payment_status ENUM('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  paid_at DATETIME NULL,
  gross_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  promo_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  voucher_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  point_redeem_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  compliment_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  deposit_applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  change_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_payment_no (payment_no),
  KEY idx_pos_payment_order (order_id),
  KEY idx_pos_payment_shift (shift_id),
  KEY idx_pos_payment_status_date (payment_status, paid_at),
  KEY idx_pos_payment_member (member_id),
  CONSTRAINT fk_pos_payment_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_payment_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id),
  CONSTRAINT fk_pos_payment_session FOREIGN KEY (cashier_session_id) REFERENCES pos_cashier_session(id),
  CONSTRAINT fk_pos_payment_cashier FOREIGN KEY (cashier_employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_payment_member FOREIGN KEY (member_id) REFERENCES crm_member(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_payment_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  payment_method_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  reference_no VARCHAR(100) NULL,
  gateway_txn_id VARCHAR(100) NULL,
  received_at DATETIME NULL,
  status ENUM('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PAID',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_payment_line_no (payment_id, line_no),
  KEY idx_pos_payment_line_method (payment_method_id),
  CONSTRAINT fk_pos_payment_line_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_payment_line_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


COMMIT;

-- Quick check
SELECT 'pos_order' AS table_name, COUNT(*) AS total_rows FROM pos_order
UNION ALL
SELECT 'pos_order_line', COUNT(*) FROM pos_order_line
UNION ALL
SELECT 'pos_payment', COUNT(*) FROM pos_payment
UNION ALL
SELECT 'pos_payment_line', COUNT(*) FROM pos_payment_line;
