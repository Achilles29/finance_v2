SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-14c_upsert_bonus_penalty_core_adoption_v2.sql
-- Tujuan :
-- 1) Mengadopsi master penalti bonus bergaya core ke modul finance
-- 2) Mengklasifikasikan penalti menjadi AUTO / MANUAL / SEMI_MANUAL
-- 3) Menandai mana yang bisa otomatis, mana yang diverifikasi berkala
--
-- Prasyarat:
-- - Jalankan 2026-06-14b_extend_bonus_rule_penalty_type_core_alignment.sql dulu
-- ============================================================

START TRANSACTION;

INSERT INTO pay_bonus_penalty_type (
  penalty_code,
  penalty_name,
  category,
  deduction_mode,
  default_points_deducted,
  default_amount_deducted,
  applies_scope,
  is_manual_only,
  behavior_mode,
  auto_source,
  attendance_trigger,
  verification_cycle,
  approval_required,
  requires_evidence,
  is_active,
  notes,
  sort_order
)
VALUES
('LATE_MINOR', 'Terlambat < 15 menit', 'ATTENDANCE', 'FIXED_POINT', 1.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'LATE_MINOR', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 10),
('LATE_MAJOR', 'Terlambat 15-30 menit', 'ATTENDANCE', 'FIXED_POINT', 3.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'LATE_MAJOR', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 20),
('LATE_SEVERE', 'Terlambat > 30 menit', 'ATTENDANCE', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'LATE_SEVERE', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 30),
('EARLY_OUT', 'Pulang cepat tanpa izin', 'ATTENDANCE', 'FIXED_POINT', 3.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'EARLY_OUT', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 40),
('EARLY_OUT_MINOR', 'Pulang cepat minor', 'ATTENDANCE', 'FIXED_POINT', 1.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'EARLY_OUT_MINOR', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 50),
('EARLY_OUT_MODERATE', 'Pulang cepat moderat', 'ATTENDANCE', 'FIXED_POINT', 2.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'EARLY_OUT_MODERATE', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis dari data absensi.', 60),
('EARLY_OUT_TOLERANCE', 'Pulang cepat dalam toleransi', 'ATTENDANCE', 'FIXED_POINT', 1.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'EARLY_OUT_TOLERANCE', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis ringan dari data absensi.', 70),
('ALPHA', 'Tidak hadir terjadwal', 'ATTENDANCE', 'FIXED_POINT', 10.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'ALPHA', 'PER_EVENT', 0, 0, 1, 'Penalti otomatis untuk tidak hadir tanpa kehadiran sah.', 80),
('MANUAL_ATTENDANCE', 'Absen manual / lupa scan', 'ATTENDANCE', 'FIXED_POINT', 2.00, 0.00, 'PERSONAL', 0, 'SEMI_MANUAL', 'ATTENDANCE', 'MANUAL_ATTENDANCE', 'PER_EVENT', 1, 1, 1, 'Perlu verifikasi admin agar tidak dihukum berulang tanpa alasan yang jelas.', 90),
('ABSENT_PH', 'Tidak hadir shift PH', 'ATTENDANCE', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'ABSENT_PH', 'PER_EVENT', 0, 0, 1, 'Untuk pegawai yang dijadwalkan shift PH tetapi tidak hadir.', 100),
('BONUS-PH-TAKEN', 'Ambil PH', 'ATTENDANCE', 'FIXED_POINT', 1.00, 0.00, 'PERSONAL', 0, 'AUTO', 'ATTENDANCE', 'PH_TAKEN', 'PER_EVENT', 1, 0, 1, 'Mengikuti konsep bonus baru: ambil PH dapat mengurangi poin bonus sesuai rule.', 110),
('BONUS-NO-FOLLOW-IG', 'Belum follow IG Namua', 'SOCIAL_MEDIA', 'FIXED_POINT', 0.50, 0.00, 'PERSONAL', 0, 'SEMI_MANUAL', 'SOCIAL_MEDIA', NULL, 'UNTIL_CHANGED', 1, 1, 1, 'Setelah admin verifikasi sudah follow, penalti tidak perlu diinput ulang sampai ada perubahan.', 120),
('BONUS-NO-STORY-TAG', 'Belum share story / tagging IG Namua', 'SOCIAL_MEDIA', 'FIXED_POINT', 0.75, 0.00, 'PERSONAL', 0, 'SEMI_MANUAL', 'SOCIAL_MEDIA', NULL, 'DAILY', 1, 1, 1, 'Cocok untuk kewajiban promosi harian yang diverifikasi admin.', 130),
('UNIFORM', 'Seragam tidak lengkap', 'DISCIPLINE', 'FIXED_POINT', 2.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 0, 1, 1, 'Observasi langsung oleh leader atau admin.', 140),
('SOP_BREACH', 'Melanggar SOP', 'DISCIPLINE', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Pelanggaran prosedur kerja yang perlu catatan jelas.', 150),
('UNAUTHORIZED_LEAVE', 'Meninggalkan area tanpa izin', 'DISCIPLINE', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Butuh bukti atau saksi sebelum disahkan.', 160),
('VIOLATION_MINOR', 'Pelanggaran ringan', 'DISCIPLINE', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Kategori umum untuk pelanggaran kecil.', 170),
('VIOLATION_MAJOR', 'Pelanggaran sedang', 'DISCIPLINE', 'FIXED_POINT', 10.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Kategori umum untuk pelanggaran sedang.', 180),
('VIOLATION_SEVERE', 'Pelanggaran berat', 'DISCIPLINE', 'FIXED_POINT', 20.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Kategori umum untuk pelanggaran berat.', 190),
('TASK_INCOMPLETE', 'Task / checklist tidak selesai', 'PERFORMANCE', 'FIXED_POINT', 2.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'CHECKLIST', NULL, 'PER_EVENT', 0, 1, 1, 'Bisa dipakai untuk checklist buka/tutup yang tidak tuntas.', 200),
('OPENING_CLOSING', 'Gagal prosedur opening / closing', 'PERFORMANCE', 'FIXED_POINT', 3.00, 0.00, 'TEAM', 1, 'MANUAL', 'CHECKLIST', NULL, 'PER_EVENT', 0, 1, 1, 'Sering relevan untuk tim per shift.', 210),
('QUALITY_FAIL', 'Kualitas produk tidak standar', 'PERFORMANCE', 'FIXED_POINT', 5.00, 0.00, 'TEAM', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Untuk kualitas hasil kerja yang tidak sesuai standar.', 220),
('CUSTOMER_COMPLAINT', 'Komplain pelanggan tercatat', 'SERVICE', 'FIXED_POINT', 5.00, 0.00, 'TEAM', 1, 'MANUAL', 'SERVICE', NULL, 'PER_EVENT', 1, 1, 1, 'Gunakan bila komplain tamu sudah tercatat dan tervalidasi.', 230),
('WRONG_ORDER', 'Salah order / salah sajian', 'SERVICE', 'FIXED_POINT', 3.00, 0.00, 'TEAM', 1, 'MANUAL', 'SERVICE', NULL, 'PER_EVENT', 0, 1, 1, 'Cocok untuk error layanan per kejadian.', 240),
('RESPONSE_SLOW', 'Respon / pelayanan lambat', 'SERVICE', 'FIXED_POINT', 2.00, 0.00, 'TEAM', 0, 'AUTO', 'SERVICE', NULL, 'DAILY', 0, 0, 1, 'Bisa otomatis bila metric waktu layanan sudah stabil.', 250),
('BONUS-SERVICE-SLOW', 'Waktu penyajian melewati standar', 'SERVICE', 'FIXED_POINT', 1.00, 0.00, 'TEAM', 0, 'AUTO', 'SERVICE', NULL, 'DAILY', 1, 0, 1, 'Dipakai bila engine waktu saji harian sudah aktif.', 260),
('BONUS-KITCHEN-DIRTY', 'Area kitchen masih kotor saat audit', 'HYGIENE', 'FIXED_POINT', 2.00, 0.00, 'TEAM', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Contoh penalti tim untuk shift terakhir bila area kitchen belum bersih.', 270),
('BONUS-BAR-DIRTY', 'Area bar masih kotor saat audit', 'HYGIENE', 'FIXED_POINT', 1.50, 0.00, 'TEAM', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Contoh penalti tim untuk shift terakhir area bar.', 280),
('BREAKAGE_MINOR', 'Kerusakan barang < Rp 100rb', 'PROPERTY', 'FIXED_POINT', 5.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Kerusakan ringan yang tetap perlu dicatat.', 290),
('BREAKAGE_MAJOR', 'Kerusakan barang >= Rp 100rb', 'PROPERTY', 'VARIABLE', 15.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Gunakan mode variabel bila dampaknya perlu diputuskan case by case.', 300),
('WASTAGE', 'Pemborosan bahan', 'PROPERTY', 'FIXED_POINT', 3.00, 0.00, 'TEAM', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Bisa dipakai untuk spoil, waste, atau salah handling bahan.', 310),
('BONUS-BROKEN-PROPERTY', 'Merusakkan alat / properti kerja', 'PROPERTY', 'FIXED_POINT', 2.00, 0.00, 'PERSONAL', 1, 'MANUAL', 'AUDIT', NULL, 'PER_EVENT', 1, 1, 1, 'Turunan sederhana dari konsep property penalty di core.', 320),
('BONUS-TEAM-COMPLAINT', 'Keluhan tamu terkait performa tim', 'SERVICE', 'FIXED_POINT', 1.50, 0.00, 'TEAM', 1, 'MANUAL', 'SERVICE', NULL, 'PER_EVENT', 1, 1, 1, 'Dipakai untuk kasus komplain tamu yang dinilai berdampak ke tim dalam satu shift.', 330)
ON DUPLICATE KEY UPDATE
  penalty_name = VALUES(penalty_name),
  category = VALUES(category),
  deduction_mode = VALUES(deduction_mode),
  default_points_deducted = VALUES(default_points_deducted),
  default_amount_deducted = VALUES(default_amount_deducted),
  applies_scope = VALUES(applies_scope),
  is_manual_only = VALUES(is_manual_only),
  behavior_mode = VALUES(behavior_mode),
  auto_source = VALUES(auto_source),
  attendance_trigger = VALUES(attendance_trigger),
  verification_cycle = VALUES(verification_cycle),
  approval_required = VALUES(approval_required),
  requires_evidence = VALUES(requires_evidence),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  penalty_code,
  penalty_name,
  category,
  behavior_mode,
  auto_source,
  verification_cycle,
  default_points_deducted,
  approval_required
FROM pay_bonus_penalty_type
WHERE penalty_code IN (
  'LATE_MINOR','LATE_MAJOR','LATE_SEVERE','EARLY_OUT','ALPHA','ABSENT_PH',
  'BONUS-PH-TAKEN','BONUS-NO-FOLLOW-IG','BONUS-NO-STORY-TAG',
  'UNIFORM','SOP_BREACH','TASK_INCOMPLETE','CUSTOMER_COMPLAINT',
  'RESPONSE_SLOW','BONUS-KITCHEN-DIRTY','BREAKAGE_MAJOR'
)
ORDER BY sort_order, penalty_code;
