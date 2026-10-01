SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A6 - POS Printer Common Setting + Desktop Device
-- File   : 2026-05-28a6_pos_printer_foundation.sql
-- Tujuan :
-- 1) Menyiapkan template, profile, dan route printer POS
-- 2) Memisahkan setting tampilan umum dan device desktop
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
-- ============================================================

START TRANSACTION;

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

COMMIT;

-- Quick check
SELECT 'pos_printer_template_master' AS table_name, COUNT(*) AS total_rows FROM pos_printer_template_master
UNION ALL
SELECT 'pos_printer_profile', COUNT(*) FROM pos_printer_profile
UNION ALL
SELECT 'pos_printer_desktop_device', COUNT(*) FROM pos_printer_desktop_device
UNION ALL
SELECT 'pos_printer_job', COUNT(*) FROM pos_printer_job;
