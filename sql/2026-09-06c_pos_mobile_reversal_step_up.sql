-- One-use bearer proof for financially sensitive POS Mobile reversals.
-- Applied only through tools/db/migration_runner.php.
SET NAMES utf8mb4;

ALTER TABLE `pos_mobile_auth_token`
  ADD COLUMN IF NOT EXISTS `step_up_failure_window_at` datetime DEFAULT NULL AFTER `last_seen_at`,
  ADD COLUMN IF NOT EXISTS `step_up_failure_count` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `step_up_failure_window_at`,
  ADD COLUMN IF NOT EXISTS `step_up_locked_until` datetime DEFAULT NULL AFTER `step_up_failure_count`;

CREATE TABLE IF NOT EXISTS `pos_mobile_sensitive_action_proof` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proof_hash` char(64) NOT NULL,
  `mobile_token_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `terminal_id` bigint(20) unsigned NOT NULL,
  `action` enum('VOID','REFUND') NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uq_pos_mobile_sensitive_action_proof_hash` (`proof_hash`) USING BTREE,
  KEY `idx_pos_mobile_sensitive_action_proof_consume` (`mobile_token_id`,`user_id`,`terminal_id`,`action`,`order_id`,`expires_at`,`consumed_at`) USING BTREE,
  KEY `idx_pos_mobile_sensitive_action_proof_expiry` (`expires_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One-use reauthentication proofs for POS Mobile void/refund.';
