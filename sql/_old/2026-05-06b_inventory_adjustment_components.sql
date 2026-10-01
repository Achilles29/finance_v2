SET NAMES utf8mb4;

-- ============================================================
-- Tahap 7K - Inventory adjustment taxonomy (5 komponen)
-- 1) movement log: kategori + reason code penyesuaian
-- 2) daily rollup: process_loss, variance, adjustment_plus + nilai rupiah
-- 3) backfill data lama agar laporan langsung terisi
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Movement log extension
-- ------------------------------------------------------------
ALTER TABLE inv_stock_movement_log
  MODIFY COLUMN movement_type ENUM(
    'PURCHASE_IN',
    'TRANSFER_IN',
    'TRANSFER_OUT',
    'USAGE_OUT',
    'DISCARDED_OUT',
    'SPOIL_OUT',
    'WASTE_OUT',
    'PROCESS_LOSS_OUT',
    'VARIANCE_OUT',
    'ADJUSTMENT',
    'ADJUSTMENT_IN'
  ) NOT NULL;

ALTER TABLE inv_stock_movement_log
  ADD COLUMN IF NOT EXISTS adjustment_category ENUM('WASTE','SPOILAGE','PROCESS_LOSS','VARIANCE','ADJUSTMENT_PLUS') NULL AFTER movement_type,
  ADD COLUMN IF NOT EXISTS adjustment_reason_code VARCHAR(64) NULL AFTER adjustment_category;

CREATE INDEX idx_inv_movement_adjustment_category ON inv_stock_movement_log (adjustment_category);
CREATE INDEX idx_inv_movement_adjustment_reason ON inv_stock_movement_log (adjustment_reason_code);

UPDATE inv_stock_movement_log
SET adjustment_category = CASE
    WHEN movement_type IN ('DISCARDED_OUT', 'WASTE_OUT') THEN 'WASTE'
    WHEN movement_type = 'SPOIL_OUT' THEN 'SPOILAGE'
    WHEN movement_type = 'PROCESS_LOSS_OUT' THEN 'PROCESS_LOSS'
    WHEN movement_type = 'VARIANCE_OUT' THEN 'VARIANCE'
    WHEN movement_type = 'ADJUSTMENT_IN' THEN 'ADJUSTMENT_PLUS'
    WHEN movement_type = 'ADJUSTMENT' AND COALESCE(qty_content_delta, 0) >= 0 THEN 'ADJUSTMENT_PLUS'
    WHEN movement_type = 'ADJUSTMENT' AND COALESCE(qty_content_delta, 0) < 0 THEN 'VARIANCE'
    ELSE adjustment_category
  END
WHERE adjustment_category IS NULL;

UPDATE inv_stock_movement_log
SET adjustment_reason_code = 'other'
WHERE adjustment_category IS NOT NULL
  AND (adjustment_reason_code IS NULL OR TRIM(adjustment_reason_code) = '');

-- ------------------------------------------------------------
-- B. Rollup extension (warehouse + division)
-- ------------------------------------------------------------
ALTER TABLE inv_warehouse_daily_rollup
  ADD COLUMN IF NOT EXISTS process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER waste_qty_content,
  ADD COLUMN IF NOT EXISTS process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER process_loss_qty_buy,
  ADD COLUMN IF NOT EXISTS variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER process_loss_qty_content,
  ADD COLUMN IF NOT EXISTS variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER variance_qty_buy,
  ADD COLUMN IF NOT EXISTS adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER variance_qty_content,
  ADD COLUMN IF NOT EXISTS adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER adjustment_plus_qty_buy,
  ADD COLUMN IF NOT EXISTS waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_value,
  ADD COLUMN IF NOT EXISTS spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER waste_total_value,
  ADD COLUMN IF NOT EXISTS process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER spoilage_total_value,
  ADD COLUMN IF NOT EXISTS variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER process_loss_total_value,
  ADD COLUMN IF NOT EXISTS adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER variance_total_value;

