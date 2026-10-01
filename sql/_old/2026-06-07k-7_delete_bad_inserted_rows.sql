SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Hapus baris yang baru diinsert k-6 yang bermasalah:
-- 1. material_id = NULL (item tanpa link material, tidak relevan untuk material reconcile)
-- 2. closing_qty_content <= 0 (saldo negatif atau nol, tidak valid)
DELETE FROM inv_division_monthly_stock
WHERE month_key   = @cur_month
  AND source_mode = 'REBUILD'
  AND notes LIKE '%Backfill missing monthly row from movement log 2026-06-07%'
  AND (material_id IS NULL OR closing_qty_content <= 0.001);

SELECT ROW_COUNT() AS rows_deleted;
