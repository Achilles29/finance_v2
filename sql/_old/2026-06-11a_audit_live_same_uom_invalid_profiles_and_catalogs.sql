SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-11a_audit_live_same_uom_invalid_profiles_and_catalogs.sql
-- Tujuan :
-- 1) Audit profile live stok divisi yang kontradiktif:
--    buy_uom_id = content_uom_id tetapi profile_content_per_buy <> 1
-- 2) Fokuskan review ke 3 barang live yang saat ini terdampak:
--    KATSUOBUSHI, TEMPE, PLASTIK 2 KG
-- 3) Tampilkan juga radius master katalog aktif yang masih berpotensi
--    menulis profile invalid serupa ke depan
-- ============================================================

SELECT
  s.id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.item_id,
  i.item_name,
  s.material_id,
  m.material_name,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
  ROUND(COALESCE(s.closing_qty_buy, 0), 4) AS closing_qty_buy,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS closing_qty_content,
  ROUND(COALESCE(s.avg_cost_per_content, 0), 6) AS avg_cost_per_content,
  ROUND(COALESCE(s.total_value, 0), 2) AS total_value,
  s.notes
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = s.material_id
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
ORDER BY s.item_id, s.material_id, s.destination_type, s.id;

SELECT
  l.id,
  l.movement_no,
  l.movement_date,
  l.movement_scope,
  l.division_id,
  l.destination_type,
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
  ROUND(COALESCE(l.qty_buy_delta, 0), 4) AS qty_buy_delta,
  ROUND(COALESCE(l.qty_content_delta, 0), 4) AS qty_content_delta,
  ROUND(COALESCE(l.qty_buy_after, 0), 4) AS qty_buy_after,
  ROUND(COALESCE(l.qty_content_after, 0), 4) AS qty_content_after,
  ROUND(COALESCE(l.unit_cost, 0), 6) AS unit_cost,
  l.ref_table,
  l.ref_id
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
  AND (
    l.item_id IN (92, 247, 284)
    OR l.material_id IN (104, 201)
  )
ORDER BY l.item_id, l.material_id, l.id DESC;

SELECT
  f.id,
  f.lot_no,
  f.location_scope,
  f.division_id,
  f.destination_type,
  f.item_id,
  i.item_name,
  f.material_id,
  m.material_name,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  ROUND(COALESCE(f.qty_in, 0), 4) AS qty_in,
  ROUND(COALESCE(f.qty_out, 0), 4) AS qty_out,
  ROUND(COALESCE(f.qty_balance, 0), 4) AS qty_balance,
  ROUND(COALESCE(f.unit_cost, 0), 6) AS unit_cost,
  f.source_table,
  f.source_id,
  f.source_line_id,
  f.status
FROM inv_material_fifo_lot f
LEFT JOIN mst_item i ON i.id = f.item_id
LEFT JOIN mst_material m ON m.id = f.material_id
WHERE (
    f.item_id IN (92, 247, 284)
    OR f.material_id IN (104, 201)
  )
ORDER BY f.item_id, f.material_id, f.id;

SELECT
  c.id,
  c.profile_key,
  c.item_id,
  i.item_name,
  c.material_id,
  m.material_name,
  c.catalog_name,
  c.brand_name,
  c.buy_uom_id,
  c.content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6) AS content_per_buy,
  ROUND(COALESCE(c.standard_price, 0), 2) AS standard_price,
  ROUND(COALESCE(c.last_unit_price, 0), 2) AS last_unit_price,
  c.is_active,
  c.notes
FROM mst_purchase_catalog c
LEFT JOIN mst_item i ON i.id = c.item_id
LEFT JOIN mst_material m ON m.id = c.material_id
WHERE c.item_id IN (92, 247, 284)
   OR c.material_id IN (104, 201)
ORDER BY c.item_id, c.material_id, c.is_active DESC, c.id;

SELECT 'live_monthly_same_uom_invalid_rows' AS metric, COUNT(*) AS total
FROM inv_division_monthly_stock s
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'live_monthly_distinct_problem_profiles', COUNT(DISTINCT s.profile_key)
FROM inv_division_monthly_stock s
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'active_catalog_same_uom_invalid_rows', COUNT(*)
FROM mst_purchase_catalog c
WHERE COALESCE(c.is_active, 1) = 1
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;
