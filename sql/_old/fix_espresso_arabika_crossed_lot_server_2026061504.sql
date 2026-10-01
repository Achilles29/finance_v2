-- ============================================================
-- FIX: ESPRESSO ARABIKA crossed-profile lot mismatch
-- Date   : 2026-06-15
-- Scope  : production server (tanpa hardcoded ID)
--
-- SITUASI sebelum fix:
--   Profil fugold (9f672e3c): CORR lot OPEN balance>0, tapi monthly_stock closing=0
--   Profil hayati (7a01c2e6): monthly_stock closing=108, tapi tidak ada lot OPEN
--
-- PENYEBAB:
--   Repair LOT jalan saat ada stale row fugold → CORR lot 1000g untuk fugold
--   → Repair LOT ke-2 deduct 892g (gap = lot 1000 - stock 108)
--   → Sisa 108g di profil yang salah (fugold, seharusnya hayati)
--
-- VERIFIKASI SEBELUM JALANKAN:
-- SELECT l.id, l.lot_no, SUBSTR(l.profile_key,1,8) pk, l.qty_balance, l.status,
--        s.closing_qty_content AS stock_closing
-- FROM inv_material_fifo_lot l
-- JOIN inv_division_monthly_stock s
--   ON s.division_id=l.division_id AND s.destination_type='BAR'
--  AND s.profile_key=l.profile_key AND s.month_key=(SELECT MAX(month_key) FROM inv_division_monthly_stock WHERE material_id=229 AND division_id=2)
-- WHERE l.material_id=229 AND l.division_id=2
--   AND l.location_scope='DIVISION' AND l.status='OPEN' AND l.qty_balance>0;
-- => Jika ada baris dengan profile=9f672e3c dan stock_closing=0 → masih perlu fix
-- => Jika sudah kosong → sudah fix, jangan jalankan lagi
-- ============================================================

-- Step 1: Tutup semua CORR lot OPEN untuk fugold (9f672e3c) ESPRESSO ARABIKA BAR
--         di mana monthly_stock closing untuk profil itu = 0
UPDATE inv_material_fifo_lot l
JOIN (
    SELECT s.profile_key
    FROM inv_division_monthly_stock s
    WHERE s.material_id = 229
      AND s.division_id = 2
      AND s.destination_type = 'BAR'
      AND s.closing_qty_content = 0
      AND s.profile_key = '9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
      AND s.month_key = (
          SELECT MAX(ms2.month_key)
          FROM inv_division_monthly_stock ms2
          WHERE ms2.material_id = 229 AND ms2.division_id = 2
      )
) zero_profile ON zero_profile.profile_key = l.profile_key
SET l.qty_out    = l.qty_out + l.qty_balance,
    l.qty_balance = 0,
    l.status      = 'CLOSED',
    l.updated_at  = NOW()
WHERE l.material_id       = 229
  AND l.division_id       = 2
  AND l.location_scope    = 'DIVISION'
  AND l.status            = 'OPEN'
  AND l.qty_balance       > 0;

-- Verifikasi step 1: harus 1 row affected (atau 0 jika sudah pernah dijalankan)
-- SELECT COUNT(*) FROM inv_material_fifo_lot
-- WHERE material_id=229 AND division_id=2 AND location_scope='DIVISION'
--   AND profile_key='9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
--   AND status='OPEN' AND qty_balance>0;
-- => Harus = 0

-- Step 2: Buat CORR lot untuk hayati (7a01c2e6) sesuai closing dari monthly_stock
--         Hanya berjalan jika hayati BELUM punya lot OPEN (idempotent)
INSERT INTO inv_material_fifo_lot (
    lot_no, location_scope, receipt_date, expiry_date,
    division_id, destination_type, item_id, material_id,
    buy_uom_id, content_uom_id, profile_key,
    qty_in, qty_out, qty_balance, unit_cost,
    source_table, source_id, source_line_id,
    receipt_id, receipt_line_id, parent_lot_id, status
)
SELECT
    CONCAT('CORR-', DATE_FORMAT(NOW(),'%Y%m%d-%H%i%S'), '-M229-HAYATI') AS lot_no,
    'DIVISION',
    CURDATE(),
    NULL,
    s.division_id,
    s.destination_type,
    s.item_id,
    s.material_id,
    s.buy_uom_id,
    s.content_uom_id,
    s.profile_key,
    s.closing_qty_content AS qty_in,
    0.0                   AS qty_out,
    s.closing_qty_content AS qty_balance,
    0.0                   AS unit_cost,
    'lot_repair', NULL, NULL, NULL, NULL, NULL, 'OPEN'
FROM inv_division_monthly_stock s
WHERE s.material_id       = 229
  AND s.division_id       = 2
  AND s.destination_type  = 'BAR'
  AND s.profile_key       = '7a01c2e626c2ae7c1ab5de1364b1c2d2ace087e6b5f8acc0b00ea642a9f91d85'
  AND s.closing_qty_content > 0
  AND s.month_key = (
      SELECT MAX(ms2.month_key)
      FROM inv_division_monthly_stock ms2
      WHERE ms2.material_id = 229 AND ms2.division_id = 2
  )
  AND NOT EXISTS (
      SELECT 1 FROM inv_material_fifo_lot ex
      WHERE ex.material_id    = 229
        AND ex.division_id    = 2
        AND ex.location_scope = 'DIVISION'
        AND ex.profile_key    = '7a01c2e626c2ae7c1ab5de1364b1c2d2ace087e6b5f8acc0b00ea642a9f91d85'
        AND ex.status         = 'OPEN'
        AND ex.qty_balance    > 0
  );

-- ============================================================
-- CEK FINAL (setelah fix):
-- SELECT SUBSTR(l.profile_key,1,8) pk,
--        SUM(l.qty_balance) AS lot_balance,
--        s.closing_qty_content AS stock_closing
-- FROM inv_material_fifo_lot l
-- JOIN (
--   SELECT profile_key, closing_qty_content
--   FROM inv_division_monthly_stock
--   WHERE material_id=229 AND division_id=2 AND destination_type='BAR'
--     AND month_key=(SELECT MAX(month_key) FROM inv_division_monthly_stock WHERE material_id=229 AND division_id=2)
-- ) s ON s.profile_key=l.profile_key
-- WHERE l.material_id=229 AND l.division_id=2 AND l.location_scope='DIVISION' AND l.status='OPEN'
-- GROUP BY l.profile_key, s.closing_qty_content;
-- => hayati: lot_balance=108, stock_closing=108 ✅
-- => fugold: lot_balance=0 (tidak ada row) stock_closing=0 ✅
-- ============================================================
