SET NAMES utf8mb4;

-- ============================================================
-- 2026-07-09b - Rename movement lama jadi Log Bahan Baku
-- Tujuan: membedakan halaman movement/log lama dari modul
--         transfer baru bernama Mutasi Bahan Baku.
-- ============================================================
START TRANSACTION;

UPDATE sys_page
SET
  page_name = 'Log Bahan Baku Divisi',
  description = 'Log keluar masuk stok bahan baku divisi dari inv_stock_movement_log',
  updated_at = CURRENT_TIMESTAMP
WHERE page_code = 'purchase.stock.division.movement.index';

UPDATE sys_menu
SET
  menu_label = 'Log Bahan Baku',
  updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'purchase.stock.division.movement';

COMMIT;

SELECT page_code, page_name
FROM sys_page
WHERE page_code = 'purchase.stock.division.movement.index';

SELECT menu_code, menu_label, url
FROM sys_menu
WHERE menu_code = 'purchase.stock.division.movement';
