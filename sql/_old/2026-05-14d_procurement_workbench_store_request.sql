SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- A) STORE REQUEST (untuk proses fulfillment gudang oleh purchasing)
-- =========================================================
CREATE TABLE IF NOT EXISTS pur_store_request (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  sr_no VARCHAR(50) NOT NULL,
  request_date DATE NOT NULL,
  needed_date DATE NULL,
  request_division_id BIGINT UNSIGNED NOT NULL,
  destination_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER',
  status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','PARTIAL_FULFILLED','FULFILLED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  voided_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_store_request_no (sr_no),
  KEY idx_pur_store_request_date (request_date),
  KEY idx_pur_store_request_status (status),
  KEY idx_pur_store_request_div_dest (request_division_id, destination_type),
  CONSTRAINT fk_pur_store_request_division FOREIGN KEY (request_division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_pur_store_request_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pur_store_request_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pur_store_request_voided_by FOREIGN KEY (voided_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_store_request_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  store_request_id BIGINT UNSIGNED NOT NULL,
  line_no INT UNSIGNED NOT NULL,
  line_kind ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NOT NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  qty_buy_requested DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_requested DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_buy_approved DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_approved DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_buy_fulfilled DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_fulfilled DECIMAL(18,4) NOT NULL DEFAULT 0,
  line_status ENUM('OPEN','PARTIAL','DONE','CANCELLED') NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_store_request_line_no (store_request_id, line_no),
  KEY idx_pur_store_request_line_item (item_id),
  KEY idx_pur_store_request_line_material (material_id),
  KEY idx_pur_store_request_line_profile_uom (profile_key, buy_uom_id, content_uom_id),
  CONSTRAINT fk_pur_store_request_line_header FOREIGN KEY (store_request_id) REFERENCES pur_store_request(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_store_request_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_pur_store_request_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pur_store_request_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_store_request_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_store_request_approval (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  store_request_id BIGINT UNSIGNED NOT NULL,
  action ENUM('SUBMIT','APPROVE','REJECT','OVERRIDE_APPROVE','VOID') NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name_snapshot VARCHAR(150) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pur_store_request_approval_req_time (store_request_id, created_at),
  CONSTRAINT fk_pur_store_request_approval_header FOREIGN KEY (store_request_id) REFERENCES pur_store_request(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_store_request_approval_actor FOREIGN KEY (actor_user_id) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_store_request_fulfillment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  store_request_id BIGINT UNSIGNED NOT NULL,
  fulfillment_no VARCHAR(60) NOT NULL,
  fulfillment_date DATE NOT NULL,
  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  posted_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  voided_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_store_request_fulfillment_no (fulfillment_no),
  KEY idx_pur_store_request_fulfillment_req (store_request_id),
  CONSTRAINT fk_pur_store_request_fulfillment_header FOREIGN KEY (store_request_id) REFERENCES pur_store_request(id),
  CONSTRAINT fk_pur_store_request_fulfillment_posted_by FOREIGN KEY (posted_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pur_store_request_fulfillment_voided_by FOREIGN KEY (voided_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_store_request_fulfillment_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  fulfillment_id BIGINT UNSIGNED NOT NULL,
  store_request_line_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NOT NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  qty_buy_posted DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_posted DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_cost_snapshot DECIMAL(18,6) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_sr_fulfillment_line (fulfillment_id, store_request_line_id),
  KEY idx_pur_sr_fulfillment_line_profile (profile_key, buy_uom_id, content_uom_id),
  CONSTRAINT fk_pur_sr_fulfillment_line_header FOREIGN KEY (fulfillment_id) REFERENCES pur_store_request_fulfillment(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_sr_fulfillment_line_sr_line FOREIGN KEY (store_request_line_id) REFERENCES pur_store_request_line(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_sr_fulfillment_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_pur_sr_fulfillment_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pur_sr_fulfillment_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_sr_fulfillment_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- B) PENGAJUAN PO/SR OLEH DIVISI (auto split stok)
-- =========================================================
CREATE TABLE IF NOT EXISTS pur_division_request (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  request_no VARCHAR(60) NOT NULL,
  request_date DATE NOT NULL,
  needed_date DATE NULL,
  division_id BIGINT UNSIGNED NOT NULL,
  status ENUM('DRAFT','SUBMITTED','VERIFIED','REJECTED','VOID') NOT NULL DEFAULT 'SUBMITTED',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_division_request_no (request_no),
  KEY idx_pur_division_request_date (request_date),
  KEY idx_pur_division_request_status (status),
  KEY idx_pur_division_request_div (division_id),
  CONSTRAINT fk_pur_division_request_div FOREIGN KEY (division_id) REFERENCES mst_operational_division(id),
  CONSTRAINT fk_pur_division_request_user FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_division_request_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  line_no INT UNSIGNED NOT NULL,
  line_kind ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NOT NULL,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  buy_uom_id BIGINT UNSIGNED NOT NULL,
  content_uom_id BIGINT UNSIGNED NOT NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1,
  profile_buy_uom_code VARCHAR(40) NULL,
  profile_content_uom_code VARCHAR(40) NULL,
  request_uom_mode ENUM('BUY','CONTENT') NOT NULL DEFAULT 'BUY',
  vendor_id BIGINT UNSIGNED NULL,
  qty_buy_requested DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_requested DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_available_snapshot DECIMAL(18,4) NOT NULL DEFAULT 0,
  routed_to ENUM('SR','PO','MIXED') NOT NULL DEFAULT 'PO',
  qty_content_to_sr DECIMAL(18,4) NOT NULL DEFAULT 0,
  qty_content_to_po DECIMAL(18,4) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pur_division_request_line_no (request_id, line_no),
  KEY idx_pur_division_request_line_item (item_id),
  KEY idx_pur_division_request_line_material (material_id),
  CONSTRAINT fk_pur_division_request_line_header FOREIGN KEY (request_id) REFERENCES pur_division_request(id) ON DELETE CASCADE,
  CONSTRAINT fk_pur_division_request_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_pur_division_request_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pur_division_request_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_division_request_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_division_request_line_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_division_request_link (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  doc_type ENUM('SR','PO') NOT NULL,
  doc_id BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pur_div_req_link_req (request_id),
  KEY idx_pur_div_req_link_doc (doc_type, doc_id),
  CONSTRAINT fk_pur_div_req_link_req FOREIGN KEY (request_id) REFERENCES pur_division_request(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- C) PAGE / MENU / PERMISSION
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('procurement.workbench.index', 'Procurement API Access', 'PURCHASE', 'Endpoint permission base procurement', 1),
  ('procurement.division.index', 'PO/SR Divisi', 'PURCHASE', 'Pengajuan kebutuhan divisi auto route ke SR/PO', 1),
  ('procurement.store_request.index', 'Store Request', 'PURCHASE', 'Verifikasi SR + fulfillment + shortage ke PO', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- nonaktifkan menu workbench lama jika sempat pernah dibuat
UPDATE sys_menu SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE menu_code = 'procurement.workbench';

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id,
  src.sort_order,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT 'procurement.division' AS menu_code, 'PO / SR Divisi' AS menu_label, 'ri-inbox-line' AS icon, '/procurement/division-po-sr' AS url, 'procurement.division.index' AS page_code, 9 AS sort_order, 'grp.purchase' AS parent_code
  UNION ALL
  SELECT 'procurement.store-request', 'Store Request', 'ri-inbox-archive-line', '/store-requests', 'procurement.store_request.index', 10, 'grp.purchase'
) src
JOIN sys_menu parent ON parent.menu_code = src.parent_code
JOIN sys_page p ON p.page_code = src.page_code
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT
  r.id,
  p.id,
  1,
  CASE
    WHEN p.page_code = 'procurement.division.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','STAFF','BARISTA','CHEF') THEN 1
    WHEN p.page_code = 'procurement.store_request.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1
    WHEN p.page_code = 'procurement.workbench.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1
    ELSE 0
  END AS can_create,
  CASE
    WHEN p.page_code = 'procurement.division.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1
    WHEN p.page_code = 'procurement.store_request.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1
    WHEN p.page_code = 'procurement.workbench.index' AND r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG') THEN 1
    ELSE 0
  END AS can_edit,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADMIN') THEN 1 ELSE 0 END AS can_delete,
  1,
  NOW(),
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('procurement.workbench.index','procurement.division.index','procurement.store_request.index')
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','ADM_GDG','ADM_FIN','STAFF','BARISTA','CHEF')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

-- nonaktifkan menu lama jika sempat terbuat
UPDATE sys_menu
SET is_active = 0, updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'procurement.purchasing';

COMMIT;
