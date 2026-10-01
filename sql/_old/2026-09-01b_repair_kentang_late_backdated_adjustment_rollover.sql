SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-09-01b_repair_kentang_late_backdated_adjustment_rollover.sql
-- Tujuan :
-- 1) Memindahkan event adjustment KENTANG 609 GR yang dibuat pada
--    1 September tetapi bertanggal 31 Agustus ke tanggal kejadian nyata.
-- 2) Mengeluarkan delta tersebut dari ledger Agustus dan memasukkannya
--    ke ledger September tanpa menambah atau mengurangi stok fisik.
-- 3) Meluruskan running balance movement September agar sama dengan lot.
--
-- Yang TIDAK dilakukan:
-- - Tidak membuat lot baru.
-- - Tidak mengubah qty_in, qty_out, atau qty_balance lot #8879.
-- - Tidak mengubah issue POS #65043, HPP, order, atau defisit historis.
--
-- Aman dijalankan berulang:
-- - Apply pertama wajib lulus preflight identitas dan gap 609 GR.
-- - Apply berikutnya membaca audit marker dan hanya memverifikasi hasil.
-- - Semua perubahan data berada dalam satu transaksi dengan rollback
--   otomatis bila validasi akhir gagal.
-- ============================================================

SET @repair_code := 'INV-KENTANG-20260901-V1';
SET @repair_actor_user_id := NULL; -- Opsional: isi auth_user.id pelaksana.
SET @repair_now := NOW();
SET @repair_adjustment_created_at := '2026-09-01 14:11:48';

