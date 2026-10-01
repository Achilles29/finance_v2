-- A3 information architecture: task-oriented Finance sidebar.
--
-- This migration changes only sys_menu labels, parent_id, and sort_order.
-- It never changes routes, page registry rows, permission rows, business
-- transactions, or menu activation. It is repeat-safe and can be applied by
-- the managed migration runner on a clean install or an existing customer.
SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_a3_sidebar_task_layout_assert_20260906i$$
CREATE PROCEDURE sp_a3_sidebar_task_layout_assert_20260906i(IN p_phase VARCHAR(8))
BEGIN
  DECLARE v_issues INT DEFAULT 0;

  IF p_phase = 'PRE' THEN
    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'dashboard' menu_code
      UNION ALL SELECT 'grp.pos'
      UNION ALL SELECT 'grp.loyalty'
      UNION ALL SELECT 'grp.purchase'
      UNION ALL SELECT 'grp.inventory'
      UNION ALL SELECT 'produk'
      UNION ALL SELECT 'grp.finance'
      UNION ALL SELECT 'grp.hr'
      UNION ALL SELECT 'grp.payroll'
      UNION ALL SELECT 'grp.asset'
      UNION ALL SELECT 'grp.master'
      UNION ALL SELECT 'grp.system'
      UNION ALL SELECT 'grp.menu_book'
      UNION ALL SELECT 'grp.wa'
      UNION ALL SELECT 'grp.telegram'
      UNION ALL SELECT 'pos.cashier'
      UNION ALL SELECT 'pos.self_order'
      UNION ALL SELECT 'pos.reservation'
      UNION ALL SELECT 'pos.report.group'
    ) required_menu
    LEFT JOIN sys_menu menu
      ON menu.menu_code = required_menu.menu_code
     AND menu.is_active = 1
     AND menu.sidebar_type = 'MAIN'
    WHERE menu.id IS NULL;

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3 sidebar IA preflight failed: required active menu is missing';
    END IF;
  ELSEIF p_phase = 'POST' THEN
    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT 'dashboard' child_code, NULL parent_code
      UNION ALL SELECT 'grp.pos', NULL
      UNION ALL SELECT 'grp.loyalty', NULL
      UNION ALL SELECT 'grp.purchase', NULL
      UNION ALL SELECT 'grp.inventory', NULL
      UNION ALL SELECT 'produk', NULL
      UNION ALL SELECT 'grp.finance', NULL
      UNION ALL SELECT 'grp.people', NULL
      UNION ALL SELECT 'grp.asset', NULL
      UNION ALL SELECT 'grp.master', NULL
      UNION ALL SELECT 'grp.system', NULL
      UNION ALL SELECT 'grp.integration', NULL
      UNION ALL SELECT 'grp.hr', 'grp.people'
      UNION ALL SELECT 'grp.payroll', 'grp.people'
      UNION ALL SELECT 'grp.menu_book', 'produk'
      UNION ALL SELECT 'grp.wa', 'grp.integration'
      UNION ALL SELECT 'grp.telegram', 'grp.integration'
      UNION ALL SELECT 'pos.group.operation', 'grp.pos'
      UNION ALL SELECT 'pos.group.channel', 'grp.pos'
      UNION ALL SELECT 'pos.group.settings', 'grp.pos'
      UNION ALL SELECT 'pos.cashier', 'pos.group.operation'
      UNION ALL SELECT 'pos.order.paid.index', 'pos.group.operation'
      UNION ALL SELECT 'pos.reservation', 'pos.group.operation'
      UNION ALL SELECT 'pos.deposit', 'pos.group.operation'
      UNION ALL SELECT 'pos.order.draft', 'pos.group.operation'
      UNION ALL SELECT 'pos.order.monitor', 'pos.group.operation'
      UNION ALL SELECT 'pos.self_order', 'pos.group.channel'
      UNION ALL SELECT 'pos.online_food', 'pos.group.channel'
      UNION ALL SELECT 'pos.stock.live', 'pos.group.channel'
      UNION ALL SELECT 'pos.availability.queue', 'pos.group.channel'
      UNION ALL SELECT 'pos.outlet-terminal', 'pos.group.settings'
      UNION ALL SELECT 'pos.payment-method', 'pos.group.settings'
      UNION ALL SELECT 'pos.sales-channel', 'pos.group.settings'
      UNION ALL SELECT 'pos.printer', 'pos.group.settings'
      UNION ALL SELECT 'pos.daily_recon_settings', 'pos.group.settings'
      UNION ALL SELECT 'pos.customer_review', 'pos.group.settings'
      UNION ALL SELECT 'pos.report.group', 'grp.pos'
      UNION ALL SELECT 'pos.stock.commit.audit', 'pos.report.group'
    ) expected
    LEFT JOIN sys_menu child
      ON child.menu_code = expected.child_code
     AND child.is_active = 1
     AND child.sidebar_type = 'MAIN'
    LEFT JOIN sys_menu parent ON parent.id = child.parent_id
    WHERE child.id IS NULL
       OR ((expected.parent_code IS NULL AND child.parent_id IS NOT NULL)
           OR (expected.parent_code IS NOT NULL AND parent.menu_code <> expected.parent_code));

    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3 sidebar IA postflight failed: task hierarchy mismatch';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM sys_menu
    WHERE menu_code IN ('grp.people', 'grp.integration', 'pos.group.operation', 'pos.group.channel', 'pos.group.settings')
      AND (TRIM(COALESCE(url, '')) <> '' OR page_id IS NOT NULL OR is_active <> 1 OR sidebar_type <> 'MAIN');
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3 sidebar IA postflight failed: generated group is not a pure active group';
    END IF;

    SELECT COUNT(*) INTO v_issues
    FROM (
      SELECT sidebar_type, COALESCE(parent_id, 0) parent_key, sort_order
      FROM sys_menu
      WHERE is_active = 1
      GROUP BY sidebar_type, COALESCE(parent_id, 0), sort_order
      HAVING COUNT(*) > 1
    ) sort_collision;
    IF v_issues > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'A3 sidebar IA postflight failed: active sibling sort collision';
    END IF;

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
        SET MESSAGE_TEXT = 'A3 sidebar IA postflight failed: active URL without active page';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A3 sidebar IA assertion failed: unknown phase';
  END IF;
