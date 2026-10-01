-- Additive metadata only. Never backfill transaction amounts or account balances.
CREATE TABLE IF NOT EXISTS fin_settlement_control (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 revenue_date DATE NOT NULL,
 payment_method_id BIGINT UNSIGNED NOT NULL,
 account_id BIGINT UNSIGNED NOT NULL,
 provider_reference VARCHAR(80) NOT NULL,
 due_date DATE NOT NULL,
 received_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 settlement_complete TINYINT NOT NULL DEFAULT 0,
 expected_amount DECIMAL(18,2) NOT NULL,
 source_fingerprint CHAR(64) NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 notes VARCHAR(255) NOT NULL DEFAULT '',
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_fin_settlement_day_method (revenue_date,payment_method_id),
 UNIQUE KEY uq_fin_settlement_provider (payment_method_id,provider_reference),
 KEY ix_fin_settlement_due (due_date,account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_cash_plan (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_key CHAR(32) NOT NULL,
 title VARCHAR(160) NOT NULL,
 direction VARCHAR(3) NOT NULL,
 amount DECIMAL(18,2) NOT NULL,
 due_date DATE NOT NULL,
 certainty VARCHAR(12) NOT NULL DEFAULT 'ESTIMATE',
 status VARCHAR(12) NOT NULL DEFAULT 'OPEN',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 notes VARCHAR(255) NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_fin_cash_plan_request (request_key),
 KEY ix_fin_cash_plan_due (status,due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fin_account_mutation_log ADD COLUMN IF NOT EXISTS settlement_control_id BIGINT UNSIGNED NULL,
 ADD INDEX IF NOT EXISTS ix_fin_mutation_settlement (settlement_control_id,report_category);
ALTER TABLE fin_cash_reconciliation_line ADD COLUMN IF NOT EXISTS settlement_control_id BIGINT UNSIGNED NULL;
ALTER TABLE fin_revenue_reconciliation_line ADD COLUMN IF NOT EXISTS settlement_control_id BIGINT UNSIGNED NULL;

INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.control.index','Kontrol Keuangan','FINANCE','FINANCE','Settlement, kualitas laporan, proyeksi kas dan laba-rugi HPP',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='finance.control.index');
INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT (SELECT id FROM sys_menu WHERE menu_code='grp.finance' LIMIT 1),'finance.control','Kontrol Keuangan','ri-funds-box-line',
 'finance-reports/control',p.id,95,1,'MAIN' FROM sys_page p WHERE p.page_code='finance.control.index'
 AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='finance.control');
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,1,1,0,0 FROM auth_role r JOIN sys_page p ON p.page_code='finance.control.index'
WHERE r.role_code='SUPERADMIN' AND NOT EXISTS (SELECT 1 FROM auth_role_permission a WHERE a.role_id=r.id AND a.page_id=p.id);
-- Rollback application code: keep nullable metadata and audit trail; disable new menu if needed.
