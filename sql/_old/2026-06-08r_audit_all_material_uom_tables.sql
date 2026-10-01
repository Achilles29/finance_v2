SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08r_audit_all_material_uom_tables.sql
-- Tujuan :
-- 1) Mendaftar SEMUA tabel yang memiliki material_id
-- 2) Menunjukkan tabel mana yang juga menyimpan buy/content UOM
-- 3) Menyusun footprint konflik buy_uom/content_uom lintas tabel aktif
-- 4) Menjadi dasar penentuan UOM kanonik per material sebelum repair massal
--
-- Catatan:
-- - Script ini TIDAK mengubah data
-- - Tabel backup/staging tetap diaudit, tetapi dipisahkan dari tabel aktif
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_material_tables;
CREATE TEMPORARY TABLE tmp_material_tables AS
SELECT
  c.TABLE_NAME,
  CASE
    WHEN c.TABLE_NAME LIKE 'backup\\_%' ESCAPE '\\'
      OR c.TABLE_NAME LIKE 'zz\\_%' ESCAPE '\\'
      OR c.TABLE_NAME LIKE 'tmp\\_%' ESCAPE '\\'
      OR c.TABLE_NAME LIKE 'z\\_%' ESCAPE '\\'
      OR c.TABLE_NAME LIKE '%legacy_backup%'
      THEN 'BACKUP'
    WHEN c.TABLE_NAME LIKE 'stg\\_%' ESCAPE '\\'
      THEN 'STAGING'
    ELSE 'ACTIVE'
  END AS table_group,
  MAX(CASE WHEN c.COLUMN_NAME = 'item_id' THEN 1 ELSE 0 END) AS has_item_id,
  MAX(CASE WHEN c.COLUMN_NAME = 'buy_uom_id' THEN 1 ELSE 0 END) AS has_buy_uom_id,
  MAX(CASE WHEN c.COLUMN_NAME = 'content_uom_id' THEN 1 ELSE 0 END) AS has_content_uom_id,
  MAX(CASE WHEN c.COLUMN_NAME IN ('content_per_buy', 'profile_content_per_buy', 'conversion_factor_to_content') THEN 1 ELSE 0 END) AS has_conversion_factor,
  MAX(CASE WHEN c.COLUMN_NAME = 'profile_key' THEN 1 ELSE 0 END) AS has_profile_key,
  MAX(CASE WHEN c.COLUMN_NAME = 'line_kind' THEN 1 ELSE 0 END) AS has_line_kind,
  MAX(CASE WHEN c.COLUMN_NAME = 'stock_domain' THEN 1 ELSE 0 END) AS has_stock_domain
FROM information_schema.COLUMNS c
WHERE c.TABLE_SCHEMA = DATABASE()
  AND c.TABLE_NAME IN (
    SELECT TABLE_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND COLUMN_NAME = 'material_id'
  )
GROUP BY c.TABLE_NAME;

ALTER TABLE tmp_material_tables
  ADD PRIMARY KEY (TABLE_NAME);

