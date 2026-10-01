SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-15b_hard_delete_final_legacy_registry.sql
-- Tujuan :
-- 1) Menghapus permanen sys_page / sys_menu legacy yang sudah
--    disepakati aman dibersihkan
-- 2) Merapikan 2 relink aktif terakhir sebelum delete:
--    - purchase.stock.opname.division -> inventory.stock.opname.division.index
--    - production.component.opening.monthly -> production.component.opname.monthly
-- 3) Menyimpan backup row sebelum delete
--
-- Catatan:
-- - Script ini fokus ke registry hak akses / sidebar, bukan business data
-- - Route alias lama boleh tetap hidup di application/config/routes.php
-- - Jalankan setelah sampling final disetujui
-- ============================================================

START TRANSACTION;

SET @backup_tag := 'hard_delete_final_legacy_registry_2026_06_15';
SET @now := NOW();

-- ------------------------------------------------------------
-- A. Backup tables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zbak_20260615_sys_page_legacy_registry AS
SELECT p.*, CAST('' AS CHAR(120)) AS backup_tag, CAST(NULL AS DATETIME) AS backuped_at
FROM sys_page p
WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_20260615_sys_menu_legacy_registry AS
SELECT m.*, CAST('' AS CHAR(120)) AS backup_tag, CAST(NULL AS DATETIME) AS backuped_at
FROM sys_menu m
WHERE 1 = 0;

CREATE TABLE IF NOT EXISTS zbak_20260615_auth_role_permission_legacy_registry AS
SELECT rp.*, CAST('' AS CHAR(120)) AS backup_tag, CAST(NULL AS DATETIME) AS backuped_at
FROM auth_role_permission rp
WHERE 1 = 0;

-- ------------------------------------------------------------
-- B. Target lists
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_legacy_page_codes_delete;
CREATE TEMPORARY TABLE tmp_legacy_page_codes_delete (
  page_code VARCHAR(190) NOT NULL PRIMARY KEY
);

INSERT INTO tmp_legacy_page_codes_delete (page_code) VALUES
  ('inventory.stock.daily.recon.division'),
  ('production.component.opening.monthly'),
  ('master.component.index'),
  ('master.component_formula.index'),
  ('master.purchase.company_account'),
  ('master.purchase.payment_channel'),
  ('procurement.purchasing.index'),
  ('purchase.account.index'),
  ('purchase.stock.opening.index'),
  ('system.backup.guide'),
  ('system.replication.guide'),
  ('grp.finance'),
  ('grp.purchase');

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_menu_codes_delete;
CREATE TEMPORARY TABLE tmp_legacy_menu_codes_delete (
  menu_code VARCHAR(190) NOT NULL PRIMARY KEY
);

INSERT INTO tmp_legacy_menu_codes_delete (menu_code) VALUES
  ('master.component'),
  ('master.component.formula'),
  ('master.purchase.company_account'),
  ('master.purchase.payment_channel'),
  ('procurement.purchasing'),
  ('purchase.account'),
  ('purchase.stock.opening'),
  ('system.backup.guide'),
  ('system.replication.guide'),
  ('pos.member');

-- ------------------------------------------------------------
-- C. Relink menu aktif yang masih menempel ke page legacy
-- ------------------------------------------------------------
SET @page_inventory_opname_division := (
  SELECT id
  FROM sys_page
  WHERE page_code = 'inventory.stock.opname.division.index'
  LIMIT 1
);

SET @page_component_opname_monthly := (
  SELECT id
  FROM sys_page
  WHERE page_code = 'production.component.opname.monthly'
  LIMIT 1
);

UPDATE sys_menu
SET
  page_id = @page_inventory_opname_division,
  updated_at = @now
WHERE menu_code = 'purchase.stock.opname.division'
  AND @page_inventory_opname_division IS NOT NULL;

UPDATE sys_menu
SET
  page_id = @page_component_opname_monthly,
  updated_at = @now
