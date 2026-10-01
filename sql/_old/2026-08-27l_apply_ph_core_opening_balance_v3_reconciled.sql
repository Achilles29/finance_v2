SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27l_apply_ph_core_opening_balance_v3_reconciled.sql
-- Tujuan :
-- 1) Menerapkan saldo pembuka PH dari core.org_employee.ph.
-- 2) Menetralkan hasil migrasi V1/V2 tanpa menghapus riwayatnya.
-- 3) Mempertahankan PH historis yang sudah disetujui melalui adjustment
--    migrasi yang dapat diaudit.
-- 4) Membuat expiry dan audit akhir hanya setelah semua validasi lolos.
--
-- Catatan:
-- - Script ini mengubah ledger PH dan tabel audit saja.
-- - Tidak mengubah jadwal, presensi, payroll, atau data Core.
-- - Saldo core.org_employee.ph adalah angka pembuka authoritative.
-- - Jika saldo Core ada tetapi tidak ada lot historis, apply memakai
--   penanda fallback 1 Juni 2026 dan melaporkannya untuk audit.
-- - Koreksi historis tidak memberi hak baru: adjustment positif dipasangkan
--   dengan adjustment debit dari grant berikutnya bila grant tersebut ada.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_v1_migration_code := 'PH-CORE-CUTOVER-20260601-V1';
SET @ph_v2_migration_code := 'PH-CORE-CUTOVER-20260601-V2';
SET @ph_expiry_months := COALESCE(
  (
    SELECT NULLIF(ph_expiry_months, 0)
    FROM att_attendance_policy
    WHERE is_active = 1
    ORDER BY id DESC
    LIMIT 1
  ),
  (
    SELECT NULLIF(expiry_months, 0)
    FROM core.att_ph_policy
    WHERE is_active = 1
    ORDER BY id DESC
    LIMIT 1
  ),
  3
);

DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_name_map;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_opening_source;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_candidate_lot;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_opening_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_source_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_replace_seed;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_replace_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_pre_cutover_use;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_work_ledger;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_lot_state;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_expire_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_initial_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_exception_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_build_opening_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_build_exception_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_validate;

-- Mapping tetap defensif karena employee_id pada dua database tidak menjadi
-- dasar penghubung. Nama harus tepat dan unik setelah normalisasi.
CREATE TEMPORARY TABLE tmp_ph_v3_name_map AS
SELECT
  LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', '')) AS name_key,
  COUNT(*) AS finance_name_matches,
  MIN(id) AS finance_employee_id,
  GROUP_CONCAT(CONCAT(id, ':', employee_name) ORDER BY id SEPARATOR ' | ') AS finance_name_candidates
FROM org_employee
GROUP BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

-- Ini adalah sumber saldo pembuka authoritative. Nilai bukan penjumlahan
-- ulang ledger Core, melainkan kolom PH yang tersimpan pada master Core.
CREATE TEMPORARY TABLE tmp_ph_v3_opening_source AS
SELECT
  core_employee.id AS core_employee_id,
  core_employee.employee_name AS core_employee_name,
  ROUND(COALESCE(core_employee.ph, 0), 2) AS opening_qty,
  name_map.finance_employee_id,
  COALESCE(name_map.finance_name_matches, 0) AS finance_name_matches,
  name_map.finance_name_candidates
FROM core.org_employee core_employee
LEFT JOIN tmp_ph_v3_name_map name_map
  ON name_map.name_key = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(core_employee.employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''))
WHERE COALESCE(core_employee.ph, 0) <> 0;

-- Lot historis tidak menambah saldo. Ia hanya memberi tanggal asal dan expiry
-- bagi saldo pembuka yang memang tercatat pada core.org_employee.ph.
CREATE TEMPORARY TABLE tmp_ph_v3_candidate_lot AS
SELECT
  ledger.id AS source_core_ledger_id,
  ledger.employee_id AS core_employee_id,
  ledger.tx_date AS source_grant_date,
  ledger.expired_at,
  ROUND(ledger.qty_days, 2) AS qty_original,
  ROUND(ledger.qty_days, 2) AS qty_available
FROM core.att_employee_ph_ledger ledger
WHERE ledger.tx_date < @ph_cutover_date
  AND UPPER(ledger.tx_type) = 'GRANT'
  AND COALESCE(ledger.qty_days, 0) > 0.0001;

