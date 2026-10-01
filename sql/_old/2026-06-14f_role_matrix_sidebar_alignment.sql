SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14f_role_matrix_sidebar_alignment.sql
-- Tujuan :
-- 1) Menyamakan registry role/matrix-group dengan sidebar aktif saat ini
-- 2) Mendaftarkan page_code controller yang belum ada di sys_page
-- 3) Memperbaiki menu yang masih tertaut ke page lama / salah
-- 4) Memisahkan registry page yang memang beda fungsi walau dulu share page_id
-- 5) Backfill matrix_group agar urutan matrix mengikuti rumpun sidebar
--
-- Catatan:
-- - Script ini fokus pada registry hak akses, bukan UI kosmetik
-- - Tidak menghapus page legacy agresif; hanya merapikan yang jelas salah-link
-- - Setelah run, refresh /roles dan /roles/matrix-groups
-- ============================================================

START TRANSACTION;

SET @now := NOW();

-- ------------------------------------------------------------
-- A. Seed / rapikan catalog matrix group mengikuti sidebar aktif
-- ------------------------------------------------------------
INSERT INTO sys_matrix_group (
  group_code, group_label, icon, color, bg_color, sort_order, created_at
)
VALUES
  ('DASHBOARD',  'Dashboard',          'ri-dashboard-line',           '#1d4ed8', '#eff6ff',  10, @now),
  ('POS',        'POS & Kasir',        'ri-store-2-line',             '#be185d', '#fdf2f8',  20, @now),
  ('PURCHASE',   'Purchase',           'ri-shopping-cart-2-line',     '#ea580c', '#fff7ed',  30, @now),
  ('INVENTORY',  'Inventory',          'ri-archive-drawer-line',      '#059669', '#f0fdf4',  40, @now),
  ('PRODUCT',    'Produk',             'ri-store-2-line',             '#7c3aed', '#f5f3ff',  50, @now),
  ('PRODUKSI',   'Base / Prepare',     'ri-flask-line',               '#0f766e', '#ecfeff',  51, @now),
  ('HR',         'Karyawan & HR',      'ri-team-line',                '#0284c7', '#f0f9ff',  60, @now),
  ('ATTENDANCE', 'Absensi',            'ri-calendar-check-line',      '#b45309', '#fffbeb',  61, @now),
  ('LOYALTY',    'Member & Promo',     'ri-user-star-line',           '#2563eb', '#eff6ff',  70, @now),
  ('FINANCE',    'Keuangan',           'ri-bank-line',                '#be123c', '#fff1f2',  80, @now),
  ('PAYROLL',    'Penggajian',         'ri-money-dollar-circle-line', '#0d9488', '#f0fdfa',  90, @now),
  ('MASTER',     'Master Data',        'ri-database-2-line',          '#475569', '#f8fafc', 100, @now),
  ('MENU_BOOK',  'Menu Book',          'ri-book-open-line',           '#7c2d12', '#fff7ed', 110, @now),
  ('AUTH',       'Hak Akses',          'ri-shield-keyhole-line',      '#2563eb', '#eff6ff', 120, @now),
  ('SYSTEM',     'Sistem',             'ri-settings-3-line',          '#475569', '#f1f5f9', 121, @now),
  ('MY_PORTAL',  'Portal Saya',        'ri-user-settings-line',       '#4338ca', '#eef2ff', 200, @now)
ON DUPLICATE KEY UPDATE
  group_label = VALUES(group_label),
  icon = VALUES(icon),
  color = VALUES(color),
  bg_color = VALUES(bg_color),
  sort_order = VALUES(sort_order);

DROP TEMPORARY TABLE IF EXISTS tmp_matrix_group_sidebar_order;
CREATE TEMPORARY TABLE tmp_matrix_group_sidebar_order AS
SELECT
  mapped.group_code,
  MIN(mapped.target_sort_order) AS target_sort_order
