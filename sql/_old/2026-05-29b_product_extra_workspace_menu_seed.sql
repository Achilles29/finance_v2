SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29b_product_extra_workspace_menu_seed.sql
-- Tujuan :
-- 1) Menambah workspace extra produk sebagai pintu konsep utama
-- 2) Menjaga domain extra tetap di modul produk, bukan di POS
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'master.product_extra.workspace.index',
  'Workspace Extra Produk',
  'MASTER',
  'Hub konsep extra produk, group extra, mapping produk, dan sambungannya ke POS',
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
  'master.product.extra.workspace',
  'Workspace Extra Produk',
  'ri-magic-line',
  '/master/relation/product-extra-workspace',
  p.id,
  7,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'produk.master.data'
WHERE p.page_code = 'master.product_extra.workspace.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 0, 0, NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'master.product_extra.workspace.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'master.product_extra.workspace.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'master.product_extra.workspace.index'
UNION ALL
SELECT 'master.product.extra.workspace', COUNT(*)
FROM sys_menu WHERE menu_code = 'master.product.extra.workspace';
