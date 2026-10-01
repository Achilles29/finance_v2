SET NAMES utf8mb4;

-- ============================================================
-- Terapkan bridge kandidat unik dari hasil script 2026-06-01b
-- lalu import hanya row AUTO_MATCH_UNIQUE_TEXT ke opening gudang.
-- Aman untuk rerun karena target punya unique key profile per bulan.
-- ============================================================

START TRANSACTION;

UPDATE stg_core_inventory_warehouse_opening s
INNER JOIN stg_core_inventory_warehouse_opening_profile_bridge_ranked r
  ON r.stage_id = s.id
 AND r.candidate_rank = 1
 AND r.candidate_status = 'AUTO_MATCH_UNIQUE_TEXT'
SET
  s.matched_finance_profile_key = r.finance_profile_key,
  s.matched_profile_source = 'BRIDGE_UNIQUE_TEXT',
  s.mapping_status = 'READY_BRIDGED',
  s.mapping_notes = CONCAT(
    'Bridge unique text ke mst_purchase_catalog id=',
    r.finance_catalog_id,
    ' profile_key=',
    r.finance_profile_key,
    '. ',
    COALESCE(r.score_detail, '')
  ),
  s.import_status = 'READY_TO_IMPORT',
  s.import_notes = CONCAT(
    'Siap import via bridge unique text. finance_catalog_id=',
    r.finance_catalog_id,
    '; finance_profile_key=',
    r.finance_profile_key
  )
WHERE s.source_qty_on_hand > 0
  AND (s.matched_finance_profile_key IS NULL OR s.matched_profile_source = 'BRIDGE_UNIQUE_TEXT')
  AND s.import_status IN ('SKIPPED_NO_KEY_MATCH', 'READY_TO_IMPORT', 'IMPORTED_BRIDGED');

DROP TEMPORARY TABLE IF EXISTS tmp_bridge_opening_grouped;

CREATE TEMPORARY TABLE tmp_bridge_opening_grouped AS
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
WHERE s.mapping_status = 'READY_BRIDGED'
  AND s.import_status = 'READY_TO_IMPORT'
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
INNER JOIN tmp_bridge_opening_grouped g
  ON o.snapshot_month = g.snapshot_month
 AND o.stock_domain = g.stock_domain
 AND o.item_id <=> g.item_id
 AND o.material_id <=> g.material_id
 AND o.buy_uom_id <=> g.buy_uom_id
 AND o.content_uom_id <=> g.content_uom_id
 AND o.profile_key = g.matched_finance_profile_key
WHERE o.notes LIKE 'Import bridge unique_text core warehouse balance.%';

UPDATE inv_warehouse_stock_opening_snapshot o
INNER JOIN tmp_bridge_opening_grouped g
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
    'Import bridge unique_text core warehouse balance. source_profile_key=',
    COALESCE(g.source_profile_keys, '-'),
    '; bridge_profile_key=',
    COALESCE(g.matched_finance_profile_key, '-'),
    '; source=BRIDGE_UNIQUE_TEXT',
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
    'Import bridge unique_text core warehouse balance. source_profile_key=',
    COALESCE(g.source_profile_keys, '-'),
    '; bridge_profile_key=',
    COALESCE(g.matched_finance_profile_key, '-'),
    '; source=BRIDGE_UNIQUE_TEXT',
    '; stage_rows=',
    g.stage_row_count
  ) AS notes,
  NULL AS created_by
FROM tmp_bridge_opening_grouped g
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
 AND COALESCE(o.item_id, 0) = COALESCE(s.item_id, 0)
 AND COALESCE(o.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(o.buy_uom_id, 0) = COALESCE(s.buy_uom_id, 0)
 AND COALESCE(o.content_uom_id, 0) = COALESCE(s.content_uom_id, 0)
 AND o.profile_key = s.matched_finance_profile_key
SET
  s.import_status = 'IMPORTED_BRIDGED',
  s.import_notes = CONCAT('Opening gudang terinput via bridge unique text ke inv_warehouse_stock_opening_snapshot id=', o.id),
  s.imported_opening_id = o.id
WHERE s.mapping_status = 'READY_BRIDGED'
  AND s.import_status = 'READY_TO_IMPORT'
  AND s.matched_finance_profile_key IS NOT NULL
  AND s.opening_qty_buy > 0
  AND s.opening_qty_content > 0;

COMMIT;

SELECT
  mapping_status,
  COUNT(*) AS total_rows,
  ROUND(SUM(opening_qty_buy), 4) AS total_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS total_qty_content,
  ROUND(SUM(opening_total_value), 2) AS total_value
FROM stg_core_inventory_warehouse_opening
GROUP BY mapping_status
ORDER BY mapping_status;

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
  COUNT(*) AS imported_bridged_rows,
  ROUND(SUM(opening_qty_buy), 4) AS imported_bridged_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS imported_bridged_qty_content,
  ROUND(SUM(opening_total_value), 2) AS imported_bridged_total_value
FROM stg_core_inventory_warehouse_opening
WHERE import_status = 'IMPORTED_BRIDGED';

SELECT
  s.id,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  s.opening_qty_content,
  s.matched_finance_profile_key,
  s.matched_profile_source,
  s.mapping_status,
  s.import_status,
  s.imported_opening_id,
  s.mapping_notes,
  s.import_notes
FROM stg_core_inventory_warehouse_opening s
WHERE s.import_status = 'IMPORTED_BRIDGED'
ORDER BY s.profile_name ASC, s.profile_brand ASC, s.id ASC;