-- Expand purchase order status to include REJECTED and PAID.
-- Run once on db_finance.

SET @tbl_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_order'
);

SET @sql := IF(
  @tbl_exists = 1,
  "ALTER TABLE pur_purchase_order MODIFY COLUMN status ENUM('DRAFT','APPROVED','ORDERED','REJECTED','PARTIAL_RECEIVED','RECEIVED','PAID','CLOSED','VOID') NOT NULL DEFAULT 'DRAFT'",
  "SELECT 'SKIP: pur_purchase_order not found' AS message"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
