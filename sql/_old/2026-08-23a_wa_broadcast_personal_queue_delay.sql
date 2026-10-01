SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-23a_wa_broadcast_personal_queue_delay.sql
-- Tujuan : Menyimpan pola jeda 10 urutan untuk antrean pesan
--          personal pada menu WhatsApp Broadcast.
-- ============================================================

START TRANSACTION;

SET @has_wa_broadcast := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'wa_broadcast'
);
SET @sql := IF(
  @has_wa_broadcast > 0,
  'ALTER TABLE wa_broadcast ADD COLUMN IF NOT EXISTS delay_pattern_json TEXT NULL AFTER scheduled_at',
  "SELECT 'skip wa_broadcast.delay_pattern_json because wa_broadcast is not installed' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_wa_broadcast > 0,
  "UPDATE wa_broadcast SET delay_pattern_json = '[2,2,2,2,2,2,2,2,2,2]' WHERE delay_pattern_json IS NULL OR TRIM(delay_pattern_json) = ''",
  "SELECT 'skip wa_broadcast default delay because wa_broadcast is not installed' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SET @sql := IF(
  @has_wa_broadcast > 0,
  'SELECT id, name, delay_pattern_json FROM wa_broadcast ORDER BY id DESC LIMIT 20',
  "SELECT 'wa_broadcast is not installed' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