DROP PROCEDURE IF EXISTS sp_repair_kentang_20260901_assert;
DELIMITER $$
CREATE PROCEDURE sp_repair_kentang_20260901_assert(IN p_stage VARCHAR(20))
BEGIN
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_audit_count INT DEFAULT 0;
  DECLARE v_lot_qty DECIMAL(18,4) DEFAULT 0;
  DECLARE v_lot_value DECIMAL(18,2) DEFAULT 0;
  DECLARE v_ledger_qty DECIMAL(18,4) DEFAULT 0;
  DECLARE v_ledger_value DECIMAL(18,2) DEFAULT 0;
  DECLARE v_latest_after DECIMAL(18,4) DEFAULT 0;

  SELECT COUNT(*) INTO v_audit_count
  FROM aud_transaction_log
  WHERE action_code = 'REPAIR_BACKDATED_ROLLOVER'
    AND entity_table = 'inv_stock_adjustment'
    AND entity_id = 3181
    AND notes LIKE CONCAT(@repair_code, '%');

  IF v_audit_count > 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: audit marker ganda ditemukan.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_adjustment header
  JOIN inv_stock_adjustment_line line ON line.adjustment_id = header.id
  WHERE header.id = 3181
    AND header.adjustment_no = 'IAD20260831-5521'
    AND header.stock_scope = 'DIVISION'
    AND header.division_id = 3
    AND header.destination_type = 'KITCHEN'
    AND header.status = 'POSTED'
    AND header.created_at = '2026-09-01 14:11:48'
    AND line.id = 3195
    AND line.input_mode = 'DELTA'
    AND line.item_id = 102
    AND line.material_id = 116
    AND line.buy_uom_id = 3
    AND line.content_uom_id = 11
    AND line.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
    AND ABS(line.qty_adjustment_plus_content - 609) < 0.0001
    AND ABS(line.unit_cost - 19) < 0.000001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: adjustment #3181/line #3195 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_movement_log movement
  WHERE movement.id = 73083
    AND movement.movement_no = 'MV202608310394'
    AND movement.movement_scope = 'DIVISION'
    AND movement.division_id = 3
    AND movement.destination_type = 'KITCHEN'
    AND movement.movement_type = 'ADJUSTMENT_IN'
    AND movement.adjustment_category = 'ADJUSTMENT_PLUS'
    AND movement.ref_table = 'inv_stock_adjustment'
    AND movement.ref_id = 3181
    AND movement.item_id = 102
    AND movement.material_id = 116
    AND movement.content_uom_id = 11
    AND movement.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
    AND ABS(movement.qty_buy_delta - 0.2030) < 0.0001
    AND ABS(movement.qty_content_delta - 609) < 0.0001
    AND ABS(movement.qty_content_after - 609) < 0.0001
    AND ABS(movement.unit_cost - 19) < 0.000001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: movement adjustment #73083 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_material_fifo_lot lot
  WHERE lot.id = 8879
    AND lot.lot_no = 'LOT20260831-96D01BD4F41F'
    AND lot.location_scope = 'DIVISION'
    AND lot.division_id = 3
    AND lot.destination_type = 'KITCHEN'
    AND lot.item_id = 102
    AND lot.material_id = 116
    AND lot.content_uom_id = 11
    AND lot.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
    AND lot.source_table = 'inv_stock_adjustment'
    AND lot.source_id = 3181
    AND lot.source_line_id = 3195
    AND ABS(lot.qty_in - 609) < 0.0001
    AND ABS(lot.qty_out - 20) < 0.0001
    AND ABS(lot.qty_balance - 589) < 0.0001
    AND ABS(lot.unit_cost - 19) < 0.000001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: lot FIFO #8879 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_material_fifo_issue_log issue_header
  JOIN inv_material_fifo_issue_line issue_line
    ON issue_line.issue_id = issue_header.id
  WHERE issue_header.id = 65043
    AND issue_header.issue_date = '2026-09-01'
    AND issue_header.status = 'POSTED'
    AND issue_header.source_table = 'pos_stock_commit'
    AND issue_header.source_id = 4771
    AND issue_header.item_id = 102
    AND issue_header.material_id = 116
    AND ABS(issue_header.issue_qty - 20) < 0.0001
    AND ABS(issue_header.total_cost - 380) < 0.01
    AND issue_line.id = 67695
    AND issue_line.lot_id = 8879
    AND ABS(issue_line.qty_out - 20) < 0.0001
    AND ABS(issue_line.source_balance_before - 609) < 0.0001
    AND ABS(issue_line.source_balance_after - 589) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: FIFO issue POS #65043 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_division_monthly_stock stock
  WHERE stock.id IN (6433, 8255)
    AND stock.division_id = 3
    AND stock.destination_type = 'KITCHEN'
    AND stock.item_id = 102
    AND stock.material_id = 116
    AND stock.content_uom_id = 11
    AND stock.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f';
  IF v_count <> 2 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: pasangan ledger Agustus/September tidak lengkap.';
  END IF;

  SELECT
    COALESCE(SUM(lot.qty_balance), 0),
    COALESCE(ROUND(SUM(lot.qty_balance * lot.unit_cost), 2), 0)
  INTO v_lot_qty, v_lot_value
  FROM inv_material_fifo_lot lot
  WHERE lot.location_scope = 'DIVISION'
    AND lot.division_id = 3
    AND lot.destination_type = 'KITCHEN'
    AND lot.item_id = 102
    AND lot.material_id = 116
    AND lot.content_uom_id = 11
    AND lot.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f';

  SELECT stock.closing_qty_content, stock.total_value
  INTO v_ledger_qty, v_ledger_value
  FROM inv_division_monthly_stock stock
  WHERE stock.id = 8255;

  IF p_stage = 'BEFORE' AND v_audit_count = 0 THEN
    SELECT COUNT(*) INTO v_count
    FROM inv_stock_adjustment header
    JOIN inv_stock_movement_log movement ON movement.id = 73083
    JOIN inv_material_fifo_lot lot ON lot.id = 8879
    WHERE header.id = 3181
      AND header.adjustment_date = '2026-08-31'
      AND movement.movement_date = '2026-08-31'
      AND lot.receipt_date = '2026-08-31';
    IF v_count <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: tanggal sumber tidak lagi dalam kondisi awal.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM inv_division_monthly_stock august_stock
    JOIN inv_division_monthly_stock september_stock ON september_stock.id = 8255
    WHERE august_stock.id = 6433
      AND august_stock.month_key = '2026-08-01'
      AND ABS(august_stock.adjustment_plus_qty_content - 3270.3000) < 0.0001
      AND ABS(august_stock.adjustment_plus_total_value - 62135.70) < 0.01
      AND ABS(august_stock.closing_qty_content - 609) < 0.0001
      AND ABS(august_stock.total_value - 11571) < 0.01
      AND august_stock.mutation_count = 6
      AND august_stock.last_movement_table = 'inv_stock_adjustment'
      AND august_stock.last_movement_id = 3181
      AND september_stock.month_key = '2026-09-01';
    IF v_count <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: ledger Agustus tidak lagi dalam kondisi terverifikasi.';
    END IF;

    IF ABS(v_lot_qty - v_ledger_qty - 609) > 0.0001
       OR ABS(v_lot_value - v_ledger_value - 11571) > 0.01 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Repair KENTANG dibatalkan: gap aktif bukan lagi tepat 609 GR/Rp11.571.';
    END IF;
  ELSE
    IF v_audit_count <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Validasi akhir KENTANG gagal: audit marker belum tersimpan.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM inv_stock_adjustment header
    JOIN inv_stock_movement_log movement ON movement.id = 73083
    JOIN inv_material_fifo_lot lot ON lot.id = 8879
    WHERE header.id = 3181
      AND header.adjustment_date = '2026-09-01'
      AND movement.movement_date = '2026-09-01'
      AND lot.receipt_date = '2026-09-01';
    IF v_count <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Validasi akhir KENTANG gagal: tanggal bisnis belum lurus ke 1 September.';
    END IF;

    SELECT COUNT(*) INTO v_count
    FROM inv_division_monthly_stock august_stock
    WHERE august_stock.id = 6433
      AND ABS(august_stock.adjustment_plus_qty_content - 2661.3000) < 0.0001
      AND ABS(august_stock.adjustment_plus_total_value - 50564.70) < 0.01
      AND ABS(august_stock.closing_qty_content) < 0.0001
      AND ABS(august_stock.total_value) < 0.01
      AND august_stock.mutation_count = 5
      AND august_stock.last_movement_table = 'inv_component_batch'
      AND august_stock.last_movement_id = 1859;
    IF v_count <> 1 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Validasi akhir KENTANG gagal: ledger Agustus belum kembali ke nol.';
    END IF;

    IF ABS(v_lot_qty - v_ledger_qty) > 0.0001
       OR ABS(v_lot_value - v_ledger_value) > 0.01 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Validasi akhir KENTANG gagal: ledger September belum sama dengan FIFO.';
    END IF;

    SELECT movement.qty_content_after INTO v_latest_after
    FROM inv_stock_movement_log movement
    WHERE movement.movement_scope = 'DIVISION'
      AND movement.division_id = 3
      AND movement.destination_type = 'KITCHEN'
      AND movement.item_id = 102
      AND movement.material_id = 116
      AND movement.content_uom_id = 11
      AND movement.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
      AND movement.movement_date >= '2026-09-01'
      AND movement.movement_date < '2026-10-01'
    ORDER BY movement.movement_date DESC, movement.created_at DESC, movement.id DESC
    LIMIT 1;
    IF ABS(v_latest_after - v_ledger_qty) > 0.0001 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Validasi akhir KENTANG gagal: running balance movement belum sama dengan ledger.';
    END IF;
  END IF;
