SET NAMES utf8mb4;

-- A3-1: materialize the runtime-only navigation registry in sys_menu.
-- Repeat-safe; execute with a delimiter-aware MySQL/MariaDB client.
-- This file intentionally does not remove page permission rows.
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_a3_navigation_registry_assert_20260904a$$
CREATE PROCEDURE sp_a3_navigation_registry_assert_20260904a(IN p_phase VARCHAR(8))
BEGIN
  DECLARE v_issues INT DEFAULT 0;

  IF p_phase = 'PRE' THEN
    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'grp.master' menu_code
      UNION ALL SELECT 'produk'
      UNION ALL SELECT 'grp.pos'
      UNION ALL SELECT 'production.component.group.transaction'
      UNION ALL SELECT 'inventory.stock.group.warehouse'
      UNION ALL SELECT 'inventory.stock.group.division'
    ) required_parent
    LEFT JOIN sys_menu parent
      ON parent.menu_code = required_parent.menu_code AND parent.is_active = 1
    WHERE parent.id IS NULL;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 preflight failed: required active navigation parent is missing';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'product.availability' page_code
      UNION ALL SELECT 'production.component.daily.recon.index'
      UNION ALL SELECT 'production.component.daily.index'
      UNION ALL SELECT 'production.component.lots'
      UNION ALL SELECT 'production.component.opname.monthly'
      UNION ALL SELECT 'purchase.stock.division.index'
      UNION ALL SELECT 'inventory.stock.opname.warehouse.monthly'
      UNION ALL SELECT 'inventory.stock.opname.division.monthly'
      UNION ALL SELECT 'inventory.stock.opening.division.generated'
      UNION ALL SELECT 'purchase.stock.warehouse.index'
      UNION ALL SELECT 'pos.cashier.index'
      UNION ALL SELECT 'pos.order.monitor.index'
      UNION ALL SELECT 'pos.order.paid.index'
      UNION ALL SELECT 'pos.report.sales.index'
      UNION ALL SELECT 'pos.report.cost_control.index'
      UNION ALL SELECT 'pos.report.sales.detail.index'
      UNION ALL SELECT 'pos.report.sales.extra.index'
      UNION ALL SELECT 'pos.report.payment.index'
      UNION ALL SELECT 'pos.report.refund.index'
      UNION ALL SELECT 'pos.report.void.index'
    ) required_page
    LEFT JOIN sys_page page
      ON page.page_code = required_page.page_code AND page.is_active = 1
    WHERE page.id IS NULL;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 preflight failed: required active page registry is missing';
    END IF;
  ELSEIF p_phase = 'POST' THEN
    SELECT COUNT(*) INTO v_issues
    FROM sys_menu menu
    LEFT JOIN sys_page page ON page.id = menu.page_id
    WHERE menu.is_active = 1
      AND TRIM(COALESCE(menu.url, '')) <> ''
      AND TRIM(COALESCE(menu.url, '')) <> '#'
      AND LOWER(TRIM(COALESCE(menu.url, ''))) NOT IN ('javascript:void(0)', 'javascript:void(0);')
      AND (menu.page_id IS NULL OR page.id IS NULL OR page.is_active <> 1);
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: active URL without active page';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM sys_menu
    WHERE is_active = 1 AND TRIM(COALESCE(icon, '')) = '';
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: active menu icon is missing';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT LOWER(TRIM(menu_code)) canonical_code
      FROM sys_menu WHERE is_active = 1
      GROUP BY LOWER(TRIM(menu_code)) HAVING COUNT(*) > 1
    ) duplicate_code;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: duplicate active menu code';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT LOWER(TRIM(BOTH '/' FROM TRIM(url))) canonical_url
      FROM sys_menu
      WHERE is_active = 1
        AND TRIM(COALESCE(url, '')) <> ''
        AND TRIM(COALESCE(url, '')) <> '#'
        AND LOWER(TRIM(COALESCE(url, ''))) NOT IN ('javascript:void(0)', 'javascript:void(0);')
      GROUP BY LOWER(TRIM(BOTH '/' FROM TRIM(url))) HAVING COUNT(*) > 1
    ) duplicate_url;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: duplicate active menu URL';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT sidebar_type, COALESCE(parent_id, 0) parent_key, sort_order
      FROM sys_menu WHERE is_active = 1
      GROUP BY sidebar_type, COALESCE(parent_id, 0), sort_order HAVING COUNT(*) > 1
    ) sort_collision;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: active sibling sort collision';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM sys_sidebar_favorite favorite
    LEFT JOIN sys_menu menu ON menu.id = favorite.menu_id
    LEFT JOIN sys_page page ON page.id = menu.page_id
    WHERE menu.id IS NULL OR menu.is_active <> 1
      OR TRIM(COALESCE(menu.url, '')) IN ('', '#', 'javascript:void(0)', 'javascript:void(0);')
      OR (menu.page_id IS NOT NULL AND (page.id IS NULL OR page.is_active <> 1));
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: invalid sidebar favorite';
    END IF;

    SELECT COUNT(DISTINCT menu.id) INTO v_issues
    FROM sys_menu menu
    JOIN sys_menu child ON child.parent_id = menu.id AND child.is_active = 1
    WHERE menu.is_active = 1
      AND TRIM(COALESCE(menu.url, '')) <> ''
      AND TRIM(COALESCE(menu.url, '')) <> '#'
      AND LOWER(TRIM(COALESCE(menu.url, ''))) NOT IN ('javascript:void(0)', 'javascript:void(0);');
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: active group has a real URL';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'master.group.product' child_code, 'grp.master' parent_code
      UNION ALL SELECT 'master.group.inventory', 'grp.master'
      UNION ALL SELECT 'master.group.relation', 'grp.master'
      UNION ALL SELECT 'master.group.config', 'grp.master'
      UNION ALL SELECT 'product.monitoring.availability', 'product.monitoring.stock'
      UNION ALL SELECT 'purchase.stock.division.lot', 'inventory.stock.group.division'
      UNION ALL SELECT 'inventory.stock.opname.warehouse.monthly', 'inventory.stock.group.warehouse'
      UNION ALL SELECT 'inventory.stock.opname.division.monthly', 'inventory.stock.group.division'
      UNION ALL SELECT 'inventory.stock.opening.division.generated', 'inventory.stock.group.division'
      UNION ALL SELECT 'inventory.stock.opening.warehouse.generated', 'inventory.stock.group.warehouse'
      UNION ALL SELECT 'production.component.daily.recon', 'production.component.group.transaction'
      UNION ALL SELECT 'production.component.reconcile', 'production.component.group.transaction'
      UNION ALL SELECT 'production.component.lot', 'production.component.group.transaction'
      UNION ALL SELECT 'production.component.opening.monthly', 'production.component.group.transaction'
      UNION ALL SELECT 'production.component.opname.monthly', 'production.component.group.transaction'
    ) expected
    LEFT JOIN sys_menu child ON child.menu_code = expected.child_code AND child.is_active = 1
    LEFT JOIN sys_menu parent ON parent.id = child.parent_id
    WHERE child.id IS NULL OR parent.menu_code <> expected.parent_code;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: canonical menu parent mismatch';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM sys_menu
    WHERE menu_code = 'finance.sales_margin.pos' AND is_active = 1;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3-1 postflight failed: finance sales alias remains active';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A3-1 assertion failed: unknown phase';
  END IF;
