-- ============================================================
-- 2026-06-14b  Registrasi halaman Opening Bulanan Component
--              ke sys_page dan sys_menu
--
-- Halaman ini menampilkan inv_component_monthly_opening
-- (carry-forward opening dari generate opname).
--
-- Permission: reuse production.component.opname.monthly
-- (halaman opening bulanan tidak butuh page_code terpisah
--  karena visibility-nya sama dengan opname).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- ============================================================

-- ── COMPONENT OPENING MONTHLY ────────────────────────────────
INSERT INTO `sys_page` (`page_code`, `page_name`, `module`, `is_active`)
VALUES ('production.component.opening.monthly', 'Opening Stok Bulanan Component', 'PRODUKSI', 1)
ON DUPLICATE KEY UPDATE `page_name` = VALUES(`page_name`), `is_active` = 1;

SET @cmp_opening_page_id := (SELECT `id` FROM `sys_page` WHERE `page_code` = 'production.component.opening.monthly' LIMIT 1);
SET @cmp_parent_id       := (SELECT `parent_id` FROM `sys_menu` WHERE `url` = 'production/component-openings' LIMIT 1);
SET @cmp_opname_sort     := IFNULL((SELECT `sort_order` FROM `sys_menu` WHERE `url` = 'production/component-opname' AND `parent_id` = @cmp_parent_id LIMIT 1), 1000);

INSERT INTO `sys_menu` (`menu_code`, `menu_label`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`)
VALUES (
    'production.component.opening.monthly',
    'Opening Bulanan',
    'production/component-opening-monthly',
    @cmp_parent_id,
    @cmp_opname_sort - 1,
    1,
    @cmp_opening_page_id
)
ON DUPLICATE KEY UPDATE
    `menu_label` = VALUES(`menu_label`),
    `url`        = VALUES(`url`),
    `parent_id`  = VALUES(`parent_id`),
    `is_active`  = 1,
    `page_id`    = VALUES(`page_id`);

-- ── VERIFIKASI ───────────────────────────────────────────────
/*
SELECT m.menu_code, m.menu_label, m.url, m.sort_order, p.page_code
FROM   sys_menu m
LEFT   JOIN sys_page p ON p.id = m.page_id
WHERE  m.menu_code IN (
    'production.component.opname.monthly',
    'production.component.opening.monthly'
)
ORDER  BY m.sort_order;
*/
