SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05b_system_tools_menu_seed.sql
-- Tujuan : Daftarkan halaman Backup Guide dan Replication Guide
--          ke sys_page + sys_menu di grup SYSTEM/ADMIN
-- ============================================================

START TRANSACTION;

-- ── sys_page ──────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES
  ('system.backup.guide',       'Panduan Backup DB',            'SYSTEM', 'Panduan dan status backup otomatis via mysqldump + GitHub.', 1),
  ('system.replication.guide',  'Panduan Replication Failover', 'SYSTEM', 'Panduan replication MySQL Master-Slave dan prosedur failover manual.', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ── Cari atau buat grup SYSTEM di sidebar ────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'grp.system', 'System Tools', 'ri-tools-line', NULL, NULL, 999, 1, 'MAIN', NULL
WHERE NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code = 'grp.system')
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

-- ── Sub-menu Backup Guide ─────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'system.backup.guide',
  'Panduan Backup DB',
  'ri-database-2-line',
  '/system/backup-guide',
  p.id,
  10,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.system'
WHERE p.page_code = 'system.backup.guide'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  url        = VALUES(url),
  page_id    = VALUES(page_id),
  parent_id  = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active  = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ── Sub-menu Replication Guide ────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'system.replication.guide',
  'Replication & Failover',
  'ri-server-line',
  '/system/replication-guide',
  p.id,
  20,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.system'
WHERE p.page_code = 'system.replication.guide'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  url        = VALUES(url),
  page_id    = VALUES(page_id),
  parent_id  = VALUES(parent_id),
  sort_order = VALUES(sort_order),
  is_active  = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ── Permission: hanya SUPERADMIN dan ADMIN ────────────────────
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT r.id, p.id, 1, 0, 0, 0, 0, NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('system.backup.guide', 'system.replication.guide')
WHERE r.role_code IN ('SUPERADMIN', 'ADMIN')
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT page_code AS seed_key, COUNT(*) AS total
FROM sys_page
WHERE page_code IN ('system.backup.guide', 'system.replication.guide')
GROUP BY page_code;
