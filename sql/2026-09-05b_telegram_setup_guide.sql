-- Canonical Telegram setup guide navigation and view-only SUPERADMIN grant.
-- Repeat-safe seed only; no credential, setting, or business data is stored.
SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('tg.guide', 'Panduan Setup Telegram', 'TELEGRAM', 'Panduan aman konfigurasi bot, webhook, target, dan jadwal Telegram', 1)
ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), module = VALUES(module),
  description = VALUES(description), is_active = 1, updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'tg.guide', 'Panduan Setup', 'ri-question-line', 'telegram/guide', page.id, 5, 1, 'MAIN', parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code = 'grp.telegram'
WHERE page.page_code = 'tg.guide'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = VALUES(page_id), sort_order = VALUES(sort_order), is_active = 1,
  sidebar_type = 'MAIN', parent_id = VALUES(parent_id), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role.id, page.id, 1, 0, 0, 0, 0, NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'tg.guide'
WHERE role.role_code = 'SUPERADMIN'
ON DUPLICATE KEY UPDATE can_view = 1, can_create = 0, can_edit = 0,
  can_delete = 0, can_export = 0, updated_at = CURRENT_TIMESTAMP;

COMMIT;
