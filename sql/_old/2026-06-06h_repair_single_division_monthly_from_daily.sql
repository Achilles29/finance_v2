SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-06h_repair_single_division_monthly_from_daily.sql
-- Tujuan :
-- 1) Memulihkan row inv_division_monthly_stock dari sumber daily rollup
--    untuk 1 item/material tertentu
-- 2) Cocok dipakai saat inventory-material-daily ada, tetapi
--    inventory/stock/division tidak menampilkan row yang sama
-- 3) Fokus ke visibility/reader monthly, tanpa menyentuh movement log
--
-- Cara pakai:
-- - sesuaikan @target_month, @target_division_id, @target_destination_type
-- - sesuaikan item/material target bila perlu
-- - untuk kasus KENTANG default item/material sudah otomatis dicari
-- ============================================================

START TRANSACTION;

SET @target_month := DATE_FORMAT(CURDATE(), '%Y-%m-01');
SET @target_division_id := 3;
SET @target_destination_type := 'KITCHEN';
SET @target_item_name := 'KENTANG';
SET @target_material_name := 'KENTANG';
SET @repair_tag := 'Repair single division monthly from daily 2026-06-06';

SET @target_item_id := (
  SELECT id
  FROM mst_item
  WHERE UPPER(TRIM(item_name)) = UPPER(TRIM(@target_item_name))
  ORDER BY id ASC
  LIMIT 1
);

SET @target_material_id := (
  SELECT COALESCE(material_id, 0)
  FROM mst_item
  WHERE id = @target_item_id
  LIMIT 1
);

SET @target_material_id := CASE
  WHEN COALESCE(@target_material_id, 0) > 0 THEN @target_material_id
  ELSE (
    SELECT id
    FROM mst_material
    WHERE UPPER(TRIM(material_name)) = UPPER(TRIM(@target_material_name))
    ORDER BY id ASC
    LIMIT 1
  )
END;

SET @guard_message := NULL;
SET @guard_message := IF(@target_division_id <= 0, 'target_division_id wajib diisi.', @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND (@target_destination_type IS NULL OR TRIM(@target_destination_type) = ''), 'target_destination_type wajib diisi.', @guard_message);
SET @guard_message := IF(@guard_message IS NULL AND COALESCE(@target_item_id, 0) <= 0 AND COALESCE(@target_material_id, 0) <= 0, 'Item/material target tidak ditemukan.', @guard_message);

SET @sql_guard := IF(
  @guard_message IS NOT NULL,
  CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''', REPLACE(@guard_message, '''', ''''''), ''''),
  'SELECT 1'
);
PREPARE stmt FROM @sql_guard; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TEMPORARY TABLE IF EXISTS tmp_target_daily_rows;
CREATE TEMPORARY TABLE tmp_target_daily_rows AS
SELECT *
FROM inv_division_daily_rollup
WHERE month_key = @target_month
  AND division_id = @target_division_id
  AND destination_type = @target_destination_type
  AND (
    item_id = @target_item_id
    OR material_id = @target_material_id
  );

