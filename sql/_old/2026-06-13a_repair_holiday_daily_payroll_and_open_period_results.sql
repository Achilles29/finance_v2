SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13a_repair_holiday_daily_payroll_and_open_period_results.sql
-- Tujuan :
-- 1) Memperbaiki att_daily status HOLIDAY/PH yang tidak punya checkout
--    tetapi seharusnya tetap dibayar penuh untuk skema gaji harian
-- 2) Refresh pay_payroll_result + payroll_result_line untuk period yang
--    belum punya batch pencairan gaji aktif
-- 3) Mengaudit period yang SUDAH PAID/CLOSED atau sudah punya batch
--    pencairan aktif agar tidak diubah diam-diam
--
-- Catatan:
-- - Script ini sengaja TIDAK mengubah period payroll yang sudah terkunci
--   atau sudah punya salary disbursement aktif non-VOID.
-- - Untuk period terblokir, script hanya menampilkan audit.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair HOLIDAY daily payroll without checkout 2026-06-13';

SET @current_work_days := (
  SELECT COALESCE(NULLIF(default_work_days_per_month, 0), 26)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
);
SET @current_work_days := COALESCE(@current_work_days, 26);

SET @current_meal_mode := (
  SELECT UPPER(COALESCE(NULLIF(meal_calc_mode, ''), 'MONTHLY'))
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
);
SET @current_meal_mode := COALESCE(@current_meal_mode, 'MONTHLY');

