SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06j_backfill_missing_division_monthly_from_fifo.sql
-- Tujuan :
-- 1) Menambahkan row inv_division_monthly_stock yang hilang,
--    tetapi saldo FIFO divisi masih positif
-- 2) Fokus hanya INSERT missing row, tidak menimpa row monthly yang sudah ada
-- 3) Dipakai untuk memulihkan visibility halaman inventory/stock/division
-- ============================================================

START TRANSACTION;

SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @repair_tag := 'Backfill missing division monthly from FIFO 2026-06-06';

DROP TEMPORARY TABLE IF EXISTS tmp_div_fifo_truth_backfill;
CREATE TEMPORARY TABLE tmp_div_fifo_truth_backfill AS
SELECT
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, 0) AS item_id,
  COALESCE(l.material_id, 0) AS material_id,
  COALESCE(l.buy_uom_id, 0) AS buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) AS qty_content_balance,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END), 2) AS total_value,
  ROUND(
    CASE
      WHEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END) > 0
      THEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END)
           / SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END)
      ELSE 0
    END,
    6
  ) AS avg_cost_per_content
FROM inv_material_fifo_lot l
WHERE l.location_scope = 'DIVISION'
GROUP BY
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, 0),
  COALESCE(l.material_id, 0),
  COALESCE(l.buy_uom_id, 0),
  l.content_uom_id,
  COALESCE(l.profile_key, '')
HAVING ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) > 0;

ALTER TABLE tmp_div_fifo_truth_backfill
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_div_fifo_missing_monthly;
CREATE TEMPORARY TABLE tmp_div_fifo_missing_monthly AS
SELECT f.*
FROM tmp_div_fifo_truth_backfill f
LEFT JOIN inv_division_monthly_stock m
  ON m.month_key = @target_month
 AND m.division_id = f.division_id
 AND m.destination_type = f.destination_type
 AND COALESCE(m.item_id, 0) = f.item_id
 AND COALESCE(m.material_id, 0) = f.material_id
 AND COALESCE(m.buy_uom_id, 0) = f.buy_uom_id
 AND m.content_uom_id = f.content_uom_id
 AND COALESCE(m.profile_key, '') = f.profile_key
WHERE m.id IS NULL;

ALTER TABLE tmp_div_fifo_missing_monthly
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_div_fifo_missing_meta;
CREATE TEMPORARY TABLE tmp_div_fifo_missing_meta AS
SELECT
  f.division_id,
  f.destination_type,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  COALESCE(
    NULLIF((SELECT c.catalog_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1), ''),
    NULLIF((SELECT c.catalog_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1), ''),
    NULLIF((SELECT i.item_name FROM mst_item i WHERE i.id = NULLIF(f.item_id, 0) LIMIT 1), ''),
    NULLIF((SELECT m.material_name FROM mst_material m WHERE m.id = NULLIF(f.material_id, 0) LIMIT 1), ''),
    'UNNAMED PROFILE'
  ) AS profile_name,
  COALESCE(
    NULLIF((SELECT c.brand_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1), ''),
    NULLIF((SELECT c.brand_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1), ''),
    'NO MERK'
  ) AS profile_brand,
  NULL AS profile_description,
  NULL AS profile_expired_date,
  CASE
    WHEN NULLIF(f.buy_uom_id, 0) IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN 1.000000
    ELSE COALESCE(
      (SELECT c.content_per_buy FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1),
      (SELECT c.content_per_buy FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1),
      1.000000
    )
  END AS profile_content_per_buy,
  (SELECT u.code FROM mst_uom u WHERE u.id = NULLIF(f.buy_uom_id, 0) LIMIT 1) AS profile_buy_uom_code,
  (SELECT u.code FROM mst_uom u WHERE u.id = f.content_uom_id LIMIT 1) AS profile_content_uom_code,
  CASE
    WHEN NULLIF(f.material_id, 0) IS NOT NULL THEN 'MATERIAL'
    ELSE 'ITEM'
  END AS stock_domain
FROM tmp_div_fifo_missing_monthly f;

