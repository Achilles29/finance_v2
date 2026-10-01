SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27k_preview_ph_core_field_opening_balance_v3_reconciled.sql
-- Tujuan :
-- 1) Menguji saldo pembuka PH dari core.org_employee.ph.
-- 2) Tidak menghitung ulang semua grant Core sebagai saldo pembuka.
-- 3) Menggunakan lot Core terbaru hanya sebagai penanda tanggal/expiry.
-- 4) Menghitung USE Finance mulai 1 Juni 2026 dan expiry dengan aturan
--    Finance: lot masih berlaku pada tanggal expired_at.
-- 5) Menyusun rekonsiliasi berjejak untuk PH historis yang sudah disetujui
--    sebelum guard saldo diterapkan.
--
-- Catatan:
-- - Script ini tidak mengubah ledger, jadwal, presensi, atau saldo permanen.
-- - Saldo core.org_employee.ph adalah angka pembuka authoritative.
-- - Jika saldo Core ada tetapi tidak ada lot historis, preview membuat
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
-- RINGKASAN PREVIEW V3
-- ============================================================
SELECT
  @ph_cutover_date AS cutover_finance,
  @ph_as_of_date AS tanggal_pemeriksaan,
  @ph_expiry_months AS masa_berlaku_bulan,
  (SELECT COUNT(*) FROM tmp_ph_v3_opening_source WHERE opening_qty > 0.0001) AS pegawai_dengan_saldo_core,
  (SELECT ROUND(SUM(opening_qty), 2) FROM tmp_ph_v3_opening_source WHERE opening_qty > 0.0001) AS total_saldo_awal_core,
  (SELECT COUNT(*) FROM tmp_ph_v3_opening_plan WHERE source_kind = 'CORE_LOT_REFERENCE') AS lot_referensi_historis,
  (SELECT COUNT(*) FROM tmp_ph_v3_opening_plan WHERE source_kind = 'FALLBACK_CUTOVER') AS lot_fallback_cutover,
  (SELECT COUNT(*) FROM tmp_ph_v3_replace_plan) AS baris_v1_v2_yang_akan_dinetralkan,
  (SELECT COUNT(*) FROM tmp_ph_v3_pre_cutover_use) AS use_sebelum_cutover_yang_tetap_void,
  (SELECT COUNT(*) FROM tmp_ph_v3_source_issue) AS temuan_sumber_saldo,
  (SELECT COUNT(*) FROM tmp_ph_v3_initial_issue WHERE issue_type = 'USE_NOT_COVERED_BY_ACTIVE_LOT') AS penggunaan_historis_yang_direkonsiliasi,
  (SELECT COUNT(*) FROM tmp_ph_v3_exception_plan WHERE reconciliation_kind = 'SETTLED_BY_LATER_GRANT') AS koreksi_ditutup_grant_berikutnya,
  (SELECT COUNT(*) FROM tmp_ph_v3_exception_plan WHERE reconciliation_kind = 'PERMANENT_OPENING_ADJUST') AS koreksi_saldo_pembuka_tetap,
  (SELECT COUNT(*) FROM tmp_ph_v3_issue) AS temuan_fifo_setelah_koreksi;

-- Seluruh pegawai yang memiliki saldo awal Core, termasuk saldo estimasi
-- setelah penggunaan Finance dan expiry diterapkan.
SELECT
  employee.employee_code,
  employee.employee_name,
  source.opening_qty AS saldo_awal_core,
  ROUND(SUM(plan.qty_days), 2) AS lot_pembuka_terbentuk,
  SUM(CASE WHEN plan.source_kind = 'FALLBACK_CUTOVER' THEN 1 ELSE 0 END) AS lot_fallback,
  COALESCE(finance_metrics.grant_finance, 0) AS grant_finance_sejak_juni,
  COALESCE(finance_metrics.use_finance, 0) AS penggunaan_finance_sejak_juni,
  COALESCE(exception_metrics.total_koreksi, 0) AS koreksi_historis,
  COALESCE(expire_metrics.qty_akan_expire, 0) AS expiry_yang_akan_dibuat,
  COALESCE(active_metrics.active_qty, 0) AS estimasi_saldo_aktif,
  CASE
    WHEN source.finance_name_matches <> 1 THEN 'PERLU REVIEW: mapping nama'
    WHEN COALESCE(fifo_metrics.total_issue, 0) > 0 THEN 'PERLU REVIEW: penggunaan melebihi saldo'
    WHEN SUM(CASE WHEN plan.source_kind = 'FALLBACK_CUTOVER' THEN 1 ELSE 0 END) > 0 THEN 'PERLU REVIEW: tanggal lot fallback'
    ELSE 'AMAN'
  END AS status_preview
