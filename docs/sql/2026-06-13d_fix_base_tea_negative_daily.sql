-- ============================================================
-- FIX: BASE TEA — saldo harian minus menyebabkan generate opname gagal
--
-- DIAGNOSIS:
-- Produksi batch dicatat terlambat → usage melebihi stok harian
-- dari 05-Jun hingga 12-Jun. Saldo akhir bulan = 0 (balance benar)
-- tapi daily closing_qty negatif pada hari-hari tersebut.
--
-- Saldo harian sebelum fix:
--   06-01: +3250 ✓   06-05:   -600 ✗   06-09:  -7450 ✗
--   06-02: +2500 ✓   06-06:  -1500 ✗   06-10:  -9350 ✗ (terparah)
--   06-03: +2300 ✓   06-07:  -6250 ✗   06-11:  -5500 ✗
--                    06-08:  -6550 ✗   06-12:  -6350 ✗
--                                       06-13:      0 ✓
--
-- SOLUSI: Pindah tanggal 3 movement sehingga produksi tersedia
--         sebelum usage. Saldo akhir bulan tetap = 0.
--
-- Perubahan:
--   A. Batch ICB202606110009 (PROD_IN 4000): 06-11 → 06-04
--   B. Adjustment ICA89 (ADJ_PLUS 5550):     06-13 → 06-04
--   C. Batch ICB202606130001 (PROD_IN 4000): 06-13 → 06-11
--
-- Saldo harian setelah fix (semua ≥ 0):
--   06-01: +3250   06-05: +8950   06-09: +2100
--   06-02: +2500   06-06: +8050   06-10:  +200
--   06-03: +2300   06-07: +3300   06-11: +4050
--   06-04:+11850   06-08: +3000   06-12: +3200
--                                  06-13:    0
-- ============================================================

START TRANSACTION;

-- ── A. Batch ICB202606110009: pindah 06-11 → 06-04 ─────────
UPDATE inv_component_movement_log
SET movement_date = '2026-06-04', movement_datetime = '2026-06-04 08:00:00'
WHERE id = 3299;   -- PRODUCTION_IN 4000gr, batch 163

UPDATE inv_component_batch
SET batch_date = '2026-06-04', updated_at = NOW()
WHERE id = 163;    -- ICB202606110009

UPDATE inv_component_lot
SET receipt_date = '2026-06-04', updated_at = NOW()
WHERE id = 347;    -- ICL202606110000800163

SELECT '3299 moved 06-11→06-04' AS step_a;

-- ── B. Adjustment ICA89: pindah 06-13 → 06-04 ──────────────
UPDATE inv_component_movement_log
SET movement_date = '2026-06-04', movement_datetime = '2026-06-04 09:00:00'
WHERE id = 4239;   -- ADJUSTMENT_PLUS 5550gr, adj 89

UPDATE inv_component_lot
SET receipt_date = '2026-06-04', updated_at = NOW()
WHERE id = 389;    -- ICA2026061300008000890095P

SELECT '4239 moved 06-13→06-04' AS step_b;

-- ── C. Batch ICB202606130001: pindah 06-13 → 06-11 ─────────
UPDATE inv_component_movement_log
SET movement_date = '2026-06-11', movement_datetime = '2026-06-11 08:00:00'
WHERE id = 3647;   -- PRODUCTION_IN 4000gr, batch 165

UPDATE inv_component_batch
SET batch_date = '2026-06-11', updated_at = NOW()
WHERE id = 165;    -- ICB202606130001

UPDATE inv_component_lot
SET receipt_date = '2026-06-11', updated_at = NOW()
WHERE id = 351;    -- ICL202606130000800165

SELECT '3647 moved 06-13→06-11' AS step_c;

-- ── Hapus monthly_stock lama ─────────────────────────────────
DELETE FROM inv_component_monthly_stock
WHERE component_id = 8;

SELECT ROW_COUNT() AS monthly_stock_deleted;

-- ── Verifikasi: ulang hitung saldo harian setelah fix ────────
SELECT
    movement_date,
    SUM(qty_in)  AS in_hari,
    SUM(qty_out) AS out_hari,
    @b := @b + SUM(qty_in) - SUM(qty_out) AS closing_qty_setelah_fix,
    IF(@b + SUM(qty_in) - SUM(qty_out) < 0, '✗ MASIH MINUS', '✓') AS status
FROM inv_component_movement_log, (SELECT @b := 0) init
WHERE component_id = 8
GROUP BY movement_date
ORDER BY movement_date;

COMMIT;
-- atau: ROLLBACK;

-- ============================================================
-- SETELAH COMMIT:
-- Buka /production/component-reconcile → filter divisi BAR
-- → klik Repair pada baris BASE TEA
-- Ini akan rebuild inv_component_monthly_stock dengan nilai
-- yang benar (total_value tidak akan negatif lagi).
-- ============================================================
