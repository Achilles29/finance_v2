SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05b_component_material_profile_drift_repair.sql
-- Tujuan :
-- 1) Repair drift profile material exact di stok divisi
-- 2) Fokus pada lane component batch / material usage
-- 3) Menormalkan konversi same-UOM dan menyelaraskan monthly stock
--    dengan FIFO lot exact identity
--
-- Catatan:
-- - Jalankan manual setelah review audit SQL 2026-06-05a
-- - Script ini tidak menghapus kolom apa pun
-- - Script ini tidak mengubah FIFO lot; FIFO lot dianggap sumber truth qty
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_component_fifo_exact_repair;

CREATE TEMPORARY TABLE tmp_component_fifo_exact_repair AS
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
  ) AS fifo_avg_cost
FROM inv_material_fifo_lot l
GROUP BY
  l.division_id,
  l.destination_type,
  l.item_id,
  l.material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key;

-- ============================================================
-- 1) Normalize movement log for same-UOM exact profiles
-- ============================================================
UPDATE inv_stock_movement_log ml
SET
  ml.profile_content_per_buy = 1.000000,
  ml.qty_buy_delta = ROUND(COALESCE(ml.qty_content_delta, 0), 4),
  ml.notes = TRIM(CONCAT(
    COALESCE(ml.notes, ''),
    CASE WHEN COALESCE(ml.notes, '') = '' THEN '' ELSE ' | ' END,
    'Repair same-UOM profile conversion 2026-06-05'
  ))
WHERE ml.movement_scope = 'DIVISION'
  AND ml.buy_uom_id IS NOT NULL
  AND ml.buy_uom_id = ml.content_uom_id
  AND (
    ABS(COALESCE(ml.profile_content_per_buy, 1) - 1) > 0.0001
    OR ABS(COALESCE(ml.qty_buy_delta, 0) - COALESCE(ml.qty_content_delta, 0)) > 0.0001
  );

-- ============================================================
-- 2) Align division monthly stock with exact FIFO lot truth
-- ============================================================
UPDATE inv_division_monthly_stock s
JOIN tmp_component_fifo_exact_repair f
  ON f.division_id = s.division_id
 AND f.destination_type = s.destination_type
 AND f.item_id <=> s.item_id
 AND f.material_id <=> s.material_id
 AND f.buy_uom_id <=> s.buy_uom_id
 AND f.content_uom_id = s.content_uom_id
 AND f.profile_key <=> s.profile_key
SET
  s.profile_content_per_buy = CASE
    WHEN s.buy_uom_id IS NOT NULL AND s.buy_uom_id = s.content_uom_id THEN 1.000000
    ELSE COALESCE(s.profile_content_per_buy, 1.000000)
  END,
  s.closing_qty_content = COALESCE(f.fifo_qty_content, 0),
  s.closing_qty_buy = CASE
    WHEN s.buy_uom_id IS NOT NULL AND s.buy_uom_id = s.content_uom_id
      THEN COALESCE(f.fifo_qty_content, 0)
    WHEN COALESCE(s.profile_content_per_buy, 0) > 0
      THEN ROUND(COALESCE(f.fifo_qty_content, 0) / s.profile_content_per_buy, 4)
    ELSE s.closing_qty_buy
  END,
  s.avg_cost_per_content = COALESCE(f.fifo_avg_cost, 0),
  s.total_value = COALESCE(f.fifo_total_value, 0),
  s.last_movement_at = NOW(),
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    'Repair exact monthly stock from FIFO lots 2026-06-05'
  ))
WHERE
  (s.buy_uom_id IS NOT NULL AND s.buy_uom_id = s.content_uom_id AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001)
  OR s.closing_qty_content < 0
  OR ABS(COALESCE(s.closing_qty_content, 0) - COALESCE(f.fifo_qty_content, 0)) > 0.0001
  OR ABS(COALESCE(s.avg_cost_per_content, 0) - COALESCE(f.fifo_avg_cost, 0)) > 0.0001;

-- ============================================================
-- 3) Summary validation
-- ============================================================
SELECT
  'monthly_same_uom_wrong_conversion_remaining' AS check_key,
  COUNT(*) AS total_rows
FROM inv_division_monthly_stock s
WHERE s.buy_uom_id IS NOT NULL
  AND s.buy_uom_id = s.content_uom_id
  AND ABS(COALESCE(s.profile_content_per_buy, 1) - 1) > 0.0001
UNION ALL
SELECT
  'monthly_negative_with_fifo_remaining',
  COUNT(*)
FROM inv_division_monthly_stock s
JOIN tmp_component_fifo_exact_repair f
  ON f.division_id = s.division_id
 AND f.destination_type = s.destination_type
 AND f.item_id <=> s.item_id
 AND f.material_id <=> s.material_id
 AND f.buy_uom_id <=> s.buy_uom_id
 AND f.content_uom_id = s.content_uom_id
 AND f.profile_key <=> s.profile_key
WHERE s.closing_qty_content < 0
  AND COALESCE(f.fifo_qty_content, 0) > 0
UNION ALL
SELECT
  'movement_same_uom_wrong_conversion_remaining',
  COUNT(*)
FROM inv_stock_movement_log ml
WHERE ml.movement_scope = 'DIVISION'
  AND ml.buy_uom_id IS NOT NULL
  AND ml.buy_uom_id = ml.content_uom_id
  AND (
    ABS(COALESCE(ml.profile_content_per_buy, 1) - 1) > 0.0001
    OR ABS(COALESCE(ml.qty_buy_delta, 0) - COALESCE(ml.qty_content_delta, 0)) > 0.0001
  );

COMMIT;

SELECT
  'Setelah repair, ulangi posting batch yang gagal dan cek pesan error terbaru bila masih ada.' AS next_step;
