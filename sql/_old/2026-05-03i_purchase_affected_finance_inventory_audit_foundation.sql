SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6I - Tabel terdampak Purchase (Rekening, Kas, Stok, Audit)
-- Tujuan:
-- 1) Metode pembayaran purchase berbasis akun perusahaan tunggal (termasuk CASH)
-- 2) Siapkan fondasi stok gudang + stok divisi yang dipengaruhi receipt purchase
-- 3) Siapkan log transaksi untuk audit trail lintas modul
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Akun perusahaan (bank/e-wallet/cash)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fin_company_account (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  account_code VARCHAR(40) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  account_type ENUM('BANK','EWALLET','CASH','OTHER') NOT NULL DEFAULT 'BANK',
  bank_name VARCHAR(120) NULL,
  account_no VARCHAR(80) NULL,
  account_holder VARCHAR(120) NULL,
  currency_code VARCHAR(10) NOT NULL DEFAULT 'IDR',
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  current_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_fin_company_account_code (account_code),
  KEY idx_fin_company_account_type (account_type),
  KEY idx_fin_company_account_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kanal pembayaran purchase berbasis akun perusahaan
CREATE TABLE IF NOT EXISTS pur_payment_channel (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  channel_code VARCHAR(50) NOT NULL,
  channel_name VARCHAR(150) NOT NULL,
  channel_type ENUM('ACCOUNT') NOT NULL DEFAULT 'ACCOUNT',
  company_account_id BIGINT UNSIGNED NULL,
  requires_due_date TINYINT(1) NOT NULL DEFAULT 0,
  requires_reference_no TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_payment_channel_code (channel_code),
  KEY idx_pur_payment_channel_account (company_account_id),
  CONSTRAINT fk_pur_payment_channel_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- B. Upgrade payment plan purchase agar pakai channel
-- ------------------------------------------------------------
ALTER TABLE pur_purchase_payment_plan
  ADD COLUMN IF NOT EXISTS payment_channel_id BIGINT UNSIGNED NULL AFTER payment_method_id,
  ADD COLUMN IF NOT EXISTS paid_from_account_id BIGINT UNSIGNED NULL AFTER payment_channel_id,
  ADD COLUMN IF NOT EXISTS payment_date DATE NULL AFTER due_date,
  ADD COLUMN IF NOT EXISTS transaction_no VARCHAR(80) NULL AFTER reference_no;

ALTER TABLE pur_purchase_payment_plan
  MODIFY COLUMN payment_method_id BIGINT UNSIGNED NULL,
  ADD KEY IF NOT EXISTS idx_pur_payment_plan_channel (payment_channel_id),
  ADD KEY IF NOT EXISTS idx_pur_payment_plan_paid_account (paid_from_account_id),
  ADD KEY IF NOT EXISTS idx_pur_payment_plan_txn_no (transaction_no),
  ADD CONSTRAINT fk_pur_payment_plan_channel FOREIGN KEY (payment_channel_id) REFERENCES pur_payment_channel(id),
  ADD CONSTRAINT fk_pur_payment_plan_paid_account FOREIGN KEY (paid_from_account_id) REFERENCES fin_company_account(id);

-- ------------------------------------------------------------
-- C. Stok gudang + stok divisi (terdampak purchase)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_warehouse_stock_balance (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NOT NULL,
  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  qty_buy_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  last_receipt_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_wh_stock_item_profile (item_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_wh_stock_item (item_id),
  KEY idx_inv_wh_stock_last_receipt_line (last_receipt_line_id),
  CONSTRAINT fk_inv_wh_stock_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_wh_stock_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_stock_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_stock_receipt_line FOREIGN KEY (last_receipt_line_id) REFERENCES pur_purchase_receipt_line(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_division_stock_balance (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  division_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  qty_buy_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  last_receipt_line_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_div_stock_scope (division_id, item_id, material_id, buy_uom_id, content_uom_id, profile_key),
  KEY idx_inv_div_stock_division (division_id),
  KEY idx_inv_div_stock_item (item_id),
  KEY idx_inv_div_stock_material (material_id),
  KEY idx_inv_div_stock_last_receipt_line (last_receipt_line_id),
  CONSTRAINT fk_inv_div_stock_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_div_stock_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_div_stock_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_div_stock_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_stock_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_stock_receipt_line FOREIGN KEY (last_receipt_line_id) REFERENCES pur_purchase_receipt_line(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_movement_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  movement_no VARCHAR(60) NOT NULL,
  movement_date DATE NOT NULL,
  movement_scope ENUM('WAREHOUSE','DIVISION') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  movement_type ENUM('PURCHASE_IN','TRANSFER_IN','TRANSFER_OUT','USAGE_OUT','DISCARDED_OUT','SPOIL_OUT','WASTE_OUT','ADJUSTMENT') NOT NULL,
  ref_table VARCHAR(80) NULL,
  ref_id BIGINT UNSIGNED NULL,
  receipt_id BIGINT UNSIGNED NULL,
  receipt_line_id BIGINT UNSIGNED NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  qty_buy_delta DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_delta DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_buy_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_stock_movement_no (movement_no),
  KEY idx_inv_stock_movement_date (movement_date),
  KEY idx_inv_stock_movement_scope (movement_scope, division_id),
  KEY idx_inv_stock_movement_ref (ref_table, ref_id),
  KEY idx_inv_stock_movement_receipt (receipt_id, receipt_line_id),
  KEY idx_inv_stock_movement_item (item_id),
  KEY idx_inv_stock_movement_material (material_id),
  CONSTRAINT fk_inv_stock_movement_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_stock_movement_receipt FOREIGN KEY (receipt_id) REFERENCES pur_purchase_receipt(id),
  CONSTRAINT fk_inv_stock_movement_receipt_line FOREIGN KEY (receipt_line_id) REFERENCES pur_purchase_receipt_line(id),
  CONSTRAINT fk_inv_stock_movement_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_stock_movement_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_stock_movement_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_stock_movement_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- D. Audit transaksi lintas modul
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS aud_transaction_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  module_code VARCHAR(40) NOT NULL,
  action_code VARCHAR(40) NOT NULL,
  entity_table VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  transaction_no VARCHAR(80) NULL,
  ref_table VARCHAR(80) NULL,
  ref_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  source_ip VARCHAR(45) NULL,
  before_payload JSON NULL,
  after_payload JSON NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aud_transaction_module (module_code),
  KEY idx_aud_transaction_entity (entity_table, entity_id),
  KEY idx_aud_transaction_no (transaction_no),
  KEY idx_aud_transaction_ref (ref_table, ref_id),
  KEY idx_aud_transaction_actor (actor_user_id),
  KEY idx_aud_transaction_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- E. Seed minimal rekening + kas + channel pembayaran
-- ------------------------------------------------------------
INSERT INTO fin_company_account (
  account_code, account_name, account_type, bank_name, account_no, account_holder,
  currency_code, opening_balance, current_balance, is_default, notes, is_active
) VALUES
('BANK-BCA-OPR', 'BCA Operasional', 'BANK', 'BCA', NULL, NULL, 'IDR', 0, 0, 1, 'Rekening operasional utama', 1)
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  bank_name = VALUES(bank_name),
  currency_code = VALUES(currency_code),
  is_default = VALUES(is_default),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO fin_company_account (
  account_code, account_name, account_type, bank_name, account_no, account_holder,
  currency_code, opening_balance, current_balance, is_default, notes, is_active
) VALUES
('CASH-UTAMA', 'Kas Utama', 'CASH', NULL, NULL, NULL, 'IDR', 0, 0, 0, 'Kas utama perusahaan', 1)
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  is_default = VALUES(is_default),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO pur_payment_channel (
  channel_code, channel_name, channel_type, company_account_id,
  requires_due_date, requires_reference_no, sort_order, notes, is_active
)
SELECT
  'BANK-OPR',
  'Pembayaran via Rekening Operasional',
  'ACCOUNT',
  acc.id,
  0,
  1,
  10,
  'Channel default dari rekening operasional',
  1
FROM fin_company_account acc
WHERE acc.account_code = 'BANK-BCA-OPR'
ON DUPLICATE KEY UPDATE
  channel_name = VALUES(channel_name),
  channel_type = VALUES(channel_type),
  company_account_id = VALUES(company_account_id),
  requires_due_date = VALUES(requires_due_date),
  requires_reference_no = VALUES(requires_reference_no),
  sort_order = VALUES(sort_order),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO pur_payment_channel (
  channel_code, channel_name, channel_type, company_account_id,
  requires_due_date, requires_reference_no, sort_order, notes, is_active
)
SELECT
  'CASH-UTAMA',
  'Pembayaran via Kas Utama',
  'ACCOUNT',
  acc.id,
  0,
  0,
  20,
  'Channel default dari kas utama',
  1
FROM fin_company_account acc
WHERE acc.account_code = 'CASH-UTAMA'
ON DUPLICATE KEY UPDATE
  channel_name = VALUES(channel_name),
  channel_type = VALUES(channel_type),
  company_account_id = VALUES(company_account_id),
  requires_due_date = VALUES(requires_due_date),
  requires_reference_no = VALUES(requires_reference_no),
  sort_order = VALUES(sort_order),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'fin_company_account' AS table_name, COUNT(*) AS total_rows FROM fin_company_account
UNION ALL
SELECT 'pur_payment_channel', COUNT(*) FROM pur_payment_channel
UNION ALL
SELECT 'inv_warehouse_stock_balance', COUNT(*) FROM inv_warehouse_stock_balance
UNION ALL
SELECT 'inv_division_stock_balance', COUNT(*) FROM inv_division_stock_balance
UNION ALL
SELECT 'inv_stock_movement_log', COUNT(*) FROM inv_stock_movement_log
UNION ALL
SELECT 'aud_transaction_log', COUNT(*) FROM aud_transaction_log;
