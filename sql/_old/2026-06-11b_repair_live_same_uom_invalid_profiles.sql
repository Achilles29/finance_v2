SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-11b_repair_live_same_uom_invalid_profiles.sql
-- Tujuan :
-- 1) Repair semua profile stok divisi live yang masih kontradiktif:
--    buy_uom_id = content_uom_id tetapi profile_content_per_buy <> 1
-- 2) Fokus pada 3 barang live yang saat ini terdampak:
--    KATSUOBUSHI, TEMPE, PLASTIK 2 KG
-- 3) Menormalkan histori pembelian / receipt / request / movement / opening
--    agar qty_buy kembali sejalan dengan qty_content pada same-UOM
--
-- Catatan:
-- - Script ini sengaja mempertahankan profile_key live yang sudah dipakai,
--   lalu menormalkan semantics konversinya menjadi cpb = 1.
-- - Harga satuan catalog/PO ikut diubah dari harga per pack legacy menjadi
--   harga per unit content aktual.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair live same-UOM invalid profiles 2026-06-11';

DROP TEMPORARY TABLE IF EXISTS tmp_live_same_uom_invalid_profiles;
CREATE TEMPORARY TABLE tmp_live_same_uom_invalid_profiles AS
SELECT
  s.profile_key,
  MAX(s.item_id) AS item_id,
  MAX(COALESCE(s.material_id, i.material_id)) AS material_id,
  MAX(s.buy_uom_id) AS buy_uom_id,
  MAX(s.content_uom_id) AS content_uom_id,
  ROUND(MAX(COALESCE(s.profile_content_per_buy, 1)), 6) AS old_cpb,
  MAX(s.profile_name) AS profile_name,
  MAX(s.profile_brand) AS profile_brand,
  MAX(s.profile_description) AS profile_description,
  MAX(COALESCE(NULLIF(s.profile_content_uom_code, ''), NULLIF(s.profile_buy_uom_code, ''), cu.code, bu.code)) AS target_uom_code,
  ROUND(MAX(COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), ROUND(COALESCE(s.avg_cost_per_content, 0) * COALESCE(s.profile_content_per_buy, 1), 2), 0)), 2) AS old_buy_price,
  ROUND(MAX(COALESCE(NULLIF(c.last_unit_price, 0), NULLIF(c.standard_price, 0), ROUND(COALESCE(s.avg_cost_per_content, 0) * COALESCE(s.profile_content_per_buy, 1), 2), 0)) / NULLIF(MAX(COALESCE(s.profile_content_per_buy, 1)), 0), 2) AS new_unit_price
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_purchase_catalog c ON c.profile_key = s.profile_key
LEFT JOIN mst_uom bu ON bu.id = s.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = s.content_uom_id
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
GROUP BY s.profile_key;

ALTER TABLE tmp_live_same_uom_invalid_profiles
  ADD PRIMARY KEY (profile_key);

UPDATE mst_purchase_catalog c
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = c.profile_key
SET
  c.content_per_buy = 1.000000,
  c.conversion_factor_to_content = 1.00000000,
  c.standard_price = CASE
    WHEN c.standard_price IS NULL THEN NULL
    ELSE ROUND(c.standard_price / NULLIF(p.old_cpb, 0), 2)
  END,
  c.last_unit_price = CASE
    WHEN c.last_unit_price IS NULL THEN NULL
    ELSE ROUND(c.last_unit_price / NULLIF(p.old_cpb, 0), 2)
  END,
  c.notes = LEFT(TRIM(CONCAT(
    COALESCE(c.notes, ''),
    CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | normalized same-UOM catalog in-place'
  )), 255),
  c.updated_at = CURRENT_TIMESTAMP
WHERE c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_division_monthly_stock s
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_stock_opening_snapshot s
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = s.profile_key
SET
  s.profile_content_per_buy = 1.000000,
  s.profile_buy_uom_code = COALESCE(p.target_uom_code, s.profile_buy_uom_code),
  s.profile_content_uom_code = COALESCE(p.target_uom_code, s.profile_content_uom_code),
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE inv_stock_movement_log l
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = l.profile_key
SET
  l.profile_content_per_buy = 1.000000,
  l.profile_buy_uom_code = COALESCE(p.target_uom_code, l.profile_buy_uom_code),
  l.profile_content_uom_code = COALESCE(p.target_uom_code, l.profile_content_uom_code),
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255)
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001;

UPDATE pur_purchase_order_line l
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = l.profile_key
SET
  l.content_per_buy = 1.000000,
  l.conversion_factor_to_content = 1.00000000,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0), 4),
  l.unit_price = CASE
    WHEN ABS(COALESCE(l.qty_content, 0)) > 0.0001 THEN ROUND(COALESCE(l.line_subtotal, 0) / NULLIF(l.qty_content, 0), 2)
    ELSE ROUND(COALESCE(l.unit_price, 0) / NULLIF(p.old_cpb, 0), 2)
  END,
  l.snapshot_buy_uom_code = COALESCE(p.target_uom_code, l.snapshot_buy_uom_code),
  l.snapshot_content_uom_code = COALESCE(p.target_uom_code, l.snapshot_content_uom_code),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.content_per_buy, 1) - 1) > 0.0001;

UPDATE pur_purchase_receipt_line l
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = l.profile_key
SET
  l.conversion_factor_to_content = 1.00000000,
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.conversion_factor_to_content, 1) - 1) > 0.0001;

UPDATE pur_division_request_line l
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = l.profile_key
SET
  l.profile_content_per_buy = 1.000000,
  l.profile_buy_uom_code = COALESCE(p.target_uom_code, l.profile_buy_uom_code),
  l.profile_content_uom_code = COALESCE(p.target_uom_code, l.profile_content_uom_code),
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  l.updated_at = CURRENT_TIMESTAMP
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001;

COMMIT;

SELECT 'target_profiles_repaired' AS metric, COUNT(*) AS total
FROM tmp_live_same_uom_invalid_profiles
UNION ALL
SELECT 'monthly_same_uom_invalid_remaining', COUNT(*)
FROM inv_division_monthly_stock s
WHERE s.buy_uom_id IS NOT NULL
  AND s.content_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'movement_same_uom_invalid_remaining', COUNT(*)
FROM inv_stock_movement_log l
WHERE l.buy_uom_id IS NOT NULL
  AND l.content_uom_id IS NOT NULL
  AND l.buy_uom_id = l.content_uom_id
  AND ABS(COALESCE(l.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT 'active_catalog_same_uom_invalid_remaining_for_live_profiles', COUNT(*)
FROM mst_purchase_catalog c
JOIN tmp_live_same_uom_invalid_profiles p ON p.profile_key = c.profile_key
WHERE COALESCE(c.is_active, 1) = 1
  AND c.buy_uom_id IS NOT NULL
  AND c.content_uom_id IS NOT NULL
  AND c.buy_uom_id = c.content_uom_id
  AND ABS(COALESCE(c.content_per_buy, 1) - 1) > 0.0001;
