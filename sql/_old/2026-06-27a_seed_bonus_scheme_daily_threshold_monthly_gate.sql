SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-27a_seed_bonus_scheme_daily_threshold_monthly_gate.sql
-- Tujuan :
-- 1) Memberi contoh skema bonus yang dimaksud user:
--    - target harian omzet minimal Rp 3.000.000
--    - target bulanan estimasi keuangan minimal Rp 10.000.000
--    - pool bonus harian = 3% dari omzet bersih harian yang lolos target
-- 2) Menyimpan contoh target keuangan + rule bonus + bobot pegawai
--    dengan pola yang sudah ada di repo finance
-- 3) Menghindari kebingungan implementasi ke depan:
--    - target bulanan hidup di fin_target_plan
--    - target harian aktif bisa ditautkan ke rule bonus
--    - generator pool harian akan membaca target DAILY yang aktif
--      jika field daily_target_plan_id tersedia; jika kosong fallback
--      ke threshold_amount pada pay_bonus_rule
--
-- Catatan penting:
-- - Jalankan 2026-06-27b_extend_bonus_rule_daily_target_link.sql lebih dulu
--   agar field daily_target_plan_id tersedia di pay_bonus_rule.
-- - Script ini sengaja membuat 2 target:
--   a) target harian aktif yang dibaca engine bonus
--   b) target bulanan aktif yang dipakai sebagai gerbang bonus
-- - Rule bonus ditautkan ke target bulanan dan target harian sekaligus.
-- - Bobot divisi/jabatan di bawah adalah contoh ala ctx=employee.
--   Jika nama divisi/jabatan yang dicari tidak ada, insert bobot itu
--   akan otomatis dilewati tanpa error.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- A. Variabel skema contoh
-- ------------------------------------------------------------
SET @scheme_month              := '2026-07';
SET @scheme_start             := STR_TO_DATE(CONCAT(@scheme_month, '-01'), '%Y-%m-%d');
SET @scheme_end               := LAST_DAY(@scheme_start);
SET @daily_reference_date     := @scheme_start;

SET @daily_target_amount      := 3000000.00;
SET @monthly_profit_target    := 10000000.00;
SET @daily_pool_percent       := 3.0000;
SET @minimum_bonus_score      := 100.00;

SET @config_code              := CONCAT('BONUS-OMZET-3PCT-', REPLACE(@scheme_month, '-', ''));
SET @config_name              := CONCAT('Bonus Omzet 3% Gate Bulanan ', @scheme_month);
SET @daily_target_code        := CONCAT('TGT-DAILY-REF-3JT-', DATE_FORMAT(@daily_reference_date, '%Y%m%d'));
SET @monthly_target_code      := CONCAT('TGT-MONTHLY-GATE-ESTPROFIT-', REPLACE(@scheme_month, '-', ''));
SET @rule_code                := CONCAT('BONUS-RULE-3JT-3PCT-', REPLACE(@scheme_month, '-', ''));

SET @daily_target_name        := CONCAT('Template Target Harian Omzet Rp 3 Juta - ', DATE_FORMAT(@daily_reference_date, '%d %b %Y'));
SET @monthly_target_name      := CONCAT('Gerbang Bonus Bulanan Profit Estimasi Rp 10 Juta - ', @scheme_month);
SET @rule_name                := CONCAT('Bonus Harian 3% jika omzet >= 3 juta | Cair jika target bulanan lolos | ', @scheme_month);

-- ------------------------------------------------------------
-- B. Pastikan metric profit estimasi tersedia
-- ------------------------------------------------------------
INSERT INTO fin_metric_catalog (
  metric_code,
  metric_group,
  metric_label,
  metric_unit,
  metric_scope,
  comparator_hint,
  description,
  is_active
)
VALUES
  (
    'ESTIMATED_PROFIT_VALUE',
    'PROFITABILITY',
    'Profit Estimasi',
    'AMOUNT',
    'GLOBAL',
    'MIN',
    'Estimasi profit dari omzet bersih dikurangi HPP live, belanja operasional, estimasi gaji berjalan, dan adjustment stok.',
    1
  ),
  (
    'ESTIMATED_PROFIT_PERCENT',
    'PROFITABILITY',
    'Margin Profit Estimasi %',
    'PERCENT',
    'GLOBAL',
    'MIN',
    'Persentase profit estimasi terhadap omzet bersih periode berjalan.',
    1
  )
