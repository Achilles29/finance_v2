SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('payroll.meal_disbursement.index', 'Pencairan Uang Makan', 'PAYROLL', 'Generate kandidat uang makan, anti duplicate, dan posting paid', 1),
  ('payroll.salary_disbursement.index', 'Pencairan Gaji', 'PAYROLL', 'Finalisasi payroll period dan pencairan gaji', 1),
  ('payroll.cash_advance.index', 'Kasbon Pegawai', 'PAYROLL', 'Manajemen kasbon dan cicilan pegawai', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','ADM_FIN') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','ADM_FIN') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','ADM_FIN') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','ADM_FIN','MGR') THEN 1 ELSE 0 END,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'payroll.meal_disbursement.index',
  'payroll.salary_disbursement.index',
  'payroll.cash_advance.index'
)
WHERE r.role_code IN ('SUPERADMIN','CEO','ADM_HR','ADM_FIN','MGR')
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
  SELECT 'pay.meal-disbursement' AS menu_code, 'Pencairan Uang Makan' AS menu_label, 'ri-restaurant-2-line' AS icon, '/payroll/meal-disbursements' AS url, 'payroll.meal_disbursement.index' AS page_code, 30 AS sort_order, 'grp.payroll' AS parent_code
  UNION ALL SELECT 'pay.salary-disbursement', 'Pencairan Gaji', 'ri-bank-card-line', '/payroll/salary-disbursements', 'payroll.salary_disbursement.index', 31, 'grp.payroll'
  UNION ALL SELECT 'pay.cash-advance', 'Kasbon Pegawai', 'ri-hand-coin-line', '/payroll/cash-advances', 'payroll.cash_advance.index', 32, 'grp.payroll'
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

UPDATE sys_menu
SET icon = 'ri-scales-3-line', updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'my.adjustment';

COMMIT;
