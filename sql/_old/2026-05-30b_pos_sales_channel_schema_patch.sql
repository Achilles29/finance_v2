SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30b_pos_sales_channel_schema_patch.sql
-- Tujuan :
-- 1) Memperbaiki schema pos_sales_channel yang sudah terlanjur dibuat tanpa allowed_service_types
-- 2) Mengisi allowed_service_types awal agar guard sales channel -> service type bisa bekerja
-- 3) Aman dijalankan ulang
-- ============================================================

START TRANSACTION;

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

UPDATE pos_sales_channel
SET allowed_service_types = CASE UPPER(COALESCE(channel_code, ''))
  WHEN 'WALK_IN' THEN 'DINE_IN,TAKE_AWAY'
  WHEN 'WHATSAPP' THEN 'PICKUP,DELIVERY,TAKE_AWAY'
  WHEN 'GRABFOOD' THEN 'DELIVERY,PICKUP'
  WHEN 'SHOPEEFOOD' THEN 'DELIVERY,PICKUP'
  WHEN 'GOFOOD' THEN 'DELIVERY,PICKUP'
  WHEN 'PHONE' THEN 'PICKUP,DELIVERY'
  ELSE COALESCE(NULLIF(allowed_service_types, ''), service_type_default)
END
WHERE COALESCE(allowed_service_types, '') = '';

COMMIT;

SELECT 'pos_sales_channel.allowed_service_types.exists' AS metric, COUNT(*) AS total_rows
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_sales_channel'
  AND COLUMN_NAME = 'allowed_service_types'
UNION ALL
SELECT 'pos_sales_channel.allowed_service_types.filled', COUNT(*)
FROM pos_sales_channel
WHERE COALESCE(allowed_service_types, '') <> '';
