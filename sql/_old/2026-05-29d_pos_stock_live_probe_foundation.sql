SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-05-29d_pos_stock_live_probe_foundation.sql
-- Tujuan :
-- 1) Menyiapkan tabel log rebuild availability cache POS
-- 2) Menyiapkan tabel probe audit DB cache vs kalkulasi live
-- 3) Menjadi fondasi halaman /pos/stock-live dan service rebuild
-- Catatan:
-- - Aman dijalankan ulang.
-- - Tidak mengubah cache utama, hanya menambah jejak audit.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_product_availability_rebuild_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_source VARCHAR(60) NOT NULL,
  event_table VARCHAR(80) NULL,
  event_id BIGINT UNSIGNED NULL,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  cache_status_before VARCHAR(20) NULL,
  cache_qty_before DECIMAL(18,4) NOT NULL DEFAULT 0,
  cache_status_after VARCHAR(20) NULL,
  cache_qty_after DECIMAL(18,4) NOT NULL DEFAULT 0,
  live_status VARCHAR(20) NULL,
  live_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  live_hpp DECIMAL(18,6) NOT NULL DEFAULT 0,
  mismatch_flag TINYINT(1) NOT NULL DEFAULT 0,
  mismatch_note VARCHAR(255) NULL,
  actor_employee_id BIGINT UNSIGNED NULL,
  rebuilt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ppa_rebuild_outlet_product (outlet_id, product_id),
  KEY idx_ppa_rebuild_event (event_source, event_table, event_id),
  KEY idx_ppa_rebuild_mismatch (mismatch_flag),
  KEY idx_ppa_rebuild_at (rebuilt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_product_availability_probe (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  outlet_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  cache_status VARCHAR(20) NULL,
  cache_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  live_status VARCHAR(20) NULL,
  live_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  mismatch_flag TINYINT(1) NOT NULL DEFAULT 0,
  trigger_context VARCHAR(60) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ppa_probe_outlet_product (outlet_id, product_id),
  KEY idx_ppa_probe_mismatch (mismatch_flag),
  KEY idx_ppa_probe_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_product_availability_probe_line (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  probe_id BIGINT UNSIGNED NOT NULL,
  line_no INT UNSIGNED NOT NULL DEFAULT 1,
  source_kind VARCHAR(20) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  source_name_snapshot VARCHAR(180) NULL,
  source_role VARCHAR(20) NOT NULL DEFAULT 'MAIN',
  required_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  available_qty_live DECIMAL(18,4) NOT NULL DEFAULT 0,
  short_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  is_bottleneck TINYINT(1) NOT NULL DEFAULT 0,
  cost_source VARCHAR(40) NULL,
  PRIMARY KEY (id),
  KEY idx_ppa_probe_line_probe (probe_id, line_no),
  KEY idx_ppa_probe_line_source (source_kind, source_id),
  CONSTRAINT fk_ppa_probe_line_probe
    FOREIGN KEY (probe_id) REFERENCES pos_product_availability_probe(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

SELECT 'pos_product_availability_rebuild_log' AS table_name, COUNT(*) AS total_rows
FROM pos_product_availability_rebuild_log
UNION ALL
SELECT 'pos_product_availability_probe', COUNT(*)
FROM pos_product_availability_probe
UNION ALL
SELECT 'pos_product_availability_probe_line', COUNT(*)
FROM pos_product_availability_probe_line;
