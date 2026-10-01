SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A4B - POS Loyalty Runtime / Voucher Runtime
-- File   : 2026-05-28a4b_pos_loyalty_runtime_foundation.sql
-- Tujuan :
-- 1) Menyiapkan ledger point dan stamp yang bergantung ke member + order/payment
-- 2) Menyiapkan issue dan redemption voucher runtime
-- Catatan:
-- - Jalankan setelah:
--   a1 outlet/shift/session
--   a2 member/rule/campaign loyalty
--   a4 order/payment
-- ============================================================

START TRANSACTION;

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

COMMIT;

-- Quick check
SELECT 'pos_point_ledger' AS table_name, COUNT(*) AS total_rows FROM pos_point_ledger
UNION ALL
SELECT 'pos_stamp_ledger', COUNT(*) FROM pos_stamp_ledger
UNION ALL
SELECT 'pos_voucher_issue', COUNT(*) FROM pos_voucher_issue
UNION ALL
SELECT 'pos_voucher_redemption', COUNT(*) FROM pos_voucher_redemption;
