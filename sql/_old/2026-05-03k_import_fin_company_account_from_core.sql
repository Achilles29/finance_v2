SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6K - Import akun perusahaan dari database core
-- Source : core.m_bank_account + core.pos_payment_method
-- Target : finance.fin_company_account
-- Catatan: Jalankan setelah tabel fin_company_account sudah ada
-- ============================================================
START TRANSACTION;

INSERT INTO fin_company_account (
  account_code,
  account_name,
  account_type,
  bank_name,
  account_no,
  account_holder,
  currency_code,
  opening_balance,
  current_balance,
  is_default,
  notes,
  is_active
)
SELECT
  CONCAT('CORE-', UPPER(REPLACE(REPLACE(COALESCE(src.account_no, CONCAT('ACC', src.id)), ' ', ''), '-', ''))) AS account_code,
  src.account_name,
  CASE
    WHEN COALESCE(pm.has_cash, 0) = 1 THEN 'CASH'
    WHEN COALESCE(pm.has_ewallet, 0) = 1 THEN 'EWALLET'
    WHEN COALESCE(pm.has_bank, 0) = 1 THEN 'BANK'
    WHEN UPPER(COALESCE(src.bank_name, '')) IN ('TUNAI', 'CASH', 'KAS', 'BRANKAS') THEN 'CASH'
    WHEN UPPER(COALESCE(src.bank_name, '')) LIKE '%MIDTRANS%' THEN 'EWALLET'
    ELSE 'BANK'
  END AS account_type,
  src.bank_name,
  src.account_no,
  src.account_name AS account_holder,
  'IDR' AS currency_code,
  0 AS opening_balance,
  0 AS current_balance,
  CASE WHEN src.id = 1 THEN 1 ELSE 0 END AS is_default,
  CONCAT('Imported from core.m_bank_account id=', src.id) AS notes,
  src.is_active
FROM core.m_bank_account src
LEFT JOIN (
  SELECT
    bank_account_id,
    MAX(CASE WHEN method_type = 'CASH' THEN 1 ELSE 0 END) AS has_cash,
    MAX(CASE WHEN method_type = 'EWALLET' THEN 1 ELSE 0 END) AS has_ewallet,
    MAX(CASE WHEN method_type IN ('BANK', 'QRIS') THEN 1 ELSE 0 END) AS has_bank
  FROM core.pos_payment_method
  WHERE bank_account_id IS NOT NULL
  GROUP BY bank_account_id
) pm ON pm.bank_account_id = src.id
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  bank_name = VALUES(bank_name),
  account_no = VALUES(account_no),
  account_holder = VALUES(account_holder),
  currency_code = VALUES(currency_code),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT id, account_code, account_name, account_type, bank_name, account_no, is_active
FROM fin_company_account
WHERE account_code LIKE 'CORE-%'
ORDER BY id;
