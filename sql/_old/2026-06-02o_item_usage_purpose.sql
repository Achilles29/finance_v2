ALTER TABLE `mst_item`
  ADD COLUMN `default_usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE `pur_store_request_line`
  ADD COLUMN `usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE `pur_division_request_line`
  ADD COLUMN `usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';