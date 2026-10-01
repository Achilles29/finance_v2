SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14e_finance_estimation_bank_daily_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan halaman Estimasi Keuangan
-- 2) Menambahkan halaman Rekap Rekening Harian
-- 3) Menambahkan halaman Keuangan Harian
-- 4) Menyambungkan semuanya ke sidebar rumpun Keuangan
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  (
    'finance.daily_overview.index',
    'Keuangan Harian',
    'FINANCE',
    'Ringkasan harian kas awal, arus masuk, arus keluar, net, kas akhir, dan detail per rekening.',
    1
  ),
  (
    'finance.financial_estimation.index',
    'Estimasi Keuangan',
    'FINANCE',
    'Dashboard estimasi keuangan berbasis omzet, HPP live, beban berjalan, estimasi gaji, adjustment stok, dan saldo riil.',
    1
  ),
  (
    'finance.bank_daily_recap.index',
    'Rekap Rekening Harian',
    'FINANCE',
    'Rekap harian saldo rekening fisik, saldo riil rekening, dan saldo riil kafe untuk audit kas harian.',
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
  'finance.daily_overview',
  'Keuangan Harian',
  'ri-funds-line',
  '/finance-reports/daily-overview',
  p.id,
  25,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.daily_overview.index'
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
  'finance.financial_estimation',
  'Estimasi Keuangan',
  'ri-line-chart-line',
  '/finance-reports/financial-estimation',
  p.id,
  26,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.financial_estimation.index'
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
  'finance.bank_daily_recap',
  'Rekap Rekening Harian',
  'ri-bank-card-line',
  '/finance-reports/rekap-rekening-harian',
  p.id,
  27,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.bank_daily_recap.index'
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
  1, 0, 0, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p
  ON p.page_code IN ('finance.daily_overview.index', 'finance.financial_estimation.index', 'finance.bank_daily_recap.index')
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.finance.daily_overview.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'finance.daily_overview.index'
UNION ALL
SELECT 'sys_page.finance.financial_estimation.index', COUNT(*) FROM sys_page WHERE page_code = 'finance.financial_estimation.index'
UNION ALL
SELECT 'sys_page.finance.bank_daily_recap.index', COUNT(*) FROM sys_page WHERE page_code = 'finance.bank_daily_recap.index'
UNION ALL
SELECT 'sys_menu.finance.daily_overview', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.daily_overview'
UNION ALL
SELECT 'sys_menu.finance.financial_estimation', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.financial_estimation'
UNION ALL
SELECT 'sys_menu.finance.bank_daily_recap', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.bank_daily_recap';
