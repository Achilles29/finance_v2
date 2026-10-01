SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Payroll standard: basic salary by rule (division/position/type/masa kerja)
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_basic_salary_standard (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  standard_code VARCHAR(60) NOT NULL,
  standard_name VARCHAR(120) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  employment_type VARCHAR(50) NULL,
  effective_start DATE NOT NULL,
  effective_end DATE NULL,
  start_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  annual_increment DECIMAL(18,2) NOT NULL DEFAULT 0,
  year_cap INT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_pay_basic_salary_standard_code (standard_code),
  KEY idx_pay_basic_salary_standard_scope (position_id, division_id, employment_type),
  KEY idx_pay_basic_salary_standard_effective (effective_start, effective_end),
  CONSTRAINT fk_pay_basic_salary_standard_division FOREIGN KEY (division_id) REFERENCES org_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_basic_salary_standard_position FOREIGN KEY (position_id) REFERENCES org_position(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Payroll standard: override tunjangan objektif khusus pegawai
-- =========================================================
CREATE TABLE IF NOT EXISTS pay_objective_override (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  override_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  effective_start DATE NOT NULL,
  effective_end DATE NULL,
  reason VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pay_objective_override_employee_effective (employee_id, effective_start, effective_end),
  KEY idx_pay_objective_override_active (is_active),
  CONSTRAINT fk_pay_objective_override_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_objective_override_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Register page for new payroll standards + preview
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('payroll.basic_salary.index', 'Standar Gaji Pokok', 'PAYROLL', 'CRUD pay_basic_salary_standard via master module', 1),
  ('payroll.objective_override.index', 'Override Tunjangan Objektif', 'PAYROLL', 'CRUD pay_objective_override via master module', 1),
  ('payroll.preview_thp.index', 'Preview THP', 'PAYROLL', 'Preview THP read-only dari assignment + kontrak aktif', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1 AS can_view,
       CASE
         WHEN p.page_code = 'payroll.preview_thp.index' THEN 0
         WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1
         ELSE 0
       END AS can_create,
       CASE
         WHEN p.page_code = 'payroll.preview_thp.index' THEN 0
         WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1
         ELSE 0
       END AS can_edit,
       CASE
         WHEN p.page_code = 'payroll.preview_thp.index' THEN 0
         WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1
         ELSE 0
       END AS can_delete,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END AS can_export,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'payroll.basic_salary.index',
  'payroll.objective_override.index',
  'payroll.preview_thp.index'
)
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
  SELECT 'pay.basic-salary' AS menu_code, 'Standar Gaji Pokok' AS menu_label, 'ri-money-dollar-box-line' AS icon, '/master/pay-basic-salary' AS url, 'payroll.basic_salary.index' AS page_code, 4 AS sort_order, 'grp.payroll' AS parent_code
  UNION ALL
  SELECT 'pay.objective-override', 'Override Tunjangan', 'ri-wallet-line', '/master/pay-objective-override', 'payroll.objective_override.index', 5, 'grp.payroll'
  UNION ALL
  SELECT 'pay.preview-thp', 'Preview THP', 'ri-line-chart-line', '/payroll/preview-thp', 'payroll.preview_thp.index', 6, 'grp.payroll'
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
