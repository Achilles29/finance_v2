SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29c_sidebar_manage_runtime_menu_seed.sql
-- Tujuan :
-- 1) Mendaftarkan menu runtime sidebar yang sebelumnya masih virtual
--    ke sys_menu untuk grup Master dan monitoring Produk.
-- 2) Merapikan parent menu Master agar wrapper group tampil sebagai
--    row nyata di sidebar/manage dan runtime sidebar.
-- Catatan:
-- - Wrapper inventory tidak dibuat di sini karena DB sudah punya
--   group riil: inventory.stock.group.warehouse / division.
-- - Menu product availability tetap dibiarkan tanpa page_id agar
--   perilaku visibility saat ini tidak berubah.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  NULL,
  NULL,
  src.sort_order,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT 'master.group.product' AS menu_code, 'Produk & Extra' AS menu_label, 'ri-store-2-line' AS icon, 1 AS sort_order
  UNION ALL
  SELECT 'master.group.inventory', 'Item & Bahan', 'ri-flask-line', 2
  UNION ALL
  SELECT 'master.group.relation', 'Relasi & Formula', 'ri-links-line', 3
  UNION ALL
  SELECT 'master.group.config', 'Konfigurasi', 'ri-settings-3-line', 4
) src
JOIN sys_menu parent ON parent.menu_code = 'grp.master'
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
JOIN sys_menu target ON target.menu_code = 'master.group.product'
SET child.parent_id = target.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.sidebar_type = 'MAIN'
  AND child.menu_code IN (
    'master.product.division', 'master.product.classification', 'master.product.category',
    'master.product_division', 'master.product_classification', 'master.product_category',
    'master.product', 'master.extra', 'master.extra_group', 'master.product.extra',
    'master.product_extra_map', 'master.extra.group', 'master.extra-group'
  );

UPDATE sys_menu child
JOIN sys_menu target ON target.menu_code = 'master.group.inventory'
SET child.parent_id = target.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.sidebar_type = 'MAIN'
  AND child.menu_code IN (
    'master.uom', 'master.operational_division', 'master.item_category',
    'master.material', 'master.item', 'master.component_category', 'master.component', 'master.vendor'
  );

UPDATE sys_menu child
JOIN sys_menu target ON target.menu_code = 'master.group.relation'
SET child.parent_id = target.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.sidebar_type = 'MAIN'
  AND child.menu_code IN (
    'master.product.recipe', 'master.component.formula', 'master.product_recipe',
    'master.component_formula', 'master.relation'
  );

UPDATE sys_menu child
JOIN sys_menu target ON target.menu_code = 'master.group.config'
SET child.parent_id = target.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.sidebar_type = 'MAIN'
  AND child.menu_code IN (
    'master.variable.cost.default', 'master.variable_cost_default'
  );

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'product.monitoring.stock',
  'Monitoring Stok',
  'ri-line-chart-line',
  NULL,
  NULL,
  3,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'produk'
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
  'product.monitoring.availability',
  'Ketersediaan Produk',
  'ri-bar-chart-grouped-line',
  '/product/availability',
  NULL,
  1,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'product.monitoring.stock'
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

COMMIT;

SELECT menu_code, menu_label, parent_id, sort_order, url
FROM sys_menu
WHERE menu_code IN (
  'master.group.product',
  'master.group.inventory',
  'master.group.relation',
  'master.group.config',
  'product.monitoring.stock',
  'product.monitoring.availability'
)
ORDER BY parent_id, sort_order, menu_code;