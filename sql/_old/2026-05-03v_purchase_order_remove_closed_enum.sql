SET NAMES utf8mb4;

-- Optional migration:
-- Remove CLOSED from pur_purchase_order status enum so lifecycle final state is PAID.
-- Run after application code is updated to lifecycle without CLOSED.

START TRANSACTION;

-- Normalize legacy rows before enum shrink.
UPDATE pur_purchase_order
SET status = 'PAID'
WHERE status = 'CLOSED';

ALTER TABLE pur_purchase_order
MODIFY COLUMN status ENUM('DRAFT','APPROVED','ORDERED','REJECTED','PARTIAL_RECEIVED','RECEIVED','PAID','VOID')
NOT NULL DEFAULT 'DRAFT';

COMMIT;

SELECT status, COUNT(*) AS total_rows
FROM pur_purchase_order
GROUP BY status
ORDER BY status;
