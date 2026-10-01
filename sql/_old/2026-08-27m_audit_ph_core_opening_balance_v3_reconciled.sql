SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27m_audit_ph_core_opening_balance_v3_reconciled.sql
-- Tujuan :
-- 1) Audit baca-saja hasil migrasi PH V3.
-- 2) Membuktikan saldo opening mengikuti core.org_employee.ph.
-- 3) Memeriksa FIFO USE, adjustment debit, expiry, dan sisa lot aktif.
-- 4) Menampilkan jejak koreksi historis tanpa mengubah data apa pun.
--
-- Prasyarat:
-- - Jalankan setelah 2026-08-27l_apply_ph_core_opening_balance_v3_reconciled.sql.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_v3_migration_code := 'PH-CORE-CUTOVER-20260601-V3';

DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_core_source;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_name_map;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_work_ledger;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_lot_state;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v3a_expire_pending;
DROP PROCEDURE IF EXISTS sp_ph_v3a_validate;

CREATE TEMPORARY TABLE tmp_ph_v3a_name_map AS
SELECT
  LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', '')) AS name_key,
  COUNT(*) AS finance_name_matches,
  MIN(id) AS finance_employee_id,
  GROUP_CONCAT(CONCAT(id, ':', employee_name) ORDER BY id SEPARATOR ' | ') AS finance_name_candidates
FROM org_employee
GROUP BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

CREATE TEMPORARY TABLE tmp_ph_v3a_core_source AS
SELECT
  core_employee.id AS core_employee_id,
  core_employee.employee_name AS core_employee_name,
  ROUND(COALESCE(core_employee.ph, 0), 2) AS opening_qty,
  name_map.finance_employee_id,
  COALESCE(name_map.finance_name_matches, 0) AS finance_name_matches,
  name_map.finance_name_candidates
FROM core.org_employee core_employee
LEFT JOIN tmp_ph_v3a_name_map name_map
  ON name_map.name_key = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(core_employee.employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''))
WHERE COALESCE(core_employee.ph, 0) <> 0;

CREATE TEMPORARY TABLE tmp_ph_v3a_work_ledger AS
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

