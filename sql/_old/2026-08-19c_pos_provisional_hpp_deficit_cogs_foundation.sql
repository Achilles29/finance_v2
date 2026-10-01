SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19c_pos_provisional_hpp_deficit_cogs_foundation.sql
-- Tujuan :
-- 1) Menyimpan koreksi HPP saat defisit POS ditutup oleh barang nyata
-- 2) Menjaga HPP snapshot transaksi POS tetap immutable/audit-friendly
-- 3) Memisahkan biaya sementara saat jual dari selisih biaya aktual receipt
--
-- Aturan pengakuan:
-- - Bila defisit dan penyelesaian berada pada bulan yang sama dan period
--   finance belum CLOSED, koreksi dibaca pada tanggal transaksi awal.
-- - Bila beda bulan atau bulan transaksi telah CLOSED, koreksi dibaca pada
--   tanggal penyelesaian sebagai koreksi HPP periode berjalan.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_stock_deficit_cogs_adjustment (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deficit_id BIGINT UNSIGNED NOT NULL,
  deficit_settlement_id BIGINT UNSIGNED NOT NULL,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  order_line_id BIGINT UNSIGNED NULL,
  stock_commit_id BIGINT UNSIGNED NULL,
  stock_commit_line_id BIGINT UNSIGNED NULL,
  operational_division_id BIGINT UNSIGNED NULL,
  sale_date DATE NOT NULL,
  settlement_date DATE NOT NULL,
  recognition_date DATE NOT NULL,
  recognition_period_month DATE NOT NULL,
  recognition_policy ENUM('SALE_MONTH_OPEN','SETTLEMENT_MONTH') NOT NULL DEFAULT 'SETTLEMENT_MONTH',
  qty_adjusted DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  provisional_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  provisional_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  actual_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  variance_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NULL,
  voided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_deficit_cogs_settlement (deficit_settlement_id),
  KEY idx_inv_deficit_cogs_recognition (status, recognition_date, operational_division_id),
  KEY idx_inv_deficit_cogs_order (order_id, order_line_id),
  KEY idx_inv_deficit_cogs_commit_line (stock_commit_id, stock_commit_line_id),
  CONSTRAINT fk_inv_deficit_cogs_deficit FOREIGN KEY (deficit_id) REFERENCES inv_stock_deficit(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_deficit_cogs_settlement FOREIGN KEY (deficit_settlement_id) REFERENCES inv_stock_deficit_settlement(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_deficit_cogs_order FOREIGN KEY (order_id) REFERENCES pos_order(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_order_line FOREIGN KEY (order_line_id) REFERENCES pos_order_line(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_commit FOREIGN KEY (stock_commit_id) REFERENCES pos_stock_commit(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_commit_line FOREIGN KEY (stock_commit_line_id) REFERENCES pos_stock_commit_line(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_division FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_deficit_cogs_voided_by FOREIGN KEY (voided_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_deficit_cogs_reversal (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reversal_key CHAR(64) NOT NULL,
  cogs_adjustment_id BIGINT UNSIGNED NOT NULL,
  deficit_id BIGINT UNSIGNED NOT NULL,
  reversal_date DATE NOT NULL,
  qty_reversed DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  provisional_amount_reversed DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  actual_amount_reversed DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  variance_amount_reversed DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  source_document_type VARCHAR(30) NOT NULL,
  source_document_id BIGINT UNSIGNED NULL,
  source_document_no VARCHAR(80) NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_deficit_cogs_reversal_key (reversal_key),
  KEY idx_inv_deficit_cogs_reversal_adjustment (cogs_adjustment_id, reversal_date),
  KEY idx_inv_deficit_cogs_reversal_source (source_document_type, source_document_id),
  CONSTRAINT fk_inv_deficit_cogs_reversal_adjustment FOREIGN KEY (cogs_adjustment_id) REFERENCES inv_stock_deficit_cogs_adjustment(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_deficit_cogs_reversal_deficit FOREIGN KEY (deficit_id) REFERENCES inv_stock_deficit(id) ON DELETE RESTRICT,
  CONSTRAINT fk_inv_deficit_cogs_reversal_created_by FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'inv_stock_deficit_cogs_adjustment' AS table_name, COUNT(*) AS total_rows
FROM inv_stock_deficit_cogs_adjustment
UNION ALL
SELECT 'inv_stock_deficit_cogs_reversal', COUNT(*)
FROM inv_stock_deficit_cogs_reversal;
