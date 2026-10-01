SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21f_pos_availability_queue_monitor_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan layar Ketersediaan POS untuk memantau antrean cache menu
-- 2) Memberi hak proses manual yang terbatas kepada operator inventory
-- 3) Tidak mengubah stok, lot, movement, HPP, order, atau saldo kas
--
-- Prasyarat:
-- - Jalankan 2026-08-21e_pos_product_availability_queue.sql terlebih dulu
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.availability.queue.index',
  'Ketersediaan POS',
  'POS',
  'Pemantauan antrean pembaruan cache ketersediaan menu POS dan panduan cron worker.',
  1
)
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
  'pos.availability.queue',
  'Ketersediaan POS',
  'ri-refresh-line',
  '/pos/availability-queue',
  page.id,
  8,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE page.page_code = 'pos.availability.queue.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  role.id,
  page.id,
  1,
  0,
  CASE WHEN role.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG') THEN 1 ELSE 0 END,
  0,
  1,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'pos.availability.queue.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  menu.menu_code,
  menu.menu_label,
  parent.menu_code AS parent_code,
  menu.url,
  page.page_code
FROM sys_menu menu
LEFT JOIN sys_menu parent ON parent.id = menu.parent_id
LEFT JOIN sys_page page ON page.id = menu.page_id
WHERE menu.menu_code = 'pos.availability.queue';

SELECT
  role.role_code,
  permission.can_view,
  permission.can_edit
FROM auth_role_permission permission
JOIN auth_role role ON role.id = permission.role_id
JOIN sys_page page ON page.id = permission.page_id
WHERE page.page_code = 'pos.availability.queue.index'
ORDER BY role.role_code;
