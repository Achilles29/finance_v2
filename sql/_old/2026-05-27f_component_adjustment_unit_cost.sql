SET @schema_name = DATABASE();
SET @ddl = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'inv_component_adjustment_line'
        AND COLUMN_NAME = 'unit_cost'
    ),
    'SELECT 1',
    'ALTER TABLE inv_component_adjustment_line ADD COLUMN unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000 AFTER qty_adjust_neg'
  )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