ALTER TABLE inv_division_daily_rollup
  ADD COLUMN IF NOT EXISTS process_loss_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER waste_qty_content,
  ADD COLUMN IF NOT EXISTS process_loss_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER process_loss_qty_buy,
  ADD COLUMN IF NOT EXISTS variance_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER process_loss_qty_content,
  ADD COLUMN IF NOT EXISTS variance_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER variance_qty_buy,
  ADD COLUMN IF NOT EXISTS adjustment_plus_qty_buy DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER variance_qty_content,
  ADD COLUMN IF NOT EXISTS adjustment_plus_qty_content DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER adjustment_plus_qty_buy,
  ADD COLUMN IF NOT EXISTS waste_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER total_value,
  ADD COLUMN IF NOT EXISTS spoilage_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER waste_total_value,
  ADD COLUMN IF NOT EXISTS process_loss_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER spoilage_total_value,
  ADD COLUMN IF NOT EXISTS variance_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER process_loss_total_value,
  ADD COLUMN IF NOT EXISTS adjustment_plus_total_value DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER variance_total_value;

-- ------------------------------------------------------------
-- C. Backfill komponen qty + nilai (estimasi via avg_cost)
-- ------------------------------------------------------------
UPDATE inv_warehouse_daily_rollup
SET
  variance_qty_content = GREATEST(variance_qty_content, GREATEST(-COALESCE(adjustment_qty_content, 0), 0)),
  adjustment_plus_qty_content = GREATEST(adjustment_plus_qty_content, GREATEST(COALESCE(adjustment_qty_content, 0), 0)),
  variance_qty_buy = GREATEST(variance_qty_buy, CASE WHEN COALESCE(profile_content_per_buy, 0) > 0 THEN ROUND(GREATEST(-COALESCE(adjustment_qty_content, 0), 0) / profile_content_per_buy, 4) ELSE 0 END),
  adjustment_plus_qty_buy = GREATEST(adjustment_plus_qty_buy, CASE WHEN COALESCE(profile_content_per_buy, 0) > 0 THEN ROUND(GREATEST(COALESCE(adjustment_qty_content, 0), 0) / profile_content_per_buy, 4) ELSE 0 END),
  waste_total_value = ROUND((COALESCE(waste_qty_content, 0) + COALESCE(discarded_qty_content, 0)) * COALESCE(avg_cost_per_content, 0), 2),
  spoilage_total_value = ROUND(COALESCE(spoil_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  process_loss_total_value = ROUND(COALESCE(process_loss_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  variance_total_value = ROUND(COALESCE(variance_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  adjustment_plus_total_value = ROUND(COALESCE(adjustment_plus_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2);

UPDATE inv_division_daily_rollup
SET
  variance_qty_content = GREATEST(variance_qty_content, GREATEST(-COALESCE(adjustment_qty_content, 0), 0)),
  adjustment_plus_qty_content = GREATEST(adjustment_plus_qty_content, GREATEST(COALESCE(adjustment_qty_content, 0), 0)),
  variance_qty_buy = GREATEST(variance_qty_buy, CASE WHEN COALESCE(profile_content_per_buy, 0) > 0 THEN ROUND(GREATEST(-COALESCE(adjustment_qty_content, 0), 0) / profile_content_per_buy, 4) ELSE 0 END),
  adjustment_plus_qty_buy = GREATEST(adjustment_plus_qty_buy, CASE WHEN COALESCE(profile_content_per_buy, 0) > 0 THEN ROUND(GREATEST(COALESCE(adjustment_qty_content, 0), 0) / profile_content_per_buy, 4) ELSE 0 END),
  waste_total_value = ROUND((COALESCE(waste_qty_content, 0) + COALESCE(discarded_qty_content, 0)) * COALESCE(avg_cost_per_content, 0), 2),
  spoilage_total_value = ROUND(COALESCE(spoil_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  process_loss_total_value = ROUND(COALESCE(process_loss_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  variance_total_value = ROUND(COALESCE(variance_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2),
  adjustment_plus_total_value = ROUND(COALESCE(adjustment_plus_qty_content, 0) * COALESCE(avg_cost_per_content, 0), 2);

COMMIT;

SELECT 'inv_stock_movement_log' AS table_name, COUNT(*) AS total_rows FROM inv_stock_movement_log
UNION ALL
SELECT 'inv_warehouse_daily_rollup', COUNT(*) FROM inv_warehouse_daily_rollup
UNION ALL
SELECT 'inv_division_daily_rollup', COUNT(*) FROM inv_division_daily_rollup;
