SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07n_cleanup_item_linked_legacy_line_kind.sql
-- Tujuan :
-- 1) Mengubah line_kind MATERIAL -> ITEM untuk row yang sudah item-linked
-- 2) Merapikan jejak legacy di purchase catalog + line transaksi aktif
-- 3) Mengurangi sumber mismatch baru dari layer transaksi, bukan stok
--
-- Prinsip:
-- - HANYA menyentuh row dengan item_id > 0
-- - Tidak mengubah qty, harga, material_id, profile_key, atau movement log
-- - Fokus pada label legacy yang sudah tidak relevan di flow item-centric
-- ============================================================

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_item_linked_legacy_line_kind_before;
CREATE TEMPORARY TABLE tmp_item_linked_legacy_line_kind_before (
  table_name VARCHAR(100) NOT NULL,
  total BIGINT NOT NULL DEFAULT 0
);

INSERT INTO tmp_item_linked_legacy_line_kind_before (table_name, total)
SELECT 'mst_purchase_catalog', COUNT(*)
FROM mst_purchase_catalog
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL'
UNION ALL
SELECT 'pur_division_request_line', COUNT(*)
FROM pur_division_request_line
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL'
UNION ALL
SELECT 'pur_store_request_line', COUNT(*)
FROM pur_store_request_line
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL'
UNION ALL
SELECT 'pur_purchase_order_line', COUNT(*)
FROM pur_purchase_order_line
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL'
UNION ALL
SELECT 'pur_purchase_receipt_line', COUNT(*)
FROM pur_purchase_receipt_line
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

UPDATE mst_purchase_catalog
SET line_kind = 'ITEM'
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

UPDATE pur_division_request_line
SET line_kind = 'ITEM'
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

UPDATE pur_store_request_line
SET line_kind = 'ITEM'
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

UPDATE pur_purchase_order_line
SET line_kind = 'ITEM'
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

UPDATE pur_purchase_receipt_line
SET line_kind = 'ITEM'
WHERE COALESCE(item_id, 0) > 0
  AND UPPER(COALESCE(line_kind, 'ITEM')) = 'MATERIAL';

COMMIT;

SELECT *
FROM tmp_item_linked_legacy_line_kind_before
ORDER BY table_name;
