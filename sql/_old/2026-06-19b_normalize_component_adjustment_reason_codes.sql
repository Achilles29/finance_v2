SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19b_normalize_component_adjustment_reason_codes.sql
-- Tujuan :
-- 1) Menormalkan reason_code adjustment component ke katalog baku
-- 2) Mengubah blank / unknown menjadi 'other'
-- 3) Memetakan legacy over_usage -> unrecorded_usage pada minus
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_component_adjustment_reason_backup;
CREATE TEMPORARY TABLE tmp_component_adjustment_reason_backup AS
SELECT *
FROM inv_component_adjustment_line
WHERE (COALESCE(qty_waste, 0) > 0 AND (
         COALESCE(TRIM(waste_reason_code), '') = ''
         OR LOWER(TRIM(COALESCE(waste_reason_code, ''))) NOT IN ('cancel_order', 'kitchen_error', 'overproduction', 'spillage', 'expired_opened', 'other')
      ))
   OR (COALESCE(qty_spoil, 0) > 0 AND (
         COALESCE(TRIM(spoil_reason_code), '') = ''
         OR LOWER(TRIM(COALESCE(spoil_reason_code, ''))) NOT IN ('expired', 'temperature_abuse', 'contamination', 'improper_storage', 'overstock', 'other')
      ))
   OR (COALESCE(qty_adjust_pos, 0) > 0 AND (
         COALESCE(TRIM(adjustment_plus_reason_code), '') = ''
         OR LOWER(TRIM(COALESCE(adjustment_plus_reason_code, ''))) NOT IN ('opening_correction', 'stock_found', 'manual_reclass', 'other')
      ))
   OR (COALESCE(qty_adjust_neg, 0) > 0 AND (
         COALESCE(TRIM(adjustment_minus_reason_code), '') = ''
         OR LOWER(TRIM(COALESCE(adjustment_minus_reason_code, ''))) NOT IN ('counting_error', 'system_mismatch', 'unrecorded_usage', 'process_loss', 'theft_suspected', 'other', 'over_usage')
      ));

UPDATE inv_component_adjustment_line
SET waste_reason_code = 'other'
WHERE COALESCE(qty_waste, 0) > 0
  AND (
    COALESCE(TRIM(waste_reason_code), '') = ''
    OR LOWER(TRIM(COALESCE(waste_reason_code, ''))) NOT IN ('cancel_order', 'kitchen_error', 'overproduction', 'spillage', 'expired_opened', 'other')
  );

UPDATE inv_component_adjustment_line
SET spoil_reason_code = 'other'
WHERE COALESCE(qty_spoil, 0) > 0
  AND (
    COALESCE(TRIM(spoil_reason_code), '') = ''
    OR LOWER(TRIM(COALESCE(spoil_reason_code, ''))) NOT IN ('expired', 'temperature_abuse', 'contamination', 'improper_storage', 'overstock', 'other')
  );

UPDATE inv_component_adjustment_line
SET adjustment_plus_reason_code = 'other'
WHERE COALESCE(qty_adjust_pos, 0) > 0
  AND (
    COALESCE(TRIM(adjustment_plus_reason_code), '') = ''
    OR LOWER(TRIM(COALESCE(adjustment_plus_reason_code, ''))) NOT IN ('opening_correction', 'stock_found', 'manual_reclass', 'other')
  );

UPDATE inv_component_adjustment_line
SET adjustment_minus_reason_code = 'unrecorded_usage'
WHERE COALESCE(qty_adjust_neg, 0) > 0
  AND LOWER(TRIM(COALESCE(adjustment_minus_reason_code, ''))) = 'over_usage';

UPDATE inv_component_adjustment_line
SET adjustment_minus_reason_code = 'other'
WHERE COALESCE(qty_adjust_neg, 0) > 0
  AND (
    COALESCE(TRIM(adjustment_minus_reason_code), '') = ''
    OR LOWER(TRIM(COALESCE(adjustment_minus_reason_code, ''))) NOT IN ('counting_error', 'system_mismatch', 'unrecorded_usage', 'process_loss', 'theft_suspected', 'other')
  );

COMMIT;

SELECT COUNT(*) AS normalized_rows
FROM tmp_component_adjustment_reason_backup;
