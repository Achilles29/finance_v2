SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-24d_pos_print_layout_customer_visibility.sql
-- Tujuan :
-- 1) Memindahkan keputusan tampil poin, stamp, dan voucher ke layout
-- 2) Menjaga data master/format voucher tetap berada di Tampilan Umum
-- 3) Mencegah KOT atau dokumen produksi membawa informasi pelanggan
--
-- Catatan:
-- - Receipt/deposit hanya dilengkapi bila switch belum ada pada layout lama.
-- - KOT dan dokumen operasional dinormalisasi untuk tidak membawa data pelanggan.
-- - Baseline lama: informasi pelanggan hanya dibuka pada receipt/deposit
--   bila pengaturan umum lama memang menyalakannya.
-- ============================================================

START TRANSACTION;

SET @general_payload := COALESCE((
  SELECT general_payload
  FROM pos_print_general_setting
  WHERE setting_code = 'GLOBAL'
    AND outlet_id IS NULL
    AND is_active = 1
  ORDER BY id DESC
  LIMIT 1
), '{}');

SET @legacy_show_points := LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(@general_payload, '$.show_customer_point_info')), '0'));
SET @legacy_show_stamps := LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(@general_payload, '$.show_customer_stamp_info')), '0'));
SET @legacy_show_voucher := LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(@general_payload, '$.show_customer_voucher')), '0'));

UPDATE pos_print_layout l
SET l.layout_payload = JSON_SET(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  '$.show_customer_point_info',
  CASE WHEN l.document_type IN ('RECEIPT', 'DEPOSIT_RECEIPT')
            AND @legacy_show_points IN ('1', 'true') THEN TRUE ELSE FALSE END
)
WHERE JSON_CONTAINS_PATH(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  'one', '$.show_customer_point_info'
) = 0;

UPDATE pos_print_layout l
SET l.layout_payload = JSON_SET(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  '$.show_customer_stamp_info',
  CASE WHEN l.document_type IN ('RECEIPT', 'DEPOSIT_RECEIPT')
            AND @legacy_show_stamps IN ('1', 'true') THEN TRUE ELSE FALSE END
)
WHERE JSON_CONTAINS_PATH(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  'one', '$.show_customer_stamp_info'
) = 0;

UPDATE pos_print_layout l
SET l.layout_payload = JSON_SET(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  '$.show_customer_voucher',
  CASE WHEN l.document_type IN ('RECEIPT', 'DEPOSIT_RECEIPT')
            AND @legacy_show_voucher IN ('1', 'true') THEN TRUE ELSE FALSE END
)
WHERE JSON_CONTAINS_PATH(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  'one', '$.show_customer_voucher'
) = 0;

-- KOT, slip pembatalan/refund, dan ringkasan shift adalah dokumen operasi.
-- Legacy payload pernah membawa switch pelanggan secara global, sehingga
-- semua jenis dokumen non-customer dinormalisasi menjadi tidak menampilkan
-- poin, stamp, maupun voucher.
UPDATE pos_print_layout l
SET l.layout_payload = JSON_SET(
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END,
  '$.show_customer_point_info', FALSE,
  '$.show_customer_stamp_info', FALSE,
  '$.show_customer_voucher', FALSE
)
WHERE l.document_type NOT IN ('RECEIPT', 'DEPOSIT_RECEIPT');

-- Layout yang dibuat dari template lama kadang hanya menyimpan sebagian
-- switch. Materialisasikan seluruh baseline visibilitas yang selama ini
-- menjadi default renderer, lalu biarkan nilai yang sudah disimpan operator
-- tetap menang. Dengan begitu database menjadi sumber yang lengkap untuk
-- menentukan isi dokumen, bukan kode PHP yang diam-diam menebak nilai kosong.
UPDATE pos_print_layout l
SET l.layout_payload = JSON_MERGE_PATCH(
  JSON_OBJECT(
    'show_logo', TRUE,
    'show_header', TRUE,
    'show_invoice_no', TRUE,
    'show_payment_no', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_customer', TRUE,
    'show_table_no', TRUE,
    'show_order_time', TRUE,
    'show_payment_time', FALSE,
    'show_cashier_order', TRUE,
    'show_cashier_payment', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_product_name', TRUE,
    'show_qty', TRUE,
    'show_extra', TRUE,
    'show_notes', TRUE,
    'show_order_notes', TRUE,
    'show_subtotal', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_payment_breakdown', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_discount', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_compliment', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_deposit_applied', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_grand_total', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_paid_amount', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_balance_due', CASE WHEN l.document_type = 'RECEIPT' THEN TRUE ELSE FALSE END,
    'show_void_reason', CASE WHEN l.document_type = 'VOID_SLIP' THEN TRUE ELSE FALSE END,
    'show_refund_reason', CASE WHEN l.document_type = 'REFUND_SLIP' THEN TRUE ELSE FALSE END,
    'show_footer', TRUE,
    'show_price', CASE WHEN l.document_type <> 'KITCHEN_TICKET' THEN TRUE ELSE FALSE END,
    'show_footer_barcode', TRUE,
    'show_wifi_info', FALSE,
    'show_customer_point_info', FALSE,
    'show_customer_stamp_info', FALSE,
    'show_customer_voucher', FALSE,
    'header_align', 'CENTER',
    'footer_align', 'CENTER',
    'footer_barcode_source', 'ORDER_NO',
    'footer_barcode_custom', ''
  ),
  CASE WHEN JSON_VALID(COALESCE(NULLIF(TRIM(l.layout_payload), ''), '{}'))
    THEN l.layout_payload ELSE JSON_OBJECT() END
);

COMMIT;

SELECT
  l.layout_code,
  l.layout_name,
  l.document_type,
  JSON_UNQUOTE(JSON_EXTRACT(l.layout_payload, '$.show_customer_point_info')) AS show_customer_point_info,
  JSON_UNQUOTE(JSON_EXTRACT(l.layout_payload, '$.show_customer_stamp_info')) AS show_customer_stamp_info,
  JSON_UNQUOTE(JSON_EXTRACT(l.layout_payload, '$.show_customer_voucher')) AS show_customer_voucher
FROM pos_print_layout l
ORDER BY l.document_type, l.id;