ON DUPLICATE KEY UPDATE
  metric_group = VALUES(metric_group),
  metric_label = VALUES(metric_label),
  metric_unit = VALUES(metric_unit),
  metric_scope = VALUES(metric_scope),
  comparator_hint = VALUES(comparator_hint),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- C. Target harian aktif pembaca omzet bonus
-- ------------------------------------------------------------
INSERT INTO fin_target_plan (
  target_code,
  target_name,
  target_scope,
  target_year,
  target_month,
  target_date,
  date_start,
  date_end,
  division_id,
  company_account_id,
  status,
  bonus_gate_mode,
  min_bonus_score,
  bonus_pool_amount,
  bonus_percent_of_profit,
  notes,
  created_at,
  updated_at
)
VALUES (
  @daily_target_code,
  @daily_target_name,
  'DAILY',
  YEAR(@daily_reference_date),
  MONTH(@daily_reference_date),
  @daily_reference_date,
  @daily_reference_date,
  @daily_reference_date,
  NULL,
  NULL,
  'ACTIVE',
  'NONE',
  0.00,
  0.00,
  0.0000,
  'Target harian aktif pembaca omzet bonus. Jika rule bonus menaut ke target ini, engine memakai angka target harian ini sebagai ambang omzet.',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  target_name = VALUES(target_name),
  target_scope = VALUES(target_scope),
  target_year = VALUES(target_year),
  target_month = VALUES(target_month),
  target_date = VALUES(target_date),
  date_start = VALUES(date_start),
  date_end = VALUES(date_end),
  status = VALUES(status),
  bonus_gate_mode = VALUES(bonus_gate_mode),
  min_bonus_score = VALUES(min_bonus_score),
  bonus_pool_amount = VALUES(bonus_pool_amount),
  bonus_percent_of_profit = VALUES(bonus_percent_of_profit),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

SET @daily_target_plan_id := (
  SELECT id
  FROM fin_target_plan
  WHERE target_code = @daily_target_code
  LIMIT 1
);

INSERT INTO fin_target_plan_line (
  target_plan_id,
  metric_group,
  metric_code,
  metric_label,
  comparator,
  target_value,
  minimum_value,
  maximum_value,
  warning_value,
  weight_percent,
  is_required,
  notes,
  created_at,
  updated_at
)
SELECT
  @daily_target_plan_id,
  'REVENUE',
  'POS_REVENUE',
  'Omzet POS',
  'MIN',
  @daily_target_amount,
  @daily_target_amount,
  NULL,
  @daily_target_amount * 0.90,
  100.0000,
  1,
  'Target harian aktif: omzet bersih minimal Rp 3.000.000.',
  NOW(),
  NOW()
FROM DUAL
WHERE @daily_target_plan_id IS NOT NULL
ON DUPLICATE KEY UPDATE
  metric_group = VALUES(metric_group),
  metric_label = VALUES(metric_label),
  comparator = VALUES(comparator),
  target_value = VALUES(target_value),
  minimum_value = VALUES(minimum_value),
  maximum_value = VALUES(maximum_value),
  warning_value = VALUES(warning_value),
  weight_percent = VALUES(weight_percent),
  is_required = VALUES(is_required),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- D. Target bulanan aktif sebagai gerbang bonus
-- ------------------------------------------------------------
INSERT INTO fin_target_plan (
  target_code,
  target_name,
  target_scope,
  target_year,
  target_month,
  target_date,
  date_start,
  date_end,
  division_id,
  company_account_id,
  status,
  bonus_gate_mode,
  min_bonus_score,
  bonus_pool_amount,
  bonus_percent_of_profit,
  notes,
  created_at,
  updated_at
)
VALUES (
  @monthly_target_code,
  @monthly_target_name,
  'MONTHLY',
  YEAR(@scheme_start),
  MONTH(@scheme_start),
  NULL,
  @scheme_start,
  @scheme_end,
  NULL,
  NULL,
  'ACTIVE',
  'ALL_REQUIRED',
  @minimum_bonus_score,
  0.00,
  0.0000,
  'Gerbang bonus bulanan. Bonus harian 3% boleh dibentuk per hari, tetapi pencairannya ditahan bila profit estimasi bulanan belum mencapai Rp 10.000.000.',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  target_name = VALUES(target_name),
  target_scope = VALUES(target_scope),
  target_year = VALUES(target_year),
  target_month = VALUES(target_month),
  target_date = VALUES(target_date),
  date_start = VALUES(date_start),
  date_end = VALUES(date_end),
  status = VALUES(status),
  bonus_gate_mode = VALUES(bonus_gate_mode),
  min_bonus_score = VALUES(min_bonus_score),
  bonus_pool_amount = VALUES(bonus_pool_amount),
  bonus_percent_of_profit = VALUES(bonus_percent_of_profit),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

SET @monthly_target_plan_id := (
  SELECT id
  FROM fin_target_plan
  WHERE target_code = @monthly_target_code
  LIMIT 1
);

INSERT INTO fin_target_plan_line (
  target_plan_id,
  metric_group,
  metric_code,
  metric_label,
  comparator,
  target_value,
  minimum_value,
  maximum_value,
  warning_value,
  weight_percent,
  is_required,
  notes,
  created_at,
  updated_at
)
SELECT
  @monthly_target_plan_id,
  'PROFITABILITY',
  'ESTIMATED_PROFIT_VALUE',
  'Profit Estimasi',
  'MIN',
  @monthly_profit_target,
  @monthly_profit_target,
  NULL,
  @monthly_profit_target * 0.85,
  100.0000,
  1,
  'Gerbang utama bonus: estimasi keuangan bulanan minimal Rp 10.000.000.',
  NOW(),
  NOW()
FROM DUAL
WHERE @monthly_target_plan_id IS NOT NULL
ON DUPLICATE KEY UPDATE
  metric_group = VALUES(metric_group),
  metric_label = VALUES(metric_label),
  comparator = VALUES(comparator),
  target_value = VALUES(target_value),
  minimum_value = VALUES(minimum_value),
  maximum_value = VALUES(maximum_value),
  warning_value = VALUES(warning_value),
  weight_percent = VALUES(weight_percent),
  is_required = VALUES(is_required),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

-- ------------------------------------------------------------
-- E. Konfigurasi bonus
-- ------------------------------------------------------------
INSERT INTO pay_bonus_config (
  config_code,
  config_name,
  description,
  company_account_id,
  distribution_scope,
  pool_source_mode,
  pool_source_value,
  payout_percent,
  linked_target_required,
  include_shift_revenue_factor,
  include_service_time_factor,
  include_peer_review_factor,
  include_attendance_factor,
  include_manual_penalty_factor,
  status,
  notes,
  created_at,
  updated_at
)
VALUES (
  @config_code,
  @config_name,
  'Skema contoh: threshold harian Rp 3.000.000, pool 3% omzet harian bersih, unlock final menunggu target bulanan estimasi keuangan.',
  NULL,
  'GLOBAL',
  'TARGET_LINKED',
  0.0000,
  100.0000,
  1,
  1,
  1,
  1,
  1,
  1,
  'ACTIVE',
  'Konfigurasi contoh agar tim bonus dan target memakai bahasa yang sama.',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  config_name = VALUES(config_name),
  description = VALUES(description),
  company_account_id = VALUES(company_account_id),
  distribution_scope = VALUES(distribution_scope),
  pool_source_mode = VALUES(pool_source_mode),
  pool_source_value = VALUES(pool_source_value),
  payout_percent = VALUES(payout_percent),
  linked_target_required = VALUES(linked_target_required),
  include_shift_revenue_factor = VALUES(include_shift_revenue_factor),
  include_service_time_factor = VALUES(include_service_time_factor),
  include_peer_review_factor = VALUES(include_peer_review_factor),
  include_attendance_factor = VALUES(include_attendance_factor),
  include_manual_penalty_factor = VALUES(include_manual_penalty_factor),
  status = VALUES(status),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

SET @config_id := (
  SELECT id
  FROM pay_bonus_config
  WHERE config_code = @config_code
  LIMIT 1
);

-- ------------------------------------------------------------
-- F. Rule bonus utama
-- ------------------------------------------------------------
INSERT INTO pay_bonus_rule (
  config_id,
  rule_code,
  rule_name,
  outlet_id,
  division_id,
  linked_target_plan_id,
  daily_target_plan_id,
  active_start_date,
  active_end_date,
  threshold_amount,
  pool_formula_type,
  pool_formula_value,
  min_shift_base_pct,
  min_target_score,
  target_gate_mode,
  ph_bonus_mode,
  ph_point_deduction,
  holiday_bonus_mode,
  late_penalty_mode,
  late_penalty_value,
  alpha_penalty_value,
  service_time_target_minute,
  service_time_weight,
  shift_revenue_weight,
  peer_review_weight,
  attendance_weight,
  manual_penalty_weight,
  is_active,
  notes,
  created_at,
  updated_at
)
SELECT
  @config_id,
  @rule_code,
  @rule_name,
  NULL,
  NULL,
  @monthly_target_plan_id,
  @daily_target_plan_id,
  @scheme_start,
  @scheme_end,
  @daily_target_amount,
  'PERCENTAGE',
  @daily_pool_percent,
  30.00,
  @minimum_bonus_score,
  'ALL_REQUIRED',
  'REDUCE',
  1.0000,
  'IGNORE',
  'REDUCE_POINT',
  1.0000,
  10.0000,
  15.00,
  1.0000,
  1.0000,
  0.2500,
  1.0000,
  1.0000,
  1,
  'Rule utama contoh. Jika omzet bersih harian < Rp 3.000.000 maka pool = 0. Jika omzet lolos, pool = 3% omzet bersih. Payout akhir tetap ditahan / dipengaruhi target bulanan linked plan.',
  NOW(),
  NOW()
FROM DUAL
WHERE @config_id IS NOT NULL
ON DUPLICATE KEY UPDATE
  config_id = VALUES(config_id),
  rule_name = VALUES(rule_name),
  outlet_id = VALUES(outlet_id),
  division_id = VALUES(division_id),
  linked_target_plan_id = VALUES(linked_target_plan_id),
  daily_target_plan_id = VALUES(daily_target_plan_id),
  active_start_date = VALUES(active_start_date),
  active_end_date = VALUES(active_end_date),
  threshold_amount = VALUES(threshold_amount),
  pool_formula_type = VALUES(pool_formula_type),
  pool_formula_value = VALUES(pool_formula_value),
  min_shift_base_pct = VALUES(min_shift_base_pct),
  min_target_score = VALUES(min_target_score),
  target_gate_mode = VALUES(target_gate_mode),
  ph_bonus_mode = VALUES(ph_bonus_mode),
  ph_point_deduction = VALUES(ph_point_deduction),
  holiday_bonus_mode = VALUES(holiday_bonus_mode),
  late_penalty_mode = VALUES(late_penalty_mode),
  late_penalty_value = VALUES(late_penalty_value),
  alpha_penalty_value = VALUES(alpha_penalty_value),
  service_time_target_minute = VALUES(service_time_target_minute),
  service_time_weight = VALUES(service_time_weight),
  shift_revenue_weight = VALUES(shift_revenue_weight),
  peer_review_weight = VALUES(peer_review_weight),
  attendance_weight = VALUES(attendance_weight),
  manual_penalty_weight = VALUES(manual_penalty_weight),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

SET @rule_id := (
  SELECT id
  FROM pay_bonus_rule
  WHERE rule_code = @rule_code
  LIMIT 1
);

-- ------------------------------------------------------------
-- G. Contoh bobot ala ctx=employee
-- ------------------------------------------------------------
-- 1) Divisi
INSERT INTO pay_bonus_weight_rule (
  rule_id,
  weight_scope,
  scope_id,
  point_weight,
  pool_weight,
  notes,
  is_active,
  created_at,
  updated_at
)
SELECT
  @rule_id,
  'DIVISION',
  d.id,
  CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE 1.0000
  END,
  CASE
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'KITCHEN' THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(d.division_name, ''))) = 'BAR' THEN 1.0000
    ELSE 1.0000
  END,
  'Contoh bobot divisi untuk rule bonus 3% omzet.',
  1,
  NOW(),
  NOW()
