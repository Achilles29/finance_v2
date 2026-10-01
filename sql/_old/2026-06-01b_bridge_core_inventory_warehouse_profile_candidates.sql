SET NAMES utf8mb4;

-- ============================================================
-- Bangun kandidat bridge profile_key dari staging opening gudang core
-- ke mst_purchase_catalog Finance berdasarkan identity item/material
-- dan harga beli per profile.
-- Jalankan setelah 2026-06-01a_stage_core_inventory_warehouse_opening.sql
-- ============================================================

START TRANSACTION;

DROP TABLE IF EXISTS stg_core_inventory_warehouse_opening_profile_bridge;

CREATE TABLE stg_core_inventory_warehouse_opening_profile_bridge (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stage_id BIGINT UNSIGNED NOT NULL,
  snapshot_month DATE NOT NULL,
  source_profile_key CHAR(64) NULL,
  stage_profile_name VARCHAR(200) NULL,
  stage_profile_brand VARCHAR(120) NULL,
  stage_profile_description VARCHAR(255) NULL,
  stage_stock_domain ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  stage_item_id BIGINT UNSIGNED NULL,
  stage_material_id BIGINT UNSIGNED NULL,
  stage_buy_uom_id BIGINT UNSIGNED NULL,
  stage_content_uom_id BIGINT UNSIGNED NULL,
  stage_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  stage_unit_price_buy DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  stage_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  stage_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,

  finance_catalog_id BIGINT UNSIGNED NOT NULL,
  finance_profile_key CHAR(64) NOT NULL,
  finance_line_kind ENUM('ITEM','MATERIAL','SERVICE','ASSET') NOT NULL DEFAULT 'ITEM',
  finance_item_id BIGINT UNSIGNED NULL,
  finance_material_id BIGINT UNSIGNED NULL,
  finance_buy_uom_id BIGINT UNSIGNED NOT NULL,
  finance_content_uom_id BIGINT UNSIGNED NULL,
  finance_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  finance_catalog_name VARCHAR(150) NOT NULL,
  finance_brand_name VARCHAR(120) NULL,
  finance_line_description VARCHAR(255) NULL,
  finance_last_purchase_date DATE NULL,
  finance_last_unit_price DECIMAL(18,2) NULL,
  finance_unit_price_buy DECIMAL(18,2) NULL,

  is_name_exact TINYINT(1) NOT NULL DEFAULT 0,
  is_brand_exact TINYINT(1) NOT NULL DEFAULT 0,
  is_description_exact TINYINT(1) NOT NULL DEFAULT 0,
  is_text_exact TINYINT(1) NOT NULL DEFAULT 0,
  is_price_exact TINYINT(1) NOT NULL DEFAULT 0,
  is_exact_identity TINYINT(1) NOT NULL DEFAULT 0,
  match_score INT NOT NULL DEFAULT 0,
  score_detail VARCHAR(255) NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_stage_catalog (stage_id, finance_catalog_id),
  KEY idx_stage (stage_id),
  KEY idx_profile_key (finance_profile_key),
  KEY idx_score (match_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO stg_core_inventory_warehouse_opening_profile_bridge (
  stage_id,
  snapshot_month,
  source_profile_key,
  stage_profile_name,
  stage_profile_brand,
  stage_profile_description,
  stage_stock_domain,
  stage_item_id,
  stage_material_id,
  stage_buy_uom_id,
  stage_content_uom_id,
  stage_content_per_buy,
  stage_unit_price_buy,
  stage_qty_buy,
  stage_qty_content,
  finance_catalog_id,
  finance_profile_key,
  finance_line_kind,
  finance_item_id,
  finance_material_id,
  finance_buy_uom_id,
  finance_content_uom_id,
  finance_content_per_buy,
  finance_catalog_name,
  finance_brand_name,
  finance_line_description,
  finance_last_purchase_date,
  finance_last_unit_price,
  finance_unit_price_buy,
  is_name_exact,
  is_brand_exact,
  is_description_exact,
  is_text_exact,
  is_price_exact,
  is_exact_identity,
  match_score,
  score_detail
)
SELECT
  s.id AS stage_id,
  s.snapshot_month,
  s.source_profile_key,
  s.profile_name AS stage_profile_name,
  s.profile_brand AS stage_profile_brand,
  s.profile_description AS stage_profile_description,
  s.stock_domain AS stage_stock_domain,
  s.item_id AS stage_item_id,
  s.material_id AS stage_material_id,
  s.buy_uom_id AS stage_buy_uom_id,
  s.content_uom_id AS stage_content_uom_id,
  s.profile_content_per_buy AS stage_content_per_buy,
  ROUND(
    CASE
      WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
      ELSE 0
    END,
    2
  ) AS stage_unit_price_buy,
  s.opening_qty_buy AS stage_qty_buy,
  s.opening_qty_content AS stage_qty_content,

  c.id AS finance_catalog_id,
  c.profile_key AS finance_profile_key,
  c.line_kind AS finance_line_kind,
  c.item_id AS finance_item_id,
  c.material_id AS finance_material_id,
  c.buy_uom_id AS finance_buy_uom_id,
  c.content_uom_id AS finance_content_uom_id,
  c.content_per_buy AS finance_content_per_buy,
  c.catalog_name AS finance_catalog_name,
  c.brand_name AS finance_brand_name,
  c.line_description AS finance_line_description,
  c.last_purchase_date AS finance_last_purchase_date,
  c.last_unit_price AS finance_last_unit_price,
  ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) AS finance_unit_price_buy,

  CASE
    WHEN UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(s.profile_name, ''))) THEN 1
    ELSE 0
  END AS is_name_exact,
  CASE
    WHEN UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(s.profile_brand, ''))) THEN 1
    ELSE 0
  END AS is_brand_exact,
  CASE
    WHEN UPPER(TRIM(COALESCE(c.line_description, ''))) = UPPER(TRIM(COALESCE(s.profile_description, ''))) THEN 1
    ELSE 0
  END AS is_description_exact,
  CASE
    WHEN UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(s.profile_name, '')))
     AND UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(s.profile_brand, '')))
     AND UPPER(TRIM(COALESCE(c.line_description, ''))) = UPPER(TRIM(COALESCE(s.profile_description, '')))
      THEN 1
    ELSE 0
  END AS is_text_exact,
  CASE
    WHEN ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(
      CASE
        WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
        ELSE 0
      END,
      2
    ) THEN 1
    ELSE 0
  END AS is_price_exact,
  CASE
    WHEN UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(s.profile_name, '')))
     AND UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(s.profile_brand, '')))
     AND UPPER(TRIM(COALESCE(c.line_description, ''))) = UPPER(TRIM(COALESCE(s.profile_description, '')))
     AND ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(
       CASE
         WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
         ELSE 0
       END,
       2
     )
      THEN 1
    ELSE 0
  END AS is_exact_identity,
  100
    + CASE
        WHEN UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(s.profile_name, ''))) THEN 30
        ELSE 0
      END
    + CASE
        WHEN UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(s.profile_brand, ''))) THEN 20
        ELSE 0
      END
    + CASE
        WHEN UPPER(TRIM(COALESCE(c.line_description, ''))) = UPPER(TRIM(COALESCE(s.profile_description, ''))) THEN 10
        ELSE 0
      END
    + CASE
        WHEN ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(
          CASE
            WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
            ELSE 0
          END,
          2
        ) THEN 40
        ELSE 0
      END
    + CASE
        WHEN c.last_purchase_date IS NOT NULL THEN 1
        ELSE 0
      END AS match_score,
  CONCAT(
    'name=', CASE WHEN UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(s.profile_name, ''))) THEN 'Y' ELSE 'N' END,
    '; brand=', CASE WHEN UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(s.profile_brand, ''))) THEN 'Y' ELSE 'N' END,
    '; desc=', CASE WHEN UPPER(TRIM(COALESCE(c.line_description, ''))) = UPPER(TRIM(COALESCE(s.profile_description, ''))) THEN 'Y' ELSE 'N' END,
    '; price=', CASE WHEN ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(
      CASE
        WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
        ELSE 0
      END,
      2
    ) THEN 'Y' ELSE 'N' END,
    '; stage_price=', ROUND(
      CASE
        WHEN COALESCE(s.opening_qty_buy, 0) > 0 THEN COALESCE(s.opening_total_value, 0) / s.opening_qty_buy
        ELSE 0
      END,
      2
    ),
    '; finance_price=', ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2),
    '; last_buy=', COALESCE(DATE_FORMAT(c.last_purchase_date, '%Y-%m-%d'), '-')
  ) AS score_detail
