SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-18b_repair_component_monthly_avg_cost_from_active_lots.sql
-- Tujuan :
-- 1) Menghitung ulang avg_cost monthly component dari weighted cost
--    lot aktif FIFO
-- 2) Menormalkan total_value monthly berdasarkan closing_qty x
--    lot_weighted_cost
-- 3) Menutup kasus monthly avg cost rusak / negatif seperti
--    CHICKEN CUBE 40, tanpa mengubah qty monthly
--
-- Catatan:
-- - Script ini HANYA membetulkan cost/value, bukan qty
-- - Dipakai setelah lot aktif diyakini lebih representatif
--   daripada avg_cost monthly lama
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair component monthly avg cost from active lots 2026-06-18';
SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

DROP TEMPORARY TABLE IF EXISTS tmp_component_active_lot_weighted_cost;
CREATE TEMPORARY TABLE tmp_component_active_lot_weighted_cost AS
SELECT
  l.location_type,
  l.division_id,
  l.component_id,
  l.uom_id,
  ROUND(SUM(COALESCE(l.qty_balance, 0)), 4) AS open_lot_qty,
  ROUND(
    CASE
      WHEN ABS(SUM(COALESCE(l.qty_balance, 0))) > 0.000001
        THEN SUM(COALESCE(l.qty_balance, 0) * COALESCE(l.unit_cost, 0)) / SUM(COALESCE(l.qty_balance, 0))
      ELSE 0
    END,
    6
  ) AS weighted_unit_cost
FROM inv_component_lot l
WHERE COALESCE(l.qty_balance, 0) > 0
GROUP BY l.location_type, l.division_id, l.component_id, l.uom_id;

ALTER TABLE tmp_component_active_lot_weighted_cost
  ADD KEY idx_tmp_component_active_lot_weighted_cost (component_id, division_id, location_type, uom_id);

DROP TEMPORARY TABLE IF EXISTS tmp_component_latest_monthly_cost_targets;
CREATE TEMPORARY TABLE tmp_component_latest_monthly_cost_targets AS
SELECT
  s.id,
  s.location_type,
  s.division_id,
  s.component_id,
  s.uom_id,
  ROUND(COALESCE(s.closing_qty, 0), 4) AS closing_qty,
  ROUND(COALESCE(s.avg_cost, 0), 6) AS before_avg_cost,
  ROUND(COALESCE(s.total_value, 0), 2) AS before_total_value,
  ROUND(COALESCE(l.weighted_unit_cost, 0), 6) AS target_avg_cost,
  ROUND(COALESCE(s.closing_qty, 0) * COALESCE(l.weighted_unit_cost, 0), 2) AS target_total_value
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
) latest
  ON latest.location_type = s.location_type
 AND latest.division_id <=> s.division_id
 AND latest.component_id = s.component_id
 AND latest.uom_id = s.uom_id
 AND latest.keep_month = s.month_key
JOIN tmp_component_active_lot_weighted_cost l
  ON l.location_type = s.location_type
 AND l.division_id <=> s.division_id
 AND l.component_id = s.component_id
 AND l.uom_id = s.uom_id
WHERE ABS(COALESCE(l.weighted_unit_cost, 0)) > 0.000001
  AND (
    ABS(COALESCE(s.avg_cost, 0) - COALESCE(l.weighted_unit_cost, 0)) > 0.000001
    OR ABS(COALESCE(s.total_value, 0) - (COALESCE(s.closing_qty, 0) * COALESCE(l.weighted_unit_cost, 0))) > 0.01
  );

ALTER TABLE tmp_component_latest_monthly_cost_targets
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_component_latest_monthly_cost_backup;
CREATE TEMPORARY TABLE tmp_component_latest_monthly_cost_backup AS
SELECT s.*
FROM inv_component_monthly_stock s
JOIN tmp_component_latest_monthly_cost_targets t ON t.id = s.id;

UPDATE inv_component_monthly_stock s
JOIN tmp_component_latest_monthly_cost_targets t ON t.id = s.id
SET
  s.avg_cost = t.target_avg_cost,
  s.total_value = t.target_total_value,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'monthly_rows_cost_repaired' AS metric, COUNT(*) AS total
FROM tmp_component_latest_monthly_cost_targets

UNION ALL

SELECT 'monthly_rows_cost_backed_up', COUNT(*)
FROM tmp_component_latest_monthly_cost_backup;
