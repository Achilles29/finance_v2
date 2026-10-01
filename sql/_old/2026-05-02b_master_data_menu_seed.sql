SET NAMES utf8mb4;

-- ============================================================
-- Tahap 2B - Seed Menu + Page RBAC Master Data
-- Date: 2026-05-02
-- Purpose:
-- 1) Daftarkan sys_page untuk modul master data + relasi
-- 2) Tambahkan submenu di bawah grp.master
-- 3) Grant default ke role operasional tertentu
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- 1) sys_page registrations
-- ------------------------------------------------------------
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('master.uom.index',                     'Master UOM',                        'MASTER', 'List dan kelola satuan (UOM)', 1),
  ('master.operational_division.index',    'Master Divisi Operasional',         'MASTER', 'List dan kelola divisi operasional', 1),
  ('master.product_division.index',        'Master Divisi Produk',              'MASTER', 'List dan kelola divisi produk', 1),
  ('master.product_classification.index',  'Master Klasifikasi Produk',         'MASTER', 'List dan kelola klasifikasi produk', 1),
  ('master.product_category.index',        'Master Kategori Produk',            'MASTER', 'List dan kelola kategori produk', 1),
  ('master.item_category.index',           'Master Kategori Item',              'MASTER', 'List dan kelola kategori item/material', 1),
  ('master.component_category.index',      'Master Kategori Component',         'MASTER', 'List dan kelola kategori component', 1),
  ('master.material.index',                'Master Bahan Baku',                'MASTER', 'List dan kelola bahan baku', 1),
  ('master.item.index',                    'Master Item',                       'MASTER', 'List dan kelola item pembelian', 1),
  ('master.component.index',               'Master Component',                  'MASTER', 'List dan kelola component', 1),
  ('master.product.index',                 'Master Product',                    'MASTER', 'List dan kelola product', 1),
  ('master.vendor.index',                  'Master Vendor',                     'MASTER', 'List dan kelola vendor', 1),
  ('master.extra.index',                   'Master Extra',                      'MASTER', 'List dan kelola extra', 1),
  ('master.extra_group.index',             'Master Extra Group',                'MASTER', 'List dan kelola group extra', 1),
  ('master.product_recipe.index',          'Relasi Recipe Product',             'MASTER', 'Kelola line recipe product', 1),
  ('master.component_formula.index',       'Relasi Formula Component',          'MASTER', 'Kelola line formula component', 1),
  ('master.product_extra_map.index',       'Mapping Product Extra Group',       'MASTER', 'Kelola mapping product ke extra group', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- 2) sys_menu under grp.master
-- ------------------------------------------------------------
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.uom', 'Master UOM', 'ri-ruler-2-line', '/master/uom', p.id, 1, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.uom.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.category', 'Kategori & Divisi', 'ri-flow-chart', '/master/operational-division', p.id, 2, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.operational_division.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.item', 'Master Item', 'ri-box-3-line', '/master/item', p.id, 3, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.item.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.material', 'Master Bahan Baku', 'ri-flask-line', '/master/material', p.id, 4, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.material.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.component', 'Master Component', 'ri-tools-line', '/master/component', p.id, 5, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.component.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.product', 'Master Product', 'ri-restaurant-line', '/master/product', p.id, 6, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.product.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.vendor', 'Master Vendor', 'ri-truck-line', '/master/vendor', p.id, 7, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.vendor.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.extra', 'Master Extra', 'ri-add-circle-line', '/master/extra', p.id, 8, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.extra.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'master.extra.group', 'Group Extra', 'ri-menu-add-line', '/master/extra-group', p.id, 9, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'master.extra_group.index'
WHERE parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- 3) Default role grants (view+manage) for selected roles
-- ------------------------------------------------------------
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code LIKE 'master.%'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- Validation quick check
SELECT p.page_code, p.page_name
FROM sys_page p
WHERE p.page_code LIKE 'master.%'
ORDER BY p.page_code;
