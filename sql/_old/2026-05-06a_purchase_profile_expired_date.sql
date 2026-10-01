-- 2026-05-06a
-- Add optional expired date as profile attribute across purchase and inventory tables.

SET @schema_name = DATABASE();

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_add_sql TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = @schema_name
          AND table_name = p_table
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = @schema_name
          AND table_name = p_table
          AND column_name = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_add_sql);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Purchase line and receipt
CALL sp_add_column_if_missing('pur_purchase_order_line', 'expired_date', '`expired_date` DATE NULL AFTER `line_description`');
CALL sp_add_column_if_missing('pur_purchase_order_line', 'snapshot_expired_date', '`snapshot_expired_date` DATE NULL AFTER `snapshot_line_description`');
CALL sp_add_column_if_missing('pur_purchase_receipt_line', 'expired_date', '`expired_date` DATE NULL AFTER `line_description`');

-- Catalog profile cache
CALL sp_add_column_if_missing('mst_purchase_catalog', 'expired_date', '`expired_date` DATE NULL AFTER `line_description`');

-- Inventory profile payload tables
CALL sp_add_column_if_missing('inv_stock_opening_snapshot', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');
CALL sp_add_column_if_missing('inv_stock_movement_log', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');
CALL sp_add_column_if_missing('inv_warehouse_stock_balance', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');
CALL sp_add_column_if_missing('inv_division_stock_balance', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');
CALL sp_add_column_if_missing('inv_warehouse_daily_rollup', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');
CALL sp_add_column_if_missing('inv_division_daily_rollup', 'profile_expired_date', '`profile_expired_date` DATE NULL AFTER `profile_description`');

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
