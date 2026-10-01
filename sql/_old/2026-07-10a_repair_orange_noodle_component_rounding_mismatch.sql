SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-10a_repair_orange_noodle_component_rounding_mismatch.sql
-- Tujuan :
-- 1) Audit ORANGE NOODLE yang tampil mismatch di
--    /production/component-reconcile walau UI membulatkan 13,00
-- 2) Menutup gap mikro movement log 0,0002 agar monthly stock,
--    lot FIFO, dan movement log sama-sama terbaca 13,0000
-- 3) Repair dibuat sempit dan idempotent hanya untuk:
--    PREP-DASH-00095 / ORANGE NOODLE, KITCHEN, PRS, sampai 2026-07-10
--
-- Penyebab:
-- - monthly_stock = 13,0000
-- - lot FIFO      = 13,0000
-- - movement log  = 12,9998
-- UI menampilkan 2 desimal sehingga terlihat sama, tetapi reconcile
-- memakai toleransi 0,0001 sehingga gap 0,0002 tetap dianggap mismatch.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair ORANGE NOODLE component rounding mismatch 2026-07-10';
SET @component_code := 'PREP-DASH-00095';
SET @component_name := 'ORANGE NOODLE';
SET @location_type := 'KITCHEN';
SET @division_code := 'KITCHEN';
SET @month_key := '2026-07-01';
SET @as_of_date := '2026-07-10';
SET @movement_dt := CONCAT(@as_of_date, ' 23:59:59');

SET @component_id := (
  SELECT c.id
  FROM mst_component c
  WHERE c.component_code = @component_code
     OR c.component_name = @component_name
  ORDER BY c.id
  LIMIT 1
);

SET @uom_id := (
  SELECT c.uom_id
  FROM mst_component c
  WHERE c.id = @component_id
  LIMIT 1
);

SET @division_id := (
  SELECT d.id
  FROM mst_operational_division d
  WHERE UPPER(TRIM(d.code)) = @division_code
     OR UPPER(TRIM(d.name)) = @division_code
  ORDER BY d.id
  LIMIT 1
);

DROP TEMPORARY TABLE IF EXISTS tmp_orange_noodle_component_rounding_audit;
CREATE TEMPORARY TABLE tmp_orange_noodle_component_rounding_audit AS
SELECT
  @component_id AS component_id,
  @uom_id AS uom_id,
  @division_id AS division_id,
  @location_type AS location_type,
  @month_key AS month_key,
  @as_of_date AS as_of_date,
  ROUND(COALESCE(ms.closing_qty, 0), 4) AS monthly_closing_qty,
  ROUND(COALESCE(lot.lot_qty, 0), 4) AS lot_qty,
  ROUND(COALESCE(mv.movement_qty, 0), 4) AS movement_qty,
  ROUND(COALESCE(ms.closing_qty, 0) - COALESCE(lot.lot_qty, 0), 4) AS gap_monthly_vs_lot,
  ROUND(COALESCE(ms.closing_qty, 0) - COALESCE(mv.movement_qty, 0), 4) AS gap_monthly_vs_movement,
  ROUND(COALESCE(lot.lot_qty, 0) - COALESCE(mv.movement_qty, 0), 4) AS gap_lot_vs_movement,
  ROUND(COALESCE(ms.avg_cost, lot.latest_unit_cost, 0), 6) AS repair_unit_cost
FROM (
  SELECT 1 AS k
) seed
LEFT JOIN inv_component_monthly_stock ms
  ON ms.month_key = @month_key
 AND ms.location_type = @location_type
 AND ms.division_id = @division_id
 AND ms.component_id = @component_id
 AND ms.uom_id = @uom_id
LEFT JOIN (
  SELECT
    component_id,
    uom_id,
    division_id,
    location_type,
    ROUND(SUM(CASE WHEN status = 'OPEN' AND qty_balance > 0.0001 THEN qty_balance ELSE 0 END), 4) AS lot_qty,
    (
      SELECT l2.unit_cost
      FROM inv_component_lot l2
      WHERE l2.component_id = @component_id
        AND l2.uom_id = @uom_id
        AND l2.division_id = @division_id
        AND l2.location_type = @location_type
        AND l2.status = 'OPEN'
        AND l2.qty_balance > 0.0001
      ORDER BY l2.receipt_date ASC, l2.id ASC
      LIMIT 1
    ) AS latest_unit_cost
  FROM inv_component_lot
  WHERE component_id = @component_id
    AND uom_id = @uom_id
    AND division_id = @division_id
    AND location_type = @location_type
  GROUP BY component_id, uom_id, division_id, location_type
) lot
  ON lot.component_id = @component_id
 AND lot.uom_id = @uom_id
 AND lot.division_id = @division_id
 AND lot.location_type = @location_type