FROM (
  SELECT 'DASHBOARD' AS group_code, (m.sort_order * 10) AS target_sort_order
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'dashboard'

  UNION ALL
  SELECT 'POS', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL
    AND m.menu_code IN ('pos.cashier', 'pos.self_order', 'grp.pos', 'pos.report.group')

  UNION ALL
  SELECT 'PURCHASE', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.purchase'

  UNION ALL
  SELECT 'INVENTORY', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.inventory'

  UNION ALL
  SELECT 'PRODUCT', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'produk'

  UNION ALL
  SELECT 'PRODUKSI', (m.sort_order * 10) + 1
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'produk'

  UNION ALL
  SELECT 'HR', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.hr'

  UNION ALL
  SELECT 'ATTENDANCE', (m.sort_order * 10) + 1
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.hr'

  UNION ALL
  SELECT 'LOYALTY', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.loyalty'

  UNION ALL
  SELECT 'FINANCE', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.finance'

  UNION ALL
  SELECT 'PAYROLL', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.payroll'

  UNION ALL
  SELECT 'MASTER', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.master'

  UNION ALL
  SELECT 'MENU_BOOK', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.menu_book'

  UNION ALL
  SELECT 'AUTH', (m.sort_order * 10)
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.system'

  UNION ALL
  SELECT 'SYSTEM', (m.sort_order * 10) + 1
  FROM sys_menu m
  WHERE m.is_active = 1 AND m.sidebar_type = 'MAIN' AND m.parent_id IS NULL AND m.menu_code = 'grp.system'
) mapped
GROUP BY mapped.group_code;

UPDATE sys_matrix_group g
JOIN tmp_matrix_group_sidebar_order o ON o.group_code = g.group_code
SET g.sort_order = o.target_sort_order;

UPDATE sys_matrix_group
SET sort_order = (
  SELECT COALESCE(MAX(target_sort_order), 0) + 10
  FROM tmp_matrix_group_sidebar_order
)
WHERE group_code = 'MY_PORTAL';

-- Legacy group lama tetap dipertahankan, tapi dorong ke bawah
UPDATE sys_matrix_group
SET sort_order = 900
WHERE group_code = 'REPORT';

UPDATE sys_matrix_group
SET sort_order = 910
WHERE group_code = 'SYS';

UPDATE sys_matrix_group
SET sort_order = 920
WHERE group_code = 'SISTEM';

-- ------------------------------------------------------------
-- B. Rapikan page_code legacy vs controller yang aktif
-- ------------------------------------------------------------
UPDATE sys_page
SET
  page_code = 'product.monitoring.availability.index',
  page_name = 'Ketersediaan Produk',
  module = 'PRODUCT'
WHERE page_code = 'product.availability';

UPDATE sys_page
SET
  page_code = 'production.component.lot.index',
  page_name = 'Lot Component',
  module = 'PRODUKSI'
WHERE page_code = 'production.component.lots';

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'production.component.reconcile.index',
  'Reconcile Base / Prepare',
  'PRODUKSI',
  'PRODUKSI',
  'Halaman audit dan rekonsiliasi mismatch stok component/base-prepare.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'production.component.reconcile.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'my.schedule.index',
  'Jadwal Saya',
  'MY_PORTAL',
  'MY_PORTAL',
  'Halaman jadwal kerja pegawai di portal saya.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'my.schedule.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'attendance.schedules.v2.index',
  'Jadwal Shift V2',
  'ATTENDANCE',
  'ATTENDANCE',
  'Versi spreadsheet jadwal shift bulanan per pegawai.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'attendance.schedules.v2.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'attendance.meal_calendar.index',
  'Kalender Uang Makan',
  'ATTENDANCE',
  'ATTENDANCE',
  'Kalender estimasi uang makan per pegawai dan per hari.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'attendance.meal_calendar.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'pos.stock.commit.audit.index',
  'Audit Commit Stok POS',
  'POS',
  'POS',
  'Audit mismatch commit stok POS untuk bahan baku dan base/prepare.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'pos.stock.commit.audit.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'purchase.stock.division.lot.index',
  'Lot Bahan Baku Divisi',
  'INVENTORY',
  'INVENTORY',
  'Audit dan penelusuran lot FIFO bahan baku pada scope divisi.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'purchase.stock.division.lot.index'
);

