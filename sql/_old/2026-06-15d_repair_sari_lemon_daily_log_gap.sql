SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-15d_repair_sari_lemon_daily_log_gap.sql
-- Tujuan :
-- 1) Menutup warning "Log Gap" SARI LEMON di /inventory-material-daily
-- 2) Memperbaiki histori movement fallback yang overshoot saat stok
--    FIFO tinggal sebagian / sudah habis
-- 3) Mengembalikan opening monthly profil legacy ke opening snapshot
--
-- Scope sempit:
-- - Division : 2 (BAR)
-- - Material : 243 (SARI LEMON)
-- - Item     : 225
--
-- Masalah yang diperbaiki:
-- - Profil legacy e3a... punya opening snapshot 200, tetapi monthly June
--   hasil rebuild pernah tersimpan opening = 0
-- - Beberapa movement fallback tetap mengurangi qty penuh walau saldo
--   FIFO yang tersedia hanya sebagian / sudah 0
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair SARI LEMON daily log gap 2026-06-15';
SET @target_month := '2026-06-01';
SET @division_id := 2;
SET @destination_type := 'BAR';
SET @item_id := 225;
SET @material_id := 243;
SET @legacy_profile_key := 'e3a51cb637ea7bd62c872e345b1aef20fe8631841a561a050754ccc81dd72f27';
SET @canonical_profile_key := 'eec258040b8b933b8351219e74d4fa15bbe047b57dab975f59d4447f149eb1ad';

-- ------------------------------------------------------------
-- A. Backup movement row target
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_sari_lemon_gap_movement_backup;
CREATE TEMPORARY TABLE tmp_sari_lemon_gap_movement_backup AS
SELECT *
FROM inv_stock_movement_log
WHERE id IN (1781, 2665, 10265, 10277, 10331, 10481, 10625);

-- ------------------------------------------------------------
-- B. Backup monthly row target
-- ------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_sari_lemon_gap_monthly_backup;
CREATE TEMPORARY TABLE tmp_sari_lemon_gap_monthly_backup AS
SELECT *
FROM inv_division_monthly_stock
WHERE month_key = @target_month
  AND division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND item_id = @item_id
  AND COALESCE(material_id, 0) = @material_id
  AND COALESCE(profile_key, '') IN (@legacy_profile_key, @canonical_profile_key);

-- ------------------------------------------------------------
-- C. Betulkan delta fallback legacy profile
--    - id 1781 harusnya consume sisa 5, bukan 15
--    - id 2665 harusnya tidak mengurangi lagi (stok sudah 0)
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log
SET
  qty_buy_delta = -5.0000,
  qty_content_delta = -5.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | fallback legacy consume available 5 only'
  )), 255)
WHERE id = 1781;

UPDATE inv_stock_movement_log
SET
  qty_buy_delta = 0.0000,
  qty_content_delta = 0.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | rollback overshoot after stock already zero'
  )), 255)
WHERE id = 2665;

-- ------------------------------------------------------------
-- D. Betulkan delta fallback canonical profile
--    Hanya fallback pertama yang consume sisa 5.
--    Sisanya harus 0 karena stok sudah habis.
-- ------------------------------------------------------------
UPDATE inv_stock_movement_log
SET
  qty_buy_delta = -0.0050,
  qty_content_delta = -5.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | fallback canonical consume available 5 only'
  )), 255)
WHERE id = 10265;

UPDATE inv_stock_movement_log
SET
  qty_buy_delta = 0.0000,
  qty_content_delta = 0.0000,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | rollback fallback overshoot after stock already zero'
  )), 255)
WHERE id IN (10277, 10331, 10481, 10625);

-- ------------------------------------------------------------
-- E. Recalc running after untuk legacy profile
-- ------------------------------------------------------------
SET @legacy_running_content := 0;
SET @legacy_running_buy := 0;

UPDATE inv_stock_movement_log
SET
  qty_content_after = (@legacy_running_content := ROUND(@legacy_running_content + qty_content_delta, 4)),
  qty_buy_after = (@legacy_running_buy := ROUND(@legacy_running_buy + qty_buy_delta, 4))
WHERE movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND item_id = @item_id
  AND COALESCE(material_id, 0) = @material_id
  AND COALESCE(profile_key, '') = @legacy_profile_key
ORDER BY movement_date, id;

-- ------------------------------------------------------------
-- F. Recalc running after untuk canonical profile
-- ------------------------------------------------------------
SET @canonical_running_content := 0;
SET @canonical_running_buy := 0;

UPDATE inv_stock_movement_log
SET
  qty_content_after = (@canonical_running_content := ROUND(@canonical_running_content + qty_content_delta, 4)),
  qty_buy_after = (@canonical_running_buy := ROUND(@canonical_running_buy + qty_buy_delta, 4))
