SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07k_fix_remaining_pos_material_mismatches.sql
-- Tujuan :
-- 1) Menutup sisa mismatch bahan baku POS yang tinggal 2 identity
--    spesifik di BAR Reguler:
--    - AIR MINERAL GALON (item_id 220 / material_id 2)
--    - SINGLE ORIGIN    (item_id 135 / material_id 173)
-- 2) Menormalkan movement identity legacy yang masih kotor
-- 3) Rebuild ulang row monthly current month hanya untuk identity ini
--
-- Catatan:
-- - Script ini SENGAJA sempit, bukan cleanup global
-- - Tidak menyentuh FIFO lot
-- - Tidak menyentuh item/material lain di luar identity target
-- ============================================================

START TRANSACTION;

SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @repair_tag := 'Fix remaining POS material mismatches 2026-06-07';

-- ------------------------------------------------------------
-- A. Normalisasi movement legacy: SINGLE ORIGIN
--    Purchase row lama belum punya material_id, padahal item 135 terhubung
--    ke material 173. Backfill supaya compare/rebuild bisa membaca identity
--    yang sama.
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log l
JOIN mst_item i ON i.id = l.item_id
SET
  l.material_id = i.material_id,
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | backfill material_id from item'
  )), 255)
WHERE l.movement_scope = 'DIVISION'
  AND l.division_id = 2
  AND COALESCE(l.destination_type, 'OTHER') = 'BAR'
  AND l.item_id = 135
  AND COALESCE(l.material_id, 0) = 0
  AND COALESCE(i.material_id, 0) = 173;

-- ------------------------------------------------------------
-- B. Normalisasi movement legacy: AIR MINERAL GALON item 220
--    Identity lama masih pecah karena brand/content_per_buy tidak konsisten.
--    Samakan metadata supaya history direbuild sebagai satu identity.
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log
SET
  profile_brand = 'NO MERK',
  profile_description = NULL,
  profile_content_per_buy = 1.000000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | normalize profile metadata'
  )), 255)
WHERE movement_scope = 'DIVISION'
  AND division_id = 2
  AND COALESCE(destination_type, 'OTHER') = 'BAR'
  AND item_id = 220
  AND COALESCE(material_id, 0) = 2;

-- ------------------------------------------------------------
-- C. Backup & purge monthly current month untuk identity target
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_remaining_material_monthly_backup;
CREATE TEMPORARY TABLE tmp_pos_remaining_material_monthly_backup AS
SELECT *
FROM inv_division_monthly_stock
WHERE month_key = @target_month
  AND division_id = 2
  AND COALESCE(destination_type, 'OTHER') = 'BAR'
  AND (
    (item_id = 220 AND COALESCE(material_id, 0) = 2)
    OR
    (item_id = 135 AND COALESCE(material_id, 0) = 173)
  );

DELETE FROM inv_division_monthly_stock
WHERE month_key = @target_month
  AND division_id = 2
  AND COALESCE(destination_type, 'OTHER') = 'BAR'
  AND (
    (item_id = 220 AND COALESCE(material_id, 0) = 2)
    OR
    (item_id = 135 AND COALESCE(material_id, 0) = 173)
  );

-- ------------------------------------------------------------
-- D. Ambil latest movement per exact identity setelah normalisasi
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_remaining_material_latest_move;
CREATE TEMPORARY TABLE tmp_pos_remaining_material_latest_move AS
SELECT
  l.id AS movement_id,
  l.movement_date,
  l.division_id,
  COALESCE(l.destination_type, 'OTHER') AS destination_type,
  l.item_id,
  COALESCE(l.material_id, i.material_id) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  l.profile_name,
  l.profile_brand,
  l.profile_description,
  l.profile_expired_date,
  ROUND(COALESCE(NULLIF(l.profile_content_per_buy, 0), 1), 6) AS profile_content_per_buy,
  l.profile_buy_uom_code,
  l.profile_content_uom_code,
  ROUND(COALESCE(l.qty_buy_after, 0), 4) AS qty_buy_after,
  ROUND(COALESCE(l.qty_content_after, 0), 4) AS qty_content_after,
  ROUND(COALESCE(l.unit_cost, 0), 6) AS movement_unit_cost,
  CASE
    WHEN COALESCE(l.profile_key, '') <> '' THEN l.profile_key
    ELSE SHA2(CONCAT_WS('|',
      CAST(COALESCE(l.item_id, 0) AS CHAR),
      CAST(COALESCE(COALESCE(l.material_id, i.material_id), 0) AS CHAR),
      CAST(COALESCE(l.buy_uom_id, 0) AS CHAR),
      CAST(COALESCE(l.content_uom_id, 0) AS CHAR),
      UPPER(TRIM(COALESCE(l.profile_name, ''))),
      UPPER(TRIM(COALESCE(l.profile_brand, ''))),
      UPPER(TRIM(COALESCE(l.profile_description, ''))),
      CAST(ROUND(COALESCE(NULLIF(l.profile_content_per_buy, 0), 1), 6) AS CHAR),
      COALESCE(DATE_FORMAT(l.profile_expired_date, '%Y-%m-%d'), '')
    ), 256)
  END AS next_identity_key
