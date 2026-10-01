-- Bind a one-use POS Mobile reauthentication proof to an exact reservation
-- only when rejecting it also returns a paid deposit. Applied through the
-- managed migration runner after the cashier-close proof migration.
SET NAMES utf8mb4;

ALTER TABLE `pos_mobile_sensitive_action_proof`
  ADD COLUMN IF NOT EXISTS `reservation_id` bigint(20) unsigned DEFAULT NULL AFTER `cashier_session_id`,
  MODIFY COLUMN `action` enum('VOID','REFUND','ORDER_REPRINT','CASHIER_CLOSE','RESERVATION_DEPOSIT_REFUND') NOT NULL,
  ADD KEY `idx_pos_mobile_sensitive_action_proof_reservation_consume` (`mobile_token_id`,`user_id`,`terminal_id`,`action`,`reservation_id`,`expires_at`,`consumed_at`);
