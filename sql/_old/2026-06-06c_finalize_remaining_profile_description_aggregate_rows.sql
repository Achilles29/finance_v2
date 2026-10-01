SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06c_finalize_remaining_profile_description_aggregate_rows.sql
-- Tujuan :
-- 1) Menuntaskan sisa row agregat stok yang masih memakai description kotor
-- 2) Membuat canonical catalog profile bila row agregat belum punya pasangan catalog aktif yang bersih
-- 3) Menyamakan balance / daily / monthly ke canonical profile_key final
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair canonical profile description 2026-06-06';

DROP TEMPORARY TABLE IF EXISTS tmp_remaining_dirty_sources;
CREATE TEMPORARY TABLE tmp_remaining_dirty_sources (
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  old_profile_key CHAR(64) NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  PRIMARY KEY (source_table, source_id),
  KEY idx_tmp_remaining_old_key (old_profile_key)
) ENGINE=InnoDB;

INSERT INTO tmp_remaining_dirty_sources
SELECT
  'inv_division_stock_balance', id, profile_key, item_id, material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_division_stock_balance
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

INSERT IGNORE INTO tmp_remaining_dirty_sources
SELECT
  'inv_warehouse_stock_balance', id, profile_key, item_id, NULL AS material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_warehouse_stock_balance
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

INSERT IGNORE INTO tmp_remaining_dirty_sources
SELECT
  'inv_division_daily_rollup', id, profile_key, item_id, material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_division_daily_rollup
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

INSERT IGNORE INTO tmp_remaining_dirty_sources
SELECT
  'inv_warehouse_daily_rollup', id, profile_key, item_id, material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_warehouse_daily_rollup
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

INSERT IGNORE INTO tmp_remaining_dirty_sources
SELECT
  'inv_division_monthly_stock', id, profile_key, item_id, material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_division_monthly_stock
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

INSERT IGNORE INTO tmp_remaining_dirty_sources
SELECT
  'inv_warehouse_monthly_stock', id, profile_key, item_id, material_id, buy_uom_id, content_uom_id,
  ROUND(COALESCE(profile_content_per_buy, 1), 6),
  ROUND(COALESCE(avg_cost_per_content, 0) * COALESCE(profile_content_per_buy, 1), 2),
  profile_name, profile_brand
FROM inv_warehouse_monthly_stock
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

DROP TEMPORARY TABLE IF EXISTS tmp_remaining_key_map;
CREATE TEMPORARY TABLE tmp_remaining_key_map AS
SELECT
  s.old_profile_key,
  MAX(s.item_id) AS item_id,
  MAX(s.material_id) AS material_id,
  MAX(s.buy_uom_id) AS buy_uom_id,
  MAX(s.content_uom_id) AS content_uom_id,
  ROUND(MAX(s.profile_content_per_buy), 6) AS profile_content_per_buy,
  ROUND(MAX(s.unit_price), 2) AS unit_price,
  MAX(s.profile_name) AS profile_name,
  MAX(s.profile_brand) AS profile_brand,
  COALESCE(
    (
      SELECT c.profile_key
      FROM mst_purchase_catalog c
      WHERE c.profile_key = s.old_profile_key
        AND COALESCE(c.is_active, 1) = 1
        AND UPPER(TRIM(COALESCE(c.line_description, ''))) = ''
      LIMIT 1
    ),
    (
      SELECT c.profile_key
      FROM mst_purchase_catalog c
      WHERE UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(MAX(s.profile_name), '')))
        AND UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(MAX(s.profile_brand), '')))
        AND UPPER(TRIM(COALESCE(c.line_description, ''))) = ''
        AND COALESCE(c.item_id, 0) = COALESCE(MAX(s.item_id), 0)
        AND COALESCE(c.material_id, 0) = COALESCE(MAX(s.material_id), 0)
        AND COALESCE(c.buy_uom_id, 0) = COALESCE(MAX(s.buy_uom_id), 0)
        AND COALESCE(c.content_uom_id, 0) = COALESCE(MAX(s.content_uom_id), 0)
        AND ROUND(COALESCE(c.content_per_buy, 1), 6) = ROUND(MAX(s.profile_content_per_buy), 6)
        AND ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(MAX(s.unit_price), 2)
        AND COALESCE(c.is_active, 1) = 1
      ORDER BY c.id ASC
      LIMIT 1
    ),
    LOWER(SHA2(CONCAT_WS('|',
      'CANONICAL_PURCHASE_PROFILE',
      CASE WHEN MAX(s.material_id) IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
      COALESCE(MAX(s.item_id), 0),
      COALESCE(MAX(s.material_id), 0),
      COALESCE(MAX(s.buy_uom_id), 0),
      COALESCE(MAX(s.content_uom_id), 0),
      ROUND(MAX(s.profile_content_per_buy), 6),
      ROUND(MAX(s.unit_price), 2),
      UPPER(TRIM(COALESCE(MAX(s.profile_name), ''))),
      UPPER(TRIM(COALESCE(MAX(s.profile_brand), ''))),
      ''
    ), 256))
  ) AS canonical_profile_key
