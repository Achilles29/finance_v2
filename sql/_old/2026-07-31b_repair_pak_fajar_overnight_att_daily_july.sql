SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-31b_repair_pak_fajar_overnight_att_daily_july.sql
-- Tujuan :
-- 1) Memperbaiki att_daily PAK FAJAR (EMP-00005) bulan Juli 2026
--    untuk shift SECURITY lintas hari yang checkout-nya tersimpan
--    pada tanggal yang sama dengan check-in.
-- 2) Menghitung ulang menit kerja, komponen gaji harian, potongan
--    manual/kasbon, dan daily_salary_amount.
-- 3) Jika payroll period 2026-07 sudah ada dan belum ada batch gaji
--    aktif, hasil payroll Pak Fajar ikut disegarkan supaya THP Riil
--    tidak lagi negatif akibat work_minutes 0.
--
-- Catatan:
-- - Scope sengaja sempit: employee_id 5 / EMP-00005 / Juli 2026.
-- - Mode uang makan mengikuti policy: jika CUSTOM, meal masuk gross
--   tetapi tidak masuk net/THP karena dicairkan via modul uang makan.
-- - Jika periode sudah punya salary disbursement aktif, script tetap
--   memperbaiki att_daily, tetapi tidak mengubah pay_payroll_result.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair overnight att_daily Pak Fajar July 2026 2026-07-31';
SET @employee_id := 5;
SET @period_code := '2026-07';
SET @date_start := '2026-07-01';
SET @date_end := '2026-07-31';

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_policy;
CREATE TEMPORARY TABLE tmp_pf_july_policy AS
SELECT
  p.id AS policy_id,
  COALESCE(p.policy_code, '') AS policy_code,
  COALESCE(p.policy_name, '') AS policy_name,
  UPPER(COALESCE(NULLIF(p.attendance_calc_mode, ''), 'DAILY')) AS attendance_calc_mode,
  UPPER(COALESCE(NULLIF(p.meal_calc_mode, ''), 'MONTHLY')) AS meal_calc_mode,
  UPPER(COALESCE(NULLIF(p.prorate_deduction_scope, ''), COALESCE(NULLIF(p.payroll_late_deduction_scope, ''), 'BASIC_ONLY'))) AS prorate_scope,
  UPPER(COALESCE(NULLIF(p.overtime_calc_mode, ''), 'AUTO')) AS overtime_calc_mode,
  UPPER(COALESCE(NULLIF(p.allowance_late_treatment, ''), 'FULL_IF_PRESENT')) AS allowance_late_treatment,
  COALESCE(p.enable_late_deduction, 1) AS enable_late_deduction,
  COALESCE(p.enable_alpha_deduction, 1) AS enable_alpha_deduction,
  COALESCE(p.late_deduction_per_minute, 0) AS late_deduction_per_minute,
  COALESCE(p.alpha_deduction_per_day, 0) AS alpha_deduction_per_day,
  GREATEST(COALESCE(p.default_work_days_per_month, 26), 1) AS default_work_days_per_month
FROM att_attendance_policy p
ORDER BY COALESCE(p.is_active, 0) DESC, p.id DESC
LIMIT 1;

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_manual_adjustment;
CREATE TEMPORARY TABLE tmp_pf_july_manual_adjustment AS
SELECT
  employee_id,
  adjustment_date AS attendance_date,
  ROUND(SUM(CASE WHEN UPPER(COALESCE(adjustment_kind, '')) = 'ADDITION' THEN COALESCE(amount, 0) ELSE 0 END), 2) AS manual_addition_amount,
  ROUND(SUM(CASE WHEN UPPER(COALESCE(adjustment_kind, '')) = 'DEDUCTION' THEN COALESCE(amount, 0) ELSE 0 END), 2) AS manual_deduction_amount
FROM pay_manual_adjustment
WHERE employee_id = @employee_id
  AND status = 'APPROVED'
  AND adjustment_date BETWEEN @date_start AND @date_end
GROUP BY employee_id, adjustment_date;

