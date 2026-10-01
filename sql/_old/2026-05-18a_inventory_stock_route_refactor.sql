SET NAMES utf8mb4;

-- ============================================================
-- Refactor menu stok ke rumpun Inventory dan hapus item-material-flow
-- ============================================================
START TRANSACTION;

UPDATE sys_menu
SET
  menu_label = 'Inventory',
  icon = 'ri-archive-stack-line',
  updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'grp.inventory';

UPDATE sys_menu
SET
  menu_label = 'PO & SR',
  icon = 'ri-shopping-cart-2-line',
  updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'grp.purchase';

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'inventory.stock.group.warehouse',
  'Stok Gudang',
  'ri-building-2-line',
  NULL,
  NULL,
  10,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'grp.inventory'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'inventory.stock.group.division',
  'Stok Divisi',
  'ri-store-2-line',
  NULL,
  NULL,
  20,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'grp.inventory'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu m
JOIN sys_menu parent ON parent.menu_code = 'inventory.stock.group.warehouse'
SET
  m.parent_id = parent.id,
  m.menu_label = CASE m.menu_code
    WHEN 'purchase.stock.warehouse.matrix' THEN 'Daily Gudang Matrix'
    WHEN 'purchase.stock.warehouse' THEN 'Stok Gudang'
    WHEN 'purchase.stock.opening.warehouse' THEN 'Opening Gudang'
    WHEN 'purchase.stock.warehouse.movement' THEN 'Keluar Masuk Gudang'
    WHEN 'purchase.stock.warehouse.daily' THEN 'Stok Bulanan / Daily Gudang'
    ELSE m.menu_label
  END,
  m.url = CASE m.menu_code
    WHEN 'purchase.stock.warehouse.matrix' THEN '/inventory-warehouse-daily'
    WHEN 'purchase.stock.warehouse' THEN '/inventory/stock/warehouse'
    WHEN 'purchase.stock.opening.warehouse' THEN '/inventory/stock/opening/warehouse'
    WHEN 'purchase.stock.warehouse.movement' THEN '/inventory/stock/warehouse/movement'
    WHEN 'purchase.stock.warehouse.daily' THEN '/inventory/stock/warehouse/daily'
    ELSE m.url
  END,
  m.sort_order = CASE m.menu_code
    WHEN 'purchase.stock.warehouse.matrix' THEN 10
    WHEN 'purchase.stock.warehouse' THEN 20
    WHEN 'purchase.stock.opening.warehouse' THEN 30
    WHEN 'purchase.stock.warehouse.movement' THEN 40
    WHEN 'purchase.stock.warehouse.daily' THEN 50
    ELSE m.sort_order
  END,
  m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code IN (
  'purchase.stock.warehouse.matrix',
  'purchase.stock.warehouse',
  'purchase.stock.opening.warehouse',
  'purchase.stock.warehouse.movement',
  'purchase.stock.warehouse.daily'
);

UPDATE sys_menu m
JOIN sys_menu parent ON parent.menu_code = 'inventory.stock.group.division'
SET
  m.parent_id = parent.id,
  m.menu_label = CASE m.menu_code
    WHEN 'purchase.stock.material.matrix' THEN 'Daily Material Matrix'
    WHEN 'purchase.stock.division' THEN 'Stok Divisi'
    WHEN 'purchase.stock.opening.division' THEN 'Opening Bahan Baku Divisi'
    WHEN 'purchase.stock.division.movement' THEN 'Keluar Masuk Divisi'
    WHEN 'purchase.stock.division.daily' THEN 'Stok Bulanan / Daily Divisi'
    ELSE m.menu_label
  END,
  m.url = CASE m.menu_code
    WHEN 'purchase.stock.material.matrix' THEN '/inventory-material-daily'
    WHEN 'purchase.stock.division' THEN '/inventory/stock/division'
    WHEN 'purchase.stock.opening.division' THEN '/inventory/stock/opening/division'
    WHEN 'purchase.stock.division.movement' THEN '/inventory/stock/division/movement'
    WHEN 'purchase.stock.division.daily' THEN '/inventory/stock/division/daily'
    ELSE m.url
  END,
  m.sort_order = CASE m.menu_code
    WHEN 'purchase.stock.material.matrix' THEN 10
    WHEN 'purchase.stock.division' THEN 20
    WHEN 'purchase.stock.opening.division' THEN 30
    WHEN 'purchase.stock.division.movement' THEN 40
    WHEN 'purchase.stock.division.daily' THEN 50
    ELSE m.sort_order
  END,
  m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code IN (
  'purchase.stock.material.matrix',
  'purchase.stock.division',
  'purchase.stock.opening.division',
  'purchase.stock.division.movement',
  'purchase.stock.division.daily'
);

DELETE arp
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id
WHERE p.page_code = 'inventory.item_material_flow.index';

DELETE FROM sys_menu
WHERE menu_code = 'inventory.item_material_flow';

DELETE FROM sys_page
WHERE page_code = 'inventory.item_material_flow.index';

COMMIT;

SELECT menu_code, menu_label, url
FROM sys_menu
WHERE menu_code IN (
  'inventory.stock.group.warehouse',
  'inventory.stock.group.division',
  'purchase.stock.warehouse.matrix',
  'purchase.stock.warehouse',
  'purchase.stock.opening.warehouse',
  'purchase.stock.warehouse.movement',
  'purchase.stock.warehouse.daily',
  'purchase.stock.material.matrix',
  'purchase.stock.division',
  'purchase.stock.opening.division',
  'purchase.stock.division.movement',
  'purchase.stock.division.daily'
)
ORDER BY sort_order, menu_label;