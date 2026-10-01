SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08aa_diag_component_batch_posting_fail.sql
-- Tujuan : Diagnosa kenapa posting ICB202606080011 gagal
--          dengan error "Stok komponen tidak cukup untuk movement PRODUCTION_IN"
--
-- PRODUCTION_IN = stok masuk (output produksi).
-- Gagal hanya jika closing_qty di inv_component_monthly_stock sudah negatif
-- dan tidak cukup untuk dikompensasi oleh qty produksi.
-- ============================================================

-- ---- 1. Header batch ----
SELECT
  b.id, b.batch_no, b.batch_date,
  b.location_type, b.division_id,
  d.code AS division_code,
  b.component_id AS output_component_id,
  c.component_name AS output_component_name,
  b.output_qty, u.code AS output_uom,
  b.output_uom_id, b.status
FROM inv_component_batch b
LEFT JOIN mst_component c ON c.id = b.component_id
LEFT JOIN mst_uom u ON u.id = b.output_uom_id
LEFT JOIN mst_operational_division d ON d.id = b.division_id
WHERE b.batch_no = 'ICB202606080011';

-- ---- 2. Semua baris input (component dan material) ----
SELECT
  i.id, i.line_no,
  i.source_kind,
  COALESCE(i.plan_role, 'INPUT') AS plan_role,
  c.component_name,
  m.material_name,
  i.qty,
  u.code AS uom,
  i.component_id,
  i.material_id,
  i.uom_id,
  i.unit_cost
FROM inv_component_batch_input i
JOIN inv_component_batch b ON b.id = i.batch_id
LEFT JOIN mst_component c ON c.id = i.component_id
LEFT JOIN mst_material m ON m.id = i.material_id
LEFT JOIN mst_uom u ON u.id = i.uom_id
WHERE b.batch_no = 'ICB202606080011'
ORDER BY i.line_no;

-- ---- 3. Simulasi cek balance PRODUCTION_IN (yg bisa gagal) ----
-- PRODUCTION_IN dipakai untuk: main output + INLINE_OUTPUT inputs
-- qtyAfter = closing_qty + will_produce_qty
-- Fail jika qtyAfter < -0.0001
SELECT
  src.movement_label,
  src.component_id,
  comp.component_name,
  src.qty AS will_produce_qty,
  u.code AS uom,
  b.location_type,
  b.division_id,
  DATE_FORMAT(b.batch_date, '%Y-%m-01') AS target_month,
  COALESCE(ms.closing_qty, 0)           AS current_monthly_balance,
  ROUND(COALESCE(ms.closing_qty, 0) + src.qty, 4) AS projected_after_production_in,
  CASE
    WHEN ROUND(COALESCE(ms.closing_qty, 0) + src.qty, 4) < -0.0001
    THEN '*** FAIL - TIDAK CUKUP ***'
    ELSE 'OK'
  END AS check_result,
  ms.month_key AS monthly_balance_from_month,
  ms.avg_cost,
  ms.total_value
FROM inv_component_batch b
CROSS JOIN (
  -- Main output
  SELECT 'MAIN_OUTPUT' AS movement_label, b2.component_id, b2.output_qty AS qty, b2.output_uom_id AS uom_id
  FROM inv_component_batch b2
  WHERE b2.batch_no = 'ICB202606080011'

  UNION ALL

  -- INLINE_OUTPUT by-product inputs
  SELECT CONCAT('INLINE_OUTPUT (line ', i.line_no, ')') AS movement_label,
         i.component_id, i.qty, i.uom_id
  FROM inv_component_batch_input i
  JOIN inv_component_batch b2 ON b2.id = i.batch_id
  WHERE b2.batch_no = 'ICB202606080011'
    AND UPPER(TRIM(COALESCE(i.plan_role, ''))) = 'INLINE_OUTPUT'
) src
LEFT JOIN mst_component comp ON comp.id = src.component_id
LEFT JOIN mst_uom u ON u.id = src.uom_id
LEFT JOIN inv_component_monthly_stock ms
  ON ms.location_type = b.location_type
 AND ms.division_id <=> b.division_id
 AND ms.component_id = src.component_id
 AND ms.uom_id = src.uom_id
 AND ms.month_key = (
   SELECT MAX(ms2.month_key)
   FROM inv_component_monthly_stock ms2
   WHERE ms2.location_type = b.location_type
     AND ms2.division_id <=> b.division_id
     AND ms2.component_id = src.component_id
     AND ms2.uom_id = src.uom_id
     AND ms2.month_key <= DATE_FORMAT(b.batch_date, '%Y-%m-01')
 )
