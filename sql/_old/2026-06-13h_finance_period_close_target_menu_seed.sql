SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13h_finance_period_close_target_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman Tutup Periode Keuangan
-- 2) Menambahkan halaman Target Keuangan
-- 3) Menyambungkan keduanya ke sidebar rumpun Keuangan
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('finance.period_close.index', 'Tutup Periode Keuangan', 'FINANCE', 'Workspace cut off bulanan dan tahunan untuk snapshot laporan keuangan.', 1),
  ('finance.target.index', 'Target Keuangan', 'FINANCE', 'Workspace target harian, bulanan, tahunan, dan fondasi bonus berbasis metric keuangan.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.period_close',
  'Tutup Periode',
  'ri-calendar-check-line',
  '/finance-reports/period-close',
  p.id,
  28,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.period_close.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.target',
  'Target Keuangan',
  'ri-focus-3-line',
  '/finance-reports/targets',
  p.id,
  29,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.target.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1, 1, 1, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p
  ON p.page_code IN ('finance.period_close.index', 'finance.target.index')
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.finance.period_close.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'finance.period_close.index'
UNION ALL
SELECT 'sys_page.finance.target.index', COUNT(*) FROM sys_page WHERE page_code = 'finance.target.index'
UNION ALL
SELECT 'sys_menu.finance.period_close', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.period_close'
UNION ALL
SELECT 'sys_menu.finance.target', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.target';
