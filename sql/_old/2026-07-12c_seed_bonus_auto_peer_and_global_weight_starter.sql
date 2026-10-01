SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-12c_seed_bonus_auto_peer_and_global_weight_starter.sql
-- Tujuan :
-- 1) Menambahkan penalti otomatis PEER agar bintang < 5 langsung
--    bisa menjadi pengurang poin setelah dimoderasi
-- 2) Menyiapkan bobot awal bonus global yang rapi untuk crew operasional
--    BAR dan KITCHEN
-- 3) Menjadi titik awal yang mudah diedit ulang dari UI
--
-- Catatan:
-- - Script ini TIDAK menghapus bobot lama yang sudah ada
-- - Script ini hanya update / insert data default yang belum ada
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
VALUES (
  'PEER_LOW_STAR',
  'Peer review di bawah 5 bintang',
  'PERFORMANCE',
  'FIXED_POINT',
  1.00,
  0.00,
  'PERSONAL',
  0,
  'AUTO',
  'PEER',
  NULL,
  'DAILY',
  0,
  0,
  1,
  'Otomatis dibuat dari peer review yang sudah dimoderasi. Poin aktual mengikuti selisih 5 - rata-rata bintang.',
  265
)
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
  verification_cycle = VALUES(verification_cycle),
  approval_required = VALUES(approval_required),
  requires_evidence = VALUES(requires_evidence),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  sort_order = VALUES(sort_order),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- Bobot awal per divisi
-- ------------------------------------------------------------
UPDATE pay_bonus_weight_rule w
JOIN org_division d ON d.id = w.scope_id
SET
  w.point_weight = CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE w.point_weight
  END,
  w.pool_weight = CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE w.pool_weight
  END,
  w.notes = 'Seed awal bonus global per divisi 2026-07-12',
  w.is_active = 1,
  w.updated_at = CURRENT_TIMESTAMP
WHERE w.rule_id IS NULL
  AND w.target_frequency = 'DAILY'
  AND w.weight_scope = 'DIVISION'
  AND UPPER(TRIM(COALESCE(d.division_name, ''))) IN ('BAR', 'KITCHEN');

INSERT INTO pay_bonus_weight_rule (
  rule_id, target_frequency, weight_scope, scope_id,
  point_weight, pool_weight, is_active, notes, created_at, updated_at
)
SELECT
  NULL,
  'DAILY',
  'DIVISION',
  d.id,
  CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE 1.0000
  END,
  CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE 1.0000
  END,
  1,
  'Seed awal bonus global per divisi 2026-07-12',
  NOW(),
  NOW()
FROM org_division d
WHERE UPPER(TRIM(COALESCE(d.division_name, ''))) IN ('BAR', 'KITCHEN')
  AND NOT EXISTS (
    SELECT 1
    FROM pay_bonus_weight_rule w
    WHERE w.rule_id IS NULL
      AND w.target_frequency = 'DAILY'
      AND w.weight_scope = 'DIVISION'
      AND w.scope_id = d.id
  );

-- ------------------------------------------------------------
-- Bobot awal per jabatan
-- ------------------------------------------------------------
UPDATE pay_bonus_weight_rule w
JOIN org_position p ON p.id = w.scope_id
SET
  w.point_weight = CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('HEADBAR', 'CHEF DE PARTIE') THEN 1.2000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('DEMI CHEF DE PARTIE', 'ASISTEN HEADBAR', 'SENIOR COOK') THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('BARISTA', 'COOK') THEN 1.0000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'COOK HELPER' THEN 0.9500
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'PARTTIME BARISTA' THEN 0.9000
    ELSE w.point_weight
  END,
  w.pool_weight = CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('HEADBAR', 'CHEF DE PARTIE') THEN 1.2000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('DEMI CHEF DE PARTIE', 'ASISTEN HEADBAR', 'SENIOR COOK') THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('BARISTA', 'COOK') THEN 1.0000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'COOK HELPER' THEN 0.9500
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'PARTTIME BARISTA' THEN 0.9000
    ELSE w.pool_weight
  END,
  w.notes = 'Seed awal bonus global per jabatan 2026-07-12',
  w.is_active = 1,
  w.updated_at = CURRENT_TIMESTAMP
WHERE w.rule_id IS NULL
  AND w.target_frequency = 'DAILY'
  AND w.weight_scope = 'POSITION'
  AND UPPER(TRIM(COALESCE(p.position_name, ''))) IN (
    'HEADBAR', 'CHEF DE PARTIE', 'DEMI CHEF DE PARTIE',
    'ASISTEN HEADBAR', 'SENIOR COOK', 'BARISTA', 'COOK',
    'COOK HELPER', 'PARTTIME BARISTA'
  );

INSERT INTO pay_bonus_weight_rule (
  rule_id, target_frequency, weight_scope, scope_id,
  point_weight, pool_weight, is_active, notes, created_at, updated_at
)
SELECT
  NULL,
  'DAILY',
  'POSITION',
  p.id,
  CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('HEADBAR', 'CHEF DE PARTIE') THEN 1.2000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('DEMI CHEF DE PARTIE', 'ASISTEN HEADBAR', 'SENIOR COOK') THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('BARISTA', 'COOK') THEN 1.0000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'COOK HELPER' THEN 0.9500
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'PARTTIME BARISTA' THEN 0.9000
    ELSE 1.0000
  END,
  CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('HEADBAR', 'CHEF DE PARTIE') THEN 1.2000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('DEMI CHEF DE PARTIE', 'ASISTEN HEADBAR', 'SENIOR COOK') THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('BARISTA', 'COOK') THEN 1.0000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'COOK HELPER' THEN 0.9500
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) = 'PARTTIME BARISTA' THEN 0.9000
    ELSE 1.0000
  END,
  1,
  'Seed awal bonus global per jabatan 2026-07-12',
  NOW(),
  NOW()
