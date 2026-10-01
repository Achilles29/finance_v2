SET NAMES utf8mb4;

SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- ============================================================
-- A. Tampilkan 28 row mismatch yang tersisa: bandingkan
--    closing_qty snapshot vs kumulatif movement log EXACT identity
--    vs kumulatif movement log BROAD (by material_id only)
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.destination_type,
  COALESCE(mi.item_name,    '-')      AS item_name,
  COALESCE(mm.material_name,'-')      AS material_name,
  dms.item_id,
  dms.material_id,
  COALESCE(dms.profile_key, '(null)') AS profile_key_snapshot,
  dms.closing_qty_content             AS snapshot_qty,
  mv_exact.cumulative_content         AS movement_exact,
  mv_broad.cumulative_content_broad   AS movement_by_material,
  ROUND(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0), 4) AS selisih_exact,
  CASE
    WHEN mv_exact.cumulative_content IS NULL THEN 'NO_EXACT_MATCH'
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
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key

-- Kumulatif movement EXACT identity
LEFT JOIN (
  SELECT
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv_exact ON
    mv_exact.division_id     = dms.division_id
AND mv_exact.destination_type = dms.destination_type
AND mv_exact.item_id          = COALESCE(dms.item_id,    0)
AND mv_exact.material_id      = COALESCE(dms.material_id,0)
AND mv_exact.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv_exact.content_uom_id   = dms.content_uom_id
AND mv_exact.profile_key      = COALESCE(dms.profile_key,'')

-- Kumulatif movement BROAD: hanya by material_id (atau item via mst_item)
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
    mv_broad.division_id     = dms.division_id
AND mv_broad.destination_type = dms.destination_type
AND mv_broad.material_id      = dms.material_id

LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item                 mi ON mi.id  = dms.item_id
LEFT JOIN mst_material             mm ON mm.id  = dms.material_id

WHERE dms.month_key = @cur_month
  AND mv_exact.cumulative_content IS NOT NULL
  AND ABS(dms.closing_qty_content - mv_exact.cumulative_content) > 0.001
ORDER BY ABS(dms.closing_qty_content - COALESCE(mv_exact.cumulative_content, 0)) DESC;


-- ============================================================
-- B. Cek: apakah ada movement log entries untuk material yang sama
--    tapi dengan profile_key BERBEDA dari yang ada di monthly stock?
--    Ini root cause identity divergence.
-- ============================================================
SELECT
  d.name                              AS division_name,
  dms.destination_type,
  COALESCE(mm.material_name, '-')     AS material_name,
  dms.item_id                         AS snap_item_id,
  dms.material_id                     AS snap_material_id,
  COALESCE(dms.profile_key,'(null)')  AS snap_profile_key,
  dms.closing_qty_content             AS snap_qty,
  ml.item_id                          AS mv_item_id,
  COALESCE(ml.material_id, mi2.material_id) AS mv_material_id,
  COALESCE(ml.profile_key, '(null)')  AS mv_profile_key,
  ROUND(SUM(ml.qty_content_delta), 4) AS mv_cumulative
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
-- Cari movement berdasarkan material saja (broad match)
JOIN inv_stock_movement_log ml
  ON ml.movement_scope   = 'DIVISION'
 AND ml.division_id      = dms.division_id
 AND ml.destination_type = dms.destination_type
LEFT JOIN mst_item mi2 ON mi2.id = ml.item_id
LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_material mm ON mm.id = dms.material_id
WHERE dms.month_key = @cur_month
  AND dms.material_id IS NOT NULL
  AND COALESCE(ml.material_id, mi2.material_id) = dms.material_id
  -- Hanya yang profile_key-nya berbeda
  AND COALESCE(ml.profile_key, '') != COALESCE(dms.profile_key, '')
GROUP BY
  d.name, dms.destination_type, mm.material_name,
  dms.item_id, dms.material_id, dms.profile_key,
  dms.closing_qty_content,
  ml.item_id, COALESCE(ml.material_id, mi2.material_id), ml.profile_key
HAVING ABS(SUM(ml.qty_content_delta)) > 0.001
ORDER BY d.name, mm.material_name;


-- ============================================================
-- C. Repair target: UPDATE closing_qty untuk row yang punya
--    exact movement match tapi masih mismatch
--    (baris ini harusnya sudah di-handle 07i, tapi jaga-jaga)
-- ============================================================
UPDATE inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
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
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
SET
  dms.closing_qty_content = ROUND(mv.cumulative_content, 4),
  dms.closing_qty_buy     = ROUND(mv.cumulative_buy,     4),
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2)
WHERE dms.month_key = @cur_month
  AND ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- D. DELETE sisa row yang identity diverge (profile_key berbeda,
--    movement ada tapi di identity lain) — hapus agar tidak
--    duplikat dengan snapshot yang akan dibuat ulang oleh sistem
-- ============================================================
DELETE dms FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, identity_key
) latest ON
    latest.division_id     = dms.division_id
AND latest.destination_type = dms.destination_type
AND latest.identity_key    = dms.identity_key
AND latest.max_month       = dms.month_key
-- Pastikan ada movement di material yang sama tapi profile berbeda
WHERE dms.month_key = @cur_month
  AND dms.material_id IS NOT NULL
  AND EXISTS (
    SELECT 1
    FROM inv_stock_movement_log ml
    LEFT JOIN mst_item mi2 ON mi2.id = ml.item_id
    WHERE ml.movement_scope   = 'DIVISION'
      AND ml.division_id      = dms.division_id
      AND ml.destination_type = dms.destination_type
      AND COALESCE(ml.material_id, mi2.material_id) = dms.material_id
      AND COALESCE(ml.profile_key, '') != COALESCE(dms.profile_key, '')
  )
  -- Hanya hapus yang closing_qty = 0 atau negatif (tidak valid)
  AND dms.closing_qty_content <= 0.001;


-- ============================================================
-- E. VERIFIKASI akhir: berapa mismatch tersisa?
-- ============================================================
SELECT
  COUNT(*) AS sisa_mismatch,
  SUM(ABS(ROUND(dms.closing_qty_content - mv.cumulative_content, 4))) AS total_selisih
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
INNER JOIN (
  SELECT
    division_id, destination_type,
    COALESCE(item_id,    0) AS item_id,
    COALESCE(material_id,0) AS material_id,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    content_uom_id,
    COALESCE(profile_key,'') AS profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type,
    COALESCE(item_id, 0), COALESCE(material_id, 0),
    COALESCE(buy_uom_id, 0), content_uom_id, COALESCE(profile_key, '')
) mv ON
    mv.division_id     = dms.division_id
AND mv.destination_type = dms.destination_type
AND mv.item_id          = COALESCE(dms.item_id,    0)
AND mv.material_id      = COALESCE(dms.material_id,0)
AND mv.buy_uom_id       = COALESCE(dms.buy_uom_id, 0)
AND mv.content_uom_id   = dms.content_uom_id
AND mv.profile_key      = COALESCE(dms.profile_key,'')
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;
