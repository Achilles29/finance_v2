SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-03b_production_domain_root_cause_audit.sql
-- Tujuan :
-- 1) Mengungkap pangkal masalah profile produksi yang masih ITEM
-- 2) Menunjukkan dampak ke PO, receipt, movement, dan snapshot stok
-- 3) Menjadi acuan review sebelum repair dijalankan
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_prod_domain_wrong_profiles;

CREATE TEMPORARY TABLE tmp_prod_domain_wrong_profiles AS
SELECT
  c.id AS catalog_id,
  c.profile_key,
  c.catalog_name,
  c.brand_name,
  c.line_kind AS catalog_line_kind,
  c.item_id,
  c.material_id AS catalog_material_id,
  COALESCE(i.material_id, 0) AS expected_material_id,
  i.item_name,
  m.material_code AS expected_material_code,
  m.material_name AS expected_material_name,
  COALESCE(c.is_active, 1) AS is_active,
  (SELECT COUNT(*)
     FROM pur_purchase_order_line pol
    WHERE pol.profile_key = c.profile_key
      AND COALESCE(pol.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
      AND UPPER(COALESCE(pol.line_kind, 'ITEM')) = 'ITEM') AS po_item_rows,
  (SELECT COUNT(*)
     FROM pur_purchase_receipt_line rl
    WHERE rl.profile_key = c.profile_key
      AND COALESCE(rl.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
      AND UPPER(COALESCE(rl.line_kind, 'ITEM')) = 'ITEM') AS receipt_item_rows,
  (SELECT COUNT(*)
     FROM pur_store_request_line srl
    WHERE srl.profile_key = c.profile_key
      AND COALESCE(srl.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
      AND UPPER(COALESCE(srl.line_kind, 'ITEM')) = 'ITEM') AS sr_item_rows,
  (SELECT COUNT(*)
     FROM pur_division_request_line drl
    WHERE drl.profile_key = c.profile_key
      AND COALESCE(drl.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
      AND UPPER(COALESCE(drl.line_kind, 'ITEM')) = 'ITEM') AS division_request_item_rows,
  (SELECT COUNT(*)
     FROM pur_store_request_fulfillment_line fl
    WHERE fl.profile_key = c.profile_key
      AND COALESCE(fl.usage_purpose, 'BAHAN_BAKU') = 'BAHAN_BAKU'
      AND (
        COALESCE(fl.item_id, 0) = COALESCE(c.item_id, 0)
        OR COALESCE(fl.material_id, 0) <> COALESCE(i.material_id, 0)
      )) AS fulfillment_item_rows,
  (SELECT COUNT(*)
     FROM inv_stock_movement_log ml
    WHERE ml.profile_key = c.profile_key) AS movement_rows,
  (SELECT COUNT(*)
     FROM inv_stock_movement_log ml
    WHERE ml.profile_key = c.profile_key
      AND COALESCE(ml.item_id, 0) = COALESCE(c.item_id, 0)
      AND COALESCE(ml.material_id, 0) <> COALESCE(i.material_id, 0)) AS movement_wrong_material_rows,
  (SELECT COUNT(*)
     FROM inv_division_monthly_stock ds
    WHERE ds.profile_key = c.profile_key
      AND UPPER(COALESCE(ds.stock_domain, 'ITEM')) = 'ITEM') AS division_monthly_item_rows,
  (SELECT COUNT(*)
     FROM inv_division_monthly_stock ds
    WHERE ds.profile_key = c.profile_key
      AND UPPER(COALESCE(ds.stock_domain, 'ITEM')) = 'MATERIAL') AS division_monthly_material_rows,
  (SELECT COUNT(*)
     FROM inv_warehouse_monthly_stock ws
    WHERE ws.profile_key = c.profile_key
      AND UPPER(COALESCE(ws.stock_domain, 'ITEM')) = 'ITEM') AS warehouse_monthly_item_rows,
  (SELECT COUNT(*)
     FROM inv_warehouse_monthly_stock ws
    WHERE ws.profile_key = c.profile_key
      AND UPPER(COALESCE(ws.stock_domain, 'ITEM')) = 'MATERIAL') AS warehouse_monthly_material_rows,
  (SELECT COUNT(*)
     FROM inv_division_daily_rollup dd
    WHERE dd.profile_key = c.profile_key
      AND UPPER(COALESCE(dd.stock_domain, 'ITEM')) = 'ITEM') AS division_daily_item_rows,
  (SELECT COUNT(*)
     FROM inv_division_daily_rollup dd
    WHERE dd.profile_key = c.profile_key
      AND UPPER(COALESCE(dd.stock_domain, 'ITEM')) = 'MATERIAL') AS division_daily_material_rows,
  (SELECT COUNT(*)
     FROM inv_warehouse_daily_rollup wd
    WHERE wd.profile_key = c.profile_key
      AND UPPER(COALESCE(wd.stock_domain, 'ITEM')) = 'ITEM') AS warehouse_daily_item_rows,
  (SELECT COUNT(*)
     FROM inv_warehouse_daily_rollup wd
    WHERE wd.profile_key = c.profile_key
      AND UPPER(COALESCE(wd.stock_domain, 'ITEM')) = 'MATERIAL') AS warehouse_daily_material_rows
FROM mst_purchase_catalog c
JOIN mst_item i ON i.id = c.item_id
LEFT JOIN mst_material m ON m.id = i.material_id
WHERE COALESCE(c.is_active, 1) = 1
  AND UPPER(COALESCE(c.line_kind, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0;

SELECT
  COUNT(*) AS total_wrong_active_profiles,
  SUM(CASE
        WHEN po_item_rows + receipt_item_rows + sr_item_rows + division_request_item_rows + fulfillment_item_rows > 0
        THEN 1 ELSE 0
      END) AS profiles_with_transaction_drift,
  SUM(CASE
        WHEN (division_monthly_item_rows > 0 AND division_monthly_material_rows > 0)
          OR (warehouse_monthly_item_rows > 0 AND warehouse_monthly_material_rows > 0)
          OR (division_daily_item_rows > 0 AND division_daily_material_rows > 0)
          OR (warehouse_daily_item_rows > 0 AND warehouse_daily_material_rows > 0)
        THEN 1 ELSE 0
      END) AS profiles_with_split_snapshot,
  SUM(po_item_rows) AS total_po_item_rows,
  SUM(receipt_item_rows) AS total_receipt_item_rows,
  SUM(movement_wrong_material_rows) AS total_movement_wrong_material_rows
FROM tmp_prod_domain_wrong_profiles
WHERE po_item_rows + receipt_item_rows + sr_item_rows + division_request_item_rows + fulfillment_item_rows
    + movement_rows + division_monthly_item_rows + warehouse_monthly_item_rows
    + division_daily_item_rows + warehouse_daily_item_rows > 0;

SELECT
  catalog_id,
  profile_key,
  catalog_name,
  brand_name,
  catalog_line_kind,
  item_id,
  catalog_material_id,
  expected_material_id,
  expected_material_code,
  expected_material_name,
  po_item_rows,
  receipt_item_rows,
  sr_item_rows,
  division_request_item_rows,
  fulfillment_item_rows,
  movement_rows,
  movement_wrong_material_rows,
  division_monthly_item_rows,
  division_monthly_material_rows,
  warehouse_monthly_item_rows,
  warehouse_monthly_material_rows,
  division_daily_item_rows,
  division_daily_material_rows,
  warehouse_daily_item_rows,
  warehouse_daily_material_rows
FROM tmp_prod_domain_wrong_profiles
WHERE po_item_rows + receipt_item_rows + sr_item_rows + division_request_item_rows + fulfillment_item_rows
    + movement_rows + division_monthly_item_rows + warehouse_monthly_item_rows
    + division_daily_item_rows + warehouse_daily_item_rows > 0
ORDER BY catalog_name ASC, brand_name ASC, catalog_id ASC;

SELECT
  'Jalankan SQL repair berikut setelah review selesai:' AS note,
  'sql/2026-06-03c_production_domain_root_cause_repair.sql' AS repair_sql;
