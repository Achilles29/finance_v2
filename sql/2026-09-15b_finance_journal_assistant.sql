-- BELUM DIJALANKAN. Requires 2026-09-15a. Metadata only; no automatic journal posting.
CREATE TABLE IF NOT EXISTS fin_gl_mapping (
 scenario_code VARCHAR(40) NOT NULL PRIMARY KEY,
 account_code VARCHAR(20) NOT NULL,
 cashflow_class VARCHAR(16) NOT NULL,
 is_enabled TINYINT NOT NULL DEFAULT 1,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 updated_by BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_gl_mapping_account FOREIGN KEY (account_code) REFERENCES fin_gl_account(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- No mappings seeded: default suggestions must be explicitly reviewed in the UI.
INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.accounting.settings','Pengaturan Akun Jurnal','FINANCE','FINANCE','COA nonkas dan pemetaan saran jurnal, bukan auto-post',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='finance.accounting.settings');
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,0,1,0,0 FROM auth_role r JOIN sys_page p ON p.page_code='finance.accounting.settings'
WHERE r.role_code='SUPERADMIN' AND NOT EXISTS (SELECT 1 FROM auth_role_permission a WHERE a.role_id=r.id AND a.page_id=p.id);
-- Uses the existing Accounting sidebar and a new tab; never duplicate the root menu.
-- Rollback code only: preserve configuration/audit/journal history. Do not drop tables.
