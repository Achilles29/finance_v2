SET NAMES utf8mb4;

-- Contract-first compensation foundation. Run once before deploying the
-- resolver. Existing att_daily salary values remain immutable snapshots.
ALTER TABLE att_daily
  ADD COLUMN IF NOT EXISTS compensation_contract_id BIGINT UNSIGNED NULL AFTER snapshot_overtime_rate,
  ADD COLUMN IF NOT EXISTS compensation_snapshot_id BIGINT UNSIGNED NULL AFTER compensation_contract_id,
  ADD COLUMN IF NOT EXISTS compensation_source VARCHAR(30) NULL AFTER compensation_snapshot_id,
  ADD COLUMN IF NOT EXISTS compensation_resolved_at DATETIME NULL AFTER compensation_source;

CREATE INDEX IF NOT EXISTS idx_att_daily_compensation_contract
  ON att_daily (compensation_contract_id, attendance_date);

SET @effective_date := '2026-09-01';

START TRANSACTION;

-- A contract that has passed its end date is historical, not ACTIVE.
UPDATE hr_contract
SET status = 'EXPIRED',
    notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, 'Lifecycle normalized ', @effective_date)),
    updated_at = NOW()
WHERE status IN ('ACTIVE', 'SIGNED')
  AND end_date < @effective_date;

-- Signed contracts with complete approval/signature are operationally active
-- once their date range starts. This never fabricates a signature.
UPDATE hr_contract c
SET c.status = 'ACTIVE',
    c.updated_at = NOW()
WHERE c.status = 'SIGNED'
  AND c.start_date <= @effective_date
  AND c.end_date >= @effective_date
  AND (SELECT COUNT(DISTINCT a.approver_role)
       FROM hr_contract_approval a
       WHERE a.contract_id = c.id
         AND a.approval_status = 'APPROVED'
         AND a.approver_role IN ('EMPLOYEE', 'COMPANY')) = 2
  AND (SELECT COUNT(DISTINCT s.signer_role)
       FROM hr_contract_signature s
       WHERE s.contract_id = c.id
         AND s.signer_role IN ('EMPLOYEE', 'COMPANY')) = 2;

-- Zella's old K1 must end one day before the K2 becomes effective. The newer
-- K2 already carries the correct Rp300.000 position allowance.
UPDATE hr_contract old_contract
JOIN hr_contract new_contract
  ON new_contract.employee_id = old_contract.employee_id
 AND new_contract.status = 'ACTIVE'
 AND new_contract.start_date > old_contract.start_date
JOIN org_employee e ON e.id = old_contract.employee_id
SET old_contract.end_date = DATE_SUB(new_contract.start_date, INTERVAL 1 DAY),
    old_contract.status = 'EXPIRED',
    old_contract.notes = TRIM(CONCAT(COALESCE(old_contract.notes, ''), CASE WHEN COALESCE(old_contract.notes, '') = '' THEN '' ELSE ' | ' END, 'Closed before successor contract')), 
    old_contract.updated_at = NOW()
WHERE e.employee_code = 'EMP-2026050002'
  AND old_contract.end_date >= new_contract.start_date;

-- Carolus, Eko, and Fairuz use the values verified in Master Pegawai. Do not
-- alter their signed historical document; create a linked amendment from today.
INSERT INTO hr_contract (
  contract_number, employee_id, template_id, previous_contract_id, contract_type, status,
  position_snapshot, division_snapshot, basic_salary, position_allowance, other_allowance,
  meal_rate, overtime_rate, start_date, end_date, notes, created_at, updated_at
)
SELECT
  CONCAT('MIG/AMD/202609/', e.employee_code),
  e.id,
  NULL,
  c.id,
  'CUSTOM',
  'ACTIVE',
  COALESCE(p.position_name, c.position_snapshot),
  COALESCE(d.division_name, c.division_snapshot),
  e.basic_salary,
  e.position_allowance,
  e.objective_allowance,
  e.meal_rate,
  e.overtime_rate,
  @effective_date,
  c.end_date,
  'MIGRASI KOMPENSASI: nilai Master Pegawai yang telah diverifikasi menjadi sumber operasional mulai 2026-09-01. Dokumen sebelumnya tetap historis.',
  NOW(),
  NOW()
FROM org_employee e
JOIN hr_contract c
  ON c.employee_id = e.id
 AND c.status = 'ACTIVE'
 AND c.start_date < @effective_date
 AND c.end_date >= @effective_date
