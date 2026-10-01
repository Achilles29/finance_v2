SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06d_purchase_catalog_activate_latest_and_disable_zero_duplicates.sql
-- Tujuan :
-- 1) Untuk catalog purchase yang identitasnya sama persis + harga sama, aktifkan hanya row terbaru
-- 2) Jika dalam identitas catalog yang sama ada row harga 0 dan ada row harga non-0,
--    maka row harga 0 dinonaktifkan
-- 3) Menjaga agar catalog aktif tidak pecah hanya karena duplikat profile historis
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Purchase catalog latest-active + zero-price cleanup 2026-06-06';

DROP TEMPORARY TABLE IF EXISTS tmp_purchase_catalog_exact_dupes;
CREATE TEMPORARY TABLE tmp_purchase_catalog_exact_dupes AS
SELECT
  c.id,
  COALESCE(c.line_kind, '') AS line_kind,
  COALESCE(c.item_id, 0) AS item_id,
  COALESCE(c.material_id, 0) AS material_id,
  UPPER(TRIM(COALESCE(c.catalog_name, ''))) AS catalog_name,
  UPPER(TRIM(COALESCE(c.brand_name, ''))) AS brand_name,
  UPPER(TRIM(COALESCE(c.line_description, ''))) AS line_description,
  COALESCE(c.buy_uom_id, 0) AS buy_uom_id,
  COALESCE(c.content_uom_id, 0) AS content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6) AS content_per_buy,
  ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) AS effective_price,
  COALESCE(c.updated_at, c.created_at, '1970-01-01 00:00:00') AS sort_ts,
  COALESCE(c.is_active, 1) AS is_active
FROM mst_purchase_catalog c
JOIN (
  SELECT
    COALESCE(line_kind, '') AS line_kind,
    COALESCE(item_id, 0) AS item_id,
    COALESCE(material_id, 0) AS material_id,
    UPPER(TRIM(COALESCE(catalog_name, ''))) AS catalog_name,
    UPPER(TRIM(COALESCE(brand_name, ''))) AS brand_name,
    UPPER(TRIM(COALESCE(line_description, ''))) AS line_description,
    COALESCE(buy_uom_id, 0) AS buy_uom_id,
    COALESCE(content_uom_id, 0) AS content_uom_id,
    ROUND(COALESCE(content_per_buy, 1), 6) AS content_per_buy,
    ROUND(COALESCE(last_unit_price, standard_price, 0), 2) AS effective_price,
    COUNT(*) AS row_count
  FROM mst_purchase_catalog
  GROUP BY
    COALESCE(line_kind, ''),
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    UPPER(TRIM(COALESCE(catalog_name, ''))),
    UPPER(TRIM(COALESCE(brand_name, ''))),
    UPPER(TRIM(COALESCE(line_description, ''))),
    COALESCE(buy_uom_id, 0),
    COALESCE(content_uom_id, 0),
    ROUND(COALESCE(content_per_buy, 1), 6),
    ROUND(COALESCE(last_unit_price, standard_price, 0), 2)
  HAVING COUNT(*) > 1
) g
  ON g.line_kind = COALESCE(c.line_kind, '')
 AND g.item_id = COALESCE(c.item_id, 0)
 AND g.material_id = COALESCE(c.material_id, 0)
 AND g.catalog_name = UPPER(TRIM(COALESCE(c.catalog_name, '')))
 AND g.brand_name = UPPER(TRIM(COALESCE(c.brand_name, '')))
 AND g.line_description = UPPER(TRIM(COALESCE(c.line_description, '')))
 AND g.buy_uom_id = COALESCE(c.buy_uom_id, 0)
 AND g.content_uom_id = COALESCE(c.content_uom_id, 0)
 AND g.content_per_buy = ROUND(COALESCE(c.content_per_buy, 1), 6)
 AND g.effective_price = ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2);

