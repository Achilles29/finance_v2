SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-02d_pos_self_order_prefix_alignment.sql
-- Tujuan :
-- 1) Menstandarkan tabel self order finance ke prefix `pos_`
-- 2) Memindahkan data dari tabel legacy `pr_*` bila sudah ada
-- 3) Menjaga kompatibilitas bertahap dengan modul member lama
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_self_order_table (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_meja VARCHAR(100) NOT NULL,
  qr_label VARCHAR(120) NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_self_order_table_active (is_active),
  KEY idx_pos_self_order_table_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_self_order_qr_secret (
  id TINYINT UNSIGNED NOT NULL,
  secret VARCHAR(128) NOT NULL,
  enforce TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS pos_self_order_qris_setting (
  id TINYINT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  midtrans_server_key VARCHAR(255) NULL,
  midtrans_client_key VARCHAR(255) NULL,
  midtrans_is_production TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_pr_meja := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pr_meja'
);
SET @sql_copy_pr_meja := IF(
  @has_pr_meja > 0,
  'INSERT INTO pos_self_order_table (id, nama_meja, qr_label, capacity, sort_order, is_active, created_at, updated_at)
   SELECT id, nama_meja, qr_label, capacity, sort_order, is_active, created_at, updated_at
   FROM pr_meja
   ON DUPLICATE KEY UPDATE
     nama_meja = VALUES(nama_meja),
     qr_label = VALUES(qr_label),
     capacity = VALUES(capacity),
     sort_order = VALUES(sort_order),
     is_active = VALUES(is_active),
     updated_at = VALUES(updated_at)',
  'SELECT 1'
);
PREPARE stmt_copy_pr_meja FROM @sql_copy_pr_meja; EXECUTE stmt_copy_pr_meja; DEALLOCATE PREPARE stmt_copy_pr_meja;

SET @has_pr_qr_secret := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pr_table_qr_secret'
);
SET @sql_copy_pr_qr_secret := IF(
  @has_pr_qr_secret > 0,
  'INSERT INTO pos_self_order_qr_secret (id, secret, enforce, created_at, updated_at)
   SELECT id, secret, enforce, created_at, updated_at
   FROM pr_table_qr_secret
   ON DUPLICATE KEY UPDATE
     secret = VALUES(secret),
     enforce = VALUES(enforce),
     updated_at = VALUES(updated_at)',
  'SELECT 1'
);
PREPARE stmt_copy_pr_qr_secret FROM @sql_copy_pr_qr_secret; EXECUTE stmt_copy_pr_qr_secret; DEALLOCATE PREPARE stmt_copy_pr_qr_secret;

SET @has_pr_qris := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pr_qris_setting'
);
SET @sql_copy_pr_qris := IF(
  @has_pr_qris > 0,
  'INSERT INTO pos_self_order_qris_setting (id, is_enabled, midtrans_server_key, midtrans_client_key, midtrans_is_production, updated_at)
   SELECT id, is_enabled, midtrans_server_key, midtrans_client_key, midtrans_is_production, updated_at
   FROM pr_qris_setting
   ON DUPLICATE KEY UPDATE
     is_enabled = VALUES(is_enabled),
     midtrans_server_key = VALUES(midtrans_server_key),
     midtrans_client_key = VALUES(midtrans_client_key),
     midtrans_is_production = VALUES(midtrans_is_production),
     updated_at = VALUES(updated_at)',
  'SELECT 1'
);
PREPARE stmt_copy_pr_qris FROM @sql_copy_pr_qris; EXECUTE stmt_copy_pr_qris; DEALLOCATE PREPARE stmt_copy_pr_qris;

COMMIT;

SELECT 'pos_self_order_table' AS table_name, COUNT(*) AS total_rows FROM pos_self_order_table
UNION ALL
SELECT 'pos_self_order_qr_secret', COUNT(*) FROM pos_self_order_qr_secret
UNION ALL
SELECT 'pos_self_order_qris_setting', COUNT(*) FROM pos_self_order_qris_setting;
