SET NAMES utf8mb4;

-- =========================================================
-- Adopsi master HR & Absensi: core -> db_finance
-- Date: 2026-05-07
-- Catatan:
-- - Jalankan file ini pada database target: db_finance
-- - Sumber tetap: core
-- - Upsert, tidak truncate
-- - PHB dinormalisasi ke PH
-- =========================================================

START TRANSACTION;

-- ---------------------------------------------------------
-- 1) Division
-- ---------------------------------------------------------
INSERT INTO org_division (
  division_code,
  division_name,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  TRIM(d.division_code) AS division_code,
  TRIM(d.division_name) AS division_name,
  COALESCE(d.id, 0) AS sort_order,
  COALESCE(d.is_active, 1) AS is_active,
  NOW(),
  NOW()
FROM core.org_division d
WHERE TRIM(IFNULL(d.division_code, '')) <> ''
ON DUPLICATE KEY UPDATE
  division_name = VALUES(division_name),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 2) Position
-- ---------------------------------------------------------
INSERT INTO org_position (
  division_id,
  position_code,
  position_name,
  default_role_id,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  td.id AS division_id,
  TRIM(p.position_code) AS position_code,
  TRIM(p.position_name) AS position_name,
  NULL AS default_role_id,
  COALESCE(p.id, 0) AS sort_order,
  COALESCE(p.is_active, 1) AS is_active,
  NOW(),
  NOW()
FROM core.org_position p
JOIN core.org_division sd ON sd.id = p.division_id
JOIN org_division td ON td.division_code = sd.division_code
WHERE TRIM(IFNULL(p.position_code, '')) <> ''
ON DUPLICATE KEY UPDATE
  division_id = VALUES(division_id),
  position_name = VALUES(position_name),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 3) Employee
-- ---------------------------------------------------------
INSERT INTO org_employee (
  employee_code,
  employee_nip,
  employee_name,
  gender,
  birth_date,
  join_date,
  mobile_phone,
  email,
  address,
  division_id,
  position_id,
  employment_status,
  basic_salary,
  position_allowance,
  objective_allowance,
  meal_rate,
  overtime_rate,
  bank_name,
  bank_account_no,
  bank_account_name,
  is_active,
  created_at,
  updated_at
)
SELECT
  CASE
    WHEN TRIM(IFNULL(e.employee_code, '')) = '' THEN CONCAT('CORE-EMP-', e.id)
    ELSE TRIM(e.employee_code)
  END AS employee_code,
  NULL AS employee_nip,
  TRIM(e.employee_name) AS employee_name,
  CASE
    WHEN e.jenis_kelamin = 1 THEN 'L'
    WHEN e.jenis_kelamin = 2 THEN 'P'
    ELSE NULL
  END AS gender,
  e.tanggal_lahir AS birth_date,
  e.tanggal_bergabung AS join_date,
  NULL AS mobile_phone,
  NULL AS email,
  e.alamat AS address,
  td.id AS division_id,
  tp.id AS position_id,
  CASE
    WHEN COALESCE(e.is_active, 1) = 0 THEN 'RESIGNED'
    WHEN e.tanggal_kontrak_akhir IS NULL THEN 'PERMANENT'
    ELSE 'CONTRACT'
  END AS employment_status,
  COALESCE(e.gaji_pokok, 0) AS basic_salary,
  COALESCE(e.tunjangan, 0) AS position_allowance,
  COALESCE(e.tambahan_lain, 0) AS objective_allowance,
  COALESCE(e.uang_makan, 0) AS meal_rate,
  COALESCE(e.gaji_per_jam, 0) AS overtime_rate,
  ba.bank_name AS bank_name,
  e.nomor_rekening AS bank_account_no,
  ba.account_name AS bank_account_name,
  COALESCE(e.is_active, 1) AS is_active,
  NOW(),
  NOW()
FROM core.org_employee e
JOIN core.org_division sd ON sd.id = e.division_id
JOIN org_division td ON td.division_code = sd.division_code
JOIN core.org_position sp ON sp.id = e.position_id
JOIN org_position tp ON tp.position_code = sp.position_code
LEFT JOIN core.m_bank_account ba ON ba.id = e.nama_bank_id
WHERE TRIM(IFNULL(e.employee_name, '')) <> ''
ON DUPLICATE KEY UPDATE
  employee_name = VALUES(employee_name),
  gender = VALUES(gender),
  birth_date = VALUES(birth_date),
  join_date = VALUES(join_date),
  address = VALUES(address),
  division_id = VALUES(division_id),
  position_id = VALUES(position_id),
  employment_status = VALUES(employment_status),
  basic_salary = VALUES(basic_salary),
  position_allowance = VALUES(position_allowance),
  objective_allowance = VALUES(objective_allowance),
  meal_rate = VALUES(meal_rate),
  overtime_rate = VALUES(overtime_rate),
  bank_name = VALUES(bank_name),
  bank_account_no = VALUES(bank_account_no),
  bank_account_name = VALUES(bank_account_name),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 4) Location
