SET NAMES utf8mb4;

-- ============================================================
-- Resync active product + recipe master from core -> db_finance
-- Date: 2026-05-27
-- Notes:
-- 1) Only active source rows are imported.
-- 2) Product default operational division is normalized at the
--    product-division level to avoid recipe AUTO lines falling back
--    to MANAJEMEN.
-- 3) EVENT currently defaults to KITCHEN because active EVENT recipe
--    lines in core map to KITCHEN_EVENT and there are no active AUTO
--    recipe lines under EVENT at the time of this sync.
-- ============================================================

START TRANSACTION;

SET @has_pd_default_operational = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mst_product_division'
    AND COLUMN_NAME = 'default_operational_division_id'
);

SET @sql = IF(
  @has_pd_default_operational = 0,
  'ALTER TABLE mst_product_division
      ADD COLUMN default_operational_division_id BIGINT UNSIGNED NULL AFTER sort_order,
      ADD KEY idx_mst_product_division_default_operational (default_operational_division_id),
      ADD CONSTRAINT fk_mst_product_division_default_operational FOREIGN KEY (default_operational_division_id) REFERENCES mst_operational_division(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1) Product-related dimensions from core (active only).
INSERT INTO mst_product_division (
  code,
  name,
  pos_scope,
  sort_order,
  is_active
)
SELECT
  d.division_code,
  d.division_name,
  IFNULL(d.pos_scope, 'REGULAR'),
  IFNULL(d.display_order, 0),
  1
FROM core.prd_product_division d
WHERE IFNULL(d.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  pos_scope = VALUES(pos_scope),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = NOW();

UPDATE mst_product_division pd
LEFT JOIN mst_operational_division od
  ON od.code = CASE pd.code
    WHEN 'BEVERAGE' THEN 'BAR'
    WHEN 'FOOD' THEN 'KITCHEN'
    WHEN 'EVENT' THEN 'KITCHEN'
    ELSE NULL
  END
SET pd.default_operational_division_id = od.id,
    pd.updated_at = NOW()
WHERE pd.code IN ('BEVERAGE', 'FOOD', 'EVENT');

INSERT INTO mst_product_classification (
  product_division_id,
  code,
  name,
  sort_order,
  is_active
)
SELECT
  pd.id,
  c.classification_code,
  c.classification_name,
  IFNULL(c.display_order, 0),
  1
FROM core.prd_product_classification c
JOIN core.prd_product_division d ON d.id = c.division_id
JOIN mst_product_division pd ON pd.code = d.division_code
WHERE IFNULL(c.is_active, 1) = 1
  AND IFNULL(d.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  product_division_id = VALUES(product_division_id),
  name = VALUES(name),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO mst_product_category (
  product_division_id,
  classification_id,
  code,
  name,
  sort_order,
  is_active
)
SELECT
  pd.id,
  pc.id,
  cat.category_code,
  cat.category_name,
  IFNULL(cat.display_order, 0),
  1
FROM core.prd_product_category cat
JOIN core.prd_product_classification c ON c.id = cat.classification_id
JOIN core.prd_product_division d ON d.id = c.division_id
JOIN mst_product_division pd ON pd.code = d.division_code
JOIN mst_product_classification pc ON pc.code = c.classification_code
WHERE IFNULL(cat.is_active, 1) = 1
  AND IFNULL(c.is_active, 1) = 1
  AND IFNULL(d.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  product_division_id = VALUES(product_division_id),
  classification_id = VALUES(classification_id),
  name = VALUES(name),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- 2) Material / item support that product recipe lines depend on.
INSERT INTO mst_item_category (
  code,
  name,
  parent_id,
  sort_order,
  is_active
)
SELECT
  mc.category_code,
  mc.category_name,
  NULL,
  0,
  1
FROM core.m_material_category mc
WHERE IFNULL(mc.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO mst_material (
  material_code,
  material_name,
  item_category_id,
  content_uom_id,
  hpp_standard,
  is_active
)
SELECT
  m.material_code,
  m.material_name,
  COALESCE(ic.id, (SELECT id FROM mst_item_category ORDER BY id LIMIT 1)),
  tu.id,
  m.hpp_standard,
  1
FROM core.m_material m
LEFT JOIN core.m_material_category mc ON mc.id = m.material_category_id
LEFT JOIN mst_item_category ic ON ic.code = mc.category_code
JOIN core.m_uom su ON su.id = m.base_uom_id
JOIN mst_uom tu ON tu.code = su.code
WHERE IFNULL(m.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  material_name = VALUES(material_name),
  item_category_id = VALUES(item_category_id),
  content_uom_id = VALUES(content_uom_id),
  hpp_standard = VALUES(hpp_standard),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO mst_item (
  item_code,
  item_name,
  item_category_id,
  buy_uom_id,
  content_uom_id,
  content_per_buy,
  min_stock_content,
  last_buy_price,
  is_material,
  material_id,
  notes,
  is_active
)
SELECT
  i.item_code,
  i.item_name,
  COALESCE(ic.id, (SELECT id FROM mst_item_category ORDER BY id LIMIT 1)),
  tu.id,
  tu.id,
  1.000000,
  0.0000,
  NULL,
  IF(i.material_id IS NULL, 0, 1),
  tm.id,
  i.notes,
  1
FROM core.m_item i
JOIN core.m_uom su ON su.id = i.base_uom_id
JOIN mst_uom tu ON tu.code = su.code
LEFT JOIN core.m_material cm ON cm.id = i.material_id
LEFT JOIN core.m_material_category cmc ON cmc.id = cm.material_category_id
LEFT JOIN mst_item_category ic ON ic.code = cmc.category_code
LEFT JOIN mst_material tm ON tm.material_code = cm.material_code
WHERE IFNULL(i.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  item_name = VALUES(item_name),
  item_category_id = VALUES(item_category_id),
  buy_uom_id = VALUES(buy_uom_id),
  content_uom_id = VALUES(content_uom_id),
  content_per_buy = VALUES(content_per_buy),
  min_stock_content = VALUES(min_stock_content),
  is_material = VALUES(is_material),
  material_id = VALUES(material_id),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- 3) Deactivate finance products that exist in core but are no longer active there.
UPDATE mst_product tp
JOIN core.prd_product sp ON sp.product_code = tp.product_code
SET tp.is_active = 0,
    tp.updated_at = NOW()
WHERE IFNULL(sp.is_active, 1) = 0
  AND tp.is_active <> 0;

-- 4) Active products from core.
INSERT INTO mst_product (
  product_code,
  product_name,
  product_division_id,
  default_operational_division_id,
  classification_id,
  product_category_id,
  uom_id,
  selling_price,
  hpp_standard,
  variable_cost_mode,
  variable_cost_percent,
  stock_mode,
  show_pos,
  show_member,
  show_landing,
  photo_path,
  is_active
)
SELECT
  p.product_code,
  p.product_name,
  pd.id,
  COALESCE(pd.default_operational_division_id, (SELECT id FROM mst_operational_division WHERE code = 'KITCHEN' LIMIT 1), (SELECT id FROM mst_operational_division ORDER BY id LIMIT 1)),
  pc.id,
  COALESCE(
    pg.id,
    (
      SELECT pgc.id
      FROM mst_product_category pgc
      WHERE pgc.classification_id = pc.id
        AND pgc.is_active = 1
      ORDER BY pgc.sort_order ASC, pgc.id ASC
      LIMIT 1
    ),
    (
      SELECT pgd.id
      FROM mst_product_category pgd
      WHERE pgd.product_division_id = pd.id
        AND pgd.is_active = 1
      ORDER BY pgd.sort_order ASC, pgd.id ASC
      LIMIT 1
    )
  ) AS product_category_id,
  tu.id,
  p.selling_price,
  p.hpp_standard,
  p.variable_cost_mode,
  p.variable_cost_percent,
  CASE
    WHEN p.availability_mode = 'MANUAL' AND p.manual_availability_status = 'SOLD_OUT' THEN 'MANUAL_OUT'
    WHEN p.availability_mode = 'MANUAL' THEN 'MANUAL_AVAILABLE'
    ELSE 'AUTO'
  END AS stock_mode,
  p.is_sellable,
  0,
  0,
  p.photo_path,
  1
FROM core.prd_product p
JOIN core.prd_product_division spd ON spd.id = p.product_division_id
JOIN mst_product_division pd ON pd.code = spd.division_code
JOIN core.prd_product_classification spc ON spc.id = p.classification_id
JOIN mst_product_classification pc ON pc.code = spc.classification_code
LEFT JOIN core.prd_product_category spg ON spg.id = p.category_id
LEFT JOIN mst_product_category pg ON pg.code = spg.category_code
JOIN core.m_uom su ON su.id = p.sale_uom_id
JOIN mst_uom tu ON tu.code = su.code
WHERE IFNULL(p.is_active, 1) = 1
ON DUPLICATE KEY UPDATE
  product_name = VALUES(product_name),
  product_division_id = VALUES(product_division_id),
  default_operational_division_id = VALUES(default_operational_division_id),
  classification_id = VALUES(classification_id),
  product_category_id = VALUES(product_category_id),
  uom_id = VALUES(uom_id),
  selling_price = VALUES(selling_price),
  hpp_standard = VALUES(hpp_standard),
  variable_cost_mode = VALUES(variable_cost_mode),
  variable_cost_percent = VALUES(variable_cost_percent),
  stock_mode = VALUES(stock_mode),
  show_pos = VALUES(show_pos),
  show_member = VALUES(show_member),
  show_landing = VALUES(show_landing),
  photo_path = VALUES(photo_path),
  is_active = VALUES(is_active),
  updated_at = NOW();

-- 5) Refresh product recipes for products sourced from core.
DELETE r
FROM mst_product_recipe r
JOIN mst_product tp ON tp.id = r.product_id
JOIN core.prd_product sp ON sp.product_code = tp.product_code;

INSERT INTO mst_product_recipe (
  product_id,
  line_no,
  line_type,
  ingredient_role,
  material_item_id,
  component_id,
  source_division_id,
  qty,
  uom_id,
  notes,
  sort_order
)
SELECT
  tp.id AS product_id,
  COALESCE(l.line_no, 1) AS line_no,
  l.source_kind AS line_type,
  COALESCE(NULLIF(l.ingredient_role, ''), 'MAIN') AS ingredient_role,
  CASE WHEN l.source_kind = 'MATERIAL' THEN ti.item_id ELSE NULL END AS material_item_id,
  CASE WHEN l.source_kind = 'COMPONENT' THEN tc.id ELSE NULL END AS component_id,
  CASE
    WHEN l.source_location_type IN ('BAR', 'BAR_EVENT') THEN od_bar.id
    WHEN l.source_location_type IN ('KITCHEN', 'KITCHEN_EVENT') THEN od_kitchen.id
    ELSE COALESCE(pd.default_operational_division_id, tp.default_operational_division_id)
  END AS source_division_id,
  l.qty,
  tu.id AS uom_id,
  l.notes,
  COALESCE(l.line_no, 0) AS sort_order
FROM core.prd_product_recipe_line l
JOIN core.prd_product_recipe h ON h.id = l.recipe_id
JOIN core.prd_product sp ON sp.id = h.product_id
JOIN mst_product tp ON tp.product_code = sp.product_code
JOIN mst_product_division pd ON pd.id = tp.product_division_id
LEFT JOIN core.m_material sm ON sm.id = l.material_id
LEFT JOIN mst_material tm ON tm.material_code = sm.material_code
LEFT JOIN (
  SELECT material_id, MIN(id) AS item_id
  FROM mst_item
  WHERE is_material = 1
    AND material_id IS NOT NULL
    AND is_active = 1
  GROUP BY material_id
) ti ON ti.material_id = tm.id
LEFT JOIN core.prd_component sc ON sc.id = l.component_id
LEFT JOIN mst_component tc ON tc.component_code = sc.component_code
LEFT JOIN core.m_uom su ON su.id = l.uom_id
LEFT JOIN mst_uom tu ON tu.code = su.code
LEFT JOIN mst_operational_division od_bar ON od_bar.code = 'BAR'
LEFT JOIN mst_operational_division od_kitchen ON od_kitchen.code = 'KITCHEN'
WHERE IFNULL(sp.is_active, 1) = 1
  AND IFNULL(h.is_active, 1) = 1
  AND IFNULL(l.is_active, 1) = 1
  AND tu.id IS NOT NULL
  AND (
    (l.source_kind = 'MATERIAL' AND ti.item_id IS NOT NULL)
    OR
    (l.source_kind = 'COMPONENT' AND tc.id IS NOT NULL)
  );

COMMIT;

SELECT 'active_core_products_in_finance' AS metric, COUNT(*) AS total
FROM mst_product tp
JOIN core.prd_product sp ON sp.product_code = tp.product_code
WHERE IFNULL(sp.is_active, 1) = 1
UNION ALL
SELECT 'product_recipe_rows_for_core_products' AS metric, COUNT(*) AS total
FROM mst_product_recipe r
JOIN mst_product tp ON tp.id = r.product_id
JOIN core.prd_product sp ON sp.product_code = tp.product_code
WHERE IFNULL(sp.is_active, 1) = 1
UNION ALL
SELECT 'core_product_recipe_rows_source_division_1' AS metric, COUNT(*) AS total
FROM mst_product_recipe r
JOIN mst_product tp ON tp.id = r.product_id
JOIN core.prd_product sp ON sp.product_code = tp.product_code
WHERE r.source_division_id = 1
UNION ALL
SELECT 'mst_product_division_default_operational_filled' AS metric, COUNT(*) AS total
FROM mst_product_division
WHERE default_operational_division_id IS NOT NULL;