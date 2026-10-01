SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21a_finance_sales_margin_pos_menu_alias.sql
-- Tujuan :
-- 1) Menempatkan Laporan Penjualan & Margin POS di rumpun Keuangan
-- 2) Memakai halaman laporan POS yang sama, tanpa membuat sumber angka baru
-- 3) Membuka akses baca untuk peran keuangan yang memang perlu audit margin
--
-- Catatan:
-- - Menu POS lama tetap dipertahankan untuk operasional kasir.
-- - Script ini tidak mengubah transaksi, HPP, stok, maupun mutasi keuangan.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_menu (
  menu_code,
  menu_label,
  icon,
  url,
  page_id,
  sort_order,
  is_active,
  sidebar_type,
  parent_id
)
SELECT
  'finance.sales_margin.pos',
  'Penjualan & Margin POS',
  'ri-line-chart-line',
  '/pos/reports/sales',
  report_page.id,
  13,
  1,
  'MAIN',
  finance_group.id
FROM sys_page report_page
JOIN sys_menu finance_group
  ON finance_group.menu_code = 'grp.finance'
WHERE report_page.page_code = 'pos.report.sales.index'
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

INSERT INTO auth_role_permission (
  role_id,
  page_id,
  can_view,
  can_create,
  can_edit,
  can_delete,
  can_export,
  created_at
)
SELECT
  role.id,
  report_page.id,
  1,
  0,
  0,
  0,
  1,
  NOW()
FROM auth_role role
JOIN sys_page report_page
  ON report_page.page_code IN (
    'pos.report.sales.index',
    'pos.report.sales.detail.index',
    'pos.report.sales.extra.index'
  )
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  menu.menu_code,
  menu.menu_label,
  parent.menu_code AS parent_code,
  menu.url,
  page.page_code
FROM sys_menu menu
LEFT JOIN sys_menu parent ON parent.id = menu.parent_id
LEFT JOIN sys_page page ON page.id = menu.page_id
WHERE menu.menu_code = 'finance.sales_margin.pos';

SELECT
  page.page_code,
  role.role_code,
  permission.can_view,
  permission.can_export
FROM auth_role_permission permission
JOIN auth_role role ON role.id = permission.role_id
JOIN sys_page page ON page.id = permission.page_id
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HOD')
  AND page.page_code IN (
    'pos.report.sales.index',
    'pos.report.sales.detail.index',
    'pos.report.sales.extra.index'
  )
ORDER BY page.page_code, role.role_code;
