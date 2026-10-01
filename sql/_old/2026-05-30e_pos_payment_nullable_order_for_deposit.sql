SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30e_pos_payment_nullable_order_for_deposit.sql
-- Tujuan :
-- 1) Mengizinkan deposit / DP berdiri sendiri tanpa order_id
-- 2) Menjaga skema payment POS tetap bisa dipakai untuk deposit mandiri
-- 3) Aman dijalankan ulang
-- ============================================================

START TRANSACTION;

SET @has_pos_payment := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_payment'
);

SET @order_id_nullable := (
  SELECT CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_payment'
    AND COLUMN_NAME = 'order_id'
);

SET @sql_alter_order_id := IF(
  @has_pos_payment = 1 AND COALESCE(@order_id_nullable, 0) = 0,
  'ALTER TABLE pos_payment MODIFY COLUMN order_id BIGINT UNSIGNED NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_alter_order_id; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT COLUMN_NAME, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_payment'
  AND COLUMN_NAME = 'order_id';
