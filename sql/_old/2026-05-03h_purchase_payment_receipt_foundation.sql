SET NAMES utf8mb4;

-- ============================================================
-- Tahap 6H - Payment Plan (Channel/Rekening) + Receipt Foundation
-- Tujuan:
-- 1) Purchase siap simpan rencana pembayaran berbasis channel/rekening
-- 2) Penerimaan PO bisa diarahkan ke gudang atau divisi operasional
-- 3) Tetap simpan dual-uom (qty beli + qty isi) untuk rekonsiliasi fisik
-- ============================================================
START TRANSACTION;

-- ------------------------------------------------------------
-- A. Rencana pembayaran per PO (berbasis channel/rekening)
-- Catatan:
-- - payment_channel_id dan paid_from_account_id dipakai sebagai sumber utama.
-- - payment_method_id dipertahankan nullable untuk kompatibilitas data lama.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS pur_purchase_payment_plan (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  payment_method_id BIGINT UNSIGNED NULL,
  payment_channel_id BIGINT UNSIGNED NULL,
  paid_from_account_id BIGINT UNSIGNED NULL,
  plan_type ENUM('DP','PARTIAL','FULL') NOT NULL DEFAULT 'FULL',
  terms_days INT UNSIGNED NOT NULL DEFAULT 0,
  due_date DATE NULL,
  payment_date DATE NULL,
  planned_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('PLANNED','PARTIAL','PAID','VOID') NOT NULL DEFAULT 'PLANNED',
  reference_no VARCHAR(80) NULL,
  transaction_no VARCHAR(80) NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pur_payment_plan_po (purchase_order_id),
  KEY idx_pur_payment_plan_method (payment_method_id),
  KEY idx_pur_payment_plan_channel (payment_channel_id),
  KEY idx_pur_payment_plan_paid_account (paid_from_account_id),
  KEY idx_pur_payment_plan_txn_no (transaction_no),
  KEY idx_pur_payment_plan_due (due_date),
  KEY idx_pur_payment_plan_status (status),
  CONSTRAINT fk_pur_payment_plan_po FOREIGN KEY (purchase_order_id) REFERENCES pur_purchase_order(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- C. Penerimaan barang dari PO (arah gudang/divisi)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pur_purchase_receipt (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  receipt_no VARCHAR(50) NOT NULL,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  receipt_date DATE NOT NULL,

  destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NOT NULL,
  destination_division_id BIGINT UNSIGNED NULL,

  status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'DRAFT',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_pur_purchase_receipt_no (receipt_no),
  KEY idx_pur_purchase_receipt_po (purchase_order_id),
  KEY idx_pur_purchase_receipt_date (receipt_date),
  KEY idx_pur_purchase_receipt_destination_div (destination_division_id),
  KEY idx_pur_purchase_receipt_status (status),

  CONSTRAINT fk_pur_purchase_receipt_po FOREIGN KEY (purchase_order_id) REFERENCES pur_purchase_order(id),
  CONSTRAINT fk_pur_purchase_receipt_destination_div FOREIGN KEY (destination_division_id) REFERENCES mst_operational_division(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pur_purchase_receipt_line (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_receipt_id BIGINT UNSIGNED NOT NULL,
  purchase_order_line_id BIGINT UNSIGNED NOT NULL,

  line_kind ENUM('ITEM','MATERIAL','SERVICE','ASSET') NOT NULL DEFAULT 'ITEM',
  item_id BIGINT UNSIGNED NULL,
  material_id BIGINT UNSIGNED NULL,

  qty_buy_received DECIMAL(18,4) NOT NULL DEFAULT 0,
  buy_uom_id BIGINT UNSIGNED NOT NULL,
  qty_content_received DECIMAL(18,4) NOT NULL DEFAULT 0,
  content_uom_id BIGINT UNSIGNED NULL,
  conversion_factor_to_content DECIMAL(18,8) NOT NULL DEFAULT 1,

  brand_name VARCHAR(120) NULL,
  line_description VARCHAR(255) NULL,
  profile_key CHAR(64) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uk_pur_purchase_receipt_line_po_line (purchase_receipt_id, purchase_order_line_id),
  KEY idx_pur_purchase_receipt_line_receipt (purchase_receipt_id),
  KEY idx_pur_purchase_receipt_line_po_line (purchase_order_line_id),
  KEY idx_pur_purchase_receipt_line_item (item_id),
  KEY idx_pur_purchase_receipt_line_material (material_id),
  KEY idx_pur_purchase_receipt_line_profile (profile_key),

  CONSTRAINT fk_pur_purchase_receipt_line_receipt FOREIGN KEY (purchase_receipt_id) REFERENCES pur_purchase_receipt(id),
  CONSTRAINT fk_pur_purchase_receipt_line_po_line FOREIGN KEY (purchase_order_line_id) REFERENCES pur_purchase_order_line(id),
  CONSTRAINT fk_pur_purchase_receipt_line_item FOREIGN KEY (item_id) REFERENCES mst_item(id),
  CONSTRAINT fk_pur_purchase_receipt_line_material FOREIGN KEY (material_id) REFERENCES mst_material(id),
  CONSTRAINT fk_pur_purchase_receipt_line_buy_uom FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
  CONSTRAINT fk_pur_purchase_receipt_line_content_uom FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- D. Tidak ada seed metode statis
-- Kanal pembayaran dan rekening di-seed pada Tahap 6I.
-- ------------------------------------------------------------

COMMIT;

SELECT 'pur_purchase_payment_plan', COUNT(*) AS total_rows FROM pur_purchase_payment_plan
UNION ALL
SELECT 'pur_purchase_receipt', COUNT(*) FROM pur_purchase_receipt
UNION ALL
SELECT 'pur_purchase_receipt_line', COUNT(*) FROM pur_purchase_receipt_line;
