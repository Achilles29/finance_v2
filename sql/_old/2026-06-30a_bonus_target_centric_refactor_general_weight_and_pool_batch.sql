SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-30a_bonus_target_centric_refactor_general_weight_and_pool_batch.sql
-- Tujuan :
-- 1) Mendukung bobot bonus umum yang tidak wajib terikat ke rule tertentu
-- 2) Menambahkan frekuensi bobot agar bisa dibedakan untuk DAILY / MONTHLY / ALL
-- 3) Menambahkan aturan konversi penalti poin -> nominal agar fair dan eksplisit
-- ============================================================

START TRANSACTION;

SET @schema_name := DATABASE();

SET @has_point_penalty_mode := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pay_bonus_config'
    AND COLUMN_NAME = 'point_penalty_currency_mode'
);
SET @sql := IF(
  @has_point_penalty_mode = 0,
  "ALTER TABLE pay_bonus_config ADD COLUMN point_penalty_currency_mode ENUM('NONE','PERCENT_SHARE','FIXED_RUPIAH') NOT NULL DEFAULT 'PERCENT_SHARE' AFTER payout_percent",
  "SELECT 'skip add point_penalty_currency_mode' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_point_penalty_value := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pay_bonus_config'
    AND COLUMN_NAME = 'point_penalty_currency_value'
);
SET @sql := IF(
  @has_point_penalty_value = 0,
  "ALTER TABLE pay_bonus_config ADD COLUMN point_penalty_currency_value DECIMAL(12,4) NOT NULL DEFAULT 5.0000 AFTER point_penalty_currency_mode",
  "SELECT 'skip add point_penalty_currency_value' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_weight_target_frequency := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pay_bonus_weight_rule'
    AND COLUMN_NAME = 'target_frequency'
);
SET @sql := IF(
  @has_weight_target_frequency = 0,
  "ALTER TABLE pay_bonus_weight_rule ADD COLUMN target_frequency ENUM('ALL','DAILY','MONTHLY') NOT NULL DEFAULT 'ALL' AFTER scope_id",
  "SELECT 'skip add target_frequency' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := "ALTER TABLE pay_bonus_weight_rule MODIFY COLUMN rule_id BIGINT UNSIGNED NULL";
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_weight_target_frequency_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'pay_bonus_weight_rule'
    AND INDEX_NAME = 'idx_pay_bonus_weight_rule_target_frequency'
);
SET @sql := IF(
  @has_weight_target_frequency_idx = 0,
  "ALTER TABLE pay_bonus_weight_rule ADD INDEX idx_pay_bonus_weight_rule_target_frequency (target_frequency, weight_scope, scope_id)",
  "SELECT 'skip add idx_pay_bonus_weight_rule_target_frequency' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pay_bonus_config
SET point_penalty_currency_mode = 'PERCENT_SHARE',
    point_penalty_currency_value = 5.0000
WHERE COALESCE(point_penalty_currency_mode, '') = '';

UPDATE pay_bonus_weight_rule
SET target_frequency = 'ALL'
WHERE COALESCE(target_frequency, '') = '';

COMMIT;

SELECT 'pay_bonus_config.point_penalty_currency_mode' AS metric,
       COUNT(*) AS total
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_config'
  AND COLUMN_NAME = 'point_penalty_currency_mode'
UNION ALL
SELECT 'pay_bonus_config.point_penalty_currency_value', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_config'
  AND COLUMN_NAME = 'point_penalty_currency_value'
UNION ALL
SELECT 'pay_bonus_weight_rule.target_frequency', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_weight_rule'
  AND COLUMN_NAME = 'target_frequency';
