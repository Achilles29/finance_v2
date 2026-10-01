SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06g_item_centric_safe_material_to_item_candidates.sql
-- Tujuan :
-- 1) Migrasi TERKONTROL row stock_domain='MATERIAL' -> 'ITEM'
--    hanya untuk kandidat yang aman
-- 2) Kandidat aman = row item-linked (item_id ada) dan BELUM punya
--    twin ITEM exact identity yang sama
-- 3) Fokus pada state table / snapshot, TIDAK menyentuh movement log
--    dan TIDAK menyentuh rollup turunan
--
-- Ruang lingkup yang disentuh:
-- - inv_division_monthly_stock
-- - inv_warehouse_monthly_stock
-- - inv_stock_opening_snapshot
-- - inv_division_stock_opening_snapshot
-- - inv_warehouse_stock_opening_snapshot
--
-- Yang sengaja TIDAK disentuh:
-- - inv_stock_movement_log
-- - inv_division_daily_rollup / inv_warehouse_daily_rollup
-- - inv_stock_adjustment_line
--
-- Alasan:
-- - movement log adalah histori sumber rebuild, perlu fase merge khusus
-- - rollup harian adalah turunan / bisa direbuild
-- - adjustment draft/live sebaiknya ikut sembuh dari script compatibility
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Safe item-centric MATERIAL->ITEM candidate migration 2026-06-06';

-- ------------------------------------------------------------
-- Helper:
-- identity_key baru mengikuti formula aplikasi:
-- - kalau profile_key ada, pakai profile_key
-- - kalau tidak ada, hash exact identity tanpa stock_domain
-- ------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_division_monthly_material_candidates;
CREATE TEMPORARY TABLE tmp_division_monthly_material_candidates AS
SELECT
  s.id,
  s.month_key,
  s.division_id,
  s.destination_type,
  CASE
    WHEN s.profile_key IS NOT NULL AND TRIM(s.profile_key) <> '' THEN s.profile_key
    ELSE SHA2(CONCAT_WS('|',
      CAST(COALESCE(s.item_id, 0) AS CHAR),
      CAST(COALESCE(s.material_id, 0) AS CHAR),
      CAST(COALESCE(s.buy_uom_id, 0) AS CHAR),
      CAST(COALESCE(s.content_uom_id, 0) AS CHAR),
      UPPER(TRIM(COALESCE(s.profile_name, ''))),
      UPPER(TRIM(COALESCE(s.profile_brand, ''))),
      UPPER(TRIM(COALESCE(s.profile_description, ''))),
      CAST(ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS DECIMAL(18,6)),
      COALESCE(DATE_FORMAT(s.profile_expired_date, '%Y-%m-%d'), '')
    ), 256)
  END AS next_identity_key
FROM inv_division_monthly_stock s
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL;

ALTER TABLE tmp_division_monthly_material_candidates
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_div_monthly_target (month_key, division_id, destination_type, next_identity_key);

DROP TEMPORARY TABLE IF EXISTS tmp_safe_division_monthly_material;
CREATE TEMPORARY TABLE tmp_safe_division_monthly_material AS
SELECT c.id, c.next_identity_key
FROM tmp_division_monthly_material_candidates c
LEFT JOIN inv_division_monthly_stock i
  ON i.month_key = c.month_key
 AND i.division_id = c.division_id
 AND i.destination_type = c.destination_type
 AND i.identity_key = c.next_identity_key
 AND i.id <> c.id
LEFT JOIN (
  SELECT month_key, division_id, destination_type, next_identity_key, COUNT(*) AS candidate_count
  FROM tmp_division_monthly_material_candidates
  GROUP BY month_key, division_id, destination_type, next_identity_key
) dup
  ON dup.month_key = c.month_key
 AND dup.division_id = c.division_id
 AND dup.destination_type = c.destination_type
 AND dup.next_identity_key = c.next_identity_key
WHERE i.id IS NULL
  AND COALESCE(dup.candidate_count, 0) = 1;

ALTER TABLE tmp_safe_division_monthly_material
  ADD PRIMARY KEY (id);

UPDATE inv_division_monthly_stock s
JOIN tmp_safe_division_monthly_material t ON t.id = s.id
SET s.stock_domain = 'ITEM',
    s.identity_key = t.next_identity_key,
    s.updated_at = CURRENT_TIMESTAMP,
    s.notes = LEFT(TRIM(CONCAT(
      COALESCE(s.notes, ''),
      CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | division monthly safe candidate'
    )), 255)
WHERE s.stock_domain = 'MATERIAL';

