SET NAMES utf8mb4;

-- ============================================================
-- Tampilkan unresolved row, buat profile katalog baru untuk row
-- yang belum punya kandidat exact (`NO_CANDIDATE`) atau hanya bentrok
-- brand/text parsial (`REVIEW_IDENTITY_ONLY`), lalu import opening-nya.
-- Kasus `AMBIGUOUS_TEXT_DUPLICATE` hanya ditampilkan, tidak dibuat.
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_unresolved_new_profile_candidates;

CREATE TEMPORARY TABLE tmp_unresolved_new_profile_candidates AS
SELECT
  s.id AS stage_id,
  CASE
    WHEN r.candidate_status = 'REVIEW_IDENTITY_ONLY' THEN 'REVIEW_IDENTITY_ONLY'
    ELSE 'NO_CANDIDATE'
  END AS create_reason,
  SHA2(CONCAT_WS('|',
    UPPER(TRIM(COALESCE(s.stock_domain, 'ITEM'))),
    COALESCE(s.item_id, 0),
    COALESCE(s.material_id, 0),
    COALESCE(s.buy_uom_id, 0),
    COALESCE(s.content_uom_id, 0),
    UPPER(TRIM(COALESCE(NULLIF(s.profile_name, ''), NULLIF(s.source_item_name, ''), NULLIF(s.source_material_name, ''), CONCAT('OPENING PROFILE ', COALESCE(s.item_id, 0))))),
    UPPER(TRIM(COALESCE(s.profile_brand, ''))),
    UPPER(TRIM(COALESCE(s.profile_description, ''))),
    FORMAT(COALESCE(s.profile_content_per_buy, 1), 6),
    FORMAT(COALESCE(s.source_avg_cost_per_buy, 0), 2)
  ), 256) AS generated_profile_key,
  CASE WHEN COALESCE(s.material_id, 0) > 0 THEN 'MATERIAL' ELSE 'ITEM' END AS line_kind,
  s.stock_domain,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(NULLIF(s.profile_name, ''), NULLIF(s.source_item_name, ''), NULLIF(s.source_material_name, ''), CONCAT('OPENING PROFILE ', COALESCE(s.item_id, 0))) AS catalog_name,
  NULLIF(s.profile_brand, '') AS brand_name,
  NULLIF(s.profile_description, '') AS line_description,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS content_per_buy,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 8) AS conversion_factor_to_content,
  ROUND(COALESCE(s.source_avg_cost_per_buy, 0), 2) AS unit_price_buy,
  s.source_profile_key,
  s.source_item_code,
  s.source_material_code,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  s.opening_qty_content,
  s.opening_total_value
FROM stg_core_inventory_warehouse_opening s
LEFT JOIN stg_core_inventory_warehouse_opening_profile_bridge_ranked r
  ON r.stage_id = s.id
 AND r.candidate_rank = 1
WHERE s.import_status = 'SKIPPED_NO_KEY_MATCH'
  AND s.source_qty_on_hand > 0
  AND (
    r.id IS NULL
    OR r.candidate_status = 'REVIEW_IDENTITY_ONLY'
  );

INSERT INTO mst_purchase_catalog (
  profile_key,
  line_kind,
  item_id,
  material_id,
  catalog_name,
  brand_name,
  line_description,
  buy_uom_id,
  content_uom_id,
  content_per_buy,
  conversion_factor_to_content,
  standard_price,
  last_unit_price,
  notes,
  is_active
)
SELECT
  c.generated_profile_key,
  c.line_kind,
  c.item_id,
  c.material_id,
  c.catalog_name,
  c.brand_name,
  c.line_description,
  c.buy_uom_id,
  c.content_uom_id,
  c.content_per_buy,
  c.conversion_factor_to_content,
  c.unit_price_buy,
  c.unit_price_buy,
  CONCAT('Auto-created from core warehouse opening: ', c.create_reason),
  1
FROM (
  SELECT
    generated_profile_key,
    MAX(line_kind) AS line_kind,
    MAX(item_id) AS item_id,
    MAX(material_id) AS material_id,
    MAX(catalog_name) AS catalog_name,
    MAX(brand_name) AS brand_name,
    MAX(line_description) AS line_description,
    MAX(buy_uom_id) AS buy_uom_id,
    MAX(content_uom_id) AS content_uom_id,
    MAX(content_per_buy) AS content_per_buy,
    MAX(conversion_factor_to_content) AS conversion_factor_to_content,
    MAX(unit_price_buy) AS unit_price_buy,
    MIN(create_reason) AS create_reason
  FROM tmp_unresolved_new_profile_candidates
  GROUP BY generated_profile_key
) c
LEFT JOIN mst_purchase_catalog existing
  ON existing.profile_key = c.generated_profile_key
WHERE existing.id IS NULL;

UPDATE stg_core_inventory_warehouse_opening s
INNER JOIN tmp_unresolved_new_profile_candidates c
  ON c.stage_id = s.id
