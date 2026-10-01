SET NAMES utf8mb4;

-- ============================================================
-- Tahap 9A3 - POS Payment Method / Bundle / Availability
-- File   : 2026-05-28a3_pos_payment_bundle_availability_foundation.sql
-- Tujuan :
-- 1) Menyiapkan metode pembayaran POS
-- 2) Menyiapkan produk paket
-- 3) Menyiapkan cache availability dan override produk POS
-- Catatan:
-- - Dipisah dari draft monolith agar eksekusi per domain lebih aman.
-- - Aman direview dan dijalankan bertahap sesuai dependency modul POS.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- C. Payment Method
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_payment_method (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  method_code VARCHAR(40) NOT NULL,
  method_name VARCHAR(120) NOT NULL,
  method_type ENUM('CASH','BANK','EWALLET','QRIS','COMPLIMENT','DEPOSIT','OTHER') NOT NULL DEFAULT 'CASH',
  company_account_id BIGINT UNSIGNED NULL,
  allows_change TINYINT(1) NOT NULL DEFAULT 0,
  requires_reference_no TINYINT(1) NOT NULL DEFAULT 0,
  show_in_cashier TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_payment_method_code (method_code),
  KEY idx_pos_payment_method_account (company_account_id),
  KEY idx_pos_payment_method_type (method_type),
  CONSTRAINT fk_pos_payment_method_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- D. Bundle Produk POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_product_bundle (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bundle_code VARCHAR(40) NOT NULL,
  bundle_name VARCHAR(150) NOT NULL,
  product_division_id BIGINT UNSIGNED NULL,
  pos_scope ENUM('REGULAR','EVENT','ALL') NOT NULL DEFAULT 'REGULAR',
  selling_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_bundle_code (bundle_code),
  KEY idx_pos_product_bundle_division (product_division_id),
  CONSTRAINT fk_pos_product_bundle_division FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_product_bundle_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  bundle_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(18,4) NOT NULL DEFAULT 1,
  unit_price_override DECIMAL(18,2) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_bundle_line_product (bundle_id, product_id),
  KEY idx_pos_product_bundle_line_product (product_id),
  CONSTRAINT fk_pos_product_bundle_line_bundle FOREIGN KEY (bundle_id) REFERENCES pos_product_bundle(id),
  CONSTRAINT fk_pos_product_bundle_line_product FOREIGN KEY (product_id) REFERENCES mst_product(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- E. Availability Produk POS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pos_product_availability_override (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  override_mode ENUM('AUTO','FORCE_AVAILABLE','FORCE_OUT') NOT NULL DEFAULT 'AUTO',
  override_note VARCHAR(255) NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_availability_override (outlet_id, product_id),
  KEY idx_pos_product_availability_override_mode (override_mode),
  CONSTRAINT fk_pos_prod_avail_override_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_prod_avail_override_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_prod_avail_override_created_by FOREIGN KEY (created_by) REFERENCES org_employee(id),
  CONSTRAINT fk_pos_prod_avail_override_updated_by FOREIGN KEY (updated_by) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_product_availability_cache (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  availability_status ENUM('AVAILABLE','LIMITED','OUT','HIDDEN') NOT NULL DEFAULT 'AVAILABLE',
  source_mode ENUM('AUTO','OVERRIDE_AVAILABLE','OVERRIDE_OUT') NOT NULL DEFAULT 'AUTO',
  estimated_available_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  uom_id BIGINT UNSIGNED NULL,
  bottleneck_kind ENUM('NONE','MATERIAL','COMPONENT') NOT NULL DEFAULT 'NONE',
  bottleneck_material_id BIGINT UNSIGNED NULL,
  bottleneck_component_id BIGINT UNSIGNED NULL,
  bottleneck_name_snapshot VARCHAR(150) NULL,
  main_missing_count INT UNSIGNED NOT NULL DEFAULT 0,
  optional_missing_count INT UNSIGNED NOT NULL DEFAULT 0,
  override_allowed TINYINT(1) NOT NULL DEFAULT 0,
  hpp_live_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  stock_reference_at DATETIME NULL,
  last_commit_event VARCHAR(50) NULL,
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_dirty TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pos_product_availability_cache (outlet_id, product_id),
  KEY idx_pos_product_availability_status (availability_status),
  CONSTRAINT fk_pos_prod_avail_cache_outlet FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_prod_avail_cache_product FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_prod_avail_cache_uom FOREIGN KEY (uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pos_prod_avail_cache_material FOREIGN KEY (bottleneck_material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pos_prod_avail_cache_component FOREIGN KEY (bottleneck_component_id) REFERENCES mst_component(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- Quick check
SELECT 'pos_payment_method' AS table_name, COUNT(*) AS total_rows FROM pos_payment_method
UNION ALL
SELECT 'pos_product_bundle', COUNT(*) FROM pos_product_bundle
UNION ALL
SELECT 'pos_product_availability_override', COUNT(*) FROM pos_product_availability_override
UNION ALL
SELECT 'pos_product_availability_cache', COUNT(*) FROM pos_product_availability_cache;
