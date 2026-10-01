SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25f_pos_void_refund_department_print_rules.sql
-- Tujuan :
-- 1) Menambahkan tiket pemberitahuan VOID untuk BAR dan KITCHEN
-- 2) Menambahkan tiket pemberitahuan REFUND untuk BAR dan KITCHEN
-- 3) Memastikan tiket divisi hanya memuat item divisinya sendiri
--
-- Catatan:
-- - Slip kasir yang sudah ada TIDAK dihapus atau diubah.
-- - Tiket divisi tidak menampilkan harga atau total uang.
-- - Mode default AUTO karena informasi batal perlu segera diterima
--   sebelum BAR/KITCHEN mulai memproses item tersebut.
-- - Koneksi dipilih dari pos_print_connection aktif yang memang
--   ditautkan ke divisi operasional BAR atau KITCHEN.
-- ============================================================

START TRANSACTION;

-- Layout terpisah agar admin dapat mengubah tampilan pemberitahuan
-- produksi tanpa mengganggu slip finansial kasir.
INSERT INTO pos_print_layout (
  layout_code, layout_name, document_type, layout_payload,
  description, is_default, is_active
)
VALUES
(
  'LAYOUT-VOID-BAR-NOTICE',
  'Pemberitahuan Void BAR',
  'VOID_SLIP',
  '{"title":"PEMBATALAN BAR","subtitle":"JANGAN DIPROSES","show_logo":false,"show_header":true,"show_invoice_no":true,"show_payment_no":false,"show_customer":true,"show_table_no":true,"show_order_time":true,"show_payment_time":false,"show_cashier_order":true,"show_cashier_payment":false,"show_product_name":true,"show_qty":true,"show_extra":true,"show_notes":true,"show_order_notes":true,"show_subtotal":false,"show_payment_breakdown":false,"show_discount":false,"show_compliment":false,"show_deposit_applied":false,"show_grand_total":false,"show_paid_amount":false,"show_balance_due":false,"show_void_reason":true,"show_refund_reason":false,"show_footer":false,"show_price":false,"show_footer_barcode":false,"show_wifi_info":false,"show_customer_point_info":false,"show_customer_stamp_info":false,"show_customer_voucher":false,"show_customer_review_qr":false,"header_align":"CENTER","footer_align":"CENTER","division_filter":"BAR"}',
  'Tiket tanpa harga untuk memberitahu BAR agar tidak memproses item yang di-void.',
  0,
  1
),
(
  'LAYOUT-VOID-KITCHEN-NOTICE',
  'Pemberitahuan Void KITCHEN',
  'VOID_SLIP',
  '{"title":"PEMBATALAN KITCHEN","subtitle":"JANGAN DIPROSES","show_logo":false,"show_header":true,"show_invoice_no":true,"show_payment_no":false,"show_customer":true,"show_table_no":true,"show_order_time":true,"show_payment_time":false,"show_cashier_order":true,"show_cashier_payment":false,"show_product_name":true,"show_qty":true,"show_extra":true,"show_notes":true,"show_order_notes":true,"show_subtotal":false,"show_payment_breakdown":false,"show_discount":false,"show_compliment":false,"show_deposit_applied":false,"show_grand_total":false,"show_paid_amount":false,"show_balance_due":false,"show_void_reason":true,"show_refund_reason":false,"show_footer":false,"show_price":false,"show_footer_barcode":false,"show_wifi_info":false,"show_customer_point_info":false,"show_customer_stamp_info":false,"show_customer_voucher":false,"show_customer_review_qr":false,"header_align":"CENTER","footer_align":"CENTER","division_filter":"KITCHEN"}',
  'Tiket tanpa harga untuk memberitahu KITCHEN agar tidak memproses item yang di-void.',
  0,
  1
),
(
  'LAYOUT-REFUND-BAR-NOTICE',
  'Pemberitahuan Refund BAR',
  'REFUND_SLIP',
  '{"title":"PEMBERITAHUAN REFUND BAR","subtitle":"JANGAN DIPROSES BILA BELUM DIBUAT","show_logo":false,"show_header":true,"show_invoice_no":true,"show_payment_no":false,"show_customer":true,"show_table_no":true,"show_order_time":true,"show_payment_time":false,"show_cashier_order":true,"show_cashier_payment":false,"show_product_name":true,"show_qty":true,"show_extra":true,"show_notes":true,"show_order_notes":true,"show_subtotal":false,"show_payment_breakdown":false,"show_discount":false,"show_compliment":false,"show_deposit_applied":false,"show_grand_total":false,"show_paid_amount":false,"show_balance_due":false,"show_void_reason":false,"show_refund_reason":true,"show_footer":false,"show_price":false,"show_footer_barcode":false,"show_wifi_info":false,"show_customer_point_info":false,"show_customer_stamp_info":false,"show_customer_voucher":false,"show_customer_review_qr":false,"header_align":"CENTER","footer_align":"CENTER","division_filter":"BAR"}',
  'Tiket tanpa harga untuk memberitahu BAR tentang item yang direfund.',
  0,
  1
),
(
  'LAYOUT-REFUND-KITCHEN-NOTICE',
  'Pemberitahuan Refund KITCHEN',
  'REFUND_SLIP',
  '{"title":"PEMBERITAHUAN REFUND KITCHEN","subtitle":"JANGAN DIPROSES BILA BELUM DIBUAT","show_logo":false,"show_header":true,"show_invoice_no":true,"show_payment_no":false,"show_customer":true,"show_table_no":true,"show_order_time":true,"show_payment_time":false,"show_cashier_order":true,"show_cashier_payment":false,"show_product_name":true,"show_qty":true,"show_extra":true,"show_notes":true,"show_order_notes":true,"show_subtotal":false,"show_payment_breakdown":false,"show_discount":false,"show_compliment":false,"show_deposit_applied":false,"show_grand_total":false,"show_paid_amount":false,"show_balance_due":false,"show_void_reason":false,"show_refund_reason":true,"show_footer":false,"show_price":false,"show_footer_barcode":false,"show_wifi_info":false,"show_customer_point_info":false,"show_customer_stamp_info":false,"show_customer_voucher":false,"show_customer_review_qr":false,"header_align":"CENTER","footer_align":"CENTER","division_filter":"KITCHEN"}',
  'Tiket tanpa harga untuk memberitahu KITCHEN tentang item yang direfund.',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  layout_name = VALUES(layout_name),
  document_type = VALUES(document_type),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- VOID: satu tiket untuk setiap koneksi BAR/KITCHEN aktif. Cakupan
-- MATCHED_DIVISION memastikan BAR tidak menerima item KITCHEN, begitu pula sebaliknya.
INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type,
  outlet_id, terminal_id, operational_division_id, product_division_id,
  content_scope, connection_id, layout_id, copy_count, priority,
  notes, print_mode, is_active
)
SELECT
  CONCAT('ROUTE-VOID-NOTICE-', UPPER(d.code), '-', c.id),
  CONCAT('Pemberitahuan void ke ', UPPER(d.name)),
  'VOID_SLIP',
  'VOID_SLIP',
  c.outlet_id,
  NULL,
  d.id,
  NULL,
  'MATCHED_DIVISION',
  c.id,
  l.id,
  0,
  200,
  CONCAT('Kirim item ', UPPER(d.name), ' yang di-void agar tidak diproses.'),
  'AUTO',
  1