SET
  s.matched_finance_profile_key = c.generated_profile_key,
  s.matched_profile_source = 'AUTO_CREATE_CATALOG',
  s.mapping_status = 'READY_NEW_CATALOG',
  s.mapping_notes = CONCAT(
    'Profile katalog baru dibuat dari unresolved opening. reason=',
    c.create_reason,
    '; generated_profile_key=',
    c.generated_profile_key
  ),
  s.import_status = 'READY_TO_IMPORT',
  s.import_notes = CONCAT(
    'Siap import via auto-create catalog. reason=',
    c.create_reason,
    '; generated_profile_key=',
    c.generated_profile_key
  )
WHERE s.import_status IN ('SKIPPED_NO_KEY_MATCH', 'READY_TO_IMPORT', 'IMPORTED_NEW_CATALOG');

DROP TEMPORARY TABLE IF EXISTS tmp_new_catalog_opening_grouped;

CREATE TEMPORARY TABLE tmp_new_catalog_opening_grouped AS
SELECT
  s.snapshot_month,
  s.stock_domain,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.matched_finance_profile_key,
  MAX(s.profile_name) AS profile_name,
  MAX(s.profile_brand) AS profile_brand,
  MAX(s.profile_description) AS profile_description,
  MAX(s.profile_expired_date) AS profile_expired_date,
  MAX(s.profile_content_per_buy) AS profile_content_per_buy,
  MAX(s.profile_buy_uom_code) AS profile_buy_uom_code,
  MAX(s.profile_content_uom_code) AS profile_content_uom_code,
  ROUND(SUM(s.opening_qty_buy), 4) AS opening_qty_buy,
  ROUND(SUM(s.opening_qty_content), 4) AS opening_qty_content,
  ROUND(
    CASE
      WHEN SUM(s.opening_qty_content) > 0 THEN SUM(s.opening_total_value) / SUM(s.opening_qty_content)
      ELSE 0
    END,
    6
  ) AS opening_avg_cost_per_content,
  ROUND(SUM(s.opening_total_value), 2) AS opening_total_value,
  COUNT(*) AS stage_row_count,
  GROUP_CONCAT(COALESCE(s.source_profile_key, '-') ORDER BY s.id SEPARATOR ',') AS source_profile_keys
FROM stg_core_inventory_warehouse_opening s
WHERE s.mapping_status = 'READY_NEW_CATALOG'
  AND s.import_status IN ('READY_TO_IMPORT', 'IMPORTED_NEW_CATALOG')
  AND s.matched_finance_profile_key IS NOT NULL
  AND s.opening_qty_buy > 0
  AND s.opening_qty_content > 0
GROUP BY
  s.snapshot_month,
  s.stock_domain,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.matched_finance_profile_key;

DELETE o
FROM inv_warehouse_stock_opening_snapshot o
INNER JOIN tmp_new_catalog_opening_grouped g
  ON o.snapshot_month = g.snapshot_month
 AND o.stock_domain = g.stock_domain
 AND o.item_id <=> g.item_id
 AND o.material_id <=> g.material_id
 AND o.buy_uom_id <=> g.buy_uom_id
 AND o.content_uom_id <=> g.content_uom_id
 AND o.profile_key = g.matched_finance_profile_key
WHERE o.notes LIKE 'Import auto_create_catalog core warehouse balance.%';

UPDATE inv_warehouse_stock_opening_snapshot o
INNER JOIN tmp_new_catalog_opening_grouped g
  ON o.snapshot_month = g.snapshot_month
 AND o.stock_domain = g.stock_domain
 AND o.item_id <=> g.item_id
 AND o.material_id <=> g.material_id
 AND o.buy_uom_id <=> g.buy_uom_id
 AND o.content_uom_id <=> g.content_uom_id
 AND o.profile_key = g.matched_finance_profile_key
SET
  o.profile_name = g.profile_name,
  o.profile_brand = g.profile_brand,
  o.profile_description = g.profile_description,
  o.profile_expired_date = g.profile_expired_date,
  o.profile_content_per_buy = g.profile_content_per_buy,
  o.profile_buy_uom_code = g.profile_buy_uom_code,
  o.profile_content_uom_code = g.profile_content_uom_code,
  o.opening_qty_buy = g.opening_qty_buy,
  o.opening_qty_content = g.opening_qty_content,
  o.opening_avg_cost_per_content = g.opening_avg_cost_per_content,
  o.opening_total_value = g.opening_total_value,
  o.source_type = 'MANUAL',
  o.notes = CONCAT(
    'Import auto_create_catalog core warehouse balance. source_profile_key=',
    COALESCE(g.source_profile_keys, '-'),
    '; generated_profile_key=',
    COALESCE(g.matched_finance_profile_key, '-'),
    '; stage_rows=',
    g.stage_row_count
  ),
  o.updated_at = CURRENT_TIMESTAMP;

