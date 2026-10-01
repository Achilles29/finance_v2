SET NAMES utf8mb4;

-- ============================================================
-- AUDIT: division monthly stock vs FIFO lot vs profile gap
-- Tanggal: 2026-06-08
-- Tujuan:
-- 1. memetakan row monthly material yang profile_key-nya kosong
-- 2. memetakan lot FIFO yang profile_key-nya kosong
-- 3. memetakan saldo monthly positif tanpa open lot pasangan
-- 4. memetakan open lot tanpa monthly positif pasangan
-- 5. memetakan mismatch latest monthly vs cumulative movement
-- ============================================================


-- ============================================================
-- A. Ringkasan cepat
-- ============================================================
SELECT
  COUNT(*) AS total_monthly_rows,
  SUM(CASE WHEN material_id IS NOT NULL THEN 1 ELSE 0 END) AS material_rows,
  SUM(CASE WHEN COALESCE(profile_key, '') = '' THEN 1 ELSE 0 END) AS monthly_missing_profile_all,
  SUM(CASE WHEN material_id IS NOT NULL AND COALESCE(profile_key, '') = '' THEN 1 ELSE 0 END) AS monthly_missing_profile_material
FROM inv_division_monthly_stock;

SELECT
  COUNT(*) AS total_fifo_lots,
  SUM(CASE WHEN COALESCE(lot_no, '') = '' THEN 1 ELSE 0 END) AS fifo_missing_lot_no,
  SUM(CASE WHEN COALESCE(profile_key, '') = '' THEN 1 ELSE 0 END) AS fifo_missing_profile_key,
  SUM(CASE WHEN qty_balance > 0.001 AND COALESCE(lot_no, '') = '' THEN 1 ELSE 0 END) AS open_fifo_missing_lot_no,
  SUM(CASE WHEN qty_balance > 0.001 AND COALESCE(profile_key, '') = '' THEN 1 ELSE 0 END) AS open_fifo_missing_profile_key
FROM inv_material_fifo_lot;

SELECT
  COUNT(*) AS total_movements,
  SUM(CASE WHEN movement_scope = 'DIVISION' AND material_id IS NOT NULL AND COALESCE(profile_key, '') = '' THEN 1 ELSE 0 END) AS division_material_missing_profile,
  SUM(CASE WHEN movement_scope = 'DIVISION' AND material_id IS NOT NULL AND item_id IS NULL THEN 1 ELSE 0 END) AS division_material_missing_item
FROM inv_stock_movement_log;


-- ============================================================
-- B. Detail monthly material yang profile_key kosong
-- ============================================================
SELECT
  dms.id,
  dms.month_key,
  dms.division_id,
  od.name AS division_name,
  dms.destination_type,
  dms.item_id,
  mi.item_code,
  mi.item_name,
  dms.material_id,
  mm.material_code,
  mm.material_name,
  dms.buy_uom_id,
  dms.content_uom_id,
  dms.closing_qty_content,
  dms.avg_cost_per_content,
  dms.identity_key,
  dms.source_mode,
  dms.notes
FROM inv_division_monthly_stock dms
LEFT JOIN mst_operational_division od ON od.id = dms.division_id
LEFT JOIN mst_item mi ON mi.id = dms.item_id
LEFT JOIN mst_material mm ON mm.id = dms.material_id
WHERE dms.material_id IS NOT NULL
  AND COALESCE(dms.profile_key, '') = ''
ORDER BY dms.closing_qty_content DESC, dms.id DESC;


-- ============================================================
-- C. Detail FIFO lot yang profile_key kosong
-- ============================================================
SELECT
  fl.id,
  fl.location_scope,
  fl.division_id,
  od.name AS division_name,
  fl.destination_type,
  fl.item_id,
  mi.item_code,
  mi.item_name,
  fl.material_id,
  mm.material_code,
  mm.material_name,
  fl.buy_uom_id,
  fl.content_uom_id,
  fl.qty_balance,
  fl.unit_cost,
  fl.status,
  fl.lot_no,
  fl.receipt_date,
  fl.source_table,
  fl.source_id
