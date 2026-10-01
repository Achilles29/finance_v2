SET NAMES utf8mb4;

START TRANSACTION;

SET @ph_exp := (
  SELECT COALESCE(ph_expiry_months, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
);

INSERT INTO att_employee_ph_ledger (
  employee_id,
  tx_date,
  tx_type,
  qty_days,
  expired_at,
  ref_table,
  ref_id,
  entry_mode,
  notes,
  created_by,
  created_at,
  updated_at
)
SELECT
  ad.employee_id,
  ad.attendance_date,
  'GRANT',
  1.00,
  CASE
    WHEN COALESCE(pe.expiry_months_override, @ph_exp, 0) > 0
      THEN DATE_ADD(ad.attendance_date, INTERVAL COALESCE(pe.expiry_months_override, @ph_exp, 0) MONTH)
    ELSE NULL
  END AS expired_at,
  'att_daily',
  ad.id,
  'AUTO',
  'Backfill auto grant PH dari attendance holiday',
  NULL,
  NOW(),
  NOW()
FROM att_daily ad
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
LEFT JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
WHERE ad.attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
  AND hc.holiday_date IS NOT NULL
  AND ad.checkin_at IS NOT NULL
  AND ad.checkout_at IS NOT NULL
  AND ad.attendance_date >= pe.effective_date
  AND NOT EXISTS (
    SELECT 1
    FROM att_employee_ph_ledger l
    WHERE l.tx_type = 'GRANT'
      AND l.ref_table = 'att_daily'
      AND l.ref_id = ad.id
  );

COMMIT;
