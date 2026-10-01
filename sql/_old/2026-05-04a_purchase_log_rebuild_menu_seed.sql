SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6N - Sidebar/menu Purchase: Log + Rebuild Impact
-- ============================================================
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('purchase.order.log.index', 'Purchase Order Log', 'PURCHASE', 'Timeline log transaksi purchase order', 1),
  ('purchase.rebuild.impact.index', 'Purchase Rebuild Impact', 'PURCHASE', 'Rebuild/resync dampak purchase secara terstruktur', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.order.log',
  'Log Purchase',
  'ri-file-list-3-line',
  '/purchase-orders/logs',
  p.id,
  12,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.order.log.index'
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
  'purchase.rebuild.impact',
  'Rebuild Impact',
  'ri-refresh-line',
  '/purchase/rebuild-impact',
  p.id,
  13,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.rebuild.impact.index'
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
  CASE WHEN p.page_code = 'purchase.rebuild.impact.index' THEN 1 ELSE 0 END,
  0,
  CASE WHEN p.page_code = 'purchase.order.log.index' THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('purchase.order.log.index', 'purchase.rebuild.impact.index')
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
WHERE p.page_code IN ('purchase.order.log.index', 'purchase.rebuild.impact.index')
ORDER BY p.page_code;
