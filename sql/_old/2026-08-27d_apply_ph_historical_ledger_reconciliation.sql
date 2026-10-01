SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27d_apply_ph_historical_ledger_reconciliation.sql
-- Tujuan :
-- 1) Menambah GRANT PH historis yang benar tetapi belum tercatat
-- 2) Menambah USE PH historis yang benar tetapi belum tercatat
-- 3) Mengoreksi GRANT AUTO lama dari shift PH menjadi USE dengan
--    audit trail, tanpa menghapus ledger asal
--
-- Prasyarat:
-- - Backup database sudah dibuat.
-- - Review dulu 2026-08-27c_preview_ph_historical_ledger_reconciliation.sql.
-- - Jalankan 2026-08-27a terlebih dahulu.
--
-- Aman dijalankan berulang. Data yang ambigu (sudah ada USE atau
-- child EXPIRE) tidak diubah dan ditampilkan sebagai review manual.
-- ============================================================

SET @reconciliation_code := 'PH-HIST-20260827-V1';
SET @actor_user_id := NULL; -- Isi ID auth_user bila ingin mencatat pelaksana.
SET @grant_qty := COALESCE((
  SELECT NULLIF(ph_grant_qty_per_day, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 1.00);
SET @grant_requires_checkout := COALESCE((
  SELECT ph_grant_requires_checkout
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 1);
SET @checkout_close_minutes := COALESCE((
  SELECT checkout_close_minutes_after
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 180);
SET @expiry_months := COALESCE((
  SELECT NULLIF(ph_expiry_months, 0)
  FROM att_attendance_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 3);

CREATE TABLE IF NOT EXISTS att_ph_ledger_reconciliation_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reconciliation_code VARCHAR(80) NOT NULL,
  action_type ENUM('RECLASSIFY_GRANT_TO_USE','INSERT_GRANT','INSERT_USE') NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  source_daily_id BIGINT UNSIGNED NOT NULL,
  ledger_id BIGINT UNSIGNED NULL,
  before_snapshot TEXT NULL,
  after_snapshot TEXT NULL,
  notes VARCHAR(255) NULL,
  executed_by BIGINT UNSIGNED NULL,
  executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_ph_reconciliation_source_action (reconciliation_code, action_type, source_daily_id),
  KEY idx_att_ph_reconciliation_employee (employee_id, executed_at),
  KEY idx_att_ph_reconciliation_ledger (ledger_id),
  CONSTRAINT fk_att_ph_reconciliation_employee
    FOREIGN KEY (employee_id) REFERENCES org_employee(id) ON DELETE RESTRICT,
  CONSTRAINT fk_att_ph_reconciliation_executed_by
    FOREIGN KEY (executed_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Audit rekonsiliasi ledger PH historis';

-- Beberapa database lama memakai utf8mb4_general_ci pada tabel absensi.
-- Normalisasi tabel audit ini agar JOIN idempoten tidak gagal karena
-- perbandingan string lintas collation setelah percobaan apply sebelumnya.
ALTER TABLE att_ph_ledger_reconciliation_audit
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_grant;
CREATE TEMPORARY TABLE tmp_ph_reconcile_grant AS
SELECT
  ad.id AS daily_id,
  ad.employee_id,
  ad.attendance_date,
  @grant_qty AS qty_days,
  CASE
    WHEN COALESCE(pe.expiry_months_override, @expiry_months) > 0
      THEN DATE_ADD(ad.attendance_date, INTERVAL COALESCE(pe.expiry_months_override, @expiry_months) MONTH)
    ELSE NULL
  END AS expired_at
FROM att_daily ad
JOIN att_shift_schedule ss
  ON ss.employee_id = ad.employee_id
 AND ss.schedule_date = ad.attendance_date
JOIN att_shift scheduled_shift ON scheduled_shift.id = ss.shift_id
LEFT JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
JOIN att_holiday_calendar hc
  ON hc.holiday_date = ad.attendance_date
 AND hc.is_active = 1
 AND hc.holiday_type = 'NATIONAL'
JOIN att_ph_eligibility pe
  ON pe.employee_id = ad.employee_id
 AND pe.is_eligible = 1
 AND pe.effective_date <= ad.attendance_date
LEFT JOIN att_employee_ph_ledger existing_grant
  ON existing_grant.employee_id = ad.employee_id
 AND existing_grant.tx_type = 'GRANT'
 AND existing_grant.ref_table = 'att_daily'
 AND existing_grant.ref_id = ad.id
WHERE ad.attendance_status IN ('PRESENT', 'LATE')
  AND UPPER(TRIM(COALESCE(scheduled_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) NOT IN ('PH', 'PHB')
  AND ad.checkin_at IS NOT NULL
  AND DATE(ad.checkin_at) = ad.attendance_date
  AND (ad.checkout_at IS NULL OR (
    ad.checkout_at >= ad.checkin_at
    AND ad.checkout_at <= DATE_ADD(
      CASE
        WHEN COALESCE(scheduled_shift.is_overnight, 0) = 1
          OR scheduled_shift.end_time <= scheduled_shift.start_time
          THEN DATE_ADD(TIMESTAMP(ad.attendance_date, scheduled_shift.end_time), INTERVAL 1 DAY)
        ELSE TIMESTAMP(ad.attendance_date, scheduled_shift.end_time)
      END,
      INTERVAL @checkout_close_minutes MINUTE
    )
  ))
  AND (@grant_requires_checkout = 0 OR ad.checkout_at IS NOT NULL)
  AND existing_grant.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_reclassify;
CREATE TEMPORARY TABLE tmp_ph_reconcile_reclassify AS
SELECT
  wrong_grant.id AS ledger_id,
  wrong_grant.employee_id,
  ad.id AS daily_id,
  wrong_grant.tx_date,
  wrong_grant.qty_days,
  wrong_grant.notes AS original_notes
FROM att_employee_ph_ledger wrong_grant
JOIN att_daily ad
  ON wrong_grant.ref_table = 'att_daily'
 AND wrong_grant.ref_id = ad.id
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = wrong_grant.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = ad.id
LEFT JOIN att_employee_ph_ledger expire_child
  ON expire_child.tx_type = 'EXPIRE'
 AND expire_child.ref_table = 'att_employee_ph_ledger'
 AND expire_child.ref_id = wrong_grant.id
WHERE wrong_grant.tx_type = 'GRANT'
  AND UPPER(COALESCE(wrong_grant.entry_mode, '')) = 'AUTO'
  AND UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND existing_use.id IS NULL
  AND expire_child.id IS NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_ph_reconcile_use;
CREATE TEMPORARY TABLE tmp_ph_reconcile_use AS
SELECT
  ad.id AS daily_id,
  ad.employee_id,
  ad.attendance_date,
  1.00 AS qty_days
FROM att_daily ad
JOIN att_shift daily_shift ON daily_shift.id = ad.shift_id
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = ad.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = ad.id
LEFT JOIN tmp_ph_reconcile_reclassify reclassify
  ON reclassify.daily_id = ad.id
WHERE UPPER(TRIM(COALESCE(daily_shift.shift_code, ''))) IN ('PH', 'PHB')
  AND ad.attendance_status IN ('HOLIDAY', 'PRESENT', 'LATE')
  AND existing_use.id IS NULL
  AND reclassify.daily_id IS NULL;

START TRANSACTION;

-- Simpan snapshot sebelum tipe ledger lama dikoreksi.
INSERT INTO att_ph_ledger_reconciliation_audit (
  reconciliation_code, action_type, employee_id, source_daily_id, ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @reconciliation_code,
  'RECLASSIFY_GRANT_TO_USE',
  r.employee_id,
  r.daily_id,
  r.ledger_id,
  CONCAT('tx_type=GRANT; qty_days=', CAST(r.qty_days AS CHAR), '; tx_date=', r.tx_date),
  CONCAT('tx_type=USE; qty_days=', CAST(r.qty_days AS CHAR), '; tx_date=', r.tx_date),
  'GRANT AUTO lama berasal dari presensi shift PH; dikoreksi menjadi USE.',
  @actor_user_id,
  NOW()
FROM tmp_ph_reconcile_reclassify r
LEFT JOIN att_ph_ledger_reconciliation_audit audit_log
  ON audit_log.reconciliation_code = @reconciliation_code
 AND audit_log.action_type = 'RECLASSIFY_GRANT_TO_USE'
 AND audit_log.source_daily_id = r.daily_id
WHERE audit_log.id IS NULL;

UPDATE att_employee_ph_ledger wrong_grant
JOIN tmp_ph_reconcile_reclassify r ON r.ledger_id = wrong_grant.id
SET
  wrong_grant.tx_type = 'USE',
  wrong_grant.notes = CONCAT_WS(' | ', NULLIF(wrong_grant.notes, ''), 'Rekonsiliasi PH: GRANT salah dikoreksi menjadi USE karena shift presensi final adalah PH.'),
  wrong_grant.updated_at = NOW();

INSERT INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  g.employee_id,
  g.attendance_date,
  'GRANT',
  g.qty_days,
  g.expired_at,
  'att_daily',
  g.daily_id,
  'AUTO',
  'Rekonsiliasi PH: hadir pada hari libur nasional dengan shift kerja reguler.',
  @actor_user_id,
  NOW(),
  NOW()
FROM tmp_ph_reconcile_grant g
LEFT JOIN att_employee_ph_ledger existing_grant
  ON existing_grant.employee_id = g.employee_id
 AND existing_grant.tx_type = 'GRANT'
 AND existing_grant.ref_table = 'att_daily'
 AND existing_grant.ref_id = g.daily_id
WHERE existing_grant.id IS NULL;

INSERT INTO att_ph_ledger_reconciliation_audit (
  reconciliation_code, action_type, employee_id, source_daily_id, ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @reconciliation_code,
  'INSERT_GRANT',
  g.employee_id,
  g.daily_id,
  ledger_grant.id,
  NULL,
  CONCAT('tx_type=GRANT; qty_days=', CAST(ledger_grant.qty_days AS CHAR), '; tx_date=', ledger_grant.tx_date),
  'Grant PH historis yang sebelumnya belum tercatat.',
  @actor_user_id,
  NOW()
FROM tmp_ph_reconcile_grant g
JOIN att_employee_ph_ledger ledger_grant
  ON ledger_grant.employee_id = g.employee_id
 AND ledger_grant.tx_type = 'GRANT'
 AND ledger_grant.ref_table = 'att_daily'
 AND ledger_grant.ref_id = g.daily_id
LEFT JOIN att_ph_ledger_reconciliation_audit audit_log
  ON audit_log.reconciliation_code = @reconciliation_code
 AND audit_log.action_type = 'INSERT_GRANT'
 AND audit_log.source_daily_id = g.daily_id
WHERE audit_log.id IS NULL;

INSERT INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  u.employee_id,
  u.attendance_date,
  'USE',
  u.qty_days,
  NULL,
  'att_daily',
  u.daily_id,
  'AUTO',
  'Rekonsiliasi PH: penggunaan PH dari shift presensi final PH.',
  @actor_user_id,
  NOW(),
  NOW()
FROM tmp_ph_reconcile_use u
LEFT JOIN att_employee_ph_ledger existing_use
  ON existing_use.employee_id = u.employee_id
 AND existing_use.tx_type = 'USE'
 AND existing_use.ref_table = 'att_daily'
 AND existing_use.ref_id = u.daily_id
WHERE existing_use.id IS NULL;

INSERT INTO att_ph_ledger_reconciliation_audit (
  reconciliation_code, action_type, employee_id, source_daily_id, ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @reconciliation_code,
  'INSERT_USE',
  u.employee_id,
  u.daily_id,
  ledger_use.id,
  NULL,
  CONCAT('tx_type=USE; qty_days=', CAST(ledger_use.qty_days AS CHAR), '; tx_date=', ledger_use.tx_date),
  'Penggunaan PH historis yang sebelumnya belum tercatat.',
  @actor_user_id,
  NOW()
FROM tmp_ph_reconcile_use u
JOIN att_employee_ph_ledger ledger_use
  ON ledger_use.employee_id = u.employee_id
 AND ledger_use.tx_type = 'USE'
 AND ledger_use.ref_table = 'att_daily'
 AND ledger_use.ref_id = u.daily_id
LEFT JOIN att_ph_ledger_reconciliation_audit audit_log
  ON audit_log.reconciliation_code = @reconciliation_code
 AND audit_log.action_type = 'INSERT_USE'
 AND audit_log.source_daily_id = u.daily_id
WHERE audit_log.id IS NULL;

COMMIT;

SELECT action_type, COUNT(*) AS total_tindakan
FROM att_ph_ledger_reconciliation_audit
WHERE reconciliation_code = @reconciliation_code
GROUP BY action_type
ORDER BY action_type;

SELECT
  e.employee_code,
  e.employee_name,
  ROUND(SUM(CASE WHEN l.tx_type = 'GRANT' THEN l.qty_days ELSE 0 END), 2) AS total_grant,
  ROUND(SUM(CASE WHEN l.tx_type = 'USE' THEN l.qty_days ELSE 0 END), 2) AS digunakan,
  ROUND(SUM(CASE WHEN l.tx_type = 'EXPIRE' THEN l.qty_days ELSE 0 END), 2) AS expired,
  ROUND(SUM(CASE WHEN l.tx_type = 'ADJUST' THEN l.qty_days ELSE 0 END), 2) AS adjustment,
  ROUND(
    SUM(CASE WHEN l.tx_type IN ('GRANT', 'ADJUST') THEN l.qty_days ELSE 0 END)
    - SUM(CASE WHEN l.tx_type IN ('USE', 'EXPIRE') THEN l.qty_days ELSE 0 END),
    2
  ) AS saldo_ledger
FROM org_employee e
JOIN att_employee_ph_ledger l ON l.employee_id = e.id
WHERE e.employee_code = 'EMP-00006'
GROUP BY e.id, e.employee_code, e.employee_name;

-- Jalankan 2026-08-27b kembali setelah script ini. Baris yang masih muncul
-- adalah kasus ambigu atau presensi yang memang belum valid untuk grant.
