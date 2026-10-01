-- Bind a one-use POS Mobile reauthentication proof to an active cashier session.
-- Applied only through tools/db/migration_runner.php, after 2026-09-06c/06d.
SET NAMES utf8mb4;

ALTER TABLE `pos_mobile_sensitive_action_proof`
  ADD COLUMN IF NOT EXISTS `cashier_session_id` bigint(20) unsigned DEFAULT NULL AFTER `order_id`,
  MODIFY COLUMN `action` enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE') NOT NULL,
  ADD KEY `idx_pos_mobile_sensitive_action_proof_cashier_consume` (`mobile_token_id`,`user_id`,`terminal_id`,`action`,`cashier_session_id`,`expires_at`,`consumed_at`);
