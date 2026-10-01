-- ============================================================
-- FIX: Sesuaikan inv_division_monthly_stock FUGOLD dengan movement_log
-- Date  : 2026-06-15
-- Scope : production server
-- Item  : ESPRESSO ARABIKA, division BAR (div=2), profil FUGOLD (9f672e3c)
--
-- FAKTA DARI MOVEMENT LOG (setelah material_id dibackfill):
--   PURCHASE_IN  : +1000g  (PO Jun 2 - PO202606020010)
--   USAGE_OUT    : -198g   (POS consumption Jun 8–15)
--   OPENING (May): 0g      (tidak ada opening snapshot untuk fugold)
--   PREDICTED CLOSING = 0 + 1000 - 198 = +802g
--
-- FAKTA DARI MONTHLY_STOCK (correct row, identity_key=profile_key):
--   opening_qty_content : 0
--   in_qty_content      : 0   ← SALAH (seharusnya 1000 dari PO)
--   out_qty_content     : 198 ← BENAR (POS sudah terekam)
--   closing_qty_content : 0   ← diupdate sesuai keputusan bisnis (lihat catatan)
--
-- SELISIH (GAP): monthly_stock.closing(0) vs movement_log prediction(802)
--   Gap = -802g → ada 802g "phantom correction" yang tidak tercatat di movement_log
--   (terjadi saat Repair LOT membuat CORR lot dan kemudian deduct/close di lot,
--   tapi tidak menulis movement_log untuk koreksi ini)
--
-- FIX:
--   Update in_qty_content = 1000 agar sesuai PO actual.
--   Setelah ini, "Log Gap" di daily matrix akan menjadi -802
--   (closing 0 - (0+1000-198) = -802) yang menjadi REMINDER
--   bahwa ada 802g koreksi phantom yang belum tercatat di movement_log.
--
-- CATATAN: closing_qty_content TIDAK diubah di sini karena:
--   a) Perlu investigasi lebih lanjut: apakah 802g masih ada fisik?
--   b) Jika 802g memang phantom (stok tidak ada fisik), closing tetap 0.
--   c) Jika 802g ada fisik, jalankan stock opname / adjustment.
-- ============================================================

-- VERIFIKASI SEBELUM:
-- SELECT id, month_key, SUBSTR(profile_key,1,8) AS pk, profile_name,
--        opening_qty_content, in_qty_content, out_qty_content, closing_qty_content,
--        IF(identity_key=profile_key,'OK','STALE') AS status_ik
-- FROM inv_division_monthly_stock
-- WHERE division_id=2 AND destination_type='BAR'
--   AND profile_key='9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
--   AND month_key='2026-06-01';
-- => Harus ada 1 baris OK (identity_key=profile_key) dengan in=0, out=198, closing=0

-- Fix in_qty_content agar sesuai movement_log PURCHASE_IN
UPDATE inv_division_monthly_stock
SET in_qty_content   = (
        SELECT COALESCE(SUM(qty_content_delta), 0)
        FROM inv_stock_movement_log
        WHERE item_id        = (SELECT id FROM mst_item WHERE material_id = 229 LIMIT 1)
          AND division_id    = 2
          AND destination_type = 'BAR'
          AND profile_key    = '9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
          AND movement_type  = 'PURCHASE_IN'
          AND movement_date BETWEEN '2026-06-01' AND '2026-06-30'
    ),
    in_qty_buy       = (
        SELECT COALESCE(SUM(qty_buy_delta), 0)
        FROM inv_stock_movement_log
        WHERE item_id        = (SELECT id FROM mst_item WHERE material_id = 229 LIMIT 1)
          AND division_id    = 2
          AND destination_type = 'BAR'
          AND profile_key    = '9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
          AND movement_type  = 'PURCHASE_IN'
          AND movement_date BETWEEN '2026-06-01' AND '2026-06-30'
    ),
    updated_at = NOW()
WHERE division_id      = 2
  AND destination_type = 'BAR'
  AND profile_key      = '9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
  AND identity_key     = profile_key
  AND month_key        = '2026-06-01';

-- ============================================================
-- VERIFIKASI SETELAH:
-- SELECT opening_qty_content, in_qty_content, out_qty_content, closing_qty_content,
--        (opening_qty_content + in_qty_content - out_qty_content) AS predicted,
--        closing_qty_content - (opening_qty_content + in_qty_content - out_qty_content) AS gap
-- FROM inv_division_monthly_stock
-- WHERE division_id=2 AND destination_type='BAR'
--   AND profile_key='9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
--   AND identity_key=profile_key AND month_key='2026-06-01';
-- => opening=0, in=1000, out=198, closing=0, predicted=802, gap=-802
-- => GAP -802 akan muncul sebagai "⚠ Log Gap -802.00" di daily matrix
-- ============================================================
