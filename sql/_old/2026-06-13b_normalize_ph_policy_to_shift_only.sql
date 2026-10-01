SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-13b_normalize_ph_policy_to_shift_only.sql
-- Tujuan :
-- 1) Menormalkan policy aktif absensi agar grant PH default memakai
--    SHIFT_ONLY, bukan lagi HOLIDAY_ONLY
-- 2) Menegaskan konsep operasional:
--    - PH = hak libur pengganti karena pegawai masuk di jadwal shift PH
--    - libur nasional/company/special biasa = tidak perlu presensi bila
--      memang tidak dijadwalkan kerja
-- 3) Tetap membiarkan opsi legacy ada di schema, tetapi policy aktif
--    diarahkan ke konsep final yang lebih aman
-- ============================================================

START TRANSACTION;

UPDATE att_attendance_policy
SET
  ph_grant_mode = 'SHIFT_ONLY',
  updated_at = NOW()
WHERE is_active = 1
  AND COALESCE(ph_grant_mode, '') <> 'SHIFT_ONLY';

COMMIT;

SELECT
  id,
  policy_code,
  policy_name,
  ph_attendance_mode,
  ph_grant_mode,
  ph_grant_holiday_type,
  ph_grant_requires_checkout,
  ph_gets_meal_allowance,
  ph_expiry_months
FROM att_attendance_policy
WHERE is_active = 1
ORDER BY id DESC
LIMIT 5;