FROM org_division d
WHERE @rule_id IS NOT NULL
  AND UPPER(TRIM(COALESCE(d.division_name, ''))) IN ('BAR', 'KITCHEN')
  AND NOT EXISTS (
    SELECT 1
    FROM pay_bonus_weight_rule w
    WHERE w.rule_id = @rule_id
      AND w.weight_scope = 'DIVISION'
      AND w.scope_id = d.id
  );

-- 2) Jabatan
INSERT INTO pay_bonus_weight_rule (
  rule_id,
  weight_scope,
  scope_id,
  point_weight,
  pool_weight,
  notes,
  is_active,
  created_at,
  updated_at
)
SELECT
  @rule_id,
  'POSITION',
  p.id,
  CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('SUPERVISOR', 'LEADER', 'HEAD BAR', 'HEAD KITCHEN') THEN 1.1500
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('KASIR', 'BARISTA', 'WAITER', 'COOK', 'COOK HELPER', 'HELPER') THEN 1.0000
    ELSE 1.0000
  END,
  CASE
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('SUPERVISOR', 'LEADER', 'HEAD BAR', 'HEAD KITCHEN') THEN 1.1000
    WHEN UPPER(TRIM(COALESCE(p.position_name, ''))) IN ('KASIR', 'BARISTA', 'WAITER', 'COOK', 'COOK HELPER', 'HELPER') THEN 1.0000
    ELSE 1.0000
  END,
  'Contoh bobot jabatan untuk rule bonus 3% omzet.',
  1,
  NOW(),
  NOW()