INSERT INTO sys_page (
  page_code, page_name, module, matrix_group, description, is_active, created_at
)
SELECT
  'purchase.stock.warehouse.lot.index',
  'Lot Stok Gudang',
  'INVENTORY',
  'INVENTORY',
  'Audit dan penelusuran lot FIFO bahan baku pada scope gudang.',
  1,
  @now
FROM dual
WHERE NOT EXISTS (
  SELECT 1 FROM sys_page WHERE page_code = 'purchase.stock.warehouse.lot.index'
);

-- ------------------------------------------------------------
-- C. Perbaiki menu yang masih salah taut ke page lama
-- ------------------------------------------------------------
UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'production.component.reconcile.index'
SET m.page_id = p.id
WHERE m.menu_code = 'production.component.reconcile';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'attendance.schedules.v2.index'
SET m.page_id = p.id
WHERE m.menu_code = 'hr.att-schedules-v2';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'attendance.meal_calendar.index'
SET m.page_id = p.id
WHERE m.menu_code = 'hr.att-meal-calendar';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'pos.stock.commit.audit.index'
SET m.page_id = p.id
WHERE m.menu_code = 'pos.stock.commit.audit';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'purchase.stock.division.lot.index'
SET m.page_id = p.id
WHERE m.menu_code = 'purchase.stock.division.lot';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'purchase.stock.warehouse.lot.index'
SET m.page_id = p.id
WHERE m.menu_code = 'purchase.stock.warehouse.lot';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'inventory.stock.opname.division.index'
SET m.page_id = p.id
WHERE m.menu_code = 'purchase.stock.opname.division';

UPDATE sys_menu m
JOIN sys_page p ON p.page_code = 'production.component.opname.monthly'
SET m.page_id = p.id
WHERE m.menu_code = 'production.component.opening.monthly';

UPDATE sys_menu
SET url = '/inventory/stock/daily-recon/division',
    updated_at = @now
WHERE menu_code = 'purchase.stock.opname.division';

UPDATE sys_menu
SET url = '/inventory/stock/opname/division/monthly',
    updated_at = @now
WHERE menu_code = 'inventory.stock.opname.division.monthly';

UPDATE sys_menu
SET url = '/inventory/stock/opname/warehouse/monthly',
    updated_at = @now
WHERE menu_code = 'inventory.stock.opname.warehouse.monthly';

UPDATE sys_menu
SET url = '/production/component-daily-recon',
    updated_at = @now
WHERE menu_code = 'production.component.daily.recon';

UPDATE sys_menu
SET url = '/production/component-opname',
    updated_at = @now
WHERE menu_code = 'production.component.opname.monthly';

UPDATE sys_menu
SET url = '/finance/accounts',
    updated_at = @now
WHERE menu_code = 'finance.company_account';

SET @has_my_schedule_menu := (
  SELECT COUNT(*)
  FROM sys_menu
  WHERE menu_code = 'my.schedule'
);

UPDATE sys_menu
SET sort_order = sort_order + 1,
    updated_at = @now
WHERE @has_my_schedule_menu = 0
  AND sidebar_type = 'MY'
  AND parent_id IS NULL
  AND sort_order >= 3;

INSERT INTO sys_menu (
  menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id
)
SELECT
  'my.schedule',
  'Jadwal Saya',
  'ri-calendar-2-line',
  '/my/schedule',
  p.id,
  3,
  1,
  'MY',
  NULL
FROM sys_page p
WHERE p.page_code = 'my.schedule.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  updated_at = @now;

-- Page alias / legacy yang memang sudah digantikan route / page lain
UPDATE sys_page
SET is_active = 0
WHERE page_code IN (
  'inventory.stock.daily.recon.division',
  'production.component.opening.monthly',
  'master.component.index',
  'master.component_formula.index',
  'master.purchase.company_account',
  'master.purchase.payment_channel',
  'procurement.purchasing.index',
  'purchase.account.index',
  'purchase.stock.opening.index',
  'pos.member.index',
  'system.backup.guide',
  'system.replication.guide',
  'grp.finance',
  'grp.purchase'
);

