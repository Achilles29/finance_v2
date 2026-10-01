SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-12b_detach_bonus_weights_from_rule_scope.sql
-- Tujuan :
-- 1) Melepas bobot bonus dari rule/skema spesifik
-- 2) Menjadikan bobot bonus berlaku global untuk semua kebijakan
-- 3) Menonaktifkan duplikasi lama agar pembagian bobot tidak dobel
-- ============================================================

START TRANSACTION;

UPDATE pay_bonus_weight_rule
SET rule_id = NULL,
    updated_at = NOW()
WHERE rule_id IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_bonus_weight_keep;
CREATE TEMPORARY TABLE tmp_bonus_weight_keep AS
SELECT MAX(id) AS keep_id
FROM pay_bonus_weight_rule
GROUP BY
  COALESCE(target_frequency, 'ALL'),
  weight_scope,
  scope_id;

UPDATE pay_bonus_weight_rule w
LEFT JOIN tmp_bonus_weight_keep k
  ON k.keep_id = w.id
SET w.is_active = CASE WHEN k.keep_id IS NOT NULL THEN 1 ELSE 0 END,
    w.updated_at = NOW()
WHERE w.rule_id IS NULL;

COMMIT;

SELECT
  id,
  COALESCE(target_frequency, 'ALL') AS target_frequency,
  weight_scope,
  scope_id,
  point_weight,
  pool_weight,
  is_active
FROM pay_bonus_weight_rule
ORDER BY target_frequency, weight_scope, scope_id, id;
