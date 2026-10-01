SET NAMES utf8mb4;

-- Contract and hr_contract_comp_snapshot are now the sole live compensation
-- source. att_daily preserves the immutable amount used by historical payroll.
-- Run this migration once after the contract-first foundation migrations.

ALTER TABLE org_employee
  DROP COLUMN basic_salary,
  DROP COLUMN position_allowance,
  DROP COLUMN objective_allowance,
  DROP COLUMN meal_rate,
  DROP COLUMN overtime_rate;

-- Expected result: zero rows. The columns must no longer be recreated.
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'org_employee'
  AND COLUMN_NAME IN (
    'basic_salary',
    'position_allowance',
    'objective_allowance',
    'meal_rate',
    'overtime_rate'
  );
