SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06k_backfill_missing_division_balance_from_fifo.sql
-- Tujuan :
-- 1) Menambahkan row inv_division_stock_balance yang hilang,
--    tetapi saldo FIFO divisi masih positif
-- 2) Fokus hanya INSERT missing row, tidak menimpa row balance yang sudah ada
-- 3) Dipakai untuk menyelaraskan projection balance dengan sumber FIFO
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Backfill missing division balance from FIFO 2026-06-06';

DROP TEMPORARY TABLE IF EXISTS tmp_div_balance_fifo_truth;
CREATE TEMPORARY TABLE tmp_div_balance_fifo_truth AS
SELECT
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, 0) AS item_id,
  COALESCE(l.material_id, 0) AS material_id,
  COALESCE(l.buy_uom_id, 0) AS buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) AS qty_content_balance,
  ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END), 2) AS total_value,
  ROUND(
    CASE
      WHEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END) > 0
      THEN SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance * l.unit_cost ELSE 0 END)
           / SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END)
      ELSE 0
    END,
    6
  ) AS avg_cost_per_content,
  MAX(l.receipt_line_id) AS last_receipt_line_id
FROM inv_material_fifo_lot l
WHERE l.location_scope = 'DIVISION'
GROUP BY
  l.division_id,
  l.destination_type,
  COALESCE(l.item_id, 0),
  COALESCE(l.material_id, 0),
  COALESCE(l.buy_uom_id, 0),
  l.content_uom_id,
  COALESCE(l.profile_key, '')
HAVING ROUND(SUM(CASE WHEN l.qty_balance > 0 THEN l.qty_balance ELSE 0 END), 4) > 0;

ALTER TABLE tmp_div_balance_fifo_truth
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_div_balance_missing;
CREATE TEMPORARY TABLE tmp_div_balance_missing AS
SELECT f.*
FROM tmp_div_balance_fifo_truth f
LEFT JOIN inv_division_stock_balance b
  ON b.division_id = f.division_id
 AND b.destination_type = f.destination_type
 AND COALESCE(b.item_id, 0) = f.item_id
 AND COALESCE(b.material_id, 0) = f.material_id
 AND COALESCE(b.buy_uom_id, 0) = f.buy_uom_id
 AND b.content_uom_id = f.content_uom_id
 AND COALESCE(b.profile_key, '') = f.profile_key
WHERE b.id IS NULL;

ALTER TABLE tmp_div_balance_missing
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_div_balance_missing_meta;
CREATE TEMPORARY TABLE tmp_div_balance_missing_meta AS
SELECT
  f.division_id,
  f.destination_type,
  f.item_id,
  f.material_id,
  f.buy_uom_id,
  f.content_uom_id,
  f.profile_key,
  COALESCE(
    NULLIF((SELECT c.catalog_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1), ''),
    NULLIF((SELECT c.catalog_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1), ''),
    NULLIF((SELECT i.item_name FROM mst_item i WHERE i.id = NULLIF(f.item_id, 0) LIMIT 1), ''),
    NULLIF((SELECT m.material_name FROM mst_material m WHERE m.id = NULLIF(f.material_id, 0) LIMIT 1), ''),
    'UNNAMED PROFILE'
  ) AS profile_name,
  COALESCE(
    NULLIF((SELECT c.brand_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1), ''),
    NULLIF((SELECT c.brand_name FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1), ''),
    'NO MERK'
  ) AS profile_brand,
  NULL AS profile_description,
  NULL AS profile_expired_date,
  CASE
    WHEN NULLIF(f.buy_uom_id, 0) IS NOT NULL AND f.buy_uom_id = f.content_uom_id THEN 1.000000
    ELSE COALESCE(
      (SELECT c.content_per_buy FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') AND c.is_active = 1 LIMIT 1),
      (SELECT c.content_per_buy FROM mst_purchase_catalog c WHERE c.profile_key = NULLIF(f.profile_key, '') LIMIT 1),
      1.000000
    )
  END AS profile_content_per_buy,
  (SELECT u.code FROM mst_uom u WHERE u.id = NULLIF(f.buy_uom_id, 0) LIMIT 1) AS profile_buy_uom_code,
  (SELECT u.code FROM mst_uom u WHERE u.id = f.content_uom_id LIMIT 1) AS profile_content_uom_code
FROM tmp_div_balance_missing f;

ALTER TABLE tmp_div_balance_missing_meta
  ADD PRIMARY KEY (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key);

INSERT INTO inv_division_stock_balance (
  division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id,
  qty_buy_balance, qty_content_balance, profile_key, profile_name, profile_brand,
  profile_description, profile_expired_date, profile_content_per_buy,
  profile_buy_uom_code, profile_content_uom_code, avg_cost_per_content,
  last_receipt_line_id, notes
)
SELECT
  f.division_id,
  f.destination_type,
  NULLIF(f.item_id, 0),
  NULLIF(f.material_id, 0),
  NULLIF(f.buy_uom_id, 0),
  f.content_uom_id,
  CASE
    WHEN COALESCE(meta.profile_content_per_buy, 1) > 0
      THEN ROUND(f.qty_content_balance / meta.profile_content_per_buy, 4)
    ELSE f.qty_content_balance
  END AS qty_buy_balance,
  f.qty_content_balance,
  NULLIF(f.profile_key, ''),
  meta.profile_name,
  meta.profile_brand,
  meta.profile_description,
  meta.profile_expired_date,
  meta.profile_content_per_buy,
  meta.profile_buy_uom_code,
  meta.profile_content_uom_code,
  f.avg_cost_per_content,
  f.last_receipt_line_id,
  @repair_tag
FROM tmp_div_balance_missing f
JOIN tmp_div_balance_missing_meta meta
  ON meta.division_id = f.division_id
 AND meta.destination_type = f.destination_type
 AND meta.item_id = f.item_id
 AND meta.material_id = f.material_id
 AND meta.buy_uom_id = f.buy_uom_id
 AND meta.content_uom_id = f.content_uom_id
 AND meta.profile_key = f.profile_key;

SELECT
  'backfilled_balance_rows' AS metric,
  COUNT(*) AS total_rows,
  ROUND(SUM(qty_content_balance), 4) AS qty_content_balance,
  ROUND(SUM(total_value), 2) AS total_value
FROM tmp_div_balance_missing;

COMMIT;
