SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-28f_pos_printer_payload_backfill_local_branding.sql
-- Tujuan :
-- 1) Membersihkan sisa payload printer hasil import yang masih menunjuk domain core
-- 2) Menormalkan logo URL printer ke aset lokal finance
-- 3) Menyiapkan master payload global POS-GLOBAL bila belum ada
-- Catatan:
-- - Aman dijalankan ulang.
-- - Fokus ke payload branding printer, tidak mengubah device / routing printer.
-- ============================================================

START TRANSACTION;

SET @local_logo_url := 'http://localhost/finance/assets/img/logo.png';
SET @global_title := 'NAMUA COFFEE N EATERY';
SET @global_subtitle := 'Jl. Magnolia, Desa Kabongan Kidul, Rembang';
SET @global_payload := JSON_OBJECT(
  'title', @global_title,
  'subtitle', @global_subtitle,
  'logo_url', @local_logo_url,
  'wifi_name', '',
  'wifi_password', '',
  'show_customer_point_info', FALSE,
  'show_customer_stamp_info', FALSE,
  'show_customer_voucher', FALSE,
  'customer_voucher_limit', 1,
  'customer_voucher_message_template', 'Selamat, Anda mendapat voucher {voucher_benefit}. Gunakan sebelum {voucher_expiry}.',
  'customer_voucher_align', 'CENTER',
  'header_lines', JSON_ARRAY('ORDER CEPAT, SAJI HANGAT.'),
  'footer_lines', JSON_ARRAY('TERIMA KASIH SUDAH BERKUNJUNG')
);

INSERT INTO pos_printer_template_master (
  master_code, master_name, master_payload, document_type, description, is_default, is_active
)
SELECT
  'POS-GLOBAL',
  'Pengaturan Umum POS',
  @global_payload,
  'OTHER',
  'Pengaturan global printer POS: branding, Wi-Fi, dan info loyalty.',
  0,
  1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM pos_printer_template_master WHERE master_code = 'POS-GLOBAL'
);

UPDATE pos_printer_template_master
SET master_payload = REPLACE(master_payload, 'https://core.namuacoffee.com/assets/img/logo.png', @local_logo_url)
WHERE COALESCE(master_payload, '') LIKE '%https://core.namuacoffee.com/assets/img/logo.png%';

UPDATE pos_printer_template_master
SET master_payload = REPLACE(master_payload, 'http://core.namuacoffee.com/assets/img/logo.png', @local_logo_url)
WHERE COALESCE(master_payload, '') LIKE '%http://core.namuacoffee.com/assets/img/logo.png%';

UPDATE pos_printer_template
SET template_payload = REPLACE(template_payload, 'https://core.namuacoffee.com/assets/img/logo.png', @local_logo_url)
WHERE COALESCE(template_payload, '') LIKE '%https://core.namuacoffee.com/assets/img/logo.png%';

UPDATE pos_printer_template
SET template_payload = REPLACE(template_payload, 'http://core.namuacoffee.com/assets/img/logo.png', @local_logo_url)
WHERE COALESCE(template_payload, '') LIKE '%http://core.namuacoffee.com/assets/img/logo.png%';

COMMIT;

SELECT 'template_payload.core_logo_rows' AS metric, COUNT(*) AS total
FROM pos_printer_template
WHERE COALESCE(template_payload, '') LIKE '%core.namuacoffee.com/assets/img/logo%'
UNION ALL
SELECT 'master_payload.core_logo_rows', COUNT(*)
FROM pos_printer_template_master
WHERE COALESCE(master_payload, '') LIKE '%core.namuacoffee.com/assets/img/logo%'
UNION ALL
SELECT 'pos_global.exists', COUNT(*)
FROM pos_printer_template_master
WHERE master_code = 'POS-GLOBAL';
