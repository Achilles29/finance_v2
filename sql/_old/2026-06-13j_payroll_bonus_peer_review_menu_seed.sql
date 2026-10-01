SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13j_payroll_bonus_peer_review_menu_seed.sql
-- Tujuan :
-- 1) Menambahkan workspace Bonus Pegawai ke sidebar Payroll
-- 2) Menambahkan halaman Bonus Saya ke portal pegawai
-- 3) Menyiapkan permission dasar admin payroll dan pegawai
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('payroll.bonus.index', 'Bonus Pegawai', 'PAYROLL', 'Workspace bonus pegawai: aturan bonus, penalti, penilaian 360, dan rekap bonus.', 1),
  ('my.bonus.index', 'Bonus Saya', 'MY_PORTAL', 'Ringkasan bonus pribadi dan penilaian rekan kerja harian.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pay.bonus',
  'Bonus Pegawai',
  'ri-gift-line',
  '/payroll/bonus',
  p.id,
  33,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.payroll'
WHERE p.page_code = 'payroll.bonus.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'my.bonus',
  'Bonus Saya',
  'ri-medal-line',
  '/my/bonus',
  p.id,
  7,
  1,
  'MY',
  NULL
FROM sys_page p
WHERE p.page_code = 'my.bonus.index'
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
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1, 1, 1, 0, 1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'payroll.bonus.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HRD')
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
  rp.role_id,
  p.id,
  1, 1, 0, 0, 0,
  NOW()
FROM auth_role_permission rp
JOIN sys_page source_page ON source_page.id = rp.page_id AND source_page.page_code = 'my.cash_advance.index'
JOIN sys_page p ON p.page_code = 'my.bonus.index'
LEFT JOIN auth_role_permission existing ON existing.role_id = rp.role_id AND existing.page_id = p.id
WHERE existing.role_id IS NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.payroll.bonus.index' AS metric, COUNT(*) AS total
FROM sys_page WHERE page_code = 'payroll.bonus.index'
UNION ALL
SELECT 'sys_page.my.bonus.index', COUNT(*) FROM sys_page WHERE page_code = 'my.bonus.index'
UNION ALL
SELECT 'sys_menu.pay.bonus', COUNT(*) FROM sys_menu WHERE menu_code = 'pay.bonus'
UNION ALL
SELECT 'sys_menu.my.bonus', COUNT(*) FROM sys_menu WHERE menu_code = 'my.bonus';