ALTER TABLE tmp_pf_july_manual_adjustment
  ADD PRIMARY KEY (employee_id, attendance_date);

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_att_daily_backup;
CREATE TEMPORARY TABLE tmp_pf_july_att_daily_backup AS
SELECT *
FROM att_daily
WHERE employee_id = @employee_id
  AND attendance_date BETWEEN @date_start AND @date_end;

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_recalc;
CREATE TEMPORARY TABLE tmp_pf_july_recalc AS
SELECT
  ad.id,
  ad.employee_id,
  ad.attendance_date,
  ad.shift_id,
  ad.attendance_status,
  ad.checkin_at,
  ad.checkout_at,
  s.start_time,
  s.end_time,
  COALESCE(s.is_overnight, 0) AS is_overnight,
  COALESCE(s.grace_late_minute, 0) AS grace_late_minute,
  COALESCE(ad.snapshot_basic_salary, e.basic_salary, 0) AS basic_salary,
  COALESCE(ad.snapshot_position_allowance, e.position_allowance, 0) AS position_allowance,
  COALESCE(ad.snapshot_objective_allowance, e.objective_allowance, 0) AS objective_allowance,
  COALESCE(ad.snapshot_meal_rate, e.meal_rate, 0) AS meal_rate,
  COALESCE(ad.snapshot_overtime_rate, e.overtime_rate, 0) AS overtime_rate,
  COALESCE(ma.manual_addition_amount, 0.00) AS manual_addition_amount,
  COALESCE(ma.manual_deduction_amount, 0.00) AS manual_deduction_amount,
  CAST(NULL AS DATETIME) AS corrected_checkout_at,
  CAST(NULL AS DATETIME) AS scheduled_start_at,
  CAST(NULL AS DATETIME) AS scheduled_end_at,
  CAST(0 AS SIGNED) AS scheduled_work_minutes,
  CAST(0 AS SIGNED) AS corrected_work_minutes,
  CAST(0 AS SIGNED) AS corrected_late_minutes,
  CAST(0 AS SIGNED) AS corrected_early_leave_minutes,
  CAST(0 AS SIGNED) AS corrected_overtime_minutes,
  CAST(0 AS DECIMAL(18,2)) AS basic_daily_rate,
  CAST(0 AS DECIMAL(18,2)) AS allowance_daily_rate,
  CAST(0 AS DECIMAL(18,2)) AS corrected_basic_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_allowance_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_meal_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_overtime_pay,
  CAST(0 AS DECIMAL(18,2)) AS corrected_late_deduction_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_alpha_deduction_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_gross_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_net_amount,
  CAST(0 AS DECIMAL(18,2)) AS corrected_daily_salary_amount
FROM att_daily ad
JOIN org_employee e ON e.id = ad.employee_id
LEFT JOIN att_shift s ON s.id = ad.shift_id
LEFT JOIN tmp_pf_july_manual_adjustment ma
  ON ma.employee_id = ad.employee_id
 AND ma.attendance_date = ad.attendance_date
WHERE ad.employee_id = @employee_id
  AND ad.attendance_date BETWEEN @date_start AND @date_end;

ALTER TABLE tmp_pf_july_recalc
  ADD PRIMARY KEY (id);

UPDATE tmp_pf_july_recalc r
JOIN tmp_pf_july_policy p
SET
  r.corrected_checkout_at = CASE
    WHEN r.checkin_at IS NOT NULL
      AND r.checkout_at IS NOT NULL
      AND r.checkout_at <= r.checkin_at
      AND (COALESCE(r.is_overnight, 0) = 1 OR COALESCE(r.end_time, '00:00:00') <= COALESCE(r.start_time, '00:00:00'))
      THEN DATE_ADD(r.checkout_at, INTERVAL 1 DAY)
    ELSE r.checkout_at
  END,
  r.scheduled_start_at = CASE WHEN r.start_time IS NOT NULL THEN TIMESTAMP(r.attendance_date, r.start_time) ELSE NULL END,
  r.scheduled_end_at = CASE
    WHEN r.end_time IS NULL THEN NULL
    WHEN COALESCE(r.is_overnight, 0) = 1 OR COALESCE(r.end_time, '00:00:00') <= COALESCE(r.start_time, '00:00:00')
      THEN DATE_ADD(TIMESTAMP(r.attendance_date, r.end_time), INTERVAL 1 DAY)
    ELSE TIMESTAMP(r.attendance_date, r.end_time)
  END,
  r.basic_daily_rate = ROUND(COALESCE(r.basic_salary, 0) / p.default_work_days_per_month, 2),
  r.allowance_daily_rate = ROUND((COALESCE(r.position_allowance, 0) + COALESCE(r.objective_allowance, 0)) / p.default_work_days_per_month, 2);