INSERT INTO inv_warehouse_stock_opening_snapshot (
  snapshot_month,
  stock_domain,
  item_id,
  material_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  profile_name,
  profile_brand,
  profile_description,
  profile_expired_date,
  profile_content_per_buy,
  profile_buy_uom_code,
  profile_content_uom_code,
  opening_qty_buy,
  opening_qty_content,
  opening_avg_cost_per_content,
  opening_total_value,
  source_type,
  notes,
  created_by
)
SELECT
  g.snapshot_month,
  g.stock_domain,
  g.item_id,
  g.material_id,
  g.buy_uom_id,
  g.content_uom_id,
  g.matched_finance_profile_key,
  g.profile_name,
  g.profile_brand,
  g.profile_description,
  g.profile_expired_date,
  g.profile_content_per_buy,
  g.profile_buy_uom_code,
  g.profile_content_uom_code,
  g.opening_qty_buy,
  g.opening_qty_content,
  g.opening_avg_cost_per_content,
  g.opening_total_value,
  'MANUAL' AS source_type,
  CONCAT(
    'Import auto_create_catalog core warehouse balance. source_profile_key=',
    COALESCE(g.source_profile_keys, '-'),
    '; generated_profile_key=',
    COALESCE(g.matched_finance_profile_key, '-'),
    '; stage_rows=',
    g.stage_row_count
  ) AS notes,
  NULL AS created_by
FROM tmp_new_catalog_opening_grouped g
LEFT JOIN inv_warehouse_stock_opening_snapshot o
  ON o.snapshot_month = g.snapshot_month
 AND o.stock_domain = g.stock_domain
 AND o.item_id <=> g.item_id
 AND o.material_id <=> g.material_id
 AND o.buy_uom_id <=> g.buy_uom_id
 AND o.content_uom_id <=> g.content_uom_id
 AND o.profile_key = g.matched_finance_profile_key
WHERE o.id IS NULL;

UPDATE stg_core_inventory_warehouse_opening s
INNER JOIN inv_warehouse_stock_opening_snapshot o
  ON o.snapshot_month = s.snapshot_month
 AND o.stock_domain = s.stock_domain
 AND o.item_id <=> s.item_id
 AND o.material_id <=> s.material_id
 AND o.buy_uom_id <=> s.buy_uom_id
 AND o.content_uom_id <=> s.content_uom_id
 AND o.profile_key = s.matched_finance_profile_key
SET
  s.import_status = 'IMPORTED_NEW_CATALOG',
  s.import_notes = CONCAT('Opening gudang terinput via auto-create catalog ke inv_warehouse_stock_opening_snapshot id=', o.id),
  s.imported_opening_id = o.id
WHERE s.mapping_status = 'READY_NEW_CATALOG'
  AND s.import_status IN ('READY_TO_IMPORT', 'IMPORTED_NEW_CATALOG')
  AND s.matched_finance_profile_key IS NOT NULL
  AND s.opening_qty_buy > 0
  AND s.opening_qty_content > 0;

COMMIT;

SELECT
  'AMBIGUOUS_TEXT_DUPLICATE' AS issue_type,
  s.id AS stage_id,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  s.opening_qty_content,
  r.candidate_count,
  r.finance_catalog_id AS sample_catalog_id,
  r.finance_profile_key AS sample_profile_key,
  r.score_detail
FROM stg_core_inventory_warehouse_opening_profile_bridge_ranked r
INNER JOIN stg_core_inventory_warehouse_opening s
  ON s.id = r.stage_id
WHERE r.candidate_rank = 1
  AND r.candidate_status = 'AMBIGUOUS_TEXT_DUPLICATE'
ORDER BY s.profile_name, s.profile_brand, s.id;

SELECT
  create_reason,
  COUNT(*) AS stage_rows,
  COUNT(DISTINCT generated_profile_key) AS created_profile_keys,
  ROUND(SUM(opening_qty_buy), 4) AS total_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS total_qty_content,
  ROUND(SUM(opening_total_value), 2) AS total_value
FROM tmp_unresolved_new_profile_candidates
GROUP BY create_reason
ORDER BY create_reason;

SELECT
  COUNT(*) AS imported_new_catalog_stage_rows,
  COUNT(DISTINCT matched_finance_profile_key) AS distinct_generated_profile_keys,
  ROUND(SUM(opening_qty_buy), 4) AS total_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS total_qty_content,
  ROUND(SUM(opening_total_value), 2) AS total_value
FROM stg_core_inventory_warehouse_opening
WHERE import_status = 'IMPORTED_NEW_CATALOG';

SELECT
  import_status,
  COUNT(*) AS total_rows,
  ROUND(SUM(opening_qty_buy), 4) AS total_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS total_qty_content,
  ROUND(SUM(opening_total_value), 2) AS total_value
FROM stg_core_inventory_warehouse_opening
GROUP BY import_status
ORDER BY import_status;

SELECT
  s.id AS stage_id,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  s.opening_qty_content,
  s.opening_total_value,
  s.source_item_code,
  s.source_material_code,
  s.mapping_status,
  s.import_status,
  s.matched_finance_profile_key,
  s.imported_opening_id,
  s.mapping_notes,
  s.import_notes
FROM stg_core_inventory_warehouse_opening s
WHERE s.import_status = 'SKIPPED_NO_KEY_MATCH'
ORDER BY s.profile_name, s.profile_brand, s.id;
