SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6R - Parent menu KEUANGAN + menu stok movement/daily
-- ============================================================
START TRANSACTION;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES ('grp.finance', 'KEUANGAN', 'ri-wallet-3-line', '#', NULL, 280, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('finance.account.index', 'Master Company Account', 'FINANCE', 'Kelola rekening perusahaan', 1),
  ('finance.mutation.index', 'Finance Mutation Monitor', 'FINANCE', 'Input mutasi rekening dan log', 1),
  ('purchase.stock.warehouse.movement.index', 'Warehouse Stock Movement', 'PURCHASE', 'Keluar masuk stok gudang', 1),
  ('purchase.stock.warehouse.daily.index', 'Warehouse Daily Rollup', 'PURCHASE', 'Daily rollup stok gudang', 1),
  ('purchase.stock.division.movement.index', 'Division Stock Movement', 'PURCHASE', 'Keluar masuk stok divisi', 1),
  ('purchase.stock.division.daily.index', 'Division Daily Rollup', 'PURCHASE', 'Daily rollup stok divisi', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.company_account',
  'Master Rekening',
  'ri-bank-line',
  '/master/company-account',
  p.id,
  10,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'finance.account.index'
WHERE parent.menu_code = 'grp.finance'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE sys_menu m
JOIN sys_menu p ON p.menu_code = 'grp.finance'
SET
  m.parent_id = p.id,
  m.is_active = 1,
  m.sort_order = 11,
  m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code = 'purchase.account';

UPDATE sys_menu m
JOIN sys_menu p ON p.menu_code = 'grp.finance'
SET
  m.parent_id = p.id,
  m.is_active = 1,
  m.sort_order = 12,
  m.updated_at = CURRENT_TIMESTAMP
WHERE m.menu_code = 'master.purchase.company_account';

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.mutation',
  'Mutasi Keuangan',
  'ri-exchange-funds-line',
  '/finance/mutations',
  p.id,
  20,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'finance.mutation.index'
WHERE parent.menu_code = 'grp.finance'
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
  'purchase.stock.warehouse.movement',
  'Mutasi Stok Gudang',
  'ri-arrow-left-right-line',
  '/purchase/stock/warehouse/movement',
  p.id,
  32,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.warehouse.movement.index'
WHERE parent.menu_code = 'grp.purchase'
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
  'purchase.stock.warehouse.daily',
  'Daily Stok Gudang',
  'ri-calendar-check-line',
  '/purchase/stock/warehouse/daily',
  p.id,
  33,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.warehouse.daily.index'
WHERE parent.menu_code = 'grp.purchase'
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
  'purchase.stock.division.movement',
  'Mutasi Stok Divisi',
  'ri-shuffle-line',
  '/purchase/stock/division/movement',
  p.id,
  42,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.division.movement.index'
WHERE parent.menu_code = 'grp.purchase'
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
  'purchase.stock.division.daily',
  'Daily Stok Divisi',
  'ri-calendar-2-line',
  '/purchase/stock/division/daily',
  p.id,
  43,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.division.daily.index'
WHERE parent.menu_code = 'grp.purchase'
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
  1,
  CASE
    WHEN p.page_code = 'finance.mutation.index' AND r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_FIN') THEN 1
    ELSE 0
  END,
  CASE
    WHEN p.page_code = 'finance.mutation.index' AND r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_FIN') THEN 1
    ELSE 0
  END,
  0,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'finance.account.index',
  'finance.mutation.index',
  'purchase.stock.warehouse.movement.index',
  'purchase.stock.warehouse.daily.index',
  'purchase.stock.division.movement.index',
  'purchase.stock.division.daily.index'
)
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT menu_code, menu_label, url, parent_id
FROM sys_menu
WHERE menu_code IN (
  'grp.finance',
  'finance.company_account',
  'finance.mutation',
  'purchase.stock.warehouse.movement',
  'purchase.stock.warehouse.daily',
  'purchase.stock.division.movement',
  'purchase.stock.division.daily'
)
ORDER BY menu_code;
