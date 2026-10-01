-- New empty integration tables only. No recipients, credentials or business data seeded.
-- Keep these tables on code rollback: delivery evidence must not be discarded.
CREATE TABLE IF NOT EXISTS app_notification_rule (
 channel VARCHAR(12) NOT NULL,
 event_code VARCHAR(32) NOT NULL,
 is_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
 order_start_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 targets_json TEXT NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL,
 last_worker_at DATETIME NULL,
 PRIMARY KEY (channel, event_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_notification_queue (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 delivery_key CHAR(64) NOT NULL,
 channel VARCHAR(12) NOT NULL,
 event_code VARCHAR(32) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 target_key VARCHAR(100) NOT NULL,
 destination VARCHAR(100) NOT NULL,
 target_label VARCHAR(190) NOT NULL,
 message_text TEXT NOT NULL,
 status VARCHAR(12) NOT NULL DEFAULT 'PENDING',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 claimed_at DATETIME NULL,
 sent_at DATETIME NULL,
 last_error VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_notification_delivery (delivery_key),
 KEY idx_notification_pending (channel, status, id),
 KEY idx_notification_source (channel, event_code, source_id, target_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
