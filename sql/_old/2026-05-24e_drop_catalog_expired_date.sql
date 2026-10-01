-- Cleanup after batch 2 is already applied.
-- Remove legacy expiry column from purchase catalog because expiry is no longer part of catalog/profile identity.

SET @has_catalog_expired_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mst_purchase_catalog'
      AND COLUMN_NAME = 'expired_date'
);

SET @drop_catalog_expired_sql := IF(
    @has_catalog_expired_col > 0,
    'ALTER TABLE mst_purchase_catalog DROP COLUMN expired_date',
    'SELECT ''mst_purchase_catalog.expired_date already removed'' AS info'
);

PREPARE stmt_drop_catalog_expired FROM @drop_catalog_expired_sql;
EXECUTE stmt_drop_catalog_expired;
DEALLOCATE PREPARE stmt_drop_catalog_expired;
