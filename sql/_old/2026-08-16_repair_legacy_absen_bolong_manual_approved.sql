START TRANSACTION;

-- Bersihkan dua absen bolong legacy dengan absen manual yang langsung terverifikasi.
-- Target:
-- 1) ALFANDITA AURIEL ANA, 2026-06-06, BAR EVENING: tambah CHECKIN 14:00.
-- 2) EKO BUDI LESTARI, 2026-06-04, BAR MORNING: tambah CHECKOUT 17:00.

CREATE TABLE IF NOT EXISTS zz_bak_att_daily_20260816_legacy_absen AS
SELECT *
FROM att_daily
WHERE (employee_id = 15 AND attendance_date = '2026-06-06')
   OR (employee_id = 6 AND attendance_date = '2026-06-04');

CREATE TABLE IF NOT EXISTS zz_bak_att_presence_20260816_legacy_absen AS
SELECT *
FROM att_presence
WHERE (employee_id = 15 AND attendance_date = '2026-06-06')
   OR (employee_id = 6 AND attendance_date = '2026-06-04');

CREATE TABLE IF NOT EXISTS zz_bak_att_pending_request_20260816_legacy_absen AS
SELECT *
FROM att_pending_request
WHERE (employee_id = 15 AND request_date = '2026-06-06')
   OR (employee_id = 6 AND request_date = '2026-06-04');

CREATE TABLE IF NOT EXISTS zz_bak_att_pending_request_approval_20260816_legacy_absen AS
SELECT pa.*
FROM att_pending_request_approval pa
JOIN att_pending_request pr ON pr.id = pa.pending_request_id
WHERE (pr.employee_id = 15 AND pr.request_date = '2026-06-06')
   OR (pr.employee_id = 6 AND pr.request_date = '2026-06-04');

-- Request manual: ALFANDITA missing check-in.
INSERT INTO att_pending_request (
  employee_id, request_date, request_type, requested_checkin_at, requested_checkout_at,
  requested_status, reason, status, approved_by, approved_at, approval_notes, created_at, updated_at
)
SELECT
  15, '2026-06-06', 'MISSING_CHECKIN', '2026-06-06 14:00:00', NULL,
  NULL, 'Cleanup legacy absen bolong - check-in manual', 'APPROVED', NULL, NOW(),
  'Disetujui otomatis untuk cleanup legacy absen bolong', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM att_pending_request
  WHERE employee_id = 15
    AND request_date = '2026-06-06'
    AND request_type = 'MISSING_CHECKIN'
    AND requested_checkin_at = '2026-06-06 14:00:00'
    AND status = 'APPROVED'
);
SET @req_alfandita := COALESCE(
  (SELECT id FROM att_pending_request
   WHERE employee_id = 15 AND request_date = '2026-06-06'
     AND request_type = 'MISSING_CHECKIN'
     AND requested_checkin_at = '2026-06-06 14:00:00'
     AND status = 'APPROVED'
   ORDER BY id DESC LIMIT 1),
  0
);

-- Request manual: EKO missing check-out.
INSERT INTO att_pending_request (
  employee_id, request_date, request_type, requested_checkin_at, requested_checkout_at,
  requested_status, reason, status, approved_by, approved_at, approval_notes, created_at, updated_at
)
SELECT
  6, '2026-06-04', 'MISSING_CHECKOUT', NULL, '2026-06-04 17:00:00',
  NULL, 'Cleanup legacy absen bolong - check-out manual', 'APPROVED', NULL, NOW(),
  'Disetujui otomatis untuk cleanup legacy absen bolong', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM att_pending_request
  WHERE employee_id = 6
    AND request_date = '2026-06-04'
    AND request_type = 'MISSING_CHECKOUT'
    AND requested_checkout_at = '2026-06-04 17:00:00'
    AND status = 'APPROVED'
);
SET @req_eko := COALESCE(
  (SELECT id FROM att_pending_request
   WHERE employee_id = 6 AND request_date = '2026-06-04'
     AND request_type = 'MISSING_CHECKOUT'
     AND requested_checkout_at = '2026-06-04 17:00:00'
     AND status = 'APPROVED'
   ORDER BY id DESC LIMIT 1),
  0
);

-- Simpan jejak approval 3 level mengikuti policy aktif saat ini.
INSERT INTO att_pending_request_approval (
  pending_request_id, approval_level, approver_employee_id, action, notes, acted_at, created_at
)
SELECT @req_alfandita, lvl.approval_level, NULL, 'APPROVED',
       'Cleanup legacy absen bolong', NOW(), NOW()
