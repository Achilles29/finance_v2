SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-08k_recalc_division_buy_projection_from_content.sql
-- Tujuan :
-- 1) Menghitung ulang qty_buy turunan untuk struktur mixed-UOM yang valid
--    (buy_uom_id <> content_uom_id, content_per_buy > 0)
-- 2) Menutup drift seperti MAYONAISE: UOM sudah benar, tetapi qty_buy
--    bulanan/movement/opening masih warisan struktur lama
-- 3) Menjaga content qty tetap sebagai source-of-truth, buy qty sebagai
--    hasil turunan: qty_content / profile_content_per_buy
-- ============================================================

START TRANSACTION;

SET @repair_tag := 'Recalc division buy projection from content 2026-06-08';

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_monthly;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_monthly AS
SELECT id, profile_key
FROM inv_division_monthly_stock
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) <> COALESCE(content_uom_id, 0)
  AND COALESCE(profile_content_per_buy, 0) > 0
  AND ABS(COALESCE(closing_qty_buy, 0) - ROUND(COALESCE(closing_qty_content, 0) / NULLIF(profile_content_per_buy, 0), 4)) > 0.0001;

ALTER TABLE tmp_buy_projection_drift_monthly ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_monthly_backup;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_monthly_backup AS
SELECT s.*
FROM inv_division_monthly_stock s
JOIN tmp_buy_projection_drift_monthly t ON t.id = s.id;

UPDATE inv_division_monthly_stock s
JOIN tmp_buy_projection_drift_monthly t ON t.id = s.id
SET
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.in_qty_buy = ROUND(COALESCE(s.in_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.out_qty_buy = ROUND(COALESCE(s.out_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.discarded_qty_buy = ROUND(COALESCE(s.discarded_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.spoil_qty_buy = ROUND(COALESCE(s.spoil_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.waste_qty_buy = ROUND(COALESCE(s.waste_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.process_loss_qty_buy = ROUND(COALESCE(s.process_loss_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.variance_qty_buy = ROUND(COALESCE(s.variance_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.adjustment_plus_qty_buy = ROUND(COALESCE(s.adjustment_plus_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.adjustment_minus_qty_buy = ROUND(COALESCE(s.adjustment_minus_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.closing_qty_buy = ROUND(COALESCE(s.closing_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_opening;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_opening AS
SELECT id
FROM inv_division_stock_opening_snapshot
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) <> COALESCE(content_uom_id, 0)
  AND COALESCE(profile_content_per_buy, 0) > 0
  AND ABS(COALESCE(opening_qty_buy, 0) - ROUND(COALESCE(opening_qty_content, 0) / NULLIF(profile_content_per_buy, 0), 4)) > 0.0001;

ALTER TABLE tmp_buy_projection_drift_opening ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_opening_backup;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_opening_backup AS
SELECT s.*
FROM inv_division_stock_opening_snapshot s
JOIN tmp_buy_projection_drift_opening t ON t.id = s.id;

UPDATE inv_division_stock_opening_snapshot s
JOIN tmp_buy_projection_drift_opening t ON t.id = s.id
SET
  s.opening_qty_buy = ROUND(COALESCE(s.opening_qty_content, 0) / NULLIF(s.profile_content_per_buy, 0), 4),
  s.notes = LEFT(TRIM(CONCAT(COALESCE(s.notes, ''), CASE WHEN COALESCE(s.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255),
  s.updated_at = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_movement;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_movement AS
SELECT id
FROM inv_stock_movement_log
WHERE movement_scope = 'DIVISION'
  AND COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) <> COALESCE(content_uom_id, 0)
  AND COALESCE(profile_content_per_buy, 0) > 0
  AND (
    ABS(COALESCE(qty_buy_delta, 0) - ROUND(COALESCE(qty_content_delta, 0) / NULLIF(profile_content_per_buy, 0), 4)) > 0.0001
    OR ABS(COALESCE(qty_buy_after, 0) - ROUND(COALESCE(qty_content_after, 0) / NULLIF(profile_content_per_buy, 0), 4)) > 0.0001
  );

ALTER TABLE tmp_buy_projection_drift_movement ADD PRIMARY KEY (id);

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_movement_backup;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_movement_backup AS
SELECT l.*
FROM inv_stock_movement_log l
JOIN tmp_buy_projection_drift_movement t ON t.id = l.id;

UPDATE inv_stock_movement_log l
JOIN tmp_buy_projection_drift_movement t ON t.id = l.id
SET
  l.qty_buy_delta = ROUND(COALESCE(l.qty_content_delta, 0) / NULLIF(l.profile_content_per_buy, 0), 4),
  l.qty_buy_after = ROUND(COALESCE(l.qty_content_after, 0) / NULLIF(l.profile_content_per_buy, 0), 4),
  l.notes = LEFT(TRIM(CONCAT(COALESCE(l.notes, ''), CASE WHEN COALESCE(l.notes, '') = '' THEN '' ELSE ' | ' END, @repair_tag)), 255);

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_po;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_po AS
SELECT id
FROM pur_purchase_order_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) <> COALESCE(content_uom_id, 0)
  AND COALESCE(content_per_buy, 0) > 0
  AND ABS(COALESCE(qty_buy, 0) - ROUND(COALESCE(qty_content, 0) / NULLIF(content_per_buy, 0), 4)) > 0.0001;

ALTER TABLE tmp_buy_projection_drift_po ADD PRIMARY KEY (id);

UPDATE pur_purchase_order_line l
JOIN tmp_buy_projection_drift_po t ON t.id = l.id
SET
  l.qty_buy = ROUND(COALESCE(l.qty_content, 0) / NULLIF(l.content_per_buy, 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

DROP TEMPORARY TABLE IF EXISTS tmp_buy_projection_drift_receipt;
CREATE TEMPORARY TABLE tmp_buy_projection_drift_receipt AS
SELECT id
FROM pur_purchase_receipt_line
WHERE COALESCE(material_id, 0) > 0
  AND COALESCE(buy_uom_id, 0) <> COALESCE(content_uom_id, 0)
  AND COALESCE(conversion_factor_to_content, 0) > 0
  AND ABS(COALESCE(qty_buy_received, 0) - ROUND(COALESCE(qty_content_received, 0) / NULLIF(conversion_factor_to_content, 0), 4)) > 0.0001;

ALTER TABLE tmp_buy_projection_drift_receipt ADD PRIMARY KEY (id);

UPDATE pur_purchase_receipt_line l
JOIN tmp_buy_projection_drift_receipt t ON t.id = l.id
SET
  l.qty_buy_received = ROUND(COALESCE(l.qty_content_received, 0) / NULLIF(l.conversion_factor_to_content, 0), 4),
  l.updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'monthly_rows_recalculated' AS metric, COUNT(*) AS total FROM tmp_buy_projection_drift_monthly
UNION ALL
SELECT 'opening_rows_recalculated', COUNT(*) FROM tmp_buy_projection_drift_opening
UNION ALL
SELECT 'movement_rows_recalculated', COUNT(*) FROM tmp_buy_projection_drift_movement
UNION ALL
SELECT 'po_rows_recalculated', COUNT(*) FROM tmp_buy_projection_drift_po
UNION ALL
SELECT 'receipt_rows_recalculated', COUNT(*) FROM tmp_buy_projection_drift_receipt;