WHERE menu_code = 'production.component.opening.monthly'
  AND @page_component_opname_monthly IS NOT NULL;

-- ------------------------------------------------------------
-- D. Resolve ids
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_legacy_pages_delete;
CREATE TEMPORARY TABLE tmp_legacy_pages_delete AS
SELECT p.*
FROM sys_page p
JOIN tmp_legacy_page_codes_delete t ON t.page_code = p.page_code;

ALTER TABLE tmp_legacy_pages_delete
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_legacy_pages_delete_code (page_code);

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_menus_delete;
CREATE TEMPORARY TABLE tmp_legacy_menus_delete AS
SELECT m.*
FROM sys_menu m
JOIN tmp_legacy_menu_codes_delete t ON t.menu_code = m.menu_code;

ALTER TABLE tmp_legacy_menus_delete
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_legacy_menus_delete_code (menu_code);

DROP TEMPORARY TABLE IF EXISTS tmp_legacy_role_perms_delete;
CREATE TEMPORARY TABLE tmp_legacy_role_perms_delete AS
SELECT rp.*
FROM auth_role_permission rp
JOIN tmp_legacy_pages_delete p ON p.id = rp.page_id;

ALTER TABLE tmp_legacy_role_perms_delete
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_legacy_role_perms_page (page_id);

-- ------------------------------------------------------------
-- E. Backup before delete
-- ------------------------------------------------------------
INSERT INTO zbak_20260615_sys_page_legacy_registry
SELECT p.*, @backup_tag, @now
FROM tmp_legacy_pages_delete p;

INSERT INTO zbak_20260615_sys_menu_legacy_registry
SELECT m.*, @backup_tag, @now
FROM tmp_legacy_menus_delete m;

INSERT INTO zbak_20260615_auth_role_permission_legacy_registry
SELECT rp.*, @backup_tag, @now
FROM tmp_legacy_role_perms_delete rp;

-- ------------------------------------------------------------
-- F. Delete sequence
-- ------------------------------------------------------------
DELETE rp
FROM auth_role_permission rp
JOIN tmp_legacy_role_perms_delete t ON t.id = rp.id;

DELETE m
FROM sys_menu m
JOIN tmp_legacy_menus_delete t ON t.id = m.id;

-- Safety net: jika ada menu lain yang masih refer ke page legacy, lepas page_id-nya
UPDATE sys_menu m
JOIN tmp_legacy_pages_delete p ON p.id = m.page_id
SET
  m.page_id = NULL,
  m.updated_at = @now;

DELETE p
FROM sys_page p
JOIN tmp_legacy_pages_delete t ON t.id = p.id;

COMMIT;

-- ------------------------------------------------------------
-- G. Verification
-- ------------------------------------------------------------
SELECT 'deleted_sys_page_rows' AS metric, COUNT(*) AS total
FROM tmp_legacy_pages_delete

UNION ALL

SELECT 'deleted_sys_menu_rows', COUNT(*)
FROM tmp_legacy_menus_delete

UNION ALL

SELECT 'deleted_auth_role_permission_rows', COUNT(*)
FROM tmp_legacy_role_perms_delete

UNION ALL

SELECT 'remaining_target_pages', COUNT(*)
FROM sys_page
WHERE page_code IN (SELECT page_code FROM tmp_legacy_page_codes_delete)

UNION ALL

SELECT 'remaining_target_menus', COUNT(*)
FROM sys_menu
WHERE menu_code IN (SELECT menu_code FROM tmp_legacy_menu_codes_delete)

UNION ALL

SELECT 'purchase_stock_opname_division_relinked', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'purchase.stock.opname.division'
  AND p.page_code = 'inventory.stock.opname.division.index'

UNION ALL

SELECT 'production_component_opening_monthly_relinked', COUNT(*)
FROM sys_menu m
JOIN sys_page p ON p.id = m.page_id
WHERE m.menu_code = 'production.component.opening.monthly'
  AND p.page_code = 'production.component.opname.monthly';
