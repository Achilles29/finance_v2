SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-26c_extend_coffee_packaging_label_details.sql
-- Tujuan :
-- 1) Menambah kolom detail label packaging kopi yang belum ada
-- 2) Mengakomodir panel premium: body, elevation, bean/grind,
--    dan footer mini
-- 3) Aman dijalankan berulang
-- ============================================================

START TRANSACTION;

SET @has_body_level := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'body_level'
);
SET @ddl_body_level := IF(
  @has_body_level = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN body_level VARCHAR(80) NULL AFTER roast_level',
  'SELECT ''coffee_packaging_label.body_level sudah ada'' AS info'
);
PREPARE stmt_body_level FROM @ddl_body_level;
EXECUTE stmt_body_level;
DEALLOCATE PREPARE stmt_body_level;

SET @has_elevation_text := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'elevation_text'
);
SET @ddl_elevation_text := IF(
  @has_elevation_text = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN elevation_text VARCHAR(120) NULL AFTER body_level',
  'SELECT ''coffee_packaging_label.elevation_text sudah ada'' AS info'
);
PREPARE stmt_elevation_text FROM @ddl_elevation_text;
EXECUTE stmt_elevation_text;
DEALLOCATE PREPARE stmt_elevation_text;

SET @has_bean_type := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'bean_type'
);
SET @ddl_bean_type := IF(
  @has_bean_type = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN bean_type VARCHAR(80) NULL AFTER elevation_text',
  'SELECT ''coffee_packaging_label.bean_type sudah ada'' AS info'
);
PREPARE stmt_bean_type FROM @ddl_bean_type;
EXECUTE stmt_bean_type;
DEALLOCATE PREPARE stmt_bean_type;

SET @has_footer_note := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'footer_note'
);
SET @ddl_footer_note := IF(
  @has_footer_note = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN footer_note VARCHAR(180) NULL AFTER description',
  'SELECT ''coffee_packaging_label.footer_note sudah ada'' AS info'
);
PREPARE stmt_footer_note FROM @ddl_footer_note;
EXECUTE stmt_footer_note;
DEALLOCATE PREPARE stmt_footer_note;

COMMIT;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'coffee_packaging_label'
  AND COLUMN_NAME IN ('body_level', 'elevation_text', 'bean_type', 'footer_note')
ORDER BY FIELD(COLUMN_NAME, 'body_level', 'elevation_text', 'bean_type', 'footer_note');
