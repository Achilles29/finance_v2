SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07h_repair_pos_stock_commit_material_mismatch_from_movement.sql
-- Tujuan :
-- 1) Menormalkan legacy profile metadata untuk profile_key bahan baku
-- 2) Menyamakan inv_division_monthly_stock dengan latest closing movement log
-- 3) Mengurangi mismatch bahan baku di /pos/stock-commit-audit
-- 4) Membantu kasus adjustment divisi yang gagal karena monthly stale
--
-- Prinsip:
-- - movement log dianggap source of truth untuk closing saldo aktif
-- - script ini TIDAK melakukan reclass ITEM/MATERIAL massal
-- - script ini TIDAK menyentuh FIFO lot qty_balance
-- - script ini fokus ke legacy dirty profile metadata dan monthly stale
--
-- Catatan:
-- - Jalankan dulu di local/staging bila memungkinkan
-- - Setelah script ini, refresh /pos/stock-commit-audit
-- - Jika masih ada legacy mismatch tertentu, lanjutkan manual reclass per profile
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair POS stock-commit material mismatch from movement 2026-06-07';
SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- ------------------------------------------------------------
-- A. Ambil canonical profile metadata dari purchase catalog aktif/latest
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mismatch_profile_catalog;
CREATE TEMPORARY TABLE tmp_pos_mismatch_profile_catalog AS
SELECT
  c.profile_key,
  c.item_id,
  c.material_id,
  c.buy_uom_id,
  c.content_uom_id,
  c.catalog_name AS canonical_profile_name,
  COALESCE(c.brand_name, '') AS canonical_profile_brand,
  COALESCE(c.line_description, '') AS canonical_profile_description,
  ROUND(COALESCE(NULLIF(c.content_per_buy, 0), 1), 6) AS canonical_profile_content_per_buy,
  bu.code AS canonical_profile_buy_uom_code,
  cu.code AS canonical_profile_content_uom_code
FROM mst_purchase_catalog c
LEFT JOIN mst_uom bu ON bu.id = c.buy_uom_id
LEFT JOIN mst_uom cu ON cu.id = c.content_uom_id
JOIN (
  SELECT profile_key, MAX(id) AS keep_id
  FROM mst_purchase_catalog
  WHERE profile_key IS NOT NULL
    AND TRIM(profile_key) <> ''
    AND COALESCE(item_id, 0) > 0
    AND COALESCE(material_id, 0) > 0
    AND COALESCE(is_active, 1) = 1
  GROUP BY profile_key
) x ON x.keep_id = c.id;

ALTER TABLE tmp_pos_mismatch_profile_catalog
  ADD PRIMARY KEY (profile_key),
  ADD KEY idx_tmp_pos_profile_item_material (item_id, material_id, buy_uom_id, content_uom_id);

