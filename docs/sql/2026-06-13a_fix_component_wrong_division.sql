-- ============================================================
-- FIX: Component tidak bisa di-adjustment karena salah divisi
-- Error: "Divisi adjustment harus sama dengan divisi operasional
--         component yang dipilih."
--
-- ROOT CAUSE: mst_component.operational_division_id tidak cocok
--             dengan divisi yang dipakai pada halaman adjustment.
--
-- CARA PAKAI:
--   1. Jalankan STEP 1 untuk diagnosa lengkap.
--   2. Tentukan mana yang salah: master atau data stock.
--   3. Jalankan STEP 2 (fix master) ATAU STEP 3 (fix data stock).
--   4. Setelah fix, coba adjust lagi dari UI.
-- ============================================================


-- ============================================================
-- STEP 1 — Diagnosa: bandingkan master vs data aktual
-- ============================================================
SELECT
    c.id                        AS component_id,
    c.component_code,
    c.component_name,
    c.component_type,
    c.operational_division_id   AS master_div_id,
    dm.name                     AS master_div_name,

    ms.location_type            AS stock_location_type,
    ms.division_id              AS stock_div_id,
    ds.name                     AS stock_div_name,
    ms.month_key,
    ms.closing_qty,
    ms.avg_cost,

    IF(c.operational_division_id = ms.division_id,
       'SAMA ✓', 'BEDA ← MASALAH')  AS status_divisi

FROM mst_component c
LEFT JOIN mst_operational_division dm ON dm.id = c.operational_division_id
LEFT JOIN inv_component_monthly_stock ms ON ms.component_id = c.id
LEFT JOIN mst_operational_division ds ON ds.id = ms.division_id

WHERE c.component_name LIKE '%TING TING CRUMBLE%'
   OR c.component_code LIKE '%CRUMBLE%'

ORDER BY ms.month_key DESC;


-- Step 1B — Lihat lot aktif component ini
SELECT
    l.id            AS lot_id,
    l.lot_no,
    l.location_type,
    l.division_id   AS lot_div_id,
    d.name          AS lot_div_name,
    c.operational_division_id AS master_div_id,
    dm.name         AS master_div_name,
    l.qty_balance,
    l.unit_cost,
    l.status,
    IF(c.operational_division_id = l.division_id,
       'SAMA ✓', 'BEDA ← MASALAH') AS status_divisi
FROM inv_component_lot l
JOIN mst_component c ON c.id = l.component_id
LEFT JOIN mst_operational_division d ON d.id = l.division_id
LEFT JOIN mst_operational_division dm ON dm.id = c.operational_division_id
WHERE c.component_name LIKE '%TING TING CRUMBLE%'
   OR c.component_code LIKE '%CRUMBLE%'
ORDER BY l.status, l.receipt_date;


-- Step 1C — Daftar divisi aktif (untuk pilih divisi yang benar)
SELECT id, code, name
FROM mst_operational_division
WHERE is_active = 1
ORDER BY name;


-- ============================================================
-- STEP 2 — FIX JALUR A: Master component salah operational_division_id
-- (Gunakan ini jika "master_div_name" yang salah, stock sudah di divisi benar)
-- ============================================================

-- SET variabel:
SET @comp_id   = 0;   -- << dari hasil Step 1 (component_id)
SET @right_div = 0;   -- << divisi yang BENAR (stock_div_id dari Step 1 / id dari Step 1C)

-- Validasi sebelum fix:
SELECT
    c.id, c.component_name,
    c.operational_division_id AS sekarang,
    d_old.name AS nama_sekarang,
    @right_div AS akan_diubah_ke,
    d_new.name AS nama_baru
FROM mst_component c
LEFT JOIN mst_operational_division d_old ON d_old.id = c.operational_division_id
LEFT JOIN mst_operational_division d_new ON d_new.id = @right_div
WHERE c.id = @comp_id;

-- Jalankan fix jika hasil di atas sudah benar:
-- (uncomment 3 baris berikut setelah verifikasi)
-- UPDATE mst_component
-- SET operational_division_id = @right_div, updated_at = NOW()
-- WHERE id = @comp_id;

-- Verifikasi setelah fix:
SELECT id, component_name, operational_division_id, updated_at
FROM mst_component WHERE id = @comp_id;


-- ============================================================
-- STEP 3 — FIX JALUR B: Data stock/lot di divisi salah
-- (Gunakan ini jika master operational_division_id sudah benar,
--  tapi lot/movement_log tercatat di divisi yang salah)
-- ============================================================

SET @comp_id   = 0;      -- ID component (dari Step 1)
SET @loc_type  = 'BAR';  -- location_type (dari Step 1B)
SET @wrong_div = 0;      -- division_id yang SALAH di lot/stock
SET @right_div = 0;      -- division_id yang BENAR (= master operational_division_id)

-- Validasi:
SELECT
    IF(@comp_id > 0,   'OK', 'BELUM DIISI') AS comp_id_status,
    IF(@wrong_div > 0, 'OK', 'BELUM DIISI') AS wrong_div_status,
    IF(@right_div > 0, 'OK', 'BELUM DIISI') AS right_div_status,
    IF(@wrong_div <> @right_div, 'OK', 'SAMA — HARUS BEDA') AS div_beda_status;

START TRANSACTION;

-- 3A. Pindahkan lot
UPDATE inv_component_lot
SET division_id = @right_div, updated_at = NOW()
WHERE component_id = @comp_id
  AND division_id  = @wrong_div
  AND location_type = @loc_type;
SELECT ROW_COUNT() AS lot_rows_updated;

-- 3B. Pindahkan movement log
UPDATE inv_component_movement_log
SET division_id = @right_div, updated_at = NOW()
WHERE component_id = @comp_id
  AND division_id  = @wrong_div
  AND location_type = @loc_type;
SELECT ROW_COUNT() AS movement_log_rows_updated;

-- 3C. Hapus monthly stock (akan di-rebuild lewat Repair di UI)
DELETE FROM inv_component_monthly_stock
WHERE component_id = @comp_id
  AND location_type = @loc_type
  AND division_id IN (@wrong_div, @right_div);
SELECT ROW_COUNT() AS monthly_stock_rows_deleted;

-- 3D. Pindahkan opname snapshot (jika ada)
UPDATE inv_component_monthly_opname
SET division_id = @right_div, updated_at = NOW()
WHERE component_id = @comp_id
  AND division_id  = @wrong_div
  AND location_type = @loc_type;
SELECT ROW_COUNT() AS opname_rows_updated;

-- 3E. Pindahkan opening carry-forward (jika ada)
UPDATE inv_component_monthly_opening
SET division_id = @right_div, updated_at = NOW()
WHERE component_id = @comp_id
  AND division_id  = @wrong_div
  AND location_type = @loc_type;
SELECT ROW_COUNT() AS opening_rows_updated;

-- Verifikasi lot sebelum commit:
SELECT l.id, l.lot_no, l.division_id, d.name AS division_name, l.qty_balance, l.status
FROM inv_component_lot l
LEFT JOIN mst_operational_division d ON d.id = l.division_id
WHERE l.component_id = @comp_id AND l.location_type = @loc_type;

COMMIT;
-- atau: ROLLBACK;

-- ============================================================
-- SETELAH FIX (JALUR A atau B):
--   • Coba lagi adjustment dari UI — error divisi seharusnya hilang.
--   • Jika Jalur B: buka /production/component-reconcile
--     → klik Repair pada baris TING TING CRUMBLE untuk rebuild monthly stock.
-- ============================================================
