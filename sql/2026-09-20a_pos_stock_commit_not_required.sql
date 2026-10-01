-- Add the state already emitted by POS confirmation for products without recipes.
-- Schema-only correction for clean/upgrade packages. No historical order backfill.
-- Runner verifies the original column and rejects invalid/partial schema or empty legacy states.
-- No automatic down migration: removing an in-use ENUM value is not a safe rollback.
ALTER TABLE pos_order
 MODIFY COLUMN stock_commit_status ENUM('PENDING','QUEUED','PROCESSING','POSTED','FAILED','REVERSED','NOT_REQUIRED') NOT NULL DEFAULT 'PENDING';
