SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-27a_purchase_report_menu_seed.sql
-- Tujuan : Menambah halaman Laporan Purchase ke modul PO & SR
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('purchase.report.index', 'Laporan Purchase', 'PURCHASE', 'Ringkasan bulanan/harian purchase dan detail per tipe per tanggal', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'purchase.report', 'Laporan Purchase', 'ri-file-chart-line', '/purchase-orders/report', p.id, 5, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.report.index'
WHERE parent.menu_code = 'grp.purchase'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- rapikan ulang urutan menu PO & SR agar konsisten dengan tab penghubung
UPDATE sys_menu SET sort_order = 1 WHERE menu_code = 'purchase.order';
UPDATE sys_menu SET sort_order = 2 WHERE menu_code = 'procurement.store-request';
UPDATE sys_menu SET sort_order = 3 WHERE menu_code = 'procurement.division';
UPDATE sys_menu SET sort_order = 4 WHERE menu_code = 'purchase.order.log';
UPDATE sys_menu SET sort_order = 5 WHERE menu_code = 'purchase.report';
UPDATE sys_menu SET sort_order = 6 WHERE menu_code = 'purchase.rebuild.impact';
UPDATE sys_menu SET sort_order = 7 WHERE menu_code = 'purchase.receipt';
UPDATE sys_menu SET sort_order = 8 WHERE menu_code = 'purchase.reclassify-profile-domain';

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT
  r.id,
  p.id,
  1, 0, 0, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.report.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