UPDATE sys_menu
SET is_active = 0,
    updated_at = @now
WHERE menu_code IN (
  'master.component',
  'master.component.formula',
  'master.purchase.payment_channel',
  'production.component.opening.monthly',
  'purchase.account',
  'system.backup.guide',
  'system.replication.guide',
  'pos.member'
);

-- ------------------------------------------------------------
-- D. Clone permission rows untuk page baru yang sebelumnya tidak bisa diatur
-- ------------------------------------------------------------
SET @page_daily_component := (
  SELECT id FROM sys_page WHERE page_code = 'production.component.daily.index' LIMIT 1
);
SET @page_reconcile_component := (
  SELECT id FROM sys_page WHERE page_code = 'production.component.reconcile.index' LIMIT 1
);
SET @page_schedules := (
  SELECT id FROM sys_page WHERE page_code = 'attendance.schedules.index' LIMIT 1
);
SET @page_schedules_v2 := (
  SELECT id FROM sys_page WHERE page_code = 'attendance.schedules.v2.index' LIMIT 1
);
SET @page_attendance_estimate := (
  SELECT id FROM sys_page WHERE page_code = 'attendance.estimate.index' LIMIT 1
);
SET @page_meal_calendar := (
  SELECT id FROM sys_page WHERE page_code = 'attendance.meal_calendar.index' LIMIT 1
);
SET @page_pos_stock_live := (
  SELECT id FROM sys_page WHERE page_code = 'pos.stock.live.index' LIMIT 1
);
SET @page_pos_stock_commit_audit := (
  SELECT id FROM sys_page WHERE page_code = 'pos.stock.commit.audit.index' LIMIT 1
);
SET @page_stock_division := (
  SELECT id FROM sys_page WHERE page_code = 'purchase.stock.division.index' LIMIT 1
);
SET @page_stock_division_lot := (
  SELECT id FROM sys_page WHERE page_code = 'purchase.stock.division.lot.index' LIMIT 1
);
SET @page_stock_warehouse := (
  SELECT id FROM sys_page WHERE page_code = 'purchase.stock.warehouse.index' LIMIT 1
);
SET @page_stock_warehouse_lot := (
  SELECT id FROM sys_page WHERE page_code = 'purchase.stock.warehouse.lot.index' LIMIT 1
);
SET @page_daily_recon_component := (
  SELECT id FROM sys_page WHERE page_code = 'production.component.daily.recon.index' LIMIT 1
);
SET @page_opname_component := (
  SELECT id FROM sys_page WHERE page_code = 'production.component.opname.monthly' LIMIT 1
);
SET @page_my_attendance := (
  SELECT id FROM sys_page WHERE page_code = 'my.attendance.index' LIMIT 1
);
SET @page_my_schedule := (
  SELECT id FROM sys_page WHERE page_code = 'my.schedule.index' LIMIT 1
);

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_reconcile_component,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_daily_component
  AND @page_reconcile_component IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_schedules_v2,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_schedules
  AND @page_schedules_v2 IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_meal_calendar,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_attendance_estimate
  AND @page_meal_calendar IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_pos_stock_commit_audit,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_pos_stock_live
  AND @page_pos_stock_commit_audit IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_stock_division_lot,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_stock_division
  AND @page_stock_division_lot IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_stock_warehouse_lot,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_stock_warehouse
  AND @page_stock_warehouse_lot IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_daily_recon_component,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_daily_component
  AND @page_daily_recon_component IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_opname_component,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_daily_component
  AND @page_opname_component IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  rp.role_id,
  @page_my_schedule,
  rp.can_view,
  rp.can_create,
  rp.can_edit,
  rp.can_delete,
  rp.can_export,
  @now
FROM auth_role_permission rp
WHERE rp.page_id = @page_my_attendance
  AND @page_my_schedule IS NOT NULL
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = @now;

