-- Batch 2: expiry requirement columns for transactional request/PO flows.
-- Safe to review first, then run in dev/staging before production.

ALTER TABLE pur_division_request_line
    ADD COLUMN expiry_policy VARCHAR(32) NULL AFTER profile_expired_date,
    ADD COLUMN required_expiry_date DATE NULL AFTER expiry_policy,
    ADD COLUMN min_remaining_days INT NULL AFTER required_expiry_date;

ALTER TABLE pur_store_request_line
    ADD COLUMN expiry_policy VARCHAR(32) NULL AFTER profile_expired_date,
    ADD COLUMN required_expiry_date DATE NULL AFTER expiry_policy,
    ADD COLUMN min_remaining_days INT NULL AFTER required_expiry_date;

ALTER TABLE pur_store_request_fulfillment_line
    ADD COLUMN expiry_policy VARCHAR(32) NULL AFTER profile_expired_date,
    ADD COLUMN required_expiry_date DATE NULL AFTER expiry_policy,
    ADD COLUMN min_remaining_days INT NULL AFTER required_expiry_date;

ALTER TABLE pur_purchase_order_line
    ADD COLUMN expiry_policy VARCHAR(32) NULL AFTER expired_date,
    ADD COLUMN required_expiry_date DATE NULL AFTER expiry_policy,
    ADD COLUMN min_remaining_days INT NULL AFTER required_expiry_date;

UPDATE pur_division_request_line
SET expiry_policy = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN 'EXACT_DATE'
        ELSE 'NONE'
    END,
    required_expiry_date = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN profile_expired_date
        ELSE NULL
    END,
    min_remaining_days = NULL
WHERE expiry_policy IS NULL;

UPDATE pur_store_request_line
SET expiry_policy = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN 'EXACT_DATE'
        ELSE 'NONE'
    END,
    required_expiry_date = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN profile_expired_date
        ELSE NULL
    END,
    min_remaining_days = NULL
WHERE expiry_policy IS NULL;

UPDATE pur_store_request_fulfillment_line
SET expiry_policy = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN 'EXACT_DATE'
        ELSE 'NONE'
    END,
    required_expiry_date = CASE
        WHEN COALESCE(profile_expired_date, '') <> '' THEN profile_expired_date
        ELSE NULL
    END,
    min_remaining_days = NULL
WHERE expiry_policy IS NULL;

UPDATE pur_purchase_order_line
SET expiry_policy = CASE
        WHEN COALESCE(required_expiry_date, expired_date, snapshot_expired_date) IS NOT NULL THEN 'EXACT_DATE'
        ELSE 'NONE'
    END,
    required_expiry_date = COALESCE(required_expiry_date, expired_date, snapshot_expired_date),
    min_remaining_days = NULL
WHERE expiry_policy IS NULL;

-- Optional follow-up index if filtering by exact requirement becomes common.
-- CREATE INDEX idx_pur_po_line_required_expiry ON pur_purchase_order_line (required_expiry_date);
-- CREATE INDEX idx_pur_sr_line_required_expiry ON pur_store_request_line (required_expiry_date);

-- Phase 2 cleanup: catalog expiry is no longer part of profile identity.
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
