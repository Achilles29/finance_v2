SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- HR Contract master structure (adopsi core + penyederhanaan finance)
-- =========================================================
CREATE TABLE IF NOT EXISTS hr_contract_template (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_code VARCHAR(30) NOT NULL,
  template_name VARCHAR(120) NOT NULL,
  contract_type ENUM('K1','K2','K3','CUSTOM') NOT NULL DEFAULT 'K1',
  duration_months SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  body_html LONGTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_hr_contract_template_code (template_code),
  KEY idx_hr_contract_template_active (is_active),
  CONSTRAINT fk_hr_contract_template_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_contract (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  contract_number VARCHAR(60) NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  previous_contract_id BIGINT UNSIGNED NULL,
  contract_type ENUM('K1','K2','K3','CUSTOM') NOT NULL DEFAULT 'K1',
  status ENUM('DRAFT','GENERATED','SIGNED','ACTIVE','EXPIRED','TERMINATED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  position_snapshot VARCHAR(120) NULL,
  division_snapshot VARCHAR(120) NULL,
  basic_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  position_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_allowance DECIMAL(18,2) NOT NULL DEFAULT 0,
  meal_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_rate DECIMAL(18,2) NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  verification_token VARCHAR(80) NULL,
  final_document_hash CHAR(64) NULL,
  document_issued_at DATETIME NULL,
  body_html LONGTEXT NULL,
  notes TEXT NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_hr_contract_number (contract_number),
  UNIQUE KEY uk_hr_contract_verification_token (verification_token),
  KEY idx_hr_contract_employee (employee_id),
  KEY idx_hr_contract_template (template_id),
  KEY idx_hr_contract_previous (previous_contract_id),
  KEY idx_hr_contract_status_dates (status, start_date, end_date),
  CONSTRAINT fk_hr_contract_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_hr_contract_template FOREIGN KEY (template_id) REFERENCES hr_contract_template(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_contract_previous FOREIGN KEY (previous_contract_id) REFERENCES hr_contract(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_contract_generated_by FOREIGN KEY (generated_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_contract_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_contract_approval (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  approver_role ENUM('EMPLOYEE','COMPANY') NOT NULL,
  approval_status ENUM('APPROVED','REVOKED') NOT NULL DEFAULT 'APPROVED',
  approver_name VARCHAR(150) NOT NULL,
  approver_user_id BIGINT UNSIGNED NULL,
  approval_note VARCHAR(255) NULL,
  approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_hr_contract_approval_contract_role (contract_id, approver_role),
  KEY idx_hr_contract_approval_status_time (approval_status, approved_at),
  CONSTRAINT fk_hr_contract_approval_contract FOREIGN KEY (contract_id) REFERENCES hr_contract(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_contract_approval_user FOREIGN KEY (approver_user_id) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_contract_signature (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  signer_role ENUM('EMPLOYEE','COMPANY') NOT NULL,
  signer_name VARCHAR(150) NOT NULL,
  signer_user_id BIGINT UNSIGNED NULL,
  signature_data LONGTEXT NOT NULL,
  signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  KEY idx_hr_contract_signature_contract (contract_id),
  CONSTRAINT fk_hr_contract_signature_contract FOREIGN KEY (contract_id) REFERENCES hr_contract(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_contract_signature_user FOREIGN KEY (signer_user_id) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_contract_comp_snapshot (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  contract_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  effective_start DATE NOT NULL,
  effective_end DATE NULL,
  basic_salary_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  position_allowance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_allowance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  meal_rate_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime_rate_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  fixed_total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  source_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_hr_contract_comp_snapshot_contract (contract_id),
  KEY idx_hr_contract_comp_snapshot_employee (employee_id),
  KEY idx_hr_contract_comp_snapshot_effective (effective_start, effective_end),
  CONSTRAINT fk_hr_contract_comp_snapshot_contract FOREIGN KEY (contract_id) REFERENCES hr_contract(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_contract_comp_snapshot_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_contract_comp_snapshot_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  component_code_snapshot VARCHAR(60) NOT NULL,
  component_name_snapshot VARCHAR(120) NOT NULL,
  component_type ENUM('EARNING','DEDUCTION') NOT NULL DEFAULT 'EARNING',
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hr_contract_snapshot_line_snapshot (snapshot_id, sort_order),
  CONSTRAINT fk_hr_contract_snapshot_line_snapshot FOREIGN KEY (snapshot_id) REFERENCES hr_contract_comp_snapshot(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Register pages
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('hr.contract_template.index', 'Master Template Kontrak', 'HR', 'CRUD hr_contract_template via master module', 1),
  ('hr.contract.index', 'Master Kontrak Pegawai', 'HR', 'CRUD hr_contract via master module', 1)
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
JOIN sys_page p ON p.page_code IN ('hr.contract_template.index', 'hr.contract.index')
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
  SELECT 'hr.contract-template' AS menu_code, 'Template Kontrak' AS menu_label, 'ri-file-text-line' AS icon, '/master/hr-contract-template' AS url, 'hr.contract_template.index' AS page_code, 11 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL SELECT 'hr.contract', 'Kontrak Pegawai', 'ri-article-line', '/master/hr-contract', 'hr.contract.index', 12, 'grp.hr'
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
