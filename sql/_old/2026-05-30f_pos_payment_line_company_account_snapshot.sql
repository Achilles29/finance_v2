SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30f_pos_payment_line_company_account_snapshot.sql
-- Tujuan :
-- 1) Menyimpan snapshot rekening perusahaan di payment line POS
-- 2) Menjaga reversal DP / refund tetap kembali ke rekening sumber yang sama
-- 3) Aman dijalankan ulang
-- ============================================================

START TRANSACTION;

SET @has_col_company_account_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment_line'
    AND COLUMN_NAME = 'company_account_id'
);

SET @sql_add_col_company_account_id := IF(
  @has_col_company_account_id = 0,
  'ALTER TABLE pos_payment_line ADD COLUMN company_account_id BIGINT UNSIGNED NULL AFTER payment_method_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_col_company_account_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_company_account_id := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment_line'
    AND INDEX_NAME = 'idx_pos_payment_line_company_account'
);
SET @sql_add_idx_company_account_id := IF(
  @has_idx_company_account_id = 0,
  'ALTER TABLE pos_payment_line ADD KEY idx_pos_payment_line_company_account (company_account_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_idx_company_account_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_company_account_id := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment_line'
    AND CONSTRAINT_NAME = 'fk_pos_payment_line_company_account'
);
SET @sql_add_fk_company_account_id := IF(
  @has_fk_company_account_id = 0,
  'ALTER TABLE pos_payment_line ADD CONSTRAINT fk_pos_payment_line_company_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fk_company_account_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pos_payment_line pl
JOIN pos_payment_method pm ON pm.id = pl.payment_method_id
SET pl.company_account_id = pm.company_account_id
WHERE pl.company_account_id IS NULL;

COMMIT;

SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_payment_line'
  AND COLUMN_NAME = 'company_account_id';
