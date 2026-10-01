-- ============================================================
-- 2026-06-14c  Perbaiki label & urutan sidebar menu Stok Divisi
--
-- Target urutan (semua di bawah parent "Stok Divisi", parent_id=222):
--   sort 100  Daily Recon
--   sort 110  Daily Material Matrix
--   sort 120  Stok Bahan Baku Live
--   sort 130  Stok Bahan Baku Bulanan
--   sort 140  Adjustment Bahan Baku
--   sort 150  mutasi Bahan Baku
--   sort 160  Opening Manual Bahan Baku
--   sort 170  Opname Bahan Baku
--   sort 180  Lot Bahan Baku
--   sort 190  Audit Bahan Baku
--
-- Catatan: URL di DB menggunakan leading slash (/)
-- Idempotent: UPDATE ... WHERE url = ...
-- ============================================================

UPDATE `sys_menu` SET `menu_label` = 'Daily Recon',              `sort_order` = 100 WHERE `url` = '/inventory/stock/daily-recon/division';
UPDATE `sys_menu` SET `menu_label` = 'Daily Material Matrix',    `sort_order` = 110 WHERE `url` = '/inventory-material-daily';
UPDATE `sys_menu` SET `menu_label` = 'Stok Bahan Baku Live',     `sort_order` = 120 WHERE `url` = '/inventory/stock/division';
UPDATE `sys_menu` SET `menu_label` = 'Stok Bahan Baku Bulanan',  `sort_order` = 130 WHERE `url` = '/inventory/stock/division/daily';
UPDATE `sys_menu` SET `menu_label` = 'Adjustment Bahan Baku',    `sort_order` = 140 WHERE `url` = '/inventory/stock/adjustment/division';
UPDATE `sys_menu` SET `menu_label` = 'mutasi Bahan Baku',        `sort_order` = 150 WHERE `url` = '/inventory/stock/division/movement';
UPDATE `sys_menu` SET `menu_label` = 'Opening Manual Bahan Baku',`sort_order` = 160 WHERE `url` = '/inventory/stock/opening/division';
UPDATE `sys_menu` SET `menu_label` = 'Opname Bahan Baku',        `sort_order` = 170 WHERE `url` IN ('/inventory/stock/opname/division/monthly','inventory/stock/opname/division/monthly');
UPDATE `sys_menu` SET `menu_label` = 'Lot Bahan Baku',           `sort_order` = 180 WHERE `url` = '/inventory/stock/division/lot';
UPDATE `sys_menu` SET `menu_label` = 'Audit Bahan Baku',         `sort_order` = 190 WHERE `url` = '/inventory/stock/division/reconcile';

DELETE FROM `sys_menu` WHERE `url` IN (
    '/inventory/stock/opening/division/generate',
    'inventory/stock/opening/division/generate'
);

-- VERIFIKASI:
-- SELECT menu_label, url, sort_order FROM sys_menu WHERE parent_id = 222 ORDER BY sort_order;