END$$

DELIMITER ;

CALL sp_a3_sidebar_task_layout_assert_20260906i('PRE');
START TRANSACTION;

-- New pure groups preserve all existing leaves and permission bindings.
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES
  ('grp.people', 'SDM & Payroll', 'ri-team-line', NULL, NULL, 8, 1, 'MAIN', NULL),
  ('grp.integration', 'Integrasi & Notifikasi', 'ri-notification-3-line', NULL, NULL, 12, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL, page_id = NULL,
  sort_order = VALUES(sort_order), is_active = 1, sidebar_type = 'MAIN', parent_id = NULL,
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, NULL, NULL, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'pos.group.operation' menu_code, 'Operasional Kasir' menu_label, 'ri-shopping-bag-3-line' icon, 1 sort_order
  UNION ALL SELECT 'pos.group.channel', 'Channel & Antrean Pesanan', 'ri-route-line', 2
  UNION ALL SELECT 'pos.group.settings', 'Pengaturan POS & Printer', 'ri-settings-3-line', 3
) seed
JOIN sys_menu parent ON parent.menu_code = 'grp.pos' AND parent.is_active = 1
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL, page_id = NULL,
  sort_order = VALUES(sort_order), is_active = 1, sidebar_type = 'MAIN',
  parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

-- Top-level task order. Existing menu identifiers/routes/pages stay intact.
UPDATE sys_menu menu
JOIN (
  SELECT 'dashboard' menu_code, 'Dashboard' menu_label, 'ri-home-smile-line' icon, 1 sort_order
  UNION ALL SELECT 'grp.pos', 'Penjualan & Pesanan', 'ri-store-2-line', 2
  UNION ALL SELECT 'grp.loyalty', 'Pelanggan, Member & Promo', 'ri-user-star-line', 3
  UNION ALL SELECT 'grp.purchase', 'Pembelian & Permintaan', 'ri-shopping-bag-3-line', 4
  UNION ALL SELECT 'grp.inventory', 'Stok & Persediaan', 'ri-archive-stack-line', 5
  UNION ALL SELECT 'produk', 'Produk & Produksi', 'ri-restaurant-line', 6
  UNION ALL SELECT 'grp.finance', 'Keuangan', 'ri-wallet-3-line', 7
  UNION ALL SELECT 'grp.people', 'SDM & Payroll', 'ri-team-line', 8
  UNION ALL SELECT 'grp.asset', 'Aset', 'ri-archive-2-line', 9
  UNION ALL SELECT 'grp.master', 'Master & Konfigurasi', 'ri-database-2-line', 10
  UNION ALL SELECT 'grp.system', 'Administrasi & Audit', 'ri-settings-3-line', 11
  UNION ALL SELECT 'grp.integration', 'Integrasi & Notifikasi', 'ri-notification-3-line', 12
) layout ON layout.menu_code = menu.menu_code
SET menu.menu_label = layout.menu_label,
    menu.icon = layout.icon,
    menu.parent_id = NULL,
    menu.sort_order = layout.sort_order,
    menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.is_active = 1 AND menu.sidebar_type = 'MAIN';

-- People, presentation, and integration become coherent work areas.
UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'grp.people'
SET child.parent_id = parent.id,
    child.sort_order = CASE child.menu_code WHEN 'grp.hr' THEN 1 WHEN 'grp.payroll' THEN 2 ELSE child.sort_order END,
    child.menu_label = CASE child.menu_code
      WHEN 'grp.hr' THEN 'SDM & Kehadiran'
      WHEN 'grp.payroll' THEN 'Payroll & Benefit'
      ELSE child.menu_label END,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('grp.hr', 'grp.payroll') AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'produk'
