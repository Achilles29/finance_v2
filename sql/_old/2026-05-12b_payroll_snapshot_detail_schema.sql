SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE pay_payroll_period
  ADD COLUMN IF NOT EXISTS rounding_mode ENUM('NONE','UP_1000') NOT NULL DEFAULT 'NONE' AFTER period_end;

ALTER TABLE pay_payroll_result
  ADD COLUMN IF NOT EXISTS basic_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER overtime_hours,
  ADD COLUMN IF NOT EXISTS allowance_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER basic_total,
  ADD COLUMN IF NOT EXISTS meal_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER allowance_total,
  ADD COLUMN IF NOT EXISTS overtime_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER meal_total,
  ADD COLUMN IF NOT EXISTS manual_addition_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER overtime_total,
  ADD COLUMN IF NOT EXISTS late_deduction_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_addition_total,
  ADD COLUMN IF NOT EXISTS alpha_deduction_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER late_deduction_total,
  ADD COLUMN IF NOT EXISTS manual_deduction_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER alpha_deduction_total,
  ADD COLUMN IF NOT EXISTS cash_advance_cut_total DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_deduction_total,
  ADD COLUMN IF NOT EXISTS net_pay_raw DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_deduction,
  ADD COLUMN IF NOT EXISTS rounding_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER net_pay_raw;

ALTER TABLE pay_salary_disbursement_line
  ADD COLUMN IF NOT EXISTS basic_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER employee_id,
  ADD COLUMN IF NOT EXISTS allowance_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER basic_total_snapshot,
  ADD COLUMN IF NOT EXISTS meal_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER allowance_total_snapshot,
  ADD COLUMN IF NOT EXISTS overtime_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER meal_total_snapshot,
  ADD COLUMN IF NOT EXISTS manual_addition_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER overtime_total_snapshot,
  ADD COLUMN IF NOT EXISTS late_deduction_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_addition_total_snapshot,
  ADD COLUMN IF NOT EXISTS alpha_deduction_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER late_deduction_total_snapshot,
  ADD COLUMN IF NOT EXISTS manual_deduction_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER alpha_deduction_total_snapshot,
  ADD COLUMN IF NOT EXISTS cash_advance_cut_total_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_deduction_total_snapshot,
  ADD COLUMN IF NOT EXISTS gross_pay_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER cash_advance_cut_total_snapshot,
  ADD COLUMN IF NOT EXISTS total_deduction_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER gross_pay_snapshot,
  ADD COLUMN IF NOT EXISTS net_pay_raw_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_deduction_snapshot,
  ADD COLUMN IF NOT EXISTS rounding_adjustment_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER net_pay_raw_snapshot,
  ADD COLUMN IF NOT EXISTS net_pay_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER rounding_adjustment_snapshot;

COMMIT;
