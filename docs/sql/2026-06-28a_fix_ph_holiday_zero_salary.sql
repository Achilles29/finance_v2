-- =============================================================================
-- REPAIR: att_daily HOLIDAY records with zero salary
-- Tanggal: 2026-06-28
-- Masalah: ensure_auto_ph_presence() hanya membuat record att_daily tanpa
--          menghitung gaji. Salary field (basic_amount, gross_amount, dll)
--          dibiarkan 0. Fix kode sudah diterapkan di My_portal_model.php —
--          script ini memperbaiki data yang sudah terlanjur masuk dengan nilai 0.
-- =============================================================================

-- -----------------------------------------------------------------------
-- STEP 1: DIAGNOSIS — tampilkan semua HOLIDAY records dengan gaji = 0
-- -----------------------------------------------------------------------
SELECT
    ad.id,
    ad.attendance_date,
    e.employee_code,
    e.employee_name,
    ad.attendance_status,
    ad.source_type,
    ad.daily_salary_amount,
    ad.basic_amount,
    ad.gross_amount,
    ad.created_at,
    ad.updated_at
FROM att_daily ad
JOIN org_employee e ON e.id = ad.employee_id
WHERE ad.attendance_status = 'HOLIDAY'
  AND ad.daily_salary_amount = 0
ORDER BY ad.attendance_date, e.employee_name;

-- -----------------------------------------------------------------------
-- STEP 2: REPAIR — update salary untuk HOLIDAY records dengan gaji = 0
--
-- Catatan formula:
--   basic_amount    = basic_salary / work_days
--   allowance_amount = (position_allowance + objective_allowance) / work_days
--   meal_amount     = meal_rate  jika (meal_calc_mode='CUSTOM' AND ph_gets_meal_allowance=1)
--                     ELSE 0
--   gross_amount    = basic_amount + allowance_amount + meal_amount
--   net_amount      = gross_amount  (tidak ada potongan untuk hari PH)
--   daily_salary_amount = net_amount + manual_adjustment (default 0)
-- -----------------------------------------------------------------------
UPDATE att_daily ad
JOIN org_employee e ON e.id = ad.employee_id
JOIN (
    SELECT
        id                         AS policy_id,
        policy_code,
        policy_name,
        default_work_days_per_month,
        meal_calc_mode,
        ph_gets_meal_allowance,
        attendance_calc_mode,
        prorate_deduction_scope,
        overtime_calc_mode,
        allowance_late_treatment,
        enable_late_deduction,
        enable_alpha_deduction,
        late_deduction_per_minute,
        alpha_deduction_per_day
    FROM att_attendance_policy
    ORDER BY id DESC
    LIMIT 1
) pol ON 1 = 1
SET
    -- Salary components
    ad.basic_amount   = ROUND(e.basic_salary / pol.default_work_days_per_month, 2),
    ad.allowance_amount = ROUND(
        (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month,
        2
    ),
    ad.meal_amount    = CASE
        WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
        THEN ROUND(e.meal_rate, 2)
        ELSE 0
    END,
    ad.late_deduction_amount  = 0,
    ad.alpha_deduction_amount = 0,
    ad.gross_amount   = ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    ),
    ad.net_amount     = ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    ),
    ad.daily_salary_amount = ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    ),
    -- Salary snapshots
    ad.snapshot_basic_salary         = e.basic_salary,
    ad.snapshot_position_allowance   = e.position_allowance,
    ad.snapshot_objective_allowance  = e.objective_allowance,
    ad.snapshot_meal_rate            = e.meal_rate,
    ad.snapshot_overtime_rate        = e.overtime_rate,
    -- Work days snapshot
    ad.work_days_snapshot            = pol.default_work_days_per_month,
    -- Policy snapshots
    ad.policy_snapshot_id            = pol.policy_id,
    ad.policy_snapshot_code          = pol.policy_code,
    ad.policy_snapshot_name          = pol.policy_name,
    ad.attendance_mode_snapshot      = pol.attendance_calc_mode,
    ad.meal_mode_snapshot            = pol.meal_calc_mode,
    ad.prorate_scope_snapshot        = pol.prorate_deduction_scope,
    ad.overtime_mode_snapshot        = pol.overtime_calc_mode,
    ad.allowance_late_treatment_snapshot = pol.allowance_late_treatment,
    ad.enable_late_deduction_snapshot    = pol.enable_late_deduction,
    ad.enable_alpha_deduction_snapshot   = pol.enable_alpha_deduction,
    ad.late_deduction_per_minute_snapshot = pol.late_deduction_per_minute,
    ad.alpha_deduction_per_day_snapshot  = pol.alpha_deduction_per_day
WHERE ad.attendance_status = 'HOLIDAY'
  AND ad.daily_salary_amount = 0;

SELECT ROW_COUNT() AS baris_diperbaiki;

