CREATE TABLE IF NOT EXISTS pos_shift_cash_denomination (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shift_id BIGINT UNSIGNED NOT NULL,
  denomination_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  qty_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_shift_cash_denomination_shift_denom (shift_id, denomination_amount),
  KEY idx_pos_shift_cash_denom_shift (shift_id),
  CONSTRAINT fk_pos_shift_cash_denom_shift FOREIGN KEY (shift_id) REFERENCES pos_shift(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;