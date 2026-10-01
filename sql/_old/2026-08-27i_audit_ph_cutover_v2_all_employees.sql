SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27i_audit_ph_cutover_v2_all_employees.sql
-- Tujuan :
-- 1) Memeriksa hasil akhir migrasi PH cutover V2 untuk seluruh pegawai.
-- 2) Membuktikan bahwa seluruh grant Core sebelum 2026-06-01 telah dibawa.
-- 3) Memeriksa bahwa USE aktif hanya dihitung sejak 2026-06-01.
-- 4) Memeriksa saldo ledger, saldo lot FIFO, dan expiry yang masih tertunda.
--
-- Prasyarat:
-- - Jalankan setelah 2026-08-27h_apply_ph_core_cutover_v2_all_grant_policy.sql.
--
-- Catatan:
-- - Script ini tidak mengubah ledger, jadwal, presensi, maupun saldo PH.
-- - Procedure sementara dibuat hanya untuk simulasi FIFO, lalu dihapus lagi
--   pada akhir script.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_v1_migration_code := 'PH-CORE-CUTOVER-20260601-V1';
SET @ph_v2_migration_code := 'PH-CORE-CUTOVER-20260601-V2';

DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_core_grant;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_name_map;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_expected;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_work_ledger;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_lot_state;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_issue;
DROP TEMPORARY TABLE IF EXISTS tmp_ph_v2_audit_expire_pending;
DROP PROCEDURE IF EXISTS sp_ph_v2_audit_simulate;

-- Sumber yang seharusnya dibawa: SEMUA grant Core sebelum Finance go-live.
CREATE TEMPORARY TABLE tmp_ph_v2_audit_core_grant AS
SELECT
  ledger.id AS source_core_ledger_id,
  ledger.employee_id AS core_employee_id,
  employee.employee_name AS core_employee_name,
  ledger.tx_date AS grant_date,
  ROUND(ledger.qty_days, 2) AS qty_days,
  ledger.expired_at
FROM core.att_employee_ph_ledger ledger
JOIN core.org_employee employee ON employee.id = ledger.employee_id
WHERE ledger.tx_date < @ph_cutover_date
  AND UPPER(ledger.tx_type) = 'GRANT'
  AND COALESCE(ledger.qty_days, 0) > 0.0001;

CREATE TEMPORARY TABLE tmp_ph_v2_audit_name_map AS
SELECT
  LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', '')) AS name_key,
  COUNT(*) AS finance_name_matches,
  MIN(id) AS finance_employee_id,
  GROUP_CONCAT(CONCAT(id, ':', employee_name) ORDER BY id SEPARATOR ' | ') AS finance_name_candidates
FROM org_employee
GROUP BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

CREATE TEMPORARY TABLE tmp_ph_v2_audit_expected AS
SELECT
  source.source_core_ledger_id,
  source.core_employee_id,
  source.core_employee_name,
  source.grant_date,
  source.qty_days,
  source.expired_at,
  mapping.finance_employee_id,
  COALESCE(mapping.finance_name_matches, 0) AS finance_name_matches,
  mapping.finance_name_candidates
