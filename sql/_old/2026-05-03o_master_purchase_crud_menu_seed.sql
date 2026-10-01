SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6O - Seed menu akses cepat Master Purchase CRUD
-- ============================================================
START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('master.purchase.posting_type', 'Master Posting Type', 'MASTER', 'CRUD master posting type purchase', 1),
  ('master.purchase.purchase_type', 'Master Purchase Type', 'MASTER', 'CRUD master purchase type', 1),
  ('master.purchase.catalog', 'Master Purchase Catalog', 'MASTER', 'CRUD master purchase catalog profile', 1),
  ('master.purchase.company_account', 'Master Company Account', 'MASTER', 'CRUD akun perusahaan untuk payment', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.purchase.posting_type', 'Master Posting Type', 'ri-file-list-3-line', '/master/posting-type', p.id, 110, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.purchase.posting_type'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.purchase.purchase_type', 'Master Purchase Type', 'ri-shopping-bag-2-line', '/master/purchase-type', p.id, 120, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.purchase.purchase_type'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.purchase.catalog', 'Master Purchase Catalog', 'ri-book-shelf-line', '/master/purchase-catalog', p.id, 130, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.purchase.catalog'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.purchase.company_account', 'Master Company Account', 'ri-bank-line', '/master/company-account', p.id, 140, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.purchase.company_account'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id, p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  0,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'master.purchase.posting_type',
  'master.purchase.purchase_type',
  'master.purchase.catalog',
  'master.purchase.company_account'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT p.page_code, m.menu_code, m.url
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id
WHERE p.page_code IN (
  'master.purchase.posting_type',
  'master.purchase.purchase_type',
  'master.purchase.catalog',
  'master.purchase.company_account'
)
ORDER BY p.page_code;
