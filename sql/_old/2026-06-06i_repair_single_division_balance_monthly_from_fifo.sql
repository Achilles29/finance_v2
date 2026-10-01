SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06i_repair_single_division_balance_monthly_from_fifo.sql
-- Tujuan :
-- 1) Memulihkan 1 profile stok divisi yang hilang dari reader
--    inv_division_stock_balance dan inv_division_monthly_stock
-- 2) Mengambil saldo truth dari FIFO lot divisi
-- 3) Mengisi ulang ringkasan monthly dari movement log bulan aktif
--
-- Default target:
-- - divisi 3 / KITCHEN
-- - item/material KENTANG
-- ============================================================

START TRANSACTION;

SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @target_division_id := 3;
SET @target_destination_type := 'KITCHEN';
SET @target_item_name := 'KENTANG';
SET @target_material_name := 'KENTANG';
SET @repair_tag := 'Repair single division balance+monthly from FIFO 2026-06-06';

SET @target_item_id := (
  SELECT id
  FROM mst_item
  WHERE UPPER(TRIM(item_name)) = UPPER(TRIM(@target_item_name))
  ORDER BY id ASC
  LIMIT 1
);

SET @target_material_id := (
  SELECT COALESCE(material_id, 0)
  FROM mst_item
  WHERE id = @target_item_id
  LIMIT 1
);

SET @target_material_id := CASE
  WHEN COALESCE(@target_material_id, 0) > 0 THEN @target_material_id
  ELSE (
    SELECT id
    FROM mst_material
    WHERE UPPER(TRIM(material_name)) = UPPER(TRIM(@target_material_name))
    ORDER BY id ASC
    LIMIT 1
  )
END;

SET @guard_message := NULL;
SET @guard_message := IF(@target_division_id <= 0, 'target_division_id wajib diisi.', @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND (@target_destination_type IS NULL OR TRIM(@target_destination_type) = ''), 'target_destination_type wajib diisi.', @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND COALESCE(@target_item_id, 0) <= 0 AND COALESCE(@target_material_id, 0) <= 0, 'Item/material target tidak ditemukan.', @guard_message);

SET @sql_guard := IF(
  @guard_message IS NOT NULL,
  CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''', REPLACE(@guard_message, '''', ''''''), ''''),
  'SELECT 1'
);
PREPARE stmt_guard FROM @sql_guard; EXECUTE stmt_guard; DEALLOCATE PREPARE stmt_guard;

DROP TEMPORARY TABLE IF EXISTS tmp_single_division_fifo_truth;
CREATE TEMPORARY TABLE tmp_single_division_fifo_truth AS
SELECT
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, @target_item_id) AS item_id,
  COALESCE(l.material_id, @target_material_id) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
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
  ) AS avg_cost_per_content,
  MAX(CASE WHEN l.qty_balance > 0 THEN l.receipt_line_id ELSE NULL END) AS last_receipt_line_id
FROM inv_material_fifo_lot l
WHERE l.location_scope = 'DIVISION'
  AND l.division_id = @target_division_id
  AND l.destination_type = @target_destination_type
  AND (
    l.item_id = @target_item_id
    OR l.material_id = @target_material_id
  )
GROUP BY
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, @target_item_id),
  COALESCE(l.material_id, @target_material_id),
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key
HAVING ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) > 0;

ALTER TABLE tmp_single_division_fifo_truth
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_single_division_profile_meta;
CREATE TEMPORARY TABLE tmp_single_division_profile_meta AS
SELECT
  f.division_id,
  f.destination_type,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  COALESCE(
    (SELECT c.catalog_name FROM mst_purchase_catalog c WHERE c.profile_key = f.profile_key LIMIT 1),
    (SELECT i.item_name FROM mst_item i WHERE i.id = f.item_id LIMIT 1),
    (SELECT m.material_name FROM mst_material m WHERE m.id = f.material_id LIMIT 1)
  ) AS profile_name,
  COALESCE(
    NULLIF((SELECT c.brand_name FROM mst_purchase_catalog c WHERE c.profile_key = f.profile_key LIMIT 1), ''),
    'NO MERK'
  ) AS profile_brand,
  NULL AS profile_description,
  NULL AS profile_expired_date,
  CASE
    WHEN f.buy_uom_id IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN 1.000000
    ELSE COALESCE((SELECT c.content_per_buy FROM mst_purchase_catalog c WHERE c.profile_key = f.profile_key LIMIT 1), 1.000000)
  END AS profile_content_per_buy,
  (SELECT u.code FROM mst_uom u WHERE u.id = f.buy_uom_id LIMIT 1) AS profile_buy_uom_code,
  (SELECT u.code FROM mst_uom u WHERE u.id = f.content_uom_id LIMIT 1) AS profile_content_uom_code,
  CASE
    WHEN EXISTS (
      SELECT 1 FROM mst_purchase_catalog c
      WHERE c.profile_key = f.profile_key
        AND UPPER(COALESCE(c.line_kind, '')) = 'MATERIAL'
      LIMIT 1
    ) THEN 'MATERIAL'
    ELSE 'ITEM'
  END AS stock_domain
