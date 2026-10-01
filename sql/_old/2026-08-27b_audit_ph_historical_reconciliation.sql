SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27b_audit_ph_historical_reconciliation.sql
-- Tujuan :
-- Membaca anomali PH historis sebelum saldo lama diperbaiki manual.
--
-- Script ini 100% READ ONLY: tidak INSERT, UPDATE, DELETE, atau ALTER.
-- Jalankan setelah 2026-08-27a agar aturan aktif sudah jelas.
-- ============================================================

SET @grant_qty := COALESCE((
  SELECT NULLIF(ph_grant_qty_per_day, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 1.00);
SET @grant_requires_checkout := COALESCE((
  SELECT ph_grant_requires_checkout
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 1);
SET @checkout_close_minutes := COALESCE((
  SELECT checkout_close_minutes_after
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 180);
SET @monthly_limit := COALESCE((
  SELECT default_work_days_per_month
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 26);

-- 0. Konfigurasi yang dipakai audit.
SELECT
  @grant_qty AS jatah_ph_per_hari,
  @grant_requires_checkout AS grant_wajib_checkout,
  @checkout_close_minutes AS batas_checkout_menit,
  @monthly_limit AS batas_jadwal_bulanan;

-- 1. Pegawai yang seharusnya mendapat PH tetapi grant otomatisnya belum ada.
SELECT
  ad.attendance_date AS tanggal_libur_nasional,
  e.employee_code,
  e.employee_name,
  d.division_name,
  scheduled_shift.shift_code AS shift_kerja,
  daily_shift.shift_code AS shift_presensi_final,
  ad.attendance_status,
  ad.source_type,
  ad.checkin_at,
  ad.checkout_at,
  @grant_qty AS seharusnya_grant_hari
FROM att_daily ad
JOIN att_shift_schedule ss
  ON ss.employee_id = ad.employee_id
 AND ss.schedule_date = ad.attendance_date
JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
 AND hc.holiday_type = 'NATIONAL'
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
 AND pe.effective_date <= ad.attendance_date
JOIN org_employee e ON e.id = ad.employee_id
LEFT JOIN org_division d ON d.id = e.division_id
LEFT JOIN att_employee_ph_ledger ledger_grant
  ON ledger_grant.employee_id = ad.employee_id
 AND ledger_grant.tx_type = 'GRANT'
 AND ledger_grant.ref_table = 'att_daily'
 AND ledger_grant.ref_id = ad.id
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND ad.checkin_at IS NOT NULL
  AND DATE(ad.checkin_at) = ad.attendance_date
  AND (
    @grant_requires_checkout = 0
    OR (
      ad.checkout_at IS NOT NULL
      AND ad.checkout_at >= ad.checkin_at
      AND ad.checkout_at <= DATE_ADD(
        CASE
          WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
            OR scheduled_shift.end_time <= scheduled_shift.start_time
            THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
          ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
        END,
        INTERVAL @checkout_close_minutes MINUTE
      )
    )
  )
  AND ledger_grant.id IS NULL
ORDER BY ad.attendance_date, e.employee_name;

-- 2. Shift PH pada presensi final yang sudah dianggap hadir, tetapi belum
-- mengurangi jatah PH. Sumber fakta adalah att_daily.shift_id, bukan jadwal.
SELECT
  ad.attendance_date,
  e.employee_code,
  e.employee_name,
  d.division_name,
  daily_shift.shift_code AS shift_ph,
  ad.attendance_status,
  ad.source_type,
  ad.id AS att_daily_id,
  @grant_qty AS seharusnya_use_hari
FROM att_daily ad
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN org_employee e ON e.id = ad.employee_id
LEFT JOIN org_division d ON d.id = e.division_id
LEFT JOIN att_employee_ph_ledger ledger_use
  ON ledger_use.employee_id = ad.employee_id
 AND ledger_use.tx_type = 'USE'
 AND ledger_use.ref_table = 'att_daily'
 AND ledger_use.ref_id = ad.id
WHERE UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND ledger_use.id IS NULL
ORDER BY ad.attendance_date, e.employee_name;

-- 3. Grant lama yang salah arah: sumber presensi finalnya justru shift PH.
-- Baris ini jangan dihapus langsung; jadikan bahan rekonsiliasi saldo awal.
SELECT
  ledger_grant.id AS ledger_id,
  ledger_grant.tx_date,
  e.employee_code,
  e.employee_name,
  ledger_grant.qty_days,
  ledger_grant.entry_mode,
  ledger_grant.notes,
  ad.id AS att_daily_id,
  daily_shift.shift_code AS shift_sumber
FROM att_employee_ph_ledger ledger_grant
JOIN att_daily ad
  ON ledger_grant.ref_table = 'att_daily'
 AND ledger_grant.ref_id = ad.id
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN org_employee e ON e.id = ledger_grant.employee_id
WHERE ledger_grant.tx_type = 'GRANT'
  AND UPPER(COALESCE(ledger_grant.entry_mode, '')) = 'AUTO'
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
ORDER BY ledger_grant.tx_date, e.employee_name, ledger_grant.id;

-- 4. Presensi hari libur nasional yang tampak hadir tetapi tidak valid untuk
-- grant karena check-in/check-out salah tanggal, tidak lengkap, atau terlalu jauh.
SELECT
  ad.attendance_date,
  e.employee_code,
  e.employee_name,
  scheduled_shift.shift_code AS shift_kerja,
  daily_shift.shift_code AS shift_presensi_final,
  ad.attendance_status,
  ad.checkin_at,
  ad.checkout_at,
  CASE
    WHEN ad.checkin_at IS NULL OR DATE(ad.checkin_at) <> ad.attendance_date THEN 'Check-in tidak berada pada tanggal kerja'
    WHEN @grant_requires_checkout = 1 AND ad.checkout_at IS NULL THEN 'Check-out belum ada'
    WHEN ad.checkout_at < ad.checkin_at THEN 'Check-out lebih awal dari check-in'
    WHEN ad.checkout_at > DATE_ADD(
      CASE
        WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
          OR scheduled_shift.end_time <= scheduled_shift.start_time
          THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
        ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
      END,
      INTERVAL @checkout_close_minutes MINUTE
    ) THEN 'Check-out melewati batas penutupan shift'
    ELSE 'Perlu review manual'
  END AS alasan_review
FROM att_daily ad
JOIN att_shift_schedule ss
  ON ss.employee_id = ad.employee_id
 AND ss.schedule_date = ad.attendance_date
JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
 AND hc.holiday_type = 'NATIONAL'
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
 AND pe.effective_date <= ad.attendance_date
JOIN org_employee e ON e.id = ad.employee_id
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND (
    ad.checkin_at IS NULL
    OR DATE(ad.checkin_at) <> ad.attendance_date
    OR (@grant_requires_checkout = 1 AND ad.checkout_at IS NULL)
    OR (ad.checkout_at IS NOT NULL AND ad.checkin_at IS NOT NULL AND ad.checkout_at < ad.checkin_at)
    OR (
      ad.checkout_at IS NOT NULL
      AND ad.checkout_at > DATE_ADD(
        CASE
          WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
            OR scheduled_shift.end_time <= scheduled_shift.start_time
            THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
          ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
        END,
        INTERVAL @checkout_close_minutes MINUTE
      )
    )
  )
ORDER BY ad.attendance_date, e.employee_name;

-- 5. Jadwal PH yang ditempatkan pada pegawai tanpa hak aktif.
SELECT
  ss.schedule_date,
  e.employee_code,
  e.employee_name,
  ph_shift.shift_code,
  COALESCE(pe.is_eligible, 0) AS is_eligible,
  pe.effective_date
FROM att_shift_schedule ss
JOIN att_shift ph_shift ON ph_shift.id = ss.shift_id
JOIN org_employee e ON e.id = ss.employee_id
LEFT JOIN att_ph_eligibility pe ON pe.employee_id = ss.employee_id
WHERE UPPER(TRIM(COALESCE(ph_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND (
    COALESCE(pe.is_eligible, 0) <> 1
    OR pe.effective_date > ss.schedule_date
  )
ORDER BY ss.schedule_date, e.employee_name;

-- 6. Jadwal dan fakta shift PH yang tidak selaras pada absensi final lama.
-- Ini tidak otomatis diubah; barisnya perlu dicek sebelum rekonsiliasi payroll.
SELECT *
FROM (
  SELECT
    ad.attendance_date,
    e.employee_code,
    e.employee_name,
    COALESCE(schedule_shift.shift_code, '-') AS shift_jadwal,
    daily_shift.shift_code AS shift_presensi_final,
    ad.attendance_status,
    'Presensi final PH, tetapi jadwal bukan PH' AS anomali
  FROM att_daily ad
  JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
  LEFT JOIN att_shift_schedule ss
    ON ss.employee_id = ad.employee_id
   AND ss.schedule_date = ad.attendance_date
  LEFT JOIN att_shift schedule_shift ON schedule_shift.id = ss.shift_id
  JOIN org_employee e ON e.id = ad.employee_id
  WHERE ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
    AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
    AND UPPER(TRIM(COALESCE(schedule_shift.shift_code, ''))) NOT IN ('PH', 'PHB')

  UNION ALL

  SELECT
    ad.attendance_date,
    e.employee_code,
    e.employee_name,
    schedule_shift.shift_code AS shift_jadwal,
    COALESCE(daily_shift.shift_code, '-') AS shift_presensi_final,
    ad.attendance_status,
    'Jadwal PH, tetapi presensi final bukan PH' AS anomali
  FROM att_shift_schedule ss
  JOIN att_shift schedule_shift ON schedule_shift.id = ss.shift_id
  JOIN att_daily ad
    ON ad.employee_id = ss.employee_id
   AND ad.attendance_date = ss.schedule_date
  LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
  JOIN org_employee e ON e.id = ss.employee_id
  WHERE ss.schedule_date < CURDATE()
    AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
    AND UPPER(TRIM(COALESCE(schedule_shift.shift_code, ''))) IN ('PH', 'PHB')
    AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
) ph_shift_mismatch
ORDER BY attendance_date, employee_name, anomali;

-- 7. Pegawai non-Security yang jadwalnya melampaui batas bulan aktif.
SELECT
  DATE_FORMAT(ss.schedule_date, '%Y-%m') AS bulan,
  e.employee_code,
  e.employee_name,
  d.division_name,
  p.position_code,
  COUNT(DISTINCT ss.schedule_date) AS total_jadwal,
  @monthly_limit AS batas_normal
FROM att_shift_schedule ss
JOIN org_employee e ON e.id = ss.employee_id
LEFT JOIN org_division d ON d.id = e.division_id
LEFT JOIN org_position p ON p.id = e.position_id
WHERE UPPER(COALESCE(p.position_code, '')) <> 'SECURITY'
GROUP BY DATE_FORMAT(ss.schedule_date, '%Y-%m'), e.id, e.employee_code, e.employee_name, d.division_name, p.position_code
HAVING COUNT(DISTINCT ss.schedule_date) > @monthly_limit
ORDER BY bulan, total_jadwal DESC, e.employee_name;

-- 8. Rekap per pegawai: bandingkan yang seharusnya terjadi vs ledger nyata.
SELECT
  e.employee_code,
  e.employee_name,
  d.division_name,
  COALESCE(pe.is_eligible, 0) AS eligible,
  COALESCE((
    SELECT COUNT(*) * @grant_qty
    FROM att_daily ad
    JOIN att_shift_schedule ss
      ON ss.employee_id = ad.employee_id
     AND ss.schedule_date = ad.attendance_date
    JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
    LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
    JOIN att_holiday_calendar hc
      ON hc.holiday_date = ad.attendance_date
     AND hc.is_active = 1
     AND hc.holiday_type = 'NATIONAL'
    JOIN att_ph_eligibility expected_pe
      ON expected_pe.employee_id = ad.employee_id
     AND expected_pe.is_eligible = 1
     AND expected_pe.effective_date <= ad.attendance_date
    WHERE ad.employee_id = e.id
       AND ad.attendance_status IN ('PRESENT', 'LATE')
        AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
        AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
       AND expected_pe.is_eligible = 1
       AND expected_pe.effective_date <= ad.attendance_date
      AND ad.checkin_at IS NOT NULL
      AND DATE(ad.checkin_at) = ad.attendance_date
      AND (
        @grant_requires_checkout = 0
        OR (
          ad.checkout_at IS NOT NULL
          AND ad.checkout_at >= ad.checkin_at
          AND ad.checkout_at <= DATE_ADD(
            CASE
              WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
                OR scheduled_shift.end_time <= scheduled_shift.start_time
                THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
              ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
            END,
            INTERVAL @checkout_close_minutes MINUTE
          )
        )
      )
  ), 0) AS seharusnya_grant,
  COALESCE((SELECT SUM(l.qty_days) FROM att_employee_ph_ledger l WHERE l.employee_id = e.id AND l.tx_type = 'GRANT'), 0) AS grant_ledger,
  COALESCE((
    SELECT COUNT(*)
    FROM att_daily ad
    JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
    WHERE ad.employee_id = e.id
      AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
      AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  ), 0) AS seharusnya_use,
  COALESCE((SELECT SUM(l.qty_days) FROM att_employee_ph_ledger l WHERE l.employee_id = e.id AND l.tx_type = 'USE'), 0) AS use_ledger,
  COALESCE((SELECT SUM(l.qty_days) FROM att_employee_ph_ledger l WHERE l.employee_id = e.id AND l.tx_type = 'EXPIRE'), 0) AS expire_ledger,
  COALESCE((SELECT SUM(l.qty_days) FROM att_employee_ph_ledger l WHERE l.employee_id = e.id AND l.tx_type = 'ADJUST'), 0) AS adjust_ledger
FROM org_employee e
LEFT JOIN org_division d ON d.id = e.division_id
LEFT JOIN att_ph_eligibility pe ON pe.employee_id = e.id
WHERE e.is_active = 1
ORDER BY d.division_name, e.employee_name;

-- 9. Ringkasan jumlah temuan agar hasil audit mudah dibandingkan antar server.
SELECT 'Hadir libur nasional valid tetapi belum GRANT' AS temuan, COUNT(*) AS total
FROM att_daily ad
JOIN att_shift_schedule ss
  ON ss.employee_id = ad.employee_id
 AND ss.schedule_date = ad.attendance_date
JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
 AND hc.holiday_type = 'NATIONAL'
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
 AND pe.effective_date <= ad.attendance_date
LEFT JOIN att_employee_ph_ledger ledger_grant
  ON ledger_grant.employee_id = ad.employee_id
 AND ledger_grant.tx_type = 'GRANT'
 AND ledger_grant.ref_table = 'att_daily'
 AND ledger_grant.ref_id = ad.id
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND ad.checkin_at IS NOT NULL
  AND DATE(ad.checkin_at) = ad.attendance_date
  AND (
    @grant_requires_checkout = 0
    OR (
      ad.checkout_at IS NOT NULL
      AND ad.checkout_at >= ad.checkin_at
      AND ad.checkout_at <= DATE_ADD(
        CASE
          WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
            OR scheduled_shift.end_time <= scheduled_shift.start_time
            THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
          ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
        END,
        INTERVAL @checkout_close_minutes MINUTE
      )
    )
  )
  AND ledger_grant.id IS NULL
UNION ALL
SELECT 'Shift PH hadir tetapi belum USE', COUNT(*)
FROM att_daily ad
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger ledger_use
  ON ledger_use.employee_id = ad.employee_id
 AND ledger_use.tx_type = 'USE'
 AND ledger_use.ref_table = 'att_daily'
 AND ledger_use.ref_id = ad.id
WHERE UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND ledger_use.id IS NULL
UNION ALL
SELECT 'GRANT lama berasal dari shift PH', COUNT(*)
FROM att_employee_ph_ledger ledger_grant
JOIN att_daily ad
  ON ledger_grant.ref_table = 'att_daily'
 AND ledger_grant.ref_id = ad.id
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
WHERE ledger_grant.tx_type = 'GRANT'
  AND UPPER(COALESCE(ledger_grant.entry_mode, '')) = 'AUTO'
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
UNION ALL
SELECT 'Presensi libur perlu review manual', COUNT(*)
FROM att_daily ad
JOIN att_shift_schedule ss
  ON ss.employee_id = ad.employee_id
 AND ss.schedule_date = ad.attendance_date
JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
 AND hc.holiday_type = 'NATIONAL'
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
 AND pe.effective_date <= ad.attendance_date
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND (
    ad.checkin_at IS NULL
    OR DATE(ad.checkin_at) <> ad.attendance_date
    OR (@grant_requires_checkout = 1 AND ad.checkout_at IS NULL)
    OR (ad.checkout_at IS NOT NULL AND ad.checkin_at IS NOT NULL AND ad.checkout_at < ad.checkin_at)
    OR (
      ad.checkout_at IS NOT NULL
      AND ad.checkout_at > DATE_ADD(
        CASE
          WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
            OR scheduled_shift.end_time <= scheduled_shift.start_time
            THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
          ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
        END,
        INTERVAL @checkout_close_minutes MINUTE
      )
    )
  )
UNION ALL
SELECT 'Jadwal PH tanpa eligibility aktif', COUNT(*)
FROM att_shift_schedule ss
JOIN att_shift ph_shift ON ph_shift.id = ss.shift_id
LEFT JOIN att_ph_eligibility pe ON pe.employee_id = ss.employee_id
WHERE UPPER(TRIM(COALESCE(ph_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND (COALESCE(pe.is_eligible, 0) <> 1 OR pe.effective_date > ss.schedule_date)
UNION ALL
SELECT 'Jadwal dan presensi final shift PH tidak selaras', COUNT(*)
FROM (
  SELECT ad.id
  FROM att_daily ad
  JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
  LEFT JOIN att_shift_schedule ss
    ON ss.employee_id = ad.employee_id
   AND ss.schedule_date = ad.attendance_date
  LEFT JOIN att_shift schedule_shift ON schedule_shift.id = ss.shift_id
  WHERE ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
    AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
    AND UPPER(TRIM(COALESCE(schedule_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  UNION ALL
  SELECT ad.id
  FROM att_shift_schedule ss
  JOIN att_shift schedule_shift ON schedule_shift.id = ss.shift_id
  JOIN att_daily ad
    ON ad.employee_id = ss.employee_id
   AND ad.attendance_date = ss.schedule_date
  LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
  WHERE ss.schedule_date < CURDATE()
    AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
    AND UPPER(TRIM(COALESCE(schedule_shift.shift_code, ''))) IN ('PH', 'PHB')
    AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
) shift_mismatch
UNION ALL
SELECT 'Kelompok jadwal non-Security di atas batas bulan', COUNT(*)
FROM (
  SELECT ss.employee_id, DATE_FORMAT(ss.schedule_date, '%Y-%m') AS month_key
  FROM att_shift_schedule ss
  JOIN org_employee e ON e.id = ss.employee_id
  LEFT JOIN org_position p ON p.id = e.position_id
  WHERE UPPER(COALESCE(p.position_code, '')) <> 'SECURITY'
  GROUP BY ss.employee_id, DATE_FORMAT(ss.schedule_date, '%Y-%m')
  HAVING COUNT(DISTINCT ss.schedule_date) > @monthly_limit
) over_limit;
