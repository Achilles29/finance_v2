SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27h_apply_ph_core_cutover_v2_all_grant_policy.sql
-- Tujuan :
-- 1) Mengoreksi hasil 2026-08-27f sesuai kebijakan cutover V2.
-- 2) Menjadikan seluruh GRANT Core sebelum 1 Juni sebagai lot historis.
-- 3) Menghitung penggunaan hanya dari Finance mulai 1 Juni 2026.
-- 4) Menutup lot yang sudah melewati masa berlaku dengan EXPIRE berjejak.
--
-- Catatan penting:
-- - Script ini MENGGANTIKAN jejak import/expiry V1 secara append-only:
--   baris V1 diubah menjadi VOID, tidak dihapus.
-- - Gunakan setelah 2026-08-27f dan setelah preview 2026-08-27g bersih.
-- - Mapping pegawai tetap berdasarkan nama yang unik, bukan employee_id.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_v1_migration_code := 'PH-CORE-CUTOVER-20260601-V1';
SET @ph_v2_migration_code := 'PH-CORE-CUTOVER-20260601-V2';

SET @ph_standard_expiry_months := COALESCE((
  SELECT NULLIF(expiry_months, 0)
  FROM core.att_ph_policy
  WHERE is_active = 1
  ORDER BY id DESC
  LIMIT 1
), 3);
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_core_grant;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_name_map;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_import_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_replace_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_pre_cutover_use_plan;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_work_ledger;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_lot_state;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_expire_plan;
DROP PROCEDURE IF EXISTS sp_ph_v2_validate;

-- Kebijakan V2: seluruh hak GRANT Core dibawa sebagai lot asal. Mutasi USE
-- Core tidak lagi dipakai untuk mengurangi Finance karena Finance baru
-- berlaku sejak 1 Juni 2026.
CREATE TEMPORARY TABLE tmp_ph_v2_core_grant AS
SELECT
  ledger.id AS source_core_ledger_id,
  ledger.employee_id AS core_employee_id,
  employee.employee_name AS core_employee_name,
  ledger.tx_date AS source_grant_date,
  ROUND(ledger.qty_days, 2) AS qty_days,
  ledger.expired_at
FROM core.att_employee_ph_ledger ledger
JOIN core.org_employee employee ON employee.id = ledger.employee_id
WHERE ledger.tx_date < @ph_cutover_date
  AND UPPER(ledger.tx_type) = 'GRANT'
  AND COALESCE(ledger.qty_days, 0) > 0.0001
ORDER BY ledger.employee_id, ledger.tx_date, ledger.id;

CREATE TEMPORARY TABLE tmp_ph_v2_name_map AS
SELECT
  LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', '')) AS name_key,
  COUNT(*) AS finance_name_matches,
  MIN(id) AS finance_employee_id,
  GROUP_CONCAT(CONCAT(id, ':', employee_name) ORDER BY id SEPARATOR ' | ') AS finance_name_candidates
FROM org_employee
GROUP BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

CREATE TEMPORARY TABLE tmp_ph_v2_import_plan AS
SELECT
  core_grant.source_core_ledger_id,
  core_grant.core_employee_id,
  core_grant.core_employee_name,
  core_grant.source_grant_date,
  core_grant.qty_days,
  core_grant.expired_at,
  name_map.finance_employee_id,
  COALESCE(name_map.finance_name_matches, 0) AS finance_name_matches,
  name_map.finance_name_candidates
