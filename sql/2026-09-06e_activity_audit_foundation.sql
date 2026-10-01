-- Central, metadata-only activity registry. Applied only via the managed
-- migration runner after the schema-migration registry foundation.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `aud_access_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `session_log_id` bigint(20) unsigned DEFAULT NULL,
  `page_code` varchar(100) DEFAULT NULL,
  `route_path` varchar(255) NOT NULL,
  `request_method` varchar(10) NOT NULL DEFAULT 'GET',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_label` varchar(80) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_aud_access_event_created` (`created_at`,`id`),
  KEY `idx_aud_access_event_user_created` (`user_id`,`created_at`),
  KEY `idx_aud_access_event_page_created` (`page_code`,`created_at`),
  KEY `idx_aud_access_event_session_created` (`session_log_id`,`created_at`),
  CONSTRAINT `fk_aud_access_event_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`id`),
  CONSTRAINT `fk_aud_access_event_session` FOREIGN KEY (`session_log_id`) REFERENCES `auth_session_log` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Metadata-only authenticated web page access audit';

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('system.activity_audit.index', 'Log Aktivitas', 'SYSTEM', 'Jejak login, akses halaman, dan transaksi tanpa payload sensitif', 1)
ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), module = VALUES(module),
  description = VALUES(description), is_active = 1, updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'system.activity_audit', 'Log Aktivitas', 'ri-shield-user-line', 'system/activity-audit', page.id, 95, 1, 'MAIN', parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.system'
WHERE page.page_code = 'system.activity_audit.index'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = 1,
  sidebar_type = 'MAIN', parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

-- The registry exposes IP/device metadata. Only SUPERADMIN is granted by
-- default; another role must receive explicit view access through RBAC. Keep
-- the canonical SUPERADMIN full-action matrix intact for clean installs.
INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role.id, page.id, 1, 1, 1, 1, 1, NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'system.activity_audit.index'
WHERE role.role_code = 'SUPERADMIN'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 1, can_edit = 1,
  can_delete = 1, can_export = 1, updated_at = CURRENT_TIMESTAMP;

COMMIT;