ALTER TABLE tmp_div_fifo_missing_meta
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_div_fifo_missing_movement;
CREATE TEMPORARY TABLE tmp_div_fifo_missing_movement AS
SELECT
  ml.division_id,
  ml.destination_type,
  COALESCE(ml.item_id, 0) AS item_id,
  COALESCE(ml.material_id, 0) AS material_id,
  COALESCE(ml.buy_uom_id, 0) AS buy_uom_id,
  ml.content_uom_id,
  COALESCE(ml.profile_key, '') AS profile_key,
  ROUND(SUM(CASE
    WHEN ml.movement_type IN ('PURCHASE_IN', 'TRANSFER_IN', 'ADJUSTMENT_IN') THEN GREATEST(COALESCE(ml.qty_buy_delta, 0), 0)
    ELSE 0 END), 4) AS in_qty_buy,
  ROUND(SUM(CASE
    WHEN ml.movement_type IN ('PURCHASE_IN', 'TRANSFER_IN', 'ADJUSTMENT_IN') THEN GREATEST(COALESCE(ml.qty_content_delta, 0), 0)
    ELSE 0 END), 4) AS in_qty_content,
  ROUND(SUM(CASE
    WHEN ml.movement_type IN ('TRANSFER_OUT', 'USAGE_OUT', 'DISCARDED_OUT', 'SPOIL_OUT', 'WASTE_OUT', 'PROCESS_LOSS_OUT', 'VARIANCE_OUT')
      THEN ABS(LEAST(COALESCE(ml.qty_buy_delta, 0), 0))
    ELSE 0 END), 4) AS out_qty_buy,
  ROUND(SUM(CASE
    WHEN ml.movement_type IN ('TRANSFER_OUT', 'USAGE_OUT', 'DISCARDED_OUT', 'SPOIL_OUT', 'WASTE_OUT', 'PROCESS_LOSS_OUT', 'VARIANCE_OUT')
      THEN ABS(LEAST(COALESCE(ml.qty_content_delta, 0), 0))
    ELSE 0 END), 4) AS out_qty_content,
  ROUND(SUM(CASE WHEN COALESCE(ml.qty_buy_delta, 0) > 0 AND ml.movement_type = 'ADJUSTMENT' THEN COALESCE(ml.qty_buy_delta, 0) ELSE 0 END), 4) AS adjustment_plus_qty_buy,
  ROUND(SUM(CASE WHEN COALESCE(ml.qty_content_delta, 0) > 0 AND ml.movement_type = 'ADJUSTMENT' THEN COALESCE(ml.qty_content_delta, 0) ELSE 0 END), 4) AS adjustment_plus_qty_content,
  ROUND(SUM(CASE WHEN COALESCE(ml.qty_buy_delta, 0) < 0 AND ml.movement_type = 'ADJUSTMENT' THEN ABS(COALESCE(ml.qty_buy_delta, 0)) ELSE 0 END), 4) AS adjustment_minus_qty_buy,
  ROUND(SUM(CASE WHEN COALESCE(ml.qty_content_delta, 0) < 0 AND ml.movement_type = 'ADJUSTMENT' THEN ABS(COALESCE(ml.qty_content_delta, 0)) ELSE 0 END), 4) AS adjustment_minus_qty_content,
  COUNT(DISTINCT ml.movement_date) AS movement_day_count,
  COUNT(*) AS mutation_count,
  MAX(ml.movement_date) AS last_movement_date,
  MAX(ml.created_at) AS last_movement_at,
  SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(ml.ref_table, '') ORDER BY ml.id DESC SEPARATOR ','), ',', 1) AS last_movement_table,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(ml.ref_id, 0) ORDER BY ml.id DESC SEPARATOR ','), ',', 1) AS UNSIGNED) AS last_movement_id
FROM inv_stock_movement_log ml
JOIN tmp_div_fifo_missing_monthly f
  ON f.division_id = ml.division_id
 AND f.destination_type = ml.destination_type
 AND f.item_id = COALESCE(ml.item_id, 0)
 AND f.material_id = COALESCE(ml.material_id, 0)
 AND f.buy_uom_id = COALESCE(ml.buy_uom_id, 0)
 AND f.content_uom_id = ml.content_uom_id
 AND f.profile_key = COALESCE(ml.profile_key, '')
WHERE ml.movement_scope = 'DIVISION'
  AND ml.movement_date >= @target_month
GROUP BY
  ml.division_id,
  ml.destination_type,
  COALESCE(ml.item_id, 0),
  COALESCE(ml.material_id, 0),
  COALESCE(ml.buy_uom_id, 0),
  ml.content_uom_id,
  COALESCE(ml.profile_key, '');

