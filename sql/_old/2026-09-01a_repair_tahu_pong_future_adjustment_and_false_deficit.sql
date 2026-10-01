SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-09-01a_repair_tahu_pong_future_adjustment_and_false_deficit.sql
-- Tujuan :
-- 1) Memindahkan jejak adjustment TAHU PONG yang salah tanggal dari
--    2026-09-30 ke tanggal kejadian sebenarnya, 2026-09-01.
-- 2) Me-VOID adjustment minus kedua yang tidak menghasilkan movement.
-- 3) Me-VOID defisit palsu yang dibuat oleh adjustment minus tersebut.
-- 4) Menjaga lot, biaya, dan audit trail yang sudah benar.
--
-- Aman dijalankan berulang:
-- - Preflight hanya menerima identitas kasus yang telah diaudit.
-- - UPDATE memakai kondisi keadaan lama maupun hasil repair.
-- - Nomor dokumen lama dipertahankan sebagai bukti audit; tanggal bisnis
--   pada header, movement, dan FIFO issue yang diluruskan.
-- - Script tidak membuat stok baru dan tidak mengubah qty lot.
-- ============================================================

SET @repair_code := 'INV-TAHU-PONG-20260901-V1';
SET @repair_actor_user_id := NULL; -- Opsional: isi auth_user.id pelaksana.
SET @repair_now := NOW();

DROP PROCEDURE IF EXISTS sp_repair_tahu_pong_20260901_assert;
DELIMITER $$
CREATE PROCEDURE sp_repair_tahu_pong_20260901_assert()
BEGIN
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_message VARCHAR(255);

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_adjustment h
  JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
  WHERE h.id = 3185
    AND h.adjustment_no = 'IAD20260930-7730'
    AND h.stock_scope = 'DIVISION'
    AND h.division_id = 3
    AND h.destination_type = 'KITCHEN'
    AND h.status = 'POSTED'
    AND h.adjustment_date IN ('2026-09-30', '2026-09-01')
    AND l.id = 3199
    AND l.item_id = 283
    AND l.material_id = 197
    AND l.input_mode = 'DELTA'
    AND ABS(l.qty_variance_content - 13) < 0.0001;
  IF v_count <> 1 THEN
    SET v_message = CONCAT('Repair dibatalkan: adjustment utama TAHU PONG tidak cocok. count=', v_count);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_message;
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_movement_log
  WHERE id = 73259
    AND ref_table = 'inv_stock_adjustment'
    AND ref_id = 3185
    AND movement_type = 'VARIANCE_OUT'
    AND movement_date IN ('2026-09-30', '2026-09-01')
    AND ABS(qty_content_delta + 13) < 0.0001
    AND ABS(qty_content_after) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: movement TAHU PONG #73259 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_material_fifo_issue_log issue_log
  JOIN inv_material_fifo_issue_line issue_line ON issue_line.issue_id = issue_log.id
  WHERE issue_log.id = 65011
    AND issue_log.source_table = 'inv_stock_adjustment'
    AND issue_log.source_id = 3185
    AND issue_log.source_line_id = 3199
    AND issue_log.issue_date IN ('2026-09-30', '2026-09-01')
    AND issue_log.status = 'POSTED'
    AND ABS(issue_log.issue_qty - 13) < 0.0001
    AND issue_line.id = 67663
    AND issue_line.lot_id = 8737
    AND ABS(issue_line.qty_out - 13) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: FIFO issue TAHU PONG #65011 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_adjustment h
  JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
  WHERE h.id = 3191
    AND h.adjustment_no = 'IAD20260901-6188'
    AND h.adjustment_date = '2026-09-01'
    AND h.stock_scope = 'DIVISION'
    AND h.division_id = 3
    AND h.destination_type = 'KITCHEN'
    AND h.status IN ('POSTED', 'VOID')
    AND l.id = 3205
    AND l.item_id = 283
    AND l.material_id = 197
    AND l.input_mode = 'DELTA'
    AND ABS(l.available_qty_content) < 0.0001
    AND ABS(l.qty_variance_content - 13) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: adjustment minus kedua #3191 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_movement_log
  WHERE ref_table = 'inv_stock_adjustment'
    AND ref_id = 3191;
  IF v_count <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: adjustment #3191 ternyata memiliki movement.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_material_fifo_issue_log
  WHERE source_table = 'inv_stock_adjustment'
    AND source_id = 3191;
  IF v_count <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: adjustment #3191 ternyata memiliki FIFO issue.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_stock_deficit
  WHERE id = 185
    AND stock_domain = 'MATERIAL'
    AND source_table = 'inv_stock_adjustment'
    AND source_id = 3191
    AND source_line_id = 3205
    AND item_id = 283
    AND material_id = 197
    AND status IN ('OPEN', 'VOID')
    AND ABS(requested_qty - 13) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: defisit palsu TAHU PONG #185 tidak cocok.';
  END IF;

  SELECT COUNT(*) INTO v_count
  FROM inv_division_monthly_stock
  WHERE id = 8153
    AND month_key = '2026-09-01'
    AND division_id = 3
    AND destination_type = 'KITCHEN'
    AND item_id = 283
    AND material_id = 197
    AND ABS(opening_qty_content - 13) < 0.0001
    AND ABS(variance_qty_content - 13) < 0.0001
    AND ABS(closing_qty_content) < 0.0001;
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair dibatalkan: saldo bulanan TAHU PONG #8153 tidak cocok.';
  END IF;
