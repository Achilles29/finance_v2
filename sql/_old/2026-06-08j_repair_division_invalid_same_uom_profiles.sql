SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08j_repair_division_invalid_same_uom_profiles.sql
-- Tujuan :
-- 1) Memperbaiki profile stok divisi yang kontradiktif:
--    buy_uom_id = content_uom_id tetapi profile_content_per_buy <> 1
-- 2) HANYA untuk kasus yang punya pasangan profile legacy valid
--    di mst_purchase_catalog (buy_uom_id <> content_uom_id)
-- 3) Menyamakan monthly/opening/movement/fifo dan line transaksi aktif
--    ke signature profile legacy yang valid
--
-- Catatan:
-- - Script ini sengaja sempit: hanya auto-fixable rows
-- - Row yang tidak punya legacy match tetap dibiarkan untuk audit manual
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair invalid same-UOM division stock profile 2026-06-08';

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_monthly;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_monthly AS
SELECT
    s.id AS monthly_id,
    s.month_key,
    s.division_id,
    COALESCE(s.destination_type, 'OTHER') AS destination_type,
    s.item_id,
    COALESCE(s.material_id, i.material_id) AS material_id,
    s.profile_key AS invalid_profile_key,
    s.profile_name,
    COALESCE(c.brand_name, s.profile_brand, '') AS profile_brand,
    ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
    c.id AS invalid_catalog_id
FROM inv_division_monthly_stock s
LEFT JOIN mst_item i ON i.id = s.item_id
LEFT JOIN mst_purchase_catalog c ON c.profile_key = s.profile_key
WHERE COALESCE(s.material_id, i.material_id, 0) > 0
  AND COALESCE(s.buy_uom_id, 0) = COALESCE(s.content_uom_id, 0)
  AND COALESCE(s.buy_uom_id, 0) > 0
  AND ABS(ROUND(COALESCE(s.profile_content_per_buy, 1), 6) - 1) > 0.000001;

ALTER TABLE tmp_invalid_same_uom_monthly ADD PRIMARY KEY (monthly_id);

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_fix_map;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_fix_map AS
SELECT
    t.monthly_id,
    t.division_id,
    t.destination_type,
    t.item_id,
    t.material_id,
    t.invalid_profile_key,
    t.invalid_catalog_id,
    legacy.id AS legacy_catalog_id,
    legacy.profile_key AS legacy_profile_key,
    legacy.buy_uom_id AS legacy_buy_uom_id,
    legacy.content_uom_id AS legacy_content_uom_id,
    ROUND(COALESCE(legacy.content_per_buy, 1), 6) AS legacy_content_per_buy,
    bu.code AS legacy_buy_uom_code,
    cu.code AS legacy_content_uom_code
FROM tmp_invalid_same_uom_monthly t
JOIN mst_purchase_catalog legacy
  ON legacy.id = (
    SELECT l.id
    FROM mst_purchase_catalog l
    WHERE COALESCE(l.item_id, 0) = COALESCE(t.item_id, 0)
      AND COALESCE(l.material_id, 0) = COALESCE(t.material_id, 0)
      AND UPPER(TRIM(COALESCE(l.catalog_name, ''))) = UPPER(TRIM(COALESCE(t.profile_name, '')))
      AND UPPER(TRIM(COALESCE(l.brand_name, ''))) = UPPER(TRIM(COALESCE(t.profile_brand, '')))
      AND ROUND(COALESCE(l.content_per_buy, 1), 6) = ROUND(COALESCE(t.profile_content_per_buy, 1), 6)
      AND COALESCE(l.buy_uom_id, 0) <> COALESCE(l.content_uom_id, 0)
    ORDER BY COALESCE(l.is_active, 1) DESC, l.id ASC
    LIMIT 1
  )
LEFT JOIN mst_uom bu ON bu.id = legacy.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = legacy.content_uom_id;

ALTER TABLE tmp_invalid_same_uom_fix_map ADD PRIMARY KEY (monthly_id);

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_fix_map_unique;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_fix_map_unique AS
SELECT DISTINCT
    invalid_profile_key,
    invalid_catalog_id,
    legacy_catalog_id,
    legacy_profile_key,
    legacy_buy_uom_id,
    legacy_content_uom_id,
    legacy_content_per_buy,
    legacy_buy_uom_code,
    legacy_content_uom_code
FROM tmp_invalid_same_uom_fix_map;

