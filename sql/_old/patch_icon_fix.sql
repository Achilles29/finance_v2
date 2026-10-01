-- ============================================================
-- Patch: Update icon sys_menu dari FA → RI
-- Jalankan jika sudah pernah run schema sebelumnya
-- ============================================================
SET NAMES utf8mb4;

UPDATE sys_menu SET icon = 'ri-home-smile-line'          WHERE menu_code = 'dashboard';
UPDATE sys_menu SET icon = 'ri-store-2-line'              WHERE menu_code = 'grp.pos';
UPDATE sys_menu SET icon = 'ri-archive-2-line'            WHERE menu_code = 'grp.inventory';
UPDATE sys_menu SET icon = 'ri-flask-line'                WHERE menu_code = 'grp.material';
UPDATE sys_menu SET icon = 'ri-tools-line'                WHERE menu_code = 'grp.production';
UPDATE sys_menu SET icon = 'ri-shopping-cart-2-line'      WHERE menu_code = 'grp.purchase';
UPDATE sys_menu SET icon = 'ri-group-line'                WHERE menu_code = 'grp.hr';
UPDATE sys_menu SET icon = 'ri-money-dollar-circle-line'  WHERE menu_code = 'grp.payroll';
UPDATE sys_menu SET icon = 'ri-bank-line'                 WHERE menu_code = 'grp.finance';
UPDATE sys_menu SET icon = 'ri-bar-chart-2-line'          WHERE menu_code = 'grp.reports';
UPDATE sys_menu SET icon = 'ri-database-2-line'           WHERE menu_code = 'grp.master';
UPDATE sys_menu SET icon = 'ri-settings-3-line'           WHERE menu_code = 'grp.system';
UPDATE sys_menu SET icon = 'ri-user-settings-line'        WHERE menu_code = 'sys.users';
UPDATE sys_menu SET icon = 'ri-shield-keyhole-line'       WHERE menu_code = 'sys.roles';
UPDATE sys_menu SET icon = 'ri-key-2-line'                WHERE menu_code = 'sys.access_control';
UPDATE sys_menu SET icon = 'ri-home-3-line'               WHERE menu_code = 'my.home';
UPDATE sys_menu SET icon = 'ri-time-line'                 WHERE menu_code = 'my.attendance';
UPDATE sys_menu SET icon = 'ri-hotel-bed-line'            WHERE menu_code = 'my.leave';
UPDATE sys_menu SET icon = 'ri-file-list-3-line'          WHERE menu_code = 'my.payroll';
UPDATE sys_menu SET icon = 'ri-restaurant-line'           WHERE menu_code = 'my.meal';
UPDATE sys_menu SET icon = 'ri-hand-coin-line'            WHERE menu_code = 'my.cash_advance';
UPDATE sys_menu SET icon = 'ri-user-3-line'               WHERE menu_code = 'my.profile';
