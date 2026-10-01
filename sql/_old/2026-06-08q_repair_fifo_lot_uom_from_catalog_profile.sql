SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08q_repair_fifo_lot_uom_from_catalog_profile.sql
-- Tujuan :
-- 1) Menyamakan buy_uom_id/content_uom_id lot FIFO ke purchase catalog
--    bila profile_key exact match dan material_id sama
-- 2) Menutup blocker sync monthly <- FIFO lots seperti kasus MAYONAISE
-- 3) Scope sempit: HANYA exact profile_key mismatch
--
-- Catatan:
-- - Script ini TIDAK mengubah qty lot
-- - Script ini aman karena hanya update identitas UOM/item/material
--   berdasarkan profile_key exact yang sudah ada di purchase catalog
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_fifo_lot_profile_uom_mismatch;
CREATE TEMPORARY TABLE tmp_fifo_lot_profile_uom_mismatch AS
SELECT
  l.id,
  l.profile_key,
  l.item_id AS old_item_id,
  l.material_id AS old_material_id,
  l.buy_uom_id AS old_buy_uom_id,
  l.content_uom_id AS old_content_uom_id,
  c.item_id AS new_item_id,
  c.material_id AS new_material_id,
  c.buy_uom_id AS new_buy_uom_id,
  c.content_uom_id AS new_content_uom_id
FROM inv_material_fifo_lot l
JOIN (
  SELECT c1.*
  FROM mst_purchase_catalog c1
  JOIN (
    SELECT profile_key, MAX(id) AS keep_id
    FROM mst_purchase_catalog
    WHERE COALESCE(profile_key, '') <> ''
      AND COALESCE(material_id, 0) > 0
    GROUP BY profile_key
  ) pick ON pick.keep_id = c1.id
) c
  ON c.profile_key = l.profile_key
 AND COALESCE(c.material_id, 0) = COALESCE(l.material_id, 0)
WHERE COALESCE(l.material_id, 0) > 0
  AND COALESCE(l.profile_key, '') <> ''
  AND (
    COALESCE(l.item_id, 0) <> COALESCE(c.item_id, 0)
    OR COALESCE(l.buy_uom_id, 0) <> COALESCE(c.buy_uom_id, 0)
    OR COALESCE(l.content_uom_id, 0) <> COALESCE(c.content_uom_id, 0)
  );

ALTER TABLE tmp_fifo_lot_profile_uom_mismatch
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_fifo_lot_profile_uom_mismatch_backup;
CREATE TEMPORARY TABLE tmp_fifo_lot_profile_uom_mismatch_backup AS
SELECT l.*
FROM inv_material_fifo_lot l
JOIN tmp_fifo_lot_profile_uom_mismatch t ON t.id = l.id;

UPDATE inv_material_fifo_lot l
JOIN tmp_fifo_lot_profile_uom_mismatch t ON t.id = l.id
SET
  l.item_id = t.new_item_id,
  l.material_id = t.new_material_id,
  l.buy_uom_id = t.new_buy_uom_id,
  l.content_uom_id = t.new_content_uom_id;

COMMIT;

SELECT 'fifo_lot_rows_repaired' AS metric, COUNT(*) AS total
FROM tmp_fifo_lot_profile_uom_mismatch_backup;
