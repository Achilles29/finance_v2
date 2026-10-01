SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13d_finance_payable_receivable_foundation.sql
-- Tujuan :
-- 1) Menambah master pihak luar untuk transaksi utang/piutang
-- 2) Menambah tabel header + pembayaran utang
-- 3) Menambah tabel header + pembayaran piutang
-- 4) Mendukung mode histori / saldo tetap agar pencatatan lama
--    tidak mengubah saldo rekening perusahaan saat diinput ulang
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS fin_relation_party (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  party_code VARCHAR(60) NOT NULL,
  party_name VARCHAR(180) NOT NULL,
  party_type ENUM('PERSON','BUSINESS','MEMBER','OTHER') NOT NULL DEFAULT 'BUSINESS',
  linked_member_id BIGINT UNSIGNED NULL,
  contact_person VARCHAR(150) NULL,
  mobile_phone VARCHAR(40) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_relation_party_code (party_code),
  KEY idx_fin_relation_party_name (party_name),
  KEY idx_fin_relation_party_member (linked_member_id),
  KEY idx_fin_relation_party_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_payable (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payable_no VARCHAR(60) NOT NULL,
  party_id BIGINT UNSIGNED NOT NULL,
  payable_date DATE NOT NULL,
  due_date DATE NULL,
  payable_title VARCHAR(200) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  outstanding_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_impact_mode ENUM('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  company_account_id BIGINT UNSIGNED NULL,
  initial_mutation_id BIGINT UNSIGNED NULL,
  status ENUM('OPEN','PARTIAL','SETTLED','VOID') NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_payable_no (payable_no),
  KEY idx_fin_payable_party (party_id),
  KEY idx_fin_payable_date (payable_date),
  KEY idx_fin_payable_status (status),
  KEY idx_fin_payable_account (company_account_id),
  KEY idx_fin_payable_mutation (initial_mutation_id),
  CONSTRAINT fk_fin_payable_party FOREIGN KEY (party_id) REFERENCES fin_relation_party(id),
  CONSTRAINT fk_fin_payable_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id),
  CONSTRAINT fk_fin_payable_mutation FOREIGN KEY (initial_mutation_id) REFERENCES fin_account_mutation_log(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_payable_payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payable_id BIGINT UNSIGNED NOT NULL,
  payment_no VARCHAR(60) NOT NULL,
  payment_date DATE NOT NULL,
  company_account_id BIGINT UNSIGNED NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_impact_mode ENUM('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  transfer_ref_no VARCHAR(80) NULL,
  mutation_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_payable_payment_no (payment_no),
  KEY idx_fin_payable_payment_header (payable_id),
  KEY idx_fin_payable_payment_date (payment_date),
  KEY idx_fin_payable_payment_account (company_account_id),
  KEY idx_fin_payable_payment_mutation (mutation_id),
  CONSTRAINT fk_fin_payable_payment_header FOREIGN KEY (payable_id) REFERENCES fin_payable(id),
  CONSTRAINT fk_fin_payable_payment_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id),
  CONSTRAINT fk_fin_payable_payment_mutation FOREIGN KEY (mutation_id) REFERENCES fin_account_mutation_log(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_receivable (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receivable_no VARCHAR(60) NOT NULL,
  party_id BIGINT UNSIGNED NOT NULL,
  receivable_date DATE NOT NULL,
  due_date DATE NULL,
  receivable_title VARCHAR(200) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  outstanding_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_impact_mode ENUM('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  company_account_id BIGINT UNSIGNED NULL,
  initial_mutation_id BIGINT UNSIGNED NULL,
  status ENUM('OPEN','PARTIAL','SETTLED','VOID') NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_receivable_no (receivable_no),
  KEY idx_fin_receivable_party (party_id),
  KEY idx_fin_receivable_date (receivable_date),
  KEY idx_fin_receivable_status (status),
  KEY idx_fin_receivable_account (company_account_id),
  KEY idx_fin_receivable_mutation (initial_mutation_id),
  CONSTRAINT fk_fin_receivable_party FOREIGN KEY (party_id) REFERENCES fin_relation_party(id),
  CONSTRAINT fk_fin_receivable_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id),
  CONSTRAINT fk_fin_receivable_mutation FOREIGN KEY (initial_mutation_id) REFERENCES fin_account_mutation_log(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_receivable_payment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receivable_id BIGINT UNSIGNED NOT NULL,
  payment_no VARCHAR(60) NOT NULL,
  payment_date DATE NOT NULL,
  company_account_id BIGINT UNSIGNED NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  account_impact_mode ENUM('APPLY_ACCOUNT','KEEP_BALANCE') NOT NULL DEFAULT 'APPLY_ACCOUNT',
  transfer_ref_no VARCHAR(80) NULL,
  mutation_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_receivable_payment_no (payment_no),
  KEY idx_fin_receivable_payment_header (receivable_id),
  KEY idx_fin_receivable_payment_date (payment_date),
  KEY idx_fin_receivable_payment_account (company_account_id),
  KEY idx_fin_receivable_payment_mutation (mutation_id),
  CONSTRAINT fk_fin_receivable_payment_header FOREIGN KEY (receivable_id) REFERENCES fin_receivable(id),
  CONSTRAINT fk_fin_receivable_payment_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id),
  CONSTRAINT fk_fin_receivable_payment_mutation FOREIGN KEY (mutation_id) REFERENCES fin_account_mutation_log(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'fin_relation_party' AS table_name, COUNT(*) AS total_rows FROM fin_relation_party
UNION ALL
SELECT 'fin_payable', COUNT(*) FROM fin_payable
UNION ALL
SELECT 'fin_payable_payment', COUNT(*) FROM fin_payable_payment
UNION ALL
SELECT 'fin_receivable', COUNT(*) FROM fin_receivable
UNION ALL
SELECT 'fin_receivable_payment', COUNT(*) FROM fin_receivable_payment;