FROM stg_core_inventory_warehouse_opening s
INNER JOIN mst_purchase_catalog c
  ON c.line_kind = s.source_line_kind
 AND COALESCE(c.is_active, 1) = 1
 AND COALESCE(c.item_id, 0) = COALESCE(s.item_id, 0)
 AND COALESCE(c.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(c.buy_uom_id, 0) = COALESCE(s.buy_uom_id, 0)
 AND COALESCE(c.content_uom_id, 0) = COALESCE(s.content_uom_id, 0)
 AND ABS(COALESCE(c.content_per_buy, 0) - COALESCE(s.profile_content_per_buy, 0)) < 0.000001
WHERE s.source_qty_on_hand > 0
  AND s.matched_finance_profile_key IS NULL;

DROP TABLE IF EXISTS stg_core_inventory_warehouse_opening_profile_bridge_ranked;

CREATE TABLE stg_core_inventory_warehouse_opening_profile_bridge_ranked AS
SELECT
  b.*,
  (
    SELECT COUNT(*)
    FROM stg_core_inventory_warehouse_opening_profile_bridge bx
    WHERE bx.stage_id = b.stage_id
  ) AS candidate_count,
  (
    SELECT COUNT(*)
    FROM stg_core_inventory_warehouse_opening_profile_bridge bx
    WHERE bx.stage_id = b.stage_id
      AND bx.is_text_exact = 1
  ) AS text_exact_candidate_count,
  (
    SELECT COUNT(*)
    FROM stg_core_inventory_warehouse_opening_profile_bridge bx
    WHERE bx.stage_id = b.stage_id
      AND bx.is_exact_identity = 1
  ) AS exact_identity_candidate_count,
  (
    SELECT COUNT(*) + 1
    FROM stg_core_inventory_warehouse_opening_profile_bridge bx
    WHERE bx.stage_id = b.stage_id
      AND (
        bx.is_exact_identity > b.is_exact_identity
        OR (
          bx.is_exact_identity = b.is_exact_identity
          AND bx.match_score > b.match_score
        )
        OR (
          bx.is_exact_identity = b.is_exact_identity
          AND bx.match_score = b.match_score
          AND COALESCE(bx.finance_last_purchase_date, '1000-01-01') > COALESCE(b.finance_last_purchase_date, '1000-01-01')
        )
        OR (
          bx.is_exact_identity = b.is_exact_identity
          AND bx.match_score = b.match_score
          AND COALESCE(bx.finance_last_purchase_date, '1000-01-01') = COALESCE(b.finance_last_purchase_date, '1000-01-01')
          AND bx.finance_catalog_id < b.finance_catalog_id
        )
      )
  ) AS candidate_rank,
  CASE
    WHEN (
      SELECT COUNT(*)
      FROM stg_core_inventory_warehouse_opening_profile_bridge bx
      WHERE bx.stage_id = b.stage_id
        AND bx.is_exact_identity = 1
    ) = 1
     AND b.is_exact_identity = 1
      THEN 'AUTO_MATCH_UNIQUE_TEXT'
    WHEN (
      SELECT COUNT(*)
      FROM stg_core_inventory_warehouse_opening_profile_bridge bx
      WHERE bx.stage_id = b.stage_id
        AND bx.is_exact_identity = 1
    ) > 1
     AND b.is_exact_identity = 1
      THEN 'AMBIGUOUS_TEXT_DUPLICATE'
    ELSE 'REVIEW_IDENTITY_ONLY'
  END AS candidate_status
FROM stg_core_inventory_warehouse_opening_profile_bridge b;

ALTER TABLE stg_core_inventory_warehouse_opening_profile_bridge_ranked
  ADD PRIMARY KEY (id),
  ADD KEY idx_stage_rank (stage_id, candidate_rank),
  ADD KEY idx_status (candidate_status),
  ADD KEY idx_rank (candidate_rank);

COMMIT;

SELECT
  COUNT(*) AS unresolved_positive_rows
FROM stg_core_inventory_warehouse_opening s
WHERE s.source_qty_on_hand > 0
  AND s.matched_finance_profile_key IS NULL;

SELECT
  CASE
    WHEN bridge_count = 0 THEN 'NO_CANDIDATE'
    WHEN best_status = 'AUTO_MATCH_UNIQUE_TEXT' THEN 'AUTO_MATCH_UNIQUE_TEXT'
    WHEN best_status = 'AMBIGUOUS_TEXT_DUPLICATE' THEN 'AMBIGUOUS_TEXT_DUPLICATE'
    ELSE 'REVIEW_IDENTITY_ONLY'
  END AS resolution_bucket,
  COUNT(*) AS stage_rows
FROM (
  SELECT
    s.id AS stage_id,
    COUNT(b.id) AS bridge_count,
    MAX(CASE WHEN r.candidate_rank = 1 THEN r.candidate_status END) AS best_status
  FROM stg_core_inventory_warehouse_opening s
  LEFT JOIN stg_core_inventory_warehouse_opening_profile_bridge b
    ON b.stage_id = s.id
  LEFT JOIN stg_core_inventory_warehouse_opening_profile_bridge_ranked r
    ON r.id = b.id
  WHERE s.source_qty_on_hand > 0
    AND s.matched_finance_profile_key IS NULL
  GROUP BY s.id
) summary
GROUP BY resolution_bucket
ORDER BY resolution_bucket;

SELECT
  r.stage_id,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  r.finance_profile_key,
  r.finance_catalog_id,
  r.finance_catalog_name,
  r.finance_brand_name,
  r.finance_line_description,
  r.finance_last_purchase_date,
  r.finance_last_unit_price,
  r.stage_unit_price_buy,
  r.finance_unit_price_buy,
  r.is_price_exact,
  r.is_exact_identity,
  r.match_score,
  r.candidate_count,
  r.text_exact_candidate_count,
  r.exact_identity_candidate_count,
  r.candidate_status,
  r.score_detail
FROM stg_core_inventory_warehouse_opening_profile_bridge_ranked r
INNER JOIN stg_core_inventory_warehouse_opening s
  ON s.id = r.stage_id
WHERE r.candidate_rank = 1
ORDER BY
  CASE r.candidate_status
    WHEN 'AUTO_MATCH_UNIQUE_TEXT' THEN 0
    WHEN 'AMBIGUOUS_TEXT_DUPLICATE' THEN 1
    ELSE 2
  END,
  s.profile_name ASC,
  s.profile_brand ASC,
  r.finance_catalog_id ASC;