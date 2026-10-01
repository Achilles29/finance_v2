SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-18b_recalculate_stock_deficit_remaining_value.sql
-- Tujuan :
-- 1) Menyamakan nilai rupiah defisit dengan qty sisa terbaru
-- 2) Merapikan row VOID lama agar tidak lagi menyimpan qty terbuka
--
-- Aman dijalankan berulang kali. Script ini tidak membuat stok, lot,
-- movement, atau saldo baru; hanya merapikan angka pada ledger defisit.
-- ============================================================

START TRANSACTION;

-- Defisit yang di-VOID tidak lagi menjadi kewajiban stok terbuka.
UPDATE inv_stock_deficit
SET reversed_qty = ROUND(COALESCE(reversed_qty, 0) + GREATEST(COALESCE(qty_remaining, 0), 0), 4),
    qty_remaining = 0.0000,
    estimated_total_value = 0.00,
    updated_at = CURRENT_TIMESTAMP
WHERE status = 'VOID'
  AND COALESCE(qty_remaining, 0) > 0.0001;

-- Nilai sisa selalu dihitung dari qty yang memang masih terbuka.
UPDATE inv_stock_deficit
SET estimated_total_value = ROUND(
      GREATEST(COALESCE(qty_remaining, 0), 0) * COALESCE(estimated_unit_cost, 0),
      2
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE ABS(
      COALESCE(estimated_total_value, 0)
      - ROUND(GREATEST(COALESCE(qty_remaining, 0), 0) * COALESCE(estimated_unit_cost, 0), 2)
    ) > 0.009;

COMMIT;

SELECT
  status,
  COUNT(*) AS total_rows,
  ROUND(SUM(COALESCE(qty_remaining, 0)), 4) AS qty_remaining,
  ROUND(SUM(COALESCE(estimated_total_value, 0)), 2) AS estimated_total_value
FROM inv_stock_deficit
GROUP BY status
ORDER BY FIELD(status, 'OPEN', 'SETTLED', 'VOID');