SET child.parent_id = parent.id,
    child.menu_label = 'Katalog & Menu Book',
    child.sort_order = 4,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code = 'grp.menu_book' AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'grp.integration'
SET child.parent_id = parent.id,
    child.sort_order = CASE child.menu_code WHEN 'grp.wa' THEN 1 WHEN 'grp.telegram' THEN 2 ELSE child.sort_order END,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('grp.wa', 'grp.telegram') AND child.is_active = 1;

-- POS actions are no longer scattered across sidebar roots.
UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'pos.group.operation'
SET child.parent_id = parent.id,
    child.sort_order = CASE child.menu_code
      WHEN 'pos.cashier' THEN 1
      WHEN 'pos.order.paid.index' THEN 2
      WHEN 'pos.reservation' THEN 3
      WHEN 'pos.deposit' THEN 4
      WHEN 'pos.order.draft' THEN 5
      WHEN 'pos.order.monitor' THEN 6
      ELSE child.sort_order END,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('pos.cashier', 'pos.order.paid.index', 'pos.reservation', 'pos.deposit', 'pos.order.draft', 'pos.order.monitor')
  AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'pos.group.channel'
SET child.parent_id = parent.id,
    child.sort_order = CASE child.menu_code
      WHEN 'pos.self_order' THEN 1
      WHEN 'pos.online_food' THEN 2
      WHEN 'pos.stock.live' THEN 3
      WHEN 'pos.availability.queue' THEN 4
      ELSE child.sort_order END,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('pos.self_order', 'pos.online_food', 'pos.stock.live', 'pos.availability.queue')
  AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'pos.group.settings'
SET child.parent_id = parent.id,
    child.sort_order = CASE child.menu_code
      WHEN 'pos.outlet-terminal' THEN 1
      WHEN 'pos.payment-method' THEN 2
      WHEN 'pos.sales-channel' THEN 3
      WHEN 'pos.printer' THEN 4
      WHEN 'pos.daily_recon_settings' THEN 5
      WHEN 'pos.customer_review' THEN 6
      ELSE child.sort_order END,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('pos.outlet-terminal', 'pos.payment-method', 'pos.sales-channel', 'pos.printer', 'pos.daily_recon_settings', 'pos.customer_review')
  AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
SET child.parent_id = parent.id,
    child.menu_label = 'Laporan & Audit POS',
    child.sort_order = 4,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code = 'pos.report.group' AND child.is_active = 1;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'pos.report.group'
SET child.parent_id = parent.id,
    child.sort_order = 13,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code = 'pos.stock.commit.audit' AND child.is_active = 1;

-- Keep purchase and stock labels readable at a glance.
UPDATE sys_menu menu
SET menu.sort_order = CASE menu.menu_code
      WHEN 'purchase.order' THEN 1
      WHEN 'procurement.store-request' THEN 2
      WHEN 'procurement.division' THEN 3
      WHEN 'purchase.receipt' THEN 4
      WHEN 'purchase.material.price_history' THEN 5
      WHEN 'purchase.report' THEN 6
      WHEN 'purchase.order.log' THEN 7
      WHEN 'purchase.reclassify-profile-domain' THEN 8
      WHEN 'purchase.rebuild.impact' THEN 9
      ELSE menu.sort_order END,
    menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.parent_id = (SELECT id FROM sys_menu parent WHERE parent.menu_code = 'grp.purchase')
  AND menu.is_active = 1;

UPDATE sys_menu menu
SET menu.menu_label = 'Stok Bahan Baku', menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.menu_code = 'inventory.stock.group.division' AND menu.is_active = 1;

UPDATE sys_menu menu
SET menu.menu_label = 'Operasional & Laporan Kehadiran', menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.menu_code = 'attend.report' AND menu.is_active = 1;

UPDATE sys_menu menu
SET menu.sort_order = CASE menu.menu_code
      WHEN 'sys.users' THEN 1
      WHEN 'sys.roles' THEN 2
      WHEN 'system.activity_audit' THEN 3
      WHEN 'sys.sidebar.manage' THEN 4
      WHEN 'system.dbtools.settings' THEN 5
      WHEN 'website.landing_page' THEN 6
      ELSE menu.sort_order END,
    menu.updated_at = CURRENT_TIMESTAMP
WHERE menu.parent_id = (SELECT id FROM sys_menu parent WHERE parent.menu_code = 'grp.system')
  AND menu.is_active = 1;

CALL sp_a3_sidebar_task_layout_assert_20260906i('POST');
COMMIT;
DROP PROCEDURE IF EXISTS sp_a3_sidebar_task_layout_assert_20260906i;
