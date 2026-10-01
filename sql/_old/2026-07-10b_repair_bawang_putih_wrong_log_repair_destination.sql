SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-10b_repair_bawang_putih_wrong_log_repair_destination.sql
-- Tujuan :
-- 1) Memperbaiki log repair BAWANG PUTIH yang salah masuk KITCHEN
--    Regular, padahal profile_key tersebut milik KITCHEN_EVENT.
-- 2) Menghilangkan baris orphan Regular yang muncul karena movement
--    log punya destination/item/uom tidak sesuai monthly profile.
-- 3) Scope sengaja sempit agar tidak menyentuh stok/lot BAWANG PUTIH
--    lain yang memang perlu direkonsiliasi terpisah.
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Repair wrong BAWANG PUTIH log repair destination 2026-07-10';
SET @month_key := '2026-07-01';
SET @movement_no := 'LOGADJ202607102223379D90A0';
SET @division_id := 3;
SET @material_id := 14;
SET @profile_key := 'b289fb0dab955337d0dbcd7e31b94ef447de3a67f9ee35e849f4b3c8f08502b4';

DROP TEMPORARY TABLE IF EXISTS tmp_bawang_putih_wrong_log_backup;
CREATE TEMPORARY TABLE tmp_bawang_putih_wrong_log_backup AS
SELECT *
FROM inv_stock_movement_log
WHERE movement_no = @movement_no
  AND movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND material_id = @material_id
  AND COALESCE(profile_key, '') = @profile_key
  AND COALESCE(ref_table, '') = 'div_log_repair';

DROP TEMPORARY TABLE IF EXISTS tmp_bawang_putih_event_profile_target;
CREATE TEMPORARY TABLE tmp_bawang_putih_event_profile_target AS
SELECT
  id AS monthly_id,
  destination_type,
  item_id,
  buy_uom_id,
  content_uom_id,
  profile_key,
  profile_name,
  profile_brand,
  profile_description,
  profile_expired_date,
  profile_content_per_buy,
  profile_buy_uom_code,
  profile_content_uom_code,
  closing_qty_buy,
  closing_qty_content,
  avg_cost_per_content
FROM inv_division_monthly_stock
WHERE month_key = @month_key
  AND division_id = @division_id
  AND material_id = @material_id
  AND profile_key = @profile_key
  AND destination_type = 'KITCHEN_EVENT'
LIMIT 1;

UPDATE inv_stock_movement_log l
JOIN tmp_bawang_putih_event_profile_target t ON t.profile_key = l.profile_key
SET
  l.destination_type = t.destination_type,
  l.item_id = t.item_id,
  l.buy_uom_id = t.buy_uom_id,
  l.content_uom_id = t.content_uom_id,
  l.qty_buy_after = t.closing_qty_buy,
  l.qty_content_after = t.closing_qty_content,
  l.profile_name = t.profile_name,
  l.profile_brand = t.profile_brand,
  l.profile_description = t.profile_description,
  l.profile_expired_date = t.profile_expired_date,
  l.profile_content_per_buy = t.profile_content_per_buy,
  l.profile_buy_uom_code = t.profile_buy_uom_code,
  l.profile_content_uom_code = t.profile_content_uom_code,
  l.unit_cost = CASE WHEN COALESCE(l.unit_cost, 0) > 0 THEN l.unit_cost ELSE t.avg_cost_per_content END,
  l.notes = LEFT(TRIM(CONCAT(
    COALESCE(l.notes, ''),
    CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END,
    @repair_tag,
    ' | moved from wrong KITCHEN Regular orphan to KITCHEN_EVENT profile identity'
  )), 255)
WHERE l.movement_no = @movement_no
  AND l.movement_scope = 'DIVISION'
  AND l.division_id = @division_id
  AND l.material_id = @material_id
  AND COALESCE(l.profile_key, '') = @profile_key
  AND COALESCE(l.ref_table, '') = 'div_log_repair'
  AND COALESCE(l.destination_type, '') = 'KITCHEN'
  AND l.item_id IS NULL;

COMMIT;

SELECT 'backup_rows' AS metric, COUNT(*) AS total
FROM tmp_bawang_putih_wrong_log_backup
UNION ALL
SELECT 'target_monthly_rows', COUNT(*)
FROM tmp_bawang_putih_event_profile_target
UNION ALL
SELECT 'fixed_log_rows', COUNT(*)
FROM inv_stock_movement_log
WHERE movement_no = @movement_no
  AND movement_scope = 'DIVISION'
  AND division_id = @division_id
  AND material_id = @material_id
  AND COALESCE(profile_key, '') = @profile_key
  AND destination_type = 'KITCHEN_EVENT'
  AND item_id IS NOT NULL;

SELECT
  id,
  movement_no,
  movement_date,
  destination_type,
  item_id,
  material_id,
  content_uom_id,
  profile_key,
  qty_content_delta,
  qty_content_after,
  ref_table,
  notes
FROM inv_stock_movement_log
WHERE movement_no = @movement_no;
