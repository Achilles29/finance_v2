-- 2026-06-08n_report_inventory_material_daily_oreo_crumb_split.sql
-- Tujuan:
-- 1) Menjelaskan kenapa /inventory-material-daily bisa menampilkan OREO CRUMB lebih dari 1 baris.
-- 2) Menunjukkan bahwa saldo -280 berasal dari movement DIVISION identitas fallback,
--    bukan dari row monthly utama.
--
-- Urutan pakai di server:
-- 1. Jalankan file ini dulu untuk audit/read-only.
-- 2. Setelah review hasilnya, baru jalankan 2026-06-08o_prepare_ambiguous_null_profile_worktable.sql.

SET @material_id := 152;
SET @item_id := 125;

SELECT
  'MONTHLY_EXACT' AS section,
  s.id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(s.profile_key, '') AS profile_key,
  s.closing_qty_buy,
  s.closing_qty_content,
  s.avg_cost_per_content,
  s.total_value,
  COALESCE(s.source_mode, '') AS source_mode
FROM inv_division_monthly_stock s
WHERE s.item_id = @item_id
  AND s.material_id = @material_id
ORDER BY s.division_id, s.destination_type, s.month_key, s.id;

SELECT
  'MOVEMENT_GROUP' AS section,
  l.division_id,
  COALESCE(l.destination_type, '') AS destination_type,
  l.item_id,
  COALESCE(l.material_id, 0) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  COUNT(*) AS movement_count,
  ROUND(SUM(l.qty_buy_delta), 4) AS qty_buy_delta_sum,
  ROUND(SUM(l.qty_content_delta), 4) AS qty_content_delta_sum,
  MIN(l.movement_date) AS first_movement_date,
  MAX(l.movement_date) AS last_movement_date
FROM inv_stock_movement_log l
WHERE l.movement_scope = 'DIVISION'
  AND l.item_id = @item_id
  AND COALESCE(l.material_id, 0) = @material_id
GROUP BY
  l.division_id,
  COALESCE(l.destination_type, ''),
  l.item_id,
  COALESCE(l.material_id, 0),
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '')
ORDER BY
  l.division_id,
  destination_type,
  l.buy_uom_id,
  l.content_uom_id,
  profile_key;

SELECT
  'BAR_NULL_PROFILE_DETAIL' AS section,
  l.id,
  l.movement_date,
  l.movement_type,
  l.division_id,
  COALESCE(l.destination_type, '') AS destination_type,
  l.item_id,
  COALESCE(l.material_id, 0) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  l.qty_buy_delta,
  l.qty_content_delta,
  COALESCE(l.ref_table, '') AS ref_table,
  l.ref_id,
  COALESCE(l.notes, '') AS notes
FROM inv_stock_movement_log l
WHERE l.movement_scope = 'DIVISION'
  AND l.division_id = 2
  AND COALESCE(l.destination_type, '') = 'BAR'
  AND l.item_id = @item_id
  AND COALESCE(l.material_id, 0) = @material_id
  AND l.buy_uom_id = 11
  AND l.content_uom_id = 11
  AND COALESCE(l.profile_key, '') = ''
ORDER BY l.movement_date, l.id;
