SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-19e_inventory_stock_value_reconciliation_foundation.sql
-- Tujuan :
-- 1) Menyediakan dokumen koreksi NILAI stok aktif tanpa mengubah qty
-- 2) Menyimpan rincian biaya lot OPEN yang berubah
-- 3) Menjaga lot CLOSED tetap sebagai histori dan tidak disentuh
--
-- Dipakai hanya jika Stock Health menunjukkan:
-- - jumlah stok sistem = jumlah lot OPEN
-- - tetapi nilai stok sistem != nilai lot OPEN
--
-- Bukan pengganti recon fisik. Selisih jumlah tetap diselesaikan melalui
-- hitung fisik / adjustment resmi.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS inv_stock_value_reconciliation (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  revaluation_no VARCHAR(80) NOT NULL,
  revaluation_date DATE NOT NULL,
  period_month DATE NOT NULL,
  stock_domain ENUM('MATERIAL','COMPONENT') NOT NULL,
  stock_scope VARCHAR(30) NOT NULL,
  division_id BIGINT UNSIGNED NULL,
  location_type VARCHAR(30) NULL,
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,
  component_id BIGINT UNSIGNED NULL,
  buy_uom_id BIGINT UNSIGNED NULL,
  content_uom_id BIGINT UNSIGNED NULL,
  profile_key CHAR(64) NULL,
  monthly_stock_id BIGINT UNSIGNED NOT NULL,
  stock_qty_snapshot DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  lot_qty_snapshot DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  stock_value_before DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  lot_value_before DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  stock_value_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  lot_value_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  resolution_mode ENUM('LOT_TO_STOCK','STOCK_TO_LOT','MANUAL_TOTAL_VALUE') NOT NULL,
  reason VARCHAR(120) NOT NULL,
  notes VARCHAR(255) NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  created_by BIGINT UNSIGNED NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  voided_by BIGINT UNSIGNED NULL,
  voided_at DATETIME NULL,
  void_notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_inv_stock_value_reconciliation_no (revaluation_no),
  KEY idx_inv_stock_value_reconciliation_period (period_month, stock_domain, status),
  KEY idx_inv_stock_value_reconciliation_identity (stock_domain, stock_scope, division_id, location_type, item_id, material_id, component_id, content_uom_id, profile_key),
  KEY idx_inv_stock_value_reconciliation_monthly_stock (monthly_stock_id),
  KEY idx_inv_stock_value_reconciliation_created_by (created_by),
  CONSTRAINT fk_inv_stock_value_reconciliation_created_by
    FOREIGN KEY (created_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_value_reconciliation_posted_by
    FOREIGN KEY (posted_by) REFERENCES auth_user(id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_stock_value_reconciliation_voided_by
    FOREIGN KEY (voided_by) REFERENCES auth_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inv_stock_value_reconciliation_lot (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  revaluation_id BIGINT UNSIGNED NOT NULL,
  lot_id BIGINT UNSIGNED NOT NULL,
  qty_balance_snapshot DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  old_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  new_unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
  old_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  new_total_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inv_stock_value_reconciliation_lot_header (revaluation_id, id),
  KEY idx_inv_stock_value_reconciliation_lot_lot (lot_id),
  CONSTRAINT fk_inv_stock_value_reconciliation_lot_header
    FOREIGN KEY (revaluation_id) REFERENCES inv_stock_value_reconciliation(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT 'inv_stock_value_reconciliation' AS table_name, COUNT(*) AS total_rows
FROM inv_stock_value_reconciliation
UNION ALL
SELECT 'inv_stock_value_reconciliation_lot', COUNT(*)
FROM inv_stock_value_reconciliation_lot;
