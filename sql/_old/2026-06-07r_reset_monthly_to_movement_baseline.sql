SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- ============================================================
-- Step 1: UPDATE closing_qty untuk row yang ada tapi nilainya salah
--         (hanya material_id != NULL)
-- ============================================================
UPDATE inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id      = dms.division_id
AND latest.destination_type  = dms.destination_type
AND latest.identity_key      = dms.identity_key
AND latest.max_month         = dms.month_key
INNER JOIN (
  SELECT
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id,0), COALESCE(material_id,0),
    COALESCE(buy_uom_id,0), content_uom_id, COALESCE(profile_key,'')
) mv ON
    mv.division_id      = dms.division_id
AND mv.destination_type  = dms.destination_type
AND mv.item_id           = COALESCE(dms.item_id,    0)
AND mv.material_id       = COALESCE(dms.material_id,0)
AND mv.buy_uom_id        = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id    = dms.content_uom_id
AND mv.profile_key       = COALESCE(dms.profile_key,'')
SET
  dms.closing_qty_content = ROUND(mv.cumulative_content, 4),
  dms.closing_qty_buy     = ROUND(mv.cumulative_buy, 4),
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2)
WHERE dms.month_key = @cur_month
  AND dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;

SELECT ROW_COUNT() AS step1_rows_updated;

-- ============================================================
-- Step 2: INSERT row yang hilang (profile_key + material_id != NULL,
--         cumulative movement > 0, belum ada di monthly stock)
-- ============================================================
INSERT INTO inv_division_monthly_stock (
  month_key, division_id, destination_type, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name,
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
  closing_qty_buy, closing_qty_content,
  avg_cost_per_content, total_value,
  movement_day_count, mutation_count,
  last_movement_date, last_movement_at,
  source_mode, notes
)
SELECT
  @cur_month,
  agg.division_id, agg.destination_type,
  NULL,
  agg.profile_key,
  agg.item_id, agg.material_id, agg.buy_uom_id, agg.content_uom_id,
  agg.profile_key,
  COALESCE(
    (SELECT catalog_name FROM mst_purchase_catalog c
     WHERE c.profile_key = agg.profile_key AND c.is_active = 1 LIMIT 1),
    (SELECT catalog_name FROM mst_purchase_catalog c
     WHERE c.profile_key = agg.profile_key LIMIT 1),
    (SELECT item_name FROM mst_item WHERE id = agg.item_id LIMIT 1),
    '-'
  ),
  0, 0, 0,
  ROUND(agg.in_content_qty, 4) / GREATEST(COALESCE(
    (SELECT content_per_buy FROM mst_purchase_catalog WHERE profile_key = agg.profile_key LIMIT 1), 1), 0.0001),
  ROUND(agg.in_content_qty, 4),
  ROUND(agg.in_content_qty * agg.avg_cost, 2),
  ROUND(ABS(agg.out_content_qty), 4) / GREATEST(COALESCE(
    (SELECT content_per_buy FROM mst_purchase_catalog WHERE profile_key = agg.profile_key LIMIT 1), 1), 0.0001),
  ROUND(ABS(agg.out_content_qty), 4),
  ROUND(ABS(agg.out_content_qty) * agg.avg_cost, 2),
  0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0,
  ROUND(agg.cumulative_content / GREATEST(COALESCE(
    (SELECT content_per_buy FROM mst_purchase_catalog WHERE profile_key = agg.profile_key LIMIT 1), 1), 0.0001), 4),
  ROUND(agg.cumulative_content, 4),
  agg.avg_cost,
  ROUND(agg.cumulative_content * agg.avg_cost, 2),
  agg.day_count, agg.mutation_count,
  agg.last_date, agg.last_at,
  'REBUILD',
  'Reset to movement baseline 2026-06-07'
FROM (
  SELECT
    ml.division_id, ml.destination_type,
    ml.item_id, ml.material_id, ml.buy_uom_id, ml.content_uom_id,
    ml.profile_key,
    ROUND(SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END), 4) AS in_content_qty,
    ROUND(SUM(CASE WHEN ml.qty_content_delta < 0 THEN ml.qty_content_delta ELSE 0 END), 4) AS out_content_qty,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content,
    ROUND(
      CASE WHEN SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END) > 0
        THEN SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta * ml.unit_cost ELSE 0 END)
             / SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END)
        ELSE 0 END, 6) AS avg_cost,
    COUNT(DISTINCT DATE(ml.movement_date)) AS day_count,
    COUNT(*) AS mutation_count,
    MAX(DATE(ml.movement_date)) AS last_date,
    MAX(ml.created_at) AS last_at
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
    AND ml.profile_key IS NOT NULL AND ml.profile_key != ''
    AND ml.material_id IS NOT NULL
  GROUP BY ml.division_id, ml.destination_type, ml.item_id,
    ml.material_id, ml.buy_uom_id, ml.content_uom_id, ml.profile_key
  HAVING ROUND(SUM(ml.qty_content_delta), 4) > 0.001
) agg
WHERE NOT EXISTS (
  SELECT 1 FROM inv_division_monthly_stock dms
  WHERE dms.month_key       = @cur_month
    AND dms.division_id     = agg.division_id
    AND dms.destination_type = agg.destination_type
    AND dms.identity_key    = agg.profile_key
);

SELECT ROW_COUNT() AS step2_rows_inserted;

-- ============================================================
-- Verifikasi: berapa mismatch tersisa (hanya material_id != NULL)
-- ============================================================
SELECT
  COUNT(*) AS sisa_mismatch_material
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key = dms.identity_key
AND latest.max_month = dms.month_key
INNER JOIN (
  SELECT
    division_id, destination_type,
    COALESCE(item_id,0) AS item_id, COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id,0) AS buy_uom_id, content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta),0) AS cumulative_content
  FROM inv_stock_movement_log WHERE movement_scope='DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id,0), COALESCE(material_id,0),
    COALESCE(buy_uom_id,0), content_uom_id, COALESCE(profile_key,'')
) mv ON
    mv.division_id = dms.division_id AND mv.destination_type = dms.destination_type
AND mv.item_id = COALESCE(dms.item_id,0) AND mv.material_id = COALESCE(dms.material_id,0)
AND mv.buy_uom_id = COALESCE(dms.buy_uom_id,0) AND mv.content_uom_id = dms.content_uom_id
AND mv.profile_key = COALESCE(dms.profile_key,'')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;
