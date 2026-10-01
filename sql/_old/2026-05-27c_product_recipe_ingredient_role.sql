SET NAMES utf8mb4;

START TRANSACTION;

SET @has_ingredient_role = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_product_recipe'
    AND COLUMN_NAME = 'ingredient_role'
);

SET @sql = IF(
  @has_ingredient_role = 0,
  "ALTER TABLE mst_product_recipe
      ADD COLUMN ingredient_role ENUM('MAIN','SUPPORT','GARNISH','OPTIONAL','SAUCE','TOPPING','OTHER') NOT NULL DEFAULT 'MAIN' AFTER line_type",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE mst_product_recipe
SET ingredient_role = 'MAIN'
WHERE ingredient_role IS NULL OR ingredient_role = '';

UPDATE mst_product_recipe r
JOIN mst_product tp ON tp.id = r.product_id
JOIN core.prd_product sp ON sp.product_code = tp.product_code
JOIN core.prd_product_recipe h ON h.product_id = sp.id AND IFNULL(h.is_active, 1) = 1
JOIN core.prd_product_recipe_line l
  ON l.recipe_id = h.id
 AND IFNULL(l.is_active, 1) = 1
 AND COALESCE(l.line_no, 1) = COALESCE(r.line_no, 1)
 AND l.source_kind = r.line_type
SET r.ingredient_role = COALESCE(NULLIF(l.ingredient_role, ''), 'MAIN')
WHERE IFNULL(sp.is_active, 1) = 1;

COMMIT;

SELECT 'mst_product_recipe_ingredient_role_filled' AS metric, COUNT(*) AS total
FROM mst_product_recipe
WHERE ingredient_role IS NOT NULL;