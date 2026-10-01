SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30a_pos_sales_channel_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman master Sales Channel POS ke sidebar
-- 2) Menyediakan permission CRUD untuk master order source / sales channel
-- 3) Menjadikan pengelolaan relasi sales channel -> service type bisa diakses dari UI
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.sales_channel.index',
  'Sales Channel POS',
  'POS',
  'Master channel penjualan POS seperti Walk In, WhatsApp, GrabFood, ShopeeFood, dan channel lain beserta relasi service type yang diizinkan.',
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
  'pos.sales-channel',
  'Sales Channel POS',
  'ri-share-forward-line',
  '/pos/sales-channels',
  p.id,
  1,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.sales_channel.index'
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
  1, 1, 1, 1, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.sales_channel.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.pos.sales_channel.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'pos.sales_channel.index'
UNION ALL
SELECT 'sys_menu.pos.sales-channel', COUNT(*)
FROM sys_menu WHERE menu_code = 'pos.sales-channel';
