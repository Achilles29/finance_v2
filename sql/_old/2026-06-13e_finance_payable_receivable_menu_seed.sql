SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13e_finance_payable_receivable_menu_seed.sql
-- Tujuan :
-- 1) Daftarkan halaman Utang, Piutang, dan Relasi Pihak ke sys_page
-- 2) Tambahkan sidebar di rumpun Keuangan
-- 3) Beri izin default untuk role keuangan/manajerial
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('finance.payable.index', 'Utang Pihak Luar', 'FINANCE', 'Kelola utang pihak luar, pelunasan, dan dampaknya ke rekening perusahaan.', 1),
  ('finance.receivable.index', 'Piutang Pihak Luar', 'FINANCE', 'Kelola piutang pihak luar, penerimaan pembayaran, dan dampaknya ke rekening perusahaan.', 1),
  ('finance.party.index', 'Relasi Utang Piutang', 'FINANCE', 'Master pihak luar untuk transaksi utang dan piutang, bisa ditautkan ke member.', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.payable',
  'Utang',
  'ri-hand-coin-line',
  '/finance/utang',
  p.id,
  25,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.payable.index'
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
  'finance.receivable',
  'Piutang',
  'ri-wallet-2-line',
  '/finance/piutang',
  p.id,
  26,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.receivable.index'
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
  'finance.party',
  'Pihak Luar',
  'ri-team-line',
  '/finance/relasi',
  p.id,
  27,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.party.index'
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
  1, 1, 1, 1, 1,
  NOW()
FROM auth_role r
JOIN sys_page p
  ON p.page_code IN ('finance.payable.index', 'finance.receivable.index', 'finance.party.index')
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.finance.payable.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page WHERE page_code = 'finance.payable.index'
UNION ALL
SELECT 'sys_page.finance.receivable.index', COUNT(*) FROM sys_page WHERE page_code = 'finance.receivable.index'
UNION ALL
SELECT 'sys_page.finance.party.index', COUNT(*) FROM sys_page WHERE page_code = 'finance.party.index'
UNION ALL
SELECT 'sys_menu.finance.payable', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.payable'
UNION ALL
SELECT 'sys_menu.finance.receivable', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.receivable'
UNION ALL
SELECT 'sys_menu.finance.party', COUNT(*) FROM sys_menu WHERE menu_code = 'finance.party';
