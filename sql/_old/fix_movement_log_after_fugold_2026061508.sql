-- ============================================================
-- FIX: Rebuild qty_content_after & qty_buy_after untuk FUGOLD
-- Date  : 2026-06-15
-- Scope : production server
-- Item  : ESPRESSO ARABIKA, BAR div=2, profil FUGOLD (9f672e3c)
--
-- MASALAH:
--   Setelah Repair LOT mengubah FIFO lot balance fugold menjadi 0,
--   semua USAGE_OUT berikutnya mencatat qty_content_after=0
--   (karena FIFO lot sudah habis saat POS diproses).
--   Akibatnya audit trail movement_log tidak menggambarkan
--   running balance yang sebenarnya.
--
-- RUNNING BALANCE YANG BENAR (berdasarkan Σdelta):
--   Jun 02 PURCHASE_IN +1000 → after = 1000
--   Jun 08 USAGE_OUT   -18   → after = 982
--   Jun 08 USAGE_OUT   -9    → after = 973
--   Jun 09 USAGE_OUT   -18   → after = 955
--   Jun 09 USAGE_OUT   -9    → after = 946
--   Jun 10 USAGE_OUT   -18   → after = 928
--   Jun 10 USAGE_OUT   -27   → after = 901
--   Jun 12 USAGE_OUT   -9    → after = 892
--   Jun 13 USAGE_OUT   -9    → after = 883
--   Jun 14 USAGE_OUT   -9    → after = 874
--   Jun 14 USAGE_OUT   -9    → after = 865
--   Jun 14 USAGE_OUT   -9    → after = 856
--   Jun 15 USAGE_OUT   -18   → after = 838
--   Jun 15 USAGE_OUT   -9    → after = 829
--   Jun 15 USAGE_OUT   -9    → after = 820
--   Jun 15 USAGE_OUT   -18   → after = 802  ← final net
--
-- Total: in=1000, out=198, net=802
-- Gap dengan monthly_stock closing(0) = -802 → akan muncul sebagai
-- "⚠ Log Gap -802.00" di /inventory-material-daily
--
-- VERIFIKASI SEBELUM:
-- SELECT COUNT(*) FROM inv_stock_movement_log
-- WHERE item_id IN (SELECT id FROM mst_item WHERE material_id=229)
--   AND division_id=2 AND destination_type='BAR'
--   AND profile_key='9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
--   AND qty_content_after = 0 AND qty_content_delta < 0;
-- => Jumlah baris bermasalah (harus 0 setelah fix)
-- ============================================================

-- Rebuild qty_content_after dengan running sum berdasarkan ORDER BY movement_date, id
-- @rc dan @rb: session variable untuk running total content dan buy
SET @rc := 0;
SET @rb := 0;

UPDATE inv_stock_movement_log
SET qty_content_after = (@rc := ROUND(@rc + qty_content_delta, 4)),
    qty_buy_after     = (@rb := ROUND(@rb + qty_buy_delta, 4))
WHERE item_id IN (SELECT id FROM mst_item WHERE material_id = 229)
  AND division_id       = 2
  AND destination_type  = 'BAR'
  AND profile_key       = '9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
ORDER BY movement_date, id;

-- ============================================================
-- VERIFIKASI SETELAH:
-- SELECT id, movement_date, movement_type,
--        qty_content_delta, qty_content_after, qty_buy_after
-- FROM inv_stock_movement_log
-- WHERE item_id IN (SELECT id FROM mst_item WHERE material_id=229)
--   AND division_id=2 AND destination_type='BAR'
--   AND profile_key='9f672e3c109f6f48d059082a4eed9f1d7788f802f1f2bc6eb2d567211a06952d'
-- ORDER BY movement_date, id;
-- => PURCHASE_IN Jun-02: after=1000
-- => USAGE_OUT terakhir Jun-15: after=802
-- ============================================================
