SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- PH policy extension (tetap 1 policy di att_attendance_policy)
-- =========================================================
ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS ph_grant_mode ENUM('SHIFT_ONLY','HOLIDAY_ONLY','SHIFT_OR_HOLIDAY') NOT NULL DEFAULT 'HOLIDAY_ONLY' AFTER ph_attendance_mode,
  ADD COLUMN IF NOT EXISTS ph_grant_holiday_type ENUM('ANY','NATIONAL','COMPANY','SPECIAL') NOT NULL DEFAULT 'ANY' AFTER ph_grant_mode,
  ADD COLUMN IF NOT EXISTS ph_grant_requires_checkout TINYINT(1) NOT NULL DEFAULT 1 AFTER ph_grant_holiday_type,
  ADD COLUMN IF NOT EXISTS ph_grant_qty_per_day DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER ph_grant_requires_checkout;

UPDATE att_attendance_policy
SET ph_grant_mode = COALESCE(NULLIF(ph_grant_mode, ''), 'HOLIDAY_ONLY'),
    ph_grant_holiday_type = COALESCE(NULLIF(ph_grant_holiday_type, ''), 'ANY'),
    ph_grant_requires_checkout = COALESCE(ph_grant_requires_checkout, 1),
    ph_grant_qty_per_day = CASE
      WHEN ph_grant_qty_per_day IS NULL OR ph_grant_qty_per_day <= 0 THEN 1.00
      ELSE ph_grant_qty_per_day
    END;

-- =========================================================
-- Assignment pegawai yang berhak PH
-- =========================================================
CREATE TABLE IF NOT EXISTS att_ph_eligibility (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  is_eligible TINYINT(1) NOT NULL DEFAULT 1,
  effective_date DATE NOT NULL,
  expiry_months_override INT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_ph_eligibility_employee (employee_id),
  KEY idx_att_ph_eligibility_active (is_eligible, effective_date),
  CONSTRAINT fk_att_ph_eligibility_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_ph_eligibility_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO att_ph_eligibility (employee_id, is_eligible, effective_date, notes, created_at)
SELECT e.id, 1, CURDATE(), 'Auto seed dari pegawai aktif', NOW()
FROM org_employee e
LEFT JOIN att_ph_eligibility pe ON pe.employee_id = e.id
WHERE e.is_active = 1
  AND pe.id IS NULL;

-- =========================================================
-- Ledger PH (grant/use/expire/adjust)
-- =========================================================
CREATE TABLE IF NOT EXISTS att_employee_ph_ledger (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  tx_type ENUM('GRANT','USE','EXPIRE','ADJUST') NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  expired_at DATE NULL,
  ref_table VARCHAR(40) NOT NULL DEFAULT '',
  ref_id BIGINT UNSIGNED NULL,
  entry_mode ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_att_employee_ph_ledger_emp_date (employee_id, tx_date),
  KEY idx_att_employee_ph_ledger_type_date (tx_type, tx_date),
  KEY idx_att_employee_ph_ledger_ref (ref_table, ref_id),
  CONSTRAINT fk_att_employee_ph_ledger_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_att_employee_ph_ledger_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Pages + permissions + menu
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.ph.assignment.index', 'Assignment PH Pegawai', 'ATTENDANCE', 'Pengaturan pegawai yang berhak PH', 1),
  ('attendance.ph.ledger.index', 'Ledger & Log PH', 'ATTENDANCE', 'Mutasi PH grant/use/expire/adjust', 1),
  ('attendance.ph.recap.index', 'Rekap PH Pegawai', 'ATTENDANCE', 'Rekap saldo PH per pegawai', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       1,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR') THEN 1 ELSE 0 END,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN (
  'attendance.ph.assignment.index',
  'attendance.ph.ledger.index',
  'attendance.ph.recap.index'
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
  SELECT 'hr.att-ph-assignment' AS menu_code, 'Assignment PH' AS menu_label, 'ri-user-star-line' AS icon, '/attendance/ph-assignments' AS url, 'attendance.ph.assignment.index' AS page_code, 21 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL SELECT 'hr.att-ph-ledger', 'Ledger PH', 'ri-file-list-2-line', '/attendance/ph-ledger', 'attendance.ph.ledger.index', 22, 'grp.hr'
  UNION ALL SELECT 'hr.att-ph-recap', 'Rekap PH', 'ri-bar-chart-box-line', '/attendance/ph-recap', 'attendance.ph.recap.index', 23, 'grp.hr'
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
