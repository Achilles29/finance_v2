SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-05d_fix_pos_payment_close_time_pos_20260604_0004.sql
-- Tujuan :
-- 1) Memundurkan jam pembayaran POS-20260604-0004 ke 4 Juni 2026 jam 22:00:00
-- 2) Menyelaraskan jejak order, payment, payment line, state log, shift, dan cashier session
-- 3) TIDAK menyentuh stok karena order ini tidak memiliki pos_stock_commit
--
-- Catatan penting:
-- - Script ini sengaja tidak mengubah nomor order / nomor payment.
-- - Script ini diasumsikan aman karena cashier_session_id=4 / shift_id=4 tidak punya
--   transaksi lain pada pagi 5 Juni 2026 selain pembayaran order ini.
-- - Review hasil precheck dulu sebelum COMMIT.
-- ============================================================

START TRANSACTION;

SET @target_order_no := 'POS-20260604-0004';
SET @target_paid_at := '2026-06-04 22:00:00';

DROP TEMPORARY TABLE IF EXISTS tmp_pos_fix_target;

CREATE TEMPORARY TABLE tmp_pos_fix_target AS
SELECT
  o.id AS order_id,
  o.order_no,
  o.shift_id,
  o.cashier_session_id,
  p.id AS payment_id
FROM pos_order o
LEFT JOIN pos_payment p ON p.order_id = o.id
WHERE o.order_no = @target_order_no
LIMIT 1;

-- ============================================================
-- PRECHECK
-- ============================================================
SELECT
  'target_found' AS check_key,
  COUNT(*) AS total_rows
FROM tmp_pos_fix_target;

SELECT
  o.id,
  o.order_no,
  o.status,
  o.stock_commit_status,
  o.ordered_at,
  o.paid_at,
  o.created_at,
  o.updated_at,
  o.shift_id,
  o.cashier_session_id
FROM pos_order o
JOIN tmp_pos_fix_target t ON t.order_id = o.id;

SELECT
  p.id,
  p.payment_no,
  p.payment_status,
  p.paid_at,
  p.created_at,
  p.updated_at,
  p.net_amount
FROM pos_payment p
JOIN tmp_pos_fix_target t ON t.payment_id = p.id;

SELECT
  pl.id,
  pl.payment_id,
  pl.payment_method_id,
  pl.amount,
  pl.received_at,
  pl.created_at,
  pl.updated_at,
  pl.status
FROM pos_payment_line pl
JOIN tmp_pos_fix_target t ON t.payment_id = pl.payment_id
ORDER BY pl.id;

SELECT
  l.id,
  l.from_status,
  l.to_status,
  l.event_code,
  l.created_at,
  l.notes
FROM pos_order_state_log l
JOIN tmp_pos_fix_target t ON t.order_id = l.order_id
ORDER BY l.id;

SELECT
  s.id,
  s.shift_no,
  s.status,
  s.opened_at,
  s.closed_at,
  s.created_at,
  s.updated_at
FROM pos_shift s
JOIN tmp_pos_fix_target t ON t.shift_id = s.id;

SELECT
  cs.id,
  cs.session_key,
  cs.session_status,
  cs.login_at,
  cs.logout_at,
  cs.last_ping_at,
  cs.created_at,
  cs.updated_at
FROM pos_cashier_session cs
JOIN tmp_pos_fix_target t ON t.cashier_session_id = cs.id;

SELECT
  COUNT(*) AS stock_commit_rows
FROM pos_stock_commit sc
JOIN tmp_pos_fix_target t ON t.order_id = sc.order_id;

-- ============================================================
-- GUARD
-- ============================================================
SELECT
  CASE
    WHEN COUNT(*) = 0 THEN 'ERROR: order target tidak ditemukan.'
    ELSE 'OK'
  END AS guard_target
FROM tmp_pos_fix_target;

SELECT
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM pos_stock_commit sc
      JOIN tmp_pos_fix_target t ON t.order_id = sc.order_id
    )
    THEN 'ERROR: order punya stock commit, review manual dulu sebelum repair.'
    ELSE 'OK'
  END AS guard_stock_commit;

-- ============================================================
-- REPAIR DATA
-- ============================================================

-- 1) Order header
UPDATE pos_order o
JOIN tmp_pos_fix_target t ON t.order_id = o.id
SET
  o.paid_at = @target_paid_at,
  o.updated_at = @target_paid_at
WHERE o.status = 'PAID';

-- 2) Payment header
UPDATE pos_payment p
JOIN tmp_pos_fix_target t ON t.payment_id = p.id
SET
  p.paid_at = @target_paid_at,
  p.created_at = @target_paid_at,
  p.updated_at = @target_paid_at
