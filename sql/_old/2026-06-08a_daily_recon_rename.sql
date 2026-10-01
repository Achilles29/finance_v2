-- ============================================================
-- Rename opname division → Daily Recon
-- Update: menu label, icon, url di sys_menu
-- Update: page name di sys_page
-- ============================================================

UPDATE sys_menu
SET
    menu_label = 'Daily Recon',
    icon       = 'ri-check-double-line',
    url        = '/inventory/stock/daily-recon/division'
WHERE menu_code = 'purchase.stock.opname.division';

UPDATE sys_page
SET
    page_name = 'Daily Recon Stok Divisi'
WHERE page_code = 'inventory.stock.opname.division.index';
