SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

SELECT
  d.name                              AS division_name,
  dms.destination_type,
  COALESCE(mi.item_name,    '-')      AS item_name,
  COALESCE(mm.material_name,'-')      AS material_name,
  dms.item_id,
  dms.material_id,
  COALESCE(dms.profile_key, '(null)') AS snap_profile_key,
  dms.closing_qty_content             AS snapshot_qty,
  COALESCE(mv_exact.cumulative_content, 0) AS movement_exact,
  mv_broad.cumulative_content_broad   AS movement_by_material,
  ROUND(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0), 4) AS selisih,
  CASE
    WHEN mv_exact.cumulative_content IS NULL            THEN 'NO_EXACT_MATCH'
    WHEN ABS(dms.closing_qty_content - mv_exact.cumulative_content) < 0.001 THEN 'MATCH'
    ELSE 'MISMATCH'
  END AS status_exact,
  dms.last_movement_date,
  dms.source_mode,
  dms.identity_key
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id      = dms.division_id
AND latest.destination_type  = dms.destination_type
AND latest.identity_key      = dms.identity_key
AND latest.max_month         = dms.month_key
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
) mv_exact ON
    mv_exact.division_id      = dms.division_id
AND mv_exact.destination_type  = dms.destination_type
AND mv_exact.item_id           = COALESCE(dms.item_id,    0)
AND mv_exact.material_id       = COALESCE(dms.material_id,0)
AND mv_exact.buy_uom_id        = COALESCE(dms.buy_uom_id, 0)
AND mv_exact.content_uom_id    = dms.content_uom_id
AND mv_exact.profile_key       = COALESCE(dms.profile_key,'')
LEFT JOIN (
  SELECT
    ml.division_id,
    ml.destination_type,
    COALESCE(ml.material_id, mi2.material_id) AS material_id,
    COALESCE(SUM(ml.qty_content_delta), 0) AS cumulative_content_broad
  FROM inv_stock_movement_log ml
  LEFT JOIN mst_item mi2 ON mi2.id = ml.item_id
  WHERE ml.movement_scope = 'DIVISION'
    AND (ml.material_id IS NOT NULL OR mi2.material_id IS NOT NULL)
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.material_id, mi2.material_id)
) mv_broad ON
    mv_broad.division_id      = dms.division_id
AND mv_broad.destination_type  = dms.destination_type
AND mv_broad.material_id       = dms.material_id
LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id  = dms.item_id
LEFT JOIN mst_material             mm ON mm.id  = dms.material_id
WHERE dms.month_key = @cur_month
  AND (
    mv_exact.cumulative_content IS NULL
    OR ABS(dms.closing_qty_content - mv_exact.cumulative_content) > 0.001
  )
ORDER BY ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) DESC;
