SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Cari identitas di movement log yang punya saldo > 0
-- tapi TIDAK ada baris di monthly stock bulan ini
-- profile_key tidak null = identity_key = profile_key (sesuai logika PHP)
SELECT
  ml.division_id,
  d.name              AS division_name,
  ml.destination_type,
  ml.item_id,
  ml.material_id,
  ml.buy_uom_id,
  ml.content_uom_id,
  ml.profile_key,
  COALESCE(mi.item_name, '-')      AS item_name,
  COALESCE(mm.material_name, '-')  AS material_name,
  ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content,
  ROUND(SUM(ml.qty_buy_delta), 4)     AS cumulative_buy
FROM inv_stock_movement_log ml
LEFT JOIN mst_operational_division d  ON d.id  = ml.division_id
LEFT JOIN mst_item                 mi ON mi.id  = ml.item_id
LEFT JOIN mst_material             mm ON mm.id  = ml.material_id
WHERE ml.movement_scope = 'DIVISION'
  AND ml.profile_key IS NOT NULL
  AND ml.profile_key != ''
  AND NOT EXISTS (
    SELECT 1
    FROM inv_division_monthly_stock dms
    WHERE dms.month_key      = @cur_month
      AND dms.division_id    = ml.division_id
      AND dms.destination_type = ml.destination_type
      AND dms.identity_key   = ml.profile_key
  )
GROUP BY
  ml.division_id, ml.destination_type, ml.item_id,
  ml.material_id, ml.buy_uom_id, ml.content_uom_id, ml.profile_key,
  d.name, mi.item_name, mm.material_name
HAVING ABS(ROUND(SUM(ml.qty_content_delta), 4)) > 0.001
ORDER BY ABS(ROUND(SUM(ml.qty_content_delta), 4)) DESC;
