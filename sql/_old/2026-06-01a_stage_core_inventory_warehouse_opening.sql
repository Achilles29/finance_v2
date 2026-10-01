SET NAMES utf8mb4;

-- ============================================================
-- Stage saldo stok gudang dari core ke Finance,
-- scan exact match profile_key Finance,
-- lalu auto insert opening gudang hanya untuk row yang match dan qty > 0.
-- ============================================================

SET @src_db = 'core';
SET @source_warehouse_code = 'GUDANG_UTAMA';
SET @snapshot_month = DATE_FORMAT(CURDATE(), '%Y-%m-01');

START TRANSACTION;

DROP TABLE IF EXISTS stg_core_inventory_warehouse_opening;

CREATE TABLE stg_core_inventory_warehouse_opening (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_month DATE NOT NULL,

  stock_domain ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NOT NULL,

  profile_name VARCHAR(200) NULL,
  profile_brand VARCHAR(120) NULL,
  profile_description VARCHAR(255) NULL,
  profile_expired_date DATE NULL,
  profile_content_per_buy DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  profile_buy_uom_code VARCHAR(30) NULL,
  profile_content_uom_code VARCHAR(30) NULL,

  opening_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  opening_avg_cost_per_content DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  opening_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_type ENUM('MANUAL','AUTO_REBUILD','OPNAME') NOT NULL DEFAULT 'MANUAL',
  notes VARCHAR(255) NULL,

  source_warehouse_code VARCHAR(30) NOT NULL,
  source_profile_key CHAR(64) NULL,
  matched_finance_profile_key CHAR(64) NULL,
  matched_profile_source VARCHAR(50) NULL,
  source_line_kind ENUM('ITEM','MATERIAL') NOT NULL DEFAULT 'ITEM',
  source_item_id BIGINT UNSIGNED NULL,
  source_material_id BIGINT UNSIGNED NULL,
  source_uom_id BIGINT UNSIGNED NULL,
  source_uom_code VARCHAR(30) NULL,
  source_brand_name VARCHAR(100) NULL,
  source_packaging VARCHAR(100) NULL,
  source_isi_per_pack DECIMAL(18,6) NOT NULL DEFAULT 1.000000,
  source_qty_on_hand DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  source_avg_cost_per_buy DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  source_last_txn_date DATE NULL,

  source_item_code VARCHAR(50) NULL,
  source_item_name VARCHAR(150) NULL,
  source_material_code VARCHAR(50) NULL,
  source_material_name VARCHAR(150) NULL,

  mapping_status VARCHAR(30) NOT NULL DEFAULT 'READY',
  mapping_notes VARCHAR(255) NULL,
  import_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
  import_notes VARCHAR(255) NULL,
  imported_opening_id BIGINT UNSIGNED NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_stg_core_inventory_wh_opening (snapshot_month, source_warehouse_code, profile_key),
  KEY idx_stg_core_inventory_wh_opening_status (mapping_status),
  KEY idx_stg_core_inventory_wh_opening_import (import_status),
  KEY idx_stg_core_inventory_wh_opening_match (matched_finance_profile_key),
  KEY idx_stg_core_inventory_wh_opening_item (item_id),
  KEY idx_stg_core_inventory_wh_opening_material (material_id),
  KEY idx_stg_core_inventory_wh_opening_source_item (source_item_id),
  KEY idx_stg_core_inventory_wh_opening_source_material (source_material_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_finance_profile_key_match;

CREATE TEMPORARY TABLE tmp_finance_profile_key_match AS
SELECT
  profile_key,
  SUBSTRING_INDEX(GROUP_CONCAT(match_source ORDER BY priority ASC SEPARATOR ','), ',', 1) AS match_source
FROM (
  SELECT TRIM(profile_key) AS profile_key, 'OPENING_WAREHOUSE' AS match_source, 1 AS priority
  FROM inv_warehouse_stock_opening_snapshot
  WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> ''

  UNION ALL

  SELECT TRIM(profile_key) AS profile_key, 'MOVEMENT_WAREHOUSE' AS match_source, 2 AS priority
  FROM inv_stock_movement_log
  WHERE profile_key IS NOT NULL
    AND TRIM(profile_key) <> ''
    AND COALESCE(movement_scope, 'WAREHOUSE') = 'WAREHOUSE'

  UNION ALL

  SELECT TRIM(profile_key) AS profile_key, 'PURCHASE_CATALOG' AS match_source, 3 AS priority
  FROM mst_purchase_catalog
  WHERE profile_key IS NOT NULL AND TRIM(profile_key) <> ''
) unioned
GROUP BY profile_key;

ALTER TABLE tmp_finance_profile_key_match
  ADD PRIMARY KEY (profile_key);

INSERT INTO stg_core_inventory_warehouse_opening (
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
  source_warehouse_code,
  source_profile_key,
  matched_finance_profile_key,
  matched_profile_source,
  source_line_kind,
  source_item_id,
  source_material_id,
  source_uom_id,
  source_uom_code,
  source_brand_name,
  source_packaging,
  source_isi_per_pack,
  source_qty_on_hand,
  source_avg_cost_per_buy,
  source_last_txn_date,
  source_item_code,
  source_item_name,
  source_material_code,
  source_material_name,
  mapping_status,
  mapping_notes,
  import_status,
  import_notes,
  imported_opening_id
)
SELECT
  @snapshot_month AS snapshot_month,
  CASE
    WHEN src.target_material_id IS NOT NULL THEN 'MATERIAL'
    ELSE 'ITEM'
  END AS stock_domain,
  src.target_item_id AS item_id,
  src.target_material_id AS material_id,
  src.target_buy_uom_id AS buy_uom_id,
  src.target_content_uom_id AS content_uom_id,
  COALESCE(
    src.matched_finance_profile_key,
    src.source_profile_key,
    SHA2(CONCAT_WS('|',
      CASE WHEN src.target_material_id IS NOT NULL THEN 'MATERIAL' ELSE 'ITEM' END,
      COALESCE(src.target_item_id, 0),
      COALESCE(src.target_material_id, 0),
      COALESCE(src.target_buy_uom_id, 0),
      COALESCE(src.target_content_uom_id, 0),
      UPPER(TRIM(COALESCE(src.profile_name, ''))),
      UPPER(TRIM(COALESCE(src.profile_brand, ''))),
      UPPER(TRIM(COALESCE(src.profile_description, ''))),
      FORMAT(src.profile_content_per_buy, 6)
    ), 256)
  ) AS profile_key,
  src.profile_name,
  src.profile_brand,
  src.profile_description,
  NULL AS profile_expired_date,
  src.profile_content_per_buy,
  src.profile_buy_uom_code,
  src.profile_content_uom_code,
  src.opening_qty_buy,
  src.opening_qty_content,
  src.opening_avg_cost_per_content,
  src.opening_total_value,
  'MANUAL' AS source_type,
  CONCAT('Staging migrasi core.inv_warehouse_balance. profile_match=', COALESCE(src.matched_profile_source, 'NONE'), '; last_txn=', COALESCE(DATE_FORMAT(src.source_last_txn_date, '%Y-%m-%d'), '-')) AS notes,
  src.source_warehouse_code,
  src.source_profile_key,
  src.matched_finance_profile_key,
  src.matched_profile_source,
  src.source_line_kind,
  src.source_item_id,
  src.source_material_id,
  src.source_uom_id,
  src.source_uom_code,
  src.source_brand_name,
  src.source_packaging,
  src.source_isi_per_pack,
  src.source_qty_on_hand,
  src.source_avg_cost_per_buy,
  src.source_last_txn_date,
  src.source_item_code,
  src.source_item_name,
  src.source_material_code,
  src.source_material_name,
  CASE
    WHEN src.source_profile_key IS NULL THEN 'NO_SOURCE_PROFILE_KEY'
    WHEN src.matched_finance_profile_key IS NULL THEN 'PROFILE_KEY_NOT_FOUND'
    WHEN src.target_item_id IS NULL THEN 'MISSING_ITEM'
    WHEN src.target_buy_uom_id IS NULL THEN 'MISSING_BUY_UOM'
    WHEN src.target_content_uom_id IS NULL THEN 'MISSING_CONTENT_UOM'
    WHEN src.source_qty_on_hand < 0 THEN 'NEGATIVE_QTY'
    WHEN src.source_qty_on_hand = 0 THEN 'ZERO_QTY'
    ELSE 'READY'
  END AS mapping_status,
  CASE
    WHEN src.source_profile_key IS NULL THEN 'Source core tidak punya profile_key.'
    WHEN src.matched_finance_profile_key IS NULL THEN 'Profile key core tidak ditemukan di profile key Finance live.'
    WHEN src.target_item_id IS NULL THEN 'Item Finance tidak ketemu. Periksa sinkron master item/material dari core.'
    WHEN src.target_buy_uom_id IS NULL THEN 'UOM beli Finance tidak ketemu berdasarkan code core.'
    WHEN src.target_content_uom_id IS NULL THEN 'UOM isi Finance tidak ketemu. Fallback content UOM gagal.'
    WHEN src.source_qty_on_hand < 0 THEN 'Saldo core minus. Diabaikan dari import opening.'
    WHEN src.source_qty_on_hand = 0 THEN 'Saldo nol. Diabaikan dari import opening.'
    ELSE CONCAT('Profile key cocok dengan Finance dari ', src.matched_profile_source, '.')
  END AS mapping_notes,
  CASE
    WHEN src.source_qty_on_hand <= 0 THEN 'SKIPPED_NON_POSITIVE'
    WHEN src.source_profile_key IS NULL THEN 'SKIPPED_NO_SOURCE_KEY'
    WHEN src.matched_finance_profile_key IS NULL THEN 'SKIPPED_NO_KEY_MATCH'
    WHEN src.target_item_id IS NULL OR src.target_buy_uom_id IS NULL OR src.target_content_uom_id IS NULL THEN 'SKIPPED_MAPPING'
    ELSE 'READY_TO_IMPORT'
  END AS import_status,
  CASE
    WHEN src.source_qty_on_hand <= 0 THEN 'Qty source <= 0, diabaikan sesuai permintaan.'
    WHEN src.source_profile_key IS NULL THEN 'Source core tidak punya profile_key.'
    WHEN src.matched_finance_profile_key IS NULL THEN 'Tidak ada exact profile key match dengan Finance.'
    WHEN src.target_item_id IS NULL OR src.target_buy_uom_id IS NULL OR src.target_content_uom_id IS NULL THEN 'Mapping item/uom belum lengkap.'
    ELSE 'Siap auto insert ke opening gudang.'
  END AS import_notes,
  NULL AS imported_opening_id
FROM (
  SELECT
    b.warehouse_code AS source_warehouse_code,
    NULLIF(TRIM(b.profile_key), '') AS source_profile_key,
    pm.profile_key AS matched_finance_profile_key,
    pm.match_source AS matched_profile_source,
    UPPER(TRIM(COALESCE(b.line_kind, 'ITEM'))) AS source_line_kind,
    b.item_id AS source_item_id,
    b.material_id AS source_material_id,
    b.uom_id AS source_uom_id,
    su.code AS source_uom_code,
    NULLIF(TRIM(b.brand_name), '') AS source_brand_name,
    NULLIF(TRIM(b.packaging), '') AS source_packaging,
    ROUND(GREATEST(COALESCE(b.isi_per_pack, 1), 0.000001), 6) AS source_isi_per_pack,
    ROUND(COALESCE(b.qty_on_hand, 0), 4) AS source_qty_on_hand,
    ROUND(COALESCE(b.avg_cost, 0), 6) AS source_avg_cost_per_buy,
    b.last_txn_date AS source_last_txn_date,
    si.item_code AS source_item_code,
    si.item_name AS source_item_name,
    sm.material_code AS source_material_code,
    sm.material_name AS source_material_name,

    COALESCE(ti_direct.id, ti_from_material.id) AS target_item_id,
    COALESCE(ti_direct.material_id, ti_from_material.material_id, tm.id) AS target_material_id,
    tbu.id AS target_buy_uom_id,
    COALESCE(ti_direct.content_uom_id, ti_from_material.content_uom_id, tm.content_uom_id, tbu.id) AS target_content_uom_id,

    COALESCE(
      NULLIF(TRIM(si.item_name), ''),
      NULLIF(TRIM(sm.material_name), ''),
      NULLIF(TRIM(ti_direct.item_name), ''),
      NULLIF(TRIM(ti_from_material.item_name), ''),
      NULLIF(TRIM(tm.material_name), '')
    ) AS profile_name,
    NULLIF(TRIM(b.brand_name), '') AS profile_brand,
    NULLIF(TRIM(b.packaging), '') AS profile_description,
    ROUND(GREATEST(COALESCE(b.isi_per_pack, 1), 0.000001), 6) AS profile_content_per_buy,
    tbu.code AS profile_buy_uom_code,
    tcu.code AS profile_content_uom_code,
    ROUND(COALESCE(b.qty_on_hand, 0), 4) AS opening_qty_buy,
    ROUND(COALESCE(b.qty_on_hand, 0) * GREATEST(COALESCE(b.isi_per_pack, 1), 0.000001), 4) AS opening_qty_content,
    ROUND(
      CASE
        WHEN GREATEST(COALESCE(b.isi_per_pack, 1), 0.000001) > 0
          THEN COALESCE(b.avg_cost, 0) / GREATEST(COALESCE(b.isi_per_pack, 1), 0.000001)
        ELSE COALESCE(b.avg_cost, 0)
      END,
      6
    ) AS opening_avg_cost_per_content,
    ROUND(COALESCE(b.qty_on_hand, 0) * COALESCE(b.avg_cost, 0), 2) AS opening_total_value
  FROM core.inv_warehouse_balance b
  LEFT JOIN core.m_item si
    ON si.id = b.item_id
  LEFT JOIN core.m_material sm
    ON sm.id = b.material_id
  LEFT JOIN core.m_uom su
    ON su.id = b.uom_id

  LEFT JOIN mst_material tm
    ON tm.material_code = sm.material_code
  LEFT JOIN mst_item ti_direct
    ON ti_direct.item_code = si.item_code
  LEFT JOIN (
    SELECT material_id, MIN(id) AS item_id
    FROM mst_item
    WHERE material_id IS NOT NULL
    GROUP BY material_id
  ) item_by_material
    ON item_by_material.material_id = tm.id
  LEFT JOIN mst_item ti_from_material
    ON ti_from_material.id = item_by_material.item_id
  LEFT JOIN mst_uom tbu
    ON tbu.code = su.code
  LEFT JOIN mst_uom tcu
    ON tcu.id = COALESCE(ti_direct.content_uom_id, ti_from_material.content_uom_id, tm.content_uom_id, tbu.id)
  LEFT JOIN tmp_finance_profile_key_match pm
    ON pm.profile_key = NULLIF(TRIM(b.profile_key), '')
  WHERE COALESCE(b.warehouse_code, 'GUDANG_UTAMA') = @source_warehouse_code
) src;

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
  s.snapshot_month,
  s.stock_domain,
  s.item_id,
  s.material_id,
  s.buy_uom_id,
  s.content_uom_id,
  s.matched_finance_profile_key,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.profile_expired_date,
  s.profile_content_per_buy,
  s.profile_buy_uom_code,
  s.profile_content_uom_code,
  s.opening_qty_buy,
  s.opening_qty_content,
  s.opening_avg_cost_per_content,
  s.opening_total_value,
  'MANUAL' AS source_type,
  CONCAT('Import match exact profile_key dari core warehouse balance. source_profile_key=', COALESCE(s.source_profile_key, '-'), '; match_source=', COALESCE(s.matched_profile_source, '-')) AS notes,
  NULL AS created_by
FROM stg_core_inventory_warehouse_opening s
WHERE s.mapping_status = 'READY'
  AND s.import_status = 'READY_TO_IMPORT'
  AND s.matched_finance_profile_key IS NOT NULL
  AND s.opening_qty_buy > 0
  AND s.opening_qty_content > 0
ON DUPLICATE KEY UPDATE
  profile_name = VALUES(profile_name),
  profile_brand = VALUES(profile_brand),
  profile_description = VALUES(profile_description),
  profile_expired_date = VALUES(profile_expired_date),
  profile_content_per_buy = VALUES(profile_content_per_buy),
  profile_buy_uom_code = VALUES(profile_buy_uom_code),
  profile_content_uom_code = VALUES(profile_content_uom_code),
  opening_qty_buy = VALUES(opening_qty_buy),
  opening_qty_content = VALUES(opening_qty_content),
  opening_avg_cost_per_content = VALUES(opening_avg_cost_per_content),
  opening_total_value = VALUES(opening_total_value),
  source_type = VALUES(source_type),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

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
  s.import_status = 'IMPORTED',
  s.import_notes = CONCAT('Opening gudang terinput ke inv_warehouse_stock_opening_snapshot id=', o.id),
  s.imported_opening_id = o.id
WHERE s.mapping_status = 'READY'
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
  id,
  snapshot_month,
  stock_domain,
  source_profile_key,
  matched_finance_profile_key,
  matched_profile_source,
  source_item_code,
  source_material_code,
  profile_name,
  profile_brand,
  profile_description,
  profile_buy_uom_code,
  profile_content_uom_code,
  opening_qty_buy,
  opening_qty_content,
  opening_avg_cost_per_content,
  opening_total_value,
  mapping_status,
  import_status,
  imported_opening_id,
  mapping_notes,
  import_notes
FROM stg_core_inventory_warehouse_opening
ORDER BY
  CASE WHEN import_status = 'IMPORTED' THEN 0 ELSE 1 END,
  CASE WHEN mapping_status = 'READY' THEN 0 ELSE 1 END,
  profile_name ASC,
  profile_brand ASC,
  source_profile_key ASC;