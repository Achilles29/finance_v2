SET NAMES utf8mb4;

-- Register the meal calendar as its own access-controlled Attendance page.
-- The menu previously pointed at attendance.estimate.index, so it could not
-- be assigned independently in the role matrix.
START TRANSACTION;

INSERT INTO sys_page (
  page_code,
  page_name,
  module,
  matrix_group,
  description,
  is_active,
  created_at
) VALUES (
  'attendance.meal_calendar.index',
  'Kalender Uang Makan',
  'ATTENDANCE',
  'ATTENDANCE',
  'Kalender estimasi uang makan per pegawai dan per hari.',
  1,
  NOW()
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  matrix_group = VALUES(matrix_group),
  description = VALUES(description),
  is_active = 1,
  updated_at = NOW();

-- Keep the existing sidebar entry, but bind it to the new independent page.
UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'attendance.meal_calendar.index'
SET m.page_id = p.id,
    m.url = '/attendance/meal-calendar',
    m.is_active = 1,
    m.updated_at = NOW()
WHERE m.menu_code = 'hr.att-meal-calendar';

-- Preserve access for roles that already had access through the former page.
INSERT INTO auth_role_permission (
  role_id,
  page_id,
  can_view,
  can_create,
  can_edit,
  can_delete,
  can_export,
  created_at
)
SELECT
  source.role_id,
  target.id,
  source.can_view,
  source.can_create,
  source.can_edit,
  source.can_delete,
  source.can_export,
  NOW()
FROM auth_role_permission source
JOIN sys_page previous ON previous.id = source.page_id
JOIN sys_page target ON target.page_code = 'attendance.meal_calendar.index'
WHERE previous.page_code = 'attendance.estimate.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = NOW();

-- Requested access: Admin Gudang can open the calendar, without write rights.
INSERT INTO auth_role_permission (
  role_id,
  page_id,
  can_view,
  can_create,
  can_edit,
  can_delete,
  can_export,
  created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'attendance.meal_calendar.index'
WHERE r.role_code = 'ADM_GDG'
ON DUPLICATE KEY UPDATE
  can_view = 1,
  updated_at = NOW();

-- Active sessions compare this timestamp before rebuilding their permission cache.
UPDATE auth_role r
JOIN auth_role_permission rp ON rp.role_id = r.id
JOIN sys_page p ON p.id = rp.page_id
SET r.permissions_updated_at = NOW()
WHERE p.page_code = 'attendance.meal_calendar.index';

COMMIT;

SELECT
  p.page_code,
  p.page_name,
  p.module,
  p.matrix_group,
  m.menu_code,
  m.url,
  r.role_name,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id AND m.menu_code = 'hr.att-meal-calendar'
LEFT JOIN auth_role r ON r.role_code = 'ADM_GDG'
LEFT JOIN auth_role_permission rp ON rp.page_id = p.id AND rp.role_id = r.id
WHERE p.page_code = 'attendance.meal_calendar.index';