SET @current_ph_gets_meal := (
  SELECT COALESCE(ph_gets_meal_allowance, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
);
SET @current_ph_gets_meal := COALESCE(@current_ph_gets_meal, 0);

DROP TEMPORARY TABLE IF EXISTS tmp_holiday_daily_candidates;
CREATE TEMPORARY TABLE tmp_holiday_daily_candidates AS
SELECT
  q.att_daily_id,
  q.employee_id,
  q.attendance_date,
  q.expected_basic,
  q.expected_allowance,
  q.expected_meal,
  q.expected_gross,
  q.expected_net,
  q.expected_daily_salary
FROM (
  SELECT
    ad.id AS att_daily_id,
    ad.employee_id,
    ad.attendance_date,
    ROUND(
      COALESCE(ad.snapshot_basic_salary, e.basic_salary, 0)
      / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
      2
    ) AS expected_basic,
    ROUND(
      (
        COALESCE(ad.snapshot_position_allowance, e.position_allowance, 0)
        + COALESCE(ad.snapshot_objective_allowance, e.objective_allowance, 0)
      ) / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
      2
    ) AS expected_allowance,
    ROUND(
      CASE
        WHEN UPPER(COALESCE(ad.meal_mode_snapshot, @current_meal_mode, 'MONTHLY')) = 'CUSTOM'
             AND @current_ph_gets_meal = 1
          THEN COALESCE(ad.snapshot_meal_rate, e.meal_rate, 0)
        ELSE 0
      END,
      2
    ) AS expected_meal,
    ROUND(
      ROUND(
        COALESCE(ad.snapshot_basic_salary, e.basic_salary, 0)
        / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        (
          COALESCE(ad.snapshot_position_allowance, e.position_allowance, 0)
          + COALESCE(ad.snapshot_objective_allowance, e.objective_allowance, 0)
        ) / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        CASE
          WHEN UPPER(COALESCE(ad.meal_mode_snapshot, @current_meal_mode, 'MONTHLY')) = 'CUSTOM'
               AND @current_ph_gets_meal = 1
            THEN COALESCE(ad.snapshot_meal_rate, e.meal_rate, 0)
          ELSE 0
        END,
        2
      ),
      2
    ) AS expected_gross,
    ROUND(
      ROUND(
        COALESCE(ad.snapshot_basic_salary, e.basic_salary, 0)
        / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        (
          COALESCE(ad.snapshot_position_allowance, e.position_allowance, 0)
          + COALESCE(ad.snapshot_objective_allowance, e.objective_allowance, 0)
        ) / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        CASE
          WHEN UPPER(COALESCE(ad.meal_mode_snapshot, @current_meal_mode, 'MONTHLY')) = 'CUSTOM'
               AND @current_ph_gets_meal = 1
            THEN COALESCE(ad.snapshot_meal_rate, e.meal_rate, 0)
          ELSE 0
        END,
        2
      ),
      2
    ) AS expected_net,
    ROUND(
      ROUND(
        COALESCE(ad.snapshot_basic_salary, e.basic_salary, 0)
        / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        (
          COALESCE(ad.snapshot_position_allowance, e.position_allowance, 0)
          + COALESCE(ad.snapshot_objective_allowance, e.objective_allowance, 0)
        ) / GREATEST(1, COALESCE(ad.work_days_snapshot, @current_work_days, 26)),
        2
      ) +
      ROUND(
        CASE
          WHEN UPPER(COALESCE(ad.meal_mode_snapshot, @current_meal_mode, 'MONTHLY')) = 'CUSTOM'
               AND @current_ph_gets_meal = 1
            THEN COALESCE(ad.snapshot_meal_rate, e.meal_rate, 0)
          ELSE 0
        END,
        2
      ) +
      COALESCE(ad.manual_adjustment_net_amount, 0),
      2
    ) AS expected_daily_salary,
    COALESCE(ad.basic_amount, 0) AS current_basic,
    COALESCE(ad.allowance_amount, 0) AS current_allowance,
    COALESCE(ad.meal_amount, 0) AS current_meal,
    COALESCE(ad.gross_amount, 0) AS current_gross,
    COALESCE(ad.net_amount, 0) AS current_net,
    COALESCE(ad.daily_salary_amount, 0) AS current_daily_salary
  FROM att_daily ad
  INNER JOIN org_employee e
    ON e.id = ad.employee_id
  WHERE UPPER(COALESCE(ad.attendance_status, '')) = 'HOLIDAY'
    AND COALESCE(e.is_active, 1) = 1
) q
WHERE ABS(q.current_basic - q.expected_basic) > 0.009
   OR ABS(q.current_allowance - q.expected_allowance) > 0.009
   OR ABS(q.current_meal - q.expected_meal) > 0.009
   OR ABS(q.current_gross - q.expected_gross) > 0.009
   OR ABS(q.current_net - q.expected_net) > 0.009
   OR ABS(q.current_daily_salary - q.expected_daily_salary) > 0.009;

ALTER TABLE tmp_holiday_daily_candidates
  ADD PRIMARY KEY (att_daily_id),
  ADD KEY idx_tmp_holiday_daily_emp_date (employee_id, attendance_date);

UPDATE att_daily ad
JOIN tmp_holiday_daily_candidates t ON t.att_daily_id = ad.id
SET
  ad.basic_amount = t.expected_basic,
  ad.allowance_amount = t.expected_allowance,
  ad.meal_amount = t.expected_meal,
  ad.overtime_pay = 0.00,
  ad.late_deduction_amount = 0.00,
  ad.alpha_deduction_amount = 0.00,
  ad.gross_amount = t.expected_gross,
  ad.net_amount = t.expected_net,
  ad.daily_salary_amount = t.expected_daily_salary,
  ad.updated_at = NOW();

DROP TEMPORARY TABLE IF EXISTS tmp_blocked_holiday_periods;
CREATE TEMPORARY TABLE tmp_blocked_holiday_periods AS
SELECT DISTINCT
  pp.id AS payroll_period_id,
  pp.period_code,
  pp.period_start,
  pp.period_end,
  pp.status AS payroll_status,
  c.employee_id,
  e.employee_code,
  e.employee_name,
  CASE
    WHEN UPPER(COALESCE(pp.status, 'DRAFT')) IN ('PAID', 'CLOSED') THEN 'PERIOD_LOCKED'
    WHEN EXISTS (
      SELECT 1
      FROM pay_salary_disbursement sd
      WHERE sd.payroll_period_id = pp.id
        AND sd.status <> 'VOID'
    ) THEN 'HAS_ACTIVE_DISBURSEMENT'
    ELSE 'UNKNOWN'
  END AS block_reason
FROM tmp_holiday_daily_candidates c
JOIN pay_payroll_period pp
  ON c.attendance_date >= pp.period_start
 AND c.attendance_date <= pp.period_end
JOIN org_employee e
  ON e.id = c.employee_id
WHERE UPPER(COALESCE(pp.status, 'DRAFT')) IN ('PAID', 'CLOSED')
   OR EXISTS (
      SELECT 1
      FROM pay_salary_disbursement sd
      WHERE sd.payroll_period_id = pp.id
        AND sd.status <> 'VOID'
   );

DROP TEMPORARY TABLE IF EXISTS tmp_holiday_open_period_targets;
CREATE TEMPORARY TABLE tmp_holiday_open_period_targets AS
SELECT DISTINCT
  pp.id AS payroll_period_id,
  c.employee_id
FROM tmp_holiday_daily_candidates c
JOIN pay_payroll_period pp
  ON c.attendance_date >= pp.period_start
 AND c.attendance_date <= pp.period_end
WHERE UPPER(COALESCE(pp.status, 'DRAFT')) NOT IN ('PAID', 'CLOSED')
  AND NOT EXISTS (
    SELECT 1
    FROM pay_salary_disbursement sd
    WHERE sd.payroll_period_id = pp.id
      AND sd.status <> 'VOID'
  );

ALTER TABLE tmp_holiday_open_period_targets
  ADD PRIMARY KEY (payroll_period_id, employee_id);

DROP TEMPORARY TABLE IF EXISTS tmp_holiday_payroll_rollup;
CREATE TEMPORARY TABLE tmp_holiday_payroll_rollup AS
SELECT
  t.payroll_period_id,
  t.employee_id,
  MAX(e.employee_code) AS employee_code_snapshot,
  MAX(e.employee_name) AS employee_name_snapshot,
  ROUND(COUNT(*), 2) AS work_days,
  ROUND(SUM(CASE WHEN ad.attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY') THEN 1 ELSE 0 END), 2) AS present_days,
  ROUND(SUM(CASE WHEN ad.attendance_status = 'ALPHA' THEN 1 ELSE 0 END), 2) AS alpha_days,
  COALESCE(SUM(ad.late_minutes), 0) AS late_minutes,
  ROUND(COALESCE(SUM(ad.overtime_minutes), 0) / 60, 2) AS overtime_hours,
  ROUND(SUM(COALESCE(ad.basic_amount, 0)), 2) AS basic_total,
  ROUND(SUM(COALESCE(ad.allowance_amount, 0)), 2) AS allowance_total,
  ROUND(SUM(COALESCE(ad.meal_amount, 0)), 2) AS meal_total,
  ROUND(SUM(COALESCE(ad.overtime_pay, 0)), 2) AS overtime_total,
  ROUND(SUM(COALESCE(ad.manual_addition_amount, 0)), 2) AS manual_addition_total,
  ROUND(SUM(COALESCE(ad.late_deduction_amount, 0)), 2) AS late_deduction_total,
  ROUND(SUM(COALESCE(ad.alpha_deduction_amount, 0)), 2) AS alpha_deduction_total,
  ROUND(SUM(COALESCE(ad.manual_deduction_amount, 0)), 2) AS manual_deduction_total,
  ROUND(SUM(COALESCE(ad.gross_amount, 0)), 2) AS gross_pay_raw,
  ROUND(SUM(COALESCE(ad.daily_salary_amount, 0)), 2) AS net_pay_raw,
  UPPER(COALESCE(MAX(pp.rounding_mode), 'NONE')) AS rounding_mode,
  COALESCE(MAX(r.cash_advance_cut_total), 0) AS cash_advance_cut_total,
  COALESCE(MAX(r.status), CASE WHEN UPPER(COALESCE(MAX(pp.status), 'DRAFT')) = 'DRAFT' THEN 'DRAFT' ELSE 'FINALIZED' END) AS result_status
FROM tmp_holiday_open_period_targets t
JOIN pay_payroll_period pp
  ON pp.id = t.payroll_period_id
JOIN att_daily ad
  ON ad.employee_id = t.employee_id
 AND ad.attendance_date >= pp.period_start
 AND ad.attendance_date <= pp.period_end
 AND (
   ad.checkout_at IS NOT NULL
   OR ad.attendance_status = 'HOLIDAY'
 )
JOIN org_employee e
  ON e.id = t.employee_id
LEFT JOIN pay_payroll_result r
  ON r.payroll_period_id = t.payroll_period_id
 AND r.employee_id = t.employee_id
GROUP BY
  t.payroll_period_id,
  t.employee_id;

ALTER TABLE tmp_holiday_payroll_rollup
  ADD PRIMARY KEY (payroll_period_id, employee_id);

DROP TEMPORARY TABLE IF EXISTS tmp_holiday_payroll_final;
CREATE TEMPORARY TABLE tmp_holiday_payroll_final AS
SELECT
  r.*,
  ROUND(
    CASE
      WHEN r.rounding_mode = 'UP_1000' AND r.net_pay_raw > 0
        THEN CEILING(r.net_pay_raw / 1000) * 1000
      ELSE r.net_pay_raw
    END,
    2
  ) AS net_pay,
  ROUND(
    CASE
      WHEN r.rounding_mode = 'UP_1000' AND r.net_pay_raw > 0
        THEN (CEILING(r.net_pay_raw / 1000) * 1000) - r.net_pay_raw
      ELSE 0
    END,
    2
  ) AS rounding_adjustment,
  GREATEST(
    0,
    ROUND(
      r.gross_pay_raw - (
        CASE
          WHEN r.rounding_mode = 'UP_1000' AND r.net_pay_raw > 0
            THEN CEILING(r.net_pay_raw / 1000) * 1000
          ELSE r.net_pay_raw
        END
      ),
      2
    )
  ) AS total_deduction
FROM tmp_holiday_payroll_rollup r;

ALTER TABLE tmp_holiday_payroll_final
  ADD PRIMARY KEY (payroll_period_id, employee_id);

UPDATE pay_payroll_result pr
JOIN tmp_holiday_payroll_final f
  ON f.payroll_period_id = pr.payroll_period_id
 AND f.employee_id = pr.employee_id
SET
  pr.employee_code_snapshot = f.employee_code_snapshot,
  pr.employee_name_snapshot = f.employee_name_snapshot,
  pr.work_days = f.work_days,
  pr.present_days = f.present_days,
  pr.alpha_days = f.alpha_days,
  pr.late_minutes = f.late_minutes,
  pr.overtime_hours = f.overtime_hours,
  pr.gross_pay = f.gross_pay_raw,
  pr.total_deduction = f.total_deduction,
  pr.net_pay = f.net_pay,
  pr.status = f.result_status,
  pr.basic_total = f.basic_total,
  pr.allowance_total = f.allowance_total,
  pr.meal_total = f.meal_total,
  pr.overtime_total = f.overtime_total,
  pr.manual_addition_total = f.manual_addition_total,
  pr.late_deduction_total = f.late_deduction_total,
  pr.alpha_deduction_total = f.alpha_deduction_total,
  pr.manual_deduction_total = f.manual_deduction_total,
  pr.cash_advance_cut_total = f.cash_advance_cut_total,
  pr.net_pay_raw = f.net_pay_raw,
  pr.rounding_adjustment = f.rounding_adjustment,
  pr.updated_at = NOW();

INSERT INTO pay_payroll_result (
  payroll_period_id,
  employee_id,
  employee_code_snapshot,
  employee_name_snapshot,
  work_days,
  present_days,
  alpha_days,
  late_minutes,
  overtime_hours,
  gross_pay,
  total_deduction,
  net_pay,
  status,
  basic_total,
  allowance_total,
  meal_total,
  overtime_total,
  manual_addition_total,
  late_deduction_total,
  alpha_deduction_total,
  manual_deduction_total,
  cash_advance_cut_total,
  net_pay_raw,
  rounding_adjustment,
  created_at,
  updated_at
)
SELECT
  f.payroll_period_id,
  f.employee_id,
  f.employee_code_snapshot,
  f.employee_name_snapshot,
  f.work_days,
  f.present_days,
  f.alpha_days,
  f.late_minutes,
  f.overtime_hours,
  f.gross_pay_raw,
  f.total_deduction,
  f.net_pay,
  f.result_status,
  f.basic_total,
  f.allowance_total,
  f.meal_total,
  f.overtime_total,
  f.manual_addition_total,
  f.late_deduction_total,
  f.alpha_deduction_total,
  f.manual_deduction_total,
  f.cash_advance_cut_total,
  f.net_pay_raw,
  f.rounding_adjustment,
  NOW(),
  NOW()
FROM tmp_holiday_payroll_final f
LEFT JOIN pay_payroll_result pr
  ON pr.payroll_period_id = f.payroll_period_id
 AND pr.employee_id = f.employee_id
WHERE pr.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_holiday_repaired_result_ids;
CREATE TEMPORARY TABLE tmp_holiday_repaired_result_ids AS
SELECT
  pr.id AS payroll_result_id,
  pr.payroll_period_id,
  pr.employee_id,
  pr.basic_total,
  pr.allowance_total,
  pr.meal_total,
  pr.overtime_total,
  pr.manual_addition_total,
  pr.late_deduction_total,
  pr.alpha_deduction_total,
  pr.manual_deduction_total,
  pr.cash_advance_cut_total,
  pr.rounding_adjustment
FROM pay_payroll_result pr
JOIN tmp_holiday_payroll_final f
  ON f.payroll_period_id = pr.payroll_period_id
 AND f.employee_id = pr.employee_id;

ALTER TABLE tmp_holiday_repaired_result_ids
  ADD PRIMARY KEY (payroll_result_id);

DELETE rl
FROM pay_payroll_result_line rl
JOIN tmp_holiday_repaired_result_ids t
  ON t.payroll_result_id = rl.payroll_result_id;

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
  x.payroll_result_id,
  NULL,
  x.line_code,
  x.line_name,
  x.line_type,
  1,
  x.amount,
  x.amount,
  @repair_tag,
  NOW(),
  NOW()
FROM (
  SELECT payroll_result_id, 'BASIC' AS line_code, 'Gaji Pokok' AS line_name, 'EARNING' AS line_type, ROUND(basic_total, 2) AS amount
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'ALLOWANCE', 'Tunjangan', 'EARNING', ROUND(allowance_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'MEAL', 'Uang Makan', 'EARNING', ROUND(meal_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'OVERTIME', 'Lembur', 'EARNING', ROUND(overtime_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'MANUAL_ADD', 'Penyesuaian (+)', 'EARNING', ROUND(manual_addition_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'LATE_DED', 'Potongan Telat', 'DEDUCTION', ROUND(late_deduction_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'ALPHA_DED', 'Potongan Alpha', 'DEDUCTION', ROUND(alpha_deduction_total, 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'MANUAL_DED', 'Penyesuaian (-) Lain', 'DEDUCTION',
         ROUND(GREATEST(manual_deduction_total - LEAST(manual_deduction_total, cash_advance_cut_total), 0), 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'CASH_ADV_DED', 'Potongan Kasbon', 'DEDUCTION',
         ROUND(LEAST(manual_deduction_total, cash_advance_cut_total), 2)
  FROM tmp_holiday_repaired_result_ids
  UNION ALL
  SELECT payroll_result_id, 'ROUNDING', 'Pembulatan', 'EARNING', ROUND(rounding_adjustment, 2)
  FROM tmp_holiday_repaired_result_ids
) x
WHERE ABS(COALESCE(x.amount, 0)) > 0.00001;

COMMIT;

SELECT 'holiday_daily_rows_repaired' AS metric, COUNT(*) AS total
FROM tmp_holiday_daily_candidates
UNION ALL
SELECT 'open_period_employee_targets_rebuilt', COUNT(*)
FROM tmp_holiday_open_period_targets
UNION ALL
SELECT 'payroll_results_rebuilt_or_inserted', COUNT(*)
FROM tmp_holiday_payroll_final
UNION ALL
SELECT 'blocked_period_employee_rows_for_manual_followup', COUNT(*)
FROM tmp_blocked_holiday_periods;

SELECT
  payroll_period_id,
  period_code,
  payroll_status,
  employee_code,
  employee_name,
  block_reason
FROM tmp_blocked_holiday_periods
ORDER BY payroll_period_id, employee_name;
