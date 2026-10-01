SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A1 - POS Outlet / Terminal / Shift / Session
-- File   : 2026-05-28a1_pos_outlet_shift_session_foundation.sql
-- Tujuan :
-- 1) Menyiapkan outlet, terminal, shift, dan cashier session POS
-- 2) Menjadi fondasi identitas operasional kasir per outlet/perangkat
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
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

COMMIT;

-- Quick check
SELECT 'pos_outlet' AS table_name, COUNT(*) AS total_rows FROM pos_outlet
UNION ALL
SELECT 'pos_terminal', COUNT(*) FROM pos_terminal
UNION ALL
SELECT 'pos_shift', COUNT(*) FROM pos_shift
UNION ALL
SELECT 'pos_cashier_session', COUNT(*) FROM pos_cashier_session;
