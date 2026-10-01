SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27e_preview_ph_core_cutover_opening_balance.sql
-- Tujuan :
-- 1) Preview saldo lot PH pembuka per 2026-06-01 dari database core
-- 2) Preview pembatalan USE yang keliru tercipta untuk April-Mei
-- 3) Membuktikan semua USE Finance setelah cutover dapat dialokasikan
--    dari saldo awal core + mutasi Finance yang sah
--
-- Catatan penting:
-- - READ ONLY: script ini hanya memakai TEMPORARY TABLE dan procedure
--   sementara. Tidak ada tabel atau ledger permanen yang diubah.
-- - Mapping pegawai memakai nama yang dinormalisasi, BUKAN employee_id.
-- - Satu nama core wajib cocok tepat ke satu nama Finance. Jika tidak,
--   apply migration nanti berhenti agar tidak salah pegawai.
-- - Cutover Finance ditetapkan pada 2026-06-01.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_prior_reconciliation_code := 'PH-HIST-20260827-V1';

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

-- 1. Konfigurasi yang akan dipakai pada apply.
SELECT
  @ph_cutover_date AS cutover_finance,
  @ph_as_of_date AS as_of_date,
  COALESCE((
    SELECT NULLIF(expiry_months, 0)
    FROM core.att_ph_policy
    WHERE is_active = 1
    ORDER BY id DESC LIMIT 1
  ), 3) AS expiry_months_dari_core,
  COALESCE((
    SELECT NULLIF(ph_expiry_months, 0)
    FROM att_attendance_policy
    WHERE is_active = 1
    ORDER BY id DESC LIMIT 1
  ), 3) AS expiry_months_finance_saat_ini;

-- 2. Penggunaan April-Mei yang harus dinetralkan karena terjadi sebelum
-- aplikasi Finance mulai dipakai.
SELECT
  e.employee_code,
  e.employee_name,
  plan.attendance_date,
  plan.qty_days,
  plan.finance_ledger_id,
  plan.source_daily_id
FROM tmp_ph_void_plan plan
JOIN org_employee e ON e.id = plan.employee_id
ORDER BY plan.attendance_date, e.employee_name;

-- 3. Lot saldo awal yang akan dibawa dari core dengan tanggal asal dan
-- tanggal berlaku sampai (expired_at) yang tetap sama.
SELECT
  plan.core_employee_name,
  e.employee_code AS finance_employee_code,
  e.employee_name AS finance_employee_name,
  plan.source_core_ledger_id AS core_lot_id,
  plan.source_grant_date,
  plan.expired_at AS berlaku_sampai,
  plan.qty_remaining AS saldo_lot_awal,
  plan.finance_name_matches,
  COALESCE(plan.finance_name_candidates, '-') AS kandidat_nama_finance
FROM tmp_ph_core_import_plan plan
LEFT JOIN org_employee e ON e.id = plan.finance_employee_id
ORDER BY plan.core_employee_name, plan.source_grant_date, plan.source_core_ledger_id;

-- 4. Semua bagian ini WAJIB kosong sebelum apply dijalankan.
SELECT 'CORE_LEDGER_ISSUE' AS problem_group, issue_type, core_employee_id AS employee_id,
       source_core_ledger_id AS source_id, tx_date, qty_days, details
FROM tmp_ph_core_cutover_issue
UNION ALL
SELECT 'NAME_MAPPING_ISSUE',
       CASE WHEN finance_name_matches = 0 THEN 'FINANCE_NAME_NOT_FOUND' ELSE 'FINANCE_NAME_AMBIGUOUS' END,
       core_employee_id,
       source_core_ledger_id,
       source_grant_date,
       qty_remaining,
       COALESCE(finance_name_candidates, '-')
FROM tmp_ph_core_import_plan
WHERE finance_name_matches <> 1
UNION ALL
SELECT 'FINANCE_LEDGER_ISSUE', issue_type, employee_id, ledger_id, tx_date, qty_days, details
FROM tmp_ph_finance_cutover_issue
ORDER BY problem_group, employee_id, tx_date, source_id;

-- 5. Rencana expire yang akan dicatat oleh apply agar saldo tampilan dan
-- guard jadwal tetap sama dengan lot efektif per hari ini.
SELECT
  e.employee_code,
  e.employee_name,
  plan.expired_at AS berlaku_sampai,
  plan.expire_tx_date AS tanggal_mutasi_expire,
  plan.qty_days AS qty_akan_expire,
  plan.lot_ledger_id
FROM tmp_ph_finance_expire_plan plan
JOIN org_employee e ON e.id = plan.employee_id
ORDER BY plan.expire_tx_date, e.employee_name, plan.lot_ledger_id;

-- 6. Proyeksi saldo aktif setelah seluruh lot expired dicatat. Nilai ini
-- menjadi acuan hasil apply; tidak ada USE setelah 1 Juni yang dibiarkan
-- tanpa sumber lot aktif.
SELECT
  e.employee_code,
  e.employee_name,
  ROUND(COALESCE(SUM(CASE
    WHEN lot.qty_remaining > 0.0001
     AND (lot.expired_at IS NULL OR lot.expired_at >= @ph_as_of_date)
    THEN lot.qty_remaining ELSE 0 END), 0), 2) AS proyeksi_saldo_ph_aktif,
  ROUND(COALESCE(SUM(CASE
    WHEN lot.qty_remaining > 0.0001
     AND lot.expired_at IS NOT NULL
     AND lot.expired_at < @ph_as_of_date
    THEN lot.qty_remaining ELSE 0 END), 0), 2) AS akan_dicatat_sebagai_expire
FROM org_employee e
LEFT JOIN tmp_ph_finance_lot_state lot ON lot.employee_id = e.id
GROUP BY e.id, e.employee_code, e.employee_name
HAVING proyeksi_saldo_ph_aktif > 0 OR akan_dicatat_sebagai_expire > 0
ORDER BY e.employee_name;

DROP PROCEDURE IF EXISTS sp_ph_core_cutover_build;
DROP PROCEDURE IF EXISTS sp_ph_finance_cutover_validate;