DROP TEMPORARY TABLE IF EXISTS tmp_warehouse_monthly_material_candidates;
CREATE TEMPORARY TABLE tmp_warehouse_monthly_material_candidates AS
SELECT
  s.id,
  s.month_key,
  CASE
    WHEN s.profile_key IS NOT NULL AND TRIM(s.profile_key) <> '' THEN s.profile_key
    ELSE SHA2(CONCAT_WS('|',
      CAST(COALESCE(s.item_id, 0) AS CHAR),
      CAST(COALESCE(s.material_id, 0) AS CHAR),
      CAST(COALESCE(s.buy_uom_id, 0) AS CHAR),
      CAST(COALESCE(s.content_uom_id, 0) AS CHAR),
      UPPER(TRIM(COALESCE(s.profile_name, ''))),
      UPPER(TRIM(COALESCE(s.profile_brand, ''))),
      UPPER(TRIM(COALESCE(s.profile_description, ''))),
      CAST(ROUND(COALESCE(s.profile_content_per_buy, 1), 6) AS DECIMAL(18,6)),
      COALESCE(DATE_FORMAT(s.profile_expired_date, '%Y-%m-%d'), '')
    ), 256)
  END AS next_identity_key
FROM inv_warehouse_monthly_stock s
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL;

ALTER TABLE tmp_warehouse_monthly_material_candidates
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_wh_monthly_target (month_key, next_identity_key);

DROP TEMPORARY TABLE IF EXISTS tmp_safe_warehouse_monthly_material;
CREATE TEMPORARY TABLE tmp_safe_warehouse_monthly_material AS
SELECT c.id, c.next_identity_key
FROM tmp_warehouse_monthly_material_candidates c
LEFT JOIN inv_warehouse_monthly_stock i
  ON i.month_key = c.month_key
 AND i.identity_key = c.next_identity_key
 AND i.id <> c.id
LEFT JOIN (
  SELECT month_key, next_identity_key, COUNT(*) AS candidate_count
  FROM tmp_warehouse_monthly_material_candidates
  GROUP BY month_key, next_identity_key
) dup
  ON dup.month_key = c.month_key
 AND dup.next_identity_key = c.next_identity_key
WHERE i.id IS NULL
  AND COALESCE(dup.candidate_count, 0) = 1;

ALTER TABLE tmp_safe_warehouse_monthly_material
  ADD PRIMARY KEY (id);

UPDATE inv_warehouse_monthly_stock s
JOIN tmp_safe_warehouse_monthly_material t ON t.id = s.id
SET s.stock_domain = 'ITEM',
    s.identity_key = t.next_identity_key,
    s.updated_at = CURRENT_TIMESTAMP,
    s.notes = LEFT(TRIM(CONCAT(
      COALESCE(s.notes, ''),
      CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | warehouse monthly safe candidate'
    )), 255)
WHERE s.stock_domain = 'MATERIAL';

-- ------------------------------------------------------------
-- Opening snapshots:
-- ubah hanya yang item-linked dan belum punya twin ITEM exact
-- Tidak ada identity_key, jadi cukup stock_domain + notes/update
-- ------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS tmp_safe_unified_opening_material;
CREATE TEMPORARY TABLE tmp_safe_unified_opening_material AS
SELECT s.id
FROM inv_stock_opening_snapshot s
LEFT JOIN inv_stock_opening_snapshot i
  ON i.snapshot_month = s.snapshot_month
 AND i.stock_scope = s.stock_scope
 AND i.division_id <=> s.division_id
 AND i.destination_type <=> s.destination_type
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id = s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND i.stock_domain = 'ITEM'
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL
  AND i.id IS NULL;

ALTER TABLE tmp_safe_unified_opening_material
  ADD PRIMARY KEY (id);

UPDATE inv_stock_opening_snapshot s
JOIN tmp_safe_unified_opening_material t ON t.id = s.id
SET s.stock_domain = 'ITEM',
    s.updated_at = CURRENT_TIMESTAMP,
    s.notes = LEFT(TRIM(CONCAT(
      COALESCE(s.notes, ''),
      CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | unified opening safe candidate'
    )), 255)
WHERE s.stock_domain = 'MATERIAL';

DROP TEMPORARY TABLE IF EXISTS tmp_safe_division_opening_material;
CREATE TEMPORARY TABLE tmp_safe_division_opening_material AS
SELECT s.id
FROM inv_division_stock_opening_snapshot s
LEFT JOIN inv_division_stock_opening_snapshot i
  ON i.snapshot_month = s.snapshot_month
 AND i.division_id = s.division_id
 AND i.destination_type = s.destination_type
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id = s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND i.stock_domain = 'ITEM'
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL
  AND i.id IS NULL;