END$$

DELIMITER ;

CALL sp_a3_navigation_registry_assert_20260904a('PRE');
START TRANSACTION;

-- Canonical group rows previously synthesized by layout/sidebar.php.
INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, NULL, NULL, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'master.group.product' menu_code, 'Produk & Extra' menu_label, 'ri-store-2-line' icon, 1 sort_order
  UNION ALL SELECT 'master.group.inventory', 'Item & Bahan', 'ri-flask-line', 2
  UNION ALL SELECT 'master.group.relation', 'Relasi & Formula', 'ri-links-line', 3
  UNION ALL SELECT 'master.group.config', 'Konfigurasi', 'ri-settings-3-line', 4
) seed
JOIN sys_menu parent ON parent.menu_code = 'grp.master'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL, page_id = NULL,
  is_active = 1, sidebar_type = 'MAIN',
  parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'product.monitoring.stock', 'Monitoring Stok', 'ri-line-chart-line', NULL, NULL, 999, 1, 'MAIN', parent.id
FROM sys_menu parent WHERE parent.menu_code = 'produk'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL,
  is_active = 1, sidebar_type = 'MAIN', updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'pos.report.group', 'Laporan POS', 'ri-bar-chart-box-line', NULL, NULL, 995, 1, 'MAIN', parent.id
