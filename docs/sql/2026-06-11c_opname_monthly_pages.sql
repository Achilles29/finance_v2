-- ============================================================
-- 2026-06-11c  Registrasi halaman Stok Opname Bulanan
--              Gudang, Divisi, dan Component
--
-- Halaman ini menampilkan snapshot inv_*_monthly_opname
-- (hasil generate dari masing-masing halaman stok).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- ============================================================

-- ── WAREHOUSE OPNAME ────────────────────────────────────────
INSERT INTO `sys_page` (`page_code`, `page_name`, `module`, `is_active`)
VALUES ('inventory.stock.opname.warehouse.monthly', 'Stok Opname Bulanan Gudang', 'INVENTORY', 1)
ON DUPLICATE KEY UPDATE `page_name` = VALUES(`page_name`), `is_active` = 1;

SET @wh_page_id := (SELECT `id` FROM `sys_page` WHERE `page_code` = 'inventory.stock.opname.warehouse.monthly' LIMIT 1);
SET @wh_parent_id := (SELECT `parent_id` FROM `sys_menu` WHERE `url` = 'inventory/stock/warehouse' LIMIT 1);
SET @wh_lot_sort := IFNULL((SELECT `sort_order` FROM `sys_menu` WHERE `url` = 'inventory/stock/warehouse/lot' AND `parent_id` = @wh_parent_id LIMIT 1), 900);

INSERT INTO `sys_menu` (`menu_code`, `menu_label`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`)
VALUES ('inventory.stock.opname.warehouse.monthly', 'Opname Gudang', 'inventory/stock/opname/warehouse/monthly', @wh_parent_id, @wh_lot_sort + 1, 1, @wh_page_id)
ON DUPLICATE KEY UPDATE `menu_label` = VALUES(`menu_label`), `url` = VALUES(`url`), `parent_id` = VALUES(`parent_id`), `is_active` = 1, `page_id` = VALUES(`page_id`);

-- ── DIVISION OPNAME ─────────────────────────────────────────
INSERT INTO `sys_page` (`page_code`, `page_name`, `module`, `is_active`)
VALUES ('inventory.stock.opname.division.monthly', 'Stok Opname Bulanan Divisi', 'INVENTORY', 1)
ON DUPLICATE KEY UPDATE `page_name` = VALUES(`page_name`), `is_active` = 1;

SET @div_page_id := (SELECT `id` FROM `sys_page` WHERE `page_code` = 'inventory.stock.opname.division.monthly' LIMIT 1);
SET @div_parent_id := (SELECT `parent_id` FROM `sys_menu` WHERE `url` = 'inventory/stock/division' LIMIT 1);
SET @div_lot_sort := IFNULL((SELECT `sort_order` FROM `sys_menu` WHERE `url` = 'inventory/stock/division/lot' AND `parent_id` = @div_parent_id LIMIT 1), 900);

INSERT INTO `sys_menu` (`menu_code`, `menu_label`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`)
VALUES ('inventory.stock.opname.division.monthly', 'Opname Divisi', 'inventory/stock/opname/division/monthly', @div_parent_id, @div_lot_sort + 1, 1, @div_page_id)
ON DUPLICATE KEY UPDATE `menu_label` = VALUES(`menu_label`), `url` = VALUES(`url`), `parent_id` = VALUES(`parent_id`), `is_active` = 1, `page_id` = VALUES(`page_id`);

-- ── COMPONENT OPNAME ────────────────────────────────────────
INSERT INTO `sys_page` (`page_code`, `page_name`, `module`, `is_active`)
VALUES ('production.component.opname.monthly', 'Stok Opname Bulanan Component', 'PRODUKSI', 1)
ON DUPLICATE KEY UPDATE `page_name` = VALUES(`page_name`), `is_active` = 1;

SET @cmp_opname_page_id := (SELECT `id` FROM `sys_page` WHERE `page_code` = 'production.component.opname.monthly' LIMIT 1);
SET @cmp_parent_id := (SELECT `parent_id` FROM `sys_menu` WHERE `url` = 'production/component-openings' LIMIT 1);
SET @cmp_reconcile_sort := IFNULL((SELECT `sort_order` FROM `sys_menu` WHERE `url` = 'production/component-reconcile' AND `parent_id` = @cmp_parent_id LIMIT 1), 999);

INSERT INTO `sys_menu` (`menu_code`, `menu_label`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`)
VALUES ('production.component.opname.monthly', 'Opname Component', 'production/component-opname', @cmp_parent_id, @cmp_reconcile_sort + 1, 1, @cmp_opname_page_id)
ON DUPLICATE KEY UPDATE `menu_label` = VALUES(`menu_label`), `url` = VALUES(`url`), `parent_id` = VALUES(`parent_id`), `is_active` = 1, `page_id` = VALUES(`page_id`);

-- ── VERIFIKASI ───────────────────────────────────────────────
/*
SELECT m.menu_code, m.menu_label, m.url, m.sort_order, p.page_code
FROM   sys_menu m
LEFT   JOIN sys_page p ON p.id = m.page_id
WHERE  m.menu_code IN (
    'inventory.stock.opname.warehouse.monthly',
    'inventory.stock.opname.division.monthly',
    'production.component.opname.monthly'
);
*/
