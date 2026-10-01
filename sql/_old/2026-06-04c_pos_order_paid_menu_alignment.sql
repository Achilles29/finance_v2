SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-04c_pos_order_paid_menu_alignment.sql
-- Tujuan :
-- 1) Memastikan sys_page untuk pos.order.paid.index ada dan up-to-date
-- 2) Memastikan sys_menu pos.order.paid.index benar-benar ada
--    di bawah grp.pos dengan sort_order yang sejajar draft & monitor
-- 3) Menambahkan permission edit (can_edit) untuk role yang relevan
--    agar fitur edit metode pembayaran di workspace bisa dipakai
-- 4) TIDAK mengubah struktur sidebar halaman lain
-- ============================================================

START TRANSACTION;

-- ── 1) sys_page ──────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.order.paid.index',
  'Pesanan Terbayar POS',
  'POS',
  'Workspace pesanan POS berstatus PAID: filter tanggal, detail order, refund, dan koreksi metode pembayaran.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name    = VALUES(page_name),
  module       = VALUES(module),
  description  = VALUES(description),
  is_active    = VALUES(is_active),
  updated_at   = CURRENT_TIMESTAMP;

-- ── 2) sys_menu di bawah grp.pos, sort_order = 7 ─────────────
--    (kasir=4, draft=5, monitor=6, paid=7)
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.order.paid.index',
  'Pesanan Terbayar',
  'ri-wallet-3-line',
  '/pos/orders/paid',
  p.id,
  7,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.order.paid.index'
ON DUPLICATE KEY UPDATE
  menu_label  = VALUES(menu_label),
  icon        = VALUES(icon),
  url         = VALUES(url),
  page_id     = VALUES(page_id),
  parent_id   = VALUES(parent_id),
  sort_order  = VALUES(sort_order),
  is_active   = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  updated_at  = CURRENT_TIMESTAMP;

-- ── 3) Permission: view + create + edit untuk role kasir & admin ──
--    (mirror dari pos.order.draft.index, plus can_edit=1 agar bisa
--     pakai fitur edit metode pembayaran)
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1, 1, 1, 0, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.order.paid.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit   = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ── Verifikasi ────────────────────────────────────────────────
SELECT 'sys_page.pos.order.paid.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'pos.order.paid.index'
UNION ALL
SELECT 'sys_menu.pos.order.paid.index', COUNT(*)
FROM sys_menu WHERE menu_code = 'pos.order.paid.index'
UNION ALL
SELECT 'auth_role_permission.pos.order.paid.index', COUNT(*)
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id AND p.page_code = 'pos.order.paid.index';
