SET NAMES utf8mb4;

-- Overtime is governed by the master standard, not by each employee's
-- compensation contract. AUTO mode needs one explicit master standard.
START TRANSACTION;

ALTER TABLE att_attendance_policy
  ADD COLUMN IF NOT EXISTS default_overtime_standard_id BIGINT UNSIGNED NULL
  AFTER overtime_calc_mode;

CREATE INDEX IF NOT EXISTS idx_att_policy_default_overtime_standard
  ON att_attendance_policy (default_overtime_standard_id);

-- Keep the normal operational standard as the initial default. EVENT and
-- KITCHEN remain selectable per overtime entry and are never guessed here.
SET @default_overtime_standard_id := (
  SELECT id
  FROM att_overtime_standard
  WHERE is_active = 1
  ORDER BY CASE WHEN standard_code = 'OT-8000' THEN 0 ELSE 1 END,
           hourly_rate ASC,
           id ASC
  LIMIT 1
);

UPDATE att_attendance_policy
SET default_overtime_standard_id = @default_overtime_standard_id,
    updated_at = NOW()
WHERE default_overtime_standard_id IS NULL
  AND @default_overtime_standard_id IS NOT NULL;

COMMIT;

SELECT
  p.id AS policy_id,
  p.policy_code,
  p.overtime_calc_mode,
  s.standard_code,
  s.standard_name,
  s.hourly_rate
FROM att_attendance_policy p
LEFT JOIN att_overtime_standard s ON s.id = p.default_overtime_standard_id
WHERE p.is_active = 1
ORDER BY p.id DESC;
