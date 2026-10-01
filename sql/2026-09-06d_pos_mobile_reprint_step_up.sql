-- Extend immutable POS Mobile proof schema for a one-use reprint proof.
-- Applied only through tools/db/migration_runner.php, after 2026-09-06c.
SET NAMES utf8mb4;

ALTER TABLE `pos_mobile_sensitive_action_proof`
  MODIFY COLUMN `action` enum('VOID','REFUND','ORDER_REPRINT') NOT NULL;
