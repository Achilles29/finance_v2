SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE att_location
  ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER radius_meter;

UPDATE att_location l
JOIN (
  SELECT id FROM att_location WHERE is_active = 1 ORDER BY id ASC LIMIT 1
) x ON x.id = l.id
SET l.is_default = 1
WHERE l.is_default = 0
  AND NOT EXISTS (SELECT 1 FROM att_location d WHERE d.is_active = 1 AND d.is_default = 1);

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.estimate.index', 'Estimasi Gaji Absensi', 'ATTENDANCE', 'Estimasi harian/bulanan berbasis absensi sebelum payroll final', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       0, 0, 0,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'attendance.estimate.index'
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
  'hr.att-estimate',
  'Estimasi Gaji',
  'ri-calculator-line',
  '/attendance/estimate',
  p.id,
  13,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'attendance.estimate.index'
WHERE parent.menu_code = 'grp.hr'
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
