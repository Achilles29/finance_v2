SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-30a_repair_pak_fajar_overnight_att_daily.sql
-- Tujuan :
-- 1) Memperbaiki data att_daily PAK FAJAR (EMP-00005) untuk
--    shift malam SECURITY yang checkout-nya masih tersimpan
--    di tanggal yang sama dengan check-in
-- 2) Menormalkan checkout lintas hari (+1 hari) untuk shift
--    overnight, lalu hitung ulang menit kerja dan nominal harian
-- 3) Menyelaraskan att_daily dengan logika payroll harian yang
--    dipakai aplikasi saat ini
--
-- Catatan penting:
-- - Script ini sengaja sempit hanya untuk EMP-00005
-- - Potongan kasbon tetap diambil dari pay_manual_adjustment
--   lalu disalin ke manual_deduction_amount / manual_net per hari
-- - Fokus kasus ini adalah bulan Juni 2026
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair overnight att_daily Pak Fajar 2026-06-30';
SET @employee_id := 5;
SET @date_start := '2026-06-01';
SET @date_end := '2026-06-30';

DROP TEMPORARY TABLE IF EXISTS tmp_pf_manual_adjustment;
CREATE TEMPORARY TABLE tmp_pf_manual_adjustment AS
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

ALTER TABLE tmp_pf_manual_adjustment
  ADD PRIMARY KEY (employee_id, attendance_date);

DROP TEMPORARY TABLE IF EXISTS tmp_pf_att_daily_backup;
CREATE TEMPORARY TABLE tmp_pf_att_daily_backup AS
SELECT *
FROM att_daily
WHERE employee_id = @employee_id
  AND attendance_date BETWEEN @date_start AND @date_end;

DROP TEMPORARY TABLE IF EXISTS tmp_pf_att_daily_recalc;
CREATE TEMPORARY TABLE tmp_pf_att_daily_recalc AS
SELECT
  ad.id,
  ad.employee_id,
  ad.attendance_date,
  ad.shift_id,
  ad.attendance_status,
  ad.checkin_at,
  ad.checkout_at,
  ad.work_days_snapshot,
  ad.snapshot_basic_salary,
  ad.snapshot_position_allowance,
  ad.snapshot_objective_allowance,
  ad.snapshot_meal_rate,
  ad.snapshot_overtime_rate,
  ad.overtime_pay,
  ad.attendance_mode_snapshot,
  ad.meal_mode_snapshot,
  ad.prorate_scope_snapshot,
  ad.overtime_mode_snapshot,
  ad.allowance_late_treatment_snapshot,
  ad.enable_late_deduction_snapshot,
  ad.enable_alpha_deduction_snapshot,
  ad.late_deduction_per_minute_snapshot,
  ad.alpha_deduction_per_day_snapshot,
  COALESCE(ma.manual_addition_amount, 0.00) AS manual_addition_amount,
  COALESCE(ma.manual_deduction_amount, 0.00) AS manual_deduction_amount,
  ROUND(COALESCE(ma.manual_addition_amount, 0.00) - COALESCE(ma.manual_deduction_amount, 0.00), 2) AS manual_net_amount,
  s.start_time,
  s.end_time,
  COALESCE(s.is_overnight, 0) AS is_overnight,
  COALESCE(s.grace_late_minute, 0) AS grace_late_minute,
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
LEFT JOIN att_shift s ON s.id = ad.shift_id
LEFT JOIN tmp_pf_manual_adjustment ma
  ON ma.employee_id = ad.employee_id
 AND ma.attendance_date = ad.attendance_date
WHERE ad.employee_id = @employee_id
  AND ad.attendance_date BETWEEN @date_start AND @date_end;

ALTER TABLE tmp_pf_att_daily_recalc
  ADD PRIMARY KEY (id);

