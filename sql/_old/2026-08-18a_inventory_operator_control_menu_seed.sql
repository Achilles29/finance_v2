SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-18a_inventory_operator_control_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman operator Defisit Stok
-- 2) Menambahkan halaman operator Tutup Periode Stok
-- 3) Menempatkan keduanya dalam rumpun Inventory
--
-- Catatan:
-- - Script ini hanya menambahkan menu, halaman, dan hak akses.
-- - Tidak mengubah stok, lot, movement, atau periode yang sudah ada.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('inventory.stock.deficit.index', 'Defisit Stok', 'INVENTORY', 'Daftar pemakaian yang belum tertutup lot stok dan riwayat penyelesaiannya.', 1),
  ('inventory.stock.period.index', 'Tutup Periode Stok', 'INVENTORY', 'Kontrol periode stok bahan baku dan component untuk cut-off bulanan.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'inventory.stock.group.control',
  'Kontrol Stok',
  'ri-shield-check-line',
  NULL,
  NULL,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'grp.inventory'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'inventory.stock.deficit',
  'Defisit Stok',
  'ri-error-warning-line',
  '/inventory/stock/deficits',
  page.id,
  1,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'inventory.stock.group.control'
WHERE page.page_code = 'inventory.stock.deficit.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'inventory.stock.period',
  'Tutup Periode Stok',
  'ri-calendar-check-line',
  '/inventory/stock/periods',
  page.id,
  2,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'inventory.stock.group.control'
WHERE page.page_code = 'inventory.stock.period.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- View diberikan ke operator stok dan manajemen; perubahan status periode
-- hanya diberikan kepada admin inventory agar tidak ada penguncian tidak sengaja.
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  1,
  CASE WHEN page.page_code = 'inventory.stock.period.index'
            AND role.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG') THEN 1 ELSE 0 END,
  CASE WHEN page.page_code = 'inventory.stock.period.index'
            AND role.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG') THEN 1 ELSE 0 END,
  0,
  1,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code IN (
  'inventory.stock.deficit.index',
  'inventory.stock.period.index'
)
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, parent.menu_code AS parent_code, m.url, p.page_code
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code IN (
  'inventory.stock.group.control',
  'inventory.stock.deficit',
  'inventory.stock.period'
)
ORDER BY m.sort_order, m.menu_code;