-- ------------------------------------------------------------
-- B. Normalisasi movement log untuk profile_key item-linked
--    Ini membersihkan kasus profile_key sama tapi nama/brand/desc drift.
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log ml
JOIN tmp_pos_mismatch_profile_catalog cp ON cp.profile_key = ml.profile_key
SET
  ml.item_id = CASE
    WHEN COALESCE(ml.item_id, 0) = 0 AND COALESCE(cp.item_id, 0) > 0 THEN cp.item_id
    ELSE ml.item_id
  END,
  ml.material_id = CASE
    WHEN COALESCE(ml.material_id, 0) = 0 AND COALESCE(cp.material_id, 0) > 0 THEN cp.material_id
    ELSE ml.material_id
  END,
  ml.buy_uom_id = CASE
    WHEN COALESCE(ml.buy_uom_id, 0) = 0 AND COALESCE(cp.buy_uom_id, 0) > 0 THEN cp.buy_uom_id
    ELSE ml.buy_uom_id
  END,
  ml.content_uom_id = CASE
    WHEN COALESCE(ml.content_uom_id, 0) = 0 AND COALESCE(cp.content_uom_id, 0) > 0 THEN cp.content_uom_id
    ELSE ml.content_uom_id
  END,
  ml.profile_name = cp.canonical_profile_name,
  ml.profile_brand = NULLIF(cp.canonical_profile_brand, ''),
  ml.profile_description = NULLIF(cp.canonical_profile_description, ''),
  ml.profile_content_per_buy = cp.canonical_profile_content_per_buy,
  ml.profile_buy_uom_code = cp.canonical_profile_buy_uom_code,
  ml.profile_content_uom_code = cp.canonical_profile_content_uom_code,
  ml.notes = LEFT(TRIM(CONCAT(
    COALESCE(ml.notes, ''),
    CASE WHEN COALESCE(ml.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | movement profile metadata normalized'
  )), 255)
WHERE ml.profile_key IS NOT NULL
  AND TRIM(ml.profile_key) <> '';

-- ------------------------------------------------------------
-- C. Normalisasi monthly divisi metadata untuk row item-linked yang punya profile_key
-- ------------------------------------------------------------
UPDATE inv_division_monthly_stock s
JOIN tmp_pos_mismatch_profile_catalog cp ON cp.profile_key = s.profile_key
SET
  s.item_id = CASE
    WHEN COALESCE(s.item_id, 0) = 0 AND COALESCE(cp.item_id, 0) > 0 THEN cp.item_id
    ELSE s.item_id
  END,
  s.material_id = CASE
    WHEN COALESCE(s.material_id, 0) = 0 AND COALESCE(cp.material_id, 0) > 0 THEN cp.material_id
    ELSE s.material_id
  END,
  s.buy_uom_id = CASE
    WHEN COALESCE(s.buy_uom_id, 0) = 0 AND COALESCE(cp.buy_uom_id, 0) > 0 THEN cp.buy_uom_id
    ELSE s.buy_uom_id
  END,
  s.content_uom_id = CASE
    WHEN COALESCE(s.content_uom_id, 0) = 0 AND COALESCE(cp.content_uom_id, 0) > 0 THEN cp.content_uom_id
    ELSE s.content_uom_id
  END,
  s.profile_name = cp.canonical_profile_name,
  s.profile_brand = NULLIF(cp.canonical_profile_brand, ''),
  s.profile_description = NULLIF(cp.canonical_profile_description, ''),
  s.profile_content_per_buy = cp.canonical_profile_content_per_buy,
  s.profile_buy_uom_code = cp.canonical_profile_buy_uom_code,
  s.profile_content_uom_code = cp.canonical_profile_content_uom_code,
  s.identity_key = cp.profile_key,
  s.updated_at = CURRENT_TIMESTAMP,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | monthly profile metadata normalized'
  )), 255)
WHERE s.profile_key IS NOT NULL
  AND TRIM(s.profile_key) <> '';

-- ------------------------------------------------------------
-- D. Latest closing movement per exact identity divisi
--    Ini dijadikan truth untuk saldo closing monthly aktif.
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mismatch_latest_division_movement;
CREATE TEMPORARY TABLE tmp_pos_mismatch_latest_division_movement AS
SELECT
  l.id AS movement_id,
  l.movement_date,
  l.division_id,
  COALESCE(l.destination_type, 'OTHER') AS destination_type,
  l.item_id,
  COALESCE(l.material_id, mi.material_id) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  l.profile_key,
  l.profile_name,
  l.profile_brand,
  l.profile_description,
  l.profile_expired_date,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
  l.profile_buy_uom_code,
  l.profile_content_uom_code,
  ROUND(COALESCE(l.qty_buy_after, 0), 4) AS qty_buy_after,
  ROUND(COALESCE(l.qty_content_after, 0), 4) AS qty_content_after,
  ROUND(COALESCE(l.unit_cost, 0), 6) AS movement_unit_cost
FROM inv_stock_movement_log l
LEFT JOIN mst_item mi ON mi.id = l.item_id
JOIN (
  SELECT
    x.division_id,
    COALESCE(x.destination_type, 'OTHER') AS destination_type,
    x.item_id,
    COALESCE(x.material_id, xi.material_id) AS material_id,
    x.buy_uom_id,
    x.content_uom_id,
    x.profile_key,
    MAX(x.id) AS keep_id
  FROM inv_stock_movement_log x
  LEFT JOIN mst_item xi ON xi.id = x.item_id
  WHERE x.movement_scope = 'DIVISION'
    AND COALESCE(x.item_id, 0) > 0
    AND COALESCE(x.profile_key, '') <> ''
    AND COALESCE(x.material_id, xi.material_id) IS NOT NULL
  GROUP BY
    x.division_id,
    COALESCE(x.destination_type, 'OTHER'),
    x.item_id,
    COALESCE(x.material_id, xi.material_id),
    x.buy_uom_id,
    x.content_uom_id,
    x.profile_key
) lastlog
  ON lastlog.keep_id = l.id;

ALTER TABLE tmp_pos_mismatch_latest_division_movement
  ADD PRIMARY KEY (movement_id),
  ADD KEY idx_tmp_pos_div_mismatch_identity (
    division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
  );