-- ------------------------------------------------------------
-- E. Backfill matrix_group dari ancestry sidebar aktif
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_role_page_top_menu_raw;
CREATE TEMPORARY TABLE tmp_role_page_top_menu_raw AS
SELECT
  p.id AS page_id,
  COALESCE(top3.menu_code, top2.menu_code, top1.menu_code, m.menu_code) AS top_menu_code,
  COALESCE(top3.sort_order, top2.sort_order, top1.sort_order, m.sort_order) AS top_sort_order
FROM sys_page p
JOIN sys_menu m
  ON m.page_id = p.id
 AND m.is_active = 1
LEFT JOIN sys_menu top1 ON top1.id = m.parent_id
LEFT JOIN sys_menu top2 ON top2.id = top1.parent_id
LEFT JOIN sys_menu top3 ON top3.id = top2.parent_id;

DROP TEMPORARY TABLE IF EXISTS tmp_role_page_top_menu;
CREATE TEMPORARY TABLE tmp_role_page_top_menu AS
SELECT
  page_id,
  SUBSTRING_INDEX(
    MIN(CONCAT(LPAD(CAST(COALESCE(top_sort_order, 9999) AS CHAR), 6, '0'), '|', top_menu_code)),
    '|',
    -1
  ) AS top_menu_code
FROM tmp_role_page_top_menu_raw
GROUP BY page_id;

UPDATE sys_page p
JOIN tmp_role_page_top_menu t ON t.page_id = p.id
SET p.matrix_group = CASE
  WHEN t.top_menu_code = 'dashboard' THEN 'DASHBOARD'
  WHEN t.top_menu_code IN ('pos.cashier', 'pos.self_order', 'grp.pos', 'pos.report.group') THEN 'POS'
  WHEN t.top_menu_code = 'grp.purchase' THEN 'PURCHASE'
  WHEN t.top_menu_code = 'grp.inventory' THEN 'INVENTORY'
  WHEN t.top_menu_code = 'produk' AND p.page_code LIKE 'production.%' THEN 'PRODUKSI'
  WHEN t.top_menu_code = 'produk' THEN 'PRODUCT'
  WHEN t.top_menu_code = 'grp.hr' AND p.page_code LIKE 'attendance.%' THEN 'ATTENDANCE'
  WHEN t.top_menu_code = 'grp.hr' THEN 'HR'
  WHEN t.top_menu_code = 'grp.loyalty' THEN 'LOYALTY'
  WHEN t.top_menu_code = 'grp.finance' THEN 'FINANCE'
  WHEN t.top_menu_code = 'grp.payroll' THEN 'PAYROLL'
  WHEN t.top_menu_code = 'grp.master' THEN 'MASTER'
  WHEN t.top_menu_code = 'grp.system' AND p.page_code LIKE 'auth.%' THEN 'AUTH'
  WHEN t.top_menu_code = 'grp.system' THEN 'SYSTEM'
  WHEN t.top_menu_code = 'grp.menu_book' THEN 'MENU_BOOK'
  WHEN t.top_menu_code = 'production.component.opening.monthly' THEN 'PRODUKSI'
  WHEN p.page_code LIKE 'my.%' THEN 'MY_PORTAL'
  ELSE COALESCE(NULLIF(p.matrix_group, ''), p.matrix_group)
END
WHERE p.is_active = 1
  AND (
    COALESCE(p.matrix_group, '') = ''
    OR p.page_code IN (
      'attendance.schedules.v2.index',
      'attendance.meal_calendar.index',
      'pos.stock.commit.audit.index',
      'product.monitoring.availability.index',
      'production.component.lot.index',
      'production.component.reconcile.index',
      'inventory.stock.opname.division.index',
      'purchase.stock.division.lot.index',
      'purchase.stock.warehouse.lot.index',
      'my.schedule.index'
    )
  );

