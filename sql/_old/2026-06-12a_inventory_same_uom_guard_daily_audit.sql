SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-12a_inventory_same_uom_guard_daily_audit.sql
-- Tujuan :
-- 1) Guard audit harian untuk profile inventory same-UOM yang invalid
-- 2) Mendeteksi kontradiksi: buy_uom_id = content_uom_id tetapi
--    content_per_buy / profile_content_per_buy <> 1
-- 3) Memberi ringkasan cepat tabel mana yang masih kotor
-- ============================================================

SELECT 'active_catalog_invalid' AS metric, COUNT(*) AS total
FROM mst_purchase_catalog
WHERE COALESCE(is_active, 1) = 1
  AND buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'division_monthly_invalid', COUNT(*)
FROM inv_division_monthly_stock
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'warehouse_monthly_invalid', COUNT(*)
FROM inv_warehouse_monthly_stock
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'division_opening_invalid', COUNT(*)
FROM inv_division_stock_opening_snapshot
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'warehouse_opening_invalid', COUNT(*)
FROM inv_warehouse_stock_opening_snapshot
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'unified_opening_invalid', COUNT(*)
FROM inv_stock_opening_snapshot
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'movement_invalid', COUNT(*)
FROM inv_stock_movement_log
WHERE buy_uom_id IS NOT NULL
  AND content_uom_id IS NOT NULL
  AND buy_uom_id = content_uom_id
  AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001;

SELECT
  'CATALOG' AS source_table,
  c.id AS row_id,
  c.item_id,
  i.item_name,
  c.material_id,
  m.material_name,
  c.buy_uom_id,
  c.content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6) AS profile_content_per_buy,
  c.profile_key,
  COALESCE(c.updated_at, c.created_at) AS row_time,
  c.notes
FROM mst_purchase_catalog c
LEFT JOIN mst_item i ON i.id = c.item_id
LEFT JOIN mst_material m ON m.id = c.material_id
WHERE COALESCE(c.is_active, 1) = 1
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT
  'DIV_MONTHLY',
  s.id,
  s.item_id,
  i.item_name,
  s.material_id,
  m.material_name,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  s.profile_key,
  COALESCE(s.updated_at, s.created_at),
  s.notes
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = s.material_id
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT
  'WH_MONTHLY',
  s.id,
  s.item_id,
  i.item_name,
  s.material_id,
  m.material_name,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  s.profile_key,
  COALESCE(s.updated_at, s.created_at),
  s.notes
FROM inv_warehouse_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = s.material_id
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT
  'MOVEMENT',
  l.id,
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6),
  l.profile_key,
  COALESCE(l.created_at, l.movement_date),
  l.notes
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
ORDER BY source_table, item_name, material_name, row_id;
