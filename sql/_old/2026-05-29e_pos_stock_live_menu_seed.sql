SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29e_pos_stock_live_menu_seed.sql
-- Tujuan :
-- 1) Menambah halaman audit Stock Live POS ke sidebar
-- 2) Mendaftarkan sys_page + sys_menu + permission default
-- 3) Menjadi entry resmi untuk audit DB cache vs live calc
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.stock.live.index',
  'Stock Live POS',
  'POS',
  'Audit cache availability POS vs kalkulasi live recipe dan stok aktual',
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
  'pos.stock.live',
  'Stock Live POS',
  'ri-pulse-line',
  '/pos/stock-live',
  p.id,
  7,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.stock.live.index'
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
  1, 1, 1, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.stock.live.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.pos.stock.live.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'pos.stock.live.index'
UNION ALL
SELECT 'sys_menu.pos.stock.live', COUNT(*)
FROM sys_menu WHERE menu_code = 'pos.stock.live';