WHERE p.payment_status = 'PAID';

-- 3) Payment line
UPDATE pos_payment_line pl
JOIN tmp_pos_fix_target t ON t.payment_id = pl.payment_id
SET
  pl.received_at = @target_paid_at,
  pl.created_at = @target_paid_at,
  pl.updated_at = @target_paid_at
WHERE pl.status = 'PAID';

-- 4) State log payment order
UPDATE pos_order_state_log l
JOIN tmp_pos_fix_target t ON t.order_id = l.order_id
SET l.created_at = @target_paid_at
WHERE l.to_status = 'PAID'
  AND l.event_code = 'ORDER_PAYMENT';

-- 5) Shift close
UPDATE pos_shift s
JOIN tmp_pos_fix_target t ON t.shift_id = s.id
SET
  s.closed_at = @target_paid_at,
  s.updated_at = @target_paid_at
WHERE s.status = 'CLOSED';

-- 6) Cashier session close
UPDATE pos_cashier_session cs
JOIN tmp_pos_fix_target t ON t.cashier_session_id = cs.id
SET
  cs.logout_at = @target_paid_at,
  cs.last_ping_at = @target_paid_at,
  cs.updated_at = @target_paid_at
WHERE cs.session_status = 'CLOSED';

-- 7) Shift summary timestamps
UPDATE pos_shift_summary ss
JOIN tmp_pos_fix_target t ON t.shift_id = ss.shift_id
SET ss.updated_at = @target_paid_at;

-- 8) Shift account summary timestamps
UPDATE pos_shift_account_summary sas
JOIN tmp_pos_fix_target t ON t.shift_id = sas.shift_id
SET
  sas.created_at = @target_paid_at,
  sas.updated_at = @target_paid_at;

-- 9) Cash denomination timestamps saat close kasir
UPDATE pos_shift_cash_denomination d
JOIN tmp_pos_fix_target t ON t.shift_id = d.shift_id
SET
  d.created_at = @target_paid_at,
  d.updated_at = @target_paid_at;

-- ============================================================
-- POSTCHECK
-- ============================================================
SELECT
  o.id,
  o.order_no,
  o.status,
  o.paid_at,
  o.updated_at
FROM pos_order o
JOIN tmp_pos_fix_target t ON t.order_id = o.id;

SELECT
  p.id,
  p.payment_no,
  p.payment_status,
  p.paid_at,
  p.created_at,
  p.updated_at
FROM pos_payment p
JOIN tmp_pos_fix_target t ON t.payment_id = p.id;

SELECT
  pl.id,
  pl.payment_id,
  pl.received_at,
  pl.created_at,
  pl.updated_at,
  pl.status
FROM pos_payment_line pl
JOIN tmp_pos_fix_target t ON t.payment_id = pl.payment_id
ORDER BY pl.id;

SELECT
  l.id,
  l.to_status,
  l.event_code,
  l.created_at
FROM pos_order_state_log l
JOIN tmp_pos_fix_target t ON t.order_id = l.order_id
ORDER BY l.id;

SELECT
  s.id,
  s.shift_no,
  s.status,
  s.opened_at,
  s.closed_at,
  s.updated_at
FROM pos_shift s
JOIN tmp_pos_fix_target t ON t.shift_id = s.id;

SELECT
  cs.id,
  cs.session_key,
  cs.session_status,
  cs.login_at,
  cs.logout_at,
  cs.last_ping_at,
  cs.updated_at
FROM pos_cashier_session cs
JOIN tmp_pos_fix_target t ON t.cashier_session_id = cs.id;

SELECT
  ss.shift_id,
  ss.total_order_count,
  ss.total_net_sales,
  ss.updated_at
FROM pos_shift_summary ss
JOIN tmp_pos_fix_target t ON t.shift_id = ss.shift_id;

SELECT
  sas.shift_id,
  sas.account_code,
  sas.account_name,
  sas.net_amount,
  sas.created_at,
  sas.updated_at
FROM pos_shift_account_summary sas
JOIN tmp_pos_fix_target t ON t.shift_id = sas.shift_id
ORDER BY sas.sort_order, sas.id;

SELECT
  d.shift_id,
  d.denomination_amount,
  d.qty_count,
  d.total_amount,
  d.created_at,
  d.updated_at
FROM pos_shift_cash_denomination d
JOIN tmp_pos_fix_target t ON t.shift_id = d.shift_id
ORDER BY d.sort_order, d.id;

COMMIT;

SELECT
  'Catatan' AS info_key,
  'payment_no / order_no tidak diubah. Fokus script ini hanya menyelaraskan timestamp jejak pembayaran & close kasir.' AS info_value;
