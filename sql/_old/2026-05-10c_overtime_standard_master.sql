SET NAMES utf8mb4;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS att_overtime_standard (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  standard_code VARCHAR(40) NOT NULL,
  standard_name VARCHAR(120) NOT NULL,
  hourly_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_overtime_standard_code (standard_code),
  KEY idx_att_overtime_standard_active_rate (is_active, hourly_rate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE att_overtime_entry
  ADD COLUMN IF NOT EXISTS overtime_standard_id BIGINT UNSIGNED NULL AFTER employee_id;

SET @has_fk := (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'att_overtime_entry'
    AND COLUMN_NAME = 'overtime_standard_id'
    AND REFERENCED_TABLE_NAME = 'att_overtime_standard'
);
SET @fk_sql := IF(
  @has_fk = 0,
  'ALTER TABLE att_overtime_entry ADD CONSTRAINT fk_att_overtime_entry_standard FOREIGN KEY (overtime_standard_id) REFERENCES att_overtime_standard(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @fk_sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

INSERT INTO att_overtime_standard (standard_code, standard_name, hourly_rate, notes, is_active)
VALUES
  ('OT-7000', 'Standar Lembur 7.000', 7000, 'Default awal', 1),
  ('OT-8000', 'Standar Lembur 8.000', 8000, 'Default awal', 1)
ON DUPLICATE KEY UPDATE
  standard_name = VALUES(standard_name),
  hourly_rate = VALUES(hourly_rate),
  notes = VALUES(notes),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

UPDATE att_overtime_entry oe
JOIN (
  SELECT s1.id, s1.hourly_rate
  FROM att_overtime_standard s1
  JOIN (
    SELECT hourly_rate, MIN(id) AS min_id
    FROM att_overtime_standard
    GROUP BY hourly_rate
  ) pick ON pick.min_id = s1.id
) s ON s.hourly_rate = oe.overtime_rate
SET oe.overtime_standard_id = s.id
WHERE oe.overtime_standard_id IS NULL;

COMMIT;