FROM sys_menu parent WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL,
  is_active = 1, sidebar_type = 'MAIN', updated_at = CURRENT_TIMESTAMP;

-- Move existing leaves into their canonical DB groups. Prefix matching mirrors
-- the old runtime maps while excluding the newly-created group rows themselves.
UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'master.group.product'
JOIN sys_menu current_parent ON current_parent.id = child.parent_id
SET child.parent_id = parent.id, child.sidebar_type = 'MAIN', child.updated_at = CURRENT_TIMESTAMP
WHERE current_parent.menu_code IN ('grp.master', 'master.group.product')
  AND child.menu_code <> parent.menu_code AND (
  child.menu_code LIKE 'master.product%' OR child.menu_code LIKE 'master.extra%'
);

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'master.group.inventory'
JOIN sys_menu current_parent ON current_parent.id = child.parent_id
SET child.parent_id = parent.id, child.sidebar_type = 'MAIN', child.updated_at = CURRENT_TIMESTAMP
WHERE current_parent.menu_code IN ('grp.master', 'master.group.inventory')
  AND child.menu_code <> parent.menu_code AND (
  child.menu_code LIKE 'master.uom%' OR child.menu_code LIKE 'master.operational_division%'
  OR child.menu_code LIKE 'master.item_category%' OR child.menu_code LIKE 'master.material%'
  OR child.menu_code LIKE 'master.item%' OR child.menu_code LIKE 'master.component_category%'
  OR child.menu_code LIKE 'master.component%' OR child.menu_code LIKE 'master.vendor%'
);

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'master.group.relation'
JOIN sys_menu current_parent ON current_parent.id = child.parent_id
SET child.parent_id = parent.id, child.sidebar_type = 'MAIN', child.updated_at = CURRENT_TIMESTAMP
WHERE current_parent.menu_code IN ('grp.master', 'master.group.relation')
  AND child.menu_code <> parent.menu_code AND (
  child.menu_code LIKE 'master.product.recipe%' OR child.menu_code LIKE 'master.product_recipe%'
  OR child.menu_code LIKE 'master.component.formula%' OR child.menu_code LIKE 'master.component_formula%'
  OR child.menu_code LIKE 'master.relation%'
);

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'master.group.config'
JOIN sys_menu current_parent ON current_parent.id = child.parent_id
SET child.parent_id = parent.id, child.sidebar_type = 'MAIN', child.updated_at = CURRENT_TIMESTAMP
WHERE current_parent.menu_code IN ('grp.master', 'master.group.config')
  AND child.menu_code <> parent.menu_code
  AND (child.menu_code LIKE 'master.variable.cost.default%' OR child.menu_code LIKE 'master.variable_cost_default%');

