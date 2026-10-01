SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- 1) Reset data turunan pegawai + relasi auth user pegawai
-- =========================================================
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE att_presence;
TRUNCATE TABLE att_daily;
TRUNCATE TABLE att_pending_request;
TRUNCATE TABLE att_overtime_entry;
TRUNCATE TABLE att_shift_schedule;

TRUNCATE TABLE pay_cash_advance;
TRUNCATE TABLE pay_salary_assignment;
TRUNCATE TABLE pay_payroll_result;
TRUNCATE TABLE pay_salary_disbursement_line;
TRUNCATE TABLE pay_salary_disbursement;
TRUNCATE TABLE pay_payroll_period;

TRUNCATE TABLE hr_contract_approval;
TRUNCATE TABLE hr_contract_signature;
TRUNCATE TABLE hr_contract_comp_snapshot_line;
TRUNCATE TABLE hr_contract_comp_snapshot;
TRUNCATE TABLE hr_contract;

TRUNCATE TABLE org_employee;

SET FOREIGN_KEY_CHECKS = 1;

-- Bersihkan user yang terhubung pegawai lama
DELETE asl
FROM auth_session_log asl
JOIN auth_user au ON au.id = asl.user_id
WHERE au.employee_id IS NOT NULL;

DELETE auo
FROM auth_user_permission_override auo
JOIN auth_user au ON au.id = auo.user_id
WHERE au.employee_id IS NOT NULL;

DELETE aur
FROM auth_user_role aur
JOIN auth_user au ON au.id = aur.user_id
WHERE au.employee_id IS NOT NULL;

DELETE FROM auth_user
WHERE employee_id IS NOT NULL;

-- =========================================================
-- 2) Import ulang pegawai dari core, ID sama persis
--    Catatan mapping:
--    - core.employee_code -> db_finance.employee_nip
--    - employee_code finance dibuat kode internal baru (EMP-00001 dst)
-- =========================================================
INSERT INTO org_employee (
  id,
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
  ce.id,
  CONCAT('EMP-', LPAD(ce.id, 5, '0')) AS employee_code,
  NULLIF(TRIM(ce.employee_code), '') AS employee_nip,
  ce.employee_name,
  CASE ce.jenis_kelamin WHEN 1 THEN 'L' WHEN 2 THEN 'P' ELSE NULL END AS gender,
  ce.tanggal_lahir,
  ce.tanggal_bergabung,
  NULL AS mobile_phone,
  NULL AS email,
  ce.alamat,
  td.id AS division_id,
  tp.id AS position_id,
  CASE
    WHEN COALESCE(ce.is_active, 1) = 0 THEN 'RESIGNED'
    WHEN ce.tanggal_kontrak_akhir IS NULL THEN 'PERMANENT'
    ELSE 'CONTRACT'
  END AS employment_status,
  COALESCE(ce.gaji_pokok, 0) AS basic_salary,
  COALESCE(ce.tunjangan, 0) AS position_allowance,
  COALESCE(ce.tambahan_lain, 0) AS objective_allowance,
  COALESCE(ce.uang_makan, 0) AS meal_rate,
  COALESCE(ce.gaji_per_jam, 0) AS overtime_rate,
  NULL AS bank_name,
  NULLIF(TRIM(ce.nomor_rekening), '') AS bank_account_no,
  NULL AS bank_account_name,
  COALESCE(ce.is_active, 1) AS is_active,
  COALESCE(ce.created_at, NOW()) AS created_at,
  ce.updated_at
FROM core.org_employee ce
LEFT JOIN core.org_division cd ON cd.id = ce.division_id
LEFT JOIN core.org_position cp ON cp.id = ce.position_id
LEFT JOIN org_division td ON td.division_code = cd.division_code
LEFT JOIN org_position tp ON tp.position_code = cp.position_code
ORDER BY ce.id;

SET @next_emp_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM org_employee);
SET @sql_emp_ai := CONCAT('ALTER TABLE org_employee AUTO_INCREMENT = ', @next_emp_ai);
PREPARE stmt_emp_ai FROM @sql_emp_ai;
EXECUTE stmt_emp_ai;
DEALLOCATE PREPARE stmt_emp_ai;

-- =========================================================
-- 3) Import ulang jadwal shift pegawai dari core
--    Normalisasi PHB/PH -> PH
-- =========================================================
INSERT INTO att_shift_schedule (
  id,
  employee_id,
  shift_id,
  schedule_date,
  notes,
  created_by,
  created_at,
  updated_at
)
SELECT
  css.id,
  te.id AS employee_id,
  ts.id AS shift_id,
  css.schedule_date,
  css.notes,
  tcb.id AS created_by,
  COALESCE(css.created_at, NOW()) AS created_at,
  css.updated_at
FROM core.att_shift_schedule css
JOIN org_employee te ON te.id = css.employee_id
JOIN core.att_shift csh ON csh.id = css.shift_id
JOIN att_shift ts ON ts.shift_code = (
  CASE
    WHEN UPPER(TRIM(csh.shift_code)) IN ('PH', 'PHB') OR COALESCE(csh.is_ph_shift, 0) = 1 THEN 'PH'
    ELSE UPPER(TRIM(csh.shift_code))
  END
)
LEFT JOIN org_employee tcb ON tcb.id = css.created_by
ORDER BY css.id;

SET @next_sched_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM att_shift_schedule);
SET @sql_sched_ai := CONCAT('ALTER TABLE att_shift_schedule AUTO_INCREMENT = ', @next_sched_ai);
PREPARE stmt_sched_ai FROM @sql_sched_ai;
EXECUTE stmt_sched_ai;
DEALLOCATE PREPARE stmt_sched_ai;

-- =========================================================
-- 4) Re-link auth_user dari core.org_employee
--    username/password mengikuti data core
-- =========================================================
INSERT INTO auth_user (
  employee_id,
  username,
  email,
  password_hash,
  is_active,
  created_at,
  updated_at
)
SELECT
  fe.id AS employee_id,
  TRIM(ce.username) AS username,
  NULL AS email,
  ce.password_hash,
  COALESCE(ce.is_active, 1) AS is_active,
  NOW(),
  NOW()
FROM core.org_employee ce
JOIN org_employee fe ON fe.id = ce.id
WHERE TRIM(IFNULL(ce.username, '')) <> ''
  AND TRIM(IFNULL(ce.password_hash, '')) <> '';

INSERT INTO auth_user_role (user_id, role_id, assigned_by, assigned_at)
SELECT u.id, r.id, 1, NOW()
FROM auth_user u
JOIN auth_role r ON r.role_code = 'STAFF'
WHERE u.employee_id IS NOT NULL
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  assigned_by = VALUES(assigned_by),
  assigned_at = VALUES(assigned_at);

COMMIT;
