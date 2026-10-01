SET NAMES utf8mb4;

START TRANSACTION;

-- =========================================================
-- Policy refinement: window checkout, PH mode, submitter scope 3 opsi,
-- approval up to 3 level, dan kontrol potongan.
-- =========================================================
ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS checkout_close_minutes_after INT UNSIGNED NOT NULL DEFAULT 180 AFTER checkin_open_minutes_before,
  ADD COLUMN IF NOT EXISTS enable_late_deduction TINYINT(1) NOT NULL DEFAULT 1 AFTER late_deduction_per_minute,
  ADD COLUMN IF NOT EXISTS enable_alpha_deduction TINYINT(1) NOT NULL DEFAULT 1 AFTER alpha_deduction_per_day,
  ADD COLUMN IF NOT EXISTS prorate_deduction_scope ENUM('BASIC_ONLY','THP_TOTAL') NOT NULL DEFAULT 'BASIC_ONLY' AFTER payroll_late_deduction_scope,
  ADD COLUMN IF NOT EXISTS pending_request_scope ENUM('SELF_ONLY','POSITION_ONLY','SELF_AND_POSITION') NOT NULL DEFAULT 'SELF_ONLY' AFTER require_photo,
  ADD COLUMN IF NOT EXISTS pending_approval_levels TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER pending_request_scope,
  ADD COLUMN IF NOT EXISTS ph_attendance_mode ENUM('AUTO_PRESENT','MANUAL_CLOCK') NOT NULL DEFAULT 'AUTO_PRESENT' AFTER night_shift_checkout_credit_to_operation_end;

UPDATE att_attendance_policy
SET ph_attendance_mode = CASE
    WHEN COALESCE(ph_requires_clock_in_out, 0) = 1 THEN 'MANUAL_CLOCK'
    ELSE 'AUTO_PRESENT'
  END
WHERE ph_attendance_mode IS NULL OR ph_attendance_mode = '';

UPDATE att_attendance_policy
SET checkout_close_minutes_after = COALESCE(checkout_close_minutes_after, 180),
    enable_late_deduction = COALESCE(enable_late_deduction, 1),
    enable_alpha_deduction = COALESCE(enable_alpha_deduction, 1),
    pending_approval_levels = CASE
      WHEN pending_approval_levels < 1 THEN 1
      WHEN pending_approval_levels > 3 THEN 3
      ELSE pending_approval_levels
    END;

-- =========================================================
-- Mapping jabatan pengusul dan verifier pending per level
-- =========================================================
CREATE TABLE IF NOT EXISTS att_pending_submitter_position (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  policy_id BIGINT UNSIGNED NOT NULL,
  position_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_pending_submitter_policy_pos (policy_id, position_id),
  KEY idx_att_pending_submitter_pos (position_id),
  CONSTRAINT fk_att_pending_submitter_policy FOREIGN KEY (policy_id) REFERENCES att_attendance_policy(id),
  CONSTRAINT fk_att_pending_submitter_position FOREIGN KEY (position_id) REFERENCES org_position(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS att_pending_verifier_position (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  policy_id BIGINT UNSIGNED NOT NULL,
  verify_level TINYINT UNSIGNED NOT NULL,
  position_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_att_pending_verifier_policy_level_pos (policy_id, verify_level, position_id),
  KEY idx_att_pending_verifier_pos (position_id),
  CONSTRAINT fk_att_pending_verifier_policy FOREIGN KEY (policy_id) REFERENCES att_attendance_policy(id),
  CONSTRAINT fk_att_pending_verifier_position FOREIGN KEY (position_id) REFERENCES org_position(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Halaman monitoring pending request absensi
-- =========================================================
INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('attendance.pending.index', 'Pending Request Absensi', 'ATTENDANCE', 'Monitoring pengajuan & approval absensi', 1)
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
JOIN sys_page p ON p.page_code = 'attendance.pending.index'
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
  'hr.att-pending',
  'Pengajuan Absensi',
  'ri-file-edit-line',
  '/attendance/pending-requests',
  p.id,
  10,
  1,
  'MAIN',
  parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'attendance.pending.index'
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
