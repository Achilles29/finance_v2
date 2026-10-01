SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-09b_asset_management_extensions.sql
-- Tujuan :
-- 1) Mutasi aset antar divisi/outlet dengan approval
-- 2) QR label aset
-- 3) Jadwal maintenance aset
-- 4) Serah terima aset antar PIC
-- 5) Disposal/penghapusan aset
-- 6) Staging jurnal penyusutan aset
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS asset_workflow (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  workflow_type ENUM('TRANSFER','HANDOVER','MAINTENANCE','DISPOSAL') NOT NULL,
  workflow_no VARCHAR(60) NOT NULL,
  asset_id BIGINT(20) UNSIGNED NOT NULL,
  workflow_date DATE NOT NULL,
  due_date DATE NULL,
  status ENUM('PENDING','APPROVED','REJECTED','POSTED','DONE','CANCELLED') NOT NULL DEFAULT 'PENDING',
  from_division_id BIGINT(20) UNSIGNED NULL,
  to_division_id BIGINT(20) UNSIGNED NULL,
  from_outlet_id BIGINT(20) UNSIGNED NULL,
  to_outlet_id BIGINT(20) UNSIGNED NULL,
  from_location VARCHAR(160) NULL,
  to_location VARCHAR(160) NULL,
  from_employee_id BIGINT(20) UNSIGNED NULL,
  to_employee_id BIGINT(20) UNSIGNED NULL,
  maintenance_type VARCHAR(80) NULL,
  priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  vendor_name VARCHAR(160) NULL,
  disposal_type ENUM('RETIRED','DISPOSED','SOLD','DONATED') NULL,
  estimated_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  disposal_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  reason TEXT NULL,
  evidence_path VARCHAR(255) NULL,
  evidence_mime VARCHAR(80) NULL,
  requested_by BIGINT(20) UNSIGNED NULL,
  approved_by BIGINT(20) UNSIGNED NULL,
  approved_at DATETIME NULL,
  posted_by BIGINT(20) UNSIGNED NULL,
  posted_at DATETIME NULL,
  completed_by BIGINT(20) UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_workflow_no (workflow_no),
  KEY idx_asset_workflow_type_status (workflow_type, status),
  KEY idx_asset_workflow_asset (asset_id),
  KEY idx_asset_workflow_date (workflow_date),
  KEY idx_asset_workflow_due (due_date),
  KEY idx_asset_workflow_to_division (to_division_id),
  KEY idx_asset_workflow_to_employee (to_employee_id),
  CONSTRAINT fk_asset_workflow_asset FOREIGN KEY (asset_id) REFERENCES asset_item(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Workflow mutasi, handover, maintenance, dan disposal aset';

CREATE TABLE IF NOT EXISTS asset_depreciation_run (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  run_no VARCHAR(60) NOT NULL,
  period_month CHAR(7) NOT NULL,
  division_id BIGINT(20) UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  total_assets INT(11) NOT NULL DEFAULT 0,
  total_depreciation DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  created_by BIGINT(20) UNSIGNED NULL,
  posted_by BIGINT(20) UNSIGNED NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_dep_run_scope (period_month, division_id),
  UNIQUE KEY uk_asset_dep_run_no (run_no),
  KEY idx_asset_dep_run_status (status, period_month),
  KEY idx_asset_dep_run_division (division_id),
  CONSTRAINT fk_asset_dep_run_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Header staging jurnal penyusutan aset';

CREATE TABLE IF NOT EXISTS asset_depreciation_run_line (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id BIGINT(20) UNSIGNED NOT NULL,
  asset_id BIGINT(20) UNSIGNED NOT NULL,
  acquisition_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  book_value_before DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  depreciation_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  book_value_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  expense_account_code VARCHAR(60) NULL,
  accumulated_account_code VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_dep_line_asset (run_id, asset_id),
  KEY idx_asset_dep_line_asset (asset_id),
  CONSTRAINT fk_asset_dep_line_run FOREIGN KEY (run_id) REFERENCES asset_depreciation_run(id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_asset_dep_line_asset FOREIGN KEY (asset_id) REFERENCES asset_item(id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detail staging jurnal penyusutan aset';

INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
VALUES
  ('asset.transfer.index', 'Mutasi Aset', 'ASSET', 'ASSET', 'Mutasi aset antar divisi/outlet dengan approval dan posting audit trail', 1),
  ('asset.label.index', 'QR Label Aset', 'ASSET', 'ASSET', 'Cetak QR label aset per unit untuk scan detail dan rekon', 1),
  ('asset.maintenance.index', 'Maintenance Aset', 'ASSET', 'ASSET', 'Jadwal dan realisasi preventive/corrective maintenance aset', 1),
  ('asset.handover.index', 'Serah Terima Aset', 'ASSET', 'ASSET', 'Serah terima aset antar PIC/pegawai', 1),
  ('asset.disposal.index', 'Disposal Aset', 'ASSET', 'ASSET', 'Pengajuan pensiun, buang, jual, atau donasi aset', 1),
  ('asset.depreciation.index', 'Jurnal Penyusutan Aset', 'ASSET', 'ASSET', 'Preview dan staging jurnal penyusutan aset bulanan', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  matrix_group = VALUES(matrix_group),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.transfer', 'Mutasi Aset', 'ri-arrow-left-right-line', '/asset-management/transfer', p.id, 4, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.transfer.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.label', 'QR Label', 'ri-fingerprint-line', '/asset-management/labels', p.id, 5, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.label.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.maintenance', 'Maintenance', 'ri-tools-line', '/asset-management/maintenance', p.id, 6, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.maintenance.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.handover', 'Serah Terima', 'ri-user-follow-line', '/asset-management/handover', p.id, 7, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.handover.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.disposal', 'Disposal', 'ri-delete-bin-line', '/asset-management/disposal', p.id, 8, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.disposal.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'asset.depreciation', 'Penyusutan', 'ri-line-chart-line', '/asset-management/depreciation', p.id, 9, 1, 'MAIN', g.id
FROM sys_page p JOIN sys_menu g ON g.menu_code = 'grp.asset'
WHERE p.page_code = 'asset.depreciation.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = VALUES(is_active), parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN','HOD') THEN 1 ELSE 0 END,
  CASE
    WHEN p.page_code IN ('asset.transfer.index','asset.maintenance.index','asset.handover.index','asset.disposal.index') AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','HOD') THEN 1
    WHEN p.page_code IN ('asset.label.index','asset.depreciation.index') AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN') THEN 1
    ELSE 0
  END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN') THEN 1 ELSE 0 END,
  CURRENT_TIMESTAMP
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'asset.transfer.index','asset.label.index','asset.maintenance.index',
  'asset.handover.index','asset.disposal.index','asset.depreciation.index'
)
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

SELECT 'asset_workflow' AS object_name, COUNT(*) AS rows_count FROM asset_workflow
UNION ALL
SELECT 'asset_depreciation_run', COUNT(*) FROM asset_depreciation_run
UNION ALL
SELECT 'asset_extension_pages', COUNT(*) FROM sys_page WHERE page_code IN (
  'asset.transfer.index','asset.label.index','asset.maintenance.index',
  'asset.handover.index','asset.disposal.index','asset.depreciation.index'
);
