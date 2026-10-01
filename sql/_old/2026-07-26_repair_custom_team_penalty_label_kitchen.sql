START TRANSACTION;

-- Repair penalti custom tim kitchen yang telanjur masuk memakai master lama
-- CUSTOM_SUPERADMIN dengan label "Libur Saat Event".
-- Mulai perubahan kode berikutnya, custom penalty dibuat sebagai master type
-- tersendiri per nama custom, bukan menimpa/reuse CUSTOM_SUPERADMIN.

SET @penalty_name := 'Grab cancel karena stok tidak di recon';
SET @penalty_code := CONCAT(
  'CUSTOM_',
  LEFT(REGEXP_REPLACE(UPPER(@penalty_name), '[^A-Z0-9]+', '_'), 22),
  '_',
  LEFT(SHA1(@penalty_name), 8)
);

CREATE TABLE IF NOT EXISTS zz_bak_penalty_event_20260726_custom_kitchen AS
SELECT pe.*
FROM pay_bonus_penalty_event pe
JOIN pay_bonus_penalty_type pt ON pt.id = pe.penalty_type_id
WHERE pe.penalty_date = '2026-07-26'
  AND pe.penalty_scope = 'TEAM'
  AND pe.source_type = 'MANUAL'
  AND pe.division_id = 3
  AND pe.reason_text = @penalty_name
  AND pt.penalty_code = 'CUSTOM_SUPERADMIN';

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
  sort_order,
  created_at,
  updated_at
)
SELECT
  @penalty_code,
  @penalty_name,
  'OTHER',
  'FIXED_POINT',
  0,
  0,
  'BOTH',
  1,
  'MANUAL',
  NULL,
  NULL,
  'PER_EVENT',
  1,
  0,
  1,
  'Repair 2026-07-26: master custom untuk penalti tim kitchen.',
  999,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM pay_bonus_penalty_type WHERE penalty_code = @penalty_code
);

UPDATE pay_bonus_penalty_event pe
JOIN pay_bonus_penalty_type old_pt ON old_pt.id = pe.penalty_type_id
JOIN pay_bonus_penalty_type new_pt ON new_pt.penalty_code = @penalty_code
SET pe.penalty_type_id = new_pt.id,
    pe.updated_at = NOW()
WHERE pe.penalty_date = '2026-07-26'
  AND pe.penalty_scope = 'TEAM'
  AND pe.source_type = 'MANUAL'
  AND pe.division_id = 3
  AND pe.reason_text = @penalty_name
  AND old_pt.penalty_code = 'CUSTOM_SUPERADMIN';

COMMIT;
