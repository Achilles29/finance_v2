SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28d_pos_single_db_import_from_core.sql
-- Tujuan :
-- 1) Menjadikan master POS memakai satu database fisik: db_finance
-- 2) Menormalkan schema lokal agar kompatibel dengan runtime POS saat ini
-- 3) Mengimpor data master POS/member/printer dari database core
-- Catatan:
-- - Jalankan saat database `core` masih tersedia di server yang sama.
-- - Setelah migrasi ini, runtime cukup memakai db_finance.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Create missing local tables with finance-compatible schema
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
  expired_at DATETIME NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_printer (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  printer_code VARCHAR(40) NOT NULL,
  printer_name VARCHAR(120) NOT NULL,
  printer_role VARCHAR(20) NOT NULL DEFAULT 'CUSTOM',
  print_scope VARCHAR(20) NOT NULL DEFAULT 'DIVISION',
  outlet_id BIGINT UNSIGNED NULL,
  connection_type ENUM('LOCAL_AGENT','LAN','USB') NOT NULL DEFAULT 'LOCAL_AGENT',
  agent_os ENUM('WINDOWS','UBUNTU','OTHER') NOT NULL DEFAULT 'WINDOWS',
  agent_host VARCHAR(120) NULL,
  device_name VARCHAR(120) NULL,
  mac_address VARCHAR(17) NULL,
  python_port INT NOT NULL DEFAULT 3000,
  ip_address VARCHAR(60) NULL,
  port INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_printer_code (printer_code),
  KEY idx_pos_printer_outlet (outlet_id),
  CONSTRAINT fk_pos_printer_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- B. Alter local tables for runtime compatibility
-- ------------------------------------------------------------
SET @has_cm_expired_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_member' AND COLUMN_NAME = 'expired_at'
);
SET @sql_cm_expired_at := IF(
  @has_cm_expired_at = 0,
  'ALTER TABLE crm_member ADD COLUMN expired_at DATETIME NULL AFTER joined_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql_cm_expired_at; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ptm_master_payload := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_template_master' AND COLUMN_NAME = 'master_payload'
);
SET @sql_ptm_master_payload := IF(
  @has_ptm_master_payload = 0,
  'ALTER TABLE pos_printer_template_master ADD COLUMN master_payload LONGTEXT NULL AFTER master_name',
  'SELECT 1'
);
PREPARE stmt FROM @sql_ptm_master_payload; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ptm_is_default := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_template_master' AND COLUMN_NAME = 'is_default'
);
SET @sql_ptm_is_default := IF(
  @has_ptm_is_default = 0,
  'ALTER TABLE pos_printer_template_master ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 1 AFTER master_payload',
  'SELECT 1'
);
PREPARE stmt FROM @sql_ptm_is_default; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pt_document_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_template' AND COLUMN_NAME = 'document_type'
);
SET @sql_pt_document_type := IF(
  @has_pt_document_type = 0,
  'ALTER TABLE pos_printer_template ADD COLUMN document_type ENUM(''RECEIPT'',''KITCHEN_TICKET'',''VOID_SLIP'',''REFUND_SLIP'',''DEPOSIT_RECEIPT'') NOT NULL DEFAULT ''RECEIPT'' AFTER template_name',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pt_document_type; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pt_template_payload := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_template' AND COLUMN_NAME = 'template_payload'
);
SET @sql_pt_template_payload := IF(
  @has_pt_template_payload = 0,
  'ALTER TABLE pos_printer_template ADD COLUMN template_payload LONGTEXT NULL AFTER document_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pt_template_payload; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_printer_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'printer_id'
);
SET @sql_pp_printer_id := IF(
  @has_pp_printer_id = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN printer_id BIGINT UNSIGNED NULL AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_printer_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_chars := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'chars_per_line'
);
SET @sql_pp_chars := IF(
  @has_pp_chars = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN chars_per_line TINYINT UNSIGNED NOT NULL DEFAULT 48 AFTER paper_width_mm',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_chars; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_copies := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'copies'
);
SET @sql_pp_copies := IF(
  @has_pp_copies = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN copies TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER chars_per_line',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_copies; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_encoding := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'encoding'
);
SET @sql_pp_encoding := IF(
  @has_pp_encoding = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN encoding VARCHAR(30) NOT NULL DEFAULT ''UTF-8'' AFTER copies',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_encoding; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_cut_mode := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'cut_mode'
);
SET @sql_pp_cut_mode := IF(
  @has_pp_cut_mode = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN cut_mode ENUM(''NONE'',''PARTIAL'',''FULL'') NOT NULL DEFAULT ''PARTIAL'' AFTER encoding',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_cut_mode; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_open_drawer := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND COLUMN_NAME = 'open_drawer'
);
SET @sql_pp_open_drawer := IF(
  @has_pp_open_drawer = 0,
  'ALTER TABLE pos_printer_profile ADD COLUMN open_drawer TINYINT(1) NOT NULL DEFAULT 0 AFTER cut_mode',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_open_drawer; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_pp_template_nullable := 'ALTER TABLE pos_printer_profile MODIFY template_id BIGINT UNSIGNED NULL';
PREPARE stmt FROM @sql_pp_template_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql_pp_code_nullable := 'ALTER TABLE pos_printer_profile MODIFY profile_code VARCHAR(40) NULL';
PREPARE stmt FROM @sql_pp_code_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql_pp_name_nullable := 'ALTER TABLE pos_printer_profile MODIFY profile_name VARCHAR(120) NULL';
PREPARE stmt FROM @sql_pp_name_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pp_idx_printer := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_profile' AND INDEX_NAME = 'idx_pos_printer_profile_printer'
);
SET @sql_pp_idx_printer := IF(
  @has_pp_idx_printer = 0,
  'ALTER TABLE pos_printer_profile ADD KEY idx_pos_printer_profile_printer (printer_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pp_idx_printer; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pcs_printer_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'printer_id'
);
SET @sql_pcs_printer_id := IF(
  @has_pcs_printer_id = 0,
  'ALTER TABLE pos_printer_content_setting ADD COLUMN printer_id BIGINT UNSIGNED NULL AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pcs_printer_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql_pcs_profile_nullable := 'ALTER TABLE pos_printer_content_setting MODIFY profile_id BIGINT UNSIGNED NULL';
PREPARE stmt FROM @sql_pcs_profile_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql_pcs_section_nullable := 'ALTER TABLE pos_printer_content_setting MODIFY section_code VARCHAR(40) NULL';
PREPARE stmt FROM @sql_pcs_section_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql_pcs_key_nullable := 'ALTER TABLE pos_printer_content_setting MODIFY setting_key VARCHAR(60) NULL';
PREPARE stmt FROM @sql_pcs_key_nullable; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- helper macro via repeated block for content columns
SET @content_cols := '
  ADD COLUMN show_logo TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_product_name TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_qty TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_extra TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_notes TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN price_visibility ENUM(''never'',''kasir_only'',''always'') NOT NULL DEFAULT ''kasir_only'',
  ADD COLUMN show_subtotal_per_item TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN show_discount TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_grand_total TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_payment_breakdown TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_header TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_footer TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_qr TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN show_invoice_no TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_customer TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_table_no TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_cashier_order TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_cashier_payment TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_order_time TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_payment_time TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_void_reason TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN show_refund_reason TINYINT(1) NOT NULL DEFAULT 1';

-- add each column conditionally
SET @cols_to_add = (
  SELECT GROUP_CONCAT(stmt SEPARATOR ', ')
  FROM (
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_logo TINYINT(1) NOT NULL DEFAULT 1', NULL) AS stmt
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_logo'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_product_name TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_product_name'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_qty TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_qty'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_extra TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_extra'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_notes TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_notes'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN price_visibility ENUM(''never'',''kasir_only'',''always'') NOT NULL DEFAULT ''kasir_only''', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'price_visibility'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_subtotal_per_item TINYINT(1) NOT NULL DEFAULT 0', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_subtotal_per_item'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_discount TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_discount'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_grand_total TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_grand_total'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_payment_breakdown TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_payment_breakdown'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_header TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_header'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_footer TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_footer'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_qr TINYINT(1) NOT NULL DEFAULT 0', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_qr'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_invoice_no TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_invoice_no'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_customer TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_customer'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_table_no TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_table_no'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_cashier_order TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_cashier_order'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_cashier_payment TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_cashier_payment'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_order_time TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_order_time'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_payment_time TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_payment_time'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_void_reason TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_void_reason'
    UNION ALL
    SELECT IF(COUNT(*) = 0, 'ADD COLUMN show_refund_reason TINYINT(1) NOT NULL DEFAULT 1', NULL)
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND COLUMN_NAME = 'show_refund_reason'
  ) x
  WHERE stmt IS NOT NULL
);
SET @sql_pcs_cols := IF(
  @cols_to_add IS NOT NULL AND @cols_to_add <> '',
  CONCAT('ALTER TABLE pos_printer_content_setting ', @cols_to_add),
  'SELECT 1'
);
PREPARE stmt FROM @sql_pcs_cols; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pcs_idx_printer := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_printer_content_setting' AND INDEX_NAME = 'idx_pos_printer_content_printer'
);
SET @sql_pcs_idx_printer := IF(
  @has_pcs_idx_printer = 0,
  'ALTER TABLE pos_printer_content_setting ADD KEY idx_pos_printer_content_printer (printer_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_pcs_idx_printer; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_terminal_os_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_terminal' AND COLUMN_NAME = 'os_type'
);
SET @sql_terminal_os_type := IF(
  @has_terminal_os_type = 0,
  'ALTER TABLE pos_terminal ADD COLUMN os_type ENUM(''WINDOWS'',''UBUNTU'',''ANDROID'',''IOS'',''WEB'',''OTHER'') NOT NULL DEFAULT ''WEB'' AFTER device_key',
  'SELECT 1'
);
PREPARE stmt FROM @sql_terminal_os_type; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_company_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_payment_method' AND COLUMN_NAME = 'company_account_id'
);
SET @sql_payment_company_id := IF(
  @has_payment_company_id = 0,
  'ALTER TABLE pos_payment_method ADD COLUMN company_account_id BIGINT UNSIGNED NULL AFTER method_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql_payment_company_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_payment_company_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_payment_method' AND INDEX_NAME = 'idx_pos_payment_method_account'
);
SET @sql_payment_company_idx := IF(
  @has_payment_company_idx = 0,
  'ALTER TABLE pos_payment_method ADD KEY idx_pos_payment_method_account (company_account_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_payment_company_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @needs_printer_conn_local_agent := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer'
    AND COLUMN_NAME = 'connection_type'
    AND COLUMN_TYPE NOT LIKE '%LOCAL_AGENT%'
);
SET @sql_printer_conn_local_agent := IF(
  @needs_printer_conn_local_agent > 0,
  'ALTER TABLE pos_printer MODIFY connection_type ENUM(''LOCAL_AGENT'',''LAN'',''USB'',''BLUETOOTH'',''OTHER'') NOT NULL DEFAULT ''LOCAL_AGENT''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_printer_conn_local_agent; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @needs_printer_agent_os := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer'
    AND COLUMN_NAME = 'agent_os'
    AND COLUMN_TYPE NOT LIKE '%UBUNTU%'
);
SET @sql_printer_agent_os := IF(
  @needs_printer_agent_os > 0,
  'ALTER TABLE pos_printer MODIFY agent_os ENUM(''WINDOWS'',''UBUNTU'',''ANDROID'',''IOS'',''WEB'',''OTHER'') NOT NULL DEFAULT ''WINDOWS''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_printer_agent_os; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @needs_outlet_scope_mixed := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_outlet'
    AND COLUMN_NAME = 'outlet_scope'
    AND COLUMN_TYPE NOT LIKE '%MIXED%'
);
SET @sql_outlet_scope_mixed := IF(
  @needs_outlet_scope_mixed > 0,
  'ALTER TABLE pos_outlet MODIFY outlet_scope ENUM(''REGULAR'',''EVENT'',''MIXED'',''ALL'') NOT NULL DEFAULT ''REGULAR''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_outlet_scope_mixed; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- C. Import / sync data from core
-- ------------------------------------------------------------

-- import rekening core ke standar lokal fin_company_account
INSERT INTO fin_company_account (
  account_code,
  account_name,
  account_type,
  bank_name,
  account_no,
  account_holder,
  currency_code,
  opening_balance,
  current_balance,
  is_default,
  notes,
  is_active
)
SELECT
  CONCAT('CORE-', UPPER(REPLACE(REPLACE(COALESCE(src.account_no, CONCAT('ACC', src.id)), ' ', ''), '-', ''))) AS account_code,
  src.account_name,
  CASE
    WHEN COALESCE(pm.has_cash, 0) = 1 THEN 'CASH'
    WHEN COALESCE(pm.has_ewallet, 0) = 1 THEN 'EWALLET'
    WHEN COALESCE(pm.has_bank, 0) = 1 THEN 'BANK'
    WHEN UPPER(COALESCE(src.bank_name, '')) IN ('TUNAI', 'CASH', 'KAS', 'BRANKAS') THEN 'CASH'
    WHEN UPPER(COALESCE(src.bank_name, '')) LIKE '%MIDTRANS%' THEN 'EWALLET'
    ELSE 'BANK'
  END AS account_type,
  src.bank_name,
  src.account_no,
  src.account_name AS account_holder,
  'IDR' AS currency_code,
  0 AS opening_balance,
  0 AS current_balance,
  CASE WHEN src.id = 1 THEN 1 ELSE 0 END AS is_default,
  CONCAT('Imported from core.m_bank_account id=', src.id) AS notes,
  src.is_active
FROM core.m_bank_account src
LEFT JOIN (
  SELECT
    bank_account_id,
    MAX(CASE WHEN method_type = 'CASH' THEN 1 ELSE 0 END) AS has_cash,
    MAX(CASE WHEN method_type = 'EWALLET' THEN 1 ELSE 0 END) AS has_ewallet,
    MAX(CASE WHEN method_type IN ('BANK', 'QRIS') THEN 1 ELSE 0 END) AS has_bank
  FROM core.pos_payment_method
  WHERE bank_account_id IS NOT NULL
  GROUP BY bank_account_id
) pm ON pm.bank_account_id = src.id
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  bank_name = VALUES(bank_name),
  account_no = VALUES(account_no),
  account_holder = VALUES(account_holder),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

-- member: landing ke crm_member, bukan customer + member_account terpisah
INSERT INTO crm_member (
  member_no, member_name, mobile_phone, email, birth_date, gender, address,
  member_tier, joined_at, expired_at, point_balance_cache, stamp_balance_cache,
  total_spending, member_status, notes, is_active, created_at, updated_at
)
SELECT
  m.member_no,
  c.customer_name AS member_name,
  c.phone AS mobile_phone,
  c.email,
  c.birth_date,
  CASE
    WHEN UPPER(COALESCE(c.gender, '')) IN ('MALE', 'L') THEN 'L'
    WHEN UPPER(COALESCE(c.gender, '')) IN ('FEMALE', 'P') THEN 'P'
    ELSE NULL
  END AS gender,
  c.address,
  m.tier_code AS member_tier,
  m.joined_at,
  m.expired_at,
  0 AS point_balance_cache,
  0 AS stamp_balance_cache,
  0 AS total_spending,
  CASE
    WHEN m.status = 'ACTIVE' THEN 'ACTIVE'
    WHEN m.status = 'SUSPENDED' THEN 'SUSPENDED'
    ELSE 'CLOSED'
  END AS member_status,
  c.notes,
  c.is_active,
  c.created_at,
  c.updated_at
FROM core.crm_member_account m
JOIN core.crm_customer c ON c.id = m.customer_id
ON DUPLICATE KEY UPDATE
  member_name = VALUES(member_name),
  mobile_phone = VALUES(mobile_phone),
  email = VALUES(email),
  birth_date = VALUES(birth_date),
  gender = VALUES(gender),
  address = VALUES(address),
  member_tier = VALUES(member_tier),
  joined_at = VALUES(joined_at),
  expired_at = VALUES(expired_at),
  member_status = VALUES(member_status),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = VALUES(updated_at);

-- outlet / terminal / payment method
INSERT INTO pos_outlet (
  id, outlet_code, outlet_name, outlet_scope, address, phone, is_active, created_at, updated_at
)
SELECT
  o.id, o.outlet_code, o.outlet_name, o.outlet_scope, o.address, o.phone, o.is_active, o.created_at, o.updated_at
FROM core.pos_outlet o
ON DUPLICATE KEY UPDATE
  outlet_code = VALUES(outlet_code),
  outlet_name = VALUES(outlet_name),
  outlet_scope = VALUES(outlet_scope),
  address = VALUES(address),
  phone = VALUES(phone),
  is_active = VALUES(is_active),
  updated_at = VALUES(updated_at);

INSERT INTO pos_terminal (
  id, outlet_id, terminal_code, terminal_name, device_key, os_type, is_active, created_at, updated_at
)
SELECT
  t.id, t.outlet_id, t.terminal_code, t.terminal_name, t.device_key, t.os_type, t.is_active, t.created_at, t.updated_at
FROM core.pos_terminal t
ON DUPLICATE KEY UPDATE
  outlet_id = VALUES(outlet_id),
  terminal_code = VALUES(terminal_code),
  terminal_name = VALUES(terminal_name),
  device_key = VALUES(device_key),
  os_type = VALUES(os_type),
  is_active = VALUES(is_active),
  updated_at = VALUES(updated_at);

INSERT INTO pos_payment_method (
  id, method_code, method_name, method_type, company_account_id, is_active, created_at, updated_at
)
SELECT
  p.id,
  p.method_code,
  p.method_name,
  p.method_type,
  acc.id AS company_account_id,
  p.is_active,
  p.created_at,
  p.updated_at
FROM core.pos_payment_method p
LEFT JOIN core.m_bank_account ba ON ba.id = p.bank_account_id
LEFT JOIN fin_company_account acc ON acc.account_code = CONCAT(
  'CORE-',
  UPPER(REPLACE(REPLACE(COALESCE(ba.account_no, CONCAT('ACC', ba.id)), ' ', ''), '-', ''))
)
ON DUPLICATE KEY UPDATE
  method_code = VALUES(method_code),
  method_name = VALUES(method_name),
  method_type = VALUES(method_type),
  company_account_id = VALUES(company_account_id),
  is_active = VALUES(is_active),
  updated_at = VALUES(updated_at);

-- printer domain: replace local draft data with core truth
DELETE FROM pos_printer_content_setting;
DELETE FROM pos_printer_profile;
DELETE FROM pos_printer;
DELETE FROM pos_printer_template;
DELETE FROM pos_printer_template_master;

INSERT INTO pos_printer_template_master (
  id, master_code, master_name, document_type, description, master_payload, is_default, is_active, created_at, updated_at
)
SELECT
  m.id,
  m.master_code,
  m.master_name,
  'OTHER' AS document_type,
  NULL AS description,
  m.master_payload,
  m.is_default,
  m.is_active,
  m.created_at,
  m.updated_at
FROM core.pos_printer_template_master m;

INSERT INTO pos_printer_template (
  id, template_code, template_name, template_master_id, template_body, footer_body, document_type, template_payload, is_default, is_active, created_at, updated_at
)
SELECT
  t.id,
  t.template_code,
  t.template_name,
  (SELECT tm.id FROM pos_printer_template_master tm ORDER BY tm.is_default DESC, tm.id ASC LIMIT 1) AS template_master_id,
  t.template_payload AS template_body,
  NULL AS footer_body,
  t.document_type,
  t.template_payload,
  t.is_default,
  t.is_active,
  t.created_at,
  t.updated_at
FROM core.pos_printer_template t;

INSERT INTO pos_printer (
  id, printer_code, printer_name, printer_role, print_scope, outlet_id, connection_type, agent_os, agent_host, device_name, mac_address, python_port, ip_address, port, is_active, created_at, updated_at
)
SELECT
  p.id, p.printer_code, p.printer_name, p.printer_role, p.print_scope, p.outlet_id, p.connection_type, p.agent_os, p.agent_host, p.device_name, p.mac_address, p.python_port, p.ip_address, p.port, p.is_active, p.created_at, p.updated_at
FROM core.pos_printer p;

INSERT INTO pos_printer_profile (
  id, profile_code, profile_name, template_id, printer_id, paper_width_mm, font_density, copy_count, show_logo, show_price, show_footer, notes, is_active, chars_per_line, copies, encoding, cut_mode, open_drawer, created_at, updated_at
)
SELECT
  pf.id,
  p.printer_code AS profile_code,
  p.printer_name AS profile_name,
  COALESCE(
    (SELECT t.id FROM pos_printer_template t WHERE t.is_default = 1 ORDER BY t.id ASC LIMIT 1),
    (SELECT MIN(t2.id) FROM pos_printer_template t2)
  ) AS template_id,
  pf.printer_id,
  pf.paper_width_mm,
  'NORMAL' AS font_density,
  pf.copies AS copy_count,
  COALESCE(cs.show_logo, 1) AS show_logo,
  CASE WHEN COALESCE(cs.price_visibility, 'always') = 'never' THEN 0 ELSE 1 END AS show_price,
  COALESCE(cs.show_footer, 1) AS show_footer,
  NULL AS notes,
  p.is_active,
  pf.chars_per_line,
  pf.copies,
  pf.encoding,
  pf.cut_mode,
  pf.open_drawer,
  pf.created_at,
  pf.updated_at
FROM core.pos_printer_profile pf
JOIN core.pos_printer p ON p.id = pf.printer_id
LEFT JOIN core.pos_printer_content_setting cs ON cs.printer_id = pf.printer_id;

INSERT INTO pos_printer_content_setting (
  id, profile_id, section_code, setting_key, setting_value, sort_order, printer_id,
  show_logo, show_product_name, show_qty, show_extra, show_notes, price_visibility,
  show_subtotal_per_item, show_discount, show_grand_total, show_payment_breakdown,
  show_header, show_footer, show_qr, show_invoice_no, show_customer, show_table_no,
  show_cashier_order, show_cashier_payment, show_order_time, show_payment_time,
  show_void_reason, show_refund_reason, created_at, updated_at
)
SELECT
  cs.id,
  COALESCE(
    (SELECT pf.id FROM pos_printer_profile pf WHERE pf.printer_id = cs.printer_id ORDER BY pf.id ASC LIMIT 1),
    cs.printer_id
  ) AS profile_id,
  'GENERAL' AS section_code,
  'CORE_PAYLOAD' AS setting_key,
  NULL AS setting_value,
  0 AS sort_order,
  cs.printer_id,
  cs.show_logo, cs.show_product_name, cs.show_qty, cs.show_extra, cs.show_notes, cs.price_visibility,
  cs.show_subtotal_per_item, cs.show_discount, cs.show_grand_total, cs.show_payment_breakdown,
  cs.show_header, cs.show_footer, cs.show_qr, cs.show_invoice_no, cs.show_customer, cs.show_table_no,
  cs.show_cashier_order, cs.show_cashier_payment, cs.show_order_time, cs.show_payment_time,
  cs.show_void_reason, cs.show_refund_reason, cs.created_at, cs.updated_at
FROM core.pos_printer_content_setting cs;

COMMIT;

-- Quick check
SELECT 'crm_member' AS table_name, COUNT(*) AS total_rows FROM crm_member
UNION ALL SELECT 'fin_company_account', COUNT(*) FROM fin_company_account
UNION ALL SELECT 'pos_outlet', COUNT(*) FROM pos_outlet
UNION ALL SELECT 'pos_terminal', COUNT(*) FROM pos_terminal
UNION ALL SELECT 'pos_payment_method', COUNT(*) FROM pos_payment_method
UNION ALL SELECT 'pos_printer', COUNT(*) FROM pos_printer
UNION ALL SELECT 'pos_printer_profile', COUNT(*) FROM pos_printer_profile
UNION ALL SELECT 'pos_printer_content_setting', COUNT(*) FROM pos_printer_content_setting
UNION ALL SELECT 'pos_printer_template', COUNT(*) FROM pos_printer_template
UNION ALL SELECT 'pos_printer_template_master', COUNT(*) FROM pos_printer_template_master;