FROM tmp_ph_v2_core_grant core_grant
LEFT JOIN tmp_ph_v2_name_map name_map
  ON name_map.name_key = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(core_grant.core_employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

-- Baris V1 ini akan dinetralkan oleh apply V2. Preview mengeluarkannya dari
-- simulasi agar hasilnya sama dengan kondisi setelah koreksi nanti.
CREATE TEMPORARY TABLE tmp_ph_v2_replace_plan AS
SELECT DISTINCT
  audit_log.finance_ledger_id AS ledger_id,
  audit_log.action_type AS v1_action_type,
  ledger.employee_id,
  ledger.tx_type,
  ledger.qty_days,
  ledger.tx_date,
  ledger.ref_table,
  ledger.ref_id
FROM att_ph_cutover_migration_audit audit_log
JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
WHERE audit_log.migration_code = @ph_v1_migration_code
  AND audit_log.action_type IN ('IMPORT_CORE_OPENING_LOT', 'CREATE_POST_CUTOVER_EXPIRE')
  AND ledger.tx_type IN ('GRANT', 'EXPIRE');

-- Semua penggunaan Finance sebelum cutover harus tidak dihitung. Saat ini
-- sembilan baris lama semestinya sudah VOID oleh V1; query ini tetap ada
-- sebagai pengaman jika ada baris lain yang belum tertangani.
CREATE TEMPORARY TABLE tmp_ph_v2_pre_cutover_use_plan AS
SELECT
  ledger.id AS ledger_id,
  ledger.employee_id,
  ledger.tx_date,
  ledger.qty_days,
  ledger.ref_table,
  ledger.ref_id
FROM att_employee_ph_ledger ledger
WHERE ledger.tx_type = 'USE'
  AND ledger.tx_date < @ph_cutover_date;

CREATE TEMPORARY TABLE tmp_ph_v2_work_ledger AS
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
LEFT JOIN tmp_ph_v2_replace_plan replace_plan ON replace_plan.ledger_id = ledger.id
WHERE replace_plan.ledger_id IS NULL;

UPDATE tmp_ph_v2_work_ledger work
JOIN tmp_ph_v2_pre_cutover_use_plan old_use ON old_use.ledger_id = work.ledger_id
SET work.tx_type = 'VOID';

-- Synthetic ID hanya dipakai dalam temporary table. Ia membuat seluruh lot
-- Core bisa diuji bersama transaksi Finance tanpa menulis ledger.
INSERT INTO tmp_ph_v2_work_ledger (
  ledger_id, employee_id, tx_date, tx_type, qty_days, expired_at, ref_table, ref_id
)
SELECT
  8000000000 + plan.source_core_ledger_id,
  plan.finance_employee_id,
  plan.source_grant_date,
  'GRANT',
  plan.qty_days,
  plan.expired_at,
  'core.att_employee_ph_ledger',
  plan.source_core_ledger_id
FROM tmp_ph_v2_import_plan plan
WHERE plan.finance_name_matches = 1;

CREATE TEMPORARY TABLE tmp_ph_v2_lot_state (
  ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(20) NOT NULL,
  PRIMARY KEY (ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v2_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v2_expire_plan (
  lot_ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  expired_at DATE NOT NULL,
  expire_tx_date DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (lot_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_v2_validate()
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
    FROM tmp_ph_v2_work_ledger
    ORDER BY employee_id, tx_date, ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  DELETE FROM tmp_ph_v2_lot_state;
  DELETE FROM tmp_ph_v2_issue;
  DELETE FROM tmp_ph_v2_expire_plan;

  OPEN ledger_cursor;
  read_loop: LOOP
    FETCH ledger_cursor INTO v_ledger_id, v_employee_id, v_tx_date, v_tx_type,
      v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF COALESCE(v_qty, 0) <= 0.0001 THEN
      INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('FINANCE_NON_POSITIVE_QTY', v_employee_id, v_ledger_id, v_tx_date, v_qty,
              'Ledger memiliki qty PH nol atau negatif.');
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      INSERT INTO tmp_ph_v2_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
      VALUES (v_ledger_id, v_employee_id, v_tx_date, v_expired_at, v_qty, 'GRANT');
    ELSEIF v_tx_type = 'ADJUST' THEN
      INSERT INTO tmp_ph_v2_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
      VALUES (v_ledger_id, v_employee_id, v_tx_date, NULL, v_qty, 'ADJUST');
    ELSEIF v_tx_type = 'USE' THEN
      SET v_remaining = v_qty;
      consume_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.ledger_id
          FROM tmp_ph_v2_lot_state lot
          WHERE lot.employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;

        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('FINANCE_USE_NOT_COVERED_BY_ACTIVE_LOT', v_employee_id, v_ledger_id,
                  v_tx_date, v_remaining,
                  'USE sejak cutover tidak dapat ditutup oleh lot PH aktif secara FIFO.');
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available
          FROM tmp_ph_v2_lot_state
          WHERE ledger_id = v_lot_id;
          SET v_lot_available = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_v2_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
          WHERE ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
        END IF;
      END WHILE;
    ELSEIF v_tx_type = 'EXPIRE' THEN
      IF v_ref_table = 'att_employee_ph_ledger' AND COALESCE(v_ref_id, 0) > 0 THEN
        SELECT COALESCE((
          SELECT lot.qty_remaining
          FROM tmp_ph_v2_lot_state lot
          WHERE lot.ledger_id = v_ref_id
          LIMIT 1
        ), -1) INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('FINANCE_EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE menunjuk lot asal yang tidak ditemukan.');
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('FINANCE_EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE melebihi sisa lot asal.');
        ELSE
          UPDATE tmp_ph_v2_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('FINANCE_EXPIRE_WITHOUT_LOT_REF', v_employee_id, v_ledger_id, v_tx_date,
                v_qty, 'EXPIRE tidak menunjuk lot asal.');
      END IF;
    ELSEIF v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_v2_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('FINANCE_UNSUPPORTED_TX_TYPE', v_employee_id, v_ledger_id, v_tx_date,
              v_qty, CONCAT('Jenis ledger tidak dikenali: ', v_tx_type));
    END IF;
  END LOOP;
  CLOSE ledger_cursor;

  -- Semua lot yang sudah melewati "berlaku sampai" akan menjadi EXPIRE.
  -- Pada tanggal expired_at lot masih sah; expire dicatat satu hari sesudahnya.
  INSERT INTO tmp_ph_v2_expire_plan (
    lot_ledger_id, employee_id, expired_at, expire_tx_date, qty_days
  )
  SELECT
    lot.ledger_id,
    lot.employee_id,
    lot.expired_at,
    DATE_ADD(lot.expired_at, INTERVAL 1 DAY),
    ROUND(lot.qty_remaining, 2)
  FROM tmp_ph_v2_lot_state lot
  WHERE lot.qty_remaining > 0.0001
    AND lot.expired_at IS NOT NULL
    AND lot.expired_at < @ph_as_of_date;
END$$
DELIMITER ;

CALL sp_ph_v2_validate();


-- ============================================================
-- APPLY KOREKSI V2
-- ============================================================
DROP PROCEDURE IF EXISTS sp_ph_v2_assert;
DELIMITER $$
CREATE PROCEDURE sp_ph_v2_assert(IN p_stage VARCHAR(40), IN p_rollback_on_failure TINYINT)
BEGIN
  DECLARE v_name_issue INT DEFAULT 0;
  DECLARE v_finance_issue INT DEFAULT 0;
  DECLARE v_pending_expire INT DEFAULT 0;
  DECLARE v_v1_audit_rows INT DEFAULT 0;
  DECLARE v_message VARCHAR(255);

  SELECT COUNT(*) INTO v_name_issue
  FROM tmp_ph_v2_import_plan
  WHERE finance_name_matches <> 1;
  SELECT COUNT(*) INTO v_finance_issue FROM tmp_ph_v2_issue;
  SELECT COUNT(*) INTO v_pending_expire FROM tmp_ph_v2_expire_plan;
  SELECT COUNT(*) INTO v_v1_audit_rows
  FROM att_ph_cutover_migration_audit
  WHERE migration_code = @ph_v1_migration_code
    AND action_type = 'IMPORT_CORE_OPENING_LOT';

  IF v_v1_audit_rows = 0 OR v_name_issue > 0 OR v_finance_issue > 0
     OR (p_stage = 'AFTER_EXPIRE' AND v_pending_expire > 0) THEN
    IF p_rollback_on_failure = 1 THEN
      ROLLBACK;
    END IF;
    SET v_message = CONCAT(
      'PH cutover V2 ', p_stage,
      ' dibatalkan. v1=', v_v1_audit_rows,
      ', mapping=', v_name_issue,
      ', finance=', v_finance_issue,
      ', pending_expire=', v_pending_expire
    );
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
  END IF;
END$$
DELIMITER ;

CALL sp_ph_v2_assert('PRECHECK', 0);

START TRANSACTION;
SET @ph_now := NOW();

-- Selaraskan angka kebijakan dengan Core. Setiap lot tetap memakai tanggal
-- berlaku sampai yang tersimpan pada grant masing-masing.
UPDATE att_attendance_policy
SET ph_expiry_months = @ph_standard_expiry_months,
    updated_at = @ph_now
WHERE is_active = 1
  AND COALESCE(ph_expiry_months, 0) <> @ph_standard_expiry_months;

-- V1 tidak dihapus. Grant dan expiry V1 dijadikan VOID agar audit lama masih
-- dapat dibaca, sementara V2 membangun lot historis sesuai kebijakan baru.
INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_v2_migration_code,
  CASE WHEN plan.tx_type = 'GRANT' THEN 'VOID_V1_IMPORTED_GRANT' ELSE 'VOID_V1_EXPIRE' END,
  plan.employee_id,
  plan.ledger_id,
  NULL,
  NULL,
  CASE WHEN plan.tx_type = 'GRANT' THEN plan.ref_id ELSE NULL END,
  CONCAT('tx_type=', plan.tx_type, '; tx_date=', plan.tx_date, '; qty_days=', plan.qty_days),
  CONCAT('tx_type=VOID; tx_date=', plan.tx_date, '; qty_days=', plan.qty_days),
  'Jejak V1 dinetralkan oleh kebijakan cutover V2; tidak dihapus.',
  NULL,
  @ph_now
FROM tmp_ph_v2_replace_plan plan
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_v2_migration_code
 AND audit_log.finance_ledger_id = plan.ledger_id
 AND audit_log.action_type = CASE WHEN plan.tx_type = 'GRANT' THEN 'VOID_V1_IMPORTED_GRANT' ELSE 'VOID_V1_EXPIRE' END
WHERE audit_log.id IS NULL;

UPDATE att_employee_ph_ledger ledger
JOIN tmp_ph_v2_replace_plan plan ON plan.ledger_id = ledger.id
SET
  ledger.tx_type = 'VOID',
  ledger.entry_mode = 'MIGRATION',
  ledger.void_reason = 'Dinetralkan oleh migrasi cutover V2: lot/expiry V1 diganti oleh seluruh grant Core dan expiry sesuai tanggal lot.',
  ledger.voided_at = COALESCE(ledger.voided_at, @ph_now),
  ledger.voided_by = NULL,
  ledger.notes = LEFT(CONCAT_WS(' | ', NULLIF(ledger.notes, ''), 'VOID V2: diganti kebijakan seluruh grant Core, USE mulai 2026-06-01.'), 255),
  ledger.updated_at = @ph_now
WHERE ledger.tx_type IN ('GRANT', 'EXPIRE');

-- Bila masih ada USE Finance sebelum go-live, netralkan juga. Kondisi normal
-- setelah V1 adalah nol karena sembilan baris lama sudah VOID.
INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_v2_migration_code,
  'VOID_PRE_CUTOVER_USE_V2',
  plan.employee_id,
  plan.ledger_id,
  NULL,
  NULL,
  NULL,
  CONCAT('tx_type=USE; tx_date=', plan.tx_date, '; qty_days=', plan.qty_days),
  CONCAT('tx_type=VOID; tx_date=', plan.tx_date, '; qty_days=', plan.qty_days),
  'USE sebelum Finance go-live tidak dihitung oleh kebijakan cutover V2.',
  NULL,
  @ph_now
FROM tmp_ph_v2_pre_cutover_use_plan plan
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_v2_migration_code
 AND audit_log.action_type = 'VOID_PRE_CUTOVER_USE_V2'
 AND audit_log.finance_ledger_id = plan.ledger_id
WHERE audit_log.id IS NULL;

UPDATE att_employee_ph_ledger ledger
JOIN tmp_ph_v2_pre_cutover_use_plan plan ON plan.ledger_id = ledger.id
SET
  ledger.tx_type = 'VOID',
  ledger.entry_mode = 'MIGRATION',
  ledger.void_reason = 'Dinetralkan oleh migrasi cutover V2: penggunaan PH sebelum 2026-06-01 tidak dihitung di Finance.',
  ledger.voided_at = COALESCE(ledger.voided_at, @ph_now),
  ledger.voided_by = NULL,
  ledger.notes = LEFT(CONCAT_WS(' | ', NULLIF(ledger.notes, ''), 'VOID V2: USE sebelum go-live Finance.'), 255),
  ledger.updated_at = @ph_now
WHERE ledger.tx_type = 'USE';

-- Bawa setiap GRANT Core, bukan hanya sisa setelah USE Core. Pada kebijakan
-- ini, penggunaan dimulai dari Finance 1 Juni dan lot lama habis lewat FIFO
-- Finance atau EXPIRE sesuai tanggal berlaku sampai.
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
  'core.att_employee_ph_ledger',
  plan.source_core_ledger_id,
  'MIGRATION',
  LEFT(CONCAT(
    'Grant PH historis Core untuk cutover V2; lot #', plan.source_core_ledger_id,
    '; grant ', plan.source_grant_date,
    '; berlaku s.d. ', COALESCE(DATE_FORMAT(plan.expired_at, '%Y-%m-%d'), '-')
  ), 255),
  NULL,
  @ph_now,
  @ph_now
FROM tmp_ph_v2_import_plan plan
LEFT JOIN att_employee_ph_ledger existing_grant
  ON existing_grant.employee_id = plan.finance_employee_id
 AND existing_grant.tx_type = 'GRANT'
 AND existing_grant.ref_table = 'core.att_employee_ph_ledger'
 AND existing_grant.ref_id = plan.source_core_ledger_id
WHERE plan.finance_name_matches = 1
  AND existing_grant.id IS NULL;

INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_v2_migration_code,
  'IMPORT_CORE_GRANT_V2',
  ledger.employee_id,
  ledger.id,
  NULL,
  plan.core_employee_id,
  plan.source_core_ledger_id,
  NULL,
  CONCAT('qty_days=', ledger.qty_days, '; tx_date=', ledger.tx_date, '; expired_at=', COALESCE(ledger.expired_at, 'NULL')),
  'Semua grant Core sebelum 2026-06-01 dibawa sebagai lot V2 berdasarkan nama pegawai unik.',
  NULL,
  @ph_now
FROM tmp_ph_v2_import_plan plan
JOIN att_employee_ph_ledger ledger
  ON ledger.employee_id = plan.finance_employee_id
 AND ledger.tx_type = 'GRANT'
 AND ledger.ref_table = 'core.att_employee_ph_ledger'
 AND ledger.ref_id = plan.source_core_ledger_id
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_v2_migration_code
 AND audit_log.action_type = 'IMPORT_CORE_GRANT_V2'
 AND audit_log.source_core_ledger_id = plan.source_core_ledger_id
WHERE plan.finance_name_matches = 1
  AND audit_log.id IS NULL;

-- Bangun ulang simulasi dari ledger aktual setelah V1 dinetralkan dan seluruh
-- lot Core V2 dimasukkan. USE hanya berasal dari tanggal cutover dan setelahnya.
DELETE FROM tmp_ph_v2_work_ledger;
INSERT INTO tmp_ph_v2_work_ledger (
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
FROM att_employee_ph_ledger ledger
LEFT JOIN tmp_ph_v2_replace_plan replace_plan ON replace_plan.ledger_id = ledger.id
WHERE replace_plan.ledger_id IS NULL;

CALL sp_ph_v2_validate();
CALL sp_ph_v2_assert('AFTER_IMPORT', 1);

-- Masukkan expiry dari lot yang benar-benar tersisa dan telah lewat tanggal
-- berlaku. Tidak ada nominal yang dihapus; tiap EXPIRE menunjuk lot asal.
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
  plan.lot_ledger_id,
  'MIGRATION',
  'Expire V2: lot PH melewati tanggal berlaku sampai.',
  NULL,
  @ph_now,
  @ph_now
FROM tmp_ph_v2_expire_plan plan
LEFT JOIN att_employee_ph_ledger existing_expire
  ON existing_expire.employee_id = plan.employee_id
 AND existing_expire.tx_type = 'EXPIRE'
 AND existing_expire.ref_table = 'att_employee_ph_ledger'
 AND existing_expire.ref_id = plan.lot_ledger_id
WHERE existing_expire.id IS NULL;

INSERT INTO att_ph_cutover_migration_audit (
  migration_code, action_type, employee_id, finance_ledger_id,
  source_finance_daily_id, source_core_employee_id, source_core_ledger_id,
  before_snapshot, after_snapshot, notes, executed_by, executed_at
)
SELECT
  @ph_v2_migration_code,
  'CREATE_EXPIRE_V2',
  expire_ledger.employee_id,
  expire_ledger.id,
  NULL,
  NULL,
  plan.lot_ledger_id,
  CONCAT('lot_ledger_id=', plan.lot_ledger_id, '; berlaku_sampai=', plan.expired_at),
  CONCAT('tx_type=EXPIRE; qty_days=', expire_ledger.qty_days, '; tx_date=', expire_ledger.tx_date),
  'Expiry V2 dari lot yang masih tersisa setelah penggunaan Finance sejak cutover.',
  NULL,
  @ph_now
FROM tmp_ph_v2_expire_plan plan
JOIN att_employee_ph_ledger expire_ledger
  ON expire_ledger.employee_id = plan.employee_id
 AND expire_ledger.tx_type = 'EXPIRE'
 AND expire_ledger.ref_table = 'att_employee_ph_ledger'
 AND expire_ledger.ref_id = plan.lot_ledger_id
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_v2_migration_code
 AND audit_log.action_type = 'CREATE_EXPIRE_V2'
 AND audit_log.finance_ledger_id = expire_ledger.id
WHERE audit_log.id IS NULL;

-- Validasi akhir memakai ledger aktual. Pending expiry atau penggunaan tanpa
-- lot aktif menyebabkan seluruh transaksi apply dibatalkan.
DELETE FROM tmp_ph_v2_work_ledger;
INSERT INTO tmp_ph_v2_work_ledger (
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
FROM att_employee_ph_ledger ledger
LEFT JOIN tmp_ph_v2_replace_plan replace_plan ON replace_plan.ledger_id = ledger.id
WHERE replace_plan.ledger_id IS NULL;

CALL sp_ph_v2_validate();
CALL sp_ph_v2_assert('AFTER_EXPIRE', 1);

COMMIT;

-- ============================================================
-- HASIL SETELAH APPLY V2
-- ============================================================
SELECT action_type, COUNT(*) AS total_mutasi
FROM att_ph_cutover_migration_audit
WHERE migration_code = @ph_v2_migration_code
GROUP BY action_type
ORDER BY action_type;

SELECT
  employee.employee_code,
  employee.employee_name,
  COALESCE(core_metrics.grant_core_v2, 0) AS grant_core_v2,
  COALESCE(finance_metrics.grant_finance_setelah_cutover, 0) AS grant_finance,
  COALESCE(finance_metrics.use_finance_setelah_cutover, 0) AS penggunaan_finance,
  COALESCE(expire_metrics.expire_v2, 0) AS expiry_v2,
  COALESCE(ledger_metrics.saldo_ledger, 0) AS saldo_ledger,
  COALESCE(active_metrics.saldo_lot_aktif, 0) AS saldo_lot_aktif
FROM org_employee employee
JOIN (
  SELECT DISTINCT employee_id
  FROM att_ph_cutover_migration_audit
  WHERE migration_code = @ph_v2_migration_code
    AND action_type = 'IMPORT_CORE_GRANT_V2'
) migrated ON migrated.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_days), 2) AS grant_core_v2
  FROM att_employee_ph_ledger
  WHERE tx_type = 'GRANT'
    AND ref_table = 'core.att_employee_ph_ledger'
  GROUP BY employee_id
) core_metrics ON core_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT' AND tx_date >= @ph_cutover_date
                    AND COALESCE(ref_table, '') <> 'core.att_employee_ph_ledger' THEN qty_days ELSE 0 END), 2) AS grant_finance_setelah_cutover,
    ROUND(SUM(CASE WHEN tx_type = 'USE' AND tx_date >= @ph_cutover_date THEN qty_days ELSE 0 END), 2) AS use_finance_setelah_cutover
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) finance_metrics ON finance_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_days), 2) AS expire_v2
  FROM att_employee_ph_ledger
  WHERE tx_type = 'EXPIRE'
    AND entry_mode = 'MIGRATION'
    AND notes LIKE 'Expire V2:%'
  GROUP BY employee_id
) expire_metrics ON expire_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id,
    ROUND(SUM(CASE WHEN tx_type IN ('GRANT','ADJUST') THEN qty_days ELSE 0 END)
          - SUM(CASE WHEN tx_type IN ('USE','EXPIRE') THEN qty_days ELSE 0 END), 2) AS saldo_ledger
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) ledger_metrics ON ledger_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS saldo_lot_aktif
  FROM tmp_ph_v2_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) active_metrics ON active_metrics.employee_id = employee.id
ORDER BY employee.employee_name;

SELECT
  employee.employee_code,
  employee.employee_name,
  ledger.tx_date,
  ledger.tx_type,
  ledger.qty_days,
  ledger.ref_table,
  ledger.ref_id,
  ledger.void_reason,
  ledger.notes
FROM att_employee_ph_ledger ledger
JOIN org_employee employee ON employee.id = ledger.employee_id
WHERE ledger.entry_mode = 'MIGRATION'
  AND ledger.tx_type = 'VOID'
  AND ledger.notes LIKE '%VOID V2%'
ORDER BY employee.employee_name, ledger.tx_date, ledger.id;

DROP PROCEDURE IF EXISTS sp_ph_v2_assert;
DROP PROCEDURE IF EXISTS sp_ph_v2_validate;
