SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('master.bank.index', 'Master Bank', 'MASTER', 'Daftar bank umum untuk referensi rekening perusahaan dan pegawai', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id,
  page_id,
  can_view,
  can_create,
  can_edit,
  can_delete,
  can_export,
  created_at,
  updated_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_FIN','ADM_HR','MGR') THEN 1 ELSE 0 END,
  NOW(),
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'master.bank.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','ADM_FIN','ADM_HR','MGR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (
  menu_code,
  menu_label,
  icon,
  url,
  page_id,
  sort_order,
  is_active,
  sidebar_type,
  parent_id
)
SELECT
  'master.bank',
  'Master Bank',
  'ri-bank-line',
  '/master/bank',
  p.id,
  2,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.bank.index'
WHERE parent.menu_code = 'grp.master'
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
