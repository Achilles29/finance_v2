SET NAMES utf8mb4;
SET @cur_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- Revert semua insert dari k-6 jika k-7 tidak cukup
DELETE FROM inv_division_monthly_stock
WHERE month_key   = @cur_month
  AND source_mode = 'REBUILD'
  AND notes LIKE '%Backfill missing monthly row from movement log 2026-06-07%';

SELECT ROW_COUNT() AS rows_deleted;