FROM tmp_remaining_dirty_sources s
GROUP BY s.old_profile_key;

ALTER TABLE tmp_remaining_key_map
  ADD PRIMARY KEY (old_profile_key),
  ADD KEY idx_tmp_remaining_canonical (canonical_profile_key);

INSERT INTO mst_purchase_catalog (
  profile_key, line_kind, item_id, material_id, catalog_name, brand_name, line_description,
  buy_uom_id, content_uom_id, content_per_buy, conversion_factor_to_content,
  standard_price, last_unit_price, notes, is_active
)
SELECT
  m.canonical_profile_key,
  CASE WHEN m.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
  m.item_id,
  m.material_id,
  COALESCE(NULLIF(TRIM(m.profile_name), ''), CONCAT('CANONICAL PROFILE ', LEFT(m.canonical_profile_key, 8))),
  NULLIF(TRIM(m.profile_brand), ''),
  NULL,
  m.buy_uom_id,
  m.content_uom_id,
  m.profile_content_per_buy,
  m.profile_content_per_buy,
  m.unit_price,
  m.unit_price,
  CONCAT(@repair_tag, ' | inserted canonical row from aggregate profile ', LEFT(m.old_profile_key, 16)),
  1
FROM tmp_remaining_key_map m
LEFT JOIN mst_purchase_catalog c ON c.profile_key = m.canonical_profile_key
WHERE c.id IS NULL;

UPDATE inv_division_stock_balance s
JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
SET s.profile_key = m.canonical_profile_key,
    s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_warehouse_stock_balance s
JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
SET s.profile_key = m.canonical_profile_key,
    s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_division_daily_rollup s
JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
SET s.profile_key = m.canonical_profile_key,
    s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_warehouse_daily_rollup s
JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
SET s.profile_key = m.canonical_profile_key,
    s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_div_monthly_final_rows;
CREATE TEMPORARY TABLE tmp_div_monthly_final_rows AS
SELECT
  s.*,
  COALESCE(m.canonical_profile_key, s.profile_key) AS target_profile_key
FROM inv_division_monthly_stock s
LEFT JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
WHERE s.profile_key IN (SELECT old_profile_key FROM tmp_remaining_key_map)
   OR s.profile_key IN (SELECT canonical_profile_key FROM tmp_remaining_key_map);

