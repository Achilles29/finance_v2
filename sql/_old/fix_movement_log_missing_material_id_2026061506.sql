-- ============================================================
-- FIX: Backfill material_id di inv_stock_movement_log
-- Date  : 2026-06-15
-- Scope : production server
--
-- PENYEBAB:
--   PO receipt yang diproses sebelum mst_item.material_id diisi
--   tidak menyimpan material_id di movement_log.
--   Akibatnya /inventory-material-daily tidak bisa menampilkan
--   pergerakan PO tersebut (daily in = 0 padahal ada PO masuk).
--
-- Item yang terdampak (15 baris, semua PURCHASE_IN):
--   AIR MINERAL GALON, BUNCIS, CHOCO BALL, ESPRESSO ARABIKA,
--   GULA PASIR KITCHEN, ICE CUBE, JAMUR ENOKI, KECAP MANIS,
--   KEJU MOZARELLA, KEMIRI, KOL UNGU, KUNYIT,
--   MINYAK GORENG, MSG, TOMAT
--
-- VERIFIKASI SEBELUM:
-- SELECT COUNT(*) FROM inv_stock_movement_log ml
-- JOIN mst_item i ON i.id = ml.item_id AND i.material_id IS NOT NULL
-- WHERE ml.material_id IS NULL;
-- => Harus > 0 untuk ada yang perlu diperbaiki
-- ============================================================

UPDATE inv_stock_movement_log ml
JOIN mst_item i ON i.id = ml.item_id AND i.material_id IS NOT NULL
SET ml.material_id = i.material_id
WHERE ml.material_id IS NULL;

-- ============================================================
-- VERIFIKASI SETELAH:
-- SELECT COUNT(*) FROM inv_stock_movement_log ml
-- JOIN mst_item i ON i.id = ml.item_id AND i.material_id IS NOT NULL
-- WHERE ml.material_id IS NULL;
-- => Harus = 0
--
-- Cek ESPRESSO ARABIKA movement_log (material_id=229):
-- SELECT id, movement_date, movement_type, material_id,
--        qty_content_delta, profile_name
-- FROM inv_stock_movement_log
-- WHERE item_id = (SELECT id FROM mst_item WHERE item_code='ITM-MAT-000229')
-- ORDER BY movement_date, id;
-- ============================================================
