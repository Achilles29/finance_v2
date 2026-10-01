SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27f_apply_ph_core_cutover_opening_balance.sql
-- Tujuan :
-- 1) Menetralkan USE salah yang tercipta sebelum cutover Finance
-- 2) Memigrasikan saldo awal lot PH dari core per 2026-06-01
-- 3) Membangun ulang alokasi FIFO penggunaan PH dan mutasi expiry
--
-- Catatan penting:
-- - Script ini menulis ledger hanya setelah seluruh preflight lulus.
-- - Mapping pegawai memakai nama yang dinormalisasi, BUKAN employee_id.
-- - Satu nama core wajib cocok tepat ke satu nama Finance. Jika tidak,
--   apply migration nanti berhenti agar tidak salah pegawai.
-- - Cutover Finance ditetapkan pada 2026-06-01.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_prior_reconciliation_code := 'PH-HIST-20260827-V1';

-- Schema tambahan bersifat append-only. VOID menjaga jejak USE yang salah
-- tanpa menghapus mutasi lama; MIGRATION menandai data cutover agar tidak
-- terlihat sebagai input manual operator.
SET @ph_cutover_migration_code := 'PH-CORE-CUTOVER-20260601-V1';
SET @ph_actor_user_id := NULL; -- Opsional: isi auth_user.id pelaksana.
SET @ph_standard_expiry_months := COALESCE((
  SELECT NULLIF(expiry_months, 0)
  FROM core.att_ph_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 3);

ALTER TABLE att_employee_ph_ledger
  MODIFY COLUMN tx_type ENUM('GRANT','USE','EXPIRE','ADJUST','VOID') NOT NULL,
  MODIFY COLUMN entry_mode ENUM('AUTO','MANUAL','MIGRATION') NOT NULL DEFAULT 'AUTO',
  ADD COLUMN IF NOT EXISTS void_reason VARCHAR(255) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL AFTER void_reason,
  ADD COLUMN IF NOT EXISTS voided_by BIGINT UNSIGNED NULL AFTER voided_at,
  ADD INDEX IF NOT EXISTS idx_att_employee_ph_ledger_type_date_v2 (tx_type, tx_date);

CREATE TABLE IF NOT EXISTS att_ph_cutover_migration_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_code VARCHAR(80) NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  finance_ledger_id BIGINT UNSIGNED NULL,
  source_finance_daily_id BIGINT UNSIGNED NULL,
  source_core_employee_id BIGINT UNSIGNED NULL,
  source_core_ledger_id BIGINT UNSIGNED NULL,
  before_snapshot TEXT NULL,
  after_snapshot TEXT NULL,
  notes VARCHAR(255) NULL,
  executed_by BIGINT UNSIGNED NULL,
  executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_ph_cutover_audit_finance (migration_code, action_type, finance_ledger_id),
  UNIQUE KEY uk_att_ph_cutover_audit_core (migration_code, action_type, source_core_ledger_id),
  KEY idx_att_ph_cutover_audit_employee (employee_id, executed_at),
  KEY idx_att_ph_cutover_audit_core_employee (source_core_employee_id, source_core_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Jejak migrasi saldo awal PH dari core pada cutover Finance 2026-06-01';
DROP TEMPORARY TABLE IF EXISTS tmp_ph_core_source;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_core_opening_lot;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_core_cutover_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_finance_name_map;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_core_import_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_void_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_finance_ledger_work;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_finance_lot_state;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_finance_cutover_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_finance_expire_plan;
DROP PROCEDURE IF EXISTS sp_ph_core_cutover_build;
DROP PROCEDURE IF EXISTS sp_ph_finance_cutover_validate;

-- Semua mutasi core sebelum cutover diproses berurutan untuk membentuk
-- sisa lot yang sesungguhnya. USE dipotong FIFO dari lot yang masih aktif.
CREATE TEMPORARY TABLE tmp_ph_core_source AS
SELECT
  l.id AS source_core_ledger_id,
  l.employee_id AS core_employee_id,
  e.employee_name AS core_employee_name,
  l.tx_date,
  UPPER(l.tx_type) AS tx_type,
  ROUND(l.qty_days, 2) AS qty_days,
  l.expired_at,
  COALESCE(l.ref_table, '') AS ref_table,
  COALESCE(l.ref_id, 0) AS ref_id
FROM core.att_employee_ph_ledger l
JOIN core.org_employee e ON e.id = l.employee_id
WHERE l.tx_date < @ph_cutover_date
  AND UPPER(l.tx_type) IN ('GRANT', 'USE', 'EXPIRE', 'ADJUST')
ORDER BY l.employee_id, l.tx_date, l.id;

CREATE TEMPORARY TABLE tmp_ph_core_opening_lot (
  source_core_ledger_id BIGINT UNSIGNED NOT NULL,
  core_employee_id BIGINT UNSIGNED NOT NULL,
  core_employee_name VARCHAR(150) NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_original DECIMAL(8,2) NOT NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(12) NOT NULL,
  PRIMARY KEY (source_core_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_core_cutover_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  core_employee_id BIGINT UNSIGNED NULL,
  source_core_ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_core_cutover_build()
BEGIN
  DECLARE v_done TINYINT DEFAULT 0;
  DECLARE v_ledger_id BIGINT UNSIGNED;
  DECLARE v_employee_id BIGINT UNSIGNED;
  DECLARE v_employee_name VARCHAR(150);
  DECLARE v_tx_date DATE;
  DECLARE v_tx_type VARCHAR(20);
  DECLARE v_qty DECIMAL(8,2);
  DECLARE v_expired_at DATE;
  DECLARE v_ref_table VARCHAR(80);
  DECLARE v_ref_id BIGINT;
  DECLARE v_remaining DECIMAL(8,2);
  DECLARE v_lot_id BIGINT UNSIGNED;
  DECLARE v_lot_available DECIMAL(8,2);
  DECLARE v_taken DECIMAL(8,2);

  DECLARE core_cursor CURSOR FOR
    SELECT source_core_ledger_id, core_employee_id, core_employee_name,
           tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
    FROM tmp_ph_core_source
    ORDER BY core_employee_id, tx_date, source_core_ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  OPEN core_cursor;
  read_loop: LOOP
    FETCH core_cursor INTO v_ledger_id, v_employee_id, v_employee_name,
      v_tx_date, v_tx_type, v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF COALESCE(v_qty, 0) <= 0 THEN
      INSERT INTO tmp_ph_core_cutover_issue (
        issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
      ) VALUES (
        'CORE_NON_POSITIVE_QTY', v_employee_id, v_ledger_id, v_tx_date, v_qty,
        'Mutasi core memiliki qty_days nol atau negatif dan perlu review manual.'
      );
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      INSERT INTO tmp_ph_core_opening_lot (
        source_core_ledger_id, core_employee_id, core_employee_name,
        tx_date, expired_at, qty_original, qty_remaining, lot_origin
      ) VALUES (
        v_ledger_id, v_employee_id, v_employee_name,
        v_tx_date, v_expired_at, v_qty, v_qty, 'GRANT'
      );
    ELSEIF v_tx_type = 'USE' THEN
      SET v_remaining = v_qty;
      consume_use_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.source_core_ledger_id
          FROM tmp_ph_core_opening_lot lot
          WHERE lot.core_employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.source_core_ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;

        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_core_cutover_issue (
            issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
          ) VALUES (
            'CORE_USE_NOT_COVERED_BY_ACTIVE_LOT', v_employee_id, v_ledger_id,
            v_tx_date, v_remaining,
            'USE core tidak dapat dialokasikan ke lot PH aktif secara FIFO.'
          );
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available
          FROM tmp_ph_core_opening_lot
          WHERE source_core_ledger_id = v_lot_id;
          SET v_taken = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_core_opening_lot
          SET qty_remaining = ROUND(qty_remaining - v_taken, 2)
          WHERE source_core_ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_taken, 2);
        END IF;
      END WHILE;
    ELSEIF v_tx_type = 'EXPIRE' THEN
      IF v_ref_table = 'att_employee_ph_ledger' AND COALESCE(v_ref_id, 0) > 0 THEN
        SELECT COALESCE((
          SELECT lot.qty_remaining
          FROM tmp_ph_core_opening_lot lot
          WHERE lot.source_core_ledger_id = v_ref_id
          LIMIT 1
        ), -1) INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_core_cutover_issue (
            issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
          ) VALUES (
            'CORE_EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id,
            v_tx_date, v_qty,
            CONCAT('EXPIRE core menunjuk lot #', v_ref_id, ' yang tidak ditemukan.')
          );
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_core_cutover_issue (
            issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
          ) VALUES (
            'CORE_EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id,
            v_tx_date, v_qty,
            CONCAT('EXPIRE core melebihi sisa lot #', v_ref_id, '.')
          );
        ELSE
          UPDATE tmp_ph_core_opening_lot
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE source_core_ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_core_cutover_issue (
          issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
        ) VALUES (
          'CORE_EXPIRE_WITHOUT_LOT_REF', v_employee_id, v_ledger_id,
          v_tx_date, v_qty,
          'EXPIRE core tidak menunjuk grant/lot asal sehingga tidak akan dimigrasikan otomatis.'
        );
      END IF;
    ELSEIF v_tx_type = 'ADJUST' THEN
      INSERT INTO tmp_ph_core_cutover_issue (
        issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
      ) VALUES (
        'CORE_ADJUST_NEEDS_REVIEW', v_employee_id, v_ledger_id,
        v_tx_date, v_qty,
        'ADJUST core tidak dimigrasikan otomatis agar arah koreksinya tidak ditebak.'
      );
    ELSE
      INSERT INTO tmp_ph_core_cutover_issue (
        issue_type, core_employee_id, source_core_ledger_id, tx_date, qty_days, details
      ) VALUES (
        'CORE_UNSUPPORTED_TX_TYPE', v_employee_id, v_ledger_id,
        v_tx_date, v_qty,
        CONCAT('Jenis mutasi core tidak dikenali: ', v_tx_type)
      );
    END IF;
  END LOOP;
  CLOSE core_cursor;
END$$
DELIMITER ;

CALL sp_ph_core_cutover_build();

-- Map nama secara defensif. Penghapusan spasi/tanda baca hanya untuk
-- menjembatani variasi seperti "BAGAS BHAKTI .R" tanpa memakai ID lama.
CREATE TEMPORARY TABLE tmp_ph_finance_name_map AS
SELECT
  LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', '')) AS name_key,
  COUNT(*) AS finance_name_matches,
  MIN(id) AS finance_employee_id,
  GROUP_CONCAT(CONCAT(id, ':', employee_name) ORDER BY id SEPARATOR ' | ') AS finance_name_candidates
FROM org_employee
GROUP BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

CREATE TEMPORARY TABLE tmp_ph_core_import_plan AS
SELECT
  lot.source_core_ledger_id,
  lot.core_employee_id,
  lot.core_employee_name,
  lot.tx_date AS source_grant_date,
  lot.expired_at,
  lot.qty_original,
  lot.qty_remaining,
  name_map.finance_employee_id,
  COALESCE(name_map.finance_name_matches, 0) AS finance_name_matches,
  name_map.finance_name_candidates
FROM tmp_ph_core_opening_lot lot
LEFT JOIN tmp_ph_finance_name_map name_map
  ON name_map.name_key = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(lot.core_employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''))
WHERE lot.qty_remaining > 0.0001;

-- Hanya sembilan USE pra-cutover dari apply lama yang dinetralkan.
-- Tidak ada ledger lain yang dipilih oleh kondisi ini.
CREATE TEMPORARY TABLE tmp_ph_void_plan AS
SELECT DISTINCT
  ledger.id AS finance_ledger_id,
  ledger.employee_id,
  daily.id AS source_daily_id,
  daily.attendance_date,
  ledger.qty_days,
  ledger.notes AS original_notes
FROM att_ph_ledger_reconciliation_audit audit_log
JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.ledger_id
JOIN att_daily daily ON daily.id = audit_log.source_daily_id
WHERE audit_log.reconciliation_code = @ph_prior_reconciliation_code
  AND audit_log.action_type = 'INSERT_USE'
  AND daily.attendance_date < @ph_cutover_date
  AND ledger.tx_type = 'USE';

-- Simulasi ledger Finance setelah pembatalan pra-cutover dan setelah lot
-- pembuka core ditambahkan. Ini tetap hanya TEMPORARY TABLE.
CREATE TEMPORARY TABLE tmp_ph_finance_ledger_work AS
SELECT
  ledger.id AS ledger_id,
  ledger.employee_id,
  ledger.tx_date,
  CAST(ledger.tx_type AS CHAR(20)) AS tx_type,
  ROUND(ledger.qty_days, 2) AS qty_days,
  ledger.expired_at,
  COALESCE(ledger.ref_table, '') AS ref_table,
  COALESCE(ledger.ref_id, 0) AS ref_id
FROM att_employee_ph_ledger ledger;

UPDATE tmp_ph_finance_ledger_work work
JOIN tmp_ph_void_plan void_plan ON void_plan.finance_ledger_id = work.ledger_id
SET work.tx_type = 'VOID';

INSERT INTO tmp_ph_finance_ledger_work (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  9000000000 + plan.source_core_ledger_id,
  plan.finance_employee_id,
  plan.source_grant_date,
  'GRANT',
  plan.qty_remaining,
  plan.expired_at,
  'core.att_employee_ph_ledger',
  plan.source_core_ledger_id
FROM tmp_ph_core_import_plan plan
WHERE plan.finance_name_matches = 1;

CREATE TEMPORARY TABLE tmp_ph_finance_lot_state (
  ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(20) NOT NULL,
  PRIMARY KEY (ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_finance_cutover_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_finance_expire_plan (
  lot_ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  expired_at DATE NOT NULL,
  expire_tx_date DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (lot_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_finance_cutover_validate()
BEGIN
  DECLARE v_done TINYINT DEFAULT 0;
  DECLARE v_ledger_id BIGINT UNSIGNED;
  DECLARE v_employee_id BIGINT UNSIGNED;
  DECLARE v_tx_date DATE;
  DECLARE v_tx_type VARCHAR(20);
  DECLARE v_qty DECIMAL(8,2);
  DECLARE v_expired_at DATE;
  DECLARE v_ref_table VARCHAR(80);
  DECLARE v_ref_id BIGINT;
  DECLARE v_remaining DECIMAL(8,2);
  DECLARE v_lot_id BIGINT UNSIGNED;
  DECLARE v_lot_available DECIMAL(8,2);
  DECLARE v_taken DECIMAL(8,2);

  DECLARE finance_cursor CURSOR FOR
    SELECT ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
    FROM tmp_ph_finance_ledger_work
    ORDER BY employee_id, tx_date, ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  DELETE FROM tmp_ph_finance_lot_state;
  DELETE FROM tmp_ph_finance_cutover_issue;
  DELETE FROM tmp_ph_finance_expire_plan;

  OPEN finance_cursor;
  read_loop: LOOP
    FETCH finance_cursor INTO v_ledger_id, v_employee_id, v_tx_date,
      v_tx_type, v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF COALESCE(v_qty, 0) <= 0 THEN
      INSERT INTO tmp_ph_finance_cutover_issue (
        issue_type, employee_id, ledger_id, tx_date, qty_days, details
      ) VALUES (
        'FINANCE_NON_POSITIVE_QTY', v_employee_id, v_ledger_id,
        v_tx_date, v_qty, 'Ledger Finance memiliki qty_days nol atau negatif.'
      );
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      INSERT INTO tmp_ph_finance_lot_state (
        ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin
      ) VALUES (
        v_ledger_id, v_employee_id, v_tx_date, v_expired_at, v_qty, 'GRANT'
      );
    ELSEIF v_tx_type = 'ADJUST' THEN
      INSERT INTO tmp_ph_finance_lot_state (
        ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin
      ) VALUES (
        v_ledger_id, v_employee_id, v_tx_date, NULL, v_qty, 'ADJUST'
      );
    ELSEIF v_tx_type = 'USE' THEN
      SET v_remaining = v_qty;
      consume_use_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.ledger_id
          FROM tmp_ph_finance_lot_state lot
          WHERE lot.employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;

        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_finance_cutover_issue (
            issue_type, employee_id, ledger_id, tx_date, qty_days, details
          ) VALUES (
            'FINANCE_USE_NOT_COVERED_BY_ACTIVE_LOT', v_employee_id,
            v_ledger_id, v_tx_date, v_remaining,
            'USE Finance tidak dapat ditutup oleh lot PH aktif secara FIFO.'
          );
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available
          FROM tmp_ph_finance_lot_state
          WHERE ledger_id = v_lot_id;
          SET v_taken = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_finance_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_taken, 2)
          WHERE ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_taken, 2);
        END IF;
      END WHILE;
    ELSEIF v_tx_type = 'EXPIRE' THEN
      IF v_ref_table = 'att_employee_ph_ledger' AND COALESCE(v_ref_id, 0) > 0 THEN
        SELECT COALESCE((
          SELECT lot.qty_remaining
          FROM tmp_ph_finance_lot_state lot
          WHERE lot.ledger_id = v_ref_id
          LIMIT 1
        ), -1) INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_finance_cutover_issue (
            issue_type, employee_id, ledger_id, tx_date, qty_days, details
          ) VALUES (
            'FINANCE_EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id,
            v_tx_date, v_qty,
            CONCAT('EXPIRE Finance menunjuk lot #', v_ref_id, ' yang tidak ditemukan.')
          );
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_finance_cutover_issue (
            issue_type, employee_id, ledger_id, tx_date, qty_days, details
          ) VALUES (
            'FINANCE_EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id,
            v_tx_date, v_qty,
            CONCAT('EXPIRE Finance melebihi sisa lot #', v_ref_id, '.')
          );
        ELSE
          UPDATE tmp_ph_finance_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_finance_cutover_issue (
          issue_type, employee_id, ledger_id, tx_date, qty_days, details
        ) VALUES (
          'FINANCE_EXPIRE_WITHOUT_LOT_REF', v_employee_id, v_ledger_id,
          v_tx_date, v_qty,
          'EXPIRE Finance tidak menunjuk lot asal.'
        );
      END IF;
    ELSEIF v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_finance_cutover_issue (
        issue_type, employee_id, ledger_id, tx_date, qty_days, details
      ) VALUES (
        'FINANCE_UNSUPPORTED_TX_TYPE', v_employee_id, v_ledger_id,
        v_tx_date, v_qty, CONCAT('Jenis mutasi Finance tidak dikenali: ', v_tx_type)
      );
    END IF;
  END LOOP;
  CLOSE finance_cursor;

  -- @ph_as_of_date adalah hari pemeriksaan. Lot masih valid sampai tanggal
  -- expired_at, lalu mutasi EXPIRE dicatat pada hari berikutnya.
  INSERT INTO tmp_ph_finance_expire_plan (
    lot_ledger_id, employee_id, expired_at, expire_tx_date, qty_days
  )
  SELECT
    lot.ledger_id,
    lot.employee_id,
    lot.expired_at,
    DATE_ADD(lot.expired_at, INTERVAL 1 DAY),
    ROUND(lot.qty_remaining, 2)
  FROM tmp_ph_finance_lot_state lot
  WHERE lot.qty_remaining > 0.0001
    AND lot.expired_at IS NOT NULL
    AND lot.expired_at < @ph_as_of_date;
END$$
DELIMITER ;

CALL sp_ph_finance_cutover_validate();

-- ============================================================
-- APPLY MULAI DI SINI
-- ============================================================
-- Semua masalah di bawah harus nol. Assertion membuat script berhenti sebelum
-- menulis ledger bila core, mapping nama, atau alokasi FIFO tidak konsisten.
DROP PROCEDURE IF EXISTS sp_ph_core_cutover_assert;
DELIMITER $$
CREATE PROCEDURE sp_ph_core_cutover_assert(IN p_stage VARCHAR(40), IN p_rollback_on_failure TINYINT)
BEGIN
  DECLARE v_core_issue INT DEFAULT 0;
  DECLARE v_name_issue INT DEFAULT 0;
  DECLARE v_finance_issue INT DEFAULT 0;
  DECLARE v_pending_expire INT DEFAULT 0;
  DECLARE v_message VARCHAR(255);

  SELECT COUNT(*) INTO v_core_issue FROM tmp_ph_core_cutover_issue;
  SELECT COUNT(*) INTO v_name_issue
  FROM tmp_ph_core_import_plan
  WHERE finance_name_matches <> 1;
  SELECT COUNT(*) INTO v_finance_issue FROM tmp_ph_finance_cutover_issue;
  SELECT COUNT(*) INTO v_pending_expire FROM tmp_ph_finance_expire_plan;

  IF v_core_issue > 0 OR v_name_issue > 0 OR v_finance_issue > 0
     OR (p_stage = 'AFTER_EXPIRE' AND v_pending_expire > 0) THEN
    IF p_rollback_on_failure = 1 THEN
      ROLLBACK;
    END IF;
    SET v_message = CONCAT(
      'PH cutover ', p_stage,
      ' dibatalkan. core=', v_core_issue,
      ', mapping=', v_name_issue,
      ', finance=', v_finance_issue,
      ', pending_expire=', v_pending_expire
    );
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
  END IF;
END$$
DELIMITER ;

-- Validasi pertama memakai simulasi, jadi belum ada saldo atau USE permanen
-- yang diubah pada tahap ini.
CALL sp_ph_core_cutover_assert('PREFLIGHT', 0);

START TRANSACTION;
SET @ph_now := NOW();

-- Satu angka expiry resmi untuk grant Finance baru. Lot pembuka dari core
-- tetap mempertahankan expired_at aslinya, tidak dihitung ulang.
UPDATE att_attendance_policy
SET ph_expiry_months = @ph_standard_expiry_months,
    updated_at = @ph_now
WHERE is_active = 1
  AND COALESCE(ph_expiry_months, 0) <> @ph_standard_expiry_months;

-- Catat audit sebelum sembilan USE pra-cutover dinetralkan.
INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_cutover_migration_code,
  'VOID_PRE_CUTOVER_USE',
  plan.employee_id,
  plan.finance_ledger_id,
  plan.source_daily_id,
  NULL,
  NULL,
  CONCAT('tx_type=USE; tx_date=', plan.attendance_date, '; qty_days=', CAST(plan.qty_days AS CHAR)),
  CONCAT('tx_type=VOID; tx_date=', plan.attendance_date, '; qty_days=', CAST(plan.qty_days AS CHAR)),
  'USE dari rekonsiliasi lama terjadi sebelum cutover Finance 2026-06-01; dinetralkan, bukan dihapus.',
  @ph_actor_user_id,
  @ph_now
FROM tmp_ph_void_plan plan
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_cutover_migration_code
 AND audit_log.action_type = 'VOID_PRE_CUTOVER_USE'
 AND audit_log.finance_ledger_id = plan.finance_ledger_id
WHERE audit_log.id IS NULL;

UPDATE att_employee_ph_ledger ledger
JOIN tmp_ph_void_plan plan ON plan.finance_ledger_id = ledger.id
SET
  ledger.tx_type = 'VOID',
  ledger.entry_mode = 'MIGRATION',
  ledger.void_reason = 'Dibatalkan oleh migrasi cutover: penggunaan PH terjadi sebelum Finance mulai dipakai pada 2026-06-01.',
  ledger.voided_at = COALESCE(ledger.voided_at, @ph_now),
  ledger.voided_by = @ph_actor_user_id,
  ledger.notes = LEFT(CONCAT_WS(' | ', NULLIF(ledger.notes, ''), 'VOID cutover: presensi sebelum 2026-06-01 tidak boleh mengurangi saldo Finance.'), 255),
  ledger.updated_at = @ph_now
WHERE ledger.tx_type = 'USE';

-- Bawa hanya SISA lot core per 31 Mei. Setiap lot menjaga tanggal grant dan
-- tanggal berlaku sampai yang asli sehingga FIFO serta expiry tetap akurat.
INSERT IGNORE INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  plan.finance_employee_id,
  plan.source_grant_date,
  'GRANT',
  plan.qty_remaining,
  plan.expired_at,
  'core.att_employee_ph_ledger',
  plan.source_core_ledger_id,
  'MIGRATION',
  LEFT(CONCAT(
    'Saldo awal PH cutover 2026-06-01 dari core lot #', plan.source_core_ledger_id,
    '; grant ', plan.source_grant_date,
    '; berlaku s.d. ', COALESCE(DATE_FORMAT(plan.expired_at, '%Y-%m-%d'), '-')
  ), 255),
  @ph_actor_user_id,
  @ph_now,
  @ph_now
FROM tmp_ph_core_import_plan plan
WHERE plan.finance_name_matches = 1;

INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_cutover_migration_code,
  'IMPORT_CORE_OPENING_LOT',
  ledger.employee_id,
  ledger.id,
  NULL,
  plan.core_employee_id,
  plan.source_core_ledger_id,
  NULL,
  CONCAT('qty_days=', CAST(ledger.qty_days AS CHAR), '; tx_date=', ledger.tx_date, '; expired_at=', COALESCE(ledger.expired_at, 'NULL')),
  'Sisa lot core per cutover Finance 2026-06-01, dipetakan berdasarkan nama pegawai yang unik.',
  @ph_actor_user_id,
  @ph_now
FROM tmp_ph_core_import_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.finance_employee_id
 AND ledger.tx_type = 'GRANT'
 AND ledger.ref_table = 'core.att_employee_ph_ledger'
 AND ledger.ref_id = plan.source_core_ledger_id
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_cutover_migration_code
 AND audit_log.action_type = 'IMPORT_CORE_OPENING_LOT'
 AND audit_log.source_core_ledger_id = plan.source_core_ledger_id
WHERE plan.finance_name_matches = 1
  AND audit_log.id IS NULL;

-- Bangun ulang simulasi dari ledger aktual pasca-import. Tahap ini sekaligus
-- membuktikan semua USE Finance sejak cutover dapat dibaca dari lot yang sah.
DELETE FROM tmp_ph_finance_ledger_work;
INSERT INTO tmp_ph_finance_ledger_work (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  ledger.id,
  ledger.employee_id,
  ledger.tx_date,
  CAST(ledger.tx_type AS CHAR(20)),
  ROUND(ledger.qty_days, 2),
  ledger.expired_at,
  COALESCE(ledger.ref_table, ''),
  COALESCE(ledger.ref_id, 0)
FROM att_employee_ph_ledger ledger;

CALL sp_ph_finance_cutover_validate();
CALL sp_ph_core_cutover_assert('AFTER_IMPORT', 1);

-- Catat expiry lot yang telah benar-benar melewati tanggal berlaku. Mutasi
-- dicatat sehari setelah "berlaku s.d." agar PH tetap dapat digunakan tepat
-- pada tanggal terakhirnya, sama dengan aturan core dan guard jadwal.
INSERT IGNORE INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  plan.employee_id,
  plan.expire_tx_date,
  'EXPIRE',
  plan.qty_days,
  NULL,
  'att_employee_ph_ledger',
  plan.lot_ledger_id,
  'MIGRATION',
  'Expire cutover: lot PH telah melewati tanggal berlaku.',
  @ph_actor_user_id,
  @ph_now,
  @ph_now
FROM tmp_ph_finance_expire_plan plan;

INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_cutover_migration_code,
  'CREATE_POST_CUTOVER_EXPIRE',
  expire_ledger.employee_id,
  expire_ledger.id,
  NULL,
  NULL,
  plan.lot_ledger_id,
  CONCAT('lot_ledger_id=', plan.lot_ledger_id, '; berlaku_sampai=', plan.expired_at),
  CONCAT('tx_type=EXPIRE; qty_days=', CAST(expire_ledger.qty_days AS CHAR), '; tx_date=', expire_ledger.tx_date),
  'Expiry lot pembuka/core atau ledger Finance yang sudah melewati tanggal berlaku.',
  @ph_actor_user_id,
  @ph_now
FROM tmp_ph_finance_expire_plan plan
JOIN att_employee_ph_ledger expire_ledger
  ON expire_ledger.employee_id = plan.employee_id
 AND expire_ledger.tx_type = 'EXPIRE'
 AND expire_ledger.ref_table = 'att_employee_ph_ledger'
 AND expire_ledger.ref_id = plan.lot_ledger_id
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_cutover_migration_code
 AND audit_log.action_type = 'CREATE_POST_CUTOVER_EXPIRE'
 AND audit_log.finance_ledger_id = expire_ledger.id
WHERE audit_log.id IS NULL;

-- Validasi akhir dari data aktual. Jika ada USE tanpa lot atau expiry yang
-- masih tertunda, transaksi dibatalkan sebelum COMMIT.
DELETE FROM tmp_ph_finance_ledger_work;
INSERT INTO tmp_ph_finance_ledger_work (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  ledger.id,
  ledger.employee_id,
  ledger.tx_date,
  CAST(ledger.tx_type AS CHAR(20)),
  ROUND(ledger.qty_days, 2),
  ledger.expired_at,
  COALESCE(ledger.ref_table, ''),
  COALESCE(ledger.ref_id, 0)
FROM att_employee_ph_ledger ledger;

CALL sp_ph_finance_cutover_validate();
CALL sp_ph_core_cutover_assert('AFTER_EXPIRE', 1);

COMMIT;

-- ============================================================
-- HASIL SESUDAH APPLY
-- ============================================================
SELECT
  action_type,
  COUNT(*) AS total_mutasi,
  ROUND(SUM(CASE WHEN action_type = 'IMPORT_CORE_OPENING_LOT' THEN 1 ELSE 0 END), 0) AS total_lot_pembuka
FROM att_ph_cutover_migration_audit
WHERE migration_code = @ph_cutover_migration_code
GROUP BY action_type
ORDER BY action_type;

SELECT
  e.employee_code,
  e.employee_name,
  COALESCE(ledger_totals.total_grant, 0) AS total_grant,
  COALESCE(ledger_totals.total_use, 0) AS total_use,
  COALESCE(ledger_totals.total_expire, 0) AS total_expire,
  COALESCE(ledger_totals.total_adjust, 0) AS total_adjust,
  COALESCE(ledger_totals.saldo_ledger, 0) AS saldo_ledger,
  COALESCE(lot_totals.saldo_lot_aktif, 0) AS saldo_lot_aktif
FROM org_employee e
JOIN (
  SELECT DISTINCT employee_id
  FROM att_ph_cutover_migration_audit
  WHERE migration_code = @ph_cutover_migration_code
) migrated ON migrated.employee_id = e.id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT' THEN qty_days ELSE 0 END), 2) AS total_grant,
    ROUND(SUM(CASE WHEN tx_type = 'USE' THEN qty_days ELSE 0 END), 2) AS total_use,
    ROUND(SUM(CASE WHEN tx_type = 'EXPIRE' THEN qty_days ELSE 0 END), 2) AS total_expire,
    ROUND(SUM(CASE WHEN tx_type = 'ADJUST' THEN qty_days ELSE 0 END), 2) AS total_adjust,
    ROUND(
      SUM(CASE WHEN tx_type IN ('GRANT','ADJUST') THEN qty_days ELSE 0 END)
      - SUM(CASE WHEN tx_type IN ('USE','EXPIRE') THEN qty_days ELSE 0 END),
      2
    ) AS saldo_ledger
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) ledger_totals ON ledger_totals.employee_id = e.id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(qty_remaining), 2) AS saldo_lot_aktif
  FROM tmp_ph_finance_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) lot_totals ON lot_totals.employee_id = e.id
ORDER BY e.employee_name;

SELECT
  e.employee_code,
  e.employee_name,
  ledger.tx_date,
  ledger.tx_type,
  ledger.qty_days,
  ledger.void_reason,
  ledger.notes
FROM att_employee_ph_ledger ledger
JOIN org_employee e ON e.id = ledger.employee_id
WHERE ledger.tx_type = 'VOID'
  AND ledger.ref_table = 'att_daily'
  AND ledger.notes LIKE '%VOID cutover%'
ORDER BY ledger.tx_date, e.employee_name;

DROP PROCEDURE IF EXISTS sp_ph_core_cutover_assert;
DROP PROCEDURE IF EXISTS sp_ph_core_cutover_build;
DROP PROCEDURE IF EXISTS sp_ph_finance_cutover_validate;