FROM org_position p
WHERE @rule_id IS NOT NULL
  AND UPPER(TRIM(COALESCE(p.position_name, ''))) IN (
    'SUPERVISOR', 'LEADER', 'HEAD BAR', 'HEAD KITCHEN',
    'KASIR', 'BARISTA', 'WAITER', 'COOK', 'COOK HELPER', 'HELPER'
  )
  AND NOT EXISTS (
    SELECT 1
    FROM pay_bonus_weight_rule w
    WHERE w.rule_id = @rule_id
      AND w.weight_scope = 'POSITION'
      AND w.scope_id = p.id
  );

-- ------------------------------------------------------------
-- H. Audit hasil seed
-- ------------------------------------------------------------
COMMIT;

SELECT
  tp.id,
  tp.target_code,
  tp.target_name,
  tp.target_scope,
  tp.status,
  tp.date_start,
  tp.date_end,
  tp.bonus_gate_mode,
  tp.min_bonus_score
FROM fin_target_plan tp
WHERE tp.target_code IN (@daily_target_code, @monthly_target_code)
ORDER BY tp.target_scope, tp.target_code;

SELECT
  tl.target_plan_id,
  tp.target_code,
  tl.metric_code,
  tl.metric_label,
  tl.comparator,
  tl.target_value,
  tl.minimum_value,
  tl.weight_percent,
  tl.is_required
