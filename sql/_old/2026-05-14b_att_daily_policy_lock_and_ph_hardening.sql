SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE att_daily
  ADD COLUMN IF NOT EXISTS policy_snapshot_id BIGINT UNSIGNED NULL AFTER source_type,
  ADD COLUMN IF NOT EXISTS policy_snapshot_code VARCHAR(40) NULL AFTER policy_snapshot_id,
  ADD COLUMN IF NOT EXISTS policy_snapshot_name VARCHAR(120) NULL AFTER policy_snapshot_code,
  ADD COLUMN IF NOT EXISTS attendance_mode_snapshot VARCHAR(20) NULL AFTER policy_snapshot_name,
  ADD COLUMN IF NOT EXISTS meal_mode_snapshot VARCHAR(20) NULL AFTER attendance_mode_snapshot,
  ADD COLUMN IF NOT EXISTS prorate_scope_snapshot VARCHAR(20) NULL AFTER meal_mode_snapshot,
  ADD COLUMN IF NOT EXISTS overtime_mode_snapshot VARCHAR(20) NULL AFTER prorate_scope_snapshot,
  ADD COLUMN IF NOT EXISTS allowance_late_treatment_snapshot VARCHAR(30) NULL AFTER overtime_mode_snapshot,
  ADD COLUMN IF NOT EXISTS enable_late_deduction_snapshot TINYINT(1) NOT NULL DEFAULT 1 AFTER allowance_late_treatment_snapshot,
  ADD COLUMN IF NOT EXISTS enable_alpha_deduction_snapshot TINYINT(1) NOT NULL DEFAULT 1 AFTER enable_late_deduction_snapshot,
  ADD COLUMN IF NOT EXISTS late_deduction_per_minute_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER enable_alpha_deduction_snapshot,
  ADD COLUMN IF NOT EXISTS alpha_deduction_per_day_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER late_deduction_per_minute_snapshot,
  ADD COLUMN IF NOT EXISTS work_days_snapshot INT UNSIGNED NOT NULL DEFAULT 26 AFTER alpha_deduction_per_day_snapshot;

SET @policy_id := NULL;
SET @policy_code := 'FINANCE_DEFAULT';
SET @policy_name := 'Finance Default Policy';
SET @attendance_mode := 'DAILY';
SET @meal_mode := 'MONTHLY';
SET @prorate_scope := 'BASIC_ONLY';
SET @overtime_mode := 'AUTO';
SET @allowance_late_treatment := 'FULL_IF_PRESENT';
SET @enable_late_deduction := 1;
SET @enable_alpha_deduction := 1;
SET @late_deduction_per_minute := 0;
SET @alpha_deduction_per_day := 0;
SET @work_days := 26;

SELECT
  id,
  COALESCE(NULLIF(policy_code, ''), 'FINANCE_DEFAULT') AS policy_code,
  COALESCE(NULLIF(policy_name, ''), 'Finance Default Policy') AS policy_name,
  COALESCE(NULLIF(attendance_calc_mode, ''), 'DAILY') AS attendance_calc_mode,
  COALESCE(NULLIF(meal_calc_mode, ''), 'MONTHLY') AS meal_calc_mode,
  COALESCE(NULLIF(prorate_deduction_scope, ''), COALESCE(NULLIF(payroll_late_deduction_scope, ''), 'BASIC_ONLY')) AS prorate_scope,
  COALESCE(NULLIF(overtime_calc_mode, ''), 'AUTO') AS overtime_calc_mode,
  COALESCE(NULLIF(allowance_late_treatment, ''), 'FULL_IF_PRESENT') AS allowance_late_treatment,
  COALESCE(enable_late_deduction, 1) AS enable_late_deduction,
  COALESCE(enable_alpha_deduction, 1) AS enable_alpha_deduction,
  COALESCE(late_deduction_per_minute, 0) AS late_deduction_per_minute,
  COALESCE(alpha_deduction_per_day, 0) AS alpha_deduction_per_day,
  COALESCE(default_work_days_per_month, 26) AS default_work_days_per_month
