SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-14a_bonus_time_overlap_pool_foundation.sql
-- Tujuan :
-- 1) Menambah fondasi pembagian bonus berbasis irisan waktu transaksi
-- 2) Menyimpan audit pool per transaksi / time slice
-- 3) Menyimpan audit jatah pegawai per slice tanpa merusak rekap harian lama
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pay_bonus_pool_time_slice (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  source_order_id BIGINT UNSIGNED NULL,
  source_shift_id BIGINT UNSIGNED NULL,
  slice_started_at DATETIME NOT NULL,
  slice_ended_at DATETIME NULL,
  slice_label VARCHAR(80) NULL,
  gross_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  net_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payout_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  active_employee_count INT NOT NULL DEFAULT 0,
  total_point_weight DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pay_bonus_pool_time_slice_order (pool_id, source_order_id),
  KEY idx_pay_bonus_pool_time_slice_pool (pool_id, slice_started_at),
  KEY idx_pay_bonus_pool_time_slice_shift (source_shift_id, slice_started_at),
  CONSTRAINT fk_pay_bonus_pool_time_slice_pool FOREIGN KEY (pool_id) REFERENCES pay_bonus_pool_daily(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pay_bonus_employee_time_slice (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pool_id BIGINT UNSIGNED NOT NULL,
  pool_time_slice_id BIGINT UNSIGNED NOT NULL,
  employee_daily_id BIGINT UNSIGNED NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  shift_id BIGINT UNSIGNED NULL,
  slice_started_at DATETIME NOT NULL,
  slice_label VARCHAR(80) NULL,
  active_employee_count INT NOT NULL DEFAULT 0,
  division_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  position_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  employee_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  shift_weight DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  raw_point DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  raw_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  attributable_revenue DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pay_bonus_employee_time_slice_emp (employee_id, attendance_date, slice_started_at),
  KEY idx_pay_bonus_employee_time_slice_daily (employee_daily_id),
  KEY idx_pay_bonus_employee_time_slice_pool (pool_id, pool_time_slice_id),
  CONSTRAINT fk_pay_bonus_employee_time_slice_pool FOREIGN KEY (pool_id) REFERENCES pay_bonus_pool_daily(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_employee_time_slice_slice FOREIGN KEY (pool_time_slice_id) REFERENCES pay_bonus_pool_time_slice(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_employee_time_slice_daily FOREIGN KEY (employee_daily_id) REFERENCES pay_bonus_employee_daily(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_bonus_employee_time_slice_employee FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_bonus_employee_time_slice_shift FOREIGN KEY (shift_id) REFERENCES att_shift(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

SELECT 'pay_bonus_pool_time_slice' AS table_name, COUNT(*) AS total_rows FROM pay_bonus_pool_time_slice
UNION ALL
SELECT 'pay_bonus_employee_time_slice', COUNT(*) FROM pay_bonus_employee_time_slice;
