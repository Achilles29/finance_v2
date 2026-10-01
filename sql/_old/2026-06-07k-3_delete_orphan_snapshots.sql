SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

DELETE dms FROM inv_division_monthly_stock dms
LEFT JOIN (
  SELECT
    division_id, destination_type,
    COALESCE(item_id,    0)  AS item_id,
    COALESCE(material_id,0)  AS material_id,
    COALESCE(buy_uom_id, 0)  AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
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
WHERE dms.month_key = @cur_month
  AND (
    mv.cumulative_content IS NULL
    OR ABS(mv.cumulative_content) < 0.001
  );

SELECT ROW_COUNT() AS rows_deleted;
