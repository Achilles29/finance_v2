CREATE TABLE IF NOT EXISTS org_employee_role_assignment (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id  BIGINT UNSIGNED NOT NULL,
  role_id      BIGINT UNSIGNED NOT NULL,
  assigned_by  BIGINT UNSIGNED NULL,
  assigned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_org_employee_role_assignment (employee_id, role_id),
  KEY idx_org_employee_role_employee (employee_id),
  KEY idx_org_employee_role_role (role_id),
  CONSTRAINT fk_org_employee_role_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id),
  CONSTRAINT fk_org_employee_role_role FOREIGN KEY (role_id) REFERENCES auth_role(id),
  CONSTRAINT fk_org_employee_role_assigner FOREIGN KEY (assigned_by) REFERENCES auth_user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Role akses default per pegawai';