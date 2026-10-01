SET NAMES utf8mb4;

-- ============================================================
-- 1. Resep produk yang punya source_division = MANAJEMEN
-- ============================================================
SELECT
  r.id           AS recipe_id,
  p.product_name,
  r.line_type,
  r.material_item_id,
  COALESCE(i.item_name, '-')      AS item_name,
  COALESCE(mm.material_name, '-') AS material_name,
  r.source_division_id,
  d.name         AS source_division_name
FROM mst_product_recipe r
JOIN mst_product p ON p.id = r.product_id
LEFT JOIN mst_item i ON i.id = r.material_item_id
LEFT JOIN mst_material mm ON mm.id = i.material_id
LEFT JOIN mst_operational_division d ON d.id = r.source_division_id
WHERE UPPER(TRIM(d.name)) = 'MANAJEMEN'
ORDER BY p.product_name, r.id;


-- ============================================================
-- 2. Component yang operational_division = MANAJEMEN
--    (formula line mengikuti component-nya)
-- ============================================================
SELECT
  c.id           AS component_id,
  c.component_name,
  c.operational_division_id,
  d.name         AS division_name,
  COUNT(f.id)    AS formula_lines
FROM mst_component c
LEFT JOIN mst_operational_division d ON d.id = c.operational_division_id
LEFT JOIN mst_component_formula f ON f.component_id = c.id
WHERE UPPER(TRIM(d.name)) = 'MANAJEMEN'
GROUP BY c.id, c.component_name, c.operational_division_id, d.name;


-- ============================================================
-- 3. Monthly stock MANAJEMEN bulan ini
-- ============================================================
SELECT
  dms.id,
  dms.month_key,
  dms.destination_type,
  COALESCE(mi.item_name,    '-') AS item_name,
  COALESCE(mm.material_name,'-') AS material_name,
  COALESCE(dms.profile_key, '(null)') AS profile_key,
  dms.closing_qty_content,
  dms.source_mode,
  dms.last_movement_date
FROM inv_division_monthly_stock dms
JOIN mst_operational_division d ON d.id = dms.division_id
LEFT JOIN mst_item     mi ON mi.id = dms.item_id
LEFT JOIN mst_material mm ON mm.id = dms.material_id
WHERE UPPER(TRIM(d.name)) = 'MANAJEMEN'
  AND dms.month_key = DATE_FORMAT(CURDATE(), '%Y-%m-01')
ORDER BY dms.closing_qty_content DESC;


-- ============================================================
-- 4. Ringkasan monthly stock MANAJEMEN semua bulan
-- ============================================================
SELECT
  dms.month_key,
  COUNT(*)                     AS row_count,
  SUM(dms.closing_qty_content) AS total_qty
FROM inv_division_monthly_stock dms
JOIN mst_operational_division d ON d.id = dms.division_id
WHERE UPPER(TRIM(d.name)) = 'MANAJEMEN'
GROUP BY dms.month_key
ORDER BY dms.month_key DESC;
