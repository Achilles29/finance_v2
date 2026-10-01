SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06a_repair_purchase_profile_description_global.sql
-- Tujuan :
-- 1) Membersihkan line/profile description yang terlanjur diisi catatan asal dokumen
-- 2) Menyatukan profile_key kotor ke canonical profile_key berbasis identitas produk yang bersih
-- 3) Menjaga tabel PO / Receipt / Opening / Gudang / Stock aggregate tetap konsisten
--
-- Catatan:
-- - Script ini fokus ke profile pembelian & stok yang tercemar oleh description seperti:
--   'IMPORT DARI ...', 'OPENING ...', 'DARI PENGAJUAN ...', 'AUTO-CREATED FROM OPENING IDENTITY'
-- - Aman dijalankan ulang. Jika canonical profile sudah ada, script akan memakai profile tersebut.
-- - Untuk catalog, row dirty lama tetap dipertahankan sebagai jejak audit tetapi dinonaktifkan
--   saat canonical profile berbeda.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair canonical profile description 2026-06-06';

DROP TEMPORARY TABLE IF EXISTS tmp_dirty_profile_sources;
CREATE TEMPORARY TABLE tmp_dirty_profile_sources (
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  old_profile_key CHAR(64) NOT NULL,
  line_kind VARCHAR(20) NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  profile_name VARCHAR(150) NULL,
  profile_brand VARCHAR(120) NULL,
  PRIMARY KEY (source_table, source_id),
  KEY idx_tmp_dirty_profile_key (old_profile_key)
) ENGINE=InnoDB;

INSERT INTO tmp_dirty_profile_sources (
  source_table, source_id, old_profile_key, line_kind,
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_content_per_buy, unit_price, profile_name, profile_brand
)
SELECT
  'mst_purchase_catalog',
  c.id,
  c.profile_key,
  COALESCE(c.line_kind, CASE WHEN c.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END),
  c.item_id,
  c.material_id,
  c.buy_uom_id,
  c.content_uom_id,
  ROUND(COALESCE(c.content_per_buy, 1), 6),
  ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2),
  NULLIF(TRIM(c.catalog_name), ''),
  NULLIF(TRIM(c.brand_name), '')
