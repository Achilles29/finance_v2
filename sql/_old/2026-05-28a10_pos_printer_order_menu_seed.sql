SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28a10_pos_printer_order_menu_seed.sql
-- Tujuan :
-- 1) Menambah page + menu Printer POS dan Draft Order POS
-- 2) Menyiapkan baseline template master dan event printer POS
-- 3) Memberi permission default ke role operasional POS
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active) VALUES
  ('pos.printer.index', 'Printer POS', 'POS', 'Master template, profile, dan device printer POS desktop', 1),
  ('pos.order.draft.index', 'Draft Order POS', 'POS', 'Workbench draft order POS dan stock commit snapshot awal', 1)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'pos.printer', 'Printer POS', 'ri-printer-line', '/pos/printers', p.id, 4, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.printer.index'
WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT 'pos.order.draft', 'Draft Order POS', 'ri-shopping-bag-3-line', '/pos/orders/draft', p.id, 5, 1, 'MAIN', parent.id
FROM sys_menu parent
JOIN sys_page p ON p.page_code = 'pos.order.draft.index'
WHERE parent.menu_code = 'grp.pos'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT r.id, p.id, 1, 1, 1, 0, 1, NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code IN ('pos.printer.index', 'pos.order.draft.index')
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','KASIR','BARISTA')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO pos_printer_template_master (master_code, master_name, document_type, description, is_active)
VALUES
  ('TPLM-RECEIPT', 'Receipt POS', 'RECEIPT', 'Template dasar receipt pembayaran POS', 1),
  ('TPLM-KOT', 'Kitchen Order Ticket', 'KOT', 'Template dasar cetak KOT / bar ticket', 1),
  ('TPLM-BILL', 'Bill Sementara', 'BILL', 'Template dasar bill sebelum payment final', 1),
  ('TPLM-SHIFT', 'Shift Close', 'SHIFT_CLOSE', 'Template dasar ringkasan tutup shift kasir', 1),
  ('TPLM-REFUND', 'Refund Slip', 'REFUND', 'Template dasar refund POS', 1),
  ('TPLM-VOID', 'Void Slip', 'VOID', 'Template dasar void POS', 1),
  ('TPLM-STICKER', 'Label / Sticker', 'STICKER', 'Template dasar sticker/label order', 1)
ON DUPLICATE KEY UPDATE
  master_name = VALUES(master_name),
  document_type = VALUES(document_type),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO pos_printer_event_setting (event_code, event_name, document_type, auto_print, allow_manual_reprint, max_copy, is_active)
VALUES
  ('ORDER_PAID_RECEIPT', 'Receipt setelah order dibayar', 'RECEIPT', 1, 1, 2, 1),
  ('ORDER_CONFIRM_KOT', 'KOT saat order dikonfirmasi', 'KOT', 1, 1, 3, 1),
  ('ORDER_PRE_BILL', 'Bill sementara sebelum bayar', 'BILL', 0, 1, 2, 1),
  ('SHIFT_CLOSE_SUMMARY', 'Ringkasan tutup shift', 'SHIFT_CLOSE', 1, 1, 2, 1),
  ('REFUND_SLIP', 'Slip refund POS', 'REFUND', 1, 1, 2, 1),
  ('VOID_SLIP', 'Slip void POS', 'VOID', 1, 1, 2, 1)
ON DUPLICATE KEY UPDATE
  event_name = VALUES(event_name),
  document_type = VALUES(document_type),
  auto_print = VALUES(auto_print),
  allow_manual_reprint = VALUES(allow_manual_reprint),
  max_copy = VALUES(max_copy),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'sys_page.pos.printer.index' AS seed_key, COUNT(*) AS total_rows FROM sys_page WHERE page_code = 'pos.printer.index'
UNION ALL
SELECT 'sys_page.pos.order.draft.index', COUNT(*) FROM sys_page WHERE page_code = 'pos.order.draft.index'
UNION ALL
SELECT 'pos_printer_template_master', COUNT(*) FROM pos_printer_template_master
UNION ALL
SELECT 'pos_printer_event_setting', COUNT(*) FROM pos_printer_event_setting;
