SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-29b_add_coffee_packaging_label_identity.sql
-- Tujuan :
-- 1) Memisahkan nama administrasi label dari nama produk kopi
-- 2) Menghubungkan label ke master produk roastery bila dipilih
-- 3) Mempertahankan label lama dengan backfill dari coffee_name
-- ============================================================

START TRANSACTION;

ALTER TABLE coffee_packaging_label
  ADD COLUMN IF NOT EXISTS label_name VARCHAR(160) NULL AFTER label_code,
  ADD COLUMN IF NOT EXISTS product_id BIGINT UNSIGNED NULL AFTER label_name,
  ADD KEY IF NOT EXISTS idx_coffee_packaging_label_label_name (label_name),
  ADD KEY IF NOT EXISTS idx_coffee_packaging_label_product (product_id);

UPDATE coffee_packaging_label
SET label_name = coffee_name
WHERE COALESCE(TRIM(label_name), '') = '';

ALTER TABLE coffee_packaging_label
  MODIFY COLUMN label_name VARCHAR(160) NOT NULL;

SET @fk_label_product := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'coffee_packaging_label'
    AND COLUMN_NAME = 'product_id'
    AND REFERENCED_TABLE_NAME = 'mst_product'
  LIMIT 1
);
SET @sql_fk_label_product := IF(
  @fk_label_product IS NULL,
  'ALTER TABLE coffee_packaging_label ADD CONSTRAINT fk_coffee_packaging_label_product FOREIGN KEY (product_id) REFERENCES mst_product(id) ON DELETE SET NULL ON UPDATE RESTRICT',
  'SELECT 1'
);
PREPARE stmt_fk_label_product FROM @sql_fk_label_product;
EXECUTE stmt_fk_label_product;
DEALLOCATE PREPARE stmt_fk_label_product;

COMMIT;

SELECT
  id,
  label_code,
  label_name,
  product_id,
  coffee_name
FROM coffee_packaging_label
ORDER BY id DESC
LIMIT 20;