ALTER TABLE tmp_div_fifo_missing_movement
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

INSERT INTO inv_division_monthly_stock (
  month_key, division_id, destination_type, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, last_movement_id, source_mode, notes
)
SELECT
  @target_month,
  f.division_id,
  f.destination_type,
  meta.stock_domain,
  SHA2(CONCAT_WS('|',
    meta.stock_domain,
    f.item_id,
    f.material_id,
    f.buy_uom_id,
    f.content_uom_id,
    f.profile_key,
    UPPER(TRIM(COALESCE(meta.profile_name, ''))),
    UPPER(TRIM(COALESCE(meta.profile_brand, ''))),
    '',
    '',
    ROUND(COALESCE(meta.profile_content_per_buy, 1), 6)
  ), 256) AS identity_key,
  NULLIF(f.item_id, 0),
  NULLIF(f.material_id, 0),
  NULLIF(f.buy_uom_id, 0),
  f.content_uom_id,
  NULLIF(f.profile_key, ''),
  meta.profile_name,
  meta.profile_brand,
  meta.profile_description,
  meta.profile_expired_date,
  meta.profile_content_per_buy,
  meta.profile_buy_uom_code,
  meta.profile_content_uom_code,
  0.0000,
  0.0000,
  0.00,
  COALESCE(mm.in_qty_buy, 0.0000),
  COALESCE(mm.in_qty_content, 0.0000),
  ROUND(COALESCE(mm.in_qty_content, 0) * f.avg_cost_per_content, 2),
  COALESCE(mm.out_qty_buy, 0.0000),
  COALESCE(mm.out_qty_content, 0.0000),
  ROUND(COALESCE(mm.out_qty_content, 0) * f.avg_cost_per_content, 2),
  0.0000,
  0.0000,
  0.00,
  0.0000,
  0.0000,
  0.00,
  0.0000,
  0.0000,
  0.00,
  0.0000,
  0.0000,
  0.00,
  0.0000,
  0.0000,
  0.00,
  COALESCE(mm.adjustment_plus_qty_buy, 0.0000),
  COALESCE(mm.adjustment_plus_qty_content, 0.0000),
  ROUND(COALESCE(mm.adjustment_plus_qty_content, 0) * f.avg_cost_per_content, 2),
  COALESCE(mm.adjustment_minus_qty_buy, 0.0000),
  COALESCE(mm.adjustment_minus_qty_content, 0.0000),
  ROUND(COALESCE(mm.adjustment_minus_qty_content, 0) * f.avg_cost_per_content, 2),
  CASE
    WHEN COALESCE(meta.profile_content_per_buy, 1) > 0
      THEN ROUND(f.qty_content_balance / meta.profile_content_per_buy, 4)
    ELSE f.qty_content_balance
  END AS closing_qty_buy,
  f.qty_content_balance,
  f.avg_cost_per_content,
  f.total_value,
  COALESCE(mm.movement_day_count, 0),
  COALESCE(mm.mutation_count, 0),
  mm.last_movement_date,
  mm.last_movement_at,
  mm.last_movement_table,
  mm.last_movement_id,
  'REBUILD',
  @repair_tag
FROM tmp_div_fifo_missing_monthly f
JOIN tmp_div_fifo_missing_meta meta
  ON meta.division_id = f.division_id
 AND meta.destination_type = f.destination_type
 AND meta.item_id = f.item_id
 AND meta.material_id = f.material_id
 AND meta.buy_uom_id = f.buy_uom_id
 AND meta.content_uom_id = f.content_uom_id
 AND meta.profile_key = f.profile_key
LEFT JOIN tmp_div_fifo_missing_movement mm
  ON mm.division_id = f.division_id
 AND mm.destination_type = f.destination_type
 AND mm.item_id = f.item_id
 AND mm.material_id = f.material_id
 AND mm.buy_uom_id = f.buy_uom_id
 AND mm.content_uom_id = f.content_uom_id
 AND mm.profile_key = f.profile_key;

SELECT
  'backfilled_monthly_rows' AS metric,
  COUNT(*) AS total_rows,
  ROUND(SUM(qty_content_balance), 4) AS qty_content_balance,
  ROUND(SUM(total_value), 2) AS total_value
FROM tmp_div_fifo_missing_monthly;

COMMIT;
