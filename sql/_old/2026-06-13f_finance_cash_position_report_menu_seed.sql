SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13f_finance_cash_position_report_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman Posisi Kas & Eksposur ke modul Keuangan
-- 2) Menyediakan dashboard saldo fisik, saldo riil, dan histori saldo tetap
-- 3) Menjadi pintu awal analisa rekening sebelum laporan keuangan penuh
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'finance.cash_position.index',
  'Posisi Kas & Eksposur',
  'FINANCE',
  'Dashboard rekening untuk membaca saldo fisik, saldo riil, utang, piutang, kasbon, payroll pending, dan histori saldo tetap.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.cash_position',
  'Posisi Kas & Eksposur',
  'ri-funds-box-line',
  '/finance-reports/cash-position',
  p.id,
  24,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.cash_position.index'
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
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'finance.cash_position.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT m.menu_code, m.menu_label, parent.menu_code AS parent_code, m.url, p.page_code
FROM sys_menu m
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code IN ('grp.finance', 'finance.cash_position');
