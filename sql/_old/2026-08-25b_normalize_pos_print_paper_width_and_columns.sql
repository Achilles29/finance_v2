SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25b_normalize_pos_print_paper_width_and_columns.sql
-- Tujuan :
-- 1) Menyamakan lebar kertas dan jumlah karakter efektif printer POS
-- 2) Mencegah koneksi 58 mm lama tetap memakai 48 karakter per baris
-- 3) Menjadikan database, preview, dan engine cetak memakai aturan sama
--
-- Standar engine saat ini:
-- - 58 mm = 32 karakter per baris
-- - 80 mm = 48 karakter per baris
--
-- Catatan:
-- - Tidak mengubah transaksi, order, pembayaran, ataupun histori cetak.
-- - Aman dijalankan berulang kali.
-- ============================================================

START TRANSACTION;

UPDATE pos_print_connection
SET
  paper_width_mm = CASE
    WHEN COALESCE(paper_width_mm, 80) = 58 THEN 58
    ELSE 80
  END,
  chars_per_line = CASE
    WHEN COALESCE(paper_width_mm, 80) = 58 THEN 32
    ELSE 48
  END
WHERE COALESCE(paper_width_mm, 80) NOT IN (58, 80)
   OR (COALESCE(paper_width_mm, 80) = 58 AND COALESCE(chars_per_line, 0) <> 32)
   OR (COALESCE(paper_width_mm, 80) <> 58 AND COALESCE(chars_per_line, 0) <> 48);

COMMIT;

SELECT
  id,
  connection_code,
  connection_name,
  paper_width_mm,
  chars_per_line,
  is_active
FROM pos_print_connection
ORDER BY paper_width_mm ASC, connection_name ASC, id ASC;
