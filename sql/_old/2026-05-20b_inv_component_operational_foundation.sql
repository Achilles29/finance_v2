SET NAMES utf8mb4;

-- ============================================================
-- Tahap 8A - Produksi Base/Prepare (Operasional + RBAC Menu)
-- File   : 2026-05-20b_inv_component_operational_foundation.sql
-- Tujuan :
-- 1) Menambah tabel operasional stok base/prepare berbasis master mst_component
-- 2) Menjaga audit movement log yang jelas
-- 3) Menyiapkan daily/monthly snapshot untuk matrix dan tutup-buka bulan
-- 4) Menyiapkan page/menu RBAC modul produksi base/prepare
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Saldo live komponen (sumber baca cepat untuk POS/Kasir)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_stock_balance (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  qty_on_hand DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  last_txn_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_stock_scope (location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_stock_component (component_id),
  KEY idx_inv_component_stock_location_component (location_type, division_id, component_id),
  CONSTRAINT fk_inv_component_stock_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_stock_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_stock_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- B. Ledger mutasi komponen (audit trail utama)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_movement_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  movement_no VARCHAR(60) NOT NULL,
  movement_date DATE NOT NULL,
  movement_datetime DATETIME NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('OPENING','PRODUCTION_IN','PRODUCTION_OUT','TRANSFER_IN','TRANSFER_OUT','USAGE','WASTE','SPOIL','ADJUSTMENT_PLUS','ADJUSTMENT_MINUS','VOID_REVERSE') NOT NULL,
  qty_in DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_out DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_module VARCHAR(50) NOT NULL,
  source_table VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  source_line_id BIGINT UNSIGNED NULL,
  lot_no_snapshot VARCHAR(80) NULL,
  received_date_snapshot DATE NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_movement_no (movement_no),
  KEY idx_inv_component_movement_main (movement_date, location_type, division_id, component_id),
  KEY idx_inv_component_movement_component_date (component_id, movement_date),
  KEY idx_inv_component_movement_source (source_module, source_table, source_id),
  KEY idx_inv_component_movement_location_component (location_type, division_id, component_id),
  CONSTRAINT fk_inv_component_movement_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_movement_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_movement_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_movement_by FOREIGN KEY (created_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Batch produksi komponen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_batch (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  batch_no VARCHAR(60) NOT NULL,
  batch_date DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  output_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  output_uom_id BIGINT UNSIGNED NOT NULL,
  total_input_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  posted_at DATETIME NULL,
  posted_by BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_batch_no (batch_no),
  KEY idx_inv_component_batch_main (batch_date, location_type, division_id, component_id),
  KEY idx_inv_component_batch_status (status),
  CONSTRAINT fk_inv_component_batch_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_batch_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_batch_uom FOREIGN KEY (output_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_batch_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id),
  CONSTRAINT fk_inv_component_batch_posted_by FOREIGN KEY (posted_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_component_batch_input (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  source_kind ENUM('MATERIAL','COMPONENT') NOT NULL,
  material_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  uom_id BIGINT UNSIGNED NOT NULL,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_component_batch_input_batch (batch_id),
  KEY idx_inv_component_batch_input_material (material_id),
  KEY idx_inv_component_batch_input_component (component_id),
  CONSTRAINT fk_inv_component_batch_input_batch FOREIGN KEY (batch_id) REFERENCES inv_component_batch(id),
  CONSTRAINT fk_inv_component_batch_input_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_component_batch_input_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_batch_input_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- D. Dokumen opening komponen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_opening (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  opening_no VARCHAR(60) NOT NULL,
  opening_date DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  posted_at DATETIME NULL,
  posted_by BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_opening_no (opening_no),
  KEY idx_inv_component_opening_date (opening_date),
  KEY idx_inv_component_opening_scope (location_type, division_id),
  CONSTRAINT fk_inv_component_opening_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_opening_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id),
  CONSTRAINT fk_inv_component_opening_posted_by FOREIGN KEY (posted_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_component_opening_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  opening_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_component_opening_line_opening (opening_id),
  KEY idx_inv_component_opening_line_component (component_id),
  CONSTRAINT fk_inv_component_opening_line_opening FOREIGN KEY (opening_id) REFERENCES inv_component_opening(id),
  CONSTRAINT fk_inv_component_opening_line_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_opening_line_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- E. Dokumen adjustment komponen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_adjustment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  adjustment_no VARCHAR(60) NOT NULL,
  adjustment_date DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  posted_at DATETIME NULL,
  posted_by BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_adjustment_no (adjustment_no),
  KEY idx_inv_component_adjustment_date (adjustment_date),
  KEY idx_inv_component_adjustment_scope (location_type, division_id),
  CONSTRAINT fk_inv_component_adjustment_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_adjustment_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id),
  CONSTRAINT fk_inv_component_adjustment_posted_by FOREIGN KEY (posted_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_component_adjustment_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  adjustment_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  available_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_spoil DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_waste DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_adjust_pos DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_adjust_neg DECIMAL(18,4) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_component_adjustment_line_adjustment (adjustment_id),
  KEY idx_inv_component_adjustment_line_component (component_id),
  CONSTRAINT fk_inv_component_adjustment_line_adjustment FOREIGN KEY (adjustment_id) REFERENCES inv_component_adjustment(id),
  CONSTRAINT fk_inv_component_adjustment_line_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_adjustment_line_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- F. Daily rollup komponen (matrix harian)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_daily_rollup (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  movement_date DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_at DATETIME NULL,
  rebuild_batch_no VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_daily_scope (movement_date, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_daily_month (month_key),
  KEY idx_inv_component_daily_scope_day (location_type, division_id, movement_date),
  KEY idx_inv_component_daily_component (component_id),
  CONSTRAINT fk_inv_component_daily_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_daily_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_daily_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- G. Snapshot bulanan komponen (opname + opening)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key CHAR(7) NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opname_date DATE NOT NULL,
  closing_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  hpp_live DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_monthly_opname (month_key, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_monthly_opname_component (component_id),
  CONSTRAINT fk_inv_component_monthly_opname_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_monthly_opname_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_monthly_opname_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_monthly_opname_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_component_monthly_opening (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key CHAR(7) NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_date DATE NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  hpp_live DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_month CHAR(7) NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_monthly_opening (month_key, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_monthly_opening_component (component_id),
  CONSTRAINT fk_inv_component_monthly_opening_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_monthly_opening_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_monthly_opening_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_monthly_opening_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- H. Registrasi halaman RBAC (sys_page)
-- ------------------------------------------------------------
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('production.component.category.index', 'Kategori Base/Prepare', 'PRODUKSI', 'Kategori master base/prepare', 1),
  ('production.component.master.index', 'Master Base/Prepare', 'PRODUKSI', 'Master data base/prepare', 1),
  ('production.component.formula.index', 'Resep/Formula Base/Prepare', 'PRODUKSI', 'Formula/resep base/prepare', 1),
  ('production.component.stock.index', 'Stok Base/Prepare', 'PRODUKSI', 'Saldo live base/prepare per lokasi', 1),
  ('production.component.movement.index', 'Mutasi Base/Prepare', 'PRODUKSI', 'Ledger keluar-masuk base/prepare', 1),
  ('production.component.daily.index', 'Daily Matrix Base/Prepare', 'PRODUKSI', 'Matrix harian stok base/prepare', 1),
  ('production.component.opening.index', 'Opening Base/Prepare', 'PRODUKSI', 'Dokumen stok awal base/prepare', 1),
  ('production.component.adjustment.index', 'Adjustment Base/Prepare', 'PRODUKSI', 'Dokumen penyesuaian base/prepare', 1),
  ('production.component.batch.index', 'Batch Produksi Base/Prepare', 'PRODUKSI', 'Produksi output base/prepare', 1),
  ('production.component.monthly.index', 'Opname Bulanan Base/Prepare', 'PRODUKSI', 'Closing/opening bulanan base/prepare', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- I. Seed menu di bawah group produksi
-- ------------------------------------------------------------
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.category', 'Kategori Base/Prepare', 'ri-price-tag-2-line', '/production/component-categories', p.id, 1, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.category.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.master', 'Master Base/Prepare', 'ri-tools-line', '/production/component-masters', p.id, 2, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.master.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.formula', 'Resep/Formula Base/Prepare', 'ri-function-line', '/production/component-formulas', p.id, 3, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.formula.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.stock', 'Stok Base/Prepare', 'ri-scales-3-line', '/production/component-stock', p.id, 4, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.stock.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.movement', 'Mutasi Base/Prepare', 'ri-exchange-funds-line', '/production/component-movements', p.id, 5, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.movement.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.daily', 'Daily Base/Prepare', 'ri-calendar-check-line', '/production/component-daily', p.id, 6, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.daily.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.batch', 'Batch Produksi', 'ri-flask-line', '/production/component-batches', p.id, 7, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.batch.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.opening', 'Opening Base/Prepare', 'ri-inbox-archive-line', '/production/component-openings', p.id, 8, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.opening.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.adjustment', 'Adjustment Base/Prepare', 'ri-equalizer-3-line', '/production/component-adjustments', p.id, 9, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.adjustment.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'production.component.monthly', 'Opname Bulanan Base/Prepare', 'ri-file-chart-line', '/production/component-monthly', p.id, 10, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'production.component.monthly.index'
WHERE parent.menu_code = 'grp.production'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url), page_id = VALUES(page_id),
  sort_order = VALUES(sort_order), is_active = VALUES(is_active), updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- J. Grant default role untuk halaman produksi
-- ------------------------------------------------------------
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN p.page_code IN ('production.component.stock.index','production.component.movement.index','production.component.daily.index','production.component.monthly.index') THEN 0 ELSE 1 END,
  CASE WHEN p.page_code IN ('production.component.stock.index','production.component.movement.index','production.component.daily.index','production.component.monthly.index') THEN 0 ELSE 1 END,
  CASE WHEN p.page_code IN ('production.component.stock.index','production.component.movement.index','production.component.daily.index','production.component.monthly.index') THEN 0 ELSE 1 END,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'production.component.category.index',
  'production.component.master.index',
  'production.component.formula.index',
  'production.component.stock.index',
  'production.component.movement.index',
  'production.component.daily.index',
  'production.component.opening.index',
  'production.component.adjustment.index',
  'production.component.batch.index',
  'production.component.monthly.index'
)
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','CHEF','BARISTA','ADM_GDG')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- Validation quick check
SELECT 'inv_component_stock_balance' AS table_name, COUNT(*) AS total_rows FROM inv_component_stock_balance
UNION ALL
SELECT 'inv_component_movement_log', COUNT(*) FROM inv_component_movement_log
UNION ALL
SELECT 'inv_component_daily_rollup', COUNT(*) FROM inv_component_daily_rollup
UNION ALL
SELECT 'inv_component_monthly_opname', COUNT(*) FROM inv_component_monthly_opname
UNION ALL
SELECT 'inv_component_monthly_opening', COUNT(*) FROM inv_component_monthly_opening;
