SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO sys_menu (
  menu_code,
  menu_label,
  icon,
  url,
  page_id,
  sort_order,
  is_active,
  sidebar_type,
  parent_id
)
SELECT
  'hr.att-meal-calendar',
  'Estimasi Uang Makan',
  'ri-restaurant-2-line',
  '/attendance/meal-calendar',
  p.id,
  6,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'attendance.estimate.index'
WHERE parent.menu_code = 'attend.report'
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
