START TRANSACTION;

-- Repair BERAS KITCHEN Reguler Juli 2026.
--
-- Kondisi sebelum repair:
-- - Profile 1.000 GR/PACK: closing -27.000 GR karena POS fallback usage.
-- - Profile 10.000 GR/PACK: closing +27.000 GR karena beberapa adjustment plus.
-- - Secara fisik diminta final 0, nilai 0, LOT 0, dan movement per profile kembali sejalan.
--
-- Scope sengaja ketat:
-- - month_key Juli 2026
-- - division KITCHEN (id 3)
-- - destination_type KITCHEN / Reguler
-- - item BERAS id 30, material BERAS id 21

SET @month_key := '2026-07-01';
SET @repair_date := '2026-07-26';
SET @division_id := 3;
SET @destination_type := 'KITCHEN';
SET @item_id := 30;
SET @material_id := 21;

CREATE TABLE IF NOT EXISTS zz_bak_div_monthly_20260726b_beras_zero AS
SELECT *
FROM inv_division_monthly_stock
WHERE month_key = @month_key
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id;

CREATE TABLE IF NOT EXISTS zz_bak_material_fifo_lot_20260726b_beras_zero AS
SELECT *
FROM inv_material_fifo_lot
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND receipt_date BETWEEN @month_key AND LAST_DAY(@month_key);

CREATE TABLE IF NOT EXISTS zz_bak_stock_movement_20260726b_beras_zero AS
SELECT *
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND movement_date BETWEEN @month_key AND LAST_DAY(@month_key);

DROP TEMPORARY TABLE IF EXISTS tmp_beras_profile_movement_sum;
CREATE TEMPORARY TABLE tmp_beras_profile_movement_sum AS
SELECT
  s.profile_key,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_expired_date,
  s.profile_content_per_buy,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(SUM(ml.qty_content_delta), 0), 4) AS movement_qty,
  CASE
    WHEN ABS(COALESCE(s.profile_content_per_buy, 0)) > 0.000001
      THEN ROUND(COALESCE(SUM(ml.qty_content_delta), 0) / s.profile_content_per_buy, 4)
    ELSE 0
  END AS movement_qty_buy,
  CASE
    WHEN COALESCE(SUM(ml.qty_content_delta), 0) > 0 THEN -1
    WHEN COALESCE(SUM(ml.qty_content_delta), 0) < 0 THEN 1
    ELSE 0
  END AS repair_sign,
  CASE
    WHEN ABS(COALESCE(SUM(ml.qty_content_delta), 0)) > 0.000001
      THEN ROUND(ABS(COALESCE(SUM(ml.qty_content_delta), 0)), 4)
    ELSE 0
  END AS repair_qty_content
FROM inv_division_monthly_stock s
LEFT JOIN inv_stock_movement_log ml
  ON ml.movement_scope = 'DIVISION'
 AND ml.division_id = s.division_id
 AND ml.destination_type = s.destination_type
 AND ml.item_id = s.item_id
 AND ml.material_id = s.material_id
 AND ml.profile_key = s.profile_key
 AND ml.movement_date BETWEEN @month_key AND LAST_DAY(@month_key)
 AND ml.ref_table <> 'manual_repair_beras_zero'
WHERE s.month_key = @month_key
  AND s.division_id = @division_id
  AND s.destination_type = @destination_type
  AND s.item_id = @item_id
  AND s.material_id = @material_id
GROUP BY
  s.profile_key,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_expired_date,
  s.profile_content_per_buy,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.buy_uom_id,
  s.content_uom_id;

-- Movement koreksi per profile supaya proyeksi movement bulan berjalan menjadi 0.
INSERT INTO inv_stock_movement_log (
  movement_no,
  movement_date,
  movement_scope,
  division_id,
  destination_type,
  movement_type,
  adjustment_category,
  adjustment_reason_code,
  ref_table,
  ref_id,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  qty_buy_delta,
  qty_content_delta,
  qty_buy_after,
  qty_content_after,
  profile_key,
  profile_name,
  profile_brand,
  profile_description,
  profile_expired_date,
  profile_content_per_buy,
  profile_buy_uom_code,
  profile_content_uom_code,
  unit_cost,
  notes,
  created_by,
  created_at
)
SELECT
  CONCAT('MV20260726-BERAS-ZERO-', SUBSTRING(profile_key, 1, 8)) AS movement_no,
  @repair_date AS movement_date,
  'DIVISION' AS movement_scope,
  @division_id AS division_id,
  @destination_type AS destination_type,
  'ADJUSTMENT' AS movement_type,
  'VARIANCE' AS adjustment_category,
  'MANUAL_REPAIR' AS adjustment_reason_code,
  'manual_repair_beras_zero' AS ref_table,
  NULL AS ref_id,
  @item_id AS item_id,
  @material_id AS material_id,
  buy_uom_id,
  content_uom_id,
  ROUND((repair_sign * repair_qty_content) / GREATEST(COALESCE(NULLIF(profile_content_per_buy, 0), 1), 0.000001), 4) AS qty_buy_delta,
  ROUND(repair_sign * repair_qty_content, 4) AS qty_content_delta,
  0.0000 AS qty_buy_after,
  0.0000 AS qty_content_after,
  profile_key,
  profile_name,
  profile_brand,
  profile_description,
  profile_expired_date,
  profile_content_per_buy,
  profile_buy_uom_code,
  profile_content_uom_code,
  0.000000 AS unit_cost,
  'Manual repair 2026-07-26: reset BERAS KITCHEN regular to zero per profile' AS notes,
  NULL AS created_by,
  NOW() AS created_at
FROM tmp_beras_profile_movement_sum
WHERE repair_qty_content > 0
ON DUPLICATE KEY UPDATE
  qty_buy_delta = VALUES(qty_buy_delta),
  qty_content_delta = VALUES(qty_content_delta),
  qty_buy_after = VALUES(qty_buy_after),
  qty_content_after = VALUES(qty_content_after),
  notes = VALUES(notes),
  created_at = VALUES(created_at);

-- LOT final harus 0.
UPDATE inv_material_fifo_lot
SET qty_out = qty_in,
    qty_balance = 0.0000,
    status = 'CLOSED',
    updated_at = NOW()
WHERE location_scope = 'DIVISION'
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id
  AND receipt_date BETWEEN @month_key AND LAST_DAY(@month_key);

-- Monthly stock final harus 0. Riwayat in/out tetap dibiarkan sebagai histori,
-- tetapi closing/nilai sebagai sumber kebenaran stok akhir dibuat bersih.
UPDATE inv_division_monthly_stock
SET closing_qty_buy = 0.0000,
    closing_qty_content = 0.0000,
    avg_cost_per_content = 0.000000,
    total_value = 0.00,
    last_movement_date = @repair_date,
    last_movement_at = NOW(),
    last_movement_table = 'manual_repair_beras_zero',
    last_movement_id = NULL,
    source_mode = 'REBUILD',
    notes = LEFT(CONCAT(COALESCE(notes, ''), ' | Repair 2026-07-26: BERAS KITCHEN regular reset final qty/value/lot/movement to 0'), 255),
    updated_at = NOW()
WHERE month_key = @month_key
  AND division_id = @division_id
  AND destination_type = @destination_type
  AND item_id = @item_id
  AND material_id = @material_id;

COMMIT;
