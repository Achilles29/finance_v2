SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28a11_pos_printer_agent_hardening.sql
-- Tujuan :
-- 1) Menyiapkan field agent bluetooth printer untuk POS desktop
-- 2) Menambah support connection_type=BLUETOOTH
-- 3) Menjadi fondasi Python local printer agent ala core
-- Catatan:
-- - Aman dijalankan ulang secara bertahap.
-- - Fokus ke desktop bluetooth agent; printer mobile menyusul.
-- ============================================================

START TRANSACTION;

SET @has_agent_host := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND COLUMN_NAME = 'agent_host'
);
SET @sql_add_agent_host := IF(
  @has_agent_host = 0,
  'ALTER TABLE pos_printer_desktop_device ADD COLUMN agent_host VARCHAR(120) NULL AFTER terminal_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_agent_host; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_mac_address := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND COLUMN_NAME = 'mac_address'
);
SET @sql_add_mac_address := IF(
  @has_mac_address = 0,
  'ALTER TABLE pos_printer_desktop_device ADD COLUMN mac_address VARCHAR(40) NULL AFTER agent_host',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_mac_address; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_python_port := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND COLUMN_NAME = 'python_port'
);
SET @sql_add_python_port := IF(
  @has_python_port = 0,
  'ALTER TABLE pos_printer_desktop_device ADD COLUMN python_port INT UNSIGNED NULL AFTER port',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_python_port; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_agent_host := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND INDEX_NAME = 'idx_pos_printer_device_agent_host'
);
SET @sql_add_idx_agent_host := IF(
  @has_idx_agent_host = 0,
  'ALTER TABLE pos_printer_desktop_device ADD KEY idx_pos_printer_device_agent_host (agent_host)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_idx_agent_host; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_python_port := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND INDEX_NAME = 'idx_pos_printer_device_python_port'
);
SET @sql_add_idx_python_port := IF(
  @has_idx_python_port = 0,
  'ALTER TABLE pos_printer_desktop_device ADD KEY idx_pos_printer_device_python_port (python_port)',
  'SELECT 1'
);
PREPARE stmt FROM @sql_add_idx_python_port; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @needs_bluetooth_enum := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pos_printer_desktop_device'
    AND COLUMN_NAME = 'connection_type'
    AND COLUMN_TYPE NOT LIKE '%BLUETOOTH%'
);
SET @sql_bluetooth_enum := IF(
  @needs_bluetooth_enum > 0,
  'ALTER TABLE pos_printer_desktop_device MODIFY connection_type ENUM(''USB'',''LAN'',''SHARED'',''BLUETOOTH'',''OTHER'') NOT NULL DEFAULT ''USB''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_bluetooth_enum; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_printer_desktop_device'
  AND COLUMN_NAME IN ('agent_host','mac_address','python_port','connection_type')
ORDER BY COLUMN_NAME;
