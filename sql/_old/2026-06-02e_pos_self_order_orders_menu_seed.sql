SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-02e_pos_self_order_orders_menu_seed.sql
-- Tujuan :
-- 1) Menambah submenu Orderan pada rumpun Self Order POS
-- 2) Memakai page permission yang sama dengan pos.self_order.index
-- 3) Menjadikan workspace verifikasi self order mudah diakses dari sidebar
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.self_order.orders',
  'Orderan Self Order',
  'ri-file-list-3-line',
  '/pos/self-order/orders',
  p.id,
  1,
  1,
  'MAIN',
  m.id
FROM sys_page p
JOIN sys_menu m ON m.menu_code = 'pos.self_order'
WHERE p.page_code = 'pos.self_order.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_menu.pos.self_order.orders' AS seed_key, COUNT(*) AS total_rows
FROM sys_menu WHERE menu_code = 'pos.self_order.orders';
