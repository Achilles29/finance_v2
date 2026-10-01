SET NAMES utf8mb4;

-- ============================================================
-- Tahap 23A - Modul Penyesuaian Stok Gudang & Divisi
-- 1) Dokumen header/line adjustment inventory
-- 2) Page permission + sidebar menu scope gudang/divisi
-- 3) Penyesuaian plus membentuk lot inbound, minus memakai FIFO lot
-- ============================================================
START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_stock_adjustment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  adjustment_no VARCHAR(60) NOT NULL,
  adjustment_date DATE NOT NULL,
  stock_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
  division_id BIGINT UNSIGNED NULL,
  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_stock_adjustment_no (adjustment_no),
  KEY idx_inv_stock_adjustment_scope_date (stock_scope, adjustment_date),
  KEY idx_inv_stock_adjustment_division (division_id, destination_type),
  KEY idx_inv_stock_adjustment_status (status),
  CONSTRAINT fk_inv_stock_adjustment_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_adjustment_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  adjustment_id BIGINT UNSIGNED NOT NULL,
  line_no INT NOT NULL,
  stock_domain ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
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
  available_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  available_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  qty_waste_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_spoil_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_process_loss_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_variance_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  qty_adjustment_plus_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  inbound_lot_no VARCHAR(80) NULL,
  inbound_expiry_date DATE NULL,
  note VARCHAR(255) NULL,
  waste_issue_id BIGINT UNSIGNED NULL,
  spoil_issue_id BIGINT UNSIGNED NULL,
  process_loss_issue_id BIGINT UNSIGNED NULL,
  variance_issue_id BIGINT UNSIGNED NULL,
  adjustment_plus_lot_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_inv_stock_adjustment_line_no (adjustment_id, line_no),
  KEY idx_inv_stock_adjustment_line_item (item_id),
  KEY idx_inv_stock_adjustment_line_material (material_id),
  KEY idx_inv_stock_adjustment_line_profile (profile_key),
  KEY idx_inv_stock_adjustment_line_waste_issue (waste_issue_id),
  KEY idx_inv_stock_adjustment_line_spoil_issue (spoil_issue_id),
  KEY idx_inv_stock_adjustment_line_process_loss_issue (process_loss_issue_id),
  KEY idx_inv_stock_adjustment_line_variance_issue (variance_issue_id),
  KEY idx_inv_stock_adjustment_line_lot (adjustment_plus_lot_id),
  CONSTRAINT fk_inv_stock_adjustment_line_adjustment FOREIGN KEY (adjustment_id) REFERENCES inv_stock_adjustment(id),
  CONSTRAINT fk_inv_stock_adjustment_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_inv_stock_adjustment_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_inv_stock_adjustment_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_inv_stock_adjustment_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('purchase.stock.adjustment.warehouse.index', 'Purchase Adjustment Gudang', 'PURCHASE', 'Input dan monitoring penyesuaian stok gudang', 1),
  ('purchase.stock.adjustment.division.index', 'Purchase Adjustment Divisi', 'PURCHASE', 'Input dan monitoring penyesuaian stok bahan baku divisi', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.adjustment.warehouse',
  'Adjustment Gudang',
  'ri-scales-3-line',
  '/inventory/stock/adjustment/warehouse',
  p.id,
  3,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.adjustment.warehouse.index'
WHERE parent.menu_code = 'inventory.stock.group.warehouse'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'purchase.stock.adjustment.division',
  'Adjustment Divisi',
  'ri-scales-3-line',
  '/inventory/stock/adjustment/division',
  p.id,
  3,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'purchase.stock.adjustment.division.index'
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
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN') THEN 1 ELSE 0 END,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.stock.adjustment.warehouse.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN', 'ADM_GDG', 'ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN', 'ADMIN') THEN 1 ELSE 0 END,
  1,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'purchase.stock.adjustment.division.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_GDG', 'ADM_FIN')
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
WHERE page_code IN (
  'purchase.stock.adjustment.warehouse.index',
  'purchase.stock.adjustment.division.index'
)
ORDER BY page_code;