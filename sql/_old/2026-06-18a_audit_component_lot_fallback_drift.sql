SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-18a_audit_component_lot_fallback_drift.sql
-- Tujuan :
-- 1) Mengaudit component yang movement log-nya pernah jatuh ke
--    fallback lot sehingga saldo movement tidak lagi foot ke lot
-- 2) Menunjukkan mana kasus yang:
--    - monthly + movement masih sinkron, tapi lot tertinggal
--    - monthly juga sudah drift
-- 3) Menjadi dasar repair sistematis untuk kasus seperti
--    CHICKEN CUBE 40
-- ============================================================

SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

DROP TEMPORARY TABLE IF EXISTS tmp_component_latest_monthly;
CREATE TEMPORARY TABLE tmp_component_latest_monthly AS
SELECT
  s.location_type,
  s.division_id,
  s.component_id,
  s.uom_id,
  s.month_key,
  ROUND(COALESCE(s.closing_qty, 0), 4) AS monthly_closing_qty,
  ROUND(COALESCE(s.avg_cost, 0), 6) AS monthly_avg_cost,
  ROUND(COALESCE(s.total_value, 0), 2) AS monthly_total_value
FROM inv_component_monthly_stock s
JOIN (
  SELECT
    location_type,
    division_id,
    component_id,
    uom_id,
    MAX(month_key) AS keep_month
  FROM inv_component_monthly_stock
  WHERE month_key <= @target_month
  GROUP BY location_type, division_id, component_id, uom_id
) x
  ON x.location_type = s.location_type
 AND x.division_id <=> s.division_id
 AND x.component_id = s.component_id
 AND x.uom_id = s.uom_id
 AND x.keep_month = s.month_key;

ALTER TABLE tmp_component_latest_monthly
  ADD KEY idx_tmp_component_latest_monthly (component_id, division_id, location_type, uom_id);

DROP TEMPORARY TABLE IF EXISTS tmp_component_lot_balance;
CREATE TEMPORARY TABLE tmp_component_lot_balance AS
SELECT
  l.location_type,
  l.division_id,
  l.component_id,
  l.uom_id,
  ROUND(SUM(COALESCE(l.qty_balance, 0)), 4) AS lot_balance_qty,
  ROUND(SUM(COALESCE(l.qty_balance, 0) * COALESCE(l.unit_cost, 0)), 2) AS lot_open_value,
  ROUND(
    CASE
      WHEN ABS(SUM(COALESCE(l.qty_balance, 0))) > 0.000001
        THEN SUM(COALESCE(l.qty_balance, 0) * COALESCE(l.unit_cost, 0)) / SUM(COALESCE(l.qty_balance, 0))
      ELSE 0
    END,
    6
  ) AS lot_weighted_cost,
  SUM(CASE WHEN UPPER(COALESCE(l.status, 'OPEN')) = 'OPEN' THEN 1 ELSE 0 END) AS open_lot_count
FROM inv_component_lot l
GROUP BY l.location_type, l.division_id, l.component_id, l.uom_id;

ALTER TABLE tmp_component_lot_balance
  ADD KEY idx_tmp_component_lot_balance (component_id, division_id, location_type, uom_id);

DROP TEMPORARY TABLE IF EXISTS tmp_component_latest_movement;
CREATE TEMPORARY TABLE tmp_component_latest_movement AS
SELECT
  m.location_type,
  m.division_id,
  m.component_id,
  m.uom_id,
  ROUND(SUM(COALESCE(m.qty_in, 0)) - SUM(COALESCE(m.qty_out, 0)), 4) AS net_movement_qty,
  MAX(m.id) AS last_movement_id,
  MAX(m.movement_date) AS last_movement_date,
  SUM(CASE WHEN COALESCE(m.notes, '') LIKE '%Lot fallback:%' THEN 1 ELSE 0 END) AS fallback_rows,
  ROUND(SUM(CASE WHEN COALESCE(m.notes, '') LIKE '%Lot fallback:%' THEN COALESCE(m.qty_out, 0) ELSE 0 END), 4) AS fallback_out_qty