FROM inv_stock_movement_log l
LEFT JOIN mst_item i ON i.id = l.item_id
JOIN (
  SELECT
    x.division_id,
    COALESCE(x.destination_type, 'OTHER') AS destination_type,
    x.item_id,
    COALESCE(x.material_id, xi.material_id) AS material_id,
    x.buy_uom_id,
    x.content_uom_id,
    COALESCE(x.profile_key, '') AS profile_key,
    UPPER(TRIM(COALESCE(x.profile_name, ''))) AS profile_name_norm,
    UPPER(TRIM(COALESCE(x.profile_brand, ''))) AS profile_brand_norm,
    UPPER(TRIM(COALESCE(x.profile_description, ''))) AS profile_description_norm,
    ROUND(COALESCE(NULLIF(x.profile_content_per_buy, 0), 1), 6) AS profile_content_per_buy,
    MAX(x.id) AS keep_id
  FROM inv_stock_movement_log x
  LEFT JOIN mst_item xi ON xi.id = x.item_id
  WHERE x.movement_scope = 'DIVISION'
    AND x.movement_date <= CURDATE()
    AND x.division_id = 2
    AND COALESCE(x.destination_type, 'OTHER') = 'BAR'
    AND (
      (x.item_id = 220 AND COALESCE(x.material_id, xi.material_id, 0) = 2)
      OR
      (x.item_id = 135 AND COALESCE(x.material_id, xi.material_id, 0) = 173)
    )
  GROUP BY
    x.division_id,
    COALESCE(x.destination_type, 'OTHER'),
    x.item_id,
    COALESCE(x.material_id, xi.material_id),
    x.buy_uom_id,
    x.content_uom_id,
    COALESCE(x.profile_key, ''),
    UPPER(TRIM(COALESCE(x.profile_name, ''))),
    UPPER(TRIM(COALESCE(x.profile_brand, ''))),
    UPPER(TRIM(COALESCE(x.profile_description, ''))),
    ROUND(COALESCE(NULLIF(x.profile_content_per_buy, 0), 1), 6)
) latest ON latest.keep_id = l.id;

-- ------------------------------------------------------------
-- E. Insert ulang monthly current month dari movement truth
-- ------------------------------------------------------------
INSERT INTO inv_division_monthly_stock (
  month_key, identity_key, division_id, destination_type,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  source_mode, notes, stock_domain
)
SELECT
  @target_month,
  t.next_identity_key,
  t.division_id,
  t.destination_type,
  t.item_id,
  t.material_id,
  t.buy_uom_id,
  t.content_uom_id,
  NULLIF(t.profile_key, ''),
  t.profile_name,
  t.profile_brand,
  t.profile_description,
  t.profile_expired_date,
  t.profile_content_per_buy,
  t.profile_buy_uom_code,
  t.profile_content_uom_code,
  0,
  0,
  0,
  t.qty_buy_after,
  t.qty_content_after,
  t.movement_unit_cost,
  ROUND(t.qty_content_after * t.movement_unit_cost, 2),
  0,
  0,
  t.movement_date,
  CURRENT_TIMESTAMP,
  'REBUILD',
  CONCAT(@repair_tag, ' | rebuilt targeted monthly from movement'),
  'ITEM'
FROM tmp_pos_remaining_material_latest_move t
WHERE ABS(COALESCE(t.qty_content_after, 0)) >= 0.0001
   OR ABS(COALESCE(t.qty_buy_after, 0)) >= 0.0001;

COMMIT;

-- ------------------------------------------------------------
-- F. Ringkasan
-- ------------------------------------------------------------
SELECT 'monthly_rows_backed_up' AS metric, COUNT(*) AS total
FROM tmp_pos_remaining_material_monthly_backup
UNION ALL
SELECT 'monthly_rows_rebuilt', COUNT(*)
FROM tmp_pos_remaining_material_latest_move
WHERE ABS(COALESCE(qty_content_after, 0)) >= 0.0001
   OR ABS(COALESCE(qty_buy_after, 0)) >= 0.0001;
