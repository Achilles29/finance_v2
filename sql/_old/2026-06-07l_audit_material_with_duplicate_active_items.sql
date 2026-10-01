SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07l_audit_material_with_duplicate_active_items.sql
-- Tujuan :
-- 1) Mengaudit material yang masih punya lebih dari satu item aktif
-- 2) Menunjukkan kandidat item kanonik berbasis purchase catalog aktif
-- 3) Menjadi pagar operasional agar mismatch item-centric tidak lahir lagi
--
-- Catatan:
-- - Script ini TIDAK mengubah data
-- - Gunakan hasilnya untuk:
--   a) memilih item kanonik per material
--   b) menonaktifkan item sibling legacy bila aman
--   c) memastikan procurement/POS/ledger tidak lagi infer ke item yang salah
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_material_duplicate_active_items;
CREATE TEMPORARY TABLE tmp_material_duplicate_active_items AS
SELECT
  i.material_id,
  COUNT(*) AS active_item_count
FROM mst_item i
WHERE COALESCE(i.material_id, 0) > 0
  AND COALESCE(i.is_active, 1) = 1
GROUP BY i.material_id
HAVING COUNT(*) > 1;

ALTER TABLE tmp_material_duplicate_active_items
  ADD PRIMARY KEY (material_id);

-- ------------------------------------------------------------
-- A. Ringkasan material bermasalah
-- ------------------------------------------------------------
SELECT
  d.material_id,
  m.material_name AS material_name,
  d.active_item_count
FROM tmp_material_duplicate_active_items d
LEFT JOIN mst_material m ON m.id = d.material_id
ORDER BY d.active_item_count DESC, d.material_id;

-- ------------------------------------------------------------
-- B. Detail item aktif per material + kandidat item kanonik
-- ------------------------------------------------------------
SELECT
  i.material_id,
  m.material_name AS material_name,
  i.id AS item_id,
  i.item_code,
  i.item_name,
  i.buy_uom_id,
  i.content_uom_id,
  COALESCE(c.profile_key, '') AS catalog_profile_key,
  c.id AS catalog_id,
  COALESCE(c.is_active, 1) AS catalog_is_active,
  CASE
    WHEN c.item_id = i.id AND COALESCE(c.is_active, 1) = 1 THEN 'catalog_candidate'
    ELSE 'non_catalog_or_legacy'
  END AS canonical_hint
FROM mst_item i
JOIN tmp_material_duplicate_active_items d ON d.material_id = i.material_id
LEFT JOIN mst_material m ON m.id = i.material_id
LEFT JOIN mst_purchase_catalog c
  ON c.item_id = i.id
 AND c.material_id = i.material_id
ORDER BY
  i.material_id,
  CASE WHEN c.item_id = i.id AND COALESCE(c.is_active, 1) = 1 THEN 0 ELSE 1 END,
  COALESCE(c.is_active, 1) DESC,
  c.id DESC,
  i.id ASC;

-- ------------------------------------------------------------
-- C. Kandidat item kanonik per material
-- ------------------------------------------------------------
SELECT
  cnd.material_id,
  m.material_name AS material_name,
  cnd.item_id AS canonical_item_id,
  cnd.item_code AS canonical_item_code,
  cnd.item_name AS canonical_item_name,
  cnd.catalog_id,
  cnd.catalog_profile_key
FROM (
  SELECT
    i.material_id,
    i.id AS item_id,
    i.item_code,
    i.item_name,
    c.id AS catalog_id,
    COALESCE(c.profile_key, '') AS catalog_profile_key,
    CASE WHEN c.item_id = i.id AND COALESCE(c.is_active, 1) = 1 THEN 0 ELSE 1 END AS candidate_rank,
    COALESCE(c.is_active, 1) AS catalog_active_rank
  FROM mst_item i
  JOIN tmp_material_duplicate_active_items d ON d.material_id = i.material_id
  LEFT JOIN mst_purchase_catalog c
    ON c.item_id = i.id
   AND c.material_id = i.material_id
  WHERE COALESCE(i.is_active, 1) = 1
) cnd
JOIN (
  SELECT
    x.material_id,
    MIN(CONCAT(
      LPAD(CAST(x.candidate_rank AS CHAR), 2, '0'), '|',
      LPAD(CAST((999999 - x.catalog_active_rank) AS CHAR), 6, '0'), '|',
      LPAD(CAST((999999 - COALESCE(x.catalog_id, 0)) AS CHAR), 6, '0'), '|',
      LPAD(CAST(x.item_id AS CHAR), 10, '0')
    )) AS keep_key
  FROM (
    SELECT
      i.material_id,
      i.id AS item_id,
      c.id AS catalog_id,
      CASE WHEN c.item_id = i.id AND COALESCE(c.is_active, 1) = 1 THEN 0 ELSE 1 END AS candidate_rank,
      COALESCE(c.is_active, 1) AS catalog_active_rank
    FROM mst_item i
    JOIN tmp_material_duplicate_active_items d ON d.material_id = i.material_id
    LEFT JOIN mst_purchase_catalog c
      ON c.item_id = i.id
     AND c.material_id = i.material_id
    WHERE COALESCE(i.is_active, 1) = 1
  ) x
  GROUP BY x.material_id
) pick
  ON pick.material_id = cnd.material_id
 AND pick.keep_key = CONCAT(
   LPAD(CAST(cnd.candidate_rank AS CHAR), 2, '0'), '|',
   LPAD(CAST((999999 - cnd.catalog_active_rank) AS CHAR), 6, '0'), '|',
   LPAD(CAST((999999 - COALESCE(cnd.catalog_id, 0)) AS CHAR), 6, '0'), '|',
   LPAD(CAST(cnd.item_id AS CHAR), 10, '0')
 )
LEFT JOIN mst_material m ON m.id = cnd.material_id
ORDER BY cnd.material_id;

-- ------------------------------------------------------------
-- D. Summary total
-- ------------------------------------------------------------
SELECT 'duplicate_materials_with_multiple_active_items' AS metric, COUNT(*) AS total
FROM tmp_material_duplicate_active_items

UNION ALL

SELECT 'active_items_inside_duplicate_materials', COUNT(*)
FROM mst_item i
JOIN tmp_material_duplicate_active_items d ON d.material_id = i.material_id
WHERE COALESCE(i.is_active, 1) = 1;
