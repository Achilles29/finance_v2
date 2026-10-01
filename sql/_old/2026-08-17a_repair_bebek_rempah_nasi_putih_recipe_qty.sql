SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-17a_repair_bebek_rempah_nasi_putih_recipe_qty.sql
-- Tujuan :
-- 1) Menyamakan porsi NASI PUTIH pada resep BEBEK GORENG BUMBU
--    REMPAH dengan master extra TANPA NASI.
-- 2) Mencegah extra TANPA NASI gagal karena mencoba mengurangi
--    90 GR dari resep yang keliru tercatat hanya 1 GR.
--
-- Dasar koreksi:
-- - Extra TANPA NASI memakai component NASI PUTIH sebanyak 90 GR.
-- - Produk nasi lain pada database juga memakai porsi 90 GR.
-- - Script hanya menyentuh satu recipe line yang tepat.
-- ============================================================

START TRANSACTION;

UPDATE mst_product_recipe r
JOIN mst_product p ON p.id = r.product_id
JOIN mst_component c ON c.id = r.component_id
SET r.qty = 90.0000
WHERE p.product_code = 'PRD-DASH-00282'
  AND UPPER(TRIM(p.product_name)) = 'BEBEK GORENG BUMBU REMPAH'
  AND c.component_code = 'PREP-DASH-00018'
  AND UPPER(TRIM(c.component_name)) = 'NASI PUTIH'
  AND r.line_type = 'COMPONENT'
  AND ABS(COALESCE(r.qty, 0) - 1.0000) < 0.0001;

COMMIT;

SELECT
  p.product_code,
  p.product_name,
  c.component_code,
  c.component_name,
  r.qty AS recipe_qty,
  u.code AS uom_code,
  e.extra_code,
  e.extra_name,
  e.source_qty AS extra_removal_qty
FROM mst_product_recipe r
JOIN mst_product p ON p.id = r.product_id
JOIN mst_component c ON c.id = r.component_id
LEFT JOIN mst_uom u ON u.id = r.uom_id
LEFT JOIN mst_extra e
  ON e.extra_code = 'EXT-TN-001'
WHERE p.product_code = 'PRD-DASH-00282'
  AND c.component_code = 'PREP-DASH-00018';
