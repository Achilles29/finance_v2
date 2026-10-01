SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14b_extend_bonus_rule_penalty_type_core_alignment.sql
-- Tujuan :
-- 1) Menambah field rule bonus agar selaras dengan konsep core
-- 2) Menambah klasifikasi penalti AUTO / MANUAL / SEMI_MANUAL
-- 3) Menambah metadata trigger, verifikasi, dan bukti penalti
--
-- Catatan:
-- - Aman dijalankan ulang
-- - Ditujukan untuk database yang sudah telanjur memakai foundation bonus lama
-- ============================================================

START TRANSACTION;

SET @db_name := DATABASE();

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_rule'
          AND COLUMN_NAME = 'threshold_amount'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_rule ADD COLUMN threshold_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER active_end_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_rule'
          AND COLUMN_NAME = 'pool_formula_type'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_rule ADD COLUMN pool_formula_type ENUM(''PERCENTAGE'',''FIXED_STEP'') NOT NULL DEFAULT ''PERCENTAGE'' AFTER threshold_amount'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_rule'
          AND COLUMN_NAME = 'pool_formula_value'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_rule ADD COLUMN pool_formula_value DECIMAL(12,4) NOT NULL DEFAULT 3.0000 AFTER pool_formula_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_rule'
          AND COLUMN_NAME = 'min_shift_base_pct'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_rule ADD COLUMN min_shift_base_pct DECIMAL(9,2) NOT NULL DEFAULT 30.00 AFTER pool_formula_value'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE pay_bonus_penalty_type
  MODIFY COLUMN category ENUM('ATTENDANCE','DISCIPLINE','PERFORMANCE','SERVICE','PROPERTY','SOCIAL_MEDIA','HYGIENE','OTHER') NOT NULL DEFAULT 'OTHER';

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'deduction_mode'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN deduction_mode ENUM(''FIXED_POINT'',''FIXED_AMOUNT'',''VARIABLE'') NOT NULL DEFAULT ''FIXED_POINT'' AFTER category'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'behavior_mode'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN behavior_mode ENUM(''AUTO'',''MANUAL'',''SEMI_MANUAL'') NOT NULL DEFAULT ''MANUAL'' AFTER is_manual_only'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'auto_source'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN auto_source ENUM(''ATTENDANCE'',''SERVICE'',''TARGET'',''PEER'',''SOCIAL_MEDIA'',''AUDIT'',''CHECKLIST'',''OTHER'') NULL AFTER behavior_mode'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'attendance_trigger'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN attendance_trigger VARCHAR(60) NULL AFTER auto_source'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'verification_cycle'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN verification_cycle ENUM(''PER_EVENT'',''DAILY'',''MONTHLY'',''UNTIL_CHANGED'') NOT NULL DEFAULT ''PER_EVENT'' AFTER attendance_trigger'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'pay_bonus_penalty_type'
          AND COLUMN_NAME = 'requires_evidence'
    ),
    'SELECT 1',
    'ALTER TABLE pay_bonus_penalty_type ADD COLUMN requires_evidence TINYINT(1) NOT NULL DEFAULT 0 AFTER approval_required'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pay_bonus_penalty_type
SET
  deduction_mode = COALESCE(deduction_mode, 'FIXED_POINT'),
  behavior_mode = CASE
    WHEN penalty_code IN ('LATE_MINOR','LATE_MAJOR','LATE_SEVERE','EARLY_OUT','EARLY_OUT_MINOR','EARLY_OUT_MODERATE','EARLY_OUT_TOLERANCE','ALPHA','ABSENT_PH','BONUS-PH-TAKEN') THEN 'AUTO'
    WHEN penalty_code IN ('BONUS-NO-FOLLOW-IG','BONUS-NO-STORY-TAG') THEN 'SEMI_MANUAL'
    WHEN COALESCE(is_manual_only, 0) = 1 THEN 'MANUAL'
    ELSE COALESCE(behavior_mode, 'MANUAL')
  END,
  auto_source = CASE
    WHEN penalty_code IN ('LATE_MINOR','LATE_MAJOR','LATE_SEVERE','EARLY_OUT','EARLY_OUT_MINOR','EARLY_OUT_MODERATE','EARLY_OUT_TOLERANCE','ALPHA','ABSENT_PH','BONUS-PH-TAKEN') THEN 'ATTENDANCE'
    WHEN penalty_code IN ('BONUS-NO-FOLLOW-IG','BONUS-NO-STORY-TAG') THEN 'SOCIAL_MEDIA'
    ELSE auto_source
  END,
  attendance_trigger = CASE
    WHEN penalty_code = 'LATE_MINOR' THEN 'LATE_MINOR'
    WHEN penalty_code = 'LATE_MAJOR' THEN 'LATE_MAJOR'
    WHEN penalty_code = 'LATE_SEVERE' THEN 'LATE_SEVERE'
    WHEN penalty_code = 'EARLY_OUT' THEN 'EARLY_OUT'
    WHEN penalty_code = 'EARLY_OUT_MINOR' THEN 'EARLY_OUT_MINOR'
    WHEN penalty_code = 'EARLY_OUT_MODERATE' THEN 'EARLY_OUT_MODERATE'
    WHEN penalty_code = 'EARLY_OUT_TOLERANCE' THEN 'EARLY_OUT_TOLERANCE'
    WHEN penalty_code = 'ALPHA' THEN 'ALPHA'
    WHEN penalty_code = 'ABSENT_PH' THEN 'ABSENT_PH'
    WHEN penalty_code = 'BONUS-PH-TAKEN' THEN 'PH_TAKEN'
    ELSE attendance_trigger
  END,
  verification_cycle = CASE
    WHEN penalty_code = 'BONUS-NO-FOLLOW-IG' THEN 'UNTIL_CHANGED'
    WHEN penalty_code = 'BONUS-NO-STORY-TAG' THEN 'DAILY'
    ELSE COALESCE(verification_cycle, 'PER_EVENT')
  END,
  requires_evidence = CASE
    WHEN penalty_code IN ('BONUS-NO-FOLLOW-IG','BONUS-NO-STORY-TAG','VIOLATION_MINOR','VIOLATION_MAJOR','VIOLATION_SEVERE','CUSTOMER_COMPLAINT','BREAKAGE_MINOR','BREAKAGE_MAJOR','BONUS-KITCHEN-DIRTY','BONUS-BAR-DIRTY') THEN 1
    ELSE COALESCE(requires_evidence, 0)
  END;

COMMIT;

SELECT
  'pay_bonus_rule.threshold_amount' AS metric,
  COUNT(*) AS total
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_rule'
  AND COLUMN_NAME = 'threshold_amount'
UNION ALL
SELECT 'pay_bonus_penalty_type.behavior_mode', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_penalty_type'
  AND COLUMN_NAME = 'behavior_mode'
UNION ALL
SELECT 'pay_bonus_penalty_type.auto_source', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pay_bonus_penalty_type'
  AND COLUMN_NAME = 'auto_source';
