SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13g_finance_reporting_cutoff_target_foundation.sql
-- Tujuan :
-- 1) Menyediakan fondasi cut off / close period laporan keuangan
-- 2) Menyimpan snapshot saldo fisik, saldo riil, dan historis saldo tetap
-- 3) Menyediakan tabel metric summary period untuk laporan manajerial
-- 4) Menyediakan tabel target harian/bulanan/tahunan + realisasi bonus
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS fin_period_close (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_code VARCHAR(30) NOT NULL,
  period_type ENUM('MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  snapshot_version INT UNSIGNED NOT NULL DEFAULT 1,
  close_mode ENUM('AUTO_REBUILD','MANUAL_LOCK') NOT NULL DEFAULT 'AUTO_REBUILD',
  status ENUM('OPEN','CLOSED','REOPENED','VOID') NOT NULL DEFAULT 'OPEN',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  closed_by BIGINT UNSIGNED NULL,
  reopened_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  reopened_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_period_close_code_version (period_code, snapshot_version),
  KEY idx_fin_period_close_type_status (period_type, status),
  KEY idx_fin_period_close_range (period_start, period_end),
  CONSTRAINT fk_fin_period_close_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_period_close_closed_by FOREIGN KEY (closed_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_period_close_reopened_by FOREIGN KEY (reopened_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_account_period_snapshot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_close_id BIGINT UNSIGNED NOT NULL,
  company_account_id BIGINT UNSIGNED NOT NULL,
  account_code_snapshot VARCHAR(60) NOT NULL,
  account_name_snapshot VARCHAR(150) NOT NULL,
  account_type_snapshot VARCHAR(40) NULL,
  bank_name_snapshot VARCHAR(120) NULL,
  opening_balance_physical DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  mutation_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  mutation_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  closing_balance_physical DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  receivable_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payable_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  cash_advance_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payroll_pending DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  historical_keep_balance_net DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  closing_balance_real DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  pos_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  pos_refund_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  purchase_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payroll_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  cash_advance_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payable_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payable_payment_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  receivable_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  receivable_payment_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  transfer_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  transfer_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  manual_in_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  manual_out_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_account_period_snapshot_unique (period_close_id, company_account_id),
  KEY idx_fin_account_period_snapshot_account (company_account_id),
  CONSTRAINT fk_fin_account_period_snapshot_period FOREIGN KEY (period_close_id) REFERENCES fin_period_close(id) ON DELETE CASCADE,
  CONSTRAINT fk_fin_account_period_snapshot_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_management_period_metric (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_close_id BIGINT UNSIGNED NOT NULL,
  scope_type ENUM('GLOBAL','DIVISION','ACCOUNT') NOT NULL DEFAULT 'GLOBAL',
  scope_ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  metric_group VARCHAR(60) NOT NULL,
  metric_code VARCHAR(80) NOT NULL,
  metric_label VARCHAR(150) NOT NULL,
  metric_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  metric_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  source_ref VARCHAR(120) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_management_period_metric_unique (period_close_id, scope_type, scope_ref_id, metric_code),
  KEY idx_fin_management_period_metric_group (metric_group, metric_code),
  CONSTRAINT fk_fin_management_period_metric_period FOREIGN KEY (period_close_id) REFERENCES fin_period_close(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_metric_catalog (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_code VARCHAR(80) NOT NULL,
  metric_group VARCHAR(60) NOT NULL,
  metric_label VARCHAR(150) NOT NULL,
  metric_unit ENUM('AMOUNT','QTY','PERCENT','DAYS','COUNT') NOT NULL DEFAULT 'AMOUNT',
  metric_scope ENUM('GLOBAL','DIVISION','ACCOUNT','PERIOD') NOT NULL DEFAULT 'GLOBAL',
  comparator_hint ENUM('MIN','MAX','RANGE','EQUAL') NOT NULL DEFAULT 'MAX',
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_metric_catalog_code (metric_code),
  KEY idx_fin_metric_catalog_group (metric_group),
  KEY idx_fin_metric_catalog_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_target_plan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_code VARCHAR(40) NOT NULL,
  target_name VARCHAR(180) NOT NULL,
  target_scope ENUM('DAILY','MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  target_year SMALLINT UNSIGNED NOT NULL,
  target_month TINYINT UNSIGNED NULL,
  target_date DATE NULL,
  date_start DATE NOT NULL,
  date_end DATE NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  company_account_id BIGINT UNSIGNED NULL,
  status ENUM('DRAFT','ACTIVE','LOCKED','VOID') NOT NULL DEFAULT 'DRAFT',
  bonus_gate_mode ENUM('NONE','ALL_REQUIRED','WEIGHTED_SCORE') NOT NULL DEFAULT 'WEIGHTED_SCORE',
  min_bonus_score DECIMAL(7,2) NOT NULL DEFAULT 100.00,
  bonus_pool_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  bonus_percent_of_profit DECIMAL(9,4) NOT NULL DEFAULT 0.0000,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_target_plan_code (target_code),
  KEY idx_fin_target_plan_scope_period (target_scope, target_year, target_month, target_date),
  KEY idx_fin_target_plan_status (status),
  KEY idx_fin_target_plan_division (division_id),
  KEY idx_fin_target_plan_account (company_account_id),
  CONSTRAINT fk_fin_target_plan_division FOREIGN KEY (division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_target_plan_account FOREIGN KEY (company_account_id) REFERENCES fin_company_account(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_target_plan_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_fin_target_plan_approved_by FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_target_plan_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_plan_id BIGINT UNSIGNED NOT NULL,
  metric_group VARCHAR(60) NOT NULL,
  metric_code VARCHAR(80) NOT NULL,
  metric_label VARCHAR(150) NOT NULL,
  comparator ENUM('MIN','MAX','RANGE','EQUAL') NOT NULL DEFAULT 'MIN',
  target_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  minimum_value DECIMAL(18,2) NULL,
  maximum_value DECIMAL(18,2) NULL,
  warning_value DECIMAL(18,2) NULL,
  weight_percent DECIMAL(9,4) NOT NULL DEFAULT 0.0000,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_target_plan_line_unique (target_plan_id, metric_code),
  KEY idx_fin_target_plan_line_group (metric_group),
  CONSTRAINT fk_fin_target_plan_line_header FOREIGN KEY (target_plan_id) REFERENCES fin_target_plan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fin_target_realization (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_plan_id BIGINT UNSIGNED NOT NULL,
  target_plan_line_id BIGINT UNSIGNED NOT NULL,
  period_close_id BIGINT UNSIGNED NULL,
  realization_date DATE NOT NULL,
  metric_code VARCHAR(80) NOT NULL,
  target_value_snapshot DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  score_percent DECIMAL(9,2) NOT NULL DEFAULT 0.00,
  is_passed TINYINT(1) NOT NULL DEFAULT 0,
  bonus_gate_passed TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fin_target_realization_unique (target_plan_line_id, realization_date),
  KEY idx_fin_target_realization_plan (target_plan_id, realization_date),
  KEY idx_fin_target_realization_period (period_close_id),
  CONSTRAINT fk_fin_target_realization_plan FOREIGN KEY (target_plan_id) REFERENCES fin_target_plan(id) ON DELETE CASCADE,
  CONSTRAINT fk_fin_target_realization_line FOREIGN KEY (target_plan_line_id) REFERENCES fin_target_plan_line(id) ON DELETE CASCADE,
  CONSTRAINT fk_fin_target_realization_period FOREIGN KEY (period_close_id) REFERENCES fin_period_close(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fin_metric_catalog (
  metric_code, metric_group, metric_label, metric_unit, metric_scope, comparator_hint, description, is_active
) VALUES
  ('POS_REVENUE', 'REVENUE', 'Omzet POS', 'AMOUNT', 'GLOBAL', 'MIN', 'Total penerimaan penjualan POS.', 1),
  ('POS_REFUND', 'REVENUE', 'Refund POS', 'AMOUNT', 'GLOBAL', 'MAX', 'Total refund penjualan POS.', 1),
  ('PURCHASE_RAW_MATERIAL', 'PURCHASE', 'Belanja Bahan Baku', 'AMOUNT', 'GLOBAL', 'MAX', 'Nilai pembelian bahan baku.', 1),
  ('PURCHASE_OPERATIONAL', 'PURCHASE', 'Belanja Operasional', 'AMOUNT', 'GLOBAL', 'MAX', 'Nilai pembelian operasional outlet.', 1),
  ('PURCHASE_UTILITY', 'PURCHASE', 'Belanja Utilitas', 'AMOUNT', 'GLOBAL', 'MAX', 'Beban listrik, air, internet, gas, dan sejenisnya.', 1),
  ('PURCHASE_ASSET', 'PURCHASE', 'Belanja Aset', 'AMOUNT', 'GLOBAL', 'MAX', 'Pembelian asset, inventaris, atau equipment.', 1),
  ('PURCHASE_OTHER', 'PURCHASE', 'Belanja Lainnya', 'AMOUNT', 'GLOBAL', 'MAX', 'Pembelian di luar kelompok utama.', 1),
  ('SR_PENDING_VALUE', 'PROCUREMENT', 'SR Pending', 'AMOUNT', 'DIVISION', 'MAX', 'Nilai store request yang belum direalisasikan.', 1),
  ('PAYROLL_ESTIMATE_RUNNING', 'PAYROLL', 'Estimasi Gaji Berjalan', 'AMOUNT', 'DIVISION', 'MAX', 'Estimasi gaji yang sedang berjalan.', 1),
  ('PAYROLL_DISBURSED', 'PAYROLL', 'Pencairan Gaji', 'AMOUNT', 'DIVISION', 'MAX', 'Gaji yang sudah benar-benar cair.', 1),
  ('PAYABLE_OUTSTANDING', 'EXPOSURE', 'Utang Outstanding', 'AMOUNT', 'ACCOUNT', 'MAX', 'Sisa kewajiban aktif ke pihak luar.', 1),
  ('RECEIVABLE_OUTSTANDING', 'EXPOSURE', 'Piutang Outstanding', 'AMOUNT', 'ACCOUNT', 'MAX', 'Sisa piutang aktif ke pihak luar.', 1),
  ('CASH_ADVANCE_OUTSTANDING', 'EXPOSURE', 'Kasbon Outstanding', 'AMOUNT', 'DIVISION', 'MAX', 'Sisa kasbon pegawai yang belum tertutup.', 1),
  ('LIVE_HPP_VALUE', 'INVENTORY_COST', 'HPP Live', 'AMOUNT', 'DIVISION', 'MAX', 'Pembacaan variable cost / HPP live.', 1),
  ('WAREHOUSE_ADJUSTMENT_VALUE', 'INVENTORY_ADJ', 'Adjustment Gudang', 'AMOUNT', 'DIVISION', 'MAX', 'Nilai spoil, waste, atau koreksi stok gudang.', 1),
  ('DIVISION_ADJUSTMENT_VALUE', 'INVENTORY_ADJ', 'Adjustment Bahan Baku Divisi', 'AMOUNT', 'DIVISION', 'MAX', 'Nilai koreksi stok bahan baku divisi.', 1),
  ('COMPONENT_ADJUSTMENT_VALUE', 'INVENTORY_ADJ', 'Adjustment Component', 'AMOUNT', 'DIVISION', 'MAX', 'Nilai koreksi base-prepare atau component.', 1),
  ('RAW_MATERIAL_IN_VALUE', 'INVENTORY_FLOW', 'Bahan Baku Masuk', 'AMOUNT', 'DIVISION', 'MIN', 'Nilai bahan baku yang masuk ke sistem.', 1),
  ('RAW_MATERIAL_USAGE_VALUE', 'INVENTORY_FLOW', 'Bahan Baku Terpakai', 'AMOUNT', 'DIVISION', 'MAX', 'Nilai bahan baku yang terpakai operasional.', 1),
  ('WAREHOUSE_ENDING_STOCK_VALUE', 'INVENTORY_POSITION', 'Stok Akhir Gudang', 'AMOUNT', 'DIVISION', 'MIN', 'Nilai stok akhir yang masih tersimpan di gudang.', 1),
  ('DIVISION_ENDING_STOCK_VALUE', 'INVENTORY_POSITION', 'Stok Akhir Divisi', 'AMOUNT', 'DIVISION', 'MIN', 'Nilai stok akhir yang masih tersimpan di divisi.', 1),
  ('REAL_BALANCE_VALUE', 'CASH_POSITION', 'Saldo Riil', 'AMOUNT', 'ACCOUNT', 'MIN', 'Posisi saldo riil kafe.', 1),
  ('PHYSICAL_BALANCE_VALUE', 'CASH_POSITION', 'Saldo Fisik', 'AMOUNT', 'ACCOUNT', 'MIN', 'Posisi saldo fisik rekening.', 1)
ON DUPLICATE KEY UPDATE
  metric_group = VALUES(metric_group),
  metric_label = VALUES(metric_label),
  metric_unit = VALUES(metric_unit),
  metric_scope = VALUES(metric_scope),
  comparator_hint = VALUES(comparator_hint),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'fin_period_close' AS table_name, COUNT(*) AS total_rows FROM fin_period_close
UNION ALL
SELECT 'fin_account_period_snapshot', COUNT(*) FROM fin_account_period_snapshot
UNION ALL
SELECT 'fin_management_period_metric', COUNT(*) FROM fin_management_period_metric
UNION ALL
SELECT 'fin_metric_catalog', COUNT(*) FROM fin_metric_catalog
UNION ALL
SELECT 'fin_target_plan', COUNT(*) FROM fin_target_plan
UNION ALL
SELECT 'fin_target_plan_line', COUNT(*) FROM fin_target_plan_line
UNION ALL
SELECT 'fin_target_realization', COUNT(*) FROM fin_target_realization;
