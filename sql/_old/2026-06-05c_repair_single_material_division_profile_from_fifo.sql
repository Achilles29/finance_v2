SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05c_repair_single_material_division_profile_from_fifo.sql
-- Tujuan :
-- 1) Repair exact profile monthly stock untuk 1 bahan pada 1 divisi/lokasi
-- 2) Menyamakan monthly stock dengan FIFO lot truth
-- 3) Cocok dipakai setelah adjustment manual pada bahan legacy
--
-- Cara pakai:
-- - Ubah 3 variabel di bawah:
--   @target_division_id
--   @target_destination_type
--   @target_material_id
-- - Jalankan manual
-- ============================================================

SET @target_division_id := 3;
SET @target_destination_type := 'KITCHEN';
SET @target_material_id := 107;

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_single_material_fifo_exact;

CREATE TEMPORARY TABLE tmp_single_material_fifo_exact AS
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
WHERE l.division_id = @target_division_id
  AND l.destination_type = @target_destination_type
  AND l.material_id = @target_material_id
GROUP BY
  l.division_id,
  l.destination_type,
  l.item_id,
  l.material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key;

-- normalize movement exact profile for same-UOM
UPDATE inv_stock_movement_log ml
SET
  ml.profile_content_per_buy = 1.000000,
  ml.qty_buy_delta = ROUND(COALESCE(ml.qty_content_delta, 0), 4),
  ml.notes = TRIM(CONCAT(
    COALESCE(ml.notes, ''),
    CASE WHEN COALESCE(ml.notes, '') = '' THEN '' ELSE ' | ' END,
    'Repair single material exact profile conversion 2026-06-05'
  ))
WHERE ml.movement_scope = 'DIVISION'
  AND ml.division_id = @target_division_id
  AND ml.destination_type = @target_destination_type
  AND ml.material_id = @target_material_id
  AND ml.buy_uom_id IS NOT NULL
  AND ml.buy_uom_id = ml.content_uom_id
  AND (
    ABS(COALESCE(ml.profile_content_per_buy, 1) - 1) > 0.0001
    OR ABS(COALESCE(ml.qty_buy_delta, 0) - COALESCE(ml.qty_content_delta, 0)) > 0.0001
  );

-- update exact monthly rows from FIFO truth
UPDATE inv_division_monthly_stock s
JOIN tmp_single_material_fifo_exact f
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
    'Repair single material exact monthly from FIFO 2026-06-05'
  ))
WHERE s.division_id = @target_division_id
  AND s.destination_type = @target_destination_type
  AND s.material_id = @target_material_id;

-- insert missing monthly rows for existing FIFO profiles
INSERT INTO inv_division_monthly_stock (
  month_key, identity_key, division_id, destination_type,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_content_per_buy,
  opening_qty_buy, opening_qty_content, opening_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at, source_mode, notes, stock_domain
)
SELECT
  DATE_FORMAT(CURDATE(), '%Y-%m-01') AS month_key,
  COALESCE(f.profile_key, SHA2(CONCAT(IFNULL(f.item_id,0),'|',IFNULL(f.material_id,0),'|',IFNULL(f.buy_uom_id,0),'|',f.content_uom_id), 256)) AS identity_key,
  f.division_id,
  f.destination_type,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  CASE WHEN f.buy_uom_id IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN 1.000000 ELSE 1.000000 END AS profile_content_per_buy,
  CASE WHEN f.buy_uom_id IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN f.fifo_qty_content ELSE f.fifo_qty_content END AS opening_qty_buy,
  f.fifo_qty_content,
  f.fifo_total_value,
  CASE WHEN f.buy_uom_id IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN f.fifo_qty_content ELSE f.fifo_qty_content END AS closing_qty_buy,
  f.fifo_qty_content,
  f.fifo_avg_cost,
  f.fifo_total_value,
  0,
  0,
  CURDATE(),
  NOW(),
  'LIVE',
  'Inserted by single material FIFO repair 2026-06-05',
  NULL
FROM tmp_single_material_fifo_exact f
LEFT JOIN inv_division_monthly_stock s
  ON s.division_id = f.division_id
 AND s.destination_type = f.destination_type
 AND s.item_id <=> f.item_id
 AND s.material_id <=> f.material_id
 AND s.buy_uom_id <=> f.buy_uom_id
 AND s.content_uom_id = f.content_uom_id
 AND s.profile_key <=> f.profile_key
WHERE s.id IS NULL;

SELECT
  s.id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_key,
  s.profile_content_per_buy,
  s.closing_qty_buy,
  s.closing_qty_content,
  s.avg_cost_per_content,
  s.total_value,
  s.notes
FROM inv_division_monthly_stock s
WHERE s.division_id = @target_division_id
  AND s.destination_type = @target_destination_type
  AND s.material_id = @target_material_id
ORDER BY s.id;

COMMIT;