FROM mst_purchase_catalog c
WHERE c.profile_key IS NOT NULL
  AND TRIM(c.profile_key) <> ''
  AND (
    UPPER(COALESCE(c.line_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(c.line_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(c.line_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(c.line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
  );

INSERT IGNORE INTO tmp_dirty_profile_sources (
  source_table, source_id, old_profile_key, line_kind,
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_content_per_buy, unit_price, profile_name, profile_brand
)
SELECT
  'pur_purchase_order_line',
  l.id,
  l.profile_key,
  COALESCE(l.line_kind, CASE WHEN l.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END),
  l.item_id,
  l.material_id,
  l.buy_uom_id,
  l.content_uom_id,
  ROUND(COALESCE(l.content_per_buy, 1), 6),
  ROUND(COALESCE(l.unit_price, 0), 2),
  NULLIF(TRIM(COALESCE(l.snapshot_item_name, l.snapshot_material_name)), ''),
  NULLIF(TRIM(l.brand_name), '')
FROM pur_purchase_order_line l
WHERE l.profile_key IS NOT NULL
  AND TRIM(l.profile_key) <> ''
  AND (
    UPPER(COALESCE(l.line_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(l.line_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(l.line_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(l.line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
    OR UPPER(COALESCE(l.snapshot_line_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(l.snapshot_line_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(l.snapshot_line_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(l.snapshot_line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
  );

INSERT IGNORE INTO tmp_dirty_profile_sources (
  source_table, source_id, old_profile_key, line_kind,
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_content_per_buy, unit_price, profile_name, profile_brand
)
SELECT
  'inv_stock_opening_snapshot',
  s.id,
  s.profile_key,
  CASE WHEN s.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  ROUND(COALESCE(s.opening_avg_cost_per_content, 0) * COALESCE(s.profile_content_per_buy, 1), 2),
  NULLIF(TRIM(s.profile_name), ''),
  NULLIF(TRIM(s.profile_brand), '')
FROM inv_stock_opening_snapshot s
WHERE s.profile_key IS NOT NULL
  AND TRIM(s.profile_key) <> ''
  AND (
    UPPER(COALESCE(s.profile_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
  );

INSERT IGNORE INTO tmp_dirty_profile_sources (
  source_table, source_id, old_profile_key, line_kind,
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_content_per_buy, unit_price, profile_name, profile_brand
)
SELECT
  'inv_division_stock_opening_snapshot',
  s.id,
  s.profile_key,
  CASE WHEN s.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  ROUND(COALESCE(s.opening_avg_cost_per_content, 0) * COALESCE(s.profile_content_per_buy, 1), 2),
  NULLIF(TRIM(s.profile_name), ''),
  NULLIF(TRIM(s.profile_brand), '')
FROM inv_division_stock_opening_snapshot s
WHERE s.profile_key IS NOT NULL
  AND TRIM(s.profile_key) <> ''
  AND (
    UPPER(COALESCE(s.profile_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
  );

INSERT IGNORE INTO tmp_dirty_profile_sources (
  source_table, source_id, old_profile_key, line_kind,
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_content_per_buy, unit_price, profile_name, profile_brand
)
SELECT
  'inv_warehouse_stock_opening_snapshot',
  s.id,
  s.profile_key,
  CASE WHEN s.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  ROUND(COALESCE(s.profile_content_per_buy, 1), 6),
  ROUND(COALESCE(s.opening_avg_cost_per_content, 0) * COALESCE(s.profile_content_per_buy, 1), 2),
  NULLIF(TRIM(s.profile_name), ''),
  NULLIF(TRIM(s.profile_brand), '')
FROM inv_warehouse_stock_opening_snapshot s
WHERE s.profile_key IS NOT NULL
  AND TRIM(s.profile_key) <> ''
  AND (
    UPPER(COALESCE(s.profile_description, '')) LIKE 'IMPORT DARI%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'OPENING%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'DARI PENGAJUAN%'
    OR UPPER(COALESCE(s.profile_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
  );

DROP TEMPORARY TABLE IF EXISTS tmp_dirty_profile_keys;
CREATE TEMPORARY TABLE tmp_dirty_profile_keys AS
SELECT
  d.old_profile_key,
  MAX(COALESCE(NULLIF(TRIM(d.line_kind), ''), CASE WHEN d.material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END)) AS line_kind,
  MAX(d.item_id) AS item_id,
  MAX(d.material_id) AS material_id,
  MAX(d.buy_uom_id) AS buy_uom_id,
  MAX(d.content_uom_id) AS content_uom_id,
  ROUND(MAX(d.profile_content_per_buy), 6) AS profile_content_per_buy,
  ROUND(MAX(d.unit_price), 2) AS unit_price,
  MAX(d.profile_name) AS profile_name,
  MAX(d.profile_brand) AS profile_brand
FROM tmp_dirty_profile_sources d
GROUP BY d.old_profile_key;

ALTER TABLE tmp_dirty_profile_keys
  ADD PRIMARY KEY (old_profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_profile_repair_map;
CREATE TEMPORARY TABLE tmp_profile_repair_map AS
SELECT
  k.old_profile_key,
  k.line_kind,
  k.item_id,
  k.material_id,
  k.buy_uom_id,
  k.content_uom_id,
  k.profile_content_per_buy,
  k.unit_price,
  k.profile_name,
  k.profile_brand,
  NULL AS canonical_profile_description,
  COALESCE(
    (
      SELECT c.profile_key
      FROM mst_purchase_catalog c
      WHERE UPPER(TRIM(COALESCE(c.catalog_name, ''))) = UPPER(TRIM(COALESCE(k.profile_name, '')))
        AND UPPER(TRIM(COALESCE(c.brand_name, ''))) = UPPER(TRIM(COALESCE(k.profile_brand, '')))
        AND UPPER(TRIM(COALESCE(c.line_description, ''))) = ''
        AND COALESCE(c.item_id, 0) = COALESCE(k.item_id, 0)
        AND COALESCE(c.material_id, 0) = COALESCE(k.material_id, 0)
        AND COALESCE(c.buy_uom_id, 0) = COALESCE(k.buy_uom_id, 0)
        AND COALESCE(c.content_uom_id, 0) = COALESCE(k.content_uom_id, 0)
        AND ROUND(COALESCE(c.content_per_buy, 1), 6) = ROUND(k.profile_content_per_buy, 6)
        AND ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2) = ROUND(k.unit_price, 2)
      ORDER BY c.id ASC
      LIMIT 1
    ),
    LOWER(SHA2(CONCAT_WS('|',
      'CANONICAL_PURCHASE_PROFILE',
      k.line_kind,
      COALESCE(k.item_id, 0),
      COALESCE(k.material_id, 0),
      COALESCE(k.buy_uom_id, 0),
      COALESCE(k.content_uom_id, 0),
      ROUND(k.profile_content_per_buy, 6),
      ROUND(k.unit_price, 2),
      UPPER(TRIM(COALESCE(k.profile_name, ''))),
      UPPER(TRIM(COALESCE(k.profile_brand, ''))),
      ''
    ), 256))
  ) AS canonical_profile_key
FROM tmp_dirty_profile_keys k;

ALTER TABLE tmp_profile_repair_map
  ADD PRIMARY KEY (old_profile_key),
  ADD KEY idx_tmp_profile_repair_canonical (canonical_profile_key);

INSERT INTO mst_purchase_catalog (
  profile_key, line_kind, item_id, material_id, catalog_name, brand_name, line_description,
  buy_uom_id, content_uom_id, content_per_buy, conversion_factor_to_content,
  standard_price, last_unit_price, notes, is_active
)
SELECT
  m.canonical_profile_key,
  m.line_kind,
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
  CONCAT(@repair_tag, ' | inserted canonical row from dirty profile ', LEFT(m.old_profile_key, 16)),
  1
FROM tmp_profile_repair_map m
LEFT JOIN mst_purchase_catalog c ON c.profile_key = m.canonical_profile_key
WHERE c.id IS NULL;

UPDATE mst_purchase_catalog c
JOIN tmp_profile_repair_map m ON m.old_profile_key = c.profile_key
SET
  c.line_description = NULL,
  c.notes = TRIM(CONCAT(
    COALESCE(c.notes, ''),
    CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )),
  c.is_active = CASE WHEN m.canonical_profile_key = c.profile_key THEN 1 ELSE 0 END
WHERE
  UPPER(COALESCE(c.line_description, '')) LIKE 'IMPORT DARI%'
  OR UPPER(COALESCE(c.line_description, '')) LIKE 'OPENING%'
  OR UPPER(COALESCE(c.line_description, '')) LIKE 'DARI PENGAJUAN%'
  OR UPPER(COALESCE(c.line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%';

UPDATE pur_purchase_order_line l
JOIN tmp_profile_repair_map m ON m.old_profile_key = l.profile_key
SET
  l.profile_key = m.canonical_profile_key,
  l.line_description = NULL,
  l.snapshot_line_description = NULL
WHERE l.profile_key <> m.canonical_profile_key
   OR l.line_description IS NOT NULL
   OR l.snapshot_line_description IS NOT NULL;

UPDATE pur_purchase_receipt_line l
JOIN tmp_profile_repair_map m ON m.old_profile_key = l.profile_key
SET
  l.profile_key = m.canonical_profile_key,
  l.line_description = NULL
WHERE l.profile_key <> m.canonical_profile_key
   OR l.line_description IS NOT NULL;

UPDATE inv_stock_opening_snapshot s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_stock_movement_log l
JOIN tmp_profile_repair_map m ON m.old_profile_key = l.profile_key
SET
  l.profile_key = m.canonical_profile_key,
  l.profile_description = NULL,
  l.notes = TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE l.profile_key <> m.canonical_profile_key
   OR l.profile_description IS NOT NULL;

UPDATE inv_material_fifo_lot l
JOIN tmp_profile_repair_map m ON m.old_profile_key = l.profile_key
SET
  l.profile_key = m.canonical_profile_key
WHERE l.profile_key <> m.canonical_profile_key;

UPDATE inv_division_stock_balance s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_warehouse_stock_balance s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_division_daily_rollup s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_warehouse_daily_rollup s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.profile_description = NULL
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL;

UPDATE inv_division_monthly_opening s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.identity_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL
   OR s.identity_key <> m.canonical_profile_key;

UPDATE inv_warehouse_monthly_opening s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.identity_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL
   OR s.identity_key <> m.canonical_profile_key;

UPDATE inv_division_monthly_stock s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.identity_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL
   OR s.identity_key <> m.canonical_profile_key;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_profile_repair_map m ON m.old_profile_key = s.profile_key
SET
  s.profile_key = m.canonical_profile_key,
  s.identity_key = m.canonical_profile_key,
  s.profile_description = NULL,
  s.notes = TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  ))
WHERE s.profile_key <> m.canonical_profile_key
   OR s.profile_description IS NOT NULL
   OR s.identity_key <> m.canonical_profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_div_monthly_conflicts;
CREATE TEMPORARY TABLE tmp_div_monthly_conflicts AS
SELECT
  s.month_key,
  s.division_id,
  s.destination_type,
  s.identity_key
FROM inv_division_monthly_stock s
WHERE s.profile_key IN (SELECT canonical_profile_key FROM tmp_profile_repair_map)
GROUP BY
  s.month_key,
  s.division_id,
  s.destination_type,
  s.identity_key
HAVING COUNT(*) > 1;

DROP TEMPORARY TABLE IF EXISTS tmp_div_monthly_conflict_rows;
CREATE TEMPORARY TABLE tmp_div_monthly_conflict_rows AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_div_monthly_conflicts c
  ON c.month_key = s.month_key
 AND c.division_id = s.division_id
 AND c.destination_type = s.destination_type
 AND c.identity_key = s.identity_key;

DROP TEMPORARY TABLE IF EXISTS tmp_div_monthly_conflict_merge;
CREATE TEMPORARY TABLE tmp_div_monthly_conflict_merge AS
SELECT
  MIN(id) AS keep_id,
  month_key,
  division_id,
  destination_type,
  MAX(stock_domain) AS stock_domain,
  identity_key,
  MAX(item_id) AS item_id,
  MAX(material_id) AS material_id,
  MAX(buy_uom_id) AS buy_uom_id,
  MAX(content_uom_id) AS content_uom_id,
  MAX(profile_key) AS profile_key,
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
FROM tmp_div_monthly_conflict_rows
GROUP BY
  month_key,
  division_id,
  destination_type,
  identity_key;

DELETE s
FROM inv_division_monthly_stock s
JOIN tmp_div_monthly_conflict_rows r ON r.id = s.id;

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
FROM tmp_div_monthly_conflict_merge;

COMMIT;

SELECT 'tmp_profile_repair_map' AS metric, COUNT(*) AS total_rows FROM tmp_profile_repair_map
UNION ALL
SELECT 'dirty_catalog_remaining', COUNT(*) FROM mst_purchase_catalog
WHERE UPPER(COALESCE(line_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_po_remaining', COUNT(*) FROM pur_purchase_order_line
WHERE UPPER(COALESCE(line_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_receipt_remaining', COUNT(*) FROM pur_purchase_receipt_line
WHERE UPPER(COALESCE(line_description, '')) LIKE 'IMPORT DARI%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'OPENING%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'DARI PENGAJUAN%'
   OR UPPER(COALESCE(line_description, '')) LIKE 'AUTO-CREATED FROM OPENING IDENTITY%'
UNION ALL
SELECT 'dirty_movement_remaining', COUNT(*) FROM inv_stock_movement_log
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
