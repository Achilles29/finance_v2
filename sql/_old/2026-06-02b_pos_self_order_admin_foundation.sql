SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-02b_pos_self_order_admin_foundation.sql
-- Tujuan :
-- 1) Menyiapkan fondasi self order via scan QR meja
-- 2) Menyediakan tabel admin finance untuk setting, meja, secret QR, dan QRIS Midtrans
-- 3) Tetap kompatibel dengan flow member yang sudah ada
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS pos_self_order_setting (
  id TINYINT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  member_base_url VARCHAR(255) NOT NULL DEFAULT 'http://localhost/member/',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pos_self_order_setting (id, is_enabled, member_base_url, notes)
VALUES (1, 1, 'http://localhost/member/', NULL)
ON DUPLICATE KEY UPDATE
  is_enabled = is_enabled;

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

INSERT INTO pos_self_order_qr_secret (id, secret, enforce)
VALUES (1, SHA2(CONCAT('finance-self-order-', DATABASE(), '-20260602'), 256), 1)
ON DUPLICATE KEY UPDATE
  enforce = enforce;

CREATE TABLE IF NOT EXISTS pos_self_order_qris_setting (
  id TINYINT UNSIGNED NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  midtrans_server_key VARCHAR(255) NULL,
  midtrans_client_key VARCHAR(255) NULL,
  midtrans_is_production TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pos_self_order_qris_setting (id, is_enabled, midtrans_server_key, midtrans_client_key, midtrans_is_production)
VALUES (1, 0, NULL, NULL, 0)
ON DUPLICATE KEY UPDATE
  is_enabled = is_enabled;

COMMIT;

SELECT 'pos_self_order_setting' AS table_name, COUNT(*) AS total_rows FROM pos_self_order_setting
UNION ALL
SELECT 'pos_self_order_table', COUNT(*) FROM pos_self_order_table
UNION ALL
SELECT 'pos_self_order_qr_secret', COUNT(*) FROM pos_self_order_qr_secret
UNION ALL
SELECT 'pos_self_order_qris_setting', COUNT(*) FROM pos_self_order_qris_setting;
