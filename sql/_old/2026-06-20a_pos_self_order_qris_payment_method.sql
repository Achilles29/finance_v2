SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-20a_pos_self_order_qris_payment_method.sql
-- Tujuan :
-- 1) Menambahkan mapping payment method QRIS untuk self order
-- 2) Menjaga agar self order Midtrans masuk ke rekening sesuai
--    payment method POS yang dipilih dari admin finance
-- 3) Tetap kompatibel dengan tabel legacy pr_qris_setting bila ada
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_self_order_qris_setting
  ADD COLUMN IF NOT EXISTS payment_method_id BIGINT UNSIGNED NULL AFTER midtrans_is_production,
  ADD KEY IF NOT EXISTS idx_pos_self_order_qris_payment_method (payment_method_id);

SET @fk_pos_self_order_qris_payment_method := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_self_order_qris_setting'
    AND COLUMN_NAME = 'payment_method_id'
    AND REFERENCED_TABLE_NAME = 'pos_payment_method'
  LIMIT 1
);
SET @sql_fk_pos_self_order_qris_payment_method := IF(
  @fk_pos_self_order_qris_payment_method IS NULL,
  'ALTER TABLE pos_self_order_qris_setting ADD CONSTRAINT fk_pos_self_order_qris_payment_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id) ON DELETE SET NULL ON UPDATE RESTRICT',
  'SELECT 1'
);
PREPARE stmt_fk_pos_self_order_qris_payment_method FROM @sql_fk_pos_self_order_qris_payment_method;
EXECUTE stmt_fk_pos_self_order_qris_payment_method;
DEALLOCATE PREPARE stmt_fk_pos_self_order_qris_payment_method;

SET @has_pr_qris_setting := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pr_qris_setting'
);
SET @sql_pr_qris_payment_method := IF(
  @has_pr_qris_setting = 0,
  'SELECT 1',
  'ALTER TABLE pr_qris_setting ADD COLUMN IF NOT EXISTS payment_method_id BIGINT UNSIGNED NULL AFTER midtrans_is_production, ADD KEY IF NOT EXISTS idx_pr_qris_payment_method (payment_method_id)'
);
PREPARE stmt_pr_qris_payment_method FROM @sql_pr_qris_payment_method;
EXECUTE stmt_pr_qris_payment_method;
DEALLOCATE PREPARE stmt_pr_qris_payment_method;

SET @fk_pr_qris_payment_method := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pr_qris_setting'
    AND COLUMN_NAME = 'payment_method_id'
    AND REFERENCED_TABLE_NAME = 'pos_payment_method'
  LIMIT 1
);
SET @sql_fk_pr_qris_payment_method := IF(
  @has_pr_qris_setting = 0 OR @fk_pr_qris_payment_method IS NOT NULL,
  'SELECT 1',
  'ALTER TABLE pr_qris_setting ADD CONSTRAINT fk_pr_qris_payment_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE stmt_fk_pr_qris_payment_method FROM @sql_fk_pr_qris_payment_method;
EXECUTE stmt_fk_pr_qris_payment_method;
DEALLOCATE PREPARE stmt_fk_pr_qris_payment_method;

COMMIT;

SELECT
  id,
  is_enabled,
  payment_method_id,
  midtrans_is_production
FROM pos_self_order_qris_setting
WHERE id = 1;