ALTER TABLE tmp_invalid_same_uom_fix_map_unique ADD PRIMARY KEY (invalid_profile_key);

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_monthly;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_monthly AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = s.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_opening;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_opening AS
SELECT s.*
FROM inv_division_stock_opening_snapshot s
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = s.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_movement;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_movement AS
SELECT l.*
FROM inv_stock_movement_log l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key
WHERE l.movement_scope = 'DIVISION';

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_fifo;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_fifo AS
SELECT f.*
FROM inv_material_fifo_lot f
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = f.profile_key
WHERE f.location_scope = 'DIVISION';

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_po;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_po AS
SELECT l.*
FROM pur_purchase_order_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_receipt;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_receipt AS
SELECT l.*
FROM pur_purchase_receipt_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key;

DROP TEMPORARY TABLE IF EXISTS tmp_invalid_same_uom_backup_dreq;
CREATE TEMPORARY TABLE tmp_invalid_same_uom_backup_dreq AS
SELECT l.*
FROM pur_division_request_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key;

UPDATE mst_purchase_catalog c
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_catalog_id = c.id
SET
  c.is_active = 0,
  c.notes = LEFT(TRIM(CONCAT(COALESCE(c.notes, ''), CASE WHEN COALESCE(c.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag, ' | superseded by profile_key=', m.legacy_profile_key)), 255);

UPDATE inv_division_monthly_stock s
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = s.profile_key
SET
  s.identity_key = m.legacy_profile_key,
  s.profile_key = m.legacy_profile_key,
  s.buy_uom_id = m.legacy_buy_uom_id,
  s.content_uom_id = m.legacy_content_uom_id,
  s.profile_content_per_buy = m.legacy_content_per_buy,
  s.profile_buy_uom_code = m.legacy_buy_uom_code,
  s.profile_content_uom_code = m.legacy_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = s.profile_key
SET
  s.profile_key = m.legacy_profile_key,
  s.buy_uom_id = m.legacy_buy_uom_id,
  s.content_uom_id = m.legacy_content_uom_id,
  s.profile_content_per_buy = m.legacy_content_per_buy,
  s.profile_buy_uom_code = m.legacy_buy_uom_code,
  s.profile_content_uom_code = m.legacy_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_stock_movement_log l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key
SET
  l.profile_key = m.legacy_profile_key,
  l.buy_uom_id = m.legacy_buy_uom_id,
  l.content_uom_id = m.legacy_content_uom_id,
  l.profile_content_per_buy = m.legacy_content_per_buy,
  l.profile_buy_uom_code = m.legacy_buy_uom_code,
  l.profile_content_uom_code = m.legacy_content_uom_code,
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255)
WHERE l.movement_scope = 'DIVISION';

UPDATE inv_material_fifo_lot f
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = f.profile_key
SET
  f.profile_key = m.legacy_profile_key,
  f.buy_uom_id = m.legacy_buy_uom_id,
  f.content_uom_id = m.legacy_content_uom_id,
  f.updated_at = CURRENT_TIMESTAMP
WHERE f.location_scope = 'DIVISION';

UPDATE pur_purchase_order_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key
SET
  l.profile_key = m.legacy_profile_key,
  l.buy_uom_id = m.legacy_buy_uom_id,
  l.content_uom_id = m.legacy_content_uom_id,
  l.content_per_buy = m.legacy_content_per_buy,
  l.conversion_factor_to_content = m.legacy_content_per_buy,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  l.snapshot_buy_uom_code = m.legacy_buy_uom_code,
  l.snapshot_content_uom_code = m.legacy_content_uom_code,
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_receipt_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key
SET
  l.profile_key = m.legacy_profile_key,
  l.buy_uom_id = m.legacy_buy_uom_id,
  l.content_uom_id = m.legacy_content_uom_id,
  l.conversion_factor_to_content = m.legacy_content_per_buy,
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_division_request_line l
JOIN tmp_invalid_same_uom_fix_map_unique m ON m.invalid_profile_key = l.profile_key
SET
  l.profile_key = m.legacy_profile_key,
  l.buy_uom_id = m.legacy_buy_uom_id,
  l.content_uom_id = m.legacy_content_uom_id,
  l.profile_content_per_buy = m.legacy_content_per_buy,
  l.profile_buy_uom_code = m.legacy_buy_uom_code,
  l.profile_content_uom_code = m.legacy_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(m.legacy_content_per_buy, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'mapped_invalid_profiles' AS metric, COUNT(*) AS total
FROM tmp_invalid_same_uom_fix_map_unique
UNION ALL
SELECT 'monthly_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_monthly
UNION ALL
SELECT 'opening_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_opening
UNION ALL
SELECT 'movement_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_movement
UNION ALL
SELECT 'fifo_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_fifo
UNION ALL
SELECT 'po_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_po
UNION ALL
SELECT 'receipt_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_receipt
UNION ALL
SELECT 'division_request_rows_repaired', COUNT(*) FROM tmp_invalid_same_uom_backup_dreq;
