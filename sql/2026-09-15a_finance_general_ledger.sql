-- BELUM DIJALANKAN. Additive journal metadata; no historic posting or balance changes.
-- Requires fin_company_account, fin_account_mutation_log, fin_period_close,
-- aud_transaction_log and the existing page/menu/permission registry.
-- Review/backup in IDE before applying. Not yet a customer managed migration.
CREATE TABLE IF NOT EXISTS fin_gl_guard (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT IGNORE INTO fin_gl_guard (id) VALUES (1);

CREATE TABLE IF NOT EXISTS fin_gl_account (
 code VARCHAR(20) NOT NULL PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 account_type VARCHAR(12) NOT NULL,
 is_cash TINYINT NOT NULL DEFAULT 0,
 is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Reference accounts only; no company identity, opening amounts or transactions.
INSERT IGNORE INTO fin_gl_account (code,name,account_type,is_cash) VALUES
 ('1100','Kas dan rekening usaha','ASSET',1),
 ('1190','Perantara transfer internal','ASSET',0),
 ('1200','Piutang usaha / tagihan platform','ASSET',0),
 ('1300','Persediaan','ASSET',0),
 ('1400','Aset tetap','ASSET',0),
 ('1490','Akumulasi penyusutan (kontra aset)','ASSET',0),
 ('1500','Biaya dibayar di muka','ASSET',0),
 ('2100','Utang usaha','LIABILITY',0),
 ('2200','Uang muka pelanggan / DP','LIABILITY',0),
 ('2300','Utang pajak','LIABILITY',0),
 ('2400','Utang gaji','LIABILITY',0),
 ('2500','Utang pinjaman','LIABILITY',0),
 ('3100','Modal pemilik','EQUITY',0),
 ('3200','Saldo laba awal','EQUITY',0),
 ('3300','Prive (pengurang ekuitas)','EQUITY',0),
 ('4100','Pendapatan penjualan','INCOME',0),
 ('4200','Pendapatan lain-lain / selisih lebih terverifikasi','INCOME',0),
 ('5100','Harga pokok penjualan','EXPENSE',0),
 ('5200','Beban operasional','EXPENSE',0),
 ('5300','Beban promo ditanggung usaha','EXPENSE',0),
 ('5400','Beban platform','EXPENSE',0),
 ('5500','Beban gaji','EXPENSE',0),
 ('5600','Selisih kas kurang terverifikasi','EXPENSE',0),
 ('5700','Beban penyusutan','EXPENSE',0);

CREATE TABLE IF NOT EXISTS fin_gl_journal (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 journal_date DATE NOT NULL,
 kind VARCHAR(16) NOT NULL,
 reference VARCHAR(120) NOT NULL,
 memo VARCHAR(500) NOT NULL,
 request_key CHAR(32) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 source_mutation_id BIGINT UNSIGNED NULL,
 source_hash CHAR(64) NULL,
 cashflow_class VARCHAR(16) NULL,
 reversal_of BIGINT UNSIGNED NULL,
 opening_key TINYINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_gl_request (request_key),
 UNIQUE KEY uq_gl_source (source_mutation_id),
 UNIQUE KEY uq_gl_reversal (reversal_of),
 UNIQUE KEY uq_gl_opening (opening_key),
 KEY ix_gl_date (journal_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_gl_line (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 journal_id BIGINT UNSIGNED NOT NULL,
 line_no SMALLINT UNSIGNED NOT NULL,
 account_code VARCHAR(20) NOT NULL,
 company_account_id BIGINT UNSIGNED NULL,
 debit DECIMAL(18,2) NOT NULL DEFAULT 0,
 credit DECIMAL(18,2) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_gl_line (journal_id,line_no),
 KEY ix_gl_account (account_code,journal_id),
 KEY ix_gl_cash (company_account_id,journal_id),
 CONSTRAINT fk_gl_line_journal FOREIGN KEY (journal_id) REFERENCES fin_gl_journal(id),
 CONSTRAINT fk_gl_line_account FOREIGN KEY (account_code) REFERENCES fin_gl_account(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sys_page (page_code,page_name,module,matrix_group,description,is_active)
SELECT 'finance.accounting.index','Akuntansi dan Jurnal','FINANCE','FINANCE','Arus kas aktual, jurnal, buku besar, neraca saldo, laba-rugi dan neraca',1
WHERE NOT EXISTS (SELECT 1 FROM sys_page WHERE page_code='finance.accounting.index');
INSERT INTO sys_menu (parent_id,menu_code,menu_label,icon,url,page_id,sort_order,is_active,sidebar_type)
SELECT (SELECT id FROM sys_menu WHERE menu_code='grp.finance' LIMIT 1),'finance.accounting','Akuntansi dan Jurnal','ri-book-open-line',
 'finance-reports/accounting',p.id,96,1,'MAIN' FROM sys_page p WHERE p.page_code='finance.accounting.index'
 AND NOT EXISTS (SELECT 1 FROM sys_menu WHERE menu_code='finance.accounting');
INSERT INTO auth_role_permission (role_id,page_id,can_view,can_create,can_edit,can_delete,can_export)
SELECT r.id,p.id,1,1,0,0,0 FROM auth_role r JOIN sys_page p ON p.page_code='finance.accounting.index'
WHERE r.role_code='SUPERADMIN' AND NOT EXISTS (SELECT 1 FROM auth_role_permission a WHERE a.role_id=r.id AND a.page_id=p.id);
-- Keep journal history on rollback; revert code / disable menu, never drop records.
