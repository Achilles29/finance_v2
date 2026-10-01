SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6O - Sidebar/menu Purchase: Inventory Daily Matrix
-- ============================================================
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  (
    'purchase.stock.warehouse.matrix.index',
    'Purchase Inventory Warehouse Daily Matrix',
    'PURCHASE',
    'Halaman matrix daily gudang dengan tanggal horizontal dan detail mutasi',
    1
  ),
  (
    'purchase.stock.material.matrix.index',
    'Purchase Inventory Material Daily Matrix',
    'PURCHASE',
    'Halaman matrix daily material per divisi dengan tanggal horizontal dan detail mutasi',
    1
  )
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.warehouse.matrix',
  'Inventory Warehouse Daily',
  'ri-bar-chart-horizontal-line',
  '/inventory-warehouse-daily',
  p.id,
  14,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.warehouse.matrix.index'
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
  'purchase.stock.material.matrix',
  'Inventory Material Daily',
  'ri-line-chart-line',
  '/inventory-material-daily',
  p.id,
  15,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.material.matrix.index'
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
  'purchase.stock.warehouse.matrix.index',
  'purchase.stock.material.matrix.index'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT p.page_code, m.menu_code, m.url, m.sort_order
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id
WHERE p.page_code IN (
  'purchase.stock.warehouse.matrix.index',
  'purchase.stock.material.matrix.index'
)
ORDER BY p.page_code;
