SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05d_sys_app_config_table.sql
-- Tujuan : Tabel konfigurasi app key-value untuk backup & replication
-- ============================================================

CREATE TABLE IF NOT EXISTS sys_app_config (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  config_group VARCHAR(50)  NOT NULL DEFAULT 'general',
  config_key   VARCHAR(120) NOT NULL,
  config_value TEXT         NULL,
  description  VARCHAR(255) NULL,
  updated_by   BIGINT UNSIGNED NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_app_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Default values ────────────────────────────────────────────
INSERT INTO sys_app_config (config_group, config_key, config_value, description) VALUES
  ('backup', 'backup.db_host',          'localhost',  'Host database'),
  ('backup', 'backup.db_port',          '3306',       'Port database'),
  ('backup', 'backup.db_user',          'root',       'User database'),
  ('backup', 'backup.db_pass',          '',           'Password database'),
  ('backup', 'backup.db_name',          'db_finance', 'Nama database'),
  ('backup', 'backup.retention_days',   '3',          'Jumlah hari simpan dump lokal'),
  ('backup', 'backup.repo_remote',      'origin',     'Git remote name'),
  ('backup', 'backup.repo_branch',      'main',       'Git branch target'),
  ('backup', 'backup.exclude_tables',   '',           'Tabel dikecualikan (pisah koma)'),

  ('replication', 'repl.server_role',   'STANDALONE', 'MASTER, SLAVE, atau STANDALONE'),
  ('replication', 'repl.master_host',   '',           'IP/domain Server 1 (Master)'),
  ('replication', 'repl.master_port',   '3306',       'Port MySQL Server 1'),
  ('replication', 'repl.repl_user',     'repl_user',  'User replication di Master'),
  ('replication', 'repl.repl_pass',     '',           'Password replication'),

  ('tunnel',  'tunnel.enabled',         '0',          '1 = aktif SSH tunnel'),
  ('tunnel',  'tunnel.ssh_host',        '',           'SSH host (biasanya = master_host)'),
  ('tunnel',  'tunnel.ssh_user',        'root',       'SSH user'),
  ('tunnel',  'tunnel.local_port',      '3307',       'Port lokal tunnel'),
  ('tunnel',  'tunnel.remote_port',     '3306',       'Port MySQL di server remote')
ON DUPLICATE KEY UPDATE
  config_group = VALUES(config_group),
  description  = VALUES(description),
  updated_at   = CURRENT_TIMESTAMP;

-- ── Menu entry untuk Settings ─────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('system.dbtools.settings', 'DB Tools Settings', 'SYSTEM', 'Pengaturan backup dan replication via UI.', 1)
ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'system.dbtools.settings', 'DB Tools Settings', 'ri-settings-3-line', '/dbtools/settings', p.id, 5, 1, 'MAIN', parent.id
FROM sys_page p JOIN sys_menu parent ON parent.menu_code = 'grp.system'
WHERE p.page_code = 'system.dbtools.settings'
ON DUPLICATE KEY UPDATE menu_label = VALUES(menu_label), icon = VALUES(icon), url = VALUES(url),
  page_id = VALUES(page_id), sort_order = VALUES(sort_order), updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 0, 0, NOW()
FROM auth_role r JOIN sys_page p ON p.page_code = 'system.dbtools.settings'
WHERE r.role_code IN ('SUPERADMIN', 'ADMIN')
ON DUPLICATE KEY UPDATE can_view = 1, can_edit = 1, can_create = 1, updated_at = CURRENT_TIMESTAMP;

SELECT 'sys_app_config created' AS result, COUNT(*) AS total FROM sys_app_config;
