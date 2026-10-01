SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-09a_asset_management_module.sql
-- Tujuan :
-- 1) Membuat modul pengelolaan aset fisik Namua
-- 2) Mendukung input bulk, foto aset, laporan rusak + bukti
-- 3) Menyediakan rekon aset bulanan
-- 4) Mendaftarkan sidebar dan role matrix
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS asset_category (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  category_code VARCHAR(40) NOT NULL,
  category_name VARCHAR(120) NOT NULL,
  default_depreciation_method ENUM('NONE','STRAIGHT_LINE') NOT NULL DEFAULT 'STRAIGHT_LINE',
  default_useful_life_months SMALLINT(5) UNSIGNED NOT NULL DEFAULT 36,
  default_residual_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  sort_order INT(11) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_category_code (category_code),
  KEY idx_asset_category_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Kategori master aset fisik';

CREATE TABLE IF NOT EXISTS asset_item (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_code VARCHAR(50) NOT NULL,
  asset_name VARCHAR(160) NOT NULL,
  category_id BIGINT(20) UNSIGNED NULL,
  brand VARCHAR(100) NULL,
  model_name VARCHAR(120) NULL,
  serial_no VARCHAR(120) NULL,
  batch_no VARCHAR(80) NULL,
  purchase_date DATE NULL,
  acquisition_date DATE NULL,
  acquisition_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  residual_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  useful_life_months SMALLINT(5) UNSIGNED NOT NULL DEFAULT 36,
  depreciation_method ENUM('NONE','STRAIGHT_LINE') NOT NULL DEFAULT 'STRAIGHT_LINE',
  depreciation_start_month CHAR(7) NULL,
  division_id BIGINT(20) UNSIGNED NULL,
  outlet_id BIGINT(20) UNSIGNED NULL,
  current_location VARCHAR(160) NULL,
  custodian_employee_id BIGINT(20) UNSIGNED NULL,
  status ENUM('ACTIVE','BROKEN','REPAIR','LOST','RETIRED','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
  condition_score TINYINT UNSIGNED NOT NULL DEFAULT 100,
  photo_path VARCHAR(255) NULL,
  photo_mime VARCHAR(80) NULL,
  notes TEXT NULL,
  created_by BIGINT(20) UNSIGNED NULL,
  updated_by BIGINT(20) UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_item_code (asset_code),
  KEY idx_asset_item_category (category_id),
  KEY idx_asset_item_status (status),
  KEY idx_asset_item_division (division_id),
  KEY idx_asset_item_outlet (outlet_id),
  KEY idx_asset_item_custodian (custodian_employee_id),
  KEY idx_asset_item_purchase_date (purchase_date),
  KEY idx_asset_item_batch (batch_no),
  CONSTRAINT fk_asset_item_category FOREIGN KEY (category_id) REFERENCES asset_category(id) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_asset_item_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_asset_item_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_asset_item_custodian FOREIGN KEY (custodian_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Unit aset fisik satu per satu';

CREATE TABLE IF NOT EXISTS asset_event (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_id BIGINT(20) UNSIGNED NOT NULL,
  event_type ENUM('ACQUIRE','UPDATE','DAMAGE','REPAIR','TRANSFER','RECON','RETIRED','LOST','DISPOSED','ADJUSTMENT') NOT NULL,
  event_date DATE NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NULL,
  from_division_id BIGINT(20) UNSIGNED NULL,
  to_division_id BIGINT(20) UNSIGNED NULL,
  condition_score_before TINYINT UNSIGNED NULL,
  condition_score_after TINYINT UNSIGNED NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  reason TEXT NULL,
  evidence_path VARCHAR(255) NULL,
  evidence_mime VARCHAR(80) NULL,
  created_by BIGINT(20) UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_asset_event_asset_date (asset_id, event_date),
  KEY idx_asset_event_type_date (event_type, event_date),
  KEY idx_asset_event_created_by (created_by),
  CONSTRAINT fk_asset_event_asset FOREIGN KEY (asset_id) REFERENCES asset_item(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail perubahan dan bukti aset';

CREATE TABLE IF NOT EXISTS asset_recon (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  recon_no VARCHAR(50) NOT NULL,
  period_month CHAR(7) NOT NULL,
  division_id BIGINT(20) UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  notes TEXT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  posted_at DATETIME NULL,
  created_by BIGINT(20) UNSIGNED NULL,
  posted_by BIGINT(20) UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_recon_scope (period_month, division_id),
  UNIQUE KEY uk_asset_recon_no (recon_no),
  KEY idx_asset_recon_status (status, period_month),
  KEY idx_asset_recon_division (division_id),
  CONSTRAINT fk_asset_recon_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Header rekon aset bulanan';

CREATE TABLE IF NOT EXISTS asset_recon_line (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  recon_id BIGINT(20) UNSIGNED NOT NULL,
  asset_id BIGINT(20) UNSIGNED NOT NULL,
  expected_status VARCHAR(30) NOT NULL,
  physical_status ENUM('NOT_CHECKED','OK','BROKEN','MISSING','NEED_REPAIR','EXTRA_FOUND') NOT NULL DEFAULT 'NOT_CHECKED',
  condition_score TINYINT UNSIGNED NULL,
  notes TEXT NULL,
  evidence_path VARCHAR(255) NULL,
  evidence_mime VARCHAR(80) NULL,
  checked_by BIGINT(20) UNSIGNED NULL,
  checked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_recon_line_asset (recon_id, asset_id),
  KEY idx_asset_recon_line_asset (asset_id),
  KEY idx_asset_recon_line_status (physical_status),
  CONSTRAINT fk_asset_recon_line_recon FOREIGN KEY (recon_id) REFERENCES asset_recon(id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_asset_recon_line_asset FOREIGN KEY (asset_id) REFERENCES asset_item(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detail hasil cek fisik rekon aset';

INSERT INTO asset_category (
  category_code, category_name, default_depreciation_method,
  default_useful_life_months, default_residual_value, sort_order, is_active
) VALUES
  ('PERALATAN-SAJI', 'Peralatan Saji', 'STRAIGHT_LINE', 24, 0, 10, 1),
  ('PERALATAN-DAPUR', 'Peralatan Dapur', 'STRAIGHT_LINE', 36, 0, 20, 1),
  ('MESIN-KOPI', 'Mesin Kopi & Bar', 'STRAIGHT_LINE', 60, 0, 30, 1),
  ('FURNITURE', 'Furniture', 'STRAIGHT_LINE', 60, 0, 40, 1),
  ('ELEKTRONIK', 'Elektronik & Device', 'STRAIGHT_LINE', 36, 0, 50, 1),
  ('LAINNYA', 'Aset Lainnya', 'STRAIGHT_LINE', 36, 0, 90, 1)
ON DUPLICATE KEY UPDATE
  category_name = VALUES(category_name),
  default_depreciation_method = VALUES(default_depreciation_method),
  default_useful_life_months = VALUES(default_useful_life_months),
  default_residual_value = VALUES(default_residual_value),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_matrix_group (
  group_code, group_label, icon, color, bg_color, sort_order
) VALUES (
  'ASSET', 'Aset', 'ri-archive-2-line', '#0f766e', '#ecfdf5', 86
)
ON DUPLICATE KEY UPDATE
  group_label = VALUES(group_label),
  icon = VALUES(icon),
  color = VALUES(color),
  bg_color = VALUES(bg_color),
  sort_order = VALUES(sort_order);

INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
VALUES
  ('asset.item.index', 'Daftar Aset', 'ASSET', 'ASSET', 'CRUD aset fisik, foto, lokasi, dan nilai buku', 1),
  ('asset.damage.index', 'Laporan Kerusakan Aset', 'ASSET', 'ASSET', 'Input aset rusak/hilang dengan alasan dan bukti foto', 1),
  ('asset.recon.index', 'Rekon Aset Bulanan', 'ASSET', 'ASSET', 'Generate dan posting rekon aset akhir bulan', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  matrix_group = VALUES(matrix_group),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES ('grp.asset', 'Aset', 'ri-archive-2-line', '#', NULL, 12, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.item', 'Daftar Aset', 'ri-list-check-3', '/asset-management', p.id, 1, 1, 'MAIN', g.id
FROM sys_page p
JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.item.index'
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
SELECT 'asset.damage', 'Lapor Rusak', 'ri-camera-lens-line', '/asset-management/damage', p.id, 2, 1, 'MAIN', g.id
FROM sys_page p
JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.damage.index'
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
SELECT 'asset.recon', 'Rekon Bulanan', 'ri-calendar-check-line', '/asset-management/recon', p.id, 3, 1, 'MAIN', g.id
FROM sys_page p
JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.recon.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE
    WHEN p.page_code = 'asset.damage.index' AND r.role_code IN ('STAFF','BARISTA','CHEF','HOD','ADM_GDG','ADMIN','MGR','CEO','SUPERADMIN') THEN 1
    WHEN p.page_code IN ('asset.item.index','asset.recon.index') AND r.role_code IN ('ADM_GDG','ADMIN','MGR','CEO','SUPERADMIN') THEN 1
    ELSE 0
  END,
  CASE WHEN r.role_code IN ('ADM_GDG','ADMIN','MGR','CEO','SUPERADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('ADMIN','MGR','CEO','SUPERADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('ADM_GDG','ADM_FIN','ADMIN','MGR','CEO','SUPERADMIN') THEN 1 ELSE 0 END,
  CURRENT_TIMESTAMP
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('asset.item.index','asset.damage.index','asset.recon.index')
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN','HOD','STAFF','BARISTA','CHEF')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

UPDATE auth_role
SET permissions_updated_at = CURRENT_TIMESTAMP
WHERE role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN','HOD','STAFF','BARISTA','CHEF');

COMMIT;

SELECT 'asset_category' AS object_name, COUNT(*) AS rows_count FROM asset_category
UNION ALL
SELECT 'asset_item', COUNT(*) FROM asset_item
UNION ALL
SELECT 'asset_pages', COUNT(*) FROM sys_page WHERE page_code LIKE 'asset.%'
UNION ALL
SELECT 'asset_menus', COUNT(*) FROM sys_menu WHERE menu_code LIKE 'asset.%' OR menu_code = 'grp.asset';