END$$
DELIMITER ;

CALL sp_repair_kentang_20260901_assert('BEFORE');

SET @repair_applied := (
  SELECT COUNT(*)
  FROM aud_transaction_log
  WHERE action_code = 'REPAIR_BACKDATED_ROLLOVER'
    AND entity_table = 'inv_stock_adjustment'
    AND entity_id = 3181
    AND notes LIKE CONCAT(@repair_code, '%')
);

DROP PROCEDURE IF EXISTS sp_repair_kentang_20260901_apply;
DELIMITER $$
CREATE PROCEDURE sp_repair_kentang_20260901_apply()
BEGIN
  DECLARE v_sep_closing_qty DECIMAL(18,4) DEFAULT 0;
  DECLARE v_sep_total_value DECIMAL(18,2) DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  IF @repair_applied = 0 THEN
    SELECT closing_qty_content, total_value
    INTO v_sep_closing_qty, v_sep_total_value
    FROM inv_division_monthly_stock
    WHERE id = 8255
    FOR UPDATE;

    UPDATE inv_stock_adjustment
    SET
      adjustment_date = '2026-09-01',
      notes = LEFT(CONCAT_WS(
        ' | ',
        NULLIF(notes, ''),
        CONCAT(@repair_code, ': tanggal bisnis dipindah dari 2026-08-31 ke 2026-09-01 setelah rollover bulanan.')
      ), 255),
      updated_at = @repair_now
    WHERE id = 3181
      AND adjustment_date = '2026-08-31';

    UPDATE inv_stock_movement_log
    SET
      movement_date = '2026-09-01',
      notes = LEFT(CONCAT_WS(
        ' | ',
        NULLIF(notes, ''),
        CONCAT(@repair_code, ': event adjustment dipindah ke periode aktif.')
      ), 255)
    WHERE id = 73083
      AND movement_date = '2026-08-31';

    UPDATE inv_material_fifo_lot
    SET receipt_date = '2026-09-01'
    WHERE id = 8879
      AND receipt_date = '2026-08-31';

    -- Keluarkan adjustment terlambat dari Agustus. Semua movement Agustus
    -- lain membentuk saldo akhir nol sebelum adjustment #3181 dibuat.
    UPDATE inv_division_monthly_stock
    SET
      adjustment_plus_qty_buy = 0.8871,
      adjustment_plus_qty_content = 2661.3000,
      adjustment_plus_total_value = 50564.70,
      closing_qty_buy = 0.0000,
      closing_qty_content = 0.0000,
      avg_cost_per_content = 0.000000,
      total_value = 0.00,
      mutation_count = 5,
      last_movement_date = '2026-08-31',
      last_movement_at = '2026-08-31 17:24:55',
      last_movement_table = 'inv_component_batch',
      last_movement_id = 1859,
      notes = 'Batch ICB202608310005 pakai lot LOT20260831-6B9C4691E77F',
      updated_at = @repair_now
    WHERE id = 6433;

    -- Masukkan delta yang sama ke September. Nilai awal disimpan sebelum
    -- UPDATE agar formula tetap benar walaupun ada movement baru setelah audit.
    UPDATE inv_division_monthly_stock
    SET
      adjustment_plus_qty_buy = ROUND(adjustment_plus_qty_buy + 0.2030, 4),
      adjustment_plus_qty_content = ROUND(adjustment_plus_qty_content + 609.0000, 4),
      adjustment_plus_total_value = ROUND(adjustment_plus_total_value + 11571.00, 2),
      closing_qty_buy = ROUND((v_sep_closing_qty + 609.0000) / 3000.000000, 4),
      closing_qty_content = ROUND(v_sep_closing_qty + 609.0000, 4),
      avg_cost_per_content = CASE
        WHEN v_sep_closing_qty + 609.0000 > 0.0001
          THEN ROUND((v_sep_total_value + 11571.00) / (v_sep_closing_qty + 609.0000), 6)
        ELSE 0.000000
      END,
      total_value = ROUND(v_sep_total_value + 11571.00, 2),
      mutation_count = mutation_count + 1,
      updated_at = @repair_now
    WHERE id = 8255;

    -- Setiap movement September sesudah adjustment dibuat membaca saldo yang
    -- kurang 609 GR. Tambahkan carry tersebut sekali saja pada running balance.
    UPDATE inv_stock_movement_log
    SET
      qty_buy_after = ROUND((qty_content_after + 609.0000) / 3000.000000, 4),
      qty_content_after = ROUND(qty_content_after + 609.0000, 4),
      notes = LEFT(CONCAT_WS(
        ' | ',
        NULLIF(notes, ''),
        CONCAT(@repair_code, ': running balance ditambah 609 GR dari adjustment September.')
      ), 255)
    WHERE movement_scope = 'DIVISION'
      AND division_id = 3
      AND destination_type = 'KITCHEN'
      AND item_id = 102
      AND material_id = 116
      AND content_uom_id = 11
      AND profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
      AND movement_date >= '2026-09-01'
      AND movement_date < '2026-10-01'
      AND id <> 73083
      AND (
        movement_date > '2026-09-01'
        OR created_at > @repair_adjustment_created_at
        OR (created_at = @repair_adjustment_created_at AND id > 73083)
      );

    INSERT INTO aud_transaction_log (
      module_code, action_code, entity_table, entity_id, transaction_no,
      ref_table, ref_id, actor_user_id, before_payload, after_payload,
      notes, created_at
    ) VALUES (
      'INVENTORY',
      'REPAIR_BACKDATED_ROLLOVER',
      'inv_stock_adjustment',
      3181,
      'IAD20260831-5521',
      'inv_material_fifo_lot',
      8879,
      @repair_actor_user_id,
      '{"event_month":"2026-08","august_closing":609,"september_closing":-20,"fifo_balance":589}',
      '{"event_month":"2026-09","august_closing":0,"september_closing":589,"fifo_balance":589}',
      CONCAT(@repair_code, ': pindah adjustment 609 GR ke September; lot dan HPP tidak berubah.'),
      @repair_now
    );
  END IF;

  CALL sp_repair_kentang_20260901_assert('AFTER');
  COMMIT;
