SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-21e_pos_product_availability_queue.sql
-- Tujuan :
-- 1) Memindahkan rebuild cache ketersediaan produk POS dari request
--    inventory ke antrean terpisah yang dapat diproses bertahap
-- 2) Menggabungkan perubahan berulang untuk outlet + produk yang sama
-- 3) Menjaga writer PO/SR, adjustment, dan produksi tetap cepat tanpa
--    mengubah stok, lot, movement, order, HPP, atau data historis
--
-- Catatan deploy:
-- - Jalankan sebelum deploy kode queue availability POS.
-- - Jika tabel ini belum ada, aplikasi baru tetap fallback ke rebuild
--   sinkron agar transaksi tidak berhenti.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_product_availability_queue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  status ENUM('QUEUED','PROCESSING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  event_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
  run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  event_source VARCHAR(80) NULL,
  event_table VARCHAR(80) NULL,
  event_id BIGINT UNSIGNED NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  result_json LONGTEXT NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pos_product_availability_queue_target (outlet_id, product_id),
  KEY idx_pos_product_availability_queue_ready (status, run_after, id),
  KEY idx_pos_product_availability_queue_product (product_id, status),
  CONSTRAINT fk_pos_product_availability_queue_outlet
    FOREIGN KEY (outlet_id) REFERENCES pos_outlet(id),
  CONSTRAINT fk_pos_product_availability_queue_product
    FOREIGN KEY (product_id) REFERENCES mst_product(id),
  CONSTRAINT fk_pos_product_availability_queue_actor
    FOREIGN KEY (actor_employee_id) REFERENCES org_employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

SELECT
  status,
  COUNT(*) AS total_rows
FROM pos_product_availability_queue
GROUP BY status
ORDER BY status;
