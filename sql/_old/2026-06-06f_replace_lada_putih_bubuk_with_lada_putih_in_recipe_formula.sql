SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06f_replace_lada_putih_bubuk_with_lada_putih_in_recipe_formula.sql
-- Tujuan :
-- 1) Mengganti referensi resep produk dari LADA PUTIH BUBUK -> LADA PUTIH
-- 2) Mengganti referensi formula component dari LADA PUTIH BUBUK -> LADA PUTIH
-- 3) Menjaga perubahan hanya pada lane recipe/formula, tanpa menyentuh histori stok/purchase
--
-- Catatan:
-- - Target master:
--   old material : LADA PUTIH BUBUK
--   new material : LADA PUTIH
-- - Script ini hanya mengubah mst_product_recipe dan mst_component_formula
-- ============================================================

START TRANSACTION;

SET @old_material_name := 'LADA PUTIH BUBUK';
SET @new_material_name := 'LADA PUTIH';
SET @repair_tag := 'Replace LADA PUTIH BUBUK -> LADA PUTIH in recipe/formula 2026-06-06';

SET @old_material_id := (
  SELECT id FROM mst_material
  WHERE UPPER(TRIM(material_name)) = UPPER(TRIM(@old_material_name))
  ORDER BY id ASC LIMIT 1
);
SET @new_material_id := (
  SELECT id FROM mst_material
  WHERE UPPER(TRIM(material_name)) = UPPER(TRIM(@new_material_name))
  ORDER BY id ASC LIMIT 1
);
SET @old_item_id := (
  SELECT id FROM mst_item
  WHERE material_id = @old_material_id
  ORDER BY id ASC LIMIT 1
);
SET @new_item_id := (
  SELECT id FROM mst_item
  WHERE material_id = @new_material_id
  ORDER BY id ASC LIMIT 1
);

SET @guard_message := NULL;
SET @guard_message := IF(@old_material_id IS NULL, CONCAT('Material lama tidak ditemukan: ', @old_material_name), @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND @new_material_id IS NULL, CONCAT('Material baru tidak ditemukan: ', @new_material_name), @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND @old_item_id IS NULL, CONCAT('Item bridge material lama tidak ditemukan untuk: ', @old_material_name), @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND @new_item_id IS NULL, CONCAT('Item bridge material baru tidak ditemukan untuk: ', @new_material_name), @guard_message);

SET @sql_guard := IF(
  @guard_message IS NOT NULL,
  CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''', REPLACE(@guard_message, '''', ''''''), ''''),
  'SELECT 1'
);
PREPARE stmt FROM @sql_guard; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1) Update resep produk
UPDATE mst_product_recipe
SET material_item_id = @new_item_id,
    updated_at = CURRENT_TIMESTAMP,
    notes = LEFT(TRIM(CONCAT(
      COALESCE(notes, ''),
      CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag
    )), 255)
WHERE line_type = 'MATERIAL'
  AND material_item_id = @old_item_id;

-- 2) Update formula component
UPDATE mst_component_formula
SET material_id = CASE WHEN material_id = @old_material_id THEN @new_material_id ELSE material_id END,
    material_item_id = CASE
      WHEN material_item_id = @old_item_id THEN @new_item_id
      WHEN material_id = @old_material_id AND material_item_id IS NULL THEN @new_item_id
      ELSE material_item_id
    END,
    updated_at = CURRENT_TIMESTAMP,
    notes = LEFT(TRIM(CONCAT(
      COALESCE(notes, ''),
      CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag
    )), 255)
WHERE line_type = 'MATERIAL'
  AND (
    material_id = @old_material_id
    OR material_item_id = @old_item_id
  );

COMMIT;

SELECT 'old_material_id' AS metric, @old_material_id AS value
UNION ALL SELECT 'new_material_id', @new_material_id
UNION ALL SELECT 'old_item_id', @old_item_id
UNION ALL SELECT 'new_item_id', @new_item_id
UNION ALL
SELECT 'product_recipe_old_refs_remaining', COUNT(*)
FROM mst_product_recipe
WHERE line_type = 'MATERIAL' AND material_item_id = @old_item_id
UNION ALL
SELECT 'component_formula_old_material_refs_remaining', COUNT(*)
FROM mst_component_formula
WHERE line_type = 'MATERIAL' AND material_id = @old_material_id
UNION ALL
SELECT 'component_formula_old_item_refs_remaining', COUNT(*)
FROM mst_component_formula
WHERE line_type = 'MATERIAL' AND material_item_id = @old_item_id
UNION ALL
SELECT 'product_recipe_new_refs_total', COUNT(*)
FROM mst_product_recipe
WHERE line_type = 'MATERIAL' AND material_item_id = @new_item_id
UNION ALL
SELECT 'component_formula_new_material_refs_total', COUNT(*)
FROM mst_component_formula
WHERE line_type = 'MATERIAL' AND material_id = @new_material_id;
