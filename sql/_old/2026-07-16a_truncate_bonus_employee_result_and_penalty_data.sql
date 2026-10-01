SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-16a_truncate_bonus_employee_result_and_penalty_data.sql
-- Tujuan :
-- 1) Mengosongkan data hasil bonus yang sudah diterima / tergenerate
--    untuk pegawai
-- 2) Mengosongkan kejadian penalti bonus pegawai
-- 3) Menjaga master kebijakan bonus, bobot global, target keuangan,
--    dan master jenis penalti tetap aman
--
-- Yang dibersihkan:
-- - pay_bonus_pool_daily
-- - pay_bonus_pool_shift
-- - pay_bonus_employee_daily
-- - pay_bonus_pool_time_slice
-- - pay_bonus_employee_time_slice
-- - pay_bonus_monthly_summary
-- - pay_bonus_penalty_event
-- - pay_bonus_manual_adjustment
--
-- Yang TIDAK dibersihkan:
-- - pay_bonus_config
-- - pay_bonus_rule
-- - pay_bonus_weight_rule
-- - pay_bonus_penalty_type
-- - fin_target_plan dan hasil target
-- - pay_bonus_service_metric_daily
-- - perf_peer_feedback
--
-- Catatan:
-- - Script ini memakai DELETE, bukan TRUNCATE, agar aman terhadap FK
-- - Setelah DELETE, AUTO_INCREMENT di-reset ke 1
-- - Jika ingin ikut menghapus peer review / metric layanan, gunakan blok
--   opsional yang disediakan di bagian bawah
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- Snapshot jumlah sebelum dibersihkan
-- ------------------------------------------------------------
SELECT 'before.pay_bonus_employee_time_slice' AS metric, COUNT(*) AS total_rows FROM pay_bonus_employee_time_slice
UNION ALL
SELECT 'before.pay_bonus_pool_time_slice', COUNT(*) FROM pay_bonus_pool_time_slice
UNION ALL
SELECT 'before.pay_bonus_employee_daily', COUNT(*) FROM pay_bonus_employee_daily
UNION ALL
SELECT 'before.pay_bonus_pool_shift', COUNT(*) FROM pay_bonus_pool_shift
UNION ALL
SELECT 'before.pay_bonus_pool_daily', COUNT(*) FROM pay_bonus_pool_daily
UNION ALL
SELECT 'before.pay_bonus_monthly_summary', COUNT(*) FROM pay_bonus_monthly_summary
UNION ALL
SELECT 'before.pay_bonus_penalty_event', COUNT(*) FROM pay_bonus_penalty_event
UNION ALL
SELECT 'before.pay_bonus_manual_adjustment', COUNT(*) FROM pay_bonus_manual_adjustment;

-- ------------------------------------------------------------
-- Bersihkan data turunan dulu, lalu header
-- ------------------------------------------------------------
DELETE FROM pay_bonus_employee_time_slice;
DELETE FROM pay_bonus_pool_time_slice;
DELETE FROM pay_bonus_employee_daily;
DELETE FROM pay_bonus_pool_shift;
DELETE FROM pay_bonus_pool_daily;
DELETE FROM pay_bonus_monthly_summary;
DELETE FROM pay_bonus_penalty_event;
DELETE FROM pay_bonus_manual_adjustment;

-- ------------------------------------------------------------
-- Reset AUTO_INCREMENT
-- ------------------------------------------------------------
ALTER TABLE pay_bonus_employee_time_slice AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_pool_time_slice AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_employee_daily AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_pool_shift AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_pool_daily AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_monthly_summary AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_penalty_event AUTO_INCREMENT = 1;
ALTER TABLE pay_bonus_manual_adjustment AUTO_INCREMENT = 1;

COMMIT;

-- ------------------------------------------------------------
-- Verifikasi sesudah dibersihkan
-- ------------------------------------------------------------
SELECT 'after.pay_bonus_employee_time_slice' AS metric, COUNT(*) AS total_rows FROM pay_bonus_employee_time_slice
UNION ALL
SELECT 'after.pay_bonus_pool_time_slice', COUNT(*) FROM pay_bonus_pool_time_slice
UNION ALL
SELECT 'after.pay_bonus_employee_daily', COUNT(*) FROM pay_bonus_employee_daily
UNION ALL
SELECT 'after.pay_bonus_pool_shift', COUNT(*) FROM pay_bonus_pool_shift
UNION ALL
SELECT 'after.pay_bonus_pool_daily', COUNT(*) FROM pay_bonus_pool_daily
UNION ALL
SELECT 'after.pay_bonus_monthly_summary', COUNT(*) FROM pay_bonus_monthly_summary
UNION ALL
SELECT 'after.pay_bonus_penalty_event', COUNT(*) FROM pay_bonus_penalty_event
UNION ALL
SELECT 'after.pay_bonus_manual_adjustment', COUNT(*) FROM pay_bonus_manual_adjustment;

SELECT 'Bonus dan penalti hasil generate/manual pegawai sudah dikosongkan. Master kebijakan tetap aman.' AS next_step;

-- ------------------------------------------------------------
-- OPSIONAL
-- Jika nanti ingin ikut bersihkan sumber penilaian / metric layanan,
-- jalankan manual baris di bawah ini (sengaja di-comment)
-- ------------------------------------------------------------
-- DELETE FROM perf_peer_feedback;
-- ALTER TABLE perf_peer_feedback AUTO_INCREMENT = 1;
--
-- DELETE FROM pay_bonus_service_metric_daily;
-- ALTER TABLE pay_bonus_service_metric_daily AUTO_INCREMENT = 1;
