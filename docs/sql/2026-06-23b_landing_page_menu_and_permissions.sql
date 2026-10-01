-- ============================================================
-- 2026-06-23b  Daftarkan halaman Pengaturan Landing Page
--              ke sys_page, sys_menu (sidebar), dan
--              auth_role_permission (SUPERADMIN / CEO / MGR / ADMIN)
--
-- Prasyarat: 2026-06-23a sudah dijalankan (tabel lp_* tersedia).
-- Idempotent: ON DUPLICATE KEY UPDATE / INSERT IGNORE.
-- ============================================================

-- ── LANGKAH 1: sys_page ──────────────────────────────────────────────
INSERT INTO `sys_page`
    (`page_code`, `page_name`, `module`, `matrix_group`, `is_active`)
VALUES
    ('landing_page.index', 'Pengaturan Landing Page', 'WEBSITE', 'WEBSITE', 1)
ON DUPLICATE KEY UPDATE
    `page_name`    = VALUES(`page_name`),
    `module`       = VALUES(`module`),
    `matrix_group` = VALUES(`matrix_group`),
    `is_active`    = 1;

SET @lp_page_id := (
    SELECT `id` FROM `sys_page`
    WHERE `page_code` = 'landing_page.index'
    LIMIT 1
);

-- ── LANGKAH 2: sys_menu ──────────────────────────────────────────────
-- Ditempatkan di bawah grp.system (id = 12), sesudah Perlindungan DB (sort=4)
INSERT INTO `sys_menu`
    (`menu_code`, `menu_label`, `icon`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`, `sidebar_type`)
VALUES
    ('website.landing_page', 'Pengaturan Landing Page', 'ri-layout-masonry-line',
     '/landing-page', 12, 5, 1, @lp_page_id, 'MAIN')
ON DUPLICATE KEY UPDATE
    `menu_label`  = VALUES(`menu_label`),
    `icon`        = VALUES(`icon`),
    `url`         = VALUES(`url`),
    `parent_id`   = VALUES(`parent_id`),
    `is_active`   = 1,
    `page_id`     = VALUES(`page_id`);

-- ── LANGKAH 3: auth_role_permission ──────────────────────────────────
-- Role yang diberi akses:
--   1 = SUPERADMIN  → full (view + create + edit + delete)
--   2 = CEO         → full
--   3 = MGR         → full
--   4 = ADMIN       → full
-- Role lain (KASIR, BARISTA, CHEF, dst.) tidak perlu entry → akses ditolak
-- can_export = 0 untuk semua (tidak ada fitur export di modul ini)

INSERT INTO `auth_role_permission`
    (`role_id`, `page_id`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export`)
VALUES
    (1, @lp_page_id, 1, 1, 1, 1, 0),   -- SUPERADMIN
    (2, @lp_page_id, 1, 1, 1, 1, 0),   -- CEO
    (3, @lp_page_id, 1, 1, 1, 1, 0),   -- MGR
    (4, @lp_page_id, 1, 1, 1, 1, 0)    -- ADMIN
ON DUPLICATE KEY UPDATE
    `can_view`   = VALUES(`can_view`),
    `can_create` = VALUES(`can_create`),
    `can_edit`   = VALUES(`can_edit`),
    `can_delete` = VALUES(`can_delete`),
    `can_export` = VALUES(`can_export`);

-- ── VERIFIKASI ────────────────────────────────────────────────────────
/*
SELECT m.id, m.menu_code, m.menu_label, m.url, m.sort_order,
       p.page_code, p.module
FROM   sys_menu m
JOIN   sys_page p ON p.id = m.page_id
WHERE  m.parent_id = 12
ORDER  BY m.sort_order;

SELECT rp.role_id, r.role_code, r.role_name,
       rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
FROM   auth_role_permission rp
JOIN   auth_role r ON r.id = rp.role_id
WHERE  rp.page_id = (SELECT id FROM sys_page WHERE page_code = 'landing_page.index' LIMIT 1)
ORDER  BY rp.role_id;
*/