-- ------------------------------------------------------------
-- E. Sinkron row monthly divisi existing ke latest movement closing
-- ------------------------------------------------------------
UPDATE inv_division_monthly_stock s
JOIN (
  SELECT
    t.*,
    m.id AS monthly_id
  FROM tmp_pos_mismatch_latest_division_movement t
  JOIN (
    SELECT
      division_id,
      destination_type,
      item_id,
      material_id,
      buy_uom_id,
      content_uom_id,
      profile_key,
      MAX(month_key) AS keep_month
    FROM inv_division_monthly_stock
    WHERE COALESCE(item_id, 0) > 0
      AND COALESCE(profile_key, '') <> ''
    GROUP BY
      division_id,
      destination_type,
      item_id,
      material_id,
      buy_uom_id,
      content_uom_id,
      profile_key
  ) latest_month
    ON latest_month.division_id = t.division_id
   AND latest_month.destination_type = t.destination_type
   AND latest_month.item_id <=> t.item_id
   AND latest_month.material_id <=> t.material_id
   AND latest_month.buy_uom_id <=> t.buy_uom_id
   AND latest_month.content_uom_id = t.content_uom_id
   AND latest_month.profile_key <=> t.profile_key
  JOIN inv_division_monthly_stock m
    ON m.month_key = latest_month.keep_month
   AND m.division_id = latest_month.division_id
   AND m.destination_type = latest_month.destination_type
   AND m.item_id <=> latest_month.item_id
   AND m.material_id <=> latest_month.material_id
   AND m.buy_uom_id <=> latest_month.buy_uom_id
   AND m.content_uom_id = latest_month.content_uom_id
   AND m.profile_key <=> latest_month.profile_key
) src ON src.monthly_id = s.id
SET
  s.identity_key = src.profile_key,
  s.profile_name = src.profile_name,
  s.profile_brand = src.profile_brand,
  s.profile_description = src.profile_description,
  s.profile_expired_date = src.profile_expired_date,
  s.profile_content_per_buy = src.profile_content_per_buy,
  s.profile_buy_uom_code = src.profile_buy_uom_code,
  s.profile_content_uom_code = src.profile_content_uom_code,
  s.closing_qty_buy = src.qty_buy_after,
  s.closing_qty_content = src.qty_content_after,
  s.avg_cost_per_content = CASE
    WHEN src.qty_content_after > 0 AND src.movement_unit_cost > 0 THEN src.movement_unit_cost
    ELSE COALESCE(s.avg_cost_per_content, 0)
  END,
  s.total_value = ROUND(
    src.qty_content_after * (
      CASE
        WHEN src.qty_content_after > 0 AND src.movement_unit_cost > 0 THEN src.movement_unit_cost
        ELSE COALESCE(s.avg_cost_per_content, 0)
      END
    ),
    2
  ),
  s.last_movement_date = src.movement_date,
  s.last_movement_at = CURRENT_TIMESTAMP,
  s.updated_at = CURRENT_TIMESTAMP,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | monthly closing synced from latest movement'
  )), 255);

-- ------------------------------------------------------------
-- F. Insert row monthly divisi yang belum ada, tapi movement latest sudah ada
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
  t.profile_key,
  t.division_id,
  t.destination_type,
  t.item_id,
  t.material_id,
  t.buy_uom_id,
  t.content_uom_id,
  t.profile_key,
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
  'LIVE',
  CONCAT(@repair_tag, ' | inserted monthly from latest movement'),
  'ITEM'
FROM tmp_pos_mismatch_latest_division_movement t
LEFT JOIN inv_division_monthly_stock s
  ON s.division_id = t.division_id
 AND s.destination_type = t.destination_type
 AND s.item_id <=> t.item_id
 AND s.material_id <=> t.material_id
 AND s.buy_uom_id <=> t.buy_uom_id
 AND s.content_uom_id = t.content_uom_id
 AND s.profile_key <=> t.profile_key
WHERE s.id IS NULL;

COMMIT;

-- ------------------------------------------------------------
-- G. Ringkasan pasca-repair
-- ------------------------------------------------------------
SELECT 'movement_profiles_normalized' AS metric, COUNT(*) AS total
FROM tmp_pos_mismatch_profile_catalog

UNION ALL

SELECT 'division_monthly_latest_movement_rows', COUNT(*)
FROM tmp_pos_mismatch_latest_division_movement

UNION ALL

SELECT 'division_monthly_item_linked_material_rows_remaining', COUNT(*)
FROM inv_division_monthly_stock
WHERE COALESCE(item_id, 0) > 0
  AND COALESCE(material_id, 0) > 0
  AND COALESCE(stock_domain, 'ITEM') = 'MATERIAL'

UNION ALL

SELECT 'movement_log_inconsistent_profile_name_rows', COUNT(*)
FROM inv_stock_movement_log ml
JOIN tmp_pos_mismatch_profile_catalog cp ON cp.profile_key = ml.profile_key
WHERE COALESCE(ml.profile_name, '') <> COALESCE(cp.canonical_profile_name, '');
