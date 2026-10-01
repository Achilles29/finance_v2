SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-12e_void_test_cash_advance_ca_202606_0001.sql
-- Tujuan :
-- 1) VOID kasbon test CA-202606-0001 milik BAGAS BHAKTI .R
-- 2) Kasus ini aman di-VOID karena:
--    - tidak ada cicilan / pembayaran
--    - tidak ada mutasi rekening keluar
--    - tidak ada potongan payroll terkait
-- 3) Menjaga recap kasbon tetap merepresentasikan hutang riil
-- ============================================================

START TRANSACTION;

UPDATE pay_cash_advance
SET
  status = 'VOID',
  outstanding_amount = 0.00,
  notes = LEFT(TRIM(CONCAT(
    COALESCE(notes, ''),
    CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END,
    'VOID test cleanup 2026-06-12 | no installment | no cash mutation'
  )), 255),
  updated_at = NOW()
WHERE id = 1
  AND advance_no = 'CA-202606-0001'
  AND status = 'SETTLED'
  AND NOT EXISTS (
    SELECT 1
    FROM pay_cash_advance_installment i
    WHERE i.cash_advance_id = pay_cash_advance.id
      AND COALESCE(i.paid_amount, 0) > 0
  )
  AND NOT EXISTS (
    SELECT 1
    FROM fin_account_mutation_log m
    WHERE m.ref_table = 'pay_cash_advance'
      AND m.ref_id = pay_cash_advance.id
      AND m.mutation_type = 'OUT'
  );

COMMIT;

SELECT id, advance_no, status, amount, outstanding_amount, notes
FROM pay_cash_advance
WHERE id = 1;
