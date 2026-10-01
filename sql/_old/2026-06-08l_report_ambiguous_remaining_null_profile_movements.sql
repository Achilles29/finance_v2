SET NAMES utf8mb4;

-- ============================================================
-- REPORT: ambiguous remaining null-profile movement groups
-- Tanggal: 2026-06-08
--
-- Tujuan:
-- - menampilkan group movement null-profile yang masih tersisa
-- - menampilkan kandidat profile exact dari catalog
-- - membantu keputusan canonical profile per item
-- ============================================================

SELECT
  od.name AS division_name,
  g.division_id,
  g.destination_type,
  g.item_id,
  i.item_code,
  i.item_name,
  g.material_id,
  m.material_code,
  m.material_name,
  g.buy_uom_id,
  g.content_uom_id,
  g.movement_count,
  g.cumulative_qty,
  COALESCE(c.exact_active_count, 0) AS exact_active_count,
  COALESCE(c.exact_total_count, 0) AS exact_total_count,
  COALESCE(c.exact_candidates, '') AS exact_candidates
FROM (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    COUNT(*) AS movement_count,
    ROUND(SUM(qty_content_delta), 4) AS cumulative_qty
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND material_id IS NOT NULL
    AND COALESCE(profile_key, '') = ''
  GROUP BY
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id
) g
LEFT JOIN mst_operational_division od ON od.id = g.division_id
LEFT JOIN mst_item i ON i.id = g.item_id
LEFT JOIN mst_material m ON m.id = g.material_id
LEFT JOIN (
  SELECT
    item_id,
    buy_uom_id,
    content_uom_id,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS exact_active_count,
    COUNT(*) AS exact_total_count,
    GROUP_CONCAT(
      CONCAT(
        profile_key,
        '|active=', is_active,
        '|brand=', COALESCE(brand_name, '-'),
        '|desc=', COALESCE(line_description, '-'),
        '|cpb=', content_per_buy
      )
      SEPARATOR ' || '
    ) AS exact_candidates
  FROM mst_purchase_catalog
  GROUP BY item_id, buy_uom_id, content_uom_id
) c ON
    c.item_id = g.item_id
AND c.buy_uom_id = g.buy_uom_id
AND c.content_uom_id = g.content_uom_id
ORDER BY ABS(g.cumulative_qty) DESC, g.movement_count DESC;
