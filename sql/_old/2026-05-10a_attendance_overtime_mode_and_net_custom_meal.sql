SET NAMES utf8mb4;

START TRANSACTION;

ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS overtime_calc_mode ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO' AFTER meal_calc_mode;

UPDATE att_attendance_policy
SET overtime_calc_mode = COALESCE(NULLIF(overtime_calc_mode, ''), 'AUTO');

COMMIT;
