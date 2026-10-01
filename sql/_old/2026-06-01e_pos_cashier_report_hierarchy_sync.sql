SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-01e_pos_cashier_report_hierarchy_sync.sql
-- Tujuan :
-- 1) Memastikan page/menu Kasir POS dan laporan POS tersedia
-- 2) Memaksa parent menu POS berada di bawah grp.pos walau row lama sudah ada
-- 3) Menyinkronkan permission dasar role operasional POS
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('pos.cashier.index', 'Kasir POS', 'POS', 'Layar kasir fase 1 untuk input order, member, bundle, confirm stock commit, dan preview void/refund', 1),
  ('pos.report.sales.index', 'Laporan Penjualan POS', 'POS', 'Laporan ringkasan transaksi penjualan POS per order.', 1),
  ('pos.report.sales.detail.index', 'Laporan Penjualan Produk POS', 'POS', 'Laporan detail penjualan POS sampai level line produk.', 1),
  ('pos.report.payment.index', 'Laporan Pembayaran POS', 'POS', 'Laporan ledger pembayaran POS per dokumen pembayaran.', 1),
  ('pos.report.refund.index', 'Laporan Refund POS', 'POS', 'Laporan refund POS berikut detail pembatalannya.', 1),
  ('pos.report.void.index', 'Laporan Void POS', 'POS', 'Laporan void POS berikut line produk dan extra yang dibatalkan.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.cashier',
  'Kasir POS',
  'ri-shopping-bag-3-line',
  '/pos/cashier',
  p.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.cashier.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.report.group',
  'Laporan POS',
  'ri-bar-chart-box-line',
  NULL,
  NULL,
  90,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id,
  src.sort_order,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT 'pos.report.sales' AS menu_code, 'Laporan Penjualan POS' AS menu_label, 'ri-receipt-line' AS icon, '/pos/reports/sales' AS url, 'pos.report.sales.index' AS page_code, 1 AS sort_order
  UNION ALL SELECT 'pos.report.sales.detail', 'Laporan Penjualan Produk', 'ri-file-list-3-line', '/pos/reports/sales-detail', 'pos.report.sales.detail.index', 2
  UNION ALL SELECT 'pos.report.payment', 'Laporan Pembayaran POS', 'ri-bank-card-line', '/pos/reports/payments', 'pos.report.payment.index', 3
  UNION ALL SELECT 'pos.report.refund', 'Laporan Refund POS', 'ri-arrow-go-back-line', '/pos/reports/refunds', 'pos.report.refund.index', 4
  UNION ALL SELECT 'pos.report.void', 'Laporan Void POS', 'ri-close-circle-line', '/pos/reports/voids', 'pos.report.void.index', 5
) src
JOIN sys_menu parent ON parent.menu_code = 'pos.report.group'
JOIN sys_page p ON p.page_code = src.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
SET child.parent_id = parent.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN ('pos.cashier', 'pos.report.group');

UPDATE sys_menu child
JOIN sys_menu parent ON parent.menu_code = 'pos.report.group'
SET child.parent_id = parent.id,
    child.updated_at = CURRENT_TIMESTAMP
WHERE child.menu_code IN (
  'pos.report.sales',
  'pos.report.sales.detail',
  'pos.report.payment',
  'pos.report.refund',
  'pos.report.void'
);

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN p.page_code = 'pos.cashier.index' THEN 1 ELSE 0 END,
  CASE WHEN p.page_code = 'pos.cashier.index' THEN 1 ELSE 0 END,
  0,
  CASE WHEN p.page_code <> 'pos.cashier.index' AND r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'pos.cashier.index',
  'pos.report.sales.index',
  'pos.report.sales.detail.index',
  'pos.report.payment.index',
  'pos.report.refund.index',
  'pos.report.void.index'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, parent.menu_code AS parent_code, m.sort_order, m.is_active
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
WHERE m.menu_code IN (
  'grp.pos',
  'pos.cashier',
  'pos.report.group',
  'pos.report.sales',
  'pos.report.sales.detail',
  'pos.report.payment',
  'pos.report.refund',
  'pos.report.void'
)
ORDER BY m.menu_code;