UPDATE tmp_pf_july_recalc
SET
  scheduled_work_minutes = CASE
    WHEN scheduled_start_at IS NOT NULL AND scheduled_end_at IS NOT NULL AND scheduled_end_at > scheduled_start_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, scheduled_start_at, scheduled_end_at), 0)
    ELSE 0
  END,
  corrected_work_minutes = CASE
    WHEN checkin_at IS NOT NULL AND corrected_checkout_at IS NOT NULL AND corrected_checkout_at > checkin_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, checkin_at, corrected_checkout_at), 0)
    ELSE 0
  END,
  corrected_late_minutes = CASE
    WHEN checkin_at IS NOT NULL AND scheduled_start_at IS NOT NULL
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, scheduled_start_at, checkin_at) - COALESCE(grace_late_minute, 0), 0)
    ELSE 0
  END,
  corrected_early_leave_minutes = CASE
    WHEN corrected_checkout_at IS NOT NULL AND scheduled_end_at IS NOT NULL AND corrected_checkout_at < scheduled_end_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, corrected_checkout_at, scheduled_end_at), 0)
    ELSE 0
  END;

UPDATE tmp_pf_july_recalc r
JOIN tmp_pf_july_policy p
SET
  r.corrected_overtime_minutes = CASE
    WHEN p.overtime_calc_mode = 'AUTO'
      AND r.corrected_checkout_at IS NOT NULL
      AND r.scheduled_end_at IS NOT NULL
      AND r.corrected_checkout_at > r.scheduled_end_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, r.scheduled_end_at, r.corrected_checkout_at), 0)
    ELSE 0
  END,
  r.corrected_work_minutes = CASE
    WHEN p.overtime_calc_mode = 'MANUAL' AND r.scheduled_work_minutes > 0
      THEN LEAST(r.corrected_work_minutes, r.scheduled_work_minutes)
    ELSE r.corrected_work_minutes
  END;

UPDATE tmp_pf_july_recalc r
JOIN tmp_pf_july_policy p
SET
  r.corrected_basic_amount = CASE
    WHEN UPPER(COALESCE(r.attendance_status, 'OFF')) IN ('PRESENT', 'LATE', 'HOLIDAY')
      AND ((r.checkin_at IS NOT NULL AND r.corrected_checkout_at IS NOT NULL AND r.corrected_checkout_at > r.checkin_at) OR UPPER(COALESCE(r.attendance_status, 'OFF')) = 'HOLIDAY')
      THEN ROUND(r.basic_daily_rate, 2)
    ELSE 0.00
  END,
  r.corrected_allowance_amount = CASE
    WHEN UPPER(COALESCE(r.attendance_status, 'OFF')) IN ('PRESENT', 'HOLIDAY')
      AND ((r.checkin_at IS NOT NULL AND r.corrected_checkout_at IS NOT NULL AND r.corrected_checkout_at > r.checkin_at) OR UPPER(COALESCE(r.attendance_status, 'OFF')) = 'HOLIDAY')
      THEN ROUND(r.allowance_daily_rate, 2)
    WHEN UPPER(COALESCE(r.attendance_status, 'OFF')) = 'LATE'
      AND p.allowance_late_treatment <> 'DEDUCT_IF_LATE'
      AND r.checkin_at IS NOT NULL
      AND r.corrected_checkout_at IS NOT NULL
      AND r.corrected_checkout_at > r.checkin_at
      THEN ROUND(r.allowance_daily_rate, 2)
    ELSE 0.00
  END,
  r.corrected_meal_amount = CASE
    WHEN p.meal_calc_mode = 'CUSTOM'
      AND UPPER(COALESCE(r.attendance_status, 'OFF')) IN ('PRESENT', 'LATE', 'HOLIDAY')
      AND r.checkin_at IS NOT NULL
      THEN ROUND(COALESCE(r.meal_rate, 0), 2)
    ELSE 0.00
  END,
  r.corrected_overtime_pay = CASE
    WHEN p.overtime_calc_mode = 'AUTO'
      THEN ROUND((r.corrected_overtime_minutes / 60) * COALESCE(r.overtime_rate, 0), 2)
    ELSE ROUND(COALESCE((SELECT ad.overtime_pay FROM att_daily ad WHERE ad.id = r.id), 0), 2)
  END,
  r.corrected_alpha_deduction_amount = CASE
    WHEN UPPER(COALESCE(r.attendance_status, 'OFF')) = 'ALPHA'
      AND p.enable_alpha_deduction = 1
      AND r.checkin_at IS NOT NULL
      AND r.corrected_checkout_at IS NOT NULL
      AND r.corrected_checkout_at > r.checkin_at
      THEN ROUND(p.alpha_deduction_per_day, 2)
    ELSE 0.00
  END;

