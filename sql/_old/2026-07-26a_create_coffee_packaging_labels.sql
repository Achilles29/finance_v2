SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-26a_create_coffee_packaging_labels.sql
-- Tujuan :
-- 1) Membuat tabel draft/desain label packaging kopi roastery
-- 2) Mendaftarkan halaman ke sys_page + sidebar Produksi > Roastery
-- 3) Memberikan permission awal agar modul muncul di role operasional
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS coffee_packaging_label (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  label_code VARCHAR(40) NOT NULL,
  coffee_name VARCHAR(160) NOT NULL,
  origin VARCHAR(160) NULL,
  process_method VARCHAR(120) NULL,
  roast_level VARCHAR(80) NULL,
  body_level VARCHAR(80) NULL,
  elevation_text VARCHAR(120) NULL,
  bean_type VARCHAR(80) NULL,
  weight_text VARCHAR(40) NULL,
  tasting_notes TEXT NULL,
  brew_suggestion VARCHAR(180) NULL,
  batch_no VARCHAR(80) NULL,
  roast_date DATE NULL,
  expiry_date DATE NULL,
  description TEXT NULL,
  footer_note VARCHAR(180) NULL,
  image_path VARCHAR(255) NULL,
  logo_path VARCHAR(255) NULL,
  canvas_width_mm SMALLINT(5) UNSIGNED NOT NULL DEFAULT 90,
  canvas_height_mm SMALLINT(5) UNSIGNED NOT NULL DEFAULT 140,
  theme_preset VARCHAR(60) NOT NULL DEFAULT 'heritage-cream',
  design_json MEDIUMTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT(20) UNSIGNED NULL,
  updated_by BIGINT(20) UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_coffee_packaging_label_code (label_code),
  KEY idx_coffee_packaging_label_active (is_active, updated_at),
  KEY idx_coffee_packaging_label_name (coffee_name),
  KEY idx_coffee_packaging_label_origin (origin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Draft desain label packaging kopi roastery';

SET @has_logo_path := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'logo_path'
);
SET @ddl_logo_path := IF(
  @has_logo_path = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN logo_path VARCHAR(255) NULL AFTER image_path',
  'SELECT ''coffee_packaging_label.logo_path sudah ada'' AS info'
);
PREPARE stmt_logo_path FROM @ddl_logo_path;
EXECUTE stmt_logo_path;
DEALLOCATE PREPARE stmt_logo_path;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  (
    'production.roastery.packaging_label.index',
    'Label Packaging Kopi',
    'PRODUKSI',
    'Desain, preview, dan cetak label packaging kopi roastery',
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
  'production.roastery.group',
  'Roastery',
  'ri-cup-line',
  NULL,
  NULL,
  18,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT id
  FROM sys_menu
  WHERE sidebar_type = 'MAIN'
    AND is_active = 1
    AND menu_code IN ('produk', 'grp.production', 'grp.inventory')
  ORDER BY FIELD(menu_code, 'produk', 'grp.production', 'grp.inventory')
  LIMIT 1
) parent
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
  'production.roastery.packaging_label',
  'Label Packaging Kopi',
  'ri-price-tag-3-line',
  '/roastery/packaging-labels',
  p.id,
  1,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.roastery.packaging_label.index'
WHERE parent.menu_code = 'production.roastery.group'
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
  1,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  1,
  CURRENT_TIMESTAMP
FROM auth_role r
JOIN sys_page p ON p.page_code = 'production.roastery.packaging_label.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','BARISTA','CHEF','ROASTERY')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

UPDATE auth_role
SET permissions_updated_at = CURRENT_TIMESTAMP
WHERE role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','BARISTA','CHEF','ROASTERY');

COMMIT;

SELECT
  p.page_code,
  p.page_name,
  m.menu_code,
  m.url,
  parent.menu_label AS parent_menu,
  COUNT(rp.id) AS permission_rows
FROM sys_page p
LEFT JOIN sys_menu m ON m.page_id = p.id
LEFT JOIN sys_menu parent ON parent.id = m.parent_id
LEFT JOIN auth_role_permission rp ON rp.page_id = p.id AND COALESCE(rp.can_view, 0) = 1
WHERE p.page_code = 'production.roastery.packaging_label.index'
GROUP BY p.page_code, p.page_name, m.menu_code, m.url, parent.menu_label;
