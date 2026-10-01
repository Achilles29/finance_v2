SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-04a_void_adjustment_legacy_monthly_audit.sql
-- Tujuan :
-- 1) Audit artefak lot yang bersumber dari adjustment berstatus VOID
-- 2) Audit row stok bulanan legacy yang masih memecah domain ITEM/MATERIAL
-- 3) Menjadi bahan review sebelum repair manual dijalankan
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_void_adjustment_lot_artifacts;
DROP TEMPORARY TABLE IF EXISTS tmp_monthly_item_backed_material_rows;
DROP TEMPORARY TABLE IF EXISTS tmp_monthly_null_profile_legacy_rows;

CREATE TEMPORARY TABLE tmp_void_adjustment_lot_artifacts AS
SELECT
  a.id AS adjustment_id,
  a.adjustment_no,
  a.status AS adjustment_status,
  l.id AS lot_id,
  l.location_scope,
  l.division_id,
  l.destination_type,
  l.item_id,
  l.material_id,
  l.profile_key,
  l.qty_in,
  l.qty_out,
  l.qty_balance,
  l.unit_cost,
  l.status AS lot_status,
  l.source_line_id,
  al.qty_adjustment_plus_content,
  al.unit_cost AS adjustment_unit_cost,
  CASE WHEN COALESCE(l.qty_balance, 0) <> 0 THEN 1 ELSE 0 END AS has_nonzero_balance,
  CASE WHEN UPPER(COALESCE(l.status, 'OPEN')) <> 'CLOSED' THEN 1 ELSE 0 END AS not_closed,
  CASE WHEN ABS(COALESCE(l.unit_cost, 0)) >= 10000 THEN 1 ELSE 0 END AS suspicious_unit_cost
FROM inv_material_fifo_lot l
JOIN inv_stock_adjustment a
  ON a.id = l.source_id
 AND l.source_table = 'inv_stock_adjustment'
LEFT JOIN inv_stock_adjustment_line al
  ON al.id = l.source_line_id
WHERE a.status = 'VOID';

CREATE TEMPORARY TABLE tmp_monthly_item_backed_material_rows AS
SELECT
  'DIVISION' AS scope_name,
  s.id AS row_id,
  s.month_key,
  s.division_id,
  s.destination_type,
  s.stock_domain,
  s.identity_key,
  s.item_id,
  i.item_name,
  i.material_id AS target_material_id,
  m.material_name AS target_material_name,
  s.profile_key,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_content_per_buy,
  s.closing_qty_buy,
  s.closing_qty_content,
  s.avg_cost_per_content,
  s.total_value
FROM inv_division_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = i.material_id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0
UNION ALL
SELECT
  'WAREHOUSE' AS scope_name,
  s.id AS row_id,
  s.month_key,
  NULL AS division_id,
  'GUDANG' AS destination_type,
  s.stock_domain,
  s.identity_key,
  s.item_id,
  i.item_name,
  i.material_id AS target_material_id,
  m.material_name AS target_material_name,
  s.profile_key,
  s.profile_name,
  s.profile_brand,
  s.profile_description,
  s.buy_uom_id,
  s.content_uom_id,
  s.profile_content_per_buy,
  s.closing_qty_buy,
  s.closing_qty_content,
  s.avg_cost_per_content,
  s.total_value
FROM inv_warehouse_monthly_stock s
JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_material m ON m.id = i.material_id
WHERE UPPER(COALESCE(s.stock_domain, 'ITEM')) = 'ITEM'
  AND COALESCE(i.material_id, 0) > 0;

CREATE TEMPORARY TABLE tmp_monthly_null_profile_legacy_rows AS
SELECT
  'DIVISION' AS scope_name,
  l.id AS legacy_row_id,
  l.month_key,
  l.division_id,
  l.destination_type,
  l.item_id AS legacy_item_id,
  l.material_id AS legacy_material_id,
  l.buy_uom_id AS legacy_buy_uom_id,
  l.content_uom_id AS legacy_content_uom_id,
  l.profile_content_per_buy AS legacy_content_per_buy,
  l.closing_qty_buy AS legacy_closing_qty_buy,
  l.closing_qty_content AS legacy_closing_qty_content,
  l.avg_cost_per_content AS legacy_avg_cost_per_content,
  l.total_value AS legacy_total_value,
  c.id AS canonical_row_id,
  c.item_id AS canonical_item_id,
  c.material_id AS canonical_material_id,
  c.profile_key AS canonical_profile_key,
  c.profile_name AS canonical_profile_name,
  c.profile_brand AS canonical_profile_brand,
  c.profile_description AS canonical_profile_description,
  c.profile_expired_date AS canonical_profile_expired_date,
  c.profile_content_per_buy AS canonical_content_per_buy,
  c.buy_uom_id AS canonical_buy_uom_id,
  c.content_uom_id AS canonical_content_uom_id,
  c.profile_buy_uom_code AS canonical_buy_uom_code,
  c.profile_content_uom_code AS canonical_content_uom_code