DROP TEMPORARY TABLE IF EXISTS tmp_div_monthly_final_merge;
CREATE TEMPORARY TABLE tmp_div_monthly_final_merge AS
SELECT
  MIN(id) AS keep_id,
  month_key,
  division_id,
  destination_type,
  MAX(stock_domain) AS stock_domain,
  target_profile_key AS identity_key,
  MAX(item_id) AS item_id,
  MAX(material_id) AS material_id,
  MAX(buy_uom_id) AS buy_uom_id,
  MAX(content_uom_id) AS content_uom_id,
  target_profile_key AS profile_key,
  MAX(profile_name) AS profile_name,
  MAX(profile_brand) AS profile_brand,
  NULL AS profile_description,
  MAX(profile_expired_date) AS profile_expired_date,
  MAX(profile_content_per_buy) AS profile_content_per_buy,
  MAX(profile_buy_uom_code) AS profile_buy_uom_code,
  MAX(profile_content_uom_code) AS profile_content_uom_code,
  ROUND(SUM(opening_qty_buy), 4) AS opening_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS opening_qty_content,
  ROUND(SUM(opening_total_value), 2) AS opening_total_value,
  ROUND(SUM(in_qty_buy), 4) AS in_qty_buy,
  ROUND(SUM(in_qty_content), 4) AS in_qty_content,
  ROUND(SUM(in_total_value), 2) AS in_total_value,
  ROUND(SUM(out_qty_buy), 4) AS out_qty_buy,
  ROUND(SUM(out_qty_content), 4) AS out_qty_content,
  ROUND(SUM(out_total_value), 2) AS out_total_value,
  ROUND(SUM(discarded_qty_buy), 4) AS discarded_qty_buy,
  ROUND(SUM(discarded_qty_content), 4) AS discarded_qty_content,
  ROUND(SUM(discarded_total_value), 2) AS discarded_total_value,
  ROUND(SUM(spoil_qty_buy), 4) AS spoil_qty_buy,
  ROUND(SUM(spoil_qty_content), 4) AS spoil_qty_content,
  ROUND(SUM(spoilage_total_value), 2) AS spoilage_total_value,
  ROUND(SUM(waste_qty_buy), 4) AS waste_qty_buy,
  ROUND(SUM(waste_qty_content), 4) AS waste_qty_content,
  ROUND(SUM(waste_total_value), 2) AS waste_total_value,
  ROUND(SUM(process_loss_qty_buy), 4) AS process_loss_qty_buy,
  ROUND(SUM(process_loss_qty_content), 4) AS process_loss_qty_content,
  ROUND(SUM(process_loss_total_value), 2) AS process_loss_total_value,
  ROUND(SUM(variance_qty_buy), 4) AS variance_qty_buy,
  ROUND(SUM(variance_qty_content), 4) AS variance_qty_content,
  ROUND(SUM(variance_total_value), 2) AS variance_total_value,
  ROUND(SUM(adjustment_plus_qty_buy), 4) AS adjustment_plus_qty_buy,
  ROUND(SUM(adjustment_plus_qty_content), 4) AS adjustment_plus_qty_content,
  ROUND(SUM(adjustment_plus_total_value), 2) AS adjustment_plus_total_value,
  ROUND(SUM(adjustment_minus_qty_buy), 4) AS adjustment_minus_qty_buy,
  ROUND(SUM(adjustment_minus_qty_content), 4) AS adjustment_minus_qty_content,
  ROUND(SUM(adjustment_minus_total_value), 2) AS adjustment_minus_total_value,
  ROUND(SUM(closing_qty_buy), 4) AS closing_qty_buy,
  ROUND(SUM(closing_qty_content), 4) AS closing_qty_content,
  ROUND(
    CASE
      WHEN ABS(SUM(closing_qty_content)) > 0.0001
        THEN SUM(total_value) / SUM(closing_qty_content)
      ELSE MAX(avg_cost_per_content)
    END,
    6
  ) AS avg_cost_per_content,
  ROUND(SUM(total_value), 2) AS total_value,
  SUM(movement_day_count) AS movement_day_count,
  SUM(mutation_count) AS mutation_count,
  MAX(last_movement_date) AS last_movement_date,
  MAX(last_movement_at) AS last_movement_at,
  SUBSTRING_INDEX(GROUP_CONCAT(last_movement_table ORDER BY last_movement_at DESC, id DESC SEPARATOR ','), ',', 1) AS last_movement_table,
  MAX(last_movement_id) AS last_movement_id,
  CASE WHEN SUM(CASE WHEN source_mode = 'LIVE' THEN 1 ELSE 0 END) > 0 THEN 'LIVE' ELSE 'REBUILD' END AS source_mode,
  LEFT(TRIM(BOTH ' |' FROM GROUP_CONCAT(DISTINCT NULLIF(notes, '') ORDER BY id SEPARATOR ' | ')), 255) AS notes
