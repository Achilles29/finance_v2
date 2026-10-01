SET NAMES utf8mb4;

-- =========================================================
-- Tahap 2 Master Data Schema (finance)
-- Date: 2026-05-02
-- Notes:
-- 1) Split division domain:
--    - product division     : mst_product_division
--    - operational division : mst_operational_division
-- 2) Item -> Material uses many-to-one via mst_item.material_id (nullable)
-- 3) HPP live uses cache + dirty flag + queue for background recompute
-- =========================================================

START TRANSACTION;

-- =========================================================
-- A. MASTER UOM
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_uom (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_uom_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_uom_conversion (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  from_uom_id BIGINT UNSIGNED NOT NULL,
  to_uom_id BIGINT UNSIGNED NOT NULL,
  factor DECIMAL(18,6) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_uom_pair (from_uom_id, to_uom_id),
  CONSTRAINT fk_mst_uom_conv_from FOREIGN KEY (from_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_mst_uom_conv_to FOREIGN KEY (to_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- B. MASTER CATEGORY + DIVISION
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_item_category (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_item_category_code (code),
  KEY idx_mst_item_category_parent (parent_id),
  CONSTRAINT fk_mst_item_category_parent FOREIGN KEY (parent_id) REFERENCES mst_item_category(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_component_category (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_component_category_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_product_division (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  pos_scope ENUM('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_product_division_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_product_classification (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_division_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_product_classification_code (code),
  KEY idx_mst_product_classification_division (product_division_id),
  CONSTRAINT fk_mst_product_classification_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_product_category (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_division_id BIGINT UNSIGNED NOT NULL,
  classification_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_product_category_code (code),
  KEY idx_mst_product_category_division (product_division_id),
  KEY idx_mst_product_category_classification (classification_id),
  CONSTRAINT fk_mst_product_category_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
  CONSTRAINT fk_mst_product_category_classification FOREIGN KEY (classification_id) REFERENCES mst_product_classification(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_operational_division (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_operational_division_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- C. MATERIAL + ITEM (many item -> one material)
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_material (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  material_code VARCHAR(50) NOT NULL,
  material_name VARCHAR(150) NOT NULL,
  item_category_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  hpp_standard DECIMAL(18,6) NOT NULL DEFAULT 0,
  shelf_life_days INT UNSIGNED NULL,
  reorder_level_content DECIMAL(18,4) NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_material_code (material_code),
  KEY idx_mst_material_category (item_category_id),
  KEY idx_mst_material_uom (content_uom_id),
  CONSTRAINT fk_mst_material_category FOREIGN KEY (item_category_id) REFERENCES mst_item_category(id),
  CONSTRAINT fk_mst_material_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_item (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  item_code VARCHAR(50) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  item_category_id BIGINT UNSIGNED NOT NULL,

  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,

  min_stock_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  last_buy_price DECIMAL(18,2) NULL,

  is_material TINYINT(1) NOT NULL DEFAULT 0,
  material_id BIGINT UNSIGNED NULL,

  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_mst_item_code (item_code),
  KEY idx_mst_item_category (item_category_id),
  KEY idx_mst_item_buy_uom (buy_uom_id),
  KEY idx_mst_item_content_uom (content_uom_id),
  KEY idx_mst_item_material_id (material_id),

  CONSTRAINT fk_mst_item_category FOREIGN KEY (item_category_id) REFERENCES mst_item_category(id),
  CONSTRAINT fk_mst_item_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_mst_item_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_mst_item_material FOREIGN KEY (material_id) REFERENCES mst_material(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- D. COMPONENT (BASE/PREPARE)
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_component (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  component_code VARCHAR(50) NOT NULL,
  component_name VARCHAR(150) NOT NULL,
  component_type ENUM('BASE','PREPARE') NOT NULL,

  product_division_id BIGINT UNSIGNED NOT NULL,
  operational_division_id BIGINT UNSIGNED NOT NULL,
  component_category_id BIGINT UNSIGNED NOT NULL,
  uom_id BIGINT UNSIGNED NOT NULL,

  yield_qty DECIMAL(18,4) NOT NULL DEFAULT 1,

  hpp_standard DECIMAL(18,6) NOT NULL DEFAULT 0,
  variable_cost_mode ENUM('DEFAULT','NONE','CUSTOM') NOT NULL DEFAULT 'DEFAULT',
  variable_cost_percent DECIMAL(10,4) NOT NULL DEFAULT 0,

  min_stock DECIMAL(18,4) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  notes VARCHAR(255) NULL,

  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_mst_component_code (component_code),
  KEY idx_mst_component_product_division (product_division_id),
  KEY idx_mst_component_division (operational_division_id),
  KEY idx_mst_component_category (component_category_id),
  KEY idx_mst_component_uom (uom_id),

  CONSTRAINT fk_mst_component_product_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
  CONSTRAINT fk_mst_component_division FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_mst_component_category FOREIGN KEY (component_category_id) REFERENCES mst_component_category(id),
  CONSTRAINT fk_mst_component_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_component_formula (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  component_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL DEFAULT 1,
  line_type ENUM('MATERIAL','COMPONENT') NOT NULL,
  material_item_id BIGINT UNSIGNED NULL,
  sub_component_id BIGINT UNSIGNED NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  uom_id BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_mst_component_formula_component (component_id),
  KEY idx_mst_component_formula_material_item (material_item_id),
  KEY idx_mst_component_formula_sub_component (sub_component_id),

  CONSTRAINT fk_mst_component_formula_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_mst_component_formula_material_item FOREIGN KEY (material_item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_mst_component_formula_sub_component FOREIGN KEY (sub_component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_mst_component_formula_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- E. PRODUCT + RECIPE
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_product (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_code VARCHAR(50) NOT NULL,
  product_name VARCHAR(150) NOT NULL,

  product_division_id BIGINT UNSIGNED NOT NULL,
  default_operational_division_id BIGINT UNSIGNED NOT NULL,
  classification_id BIGINT UNSIGNED NOT NULL,
  product_category_id BIGINT UNSIGNED NOT NULL,

  uom_id BIGINT UNSIGNED NOT NULL,
  selling_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  description TEXT NULL,

  hpp_standard DECIMAL(18,6) NOT NULL DEFAULT 0,
  hpp_live_cache DECIMAL(18,6) NULL,
  hpp_live_at DATETIME NULL,
  hpp_dirty TINYINT(1) NOT NULL DEFAULT 1,

  variable_cost_mode ENUM('DEFAULT','NONE','CUSTOM') NOT NULL DEFAULT 'DEFAULT',
  variable_cost_percent DECIMAL(10,4) NOT NULL DEFAULT 0,

  stock_mode ENUM('MANUAL_AVAILABLE','MANUAL_OUT','AUTO') NOT NULL DEFAULT 'AUTO',

  show_pos TINYINT(1) NOT NULL DEFAULT 1,
  show_member TINYINT(1) NOT NULL DEFAULT 0,
  show_landing TINYINT(1) NOT NULL DEFAULT 0,

  photo_path VARCHAR(255) NULL,
  photo_mime VARCHAR(50) NULL,

  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_mst_product_code (product_code),
  KEY idx_mst_product_division (product_division_id),
  KEY idx_mst_product_classification (classification_id),
  KEY idx_mst_product_category (product_category_id),
  KEY idx_mst_product_default_operational_division (default_operational_division_id),

  CONSTRAINT fk_mst_product_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
  CONSTRAINT fk_mst_product_default_operational_division FOREIGN KEY (default_operational_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_mst_product_classification FOREIGN KEY (classification_id) REFERENCES mst_product_classification(id),
  CONSTRAINT fk_mst_product_category FOREIGN KEY (product_category_id) REFERENCES mst_product_category(id),
  CONSTRAINT fk_mst_product_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_product_recipe (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL DEFAULT 1,
  line_type ENUM('MATERIAL','COMPONENT') NOT NULL,
  material_item_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  source_division_id BIGINT UNSIGNED NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  uom_id BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_mst_product_recipe_product (product_id),
  KEY idx_mst_product_recipe_material_item (material_item_id),
  KEY idx_mst_product_recipe_component (component_id),
  KEY idx_mst_product_recipe_source_division (source_division_id),

  CONSTRAINT fk_mst_product_recipe_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_mst_product_recipe_material_item FOREIGN KEY (material_item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_mst_product_recipe_component FOREIGN KEY (component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_mst_product_recipe_source_division FOREIGN KEY (source_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_mst_product_recipe_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- F. VENDOR
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_vendor (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  vendor_code VARCHAR(50) NOT NULL,
  vendor_name VARCHAR(150) NOT NULL,
  contact_name VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  tax_no VARCHAR(50) NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  payment_terms INT UNSIGNED NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_vendor_code (vendor_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_vendor_item (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  vendor_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  vendor_sku VARCHAR(50) NULL,
  last_price DECIMAL(18,2) NULL,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_vendor_item (vendor_id, item_id),
  CONSTRAINT fk_mst_vendor_item_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id),
  CONSTRAINT fk_mst_vendor_item_item FOREIGN KEY (item_id) REFERENCES mst_item(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- G. EXTRA / ADD-ON
-- =========================================================
CREATE TABLE IF NOT EXISTS mst_extra (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  extra_code VARCHAR(40) NOT NULL,
  extra_name VARCHAR(120) NOT NULL,
  uom_name VARCHAR(50) NULL,
  extra_type ENUM('ADD','REMOVE','CHOICE','INFO') NOT NULL DEFAULT 'ADD',
  selling_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  cost_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_kind ENUM('NONE','PRODUCT','COMPONENT','MATERIAL') NOT NULL DEFAULT 'NONE',
  source_product_id BIGINT UNSIGNED NULL,
  source_component_id BIGINT UNSIGNED NULL,
  source_material_id BIGINT UNSIGNED NULL,
  source_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  replacement_kind ENUM('NONE','PRODUCT','COMPONENT','MATERIAL') NOT NULL DEFAULT 'NONE',
  replacement_product_id BIGINT UNSIGNED NULL,
  replacement_component_id BIGINT UNSIGNED NULL,
  replacement_material_id BIGINT UNSIGNED NULL,
  replacement_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  show_in_cashier TINYINT(1) NOT NULL DEFAULT 1,
  show_in_self_order TINYINT(1) NOT NULL DEFAULT 1,
  show_in_landing TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_extra_code (extra_code),
  KEY idx_mst_extra_source_product (source_product_id),
  KEY idx_mst_extra_source_component (source_component_id),
  KEY idx_mst_extra_source_material (source_material_id),
  CONSTRAINT fk_mst_extra_source_product FOREIGN KEY (source_product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_mst_extra_source_component FOREIGN KEY (source_component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_mst_extra_source_material FOREIGN KEY (source_material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_mst_extra_replacement_product FOREIGN KEY (replacement_product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_mst_extra_replacement_component FOREIGN KEY (replacement_component_id) REFERENCES mst_component(id),
  CONSTRAINT fk_mst_extra_replacement_material FOREIGN KEY (replacement_material_id) REFERENCES mst_material(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_extra_group (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  group_code VARCHAR(40) NOT NULL,
  group_name VARCHAR(120) NOT NULL,
  product_division_id BIGINT UNSIGNED NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  min_select INT NOT NULL DEFAULT 0,
  max_select INT NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_extra_group_code (group_code),
  KEY idx_mst_extra_group_division (product_division_id),
  CONSTRAINT fk_mst_extra_group_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_extra_group_item (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  extra_group_id BIGINT UNSIGNED NOT NULL,
  extra_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_extra_group_item (extra_group_id, extra_id),
  CONSTRAINT fk_mst_extra_group_item_group FOREIGN KEY (extra_group_id) REFERENCES mst_extra_group(id),
  CONSTRAINT fk_mst_extra_group_item_extra FOREIGN KEY (extra_id) REFERENCES mst_extra(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_product_extra_map (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  extra_group_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_product_extra_map (extra_group_id, product_id),
  CONSTRAINT fk_mst_product_extra_map_group FOREIGN KEY (extra_group_id) REFERENCES mst_extra_group(id),
  CONSTRAINT fk_mst_product_extra_map_product FOREIGN KEY (product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- H. COST RECOMPUTE QUEUE
-- =========================================================
CREATE TABLE IF NOT EXISTS cost_recalc_queue (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(50) NOT NULL,
  status ENUM('PENDING','PROCESSING','DONE','FAILED') NOT NULL DEFAULT 'PENDING',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  KEY idx_cost_recalc_queue_status (status),
  KEY idx_cost_recalc_queue_product (product_id),
  CONSTRAINT fk_cost_recalc_queue_product FOREIGN KEY (product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- =========================================================
-- I. CORE SEED IMPORT (OPTIONAL)
-- =========================================================
-- Usage:
--   SET @src_db = 'core';
--   Run statements below one by one.

SET @src_db = IFNULL(@src_db, 'core');

-- 1) UOM
SET @sql = CONCAT(
"INSERT INTO mst_uom (code, name, is_active, created_at, updated_at)",
" SELECT code, name, IFNULL(is_active,1), NOW(), NOW() FROM ", @src_db, ".m_uom",
" ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) UOM conversion by code mapping
SET @sql = CONCAT(
"INSERT INTO mst_uom_conversion (from_uom_id, to_uom_id, factor, is_active, created_at, updated_at)",
" SELECT fu.id, tu.id, c.factor, IFNULL(c.is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".m_uom_conversion c",
" JOIN ", @src_db, ".m_uom sf ON sf.id = c.from_uom_id",
" JOIN ", @src_db, ".m_uom st ON st.id = c.to_uom_id",
" JOIN mst_uom fu ON fu.code = sf.code",
" JOIN mst_uom tu ON tu.code = st.code",
" ON DUPLICATE KEY UPDATE factor=VALUES(factor), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Operational division from org_division
SET @sql = CONCAT(
"INSERT INTO mst_operational_division (code, name, sort_order, is_active, created_at, updated_at)",
" SELECT division_code, division_name, 0, IFNULL(is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".org_division",
" ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Product division / classification / category
SET @sql = CONCAT(
"INSERT INTO mst_product_division (code, name, pos_scope, sort_order, is_active, created_at, updated_at)",
" SELECT division_code, division_name, IFNULL(pos_scope,'REGULAR'), IFNULL(display_order,0), IFNULL(is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".prd_product_division",
" ON DUPLICATE KEY UPDATE name=VALUES(name), pos_scope=VALUES(pos_scope), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
"INSERT INTO mst_product_classification (product_division_id, code, name, sort_order, is_active, created_at, updated_at)",
" SELECT d_new.id, c.classification_code, c.classification_name, IFNULL(c.display_order,0), IFNULL(c.is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".prd_product_classification c",
" JOIN ", @src_db, ".prd_product_division d_old ON d_old.id = c.division_id",
" JOIN mst_product_division d_new ON d_new.code = d_old.division_code",
" ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT(
"INSERT INTO mst_product_category (product_division_id, classification_id, code, name, sort_order, is_active, created_at, updated_at)",
" SELECT d_new.id, c_new.id, cat.category_code, cat.category_name, IFNULL(cat.display_order,0), IFNULL(cat.is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".prd_product_category cat",
" JOIN ", @src_db, ".prd_product_classification c_old ON c_old.id = cat.classification_id",
" JOIN ", @src_db, ".prd_product_division d_old ON d_old.id = c_old.division_id",
" JOIN mst_product_division d_new ON d_new.code = d_old.division_code",
" JOIN mst_product_classification c_new ON c_new.code = c_old.classification_code",
" ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Component category from core
SET @sql = CONCAT(
"INSERT INTO mst_component_category (code, name, sort_order, is_active, created_at, updated_at)",
" SELECT category_code, category_name, IFNULL(display_order,0), IFNULL(is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".prd_component_category",
" ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Item/material category from core material category
SET @sql = CONCAT(
"INSERT INTO mst_item_category (code, name, parent_id, sort_order, is_active, created_at, updated_at)",
" SELECT category_code, category_name, NULL, 0, IFNULL(is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".m_material_category",
" ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) Extra master from core POS extra
SET @sql = CONCAT(
"INSERT INTO mst_extra (extra_code, extra_name, uom_name, extra_type, selling_price, cost_amount, source_kind, source_qty, show_in_cashier, show_in_self_order, is_active, created_at, updated_at)",
" SELECT extra_code, extra_name, uom_name, extra_type, selling_price, cost_amount, source_kind, IFNULL(source_qty,0), IFNULL(show_in_cashier,1), IFNULL(show_in_self_order,1), IFNULL(is_active,1), NOW(), NOW()",
" FROM ", @src_db, ".pos_extra",
" ON DUPLICATE KEY UPDATE extra_name=VALUES(extra_name), selling_price=VALUES(selling_price), cost_amount=VALUES(cost_amount), is_active=VALUES(is_active), updated_at=NOW()"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- Validation checks
-- =========================================================
SELECT 'mst_uom' AS metric, COUNT(*) AS total FROM mst_uom
UNION ALL SELECT 'mst_uom_conversion', COUNT(*) FROM mst_uom_conversion
UNION ALL SELECT 'mst_operational_division', COUNT(*) FROM mst_operational_division
UNION ALL SELECT 'mst_product_division', COUNT(*) FROM mst_product_division
UNION ALL SELECT 'mst_product_classification', COUNT(*) FROM mst_product_classification
UNION ALL SELECT 'mst_product_category', COUNT(*) FROM mst_product_category
UNION ALL SELECT 'mst_component_category', COUNT(*) FROM mst_component_category
UNION ALL SELECT 'mst_item_category', COUNT(*) FROM mst_item_category
UNION ALL SELECT 'mst_extra', COUNT(*) FROM mst_extra;