INTO
  @policy_id,
  @policy_code,
  @policy_name,
  @attendance_mode,
  @meal_mode,
  @prorate_scope,
  @overtime_mode,
  @allowance_late_treatment,
  @enable_late_deduction,
  @enable_alpha_deduction,
  @late_deduction_per_minute,
  @alpha_deduction_per_day,
  @work_days
FROM att_attendance_policy
WHERE is_active = 1
ORDER BY id DESC
LIMIT 1;

UPDATE att_daily
SET policy_snapshot_id = COALESCE(policy_snapshot_id, @policy_id),
    policy_snapshot_code = COALESCE(NULLIF(policy_snapshot_code, ''), @policy_code),
    policy_snapshot_name = COALESCE(NULLIF(policy_snapshot_name, ''), @policy_name),
    attendance_mode_snapshot = COALESCE(NULLIF(attendance_mode_snapshot, ''), @attendance_mode),
    meal_mode_snapshot = COALESCE(NULLIF(meal_mode_snapshot, ''), @meal_mode),
    prorate_scope_snapshot = COALESCE(NULLIF(prorate_scope_snapshot, ''), @prorate_scope),
    overtime_mode_snapshot = COALESCE(NULLIF(overtime_mode_snapshot, ''), @overtime_mode),
    allowance_late_treatment_snapshot = COALESCE(NULLIF(allowance_late_treatment_snapshot, ''), @allowance_late_treatment),
    enable_late_deduction_snapshot = COALESCE(enable_late_deduction_snapshot, @enable_late_deduction),
    enable_alpha_deduction_snapshot = COALESCE(enable_alpha_deduction_snapshot, @enable_alpha_deduction),
    late_deduction_per_minute_snapshot = COALESCE(late_deduction_per_minute_snapshot, @late_deduction_per_minute),
    alpha_deduction_per_day_snapshot = COALESCE(alpha_deduction_per_day_snapshot, @alpha_deduction_per_day),
    work_days_snapshot = COALESCE(work_days_snapshot, @work_days)
WHERE policy_snapshot_id IS NULL
   OR policy_snapshot_code IS NULL OR policy_snapshot_code = ''
   OR policy_snapshot_name IS NULL OR policy_snapshot_name = ''
   OR attendance_mode_snapshot IS NULL OR attendance_mode_snapshot = ''
   OR meal_mode_snapshot IS NULL OR meal_mode_snapshot = ''
   OR prorate_scope_snapshot IS NULL OR prorate_scope_snapshot = ''
   OR overtime_mode_snapshot IS NULL OR overtime_mode_snapshot = ''
   OR allowance_late_treatment_snapshot IS NULL OR allowance_late_treatment_snapshot = '';

DELETE l1
FROM att_employee_ph_ledger l1
JOIN att_employee_ph_ledger l2
  ON l1.id > l2.id
 AND l1.employee_id = l2.employee_id
 AND l1.tx_type = l2.tx_type
 AND l1.ref_table = l2.ref_table
 AND COALESCE(l1.ref_id, 0) = COALESCE(l2.ref_id, 0)
WHERE l1.tx_type = 'GRANT'
  AND l1.ref_table = 'att_daily'
  AND l1.ref_id IS NOT NULL
  AND l2.ref_id IS NOT NULL;

SET @ux_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'att_employee_ph_ledger'
    AND INDEX_NAME = 'uk_att_employee_ph_ledger_grant_ref'
);
SET @sql_ux := IF(
  @ux_exists = 0,
  'ALTER TABLE att_employee_ph_ledger ADD UNIQUE KEY uk_att_employee_ph_ledger_grant_ref (employee_id, tx_type, ref_table, ref_id)',
  'SELECT 1'
);
PREPARE st_ux FROM @sql_ux;
EXECUTE st_ux;
DEALLOCATE PREPARE st_ux;

COMMIT;
