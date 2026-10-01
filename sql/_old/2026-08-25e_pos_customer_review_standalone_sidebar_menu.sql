SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25e_pos_customer_review_standalone_sidebar_menu.sql
-- Tujuan :
-- 1) Menempatkan Ulasan Pelanggan sebagai menu mandiri di POS
-- 2) Tidak menggantungkan akses halaman pada kelompok Printer atau Laporan
-- 3) Memperbarui menu lama secara aman tanpa membuat duplikasi
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'pos.customer_review',
  'Ulasan Pelanggan',
  'ri-star-smile-line',
  '/pos/customer-reviews',
  page.id,
  9,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE page.page_code = 'pos.customer_review.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  menu.menu_code,
  menu.menu_label,
  parent.menu_code AS parent_code,
  menu.url,
  menu.sort_order,
  menu.is_active
FROM sys_menu menu
LEFT JOIN sys_menu parent ON parent.id = menu.parent_id
WHERE menu.menu_code = 'pos.customer_review';
