SET NAMES utf8mb4;

START TRANSACTION;

UPDATE sys_menu
SET menu_label = 'Kontrak Pegawai',
    url = '/hr/contracts',
    updated_at = CURRENT_TIMESTAMP
WHERE menu_code = 'hr.contract';

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'hr.contract-master',
  'Kontrak Pegawai (Master)',
  'ri-database-2-line',
  '/master/hr-contract',
  p.id,
  13,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'hr.contract.index'
WHERE parent.menu_code = 'grp.hr'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;
