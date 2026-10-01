START TRANSACTION;

-- Repair value-only mismatch pada /inventory/stock/division/reconcile.
-- Qty monthly stock dan LOT FIFO sudah sama, tetapi total_value/avg_cost_per_content
-- di inv_division_monthly_stock belum mengikuti nilai LOT FIFO bulan berjalan.
--
-- Catatan:
-- - KOL PUTIH hanya selisih rounding Rp 0,13 dan sudah ditangani lewat toleransi kode.
-- - Script ini tetap aman untuk target terdaftar karena hanya mengubah nilai, bukan qty.

SET @month_key := '2026-07-01';
SET @date_from := '2026-07-01';
SET @date_to := '2026-07-31';

CREATE TABLE IF NOT EXISTS zz_bak_div_monthly_20260712_value_mismatch AS
SELECT *
FROM inv_division_monthly_stock
WHERE id IN (4083, 5447, 4681, 4699, 5511);

DROP TEMPORARY TABLE IF EXISTS tmp_division_value_mismatch_target;
CREATE TEMPORARY TABLE tmp_division_value_mismatch_target (
  monthly_stock_id BIGINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=Memory;

INSERT INTO tmp_division_value_mismatch_target (monthly_stock_id)
VALUES
  (4083), -- BOTOL PLASTIK 1 L / BAR
  (5447), -- SO WANOJA FP / BAR
  (4681), -- KOL PUTIH / KITCHEN, rounding-only
  (4699), -- KULIT PANGSIT / KITCHEN
  (5511); -- PAPER BOX TA L / KITCHEN

DROP TEMPORARY TABLE IF EXISTS tmp_division_lot_value_sum;
CREATE TEMPORARY TABLE tmp_division_lot_value_sum AS
SELECT
  s.id AS monthly_stock_id,
  ROUND(SUM(l.qty_balance), 4) AS lot_qty,
  ROUND(SUM(l.qty_balance * l.unit_cost), 2) AS lot_value
FROM inv_division_monthly_stock s
JOIN tmp_division_value_mismatch_target t ON t.monthly_stock_id = s.id
JOIN inv_material_fifo_lot l
  ON l.location_scope = 'DIVISION'
 AND l.division_id = s.division_id
 AND l.destination_type = s.destination_type
 AND l.material_id = s.material_id
 AND l.profile_key = s.profile_key
 AND l.status = 'OPEN'
 AND l.qty_balance > 0
 AND l.receipt_date BETWEEN @date_from AND @date_to
WHERE s.month_key = @month_key
GROUP BY s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_division_lot_value_sum lv ON lv.monthly_stock_id = s.id
SET s.total_value = lv.lot_value,
    s.avg_cost_per_content = CASE
      WHEN ABS(COALESCE(s.closing_qty_content, 0)) > 0.000001
        THEN ROUND(lv.lot_value / s.closing_qty_content, 6)
      ELSE 0
    END,
    s.source_mode = 'REBUILD',
    s.notes = LEFT(CONCAT(COALESCE(s.notes, ''), ' | Repair 2026-07-12: sync value-only mismatch from current-month open FIFO lots'), 255),
    s.updated_at = NOW()
WHERE s.month_key = @month_key
  AND ABS(COALESCE(s.closing_qty_content, 0) - COALESCE(lv.lot_qty, 0)) <= 0.01;

COMMIT;
