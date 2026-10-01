-- ============================================================
-- FIX: PAPER FILTER V60 Reconcile Mismatch + Self-Order Recipe
-- Date   : 2026-06-15
-- Scope  : db_finance
-- Ticket : investigasi PAPER FILTER V60 mismatch & order MSO-20260614153456-3FEA
-- ============================================================

-- 1. PAPER FILTER V60 — fix movement log row yang ter-save dengan profile_key NULL
--    Movement id=2235 (2026-06-06, divisi BAR, -1 pcs) seharusnya profile d2dadf1b
UPDATE inv_stock_movement_log
SET profile_key = 'd2dadf1bc2dcf28fbd3aef39cbf21cda985acdddbc4d86f3e7a93f1574ceb0fa'
WHERE id = 2235
  AND profile_key IS NULL
  AND material_id = 156;

-- 2. PAPER FILTER V60 — hapus baris monthly stock NULL profile (closing=0, tidak valid)
DELETE FROM inv_division_monthly_stock
WHERE id = 2728
  AND profile_key IS NULL
  AND material_id = 156
  AND closing_qty_content = 0;

-- 3. PAPER FILTER V60 — update monthly stock d2dadf1b: out +1, closing -1
--    (karena movement 2235 kini masuk ke profile ini, bukan NULL)
UPDATE inv_division_monthly_stock
SET out_qty_content     = out_qty_content + 1,
    closing_qty_content = closing_qty_content - 1
WHERE id = 2726
  AND profile_key = 'd2dadf1bc2dcf28fbd3aef39cbf21cda985acdddbc4d86f3e7a93f1574ceb0fa'
  AND material_id = 156;

-- 4. RECIPE — MANUAL BREW V60 JAPANESE (product_id=28)
--    Ganti PAPER FILTER KALITA WAVE (item 276) → PAPER FILTER V60 (item 277)
UPDATE mst_product_recipe
SET material_item_id = 277
WHERE id = 3351
  AND material_item_id = 276;

-- 5. COMMIT LINE — order MSO-20260614153456-3FEA (commit PSC-202606-0329)
--    Perbaiki material yang tersimpan salah di baris commit
UPDATE pos_stock_commit_line
SET material_id          = 156,
    source_name_snapshot = 'PAPER FILTER V60'
WHERE id = 20459
  AND material_id = 155;

-- ============================================================
-- Setelah SQL ini dijalankan:
-- - Rekonsiliasi PAPER FILTER V60 akan MATCH (d2dadf1b=14, 56c9dd81=45)
-- - Recipe MANUAL BREW V60 JAPANESE sekarang benar (V60, bukan KALITA WAVE)
-- - Self-order commit PSC-202606-0329 siap di-re-post dari UI
--   (Pergi ke halaman Stock Commit order MSO-20260614153456-3FEA, klik Post Stock)
-- ============================================================
