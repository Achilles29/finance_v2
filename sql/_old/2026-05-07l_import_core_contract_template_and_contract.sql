SET NAMES utf8mb4;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE hr_contract_approval;
TRUNCATE TABLE hr_contract_signature;
TRUNCATE TABLE hr_contract_comp_snapshot_line;
TRUNCATE TABLE hr_contract_comp_snapshot;
TRUNCATE TABLE hr_contract;
TRUNCATE TABLE hr_contract_template;
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1) Import template kontrak dari core (ID dipertahankan)
-- =========================================================
INSERT INTO hr_contract_template (
  id,
  template_code,
  template_name,
  contract_type,
  duration_months,
  body_html,
  is_active,
  created_by,
  created_at,
  updated_at
)
SELECT
  ct.id,
  ct.template_code,
  ct.template_name,
  CASE ct.contract_type
    WHEN 1 THEN 'K1'
    WHEN 2 THEN 'K2'
    WHEN 3 THEN 'K3'
    ELSE 'CUSTOM'
  END AS contract_type,
  COALESCE(ct.duration_months, 3) AS duration_months,
  ct.body_html,
  COALESCE(ct.is_active, 1) AS is_active,
  au.id AS created_by,
  COALESCE(ct.created_at, NOW()) AS created_at,
  ct.updated_at
FROM core.hr_contract_template ct
LEFT JOIN core.org_employee ce ON ce.id = ct.created_by
LEFT JOIN auth_user au ON au.username = ce.username
ORDER BY ct.id;

SET @next_tpl_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract_template);
SET @sql_tpl_ai := CONCAT('ALTER TABLE hr_contract_template AUTO_INCREMENT = ', @next_tpl_ai);
PREPARE stmt_tpl_ai FROM @sql_tpl_ai;
EXECUTE stmt_tpl_ai;
DEALLOCATE PREPARE stmt_tpl_ai;

-- =========================================================
-- 2) Import kontrak dari core (ID dipertahankan)
-- =========================================================
INSERT INTO hr_contract (
  id,
  contract_number,
  employee_id,
  template_id,
  previous_contract_id,
  contract_type,
  status,
  position_snapshot,
  division_snapshot,
  basic_salary,
  position_allowance,
  other_allowance,
  meal_rate,
  overtime_rate,
  start_date,
  end_date,
  verification_token,
  final_document_hash,
  document_issued_at,
  body_html,
  notes,
  generated_by,
  generated_at,
  created_by,
  created_at,
  updated_at
)
SELECT
  cc.id,
  cc.contract_number,
  cc.employee_id,
  cc.template_id,
  NULL AS previous_contract_id,
  CASE cc.contract_type
    WHEN 1 THEN 'K1'
    WHEN 2 THEN 'K2'
    WHEN 3 THEN 'K3'
    ELSE 'CUSTOM'
  END AS contract_type,
  cc.status,
  cc.jabatan AS position_snapshot,
  cc.divisi AS division_snapshot,
  COALESCE(cc.gaji_pokok, 0) AS basic_salary,
  0 AS position_allowance,
  0 AS other_allowance,
  COALESCE(cc.uang_makan, 0) AS meal_rate,
  COALESCE(cc.tarif_lembur, 0) AS overtime_rate,
  cc.tanggal_mulai AS start_date,
  cc.tanggal_akhir AS end_date,
  cc.verification_token,
  cc.final_document_hash,
  cc.document_issued_at,
  cc.body_html,
  cc.notes,
  aug.id AS generated_by,
  cc.generated_at,
  aug.id AS created_by,
  COALESCE(cc.created_at, NOW()) AS created_at,
  cc.updated_at
FROM core.hr_contract cc
JOIN org_employee fe ON fe.id = cc.employee_id
LEFT JOIN core.org_employee ceg ON ceg.id = cc.generated_by
LEFT JOIN auth_user aug ON aug.username = ceg.username
ORDER BY cc.id;

-- previous_contract_id berdasarkan nomor kontrak sebelumnya
UPDATE hr_contract c
JOIN core.hr_contract cc ON cc.id = c.id
LEFT JOIN hr_contract prev ON prev.contract_number = cc.contract_prev_number
SET c.previous_contract_id = prev.id;

-- enrichment tunjangan dari snapshot agar mendekati data core payroll
UPDATE hr_contract c
JOIN core.hr_contract_comp_snapshot cs ON cs.contract_id = c.id
SET c.position_allowance = COALESCE(cs.position_allowance_amount, 0),
    c.other_allowance = COALESCE(cs.objective_allowance_amount, 0)
WHERE (COALESCE(c.position_allowance, 0) = 0 OR COALESCE(c.other_allowance, 0) = 0);

SET @next_ctr_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract);
SET @sql_ctr_ai := CONCAT('ALTER TABLE hr_contract AUTO_INCREMENT = ', @next_ctr_ai);
PREPARE stmt_ctr_ai FROM @sql_ctr_ai;
EXECUTE stmt_ctr_ai;
DEALLOCATE PREPARE stmt_ctr_ai;

