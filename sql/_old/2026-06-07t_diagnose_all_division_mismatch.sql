SET NAMES utf8mb4;

-- ============================================================
-- DIAGNOSIS: Semua mismatch di inv_division_monthly_stock
-- vs inv_stock_movement_log (seluruh material, bukan per item)
-- Tanggal: 2026-06-07
-- Jalankan dulu sebelum fix (07u). Semua SELECT, tidak ubah data.
-- ============================================================

SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');


-- ============================================================
-- A. Ringkasan: jumlah mismatch per tipe
-- ============================================================
SELECT
  CASE
    WHEN mv_exact.cumulative_content IS NULL THEN 'NO_EXACT_MATCH'
    ELSE 'MISMATCH'
  END                                AS mismatch_type,
  COUNT(*)                           AS jumlah_row,
  ROUND(SUM(ABS(
    dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)
  )), 4)                             AS total_selisih_content
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
LEFT JOIN (
  SELECT
    ml.division_id,
    ml.destination_type,
    COALESCE(ml.item_id,     0) AS item_id,
    COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id,  0) AS buy_uom_id,
    ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0), COALESCE(ml.material_id, 0),
    COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON
    mv_exact.division_id     = dms.division_id
AND mv_exact.destination_type = dms.destination_type
AND mv_exact.item_id          = COALESCE(dms.item_id,    0)
AND mv_exact.material_id      = COALESCE(dms.material_id,0)
AND mv_exact.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv_exact.content_uom_id   = dms.content_uom_id
AND mv_exact.profile_key      = COALESCE(dms.profile_key,'')
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001
GROUP BY mismatch_type
ORDER BY mismatch_type;


-- ============================================================
-- B. Detail semua mismatch — terurut terbesar selisihnya
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.division_id,
  dms.destination_type,
  COALESCE(mi.item_code,  '-')        AS item_code,
  COALESCE(mi.item_name,  '-')        AS item_name,
  COALESCE(mm.material_code, '-')     AS material_code,
  COALESCE(mm.material_name, '-')     AS material_name,
  dms.item_id,
  dms.material_id,
  dms.buy_uom_id,
  dms.content_uom_id,
  COALESCE(dms.profile_key, '(null)') AS profile_key,
  dms.identity_key,
  dms.closing_qty_content             AS snap_qty,
  COALESCE(mv_exact.cumulative_content, 0) AS movement_qty,
  ROUND(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0), 4) AS selisih,
  CASE
    WHEN mv_exact.cumulative_content IS NULL THEN 'NO_EXACT_MATCH'
    ELSE 'MISMATCH'
  END                                 AS mismatch_type,
  -- Cek apakah ada movement di identity lain untuk material yang sama
  CASE
    WHEN mv_broad.material_id IS NOT NULL THEN CONCAT('ADA_DI_IDENTITY_LAIN (mv_cumul=',
      ROUND(mv_broad.cumulative_broad, 4), ')')
    ELSE 'TIDAK_ADA_MOVEMENT_BROAD'
  END                                 AS movement_broad_status,
  -- Cek FIFO lot balance
  COALESCE(fifo.fifo_balance, 0)      AS fifo_balance_division,
  dms.last_movement_date,
  dms.source_mode,
  dms.month_key
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key

-- exact movement match
LEFT JOIN (
  SELECT
    ml.division_id,
    ml.destination_type,
    COALESCE(ml.item_id,     0) AS item_id,
    COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id,  0) AS buy_uom_id,
    ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0), COALESCE(ml.material_id, 0),
    COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON
    mv_exact.division_id     = dms.division_id
AND mv_exact.destination_type = dms.destination_type
AND mv_exact.item_id          = COALESCE(dms.item_id,    0)
AND mv_exact.material_id      = COALESCE(dms.material_id,0)
AND mv_exact.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv_exact.content_uom_id   = dms.content_uom_id
AND mv_exact.profile_key      = COALESCE(dms.profile_key,'')

-- broad movement match (by material_id saja, lintas identity)
LEFT JOIN (
  SELECT
    ml.division_id,
    ml.destination_type,
    COALESCE(ml.material_id, xi.material_id) AS material_id,
    ROUND(SUM(ml.qty_content_delta), 4)       AS cumulative_broad
  FROM inv_stock_movement_log ml
  LEFT JOIN mst_item xi ON xi.id = ml.item_id
  WHERE ml.movement_scope = 'DIVISION'
    AND COALESCE(ml.material_id, xi.material_id) IS NOT NULL
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.material_id, xi.material_id)
) mv_broad ON
    mv_broad.division_id     = dms.division_id
