SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE pay_cash_advance
  ADD COLUMN IF NOT EXISTS company_account_id BIGINT UNSIGNED NULL AFTER amount;

ALTER TABLE pay_cash_advance_installment
  ADD COLUMN IF NOT EXISTS payment_method ENUM('CASH','TRANSFER','SALARY_CUT') NOT NULL DEFAULT 'CASH' AFTER status,
  ADD COLUMN IF NOT EXISTS company_account_id BIGINT UNSIGNED NULL AFTER payment_method,
  ADD COLUMN IF NOT EXISTS payment_date DATE NULL AFTER company_account_id,
  ADD COLUMN IF NOT EXISTS salary_cut_period CHAR(7) NULL AFTER payment_date,
  ADD COLUMN IF NOT EXISTS salary_cut_date DATE NULL AFTER salary_cut_period,
  ADD COLUMN IF NOT EXISTS transfer_ref_no VARCHAR(100) NULL AFTER salary_cut_date,
  ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER transfer_ref_no;

ALTER TABLE pay_salary_disbursement_line
  ADD COLUMN IF NOT EXISTS employee_bank_name VARCHAR(120) NULL AFTER employee_id,
  ADD COLUMN IF NOT EXISTS employee_bank_account_no VARCHAR(60) NULL AFTER employee_bank_name,
  ADD COLUMN IF NOT EXISTS employee_bank_account_name VARCHAR(150) NULL AFTER employee_bank_account_no;

SET @fk_ca_account_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_cash_advance'
    AND CONSTRAINT_NAME = 'fk_pay_cash_advance_account'
);
SET @sql_ca_fk := IF(
  @fk_ca_account_exists = 0,
  'ALTER TABLE pay_cash_advance ADD CONSTRAINT fk_pay_cash_advance_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE st_ca_fk FROM @sql_ca_fk;
EXECUTE st_ca_fk;
DEALLOCATE PREPARE st_ca_fk;

SET @fk_ca_inst_account_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_cash_advance_installment'
    AND CONSTRAINT_NAME = 'fk_pay_cash_advance_inst_account'
);
SET @sql_ca_inst_fk := IF(
  @fk_ca_inst_account_exists = 0,
  'ALTER TABLE pay_cash_advance_installment ADD CONSTRAINT fk_pay_cash_advance_inst_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE st_ca_inst_fk FROM @sql_ca_inst_fk;
EXECUTE st_ca_inst_fk;
DEALLOCATE PREPARE st_ca_inst_fk;

UPDATE pay_salary_disbursement_line l
JOIN org_employee e ON e.id = l.employee_id
SET l.employee_bank_name = COALESCE(NULLIF(l.employee_bank_name,''), NULLIF(e.bank_name,'')),
    l.employee_bank_account_no = COALESCE(NULLIF(l.employee_bank_account_no,''), NULLIF(e.bank_account_no,'')),
    l.employee_bank_account_name = COALESCE(NULLIF(l.employee_bank_account_name,''), NULLIF(e.bank_account_name,''))
WHERE l.employee_id IS NOT NULL;

COMMIT;
