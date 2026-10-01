SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07i_cleanup_pos_stock_commit_material_monthly_mismatch_groups.sql
-- Tujuan :
-- 1) Membersihkan mismatch bahan baku POS yang sumber utamanya
--    saldo monthly divisi stale / pecah dibanding closing movement
-- 2) Rebuild row latest inv_division_monthly_stock hanya untuk
--    grup mismatch (division + destination_group + item + material)
-- 3) Menyamakan "Stok Divisi" dengan truth dari movement log
--    tanpa menyentuh FIFO lot dan tanpa mass reclass ITEM/MATERIAL
--
-- Prinsip:
-- - inv_stock_movement_log dianggap source of truth untuk closing aktif
-- - yang disentuh hanya latest monthly row pada grup mismatch
-- - row monthly stale dihapus lalu dibangun ulang dari latest movement
-- - stock_domain hasil rebuild dipaksa ITEM (item-centric)
--
-- Catatan:
-- - Script ini fokus ke mismatch bahan baku di /pos/stock-commit-audit
-- - Setelah jalankan script ini, refresh halaman audit
-- - Jika masih ada sisa mismatch, itu kandidat manual reclass / drift transaksi
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Cleanup POS stock-commit monthly mismatch groups 2026-06-07';
SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- ------------------------------------------------------------
-- A. Latest closing movement per exact identity divisi
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_latest_movement_identity;
CREATE TEMPORARY TABLE tmp_pos_mm_latest_movement_identity AS
SELECT
  l.id AS movement_id,
  l.division_id,
  COALESCE(l.destination_type, 'OTHER') AS destination_type,
  CASE
    WHEN COALESCE(l.destination_type, 'OTHER') IN ('BAR_EVENT', 'KITCHEN_EVENT') THEN 'EVENT'
    ELSE 'REGULER'
  END AS destination_group,
  l.item_id,
  COALESCE(l.material_id, mi.material_id) AS material_id,
  l.buy_uom_id,
  l.content_uom_id,
  COALESCE(l.profile_key, '') AS profile_key,
  l.profile_name,
  l.profile_brand,
  l.profile_description,
  l.profile_expired_date,
  ROUND(COALESCE(l.profile_content_per_buy, 1), 6) AS profile_content_per_buy,
  l.profile_buy_uom_code,
  l.profile_content_uom_code,
  ROUND(COALESCE(l.qty_buy_after, 0), 4) AS qty_buy_after,
  ROUND(COALESCE(l.qty_content_after, 0), 4) AS qty_content_after,
  ROUND(COALESCE(l.unit_cost, 0), 6) AS movement_unit_cost,
  l.movement_date,
  CASE
    WHEN COALESCE(l.profile_key, '') <> '' THEN l.profile_key
    ELSE SHA2(CONCAT_WS('|',
      CAST(COALESCE(l.item_id, 0) AS CHAR),
      CAST(COALESCE(COALESCE(l.material_id, mi.material_id), 0) AS CHAR),
      CAST(COALESCE(l.buy_uom_id, 0) AS CHAR),
      CAST(COALESCE(l.content_uom_id, 0) AS CHAR),
      UPPER(TRIM(COALESCE(l.profile_name, ''))),
      UPPER(TRIM(COALESCE(l.profile_brand, ''))),
      UPPER(TRIM(COALESCE(l.profile_description, ''))),
      CAST(ROUND(COALESCE(l.profile_content_per_buy, 1), 6) AS CHAR),
      COALESCE(DATE_FORMAT(l.profile_expired_date, '%Y-%m-%d'), '')
    ), 256)
  END AS next_identity_key
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
    COALESCE(x.profile_key, '') AS profile_key,
    MAX(x.id) AS keep_id
  FROM inv_stock_movement_log x
  LEFT JOIN mst_item xi ON xi.id = x.item_id
  WHERE x.movement_scope = 'DIVISION'
    AND x.movement_date <= CURDATE()
    AND COALESCE(x.item_id, 0) > 0
    AND COALESCE(x.material_id, xi.material_id, 0) > 0
  GROUP BY
    x.division_id,
    COALESCE(x.destination_type, 'OTHER'),
    x.item_id,
    COALESCE(x.material_id, xi.material_id),
    x.buy_uom_id,
    x.content_uom_id,
    COALESCE(x.profile_key, '')
) latest ON latest.keep_id = l.id;