UPDATE tmp_pf_july_recalc r
JOIN tmp_pf_july_policy p
SET
  r.corrected_late_deduction_amount = CASE
    WHEN r.checkin_at IS NOT NULL
      AND r.corrected_checkout_at IS NOT NULL
      AND r.corrected_checkout_at > r.checkin_at
      AND p.enable_late_deduction = 1
      AND p.late_deduction_per_minute > 0
      THEN ROUND(r.corrected_late_minutes * p.late_deduction_per_minute, 2)
    WHEN r.checkin_at IS NOT NULL
      AND r.corrected_checkout_at IS NOT NULL
      AND r.corrected_checkout_at > r.checkin_at
      AND p.enable_late_deduction = 0
      AND p.enable_alpha_deduction = 0
      AND r.scheduled_work_minutes > 0
      THEN ROUND(
        CASE
          WHEN p.prorate_scope = 'THP_TOTAL'
            THEN (r.basic_daily_rate + r.allowance_daily_rate + CASE WHEN p.meal_calc_mode = 'CUSTOM' THEN COALESCE(r.meal_rate, 0) ELSE 0 END)
                 * (1 - LEAST(r.corrected_work_minutes / r.scheduled_work_minutes, 1))
          ELSE r.basic_daily_rate * (1 - LEAST(r.corrected_work_minutes / r.scheduled_work_minutes, 1))
        END
      , 2)
    ELSE 0.00
  END;

UPDATE tmp_pf_july_recalc r
JOIN tmp_pf_july_policy p
SET
  r.corrected_gross_amount = ROUND(
    r.corrected_basic_amount + r.corrected_allowance_amount + r.corrected_meal_amount + r.corrected_overtime_pay
  , 2),
  r.corrected_net_amount = ROUND(
    (CASE
      WHEN p.meal_calc_mode = 'CUSTOM'
        THEN r.corrected_basic_amount + r.corrected_allowance_amount + r.corrected_overtime_pay
      ELSE r.corrected_basic_amount + r.corrected_allowance_amount + r.corrected_meal_amount + r.corrected_overtime_pay
    END)
    - r.corrected_late_deduction_amount
    - r.corrected_alpha_deduction_amount
  , 2);

UPDATE tmp_pf_july_recalc
SET corrected_daily_salary_amount = ROUND(corrected_net_amount + manual_addition_amount - manual_deduction_amount, 2);

