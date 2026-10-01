SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Approval log per level untuk pending request absensi
-- =========================================================
CREATE TABLE IF NOT EXISTS att_pending_request_approval (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  pending_request_id BIGINT UNSIGNED NOT NULL,
  approval_level TINYINT UNSIGNED NOT NULL,
  approver_employee_id BIGINT UNSIGNED NULL,
  action ENUM('APPROVED','REJECTED') NOT NULL,
  notes VARCHAR(255) NULL,
  acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_pending_req_approval_level (pending_request_id, approval_level),
  KEY idx_att_pending_req_approval_pending (pending_request_id),
  KEY idx_att_pending_req_approval_actor (approver_employee_id),
  CONSTRAINT fk_att_pending_req_approval_pending FOREIGN KEY (pending_request_id) REFERENCES att_pending_request(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_pending_req_approval_actor FOREIGN KEY (approver_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Register page
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.anomalies.index', 'Monitoring Anomali Absensi', 'ATTENDANCE', 'Monitoring data anomali absensi harian', 1),
  ('attendance.master_health.index', 'Kesehatan Data Master HR', 'ATTENDANCE', 'Audit relasi data master HR sebelum operasional payroll', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at, updated_at)
SELECT r.id, p.id,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END AS can_view,
       0 AS can_create,
       0 AS can_edit,
       0 AS can_delete,
       CASE WHEN r.role_code IN ('SUPERADMIN','CEO','ADM_HR','MGR') THEN 1 ELSE 0 END AS can_export,
       NOW(), NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('attendance.anomalies.index', 'attendance.master_health.index')
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
  SELECT 'hr.att-anomalies' AS menu_code, 'Anomali Absensi' AS menu_label, 'ri-alert-line' AS icon, '/attendance/anomalies' AS url, 'attendance.anomalies.index' AS page_code, 11 AS sort_order, 'grp.hr' AS parent_code
  UNION ALL
  SELECT 'hr.att-master-health', 'Kesehatan Data HR', 'ri-heart-pulse-line', '/attendance/master-health', 'attendance.master_health.index', 12, 'grp.hr'
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
