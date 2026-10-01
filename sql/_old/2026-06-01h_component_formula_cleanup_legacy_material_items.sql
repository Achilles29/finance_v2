-- Cleanup aman untuk item legacy di formula component:
-- 221 = FRESH MILK UHT   -> canonical keep: material 69 / item 194 (FRESH MILK)
-- 177 = GULA KITCHEN     -> canonical keep: material 76 / item 70  (GULA PASIR KITCHEN)
-- 14  = AYAM PAHA PASAR  -> canonical keep: material 9  / item 12  (AYAM UTUH)
--
-- Kriteria delete kandidat:
-- - row legacy berada di mst_component_formula
-- - line_type = MATERIAL
-- - ada pasangan row lain pada component yang sama
-- - resolved material_id sama
-- - qty sama
-- - pasangan row bukan item legacy (baris canonical keep)
--
-- Jalankan step preview dulu. Setelah hasilnya sesuai, lanjutkan step delete.

DROP TEMPORARY TABLE IF EXISTS tmp_component_formula_legacy_delete_candidates;

CREATE TEMPORARY TABLE tmp_component_formula_legacy_delete_candidates AS
SELECT
    f.id AS delete_row_id,
    MIN(k.id) AS keep_row_id,
    f.component_id,
    COALESCE(f.material_id, fi.material_id) AS resolved_material_id,
    f.material_item_id AS legacy_item_id,
    ROUND(COALESCE(f.qty, 0), 4) AS qty
FROM mst_component_formula f
JOIN mst_item fi
  ON fi.id = f.material_item_id
JOIN mst_component_formula k
  ON k.component_id = f.component_id
 AND k.line_type = 'MATERIAL'
 AND k.id <> f.id
JOIN mst_item ki
  ON ki.id = k.material_item_id
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id IN (221, 177, 14)
  AND k.material_item_id NOT IN (221, 177, 14)
  AND COALESCE(k.material_id, ki.material_id) = COALESCE(f.material_id, fi.material_id)
  AND ROUND(COALESCE(k.qty, 0), 4) = ROUND(COALESCE(f.qty, 0), 4)
GROUP BY
    f.id,
    f.component_id,
    COALESCE(f.material_id, fi.material_id),
    f.material_item_id,
    ROUND(COALESCE(f.qty, 0), 4);

-- Preview ringkas kandidat yang akan dihapus.
SELECT
    c.delete_row_id,
    c.keep_row_id,
    c.component_id,
    mc.component_name,
    c.resolved_material_id,
    mm.material_name,
    c.legacy_item_id,
    REPLACE(REPLACE(mi.item_name, CHAR(13), '\\r'), CHAR(10), '\\n') AS legacy_item_name,
    c.qty
FROM tmp_component_formula_legacy_delete_candidates c
JOIN mst_component mc ON mc.id = c.component_id
LEFT JOIN mst_material mm ON mm.id = c.resolved_material_id
LEFT JOIN mst_item mi ON mi.id = c.legacy_item_id
ORDER BY mi.id, mc.component_name, c.delete_row_id;

-- Summary per item legacy.
SELECT
    c.legacy_item_id,
    REPLACE(REPLACE(mi.item_name, CHAR(13), '\\r'), CHAR(10), '\\n') AS legacy_item_name,
    COUNT(*) AS candidate_rows
FROM tmp_component_formula_legacy_delete_candidates c
LEFT JOIN mst_item mi ON mi.id = c.legacy_item_id
GROUP BY c.legacy_item_id, mi.item_name
ORDER BY c.legacy_item_id;

-- Expected counts from audit:
-- 14  -> 2 row
-- 177 -> 46 row
-- 221 -> 3 row

-- Hapus baris kandidat jika hasil preview sudah sesuai.
START TRANSACTION;

DELETE f
FROM mst_component_formula f
JOIN tmp_component_formula_legacy_delete_candidates c
  ON c.delete_row_id = f.id;

-- Verifikasi sisa pemakaian item legacy di formula component.
SELECT
    f.material_item_id AS legacy_item_id,
    COUNT(*) AS remaining_formula_rows
FROM mst_component_formula f
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id IN (221, 177, 14)
GROUP BY f.material_item_id
ORDER BY f.material_item_id;

COMMIT;

-- Verifikasi global sisa pemakaian item legacy di resep/fomula setelah cleanup.
SELECT
    'PRODUCT_RECIPE' AS src,
    r.material_item_id AS item_id,
    COUNT(*) AS row_count
FROM mst_product_recipe r
WHERE r.line_type = 'MATERIAL'
  AND r.material_item_id IN (221, 177, 14)
GROUP BY r.material_item_id

UNION ALL

SELECT
    'COMPONENT_FORMULA' AS src,
    f.material_item_id AS item_id,
    COUNT(*) AS row_count
FROM mst_component_formula f
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id IN (221, 177, 14)
GROUP BY f.material_item_id
ORDER BY src, item_id;

-- Opsional: nonaktifkan item legacy hanya jika verifikasi global di atas sudah 0 semua.
-- UPDATE mst_item
-- SET is_active = 0
-- WHERE id IN (221, 177, 14);
