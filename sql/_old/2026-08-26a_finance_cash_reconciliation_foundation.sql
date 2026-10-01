SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-26a_finance_cash_reconciliation_foundation.sql
-- Tujuan :
-- 1) Menyediakan dokumen dan baris audit rekonsiliasi kas harian
-- 2) Menjaga saldo riil sebagai hasil hitung sampai penyesuaian diposting
-- 3) Mendaftarkan menu Rekonsiliasi Kas di rumpun Keuangan
-- Catatan:
-- - Script ini tidak membuat mutasi rekening dan tidak mengubah saldo akun.
-- - Mutasi baru dibuat dari aplikasi setelah pengguna menekan Posting.
-- ============================================================

CREATE TABLE IF NOT EXISTS fin_cash_reconciliation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reconciliation_no VARCHAR(60) NOT NULL,
  reconciliation_date DATE NOT NULL,
  status ENUM('DRAFT','COMPLETED') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_cash_recon_no (reconciliation_no),
  UNIQUE KEY uk_fin_cash_recon_date (reconciliation_date),
  KEY idx_fin_cash_recon_status_date (status, reconciliation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Header rekonsiliasi saldo riil rekening perusahaan';

CREATE TABLE IF NOT EXISTS fin_cash_reconciliation_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reconciliation_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  system_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_balance DECIMAL(18,2) NULL,
  difference_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  resolution_type ENUM('NONE','IN','OUT','TRANSFER') NOT NULL DEFAULT 'NONE',
  counter_account_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(255) NULL,
  status ENUM('UNCHECKED','MATCHED','OPEN','POSTED') NOT NULL DEFAULT 'UNCHECKED',
  mutation_id BIGINT UNSIGNED NULL,
  counter_mutation_id BIGINT UNSIGNED NULL,
  entered_by BIGINT UNSIGNED NULL,
  entered_at DATETIME NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_cash_recon_line_account (reconciliation_id, account_id),
  KEY idx_fin_cash_recon_line_account (account_id),
  KEY idx_fin_cash_recon_line_status (status),
  KEY idx_fin_cash_recon_line_counter (counter_account_id),
  KEY idx_fin_cash_recon_line_mutation (mutation_id),
  KEY idx_fin_cash_recon_line_counter_mutation (counter_mutation_id),
  CONSTRAINT fk_fin_cash_recon_line_header
    FOREIGN KEY (reconciliation_id) REFERENCES fin_cash_reconciliation(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fin_cash_recon_line_account
    FOREIGN KEY (account_id) REFERENCES fin_company_account(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fin_cash_recon_line_counter_account
    FOREIGN KEY (counter_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_cash_recon_line_mutation
    FOREIGN KEY (mutation_id) REFERENCES fin_account_mutation_log(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_cash_recon_line_counter_mutation
    FOREIGN KEY (counter_mutation_id) REFERENCES fin_account_mutation_log(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Snapshot saldo sistem, saldo riil, dan keputusan penyesuaian per rekening';

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
VALUES (
  'finance.cash_reconciliation.index',
  'Rekonsiliasi Kas',
  'FINANCE',
  'FINANCE',
  'Rekonsiliasi saldo sistem dan saldo riil rekening, dengan tindak lanjut mutasi atau transfer yang diaudit.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  matrix_group = VALUES(matrix_group),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'finance.cash_reconciliation',
  'Rekonsiliasi Kas',
  'ri-scales-3-line',
  '/finance-reports/cash-reconciliation',
  p.id,
  4,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.finance'
WHERE p.page_code = 'finance.cash_reconciliation.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

-- Menyalin akses lihat dari Posisi Kas. Pengguna operasional keuangan
-- memperoleh edit agar dapat menyimpan hitung dan memposting penyesuaian.
INSERT INTO auth_role_permission (
  role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at
)
SELECT
  source.role_id,
  target_page.id,
  source.can_view,
  CASE WHEN role.role_code IN ('SUPERADMIN','ADMIN','ADM_FIN') THEN 1 ELSE 0 END,
  CASE WHEN role.role_code IN ('SUPERADMIN','ADMIN','ADM_FIN') THEN 1 ELSE 0 END,
  0,
  source.can_export,
  NOW()
FROM auth_role_permission source
JOIN sys_page source_page ON source_page.id = source.page_id
JOIN sys_page target_page ON target_page.page_code = 'finance.cash_reconciliation.index'
JOIN auth_role role ON role.id = source.role_id
WHERE source_page.page_code = 'finance.cash_position.index'
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

UPDATE auth_role role
JOIN auth_role_permission permission ON permission.role_id = role.id
JOIN sys_page page ON page.id = permission.page_id
SET role.permissions_updated_at = CURRENT_TIMESTAMP
WHERE page.page_code = 'finance.cash_reconciliation.index';

COMMIT;

SELECT
  page.page_code,
  menu.menu_label,
  menu.url,
  role.role_code,
  permission.can_view,
  permission.can_edit
FROM sys_page page
LEFT JOIN sys_menu menu ON menu.page_id = page.id AND menu.menu_code = 'finance.cash_reconciliation'
LEFT JOIN auth_role_permission permission ON permission.page_id = page.id
LEFT JOIN auth_role role ON role.id = permission.role_id
WHERE page.page_code = 'finance.cash_reconciliation.index'
ORDER BY role.role_code;
