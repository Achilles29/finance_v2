SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29f_pos_sales_channel_master.sql
-- Tujuan :
-- 1) Menambah master sales channel / order source POS
-- 2) Memisahkan service_type dari channel penjualan
-- 3) Menyiapkan channel seperti Walk In, GrabFood, ShopeeFood, dst
-- Catatan:
-- - Aman dijalankan ulang.
-- - service_type tetap dipakai untuk cara layanan.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_sales_channel (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel_code VARCHAR(40) NOT NULL,
  channel_name VARCHAR(120) NOT NULL,
  service_type_default ENUM('DINE_IN','TAKE_AWAY','DELIVERY','PICKUP') NOT NULL DEFAULT 'DINE_IN',
  allowed_service_types VARCHAR(120) NULL,
  marketplace_fee_percent DECIMAL(8,4) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_sales_channel_code (channel_code),
  KEY idx_pos_sales_channel_active_sort (is_active, sort_order, channel_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_sales_channel_allowed_types := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_sales_channel'
    AND COLUMN_NAME = 'allowed_service_types'
);
SET @sql_add_sales_channel_allowed_types := IF(
  @has_sales_channel_allowed_types = 0,
  'ALTER TABLE pos_sales_channel ADD COLUMN allowed_service_types VARCHAR(120) NULL AFTER service_type_default',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_sales_channel_allowed_types; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_order_sales_channel_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
    AND COLUMN_NAME = 'sales_channel_id'
);
SET @sql_add_order_sales_channel_id := IF(
  @has_order_sales_channel_id = 0,
  'ALTER TABLE pos_order ADD COLUMN sales_channel_id BIGINT UNSIGNED NULL AFTER service_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_order_sales_channel_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_order_sales_channel := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
    AND INDEX_NAME = 'idx_pos_order_sales_channel'
);
SET @sql_add_idx_order_sales_channel := IF(
  @has_idx_order_sales_channel = 0,
  'ALTER TABLE pos_order ADD KEY idx_pos_order_sales_channel (sales_channel_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_idx_order_sales_channel; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_order_sales_channel := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
    AND CONSTRAINT_NAME = 'fk_pos_order_sales_channel'
);
SET @sql_add_fk_order_sales_channel := IF(
  @has_fk_order_sales_channel = 0,
  'ALTER TABLE pos_order ADD CONSTRAINT fk_pos_order_sales_channel FOREIGN KEY (sales_channel_id) REFERENCES pos_sales_channel(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_fk_order_sales_channel; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO pos_sales_channel (channel_code, channel_name, service_type_default, allowed_service_types, marketplace_fee_percent, is_default, is_active, sort_order, notes)
VALUES
  ('WALK_IN', 'Walk In', 'DINE_IN', 'DINE_IN,TAKE_AWAY', 0, 1, 1, 10, 'Default transaksi kasir langsung.'),
  ('WHATSAPP', 'WhatsApp', 'PICKUP', 'PICKUP,DELIVERY,TAKE_AWAY', 0, 0, 1, 20, 'Pesanan masuk dari WhatsApp.'),
  ('GRABFOOD', 'GrabFood', 'DELIVERY', 'DELIVERY,PICKUP', 20, 0, 1, 30, 'Marketplace delivery GrabFood.'),
  ('SHOPEEFOOD', 'ShopeeFood', 'DELIVERY', 'DELIVERY,PICKUP', 20, 0, 1, 40, 'Marketplace delivery ShopeeFood.'),
  ('GOFOOD', 'GoFood', 'DELIVERY', 'DELIVERY,PICKUP', 20, 0, 1, 50, 'Marketplace delivery GoFood.'),
  ('PHONE', 'Phone Order', 'PICKUP', 'PICKUP,DELIVERY', 0, 0, 1, 60, 'Pesanan melalui telepon.')
ON DUPLICATE KEY UPDATE
  channel_name = VALUES(channel_name),
  service_type_default = VALUES(service_type_default),
  allowed_service_types = VALUES(allowed_service_types),
  marketplace_fee_percent = VALUES(marketplace_fee_percent),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

UPDATE pos_order o
JOIN (
  SELECT id
  FROM pos_sales_channel
  WHERE channel_code = 'WALK_IN'
  ORDER BY is_default DESC, sort_order ASC, id ASC
  LIMIT 1
) sc ON 1 = 1
SET o.sales_channel_id = sc.id
WHERE o.sales_channel_id IS NULL;

COMMIT;

SELECT 'pos_sales_channel.rows' AS metric, COUNT(*) AS total_rows
FROM pos_sales_channel
UNION ALL
SELECT 'pos_order.sales_channel_id.not_null', COUNT(*)
FROM pos_order
WHERE sales_channel_id IS NOT NULL;
