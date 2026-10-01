-- Tambah destination_type pada header request divisi agar BAR/KITCHEN bisa dibedakan Reguler vs Event.

SET @has_destination_type := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_division_request'
    AND COLUMN_NAME = 'destination_type'
);

SET @sql := IF(
  @has_destination_type = 0,
  "ALTER TABLE pur_division_request
      ADD COLUMN destination_type ENUM('BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL DEFAULT 'OTHER' AFTER division_id,
      ADD KEY idx_pur_division_request_dest (destination_type)",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE pur_division_request r
LEFT JOIN mst_operational_division d ON d.id = r.division_id
SET r.destination_type = CASE
    WHEN UPPER(TRIM(COALESCE(d.code, ''))) = 'BAR' THEN 'BAR'
    WHEN UPPER(TRIM(COALESCE(d.code, ''))) = 'KITCHEN' THEN 'KITCHEN'
    WHEN UPPER(TRIM(COALESCE(d.code, ''))) = 'OFFICE' THEN 'OFFICE'
  WHEN CONCAT(' ', UPPER(TRIM(COALESCE(d.name, ''))), ' ') LIKE '% BAR %' THEN 'BAR'
  WHEN CONCAT(' ', UPPER(TRIM(COALESCE(d.name, ''))), ' ') LIKE '% KITCHEN %' THEN 'KITCHEN'
  WHEN CONCAT(' ', UPPER(TRIM(COALESCE(d.name, ''))), ' ') LIKE '% DAPUR %' THEN 'KITCHEN'
  WHEN CONCAT(' ', UPPER(TRIM(COALESCE(d.name, ''))), ' ') LIKE '% OFFICE %' THEN 'OFFICE'
    ELSE 'OTHER'
END
WHERE r.destination_type IS NULL
   OR r.destination_type = ''
   OR r.destination_type = 'OTHER';