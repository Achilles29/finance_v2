-- ============================================================
-- CLEANUP: Hapus baris inv_division_monthly_stock tanpa material_id
-- Date  : 2026-06-15
-- Scope : production server
--
-- DAFTAR ITEM (25 baris):
--   BOX CATERING, GARPU PLASTIK, HANDTOWEL, KACANG PANJANG,
--   KERTAS NASI KFC (2x), MIKA VIP, PLASTIK 1 KG, PLASTIK 1/2 KG,
--   PLASTIK 1/4 KG, PLASTIK 2 KG, PLASTIK KERUPUK, PLASTIK KLIP (2x),
--   PLASTIK SELAMAT MENIKMATI (2x), PLASTIK SENDOK (2x), PLASTIK TA M,
--   SABUN CUCI PIRING (2x), SENDOK PLASTIK, SNACK CATERING,
--   THINWALL 35ML, TISSUE KATELERIS
--   @ BAR / KITCHEN
--
-- ALASAN: Barang-barang ini bukan bahan baku (tidak punya material_id)
--   dan salah masuk ke stok divisi. Tidak muncul di rekonsiliasi karena
--   tanpa material_id tersembunyi dari tabel reconcile.
--
-- VERIFIKASI SEBELUM:
-- SELECT COUNT(*) FROM inv_division_monthly_stock WHERE material_id IS NULL;
-- => Harus = 25 (atau lebih jika ada tambahan lain)
--
-- SELECT dms.id, i.item_name,
--        CASE WHEN dms.destination_type LIKE '%BAR%' THEN 'BAR' ELSE 'KITCHEN' END AS lokasi,
--        dms.closing_qty_content AS sisa_stok, dms.month_key
-- FROM inv_division_monthly_stock dms
-- LEFT JOIN mst_item i ON i.id = dms.item_id
-- WHERE dms.material_id IS NULL
-- ORDER BY i.item_name, dms.destination_type;
-- ============================================================

-- Step 1: Hapus dari inv_division_monthly_stock
DELETE FROM inv_division_monthly_stock
WHERE material_id IS NULL;

-- Step 2: Hapus dari inv_material_fifo_lot (lot tanpa material_id dari item yang sama)
DELETE FROM inv_material_fifo_lot
WHERE material_id IS NULL
  AND location_scope = 'DIVISION'
  AND item_id IN (
      SELECT id FROM mst_item
      WHERE material_id IS NULL
        AND item_name IN (
            'BOX CATERING','GARPU PLASTIK','HANDTOWEL','KACANG PANJANG',
            'KERTAS NASI KFC','MIKA VIP','PLASTIK 1 KG','PLASTIK 1/2 KG',
            'PLASTIK 1/4 KG','PLASTIK 2 KG','PLASTIK KERUPUK','PLASTIK KLIP',
            'PLASTIK SELAMAT MENIKMATI','PLASTIK SENDOK','PLASTIK TA M',
            'SABUN CUCI PIRING','SENDOK PLASTIK','SNACK CATERING',
            'THINWALL 35ML','TISSUE KATELERIS'
        )
  );

-- Step 3: Hapus dari inv_stock_movement_log (movement tanpa material_id dari item yang sama)
DELETE FROM inv_stock_movement_log
WHERE material_id IS NULL
  AND movement_scope = 'DIVISION'
  AND item_id IN (
      SELECT id FROM mst_item
      WHERE material_id IS NULL
        AND item_name IN (
            'BOX CATERING','GARPU PLASTIK','HANDTOWEL','KACANG PANJANG',
            'KERTAS NASI KFC','MIKA VIP','PLASTIK 1 KG','PLASTIK 1/2 KG',
            'PLASTIK 1/4 KG','PLASTIK 2 KG','PLASTIK KERUPUK','PLASTIK KLIP',
            'PLASTIK SELAMAT MENIKMATI','PLASTIK SENDOK','PLASTIK TA M',
            'SABUN CUCI PIRING','SENDOK PLASTIK','SNACK CATERING',
            'THINWALL 35ML','TISSUE KATELERIS'
        )
  );

-- ============================================================
-- VERIFIKASI SETELAH:
-- SELECT COUNT(*) FROM inv_division_monthly_stock WHERE material_id IS NULL;
-- => Harus = 0
--
-- SELECT COUNT(*) FROM inv_material_fifo_lot
-- WHERE material_id IS NULL AND location_scope = 'DIVISION';
-- => Harus = 0 (atau sisa yang memang bukan dari daftar di atas)
-- ============================================================
