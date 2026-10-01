START TRANSACTION;

-- Rebuild component formula for FILL TOFU (component_id=119)
-- by expanding the DIMSUM FILL sub-component recipe (component_id=52)
-- into direct material lines, scaled by:
--   target sub-component qty / source component yield_qty = 120 / 10 = 12
-- and preserving any other existing target lines (for example TAHU PONG).

DROP TEMPORARY TABLE IF EXISTS tmp_fill_tofu_preserved_lines;
CREATE TEMPORARY TABLE tmp_fill_tofu_preserved_lines AS
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
  AND NOT (line_type = 'COMPONENT' AND sub_component_id = 52);

SET @source_component_id := 52;
SET @target_component_id := 119;

SET @source_yield_qty := (
    SELECT COALESCE(NULLIF(yield_qty, 0), 1)
    FROM mst_component
    WHERE id = @source_component_id
    LIMIT 1
);

SET @target_sub_component_qty := (
    SELECT qty
    FROM mst_component_formula
    WHERE component_id = @target_component_id
      AND line_type = 'COMPONENT'
      AND sub_component_id = @source_component_id
    ORDER BY id DESC
    LIMIT 1
);

SET @formula_multiplier := ROUND(COALESCE(@target_sub_component_qty, 0) / COALESCE(@source_yield_qty, 1), 8);

DELETE FROM mst_component_formula
WHERE component_id = @target_component_id;

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
    @target_component_id AS component_id,
    (@line_no := @line_no + 1) AS line_no,
    'MATERIAL' AS line_type,
    f.material_id,
    f.material_item_id,
    NULL AS sub_component_id,
    ROUND(f.qty * @formula_multiplier, 4) AS qty,
    f.notes,
    COALESCE(f.sort_order, (@line_no * 10)) AS sort_order,
    f.source_division_id
FROM mst_component_formula f
WHERE f.component_id = @source_component_id
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
    @target_component_id AS component_id,
    (@line_no := @line_no + 1) AS line_no,
    p.line_type,
    p.material_id,
    p.material_item_id,
    p.sub_component_id,
    p.qty,
    p.notes,
    COALESCE(p.sort_order, (@line_no * 10)) AS sort_order,
    p.source_division_id
FROM tmp_fill_tofu_preserved_lines p
ORDER BY p.line_type ASC, p.material_id ASC, p.sub_component_id ASC;

DROP TEMPORARY TABLE IF EXISTS tmp_fill_tofu_preserved_lines;

COMMIT;