-- Materialize every leaf synthesized by the old renderer. Existing page_id and
-- permissions are retained; absent rows are inserted only when their page exists.
INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, seed.url, page.id, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'product.monitoring.availability' menu_code, 'Ketersediaan Produk' menu_label, 'ri-bar-chart-grouped-line' icon, '/product/availability' url, 'product.availability' page_code, 1 sort_order, 'product.monitoring.stock' parent_code
  UNION ALL SELECT 'production.component.daily.recon', 'Daily Recon Component', 'ri-check-double-line', '/production/component-daily-recon', 'production.component.daily.recon.index', 2, 'production.component.group.transaction'
  UNION ALL SELECT 'production.component.reconcile', 'Reconcile Base/Prepare', 'ri-scales-3-line', '/production/component-reconcile', 'production.component.daily.index', 3, 'production.component.group.transaction'
  UNION ALL SELECT 'production.component.lot', 'Lot Component', 'ri-stack-line', '/production/component-lots', 'production.component.lots', 4, 'production.component.group.transaction'
  UNION ALL SELECT 'production.component.opening.monthly', 'Opening Bulanan', 'ri-archive-line', '/production/component-opening-monthly', 'production.component.opname.monthly', 5, 'production.component.group.transaction'
  UNION ALL SELECT 'production.component.opname.monthly', 'Opname Component', 'ri-file-list-3-line', '/production/component-opname', 'production.component.opname.monthly', 6, 'production.component.group.transaction'
  UNION ALL SELECT 'pos.cashier', 'Kasir POS', 'ri-shopping-bag-3-line', '/pos/cashier', 'pos.cashier.index', 2, 'grp.pos'
  UNION ALL SELECT 'pos.order.monitor', 'Monitor Dapur / Bar', 'ri-restaurant-2-line', '/pos/order-monitor', 'pos.order.monitor.index', 44, 'grp.pos'
  UNION ALL SELECT 'pos.order.paid.index', 'Pesanan Terbayar', 'ri-wallet-3-line', '/pos/orders/paid', 'pos.order.paid.index', 45, 'grp.pos'
  UNION ALL SELECT 'pos.report.sales', 'Laporan Penjualan POS', 'ri-receipt-line', '/pos/reports/sales', 'pos.report.sales.index', 1, 'pos.report.group'
  UNION ALL SELECT 'pos.report.cost_control', 'Cost Control POS', 'ri-funds-box-line', '/pos/reports/cost-control', 'pos.report.cost_control.index', 2, 'pos.report.group'
  UNION ALL SELECT 'pos.report.sales.detail', 'Laporan Penjualan Produk', 'ri-file-list-3-line', '/pos/reports/sales-detail', 'pos.report.sales.detail.index', 3, 'pos.report.group'
  UNION ALL SELECT 'pos.report.sales.extra', 'Laporan Penjualan Extra', 'ri-add-box-line', '/pos/reports/sales-extra', 'pos.report.sales.extra.index', 4, 'pos.report.group'
  UNION ALL SELECT 'pos.report.payment', 'Laporan Pembayaran POS', 'ri-bank-card-line', '/pos/reports/payments', 'pos.report.payment.index', 5, 'pos.report.group'
  UNION ALL SELECT 'pos.report.refund', 'Laporan Refund POS', 'ri-arrow-go-back-line', '/pos/reports/refunds', 'pos.report.refund.index', 6, 'pos.report.group'
  UNION ALL SELECT 'pos.report.void', 'Laporan Void POS', 'ri-close-circle-line', '/pos/reports/voids', 'pos.report.void.index', 7, 'pos.report.group'
) seed
JOIN sys_menu parent ON parent.menu_code = seed.parent_code AND parent.is_active = 1
JOIN sys_page page ON page.page_code = seed.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = IF(VALUES(menu_code) = 'product.monitoring.availability', VALUES(page_id), sys_menu.page_id),
  is_active = 1, sidebar_type = 'MAIN',
  parent_id = IF(VALUES(menu_code) = 'product.monitoring.availability', VALUES(parent_id), sys_menu.parent_id),
  updated_at = CURRENT_TIMESTAMP;

