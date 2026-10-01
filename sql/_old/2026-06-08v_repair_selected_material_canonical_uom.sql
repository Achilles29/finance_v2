SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08v_repair_selected_material_canonical_uom.sql
-- Tujuan :
-- 1) Menormalkan UOM beli kanonik untuk material yang sudah diputuskan:
--    - TELUR               -> KG / BUTIR
--    - AIR ISI ULANG GALON -> GLN / ML
--    - KECAP MANIS         -> BTL / ML
--    - AIR                 -> JRG / ML
--    - PISANG KITCHEN      -> SSR / BUAH
-- 2) Menjaga content_per_buy per profile tetap hidup bila memang valid
--    (contoh TELUR 17/18 butir per KG, PISANG 18/19/20/22 buah per SSR)
-- 3) Menghitung ulang qty_buy turunan dari qty_content pada tabel aktif
--    agar struktur buy/content konsisten ke depan
--
-- Catatan penting:
-- - Script ini memang intervensi data
-- - Script ini sengaja TIDAK mengubah qty_content sebagai source of truth
-- - qty_buy aktif akan dihitung ulang dari qty_content / content_per_buy
-- - Untuk AIR, script ini akan membuat UOM baru code=JRG bila belum ada
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair selected canonical material buy UOM 2026-06-08';

INSERT INTO mst_uom (code, name, description, is_active)
SELECT 'JRG', 'JERIGEN', 'UOM beli kanonik untuk AIR jerigen', 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM mst_uom
  WHERE code = 'JRG'
);

DROP TEMPORARY TABLE IF EXISTS tmp_selected_material_canonical;
CREATE TEMPORARY TABLE tmp_selected_material_canonical AS
SELECT
  m.id AS material_id,
  m.material_name,
  bu.id AS canonical_buy_uom_id,
  bu.code AS canonical_buy_uom_code,
  cu.id AS canonical_content_uom_id,
  cu.code AS canonical_content_uom_code,
  x.default_content_per_buy
FROM (
  SELECT 'TELUR' AS material_name, 'KG' AS buy_uom_code, 'BUTIR' AS content_uom_code, 17.000000 AS default_content_per_buy
  UNION ALL SELECT 'AIR ISI ULANG GALON', 'GLN', 'ML', 19000.000000
  UNION ALL SELECT 'KECAP MANIS', 'BTL', 'ML', 1500.000000
  UNION ALL SELECT 'AIR', 'JRG', 'ML', 20000.000000
  UNION ALL SELECT 'PISANG KITCHEN', 'SSR', 'BUAH', 18.000000
) x
JOIN mst_material m ON m.material_name = x.material_name
JOIN mst_uom bu ON bu.code = x.buy_uom_code
JOIN mst_uom cu ON cu.code = x.content_uom_code;

ALTER TABLE tmp_selected_material_canonical
  ADD PRIMARY KEY (material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_selected_catalog_snapshot;
CREATE TEMPORARY TABLE tmp_selected_catalog_snapshot AS
SELECT
  c.id,
  c.material_id,
  COALESCE(c.profile_key, '') AS profile_key,
  c.buy_uom_id AS old_buy_uom_id,
  c.content_uom_id AS old_content_uom_id,
  ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6) AS canonical_content_per_buy
FROM mst_purchase_catalog c
JOIN tmp_selected_material_canonical d ON d.material_id = c.material_id;

ALTER TABLE tmp_selected_catalog_snapshot
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_selected_catalog_profile (material_id, profile_key);

-- ------------------------------------------------------------
-- Backup affected rows
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_mst_item;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_mst_item AS
SELECT *
FROM mst_item
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_mst_purchase_catalog;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_mst_purchase_catalog AS
SELECT *
FROM mst_purchase_catalog
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_division_monthly_stock;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_division_monthly_stock AS
SELECT *
FROM inv_division_monthly_stock
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_warehouse_monthly_stock;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_warehouse_monthly_stock AS
SELECT *
FROM inv_warehouse_monthly_stock
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_division_stock_opening_snapshot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_division_stock_opening_snapshot AS
SELECT *
FROM inv_division_stock_opening_snapshot
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot AS
SELECT *
FROM inv_warehouse_stock_opening_snapshot
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_stock_movement_log;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_stock_movement_log AS
SELECT *
FROM inv_stock_movement_log
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_material_fifo_lot;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_material_fifo_lot AS
SELECT *
FROM inv_material_fifo_lot
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_material_fifo_issue_log;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_material_fifo_issue_log AS
SELECT *
FROM inv_material_fifo_issue_log
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_inv_stock_adjustment_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_inv_stock_adjustment_line AS
SELECT *
FROM inv_stock_adjustment_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_division_request_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_division_request_line AS
SELECT *
FROM pur_division_request_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_store_request_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_store_request_line AS
SELECT *
FROM pur_store_request_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_store_request_fulfillment_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_store_request_fulfillment_line AS
SELECT *
FROM pur_store_request_fulfillment_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_purchase_order_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_purchase_order_line AS
SELECT *
FROM pur_purchase_order_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

DROP TEMPORARY TABLE IF EXISTS tmp_uom_fix_backup_pur_purchase_receipt_line;
CREATE TEMPORARY TABLE tmp_uom_fix_backup_pur_purchase_receipt_line AS
SELECT *
FROM pur_purchase_receipt_line
WHERE material_id IN (SELECT material_id FROM tmp_selected_material_canonical);

-- ------------------------------------------------------------
-- A. Normalize master
-- ------------------------------------------------------------
UPDATE mst_item i
JOIN tmp_selected_material_canonical d ON d.material_id = i.material_id
SET
  i.buy_uom_id = d.canonical_buy_uom_id,
  i.content_uom_id = d.canonical_content_uom_id,
  i.content_per_buy = d.default_content_per_buy,
  i.updated_at = CURRENT_TIMESTAMP;

