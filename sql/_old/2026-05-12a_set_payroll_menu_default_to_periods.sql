SET NAMES utf8mb4;

START TRANSACTION;

UPDATE sys_menu
SET url = '/payroll/payroll-periods',
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'pay.salary-disbursement';

COMMIT;
