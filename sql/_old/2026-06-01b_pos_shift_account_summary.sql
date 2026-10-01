CREATE TABLE IF NOT EXISTS pos_shift_account_summary (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shift_id BIGINT UNSIGNED NOT NULL,
  company_account_id BIGINT UNSIGNED NULL,
  account_code VARCHAR(100) NULL,
  account_name VARCHAR(255) NULL,
  bank_name VARCHAR(255) NULL,
  account_label VARCHAR(255) NULL,
  gross_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pos_shift_account_summary_shift (shift_id),
  KEY idx_pos_shift_account_summary_account (company_account_id),
  CONSTRAINT fk_pos_shift_account_summary_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_shift_account_summary_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;