FROM tmp_div_monthly_final_rows
GROUP BY month_key, division_id, destination_type, target_profile_key;

DELETE s
FROM inv_division_monthly_stock s
WHERE s.profile_key IN (SELECT old_profile_key FROM tmp_remaining_key_map)
   OR s.profile_key IN (SELECT canonical_profile_key FROM tmp_remaining_key_map);

INSERT INTO inv_division_monthly_stock (
  id, month_key, division_id, destination_type, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, last_movement_id, source_mode, notes
)
SELECT
  keep_id, month_key, division_id, destination_type, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, last_movement_id, source_mode, notes
FROM tmp_div_monthly_final_merge;

DROP TEMPORARY TABLE IF EXISTS tmp_wh_monthly_final_rows;
CREATE TEMPORARY TABLE tmp_wh_monthly_final_rows AS
SELECT
  s.*,
  COALESCE(m.canonical_profile_key, s.profile_key) AS target_profile_key
FROM inv_warehouse_monthly_stock s
LEFT JOIN tmp_remaining_key_map m ON m.old_profile_key = s.profile_key
WHERE s.profile_key IN (SELECT old_profile_key FROM tmp_remaining_key_map)
   OR s.profile_key IN (SELECT canonical_profile_key FROM tmp_remaining_key_map);

