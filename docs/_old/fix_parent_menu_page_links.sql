-- ============================================================
-- Fix: Lepas page_id dari menu induk (url = '#')
-- dan nonaktifkan sys_page yang hanya berfungsi sebagai
-- header kolapsibel sidebar — bukan halaman nyata.
--
-- Konteks:
--   Item menu induk (url = '#') tidak menavigasi ke mana-mana.
--   Visibilitasnya di sidebar otomatis mengikuti child-nya.
--   Menghubungkan page_id ke menu induk menyebabkan entry
--   tersebut muncul di role matrix dan bisa menyebabkan
--   header hilang dari sidebar saat page dinonaktifkan.
--
-- Aman diulang (idempotent).
-- Jalankan setelah sidebar_roles_improvements.sql.
-- ============================================================

-- ── PREVIEW (jalankan SELECT ini dulu sebelum mengubah data) ─
/*
SELECT
    p.id,
    p.page_code,
    p.page_name,
    p.is_active,
    m.menu_label   AS linked_menu,
    m.url          AS menu_url,
    (
        SELECT COUNT(*) FROM sys_menu mx
        WHERE mx.page_id = p.id
          AND mx.url != '#'
          AND mx.url != ''
    ) AS real_menu_links
FROM sys_page p
JOIN sys_menu m ON m.page_id = p.id
WHERE m.url = '#';
*/

-- ── LANGKAH 1: Lepas page_id dari semua menu induk (url='#') ─
--   Menu induknya TIDAK akan hilang dari sidebar —
--   visibilitasnya dikendalikan oleh sys_menu.is_active
--   dan apakah ada child yang visible untuk user tersebut.
-- ─────────────────────────────────────────────────────────────
UPDATE `sys_menu`
SET    `page_id` = NULL
WHERE  `url` = '#'
  AND  `page_id` IS NOT NULL;

-- ── LANGKAH 2: Nonaktifkan sys_page yang HANYA terhubung ke ──
--   menu induk (url = '#') dan tidak punya menu nyata lain.
--   Kondisi: tidak ada sys_menu lain dengan url berbeda '#'/''.
-- ─────────────────────────────────────────────────────────────
UPDATE `sys_page` p
SET    p.`is_active` = 0
WHERE  p.`is_active` = 1
  AND  NOT EXISTS (
           -- Masih ada menu aktif yang bukan induk yang mengarah ke page ini
           SELECT 1
           FROM   `sys_menu` m
           WHERE  m.`page_id` = p.`id`
             AND  m.`is_active` = 1
             AND  m.`url` NOT IN ('#', '')
       )
  AND  NOT EXISTS (
           -- Masih ada menu nonaktif (url nyata) yang mungkin akan diaktifkan lagi
           SELECT 1
           FROM   `sys_menu` m
           WHERE  m.`page_id` = p.`id`
             AND  m.`url` NOT IN ('#', '')
       )
  AND  NOT EXISTS (
           -- Jangan sentuh jika sudah punya role permission (ada yang sengaja mengatur)
           SELECT 1
           FROM   `auth_role_permission` rp
           WHERE  rp.`page_id` = p.`id`
       );

-- ── LANGKAH 3 (OPSIONAL): Bersihkan permission untuk page ────
--   yang sudah nonaktif dan tidak terhubung ke menu apapun.
--   Hapus komentar dan jalankan HANYA jika yakin.
-- ─────────────────────────────────────────────────────────────
/*
DELETE rp
FROM   `auth_role_permission` rp
JOIN   `sys_page` p ON p.`id` = rp.`page_id`
WHERE  p.`is_active` = 0
  AND  NOT EXISTS (
           SELECT 1 FROM `sys_menu` m
           WHERE m.`page_id` = p.`id`
       );
*/

-- ── CEK HASIL ────────────────────────────────────────────────
-- Jalankan setelah eksekusi untuk verifikasi:
/*
-- Menu induk yang tidak lagi punya page_id:
SELECT id, menu_label, menu_code, url, page_id
FROM   sys_menu
WHERE  url = '#'
ORDER BY menu_label;

-- Page yang menjadi nonaktif:
SELECT page_code, page_name, is_active
FROM   sys_page
WHERE  is_active = 0
ORDER BY page_code;
*/
