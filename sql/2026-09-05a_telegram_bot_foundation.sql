-- Telegram MVP foundation. Repeat-safe for MariaDB/MySQL; no customer target
-- or credential is seeded. Apply only after 2026-09-04c via migration runner.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tg_target (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  chat_id VARCHAR(32) NOT NULL,
  target_type ENUM('GROUP','SUPERGROUP','CHANNEL') NOT NULL,
  title VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tg_target_chat (chat_id),
  KEY idx_tg_target_active (is_active),
  CONSTRAINT fk_tg_target_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id),
  CONSTRAINT fk_tg_target_updated_by FOREIGN KEY (updated_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Allowlist group/channel internal Telegram';

CREATE TABLE IF NOT EXISTS tg_schedule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_id BIGINT UNSIGNED NOT NULL,
  schedule_name VARCHAR(120) NOT NULL,
  report_type ENUM('OMZET_TODAY','PURCHASE_TODAY') NOT NULL,
  send_time TIME NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_enqueued_date DATE NULL,
  last_queue_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tg_schedule_due (is_active, send_time, last_enqueued_date),
  KEY idx_tg_schedule_target (target_id),
  CONSTRAINT fk_tg_schedule_target FOREIGN KEY (target_id) REFERENCES tg_target(id),
  CONSTRAINT fk_tg_schedule_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id),
  CONSTRAINT fk_tg_schedule_updated_by FOREIGN KEY (updated_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jadwal laporan Telegram harian';

CREATE TABLE IF NOT EXISTS tg_delivery_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idempotency_key VARCHAR(191) NOT NULL,
  source_type ENUM('COMMAND','SCHEDULE','TEST','RESEND') NOT NULL,
  source_ref VARCHAR(64) NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  report_type ENUM('MENU','OMZET_TODAY','PURCHASE_TODAY') NULL,
  report_date DATE NULL,
  message_text TEXT NULL,
  status ENUM('PENDING','PROCESSING','SENT','FAILED','UNKNOWN') NOT NULL DEFAULT 'PENDING',
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lease_token CHAR(32) NULL,
  leased_at DATETIME NULL,
  sent_at DATETIME NULL,
  http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  telegram_message_id BIGINT NULL,
  last_error VARCHAR(500) NULL,
  resolution_action ENUM('CONFIRM_SENT','CLOSE_FAILED','RESEND') NULL,
  resolution_reason VARCHAR(500) NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_queue_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tg_queue_idempotency (idempotency_key),
  KEY idx_tg_queue_claim (status, available_at, leased_at, id),
  KEY idx_tg_queue_target (target_id),
  KEY idx_tg_queue_resolution (resolution_action, resolved_at),
  KEY idx_tg_queue_resolution_queue (resolution_queue_id),
  CONSTRAINT fk_tg_queue_target FOREIGN KEY (target_id) REFERENCES tg_target(id),
  CONSTRAINT fk_tg_queue_resolved_by FOREIGN KEY (resolved_by) REFERENCES auth_user(id),
  CONSTRAINT fk_tg_queue_resolution_queue FOREIGN KEY (resolution_queue_id) REFERENCES tg_delivery_queue(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotent leased Telegram delivery queue';

CREATE TABLE IF NOT EXISTS tg_webhook_update (
  update_id BIGINT NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  queue_id BIGINT UNSIGNED NULL,
  chat_id VARCHAR(32) NOT NULL,
  command_name VARCHAR(32) NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (update_id),
  KEY idx_tg_webhook_target (target_id, received_at),
  KEY idx_tg_webhook_queue (queue_id),
  CONSTRAINT fk_tg_webhook_target FOREIGN KEY (target_id) REFERENCES tg_target(id),
  CONSTRAINT fk_tg_webhook_queue FOREIGN KEY (queue_id) REFERENCES tg_delivery_queue(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deduplication record for accepted Telegram updates';

CREATE TABLE IF NOT EXISTS tg_delivery_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  queue_id BIGINT UNSIGNED NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL,
  delivery_status ENUM('SENT','FAILED','UNKNOWN') NOT NULL,
  http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  telegram_message_id BIGINT NULL,
  message_preview VARCHAR(500) NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tg_log_created (created_at, id),
  KEY idx_tg_log_queue (queue_id),
  KEY idx_tg_log_target (target_id),
  CONSTRAINT fk_tg_log_queue FOREIGN KEY (queue_id) REFERENCES tg_delivery_queue(id),
  CONSTRAINT fk_tg_log_target FOREIGN KEY (target_id) REFERENCES tg_target(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram delivery attempt audit log';

CREATE TABLE IF NOT EXISTS tg_setting (
  setting_key VARCHAR(100) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  description VARCHAR(255) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_tg_setting_updated_by FOREIGN KEY (updated_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Non-secret Telegram module settings';

START TRANSACTION;

INSERT INTO tg_setting (setting_key, setting_value, description, updated_by)
VALUES ('telegram.enabled', '1', 'Master switch Telegram bot', NULL)
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
('tg.dashboard', 'Telegram Dashboard', 'TELEGRAM', 'Allowlist target group/channel internal Telegram', 1),
('tg.delivery', 'Telegram Delivery', 'TELEGRAM', 'Jadwal dan antrean delivery laporan Telegram', 1),
('tg.log', 'Telegram Log', 'TELEGRAM', 'Audit hasil delivery Telegram', 1),
('tg.settings', 'Telegram Settings', 'TELEGRAM', 'Readiness credential dan test send Telegram', 1)
ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), module = VALUES(module),
  description = VALUES(description), is_active = 1, updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
VALUES ('grp.telegram', 'Telegram', 'ri-telegram-line', NULL, NULL, 960, 1, 'MAIN', NULL)
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = NULL,
  page_id = NULL, sort_order = VALUES(sort_order), is_active = 1, sidebar_type = 'MAIN',
  parent_id = NULL, updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT seed.menu_code, seed.menu_label, seed.icon, seed.url, page.id, seed.sort_order, 1, 'MAIN', parent.id
FROM (
  SELECT 'tg.dashboard' menu_code, 'Dashboard & Target' menu_label, 'ri-dashboard-line' icon, 'telegram' url, 1 sort_order, 'tg.dashboard' page_code
  UNION ALL SELECT 'tg.delivery', 'Jadwal & Delivery', 'ri-send-plane-line', 'telegram/delivery', 2, 'tg.delivery'
  UNION ALL SELECT 'tg.log', 'Log Pengiriman', 'ri-history-line', 'telegram/log', 3, 'tg.log'
  UNION ALL SELECT 'tg.settings', 'Pengaturan', 'ri-settings-3-line', 'telegram/settings', 4, 'tg.settings'
) seed
JOIN sys_page page ON page.page_code = seed.page_code
JOIN sys_menu parent ON parent.menu_code = 'grp.telegram'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = 1,
  sidebar_type = 'MAIN', parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

-- Intentionally seed full access only for SUPERADMIN. Other roles must be
-- granted explicitly through the existing role matrix after deployment.
INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role.id, page.id, 1, 1, 1, 1, 1, NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code IN ('tg.dashboard','tg.delivery','tg.log','tg.settings')
WHERE role.role_code = 'SUPERADMIN'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1,
  can_delete = 1, can_export = 1, updated_at = CURRENT_TIMESTAMP;

COMMIT;