FROM tmp_single_division_fifo_truth f;

ALTER TABLE tmp_single_division_profile_meta
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_single_division_movement_month;
CREATE TEMPORARY TABLE tmp_single_division_movement_month AS
SELECT
  ml.division_id,
  ml.destination_type,
  COALESCE(ml.item_id, @target_item_id) AS item_id,
  COALESCE(ml.material_id, @target_material_id) AS material_id,
  ml.buy_uom_id,
  ml.content_uom_id,
  ml.profile_key,
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
WHERE ml.movement_scope = 'DIVISION'
  AND ml.division_id = @target_division_id
  AND ml.destination_type = @target_destination_type
  AND ml.movement_date >= @target_month
  AND (
    ml.item_id = @target_item_id
    OR ml.material_id = @target_material_id
  )
GROUP BY
  ml.division_id,
  ml.destination_type,
  COALESCE(ml.item_id, @target_item_id),
  COALESCE(ml.material_id, @target_material_id),
  ml.buy_uom_id,
  ml.content_uom_id,
  ml.profile_key;

ALTER TABLE tmp_single_division_movement_month
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DELETE b
FROM inv_division_stock_balance b
LEFT JOIN tmp_single_division_fifo_truth f
  ON f.division_id = b.division_id
 AND f.destination_type = b.destination_type
 AND f.item_id <=> b.item_id
 AND f.material_id <=> b.material_id
 AND f.buy_uom_id <=> b.buy_uom_id
 AND f.content_uom_id = b.content_uom_id
 AND f.profile_key <=> b.profile_key
WHERE b.division_id = @target_division_id
  AND b.destination_type = @target_destination_type
  AND (
    b.item_id = @target_item_id
    OR b.material_id = @target_material_id
  )
  AND f.profile_key IS NULL;

INSERT INTO inv_division_stock_balance (
  division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id,
  qty_buy_balance, qty_content_balance,
  profile_key, profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  avg_cost_per_content, last_receipt_line_id, notes
)
SELECT
  f.division_id,
  f.destination_type,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  CASE
    WHEN COALESCE(pm.profile_content_per_buy, 1) > 0
      THEN ROUND(f.qty_content_balance / pm.profile_content_per_buy, 4)
    ELSE f.qty_content_balance
  END AS qty_buy_balance,
  f.qty_content_balance,
  f.profile_key,
  pm.profile_name,
  pm.profile_brand,
  pm.profile_description,
  pm.profile_expired_date,
  pm.profile_content_per_buy,
  pm.profile_buy_uom_code,
  pm.profile_content_uom_code,
  f.avg_cost_per_content,
  f.last_receipt_line_id,
  @repair_tag
FROM tmp_single_division_fifo_truth f
JOIN tmp_single_division_profile_meta pm
  ON pm.division_id = f.division_id
 AND pm.destination_type = f.destination_type
 AND pm.item_id <=> f.item_id
 AND pm.material_id <=> f.material_id
 AND pm.buy_uom_id <=> f.buy_uom_id
 AND pm.content_uom_id = f.content_uom_id
 AND pm.profile_key <=> f.profile_key
ON DUPLICATE KEY UPDATE
  qty_buy_balance = VALUES(qty_buy_balance),
  qty_content_balance = VALUES(qty_content_balance),
  profile_name = VALUES(profile_name),
  profile_brand = VALUES(profile_brand),
  profile_description = VALUES(profile_description),
  profile_expired_date = VALUES(profile_expired_date),
  profile_content_per_buy = VALUES(profile_content_per_buy),
  profile_buy_uom_code = VALUES(profile_buy_uom_code),
  profile_content_uom_code = VALUES(profile_content_uom_code),
  avg_cost_per_content = VALUES(avg_cost_per_content),
  last_receipt_line_id = VALUES(last_receipt_line_id),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

DELETE m
FROM inv_division_monthly_stock m
LEFT JOIN tmp_single_division_fifo_truth f
  ON f.division_id = m.division_id
 AND f.destination_type = m.destination_type
 AND f.profile_key <=> m.profile_key
 AND f.buy_uom_id <=> m.buy_uom_id
 AND f.content_uom_id = m.content_uom_id
WHERE m.month_key = @target_month
  AND m.division_id = @target_division_id
  AND m.destination_type = @target_destination_type
  AND (
    m.item_id = @target_item_id
    OR m.material_id = @target_material_id
  )
  AND f.profile_key IS NULL;

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
  pm.stock_domain,
  COALESCE(f.profile_key, SHA2(CONCAT_WS('|', f.division_id, f.destination_type, f.item_id, f.material_id, f.buy_uom_id, f.content_uom_id), 256)) AS identity_key,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  pm.profile_name,
  pm.profile_brand,
  pm.profile_description,
  pm.profile_expired_date,
  pm.profile_content_per_buy,
  pm.profile_buy_uom_code,
  pm.profile_content_uom_code,
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
    WHEN COALESCE(pm.profile_content_per_buy, 1) > 0
      THEN ROUND(f.qty_content_balance / pm.profile_content_per_buy, 4)
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
FROM tmp_single_division_fifo_truth f
JOIN tmp_single_division_profile_meta pm
  ON pm.division_id = f.division_id
 AND pm.destination_type = f.destination_type
 AND pm.item_id <=> f.item_id
 AND pm.material_id <=> f.material_id
 AND pm.buy_uom_id <=> f.buy_uom_id
 AND pm.content_uom_id = f.content_uom_id
 AND pm.profile_key <=> f.profile_key