FROM inv_component_movement_log m
GROUP BY m.location_type, m.division_id, m.component_id, m.uom_id;

ALTER TABLE tmp_component_latest_movement
  ADD KEY idx_tmp_component_latest_movement (component_id, division_id, location_type, uom_id);

SELECT
  COALESCE(d.code, CAST(mm.division_id AS CHAR)) AS division_code,
  COALESCE(d.name, CAST(mm.division_id AS CHAR)) AS division_name,
  mm.location_type,
  mm.component_id,
  c.component_code,
  c.component_name,
  mm.uom_id,
  u.code AS uom_code,
  ROUND(COALESCE(mm.monthly_closing_qty, 0), 4) AS monthly_qty,
  ROUND(COALESCE(lb.lot_balance_qty, 0), 4) AS lot_qty,
  ROUND(COALESCE(mv.net_movement_qty, 0), 4) AS movement_qty,
  ROUND(COALESCE(lb.lot_weighted_cost, 0), 6) AS lot_weighted_cost,
  ROUND(COALESCE(mm.monthly_avg_cost, 0), 6) AS monthly_avg_cost,
  COALESCE(lb.open_lot_count, 0) AS open_lot_count,
  COALESCE(mv.fallback_rows, 0) AS fallback_rows,
  ROUND(COALESCE(mv.fallback_out_qty, 0), 4) AS fallback_out_qty,
  ROUND(COALESCE(lb.lot_balance_qty, 0) - COALESCE(mv.net_movement_qty, 0), 4) AS lot_minus_movement,
  ROUND(COALESCE(mm.monthly_closing_qty, 0) - COALESCE(mv.net_movement_qty, 0), 4) AS monthly_minus_movement,
  CASE
    WHEN COALESCE(mv.fallback_rows, 0) = 0 THEN 'NO_FALLBACK_TRACE'
    WHEN ABS(COALESCE(mm.monthly_closing_qty, 0) - COALESCE(mv.net_movement_qty, 0)) <= 0.0001
      AND ABS(COALESCE(lb.lot_balance_qty, 0) - COALESCE(mv.net_movement_qty, 0)) > 0.0001
      THEN 'LOT_ONLY_DRIFT_AFTER_FALLBACK'
    WHEN ABS(COALESCE(mm.monthly_closing_qty, 0) - COALESCE(mv.net_movement_qty, 0)) > 0.0001
      THEN 'MONTHLY_AND_LOT_DRIFT'
    ELSE 'FALLBACK_PRESENT_BUT_BALANCE_SYNC'
  END AS drift_bucket
FROM tmp_component_latest_monthly mm
LEFT JOIN tmp_component_lot_balance lb
  ON lb.location_type = mm.location_type
 AND lb.division_id <=> mm.division_id
 AND lb.component_id = mm.component_id
 AND lb.uom_id = mm.uom_id
LEFT JOIN tmp_component_latest_movement mv
  ON mv.location_type = mm.location_type
 AND mv.division_id <=> mm.division_id
 AND mv.component_id = mm.component_id
 AND mv.uom_id = mm.uom_id
LEFT JOIN mst_component c ON c.id = mm.component_id
LEFT JOIN mst_operational_division d ON d.id = mm.division_id
LEFT JOIN mst_uom u ON u.id = mm.uom_id
WHERE COALESCE(mv.fallback_rows, 0) > 0
ORDER BY
  CASE
    WHEN COALESCE(mv.fallback_rows, 0) = 0 THEN 3
    WHEN ABS(COALESCE(mm.monthly_closing_qty, 0) - COALESCE(mv.net_movement_qty, 0)) <= 0.0001
      AND ABS(COALESCE(lb.lot_balance_qty, 0) - COALESCE(mv.net_movement_qty, 0)) > 0.0001 THEN 0
    WHEN ABS(COALESCE(mm.monthly_closing_qty, 0) - COALESCE(mv.net_movement_qty, 0)) > 0.0001 THEN 1
    ELSE 2
  END,
  ABS(COALESCE(lb.lot_balance_qty, 0) - COALESCE(mv.net_movement_qty, 0)) DESC,
  mm.component_id,
  mm.location_type;