FROM tmp_ph_v2_audit_core_grant source
LEFT JOIN tmp_ph_v2_audit_name_map mapping
  ON mapping.name_key = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(source.core_employee_name), ' ', ''), '.', ''), ',', ''), '''', ''), '-', ''));

-- Semua ledger aktual disimulasikan kembali. VOID sengaja tetap dimuat agar
-- terlihat pada audit, tetapi tidak memengaruhi saldo FIFO.
CREATE TEMPORARY TABLE tmp_ph_v2_audit_work_ledger AS
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

CREATE TEMPORARY TABLE tmp_ph_v2_audit_lot_state (
  ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  tx_date DATE NOT NULL,
  expired_at DATE NULL,
  qty_remaining DECIMAL(8,2) NOT NULL,
  lot_origin VARCHAR(20) NOT NULL,
  PRIMARY KEY (ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v2_audit_issue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  issue_type VARCHAR(80) NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  ledger_id BIGINT UNSIGNED NULL,
  tx_date DATE NULL,
  qty_days DECIMAL(8,2) NULL,
  details VARCHAR(255) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TEMPORARY TABLE tmp_ph_v2_audit_expire_pending (
  lot_ledger_id BIGINT UNSIGNED NOT NULL,
  employee_id BIGINT UNSIGNED NOT NULL,
  expired_at DATE NOT NULL,
  qty_days DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (lot_ledger_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE PROCEDURE sp_ph_v2_audit_simulate()
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
    FROM tmp_ph_v2_audit_work_ledger
    ORDER BY employee_id, tx_date, ledger_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  DELETE FROM tmp_ph_v2_audit_lot_state;
  DELETE FROM tmp_ph_v2_audit_issue;
  DELETE FROM tmp_ph_v2_audit_expire_pending;

  OPEN ledger_cursor;
  read_loop: LOOP
    FETCH ledger_cursor INTO v_ledger_id, v_employee_id, v_tx_date, v_tx_type,
      v_qty, v_expired_at, v_ref_table, v_ref_id;
    IF v_done = 1 THEN
      LEAVE read_loop;
    END IF;

    IF COALESCE(v_qty, 0) <= 0.0001 THEN
      INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('NON_POSITIVE_QTY', v_employee_id, v_ledger_id, v_tx_date, v_qty,
              'Ledger memiliki qty PH nol atau negatif.');
      ITERATE read_loop;
    END IF;

    IF v_tx_type = 'GRANT' THEN
      INSERT INTO tmp_ph_v2_audit_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
      VALUES (v_ledger_id, v_employee_id, v_tx_date, v_expired_at, v_qty, 'GRANT');
    ELSEIF v_tx_type = 'ADJUST' THEN
      INSERT INTO tmp_ph_v2_audit_lot_state (ledger_id, employee_id, tx_date, expired_at, qty_remaining, lot_origin)
      VALUES (v_ledger_id, v_employee_id, v_tx_date, NULL, v_qty, 'ADJUST');
    ELSEIF v_tx_type = 'USE' THEN
      SET v_remaining = v_qty;
      consume_loop: WHILE v_remaining > 0.0001 DO
        SELECT COALESCE((
          SELECT lot.ledger_id
          FROM tmp_ph_v2_audit_lot_state lot
          WHERE lot.employee_id = v_employee_id
            AND lot.tx_date <= v_tx_date
            AND lot.qty_remaining > 0.0001
            AND (lot.expired_at IS NULL OR lot.expired_at >= v_tx_date)
          ORDER BY lot.tx_date, lot.ledger_id
          LIMIT 1
        ), 0) INTO v_lot_id;

        IF v_lot_id = 0 THEN
          INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('USE_NOT_COVERED_BY_ACTIVE_LOT', v_employee_id, v_ledger_id,
                  v_tx_date, v_remaining,
                  'Penggunaan PH tidak dapat ditutup oleh lot aktif secara FIFO.');
          SET v_remaining = 0;
        ELSE
          SELECT qty_remaining INTO v_lot_available
          FROM tmp_ph_v2_audit_lot_state
          WHERE ledger_id = v_lot_id;
          SET v_lot_available = LEAST(v_lot_available, v_remaining);
          UPDATE tmp_ph_v2_audit_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_lot_available, 2)
          WHERE ledger_id = v_lot_id;
          SET v_remaining = ROUND(v_remaining - v_lot_available, 2);
        END IF;
      END WHILE;
    ELSEIF v_tx_type = 'EXPIRE' THEN
      IF v_ref_table = 'att_employee_ph_ledger' AND COALESCE(v_ref_id, 0) > 0 THEN
        SELECT COALESCE((
          SELECT lot.qty_remaining
          FROM tmp_ph_v2_audit_lot_state lot
          WHERE lot.ledger_id = v_ref_id
          LIMIT 1
        ), -1) INTO v_lot_available;
        IF v_lot_available < 0 THEN
          INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_REF_NOT_FOUND', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE tidak menemukan lot asal.');
        ELSEIF v_qty > v_lot_available + 0.0001 THEN
          INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
          VALUES ('EXPIRE_EXCEEDS_LOT', v_employee_id, v_ledger_id, v_tx_date,
                  v_qty, 'EXPIRE melebihi sisa lot asal.');
        ELSE
          UPDATE tmp_ph_v2_audit_lot_state
          SET qty_remaining = ROUND(qty_remaining - v_qty, 2)
          WHERE ledger_id = v_ref_id;
        END IF;
      ELSE
        INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
        VALUES ('EXPIRE_WITHOUT_LOT_REF', v_employee_id, v_ledger_id, v_tx_date,
                v_qty, 'EXPIRE tidak menunjuk lot asal.');
      END IF;
    ELSEIF v_tx_type <> 'VOID' THEN
      INSERT INTO tmp_ph_v2_audit_issue (issue_type, employee_id, ledger_id, tx_date, qty_days, details)
      VALUES ('UNSUPPORTED_TX_TYPE', v_employee_id, v_ledger_id, v_tx_date,
              v_qty, CONCAT('Jenis ledger tidak dikenali: ', v_tx_type));
    END IF;
  END LOOP;
  CLOSE ledger_cursor;

  INSERT INTO tmp_ph_v2_audit_expire_pending (lot_ledger_id, employee_id, expired_at, qty_days)
  SELECT lot.ledger_id, lot.employee_id, lot.expired_at, ROUND(lot.qty_remaining, 2)
  FROM tmp_ph_v2_audit_lot_state lot
  WHERE lot.qty_remaining > 0.0001
    AND lot.expired_at IS NOT NULL
    AND lot.expired_at < @ph_as_of_date;
END$$
DELIMITER ;

CALL sp_ph_v2_audit_simulate();

-- ============================================================
-- RINGKASAN UTAMA
-- ============================================================
SELECT
  @ph_cutover_date AS cutover_finance,
  @ph_as_of_date AS tanggal_pemeriksaan,
  (SELECT COUNT(*) FROM tmp_ph_v2_audit_expected) AS grant_core_yang_harus_dimigrasi,
  (SELECT COUNT(*) FROM att_ph_cutover_migration_audit
   WHERE migration_code = @ph_v2_migration_code AND action_type = 'IMPORT_CORE_GRANT_V2') AS grant_core_tercatat_v2,
  (SELECT COUNT(*) FROM att_employee_ph_ledger ledger
   JOIN att_ph_cutover_migration_audit audit_log ON audit_log.finance_ledger_id = ledger.id
   WHERE audit_log.migration_code = @ph_v1_migration_code
     AND audit_log.action_type IN ('IMPORT_CORE_OPENING_LOT', 'CREATE_POST_CUTOVER_EXPIRE')
     AND ledger.tx_type IN ('GRANT', 'EXPIRE')) AS baris_v1_yang_masih_aktif,
  (SELECT COUNT(*) FROM att_employee_ph_ledger
   WHERE tx_type = 'USE' AND tx_date < @ph_cutover_date) AS penggunaan_aktif_sebelum_cutover,
  (SELECT COUNT(*) FROM tmp_ph_v2_audit_issue) AS masalah_fifo,
  (SELECT COUNT(*) FROM tmp_ph_v2_audit_expire_pending) AS lot_expired_belum_dicatat;

-- ============================================================
-- STATUS MIGRASI SELURUH PEGAWAI YANG MEMILIKI SUMBER CORE
-- ============================================================
SELECT
  employee.employee_code,
  employee.employee_name,
  COUNT(expected.source_core_ledger_id) AS lot_core_diharapkan,
  ROUND(SUM(expected.qty_days), 2) AS grant_core_diharapkan,
  COUNT(import_audit.id) AS lot_core_v2_tercatat,
  ROUND(SUM(CASE WHEN imported_ledger.tx_type = 'GRANT' THEN imported_ledger.qty_days ELSE 0 END), 2) AS grant_core_v2_aktif,
  COALESCE(finance_metrics.grant_finance, 0) AS grant_finance_sejak_juni,
  COALESCE(finance_metrics.use_finance, 0) AS penggunaan_sejak_juni,
  COALESCE(expire_metrics.expire_v2, 0) AS expiry_v2,
  COALESCE(ledger_metrics.saldo_ledger, 0) AS saldo_ledger,
  COALESCE(lot_metrics.saldo_fifo_aktif, 0) AS saldo_fifo_aktif,
  CASE
    WHEN MAX(expected.finance_name_matches) <> 1 THEN 'PERLU REVIEW: nama Core tidak cocok unik ke Finance'
    WHEN COUNT(import_audit.id) <> COUNT(expected.source_core_ledger_id) THEN 'PERLU REVIEW: jumlah lot Core belum lengkap'
    WHEN ABS(COALESCE(ledger_metrics.saldo_ledger, 0) - COALESCE(lot_metrics.saldo_fifo_aktif, 0)) > 0.0001 THEN 'PERLU REVIEW: saldo ledger dan FIFO berbeda'
    WHEN COALESCE(issue_metrics.total_issue, 0) > 0 THEN 'PERLU REVIEW: ada masalah FIFO'
    ELSE 'AMAN'
  END AS status_audit
FROM tmp_ph_v2_audit_expected expected
JOIN org_employee employee ON employee.id = expected.finance_employee_id
LEFT JOIN att_ph_cutover_migration_audit import_audit
  ON import_audit.migration_code = @ph_v2_migration_code
 AND import_audit.action_type = 'IMPORT_CORE_GRANT_V2'
 AND import_audit.source_core_ledger_id = expected.source_core_ledger_id
LEFT JOIN att_employee_ph_ledger imported_ledger ON imported_ledger.id = import_audit.finance_ledger_id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT'
                    AND tx_date >= @ph_cutover_date
                    AND COALESCE(ref_table, '') <> 'core.att_employee_ph_ledger'
              THEN qty_days ELSE 0 END), 2) AS grant_finance,
    ROUND(SUM(CASE WHEN tx_type = 'USE' AND tx_date >= @ph_cutover_date THEN qty_days ELSE 0 END), 2) AS use_finance
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) finance_metrics ON finance_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT audit_log.employee_id, ROUND(SUM(ledger.qty_days), 2) AS expire_v2
  FROM att_ph_cutover_migration_audit audit_log
  JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
  WHERE audit_log.migration_code = @ph_v2_migration_code
    AND audit_log.action_type = 'CREATE_EXPIRE_V2'
    AND ledger.tx_type = 'EXPIRE'
  GROUP BY audit_log.employee_id
) expire_metrics ON expire_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id,
    ROUND(SUM(CASE WHEN tx_type IN ('GRANT', 'ADJUST') THEN qty_days ELSE 0 END)
        - SUM(CASE WHEN tx_type IN ('USE', 'EXPIRE') THEN qty_days ELSE 0 END), 2) AS saldo_ledger
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) ledger_metrics ON ledger_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS saldo_fifo_aktif
  FROM tmp_ph_v2_audit_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) lot_metrics ON lot_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_issue
  FROM tmp_ph_v2_audit_issue
  GROUP BY employee_id
) issue_metrics ON issue_metrics.employee_id = employee.id
GROUP BY
  employee.id, employee.employee_code, employee.employee_name,
  finance_metrics.grant_finance, finance_metrics.use_finance,
  expire_metrics.expire_v2, ledger_metrics.saldo_ledger,
  lot_metrics.saldo_fifo_aktif, issue_metrics.total_issue
ORDER BY employee.employee_name;

-- ============================================================
-- RINGKASAN SELURUH PEGAWAI AKTIF FINANCE
-- ============================================================
SELECT
  employee.employee_code,
  employee.employee_name,
  CASE WHEN COALESCE(core_metrics.total_lot_core, 0) > 0
       THEN 'TERDAMPAK MIGRASI CORE'
       WHEN COALESCE(finance_metrics.grant_finance, 0) > 0
         OR COALESCE(finance_metrics.use_finance, 0) > 0
       THEN 'PH HANYA DARI FINANCE'
       ELSE 'BELUM ADA MUTASI PH'
  END AS cakupan,
  COALESCE(core_metrics.total_lot_core, 0) AS lot_core_diharapkan,
  COALESCE(core_metrics.total_grant_core, 0) AS grant_core_diharapkan,
  COALESCE(import_metrics.total_lot_v2, 0) AS lot_core_v2_tercatat,
  COALESCE(finance_metrics.grant_finance, 0) AS grant_finance_sejak_juni,
  COALESCE(finance_metrics.use_finance, 0) AS penggunaan_sejak_juni,
  COALESCE(expire_metrics.expire_v2, 0) AS expiry_v2,
  COALESCE(ledger_metrics.saldo_ledger, 0) AS saldo_ledger,
  COALESCE(lot_metrics.saldo_fifo_aktif, 0) AS saldo_fifo_aktif,
  CASE
    WHEN COALESCE(pre_cutover_use.total_use, 0) > 0 THEN 'PERLU REVIEW: USE aktif sebelum cutover'
    WHEN COALESCE(core_metrics.total_lot_core, 0) > 0
         AND COALESCE(import_metrics.total_lot_v2, 0) <> COALESCE(core_metrics.total_lot_core, 0)
      THEN 'PERLU REVIEW: lot Core V2 belum lengkap'
    WHEN ABS(COALESCE(ledger_metrics.saldo_ledger, 0) - COALESCE(lot_metrics.saldo_fifo_aktif, 0)) > 0.0001
      THEN 'PERLU REVIEW: saldo ledger dan FIFO berbeda'
    WHEN COALESCE(issue_metrics.total_issue, 0) > 0 THEN 'PERLU REVIEW: ada masalah FIFO'
    ELSE 'AMAN'
  END AS status_audit
FROM org_employee employee
LEFT JOIN (
  SELECT
    finance_employee_id AS employee_id,
    COUNT(*) AS total_lot_core,
    ROUND(SUM(qty_days), 2) AS total_grant_core
  FROM tmp_ph_v2_audit_expected
  WHERE finance_name_matches = 1
  GROUP BY finance_employee_id
) core_metrics ON core_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_lot_v2
  FROM att_ph_cutover_migration_audit
  WHERE migration_code = @ph_v2_migration_code
    AND action_type = 'IMPORT_CORE_GRANT_V2'
  GROUP BY employee_id
) import_metrics ON import_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT
    employee_id,
    ROUND(SUM(CASE WHEN tx_type = 'GRANT'
                    AND tx_date >= @ph_cutover_date
                    AND COALESCE(ref_table, '') <> 'core.att_employee_ph_ledger'
              THEN qty_days ELSE 0 END), 2) AS grant_finance,
    ROUND(SUM(CASE WHEN tx_type = 'USE' AND tx_date >= @ph_cutover_date THEN qty_days ELSE 0 END), 2) AS use_finance
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) finance_metrics ON finance_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT audit_log.employee_id, ROUND(SUM(ledger.qty_days), 2) AS expire_v2
  FROM att_ph_cutover_migration_audit audit_log
  JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
  WHERE audit_log.migration_code = @ph_v2_migration_code
    AND audit_log.action_type = 'CREATE_EXPIRE_V2'
    AND ledger.tx_type = 'EXPIRE'
  GROUP BY audit_log.employee_id
) expire_metrics ON expire_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id,
    ROUND(SUM(CASE WHEN tx_type IN ('GRANT', 'ADJUST') THEN qty_days ELSE 0 END)
        - SUM(CASE WHEN tx_type IN ('USE', 'EXPIRE') THEN qty_days ELSE 0 END), 2) AS saldo_ledger
  FROM att_employee_ph_ledger
  GROUP BY employee_id
) ledger_metrics ON ledger_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS saldo_fifo_aktif
  FROM tmp_ph_v2_audit_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) lot_metrics ON lot_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_issue
  FROM tmp_ph_v2_audit_issue
  GROUP BY employee_id
) issue_metrics ON issue_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, COUNT(*) AS total_use
  FROM att_employee_ph_ledger
  WHERE tx_type = 'USE'
    AND tx_date < @ph_cutover_date
  GROUP BY employee_id
) pre_cutover_use ON pre_cutover_use.employee_id = employee.id
WHERE employee.is_active = 1
   OR COALESCE(core_metrics.total_lot_core, 0) > 0
   OR COALESCE(ledger_metrics.saldo_ledger, 0) <> 0
ORDER BY employee.employee_name;

-- ============================================================
-- RINCIAN LOT CORE: sumber, lot V2, status sisa, dan expiry
-- ============================================================
SELECT
  expected.core_employee_name AS nama_core,
  employee.employee_code,
  employee.employee_name AS nama_finance,
  expected.source_core_ledger_id AS lot_core_id,
  expected.grant_date,
  expected.expired_at AS berlaku_sampai,
  expected.qty_days AS nilai_grant,
  imported_ledger.id AS ledger_finance_id,
  imported_ledger.tx_type AS status_ledger_finance,
  COALESCE(lot_state.qty_remaining, 0) AS sisa_lot_setelah_fifo,
  COALESCE(expire_ledger.qty_days, 0) AS nilai_expire_tercatat,
  CASE
    WHEN expected.finance_name_matches <> 1 THEN 'MAPPING NAMA PERLU REVIEW'
    WHEN imported_ledger.id IS NULL THEN 'BELUM DIMIGRASIKAN'
    WHEN imported_ledger.tx_type <> 'GRANT' THEN 'LOT V2 TIDAK AKTIF'
    WHEN expected.expired_at IS NOT NULL AND expected.expired_at < @ph_as_of_date
         AND COALESCE(lot_state.qty_remaining, 0) > 0.0001 THEN 'EXPIRE MASIH TERTUNDA'
    WHEN COALESCE(expire_ledger.qty_days, 0) > 0 THEN 'SUDAH EXPIRE'
    WHEN COALESCE(lot_state.qty_remaining, 0) > 0.0001 THEN 'AKTIF / MASIH TERSISA'
    ELSE 'SUDAH DIGUNAKAN'
  END AS status_lot
FROM tmp_ph_v2_audit_expected expected
LEFT JOIN org_employee employee ON employee.id = expected.finance_employee_id
LEFT JOIN att_ph_cutover_migration_audit import_audit
  ON import_audit.migration_code = @ph_v2_migration_code
 AND import_audit.action_type = 'IMPORT_CORE_GRANT_V2'
 AND import_audit.source_core_ledger_id = expected.source_core_ledger_id
LEFT JOIN att_employee_ph_ledger imported_ledger ON imported_ledger.id = import_audit.finance_ledger_id
LEFT JOIN tmp_ph_v2_audit_lot_state lot_state ON lot_state.ledger_id = imported_ledger.id
LEFT JOIN att_employee_ph_ledger expire_ledger
  ON expire_ledger.tx_type = 'EXPIRE'
 AND expire_ledger.ref_table = 'att_employee_ph_ledger'
 AND expire_ledger.ref_id = imported_ledger.id
ORDER BY employee.employee_name, expected.grant_date, expected.source_core_ledger_id;

-- ============================================================
-- SEMUA TEMUAN YANG HARUS NOL SETELAH MIGRASI
-- ============================================================
SELECT 'Mapping nama Core ke Finance tidak unik' AS jenis_temuan,
       expected.core_employee_name AS target,
       CONCAT('core lot #', expected.source_core_ledger_id, '; kandidat=', COALESCE(expected.finance_name_candidates, '-')) AS rincian
FROM tmp_ph_v2_audit_expected expected
WHERE expected.finance_name_matches <> 1
UNION ALL
SELECT 'Grant Core belum tercatat sebagai V2',
       expected.core_employee_name,
       CONCAT('core lot #', expected.source_core_ledger_id)
FROM tmp_ph_v2_audit_expected expected
LEFT JOIN att_ph_cutover_migration_audit audit_log
  ON audit_log.migration_code = @ph_v2_migration_code
 AND audit_log.action_type = 'IMPORT_CORE_GRANT_V2'
 AND audit_log.source_core_ledger_id = expected.source_core_ledger_id
WHERE expected.finance_name_matches = 1
  AND audit_log.id IS NULL
UNION ALL
SELECT 'USE aktif sebelum 2026-06-01', employee.employee_name,
       CONCAT('ledger #', ledger.id, '; tanggal=', ledger.tx_date, '; qty=', ledger.qty_days)
FROM att_employee_ph_ledger ledger
JOIN org_employee employee ON employee.id = ledger.employee_id
WHERE ledger.tx_type = 'USE'
  AND ledger.tx_date < @ph_cutover_date
UNION ALL
SELECT 'Jejak V1 masih aktif', employee.employee_name,
       CONCAT('ledger #', ledger.id, '; ', ledger.tx_type, '; ', ledger.qty_days)
FROM att_ph_cutover_migration_audit audit_log
JOIN att_employee_ph_ledger ledger ON ledger.id = audit_log.finance_ledger_id
JOIN org_employee employee ON employee.id = ledger.employee_id
WHERE audit_log.migration_code = @ph_v1_migration_code
  AND audit_log.action_type IN ('IMPORT_CORE_OPENING_LOT', 'CREATE_POST_CUTOVER_EXPIRE')
  AND ledger.tx_type IN ('GRANT', 'EXPIRE')
UNION ALL
SELECT 'Masalah simulasi FIFO', employee.employee_name,
       CONCAT(issue.issue_type, '; ledger #', issue.ledger_id, '; ', issue.details)
FROM tmp_ph_v2_audit_issue issue
LEFT JOIN org_employee employee ON employee.id = issue.employee_id
UNION ALL
SELECT 'Lot expired belum memiliki EXPIRE', employee.employee_name,
       CONCAT('ledger #', pending.lot_ledger_id, '; berlaku s.d. ', pending.expired_at, '; sisa=', pending.qty_days)
FROM tmp_ph_v2_audit_expire_pending pending
JOIN org_employee employee ON employee.id = pending.employee_id
ORDER BY jenis_temuan, target;

DROP PROCEDURE IF EXISTS sp_ph_v2_audit_simulate;
