SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-12b_inventory_same_uom_guard_weekly_recent_writes.sql
-- Tujuan :
-- 1) Guard audit mingguan untuk write recent yang masih mencoba
--    menyimpan profile same-UOM invalid
-- 2) Fokus ke 7 hari terakhir
-- 3) Membantu review cepat apakah masih ada jalur writer lama/manual
-- ============================================================

SET @week_start := DATE_SUB(CURDATE(), INTERVAL 7 DAY);

SELECT 'recent_invalid_catalog_active' AS metric, COUNT(*) AS total
FROM mst_purchase_catalog c
WHERE COALESCE(c.is_active, 1) = 1
  AND DATE(COALESCE(c.updated_at, c.created_at)) >= @week_start
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'recent_invalid_division_monthly', COUNT(*)
FROM inv_division_monthly_stock s
WHERE DATE(COALESCE(s.updated_at, s.created_at)) >= @week_start
  AND s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'recent_invalid_warehouse_monthly', COUNT(*)
FROM inv_warehouse_monthly_stock s
WHERE DATE(COALESCE(s.updated_at, s.created_at)) >= @week_start
  AND s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'recent_invalid_movement', COUNT(*)
FROM inv_stock_movement_log l
WHERE DATE(COALESCE(l.created_at, l.movement_date)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'recent_invalid_receipt_lines', COUNT(*)
FROM pur_purchase_receipt_line l
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.conversion_factor_to_content, 1) - 1) > 0.0001
UNION ALL
SELECT 'recent_invalid_po_lines', COUNT(*)
FROM pur_purchase_order_line l
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND (
    ABS(COALESCE(l.content_per_buy, 1) - 1) > 0.0001
    OR ABS(COALESCE(l.conversion_factor_to_content, 1) - 1) > 0.0001
  )
UNION ALL
SELECT 'recent_invalid_division_request_lines', COUNT(*)
FROM pur_division_request_line l
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001;

SELECT
  'MOVEMENT' AS source_table,
  l.id AS row_id,
  DATE(COALESCE(l.created_at, l.movement_date)) AS row_date,
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6) AS factor_value,
  l.profile_key,
  l.ref_table,
  l.ref_id,
  l.notes
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE DATE(COALESCE(l.created_at, l.movement_date)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT
  'RECEIPT_LINE',
  l.id,
  DATE(COALESCE(l.updated_at, l.created_at)),
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.conversion_factor_to_content, 1), 6),
  l.profile_key,
  'pur_purchase_receipt_line',
  l.purchase_receipt_id,
  l.notes
FROM pur_purchase_receipt_line l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.conversion_factor_to_content, 1) - 1) > 0.0001
UNION ALL
SELECT
  'PO_LINE',
  l.id,
  DATE(COALESCE(l.updated_at, l.created_at)),
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.content_per_buy, 1), 6),
  l.profile_key,
  'pur_purchase_order_line',
  l.purchase_order_id,
  l.notes
FROM pur_purchase_order_line l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND (
    ABS(COALESCE(l.content_per_buy, 1) - 1) > 0.0001
    OR ABS(COALESCE(l.conversion_factor_to_content, 1) - 1) > 0.0001
  )
UNION ALL
SELECT
  'DIV_REQUEST_LINE',
  l.id,
  DATE(COALESCE(l.updated_at, l.created_at)),
  l.item_id,
  i.item_name,
  l.material_id,
  m.material_name,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6),
  l.profile_key,
  'pur_division_request_line',
  l.request_id,
  l.notes
FROM pur_division_request_line l
LEFT JOIN mst_item i ON i.id = l.item_id
LEFT JOIN mst_material m ON m.id = l.material_id
WHERE DATE(COALESCE(l.updated_at, l.created_at)) >= @week_start
  AND l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
ORDER BY row_date DESC, source_table, row_id DESC;
