-- Tahap 6 Purchase alignment:
-- Simpan rekening pembayaran langsung di header purchase order

ALTER TABLE pur_purchase_order
  ADD COLUMN IF NOT EXISTS payment_account_id BIGINT UNSIGNED NULL AFTER vendor_id,
  ADD KEY IF NOT EXISTS idx_pur_purchase_order_payment_account (payment_account_id);

SET @has_fk_payment_account := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_order'
    AND CONSTRAINT_NAME = 'fk_pur_purchase_order_payment_account'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk_payment_account := IF(
  @has_fk_payment_account = 0,
  'ALTER TABLE pur_purchase_order ADD CONSTRAINT fk_pur_purchase_order_payment_account FOREIGN KEY (payment_account_id) REFERENCES fin_company_account(id)',
  'SELECT 1'
);
PREPARE stmt_fk_payment_account FROM @sql_fk_payment_account;
EXECUTE stmt_fk_payment_account;
DEALLOCATE PREPARE stmt_fk_payment_account;
