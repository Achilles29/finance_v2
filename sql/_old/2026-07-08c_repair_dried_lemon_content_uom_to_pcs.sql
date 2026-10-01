SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-08c_repair_dried_lemon_content_uom_to_pcs.sql
-- Tujuan :
-- 1) Memperbaiki satuan isi DRIED LEMON dari GR/GRAM menjadi
--    PCS/PIECES secara konsisten.
-- 2) Menyelaraskan master material, master item, catalog,
--    resep produk, purchase/request, stok bulanan, opening/opname,
--    FIFO lot, issue log, movement log, dan adjustment line.
-- 3) Tidak mengubah angka qty / stok / HPP. Script ini hanya
--    memperbaiki identitas UOM isi dan snapshot kode UOM isi.
--
-- Hasil audit lokal sebelum repair:
-- - mst_material DRIED LEMON id 63 content_uom_id = 11 (GR)
-- - mst_item DRIED LEMON id 61 content_uom_id = 11 (GR)
-- - mst_uom target PCS/PIECES id 1
-- - 6 recipe memakai DRIED LEMON masih uom_id GR
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair DRIED LEMON content UOM to PCS 2026-07-08';

SET @target_content_uom_id := (
  SELECT id
  FROM mst_uom
  WHERE COALESCE(is_active, 1) = 1
    AND (
      UPPER(TRIM(code)) IN ('PCS', 'PIECES', 'PC')
      OR UPPER(TRIM(name)) IN ('PCS', 'PIECE', 'PIECES')
    )
  ORDER BY
    CASE UPPER(TRIM(code))
      WHEN 'PCS' THEN 0
      WHEN 'PIECES' THEN 1
      WHEN 'PC' THEN 2
      ELSE 9
    END,
    id
  LIMIT 1
);

SET @target_content_uom_code := (
  SELECT code FROM mst_uom WHERE id = @target_content_uom_id LIMIT 1
);

-- Jika hasil ini NULL, hentikan dan buat UOM PCS/PIECES dulu.
SELECT
  @target_content_uom_id AS target_content_uom_id,
  @target_content_uom_code AS target_content_uom_code;

DROP TEMPORARY TABLE IF EXISTS tmp_dried_lemon_material;
CREATE TEMPORARY TABLE tmp_dried_lemon_material AS
SELECT id
FROM mst_material
WHERE UPPER(TRIM(material_name)) LIKE '%DRIED%LEMON%'
   OR UPPER(TRIM(material_code)) LIKE '%DRIED%LEMON%';

ALTER TABLE tmp_dried_lemon_material
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_dried_lemon_item;
CREATE TEMPORARY TABLE tmp_dried_lemon_item AS
SELECT id
FROM mst_item
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR UPPER(TRIM(item_name)) LIKE '%DRIED%LEMON%'
   OR UPPER(TRIM(item_code)) LIKE '%DRIED%LEMON%';

ALTER TABLE tmp_dried_lemon_item
  ADD PRIMARY KEY (id);

-- ------------------------------------------------------------
-- Backup sementara row yang akan disentuh.
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_mst_material;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_mst_material AS
SELECT * FROM mst_material
WHERE id IN (SELECT id FROM tmp_dried_lemon_material);

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_mst_item;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_mst_item AS
SELECT * FROM mst_item
WHERE id IN (SELECT id FROM tmp_dried_lemon_item);

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_mst_purchase_catalog;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_mst_purchase_catalog AS
SELECT * FROM mst_purchase_catalog
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item)
   OR UPPER(TRIM(catalog_name)) LIKE '%DRIED%LEMON%';

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_mst_product_recipe;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_mst_product_recipe AS
SELECT * FROM mst_product_recipe
WHERE material_item_id IN (SELECT id FROM tmp_dried_lemon_item);

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_fifo_lot;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_fifo_lot AS
SELECT * FROM inv_material_fifo_lot
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_fifo_issue_log;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_fifo_issue_log AS
SELECT * FROM inv_material_fifo_issue_log
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

DROP TEMPORARY TABLE IF EXISTS tmp_backup_dried_lemon_stock_movement_log;
CREATE TEMPORARY TABLE tmp_backup_dried_lemon_stock_movement_log AS
SELECT * FROM inv_stock_movement_log
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

-- ------------------------------------------------------------
-- A. Master data
-- ------------------------------------------------------------
UPDATE mst_material
SET
  content_uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE id IN (SELECT id FROM tmp_dried_lemon_material)
  AND content_uom_id <> @target_content_uom_id;

UPDATE mst_item
SET
  content_uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE id IN (SELECT id FROM tmp_dried_lemon_item)
  AND content_uom_id <> @target_content_uom_id;

UPDATE mst_purchase_catalog
SET
  content_uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE (
    material_id IN (SELECT id FROM tmp_dried_lemon_material)
    OR item_id IN (SELECT id FROM tmp_dried_lemon_item)
    OR UPPER(TRIM(catalog_name)) LIKE '%DRIED%LEMON%'
  )
  AND COALESCE(content_uom_id, 0) <> @target_content_uom_id;

UPDATE mst_product_recipe
SET
  uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE material_item_id IN (SELECT id FROM tmp_dried_lemon_item)
  AND uom_id <> @target_content_uom_id;

-- ------------------------------------------------------------
-- B. Purchase / request documents
-- ------------------------------------------------------------
UPDATE pur_purchase_order_line
SET
  content_uom_id = @target_content_uom_id,
  snapshot_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE pur_purchase_receipt_line
