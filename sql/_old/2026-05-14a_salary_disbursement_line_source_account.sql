SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE pay_salary_disbursement_line
  ADD COLUMN IF NOT EXISTS company_account_id BIGINT UNSIGNED NULL AFTER employee_id,
  ADD KEY IF NOT EXISTS idx_pay_salary_disb_line_company_account (company_account_id);

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_salary_disbursement_line'
    AND CONSTRAINT_NAME = 'fk_pay_salary_disb_line_company_account'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk := IF(
  @fk_exists = 0,
  'ALTER TABLE pay_salary_disbursement_line ADD CONSTRAINT fk_pay_salary_disb_line_company_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE st_fk FROM @sql_fk;
EXECUTE st_fk;
DEALLOCATE PREPARE st_fk;

UPDATE pay_salary_disbursement_line l
JOIN pay_salary_disbursement h ON h.id = l.disbursement_id
SET l.company_account_id = h.company_account_id
WHERE l.company_account_id IS NULL
  AND h.company_account_id IS NOT NULL;

COMMIT;
