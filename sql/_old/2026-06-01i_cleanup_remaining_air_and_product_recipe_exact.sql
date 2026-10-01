-- Cleanup tahap 2 untuk sisa duplikasi setelah script 2026-06-01h dijalankan.
--
-- Target:
-- 1. Formula component AIR: hapus row item legacy/non-canonical `189` dan pertahankan `220`
--    jika pada component yang sama ada pasangan row `220` dengan qty yang sama.
-- 2. Exact duplicate resep produk: hapus row duplikat exact dan pertahankan row id terkecil.
--
-- Catatan:
-- - Item 189 (`AIR MINERAL GALON` + CRLF) saat audit sudah `is_active = 0`.
-- - Item 220 (`AIR MINERAL GALON`) adalah canonical keep.
-- - Jalankan semua query preview dulu. Jika hasilnya sesuai, lanjutkan blok DELETE.

DROP TEMPORARY TABLE IF EXISTS tmp_formula_air_delete_candidates;
DROP TEMPORARY TABLE IF EXISTS tmp_product_recipe_exact_delete_candidates;

CREATE TEMPORARY TABLE tmp_formula_air_delete_candidates AS
SELECT
    f.id AS delete_row_id,
    MIN(k.id) AS keep_row_id,
    f.component_id,
    ROUND(COALESCE(f.qty, 0), 4) AS qty
FROM mst_component_formula f
JOIN mst_component_formula k
  ON k.component_id = f.component_id
 AND k.line_type = 'MATERIAL'
 AND k.id <> f.id
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id = 189
  AND k.material_item_id = 220
  AND ROUND(COALESCE(k.qty, 0), 4) = ROUND(COALESCE(f.qty, 0), 4)
GROUP BY
    f.id,
    f.component_id,
    ROUND(COALESCE(f.qty, 0), 4);

CREATE TEMPORARY TABLE tmp_product_recipe_exact_delete_candidates AS
SELECT
    r.id AS delete_row_id,
    g.keep_row_id,
    r.product_id,
    r.material_item_id,
    ROUND(COALESCE(r.qty, 0), 4) AS qty,
    COALESCE(r.source_division_id, 0) AS source_division_id,
    COALESCE(r.ingredient_role, 'MAIN') AS ingredient_role
FROM mst_product_recipe r
JOIN (
    SELECT
        product_id,
        material_item_id,
        ROUND(COALESCE(qty, 0), 4) AS qty,
        COALESCE(source_division_id, 0) AS source_division_id,
        COALESCE(ingredient_role, 'MAIN') AS ingredient_role,
        MIN(id) AS keep_row_id,
        COUNT(*) AS row_count
    FROM mst_product_recipe
    WHERE line_type = 'MATERIAL'
    GROUP BY
        product_id,
        material_item_id,
        ROUND(COALESCE(qty, 0), 4),
        COALESCE(source_division_id, 0),
        COALESCE(ingredient_role, 'MAIN')
    HAVING COUNT(*) > 1
) g
  ON g.product_id = r.product_id
 AND g.material_item_id = r.material_item_id
 AND g.qty = ROUND(COALESCE(r.qty, 0), 4)
 AND g.source_division_id = COALESCE(r.source_division_id, 0)
 AND g.ingredient_role = COALESCE(r.ingredient_role, 'MAIN')
WHERE r.id <> g.keep_row_id;

-- Preview kandidat delete formula AIR.
SELECT
    c.delete_row_id,
    c.keep_row_id,
    c.component_id,
    mc.component_name,
    189 AS delete_item_id,
    220 AS keep_item_id,
    c.qty
FROM tmp_formula_air_delete_candidates c
JOIN mst_component mc ON mc.id = c.component_id
ORDER BY mc.component_name, c.delete_row_id;

-- Preview kandidat delete exact duplicate resep produk.
SELECT
    c.delete_row_id,
    c.keep_row_id,
    c.product_id,
    p.product_name,
    c.material_item_id,
    mi.item_name,
    c.qty,
    c.source_division_id,
    c.ingredient_role
FROM tmp_product_recipe_exact_delete_candidates c
JOIN mst_product p ON p.id = c.product_id
LEFT JOIN mst_item mi ON mi.id = c.material_item_id
ORDER BY p.product_name, c.delete_row_id;

-- Summary preview.
SELECT 'formula_air_delete_candidates' AS bucket, COUNT(*) AS row_count
FROM tmp_formula_air_delete_candidates

UNION ALL

SELECT 'product_recipe_exact_delete_candidates' AS bucket, COUNT(*) AS row_count
FROM tmp_product_recipe_exact_delete_candidates;

-- Expected count saat ini:
-- formula_air_delete_candidates           -> 9 row
-- product_recipe_exact_delete_candidates  -> 1 row

-- Hapus kandidat jika hasil preview sudah sesuai.
START TRANSACTION;

DELETE f
FROM mst_component_formula f
JOIN tmp_formula_air_delete_candidates c
  ON c.delete_row_id = f.id;

DELETE r
FROM mst_product_recipe r
JOIN tmp_product_recipe_exact_delete_candidates c
  ON c.delete_row_id = r.id;

-- Verifikasi sisa pemakaian item AIR 189/220 di formula component.
SELECT
    f.material_item_id,
    COUNT(*) AS row_count
FROM mst_component_formula f
WHERE f.line_type = 'MATERIAL'
  AND f.material_item_id IN (189, 220)
GROUP BY f.material_item_id
ORDER BY f.material_item_id;

-- Verifikasi sisa duplikasi global.
SELECT 'product_duplicate_material_groups' AS audit_key, COUNT(*) AS affected_groups
FROM (
    SELECT r.product_id
    FROM mst_product_recipe r
    JOIN mst_item mi ON mi.id = r.material_item_id
    WHERE r.line_type = 'MATERIAL'
    GROUP BY r.product_id, mi.material_id, COALESCE(r.source_division_id, 0), COALESCE(r.ingredient_role, 'MAIN')
    HAVING COUNT(*) > 1
) x

UNION ALL

SELECT 'component_duplicate_material_groups' AS audit_key, COUNT(*) AS affected_groups
FROM (
    SELECT f.component_id
    FROM mst_component_formula f
    LEFT JOIN mst_item mi ON mi.id = f.material_item_id
    WHERE f.line_type = 'MATERIAL'
      AND COALESCE(f.material_id, mi.material_id) IS NOT NULL
    GROUP BY f.component_id, COALESCE(f.material_id, mi.material_id)
    HAVING COUNT(*) > 1
) x;

COMMIT;

-- Opsional setelah cleanup berhasil dan verifikasi global = 0:
-- rapikan nama item 189 agar tidak mengandung CRLF walau item sudah inactive.
-- UPDATE mst_item
-- SET item_name = TRIM(REPLACE(REPLACE(item_name, CHAR(13), ''), CHAR(10), ''))
-- WHERE id = 189;
