SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21c_pos_refund_cash_and_gross_amount_schema.sql
-- Tujuan :
-- 1) Memisahkan nilai barang sebelum refund dari uang yang benar-benar
--    dikembalikan ke customer pada setiap line refund POS.
-- 2) Menjaga laporan produk, extra, diskon, refund, dan HPP tetap benar
--    ketika refund dilakukan pada order yang memakai potongan.
--
-- Catatan:
-- - gross_amount_refunded = nilai produk/extra sebelum potongan proporsional.
-- - amount_refunded       = uang aktual yang dikembalikan ke customer.
-- - Script tidak mengubah nominal refund, stok, lot, kas, atau status dokumen.
--   Script hanya mengisi kolom nilai kotor baru sebagai metadata laporan.
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_refund_line
  ADD COLUMN IF NOT EXISTS gross_amount_refunded DECIMAL(18,2) NOT NULL DEFAULT 0.00
  AFTER amount_refunded;

-- Data historis belum menyimpan nilai kotor line. Untuk refund tanpa pola
-- diskon proporsional, amount_refunded adalah fallback paling aman.
UPDATE pos_refund_line
SET gross_amount_refunded = COALESCE(amount_refunded, 0)
WHERE COALESCE(gross_amount_refunded, 0) = 0
  AND COALESCE(amount_refunded, 0) <> 0;

COMMIT;

SELECT
  COUNT(*) AS refund_line_total,
  SUM(CASE WHEN COALESCE(gross_amount_refunded, 0) > 0 THEN 1 ELSE 0 END) AS refund_line_with_gross_amount,
  SUM(CASE WHEN COALESCE(gross_amount_refunded, 0) = 0
             AND COALESCE(amount_refunded, 0) <> 0 THEN 1 ELSE 0 END) AS refund_line_still_missing_gross_amount
FROM pos_refund_line;