LEFT JOIN org_position p ON p.id = e.position_id
LEFT JOIN org_division d ON d.id = e.division_id
WHERE e.employee_code IN ('EMP-00004', 'EMP-00006', 'EMP-00009')
ON DUPLICATE KEY UPDATE
  previous_contract_id = VALUES(previous_contract_id),
  position_snapshot = VALUES(position_snapshot),
  division_snapshot = VALUES(division_snapshot),
  basic_salary = VALUES(basic_salary),
  position_allowance = VALUES(position_allowance),
  other_allowance = VALUES(other_allowance),
  meal_rate = VALUES(meal_rate),
  overtime_rate = VALUES(overtime_rate),
  start_date = VALUES(start_date),
  end_date = VALUES(end_date),
  status = 'ACTIVE',
  notes = VALUES(notes),
  updated_at = NOW();

-- Close predecessors only after their successor amendment exists.
UPDATE hr_contract previous_contract
JOIN hr_contract amendment ON amendment.previous_contract_id = previous_contract.id
SET previous_contract.end_date = DATE_SUB(amendment.start_date, INTERVAL 1 DAY),
    previous_contract.status = 'EXPIRED',
    previous_contract.updated_at = NOW()
WHERE amendment.contract_number LIKE 'MIG/AMD/202609/%'
  AND previous_contract.end_date >= amendment.start_date;

-- Every active employee must have one effective compensation source. These
-- baseline records are administrative migration records, not forged signed
-- documents; formal renewal still follows the real approval/signature flow.
INSERT INTO hr_contract (
  contract_number, employee_id, template_id, previous_contract_id, contract_type, status,
  position_snapshot, division_snapshot, basic_salary, position_allowance, other_allowance,
  meal_rate, overtime_rate, start_date, end_date, notes, created_at, updated_at
)
SELECT
  CONCAT('MIG/BASE/202609/', e.employee_code),
  e.id,
  NULL,
  NULL,
  'CUSTOM',
  'ACTIVE',
  p.position_name,
  d.division_name,
  e.basic_salary,
  e.position_allowance,
  e.objective_allowance,
  e.meal_rate,
  e.overtime_rate,
  @effective_date,
  DATE_SUB(DATE_ADD(@effective_date, INTERVAL 12 MONTH), INTERVAL 1 DAY),
  'MIGRASI OPERASIONAL: baseline sumber kompensasi dari Master Pegawai. Wajib dibuat dokumen formal dan tanda tangan saat pembaruan kontrak berikutnya.',
  NOW(),
  NOW()
FROM org_employee e
LEFT JOIN org_position p ON p.id = e.position_id
LEFT JOIN org_division d ON d.id = e.division_id
LEFT JOIN hr_contract active_contract
  ON active_contract.employee_id = e.id
 AND active_contract.status = 'ACTIVE'
 AND active_contract.start_date <= @effective_date
 AND active_contract.end_date >= @effective_date
WHERE e.is_active = 1
  AND active_contract.id IS NULL
ON DUPLICATE KEY UPDATE
  position_snapshot = VALUES(position_snapshot),
  division_snapshot = VALUES(division_snapshot),
  basic_salary = VALUES(basic_salary),
  position_allowance = VALUES(position_allowance),
  other_allowance = VALUES(other_allowance),
  meal_rate = VALUES(meal_rate),
  overtime_rate = VALUES(overtime_rate),
  status = 'ACTIVE',
  updated_at = NOW();

-- All migrated records receive immutable compensation snapshots and lines.
INSERT INTO hr_contract_comp_snapshot (
  contract_id, employee_id, effective_start, effective_end,
  basic_salary_amount, position_allowance_amount, other_allowance_amount,
  meal_rate_amount, overtime_rate_amount, fixed_total_amount,
  source_notes, created_at, updated_at
)
SELECT
  c.id, c.employee_id, c.start_date, c.end_date,
  c.basic_salary, c.position_allowance, c.other_allowance,
  c.meal_rate, c.overtime_rate,
  c.basic_salary + c.position_allowance + c.other_allowance,
  'Snapshot migrasi contract-first 2026-09-01', NOW(), NOW()
FROM hr_contract c
WHERE c.contract_number LIKE 'MIG/AMD/202609/%'
   OR c.contract_number LIKE 'MIG/BASE/202609/%'
ON DUPLICATE KEY UPDATE
  employee_id = VALUES(employee_id),
  effective_start = VALUES(effective_start),
  effective_end = VALUES(effective_end),
  basic_salary_amount = VALUES(basic_salary_amount),
  position_allowance_amount = VALUES(position_allowance_amount),
  other_allowance_amount = VALUES(other_allowance_amount),
  meal_rate_amount = VALUES(meal_rate_amount),
  overtime_rate_amount = VALUES(overtime_rate_amount),
  fixed_total_amount = VALUES(fixed_total_amount),
  source_notes = VALUES(source_notes),
  updated_at = NOW();

DELETE line_item
FROM hr_contract_comp_snapshot_line line_item
JOIN hr_contract_comp_snapshot snapshot ON snapshot.id = line_item.snapshot_id
JOIN hr_contract c ON c.id = snapshot.contract_id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%'
   OR c.contract_number LIKE 'MIG/BASE/202609/%';

