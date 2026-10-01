SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16h_audit_air_mineral_galon_cross_item_gap.sql
-- Tujuan :
-- 1) Mengaudit kasus residual AIR MINERAL GALON yang belum aman
--    untuk auto-repair karena tidak punya opening snapshot dan
--    tidak punya closing bulan lalu pada identity aktif item 220
-- 2) Menunjukkan sibling item dalam material yang sama agar bisa
--    diputuskan apakah ada carry-over stok legacy lintas item
-- 3) Menjadi dasar repair sempit manual / migrasi sibling stock
-- ============================================================

SET @target_month := '2026-06-01';
SET @division_id := 2;
SET @destination_type := 'BAR';
SET @material_id := 2;

SELECT
  i.id AS item_id,
  i.item_code,
  i.item_name,
  i.buy_uom_id,
  i.content_uom_id,
  i.content_per_buy,
  COALESCE(i.is_active, 1) AS is_active
FROM mst_item i
WHERE COALESCE(i.material_id, 0) = @material_id
ORDER BY COALESCE(i.is_active, 1) DESC, i.id;

SELECT
  month_key,
  item_id,
  profile_key,
  profile_name,
  opening_qty_buy,
  opening_qty_content,
  closing_qty_buy,
  closing_qty_content,
  notes
FROM inv_division_monthly_stock
WHERE division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND COALESCE(material_id, 0) = @material_id
ORDER BY month_key, item_id, profile_key;

SELECT
  snapshot_month,
  item_id,
  profile_key,
  profile_name,
  opening_qty_buy,
  opening_qty_content
FROM inv_division_stock_opening_snapshot
WHERE division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND COALESCE(material_id, 0) = @material_id
ORDER BY snapshot_month, item_id, profile_key;

SELECT
  profile_key,
  item_id,
  buy_uom_id,
  content_uom_id,
  ROUND(SUM(COALESCE(qty_balance, 0)), 4) AS lot_balance,
  COUNT(*) AS lot_rows
FROM inv_material_fifo_lot
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND COALESCE(material_id, 0) = @material_id
GROUP BY profile_key, item_id, buy_uom_id, content_uom_id
ORDER BY item_id, profile_key;

SELECT
  id,
  movement_date,
  movement_type,
  ref_table,
  ref_id,
  item_id,
  profile_key,
  profile_name,
  qty_content_delta,
  qty_content_after,
  qty_buy_delta,
  qty_buy_after,
  notes
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND COALESCE(material_id, 0) = @material_id
ORDER BY movement_date, id;
