SET NAMES utf8mb4;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE pay_objective_override;
TRUNCATE TABLE pay_basic_salary_standard;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1) Import standar gaji pokok dari core (ID dipertahankan)
-- =========================================================
INSERT INTO pay_basic_salary_standard (
  id,
  standard_code,
  standard_name,
  division_id,
  position_id,
  employment_type,
  effective_start,
  effective_end,
  start_amount,
  annual_increment,
  year_cap,
  notes,
  is_active,
  created_at,
  updated_at
)
SELECT
  s.id,
  s.standard_code,
  s.standard_name,
  s.division_id,
  s.position_id,
  s.employment_type,
  s.effective_start,
  s.effective_end,
  COALESCE(s.start_amount, 0),
  COALESCE(s.annual_increment, 0),
  s.year_cap,
  s.notes,
  COALESCE(s.is_active, 1),
  COALESCE(s.created_at, NOW()),
  s.updated_at
FROM core.pay_basic_salary_standard s
LEFT JOIN org_division d ON d.id = s.division_id
LEFT JOIN org_position p ON p.id = s.position_id
WHERE (s.division_id IS NULL OR d.id IS NOT NULL)
  AND (s.position_id IS NULL OR p.id IS NOT NULL)
ORDER BY s.id;

-- =========================================================
-- 2) Import objective override pegawai dari core
--    Hanya komponen OBJECTIVE_ALLOWANCE
-- =========================================================
INSERT INTO pay_objective_override (
  id,
  employee_id,
  override_amount,
  effective_start,
  effective_end,
  reason,
  is_active,
  created_by,
  created_at,
  updated_at
)
SELECT
  o.id,
  o.employee_id,
  COALESCE(o.override_amount, 0),
  o.effective_start,
  o.effective_end,
  o.reason,
  1 AS is_active,
  COALESCE(au_emp.id, au_user.id) AS created_by,
  COALESCE(o.created_at, NOW()),
  o.updated_at
FROM core.pay_employee_comp_override o
JOIN core.pay_comp_component c
  ON c.id = o.component_id
 AND c.component_code = 'OBJECTIVE_ALLOWANCE'
JOIN org_employee e
  ON e.id = o.employee_id
LEFT JOIN auth_user au_emp
  ON au_emp.employee_id = o.approved_by
LEFT JOIN core.org_employee ce_appr
  ON ce_appr.id = o.approved_by
LEFT JOIN auth_user au_user
  ON au_user.username = ce_appr.username
ORDER BY o.id;

-- =========================================================
-- 3) Reset AUTO_INCREMENT
-- =========================================================
SET @ai_basic := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_basic_salary_standard);
SET @sql_basic := CONCAT('ALTER TABLE pay_basic_salary_standard AUTO_INCREMENT = ', @ai_basic);
PREPARE st_basic FROM @sql_basic;
EXECUTE st_basic;
DEALLOCATE PREPARE st_basic;

SET @ai_obj := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_objective_override);
SET @sql_obj := CONCAT('ALTER TABLE pay_objective_override AUTO_INCREMENT = ', @ai_obj);
PREPARE st_obj FROM @sql_obj;
EXECUTE st_obj;
DEALLOCATE PREPARE st_obj;

COMMIT;
