SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05c_system_tools_url_fix.sql
-- Tujuan : Fix URL sys_menu dari /system/* ke /dbtools/*
--          Alasan: /system/ adalah reserved path di CI3
-- ============================================================

UPDATE sys_menu SET url = '/dbtools/backup-guide',     updated_at = CURRENT_TIMESTAMP WHERE menu_code = 'system.backup.guide';
UPDATE sys_menu SET url = '/dbtools/replication-guide', updated_at = CURRENT_TIMESTAMP WHERE menu_code = 'system.replication.guide';

SELECT menu_code, url FROM sys_menu WHERE menu_code IN ('system.backup.guide', 'system.replication.guide');