-- Inventory leaves injected by the old renderer use existing stock registry pages.
INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, seed.url, page.id, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'purchase.stock.division.lot' menu_code, 'Lot Bahan Baku' menu_label, 'ri-stack-line' icon, '/inventory/stock/division/lot' url, 'purchase.stock.division.index' page_code, 90 sort_order, 'inventory.stock.group.division' parent_code
  UNION ALL SELECT 'inventory.stock.opname.warehouse.monthly', 'Opname Gudang', 'ri-file-list-3-line', '/inventory/stock/opname/warehouse/monthly', 'inventory.stock.opname.warehouse.monthly', 91, 'inventory.stock.group.warehouse'
  UNION ALL SELECT 'inventory.stock.opname.division.monthly', 'Opname Bahan Baku', 'ri-file-list-3-line', '/inventory/stock/opname/division/monthly', 'inventory.stock.opname.division.monthly', 91, 'inventory.stock.group.division'
  UNION ALL SELECT 'inventory.stock.opening.division.generated', 'Stok Awal Bahan Baku', 'ri-archive-drawer-line', '/inventory/stock/stok-awal/division', 'inventory.stock.opening.division.generated', 92, 'inventory.stock.group.division'
) seed
JOIN sys_menu parent ON parent.menu_code = seed.parent_code
JOIN sys_page page ON page.page_code = seed.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  is_active = 1, sidebar_type = 'MAIN',
  updated_at = CURRENT_TIMESTAMP;