INSERT INTO hr_contract_comp_snapshot_line (
  snapshot_id, component_code_snapshot, component_name_snapshot, component_type, amount, sort_order, created_at
)
SELECT snapshot.id, 'GAJI_POKOK', 'Gaji Pokok', 'EARNING', c.basic_salary, 1, NOW()
FROM hr_contract c JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%' OR c.contract_number LIKE 'MIG/BASE/202609/%'
UNION ALL
SELECT snapshot.id, 'TUNJANGAN_JABATAN', 'Tunjangan Jabatan', 'EARNING', c.position_allowance, 2, NOW()
FROM hr_contract c JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%' OR c.contract_number LIKE 'MIG/BASE/202609/%'
UNION ALL
SELECT snapshot.id, 'TUNJANGAN_OBJEKTIF', 'Tunjangan Objektif', 'EARNING', c.other_allowance, 3, NOW()
FROM hr_contract c JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%' OR c.contract_number LIKE 'MIG/BASE/202609/%'
UNION ALL
SELECT snapshot.id, 'UANG_MAKAN', 'Uang Makan per Hari', 'EARNING', c.meal_rate, 4, NOW()
FROM hr_contract c JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%' OR c.contract_number LIKE 'MIG/BASE/202609/%'
UNION ALL
SELECT snapshot.id, 'LEMBUR_PER_JAM', 'Lembur per Jam', 'EARNING', c.overtime_rate, 5, NOW()
FROM hr_contract c JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
WHERE c.contract_number LIKE 'MIG/AMD/202609/%' OR c.contract_number LIKE 'MIG/BASE/202609/%';

-- Synchronize the compatibility cache in Master Pegawai from the one active
-- contract per employee. This is a write-through cache, never an input source.
UPDATE org_employee e
JOIN hr_contract c
  ON c.employee_id = e.id
 AND c.status = 'ACTIVE'
 AND c.start_date <= @effective_date
 AND c.end_date >= @effective_date
LEFT JOIN hr_contract newer_contract
  ON newer_contract.employee_id = c.employee_id
 AND newer_contract.status = 'ACTIVE'
 AND newer_contract.start_date <= @effective_date
 AND newer_contract.end_date >= @effective_date
 AND (newer_contract.start_date > c.start_date OR (newer_contract.start_date = c.start_date AND newer_contract.id > c.id))
LEFT JOIN hr_contract_comp_snapshot snapshot ON snapshot.contract_id = c.id
SET e.basic_salary = COALESCE(snapshot.basic_salary_amount, c.basic_salary),
    e.position_allowance = COALESCE(snapshot.position_allowance_amount, c.position_allowance),
    e.objective_allowance = COALESCE(snapshot.other_allowance_amount, c.other_allowance),
    e.meal_rate = COALESCE(snapshot.meal_rate_amount, c.meal_rate),
    e.overtime_rate = COALESCE(snapshot.overtime_rate_amount, c.overtime_rate),
    e.updated_at = NOW()
WHERE newer_contract.id IS NULL;

-- Do not invent historical contract links: old daily salary snapshots remain
-- truthful as legacy snapshots until a deliberate, unlocked contract rebuild.
UPDATE att_daily
SET compensation_source = 'LEGACY_SNAPSHOT',
    compensation_resolved_at = NOW()
WHERE snapshot_basic_salary IS NOT NULL
  AND (compensation_source IS NULL OR compensation_source = '');

COMMIT;

-- Verification queries for the execution log.
SELECT e.employee_code, e.employee_name, c.contract_number, c.status, c.start_date, c.end_date,
       COALESCE(s.fixed_total_amount, c.basic_salary + c.position_allowance + c.other_allowance) AS fixed_total,
       COALESCE(s.meal_rate_amount, c.meal_rate) AS meal_rate
FROM org_employee e
LEFT JOIN hr_contract c
  ON c.employee_id = e.id
 AND c.status = 'ACTIVE'
 AND c.start_date <= @effective_date
 AND c.end_date >= @effective_date
LEFT JOIN hr_contract_comp_snapshot s ON s.contract_id = c.id
WHERE e.is_active = 1
ORDER BY e.employee_name;

SELECT c.contract_number, e.employee_name, c.status, c.start_date, c.end_date
FROM hr_contract c
JOIN org_employee e ON e.id = c.employee_id
WHERE c.status = 'ACTIVE'
  AND c.start_date <= @effective_date
  AND c.end_date >= @effective_date
  AND ((SELECT COUNT(DISTINCT a.approver_role) FROM hr_contract_approval a WHERE a.contract_id = c.id AND a.approval_status = 'APPROVED') < 2
       OR (SELECT COUNT(DISTINCT s.signer_role) FROM hr_contract_signature s WHERE s.contract_id = c.id) < 2)
ORDER BY e.employee_name;