FROM (
  SELECT 1 AS approval_level UNION ALL SELECT 2 UNION ALL SELECT 3
) lvl
WHERE @req_alfandita > 0
  AND NOT EXISTS (
    SELECT 1 FROM att_pending_request_approval pa
    WHERE pa.pending_request_id = @req_alfandita
      AND pa.approval_level = lvl.approval_level
  );

INSERT INTO att_pending_request_approval (
  pending_request_id, approval_level, approver_employee_id, action, notes, acted_at, created_at
)
SELECT @req_eko, lvl.approval_level, NULL, 'APPROVED',
       'Cleanup legacy absen bolong', NOW(), NOW()
FROM (
  SELECT 1 AS approval_level UNION ALL SELECT 2 UNION ALL SELECT 3
) lvl
WHERE @req_eko > 0
  AND NOT EXISTS (
    SELECT 1 FROM att_pending_request_approval pa
    WHERE pa.pending_request_id = @req_eko
      AND pa.approval_level = lvl.approval_level
  );

-- Presence manual yang hilang.
INSERT INTO att_presence (
  employee_id, shift_id, attendance_date, attendance_time, attendance_at,
  event_type, source_type, location_id, latitude, longitude, photo_path, notes, created_at, updated_at
)
SELECT
  15, 2, '2026-06-06', '14:00:00', '2026-06-06 14:00:00',
  'CHECKIN', 'MANUAL', NULL, NULL, NULL, NULL,
  'Cleanup legacy absen bolong - check-in manual approved', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM att_presence
  WHERE employee_id = 15
    AND attendance_date = '2026-06-06'
    AND event_type = 'CHECKIN'
);

INSERT INTO att_presence (
  employee_id, shift_id, attendance_date, attendance_time, attendance_at,
  event_type, source_type, location_id, latitude, longitude, photo_path, notes, created_at, updated_at
)
SELECT
  6, 4, '2026-06-04', '17:00:00', '2026-06-04 17:00:00',
  'CHECKOUT', 'MANUAL', NULL, NULL, NULL, NULL,
  'Cleanup legacy absen bolong - check-out manual approved', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM att_presence
  WHERE employee_id = 6
    AND attendance_date = '2026-06-04'
    AND event_type = 'CHECKOUT'
);

-- Update rekap harian ALFANDITA: BAR EVENING 14:00-23:00 lengkap.
UPDATE att_daily
SET checkin_at = '2026-06-06 14:00:00',
    checkout_at = '2026-06-06 23:00:00',
    attendance_status = 'PRESENT',
    work_minutes = 540,
    late_minutes = 0,
    early_leave_minutes = 0,
    overtime_minutes = 0,
    overtime_pay = 0.00,
    basic_amount = 46153.85,
    allowance_amount = 7846.15,
    meal_amount = 0.00,
    late_deduction_amount = 0.00,
    alpha_deduction_amount = 0.00,
    gross_amount = 54000.00,
    net_amount = 54000.00,
    daily_salary_amount = 54000.00,
    source_type = 'PENDING_APPROVAL',
    remarks = CONCAT('Cleanup legacy absen bolong; Approved pending request #', @req_alfandita),
    updated_at = NOW()
WHERE employee_id = 15
  AND attendance_date = '2026-06-06';

-- Update rekap harian EKO: BAR MORNING 08:00-17:00, check-in aktual 08:09 tetap dipakai.
UPDATE att_daily
SET checkout_at = '2026-06-04 17:00:00',
    attendance_status = 'LATE',
    work_minutes = 530,
    late_minutes = 9,
    early_leave_minutes = 0,
    overtime_minutes = 0,
    overtime_pay = 0.00,
    basic_amount = 53846.15,
    allowance_amount = 23076.92,
    meal_amount = 8000.00,
    late_deduction_amount = 997.15,
    alpha_deduction_amount = 0.00,
    gross_amount = 84923.08,
    net_amount = 75925.93,
    daily_salary_amount = 75925.93,
    source_type = 'PENDING_APPROVAL',
    remarks = CONCAT('Cleanup legacy absen bolong; Approved pending request #', @req_eko),
    updated_at = NOW()
WHERE employee_id = 6
  AND attendance_date = '2026-06-04';

COMMIT;
