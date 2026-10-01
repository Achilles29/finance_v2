SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21b_pos_sales_hpp_integrity_audit_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman Audit Penjualan & HPP POS
-- 2) Menempatkannya di bawah Laporan POS
-- 3) Memberi akses baca ke peran operasional dan keuangan terkait
--
-- Catatan:
-- - Script ini hanya menambah page, menu, dan hak akses.
-- - Tidak mengubah order, refund, stok, lot, HPP, atau kas.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.report.sales.audit.index',
  'Audit Penjualan & HPP POS',
  'POS',
  'Pemeriksaan baca-saja untuk refund, penjualan bersih, HPP, margin, dan tautan koreksi HPP defisit.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

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
  'pos.report.sales.audit',
  'Audit Penjualan & HPP',
  'ri-shield-search-line',
  '/pos/reports/sales-audit',
  audit_page.id,
  11,
  1,
  'MAIN',
  report_group.id
FROM sys_page audit_page
JOIN sys_menu report_group
  ON report_group.menu_code = 'pos.report.group'
WHERE audit_page.page_code = 'pos.report.sales.audit.index'
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
  audit_page.id,
  1,
  0,
  0,
  0,
  1,
  NOW()
FROM auth_role role
JOIN sys_page audit_page
  ON audit_page.page_code = 'pos.report.sales.audit.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
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
WHERE menu.menu_code = 'pos.report.sales.audit';

SELECT
  page.page_code,
  role.role_code,
  permission.can_view,
  permission.can_export
FROM auth_role_permission permission
JOIN auth_role role ON role.id = permission.role_id
JOIN sys_page page ON page.id = permission.page_id
WHERE page.page_code = 'pos.report.sales.audit.index'
  AND role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HOD')
ORDER BY role.role_code;