LEFT JOIN (
  SELECT
    component_id,
    uom_id,
    division_id,
    location_type,
    ROUND(SUM(qty_in - qty_out), 4) AS movement_qty
  FROM inv_component_movement_log
  WHERE component_id = @component_id
    AND uom_id = @uom_id
    AND division_id = @division_id
    AND location_type = @location_type
    AND movement_date <= @as_of_date
  GROUP BY component_id, uom_id, division_id, location_type
) mv
  ON mv.component_id = @component_id
 AND mv.uom_id = @uom_id
 AND mv.division_id = @division_id
 AND mv.location_type = @location_type;

DROP TEMPORARY TABLE IF EXISTS tmp_orange_noodle_monthly_backup;
CREATE TEMPORARY TABLE tmp_orange_noodle_monthly_backup AS
SELECT *
FROM inv_component_monthly_stock
WHERE month_key = @month_key
  AND location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id;

DROP TEMPORARY TABLE IF EXISTS tmp_orange_noodle_lot_backup;
CREATE TEMPORARY TABLE tmp_orange_noodle_lot_backup AS
SELECT *
FROM inv_component_lot
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id;

DROP TEMPORARY TABLE IF EXISTS tmp_orange_noodle_movement_backup;
CREATE TEMPORARY TABLE tmp_orange_noodle_movement_backup AS
SELECT *
FROM inv_component_movement_log
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id
  AND movement_date <= @as_of_date;

-- Insert hanya jika gap mikro aman. Monthly stock dan lot FIFO tidak diubah
-- karena keduanya sudah sama-sama 13,0000.
INSERT INTO inv_component_movement_log (
  movement_no,
  movement_date,
  movement_datetime,
  location_type,
  division_id,
  component_id,
  uom_id,
  movement_type,
  qty_in,
  qty_out,
  unit_cost,
  total_cost,
  source_module,
  source_table,
  source_id,
  source_line_id,
  lot_no_snapshot,
  received_date_snapshot,
  notes,
  created_by,
  created_at
)
SELECT
  CONCAT('CMPRND', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), LPAD(FLOOR(RAND() * 10000), 4, '0')) AS movement_no,
  @as_of_date AS movement_date,
  @movement_dt AS movement_datetime,
  a.location_type,
  a.division_id,
  a.component_id,
  a.uom_id,
  CASE WHEN a.gap_monthly_vs_movement >= 0 THEN 'ADJUSTMENT_PLUS' ELSE 'ADJUSTMENT_MINUS' END AS movement_type,
  CASE WHEN a.gap_monthly_vs_movement >= 0 THEN ABS(a.gap_monthly_vs_movement) ELSE 0 END AS qty_in,
  CASE WHEN a.gap_monthly_vs_movement < 0 THEN ABS(a.gap_monthly_vs_movement) ELSE 0 END AS qty_out,
  a.repair_unit_cost AS unit_cost,
  ROUND(ABS(a.gap_monthly_vs_movement) * a.repair_unit_cost, 2) AS total_cost,
  'REPAIR' AS source_module,
  'component_rounding_repair' AS source_table,
  a.component_id AS source_id,
  NULL AS source_line_id,
  NULL AS lot_no_snapshot,
  NULL AS received_date_snapshot,
  @repair_tag AS notes,
  NULL AS created_by,
  CURRENT_TIMESTAMP AS created_at
FROM tmp_orange_noodle_component_rounding_audit a
WHERE ABS(a.gap_monthly_vs_movement) > 0.0001
  AND ABS(a.gap_monthly_vs_movement) <= 0.01
  AND ABS(a.gap_monthly_vs_lot) <= 0.0001
  AND NOT EXISTS (
    SELECT 1
    FROM inv_component_movement_log x
    WHERE x.location_type = a.location_type
      AND x.division_id = a.division_id
      AND x.component_id = a.component_id
      AND x.uom_id = a.uom_id
      AND x.source_module = 'REPAIR'
      AND x.source_table = 'component_rounding_repair'
      AND x.source_id = a.component_id
      AND x.notes = @repair_tag
  );