DROP TEMPORARY TABLE IF EXISTS tmp_wh_monthly_final_merge;
CREATE TEMPORARY TABLE tmp_wh_monthly_final_merge AS
SELECT
  MIN(id) AS keep_id,
  month_key,
  MAX(stock_domain) AS stock_domain,
  target_profile_key AS identity_key,
  MAX(item_id) AS item_id,
  MAX(material_id) AS material_id,
  MAX(buy_uom_id) AS buy_uom_id,
  MAX(content_uom_id) AS content_uom_id,
  target_profile_key AS profile_key,
  MAX(profile_name) AS profile_name,
  MAX(profile_brand) AS profile_brand,
  NULL AS profile_description,
  MAX(profile_expired_date) AS profile_expired_date,
  MAX(profile_content_per_buy) AS profile_content_per_buy,
  MAX(profile_buy_uom_code) AS profile_buy_uom_code,
  MAX(profile_content_uom_code) AS profile_content_uom_code,
  ROUND(SUM(opening_qty_buy), 4) AS opening_qty_buy,
  ROUND(SUM(opening_qty_content), 4) AS opening_qty_content,
  ROUND(SUM(opening_total_value), 2) AS opening_total_value,
  ROUND(SUM(in_qty_buy), 4) AS in_qty_buy,
  ROUND(SUM(in_qty_content), 4) AS in_qty_content,
  ROUND(SUM(in_total_value), 2) AS in_total_value,
  ROUND(SUM(out_qty_buy), 4) AS out_qty_buy,
  ROUND(SUM(out_qty_content), 4) AS out_qty_content,
  ROUND(SUM(out_total_value), 2) AS out_total_value,
  ROUND(SUM(discarded_qty_buy), 4) AS discarded_qty_buy,
  ROUND(SUM(discarded_qty_content), 4) AS discarded_qty_content,
  ROUND(SUM(discarded_total_value), 2) AS discarded_total_value,
  ROUND(SUM(spoil_qty_buy), 4) AS spoil_qty_buy,
  ROUND(SUM(spoil_qty_content), 4) AS spoil_qty_content,
  ROUND(SUM(spoilage_total_value), 2) AS spoilage_total_value,
  ROUND(SUM(waste_qty_buy), 4) AS waste_qty_buy,
  ROUND(SUM(waste_qty_content), 4) AS waste_qty_content,
  ROUND(SUM(waste_total_value), 2) AS waste_total_value,
  ROUND(SUM(process_loss_qty_buy), 4) AS process_loss_qty_buy,
  ROUND(SUM(process_loss_qty_content), 4) AS process_loss_qty_content,
  ROUND(SUM(process_loss_total_value), 2) AS process_loss_total_value,
  ROUND(SUM(variance_qty_buy), 4) AS variance_qty_buy,
  ROUND(SUM(variance_qty_content), 4) AS variance_qty_content,
  ROUND(SUM(variance_total_value), 2) AS variance_total_value,
  ROUND(SUM(adjustment_plus_qty_buy), 4) AS adjustment_plus_qty_buy,
  ROUND(SUM(adjustment_plus_qty_content), 4) AS adjustment_plus_qty_content,
  ROUND(SUM(adjustment_plus_total_value), 2) AS adjustment_plus_total_value,
  ROUND(SUM(adjustment_minus_qty_buy), 4) AS adjustment_minus_qty_buy,
  ROUND(SUM(adjustment_minus_qty_content), 4) AS adjustment_minus_qty_content,
  ROUND(SUM(adjustment_minus_total_value), 2) AS adjustment_minus_total_value,
  ROUND(SUM(closing_qty_buy), 4) AS closing_qty_buy,
  ROUND(SUM(closing_qty_content), 4) AS closing_qty_content,
  ROUND(
    CASE
      WHEN ABS(SUM(closing_qty_content)) > 0.0001
        THEN SUM(total_value) / SUM(closing_qty_content)
      ELSE MAX(avg_cost_per_content)
    END,
    6
  ) AS avg_cost_per_content,
  ROUND(SUM(total_value), 2) AS total_value,
  SUM(movement_day_count) AS movement_day_count,
  SUM(mutation_count) AS mutation_count,
  MAX(last_movement_date) AS last_movement_date,
  MAX(last_movement_at) AS last_movement_at,
  SUBSTRING_INDEX(GROUP_CONCAT(last_movement_table ORDER BY last_movement_at DESC, id DESC SEPARATOR ','), ',', 1) AS last_movement_table,
  MAX(last_movement_id) AS last_movement_id,
  CASE WHEN SUM(CASE WHEN source_mode = 'LIVE' THEN 1 ELSE 0 END) > 0 THEN 'LIVE' ELSE 'REBUILD' END AS source_mode,
  LEFT(TRIM(BOTH ' |' FROM GROUP_CONCAT(DISTINCT NULLIF(notes, '') ORDER BY id SEPARATOR ' | ')), 255) AS notes
FROM tmp_wh_monthly_final_rows
GROUP BY month_key, target_profile_key;

DELETE s
FROM inv_warehouse_monthly_stock s
WHERE s.profile_key IN (SELECT old_profile_key FROM tmp_remaining_key_map)
   OR s.profile_key IN (SELECT canonical_profile_key FROM tmp_remaining_key_map);

INSERT INTO inv_warehouse_monthly_stock (
  id, month_key, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, last_movement_id, source_mode, notes
)
SELECT
  keep_id, month_key, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, last_movement_id, source_mode, notes
FROM tmp_wh_monthly_final_merge;

COMMIT;

SELECT 'tmp_remaining_key_map' AS metric, COUNT(*) AS total_rows FROM tmp_remaining_key_map
UNION ALL
SELECT 'dirty_div_balance_remaining', COUNT(*) FROM inv_division_stock_balance
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_wh_balance_remaining', COUNT(*) FROM inv_warehouse_stock_balance
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_div_daily_remaining', COUNT(*) FROM inv_division_daily_rollup
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_wh_daily_remaining', COUNT(*) FROM inv_warehouse_daily_rollup
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_div_monthly_remaining', COUNT(*) FROM inv_division_monthly_stock
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_wh_monthly_remaining', COUNT(*) FROM inv_warehouse_monthly_stock
WHERE UPPER(COALESCE(profile_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';
