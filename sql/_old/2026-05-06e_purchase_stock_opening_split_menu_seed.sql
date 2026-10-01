SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6E - Split sidebar Opening Stok (Gudang vs Divisi)
-- ============================================================
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('purchase.stock.opening.warehouse.index', 'Purchase Opening Gudang', 'PURCHASE', 'Input dan monitoring opening stok gudang', 1),
  ('purchase.stock.opening.division.index', 'Purchase Opening Divisi', 'PURCHASE', 'Input dan monitoring opening bahan baku divisi', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.opening.warehouse',
  'Opening Gudang',
  'ri-archive-drawer-line',
  '/purchase/stock/opening/warehouse',
  p.id,
  31,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.opening.warehouse.index'
WHERE parent.menu_code = 'grp.purchase'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.opening.division',
  'Opening Divisi',
  'ri-archive-drawer-line',
  '/purchase/stock/opening/division',
  p.id,
  32,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.opening.division.index'
WHERE parent.menu_code = 'grp.purchase'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  0,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.stock.opening.warehouse.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  0,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.stock.opening.division.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'purchase.stock.opening';

COMMIT;

SELECT p.page_code, m.menu_code, m.url, m.is_active
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id
WHERE p.page_code IN (
  'purchase.stock.opening.index',
  'purchase.stock.opening.warehouse.index',
  'purchase.stock.opening.division.index'
)
ORDER BY p.page_code, m.menu_code;
