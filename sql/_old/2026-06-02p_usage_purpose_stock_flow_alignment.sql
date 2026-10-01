SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-02p_usage_purpose_stock_flow_alignment.sql
-- Tujuan :
-- 1) Melengkapi kolom usage purpose pada jalur PO/receipt/fulfillment
-- 2) Menjaga pemakaian operasional tidak tercampur ke stok bahan baku
-- 3) Aman dijalankan ulang di database yang sudah berjalan
-- ============================================================

START TRANSACTION;

SET @has_po_line_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_order_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_add_po_line_usage := IF(
  @has_po_line_usage = 0,
  "ALTER TABLE `pur_purchase_order_line` ADD COLUMN `usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'",
  'SELECT 1'
);
PREPARE stmt_po_line_usage FROM @sql_add_po_line_usage; EXECUTE stmt_po_line_usage; DEALLOCATE PREPARE stmt_po_line_usage;

SET @has_receipt_line_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_purchase_receipt_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_add_receipt_line_usage := IF(
  @has_receipt_line_usage = 0,
  "ALTER TABLE `pur_purchase_receipt_line` ADD COLUMN `usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'",
  'SELECT 1'
);
PREPARE stmt_receipt_line_usage FROM @sql_add_receipt_line_usage; EXECUTE stmt_receipt_line_usage; DEALLOCATE PREPARE stmt_receipt_line_usage;

SET @has_fulfillment_line_usage := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pur_store_request_fulfillment_line'
    AND COLUMN_NAME = 'usage_purpose'
);
SET @sql_add_fulfillment_line_usage := IF(
  @has_fulfillment_line_usage = 0,
  "ALTER TABLE `pur_store_request_fulfillment_line` ADD COLUMN `usage_purpose` VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'",
  'SELECT 1'
);
PREPARE stmt_fulfillment_line_usage FROM @sql_add_fulfillment_line_usage; EXECUTE stmt_fulfillment_line_usage; DEALLOCATE PREPARE stmt_fulfillment_line_usage;

UPDATE pur_purchase_order_line pol
LEFT JOIN mst_item i ON i.id = pol.item_id
SET pol.usage_purpose = COALESCE(NULLIF(pol.usage_purpose, ''), i.default_usage_purpose, 'BAHAN_BAKU')
WHERE pol.usage_purpose IS NULL OR pol.usage_purpose = '';

UPDATE pur_purchase_receipt_line rl
LEFT JOIN pur_purchase_order_line pol ON pol.id = rl.purchase_order_line_id
LEFT JOIN mst_item i ON i.id = rl.item_id
SET rl.usage_purpose = COALESCE(NULLIF(rl.usage_purpose, ''), pol.usage_purpose, i.default_usage_purpose, 'BAHAN_BAKU')
WHERE rl.usage_purpose IS NULL OR rl.usage_purpose = '';

UPDATE pur_store_request_fulfillment_line fl
LEFT JOIN pur_store_request_line srl ON srl.id = fl.store_request_line_id
LEFT JOIN mst_item i ON i.id = fl.item_id
SET fl.usage_purpose = COALESCE(NULLIF(fl.usage_purpose, ''), srl.usage_purpose, i.default_usage_purpose, 'BAHAN_BAKU')
WHERE fl.usage_purpose IS NULL OR fl.usage_purpose = '';

COMMIT;

SELECT 'pur_purchase_order_line.usage_purpose' AS schema_key,
       COUNT(*) AS total_rows
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_purchase_order_line'
  AND COLUMN_NAME = 'usage_purpose'
UNION ALL
SELECT 'pur_purchase_receipt_line.usage_purpose', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_purchase_receipt_line'
  AND COLUMN_NAME = 'usage_purpose'
UNION ALL
SELECT 'pur_store_request_fulfillment_line.usage_purpose', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_store_request_fulfillment_line'
  AND COLUMN_NAME = 'usage_purpose';
