SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05e_dbtools_menu_simplify.sql
-- Tujuan : Sederhanakan menu menjadi 1 entry "Perlindungan DB"
--          yang mengarah ke halaman gabungan /dbtools
-- ============================================================

-- Update URL dan label semua entry menjadi satu halaman
UPDATE sys_menu SET
  menu_label = 'Perlindungan Database',
  icon       = 'ri-shield-check-line',
  url        = '/dbtools',
  sort_order = 5,
  updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'system.dbtools.settings';

-- Sembunyikan entry panduan yang sudah tidak terpakai sendiri
UPDATE sys_menu SET is_active = 0, updated_at = CURRENT_TIMESTAMP
WHERE menu_code IN ('system.backup.guide', 'system.replication.guide');

SELECT menu_code, menu_label, url, is_active
FROM sys_menu
WHERE menu_code IN ('system.dbtools.settings','system.backup.guide','system.replication.guide');
