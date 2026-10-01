ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS attendance_revision_window_mode ENUM('OFF','ON','BY_DAYS') NOT NULL DEFAULT 'ON' AFTER pending_request_scope,
  ADD COLUMN IF NOT EXISTS attendance_revision_window_days INT(10) UNSIGNED NOT NULL DEFAULT 7 AFTER attendance_revision_window_mode;

UPDATE att_attendance_policy
SET attendance_revision_window_mode = CASE
        WHEN attendance_revision_window_mode IS NULL OR TRIM(attendance_revision_window_mode) = '' THEN 'ON'
        ELSE attendance_revision_window_mode
    END,
    attendance_revision_window_days = CASE
        WHEN COALESCE(attendance_revision_window_days, 0) <= 0 THEN 7
        ELSE attendance_revision_window_days
    END;
