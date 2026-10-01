SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27c_preview_ph_historical_ledger_reconciliation.sql
-- Tujuan :
-- Menampilkan dampak rekonsiliasi ledger PH historis TANPA
-- mengubah data apa pun. Jalankan dan review hasilnya sebelum
-- menjalankan 2026-08-27d.
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
SET @expiry_months := COALESCE((
  SELECT NULLIF(ph_expiry_months, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 3);

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_grant;
CREATE TEMPORARY TABLE tmp_ph_reconcile_grant AS
SELECT
  ad.id AS daily_id,
  ad.employee_id,
  ad.attendance_date,
  @grant_qty AS qty_days,
  CASE
    WHEN COALESCE(pe.expiry_months_override, @expiry_months) > 0
      THEN DATE_ADD(ad.attendance_date, INTERVAL COALESCE(pe.expiry_months_override, @expiry_months) MONTH)
    ELSE NULL
  END AS expired_at
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
LEFT JOIN att_employee_ph_ledger existing_grant
  ON existing_grant.employee_id = ad.employee_id
 AND existing_grant.tx_type = 'GRANT'
 AND existing_grant.ref_table = 'att_daily'
 AND existing_grant.ref_id = ad.id
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND ad.checkin_at IS NOT NULL
  AND DATE(ad.checkin_at) = ad.attendance_date
  AND (ad.checkout_at IS NULL OR (
    ad.checkout_at >= ad.checkin_at
    AND ad.checkout_at <= DATE_ADD(
      CASE
        WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
          OR scheduled_shift.end_time <= scheduled_shift.start_time
          THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
        ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
      END,
      INTERVAL @checkout_close_minutes MINUTE
    )
  ))
  AND (@grant_requires_checkout = 0 OR ad.checkout_at IS NOT NULL)
  AND existing_grant.id IS NULL;

-- Grant AUTO lama dari shift PH dapat dikoreksi menjadi USE bila belum ada
-- penggunaan PH lain dan tidak memiliki child EXPIRE. Kondisi lain sengaja
-- tidak disentuh agar dapat direview manual.
DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_reclassify;
CREATE TEMPORARY TABLE tmp_ph_reconcile_reclassify AS
SELECT
  wrong_grant.id AS ledger_id,
  wrong_grant.employee_id,
  ad.id AS daily_id,
  wrong_grant.tx_date,
  wrong_grant.qty_days,
  wrong_grant.notes AS original_notes
FROM att_employee_ph_ledger wrong_grant
JOIN att_daily ad
  ON wrong_grant.ref_table = 'att_daily'
 AND wrong_grant.ref_id = ad.id
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = wrong_grant.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = ad.id
LEFT JOIN att_employee_ph_ledger expire_child
  ON expire_child.tx_type = 'EXPIRE'
 AND expire_child.ref_table = 'att_employee_ph_ledger'
 AND expire_child.ref_id = wrong_grant.id
WHERE wrong_grant.tx_type = 'GRANT'
  AND UPPER(COALESCE(wrong_grant.entry_mode, '')) = 'AUTO'
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND existing_use.id IS NULL
  AND expire_child.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_manual_review;
CREATE TEMPORARY TABLE tmp_ph_reconcile_manual_review AS
SELECT
  wrong_grant.id AS ledger_id,
  wrong_grant.employee_id,
  ad.id AS daily_id,
  wrong_grant.tx_date,
  CASE
    WHEN existing_use.id IS NOT NULL AND expire_child.id IS NOT NULL THEN 'Sudah ada USE dan child EXPIRE'
    WHEN existing_use.id IS NOT NULL THEN 'Sudah ada USE untuk presensi yang sama'
    WHEN expire_child.id IS NOT NULL THEN 'Grant salah sudah memiliki child EXPIRE'
    ELSE 'Perlu review manual'
  END AS review_reason
FROM att_employee_ph_ledger wrong_grant
JOIN att_daily ad
  ON wrong_grant.ref_table = 'att_daily'
 AND wrong_grant.ref_id = ad.id
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = wrong_grant.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = ad.id
LEFT JOIN att_employee_ph_ledger expire_child
  ON expire_child.tx_type = 'EXPIRE'
 AND expire_child.ref_table = 'att_employee_ph_ledger'
 AND expire_child.ref_id = wrong_grant.id
WHERE wrong_grant.tx_type = 'GRANT'
  AND UPPER(COALESCE(wrong_grant.entry_mode, '')) = 'AUTO'
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND (existing_use.id IS NOT NULL OR expire_child.id IS NOT NULL);

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_use;
CREATE TEMPORARY TABLE tmp_ph_reconcile_use AS
SELECT
  ad.id AS daily_id,
  ad.employee_id,
  ad.attendance_date,
  1.00 AS qty_days
FROM att_daily ad
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = ad.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = ad.id
LEFT JOIN tmp_ph_reconcile_reclassify reclassify
  ON reclassify.daily_id = ad.id
WHERE UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND existing_use.id IS NULL
  AND reclassify.daily_id IS NULL;

-- Ringkasan dampak ledger setelah rekonsiliasi. Nilai negatif berarti dahulu
-- ada penggunaan PH melebihi hak yang berhasil dibuktikan oleh data absensi.
SELECT
  e.employee_code,
  e.employee_name,
  COALESCE(l.grant_saat_ini, 0) AS grant_saat_ini,
  COALESCE(g.grant_akan_ditambah, 0) AS grant_akan_ditambah,
  COALESCE(r.grant_salah_akan_diubah_ke_use, 0) AS grant_salah_akan_diubah_ke_use,
  COALESCE(l.use_saat_ini, 0) AS use_saat_ini,
  COALESCE(u.use_akan_ditambah, 0) AS use_akan_ditambah,
  COALESCE(l.expire_saat_ini, 0) AS expire_saat_ini,
  COALESCE(l.adjust_saat_ini, 0) AS adjust_saat_ini,
  ROUND(
    COALESCE(l.grant_saat_ini, 0)
    - COALESCE(r.qty_days, 0)
    + COALESCE(g.qty_days, 0)
    + COALESCE(l.adjust_saat_ini, 0)
    - COALESCE(l.use_saat_ini, 0)
    - COALESCE(r.qty_days, 0)
    - COALESCE(u.qty_days, 0)
    - COALESCE(l.expire_saat_ini, 0),
    2
  ) AS estimasi_saldo_setelah_rekonsiliasi
FROM org_employee e
LEFT JOIN (
  SELECT
    employee_id,
    SUM(CASE WHEN tx_type = 'GRANT' THEN qty_days ELSE 0 END) AS grant_saat_ini,
    SUM(CASE WHEN tx_type = 'USE' THEN qty_days ELSE 0 END) AS use_saat_ini,
    SUM(CASE WHEN tx_type = 'EXPIRE' THEN qty_days ELSE 0 END) AS expire_saat_ini,
    SUM(CASE WHEN tx_type = 'ADJUST' THEN qty_days ELSE 0 END) AS adjust_saat_ini
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) l ON l.employee_id = e.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS grant_akan_ditambah, SUM(qty_days) AS qty_days
  FROM tmp_ph_reconcile_grant
  GROUP BY employee_id
) g ON g.employee_id = e.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS grant_salah_akan_diubah_ke_use, SUM(qty_days) AS qty_days
  FROM tmp_ph_reconcile_reclassify
  GROUP BY employee_id
) r ON r.employee_id = e.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS use_akan_ditambah, SUM(qty_days) AS qty_days
  FROM tmp_ph_reconcile_use
  GROUP BY employee_id
) u ON u.employee_id = e.id
WHERE g.employee_id IS NOT NULL
   OR r.employee_id IS NOT NULL
   OR u.employee_id IS NOT NULL
ORDER BY e.employee_name;

-- Rincian yang akan ditulis oleh script apply.
SELECT 'GRANT akan ditambah' AS action_name, g.attendance_date, e.employee_code, e.employee_name, g.qty_days
FROM tmp_ph_reconcile_grant g
JOIN org_employee e ON e.id = g.employee_id
UNION ALL
SELECT 'GRANT salah diubah menjadi USE', r.tx_date, e.employee_code, e.employee_name, r.qty_days
FROM tmp_ph_reconcile_reclassify r
JOIN org_employee e ON e.id = r.employee_id
UNION ALL
SELECT 'USE akan ditambah', u.attendance_date, e.employee_code, e.employee_name, u.qty_days
FROM tmp_ph_reconcile_use u
JOIN org_employee e ON e.id = u.employee_id
ORDER BY attendance_date, employee_name, action_name;

SELECT
  e.employee_code,
  e.employee_name,
  m.tx_date,
  m.review_reason,
  m.ledger_id
FROM tmp_ph_reconcile_manual_review m
JOIN org_employee e ON e.id = m.employee_id
ORDER BY m.tx_date, e.employee_name;
