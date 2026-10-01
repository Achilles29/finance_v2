ALTER TABLE pur_division_request_line
  ADD COLUMN IF NOT EXISTS request_uom_mode ENUM('BUY','CONTENT') NOT NULL DEFAULT 'BUY' AFTER profile_content_uom_code;

UPDATE pur_division_request_line
SET request_uom_mode = 'BUY'
WHERE request_uom_mode IS NULL OR request_uom_mode = '';
