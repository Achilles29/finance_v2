SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30h_inventory_stock_division_reconcile_menu_seed.sql
-- Tujuan :
-- 1) Menambah halaman Banding Stok Akhir ke sidebar stok divisi
-- 2) Mendaftarkan sys_page + sys_menu + permission default
-- 3) Menjadi entry resmi audit stok akhir lintas halaman divisi
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'purchase.stock.division.reconcile.index',
  'Banding Stok Akhir Divisi',
  'PURCHASE',
  'Banding stok akhir divisi antara stok aktif, material daily, daily rollup, dan movement mentah',
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
  'purchase.stock.division.reconcile',
  'Banding Stok Akhir',
  'ri-scales-3-line',
  '/inventory/stock/division/reconcile',
  p.id,
  8,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN (
  SELECT id
  FROM sys_menu
  WHERE menu_code IN ('inventory.stock.group.division', 'purchase.stock.division')
  ORDER BY FIELD(menu_code, 'inventory.stock.group.division', 'purchase.stock.division')
  LIMIT 1
) parent
WHERE p.page_code = 'purchase.stock.division.reconcile.index'
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
  1, 0, 0, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.stock.division.reconcile.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.purchase.stock.division.reconcile.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'purchase.stock.division.reconcile.index'
UNION ALL
SELECT 'sys_menu.purchase.stock.division.reconcile', COUNT(*)
FROM sys_menu WHERE menu_code = 'purchase.stock.division.reconcile';
