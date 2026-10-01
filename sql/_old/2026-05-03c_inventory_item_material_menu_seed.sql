SET NAMES utf8mb4;

-- ============================================================
-- Tambah menu resmi untuk flow inventory item -> material
-- ============================================================
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('inventory.item_material_flow.index', 'Flow Item ke Material', 'INVENTORY', 'Posting konsumsi item ke material per divisi operasional', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'inventory.item_material_flow',
  'Flow Item ke Material',
  'ri-exchange-funds-line',
  '/inventory/item-material-flow',
  p.id,
  40,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'inventory.item_material_flow.index'
WHERE parent.menu_code = 'grp.inventory'
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
  1, 1, 1, 0, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'inventory.item_material_flow.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, m.url
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE p.page_code = 'inventory.item_material_flow.index';
