SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25d_seed_customer_review_print_settings.sql
-- Tujuan :
-- 1) Menyimpan flag QR ulasan secara eksplisit pada setting umum
--    dan layout struk lama.
-- 2) Menghindari perbedaan antara toggle layar, preview, dan cetak nyata
--    akibat nilai default yang sebelumnya hanya hidup di kode.
--
-- Prasyarat:
-- - Jalankan 2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql
-- - Jalankan 2026-08-25c_pos_customer_review_station_and_printable_qr.sql
-- ============================================================

START TRANSACTION;

-- Pengaman untuk instalasi yang belum memiliki pengaturan umum GLOBAL.
INSERT INTO pos_print_general_setting (
  setting_code, setting_name, outlet_id, general_payload, notes, is_active
)
SELECT
  'GLOBAL',
  'Tampilan Umum Semua Outlet',
  NULL,
  JSON_OBJECT(
    'customer_review_qr_enabled', 1,
    'customer_review_message', 'Bagikan ulasan Anda dengan scan QR berikut.'
  ),
  'Pengaturan awal QR ulasan pelanggan.',
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM pos_print_general_setting
  WHERE setting_code = 'GLOBAL'
    AND outlet_id IS NULL
);

-- Hanya isi data yang sebelumnya belum ada. Nilai OFF yang sengaja dipilih
-- operator tidak pernah ditimpa oleh migration ini.
UPDATE pos_print_general_setting
SET general_payload = JSON_SET(
  CASE
    WHEN JSON_VALID(COALESCE(general_payload, '')) THEN general_payload
    ELSE '{}'
  END,
  '$.customer_review_qr_enabled', 1
)
WHERE setting_code = 'GLOBAL'
  AND outlet_id IS NULL
  AND JSON_EXTRACT(
    CASE
      WHEN JSON_VALID(COALESCE(general_payload, '')) THEN general_payload
      ELSE '{}'
    END,
    '$.customer_review_qr_enabled'
  ) IS NULL;

UPDATE pos_print_general_setting
SET general_payload = JSON_SET(
  CASE
    WHEN JSON_VALID(COALESCE(general_payload, '')) THEN general_payload
    ELSE '{}'
  END,
  '$.customer_review_message', 'Bagikan ulasan Anda dengan scan QR berikut.'
)
WHERE setting_code = 'GLOBAL'
  AND outlet_id IS NULL
  AND JSON_EXTRACT(
    CASE
      WHEN JSON_VALID(COALESCE(general_payload, '')) THEN general_payload
      ELSE '{}'
    END,
    '$.customer_review_message'
  ) IS NULL;

-- Layout struk lama sebelumnya secara kode sudah dianggap mengizinkan QR.
-- Sekarang flag itu disimpan ke database agar menjadi satu sumber kebenaran.
UPDATE pos_print_layout
SET layout_payload = JSON_SET(
  CASE
    WHEN JSON_VALID(COALESCE(layout_payload, '')) THEN layout_payload
    ELSE '{}'
  END,
  '$.show_customer_review_qr', 1
)
WHERE document_type = 'RECEIPT'
  AND JSON_EXTRACT(
    CASE
      WHEN JSON_VALID(COALESCE(layout_payload, '')) THEN layout_payload
      ELSE '{}'
    END,
    '$.show_customer_review_qr'
  ) IS NULL;

COMMIT;

SELECT
  setting_code,
  JSON_UNQUOTE(JSON_EXTRACT(general_payload, '$.customer_review_qr_enabled')) AS qr_struk_aktif,
  JSON_UNQUOTE(JSON_EXTRACT(general_payload, '$.customer_review_message')) AS pesan_qr
FROM pos_print_general_setting
WHERE setting_code = 'GLOBAL'
  AND outlet_id IS NULL;

SELECT
  id,
  layout_name,
  document_type,
  JSON_UNQUOTE(JSON_EXTRACT(layout_payload, '$.show_customer_review_qr')) AS layout_izinkan_qr
FROM pos_print_layout
WHERE document_type = 'RECEIPT'
ORDER BY id;
