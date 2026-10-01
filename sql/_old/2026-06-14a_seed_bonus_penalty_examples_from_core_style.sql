SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14a_seed_bonus_penalty_examples_from_core_style.sql
-- Tujuan :
-- 1) Menambahkan contoh master penalti bonus untuk modul bonus baru
-- 2) Penamaan dan struktur diadaptasi dari konsep core payroll bonus
-- 3) Isi disesuaikan dengan kebutuhan operasional Namua saat ini
--
-- Catatan penting:
-- - Master tabel penalti bonus di database core saat ini terdeteksi
--   rusak di engine, sehingga row asli tidak bisa dibaca langsung.
-- - Seed ini dibuat dari:
--   a) struktur penalti core
--   b) alur bisnis yang sedang dipakai di finance
--   c) contoh penalti yang sudah dibahas bersama user
-- ============================================================

START TRANSACTION;

INSERT INTO pay_bonus_penalty_type (
  penalty_code, penalty_name, category, default_points_deducted, default_amount_deducted,
  applies_scope, is_manual_only, approval_required, is_active, notes, sort_order
)
SELECT
  x.penalty_code,
  x.penalty_name,
  x.category,
  x.default_points_deducted,
  x.default_amount_deducted,
  x.applies_scope,
  x.is_manual_only,
  x.approval_required,
  x.is_active,
  x.notes,
  x.sort_order
FROM (
  SELECT 'BONUS-PH-TAKEN' AS penalty_code, 'Ambil PH' AS penalty_name, 'DISCIPLINE' AS category,
         1.00 AS default_points_deducted, 0.00 AS default_amount_deducted,
         'PERSONAL' AS applies_scope, 0 AS is_manual_only, 1 AS approval_required, 1 AS is_active,
         'Mengikuti konsep bonus baru: ambil PH dapat mengurangi poin bonus sesuai rule.' AS notes, 10 AS sort_order
  UNION ALL
  SELECT 'BONUS-NO-FOLLOW-IG', 'Belum follow IG Namua', 'SOCIAL_MEDIA', 0.50, 0.00, 'PERSONAL', 1, 1, 1,
         'Contoh penalti personal untuk disiplin promosi outlet.', 20
  UNION ALL
  SELECT 'BONUS-NO-STORY-TAG', 'Belum share story / tagging IG Namua', 'SOCIAL_MEDIA', 0.75, 0.00, 'PERSONAL', 1, 1, 1,
         'Dipakai jika ada kewajiban promosi harian yang belum dijalankan.', 30
  UNION ALL
  SELECT 'BONUS-KITCHEN-DIRTY', 'Area kitchen masih kotor saat audit', 'HYGIENE', 2.00, 0.00, 'TEAM', 1, 1, 1,
         'Contoh penalti tim untuk shift terakhir bila area kitchen belum bersih.', 40
  UNION ALL
  SELECT 'BONUS-BAR-DIRTY', 'Area bar masih kotor saat audit', 'HYGIENE', 1.50, 0.00, 'TEAM', 1, 1, 1,
         'Contoh penalti tim untuk shift terakhir area bar.', 50
  UNION ALL
  SELECT 'BONUS-SERVICE-SLOW', 'Waktu penyajian melewati standar', 'SERVICE', 1.00, 0.00, 'TEAM', 0, 1, 1,
         'Dipakai untuk kasus layanan lambat yang berdampak ke bonus operasional.', 60
  UNION ALL
  SELECT 'BONUS-UNEXCUSED-LATE', 'Terlambat tanpa konfirmasi', 'DISCIPLINE', 1.00, 0.00, 'PERSONAL', 0, 1, 1,
         'Bisa dipakai manual atau otomatis, tergantung final rule bonus.', 70
  UNION ALL
  SELECT 'BONUS-ABSENT-NO-NOTICE', 'Tidak hadir tanpa kabar', 'DISCIPLINE', 3.00, 0.00, 'PERSONAL', 0, 1, 1,
         'Penalti berat untuk ketidakhadiran yang merusak operasional tim.', 80
  UNION ALL
  SELECT 'BONUS-BROKEN-PROPERTY', 'Merusakkan alat / properti kerja', 'OTHER', 2.00, 0.00, 'PERSONAL', 1, 1, 1,
         'Contoh turunan dari konsep property penalty di core.', 90
  UNION ALL
  SELECT 'BONUS-TEAM-COMPLAINT', 'Keluhan tamu terkait performa tim', 'SERVICE', 1.50, 0.00, 'TEAM', 1, 1, 1,
         'Untuk kasus komplain tamu yang dinilai berdampak ke tim dalam satu shift.', 100
) x
WHERE NOT EXISTS (
  SELECT 1
  FROM pay_bonus_penalty_type p
  WHERE p.penalty_code = x.penalty_code
);

COMMIT;

SELECT penalty_code, penalty_name, category, applies_scope, default_points_deducted, sort_order
FROM pay_bonus_penalty_type
WHERE penalty_code IN (
  'BONUS-PH-TAKEN',
  'BONUS-NO-FOLLOW-IG',
  'BONUS-NO-STORY-TAG',
  'BONUS-KITCHEN-DIRTY',
  'BONUS-BAR-DIRTY',
  'BONUS-SERVICE-SLOW',
  'BONUS-UNEXCUSED-LATE',
  'BONUS-ABSENT-NO-NOTICE',
  'BONUS-BROKEN-PROPERTY',
  'BONUS-TEAM-COMPLAINT'
)
ORDER BY sort_order, penalty_name;