FROM fin_target_plan_line tl
JOIN fin_target_plan tp ON tp.id = tl.target_plan_id
WHERE tp.target_code IN (@daily_target_code, @monthly_target_code)
ORDER BY tp.target_code, tl.metric_code;

SELECT
  c.id AS config_id,
  c.config_code,
  c.config_name,
  c.pool_source_mode,
  c.payout_percent,
  c.linked_target_required,
  r.id AS rule_id,
  r.rule_code,
  r.rule_name,
  r.threshold_amount,
  r.pool_formula_type,
  r.pool_formula_value,
  r.target_gate_mode,
  r.min_target_score,
  r.linked_target_plan_id,
  r.daily_target_plan_id
FROM pay_bonus_config c
LEFT JOIN pay_bonus_rule r
  ON r.config_id = c.id
 AND r.rule_code = @rule_code
WHERE c.config_code = @config_code;

SELECT
  w.rule_id,
  w.weight_scope,
  w.scope_id,
  w.point_weight,
  w.pool_weight,
  w.notes
FROM pay_bonus_weight_rule w
WHERE w.rule_id = @rule_id
ORDER BY w.weight_scope, w.scope_id;

SELECT
  'Generator pool harian dijalankan dari /payroll/bonus -> tab Ringkasan -> form "Generate draft pool bonus harian". Endpoint POST: /payroll/bonus/generate-pool. Data masuk ke pay_bonus_pool_daily, pay_bonus_pool_shift, dan pay_bonus_employee_daily.' AS next_step;
