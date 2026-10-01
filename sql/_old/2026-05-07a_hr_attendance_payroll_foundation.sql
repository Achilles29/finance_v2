SET NAMES utf8mb4;

-- =========================================================
-- Tahap 3/4/5 Foundation: HR + Attendance + Payroll
-- Date: 2026-05-07
-- Prinsip:
-- - Satu sumber rekap absensi harian: att_daily (tanpa dual-table legacy)
-- - Struktur payroll dipisah: master komponen/profil, proses periodik, disbursement
-- - Tetap kompatibel dengan fondasi auth/rbac/menu yang sudah ada
-- =========================================================

START TRANSACTION;

-- =========================================================
-- A. HR ORGANIZATION
-- =========================================================
CREATE TABLE IF NOT EXISTS org_division (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  division_code VARCHAR(40) NOT NULL,
  division_name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_org_division_code (division_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS org_position (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  division_id BIGINT UNSIGNED NOT NULL,
  position_code VARCHAR(40) NOT NULL,
  position_name VARCHAR(120) NOT NULL,
  default_role_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_org_position_code (position_code),
  KEY idx_org_position_division (division_id),
  CONSTRAINT fk_org_position_division FOREIGN KEY (division_id) REFERENCES org_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS org_employee (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_code VARCHAR(50) NOT NULL,
  employee_nip VARCHAR(24) NULL,
  employee_name VARCHAR(150) NOT NULL,
  gender ENUM('L','P') NULL,
  birth_date DATE NULL,
  join_date DATE NULL,
  mobile_phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address TEXT NULL,

  division_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  employment_status ENUM('PERMANENT','CONTRACT','PROBATION','DAILY','RESIGNED') NOT NULL DEFAULT 'CONTRACT',

  basic_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  position_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  objective_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  meal_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(18,2) NOT NULL DEFAULT 0,

  bank_name VARCHAR(120) NULL,
  bank_account_no VARCHAR(60) NULL,
  bank_account_name VARCHAR(150) NULL,

  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_org_employee_code (employee_code),
  UNIQUE KEY uk_org_employee_nip (employee_nip),
  KEY idx_org_employee_division (division_id),
  KEY idx_org_employee_position (position_id),
  KEY idx_org_employee_status (employment_status),
  CONSTRAINT fk_org_employee_division FOREIGN KEY (division_id) REFERENCES org_division(id),
  CONSTRAINT fk_org_employee_position FOREIGN KEY (position_id) REFERENCES org_position(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- B. ATTENDANCE MASTER + TRANSACTION
-- =========================================================
CREATE TABLE IF NOT EXISTS att_location (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  location_code VARCHAR(40) NOT NULL,
  location_name VARCHAR(120) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  radius_meter DECIMAL(10,2) NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_location_code (location_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_shift (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shift_code VARCHAR(40) NOT NULL,
  shift_name VARCHAR(120) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_overnight TINYINT(1) NOT NULL DEFAULT 0,
  grace_late_minute INT UNSIGNED NOT NULL DEFAULT 0,
  overtime_after_minute INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_shift_code (shift_code),
  KEY idx_att_shift_division (division_id),
  CONSTRAINT fk_att_shift_division FOREIGN KEY (division_id) REFERENCES org_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_shift_schedule (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  schedule_date DATE NOT NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_shift_schedule_unique (employee_id, schedule_date),
  KEY idx_att_shift_schedule_date (schedule_date),
  KEY idx_att_shift_schedule_shift (shift_id),
  CONSTRAINT fk_att_shift_schedule_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_shift_schedule_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id),
  CONSTRAINT fk_att_shift_schedule_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_holiday_calendar (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  holiday_date DATE NOT NULL,
  holiday_name VARCHAR(150) NOT NULL,
  holiday_type ENUM('NATIONAL','COMPANY','SPECIAL') NOT NULL DEFAULT 'NATIONAL',
  source_ref VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_holiday_date_name (holiday_date, holiday_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_attendance_policy (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  policy_code VARCHAR(40) NOT NULL,
  policy_name VARCHAR(120) NOT NULL,
  checkin_open_minutes_before INT UNSIGNED NOT NULL DEFAULT 30,
  enforce_geofence TINYINT(1) NOT NULL DEFAULT 1,
  require_photo TINYINT(1) NOT NULL DEFAULT 0,
  late_deduction_per_minute DECIMAL(18,2) NOT NULL DEFAULT 0,
  alpha_deduction_per_day DECIMAL(18,2) NOT NULL DEFAULT 0,
  use_basic_salary_daily_rate TINYINT(1) NOT NULL DEFAULT 1,
  default_work_days_per_month INT UNSIGNED NOT NULL DEFAULT 26,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_attendance_policy_code (policy_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_presence (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  attendance_date DATE NOT NULL,
  attendance_time TIME NOT NULL,
  attendance_at DATETIME NOT NULL,
  event_type ENUM('CHECKIN','CHECKOUT') NOT NULL,
  source_type ENUM('GPS','DEVICE','MANUAL') NOT NULL DEFAULT 'GPS',
  location_id BIGINT UNSIGNED NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  photo_path VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_att_presence_employee_date (employee_id, attendance_date),
  KEY idx_att_presence_shift (shift_id),
  KEY idx_att_presence_event_at (attendance_at),
  CONSTRAINT fk_att_presence_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_presence_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id),
  CONSTRAINT fk_att_presence_location FOREIGN KEY (location_id) REFERENCES att_location(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_daily (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  attendance_date DATE NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  checkin_at DATETIME NULL,
  checkout_at DATETIME NULL,
  attendance_status ENUM('PRESENT','LATE','ALPHA','SICK','LEAVE','OFF','HOLIDAY') NOT NULL DEFAULT 'OFF',
  work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  early_leave_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  overtime_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
  daily_salary_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_type ENUM('AUTO','MANUAL','PENDING_APPROVAL') NOT NULL DEFAULT 'AUTO',
  remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_daily_employee_date (employee_id, attendance_date),
  KEY idx_att_daily_date (attendance_date),
  KEY idx_att_daily_status (attendance_status),
  CONSTRAINT fk_att_daily_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_daily_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_pending_request (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  request_date DATE NOT NULL,
  request_type ENUM('MISSING_CHECKIN','MISSING_CHECKOUT','STATUS_CORRECTION','OVERTIME','LEAVE','SICK') NOT NULL,
  requested_checkin_at DATETIME NULL,
  requested_checkout_at DATETIME NULL,
  requested_status ENUM('PRESENT','LATE','ALPHA','SICK','LEAVE','OFF','HOLIDAY') NULL,
  reason TEXT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  approval_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_att_pending_employee_date (employee_id, request_date),
  KEY idx_att_pending_status (status),
  CONSTRAINT fk_att_pending_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_pending_approver FOREIGN KEY (approved_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_overtime_entry (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  overtime_date DATE NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_overtime_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_att_overtime_employee_date (employee_id, overtime_date),
  KEY idx_att_overtime_status (status),
  CONSTRAINT fk_att_overtime_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_overtime_approver FOREIGN KEY (approved_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- C. PAYROLL MASTER
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_salary_component (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  component_code VARCHAR(50) NOT NULL,
  component_name VARCHAR(150) NOT NULL,
  component_type ENUM('EARNING','DEDUCTION') NOT NULL,
  calc_method ENUM('FIXED','PER_DAY','PER_HOUR','PER_MINUTE','FORMULA') NOT NULL DEFAULT 'FIXED',
  default_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  affects_attendance TINYINT(1) NOT NULL DEFAULT 0,
  affects_bpjs_base TINYINT(1) NOT NULL DEFAULT 0,
  is_taxable TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_salary_component_code (component_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_salary_profile (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  profile_code VARCHAR(50) NOT NULL,
  profile_name VARCHAR(150) NOT NULL,
  effective_start DATE NULL,
  effective_end DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_salary_profile_code (profile_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_salary_profile_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  profile_id BIGINT UNSIGNED NOT NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  formula_expr VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_salary_profile_line_unique (profile_id, component_id),
  CONSTRAINT fk_pay_salary_profile_line_profile FOREIGN KEY (profile_id) REFERENCES pay_salary_profile(id),
  CONSTRAINT fk_pay_salary_profile_line_component FOREIGN KEY (component_id) REFERENCES pay_salary_component(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_salary_assignment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  effective_start DATE NOT NULL,
  effective_end DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_salary_assignment_employee (employee_id),
  KEY idx_pay_salary_assignment_profile (profile_id),
  KEY idx_pay_salary_assignment_effective (effective_start, effective_end),
  CONSTRAINT fk_pay_salary_assignment_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pay_salary_assignment_profile FOREIGN KEY (profile_id) REFERENCES pay_salary_profile(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_cash_advance (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  advance_no VARCHAR(60) NOT NULL,
  request_date DATE NOT NULL,
  approved_date DATE NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tenor_month INT UNSIGNED NOT NULL DEFAULT 1,
  monthly_deduction_plan DECIMAL(18,2) NOT NULL DEFAULT 0,
  outstanding_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','APPROVED','REJECTED','SETTLED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_cash_advance_no (advance_no),
  KEY idx_pay_cash_advance_employee (employee_id),
  CONSTRAINT fk_pay_cash_advance_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_cash_advance_installment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cash_advance_id BIGINT UNSIGNED NOT NULL,
  installment_no INT UNSIGNED NOT NULL,
  due_period CHAR(7) NOT NULL,
  plan_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('OPEN','PARTIAL','PAID') NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_cash_advance_inst_unique (cash_advance_id, installment_no),
  KEY idx_pay_cash_advance_inst_period (due_period),
  CONSTRAINT fk_pay_cash_advance_inst_parent FOREIGN KEY (cash_advance_id) REFERENCES pay_cash_advance(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- D. PAYROLL PROCESSING + DISBURSEMENT
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_payroll_period (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  period_code CHAR(7) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  status ENUM('DRAFT','CALCULATED','FINALIZED','PAID','CLOSED') NOT NULL DEFAULT 'DRAFT',
  finalized_at DATETIME NULL,
  finalized_by BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_payroll_period_code (period_code),
  CONSTRAINT fk_pay_payroll_period_finalizer FOREIGN KEY (finalized_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_payroll_result (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  employee_code_snapshot VARCHAR(50) NOT NULL,
  employee_name_snapshot VARCHAR(150) NOT NULL,

  work_days DECIMAL(10,2) NOT NULL DEFAULT 0,
  present_days DECIMAL(10,2) NOT NULL DEFAULT 0,
  alpha_days DECIMAL(10,2) NOT NULL DEFAULT 0,
  late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0,

  gross_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
  total_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(18,2) NOT NULL DEFAULT 0,

  status ENUM('DRAFT','FINALIZED','PAID') NOT NULL DEFAULT 'DRAFT',
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_pay_payroll_result_unique (payroll_period_id, employee_id),
  KEY idx_pay_payroll_result_status (status),
  CONSTRAINT fk_pay_payroll_result_period FOREIGN KEY (payroll_period_id) REFERENCES pay_payroll_period(id),
  CONSTRAINT fk_pay_payroll_result_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_payroll_result_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payroll_result_id BIGINT UNSIGNED NOT NULL,
  component_id BIGINT UNSIGNED NULL,
  line_code VARCHAR(60) NOT NULL,
  line_name VARCHAR(150) NOT NULL,
  line_type ENUM('EARNING','DEDUCTION') NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 1,
  rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_payroll_result_line_result (payroll_result_id),
  KEY idx_pay_payroll_result_line_component (component_id),
  CONSTRAINT fk_pay_payroll_result_line_result FOREIGN KEY (payroll_result_id) REFERENCES pay_payroll_result(id),
  CONSTRAINT fk_pay_payroll_result_line_component FOREIGN KEY (component_id) REFERENCES pay_salary_component(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_salary_disbursement (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  payroll_period_id BIGINT UNSIGNED NOT NULL,
  disbursement_no VARCHAR(60) NOT NULL,
  disbursement_date DATE NOT NULL,
  company_account_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_salary_disbursement_no (disbursement_no),
  KEY idx_pay_salary_disbursement_period (payroll_period_id),
  KEY idx_pay_salary_disbursement_company_account (company_account_id),
  CONSTRAINT fk_pay_salary_disbursement_period FOREIGN KEY (payroll_period_id) REFERENCES pay_payroll_period(id),
  CONSTRAINT fk_pay_salary_disbursement_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pay_salary_disbursement_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  disbursement_id BIGINT UNSIGNED NOT NULL,
  payroll_result_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  transfer_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  transfer_status ENUM('PENDING','PAID','FAILED','VOID') NOT NULL DEFAULT 'PENDING',
  transfer_ref_no VARCHAR(100) NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_salary_disb_line_unique (disbursement_id, payroll_result_id),
  KEY idx_pay_salary_disb_line_employee (employee_id),
  CONSTRAINT fk_pay_salary_disb_line_disbursement FOREIGN KEY (disbursement_id) REFERENCES pay_salary_disbursement(id),
  CONSTRAINT fk_pay_salary_disb_line_result FOREIGN KEY (payroll_result_id) REFERENCES pay_payroll_result(id),
  CONSTRAINT fk_pay_salary_disb_line_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
