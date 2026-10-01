SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('pos.report.sales.extra.index', 'Laporan Penjualan Extra POS', 'POS', 'Laporan detail penjualan POS untuk extra per item extra.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.report.sales.extra',
  'Laporan Penjualan Extra',
  'ri-add-box-line',
  '/pos/reports/sales-extra',
  p.id,
  3,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'pos.report.group'
WHERE p.page_code = 'pos.report.sales.extra.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET sort_order = 4, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pos.report.payment' AND sort_order < 4;

UPDATE sys_menu
SET sort_order = 5, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pos.report.refund' AND sort_order < 5;

UPDATE sys_menu
SET sort_order = 6, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pos.report.void' AND sort_order < 6;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.report.sales.extra.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, parent.menu_code AS parent_code, m.url, m.sort_order, m.is_active
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
WHERE m.menu_code = 'pos.report.sales.extra';