FROM pos_print_connection c
JOIN mst_operational_division d
  ON d.id = c.operational_division_id
JOIN pos_print_layout l
  ON l.layout_code = CASE UPPER(d.code)
    WHEN 'BAR' THEN 'LAYOUT-VOID-BAR-NOTICE'
    WHEN 'KITCHEN' THEN 'LAYOUT-VOID-KITCHEN-NOTICE'
  END
WHERE c.is_active = 1
  AND UPPER(d.code) IN ('BAR', 'KITCHEN')
ON DUPLICATE KEY UPDATE
  route_name = VALUES(route_name),
  event_code = VALUES(event_code),
  document_type = VALUES(document_type),
  outlet_id = VALUES(outlet_id),
  terminal_id = VALUES(terminal_id),
  operational_division_id = VALUES(operational_division_id),
  product_division_id = VALUES(product_division_id),
  content_scope = VALUES(content_scope),
  connection_id = VALUES(connection_id),
  layout_id = VALUES(layout_id),
  copy_count = VALUES(copy_count),
  priority = VALUES(priority),
  notes = VALUES(notes),
  print_mode = VALUES(print_mode),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- REFUND: tetap dikirim ke divisi sebagai pemberitahuan. Ini tidak membatalkan
-- produk yang sudah terlanjur dibuat, tetapi mencegah produksi dimulai bila
-- refund terjadi sebelum proses fisik selesai.
INSERT INTO pos_print_route (
  route_code, route_name, event_code, document_type,
  outlet_id, terminal_id, operational_division_id, product_division_id,
  content_scope, connection_id, layout_id, copy_count, priority,
  notes, print_mode, is_active
)
SELECT
  CONCAT('ROUTE-REFUND-NOTICE-', UPPER(d.code), '-', c.id),
  CONCAT('Pemberitahuan refund ke ', UPPER(d.name)),
  'REFUND_SLIP',
  'REFUND_SLIP',
  c.outlet_id,
  NULL,
  d.id,
  NULL,
  'MATCHED_DIVISION',
  c.id,
  l.id,
  0,
  200,
  CONCAT('Kirim item ', UPPER(d.name), ' yang direfund agar tidak diproses bila belum dibuat.'),
  'AUTO',
  1
FROM pos_print_connection c
JOIN mst_operational_division d
  ON d.id = c.operational_division_id
JOIN pos_print_layout l
  ON l.layout_code = CASE UPPER(d.code)
    WHEN 'BAR' THEN 'LAYOUT-REFUND-BAR-NOTICE'
    WHEN 'KITCHEN' THEN 'LAYOUT-REFUND-KITCHEN-NOTICE'
  END
WHERE c.is_active = 1
  AND UPPER(d.code) IN ('BAR', 'KITCHEN')
ON DUPLICATE KEY UPDATE
  route_name = VALUES(route_name),
  event_code = VALUES(event_code),
  document_type = VALUES(document_type),
  outlet_id = VALUES(outlet_id),
  terminal_id = VALUES(terminal_id),
  operational_division_id = VALUES(operational_division_id),
  product_division_id = VALUES(product_division_id),
  content_scope = VALUES(content_scope),
  connection_id = VALUES(connection_id),
  layout_id = VALUES(layout_id),
  copy_count = VALUES(copy_count),
  priority = VALUES(priority),
  notes = VALUES(notes),
  print_mode = VALUES(print_mode),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT
  r.route_code,
  r.route_name,
  r.event_code,
  r.content_scope,
  d.name AS division_name,
  c.connection_name,
  l.layout_name,
  r.print_mode,
  r.is_active
FROM pos_print_route r
JOIN pos_print_connection c ON c.id = r.connection_id
JOIN pos_print_layout l ON l.id = r.layout_id
LEFT JOIN mst_operational_division d ON d.id = r.operational_division_id
WHERE r.route_code LIKE 'ROUTE-VOID-NOTICE-%'
   OR r.route_code LIKE 'ROUTE-REFUND-NOTICE-%'
ORDER BY r.event_code, d.name, r.id;