ALTER TABLE tmp_purchase_catalog_exact_dupes
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_purchase_catalog_exact_identity (
    line_kind, item_id, material_id, catalog_name, brand_name, line_description,
    buy_uom_id, content_uom_id, content_per_buy, effective_price, sort_ts
  );

DROP TEMPORARY TABLE IF EXISTS tmp_purchase_catalog_exact_keep;
CREATE TEMPORARY TABLE tmp_purchase_catalog_exact_keep AS
SELECT
  d.id,
  CASE
    WHEN NOT EXISTS (
      SELECT 1
      FROM tmp_purchase_catalog_exact_dupes x
      WHERE x.line_kind = d.line_kind
        AND x.item_id = d.item_id
        AND x.material_id = d.material_id
        AND x.catalog_name = d.catalog_name
        AND x.brand_name = d.brand_name
        AND x.line_description = d.line_description
        AND x.buy_uom_id = d.buy_uom_id
        AND x.content_uom_id = d.content_uom_id
        AND x.content_per_buy = d.content_per_buy
        AND x.effective_price = d.effective_price
        AND (
          x.sort_ts > d.sort_ts
          OR (x.sort_ts = d.sort_ts AND x.id > d.id)
        )
    ) THEN 1 ELSE 0
  END AS should_be_active
FROM tmp_purchase_catalog_exact_dupes d;

ALTER TABLE tmp_purchase_catalog_exact_keep ADD PRIMARY KEY (id);

UPDATE mst_purchase_catalog c
JOIN tmp_purchase_catalog_exact_keep k ON k.id = c.id
SET c.is_active = k.should_be_active,
    c.updated_at = CURRENT_TIMESTAMP,
    c.notes = LEFT(TRIM(CONCAT(
      COALESCE(c.notes, ''),
      CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      CASE WHEN k.should_be_active = 1 THEN ' | latest exact duplicate kept active' ELSE ' | older exact duplicate set inactive' END
    )), 255)
WHERE COALESCE(c.is_active, 1) <> k.should_be_active;

DROP TEMPORARY TABLE IF EXISTS tmp_purchase_catalog_zero_mixed_groups;
CREATE TEMPORARY TABLE tmp_purchase_catalog_zero_mixed_groups AS
SELECT
  COALESCE(line_kind, '') AS line_kind,
  COALESCE(item_id, 0) AS item_id,
  COALESCE(material_id, 0) AS material_id,
  UPPER(TRIM(COALESCE(catalog_name, ''))) AS catalog_name,
  UPPER(TRIM(COALESCE(brand_name, ''))) AS brand_name,
  UPPER(TRIM(COALESCE(line_description, ''))) AS line_description,
  COALESCE(buy_uom_id, 0) AS buy_uom_id,
  COALESCE(content_uom_id, 0) AS content_uom_id,
  ROUND(COALESCE(content_per_buy, 1), 6) AS content_per_buy
FROM mst_purchase_catalog
GROUP BY
  COALESCE(line_kind, ''),
  COALESCE(item_id, 0),
  COALESCE(material_id, 0),
  UPPER(TRIM(COALESCE(catalog_name, ''))),
  UPPER(TRIM(COALESCE(brand_name, ''))),
  UPPER(TRIM(COALESCE(line_description, ''))),
  COALESCE(buy_uom_id, 0),
  COALESCE(content_uom_id, 0),
  ROUND(COALESCE(content_per_buy, 1), 6)
HAVING
  SUM(CASE WHEN ROUND(COALESCE(last_unit_price, standard_price, 0), 2) = 0 THEN 1 ELSE 0 END) > 0
  AND SUM(CASE WHEN ROUND(COALESCE(last_unit_price, standard_price, 0), 2) > 0 THEN 1 ELSE 0 END) > 0;

