SET NAMES utf8mb4;

-- ============================================================
-- Import recipe/formula lines from core -> db_finance
-- Note: this script refreshes relation tables from source core.
-- ============================================================

START TRANSACTION;

DELETE FROM mst_product_recipe;

INSERT INTO mst_product_recipe (
  product_id,
  line_no,
  line_type,
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
  CASE WHEN l.source_kind = 'MATERIAL' THEN ti.id ELSE NULL END AS material_item_id,
  CASE WHEN l.source_kind = 'COMPONENT' THEN tc.id ELSE NULL END AS component_id,
  COALESCE(
    od.id,
    tp.default_operational_division_id
  ) AS source_division_id,
  l.qty,
  tu.id AS uom_id,
  l.notes,
  COALESCE(l.line_no, 0) AS sort_order
FROM core.prd_product_recipe_line l
JOIN core.prd_product_recipe h ON h.id = l.recipe_id
JOIN core.prd_product sp ON sp.id = h.product_id
JOIN mst_product tp ON tp.product_code = sp.product_code
LEFT JOIN core.m_material sm ON sm.id = l.material_id
LEFT JOIN mst_material tm ON tm.material_code = sm.material_code
LEFT JOIN mst_item ti ON ti.material_id = tm.id AND ti.is_material = 1
LEFT JOIN core.prd_component sc ON sc.id = l.component_id
LEFT JOIN mst_component tc ON tc.component_code = sc.component_code
LEFT JOIN core.m_uom su ON su.id = l.uom_id
LEFT JOIN mst_uom tu ON tu.code = su.code
LEFT JOIN mst_operational_division od ON od.code = CASE
  WHEN l.source_location_type IN ('BAR', 'BAR_EVENT') THEN 'BAR'
  WHEN l.source_location_type IN ('KITCHEN', 'KITCHEN_EVENT') THEN 'KITCHEN'
  ELSE NULL
END
WHERE h.is_active = 1
  AND l.is_active = 1
  AND tu.id IS NOT NULL
  AND (
    (l.source_kind = 'MATERIAL' AND ti.id IS NOT NULL)
    OR
    (l.source_kind = 'COMPONENT' AND tc.id IS NOT NULL)
  );

DELETE FROM mst_component_formula;

INSERT INTO mst_component_formula (
  component_id,
  line_no,
  line_type,
  material_item_id,
  sub_component_id,
  qty,
  uom_id,
  notes,
  sort_order
)
SELECT
  tc_parent.id AS component_id,
  COALESCE(l.line_no, 1) AS line_no,
  l.source_kind AS line_type,
  CASE WHEN l.source_kind = 'MATERIAL' THEN ti.id ELSE NULL END AS material_item_id,
  CASE WHEN l.source_kind = 'COMPONENT' THEN tc_sub.id ELSE NULL END AS sub_component_id,
  l.qty,
  tu.id AS uom_id,
  l.notes,
  COALESCE(l.line_no, 0) AS sort_order
FROM core.prd_component_formula_line l
JOIN core.prd_component_formula h ON h.id = l.formula_id
JOIN core.prd_component sc_parent ON sc_parent.id = h.component_id
JOIN mst_component tc_parent ON tc_parent.component_code = sc_parent.component_code
LEFT JOIN core.m_material sm ON sm.id = l.material_id
LEFT JOIN mst_material tm ON tm.material_code = sm.material_code
LEFT JOIN mst_item ti ON ti.material_id = tm.id AND ti.is_material = 1
LEFT JOIN core.prd_component sc_sub ON sc_sub.id = l.component_id
LEFT JOIN mst_component tc_sub ON tc_sub.component_code = sc_sub.component_code
LEFT JOIN core.m_uom su ON su.id = l.uom_id
LEFT JOIN mst_uom tu ON tu.code = su.code
WHERE h.is_active = 1
  AND tu.id IS NOT NULL
  AND (
    (l.source_kind = 'MATERIAL' AND ti.id IS NOT NULL)
    OR
    (l.source_kind = 'COMPONENT' AND tc_sub.id IS NOT NULL AND tc_sub.id <> tc_parent.id)
  );

COMMIT;

SELECT 'mst_product_recipe' AS table_name, COUNT(*) AS total_rows FROM mst_product_recipe
UNION ALL
SELECT 'mst_component_formula' AS table_name, COUNT(*) AS total_rows FROM mst_component_formula;
