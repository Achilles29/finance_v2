SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-25c_pos_customer_review_station_and_printable_qr.sql
-- Tujuan :
-- 1) Menambah QR ulasan umum yang dapat dicetak dan ditempel di area outlet
-- 2) Memisahkan ulasan dari struk dengan ulasan dari QR area pengunjung
-- 3) Mengizinkan pengunjung mendaftar/memberi nomor member saat mengulas
--
-- Prasyarat:
-- - Jalankan 2026-08-25a_pos_print_rule_mode_and_customer_review_foundation.sql
--   terlebih dahulu.
--
-- Catatan:
-- - QR struk tetap unik per nota dan tidak berubah.
-- - QR area tidak membutuhkan nota; pengunjung mengisi nama dan WhatsApp.
-- - Migration ini tidak mengubah ulasan lama maupun data member lama.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_customer_review_station (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  station_code VARCHAR(60) NOT NULL,
  station_name VARCHAR(150) NOT NULL,
  outlet_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_customer_review_station_code (station_code),
  KEY idx_pos_customer_review_station_active (is_active, outlet_id, station_name),
  CONSTRAINT fk_pos_customer_review_station_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='QR ulasan umum yang dapat ditempel di area outlet.';

-- Ulasan dari QR area tidak selalu memiliki nota. Ulasan lama tetap
-- menggunakan order_id dan review_source RECEIPT.
ALTER TABLE pos_customer_review
  MODIFY COLUMN order_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS review_source ENUM('RECEIPT','STATION') NOT NULL DEFAULT 'RECEIPT' AFTER review_token,
  ADD COLUMN IF NOT EXISTS station_id BIGINT UNSIGNED NULL AFTER outlet_id,
  ADD COLUMN IF NOT EXISTS visitor_phone_snapshot VARCHAR(30) NULL AFTER customer_name_snapshot,
  ADD INDEX IF NOT EXISTS idx_pos_customer_review_source_date (review_source, submitted_at),
  ADD INDEX IF NOT EXISTS idx_pos_customer_review_station_date (station_id, submitted_at);

UPDATE pos_customer_review
SET review_source = 'RECEIPT'
WHERE COALESCE(review_source, '') = '';

INSERT INTO pos_customer_review_station (
  station_code,
  station_name,
  outlet_id,
  is_active,
  notes
)
VALUES (
  'GENERAL',
  'QR Ulasan Umum',
  NULL,
  1,
  'QR umum untuk meja, pintu keluar, atau area pengunjung.'
)
ON DUPLICATE KEY UPDATE
  station_name = VALUES(station_name),
  is_active = VALUES(is_active),
  notes = VALUES(notes),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT 'pos_customer_review_station' AS check_key, COUNT(*) AS total_rows
FROM pos_customer_review_station
UNION ALL
SELECT 'pos_customer_review.review_source', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_customer_review'
  AND COLUMN_NAME = 'review_source'
UNION ALL
SELECT 'pos_customer_review.station_id', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_customer_review'
  AND COLUMN_NAME = 'station_id';