UPDATE att_daily ad
JOIN tmp_pf_july_recalc t ON t.id = ad.id
JOIN tmp_pf_july_policy p
SET
  ad.checkout_at = t.corrected_checkout_at,
  ad.work_minutes = t.corrected_work_minutes,
  ad.late_minutes = t.corrected_late_minutes,
  ad.early_leave_minutes = t.corrected_early_leave_minutes,
  ad.overtime_minutes = t.corrected_overtime_minutes,
  ad.basic_amount = t.corrected_basic_amount,
  ad.allowance_amount = t.corrected_allowance_amount,
  ad.meal_amount = t.corrected_meal_amount,
  ad.overtime_pay = t.corrected_overtime_pay,
  ad.late_deduction_amount = t.corrected_late_deduction_amount,
  ad.alpha_deduction_amount = t.corrected_alpha_deduction_amount,
  ad.gross_amount = t.corrected_gross_amount,
  ad.net_amount = t.corrected_net_amount,
  ad.manual_addition_amount = t.manual_addition_amount,
  ad.manual_deduction_amount = t.manual_deduction_amount,
  ad.manual_adjustment_net_amount = ROUND(t.manual_addition_amount - t.manual_deduction_amount, 2),
  ad.daily_salary_amount = t.corrected_daily_salary_amount,
  ad.policy_snapshot_id = p.policy_id,
  ad.policy_snapshot_code = p.policy_code,
  ad.policy_snapshot_name = p.policy_name,
  ad.attendance_mode_snapshot = p.attendance_calc_mode,
  ad.meal_mode_snapshot = p.meal_calc_mode,
  ad.prorate_scope_snapshot = p.prorate_scope,
  ad.overtime_mode_snapshot = p.overtime_calc_mode,
  ad.allowance_late_treatment_snapshot = p.allowance_late_treatment,
  ad.enable_late_deduction_snapshot = p.enable_late_deduction,
  ad.enable_alpha_deduction_snapshot = p.enable_alpha_deduction,
  ad.late_deduction_per_minute_snapshot = p.late_deduction_per_minute,
  ad.alpha_deduction_per_day_snapshot = p.alpha_deduction_per_day,
  ad.work_days_snapshot = p.default_work_days_per_month,
  ad.snapshot_basic_salary = ROUND(t.basic_salary, 2),
  ad.snapshot_position_allowance = ROUND(t.position_allowance, 2),
  ad.snapshot_objective_allowance = ROUND(t.objective_allowance, 2),
  ad.snapshot_meal_rate = ROUND(t.meal_rate, 2),
  ad.snapshot_overtime_rate = ROUND(t.overtime_rate, 2),
  ad.remarks = LEFT(TRIM(CONCAT(
    COALESCE(ad.remarks, ''),
    CASE WHEN COALESCE(ad.remarks, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  ad.updated_at = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_payroll_rollup;
CREATE TEMPORARY TABLE tmp_pf_july_payroll_rollup AS
SELECT
  pp.id AS payroll_period_id,
  pp.rounding_mode,
  ad.employee_id,
  e.employee_code,
  e.employee_name,
  COUNT(*) AS work_days,
  SUM(CASE WHEN ad.attendance_status IN ('PRESENT','LATE','HOLIDAY') THEN 1 ELSE 0 END) AS present_days,
  SUM(CASE WHEN ad.attendance_status = 'ALPHA' THEN 1 ELSE 0 END) AS alpha_days,
  SUM(COALESCE(ad.late_minutes, 0)) AS late_minutes,
  ROUND(SUM(COALESCE(ad.overtime_minutes, 0)) / 60, 2) AS overtime_hours,
  ROUND(SUM(COALESCE(ad.basic_amount, 0)), 2) AS basic_total,
  ROUND(SUM(COALESCE(ad.allowance_amount, 0)), 2) AS allowance_total,
  ROUND(SUM(COALESCE(ad.meal_amount, 0)), 2) AS meal_total,
  ROUND(SUM(COALESCE(ad.overtime_pay, 0)), 2) AS overtime_total,
  ROUND(SUM(COALESCE(ad.manual_addition_amount, 0)), 2) AS manual_addition_total,
  ROUND(SUM(COALESCE(ad.late_deduction_amount, 0)), 2) AS late_deduction_total,
  ROUND(SUM(COALESCE(ad.alpha_deduction_amount, 0)), 2) AS alpha_deduction_total,
  ROUND(SUM(COALESCE(ad.manual_deduction_amount, 0)), 2) AS manual_deduction_total,
  ROUND(COALESCE(ca.cash_advance_cut_total, 0), 2) AS cash_advance_cut_total,
  ROUND(SUM(COALESCE(ad.gross_amount, 0)), 2) AS gross_pay,
  ROUND(SUM(COALESCE(ad.daily_salary_amount, 0)), 2) AS net_pay_raw,
  CAST(0 AS DECIMAL(18,2)) AS net_pay,
  CAST(0 AS DECIMAL(18,2)) AS rounding_adjustment,
  COALESCE(sd.active_salary_disbursement_count, 0) AS active_salary_disbursement_count
FROM pay_payroll_period pp
JOIN att_daily ad
  ON ad.attendance_date BETWEEN pp.period_start AND pp.period_end
JOIN org_employee e ON e.id = ad.employee_id
LEFT JOIN (
  SELECT ca.employee_id, ROUND(SUM(COALESCE(i.paid_amount, 0)), 2) AS cash_advance_cut_total
  FROM pay_cash_advance_installment i
  JOIN pay_cash_advance ca ON ca.id = i.cash_advance_id
  WHERE i.payment_method = 'SALARY_CUT'
    AND i.salary_cut_period = @period_code
    AND i.status = 'PAID'
  GROUP BY ca.employee_id
) ca ON ca.employee_id = ad.employee_id
LEFT JOIN (
  SELECT payroll_period_id, COUNT(*) AS active_salary_disbursement_count
  FROM pay_salary_disbursement
  WHERE status <> 'VOID'
  GROUP BY payroll_period_id
) sd ON sd.payroll_period_id = pp.id
WHERE pp.period_code = @period_code
  AND ad.employee_id = @employee_id
  AND (ad.checkout_at IS NOT NULL OR ad.attendance_status = 'HOLIDAY')
GROUP BY
  pp.id,
  pp.rounding_mode,
  ad.employee_id,
  e.employee_code,
  e.employee_name,
  ca.cash_advance_cut_total,
  sd.active_salary_disbursement_count;

UPDATE tmp_pf_july_payroll_rollup
SET
  net_pay = CASE
    WHEN UPPER(COALESCE(rounding_mode, 'NONE')) = 'UP_1000' AND net_pay_raw > 0
      THEN CEIL(net_pay_raw / 1000) * 1000
    ELSE net_pay_raw
  END;

UPDATE tmp_pf_july_payroll_rollup
SET rounding_adjustment = ROUND(net_pay - net_pay_raw, 2);

UPDATE pay_payroll_result r
JOIN tmp_pf_july_payroll_rollup x
  ON x.payroll_period_id = r.payroll_period_id
 AND x.employee_id = r.employee_id
SET
  r.employee_code_snapshot = x.employee_code,
  r.employee_name_snapshot = x.employee_name,
  r.work_days = x.work_days,
  r.present_days = x.present_days,
  r.alpha_days = x.alpha_days,
  r.late_minutes = x.late_minutes,
  r.overtime_hours = x.overtime_hours,
  r.basic_total = x.basic_total,
  r.allowance_total = x.allowance_total,
  r.meal_total = x.meal_total,
  r.overtime_total = x.overtime_total,
  r.manual_addition_total = x.manual_addition_total,
  r.late_deduction_total = x.late_deduction_total,
  r.alpha_deduction_total = x.alpha_deduction_total,
  r.manual_deduction_total = x.manual_deduction_total,
  r.cash_advance_cut_total = x.cash_advance_cut_total,
  r.gross_pay = x.gross_pay,
  r.net_pay_raw = x.net_pay_raw,
  r.rounding_adjustment = x.rounding_adjustment,
  r.net_pay = x.net_pay,
  r.total_deduction = GREATEST(0, ROUND(x.gross_pay - x.net_pay, 2)),
  r.status = 'FINALIZED',
  r.updated_at = CURRENT_TIMESTAMP
WHERE x.active_salary_disbursement_count = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_pf_july_payroll_result_lines_new;
CREATE TEMPORARY TABLE tmp_pf_july_payroll_result_lines_new AS
SELECT r.id AS payroll_result_id, 'BASIC' AS line_code, 'Gaji Pokok' AS line_name, 'EARNING' AS line_type, x.basic_total AS amount
FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id
WHERE x.active_salary_disbursement_count = 0 AND ABS(x.basic_total) > 0.00001
UNION ALL SELECT r.id, 'ALLOWANCE', 'Tunjangan', 'EARNING', x.allowance_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.allowance_total) > 0.00001
UNION ALL SELECT r.id, 'MEAL', 'Uang Makan', 'EARNING', x.meal_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.meal_total) > 0.00001
UNION ALL SELECT r.id, 'OVERTIME', 'Lembur', 'EARNING', x.overtime_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.overtime_total) > 0.00001
UNION ALL SELECT r.id, 'MANUAL_ADD', 'Penyesuaian (+)', 'EARNING', x.manual_addition_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.manual_addition_total) > 0.00001
UNION ALL SELECT r.id, 'LATE_DED', 'Potongan Telat', 'DEDUCTION', x.late_deduction_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.late_deduction_total) > 0.00001
UNION ALL SELECT r.id, 'ALPHA_DED', 'Potongan Alpha', 'DEDUCTION', x.alpha_deduction_total FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.alpha_deduction_total) > 0.00001
UNION ALL SELECT r.id, 'MANUAL_DED', 'Penyesuaian (-) Lain', 'DEDUCTION', GREATEST(0, x.manual_deduction_total - x.cash_advance_cut_total) FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(GREATEST(0, x.manual_deduction_total - x.cash_advance_cut_total)) > 0.00001
UNION ALL SELECT r.id, 'CASH_ADV_DED', 'Potongan Kasbon', 'DEDUCTION', LEAST(x.manual_deduction_total, x.cash_advance_cut_total) FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(LEAST(x.manual_deduction_total, x.cash_advance_cut_total)) > 0.00001
UNION ALL SELECT r.id, 'ROUNDING', 'Pembulatan', 'EARNING', x.rounding_adjustment FROM tmp_pf_july_payroll_rollup x JOIN pay_payroll_result r ON r.payroll_period_id = x.payroll_period_id AND r.employee_id = x.employee_id WHERE x.active_salary_disbursement_count = 0 AND ABS(x.rounding_adjustment) > 0.00001;

