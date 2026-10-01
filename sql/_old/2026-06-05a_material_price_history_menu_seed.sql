SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05a_material_price_history_menu_seed.sql
-- Tujuan :
-- 1) Daftarkan halaman Riwayat Harga Bahan ke sys_page
-- 2) Tambahkan ke sidebar di rumpun Purchase (grp.purchase)
--    setara dengan Log Purchase, Report Purchase, dll.
-- 3) Permission: read-only untuk semua role yang punya akses purchase
-- ============================================================

START TRANSACTION;

-- ── sys_page ──────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'purchase.material.price_history',
  'Riwayat Harga Bahan Baku',
  'PURCHASE',
  'Grafik dan tabel riwayat harga beli & HPP per item dari data purchase receipt.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ── sys_menu di bawah grp.purchase, sort_order = 85 ──────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.material.price_history',
  'Riwayat Harga Item',
  'ri-line-chart-line',
  '/purchase/item-price-history',
  p.id,
  85,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.purchase'
WHERE p.page_code = 'purchase.material.price_history'
ON DUPLICATE KEY UPDATE
  menu_label  = VALUES(menu_label),
  icon        = VALUES(icon),
  url         = VALUES(url),
  page_id     = VALUES(page_id),
  parent_id   = VALUES(parent_id),
  sort_order  = VALUES(sort_order),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ── Permission: mirror dari purchase.order.index ──────────────
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  dst.id,
  rp.can_view,
  0, 0, 0,
  rp.can_export,
  NOW()
FROM auth_role_permission rp
JOIN sys_page src ON src.id = rp.page_id AND src.page_code = 'purchase.order.index'
JOIN sys_page dst ON dst.page_code = 'purchase.material.price_history'
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.purchase.material.price_history' AS seed_key, COUNT(*) AS total
FROM sys_page WHERE page_code = 'purchase.material.price_history'
UNION ALL
SELECT 'sys_menu.purchase.material.price_history', COUNT(*)
FROM sys_menu WHERE menu_code = 'purchase.material.price_history';
