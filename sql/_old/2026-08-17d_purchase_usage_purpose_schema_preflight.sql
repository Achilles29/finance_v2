SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-17d_purchase_usage_purpose_schema_preflight.sql
-- Tujuan :
-- 1) Memasang kolom tujuan pemakaian purchase melalui migration resmi
-- 2) Menghapus kebutuhan ALTER TABLE dari request PO, receipt, dan SR
-- 3) Menjaga default tujuan pemakaian tetap Persediaan Produksi
--
-- Catatan:
-- - Aman dijalankan ulang.
-- - Tidak mengubah transaksi lama; hanya memastikan struktur tersedia.
-- ============================================================

START TRANSACTION;

ALTER TABLE mst_item
  ADD COLUMN IF NOT EXISTS default_usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE pur_purchase_order_line
  ADD COLUMN IF NOT EXISTS usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE pur_purchase_receipt_line
  ADD COLUMN IF NOT EXISTS usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE pur_store_request_fulfillment_line
  ADD COLUMN IF NOT EXISTS usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE pur_store_request_line
  ADD COLUMN IF NOT EXISTS usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

ALTER TABLE pur_division_request_line
  ADD COLUMN IF NOT EXISTS usage_purpose VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU';

COMMIT;

SELECT 'mst_item.default_usage_purpose' AS schema_key, COUNT(*) AS total
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'mst_item'
  AND COLUMN_NAME = 'default_usage_purpose'
UNION ALL
SELECT 'pur_purchase_order_line.usage_purpose', COUNT(*)
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
  AND COLUMN_NAME = 'usage_purpose'
UNION ALL
SELECT 'pur_store_request_line.usage_purpose', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_store_request_line'
  AND COLUMN_NAME = 'usage_purpose'
UNION ALL
SELECT 'pur_division_request_line.usage_purpose', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pur_division_request_line'
  AND COLUMN_NAME = 'usage_purpose';
