SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE inv_stock_adjustment_line
  ADD COLUMN IF NOT EXISTS waste_reason_code VARCHAR(64) NULL AFTER qty_waste_content,
  ADD COLUMN IF NOT EXISTS spoil_reason_code VARCHAR(64) NULL AFTER qty_spoil_content,
  ADD COLUMN IF NOT EXISTS process_loss_reason_code VARCHAR(64) NULL AFTER qty_process_loss_content,
  ADD COLUMN IF NOT EXISTS variance_reason_code VARCHAR(64) NULL AFTER qty_variance_content,
  ADD COLUMN IF NOT EXISTS adjustment_plus_reason_code VARCHAR(64) NULL AFTER qty_adjustment_plus_content;

COMMIT;

SHOW COLUMNS FROM inv_stock_adjustment_line;