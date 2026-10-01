SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Insert monthly stock rows yang hilang
-- Hanya untuk identitas dengan profile_key (identity_key = profile_key, sesuai PHP)
-- closing_qty diambil dari kumulatif movement log
-- avg_cost diambil dari FIFO lot atau movement log terakhir
INSERT INTO inv_division_monthly_stock (
  month_key,
  division_id,
  destination_type,
  stock_domain,
  identity_key,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  profile_name,
  opening_qty_buy,
  opening_qty_content,
  opening_total_value,
  in_qty_buy,
  in_qty_content,
  in_total_value,
  out_qty_buy,
  out_qty_content,
  out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy,
  closing_qty_content,
  avg_cost_per_content,
  total_value,
  movement_day_count,
  mutation_count,
  last_movement_date,
  last_movement_at,
  source_mode,
  notes
)
SELECT
  @cur_month,
  agg.division_id,
  agg.destination_type,
  NULL,                          -- stock_domain nullable setelah 04g
  agg.profile_key,               -- identity_key = profile_key (PHP formula)
  agg.item_id,
  agg.material_id,
  agg.buy_uom_id,
  agg.content_uom_id,
  agg.profile_key,
  COALESCE(
    (SELECT catalog_name FROM mst_purchase_catalog WHERE profile_key = agg.profile_key AND is_active = 1 LIMIT 1),
    (SELECT catalog_name FROM mst_purchase_catalog WHERE profile_key = agg.profile_key LIMIT 1),
    (SELECT item_name FROM mst_item WHERE id = agg.item_id LIMIT 1),
    '-'
  )                              AS profile_name,
  0, 0, 0,                       -- opening qty/value
  ROUND(agg.in_buy, 4),
  ROUND(agg.in_content, 4),
  ROUND(agg.in_value, 2),
  ROUND(agg.out_buy, 4),
  ROUND(agg.out_content, 4),
  ROUND(agg.out_value, 2),
  0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0, 0,0,0,
  ROUND(agg.cumulative_buy, 4),
  ROUND(agg.cumulative_content, 4),
  agg.avg_cost,
  ROUND(agg.cumulative_content * agg.avg_cost, 2),
  agg.day_count,
  agg.mutation_count,
  agg.last_movement_date,
  agg.last_movement_at,
  'REBUILD',
  'Backfill missing monthly row from movement log 2026-06-07'
FROM (
  SELECT
    ml.division_id,
    ml.destination_type,
    ml.item_id,
    ml.material_id,
    ml.buy_uom_id,
    ml.content_uom_id,
    ml.profile_key,
    ROUND(SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_buy_delta     ELSE 0 END), 4) AS in_buy,
    ROUND(SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END), 4) AS in_content,
    ROUND(SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta * ml.unit_cost ELSE 0 END), 2) AS in_value,
    ROUND(SUM(CASE WHEN ml.qty_content_delta < 0 THEN ABS(ml.qty_buy_delta)     ELSE 0 END), 4) AS out_buy,
    ROUND(SUM(CASE WHEN ml.qty_content_delta < 0 THEN ABS(ml.qty_content_delta) ELSE 0 END), 4) AS out_content,
    ROUND(SUM(CASE WHEN ml.qty_content_delta < 0 THEN ABS(ml.qty_content_delta) * ml.unit_cost ELSE 0 END), 2) AS out_value,
    ROUND(SUM(ml.qty_content_delta), 4)  AS cumulative_content,
    ROUND(SUM(ml.qty_buy_delta), 4)      AS cumulative_buy,
    ROUND(
      CASE
        WHEN SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END) > 0
          THEN SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta * ml.unit_cost ELSE 0 END)
               / SUM(CASE WHEN ml.qty_content_delta > 0 THEN ml.qty_content_delta ELSE 0 END)
        ELSE 0
      END,
    6) AS avg_cost,
    COUNT(DISTINCT DATE(ml.movement_date)) AS day_count,
    COUNT(*)                               AS mutation_count,
    MAX(DATE(ml.movement_date))            AS last_movement_date,
    MAX(ml.created_at)                     AS last_movement_at
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
    AND ml.profile_key IS NOT NULL
    AND ml.profile_key != ''
  GROUP BY
    ml.division_id, ml.destination_type, ml.item_id,
    ml.material_id, ml.buy_uom_id, ml.content_uom_id, ml.profile_key
  HAVING ABS(ROUND(SUM(ml.qty_content_delta), 4)) > 0.001
) agg
WHERE NOT EXISTS (
  SELECT 1
  FROM inv_division_monthly_stock dms
  WHERE dms.month_key       = @cur_month
    AND dms.division_id     = agg.division_id
    AND dms.destination_type = agg.destination_type
    AND dms.identity_key    = agg.profile_key
);

SELECT ROW_COUNT() AS rows_inserted;