AND mv_broad.destination_type = dms.destination_type
AND mv_broad.material_id      = dms.material_id

-- FIFO lot balance
LEFT JOIN (
  SELECT
    fl.division_id,
    fl.destination_type,
    COALESCE(fl.item_id, 0)     AS item_id,
    COALESCE(fl.material_id, 0) AS material_id,
    COALESCE(fl.profile_key, '') AS profile_key,
    ROUND(SUM(fl.qty_balance), 4) AS fifo_balance
  FROM inv_material_fifo_lot fl
  WHERE fl.location_scope = 'DIVISION'
    AND fl.status = 'OPEN'
  GROUP BY fl.division_id, fl.destination_type,
    COALESCE(fl.item_id, 0), COALESCE(fl.material_id, 0), COALESCE(fl.profile_key, '')
) fifo ON
    fifo.division_id     = dms.division_id
AND fifo.destination_type = dms.destination_type
AND fifo.item_id          = COALESCE(dms.item_id,    0)
AND fifo.material_id      = COALESCE(dms.material_id,0)
AND fifo.profile_key      = COALESCE(dms.profile_key,'')

LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id  = dms.item_id
LEFT JOIN mst_material             mm ON mm.id  = dms.material_id

WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001
ORDER BY ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) DESC;


-- ============================================================
-- C. Ringkasan per tipe untuk menentukan prioritas fix
--    (berapa yang MISMATCH vs NO_EXACT_MATCH, dan total selisih)
-- ============================================================
SELECT
  CASE
    WHEN mv_exact.cumulative_content IS NULL THEN 'NO_EXACT_MATCH'
    ELSE 'MISMATCH'
  END                                AS mismatch_type,
  CASE
    WHEN mv_broad.material_id IS NOT NULL THEN 'ADA_MOVEMENT_IDENTITY_LAIN'
    ELSE 'TIDAK_ADA_MOVEMENT'
  END                                AS broad_movement_status,
  COUNT(*)                           AS jumlah_row
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
LEFT JOIN (
  SELECT
    ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0) AS item_id, COALESCE(ml.material_id, 0) AS material_id,
    COALESCE(ml.buy_uom_id, 0) AS buy_uom_id, ml.content_uom_id,
    COALESCE(ml.profile_key, '') AS profile_key,
    ROUND(SUM(ml.qty_content_delta), 4) AS cumulative_content
  FROM inv_stock_movement_log ml
  WHERE ml.movement_scope = 'DIVISION'
  GROUP BY ml.division_id, ml.destination_type,
    COALESCE(ml.item_id, 0), COALESCE(ml.material_id, 0),
    COALESCE(ml.buy_uom_id, 0), ml.content_uom_id, COALESCE(ml.profile_key, '')
) mv_exact ON
    mv_exact.division_id = dms.division_id AND mv_exact.destination_type = dms.destination_type
AND mv_exact.item_id = COALESCE(dms.item_id, 0) AND mv_exact.material_id = COALESCE(dms.material_id, 0)
AND mv_exact.buy_uom_id = COALESCE(dms.buy_uom_id, 0) AND mv_exact.content_uom_id = dms.content_uom_id
AND mv_exact.profile_key = COALESCE(dms.profile_key, '')
LEFT JOIN (
  SELECT
    ml.division_id, ml.destination_type,
    COALESCE(ml.material_id, xi.material_id) AS material_id
  FROM inv_stock_movement_log ml
  LEFT JOIN mst_item xi ON xi.id = ml.item_id
  WHERE ml.movement_scope = 'DIVISION'
    AND COALESCE(ml.material_id, xi.material_id) IS NOT NULL
  GROUP BY ml.division_id, ml.destination_type, COALESCE(ml.material_id, xi.material_id)
) mv_broad ON
    mv_broad.division_id = dms.division_id
AND mv_broad.destination_type = dms.destination_type
AND mv_broad.material_id = dms.material_id
WHERE dms.material_id IS NOT NULL
  AND ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) > 0.001
GROUP BY mismatch_type, broad_movement_status
ORDER BY mismatch_type, broad_movement_status;
