SET NAMES utf8mb4;

-- Rekonsiliasi penerimaan penjualan per metode pembayaran. Omzet bruto berasal
-- dari POS, sedangkan nilai diterima diisi sesuai settlement provider.
CREATE TABLE IF NOT EXISTS fin_revenue_reconciliation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reconciliation_no VARCHAR(70) NOT NULL,
  reconciliation_date DATE NOT NULL,
  revenue_date DATE NOT NULL,
  round_no INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('OPEN','COMPLETED') NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_revenue_recon_no (reconciliation_no),
  UNIQUE KEY uk_fin_revenue_recon_dates_round (reconciliation_date, revenue_date, round_no),
  KEY idx_fin_revenue_recon_revenue_date (revenue_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS fin_revenue_reconciliation_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reconciliation_id BIGINT UNSIGNED NOT NULL,
  payment_method_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NULL,
  expected_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_amount DECIMAL(18,2) NULL,
  difference_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  transaction_count INT UNSIGNED NOT NULL DEFAULT 0,
  resolution_type ENUM('NONE','IN','OUT') NOT NULL DEFAULT 'NONE',
  resolution_note VARCHAR(255) NULL,
  status ENUM('UNCHECKED','MATCHED','OPEN','POSTED') NOT NULL DEFAULT 'UNCHECKED',
  mutation_id BIGINT UNSIGNED NULL,
  entered_by BIGINT UNSIGNED NULL,
  entered_at DATETIME NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_revenue_recon_line_method (reconciliation_id, payment_method_id),
  KEY idx_fin_revenue_recon_line_account (account_id),
  KEY idx_fin_revenue_recon_line_mutation (mutation_id),
  CONSTRAINT fk_fin_revenue_recon_line_header FOREIGN KEY (reconciliation_id) REFERENCES fin_revenue_reconciliation(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fin_revenue_recon_line_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fin_revenue_recon_line_account FOREIGN KEY (account_id) REFERENCES fin_company_account(id) ON DELETE RESTRICT,
  CONSTRAINT fk_fin_revenue_recon_line_mutation FOREIGN KEY (mutation_id) REFERENCES fin_account_mutation_log(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS fin_revenue_reconciliation_method (
  payment_method_id BIGINT UNSIGNED NOT NULL,
  settlement_delay_days INT UNSIGNED NOT NULL DEFAULT 1,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (payment_method_id),
  CONSTRAINT fk_fin_revenue_recon_setting_method FOREIGN KEY (payment_method_id) REFERENCES pos_payment_method(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO fin_revenue_reconciliation_method (payment_method_id, settlement_delay_days, is_enabled)
SELECT id, CASE WHEN method_type = 'CASH' THEN 0 ELSE 1 END, 1
FROM pos_payment_method
WHERE is_active = 1;

START TRANSACTION;
INSERT INTO sys_page (page_code, page_name, module, matrix_group, description, is_active)
VALUES ('finance.revenue_reconciliation.index', 'Rekonsiliasi Pendapatan', 'FINANCE', 'FINANCE',
  'Rekonsiliasi omzet POS dengan settlement riil per metode pembayaran.', 1)
ON DUPLICATE KEY UPDATE page_name=VALUES(page_name), description=VALUES(description), is_active=1, updated_at=CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'finance.revenue_reconciliation', 'Rekonsiliasi Pendapatan', 'ri-hand-coin-line',
  '/finance-reports/revenue-reconciliation', page.id, 5, 1, 'MAIN', parent.id
FROM sys_page page
JOIN sys_menu parent ON parent.menu_code='grp.finance'
WHERE page.page_code='finance.revenue_reconciliation.index'
ON DUPLICATE KEY UPDATE menu_label=VALUES(menu_label), icon=VALUES(icon), url=VALUES(url),
  page_id=VALUES(page_id), sort_order=VALUES(sort_order), is_active=1, sidebar_type=VALUES(sidebar_type),
  parent_id=VALUES(parent_id), updated_at=CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT source.role_id, target.id, source.can_view, source.can_create, source.can_edit, 0, source.can_export, NOW()
FROM auth_role_permission source
JOIN sys_page old_page ON old_page.id=source.page_id AND old_page.page_code='finance.cash_reconciliation.index'
JOIN sys_page target ON target.page_code='finance.revenue_reconciliation.index'
ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_create=VALUES(can_create), can_edit=VALUES(can_edit), can_export=VALUES(can_export), updated_at=CURRENT_TIMESTAMP;

UPDATE auth_role role
JOIN auth_role_permission permission ON permission.role_id=role.id
JOIN sys_page page ON page.id=permission.page_id
SET role.permissions_updated_at=CURRENT_TIMESTAMP
WHERE page.page_code='finance.revenue_reconciliation.index';
COMMIT;