ALTER TABLE tmp_safe_division_opening_material
  ADD PRIMARY KEY (id);

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_safe_division_opening_material t ON t.id = s.id
SET s.stock_domain = 'ITEM',
    s.updated_at = CURRENT_TIMESTAMP,
    s.notes = LEFT(TRIM(CONCAT(
      COALESCE(s.notes, ''),
      CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | division opening safe candidate'
    )), 255)
WHERE s.stock_domain = 'MATERIAL';

DROP TEMPORARY TABLE IF EXISTS tmp_safe_warehouse_opening_material;
CREATE TEMPORARY TABLE tmp_safe_warehouse_opening_material AS
SELECT s.id
FROM inv_warehouse_stock_opening_snapshot s
LEFT JOIN inv_warehouse_stock_opening_snapshot i
  ON i.snapshot_month = s.snapshot_month
 AND i.item_id <=> s.item_id
 AND i.material_id <=> s.material_id
 AND i.buy_uom_id <=> s.buy_uom_id
 AND i.content_uom_id = s.content_uom_id
 AND i.profile_key <=> s.profile_key
 AND i.stock_domain = 'ITEM'
WHERE s.stock_domain = 'MATERIAL'
  AND s.item_id IS NOT NULL
  AND i.id IS NULL;

ALTER TABLE tmp_safe_warehouse_opening_material
  ADD PRIMARY KEY (id);

UPDATE inv_warehouse_stock_opening_snapshot s
JOIN tmp_safe_warehouse_opening_material t ON t.id = s.id
SET s.stock_domain = 'ITEM',
    s.updated_at = CURRENT_TIMESTAMP,
    s.notes = LEFT(TRIM(CONCAT(
      COALESCE(s.notes, ''),
      CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
      @repair_tag,
      ' | warehouse opening safe candidate'
    )), 255)
WHERE s.stock_domain = 'MATERIAL';

COMMIT;

-- ------------------------------------------------------------
-- Post-check
-- ------------------------------------------------------------
SELECT 'div_monthly_migrated_rows' AS metric, COUNT(*) AS total
FROM tmp_safe_division_monthly_material

UNION ALL

SELECT 'wh_monthly_migrated_rows', COUNT(*)
FROM tmp_safe_warehouse_monthly_material

UNION ALL

SELECT 'unified_opening_migrated_rows', COUNT(*)
FROM tmp_safe_unified_opening_material

UNION ALL

SELECT 'division_opening_migrated_rows', COUNT(*)
FROM tmp_safe_division_opening_material

UNION ALL

SELECT 'warehouse_opening_migrated_rows', COUNT(*)
FROM tmp_safe_warehouse_opening_material

UNION ALL

SELECT 'div_monthly_material_item_linked_remaining_safe', COUNT(*)
FROM tmp_division_monthly_material_candidates c
LEFT JOIN inv_division_monthly_stock i
  ON i.month_key = c.month_key
 AND i.division_id = c.division_id
 AND i.destination_type = c.destination_type
 AND i.identity_key = c.next_identity_key
 AND i.id <> c.id
LEFT JOIN (
  SELECT month_key, division_id, destination_type, next_identity_key, COUNT(*) AS candidate_count
  FROM tmp_division_monthly_material_candidates
  GROUP BY month_key, division_id, destination_type, next_identity_key
) dup
  ON dup.month_key = c.month_key
 AND dup.division_id = c.division_id
 AND dup.destination_type = c.destination_type
 AND dup.next_identity_key = c.next_identity_key
WHERE i.id IS NULL
  AND COALESCE(dup.candidate_count, 0) = 1

UNION ALL

SELECT 'wh_monthly_material_item_linked_remaining_safe', COUNT(*)
FROM tmp_warehouse_monthly_material_candidates c
LEFT JOIN inv_warehouse_monthly_stock i
  ON i.month_key = c.month_key
 AND i.identity_key = c.next_identity_key
 AND i.id <> c.id
LEFT JOIN (
  SELECT month_key, next_identity_key, COUNT(*) AS candidate_count
  FROM tmp_warehouse_monthly_material_candidates
  GROUP BY month_key, next_identity_key
) dup
  ON dup.month_key = c.month_key
 AND dup.next_identity_key = c.next_identity_key
WHERE i.id IS NULL
  AND COALESCE(dup.candidate_count, 0) = 1;