END$$
DELIMITER ;

CALL sp_repair_tahu_pong_20260901_assert();
DROP PROCEDURE IF EXISTS sp_repair_tahu_pong_20260901_assert;

START TRANSACTION;

UPDATE inv_stock_adjustment
SET
  adjustment_date = '2026-09-01',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE CONCAT('%', @repair_code, '%') THEN notes
    ELSE LEFT(CONCAT_WS(' | ', NULLIF(notes, ''), CONCAT(@repair_code, ': tanggal bisnis diluruskan dari 2026-09-30 ke 2026-09-01; nomor lama dipertahankan untuk audit.')), 255)
  END,
  updated_at = @repair_now
WHERE id = 3185
  AND adjustment_date IN ('2026-09-30', '2026-09-01');

UPDATE inv_stock_movement_log
SET
  movement_date = '2026-09-01',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE CONCAT('%', @repair_code, '%') THEN notes
    ELSE LEFT(CONCAT_WS(' | ', NULLIF(notes, ''), CONCAT(@repair_code, ': event date diperbaiki ke 2026-09-01.')), 255)
  END
WHERE id = 73259
  AND ref_table = 'inv_stock_adjustment'
  AND ref_id = 3185
  AND movement_date IN ('2026-09-30', '2026-09-01');

UPDATE inv_material_fifo_issue_log
SET
  issue_date = '2026-09-01',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE CONCAT('%', @repair_code, '%') THEN notes
    ELSE LEFT(CONCAT_WS(' | ', NULLIF(notes, ''), CONCAT(@repair_code, ': issue date diperbaiki ke 2026-09-01.')), 255)
  END
WHERE id = 65011
  AND source_table = 'inv_stock_adjustment'
  AND source_id = 3185
  AND issue_date IN ('2026-09-30', '2026-09-01');

UPDATE inv_stock_adjustment
SET
  status = 'VOID',
  notes = CASE
    WHEN COALESCE(notes, '') LIKE CONCAT('%', @repair_code, '%') THEN notes
    ELSE LEFT(CONCAT_WS(' | ', NULLIF(notes, ''), CONCAT(@repair_code, ': VOID karena minus kedua tidak memiliki movement/FIFO dan hanya membuat defisit palsu.')), 255)
  END,
  updated_at = @repair_now
WHERE id = 3191
  AND status IN ('POSTED', 'VOID');

UPDATE inv_stock_deficit
SET
  status = 'VOID',
  reversed_qty = ROUND(reversed_qty + qty_remaining, 4),
  qty_remaining = 0,
  estimated_total_value = 0,
  voided_by = COALESCE(voided_by, @repair_actor_user_id),
  voided_at = COALESCE(voided_at, @repair_now),
  notes = CASE
    WHEN COALESCE(notes, '') LIKE CONCAT('%', @repair_code, '%') THEN notes
    ELSE LEFT(CONCAT_WS(' | ', NULLIF(notes, ''), CONCAT('VOID ', @repair_code, ': adjustment operator tidak boleh membuat defisit stok.')), 255)
  END,
  updated_at = @repair_now
WHERE id = 185
  AND source_table = 'inv_stock_adjustment'
  AND source_id = 3191
  AND status = 'OPEN';

-- last_movement_date dihitung kembali dari movement nyata pada identity ini.
UPDATE inv_division_monthly_stock stock
SET
  last_movement_date = (
    SELECT MAX(movement.movement_date)
    FROM inv_stock_movement_log movement
    WHERE movement.movement_scope = 'DIVISION'
      AND movement.division_id = stock.division_id
      AND movement.destination_type = stock.destination_type
      AND movement.item_id = stock.item_id
      AND COALESCE(movement.material_id, 0) = COALESCE(stock.material_id, 0)
      AND movement.content_uom_id = stock.content_uom_id
      AND COALESCE(movement.profile_key, '') = COALESCE(stock.profile_key, '')
      AND movement.movement_date >= stock.month_key
      AND movement.movement_date < DATE_ADD(stock.month_key, INTERVAL 1 MONTH)
  ),
  updated_at = @repair_now
