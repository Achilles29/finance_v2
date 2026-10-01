SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-01k_pos_cashier_close_report_seed.sql
-- Tujuan : Menambah halaman laporan POS ke sys_page,
--          sys_menu, dan permission agar sidebar/RBAC hormat DB.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('pos.report.cashier.close.index', 'Laporan Tutup Kasir POS', 'POS', 'Laporan tutup kasir POS yang membandingkan snapshot rekening shift dengan mutasi rekening POS tercatat.', 1),
  ('pos.report.daily_sales.index', 'Daily Sales POS', 'POS', 'Ringkasan penjualan harian POS per tanggal, metode bayar, rekening, dan shift.', 1),
  ('pos.report.payment.method.index', 'Laporan Metode Pembayaran POS', 'POS', 'Rekap penerimaan POS per metode pembayaran termasuk refund.', 1),
  ('pos.report.payment.account.index', 'Laporan Rekening Pembayaran POS', 'POS', 'Rekap penerimaan POS per rekening perusahaan termasuk refund.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
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
  'pos.report.payment.method',
  'Laporan Metode Pembayaran POS',
  'ri-bank-card-2-line',
  '/pos/reports/payment-methods',
  p.id,
  5,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.report.payment.method.index'
WHERE parent.menu_code = 'pos.report.group'
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
  'pos.report.payment.account',
  'Laporan Rekening Pembayaran POS',
  'ri-wallet-3-line',
  '/pos/reports/payment-accounts',
  p.id,
  6,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.report.payment.account.index'
WHERE parent.menu_code = 'pos.report.group'
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
  'pos.report.cashier.close',
  'Laporan Tutup Kasir POS',
  'ri-safe-2-line',
  '/pos/reports/cashier-close',
  p.id,
  7,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.report.cashier.close.index'
WHERE parent.menu_code = 'pos.report.group'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu
SET sort_order = CASE menu_code
  WHEN 'pos.report.daily_sales' THEN 1
  WHEN 'pos.report.sales' THEN 2
  WHEN 'pos.report.sales.detail' THEN 3
  WHEN 'pos.report.payment' THEN 4
  WHEN 'pos.report.payment.method' THEN 5
  WHEN 'pos.report.payment.account' THEN 6
  WHEN 'pos.report.cashier.close' THEN 7
  WHEN 'pos.report.refund' THEN 8
  WHEN 'pos.report.void' THEN 9
  ELSE sort_order
END,
updated_at = CURRENT_TIMESTAMP
WHERE menu_code IN (
  'pos.report.daily_sales',
  'pos.report.sales',
  'pos.report.sales.detail',
  'pos.report.payment',
  'pos.report.payment.method',
  'pos.report.payment.account',
  'pos.report.cashier.close',
  'pos.report.refund',
  'pos.report.void'
);

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.report.daily_sales',
  'Daily Sales POS',
  'ri-calendar-check-line',
  '/pos/reports/daily-sales',
  p.id,
  1,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.report.daily_sales.index'
WHERE parent.menu_code = 'pos.report.group'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  parent_id = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.report.cashier.close.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.report.daily_sales.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.report.payment.method.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  0,
  0,
  0,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.report.payment.account.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR', 'BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.pos.report.cashier.close' AS seed_key, COUNT(*) AS total_rows
FROM sys_page
WHERE page_code = 'pos.report.cashier.close.index'
UNION ALL
SELECT 'sys_page.pos.report.daily_sales', COUNT(*)
FROM sys_page
WHERE page_code = 'pos.report.daily_sales.index'
UNION ALL
SELECT 'sys_page.pos.report.payment.method', COUNT(*)
FROM sys_page
WHERE page_code = 'pos.report.payment.method.index'
UNION ALL
SELECT 'sys_page.pos.report.payment.account', COUNT(*)
FROM sys_page
WHERE page_code = 'pos.report.payment.account.index'
UNION ALL
SELECT 'sys_menu.pos.report.cashier.close', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.report.cashier.close'
UNION ALL
SELECT 'sys_menu.pos.report.daily_sales', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.report.daily_sales'
UNION ALL
SELECT 'sys_menu.pos.report.payment.method', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.report.payment.method'
UNION ALL
SELECT 'sys_menu.pos.report.payment.account', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.report.payment.account'
UNION ALL
SELECT 'auth_role_permission.pos.report.cashier.close', COUNT(*)
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id
WHERE p.page_code = 'pos.report.cashier.close.index'
UNION ALL
SELECT 'auth_role_permission.pos.report.daily_sales', COUNT(*)
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id
WHERE p.page_code = 'pos.report.daily_sales.index'
UNION ALL
SELECT 'auth_role_permission.pos.report.payment.method', COUNT(*)
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id
WHERE p.page_code = 'pos.report.payment.method.index'
UNION ALL
SELECT 'auth_role_permission.pos.report.payment.account', COUNT(*)
FROM auth_role_permission arp
JOIN sys_page p ON p.id = arp.page_id
WHERE p.page_code = 'pos.report.payment.account.index';