LEFT JOIN tmp_single_division_movement_month mm
  ON mm.division_id = f.division_id
 AND mm.destination_type = f.destination_type
 AND mm.item_id <=> f.item_id
 AND mm.material_id <=> f.material_id
 AND mm.buy_uom_id <=> f.buy_uom_id
 AND mm.content_uom_id = f.content_uom_id
 AND mm.profile_key <=> f.profile_key
ON DUPLICATE KEY UPDATE
  stock_domain = VALUES(stock_domain),
  item_id = VALUES(item_id),
  material_id = VALUES(material_id),
  buy_uom_id = VALUES(buy_uom_id),
  content_uom_id = VALUES(content_uom_id),
  profile_key = VALUES(profile_key),
  profile_name = VALUES(profile_name),
  profile_brand = VALUES(profile_brand),
  profile_description = VALUES(profile_description),
  profile_expired_date = VALUES(profile_expired_date),
  profile_content_per_buy = VALUES(profile_content_per_buy),
  profile_buy_uom_code = VALUES(profile_buy_uom_code),
  profile_content_uom_code = VALUES(profile_content_uom_code),
  opening_qty_buy = VALUES(opening_qty_buy),
  opening_qty_content = VALUES(opening_qty_content),
  opening_total_value = VALUES(opening_total_value),
  in_qty_buy = VALUES(in_qty_buy),
  in_qty_content = VALUES(in_qty_content),
  in_total_value = VALUES(in_total_value),
  out_qty_buy = VALUES(out_qty_buy),
  out_qty_content = VALUES(out_qty_content),
  out_total_value = VALUES(out_total_value),
  discarded_qty_buy = VALUES(discarded_qty_buy),
  discarded_qty_content = VALUES(discarded_qty_content),
  discarded_total_value = VALUES(discarded_total_value),
  spoil_qty_buy = VALUES(spoil_qty_buy),
  spoil_qty_content = VALUES(spoil_qty_content),
  spoilage_total_value = VALUES(spoilage_total_value),
  waste_qty_buy = VALUES(waste_qty_buy),
  waste_qty_content = VALUES(waste_qty_content),
  waste_total_value = VALUES(waste_total_value),
  process_loss_qty_buy = VALUES(process_loss_qty_buy),
  process_loss_qty_content = VALUES(process_loss_qty_content),
  process_loss_total_value = VALUES(process_loss_total_value),
  variance_qty_buy = VALUES(variance_qty_buy),
  variance_qty_content = VALUES(variance_qty_content),
  variance_total_value = VALUES(variance_total_value),
  adjustment_plus_qty_buy = VALUES(adjustment_plus_qty_buy),
  adjustment_plus_qty_content = VALUES(adjustment_plus_qty_content),
  adjustment_plus_total_value = VALUES(adjustment_plus_total_value),
  adjustment_minus_qty_buy = VALUES(adjustment_minus_qty_buy),
  adjustment_minus_qty_content = VALUES(adjustment_minus_qty_content),
  adjustment_minus_total_value = VALUES(adjustment_minus_total_value),
  closing_qty_buy = VALUES(closing_qty_buy),
  closing_qty_content = VALUES(closing_qty_content),
  avg_cost_per_content = VALUES(avg_cost_per_content),
  total_value = VALUES(total_value),
  movement_day_count = VALUES(movement_day_count),
  mutation_count = VALUES(mutation_count),
  last_movement_date = VALUES(last_movement_date),
  last_movement_at = VALUES(last_movement_at),
  last_movement_table = VALUES(last_movement_table),
  last_movement_id = VALUES(last_movement_id),
  source_mode = VALUES(source_mode),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

SELECT
  'balance_rows' AS metric,
  COUNT(*) AS total_rows,
  ROUND(SUM(qty_content_balance), 4) AS qty_content_balance,
  ROUND(SUM(qty_content_balance * avg_cost_per_content), 2) AS total_value
FROM inv_division_stock_balance
WHERE division_id = @target_division_id
  AND destination_type = @target_destination_type
  AND (
    item_id = @target_item_id
    OR material_id = @target_material_id
  )
UNION ALL
SELECT
  'monthly_rows',
  COUNT(*),
  ROUND(SUM(closing_qty_content), 4),
  ROUND(SUM(total_value), 2)
FROM inv_division_monthly_stock
WHERE month_key = @target_month
  AND division_id = @target_division_id
  AND destination_type = @target_destination_type
  AND (
    item_id = @target_item_id
    OR material_id = @target_material_id
  );

COMMIT;
