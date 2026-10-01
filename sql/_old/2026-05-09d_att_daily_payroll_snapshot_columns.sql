SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE att_daily
  ADD COLUMN IF NOT EXISTS snapshot_basic_salary DECIMAL(18,2) NULL AFTER source_type,
  ADD COLUMN IF NOT EXISTS snapshot_position_allowance DECIMAL(18,2) NULL AFTER snapshot_basic_salary,
  ADD COLUMN IF NOT EXISTS snapshot_objective_allowance DECIMAL(18,2) NULL AFTER snapshot_position_allowance,
  ADD COLUMN IF NOT EXISTS snapshot_meal_rate DECIMAL(18,2) NULL AFTER snapshot_objective_allowance,
  ADD COLUMN IF NOT EXISTS snapshot_overtime_rate DECIMAL(18,2) NULL AFTER snapshot_meal_rate,
  ADD COLUMN IF NOT EXISTS basic_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER snapshot_overtime_rate,
  ADD COLUMN IF NOT EXISTS allowance_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER basic_amount,
  ADD COLUMN IF NOT EXISTS meal_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER allowance_amount,
  ADD COLUMN IF NOT EXISTS late_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER meal_amount,
  ADD COLUMN IF NOT EXISTS alpha_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER late_deduction_amount,
  ADD COLUMN IF NOT EXISTS gross_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER alpha_deduction_amount,
  ADD COLUMN IF NOT EXISTS net_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER gross_amount;

UPDATE att_daily ad
JOIN org_employee e ON e.id = ad.employee_id
SET ad.snapshot_basic_salary = COALESCE(ad.snapshot_basic_salary, e.basic_salary),
    ad.snapshot_position_allowance = COALESCE(ad.snapshot_position_allowance, e.position_allowance),
    ad.snapshot_objective_allowance = COALESCE(ad.snapshot_objective_allowance, e.objective_allowance),
    ad.snapshot_meal_rate = COALESCE(ad.snapshot_meal_rate, e.meal_rate),
    ad.snapshot_overtime_rate = COALESCE(ad.snapshot_overtime_rate, e.overtime_rate)
WHERE ad.snapshot_basic_salary IS NULL
   OR ad.snapshot_position_allowance IS NULL
   OR ad.snapshot_objective_allowance IS NULL
   OR ad.snapshot_meal_rate IS NULL
   OR ad.snapshot_overtime_rate IS NULL;

UPDATE att_daily
SET net_amount = COALESCE(NULLIF(net_amount, 0), daily_salary_amount),
    gross_amount = CASE
      WHEN COALESCE(gross_amount, 0) > 0 THEN gross_amount
      ELSE COALESCE(daily_salary_amount, 0) + COALESCE(late_deduction_amount, 0) + COALESCE(alpha_deduction_amount, 0)
    END
WHERE 1=1;

COMMIT;
