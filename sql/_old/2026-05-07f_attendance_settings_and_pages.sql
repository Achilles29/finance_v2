SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Extend attendance policy settings
-- =========================================================
ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS attendance_calc_mode ENUM('DAILY','MONTHLY') NOT NULL DEFAULT 'DAILY' AFTER default_work_days_per_month,
  ADD COLUMN IF NOT EXISTS payroll_late_deduction_scope ENUM('BASIC_ONLY','THP_TOTAL') NOT NULL DEFAULT 'BASIC_ONLY' AFTER attendance_calc_mode,
  ADD COLUMN IF NOT EXISTS allowance_late_treatment ENUM('FULL_IF_PRESENT','DEDUCT_IF_LATE') NOT NULL DEFAULT 'FULL_IF_PRESENT' AFTER payroll_late_deduction_scope,
  ADD COLUMN IF NOT EXISTS meal_calc_mode ENUM('MONTHLY','CUSTOM') NOT NULL DEFAULT 'MONTHLY' AFTER allowance_late_treatment,
  ADD COLUMN IF NOT EXISTS operation_start_time TIME NULL AFTER meal_calc_mode,
  ADD COLUMN IF NOT EXISTS operation_end_time TIME NULL AFTER operation_start_time,
  ADD COLUMN IF NOT EXISTS night_shift_checkout_credit_after TIME NULL AFTER operation_end_time,
  ADD COLUMN IF NOT EXISTS night_shift_checkout_credit_to_operation_end TINYINT(1) NOT NULL DEFAULT 1 AFTER night_shift_checkout_credit_after,
  ADD COLUMN IF NOT EXISTS ph_auto_presence_on_open TINYINT(1) NOT NULL DEFAULT 1 AFTER night_shift_checkout_credit_to_operation_end,
  ADD COLUMN IF NOT EXISTS ph_requires_clock_in_out TINYINT(1) NOT NULL DEFAULT 0 AFTER ph_auto_presence_on_open,
  ADD COLUMN IF NOT EXISTS ph_expiry_months INT UNSIGNED NOT NULL DEFAULT 3 AFTER ph_requires_clock_in_out,
  ADD COLUMN IF NOT EXISTS ph_gets_meal_allowance TINYINT(1) NOT NULL DEFAULT 0 AFTER ph_expiry_months,
  ADD COLUMN IF NOT EXISTS ph_gets_bonus TINYINT(1) NOT NULL DEFAULT 0 AFTER ph_gets_meal_allowance;

UPDATE att_attendance_policy
SET operation_start_time = COALESCE(operation_start_time, '08:00:00'),
    operation_end_time = COALESCE(operation_end_time, '23:00:00'),
    night_shift_checkout_credit_after = COALESCE(night_shift_checkout_credit_after, '22:00:00');

-- =========================================================
-- Register pages
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.settings.index', 'Pengaturan Absensi & Payroll', 'ATTENDANCE', 'Policy absensi dan payroll scope', 1),
  ('attendance.daily.index', 'Rekap Absensi Harian', 'ATTENDANCE', 'Rekap harian tunggal dari att_daily', 1),
  ('attendance.logs.index', 'Log Presensi', 'ATTENDANCE', 'Log checkin/checkout dari att_presence', 1),
  ('attendance.schedules.index', 'Jadwal Shift Pegawai', 'ATTENDANCE', 'Monitoring jadwal shift pegawai', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'attendance.settings.index',
  'attendance.daily.index',
  'attendance.logs.index',
  'attendance.schedules.index'
)
WHERE r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id,
  src.sort_order,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT 'hr.att-settings' AS menu_code, 'Pengaturan Absensi' AS menu_label, 'ri-settings-3-line' AS icon, '/attendance/settings' AS url, 'attendance.settings.index' AS page_code, 3 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL SELECT 'hr.att-daily', 'Rekap Absensi', 'ri-calendar-check-line', '/attendance/daily', 'attendance.daily.index', 7, 'grp.hr'
  UNION ALL SELECT 'hr.att-logs', 'Log Presensi', 'ri-fingerprint-line', '/attendance/logs', 'attendance.logs.index', 8, 'grp.hr'
  UNION ALL SELECT 'hr.att-schedules', 'Jadwal Shift', 'ri-time-line', '/attendance/schedules', 'attendance.schedules.index', 9, 'grp.hr'
) src
JOIN sys_menu parent ON parent.menu_code = src.parent_code
JOIN sys_page p ON p.page_code = src.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
