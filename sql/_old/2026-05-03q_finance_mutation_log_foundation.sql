SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6Q - Finance Mutation Log Foundation
-- Tujuan:
-- 1) Menyediakan log mutasi rekening (manual + auto dari purchase payment)
-- 2) Menjaga jejak saldo before/after untuk audit
-- ============================================================
START TRANSACTION;

CREATE TABLE IF NOT EXISTS fin_account_mutation_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  mutation_no VARCHAR(60) NOT NULL,
  mutation_date DATE NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  mutation_type ENUM('IN','OUT') NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance_before DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
  ref_module VARCHAR(40) NULL,
  ref_table VARCHAR(80) NULL,
  ref_id BIGINT UNSIGNED NULL,
  ref_no VARCHAR(80) NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_fin_account_mutation_no (mutation_no),
  KEY idx_fin_account_mutation_date (mutation_date),
  KEY idx_fin_account_mutation_account (account_id),
  KEY idx_fin_account_mutation_ref (ref_module, ref_table, ref_id),
  CONSTRAINT fk_fin_account_mutation_account FOREIGN KEY (account_id) REFERENCES fin_company_account(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'fin_account_mutation_log' AS table_name, COUNT(*) AS total_rows FROM fin_account_mutation_log;