WHERE movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND COALESCE(destination_type, 'OTHER') = @destination_type
  AND item_id = @item_id
  AND COALESCE(material_id, 0) = @material_id
  AND COALESCE(profile_key, '') = @canonical_profile_key
ORDER BY movement_date, id;

-- ------------------------------------------------------------
-- G. Kembalikan opening monthly legacy profile dari opening snapshot
-- ------------------------------------------------------------
UPDATE inv_division_monthly_stock s
JOIN (
  SELECT
    division_id,
    COALESCE(destination_type, 'OTHER') AS destination_type,
    item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(COALESCE(opening_qty_buy, 0), 4) AS opening_qty_buy,
    ROUND(COALESCE(opening_qty_content, 0), 4) AS opening_qty_content
  FROM inv_division_stock_opening_snapshot
  WHERE snapshot_month = @target_month
    AND division_id = @division_id
    AND COALESCE(destination_type, 'OTHER') = @destination_type
    AND item_id = @item_id
    AND COALESCE(material_id, 0) = @material_id
    AND COALESCE(profile_key, '') = @legacy_profile_key
) os
  ON os.division_id = s.division_id
 AND os.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND os.item_id = s.item_id
 AND os.material_id = COALESCE(s.material_id, 0)
 AND os.profile_key = COALESCE(s.profile_key, '')
SET
  s.opening_qty_buy = os.opening_qty_buy,
  s.opening_qty_content = os.opening_qty_content,
  s.opening_total_value = 0.00,
  s.notes = LEFT(TRIM(CONCAT(
    COALESCE(s.notes, ''),
    CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | restore monthly opening from opening snapshot'
  )), 255),
  s.updated_at = CURRENT_TIMESTAMP
WHERE s.month_key = @target_month;

COMMIT;

-- ------------------------------------------------------------
-- H. Verifikasi ringkas
-- ------------------------------------------------------------
SELECT 'movement_rows_repaired' AS metric, COUNT(*) AS total
FROM tmp_sari_lemon_gap_movement_backup

UNION ALL

SELECT 'monthly_rows_backed_up', COUNT(*)
FROM tmp_sari_lemon_gap_monthly_backup

UNION ALL

SELECT 'legacy_profile_final_after_content',
       ROUND(COALESCE((
         SELECT l.qty_content_after
         FROM inv_stock_movement_log l
         WHERE l.movement_scope = 'DIVISION'
           AND l.division_id = @division_id
           AND COALESCE(l.destination_type, 'OTHER') = @destination_type
           AND l.item_id = @item_id
           AND COALESCE(l.material_id, 0) = @material_id
           AND COALESCE(l.profile_key, '') = @legacy_profile_key
         ORDER BY l.movement_date DESC, l.id DESC
         LIMIT 1
       ), 0), 4)

UNION ALL

SELECT 'canonical_profile_final_after_content',
       ROUND(COALESCE((
         SELECT l.qty_content_after
         FROM inv_stock_movement_log l
         WHERE l.movement_scope = 'DIVISION'
           AND l.division_id = @division_id
           AND COALESCE(l.destination_type, 'OTHER') = @destination_type
           AND l.item_id = @item_id
           AND COALESCE(l.material_id, 0) = @material_id
           AND COALESCE(l.profile_key, '') = @canonical_profile_key
         ORDER BY l.movement_date DESC, l.id DESC
         LIMIT 1
       ), 0), 4);

-- Gap audit pasca-repair:
SELECT
  s.profile_key,
  s.profile_name,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS monthly_opening_content,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS monthly_closing_content,
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0), 4) AS predicted_closing_from_opening_and_movement,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS remaining_log_gap
FROM inv_division_monthly_stock s
LEFT JOIN (
  SELECT
    division_id,
    COALESCE(destination_type, 'OTHER') AS destination_type,
    item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(
      CASE
        WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 0
        ELSE COALESCE(qty_content_delta, 0)
      END
    ), 4) AS net_non_opening_delta
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND division_id = @division_id
    AND COALESCE(destination_type, 'OTHER') = @destination_type
    AND item_id = @item_id
    AND COALESCE(material_id, 0) = @material_id
  GROUP BY
    division_id,
    COALESCE(destination_type, 'OTHER'),
    item_id,
    COALESCE(material_id, 0),
    COALESCE(profile_key, '')
) mv
  ON mv.division_id = s.division_id
 AND mv.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND mv.item_id = s.item_id
 AND mv.material_id = COALESCE(s.material_id, 0)
 AND mv.profile_key = COALESCE(s.profile_key, '')
WHERE s.month_key = @target_month
  AND s.division_id = @division_id
  AND COALESCE(s.destination_type, 'OTHER') = @destination_type
  AND s.item_id = @item_id
  AND COALESCE(s.material_id, 0) = @material_id
ORDER BY s.profile_key;