END$$
DELIMITER ;

CALL sp_repair_kentang_20260901_apply();

DROP PROCEDURE IF EXISTS sp_repair_kentang_20260901_apply;
DROP PROCEDURE IF EXISTS sp_repair_kentang_20260901_assert;

-- ============================================================
-- HASIL SESUDAH APPLY
-- ============================================================
SELECT
  header.id,
  header.adjustment_no,
  header.adjustment_date,
  header.status,
  line.qty_adjustment_plus_content,
  line.unit_cost,
  header.notes
FROM inv_stock_adjustment header
JOIN inv_stock_adjustment_line line ON line.adjustment_id = header.id
WHERE header.id = 3181;

SELECT
  stock.id,
  stock.month_key,
  stock.opening_qty_content,
  stock.out_qty_content,
  stock.adjustment_plus_qty_content,
  stock.closing_qty_content,
  stock.avg_cost_per_content,
  stock.total_value,
  stock.mutation_count,
  stock.last_movement_table,
  stock.last_movement_id
FROM inv_division_monthly_stock stock
WHERE stock.id IN (6433, 8255)
ORDER BY stock.month_key;

SELECT
  movement.id,
  movement.movement_no,
  movement.movement_date,
  movement.movement_type,
  movement.qty_content_delta,
  movement.qty_content_after,
  movement.notes
