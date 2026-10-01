SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE att_daily
  ADD COLUMN IF NOT EXISTS manual_addition_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER overtime_pay,
  ADD COLUMN IF NOT EXISTS manual_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_addition_amount,
  ADD COLUMN IF NOT EXISTS manual_adjustment_net_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER manual_deduction_amount;

COMMIT;
