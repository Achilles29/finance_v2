SET NAMES utf8mb4;

-- Preserve the three old daily rows that predate salary snapshots.  The
-- figures are taken from the contract effective on each attendance date, not
-- from the employee master cache and without recalculating any paid payroll.
START TRANSACTION;

UPDATE att_daily ad
JOIN hr_contract c
  ON c.employee_id = ad.employee_id
 AND c.start_date <= ad.attendance_date
 AND c.end_date >= ad.attendance_date
 AND c.status IN ('ACTIVE', 'SIGNED', 'EXPIRED')
LEFT JOIN hr_contract newer
  ON newer.employee_id = c.employee_id
 AND newer.start_date <= ad.attendance_date
 AND newer.end_date >= ad.attendance_date
 AND newer.status IN ('ACTIVE', 'SIGNED', 'EXPIRED')
 AND (newer.start_date > c.start_date OR (newer.start_date = c.start_date AND newer.id > c.id))
LEFT JOIN hr_contract_comp_snapshot s ON s.contract_id = c.id
SET ad.snapshot_basic_salary = COALESCE(s.basic_salary_amount, c.basic_salary),
    ad.snapshot_position_allowance = COALESCE(s.position_allowance_amount, c.position_allowance),
    ad.snapshot_objective_allowance = COALESCE(s.other_allowance_amount, c.other_allowance),
    ad.snapshot_meal_rate = COALESCE(s.meal_rate_amount, c.meal_rate),
    ad.snapshot_overtime_rate = COALESCE(s.overtime_rate_amount, c.overtime_rate),
    ad.compensation_contract_id = c.id,
    ad.compensation_snapshot_id = s.id,
    ad.compensation_source = 'CONTRACT_HISTORICAL',
    ad.compensation_resolved_at = NOW()
WHERE ad.snapshot_basic_salary IS NULL
  AND newer.id IS NULL;

COMMIT;

SELECT
  ad.id,
  ad.attendance_date,
  e.employee_code,
  ad.compensation_source,
  c.contract_number,
  ad.snapshot_basic_salary,
  ad.snapshot_position_allowance,
  ad.snapshot_objective_allowance,
  ad.snapshot_meal_rate,
  ad.snapshot_overtime_rate
FROM att_daily ad
JOIN org_employee e ON e.id = ad.employee_id
LEFT JOIN hr_contract c ON c.id = ad.compensation_contract_id
WHERE ad.id IN (52, 97, 131)
ORDER BY ad.id;
