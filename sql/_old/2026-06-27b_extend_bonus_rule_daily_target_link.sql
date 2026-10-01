SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-27b_extend_bonus_rule_daily_target_link.sql
-- Tujuan :
-- 1) Menambahkan tautan target harian ke rule bonus
-- 2) Membuat target DAILY benar-benar bisa dibaca engine pool bonus
-- 3) Menjaga fallback lama tetap aman jika target harian belum dipilih
-- ============================================================

START TRANSACTION;

SET @table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_bonus_rule'
);

SET @field_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_bonus_rule'
    AND COLUMN_NAME = 'daily_target_plan_id'
);

SET @sql := IF(
  @table_exists > 0 AND @field_exists = 0,
  'ALTER TABLE pay_bonus_rule ADD COLUMN daily_target_plan_id BIGINT UNSIGNED NULL AFTER linked_target_plan_id',
  'SELECT ''skip add daily_target_plan_id'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pay_bonus_rule'
    AND INDEX_NAME = 'idx_pay_bonus_rule_daily_target_plan'
);

SET @sql := IF(
  @table_exists > 0 AND @index_exists = 0,
  'ALTER TABLE pay_bonus_rule ADD INDEX idx_pay_bonus_rule_daily_target_plan (daily_target_plan_id)',
  'SELECT ''skip add idx_pay_bonus_rule_daily_target_plan'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_pay_bonus_rule_daily_target_plan'
);

SET @target_table_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'fin_target_plan'
);

SET @sql := IF(
  @table_exists > 0 AND @target_table_exists > 0 AND @fk_exists = 0,
  'ALTER TABLE pay_bonus_rule ADD CONSTRAINT fk_pay_bonus_rule_daily_target_plan FOREIGN KEY (daily_target_plan_id) REFERENCES fin_target_plan(id) ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT ''skip add fk_pay_bonus_rule_daily_target_plan'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_rule'
  AND COLUMN_NAME = 'daily_target_plan_id';