CREATE TEMPORARY TABLE tmp_ph_v3a_lot_state (
  ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(20) NOT NULL,
  PRIMARY KEY (ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v3a_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v3a_expire_pending (
  lot_ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  expired_at DATE NOT NULL,
  expire_tx_date DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (lot_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_v3a_validate()
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
    FROM tmp_ph_v3a_work_ledger
    ORDER BY employee_id, tx_date, ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  DELETE FROM tmp_ph_v3a_lot_state;
  DELETE FROM tmp_ph_v3a_issue;
  DELETE FROM tmp_ph_v3a_expire_pending;

  OPEN ledger_cursor;
  read_loop: LOOP
    FETCH ledger_cursor INTO v_ledger_id, v_employee_id, v_tx_date, v_tx_type,
      v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF ABS(COALESCE(v_qty, 0)) <= 0.0001 THEN
      INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('ZERO_QTY', v_employee_id, v_ledger_id, v_tx_date, v_qty, 'Ledger memiliki qty nol.');
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      IF v_qty < 0 THEN
        INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('NEGATIVE_GRANT', v_employee_id, v_ledger_id, v_tx_date, v_qty, 'GRANT tidak boleh negatif.');
      ELSE
        INSERT INTO tmp_ph_v3a_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
        VALUES (v_ledger_id, v_employee_id, v_tx_date, v_expired_at, v_qty, 'GRANT');
      END IF;
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'ADJUST' THEN
      IF v_qty > 0 THEN
        INSERT INTO tmp_ph_v3a_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
        VALUES (v_ledger_id, v_employee_id, v_tx_date, NULL, v_qty, 'ADJUST');
      ELSE
        SET v_remaining = ABS(v_qty);
        adjust_loop: WHILE v_remaining > 0.0001 DO
          SELECT COALESCE((
            SELECT lot.ledger_id
            FROM tmp_ph_v3a_lot_state lot
            WHERE lot.employee_id = v_employee_id
              AND lot.tx_date <= v_tx_date
              AND lot.qty_remaining > 0.0001
              AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
            ORDER BY lot.tx_date, lot.ledger_id
            LIMIT 1
          ), 0) INTO v_lot_id;
          IF v_lot_id = 0 THEN
            INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
            VALUES ('ADJUST_DEBIT_NOT_COVERED', v_employee_id, v_ledger_id, v_tx_date, v_remaining,
                    'Adjustment debit tidak dapat dialokasikan ke lot aktif.');
            SET v_remaining = 0;
          ELSE
            SELECT qty_remaining INTO v_lot_available FROM tmp_ph_v3a_lot_state WHERE ledger_id = v_lot_id;
            SET v_lot_available = LEAST(v_lot_available, v_remaining);
            UPDATE tmp_ph_v3a_lot_state
            SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
            WHERE ledger_id = v_lot_id;
            SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
          END IF;
        END WHILE;
      END IF;
      ITERATE read_loop;
    END IF;

    IF v_qty < 0 AND v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('NEGATIVE_NON_ADJUST', v_employee_id, v_ledger_id, v_tx_date, v_qty,
              CONCAT(v_tx_type, ' tidak boleh negatif.'));
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'USE' THEN
      SET v_remaining = v_qty;
      use_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.ledger_id
          FROM tmp_ph_v3a_lot_state lot
          WHERE lot.employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;
        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('USE_NOT_COVERED', v_employee_id, v_ledger_id, v_tx_date, v_remaining,
                  'USE tidak memiliki lot PH aktif pada tanggal transaksi.');
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available FROM tmp_ph_v3a_lot_state WHERE ledger_id = v_lot_id;
          SET v_lot_available = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_v3a_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
          WHERE ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
        END IF;
      END WHILE;
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'EXPIRE' THEN
      IF v_ref_table = 'att_employee_ph_ledger' AND v_ref_id > 0 THEN
        SELECT COALESCE((SELECT qty_remaining FROM tmp_ph_v3a_lot_state WHERE ledger_id = v_ref_id LIMIT 1), -1)
        INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                  'EXPIRE tidak menemukan lot asal.');
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                  'EXPIRE melebihi sisa lot asal.');
        ELSE
          UPDATE tmp_ph_v3a_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('EXPIRE_WITHOUT_REF', v_employee_id, v_ledger_id, v_tx_date, v_qty,
                'EXPIRE tidak memiliki referensi lot.');
      END IF;
      ITERATE read_loop;
    END IF;

    IF v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_v3a_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('UNSUPPORTED_TYPE', v_employee_id, v_ledger_id, v_tx_date, v_qty,
              CONCAT('Jenis mutasi tidak dikenali: ', v_tx_type));
    END IF;
  END LOOP;
  CLOSE ledger_cursor;

  INSERT INTO tmp_ph_v3a_expire_pending (
    lot_ledger_id, employee_id, expired_at, expire_tx_date, qty_days
  )
  SELECT
    lot.ledger_id,
    lot.employee_id,
    lot.expired_at,
    DATE_ADD(lot.expired_at, INTERVAL 1 DAY),
    ROUND(lot.qty_remaining, 2)
  FROM tmp_ph_v3a_lot_state lot
  WHERE lot.qty_remaining > 0.0001
    AND lot.expired_at IS NOT NULL
    AND lot.expired_at < @ph_as_of_date;
END$$
DELIMITER ;

CALL sp_ph_v3a_validate();

-- Ringkasan status audit.
SELECT
  @ph_as_of_date AS tanggal_pemeriksaan,
  (SELECT COUNT(*) FROM att_ph_cutover_v3_audit WHERE migration_code = @ph_v3_migration_code AND action_type = 'APPLY_COMPLETED') AS apply_v3_selesai,
  (SELECT COUNT(*) FROM tmp_ph_v3a_issue) AS temuan_integritas,
  (SELECT COUNT(*) FROM tmp_ph_v3a_expire_pending) AS expiry_yang_belum_tercatat,
  (SELECT COUNT(*)
   FROM att_ph_cutover_migration_audit legacy_audit
   JOIN att_employee_ph_ledger ledger ON ledger.id = legacy_audit.finance_ledger_id
   WHERE legacy_audit.migration_code IN ('PH-CORE-CUTOVER-20260601-V1', 'PH-CORE-CUTOVER-20260601-V2')
     AND legacy_audit.action_type IN ('IMPORT_CORE_OPENING_LOT', 'CREATE_POST_CUTOVER_EXPIRE', 'IMPORT_CORE_GRANT_V2', 'CREATE_EXPIRE_V2')
     AND ledger.tx_type <> 'VOID') AS legacy_v1_v2_masih_aktif;

-- Pemeriksaan seluruh pegawai yang memiliki saldo awal Core.
SELECT
  employee.employee_code,
  employee.employee_name,
  source.opening_qty AS saldo_awal_core,
  COALESCE(imported.opening_v3, 0) AS saldo_awal_v3,
  COALESCE(ledger_total.total_grant, 0) AS grant_total,
  COALESCE(ledger_total.total_use, 0) AS penggunaan_total,
  COALESCE(ledger_total.total_expire, 0) AS expired_total,
  COALESCE(ledger_total.total_adjust, 0) AS koreksi_bersih,
  COALESCE(ledger_total.saldo_ledger, 0) AS saldo_ledger,
  COALESCE(active.active_lot, 0) AS saldo_lot_aktif,
  COALESCE(exception_log.total_historical_uses, 0) AS penggunaan_historis_diselaraskan,
  CASE
    WHEN source.finance_name_matches <> 1 THEN 'GAGAL: mapping nama'
    WHEN ABS(COALESCE(imported.opening_v3, 0) - source.opening_qty) > 0.0001 THEN 'GAGAL: saldo opening berbeda'
    WHEN COALESCE(issue_count.total_issue, 0) > 0 THEN 'GAGAL: integritas ledger'
    WHEN ABS(COALESCE(ledger_total.saldo_ledger, 0) - COALESCE(active.active_lot, 0)) > 0.0001 THEN 'GAGAL: saldo ledger dan lot berbeda'
    WHEN COALESCE(imported.fallback_count, 0) > 0 THEN 'AMAN: fallback tanggal cutover'
    ELSE 'AMAN'
  END AS status_audit
FROM tmp_ph_v3a_core_source source
JOIN org_employee employee ON employee.id = source.finance_employee_id
LEFT JOIN (
  SELECT
    audit_log.employee_id,
    ROUND(SUM(ledger.qty_days), 2) AS opening_v3,
    SUM(CASE WHEN ledger.ref_table = 'core.org_employee.ph' THEN 1 ELSE 0 END) AS fallback_count
  FROM att_ph_cutover_v3_audit audit_log
  JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
  WHERE audit_log.migration_code = @ph_v3_migration_code
    AND audit_log.action_type = 'IMPORT_OPENING_BALANCE'
    AND ledger.tx_type = 'GRANT'
  GROUP BY audit_log.employee_id
) imported ON imported.employee_id = employee.id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT' THEN qty_days ELSE 0 END), 2) AS total_grant,
    ROUND(SUM(CASE WHEN tx_type = 'USE' THEN qty_days ELSE 0 END), 2) AS total_use,
    ROUND(SUM(CASE WHEN tx_type = 'EXPIRE' THEN qty_days ELSE 0 END), 2) AS total_expire,
    ROUND(SUM(CASE WHEN tx_type = 'ADJUST' THEN qty_days ELSE 0 END), 2) AS total_adjust,
    ROUND(SUM(CASE WHEN tx_type IN ('GRANT', 'ADJUST') THEN qty_days ELSE 0 END)
          - SUM(CASE WHEN tx_type IN ('USE', 'EXPIRE') THEN qty_days ELSE 0 END), 2) AS saldo_ledger
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) ledger_total ON ledger_total.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS active_lot
  FROM tmp_ph_v3a_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) active ON active.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_issue
  FROM tmp_ph_v3a_issue
  GROUP BY employee_id
) issue_count ON issue_count.employee_id = employee.id
LEFT JOIN (
  -- Satu penggunaan Fadilla menghasilkan dua mutasi: advance dan
  -- settlement. Hitung nomor USE yang unik agar tidak tampak sebagai
  -- dua hari PH terpisah pada laporan audit.
  SELECT employee_id, COUNT(DISTINCT source_use_ledger_id) AS total_historical_uses
  FROM att_ph_cutover_v3_audit
  WHERE migration_code = @ph_v3_migration_code
    AND action_type IN ('CREATE_HISTORICAL_ADVANCE', 'SETTLE_HISTORICAL_ADVANCE')
  GROUP BY employee_id
) exception_log ON exception_log.employee_id = employee.id
WHERE source.finance_name_matches = 1
ORDER BY employee.employee_name;

