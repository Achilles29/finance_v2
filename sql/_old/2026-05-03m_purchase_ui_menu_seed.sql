SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6M - Seed page/menu/permission UI Purchase lanjutan
-- Halaman: Rekening, Stok Gudang, Stok Divisi
-- ============================================================
START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES ('grp.purchase', 'Purchase', 'ri-shopping-bag-3-line', '#', NULL, 300, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('purchase.account.index', 'Purchase Account Monitor', 'PURCHASE', 'Monitoring rekening perusahaan untuk purchase', 1),
  ('purchase.stock.warehouse.index', 'Purchase Warehouse Stock Monitor', 'PURCHASE', 'Monitoring stok gudang dari receipt purchase', 1),
  ('purchase.stock.division.index', 'Purchase Division Stock Monitor', 'PURCHASE', 'Monitoring stok divisi dari receipt purchase', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.account',
  'Rekening Purchase',
  'ri-bank-line',
  '/purchase/account',
  p.id,
  20,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.account.index'
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
  'purchase.stock.warehouse',
  'Stok Gudang Purchase',
  'ri-building-2-line',
  '/purchase/stock/warehouse',
  p.id,
  30,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.warehouse.index'
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
  'purchase.stock.division',
  'Stok Divisi Purchase',
  'ri-store-2-line',
  '/purchase/stock/division',
  p.id,
  40,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.division.index'
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
  0,
  0,
  0,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'purchase.account.index',
  'purchase.stock.warehouse.index',
  'purchase.stock.division.index'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT p.page_code, m.menu_code, m.url
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id
WHERE p.page_code IN (
  'purchase.account.index',
  'purchase.stock.warehouse.index',
  'purchase.stock.division.index'
)
ORDER BY p.page_code;