DROP TEMPORARY TABLE IF EXISTS tmp_material_uom_active_rows;
CREATE TEMPORARY TABLE tmp_material_uom_active_rows (
  source_table VARCHAR(80) NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  conversion_factor DECIMAL(18,6) NULL,
  row_code VARCHAR(255) NULL,
  row_name VARCHAR(255) NULL,
  profile_key CHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_material_uom_active_rows
SELECT 'mst_item', material_id, buy_uom_id, content_uom_id, ROUND(COALESCE(content_per_buy, 1), 6), item_code, item_name, ''
FROM mst_item
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'mst_purchase_catalog', material_id, buy_uom_id, content_uom_id, ROUND(COALESCE(content_per_buy, 1), 6), COALESCE(profile_key, ''), COALESCE(catalog_name, ''), COALESCE(profile_key, '')
FROM mst_purchase_catalog
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_division_monthly_stock', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_division_monthly_stock
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_division_stock_opening_snapshot', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_division_stock_opening_snapshot
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_stock_movement_log', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_stock_movement_log
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_material_fifo_lot', COALESCE(material_id, 0), buy_uom_id, content_uom_id, NULL, CAST(id AS CHAR), COALESCE(lot_no, ''), COALESCE(profile_key, '')
FROM inv_material_fifo_lot
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'pur_division_request_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM pur_division_request_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'pur_store_request_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM pur_store_request_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'pur_store_request_fulfillment_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM pur_store_request_fulfillment_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'pur_purchase_order_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(snapshot_item_name, snapshot_material_name, line_description, ''), COALESCE(profile_key, '')
FROM pur_purchase_order_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'pur_purchase_receipt_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(conversion_factor_to_content, 1), 6), CAST(id AS CHAR), COALESCE(brand_name, ''), COALESCE(profile_key, '')
FROM pur_purchase_receipt_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_stock_adjustment_line', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_stock_adjustment_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_division_monthly_opening', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_division_monthly_opening
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_division_monthly_opname', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_division_monthly_opname
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_division_stock_opname', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_division_stock_opname
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_stock_opening_snapshot', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_stock_opening_snapshot
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_warehouse_monthly_opening', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_warehouse_monthly_opening
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_warehouse_monthly_opname', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_warehouse_monthly_opname
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_warehouse_monthly_stock', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_warehouse_monthly_stock
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'inv_warehouse_stock_opening_snapshot', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM inv_warehouse_stock_opening_snapshot
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'stg_core_inventory_warehouse_opening', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(profile_content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(profile_name, ''), COALESCE(profile_key, '')
FROM stg_core_inventory_warehouse_opening
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

INSERT INTO tmp_material_uom_active_rows
SELECT 'stg_core_latest_purchase_material', COALESCE(material_id, 0), buy_uom_id, content_uom_id, ROUND(COALESCE(content_per_buy, 1), 6), CAST(id AS CHAR), COALESCE(material_name, ''), ''
FROM stg_core_latest_purchase_material
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) > 0;

ALTER TABLE tmp_material_uom_active_rows
  ADD KEY idx_tmp_material_uom_active_rows_material (material_id, source_table, buy_uom_id),
  ADD KEY idx_tmp_material_uom_active_rows_profile (profile_key(32));

-- ------------------------------------------------------------
-- A. Daftar semua tabel yang punya material_id
-- ------------------------------------------------------------
SELECT *
FROM tmp_material_tables
ORDER BY table_group, TABLE_NAME;

-- ------------------------------------------------------------
-- B. Ringkasan footprint konflik material di tabel aktif
-- ------------------------------------------------------------
SELECT
  r.material_id,
  m.material_code,
  m.material_name,
  COUNT(*) AS total_rows,
  COUNT(DISTINCT r.source_table) AS source_table_count,
  COUNT(DISTINCT r.buy_uom_id) AS distinct_buy_uom_count,
  COUNT(DISTINCT r.content_uom_id) AS distinct_content_uom_count,
  GROUP_CONCAT(DISTINCT CONCAT(r.buy_uom_id, ':', COALESCE(bu.code, '?')) ORDER BY r.buy_uom_id SEPARATOR ', ') AS observed_buy_uoms,
  GROUP_CONCAT(DISTINCT CONCAT(r.content_uom_id, ':', COALESCE(cu.code, '?')) ORDER BY r.content_uom_id SEPARATOR ', ') AS observed_content_uoms
FROM tmp_material_uom_active_rows r
JOIN mst_material m ON m.id = r.material_id
LEFT JOIN mst_uom bu ON bu.id = r.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = r.content_uom_id
GROUP BY r.material_id, m.material_code, m.material_name
HAVING COUNT(DISTINCT r.buy_uom_id) > 1
    OR COUNT(DISTINCT r.content_uom_id) > 1
ORDER BY distinct_buy_uom_count DESC, distinct_content_uom_count DESC, total_rows DESC, r.material_id;

-- ------------------------------------------------------------
-- C. Breakdown per source table
-- ------------------------------------------------------------
SELECT
  r.material_id,
  m.material_code,
  m.material_name,
  r.source_table,
  r.buy_uom_id,
  bu.code AS buy_uom_code,
  r.content_uom_id,
  cu.code AS content_uom_code,
  COUNT(*) AS row_count,
  COUNT(DISTINCT NULLIF(r.profile_key, '')) AS distinct_profile_key_count
FROM tmp_material_uom_active_rows r
JOIN mst_material m ON m.id = r.material_id
LEFT JOIN mst_uom bu ON bu.id = r.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = r.content_uom_id
GROUP BY
  r.material_id, m.material_code, m.material_name, r.source_table,
  r.buy_uom_id, bu.code, r.content_uom_id, cu.code
HAVING COUNT(*) > 0
ORDER BY r.material_id, r.source_table, r.buy_uom_id, r.content_uom_id;

-- ------------------------------------------------------------
-- D. Exact profile_key mismatch terhadap purchase catalog
-- ------------------------------------------------------------
SELECT
  r.source_table,
  r.material_id,
  m.material_code,
  m.material_name,
  r.profile_key,
  COUNT(*) AS mismatched_rows,
  GROUP_CONCAT(DISTINCT CONCAT(r.buy_uom_id, ':', COALESCE(bur.code, '?')) ORDER BY r.buy_uom_id SEPARATOR ', ') AS row_buy_uoms,
  GROUP_CONCAT(DISTINCT CONCAT(c.buy_uom_id, ':', COALESCE(buc.code, '?')) ORDER BY c.buy_uom_id SEPARATOR ', ') AS catalog_buy_uoms
FROM tmp_material_uom_active_rows r
JOIN mst_purchase_catalog c
  ON BINARY c.profile_key = BINARY r.profile_key
 AND COALESCE(c.material_id, 0) = COALESCE(r.material_id, 0)
LEFT JOIN mst_material m ON m.id = r.material_id
LEFT JOIN mst_uom bur ON bur.id = r.buy_uom_id
LEFT JOIN mst_uom buc ON buc.id = c.buy_uom_id
WHERE r.profile_key <> ''
  AND (
    COALESCE(r.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
    OR COALESCE(r.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0)
  )
GROUP BY r.source_table, r.material_id, m.material_code, m.material_name, r.profile_key
ORDER BY mismatched_rows DESC, r.material_id, r.source_table;

-- ------------------------------------------------------------
-- E. Summary total
-- ------------------------------------------------------------
SELECT 'tables_with_material_id' AS metric, COUNT(*) AS total
FROM tmp_material_tables

UNION ALL

SELECT 'active_tables_with_material_id', COUNT(*)
FROM tmp_material_tables
WHERE table_group = 'ACTIVE'

UNION ALL

SELECT 'active_tables_with_material_id_and_buy_uom', COUNT(*)
FROM tmp_material_tables
WHERE table_group = 'ACTIVE'
  AND has_buy_uom_id = 1

UNION ALL

SELECT 'materials_with_multi_buy_uom_in_active_tables', COUNT(*)
FROM (
  SELECT material_id
  FROM tmp_material_uom_active_rows
  GROUP BY material_id
  HAVING COUNT(DISTINCT buy_uom_id) > 1
) x

UNION ALL

SELECT 'materials_with_multi_content_uom_in_active_tables', COUNT(*)
FROM (
  SELECT material_id
  FROM tmp_material_uom_active_rows
  GROUP BY material_id
  HAVING COUNT(DISTINCT content_uom_id) > 1
) x

UNION ALL

SELECT 'exact_profile_key_uom_mismatch_groups', COUNT(*)
FROM (
  SELECT r.source_table, r.material_id, r.profile_key
  FROM tmp_material_uom_active_rows r
  JOIN mst_purchase_catalog c
    ON BINARY c.profile_key = BINARY r.profile_key
   AND COALESCE(c.material_id, 0) = COALESCE(r.material_id, 0)
  WHERE r.profile_key <> ''
    AND (
      COALESCE(r.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
      OR COALESCE(r.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0)
    )
  GROUP BY r.source_table, r.material_id, r.profile_key
) x;
