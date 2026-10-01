-- Additive only: no transaction backfill, cash posting or approval activation.
ALTER TABLE fin_settlement_control ADD COLUMN IF NOT EXISTS receipt_mode TINYINT NOT NULL DEFAULT 0,
 ADD COLUMN IF NOT EXISTS receipt_opening_amount DECIMAL(18,2) NULL;
CREATE TABLE IF NOT EXISTS fin_settlement_receipt (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, settlement_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL, received_date DATE NOT NULL, reference_no VARCHAR(80) NOT NULL,
 active_reference_hash CHAR(64) NULL, amount DECIMAL(18,2) NOT NULL, evidence_id BIGINT UNSIGNED NULL,
 request_key CHAR(32) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'ACTIVE', notes VARCHAR(255) NOT NULL,
 created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_by BIGINT UNSIGNED NULL, voided_at DATETIME NULL, void_reason VARCHAR(255) NULL,
 UNIQUE KEY uq_fin_receipt_request(request_key), UNIQUE KEY uq_fin_receipt_ref(account_id,active_reference_hash),
 KEY ix_fin_receipt_case(settlement_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_settlement_charge (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, settlement_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL, charge_date DATE NOT NULL, document_no VARCHAR(80) NOT NULL,
 line_reference VARCHAR(40) NOT NULL, identity_hash CHAR(64) NOT NULL,
 category VARCHAR(40) NOT NULL, direction VARCHAR(3) NOT NULL, amount DECIMAL(18,2) NOT NULL,
 evidence_id BIGINT UNSIGNED NULL, request_key CHAR(32) NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1,
 notes VARCHAR(255) NOT NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_fin_charge_request(request_key), UNIQUE KEY uq_fin_charge_identity(account_id,identity_hash),
 KEY ix_fin_charge_case(settlement_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE fin_account_mutation_log ADD COLUMN IF NOT EXISTS settlement_charge_id BIGINT UNSIGNED NULL,
 ADD INDEX IF NOT EXISTS ix_fin_mutation_charge(settlement_charge_id);
ALTER TABLE fin_cash_reconciliation_line ADD COLUMN IF NOT EXISTS settlement_charge_id BIGINT UNSIGNED NULL;
ALTER TABLE fin_revenue_reconciliation_line ADD COLUMN IF NOT EXISTS settlement_charge_id BIGINT UNSIGNED NULL;
CREATE TABLE IF NOT EXISTS fin_cash_plan_realization (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, plan_id BIGINT UNSIGNED NOT NULL,
 mutation_id BIGINT UNSIGNED NOT NULL, active_mutation_id BIGINT UNSIGNED NULL,
 notes VARCHAR(255) NOT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 unlinked_by BIGINT UNSIGNED NULL, unlinked_at DATETIME NULL, unlink_reason VARCHAR(255) NULL,
 UNIQUE KEY uq_fin_plan_mutation(active_mutation_id), KEY ix_fin_realization_plan(plan_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_control_evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, settlement_id BIGINT UNSIGNED NOT NULL,
 storage_name CHAR(64) NOT NULL, original_name VARCHAR(160) NOT NULL, mime_type VARCHAR(50) NOT NULL,
 byte_size INT UNSIGNED NOT NULL, sha256 CHAR(64) NOT NULL, created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_fin_evidence_storage(storage_name),
 KEY ix_fin_evidence_case(settlement_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fin_control_policy (
 id INT UNSIGNED PRIMARY KEY, approval_enabled TINYINT NOT NULL DEFAULT 0,
 approval_threshold DECIMAL(18,2) NOT NULL DEFAULT 1000000, evidence_required TINYINT NOT NULL DEFAULT 0,
 payroll_day TINYINT UNSIGNED NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1,
 updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO fin_control_policy(id) VALUES(1);
CREATE TABLE IF NOT EXISTS fin_control_approval (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, action_code VARCHAR(24) NOT NULL, target_id BIGINT UNSIGNED NOT NULL,
 payload_hash CHAR(64) NOT NULL, requested_by BIGINT UNSIGNED NOT NULL, reviewed_by BIGINT UNSIGNED NULL,
 status VARCHAR(12) NOT NULL DEFAULT 'PENDING', reason VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, reviewed_at DATETIME NULL, consumed_at DATETIME NULL,
 KEY ix_fin_approval_target(action_code,target_id,status,payload_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO sys_page(page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.control.approve','Persetujuan penyesuaian settlement','FINANCE','FINANCE','Review oleh pengguna berbeda dari pembuat dan pengaju',1
WHERE NOT EXISTS(SELECT 1 FROM sys_page WHERE page_code='finance.control.approve');
INSERT INTO sys_page(page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.control.settings','Kebijakan kontrol keuangan','FINANCE','FINANCE','Ambang persetujuan, bukti, dan jadwal proyeksi payroll',1
WHERE NOT EXISTS(SELECT 1 FROM sys_page WHERE page_code='finance.control.settings');
INSERT INTO auth_role_permission(role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,0,1,0,0 FROM auth_role r JOIN sys_page p ON p.page_code IN ('finance.control.approve','finance.control.settings')
WHERE r.role_code='SUPERADMIN' AND NOT EXISTS(SELECT 1 FROM auth_role_permission x WHERE x.role_id=r.id AND x.page_id=p.id);
-- Rollback: keep metadata/audit/evidence, disable operations UI. Do not drop historic records.
