SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-27g_preview_ph_core_cutover_v2_all_grant_policy.sql
-- Tujuan :
-- 1) Memeriksa ulang saldo PH dengan kebijakan cutover yang direvisi.
-- 2) Mengambil SELURUH GRANT Core sebelum 1 Juni 2026 sebagai lot.
-- 3) Mengabaikan USE sebelum cutover dan menghitung USE hanya dari Finance
--    mulai 1 Juni 2026.
-- 4) Menutup lot lama melalui EXPIRE sesuai tanggal berlaku sampai.
--
-- Catatan:
-- - Script ini hanya memakai temporary table dan helper procedure yang
--   dihapus kembali di akhir script. Tidak mengubah ledger permanen.
-- - Jalankan setelah 2026-08-27f bila ingin memeriksa koreksi V2.
-- ============================================================

SET @ph_cutover_date := '2026-06-01';
SET @ph_as_of_date := CURDATE();
SET @ph_v1_migration_code := 'PH-CORE-CUTOVER-20260601-V1';
SET @ph_v2_migration_code := 'PH-CORE-CUTOVER-20260601-V2';

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

-- Ringkasan rencana koreksi V2.
SELECT
  @ph_cutover_date AS cutover_finance,
  @ph_as_of_date AS as_of_date,
  (SELECT COUNT(*) FROM tmp_ph_v2_import_plan) AS seluruh_lot_grant_core,
  (SELECT ROUND(SUM(qty_days), 2) FROM tmp_ph_v2_import_plan) AS total_hak_grant_core,
  (SELECT COUNT(*) FROM tmp_ph_v2_replace_plan WHERE tx_type = 'GRANT') AS grant_v1_akan_dinetralkan,
  (SELECT COUNT(*) FROM tmp_ph_v2_replace_plan WHERE tx_type = 'EXPIRE') AS expire_v1_akan_dinetralkan,
  (SELECT COUNT(*) FROM tmp_ph_v2_pre_cutover_use_plan) AS use_finance_sebelum_cutover_akan_void;

-- Bagian ini wajib kosong sebelum apply V2.
SELECT 'NAME_MAPPING_ISSUE' AS problem_group,
       CASE WHEN finance_name_matches = 0 THEN 'FINANCE_NAME_NOT_FOUND' ELSE 'FINANCE_NAME_AMBIGUOUS' END AS issue_type,
       core_employee_id AS employee_id,
       source_core_ledger_id AS ledger_id,
       source_grant_date AS tx_date,
       qty_days,
       COALESCE(finance_name_candidates, '-') AS details
FROM tmp_ph_v2_import_plan
WHERE finance_name_matches <> 1
UNION ALL
SELECT 'FINANCE_LEDGER_ISSUE', issue_type, employee_id, ledger_id, tx_date, qty_days, details
FROM tmp_ph_v2_issue
ORDER BY problem_group, employee_id, tx_date, ledger_id;

-- Pemeriksaan lengkap seluruh pegawai yang memiliki grant Core. Kolom
-- "estimated_active_balance" adalah saldo setelah USE Finance sejak 1 Juni
-- dan expiry lot lama diterapkan.
SELECT
  employee.employee_code,
  employee.employee_name,
  COALESCE(core_metrics.core_grant_qty, 0) AS seluruh_grant_core,
  COALESCE(finance_metrics.grant_finance_setelah_cutover, 0) AS grant_finance,
  COALESCE(finance_metrics.use_finance_setelah_cutover, 0) AS penggunaan_finance,
  COALESCE(expire_metrics.qty_akan_expire, 0) AS akan_expire,
  COALESCE(active_metrics.active_qty, 0) AS estimated_active_balance
FROM org_employee employee
JOIN (
  SELECT finance_employee_id AS employee_id, ROUND(SUM(qty_days), 2) AS core_grant_qty
  FROM tmp_ph_v2_import_plan
  WHERE finance_name_matches = 1
  GROUP BY finance_employee_id
) core_metrics ON core_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT
    work.employee_id,
    ROUND(SUM(CASE
      WHEN work.tx_type = 'GRANT'
       AND work.tx_date >= @ph_cutover_date
       AND work.ref_table <> 'core.att_employee_ph_ledger'
      THEN work.qty_days ELSE 0 END), 2) AS grant_finance_setelah_cutover,
    ROUND(SUM(CASE
      WHEN work.tx_type = 'USE' AND work.tx_date >= @ph_cutover_date
      THEN work.qty_days ELSE 0 END), 2) AS use_finance_setelah_cutover
  FROM tmp_ph_v2_work_ledger work
  GROUP BY work.employee_id
) finance_metrics ON finance_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_days), 2) AS qty_akan_expire
  FROM tmp_ph_v2_expire_plan
  GROUP BY employee_id
) expire_metrics ON expire_metrics.employee_id = employee.id
LEFT JOIN (
  SELECT employee_id, ROUND(SUM(qty_remaining), 2) AS active_qty
  FROM tmp_ph_v2_lot_state
  WHERE qty_remaining > 0.0001
    AND (expired_at IS NULL OR expired_at >= @ph_as_of_date)
  GROUP BY employee_id
) active_metrics ON active_metrics.employee_id = employee.id
ORDER BY employee.employee_name;

-- Rincian lot Core untuk audit manual. "Sisa simulasi" bernilai nol jika
-- lot telah dipakai oleh Finance sejak cutover atau akan ditutup expiry.
SELECT
  employee.employee_code,
  employee.employee_name,
  plan.source_core_ledger_id AS core_lot_id,
  plan.source_grant_date,
  plan.expired_at AS berlaku_sampai,
  plan.qty_days AS grant_core,
  COALESCE(lot.qty_remaining, 0) AS sisa_setelah_penggunaan_finance,
  CASE
    WHEN COALESCE(lot.qty_remaining, 0) <= 0.0001 THEN 'Sudah dipakai Finance sejak cutover'
    WHEN plan.expired_at < @ph_as_of_date THEN 'Akan EXPIRE'
    ELSE 'Masih aktif'
  END AS status_v2
FROM tmp_ph_v2_import_plan plan
JOIN org_employee employee ON employee.id = plan.finance_employee_id
LEFT JOIN tmp_ph_v2_lot_state lot
  ON lot.ledger_id = 8000000000 + plan.source_core_ledger_id
WHERE plan.finance_name_matches = 1
ORDER BY employee.employee_name, plan.source_grant_date, plan.source_core_ledger_id;

DROP PROCEDURE IF EXISTS sp_ph_v2_validate;
