SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-24b_asset_master_lock_and_change_request_foundation.sql
-- Tujuan :
-- 1) Menambahkan kunci data master aset tanpa mengubah status fisik aset
-- 2) Menyediakan pengajuan perubahan data aset yang berjejak
-- 3) Menambahkan halaman Perubahan Data Aset dan hak aksesnya
--
-- Catatan:
-- - status ACTIVE/BROKEN/LOST tetap merupakan kondisi fisik aset.
-- - master_lock_status hanya mengatur apakah data awal boleh diedit langsung.
-- - Semua aset lama tetap OPEN agar pendataan awal dapat diselesaikan.
-- ============================================================

START TRANSACTION;

ALTER TABLE asset_item
  ADD COLUMN IF NOT EXISTS master_lock_status ENUM('OPEN','LOCKED') NOT NULL DEFAULT 'OPEN' AFTER status,
  ADD COLUMN IF NOT EXISTS master_locked_by BIGINT UNSIGNED NULL AFTER master_lock_status,
  ADD COLUMN IF NOT EXISTS master_locked_at DATETIME NULL AFTER master_locked_by,
  ADD INDEX IF NOT EXISTS idx_asset_item_master_lock (master_lock_status, division_id, id);

CREATE TABLE IF NOT EXISTS asset_master_change_request (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(60) NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','POSTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  before_snapshot LONGTEXT NOT NULL,
  requested_snapshot LONGTEXT NOT NULL,
  change_summary TEXT NULL,
  reason TEXT NOT NULL,
  evidence_path VARCHAR(255) NULL,
  evidence_mime VARCHAR(80) NULL,
  requested_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  rejected_by BIGINT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  rejection_reason TEXT NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_asset_master_change_request_no (request_no),
  KEY idx_asset_master_change_asset_status (asset_id, status, created_at),
  KEY idx_asset_master_change_status_date (status, created_at),
  KEY idx_asset_master_change_requested_by (requested_by),
  CONSTRAINT fk_asset_master_change_asset
    FOREIGN KEY (asset_id) REFERENCES asset_item(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pengajuan perubahan data master aset setelah aset dikunci';

INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
VALUES (
  'asset.master_change.index',
  'Perubahan Data Aset',
  'ASSET',
  'ASSET',
  'Pengajuan, persetujuan, dan jejak perubahan data master aset yang sudah dikunci.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  matrix_group = VALUES(matrix_group),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'asset.master_change',
  'Perubahan Data',
  'ri-file-edit-line',
  '/asset-management/changes',
  page.id,
  3,
  1,
  'MAIN',
  parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.asset'
WHERE page.page_code = 'asset.master_change.index'
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
  role.id,
  page.id,
  1,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','HOD','STAFF','BARISTA','CHEF') THEN 1 ELSE 0 END,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1 ELSE 0 END,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1 ELSE 0 END,
  CASE WHEN role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN') THEN 1 ELSE 0 END,
  NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'asset.master_change.index'
WHERE role.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN','HOD','STAFF','BARISTA','CHEF')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'asset_item.master_lock_status' AS check_key, COUNT(*) AS total_rows
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'asset_item'
  AND COLUMN_NAME = 'master_lock_status'
UNION ALL
SELECT 'asset_master_change_request', COUNT(*) FROM asset_master_change_request
UNION ALL
SELECT 'sys_page.asset.master_change.index', COUNT(*)
FROM sys_page WHERE page_code = 'asset.master_change.index'
UNION ALL
SELECT 'sys_menu.asset.master_change', COUNT(*)
FROM sys_menu WHERE menu_code = 'asset.master_change';
