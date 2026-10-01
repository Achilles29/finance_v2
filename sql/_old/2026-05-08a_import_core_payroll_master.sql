SET NAMES utf8mb4;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE pay_salary_profile_line;
TRUNCATE TABLE pay_salary_assignment;
TRUNCATE TABLE pay_salary_profile;
TRUNCATE TABLE pay_salary_component;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1) Komponen gaji (core.pay_comp_component -> finance.pay_salary_component)
-- =========================================================
INSERT INTO pay_salary_component (
  id,
  component_code,
  component_name,
  component_type,
  calc_method,
  default_amount,
  affects_attendance,
  affects_bpjs_base,
  is_taxable,
  sort_order,
  is_active,
  notes,
  created_at,
  updated_at
)
SELECT
  c.id,
  LEFT(c.component_code, 50) AS component_code,
  c.component_name,
  c.component_type,
  CASE c.calc_scope
    WHEN 'MONTHLY_FIXED' THEN 'FIXED'
    WHEN 'DAILY' THEN 'PER_DAY'
    WHEN 'HOURLY' THEN 'PER_HOUR'
    ELSE 'FORMULA'
  END AS calc_method,
  0 AS default_amount,
  CASE WHEN c.default_basis = 'ATTENDANCE' THEN 1 ELSE 0 END AS affects_attendance,
  CASE WHEN c.default_basis IN ('BASIC_ONLY', 'GROSS') THEN 1 ELSE 0 END AS affects_bpjs_base,
  COALESCE(c.is_taxable, 0) AS is_taxable,
  COALESCE(c.sort_order, 0) AS sort_order,
  COALESCE(c.is_active, 1) AS is_active,
  LEFT(COALESCE(c.notes, ''), 255) AS notes,
  COALESCE(c.created_at, NOW()) AS created_at,
  c.updated_at
FROM core.pay_comp_component c
ORDER BY c.id;

-- =========================================================
-- 2) Profil gaji (core.pay_comp_profile -> finance.pay_salary_profile)
-- =========================================================
INSERT INTO pay_salary_profile (
  id,
  profile_code,
  profile_name,
  effective_start,
  effective_end,
  is_active,
  notes,
  created_at,
  updated_at
)
SELECT
  p.id,
  LEFT(p.profile_code, 50) AS profile_code,
  p.profile_name,
  NULL AS effective_start,
  NULL AS effective_end,
  COALESCE(p.is_active, 1) AS is_active,
  LEFT(COALESCE(p.notes, ''), 255) AS notes,
  COALESCE(p.created_at, NOW()) AS created_at,
  p.updated_at
FROM core.pay_comp_profile p
ORDER BY p.id;

-- =========================================================
-- 3) Komponen per profil
--    (core.pay_comp_profile_line -> finance.pay_salary_profile_line)
-- =========================================================
INSERT INTO pay_salary_profile_line (
  id,
  profile_id,
  component_id,
  amount,
  formula_expr,
  sort_order,
  is_active,
  created_at,
  updated_at
)
SELECT
  l.id,
  l.profile_id,
  l.component_id,
  COALESCE(l.amount, 0),
  LEFT(COALESCE(l.formula_json, ''), 255) AS formula_expr,
  COALESCE(l.sort_order, 0),
  1 AS is_active,
  COALESCE(l.created_at, NOW()) AS created_at,
  l.updated_at
FROM core.pay_comp_profile_line l
JOIN pay_salary_profile fp ON fp.id = l.profile_id
JOIN pay_salary_component fc ON fc.id = l.component_id
ORDER BY l.id;

-- isi default_amount komponen dari median kasar (avg) line profil jika ada
UPDATE pay_salary_component c
JOIN (
  SELECT component_id, AVG(amount) AS avg_amount
  FROM pay_salary_profile_line
  GROUP BY component_id
) x ON x.component_id = c.id
SET c.default_amount = ROUND(COALESCE(x.avg_amount, 0), 2)
WHERE COALESCE(c.default_amount, 0) = 0;

-- =========================================================
-- 4) Assignment profil pegawai
--    (core.pay_employee_comp_assignment -> finance.pay_salary_assignment)
-- =========================================================
INSERT INTO pay_salary_assignment (
  id,
  employee_id,
  profile_id,
  effective_start,
  effective_end,
  is_active,
  notes,
  created_at,
  updated_at
)
SELECT
  a.id,
  a.employee_id,
  a.profile_id,
  a.effective_start,
  a.effective_end,
  CASE WHEN a.status = 'ACTIVE' THEN 1 ELSE 0 END AS is_active,
  LEFT(COALESCE(a.notes, ''), 255) AS notes,
  COALESCE(a.created_at, NOW()) AS created_at,
  a.updated_at
FROM core.pay_employee_comp_assignment a
JOIN org_employee e ON e.id = a.employee_id
JOIN pay_salary_profile p ON p.id = a.profile_id
ORDER BY a.id;

-- =========================================================
-- 5) Reset AUTO_INCREMENT
-- =========================================================
SET @ai_component := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_salary_component);
SET @sql_component := CONCAT('ALTER TABLE pay_salary_component AUTO_INCREMENT = ', @ai_component);
PREPARE st_component FROM @sql_component; EXECUTE st_component; DEALLOCATE PREPARE st_component;

SET @ai_profile := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_salary_profile);
SET @sql_profile := CONCAT('ALTER TABLE pay_salary_profile AUTO_INCREMENT = ', @ai_profile);
PREPARE st_profile FROM @sql_profile; EXECUTE st_profile; DEALLOCATE PREPARE st_profile;

SET @ai_profile_line := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_salary_profile_line);
SET @sql_profile_line := CONCAT('ALTER TABLE pay_salary_profile_line AUTO_INCREMENT = ', @ai_profile_line);
PREPARE st_profile_line FROM @sql_profile_line; EXECUTE st_profile_line; DEALLOCATE PREPARE st_profile_line;

SET @ai_assignment := (SELECT COALESCE(MAX(id), 0) + 1 FROM pay_salary_assignment);
SET @sql_assignment := CONCAT('ALTER TABLE pay_salary_assignment AUTO_INCREMENT = ', @ai_assignment);
PREPARE st_assignment FROM @sql_assignment; EXECUTE st_assignment; DEALLOCATE PREPARE st_assignment;

COMMIT;