ALTER TABLE tmp_purchase_catalog_zero_mixed_groups
  ADD KEY idx_tmp_purchase_catalog_zero_mixed (
    line_kind, item_id, material_id, catalog_name, brand_name, line_description,
    buy_uom_id, content_uom_id, content_per_buy
  );

UPDATE mst_purchase_catalog c
JOIN tmp_purchase_catalog_zero_mixed_groups g
  ON g.line_kind = COALESCE(c.line_kind, '')
 AND g.item_id = COALESCE(c.item_id, 0)
 AND g.material_id = COALESCE(c.material_id, 0)
 AND g.catalog_name = UPPER(TRIM(COALESCE(c.catalog_name, '')))
 AND g.brand_name = UPPER(TRIM(COALESCE(c.brand_name, '')))
 AND g.line_description = UPPER(TRIM(COALESCE(c.line_description, '')))
 AND g.buy_uom_id = COALESCE(c.buy_uom_id, 0)
 AND g.content_uom_id = COALESCE(c.content_uom_id, 0)
 AND g.content_per_buy = ROUND(COALESCE(c.content_per_buy, 1), 6)
SET c.is_active = 0,
    c.updated_at = CURRENT_TIMESTAMP,
    c.notes = LEFT(TRIM(CONCAT(
      COALESCE(c.notes, ''),
      CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | zero-price profile set inactive because non-zero profile exists'
    )), 255)
WHERE ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = 0
  AND COALESCE(c.is_active, 1) <> 0;

COMMIT;

SELECT 'active_exact_duplicate_groups_remaining' AS metric, COUNT(*) AS total_rows
FROM (
  SELECT 1
  FROM mst_purchase_catalog
  WHERE COALESCE(is_active, 1) = 1
  GROUP BY
    COALESCE(line_kind, ''),
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    UPPER(TRIM(COALESCE(catalog_name, ''))),
    UPPER(TRIM(COALESCE(brand_name, ''))),
    UPPER(TRIM(COALESCE(line_description, ''))),
    COALESCE(buy_uom_id, 0),
    COALESCE(content_uom_id, 0),
    ROUND(COALESCE(content_per_buy, 1), 6),
    ROUND(COALESCE(last_unit_price, standard_price, 0), 2)
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT 'active_zero_rows_in_mixed_groups', COUNT(*)
FROM mst_purchase_catalog c
JOIN tmp_purchase_catalog_zero_mixed_groups g
  ON g.line_kind = COALESCE(c.line_kind, '')
 AND g.item_id = COALESCE(c.item_id, 0)
 AND g.material_id = COALESCE(c.material_id, 0)
 AND g.catalog_name = UPPER(TRIM(COALESCE(c.catalog_name, '')))
 AND g.brand_name = UPPER(TRIM(COALESCE(c.brand_name, '')))
 AND g.line_description = UPPER(TRIM(COALESCE(c.line_description, '')))
 AND g.buy_uom_id = COALESCE(c.buy_uom_id, 0)
 AND g.content_uom_id = COALESCE(c.content_uom_id, 0)
 AND g.content_per_buy = ROUND(COALESCE(c.content_per_buy, 1), 6)
WHERE ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = 0
  AND COALESCE(c.is_active, 1) = 1
UNION ALL
SELECT 'exact_duplicate_groups_total', COUNT(*)
FROM (
  SELECT 1
  FROM mst_purchase_catalog
  GROUP BY
    COALESCE(line_kind, ''),
    COALESCE(item_id, 0),
    COALESCE(material_id, 0),
    UPPER(TRIM(COALESCE(catalog_name, ''))),
    UPPER(TRIM(COALESCE(brand_name, ''))),
    UPPER(TRIM(COALESCE(line_description, ''))),
    COALESCE(buy_uom_id, 0),
    COALESCE(content_uom_id, 0),
    ROUND(COALESCE(content_per_buy, 1), 6),
    ROUND(COALESCE(last_unit_price, standard_price, 0), 2)
  HAVING COUNT(*) > 1
) x;