FROM tmp_ph_v3_opening_source source
JOIN org_employee employee ON employee.id = source.finance_employee_id
LEFT JOIN tmp_ph_v3_opening_plan plan ON plan.finance_employee_id = employee.id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT'
                    AND tx_date >= @ph_cutover_date
                    AND COALESCE(ref_table, '') <> 'core.org_employee.ph'
              THEN qty_days ELSE 0 END), 2) AS grant_finance,
    ROUND(SUM(CASE WHEN tx_type = 'USE' AND tx_date >= @ph_cutover_date THEN qty_days ELSE 0 END), 2) AS use_finance
  FROM tmp_ph_v3_work_ledger
  GROUP BY employee_id
) finance_metrics ON finance_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_days), 2) AS total_koreksi
  FROM tmp_ph_v3_exception_plan
  GROUP BY employee_id
) exception_metrics ON exception_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_days), 2) AS qty_akan_expire
  FROM tmp_ph_v3_expire_plan
  GROUP BY employee_id
) expire_metrics ON expire_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS active_qty
  FROM tmp_ph_v3_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) active_metrics ON active_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_issue
  FROM tmp_ph_v3_issue
  GROUP BY employee_id
) fifo_metrics ON fifo_metrics.employee_id = employee.id
WHERE source.finance_name_matches = 1
GROUP BY
  employee.id, employee.employee_code, employee.employee_name,
  source.opening_qty, source.finance_name_matches,
  finance_metrics.grant_finance, finance_metrics.use_finance,
  exception_metrics.total_koreksi,
  expire_metrics.qty_akan_expire, active_metrics.active_qty,
  fifo_metrics.total_issue
ORDER BY employee.employee_name;

-- Rincian asal saldo pembuka. Ini menjelaskan lot mana yang dipakai sebagai
-- penanda untuk angka pada core.org_employee.ph.
SELECT
  employee.employee_code,
  employee.employee_name,
  plan.qty_days AS saldo_yang_dibawa,
  plan.source_kind,
  plan.source_core_ledger_id AS core_lot_referensi,
  plan.source_grant_date AS tanggal_asal,
  plan.expired_at AS berlaku_sampai,
  COALESCE(lot.qty_remaining, 0) AS sisa_setelah_penggunaan,
  CASE
    WHEN COALESCE(lot.qty_remaining, 0) <= 0.0001 THEN 'SUDAH DIPAKAI'
    WHEN plan.expired_at < @ph_as_of_date THEN 'AKAN EXPIRE'
    ELSE 'MASIH AKTIF'
  END AS status_lot
FROM tmp_ph_v3_opening_plan plan
JOIN org_employee employee ON employee.id = plan.finance_employee_id
LEFT JOIN tmp_ph_v3_lot_state lot ON lot.ledger_id = 7000000000 + plan.plan_id
ORDER BY employee.employee_name, plan.source_grant_date, plan.plan_id;

-- Jejak setiap PH lama yang membutuhkan perlakuan khusus. Ini akan menjadi
-- dasar audit permanen saat apply V3 dijalankan.
SELECT
  employee.employee_code,
  employee.employee_name,
  plan.use_ledger_id AS ledger_penggunaan,
  plan.use_date AS tanggal_ph,
  plan.advance_date AS tanggal_koreksi,
  plan.qty_days,
  plan.reconciliation_kind,
  grant_ledger.tx_date AS tanggal_grant_penyelesaian,
  plan.details
FROM tmp_ph_v3_exception_plan plan
JOIN org_employee employee ON employee.id = plan.employee_id
LEFT JOIN tmp_ph_v3_work_ledger grant_ledger ON grant_ledger.ledger_id = plan.settlement_grant_ledger_id
ORDER BY employee.employee_name, plan.use_date, plan.use_ledger_id;

-- Bagian FIFO harus kosong sebelum apply V3. SOURCE_ISSUE fallback tetap
-- dilaporkan agar asal tanggalnya transparan, tetapi bukan alasan menolak
-- saldo opening yang memang tercatat resmi di Core.
SELECT 'SOURCE_ISSUE' AS problem_group,
       issue_type,
       core_employee_name AS target,
       qty_days,
       details
FROM tmp_ph_v3_source_issue
UNION ALL
SELECT 'FIFO_ISSUE',
       issue_type,
       COALESCE(employee.employee_name, CONCAT('employee #', issue.employee_id)),
       issue.qty_days,
       issue.details
FROM tmp_ph_v3_issue issue
LEFT JOIN org_employee employee ON employee.id = issue.employee_id
ORDER BY problem_group, target, issue_type;

DROP PROCEDURE IF EXISTS sp_ph_v3_build_opening_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_build_exception_plan;
DROP PROCEDURE IF EXISTS sp_ph_v3_validate;