CREATE TEMPORARY TABLE tmp_ph_v3_opening_plan (
  plan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  core_employee_id BIGINT UNSIGNED NOT NULL,
  finance_employee_id BIGINT UNSIGNED NOT NULL,
  core_employee_name VARCHAR(150) NOT NULL,
  source_core_ledger_id BIGINT UNSIGNED NULL,
  source_grant_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  source_kind ENUM('CORE_LOT_REFERENCE','FALLBACK_CUTOVER') NOT NULL,
  PRIMARY KEY (plan_id),
  KEY idx_tmp_ph_v3_opening_plan_employee (finance_employee_id, source_grant_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v3_source_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  core_employee_id BIGINT UNSIGNED NULL,
  core_employee_name VARCHAR(150) NULL,
  finance_employee_id BIGINT UNSIGNED NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ambil lot terbaru lebih dahulu hanya untuk memberi penanda expiry pada
-- angka pembuka. Hal ini menjaga agar saldo pembuka tidak berubah menjadi
-- total seluruh grant Core yang sudah pernah terjadi.
DELIMITER $$
CREATE PROCEDURE sp_ph_v3_build_opening_plan()
BEGIN
  DECLARE v_done TINYINT DEFAULT 0;
  DECLARE v_core_employee_id BIGINT UNSIGNED;
  DECLARE v_core_employee_name VARCHAR(150);
  DECLARE v_opening_qty DECIMAL(8,2);
  DECLARE v_finance_employee_id BIGINT UNSIGNED;
  DECLARE v_finance_name_matches INT;
  DECLARE v_finance_name_candidates TEXT;
  DECLARE v_remaining DECIMAL(8,2);
  DECLARE v_candidate_id BIGINT UNSIGNED;
  DECLARE v_candidate_date DATE;
  DECLARE v_candidate_expired_at DATE;
  DECLARE v_candidate_available DECIMAL(8,2);
  DECLARE v_take DECIMAL(8,2);

  DECLARE opening_cursor CURSOR FOR
    SELECT core_employee_id, core_employee_name, opening_qty,
           finance_employee_id, finance_name_matches, finance_name_candidates
    FROM tmp_ph_v3_opening_source
    ORDER BY core_employee_name, core_employee_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  OPEN opening_cursor;
  read_loop: LOOP
    FETCH opening_cursor INTO v_core_employee_id, v_core_employee_name,
      v_opening_qty, v_finance_employee_id, v_finance_name_matches,
      v_finance_name_candidates;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF COALESCE(v_opening_qty, 0) <= 0.0001 THEN
      INSERT INTO tmp_ph_v3_source_issue (
        issue_type, core_employee_id, core_employee_name, finance_employee_id, qty_days, details
      ) VALUES (
        'CORE_OPENING_NON_POSITIVE', v_core_employee_id, v_core_employee_name,
        v_finance_employee_id, v_opening_qty,
        'Kolom core.org_employee.ph bernilai nol atau negatif.'
      );
      ITERATE read_loop;
    END IF;

    IF COALESCE(v_finance_name_matches, 0) <> 1 THEN
      INSERT INTO tmp_ph_v3_source_issue (
        issue_type, core_employee_id, core_employee_name, finance_employee_id, qty_days, details
      ) VALUES (
        CASE WHEN COALESCE(v_finance_name_matches, 0) = 0
             THEN 'FINANCE_NAME_NOT_FOUND' ELSE 'FINANCE_NAME_AMBIGUOUS' END,
        v_core_employee_id, v_core_employee_name, v_finance_employee_id,
        v_opening_qty, COALESCE(v_finance_name_candidates, '-')
      );
      ITERATE read_loop;
    END IF;

    SET v_remaining = v_opening_qty;
    allocate_loop: WHILE v_remaining > 0.0001 DO
      SELECT COALESCE((
        SELECT candidate.source_core_ledger_id
        FROM tmp_ph_v3_candidate_lot candidate
        WHERE candidate.core_employee_id = v_core_employee_id
          AND candidate.qty_available > 0.0001
        ORDER BY candidate.source_grant_date DESC, candidate.source_core_ledger_id DESC
        LIMIT 1
      ), 0) INTO v_candidate_id;

      IF v_candidate_id = 0 THEN
        INSERT INTO tmp_ph_v3_opening_plan (
          core_employee_id, finance_employee_id, core_employee_name,
          source_core_ledger_id, source_grant_date, expired_at, qty_days, source_kind
        ) VALUES (
          v_core_employee_id, v_finance_employee_id, v_core_employee_name,
          NULL, @ph_cutover_date,
          DATE_ADD(@ph_cutover_date, INTERVAL @ph_expiry_months MONTH),
          v_remaining, 'FALLBACK_CUTOVER'
        );
        INSERT INTO tmp_ph_v3_source_issue (
          issue_type, core_employee_id, core_employee_name, finance_employee_id, qty_days, details
        ) VALUES (
          'OPENING_WITHOUT_HISTORICAL_LOT', v_core_employee_id, v_core_employee_name,
          v_finance_employee_id, v_remaining,
          'Saldo Core ada, tetapi grant historis sebelum cutover tidak tersedia. Dipakai penanda cutover 2026-06-01.'
        );
        SET v_remaining = 0;
      ELSE
        SELECT source_grant_date, expired_at, qty_available
        INTO v_candidate_date, v_candidate_expired_at, v_candidate_available
        FROM tmp_ph_v3_candidate_lot
        WHERE source_core_ledger_id = v_candidate_id;

        SET v_take = LEAST(v_candidate_available, v_remaining);
        INSERT INTO tmp_ph_v3_opening_plan (
          core_employee_id, finance_employee_id, core_employee_name,
          source_core_ledger_id, source_grant_date, expired_at, qty_days, source_kind
        ) VALUES (
          v_core_employee_id, v_finance_employee_id, v_core_employee_name,
          v_candidate_id, v_candidate_date,
          COALESCE(v_candidate_expired_at, DATE_ADD(v_candidate_date, INTERVAL @ph_expiry_months MONTH)),
          v_take, 'CORE_LOT_REFERENCE'
        );
        UPDATE tmp_ph_v3_candidate_lot
        SET qty_available = ROUND(qty_available - v_take, 2)
        WHERE source_core_ledger_id = v_candidate_id;
        SET v_remaining = ROUND(v_remaining - v_take, 2);
      END IF;
    END WHILE;
  END LOOP;
  CLOSE opening_cursor;
END$$
DELIMITER ;

CALL sp_ph_v3_build_opening_plan();

-- Semua hasil migrasi V1/V2 dikeluarkan dari simulasi. V3 menggantikannya
-- dengan saldo pembuka authoritative dari core.org_employee.ph.
CREATE TEMPORARY TABLE tmp_ph_v3_replace_seed AS
SELECT DISTINCT audit_log.finance_ledger_id AS ledger_id
FROM att_ph_cutover_migration_audit audit_log
JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
WHERE (
  (audit_log.migration_code = @ph_v1_migration_code
   AND audit_log.action_type IN ('IMPORT_CORE_OPENING_LOT', 'CREATE_POST_CUTOVER_EXPIRE'))
  OR
  (audit_log.migration_code = @ph_v2_migration_code
   AND audit_log.action_type IN ('IMPORT_CORE_GRANT_V2', 'CREATE_EXPIRE_V2'))
)
  AND ledger.tx_type IN ('GRANT', 'EXPIRE');

CREATE TEMPORARY TABLE tmp_ph_v3_replace_plan AS
SELECT ledger_id FROM tmp_ph_v3_replace_seed
UNION
SELECT expire_ledger.id
FROM att_employee_ph_ledger expire_ledger
JOIN tmp_ph_v3_replace_seed seed ON seed.ledger_id = expire_ledger.ref_id
WHERE expire_ledger.tx_type = 'EXPIRE'
  AND expire_ledger.ref_table = 'att_employee_ph_ledger';

CREATE TEMPORARY TABLE tmp_ph_v3_pre_cutover_use AS
SELECT ledger.id AS ledger_id, ledger.employee_id, ledger.tx_date, ledger.qty_days
FROM att_employee_ph_ledger ledger
WHERE ledger.tx_type = 'USE'
  AND ledger.tx_date < @ph_cutover_date;

CREATE TEMPORARY TABLE tmp_ph_v3_work_ledger AS
SELECT
  ledger.id AS ledger_id,
  ledger.employee_id,
  ledger.tx_date,
  CAST(ledger.tx_type AS CHAR(20)) AS tx_type,
  ROUND(ledger.qty_days, 2) AS qty_days,
  ledger.expired_at,
  COALESCE(ledger.ref_table, '') AS ref_table,
  COALESCE(ledger.ref_id, 0) AS ref_id
FROM att_employee_ph_ledger ledger
LEFT JOIN tmp_ph_v3_replace_plan replace_plan ON replace_plan.ledger_id = ledger.id
WHERE replace_plan.ledger_id IS NULL;

UPDATE tmp_ph_v3_work_ledger work
JOIN tmp_ph_v3_pre_cutover_use old_use ON old_use.ledger_id = work.ledger_id
SET work.tx_type = 'VOID';

-- Synthetic ID hanya hidup di temporary table dan tidak pernah masuk ledger.
INSERT INTO tmp_ph_v3_work_ledger (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  7000000000 + plan.plan_id,
  plan.finance_employee_id,
  plan.source_grant_date,
  'GRANT',
  plan.qty_days,
  plan.expired_at,
  'core.org_employee.ph',
  COALESCE(plan.source_core_ledger_id, 0)
FROM tmp_ph_v3_opening_plan plan;

CREATE TEMPORARY TABLE tmp_ph_v3_lot_state (
  ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(30) NOT NULL,
  PRIMARY KEY (ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v3_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v3_expire_plan (
  lot_ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  expired_at DATE NOT NULL,
  expire_tx_date DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (lot_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_v3_validate()
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

  DECLARE ledger_cursor CURSOR FOR
    SELECT ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
    FROM tmp_ph_v3_work_ledger
    ORDER BY employee_id, tx_date, ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  DELETE FROM tmp_ph_v3_lot_state;
  DELETE FROM tmp_ph_v3_issue;
  DELETE FROM tmp_ph_v3_expire_plan;

  OPEN ledger_cursor;
  read_loop: LOOP
    FETCH ledger_cursor INTO v_ledger_id, v_employee_id, v_tx_date, v_tx_type,
      v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF ABS(COALESCE(v_qty, 0)) <= 0.0001 THEN
      INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('NON_POSITIVE_QTY', v_employee_id, v_ledger_id, v_tx_date, v_qty,
              'Ledger memiliki qty PH nol.');
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      IF v_qty < 0 THEN
        INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('NEGATIVE_GRANT', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                'GRANT tidak boleh bernilai negatif.');
        ITERATE read_loop;
      END IF;
      INSERT INTO tmp_ph_v3_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
      VALUES (v_ledger_id, v_employee_id, v_tx_date, v_expired_at, v_qty, 'GRANT');
    ELSEIF v_tx_type = 'ADJUST' THEN
      IF v_qty > 0 THEN
        INSERT INTO tmp_ph_v3_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
        VALUES (v_ledger_id, v_employee_id, v_tx_date, NULL, v_qty, 'ADJUST');
      ELSE
        SET v_remaining = ABS(v_qty);
        consume_adjust_loop: WHILE v_remaining > 0.0001 DO
          SELECT COALESCE((
            SELECT lot.ledger_id
            FROM tmp_ph_v3_lot_state lot
            WHERE lot.employee_id = v_employee_id
              AND lot.tx_date <= v_tx_date
              AND lot.qty_remaining > 0.0001
              AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
            ORDER BY lot.tx_date, lot.ledger_id
            LIMIT 1
          ), 0) INTO v_lot_id;

          IF v_lot_id = 0 THEN
            INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
            VALUES ('ADJUST_DEBIT_NOT_COVERED', v_employee_id, v_ledger_id,
                    v_tx_date, v_remaining,
                    'Adjustment debit tidak dapat dialokasikan ke lot PH aktif.');
            SET v_remaining = 0;
          ELSE
            SELECT qty_remaining INTO v_lot_available
            FROM tmp_ph_v3_lot_state
            WHERE ledger_id = v_lot_id;
            SET v_lot_available = LEAST(v_lot_available, v_remaining);
            UPDATE tmp_ph_v3_lot_state
            SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
            WHERE ledger_id = v_lot_id;
            SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
          END IF;
        END WHILE;
      END IF;
    ELSEIF v_tx_type = 'USE' THEN
      IF v_qty < 0 THEN
        INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('NEGATIVE_USE', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                'USE tidak boleh bernilai negatif.');
        ITERATE read_loop;
      END IF;
      SET v_remaining = v_qty;
      consume_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.ledger_id
          FROM tmp_ph_v3_lot_state lot
          WHERE lot.employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;

        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('USE_NOT_COVERED_BY_ACTIVE_LOT', v_employee_id, v_ledger_id,
                  v_tx_date, v_remaining,
                  'USE Finance tidak memiliki saldo PH aktif dari sumber pembuka atau Finance.');
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available
          FROM tmp_ph_v3_lot_state
          WHERE ledger_id = v_lot_id;
          SET v_lot_available = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_v3_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
          WHERE ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
        END IF;
      END WHILE;
    ELSEIF v_tx_type = 'EXPIRE' THEN
      IF v_qty < 0 THEN
        INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('NEGATIVE_EXPIRE', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                'EXPIRE tidak boleh bernilai negatif.');
        ITERATE read_loop;
      END IF;
      IF v_ref_table = 'att_employee_ph_ledger' AND COALESCE(v_ref_id, 0) > 0 THEN
        SELECT COALESCE((
          SELECT lot.qty_remaining
          FROM tmp_ph_v3_lot_state lot
          WHERE lot.ledger_id = v_ref_id
          LIMIT 1
        ), -1) INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE tidak menemukan lot asal.');
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE melebihi sisa lot asal.');
        ELSE
          UPDATE tmp_ph_v3_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('EXPIRE_WITHOUT_LOT_REF', v_employee_id, v_ledger_id, v_tx_date,
                v_qty, 'EXPIRE tidak menunjuk lot asal.');
      END IF;
    ELSEIF v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_v3_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('UNSUPPORTED_TX_TYPE', v_employee_id, v_ledger_id, v_tx_date,
              v_qty, CONCAT('Jenis ledger tidak dikenali: ', v_tx_type));
    END IF;
  END LOOP;
  CLOSE ledger_cursor;

  -- Kebijakan Finance: expired_at berarti "berlaku sampai". Lot baru
  -- ditutup pada hari berikutnya agar tetap dapat dipakai di hari terakhir.
  INSERT INTO tmp_ph_v3_expire_plan (
    lot_ledger_id, employee_id, expired_at, expire_tx_date, qty_days
  )
  SELECT
    lot.ledger_id,
    lot.employee_id,
    lot.expired_at,
    DATE_ADD(lot.expired_at, INTERVAL 1 DAY),
    ROUND(lot.qty_remaining, 2)
  FROM tmp_ph_v3_lot_state lot
  WHERE lot.qty_remaining > 0.0001
    AND lot.expired_at IS NOT NULL
    AND lot.expired_at < @ph_as_of_date;
END$$
DELIMITER ;

CALL sp_ph_v3_validate();

-- Penggunaan PH lama yang telah disetujui tidak dihapus dari presensi. Bila
-- ada grant Finance setelahnya, adjustment positif sebelum pemakaian akan
-- ditutup kembali oleh debit pada tanggal grant tersebut. Bila tidak ada
-- grant berikutnya (contoh Bagas), adjustment tetap dicatat terang sebagai
-- koreksi saldo pembuka historis agar saldo tidak menjadi minus.
CREATE TEMPORARY TABLE tmp_ph_v3_initial_issue AS
SELECT * FROM tmp_ph_v3_issue;

CREATE TEMPORARY TABLE tmp_ph_v3_exception_plan (
  exception_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id BIGINT UNSIGNED NOT NULL,
  use_ledger_id BIGINT UNSIGNED NOT NULL,
  use_date DATE NOT NULL,
  advance_date DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  settlement_grant_ledger_id BIGINT UNSIGNED NULL,
  settlement_date DATE NULL,
  reconciliation_kind ENUM('SETTLED_BY_LATER_GRANT','PERMANENT_OPENING_ADJUST') NOT NULL,
  details VARCHAR(255) NOT NULL,
  PRIMARY KEY (exception_id),
  UNIQUE KEY uk_tmp_ph_v3_exception_use (use_ledger_id),
  KEY idx_tmp_ph_v3_exception_settlement (settlement_grant_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_v3_build_exception_plan()
BEGIN
  DECLARE v_done TINYINT DEFAULT 0;
  DECLARE v_employee_id BIGINT UNSIGNED;
  DECLARE v_use_ledger_id BIGINT UNSIGNED;
  DECLARE v_use_date DATE;
  DECLARE v_qty DECIMAL(8,2);
  DECLARE v_settlement_grant_id BIGINT UNSIGNED;
  DECLARE v_settlement_date DATE;

  DECLARE issue_cursor CURSOR FOR
    SELECT issue.employee_id, issue.ledger_id, issue.tx_date, issue.qty_days
    FROM tmp_ph_v3_initial_issue issue
    WHERE issue.issue_type = 'USE_NOT_COVERED_BY_ACTIVE_LOT'
    ORDER BY issue.employee_id, issue.tx_date, issue.ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  OPEN issue_cursor;
  read_loop: LOOP
    FETCH issue_cursor INTO v_employee_id, v_use_ledger_id, v_use_date, v_qty;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    SELECT COALESCE((
      SELECT future_grant.ledger_id
      FROM tmp_ph_v3_work_ledger future_grant
      WHERE future_grant.employee_id = v_employee_id
        AND future_grant.tx_type = 'GRANT'
        AND future_grant.tx_date > v_use_date
        AND COALESCE(future_grant.ref_table, '') NOT IN ('core.org_employee.ph', 'core.att_employee_ph_ledger')
        AND future_grant.qty_days > COALESCE((
          SELECT SUM(plan.qty_days)
          FROM tmp_ph_v3_exception_plan plan
          WHERE plan.settlement_grant_ledger_id = future_grant.ledger_id
        ), 0) + 0.0001
      ORDER BY future_grant.tx_date, future_grant.ledger_id
      LIMIT 1
    ), 0) INTO v_settlement_grant_id;

    IF v_settlement_grant_id > 0 THEN
      SELECT tx_date INTO v_settlement_date
      FROM tmp_ph_v3_work_ledger
      WHERE ledger_id = v_settlement_grant_id;
      INSERT INTO tmp_ph_v3_exception_plan (
        employee_id, use_ledger_id, use_date, advance_date, qty_days,
        settlement_grant_ledger_id, settlement_date, reconciliation_kind, details
      ) VALUES (
        v_employee_id, v_use_ledger_id, v_use_date,
        GREATEST(@ph_cutover_date, DATE_SUB(v_use_date, INTERVAL 1 DAY)), v_qty,
        v_settlement_grant_id, v_settlement_date, 'SETTLED_BY_LATER_GRANT',
        'PH historis sudah disetujui sebelum guard; ditutup oleh hak Finance yang diperoleh setelahnya.'
      );
    ELSE
      INSERT INTO tmp_ph_v3_exception_plan (
        employee_id, use_ledger_id, use_date, advance_date, qty_days,
        settlement_grant_ledger_id, settlement_date, reconciliation_kind, details
      ) VALUES (
        v_employee_id, v_use_ledger_id, v_use_date,
        GREATEST(@ph_cutover_date, DATE_SUB(v_use_date, INTERVAL 1 DAY)), v_qty,
        NULL, NULL, 'PERMANENT_OPENING_ADJUST',
        'PH historis sudah disetujui sebelum guard; tidak ada grant berikutnya untuk menutupnya sehingga dicatat sebagai koreksi saldo pembuka.'
      );
    END IF;
  END LOOP;
  CLOSE issue_cursor;
END$$
DELIMITER ;

CALL sp_ph_v3_build_exception_plan();

INSERT INTO tmp_ph_v3_work_ledger (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  6000000000 + (plan.exception_id * 2),
  plan.employee_id,
  plan.advance_date,
  'ADJUST',
  plan.qty_days,
  NULL,
  'att_ph_v3_historical_use',
  plan.use_ledger_id
FROM tmp_ph_v3_exception_plan plan;

INSERT INTO tmp_ph_v3_work_ledger (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  6000000000 + (plan.exception_id * 2) + 1,
  plan.employee_id,
  plan.settlement_date,
  'ADJUST',
  -plan.qty_days,
  NULL,
  'att_ph_v3_historical_settle',
  plan.use_ledger_id
FROM tmp_ph_v3_exception_plan plan
WHERE plan.settlement_grant_ledger_id IS NOT NULL;

-- Validasi kedua wajib tidak memiliki masalah FIFO. Ini yang nantinya
-- menjadi preflight sebelum apply V3 diizinkan menulis ledger.
CALL sp_ph_v3_validate();

-- ============================================================
-- APPLY V3
-- ============================================================
-- Script ini hanya boleh dijalankan setelah preview 2026-08-27k menunjukkan
-- temuan_fifo_setelah_koreksi = 0. Semua perubahan berada dalam transaksi;
-- V1/V2 tidak dihapus, tetapi di-VOID agar jejak lama tetap dapat diaudit.

SET @ph_v3_migration_code := 'PH-CORE-CUTOVER-20260601-V3';
SET @ph_apply_now := NOW();

CREATE TABLE IF NOT EXISTS att_ph_cutover_v3_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_code VARCHAR(80) NOT NULL,
  action_type VARCHAR(60) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  finance_ledger_id BIGINT UNSIGNED NULL,
  related_finance_ledger_id BIGINT UNSIGNED NULL,
  source_core_employee_id BIGINT UNSIGNED NULL,
  source_core_ledger_id BIGINT UNSIGNED NULL,
  source_use_ledger_id BIGINT UNSIGNED NULL,
  before_snapshot TEXT NULL,
  after_snapshot TEXT NULL,
  notes VARCHAR(255) NULL,
  executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_att_ph_cutover_v3_audit_action_ledger (migration_code, action_type, finance_ledger_id),
  KEY idx_att_ph_cutover_v3_audit_employee (employee_id, executed_at),
  KEY idx_att_ph_cutover_v3_audit_source_core (source_core_employee_id, source_core_ledger_id),
  KEY idx_att_ph_cutover_v3_audit_source_use (source_use_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Audit migrasi saldo pembuka PH V3 dari core.org_employee.ph';

DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3_actual_expire_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_apply_assert;
DELIMITER $$
CREATE PROCEDURE sp_ph_v3_apply_assert(IN p_stage VARCHAR(40), IN p_rollback_on_failure TINYINT)
BEGIN
  DECLARE v_mapping_issue INT DEFAULT 0;
  DECLARE v_fifo_issue INT DEFAULT 0;
  DECLARE v_expire_orphan INT DEFAULT 0;
  DECLARE v_already_applied INT DEFAULT 0;
  DECLARE v_message VARCHAR(255);

  SELECT COUNT(*) INTO v_mapping_issue
  FROM tmp_ph_v3_source_issue
  WHERE issue_type IN ('CORE_OPENING_NON_POSITIVE', 'FINANCE_NAME_NOT_FOUND', 'FINANCE_NAME_AMBIGUOUS');
  SELECT COUNT(*) INTO v_fifo_issue FROM tmp_ph_v3_issue;
  SELECT COUNT(*) INTO v_already_applied
  FROM att_ph_cutover_v3_audit
  WHERE migration_code = @ph_v3_migration_code
    AND action_type = 'APPLY_COMPLETED';

  IF p_stage = 'POST_APPLY' THEN
    SELECT COUNT(*) INTO v_expire_orphan
    FROM tmp_ph_v3_actual_expire_plan
    WHERE actual_lot_ledger_id IS NULL;
  END IF;

  IF v_already_applied > 0 OR v_mapping_issue > 0 OR v_fifo_issue > 0 OR v_expire_orphan > 0 THEN
    IF p_rollback_on_failure = 1 THEN
      ROLLBACK;
    END IF;
    SET v_message = CONCAT(
      'PH V3 ', p_stage, ' dibatalkan. already=', v_already_applied,
      ', mapping=', v_mapping_issue,
      ', fifo=', v_fifo_issue,
      ', expire_orphan=', v_expire_orphan
    );
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
  END IF;
END$$
DELIMITER ;

-- Seluruh write diletakkan dalam satu procedure/transaction. Klien MySQL
-- kadang tetap membaca baris setelah SIGNAL; dengan bentuk ini, kegagalan
-- preflight tidak dapat meneruskan satu pun perubahan ledger di bawahnya.
DROP PROCEDURE IF EXISTS sp_ph_v3_execute_apply;
DELIMITER $$
CREATE PROCEDURE sp_ph_v3_execute_apply()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;
  -- Preflight memakai simulasi akhir di atas. Fallback tanggal lot tetap
  -- boleh dipakai karena jumlahnya dari field PH Core yang authoritative.
  CALL sp_ph_v3_apply_assert('PREFLIGHT', 1);

-- 1. Rekam dan netralkan hasil V1/V2 yang salah sumber saldo awalnya.
INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  before_snapshot, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'VOID_V1_V2_LEDGER',
  ledger.employee_id,
  ledger.id,
  CONCAT('tx_type=', ledger.tx_type, '; tx_date=', ledger.tx_date, '; qty=', ledger.qty_days),
  CONCAT('tx_type=VOID; tx_date=', ledger.tx_date, '; qty=', ledger.qty_days),
  'Baris migrasi V1/V2 digantikan saldo pembuka V3 dari core.org_employee.ph.',
  @ph_apply_now
FROM att_employee_ph_ledger ledger
JOIN tmp_ph_v3_replace_plan plan ON plan.ledger_id = ledger.id
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'VOID_V1_V2_LEDGER'
 AND audit_log.finance_ledger_id = ledger.id
WHERE ledger.tx_type IN ('GRANT', 'EXPIRE')
  AND audit_log.id IS NULL;

UPDATE att_employee_ph_ledger ledger
JOIN tmp_ph_v3_replace_plan plan ON plan.ledger_id = ledger.id
SET
  ledger.tx_type = 'VOID',
  ledger.entry_mode = 'MIGRATION',
  ledger.void_reason = LEFT(CONCAT(
    'Digantikan oleh migrasi PH V3: saldo pembuka memakai core.org_employee.ph. Sumber lama ',
    ledger.ref_table, '#', COALESCE(ledger.ref_id, 0), '.'
  ), 255),
  ledger.voided_at = COALESCE(ledger.voided_at, @ph_apply_now),
  ledger.notes = LEFT(CONCAT_WS(' | ', NULLIF(ledger.notes, ''), 'VOID V3: sumber saldo pembuka V1/V2 tidak dipakai.'), 255),
  -- V1 dan V2 dapat memiliki source lot sama. Setelah menjadi VOID, pakai
  -- identitas arsip per ledger agar unique key tidak berbenturan.
  ledger.ref_table = 'att_ph_v3_void_archive',
  ledger.ref_id = ledger.id,
  ledger.updated_at = @ph_apply_now
WHERE ledger.tx_type IN ('GRANT', 'EXPIRE');

-- 2. Seluruh penggunaan sebelum Finance mulai dipakai tidak mengurangi
-- saldo Finance. Baris lama tetap ada sebagai VOID untuk audit.
INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_use_ledger_id, before_snapshot, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'VOID_PRE_CUTOVER_USE',
  ledger.employee_id,
  ledger.id,
  ledger.id,
  CONCAT('tx_type=USE; tx_date=', ledger.tx_date, '; qty=', ledger.qty_days),
  CONCAT('tx_type=VOID; tx_date=', ledger.tx_date, '; qty=', ledger.qty_days),
  'Penggunaan sebelum 2026-06-01 tidak boleh mengurangi saldo Finance.',
  @ph_apply_now
FROM att_employee_ph_ledger ledger
JOIN tmp_ph_v3_pre_cutover_use plan ON plan.ledger_id = ledger.id
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'VOID_PRE_CUTOVER_USE'
 AND audit_log.finance_ledger_id = ledger.id
WHERE ledger.tx_type = 'USE'
  AND audit_log.id IS NULL;

UPDATE att_employee_ph_ledger ledger
JOIN tmp_ph_v3_pre_cutover_use plan ON plan.ledger_id = ledger.id
SET
  ledger.tx_type = 'VOID',
  ledger.entry_mode = 'MIGRATION',
  ledger.void_reason = 'Penggunaan PH terjadi sebelum cutover Finance 2026-06-01.',
  ledger.voided_at = COALESCE(ledger.voided_at, @ph_apply_now),
  ledger.notes = LEFT(CONCAT_WS(' | ', NULLIF(ledger.notes, ''), 'VOID V3: penggunaan sebelum Finance cutover.'), 255),
  ledger.updated_at = @ph_apply_now
WHERE ledger.tx_type = 'USE';

-- 3. Buat saldo pembuka satu kali per lot referensi. Jumlahnya berasal dari
-- core.org_employee.ph; lot Core hanya membawa tanggal asal dan expiry.
INSERT INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  plan.finance_employee_id,
  plan.source_grant_date,
  'GRANT',
  plan.qty_days,
  plan.expired_at,
  CASE WHEN plan.source_core_ledger_id IS NULL THEN 'core.org_employee.ph' ELSE 'core.att_employee_ph_ledger' END,
  CASE WHEN plan.source_core_ledger_id IS NULL THEN plan.core_employee_id ELSE plan.source_core_ledger_id END,
  'MIGRATION',
  LEFT(CONCAT(
    'Saldo awal PH V3 dari core.org_employee.ph=', plan.qty_days,
    '; referensi ', plan.source_kind,
    CASE WHEN plan.source_core_ledger_id IS NULL THEN '' ELSE CONCAT(' lot Core #', plan.source_core_ledger_id) END,
    '; berlaku s.d. ', COALESCE(DATE_FORMAT(plan.expired_at, '%Y-%m-%d'), '-')
  ), 255),
  NULL,
  @ph_apply_now,
  @ph_apply_now
FROM tmp_ph_v3_opening_plan plan
LEFT JOIN att_employee_ph_ledger existing_ledger
  ON existing_ledger.employee_id = plan.finance_employee_id
 AND existing_ledger.tx_type = 'GRANT'
 AND existing_ledger.ref_table = CASE WHEN plan.source_core_ledger_id IS NULL THEN 'core.org_employee.ph' ELSE 'core.att_employee_ph_ledger' END
 AND existing_ledger.ref_id = CASE WHEN plan.source_core_ledger_id IS NULL THEN plan.core_employee_id ELSE plan.source_core_ledger_id END
WHERE existing_ledger.id IS NULL;

INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_core_employee_id, source_core_ledger_id, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'IMPORT_OPENING_BALANCE',
  ledger.employee_id,
  ledger.id,
  plan.core_employee_id,
  plan.source_core_ledger_id,
  CONCAT('qty=', ledger.qty_days, '; tx_date=', ledger.tx_date, '; expired_at=', COALESCE(ledger.expired_at, 'NULL')),
  'Saldo awal authoritative dari core.org_employee.ph.',
  @ph_apply_now
FROM tmp_ph_v3_opening_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.finance_employee_id
 AND ledger.tx_type = 'GRANT'
 AND ledger.ref_table = CASE WHEN plan.source_core_ledger_id IS NULL THEN 'core.org_employee.ph' ELSE 'core.att_employee_ph_ledger' END
 AND ledger.ref_id = CASE WHEN plan.source_core_ledger_id IS NULL THEN plan.core_employee_id ELSE plan.source_core_ledger_id END
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'IMPORT_OPENING_BALANCE'
 AND audit_log.finance_ledger_id = ledger.id
WHERE audit_log.id IS NULL;

-- 4. Pertahankan PH historis yang telah disetujui. Koreksi positif tersedia
-- sebelum tanggal pakai; untuk Fadilla debitnya ditautkan ke grant berikutnya.
INSERT INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  plan.employee_id,
  plan.advance_date,
  'ADJUST',
  plan.qty_days,
  NULL,
  'att_ph_v3_historical_use',
  plan.use_ledger_id,
  'MIGRATION',
  LEFT(CONCAT('Koreksi PH historis untuk penggunaan ledger #', plan.use_ledger_id, ': ', plan.details), 255),
  NULL,
  @ph_apply_now,
  @ph_apply_now
FROM tmp_ph_v3_exception_plan plan
LEFT JOIN att_employee_ph_ledger existing_ledger
  ON existing_ledger.employee_id = plan.employee_id
 AND existing_ledger.tx_type = 'ADJUST'
 AND existing_ledger.ref_table = 'att_ph_v3_historical_use'
 AND existing_ledger.ref_id = plan.use_ledger_id
WHERE existing_ledger.id IS NULL;

INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_use_ledger_id, related_finance_ledger_id, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'CREATE_HISTORICAL_ADVANCE',
  ledger.employee_id,
  ledger.id,
  plan.use_ledger_id,
  plan.settlement_grant_ledger_id,
  CONCAT('qty=', ledger.qty_days, '; tx_date=', ledger.tx_date),
  plan.details,
  @ph_apply_now
FROM tmp_ph_v3_exception_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.employee_id
 AND ledger.tx_type = 'ADJUST'
 AND ledger.ref_table = 'att_ph_v3_historical_use'
 AND ledger.ref_id = plan.use_ledger_id
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'CREATE_HISTORICAL_ADVANCE'
 AND audit_log.finance_ledger_id = ledger.id
WHERE audit_log.id IS NULL;

INSERT INTO att_employee_ph_ledger (
  employee_id, tx_date, tx_type, qty_days, expired_at,
  ref_table, ref_id, entry_mode, notes, created_by, created_at, updated_at
)
SELECT
  plan.employee_id,
  plan.settlement_date,
  'ADJUST',
  -plan.qty_days,
  NULL,
  'att_ph_v3_historical_settle',
  plan.use_ledger_id,
  'MIGRATION',
  LEFT(CONCAT('Penutupan koreksi PH historis ledger #', plan.use_ledger_id, ' oleh grant Finance #', plan.settlement_grant_ledger_id, '.'), 255),
  NULL,
  @ph_apply_now,
  @ph_apply_now
FROM tmp_ph_v3_exception_plan plan
LEFT JOIN att_employee_ph_ledger existing_ledger
  ON existing_ledger.employee_id = plan.employee_id
 AND existing_ledger.tx_type = 'ADJUST'
 AND existing_ledger.ref_table = 'att_ph_v3_historical_settle'
 AND existing_ledger.ref_id = plan.use_ledger_id
WHERE plan.settlement_grant_ledger_id IS NOT NULL
  AND existing_ledger.id IS NULL;

INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_use_ledger_id, related_finance_ledger_id, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'SETTLE_HISTORICAL_ADVANCE',
  ledger.employee_id,
  ledger.id,
  plan.use_ledger_id,
  plan.settlement_grant_ledger_id,
  CONCAT('qty=', ledger.qty_days, '; tx_date=', ledger.tx_date),
  'Debit migrasi menutup koreksi PH historis tanpa menambah saldo akhir.',
  @ph_apply_now
FROM tmp_ph_v3_exception_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.employee_id
 AND ledger.tx_type = 'ADJUST'
 AND ledger.ref_table = 'att_ph_v3_historical_settle'
 AND ledger.ref_id = plan.use_ledger_id
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'SETTLE_HISTORICAL_ADVANCE'
 AND audit_log.finance_ledger_id = ledger.id
WHERE plan.settlement_grant_ledger_id IS NOT NULL
  AND audit_log.id IS NULL;

-- 5. Ubah rencana expiry simulasi ke ID ledger nyata, lalu catat EXPIRE.
CREATE TEMPORARY TABLE tmp_ph_v3_actual_expire_plan AS
SELECT
  expire_plan.employee_id,
  expire_plan.expired_at,
  expire_plan.expire_tx_date,
  expire_plan.qty_days,
  CASE
    WHEN opening_plan.plan_id IS NULL THEN expire_plan.lot_ledger_id
    ELSE opening_ledger.id
  END AS actual_lot_ledger_id
FROM tmp_ph_v3_expire_plan expire_plan
LEFT JOIN tmp_ph_v3_opening_plan opening_plan
  ON expire_plan.lot_ledger_id = 7000000000 + opening_plan.plan_id
LEFT JOIN att_employee_ph_ledger opening_ledger
  ON opening_ledger.employee_id = opening_plan.finance_employee_id
 AND opening_ledger.tx_type = 'GRANT'
 AND opening_ledger.ref_table = CASE WHEN opening_plan.source_core_ledger_id IS NULL THEN 'core.org_employee.ph' ELSE 'core.att_employee_ph_ledger' END
 AND opening_ledger.ref_id = CASE WHEN opening_plan.source_core_ledger_id IS NULL THEN opening_plan.core_employee_id ELSE opening_plan.source_core_ledger_id END;

INSERT INTO att_employee_ph_ledger (
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
  plan.actual_lot_ledger_id,
  'MIGRATION',
  'Expire V3: saldo opening atau lot PH telah melewati tanggal berlaku.',
  NULL,
  @ph_apply_now,
  @ph_apply_now
FROM tmp_ph_v3_actual_expire_plan plan
LEFT JOIN att_employee_ph_ledger existing_ledger
  ON existing_ledger.employee_id = plan.employee_id
 AND existing_ledger.tx_type = 'EXPIRE'
 AND existing_ledger.ref_table = 'att_employee_ph_ledger'
 AND existing_ledger.ref_id = plan.actual_lot_ledger_id
WHERE plan.actual_lot_ledger_id IS NOT NULL
  AND existing_ledger.id IS NULL;

INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  related_finance_ledger_id, after_snapshot, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'CREATE_EXPIRE',
  ledger.employee_id,
  ledger.id,
  plan.actual_lot_ledger_id,
  CONCAT('qty=', ledger.qty_days, '; tx_date=', ledger.tx_date),
  'Expiry V3 mengikuti tanggal berlaku lot pembuka/ledger.',
  @ph_apply_now
FROM tmp_ph_v3_actual_expire_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.employee_id
 AND ledger.tx_type = 'EXPIRE'
 AND ledger.ref_table = 'att_employee_ph_ledger'
 AND ledger.ref_id = plan.actual_lot_ledger_id
LEFT JOIN att_ph_cutover_v3_audit audit_log
  ON audit_log.migration_code = @ph_v3_migration_code
 AND audit_log.action_type = 'CREATE_EXPIRE'
 AND audit_log.finance_ledger_id = ledger.id
WHERE plan.actual_lot_ledger_id IS NOT NULL
  AND audit_log.id IS NULL;

-- 6. Validasi ulang dari ledger nyata sebelum commit. Tidak ada satu pun
-- USE, debit adjustment, atau EXPIRE yang boleh tersisa tanpa lot asal.
DELETE FROM tmp_ph_v3_work_ledger;
INSERT INTO tmp_ph_v3_work_ledger (
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

CALL sp_ph_v3_validate();
CALL sp_ph_v3_apply_assert('POST_APPLY', 1);

INSERT INTO att_ph_cutover_v3_audit (
  migration_code, action_type, notes, executed_at
)
SELECT
  @ph_v3_migration_code,
  'APPLY_COMPLETED',
  'Migrasi saldo pembuka PH V3 selesai dan lolos validasi ledger akhir.',
  @ph_apply_now
WHERE NOT EXISTS (
  SELECT 1
  FROM att_ph_cutover_v3_audit audit_log
  WHERE audit_log.migration_code = @ph_v3_migration_code
    AND audit_log.action_type = 'APPLY_COMPLETED'
);

  COMMIT;
END$$
DELIMITER ;

CALL sp_ph_v3_execute_apply();

-- ============================================================
-- HASIL SINGKAT SESUDAH APPLY
-- ============================================================
SELECT
  action_type,
  COUNT(*) AS total_baris_audit
FROM att_ph_cutover_v3_audit
WHERE migration_code = @ph_v3_migration_code
GROUP BY action_type
ORDER BY action_type;

SELECT
  COUNT(DISTINCT source_use_ledger_id) AS total_penggunaan_historis_diselaraskan
FROM att_ph_cutover_v3_audit
WHERE migration_code = @ph_v3_migration_code
  AND action_type IN ('CREATE_HISTORICAL_ADVANCE', 'SETTLE_HISTORICAL_ADVANCE');

SELECT
  employee.employee_code,
  employee.employee_name,
  ROUND(SUM(CASE WHEN ledger.tx_type = 'GRANT' THEN ledger.qty_days ELSE 0 END), 2) AS total_grant,
  ROUND(SUM(CASE WHEN ledger.tx_type = 'USE' THEN ledger.qty_days ELSE 0 END), 2) AS penggunaan,
  ROUND(SUM(CASE WHEN ledger.tx_type = 'EXPIRE' THEN ledger.qty_days ELSE 0 END), 2) AS expired,
  ROUND(SUM(CASE WHEN ledger.tx_type = 'ADJUST' THEN ledger.qty_days ELSE 0 END), 2) AS koreksi_bersih,
  ROUND(
    SUM(CASE WHEN ledger.tx_type IN ('GRANT', 'ADJUST') THEN ledger.qty_days ELSE 0 END)
    - SUM(CASE WHEN ledger.tx_type IN ('USE', 'EXPIRE') THEN ledger.qty_days ELSE 0 END),
    2
  ) AS saldo_ledger
FROM org_employee employee
JOIN att_employee_ph_ledger ledger ON ledger.employee_id = employee.id
WHERE employee.employee_name IN ('FAIRUZ SABRI RAFIF', 'BAGAS BHAKTI .R', 'FADILLA HARTONO PUTRI')
GROUP BY employee.id, employee.employee_code, employee.employee_name
ORDER BY employee.employee_name;

DROP PROCEDURE IF EXISTS sp_ph_v3_execute_apply;
DROP PROCEDURE IF EXISTS sp_ph_v3_apply_assert;
DROP PROCEDURE IF EXISTS sp_ph_v3_build_opening_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_build_exception_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_validate;
