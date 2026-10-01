SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-12a_cleanup_seed_bonus_rules_and_configs.sql
-- Tujuan :
-- 1) Menghapus data seed/demo bonus yang sebelumnya dibuat untuk contoh
-- 2) Mengosongkan tab /payroll/bonus?tab=rules dari contoh sistem
-- 3) Menjaga data target keuangan tetap utuh, hanya bonus seed yang dibersihkan
--
-- Ruang lingkup cleanup:
-- - pay_bonus_weight_rule
-- - pay_bonus_rule
-- - pay_bonus_config
--
-- Aman karena:
-- - rule contoh belum dipakai membentuk pool harian
-- - tidak ada employee_daily / monthly_summary / penalty_event terkait
-- - target keuangan di fin_target_plan tidak disentuh
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_bonus_seed_config_ids;
CREATE TEMPORARY TABLE tmp_bonus_seed_config_ids (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY
);

INSERT INTO tmp_bonus_seed_config_ids (id)
SELECT id
FROM pay_bonus_config
WHERE config_code IN ('BONUS-DEFAULT', 'BONUS-OMZET-3PCT-202607')
   OR notes LIKE '%Seed awal modul bonus 2026-06-13%'
   OR notes LIKE '%Konfigurasi contoh agar tim bonus dan target memakai bahasa yang sama%';

DROP TEMPORARY TABLE IF EXISTS tmp_bonus_seed_rule_ids;
CREATE TEMPORARY TABLE tmp_bonus_seed_rule_ids (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY
);

INSERT INTO tmp_bonus_seed_rule_ids (id)
SELECT id
FROM pay_bonus_rule
WHERE rule_code IN ('BONUS-RULE-3JT-3PCT-202607')
   OR config_id IN (SELECT id FROM tmp_bonus_seed_config_ids)
   OR notes LIKE '%Rule utama contoh%'
   OR rule_name LIKE '%3%%jika omzet >= 3 juta%';

DELETE FROM pay_bonus_weight_rule
WHERE rule_id IN (SELECT id FROM tmp_bonus_seed_rule_ids)
   OR notes LIKE '%Contoh bobot divisi untuk rule bonus 3% omzet.%'
   OR notes LIKE '%Contoh bobot jabatan untuk rule bonus 3% omzet.%';

DELETE FROM pay_bonus_rule
WHERE id IN (SELECT id FROM tmp_bonus_seed_rule_ids);

DELETE FROM pay_bonus_config
WHERE id IN (SELECT id FROM tmp_bonus_seed_config_ids);

COMMIT;

SELECT 'remaining_bonus_config' AS metric, COUNT(*) AS total
FROM pay_bonus_config
UNION ALL
SELECT 'remaining_bonus_rule', COUNT(*)
FROM pay_bonus_rule
UNION ALL
SELECT 'remaining_bonus_weight_rule', COUNT(*)
FROM pay_bonus_weight_rule;