ALTER TABLE tmp_pos_mm_latest_movement_identity
  ADD PRIMARY KEY (movement_id),
  ADD KEY idx_tmp_pos_mm_move_group (division_id, destination_group, item_id, material_id),
  ADD KEY idx_tmp_pos_mm_move_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key(32));

-- ------------------------------------------------------------
-- B. Latest monthly row per exact identity divisi
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_latest_monthly_identity;
CREATE TEMPORARY TABLE tmp_pos_mm_latest_monthly_identity AS
SELECT
  s.id,
  s.month_key,
  s.division_id,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  CASE
    WHEN COALESCE(s.destination_type, 'OTHER') IN ('BAR_EVENT', 'KITCHEN_EVENT') THEN 'EVENT'
    ELSE 'REGULER'
  END AS destination_group,
  s.item_id,
  COALESCE(s.material_id, mi.material_id) AS material_id,
  s.buy_uom_id,
  s.content_uom_id,
  COALESCE(s.profile_key, '') AS profile_key,
  ROUND(COALESCE(s.closing_qty_buy, 0), 4) AS closing_qty_buy,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS closing_qty_content,
  ROUND(COALESCE(s.avg_cost_per_content, 0), 6) AS avg_cost_per_content
FROM inv_division_monthly_stock s
LEFT JOIN mst_item mi ON mi.id = s.item_id
JOIN (
  SELECT
    division_id,
    COALESCE(destination_type, 'OTHER') AS destination_type,
    identity_key,
    MAX(month_key) AS keep_month
  FROM inv_division_monthly_stock
  WHERE month_key <= @target_month
    AND COALESCE(item_id, 0) > 0
    AND COALESCE(material_id, 0) > 0
  GROUP BY
    division_id,
    COALESCE(destination_type, 'OTHER'),
    identity_key
) latest ON latest.division_id = s.division_id
       AND latest.destination_type = COALESCE(s.destination_type, 'OTHER')
       AND latest.identity_key = s.identity_key
       AND latest.keep_month = s.month_key;

ALTER TABLE tmp_pos_mm_latest_monthly_identity
  ADD PRIMARY KEY (id),
  ADD KEY idx_tmp_pos_mm_monthly_group (division_id, destination_group, item_id, material_id),
  ADD KEY idx_tmp_pos_mm_monthly_identity (division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key(32));

-- ------------------------------------------------------------
-- C. Aggregate compare-key ala POS stock-commit-audit
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_group_keys;
CREATE TEMPORARY TABLE tmp_pos_mm_group_keys AS
SELECT division_id, destination_group, item_id, material_id
FROM tmp_pos_mm_latest_movement_identity
UNION
SELECT division_id, destination_group, item_id, material_id
FROM tmp_pos_mm_latest_monthly_identity;

