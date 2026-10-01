SET NAMES utf8mb4;

-- ============================================================
-- Tahap 31B - Monthly stock projection foundation per domain
-- Tujuan:
-- 1) Menyiapkan tabel stok bulanan terpisah untuk warehouse, division material,
--    dan component.
-- 2) Menyiapkan tabel opening dan opname bulanan terpisah per domain.
-- 3) Memaksa identitas bulanan melalui identity_key kanonik pada warehouse dan
--    division agar tidak pecah oleh kolom nullable seperti profile_key.
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Warehouse monthly stock / opening / opname
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_warehouse_monthly_stock (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_date DATE NULL,
  last_movement_at DATETIME NULL,
  last_movement_table VARCHAR(80) NULL,
  last_movement_id BIGINT UNSIGNED NULL,
  source_mode ENUM('LIVE','REBUILD') NOT NULL DEFAULT 'LIVE',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_wh_monthly_stock_identity (month_key, identity_key),
  KEY idx_inv_wh_monthly_stock_month (month_key),
  KEY idx_inv_wh_monthly_stock_item (item_id),
  KEY idx_inv_wh_monthly_stock_material (material_id),
  KEY idx_inv_wh_monthly_stock_profile (profile_key),
  CONSTRAINT fk_inv_wh_monthly_stock_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_wh_monthly_stock_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_wh_monthly_stock_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_monthly_stock_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inv_warehouse_monthly_opening (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_type ENUM('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  source_month_key DATE NULL,
  source_ref_table VARCHAR(80) NULL,
  source_ref_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_wh_monthly_opening_identity (month_key, identity_key),
  KEY idx_inv_wh_monthly_opening_month (month_key),
  KEY idx_inv_wh_monthly_opening_item (item_id),
  KEY idx_inv_wh_monthly_opening_material (material_id),
  CONSTRAINT fk_inv_wh_monthly_opening_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_wh_monthly_opening_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_wh_monthly_opening_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_monthly_opening_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_monthly_opening_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inv_warehouse_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_wh_monthly_opname_identity (month_key, identity_key),
  KEY idx_inv_wh_monthly_opname_month (month_key),
  KEY idx_inv_wh_monthly_opname_item (item_id),
  KEY idx_inv_wh_monthly_opname_material (material_id),
  CONSTRAINT fk_inv_wh_monthly_opname_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_wh_monthly_opname_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_wh_monthly_opname_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_monthly_opname_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_wh_monthly_opname_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- B. Division material monthly stock / opening / opname
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_division_monthly_stock (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_date DATE NULL,
  last_movement_at DATETIME NULL,
  last_movement_table VARCHAR(80) NULL,
  last_movement_id BIGINT UNSIGNED NULL,
  source_mode ENUM('LIVE','REBUILD') NOT NULL DEFAULT 'LIVE',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_div_monthly_stock_identity (month_key, division_id, destination_type, identity_key),
  KEY idx_inv_div_monthly_stock_month (month_key),
  KEY idx_inv_div_monthly_stock_scope (division_id, destination_type, month_key),
  KEY idx_inv_div_monthly_stock_item (item_id),
  KEY idx_inv_div_monthly_stock_material (material_id),
  KEY idx_inv_div_monthly_stock_profile (profile_key),
  CONSTRAINT fk_inv_div_monthly_stock_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_div_monthly_stock_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_div_monthly_stock_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_div_monthly_stock_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_monthly_stock_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inv_division_monthly_opening (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_type ENUM('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  source_month_key DATE NULL,
  source_ref_table VARCHAR(80) NULL,
  source_ref_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_div_monthly_opening_identity (month_key, division_id, destination_type, identity_key),
  KEY idx_inv_div_monthly_opening_month (month_key),
  KEY idx_inv_div_monthly_opening_scope (division_id, destination_type, month_key),
  KEY idx_inv_div_monthly_opening_item (item_id),
  KEY idx_inv_div_monthly_opening_material (material_id),
  CONSTRAINT fk_inv_div_monthly_opening_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_div_monthly_opening_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_div_monthly_opening_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_div_monthly_opening_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_monthly_opening_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_monthly_opening_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS inv_division_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL,
  identity_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_key CHAR(64) NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NULL,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  discarded_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  discarded_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  closing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_div_monthly_opname_identity (month_key, division_id, destination_type, identity_key),
  KEY idx_inv_div_monthly_opname_month (month_key),
  KEY idx_inv_div_monthly_opname_scope (division_id, destination_type, month_key),
  KEY idx_inv_div_monthly_opname_item (item_id),
  KEY idx_inv_div_monthly_opname_material (material_id),
  CONSTRAINT fk_inv_div_monthly_opname_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_div_monthly_opname_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_div_monthly_opname_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_div_monthly_opname_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_monthly_opname_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_div_monthly_opname_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- C. Component monthly stock / opening / opname
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inv_component_monthly_stock (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_movement_date DATE NULL,
  last_movement_at DATETIME NULL,
  last_movement_table VARCHAR(80) NULL,
  last_movement_id BIGINT UNSIGNED NULL,
  source_mode ENUM('LIVE','REBUILD') NOT NULL DEFAULT 'LIVE',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_monthly_stock_scope (month_key, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_monthly_stock_month (month_key),
  KEY idx_inv_component_monthly_stock_scope_month (location_type, division_id, month_key),
  KEY idx_inv_component_monthly_stock_component (component_id),
  CONSTRAINT fk_inv_component_monthly_stock_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_monthly_stock_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_monthly_stock_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backup schema lama component monthly agar nama final bisa dipakai tanpa suffix.
SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opening'
        AND COLUMN_NAME = 'opening_date'
    )
    AND NOT EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opening_backup_20260531'
    )
    THEN 'RENAME TABLE inv_component_monthly_opening TO inv_component_monthly_opening_backup_20260531'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opname'
        AND COLUMN_NAME = 'opname_date'
    )
    AND NOT EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opname_backup_20260531'
    )
    THEN 'RENAME TABLE inv_component_monthly_opname TO inv_component_monthly_opname_backup_20260531'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS inv_component_monthly_opening (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_avg_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_type ENUM('MANUAL','AUTO_CARRY_FORWARD','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  source_month_key DATE NULL,
  source_ref_table VARCHAR(80) NULL,
  source_ref_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_monthly_opening (month_key, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_monthly_opening_month (month_key),
  KEY idx_inv_component_monthly_opening_scope (location_type, division_id, month_key),
  KEY idx_inv_component_monthly_opening_component (component_id),
  CONSTRAINT fk_inv_component_monthly_opening_live_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_monthly_opening_live_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_monthly_opening_live_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_monthly_opening_live_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opening_v2'
    )
    THEN 'INSERT IGNORE INTO inv_component_monthly_opening (month_key, location_type, division_id, component_id, uom_id, opening_qty, opening_avg_cost, opening_total_value, source_type, source_month_key, source_ref_table, source_ref_id, notes, generated_by, generated_at, created_at, updated_at) SELECT month_key, location_type, division_id, component_id, uom_id, opening_qty, opening_avg_cost, opening_total_value, source_type, source_month_key, source_ref_table, source_ref_id, notes, generated_by, generated_at, created_at, updated_at FROM inv_component_monthly_opening_v2'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opening_v2'
    )
    THEN 'DROP TABLE inv_component_monthly_opening_v2'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS inv_component_monthly_opname (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  month_key DATE NOT NULL,
  location_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT') NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,
  opening_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  in_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  in_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  out_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  out_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  waste_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  spoil_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  spoil_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_plus_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  adjustment_minus_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  adjustment_minus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  closing_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
  total_value DECIMAL(18,2) NOT NULL DEFAULT 0,
  movement_day_count INT UNSIGNED NOT NULL DEFAULT 0,
  mutation_count INT UNSIGNED NOT NULL DEFAULT 0,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_component_monthly_opname (month_key, location_type, division_id, component_id, uom_id),
  KEY idx_inv_component_monthly_opname_month (month_key),
  KEY idx_inv_component_monthly_opname_scope (location_type, division_id, month_key),
  KEY idx_inv_component_monthly_opname_component (component_id),
  CONSTRAINT fk_inv_component_monthly_opname_live_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_component_monthly_opname_live_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_inv_component_monthly_opname_live_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_component_monthly_opname_live_by FOREIGN KEY (generated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opname_v2'
    )
    THEN 'INSERT IGNORE INTO inv_component_monthly_opname (month_key, location_type, division_id, component_id, uom_id, opening_qty, opening_total_value, in_qty, in_total_value, out_qty, out_total_value, waste_qty, waste_total_value, spoil_qty, spoil_total_value, adjustment_plus_qty, adjustment_plus_total_value, adjustment_minus_qty, adjustment_minus_total_value, closing_qty, avg_cost, total_value, movement_day_count, mutation_count, generated_by, generated_at, created_at, updated_at) SELECT month_key, location_type, division_id, component_id, uom_id, opening_qty, opening_total_value, in_qty, in_total_value, out_qty, out_total_value, waste_qty, waste_total_value, spoil_qty, spoil_total_value, adjustment_plus_qty, adjustment_plus_total_value, adjustment_minus_qty, adjustment_minus_total_value, closing_qty, avg_cost, total_value, movement_day_count, mutation_count, generated_by, generated_at, created_at, updated_at FROM inv_component_monthly_opname_v2'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1
      FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'inv_component_monthly_opname_v2'
    )
    THEN 'DROP TABLE inv_component_monthly_opname_v2'
    ELSE 'SELECT 1'
  END
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

SELECT 'inv_warehouse_monthly_stock' AS table_name, COUNT(*) AS total_rows FROM inv_warehouse_monthly_stock
UNION ALL
SELECT 'inv_warehouse_monthly_opening', COUNT(*) FROM inv_warehouse_monthly_opening
UNION ALL
SELECT 'inv_warehouse_monthly_opname', COUNT(*) FROM inv_warehouse_monthly_opname
UNION ALL
SELECT 'inv_division_monthly_stock', COUNT(*) FROM inv_division_monthly_stock
UNION ALL
SELECT 'inv_division_monthly_opening', COUNT(*) FROM inv_division_monthly_opening
UNION ALL
SELECT 'inv_division_monthly_opname', COUNT(*) FROM inv_division_monthly_opname
UNION ALL
SELECT 'inv_component_monthly_stock', COUNT(*) FROM inv_component_monthly_stock
UNION ALL
SELECT 'inv_component_monthly_opening', COUNT(*) FROM inv_component_monthly_opening
UNION ALL
SELECT 'inv_component_monthly_opname', COUNT(*) FROM inv_component_monthly_opname;