FROM org_position p
WHERE UPPER(TRIM(COALESCE(p.position_name, ''))) IN (
  'HEADBAR', 'CHEF DE PARTIE', 'DEMI CHEF DE PARTIE',
  'ASISTEN HEADBAR', 'SENIOR COOK', 'BARISTA', 'COOK',
  'COOK HELPER', 'PARTTIME BARISTA'
)
  AND NOT EXISTS (
    SELECT 1
    FROM pay_bonus_weight_rule w
    WHERE w.rule_id IS NULL
      AND w.target_frequency = 'DAILY'
      AND w.weight_scope = 'POSITION'
      AND w.scope_id = p.id
  );

-- ------------------------------------------------------------
-- Bobot awal per shift
-- ------------------------------------------------------------
UPDATE pay_bonus_weight_rule w
JOIN att_shift s ON s.id = w.scope_id
SET
  w.point_weight = CASE
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('FB', 'FK', 'MDBF', 'MDKF', 'EB', 'EK') THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) = 'PH' THEN 0.9500
    ELSE 1.0000
  END,
  w.pool_weight = CASE
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('FB', 'FK', 'MDBF', 'MDKF', 'EB', 'EK') THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) = 'PH' THEN 0.9500
    ELSE 1.0000
  END,
  w.notes = 'Seed awal bonus global per shift 2026-07-12',
  w.is_active = 1,
  w.updated_at = CURRENT_TIMESTAMP
WHERE w.rule_id IS NULL
  AND w.target_frequency = 'DAILY'
  AND w.weight_scope = 'SHIFT';

INSERT INTO pay_bonus_weight_rule (
  rule_id, target_frequency, weight_scope, scope_id,
  point_weight, pool_weight, is_active, notes, created_at, updated_at
)
SELECT
  NULL,
  'DAILY',
  'SHIFT',
  s.id,
  CASE
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('FB', 'FK', 'MDBF', 'MDKF', 'EB', 'EK') THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) = 'PH' THEN 0.9500
    ELSE 1.0000
  END,
  CASE
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) IN ('FB', 'FK', 'MDBF', 'MDKF', 'EB', 'EK') THEN 1.0500
    WHEN UPPER(TRIM(COALESCE(s.shift_code, ''))) = 'PH' THEN 0.9500
    ELSE 1.0000
  END,
  1,
  'Seed awal bonus global per shift 2026-07-12',
  NOW(),
  NOW()
FROM att_shift s
WHERE NOT EXISTS (
  SELECT 1
  FROM pay_bonus_weight_rule w
  WHERE w.rule_id IS NULL
    AND w.target_frequency = 'DAILY'
    AND w.weight_scope = 'SHIFT'
    AND w.scope_id = s.id
);

-- ------------------------------------------------------------
-- Bobot awal per pegawai operasional
-- ------------------------------------------------------------
UPDATE pay_bonus_weight_rule w
JOIN org_employee e ON e.id = w.scope_id
JOIN org_division d ON d.id = e.division_id
SET
  w.point_weight = 1.0000,
  w.pool_weight = 1.0000,
  w.notes = CONCAT('Seed awal bonus global pegawai ', COALESCE(e.employee_name, ''), ' 2026-07-12'),
  w.is_active = 1,
  w.updated_at = CURRENT_TIMESTAMP
WHERE w.rule_id IS NULL
  AND w.target_frequency = 'DAILY'
  AND w.weight_scope = 'EMPLOYEE'
  AND COALESCE(e.is_active, 1) = 1
  AND UPPER(TRIM(COALESCE(d.division_name, ''))) IN ('BAR', 'KITCHEN');

INSERT INTO pay_bonus_weight_rule (
  rule_id, target_frequency, weight_scope, scope_id,
  point_weight, pool_weight, is_active, notes, created_at, updated_at
)
SELECT
  NULL,
  'DAILY',
  'EMPLOYEE',
  e.id,
  1.0000,
  1.0000,
  1,
  CONCAT('Seed awal bonus global pegawai ', COALESCE(e.employee_name, ''), ' 2026-07-12'),
  NOW(),
  NOW()
FROM org_employee e
JOIN org_division d ON d.id = e.division_id
WHERE COALESCE(e.is_active, 1) = 1
  AND UPPER(TRIM(COALESCE(d.division_name, ''))) IN ('BAR', 'KITCHEN')
  AND NOT EXISTS (
    SELECT 1
    FROM pay_bonus_weight_rule w
    WHERE w.rule_id IS NULL
      AND w.target_frequency = 'DAILY'
      AND w.weight_scope = 'EMPLOYEE'
      AND w.scope_id = e.id
  );

COMMIT;

SELECT penalty_code, penalty_name, behavior_mode, auto_source, is_active
FROM pay_bonus_penalty_type
WHERE penalty_code = 'PEER_LOW_STAR'
UNION ALL
SELECT
  CONCAT(weight_scope, ':', scope_id) AS penalty_code,
  CONCAT('freq=', target_frequency, ' weight=', point_weight) AS penalty_name,
  NULL AS behavior_mode,
  NULL AS auto_source,
  is_active
FROM pay_bonus_weight_rule
WHERE rule_id IS NULL
  AND target_frequency = 'DAILY'
  AND weight_scope IN ('DIVISION', 'POSITION', 'SHIFT', 'EMPLOYEE')
ORDER BY 1;