FROM inv_material_fifo_lot fl
LEFT JOIN mst_operational_division od ON od.id = fl.division_id
LEFT JOIN mst_item mi ON mi.id = fl.item_id
LEFT JOIN mst_material mm ON mm.id = fl.material_id
WHERE COALESCE(fl.profile_key, '') = ''
ORDER BY fl.qty_balance DESC, fl.id DESC;


-- ============================================================
-- D. Material movement divisi yang profile_key kosong
--    Berguna untuk melihat apakah gap profile memang source-level
-- ============================================================
SELECT
  od.name AS division_name,
  ml.division_id,
  ml.destination_type,
  ml.item_id,
  mi.item_code,
  mi.item_name,
  ml.material_id,
  mm.material_code,
  mm.material_name,
  COALESCE(ml.profile_key, '(null)') AS profile_key,
  ml.buy_uom_id,
  ml.content_uom_id,
  COUNT(*) AS movement_count,
  ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_qty,
  MIN(ml.movement_date) AS first_date,
  MAX(ml.movement_date) AS last_date
FROM inv_stock_movement_log ml
LEFT JOIN mst_operational_division od ON od.id = ml.division_id
LEFT JOIN mst_item mi ON mi.id = ml.item_id
LEFT JOIN mst_material mm ON mm.id = ml.material_id
WHERE ml.movement_scope = 'DIVISION'
  AND ml.material_id IS NOT NULL
  AND COALESCE(ml.profile_key, '') = ''
GROUP BY
  ml.division_id,
  ml.destination_type,
  ml.item_id,
  ml.material_id,
  ml.buy_uom_id,
  ml.content_uom_id,
  COALESCE(ml.profile_key, '')
ORDER BY ABS(SUM(ml.qty_content_delta)) DESC, COUNT(*) DESC;


-- ============================================================
-- E. Latest monthly positif yang tidak punya open lot pasangan
--    Kandidat:
--    - bootstrap lot dari movement/source
--    - atau zero/sync kalau monthly memang phantom
-- ============================================================
SELECT
  d.name AS division_name,
  x.division_id,
  x.destination_type,
  x.item_id,
  mi.item_code,
  mi.item_name,
  x.material_id,
  mm.material_code,
  mm.material_name,
  COALESCE(x.profile_key, '(null)') AS profile_key,
  x.buy_uom_id,
  x.content_uom_id,
  x.monthly_qty
FROM (
  SELECT
    dms.division_id,
    dms.destination_type,
    dms.item_id,
    dms.material_id,
    dms.buy_uom_id,
    dms.content_uom_id,
    dms.profile_key,
    dms.closing_qty_content AS monthly_qty
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
  WHERE dms.material_id IS NOT NULL
    AND dms.closing_qty_content > 0.001
) x
LEFT JOIN mst_operational_division d ON d.id = x.division_id
LEFT JOIN mst_item mi ON mi.id = x.item_id
LEFT JOIN mst_material mm ON mm.id = x.material_id
WHERE NOT EXISTS (
  SELECT 1
  FROM inv_material_fifo_lot fl
  WHERE fl.location_scope = 'DIVISION'
    AND fl.status = 'OPEN'
    AND fl.division_id = x.division_id
    AND fl.destination_type = x.destination_type
    AND COALESCE(fl.item_id, 0) = COALESCE(x.item_id, 0)
    AND COALESCE(fl.material_id, 0) = COALESCE(x.material_id, 0)
    AND COALESCE(fl.buy_uom_id, 0) = COALESCE(x.buy_uom_id, 0)
    AND fl.content_uom_id = x.content_uom_id
    AND COALESCE(fl.profile_key, '') = COALESCE(x.profile_key, '')
    AND fl.qty_balance > 0.001
)
ORDER BY x.monthly_qty DESC;


