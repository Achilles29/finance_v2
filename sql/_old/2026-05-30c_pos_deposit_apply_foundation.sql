SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-30c_pos_deposit_apply_foundation.sql
-- Tujuan :
-- 1) Menyiapkan relasi aplikasi DP/deposit terhadap payment final
-- 2) Menjadi fondasi audit sisa deposit sebelum dihubungkan penuh ke kasir
-- 3) Aman dijalankan ulang pada database yang sudah punya tabel POS payment
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_payment_deposit_apply (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deposit_payment_id BIGINT UNSIGNED NOT NULL,
  applied_payment_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  apply_status ENUM('APPLIED','VOID') NOT NULL DEFAULT 'APPLIED',
  notes VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_deposit_apply_pair (deposit_payment_id, applied_payment_id),
  KEY idx_pos_deposit_apply_order (order_id),
  KEY idx_pos_deposit_apply_status (apply_status),
  CONSTRAINT fk_pos_deposit_apply_deposit_payment FOREIGN KEY (deposit_payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_deposit_apply_applied_payment FOREIGN KEY (applied_payment_id) REFERENCES pos_payment(id),
  CONSTRAINT fk_pos_deposit_apply_order FOREIGN KEY (order_id) REFERENCES pos_order(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

SELECT 'pos_payment_deposit_apply' AS table_name, COUNT(*) AS total_rows
FROM pos_payment_deposit_apply;
