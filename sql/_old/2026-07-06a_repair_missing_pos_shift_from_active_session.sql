SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-06a_repair_missing_pos_shift_from_active_session.sql
-- Tujuan :
-- 1) Memulihkan master pos_shift yang hilang setelah import DB
--    namun masih direferensikan oleh pos_cashier_session / order /
--    payment.
-- 2) Menutup kasus payment POS gagal karena pos_payment.shift_id
--    terkena FK ke pos_shift yang tidak ada.
--
-- Catatan:
-- - Scope aman: hanya membuat pos_shift yang dirujuk session, tetapi
--   belum ada di pos_shift.
-- - Tidak mengubah order, payment, stok, atau mutasi rekening.
-- - opening_cash default 300000 mengikuti pola shift kasir harian
--   yang sudah ada di database lokal.
-- ============================================================

START TRANSACTION;

SET @repair_note := 'Repair missing pos_shift from cashier session 2026-07-06';
SET @default_opening_cash := 300000.00;

DROP TEMPORARY TABLE IF EXISTS tmp_missing_pos_shift_from_session;
CREATE TEMPORARY TABLE tmp_missing_pos_shift_from_session AS
SELECT
  cs.shift_id,
  cs.outlet_id,
  cs.terminal_id,
  cs.employee_id AS cashier_open_employee_id,
  cs.login_at AS opened_at,
  cs.created_at,
  cs.session_status,
  CASE
    WHEN EXISTS (
      SELECT 1
      FROM pos_shift x
      WHERE x.shift_no = CONCAT('SHIFT-', DATE_FORMAT(cs.login_at, '%Y%m%d'), '-', LPAD(cs.outlet_id, 2, '0'), '-0001')
      LIMIT 1
    )
      THEN CONCAT('SHIFT-', DATE_FORMAT(cs.login_at, '%Y%m%d'), '-', LPAD(cs.outlet_id, 2, '0'), '-R', cs.shift_id)
    ELSE CONCAT('SHIFT-', DATE_FORMAT(cs.login_at, '%Y%m%d'), '-', LPAD(cs.outlet_id, 2, '0'), '-0001')
  END AS repaired_shift_no
FROM pos_cashier_session cs
LEFT JOIN pos_shift s ON s.id = cs.shift_id
WHERE cs.shift_id IS NOT NULL
  AND cs.shift_id > 0
  AND s.id IS NULL;

ALTER TABLE tmp_missing_pos_shift_from_session
  ADD PRIMARY KEY (shift_id);

INSERT INTO pos_shift (
  id,
  shift_no,
  outlet_id,
  terminal_id,
  cashier_open_employee_id,
  status,
  opened_at,
  opening_cash,
  expected_cash,
  actual_cash,
  variance_cash,
  notes,
  created_at
)
SELECT
  t.shift_id,
  t.repaired_shift_no,
  t.outlet_id,
  t.terminal_id,
  t.cashier_open_employee_id,
  CASE WHEN t.session_status = 'CLOSED' THEN 'CLOSED' ELSE 'OPEN' END,
  t.opened_at,
  @default_opening_cash,
  @default_opening_cash,
  0.00,
  0.00,
  @repair_note,
  COALESCE(t.created_at, t.opened_at, CURRENT_TIMESTAMP)
FROM tmp_missing_pos_shift_from_session t;

INSERT INTO pos_shift_summary (
  shift_id,
  total_order_count,
  total_gross_sales,
  total_discount,
  total_promo,
  total_net_sales,
  total_cash_sales,
  total_non_cash_sales,
  total_refund,
  total_void,
  created_at
)
SELECT
  t.shift_id,
  0,
  0.00,
  0.00,
  0.00,
  0.00,
  0.00,
  0.00,
  0.00,
  0.00,
  CURRENT_TIMESTAMP
FROM tmp_missing_pos_shift_from_session t
LEFT JOIN pos_shift_summary ss ON ss.shift_id = t.shift_id
WHERE ss.shift_id IS NULL;

COMMIT;

SELECT 'missing_shift_rows_repaired' AS metric, COUNT(*) AS total
FROM tmp_missing_pos_shift_from_session

UNION ALL

SELECT 'remaining_missing_session_shift', COUNT(*)
FROM pos_cashier_session cs
LEFT JOIN pos_shift s ON s.id = cs.shift_id
WHERE cs.shift_id IS NOT NULL
  AND cs.shift_id > 0
  AND s.id IS NULL

UNION ALL

SELECT 'remaining_missing_order_shift', COUNT(*)
FROM pos_order o
LEFT JOIN pos_shift s ON s.id = o.shift_id
WHERE o.shift_id IS NOT NULL
  AND o.shift_id > 0
  AND s.id IS NULL

UNION ALL

SELECT 'remaining_missing_payment_shift', COUNT(*)
FROM pos_payment p
LEFT JOIN pos_shift s ON s.id = p.shift_id
WHERE p.shift_id IS NOT NULL
  AND p.shift_id > 0
  AND s.id IS NULL;
