-- ============================================================
-- CLEANUP: Zero-out stale inv_division_monthly_stock rows
-- Date  : 2026-06-15
-- Scope : production server
--
-- PENYEBAB:
--   Repair Material ID mengisi material_id tapi tidak update identity_key.
--   identity_key yang lama (SHA256 dengan material=0) tetap ada di DB.
--   Kemudian upsertMonthlyStock membuat ROW BARU dengan identity_key=profile_key
--   yang benar (misal saat POS transaksi masuk).
--   Hasilnya: 2 row per profil — stale (identity_key lama) dan correct (identity_key=profile_key).
--
--   fix_stale_identity_key mencoba UPDATE IGNORE identity_key=profile_key,
--   tapi gagal karena UNIQUE KEY conflict (correct row sudah ada).
--   Stale row tetap ada dengan closing_qty_content yang SALAH (nilai lama sebelum repair).
--
-- DAMPAK:
--   /inventory-material-daily pivot pakai profile_key sebagai key.
--   Stale row (closing_qty lama) menimpa correct row (closing_qty benar)
--   → tampilan daily matrix salah (misal fugold=802, hayati=-93)
--
-- FIX:
--   Zero-out semua qty di stale rows (profil rows dengan identity_key != profile_key).
--   Baris tetap ada di DB tapi semua qty=0 → tidak mempengaruhi tampilan.
--   Setelah ini jalankan fix_stale_identity_key untuk update identity_key.
--
-- VERIFIKASI SEBELUM:
-- SELECT COUNT(*) FROM inv_division_monthly_stock
-- WHERE profile_key IS NOT NULL AND profile_key != identity_key;
-- => Catat angkanya, harus sama dengan jumlah affected rows setelah UPDATE
-- ============================================================

-- Step 1: Zero-out semua qty di stale rows
UPDATE inv_division_monthly_stock
SET opening_qty_buy          = 0,
    opening_qty_content      = 0,
    opening_total_value      = 0,
    in_qty_buy               = 0,
    in_qty_content           = 0,
    in_total_value           = 0,
    out_qty_buy              = 0,
    out_qty_content          = 0,
    out_total_value          = 0,
    discarded_qty_buy        = 0,
    discarded_qty_content    = 0,
    discarded_total_value    = 0,
    spoil_qty_buy            = 0,
    spoil_qty_content        = 0,
    spoilage_total_value     = 0,
    waste_qty_buy            = 0,
    waste_qty_content        = 0,
    waste_total_value        = 0,
    process_loss_qty_buy     = 0,
    process_loss_qty_content = 0,
    process_loss_total_value = 0,
    variance_qty_buy         = 0,
    variance_qty_content     = 0,
    variance_total_value     = 0,
    adjustment_plus_qty_buy  = 0,
    adjustment_plus_qty_content = 0,
    adjustment_plus_total_value = 0,
    adjustment_minus_qty_buy = 0,
    adjustment_minus_qty_content = 0,
    adjustment_minus_total_value = 0,
    closing_qty_buy          = 0,
    closing_qty_content      = 0,
    avg_cost_per_content     = 0,
    total_value              = 0,
    notes                    = CONCAT(COALESCE(notes,''), ' [ZEROED: stale identity_key ', SUBSTR(identity_key,1,8), ']'),
    updated_at               = NOW()
WHERE profile_key IS NOT NULL
  AND profile_key != identity_key;

-- Step 2: Setelah zero-out, update identity_key agar tidak duplikat lagi
-- (tidak pakai UPDATE IGNORE karena conflict sudah tidak ada setelah zero-out,
--  tapi unique key masih ada → jika correct row ada, conflict tetap muncul.
--  Gunakan DELETE saja untuk bersihkan stale rows yang sudah di-zero.)
DELETE FROM inv_division_monthly_stock
WHERE profile_key IS NOT NULL
  AND profile_key != identity_key
  AND closing_qty_content = 0
  AND opening_qty_content = 0
  AND in_qty_content      = 0
  AND out_qty_content     = 0;

-- ============================================================
-- VERIFIKASI SETELAH:
-- SELECT COUNT(*) FROM inv_division_monthly_stock
-- WHERE profile_key IS NOT NULL AND profile_key != identity_key;
-- => Harus = 0
--
-- Cek ESPRESSO ARABIKA (item_id = 165 di server):
-- SELECT id, month_key, SUBSTR(profile_key,1,8) pk, SUBSTR(identity_key,1,8) ik,
--        profile_name, opening_qty_content, closing_qty_content
-- FROM inv_division_monthly_stock
-- WHERE division_id=2 AND destination_type='BAR' AND material_id=229
-- ORDER BY month_key, profile_name;
-- => Hanya 2 rows, keduanya OK (pk=ik), hayati=108 fugold=0
-- ============================================================
