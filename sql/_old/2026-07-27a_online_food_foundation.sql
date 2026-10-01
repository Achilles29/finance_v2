SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-27a_online_food_foundation.sql
-- Tujuan :
-- 1) Menambahkan harga dan visibility Online Food di mst_product
-- 2) Menyiapkan pengaturan buka/tutup, jadwal, pembayaran, dan ongkir Online Food
-- 3) Menambahkan menu + permission Online Food di POS finance
-- ============================================================

START TRANSACTION;

ALTER TABLE mst_product
  ADD COLUMN IF NOT EXISTS online_food_price DECIMAL(18,2) NULL AFTER selling_price,
  ADD COLUMN IF NOT EXISTS show_online_food TINYINT(1) NOT NULL DEFAULT 0 AFTER show_member,
  ADD KEY IF NOT EXISTS idx_mst_product_online_food (show_online_food, is_active);

UPDATE mst_product
SET
  online_food_price = COALESCE(NULLIF(online_food_price, 0), selling_price),
  show_online_food = CASE
    WHEN show_online_food = 1 THEN 1
    WHEN COALESCE(show_pos, 0) = 1 THEN 1
    ELSE show_online_food
  END
WHERE is_active = 1;

CREATE TABLE IF NOT EXISTS pos_online_food_setting (
  id TINYINT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  open_mode ENUM('MANUAL','SCHEDULE') NOT NULL DEFAULT 'MANUAL',
  manual_status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
  open_time TIME NULL DEFAULT '08:00:00',
  close_time TIME NULL DEFAULT '22:00:00',
  schedule_days VARCHAR(32) NOT NULL DEFAULT '1,2,3,4,5,6,0',
  allow_cod TINYINT(1) NOT NULL DEFAULT 1,
  allow_qris TINYINT(1) NOT NULL DEFAULT 0,
  qris_payment_method_id BIGINT UNSIGNED NULL,
  delivery_fee_mode ENUM('FLAT','DISTANCE') NOT NULL DEFAULT 'DISTANCE',
  delivery_flat_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
  delivery_base_fee DECIMAL(18,2) NOT NULL DEFAULT 5000,
  delivery_base_km DECIMAL(10,2) NOT NULL DEFAULT 2,
  delivery_per_km_fee DECIMAL(18,2) NOT NULL DEFAULT 2500,
  delivery_min_fee DECIMAL(18,2) NOT NULL DEFAULT 5000,
  delivery_max_distance_km DECIMAL(10,2) NOT NULL DEFAULT 10,
  free_delivery_min_order DECIMAL(18,2) NOT NULL DEFAULT 0,
  packaging_fee_default DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_order_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  outlet_lat DECIMAL(10,7) NULL,
  outlet_lng DECIMAL(10,7) NULL,
  member_base_url VARCHAR(255) NOT NULL DEFAULT 'http://localhost/member/',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_online_food_setting_qris (qris_payment_method_id),
  CONSTRAINT fk_pos_online_food_setting_qris_method
    FOREIGN KEY (qris_payment_method_id) REFERENCES pos_payment_method(id)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pos_online_food_setting (
  id, is_enabled, open_mode, manual_status, timezone, open_time, close_time,
  schedule_days, allow_cod, allow_qris, member_base_url
)
VALUES (
  1, 1, 'MANUAL', 'OPEN', 'Asia/Jakarta', '08:00:00', '22:00:00',
  '1,2,3,4,5,6,0', 1, 0, 'http://localhost/member/'
)
ON DUPLICATE KEY UPDATE
  is_enabled = is_enabled;

UPDATE pos_online_food_setting
SET allow_qris = 0
WHERE id = 1
  AND qris_payment_method_id IS NULL;

CREATE TABLE IF NOT EXISTS pos_online_food_payment_method (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  payment_method_id BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_online_food_payment_method (setting_id, payment_method_id),
  KEY idx_pos_online_food_payment_method_active (is_active),
  CONSTRAINT fk_pos_online_food_payment_method_setting
    FOREIGN KEY (setting_id) REFERENCES pos_online_food_setting(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_pos_online_food_payment_method_method
    FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pos_online_food_payment_method (setting_id, payment_method_id, is_active)
SELECT 1, pm.id, 1
FROM pos_payment_method pm
WHERE pm.is_active = 1
  AND pm.method_type IN ('CASH','BANK','EWALLET','QRIS','OTHER')
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

INSERT INTO pos_sales_channel (channel_code, channel_name, service_type_default, allowed_service_types, sort_order, is_active)
VALUES ('ONLINE_FOOD', 'Online Food', 'DELIVERY', 'DELIVERY', 40, 1)
ON DUPLICATE KEY UPDATE
  channel_name = VALUES(channel_name),
  service_type_default = VALUES(service_type_default),
  allowed_service_types = VALUES(allowed_service_types),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.online_food.index',
  'Online Food POS',
  'POS',
  'Pengaturan dan monitoring order online food dari aplikasi member',
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
  'pos.online_food',
  'Online Food',
  'ri-takeaway-line',
  '/pos/online-food',
  p.id,
  8,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.pos'
WHERE p.page_code = 'pos.online_food.index'
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
  'pos.online_food.settings',
  'Settings Online Food',
  'ri-settings-3-line',
  '/pos/online-food/settings',
  p.id,
  1,
  1,
  'MAIN',
  m.id
FROM sys_page p
JOIN sys_menu m ON m.menu_code = 'pos.online_food'
WHERE p.page_code = 'pos.online_food.index'
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
  1, 1, 1, 1, 0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'pos.online_food.index'
WHERE r.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'KASIR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'mst_product.online_food' AS seed_key, COUNT(*) AS total_rows
FROM mst_product
WHERE COALESCE(show_online_food, 0) = 1
UNION ALL
SELECT 'pos_online_food_setting', COUNT(*) FROM pos_online_food_setting
UNION ALL
SELECT 'pos_online_food_payment_method', COUNT(*) FROM pos_online_food_payment_method
UNION ALL
SELECT 'sys_page.pos.online_food.index', COUNT(*) FROM sys_page WHERE page_code = 'pos.online_food.index'
UNION ALL
SELECT 'sys_menu.pos.online_food', COUNT(*) FROM sys_menu WHERE menu_code = 'pos.online_food';
