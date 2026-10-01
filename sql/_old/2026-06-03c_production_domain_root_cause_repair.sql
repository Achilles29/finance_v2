SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-03c_production_domain_root_cause_repair.sql
-- Tujuan :
-- 1) Menyetel ulang profile produksi yang salah domain ITEM -> MATERIAL
-- 2) Meluruskan histori transaksi + histori stok untuk Persediaan Produksi
-- 3) TIDAK dieksekusi otomatis oleh aplikasi; jalankan manual setelah review
--
-- Catatan:
-- - Script ini sengaja TIDAK menyentuh usage_purpose OPERASIONAL
-- - Script ini fokus pada jalur produksi / Persediaan Produksi
-- - Setelah COMMIT, buka /pos/stock-commit-audit lalu jalankan Retry Semua Gagal
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_prod_domain_targets;

CREATE TEMPORARY TABLE tmp_prod_domain_targets AS
SELECT DISTINCT
  c.id AS catalog_id,
  c.profile_key,
  c.item_id,
  i.material_id AS target_material_id,
  c.catalog_name,
  c.brand_name
FROM mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
WHERE COALESCE(c.is_active, 1) = 1
  AND UPPER(COALESCE(c.line_kind, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
  AND (
    EXISTS (
      SELECT 1
      FROM pur_purchase_order_line pol
      WHERE pol.profile_key = c.profile_key
        AND COALESCE(pol.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
    )
    OR EXISTS (
      SELECT 1
      FROM pur_purchase_receipt_line rl
      WHERE rl.profile_key = c.profile_key
        AND COALESCE(rl.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
    )
    OR EXISTS (
      SELECT 1
      FROM inv_division_monthly_stock ds
      WHERE ds.profile_key = c.profile_key
    )
    OR EXISTS (
      SELECT 1
      FROM inv_warehouse_monthly_stock ws
      WHERE ws.profile_key = c.profile_key
    )
  );

SELECT
  COUNT(*) AS total_target_profiles,
  GROUP_CONCAT(CONCAT(catalog_name, IF(brand_name IS NULL OR brand_name = '', '', CONCAT(' / ', brand_name))) ORDER BY catalog_name SEPARATOR ' | ') AS target_profiles
FROM tmp_prod_domain_targets;

-- ============================================================
-- 1) Profile aktif purchase catalog -> MATERIAL
-- ============================================================
UPDATE mst_purchase_catalog c
JOIN tmp_prod_domain_targets t ON t.catalog_id = c.id
SET
  c.line_kind = 'MATERIAL',
  c.material_id = t.target_material_id,
  c.notes = TRIM(CONCAT(
    COALESCE(c.notes, ''),
    CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END,
    'Production domain repair ITEM->MATERIAL 2026-06-03'
  )),
  c.updated_at = NOW()
WHERE UPPER(COALESCE(c.line_kind, 'ITEM')) = 'ITEM';

-- ============================================================
-- 2) Jalur transaksi produksi -> MATERIAL
-- ============================================================
SET @has_po_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_order_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_fix_po_line := IF(
  @has_po_usage > 0,
  "UPDATE pur_purchase_order_line l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.line_kind = 'MATERIAL',
       l.material_id = t.target_material_id
   WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
     AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_po_line FROM @sql_fix_po_line; EXECUTE stmt_fix_po_line; DEALLOCATE PREPARE stmt_fix_po_line;

SET @has_receipt_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_receipt_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_fix_receipt_line := IF(
  @has_receipt_usage > 0,
  "UPDATE pur_purchase_receipt_line l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.line_kind = 'MATERIAL',
       l.material_id = t.target_material_id
   WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
     AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_receipt_line FROM @sql_fix_receipt_line; EXECUTE stmt_fix_receipt_line; DEALLOCATE PREPARE stmt_fix_receipt_line;

SET @has_sr_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_store_request_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_fix_sr_line := IF(
  @has_sr_usage > 0,
  "UPDATE pur_store_request_line l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.line_kind = 'MATERIAL',
       l.material_id = t.target_material_id
   WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
     AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_sr_line FROM @sql_fix_sr_line; EXECUTE stmt_fix_sr_line; DEALLOCATE PREPARE stmt_fix_sr_line;

SET @has_div_req_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_division_request_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_fix_div_req_line := IF(
  @has_div_req_usage > 0,
  "UPDATE pur_division_request_line l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.line_kind = 'MATERIAL',
       l.material_id = t.target_material_id
   WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
     AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_div_req_line FROM @sql_fix_div_req_line; EXECUTE stmt_fix_div_req_line; DEALLOCATE PREPARE stmt_fix_div_req_line;

SET @has_fulfill_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_store_request_fulfillment_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_fix_fulfill_line := IF(
  @has_fulfill_usage > 0,
  "UPDATE pur_store_request_fulfillment_line l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.material_id = t.target_material_id
   WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
     AND COALESCE(l.item_id, 0) = t.item_id
     AND COALESCE(l.material_id, 0) <> t.target_material_id",
  'SELECT 1'
);
PREPARE stmt_fix_fulfill_line FROM @sql_fix_fulfill_line; EXECUTE stmt_fix_fulfill_line; DEALLOCATE PREPARE stmt_fix_fulfill_line;

-- ============================================================
-- 3) Movement log -> luruskan material_id untuk profile produksi
-- ============================================================
SET @sql_fix_movement := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_movement_log') > 0,
  "UPDATE inv_stock_movement_log l
   JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
   SET l.material_id = t.target_material_id
   WHERE COALESCE(l.item_id, 0) = t.item_id
     AND COALESCE(l.material_id, 0) <> t.target_material_id",
  'SELECT 1'
);
PREPARE stmt_fix_movement FROM @sql_fix_movement; EXECUTE stmt_fix_movement; DEALLOCATE PREPARE stmt_fix_movement;

-- ============================================================
-- 4) Snapshot / rollup / saldo bulanan -> MATERIAL
-- ============================================================
SET @sql_fix_inv_division_daily_rollup := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_daily_rollup') > 0,
  "UPDATE inv_division_daily_rollup x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_division_daily_rollup FROM @sql_fix_inv_division_daily_rollup; EXECUTE stmt_fix_inv_division_daily_rollup; DEALLOCATE PREPARE stmt_fix_inv_division_daily_rollup;

SET @sql_fix_inv_warehouse_daily_rollup := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_daily_rollup') > 0,
  "UPDATE inv_warehouse_daily_rollup x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_warehouse_daily_rollup FROM @sql_fix_inv_warehouse_daily_rollup; EXECUTE stmt_fix_inv_warehouse_daily_rollup; DEALLOCATE PREPARE stmt_fix_inv_warehouse_daily_rollup;

SET @sql_fix_inv_division_monthly_stock := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_stock') > 0,
  "UPDATE inv_division_monthly_stock x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_division_monthly_stock FROM @sql_fix_inv_division_monthly_stock; EXECUTE stmt_fix_inv_division_monthly_stock; DEALLOCATE PREPARE stmt_fix_inv_division_monthly_stock;

SET @sql_fix_inv_warehouse_monthly_stock := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_stock') > 0,
  "UPDATE inv_warehouse_monthly_stock x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_warehouse_monthly_stock FROM @sql_fix_inv_warehouse_monthly_stock; EXECUTE stmt_fix_inv_warehouse_monthly_stock; DEALLOCATE PREPARE stmt_fix_inv_warehouse_monthly_stock;

SET @sql_fix_inv_division_monthly_opname := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_monthly_opname') > 0,
  "UPDATE inv_division_monthly_opname x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_division_monthly_opname FROM @sql_fix_inv_division_monthly_opname; EXECUTE stmt_fix_inv_division_monthly_opname; DEALLOCATE PREPARE stmt_fix_inv_division_monthly_opname;

SET @sql_fix_inv_warehouse_monthly_opname := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_monthly_opname') > 0,
  "UPDATE inv_warehouse_monthly_opname x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_warehouse_monthly_opname FROM @sql_fix_inv_warehouse_monthly_opname; EXECUTE stmt_fix_inv_warehouse_monthly_opname; DEALLOCATE PREPARE stmt_fix_inv_warehouse_monthly_opname;

SET @sql_fix_inv_division_stock_opening_snapshot := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_division_stock_opening_snapshot') > 0,
  "UPDATE inv_division_stock_opening_snapshot x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_division_stock_opening_snapshot FROM @sql_fix_inv_division_stock_opening_snapshot; EXECUTE stmt_fix_inv_division_stock_opening_snapshot; DEALLOCATE PREPARE stmt_fix_inv_division_stock_opening_snapshot;

SET @sql_fix_inv_warehouse_stock_opening_snapshot := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_warehouse_stock_opening_snapshot') > 0,
  "UPDATE inv_warehouse_stock_opening_snapshot x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_warehouse_stock_opening_snapshot FROM @sql_fix_inv_warehouse_stock_opening_snapshot; EXECUTE stmt_fix_inv_warehouse_stock_opening_snapshot; DEALLOCATE PREPARE stmt_fix_inv_warehouse_stock_opening_snapshot;

SET @sql_fix_inv_stock_opening_snapshot := IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_opening_snapshot') > 0,
  "UPDATE inv_stock_opening_snapshot x
   JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
   SET x.stock_domain = 'MATERIAL',
       x.material_id = t.target_material_id
   WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'",
  'SELECT 1'
);
PREPARE stmt_fix_inv_stock_opening_snapshot FROM @sql_fix_inv_stock_opening_snapshot; EXECUTE stmt_fix_inv_stock_opening_snapshot; DEALLOCATE PREPARE stmt_fix_inv_stock_opening_snapshot;

-- ============================================================
-- 5) Validasi sisa drift sesudah repair
-- ============================================================
SELECT
  c.id AS catalog_id,
  c.profile_key,
  c.catalog_name,
  c.brand_name,
  c.line_kind,
  c.material_id,
  i.material_id AS expected_material_id
FROM mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
WHERE c.profile_key IN (SELECT profile_key FROM tmp_prod_domain_targets)
ORDER BY c.catalog_name, c.brand_name, c.id;

SELECT
  'pur_purchase_order_line_remaining_item' AS check_key,
  COUNT(*) AS total_rows
FROM pur_purchase_order_line l
JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
  AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'
UNION ALL
SELECT
  'pur_purchase_receipt_line_remaining_item',
  COUNT(*)
FROM pur_purchase_receipt_line l
JOIN tmp_prod_domain_targets t ON t.profile_key = l.profile_key
WHERE COALESCE(l.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
  AND UPPER(COALESCE(l.line_kind, 'ITEM')) = 'ITEM'
UNION ALL
SELECT
  'inv_division_monthly_stock_remaining_item',
  COUNT(*)
FROM inv_division_monthly_stock x
JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM'
UNION ALL
SELECT
  'inv_warehouse_monthly_stock_remaining_item',
  COUNT(*)
FROM inv_warehouse_monthly_stock x
JOIN tmp_prod_domain_targets t ON t.profile_key = x.profile_key
WHERE UPPER(COALESCE(x.stock_domain, 'ITEM')) = 'ITEM';

COMMIT;

SELECT
  'Lanjutkan langkah berikut:' AS note,
  '1) buka /pos/stock-commit-audit 2) klik Retry Semua Gagal 3) cek /pos/stock-live + /inventory/stock/division/reconcile' AS next_step;