SET
  content_uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE pur_division_request_line
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE pur_store_request_line
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE pur_store_request_fulfillment_line
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

-- ------------------------------------------------------------
-- C. FIFO lot, issue log, dan movement log
-- ------------------------------------------------------------
UPDATE inv_material_fifo_lot
SET
  content_uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_material_fifo_issue_log
SET
  content_uom_id = @target_content_uom_id
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_stock_movement_log
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255)
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

-- ------------------------------------------------------------
-- D. Monthly stock, opening, opname, snapshot
-- ------------------------------------------------------------
UPDATE inv_division_monthly_stock
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_warehouse_monthly_stock
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_division_monthly_opening
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_warehouse_monthly_opening
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_division_monthly_opname
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_warehouse_monthly_opname
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_division_stock_opname
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_division_stock_opening_snapshot
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_warehouse_stock_opening_snapshot
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_stock_opening_snapshot
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

-- ------------------------------------------------------------
-- E. Adjustment dan checkpoint line aktif
-- ------------------------------------------------------------
UPDATE inv_stock_adjustment_line
SET
  content_uom_id = @target_content_uom_id,
  profile_content_uom_code = @target_content_uom_code,
  note = LEFT(TRIM(CONCAT(
    COALESCE(note, ''),
    CASE WHEN COALESCE(note, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag
  )), 255),
  updated_at = CURRENT_TIMESTAMP
WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
   OR item_id IN (SELECT id FROM tmp_dried_lemon_item);

UPDATE inv_daily_recon_checkpoint_line
SET
  uom_id = @target_content_uom_id,
  updated_at = CURRENT_TIMESTAMP
WHERE recon_domain = 'MATERIAL'
  AND (
    material_id IN (SELECT id FROM tmp_dried_lemon_material)
    OR item_id IN (SELECT id FROM tmp_dried_lemon_item)
  );

COMMIT;

-- ------------------------------------------------------------
-- Verifikasi ringkas pasca-repair
-- ------------------------------------------------------------
SELECT
  'target_uom' AS metric,
  CONCAT(@target_content_uom_id, ':', @target_content_uom_code) AS value

UNION ALL

SELECT
  'material_rows',
  CAST(COUNT(*) AS CHAR)
FROM mst_material
WHERE id IN (SELECT id FROM tmp_dried_lemon_material)

UNION ALL

SELECT
  'item_rows',
  CAST(COUNT(*) AS CHAR)
FROM mst_item
WHERE id IN (SELECT id FROM tmp_dried_lemon_item)

UNION ALL

SELECT
  'recipe_rows',
  CAST(COUNT(*) AS CHAR)
FROM mst_product_recipe
WHERE material_item_id IN (SELECT id FROM tmp_dried_lemon_item);

SELECT
  table_name,
  total_rows,
  rows_not_pcs
FROM (
  SELECT 'mst_material' AS table_name, COUNT(*) AS total_rows,
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END) AS rows_not_pcs
  FROM mst_material
  WHERE id IN (SELECT id FROM tmp_dried_lemon_material)

  UNION ALL

  SELECT 'mst_item', COUNT(*),
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM mst_item
  WHERE id IN (SELECT id FROM tmp_dried_lemon_item)

  UNION ALL

  SELECT 'mst_purchase_catalog', COUNT(*),
         SUM(CASE WHEN COALESCE(content_uom_id, 0) <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM mst_purchase_catalog
  WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
     OR item_id IN (SELECT id FROM tmp_dried_lemon_item)
     OR UPPER(TRIM(catalog_name)) LIKE '%DRIED%LEMON%'

  UNION ALL

  SELECT 'mst_product_recipe', COUNT(*),
         SUM(CASE WHEN uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM mst_product_recipe
  WHERE material_item_id IN (SELECT id FROM tmp_dried_lemon_item)

  UNION ALL

  SELECT 'inv_material_fifo_lot', COUNT(*),
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM inv_material_fifo_lot
  WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
     OR item_id IN (SELECT id FROM tmp_dried_lemon_item)

  UNION ALL

  SELECT 'inv_material_fifo_issue_log', COUNT(*),
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM inv_material_fifo_issue_log
  WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
     OR item_id IN (SELECT id FROM tmp_dried_lemon_item)

  UNION ALL

  SELECT 'inv_stock_movement_log', COUNT(*),
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM inv_stock_movement_log
  WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
     OR item_id IN (SELECT id FROM tmp_dried_lemon_item)

  UNION ALL

  SELECT 'inv_division_monthly_stock', COUNT(*),
         SUM(CASE WHEN content_uom_id <> @target_content_uom_id THEN 1 ELSE 0 END)
  FROM inv_division_monthly_stock
  WHERE material_id IN (SELECT id FROM tmp_dried_lemon_material)
     OR item_id IN (SELECT id FROM tmp_dried_lemon_item)
) audit
ORDER BY table_name;

SELECT
  m.id AS material_id,
  m.material_code,
  m.material_name,
  mu.code AS material_content_uom,
  i.id AS item_id,
  i.item_code,
  i.item_name,
  bu.code AS buy_uom,
  cu.code AS content_uom,
  i.content_per_buy
FROM mst_material m
JOIN mst_item i ON i.material_id = m.id
LEFT JOIN mst_uom mu ON mu.id = m.content_uom_id
LEFT JOIN mst_uom bu ON bu.id = i.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = i.content_uom_id
WHERE m.id IN (SELECT id FROM tmp_dried_lemon_material)
ORDER BY i.id;