-- ============================================================
-- F. Open lot tanpa monthly positif pasangan
--    Kandidat:
--    - close stale lot
--    - atau sync monthly exact profile dari FIFO truth
-- ============================================================
SELECT
  fl.id,
  od.name AS division_name,
  fl.division_id,
  fl.destination_type,
  fl.item_id,
  mi.item_code,
  mi.item_name,
  fl.material_id,
  mm.material_code,
  mm.material_name,
  COALESCE(fl.profile_key, '(null)') AS profile_key,
  fl.buy_uom_id,
  fl.content_uom_id,
  fl.qty_balance,
  fl.unit_cost,
  fl.lot_no,
  fl.receipt_date,
  fl.source_table,
  fl.source_id
FROM inv_material_fifo_lot fl
LEFT JOIN mst_operational_division od ON od.id = fl.division_id
LEFT JOIN mst_item mi ON mi.id = fl.item_id
LEFT JOIN mst_material mm ON mm.id = fl.material_id
WHERE fl.location_scope = 'DIVISION'
  AND fl.status = 'OPEN'
  AND fl.qty_balance > 0.001
  AND NOT EXISTS (
    SELECT 1
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
    WHERE dms.division_id = fl.division_id
      AND dms.destination_type = fl.destination_type
      AND COALESCE(dms.item_id, 0) = COALESCE(fl.item_id, 0)
      AND COALESCE(dms.material_id, 0) = COALESCE(fl.material_id, 0)
      AND COALESCE(dms.buy_uom_id, 0) = COALESCE(fl.buy_uom_id, 0)
      AND dms.content_uom_id = fl.content_uom_id
      AND COALESCE(dms.profile_key, '') = COALESCE(fl.profile_key, '')
      AND dms.closing_qty_content > 0.001
  )
ORDER BY fl.qty_balance DESC, fl.id DESC;


-- ============================================================
-- G. Mismatch latest monthly vs cumulative movement
--    Ini fokus saldo bulanan, bukan hanya pasangan lot.
-- ============================================================
SELECT
  d.name AS division_name,
  dms.division_id,
  dms.destination_type,
  dms.item_id,
  mi.item_code,
  mi.item_name,
  dms.material_id,
  mm.material_code,
  mm.material_name,
  COALESCE(dms.profile_key, '(null)') AS profile_key,
  dms.buy_uom_id,
  dms.content_uom_id,
  dms.closing_qty_content AS monthly_qty,
  COALESCE(mv.cumulative_content, 0) AS movement_qty,
  ROUND(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0), 4) AS diff_qty,
  COALESCE(fifo.fifo_qty, 0) AS fifo_qty,
  dms.source_mode,
  dms.notes
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
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    COALESCE(item_id, 0) AS item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY
    division_id,
    destination_type,
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0),
    content_uom_id,
    COALESCE(profile_key, '')
) mv ON
    mv.division_id = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id = COALESCE(dms.item_id, 0)
AND mv.material_id = COALESCE(dms.material_id, 0)
AND mv.buy_uom_id = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id = dms.content_uom_id
AND mv.profile_key = COALESCE(dms.profile_key, '')
LEFT JOIN (
  SELECT
    division_id,
    destination_type,
    COALESCE(item_id, 0) AS item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(qty_balance), 4) AS fifo_qty
  FROM inv_material_fifo_lot
  WHERE location_scope = 'DIVISION'
    AND status = 'OPEN'
  GROUP BY
    division_id,
    destination_type,
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0),
    content_uom_id,
    COALESCE(profile_key, '')
) fifo ON
    fifo.division_id = dms.division_id
AND fifo.destination_type = dms.destination_type
AND fifo.item_id = COALESCE(dms.item_id, 0)
AND fifo.material_id = COALESCE(dms.material_id, 0)
AND fifo.buy_uom_id = COALESCE(dms.buy_uom_id, 0)
AND fifo.content_uom_id = dms.content_uom_id
AND fifo.profile_key = COALESCE(dms.profile_key, '')
LEFT JOIN mst_operational_division d ON d.id = dms.division_id
LEFT JOIN mst_item mi ON mi.id = dms.item_id
LEFT JOIN mst_material mm ON mm.id = dms.material_id
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0)) > 0.001
ORDER BY ABS(dms.closing_qty_content - COALESCE(mv.cumulative_content, 0)) DESC;