ALTER TABLE tmp_pos_mm_group_keys
  ADD KEY idx_tmp_pos_mm_group_keys (division_id, destination_group, item_id, material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_group_movement;
CREATE TEMPORARY TABLE tmp_pos_mm_group_movement AS
SELECT
  division_id,
  destination_group,
  item_id,
  material_id,
  ROUND(SUM(qty_buy_after), 4) AS qty_buy_after,
  ROUND(SUM(qty_content_after), 4) AS qty_content_after
FROM tmp_pos_mm_latest_movement_identity
GROUP BY division_id, destination_group, item_id, material_id;

ALTER TABLE tmp_pos_mm_group_movement
  ADD KEY idx_tmp_pos_mm_group_movement (division_id, destination_group, item_id, material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_group_monthly;
CREATE TEMPORARY TABLE tmp_pos_mm_group_monthly AS
SELECT
  division_id,
  destination_group,
  item_id,
  material_id,
  ROUND(SUM(closing_qty_buy), 4) AS closing_qty_buy,
  ROUND(SUM(closing_qty_content), 4) AS closing_qty_content
FROM tmp_pos_mm_latest_monthly_identity
GROUP BY division_id, destination_group, item_id, material_id;

ALTER TABLE tmp_pos_mm_group_monthly
  ADD KEY idx_tmp_pos_mm_group_monthly (division_id, destination_group, item_id, material_id);

DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_mismatch_groups;
CREATE TEMPORARY TABLE tmp_pos_mm_mismatch_groups AS
SELECT
  k.division_id,
  k.destination_group,
  k.item_id,
  k.material_id,
  ROUND(COALESCE(m.closing_qty_buy, 0), 4) AS monthly_qty_buy,
  ROUND(COALESCE(m.closing_qty_content, 0), 4) AS monthly_qty_content,
  ROUND(COALESCE(g.qty_buy_after, 0), 4) AS movement_qty_buy,
  ROUND(COALESCE(g.qty_content_after, 0), 4) AS movement_qty_content
FROM tmp_pos_mm_group_keys k
LEFT JOIN tmp_pos_mm_group_monthly m
  ON m.division_id = k.division_id
 AND m.destination_group = k.destination_group
 AND m.item_id = k.item_id
 AND m.material_id = k.material_id
LEFT JOIN tmp_pos_mm_group_movement g
  ON g.division_id = k.division_id
 AND g.destination_group = k.destination_group
 AND g.item_id = k.item_id
 AND g.material_id = k.material_id
WHERE ABS(COALESCE(m.closing_qty_content, 0) - COALESCE(g.qty_content_after, 0)) >= 0.0001
   OR ABS(COALESCE(m.closing_qty_buy, 0) - COALESCE(g.qty_buy_after, 0)) >= 0.0001;

ALTER TABLE tmp_pos_mm_mismatch_groups
  ADD KEY idx_tmp_pos_mm_mismatch_groups (division_id, destination_group, item_id, material_id);

-- ------------------------------------------------------------
-- D. Backup row latest monthly yang akan dibersihkan
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_monthly_delete_ids;
CREATE TEMPORARY TABLE tmp_pos_mm_monthly_delete_ids AS
SELECT m.id
FROM tmp_pos_mm_latest_monthly_identity m
JOIN tmp_pos_mm_mismatch_groups g
  ON g.division_id = m.division_id
 AND g.destination_group = m.destination_group
 AND g.item_id = m.item_id
 AND g.material_id = m.material_id;

ALTER TABLE tmp_pos_mm_monthly_delete_ids
  ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_pos_mm_monthly_deleted_backup;
CREATE TEMPORARY TABLE tmp_pos_mm_monthly_deleted_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_pos_mm_monthly_delete_ids d ON d.id = s.id;

-- ------------------------------------------------------------
-- E. Hapus latest monthly row stale pada grup mismatch
-- ------------------------------------------------------------
DELETE s
FROM inv_division_monthly_stock s
JOIN tmp_pos_mm_monthly_delete_ids d ON d.id = s.id;

-- ------------------------------------------------------------
-- F. Build ulang latest monthly row dari movement truth
--    Hanya untuk grup mismatch, dan hanya insert row non-zero
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
  'LIVE',
  CONCAT(@repair_tag, ' | rebuilt latest monthly from movement'),
  'ITEM'
FROM tmp_pos_mm_latest_movement_identity t
JOIN tmp_pos_mm_mismatch_groups g
  ON g.division_id = t.division_id
 AND g.destination_group = t.destination_group
 AND g.item_id = t.item_id
 AND g.material_id = t.material_id
WHERE ABS(COALESCE(t.qty_content_after, 0)) >= 0.0001
   OR ABS(COALESCE(t.qty_buy_after, 0)) >= 0.0001;

COMMIT;

-- ------------------------------------------------------------
-- G. Ringkasan pasca-cleanup
-- ------------------------------------------------------------
SELECT 'mismatch_groups_cleaned' AS metric, COUNT(*) AS total
FROM tmp_pos_mm_mismatch_groups

UNION ALL

SELECT 'monthly_rows_deleted', COUNT(*)
FROM tmp_pos_mm_monthly_deleted_backup

UNION ALL

SELECT 'movement_identity_rows_rebuilt', COUNT(*)
FROM tmp_pos_mm_latest_movement_identity t
JOIN tmp_pos_mm_mismatch_groups g
  ON g.division_id = t.division_id
 AND g.destination_group = t.destination_group
 AND g.item_id = t.item_id
 AND g.material_id = t.material_id
WHERE ABS(COALESCE(t.qty_content_after, 0)) >= 0.0001
   OR ABS(COALESCE(t.qty_buy_after, 0)) >= 0.0001

UNION ALL

SELECT 'remaining_item_linked_material_monthly_material_domain', COUNT(*)
FROM inv_division_monthly_stock
WHERE COALESCE(item_id, 0) > 0
  AND COALESCE(material_id, 0) > 0
  AND COALESCE(stock_domain, 'ITEM') = 'MATERIAL';
