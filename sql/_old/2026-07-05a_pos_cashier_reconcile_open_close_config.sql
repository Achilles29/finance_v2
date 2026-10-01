SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-05a_pos_cashier_reconcile_open_close_config.sql
-- Tujuan :
-- 1) Menyediakan checkpoint konfirmasi daily recon bahan baku dan
--    component per divisi, per tanggal, per tahap buka/tutup kasir
-- 2) Membuat gate POS opsional via sys_app_config
-- 3) POS hanya mengecek status recon dan memberi warning; recon tetap
--    dilakukan di halaman:
--    - /inventory/stock/daily-recon/division
--    - /production/component-daily-recon
-- 4) Menambahkan halaman pengaturan gate ke RBAC/sidebar POS
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_daily_recon_checkpoint (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  checkpoint_date DATE NOT NULL,
  recon_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  division_id BIGINT(20) UNSIGNED NOT NULL,
  checkpoint_stage ENUM('OPEN','CLOSE') NOT NULL,
  source_page VARCHAR(120) NOT NULL DEFAULT '',
  notes VARCHAR(255) NULL,
  confirmed_by BIGINT(20) UNSIGNED NULL,
  confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_daily_recon_checkpoint (checkpoint_date, recon_domain, division_id, checkpoint_stage),
  KEY idx_inv_daily_recon_checkpoint_date_stage (checkpoint_date, checkpoint_stage),
  KEY idx_inv_daily_recon_checkpoint_division (division_id),
  KEY idx_inv_daily_recon_checkpoint_user (confirmed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inv_daily_recon_checkpoint_line (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  checkpoint_date DATE NOT NULL,
  recon_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  division_id BIGINT(20) UNSIGNED NOT NULL,
  checkpoint_stage ENUM('OPEN','CLOSE') NOT NULL,
  line_key VARCHAR(191) NOT NULL,
  line_label VARCHAR(180) NOT NULL DEFAULT '',
  item_id BIGINT(20) UNSIGNED NULL,
  material_id BIGINT(20) UNSIGNED NULL,
  profile_key VARCHAR(80) NULL,
  component_id BIGINT(20) UNSIGNED NULL,
  uom_id BIGINT(20) UNSIGNED NULL,
  lot_id BIGINT(20) UNSIGNED NULL,
  required_reason VARCHAR(120) NULL,
  source_page VARCHAR(120) NOT NULL DEFAULT '',
  notes VARCHAR(255) NULL,
  confirmed_by BIGINT(20) UNSIGNED NULL,
  confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inv_daily_recon_checkpoint_line (checkpoint_date, recon_domain, division_id, checkpoint_stage, line_key),
  KEY idx_inv_daily_recon_checkpoint_line_scope (checkpoint_date, recon_domain, division_id, checkpoint_stage),
  KEY idx_inv_daily_recon_checkpoint_line_material (material_id),
  KEY idx_inv_daily_recon_checkpoint_line_component (component_id),
  KEY idx_inv_daily_recon_checkpoint_line_user (confirmed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sys_app_config (config_group, config_key, config_value, description, updated_at)
VALUES
  (
    'pos',
    'pos.daily_recon_gate_mode',
    'OPEN_AND_CLOSE',
    'Mode gate daily recon sebelum buka/tutup POS: OFF, OPEN_ONLY, CLOSE_ONLY, OPEN_AND_CLOSE.',
    CURRENT_TIMESTAMP
  ),
  (
    'pos',
    'pos.daily_recon_gate_policy',
    'BLOCK',
    'Kebijakan gate daily recon POS. BLOCK: kasir tidak bisa buka/tutup sebelum checkpoint recon lengkap.',
    CURRENT_TIMESTAMP
  ),
  (
    'pos',
    'pos.daily_recon_confirm_mode',
    'BULK_ALLOWED',
    'Mode konfirmasi daily recon: BULK_ALLOWED atau ROW_REQUIRED.',
    CURRENT_TIMESTAMP
  ),
  (
    'pos',
    'pos.daily_recon_required_materials',
    '',
    'Daftar bahan baku yang wajib recon per baris. Isi material_id, material_code, atau nama; pisahkan baris/koma.',
    CURRENT_TIMESTAMP
  ),
  (
    'pos',
    'pos.daily_recon_required_components',
    '',
    'Daftar component yang wajib recon per baris. Isi component_id, component_code, atau nama; pisahkan baris/koma.',
    CURRENT_TIMESTAMP
  )
ON DUPLICATE KEY UPDATE
  config_group = VALUES(config_group),
  description = VALUES(description),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.daily_recon_settings.index',
  'Pengaturan Gate Daily Recon POS',
  'POS',
  'Mengaktifkan/menonaktifkan warning cek daily recon bahan baku dan component saat buka/tutup kasir POS.',
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
  'pos.daily_recon_settings',
  'Gate Daily Recon',
  'ri-shield-check-line',
  '/pos/daily-recon-settings',
  p.id,
  12,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.daily_recon_settings.index'
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
  1, 0, 1, 0, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.daily_recon_settings.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT config_key, config_value
FROM sys_app_config
WHERE config_key IN (
  'pos.daily_recon_gate_mode',
  'pos.daily_recon_gate_policy',
  'pos.daily_recon_confirm_mode',
  'pos.daily_recon_required_materials',
  'pos.daily_recon_required_components'
)
ORDER BY config_key;

SELECT 'sys_page.pos.daily_recon_settings.index' AS seed_key, COUNT(*) AS total_rows
FROM sys_page
WHERE page_code = 'pos.daily_recon_settings.index'
UNION ALL
SELECT 'sys_menu.pos.daily_recon_settings', COUNT(*)
FROM sys_menu
WHERE menu_code = 'pos.daily_recon_settings';
