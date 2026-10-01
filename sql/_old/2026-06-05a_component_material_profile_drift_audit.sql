SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05a_component_material_profile_drift_audit.sql
-- Tujuan :
-- 1) Audit profile material exact di stok divisi yang drift
-- 2) Fokus pada lane component batch / material usage
-- 3) Menandai data yang harus direpair, bukan dipaksa lewat alur baru
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_component_fifo_exact;

CREATE TEMPORARY TABLE tmp_component_fifo_exact AS
SELECT
  l.division_id,
  l.destination_type,
  l.item_id,
  l.material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) AS fifo_qty_content,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END), 2) AS fifo_total_value,
  ROUND(
    CASE
      WHEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END) > 0
      THEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END)
           / SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END)
      ELSE 0
    END,
    6
  ) AS fifo_avg_cost,
  COUNT(*) AS lot_count
FROM inv_material_fifo_lot l
GROUP BY
  l.division_id,
  l.destination_type,
  l.item_id,
  l.material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key;

SELECT
  s.id AS monthly_stock_id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.item_id,
  COALESCE(i.item_name, '') AS item_name,
  s.material_id,
  COALESCE(m.material_name, '') AS material_name,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  s.profile_content_per_buy,
  s.closing_qty_buy,
  s.closing_qty_content,
  s.avg_cost_per_content,
  s.total_value,
  COALESCE(f.fifo_qty_content, 0) AS fifo_qty_content,
  COALESCE(f.fifo_avg_cost, 0) AS fifo_avg_cost,
  COALESCE(f.fifo_total_value, 0) AS fifo_total_value,
  COALESCE(f.lot_count, 0) AS fifo_lot_count,
  CASE
    WHEN s.buy_uom_id = s.content_uom_id
         AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
    THEN 'SAME_UOM_WRONG_CONVERSION'
    WHEN s.closing_qty_content < 0 AND COALESCE(f.fifo_qty_content, 0) > 0
    THEN 'NEGATIVE_MONTHLY_WHILE_FIFO_POSITIVE'
    WHEN ABS(COALESCE(s.closing_qty_content, 0) - COALESCE(f.fifo_qty_content, 0)) > 0.0001
    THEN 'MONTHLY_FIFO_QTY_DRIFT'
    WHEN ABS(COALESCE(s.avg_cost_per_content, 0) - COALESCE(f.fifo_avg_cost, 0)) > 0.0001
    THEN 'MONTHLY_FIFO_COST_DRIFT'
    ELSE 'OK'
  END AS drift_type,
  s.notes
FROM inv_division_monthly_stock s
LEFT JOIN tmp_component_fifo_exact f
  ON f.division_id = s.division_id
 AND f.destination_type = s.destination_type
 AND f.item_id <=> s.item_id
 AND f.material_id <=> s.material_id
 AND f.buy_uom_id <=> s.buy_uom_id
 AND f.content_uom_id = s.content_uom_id
 AND f.profile_key <=> s.profile_key
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = s.material_id
WHERE
  (
    (s.buy_uom_id = s.content_uom_id AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001)
    OR (s.closing_qty_content < 0 AND COALESCE(f.fifo_qty_content, 0) > 0)
    OR ABS(COALESCE(s.closing_qty_content, 0) - COALESCE(f.fifo_qty_content, 0)) > 0.0001
    OR ABS(COALESCE(s.avg_cost_per_content, 0) - COALESCE(f.fifo_avg_cost, 0)) > 0.0001
  )
ORDER BY s.division_id, s.destination_type, s.material_id, s.id;

SELECT
  ml.id AS movement_id,
  ml.movement_date,
  ml.movement_type,
  ml.division_id,
  ml.destination_type,
  ml.item_id,
  COALESCE(i.item_name, '') AS item_name,
  ml.material_id,
  COALESCE(m.material_name, '') AS material_name,
  ml.buy_uom_id,
  ml.content_uom_id,
  ml.profile_key,
  ml.profile_content_per_buy,
  ml.qty_buy_delta,
  ml.qty_content_delta,
  ml.unit_cost,
  ml.notes,
  CASE
    WHEN ml.buy_uom_id = ml.content_uom_id
         AND ABS(COALESCE(ml.profile_content_per_buy, 1) - 1) > 0.0001
    THEN 'SAME_UOM_WRONG_CONVERSION'
    WHEN ml.buy_uom_id = ml.content_uom_id
         AND ABS(COALESCE(ml.qty_buy_delta, 0) - COALESCE(ml.qty_content_delta, 0)) > 0.0001
    THEN 'QTY_BUY_DELTA_DRIFT'
    ELSE 'OK'
  END AS drift_type
FROM inv_stock_movement_log ml
LEFT JOIN mst_item i ON i.id = ml.item_id
LEFT JOIN mst_material m ON m.id = ml.material_id
WHERE ml.movement_scope = 'DIVISION'
  AND (
    (ml.buy_uom_id = ml.content_uom_id AND ABS(COALESCE(ml.profile_content_per_buy, 1) - 1) > 0.0001)
    OR (ml.buy_uom_id = ml.content_uom_id AND ABS(COALESCE(ml.qty_buy_delta, 0) - COALESCE(ml.qty_content_delta, 0)) > 0.0001)
  )
ORDER BY ml.division_id, ml.destination_type, ml.material_id, ml.id;

SELECT
  b.batch_no,
  b.batch_date,
  b.division_id,
  b.location_type,
  i.id AS batch_input_id,
  COALESCE(mi.item_name, mm.material_name) AS material_name,
  i.item_id,
  i.material_id,
  i.uom_id,
  i.qty
FROM inv_component_batch b
JOIN inv_component_batch_input i ON i.batch_id = b.id
LEFT JOIN mst_item mi ON mi.id = i.item_id
LEFT JOIN mst_material mm ON mm.id = i.material_id
WHERE b.status = 'DRAFT'
  AND i.source_kind = 'MATERIAL'
  AND EXISTS (
    SELECT 1
    FROM inv_division_monthly_stock s
    LEFT JOIN tmp_component_fifo_exact f
      ON f.division_id = s.division_id
     AND f.destination_type = s.destination_type
     AND f.item_id <=> s.item_id
     AND f.material_id <=> s.material_id
     AND f.buy_uom_id <=> s.buy_uom_id
     AND f.content_uom_id = s.content_uom_id
     AND f.profile_key <=> s.profile_key
    WHERE s.division_id = b.division_id
      AND s.destination_type = b.location_type
      AND s.material_id = i.material_id
      AND (
        (s.buy_uom_id = s.content_uom_id AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001)
        OR (s.closing_qty_content < 0 AND COALESCE(f.fifo_qty_content, 0) > 0)
        OR ABS(COALESCE(s.closing_qty_content, 0) - COALESCE(f.fifo_qty_content, 0)) > 0.0001
      )
  )
ORDER BY b.batch_no, i.id;

SELECT
  'Lanjutkan ke repair SQL setelah review:' AS note,
  'sql/2026-06-05b_component_material_profile_drift_repair.sql' AS repair_sql;