UPDATE tmp_pf_att_daily_recalc
SET
  corrected_checkout_at = CASE
    WHEN checkin_at IS NOT NULL
      AND checkout_at IS NOT NULL
      AND checkout_at <= checkin_at
      AND (COALESCE(is_overnight, 0) = 1 OR COALESCE(end_time, '00:00:00') <= COALESCE(start_time, '00:00:00'))
      THEN DATE_ADD(checkout_at, INTERVAL 1 DAY)
    ELSE checkout_at
  END,
  scheduled_start_at = CASE
    WHEN start_time IS NOT NULL THEN TIMESTAMP(attendance_date, start_time)
    ELSE NULL
  END,
  scheduled_end_at = CASE
    WHEN end_time IS NULL THEN NULL
    WHEN COALESCE(is_overnight, 0) = 1 OR COALESCE(end_time, '00:00:00') <= COALESCE(start_time, '00:00:00')
      THEN DATE_ADD(TIMESTAMP(attendance_date, end_time), INTERVAL 1 DAY)
    ELSE TIMESTAMP(attendance_date, end_time)
  END,
  basic_daily_rate = ROUND(COALESCE(snapshot_basic_salary, 0) / GREATEST(COALESCE(work_days_snapshot, 26), 1), 2),
  allowance_daily_rate = ROUND((COALESCE(snapshot_position_allowance, 0) + COALESCE(snapshot_objective_allowance, 0)) / GREATEST(COALESCE(work_days_snapshot, 26), 1), 2);

UPDATE tmp_pf_att_daily_recalc
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
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, scheduled_start_at, checkin_at), 0)
    ELSE 0
  END,
  corrected_early_leave_minutes = CASE
    WHEN corrected_checkout_at IS NOT NULL AND scheduled_end_at IS NOT NULL AND corrected_checkout_at < scheduled_end_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, corrected_checkout_at, scheduled_end_at), 0)
    ELSE 0
  END,
  corrected_overtime_minutes = CASE
    WHEN UPPER(COALESCE(overtime_mode_snapshot, 'AUTO')) = 'AUTO'
      AND corrected_checkout_at IS NOT NULL
      AND scheduled_end_at IS NOT NULL
      AND corrected_checkout_at > scheduled_end_at
      THEN GREATEST(TIMESTAMPDIFF(MINUTE, scheduled_end_at, corrected_checkout_at), 0)
    ELSE 0
  END;

UPDATE tmp_pf_att_daily_recalc
SET
  corrected_basic_amount = CASE
    WHEN attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
      AND (
        (checkin_at IS NOT NULL AND corrected_checkout_at IS NOT NULL AND corrected_checkout_at > checkin_at)
        OR attendance_status = 'HOLIDAY'
      )
      THEN ROUND(basic_daily_rate, 2)
    ELSE 0.00
  END,
  corrected_allowance_amount = CASE
    WHEN attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
      AND UPPER(COALESCE(allowance_late_treatment_snapshot, 'FULL_IF_PRESENT')) <> 'DEDUCT_IF_LATE'
      AND (
        (checkin_at IS NOT NULL AND corrected_checkout_at IS NOT NULL AND corrected_checkout_at > checkin_at)
        OR attendance_status = 'HOLIDAY'
      )
      THEN ROUND(allowance_daily_rate, 2)
    WHEN attendance_status IN ('PRESENT', 'HOLIDAY')
      AND (
        (checkin_at IS NOT NULL AND corrected_checkout_at IS NOT NULL AND corrected_checkout_at > checkin_at)
        OR attendance_status = 'HOLIDAY'
      )
      THEN ROUND(allowance_daily_rate, 2)
    ELSE 0.00
  END,
  corrected_meal_amount = CASE
    WHEN UPPER(COALESCE(meal_mode_snapshot, 'MONTHLY')) = 'CUSTOM'
      AND checkin_at IS NOT NULL
      AND attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
      THEN ROUND(COALESCE(snapshot_meal_rate, 0), 2)
    ELSE 0.00
  END,
  corrected_overtime_pay = CASE
    WHEN UPPER(COALESCE(overtime_mode_snapshot, 'AUTO')) = 'AUTO'
      THEN ROUND((corrected_overtime_minutes / 60) * COALESCE(snapshot_overtime_rate, 0), 2)
    ELSE ROUND(COALESCE(overtime_pay, 0), 2)
  END,
  corrected_alpha_deduction_amount = CASE
    WHEN attendance_status = 'ALPHA'
      AND COALESCE(enable_alpha_deduction_snapshot, 0) = 1
      AND checkin_at IS NOT NULL
      AND corrected_checkout_at IS NOT NULL
      AND corrected_checkout_at > checkin_at
      THEN ROUND(COALESCE(alpha_deduction_per_day_snapshot, 0), 2)
    ELSE 0.00
  END,
  corrected_late_deduction_amount = CASE
    WHEN checkin_at IS NOT NULL
      AND corrected_checkout_at IS NOT NULL
      AND corrected_checkout_at > checkin_at
      AND COALESCE(enable_late_deduction_snapshot, 0) = 1
      AND COALESCE(late_deduction_per_minute_snapshot, 0) > 0
      THEN ROUND(corrected_late_minutes * COALESCE(late_deduction_per_minute_snapshot, 0), 2)
    WHEN checkin_at IS NOT NULL
      AND corrected_checkout_at IS NOT NULL
      AND corrected_checkout_at > checkin_at
      AND COALESCE(enable_late_deduction_snapshot, 0) = 0
      AND COALESCE(enable_alpha_deduction_snapshot, 0) = 0
      AND scheduled_work_minutes > 0
      THEN ROUND(
        CASE
          WHEN UPPER(COALESCE(prorate_scope_snapshot, 'BASIC_ONLY')) = 'THP_TOTAL'
            THEN (basic_daily_rate + allowance_daily_rate + CASE WHEN UPPER(COALESCE(meal_mode_snapshot, 'MONTHLY')) = 'CUSTOM' THEN COALESCE(snapshot_meal_rate, 0) ELSE 0 END)
                 * (1 - LEAST(corrected_work_minutes / scheduled_work_minutes, 1))
          ELSE basic_daily_rate * (1 - LEAST(corrected_work_minutes / scheduled_work_minutes, 1))
        END
      , 2)
    ELSE 0.00
  END;

