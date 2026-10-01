SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-20a_inventory_official_cutoff_run_audit.sql
-- Tujuan :
-- 1) Menyimpan jejak setiap percobaan posting cut-off stok resmi
-- 2) Membuat hasil partial/failed terlihat sebelum operator mencoba ulang
-- 3) Tidak mengubah stok, lot, movement, opname, atau opening data lama
--
-- Prasyarat:
-- - Jalankan 2026-08-13a_inventory_active_month_deficit_period_lock_foundation.sql
--   terlebih dahulu karena tabel ini mengacu ke inv_stock_period.
-- ============================================================

CREATE TABLE IF NOT EXISTS inv_stock_cutoff_run (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cutoff_no VARCHAR(80) NOT NULL,
  period_id BIGINT UNSIGNED NOT NULL,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  period_month DATE NOT NULL,
  opening_month DATE NOT NULL,
  status ENUM('RUNNING','POSTED','FAILED','PARTIAL') NOT NULL DEFAULT 'RUNNING',
  attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
  preview_source_rows INT UNSIGNED NOT NULL DEFAULT 0,
  preview_candidate_rows INT UNSIGNED NOT NULL DEFAULT 0,
  preview_zero_rows INT UNSIGNED NOT NULL DEFAULT 0,
  preview_negative_rows INT UNSIGNED NOT NULL DEFAULT 0,
  preview_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  generated_opname_rows INT UNSIGNED NOT NULL DEFAULT 0,
  generated_opening_rows INT UNSIGNED NOT NULL DEFAULT 0,
  generated_monthly_rows INT UNSIGNED NOT NULL DEFAULT 0,
  result_payload LONGTEXT NULL,
  error_message VARCHAR(1000) NULL,
  notes VARCHAR(255) NULL,
  started_by BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_stock_cutoff_run_no (cutoff_no),
  UNIQUE KEY uk_inv_stock_cutoff_run_attempt (period_id, attempt_no),
  KEY idx_inv_stock_cutoff_run_period_status (period_id, status, started_at),
  KEY idx_inv_stock_cutoff_run_domain_month (stock_domain, period_month, status),
  KEY idx_inv_stock_cutoff_run_started_by (started_by),
  CONSTRAINT fk_inv_stock_cutoff_run_period
    FOREIGN KEY (period_id) REFERENCES inv_stock_period(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_stock_cutoff_run_started_by
    FOREIGN KEY (started_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'inv_stock_cutoff_run' AS table_name, COUNT(*) AS total_rows
FROM inv_stock_cutoff_run;
