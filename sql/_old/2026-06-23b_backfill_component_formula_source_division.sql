-- Backfill source_division_id on mst_component_formula from parent component's operational_division_id.
-- All 774 lines were NULL after the 2026-06-23a migration.
-- Default rule: each formula line inherits the operational division of its parent component.
-- Lines can be manually overridden per-line via the formula edit UI after this backfill.

-- Preview (run SELECT first to verify before UPDATE):
-- SELECT f.id, c.component_name, od.name AS division_name
-- FROM mst_component_formula f
-- JOIN mst_component c ON c.id = f.component_id
-- JOIN mst_operational_division od ON od.id = c.operational_division_id
-- WHERE f.source_division_id IS NULL
-- ORDER BY od.name, c.component_name;

UPDATE mst_component_formula f
JOIN mst_component c ON c.id = f.component_id
SET f.source_division_id = c.operational_division_id
WHERE f.source_division_id IS NULL
  AND c.operational_division_id IS NOT NULL;

-- Verify:
-- SELECT
--   od.name AS division_name,
--   COUNT(f.id) AS lines,
--   COUNT(DISTINCT f.component_id) AS components,
--   SUM(CASE WHEN f.source_division_id IS NULL THEN 1 ELSE 0 END) AS still_null
-- FROM mst_component_formula f
-- JOIN mst_component c ON c.id = f.component_id
-- LEFT JOIN mst_operational_division od ON od.id = f.source_division_id
-- GROUP BY od.id, od.name;