-- Warehouse opening uses the established warehouse stock permission page.
INSERT INTO sys_menu
  (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'inventory.stock.opening.warehouse.generated', 'Stok Awal Gudang', 'ri-archive-drawer-line',
       '/inventory/stock/stok-awal/warehouse', page.id, 92, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page page ON page.page_code = 'purchase.stock.warehouse.index'
WHERE parent.menu_code = 'inventory.stock.group.warehouse'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  is_active = 1, sidebar_type = 'MAIN',
  updated_at = CURRENT_TIMESTAMP;

-- Exact staging icon findings (IDs are documented in the predicate as a guard;
-- menu_code remains canonical so the patch is portable and repeat-safe).
UPDATE sys_menu SET icon = 'ri-file-list-3-line', updated_at = CURRENT_TIMESTAMP
WHERE (id = 337 OR menu_code = 'inventory.stock.opname.warehouse.monthly') AND menu_code = 'inventory.stock.opname.warehouse.monthly';
UPDATE sys_menu SET icon = 'ri-file-list-3-line', updated_at = CURRENT_TIMESTAMP
WHERE (id = 339 OR menu_code = 'inventory.stock.opname.division.monthly') AND menu_code = 'inventory.stock.opname.division.monthly';
UPDATE sys_menu SET icon = 'ri-check-double-line', updated_at = CURRENT_TIMESTAMP
WHERE (id = 335 OR menu_code = 'production.component.daily.recon') AND menu_code = 'production.component.daily.recon';
UPDATE sys_menu SET icon = 'ri-archive-line', updated_at = CURRENT_TIMESTAMP
WHERE (id = 365 OR menu_code = 'production.component.opening.monthly') AND menu_code = 'production.component.opening.monthly';
UPDATE sys_menu SET icon = 'ri-file-list-3-line', updated_at = CURRENT_TIMESTAMP
WHERE (id = 341 OR menu_code = 'production.component.opname.monthly') AND menu_code = 'production.component.opname.monthly';

UPDATE sys_menu SET menu_label = 'PO & SR', updated_at = CURRENT_TIMESTAMP WHERE menu_code = 'grp.purchase';
UPDATE sys_menu SET menu_label = 'Inventory', icon = 'ri-archive-stack-line', updated_at = CURRENT_TIMESTAMP WHERE menu_code = 'grp.inventory';

-- Online Food already has dedicated Orders and Settings leaves. Keep its
-- parent as a pure toggle so the renderer never hides a second action URL.
UPDATE sys_menu parent
JOIN sys_menu child ON child.parent_id = parent.id AND child.is_active = 1
SET parent.url = NULL, parent.page_id = NULL, parent.updated_at = CURRENT_TIMESTAMP
WHERE parent.menu_code = 'pos.online_food';

-- Resolve the known URL alias (ids 293/513) without losing favorites. Both menu
-- rows use the same page registry, so role permissions remain attached to it.
UPDATE sys_sidebar_favorite canonical_favorite
JOIN sys_menu canonical_menu ON canonical_menu.id = canonical_favorite.menu_id AND canonical_menu.menu_code = 'pos.report.sales'
JOIN sys_sidebar_favorite alias_favorite ON alias_favorite.user_id = canonical_favorite.user_id
JOIN sys_menu alias_menu ON alias_menu.id = alias_favorite.menu_id AND alias_menu.menu_code = 'finance.sales_margin.pos'
SET canonical_favorite.sort_order = LEAST(canonical_favorite.sort_order, alias_favorite.sort_order);

INSERT INTO sys_sidebar_favorite (user_id, menu_id, sort_order, created_at)
SELECT favorite.user_id, canonical.id, favorite.sort_order, favorite.created_at
FROM sys_sidebar_favorite favorite
JOIN sys_menu alias_menu ON alias_menu.id = favorite.menu_id AND alias_menu.menu_code = 'finance.sales_margin.pos'
JOIN sys_menu canonical ON canonical.menu_code = 'pos.report.sales'
LEFT JOIN sys_sidebar_favorite existing ON existing.user_id = favorite.user_id AND existing.menu_id = canonical.id
WHERE existing.id IS NULL;

DELETE favorite FROM sys_sidebar_favorite favorite
JOIN sys_menu alias_menu ON alias_menu.id = favorite.menu_id
WHERE alias_menu.menu_code = 'finance.sales_margin.pos';

UPDATE sys_menu SET is_active = 0, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'finance.sales_margin.pos'
  AND LOWER(TRIM(BOTH '/' FROM TRIM(COALESCE(url, '')))) = 'pos/reports/sales';

-- Canonicalize every active sibling order, resolving all nine known collision
-- pairs (2/539, 447/517, 259/531, 308/535, 379/541, 387/521,
-- 293/525, 465/529, 509/511) and preventing collisions in inserted groups.
DROP TEMPORARY TABLE IF EXISTS tmp_a3_menu_order;
CREATE TEMPORARY TABLE tmp_a3_menu_order AS
SELECT menu.id,
       1 + (
         SELECT COUNT(*)
         FROM sys_menu sibling
         WHERE sibling.is_active = 1
           AND sibling.sidebar_type = menu.sidebar_type
           AND COALESCE(sibling.parent_id, 0) = COALESCE(menu.parent_id, 0)
           AND (sibling.sort_order < menu.sort_order
                OR (sibling.sort_order = menu.sort_order AND sibling.id < menu.id))
       ) AS canonical_sort_order
FROM sys_menu menu
WHERE menu.is_active = 1;

UPDATE sys_menu menu
JOIN tmp_a3_menu_order canonical ON canonical.id = menu.id
SET menu.sort_order = canonical.canonical_sort_order,
    menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.sort_order <> canonical.canonical_sort_order;
DROP TEMPORARY TABLE IF EXISTS tmp_a3_menu_order;

CALL sp_a3_navigation_registry_assert_20260904a('POST');
COMMIT;
DROP PROCEDURE IF EXISTS sp_a3_navigation_registry_assert_20260904a;

-- Read-only post-apply summary. Expected counts are all zero.
SELECT 'missing_icon' issue, COUNT(*) total
FROM sys_menu WHERE is_active = 1 AND TRIM(COALESCE(icon, '')) = ''
UNION ALL
SELECT 'duplicate_code', COUNT(*) FROM (
  SELECT LOWER(TRIM(menu_code)) code FROM sys_menu WHERE is_active = 1
  GROUP BY LOWER(TRIM(menu_code)) HAVING COUNT(*) > 1
) duplicate_codes
UNION ALL
SELECT 'duplicate_url', COUNT(*) FROM (
  SELECT LOWER(TRIM(BOTH '/' FROM TRIM(url))) canonical_url
  FROM sys_menu
  WHERE is_active = 1 AND TRIM(COALESCE(url, '')) NOT IN ('', '#', 'javascript:void(0)', 'javascript:void(0);')
  GROUP BY LOWER(TRIM(BOTH '/' FROM TRIM(url))) HAVING COUNT(*) > 1
) duplicate_urls
UNION ALL
SELECT 'sort_collision', COUNT(*) FROM (
  SELECT sidebar_type, COALESCE(parent_id, 0), sort_order
  FROM sys_menu WHERE is_active = 1
  GROUP BY sidebar_type, COALESCE(parent_id, 0), sort_order HAVING COUNT(*) > 1
) collisions;