-- =========================================================
-- 3) Import approval
-- =========================================================
INSERT INTO hr_contract_approval (
  id,
  contract_id,
  approver_role,
  approval_status,
  approver_name,
  approver_user_id,
  approval_note,
  approved_at,
  revoked_at,
  ip_address,
  user_agent,
  created_at,
  updated_at
)
SELECT
  ca.id,
  ca.contract_id,
  ca.approver_role,
  ca.approval_status,
  ca.approver_name,
  au.id AS approver_user_id,
  ca.approval_note,
  ca.approved_at,
  ca.revoked_at,
  ca.ip_address,
  ca.user_agent,
  COALESCE(ca.created_at, NOW()) AS created_at,
  ca.updated_at
FROM core.hr_contract_approval ca
JOIN hr_contract fc ON fc.id = ca.contract_id
LEFT JOIN core.org_employee ce ON ce.id = ca.approver_user_id
LEFT JOIN auth_user au ON au.username = ce.username
ORDER BY ca.id;

SET @next_appr_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract_approval);
SET @sql_appr_ai := CONCAT('ALTER TABLE hr_contract_approval AUTO_INCREMENT = ', @next_appr_ai);
PREPARE stmt_appr_ai FROM @sql_appr_ai;
EXECUTE stmt_appr_ai;
DEALLOCATE PREPARE stmt_appr_ai;

-- =========================================================
-- 4) Import signature (jika ada)
-- =========================================================
INSERT INTO hr_contract_signature (
  id,
  contract_id,
  signer_role,
  signer_name,
  signer_user_id,
  signature_data,
  signed_at,
  ip_address,
  user_agent
)
SELECT
  cs.id,
  cs.contract_id,
  cs.signer_role,
  cs.signer_name,
  au.id AS signer_user_id,
  cs.signature_data,
  cs.signed_at,
  cs.ip_address,
  cs.user_agent
FROM core.hr_contract_signature cs
JOIN hr_contract fc ON fc.id = cs.contract_id
LEFT JOIN core.org_employee ce ON ce.id = cs.signer_user_id
LEFT JOIN auth_user au ON au.username = ce.username
ORDER BY cs.id;

SET @next_sign_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract_signature);
SET @sql_sign_ai := CONCAT('ALTER TABLE hr_contract_signature AUTO_INCREMENT = ', @next_sign_ai);
PREPARE stmt_sign_ai FROM @sql_sign_ai;
EXECUTE stmt_sign_ai;
DEALLOCATE PREPARE stmt_sign_ai;

-- =========================================================
-- 5) Import compensation snapshot
-- =========================================================
INSERT INTO hr_contract_comp_snapshot (
  id,
  contract_id,
  employee_id,
  effective_start,
  effective_end,
  basic_salary_amount,
  position_allowance_amount,
  other_allowance_amount,
  meal_rate_amount,
  overtime_rate_amount,
  fixed_total_amount,
  source_notes,
  created_at,
  updated_at
)
SELECT
  cs.id,
  cs.contract_id,
  cs.employee_id,
  cs.effective_start,
  cs.effective_end,
  COALESCE(cs.basic_salary_amount, 0),
  COALESCE(cs.position_allowance_amount, 0),
  COALESCE(cs.objective_allowance_amount, 0) AS other_allowance_amount,
  COALESCE(cs.meal_rate_amount, 0),
  COALESCE(cs.overtime_rate_amount, 0),
  COALESCE(cs.fixed_total_amount, 0),
  cs.source_notes,
  COALESCE(cs.created_at, NOW()) AS created_at,
  cs.updated_at
FROM core.hr_contract_comp_snapshot cs
JOIN hr_contract fc ON fc.id = cs.contract_id
JOIN org_employee fe ON fe.id = cs.employee_id
ORDER BY cs.id;

SET @next_snap_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract_comp_snapshot);
SET @sql_snap_ai := CONCAT('ALTER TABLE hr_contract_comp_snapshot AUTO_INCREMENT = ', @next_snap_ai);
PREPARE stmt_snap_ai FROM @sql_snap_ai;
EXECUTE stmt_snap_ai;
DEALLOCATE PREPARE stmt_snap_ai;

INSERT INTO hr_contract_comp_snapshot_line (
  id,
  snapshot_id,
  component_code_snapshot,
  component_name_snapshot,
  component_type,
  amount,
  sort_order,
  created_at
)
SELECT
  cl.id,
  cl.snapshot_id,
  cl.component_code_snapshot,
  cl.component_name_snapshot,
  CASE
    WHEN UPPER(TRIM(cl.component_type)) = 'DEDUCTION' THEN 'DEDUCTION'
    ELSE 'EARNING'
  END AS component_type,
  COALESCE(cl.amount, 0),
  COALESCE(cl.sort_order, 0),
  COALESCE(cl.created_at, NOW())
FROM core.hr_contract_comp_snapshot_line cl
JOIN hr_contract_comp_snapshot fs ON fs.id = cl.snapshot_id
ORDER BY cl.id;

SET @next_line_ai := (SELECT COALESCE(MAX(id), 0) + 1 FROM hr_contract_comp_snapshot_line);
SET @sql_line_ai := CONCAT('ALTER TABLE hr_contract_comp_snapshot_line AUTO_INCREMENT = ', @next_line_ai);
PREPARE stmt_line_ai FROM @sql_line_ai;
EXECUTE stmt_line_ai;
DEALLOCATE PREPARE stmt_line_ai;

COMMIT;