-- Contoh kunci yang harus sesuai dengan keputusan bisnis V3.
SELECT
  employee.employee_code,
  employee.employee_name,
  ledger.tx_date,
  ledger.tx_type,
  ledger.qty_days,
  ledger.expired_at,
  ledger.entry_mode,
  ledger.ref_table,
  ledger.ref_id,
  ledger.notes
FROM att_employee_ph_ledger ledger
JOIN org_employee employee ON employee.id = ledger.employee_id
WHERE employee.employee_name IN ('FAIRUZ SABRI RAFIF', 'BAGAS BHAKTI .R', 'FADILLA HARTONO PUTRI')
  AND ledger.tx_type <> 'VOID'
ORDER BY employee.employee_name, ledger.tx_date, ledger.id;

-- Semua hasil di bawah harus kosong untuk menyatakan audit bersih.
SELECT
  'LEDGER_ISSUE' AS problem_group,
  issue.issue_type,
  employee.employee_name,
  issue.ledger_id,
  issue.tx_date,
  issue.qty_days,
  issue.details
FROM tmp_ph_v3a_issue issue
LEFT JOIN org_employee employee ON employee.id = issue.employee_id
UNION ALL
SELECT
  'EXPIRE_PENDING',
  'EXPIRE_MISSING',
  employee.employee_name,
  pending.lot_ledger_id,
  pending.expire_tx_date,
  pending.qty_days,
  CONCAT('Lot seharusnya expired setelah ', pending.expired_at)
FROM tmp_ph_v3a_expire_pending pending
JOIN org_employee employee ON employee.id = pending.employee_id
ORDER BY problem_group, employee_name, tx_date, ledger_id;

DROP PROCEDURE IF EXISTS sp_ph_v3a_validate;
