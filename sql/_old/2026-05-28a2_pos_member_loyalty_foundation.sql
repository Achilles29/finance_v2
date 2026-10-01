SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A2 - POS Member / Loyalty / Voucher
-- File   : 2026-05-28a2_pos_member_loyalty_foundation.sql
-- Tujuan :
-- 1) Menyiapkan master member sebagai entitas utama POS
-- 2) Menyiapkan rule/campaign loyalty yang tidak bergantung ke transaksi POS
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
-- ============================================================

START TRANSACTION;

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

COMMIT;

-- Quick check
SELECT 'crm_member' AS table_name, COUNT(*) AS total_rows FROM crm_member
UNION ALL
SELECT 'pos_point_rule', COUNT(*) FROM pos_point_rule
UNION ALL
SELECT 'pos_stamp_campaign', COUNT(*) FROM pos_stamp_campaign
UNION ALL
SELECT 'pos_voucher_campaign', COUNT(*) FROM pos_voucher_campaign;
