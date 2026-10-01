-- ============================================================
-- Fondasi Tahap 6: Purchase (PO + Snapshot Profile + Catalog)
-- Selaras dengan kontrak snapshot Tahap 2 (Gate Closed)
-- ============================================================
SET NAMES utf8mb4;

START TRANSACTION;

-- ============================================================
-- A. MASTER POSTING TYPE + PURCHASE TYPE
-- ============================================================
CREATE TABLE IF NOT EXISTS mst_posting_type (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  type_code VARCHAR(40) NOT NULL,
  type_name VARCHAR(120) NOT NULL,
  affects_inventory TINYINT(1) NOT NULL DEFAULT 0,
  affects_service TINYINT(1) NOT NULL DEFAULT 0,
  affects_asset TINYINT(1) NOT NULL DEFAULT 0,
  affects_payroll TINYINT(1) NOT NULL DEFAULT 0,
  affects_expense TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_posting_type_code (type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_purchase_type (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  type_code VARCHAR(40) NOT NULL,
  type_name VARCHAR(120) NOT NULL,
  posting_type_id BIGINT UNSIGNED NOT NULL,
  destination_behavior ENUM('REQUIRED','NONE') NOT NULL DEFAULT 'REQUIRED',
  default_destination ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_mst_purchase_type_code (type_code),
  KEY idx_mst_purchase_type_posting (posting_type_id),
  CONSTRAINT fk_mst_purchase_type_posting FOREIGN KEY (posting_type_id) REFERENCES mst_posting_type(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- B. PURCHASE ORDER HEADER
-- ============================================================
CREATE TABLE IF NOT EXISTS pur_purchase_order (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  po_no VARCHAR(50) NOT NULL,
  request_date DATE NOT NULL,
  expected_date DATE NULL,

  purchase_type_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  destination_division_id BIGINT UNSIGNED NULL,

  vendor_id BIGINT UNSIGNED NULL,

  status ENUM('DRAFT','APPROVED','ORDERED','REJECTED','PARTIAL_RECEIVED','RECEIVED','PAID','VOID') NOT NULL DEFAULT 'DRAFT',
  currency_code VARCHAR(10) NOT NULL DEFAULT 'IDR',
  exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1,

  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,

  external_ref_no VARCHAR(80) NULL,
  notes VARCHAR(255) NULL,

  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_pur_purchase_order_no (po_no),
  KEY idx_pur_purchase_order_date (request_date),
  KEY idx_pur_purchase_order_type (purchase_type_id),
  KEY idx_pur_purchase_order_vendor (vendor_id),
  KEY idx_pur_purchase_order_status (status),
  KEY idx_pur_purchase_order_destination_div (destination_division_id),

  CONSTRAINT fk_pur_purchase_order_type FOREIGN KEY (purchase_type_id) REFERENCES mst_purchase_type(id),
  CONSTRAINT fk_pur_purchase_order_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id),
  CONSTRAINT fk_pur_purchase_order_destination_div FOREIGN KEY (destination_division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- C. PURCHASE ORDER LINE (SNAPSHOT PROFILE)
-- ============================================================
CREATE TABLE IF NOT EXISTS pur_purchase_order_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  line_no INT UNSIGNED NOT NULL,

  line_kind ENUM('ITEM','MATERIAL','SERVICE','ASSET') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,

  line_description VARCHAR(255) NULL,
  brand_name VARCHAR(120) NULL,

  qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0,
  buy_uom_id BIGINT UNSIGNED NOT NULL,

  content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  qty_content DECIMAL(18,4) NOT NULL DEFAULT 0,
  content_uom_id BIGINT UNSIGNED NULL,
  conversion_factor_to_content DECIMAL(18,8) NOT NULL DEFAULT 1,

  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount_percent DECIMAL(9,4) NOT NULL DEFAULT 0,
  tax_percent DECIMAL(9,4) NOT NULL DEFAULT 0,
  line_subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,

  -- Snapshot fields (audit-safe)
  snapshot_item_name VARCHAR(150) NULL,
  snapshot_material_name VARCHAR(150) NULL,
  snapshot_brand_name VARCHAR(120) NULL,
  snapshot_line_description VARCHAR(255) NULL,
  snapshot_buy_uom_code VARCHAR(40) NULL,
  snapshot_content_uom_code VARCHAR(40) NULL,

  profile_key CHAR(64) NULL,

  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_pur_purchase_order_line_no (purchase_order_id, line_no),
  KEY idx_pur_purchase_order_line_po (purchase_order_id),
  KEY idx_pur_purchase_order_line_item (item_id),
  KEY idx_pur_purchase_order_line_material (material_id),
  KEY idx_pur_purchase_order_line_buy_uom (buy_uom_id),
  KEY idx_pur_purchase_order_line_content_uom (content_uom_id),
  KEY idx_pur_purchase_order_line_profile_key (profile_key),

  CONSTRAINT fk_pur_purchase_order_line_po FOREIGN KEY (purchase_order_id) REFERENCES pur_purchase_order(id),
  CONSTRAINT fk_pur_purchase_order_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_pur_purchase_order_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pur_purchase_order_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_purchase_order_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- D. PURCHASE CATALOG (PROFILE REUSE)
-- ============================================================
CREATE TABLE IF NOT EXISTS mst_purchase_catalog (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  profile_key CHAR(64) NOT NULL,

  line_kind ENUM('ITEM','MATERIAL','SERVICE','ASSET') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  vendor_id BIGINT UNSIGNED NOT NULL,

  catalog_name VARCHAR(150) NOT NULL,
  brand_name VARCHAR(120) NULL,
  line_description VARCHAR(255) NULL,

  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  conversion_factor_to_content DECIMAL(18,8) NOT NULL DEFAULT 1,

  standard_price DECIMAL(18,2) NULL,
  last_unit_price DECIMAL(18,2) NULL,
  last_purchase_date DATE NULL,

  last_purchase_order_id BIGINT UNSIGNED NULL,
  last_purchase_line_id BIGINT UNSIGNED NULL,

  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_mst_purchase_catalog_profile_key (profile_key),
  KEY idx_mst_purchase_catalog_vendor (vendor_id),
  KEY idx_mst_purchase_catalog_item (item_id),
  KEY idx_mst_purchase_catalog_material (material_id),
  KEY idx_mst_purchase_catalog_buy_uom (buy_uom_id),
  KEY idx_mst_purchase_catalog_content_uom (content_uom_id),
  KEY idx_mst_purchase_catalog_active (is_active),

  CONSTRAINT fk_mst_purchase_catalog_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id),
  CONSTRAINT fk_mst_purchase_catalog_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_mst_purchase_catalog_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_mst_purchase_catalog_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_mst_purchase_catalog_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_mst_purchase_catalog_last_po FOREIGN KEY (last_purchase_order_id) REFERENCES pur_purchase_order(id),
  CONSTRAINT fk_mst_purchase_catalog_last_line FOREIGN KEY (last_purchase_line_id) REFERENCES pur_purchase_order_line(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- E. SEED DATA POSTING TYPE + PURCHASE TYPE
-- ============================================================
INSERT INTO mst_posting_type (
  type_code,
  type_name,
  affects_inventory,
  affects_service,
  affects_asset,
  affects_payroll,
  affects_expense,
  sort_order,
  notes,
  is_active
) VALUES
('INVENTORY', 'Inventory Posting', 1, 0, 0, 0, 0, 10, 'Posting menambah/mengurangi stok inventori', 1),
('SERVICE', 'Service Posting', 0, 1, 0, 0, 0, 20, 'Posting untuk pembelian jasa', 1),
('ASSET', 'Asset Posting', 0, 0, 1, 0, 0, 30, 'Posting untuk pembelian aset', 1),
('PAYROLL', 'Payroll Posting', 0, 0, 0, 1, 0, 40, 'Posting terkait payroll', 1),
('EXPENSE', 'Expense Posting', 0, 0, 0, 0, 1, 50, 'Posting beban operasional', 1)
ON DUPLICATE KEY UPDATE
  type_name = VALUES(type_name),
  affects_inventory = VALUES(affects_inventory),
  affects_service = VALUES(affects_service),
  affects_asset = VALUES(affects_asset),
  affects_payroll = VALUES(affects_payroll),
  affects_expense = VALUES(affects_expense),
  sort_order = VALUES(sort_order),
  notes = VALUES(notes),
  is_active = VALUES(is_active);

INSERT INTO mst_purchase_type (
  type_code,
  type_name,
  posting_type_id,
  destination_behavior,
  default_destination,
  sort_order,
  notes,
  is_active
) 
SELECT
  seed.type_code,
  seed.type_name,
  pt.id,
  seed.destination_behavior,
  seed.default_destination,
  seed.sort_order,
  seed.notes,
  seed.is_active
FROM (
  SELECT 'INV_STOK' AS type_code, 'Inventory Stock' AS type_name, 'INVENTORY' AS posting_type_code, 'REQUIRED' AS destination_behavior, 'GUDANG' AS default_destination, 10 AS sort_order, 'Pembelian stok umum ke gudang' AS notes, 1 AS is_active
  UNION ALL SELECT 'INV_BAR', 'Inventory Bar', 'INVENTORY', 'REQUIRED', 'BAR', 20, 'Pembelian khusus kebutuhan bar', 1
  UNION ALL SELECT 'INV_KITCHEN', 'Inventory Kitchen', 'INVENTORY', 'REQUIRED', 'KITCHEN', 30, 'Pembelian khusus kebutuhan kitchen', 1
  UNION ALL SELECT 'JASA', 'Jasa/Service', 'SERVICE', 'NONE', NULL, 40, 'Pembelian jasa tanpa posting inventori', 1
  UNION ALL SELECT 'ASET', 'Asset', 'ASSET', 'NONE', NULL, 50, 'Pembelian aset', 1
  UNION ALL SELECT 'BEBAN', 'Beban Operasional', 'EXPENSE', 'NONE', NULL, 60, 'Pengeluaran operasional non inventori', 1
) seed
JOIN mst_posting_type pt ON pt.type_code = seed.posting_type_code
ON DUPLICATE KEY UPDATE
  type_name = VALUES(type_name),
  posting_type_id = VALUES(posting_type_id),
  destination_behavior = VALUES(destination_behavior),
  default_destination = VALUES(default_destination),
  sort_order = VALUES(sort_order),
  notes = VALUES(notes),
  is_active = VALUES(is_active);

COMMIT;

SELECT 'mst_posting_type' AS table_name, COUNT(*) AS total_rows FROM mst_posting_type
UNION ALL
SELECT 'mst_purchase_type', COUNT(*) FROM mst_purchase_type
UNION ALL
SELECT 'pur_purchase_order', COUNT(*) FROM pur_purchase_order
UNION ALL
SELECT 'pur_purchase_order_line', COUNT(*) FROM pur_purchase_order_line
UNION ALL
SELECT 'mst_purchase_catalog', COUNT(*) FROM mst_purchase_catalog;
