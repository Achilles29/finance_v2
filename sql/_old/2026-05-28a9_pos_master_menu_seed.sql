SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28a9_pos_master_menu_seed.sql
-- Tujuan : Menambah halaman master POS lanjutan ke sidebar POS & Kasir
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('pos.payment_method.index', 'Payment Method POS', 'POS', 'Master metode pembayaran POS dan mapping rekening kasir', 1),
  ('pos.outlet_terminal.index', 'Outlet + Terminal POS', 'POS', 'Master outlet penjualan dan terminal/perangkat kasir POS', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'pos.payment-method', 'Payment Method POS', 'ri-bank-card-line', '/pos/payment-methods', p.id, 2, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.payment_method.index'
WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'pos.outlet-terminal', 'Outlet + Terminal POS', 'ri-store-3-line', '/pos/outlets-terminals', p.id, 3, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.outlet_terminal.index'
WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 0, 1, NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('pos.payment_method.index', 'pos.outlet_terminal.index')
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','KASIR','BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