-- ---------------------------------------------------------
INSERT INTO att_location (
  location_code,
  location_name,
  latitude,
  longitude,
  radius_meter,
  is_active,
  created_at,
  updated_at
)
SELECT
  TRIM(l.location_code),
  TRIM(l.location_name),
  l.latitude,
  l.longitude,
  COALESCE(l.radius_meter, 100),
  COALESCE(l.is_active, 1),
  NOW(),
  NOW()
FROM core.att_location l
WHERE TRIM(IFNULL(l.location_code, '')) <> ''
ON DUPLICATE KEY UPDATE
  location_name = VALUES(location_name),
  latitude = VALUES(latitude),
  longitude = VALUES(longitude),
  radius_meter = VALUES(radius_meter),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 5) Shift (normalize PH/PHB -> PH)
-- ---------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_core_shift_norm;
CREATE TEMPORARY TABLE tmp_core_shift_norm AS
SELECT
  d.division_code,
  CASE
    WHEN UPPER(TRIM(s.shift_code)) IN ('PH', 'PHB') THEN 'PH'
    ELSE UPPER(TRIM(s.shift_code))
  END AS shift_code_norm,
  CASE
    WHEN UPPER(TRIM(s.shift_code)) IN ('PH', 'PHB') THEN 'PUBLIC HOLIDAY'
    ELSE TRIM(s.shift_name)
  END AS shift_name_norm,
  MIN(s.start_time) AS start_time,
  MAX(s.end_time) AS end_time,
  MAX(COALESCE(s.is_overnight, 0)) AS is_overnight,
  MAX(COALESCE(s.grace_late_minute, 0)) AS grace_late_minute,
  MAX(COALESCE(s.overtime_after_minute, 0)) AS overtime_after_minute,
  MAX(COALESCE(s.is_active, 1)) AS is_active
FROM core.att_shift s
JOIN core.org_division d ON d.id = s.division_id
WHERE TRIM(IFNULL(s.shift_code, '')) <> ''
GROUP BY
  d.division_code,
  CASE
    WHEN UPPER(TRIM(s.shift_code)) IN ('PH', 'PHB') THEN 'PH'
    ELSE UPPER(TRIM(s.shift_code))
  END,
  CASE
    WHEN UPPER(TRIM(s.shift_code)) IN ('PH', 'PHB') THEN 'PUBLIC HOLIDAY'
    ELSE TRIM(s.shift_name)
  END;

INSERT INTO att_shift (
  shift_code,
  shift_name,
  division_id,
  start_time,
  end_time,
  is_overnight,
  grace_late_minute,
  overtime_after_minute,
  is_active,
  created_at,
  updated_at
)
SELECT
  n.shift_code_norm,
  n.shift_name_norm,
  CASE
    WHEN n.shift_code_norm = 'PH' THEN NULL
    ELSE td.id
  END AS division_id,
  n.start_time,
  n.end_time,
  n.is_overnight,
  n.grace_late_minute,
  n.overtime_after_minute,
  n.is_active,
  NOW(),
  NOW()
FROM tmp_core_shift_norm n
JOIN org_division td ON td.division_code = n.division_code
ON DUPLICATE KEY UPDATE
  shift_name = VALUES(shift_name),
  division_id = VALUES(division_id),
  start_time = VALUES(start_time),
  end_time = VALUES(end_time),
  is_overnight = VALUES(is_overnight),
  grace_late_minute = VALUES(grace_late_minute),
  overtime_after_minute = VALUES(overtime_after_minute),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 6) Shift schedule (map via employee_code + normalized shift code)
-- ---------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_core_shift_map;
CREATE TEMPORARY TABLE tmp_core_shift_map AS
SELECT
  s.id AS source_shift_id,
  d.division_code,
  CASE
    WHEN UPPER(TRIM(s.shift_code)) IN ('PH', 'PHB') THEN 'PH'
    ELSE UPPER(TRIM(s.shift_code))
  END AS shift_code_norm
FROM core.att_shift s
JOIN core.org_division d ON d.id = s.division_id;

INSERT INTO att_shift_schedule (
  employee_id,
  shift_id,
  schedule_date,
  notes,
  created_by,
  created_at,
  updated_at
)
SELECT
  te.id AS employee_id,
  ts.id AS shift_id,
  sc.schedule_date,
  sc.notes,
  tcb.id AS created_by,
  NOW(),
  NOW()
