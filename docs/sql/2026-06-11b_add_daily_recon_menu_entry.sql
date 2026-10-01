-- ============================================================
-- 2026-06-11b  Tambah menu Daily Recon Component ke sidebar
-- Dimasukkan ke grup yang sama dengan Opening Base/Prepare,
-- di posisi ke-2 (tepat sesudah Opening).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Jalankan setelah 2026-06-11a (tabel sudah ada).
-- ============================================================

-- ── LANGKAH 1: sys_page ──────────────────────────────────────
INSERT INTO `sys_page` (`page_code`, `page_name`, `module`, `is_active`)
VALUES (
    'production.component.daily.recon.index',
    'Daily Recon Stok Component',
    'PRODUKSI',
    1
)
ON DUPLICATE KEY UPDATE
    `page_name` = VALUES(`page_name`),
    `module`    = VALUES(`module`),
    `is_active` = 1;

-- ── LANGKAH 2: Temukan parent_id & sort_order Opening ────────
SET @cmp_parent_id := (
    SELECT `parent_id` FROM `sys_menu`
    WHERE `url` = 'production/component-openings'
    LIMIT 1
);

SET @opening_sort := IFNULL((
    SELECT `sort_order` FROM `sys_menu`
    WHERE `url` = 'production/component-openings'
    LIMIT 1
), 1);

SET @page_id := (
    SELECT `id` FROM `sys_page`
    WHERE `page_code` = 'production.component.daily.recon.index'
    LIMIT 1
);

-- ── LANGKAH 3: Geser items sesudah Opening (idempotent) ──────
-- Hanya jika Daily Recon belum ada di sys_menu
UPDATE `sys_menu`
SET    `sort_order` = `sort_order` + 1
WHERE  `parent_id` = @cmp_parent_id
  AND  `sort_order` > @opening_sort
  AND  `menu_code` != 'production.component.daily.recon'
  AND  (
    SELECT COUNT(*) FROM (
        SELECT `id` FROM `sys_menu`
        WHERE `menu_code` = 'production.component.daily.recon'
    ) AS _dr
  ) = 0;

-- ── LANGKAH 4: Insert / update menu Daily Recon ──────────────
INSERT INTO `sys_menu`
    (`menu_code`, `menu_label`, `url`, `parent_id`, `sort_order`, `is_active`, `page_id`)
VALUES (
    'production.component.daily.recon',
    'Daily Recon Component',
    'production/component-daily-recon',
    @cmp_parent_id,
    @opening_sort + 1,
    1,
    @page_id
)
ON DUPLICATE KEY UPDATE
    `menu_label` = VALUES(`menu_label`),
    `url`        = VALUES(`url`),
    `parent_id`  = VALUES(`parent_id`),
    `is_active`  = 1,
    `page_id`    = VALUES(`page_id`);

-- ── VERIFIKASI ───────────────────────────────────────────────
/*
SELECT m.id, m.menu_code, m.menu_label, m.url, m.sort_order, m.is_active,
       p.menu_label AS parent_label
FROM   sys_menu m
LEFT   JOIN sys_menu p ON p.id = m.parent_id
WHERE  m.parent_id = @cmp_parent_id
ORDER  BY m.sort_order;
*/
