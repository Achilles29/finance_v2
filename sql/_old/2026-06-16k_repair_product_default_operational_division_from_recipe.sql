SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-16k_repair_product_default_operational_division_from_recipe.sql
-- Tujuan :
-- 1) Menyamakan default_operational_division_id produk dengan
--    source_division recipe jika recipe punya 1 divisi yang jelas
-- 2) Fallback ke pemetaan rumpun produk BEVERAGE->BAR dan
--    FOOD->KITCHEN bila recipe belum memberi anchor
-- 3) Menutup akar kasus POS stock commit yang jatuh ke
--    destination OTHER walau recipe sudah jelas BAR/KITCHEN
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_product_recipe_unique_division;
CREATE TEMPORARY TABLE tmp_product_recipe_unique_division AS
SELECT
  r.product_id,
  MIN(r.source_division_id) AS target_division_id,
  COUNT(DISTINCT r.source_division_id) AS recipe_division_count
FROM mst_product_recipe r
WHERE r.source_division_id IS NOT NULL
GROUP BY r.product_id
HAVING COUNT(DISTINCT r.source_division_id) = 1;

ALTER TABLE tmp_product_recipe_unique_division
  ADD PRIMARY KEY (product_id);

DROP TEMPORARY TABLE IF EXISTS tmp_product_division_fallback_target;
CREATE TEMPORARY TABLE tmp_product_division_fallback_target AS
SELECT
  p.id AS product_id,
  CASE
    WHEN UPPER(TRIM(COALESCE(pd.name, pd.code, ''))) = 'BEVERAGE' THEN (
      SELECT od.id FROM mst_operational_division od WHERE UPPER(TRIM(od.code)) = 'BAR' LIMIT 1
    )
    WHEN UPPER(TRIM(COALESCE(pd.name, pd.code, ''))) = 'FOOD' THEN (
      SELECT od.id FROM mst_operational_division od WHERE UPPER(TRIM(od.code)) = 'KITCHEN' LIMIT 1
    )
    ELSE NULL
  END AS target_division_id
FROM mst_product p
LEFT JOIN mst_product_division pd ON pd.id = p.product_division_id;

ALTER TABLE tmp_product_division_fallback_target
  ADD PRIMARY KEY (product_id);

DROP TEMPORARY TABLE IF EXISTS tmp_product_default_operational_division_targets;
CREATE TEMPORARY TABLE tmp_product_default_operational_division_targets AS
SELECT
  p.id AS product_id,
  p.product_code,
  p.product_name,
  p.default_operational_division_id AS before_division_id,
  COALESCE(r.target_division_id, f.target_division_id) AS target_division_id,
  CASE
    WHEN r.product_id IS NOT NULL THEN 'RECIPE_UNIQUE_DIVISION'
    WHEN f.target_division_id IS NOT NULL THEN 'PRODUCT_DIVISION_FALLBACK'
    ELSE 'NO_TARGET'
  END AS repair_source
FROM mst_product p
LEFT JOIN tmp_product_recipe_unique_division r ON r.product_id = p.id
LEFT JOIN tmp_product_division_fallback_target f ON f.product_id = p.id
WHERE COALESCE(r.target_division_id, f.target_division_id, 0) > 0
  AND COALESCE(p.default_operational_division_id, 0) <> COALESCE(r.target_division_id, f.target_division_id, 0);

ALTER TABLE tmp_product_default_operational_division_targets
  ADD PRIMARY KEY (product_id),
  ADD KEY idx_tmp_product_default_op_target (target_division_id, repair_source);

DROP TEMPORARY TABLE IF EXISTS tmp_product_default_operational_division_backup;
CREATE TEMPORARY TABLE tmp_product_default_operational_division_backup AS
SELECT p.*
FROM mst_product p
JOIN tmp_product_default_operational_division_targets t ON t.product_id = p.id;

UPDATE mst_product p
JOIN tmp_product_default_operational_division_targets t ON t.product_id = p.id
SET
  p.default_operational_division_id = t.target_division_id,
  p.updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ------------------------------------------------------------
-- A. Ringkasan update
-- ------------------------------------------------------------
SELECT
  repair_source,
  COUNT(*) AS total_products
FROM tmp_product_default_operational_division_targets
GROUP BY repair_source
ORDER BY total_products DESC, repair_source;

-- ------------------------------------------------------------
-- B. Detail produk yang berubah
-- ------------------------------------------------------------
SELECT
  t.product_id,
  t.product_code,
  t.product_name,
  t.before_division_id,
  oldd.code AS before_division_code,
  oldd.name AS before_division_name,
  t.target_division_id,
  newd.code AS target_division_code,
  newd.name AS target_division_name,
  t.repair_source
FROM tmp_product_default_operational_division_targets t
LEFT JOIN mst_operational_division oldd ON oldd.id = t.before_division_id
LEFT JOIN mst_operational_division newd ON newd.id = t.target_division_id
ORDER BY t.repair_source, t.product_id;

-- ------------------------------------------------------------
-- C. Sisa produk yang recipe-nya masih lintas lebih dari 1 divisi
-- ------------------------------------------------------------
SELECT
  p.id AS product_id,
  p.product_code,
  p.product_name,
  p.default_operational_division_id,
  od.code AS default_division_code,
  rc.recipe_division_count,
  rc.recipe_division_codes
FROM mst_product p
JOIN (
  SELECT
    r.product_id,
    COUNT(DISTINCT r.source_division_id) AS recipe_division_count,
    GROUP_CONCAT(DISTINCT d.code ORDER BY d.code SEPARATOR ',') AS recipe_division_codes
  FROM mst_product_recipe r
  LEFT JOIN mst_operational_division d ON d.id = r.source_division_id
  WHERE r.source_division_id IS NOT NULL
  GROUP BY r.product_id
  HAVING COUNT(DISTINCT r.source_division_id) > 1
) rc ON rc.product_id = p.id
LEFT JOIN mst_operational_division od ON od.id = p.default_operational_division_id
ORDER BY p.id;