COMMIT;

-- ------------------------------------------------------------
-- Audit ringkas before/after
-- ------------------------------------------------------------
SELECT 'monthly_rows_backed_up' AS metric, COUNT(*) AS total
FROM tmp_orange_noodle_monthly_backup
UNION ALL
SELECT 'lot_rows_backed_up', COUNT(*)
FROM tmp_orange_noodle_lot_backup
UNION ALL
SELECT 'movement_rows_backed_up', COUNT(*)
FROM tmp_orange_noodle_movement_backup
UNION ALL
SELECT 'rounding_repair_rows_inserted', COUNT(*)
FROM inv_component_movement_log
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id
  AND source_module = 'REPAIR'
  AND source_table = 'component_rounding_repair'
  AND source_id = @component_id
  AND notes = @repair_tag;

SELECT
  c.component_code,
  c.component_name,
  d.code AS division_code,
  a.location_type,
  a.month_key,
  a.as_of_date,
  a.monthly_closing_qty AS before_monthly_closing_qty,
  a.lot_qty AS before_lot_qty,
  a.movement_qty AS before_movement_qty,
  a.gap_monthly_vs_movement AS before_gap_monthly_vs_movement,
  ROUND(COALESCE(ms.closing_qty, 0), 4) AS after_monthly_closing_qty,
  ROUND(COALESCE(lot.lot_qty, 0), 4) AS after_lot_qty,
  ROUND(COALESCE(mv.movement_qty, 0), 4) AS after_movement_qty,
  ROUND(COALESCE(ms.closing_qty, 0) - COALESCE(mv.movement_qty, 0), 4) AS after_gap_monthly_vs_movement
FROM tmp_orange_noodle_component_rounding_audit a
LEFT JOIN mst_component c ON c.id = a.component_id
LEFT JOIN mst_operational_division d ON d.id = a.division_id
LEFT JOIN inv_component_monthly_stock ms
  ON ms.month_key = a.month_key
 AND ms.location_type = a.location_type
 AND ms.division_id = a.division_id
 AND ms.component_id = a.component_id
 AND ms.uom_id = a.uom_id
LEFT JOIN (
  SELECT
    component_id,
    uom_id,
    division_id,
    location_type,
    ROUND(SUM(CASE WHEN status = 'OPEN' AND qty_balance > 0.0001 THEN qty_balance ELSE 0 END), 4) AS lot_qty
  FROM inv_component_lot
  WHERE component_id = @component_id
    AND uom_id = @uom_id
    AND division_id = @division_id
    AND location_type = @location_type
  GROUP BY component_id, uom_id, division_id, location_type
) lot
  ON lot.component_id = a.component_id
 AND lot.uom_id = a.uom_id
 AND lot.division_id = a.division_id
 AND lot.location_type = a.location_type
LEFT JOIN (
  SELECT
    component_id,
    uom_id,
    division_id,
    location_type,
    ROUND(SUM(qty_in - qty_out), 4) AS movement_qty
  FROM inv_component_movement_log
  WHERE component_id = @component_id
    AND uom_id = @uom_id
    AND division_id = @division_id
    AND location_type = @location_type
    AND movement_date <= @as_of_date
  GROUP BY component_id, uom_id, division_id, location_type
) mv
  ON mv.component_id = a.component_id
 AND mv.uom_id = a.uom_id
 AND mv.division_id = a.division_id
 AND mv.location_type = a.location_type;

-- Jejak detail angka mentah yang menjadi penyebab mismatch.
SELECT
  id,
  movement_no,
  movement_date,
  movement_datetime,
  movement_type,
  qty_in,
  qty_out,
  unit_cost,
  total_cost,
  source_module,
  source_table,
  source_id,
  source_line_id,
  notes
FROM inv_component_movement_log
WHERE location_type = @location_type
  AND division_id = @division_id
  AND component_id = @component_id
  AND uom_id = @uom_id
  AND (
    ABS(qty_in - ROUND(qty_in, 0)) > 0.0001
    OR ABS(qty_out - ROUND(qty_out, 0)) > 0.0001
    OR notes = @repair_tag
  )
ORDER BY movement_date, movement_datetime, id;
