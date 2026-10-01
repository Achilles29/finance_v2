SET NAMES utf8mb4;

-- ============================================================
-- 2026-07-09a - Modul Mutasi Bahan Baku Antar Divisi / Lokasi
-- 1) Header + line dokumen transfer bahan baku divisi
-- 2) Page permission + sidebar menu stok divisi
-- ============================================================
START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_stock_transfer (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  transfer_no VARCHAR(60) NOT NULL,
  transfer_date DATE NOT NULL,
  from_division_id BIGINT UNSIGNED NOT NULL,
  from_destination_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL,
  to_division_id BIGINT UNSIGNED NOT NULL,
  to_destination_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  posted_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_stock_transfer_no (transfer_no),
  KEY idx_inv_stock_transfer_date (transfer_date),
  KEY idx_inv_stock_transfer_status (status),
  KEY idx_inv_stock_transfer_from (from_division_id, from_destination_type),
  KEY idx_inv_stock_transfer_to (to_division_id, to_destination_type),
  CONSTRAINT fk_inv_stock_transfer_from_division FOREIGN KEY (from_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_inv_stock_transfer_to_division FOREIGN KEY (to_division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_transfer_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  transfer_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NOT NULL,
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
  available_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  available_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  target_existing_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  qty_transfer_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  transfer_issue_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_stock_transfer_line_no (transfer_id, line_no),
  KEY idx_inv_stock_transfer_line_item (item_id),
  KEY idx_inv_stock_transfer_line_material (material_id),
  KEY idx_inv_stock_transfer_line_profile (profile_key),
  KEY idx_inv_stock_transfer_line_issue (transfer_issue_id),
  CONSTRAINT fk_inv_stock_transfer_line_transfer FOREIGN KEY (transfer_id) REFERENCES inv_stock_transfer(id),
  CONSTRAINT fk_inv_stock_transfer_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_stock_transfer_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_stock_transfer_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_stock_transfer_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('purchase.stock.transfer.division.index', 'Purchase Transfer Divisi', 'PURCHASE', 'Input dan monitoring mutasi bahan baku antar divisi dan lokasi', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.transfer.division',
  'Mutasi Bahan Baku',
  'ri-share-forward-2-line',
  '/inventory/stock/transfer/division',
  p.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.transfer.division.index'
WHERE parent.menu_code = 'inventory.stock.group.division'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  arp.role_id,
  target.id,
  arp.can_view,
  arp.can_create,
  arp.can_edit,
  arp.can_delete,
  arp.can_export,
  NOW()
FROM sys_page source
JOIN auth_role_permission arp ON arp.page_id = source.id
JOIN sys_page target ON target.page_code = 'purchase.stock.transfer.division.index'
WHERE source.page_code = 'purchase.stock.adjustment.division.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT page_code, page_name
FROM sys_page
WHERE page_code = 'purchase.stock.transfer.division.index';