FROM core.att_shift_schedule sc
JOIN core.org_employee se ON se.id = sc.employee_id
JOIN org_employee te ON te.employee_code = (
  CASE
    WHEN TRIM(IFNULL(se.employee_code, '')) = '' THEN CONCAT('CORE-EMP-', se.id)
    ELSE TRIM(se.employee_code)
  END
)
JOIN tmp_core_shift_map sm ON sm.source_shift_id = sc.shift_id
JOIN org_division td ON td.division_code = sm.division_code
JOIN att_shift ts ON ts.shift_code = sm.shift_code_norm
  AND (
    sm.shift_code_norm = 'PH'
    OR ts.division_id = td.id
  )
LEFT JOIN core.org_employee cb ON cb.id = sc.created_by
LEFT JOIN org_employee tcb ON tcb.employee_code = (
  CASE
    WHEN cb.id IS NULL THEN NULL
    WHEN TRIM(IFNULL(cb.employee_code, '')) = '' THEN CONCAT('CORE-EMP-', cb.id)
    ELSE TRIM(cb.employee_code)
  END
)
ON DUPLICATE KEY UPDATE
  shift_id = VALUES(shift_id),
  notes = VALUES(notes),
  created_by = VALUES(created_by),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 7) Holiday calendar
-- ---------------------------------------------------------
INSERT INTO att_holiday_calendar (
  holiday_date,
  holiday_name,
  holiday_type,
  source_ref,
  is_active,
  created_at,
  updated_at
)
SELECT
  h.holiday_date,
  TRIM(h.holiday_name),
  CASE
    WHEN h.holiday_type = 'NATIONAL' THEN 'NATIONAL'
    WHEN h.holiday_type = 'COMPANY' THEN 'COMPANY'
    WHEN h.holiday_type = 'CUTI_BERSAMA' THEN 'SPECIAL'
    ELSE 'SPECIAL'
  END AS holiday_type,
  LEFT(IFNULL(h.source_ref, ''), 100) AS source_ref,
  COALESCE(h.is_active, 1) AS is_active,
  NOW(),
  NOW()
FROM core.att_holiday_calendar h
WHERE h.holiday_date IS NOT NULL
  AND TRIM(IFNULL(h.holiday_name, '')) <> ''
ON DUPLICATE KEY UPDATE
  holiday_type = VALUES(holiday_type),
  source_ref = VALUES(source_ref),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- ---------------------------------------------------------
-- 8) Attendance policy (active latest from core)
-- ---------------------------------------------------------
UPDATE att_attendance_policy
SET is_active = 0,
    updated_at = NOW();

INSERT INTO att_attendance_policy (
  policy_code,
  policy_name,
  checkin_open_minutes_before,
  enforce_geofence,
  require_photo,
  late_deduction_per_minute,
  alpha_deduction_per_day,
  use_basic_salary_daily_rate,
  default_work_days_per_month,
  is_active,
  created_at,
  updated_at
)
SELECT
  LEFT(CONCAT('CORE_', p.policy_code), 40) AS policy_code,
  LEFT(CONCAT('Import ', p.policy_name), 120) AS policy_name,
  COALESCE(p.checkin_open_minutes_before, 30) AS checkin_open_minutes_before,
  COALESCE(p.enforce_geofence, 1) AS enforce_geofence,
  COALESCE(p.require_photo, 0) AS require_photo,
  0 AS late_deduction_per_minute,
  0 AS alpha_deduction_per_day,
  1 AS use_basic_salary_daily_rate,
  26 AS default_work_days_per_month,
  1 AS is_active,
  NOW(),
  NOW()
FROM core.att_attendance_policy p
WHERE p.is_active = 1
ORDER BY p.id DESC
LIMIT 1
ON DUPLICATE KEY UPDATE
  policy_name = VALUES(policy_name),
  checkin_open_minutes_before = VALUES(checkin_open_minutes_before),
  enforce_geofence = VALUES(enforce_geofence),
  require_photo = VALUES(require_photo),
  is_active = 1,
  updated_at = NOW();

COMMIT;

-- ---------------------------------------------------------
-- Post-check
-- ---------------------------------------------------------
SELECT 'org_division' t, COUNT(*) c FROM org_division
UNION ALL SELECT 'org_position', COUNT(*) FROM org_position
UNION ALL SELECT 'org_employee', COUNT(*) FROM org_employee
UNION ALL SELECT 'att_location', COUNT(*) FROM att_location
UNION ALL SELECT 'att_shift', COUNT(*) FROM att_shift
UNION ALL SELECT 'att_shift_schedule', COUNT(*) FROM att_shift_schedule
UNION ALL SELECT 'att_holiday_calendar', COUNT(*) FROM att_holiday_calendar
UNION ALL SELECT 'att_attendance_policy', COUNT(*) FROM att_attendance_policy;