DROP TEMPORARY TABLE IF EXISTS tmp_target_daily_agg;
CREATE TEMPORARY TABLE tmp_target_daily_agg AS
SELECT
  month_key,
  division_id,
  destination_type,
  COALESCE(stock_domain, 'ITEM') AS stock_domain,
  item_id,
  material_id,
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
  CASE
    WHEN profile_key IS NOT NULL AND TRIM(profile_key) <> '' THEN profile_key
    ELSE SHA2(CONCAT_WS('|',
      CAST(COALESCE(item_id, 0) AS CHAR),
      CAST(COALESCE(material_id, 0) AS CHAR),
      CAST(COALESCE(buy_uom_id, 0) AS CHAR),
      CAST(COALESCE(content_uom_id, 0) AS CHAR),
      UPPER(TRIM(COALESCE(profile_name, ''))),
      UPPER(TRIM(COALESCE(profile_brand, ''))),
      UPPER(TRIM(COALESCE(profile_description, ''))),
      CAST(ROUND(COALESCE(profile_content_per_buy, 1), 6) AS CHAR),
      COALESCE(DATE_FORMAT(profile_expired_date, '%Y-%m-%d'), '')
    ), 256)
  END AS identity_key,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(opening_qty_buy ORDER BY movement_date ASC, id ASC SEPARATOR ','), ',', 1) AS DECIMAL(18,4)) AS opening_qty_buy,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(opening_qty_content ORDER BY movement_date ASC, id ASC SEPARATOR ','), ',', 1) AS DECIMAL(18,4)) AS opening_qty_content,
  ROUND(SUM(COALESCE(in_qty_buy, 0)), 4) AS in_qty_buy,
  ROUND(SUM(COALESCE(in_qty_content, 0)), 4) AS in_qty_content,
  ROUND(SUM(COALESCE(out_qty_buy, 0)), 4) AS out_qty_buy,
  ROUND(SUM(COALESCE(out_qty_content, 0)), 4) AS out_qty_content,
  ROUND(SUM(COALESCE(discarded_qty_buy, 0)), 4) AS discarded_qty_buy,
  ROUND(SUM(COALESCE(discarded_qty_content, 0)), 4) AS discarded_qty_content,
  ROUND(SUM(COALESCE(spoil_qty_buy, 0)), 4) AS spoil_qty_buy,
  ROUND(SUM(COALESCE(spoil_qty_content, 0)), 4) AS spoil_qty_content,
  ROUND(SUM(COALESCE(waste_qty_buy, 0)), 4) AS waste_qty_buy,
  ROUND(SUM(COALESCE(waste_qty_content, 0)), 4) AS waste_qty_content,
  ROUND(SUM(COALESCE(process_loss_qty_buy, 0)), 4) AS process_loss_qty_buy,
  ROUND(SUM(COALESCE(process_loss_qty_content, 0)), 4) AS process_loss_qty_content,
  ROUND(SUM(COALESCE(variance_qty_buy, 0)), 4) AS variance_qty_buy,
  ROUND(SUM(COALESCE(variance_qty_content, 0)), 4) AS variance_qty_content,
  ROUND(SUM(COALESCE(adjustment_plus_qty_buy, 0)), 4) AS adjustment_plus_qty_buy,
  ROUND(SUM(COALESCE(adjustment_plus_qty_content, 0)), 4) AS adjustment_plus_qty_content,
  ROUND(GREATEST(-SUM(COALESCE(adjustment_qty_buy, 0)), 0), 4) AS adjustment_minus_qty_buy,
  ROUND(GREATEST(-SUM(COALESCE(adjustment_qty_content, 0)), 0), 4) AS adjustment_minus_qty_content,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(closing_qty_buy ORDER BY movement_date DESC, id DESC SEPARATOR ','), ',', 1) AS DECIMAL(18,4)) AS closing_qty_buy,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(closing_qty_content ORDER BY movement_date DESC, id DESC SEPARATOR ','), ',', 1) AS DECIMAL(18,4)) AS closing_qty_content,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(avg_cost_per_content ORDER BY movement_date DESC, id DESC SEPARATOR ','), ',', 1) AS DECIMAL(18,6)) AS avg_cost_per_content,
  CAST(SUBSTRING_INDEX(GROUP_CONCAT(total_value ORDER BY movement_date DESC, id DESC SEPARATOR ','), ',', 1) AS DECIMAL(18,2)) AS total_value,
  ROUND(SUM(COALESCE(waste_total_value, 0)), 2) AS waste_total_value,
  ROUND(SUM(COALESCE(spoilage_total_value, 0)), 2) AS spoilage_total_value,
  ROUND(SUM(COALESCE(process_loss_total_value, 0)), 2) AS process_loss_total_value,
  ROUND(SUM(COALESCE(variance_total_value, 0)), 2) AS variance_total_value,
  ROUND(SUM(COALESCE(adjustment_plus_total_value, 0)), 2) AS adjustment_plus_total_value,
  COUNT(DISTINCT movement_date) AS movement_day_count,
  SUM(COALESCE(mutation_count, 0)) AS mutation_count,
  MAX(movement_date) AS last_movement_date,
  MAX(last_movement_at) AS last_movement_at