UPDATE tmp_pf_att_daily_recalc
SET
  corrected_gross_amount = ROUND(
    corrected_basic_amount
    + corrected_allowance_amount
    + corrected_meal_amount
    + corrected_overtime_pay
  , 2),
  corrected_net_amount = ROUND(
    (
      CASE
        WHEN UPPER(COALESCE(meal_mode_snapshot, 'MONTHLY')) = 'CUSTOM'
          THEN (
            corrected_basic_amount
            + corrected_allowance_amount
            + corrected_overtime_pay
          )
        ELSE (
          corrected_basic_amount
          + corrected_allowance_amount
          + corrected_meal_amount
          + corrected_overtime_pay
        )
      END
    )
    - corrected_late_deduction_amount
    - corrected_alpha_deduction_amount
  , 2),
  corrected_daily_salary_amount = ROUND(
    (
      (
        CASE
          WHEN UPPER(COALESCE(meal_mode_snapshot, 'MONTHLY')) = 'CUSTOM'
            THEN (
              corrected_basic_amount
              + corrected_allowance_amount
              + corrected_overtime_pay
            )
          ELSE (
            corrected_basic_amount
            + corrected_allowance_amount
            + corrected_meal_amount
            + corrected_overtime_pay
          )
        END
      )
      - corrected_late_deduction_amount
      - corrected_alpha_deduction_amount
    )
    + manual_net_amount
  , 2);

UPDATE att_daily ad
JOIN tmp_pf_att_daily_recalc t ON t.id = ad.id
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
  ad.manual_adjustment_net_amount = t.manual_net_amount,
  ad.daily_salary_amount = t.corrected_daily_salary_amount,
  ad.remarks = LEFT(TRIM(CONCAT(
    COALESCE(ad.remarks, ''),
    CASE WHEN COALESCE(ad.remarks, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  ad.updated_at = CURRENT_TIMESTAMP
WHERE ad.employee_id = @employee_id
  AND ad.attendance_date BETWEEN @date_start AND @date_end;

COMMIT;

-- ------------------------------------------------------------
-- Verifikasi ringkas
-- ------------------------------------------------------------
SELECT 'rows_backed_up' AS metric, COUNT(*) AS total
FROM tmp_pf_att_daily_backup

UNION ALL

SELECT 'rows_recalculated', COUNT(*)
FROM tmp_pf_att_daily_recalc

UNION ALL

SELECT 'rows_with_checkout_shifted_plus_1_day', COUNT(*)
FROM tmp_pf_att_daily_recalc
WHERE checkout_at IS NOT NULL
  AND corrected_checkout_at IS NOT NULL
  AND corrected_checkout_at <> checkout_at

UNION ALL

SELECT 'rows_with_positive_work_minutes_after_repair', COUNT(*)
FROM tmp_pf_att_daily_recalc
WHERE corrected_work_minutes > 0;

SELECT
  attendance_date,
  attendance_status,
  checkin_at,
  corrected_checkout_at AS checkout_at_after_repair,
  corrected_work_minutes AS work_minutes_after_repair,
  corrected_late_minutes AS late_minutes_after_repair,
  corrected_gross_amount AS gross_after_repair,
  corrected_net_amount AS net_after_repair,
  manual_net_amount AS manual_net_after_repair,
  corrected_daily_salary_amount AS daily_salary_after_repair
FROM tmp_pf_att_daily_recalc
ORDER BY attendance_date;
