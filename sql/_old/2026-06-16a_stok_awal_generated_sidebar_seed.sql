SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16a_stok_awal_generated_sidebar_seed.sql
-- Tujuan : Mendaftarkan halaman "Stok Awal Bahan Baku" ke
--          sys_page, sys_menu (di bawah grup purchase.stock.division),
--          dan menyalin hak akses dari purchase.stock.division.
--
-- URL    : inventory/stock/stok-awal/division
-- Halaman: stock_opening_division_generated_index.php
-- Sumber : AUTO_REBUILD rows di inv_division_stock_opening_snapshot
-- ============================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. Daftarkan page
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
    'inventory.stock.opening.division.generated',
    'Stok Awal Bahan Baku',
    'INVENTORY',
    'Stok awal bahan baku divisi hasil generate otomatis (carry-forward closing opname bulan sebelumnya). Source: inv_division_stock_opening_snapshot WHERE source_type = AUTO_REBUILD.',
    1
)
ON DUPLICATE KEY UPDATE
    page_name   = VALUES(page_name),
    module      = VALUES(module),
    description = VALUES(description),
    is_active   = VALUES(is_active),
    updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 2. Pasang menu di bawah grup Stok Divisi
--    sort_order 56 = setelah "mutasi Bahan Baku" (55) dan
--    sebelum "Opening Manual" (57)
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
    'inventory.stock.opening.division.generated',
    'Stok Awal Bahan Baku',
    'ri-archive-drawer-line',
    'inventory/stock/stok-awal/division',
    p.id,
    56,
    1,
    'MAIN',
    parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'purchase.stock.division'
WHERE p.page_code = 'inventory.stock.opening.division.generated'
ON DUPLICATE KEY UPDATE
    menu_label  = VALUES(menu_label),
    icon        = VALUES(icon),
    url         = VALUES(url),
    page_id     = VALUES(page_id),
    parent_id   = VALUES(parent_id),
    sort_order  = VALUES(sort_order),
    is_active   = VALUES(is_active),
    sidebar_type= VALUES(sidebar_type),
    updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 3. Role matrix — salin can_view & can_export dari
--    purchase.stock.division ke halaman baru.
--    Halaman ini hanya read-only; tidak ada can_create/edit/delete.
-- ─────────────────────────────────────────────────────────────
INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT
    rp.role_id,
    new_p.id,
    rp.can_view,
    0,
    0,
    0,
    rp.can_export,
    NOW()
FROM auth_role_permission rp
JOIN sys_page src_p ON src_p.id = rp.page_id
    AND src_p.page_code = 'purchase.stock.division'
JOIN sys_page new_p ON new_p.page_code = 'inventory.stock.opening.division.generated'
WHERE NOT EXISTS (
    SELECT 1 FROM auth_role_permission x
    WHERE x.role_id = rp.role_id
      AND x.page_id = new_p.id
);

-- ─────────────────────────────────────────────────────────────
-- Verifikasi
-- SELECT p.page_code, m.menu_code, m.url, m.sort_order, m.is_active
-- FROM sys_page p
-- JOIN sys_menu m ON m.page_id = p.id
-- WHERE p.page_code = 'inventory.stock.opening.division.generated';
--
-- SELECT COUNT(*) FROM auth_role_permission rp
-- JOIN sys_page p ON p.id = rp.page_id
-- WHERE p.page_code = 'inventory.stock.opening.division.generated';
-- ─────────────────────────────────────────────────────────────

COMMIT;
