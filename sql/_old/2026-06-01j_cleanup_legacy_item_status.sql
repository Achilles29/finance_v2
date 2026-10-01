-- Final cleanup master item legacy setelah duplikasi recipe/formula dibersihkan.
--
-- Target:
-- - Nonaktifkan item legacy yang sudah tidak dipakai lagi di recipe/formula:
--   14  = AYAM PAHA PASAR
--   177 = GULA KITCHEN
--   221 = FRESH MILK UHT
-- - Rapikan nama item 189 agar tidak mengandung CRLF.
--
-- Canonical keep:
--   12  = AYAM UTUH
--   70  = GULA PASIR KITCHEN
--   194 = FRESH MILK
--   220 = AIR MINERAL GALON
--
-- Jalankan preview dulu. Jika hasilnya sesuai, lanjutkan blok UPDATE.

-- Preview status item target.
SELECT
    id,
    REPLACE(REPLACE(item_name, CHAR(13), '\\r'), CHAR(10), '\\n') AS item_name,
    material_id,
    is_active
FROM mst_item
WHERE id IN (14, 177, 189, 221)
ORDER BY id;

-- Pastikan item legacy sudah tidak dipakai lagi di recipe/formula.
SELECT
    'PRODUCT_RECIPE' AS src,
    material_item_id AS item_id,
    COUNT(*) AS row_count
FROM mst_product_recipe
WHERE line_type = 'MATERIAL'
  AND material_item_id IN (14, 177, 189, 221)
GROUP BY material_item_id

UNION ALL

SELECT
    'COMPONENT_FORMULA' AS src,
    material_item_id AS item_id,
    COUNT(*) AS row_count
FROM mst_component_formula
WHERE line_type = 'MATERIAL'
  AND material_item_id IN (14, 177, 189, 221)
GROUP BY material_item_id
ORDER BY src, item_id;

-- Jika query di atas kosong / tidak ada row, lanjutkan update berikut.
START TRANSACTION;

UPDATE mst_item
SET is_active = 0
WHERE id IN (14, 177, 221);

UPDATE mst_item
SET item_name = TRIM(REPLACE(REPLACE(item_name, CHAR(13), ''), CHAR(10), ''))
WHERE id = 189;

-- Verifikasi hasil akhir.
SELECT
    id,
    REPLACE(REPLACE(item_name, CHAR(13), '\\r'), CHAR(10), '\\n') AS item_name,
    material_id,
    is_active
FROM mst_item
WHERE id IN (14, 177, 189, 221)
ORDER BY id;

COMMIT;