WHERE stock.id = 8153;

INSERT INTO aud_transaction_log (
  module_code, action_code, entity_table, entity_id, transaction_no,
  actor_user_id, before_payload, after_payload, notes, created_at
)
SELECT
  'INVENTORY', 'REPAIR_ADJUSTMENT_DATE', 'inv_stock_adjustment', 3185,
  'IAD20260930-7730', @repair_actor_user_id,
  '{"adjustment_date":"2026-09-30","movement_date":"2026-09-30","issue_date":"2026-09-30"}',
  '{"adjustment_date":"2026-09-01","movement_date":"2026-09-01","issue_date":"2026-09-01"}',
  CONCAT(@repair_code, ': tanggal TAHU PONG diluruskan tanpa mengubah qty atau nilai.'),
  @repair_now
WHERE NOT EXISTS (
  SELECT 1 FROM aud_transaction_log
  WHERE action_code = 'REPAIR_ADJUSTMENT_DATE'
    AND entity_table = 'inv_stock_adjustment'
    AND entity_id = 3185
    AND notes LIKE CONCAT(@repair_code, '%')
);

INSERT INTO aud_transaction_log (
  module_code, action_code, entity_table, entity_id, transaction_no,
  actor_user_id, before_payload, after_payload, notes, created_at
)
SELECT
  'INVENTORY', 'VOID_FALSE_DEFICIT', 'inv_stock_adjustment', 3191,
  'IAD20260901-6188', @repair_actor_user_id,
  '{"adjustment_status":"POSTED","deficit_id":185,"deficit_status":"OPEN","qty_remaining":13}',
  '{"adjustment_status":"VOID","deficit_id":185,"deficit_status":"VOID","qty_remaining":0}',
  CONCAT(@repair_code, ': adjustment kedua dan defisit palsu dinetralkan.'),
  @repair_now
WHERE NOT EXISTS (
  SELECT 1 FROM aud_transaction_log
  WHERE action_code = 'VOID_FALSE_DEFICIT'
    AND entity_table = 'inv_stock_adjustment'
    AND entity_id = 3191
    AND notes LIKE CONCAT(@repair_code, '%')
);

COMMIT;

-- ============================================================
-- HASIL SESUDAH APPLY
-- ============================================================
SELECT
  h.id,
  h.adjustment_no,
  h.adjustment_date,
  h.status,
  l.input_mode,
  l.qty_variance_content,
  h.notes
FROM inv_stock_adjustment h
JOIN inv_stock_adjustment_line l ON l.adjustment_id = h.id
WHERE h.id IN (3185, 3191)
ORDER BY h.id;

SELECT
  id,
  movement_no,
  movement_date,
  movement_type,
  qty_content_delta,
  qty_content_after
FROM inv_stock_movement_log
WHERE id = 73259;

SELECT
  id,
  status,
  requested_qty,
  issued_qty,
  reversed_qty,
  qty_remaining,
  voided_at,
  notes
FROM inv_stock_deficit
WHERE id = 185;

SELECT
  id,
  month_key,
  opening_qty_content,
  variance_qty_content,
  closing_qty_content,
  last_movement_date
FROM inv_division_monthly_stock
WHERE id = 8153;

-- Audit kondisi aktif setelah repair: dua query pertama idealnya kosong.
SELECT
  h.id,
  h.adjustment_no,
  h.adjustment_date,
  h.posted_at,
  h.status
FROM inv_stock_adjustment h
WHERE h.status = 'POSTED'
  AND h.adjustment_date > CURDATE();

SELECT
  d.id,
  d.stock_domain,
  d.deficit_date,
  d.qty_remaining,
  d.source_table,
  d.source_id,
  d.notes
FROM inv_stock_deficit d
WHERE d.status = 'OPEN'
  AND d.source_table IN ('inv_stock_adjustment', 'inv_component_adjustment')
ORDER BY d.deficit_date, d.id;

-- Arsip adjustment yang dahulu diposting sebelum tanggal bisnisnya. Baris
-- lama tetap ditampilkan untuk audit, tetapi tidak diubah otomatis oleh
-- repair TAHU PONG karena tanggal tersebut sudah berlalu dan perlu ditelaah
-- per dokumen bila ditemukan dampak historis.
SELECT
  h.id,
  h.adjustment_no,
  h.adjustment_date,
  h.created_at,
  h.posted_at,
  h.status
FROM inv_stock_adjustment h
WHERE h.status = 'POSTED'
  AND h.adjustment_date > DATE(COALESCE(h.posted_at, h.created_at))
  AND h.adjustment_date <= CURDATE()
ORDER BY h.adjustment_date, h.id;
