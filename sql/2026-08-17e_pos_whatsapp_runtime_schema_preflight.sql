SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql
-- Tujuan :
-- 1) Memindahkan perubahan schema POS customer ke migration resmi
-- 2) Memindahkan upgrade schema WhatsApp dari request halaman
-- 3) Mencegah DDL saat operator membuka POS atau WhatsApp
--
-- Catatan:
-- - Aman dijalankan ulang.
-- - Bagian WhatsApp dilewati bila modul dasarnya belum dipasang.
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_order
  ADD COLUMN IF NOT EXISTS customer_name VARCHAR(150) NULL;

SET @has_wa_session := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'wa_session'
);
SET @sql := IF(
  @has_wa_session > 0,
  'ALTER TABLE wa_session ADD COLUMN IF NOT EXISTS node_path VARCHAR(500) DEFAULT NULL',
  "SELECT 'skip wa_session.node_path because wa_session is not installed' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_wa_broadcast := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'wa_broadcast'
);
SET @sql := IF(
  @has_wa_broadcast > 0,
  "ALTER TABLE wa_broadcast MODIFY COLUMN target_type ENUM('MANUAL','SELECTED_MEMBERS','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM') NOT NULL DEFAULT 'MANUAL', ADD COLUMN IF NOT EXISTS media_path VARCHAR(500) NULL, ADD COLUMN IF NOT EXISTS media_url VARCHAR(500) NULL, ADD COLUMN IF NOT EXISTS media_mime VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS media_name VARCHAR(255) NULL",
  "SELECT 'skip wa_broadcast media fields because wa_broadcast is not installed' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS wa_report_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  report_type ENUM('OMZET_TODAY','PURCHASE_TODAY','ADJUSTMENT_TODAY','PO_SR_TODAY') NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  send_time TIME NOT NULL,
  date_offset_days SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) DEFAULT NULL,
  last_run_at DATETIME DEFAULT NULL,
  last_sent_at DATETIME DEFAULT NULL,
  last_sent_date DATE DEFAULT NULL,
  last_status ENUM('SENT','FAILED','SKIPPED') DEFAULT NULL,
  last_error VARCHAR(500) DEFAULT NULL,
  run_claim_token CHAR(32) DEFAULT NULL,
  run_claimed_at DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_active_time (is_active, send_time),
  KEY idx_last_sent_date (last_sent_date),
  KEY idx_template (template_id),
  KEY idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'pos_order.customer_name' AS schema_key, COUNT(*) AS total
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_order'
  AND COLUMN_NAME = 'customer_name'
UNION ALL
SELECT 'wa_session.node_path', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wa_session'
  AND COLUMN_NAME = 'node_path'
UNION ALL
SELECT 'wa_broadcast.media_path', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wa_broadcast'
  AND COLUMN_NAME = 'media_path'
UNION ALL
SELECT 'wa_report_schedule', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wa_report_schedule';
