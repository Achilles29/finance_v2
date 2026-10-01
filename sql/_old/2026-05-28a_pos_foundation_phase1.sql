SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A - POS Foundation Phase 1
-- File   : 2026-05-28a_pos_foundation_phase1.sql
-- Tujuan :
-- 1) Menyiapkan domain utama POS finance
-- 2) Menyiapkan member, loyalty, payment, bundle, printer, void, refund
-- 3) Menyiapkan availability cache + override produk POS
-- Catatan:
-- - Walk-in transaction cukup member_id = NULL
-- - Voucher bisa umum atau tertaut member
-- - Availability cache wajib event-driven
-- - Commit stok terjadi saat transaksi stok dicatat, bukan saat payment
-- - Untuk eksekusi bertahap, prioritaskan file split 2026-05-28a1 s/d 2026-05-28a6
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Outlet / Terminal / Shift / Cashier Session
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_outlet (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_code VARCHAR(40) NOT NULL,
  outlet_name VARCHAR(120) NOT NULL,
  outlet_scope ENUM('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  product_division_id BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  address TEXT NULL,
  phone VARCHAR(30) NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_outlet_code (outlet_code),
  KEY idx_pos_outlet_product_division (product_division_id),
  KEY idx_pos_outlet_operational_division (operational_division_id),
  CONSTRAINT fk_pos_outlet_product_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
  CONSTRAINT fk_pos_outlet_operational_division FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_terminal (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  terminal_code VARCHAR(40) NOT NULL,
  terminal_name VARCHAR(120) NOT NULL,
  device_key VARCHAR(120) NULL,
  app_platform ENUM('DESKTOP','WEB','ANDROID','IOS','OTHER') NOT NULL DEFAULT 'DESKTOP',
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_terminal_code (terminal_code),
  UNIQUE KEY uk_pos_terminal_device_key (device_key),
  KEY idx_pos_terminal_outlet (outlet_id),
  CONSTRAINT fk_pos_terminal_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_shift (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shift_no VARCHAR(40) NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  terminal_id BIGINT UNSIGNED NULL,
  cashier_open_employee_id BIGINT UNSIGNED NOT NULL,
  cashier_close_employee_id BIGINT UNSIGNED NULL,
  status ENUM('OPEN','CLOSED','VOID') NOT NULL DEFAULT 'OPEN',
  opened_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  opening_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  expected_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  actual_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_shift_no (shift_no),
  KEY idx_pos_shift_outlet_status (outlet_id, status),
  KEY idx_pos_shift_terminal (terminal_id),
  CONSTRAINT fk_pos_shift_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_shift_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_shift_open_emp FOREIGN KEY (cashier_open_employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_shift_close_emp FOREIGN KEY (cashier_close_employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_shift_summary (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shift_id BIGINT UNSIGNED NOT NULL,
  total_order_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_gross_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_promo DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_net_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_cash_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_non_cash_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_refund DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_void DECIMAL(18,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_shift_summary_shift (shift_id),
  CONSTRAINT fk_pos_shift_summary_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_cashier_session (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  session_key VARCHAR(120) NOT NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  terminal_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  session_status ENUM('OPEN','LOCKED','CLOSED') NOT NULL DEFAULT 'OPEN',
  login_at DATETIME NOT NULL,
  logout_at DATETIME NULL,
  last_ping_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_cashier_session_key (session_key),
  KEY idx_pos_cashier_session_terminal_status (terminal_id, session_status),
  KEY idx_pos_cashier_session_shift (shift_id),
  KEY idx_pos_cashier_session_employee (employee_id),
  CONSTRAINT fk_pos_cashier_session_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_cashier_session_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_cashier_session_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id),
  CONSTRAINT fk_pos_cashier_session_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- B. Member
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm_member (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  member_no VARCHAR(40) NOT NULL,
  member_name VARCHAR(150) NOT NULL,
  mobile_phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  birth_date DATE NULL,
  gender ENUM('L','P') NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  emergency_contact_name VARCHAR(120) NULL,
  emergency_contact_phone VARCHAR(30) NULL,
  member_tier VARCHAR(50) NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  point_balance_cache DECIMAL(18,4) NOT NULL DEFAULT 0,
  stamp_balance_cache DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_spending DECIMAL(18,2) NOT NULL DEFAULT 0,
  member_status ENUM('ACTIVE','SUSPENDED','CLOSED') NOT NULL DEFAULT 'ACTIVE',
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_crm_member_no (member_no),
  KEY idx_crm_member_name (member_name),
  KEY idx_crm_member_phone (mobile_phone),
  KEY idx_crm_member_email (email),
  KEY idx_crm_member_status (member_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Payment Method
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_payment_method (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  method_code VARCHAR(40) NOT NULL,
  method_name VARCHAR(120) NOT NULL,
  method_type ENUM('CASH','BANK','EWALLET','QRIS','COMPLIMENT','DEPOSIT','OTHER') NOT NULL DEFAULT 'CASH',
  company_account_id BIGINT UNSIGNED NULL,
  allows_change TINYINT(1) NOT NULL DEFAULT 0,
  requires_reference_no TINYINT(1) NOT NULL DEFAULT 0,
  show_in_cashier TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_payment_method_code (method_code),
  KEY idx_pos_payment_method_account (company_account_id),
  KEY idx_pos_payment_method_type (method_type),
  CONSTRAINT fk_pos_payment_method_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- D. Bundle Produk POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_product_bundle (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bundle_code VARCHAR(40) NOT NULL,
  bundle_name VARCHAR(150) NOT NULL,
  product_division_id BIGINT UNSIGNED NULL,
  pos_scope ENUM('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  selling_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_bundle_code (bundle_code),
  KEY idx_pos_product_bundle_division (product_division_id),
  CONSTRAINT fk_pos_product_bundle_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_product_bundle_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bundle_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 1,
  unit_price_override DECIMAL(18,2) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_bundle_line_product (bundle_id, product_id),
  KEY idx_pos_product_bundle_line_product (product_id),
  CONSTRAINT fk_pos_product_bundle_line_bundle FOREIGN KEY (bundle_id) REFERENCES pos_product_bundle(id),
  CONSTRAINT fk_pos_product_bundle_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- E. Availability Produk POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_product_availability_override (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  override_mode ENUM('AUTO','FORCE_AVAILABLE','FORCE_OUT') NOT NULL DEFAULT 'AUTO',
  override_note VARCHAR(255) NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_availability_override (outlet_id, product_id),
  KEY idx_pos_product_availability_override_mode (override_mode),
  CONSTRAINT fk_pos_prod_avail_override_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_prod_avail_override_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_prod_avail_override_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_prod_avail_override_updated_by FOREIGN KEY (updated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_product_availability_cache (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  availability_status ENUM('AVAILABLE','LIMITED','OUT','HIDDEN') NOT NULL DEFAULT 'AVAILABLE',
  source_mode ENUM('AUTO','OVERRIDE_AVAILABLE','OVERRIDE_OUT') NOT NULL DEFAULT 'AUTO',
  estimated_available_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  uom_id BIGINT UNSIGNED NULL,
  bottleneck_kind ENUM('NONE','MATERIAL','COMPONENT') NOT NULL DEFAULT 'NONE',
  bottleneck_material_id BIGINT UNSIGNED NULL,
  bottleneck_component_id BIGINT UNSIGNED NULL,
  bottleneck_name_snapshot VARCHAR(150) NULL,
  main_missing_count INT UNSIGNED NOT NULL DEFAULT 0,
  optional_missing_count INT UNSIGNED NOT NULL DEFAULT 0,
  override_allowed TINYINT(1) NOT NULL DEFAULT 0,
  hpp_live_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  stock_reference_at DATETIME NULL,
  last_commit_event VARCHAR(50) NULL,
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_dirty TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_availability_cache (outlet_id, product_id),
  KEY idx_pos_product_availability_status (availability_status),
  CONSTRAINT fk_pos_prod_avail_cache_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_prod_avail_cache_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_prod_avail_cache_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pos_prod_avail_cache_material FOREIGN KEY (bottleneck_material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pos_prod_avail_cache_component FOREIGN KEY (bottleneck_component_id) REFERENCES mst_component(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ------------------------------------------------------------
-- G. Loyalty yang Diringkas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_point_rule (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  rule_code VARCHAR(40) NOT NULL,
  rule_name VARCHAR(120) NOT NULL,
  earn_mode ENUM('AMOUNT','PRODUCT','FLAT') NOT NULL DEFAULT 'AMOUNT',
  spend_basis ENUM('NET','GROSS') NOT NULL DEFAULT 'NET',
  amount_per_point DECIMAL(18,2) NOT NULL DEFAULT 0,
  flat_point DECIMAL(18,4) NOT NULL DEFAULT 0,
  required_product_id BIGINT UNSIGNED NULL,
  min_spend_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  point_expiry_days INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_point_rule_code (rule_code),
  KEY idx_pos_point_rule_product (required_product_id),
  CONSTRAINT fk_pos_point_rule_product FOREIGN KEY (required_product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_point_ledger (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  rule_id BIGINT UNSIGNED NULL,
  ledger_type ENUM('EARN','REDEEM','ADJUST','EXPIRE','REVERSE') NOT NULL DEFAULT 'EARN',
  points_in DECIMAL(18,4) NOT NULL DEFAULT 0,
  points_out DECIMAL(18,4) NOT NULL DEFAULT 0,
  balance_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  expired_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pos_point_ledger_member (member_id),
  KEY idx_pos_point_ledger_order (order_id),
  KEY idx_pos_point_ledger_payment (payment_id),
  KEY idx_pos_point_ledger_rule (rule_id),
  CONSTRAINT fk_pos_point_ledger_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_point_ledger_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_point_ledger_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_point_ledger_rule FOREIGN KEY (rule_id) REFERENCES pos_point_rule(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_stamp_campaign (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  campaign_code VARCHAR(40) NOT NULL,
  campaign_name VARCHAR(120) NOT NULL,
  earn_mode ENUM('TXN','AMOUNT','PRODUCT') NOT NULL DEFAULT 'TXN',
  amount_step DECIMAL(18,2) NOT NULL DEFAULT 0,
  stamp_per_earn DECIMAL(18,4) NOT NULL DEFAULT 1,
  required_product_id BIGINT UNSIGNED NULL,
  redeem_required_stamp DECIMAL(18,4) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  stamp_expiry_days INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_stamp_campaign_code (campaign_code),
  KEY idx_pos_stamp_campaign_product (required_product_id),
  CONSTRAINT fk_pos_stamp_campaign_product FOREIGN KEY (required_product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_stamp_ledger (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  ledger_type ENUM('EARN','REDEEM','ADJUST','EXPIRE','REVERSE') NOT NULL DEFAULT 'EARN',
  stamp_in DECIMAL(18,4) NOT NULL DEFAULT 0,
  stamp_out DECIMAL(18,4) NOT NULL DEFAULT 0,
  balance_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  expired_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pos_stamp_ledger_member (member_id),
  KEY idx_pos_stamp_ledger_order (order_id),
  KEY idx_pos_stamp_ledger_payment (payment_id),
  KEY idx_pos_stamp_ledger_campaign (campaign_id),
  CONSTRAINT fk_pos_stamp_ledger_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_stamp_ledger_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_stamp_ledger_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_stamp_ledger_campaign FOREIGN KEY (campaign_id) REFERENCES pos_stamp_campaign(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_voucher_campaign (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  campaign_code VARCHAR(40) NOT NULL,
  campaign_name VARCHAR(120) NOT NULL,
  issue_mode ENUM('PUBLIC','AUTO_FROM_TXN','MEMBER_TARGETED','MANUAL') NOT NULL DEFAULT 'PUBLIC',
  voucher_type ENUM('AMOUNT','PERCENT','FREE_PRODUCT') NOT NULL DEFAULT 'AMOUNT',
  discount_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  max_discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_spend_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  trigger_product_id BIGINT UNSIGNED NULL,
  free_product_id BIGINT UNSIGNED NULL,
  free_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  valid_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  point_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  stamp_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_voucher_campaign_code (campaign_code),
  KEY idx_pos_voucher_campaign_trigger_product (trigger_product_id),
  KEY idx_pos_voucher_campaign_free_product (free_product_id),
  CONSTRAINT fk_pos_voucher_campaign_trigger_product FOREIGN KEY (trigger_product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_voucher_campaign_free_product FOREIGN KEY (free_product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_voucher_issue (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  voucher_issue_no VARCHAR(40) NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  source_order_id BIGINT UNSIGNED NULL,
  source_payment_id BIGINT UNSIGNED NULL,
  voucher_code VARCHAR(60) NOT NULL,
  voucher_status ENUM('OPEN','REDEEMED','EXPIRED','VOID') NOT NULL DEFAULT 'OPEN',
  amount_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0,
  percent_snapshot DECIMAL(18,4) NOT NULL DEFAULT 0,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expired_at DATETIME NULL,
  redeemed_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_voucher_issue_no (voucher_issue_no),
  UNIQUE KEY uk_pos_voucher_issue_code (voucher_code),
  KEY idx_pos_voucher_issue_member (member_id),
  KEY idx_pos_voucher_issue_campaign (campaign_id),
  KEY idx_pos_voucher_issue_status (voucher_status),
  CONSTRAINT fk_pos_voucher_issue_campaign FOREIGN KEY (campaign_id) REFERENCES pos_voucher_campaign(id),
  CONSTRAINT fk_pos_voucher_issue_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_voucher_issue_order FOREIGN KEY (source_order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_voucher_issue_payment FOREIGN KEY (source_payment_id) REFERENCES pos_payment(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_voucher_redemption (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  voucher_issue_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  redeem_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL,
  KEY idx_pos_voucher_redemption_issue (voucher_issue_id),
  KEY idx_pos_voucher_redemption_member (member_id),
  KEY idx_pos_voucher_redemption_order (order_id),
  KEY idx_pos_voucher_redemption_payment (payment_id),
  CONSTRAINT fk_pos_voucher_redemption_issue FOREIGN KEY (voucher_issue_id) REFERENCES pos_voucher_issue(id),
  CONSTRAINT fk_pos_voucher_redemption_member FOREIGN KEY (member_id) REFERENCES crm_member(id),
  CONSTRAINT fk_pos_voucher_redemption_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_voucher_redemption_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- ------------------------------------------------------------
-- I. Printer Common Setting + Desktop Device
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_printer_template_master (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  master_code VARCHAR(40) NOT NULL,
  master_name VARCHAR(120) NOT NULL,
  document_type ENUM('RECEIPT','KOT','BILL','SHIFT_CLOSE','REFUND','VOID','STICKER','OTHER') NOT NULL DEFAULT 'RECEIPT',
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_template_master_code (master_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_template (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_code VARCHAR(40) NOT NULL,
  template_name VARCHAR(120) NOT NULL,
  template_master_id BIGINT UNSIGNED NULL,
  template_body LONGTEXT NULL,
  footer_body LONGTEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_template_code (template_code),
  KEY idx_pos_printer_template_master (template_master_id),
  CONSTRAINT fk_pos_printer_template_master FOREIGN KEY (template_master_id) REFERENCES pos_printer_template_master(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_profile (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  profile_code VARCHAR(40) NOT NULL,
  profile_name VARCHAR(120) NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  paper_width_mm INT UNSIGNED NOT NULL DEFAULT 80,
  font_density ENUM('SMALL','NORMAL','LARGE') NOT NULL DEFAULT 'NORMAL',
  copy_count INT UNSIGNED NOT NULL DEFAULT 1,
  show_logo TINYINT(1) NOT NULL DEFAULT 1,
  show_price TINYINT(1) NOT NULL DEFAULT 1,
  show_footer TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_profile_code (profile_code),
  KEY idx_pos_printer_profile_template (template_id),
  CONSTRAINT fk_pos_printer_profile_template FOREIGN KEY (template_id) REFERENCES pos_printer_template(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_content_setting (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  profile_id BIGINT UNSIGNED NOT NULL,
  section_code VARCHAR(40) NOT NULL,
  setting_key VARCHAR(60) NOT NULL,
  setting_value LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_content_setting (profile_id, section_code, setting_key),
  CONSTRAINT fk_pos_printer_content_setting_profile FOREIGN KEY (profile_id) REFERENCES pos_printer_profile(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_event_setting (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  event_code VARCHAR(40) NOT NULL,
  event_name VARCHAR(120) NOT NULL,
  document_type ENUM('RECEIPT','KOT','BILL','SHIFT_CLOSE','REFUND','VOID','STICKER','OTHER') NOT NULL DEFAULT 'RECEIPT',
  auto_print TINYINT(1) NOT NULL DEFAULT 1,
  allow_manual_reprint TINYINT(1) NOT NULL DEFAULT 1,
  max_copy INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_event_setting_code (event_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_route_rule (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  route_code VARCHAR(40) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  product_division_id BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  document_type ENUM('RECEIPT','KOT','BILL','SHIFT_CLOSE','REFUND','VOID','STICKER','OTHER') NOT NULL DEFAULT 'RECEIPT',
  event_code VARCHAR(40) NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  priority INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_route_code (route_code),
  KEY idx_pos_printer_route_outlet (outlet_id),
  KEY idx_pos_printer_route_terminal (terminal_id),
  CONSTRAINT fk_pos_printer_route_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_printer_route_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id),
  CONSTRAINT fk_pos_printer_route_product_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
  CONSTRAINT fk_pos_printer_route_operational_division FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_pos_printer_route_profile FOREIGN KEY (profile_id) REFERENCES pos_printer_profile(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_desktop_device (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  device_code VARCHAR(40) NOT NULL,
  device_name VARCHAR(120) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  terminal_id BIGINT UNSIGNED NULL,
  driver_type ENUM('ESC_POS','WINDOWS','SYSTEM','NETWORK') NOT NULL DEFAULT 'SYSTEM',
  connection_type ENUM('USB','LAN','SHARED','OTHER') NOT NULL DEFAULT 'USB',
  share_path VARCHAR(255) NULL,
  host VARCHAR(120) NULL,
  port INT UNSIGNED NULL,
  paper_width_mm INT UNSIGNED NOT NULL DEFAULT 80,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_desktop_device_code (device_code),
  KEY idx_pos_printer_desktop_device_outlet (outlet_id),
  CONSTRAINT fk_pos_printer_desktop_device_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_printer_desktop_device_terminal FOREIGN KEY (terminal_id) REFERENCES pos_terminal(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_job (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_no VARCHAR(40) NOT NULL,
  route_rule_id BIGINT UNSIGNED NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  desktop_device_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_id BIGINT UNSIGNED NULL,
  refund_id BIGINT UNSIGNED NULL,
  void_id BIGINT UNSIGNED NULL,
  document_type ENUM('RECEIPT','KOT','BILL','SHIFT_CLOSE','REFUND','VOID','STICKER','OTHER') NOT NULL DEFAULT 'RECEIPT',
  event_code VARCHAR(40) NOT NULL,
  print_payload LONGTEXT NULL,
  copy_count INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('PENDING','PROCESSING','DONE','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_printer_job_no (job_no),
  KEY idx_pos_printer_job_status (status),
  CONSTRAINT fk_pos_printer_job_route FOREIGN KEY (route_rule_id) REFERENCES pos_printer_route_rule(id),
  CONSTRAINT fk_pos_printer_job_profile FOREIGN KEY (profile_id) REFERENCES pos_printer_profile(id),
  CONSTRAINT fk_pos_printer_job_device FOREIGN KEY (desktop_device_id) REFERENCES pos_printer_desktop_device(id),
  CONSTRAINT fk_pos_printer_job_order FOREIGN KEY (order_id) REFERENCES pos_order(id),
  CONSTRAINT fk_pos_printer_job_payment FOREIGN KEY (payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_printer_job_refund FOREIGN KEY (refund_id) REFERENCES pos_refund(id),
  CONSTRAINT fk_pos_printer_job_void FOREIGN KEY (void_id) REFERENCES pos_void(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_printer_job_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_id BIGINT UNSIGNED NOT NULL,
  log_level ENUM('INFO','WARN','ERROR') NOT NULL DEFAULT 'INFO',
  message VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pos_printer_job_log_job (job_id),
  CONSTRAINT fk_pos_printer_job_log_job FOREIGN KEY (job_id) REFERENCES pos_printer_job(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- Quick check
SELECT 'crm_member' AS table_name, COUNT(*) AS total_rows FROM crm_member
UNION ALL
SELECT 'pos_outlet', COUNT(*) FROM pos_outlet
UNION ALL
SELECT 'pos_order', COUNT(*) FROM pos_order
UNION ALL
SELECT 'pos_payment', COUNT(*) FROM pos_payment
UNION ALL
SELECT 'pos_product_availability_cache', COUNT(*) FROM pos_product_availability_cache
UNION ALL
SELECT 'pos_voucher_issue', COUNT(*) FROM pos_voucher_issue;