FROM inv_division_monthly_stock l
JOIN inv_division_monthly_stock c
  ON c.month_key = l.month_key
 AND c.division_id = l.division_id
 AND c.destination_type = l.destination_type
 AND COALESCE(c.material_id, 0) = COALESCE(l.material_id, 0)
 AND COALESCE(c.content_uom_id, 0) = COALESCE(l.content_uom_id, 0)
 AND c.id <> l.id
 AND COALESCE(c.profile_key, '') <> ''
WHERE UPPER(COALESCE(l.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(l.profile_key, '') = ''
UNION ALL
SELECT
  'WAREHOUSE' AS scope_name,
  l.id AS legacy_row_id,
  l.month_key,
  NULL AS division_id,
  'GUDANG' AS destination_type,
  l.item_id AS legacy_item_id,
  l.material_id AS legacy_material_id,
  l.buy_uom_id AS legacy_buy_uom_id,
  l.content_uom_id AS legacy_content_uom_id,
  l.profile_content_per_buy AS legacy_content_per_buy,
  l.closing_qty_buy AS legacy_closing_qty_buy,
  l.closing_qty_content AS legacy_closing_qty_content,
  l.avg_cost_per_content AS legacy_avg_cost_per_content,
  l.total_value AS legacy_total_value,
  c.id AS canonical_row_id,
  c.item_id AS canonical_item_id,
  c.material_id AS canonical_material_id,
  c.profile_key AS canonical_profile_key,
  c.profile_name AS canonical_profile_name,
  c.profile_brand AS canonical_profile_brand,
  c.profile_description AS canonical_profile_description,
  c.profile_expired_date AS canonical_profile_expired_date,
  c.profile_content_per_buy AS canonical_content_per_buy,
  c.buy_uom_id AS canonical_buy_uom_id,
  c.content_uom_id AS canonical_content_uom_id,
  c.profile_buy_uom_code AS canonical_buy_uom_code,
  c.profile_content_uom_code AS canonical_content_uom_code
FROM inv_warehouse_monthly_stock l
JOIN inv_warehouse_monthly_stock c
  ON c.month_key = l.month_key
 AND COALESCE(c.material_id, 0) = COALESCE(l.material_id, 0)
 AND COALESCE(c.content_uom_id, 0) = COALESCE(l.content_uom_id, 0)
 AND c.id <> l.id
 AND COALESCE(c.profile_key, '') <> ''
WHERE UPPER(COALESCE(l.stock_domain, 'ITEM')) = 'MATERIAL'
  AND COALESCE(l.profile_key, '') = '';

SELECT
  COUNT(*) AS total_void_adjustment_lots,
  SUM(has_nonzero_balance) AS void_lots_with_nonzero_balance,
  SUM(not_closed) AS void_lots_not_closed,
  SUM(suspicious_unit_cost) AS void_lots_with_suspicious_unit_cost
FROM tmp_void_adjustment_lot_artifacts;

SELECT
  COUNT(*) AS total_item_backed_material_monthly_rows,
  SUM(CASE WHEN scope_name = 'DIVISION' THEN 1 ELSE 0 END) AS division_rows,
  SUM(CASE WHEN scope_name = 'WAREHOUSE' THEN 1 ELSE 0 END) AS warehouse_rows,
  ROUND(SUM(COALESCE(closing_qty_content, 0)), 4) AS total_closing_qty_content,
  ROUND(SUM(COALESCE(total_value, 0)), 2) AS total_closing_value
FROM tmp_monthly_item_backed_material_rows;

SELECT
  COUNT(*) AS total_null_profile_legacy_monthly_rows,
  SUM(CASE WHEN scope_name = 'DIVISION' THEN 1 ELSE 0 END) AS division_rows,
  SUM(CASE WHEN scope_name = 'WAREHOUSE' THEN 1 ELSE 0 END) AS warehouse_rows,
  ROUND(SUM(COALESCE(legacy_closing_qty_content, 0)), 4) AS total_legacy_closing_qty_content,
  ROUND(SUM(COALESCE(legacy_total_value, 0)), 2) AS total_legacy_closing_value
FROM tmp_monthly_null_profile_legacy_rows;

SELECT *
FROM tmp_void_adjustment_lot_artifacts
ORDER BY adjustment_id, lot_id;

SELECT *
FROM tmp_monthly_item_backed_material_rows
ORDER BY scope_name, month_key, target_material_name, item_name, row_id;

SELECT *
FROM tmp_monthly_null_profile_legacy_rows
ORDER BY scope_name, month_key, legacy_material_id, legacy_row_id;

SELECT
  'Jalankan SQL repair berikut setelah review selesai:' AS note,
  'sql/2026-06-04b_void_adjustment_legacy_monthly_repair.sql' AS repair_sql;