WHERE b.batch_no = 'ICB202606080011'
ORDER BY check_result DESC, src.movement_label;

-- ---- 4. Simulasi cek balance PRODUCTION_OUT (component inputs) ----
-- Ini untuk kelengkapan — PRODUCTION_OUT bisa gagal juga tapi error msg beda
SELECT
  CONCAT('COMPONENT_INPUT (line ', i.line_no, ')') AS movement_label,
  i.component_id,
  comp.component_name,
  i.qty AS will_consume_qty,
  u.code AS uom,
  b.location_type,
  b.division_id,
  DATE_FORMAT(b.batch_date, '%Y-%m-01') AS target_month,
  COALESCE(ms.closing_qty, 0)           AS current_monthly_balance,
  ROUND(COALESCE(ms.closing_qty, 0) - i.qty, 4) AS projected_after_production_out,
  CASE
    WHEN ROUND(COALESCE(ms.closing_qty, 0) - i.qty, 4) < -0.0001
    THEN '*** FAIL - TIDAK CUKUP ***'
    ELSE 'OK'
  END AS check_result,
  ms.month_key AS monthly_balance_from_month
FROM inv_component_batch b
JOIN inv_component_batch_input i ON i.batch_id = b.id
LEFT JOIN mst_component comp ON comp.id = i.component_id
LEFT JOIN mst_uom u ON u.id = i.uom_id
LEFT JOIN inv_component_monthly_stock ms
  ON ms.location_type = b.location_type
 AND ms.division_id <=> b.division_id
 AND ms.component_id = i.component_id
 AND ms.uom_id = i.uom_id
 AND ms.month_key = (
   SELECT MAX(ms2.month_key)
   FROM inv_component_monthly_stock ms2
   WHERE ms2.location_type = b.location_type
     AND ms2.division_id <=> b.division_id
     AND ms2.component_id = i.component_id
     AND ms2.uom_id = i.uom_id
     AND ms2.month_key <= DATE_FORMAT(b.batch_date, '%Y-%m-01')
 )
WHERE b.batch_no = 'ICB202606080011'
  AND i.source_kind = 'COMPONENT'
  AND UPPER(TRIM(COALESCE(i.plan_role, ''))) NOT IN ('INLINE_OUTPUT')
ORDER BY check_result DESC, i.line_no;

-- ---- 5. Riwayat movement komponen OUTPUT yang mungkin negatif ----
-- Cek apakah ada movement history yang menyebabkan saldo negatif
SELECT
  ml.movement_no,
  ml.movement_date,
  ml.movement_type,
  comp.component_name,
  u.code AS uom,
  ml.qty_in,
  ml.qty_out,
  ml.unit_cost,
  ml.total_cost,
  ml.source_module,
  ml.source_table,
  ml.source_id,
  ml.notes
FROM inv_component_movement_log ml
JOIN inv_component_batch b ON b.batch_no = 'ICB202606080011'
LEFT JOIN mst_component comp ON comp.id = ml.component_id
LEFT JOIN mst_uom u ON u.id = ml.uom_id
WHERE ml.location_type = b.location_type
  AND ml.division_id <=> b.division_id
  AND ml.component_id = b.component_id   -- output component
ORDER BY ml.movement_date ASC, ml.id ASC;

-- ---- 6. Semua record monthly stock untuk output component ----
SELECT
  ms.month_key, ms.location_type, ms.division_id,
  comp.component_name, u.code AS uom,
  ms.opening_qty, ms.in_qty, ms.out_qty, ms.closing_qty,
  ms.avg_cost, ms.total_value,
  ms.last_movement_at, ms.updated_at
FROM inv_component_monthly_stock ms
JOIN inv_component_batch b ON b.batch_no = 'ICB202606080011'
LEFT JOIN mst_component comp ON comp.id = ms.component_id
LEFT JOIN mst_uom u ON u.id = ms.uom_id
WHERE ms.location_type = b.location_type
  AND ms.division_id <=> b.division_id
  AND ms.component_id = b.component_id
ORDER BY ms.month_key DESC;