DELETE l
FROM pay_payroll_result_line l
JOIN tmp_pf_july_payroll_result_lines_new n ON n.payroll_result_id = l.payroll_result_id;

INSERT INTO pay_payroll_result_line (
  payroll_result_id,
  component_id,
  line_code,
  line_name,
  line_type,
  qty,
  rate,
  amount,
  notes,
  created_at,
  updated_at
)
SELECT
  payroll_result_id,
  NULL,
  line_code,
  line_name,
  line_type,
  1,
  amount,
  amount,
  NULL,
  CURRENT_TIMESTAMP,
  CURRENT_TIMESTAMP
FROM tmp_pf_july_payroll_result_lines_new;

COMMIT;

-- ------------------------------------------------------------
-- Verifikasi ringkas
-- ------------------------------------------------------------
SELECT 'att_daily_rows_backed_up' AS metric, COUNT(*) AS total
FROM tmp_pf_july_att_daily_backup
UNION ALL
SELECT 'att_daily_rows_recalculated', COUNT(*)
FROM tmp_pf_july_recalc
UNION ALL
SELECT 'checkout_shifted_plus_1_day', COUNT(*)
FROM tmp_pf_july_recalc
WHERE checkout_at IS NOT NULL
  AND corrected_checkout_at IS NOT NULL
  AND corrected_checkout_at <> checkout_at
