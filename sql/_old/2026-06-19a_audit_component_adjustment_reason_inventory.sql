SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19a_audit_component_adjustment_reason_inventory.sql
-- Tujuan :
-- 1) Menginventaris semua reason_code yang sudah terpakai pada
--    inv_component_adjustment_line
-- 2) Mengelompokkan reason menurut jenis bucket adjustment line
-- 3) Menjadi dasar normalisasi + pengencangan enum reason code
-- ============================================================

DROP TEMPORARY TABLE IF EXISTS tmp_component_adjustment_reason_inventory;
CREATE TEMPORARY TABLE tmp_component_adjustment_reason_inventory AS
SELECT
  'WASTE' AS reason_category,
  COALESCE(NULLIF(TRIM(waste_reason_code), ''), '(blank)') AS reason_code,
  COUNT(*) AS total_rows
FROM inv_component_adjustment_line
WHERE COALESCE(qty_waste, 0) > 0
GROUP BY COALESCE(NULLIF(TRIM(waste_reason_code), ''), '(blank)')

UNION ALL

SELECT
  'SPOILAGE',
  COALESCE(NULLIF(TRIM(spoil_reason_code), ''), '(blank)'),
  COUNT(*)
FROM inv_component_adjustment_line
WHERE COALESCE(qty_spoil, 0) > 0
GROUP BY COALESCE(NULLIF(TRIM(spoil_reason_code), ''), '(blank)')

UNION ALL

SELECT
  'ADJUSTMENT_PLUS',
  COALESCE(NULLIF(TRIM(adjustment_plus_reason_code), ''), '(blank)'),
  COUNT(*)
FROM inv_component_adjustment_line
WHERE COALESCE(qty_adjust_pos, 0) > 0
GROUP BY COALESCE(NULLIF(TRIM(adjustment_plus_reason_code), ''), '(blank)')

UNION ALL

SELECT
  'ADJUSTMENT_MINUS',
  COALESCE(NULLIF(TRIM(adjustment_minus_reason_code), ''), '(blank)'),
  COUNT(*)
FROM inv_component_adjustment_line
WHERE COALESCE(qty_adjust_neg, 0) > 0
GROUP BY COALESCE(NULLIF(TRIM(adjustment_minus_reason_code), ''), '(blank)');

ALTER TABLE tmp_component_adjustment_reason_inventory
  ADD KEY idx_component_adjustment_reason_inventory (reason_category, reason_code);

DROP TEMPORARY TABLE IF EXISTS tmp_component_adjustment_reason_allowed;
CREATE TEMPORARY TABLE tmp_component_adjustment_reason_allowed (
  reason_category VARCHAR(40) NOT NULL,
  reason_code VARCHAR(50) NOT NULL,
  PRIMARY KEY (reason_category, reason_code)
);

INSERT INTO tmp_component_adjustment_reason_allowed (reason_category, reason_code) VALUES
('WASTE', 'cancel_order'),
('WASTE', 'kitchen_error'),
('WASTE', 'overproduction'),
('WASTE', 'spillage'),
('WASTE', 'expired_opened'),
('WASTE', 'other'),
('SPOILAGE', 'expired'),
('SPOILAGE', 'temperature_abuse'),
('SPOILAGE', 'contamination'),
('SPOILAGE', 'improper_storage'),
('SPOILAGE', 'overstock'),
('SPOILAGE', 'other'),
('ADJUSTMENT_PLUS', 'opening_correction'),
('ADJUSTMENT_PLUS', 'stock_found'),
('ADJUSTMENT_PLUS', 'manual_reclass'),
('ADJUSTMENT_PLUS', 'other'),
('ADJUSTMENT_MINUS', 'counting_error'),
('ADJUSTMENT_MINUS', 'system_mismatch'),
('ADJUSTMENT_MINUS', 'unrecorded_usage'),
('ADJUSTMENT_MINUS', 'process_loss'),
('ADJUSTMENT_MINUS', 'theft_suspected'),
('ADJUSTMENT_MINUS', 'other');

-- ------------------------------------------------------------
-- A. Ringkasan semua reason terpakai
-- ------------------------------------------------------------
SELECT
  reason_category,
  reason_code,
  total_rows
FROM tmp_component_adjustment_reason_inventory
ORDER BY reason_category, total_rows DESC, reason_code;

-- ------------------------------------------------------------
-- B. Reason yang tidak ada di katalog baku
-- ------------------------------------------------------------
SELECT
  i.reason_category,
  i.reason_code,
  i.total_rows
FROM tmp_component_adjustment_reason_inventory i
LEFT JOIN tmp_component_adjustment_reason_allowed a
  ON a.reason_category = i.reason_category
 AND a.reason_code = i.reason_code
WHERE i.reason_code = '(blank)'
   OR a.reason_code IS NULL
ORDER BY i.reason_category, i.total_rows DESC, i.reason_code;

-- ------------------------------------------------------------
-- C. Summary total per bucket
-- ------------------------------------------------------------
SELECT
  reason_category,
  SUM(total_rows) AS total_bucket_rows,
  COUNT(*) AS distinct_reason_codes
FROM tmp_component_adjustment_reason_inventory
GROUP BY reason_category
ORDER BY reason_category;
