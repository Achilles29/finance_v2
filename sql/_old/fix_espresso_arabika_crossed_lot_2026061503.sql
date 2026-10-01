-- ============================================================
-- FIX: ESPRESSO ARABIKA crossed-profile lot mismatch (BAR div=2)
-- Date   : 2026-06-15
-- Scope  : production server
--
-- SITUASI:
--   Profil fugold (9f672e3c): monthly_stock closing=0, CORR lot balance=108  ← salah
--   Profil hayati (7a01c2e6): monthly_stock closing=108, lot balance=0        ← kurang lot
--
--   Total stock=108, total lot=108 → total match, tapi profil silang.
--   FIFO akan memotong dari profil yang salah sehingga hayati bisa minus.
--
-- PENYEBAB:
--   Repair LOT jalan saat ada stale monthly_stock row fugold closing=1000
--   → CORR lot 1000g dibuat untuk fugold
--   → Repair LOT ke-2 deduct 892g (gap = lot 1000 - stock 108 = 892)
--   → Sisa 108g di profil yang salah (fugold, seharusnya hayati)
--
-- VERIFIKASI SEBELUM JALANKAN:
-- SELECT id, lot_no, profile_key, qty_in, qty_out, qty_balance, status
-- FROM inv_material_fifo_lot
-- WHERE material_id=229 AND division_id=2 AND location_scope='DIVISION'
--   AND status='OPEN' AND qty_balance > 0;
-- => Harus ada id=1505, profile=9f672e3c..., balance=108
-- ============================================================

-- Step 1: Tutup CORR lot yang salah profil (fugold, seharusnya tidak punya sisa)
UPDATE inv_material_fifo_lot
SET qty_out    = qty_out + qty_balance,
    qty_balance = 0,
    status      = 'CLOSED',
    updated_at  = NOW()
WHERE id = 1505
  AND status = 'OPEN'
  AND qty_balance > 0;

-- Verifikasi step 1: harus 1 row affected, id=1505 status=CLOSED, balance=0
-- SELECT id, status, qty_balance FROM inv_material_fifo_lot WHERE id=1505;

-- Step 2: Setelah SQL ini, jalankan Repair LOT dari UI untuk ESPRESSO ARABIKA
--   → halaman /inventory/stock/division/reconcile → klik Repair LOT untuk ESPRESSO ARABIKA
--   → Sistem akan buat CORR lot baru 108g untuk profil hayati (7a01c2e6)
--   → Akhirnya: hayati stock=108 lot=108 ✅, fugold stock=0 lot=0 ✅
--
-- ATAU jalankan langsung:
INSERT INTO inv_material_fifo_lot (
    lot_no, location_scope, receipt_date, expiry_date,
    division_id, destination_type, item_id, material_id,
    buy_uom_id, content_uom_id, profile_key,
    qty_in, qty_out, qty_balance, unit_cost,
    source_table, source_id, source_line_id,
    receipt_id, receipt_line_id, parent_lot_id, status
)
SELECT
    CONCAT('CORR-', DATE_FORMAT(NOW(),'%Y%m%d-%H%i%S'), '-M229-FIX') AS lot_no,
    'DIVISION', CURDATE(), NULL,
    2, 'BAR',
    (SELECT item_id FROM inv_division_monthly_stock WHERE id=2745),
    229,
    (SELECT buy_uom_id FROM inv_division_monthly_stock WHERE id=2745),
    (SELECT content_uom_id FROM inv_division_monthly_stock WHERE id=2745),
    '7a01c2e626c2ae7c1ab5de1364b1c2d2ace087e6b5f8acc0b00ea642a9f91d85',
    108.0, 0.0, 108.0, 0.0,
    'lot_repair', NULL, NULL, NULL, NULL, NULL, 'OPEN';

-- ============================================================
-- SETELAH FIX: cek final
-- SELECT SUBSTR(profile_key,1,20) pk, qty_balance, status
-- FROM inv_material_fifo_lot
-- WHERE material_id=229 AND division_id=2 AND location_scope='DIVISION'
--   AND status='OPEN';
-- => Hanya profile hayati (7a01c2e6...) dengan balance=108 yang tersisa
-- ============================================================
