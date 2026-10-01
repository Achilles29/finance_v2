SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13i_payroll_bonus_peer_review_foundation.sql
-- Tujuan :
-- 1) Menyiapkan fondasi modul bonus pegawai yang terhubung ke target
-- 2) Menyiapkan tabel penalti bonus, service metric, dan distribusi bonus
-- 3) Menyiapkan tabel penilaian 360 antar rekan kerja
-- 4) Menjaga bonus, target, absensi, dan payroll tetap terpisah tapi nyambung
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pay_bonus_config (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  config_code VARCHAR(40) NOT NULL,
  config_name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  company_account_id BIGINT UNSIGNED NULL,
  distribution_scope ENUM('GLOBAL','OUTLET','DIVISION') NOT NULL DEFAULT 'GLOBAL',
  pool_source_mode ENUM('FIXED','PERCENT_REVENUE','PERCENT_PROFIT','TARGET_LINKED','MANUAL') NOT NULL DEFAULT 'TARGET_LINKED',
  pool_source_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  payout_percent DECIMAL(9,4) NOT NULL DEFAULT 100.0000,
  linked_target_required TINYINT(1) NOT NULL DEFAULT 1,
  include_shift_revenue_factor TINYINT(1) NOT NULL DEFAULT 1,
  include_service_time_factor TINYINT(1) NOT NULL DEFAULT 1,
  include_peer_review_factor TINYINT(1) NOT NULL DEFAULT 1,
  include_attendance_factor TINYINT(1) NOT NULL DEFAULT 1,
  include_manual_penalty_factor TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('DRAFT','ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_config_code (config_code),
  KEY idx_pay_bonus_config_status (status),
  KEY idx_pay_bonus_config_account (company_account_id),
  CONSTRAINT fk_pay_bonus_config_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_config_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_config_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_rule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  config_id BIGINT UNSIGNED NOT NULL,
  rule_code VARCHAR(40) NOT NULL,
  rule_name VARCHAR(120) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  division_id BIGINT UNSIGNED NULL,
  linked_target_plan_id BIGINT UNSIGNED NULL,
  active_start_date DATE NULL,
  active_end_date DATE NULL,
  threshold_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  pool_formula_type ENUM('PERCENTAGE','FIXED_STEP') NOT NULL DEFAULT 'PERCENTAGE',
  pool_formula_value DECIMAL(12,4) NOT NULL DEFAULT 3.0000,
  min_shift_base_pct DECIMAL(9,2) NOT NULL DEFAULT 30.00,
  min_target_score DECIMAL(7,2) NOT NULL DEFAULT 100.00,
  target_gate_mode ENUM('NONE','ALL_REQUIRED','WEIGHTED_SCORE') NOT NULL DEFAULT 'WEIGHTED_SCORE',
  ph_bonus_mode ENUM('ALLOW','EXCLUDE','REDUCE') NOT NULL DEFAULT 'EXCLUDE',
  ph_point_deduction DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  holiday_bonus_mode ENUM('IGNORE','NEUTRAL') NOT NULL DEFAULT 'IGNORE',
  late_penalty_mode ENUM('NONE','REDUCE_POINT','REDUCE_AMOUNT') NOT NULL DEFAULT 'REDUCE_POINT',
  late_penalty_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  alpha_penalty_value DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  service_time_target_minute DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  service_time_weight DECIMAL(9,4) NOT NULL DEFAULT 0.0000,
  shift_revenue_weight DECIMAL(9,4) NOT NULL DEFAULT 1.0000,
  peer_review_weight DECIMAL(9,4) NOT NULL DEFAULT 0.0000,
  attendance_weight DECIMAL(9,4) NOT NULL DEFAULT 1.0000,
  manual_penalty_weight DECIMAL(9,4) NOT NULL DEFAULT 1.0000,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_rule_code (rule_code),
  KEY idx_pay_bonus_rule_config (config_id),
  KEY idx_pay_bonus_rule_outlet (outlet_id),
  KEY idx_pay_bonus_rule_division (division_id),
  KEY idx_pay_bonus_rule_target (linked_target_plan_id),
  CONSTRAINT fk_pay_bonus_rule_config FOREIGN KEY (config_id) REFERENCES pay_bonus_config(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_rule_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_rule_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_rule_target FOREIGN KEY (linked_target_plan_id) REFERENCES fin_target_plan(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_rule_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_rule_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_weight_rule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_id BIGINT UNSIGNED NOT NULL,
  weight_scope ENUM('DIVISION','POSITION','EMPLOYEE','SHIFT') NOT NULL,
  scope_id BIGINT UNSIGNED NOT NULL,
  point_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  pool_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_weight_rule_unique (rule_id, weight_scope, scope_id),
  KEY idx_pay_bonus_weight_rule_scope (weight_scope, scope_id),
  CONSTRAINT fk_pay_bonus_weight_rule_header FOREIGN KEY (rule_id) REFERENCES pay_bonus_rule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_penalty_type (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  penalty_code VARCHAR(40) NOT NULL,
  penalty_name VARCHAR(120) NOT NULL,
  category ENUM('ATTENDANCE','DISCIPLINE','PERFORMANCE','SERVICE','PROPERTY','SOCIAL_MEDIA','HYGIENE','OTHER') NOT NULL DEFAULT 'OTHER',
  deduction_mode ENUM('FIXED_POINT','FIXED_AMOUNT','VARIABLE') NOT NULL DEFAULT 'FIXED_POINT',
  default_points_deducted DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  default_amount_deducted DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  applies_scope ENUM('PERSONAL','TEAM','BOTH') NOT NULL DEFAULT 'BOTH',
  is_manual_only TINYINT(1) NOT NULL DEFAULT 0,
  behavior_mode ENUM('AUTO','MANUAL','SEMI_MANUAL') NOT NULL DEFAULT 'MANUAL',
  auto_source ENUM('ATTENDANCE','SERVICE','TARGET','PEER','SOCIAL_MEDIA','AUDIT','CHECKLIST','OTHER') NULL,
  attendance_trigger VARCHAR(60) NULL,
  verification_cycle ENUM('PER_EVENT','DAILY','MONTHLY','UNTIL_CHANGED') NOT NULL DEFAULT 'PER_EVENT',
  approval_required TINYINT(1) NOT NULL DEFAULT 1,
  requires_evidence TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_penalty_type_code (penalty_code),
  KEY idx_pay_bonus_penalty_type_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_penalty_event (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  penalty_date DATE NOT NULL,
  rule_id BIGINT UNSIGNED NULL,
  penalty_type_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  division_id BIGINT UNSIGNED NULL,
  shift_id BIGINT UNSIGNED NULL,
  penalty_scope ENUM('PERSONAL','TEAM') NOT NULL DEFAULT 'PERSONAL',
  source_type ENUM('MANUAL','AUTO_ATTENDANCE','AUTO_SERVICE','AUTO_TARGET','AUTO_PEER') NOT NULL DEFAULT 'MANUAL',
  points_deducted DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  amount_deducted DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  reason_text VARCHAR(255) NULL,
  status ENUM('DRAFT','APPROVED','REJECTED','VOID') NOT NULL DEFAULT 'APPROVED',
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_bonus_penalty_event_date (penalty_date, status),
  KEY idx_pay_bonus_penalty_event_employee (employee_id),
  KEY idx_pay_bonus_penalty_event_division (division_id),
  KEY idx_pay_bonus_penalty_event_shift (shift_id),
  CONSTRAINT fk_pay_bonus_penalty_event_rule FOREIGN KEY (rule_id) REFERENCES pay_bonus_rule(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_penalty_event_type FOREIGN KEY (penalty_type_id) REFERENCES pay_bonus_penalty_type(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pay_bonus_penalty_event_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_penalty_event_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_penalty_event_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_penalty_event_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_penalty_event_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_service_metric_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_date DATE NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  division_id BIGINT UNSIGNED NULL,
  shift_id BIGINT UNSIGNED NULL,
  total_orders INT NOT NULL DEFAULT 0,
  served_orders INT NOT NULL DEFAULT 0,
  ontime_orders INT NOT NULL DEFAULT 0,
  late_orders INT NOT NULL DEFAULT 0,
  avg_service_minutes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  score_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  source_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_service_metric_daily (metric_date, outlet_id, division_id, shift_id),
  KEY idx_pay_bonus_service_metric_scope (metric_date, outlet_id, division_id),
  CONSTRAINT fk_pay_bonus_service_metric_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_service_metric_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_service_metric_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_pool_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bonus_date DATE NOT NULL,
  config_id BIGINT UNSIGNED NOT NULL,
  rule_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  division_id BIGINT UNSIGNED NULL,
  target_plan_id BIGINT UNSIGNED NULL,
  target_score_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  target_gate_passed TINYINT(1) NOT NULL DEFAULT 0,
  gross_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  refund_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  service_score_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  pool_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payout_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total_employee_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_employee_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  approval_status ENUM('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_pool_daily_unique (bonus_date, rule_id, outlet_id, division_id),
  KEY idx_pay_bonus_pool_daily_scope (bonus_date, outlet_id, division_id),
  KEY idx_pay_bonus_pool_daily_status (approval_status),
  CONSTRAINT fk_pay_bonus_pool_daily_config FOREIGN KEY (config_id) REFERENCES pay_bonus_config(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_pool_daily_rule FOREIGN KEY (rule_id) REFERENCES pay_bonus_rule(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_pool_daily_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_pool_daily_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_pool_daily_target FOREIGN KEY (target_plan_id) REFERENCES fin_target_plan(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_pool_daily_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_pool_daily_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_pool_shift (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  shift_start TIME NULL,
  shift_end TIME NULL,
  gross_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total_orders INT NOT NULL DEFAULT 0,
  avg_service_minutes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  service_score_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  shift_point_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  shift_pool_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  employee_count INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_pool_shift_unique (pool_id, shift_id),
  KEY idx_pay_bonus_pool_shift_shift (shift_id),
  CONSTRAINT fk_pay_bonus_pool_shift_header FOREIGN KEY (pool_id) REFERENCES pay_bonus_pool_daily(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_pool_shift_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_employee_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  pool_shift_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  attendance_status VARCHAR(30) NULL,
  division_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  position_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  employee_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  shift_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  attendance_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  target_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  service_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  peer_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  revenue_in_shift DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  raw_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  raw_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  penalty_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  penalty_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  final_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  final_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  approval_status ENUM('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_employee_daily_unique (pool_id, employee_id, shift_id),
  KEY idx_pay_bonus_employee_daily_employee (employee_id, attendance_date),
  KEY idx_pay_bonus_employee_daily_shift (shift_id),
  CONSTRAINT fk_pay_bonus_employee_daily_pool FOREIGN KEY (pool_id) REFERENCES pay_bonus_pool_daily(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_employee_daily_pool_shift FOREIGN KEY (pool_shift_id) REFERENCES pay_bonus_pool_shift(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_employee_daily_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_employee_daily_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_monthly_summary (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  summary_month CHAR(7) NOT NULL,
  config_id BIGINT UNSIGNED NOT NULL,
  rule_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  division_id BIGINT UNSIGNED NULL,
  total_raw_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_penalty_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_final_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_raw_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total_penalty_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total_final_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ph_taken_count INT NOT NULL DEFAULT 0,
  late_count INT NOT NULL DEFAULT 0,
  alpha_count INT NOT NULL DEFAULT 0,
  peer_avg_star DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  service_avg_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  target_avg_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  payout_status ENUM('DRAFT','APPROVED','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  posted_manual_adjustment_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_monthly_summary_unique (summary_month, config_id, employee_id),
  KEY idx_pay_bonus_monthly_summary_scope (summary_month, outlet_id, division_id),
  KEY idx_pay_bonus_monthly_summary_status (payout_status),
  CONSTRAINT fk_pay_bonus_monthly_summary_config FOREIGN KEY (config_id) REFERENCES pay_bonus_config(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_monthly_summary_rule FOREIGN KEY (rule_id) REFERENCES pay_bonus_rule(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_monthly_summary_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_monthly_summary_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_monthly_summary_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_monthly_summary_manual_adj FOREIGN KEY (posted_manual_adjustment_id) REFERENCES pay_manual_adjustment(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_manual_adjustment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bonus_month CHAR(7) NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  adjustment_kind ENUM('ADD','DEDUCT') NOT NULL DEFAULT 'ADD',
  adjustment_basis ENUM('POINT','AMOUNT') NOT NULL DEFAULT 'POINT',
  adjustment_value DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  reason_text VARCHAR(255) NOT NULL,
  source_type ENUM('SUPERADMIN','PEER_REVIEW','AUDIT','OTHER') NOT NULL DEFAULT 'SUPERADMIN',
  status ENUM('DRAFT','APPROVED','VOID') NOT NULL DEFAULT 'APPROVED',
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_bonus_manual_adjustment_month_employee (bonus_month, employee_id),
  CONSTRAINT fk_pay_bonus_manual_adjustment_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_manual_adjustment_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_manual_adjustment_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS perf_peer_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feedback_date DATE NOT NULL,
  from_employee_id BIGINT UNSIGNED NOT NULL,
  to_employee_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  star_rating TINYINT UNSIGNED NOT NULL,
  reason_text VARCHAR(255) NULL,
  status ENUM('SUBMITTED','APPROVED','REJECTED','VOID') NOT NULL DEFAULT 'SUBMITTED',
  moderator_id BIGINT UNSIGNED NULL,
  moderation_notes VARCHAR(255) NULL,
  bonus_adjustment_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_perf_peer_feedback_unique (feedback_date, from_employee_id, to_employee_id),
  KEY idx_perf_peer_feedback_to (to_employee_id, feedback_date, status),
  KEY idx_perf_peer_feedback_from (from_employee_id, feedback_date),
  CONSTRAINT fk_perf_peer_feedback_from_employee FOREIGN KEY (from_employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_perf_peer_feedback_to_employee FOREIGN KEY (to_employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_perf_peer_feedback_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE SET NULL,
  CONSTRAINT fk_perf_peer_feedback_moderator FOREIGN KEY (moderator_id) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_perf_peer_feedback_bonus_adjustment FOREIGN KEY (bonus_adjustment_id) REFERENCES pay_bonus_manual_adjustment(id) ON DELETE SET NULL,
  CONSTRAINT chk_perf_peer_feedback_star CHECK (star_rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pay_bonus_config (
  config_code, config_name, description, distribution_scope, pool_source_mode, pool_source_value,
  payout_percent, linked_target_required, include_shift_revenue_factor, include_service_time_factor,
  include_peer_review_factor, include_attendance_factor, include_manual_penalty_factor,
  status, notes, created_at, updated_at
)
SELECT
  'BONUS-DEFAULT',
  'Bonus Operasional Default',
  'Konfigurasi awal bonus operasional yang membaca target, omzet shift, service time, absensi, dan review 360.',
  'GLOBAL',
  'TARGET_LINKED',
  0.0000,
  100.0000,
  1,
  1,
  1,
  1,
  1,
  1,
  'ACTIVE',
  'Seed awal modul bonus 2026-06-13',
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM pay_bonus_config WHERE config_code = 'BONUS-DEFAULT'
);

COMMIT;

SELECT 'pay_bonus_config' AS metric, COUNT(*) AS total FROM pay_bonus_config
UNION ALL
SELECT 'pay_bonus_rule', COUNT(*) FROM pay_bonus_rule
UNION ALL
SELECT 'pay_bonus_penalty_type', COUNT(*) FROM pay_bonus_penalty_type
UNION ALL
SELECT 'pay_bonus_pool_daily', COUNT(*) FROM pay_bonus_pool_daily
UNION ALL
SELECT 'pay_bonus_employee_daily', COUNT(*) FROM pay_bonus_employee_daily
UNION ALL
SELECT 'perf_peer_feedback', COUNT(*) FROM perf_peer_feedback;
