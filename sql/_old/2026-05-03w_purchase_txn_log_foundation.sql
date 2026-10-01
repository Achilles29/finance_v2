-- ============================================================
-- Purchase Transaction Log (Dedicated timeline for PO lifecycle)
-- ============================================================

CREATE TABLE IF NOT EXISTS pur_purchase_txn_log (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  purchase_receipt_id BIGINT UNSIGNED NULL,
  payment_plan_id BIGINT UNSIGNED NULL,
  action_code VARCHAR(40) NOT NULL,
  status_before VARCHAR(30) NULL,
  status_after VARCHAR(30) NULL,
  transaction_no VARCHAR(80) NULL,
  ref_table VARCHAR(80) NULL,
  ref_id BIGINT UNSIGNED NULL,
  amount DECIMAL(18,2) NULL,
  payload_json JSON NULL,
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_purchase_txn_po (purchase_order_id),
  KEY idx_purchase_txn_receipt (purchase_receipt_id),
  KEY idx_purchase_txn_payment (payment_plan_id),
  KEY idx_purchase_txn_action (action_code),
  KEY idx_purchase_txn_created (created_at),
  KEY idx_purchase_txn_ref (ref_table, ref_id),
  CONSTRAINT fk_purchase_txn_po FOREIGN KEY (purchase_order_id) REFERENCES pur_purchase_order(id),
  CONSTRAINT fk_purchase_txn_receipt FOREIGN KEY (purchase_receipt_id) REFERENCES pur_purchase_receipt(id),
  CONSTRAINT fk_purchase_txn_payment FOREIGN KEY (payment_plan_id) REFERENCES pur_purchase_payment_plan(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