UNION ALL
SELECT 'rows_positive_work_minutes_after_repair', COUNT(*)
FROM tmp_pf_july_recalc
WHERE corrected_work_minutes > 0
UNION ALL
SELECT 'payroll_result_updated_allowed', COUNT(*)
FROM tmp_pf_july_payroll_rollup
WHERE active_salary_disbursement_count = 0
UNION ALL
SELECT 'payroll_result_blocked_by_active_disbursement', COUNT(*)
FROM tmp_pf_july_payroll_rollup
WHERE active_salary_disbursement_count > 0;

SELECT
  attendance_date,
  attendance_status,
  checkin_at,
  corrected_checkout_at AS checkout_after_repair,
  corrected_work_minutes AS work_minutes_after_repair,
  corrected_basic_amount AS basic_after_repair,
  corrected_allowance_amount AS allowance_after_repair,
  corrected_meal_amount AS meal_after_repair,
  manual_deduction_amount,
  corrected_daily_salary_amount AS daily_salary_after_repair
FROM tmp_pf_july_recalc
ORDER BY attendance_date;

SELECT
  r.payroll_period_id,
  r.employee_id,
  r.employee_code_snapshot,
  r.employee_name_snapshot,
  r.work_days,
  r.present_days,
  r.basic_total,
  r.allowance_total,
  r.meal_total,
  r.manual_deduction_total,
  r.cash_advance_cut_total,
  r.gross_pay,
  r.net_pay_raw AS thp_riil_raw,
  r.rounding_adjustment,
  r.net_pay AS thp_final,
  roll.active_salary_disbursement_count
FROM pay_payroll_result r
JOIN tmp_pf_july_payroll_rollup roll
  ON roll.payroll_period_id = r.payroll_period_id
 AND roll.employee_id = r.employee_id;
