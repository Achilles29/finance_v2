SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28g_pos_cashier_menu_seed.sql
-- Tujuan :
-- 1) Menambah halaman Kasir POS fase 1 ke sidebar POS
-- 2) Menjadikan /pos/cashier sebagai cockpit kasir utama
-- 3) Tetap memakai permission POS draft order agar rollout bertahap aman
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.cashier.index',
  'Kasir POS',
  'POS',
  'Layar kasir fase 1 untuk input order, member, bundle, confirm stock commit, dan preview void/refund',
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
  'pos.cashier',
  'Kasir POS',
  'ri-shopping-bag-3-line',
  '/pos/cashier',
  p.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.cashier.index'
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
  r.id,
  p.id,
  1, 1, 1, 0, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.cashier.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.pos.cashier.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'pos.cashier.index'
UNION ALL
SELECT 'sys_menu.pos.cashier', COUNT(*)
FROM sys_menu WHERE menu_code = 'pos.cashier';
