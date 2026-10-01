SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-26b_add_logo_path_to_coffee_packaging_label.sql
-- Tujuan :
-- Menambahkan logo_path agar setiap label packaging kopi bisa
-- memakai logo PNG berbeda, terpisah dari artwork/background label.
-- Aman dijalankan berulang.
-- ============================================================

SET @has_logo_path := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'logo_path'
);

SET @ddl_logo_path := IF(
  @has_logo_path = 0,
  'ALTER TABLE coffee_packaging_label ADD COLUMN logo_path VARCHAR(255) NULL AFTER image_path',
  'SELECT ''coffee_packaging_label.logo_path sudah ada'' AS info'
);

PREPARE stmt_logo_path FROM @ddl_logo_path;
EXECUTE stmt_logo_path;
DEALLOCATE PREPARE stmt_logo_path;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'coffee_packaging_label'
  AND COLUMN_NAME = 'logo_path';
