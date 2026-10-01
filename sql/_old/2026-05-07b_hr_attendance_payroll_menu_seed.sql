SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Register pages for HR + Attendance + Payroll foundation
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('hr.org_division.index', 'Master Divisi Organisasi', 'HR', 'CRUD org_division via master module', 1),
  ('hr.org_position.index', 'Master Jabatan Organisasi', 'HR', 'CRUD org_position via master module', 1),
  ('hr.org_employee.index', 'Master Pegawai', 'HR', 'CRUD org_employee via master module', 1),
  ('attendance.shift.index', 'Master Shift', 'ATTENDANCE', 'CRUD att_shift via master module', 1),
  ('attendance.location.index', 'Master Lokasi Absensi', 'ATTENDANCE', 'CRUD att_location via master module', 1),
  ('attendance.holiday.index', 'Master Hari Libur', 'ATTENDANCE', 'CRUD att_holiday_calendar via master module', 1),
  ('payroll.component.index', 'Master Komponen Gaji', 'PAYROLL', 'CRUD pay_salary_component via master module', 1),
  ('payroll.profile.index', 'Master Profil Gaji', 'PAYROLL', 'CRUD pay_salary_profile via master module', 1),
  ('payroll.assignment.index', 'Assignment Profil Gaji', 'PAYROLL', 'CRUD pay_salary_assignment via master module', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- =========================================================
-- Role permission seed
-- =========================================================
INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1 AS can_view,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END AS can_create,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END AS can_edit,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END AS can_delete,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END AS can_export,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'hr.org_division.index',
  'hr.org_position.index',
  'hr.org_employee.index',
  'attendance.shift.index',
  'attendance.location.index',
  'attendance.holiday.index',
  'payroll.component.index',
  'payroll.profile.index',
  'payroll.assignment.index'
)
WHERE r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

-- =========================================================
-- Menu seed under existing group: grp.hr and grp.payroll
-- =========================================================
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id AS page_id,
  src.sort_order,
  1 AS is_active,
  'MAIN' AS sidebar_type,
  parent.id AS parent_id
FROM (
  SELECT 'hr.org-division' AS menu_code, 'Divisi Organisasi' AS menu_label, 'ri-git-branch-line' AS icon, '/master/org-division' AS url, 'hr.org_division.index' AS page_code, 1 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL SELECT 'hr.org-position', 'Jabatan Organisasi', 'ri-user-star-line', '/master/org-position', 'hr.org_position.index', 2, 'grp.hr'
  UNION ALL SELECT 'hr.org-employee', 'Data Pegawai', 'ri-team-line', '/master/org-employee', 'hr.org_employee.index', 3, 'grp.hr'
  UNION ALL SELECT 'hr.att-shift', 'Master Shift', 'ri-time-line', '/master/att-shift', 'attendance.shift.index', 4, 'grp.hr'
  UNION ALL SELECT 'hr.att-location', 'Lokasi Absensi', 'ri-map-pin-line', '/master/att-location', 'attendance.location.index', 5, 'grp.hr'
  UNION ALL SELECT 'hr.att-holiday', 'Hari Libur', 'ri-calendar-event-line', '/master/att-holiday', 'attendance.holiday.index', 6, 'grp.hr'
  UNION ALL SELECT 'pay.component', 'Komponen Gaji', 'ri-wallet-3-line', '/master/pay-component', 'payroll.component.index', 1, 'grp.payroll'
  UNION ALL SELECT 'pay.profile', 'Profil Gaji', 'ri-file-settings-line', '/master/pay-profile', 'payroll.profile.index', 2, 'grp.payroll'
  UNION ALL SELECT 'pay.assignment', 'Assignment Profil', 'ri-links-line', '/master/pay-assignment', 'payroll.assignment.index', 3, 'grp.payroll'
) src
JOIN sys_menu parent ON parent.menu_code = src.parent_code
LEFT JOIN sys_page p ON p.page_code = src.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