FROM inv_stock_movement_log movement
WHERE movement.id IN (73083, 73295)
ORDER BY movement.created_at, movement.id;

SELECT
  lot.id,
  lot.lot_no,
  lot.receipt_date,
  lot.qty_in,
  lot.qty_out,
  lot.qty_balance,
  lot.unit_cost,
  ROUND(lot.qty_balance * lot.unit_cost, 2) AS lot_value
FROM inv_material_fifo_lot lot
WHERE lot.id = 8879;

-- Query ini idealnya menghasilkan gap nol.
SELECT
  stock.closing_qty_content AS ledger_qty,
  lot_summary.lot_qty,
  ROUND(stock.closing_qty_content - lot_summary.lot_qty, 4) AS qty_gap,
  stock.total_value AS ledger_value,
  lot_summary.lot_value,
  ROUND(stock.total_value - lot_summary.lot_value, 2) AS value_gap
FROM inv_division_monthly_stock stock
JOIN (
  SELECT
    ROUND(SUM(lot.qty_balance), 4) AS lot_qty,
    ROUND(SUM(lot.qty_balance * lot.unit_cost), 2) AS lot_value
  FROM inv_material_fifo_lot lot
  WHERE lot.location_scope = 'DIVISION'
    AND lot.division_id = 3
    AND lot.destination_type = 'KITCHEN'
    AND lot.item_id = 102
    AND lot.material_id = 116
    AND lot.content_uom_id = 11
    AND lot.profile_key = 'dd7cd6b4348b987ed6b6a0e6f76c02e1bdb75a8b90744fbaa3fbba9b8f1c900f'
) lot_summary
WHERE stock.id = 8255;
