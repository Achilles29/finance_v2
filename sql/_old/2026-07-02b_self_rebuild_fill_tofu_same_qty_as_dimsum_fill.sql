START TRANSACTION;

-- Rebuild component formula for FILL TOFU (component_id=119)
-- using the exact same material quantities as DIMSUM FILL (component_id=52),
-- without scaling. Preserve other target-specific lines such as TAHU PONG.

DROP TEMPORARY TABLE IF EXISTS tmp_fill_tofu_preserved_lines_same_qty;
CREATE TEMPORARY TABLE tmp_fill_tofu_preserved_lines_same_qty AS
SELECT
    line_type,
    material_id,
    material_item_id,
    sub_component_id,
    qty,
    notes,
    sort_order,
    source_division_id
FROM mst_component_formula
WHERE component_id = 119
  AND NOT (
      line_type = 'MATERIAL'
      AND material_id IN (
          SELECT material_id
          FROM mst_component_formula
          WHERE component_id = 52
            AND line_type = 'MATERIAL'
            AND material_id IS NOT NULL
      )
  );

DELETE FROM mst_component_formula
WHERE component_id = 119;

SET @line_no := 0;

INSERT INTO mst_component_formula (
    component_id,
    line_no,
    line_type,
    material_id,
    material_item_id,
    sub_component_id,
    qty,
    notes,
    sort_order,
    source_division_id
)
SELECT
    119 AS component_id,
    (@line_no := @line_no + 1) AS line_no,
    'MATERIAL' AS line_type,
    f.material_id,
    f.material_item_id,
    NULL AS sub_component_id,
    f.qty,
    f.notes,
    COALESCE(f.sort_order, (@line_no * 10)) AS sort_order,
    f.source_division_id
FROM mst_component_formula f
WHERE f.component_id = 52
ORDER BY f.line_no ASC, f.id ASC;

INSERT INTO mst_component_formula (
    component_id,
    line_no,
    line_type,
    material_id,
    material_item_id,
    sub_component_id,
    qty,
    notes,
    sort_order,
    source_division_id
)
SELECT
    119 AS component_id,
    (@line_no := @line_no + 1) AS line_no,
    p.line_type,
    p.material_id,
    p.material_item_id,
    p.sub_component_id,
    p.qty,
    p.notes,
    COALESCE(p.sort_order, (@line_no * 10)) AS sort_order,
    p.source_division_id
FROM tmp_fill_tofu_preserved_lines_same_qty p
ORDER BY p.line_type ASC, p.material_id ASC, p.sub_component_id ASC;

DROP TEMPORARY TABLE IF EXISTS tmp_fill_tofu_preserved_lines_same_qty;

COMMIT;
