START TRANSACTION;

UPDATE sys_menu
SET menu_label = 'Produk',
    icon = 'ri-store-2-line'
WHERE menu_code = 'produk';

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'produk.master', 'Master Produk', 'ri-folders-line', NULL, NULL, 1, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'produk'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'produk.master'
  );

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'produk'
SET child.parent_id = parent.id,
    child.menu_label = 'Master Produk',
    child.icon = 'ri-folders-line',
    child.url = NULL,
    child.page_id = NULL,
    child.sort_order = 1,
    child.is_active = 1,
    child.sidebar_type = 'MAIN'
WHERE child.menu_code = 'produk.master';

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'produk'
SET child.parent_id = parent.id,
    child.menu_label = 'Produksi Base/Prepare',
    child.icon = 'ri-tools-line',
    child.sort_order = 2,
    child.is_active = 1,
    child.sidebar_type = 'MAIN'
WHERE child.menu_code = 'grp.production';

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'produk.master.data', 'Data Produk', 'ri-restaurant-line', NULL, NULL, 1, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'produk.master'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'produk.master.data'
  );

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'produk.master.extra', 'Extra Produk', 'ri-add-circle-line', NULL, NULL, 2, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'produk.master'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'produk.master.extra'
  );

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'produk.master'
SET child.parent_id = parent.id,
    child.menu_label = 'Data Produk',
    child.icon = 'ri-restaurant-line',
    child.url = NULL,
    child.page_id = NULL,
    child.sort_order = 1,
    child.is_active = 1,
    child.sidebar_type = 'MAIN'
WHERE child.menu_code = 'produk.master.data';

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'produk.master'
SET child.parent_id = parent.id,
    child.menu_label = 'Extra Produk',
    child.icon = 'ri-add-circle-line',
    child.url = NULL,
    child.page_id = NULL,
    child.sort_order = 2,
    child.is_active = 1,
    child.sidebar_type = 'MAIN'
WHERE child.menu_code = 'produk.master.extra';

UPDATE sys_menu item
JOIN sys_menu parent ON parent.menu_code = 'produk.master.data'
SET item.parent_id = parent.id,
    item.sort_order = CASE item.menu_code
        WHEN 'master.product.division' THEN 1
        WHEN 'master.product.classification' THEN 2
        WHEN 'master.product.category' THEN 3
        WHEN 'master.product' THEN 4
        WHEN 'master.product.recipe' THEN 5
        ELSE item.sort_order
    END
WHERE item.menu_code IN (
    'master.product.division',
    'master.product.classification',
    'master.product.category',
    'master.product',
    'master.product.recipe'
);

UPDATE sys_menu item
JOIN sys_menu parent ON parent.menu_code = 'produk.master.extra'
SET item.parent_id = parent.id,
    item.sort_order = CASE item.menu_code
        WHEN 'master.extra' THEN 1
        WHEN 'master.extra.group' THEN 2
        WHEN 'master.product.extra.map' THEN 3
        ELSE item.sort_order
    END
WHERE item.menu_code IN (
    'master.extra',
    'master.extra.group',
    'master.product.extra.map'
);

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'production.component.group.master', 'Master Base/Prepare', 'ri-book-shelf-line', NULL, NULL, 1, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'grp.production'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'production.component.group.master'
  );

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'production.component.group.transaction', 'Transaksi Base/Prepare', 'ri-exchange-funds-line', NULL, NULL, 2, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'grp.production'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'production.component.group.transaction'
  );

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'production.component.group.monitoring', 'Monitoring Stok', 'ri-line-chart-line', NULL, NULL, 3, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'grp.production'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'production.component.group.monitoring'
  );

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'grp.production'
SET child.parent_id = parent.id,
    child.url = NULL,
    child.page_id = NULL,
    child.is_active = 1,
    child.sidebar_type = 'MAIN',
    child.sort_order = CASE child.menu_code
        WHEN 'production.component.group.master' THEN 1
        WHEN 'production.component.group.transaction' THEN 2
        WHEN 'production.component.group.monitoring' THEN 3
        ELSE child.sort_order
    END,
    child.menu_label = CASE child.menu_code
        WHEN 'production.component.group.master' THEN 'Master Base/Prepare'
        WHEN 'production.component.group.transaction' THEN 'Transaksi Base/Prepare'
        WHEN 'production.component.group.monitoring' THEN 'Monitoring Stok'
        ELSE child.menu_label
    END,
    child.icon = CASE child.menu_code
        WHEN 'production.component.group.master' THEN 'ri-book-shelf-line'
        WHEN 'production.component.group.transaction' THEN 'ri-exchange-funds-line'
        WHEN 'production.component.group.monitoring' THEN 'ri-line-chart-line'
        ELSE child.icon
    END
WHERE child.menu_code IN (
    'production.component.group.master',
    'production.component.group.transaction',
    'production.component.group.monitoring'
);

UPDATE sys_menu item
JOIN sys_menu parent ON parent.menu_code = 'production.component.group.master'
SET item.parent_id = parent.id,
    item.sort_order = CASE item.menu_code
        WHEN 'production.component.category' THEN 1
        WHEN 'production.component.master' THEN 2
        WHEN 'production.component.formula' THEN 3
        ELSE item.sort_order
    END
WHERE item.menu_code IN (
    'production.component.category',
    'production.component.master',
    'production.component.formula'
);

UPDATE sys_menu item
JOIN sys_menu parent ON parent.menu_code = 'production.component.group.transaction'
SET item.parent_id = parent.id,
    item.sort_order = CASE item.menu_code
        WHEN 'production.component.opening' THEN 1
        WHEN 'production.component.batch' THEN 2
        WHEN 'production.component.adjustment' THEN 3
        WHEN 'production.component.monthly' THEN 4
        ELSE item.sort_order
    END
WHERE item.menu_code IN (
    'production.component.opening',
    'production.component.batch',
    'production.component.adjustment',
    'production.component.monthly'
);

UPDATE sys_menu item
JOIN sys_menu parent ON parent.menu_code = 'production.component.group.monitoring'
SET item.parent_id = parent.id,
    item.sort_order = CASE item.menu_code
        WHEN 'production.component.stock' THEN 1
        WHEN 'production.component.movement' THEN 2
        WHEN 'production.component.daily' THEN 3
        WHEN 'production.component.lot' THEN 4
        ELSE item.sort_order
    END
WHERE item.menu_code IN (
    'production.component.stock',
    'production.component.movement',
    'production.component.daily',
    'production.component.lot'
);

INSERT INTO sys_menu (parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, created_at)
SELECT p.id, 'production.component.lot', 'Lot Component', 'ri-stack-line', '/production/component-lots', NULL, 4, 1, 'MAIN', NOW()
FROM sys_menu p
WHERE p.menu_code = 'production.component.group.monitoring'
  AND NOT EXISTS (
      SELECT 1 FROM sys_menu existing WHERE existing.menu_code = 'production.component.lot'
  );

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'production.component.group.monitoring'
SET child.parent_id = parent.id,
    child.menu_label = 'Lot Component',
    child.icon = 'ri-stack-line',
    child.url = '/production/component-lots',
    child.sort_order = 4,
    child.is_active = 1,
    child.sidebar_type = 'MAIN'
WHERE child.menu_code = 'production.component.lot';

COMMIT;