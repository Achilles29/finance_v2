SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-28b_online_food_delivery_separate_table.sql
-- Tujuan :
-- 1) Memisahkan ongkir Online Food dari pos_order agar tidak menjadi sales POS
-- 2) Menyediakan tabel alamat/lokasi tersimpan member
-- 3) Menambahkan aturan gratis ongkir jarak dekat dan mode penagihan ongkir
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_online_food_setting
  ADD COLUMN IF NOT EXISTS delivery_fee_charge_mode ENUM('CUSTOMER_TO_DRIVER','RECORD_ONLY','MERCHANT_COLLECT') NOT NULL DEFAULT 'CUSTOMER_TO_DRIVER' AFTER delivery_fee_mode,
  ADD COLUMN IF NOT EXISTS free_delivery_distance_km DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER free_delivery_min_order;

CREATE TABLE IF NOT EXISTS crm_member_delivery_location (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL DEFAULT 'Rumah',
  recipient_name VARCHAR(150) NULL,
  recipient_phone VARCHAR(32) NULL,
  address VARCHAR(255) NOT NULL,
  address_note VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  location_accuracy DECIMAL(10,2) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  free_delivery_enabled TINYINT(1) NOT NULL DEFAULT 0,
  free_delivery_reason VARCHAR(120) NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_crm_member_delivery_location_member (member_id, is_default),
  KEY idx_crm_member_delivery_location_latlng (latitude, longitude),
  CONSTRAINT fk_crm_member_delivery_location_member
    FOREIGN KEY (member_id) REFERENCES crm_member(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_online_food_delivery_order (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NULL,
  saved_location_id BIGINT UNSIGNED NULL,
  delivery_status ENUM('PENDING','ASSIGNED','PICKED_UP','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  delivery_provider ENUM('OJEK_ONLINE','INTERNAL','OTHER') NOT NULL DEFAULT 'OJEK_ONLINE',
  fee_charge_mode ENUM('CUSTOMER_TO_DRIVER','RECORD_ONLY','MERCHANT_COLLECT') NOT NULL DEFAULT 'CUSTOMER_TO_DRIVER',
  fee_paid_by ENUM('CUSTOMER','MERCHANT','FREE') NOT NULL DEFAULT 'CUSTOMER',
  fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  estimated_fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  distance_km DECIMAL(10,3) NULL,
  straight_distance_km DECIMAL(10,3) NULL,
  route_distance_km DECIMAL(10,3) NULL,
  duration_min DECIMAL(10,2) NULL,
  route_source VARCHAR(30) NULL,
  recipient_name VARCHAR(150) NULL,
  recipient_phone VARCHAR(32) NULL,
  delivery_address VARCHAR(255) NULL,
  address_note VARCHAR(255) NULL,
  customer_lat DECIMAL(10,7) NULL,
  customer_lng DECIMAL(10,7) NULL,
  customer_location_accuracy DECIMAL(10,2) NULL,
  free_reason VARCHAR(120) NULL,
  courier_ref VARCHAR(80) NULL,
  courier_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_online_food_delivery_order (order_id),
  KEY idx_pos_online_food_delivery_order_member (member_id),
  KEY idx_pos_online_food_delivery_order_location (customer_lat, customer_lng),
  KEY idx_pos_online_food_delivery_order_saved_location (saved_location_id),
  CONSTRAINT fk_pos_online_food_delivery_order_order
    FOREIGN KEY (order_id) REFERENCES pos_order(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_pos_online_food_delivery_order_member
    FOREIGN KEY (member_id) REFERENCES crm_member(id)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT fk_pos_online_food_delivery_order_saved_location
    FOREIGN KEY (saved_location_id) REFERENCES crm_member_delivery_location(id)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_pos_order_delivery_cols = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_order'
    AND COLUMN_NAME IN ('delivery_fee_amount', 'customer_lat', 'customer_lng', 'delivery_address')
);

SET @backfill_delivery_sql = IF(
  @has_pos_order_delivery_cols >= 4,
  "INSERT INTO pos_online_food_delivery_order (
    order_id, member_id, fee_amount, estimated_fee_amount, distance_km,
    straight_distance_km, route_distance_km, duration_min, delivery_address,
    customer_lat, customer_lng, customer_location_accuracy, route_source
  )
  SELECT
    o.id,
    o.member_id,
    COALESCE(o.delivery_fee_amount, 0),
    COALESCE(o.delivery_fee_amount, 0),
    o.delivery_distance_km,
    o.delivery_distance_km,
    o.delivery_route_distance_km,
    o.delivery_duration_min,
    o.delivery_address,
    o.customer_lat,
    o.customer_lng,
    o.customer_location_accuracy,
    CASE WHEN o.delivery_route_distance_km IS NOT NULL THEN 'ROUTE' ELSE 'HAVERSINE' END
  FROM pos_order o
  WHERE o.order_channel = 'DELIVERY'
    AND (
      COALESCE(o.delivery_fee_amount, 0) <> 0
      OR o.customer_lat IS NOT NULL
      OR o.customer_lng IS NOT NULL
      OR o.delivery_address IS NOT NULL
    )
  ON DUPLICATE KEY UPDATE
    member_id = VALUES(member_id),
    fee_amount = VALUES(fee_amount),
    estimated_fee_amount = VALUES(estimated_fee_amount),
    distance_km = VALUES(distance_km),
    straight_distance_km = VALUES(straight_distance_km),
    route_distance_km = VALUES(route_distance_km),
    duration_min = VALUES(duration_min),
    delivery_address = VALUES(delivery_address),
    customer_lat = VALUES(customer_lat),
    customer_lng = VALUES(customer_lng),
    customer_location_accuracy = VALUES(customer_location_accuracy),
    route_source = VALUES(route_source)",
  "SELECT 0"
);
PREPARE stmt_backfill_delivery FROM @backfill_delivery_sql;
EXECUTE stmt_backfill_delivery;
DEALLOCATE PREPARE stmt_backfill_delivery;

ALTER TABLE pos_order DROP KEY IF EXISTS idx_pos_order_delivery_location;
ALTER TABLE pos_order
  DROP COLUMN IF EXISTS delivery_fee_amount,
  DROP COLUMN IF EXISTS delivery_distance_km,
  DROP COLUMN IF EXISTS delivery_route_distance_km,
  DROP COLUMN IF EXISTS delivery_duration_min,
  DROP COLUMN IF EXISTS delivery_address,
  DROP COLUMN IF EXISTS customer_lat,
  DROP COLUMN IF EXISTS customer_lng,
  DROP COLUMN IF EXISTS customer_location_accuracy;

COMMIT;

SELECT 'crm_member_delivery_location' AS object_name, COUNT(*) AS rows_count FROM crm_member_delivery_location
UNION ALL
SELECT 'pos_online_food_delivery_order', COUNT(*) FROM pos_online_food_delivery_order
UNION ALL
SELECT 'pos_order_delivery_columns_remaining', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_order'
  AND COLUMN_NAME IN (
    'delivery_fee_amount',
    'delivery_distance_km',
    'delivery_route_distance_km',
    'delivery_duration_min',
    'delivery_address',
    'customer_lat',
    'customer_lng',
    'customer_location_accuracy'
  );