-- -----------------------------------------------------------------------
-- STEP 3: REPAIR MISSING RECORDS — buat att_daily untuk jadwal PH yang
--         sama sekali tidak punya record (pegawai tidak pernah buka halaman)
--         Hanya untuk tanggal yang sudah lewat (< CURDATE()).
-- -----------------------------------------------------------------------
INSERT INTO att_daily (
    attendance_date,
    employee_id,
    shift_id,
    checkin_at,
    checkout_at,
    attendance_status,
    work_minutes,
    late_minutes,
    early_leave_minutes,
    overtime_minutes,
    overtime_pay,
    basic_amount,
    allowance_amount,
    meal_amount,
    late_deduction_amount,
    alpha_deduction_amount,
    gross_amount,
    net_amount,
    daily_salary_amount,
    snapshot_basic_salary,
    snapshot_position_allowance,
    snapshot_objective_allowance,
    snapshot_meal_rate,
    snapshot_overtime_rate,
    work_days_snapshot,
    policy_snapshot_id,
    policy_snapshot_code,
    policy_snapshot_name,
    attendance_mode_snapshot,
    meal_mode_snapshot,
    prorate_scope_snapshot,
    overtime_mode_snapshot,
    allowance_late_treatment_snapshot,
    enable_late_deduction_snapshot,
    enable_alpha_deduction_snapshot,
    late_deduction_per_minute_snapshot,
    alpha_deduction_per_day_snapshot,
    source_type,
    remarks,
    created_at
)
SELECT
    ss.schedule_date                                           AS attendance_date,
    ss.employee_id,
    ss.shift_id,
    CONCAT(ss.schedule_date, ' ', sh.start_time)             AS checkin_at,
    CASE
        WHEN sh.is_overnight = 1
        THEN DATE_ADD(CONCAT(ss.schedule_date, ' ', sh.end_time), INTERVAL 1 DAY)
        ELSE CONCAT(ss.schedule_date, ' ', sh.end_time)
    END                                                        AS checkout_at,
    'HOLIDAY'                                                  AS attendance_status,
    TIMESTAMPDIFF(
        MINUTE,
        CONCAT(ss.schedule_date, ' ', sh.start_time),
        CASE WHEN sh.is_overnight = 1
             THEN DATE_ADD(CONCAT(ss.schedule_date, ' ', sh.end_time), INTERVAL 1 DAY)
             ELSE CONCAT(ss.schedule_date, ' ', sh.end_time) END
    )                                                          AS work_minutes,
    0                                                          AS late_minutes,
    0                                                          AS early_leave_minutes,
    0                                                          AS overtime_minutes,
    0                                                          AS overtime_pay,
    -- Salary components
    ROUND(e.basic_salary / pol.default_work_days_per_month, 2) AS basic_amount,
    ROUND((e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month, 2) AS allowance_amount,
    CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
         THEN ROUND(e.meal_rate, 2) ELSE 0 END                AS meal_amount,
    0                                                          AS late_deduction_amount,
    0                                                          AS alpha_deduction_amount,
    ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    )                                                          AS gross_amount,
    ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    )                                                          AS net_amount,
    ROUND(
        e.basic_salary / pol.default_work_days_per_month
        + (e.position_allowance + e.objective_allowance) / pol.default_work_days_per_month
        + CASE WHEN pol.meal_calc_mode = 'CUSTOM' AND pol.ph_gets_meal_allowance = 1
               THEN e.meal_rate ELSE 0 END,
        2
    )                                                          AS daily_salary_amount,
    -- Snapshots
    e.basic_salary,
    e.position_allowance,
    e.objective_allowance,
    e.meal_rate,
    e.overtime_rate,
    pol.default_work_days_per_month,
    pol.policy_id,
    pol.policy_code,
    pol.policy_name,
    pol.attendance_calc_mode,
    pol.meal_calc_mode,
    pol.prorate_deduction_scope,
    pol.overtime_calc_mode,
    pol.allowance_late_treatment,
    pol.enable_late_deduction,
    pol.enable_alpha_deduction,
    pol.late_deduction_per_minute,
    pol.alpha_deduction_per_day,
    'AUTO'                                                     AS source_type,
    'Auto hadir PH (dibuat retroaktif)'                        AS remarks,
    NOW()                                                      AS created_at
FROM att_shift_schedule ss
JOIN att_shift sh ON sh.id = ss.shift_id AND sh.shift_code = 'PH'
JOIN org_employee e ON e.id = ss.employee_id
JOIN (
    SELECT
        id AS policy_id, policy_code, policy_name,
        default_work_days_per_month, meal_calc_mode, ph_gets_meal_allowance,
        attendance_calc_mode, prorate_deduction_scope, overtime_calc_mode,
        allowance_late_treatment, enable_late_deduction, enable_alpha_deduction,
        late_deduction_per_minute, alpha_deduction_per_day
    FROM att_attendance_policy ORDER BY id DESC LIMIT 1
) pol ON 1 = 1
LEFT JOIN att_daily ad ON ad.employee_id = ss.employee_id
    AND ad.attendance_date = ss.schedule_date
WHERE ss.schedule_date < CURDATE()   -- hanya tanggal yang sudah lewat
  AND ad.id IS NULL;                 -- belum ada record

SELECT ROW_COUNT() AS baris_dibuat;

-- -----------------------------------------------------------------------
-- STEP 4: VERIFIKASI AKHIR
-- -----------------------------------------------------------------------
SELECT
    ss.schedule_date,
    e.employee_code,
    e.employee_name,
    sh.shift_code,
    COALESCE(ad.attendance_status, 'NO_RECORD')  AS att_status,
    COALESCE(ad.daily_salary_amount, 0)           AS salary_amount,
    CASE
        WHEN ad.id IS NULL                             THEN 'MASALAH: TIDAK ADA RECORD'
        WHEN COALESCE(ad.daily_salary_amount, 0) = 0   THEN 'MASALAH: GAJI NOL'
        ELSE 'OK'
    END AS status_check
FROM att_shift_schedule ss
JOIN att_shift sh ON sh.id = ss.shift_id AND sh.shift_code = 'PH'
JOIN org_employee e ON e.id = ss.employee_id
LEFT JOIN att_daily ad ON ad.employee_id = ss.employee_id
    AND ad.attendance_date = ss.schedule_date
ORDER BY ss.schedule_date, e.employee_name;
