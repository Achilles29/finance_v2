SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28b_product_bundle_menu_seed.sql
-- Tujuan :
-- 1) Menambah halaman Bundle Produk di domain Produk
-- 2) Menempatkan menu di sidebar Produk, bukan di POS
-- 3) Menyiapkan default permission untuk role master terkait
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'master.product_bundle.index',
  'Bundle Produk',
  'MASTER',
  'Kelola bundle produk untuk kebutuhan penjualan POS',
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
  'master.product.bundle',
  'Bundle Produk',
  'ri-gift-2-line',
  '/master/relation/product-bundle',
  p.id,
  6,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'produk.master.data'
WHERE p.page_code = 'master.product_bundle.index'
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
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'master.product_bundle.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
