SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

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
    COALESCE(item_id,    0)  AS item_id,
    COALESCE(material_id,0)  AS material_id,
    COALESCE(buy_uom_id, 0)  AS buy_uom_id,
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
  AND ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;

SELECT ROW_COUNT() AS rows_updated;
