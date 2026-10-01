ALTER TABLE inv_warehouse_stock_opening_snapshot
  MODIFY COLUMN profile_key CHAR(64) NOT NULL;

ALTER TABLE inv_division_stock_opening_snapshot
  MODIFY COLUMN profile_key CHAR(64) NOT NULL;

ALTER TABLE inv_stock_opening_snapshot
  MODIFY COLUMN profile_key CHAR(64) NULL;