UPDATE mst_purchase_catalog c
JOIN tmp_selected_material_canonical d ON d.material_id = c.material_id
SET
  c.buy_uom_id = d.canonical_buy_uom_id,
  c.content_uom_id = d.canonical_content_uom_id,
  c.content_per_buy = ROUND(COALESCE(NULLIF(c.content_per_buy, 0), d.default_content_per_buy), 6),
  c.updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- B. Normalize active stock/read tables with qty_content as truth
-- ------------------------------------------------------------
UPDATE inv_division_monthly_stock s
JOIN tmp_selected_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_selected_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_selected_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_selected_material_canonical d ON d.material_id = s.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = s.material_id
 AND cs.profile_key = COALESCE(s.profile_key, '')
SET
  s.buy_uom_id = d.canonical_buy_uom_id,
  s.content_uom_id = d.canonical_content_uom_id,
  s.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  s.profile_buy_uom_code = d.canonical_buy_uom_code,
  s.profile_content_uom_code = d.canonical_content_uom_code,
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(s.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_stock_movement_log l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

UPDATE inv_stock_adjustment_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.available_qty_buy = ROUND(COALESCE(l.available_qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.note = LEFT(TRIM(CONCAT(COALESCE(l.note, ''), CASE WHEN COALESCE(l.note, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- C. Normalize FIFO lot / issue log from old buy qty to new buy qty
-- ------------------------------------------------------------
UPDATE inv_material_fifo_lot f
JOIN tmp_selected_material_canonical d ON d.material_id = f.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = f.material_id
 AND cs.profile_key = COALESCE(f.profile_key, '')
SET
  f.qty_in = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_in, 0)
        ELSE COALESCE(f.qty_in, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_out = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_out, 0)
        ELSE COALESCE(f.qty_out, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.qty_balance = ROUND(
    (
      CASE
        WHEN COALESCE(f.buy_uom_id, 0) = COALESCE(f.content_uom_id, 0) THEN COALESCE(f.qty_balance, 0)
        ELSE COALESCE(f.qty_balance, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  f.buy_uom_id = d.canonical_buy_uom_id,
  f.content_uom_id = d.canonical_content_uom_id,
  f.updated_at = CURRENT_TIMESTAMP;

UPDATE inv_material_fifo_issue_log l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.issue_qty = ROUND(
    (
      CASE
        WHEN COALESCE(l.buy_uom_id, 0) = COALESCE(l.content_uom_id, 0) THEN COALESCE(l.issue_qty, 0)
        ELSE COALESCE(l.issue_qty, 0) * COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy)
      END
    ) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0),
    4
  ),
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

-- ------------------------------------------------------------
-- D. Normalize request / PO / receipt
-- ------------------------------------------------------------
UPDATE pur_division_request_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_requested = ROUND(COALESCE(l.qty_content_requested, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_approved = ROUND(COALESCE(l.qty_content_approved, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.qty_buy_fulfilled = ROUND(COALESCE(l.qty_content_fulfilled, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_store_request_fulfillment_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.profile_content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 6),
  l.profile_buy_uom_code = d.canonical_buy_uom_code,
  l.profile_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy_posted = ROUND(COALESCE(l.qty_content_posted, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.profile_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_order_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.content_per_buy = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 6),
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 8),
  l.snapshot_buy_uom_code = d.canonical_buy_uom_code,
  l.snapshot_content_uom_code = d.canonical_content_uom_code,
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), NULLIF(l.content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

UPDATE pur_purchase_receipt_line l
JOIN tmp_selected_material_canonical d ON d.material_id = l.material_id
LEFT JOIN tmp_selected_catalog_snapshot cs
  ON cs.material_id = l.material_id
 AND cs.profile_key = COALESCE(l.profile_key, '')
SET
  l.buy_uom_id = d.canonical_buy_uom_id,
  l.content_uom_id = d.canonical_content_uom_id,
  l.conversion_factor_to_content = ROUND(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 8),
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0) / NULLIF(COALESCE(NULLIF(cs.canonical_content_per_buy, 0), d.default_content_per_buy), 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ------------------------------------------------------------
-- Summary
-- ------------------------------------------------------------
SELECT 'mst_item_rows_repaired' AS metric, COUNT(*) AS total FROM tmp_uom_fix_backup_mst_item
UNION ALL
SELECT 'mst_purchase_catalog_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_mst_purchase_catalog
UNION ALL
SELECT 'inv_division_monthly_stock_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_division_monthly_stock
UNION ALL
SELECT 'inv_warehouse_monthly_stock_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_warehouse_monthly_stock
UNION ALL
SELECT 'inv_division_stock_opening_snapshot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_division_stock_opening_snapshot
UNION ALL
SELECT 'inv_warehouse_stock_opening_snapshot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_warehouse_stock_opening_snapshot
UNION ALL
SELECT 'inv_stock_movement_log_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_stock_movement_log
UNION ALL
SELECT 'inv_material_fifo_lot_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_material_fifo_lot
UNION ALL
SELECT 'inv_material_fifo_issue_log_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_material_fifo_issue_log
UNION ALL
SELECT 'inv_stock_adjustment_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_inv_stock_adjustment_line
UNION ALL
SELECT 'pur_division_request_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_division_request_line
UNION ALL
SELECT 'pur_store_request_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_store_request_line
UNION ALL
SELECT 'pur_store_request_fulfillment_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_store_request_fulfillment_line
UNION ALL
SELECT 'pur_purchase_order_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_purchase_order_line
UNION ALL
SELECT 'pur_purchase_receipt_line_rows_repaired', COUNT(*) FROM tmp_uom_fix_backup_pur_purchase_receipt_line;
