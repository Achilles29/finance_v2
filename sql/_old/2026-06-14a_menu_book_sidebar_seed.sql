SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14a_menu_book_sidebar_seed.sql
-- Tujuan : Daftarkan Menu Book ke sys_page, sys_menu, dan
--          auth_role_permission untuk akses SUPERADMIN + ADMIN.
-- ============================================================

START TRANSACTION;

-- ── sys_page ──────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('menu_book.index', 'Menu Book NAMUA', 'MENU_BOOK', 'Halaman indeks buku menu digital NAMUA Coffee & Eatery — food & beverage.', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ── Grup Menu Book di sidebar (top-level) ─────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'grp.menu_book', 'Menu Book', 'ri-book-open-line', NULL, NULL, 900, 1, 'MAIN', NULL
WHERE NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code = 'grp.menu_book')
ON DUPLICATE KEY UPDATE
  menu_label  = VALUES(menu_label),
  icon        = VALUES(icon),
  sort_order  = VALUES(sort_order),
  updated_at  = CURRENT_TIMESTAMP;

-- ── Sub-menu: Index (halaman navigasi) ────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'menu_book.index',
  'Menu Book',
  'ri-restaurant-line',
  '/menu_book',
  p.id,
  10,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.menu_book'
WHERE p.page_code = 'menu_book.index'
ON DUPLICATE KEY UPDATE
  menu_label  = VALUES(menu_label),
  icon        = VALUES(icon),
  url         = VALUES(url),
  page_id     = VALUES(page_id),
  parent_id   = VALUES(parent_id),
  sort_order  = VALUES(sort_order),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ── Role permission: SUPERADMIN dan ADMIN ─────────────────────
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT r.id, p.id, 1, 0, 0, 0, 0, NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'menu_book.index'
WHERE r.role_code IN ('SUPERADMIN', 'ADMIN')
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ── Verifikasi ────────────────────────────────────────────────
SELECT
  m.menu_code,
  m.menu_label,
  parent.menu_code AS parent_code,
  p.page_code,
  m.sort_order,
  m.is_active
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code IN ('grp.menu_book', 'menu_book.index')
ORDER BY m.sort_order;