FROM tmp_target_daily_rows
GROUP BY
  month_key, division_id, destination_type,
  COALESCE(stock_domain, 'ITEM'),
  item_id, material_id, buy_uom_id, content_uom_id,
  profile_key, profile_name, profile_brand, profile_description,
  profile_expired_date, profile_content_per_buy,
  profile_buy_uom_code, profile_content_uom_code;

UPDATE inv_division_monthly_stock m
JOIN tmp_target_daily_agg a
  ON a.month_key = m.month_key
 AND a.division_id = m.division_id
 AND a.destination_type = m.destination_type
 AND a.identity_key = m.identity_key
SET
  m.stock_domain = a.stock_domain,
  m.item_id = a.item_id,
  m.material_id = a.material_id,
  m.buy_uom_id = a.buy_uom_id,
  m.content_uom_id = a.content_uom_id,
  m.profile_key = a.profile_key,
  m.profile_name = a.profile_name,
  m.profile_brand = a.profile_brand,
  m.profile_description = a.profile_description,
  m.profile_expired_date = a.profile_expired_date,
  m.profile_content_per_buy = a.profile_content_per_buy,
  m.profile_buy_uom_code = a.profile_buy_uom_code,
  m.profile_content_uom_code = a.profile_content_uom_code,
  m.opening_qty_buy = a.opening_qty_buy,
  m.opening_qty_content = a.opening_qty_content,
  m.opening_total_value = ROUND(a.opening_qty_content * a.avg_cost_per_content, 2),
  m.in_qty_buy = a.in_qty_buy,
  m.in_qty_content = a.in_qty_content,
  m.in_total_value = 0,
  m.out_qty_buy = a.out_qty_buy,
  m.out_qty_content = a.out_qty_content,
  m.out_total_value = 0,
  m.discarded_qty_buy = a.discarded_qty_buy,
  m.discarded_qty_content = a.discarded_qty_content,
  m.discarded_total_value = 0,
  m.spoil_qty_buy = a.spoil_qty_buy,
  m.spoil_qty_content = a.spoil_qty_content,
  m.spoilage_total_value = a.spoilage_total_value,
  m.waste_qty_buy = a.waste_qty_buy,
  m.waste_qty_content = a.waste_qty_content,
  m.waste_total_value = a.waste_total_value,
  m.process_loss_qty_buy = a.process_loss_qty_buy,
  m.process_loss_qty_content = a.process_loss_qty_content,
  m.process_loss_total_value = a.process_loss_total_value,
  m.variance_qty_buy = a.variance_qty_buy,
  m.variance_qty_content = a.variance_qty_content,
  m.variance_total_value = a.variance_total_value,
  m.adjustment_plus_qty_buy = a.adjustment_plus_qty_buy,
  m.adjustment_plus_qty_content = a.adjustment_plus_qty_content,
  m.adjustment_plus_total_value = a.adjustment_plus_total_value,
  m.adjustment_minus_qty_buy = a.adjustment_minus_qty_buy,
  m.adjustment_minus_qty_content = a.adjustment_minus_qty_content,
  m.adjustment_minus_total_value = 0,
  m.closing_qty_buy = a.closing_qty_buy,
  m.closing_qty_content = a.closing_qty_content,
  m.avg_cost_per_content = a.avg_cost_per_content,
  m.total_value = a.total_value,
  m.movement_day_count = a.movement_day_count,
  m.mutation_count = a.mutation_count,
  m.last_movement_date = a.last_movement_date,
  m.last_movement_at = a.last_movement_at,
  m.last_movement_table = 'inv_division_daily_rollup',
  m.source_mode = 'REBUILD',
  m.updated_at = CURRENT_TIMESTAMP,
  m.notes = LEFT(TRIM(CONCAT(COALESCE(m.notes, ''), CASE WHEN COALESCE(m.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

INSERT INTO inv_division_monthly_stock (
  month_key, division_id, destination_type, stock_domain, identity_key,
  item_id, material_id, buy_uom_id, content_uom_id, profile_key,
  profile_name, profile_brand, profile_description, profile_expired_date,
  profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code,
  opening_qty_buy, opening_qty_content, opening_total_value,
  in_qty_buy, in_qty_content, in_total_value,
  out_qty_buy, out_qty_content, out_total_value,
  discarded_qty_buy, discarded_qty_content, discarded_total_value,
  spoil_qty_buy, spoil_qty_content, spoilage_total_value,
  waste_qty_buy, waste_qty_content, waste_total_value,
  process_loss_qty_buy, process_loss_qty_content, process_loss_total_value,
  variance_qty_buy, variance_qty_content, variance_total_value,
  adjustment_plus_qty_buy, adjustment_plus_qty_content, adjustment_plus_total_value,
  adjustment_minus_qty_buy, adjustment_minus_qty_content, adjustment_minus_total_value,
  closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value,
  movement_day_count, mutation_count, last_movement_date, last_movement_at,
  last_movement_table, source_mode, notes
)
SELECT
  a.month_key, a.division_id, a.destination_type, a.stock_domain, a.identity_key,
  a.item_id, a.material_id, a.buy_uom_id, a.content_uom_id, a.profile_key,
  a.profile_name, a.profile_brand, a.profile_description, a.profile_expired_date,
  a.profile_content_per_buy, a.profile_buy_uom_code, a.profile_content_uom_code,
  a.opening_qty_buy, a.opening_qty_content, ROUND(a.opening_qty_content * a.avg_cost_per_content, 2),
  a.in_qty_buy, a.in_qty_content, 0,
  a.out_qty_buy, a.out_qty_content, 0,
  a.discarded_qty_buy, a.discarded_qty_content, 0,
  a.spoil_qty_buy, a.spoil_qty_content, a.spoilage_total_value,
  a.waste_qty_buy, a.waste_qty_content, a.waste_total_value,
  a.process_loss_qty_buy, a.process_loss_qty_content, a.process_loss_total_value,
  a.variance_qty_buy, a.variance_qty_content, a.variance_total_value,
  a.adjustment_plus_qty_buy, a.adjustment_plus_qty_content, a.adjustment_plus_total_value,
  a.adjustment_minus_qty_buy, a.adjustment_minus_qty_content, 0,
  a.closing_qty_buy, a.closing_qty_content, a.avg_cost_per_content, a.total_value,
  a.movement_day_count, a.mutation_count, a.last_movement_date, a.last_movement_at,
  'inv_division_daily_rollup', 'REBUILD', @repair_tag
FROM tmp_target_daily_agg a
LEFT JOIN inv_division_monthly_stock m
  ON m.month_key = a.month_key
 AND m.division_id = a.division_id
 AND m.destination_type = a.destination_type
 AND m.identity_key = a.identity_key
WHERE m.id IS NULL;

COMMIT;

SELECT 'target_item_id' AS metric, @target_item_id AS value
UNION ALL SELECT 'target_material_id', @target_material_id
UNION ALL SELECT 'daily_rows_found', COUNT(*) FROM tmp_target_daily_rows
UNION ALL SELECT 'monthly_rows_after_repair', COUNT(*)
FROM inv_division_monthly_stock
WHERE month_key = @target_month
  AND division_id = @target_division_id
  AND destination_type = @target_destination_type
  AND (item_id = @target_item_id OR material_id = @target_material_id);
