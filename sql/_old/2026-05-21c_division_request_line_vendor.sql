ALTER TABLE pur_division_request_line
  ADD COLUMN IF NOT EXISTS vendor_id BIGINT UNSIGNED NULL AFTER request_uom_mode,
  ADD INDEX IF NOT EXISTS idx_pur_division_request_line_vendor (vendor_id),
  ADD CONSTRAINT fk_pur_division_request_line_vendor FOREIGN KEY (vendor_id) REFERENCES mst_vendor(id);
