SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-01a_inventory_and_production_menu_sync.sql
-- Tujuan :
-- 1) Menyinkronkan menu lot inventory dengan struktur sidebar stok terbaru
-- 2) Menambahkan menu reconcile component ke monitoring Base/Prepare
-- 3) Menormalkan urutan child monitoring agar konsisten dengan runtime sidebar
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.warehouse.lot',
  'Lot Gudang',
  'ri-stack-line',
  '/inventory/stock/warehouse/lot',
  p.id,
  7,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN (
  SELECT id
  FROM sys_menu
  WHERE menu_code IN ('inventory.stock.group.warehouse', 'purchase.stock.warehouse')
  ORDER BY FIELD(menu_code, 'inventory.stock.group.warehouse', 'purchase.stock.warehouse')
  LIMIT 1
) parent
WHERE p.page_code = 'purchase.stock.warehouse.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.division.lot',
  'Lot Divisi',
  'ri-stack-line',
  '/inventory/stock/division/lot',
  p.id,
  7,
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
WHERE p.page_code = 'purchase.stock.division.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'production.component.reconcile',
  'Reconcile Base/Prepare',
  'ri-scales-3-line',
  '/production/component-reconcile',
  p.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN (
  SELECT id
  FROM sys_menu
  WHERE menu_code IN ('production.component.group.monitoring', 'grp.production')
  ORDER BY FIELD(menu_code, 'production.component.group.monitoring', 'grp.production')
  LIMIT 1
) parent
WHERE p.page_code = 'production.component.daily.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu child
JOIN (
  SELECT id
  FROM sys_menu
  WHERE menu_code IN ('production.component.group.monitoring', 'grp.production')
  ORDER BY FIELD(menu_code, 'production.component.group.monitoring', 'grp.production')
  LIMIT 1
) parent ON 1 = 1
SET child.parent_id = parent.id,
    child.menu_label = CASE child.menu_code
      WHEN 'production.component.reconcile' THEN 'Reconcile Base/Prepare'
      WHEN 'production.component.lot' THEN 'Lot Component'
      ELSE child.menu_label
    END,
    child.icon = CASE child.menu_code
      WHEN 'production.component.reconcile' THEN 'ri-scales-3-line'
      WHEN 'production.component.lot' THEN 'ri-stack-line'
      ELSE child.icon
    END,
    child.url = CASE child.menu_code
      WHEN 'production.component.reconcile' THEN '/production/component-reconcile'
      WHEN 'production.component.lot' THEN '/production/component-lots'
      ELSE child.url
    END,
    child.page_id = CASE child.menu_code
      WHEN 'production.component.reconcile' THEN (
        SELECT id FROM sys_page WHERE page_code = 'production.component.daily.index' LIMIT 1
      )
      WHEN 'production.component.lot' THEN NULL
      ELSE child.page_id
    END,
    child.sort_order = CASE child.menu_code
      WHEN 'production.component.stock' THEN 1
      WHEN 'production.component.movement' THEN 2
      WHEN 'production.component.daily' THEN 3
      WHEN 'production.component.reconcile' THEN 4
      WHEN 'production.component.lot' THEN 5
      ELSE child.sort_order
    END,
    child.is_active = 1,
    child.sidebar_type = 'MAIN',
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN (
  'production.component.stock',
  'production.component.movement',
  'production.component.daily',
  'production.component.reconcile',
  'production.component.lot'
);

COMMIT;

SELECT 'sys_menu.purchase.stock.warehouse.lot' AS seed_key, COUNT(*) AS total_rows
FROM sys_menu WHERE menu_code = 'purchase.stock.warehouse.lot'
UNION ALL
SELECT 'sys_menu.purchase.stock.division.lot', COUNT(*)
FROM sys_menu WHERE menu_code = 'purchase.stock.division.lot'
UNION ALL
SELECT 'sys_menu.production.component.reconcile', COUNT(*)
FROM sys_menu WHERE menu_code = 'production.component.reconcile';