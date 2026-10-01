SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27a_ph_public_holiday_schedule_guard_foundation.sql
-- Tujuan :
-- 1) Mengunci aturan grant PH ke hari libur nasional + shift reguler
-- 2) Menyediakan audit override Superadmin untuk jadwal > batas bulanan
-- 3) Menjamin satu pegawai hanya memiliki satu jadwal per tanggal
-- 4) Menjamin mutasi otomatis PH tidak tercatat dua kali untuk referensi sama
--
-- Aman dijalankan berulang.
-- Catatan:
-- - Tidak membuat/menutup saldo PH lama.
-- - Bila ada duplikasi data lama, unique index dilewati dan ditampilkan
--   sebagai laporan. Periksa dulu memakai SQL audit 2026-08-27b.
-- ============================================================

CREATE TABLE IF NOT EXISTS att_schedule_monthly_override (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  month_start DATE NOT NULL,
  base_limit_days INT UNSIGNED NOT NULL,
  approved_limit_days INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_schedule_monthly_override_employee_month (employee_id, month_start),
  KEY idx_att_schedule_monthly_override_month (month_start, employee_id),
  CONSTRAINT fk_att_schedule_monthly_override_employee
    FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_att_schedule_monthly_override_approved_by
    FOREIGN KEY (approved_by) REFERENCES auth_user(id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Persetujuan Superadmin bila jadwal pegawai melampaui batas hari kerja bulanan';

SET @schema_name := DATABASE();

-- Tambahkan unique jadwal hanya jika server tidak memiliki duplikasi lama.
SET @has_schedule_unique := (
  SELECT COUNT(*)
  FROM (
    SELECT index_name
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'att_shift_schedule'
      AND non_unique = 0
    GROUP BY index_name
    HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') = 'employee_id,schedule_date'
  ) schedule_unique_index
);
SET @schedule_duplicate_count := (
  SELECT COUNT(*)
  FROM (
    SELECT employee_id, schedule_date
    FROM att_shift_schedule
    GROUP BY employee_id, schedule_date
    HAVING COUNT(*) > 1
  ) schedule_duplicates
);
SET @sql := IF(
  @has_schedule_unique = 0 AND @schedule_duplicate_count = 0,
  'ALTER TABLE att_shift_schedule ADD UNIQUE KEY uk_att_shift_schedule_employee_date (employee_id, schedule_date)',
  'SELECT ''Unique jadwal sudah ada atau dilewati karena ada duplikasi lama; lihat laporan di bawah.'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mutasi otomatis menggunakan referensi att_daily. Index ini mencegah reload
-- halaman atau retry request menciptakan GRANT/USE dua kali.
SET @has_ledger_unique := (
  SELECT COUNT(*)
  FROM (
    SELECT index_name
    FROM information_schema.statistics
    WHERE table_schema = @schema_name
      AND table_name = 'att_employee_ph_ledger'
      AND non_unique = 0
    GROUP BY index_name
    HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') = 'employee_id,tx_type,ref_table,ref_id'
  ) ledger_unique_index
);
SET @ledger_duplicate_count := (
  SELECT COUNT(*)
  FROM (
    SELECT employee_id, tx_type, ref_table, ref_id
    FROM att_employee_ph_ledger
    WHERE ref_table IS NOT NULL
      AND ref_id IS NOT NULL
    GROUP BY employee_id, tx_type, ref_table, ref_id
    HAVING COUNT(*) > 1
  ) ledger_duplicates
);
SET @sql := IF(
  @has_ledger_unique = 0 AND @ledger_duplicate_count = 0,
  'ALTER TABLE att_employee_ph_ledger ADD UNIQUE KEY uk_att_employee_ph_ledger_auto_ref (employee_id, tx_type, ref_table, ref_id)',
  'SELECT ''Unique ledger PH sudah ada atau dilewati karena ada duplikasi lama; lihat laporan di bawah.'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

START TRANSACTION;

-- Paksa konfigurasi aktif ke kebijakan PH yang disepakati.
UPDATE att_attendance_policy
SET ph_attendance_mode = 'AUTO_PRESENT',
    ph_auto_presence_on_open = 1,
    ph_requires_clock_in_out = 0,
    ph_grant_mode = 'HOLIDAY_ONLY',
    ph_grant_holiday_type = 'NATIONAL',
    ph_grant_qty_per_day = CASE
      WHEN COALESCE(ph_grant_qty_per_day, 0) <= 0 THEN 1
      ELSE ph_grant_qty_per_day
    END,
    ph_expiry_months = CASE
      WHEN COALESCE(ph_expiry_months, 0) <= 0 THEN 3
      ELSE ph_expiry_months
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE is_active = 1;

COMMIT;

SELECT
  'schedule_unique_exists' AS check_key,
  COUNT(*) AS total
FROM (
  SELECT index_name
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'att_shift_schedule'
    AND non_unique = 0
  GROUP BY index_name
  HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') = 'employee_id,schedule_date'
) x
UNION ALL
SELECT
  'schedule_duplicate_groups',
  COUNT(*)
FROM (
  SELECT employee_id, schedule_date
  FROM att_shift_schedule
  GROUP BY employee_id, schedule_date
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT
  'ledger_unique_exists',
  COUNT(*)
FROM (
  SELECT index_name
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'att_employee_ph_ledger'
    AND non_unique = 0
  GROUP BY index_name
  HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') = 'employee_id,tx_type,ref_table,ref_id'
) x
UNION ALL
SELECT
  'ledger_duplicate_groups',
  COUNT(*)
FROM (
  SELECT employee_id, tx_type, ref_table, ref_id
  FROM att_employee_ph_ledger
  WHERE ref_table IS NOT NULL
    AND ref_id IS NOT NULL
  GROUP BY employee_id, tx_type, ref_table, ref_id
  HAVING COUNT(*) > 1
) x
UNION ALL
SELECT
  'active_policy_normalized',
  COUNT(*)
FROM att_attendance_policy
WHERE is_active = 1
  AND ph_grant_mode = 'HOLIDAY_ONLY'
  AND ph_grant_holiday_type = 'NATIONAL'
  AND ph_attendance_mode = 'AUTO_PRESENT';