-- Fallback untuk page aktif yang belum punya menu aktif
UPDATE sys_page
SET matrix_group = CASE
  WHEN page_code LIKE 'dashboard.%' THEN 'DASHBOARD'
  WHEN page_code LIKE 'auth.%' THEN 'AUTH'
  WHEN page_code LIKE 'system.%' THEN 'SYSTEM'
  WHEN page_code LIKE 'finance.%' THEN 'FINANCE'
  WHEN page_code LIKE 'payroll.%' THEN 'PAYROLL'
  WHEN page_code LIKE 'loyalty.%' THEN 'LOYALTY'
  WHEN page_code LIKE 'attendance.%' THEN 'ATTENDANCE'
  WHEN page_code LIKE 'hr.%' THEN 'HR'
  WHEN page_code LIKE 'my.%' THEN 'MY_PORTAL'
  WHEN page_code LIKE 'pos.%' THEN 'POS'
  WHEN page_code LIKE 'procurement.%' THEN 'PURCHASE'
  WHEN page_code LIKE 'purchase.%' THEN 'PURCHASE'
  WHEN page_code LIKE 'inventory.%' THEN 'INVENTORY'
  WHEN page_code LIKE 'production.%' THEN 'PRODUKSI'
  WHEN page_code LIKE 'product.%' THEN 'PRODUCT'
  WHEN page_code LIKE 'menu_book.%' THEN 'MENU_BOOK'
  WHEN page_code LIKE 'master.%' THEN 'MASTER'
  ELSE COALESCE(NULLIF(module, ''), 'SYSTEM')
END
WHERE is_active = 1
  AND COALESCE(matrix_group, '') = '';

COMMIT;

-- ------------------------------------------------------------
-- G. Verifikasi ringkas
-- ------------------------------------------------------------
SELECT 'controller_pages_missing_after_seed' AS metric, COUNT(*) AS total
FROM (
  SELECT 'my.schedule.index' AS page_code
  UNION ALL SELECT 'attendance.schedules.v2.index'
  UNION ALL SELECT 'attendance.meal_calendar.index'
  UNION ALL SELECT 'pos.stock.commit.audit.index'
  UNION ALL SELECT 'purchase.stock.division.lot.index'
  UNION ALL SELECT 'purchase.stock.warehouse.lot.index'
  UNION ALL SELECT 'product.monitoring.availability.index'
  UNION ALL SELECT 'production.component.lot.index'
  UNION ALL SELECT 'production.component.reconcile.index'
) t
LEFT JOIN sys_page p ON p.page_code = t.page_code
WHERE p.id IS NULL

UNION ALL

SELECT 'active_menu_without_page_after_seed', COUNT(*)
FROM sys_menu
WHERE is_active = 1
  AND COALESCE(TRIM(url), '') <> ''
  AND COALESCE(url, '') <> '#'
  AND page_id IS NULL

UNION ALL

SELECT 'active_pages_without_matrix_group', COUNT(*)
FROM sys_page
WHERE is_active = 1
  AND COALESCE(matrix_group, '') = ''

UNION ALL

SELECT 'inventory_stock_opname_division_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'purchase.stock.opname.division'
  AND p.page_code = 'inventory.stock.opname.division.index'

UNION ALL

SELECT 'attendance_schedules_v2_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'hr.att-schedules-v2'
  AND p.page_code = 'attendance.schedules.v2.index'

UNION ALL

SELECT 'attendance_meal_calendar_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'hr.att-meal-calendar'
  AND p.page_code = 'attendance.meal_calendar.index'

UNION ALL

SELECT 'pos_stock_commit_audit_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'pos.stock.commit.audit'
  AND p.page_code = 'pos.stock.commit.audit.index'

UNION ALL

SELECT 'purchase_stock_division_lot_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'purchase.stock.division.lot'
  AND p.page_code = 'purchase.stock.division.lot.index'

UNION ALL

SELECT 'purchase_stock_warehouse_lot_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'purchase.stock.warehouse.lot'
  AND p.page_code = 'purchase.stock.warehouse.lot.index'

UNION ALL

SELECT 'production_component_reconcile_menu_link_ok', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'production.component.reconcile'
  AND p.page_code = 'production.component.reconcile.index'

UNION ALL

SELECT 'production_component_opening_monthly_menu_disabled', COUNT(*)
FROM sys_menu m
WHERE m.menu_code = 'production.component.opening.monthly'
  AND COALESCE(m.is_active, 1) = 0;
