SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Payroll Manual Adjustment (tambahan/pengurangan manual)
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_manual_adjustment (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  adjustment_date DATE NOT NULL,
  adjustment_kind ENUM('ADDITION','DEDUCTION') NOT NULL DEFAULT 'ADDITION',
  adjustment_name VARCHAR(120) NOT NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'APPROVED',
  notes VARCHAR(255) NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_manual_adj_employee_date (employee_id, adjustment_date),
  KEY idx_pay_manual_adj_kind_status (adjustment_kind, status),
  KEY idx_pay_manual_adj_date (adjustment_date),
  CONSTRAINT fk_pay_manual_adj_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_pay_manual_adj_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_manual_adj_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Register pages
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.overtime_entry.index', 'Input Lembur Manual', 'ATTENDANCE', 'CRUD input lembur manual berbasis tanggal dan jam lembur', 1),
  ('payroll.manual_adjustment.index', 'Penyesuaian Gaji Manual', 'PAYROLL', 'CRUD tambahan dan pengurangan gaji manual', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('attendance.overtime_entry.index', 'payroll.manual_adjustment.index')
WHERE r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  src.menu_code,
  src.menu_label,
  src.icon,
  src.url,
  p.id,
  src.sort_order,
  1,
  'MAIN',
  parent.id
FROM (
  SELECT 'hr.att-overtime' AS menu_code, 'Input Lembur' AS menu_label, 'ri-time-line' AS icon, '/attendance/overtime-entries' AS url, 'attendance.overtime_entry.index' AS page_code, 14 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL
  SELECT 'pay.manual-adjustment', 'Adj. Gaji Manual', 'ri-scales-3-line', '/payroll/manual-adjustments', 'payroll.manual_adjustment.index', 7, 'grp.payroll'
) src
JOIN sys_menu parent ON parent.menu_code = src.parent_code
JOIN sys_page p ON p.page_code = src.page_code
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
