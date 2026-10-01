SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-12d_extend_bonus_penalty_auto_service_peer_settings.sql
-- Tujuan :
-- 1) Menambahkan parameter otomatis untuk penalti SERVICE
--    agar batas waktu saji dan step pengurang poin bisa diatur dari UI
-- 2) Menambahkan parameter otomatis untuk penalti PEER
--    agar potongan poin per rating bintang bisa diatur dari UI
-- 3) Mengisi default awal yang langsung cocok dengan pola operasional
-- ============================================================

START TRANSACTION;

SET @schema_name := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'service_target_minute') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN service_target_minute DECIMAL(10,2) NOT NULL DEFAULT 15.00 AFTER default_amount_deducted",
  "SELECT 'skip service_target_minute' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'service_step_minute') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN service_step_minute DECIMAL(10,2) NOT NULL DEFAULT 5.00 AFTER service_target_minute",
  "SELECT 'skip service_step_minute' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'peer_star_4_points') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN peer_star_4_points DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER service_step_minute",
  "SELECT 'skip peer_star_4_points' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'peer_star_3_points') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN peer_star_3_points DECIMAL(10,2) NOT NULL DEFAULT 2.00 AFTER peer_star_4_points",
  "SELECT 'skip peer_star_3_points' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'peer_star_2_points') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN peer_star_2_points DECIMAL(10,2) NOT NULL DEFAULT 3.00 AFTER peer_star_3_points",
  "SELECT 'skip peer_star_2_points' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @schema_name
     AND TABLE_NAME = 'pay_bonus_penalty_type'
     AND COLUMN_NAME = 'peer_star_1_points') = 0,
  "ALTER TABLE pay_bonus_penalty_type ADD COLUMN peer_star_1_points DECIMAL(10,2) NOT NULL DEFAULT 4.00 AFTER peer_star_2_points",
  "SELECT 'skip peer_star_1_points' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pay_bonus_penalty_type
SET
  service_target_minute = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'SERVICE' AND COALESCE(service_target_minute, 0) <= 0 THEN 15.00
    ELSE service_target_minute
  END,
  service_step_minute = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'SERVICE' AND COALESCE(service_step_minute, 0) <= 0 THEN 5.00
    ELSE service_step_minute
  END,
  peer_star_4_points = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'PEER' AND COALESCE(peer_star_4_points, 0) <= 0 THEN 1.00
    ELSE peer_star_4_points
  END,
  peer_star_3_points = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'PEER' AND COALESCE(peer_star_3_points, 0) <= 0 THEN 2.00
    ELSE peer_star_3_points
  END,
  peer_star_2_points = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'PEER' AND COALESCE(peer_star_2_points, 0) <= 0 THEN 3.00
    ELSE peer_star_2_points
  END,
  peer_star_1_points = CASE
    WHEN UPPER(COALESCE(auto_source, '')) = 'PEER' AND COALESCE(peer_star_1_points, 0) <= 0 THEN 4.00
    ELSE peer_star_1_points
  END
WHERE UPPER(COALESCE(auto_source, '')) IN ('SERVICE', 'PEER');

COMMIT;

SELECT
  penalty_code,
  auto_source,
  service_target_minute,
  service_step_minute,
  peer_star_4_points,
  peer_star_3_points,
  peer_star_2_points,
  peer_star_1_points
FROM pay_bonus_penalty_type
WHERE UPPER(COALESCE(auto_source, '')) IN ('SERVICE', 'PEER')
ORDER BY sort_order, penalty_name;
